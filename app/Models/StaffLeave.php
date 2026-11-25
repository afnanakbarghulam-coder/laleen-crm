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
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'integer',
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
