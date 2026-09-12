<?php

namespace Tests\Unit\NovaAI;

use App\Models\AgentShiftLog;
use App\Models\Appointment;
use App\Models\KpiAgentTargetReport;
use App\Models\User;
use App\NovaAI\Services\NovaAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 3F: the booking-agent performance snapshot section. Nova reads
 * the CRM's existing, unmodified KpiAgentTargetReport for shift-level
 * bookings/target/achievement - so the shift-level assertions here are
 * parity checks against a live instance of that class, not hand-derived
 * formulas.
 */
class BookingAgentPerformanceSectionTest extends TestCase
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

    private function makeAgent(string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
            'password' => 'irrelevant-for-tests',
            'role' => 'agent',
        ]);
    }

    private function makeManager(string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
            'password' => 'irrelevant-for-tests',
            'role' => 'admin',
        ]);
    }

    private function makeShiftLog(int $userId, Carbon $date, string $shift, string $checkIn, string $checkOut): AgentShiftLog
    {
        return AgentShiftLog::create([
            'user_id' => $userId,
            'date' => $date->toDateString(),
            'shift' => $shift,
            'check_in_time' => $checkIn,
            'check_out_time' => $checkOut,
        ]);
    }

    private function makeAppointment(int $createdBy, Carbon $createdAt, string $branch = 'old_airport'): Appointment
    {
        $appointment = new Appointment([
            'customer_name' => 'Test Customer',
            'phone' => '+97400000000',
            'appointment_datetime' => $createdAt,
            'service_name' => 'Test Service',
            'branch' => $branch,
            'status' => 'pending',
            'created_by' => $createdBy,
        ]);
        $appointment->created_at = $createdAt;
        $appointment->save();

        return $appointment;
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

    private function agentSection(string $text): string
    {
        $section = substr($text, strpos($text, 'BOOKING AGENT PERFORMANCE'));
        $nextBreak = strpos($section, "\n\n");

        return $nextBreak !== false ? substr($section, 0, $nextBreak) : $section;
    }

    public function test_shift_level_figures_match_kpi_agent_target_report_exactly(): void
    {
        $agent = $this->makeAgent('Morning Agent');
        $this->makeShiftLog($agent->id, now(), 'morning', '08:00', '14:00');
        $this->makeAppointment($agent->id, now()->setTime(9, 0));
        $this->makeAppointment($agent->id, now()->setTime(10, 0));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are our booking agents doing?');

        $section = $this->agentSection($this->userTurnText());

        $reference = new KpiAgentTargetReport(['date_from' => now()->startOfMonth(), 'date_to' => now()->endOfDay()]);
        $shiftStats = $reference->shiftStats();
        $combined = $reference->combined();

        $this->assertStringContainsString(
            sprintf(
                'Morning: %d bookings | target %d | %.1f%% | gap %d | %s',
                $shiftStats['morning']['bookings'],
                $shiftStats['morning']['target'],
                $shiftStats['morning']['pct'],
                $shiftStats['morning']['gap'],
                strtoupper($shiftStats['morning']['border'])
            ),
            $section
        );
        $this->assertStringContainsString(
            sprintf('Combined: %d bookings | target %d', $combined['bookings'], $combined['target']),
            $section
        );
        // Confirms the fixture actually produced real bookings, not a
        // trivially-passing 0-vs-0 comparison.
        $this->assertSame(2, $shiftStats['morning']['bookings']);
    }

    public function test_morning_and_evening_attribution_use_the_agents_own_shift_window(): void
    {
        $morningAgent = $this->makeAgent('AM Agent');
        $eveningAgent = $this->makeAgent('PM Agent');

        $this->makeShiftLog($morningAgent->id, now(), 'morning', '08:00', '14:00');
        $this->makeShiftLog($eveningAgent->id, now(), 'evening', '15:00', '21:00');

        $this->makeAppointment($morningAgent->id, now()->setTime(9, 30));
        $this->makeAppointment($eveningAgent->id, now()->setTime(16, 30));
        $this->makeAppointment($eveningAgent->id, now()->setTime(17, 30));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are our booking agents doing?');

        $section = $this->agentSection($this->userTurnText());

        $this->assertStringContainsString('AM Agent: 1 morning / 0 evening (1 total)', $section);
        $this->assertStringContainsString('PM Agent: 0 morning / 2 evening (2 total)', $section);
    }

    public function test_booking_outside_the_shift_window_is_not_attributed(): void
    {
        $agent = $this->makeAgent('Window Agent');
        $this->makeShiftLog($agent->id, now(), 'morning', '08:00', '14:00');

        // Before check-in and after check-out - neither should count.
        $this->makeAppointment($agent->id, now()->setTime(7, 0));
        $this->makeAppointment($agent->id, now()->setTime(15, 0));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are our booking agents doing?');

        $section = $this->agentSection($this->userTurnText());

        $this->assertStringContainsString('Window Agent: 0 morning / 0 evening (0 total)', $section);
    }

    public function test_booking_created_by_a_manager_is_not_attributed_to_the_agent(): void
    {
        $agent = $this->makeAgent('Delegated Agent');
        $manager = $this->makeManager('Manager Example');
        $this->makeShiftLog($agent->id, now(), 'morning', '08:00', '14:00');

        // Created by the manager, not the agent, even though it happens
        // during the agent's own logged shift window.
        $this->makeAppointment($manager->id, now()->setTime(9, 0));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are our booking agents doing?');

        $section = $this->agentSection($this->userTurnText());

        $this->assertStringContainsString('Delegated Agent: 0 morning / 0 evening (0 total)', $section);
    }

    public function test_agent_with_shift_log_but_zero_bookings_is_represented_accurately(): void
    {
        $agent = $this->makeAgent('Zero Booking Agent');
        $this->makeShiftLog($agent->id, now(), 'morning', '08:00', '12:00');

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are our booking agents doing?');

        $section = $this->agentSection($this->userTurnText());

        $this->assertStringContainsString('Zero Booking Agent: 0 morning / 0 evening (0 total), 4.0 logged shift hours', $section);
    }

    public function test_no_shift_logs_reports_plainly(): void
    {
        $this->fakeSuccess();
        (new NovaAIService())->ask('How are our booking agents doing?');

        $section = $this->agentSection($this->userTurnText());

        $this->assertStringContainsString('No agent shift logs', $section);
    }

    public function test_bookings_out_of_period_are_excluded(): void
    {
        $agent = $this->makeAgent('Cross Period Agent');
        $this->makeShiftLog($agent->id, now(), 'morning', '08:00', '14:00');
        $this->makeAppointment($agent->id, now()->setTime(9, 0));

        // A shift log + booking from last month must not leak into this
        // month-to-date period.
        $lastMonth = now()->subMonthNoOverflow();
        $this->makeShiftLog($agent->id, $lastMonth, 'morning', '08:00', '14:00');
        $this->makeAppointment($agent->id, $lastMonth->copy()->setTime(9, 0));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are our booking agents doing?');

        $section = $this->agentSection($this->userTurnText());

        $this->assertStringContainsString('Cross Period Agent: 1 morning / 0 evening (1 total)', $section);
    }

    public function test_snapshot_uses_booking_terminology_not_sale_terminology(): void
    {
        $agent = $this->makeAgent('Terminology Agent');
        $this->makeShiftLog($agent->id, now(), 'morning', '08:00', '14:00');
        $this->makeAppointment($agent->id, now()->setTime(9, 0));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are our booking agents doing?');

        $section = $this->agentSection($this->userTurnText());

        $this->assertStringContainsString('bookings', $section);
        $this->assertStringNotContainsString('sale', strtolower($section));
    }

    public function test_no_customer_pii_in_the_agent_section(): void
    {
        $agent = $this->makeAgent('Privacy Check Agent');
        $this->makeShiftLog($agent->id, now(), 'morning', '08:00', '14:00');
        $this->makeAppointment($agent->id, now()->setTime(9, 0));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are our booking agents doing?');

        $section = $this->agentSection($this->userTurnText());

        $this->assertStringNotContainsString('Test Customer', $section);
        $this->assertStringNotContainsString('+97400000000', $section);
        // The agent's own name is fine - an internal management metric.
        $this->assertStringContainsString('Privacy Check Agent', $section);
    }

    public function test_agent_performance_data_is_in_the_user_turn_not_the_system_instruction(): void
    {
        $agent = $this->makeAgent('Placement Agent');
        $this->makeShiftLog($agent->id, now(), 'morning', '08:00', '14:00');
        $this->makeAppointment($agent->id, now()->setTime(9, 0));

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are our booking agents doing?');

        $systemText = $this->systemInstructionText();

        $this->assertStringNotContainsString('BOOKING AGENT PERFORMANCE', $systemText);
        $this->assertStringNotContainsString('Placement Agent', $systemText);
    }

    public function test_system_instruction_carries_the_agent_evidence_caveats(): void
    {
        $this->fakeSuccess();
        (new NovaAIService())->ask('How are our booking agents doing?');

        $systemText = $this->systemInstructionText();
        $normalized = preg_replace('/\s+/', ' ', $systemText);

        $this->assertStringContainsStringIgnoringCase('no per-agent target in the CRM', $normalized);
        $this->assertStringContainsStringIgnoringCase('personally converted', $normalized);
    }
}
