<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Support\ClientMaintenancePlanner;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->withCount('appointments');

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
            ->selectRaw('customer_id, SUM(total_amount) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        $lastVisit = \App\Models\Appointment::whereIn('customer_id', $customerIds)
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
        $customer->loadCount('appointments');

        $appointments = $customer->appointments()
            ->with('staff')
            ->orderByDesc('appointment_datetime')
            ->get();

        $upcoming = $appointments->filter(fn($a) => $a->appointment_datetime->isFuture() && $a->status === 'pending')->sortBy('appointment_datetime');
        $past = $appointments->filter(fn($a) => !$a->appointment_datetime->isFuture() || $a->status !== 'pending');

        $lifetimeValue = Sale::where('customer_id', $customer->id)->sum('total_amount');

        $favoriteServices = $appointments
            ->flatMap(fn($a) => array_map('trim', explode(',', $a->service_name)))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(5);

        $loyaltyHistory = $customer->loyaltyTransactions()->take(10)->get();

        $maintenanceSchedule = (new ClientMaintenancePlanner())->scheduleForCustomer($customer);

        return view('customers.show', compact('customer', 'appointments', 'upcoming', 'past', 'lifetimeValue', 'favoriteServices', 'loyaltyHistory', 'maintenanceSchedule'));
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

        $visitCount = $customer->appointments()->count();
        $lastVisit = $customer->appointments()->orderByDesc('appointment_datetime')->value('appointment_datetime');

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
