<?php

namespace Tests\Feature;

use App\Http\Controllers\AppointmentController;
use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\ClientPackageService;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\KpiChatEvaluation;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Support\DashboardScorecardBuilder;
use App\Support\StaffSalesAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SwarmAuditCriticalFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Staff $oaStylist;
    private Staff $wkStylist;
    private Service $svcHaircut;
    private Service $svcManicure;
    private Service $svcFacial;
    private Combo $bridalCombo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Owner',
            'email' => 'admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->svcHaircut = Service::create(['name' => 'Signature Haircut', 'price' => 100, 'duration' => 30]);
        $this->svcManicure = Service::create(['name' => 'Gel Manicure', 'price' => 80, 'duration' => 30]);
        $this->svcFacial = Service::create(['name' => 'Hydra Facial', 'price' => 150, 'duration' => 60]);

        $this->oaStylist = Staff::create([
            'name' => 'Sara Ahmed',
            'branch' => 'old_airport',
            'availability_status' => 'available',
            'bookable' => true,
        ]);
        $this->oaStylist->services()->sync([
            $this->svcHaircut->id,
            $this->svcManicure->id,
            $this->svcFacial->id,
        ]);

        $this->wkStylist = Staff::create([
            'name' => 'Rana Wakrah',
            'branch' => 'wakrah',
            'availability_status' => 'available',
            'bookable' => true,
        ]);
        $this->wkStylist->services()->sync([
            $this->svcHaircut->id,
            $this->svcManicure->id,
            $this->svcFacial->id,
        ]);

        $this->bridalCombo = Combo::create([
            'name' => 'Bridal Glow 3-Pick',
            'price' => 200,
            'quantity_included' => 3,
            'validity_days' => 14,
            'status' => 'active',
        ]);
        $this->bridalCombo->services()->sync([
            $this->svcHaircut->id,
            $this->svcManicure->id,
            $this->svcFacial->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_p0_1_and_p0_3_paid_booking_locks_against_tampering_and_enforces_finance_permission_for_reversals(): void
    {
        $slot = Carbon::today()->addDay()->setTime(13, 0);

        $storeRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Noor Al-Thani',
            'phone' => '+97455110011',
            'appointment_datetime' => $slot->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'packages' => [[
                'combo_id' => $this->bridalCombo->id,
                'immediate_service_ids' => [
                    $this->svcHaircut->id,
                    $this->svcManicure->id,
                    $this->svcFacial->id,
                ],
            ]],
        ])->assertCreated();

        $appointment = Appointment::findOrFail($storeRes->json('appointment.id'));
        $pkg = ClientPackage::where('appointment_id', $appointment->id)->firstOrFail();

        // Complete checkout (200 QAR) -> all 3 slots redeemed, package status = 'completed'
        $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $appointment), [
            'payments' => ['cash' => 200],
        ])->assertRedirect(route('appointments.revenue.index'));

        $this->assertEquals('completed', $appointment->fresh()->status);
        $this->assertEquals('completed', $pkg->fresh()->status);
        $this->assertSame(3, $pkg->fresh()->redeemed_count);
        $this->assertSame(0, $pkg->fresh()->remaining_count);

        // 1. P0-1: Paid appointment rejects PUT tampering (branch/price/combo slot unchecking) with 422
        $tamperRes = $this->actingAs($this->admin)->putJson(route('appointments.update', $appointment), [
            'customer_name' => 'Noor Al-Thani',
            'phone' => '+97455110011',
            'appointment_datetime' => $slot->format('Y-m-d\TH:i'),
            'branch' => 'wakrah',
            'price' => 320,
            'staff_id' => $this->wkStylist->id,
            'status' => 'completed',
            'combo_packages' => [[
                'client_package_id' => $pkg->id,
                'today_service_ids' => [$this->svcHaircut->id],
                'pending_service_ids' => [],
            ]],
        ]);
        $tamperRes->assertStatus(422)
            ->assertJsonPath('reason', 'paid_locked')
            ->assertJsonPath('message', AppointmentController::PAID_BOOKING_LOCKED_MESSAGE);

        // Reschedule & drawer addService also reject mutations on paid bookings with 422
        $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $appointment), [
            'appointment_datetime' => $slot->copy()->addHour()->format('Y-m-d\TH:i'),
        ])->assertStatus(422);

        $this->actingAs($this->admin)->postJson(route('appointments.services.store', $appointment), [
            'service_id' => $this->svcFacial->id,
        ])->assertStatus(422);

        // Verify Appointment, Sale, and 3/3 redeemed ClientPackage slots remain 100% intact
        $appointment->refresh();
        $pkg->refresh();
        $this->assertSame('old_airport', $appointment->branch);
        $this->assertEquals(200.0, (float) $appointment->price);
        $this->assertSame('completed', $pkg->status);
        $this->assertSame(3, $pkg->redeemed_count);
        $this->assertSame(0, $pkg->remaining_count);

        // 2. P0-3: Receptionist with bookings/calendar edit but finance = 'none' gets 403 on status cancel & DELETE
        $noFinanceReceptionist = User::factory()->create([
            'name' => 'Floor Receptionist',
            'email' => 'reception@laleen.qa',
            'role' => 'staff',
            'is_active' => true,
            'permissions' => array_merge(User::defaultPermissionsForRole('staff'), [
                'bookings' => 'edit',
                'calendar' => 'edit',
                'finance' => 'none',
            ]),
        ]);

        $this->actingAs($noFinanceReceptionist)
            ->patchJson(route('appointments.updateStatus', $appointment), ['status' => 'cancelled'])
            ->assertStatus(403)
            ->assertJsonPath('message', AppointmentController::FINANCE_REVERSAL_FORBIDDEN_MESSAGE);

        $this->actingAs($noFinanceReceptionist)
            ->deleteJson(route('appointments.destroy', $appointment))
            ->assertStatus(403);

        $this->assertSame(1, Sale::where('appointment_id', $appointment->id)->count());

        // 3. Authorized Finance editor CAN reverse the paid sale cleanly
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $appointment), ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(0, Sale::where('appointment_id', $appointment->id)->count());
        $this->assertSame('cancelled', $appointment->fresh()->status);
    }

    public function test_p0_2_cancelling_or_no_showing_combo_and_rescheduling_preserves_full_combo_due_price_and_sets_pending_status(): void
    {
        $slot = Carbon::today()->addDay()->setTime(12, 0);

        $storeRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Hissa Al-Sulaiti',
            'phone' => '+97455220022',
            'appointment_datetime' => $slot->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'packages' => [[
                'combo_id' => $this->bridalCombo->id,
                'immediate_service_ids' => [$this->svcHaircut->id, $this->svcManicure->id],
                'pending_service_ids' => [$this->svcFacial->id],
            ]],
        ])->assertCreated();

        $appointment = Appointment::findOrFail($storeRes->json('appointment.id'));
        $pkg = ClientPackage::where('appointment_id', $appointment->id)->firstOrFail();

        // 1. Mark booking as No Show -> package preserved in 'cancelled' state (not deleted leaving 0 QAR free lines)
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $appointment), ['status' => 'no_show'])
            ->assertOk();

        $this->assertSame('no_show', $appointment->fresh()->status);
        $this->assertSame('cancelled', $pkg->fresh()->status);

        // 2. Reschedule cancelled/no-show combo onto a new slot -> revives to 'pending' (NOT 'confirmed') and full 200.00 QAR!
        $newSlot = $slot->copy()->addDays(2)->setTime(15, 0);
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.reschedule', $appointment), [
                'appointment_datetime' => $newSlot->format('Y-m-d\TH:i'),
                'staff_id' => $this->oaStylist->id,
            ])
            ->assertOk();

        $appointment->refresh();
        $pkg->refresh();
        $this->assertSame('pending', $appointment->status);
        $this->assertSame('active', $pkg->status);
        $this->assertEquals(200.0, (float) $appointment->price);

        // Attempting 0.00 QAR checkout is rejected; 200.00 QAR is required and succeeds
        $this->actingAs($this->admin)->get(route('appointments.revenue.payment', $appointment))
            ->assertOk()
            ->assertSee('200.00 QAR');

        $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $appointment), [
            'payments' => ['cash' => 0],
        ])->assertSessionHas('error');
        $this->assertSame(0, Sale::where('appointment_id', $appointment->id)->count());

        $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $appointment), [
            'payments' => ['cash' => 200],
        ])->assertRedirect(route('appointments.revenue.index'));

        $sale = Sale::where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertEquals(200.0, (float) $sale->total_amount);
        $this->assertSame(1, $pkg->fresh()->remaining_count);
    }

    public function test_p0_4_unpicked_zero_of_x_choose_in_salon_combo_blocks_checkout_unless_services_picked_or_prepurchase_confirmed(): void
    {
        $slot = Carbon::today()->addDay()->setTime(13, 0);

        $storeRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Maryam Al-Misnad',
            'phone' => '+97455330033',
            'appointment_datetime' => $slot->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'packages' => [[
                'combo_id' => $this->bridalCombo->id,
                'immediate_service_ids' => [],
                'pending_service_ids' => [],
            ]],
        ])->assertCreated();

        $appointment = Appointment::findOrFail($storeRes->json('appointment.id'));
        $this->assertTrue($appointment->is_combo_tbd);

        // Checkout page renders the prominent warning banner & pre-purchase confirmation checkbox
        $this->actingAs($this->admin)->get(route('appointments.revenue.payment', $appointment))
            ->assertOk()
            ->assertSee('id="unpickedComboWarningBanner"', false)
            ->assertSee('id="packagePrepurchaseOnly"', false);

        // 1. PATCH /status -> completed fails with 422 combo_tbd
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $appointment), ['status' => 'completed'])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'combo_tbd');

        // 2. POST /payment without picking services or checking package_prepurchase_only fails with 422
        $this->actingAs($this->admin)
            ->postJson(route('appointments.revenue.storePayment', $appointment), [
                'payments' => ['cash' => 200],
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'combo_tbd')
            ->assertJsonPath('message', AppointmentController::UNPICKED_COMBO_CHECKOUT_MESSAGE);

        $this->assertSame(0, Sale::where('appointment_id', $appointment->id)->count());

        // 3. Explicitly confirming package_prepurchase_only = 1 completes the pre-purchase sale cleanly
        $this->actingAs($this->admin)
            ->post(route('appointments.revenue.storePayment', $appointment), [
                'package_prepurchase_only' => 1,
                'payments' => ['cash' => 200],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        $this->assertSame(1, Sale::where('appointment_id', $appointment->id)->count());
        $pkg = ClientPackage::where('appointment_id', $appointment->id)->firstOrFail();
        $this->assertSame(3, $pkg->remaining_count);
    }

    public function test_p1_1_visit_2_smart_coverage_prevents_double_charge_and_drawer_redeem_package_resolves_decide_in_salon(): void
    {
        $visit1Slot = Carbon::today()->setTime(13, 0);

        // Visit 1: Buy 3-service Combo (Haircut + Manicure Today, Facial Pending -> 2 remaining if 1 Today or 1 remaining here)
        $v1Res = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Aisha Al-Kuwari',
            'phone' => '+97455440044',
            'appointment_datetime' => $visit1Slot->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'packages' => [[
                'combo_id' => $this->bridalCombo->id,
                'immediate_service_ids' => [$this->svcHaircut->id],
                'pending_service_ids' => [$this->svcFacial->id, $this->svcManicure->id],
            ]],
        ])->assertCreated();

        $visit1 = Appointment::findOrFail($v1Res->json('appointment.id'));
        $pkg = ClientPackage::where('appointment_id', $visit1->id)->firstOrFail();

        $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $visit1), [
            'payments' => ['cash' => 200],
        ])->assertRedirect(route('appointments.revenue.index'));

        $this->assertSame(2, $pkg->fresh()->remaining_count);
        $this->assertSame(2, AppointmentService::where('appointment_id', $visit1->id)->where('is_pending', true)->count());

        // --- PART A: Visit 2 booked with standalone Hydra Facial (150 QAR) + redeemed at Checkout -> 0.00 QAR (NOT 150 QAR!) ---
        $visit2Slot = Carbon::today()->addDays(2)->setTime(14, 0);
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Aisha Al-Kuwari',
            'phone' => '+97455440044',
            'appointment_datetime' => $visit2Slot->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'service_name' => [$this->svcFacial->name],
        ])->assertCreated();

        $visit2 = Appointment::where('id', '>', $visit1->id)->latest('id')->firstOrFail();
        $this->assertEquals(150.0, (float) $visit2->price);

        $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $visit2), [
            'redeem_service_ids' => ["{$pkg->id}:{$this->svcFacial->id}"],
            'payments' => ['cash' => 0],
        ])->assertRedirect(route('appointments.revenue.index'));

        $visit2Sale = Sale::where('appointment_id', $visit2->id)->firstOrFail();
        $this->assertEquals(0.0, (float) $visit2Sale->total_amount);
        $this->assertEquals(0.0, (float) $visit2->fresh()->price);
        $this->assertSame(1, $visit2->appointmentServices()->where('is_pending', false)->count(), 'Must convert standalone line in-place without duplicating');
        $this->assertSame(1, $pkg->fresh()->remaining_count);
        // Visit 1's stale is_pending row for Hydra Facial was automatically cleaned up!
        $this->assertSame(1, AppointmentService::where('appointment_id', $visit1->id)->where('is_pending', true)->count());

        // --- PART B: Visit 3 booked as Decide in Salon -> resolved via Drawer Redeem Package endpoint ---
        $visit3Slot = Carbon::today()->addDays(4)->setTime(16, 0);
        $v3Res = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Aisha Al-Kuwari',
            'phone' => '+97455440044',
            'appointment_datetime' => $visit3Slot->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'decide_in_salon' => true,
        ])->assertCreated();

        $visit3 = Appointment::findOrFail($v3Res->json('appointment.id'));
        $this->assertTrue((bool) $visit3->decide_in_salon);

        // Drawer show() exposes remaining Gel Manicure slot
        $this->actingAs($this->admin)->getJson(route('appointments.show', $visit3))
            ->assertOk()
            ->assertJsonPath('redeemable_package_services.0.id', "{$pkg->id}:{$this->svcManicure->id}");

        // Redeem Gel Manicure directly onto Visit 3 via drawer endpoint
        $this->actingAs($this->admin)->postJson(route('appointments.redeemPackage', $visit3), [
            'redeem_service_ids' => ["{$pkg->id}:{$this->svcManicure->id}"],
        ])->assertOk()->assertJsonPath('success', true);

        $visit3->refresh();
        $pkg->refresh();
        $this->assertFalse((bool) $visit3->decide_in_salon);
        $this->assertEquals(0.0, (float) $visit3->price);
        $this->assertSame('Gel Manicure', $visit3->service_name);
        $this->assertSame(0, $pkg->remaining_count);
        $this->assertSame('completed', $pkg->status);
        $this->assertSame(0, AppointmentService::where('appointment_id', $visit1->id)->where('is_pending', true)->count());
    }

    public function test_p0_5_bookings_index_includes_future_appointments_in_this_month_this_week_and_all_time(): void
    {
        // Freeze to Monday 2026-11-16 10:00 (Sunday–Saturday week = Nov 15..Nov 21; Month = Nov 1..Nov 30)
        Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00', 'Asia/Qatar'));

        Appointment::create([
            'customer_name' => 'Same Week Future Client',
            'phone' => '55600001',
            'appointment_datetime' => Carbon::parse('2026-11-19 14:00:00'),
            'booking_date' => '2026-11-19',
            'start_time' => '14:00:00',
            'service_name' => 'Signature Haircut',
            'branch' => 'old_airport',
            'price' => 100,
            'status' => 'pending',
        ]);

        Appointment::create([
            'customer_name' => 'Same Month Future Client',
            'phone' => '55600002',
            'appointment_datetime' => Carbon::parse('2026-11-25 15:00:00'),
            'booking_date' => '2026-11-25',
            'start_time' => '15:00:00',
            'service_name' => 'Hydra Facial',
            'branch' => 'old_airport',
            'price' => 150,
            'status' => 'pending',
        ]);

        Appointment::create([
            'customer_name' => 'Next Month Future Client',
            'phone' => '55600003',
            'appointment_datetime' => Carbon::parse('2026-12-12 16:00:00'),
            'booking_date' => '2026-12-12',
            'start_time' => '16:00:00',
            'service_name' => 'Gel Manicure',
            'branch' => 'wakrah',
            'price' => 80,
            'status' => 'pending',
        ]);

        // 1. Default (this_month) shows both Nov 19 and Nov 25 future bookings
        $this->actingAs($this->admin)->get(route('appointments.index'))
            ->assertOk()
            ->assertSee('Same Week Future Client')
            ->assertSee('Same Month Future Client');

        // 2. this_week shows Nov 19 future booking
        $this->actingAs($this->admin)->get(route('appointments.index', ['period' => 'this_week']))
            ->assertOk()
            ->assertSee('Same Week Future Client');

        // 3. all_time shows all 3 upcoming bookings including Dec 12 next month
        $this->actingAs($this->admin)->get(route('appointments.index', ['period' => 'all_time']))
            ->assertOk()
            ->assertSee('Same Week Future Client')
            ->assertSee('Same Month Future Client')
            ->assertSee('Next Month Future Client');
    }

    public function test_p0_8_cancelled_booking_reopens_lead_into_all_followup_queues_and_prioritizes_followup_over_cancel(): void
    {
        $zoya = User::factory()->create([
            'name' => 'Zoya Khan',
            'email' => 'zoya@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('agent'),
        ]);

        $customer = Customer::create(['name' => 'Salma Al-Thani', 'phone' => '55770077']);
        $lead = Lead::create([
            'phone' => '55770077',
            'customer_id' => $customer->id,
            'assigned_agent_id' => $zoya->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['Hydra Facial'],
            'next_followup_date' => today()->addDay(),
        ]);

        $slot = Carbon::today()->addDays(2)->setTime(13, 0);
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => 'Salma Al-Thani',
            'phone' => '+97455770077',
            'appointment_datetime' => $slot->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->oaStylist->id,
            'booking_agent_id' => $zoya->id,
            'service_name' => ['Hydra Facial'],
        ])->assertCreated();

        $appointment = Appointment::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('booked', $lead->fresh()->needful_done);

        // Cancel the appointment -> Lead reopens with category = 'cancel' and next_followup_date = today()
        $this->actingAs($this->admin)
            ->patchJson(route('appointments.updateStatus', $appointment), ['status' => 'cancelled'])
            ->assertOk();

        $lead->refresh();
        $this->assertNull($lead->needful_done);
        $this->assertSame('cancel', $lead->category);
        $this->assertSame(today()->toDateString(), $lead->next_followup_date?->toDateString());

        // Immediately visible in pendingFollowUpsQuery & Follow-ups Bell for BOTH Zoya and Admin!
        $this->assertSame(1, Lead::pendingFollowUpsQuery($zoya)->count());
        $this->assertSame(1, Lead::pendingFollowUpsQuery($this->admin)->count());

        $this->actingAs($zoya)->get(route('leads.index'))
            ->assertOk()
            ->assertViewHas('pendingFollowupsCount', 1);

        // Move follow-up date to yesterday -> visible in Overdue Follow-ups too
        $lead->update(['next_followup_date' => today()->subDay()]);
        $this->actingAs($this->admin)->get(route('leads.index', ['alert_filter' => 'overdue']))
            ->assertOk()
            ->assertViewHas('overdueCount', 1)
            ->assertSee('Salma Al-Thani');

        // Checking both ['cancel', 'follow_up'] prioritizes 'follow_up' as primary category while preserving 'cancel' badge
        $this->actingAs($zoya)->put(route('leads.update', $lead), [
            'country_code' => '974',
            'phone_number' => '55770077',
            'customer_name' => 'Salma Al-Thani',
            'assigned_agent_id' => $zoya->id,
            'categories' => ['cancel', 'follow_up'],
            'service_interest' => ['Hydra Facial'],
            'next_followup_date' => today()->toDateString(),
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame('follow_up', $lead->category);
        $this->assertTrue($lead->hasCategory('cancel'));
        $this->assertTrue($lead->hasCategory('follow_up'));
    }

    public function test_p0_9_customer_lookup_and_lead_store_never_substring_hijack_international_phone_profiles(): void
    {
        $saudiCustomer = Customer::create([
            'name' => 'Hessa Al-Saud',
            'phone' => '966558811223',
        ]);

        // 1. Qatar 8-digit lookup '55881122' must NOT substring-match Saudi '966558811223'
        $this->actingAs($this->admin)
            ->getJson(route('customers.lookup', ['phone' => '55881122']))
            ->assertOk()
            ->assertExactJson(['found' => false]);

        // Exact Qatar variants DO match once a Qatar customer exists, but first test Lead creation
        // even when a stale/wrong customer_id ($saudiCustomer->id) is submitted in the form!
        $this->actingAs($this->admin)->post(route('leads.store'), [
            'country_code' => '974',
            'phone_number' => '55881122',
            'customer_name' => 'Dana Al-Thani',
            'customer_id' => $saudiCustomer->id,
            'category' => 'inquiry',
            'service_interest' => ['Hydra Facial'],
            'next_followup_date' => today()->addDay()->toDateString(),
        ])->assertRedirect();

        $qatarLead = Lead::where('phone', '55881122')->firstOrFail();
        $this->assertNotEquals($saudiCustomer->id, $qatarLead->customer_id);

        $qatarCustomer = Customer::findOrFail($qatarLead->customer_id);
        $this->assertSame('55881122', $qatarCustomer->phone);
        $this->assertSame('Dana Al-Thani', $qatarCustomer->name);
        $this->assertSame('Hessa Al-Saud', $saudiCustomer->fresh()->name);

        // And now canonical +974 lookup resolves Dana Al-Thani cleanly
        $this->actingAs($this->admin)
            ->getJson(route('customers.lookup', ['phone' => '+974 5588 1122']))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('id', $qatarCustomer->id);
    }

    public function test_p0_11_dashboard_includes_same_day_ad_leads_and_chat_evaluations_without_sqlite_date_string_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 14:30:00', 'Asia/Qatar'));

        $zoya = User::factory()->create([
            'name' => 'Zoya',
            'email' => 'zoya@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
        ]);

        AdLeadEntry::create([
            'date' => '2026-11-16',
            'customer_name' => 'Today Ad Client',
            'phone' => '55990011',
            'category' => 'Facial Ads',
            'branch' => 'old_airport',
            'ticket_amount' => 2500,
            'old_airport_amount' => 2500,
            'created_by' => $this->admin->id,
        ]);

        KpiChatEvaluation::create([
            'agent_id' => $zoya->id,
            'evaluator_id' => $this->admin->id,
            'evaluation_date' => '2026-11-16',
            'answers' => array_fill_keys(array_keys(KpiChatEvaluation::QUESTIONS), 'pass'),
            'total_score' => KpiChatEvaluation::maxScore(),
            'percentage' => 95.0,
        ]);

        $cards = collect((new DashboardScorecardBuilder(
            Carbon::parse('2026-11-01', 'Asia/Qatar'),
            Carbon::parse('2026-11-16', 'Asia/Qatar'),
            $this->admin
        ))->build())->keyBy('key');

        $ayanKpis = collect($cards['ayan']['kpis'])->keyBy('label');
        $this->assertStringContainsString('QAR 2,500', $ayanKpis['Ad Revenue']['value_label']);
        $this->assertSame('100%', $ayanKpis['Ad Conversion']['value_label']);

        $zoyaKpis = collect($cards['zoya']['kpis'])->keyBy('label');
        $this->assertSame('95%', $zoyaKpis['Chat QA (<5m)']['value_label']);

        // Also verify single-day filter (?from=2026-11-16&to=2026-11-16) on /dashboard HTTP endpoint
        $this->actingAs($this->admin)
            ->get(route('dashboard', ['from' => '2026-11-16', 'to' => '2026-11-16']))
            ->assertOk()
            ->assertSee('QAR 2,500')
            ->assertSee('95%');
    }

    public function test_p0_12_dashboard_branch_manager_chair_upsells_matches_multi_stylist_team_target(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 12:00:00', 'Asia/Qatar'));

        // setUp already created 1 old_airport stylist (Sara) and 1 wakrah stylist (Rana).
        // Add 2 more OA stylists, 1 more WK stylist, and 1 dual-branch ('both') stylist:
        // OA roster = 3 full + 0.5 dual = 3.5 stylists; WK roster = 2 full + 0.5 dual = 2.5 stylists.
        Staff::create(['name' => 'Layla OA', 'branch' => 'old_airport', 'bookable' => true]);
        Staff::create(['name' => 'Aisha OA', 'branch' => 'old_airport', 'bookable' => true]);
        Staff::create(['name' => 'Nadia WK', 'branch' => 'wakrah', 'bookable' => true]);
        Staff::create(['name' => 'Dual Specialist', 'branch' => 'both', 'bookable' => true]);

        $from = Carbon::parse('2026-11-01', 'Asia/Qatar')->startOfDay();
        $to = Carbon::parse('2026-11-16', 'Asia/Qatar')->endOfDay();

        $analytics = new StaffSalesAnalytics($from, $to);
        $expectedOaTarget = round((float) $analytics->totals('old_airport')['team_target'], 0);
        $expectedWkTarget = round((float) $analytics->totals('wakrah')['team_target'], 0);

        // Verify November uses 30 days (16/30 * 1700 = 906.67 per full stylist -> OA = ~3,173 QAR, NOT 907 QAR!)
        $this->assertEquals(30, $analytics->daysInTargetMonth());
        $this->assertGreaterThan(2000, $expectedOaTarget);
        $this->assertGreaterThan(1500, $expectedWkTarget);

        $cards = collect((new DashboardScorecardBuilder($from, $to, $this->admin))->build())->keyBy('key');

        $ahanaUpsells = collect($cards['ahana']['kpis'])->firstWhere('label', 'Chair Upsells');
        $rheaUpsells = collect($cards['rhea']['kpis'])->firstWhere('label', 'Chair Upsells');

        $this->assertSame('QAR 0 / ' . number_format($expectedOaTarget, 0), $ahanaUpsells['value_label']);
        $this->assertSame('QAR 0 / ' . number_format($expectedWkTarget, 0), $rheaUpsells['value_label']);
    }
}
