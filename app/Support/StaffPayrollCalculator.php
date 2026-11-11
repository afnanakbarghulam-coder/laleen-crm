<?php

namespace App\Support;

use App\Models\SaleItem;
use App\Models\Staff;
use App\Models\StaffDeduction;
use App\Models\StaffOvertimeEntry;
use App\Models\StaffTimeOff;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Live, non-persisted payroll computation for a date range — nothing here
 * is ever saved. Net Salary = Base Salary + Overtime Pay - Deductions.
 * Commissions are intentionally never referenced: payroll is fixed pay plus
 * logged overtime, minus logged deductions, full stop.
 */
class StaffPayrollCalculator
{
    /** @var array<int, Collection> */
    private array $overtimeCache = [];

    /** @var array<int, Collection> */
    private array $deductionsCache = [];

    /** @var array<int, Collection> */
    private array $unpaidTimeOffsCache = [];

    /** @var array<int, float> */
    private array $commissionSalesCache = [];

    public function __construct(
        public Carbon $dateFrom,
        public Carbon $dateTo,
    ) {}

    /**
     * Batch-preload overtime entries, deductions (with complaints), unpaid leave,
     * and commissionable sales for an entire Staff collection in O(1) SQL queries.
     */
    public function preloadForStaff(Collection $staffs): void
    {
        $staffIds = $staffs->pluck('id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        if ($staffIds->isEmpty()) {
            return;
        }

        $bounds = $this->dateRangeBounds();
        $fromStr = $this->dateFrom->toDateString();
        $toStr = $this->dateTo->toDateString();
        $staffById = $staffs->keyBy('id');

        $overtimeByStaff = StaffOvertimeEntry::whereIn('staff_id', $staffIds)
            ->whereBetween('entry_date', $bounds)
            ->get()
            ->groupBy('staff_id');

        $deductionsByStaff = StaffDeduction::with('complaint')
            ->whereIn('staff_id', $staffIds)
            ->whereBetween('deduction_date', $bounds)
            ->get()
            ->groupBy('staff_id');

        $unpaidLeaveByStaff = StaffTimeOff::whereIn('staff_id', $staffIds)
            ->where('reason', 'unpaid')
            ->whereDate('start_date', '<=', $toStr)
            ->whereDate('end_date', '>=', $fromStr)
            ->get()
            ->groupBy('staff_id');

        $commissionStaffIds = $staffs
            ->filter(fn (Staff $s) => (float) ($s->commission_rate ?? 0) > 0)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $salesByStaff = collect();
        if ($commissionStaffIds->isNotEmpty()) {
            $salesByStaff = SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->leftJoin('appointments', 'appointments.id', '=', 'sales.appointment_id')
                ->whereIn('sale_items.staff_id', $commissionStaffIds)
                ->whereBetween('sales.created_at', $bounds)
                ->where(function ($q) {
                    $q->whereNull('sales.appointment_id')
                        ->orWhereNotIn('appointments.status', ['cancelled', 'no_show']);
                })
                ->groupBy('sale_items.staff_id')
                ->selectRaw('sale_items.staff_id, SUM(sale_items.total) as total')
                ->pluck('total', 'staff_id');
        }

        foreach ($staffIds as $id) {
            $staffModel = $staffById->get($id);
            $otRows = $overtimeByStaff->get($id, collect());
            if ($staffModel) {
                $otRows->each(fn ($entry) => $entry->setRelation('staff', $staffModel));
            }
            $this->overtimeCache[$id] = $otRows;
            $this->deductionsCache[$id] = $deductionsByStaff->get($id, collect());
            $this->unpaidTimeOffsCache[$id] = $unpaidLeaveByStaff->get($id, collect());
            $this->commissionSalesCache[$id] = (float) $salesByStaff->get($id, 0.0);
        }
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

    private function overtimeEntriesFor(Staff $staff): Collection
    {
        if (array_key_exists($staff->id, $this->overtimeCache)) {
            return $this->overtimeCache[$staff->id];
        }

        $entries = StaffOvertimeEntry::where('staff_id', $staff->id)
            ->whereBetween('entry_date', $this->dateRangeBounds())
            ->get();
        $entries->each(fn ($e) => $e->setRelation('staff', $staff));

        return $this->overtimeCache[$staff->id] = $entries;
    }

    private function deductionsFor(Staff $staff): Collection
    {
        if (array_key_exists($staff->id, $this->deductionsCache)) {
            return $this->deductionsCache[$staff->id];
        }

        return $this->deductionsCache[$staff->id] = StaffDeduction::with('complaint')
            ->where('staff_id', $staff->id)
            ->whereBetween('deduction_date', $this->dateRangeBounds())
            ->get();
    }

    private function unpaidTimeOffsFor(Staff $staff): Collection
    {
        if (array_key_exists($staff->id, $this->unpaidTimeOffsCache)) {
            return $this->unpaidTimeOffsCache[$staff->id];
        }

        $fromStr = $this->dateFrom->toDateString();
        $toStr = $this->dateTo->toDateString();

        return $this->unpaidTimeOffsCache[$staff->id] = StaffTimeOff::where('staff_id', $staff->id)
            ->where('reason', 'unpaid')
            ->whereDate('start_date', '<=', $toStr)
            ->whereDate('end_date', '>=', $fromStr)
            ->get();
    }

    public function commissionAmount(Staff $staff): float
    {
        $rate = (float) ($staff->commission_rate ?? 0);
        if ($rate <= 0) {
            return 0.0;
        }

        if (!array_key_exists($staff->id, $this->commissionSalesCache)) {
            $this->commissionSalesCache[$staff->id] = (float) SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->leftJoin('appointments', 'appointments.id', '=', 'sales.appointment_id')
                ->where('sale_items.staff_id', $staff->id)
                ->whereBetween('sales.created_at', $this->dateRangeBounds())
                ->where(function ($q) {
                    $q->whereNull('sales.appointment_id')
                        ->orWhereNotIn('appointments.status', ['cancelled', 'no_show']);
                })
                ->sum('sale_items.total');
        }

        return round($this->commissionSalesCache[$staff->id] * ($rate / 100), 2);
    }

    public function unpaidLeaveDays(Staff $staff): int
    {
        $rangeStart = $this->dateFrom->copy()->startOfDay();
        $rangeEnd = $this->dateTo->copy()->startOfDay();
        $days = 0;

        foreach ($this->unpaidTimeOffsFor($staff) as $timeOff) {
            $start = Carbon::parse($timeOff->start_date)->startOfDay();
            $end = Carbon::parse($timeOff->end_date)->startOfDay();

            if ($start->lt($rangeStart)) {
                $start = $rangeStart->copy();
            }
            if ($end->gt($rangeEnd)) {
                $end = $rangeEnd->copy();
            }

            if ($end->gte($start)) {
                $days += (int) $start->diffInDays($end) + 1;
            }
        }

        return $days;
    }

    public function unpaidLeaveDeduction(Staff $staff): float
    {
        $baseSalary = $this->baseSalary($staff);
        $unpaidDays = $this->unpaidLeaveDays($staff);
        if ($baseSalary <= 0 || $unpaidDays <= 0) {
            return 0.0;
        }

        $daysInMonth = max((int) $this->dateFrom->daysInMonth, 1);
        $deduction = round(($baseSalary / $daysInMonth) * $unpaidDays, 2);

        return min($baseSalary, $deduction);
    }

    public function overtimeHours(Staff $staff): float
    {
        return (float) $this->overtimeEntriesFor($staff)->sum('hours');
    }

    public function overtimePay(Staff $staff): float
    {
        return round($this->overtimeEntriesFor($staff)->sum(function ($e) use ($staff) {
            $e->setRelation('staff', $staff);

            return $e->pay();
        }), 2);
    }

    public function deductionsTotal(Staff $staff): float
    {
        $loggedDeductions = (float) $this->deductionsFor($staff)->sum('amount');

        return round($loggedDeductions + $this->unpaidLeaveDeduction($staff), 2);
    }

    public function baseSalary(Staff $staff): float
    {
        return (float) ($staff->base_salary ?? 0);
    }

    public function netSalary(Staff $staff): float
    {
        return round($this->baseSalary($staff) + $this->overtimePay($staff) - $this->deductionsTotal($staff), 2);
    }

    /** One computed payroll row per staff member. */
    public function rowFor(Staff $staff): array
    {
        $overtimeEntries = $this->overtimeEntriesFor($staff)->sortByDesc('entry_date')->values();
        $deductionEntries = $this->deductionsFor($staff);
        if ($deductionEntries->isNotEmpty() && !$deductionEntries->first()->relationLoaded('complaint')) {
            $deductionEntries->load('complaint');
        }
        $deductionEntries = $deductionEntries->sortByDesc('deduction_date')->values();

        return [
            'staff_id' => $staff->id,
            'name' => $staff->name,
            'branch' => $staff->branch,
            'base_salary' => $this->baseSalary($staff),
            'hourly_wage' => (float) ($staff->hourly_wage ?? 0),
            'commission_rate' => (float) ($staff->commission_rate ?? 0),
            'commission_amount' => $this->commissionAmount($staff),
            'overtime_hours' => $this->overtimeHours($staff),
            'overtime_pay' => $this->overtimePay($staff),
            'unpaid_leave_days' => $this->unpaidLeaveDays($staff),
            'unpaid_leave_deduction' => $this->unpaidLeaveDeduction($staff),
            'deductions' => $this->deductionsTotal($staff),
            'net_salary' => $this->netSalary($staff),
            'overtime_entries' => $overtimeEntries,
            'deduction_entries' => $deductionEntries,
        ];
    }

    public function payrollFor(Collection $staffs): Collection
    {
        $this->preloadForStaff($staffs);

        return $staffs->map(fn (Staff $staff) => $this->rowFor($staff))->values();
    }
}
