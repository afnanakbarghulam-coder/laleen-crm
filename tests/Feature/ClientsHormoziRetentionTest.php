<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientsHormoziRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'hormozi-admin@laleen.test',
            'is_active' => true,
        ]);
    }

    public function test_four_cash_buckets_filter_bar_accurately_filters_segments_and_excludes_upcoming_appointments(): void
    {
        // 1. 🔥 2nd-Visit Danger Zone: 1 completed visit 25 days ago, 0 upcoming
        $secondVisitClient = Customer::create([
            'name' => 'Aisha SecondVisit',
            'phone' => '55100001',
        ]);
        Appointment::create([
            'customer_id' => $secondVisitClient->id,
            'customer_name' => $secondVisitClient->name,
            'phone' => $secondVisitClient->phone,
            'service_name' => 'Signature Blowdry',
            'appointment_datetime' => now()->subDays(25),
            'booking_date' => now()->subDays(25)->toDateString(),
            'start_time' => '14:00:00',
            'status' => 'completed',
            'branch' => 'old_airport',
            'price' => 150,
        ]);

        // Excluded from 2nd-visit zone because she already has an upcoming appointment tomorrow
        $bookedSecondVisitClient = Customer::create([
            'name' => 'Booked SecondVisit',
            'phone' => '55100002',
        ]);
        Appointment::create([
            'customer_id' => $bookedSecondVisitClient->id,
            'customer_name' => $bookedSecondVisitClient->name,
            'phone' => $bookedSecondVisitClient->phone,
            'service_name' => 'Signature Blowdry',
            'appointment_datetime' => now()->subDays(25),
            'booking_date' => now()->subDays(25)->toDateString(),
            'start_time' => '14:00:00',
            'status' => 'completed',
            'branch' => 'old_airport',
            'price' => 150,
        ]);
        Appointment::create([
            'customer_id' => $bookedSecondVisitClient->id,
            'customer_name' => $bookedSecondVisitClient->name,
            'phone' => $bookedSecondVisitClient->phone,
            'service_name' => 'Signature Blowdry',
            'appointment_datetime' => now()->addDays(2),
            'booking_date' => now()->addDays(2)->toDateString(),
            'start_time' => '16:00:00',
            'status' => 'pending',
            'branch' => 'old_airport',
            'price' => 150,
        ]);

        // 2. ⚠️ Slipping VIPs: 2 completed visits, last completed visit 55 days ago, 0 upcoming
        $slippingVipClient = Customer::create([
            'name' => 'Fatima SlippingVIP',
            'phone' => '55100003',
        ]);
        Appointment::create([
            'customer_id' => $slippingVipClient->id,
            'customer_name' => $slippingVipClient->name,
            'phone' => $slippingVipClient->phone,
            'service_name' => 'Keratin Treatment',
            'appointment_datetime' => now()->subDays(90),
            'booking_date' => now()->subDays(90)->toDateString(),
            'start_time' => '12:00:00',
            'status' => 'completed',
            'branch' => 'old_airport',
            'price' => 450,
        ]);
        Appointment::create([
            'customer_id' => $slippingVipClient->id,
            'customer_name' => $slippingVipClient->name,
            'phone' => $slippingVipClient->phone,
            'service_name' => 'Keratin Treatment',
            'appointment_datetime' => now()->subDays(55),
            'booking_date' => now()->subDays(55)->toDateString(),
            'start_time' => '12:00:00',
            'status' => 'completed',
            'branch' => 'old_airport',
            'price' => 450,
        ]);

        // 3. 🎟️ Package Renewal / Expiring: active ClientPackage expiring within 10 days (<= 21 days)
        $packageClient = Customer::create([
            'name' => 'Hessa PackageRenewal',
            'phone' => '55100004',
        ]);
        $combo = Combo::create([
            'name' => 'Royal Blowdry Pack',
            'price' => 500,
            'quantity_included' => 4,
            'validity_days' => 30,
            'status' => 'active',
        ]);
        $sale = Sale::create([
            'customer_id' => $packageClient->id,
            'branch' => 'old_airport',
            'services_total' => 0,
            'products_total' => 0,
            'packages_total' => 500,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 500,
        ]);
        ClientPackage::create([
            'customer_id' => $packageClient->id,
            'combo_id' => $combo->id,
            'sale_id' => $sale->id,
            'combo_name' => $combo->name,
            'price_paid' => 500,
            'quantity_included' => 4,
            'purchased_at' => now()->subDays(20),
            'expires_at' => now()->addDays(10),
            'status' => 'active',
        ]);

        // 4. 💎 Idle Loyalty Credit: loyalty_points >= 30 and no completed visit in last 30 days
        $idlePointsClient = Customer::create([
            'name' => 'Latifa IdlePoints',
            'phone' => '55100005',
            'loyalty_points' => 85,
        ]);

        // Verify second_visit_zone filter
        $respSecond = $this->actingAs($this->admin)
            ->get(route('customers.index', ['segment' => 'second_visit_zone']))
            ->assertOk();
        $respSecond->assertSee('Aisha SecondVisit');
        $respSecond->assertDontSee('Booked SecondVisit');
        $respSecond->assertDontSee('Fatima SlippingVIP');

        // Verify bleeding_vips filter
        $respVip = $this->actingAs($this->admin)
            ->get(route('customers.index', ['segment' => 'bleeding_vips']))
            ->assertOk();
        $respVip->assertSee('Fatima SlippingVIP');
        $respVip->assertDontSee('Aisha SecondVisit');
        $respVip->assertDontSee('Booked SecondVisit');

        // Verify package_renewal filter
        $respPkg = $this->actingAs($this->admin)
            ->get(route('customers.index', ['segment' => 'package_renewal']))
            ->assertOk();
        $respPkg->assertSee('Hessa PackageRenewal');
        $respPkg->assertDontSee('Aisha SecondVisit');

        // Verify idle_points filter
        $respIdle = $this->actingAs($this->admin)
            ->get(route('customers.index', ['segment' => 'idle_points']))
            ->assertOk();
        $respIdle->assertSee('Latifa IdlePoints');
        $respIdle->assertDontSee('Booked SecondVisit');
        $respIdle->assertSee('Every client in your database.', false);
        $respIdle->assertSee('Has 30+ points (QAR discount) unused for 30+ days.', false);

        // Verify WhatsApp & Rebook buttons are removed, and ✨ AI Script renders ONLY on retention tabs
        foreach ([$respSecond, $respVip, $respPkg, $respIdle] as $retentionResp) {
            $retentionResp->assertSee('✨ AI Script', false);
            $retentionResp->assertDontSee('bxl-whatsapp', false);
            $retentionResp->assertDontSee('bx-calendar-plus', false);
        }

        $respAll = $this->actingAs($this->admin)
            ->get(route('customers.index', ['segment' => 'all']))
            ->assertOk();
        $respAll->assertDontSee('✨ AI Script', false);
        $respAll->assertDontSee('bxl-whatsapp', false);
        $respAll->assertDontSee('bx-calendar-plus', false);
        $respAll->assertSee('Clients ranked from highest total spend to lowest.', false);
    }

    public function test_top_ltv_segment_sorts_clients_from_highest_net_spend_to_lowest(): void
    {
        $lowClient = Customer::create([
            'name' => 'Alpha LowSpend',
            'phone' => '55900001',
        ]);
        Sale::create([
            'customer_id' => $lowClient->id,
            'branch' => 'old_airport',
            'services_total' => 120,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 20,
            'tip_amount' => 15,
            'total_amount' => 115,
        ]);

        $highClient = Customer::create([
            'name' => 'Zara HighSpend',
            'phone' => '55900002',
        ]);
        Sale::create([
            'customer_id' => $highClient->id,
            'branch' => 'old_airport',
            'services_total' => 800,
            'products_total' => 150,
            'packages_total' => 0,
            'discount_amount' => 50,
            'tip_amount' => 0,
            'total_amount' => 900,
        ]);

        $midClient = Customer::create([
            'name' => 'Mariam MidSpend',
            'phone' => '55900003',
        ]);
        Sale::create([
            'customer_id' => $midClient->id,
            'branch' => 'old_airport',
            'services_total' => 450,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 450,
        ]);

        $respLtv = $this->actingAs($this->admin)
            ->get(route('customers.index', ['segment' => 'ltv']))
            ->assertOk();

        $respLtv->assertSeeInOrder([
            'Zara HighSpend',
            'Mariam MidSpend',
            'Alpha LowSpend',
        ]);
        $respLtv->assertDontSee('✨ AI Script', false);
        $respLtv->assertDontSee('bxl-whatsapp', false);
        $respLtv->assertDontSee('bx-calendar-plus', false);
    }

    public function test_customer_show_renders_ascension_and_cross_sell_intelligence_card(): void
    {
        $hairCat = ServiceCategory::create(['name' => 'Hair Rituals', 'color' => '#3f8cff', 'sort_order' => 10]);
        $facialCat = ServiceCategory::create(['name' => 'Facial & Skin', 'color' => '#2bb673', 'sort_order' => 11]);

        $hairService = Service::create([
            'name' => 'Caviar Hair Mask',
            'category_id' => $hairCat->id,
            'price' => 280,
            'duration' => 60,
        ]);
        $facialService = Service::create([
            'name' => 'Express Glow Facial',
            'category_id' => $facialCat->id,
            'price' => 220,
            'duration' => 45,
        ]);

        $combo = Combo::create([
            'name' => 'Caviar 4-Session VIP Bundle',
            'price' => 900,
            'quantity_included' => 4,
            'validity_days' => 60,
            'status' => 'active',
        ]);
        $combo->services()->sync([$hairService->id]);

        $staff = Staff::create([
            'name' => 'Sara Senior Stylist',
            'first_name' => 'Sara',
            'branch' => 'old_airport',
            'bookable' => true,
            'availability_status' => 'present',
        ]);

        $customer = Customer::create([
            'name' => 'Moza AscensionClient',
            'phone' => '55200001',
        ]);

        // 2 completed visits for Caviar Hair Mask, 0 visits in Facial & Skin
        foreach ([35, 15] as $daysAgo) {
            $dt = now()->subDays($daysAgo)->setTime(13, 0);
            $appt = Appointment::create([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'phone' => $customer->phone,
                'service_name' => 'Caviar Hair Mask',
                'appointment_datetime' => $dt,
                'booking_date' => $dt->toDateString(),
                'start_time' => '13:00:00',
                'status' => 'completed',
                'branch' => 'old_airport',
                'staff_id' => $staff->id,
                'price' => 280,
            ]);
            AppointmentService::create([
                'appointment_id' => $appt->id,
                'service_id' => $hairService->id,
                'staff_id' => $staff->id,
                'name' => 'Caviar Hair Mask',
                'price' => 280,
                'original_price' => 280,
                'duration' => 60,
                'start_time' => $dt,
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->get(route('customers.show', $customer))
            ->assertOk();

        $response->assertSee('Hormozi LTV & Ascension Playbook', false);
        $response->assertSee('Caviar 4-Session VIP Bundle');
        $response->assertSee('Facial & Skin');
        $response->assertSee('Rebook Favorite Visit');
    }

    public function test_ai_retention_script_endpoint_returns_short_script_in_english_and_arabic(): void
    {
        $customer = Customer::create([
            'name' => 'Noora Al Thani',
            'phone' => '55300001',
            'loyalty_points' => 60,
        ]);

        $enResponse = $this->actingAs($this->admin)
            ->postJson(route('customers.ai-retention-script', $customer), [
                'service_name' => 'HydraFacial',
                'days_overdue' => 12,
                'last_staff_name' => 'Elena',
                'branch' => 'old_airport',
                'mode' => 'maintenance',
                'language' => 'en',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'language' => 'en',
                'mode' => 'maintenance',
            ]);

        $enScript = $enResponse->json('script');
        $this->assertNotEmpty($enScript);
        $this->assertStringContainsString('HydraFacial', $enScript);
        $this->assertLessThanOrEqual(35, str_word_count($enScript));

        $arResponse = $this->actingAs($this->admin)
            ->postJson(route('customers.ai-retention-script', $customer), [
                'service_name' => 'HydraFacial',
                'days_overdue' => 12,
                'last_staff_name' => 'Elena',
                'branch' => 'old_airport',
                'mode' => 'vip_winback',
                'language' => 'ar',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'language' => 'ar',
                'mode' => 'vip_winback',
            ]);

        $arScript = $arResponse->json('script');
        $this->assertNotEmpty($arScript);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $arScript);

        $idleEnResponse = $this->actingAs($this->admin)
            ->postJson(route('customers.ai-retention-script', $customer), [
                'service_name' => 'HydraFacial',
                'days_overdue' => 35,
                'last_staff_name' => 'Elena',
                'branch' => 'old_airport',
                'mode' => 'idle_points',
                'language' => 'en',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'language' => 'en',
                'mode' => 'idle_points',
            ]);
        $idleEnScript = $idleEnResponse->json('script');
        $this->assertStringContainsString('60', $idleEnScript);

        $idleArResponse = $this->actingAs($this->admin)
            ->postJson(route('customers.ai-retention-script', $customer), [
                'service_name' => 'HydraFacial',
                'days_overdue' => 35,
                'last_staff_name' => 'Elena',
                'branch' => 'old_airport',
                'mode' => 'idle_points',
                'language' => 'ar',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'language' => 'ar',
                'mode' => 'idle_points',
            ]);
        $idleArScript = $idleArResponse->json('script');
        $this->assertStringContainsString('60', $idleArScript);
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $idleArScript);
    }

    public function test_sync_retention_leads_pushes_overdue_and_slipping_vips_without_duplicating_open_leads(): void
    {
        $trackedService = Service::create([
            'name' => 'Russian Manicure',
            'price' => 200,
            'duration' => 60,
            'rebooking_interval_days' => 21,
        ]);

        // Client 1: Overdue on Russian Manicure (visited 30 days ago, interval 21 days -> 9 days overdue)
        $overdueClient = Customer::create([
            'name' => 'Maha Overdue',
            'phone' => '55400001',
        ]);
        $pastDate = now()->subDays(30)->setTime(11, 0);
        $appt1 = Appointment::create([
            'customer_id' => $overdueClient->id,
            'customer_name' => $overdueClient->name,
            'phone' => $overdueClient->phone,
            'service_name' => 'Russian Manicure',
            'appointment_datetime' => $pastDate,
            'booking_date' => $pastDate->toDateString(),
            'start_time' => '11:00:00',
            'status' => 'completed',
            'branch' => 'old_airport',
            'price' => 200,
        ]);
        AppointmentService::create([
            'appointment_id' => $appt1->id,
            'service_id' => $trackedService->id,
            'name' => 'Russian Manicure',
            'price' => 200,
            'original_price' => 200,
            'duration' => 60,
            'start_time' => $pastDate,
        ]);

        // Client 2: Slipping VIP (2 completed visits > 45 days ago) who ALREADY has an open lead
        $slippingVip = Customer::create([
            'name' => 'Alia SlippingVIP',
            'phone' => '55400002',
        ]);
        foreach ([80, 50] as $daysAgo) {
            Appointment::create([
                'customer_id' => $slippingVip->id,
                'customer_name' => $slippingVip->name,
                'phone' => $slippingVip->phone,
                'service_name' => 'Balayage Color',
                'appointment_datetime' => now()->subDays($daysAgo),
                'booking_date' => now()->subDays($daysAgo)->toDateString(),
                'start_time' => '15:00:00',
                'status' => 'completed',
                'branch' => 'old_airport',
                'price' => 600,
            ]);
        }

        $existingLead = Lead::create([
            'customer_id' => $slippingVip->id,
            'phone' => '55400002',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['Haircut'],
            'customer_remarks' => 'Asked about weekend slots',
            'next_followup_date' => today(),
        ]);

        // Execute sync twice to verify idempotency and zero duplicate phone rows
        $this->actingAs($this->admin)
            ->post(route('customers.sync-retention-leads'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post(route('customers.sync-retention-leads'))
            ->assertRedirect();

        $this->assertEquals(1, Lead::where('phone', '55400001')->count());
        $this->assertEquals(1, Lead::where('phone', '55400002')->count());

        $mahaLead = Lead::where('phone', '55400001')->firstOrFail();
        $this->assertTrue($mahaLead->hasCategory('followup'));
        $this->assertContains('Russian Manicure', $mahaLead->service_interest);
        $this->assertTrue($mahaLead->next_followup_date->isToday());
        $this->assertStringContainsString('[Retention Radar]', (string) $mahaLead->customer_remarks);

        $existingLead->refresh();
        $this->assertTrue($existingLead->hasCategory('followup'));
        $this->assertContains('Haircut', $existingLead->service_interest);
        $this->assertContains('Balayage Color', $existingLead->service_interest);
        $this->assertTrue($existingLead->next_followup_date->isToday());
        $this->assertStringContainsString('Asked about weekend slots', (string) $existingLead->customer_remarks);
        $this->assertStringContainsString('[Retention Radar]', (string) $existingLead->customer_remarks);
    }
}
