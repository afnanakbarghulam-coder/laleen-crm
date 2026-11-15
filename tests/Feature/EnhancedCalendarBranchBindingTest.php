<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EnhancedCalendarBranchBindingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Staff $aishaOldAirport;
    private Staff $laylaAlWakrah;
    private Staff $noorBothBranches;
    private Service $blowdryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->aishaOldAirport = Staff::create([
            'name' => 'Aisha Khan',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        $this->laylaAlWakrah = Staff::create([
            'name' => 'Layla Hassan',
            'branch' => 'wakrah',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        $this->noorBothBranches = Staff::create([
            'name' => 'Noor Shared',
            'branch' => 'both',
            'availability_status' => 'present',
            'bookable' => true,
        ]);

        $this->blowdryService = Service::create([
            'name' => 'Signature Blowdry',
            'category' => 'Hair',
            'price' => 150,
            'duration' => 60,
        ]);

        $this->blowdryService->staff()->sync([
            $this->aishaOldAirport->id,
            $this->laylaAlWakrah->id,
            $this->noorBothBranches->id,
        ]);
    }

    public function test_enhanced_calendar_hides_branch_dropdown_in_drawer_and_binds_hidden_active_branch(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('appointments.calendar', ['branch' => 'Al Wakrah']));

        $response->assertOk();

        // Redundant "-- Select Branch --" dropdown is completely removed from the drawer
        $response->assertDontSee('-- Select Branch --', false);
        $response->assertDontSee('<select id="bookBranch"', false);
        $response->assertDontSee('<select id="drawerRescheduleBranch"', false);

        // Hidden branch input is bound to normalized canonical 'wakrah' (Al Wakrah)
        $response->assertSee('type="hidden" name="branch" id="bookBranch" value="wakrah"', false);
        $response->assertSee('type="hidden" id="drawerRescheduleBranch"', false);

        // Top LOCATION filter and JS subheader label use canonical "Old Airport" and "Al Wakrah"
        $response->assertSee('<option value="old_airport">Old Airport</option>', false);
        $response->assertSee('<option value="wakrah">Al Wakrah</option>', false);
        $response->assertSee("Doesn't repeat", false);
        $response->assertSee('data-branch="old_airport"', false);
        $response->assertSee('data-branch="wakrah"', false);
    }

    public function test_creating_updating_and_rescheduling_bookings_normalize_and_save_under_active_calendar_branch(): void
    {
        $targetDate = Carbon::tomorrow()->setTime(13, 0, 0);
        if ($targetDate->isFriday()) {
            $targetDate->addDay();
        }
        $dateStr = $targetDate->toDateString();

        // 1. Create scheduled booking on Al Wakrah (passing display label or active_branch fallback)
        $storeRes = $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => 'Mariam Al-Thani',
            'phone' => '+97455112233',
            'appointment_datetime' => $dateStr . 'T13:00',
            'active_branch' => 'Al Wakrah',
            'staff_id' => $this->laylaAlWakrah->id,
            'service_name' => [$this->blowdryService->name],
        ]);
        $storeRes->assertRedirect();
        $storeRes->assertSessionHasNoErrors();

        $wakrahAppt = Appointment::where('customer_name', 'Mariam Al-Thani')->firstOrFail();
        $this->assertSame('wakrah', $wakrahAppt->branch);
        $this->assertSame($this->laylaAlWakrah->id, (int) $wakrahAppt->staff_id);

        // Customer profile branch also syncs cleanly for the Clients module branch filter
        $customer = Customer::findOrFail($wakrahAppt->customer_id);
        $this->assertSame('wakrah', $customer->branch);

        // Drawer JSON endpoint returns canonical display label "Al Wakrah" and raw key "wakrah"
        $showRes = $this->actingAs($this->admin)->getJson(route('appointments.show', $wakrahAppt));
        $showRes->assertOk()
            ->assertJsonPath('branch', 'Al Wakrah')
            ->assertJsonPath('branch_raw', 'wakrah');

        // Calendar data for Al Wakrah returns this booking, while Old Airport does not
        $wakrahCal = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'day',
            'date' => $dateStr,
            'branch' => 'Al Wakrah',
        ]));
        $wakrahCal->assertOk();
        $this->assertCount(1, $wakrahCal->json("appointments.{$this->laylaAlWakrah->id}") ?? []);

        $airportCal = $this->actingAs($this->admin)->getJson(route('appointments.calendar_data', [
            'view' => 'day',
            'date' => $dateStr,
            'branch' => 'old_airport',
        ]));
        $airportCal->assertOk();
        $this->assertEmpty($airportCal->json("appointments.{$this->laylaAlWakrah->id}") ?? []);

        // 2. Create Unscheduled Queue booking on Old Airport and drag/schedule it onto Aisha Khan's column
        $unschedRes = $this->actingAs($this->admin)->post(route('appointments.store'), [
            'customer_name' => 'Dana Al-Sulaiti',
            'phone' => '+97455443322',
            'booking_date' => $dateStr,
            'unscheduled' => '1',
            'branch' => 'Old Airport',
            'service_name' => [$this->blowdryService->name],
        ]);
        $unschedRes->assertRedirect();
        $unschedRes->assertSessionHasNoErrors();

        $unschedAppt = Appointment::where('customer_name', 'Dana Al-Sulaiti')->firstOrFail();
        $this->assertSame('old_airport', $unschedAppt->branch);
        $this->assertNull($unschedAppt->start_time);

        $reschedRes = $this->actingAs($this->admin)->patchJson(route('appointments.reschedule', $unschedAppt), [
            'appointment_datetime' => $dateStr . 'T14:00',
            'staff_id' => $this->aishaOldAirport->id,
            'branch' => 'Old Airport',
        ]);
        $reschedRes->assertOk()->assertJsonPath('success', true);

        $unschedAppt->refresh();
        $this->assertSame('old_airport', $unschedAppt->branch);
        $this->assertSame('14:00:00', $unschedAppt->start_time);
        $this->assertSame($this->aishaOldAirport->id, (int) $unschedAppt->staff_id);

        // 3. Updating appointment normalizes 'Wakrah' / 'Al Wakrah' -> 'wakrah' cleanly
        $updateRes = $this->actingAs($this->admin)->put(route('appointments.update', $wakrahAppt), [
            'customer_name' => 'Mariam Al-Thani',
            'phone' => '+97455112233',
            'appointment_datetime' => $dateStr . 'T15:00',
            'branch' => 'Wakrah',
            'staff_id' => $this->laylaAlWakrah->id,
            'service_name' => [$this->blowdryService->name],
        ]);
        $updateRes->assertRedirect();
        $updateRes->assertSessionHasNoErrors();

        $wakrahAppt->refresh();
        $this->assertSame('wakrah', $wakrahAppt->branch);
        $this->assertSame('15:00:00', $wakrahAppt->start_time);
    }

    public function test_team_member_options_and_booking_validation_scope_staff_to_active_calendar_branch(): void
    {
        $targetDate = Carbon::tomorrow()->setTime(13, 0, 0);
        if ($targetDate->isFriday()) {
            $targetDate->addDay();
        }
        $dateStr = $targetDate->toDateString();

        // Old Airport availableStaff returns Aisha Khan + Noor Shared, never Layla Hassan
        $airportStaffRes = $this->actingAs($this->admin)->getJson(route('appointments.availableStaff', [
            'decide_in_salon' => 1,
            'appointment_datetime' => $dateStr . 'T13:00',
            'branch' => 'Old Airport',
        ]));
        $airportStaffRes->assertOk();
        $airportNames = array_column($airportStaffRes->json(), 'name');
        $this->assertContains('Aisha Khan', $airportNames);
        $this->assertContains('Noor Shared', $airportNames);
        $this->assertNotContains('Layla Hassan', $airportNames);

        // Al Wakrah availableStaff returns Layla Hassan + Noor Shared, never Aisha Khan
        $wakrahStaffRes = $this->actingAs($this->admin)->getJson(route('appointments.availableStaff', [
            'services' => [$this->blowdryService->name],
            'appointment_datetime' => $dateStr . 'T13:00',
            'branch' => 'Al Wakrah',
        ]));
        $wakrahStaffRes->assertOk();
        $wakrahNames = array_column($wakrahStaffRes->json(), 'name');
        $this->assertContains('Layla Hassan', $wakrahNames);
        $this->assertContains('Noor Shared', $wakrahNames);
        $this->assertNotContains('Aisha Khan', $wakrahNames);

        // Cross-branch booking guard: Aisha Khan (Old Airport only) cannot be booked on Al Wakrah
        $crossBranchRes = $this->actingAs($this->admin)->postJson(route('appointments.store'), [
            'customer_name' => 'Cross Branch Client',
            'phone' => '+97455998877',
            'appointment_datetime' => $dateStr . 'T13:00',
            'branch' => 'wakrah',
            'staff_id' => $this->aishaOldAirport->id,
            'service_name' => [$this->blowdryService->name],
        ]);
        $crossBranchRes->assertStatus(422)->assertJsonValidationErrors('staff_id');
        $this->assertSame(0, Appointment::where('customer_name', 'Cross Branch Client')->count());
    }
}
