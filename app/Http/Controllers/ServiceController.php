<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use Illuminate\Http\Request;

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

        return view('services.index', compact('categories', 'services', 'staffList'));
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

        if ($request->hasFile('photo')) {
            $validated['photo'] = $this->handlePhoto($request);
        }

        $service->update($validated);
        $service->staff()->sync($request->input('staff_ids', []));

        return back()->with('success', 'Service updated successfully.');
    }

    public function destroy(Service $service)
    {
        $service->delete();
        return back()->with('success', 'Service deleted successfully.');
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
            'maintenance_interval_days' => 'nullable|integer|min:1|max:730',
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
