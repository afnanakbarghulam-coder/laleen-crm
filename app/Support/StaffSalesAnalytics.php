<?php

namespace App\Support;

use App\Models\AppointmentUpsell;
use App\Models\SaleItem;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Live, non-persisted Staff Sales Performance computation — everything the
 * "Sales & Upsells" and "Analytics" tabs show is calculated fresh from
 * appointment_upsells for whatever date range/target is currently filtered.
 * There is no saved-report concept anymore; nothing here is ever written
 * to the database.
 */
class StaffSalesAnalytics
{
    const DEFAULT_MONTHLY_TARGET = 1700;
    const DAYS_IN_MONTH = 31;

    const BRANCHES = [
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
    ];

    public function __construct(
        public Carbon $dateFrom,
        public Carbon $dateTo,
        public float $monthlyTargetPerStaff = self::DEFAULT_MONTHLY_TARGET,
    ) {}

    public function daysElapsed(): int
    {
        return max($this->dateFrom->diffInDays($this->dateTo) + 1, 1);
    }

    public function proratedTarget(): float
    {
        return round($this->monthlyTargetPerStaff * $this->daysElapsed() / self::DAYS_IN_MONTH, 2);
    }

    /**
     * Branch-allocated prorated target for a staff member:
     * - 'both' branch staff split their monthly/prorated quota 50% Old Airport / 50% Al Wakrah
     * - single-branch staff allocate 100% to their home branch (0% on cross-branch guest upsells)
     */
    public function proratedTargetForStaffOnBranch(Staff $staff, string $branch): float
    {
        $fullProrated = $this->proratedTarget();

        if ($staff->branch === 'both') {
            return round($fullProrated * 0.5, 2);
        }

        if ($staff->branch === $branch) {
            return $fullProrated;
        }

        return 0.0;
    }

    public function borderFor(float $pct): string
    {
        return match (true) {
            $pct >= 85 => 'green',
            $pct >= 50 => 'amber',
            default => 'red',
        };
    }

    /**
     * Start-of-day / end-of-day bounds — SQLite stores every Carbon-cast
     * column as a full "Y-m-d H:i:s" string regardless of declared type, so
     * a plain 'Y-m-d' bound would lexically exclude rows.
     */
    private function dateRangeBounds(): array
    {
        return [$this->dateFrom->copy()->startOfDay(), $this->dateTo->copy()->endOfDay()];
    }

    /** Every staff member's paid upsell + retail product sales total for a branch within the range, keyed by staff_id. */
    private function upsellTotalsForBranch(string $branch): Collection
    {
        [$from, $to] = $this->dateRangeBounds();

        $upsells = AppointmentUpsell::query()
            ->join('appointments', 'appointments.id', '=', 'appointment_upsells.appointment_id')
            ->whereNotNull('appointment_upsells.staff_id')
            ->where('appointments.branch', $branch)
            ->where('appointments.status', 'completed')
            ->whereBetween('appointments.appointment_datetime', [$from, $to])
            ->groupBy('appointment_upsells.staff_id')
            ->selectRaw('appointment_upsells.staff_id, SUM(appointment_upsells.amount) as total')
            ->pluck('total', 'staff_id');

        $productSales = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.type', 'product')
            ->whereNotNull('sale_items.staff_id')
            ->where('sales.branch', $branch)
            ->where('sale_items.name', 'not like', '%(Upsell)')
            ->whereBetween('sales.created_at', [$from, $to])
            ->groupBy('sale_items.staff_id')
            ->selectRaw('sale_items.staff_id, SUM(sale_items.total) as total')
            ->pluck('total', 'staff_id');

        $allStaffIds = $upsells->keys()->merge($productSales->keys())->unique();

        return $allStaffIds->mapWithKeys(fn ($staffId) => [
            $staffId => round((float) $upsells->get($staffId, 0) + (float) $productSales->get($staffId, 0), 2),
        ]);
    }

    /** Every staff member's paid upsell + retail product sales total across ALL branches within the range. */
    private function upsellTotalsOverall(): Collection
    {
        [$from, $to] = $this->dateRangeBounds();

        $upsells = AppointmentUpsell::query()
            ->join('appointments', 'appointments.id', '=', 'appointment_upsells.appointment_id')
            ->whereNotNull('appointment_upsells.staff_id')
            ->where('appointments.status', 'completed')
            ->whereBetween('appointments.appointment_datetime', [$from, $to])
            ->groupBy('appointment_upsells.staff_id')
            ->selectRaw('appointment_upsells.staff_id, SUM(appointment_upsells.amount) as total')
            ->pluck('total', 'staff_id');

        $productSales = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.type', 'product')
            ->whereNotNull('sale_items.staff_id')
            ->where('sale_items.name', 'not like', '%(Upsell)')
            ->whereBetween('sales.created_at', [$from, $to])
            ->groupBy('sale_items.staff_id')
            ->selectRaw('sale_items.staff_id, SUM(sale_items.total) as total')
            ->pluck('total', 'staff_id');

        $allStaffIds = $upsells->keys()->merge($productSales->keys())->unique();

        return $allStaffIds->mapWithKeys(fn ($staffId) => [
            $staffId => round((float) $upsells->get($staffId, 0) + (float) $productSales->get($staffId, 0), 2),
        ]);
    }

    /** Active staff who normally work a branch (or 'both'), union'd with anyone who actually earned upsell there. */
    private function staffForBranch(string $branch): Collection
    {
        $rosterIds = Staff::active()
            ->where(fn($q) => $q->where('branch', $branch)->orWhere('branch', 'both'))
            ->pluck('id');

        $upsellIds = $this->upsellTotalsForBranch($branch)->keys();

        return Staff::whereIn('id', $rosterIds->merge($upsellIds)->unique())->orderBy('name')->get();
    }

    private function computeRow(Staff $staff, float $upsell, float $prorated): array
    {
        $pct = $prorated > 0 ? round($upsell / $prorated * 100, 1) : 0.0;

        return [
            'staff_id' => $staff->id,
            'name' => $staff->name,
            'branch' => $staff->branch,
            'upsell' => $upsell,
            'prorated_target' => $prorated,
            'pct' => $pct,
            'gap' => round($prorated - $upsell, 2),
            'border' => $this->borderFor($pct),
        ];
    }

    /** Per-staff breakdown for one branch — used by the "Sales & Upsells" tab. */
    public function computedStaff(string $branch): Collection
    {
        $totals = $this->upsellTotalsForBranch($branch);

        return $this->staffForBranch($branch)
            ->map(fn ($staff) => $this->computeRow(
                $staff,
                (float) $totals->get($staff->id, 0),
                $this->proratedTargetForStaffOnBranch($staff, $branch)
            ))
            ->values();
    }

    /** Team totals for one branch. */
    public function totals(string $branch): array
    {
        return $this->summarize($this->computedStaff($branch));
    }

    /** Per-staff breakdown across ALL branches (one row per person, upsell summed across branches worked). */
    public function allStaffComputed(): Collection
    {
        $prorated = $this->proratedTarget();
        $totals = $this->upsellTotalsOverall();
        $rosterIds = Staff::active()->pluck('id');
        $ids = $rosterIds->merge($totals->keys())->unique();

        return Staff::whereIn('id', $ids)->orderBy('name')->get()
            ->map(fn ($staff) => $this->computeRow($staff, (float) $totals->get($staff->id, 0), $prorated))
            ->values();
    }

    /** Team totals across all branches combined — used by the "Analytics" tab. */
    public function overallTotals(): array
    {
        return $this->summarize($this->allStaffComputed());
    }

    private function summarize(Collection $staff): array
    {
        $prorated = $this->proratedTarget();
        $teamTotal = round($staff->sum('upsell'), 2);
        $teamTarget = $staff->isNotEmpty()
            ? round((float) $staff->sum('prorated_target'), 2)
            : $prorated;
        $teamPct = $teamTarget > 0 ? round($teamTotal / $teamTarget * 100, 1) : 0.0;
        $top = $staff->sortByDesc('upsell')->first();

        return [
            'team_total' => $teamTotal,
            'team_target' => $teamTarget,
            'team_pct' => $teamPct,
            'top_performer' => $top['name'] ?? '—',
            'top_performer_amount' => $top['upsell'] ?? 0,
            'zero_upsell_count' => $staff->where('upsell', 0)->count(),
            'staff_count' => $staff->count(),
            'border' => $this->borderFor($teamPct),
        ];
    }

    /**
     * Branch-by-branch comparison for the Analytics tab. A staff member who
     * works "both" branches contributes their branch-specific earnings to
     * each branch's own total — that revenue was genuinely earned there, so
     * this is not double-counting.
     */
    public function branchComparison(): array
    {
        $branches = collect(self::BRANCHES)->map(function ($label, $key) {
            $totals = $this->totals($key);

            return array_merge(['key' => $key, 'label' => $label], $totals);
        })->values();

        $sorted = $branches->sortByDesc('team_total')->values();
        $leading = $sorted->first();
        $runnerUp = $sorted->get(1);

        return [
            'branches' => $branches,
            'leading_branch' => $leading && $leading['team_total'] > 0 ? $leading['label'] : null,
            'gap' => ($leading && $runnerUp)
                ? round(abs((float) $leading['team_total'] - (float) $runnerUp['team_total']), 2)
                : 0,
        ];
    }

    /** Top-performing staff ranking across all branches. */
    public function topPerformers(int $limit = 10): Collection
    {
        return $this->allStaffComputed()->sortByDesc('upsell')->values()->take($limit);
    }
}
