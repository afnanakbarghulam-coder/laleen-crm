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
     * A lead is only counted as a booking once it's assigned a branch AND
     * actually converted (ticket_amount > 0 or linked to a booked lead/appointment).
     * Unbooked inquiries with ticket_amount = 0 are never counted as booked just because branch is set.
     */
    public function isBooked(): bool
    {
        if (!in_array($this->branch, array_keys(self::BRANCHES), true)) {
            return false;
        }

        if ((float) $this->ticket_amount > 0) {
            return true;
        }

        if ($this->lead_id && Schema::hasTable('leads')) {
            $lead = $this->relationLoaded('lead') ? $this->lead : $this->lead()->first();
            if ($lead && ($lead->needful_done === 'booked' || $lead->appointment_id !== null)) {
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
     * Synchronize the matching AdLeadEntry for a client within the SAME calendar
     * month & year as $activityDate (where ad_lead_entries.date <= $activityDate).
     * Strictly isolated by calendar month so activity in a later month never mutates
     * an earlier month's AdLeadEntry, and adding a new AdLeadEntry in a new month
     * never pulls in past sales or bookings from a previous month.
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

        if (
            $targetEntry
            && $targetEntry->date
            && Carbon::parse($targetEntry->date)->year === $activityCarbon->year
            && Carbon::parse($targetEntry->date)->month === $activityCarbon->month
        ) {
            $entry = $targetEntry;
        } else {
            $entry = self::query()
                ->where(function ($q) use ($customerIds, $leadIds, $phoneCandidates, $hasCustomerIdColumn, $hasLeadIdColumn) {
                    if ($hasCustomerIdColumn && !empty($customerIds)) {
                        $q->orWhereIn('customer_id', $customerIds);
                    }
                    if ($hasLeadIdColumn && !empty($leadIds)) {
                        $q->orWhereIn('lead_id', $leadIds);
                    }
                    if (!empty($phoneCandidates)) {
                        $q->orWhereIn('phone', $phoneCandidates);
                    }
                })
                ->whereYear('date', $activityCarbon->year)
                ->whereMonth('date', $activityCarbon->month)
                ->whereDate('date', '<=', $activityCarbon->toDateString())
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->first();
        }

        if (!$entry && !empty($leadIds) && Schema::hasTable('leads')) {
            $sameMonthLeads = Lead::whereIn('id', $leadIds)
                ->whereYear('created_at', $activityCarbon->year)
                ->whereMonth('created_at', $activityCarbon->month)
                ->orderByDesc('id')
                ->get();

            $paidAdLead = $sameMonthLeads->first(fn (Lead $l) => $l->isPaidAdLead());
            if ($paidAdLead) {
                $leadDate = $paidAdLead->created_at ? Carbon::parse($paidAdLead->created_at)->toDateString() : $activityCarbon->toDateString();
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
                $entry = self::create($entryPayload);
            }
        }

        if (!$entry) {
            return null;
        }

        $entryDate = Carbon::parse($entry->date)->startOfDay();
        $entryDateStr = $entryDate->toDateString();
        $cohortYear = $entryDate->year;
        $cohortMonth = $entryDate->month;

        // Collect all appointments for this client and filter to the exact same month/year on or after $entry->date
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

        $cohortAppointments = $allClientAppointments->filter(function (Appointment $a) use ($cohortYear, $cohortMonth, $entryDateStr) {
            $apptCarbon = Carbon::parse($a->appointment_datetime ?: $a->booking_date ?: $a->created_at);

            return $apptCarbon->year === $cohortYear
                && $apptCarbon->month === $cohortMonth
                && $apptCarbon->toDateString() >= $entryDateStr;
        })->values();

        $activeAppointments = $cohortAppointments->filter(
            fn (Appointment $a) => !in_array(strtolower((string) ($a->status ?? 'pending')), ['cancelled', 'no_show'], true)
        )->values();

        // Collect all paid Sales for this client in the exact same month/year on or after $entry->date
        $allClientAppointmentIds = $allClientAppointments->pluck('id')->all();
        $cohortSales = collect();

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

            $cohortSales = $clientSales->filter(function (Sale $sale) use ($cohortYear, $cohortMonth, $entryDateStr) {
                if ($sale->appointment && in_array(strtolower((string) $sale->appointment->status), ['cancelled', 'no_show'], true)) {
                    return false;
                }

                $saleDate = self::resolveSaleDate($sale);

                return $saleDate->year === $cohortYear
                    && $saleDate->month === $cohortMonth
                    && $saleDate->toDateString() >= $entryDateStr;
            })->values();
        }

        $newBranch = $entry->branch;
        $newTicketAmount = round((float) $entry->ticket_amount, 2);

        if ($cohortSales->isNotEmpty()) {
            $totalPaidRevenue = round((float) $cohortSales->sum(fn (Sale $s) => self::netSaleAmount($s)), 2);
            $latestSale = $cohortSales->sortByDesc(fn (Sale $s) => self::resolveSaleDate($s)->timestamp)->first();
            $primaryAppt = ($appointmentId ? $activeAppointments->firstWhere('id', $appointmentId) : null)
                ?: $activeAppointments->sortByDesc(fn (Appointment $a) => Carbon::parse($a->appointment_datetime ?: $a->booking_date)->timestamp)->first();

            $resolvedBranch = $primaryAppt?->branch
                ?: ($latestSale->branch ?: ($latestSale->appointment?->branch ?: $entry->branch));

            if (in_array($resolvedBranch, array_keys(self::BRANCHES), true)) {
                $newBranch = $resolvedBranch;
            }
            $newTicketAmount = $totalPaidRevenue;
        } elseif ($activeAppointments->isNotEmpty()) {
            $primaryAppt = ($appointmentId ? $activeAppointments->firstWhere('id', $appointmentId) : null)
                ?: $activeAppointments->sortByDesc(fn (Appointment $a) => Carbon::parse($a->appointment_datetime ?: $a->booking_date)->timestamp)->first();

            if ($primaryAppt && in_array($primaryAppt->branch, array_keys(self::BRANCHES), true)) {
                $newBranch = $primaryAppt->branch;
            }

            $bookedTotal = round((float) self::appointmentBookedAmount($primaryAppt), 2);
            if ((float) $entry->ticket_amount <= 0 || ($resetIfNoActivity && $bookedTotal > 0)) {
                $newTicketAmount = $bookedTotal;
            }
        } elseif ($resetIfNoActivity) {
            $newBranch = null;
            $newTicketAmount = 0.0;
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
        if ($hasLeadIdColumn && !$entry->lead_id && !empty($leadIds)) {
            $preferredLeadId = null;
            if ($appointmentId) {
                $preferredLeadId = Lead::where('appointment_id', $appointmentId)->value('id');
            }
            if (!$preferredLeadId) {
                $preferredLeadId = Lead::whereIn('id', $leadIds)
                    ->whereYear('created_at', $cohortYear)
                    ->whereMonth('created_at', $cohortMonth)
                    ->orderByDesc('id')
                    ->value('id');
            }
            $updates['lead_id'] = $preferredLeadId ?: $leadIds[0];
        }

        if (!empty($updates)) {
            $entry->update($updates);
        }

        return $entry->fresh();
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
