<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\AgentShiftLog;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\AppointmentUpsell;
use App\Models\ClientPackage;
use App\Models\ClientPackageService;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\KpiAgentTargetReport;
use App\Models\Lead;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\SocialContentPlan;
use App\Models\Staff;
use App\Models\User;
use App\Support\StaffSalesAnalytics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicesProductsKpisDashboardWave5Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_service_deletion_guard_combo_protection_and_rename_propagation(): void
    {
        $svc1 = Service::create([
            'name' => 'Classic HydraFacial',
            'price' => 300,
            'duration' => 60,
        ]);
        $svc2 = Service::create([
            'name' => 'Glow Peel',
            'price' => 200,
            'duration' => 45,
        ]);
        $unusedSvc = Service::create([
            'name' => 'Unused Brow Tint',
            'price' => 80,
            'duration' => 20,
        ]);

        $combo = Combo::create([
            'name' => 'Glow Bundle',
            'price' => 400,
            'quantity_included' => 2,
            'validity_days' => 30,
            'status' => 'active',
        ]);
        $combo->services()->sync([$svc1->id, $svc2->id]);

        $customer = Customer::create([
            'name' => 'Noor Al-Thani',
            'phone' => '+97455112233',
        ]);

        $pkg = ClientPackage::create([
            'customer_id' => $customer->id,
            'combo_id' => $combo->id,
            'combo_name' => $combo->name,
            'price_paid' => 400,
            'quantity_included' => 2,
            'purchased_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ]);

        // 1. ServiceController::destroy blocks deleting a service in use by a Combo
        $this->actingAs($this->admin)
            ->delete(route('services.destroy', $svc1->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('services', ['id' => $svc1->id]);

        // Unused service can be deleted cleanly
        $this->actingAs($this->admin)
            ->delete(route('services.destroy', $unusedSvc->id))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('services', ['id' => $unusedSvc->id]);

        // 2. ComboController::update blocks removing a service or lowering quantity_included while active ClientPackage exists
        $this->actingAs($this->admin)
            ->putJson(route('combos.update', $combo->id), [
                'name' => 'Glow Bundle',
                'price' => 400,
                'quantity_included' => 1,
                'validity_days' => 30,
                'status' => 'active',
                'service_ids' => [$svc1->id], // tries to remove $svc2
            ])
            ->assertStatus(422);

        // 3. Renaming $svc1 propagates to open appointment_services, appointments.service_name, client_package_services, and open leads
        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => 'Classic HydraFacial, Glow Peel',
            'appointment_datetime' => now()->addDay()->setTime(14, 0),
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00:00',
            'branch' => 'old_airport',
            'price' => 500,
            'status' => 'confirmed',
        ]);

        $line1 = AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $svc1->id,
            'name' => 'Classic HydraFacial',
            'price' => 300,
            'original_price' => 300,
            'duration' => 60,
            'start_time' => now()->addDay()->setTime(14, 0),
        ]);
        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $svc2->id,
            'name' => 'Glow Peel',
            'price' => 200,
            'original_price' => 200,
            'duration' => 45,
            'start_time' => now()->addDay()->setTime(15, 0),
        ]);

        $pkgService = ClientPackageService::create([
            'client_package_id' => $pkg->id,
            'service_id' => $svc1->id,
            'service_name' => 'Classic HydraFacial',
            'status' => 'redeemed',
            'appointment_service_id' => $line1->id,
            'redeemed_at' => now(),
        ]);

        $lead = Lead::create([
            'phone' => '+97455998877',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['Classic HydraFacial', 'Glow Peel'],
        ]);

        $this->actingAs($this->admin)
            ->put(route('services.update', $svc1->id), [
                'name' => 'Platinum HydraFacial',
                'price' => 350,
                'duration' => 60,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Platinum HydraFacial', $line1->fresh()->name);
        $this->assertSame('Platinum HydraFacial, Glow Peel', $appointment->fresh()->service_name);
        $this->assertSame('Platinum HydraFacial', $pkgService->fresh()->service_name);
        $this->assertContains('Platinum HydraFacial', $lead->fresh()->service_interest);
    }

    public function test_retail_product_inventory_decrement_reversal_staff_attribution_and_deletion_guard(): void
    {
        $staff = Staff::create([
            'name' => 'Layla Stylist',
            'role' => 'Stylist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'is_bookable' => true,
        ]);

        $shampoo = Product::create([
            'name' => 'Kerastase Elixir',
            'sku' => 'KER-01',
            'price' => 150,
            'stock_quantity' => 10,
            'low_stock_threshold' => 3,
        ]);

        // 1. Standalone POS sale decrements stock_quantity and records staff_id on Sale and SaleItem
        $this->actingAs($this->admin)
            ->post(route('sales.store'), [
                'branch' => 'old_airport',
                'staff_id' => $staff->id,
                'products' => [
                    ['product_id' => $shampoo->id, 'quantity' => 2],
                ],
                'payments' => ['cash' => 300],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        $this->assertSame(8, $shampoo->fresh()->stock_quantity);

        $posItem = SaleItem::where('product_id', $shampoo->id)->first();
        $this->assertNotNull($posItem);
        $this->assertSame($staff->id, (int) $posItem->staff_id);

        // 2. ProductController::destroy blocks deleting a product referenced in SaleItem
        $this->actingAs($this->admin)
            ->delete(route('products.destroy', $shampoo->id))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $shampoo->id]);

        // 3. Appointment checkout with product + upsell decrements stock and reversal restores it
        $service = Service::create(['name' => 'Blowdry', 'price' => 100, 'duration' => 45]);
        $appointment = Appointment::create([
            'customer_name' => 'Dana',
            'phone' => '+97466112233',
            'staff_id' => $staff->id,
            'service_name' => 'Blowdry',
            'appointment_datetime' => now()->setTime(12, 0),
            'booking_date' => today()->toDateString(),
            'start_time' => '12:00:00',
            'branch' => 'old_airport',
            'price' => 100,
            'status' => 'in_progress',
        ]);
        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'staff_id' => $staff->id,
            'name' => 'Blowdry',
            'price' => 100,
            'original_price' => 100,
            'duration' => 45,
            'start_time' => now()->setTime(12, 0),
        ]);
        AppointmentUpsell::create([
            'appointment_id' => $appointment->id,
            'type' => 'product',
            'product_id' => $shampoo->id,
            'staff_id' => $staff->id,
            'name' => $shampoo->name,
            'amount' => 150,
            'created_by' => $this->admin->id,
        ]);

        // Checkout: 100 service + 150 upsell (1 shampoo) + 300 checkout product (2 shampoos) = 550 QAR (total 3 shampoos)
        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $appointment->id), [
                'products' => [
                    ['product_id' => $shampoo->id, 'quantity' => 2],
                ],
                'payments' => ['card' => 550],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        // Stock was 8, minus 2 (checkout line) minus 1 (upsell) = 5
        $this->assertSame(5, $shampoo->fresh()->stock_quantity);

        $checkoutItems = SaleItem::where('sale_id', $appointment->fresh()->sale->id)
            ->where('type', 'product')
            ->get();
        $this->assertCount(2, $checkoutItems);
        foreach ($checkoutItems as $item) {
            $this->assertSame($staff->id, (int) $item->staff_id);
        }

        // Reversing the appointment sale (by cancelling the booking) restores the 3 shampoos back to stock (5 + 3 = 8)
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $appointment->id), [
                'status' => 'cancelled',
            ])
            ->assertOk();

        $this->assertSame(8, $shampoo->fresh()->stock_quantity);
    }

    public function test_kpis_paid_only_staff_sales_double_shift_agent_targets_and_ad_lead_sync(): void
    {
        $staff = Staff::create([
            'name' => 'Rania',
            'role' => 'Beautician',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'is_bookable' => true,
        ]);

        // 1. StaffSalesAnalytics excludes unpaid pending upsells and includes completed upsells
        $unpaidAppt = Appointment::create([
            'customer_name' => 'Unpaid Client',
            'phone' => '+97455000001',
            'staff_id' => $staff->id,
            'service_name' => 'Facial',
            'appointment_datetime' => now(),
            'branch' => 'old_airport',
            'price' => 200,
            'status' => 'pending',
        ]);
        AppointmentUpsell::create([
            'appointment_id' => $unpaidAppt->id,
            'type' => 'service',
            'staff_id' => $staff->id,
            'name' => 'Eye Mask',
            'amount' => 120,
        ]);

        $paidAppt = Appointment::create([
            'customer_name' => 'Paid Client',
            'phone' => '+97455000002',
            'staff_id' => $staff->id,
            'service_name' => 'Facial',
            'appointment_datetime' => now(),
            'branch' => 'old_airport',
            'price' => 200,
            'status' => 'completed',
        ]);
        AppointmentUpsell::create([
            'appointment_id' => $paidAppt->id,
            'type' => 'service',
            'staff_id' => $staff->id,
            'name' => 'Collagen Mask',
            'amount' => 250,
        ]);

        $analytics = new StaffSalesAnalytics(now()->startOfMonth(), now()->endOfDay());
        $branchTotals = $analytics->totals('old_airport');
        $this->assertEquals(250.0, $branchTotals['team_total']);

        // 2. KpiAgentTargetReport counts both morning and evening shifts worked by the same agent on the same day
        $agent = User::factory()->create([
            'name' => 'Sara Agent',
            'role' => 'agent',
            'is_active' => true,
        ]);

        $todayStr = today()->toDateString();
        AgentShiftLog::create([
            'user_id' => $agent->id,
            'date' => $todayStr,
            'shift' => 'morning',
            'check_in_time' => '08:00:00',
            'check_out_time' => '13:00:00',
            'created_by' => $this->admin->id,
        ]);
        AgentShiftLog::create([
            'user_id' => $agent->id,
            'date' => $todayStr,
            'shift' => 'evening',
            'check_in_time' => '15:00:00',
            'check_out_time' => '21:00:00',
            'created_by' => $this->admin->id,
        ]);

        $morningBooking = Appointment::create([
            'customer_name' => 'Morning Client',
            'phone' => '+97455110011',
            'service_name' => 'Manicure',
            'appointment_datetime' => now()->addDay(),
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '11:00:00',
            'staff_id' => $staff->id,
            'branch' => 'old_airport',
            'price' => 100,
            'status' => 'completed',
            'booking_agent_id' => $agent->id,
            'created_by' => $agent->id,
        ]);
        $morningBooking->forceFill(['created_at' => today()->setTime(10, 30)])->saveQuietly();

        $eveningBooking = Appointment::create([
            'customer_name' => 'Evening Client',
            'phone' => '+97455110022',
            'service_name' => 'Pedicure',
            'appointment_datetime' => now()->addDay(),
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '17:00:00',
            'staff_id' => $staff->id,
            'branch' => 'old_airport',
            'price' => 120,
            'status' => 'completed',
            'booking_agent_id' => $agent->id,
            'created_by' => $agent->id,
        ]);
        $eveningBooking->forceFill(['created_at' => today()->setTime(17, 15)])->saveQuietly();

        $targetReport = new KpiAgentTargetReport([
            'date_from' => today(),
            'date_to' => today(),
        ]);
        $shifts = $targetReport->shiftStats();
        $this->assertSame(1, $shifts['morning']['bookings']);
        $this->assertSame(1, $shifts['evening']['bookings']);

        // 3. AdLeadEntryController normalizes phone via Customer::normalizePhone and syncs Lead
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => $todayStr,
                'customer_name' => 'Fatima Ad Lead',
                'country_code' => '+974',
                'phone_number' => '7712 3456',
                'category' => 'Facial',
                'ticket_amount' => 350,
                'branch' => 'old_airport',
                'remarks' => 'Booked via IG promo',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $adEntry = AdLeadEntry::first();
        $this->assertNotNull($adEntry);
        $expectedPhone = Customer::normalizePhone('+974 7712 3456');
        $this->assertSame($expectedPhone, $adEntry->phone);

        $syncedCustomer = Customer::where('phone', $expectedPhone)->first();
        $this->assertNotNull($syncedCustomer);

        $syncedLead = Lead::where('phone', $expectedPhone)->first();
        $this->assertNull($syncedLead, 'AdLeadEntryController must not auto-create rows in the separate leads table');
    }

    public function test_dashboard_reads_social_content_plan_and_renders_live_operational_pulse(): void
    {
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();

        // Create 4 posted reels + 7 posted stories for the current week (100% weekly compliance)
        foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
            SocialContentPlan::create([
                'week_start_date' => $weekStart,
                'day_of_week' => $dayName,
                'reel_content' => $idx < 4 ? 'Reel ' . ($idx + 1) : null,
                'reel_link' => $idx < 4 ? 'https://drive.google.com/reel-' . ($idx + 1) : null,
                'is_reel_posted' => $idx < 4,
                'is_reel_skipped' => $idx >= 4,
                'stories_content' => 'Daily Story ' . ($idx + 1),
                'are_stories_posted' => true,
            ]);
        }

        Appointment::create([
            'customer_name' => 'Today Pulse Client',
            'phone' => '+97455889900',
            'service_name' => 'Blowdry',
            'appointment_datetime' => now()->setTime(14, 0),
            'booking_date' => today()->toDateString(),
            'start_time' => '14:00:00',
            'branch' => 'old_airport',
            'price' => 150,
            'status' => 'confirmed',
        ]);

        Lead::create([
            'phone' => '+97455889911',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'next_followup_date' => today()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Live Today Salon Pulse');
        $response->assertSee(route('kpi.content-calendar.index'));
        $response->assertViewHas('contentMetrics', function (array $metrics) {
            return $metrics['overall'] === 100.0 && $metrics['grade'] === 'Excellent';
        });
        $response->assertViewHas('operationalPulse', function (array $pulse) {
            return $pulse['today_appointments'] === 1 && $pulse['leads_due_today'] === 1;
        });
    }
}
