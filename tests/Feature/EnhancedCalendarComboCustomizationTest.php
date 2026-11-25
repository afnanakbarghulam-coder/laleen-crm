<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\User;
use App\Support\AgentBookingQuotaCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EnhancedCalendarComboCustomizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $zoya;
    private Staff $specialist;
    private Staff $unskilledStaff;
    private Service $svc1;
    private Service $svc2;
    private Service $svc3;
    private Service $svc4;
    private Service $extraSvc;
    private Service $longExtraSvc;
    private Service $outsideSvc;
    private Combo $luxury4Combo;
    private Combo $fixedBridalCombo;
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

        $this->zoya = User::factory()->create([
            'name' => 'Zoya Ali',
            'email' => 'zoya@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => ['bookings' => 'edit', 'calendar' => 'edit', 'kpis' => 'edit'],
        ]);

        $this->svc1 = Service::create(['name' => 'Luxury Blowdry', 'price' => 60.00, 'duration' => 30]);
        $this->svc2 = Service::create(['name' => 'Luxury Manicure', 'price' => 55.00, 'duration' => 30]);
        $this->svc3 = Service::create(['name' => 'Luxury Pedicure', 'price' => 65.00, 'duration' => 30]);
        $this->svc4 = Service::create(['name' => 'Luxury Facial', 'price' => 90.00, 'duration' => 45]);
        $this->extraSvc = Service::create(['name' => 'Luxury Hair Spa', 'price' => 85.00, 'duration' => 45]);
        $this->longExtraSvc = Service::create(['name' => 'Keratin Treatment', 'price' => 400.00, 'duration' => 240]);
        $this->outsideSvc = Service::create(['name' => 'Balayage', 'price' => 320.00, 'duration' => 120]);

        // "Pick 4 from 6" eligible service pool combo
        $this->luxury4Combo = Combo::create([
            'name' => '4 Luxury Services',
            'price' => 170.00,
            'quantity_included' => 4,
            'validity_days' => 14,
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->luxury4Combo->services()->sync([
            $this->svc1->id,
            $this->svc2->id,
            $this->svc3->id,
            $this->svc4->id,
            $this->extraSvc->id,
            $this->longExtraSvc->id,
        ]);

        // Fixed "3 of 3" combo (zero extra pool services remain once all 3 are selected)
        $this->fixedBridalCombo = Combo::create([
            'name' => 'Bridal Glow Package',
            'price' => 200.00,
            'quantity_included' => 3,
            'validity_days' => 14,
            'status' => 'active',
            'is_active' => true,
        ]);
        $this->fixedBridalCombo->services()->sync([
            $this->svc1->id,
            $this->svc2->id,
            $this->svc3->id,
        ]);

        $this->specialist = Staff::create([
            'name' => 'Sara Specialist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->specialist->services()->sync([
            $this->svc1->id,
            $this->svc2->id,
            $this->svc3->id,
            $this->svc4->id,
            $this->extraSvc->id,
            $this->longExtraSvc->id,
            $this->outsideSvc->id,
        ]);

        $pattern = ShiftPattern::create([
            'staff_id' => $this->specialist->id,
            'repeat_frequency' => 'weekly',
            'start_date' => $this->targetDate->copy()->subWeek()->toDateString(),
            'end_date' => null,
        ]);
        for ($dow = 0; $dow <= 6; $dow++) {
            ShiftBlock::create([
                'shift_pattern_id' => $pattern->id,
                'day_of_week' => $dow,
                'start_time' => '10:00:00',
                'end_time' => '21:00:00',
            ]);
        }

        $this->unskilledStaff = Staff::create([
            'name' => 'Nadia Nails',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->unskilledStaff->services()->sync([$this->svc2->id]);

        $unskilledPattern = ShiftPattern::create([
            'staff_id' => $this->unskilledStaff->id,
            'repeat_frequency' => 'weekly',
            'start_date' => $this->targetDate->copy()->subWeek()->toDateString(),
            'end_date' => null,
        ]);
        for ($dow = 0; $dow <= 6; $dow++) {
            ShiftBlock::create([
                'shift_pattern_id' => $unskilledPattern->id,
                'day_of_week' => $dow,
                'start_time' => '10:00:00',
                'end_time' => '21:00:00',
            ]);
        }

        $this->customer = Customer::create([
            'name' => 'Mariam Al-Kuwari',
            'phone' => '+97455889900',
            'branch' => 'old_airport',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createCustomized5ServiceCombo(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $dateStr = $this->targetDate->toDateString();
        $allFiveIds = [
            $this->svc1->id,
            $this->svc2->id,
            $this->svc3->id,
            $this->svc4->id,
            $this->extraSvc->id,
        ];

        $packagePayload = array_merge([
            'combo_id' => $this->luxury4Combo->id,
            'immediate_service_ids' => $allFiveIds,
            'pending_service_ids' => [],
            'extra_service_ids' => [$this->extraSvc->id],
            'custom_price' => 210.00,
            'is_custom_combo' => 1,
            'combo_override_reason' => 'Added 5th luxury service',
            'custom_quantity' => 5,
            'service_staff' => [
                $this->svc1->id => $this->specialist->id,
                $this->svc2->id => $this->specialist->id,
                $this->svc3->id => $this->specialist->id,
                $this->svc4->id => $this->specialist->id,
                $this->extraSvc->id => $this->specialist->id,
            ],
        ], $overrides['package'] ?? []);

        unset($overrides['package']);

        $payload = array_merge([
            'booking_agent_id' => $this->zoya->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T13:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->specialist->id,
            'packages' => [$packagePayload],
        ], $overrides);

        return $this->actingAs($this->admin)->postJson(route('appointments.store'), $payload);
    }

    public function test_customized_5_service_combo_preserves_master_catalog_and_syncs_calendar_checkout_ad_revenue_and_agent_kpi(): void
    {
        // Attach a 14-day rolling Ad Lead for this client
        $adLead = AdLeadEntry::create([
            'date' => $this->targetDate->copy()->subDays(3)->toDateString(),
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'category' => '4 Luxury Services',
            'status' => 'pending',
            'ticket_amount' => 0,
            'created_by' => $this->admin->id,
        ]);

        $res = $this->createCustomized5ServiceCombo();
        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.is_custom_combo', true);

        $appointment = Appointment::with(['appointmentServices', 'clientPackages'])->findOrFail($res->json('appointment.id'));

        // 1. Booking-level storage & Appointment mirror
        $this->assertTrue((bool) $appointment->is_custom_combo);
        $this->assertEquals(210.00, (float) $appointment->price);
        $this->assertEquals(170.00, (float) $appointment->original_combo_price);
        $this->assertSame('Added 5th luxury service', $appointment->combo_override_reason);

        $pkg = ClientPackage::where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertTrue((bool) $pkg->is_custom_combo);
        $this->assertEquals(210.00, (float) $pkg->price_paid);
        $this->assertEquals(170.00, (float) $pkg->original_combo_price);
        $this->assertSame('Added 5th luxury service', $pkg->combo_override_reason);
        $this->assertSame(5, (int) $pkg->quantity_included);

        // 2. Five linked appointment_services rows: 4 catalog + 1 extra
        $lines = $appointment->appointmentServices()->orderBy('sort_order')->get();
        $this->assertCount(5, $lines);
        $this->assertSame(4, $lines->where('is_combo_extra', false)->count());
        $this->assertSame(1, $lines->where('is_combo_extra', true)->count());
        $extraLine = $lines->firstWhere('is_combo_extra', true);
        $this->assertSame($this->extraSvc->id, (int) $extraLine->service_id);

        // 3. Master catalog Combo & combo_services are 100% UNCHANGED
        $masterCombo = Combo::with('services')->findOrFail($this->luxury4Combo->id);
        $this->assertEquals(170.00, (float) $masterCombo->price);
        $this->assertSame(4, (int) $masterCombo->quantity_included);
        $this->assertEqualsCanonicalizing(
            [$this->svc1->id, $this->svc2->id, $this->svc3->id, $this->svc4->id, $this->extraSvc->id, $this->longExtraSvc->id],
            $masterCombo->services->pluck('id')->all()
        );

        // 4. Calendar grid renders all 5 linked blocks (1/5 .. 5/5)
        $calRes = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'branch' => 'old_airport',
            'date' => $this->targetDate->toDateString(),
            'view' => 'day',
        ]));
        $calRes->assertOk();
        $apptSlices = collect($calRes->json("appointments.{$this->specialist->id}"))
            ->where('id', $appointment->id)
            ->values();
        $this->assertCount(5, $apptSlices);
        $this->assertSame([0, 1, 2, 3, 4], $apptSlices->pluck('slice_index')->all());
        $this->assertTrue($apptSlices->every(fn ($s) => (int) $s['slice_count'] === 5 && (bool) $s['is_custom_combo'] === true));

        // 5. Appointment Drawer payload (GET /appointments/{id})
        $showRes = $this->actingAs($this->admin)->getJson(route('appointments.show', $appointment));
        $showRes->assertOk()
            ->assertJsonPath('is_custom_combo', true)
            ->assertJsonPath('price', 210)
            ->assertJsonPath('original_combo_price', 170)
            ->assertJsonPath('combo_override_reason', 'Added 5th luxury service')
            ->assertJsonPath('combo_packages.0.is_custom_combo', true)
            ->assertJsonPath('combo_packages.0.price_paid', 210)
            ->assertJsonPath('combo_packages.0.original_combo_price', 170)
            ->assertJsonPath('combo_packages.0.quantity_included', 5)
            ->assertJsonPath('combo_packages.0.combo_override_reason', 'Added 5th luxury service');
        $poolServices = collect($showRes->json('combo_packages.0.pool_services'));
        $this->assertCount(6, $poolServices);
        $this->assertTrue((bool) $poolServices->firstWhere('id', $this->extraSvc->id)['is_combo_extra']);
        $this->assertSame('unselected', $poolServices->firstWhere('id', $this->longExtraSvc->id)['state']);

        // 6. 14-day Ad Lead revenue attribution uses agreed custom price (210 QAR)
        $adLead->refresh();
        $this->assertSame('booked', $adLead->status);
        $this->assertEquals(210.00, (float) $adLead->ticket_amount);
        $this->assertEquals(210.00, (float) $adLead->old_airport_amount);

        // 7. Chat Agent KPI counts the entire 5-service customized combo as EXACTLY 1 booking
        $todayCount = AgentBookingQuotaCalculator::fetchForRange(
            [$this->zoya->id],
            $this->targetDate->toDateString(),
            $this->targetDate->toDateString()
        )->count();
        $this->assertSame(1, $todayCount);

        // 8. Checkout page displays Custom badge, 210.00 QAR, original 170.00, and exception note
        $payPage = $this->actingAs($this->admin)->get(route('appointments.revenue.payment', $appointment));
        $payPage->assertOk();
        $payPage->assertSee('4 Luxury Services');
        $payPage->assertSee('Custom');
        $payPage->assertSee('210.00 QAR');
        $payPage->assertSee('170.00');
        $payPage->assertSee('Added 5th luxury service');

        // 9. Complete Checkout at agreed 210.00 QAR (must NOT reset to 170.00)
        $paySubmit = $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $appointment), [
            'payments' => ['card' => 210.00],
        ]);
        $paySubmit->assertRedirect();

        $sale = Sale::where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertEquals(210.00, (float) $sale->packages_total);
        $this->assertEquals(210.00, (float) $sale->total_amount);

        $pkgSaleItem = SaleItem::where('sale_id', $sale->id)->where('type', 'package')->firstOrFail();
        $this->assertStringContainsString('Custom Package', $pkgSaleItem->name);
        $this->assertEquals(210.00, (float) $pkgSaleItem->price);
        $this->assertEquals(170.00, (float) $pkgSaleItem->original_price);
        $this->assertEquals(210.00, (float) $pkgSaleItem->total);

        // Post-checkout Ad Lead revenue stays synced to 210.00 QAR
        $adLead->refresh();
        $this->assertEquals(210.00, (float) $adLead->ticket_amount);
        $this->assertEquals(210.00, (float) $adLead->old_airport_amount);

        // Post-checkout mutation is blocked
        $lockedRes = $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appointment), [
            'booking_agent_id' => $this->zoya->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $this->targetDate->toDateString() . 'T13:00',
            'branch' => 'old_airport',
            'packages' => [[
                'combo_id' => $this->luxury4Combo->id,
                'immediate_service_ids' => [$this->svc1->id, $this->svc2->id],
                'custom_price' => 100.00,
            ]],
        ]);
        $lockedRes->assertStatus(422);
    }

    public function test_full_edit_can_remove_combo_services_and_update_custom_price_without_touching_master_combo(): void
    {
        $adLead = AdLeadEntry::create([
            'date' => $this->targetDate->copy()->subDays(2)->toDateString(),
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'category' => '4 Luxury Services',
            'status' => 'pending',
            'ticket_amount' => 0,
            'created_by' => $this->admin->id,
        ]);

        $createRes = $this->createCustomized5ServiceCombo();
        $createRes->assertCreated();
        $appointment = Appointment::findOrFail($createRes->json('appointment.id'));

        // Edit booking: trim down to 3 services and adjust custom price to 150.00 QAR
        $dateStr = $this->targetDate->toDateString();
        $editRes = $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appointment), [
            'booking_agent_id' => $this->zoya->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T13:00",
            'branch' => 'old_airport',
            'staff_id' => $this->specialist->id,
            'packages' => [[
                'combo_id' => $this->luxury4Combo->id,
                'immediate_service_ids' => [$this->svc1->id, $this->svc2->id, $this->svc3->id],
                'pending_service_ids' => [],
                'extra_service_ids' => [],
                'custom_price' => 150.00,
                'is_custom_combo' => 1,
                'combo_override_reason' => 'Trimmed to 3 services',
                'custom_quantity' => 3,
                'service_staff' => [
                    $this->svc1->id => $this->specialist->id,
                    $this->svc2->id => $this->specialist->id,
                    $this->svc3->id => $this->specialist->id,
                ],
            ]],
        ]);

        $editRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.is_custom_combo', true);

        $appointment->refresh();
        $this->assertTrue((bool) $appointment->is_custom_combo);
        $this->assertEquals(150.00, (float) $appointment->price);
        $this->assertEquals(170.00, (float) $appointment->original_combo_price);
        $this->assertSame('Trimmed to 3 services', $appointment->combo_override_reason);

        $lines = $appointment->appointmentServices()->where('is_pending', false)->get();
        $this->assertCount(3, $lines);

        $pkg = ClientPackage::where('appointment_id', $appointment->id)->where('status', '!=', 'cancelled')->firstOrFail();
        $this->assertEquals(150.00, (float) $pkg->price_paid);
        $this->assertSame(3, (int) $pkg->quantity_included);

        // Ad Lead updated cleanly to 150.00 QAR
        $adLead->refresh();
        $this->assertEquals(150.00, (float) $adLead->ticket_amount);
        $this->assertEquals(150.00, (float) $adLead->old_airport_amount);

        // Master combo untouched
        $masterCombo = Combo::with('services')->findOrFail($this->luxury4Combo->id);
        $this->assertEquals(170.00, (float) $masterCombo->price);
        $this->assertSame(4, (int) $masterCombo->quantity_included);
        $this->assertCount(6, $masterCombo->services);

        // Agent KPI still counts exactly 1 booking
        $this->assertSame(
            1,
            AgentBookingQuotaCalculator::fetchForRange([$this->zoya->id], $dateStr, $dateStr)->count()
        );
    }

    public function test_overtime_spillover_is_calculated_on_added_long_extra_combo_service(): void
    {
        // Shift ends at 21:00. Start at 17:00: 4 services (30+30+30+45 = 135 min -> ends 19:15)
        // + Keratin Treatment (240 min -> 19:15 to 23:15 = 135 min overtime spillover past 21:00!)
        $dateStr = $this->targetDate->toDateString();
        $res = $this->createCustomized5ServiceCombo([
            'appointment_datetime' => "{$dateStr}T17:00",
            'package' => [
                'immediate_service_ids' => [
                    $this->svc1->id,
                    $this->svc2->id,
                    $this->svc3->id,
                    $this->svc4->id,
                    $this->longExtraSvc->id,
                ],
                'extra_service_ids' => [$this->longExtraSvc->id],
                'custom_price' => 490.00,
                'combo_override_reason' => 'Added Keratin Treatment',
                'service_staff' => [
                    $this->svc1->id => $this->specialist->id,
                    $this->svc2->id => $this->specialist->id,
                    $this->svc3->id => $this->specialist->id,
                    $this->svc4->id => $this->specialist->id,
                    $this->longExtraSvc->id => $this->specialist->id,
                ],
            ],
        ]);

        $res->assertCreated();
        $appointment = Appointment::findOrFail($res->json('appointment.id'));
        $this->assertTrue((bool) $appointment->is_overtime);
        $this->assertGreaterThanOrEqual(135, (int) $appointment->overtime_minutes);
    }

    public function test_combo_customization_validation_and_safeguards_return_422(): void
    {
        // 1. Negative custom price
        $negRes = $this->createCustomized5ServiceCombo([
            'package' => ['custom_price' => -15],
        ]);
        $negRes->assertStatus(422);

        // 2. Reason > 150 chars
        $longReasonRes = $this->createCustomized5ServiceCombo([
            'package' => ['combo_override_reason' => str_repeat('A', 151)],
        ]);
        $longReasonRes->assertStatus(422);

        // 3. Missing Booking Agent
        $noAgentRes = $this->createCustomized5ServiceCombo([
            'booking_agent_id' => null,
        ]);
        $noAgentRes->assertStatus(422);

        // 4. Unskilled specialist assigned to added 5th extra service
        $unskilledRes = $this->createCustomized5ServiceCombo([
            'package' => [
                'service_staff' => [
                    $this->svc1->id => $this->specialist->id,
                    $this->svc2->id => $this->specialist->id,
                    $this->svc3->id => $this->specialist->id,
                    $this->svc4->id => $this->specialist->id,
                    $this->extraSvc->id => $this->unskilledStaff->id,
                ],
            ],
        ]);
        $unskilledRes->assertStatus(422)
            ->assertJsonPath('reason', 'missing_skill');

        // 5. Double-booking conflict on the added 5th extra service slot (15:15–16:00)
        $dateStr = $this->targetDate->toDateString();
        $blocker = Appointment::create([
            'customer_name' => 'Blocker Client',
            'phone' => '+97455000011',
            'branch' => 'old_airport',
            'staff_id' => $this->specialist->id,
            'booking_agent_id' => $this->zoya->id,
            'booking_date' => $dateStr,
            'start_time' => '15:15:00',
            'appointment_datetime' => "{$dateStr} 15:15:00",
            'service_name' => $this->extraSvc->name,
            'duration' => 45,
            'price' => 85,
            'status' => 'confirmed',
        ]);
        AppointmentService::create([
            'appointment_id' => $blocker->id,
            'service_id' => $this->extraSvc->id,
            'staff_id' => $this->specialist->id,
            'name' => $this->extraSvc->name,
            'price' => 85,
            'original_price' => 85,
            'duration' => 45,
            'start_time' => "{$dateStr} 15:15:00",
            'status' => 'scheduled',
            'is_pending' => false,
        ]);

        $conflictRes = $this->createCustomized5ServiceCombo();
        $conflictRes->assertStatus(422);
    }

    public function test_unmodified_catalog_combo_remains_non_custom(): void
    {
        $dateStr = $this->targetDate->toDateString();
        $res = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => $this->zoya->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T13:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->specialist->id,
            'packages' => [[
                'combo_id' => $this->luxury4Combo->id,
                'immediate_service_ids' => [$this->svc1->id, $this->svc2->id, $this->svc3->id, $this->svc4->id],
                'pending_service_ids' => [],
                'service_staff' => [
                    $this->svc1->id => $this->specialist->id,
                    $this->svc2->id => $this->specialist->id,
                    $this->svc3->id => $this->specialist->id,
                    $this->svc4->id => $this->specialist->id,
                ],
            ]],
        ]);

        $res->assertCreated()->assertJsonPath('appointment.is_custom_combo', false);

        $appointment = Appointment::findOrFail($res->json('appointment.id'));
        $pkg = ClientPackage::where('appointment_id', $appointment->id)->firstOrFail();

        $this->assertFalse((bool) $appointment->is_custom_combo);
        $this->assertFalse((bool) $pkg->is_custom_combo);
        $this->assertEquals(170.00, (float) $pkg->price_paid);
        $this->assertEquals(170.00, (float) $pkg->original_combo_price);
        $this->assertNull($pkg->combo_override_reason);
    }

    public function test_outside_catalog_service_is_rejected_inside_combo_on_store_and_edit_but_allowed_standalone(): void
    {
        $dateStr = $this->targetDate->toDateString();

        // 1. POST /appointments with an outside service (Balayage) inside 4 Luxury Services -> 422 exact message
        $storeOutsideRes = $this->createCustomized5ServiceCombo([
            'package' => [
                'immediate_service_ids' => [$this->svc1->id, $this->svc2->id, $this->svc3->id, $this->svc4->id, $this->outsideSvc->id],
                'extra_service_ids' => [$this->outsideSvc->id],
                'service_staff' => [
                    $this->svc1->id => $this->specialist->id,
                    $this->svc2->id => $this->specialist->id,
                    $this->svc3->id => $this->specialist->id,
                    $this->svc4->id => $this->specialist->id,
                    $this->outsideSvc->id => $this->specialist->id,
                ],
            ],
        ]);
        $storeOutsideRes->assertStatus(422)
            ->assertJsonPath('message', "Selected service is not part of this combo's allowed service list.");

        // 2. POST /appointments with an outside service inside fixed 3-of-3 Bridal Glow Package -> 422
        $fixedOutsideRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => $this->zoya->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T13:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->specialist->id,
            'packages' => [[
                'combo_id' => $this->fixedBridalCombo->id,
                'immediate_service_ids' => [$this->svc1->id, $this->svc2->id, $this->svc3->id, $this->outsideSvc->id],
                'extra_service_ids' => [$this->outsideSvc->id],
                'is_custom_combo' => 1,
                'custom_price' => 350.00,
                'service_staff' => [
                    $this->svc1->id => $this->specialist->id,
                    $this->svc2->id => $this->specialist->id,
                    $this->svc3->id => $this->specialist->id,
                    $this->outsideSvc->id => $this->specialist->id,
                ],
            ]],
        ]);
        $fixedOutsideRes->assertStatus(422)
            ->assertJsonPath('message', "Selected service is not part of this combo's allowed service list.");

        // 3. Create a valid booking first, then try adding an outside service inside combo via PUT full-edit -> 422
        $validCreate = $this->createCustomized5ServiceCombo();
        $validCreate->assertCreated();
        $appointment = Appointment::findOrFail($validCreate->json('appointment.id'));

        $editOutsideRes = $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appointment), [
            'booking_agent_id' => $this->zoya->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T13:00",
            'branch' => 'old_airport',
            'staff_id' => $this->specialist->id,
            'packages' => [[
                'combo_id' => $this->luxury4Combo->id,
                'immediate_service_ids' => [$this->svc1->id, $this->svc2->id, $this->svc3->id, $this->outsideSvc->id],
                'extra_service_ids' => [$this->outsideSvc->id],
                'is_custom_combo' => 1,
                'custom_price' => 250.00,
                'service_staff' => [
                    $this->svc1->id => $this->specialist->id,
                    $this->svc2->id => $this->specialist->id,
                    $this->svc3->id => $this->specialist->id,
                    $this->outsideSvc->id => $this->specialist->id,
                ],
            ]],
        ]);
        $editOutsideRes->assertStatus(422)
            ->assertJsonPath('message', "Selected service is not part of this combo's allowed service list.");

        // 4. Adding outside service (Balayage) as a separate standalone service alongside the fixed combo SUCCEEDS
        $comboPlusStandaloneRes = $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appointment), [
            'booking_agent_id' => $this->zoya->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T13:00",
            'branch' => 'old_airport',
            'staff_id' => $this->specialist->id,
            'service_name' => [$this->outsideSvc->name],
            'service_price' => [320.00],
            'service_staff' => [$this->specialist->id],
            'packages' => [[
                'combo_id' => $this->fixedBridalCombo->id,
                'immediate_service_ids' => [$this->svc1->id, $this->svc2->id, $this->svc3->id],
                'pending_service_ids' => [],
                'service_staff' => [
                    $this->svc1->id => $this->specialist->id,
                    $this->svc2->id => $this->specialist->id,
                    $this->svc3->id => $this->specialist->id,
                ],
            ]],
        ]);
        $comboPlusStandaloneRes->assertOk()->assertJsonPath('success', true);
        $appointment->refresh();
        $this->assertEquals(520.00, (float) $appointment->price); // 200 combo + 320 standalone Balayage
    }
}
