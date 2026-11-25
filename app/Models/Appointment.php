<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Appointment extends Model
{
    use HasFactory;
    protected $table = 'appointments';

    public const BOOKING_AGENT_SALON_STAFF = 'salon_staff';
    public const BOOKING_AGENT_TYPE_AGENT = 'agent';
    public const BOOKING_AGENT_SALON_STAFF_LABEL = 'Salon Staff';
    public const BOOKING_AGENT_VALIDATION_ERROR = 'Please select a Booking Agent (Salon Staff, Areeba, or Zoya) before saving.';

    /**
     * Exact allowed Chat Agents selectable in the Booking Agent dropdown (in display order).
     */
    public const ALLOWED_BOOKING_AGENT_SLUGS = [
        'areeba' => 'Areeba',
        'zoya' => 'Zoya',
    ];

    protected $fillable = [
        'customer_name',
        'phone',
        'customer_id',
        'appointment_datetime',
        'booking_date',
        'start_time',
        // 'service_id',
        'service_name',
        'combo_id',
        'combo_name',
        'freebie_package_id',
        'is_freebie_redemption',
        'decide_in_salon',
        'hold_duration',
        'is_overtime',
        'overtime_minutes',
        'notes',
        'branch',
        'price',
        'original_price',
        'discount_amount',
        'lifetime_revenue',
        'booking_agent_id',
        'booking_agent_type',
        'created_by',
        'staff_id',
        'status',
        'payment_method',
        'paid_at'
    ];

    protected $casts = [
        'appointment_datetime' => 'datetime',
        'paid_at' => 'datetime',
        'decide_in_salon' => 'boolean',
        'hold_duration' => 'integer',
        'is_overtime' => 'boolean',
        'overtime_minutes' => 'integer',
        'freebie_package_id' => 'integer',
        'is_freebie_redemption' => 'boolean',
        'original_price' => 'float',
        'discount_amount' => 'float',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $appointment): void {
            if ($appointment->status !== 'cancelled' && !empty($appointment->branch)) {
                Customer::syncBranchFromActivity(
                    $appointment->customer_id ? (int) $appointment->customer_id : null,
                    $appointment->phone,
                    $appointment->branch
                );
            }
        });
    }

    public function setBranchAttribute($value): void
    {
        if (is_string($value)) {
            $normalized = Customer::normalizeBranch($value);
            $this->attributes['branch'] = $normalized ?? trim($value);
            return;
        }

        $this->attributes['branch'] = $value;
    }

    public function getBranchLabelAttribute(): string
    {
        return Customer::branchLabel($this->attributes['branch'] ?? null)
            ?? ucwords(str_replace('_', ' ', (string) ($this->attributes['branch'] ?? '')));
    }

    public function getAppointmentDateAttribute(): Carbon
    {
        return Carbon::parse($this->appointment_datetime ?: $this->booking_date ?: $this->created_at ?: now());
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'booking_agent_id');
    }

    /**
     * Build the exact ordered Booking Agent options for the Enhanced Calendar dropdown:
     * Salon Staff, Areeba, Zoya.
     *
     * @return list<array{value: string, label: string, type: string, user_id: int|null}>
     */
    public static function bookingAgentOptions(): array
    {
        $options = [
            [
                'value' => self::BOOKING_AGENT_SALON_STAFF,
                'label' => self::BOOKING_AGENT_SALON_STAFF_LABEL,
                'type' => self::BOOKING_AGENT_SALON_STAFF,
                'user_id' => null,
            ],
        ];

        $agentUsers = User::where('role', 'agent')
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->orderBy('id')
            ->get(['id', 'name']);

        foreach (self::ALLOWED_BOOKING_AGENT_SLUGS as $needle => $label) {
            $matched = $agentUsers->first(
                fn (User $u) => str_contains(mb_strtolower(trim((string) $u->name)), $needle)
            );
            if ($matched) {
                $options[] = [
                    'value' => (string) $matched->id,
                    'label' => $label,
                    'type' => self::BOOKING_AGENT_TYPE_AGENT,
                    'user_id' => (int) $matched->id,
                ];
            }
        }

        return $options;
    }

    public static function isAllowedBookingAgentUser(?User $user): bool
    {
        if (! $user || $user->role !== 'agent') {
            return false;
        }

        $norm = mb_strtolower(trim((string) $user->name));
        foreach (array_keys(self::ALLOWED_BOOKING_AGENT_SLUGS) as $needle) {
            if (str_contains($norm, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function canonicalAgentShortName(?string $name): ?string
    {
        $trimmed = trim((string) $name);
        if ($trimmed === '') {
            return null;
        }

        $lower = mb_strtolower($trimmed);
        foreach (self::ALLOWED_BOOKING_AGENT_SLUGS as $needle => $label) {
            if (str_contains($lower, $needle)) {
                return $label;
            }
        }

        return $trimmed;
    }

    /**
     * Resolve and validate a Booking Agent selection from form/API input.
     * Accepts:
     * - "salon_staff" or "Salon Staff" -> booking_agent_id = null, booking_agent_type = 'salon_staff'
     * - Numeric User ID for Areeba or Zoya (role = 'agent') -> booking_agent_id = user.id, booking_agent_type = 'agent'
     * - "Areeba" or "Zoya" string name resolved to the corresponding role = 'agent' User
     *
     * Returns null when empty or invalid.
     *
     * @return array{booking_agent_id: int|null, booking_agent_type: string, label: string}|null
     */
    public static function resolveBookingAgentSelection(mixed $rawValue, ?string $rawType = null): ?array
    {
        $strVal = is_scalar($rawValue) ? trim((string) $rawValue) : '';
        $normVal = mb_strtolower($strVal);
        $normType = mb_strtolower(trim((string) $rawType));

        if (
            in_array($normVal, [self::BOOKING_AGENT_SALON_STAFF, 'salon staff'], true)
            || ($strVal === '' && $normType === self::BOOKING_AGENT_SALON_STAFF)
        ) {
            return [
                'booking_agent_id' => null,
                'booking_agent_type' => self::BOOKING_AGENT_SALON_STAFF,
                'label' => self::BOOKING_AGENT_SALON_STAFF_LABEL,
            ];
        }

        if ($strVal === '') {
            return null;
        }

        if (ctype_digit($strVal)) {
            $user = User::where('id', (int) $strVal)
                ->where('role', 'agent')
                ->first(['id', 'name', 'role']);

            if (! self::isAllowedBookingAgentUser($user)) {
                return null;
            }

            return [
                'booking_agent_id' => (int) $user->id,
                'booking_agent_type' => self::BOOKING_AGENT_TYPE_AGENT,
                'label' => self::canonicalAgentShortName($user->name) ?? $user->name,
            ];
        }

        foreach (self::ALLOWED_BOOKING_AGENT_SLUGS as $needle => $label) {
            if ($normVal === $needle || str_contains($normVal, $needle)) {
                $user = User::where('role', 'agent')
                    ->get(['id', 'name', 'role'])
                    ->first(fn (User $u) => str_contains(mb_strtolower(trim((string) $u->name)), $needle));

                if ($user) {
                    return [
                        'booking_agent_id' => (int) $user->id,
                        'booking_agent_type' => self::BOOKING_AGENT_TYPE_AGENT,
                        'label' => $label,
                    ];
                }
            }
        }

        return null;
    }

    public function hasAssignedBookingAgent(): bool
    {
        return $this->booking_agent_id !== null
            || $this->booking_agent_type === self::BOOKING_AGENT_SALON_STAFF;
    }

    public function getBookingAgentDisplayNameAttribute(): ?string
    {
        if ($this->booking_agent_type === self::BOOKING_AGENT_SALON_STAFF) {
            return self::BOOKING_AGENT_SALON_STAFF_LABEL;
        }

        if ($this->booking_agent_id) {
            return self::canonicalAgentShortName($this->agent?->name);
        }

        return null;
    }

    public function getBookingAgentSelectValueAttribute(): string
    {
        if ($this->booking_agent_type === self::BOOKING_AGENT_SALON_STAFF) {
            return self::BOOKING_AGENT_SALON_STAFF;
        }

        return $this->booking_agent_id ? (string) $this->booking_agent_id : '';
    }

    /**
     * The account that physically created this booking record — distinct
     * from agent()/booking_agent_id, which is who the booking is credited
     * to. Used to verify a booking was self-created by an agent (not a
     * manager/admin acting on their behalf) for shift-window attribution.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function sale()
    {
        return $this->hasOne(Sale::class);
    }

    /**
     * Scope to appointments where the sale/checkout is actually paid or completed.
     * Unpaid scheduled/confirmed appointments are excluded until checkout/payment.
     */
    public function scopePaidOrCompleted($query)
    {
        return $query->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) {
                $q->where('status', 'completed')
                  ->orWhereNotNull('paid_at')
                  ->orWhereHas('sale');
            });
    }

    public function appointmentServices()
    {
        return $this->hasMany(AppointmentService::class)
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->orderBy('id');
    }

    public function activeAppointmentServices()
    {
        return $this->hasMany(AppointmentService::class)
            ->where('is_pending', false)
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->orderBy('id');
    }

    public function combo()
    {
        return $this->belongsTo(Combo::class, 'combo_id');
    }

    public function clientPackages()
    {
        return $this->hasMany(ClientPackage::class, 'appointment_id');
    }

    public function getDisplayComboNameAttribute(): ?string
    {
        $direct = trim((string) ($this->attributes['combo_name'] ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $names = collect();

        if ($this->relationLoaded('clientPackages')) {
            $names = $names->merge($this->clientPackages->pluck('combo_name'));
        }

        if ($this->relationLoaded('appointmentServices')) {
            $names = $names->merge($this->appointmentServices->pluck('combo_name'));
            foreach ($this->appointmentServices as $svc) {
                if ($svc->relationLoaded('clientPackageService') && $svc->clientPackageService?->relationLoaded('clientPackage')) {
                    $names->push($svc->clientPackageService->clientPackage?->combo_name);
                } elseif (is_string($svc->discount_reason) && preg_match('/^Redeemed from (.+) package$/i', trim($svc->discount_reason), $m)) {
                    $names->push(trim($m[1]));
                }
            }
        }

        $unique = $names->map(fn ($n) => trim((string) $n))->filter()->unique()->values();

        return $unique->isNotEmpty() ? $unique->implode(', ') : null;
    }

    public function getEffectiveHoldDurationAttribute(): int
    {
        $dur = (int) ($this->attributes['hold_duration'] ?? 0);

        return $dur > 0 ? $dur : 60;
    }

    /**
     * Total salon visit span in minutes (`max(slice_end) - min(slice_start)`),
     * naturally shortening when consecutive slices run in parallel (`is_parallel = true`).
     */
    public function getVisitSpanMinutesAttribute(): int
    {
        $lines = $this->relationLoaded('appointmentServices')
            ? $this->appointmentServices->where('is_pending', false)->values()
            : $this->activeAppointmentServices()->get();

        if ($lines->isEmpty()) {
            if ($this->is_combo_tbd) {
                return (int) ($this->hold_duration ?: ($this->combo?->effective_duration ?? 60));
            }

            return $this->effective_hold_duration;
        }

        $scheduled = $lines->filter(fn (AppointmentService $s) => $s->start_time !== null);
        if ($scheduled->isNotEmpty()) {
            $minStart = $scheduled->min(fn (AppointmentService $s) => $s->start_time->getTimestamp());
            $maxEnd = $scheduled->max(fn (AppointmentService $s) => ($s->end_time ?? $s->start_time->copy()->addMinutes((int) ($s->duration ?: 30)))->getTimestamp());

            return max(5, (int) round(($maxEnd - $minStart) / 60));
        }

        $timeline = \App\Support\BookingConflictValidator::computeSliceTimeline(
            now()->startOfHour(),
            $lines->map(fn (AppointmentService $s, int $idx) => [
                'duration' => (int) ($s->duration ?: 30),
                'staff_id' => $s->staff_id,
                'sort_order' => (int) ($s->sort_order ?? $idx),
                'is_parallel' => (bool) $s->is_parallel,
            ])->all()
        );

        return max(5, (int) ($timeline['span_minutes'] ?? 30));
    }

    public function getIsComboTbdAttribute(): bool
    {
        if (!$this->display_combo_name) {
            return false;
        }

        if ($this->relationLoaded('appointmentServices')) {
            return $this->appointmentServices->where('is_pending', false)->isEmpty();
        }

        $svcName = strtolower(trim((string) ($this->attributes['service_name'] ?? '')));

        return in_array($svcName, ['', 'decide in salon', 'choose in salon', 'services tbd in salon'], true);
    }

    public function upsells()
    {
        return $this->hasMany(AppointmentUpsell::class)->orderBy('created_at');
    }

    /**
     * Permanent audit trail of every manual price override/discount applied
     * to this booking's services.
     */
    public function priceOverrides()
    {
        return $this->hasMany(AppointmentPriceOverride::class)->orderByDesc('created_at');
    }

    public function freebiePackage()
    {
        return $this->belongsTo(ServiceFreebiePackage::class, 'freebie_package_id');
    }

    public function anchoredFreebieCredits()
    {
        return $this->hasMany(ClientFreebieCredit::class, 'anchor_appointment_id')->orderBy('slot_number');
    }

    public function redeemedFreebieCredits()
    {
        return $this->hasMany(ClientFreebieCredit::class, 'redemption_appointment_id')->orderBy('slot_number');
    }

    /**
     * Recompute the appointment's summary fields (service_name, combo_name,
     * price, appointment_datetime, staff_id) from its active line items and
     * attached Combo packages. Call this any time appointment_services or
     * combo package selections are added, edited, or removed so the calendar
     * block and Bookings table stay 100% accurate.
     */
    public function syncFromServices(): void
    {
        $allServices = $this->appointmentServices()->get();
        $activeServices = $allServices->filter(fn (AppointmentService $s) => !$s->is_pending)->values();
        $attachedPackages = ClientPackage::where('appointment_id', $this->id)->orderBy('id')->get();

        $comboNames = $attachedPackages->pluck('combo_name')
            ->merge($allServices->pluck('combo_name'))
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        if ($comboNames->isNotEmpty()) {
            $this->combo_name = $comboNames->implode(', ');
            $this->combo_id = $attachedPackages->first()?->combo_id
                ?? $allServices->whereNotNull('combo_id')->first()?->combo_id
                ?? $this->combo_id;
        } elseif ($attachedPackages->isEmpty() && $allServices->whereNotNull('combo_name')->isEmpty()) {
            // Clear stale combo badge if all combo links were removed
            if ($allServices->isNotEmpty()) {
                $this->combo_id = null;
                $this->combo_name = null;
            }
        }

        if ($activeServices->isNotEmpty()) {
            $first = $activeServices->first();
            $scheduledSlices = $activeServices
                ->filter(fn (AppointmentService $s) => $s->start_time !== null)
                ->sortBy(fn (AppointmentService $s) => sprintf('%015d_%05d_%010d', $s->start_time->getTimestamp(), (int) $s->sort_order, (int) $s->id))
                ->values();
            $earliestSlice = $scheduledSlices->first() ?? $first;

            $this->service_name = $activeServices->pluck('name')->implode(', ');
            $this->decide_in_salon = false;
            $this->hold_duration = null;
            if ($earliestSlice && $earliestSlice->start_time) {
                $this->appointment_datetime = $earliestSlice->start_time;
                $this->booking_date = $earliestSlice->start_time->toDateString();
                $this->start_time = $earliestSlice->start_time->format('H:i:s');
            }
            $this->staff_id = $first->staff_id ?? $earliestSlice?->staff_id ?? $this->staff_id;
        } elseif ($attachedPackages->isNotEmpty() || !empty($this->combo_name)) {
            $this->service_name = 'Choose in Salon';
            $this->decide_in_salon = false;
            $this->hold_duration = null;
        } elseif ($allServices->isEmpty()) {
            if ($this->decide_in_salon) {
                $this->service_name = 'Decide in Salon';
                $this->hold_duration = $this->effective_hold_duration;
                if (!$this->sale()->exists()) {
                    $this->price = 0;
                    $this->original_price = 0;
                    $this->discount_amount = 0;
                }
                $this->save();
            }

            return;
        }

        if (!$this->sale()->exists()) {
            $unpaidPackagesTotal = (float) $attachedPackages->whereNull('sale_id')->sum('price_paid');
            $activeLinesTotal = (float) $activeServices->sum(fn (AppointmentService $s) => $s->final_price);
            $activeLinesOriginal = (float) $activeServices->sum(
                fn (AppointmentService $s) => $s->isPackageRedemptionLine() ? 0.0 : (float) ($s->original_price ?? $s->price)
            );
            $activeLinesDiscount = (float) $activeServices->sum(
                fn (AppointmentService $s) => $s->isPackageRedemptionLine() ? 0.0 : (float) $s->discount_amount
            );

            $this->price = round($activeLinesTotal + $unpaidPackagesTotal, 2);
            $this->original_price = round($activeLinesOriginal + $unpaidPackagesTotal, 2);
            $this->discount_amount = round($activeLinesDiscount, 2);
        }

        $this->save();
    }

    // public function service()
    // {
    //     return $this->belongsTo(Service::class);
    // }
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Scope to active scheduled Enhanced Calendar bookings eligible for Chat Agent quota credit:
     * - Explicit `booking_agent_id` belonging to a Chat Agent (`role = 'agent'`) ONLY
     *   (no `created_by` fallback, never `Salon Staff`).
     * - Scheduled onto the calendar (`start_time IS NOT NULL` and `staff_id IS NOT NULL`),
     *   excluding unscheduled sidebar queue placeholders until placed on the grid.
     * - Excludes cancelled and soft-deleted bookings consistently.
     */
    public function scopeScheduledForAgentQuota($query, iterable $agentUserIds)
    {
        $ids = collect($agentUserIds)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        if (empty($ids)) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereIn('booking_agent_id', $ids)
            ->where(function ($q) {
                $q->whereNull('booking_agent_type')
                    ->orWhere('booking_agent_type', '!=', self::BOOKING_AGENT_SALON_STAFF);
            })
            ->whereNotNull('start_time')
            ->whereNotNull('staff_id')
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(status) != ?', ['cancelled']);
            });

        if (Schema::hasColumn($this->getTable(), 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    /**
     * Deduplicate Chat Agent quota appointments by `(booking_agent_id, customer_identity, appointment_date)`
     * ordered chronologically (`created_at ASC, id ASC`) so:
     * - Splitting 1 client into 3 separate same-day service rows counts as +1, not +3.
     * - Cancelling and re-booking the same client on the same appointment date counts as +1, not +2.
     * - Booking the same client for two genuine visits on different appointment dates counts as +2.
     *
     * @param  Collection<int, self>  $appointments
     * @return Collection<int, self>
     */
    public static function deduplicateForAgentQuota(Collection $appointments): Collection
    {
        if ($appointments->isEmpty()) {
            return collect();
        }

        $phoneToCustomerId = [];
        foreach ($appointments as $appt) {
            if (!empty($appt->customer_id) && !empty($appt->phone)) {
                $norm = Customer::normalizePhone((string) $appt->phone);
                if ($norm !== '') {
                    $phoneToCustomerId[$norm] = (int) $appt->customer_id;
                }
            }
        }

        return $appointments
            ->sortBy([
                fn (self $a, self $b) => ($a->created_at?->getTimestamp() ?? 0) <=> ($b->created_at?->getTimestamp() ?? 0),
                fn (self $a, self $b) => ((int) $a->id) <=> ((int) $b->id),
            ])
            ->unique(function (self $appt) use ($phoneToCustomerId): string {
                $agentId = (int) $appt->booking_agent_id;
                $normPhone = !empty($appt->phone) ? Customer::normalizePhone((string) $appt->phone) : '';

                if (!empty($appt->customer_id)) {
                    $customerKey = 'cid:' . (int) $appt->customer_id;
                } elseif ($normPhone !== '' && isset($phoneToCustomerId[$normPhone])) {
                    $customerKey = 'cid:' . $phoneToCustomerId[$normPhone];
                } elseif ($normPhone !== '') {
                    $customerKey = 'ph:' . $normPhone;
                } elseif (trim((string) $appt->customer_name) !== '') {
                    $customerKey = 'nm:' . mb_strtolower(trim((string) $appt->customer_name));
                } else {
                    $customerKey = 'id:' . (int) $appt->id;
                }

                if (!empty($appt->booking_date)) {
                    $apptDate = Carbon::parse($appt->booking_date)->toDateString();
                } elseif (!empty($appt->appointment_datetime)) {
                    $apptDate = Carbon::parse($appt->appointment_datetime)->timezone('Asia/Qatar')->toDateString();
                } elseif ($appt->created_at) {
                    $apptDate = $appt->created_at->copy()->timezone('Asia/Qatar')->toDateString();
                } else {
                    $apptDate = now('Asia/Qatar')->toDateString();
                }

                return "{$agentId}|{$customerKey}|{$apptDate}";
            })
            ->values();
    }
}
