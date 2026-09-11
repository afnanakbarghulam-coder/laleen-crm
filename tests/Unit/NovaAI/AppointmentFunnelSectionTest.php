<?php

namespace Tests\Unit\NovaAI;

use App\Models\Appointment;
use App\NovaAI\Services\NovaAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 3B: the appointment-funnel snapshot section. This project has no
 * isolated test database (phpunit.xml leaves DB_CONNECTION pointed at the
 * real one), so RefreshDatabase wraps every test in a transaction that
 * is always rolled back - no fake Appointment row this suite creates is
 * ever actually persisted. Time is frozen so the trailing-7-day window is
 * exact and reproducible regardless of when the suite runs.
 */
class AppointmentFunnelSectionTest extends TestCase
{
    use RefreshDatabase;

    private const GEMINI_URL_PATTERN = 'generativelanguage.googleapis.com/*';

    /** Fixed "now": trailing-7-day window is 2026-06-09 00:00:00 .. 2026-06-15 12:00:00. */
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

    private function makeAppointment(string $status, string $branch, Carbon $when): Appointment
    {
        return Appointment::create([
            'customer_name' => 'Test Customer',
            'phone' => '+97400000000',
            'appointment_datetime' => $when,
            'service_name' => 'Test Service',
            'branch' => $branch,
            'status' => $status,
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

    public function test_status_counts_are_aggregated_correctly(): void
    {
        $inWindow = Carbon::parse('2026-06-12 10:00:00');

        $this->makeAppointment('pending', 'old_airport', $inWindow);
        $this->makeAppointment('arrived', 'old_airport', $inWindow);
        $this->makeAppointment('in_progress', 'old_airport', $inWindow);
        $this->makeAppointment('completed', 'old_airport', $inWindow);
        $this->makeAppointment('completed', 'old_airport', $inWindow);
        $this->makeAppointment('no_show', 'old_airport', $inWindow);
        $this->makeAppointment('cancelled', 'old_airport', $inWindow);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are bookings looking?');

        $text = $this->userTurnText();

        $this->assertStringContainsString('APPOINTMENT FUNNEL', $text);
        $this->assertStringContainsString(
            'All branches: 7 scheduled | 2 completed | 2 arrived/in-progress | 1 cancelled | 1 no-show | 1 pending',
            $text
        );
    }

    public function test_window_includes_only_the_trailing_seven_scheduled_days(): void
    {
        // Window is [2026-06-09 00:00:00, 2026-06-15 12:00:00].
        $this->makeAppointment('completed', 'old_airport', Carbon::parse('2026-06-05 10:00:00')); // too old - excluded
        $this->makeAppointment('completed', 'old_airport', Carbon::parse('2026-06-16 10:00:00')); // future - excluded
        $this->makeAppointment('completed', 'old_airport', Carbon::parse('2026-06-09 00:00:00')); // exact start - included
        $this->makeAppointment('completed', 'old_airport', Carbon::parse('2026-06-15 12:00:00')); // exact "now" - included
        $this->makeAppointment('completed', 'old_airport', Carbon::parse('2026-06-12 10:00:00')); // mid-window - included

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are bookings looking?');

        $text = $this->userTurnText();

        $this->assertStringContainsString('All branches: 3 scheduled | 3 completed', $text);
    }

    public function test_show_rate_no_show_rate_and_cancellation_rate_use_the_correct_denominators(): void
    {
        $inWindow = Carbon::parse('2026-06-12 10:00:00');

        // 1 arrived + 1 in_progress + 2 completed = 4 showed; 1 no_show -> attendance-decision population = 5.
        $this->makeAppointment('arrived', 'old_airport', $inWindow);
        $this->makeAppointment('in_progress', 'old_airport', $inWindow);
        $this->makeAppointment('completed', 'old_airport', $inWindow);
        $this->makeAppointment('completed', 'old_airport', $inWindow);
        $this->makeAppointment('no_show', 'old_airport', $inWindow);
        // Cancelled and pending must NOT enter the show/no-show denominator, only the cancellation-rate one.
        $this->makeAppointment('cancelled', 'old_airport', $inWindow);
        $this->makeAppointment('pending', 'old_airport', $inWindow);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are bookings looking?');

        $text = $this->userTurnText();

        // total = 7, cancelled = 1 -> 1/7 = 14.3%; showed 4/5 = 80%; no_show 1/5 = 20%.
        $this->assertStringContainsString('show rate 80%, no-show rate 20%, cancellation rate 14.3%', $text);
    }

    public function test_zero_attendance_decision_population_returns_na_not_zero_percent(): void
    {
        $inWindow = Carbon::parse('2026-06-12 10:00:00');

        // Only cancelled/pending - nobody has had an attendance outcome yet.
        $this->makeAppointment('cancelled', 'old_airport', $inWindow);
        $this->makeAppointment('pending', 'old_airport', $inWindow);

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are bookings looking?');

        $text = $this->userTurnText();

        $this->assertStringContainsString('show rate N/A, no-show rate N/A, cancellation rate 50%', $text);
    }

    public function test_empty_window_reports_no_appointments_rather_than_fabricated_zero_rates(): void
    {
        // No appointments created at all in this test.
        $this->fakeSuccess();
        (new NovaAIService())->ask('How are bookings looking?');

        $text = $this->userTurnText();

        $this->assertStringContainsString('APPOINTMENT FUNNEL', $text);
        $this->assertStringContainsString('No appointments scheduled in this window.', $text);
        $this->assertStringNotContainsString('0%', $text);
    }

    public function test_branch_breakdown_uses_the_existing_branch_label_convention(): void
    {
        $inWindow = Carbon::parse('2026-06-12 10:00:00');

        $this->makeAppointment('completed', 'old_airport', $inWindow);
        $this->makeAppointment('completed', 'wakrah', $inWindow);
        $this->makeAppointment('no_show', 'wakrah', $inWindow);
        // No home_service appointments in this window at all.

        $this->fakeSuccess();
        (new NovaAIService())->ask('How are bookings looking?');

        $text = $this->userTurnText();

        $this->assertStringContainsString('Old Airport: 1 scheduled | 1 completed', $text);
        $this->assertStringContainsString('Al Wakrah: 2 scheduled | 1 completed', $text);
        // A branch with zero appointments in the window is omitted, not printed with all-zero counts.
        $this->assertStringNotContainsString('Home Service:', $text);
    }
}
