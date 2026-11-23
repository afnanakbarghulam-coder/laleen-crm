<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceFreebiePackage;
use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EnhancedCalendarOvertimeAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Service $keratin;
    private Service $threading;
    private Staff $staffInShift;
    private Staff $staffOt1h;
    private Staff $staffOt2h;
    private Staff $staffShiftEnded;
    private Staff $staffDayOff;
    private Staff $staffUnskilled;
    private Staff $staffWrongBranch;
    private Customer $customer;
    private Carbon $targetDate;

    protected function setUp(): void
    {
        parent::setUp();

        // Tuesday 2026-11-24 (old_airport branch hours: 10:00 - 22:00)
        $this->targetDate = Carbon::parse('2026-11-24', 'Asia/Qatar')->startOfDay();
        Carbon::setTestNow($this->targetDate->copy()->setTime(10, 0));

        $this->admin = User::factory()->create([
            'name' => 'Salon Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->keratin = Service::create([
            'name' => 'Keratin Treatment',
            'price' => 450.00,
            'duration' => 240,
        ]);

        $this->threading = Service::create([
            'name' => 'Eyebrow Threading',
            'price' => 40.00,
            'duration' => 30,
        ]);

        $this->customer = Customer::create([
            'name' => 'Noor Al-Thani',
            'phone' => '+97455112233',
            'branch' => 'old_airport',
        ]);

        $this->staffInShift = $this->makeStaffWithShift('Amina Late', 'old_airport', '14:00:00', '23:30:00', [$this->keratin->id]);
        $this->staffOt1h = $this->makeStaffWithShift('Bina Standard', 'old_airport', '11:00:00', '22:00:00', [$this->keratin->id]);
        $this->staffOt2h = $this->makeStaffWithShift('Chitra Early', 'old_airport', '10:00:00', '21:00:00', [$this->keratin->id]);
        $this->staffShiftEnded = $this->makeStaffWithShift('Dina Morning', 'old_airport', '09:00:00', '19:00:00', [$this->keratin->id]);
        $this->staffDayOff = $this->makeStaffWithShift('Elena Off', 'old_airport', '10:00:00', '22:00:00', [$this->keratin->id]);
        $this->staffUnskilled = $this->makeStaffWithShift('Farah Threading', 'old_airport', '10:00:00', '23:30:00', [$this->threading->id]);
        $this->staffWrongBranch = $this->makeStaffWithShift('Gina Wakrah', 'wakrah', '10:00:00', '23:30:00', [$this->keratin->id]);

        StaffTimeOff::create([
            'staff_id' => $this->staffDayOff->id,
            'start_date' => $this->targetDate->toDateString(),
            'end_date' => $this->targetDate->toDateString(),
            'reason' => 'on-leave',
            'notes' => 'Scheduled leave',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeStaffWithShift(string $name, string $branch, string $start, string $end, array $serviceIds): Staff
    {
        $staff = Staff::create([
            'name' => $name,
            'branch' => $branch,
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        if (! empty($serviceIds)) {
            $staff->services()->sync($serviceIds);
        }

        $pattern = ShiftPattern::create([
            'staff_id' => $staff->id,
            'repeat_frequency' => 'weekly',
            'start_date' => $this->targetDate->copy()->subWeek()->toDateString(),
            'end_date' => null,
        ]);

        ShiftBlock::create([
            'shift_pattern_id' => $pattern->id,
            'day_of_week' => $this->targetDate->dayOfWeek,
            'start_time' => $start,
            'end_time' => $end,
        ]);

        return $staff;
    }

    public function test_available_staff_orders_in_shift_before_overtime_and_excludes_unskilled_day_off_and_ended_shift(): void
    {
        $dateStr = $this->targetDate->toDateString();

        $res = $this->actingAs($this->admin)->getJson(route('appointments.availableStaff', [
            'services' => [$this->keratin->name],
            'appointment_datetime' => $dateStr . 'T19:00',
            'branch' => 'old_airport',
        ]));

        $res->assertOk();
        $list = $res->json();

        // Only skilled old_airport staff on duty at 19:00 are returned:
        // Tier 1 In Shift first (Amina Late), then Tier 2 Overtime sorted by OT mins (Bina +60m, Chitra +120m).
        // Dina (shift ends 19:00), Elena (Day Off), Farah (Unskilled), Gina (Wakrah branch) are excluded.
        $this->assertSame(
            [$this->staffInShift->id, $this->staffOt1h->id, $this->staffOt2h->id],
            array_column($list, 'id')
        );

        $this->assertFalse($list[0]['is_overtime']);
        $this->assertSame(0, $list[0]['overtime_minutes']);

        $this->assertTrue($list[1]['is_overtime']);
        $this->assertSame(60, $list[1]['overtime_minutes']);
        $this->assertSame('22:00', $list[1]['shift_end']);

        $this->assertTrue($list[2]['is_overtime']);
        $this->assertSame(120, $list[2]['overtime_minutes']);
        $this->assertSame('21:00', $list[2]['shift_end']);
    }

    public function test_overtime_booking_saves_persists_flags_renders_in_calendar_and_clears_when_moved_into_shift(): void
    {
        $dateStr = $this->targetDate->toDateString();

        $storeRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T19:00',
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->staffOt2h->id,
            'service_name' => [$this->keratin->name],
            'service_staff' => [$this->staffOt2h->id],
        ]);

        $storeRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.is_overtime', true)
            ->assertJsonPath('appointment.overtime_minutes', 120);

        $apptId = (int) $storeRes->json('appointment.id');
        $appt = Appointment::with('appointmentServices')->findOrFail($apptId);

        $this->assertTrue((bool) $appt->is_overtime);
        $this->assertSame(120, (int) $appt->overtime_minutes);
        $this->assertCount(1, $appt->appointmentServices);
        $this->assertTrue((bool) $appt->appointmentServices[0]->is_overtime);
        $this->assertSame(120, (int) $appt->appointmentServices[0]->overtime_minutes);

        // Day calendar data must preserve unclamped 23:00 end time, extend grid slots to 23:00, and include OT flags
        $dayRes = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'day',
            'date' => $dateStr,
            'branch' => 'old_airport',
        ]));
        $dayRes->assertOk();
        $this->assertContains('23:00', $dayRes->json('time_slots'));

        $staffSlices = $dayRes->json("appointments.{$this->staffOt2h->id}");
        $this->assertCount(1, $staffSlices);
        $this->assertSame('19:00', $staffSlices[0]['start']);
        $this->assertSame('23:00', $staffSlices[0]['end']);
        $this->assertSame(1380, $staffSlices[0]['end_minutes']);
        $this->assertTrue($staffSlices[0]['is_overtime']);
        $this->assertSame(120, $staffSlices[0]['overtime_minutes']);

        // Week calendar data must also expose OT flags
        $weekRes = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'week',
            'date' => $dateStr,
            'branch' => 'old_airport',
        ]));
        $weekRes->assertOk();
        $weekCards = $weekRes->json("appointments.{$this->staffOt2h->id}.{$dateStr}");
        $this->assertCount(1, $weekCards);
        $this->assertTrue($weekCards[0]['is_overtime']);
        $this->assertSame(120, $weekCards[0]['overtime_minutes']);

        // Drawer show payload exposes OT flags
        $showRes = $this->actingAs($this->admin)->getJson(route('appointments.show', $apptId));
        $showRes->assertOk()
            ->assertJsonPath('is_overtime', true)
            ->assertJsonPath('overtime_minutes', 120)
            ->assertJsonPath('services.0.is_overtime', true)
            ->assertJsonPath('services.0.overtime_minutes', 120);

        // Reschedule earlier to 15:00-19:00 (strictly inside Chitra's 10:00-21:00 shift) -> overtime clears to false / 0
        $reschedRes = $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $apptId), [
            'appointment_datetime' => $dateStr . 'T15:00',
            'staff_id' => $this->staffOt2h->id,
        ]);
        $reschedRes->assertOk()->assertJsonPath('success', true);

        $appt->refresh();
        $appt->load('appointmentServices');
        $this->assertFalse((bool) $appt->is_overtime);
        $this->assertSame(0, (int) $appt->overtime_minutes);
        $this->assertFalse((bool) $appt->appointmentServices[0]->is_overtime);
        $this->assertSame(0, (int) $appt->appointmentServices[0]->overtime_minutes);
    }

    public function test_overtime_strictly_blocks_after_shift_before_shift_day_off_unskilled_double_booking_and_missing_agent(): void
    {
        $dateStr = $this->targetDate->toDateString();

        // 1) Starting AT shift end (21:00 when shift is 10:00-21:00) -> strictly blocked
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T21:00',
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->staffOt2h->id,
            'service_name' => [$this->keratin->name],
            'service_staff' => [$this->staffOt2h->id],
        ])->assertStatus(422)->assertJsonPath('reason', 'staff_off');

        // 2) Starting BEFORE shift start (11:00 when Amina's shift starts 14:00) -> strictly blocked
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T11:00',
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->staffInShift->id,
            'service_name' => [$this->keratin->name],
            'service_staff' => [$this->staffInShift->id],
        ])->assertStatus(422)->assertJsonPath('reason', 'staff_off');

        // 3) Day Off / Leave staff -> strictly blocked
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T19:00',
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->staffDayOff->id,
            'service_name' => [$this->keratin->name],
            'service_staff' => [$this->staffDayOff->id],
        ])->assertStatus(422)->assertJsonPath('reason', 'staff_off');

        // 4) Unskilled staff -> strictly blocked even though shift runs until 23:30
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T19:00',
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->staffUnskilled->id,
            'service_name' => [$this->keratin->name],
            'service_staff' => [$this->staffUnskilled->id],
        ])->assertStatus(422)->assertJsonPath('reason', 'missing_skill');

        // 5) Missing mandatory Booking Agent -> strictly blocked even for valid overtime slot
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T19:00',
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->staffOt2h->id,
            'service_name' => [$this->keratin->name],
            'service_staff' => [$this->staffOt2h->id],
        ])->assertStatus(422)->assertJsonValidationErrors(['booking_agent_id']);

        // 6) Book overtime 19:00-23:00 for Chitra, then verify overlapping booking at 20:30 is blocked as staff_busy
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T19:00',
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->staffOt2h->id,
            'service_name' => [$this->keratin->name],
            'service_staff' => [$this->staffOt2h->id],
        ])->assertStatus(201);

        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Second Client',
            'phone' => '+97455998877',
            'appointment_datetime' => $dateStr . 'T20:30',
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->staffOt2h->id,
            'service_name' => [$this->keratin->name],
            'service_staff' => [$this->staffOt2h->id],
        ])->assertStatus(422)->assertJsonPath('reason', 'staff_busy');

        // 7) Midnight-crossing overtime booking: Bina (shift 11:00-22:00) starts 4h Keratin at 21:00 -> ends 01:00 (+180m OT)
        $midnightRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Late Night Client',
            'phone' => '+97455001122',
            'appointment_datetime' => $dateStr . 'T21:00',
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->staffOt1h->id,
            'service_name' => [$this->keratin->name],
            'service_staff' => [$this->staffOt1h->id],
        ]);
        $midnightRes->assertStatus(201)
            ->assertJsonPath('appointment.is_overtime', true)
            ->assertJsonPath('appointment.overtime_minutes', 180);

        // Overlapping booking on Bina at 21:30 must be rejected as staff_busy
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => 'Overlap Late Client',
            'phone' => '+97455003344',
            'appointment_datetime' => $dateStr . 'T21:30',
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->staffOt1h->id,
            'service_name' => [$this->keratin->name],
            'service_staff' => [$this->staffOt1h->id],
        ])->assertStatus(422)->assertJsonPath('reason', 'staff_busy');
    }

    public function test_service_plus_freebies_anchor_overtime_booking_persists_overtime_metadata(): void
    {
        $dateStr = $this->targetDate->toDateString();

        $pkg = ServiceFreebiePackage::create([
            'name' => 'Keratin treatment+2 free services',
            'anchor_service_id' => $this->keratin->id,
            'price' => 450.00,
            'free_choices_count' => 2,
            'validity_days' => 14,
            'is_active' => true,
        ]);
        $pkg->eligibleServices()->sync([$this->threading->id]);

        $res = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => $dateStr . 'T19:00',
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'freebie_packages' => [
                [
                    'package_id' => $pkg->id,
                    'anchor_staff_id' => $this->staffOt2h->id,
                    'slots' => [
                        ['mode' => 'save_for_later'],
                        ['mode' => 'save_for_later'],
                    ],
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.is_overtime', true)
            ->assertJsonPath('appointment.overtime_minutes', 120);

        $appt = Appointment::with('appointmentServices')->findOrFail($res->json('appointment.id'));
        $this->assertTrue((bool) $appt->is_overtime);
        $this->assertSame(120, (int) $appt->overtime_minutes);
        $this->assertCount(1, $appt->appointmentServices);
        $this->assertTrue((bool) $appt->appointmentServices[0]->is_overtime);
        $this->assertSame(120, (int) $appt->appointmentServices[0]->overtime_minutes);
    }
}
