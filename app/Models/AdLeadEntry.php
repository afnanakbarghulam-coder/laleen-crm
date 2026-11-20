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

    protected static function booted(): void
    {
        static::saved(function (self $entry): void {
            if (!empty($entry->branch)) {
                Customer::syncBranchFromActivity(
                    $entry->customer_id ? (int) $entry->customer_id : null,
                    $entry->phone,
                    $entry->branch
                );
            }
        });
    }

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
     * Rolling spillover days past `entry.date` when a lead arrives near month-end.
     * Window end is `max(endOfMonth(entry.date), entry.date + SPILLOVER_DAYS)`.
     */
    public const SPILLOVER_DAYS = 14;

    /**
     * Legacy constant retained for backward compatibility; attribution now enforces
     * same-month + 14-day rolling spillover via attributionWindowFor().
     */
    public const ATTRIBUTION_WINDOW_DAYS = 30;

    /**
     * Compute the inclusive attribution window [windowStart, windowEnd] for an AdLeadEntry date:
     * start = entry.date, end = max(endOfMonth(entry.date), entry.date + 14 days).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function attributionWindowFor(Carbon|string $entryDate): array
    {
        $start = ($entryDate instanceof Carbon ? $entryDate->copy() : Carbon::parse($entryDate))->startOfDay();
        $endOfMonth = $start->copy()->endOfMonth()->startOfDay();
        $rollingEnd = $start->copy()->addDays(self::SPILLOVER_DAYS)->startOfDay();
        $end = $rollingEnd->greaterThan($endOfMonth) ? $rollingEnd : $endOfMonth;

        return [$start, $end];
    }

    /**
     * A lead is counted as booked/converted whenever it has a valid assigned branch
     * from an active Enhanced Calendar appointment — including 0 QAR ("Decide in Salon") bookings.
     */
    public function isBooked(): bool
    {
        return in_array($this->branch, array_keys(self::BRANCHES), true);
    }

    /**
     * Return exact attributed net revenue for a specific branch ('old_airport' or 'wakrah'),
     * supporting dual-branch visits in the same attribution window while falling back to branch/ticket_amount
     * when legacy rows do not have explicit per-branch split columns populated.
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
     * True when this entry is booked at the given branch or has positive attributed revenue at it.
     */
    public function hasBranchConversion(string $branch): bool
    {
        return $this->isBooked() && ($this->branch === $branch || $this->branchRevenue($branch) > 0);
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

            foreach (Customer::phoneMatchVariants($rawStr) as $variant) {
                $exactVariants[] = $variant;
                $variantDigits = preg_replace('/\D+/', '', $variant);
                if ($variantDigits !== '') {
                    $strippedDigitsSet[] = $variantDigits;
                }
            }

            foreach ([$normCustomer, $normLead] as $norm) {
                $norm = trim((string) $norm);
                if ($norm === '') {
                    continue;
                }

                $canonicalPhones[] = $norm;
                $exactVariants[] = $norm;
                $normDigits = preg_replace('/\D+/', '', $norm);
                if ($normDigits !== '') {
                    $strippedDigitsSet[] = $normDigits;
                }

                if (strlen($normDigits) === 8) {
                    $exactVariants[] = '974' . $normDigits;
                    $exactVariants[] = '+974' . $normDigits;
                    $exactVariants[] = '00974' . $normDigits;
                    $exactVariants[] = '0' . $normDigits;

                    $strippedDigitsSet[] = '974' . $normDigits;
                    $strippedDigitsSet[] = '00974' . $normDigits;
                    $strippedDigitsSet[] = '0' . $normDigits;
                    $strippedDigitsSet[] = '9740' . $normDigits;
                    $strippedDigitsSet[] = '009740' . $normDigits;
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
     * storing new and upgraded `customers.phone` values in canonical E.164 format
     * (+974XXXXXXXX for Qatar, +<cc><number> for international) and preferring E.164 rows
     * over legacy 8-digit rows when duplicates exist.
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
        $e164Phone = Customer::normalizePhoneE164($effectiveRaw);
        $matchPhone = Customer::normalizePhone($effectiveRaw);
        $storagePhone = $e164Phone !== '' ? $e164Phone : $matchPhone;

        if ($storagePhone === '' && $hintCustomer) {
            return $hintCustomer;
        }

        if ($storagePhone === '') {
            return null;
        }

        [, $exactVariants, $strippedDigits] = self::buildPhoneLookupSets([
            $effectiveRaw,
            $e164Phone,
            $matchPhone,
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

        // Prefer a Customer row already holding canonical E.164 phone; fall back to normalized match, then earliest row
        $canonical = ($e164Phone !== '' ? $matches->firstWhere('phone', $e164Phone) : null)
            ?: $matches->first(fn (Customer $c) => Customer::isCanonicalE164($c->phone))
            ?: ($matchPhone !== '' ? $matches->firstWhere('phone', $matchPhone) : null)
            ?: $matches->first();
        $cleanName = trim((string) $customerName);

        if (!$canonical) {
            return Customer::create([
                'phone' => $storagePhone,
                'name' => $cleanName !== '' ? $cleanName : 'Ad Lead Client',
            ]);
        }

        $updates = [];
        if (
            $e164Phone !== ''
            && !Customer::isCanonicalE164($canonical->phone)
            && $canonical->phone !== $e164Phone
            && !Customer::where('phone', $e164Phone)->where('id', '!=', $canonical->id)->exists()
        ) {
            $updates['phone'] = $e164Phone;
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
     * Return a real non-cancelled / non-no-show Appointment for this client within
     * the entry's attribution window [entry.date, max(endOfMonth(entry.date), entry.date + 14 days)].
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

        [$windowStart, $windowEnd] = self::attributionWindowFor($this->date ?: today());
        $minDateStr = $windowStart->toDateString();
        $maxDateStr = $windowEnd->toDateString();

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
     * True ONLY if the client has an active Enhanced Calendar Appointment (not cancelled/no_show)
     * within this entry's attribution window.
     */
    public function hasVerifiedCustomerConversion(): bool
    {
        return $this->resolveVerifiedAppointment() !== null;
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
        $allLines = $appointment->appointmentServices()->get();
        $linesSum = (float) $allLines->where('is_pending', false)->sum(fn ($s) => $s->final_price);

        $unpaidPackagesTotal = Schema::hasTable('client_packages')
            ? (float) ClientPackage::where('appointment_id', $appointment->id)->whereNull('sale_id')->sum('price_paid')
            : 0.0;

        $baseServiceTotal = ($linesSum > 0 || $allLines->isNotEmpty() || $unpaidPackagesTotal > 0)
            ? $linesSum
            : (float) $appointment->price;

        $upsellsTotal = Schema::hasTable('appointment_upsells')
            ? (float) $appointment->upsells()->sum('amount')
            : 0.0;

        return round(max(0.0, $baseServiceTotal + $unpaidPackagesTotal + $upsellsTotal), 2);
    }

    /**
     * Synchronize AdLeadEntry attribution for a client cohort using:
     * - Canonical E.164 Customer Profile bridge (matching +974/974/00974/8-digit/international variants)
     * - Enhanced Calendar Appointments as the single source of truth (no standalone walk-in sales):
     *   active appointments (`status NOT IN ('cancelled','no_show')`) credit immediately on booking
     *   (including 0 QAR "Decide in Salon" bookings) using `appointmentBookedAmount()` while unpaid,
     *   and automatically upgrade to `netSaleAmount($appt->sale)` once checked out.
     * - Attribution window: `[entry.date, max(endOfMonth(entry.date), entry.date + 14 days)]`
     * - Last-touch single-winner per appointment across overlapping lead windows, with exact
     *   per-branch revenue splits (`old_airport_amount`, `wakrah_amount`).
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

        if (empty($customerIds) && empty($phoneCandidates) && empty($strippedDigits) && !$targetEntry) {
            return null;
        }

        $hasCustomerIdColumn = Schema::hasColumn('ad_lead_entries', 'customer_id');
        $hasSplitColumns = Schema::hasColumn('ad_lead_entries', 'old_airport_amount')
            && Schema::hasColumn('ad_lead_entries', 'wakrah_amount');

        // Load all AdLeadEntry rows for this client cohort (covers same-month AND prior-month 14-day spillover leads)
        $clientEntries = self::query()
            ->where(function ($q) use ($customerIds, $phoneCandidates, $strippedDigits, $hasCustomerIdColumn, $targetEntry) {
                if ($hasCustomerIdColumn && !empty($customerIds)) {
                    $q->orWhereIn('customer_id', $customerIds);
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

        $coveringEntry = $clientEntries
            ->filter(function (self $e) use ($activityDateStr) {
                [$winStart, $winEnd] = self::attributionWindowFor($e->date);

                return $activityDateStr >= $winStart->toDateString()
                    && $activityDateStr <= $winEnd->toDateString();
            })
            ->sortBy([
                [fn (self $e) => Carbon::parse($e->date)->toDateString(), 'desc'],
                ['id', 'desc'],
            ])
            ->first();

        if ($clientEntries->isEmpty()) {
            return null;
        }

        if (!$canonicalCustomer && !empty($customerIds) && Schema::hasTable('customers')) {
            $canonicalCustomer = Customer::find($customerIds[0]);
        }

        // Order descending by (date, id) so the first matching entry whose window covers the appointment wins (last-touch)
        $entriesDesc = $clientEntries
            ->sortBy([
                [fn (self $e) => Carbon::parse($e->date)->toDateString(), 'desc'],
                ['id', 'desc'],
            ])
            ->values();

        // Collect all Enhanced Calendar appointments for this client cohort (including spillover dates) and repair links
        $allClientAppointments = collect();
        $hasSalesTable = Schema::hasTable('sales');
        if (Schema::hasTable('appointments') && (!empty($customerIds) || !empty($phoneCandidates) || !empty($strippedDigits) || $appointmentId)) {
            $apptQuery = Appointment::query()
                ->where(function ($q) use ($customerIds, $phoneCandidates, $strippedDigits, $appointmentId) {
                    if (!empty($customerIds)) {
                        $q->orWhereIn('customer_id', $customerIds);
                    }
                    self::applyPhoneCohortClause($q, $phoneCandidates, $strippedDigits, 'or', 'phone');
                    if ($appointmentId) {
                        $q->orWhere('id', $appointmentId);
                    }
                });

            if ($hasSalesTable) {
                $apptQuery->with('sale');
            }

            $allClientAppointments = $apptQuery->get();

            if ($canonicalCustomer && Schema::hasColumn('appointments', 'customer_id')) {
                foreach ($allClientAppointments as $apptRow) {
                    if (!$apptRow->customer_id) {
                        $apptRow->update(['customer_id' => $canonicalCustomer->id]);
                    }
                    if ($hasSalesTable && $apptRow->sale && !$apptRow->sale->customer_id && Schema::hasColumn('sales', 'customer_id')) {
                        $apptRow->sale->update(['customer_id' => $canonicalCustomer->id]);
                    }
                }
            }
        }

        // Single source of truth: active Enhanced Calendar appointments ONLY (standalone walk-in sales excluded).
        // Unpaid appointments credit appointmentBookedAmount() (including 0 QAR Decide in Salon);
        // checked-out appointments with a linked Sale credit netSaleAmount($appt->sale) (excluding tips).
        $conversions = [];

        foreach ($allClientAppointments as $appt) {
            $statusLower = strtolower((string) ($appt->status ?? 'pending'));
            if (in_array($statusLower, ['cancelled', 'no_show'], true)) {
                continue;
            }

            $linkedSale = $hasSalesTable ? $appt->sale : null;
            $convBranch = Customer::normalizeBranch($appt->branch)
                ?: ($linkedSale ? Customer::normalizeBranch($linkedSale->branch) : null);

            if (!$convBranch || !in_array($convBranch, array_keys(self::BRANCHES), true)) {
                continue;
            }

            $amount = $linkedSale
                ? self::netSaleAmount($linkedSale)
                : self::appointmentBookedAmount($appt);

            $convDate = $appt->appointment_date;

            $conversions[] = [
                'date_str' => $convDate->toDateString(),
                'timestamp' => $convDate->timestamp,
                'amount' => round(max(0.0, (float) $amount), 2),
                'branch' => $convBranch,
                'appointment_id' => (int) $appt->id,
            ];
        }

        // Same-month + 14-day rolling spillover, last-touch single-winner attribution per appointment
        $attributedByEntryId = [];
        $claimedConversionDates = [];

        foreach ($conversions as $conv) {
            $convDateStr = $conv['date_str'];
            $winner = $entriesDesc->first(function (self $candidate) use ($convDateStr) {
                [$winStart, $winEnd] = self::attributionWindowFor($candidate->date);

                return $convDateStr >= $winStart->toDateString()
                    && $convDateStr <= $winEnd->toDateString();
            });

            if ($winner) {
                $attributedByEntryId[$winner->id][] = $conv;
                $claimedConversionDates[] = [
                    'winner_id' => $winner->id,
                    'date_str' => $convDateStr,
                ];
            }
        }

        foreach ($entriesDesc as $entry) {
            [$winStart, $winEnd] = self::attributionWindowFor($entry->date);
            $entryStartStr = $winStart->toDateString();
            $entryEndStr = $winEnd->toDateString();

            $myConversions = $attributedByEntryId[$entry->id] ?? [];
            $newBranch = $entry->branch;
            $newTicketAmount = round((float) $entry->ticket_amount, 2);
            $newOaAmount = $entry->old_airport_amount !== null ? round((float) $entry->old_airport_amount, 2) : null;
            $newWkAmount = $entry->wakrah_amount !== null ? round((float) $entry->wakrah_amount, 2) : null;

            if (!empty($myConversions)) {
                $newTicketAmount = round((float) array_sum(array_column($myConversions, 'amount')), 2);
                $newOaAmount = 0.0;
                $newWkAmount = 0.0;

                // Attribute primary branch from majority revenue among THIS entry's appointments (tie-break: earliest appointment),
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
