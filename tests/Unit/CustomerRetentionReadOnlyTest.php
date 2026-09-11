<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Sale;
use App\NovaAI\Services\NovaAIService;
use App\Models\ClientPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * NOVA READ-ONLY INTEGRATION RULE guard (see app/NovaAI/README.md) for
 * Stage 3E. The direction must stay:
 *
 *   CRM customer data / ClientMaintenancePlanner -> READ -> Nova
 *
 * never the reverse.
 */
class CustomerRetentionReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_controller_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/CustomerController.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_customer_model_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Models/Customer.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_client_maintenance_planner_has_no_awareness_of_nova(): void
    {
        $source = file_get_contents(app_path('Support/ClientMaintenancePlanner.php'));

        $this->assertStringNotContainsString('NovaAI', $source);
        $this->assertStringNotContainsString('Nova', $source);
    }

    public function test_nova_customer_retention_analytics_declares_its_read_only_sources(): void
    {
        $source = file_get_contents(app_path('NovaAI/Support/CustomerRetentionAnalytics.php'));

        $this->assertStringContainsString('ClientMaintenancePlanner', $source);
    }

    public function test_nova_customer_retention_section_makes_zero_writes_to_customer_data(): void
    {
        config(['services.gemini.key' => 'test-gemini-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        $customer = Customer::create(['name' => 'Read Only Check', 'phone' => '+97499999999']);
        $appointment = Appointment::create([
            'customer_name' => 'Read Only Check',
            'phone' => '+97499999999',
            'customer_id' => $customer->id,
            'appointment_datetime' => now()->subDays(10),
            'service_name' => 'Test Service',
            'branch' => 'old_airport',
            'status' => 'completed',
        ]);
        $sale = Sale::create([
            'customer_id' => $customer->id,
            'branch' => 'old_airport',
            'services_total' => 200,
            'products_total' => 0,
            'total_amount' => 200,
        ]);
        $combo = Combo::create(['name' => 'Test Combo', 'price' => 200, 'quantity_included' => 2, 'validity_days' => 30]);
        $package = ClientPackage::create([
            'customer_id' => $customer->id,
            'combo_id' => $combo->id,
            'combo_name' => 'Test Combo',
            'price_paid' => 200,
            'quantity_included' => 2,
            'purchased_at' => now()->subDays(10),
            'expires_at' => now()->addDays(20),
            'status' => 'active',
        ]);

        $customerBefore = Customer::find($customer->id)->getAttributes();
        $appointmentBefore = Appointment::find($appointment->id)->getAttributes();
        $saleBefore = Sale::find($sale->id)->getAttributes();
        $packageBefore = ClientPackage::find($package->id)->getAttributes();

        $countsBefore = [Customer::count(), Appointment::count(), Sale::count(), ClientPackage::count()];

        (new NovaAIService())->ask('How is customer retention looking?');

        $this->assertSame($countsBefore, [Customer::count(), Appointment::count(), Sale::count(), ClientPackage::count()]);
        $this->assertSame($customerBefore, Customer::find($customer->id)->getAttributes());
        $this->assertSame($appointmentBefore, Appointment::find($appointment->id)->getAttributes());
        $this->assertSame($saleBefore, Sale::find($sale->id)->getAttributes());
        $this->assertSame($packageBefore, ClientPackage::find($package->id)->getAttributes());
    }
}
