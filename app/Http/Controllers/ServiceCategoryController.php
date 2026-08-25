<?php

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class ServiceCategoryController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:service_categories,name',
            'color' => 'nullable|string|max:7',
        ]);

        ServiceCategory::create([
            'name' => $request->name,
            'color' => $request->color ?: '#3f8cff',
            'sort_order' => ServiceCategory::max('sort_order') + 1,
        ]);

        return back()->with('success', 'Category added.');
    }

    public function destroy(ServiceCategory $serviceCategory)
    {
        if ($serviceCategory->services()->exists()) {
            return back()->with('error', 'Move or delete the services in this category first.');
        }

        $serviceCategory->delete();

        return back()->with('success', 'Category removed.');
    }
}
