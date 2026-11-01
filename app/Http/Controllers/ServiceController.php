<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackageService;
use App\Models\Combo;
use App\Models\Lead;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $categories = ServiceCategory::withCount('services')->orderBy('sort_order')->get();

        $query = Service::with(['category', 'staff'])->orderBy('name');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $services = $query->get()->groupBy(fn($s) => $s->category->name ?? 'Uncategorized');
        $staffList = Staff::orderBy('name')->get();

        // Flat catalog (not grouped) for the Combos tab's service picker, and
        // the combos themselves with their bundled services preloaded.
        $allServices = Service::orderBy('name')->get();
        $combos = Combo::with('services')->orderBy('name')->get();

        return view('services.index', compact('categories', 'services', 'staffList', 'allServices', 'combos'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $photoPath = $this->handlePhoto($request);

        $service = Service::create(array_merge($validated, ['photo' => $photoPath]));
        $service->staff()->sync($request->input('staff_ids', []));

        return back()->with('success', 'Service added successfully.');
    }

    public function update(Request $request, Service $service)
    {
        $validated = $this->validated($request);
        $oldName = trim((string) $service->name);
        $oldPhoto = $service->photo;

        if ($request->hasFile('photo')) {
            $validated['photo'] = $this->handlePhoto($request);
            $this->deletePhotoFile($oldPhoto);
        }

        DB::transaction(function () use ($service, $validated, $request, $oldName) {
            $service->update($validated);
            $service->staff()->sync($request->input('staff_ids', []));

            $newName = trim((string) $service->name);
            if ($oldName !== '' && strcasecmp($oldName, $newName) !== 0 || $oldName !== $newName) {
                $this->propagateServiceRename($service, $oldName, $newName);
            }
        });

        return back()->with('success', 'Service updated successfully.');
    }

    public function destroy(Service $service)
    {
        $usages = [];
        if ($service->combos()->exists()) {
            $usages[] = 'combo packages';
        }
        if ($service->appointmentServices()->exists()) {
            $usages[] = 'bookings';
        }
        if ($service->clientPackageServices()->exists()) {
            $usages[] = 'client package redemptions';
        }
        if ($service->upsells()->exists()) {
            $usages[] = 'appointment upsells';
        }
        if ($service->complaints()->exists()) {
            $usages[] = 'staff complaints';
        }

        if (!empty($usages)) {
            return back()->with(
                'error',
                sprintf('Cannot delete "%s" because it is linked to existing %s.', $service->name, implode(', ', $usages))
            );
        }

        $this->deletePhotoFile($service->photo);
        $service->delete();

        return back()->with('success', 'Service deleted successfully.');
    }

    private function propagateServiceRename(Service $service, string $oldName, string $newName): void
    {
        // 1. Update open/non-completed AppointmentService rows and resync parent Appointment.service_name
        $openLines = AppointmentService::query()
            ->where(function ($q) use ($service, $oldName) {
                $q->where('service_id', $service->id)
                    ->orWhere('name', $oldName);
            })
            ->whereHas('appointment', fn ($q) => $q->where('status', '!=', 'completed'))
            ->get();

        $affectedAppointmentIds = $openLines->pluck('appointment_id')->unique()->filter()->values();
        if ($openLines->isNotEmpty()) {
            AppointmentService::whereIn('id', $openLines->pluck('id'))
                ->update([
                    'service_id' => $service->id,
                    'name' => $newName,
                ]);

            foreach (Appointment::whereIn('id', $affectedAppointmentIds)->get() as $appointment) {
                $appointment->syncFromServices();
            }
        }

        // Also update any bare open appointments (without appointment_services rows) whose flat service_name mentions $oldName
        $bareAppointments = Appointment::query()
            ->where('status', '!=', 'completed')
            ->whereDoesntHave('appointmentServices')
            ->where('service_name', 'like', '%' . $oldName . '%')
            ->get();

        foreach ($bareAppointments as $appointment) {
            $parts = array_map('trim', explode(',', (string) $appointment->service_name));
            $updatedParts = array_map(
                fn ($p) => strcasecmp($p, $oldName) === 0 ? $newName : $p,
                $parts
            );
            $newServiceString = implode(', ', $updatedParts);
            if ($newServiceString !== $appointment->service_name) {
                $appointment->update(['service_name' => $newServiceString]);
            }
        }

        // 2. Update ClientPackageService rows referencing this service
        ClientPackageService::where('service_id', $service->id)
            ->update(['service_name' => $newName]);

        // 3. Update open Leads whose service_interest array includes the old service name
        $openLeads = Lead::open()
            ->whereNotNull('service_interest')
            ->get();

        foreach ($openLeads as $lead) {
            $interests = $lead->service_interest;
            if (!is_array($interests)) {
                continue;
            }
            $changed = false;
            $updatedInterests = array_map(function ($item) use ($oldName, $newName, &$changed) {
                if (is_string($item) && strcasecmp(trim($item), $oldName) === 0) {
                    $changed = true;
                    return $newName;
                }
                return $item;
            }, $interests);

            if ($changed) {
                $lead->update(['service_interest' => array_values(array_unique($updatedInterests))]);
            }
        }
    }

    private function deletePhotoFile(?string $photoPath): void
    {
        if (!$photoPath) {
            return;
        }
        $fullPath = public_path($photoPath);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:service_categories,id',
            'treatment_type' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'rebooking_interval_days' => 'nullable|integer|min:1|max:730',
        ]);

        $data['price_type'] = 'fixed';

        return $data;
    }

    private function handlePhoto(Request $request): ?string
    {
        if (!$request->hasFile('photo')) {
            return null;
        }

        $destinationPath = public_path('services/photos');
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $file = $request->file('photo');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->move($destinationPath, $fileName);

        return 'services/photos/' . $fileName;
    }
}
