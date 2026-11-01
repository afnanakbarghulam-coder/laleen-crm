<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ClientPackage;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\StaffComplaint;
use App\Support\ClientMaintenancePlanner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->withCount([
            'appointments' => fn ($q) => $q->where('status', 'completed'),
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $digits = preg_replace('/\D/', '', $search);

            $query->where(function ($q) use ($search, $digits) {
                $q->where('name', 'like', '%' . $search . '%');
                if ($digits !== '') {
                    $q->orWhere('phone', 'like', '%' . $digits . '%');
                }
            });
        }

        $customers = $query->orderBy('name')->paginate(20)->withQueryString();

        // LTV + last visit, computed in bulk to avoid N+1 per-row queries.
        $customerIds = $customers->pluck('id');

        $ltv = Sale::whereIn('customer_id', $customerIds)
            ->get(['customer_id', 'services_total', 'products_total', 'packages_total', 'discount_amount'])
            ->groupBy('customer_id')
            ->map(fn ($sales) => round((float) $sales->sum(fn ($s) => max(0, (float) $s->services_total + (float) $s->products_total + (float) $s->packages_total - (float) $s->discount_amount)), 2));

        $lastVisit = Appointment::whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->selectRaw('customer_id, MAX(appointment_datetime) as last_visit')
            ->groupBy('customer_id')
            ->pluck('last_visit', 'customer_id');

        $dueCustomerIds = (new ClientMaintenancePlanner())->dueQueue()->pluck('customer_id')->unique();

        return view('customers.index', compact('customers', 'ltv', 'lastVisit', 'dueCustomerIds'));
    }

    /**
     * Beauty Planning: front-desk task queue of clients whose re-booking
     * window is overdue or due soon for a specific treatment, ready to
     * message right away.
     */
    public function beautyPlanning()
    {
        $queue = (new ClientMaintenancePlanner())->dueQueue();

        return view('customers.beauty_planning', compact('queue'));
    }

    public function show(Customer $customer)
    {
        $customer->loadCount([
            'appointments' => fn ($q) => $q->where('status', 'completed'),
        ]);

        $appointments = $customer->appointments()
            ->with('staff')
            ->orderByDesc('appointment_datetime')
            ->get();

        $upcoming = $appointments->filter(fn($a) => $a->appointment_datetime->isFuture() && $a->status === 'pending')->sortBy('appointment_datetime');
        $past = $appointments->filter(fn($a) => !$a->appointment_datetime->isFuture() || $a->status !== 'pending');

        $lifetimeValue = round((float) Sale::where('customer_id', $customer->id)
            ->get(['services_total', 'products_total', 'packages_total', 'discount_amount'])
            ->sum(fn ($s) => max(0, (float) $s->services_total + (float) $s->products_total + (float) $s->packages_total - (float) $s->discount_amount)), 2);

        $favoriteServices = $appointments
            ->where('status', 'completed')
            ->flatMap(fn($a) => array_map('trim', explode(',', (string) $a->service_name)))
            ->filter(fn($name) => $name !== '' && strcasecmp($name, 'Decide in Salon') !== 0)
            ->countBy()
            ->sortDesc()
            ->take(5);

        $loyaltyHistory = $customer->loyaltyTransactions()->take(10)->get();

        $maintenanceSchedule = (new ClientMaintenancePlanner())->scheduleForCustomer($customer);

        // Reconcile against real time before displaying, so an expired
        // package never shows stale "pending" services here.
        ClientPackage::expireDue($customer->id);
        $clientPackages = $customer->clientPackages()->with('services', 'redeemedServices')->get();

        return view('customers.show', compact('customer', 'appointments', 'upcoming', 'past', 'lifetimeValue', 'favoriteServices', 'loyaltyHistory', 'maintenanceSchedule', 'clientPackages'));
    }

    public function store(Request $request)
    {
        if ($request->filled('phone')) {
            $normalizedPhone = Customer::normalizePhone($request->input('phone'));
            if ($normalizedPhone !== '') {
                $request->merge(['phone' => $normalizedPhone]);
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $rawDigits = preg_replace('/\D+/', '', (string) $validated['phone']);
        $phoneCandidates = array_values(array_unique(array_filter([$validated['phone'], $rawDigits])));

        $customer = Customer::whereIn('phone', $phoneCandidates)->first();
        if ($customer) {
            $customer->update([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? $customer->email,
            ]);
        } else {
            $customer = Customer::create($validated);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'customer' => $customer]);
        }

        return redirect()->route('customers.show', $customer)->with('success', 'Client profile saved.');
    }

    public function update(Request $request, Customer $customer)
    {
        if ($request->filled('phone')) {
            $normalizedPhone = Customer::normalizePhone($request->input('phone'));
            if ($normalizedPhone !== '') {
                $request->merge(['phone' => $normalizedPhone]);
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255', Rule::unique('customers', 'phone')->ignore($customer->id)],
        ]);

        $customer->update($validated);

        // Propagate updated client name and normalized phone to linked Appointments, Leads, and Complaints
        Appointment::where('customer_id', $customer->id)->update([
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
        ]);

        Lead::where('customer_id', $customer->id)->update([
            'phone' => $customer->phone,
        ]);

        StaffComplaint::where('customer_id', $customer->id)->update([
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
        ]);

        return back()->with('success', 'Client profile updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->appointments()->exists() || $customer->sales()->exists()) {
            return back()->with('error', 'Cannot delete a client with existing appointments or sales history.');
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Client deleted successfully.');
    }

    public function updateNotes(Request $request, Customer $customer)
    {
        $request->validate(['notes' => 'nullable|string|max:5000']);
        $customer->update(['notes' => $request->notes]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Client notes updated.');
    }

    public function updateAllergies(Request $request, Customer $customer)
    {
        $request->validate(['allergies' => 'nullable|string|max:2000']);
        $customer->update(['allergies' => $request->allergies]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Allergy information updated.');
    }

    public function redeemPoints(Request $request, Customer $customer)
    {
        $request->validate([
            'points' => 'required|integer|min:1|max:' . max($customer->loyalty_points, 1),
            'reward' => 'required|string|max:255',
        ]);

        try {
            $customer->redeemPoints((int) $request->points, $request->reward, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Redeemed {$request->points} points for {$request->reward}.");
    }

    /**
     * Live client search for the booking drawer's "Select a client" panel.
     * Matches by name or phone, returns a short list for a picker UI.
     */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = Customer::query();

        if ($q !== '') {
            $digits = preg_replace('/\D/', '', $q);
            $query->where(function ($sub) use ($q, $digits) {
                $sub->where('name', 'like', '%' . $q . '%');
                if ($digits !== '') {
                    $sub->orWhere('phone', 'like', '%' . $digits . '%');
                }
            });
        }

        $customers = $query->orderBy('name')->limit(15)->get(['id', 'name', 'phone']);

        return response()->json($customers->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name ?: 'Unnamed',
            'phone' => $c->phone,
            'initials' => $c->name ? strtoupper(substr($c->name, 0, 1)) : '?',
        ]));
    }

    /**
     * This client's still-redeemable combo package services, for the
     * booking drawer's "Redeem Package Service" picker - fetched once a
     * client is selected so staff can apply what's left from an earlier
     * purchase directly onto a new appointment.
     */
    public function pendingPackages(Customer $customer)
    {
        return response()->json($customer->redeemablePackageServices());
    }

    /**
     * Quick phone lookup used by the booking modal to auto-fill returning customers.
     */
    public function lookup(Request $request)
    {
        $phone = preg_replace('/\D/', '', $request->query('phone', ''));

        if (strlen($phone) < 4) {
            return response()->json(['found' => false]);
        }

        $customer = Customer::where('phone', 'like', '%' . $phone . '%')->first();

        if (!$customer) {
            return response()->json(['found' => false]);
        }

        $visitCount = $customer->appointments()->where('status', 'completed')->count();
        $lastVisit = $customer->appointments()
            ->where('status', 'completed')
            ->orderByDesc('appointment_datetime')
            ->value('appointment_datetime');

        return response()->json([
            'found' => true,
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'visit_count' => $visitCount,
            'last_visit' => $lastVisit ? \Illuminate\Support\Carbon::parse($lastVisit)->format('d M Y') : null,
            'profile_url' => route('customers.show', $customer->id),
        ]);
    }
}
