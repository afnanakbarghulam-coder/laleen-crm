<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\AdCategory;
use App\Models\AdLeadEntry;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdLeadEntryController extends Controller
{
    public function store(Request $request)
    {
        $hasManualBranch = $request->filled('branch');
        $validated = $this->validated($request);

        if (!$hasManualBranch) {
            $validated['branch'] = null;
            $validated['ticket_amount'] = 0;
        }

        $entry = AdLeadEntry::create($validated + ['created_by' => auth()->id()]);

        $entry = $entry->fresh();
        AdLeadEntry::syncFromCustomerActivity(
            customer: $entry->customer,
            activityDate: Carbon::parse($entry->date),
            phone: $entry->phone,
            targetEntry: $entry,
            resetIfNoActivity: !$hasManualBranch
        );

        return redirect()->route('kpi.ads.index')->with('success', 'Ad lead logged.');
    }

    public function update(Request $request, AdLeadEntry $adLeadEntry)
    {
        $hasManualBranch = $request->filled('branch');
        $validated = $this->validated($request);

        if (!$hasManualBranch) {
            $validated['branch'] = null;
            $validated['ticket_amount'] = 0;
        }

        $adLeadEntry->update($validated);
        $entry = $adLeadEntry->fresh();

        AdLeadEntry::syncFromCustomerActivity(
            customer: $entry->customer,
            activityDate: Carbon::parse($entry->date),
            phone: $entry->phone,
            targetEntry: $entry,
            resetIfNoActivity: !$hasManualBranch
        );

        return redirect()->route('kpi.ads.index')->with('success', 'Ad lead updated.');
    }

    public function destroy(AdLeadEntry $adLeadEntry)
    {
        $customer = $adLeadEntry->customer;
        $phone = $adLeadEntry->phone;
        $entryDate = $adLeadEntry->date ? Carbon::parse($adLeadEntry->date) : now();

        $adLeadEntry->delete();

        if ($customer || $phone) {
            AdLeadEntry::syncFromCustomerActivity(
                customer: $customer,
                activityDate: $entryDate,
                phone: $phone
            );
        }

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

        $rawCombined = Lead::normalizePhone($validated['country_code'], $validated['phone_number']);
        $phone = Customer::normalizePhone($rawCombined);
        if ($phone === '') {
            $phone = $rawCombined;
        }
        unset($validated['country_code'], $validated['phone_number']);
        $validated['phone'] = $phone;

        $customerName = trim($validated['customer_name']);
        $customer = AdLeadEntry::resolveOrCreateCanonicalCustomer($phone, $customerName);
        if (!$customer) {
            $customer = Customer::create([
                'phone' => $phone,
                'name' => $customerName,
            ]);
        }

        if (Schema::hasColumn('ad_lead_entries', 'customer_id')) {
            $validated['customer_id'] = $customer->id;
        }
        if (Schema::hasColumn('ad_lead_entries', 'customer_name')) {
            $validated['customer_name'] = $customerName;
        } else {
            unset($validated['customer_name']);
        }
        if (Schema::hasColumn('ad_lead_entries', 'old_airport_amount')) {
            $validated['old_airport_amount'] = null;
            $validated['wakrah_amount'] = null;
        }
        $validated['ticket_amount'] = $validated['ticket_amount'] ?? 0;

        return $validated;
    }
}
