<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    use HasFactory;

    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LATE = 'late';
    public const STATUS_OFF = 'off';

    public const STATUSES = [
        self::STATUS_PRESENT => 'Present',
        self::STATUS_ABSENT => 'Absent',
        self::STATUS_LATE => 'Late',
        self::STATUS_OFF => 'Off',
    ];

    /** Active statuses allowed in Attendance Log controls and manual writes (`Off` removed). */
    public const MANUAL_STATUSES = [
        self::STATUS_PRESENT => 'Present',
        self::STATUS_ABSENT => 'Absent',
        self::STATUS_LATE => 'Late',
    ];

    protected $table = 'staff_attendances';

    protected $fillable = [
        'staff_id',
        'attendance_date',
        'status',
        'scheduled_start_time',
        'scheduled_end_time',
        'checked_in_at',
        'checked_out_at',
        'late_minutes',
        'checked_in_by',
        'is_locked',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'late_minutes' => 'integer',
        'is_locked' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Format a positive late-minute delta compactly (`+7 min`, `+1h 12m`, `+2h`).
     */
    public static function formatLateDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 min';
        }
        if ($minutes < 60) {
            return "+{$minutes} min";
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;

        return $rem > 0 ? "+{$hours}h {$rem}m" : "+{$hours}h";
    }

    /**
     * Format cumulative late minutes without leading `+` (`17 min`, `1h 12m`).
     */
    public static function formatTotalLateMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 min';
        }
        if ($minutes < 60) {
            return "{$minutes} min";
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;

        return $rem > 0 ? "{$hours}h {$rem}m" : "{$hours}h";
    }

    public function checkedInQatarLabel(): ?string
    {
        return $this->checked_in_at
            ? $this->checked_in_at->copy()->timezone('Asia/Qatar')->format('g:i A')
            : null;
    }

    public function checkedOutQatarLabel(): ?string
    {
        return $this->checked_out_at
            ? $this->checked_out_at->copy()->timezone('Asia/Qatar')->format('g:i A')
            : null;
    }

    public function scheduledShiftLabel(): ?string
    {
        if (empty($this->scheduled_start_time) || empty($this->scheduled_end_time)) {
            return null;
        }

        return Staff::formatClockRange((string) $this->scheduled_start_time, (string) $this->scheduled_end_time);
    }

    /**
     * Unified status pill payload for Attendance Log & `/my-profile`.
     *
     * @return array{label: string, tone: string}
     */
    public function statusBadgePayload(): array
    {
        $lateMins = max(0, (int) ($this->late_minutes ?? 0));

        return match ($this->status) {
            self::STATUS_PRESENT => [
                'label' => $this->checked_in_at ? 'On Time' : 'Present',
                'tone' => 'emerald',
            ],
            self::STATUS_LATE => [
                'label' => $lateMins > 0
                    ? ('Late (' . self::formatLateDuration($lateMins) . ')')
                    : 'Late',
                'tone' => $lateMins >= 30 ? 'danger' : 'amber',
            ],
            self::STATUS_ABSENT => [
                'label' => 'Absent',
                'tone' => 'danger',
            ],
            self::STATUS_OFF => [
                'label' => 'Off',
                'tone' => 'muted',
            ],
            default => [
                'label' => $this->statusLabel(),
                'tone' => 'muted',
            ],
        };
    }
}
