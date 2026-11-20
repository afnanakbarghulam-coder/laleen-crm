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
        $validated = $this->validated($request);
        $validated['branch'] = null;
        $validated['ticket_amount'] = 0;

        if (Schema::hasColumn('ad_lead_entries', 'old_airport_amount')) {
            $validated['old_airport_amount'] = 0;
            $validated['wakrah_amount'] = 0;
        }

        $entry = AdLeadEntry::create($validated + ['created_by' => auth()->id()]);

        $entry = $entry->fresh();
        AdLeadEntry::syncFromCustomerActivity(
            customer: $entry->customer,
            activityDate: Carbon::parse($entry->date),
            phone: $entry->phone,
            targetEntry: $entry,
            resetIfNoActivity: true
        );

        return redirect()->route('kpi.ads.index')->with('success', 'Ad lead logged.');
    }

    public function update(Request $request, AdLeadEntry $adLeadEntry)
    {
        $previousCustomer = $adLeadEntry->customer;
        $previousCustomerId = $adLeadEntry->customer_id ? (int) $adLeadEntry->customer_id : null;
        $previousPhone = $adLeadEntry->phone;
        $previousDate = $adLeadEntry->date ? Carbon::parse($adLeadEntry->date) : now();

        $validated = $this->validated($request);

        $adLeadEntry->update($validated);
        $entry = $adLeadEntry->fresh();

        $oldNorm = Customer::normalizePhone($previousPhone);
        $newNorm = Customer::normalizePhone($entry->phone);
        $identityChanged = ($previousCustomerId && (int) $entry->customer_id !== $previousCustomerId)
            || ($oldNorm !== '' && $oldNorm !== $newNorm);

        if ($identityChanged && ($previousCustomer || $previousPhone)) {
            AdLeadEntry::syncFromCustomerActivity(
                customer: $previousCustomer,
                activityDate: $previousDate,
                phone: $previousPhone,
                resetIfNoActivity: true
            );
        }

        AdLeadEntry::syncFromCustomerActivity(
            customer: $entry->customer,
            activityDate: Carbon::parse($entry->date),
            phone: $entry->phone,
            targetEntry: $entry,
            resetIfNoActivity: true
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

        $rules = array_merge([
            'date' => 'required|date',
            'customer_name' => 'required|string|max:255',
        ], Lead::phoneValidationRules($request->input('country_code')), [
            'category' => $isCreateNew
                ? ['required', 'string']
                : ['required', 'string', 'max:255', Rule::in(AdLeadEntry::availableCategories())],
            'remarks' => 'nullable|string|max:255',
        ]);

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
        $customer = AdLeadEntry::resolveOrCreateCanonicalCustomer($rawCombined, $customerName);
        if (!$customer) {
            $e164Phone = Customer::normalizePhoneE164($rawCombined);
            $customer = Customer::create([
                'phone' => $e164Phone !== '' ? $e164Phone : $phone,
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

        return $validated;
    }
}
