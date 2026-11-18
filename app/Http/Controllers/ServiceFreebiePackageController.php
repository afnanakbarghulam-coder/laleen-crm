<?php

namespace App\Http\Controllers;

use App\Models\ClientFreebieCredit;
use App\Models\Service;
use App\Models\ServiceFreebiePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceFreebiePackageController extends Controller
{
    public function index()
    {
        $packages = ServiceFreebiePackage::with(['anchorService.staff', 'eligibleServices'])
            ->orderBy('name')
            ->get();

        return response()->json($packages->map(fn (ServiceFreebiePackage $pkg) => $this->formatPackage($pkg))->values());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $eligibleServiceIds = $data['eligible_service_ids'];
        unset($data['eligible_service_ids']);

        $package = DB::transaction(function () use ($data, $eligibleServiceIds) {
            $pkg = ServiceFreebiePackage::create($data);
            $pkg->eligibleServices()->sync($eligibleServiceIds);

            return $pkg->load(['anchorService.staff', 'eligibleServices']);
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Service + Freebies package created.',
                'package' => $this->formatPackage($package),
            ], 201);
        }

        return redirect()->route('services.index', ['tab' => 'freebies'])
            ->with('success', 'Service + Freebies package created.');
    }

    public function update(Request $request, ServiceFreebiePackage $serviceFreebiePackage)
    {
        $data = $this->validated($request);
        $eligibleServiceIds = array_map('intval', $data['eligible_service_ids']);
        unset($data['eligible_service_ids']);

        $hasActiveCredits = $serviceFreebiePackage->credits()
            ->whereIn('status', [
                ClientFreebieCredit::STATUS_LOCKED_UNTIL_PAID,
                ClientFreebieCredit::STATUS_AVAILABLE,
                ClientFreebieCredit::STATUS_SCHEDULED,
            ])
            ->exists();

        if ($hasActiveCredits) {
            $existingIds = $serviceFreebiePackage->eligibleServices()
                ->pluck('services.id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $removedIds = array_diff($existingIds, $eligibleServiceIds);

            if (!empty($removedIds)) {
                throw ValidationException::withMessages([
                    'eligible_service_ids' => 'Cannot remove eligible mini-services while clients hold active freebie credits.',
                ]);
            }
        }

        DB::transaction(function () use ($serviceFreebiePackage, $data, $eligibleServiceIds) {
            $serviceFreebiePackage->update($data);
            $serviceFreebiePackage->eligibleServices()->sync($eligibleServiceIds);
        });

        $serviceFreebiePackage->load(['anchorService.staff', 'eligibleServices']);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Service + Freebies package updated.',
                'package' => $this->formatPackage($serviceFreebiePackage),
            ]);
        }

        return redirect()->route('services.index', ['tab' => 'freebies'])
            ->with('success', 'Service + Freebies package updated.');
    }

    public function destroy(Request $request, ServiceFreebiePackage $serviceFreebiePackage)
    {
        if ($serviceFreebiePackage->credits()->exists() || $serviceFreebiePackage->appointments()->exists()) {
            $message = 'This Service + Freebies package has already been booked by clients and cannot be deleted. Set it to Inactive instead.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $serviceFreebiePackage->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Service + Freebies package deleted.',
            ]);
        }

        return redirect()->route('services.index', ['tab' => 'freebies'])
            ->with('success', 'Service + Freebies package deleted.');
    }

    private function validated(Request $request): array
    {
        if (!$request->has('eligible_service_ids') && $request->has('service_ids')) {
            $request->merge(['eligible_service_ids' => $request->input('service_ids')]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'anchor_service_id' => 'required|integer|exists:services,id',
            'price' => 'required|numeric|min:0',
            'free_choices_count' => 'nullable|integer|min:1|max:20',
            'validity_days' => 'nullable|integer|min:1|max:3650',
            'is_active' => 'nullable|boolean',
            'status' => 'nullable|in:active,inactive',
            'eligible_service_ids' => 'required|array|min:1',
            'eligible_service_ids.*' => 'integer|exists:services,id|distinct',
        ]);

        $freeChoicesCount = !empty($data['free_choices_count']) ? (int) $data['free_choices_count'] : 2;
        $validityDays = !empty($data['validity_days']) ? (int) $data['validity_days'] : 7;

        if ($freeChoicesCount > count($data['eligible_service_ids'])) {
            throw ValidationException::withMessages([
                'free_choices_count' => 'Free mini-services count cannot exceed the number of eligible mini-services in the pool.',
            ]);
        }

        $isActive = true;
        if ($request->has('is_active')) {
            $isActive = $request->boolean('is_active');
        } elseif (!empty($data['status'])) {
            $isActive = $data['status'] === 'active';
        }

        return [
            'name' => trim($data['name']),
            'anchor_service_id' => (int) $data['anchor_service_id'],
            'price' => round((float) $data['price'], 2),
            'free_choices_count' => $freeChoicesCount,
            'validity_days' => $validityDays,
            'is_active' => $isActive,
            'eligible_service_ids' => array_values(array_map('intval', $data['eligible_service_ids'])),
        ];
    }

    public function formatPackage(ServiceFreebiePackage $package): array
    {
        $anchor = $package->anchorService;
        $staffCount = $anchor && $anchor->relationLoaded('staff')
            ? $anchor->staff->count()
            : ($anchor ? $anchor->staff()->count() : 0);

        $eligibleServices = $package->eligibleServices->map(fn (Service $s) => [
            'id' => (int) $s->id,
            'name' => $s->name,
            'price' => (float) $s->price,
            'duration' => (int) ($s->duration ?: 30),
        ])->values();

        return [
            'id' => (int) $package->id,
            'name' => $package->name,
            'anchor_service_id' => (int) $package->anchor_service_id,
            'anchor_service_name' => $anchor?->name ?? 'Anchor Service',
            'anchor_duration' => (int) ($anchor?->duration ?: 60),
            'anchor_staff_count' => (int) $staffCount,
            'price' => (float) $package->price,
            'free_choices_count' => (int) $package->effective_choices_count,
            'validity_days' => (int) $package->effective_validity_days,
            'is_active' => (bool) $package->is_active,
            'eligible_services' => $eligibleServices,
        ];
    }
}
