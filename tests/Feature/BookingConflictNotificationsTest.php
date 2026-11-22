<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Combo;
use App\Models\Service;
use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\StaffBlock;
use App\Models\StaffTimeOff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingConflictNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Staff $aisha;
    private Staff $noor;
    private Staff $saraUnskilled;
    private Service $svcHaircut;
    private Service $svcFacial;
    private Service $svcManicure;
    private Combo $vipCombo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->aisha = Staff::create([
            'name' => 'Aisha',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        $this->noor = Staff::create([
            'name' => 'Noor',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        $this->saraUnskilled = Staff::create([
            'name' => 'Sara',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        $this->svcHaircut = Service::create([
            'name' => 'Signature Haircut',
            'category' => 'Hair',
            'price' => 120,
            'duration' => 60,
        ]);

        $this->svcFacial = Service::create([
            'name' => 'Hydra Glow Facial',
            'category' => 'Facial',
            'price' => 250,
            'duration' => 60,
        ]);

        $this->svcManicure = Service::create([
            'name' => 'Luxury Manicure',
            'category' => 'Nails',
            'price' => 90,
            'duration' => 30,
        ]);

        // Aisha & Noor have Haircut + Facial; only Aisha has Manicure; Sara has none.
        $this->svcHaircut->staff()->sync([$this->aisha->id, $this->noor->id]);
        $this->svcFacial->staff()->sync([$this->aisha->id, $this->noor->id]);
        $this->svcManicure->staff()->sync([$this->aisha->id]);

        $this->vipCombo = Combo::create([
            'name' => 'Glow 3-in-1 Combo',
            'price' => 399,
            'quantity_included' => 3,
            'validity_days' => 7,
            'default_duration' => 60,
            'status' => 'active',
        ]);
        $this->vipCombo->services()->sync([
            $this->svcHaircut->id,
            $this->svcFacial->id,
            $this->svcManicure->id,
        ]);
    }

    private function openSlotDay(int $daysAhead = 2): Carbon
    {
        $day = Carbon::today()->addDays($daysAhead)->setTime(13, 0, 0);
        if ($day->isFriday()) {
            $day->addDay();
        }
        return $day;
    }

    public function test_staff_off_blocks_create_edit_and_reschedule_with_exact_reasons(): void
    {
        $slot = $this->openSlotDay(2);
        $dateStr = $slot->toDateString();
        $formattedDate = $slot->format('d M Y');

        // 1. Full-day TimeOff blocks create with exact `staff_off` message
        StaffTimeOff::create([
            'staff_id' => $this->aisha->id,
            'start_date' => $dateStr,
            'end_date' => $dateStr,
            'reason' => 'on-leave',
        ]);

        $createOffRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Mariam Al-Thani',
            'phone' => '+97455110011',
            'appointment_datetime' => $dateStr . 'T13:00',
            'branch' => 'old_airport',
            'staff_id' => $this->aisha->id,
            'service_name' => [$this->svcHaircut->name],
        ]);

        $createOffRes->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'staff_off')
            ->assertJsonPath('message', "Cannot book: Aisha is off duty on {$formattedDate}.");

        // Create a valid booking on Noor so we can test editing & rescheduling onto off-duty / blocked / outside-shift slots
        $validCreate = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Mariam Al-Thani',
            'phone' => '+97455110011',
            'appointment_datetime' => $dateStr . 'T13:00',
            'branch' => 'old_airport',
            'staff_id' => $this->noor->id,
            'service_name' => [$this->svcHaircut->name],
        ])->assertStatus(201);

        $appt = Appointment::findOrFail($validCreate->json('appointment.id'));

        // 2. Rescheduling Noor's booking onto Aisha (who is off duty all day) returns `staff_off` with "Cannot reschedule:"
        $reschedOffDay = $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $appt), [
            'date' => $dateStr,
            'time' => '13:00',
            'staff_id' => $this->aisha->id,
        ]);

        $reschedOffDay->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'staff_off')
            ->assertJsonPath('message', "Cannot reschedule: Aisha is off duty on {$formattedDate}.");

        // 3. Calendar Blocked slot (`StaffBlock`) on Noor from 3:00 PM to 4:00 PM blocks create, edit, and reschedule
        StaffBlock::create([
            'staff_id' => $this->noor->id,
            'date' => $dateStr,
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
            'reason' => 'Break',
            'created_by' => $this->admin->id,
        ]);

        $createBlocked = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Dana Client',
            'phone' => '+97455110022',
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'old_airport',
            'staff_id' => $this->noor->id,
            'service_name' => [$this->svcHaircut->name],
        ]);

        $createBlocked->assertStatus(422)
            ->assertJsonPath('reason', 'staff_off')
            ->assertJsonPath('message', 'Cannot book: Noor is unavailable / blocked from 3:00 PM to 4:00 PM.');

        $editBlocked = $this->actingAs($this->admin)->putJson(route('appointments.update', $appt), [
            'customer_name' => $appt->customer_name,
            'phone' => $appt->phone,
            'appointment_datetime' => $dateStr . 'T15:30',
            'branch' => 'old_airport',
            'staff_id' => $this->noor->id,
            'service_name' => [$this->svcHaircut->name],
            'status' => 'pending',
        ]);

        $editBlocked->assertStatus(422)
            ->assertJsonPath('reason', 'staff_off')
            ->assertJsonPath('message', 'Cannot reschedule: Noor is unavailable / blocked from 3:00 PM to 4:00 PM.');

        $reschedBlocked = $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $appt), [
            'date' => $dateStr,
            'time' => '15:00',
            'staff_id' => $this->noor->id,
        ]);

        $reschedBlocked->assertStatus(422)
            ->assertJsonPath('reason', 'staff_off')
            ->assertJsonPath('message', 'Cannot reschedule: Noor is unavailable / blocked from 3:00 PM to 4:00 PM.');

        // 4. Shift hours check: Noor only works 12:00 PM – 6:00 PM on that weekday; moving to 7:00 PM triggers `staff_off`
        $pattern = ShiftPattern::create([
            'staff_id' => $this->noor->id,
            'repeat_frequency' => 'weekly',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => null,
        ]);
        ShiftBlock::create([
            'shift_pattern_id' => $pattern->id,
            'day_of_week' => $slot->dayOfWeek,
            'start_time' => '12:00:00',
            'end_time' => '18:00:00',
        ]);

        $reschedOutsideShift = $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $appt), [
            'date' => $dateStr,
            'time' => '19:00',
            'staff_id' => $this->noor->id,
        ]);

        $reschedOutsideShift->assertStatus(422)
            ->assertJsonPath('reason', 'staff_off')
            ->assertJsonPath('message', 'Cannot reschedule: Noor is unavailable / blocked from 7:00 PM to 8:00 PM.');
    }

    public function test_missing_skill_blocks_concrete_services_with_eligible_staff_and_bypasses_tbd_combo(): void
    {
        $slot = $this->openSlotDay(3);
        $dateStr = $slot->toDateString();

        // 1. Booking Signature Haircut on Sara (unskilled) returns 422 `missing_skill` + lists Aisha, Noor
        $createUnskilled = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Huda Client',
            'phone' => '+97455220011',
            'appointment_datetime' => $dateStr . 'T12:00',
            'branch' => 'old_airport',
            'staff_id' => $this->saraUnskilled->id,
            'service_name' => [$this->svcHaircut->name],
        ]);

        $createUnskilled->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'missing_skill')
            ->assertJsonPath(
                'message',
                'Cannot book: Sara does not have the required skill for Signature Haircut. Eligible staff: Aisha, Noor.'
            );

        // 2. Booking a valid appointment on Aisha, then editing or rescheduling onto Sara triggers `missing_skill`
        $created = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Huda Client',
            'phone' => '+97455220011',
            'appointment_datetime' => $dateStr . 'T12:00',
            'branch' => 'old_airport',
            'staff_id' => $this->aisha->id,
            'service_name' => [$this->svcManicure->name],
        ])->assertStatus(201);

        $appt = Appointment::findOrFail($created->json('appointment.id'));

        $editUnskilled = $this->actingAs($this->admin)->putJson(route('appointments.update', $appt), [
            'customer_name' => $appt->customer_name,
            'phone' => $appt->phone,
            'appointment_datetime' => $dateStr . 'T12:00',
            'branch' => 'old_airport',
            'staff_id' => $this->noor->id, // Noor lacks Luxury Manicure; only Aisha is eligible
            'service_name' => [$this->svcManicure->name],
            'status' => 'pending',
        ]);

        $editUnskilled->assertStatus(422)
            ->assertJsonPath('reason', 'missing_skill')
            ->assertJsonPath(
                'message',
                'Cannot book: Noor does not have the required skill for Luxury Manicure. Eligible staff: Aisha.'
            );

        $reschedUnskilled = $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $appt), [
            'date' => $dateStr,
            'time' => '14:00',
            'staff_id' => $this->saraUnskilled->id,
        ]);

        $reschedUnskilled->assertStatus(422)
            ->assertJsonPath('reason', 'missing_skill')
            ->assertJsonPath(
                'message',
                'Cannot reschedule: Sara does not have the required skill for Luxury Manicure. Eligible staff: Aisha.'
            );

        // 3. Bypass Rule: Combo booked in "Choose Services in Salon" mode (0 specific sub-services selected yet)
        // skips `missing_skill` even on Sara (who has 0 service skills) until actual services are selected!
        $comboTbdCreate = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Reem Combo Guest',
            'phone' => '+97455229900',
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'old_airport',
            'staff_id' => $this->saraUnskilled->id,
            'packages' => [
                [
                    'combo_id' => $this->vipCombo->id,
                    'immediate_service_ids' => [],
                    'pending_service_ids' => [],
                ],
            ],
        ]);

        $comboTbdCreate->assertStatus(201)
            ->assertJsonPath('success', true);

        $comboAppt = Appointment::findOrFail($comboTbdCreate->json('appointment.id'));

        // Rescheduling that TBD Combo to another slot on Sara also bypasses `missing_skill`
        $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $comboAppt), [
            'date' => $dateStr,
            'time' => '16:30',
            'staff_id' => $this->saraUnskilled->id,
        ])->assertOk()->assertJsonPath('success', true);

        // Once concrete sub-services are chosen in-salon on Sara, `missing_skill` enforces cleanly!
        $clientPkg = $comboAppt->clientPackages()->firstOrFail();
        $chooseConcreteOnUnskilled = $this->actingAs($this->admin)->patchJson(
            route('appointments.comboServices.update', [$comboAppt, $clientPkg]),
            [
                'today_service_ids' => [$this->svcHaircut->id],
                'pending_service_ids' => [],
            ]
        );

        $chooseConcreteOnUnskilled->assertStatus(422)
            ->assertJsonPath('reason', 'missing_skill')
            ->assertJsonPath(
                'message',
                'Cannot book: Sara does not have the required skill for Signature Haircut. Eligible staff: Aisha, Noor.'
            );
    }

    public function test_staff_busy_overlap_cancelled_no_show_exclusion_self_exclusion_and_unscheduled_unassigned_bypasses(): void
    {
        $slot = $this->openSlotDay(4);
        $dateStr = $slot->toDateString();

        // 1. Existing active booking for Aisha from 1:00 PM to 2:00 PM (Signature Haircut = 60 min)
        $firstCreate = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Existing Guest',
            'phone' => '+97455330011',
            'appointment_datetime' => $dateStr . 'T13:00',
            'branch' => 'old_airport',
            'staff_id' => $this->aisha->id,
            'service_name' => [$this->svcHaircut->name],
        ])->assertStatus(201);

        $firstAppt = Appointment::findOrFail($firstCreate->json('appointment.id'));

        // 2. Creating an overlapping booking on Aisha at 1:30 PM returns 422 `staff_busy` with exact conflict window
        $busyCreate = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Overlap Guest',
            'phone' => '+97455330022',
            'appointment_datetime' => $dateStr . 'T13:30',
            'branch' => 'old_airport',
            'staff_id' => $this->aisha->id,
            'service_name' => [$this->svcFacial->name],
        ]);

        $busyCreate->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('reason', 'staff_busy')
            ->assertJsonPath(
                'message',
                'Cannot book: Aisha is already busy with another booking from 1:00 PM to 2:00 PM.'
            );

        // 3. Create a non-overlapping booking at 12:15 PM (Luxury Manicure = 30 min -> 12:15 PM to 12:45 PM),
        // then test drag-drop reschedule AND duration-resize collision into 1:00 PM–2:00 PM!
        $secondCreate = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Morning Guest',
            'phone' => '+97455330033',
            'appointment_datetime' => $dateStr . 'T12:15',
            'branch' => 'old_airport',
            'staff_id' => $this->aisha->id,
            'service_name' => [$this->svcManicure->name],
        ])->assertStatus(201);

        $secondAppt = Appointment::findOrFail($secondCreate->json('appointment.id'));

        $busyResched = $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $secondAppt), [
            'date' => $dateStr,
            'time' => '13:15',
            'staff_id' => $this->aisha->id,
        ]);

        $busyResched->assertStatus(422)
            ->assertJsonPath('reason', 'staff_busy')
            ->assertJsonPath(
                'message',
                'Cannot reschedule: Aisha is already busy with another booking from 1:00 PM to 2:00 PM.'
            );

        // Resizing 12:15 PM booking from 30m -> 75m (ending 1:30 PM) collides with 1:00 PM–2:00 PM
        $busyResize = $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $secondAppt), [
            'date' => $dateStr,
            'time' => '12:15',
            'staff_id' => $this->aisha->id,
            'duration' => 75,
        ]);

        $busyResize->assertStatus(422)
            ->assertJsonPath('reason', 'staff_busy')
            ->assertJsonPath(
                'message',
                'Cannot reschedule: Aisha is already busy with another booking from 1:00 PM to 2:00 PM.'
            );

        // Editing $secondAppt via PUT into 1:15 PM also returns `staff_busy`
        $busyEdit = $this->actingAs($this->admin)->putJson(route('appointments.update', $secondAppt), [
            'customer_name' => $secondAppt->customer_name,
            'phone' => $secondAppt->phone,
            'appointment_datetime' => $dateStr . 'T13:15',
            'branch' => 'old_airport',
            'staff_id' => $this->aisha->id,
            'service_name' => [$this->svcManicure->name],
            'status' => 'pending',
        ]);

        $busyEdit->assertStatus(422)
            ->assertJsonPath('reason', 'staff_busy')
            ->assertJsonPath(
                'message',
                'Cannot reschedule: Aisha is already busy with another booking from 1:00 PM to 2:00 PM.'
            );

        // 4. Self-Overlap Exclusion: Editing or rescheduling/resizing $firstAppt within its own 1:00 PM–2:00 PM slot NEVER conflicts with itself!
        $this->actingAs($this->admin)->putJson(route('appointments.update', $firstAppt), [
            'customer_name' => 'Existing Guest Updated',
            'phone' => $firstAppt->phone,
            'appointment_datetime' => $dateStr . 'T13:15',
            'branch' => 'old_airport',
            'staff_id' => $this->aisha->id,
            'service_name' => [$this->svcHaircut->name],
            'status' => 'arrived',
        ])->assertOk()->assertJsonPath('success', true);

        $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $firstAppt), [
            'date' => $dateStr,
            'time' => '13:00',
            'staff_id' => $this->aisha->id,
            'duration' => 60,
        ])->assertOk()->assertJsonPath('success', true);

        // 5. Cancelled and No Show appointments are ignored during overlap checks
        $firstAppt->update(['status' => 'cancelled']);
        $bookOverCancelled = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Rebooked Slot Guest',
            'phone' => '+97455330044',
            'appointment_datetime' => $dateStr . 'T13:00',
            'branch' => 'old_airport',
            'staff_id' => $this->aisha->id,
            'service_name' => [$this->svcHaircut->name],
        ])->assertStatus(201);

        $rebookedAppt = Appointment::findOrFail($bookOverCancelled->json('appointment.id'));
        $rebookedAppt->update(['status' => 'no_show']);

        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'After No Show Guest',
            'phone' => '+97455330055',
            'appointment_datetime' => $dateStr . 'T13:00',
            'branch' => 'old_airport',
            'staff_id' => $this->aisha->id,
            'service_name' => [$this->svcHaircut->name],
        ])->assertStatus(201);

        // 6. Bypass Rule: `Pending time — add to Unscheduled Queue` skips time-overlap and shift-hour checks
        $unschedRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Unscheduled Queue Guest',
            'phone' => '+97455330066',
            'unscheduled' => '1',
            'appointment_datetime' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->aisha->id,
            'service_name' => [$this->svcHaircut->name],
        ])->assertStatus(201)->assertJsonPath('success', true);

        $unschedAppt = Appointment::findOrFail($unschedRes->json('appointment.id'));
        $this->assertNull($unschedAppt->start_time);

        // 7. Bypass Rule: Unassigned (`staff_id` empty/null) skips all staff-specific checks on create and edit
        $unassignedCreate = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Unassigned Walk-in',
            'phone' => '+97455330077',
            'appointment_datetime' => $dateStr . 'T13:00',
            'branch' => 'old_airport',
            'staff_id' => '',
            'service_name' => [$this->svcHaircut->name],
        ])->assertStatus(201);

        $unassignedAppt = Appointment::findOrFail($unassignedCreate->json('appointment.id'));
        $this->assertNull($unassignedAppt->staff_id);

        $this->actingAs($this->admin)->putJson(route('appointments.update', $unassignedAppt), [
            'customer_name' => 'Unassigned Walk-in',
            'phone' => '+97455330077',
            'appointment_datetime' => $dateStr . 'T14:00',
            'branch' => 'old_airport',
            'staff_id' => '',
            'service_name' => [$this->svcHaircut->name],
            'status' => 'pending',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_calendar_and_bookings_views_render_minimal_conflict_banners_and_toast_without_raw_alerts(): void
    {
        $calHtml = $this->actingAs($this->admin)
            ->get(route('appointments.calendar'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="fbBookingErrorBanner"', $calHtml);
        $this->assertStringContainsString('id="drawerConflictBanner"', $calHtml);
        $this->assertStringContainsString('id="calToastContainer"', $calHtml);
        $this->assertDoesNotMatchRegularExpression('/(?<![\.\w])alert\s*\(/', $calHtml);

        $indexHtml = $this->actingAs($this->admin)
            ->get(route('appointments.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="editModalError"', $indexHtml);
        $this->assertStringContainsString('id="editModalErrorText"', $indexHtml);
        $this->assertDoesNotMatchRegularExpression('/(?<![\.\w])alert\s*\(/', $indexHtml);
    }
}
