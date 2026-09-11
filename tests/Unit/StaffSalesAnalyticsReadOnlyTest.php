<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\AppointmentUpsell;
use App\Models\Staff;
use App\NovaAI\Services\NovaAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * NOVA READ-ONLY INTEGRATION RULE guard (see app/NovaAI/README.md) for
 * Stage 3D. The direction must stay:
 *
 *   CRM staff data / StaffSalesAnalytics -> READ -> Nova
 *
 * never the reverse. The source-string checks below are deliberately
 * simple structural guards; the final test is a behavioral one - it
 * proves Nova's staff-target section makes zero writes to any staff-
 * related table, which a regex on method-call source text can't reliably
 * prove once calls go through a variable (`$analytics->computedStaff()`)
 * rather than the literal class name.
 */
class StaffSalesAnalyticsReadOnlyTest extends TestCase
{
    use RefreshDatabase;


    public function test_staff_sales_analytics_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Support/StaffSalesAnalytics.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_staff_model_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Models/Staff.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_staff_sales_controller_and_kpi_view_are_untouched_by_nova(): void
    {
        $controllerSource = file_get_contents(app_path('Http/Controllers/Kpi/StaffSalesController.php'));

        $this->assertStringNotContainsString('NovaAI', $controllerSource);
        $this->assertStringNotContainsString('Nova', $controllerSource);
    }

    public function test_nova_declares_it_consumes_staff_sales_analytics(): void
    {
        $novaSource = file_get_contents(app_path('NovaAI/Services/NovaAIService.php'));

        $this->assertStringContainsString('StaffSalesAnalytics', $novaSource);
    }

    public function test_nova_staff_target_section_makes_zero_writes_to_staff_data(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        $staff = Staff::create(['name' => 'Read Only Check', 'branch' => 'old_airport']);
        $appointment = Appointment::create([
            'customer_name' => 'Test Customer',
            'phone' => '+97400000000',
            'appointment_datetime' => now(),
            'service_name' => 'Test Service',
            'branch' => 'old_airport',
            'status' => 'completed',
        ]);
        AppointmentUpsell::create([
            'appointment_id' => $appointment->id,
            'staff_id' => $staff->id,
            'type' => 'service',
            'name' => 'Test Upsell',
            'amount' => 500.0,
        ]);

        $staffBefore = Staff::find($staff->id)->getAttributes();
        $upsellBefore = AppointmentUpsell::first()->getAttributes();
        $staffCountBefore = Staff::count();
        $upsellCountBefore = AppointmentUpsell::count();

        (new NovaAIService())->ask('How is staff performance against target?');

        $this->assertSame($staffCountBefore, Staff::count(), 'Nova must not create or delete Staff rows.');
        $this->assertSame($upsellCountBefore, AppointmentUpsell::count(), 'Nova must not create or delete AppointmentUpsell rows.');
        $this->assertSame($staffBefore, Staff::find($staff->id)->getAttributes(), 'Nova must not modify an existing Staff row.');
        $this->assertSame($upsellBefore, AppointmentUpsell::first()->getAttributes(), 'Nova must not modify an existing AppointmentUpsell row.');
    }
}
