<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class StockWeek extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVISION_REQUESTED = 'revision_requested';
    public const STATUS_APPROVED = 'approved';

    public const STATUSES = [
        self::STATUS_OPEN => 'Open',
        self::STATUS_SUBMITTED => 'Pending Approval',
        self::STATUS_REVISION_REQUESTED => 'Revision Requested',
        self::STATUS_APPROVED => 'Approved',
    ];

    public const BRANCHES = [
        'old_airport' => 'Old Airport',
        'al_wakrah' => 'Al Wakrah',
    ];

    protected $table = 'stock_weeks';

    protected $fillable = [
        'branch',
        'week_start_date',
        'week_end_date',
        'status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'admin_note',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public static function normalizeBranch(?string $raw): ?string
    {
        $slug = strtolower(trim((string) $raw));

        return match ($slug) {
            'old_airport', 'airport', 'old airport' => 'old_airport',
            'al_wakrah', 'wakrah', 'al wakrah', 'alwakrah' => 'al_wakrah',
            default => null,
        };
    }

    public static function branchLabel(string $branch): string
    {
        $norm = self::normalizeBranch($branch) ?? $branch;

        return self::BRANCHES[$norm] ?? ucwords(str_replace('_', ' ', $branch));
    }

    /**
     * Resolve the canonical Saturday -> Friday weekly bounds in Asia/Qatar containing `$date`.
     *
     * @return array{start: Carbon, end: Carbon, start_str: string, end_str: string, label: string}
     */
    public static function weekBoundsFor(Carbon|string|null $date = null): array
    {
        $anchor = $date instanceof Carbon
            ? $date->copy()->timezone('Asia/Qatar')
            : ($date ? Carbon::parse($date, 'Asia/Qatar') : Carbon::now('Asia/Qatar'));

        $start = $anchor->copy()->startOfWeek(Carbon::SATURDAY)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'start_str' => $start->toDateString(),
            'end_str' => $end->toDateString(),
            'label' => $start->format('D d M') . ' – ' . $end->format('D d M Y'),
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StockWeekEntry::class, 'stock_week_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'stock_week_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditableByManager(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_REVISION_REQUESTED], true);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function rangeLabel(): string
    {
        return $this->week_start_date->format('D d M') . ' – ' . $this->week_end_date->format('D d M Y');
    }

    /**
     * @return array{label: string, tone: string}
     */
    public function statusBadge(): array
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED => ['label' => 'Pending Approval', 'tone' => 'amber'],
            self::STATUS_REVISION_REQUESTED => ['label' => 'Revision Requested', 'tone' => 'danger'],
            self::STATUS_APPROVED => ['label' => 'Approved', 'tone' => 'emerald'],
            default => ['label' => 'Open', 'tone' => 'champagne'],
        };
    }
}
