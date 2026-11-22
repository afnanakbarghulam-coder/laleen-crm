<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\AdCategory;
use App\Models\AdLeadEntry;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\AdLeadFollowUpSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdLeadEntryController extends Controller
{
    public function store(Request $request)
    {
        $sendToFollowUps = $request->boolean('send_to_followups');
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

        $entry = $entry->fresh();
        $syncedLead = AdLeadFollowUpSyncService::syncFromAdLead(
            entry: $entry,
            followUpDate: $entry->follow_up_date?->toDateString(),
            assignedAgentId: $entry->assigned_agent_id ? (int) $entry->assigned_agent_id : null,
            force: $sendToFollowUps
        );

        $flash = $syncedLead
            ? 'Ad lead logged and synced to Follow-Ups.'
            : 'Ad lead logged.';

        return redirect()->route('kpi.ads.index')->with('success', $flash);
    }

    public function update(Request $request, AdLeadEntry $adLeadEntry)
    {
        $sendToFollowUps = $request->boolean('send_to_followups');
        $previousCustomer = $adLeadEntry->customer;
        $previousCustomerId = $adLeadEntry->customer_id ? (int) $adLeadEntry->customer_id : null;
        $previousPhone = $adLeadEntry->phone;
        $previousDate = $adLeadEntry->date ? Carbon::parse($adLeadEntry->date) : now();
        $previousRemarks = (string) ($adLeadEntry->remarks ?? '');

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

        $entry = $entry->fresh();
        $syncedLead = AdLeadFollowUpSyncService::syncFromAdLead(
            entry: $entry,
            followUpDate: $entry->follow_up_date?->toDateString(),
            assignedAgentId: $entry->assigned_agent_id ? (int) $entry->assigned_agent_id : null,
            force: $sendToFollowUps,
            previousAdRemarks: $previousRemarks
        );

        $flash = $syncedLead
            ? 'Ad lead updated and synced with Follow-Ups.'
            : 'Ad lead updated.';

        return redirect()->route('kpi.ads.index')->with('success', $flash);
    }

    /**
     * Confirm & push an Ad Lead to Lead Management (Follow-Ups) with an explicit Follow-Up Date and Remarks.
     * Never deletes or moves the AdLeadEntry and never creates duplicate Lead rows.
     */
    public function sendToFollowUps(Request $request, AdLeadEntry $adLeadEntry)
    {
        $validated = $request->validate([
            'follow_up_date' => 'required|date',
            'remarks' => 'nullable|string|max:2000',
            'assigned_agent_id' => 'nullable|exists:users,id',
            'status' => ['nullable', 'string', Rule::in(array_keys(AdLeadEntry::MANUAL_STATUSES))],
        ]);

        $previousRemarks = (string) ($adLeadEntry->remarks ?? '');
        $entryUpdates = [];

        if (! empty($validated['status']) && Schema::hasColumn('ad_lead_entries', 'status') && ! $adLeadEntry->isBooked()) {
            $entryUpdates['status'] = $validated['status'];
        }

        if (array_key_exists('remarks', $validated)) {
            $cleanRemarks = trim((string) ($validated['remarks'] ?? ''));
            $entryUpdates['remarks'] = $cleanRemarks !== '' ? $cleanRemarks : null;
        }

        if (Schema::hasColumn('ad_lead_entries', 'follow_up_date') && ! $adLeadEntry->isBooked()) {
            $entryUpdates['follow_up_date'] = Carbon::parse($validated['follow_up_date'])->toDateString();
        }

        if (! empty($entryUpdates)) {
            $adLeadEntry->update($entryUpdates);
        }

        $lead = AdLeadFollowUpSyncService::syncFromAdLead(
            entry: $adLeadEntry->fresh(),
            followUpDate: $validated['follow_up_date'],
            assignedAgentId: ! empty($validated['assigned_agent_id']) ? (int) $validated['assigned_agent_id'] : null,
            force: true,
            previousAdRemarks: $previousRemarks
        );

        $freshEntry = $adLeadEntry->fresh(['lead']);
        $effectiveDueDate = $freshEntry->effectiveFollowUpDate();
        $dueLabel = $effectiveDueDate
            ? ' (Due ' . $effectiveDueDate->format('d M Y') . ')'
            : '';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'entry_id' => $freshEntry->id,
                'lead_id' => $lead?->id,
                'follow_up_date' => $effectiveDueDate?->format('Y-m-d'),
                'follow_up_pill_text' => $effectiveDueDate
                    ? ('In Follow-Ups · Due ' . $effectiveDueDate->format('d M'))
                    : 'In Follow-Ups',
                'follow_up_url' => $freshEntry->followUpUrl(),
                'remarks' => $freshEntry->remarks,
                'status' => $freshEntry->status,
                'assigned_agent_id' => $freshEntry->assigned_agent_id ?: $lead?->assigned_agent_id,
            ]);
        }

        return redirect()->back()->with('success', 'Synced to Follow-Ups' . $dueLabel . '.');
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
        if (! $request->filled('category') && $request->filled('ad_category')) {
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
            'remarks' => 'nullable|string|max:2000',
            'status' => ['nullable', 'string', Rule::in(array_keys(AdLeadEntry::MANUAL_STATUSES))],
            'follow_up_date' => 'nullable|date',
            'assigned_agent_id' => 'nullable|exists:users,id',
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
        if (! $customer) {
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

        // Only persist optional follow-up columns if present in the table and explicitly supplied (preserve nulls cleanly)
        if (! Schema::hasColumn('ad_lead_entries', 'status')) {
            unset($validated['status']);
        } elseif (array_key_exists('status', $validated) && trim((string) $validated['status']) === '') {
            $validated['status'] = null;
        }

        if (! Schema::hasColumn('ad_lead_entries', 'follow_up_date')) {
            unset($validated['follow_up_date']);
        } elseif (array_key_exists('follow_up_date', $validated) && trim((string) $validated['follow_up_date']) === '') {
            $validated['follow_up_date'] = null;
        }

        if (! Schema::hasColumn('ad_lead_entries', 'assigned_agent_id')) {
            unset($validated['assigned_agent_id']);
        } elseif (array_key_exists('assigned_agent_id', $validated) && trim((string) $validated['assigned_agent_id']) === '') {
            $validated['assigned_agent_id'] = null;
        }

        return $validated;
    }
}
