<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class Staff extends Model
{
    use HasFactory;

    public const DEFAULT_CONTRACT_PIN = '2456';

    public const PHYSICAL_BRANCHES = ['old_airport', 'wakrah', 'both'];

    public const REMOTE_ROLES = [
        'agent',
        'sales_coordinator',
        'ops_lead',
        'marketing_specialist',
        'social_media_editor',
    ];

    public const BRANCH_ROLES = [
        'salon_manager',
        'salon_staff',
    ];

    protected $table = 'staff';

    protected $hidden = [
        'contract_pin',
    ];

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'birthday',
        'address_line1',
        'city',
        'country',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'branch',
        'is_remote',
        'annual_leave_days',
        'sick_leave_days',
        'skills',
        'working_hours',
        'weekly_off',
        'availability_status',
        'off_from',
        'off_to',
        'profile_picture',
        'bookable',
        'start_date',
        'end_date',
        'employment_type',
        'staff_member_id',
        'internal_notes',
        'hourly_wage',
        'commission_rate',
        'base_salary',
        'user_id',
        'contract_pin',
    ];

    protected $casts = [
        'skills' => 'array',
        'working_hours' => 'array',
        'weekly_off' => 'array',
        'is_remote' => 'boolean',
        'annual_leave_days' => 'integer',
        'sick_leave_days' => 'integer',
        'bookable' => 'boolean',
        'birthday' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public static function isRemoteRole(?string $role): bool
    {
        return in_array(strtolower(trim((string) $role)), self::REMOTE_ROLES, true);
    }

    public function isLeaveEligible(): bool
    {
        if ($this->user_id) {
            $linkedUser = $this->relationLoaded('user') ? $this->user : $this->user()->first();
            if ($linkedUser) {
                return $linkedUser->isLeaveEligible();
            }
        }

        return !$this->isRemote();
    }

    public function isRemote(): bool
    {
        if ((bool) ($this->attributes['is_remote'] ?? false)) {
            return true;
        }

        $rawBranch = strtolower(trim((string) ($this->attributes['branch'] ?? '')));

        return $rawBranch === '' || $rawBranch === 'remote';
    }

    public function getLocationLabelAttribute(): string
    {
        if ($this->isRemote()) {
            return 'Remote';
        }

        return match (strtolower(trim((string) $this->branch))) {
            'old_airport' => 'Old Airport',
            'wakrah', 'al_wakrah' => 'Al Wakrah',
            'both' => 'Old Airport & Al Wakrah',
            default => 'Remote',
        };
    }

    public function getBranchKeyAttribute(): string
    {
        if ($this->isRemote()) {
            return 'remote';
        }

        $raw = strtolower(trim((string) $this->branch));

        return match ($raw) {
            'wakrah', 'al_wakrah' => 'wakrah',
            'both' => 'both',
            'old_airport' => 'old_airport',
            default => 'remote',
        };
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'staff_id');
    }

    public function appointmentServices()
    {
        return $this->hasMany(AppointmentService::class, 'staff_id');
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'staff_id');
    }

    public function staffBlocks()
    {
        return $this->hasMany(StaffBlock::class, 'staff_id');
    }

    public function upsells()
    {
        return $this->hasMany(AppointmentUpsell::class);
    }

    /**
     * Bookable in-salon staff who are currently employed (no end date, or an end date
     * that hasn't passed yet) — excludes Remote staff and Chat Agents so they never
     * leak into Enhanced Calendar columns, specialist pickers, or salon upsells.
     */
    public function scopeActive($query)
    {
        $today = now('Asia/Qatar')->toDateString();

        return $query->where('bookable', true)
            ->where(function ($q) {
                $q->where('is_remote', false)->orWhereNull('is_remote');
            })
            ->whereIn('branch', self::PHYSICAL_BRANCHES)
            ->whereDoesntHave('user', function ($uq) {
                $uq->whereIn(DB::raw("LOWER(TRIM(COALESCE(role, '')))"), self::REMOTE_ROLES);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            });
    }

    /**
     * Salon staff eligible for weekly Monday Branch Manager evaluations as of $asOfDate.
     * Includes absent / sick / on-leave stylists, while excluding Remote staff, non-stylist
     * CRM roles (chat agents, ops_lead, sales_coordinator, salon_manager, admins, owners, super_admins, managers)
     * and Branch Managers (Ahana, Rhea).
     */
    public function scopeSalonEvaluatable($query, ?string $branch = null, $asOfDate = null)
    {
        $dateStr = $asOfDate
            ? Carbon::parse($asOfDate)->toDateString()
            : now('Asia/Qatar')->toDateString();

        return $query
            ->where('bookable', true)
            ->where(function ($q) {
                $q->where('is_remote', false)->orWhereNull('is_remote');
            })
            ->whereIn('branch', self::PHYSICAL_BRANCHES)
            ->when($branch, fn ($q) => $q->whereIn('branch', [$branch, 'both']))
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', $dateStr);
            })
            ->where(function ($q) use ($dateStr) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $dateStr);
            })
            ->whereDoesntHave('user', function ($uq) {
                $uq->whereIn(DB::raw("LOWER(TRIM(COALESCE(role, '')))"), [
                    'agent',
                    'sales_coordinator',
                    'ops_lead',
                    'marketing_specialist',
                    'social_media_editor',
                    'salon_manager',
                    'admin',
                    'owner',
                    'super_admin',
                    'manager',
                ])
                ->orWhereRaw("LOWER(TRIM(COALESCE(name, ''))) = 'ahana'")
                ->orWhereRaw("LOWER(TRIM(COALESCE(name, ''))) LIKE 'ahana %'")
                ->orWhereRaw("LOWER(TRIM(COALESCE(name, ''))) LIKE '% ahana'")
                ->orWhereRaw("LOWER(TRIM(COALESCE(name, ''))) LIKE '% ahana %'")
                ->orWhereRaw("LOWER(TRIM(COALESCE(name, ''))) = 'rhea'")
                ->orWhereRaw("LOWER(TRIM(COALESCE(name, ''))) LIKE 'rhea %'")
                ->orWhereRaw("LOWER(TRIM(COALESCE(name, ''))) LIKE '% rhea'")
                ->orWhereRaw("LOWER(TRIM(COALESCE(name, ''))) LIKE '% rhea %'");
            })
            ->whereRaw("LOWER(TRIM(COALESCE(name, ''))) != 'ahana'")
            ->whereRaw("LOWER(TRIM(COALESCE(name, ''))) NOT LIKE 'ahana %'")
            ->whereRaw("LOWER(TRIM(COALESCE(name, ''))) NOT LIKE '% ahana'")
            ->whereRaw("LOWER(TRIM(COALESCE(name, ''))) NOT LIKE '% ahana %'")
            ->whereRaw("LOWER(TRIM(COALESCE(name, ''))) != 'rhea'")
            ->whereRaw("LOWER(TRIM(COALESCE(name, ''))) NOT LIKE 'rhea %'")
            ->whereRaw("LOWER(TRIM(COALESCE(name, ''))) NOT LIKE '% rhea'")
            ->whereRaw("LOWER(TRIM(COALESCE(name, ''))) NOT LIKE '% rhea %'");
    }

    public function blocks()
    {
        return $this->hasMany(StaffBlock::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_staff');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shiftPatterns()
    {
        return $this->hasMany(ShiftPattern::class);
    }

    public function timeOffs()
    {
        return $this->hasMany(StaffTimeOff::class);
    }

    public function activePatternFor($date): ?ShiftPattern
    {
        return $this->shiftPatterns
            ->filter(fn ($pattern) => $pattern->coversDate($date))
            ->sortByDesc('start_date')
            ->first();
    }

    public function overtimeEntries()
    {
        return $this->hasMany(StaffOvertimeEntry::class);
    }

    public function complaints()
    {
        return $this->belongsToMany(StaffComplaint::class, 'complaint_staff', 'staff_id', 'staff_complaint_id');
    }

    public function deductions()
    {
        return $this->hasMany(StaffDeduction::class);
    }

    public function notices()
    {
        return $this->hasMany(StaffNotice::class);
    }

    public function contracts()
    {
        return $this->hasMany(StaffContract::class, 'staff_id');
    }

    public function documents()
    {
        return $this->hasMany(StaffDocument::class, 'staff_id');
    }

    public function leaves()
    {
        return $this->hasMany(StaffLeave::class, 'staff_id');
    }

    public function attendances()
    {
        return $this->hasMany(StaffAttendance::class, 'staff_id');
    }

    public function evaluations()
    {
        return $this->hasMany(KpiStaffEvaluation::class, 'staff_id');
    }

    public function setContractPin(string $pin): void
    {
        $this->forceFill([
            'contract_pin' => Hash::make($pin),
        ])->save();
    }

    public function hasCustomContractPin(): bool
    {
        return !empty($this->contract_pin);
    }

    public function verifyContractPin(string $inputPin): bool
    {
        $cleanInput = trim($inputPin);
        if (!preg_match('/^\d{4}$/', $cleanInput)) {
            return false;
        }

        $stored = (string) ($this->contract_pin ?? '');
        if ($stored === '') {
            return hash_equals(self::DEFAULT_CONTRACT_PIN, $cleanInput);
        }

        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon2')) {
            return Hash::check($cleanInput, $stored);
        }

        return hash_equals($stored, $cleanInput);
    }
}
