<?php

namespace App\Services;

use App\Models\AdLeadEntry;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdLeadFollowUpSyncService
{
    /**
     * Re-entrancy guard preventing infinite observer / cross-model sync loops.
     */
    private static bool $isSyncing = false;

    public static function isSyncing(): bool
    {
        return self::$isSyncing;
    }

    /**
     * Execute a callback with loop-prevention enabled.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn|null
     */
    private static function withoutLoop(callable $callback): mixed
    {
        if (self::$isSyncing) {
            return null;
        }

        self::$isSyncing = true;

        try {
            return $callback();
        } finally {
            self::$isSyncing = false;
        }
    }

    /**
     * Determine whether an AdLeadEntry should create/update a linked Lead Management follow-up row.
     */
    public static function shouldSyncFromAdLead(AdLeadEntry $entry, bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        if ($entry->lead_id !== null) {
            return true;
        }

        $status = strtolower(trim((string) ($entry->status ?? '')));
        if ($status !== '' && in_array($status, AdLeadEntry::FOLLOW_UP_TRIGGER_STATUSES, true)) {
            return true;
        }

        if ($entry->follow_up_date !== null) {
            return true;
        }

        return false;
    }

    /**
     * Sync an AdLeadEntry forward into Lead Management (Follow-Ups).
     *
     * - Never deletes or moves the AdLeadEntry (preserves campaign & 14-day rolling revenue attribution).
     * - Deduplicates via existing FK link (`ad_lead_entries.lead_id` / `leads.ad_lead_id`) + normalized E.164 phone check.
     * - Carries forward customer name, phone, service interest, branch, ad source/campaign, assigned agent,
     *   follow-up date (defaults to next day in Asia/Qatar if not specified), and remarks without duplicate appending.
     */
    public static function syncFromAdLead(
        AdLeadEntry $entry,
        ?string $followUpDate = null,
        ?int $assignedAgentId = null,
        bool $force = false,
        ?string $previousAdRemarks = null
    ): ?Lead {
        if (! Schema::hasTable('leads') || ! Schema::hasTable('ad_lead_entries')) {
            return null;
        }

        if (! self::shouldSyncFromAdLead($entry, $force)) {
            return null;
        }

        return self::withoutLoop(function () use ($entry, $followUpDate, $assignedAgentId, $force, $previousAdRemarks): ?Lead {
            return DB::transaction(function () use ($entry, $followUpDate, $assignedAgentId, $force, $previousAdRemarks): ?Lead {
                $entry->refresh();

                // 1. Resolve or create canonical E.164 Customer profile
                $canonicalCustomer = AdLeadEntry::resolveOrCreateCanonicalCustomer(
                    rawPhone: $entry->phone,
                    customerName: $entry->customer_name,
                    hintCustomer: $entry->customer
                );

                // Normalize phone into Lead's storage convention while keeping E.164 on Customer
                [$cc, $local] = Lead::splitPhone((string) ($entry->phone ?: $canonicalCustomer?->phone));
                $leadPhone = Lead::normalizePhone($cc, $local);
                if ($leadPhone === '') {
                    $leadPhone = Customer::normalizePhone((string) $entry->phone) ?: trim((string) $entry->phone);
                }

                // 2. Deduplicate: check existing FK link first, then reverse FK, then open Lead by normalized phone / customer_id
                $wasAlreadyLinked = false;
                $lead = null;

                if ($entry->lead_id) {
                    $lead = Lead::find($entry->lead_id);
                    if ($lead) {
                        $wasAlreadyLinked = true;
                    }
                }

                if (! $lead && Schema::hasColumn('leads', 'ad_lead_id')) {
                    $lead = Lead::where('ad_lead_id', $entry->id)->orderByDesc('id')->first();
                    if ($lead) {
                        $wasAlreadyLinked = true;
                    }
                }

                if (! $lead) {
                    $lead = Lead::findOpenLeadByPhone(
                        $canonicalCustomer?->phone ?: $entry->phone,
                        $canonicalCustomer?->id ?: ($entry->customer_id ? (int) $entry->customer_id : null)
                    );
                }

                // 3. Determine effective status, needful_done, and follow-up date
                $rawStatus = strtolower(trim((string) ($entry->status ?? '')));
                $isBookedByCalendar = $entry->isBooked()
                    || ($lead && $lead->needful_done === 'booked' && $lead->hasVerifiedConversionActivity());

                if ($isBookedByCalendar) {
                    $effectiveAdStatus = 'booked';
                    $targetNeedfulDone = 'booked';
                } elseif ($rawStatus === 'lost') {
                    $effectiveAdStatus = 'lost';
                    $targetNeedfulDone = 'no';
                } elseif ($rawStatus === 'contacted') {
                    $effectiveAdStatus = 'contacted';
                    $targetNeedfulDone = 'yes';
                } elseif (in_array($rawStatus, AdLeadEntry::FOLLOW_UP_TRIGGER_STATUSES, true)) {
                    $effectiveAdStatus = $rawStatus;
                    $targetNeedfulDone = null;
                } else {
                    // Explicit Send to Follow-Ups or follow_up_date set with no status yet
                    $effectiveAdStatus = 'follow_up';
                    $targetNeedfulDone = ($lead && $lead->needful_done === 'yes' && ! $force) ? 'yes' : null;
                }

                $isClosed = in_array($targetNeedfulDone, Lead::CLOSED_STATUSES, true);

                // Resolve follow-up date: explicit argument -> entry.follow_up_date -> existing lead date -> tomorrow (Asia/Qatar)
                $resolvedFollowUpDate = null;
                if (! $isClosed) {
                    if ($followUpDate !== null && trim($followUpDate) !== '') {
                        $resolvedFollowUpDate = Carbon::parse($followUpDate)->toDateString();
                    } elseif ($entry->follow_up_date) {
                        $resolvedFollowUpDate = $entry->follow_up_date->toDateString();
                    } elseif ($lead && $lead->next_followup_date) {
                        $resolvedFollowUpDate = $lead->next_followup_date->toDateString();
                    } else {
                        $resolvedFollowUpDate = Lead::salonToday()->addDay()->toDateString();
                    }
                }

                // Resolve assigned agent: explicit arg -> entry.assigned_agent_id -> existing lead agent -> logged-in chat agent
                $resolvedAgentId = $assignedAgentId
                    ?: ($entry->assigned_agent_id ? (int) $entry->assigned_agent_id : null)
                    ?: ($lead?->assigned_agent_id ? (int) $lead->assigned_agent_id : null);

                if (! $resolvedAgentId && auth()->check() && strtolower((string) auth()->user()?->role) === 'agent') {
                    $resolvedAgentId = (int) auth()->id();
                }

                // Resolve service interest & source label
                $incomingServiceInterests = self::resolveServiceInterestsFromCategory((string) $entry->category);
                $sourceLabel = self::buildAdSourceLabel((string) $entry->category);

                // Resolve clean remarks without duplicate concatenation
                $adRemark = trim((string) ($entry->remarks ?? ''));
                $leadRemark = $lead ? trim((string) ($lead->customer_remarks ?? '')) : '';
                $finalRemarks = self::resolveSyncedRemarksForLead(
                    adRemark: $adRemark,
                    existingLeadRemark: $leadRemark,
                    wasAlreadyLinked: $wasAlreadyLinked,
                    previousAdRemark: $previousAdRemarks !== null ? trim($previousAdRemarks) : null
                );

                // 4. Create or update the canonical Lead Management row
                if ($lead) {
                    $existingServices = is_array($lead->service_interest) ? $lead->service_interest : [];
                    $mergedServices = Lead::normalizeServiceInterests(
                        array_merge($existingServices, $incomingServiceInterests)
                    );

                    $mergedCategories = $lead->mergeCategories(['follow_up']);
                    $primaryCategory = Lead::resolvePrimaryCategory($mergedCategories) ?: 'follow_up';

                    $leadUpdate = [
                        'phone' => $leadPhone ?: $lead->phone,
                        'customer_id' => $canonicalCustomer?->id ?: $lead->customer_id,
                        'assigned_agent_id' => $resolvedAgentId,
                        'category' => $primaryCategory,
                        'categories' => $mergedCategories,
                        'service_interest' => ! empty($mergedServices) ? $mergedServices : $incomingServiceInterests,
                        'customer_remarks' => $finalRemarks !== '' ? $finalRemarks : null,
                        'needful_done' => $targetNeedfulDone,
                        'next_followup_date' => $resolvedFollowUpDate,
                    ];

                    if (Schema::hasColumn('leads', 'ad_lead_id')) {
                        $leadUpdate['ad_lead_id'] = $entry->id;
                    }
                    if (Schema::hasColumn('leads', 'source')) {
                        $leadUpdate['source'] = $sourceLabel;
                    }
                    if (Schema::hasColumn('leads', 'branch') && ! empty($entry->branch)) {
                        $leadUpdate['branch'] = $entry->branch;
                    }

                    $lead->update($leadUpdate);
                    $lead = $lead->fresh();
                } else {
                    $initialCategories = Lead::normalizeCategories(['follow_up']);

                    $leadCreate = [
                        'phone' => $leadPhone,
                        'customer_id' => $canonicalCustomer?->id,
                        'assigned_agent_id' => $resolvedAgentId,
                        'category' => Lead::resolvePrimaryCategory($initialCategories) ?: 'follow_up',
                        'categories' => $initialCategories,
                        'service_interest' => $incomingServiceInterests,
                        'customer_remarks' => $finalRemarks !== '' ? $finalRemarks : null,
                        'needful_done' => $targetNeedfulDone,
                        'next_followup_date' => $resolvedFollowUpDate,
                    ];

                    if (Schema::hasColumn('leads', 'ad_lead_id')) {
                        $leadCreate['ad_lead_id'] = $entry->id;
                    }
                    if (Schema::hasColumn('leads', 'source')) {
                        $leadCreate['source'] = $sourceLabel;
                    }
                    if (Schema::hasColumn('leads', 'branch') && ! empty($entry->branch)) {
                        $leadCreate['branch'] = $entry->branch;
                    }

                    $lead = Lead::create($leadCreate);
                }

                // 5. Persist link & normalized follow-up fields back onto AdLeadEntry (NEVER modifying branch/ticket_amount)
                $entryUpdates = [];
                if (Schema::hasColumn('ad_lead_entries', 'lead_id') && (int) $entry->lead_id !== (int) $lead->id) {
                    $entryUpdates['lead_id'] = $lead->id;
                }
                if (Schema::hasColumn('ad_lead_entries', 'customer_id') && $canonicalCustomer && (int) $entry->customer_id !== (int) $canonicalCustomer->id) {
                    $entryUpdates['customer_id'] = $canonicalCustomer->id;
                }
                if (Schema::hasColumn('ad_lead_entries', 'status') && $entry->status !== $effectiveAdStatus) {
                    $entryUpdates['status'] = $effectiveAdStatus;
                }
                if (Schema::hasColumn('ad_lead_entries', 'follow_up_date')) {
                    $currentDateStr = $entry->follow_up_date?->toDateString();
                    if ($currentDateStr !== $resolvedFollowUpDate) {
                        $entryUpdates['follow_up_date'] = $resolvedFollowUpDate;
                    }
                }
                if (Schema::hasColumn('ad_lead_entries', 'assigned_agent_id') && (int) ($entry->assigned_agent_id ?? 0) !== (int) ($resolvedAgentId ?? 0)) {
                    $entryUpdates['assigned_agent_id'] = $resolvedAgentId;
                }
                // Reflect merged/cleaned remark back to AdLeadEntry if linking merged an existing open lead's remark
                $cleanBackRemark = self::stripAutoClosedTag($finalRemarks);
                if ($cleanBackRemark !== $adRemark && ! $wasAlreadyLinked && $cleanBackRemark !== '') {
                    $entryUpdates['remarks'] = $cleanBackRemark;
                }

                if (! empty($entryUpdates)) {
                    $entry->update($entryUpdates);
                }

                return $lead;
            });
        });
    }

    /**
     * Reverse sync: whenever a linked Lead is updated in Lead Management (or auto-closed/reopened by Calendar),
     * sync its latest remarks, follow-up date, status, assigned agent, and customer name back to all linked AdLeadEntry rows.
     *
     * IMPORTANT:
     * - Does NOTHING for unlinked Leads (never touches unrelated AdLeadEntry rows).
     * - NEVER mutates `branch`, `ticket_amount`, `old_airport_amount`, `wakrah_amount`, or `date`
     *   (so 14-day rolling calendar revenue attribution stays 100% intact with zero double-counting).
     */
    public static function syncFromLead(Lead $lead): void
    {
        if (! Schema::hasTable('ad_lead_entries') || ! Schema::hasTable('leads')) {
            return;
        }

        self::withoutLoop(function () use ($lead): void {
            $hasLeadAdIdCol = Schema::hasColumn('leads', 'ad_lead_id');
            $hasEntryLeadIdCol = Schema::hasColumn('ad_lead_entries', 'lead_id');

            if (! $hasLeadAdIdCol && ! $hasEntryLeadIdCol) {
                return;
            }

            $linkedEntries = AdLeadEntry::query()
                ->where(function ($q) use ($lead, $hasLeadAdIdCol, $hasEntryLeadIdCol) {
                    if ($hasEntryLeadIdCol) {
                        $q->where('lead_id', $lead->id);
                    }
                    if ($hasLeadAdIdCol && $lead->ad_lead_id) {
                        $q->orWhere('id', $lead->ad_lead_id);
                    }
                })
                ->get();

            if ($linkedEntries->isEmpty()) {
                return;
            }

            $hasStatusCol = Schema::hasColumn('ad_lead_entries', 'status');
            $hasFollowUpDateCol = Schema::hasColumn('ad_lead_entries', 'follow_up_date');
            $hasAssignedAgentCol = Schema::hasColumn('ad_lead_entries', 'assigned_agent_id');
            $hasCustomerIdCol = Schema::hasColumn('ad_lead_entries', 'customer_id');
            $hasCustomerNameCol = Schema::hasColumn('ad_lead_entries', 'customer_name');

            $leadRemarks = trim((string) ($lead->customer_remarks ?? ''));
            $leadFollowUpDate = in_array($lead->needful_done, Lead::CLOSED_STATUSES, true)
                ? null
                : $lead->next_followup_date?->toDateString();
            $leadAgentId = $lead->assigned_agent_id ? (int) $lead->assigned_agent_id : null;
            $leadCustomerName = trim((string) ($lead->customer?->name ?? ''));
            $isPlaceholderName = $leadCustomerName === ''
                || in_array(strtolower($leadCustomerName), ['walk-in', 'guest', 'unnamed', 'unnamed client', '-', '—', 'ad lead client'], true);

            foreach ($linkedEntries as $entry) {
                $updates = [];

                // Ensure bidirectional FK is always consistent
                if ($hasEntryLeadIdCol && (int) $entry->lead_id !== (int) $lead->id) {
                    $updates['lead_id'] = $lead->id;
                }

                // Sync remarks cleanly (exact replacement — never appends duplicate copies)
                $currentEntryRemark = trim((string) ($entry->remarks ?? ''));
                $normalizedLeadRemark = $leadRemarks !== '' ? $leadRemarks : null;
                if ($currentEntryRemark !== (string) ($normalizedLeadRemark ?? '')) {
                    $updates['remarks'] = $normalizedLeadRemark;
                }

                // Sync status cleanly while respecting Calendar-derived booking state
                if ($hasStatusCol) {
                    $mappedStatus = self::mapLeadStatusToAdLeadStatus($lead, $entry);
                    if ($entry->status !== $mappedStatus) {
                        $updates['status'] = $mappedStatus;
                    }
                }

                // Sync follow-up date
                if ($hasFollowUpDateCol) {
                    $currentDate = $entry->follow_up_date?->toDateString();
                    if ($currentDate !== $leadFollowUpDate) {
                        $updates['follow_up_date'] = $leadFollowUpDate;
                    }
                }

                // Sync assigned agent
                if ($hasAssignedAgentCol && (int) ($entry->assigned_agent_id ?? 0) !== (int) ($leadAgentId ?? 0)) {
                    $updates['assigned_agent_id'] = $leadAgentId;
                }

                // Keep customer profile link & name aligned
                if ($hasCustomerIdCol && $lead->customer_id && ! $entry->customer_id) {
                    $updates['customer_id'] = $lead->customer_id;
                }
                if ($hasCustomerNameCol && ! $isPlaceholderName && trim((string) $entry->customer_name) !== $leadCustomerName) {
                    $updates['customer_name'] = $leadCustomerName;
                }

                if (! empty($updates)) {
                    $entry->update($updates);
                }
            }
        });
    }

    /**
     * Map Lead Management `needful_done` status back to `ad_lead_entries.status`.
     */
    public static function mapLeadStatusToAdLeadStatus(Lead $lead, AdLeadEntry $entry): string
    {
        if ($lead->needful_done === 'booked' || $entry->isBooked()) {
            return 'booked';
        }

        if ($lead->needful_done === 'no') {
            return 'lost';
        }

        if ($lead->needful_done === 'yes') {
            return 'contacted';
        }

        // Open / Pending in Lead Management: preserve specific open follow-up flavor if already set on Ads List
        $current = strtolower(trim((string) ($entry->status ?? '')));
        if (in_array($current, AdLeadEntry::FOLLOW_UP_TRIGGER_STATUSES, true)) {
            return $current;
        }

        return 'follow_up';
    }

    /**
     * Resolve remarks when syncing from Ads List -> Lead Management without duplicating appended text.
     */
    private static function resolveSyncedRemarksForLead(
        string $adRemark,
        string $existingLeadRemark,
        bool $wasAlreadyLinked,
        ?string $previousAdRemark = null
    ): string {
        // Already linked: editing the Ad Lead's remarks directly updates the Lead's remarks
        // (preserving any trailing `[Auto-closed: ...]` tag on the Lead).
        if ($wasAlreadyLinked) {
            if ($previousAdRemark !== null && $adRemark === $previousAdRemark) {
                return $existingLeadRemark;
            }

            $autoCloseSuffix = self::extractAutoClosedTag($existingLeadRemark);
            if ($adRemark === '') {
                return $autoCloseSuffix;
            }

            if ($autoCloseSuffix !== '' && ! str_contains($adRemark, $autoCloseSuffix)) {
                return trim($adRemark . ' ' . $autoCloseSuffix);
            }

            return $adRemark;
        }

        // First-time link / merge into an existing open Lead (or brand new Lead):
        if ($existingLeadRemark === '') {
            return $adRemark;
        }
        if ($adRemark === '') {
            return $existingLeadRemark;
        }

        $normExisting = mb_strtolower($existingLeadRemark);
        $normIncoming = mb_strtolower($adRemark);

        if ($normExisting === $normIncoming || str_contains($normExisting, $normIncoming)) {
            return $existingLeadRemark;
        }
        if (str_contains($normIncoming, $normExisting)) {
            return $adRemark;
        }

        return $existingLeadRemark . ' | ' . $adRemark;
    }

    private static function extractAutoClosedTag(string $text): string
    {
        if (preg_match('/(\[Auto-closed:[^\]]*\])/i', $text, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    private static function stripAutoClosedTag(string $text): string
    {
        return trim((string) preg_replace('/\s*\[Auto-closed:[^\]]*\]/i', '', $text));
    }

    /**
     * Map an Ad Lead category to a matching catalog salon service or combo.
     * Falls back cleanly to a centralized option from Lead::GENERAL_SERVICE_INTERESTS
     * (matching "Hairstyling / General Hairstyle" for general hairstyling ads, or
     * "Offer Inquiry / General Inquiry" for generic campaign names like "Ramadan Deal").
     *
     * @return list<string>
     */
    private static function resolveServiceInterestsFromCategory(string $category): array
    {
        $clean = trim($category);
        if ($clean === '') {
            return [Lead::GENERAL_INQUIRY_OPTION];
        }

        $lower = mb_strtolower($clean);

        if (Schema::hasTable('services')) {
            $exactService = Service::whereRaw('LOWER(name) = ?', [$lower])->value('name');
            if (is_string($exactService) && trim($exactService) !== '') {
                return [trim($exactService)];
            }
        }

        if (Schema::hasTable('combos')) {
            $exactCombo = Combo::whereRaw('LOWER(name) = ?', [$lower])->value('name');
            if (is_string($exactCombo) && trim($exactCombo) !== '') {
                return ['[Combo] ' . trim($exactCombo)];
            }
        }

        $canonicalGeneral = Lead::canonicalGeneralInterestLabel($clean);
        if ($canonicalGeneral !== null) {
            return [$canonicalGeneral];
        }

        if (str_contains($lower, 'hairstyl') || str_contains($lower, 'hair style')) {
            return [Lead::GENERAL_HAIRSTYLE_OPTION];
        }

        return [Lead::GENERAL_INQUIRY_OPTION];
    }

    private static function buildAdSourceLabel(string $category): string
    {
        $clean = trim($category);

        return $clean !== '' ? 'Paid Ads · ' . $clean : 'Paid Ads';
    }
}
