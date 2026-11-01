<?php

namespace App\Support;

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
    public function __construct(
        public Carbon $dateFrom,
        public Carbon $dateTo,
    ) {}

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
        return StaffOvertimeEntry::where('staff_id', $staff->id)
            ->whereBetween('entry_date', $this->dateRangeBounds())
            ->get();
    }

    private function deductionsFor(Staff $staff): Collection
    {
        return StaffDeduction::where('staff_id', $staff->id)
            ->whereBetween('deduction_date', $this->dateRangeBounds())
            ->get();
    }

    private function unpaidTimeOffsFor(Staff $staff): Collection
    {
        $fromStr = $this->dateFrom->toDateString();
        $toStr = $this->dateTo->toDateString();

        return StaffTimeOff::where('staff_id', $staff->id)
            ->where('reason', 'unpaid')
            ->whereDate('start_date', '<=', $toStr)
            ->whereDate('end_date', '>=', $fromStr)
            ->get();
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
        $deductionEntries = $this->deductionsFor($staff)->load('complaint')->sortByDesc('deduction_date')->values();

        return [
            'staff_id' => $staff->id,
            'name' => $staff->name,
            'branch' => $staff->branch,
            'base_salary' => $this->baseSalary($staff),
            'hourly_wage' => (float) ($staff->hourly_wage ?? 0),
            'commission_rate' => (float) ($staff->commission_rate ?? 0),
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
        return $staffs->map(fn (Staff $staff) => $this->rowFor($staff))->values();
    }
}
