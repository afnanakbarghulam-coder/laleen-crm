<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DecideInSalonBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Staff $skilledStylist;
    private Staff $untrainedStylist;
    private Service $svcHaircut;
    private Service $svcFacial;
    private Combo $glowCombo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->skilledStylist = Staff::create([
            'name' => 'Aisha Stylist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        // Staff member at Old Airport with ZERO assigned service skills
        $this->untrainedStylist = Staff::create([
            'name' => 'Mona Consultation',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        $this->svcHaircut = Service::create([
            'name' => 'Signature Haircut',
            'category' => 'Hair',
            'price' => 140,
            'duration' => 45,
        ]);

        $this->svcFacial = Service::create([
            'name' => 'Hydra Glow Facial',
            'category' => 'Facial',
            'price' => 250,
            'duration' => 60,
        ]);

        // Only Aisha is trained on Signature Haircut & Hydra Glow Facial
        $this->svcHaircut->staff()->sync([$this->skilledStylist->id]);
        $this->svcFacial->staff()->sync([$this->skilledStylist->id]);

        $this->glowCombo = Combo::create([
            'name' => 'Glow Duo Package',
            'price' => 300,
            'quantity_included' => 2,
            'validity_days' => 14,
            'status' => 'active',
        ]);
        $this->glowCombo->services()->sync([$this->svcHaircut->id, $this->svcFacial->id]);
    }

    public function test_creating_decide_in_salon_booking_sets_60_min_slot_zero_price_syncs_lead_and_renders_across_views(): void
    {
        $visitDate = now()->addDays(2)->setTime(13, 0, 0);

        $customer = Customer::create([
            'name' => 'Fatima Arrival',
            'phone' => '97455112233',
            'branch' => 'old_airport',
        ]);

        $openLead = Lead::create([
            'customer_id' => $customer->id,
            'phone' => '97455112233',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'branch' => 'old_airport',
            'needful_done' => null,
            'next_followup_date' => now()->toDateString(),
        ]);

        $res = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Fatima Arrival',
            'phone' => '+97455112233',
            'appointment_datetime' => $visitDate->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->skilledStylist->id,
            'decide_in_salon' => true,
        ]);

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.decide_in_salon', true)
            ->assertJsonPath('appointment.hold_duration', 60)
            ->assertJsonPath('appointment.service_name', 'Decide in Salon');

        $appointment = Appointment::latest('id')->firstOrFail();
        $this->assertTrue($appointment->decide_in_salon);
        $this->assertSame(60, $appointment->effective_hold_duration);
        $this->assertEquals(0.0, (float) $appointment->price);
        $this->assertSame('Decide in Salon', $appointment->service_name);
        $this->assertSame($customer->id, (int) $appointment->customer_id);
        $this->assertCount(0, $appointment->appointmentServices);

        // Open lead automatically flips to Booked
        $openLead->refresh();
        $this->assertSame('booked', $openLead->needful_done);
        $this->assertSame($appointment->id, (int) $openLead->appointment_id);

        // Calendar Day payload renders 60 min slot (13:00 -> 14:00) and Decide in Salon
        $dayPayload = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'day',
            'date' => $visitDate->toDateString(),
            'branch' => 'old_airport',
        ]))->assertOk()->json();

        $stylistSlices = $dayPayload['appointments'][$this->skilledStylist->id] ?? [];
        $this->assertCount(1, $stylistSlices);
        $this->assertSame(60, $stylistSlices[0]['duration']);
        $this->assertSame('13:00', $stylistSlices[0]['start']);
        $this->assertSame('14:00', $stylistSlices[0]['end']);
        $this->assertTrue($stylistSlices[0]['decide_in_salon']);
        $this->assertSame('Decide in Salon', $stylistSlices[0]['service_name']);

        // Bookings table & Client Profile render subtle Decide in Salon badge
        $bookingsHtml = $this->actingAs($this->admin)->get(route('appointments.index', [
            'period' => 'custom',
            'from' => $visitDate->toDateString(),
            'to' => $visitDate->toDateString(),
        ]))->assertOk()->getContent();
        $this->assertStringContainsString('bk-decide-badge', $bookingsHtml);
        $this->assertStringContainsString('Decide in Salon', $bookingsHtml);

        $profileHtml = $this->actingAs($this->admin)->get(route('customers.show', $customer->id))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Decide in Salon', $profileHtml);

        $calHtml = $this->actingAs($this->admin)->get(route('appointments.calendar'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="fbDecideSalonPill"', $calHtml);
        $this->assertStringContainsString('Client will choose services on arrival', $calHtml);
    }

    public function test_creating_empty_booking_with_zero_services_zero_combos_and_decide_in_salon_false_fails_validation_422(): void
    {
        $visitDate = now()->addDays(2)->setTime(14, 0, 0);

        $res = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Empty Client',
            'phone' => '+97455998877',
            'appointment_datetime' => $visitDate->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->skilledStylist->id,
            'decide_in_salon' => false,
            'service_name' => [],
            'packages' => [],
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('service_name');

        $this->assertSame(0, Appointment::count());
    }

    public function test_decide_in_salon_bypasses_missing_skill_but_enforces_staff_off_and_staff_busy_60_min_overlaps(): void
    {
        $visitDate = now()->addDays(3)->setTime(12, 0, 0);

        // 1. Untrained stylist (0 assigned service skills) succeeds with decide_in_salon = true
        $okRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Noor Consultation',
            'phone' => '+97455223344',
            'appointment_datetime' => $visitDate->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->untrainedStylist->id,
            'decide_in_salon' => true,
        ]);
        $okRes->assertCreated()->assertJsonPath('success', true);

        // 2. Overlapping within the 60-minute hold window (12:45 PM overlaps 12:00-13:00 hold!) returns 422 staff_busy
        $busyRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Second Caller',
            'phone' => '+97455334455',
            'appointment_datetime' => $visitDate->copy()->setTime(12, 45, 0)->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->untrainedStylist->id,
            'decide_in_salon' => true,
        ]);
        $busyRes->assertStatus(422)
            ->assertJsonPath('reason', 'staff_busy')
            ->assertJsonPath('message', 'Cannot book: Mona Consultation is already busy with another booking from 12:00 PM to 1:00 PM.');

        // 3. Blocked calendar slot (15:00-16:00) returns 422 staff_off when booking Decide in Salon at 14:30 (60m overlaps 15:00!)
        StaffBlock::create([
            'staff_id' => $this->untrainedStylist->id,
            'date' => $visitDate->toDateString(),
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
            'reason' => 'Staff Lunch',
        ]);

        $blockOverlapRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Lunch Overlap Client',
            'phone' => '+97455445566',
            'appointment_datetime' => $visitDate->copy()->setTime(14, 30, 0)->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->untrainedStylist->id,
            'decide_in_salon' => true,
        ]);
        $blockOverlapRes->assertStatus(422)
            ->assertJsonPath('reason', 'staff_off')
            ->assertJsonPath('message', 'Cannot book: Mona Consultation is unavailable / blocked from 3:00 PM to 4:00 PM.');

        // 4. Off-duty staff member returns 422 staff_off even when decide_in_salon = true
        $offStylist = Staff::create([
            'name' => 'Sara OffDuty',
            'branch' => 'old_airport',
            'availability_status' => 'off',
            'bookable' => true,
        ]);

        $offRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Off Duty Caller',
            'phone' => '+97455667788',
            'appointment_datetime' => $visitDate->copy()->setTime(16, 0, 0)->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $offStylist->id,
            'decide_in_salon' => true,
        ]);
        $offRes->assertStatus(422)
            ->assertJsonPath('reason', 'staff_off')
            ->assertJsonPath('message', "Cannot book: Sara OffDuty is off duty on {$visitDate->format('d M Y')}.");

        // 5. Unscheduled Queue ("Pending time") bypasses time-overlap and blocked-slot checks cleanly
        $unschedRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Queue Consultation',
            'phone' => '+97455778899',
            'booking_date' => $visitDate->toDateString(),
            'unscheduled' => true,
            'branch' => 'old_airport',
            'staff_id' => $this->untrainedStylist->id,
            'decide_in_salon' => true,
        ]);
        $unschedRes->assertCreated()->assertJsonPath('appointment.decide_in_salon', true);
    }

    public function test_adding_real_service_or_combo_to_decide_in_salon_booking_flips_flag_false_and_updates_duration_and_total(): void
    {
        $visitDate = now()->addDays(2)->setTime(15, 0, 0);

        // Case A: Saved Decide in Salon booking updated via Bookings Edit (PUT) with a real service
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Mariam Upgrade',
            'phone' => '+97466112233',
            'appointment_datetime' => $visitDate->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->skilledStylist->id,
            'decide_in_salon' => true,
        ])->assertCreated();

        $apptA = Appointment::latest('id')->firstOrFail();
        $this->assertTrue($apptA->decide_in_salon);
        $this->assertEquals(0.0, (float) $apptA->price);

        $this->actingAs($this->admin)->putJson(route('appointments.update', $apptA->id), [
            'customer_name' => 'Mariam Upgrade',
            'phone' => '+97466112233',
            'appointment_datetime' => $visitDate->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->skilledStylist->id,
            'service_name' => ['Signature Haircut'],
            'decide_in_salon' => true, // even if stale hidden input sends true, real service wins!
        ])->assertOk();

        $apptA->refresh();
        $this->assertFalse($apptA->decide_in_salon);
        $this->assertSame('Signature Haircut', $apptA->service_name);
        $this->assertEquals(140.0, (float) $apptA->price);
        $this->assertSame(45, (int) $apptA->appointmentServices()->where('is_pending', false)->sum('duration'));

        // Case B: Saved Decide in Salon booking updated via Calendar Drawer "+ Add service" (POST /appointments/{id}/services)
        $visitDateB = now()->addDays(2)->setTime(17, 0, 0);
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Huda Drawer Add',
            'phone' => '+97466223344',
            'appointment_datetime' => $visitDateB->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->skilledStylist->id,
            'decide_in_salon' => true,
        ])->assertCreated();

        $apptB = Appointment::latest('id')->firstOrFail();
        $this->assertTrue($apptB->decide_in_salon);

        $this->actingAs($this->admin)->postJson("/appointments/{$apptB->id}/services", [
            'service_id' => $this->svcFacial->id,
            'staff_id' => $this->skilledStylist->id,
        ])->assertOk();

        $apptB->refresh();
        $this->assertFalse($apptB->decide_in_salon);
        $this->assertSame('Hydra Glow Facial', $apptB->service_name);
        $this->assertEquals(250.0, (float) $apptB->price);
        $this->assertSame(60, (int) $apptB->appointmentServices()->where('is_pending', false)->sum('duration'));

        // Case C: Booking payload submitting both a Combo Package AND decide_in_salon=true automatically forces decide_in_salon=false
        $visitDateC = now()->addDays(2)->setTime(19, 0, 0);
        $comboRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Combo Arrival Client',
            'phone' => '+97466334455',
            'appointment_datetime' => $visitDateC->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->skilledStylist->id,
            'decide_in_salon' => true,
            'packages' => [
                1 => [
                    'combo_id' => $this->glowCombo->id,
                    'immediate_service_ids' => [$this->svcHaircut->id],
                    'pending_service_ids' => [$this->svcFacial->id],
                ],
            ],
        ]);
        $comboRes->assertCreated()->assertJsonPath('appointment.decide_in_salon', false);
        $apptC = Appointment::latest('id')->firstOrFail();
        $this->assertFalse($apptC->decide_in_salon);
        $this->assertEquals(300.0, (float) $apptC->price);
    }

    public function test_attempting_checkout_on_unresolved_decide_in_salon_booking_returns_422_requiring_service_or_combo_first(): void
    {
        $visitDate = now()->addDays(1)->setTime(14, 0, 0);

        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Unresolved Checkout Client',
            'phone' => '+97477112233',
            'appointment_datetime' => $visitDate->format('Y-m-d\TH:i'),
            'branch' => 'old_airport',
            'staff_id' => $this->skilledStylist->id,
            'decide_in_salon' => true,
        ])->assertCreated();

        $appointment = Appointment::latest('id')->firstOrFail();
        $expectedMsg = 'Please add the service(s) or combo performed before completing checkout.';

        // 1. Attempting storePayment on unresolved Decide in Salon returns 422
        $payRes = $this->actingAs($this->admin)->postJson(route('appointments.revenue.storePayment', $appointment->id), [
            'payments' => ['cash' => 0],
        ]);
        $payRes->assertStatus(422)
            ->assertJsonPath('message', $expectedMsg);
        $this->assertSame(0, Sale::where('appointment_id', $appointment->id)->count());

        // 2. Attempting GET checkout page via JSON/AJAX or status='completed' returns 422
        $this->actingAs($this->admin)->getJson(route('appointments.revenue.payment', $appointment->id))
            ->assertStatus(422)
            ->assertJsonPath('message', $expectedMsg);

        $this->actingAs($this->admin)->patchJson(route('appointments.updateStatus', $appointment->id), [
            'status' => 'completed',
        ])->assertStatus(422)
            ->assertJsonPath('message', $expectedMsg);

        // 3. Once a real service is added, Checkout succeeds normally!
        $this->actingAs($this->admin)->postJson("/appointments/{$appointment->id}/services", [
            'service_id' => $this->svcHaircut->id,
        ])->assertOk();

        $this->actingAs($this->admin)->post(route('appointments.revenue.storePayment', $appointment->id), [
            'payments' => ['cash' => 140],
        ])->assertRedirect(route('appointments.revenue.index'));

        $appointment->refresh();
        $this->assertFalse($appointment->decide_in_salon);
        $this->assertSame('completed', $appointment->status);
        $this->assertSame(1, Sale::where('appointment_id', $appointment->id)->count());
    }
}
