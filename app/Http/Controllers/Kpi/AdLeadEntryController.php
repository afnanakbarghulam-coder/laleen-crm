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
        $this->syncLeadFromAdEntry($entry);

        $entry = $entry->fresh();
        $synced = AdLeadEntry::syncFromCustomerActivity(
            customer: $entry->customer,
            activityDate: Carbon::parse($entry->date)->endOfMonth(),
            phone: $entry->phone,
            targetEntry: $entry,
            resetIfNoActivity: !$hasManualBranch
        );
        if ($synced) {
            $this->syncLeadFromAdEntry($synced);
        }

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
        $this->syncLeadFromAdEntry($entry);

        $entry = $entry->fresh();
        $synced = AdLeadEntry::syncFromCustomerActivity(
            customer: $entry->customer,
            activityDate: Carbon::parse($entry->date)->endOfMonth(),
            phone: $entry->phone,
            targetEntry: $entry,
            resetIfNoActivity: !$hasManualBranch
        );
        if ($synced) {
            $this->syncLeadFromAdEntry($synced);
        }

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

        $rawCombined = Lead::normalizePhone($validated['country_code'], $validated['phone_number']);
        $phone = Customer::normalizePhone($rawCombined);
        if ($phone === '') {
            $phone = $rawCombined;
        }
        unset($validated['country_code'], $validated['phone_number']);
        $validated['phone'] = $phone;

        $customerName = trim($validated['customer_name']);
        $rawDigits = preg_replace('/\D/', '', $phone);
        $candidates = array_values(array_unique(array_filter([$phone, $rawCombined, $rawDigits])));

        $customer = Customer::whereIn('phone', $candidates)->first();
        if (!$customer) {
            $customer = Customer::create([
                'phone' => $phone,
                'name' => $customerName,
            ]);
        } elseif ((empty($customer->name) || strtolower($customer->name) === 'walk-in') && $customerName !== '') {
            $customer->update(['name' => $customerName]);
        }

        if (Schema::hasColumn('ad_lead_entries', 'customer_id')) {
            $validated['customer_id'] = $customer->id;
        }
        if (Schema::hasColumn('ad_lead_entries', 'customer_name')) {
            $validated['customer_name'] = $customerName;
        } else {
            unset($validated['customer_name']);
        }
        $validated['ticket_amount'] = $validated['ticket_amount'] ?? 0;

        return $validated;
    }

    private function syncLeadFromAdEntry(AdLeadEntry $entry): void
    {
        if (empty($entry->phone) || !Schema::hasTable('leads')) {
            return;
        }

        $rawDigits = preg_replace('/\D/', '', (string) $entry->phone);
        $candidates = array_values(array_unique(array_filter([$entry->phone, $rawDigits])));
        $hasLeadIdColumn = Schema::hasColumn('ad_lead_entries', 'lead_id');
        $entryDate = $entry->date ? Carbon::parse($entry->date) : now();

        $existingLead = null;
        if ($hasLeadIdColumn && $entry->lead_id) {
            $existingLead = Lead::find($entry->lead_id);
        }

        if (!$existingLead) {
            $existingLead = Lead::open()
                ->where(function ($q) use ($entry, $candidates) {
                    if ($entry->customer_id) {
                        $q->where('customer_id', $entry->customer_id);
                    }
                    if (!empty($candidates)) {
                        $q->orWhereIn('phone', $candidates);
                    }
                })
                ->latest('id')
                ->first();
        }

        if (!$existingLead) {
            $existingLead = Lead::query()
                ->where(function ($q) use ($entry, $candidates) {
                    if ($entry->customer_id) {
                        $q->where('customer_id', $entry->customer_id);
                    }
                    if (!empty($candidates)) {
                        $q->orWhereIn('phone', $candidates);
                    }
                })
                ->whereYear('created_at', $entryDate->year)
                ->whereMonth('created_at', $entryDate->month)
                ->latest('id')
                ->first();
        }

        $interestList = $entry->category ? [$entry->category] : [];

        if (!$existingLead) {
            $newLead = Lead::create([
                'phone' => $entry->phone,
                'customer_id' => $entry->customer_id,
                'category' => 'inquiry',
                'categories' => ['inquiry'],
                'service_interest' => $interestList,
                'customer_remarks' => $entry->remarks,
                'needful_done' => $entry->isBooked() ? 'booked' : null,
                'assigned_agent_id' => $entry->created_by ?? auth()->id(),
            ]);

            if ($hasLeadIdColumn && (int) $entry->lead_id !== (int) $newLead->id) {
                $entry->update(['lead_id' => $newLead->id]);
            }
            return;
        }

        $updates = [];
        if ($entry->customer_id && !$existingLead->customer_id) {
            $updates['customer_id'] = $entry->customer_id;
        }
        if ($entry->isBooked() && $existingLead->needful_done !== 'booked') {
            $updates['needful_done'] = 'booked';
            $updates['next_followup_date'] = null;
        }
        if (!empty($interestList)) {
            $currentInterests = is_array($existingLead->service_interest) ? $existingLead->service_interest : [];
            $mergedInterests = array_values(array_unique(array_merge($currentInterests, $interestList)));
            if ($mergedInterests !== $currentInterests) {
                $updates['service_interest'] = $mergedInterests;
            }
        }

        if (!empty($updates)) {
            $existingLead->update($updates);
        }

        if ($hasLeadIdColumn && (int) $entry->lead_id !== (int) $existingLead->id) {
            $entry->update(['lead_id' => $existingLead->id]);
        }
    }
}
