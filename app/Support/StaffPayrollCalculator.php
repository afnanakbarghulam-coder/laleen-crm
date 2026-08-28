<?php

namespace App\Support;

use App\Models\Staff;
use App\Models\StaffDeduction;
use App\Models\StaffOvertimeEntry;
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

    public function overtimeHours(Staff $staff): float
    {
        return (float) $this->overtimeEntriesFor($staff)->sum('hours');
    }

    public function overtimePay(Staff $staff): float
    {
        return round($this->overtimeEntriesFor($staff)->sum(fn ($e) => $e->hours * (float) ($e->rate ?? $staff->hourly_wage ?? 0)), 2);
    }

    public function deductionsTotal(Staff $staff): float
    {
        return round((float) $this->deductionsFor($staff)->sum('amount'), 2);
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
        return [
            'staff_id' => $staff->id,
            'name' => $staff->name,
            'branch' => $staff->branch,
            'base_salary' => $this->baseSalary($staff),
            'overtime_hours' => $this->overtimeHours($staff),
            'overtime_pay' => $this->overtimePay($staff),
            'deductions' => $this->deductionsTotal($staff),
            'net_salary' => $this->netSalary($staff),
        ];
    }

    public function payrollFor(Collection $staffs): Collection
    {
        return $staffs->map(fn (Staff $staff) => $this->rowFor($staff))->values();
    }
}
