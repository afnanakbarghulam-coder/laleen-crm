<?php

namespace Tests\Unit\NovaAI;

use App\Models\Staff;
use App\Models\StaffDeduction;
use App\Models\StaffOvertimeEntry;
use App\NovaAI\Services\NovaAIService;
use App\Support\StaffPayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 3H: the staff payroll cost snapshot section. Nova reads the CRM's
 * existing, unmodified StaffPayrollCalculator directly - so the payroll
 * figures here are parity checks against a live instance, not
 * hand-derived formulas.
 */
class StaffPayrollSectionTest extends TestCase
{
    use RefreshDatabase;

    private const GEMINI_URL_PATTERN = 'generativelanguage.googleapis.com/*';
    private const FROZEN_NOW = '2026-06-15 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gemini.key' => 'test-gemini-key']);
        config(['services.gemini.model' => 'gemini-3.5-flash-lite']);

        Carbon::setTestNow(Carbon::parse(self::FROZEN_NOW));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeStaff(string $name, string $branch, float $baseSalary): Staff
    {
        return Staff::create(['name' => $name, 'branch' => $branch, 'base_salary' => $baseSalary]);
    }

    private function makeOvertime(int $staffId, float $hours, float $rate, ?Carbon $when = null): StaffOvertimeEntry
    {
        return StaffOvertimeEntry::create([
            'staff_id' => $staffId,
            'entry_date' => ($when ?? now())->toDateString(),
            'hours' => $hours,
            'rate' => $rate,
        ]);
    }

    private function makeDeduction(int $staffId, float $amount, ?Carbon $when = null): StaffDeduction
    {
        return StaffDeduction::create([
            'staff_id' => $staffId,
            'deduction_date' => ($when ?? now())->toDateString(),
            'amount' => $amount,
            'reason' => 'Test deduction',
        ]);
    }

    private function fakeSuccess(): void
    {
        Http::fake([
            self::GEMINI_URL_PATTERN => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);
    }

    private function userTurnText(): string
    {
        $captured = null;

        Http::assertSent(function (HttpClientRequest $request) use (&$captured) {
            $captured = $request['contents'][0]['parts'][0]['text'] ?? '';

            return true;
        });

        return $captured ?? '';
    }

    private function systemInstructionText(): string
    {
        $captured = null;

        Http::assertSent(function (HttpClientRequest $request) use (&$captured) {
            $captured = $request['systemInstruction']['parts'][0]['text'] ?? '';

            return true;
        });

        return $captured ?? '';
    }

    private function payrollSection(string $text): string
    {
        $section = substr($text, strpos($text, 'STAFF PAYROLL COST'));
        $nextBreak = strpos($section, "\n\n");

        return $nextBreak !== false ? substr($section, 0, $nextBreak) : $section;
    }

    public function test_per_staff_row_matches_staff_payroll_calculator_exactly(): void
    {
        $staff = $this->makeStaff('Payroll Test Staff', 'old_airport', 3000.0);
        $this->makeOvertime($staff->id, 5.0, 30.0);
        $this->makeDeduction($staff->id, 50.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('What is our payroll looking like?');

        $section = $this->payrollSection($this->userTurnText());

        $reference = new StaffPayrollCalculator(now()->startOfMonth(), now()->endOfMonth());
        $row = $reference->rowFor($staff);

        $expectedLine = sprintf(
            '- %s (Old Airport): base salary %.2f QAR | overtime %.2f QAR (%.1f hrs) | deductions %.2f QAR |'
                . ' calculated net salary %.2f QAR',
            $row['name'],
            $row['base_salary'],
            $row['overtime_pay'],
            $row['overtime_hours'],
            $row['deductions'],
            $row['net_salary']
        );

        $this->assertStringContainsString('STAFF PAYROLL COST', $section);
        $this->assertStringContainsString($expectedLine, $section);
        // Confirms the fixture produced real, non-trivial numbers:
        // 3000 base + (5 hrs x 30 QAR/hr) overtime - 50 deductions = 3100.
        $this->assertSame(3100.0, $row['net_salary']);
    }

    public function test_base_salary_only_zero_overtime_and_deductions_represented_accurately(): void
    {
        $this->makeStaff('No Extras Staff', 'wakrah', 2000.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('What is our payroll looking like?');

        $section = $this->payrollSection($this->userTurnText());

        $this->assertStringContainsString(
            'No Extras Staff (Al Wakrah): base salary 2000.00 QAR | overtime 0.00 QAR (0.0 hrs) | deductions 0.00 QAR | calculated net salary 2000.00 QAR',
            $section
        );
    }

    public function test_overtime_and_deductions_outside_the_calendar_month_are_excluded(): void
    {
        $staff = $this->makeStaff('Period Isolation Staff', 'old_airport', 1000.0);
        $this->makeOvertime($staff->id, 10.0, 20.0); // this month - included
        $this->makeOvertime($staff->id, 99.0, 50.0, now()->subMonthNoOverflow()); // last month - excluded
        $this->makeDeduction($staff->id, 500.0, now()->addMonthNoOverflow()); // next month - excluded

        $this->fakeSuccess();
        (new NovaAIService())->ask('What is our payroll looking like?');

        $section = $this->payrollSection($this->userTurnText());

        // 1000 base + (10 * 20) overtime - 0 deductions = 1200.
        $this->assertStringContainsString(
            'Period Isolation Staff (Old Airport): base salary 1000.00 QAR | overtime 200.00 QAR (10.0 hrs) | deductions 0.00 QAR | calculated net salary 1200.00 QAR',
            $section
        );
    }

    public function test_branch_totals_do_not_leak_into_each_other(): void
    {
        $this->makeStaff('OA Person', 'old_airport', 3000.0);
        $this->makeStaff('Wakrah Person', 'wakrah', 2500.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('What is our payroll looking like?');

        $section = $this->payrollSection($this->userTurnText());

        $this->assertStringContainsString('Old Airport (1 staff): base salary 3000.00 QAR', $section);
        $this->assertStringContainsString('Al Wakrah (1 staff): base salary 2500.00 QAR', $section);
    }

    public function test_both_branch_staff_are_not_double_counted_into_either_branch_total(): void
    {
        $this->makeStaff('OA Only', 'old_airport', 1000.0);
        $this->makeStaff('Both Branch Person', 'both', 4000.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('What is our payroll looking like?');

        $section = $this->payrollSection($this->userTurnText());

        // Old Airport total must be ONLY the old_airport-branch staff member (1000), not 1000+4000.
        $this->assertStringContainsString('Old Airport (1 staff): base salary 1000.00 QAR', $section);
        $this->assertStringContainsString('Al Wakrah (0 staff): base salary 0.00 QAR', $section);
        $this->assertStringContainsString(
            'Both-branch staff (not included in either branch total above, to avoid double-counting) (1 staff): base salary 4000.00 QAR',
            $section
        );
        // Overall total correctly includes everyone exactly once: 1000 + 4000 = 5000.
        $this->assertStringContainsString('All active staff combined (2 staff): base salary 5000.00 QAR', $section);
    }

    public function test_payroll_is_never_labeled_or_confused_with_commission(): void
    {
        $this->makeStaff('Payroll Separation Check', 'old_airport', 2000.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('What is our payroll looking like?');

        $text = $this->userTurnText();
        $section = $this->payrollSection($text);

        // The per-staff payroll LINE itself must never mention commission -
        // only the section's own closing disclaimer sentence is allowed to,
        // since it exists specifically to clarify the two are separate
        // (checked positively below).
        $staffLine = explode("\n", trim($section))[1] ?? '';
        $this->assertStringContainsString('Payroll Separation Check', $staffLine);
        $this->assertStringNotContainsString('commission', strtolower($staffLine));
        $this->assertStringContainsString('excludes commission entirely', $text);
    }

    public function test_no_staff_reports_plainly(): void
    {
        // No Staff rows at all.
        $this->fakeSuccess();
        (new NovaAIService())->ask('What is our payroll looking like?');

        $section = $this->payrollSection($this->userTurnText());

        $this->assertStringContainsString('No active staff recorded for payroll calculation.', $section);
    }

    public function test_no_sensitive_hr_fields_appear_in_the_payroll_section(): void
    {
        $staff = Staff::create([
            'name' => 'Privacy Check Staff',
            'branch' => 'old_airport',
            'base_salary' => 1500.0,
            'phone' => '55512345',
            'address_line1' => '123 Secret Street',
            'emergency_contact_name' => 'Emergency Contact Name',
            'emergency_contact_phone' => '55598765',
            'internal_notes' => 'Confidential HR note',
        ]);

        $this->fakeSuccess();
        (new NovaAIService())->ask('What is our payroll looking like?');

        $section = $this->payrollSection($this->userTurnText());

        $this->assertStringNotContainsString('55512345', $section);
        $this->assertStringNotContainsString('123 Secret Street', $section);
        $this->assertStringNotContainsString('Emergency Contact Name', $section);
        $this->assertStringNotContainsString('55598765', $section);
        $this->assertStringNotContainsString('Confidential HR note', $section);
    }

    public function test_payroll_data_is_in_the_user_turn_not_the_system_instruction(): void
    {
        $this->makeStaff('Placement Staff', 'old_airport', 1000.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('What is our payroll looking like?');

        $systemText = $this->systemInstructionText();

        $this->assertStringNotContainsString('STAFF PAYROLL COST', $systemText);
        $this->assertStringNotContainsString('Placement Staff', $systemText);
    }

    public function test_system_instruction_carries_the_payroll_evidence_caveats(): void
    {
        $this->fakeSuccess();
        (new NovaAIService())->ask('What is our payroll looking like?');

        $systemText = $this->systemInstructionText();
        $normalized = preg_replace('/\s+/', ' ', $systemText);

        $this->assertStringContainsStringIgnoringCase('separate calculation from the estimated commission', $normalized);
        $this->assertStringContainsStringIgnoringCase('not established anywhere in the code', $normalized);
        $this->assertStringContainsStringIgnoringCase('double-counting', $normalized);
    }
}
