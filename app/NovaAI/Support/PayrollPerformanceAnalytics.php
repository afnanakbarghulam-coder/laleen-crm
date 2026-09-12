<?php

namespace App\NovaAI\Support;

use App\Models\Staff;
use App\Support\StaffPayrollCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * NOVA READ-ONLY INTEGRATION RULE (see app/NovaAI/README.md): consumes the
 * CRM's existing, unmodified App\Support\StaffPayrollCalculator directly -
 * rowFor()/payrollFor() are already public and population-agnostic, and
 * this is exactly how StaffController::index()'s Payroll tab already uses
 * it (same active-staff roster, same date-range construction). Nothing
 * here recomputes the payroll formula itself; this class only adds
 * Nova-specific branch bucketing that never double-counts a
 * 'both'-branch staff member into two branch totals.
 *
 * Payroll formula (unchanged, read straight from the calculator):
 *   net_salary = base_salary + overtime_pay - deductions
 * Commission is never referenced by this formula - see
 * NovaAIService::staffPayrollSection()'s docblock for why that must never
 * be confused with the estimated-commission figure in
 * staffPerformanceSection().
 *
 * base_salary is NOT prorated to the selected period - it is always the
 * staff member's full stored Staff.base_salary, regardless of how long
 * [$from, $to] spans. This class does not invent proration either.
 */
class PayrollPerformanceAnalytics
{
    public function summary(Carbon $from, Carbon $to): array
    {
        $calculator = new StaffPayrollCalculator($from, $to);

        // Same population StaffController::index()'s Payroll tab already
        // uses - no separate Nova roster invented.
        $staff = Staff::active()->orderBy('name')->get();
        $rows = $calculator->payrollFor($staff);

        return [
            'rows' => $rows,
            'old_airport' => $this->totalsFor($rows->where('branch', 'old_airport')),
            'wakrah' => $this->totalsFor($rows->where('branch', 'wakrah')),
            'both' => $this->totalsFor($rows->where('branch', 'both')),
            'overall' => $this->totalsFor($rows),
        ];
    }

    private function totalsFor(Collection $rows): array
    {
        return [
            'staff_count' => $rows->count(),
            'base_salary' => round($rows->sum('base_salary'), 2),
            'overtime_pay' => round($rows->sum('overtime_pay'), 2),
            'deductions' => round($rows->sum('deductions'), 2),
            'net_salary' => round($rows->sum('net_salary'), 2),
        ];
    }
}
