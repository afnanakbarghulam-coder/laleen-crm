<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\ClientPackageService;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PortalProductionBreakdownDrawerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $ahanaUser;
    private Staff $ahanaStaff;
    private User $aishaUser;
    private Staff $aishaStaff;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-25 14:00:00', 'Asia/Qatar'));

        $this->admin = User::factory()->create([
            'name' => 'Afnan Admin',
            'email' => 'admin.brk@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->ahanaUser = User::factory()->create([
            'name' => 'Ahana Sharma',
            'email' => 'ahana.brk@laleen.qa',
            'role' => 'salon_manager',
            'permissions' => User::defaultPermissionsForRole('salon_manager'),
            'is_active' => true,
        ]);
        $this->ahanaStaff = Staff::create([
            'name' => 'Ahana Sharma',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $this->ahanaUser->id,
        ]);

        $this->aishaUser = User::factory()->create([
            'name' => 'Aisha Specialist',
            'email' => 'aisha.brk@laleen.qa',
            'role' => 'salon_staff',
            'permissions' => User::defaultPermissionsForRole('salon_staff'),
            'is_active' => true,
        ]);
        $this->aishaStaff = Staff::create([
            'name' => 'Aisha Specialist',
            'branch' => 'old_airport',
            'is_remote' => false,
            'bookable' => true,
            'user_id' => $this->aishaUser->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_breakdown_math_reconciles_with_mtd_card_groups_by_service_and_strips_client_pii(): void
    {
        $client1 = Customer::create([
            'name' => 'Fatima Al-Thani',
            'phone' => '+97455110099',
            'email' => 'fatima.private@example.com',
            'branch' => 'old_airport',
        ]);
        $client2 = Customer::create([
            'name' => 'Noor Al-Mansoori',
            'phone' => '+97455220088',
            'email' => 'noor.private@example.com',
            'branch' => 'old_airport',
        ]);
        $client3 = Customer::create([
            'name' => 'Mariam Al-Kuwari',
            'phone' => '+97455330077',
            'email' => 'mariam.private@example.com',
            'branch' => 'old_airport',
        ]);

        $hairColor = Service::create(['name' => 'Hair Color', 'price' => 250, 'duration' => 90]);
        $keratin = Service::create(['name' => 'Keratin Treatment', 'price' => 300, 'duration' => 120]);
        $blowdry = Service::create(['name' => 'Signature Blowdry', 'price' => 100, 'duration' => 45]);
        $facial = Service::create(['name' => 'Glow Facial', 'price' => 150, 'duration' => 60]);
        $eyebrow = Service::create(['name' => 'Eyebrow Threading', 'price' => 30, 'duration' => 15]);
        $manicure = Service::create(['name' => 'Classic Manicure', 'price' => 0, 'duration' => 30]);

        $combo = Combo::create(['name' => 'Keratin + Blowdry Combo', 'price' => 300, 'status' => 'active']);
        $combo->services()->sync([$keratin->id, $blowdry->id]);

        // (a) Completed standalone DISCOUNTED service for Aisha: 250 - 50 = 200.00 QAR
        $appt1 = Appointment::create([
            'customer_id' => $client1->id,
            'customer_name' => $client1->name,
            'phone' => $client1->phone,
            'service_name' => $hairColor->name,
            'branch' => 'old_airport',
            'booking_date' => '2026-11-10',
            'start_time' => '11:00:00',
            'appointment_datetime' => '2026-11-10 11:00:00',
            'staff_id' => $this->aishaStaff->id,
            'status' => 'completed',
            'price' => 200,
            'original_price' => 250,
            'discount_amount' => 50,
            'paid_at' => Carbon::parse('2026-11-10 12:30:00', 'Asia/Qatar'),
        ]);
        AppointmentService::create([
            'appointment_id' => $appt1->id,
            'service_id' => $hairColor->id,
            'staff_id' => $this->aishaStaff->id,
            'name' => $hairColor->name,
            'price' => 200,
            'original_price' => 250,
            'discount_type' => 'fixed',
            'discount_value' => 50,
            'duration' => 90,
            'is_pending' => false,
        ]);

        // (b) Completed 2-service Combo (paid 300 QAR) SPLIT across Aisha (225.00 QAR) & Ahana (75.00 QAR)
        $appt2 = Appointment::create([
            'customer_id' => $client2->id,
            'customer_name' => $client2->name,
            'phone' => $client2->phone,
            'service_name' => $keratin->name . ', ' . $blowdry->name,
            'branch' => 'old_airport',
            'booking_date' => '2026-11-18',
            'start_time' => '13:30:00',
            'appointment_datetime' => '2026-11-18 13:30:00',
            'staff_id' => $this->aishaStaff->id,
            'combo_id' => $combo->id,
            'combo_name' => $combo->name,
            'status' => 'completed',
            'price' => 300,
            'paid_at' => Carbon::parse('2026-11-18 15:45:00', 'Asia/Qatar'),
        ]);
        $sale2 = Sale::create([
            'appointment_id' => $appt2->id,
            'customer_id' => $client2->id,
            'staff_id' => $this->aishaStaff->id,
            'created_by' => $this->admin->id,
            'branch' => 'old_airport',
            'services_total' => 0,
            'products_total' => 0,
            'packages_total' => 300,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 300,
        ]);
        $pkg2 = ClientPackage::create([
            'customer_id' => $client2->id,
            'combo_id' => $combo->id,
            'sale_id' => $sale2->id,
            'appointment_id' => $appt2->id,
            'combo_name' => $combo->name,
            'price_paid' => 300,
            'original_combo_price' => 400,
            'quantity_included' => 2,
            'validity_days' => 7,
            'status' => 'completed',
        ]);
        $lineKeratin = AppointmentService::create([
            'appointment_id' => $appt2->id,
            'service_id' => $keratin->id,
            'combo_id' => $combo->id,
            'combo_name' => $combo->name,
            'client_package_id' => $pkg2->id,
            'staff_id' => $this->aishaStaff->id,
            'name' => $keratin->name,
            'price' => 0,
            'original_price' => 300,
            'duration' => 120,
            'is_pending' => false,
        ]);
        $lineBlowdry = AppointmentService::create([
            'appointment_id' => $appt2->id,
            'service_id' => $blowdry->id,
            'combo_id' => $combo->id,
            'combo_name' => $combo->name,
            'client_package_id' => $pkg2->id,
            'staff_id' => $this->ahanaStaff->id,
            'name' => $blowdry->name,
            'price' => 0,
            'original_price' => 100,
            'duration' => 45,
            'is_pending' => false,
        ]);
        ClientPackageService::create([
            'client_package_id' => $pkg2->id,
            'service_id' => $keratin->id,
            'service_name' => $keratin->name,
            'status' => 'redeemed',
            'appointment_service_id' => $lineKeratin->id,
            'sale_id' => $sale2->id,
        ]);
        ClientPackageService::create([
            'client_package_id' => $pkg2->id,
            'service_id' => $blowdry->id,
            'service_name' => $blowdry->name,
            'status' => 'redeemed',
            'appointment_service_id' => $lineBlowdry->id,
            'sale_id' => $sale2->id,
        ]);

        // (c) Completed Service + Freebies for Aisha: Anchor 150.00 QAR + Freebie 0.00 QAR
        $appt3 = Appointment::create([
            'customer_id' => $client3->id,
            'customer_name' => $client3->name,
            'phone' => $client3->phone,
            'service_name' => $facial->name . ', ' . $eyebrow->name,
            'branch' => 'old_airport',
            'booking_date' => '2026-11-22',
            'start_time' => '15:00:00',
            'appointment_datetime' => '2026-11-22 15:00:00',
            'staff_id' => $this->aishaStaff->id,
            'status' => 'completed',
            'price' => 150,
            'paid_at' => Carbon::parse('2026-11-22 16:15:00', 'Asia/Qatar'),
        ]);
        AppointmentService::create([
            'appointment_id' => $appt3->id,
            'service_id' => $facial->id,
            'staff_id' => $this->aishaStaff->id,
            'name' => $facial->name,
            'price' => 150,
            'original_price' => 150,
            'duration' => 60,
            'is_pending' => false,
        ]);
        AppointmentService::create([
            'appointment_id' => $appt3->id,
            'service_id' => $eyebrow->id,
            'staff_id' => $this->aishaStaff->id,
            'name' => $eyebrow->name,
            'price' => 0,
            'original_price' => 30,
            'is_freebie_redemption' => true,
            'duration' => 15,
            'is_pending' => false,
        ]);

        // (d) 2nd Glow Facial (150.00 QAR) + 3-way odd-penny combo (100 QAR -> 33.33 + 33.33 + 33.34 = 100.00 QAR)
        $appt4 = Appointment::create([
            'customer_id' => $client1->id,
            'customer_name' => $client1->name,
            'phone' => $client1->phone,
            'service_name' => $facial->name,
            'branch' => 'old_airport',
            'booking_date' => '2026-11-24',
            'start_time' => '14:30:00',
            'appointment_datetime' => '2026-11-24 14:30:00',
            'staff_id' => $this->aishaStaff->id,
            'status' => 'completed',
            'price' => 150,
            'paid_at' => Carbon::parse('2026-11-24 15:30:00', 'Asia/Qatar'),
        ]);
        AppointmentService::create([
            'appointment_id' => $appt4->id,
            'service_id' => $facial->id,
            'staff_id' => $this->aishaStaff->id,
            'name' => $facial->name,
            'price' => 150,
            'original_price' => 150,
            'duration' => 60,
            'is_pending' => false,
        ]);

        // 3-item equal-split combo for penny-exact reconciliation test (100 QAR / 3 = 33.33, 33.33, 33.34)
        $oddCombo = Combo::create(['name' => '3 Express Nails', 'price' => 100, 'status' => 'active']);
        $apptOdd = Appointment::create([
            'customer_id' => $client2->id,
            'customer_name' => $client2->name,
            'phone' => $client2->phone,
            'service_name' => 'Classic Manicure',
            'branch' => 'old_airport',
            'booking_date' => '2026-11-25',
            'start_time' => '10:00:00',
            'appointment_datetime' => '2026-11-25 10:00:00',
            'staff_id' => $this->aishaStaff->id,
            'combo_id' => $oddCombo->id,
            'combo_name' => $oddCombo->name,
            'status' => 'completed',
            'price' => 100,
            'paid_at' => Carbon::parse('2026-11-25 11:00:00', 'Asia/Qatar'),
        ]);
        for ($k = 0; $k < 3; $k++) {
            AppointmentService::create([
                'appointment_id' => $apptOdd->id,
                'service_id' => $manicure->id,
                'combo_id' => $oddCombo->id,
                'combo_name' => $oddCombo->name,
                'staff_id' => $this->aishaStaff->id,
                'name' => 'Classic Manicure',
                'price' => 0,
                'original_price' => 0,
                'duration' => 30,
                'is_pending' => false,
            ]);
        }

        // (e) Excluded bookings: Cancelled, No-Show, Uncompleted Future, Prior Month
        foreach ([
            ['date' => '2026-11-19', 'status' => 'cancelled', 'paid_at' => null],
            ['date' => '2026-11-20', 'status' => 'no_show', 'paid_at' => null],
            ['date' => '2026-11-28', 'status' => 'scheduled', 'paid_at' => null],
            ['date' => '2026-10-29', 'status' => 'completed', 'paid_at' => Carbon::parse('2026-10-29 14:00:00', 'Asia/Qatar')],
        ] as $ex) {
            $exAppt = Appointment::create([
                'customer_id' => $client1->id,
                'customer_name' => $client1->name,
                'phone' => $client1->phone,
                'service_name' => $hairColor->name,
                'branch' => 'old_airport',
                'booking_date' => $ex['date'],
                'start_time' => '10:00:00',
                'appointment_datetime' => $ex['date'] . ' 10:00:00',
                'staff_id' => $this->aishaStaff->id,
                'status' => $ex['status'],
                'price' => 500,
                'paid_at' => $ex['paid_at'],
            ]);
            AppointmentService::create([
                'appointment_id' => $exAppt->id,
                'service_id' => $hairColor->id,
                'staff_id' => $this->aishaStaff->id,
                'name' => $hairColor->name,
                'price' => 500,
                'original_price' => 500,
                'duration' => 60,
                'is_pending' => false,
            ]);
        }

        // 1. Verify Portal Performance page shows polished MTD cards + View Breakdown drawer trigger
        $perfResponse = $this->actingAs($this->aishaUser)
            ->get('/my-profile?tab=performance')
            ->assertOk()
            ->assertSee('MTD Service Production')
            ->assertSee('View Breakdown')
            ->assertSee('View itemized list')
            ->assertSee('orb-champagne')
            ->assertSee('luxe-card-hero')
            ->assertSee('mtdProductionDrawer')
            ->assertSee('825 QAR');

        $mtdCardSummary = $perfResponse->viewData('mtdProduction');

        // 2. Verify Self-View JSON Breakdown for Salon Staff (Aisha) — passing staff_id query MUST be ignored!
        $brkRes = $this->actingAs($this->aishaUser)
            ->getJson('/my-profile/production-breakdown?staff_id=' . $this->ahanaStaff->id)
            ->assertOk();

        $json = $brkRes->json();
        $rawBody = $brkRes->getContent();

        // Exact reconciliation: sum(items) === sum(by_service) === summary.total_revenue_qar === MTD card total (825.00)
        $this->assertSame('Aisha Specialist', $json['staff_name']);
        $this->assertFalse($json['show_client_first_name']);
        $this->assertSame(8, $json['summary']['total_services']);
        $this->assertSame(3, $json['summary']['standalone_paid_count']); // Hair Color + 2x Glow Facial
        $this->assertSame(4, $json['summary']['combo_count']); // Keratin + 3x Classic Manicure
        $this->assertSame(1, $json['summary']['freebie_count']); // Eyebrow Threading
        $this->assertSame(5, $json['summary']['combo_and_freebie_count']);
        $this->assertEquals(825.00, $json['summary']['total_revenue_qar']);
        $this->assertEquals($mtdCardSummary['total_revenue_qar'], $json['summary']['total_revenue_qar']);

        $itemsSum = round(collect($json['items'])->sum('credited_qar'), 2);
        $byServiceSum = round(collect($json['by_service'])->sum('total_qar'), 2);
        $this->assertEquals(825.00, $itemsSum);
        $this->assertEquals(825.00, $byServiceSum);

        // Verify exact row types, notes, and sublines
        $hairRow = collect($json['items'])->firstWhere('service_name', 'Hair Color');
        $this->assertSame('standalone', $hairRow['type']);
        $this->assertSame('200.00 QAR', $hairRow['formatted_price']);
        $this->assertSame('Catalog 250 QAR · Discounted', $hairRow['subline']);

        $keratinRow = collect($json['items'])->firstWhere('service_name', 'Keratin Treatment');
        $this->assertSame('combo', $keratinRow['type']);
        $this->assertSame('225.00 QAR', $keratinRow['formatted_price']);
        $this->assertSame('Part of: Keratin + Blowdry Combo', $keratinRow['subline']);
        $this->assertSame('Prorated', $keratinRow['price_note']);

        $freebieRow = collect($json['items'])->firstWhere('service_name', 'Eyebrow Threading');
        $this->assertSame('freebie', $freebieRow['type']);
        $this->assertSame('0.00 QAR', $freebieRow['formatted_price']);
        $this->assertSame('Freebie', $freebieRow['price_note']);

        // Verify By-Service grouping sorted highest revenue first
        $this->assertSame('Glow Facial', $json['by_service'][0]['service_name']);
        $this->assertSame(2, $json['by_service'][0]['count']);
        $this->assertSame('150.00 QAR', $json['by_service'][0]['formatted_avg_price']);
        $this->assertSame('300.00 QAR', $json['by_service'][0]['formatted_total']);

        $this->assertSame('Keratin Treatment', $json['by_service'][1]['service_name']);
        $this->assertSame('Hair Color', $json['by_service'][2]['service_name']);
        $this->assertSame('Classic Manicure', $json['by_service'][3]['service_name']);
        $this->assertSame('100.00 QAR', $json['by_service'][3]['formatted_total']); // 33.33 + 33.33 + 33.34 exact!
        $this->assertSame('Eyebrow Threading', $json['by_service'][4]['service_name']);
        $this->assertSame('0.00 QAR', $json['by_service'][4]['formatted_total']);

        // STRICT PRIVACY CHECKS for Salon Staff:
        // Zero phones, zero emails, zero full client names, zero other specialists' services
        foreach ($json['items'] as $row) {
            $this->assertNull($row['client_first_name']);
        }
        $this->assertStringNotContainsString('+97455110099', $rawBody);
        $this->assertStringNotContainsString('+97455220088', $rawBody);
        $this->assertStringNotContainsString('fatima.private@example.com', $rawBody);
        $this->assertStringNotContainsString('noor.private@example.com', $rawBody);
        $this->assertStringNotContainsString('Fatima', $rawBody);
        $this->assertStringNotContainsString('Al-Thani', $rawBody);
        $this->assertStringNotContainsString('Signature Blowdry', $rawBody);

        // 3. Verify Salon Manager self-view & Admin Preview include first name ONLY (still zero phone/email!)
        $ahanaRes = $this->actingAs($this->ahanaUser)
            ->getJson('/my-profile/production-breakdown')
            ->assertOk();
        $ahanaJson = $ahanaRes->json();
        $ahanaRaw = $ahanaRes->getContent();
        $this->assertTrue($ahanaJson['show_client_first_name']);
        $this->assertCount(1, $ahanaJson['items']);
        $this->assertSame('Signature Blowdry', $ahanaJson['items'][0]['service_name']);
        $this->assertSame('75.00 QAR', $ahanaJson['items'][0]['formatted_price']);
        $this->assertSame('Noor', $ahanaJson['items'][0]['client_first_name']);
        $this->assertStringNotContainsString('Al-Mansoori', $ahanaRaw);
        $this->assertStringNotContainsString('+97455220088', $ahanaRaw);
        $this->assertStringNotContainsString('noor.private@example.com', $ahanaRaw);

        $adminPreviewRes = $this->actingAs($this->admin)
            ->getJson('/staff-portal-preview/' . $this->aishaStaff->id . '/production-breakdown')
            ->assertOk();
        $adminJson = $adminPreviewRes->json();
        $adminRaw = $adminPreviewRes->getContent();
        $this->assertTrue($adminJson['show_client_first_name']);
        $this->assertEquals(825.00, $adminJson['summary']['total_revenue_qar']);
        $this->assertStringNotContainsString('+97455110099', $adminRaw);
        $this->assertStringNotContainsString('fatima.private@example.com', $adminRaw);
        $this->assertStringNotContainsString('Al-Thani', $adminRaw);
    }

    public function test_breakdown_endpoints_enforce_strict_role_and_cross_staff_403_isolation(): void
    {
        // Salon Staff cannot call Admin Preview breakdown for another staff member or even themselves
        $this->actingAs($this->aishaUser)
            ->getJson('/staff-portal-preview/' . $this->ahanaStaff->id . '/production-breakdown')
            ->assertForbidden();

        $this->actingAs($this->aishaUser)
            ->getJson('/staff-portal-preview/' . $this->aishaStaff->id . '/production-breakdown')
            ->assertForbidden();

        // Salon Manager cannot call Admin Preview breakdown for a specialist
        $this->actingAs($this->ahanaUser)
            ->getJson('/staff-portal-preview/' . $this->aishaStaff->id . '/production-breakdown')
            ->assertForbidden();

        // All 5 Remote roles get HTTP 403 on self-view breakdown AND Admin Preview breakdown
        foreach (['agent', 'sales_coordinator', 'ops_lead', 'marketing_specialist', 'social_media_editor'] as $remoteRole) {
            $remoteUser = User::factory()->create([
                'name' => 'Remote ' . $remoteRole,
                'email' => $remoteRole . '.brk@laleen.qa',
                'role' => $remoteRole,
                'permissions' => User::defaultPermissionsForRole($remoteRole),
                'is_active' => true,
            ]);
            $remoteStaff = Staff::create([
                'name' => 'Remote ' . $remoteRole,
                'branch' => null,
                'is_remote' => true,
                'bookable' => false,
                'user_id' => $remoteUser->id,
            ]);

            $this->actingAs($remoteUser)
                ->getJson('/my-profile/production-breakdown')
                ->assertForbidden();

            $this->actingAs($this->admin)
                ->getJson('/staff-portal-preview/' . $remoteStaff->id . '/production-breakdown')
                ->assertForbidden();

            // Also verify the portal Performance page hides the breakdown drawer for remote roles
            $this->actingAs($remoteUser)
                ->get('/my-profile?tab=performance')
                ->assertOk()
                ->assertDontSee('mtdProductionDrawer')
                ->assertDontSee('View Breakdown');
        }
    }
}
