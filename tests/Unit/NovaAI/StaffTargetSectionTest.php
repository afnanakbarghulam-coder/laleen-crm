<?php

namespace Tests\Unit\NovaAI;

use App\Models\Appointment;
use App\Models\AppointmentUpsell;
use App\Models\Staff;
use App\NovaAI\Services\NovaAIService;
use App\Support\StaffSalesAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 3D: the staff-target-performance snapshot section. Nova must read
 * App\Support\StaffSalesAnalytics (the CRM's existing, unmodified upsell
 * target engine) without duplicating its formulas - so most assertions
 * here are parity checks: build the expected string FROM a real
 * StaffSalesAnalytics instance rather than hand-deriving numbers, so a
 * failure here means Nova's snapshot has drifted from the CRM's own
 * calculation, not that the test's own math disagreed with it.
 */
class StaffTargetSectionTest extends TestCase
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

    private function makeStaff(string $name, string $branch): Staff
    {
        return Staff::create(['name' => $name, 'branch' => $branch]);
    }

    private function makeUpsell(int $staffId, string $branch, float $amount, ?Carbon $when = null): AppointmentUpsell
    {
        $appointment = Appointment::create([
            'customer_name' => 'Test Customer',
            'phone' => '+97400000000',
            'appointment_datetime' => $when ?? now(),
            'service_name' => 'Test Service',
            'branch' => $branch,
            'status' => 'completed',
        ]);

        return AppointmentUpsell::create([
            'appointment_id' => $appointment->id,
            'staff_id' => $staffId,
            'type' => 'service',
            'name' => 'Test Upsell',
            'amount' => $amount,
        ]);
    }

    private function fakeSuccess(): void
    {
        Http::fake([
            self::GEMINI_URL_PATTERN => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'ok']]]],
                ],
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

    public function test_per_staff_line_matches_staffsalesanalytics_exactly(): void
    {
        $anita = $this->makeStaff('Anita', 'old_airport');
        $this->makeUpsell($anita->id, 'old_airport', 620.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is staff performance against target?');

        $text = $this->userTurnText();

        // Build the expected line FROM the real, unmodified CRM service -
        // never a hand-derived number - so this is a parity check.
        $reference = new StaffSalesAnalytics(now()->startOfMonth(), now()->endOfDay());
        $row = $reference->computedStaff('old_airport')->firstWhere('staff_id', $anita->id);

        $expectedLine = sprintf(
            '- %s: %.2f QAR upsell | target %.2f QAR | %.1f%% | gap %.2f QAR | %s',
            $row['name'],
            $row['upsell'],
            $row['prorated_target'],
            $row['pct'],
            $row['gap'],
            strtoupper($row['border'])
        );

        $this->assertStringContainsString('STAFF TARGET PERFORMANCE', $text);
        $this->assertStringContainsString($expectedLine, $text);
    }

    public function test_branch_summary_matches_staffsalesanalytics_totals(): void
    {
        $staffA = $this->makeStaff('Fatima', 'wakrah');
        $staffB = $this->makeStaff('Layla', 'wakrah');
        $this->makeUpsell($staffA->id, 'wakrah', 900.0);
        $this->makeUpsell($staffB->id, 'wakrah', 100.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is staff performance against target?');

        $text = $this->userTurnText();

        $reference = new StaffSalesAnalytics(now()->startOfMonth(), now()->endOfDay());
        $totals = $reference->totals('wakrah');
        $staffRows = $reference->computedStaff('wakrah');
        $borderCounts = $staffRows->countBy('border');

        $expectedSummary = sprintf(
            '- Al Wakrah summary: %d green / %d amber / %d red, team %.2f QAR of %.2f QAR target (%.1f%%),'
                . ' top performer %s (%.2f QAR)',
            $borderCounts->get('green', 0),
            $borderCounts->get('amber', 0),
            $borderCounts->get('red', 0),
            $totals['team_total'],
            $totals['team_target'],
            $totals['team_pct'],
            $totals['top_performer'],
            $totals['top_performer_amount']
        );

        $this->assertStringContainsString($expectedSummary, $text);
    }

    public function test_branches_do_not_leak_into_each_other(): void
    {
        $oldAirportStaff = $this->makeStaff('Nour', 'old_airport');
        $wakrahStaff = $this->makeStaff('Sumera', 'wakrah');
        $this->makeUpsell($oldAirportStaff->id, 'old_airport', 400.0);
        $this->makeUpsell($wakrahStaff->id, 'wakrah', 300.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is staff performance against target?');

        $text = $this->userTurnText();

        // Isolate just the STAFF TARGET PERFORMANCE section first - the
        // branch-financial-performance section earlier in the same
        // snapshot also contains "- Old Airport: ..." / "- Al Wakrah: ..."
        // lines, which would otherwise confuse a plain strpos() search.
        $staffSection = substr($text, strpos($text, 'STAFF TARGET PERFORMANCE'));
        $nextSectionBreak = strpos($staffSection, "\n\n");
        if ($nextSectionBreak !== false) {
            $staffSection = substr($staffSection, 0, $nextSectionBreak);
        }

        // Within that isolated section, split at the branch headers (e.g.
        // "Old Airport:" with no leading dash) and confirm each name only
        // ever appears under its own branch.
        $oldAirportSection = substr(
            $staffSection,
            strpos($staffSection, 'Old Airport:'),
            strpos($staffSection, 'Al Wakrah:') - strpos($staffSection, 'Old Airport:')
        );
        $wakrahSection = substr($staffSection, strpos($staffSection, 'Al Wakrah:'));

        $this->assertStringContainsString('Nour', $oldAirportSection);
        $this->assertStringNotContainsString('Sumera', $oldAirportSection);
        $this->assertStringContainsString('Sumera', $wakrahSection);
        $this->assertStringNotContainsString('Nour', $wakrahSection);
    }

    public function test_zero_upsell_staff_is_represented_accurately_not_invented(): void
    {
        // An active staff member with no upsell activity at all this month.
        $idle = $this->makeStaff('Idle Staff', 'old_airport');

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is staff performance against target?');

        $text = $this->userTurnText();

        $reference = new StaffSalesAnalytics(now()->startOfMonth(), now()->endOfDay());
        $row = $reference->computedStaff('old_airport')->firstWhere('staff_id', $idle->id);

        $this->assertSame(0.0, $row['upsell']);
        $this->assertSame(0.0, $row['pct']);
        $this->assertSame('red', $row['border']);

        $expectedLine = sprintf(
            '- %s: %.2f QAR upsell | target %.2f QAR | %.1f%% | gap %.2f QAR | RED',
            $row['name'],
            $row['upsell'],
            $row['prorated_target'],
            $row['pct'],
            $row['gap']
        );

        $this->assertStringContainsString($expectedLine, $text);
    }

    public function test_status_thresholds_match_staffsalesanalytics_border_logic_exactly(): void
    {
        // Derive amounts FROM the class's own prorated target for this
        // window, rather than assuming any specific numeric constant -
        // guarantees the thresholds are read from the CRM logic, not
        // reinvented in the test.
        $reference = new StaffSalesAnalytics(now()->startOfMonth(), now()->endOfDay());
        $prorated = $reference->proratedTarget();

        $green = $this->makeStaff('Green Staff', 'old_airport');
        $amber = $this->makeStaff('Amber Staff', 'old_airport');
        $red = $this->makeStaff('Red Staff', 'old_airport');

        $this->makeUpsell($green->id, 'old_airport', $prorated * 0.90); // 90% -> green (>=85)
        $this->makeUpsell($amber->id, 'old_airport', $prorated * 0.60); // 60% -> amber (>=50)
        $this->makeUpsell($red->id, 'old_airport', $prorated * 0.30);   // 30% -> red

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is staff performance against target?');

        $text = $this->userTurnText();

        $liveReference = new StaffSalesAnalytics(now()->startOfMonth(), now()->endOfDay());
        $rows = $liveReference->computedStaff('old_airport')->keyBy('name');

        $this->assertSame('green', $rows['Green Staff']['border']);
        $this->assertSame('amber', $rows['Amber Staff']['border']);
        $this->assertSame('red', $rows['Red Staff']['border']);

        $this->assertStringContainsString('Green Staff:', $text);
        $this->assertStringContainsString('| GREEN', $text);
        $this->assertStringContainsString('| AMBER', $text);
        $this->assertStringContainsString('| RED', $text);
    }

    public function test_staff_target_data_is_in_the_user_turn_not_the_system_instruction(): void
    {
        // Deliberately not "Anita" - the system prompt's own illustrative
        // example ("Anita is at 62% of her prorated upsell target") would
        // make that name a false positive here regardless of the snapshot.
        $staff = $this->makeStaff('Zohra Test Fixture', 'old_airport');
        $this->makeUpsell($staff->id, 'old_airport', 620.0);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How is staff performance against target?');

        $systemText = $this->systemInstructionText();

        $this->assertStringNotContainsString('STAFF TARGET PERFORMANCE', $systemText);
        $this->assertStringNotContainsString('Zohra Test Fixture', $systemText);
    }

    public function test_system_instruction_carries_the_upsell_target_evidence_caveat(): void
    {
        $this->fakeSuccess();
        (new NovaAIService())->ask('How is staff performance against target?');

        $systemText = $this->systemInstructionText();
        $normalized = preg_replace('/\s+/', ' ', $systemText);

        $this->assertStringContainsStringIgnoringCase('existing UPSELL target system', $normalized);
        $this->assertStringContainsStringIgnoringCase("never say \"Anita is your worst employee\"", $normalized);
        $this->assertStringContainsStringIgnoringCase('no clock-in attendance system for stylists', $normalized);
    }
}
