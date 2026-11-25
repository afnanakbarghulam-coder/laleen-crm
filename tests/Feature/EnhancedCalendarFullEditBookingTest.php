<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\ClientFreebieCredit;
use App\Models\ClientPackage;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceFreebiePackage;
use App\Models\ShiftBlock;
use App\Models\ShiftPattern;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EnhancedCalendarFullEditBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $areeba;
    private Staff $specialist;
    private Staff $unskilledStaff;
    private Service $blowdry;
    private Service $manicure;
    private Service $facial;
    private Service $hairColor;
    private Service $keratin;
    private Combo $bridalCombo;
    private Combo $luxuryCombo;
    private ServiceFreebiePackage $freebiePkg;
    private Customer $customer;
    private Carbon $targetDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->targetDate = Carbon::parse('2026-11-24', 'Asia/Qatar')->startOfDay();
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

        $this->blowdry = Service::create(['name' => 'Blowdry', 'price' => 80.00, 'duration' => 30]);
        $this->manicure = Service::create(['name' => 'Manicure', 'price' => 70.00, 'duration' => 45]);
        $this->facial = Service::create(['name' => 'Facial', 'price' => 120.00, 'duration' => 60]);
        $this->hairColor = Service::create(['name' => 'Hair Color', 'price' => 250.00, 'duration' => 90]);
        $this->keratin = Service::create(['name' => 'Keratin Treatment', 'price' => 400.00, 'duration' => 240]);

        $this->bridalCombo = Combo::create([
            'name' => 'Bridal Glow Package',
            'price' => 200.00,
            'quantity_included' => 3,
            'validity_days' => 30,
            'is_active' => true,
        ]);
        $this->bridalCombo->services()->sync([$this->blowdry->id, $this->manicure->id, $this->facial->id]);

        $this->luxuryCombo = Combo::create([
            'name' => 'Luxury Combo',
            'price' => 450.00,
            'quantity_included' => 2,
            'validity_days' => 30,
            'is_active' => true,
        ]);
        $this->luxuryCombo->services()->sync([$this->hairColor->id, $this->keratin->id]);

        $this->freebiePkg = ServiceFreebiePackage::create([
            'name' => 'Keratin + 2 Free Services',
            'anchor_service_id' => $this->keratin->id,
            'price' => 380.00,
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
            $this->blowdry->id,
            $this->manicure->id,
            $this->facial->id,
            $this->hairColor->id,
            $this->keratin->id,
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

        $this->unskilledStaff = Staff::create([
            'name' => 'Nadia Nails',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $this->unskilledStaff->services()->sync([$this->manicure->id]);

        $this->customer = Customer::create([
            'name' => 'Mariam Al-Mansoor',
            'phone' => '+97455112233',
            'branch' => 'old_airport',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createInitialBridalComboBooking(string $time = '14:00'): Appointment
    {
        $dateStr = $this->targetDate->toDateString();

        $res = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => $this->areeba->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T{$time}",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->specialist->id,
            'packages' => [[
                'combo_id' => $this->bridalCombo->id,
                'immediate_service_ids' => [$this->blowdry->id, $this->manicure->id, $this->facial->id],
                'pending_service_ids' => [],
                'service_staff' => [
                    $this->blowdry->id => $this->specialist->id,
                    $this->manicure->id => $this->specialist->id,
                    $this->facial->id => $this->specialist->id,
                ],
            ]],
        ]);

        $res->assertStatus(201);

        return Appointment::with(['appointmentServices', 'clientPackages'])->findOrFail($res->json('appointment.id'));
    }

    public function test_swap_combo_to_different_combo_replaces_slices_without_self_conflict_and_updates_ad_revenue(): void
    {
        $dateStr = $this->targetDate->toDateString();

        $adLead = AdLeadEntry::create([
            'date' => $dateStr,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'category' => 'Bridal Package',
            'status' => 'pending',
            'ticket_amount' => 0,
            'created_by' => $this->admin->id,
        ]);

        $appt = $this->createInitialBridalComboBooking('14:00');
        $this->assertSame(200.00, (float) $appt->price);
        $this->assertCount(3, $appt->appointmentServices);
        $this->assertSame(1, ClientPackage::where('appointment_id', $appt->id)->count());

        $adLead->refresh();
        $this->assertSame('booked', $adLead->status);
        $this->assertSame(200.00, (float) $adLead->ticket_amount);

        // Swap Bridal Glow Package (200 QAR, 3 slices) -> Luxury Combo (450 QAR, 2 slices) on SAME specialist & time
        $editRes = $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appt->id), [
            'booking_agent_id' => $this->areeba->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T14:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->specialist->id,
            'notes' => 'Upgraded to Luxury Combo',
            'packages' => [[
                'combo_id' => $this->luxuryCombo->id,
                'immediate_service_ids' => [$this->hairColor->id, $this->keratin->id],
                'pending_service_ids' => [],
                'service_staff' => [
                    $this->hairColor->id => $this->specialist->id,
                    $this->keratin->id => $this->specialist->id,
                ],
            ]],
        ]);

        $editRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.combo_name', 'Luxury Combo')
            ->assertJsonPath('appointment.price', 450);

        $appt->refresh()->load(['appointmentServices', 'clientPackages']);
        $this->assertSame(450.00, (float) $appt->price);
        $this->assertSame('Luxury Combo', $appt->combo_name);
        $this->assertSame($this->areeba->id, (int) $appt->booking_agent_id);
        $this->assertSame('Upgraded to Luxury Combo', $appt->notes);

        // Zero orphaned packages or old Bridal Glow slices
        $this->assertSame(1, ClientPackage::where('appointment_id', $appt->id)->count());
        $this->assertSame('Luxury Combo', ClientPackage::where('appointment_id', $appt->id)->first()->combo_name);
        $this->assertCount(2, $appt->appointmentServices);
        $this->assertSame(['Hair Color', 'Keratin Treatment'], $appt->appointmentServices->sortBy('sort_order')->pluck('name')->values()->all());

        // Calendar grid returns ONLY the 2 new Luxury Combo blocks
        $calRes = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'day',
            'date' => $dateStr,
            'branch' => 'old_airport',
        ]));
        $calRes->assertOk();
        $slices = $calRes->json("appointments.{$this->specialist->id}");
        $this->assertCount(2, $slices);
        $this->assertSame('Luxury Combo', $slices[0]['combo_name']);
        $this->assertSame('14:00', $slices[0]['start']);
        $this->assertSame('15:30', $slices[0]['end']);
        $this->assertSame('15:30', $slices[1]['start']);
        $this->assertSame('19:30', $slices[1]['end']);

        // 14-day rolling Ad Lead attribution updated cleanly from 200 -> 450 with zero double-counting
        $adLead->refresh();
        $this->assertSame('booked', $adLead->status);
        $this->assertSame(450.00, (float) $adLead->ticket_amount);
        $this->assertSame(450.00, (float) $adLead->old_airport_amount);
    }

    public function test_replace_combo_with_standalone_services_and_service_plus_freebies_and_overtime_sync(): void
    {
        $dateStr = $this->targetDate->toDateString();
        $appt = $this->createInitialBridalComboBooking('19:00');
        $this->assertFalse((bool) $appt->is_overtime);

        // 1) Replace Bridal Glow Package with 2 standalone services at 19:00 (90m + 240m -> ends 00:30, +150m OT past 22:00)
        $standaloneRes = $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appt->id), [
            'booking_agent_id' => $this->areeba->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T19:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->specialist->id,
            'service_name' => [$this->hairColor->name, $this->keratin->name],
            'service_price' => [250.00, 400.00],
            'service_staff' => [$this->specialist->id, $this->specialist->id],
        ]);

        $standaloneRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.combo_name', null)
            ->assertJsonPath('appointment.price', 650)
            ->assertJsonPath('appointment.is_overtime', true)
            ->assertJsonPath('appointment.overtime_minutes', 150);

        $appt->refresh()->load('appointmentServices');
        $this->assertNull($appt->combo_id);
        $this->assertNull($appt->combo_name);
        $this->assertSame(0, ClientPackage::where('appointment_id', $appt->id)->count());
        $this->assertCount(2, $appt->appointmentServices);
        $this->assertTrue((bool) $appt->is_overtime);
        $this->assertSame(150, (int) $appt->overtime_minutes);
        $this->assertSame($this->areeba->id, (int) $appt->booking_agent_id);

        // 2) Replace standalone services with Service + Freebies at 15:00-19:00 (back inside shift -> clears OT!)
        $freebieRes = $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appt->id), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T15:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'freebie_packages' => [[
                'package_id' => $this->freebiePkg->id,
                'anchor_staff_id' => $this->specialist->id,
                'slots' => [
                    ['mode' => 'save_for_later'],
                    ['mode' => 'save_for_later'],
                ],
            ]],
        ]);

        $freebieRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('appointment.price', 380)
            ->assertJsonPath('appointment.is_overtime', false)
            ->assertJsonPath('appointment.overtime_minutes', 0);

        $this->assertSame(2, ClientFreebieCredit::where('anchor_appointment_id', $appt->id)->count());

        // Drawer show() exposes freebie_packages payload for further edits
        $this->actingAs($this->admin)->getJson(route('appointments.show', $appt->id))
            ->assertOk()
            ->assertJsonPath('freebie_packages.0.package_id', $this->freebiePkg->id)
            ->assertJsonPath('booking_agent_select_value', 'salon_staff');

        // 3) Replace Service + Freebies with a single standalone Blowdry -> cleans up unpaid ClientFreebieCredits cleanly
        $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appt->id), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T15:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'service_name' => [$this->blowdry->name],
            'service_price' => [80.00],
            'service_staff' => [$this->specialist->id],
        ])->assertOk()->assertJsonPath('appointment.price', 80);

        $this->assertSame(0, ClientFreebieCredit::where('anchor_appointment_id', $appt->id)->count());
        $appt->refresh();
        $this->assertNull($appt->freebie_package_id);
        $this->assertCount(1, $appt->appointmentServices);
    }

    public function test_full_edit_enforces_other_client_conflict_skill_mandatory_agent_and_completed_paid_locks(): void
    {
        $dateStr = $this->targetDate->toDateString();
        $appt = $this->createInitialBridalComboBooking('14:00'); // 14:00 - 16:15

        // Another client booked on the same specialist at 17:00 - 18:30
        $otherClient = Customer::create([
            'name' => 'Fatima Other',
            'phone' => '+97455998877',
            'branch' => 'old_airport',
        ]);
        $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $otherClient->name,
            'phone' => $otherClient->phone,
            'appointment_datetime' => "{$dateStr}T17:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'staff_id' => $this->specialist->id,
            'service_name' => [$this->hairColor->name],
            'service_staff' => [$this->specialist->id],
        ])->assertStatus(201);

        // 1) Swapping to Luxury Combo (14:00 -> 19:30) overlaps Fatima's 17:00 slot -> strictly blocked with 422 staff_busy!
        $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appt->id), [
            'booking_agent_id' => $this->areeba->id,
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T14:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'packages' => [[
                'combo_id' => $this->luxuryCombo->id,
                'immediate_service_ids' => [$this->hairColor->id, $this->keratin->id],
                'service_staff' => [
                    $this->hairColor->id => $this->specialist->id,
                    $this->keratin->id => $this->specialist->id,
                ],
            ]],
        ])->assertStatus(422)->assertJsonPath('reason', 'staff_busy');

        // Confirm original Bridal Glow Package and 3 slices were NOT mutated on failed edit
        $appt->refresh()->load('appointmentServices');
        $this->assertSame('Bridal Glow Package', $appt->combo_name);
        $this->assertCount(3, $appt->appointmentServices);

        // 2) Missing mandatory Booking Agent -> 422
        $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appt->id), [
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T14:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'service_name' => [$this->blowdry->name],
            'service_staff' => [$this->specialist->id],
        ])->assertStatus(422)->assertJsonValidationErrors(['booking_agent_id']);

        // 3) Unskilled staff -> 422 missing_skill
        $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appt->id), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T14:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'service_name' => [$this->hairColor->name],
            'service_staff' => [$this->unskilledStaff->id],
        ])->assertStatus(422)->assertJsonPath('reason', 'missing_skill');

        // 4) Completed appointment -> blocked with 422 paid_locked
        $appt->update(['status' => 'completed']);
        $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appt->id), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T14:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'service_name' => [$this->blowdry->name],
            'service_staff' => [$this->specialist->id],
        ])->assertStatus(422)
            ->assertJsonPath('reason', 'paid_locked')
            ->assertJsonPath('message', 'Checked-out appointments cannot be modified.');

        // 5) Paid appointment (Sale exists even if status isn't completed) -> blocked with 422 paid_locked
        $appt->update(['status' => 'in_progress', 'paid_at' => now()]);
        Sale::create([
            'appointment_id' => $appt->id,
            'customer_id' => $this->customer->id,
            'branch' => 'old_airport',
            'subtotal' => 200.00,
            'discount' => 0,
            'total' => 200.00,
            'payment_method' => 'cash',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->putJson(route('appointments.fullUpdate', $appt->id), [
            'booking_agent_id' => 'salon_staff',
            'customer_name' => $this->customer->name,
            'phone' => $this->customer->phone,
            'appointment_datetime' => "{$dateStr}T14:00",
            'booking_date' => $dateStr,
            'branch' => 'old_airport',
            'service_name' => [$this->blowdry->name],
            'service_staff' => [$this->specialist->id],
        ])->assertStatus(422)->assertJsonPath('reason', 'paid_locked');
    }

    public function test_enhanced_calendar_renders_change_edit_booking_controls_and_swap_combo_ui(): void
    {
        $res = $this->actingAs($this->admin)->get(route('appointments.calendar'));
        $res->assertOk()
            ->assertSee('id="drawerChangeBookingBtn"', false)
            ->assertSee('Change / Edit Booking')
            ->assertSee('id="drawerQuickChangeBookingBtn"', false)
            ->assertSee('id="fbEditModeBanner"', false)
            ->assertSee('fb-combo-swap-select')
            ->assertSee('Checked-out appointments cannot be modified');
    }
}
