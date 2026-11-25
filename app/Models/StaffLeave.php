<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class StaffLeave extends Model
{
    use HasFactory;

    public const TYPE_ANNUAL = 'annual';
    public const TYPE_SICK = 'sick';

    public const SICK_SUBMISSION_WINDOW_DAYS = 3;

    public const SICK_POLICY_NOTICE = 'Sick leave must be submitted within 3 days with a valid Medical Certificate and approved by management. Missing medical certificate, late submission (>3 days), or unapproved sick leave results in a salary deduction.';

    public const TYPES = [
        self::TYPE_ANNUAL => 'Annual Leave',
        self::TYPE_SICK => 'Sick Leave',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $table = 'staff_leaves';

    protected $fillable = [
        'staff_id',
        'leave_type',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'medical_certificate_path',
        'submitted_within_window',
        'deduction_applied',
        'deduction_id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'integer',
        'submitted_within_window' => 'boolean',
        'deduction_applied' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function deduction(): BelongsTo
    {
        return $this->belongsTo(StaffDeduction::class, 'deduction_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeActiveRequest($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    public function scopeCoveringDate($query, Carbon|string $date)
    {
        $dateStr = $date instanceof Carbon
            ? $date->copy()->timezone('Asia/Qatar')->toDateString()
            : Carbon::parse($date, 'Asia/Qatar')->toDateString();

        return $query->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr);
    }

    public function coversDate(Carbon|string $date): bool
    {
        $target = $date instanceof Carbon
            ? $date->copy()->timezone('Asia/Qatar')->startOfDay()
            : Carbon::parse($date, 'Asia/Qatar')->startOfDay();

        return $this->start_date->copy()->startOfDay()->lte($target)
            && $this->end_date->copy()->startOfDay()->gte($target);
    }

    /**
     * Sick Leave 3-day submission rule (Asia/Qatar):
     * Returns false when submission date is more than 3 calendar days after start_date (`now > start_date + 3 days`).
     */
    public static function isSubmittedWithinWindow(Carbon|string $startDate, ?Carbon $submittedAt = null): bool
    {
        $nowDay = ($submittedAt ? $submittedAt->copy()->timezone('Asia/Qatar') : Carbon::now('Asia/Qatar'))->startOfDay();
        $startDay = $startDate instanceof Carbon
            ? $startDate->copy()->timezone('Asia/Qatar')->startOfDay()
            : Carbon::parse($startDate, 'Asia/Qatar')->startOfDay();

        $daysDelta = (int) $nowDay->diffInDays($startDay, false);

        return $daysDelta >= -self::SICK_SUBMISSION_WINDOW_DAYS;
    }

    public function isSick(): bool
    {
        return $this->leave_type === self::TYPE_SICK;
    }

    public function hasMedicalCertificate(): bool
    {
        return !empty($this->medical_certificate_path);
    }

    public function isComplianceRisk(): bool
    {
        if (!$this->isSick()) {
            return false;
        }

        return !$this->hasMedicalCertificate() || !($this->submitted_within_window ?? true);
    }

    public function canUploadMissingCertificate(?Carbon $nowQatar = null): bool
    {
        if (!$this->isSick() || $this->status !== self::STATUS_PENDING || $this->hasMedicalCertificate()) {
            return false;
        }

        return self::isSubmittedWithinWindow($this->start_date, $nowQatar);
    }

    /**
     * Structured compliance & deduction badges for Sick Leave rows across Staff Portal and Admin Queue.
     *
     * @return list<array{key: string, label: string, tone: string}>
     */
    public function complianceBadges(): array
    {
        if (!$this->isSick()) {
            return [];
        }

        $badges = [];

        if ($this->hasMedicalCertificate()) {
            $badges[] = [
                'key' => 'med_cert_attached',
                'label' => 'Med Cert Attached',
                'tone' => 'emerald',
            ];
        } else {
            $badges[] = [
                'key' => 'missing_med_cert',
                'label' => 'Missing Med Cert · Deduction Risk',
                'tone' => 'amber',
            ];
        }

        if (($this->submitted_within_window ?? true) === false) {
            $badges[] = [
                'key' => 'late_submission',
                'label' => 'Late (>3 Days) · Deduction Risk',
                'tone' => 'danger',
            ];
        }

        if ((bool) ($this->deduction_applied ?? false)) {
            $amount = $this->relationLoaded('deduction')
                ? (float) ($this->deduction?->amount ?? 0)
                : (float) ($this->deduction()->value('amount') ?? 0);
            $formatted = $amount > 0
                ? (number_format($amount, abs($amount - round($amount)) < 0.005 ? 0 : 2) . ' QAR')
                : 'Logged';

            $badges[] = [
                'key' => 'deducted',
                'label' => "Deducted ({$formatted})",
                'tone' => 'danger',
            ];
        }

        return $badges;
    }

    public function defaultDeductionReason(): string
    {
        $dateLabel = $this->start_date->equalTo($this->end_date)
            ? $this->start_date->format('d M Y')
            : ($this->start_date->format('d M') . ' – ' . $this->end_date->format('d M Y'));

        $causes = [];
        if (!$this->hasMedicalCertificate()) {
            $causes[] = 'missing medical certificate';
        }
        if (($this->submitted_within_window ?? true) === false) {
            $causes[] = 'submitted after 3-day limit';
        }

        if (!empty($causes)) {
            return "Unapproved sick leave ({$dateLabel}) — " . implode(' & ', $causes);
        }

        return "Unapproved sick leave ({$dateLabel})";
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->leave_type] ?? ucfirst((string) $this->leave_type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public static function inclusiveDays(Carbon|string $start, Carbon|string $end): int
    {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();

        return max(1, (int) $s->diffInDays($e) + 1);
    }

    /**
     * Count inclusive days of this leave falling inside the specified calendar year (Asia/Qatar).
     */
    public function daysWithinYear(int $year): int
    {
        $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, 'Asia/Qatar')->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31, 0, 0, 0, 'Asia/Qatar')->startOfDay();

        $start = $this->start_date->copy()->startOfDay();
        $end = $this->end_date->copy()->startOfDay();

        if ($end->lt($yearStart) || $start->gt($yearEnd)) {
            return 0;
        }

        $clampedStart = $start->lt($yearStart) ? $yearStart : $start;
        $clampedEnd = $end->gt($yearEnd) ? $yearEnd : $end;

        return max(0, (int) $clampedStart->diffInDays($clampedEnd) + 1);
    }
}
