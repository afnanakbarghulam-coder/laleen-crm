<?php

namespace Tests\Unit;

use App\Models\AgentShiftLog;
use App\Models\Appointment;
use App\Models\User;
use App\NovaAI\Services\NovaAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * NOVA READ-ONLY INTEGRATION RULE guard (see app/NovaAI/README.md) for
 * Stage 3F. The direction must stay:
 *
 *   CRM agent data / KpiAgentTargetReport -> READ -> Nova
 *
 * never the reverse.
 */
class BookingAgentReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_shift_log_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Models/AgentShiftLog.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_kpi_agent_target_report_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Models/KpiAgentTargetReport.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_user_model_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Models/User.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_agent_kpi_controllers_have_no_awareness_of_nova(): void
    {
        $shiftLogSource = file_get_contents(app_path('Http/Controllers/Kpi/AgentShiftLogController.php'));
        $targetSource = file_get_contents(app_path('Http/Controllers/Kpi/AgentTargetController.php'));

        $this->assertStringNotContainsString('NovaAI', $shiftLogSource);
        $this->assertStringNotContainsString('Nova', $shiftLogSource);
        $this->assertStringNotContainsString('NovaAI', $targetSource);
        $this->assertStringNotContainsString('Nova', $targetSource);
    }

    public function test_nova_booking_agent_analytics_declares_its_read_only_source(): void
    {
        $source = file_get_contents(app_path('NovaAI/Support/BookingAgentPerformanceAnalytics.php'));

        $this->assertStringContainsString('KpiAgentTargetReport', $source);
    }

    public function test_nova_never_persists_a_kpi_agent_target_report_row(): void
    {
        // KpiAgentTargetReport is only ever constructed unsaved as a
        // calculator (matching UserController::dashboard()'s own usage) -
        // Nova must never call ::create()/save() on it, which would
        // pollute the real kpi_agent_target_reports table with a bookmark
        // row nobody asked for.
        $source = file_get_contents(app_path('NovaAI/Support/BookingAgentPerformanceAnalytics.php'));

        $this->assertStringNotContainsString('KpiAgentTargetReport::create', $source);
        $this->assertStringNotContainsString('->save()', $source);
    }

    public function test_nova_booking_agent_section_makes_zero_writes_to_agent_data(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        $agent = User::create([
            'name' => 'Read Only Agent',
            'email' => 'read.only.agent@example.test',
            'password' => 'irrelevant-for-tests',
            'role' => 'agent',
        ]);

        $shiftLog = AgentShiftLog::create([
            'user_id' => $agent->id,
            'date' => now()->toDateString(),
            'shift' => 'morning',
            'check_in_time' => '08:00',
            'check_out_time' => '14:00',
        ]);

        $appointment = new Appointment([
            'customer_name' => 'Test Customer',
            'phone' => '+97400000000',
            'appointment_datetime' => now()->setTime(9, 0),
            'service_name' => 'Test Service',
            'branch' => 'old_airport',
            'status' => 'pending',
            'created_by' => $agent->id,
        ]);
        $appointment->created_at = now()->setTime(9, 0);
        $appointment->save();

        $countsBefore = [User::count(), AgentShiftLog::count(), Appointment::count(), \App\Models\KpiAgentTargetReport::count()];
        $agentBefore = User::find($agent->id)->getAttributes();
        $shiftLogBefore = AgentShiftLog::find($shiftLog->id)->getAttributes();
        $appointmentBefore = Appointment::find($appointment->id)->getAttributes();

        (new NovaAIService())->ask('How are our booking agents doing?');

        $this->assertSame(
            $countsBefore,
            [User::count(), AgentShiftLog::count(), Appointment::count(), \App\Models\KpiAgentTargetReport::count()]
        );
        $this->assertSame($agentBefore, User::find($agent->id)->getAttributes());
        $this->assertSame($shiftLogBefore, AgentShiftLog::find($shiftLog->id)->getAttributes());
        $this->assertSame($appointmentBefore, Appointment::find($appointment->id)->getAttributes());
    }
}
