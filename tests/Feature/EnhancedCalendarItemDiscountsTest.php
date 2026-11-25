<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\AppointmentPriceOverride;
use App\Models\AppointmentService;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceFreebiePackage;
use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EnhancedCalendarItemDiscountsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $areeba;
    private Staff $specialist;
    private Service $hairColor;
    private Service $keratin;
    private Service $blowdry;
    private Service $manicure;
    private Combo $bridalCombo;
    private ServiceFreebiePackage $freebiePkg;
    private Customer $customer;
    private Carbon $targetDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->targetDate = Carbon::parse('2026-11-25', 'Asia/Qatar')->startOfDay();
        Carbon::setTestNow($this->targetDate->copy()->setTime(10, 0));

        $this->admin = User::factory()->create([
            'name' => 'Salon Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->areeba = User::factory()->create([
            'name' => 'Areeba Khan',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => ['bookings' => 'edit', 'calendar' => 'edit'],
        ]);

        $this->hairColor = Service::create([
            'name' => 'Advance hair color (10-14)',
            'price' => 299.00,
            'duration' => 90,
        ]);
        $this->keratin = Service::create([
            'name' => 'Keratin Treatment',
            'price' => 450.00,
            'duration' => 120,
        ]);
        $this->blowdry = Service::create([
            'name' => 'Blowdry',
            'price' => 80.00,
            'duration' => 30,
        ]);
        $this->manicure = Service::create([
            'name' => 'Manicure',
            'price' => 70.00,
            'duration' => 45,
        ]);

        $this->bridalCombo = Combo::create([
            'name' => 'Bridal Glow Package',
            'price' => 200.00,
            'quantity_included' => 2,
            'validity_days' => 30,
            'is_active' => true,
        ]);
        $this->bridalCombo->services()->sync([$this->blowdry->id, $this->manicure->id]);

        $this->freebiePkg = ServiceFreebiePackage::create([
            'name' => 'Keratin + 2 Free Mini Services',
            'anchor_service_id' => $this->keratin->id,
            'price' => 450.00,
            'free_choices_count' => 2,
            'validity_days' => 14,
            'is_active' => true,
        ]);
        $this->freebiePkg->eligibleServices()->sync([$this->blowdry->id, $this->manicure->id]);

        $this->specialist = Staff::create([
            'name' => 'Sara Specialist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->specialist->services()->sync([
            $this->hairColor->id,
            $this->keratin->id,
            $this->blowdry->id,
            $this->manicure->id,
        ]);

        $pattern = ShiftPattern::create([
            'staff_id' => $this->specialist->id,
            'repeat_frequency' => 'weekly',
            'start_date' => $this->targetDate->copy()->subWeek()->toDateString(),
            'end_date' => null,
        ]);
        ShiftBlock::create([
            'shift_pattern_id' => $pattern->id,
            'day_of_week' => $this->targetDate->dayOfWeek,
            'start_time' => '10:00:00',
            'end_time' => '22:00:00',
        ]);

        $this->customer = Customer::create([
            'name' => 'Lina Discount Client',
            'phone' => '+97455008811',
            'branch' => 'old_airport',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_standalone_service_fixed_discount_syncs_across_drawer_checkout_finance_and_ad_lead_attribution(): void
    {
        $adEntry = AdLeadEntry::create([
            'date' => $this->targetDate->toDateString(),
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'category' => 'Hair Color',
            'status' => 'pending',
            'ticket_amount' => 0,
            'old_airport_amount' => 0,
            'wakrah_amount' => 0,
            'created_by' => $this->admin->id,
        ]);

        // Book 299.00 QAR service at 250.00 QAR (auto fixed discount -49.00 QAR)
        $res = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'branch' => 'old_airport',
            'booking_date' => $this->targetDate->toDateString(),
            'appointment_datetime' => $this->targetDate->toDateString() . ' 13:00:00',
            'booking_agent_id' => (string) $this->areeba->id,
            'staff_id' => $this->specialist->id,
            'service_name' => [$this->hairColor->name],
            'service_price' => [250.00],
            'service_discount_type' => ['fixed'],
            'service_discount_value' => [49.00],
            'service_discount_reason' => ['Agent promo / loyal client'],
            'service_staff' => [$this->specialist->id],
            'price' => 250.00,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.price', 250)
            ->assertJsonPath('appointment.original_price', 299)
            ->assertJsonPath('appointment.discount_amount', 49);

        $appointment = Appointment::findOrFail($res->json('appointment.id'));
        $line = $appointment->appointmentServices()->firstOrFail();

        $this->assertEquals(299.00, (float) $line->original_price);
        $this->assertEquals(250.00, (float) $line->price);
        $this->assertEquals(250.00, (float) $line->final_price); // No double-discount!
        $this->assertSame('fixed', $line->discount_type);
        $this->assertEquals(49.00, (float) $line->discount_value);
        $this->assertEquals(49.00, (float) $line->discount_amount);
        $this->assertSame('Agent promo / loyal client', $line->discount_reason);

        // Audit override logged
        $this->assertDatabaseHas('appointment_price_overrides', [
            'appointment_id' => $appointment->id,
            'appointment_service_id' => $line->id,
            'original_price' => 299.00,
            'new_price' => 250.00,
            'discount_amount' => 49.00,
            'discount_reason' => 'Agent promo / loyal client',
        ]);

        // 14-day Ad Lead revenue attribution reflects discounted net total (250.00, NOT 299.00)
        $adEntry->refresh();
        $this->assertEquals(250.00, (float) $adEntry->old_airport_amount);

        // Drawer endpoint returns crisp breakdown
        $this->actingAs($this->admin)->getJson(route('appointments.show', $appointment))
            ->assertOk()
            ->assertJsonPath('price', 250)
            ->assertJsonPath('original_price', 299)
            ->assertJsonPath('discount_amount', 49)
            ->assertJsonPath('services.0.original_price', 299)
            ->assertJsonPath('services.0.price', 250)
            ->assertJsonPath('services.0.final_price', 250)
            ->assertJsonPath('services.0.discount_type', 'fixed')
            ->assertJsonPath('services.0.discount_value', 49)
            ->assertJsonPath('services.0.discount_amount', 49)
            ->assertJsonPath('services.0.discount_reason', 'Agent promo / loyal client');

        // Checkout page pre-loads net 250.00 QAR with -49.00 QAR discount breakdown and zero double-discounting
        $this->actingAs($this->admin)->get(route('appointments.revenue.payment', $appointment))
            ->assertOk()
            ->assertViewHas('servicesTotal', 250.0)
            ->assertViewHas('serviceDiscountTotal', 49.0)
            ->assertSee('299.00 QAR')
            ->assertSee('250.00 QAR')
            ->assertSee('49.00 QAR')
            ->assertSee('Agent promo / loyal client');

        // Completing checkout at 250.00 QAR saves net Sale & SaleItem with preserved discount breakdown
        $payRes = $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $appointment), [
            'payments' => ['cash' => 250.00],
        ]);
        $payRes->assertRedirect();

        $sale = Sale::where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertEquals(250.00, (float) $sale->services_total);
        $this->assertEquals(0.00, (float) $sale->discount_amount); // Checkout-level discount untouched
        $this->assertEquals(250.00, (float) $sale->total_amount);

        $saleItem = SaleItem::where('sale_id', $sale->id)->where('type', 'service')->firstOrFail();
        $this->assertEquals(250.00, (float) $saleItem->price);
        $this->assertEquals(299.00, (float) $saleItem->original_price);
        $this->assertEquals(49.00, (float) $saleItem->discount_amount);
        $this->assertEquals(250.00, (float) $saleItem->total);

        $adEntry->refresh();
        $this->assertEquals(250.00, (float) $adEntry->old_airport_amount);

        // Post-checkout edits remain strictly locked
        $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appointment), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'branch' => 'old_airport',
            'booking_date' => $this->targetDate->toDateString(),
            'appointment_datetime' => $this->targetDate->toDateString() . ' 13:00:00',
            'booking_agent_id' => (string) $this->areeba->id,
            'staff_id' => $this->specialist->id,
            'service_name' => [$this->hairColor->name],
            'service_price' => [200.00],
            'service_staff' => [$this->specialist->id],
        ])->assertStatus(422);

        $this->actingAs($this->admin)->patchJson(route('appointments.services.update', [$appointment, $line]), [
            'service_id' => $this->hairColor->id,
            'price' => 200.00,
            'duration' => 90,
            'start_time' => $this->targetDate->toDateString() . 'T13:00',
            'staff_id' => $this->specialist->id,
            'discount_type' => 'fixed',
            'discount_value' => 99,
        ])->assertStatus(422);
    }

    public function test_direct_lower_final_price_auto_detects_fixed_discount_and_raised_price_adjusts_base(): void
    {
        // Lower direct final price without explicit discount_type -> auto fixed discount
        $resLower = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'branch' => 'old_airport',
            'booking_date' => $this->targetDate->toDateString(),
            'appointment_datetime' => $this->targetDate->toDateString() . ' 11:00:00',
            'booking_agent_id' => 'salon_staff',
            'staff_id' => $this->specialist->id,
            'service_name' => [$this->hairColor->name],
            'service_price' => [250.00],
            'service_staff' => [$this->specialist->id],
        ])->assertStatus(201);

        $lineLower = AppointmentService::where('appointment_id', $resLower->json('appointment.id'))->firstOrFail();
        $this->assertEquals(299.00, (float) $lineLower->original_price);
        $this->assertEquals(250.00, (float) $lineLower->price);
        $this->assertSame('fixed', $lineLower->discount_type);
        $this->assertEquals(49.00, (float) $lineLower->discount_value);
        $this->assertEquals(49.00, (float) $lineLower->discount_amount);

        // Raised price above catalog (350.00 vs 299.00) -> adjusted base price with 0 discount
        $resRaised = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'branch' => 'old_airport',
            'booking_date' => $this->targetDate->toDateString(),
            'appointment_datetime' => $this->targetDate->toDateString() . ' 15:00:00',
            'booking_agent_id' => 'salon_staff',
            'staff_id' => $this->specialist->id,
            'service_name' => [$this->hairColor->name],
            'service_price' => [350.00],
            'service_staff' => [$this->specialist->id],
        ])->assertStatus(201);

        $lineRaised = AppointmentService::where('appointment_id', $resRaised->json('appointment.id'))->firstOrFail();
        $this->assertEquals(350.00, (float) $lineRaised->original_price);
        $this->assertEquals(350.00, (float) $lineRaised->price);
        $this->assertNull($lineRaised->discount_type);
        $this->assertEquals(0.00, (float) $lineRaised->discount_value);
        $this->assertEquals(0.00, (float) $lineRaised->discount_amount);
    }

    public function test_service_plus_freebies_percent_discount_persists_edits_and_updates_ad_attribution(): void
    {
        $adEntry = AdLeadEntry::create([
            'date' => $this->targetDate->toDateString(),
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'category' => 'Keratin Offer',
            'status' => 'pending',
            'ticket_amount' => 0,
            'old_airport_amount' => 0,
            'wakrah_amount' => 0,
            'created_by' => $this->admin->id,
        ]);

        // Book Service + Freebies (450.00 QAR) with 10% discount -> 405.00 QAR (-45.00 QAR)
        $res = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'branch' => 'old_airport',
            'booking_date' => $this->targetDate->toDateString(),
            'appointment_datetime' => $this->targetDate->toDateString() . ' 12:00:00',
            'booking_agent_id' => (string) $this->areeba->id,
            'staff_id' => $this->specialist->id,
            'freebie_packages' => [[
                'package_id' => $this->freebiePkg->id,
                'anchor_staff_id' => $this->specialist->id,
                'discount_type' => 'percent',
                'discount_value' => 10,
                'price' => 405.00,
                'discount_reason' => 'VIP 10% promo',
                'slots' => [
                    ['mode' => 'same_day', 'service_id' => $this->blowdry->id, 'staff_id' => $this->specialist->id],
                    ['mode' => 'save_for_later', 'service_id' => ''],
                ],
            ]],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('appointment.price', 405)
            ->assertJsonPath('appointment.original_price', 450)
            ->assertJsonPath('appointment.discount_amount', 45);

        $appointment = Appointment::findOrFail($res->json('appointment.id'));
        $anchorLine = $appointment->appointmentServices()->where('is_freebie_redemption', false)->firstOrFail();
        $freebieLine = $appointment->appointmentServices()->where('is_freebie_redemption', true)->firstOrFail();

        $this->assertEquals(450.00, (float) $anchorLine->original_price);
        $this->assertEquals(405.00, (float) $anchorLine->price);
        $this->assertEquals(405.00, (float) $anchorLine->final_price);
        $this->assertSame('percent', $anchorLine->discount_type);
        $this->assertEquals(10.00, (float) $anchorLine->discount_value);
        $this->assertEquals(45.00, (float) $anchorLine->discount_amount);
        $this->assertSame('VIP 10% promo', $anchorLine->discount_reason);

        // Same-day freebie stays 0.00 QAR and does not inflate discount_amount
        $this->assertEquals(0.00, (float) $freebieLine->final_price);
        $this->assertEquals(0.00, (float) $freebieLine->discount_amount);

        // 14-day Ad Lead attribution synced to 405.00
        $adEntry->refresh();
        $this->assertEquals(405.00, (float) $adEntry->old_airport_amount);

        // Drawer & Edit hydration payload includes freebie package discount details
        $this->actingAs($this->admin)->getJson(route('appointments.show', $appointment))
            ->assertOk()
            ->assertJsonPath('freebie_packages.0.catalog_price', 450)
            ->assertJsonPath('freebie_packages.0.original_price', 450)
            ->assertJsonPath('freebie_packages.0.price', 405)
            ->assertJsonPath('freebie_packages.0.discount_type', 'percent')
            ->assertJsonPath('freebie_packages.0.discount_value', 10)
            ->assertJsonPath('freebie_packages.0.discount_amount', 45)
            ->assertJsonPath('freebie_packages.0.discount_reason', 'VIP 10% promo');

        // Edit via Change / Edit Booking -> switch to 50.00 QAR fixed discount (400.00 QAR net)
        $editRes = $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appointment), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'branch' => 'old_airport',
            'booking_date' => $this->targetDate->toDateString(),
            'appointment_datetime' => $this->targetDate->toDateString() . ' 12:00:00',
            'booking_agent_id' => (string) $this->areeba->id,
            'staff_id' => $this->specialist->id,
            'freebie_packages' => [[
                'package_id' => $this->freebiePkg->id,
                'anchor_staff_id' => $this->specialist->id,
                'discount_type' => 'fixed',
                'discount_value' => 50,
                'price' => 400.00,
                'discount_reason' => 'Updated flat discount',
                'slots' => [
                    ['mode' => 'same_day', 'service_id' => $this->blowdry->id, 'staff_id' => $this->specialist->id],
                    ['mode' => 'save_for_later', 'service_id' => ''],
                ],
            ]],
        ]);

        $editRes->assertOk()
            ->assertJsonPath('appointment.price', 400)
            ->assertJsonPath('appointment.original_price', 450)
            ->assertJsonPath('appointment.discount_amount', 50);

        $adEntry->refresh();
        $this->assertEquals(400.00, (float) $adEntry->old_airport_amount);

        // Edit again clearing discount -> back to 450.00 QAR
        $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appointment), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'branch' => 'old_airport',
            'booking_date' => $this->targetDate->toDateString(),
            'appointment_datetime' => $this->targetDate->toDateString() . ' 12:00:00',
            'booking_agent_id' => (string) $this->areeba->id,
            'staff_id' => $this->specialist->id,
            'freebie_packages' => [[
                'package_id' => $this->freebiePkg->id,
                'anchor_staff_id' => $this->specialist->id,
                'discount_type' => '',
                'discount_value' => 0,
                'price' => 450.00,
                'discount_reason' => '',
                'slots' => [
                    ['mode' => 'same_day', 'service_id' => $this->blowdry->id, 'staff_id' => $this->specialist->id],
                    ['mode' => 'save_for_later', 'service_id' => ''],
                ],
            ]],
        ])->assertOk()
            ->assertJsonPath('appointment.price', 450)
            ->assertJsonPath('appointment.discount_amount', 0);

        $adEntry->refresh();
        $this->assertEquals(450.00, (float) $adEntry->old_airport_amount);
    }

    public function test_server_validation_rejects_invalid_discounts_recomputes_tampered_totals_and_leaves_combos_untouched(): void
    {
        $basePayload = [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'branch' => 'old_airport',
            'booking_date' => $this->targetDate->toDateString(),
            'appointment_datetime' => $this->targetDate->toDateString() . ' 14:00:00',
            'booking_agent_id' => 'salon_staff',
            'staff_id' => $this->specialist->id,
            'service_name' => [$this->hairColor->name],
            'service_staff' => [$this->specialist->id],
        ];

        // 1. Negative discount rejected (422)
        $this->actingAs($this->admin)->postJson(route('appointments.store'), array_merge($basePayload, [
            'service_discount_type' => ['fixed'],
            'service_discount_value' => [-10],
        ]))->assertStatus(422);

        // 2. Percent > 100% rejected (422)
        $this->actingAs($this->admin)->postJson(route('appointments.store'), array_merge($basePayload, [
            'service_discount_type' => ['percent'],
            'service_discount_value' => [105],
        ]))->assertStatus(422);

        // 3. Fixed discount > catalog price rejected (422)
        $this->actingAs($this->admin)->postJson(route('appointments.store'), array_merge($basePayload, [
            'service_discount_type' => ['fixed'],
            'service_discount_value' => [350],
        ]))->assertStatus(422);

        // 4. Reason > 150 chars rejected (422)
        $this->actingAs($this->admin)->postJson(route('appointments.store'), array_merge($basePayload, [
            'service_discount_type' => ['fixed'],
            'service_discount_value' => [20],
            'service_discount_reason' => [str_repeat('A', 151)],
        ]))->assertStatus(422);

        // 5. Service + Freebies percent > 100% rejected (422)
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'branch' => 'old_airport',
            'booking_date' => $this->targetDate->toDateString(),
            'appointment_datetime' => $this->targetDate->toDateString() . ' 14:00:00',
            'booking_agent_id' => 'salon_staff',
            'staff_id' => $this->specialist->id,
            'freebie_packages' => [[
                'package_id' => $this->freebiePkg->id,
                'anchor_staff_id' => $this->specialist->id,
                'discount_type' => 'percent',
                'discount_value' => 120,
                'slots' => [
                    ['mode' => 'save_for_later', 'service_id' => ''],
                    ['mode' => 'save_for_later', 'service_id' => ''],
                ],
            ]],
        ])->assertStatus(422);

        // 6. Tampered client totals are ignored; server recomputes 10% off 299.00 = 269.10
        $tamperedRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), array_merge($basePayload, [
            'service_price' => [1.00],
            'service_discount_type' => ['percent'],
            'service_discount_value' => [10],
            'price' => 1.00,
        ]))->assertStatus(201);

        $this->assertEquals(269.10, (float) $tamperedRes->json('appointment.price'));
        $this->assertEquals(299.00, (float) $tamperedRes->json('appointment.original_price'));
        $this->assertEquals(29.90, (float) $tamperedRes->json('appointment.discount_amount'));

        // 7. Combo package remains untouched with locked price and zero discount
        $comboRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'branch' => 'old_airport',
            'booking_date' => $this->targetDate->toDateString(),
            'appointment_datetime' => $this->targetDate->toDateString() . ' 17:00:00',
            'booking_agent_id' => 'salon_staff',
            'staff_id' => $this->specialist->id,
            'packages' => [[
                'combo_id' => $this->bridalCombo->id,
                'immediate_service_ids' => [$this->blowdry->id, $this->manicure->id],
                'service_staff' => [
                    $this->blowdry->id => $this->specialist->id,
                    $this->manicure->id => $this->specialist->id,
                ],
            ]],
        ])->assertStatus(201);

        $this->assertEquals(200.00, (float) $comboRes->json('appointment.price'));
        $this->assertEquals(200.00, (float) $comboRes->json('appointment.original_price'));
        $this->assertEquals(0.00, (float) $comboRes->json('appointment.discount_amount'));
    }
}
