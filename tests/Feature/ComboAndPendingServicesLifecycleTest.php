<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentPriceOverride;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\ClientPackageService;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ComboAndPendingServicesLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin-combos@laleen.test',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createFourServiceComboFixture(): array
    {
        $hairCat = ServiceCategory::create(['name' => 'Hair', 'color' => '#d98f83']);
        $nailCat = ServiceCategory::create(['name' => 'Nails', 'color' => '#8ea88a']);

        $svc1 = Service::create([
            'name' => 'Signature Blowdry',
            'category' => 'Hair',
            'category_id' => $hairCat->id,
            'price' => 80,
            'duration' => 45,
        ]);
        $svc2 = Service::create([
            'name' => 'Caviar Hair Mask',
            'category' => 'Hair',
            'category_id' => $hairCat->id,
            'price' => 70,
            'duration' => 30,
        ]);
        $svc3 = Service::create([
            'name' => 'Gel Manicure',
            'category' => 'Nails',
            'category_id' => $nailCat->id,
            'price' => 60,
            'duration' => 45,
        ]);
        $svc4 = Service::create([
            'name' => 'Classic Pedicure',
            'category' => 'Nails',
            'category_id' => $nailCat->id,
            'price' => 60,
            'duration' => 45,
        ]);

        // Create combo via ComboController with blank validity_days to verify default = 7
        $resp = $this->actingAs($this->admin)->postJson(route('combos.store'), [
            'name' => 'Bridal Glow 4-Session Combo',
            'price' => 180,
            'quantity_included' => 4,
            'validity_days' => null,
            'status' => 'active',
            'service_ids' => [$svc1->id, $svc2->id, $svc3->id, $svc4->id],
        ]);
        $resp->assertSuccessful();

        $combo = Combo::findOrFail($resp->json('combo.id'));
        $this->assertSame(7, (int) $combo->validity_days);

        $stylistA = Staff::create([
            'name' => 'Stylist Amina',
            'role' => 'Hair Stylist',
            'branch' => 'old_airport',
            'bookable' => true,
        ]);
        $stylistA->services()->sync([$svc1->id, $svc2->id]);

        $stylistB = Staff::create([
            'name' => 'Stylist Bella',
            'role' => 'Nail Specialist',
            'branch' => 'wakrah',
            'bookable' => true,
        ]);
        $stylistB->services()->sync([$svc3->id]);

        $stylistC = Staff::create([
            'name' => 'Stylist Carla',
            'role' => 'Pedicure Specialist',
            'branch' => 'wakrah',
            'bookable' => true,
        ]);
        $stylistC->services()->sync([$svc4->id]);

        $customer = Customer::create([
            'name' => 'Fatima Al Kuwari',
            'phone' => '+97455102030',
        ]);

        return compact('combo', 'svc1', 'svc2', 'svc3', 'svc4', 'stylistA', 'stylistB', 'stylistC', 'customer');
    }

    public function test_advance_booking_starts_7_day_expiry_at_visit_1_checkout_with_zero_fake_discounts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 11:00:00', 'Asia/Qatar'));

        $f = $this->createFourServiceComboFixture();
        $combo = $f['combo'];
        $customer = $f['customer'];

        // Book Visit 1 10 days in advance (2026-11-11 14:00) with 2 immediate services and 2 pending
        $storeResp = $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'appointment_datetime' => '2026-11-11T14:00',
            'booking_date' => '2026-11-11',
            'branch' => 'old_airport',
            'staff_id' => $f['stylistA']->id,
            'packages' => [
                1 => [
                    'combo_id' => $combo->id,
                    'immediate_service_ids' => [$f['svc1']->id, $f['svc2']->id],
                    'service_staff' => [
                        $f['svc1']->id => $f['stylistA']->id,
                        $f['svc2']->id => $f['stylistA']->id,
                    ],
                ],
            ],
        ]);
        $storeResp->assertRedirect();

        $visit1 = Appointment::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $pkg = ClientPackage::where('appointment_id', $visit1->id)->firstOrFail();

        // Pre-payment state: sale_id, purchased_at, and expires_at MUST all be null!
        $this->assertNull($pkg->sale_id);
        $this->assertNull($pkg->purchased_at);
        $this->assertNull($pkg->expires_at);
        $this->assertSame(7, (int) $pkg->validity_days);
        $this->assertSame('active', $pkg->status);
        $this->assertFalse($pkg->is_expired);

        // Unpaid package must NOT unlock redemptions for unrelated appointments yet
        $this->assertCount(0, $customer->fresh()->redeemablePackageServices());

        // Client profile must render cleanly with "Starts at Visit 1 checkout" badge (no null format crash)
        $this->actingAs($this->admin)
            ->get(route('customers.show', $customer->id))
            ->assertOk()
            ->assertSee('Starts at Visit 1 checkout (7 days)');

        // Fast-forward 9 days (day before Visit 1): package must NOT expire!
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Asia/Qatar'));
        $expiredCount = ClientPackage::expireDue();
        $this->assertSame(0, $expiredCount);
        $this->artisan('packages:expire')->assertExitCode(0);
        $this->assertSame('active', $pkg->fresh()->status);
        $this->assertNull($pkg->fresh()->expires_at);

        // Arrive at Visit 1 (2026-11-11 15:30 Doha time) and check out 180 QAR cash
        Carbon::setTestNow(Carbon::parse('2026-11-11 15:30:00', 'Asia/Qatar'));

        $this->actingAs($this->admin)
            ->get(route('appointments.revenue.payment', $visit1->id))
            ->assertOk()
            ->assertViewHas('serviceDiscountTotal', 0.0);

        $payResp = $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $visit1->id), [
            'payments' => ['cash' => 180],
        ]);
        $payResp->assertRedirect(route('appointments.revenue.index'));

        $pkg->refresh();
        $visit1Sale = Sale::where('appointment_id', $visit1->id)->firstOrFail();

        // Countdown starts strictly at Visit 1 payment: 2026-11-11 + 7 days = 2026-11-18 23:59:59 Doha time
        $this->assertSame($visit1Sale->id, (int) $pkg->sale_id);
        $this->assertNotNull($pkg->purchased_at);
        $this->assertNotNull($pkg->expires_at);
        $this->assertSame(
            '2026-11-18 23:59:59',
            $pkg->expires_at->copy()->timezone('Asia/Qatar')->format('Y-m-d H:i:s')
        );
        $this->assertSame('active', $pkg->status);
        $this->assertSame(2, $pkg->remaining_count);

        // 2 pending slots are now unlocked for Visit 2
        $redeemable = $customer->fresh()->redeemablePackageServices();
        $this->assertCount(2, $redeemable);
        $this->assertSame(2, $redeemable[0]['remaining_count']);

        // ZERO fake discounts or price overrides
        $this->assertSame(0, AppointmentPriceOverride::count());
        foreach ($visit1->appointmentServices()->get() as $line) {
            $this->assertEquals(0.0, (float) $line->discount_amount);
            $this->assertEquals(0.0, (float) $line->final_price);
        }
        foreach (SaleItem::where('sale_id', $visit1Sale->id)->get() as $si) {
            $this->assertEquals(0.0, (float) $si->discount_amount);
        }

        // Verify Finance /revenue: 180 QAR credited to old_airport, 0 discounts
        $finResp = $this->actingAs($this->admin)->get(route('appointments.revenue.index', [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
        ]));
        $finResp->assertOk();
        $finResp->assertViewHas('grossSales', 180.0);
        $finResp->assertViewHas('totalDiscounts', 0.0);
        $finResp->assertViewHas('serviceLevelDiscounts', 0.0);

        $branchBreakdown = collect($finResp->viewData('branchBreakdown'))->keyBy('key');
        $this->assertEquals(180.0, (float) $branchBreakdown['old_airport']['sales']);
        $this->assertEquals(0.0, (float) $branchBreakdown['wakrah']['sales']);
    }

    public function test_multi_stylist_visit_2_redemption_and_equal_per_slot_staff_revenue_split(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 13:00:00', 'Asia/Qatar'));

        $f = $this->createFourServiceComboFixture();
        $combo = $f['combo'];
        $customer = $f['customer'];

        // Visit 1 at Old Airport: Stylist A performs 2 services; client pays 180 QAR
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'appointment_datetime' => '2026-11-11T14:00',
            'booking_date' => '2026-11-11',
            'branch' => 'old_airport',
            'staff_id' => $f['stylistA']->id,
            'packages' => [
                1 => [
                    'combo_id' => $combo->id,
                    'immediate_service_ids' => [$f['svc1']->id, $f['svc2']->id],
                    'service_staff' => [
                        $f['svc1']->id => $f['stylistA']->id,
                        $f['svc2']->id => $f['stylistA']->id,
                    ],
                ],
            ],
        ])->assertRedirect();

        $visit1 = Appointment::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $visit1->id), [
            'payments' => ['cash' => 180],
        ])->assertRedirect(route('appointments.revenue.index'));

        $pkg = ClientPackage::where('appointment_id', $visit1->id)->firstOrFail();
        $this->assertEquals(45.0, $pkg->per_slot_share);

        // Visit 2 on 2026-11-14 at Wakrah: redeem svc3 (Stylist B) + svc4 (Stylist C) in ONE multi-stylist booking!
        Carbon::setTestNow(Carbon::parse('2026-11-14 14:00:00', 'Asia/Qatar'));
        $token3 = "{$pkg->id}:{$f['svc3']->id}";
        $token4 = "{$pkg->id}:{$f['svc4']->id}";

        $visit2StoreResp = $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'appointment_datetime' => '2026-11-14T15:00',
            'booking_date' => '2026-11-14',
            'branch' => 'wakrah',
            // Notice: top-level staff_id omitted (hidden behind multi-stylist redemption rows)
            'redeem_service_ids' => [$token3, $token4],
            'redeem_service_staff' => [
                $token3 => $f['stylistB']->id,
                $token4 => $f['stylistC']->id,
            ],
        ]);
        $visit2StoreResp->assertRedirect();

        $visit2 = Appointment::where('customer_id', $customer->id)->where('id', '!=', $visit1->id)->latest('id')->firstOrFail();
        $visit2Lines = $visit2->appointmentServices()->orderBy('id')->get();
        $this->assertCount(2, $visit2Lines);
        $this->assertSame($f['stylistB']->id, (int) $visit2Lines[0]->staff_id);
        $this->assertSame($f['stylistC']->id, (int) $visit2Lines[1]->staff_id);

        // Checkout Visit 2 for 0.00 QAR
        $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $visit2->id), [
            'payments' => ['cash' => 0],
        ])->assertRedirect(route('appointments.revenue.index'));

        $pkg->refresh();
        $this->assertSame('completed', $pkg->status);
        $this->assertSame(0, $pkg->remaining_count);

        // 1. Unfiltered Salon / Branch P&L stays strictly 180.00 QAR on Visit 1's branch (no double counting!)
        $overallFin = $this->actingAs($this->admin)->get(route('appointments.revenue.index', [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
        ]));
        $overallFin->assertOk();
        $overallFin->assertViewHas('grossSales', 180.0);
        $overallFin->assertViewHas('totalDiscounts', 0.0);
        $branches = collect($overallFin->viewData('branchBreakdown'))->keyBy('key');
        $this->assertEquals(180.0, (float) $branches['old_airport']['sales']);
        $this->assertEquals(0.0, (float) $branches['wakrah']['sales']);

        // 2. Equal per-slot Staff Revenue attribution (180 / 4 = 45.00 QAR per slot):
        // Stylist A performed 2 slots on Visit 1 -> 90.00 QAR
        $finA = $this->actingAs($this->admin)->get(route('appointments.revenue.index', [
            'from' => '2026-11-01',
            'to' => '2026-11-30',
            'staff_id' => $f['stylistA']->id,
        ]));
        $finA->assertOk()->assertViewHas('grossSales', 90.0);

        // Stylist B performed 1 slot on Visit 2 -> 45.00 QAR on Visit 2's checkout date (2026-11-14)
        $finB = $this->actingAs($this->admin)->get(route('appointments.revenue.index', [
            'from' => '2026-11-14',
            'to' => '2026-11-14',
            'staff_id' => $f['stylistB']->id,
        ]));
        $finB->assertOk()->assertViewHas('grossSales', 45.0);

        // Stylist C performed 1 slot on Visit 2 -> 45.00 QAR on Visit 2's checkout date (2026-11-14)
        $finC = $this->actingAs($this->admin)->get(route('appointments.revenue.index', [
            'from' => '2026-11-14',
            'to' => '2026-11-14',
            'staff_id' => $f['stylistC']->id,
        ]));
        $finC->assertOk()->assertViewHas('grossSales', 45.0);
    }

    public function test_cancellation_restores_slots_distinct_service_guard_and_visit_1_downstream_cancel_block(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 13:00:00', 'Asia/Qatar'));

        $f = $this->createFourServiceComboFixture();
        $combo = $f['combo'];
        $customer = $f['customer'];

        // Visit 1 booked + paid (redeeming svc1 & svc2)
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'appointment_datetime' => '2026-11-11T14:00',
            'booking_date' => '2026-11-11',
            'branch' => 'old_airport',
            'staff_id' => $f['stylistA']->id,
            'packages' => [
                1 => [
                    'combo_id' => $combo->id,
                    'immediate_service_ids' => [$f['svc1']->id, $f['svc2']->id],
                ],
            ],
        ])->assertRedirect();

        $visit1 = Appointment::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $visit1->id), [
            'payments' => ['cash' => 180],
        ])->assertRedirect(route('appointments.revenue.index'));

        $pkg = ClientPackage::where('appointment_id', $visit1->id)->firstOrFail();

        // Guard 1: Repeating an already-redeemed service (svc1) on Visit 2 MUST fail validation
        $dupResp = $this->from(route('appointments.calendar'))
            ->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'customer_name' => $customer->name,
                'phone' => $customer->phone,
                'appointment_datetime' => '2026-11-13T14:00',
                'booking_date' => '2026-11-13',
                'branch' => 'old_airport',
                'staff_id' => $f['stylistA']->id,
                'redeem_service_ids' => ["{$pkg->id}:{$f['svc1']->id}"],
            ]);
        $dupResp->assertRedirect(route('appointments.calendar'));
        $dupResp->assertSessionHas('error');
        $this->assertSame(2, $pkg->fresh()->remaining_count);

        // Book valid Visit 2 redeeming svc3 & svc4
        $token3 = "{$pkg->id}:{$f['svc3']->id}";
        $token4 = "{$pkg->id}:{$f['svc4']->id}";
        $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'appointment_datetime' => '2026-11-13T15:00',
            'booking_date' => '2026-11-13',
            'branch' => 'wakrah',
            'redeem_service_ids' => [$token3, $token4],
            'redeem_service_staff' => [
                $token3 => $f['stylistB']->id,
                $token4 => $f['stylistC']->id,
            ],
        ])->assertRedirect();

        $visit2 = Appointment::where('customer_id', $customer->id)->where('id', '!=', $visit1->id)->latest('id')->firstOrFail();
        $this->assertSame('completed', $pkg->fresh()->status);
        $this->assertSame(0, $pkg->fresh()->remaining_count);

        // Guard 2: Swapping a Visit 2 redeemed line to an already-redeemed service or out-of-pool service is blocked (422)
        $outOfPoolSvc = Service::create(['name' => 'Bridal Makeup', 'category' => 'Makeup', 'price' => 500, 'duration' => 60]);
        $visit2FirstLine = $visit2->appointmentServices()->orderBy('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->patchJson(route('appointments.services.update', [$visit2->id, $visit2FirstLine->id]), [
                'service_id' => $outOfPoolSvc->id,
                'price' => 500,
                'duration' => 60,
                'start_time' => '2026-11-13T15:00',
                'staff_id' => null,
            ])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->patchJson(route('appointments.services.update', [$visit2->id, $visit2FirstLine->id]), [
                'service_id' => $f['svc1']->id,
                'price' => 80,
                'duration' => 45,
                'start_time' => '2026-11-13T15:00',
                'staff_id' => $f['stylistA']->id,
            ])
            ->assertStatus(422);

        // Guard 3: Cancelling or deleting Visit 1 while Visit 2 has active redemptions MUST be blocked!
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $visit1->id), ['status' => 'cancelled'])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->deleteJson(route('appointments.destroy', $visit1->id))
            ->assertStatus(422);

        // Cancelling Visit 2 before expiry restores the 2 pending slots back to 'active'!
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $visit2->id), ['status' => 'cancelled'])
            ->assertOk();

        $pkg->refresh();
        $this->assertSame('active', $pkg->status);
        $this->assertSame(2, $pkg->remaining_count);
        $this->assertSame(2, $customer->fresh()->pending_package_service_count);
    }

    public function test_admin_only_package_override_enforces_403_for_non_admin_and_reactivates_expired_package(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 10:00:00', 'Asia/Qatar'));

        $f = $this->createFourServiceComboFixture();
        $combo = $f['combo'];
        $customer = $f['customer'];

        $sale = Sale::create([
            'customer_id' => $customer->id,
            'branch' => 'old_airport',
            'services_total' => 0,
            'products_total' => 0,
            'packages_total' => 180,
            'discount_amount' => 0,
            'tip_amount' => 0,
            'total_amount' => 180,
        ]);

        // Create an expired package that still has 2 unused slots (2 of 4 redeemed)
        $pkg = ClientPackage::create([
            'customer_id' => $customer->id,
            'combo_id' => $combo->id,
            'sale_id' => $sale->id,
            'combo_name' => $combo->name,
            'price_paid' => 180,
            'quantity_included' => 4,
            'validity_days' => 7,
            'purchased_at' => Carbon::parse('2026-11-01 12:00:00', 'Asia/Qatar'),
            'expires_at' => Carbon::parse('2026-11-08 23:59:59', 'Asia/Qatar'),
            'status' => 'active',
        ]);

        ClientPackageService::create([
            'client_package_id' => $pkg->id,
            'service_id' => $f['svc1']->id,
            'service_name' => $f['svc1']->name,
            'status' => 'redeemed',
            'redeemed_at' => Carbon::parse('2026-11-01 12:00:00', 'Asia/Qatar'),
        ]);
        ClientPackageService::create([
            'client_package_id' => $pkg->id,
            'service_id' => $f['svc2']->id,
            'service_name' => $f['svc2']->name,
            'status' => 'redeemed',
            'redeemed_at' => Carbon::parse('2026-11-01 12:30:00', 'Asia/Qatar'),
        ]);

        ClientPackage::expireDue($customer->id);
        $pkg->refresh();
        $this->assertSame('expired', $pkg->status);
        $this->assertFalse($pkg->canRedeem());
        $this->assertCount(0, $customer->fresh()->redeemablePackageServices());

        // Non-admin user MUST get 403 Forbidden and NEVER see "Manage / Extend"
        $agent = User::factory()->create([
            'role' => 'agent',
            'email' => 'agent-combos@laleen.test',
            'is_active' => true,
            'permissions' => ['clients' => 'edit', 'bookings' => 'edit'],
        ]);

        $this->actingAs($agent)
            ->get(route('customers.show', $customer->id))
            ->assertOk()
            ->assertDontSee('Manage / Extend');

        $this->actingAs($agent)
            ->patch(route('customers.packages.update', [$customer->id, $pkg->id]), [
                'expires_at' => '2026-11-20',
                'quantity_included' => 4,
            ])
            ->assertForbidden();

        // Admin cannot reduce quantity_included below redeemed_count (2)
        $this->actingAs($this->admin)
            ->patchJson(route('customers.packages.update', [$customer->id, $pkg->id]), [
                'expires_at' => '2026-11-20',
                'quantity_included' => 1,
            ])
            ->assertStatus(422);

        // Admin extends expiry by +7 days -> automatically reactivates 'expired' -> 'active' and restores redeemability!
        $this->actingAs($this->admin)
            ->get(route('customers.show', $customer->id))
            ->assertOk()
            ->assertSee('Manage / Extend');

        $this->actingAs($this->admin)
            ->patch(route('customers.packages.update', [$customer->id, $pkg->id]), [
                'expires_at' => '2026-11-20',
                'quantity_included' => 4,
            ])
            ->assertRedirect();

        $pkg->refresh();
        $this->assertSame('active', $pkg->status);
        $this->assertTrue($pkg->canRedeem());
        $this->assertSame(
            '2026-11-20 23:59:59',
            $pkg->expires_at->copy()->timezone('Asia/Qatar')->format('Y-m-d H:i:s')
        );
        $this->assertCount(2, $customer->fresh()->redeemablePackageServices());
    }
}
