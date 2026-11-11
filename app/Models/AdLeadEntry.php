<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class AdLeadEntry extends Model
{
    /**
     * Ad type / campaign categories offered in the data-entry dropdown.
     * Keep this in sync with whatever campaigns are currently running.
     */
    const CATEGORIES = [
        'Ramadan Deal',
        'Hair Color',
        'Hair Treatment',
        'Makeup',
        'Facial',
        'Nails',
        'Spa & Massage',
        'Bridal Package',
        'Other',
    ];

    const BRANCHES = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
    ];

    protected $fillable = [
        'date',
        'customer_id',
        'lead_id',
        'customer_name',
        'phone',
        'category',
        'ticket_amount',
        'branch',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'ticket_amount' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Attribution window (in days) after an AdLeadEntry's date during which
     * a paid conversion (Sale / completed Appointment) can be attributed to it.
     */
    public const ATTRIBUTION_WINDOW_DAYS = 30;

    /**
     * A lead is ONLY counted as booked/converted once it has a valid branch
     * AND a positive paid ticket_amount (> 0) from a completed/paid sale.
     */
    public function isBooked(): bool
    {
        return in_array($this->branch, array_keys(self::BRANCHES), true)
            && (float) $this->ticket_amount > 0;
    }

    /**
     * Build normalized phone candidates and matching customer IDs for this entry.
     *
     * @return array{0: array<int, int>, 1: array<int, string>}
     */
    public function resolveIdentityCohort(): array
    {
        $rawPhones = array_filter([
            $this->phone,
            $this->customer?->phone,
        ]);

        $phoneCandidates = [];
        foreach ($rawPhones as $raw) {
            $digits = preg_replace('/\D+/', '', (string) $raw);
            $normCustomer = Customer::normalizePhone((string) $raw);
            [$cc, $local] = Lead::splitPhone($digits);
            $normLead = Lead::normalizePhone($cc, $local);

            foreach ([$raw, $digits, $normCustomer, $normLead] as $candidate) {
                $trimmed = trim((string) $candidate);
                if ($trimmed !== '') {
                    $phoneCandidates[] = $trimmed;
                }
            }
        }
        $phoneCandidates = array_values(array_unique($phoneCandidates));

        $customerIds = array_values(array_unique(array_filter([
            $this->customer_id,
        ])));

        if (!empty($phoneCandidates) && Schema::hasTable('customers')) {
            $matchedIds = Customer::whereIn('phone', $phoneCandidates)->pluck('id')->all();
            $customerIds = array_values(array_unique(array_merge($customerIds, $matchedIds)));
        }

        return [$customerIds, $phoneCandidates];
    }

    /**
     * Return a real non-cancelled / non-no-show Appointment for this client if one exists.
     */
    public function resolveVerifiedAppointment(): ?Appointment
    {
        if (!Schema::hasTable('appointments')) {
            return null;
        }

        [$customerIds, $phoneCandidates] = $this->resolveIdentityCohort();
        if (empty($customerIds) && empty($phoneCandidates)) {
            return null;
        }

        $entryDateStr = $this->date ? Carbon::parse($this->date)->toDateString() : null;

        $appointments = Appointment::query()
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($customerIds, $phoneCandidates) {
                if (!empty($customerIds)) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
                if (!empty($phoneCandidates)) {
                    $q->orWhereIn('phone', $phoneCandidates);
                }
            })
            ->orderByDesc('appointment_datetime')
            ->orderByDesc('id')
            ->get();

        if ($appointments->isEmpty()) {
            return null;
        }

        $todayStr = today()->toDateString();
        $minDateStr = $entryDateStr ?: $todayStr;

        return $appointments->first(function (Appointment $a) use ($minDateStr, $todayStr) {
            $apptDateStr = $a->appointment_date->toDateString();

            return $apptDateStr >= $minDateStr || $apptDateStr >= $todayStr;
        });
    }

    /**
     * True ONLY if the client has a real non-cancelled/non-no-show Appointment
     * OR a verified paid Sale record on or after $this->date.
     */
    public function hasVerifiedCustomerConversion(): bool
    {
        if ($this->resolveVerifiedAppointment() !== null) {
            return true;
        }

        [$customerIds] = $this->resolveIdentityCohort();
        if (empty($customerIds) || !Schema::hasTable('sales')) {
            return false;
        }

        $entryDateStr = $this->date ? Carbon::parse($this->date)->toDateString() : null;

        $sales = Sale::with('appointment')
            ->whereIn('customer_id', $customerIds)
            ->get();

        foreach ($sales as $sale) {
            if ($sale->appointment && in_array(strtolower((string) $sale->appointment->status), ['cancelled', 'no_show'], true)) {
                continue;
            }
            if ($entryDateStr && self::resolveSaleDate($sale)->toDateString() < $entryDateStr) {
                continue;
            }
            if (self::netSaleAmount($sale) > 0) {
                return true;
            }
        }

        return false;
    }

    public static function resolveCategoryFromLead(Lead $lead): string
    {
        $interests = is_array($lead->service_interest) ? $lead->service_interest : [];
        $available = self::availableCategories();

        foreach ($interests as $interest) {
            $normInterest = strtolower(trim((string) $interest));
            if ($normInterest === '') {
                continue;
            }
            foreach ($available as $cat) {
                $normCat = strtolower(trim((string) $cat));
                if ($normInterest === $normCat || str_contains($normInterest, $normCat) || str_contains($normCat, $normInterest)) {
                    return $cat;
                }
            }
        }

        return 'Other';
    }

    /**
     * Resolve the effective cohort date for a Sale (uses the linked appointment's
     * date when present so a checkout for a future/past month appointment in tests
     * or operations always attributes to that appointment's month).
     */
    public static function resolveSaleDate(Sale $sale): Carbon
    {
        $appointment = $sale->relationLoaded('appointment')
            ? $sale->appointment
            : ($sale->appointment_id ? $sale->appointment()->first() : null);

        if ($appointment) {
            if ($appointment->appointment_datetime) {
                return Carbon::parse($appointment->appointment_datetime);
            }
            if ($appointment->booking_date) {
                return Carbon::parse($appointment->booking_date);
            }
        }

        return $sale->created_at ? Carbon::parse($sale->created_at) : now();
    }

    /**
     * Compute net paid revenue collected on a Sale (services + products + packages - discount, excluding tips).
     */
    public static function netSaleAmount(Sale $sale): float
    {
        $componentsSubtotal = (float) $sale->services_total
            + (float) $sale->products_total
            + (float) $sale->packages_total;

        if ($componentsSubtotal > 0) {
            return round(max(0.0, $componentsSubtotal - (float) $sale->discount_amount), 2);
        }

        return round(max(0.0, (float) $sale->total_amount - (float) $sale->tip_amount), 2);
    }

    /**
     * Compute booked service total for an unpaid Appointment.
     */
    public static function appointmentBookedAmount(Appointment $appointment): float
    {
        $linesSum = (float) $appointment->appointmentServices()->get()->sum(fn ($s) => $s->final_price);
        $baseServiceTotal = $linesSum > 0 ? $linesSum : (float) $appointment->price;

        $unpaidPackagesTotal = Schema::hasTable('client_packages')
            ? (float) ClientPackage::where('appointment_id', $appointment->id)->whereNull('sale_id')->sum('price_paid')
            : 0.0;

        $upsellsTotal = Schema::hasTable('appointment_upsells')
            ? (float) $appointment->upsells()->sum('amount')
            : 0.0;

        return round(max(0.0, $baseServiceTotal + $unpaidPackagesTotal + $upsellsTotal), 2);
    }

    /**
     * Synchronize AdLeadEntry attribution for a client cohort using:
     * - 30-day cross-month attribution window: [entry.date, entry.date + 30 days]
     * - Last-touch single-winner attribution: each Sale (and standalone paid Appointment)
     *   attributes to at most ONE AdLeadEntry — the most recent entry on or before
     *   the conversion date within 30 days, preventing double-counting and cross-branch hijacking.
     */
    public static function syncFromCustomerActivity(
        ?Customer $customer,
        Carbon|string $activityDate,
        ?string $phone = null,
        ?int $appointmentId = null,
        ?AdLeadEntry $targetEntry = null,
        bool $resetIfNoActivity = true
    ): ?self {
        if (!Schema::hasTable('ad_lead_entries')) {
            return null;
        }

        $activityCarbon = $activityDate instanceof Carbon
            ? $activityDate->copy()
            : Carbon::parse($activityDate);

        $appointment = $appointmentId ? Appointment::find($appointmentId) : null;

        $rawPhones = array_filter([
            $customer?->phone,
            $phone,
            $targetEntry?->phone,
            $appointment?->phone,
        ]);

        $phoneCandidates = [];
        foreach ($rawPhones as $raw) {
            $digits = preg_replace('/\D+/', '', (string) $raw);
            $normCustomer = Customer::normalizePhone((string) $raw);
            [$cc, $local] = Lead::splitPhone($digits);
            $normLead = Lead::normalizePhone($cc, $local);

            foreach ([$raw, $digits, $normCustomer, $normLead] as $candidate) {
                $trimmed = trim((string) $candidate);
                if ($trimmed !== '') {
                    $phoneCandidates[] = $trimmed;
                }
            }
        }
        $phoneCandidates = array_values(array_unique($phoneCandidates));

        $customerIds = array_values(array_unique(array_filter([
            $customer?->id,
            $targetEntry?->customer_id,
            $appointment?->customer_id,
        ])));

        if (!empty($phoneCandidates) && Schema::hasTable('customers')) {
            $matchedCustomerIds = Customer::whereIn('phone', $phoneCandidates)->pluck('id')->all();
            $customerIds = array_values(array_unique(array_merge($customerIds, $matchedCustomerIds)));
        }

        if (!empty($customerIds) && Schema::hasTable('customers')) {
            $extraPhones = Customer::whereIn('id', $customerIds)->pluck('phone')->filter()->all();
            foreach ($extraPhones as $ep) {
                $digits = preg_replace('/\D+/', '', (string) $ep);
                $norm = Customer::normalizePhone((string) $ep);
                foreach ([$ep, $digits, $norm] as $c) {
                    if ($c !== '') {
                        $phoneCandidates[] = $c;
                    }
                }
            }
            $phoneCandidates = array_values(array_unique($phoneCandidates));
        }

        $leadIds = array_values(array_unique(array_filter([
            $targetEntry?->lead_id,
        ])));

        if (Schema::hasTable('leads') && (!empty($customerIds) || !empty($phoneCandidates) || $appointmentId)) {
            $matchedLeadIds = Lead::query()
                ->where(function ($q) use ($customerIds, $phoneCandidates, $appointmentId) {
                    if (!empty($customerIds)) {
                        $q->orWhereIn('customer_id', $customerIds);
                    }
                    if (!empty($phoneCandidates)) {
                        $q->orWhereIn('phone', $phoneCandidates);
                    }
                    if ($appointmentId) {
                        $q->orWhere('appointment_id', $appointmentId);
                    }
                })
                ->pluck('id')
                ->all();

            $leadIds = array_values(array_unique(array_merge($leadIds, $matchedLeadIds)));
        }

        if (empty($customerIds) && empty($phoneCandidates) && empty($leadIds) && !$targetEntry) {
            return null;
        }

        $hasLeadIdColumn = Schema::hasColumn('ad_lead_entries', 'lead_id');
        $hasCustomerIdColumn = Schema::hasColumn('ad_lead_entries', 'customer_id');

        $clientEntries = self::query()
            ->where(function ($q) use ($customerIds, $leadIds, $phoneCandidates, $hasCustomerIdColumn, $hasLeadIdColumn, $targetEntry) {
                if ($hasCustomerIdColumn && !empty($customerIds)) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
                if ($hasLeadIdColumn && !empty($leadIds)) {
                    $q->orWhereIn('lead_id', $leadIds);
                }
                if (!empty($phoneCandidates)) {
                    $q->orWhereIn('phone', $phoneCandidates);
                }
                if ($targetEntry?->id) {
                    $q->orWhere('id', $targetEntry->id);
                }
            })
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $activityDateStr = $activityCarbon->toDateString();
        $windowEarliestEntryDate = $activityCarbon->copy()->subDays(self::ATTRIBUTION_WINDOW_DAYS)->toDateString();

        $coveringEntry = $clientEntries
            ->filter(function (self $e) use ($windowEarliestEntryDate, $activityDateStr) {
                $d = Carbon::parse($e->date)->toDateString();

                return $d >= $windowEarliestEntryDate && $d <= $activityDateStr;
            })
            ->sortBy([
                [fn (self $e) => Carbon::parse($e->date)->toDateString(), 'desc'],
                ['id', 'desc'],
            ])
            ->first();

        if (!$coveringEntry && !$targetEntry && !empty($leadIds) && Schema::hasTable('leads')) {
            $windowLeads = Lead::whereIn('id', $leadIds)
                ->whereDate('created_at', '>=', $windowEarliestEntryDate)
                ->whereDate('created_at', '<=', $activityDateStr)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            $paidAdLead = $windowLeads->first(fn (Lead $l) => $l->isPaidAdLead());
            if ($paidAdLead) {
                $leadDate = $paidAdLead->created_at ? Carbon::parse($paidAdLead->created_at)->toDateString() : $activityDateStr;
                $resolvedCustomer = $customer ?: ($paidAdLead->customer_id ? Customer::find($paidAdLead->customer_id) : null);
                $resolvedCategory = self::resolveCategoryFromLead($paidAdLead);
                $entryPayload = [
                    'date' => $leadDate,
                    'phone' => $paidAdLead->phone ?: ($phoneCandidates[0] ?? ''),
                    'category' => $resolvedCategory,
                    'ticket_amount' => 0,
                    'branch' => null,
                    'remarks' => $paidAdLead->customer_remarks ?: ($paidAdLead->source ? "Source: {$paidAdLead->source}" : null),
                    'created_by' => $paidAdLead->assigned_agent_id ?? auth()->id(),
                ];
                if ($hasCustomerIdColumn) {
                    $entryPayload['customer_id'] = $resolvedCustomer?->id;
                }
                if (Schema::hasColumn('ad_lead_entries', 'customer_name')) {
                    $entryPayload['customer_name'] = $resolvedCustomer?->name ?: 'Ad Lead Client';
                }
                if ($hasLeadIdColumn) {
                    $entryPayload['lead_id'] = $paidAdLead->id;
                }
                $createdEntry = self::create($entryPayload);
                $clientEntries->push($createdEntry);
                $coveringEntry = $createdEntry;
            }
        }

        if ($clientEntries->isEmpty()) {
            return null;
        }

        // Order descending by (date, id) so the first matching entry is the last-touch winner
        $entriesDesc = $clientEntries
            ->sortBy([
                [fn (self $e) => Carbon::parse($e->date)->toDateString(), 'desc'],
                ['id', 'desc'],
            ])
            ->values();

        // Collect all appointments for this client cohort
        $allClientAppointments = collect();
        if (Schema::hasTable('appointments') && (!empty($customerIds) || !empty($phoneCandidates) || $appointmentId)) {
            $allClientAppointments = Appointment::query()
                ->where(function ($q) use ($customerIds, $phoneCandidates, $appointmentId) {
                    if (!empty($customerIds)) {
                        $q->orWhereIn('customer_id', $customerIds);
                    }
                    if (!empty($phoneCandidates)) {
                        $q->orWhereIn('phone', $phoneCandidates);
                    }
                    if ($appointmentId) {
                        $q->orWhere('id', $appointmentId);
                    }
                })
                ->get();
        }

        $allClientAppointmentIds = $allClientAppointments->pluck('id')->all();

        // Build unified list of valid paid conversions (Sales + standalone paid Appointments without a Sale)
        $conversions = [];
        $saleAppointmentIds = [];

        if (Schema::hasTable('sales') && (!empty($customerIds) || !empty($allClientAppointmentIds))) {
            $clientSales = Sale::with('appointment')
                ->where(function ($q) use ($customerIds, $allClientAppointmentIds) {
                    if (!empty($customerIds)) {
                        $q->orWhereIn('customer_id', $customerIds);
                    }
                    if (!empty($allClientAppointmentIds)) {
                        $q->orWhereIn('appointment_id', $allClientAppointmentIds);
                    }
                })
                ->get();

            foreach ($clientSales as $sale) {
                if ($sale->appointment_id) {
                    $saleAppointmentIds[] = (int) $sale->appointment_id;
                }
                if ($sale->appointment && in_array(strtolower((string) $sale->appointment->status), ['cancelled', 'no_show'], true)) {
                    continue;
                }
                $amount = self::netSaleAmount($sale);
                if ($amount <= 0) {
                    continue;
                }
                $convDate = self::resolveSaleDate($sale);
                $convBranch = $sale->appointment?->branch ?: $sale->branch;

                $conversions[] = [
                    'date_str' => $convDate->toDateString(),
                    'timestamp' => $convDate->timestamp,
                    'amount' => $amount,
                    'branch' => in_array($convBranch, array_keys(self::BRANCHES), true) ? $convBranch : null,
                    'appointment_id' => $sale->appointment_id ? (int) $sale->appointment_id : null,
                ];
            }
        }

        $saleAppointmentIds = array_values(array_unique($saleAppointmentIds));

        foreach ($allClientAppointments as $appt) {
            if (in_array((int) $appt->id, $saleAppointmentIds, true)) {
                continue;
            }
            $statusLower = strtolower((string) ($appt->status ?? 'pending'));
            if (in_array($statusLower, ['cancelled', 'no_show'], true)) {
                continue;
            }
            if ($statusLower !== 'completed' && $appt->paid_at === null) {
                continue;
            }
            $amount = self::appointmentBookedAmount($appt);
            if ($amount <= 0) {
                continue;
            }
            $convDate = $appt->appointment_date;
            $convBranch = $appt->branch;

            $conversions[] = [
                'date_str' => $convDate->toDateString(),
                'timestamp' => $convDate->timestamp,
                'amount' => $amount,
                'branch' => in_array($convBranch, array_keys(self::BRANCHES), true) ? $convBranch : null,
                'appointment_id' => (int) $appt->id,
            ];
        }

        // Last-touch single-winner attribution within [entry.date, entry.date + 30 days]
        $attributedByEntryId = [];
        $claimedConversionDates = [];

        foreach ($conversions as $conv) {
            $convDateStr = $conv['date_str'];
            $winner = $entriesDesc->first(function (self $candidate) use ($convDateStr) {
                $entryStartStr = Carbon::parse($candidate->date)->toDateString();
                $entryEndStr = Carbon::parse($candidate->date)->addDays(self::ATTRIBUTION_WINDOW_DAYS)->toDateString();

                return $convDateStr >= $entryStartStr && $convDateStr <= $entryEndStr;
            });

            if ($winner) {
                $attributedByEntryId[$winner->id][] = $conv;
                $claimedConversionDates[] = [
                    'winner_id' => $winner->id,
                    'date_str' => $convDateStr,
                ];
            }
        }

        $allLeadsCollection = (!empty($leadIds) && Schema::hasTable('leads'))
            ? Lead::whereIn('id', $leadIds)->orderByDesc('id')->get()
            : collect();

        foreach ($entriesDesc as $entry) {
            $entryStart = Carbon::parse($entry->date)->startOfDay();
            $entryStartStr = $entryStart->toDateString();
            $entryEndStr = $entryStart->copy()->addDays(self::ATTRIBUTION_WINDOW_DAYS)->toDateString();

            $myConversions = $attributedByEntryId[$entry->id] ?? [];
            $newBranch = $entry->branch;
            $newTicketAmount = round((float) $entry->ticket_amount, 2);

            if (!empty($myConversions)) {
                $newTicketAmount = round((float) array_sum(array_column($myConversions, 'amount')), 2);

                // Attribute branch from majority revenue among THIS entry's conversions (tie-break: earliest conversion)
                $branchTotals = [];
                $branchFirstTs = [];
                foreach ($myConversions as $mc) {
                    $b = $mc['branch'];
                    if (!$b) {
                        continue;
                    }
                    $branchTotals[$b] = ($branchTotals[$b] ?? 0.0) + (float) $mc['amount'];
                    if (!isset($branchFirstTs[$b]) || $mc['timestamp'] < $branchFirstTs[$b]) {
                        $branchFirstTs[$b] = $mc['timestamp'];
                    }
                }

                if (!empty($branchTotals)) {
                    uksort($branchTotals, function (string $a, string $b) use ($branchTotals, $branchFirstTs) {
                        $diff = $branchTotals[$b] <=> $branchTotals[$a];
                        if ($diff !== 0) {
                            return $diff;
                        }

                        return ($branchFirstTs[$a] ?? 0) <=> ($branchFirstTs[$b] ?? 0);
                    });
                    $newBranch = array_key_first($branchTotals);
                }
            } else {
                $conversionShiftedToNewerEntry = false;
                foreach ($claimedConversionDates as $claimed) {
                    if ($claimed['winner_id'] !== $entry->id
                        && $claimed['date_str'] >= $entryStartStr
                        && $claimed['date_str'] <= $entryEndStr
                    ) {
                        $conversionShiftedToNewerEntry = true;
                        break;
                    }
                }

                $activityInWindow = $activityDateStr >= $entryStartStr && $activityDateStr <= $entryEndStr;

                $shouldReset = ($targetEntry && $entry->id === $targetEntry->id && $resetIfNoActivity)
                    || (!$targetEntry && $resetIfNoActivity && $activityInWindow)
                    || $conversionShiftedToNewerEntry;

                if ($shouldReset) {
                    $newBranch = null;
                    $newTicketAmount = 0.0;
                }
            }

            $updates = [];
            if ($entry->branch !== $newBranch) {
                $updates['branch'] = $newBranch;
            }
            if (round((float) $entry->ticket_amount, 2) !== round((float) $newTicketAmount, 2)) {
                $updates['ticket_amount'] = $newTicketAmount;
            }
            if ($hasCustomerIdColumn && !$entry->customer_id && !empty($customerIds)) {
                $updates['customer_id'] = $customerIds[0];
            }
            if ($hasLeadIdColumn && !$entry->lead_id && $allLeadsCollection->isNotEmpty()) {
                $preferredLead = null;
                if (!empty($myConversions)) {
                    $attributedApptIds = array_values(array_filter(array_column($myConversions, 'appointment_id')));
                    if (!empty($attributedApptIds)) {
                        $preferredLead = $allLeadsCollection->first(
                            fn (Lead $l) => $l->appointment_id && in_array((int) $l->appointment_id, $attributedApptIds, true)
                        );
                    }
                }
                if (!$preferredLead) {
                    $preferredLead = $allLeadsCollection->first(function (Lead $l) use ($entryStartStr, $entryEndStr) {
                        if (!$l->created_at) {
                            return false;
                        }
                        $ld = Carbon::parse($l->created_at)->toDateString();

                        return $ld >= Carbon::parse($entryStartStr)->subDays(self::ATTRIBUTION_WINDOW_DAYS)->toDateString()
                            && $ld <= $entryEndStr;
                    });
                }
                $updates['lead_id'] = ($preferredLead ?: $allLeadsCollection->first())->id;
            }

            if (!empty($updates)) {
                $entry->update($updates);
            }
        }

        $returnEntry = $targetEntry ?: $coveringEntry;

        return $returnEntry ? $returnEntry->fresh() : null;
    }

    /**
     * All available ad categories (from the ad_categories table, default constants,
     * and any distinct categories logged in ad_lead_entries).
     */
    public static function availableCategories(): array
    {
        $categories = self::CATEGORIES;

        if (Schema::hasTable('ad_categories')) {
            $stored = AdCategory::orderBy('id')->pluck('name')->all();
            $categories = array_merge($categories, $stored);
        }

        if (Schema::hasTable('ad_lead_entries')) {
            $logged = self::query()
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->all();
            $categories = array_merge($categories, $logged);
        }

        return array_values(array_unique(array_filter(array_map('trim', $categories))));
    }
}
