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
        'old_airport_amount',
        'wakrah_amount',
        'branch',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'ticket_amount' => 'decimal:2',
        'old_airport_amount' => 'decimal:2',
        'wakrah_amount' => 'decimal:2',
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
     * Legacy constant retained for backward compatibility; attribution now strictly
     * enforces same-calendar-month boundaries [entry.date, endOfMonth(entry.date)].
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
     * Return exact attributed net revenue for a specific branch ('old_airport' or 'wakrah'),
     * supporting dual-branch visits in the same month while falling back to branch/ticket_amount
     * when legacy/manual rows do not have explicit per-branch split columns populated.
     */
    public function branchRevenue(string $branch): float
    {
        if (!$this->isBooked() || !in_array($branch, array_keys(self::BRANCHES), true)) {
            return 0.0;
        }

        if ($this->old_airport_amount !== null || $this->wakrah_amount !== null) {
            return match ($branch) {
                'old_airport' => round(max(0.0, (float) $this->old_airport_amount), 2),
                'wakrah' => round(max(0.0, (float) $this->wakrah_amount), 2),
                default => 0.0,
            };
        }

        return $this->branch === $branch
            ? round(max(0.0, (float) $this->ticket_amount), 2)
            : 0.0;
    }

    /**
     * True when this entry has positive attributed paid revenue at the given branch.
     */
    public function hasBranchConversion(string $branch): bool
    {
        return $this->branchRevenue($branch) > 0;
    }

    /**
     * SQLite & MySQL compatible SQL expression stripping formatting characters from a phone column.
     */
    public static function normalizedPhoneSqlExpression(string $column = 'phone'): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')";
    }

    /**
     * Build canonical normalized phone numbers, exact string candidates, and stripped-digit variants
     * (covering 8-digit local Qatar numbers as well as +974, 974, 00974, leading-zero, spaced, and dashed variants).
     *
     * @param  array<int, string|null>  $rawPhones
     * @return array{0: array<int, string>, 1: array<int, string>, 2: array<int, string>}
     */
    public static function buildPhoneLookupSets(array $rawPhones): array
    {
        $canonicalPhones = [];
        $exactVariants = [];
        $strippedDigitsSet = [];

        foreach ($rawPhones as $raw) {
            $rawStr = trim((string) $raw);
            if ($rawStr === '') {
                continue;
            }

            $exactVariants[] = $rawStr;
            $digits = preg_replace('/\D+/', '', $rawStr);
            if ($digits !== '') {
                $exactVariants[] = $digits;
                $strippedDigitsSet[] = $digits;
            }

            $normCustomer = Customer::normalizePhone($rawStr);
            [$cc, $local] = Lead::splitPhone($digits);
            $normLead = Lead::normalizePhone($cc, $local);

            foreach ([$normCustomer, $normLead] as $norm) {
                $norm = trim((string) $norm);
                if ($norm === '') {
                    continue;
                }

                $canonicalPhones[] = $norm;
                $exactVariants[] = $norm;
                $strippedDigitsSet[] = $norm;

                if (strlen($norm) === 8) {
                    $exactVariants[] = '974' . $norm;
                    $exactVariants[] = '+974' . $norm;
                    $exactVariants[] = '00974' . $norm;
                    $exactVariants[] = '0' . $norm;

                    $strippedDigitsSet[] = '974' . $norm;
                    $strippedDigitsSet[] = '00974' . $norm;
                    $strippedDigitsSet[] = '0' . $norm;
                    $strippedDigitsSet[] = '9740' . $norm;
                    $strippedDigitsSet[] = '009740' . $norm;
                }
            }
        }

        return [
            array_values(array_unique(array_filter($canonicalPhones))),
            array_values(array_unique(array_filter($exactVariants))),
            array_values(array_unique(array_filter($strippedDigitsSet))),
        ];
    }

    /**
     * Add a phone matching clause matching both exact variants and stripped formatting variants.
     */
    public static function applyPhoneCohortClause(
        $query,
        array $exactVariants,
        array $strippedDigitsSet,
        string $boolean = 'or',
        string $column = 'phone'
    ): void {
        if (empty($exactVariants) && empty($strippedDigitsSet)) {
            return;
        }

        $method = strtolower($boolean) === 'and' ? 'where' : 'orWhere';
        $sqlExpr = self::normalizedPhoneSqlExpression($column);

        $query->{$method}(function ($sub) use ($exactVariants, $strippedDigitsSet, $column, $sqlExpr) {
            if (!empty($exactVariants)) {
                $sub->whereIn($column, $exactVariants);
            }
            if (!empty($strippedDigitsSet)) {
                $placeholders = implode(', ', array_fill(0, count($strippedDigitsSet), '?'));
                $sub->orWhereRaw("{$sqlExpr} IN ({$placeholders})", $strippedDigitsSet);
            }
        });
    }

    /**
     * Resolve or create the single canonical Customer profile for a phone number,
     * normalizing Qatar +974 / 974 / 00974 / spaced / dashed phones to the 8-digit local number.
     */
    public static function resolveOrCreateCanonicalCustomer(
        ?string $rawPhone,
        ?string $customerName = null,
        ?Customer $hintCustomer = null
    ): ?Customer {
        if (!Schema::hasTable('customers')) {
            return $hintCustomer;
        }

        $effectiveRaw = trim((string) ($rawPhone ?: $hintCustomer?->phone));
        $canonicalPhone = Customer::normalizePhone($effectiveRaw);

        if ($canonicalPhone === '' && $hintCustomer) {
            return $hintCustomer;
        }

        if ($canonicalPhone === '') {
            return null;
        }

        [, $exactVariants, $strippedDigits] = self::buildPhoneLookupSets([
            $effectiveRaw,
            $canonicalPhone,
            $hintCustomer?->phone,
        ]);

        $matches = Customer::query()
            ->where(function ($q) use ($hintCustomer, $exactVariants, $strippedDigits) {
                if ($hintCustomer?->id) {
                    $q->where('id', $hintCustomer->id);
                }
                self::applyPhoneCohortClause($q, $exactVariants, $strippedDigits, 'or', 'phone');
            })
            ->orderBy('id')
            ->get();

        // Prefer the Customer row already holding the canonical 8-digit phone; else earliest Customer record
        $canonical = $matches->firstWhere('phone', $canonicalPhone) ?: $matches->first();
        $cleanName = trim((string) $customerName);

        if (!$canonical) {
            return Customer::create([
                'phone' => $canonicalPhone,
                'name' => $cleanName !== '' ? $cleanName : 'Ad Lead Client',
            ]);
        }

        $updates = [];
        if (
            $canonical->phone !== $canonicalPhone
            && !Customer::where('phone', $canonicalPhone)->where('id', '!=', $canonical->id)->exists()
        ) {
            $updates['phone'] = $canonicalPhone;
        }

        $existingNameLower = strtolower(trim((string) $canonical->name));
        if (
            $cleanName !== ''
            && strtolower($cleanName) !== 'ad lead client'
            && ($existingNameLower === '' || $existingNameLower === 'walk-in' || $existingNameLower === 'ad lead client')
        ) {
            $updates['name'] = $cleanName;
        }

        if (!empty($updates)) {
            $canonical->update($updates);
        }

        return $canonical;
    }

    /**
     * Build normalized phone candidates and matching customer IDs for this entry.
     *
     * @return array{0: array<int, int>, 1: array<int, string>, 2: array<int, string>}
     */
    public function resolveIdentityCohort(): array
    {
        $rawPhones = array_filter([
            $this->phone,
            $this->customer?->phone,
        ]);

        [, $phoneCandidates, $strippedDigits] = self::buildPhoneLookupSets($rawPhones);

        $customerIds = array_values(array_unique(array_filter([
            $this->customer_id,
        ])));

        if ((!empty($phoneCandidates) || !empty($strippedDigits)) && Schema::hasTable('customers')) {
            $matchedIds = Customer::query()
                ->where(function ($q) use ($phoneCandidates, $strippedDigits) {
                    self::applyPhoneCohortClause($q, $phoneCandidates, $strippedDigits, 'or', 'phone');
                })
                ->pluck('id')
                ->all();
            $customerIds = array_values(array_unique(array_merge($customerIds, $matchedIds)));
        }

        return [$customerIds, $phoneCandidates, $strippedDigits];
    }

    /**
     * Return a real non-cancelled / non-no-show Appointment for this client in the same month if one exists.
     */
    public function resolveVerifiedAppointment(): ?Appointment
    {
        if (!Schema::hasTable('appointments')) {
            return null;
        }

        [$customerIds, $phoneCandidates, $strippedDigits] = $this->resolveIdentityCohort();
        if (empty($customerIds) && empty($phoneCandidates) && empty($strippedDigits)) {
            return null;
        }

        $entryDate = $this->date ? Carbon::parse($this->date) : today();
        $minDateStr = $entryDate->toDateString();
        $maxDateStr = $entryDate->copy()->endOfMonth()->toDateString();

        $appointments = Appointment::query()
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($customerIds, $phoneCandidates, $strippedDigits) {
                if (!empty($customerIds)) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
                self::applyPhoneCohortClause($q, $phoneCandidates, $strippedDigits, 'or', 'phone');
            })
            ->orderByDesc('appointment_datetime')
            ->orderByDesc('id')
            ->get();

        if ($appointments->isEmpty()) {
            return null;
        }

        return $appointments->first(function (Appointment $a) use ($minDateStr, $maxDateStr) {
            $apptDateStr = $a->appointment_date->toDateString();

            return $apptDateStr >= $minDateStr && $apptDateStr <= $maxDateStr;
        });
    }

    /**
     * True ONLY if the client has a real non-cancelled/non-no-show Appointment
     * OR a verified paid Sale record in the same calendar month on or after $this->date.
     */
    public function hasVerifiedCustomerConversion(): bool
    {
        if ($this->resolveVerifiedAppointment() !== null) {
            return true;
        }

        [$customerIds, $phoneCandidates, $strippedDigits] = $this->resolveIdentityCohort();
        if ((empty($customerIds) && empty($phoneCandidates) && empty($strippedDigits)) || !Schema::hasTable('sales')) {
            return false;
        }

        $entryDate = $this->date ? Carbon::parse($this->date) : today();
        $minDateStr = $entryDate->toDateString();
        $maxDateStr = $entryDate->copy()->endOfMonth()->toDateString();

        $appointmentIds = [];
        if (Schema::hasTable('appointments')) {
            $appointmentIds = Appointment::query()
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($customerIds, $phoneCandidates, $strippedDigits) {
                    if (!empty($customerIds)) {
                        $q->orWhereIn('customer_id', $customerIds);
                    }
                    self::applyPhoneCohortClause($q, $phoneCandidates, $strippedDigits, 'or', 'phone');
                })
                ->pluck('id')
                ->all();
        }

        if (empty($customerIds) && empty($appointmentIds)) {
            return false;
        }

        $sales = Sale::with('appointment')
            ->where(function ($q) use ($customerIds, $appointmentIds) {
                if (!empty($customerIds)) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
                if (!empty($appointmentIds)) {
                    $q->orWhereIn('appointment_id', $appointmentIds);
                }
            })
            ->get();

        foreach ($sales as $sale) {
            if ($sale->appointment && in_array(strtolower((string) $sale->appointment->status), ['cancelled', 'no_show'], true)) {
                continue;
            }
            $saleDateStr = self::resolveSaleDate($sale)->toDateString();
            if ($saleDateStr < $minDateStr || $saleDateStr > $maxDateStr) {
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
     * - Canonical Customer Profile bridge (8-digit normalized Qatar phone across +974/974/00974/spaces/dashes)
     * - Strict same-calendar-month attribution window: [entry.date, endOfMonth(entry.date)]
     * - Last-touch single-winner attribution: each Sale (and standalone paid Appointment)
     *   attributes to at most ONE AdLeadEntry — the most recent entry in that calendar month
     *   on or before the conversion date — with exact per-branch revenue splits.
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

        [$canonicalPhones, $phoneCandidates, $strippedDigits] = self::buildPhoneLookupSets($rawPhones);

        $preferredName = $targetEntry?->customer_name
            ?: ($appointment?->customer_name ?: $customer?->name);
        $preferredRawPhone = $phone
            ?: ($targetEntry?->phone ?: ($appointment?->phone ?: $customer?->phone));

        $canonicalCustomer = self::resolveOrCreateCanonicalCustomer(
            rawPhone: $preferredRawPhone,
            customerName: $preferredName,
            hintCustomer: $customer
        );

        $customerIds = array_values(array_unique(array_filter([
            $canonicalCustomer?->id,
            $customer?->id,
            $targetEntry?->customer_id,
            $appointment?->customer_id,
        ])));

        if ((!empty($phoneCandidates) || !empty($strippedDigits)) && Schema::hasTable('customers')) {
            $matchedCustomerIds = Customer::query()
                ->where(function ($q) use ($phoneCandidates, $strippedDigits) {
                    self::applyPhoneCohortClause($q, $phoneCandidates, $strippedDigits, 'or', 'phone');
                })
                ->pluck('id')
                ->all();
            $customerIds = array_values(array_unique(array_merge($customerIds, $matchedCustomerIds)));
        }

        if (!empty($customerIds) && Schema::hasTable('customers')) {
            $extraPhones = Customer::whereIn('id', $customerIds)->pluck('phone')->filter()->all();
            if (!empty($extraPhones)) {
                [$canonicalPhones, $phoneCandidates, $strippedDigits] = self::buildPhoneLookupSets(
                    array_merge($rawPhones, $extraPhones)
                );
            }
        }

        $leadIds = array_values(array_unique(array_filter([
            $targetEntry?->lead_id,
        ])));

        if (Schema::hasTable('leads') && (!empty($customerIds) || !empty($phoneCandidates) || !empty($strippedDigits) || $appointmentId)) {
            $matchedLeadIds = Lead::query()
                ->where(function ($q) use ($customerIds, $phoneCandidates, $strippedDigits, $appointmentId) {
                    if (!empty($customerIds)) {
                        $q->orWhereIn('customer_id', $customerIds);
                    }
                    self::applyPhoneCohortClause($q, $phoneCandidates, $strippedDigits, 'or', 'phone');
                    if ($appointmentId) {
                        $q->orWhere('appointment_id', $appointmentId);
                    }
                })
                ->pluck('id')
                ->all();

            $leadIds = array_values(array_unique(array_merge($leadIds, $matchedLeadIds)));
        }

        if (empty($customerIds) && empty($phoneCandidates) && empty($strippedDigits) && empty($leadIds) && !$targetEntry) {
            return null;
        }

        $hasLeadIdColumn = Schema::hasColumn('ad_lead_entries', 'lead_id');
        $hasCustomerIdColumn = Schema::hasColumn('ad_lead_entries', 'customer_id');
        $hasSplitColumns = Schema::hasColumn('ad_lead_entries', 'old_airport_amount')
            && Schema::hasColumn('ad_lead_entries', 'wakrah_amount');

        $clientEntries = self::query()
            ->where(function ($q) use ($customerIds, $leadIds, $phoneCandidates, $strippedDigits, $hasCustomerIdColumn, $hasLeadIdColumn, $targetEntry) {
                if ($hasCustomerIdColumn && !empty($customerIds)) {
                    $q->orWhereIn('customer_id', $customerIds);
                }
                if ($hasLeadIdColumn && !empty($leadIds)) {
                    $q->orWhereIn('lead_id', $leadIds);
                }
                self::applyPhoneCohortClause($q, $phoneCandidates, $strippedDigits, 'or', 'phone');
                if ($targetEntry?->id) {
                    $q->orWhere('id', $targetEntry->id);
                }
            })
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $activityDateStr = $activityCarbon->toDateString();
        $activityMonthStartStr = $activityCarbon->copy()->startOfMonth()->toDateString();

        $coveringEntry = $clientEntries
            ->filter(function (self $e) use ($activityMonthStartStr, $activityDateStr) {
                $d = Carbon::parse($e->date)->toDateString();

                return $d >= $activityMonthStartStr && $d <= $activityDateStr;
            })
            ->sortBy([
                [fn (self $e) => Carbon::parse($e->date)->toDateString(), 'desc'],
                ['id', 'desc'],
            ])
            ->first();

        if (!$coveringEntry && !$targetEntry && !empty($leadIds) && Schema::hasTable('leads')) {
            $windowLeads = Lead::whereIn('id', $leadIds)
                ->whereDate('created_at', '>=', $activityMonthStartStr)
                ->whereDate('created_at', '<=', $activityDateStr)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            $paidAdLead = $windowLeads->first(fn (Lead $l) => $l->isPaidAdLead());
            if ($paidAdLead) {
                $leadDate = $paidAdLead->created_at ? Carbon::parse($paidAdLead->created_at)->toDateString() : $activityDateStr;
                $resolvedCustomer = $canonicalCustomer ?: self::resolveOrCreateCanonicalCustomer(
                    rawPhone: $paidAdLead->phone ?: ($canonicalPhones[0] ?? ($phoneCandidates[0] ?? null)),
                    customerName: $customer?->name,
                    hintCustomer: $paidAdLead->customer_id ? Customer::find($paidAdLead->customer_id) : null
                );
                if ($resolvedCustomer && !in_array($resolvedCustomer->id, $customerIds, true)) {
                    $customerIds[] = $resolvedCustomer->id;
                }
                $canonicalLeadPhone = Customer::normalizePhone($paidAdLead->phone ?: ($phoneCandidates[0] ?? ''));
                $resolvedCategory = self::resolveCategoryFromLead($paidAdLead);
                $entryPayload = [
                    'date' => $leadDate,
                    'phone' => $canonicalLeadPhone !== '' ? $canonicalLeadPhone : ($paidAdLead->phone ?: ($phoneCandidates[0] ?? '')),
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
                $canonicalCustomer = $canonicalCustomer ?: $resolvedCustomer;
            }
        }

        if ($clientEntries->isEmpty()) {
            return null;
        }

        if (!$canonicalCustomer && !empty($customerIds) && Schema::hasTable('customers')) {
            $canonicalCustomer = Customer::find($customerIds[0]);
        }

        // Order descending by (date, id) so the first matching entry in that month is the last-touch winner
        $entriesDesc = $clientEntries
            ->sortBy([
                [fn (self $e) => Carbon::parse($e->date)->toDateString(), 'desc'],
                ['id', 'desc'],
            ])
            ->values();

        // Collect all appointments for this client cohort and repair missing customer_id links
        $allClientAppointments = collect();
        if (Schema::hasTable('appointments') && (!empty($customerIds) || !empty($phoneCandidates) || !empty($strippedDigits) || $appointmentId)) {
            $allClientAppointments = Appointment::query()
                ->where(function ($q) use ($customerIds, $phoneCandidates, $strippedDigits, $appointmentId) {
                    if (!empty($customerIds)) {
                        $q->orWhereIn('customer_id', $customerIds);
                    }
                    self::applyPhoneCohortClause($q, $phoneCandidates, $strippedDigits, 'or', 'phone');
                    if ($appointmentId) {
                        $q->orWhere('id', $appointmentId);
                    }
                })
                ->get();

            if ($canonicalCustomer && Schema::hasColumn('appointments', 'customer_id')) {
                foreach ($allClientAppointments as $apptRow) {
                    if (!$apptRow->customer_id) {
                        $apptRow->update(['customer_id' => $canonicalCustomer->id]);
                    }
                }
            }
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
                if ($canonicalCustomer && !$sale->customer_id && Schema::hasColumn('sales', 'customer_id')) {
                    $sale->update(['customer_id' => $canonicalCustomer->id]);
                }
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

        // Strict same-calendar-month last-touch single-winner attribution: [entry.date, endOfMonth(entry.date)]
        $attributedByEntryId = [];
        $claimedConversionDates = [];

        foreach ($conversions as $conv) {
            $convDateStr = $conv['date_str'];
            $winner = $entriesDesc->first(function (self $candidate) use ($convDateStr) {
                $entryStart = Carbon::parse($candidate->date);
                $entryStartStr = $entryStart->toDateString();
                $entryEndStr = $entryStart->copy()->endOfMonth()->toDateString();

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
            $entryMonthStartStr = $entryStart->copy()->startOfMonth()->toDateString();
            $entryEndStr = $entryStart->copy()->endOfMonth()->toDateString();

            $myConversions = $attributedByEntryId[$entry->id] ?? [];
            $newBranch = $entry->branch;
            $newTicketAmount = round((float) $entry->ticket_amount, 2);
            $newOaAmount = $entry->old_airport_amount !== null ? round((float) $entry->old_airport_amount, 2) : null;
            $newWkAmount = $entry->wakrah_amount !== null ? round((float) $entry->wakrah_amount, 2) : null;

            if (!empty($myConversions)) {
                $newTicketAmount = round((float) array_sum(array_column($myConversions, 'amount')), 2);
                $newOaAmount = 0.0;
                $newWkAmount = 0.0;

                // Attribute primary branch from majority revenue among THIS entry's conversions (tie-break: earliest conversion),
                // while storing exact per-branch net revenue in old_airport_amount and wakrah_amount.
                $branchTotals = [];
                $branchFirstTs = [];
                foreach ($myConversions as $mc) {
                    $b = $mc['branch'];
                    if (!$b) {
                        continue;
                    }
                    $amt = (float) $mc['amount'];
                    $branchTotals[$b] = ($branchTotals[$b] ?? 0.0) + $amt;
                    if ($b === 'old_airport') {
                        $newOaAmount = round($newOaAmount + $amt, 2);
                    } elseif ($b === 'wakrah') {
                        $newWkAmount = round($newWkAmount + $amt, 2);
                    }
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
                    $newOaAmount = 0.0;
                    $newWkAmount = 0.0;
                }
            }

            $updates = [];
            if ($entry->branch !== $newBranch) {
                $updates['branch'] = $newBranch;
            }
            if (round((float) $entry->ticket_amount, 2) !== round((float) $newTicketAmount, 2)) {
                $updates['ticket_amount'] = $newTicketAmount;
            }
            if ($hasSplitColumns) {
                $currOa = $entry->old_airport_amount !== null ? round((float) $entry->old_airport_amount, 2) : null;
                $currWk = $entry->wakrah_amount !== null ? round((float) $entry->wakrah_amount, 2) : null;
                if ($currOa !== $newOaAmount) {
                    $updates['old_airport_amount'] = $newOaAmount;
                }
                if ($currWk !== $newWkAmount) {
                    $updates['wakrah_amount'] = $newWkAmount;
                }
            }
            if ($hasCustomerIdColumn && $canonicalCustomer && (int) $entry->customer_id !== (int) $canonicalCustomer->id) {
                if (!$entry->customer_id || in_array((int) $entry->customer_id, $customerIds, true)) {
                    $updates['customer_id'] = $canonicalCustomer->id;
                }
            }
            $normalizedEntryPhone = Customer::normalizePhone($entry->phone);
            if ($normalizedEntryPhone !== '' && $entry->phone !== $normalizedEntryPhone) {
                $updates['phone'] = $normalizedEntryPhone;
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
                    $preferredLead = $allLeadsCollection->first(function (Lead $l) use ($entryMonthStartStr, $entryEndStr) {
                        if (!$l->created_at) {
                            return false;
                        }
                        $ld = Carbon::parse($l->created_at)->toDateString();

                        return $ld >= $entryMonthStartStr && $ld <= $entryEndStr;
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
