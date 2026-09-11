<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ComboController extends Controller
{
    /**
     * JSON list of combo packages with their bundled services, used by the
     * Combos tab on the Service Catalog page (initial load and refresh after
     * a create/update/delete).
     */
    public function index()
    {
        $combos = Combo::with('services')->orderBy('name')->get();

        return response()->json($combos->map(fn($combo) => $this->formatCombo($combo))->values());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $serviceIds = $data['service_ids'];
        unset($data['service_ids']);

        $combo = Combo::create($data);
        $combo->services()->sync($serviceIds);

        return response()->json([
            'success' => true,
            'message' => 'Combo package added.',
            'combo' => $this->formatCombo($combo->fresh('services')),
        ]);
    }

    public function update(Request $request, Combo $combo)
    {
        $data = $this->validated($request);
        $serviceIds = $data['service_ids'];
        unset($data['service_ids']);

        $combo->update($data);
        $combo->services()->sync($serviceIds);

        return response()->json([
            'success' => true,
            'message' => 'Combo package updated.',
            'combo' => $this->formatCombo($combo->fresh('services')),
        ]);
    }

    public function destroy(Combo $combo)
    {
        if ($combo->clientPackages()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This combo has already been sold to clients and cannot be deleted.',
            ], 422);
        }

        $combo->delete();

        return response()->json(['success' => true, 'message' => 'Combo package deleted.']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'quantity_included' => 'nullable|integer|min:1',
            'validity_days' => 'nullable|integer|min:1|max:3650',
            'status' => 'nullable|in:active,inactive',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'integer|exists:services,id|distinct',
        ]);

        $data['status'] = $data['status'] ?? 'active';

        // Defaults to "take every service in the pool" (the original
        // behavior) when the admin doesn't set a smaller pick count for a
        // "choose any N" style combo.
        $data['quantity_included'] = $data['quantity_included'] ?? count($data['service_ids']);

        if ($data['quantity_included'] > count($data['service_ids'])) {
            throw ValidationException::withMessages([
                'quantity_included' => 'Cannot ask clients to choose more services than are in the pool.',
            ]);
        }

        return $data;
    }

    /**
     * Includes the sum of the bundled services' individual prices alongside
     * the package price, so the UI can show clients how much a combo saves
     * them versus booking each service separately.
     */
    private function formatCombo(Combo $combo): array
    {
        $services = $combo->services->map(fn(Service $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'price' => (float) $s->price,
            'duration' => $s->duration,
        ])->values();

        return [
            'id' => $combo->id,
            'name' => $combo->name,
            'price' => (float) $combo->price,
            'quantity_included' => $combo->quantity_included,
            'validity_days' => $combo->validity_days,
            'status' => $combo->status,
            'services' => $services,
            'regular_price' => round((float) $services->sum('price'), 2),
        ];
    }
}
