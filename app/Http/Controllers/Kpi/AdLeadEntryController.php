<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\AdCategory;
use App\Models\AdLeadEntry;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdLeadEntryController extends Controller
{
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        AdLeadEntry::create($validated + ['created_by' => auth()->id()]);

        return redirect()->route('kpi.ads.index')->with('success', 'Ad lead logged.');
    }

    public function update(Request $request, AdLeadEntry $adLeadEntry)
    {
        $adLeadEntry->update($this->validated($request));

        return redirect()->route('kpi.ads.index')->with('success', 'Ad lead updated.');
    }

    public function destroy(AdLeadEntry $adLeadEntry)
    {
        $adLeadEntry->delete();

        return redirect()->route('kpi.ads.index')->with('success', 'Ad lead deleted.');
    }

    private function validated(Request $request): array
    {
        if (!$request->filled('category') && $request->filled('ad_category')) {
            $request->merge(['category' => $request->input('ad_category')]);
        }

        $isCreateNew = $request->input('category') === 'create_new'
            || $request->input('ad_category') === 'create_new';

        $rules = [
            'date' => 'required|date',
            'customer_name' => 'required|string|max:255',
            'country_code' => 'required|string|max:5',
            'phone_number' => 'required|string|max:20',
            'category' => $isCreateNew
                ? ['required', 'string']
                : ['required', 'string', 'max:255', Rule::in(AdLeadEntry::availableCategories())],
            'ticket_amount' => 'nullable|numeric|min:0',
            'branch' => ['nullable', Rule::in(array_keys(AdLeadEntry::BRANCHES))],
            'remarks' => 'nullable|string|max:255',
        ];

        if ($isCreateNew) {
            $rules['new_category'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        if ($isCreateNew) {
            $newCategory = trim($validated['new_category']);
            if (Schema::hasTable('ad_categories')) {
                AdCategory::firstOrCreate(['name' => $newCategory]);
            }
            $validated['category'] = $newCategory;
            unset($validated['new_category']);
        }

        $phone = Lead::normalizePhone($validated['country_code'], $validated['phone_number']);
        unset($validated['country_code'], $validated['phone_number']);
        $validated['phone'] = $phone;

        $customerName = trim($validated['customer_name']);
        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            ['name' => $customerName]
        );
        if (empty($customer->name) && $customerName !== '') {
            $customer->update(['name' => $customerName]);
        }

        $validated['customer_id'] = $customer->id;
        $validated['customer_name'] = $customerName;
        $validated['ticket_amount'] = $validated['ticket_amount'] ?? 0;

        return $validated;
    }
}
