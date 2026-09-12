<?php

namespace Tests\Unit;

use App\Models\Staff;
use App\Models\StaffDeduction;
use App\Models\StaffOvertimeEntry;
use App\NovaAI\Services\NovaAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * NOVA READ-ONLY INTEGRATION RULE guard (see app/NovaAI/README.md) for
 * Stage 3H. The direction must stay:
 *
 *   CRM payroll data / StaffPayrollCalculator -> READ -> NOVA
 *
 * never the reverse.
 */
class PayrollReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_payroll_calculator_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Support/StaffPayrollCalculator.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_staff_model_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Models/Staff.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_overtime_and_deduction_models_have_no_awareness_of_nova(): void
    {
        $overtimeSource = file_get_contents(app_path('Models/StaffOvertimeEntry.php'));
        $deductionSource = file_get_contents(app_path('Models/StaffDeduction.php'));

        foreach ([$overtimeSource, $deductionSource] as $source) {
            $this->assertStringNotContainsString('NovaAI', $source);
            $this->assertStringNotContainsString('Nova', $source);
        }
    }

    public function test_staff_controller_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/StaffController.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_nova_payroll_analytics_declares_its_read_only_source(): void
    {
        $source = file_get_contents(app_path('NovaAI/Support/PayrollPerformanceAnalytics.php'));

        $this->assertStringContainsString('StaffPayrollCalculator', $source);
    }

    /**
     * Instruction 17's explicit accounting guard: confirm (by re-checking
     * the actual source, not just trusting the earlier audit) that nothing
     * in this codebase ever writes a StaffPayrollCalculator-derived amount
     * into Expense::create(). If this ever changes, this test must be
     * revisited alongside the "payroll vs expense" prompt/section wording,
     * since that would newly establish a relationship Nova currently must
     * describe as unproven.
     */
    public function test_payroll_code_never_writes_into_expense(): void
    {
        // FinanceController::storeExpense() legitimately calls
        // Expense::create() for its own, unrelated manual-entry feature -
        // that's expected and out of scope here. The question this guards
        // is narrower: does anything on the PAYROLL side ever post a
        // payroll-derived amount into Expense? It must not.
        $searchPaths = [
            app_path('Support/StaffPayrollCalculator.php'),
            app_path('Http/Controllers/StaffController.php'),
        ];

        foreach ($searchPaths as $path) {
            $source = file_get_contents($path);
            $this->assertStringNotContainsString('Expense::create', $source, "{$path} unexpectedly writes to Expense - the payroll/expense relationship documented as 'unestablished' needs re-verifying.");
            $this->assertStringNotContainsString('new Expense(', $source, "{$path} unexpectedly writes to Expense - the payroll/expense relationship documented as 'unestablished' needs re-verifying.");
        }
    }

    public function test_nova_never_persists_payroll_related_records(): void
    {
        $source = file_get_contents(app_path('NovaAI/Support/PayrollPerformanceAnalytics.php'));

        $this->assertStringNotContainsString('::create(', $source);
        $this->assertStringNotContainsString('->save()', $source);
    }

    public function test_nova_payroll_section_makes_zero_writes_to_staff_data(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        $staff = Staff::create(['name' => 'Read Only Payroll Staff', 'branch' => 'old_airport', 'base_salary' => 2000.0]);
        $overtime = StaffOvertimeEntry::create([
            'staff_id' => $staff->id,
            'entry_date' => now()->toDateString(),
            'hours' => 3.0,
            'rate' => 25.0,
        ]);
        $deduction = StaffDeduction::create([
            'staff_id' => $staff->id,
            'deduction_date' => now()->toDateString(),
            'amount' => 20.0,
            'reason' => 'Test deduction',
        ]);

        $staffBefore = Staff::find($staff->id)->getAttributes();
        $overtimeBefore = StaffOvertimeEntry::find($overtime->id)->getAttributes();
        $deductionBefore = StaffDeduction::find($deduction->id)->getAttributes();
        $countsBefore = [Staff::count(), StaffOvertimeEntry::count(), StaffDeduction::count()];

        (new NovaAIService())->ask('What is our payroll looking like?');

        $this->assertSame($countsBefore, [Staff::count(), StaffOvertimeEntry::count(), StaffDeduction::count()]);
        $this->assertSame($staffBefore, Staff::find($staff->id)->getAttributes());
        $this->assertSame($overtimeBefore, StaffOvertimeEntry::find($overtime->id)->getAttributes());
        $this->assertSame($deductionBefore, StaffDeduction::find($deduction->id)->getAttributes());
    }
}
