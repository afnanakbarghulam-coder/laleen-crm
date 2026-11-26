<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StaffAttendance extends Model
{
    use HasFactory;

    public const PUNCTUALITY_TARGET_PCT = 90.0;

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
     * Resolve late minutes using the exact Staff Management check-in rule (`Asia/Qatar`):
     * `checked_in_at <= scheduled_start_time` => 0 min (On Time);
     * `checked_in_at > scheduled_start_time` => `max(1, floor(diffSeconds / 60))` min late.
     */
    public static function resolveLateMinutes(self $record): int
    {
        if ($record->late_minutes !== null && (int) $record->late_minutes > 0) {
            return (int) $record->late_minutes;
        }

        if ($record->checked_in_at && filled($record->scheduled_start_time) && $record->attendance_date) {
            $dateStr = $record->attendance_date->toDateString();
            $scheduledStart = Carbon::parse($dateStr . ' ' . trim((string) $record->scheduled_start_time), 'Asia/Qatar');
            $checkedIn = $record->checked_in_at->copy()->timezone('Asia/Qatar');

            if ($checkedIn->gt($scheduledStart)) {
                $diffSeconds = (int) $scheduledStart->diffInSeconds($checkedIn, false);

                return max(1, (int) floor($diffSeconds / 60));
            }
        }

        return max(0, (int) ($record->late_minutes ?? 0));
    }

    /**
     * Single-source-of-truth check-in punctuality summary matching Staff Management attendance logs.
     * Counts attended check-in rows (`present` / `late`) and ignores `absent` / `off` rows.
     *
     * @param  Collection<int, self>  $records
     * @return array{
     *     check_ins_count: int,
     *     on_time_count: int,
     *     late_count: int,
     *     total_late_minutes: int,
     *     avg_late_minutes: int,
     *     punctuality_pct: ?float,
     *     has_check_ins: bool,
     *     is_passing: bool,
     *     target_pct: float
     * }
     */
    public static function summarizePunctuality(Collection $records): array
    {
        $onTimeCount = 0;
        $lateCount = 0;
        $totalLateMinutes = 0;

        foreach ($records as $record) {
            $status = strtolower(trim((string) ($record->status ?? '')));
            if (!in_array($status, [self::STATUS_PRESENT, self::STATUS_LATE], true)) {
                continue;
            }

            $lateMinutes = self::resolveLateMinutes($record);
            if ($status === self::STATUS_LATE || $lateMinutes > 0) {
                $lateCount++;
                $totalLateMinutes += $lateMinutes;
            } else {
                $onTimeCount++;
            }
        }

        $checkInsCount = $onTimeCount + $lateCount;
        $hasCheckIns = $checkInsCount > 0;
        $punctualityPct = $hasCheckIns ? round(($onTimeCount / $checkInsCount) * 100, 1) : null;
        $avgLateMinutes = $lateCount > 0 ? (int) round($totalLateMinutes / $lateCount) : 0;

        return [
            'check_ins_count' => $checkInsCount,
            'on_time_count' => $onTimeCount,
            'late_count' => $lateCount,
            'total_late_minutes' => $totalLateMinutes,
            'avg_late_minutes' => $avgLateMinutes,
            'punctuality_pct' => $punctualityPct,
            'has_check_ins' => $hasCheckIns,
            'is_passing' => $hasCheckIns && $punctualityPct >= self::PUNCTUALITY_TARGET_PCT,
            'target_pct' => self::PUNCTUALITY_TARGET_PCT,
        ];
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
