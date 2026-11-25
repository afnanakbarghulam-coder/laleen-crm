<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DailyBranchClose extends Model
{
    use HasFactory;

    public const STATUS_BALANCED = 'balanced';
    public const STATUS_SHORTAGE = 'shortage';
    public const STATUS_OVERAGE = 'overage';

    public const BRANCHES = [
        'old_airport' => 'Old Airport',
        'al_wakrah' => 'Al Wakrah',
    ];

    protected $table = 'daily_branch_closes';

    protected $fillable = [
        'branch',
        'close_date',
        'opening_float_qar',
        'entered_cash_qar',
        'entered_card_qar',
        'entered_transfer_qar',
        'expected_cash_qar',
        'expected_card_qar',
        'expected_transfer_qar',
        'cash_variance_qar',
        'card_variance_qar',
        'transfer_variance_qar',
        'total_variance_qar',
        'status',
        'z_report_path',
        'variance_note',
        'submitted_by',
        'submitted_at',
        'verified_by',
        'verified_at',
        'is_locked',
    ];

    protected $casts = [
        'close_date' => 'date',
        'opening_float_qar' => 'float',
        'entered_cash_qar' => 'float',
        'entered_card_qar' => 'float',
        'entered_transfer_qar' => 'float',
        'expected_cash_qar' => 'float',
        'expected_card_qar' => 'float',
        'expected_transfer_qar' => 'float',
        'cash_variance_qar' => 'float',
        'card_variance_qar' => 'float',
        'transfer_variance_qar' => 'float',
        'total_variance_qar' => 'float',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_locked' => 'boolean',
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

    /**
     * Exact branch strings stored on `sales.branch` for a canonical EOD branch.
     *
     * @return list<string>
     */
    public static function salesBranchMatchValues(string $canonicalBranch): array
    {
        return $canonicalBranch === 'al_wakrah'
            ? ['wakrah', 'al_wakrah', 'Al Wakrah', 'Wakrah']
            : ['old_airport', 'Old Airport'];
    }

    public static function branchLabel(string $branch): string
    {
        $norm = self::normalizeBranch($branch) ?? $branch;

        return self::BRANCHES[$norm] ?? ucwords(str_replace('_', ' ', $branch));
    }

    /**
     * Server-side Sales Ledger truth for a branch on a given Asia/Qatar date.
     *
     * @return array{cash: float, card: float, transfer: float, total: float, sales_count: int}
     */
    public static function expectedTotalsFor(string $branch, string $dateStr): array
    {
        $canonical = self::normalizeBranch($branch) ?? 'old_airport';
        $dayStart = Carbon::parse($dateStr, 'Asia/Qatar')->startOfDay();
        $dayEnd = Carbon::parse($dateStr, 'Asia/Qatar')->endOfDay();

        $saleIds = Sale::query()
            ->whereIn('branch', self::salesBranchMatchValues($canonical))
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->pluck('id');

        if ($saleIds->isEmpty()) {
            return [
                'cash' => 0.0,
                'card' => 0.0,
                'transfer' => 0.0,
                'total' => 0.0,
                'sales_count' => 0,
            ];
        }

        $grouped = SalePayment::query()
            ->whereIn('sale_id', $saleIds)
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $cash = round((float) ($grouped['cash'] ?? 0), 2);
        $card = round((float) ($grouped['card'] ?? 0), 2);
        $transfer = round((float) (($grouped['online_transfer'] ?? 0) + ($grouped['transfer'] ?? 0) + ($grouped['payment_link'] ?? 0)), 2);

        return [
            'cash' => $cash,
            'card' => $card,
            'transfer' => $transfer,
            'total' => round($cash + $card + $transfer, 2),
            'sales_count' => $saleIds->count(),
        ];
    }

    public function netCashEntered(): float
    {
        return round((float) $this->entered_cash_qar - (float) $this->opening_float_qar, 2);
    }

    public function totalEntered(): float
    {
        return round($this->netCashEntered() + (float) $this->entered_card_qar + (float) $this->entered_transfer_qar, 2);
    }

    public function totalExpected(): float
    {
        return round((float) $this->expected_cash_qar + (float) $this->expected_card_qar + (float) $this->expected_transfer_qar, 2);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * @return array{label: string, tone: string}
     */
    public function statusBadge(): array
    {
        $var = round((float) $this->total_variance_qar, 2);

        return match ($this->status) {
            self::STATUS_SHORTAGE => [
                'label' => 'Shortage ' . number_format($var, 2) . ' QAR',
                'tone' => 'danger',
            ],
            self::STATUS_OVERAGE => [
                'label' => 'Overage +' . number_format(abs($var), 2) . ' QAR',
                'tone' => 'amber',
            ],
            default => [
                'label' => 'Balanced',
                'tone' => 'emerald',
            ],
        };
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
