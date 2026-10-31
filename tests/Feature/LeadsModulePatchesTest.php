<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadsModulePatchesTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_patch_1_removes_wa_me_link_and_leaves_only_copy_to_whatsapp(): void
    {
        $user = $this->createAdminUser();
        $customer = Customer::create(['name' => 'Amina Al-Thani', 'phone' => '55592353']);
        $lead = Lead::create([
            'phone' => '55592353',
            'customer_id' => $customer->id,
            'category' => 'cancel',
            'service_interest' => ['Facial'],
            'next_followup_date' => null,
        ]);

        $indexResponse = $this->actingAs($user)->get(route('leads.index'));
        $indexResponse->assertOk();
        $indexResponse->assertDontSee('Open WhatsApp Chat');
        $indexResponse->assertDontSee('wa.me/');
        $indexResponse->assertSee('Copy to WhatsApp');

        // next_followup_date remains untouched (null) until manually set by an agent
        $this->assertNull($lead->fresh()->next_followup_date);
    }

    public function test_patch_2_calendar_status_change_merges_into_existing_open_lead_instead_of_duplicating(): void
    {
        $user = $this->createAdminUser();
        $customer = Customer::create(['name' => 'Sara Khan', 'phone' => '77132353']);

        $appt1 = Appointment::create([
            'customer_name' => 'Sara Khan',
            'phone' => '77132353',
            'customer_id' => $customer->id,
            'appointment_datetime' => now()->addDay(),
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00:00',
            'service_name' => 'Facial',
            'branch' => 'old_airport',
            'status' => 'pending',
        ]);

        $appt2 = Appointment::create([
            'customer_name' => 'Sara Khan',
            'phone' => '77132353',
            'customer_id' => $customer->id,
            'appointment_datetime' => now()->addDays(2),
            'booking_date' => now()->addDays(2)->toDateString(),
            'start_time' => '16:00:00',
            'service_name' => 'Balayage',
            'branch' => 'old_airport',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->patchJson(route('appointments.updateStatus', $appt1), ['status' => 'cancelled'])
            ->assertOk();

        $this->assertEquals(1, Lead::where('phone', '77132353')->count());

        $this->actingAs($user)
            ->patchJson(route('appointments.updateStatus', $appt2), ['status' => 'no_show'])
            ->assertOk();

        // Still 1 open lead, with merged services and updated category/appointment_id
        $this->assertEquals(1, Lead::where('phone', '77132353')->count());
        $lead = Lead::where('phone', '77132353')->first();
        $this->assertEquals('no_show', $lead->category);
        $this->assertEquals($appt2->id, $lead->appointment_id);
        $this->assertEquals(['Facial', 'Balayage'], $lead->service_interest);
        // If the appointment is subsequently changed back to confirmed/completed on the Calendar,
        // the lead automatically transitions to 'booked' and links customer_id.
        $this->actingAs($user)
            ->patchJson(route('appointments.updateStatus', $appt2), ['status' => 'confirmed'])
            ->assertOk();

        $lead->refresh();
        $this->assertEquals('booked', $lead->needful_done);
        $this->assertEquals($customer->id, $lead->customer_id);
        $this->assertEquals($appt2->id, $lead->appointment_id);
    }

    public function test_patch_3_creating_salon_booking_auto_closes_open_leads_for_client(): void
    {
        $user = $this->createAdminUser();

        // Lead 1: Pending (null), unlinked customer_id initially
        $pendingLead = Lead::create([
            'phone' => '55592353',
            'customer_id' => null,
            'category' => 'cancel',
            'customer_remarks' => 'Wanted weekend slot',
            'service_interest' => ['Facial'],
            'needful_done' => null,
            'next_followup_date' => null,
        ]);

        // Lead 2: Contacted / In Progress ('yes'), also open for the same phone
        $contactedLead = Lead::create([
            'phone' => '55592353',
            'customer_id' => null,
            'category' => 'follow_up',
            'customer_remarks' => 'Spoke on WhatsApp',
            'service_interest' => ['Balayage'],
            'needful_done' => 'yes',
            'next_followup_date' => now()->addDays(2),
        ]);

        // Manual attempt to set needful_done = 'booked' via PATCH is rejected with 422
        $manualBookedResponse = $this->actingAs($user)
            ->patchJson(route('leads.needful-done', $pendingLead), ['needful_done' => 'booked']);
        $manualBookedResponse->assertStatus(422)
            ->assertJsonValidationErrors(['needful_done']);
        $this->assertNull($pendingLead->fresh()->needful_done);

        // Verify initial Booked pill counter is 0
        $initialIndex = $this->actingAs($user)->get(route('leads.index'));
        $initialIndex->assertOk()
            ->assertViewHas('bookedCount', 0)
            ->assertDontSee('<option value="booked"', false);

        $service = Service::create([
            'name' => 'Facial',
            'price' => 250,
            'duration' => 60,
        ]);

        $staff = Staff::create([
            'name' => 'Layla Stylist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $staff->services()->attach($service->id);

        $bookingDate = now()->addDays(3)->setTime(13, 0, 0);

        $response = $this->actingAs($user)->post(route('appointments.store'), [
            'customer_name' => 'Noor Al-Sulaiti',
            'phone' => '+97455592353',
            'appointment_datetime' => $bookingDate->format('Y-m-d H:i:s'),
            'service_name' => ['Facial'],
            'branch' => 'old_airport',
            'staff_id' => $staff->id,
            'price' => 250,
        ]);

        $response->assertRedirect();

        $customer = Customer::where('phone', '55592353')->firstOrFail();
        $appointment = Appointment::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        // Both open leads for 55592353 are consolidated into the single latest lead (contactedLead) so 1 booking = 1 conversion
        $this->assertNull(Lead::find($pendingLead->id));
        $this->assertEquals(1, Lead::where('phone', '55592353')->count());

        $contactedLead->refresh();
        $this->assertEquals('booked', $contactedLead->needful_done);
        $this->assertNull($contactedLead->next_followup_date);
        $this->assertEquals($customer->id, $contactedLead->customer_id);
        $this->assertEquals($appointment->id, $contactedLead->appointment_id);
        $this->assertEquals(['Balayage', 'Facial'], $contactedLead->service_interest);
        $this->assertStringContainsString('[Auto-closed: Booked on ' . now()->format('d M Y') . ']', (string) $contactedLead->customer_remarks);
        $this->assertStringContainsString('Wanted weekend slot', (string) $contactedLead->customer_remarks);
        $this->assertStringContainsString('Spoke on WhatsApp', (string) $contactedLead->customer_remarks);

        // Once 'booked', manual attempts to overwrite needful_done to 'no' or 'yes' are rejected with 422
        $overwriteBookedResponse = $this->actingAs($user)
            ->patchJson(route('leads.needful-done', $contactedLead), ['needful_done' => 'no']);
        $overwriteBookedResponse->assertStatus(422);
        $this->assertEquals('booked', $contactedLead->fresh()->needful_done);

        // Verify the Green Booked pill counter on leads/index is 1 (no double-counting) and the locked badge renders
        $afterIndex = $this->actingAs($user)->get(route('leads.index'));
        $afterIndex->assertOk()
            ->assertViewHas('bookedCount', 1)
            ->assertSee('<span class="lead-pill-count" id="bookedLeadsPillCount">1</span>', false)
            ->assertSee('Auto-verified via Calendar Booking');
    }

    public function test_patch_4_closed_leads_excluded_from_unscheduled_alert_banner(): void
    {
        $user = $this->createAdminUser();
        $customer = Customer::create(['name' => 'Closed Client', 'phone' => '55511122']);

        Lead::create([
            'phone' => '55511122',
            'customer_id' => $customer->id,
            'category' => 'cancel',
            'service_interest' => ['Facial'],
            'needful_done' => 'booked',
            'next_followup_date' => null,
        ]);

        // Contacted ('yes') lead still stays open/active until booked or lost
        $contactedLead = Lead::create([
            'phone' => '55533344',
            'customer_id' => $customer->id,
            'category' => 'no_show',
            'service_interest' => ['Balayage'],
            'needful_done' => 'yes',
            'next_followup_date' => null,
        ]);

        $response = $this->actingAs($user)->get(route('leads.index'));
        $response->assertOk();
        $response->assertViewHas('unscheduledLeads', fn ($leads) => $leads->count() === 1 && $leads->first()->id === $contactedLead->id);
        $response->assertSee('id="leadQuickFilterBar"', false);
        $response->assertSee('Overdue Follow-ups');
        $response->assertSee('Needs Follow-up Date');
        $response->assertSee('Booked');

        // Filtering by ?alert_filter=booked returns only the booked lead
        $bookedFilterResponse = $this->actingAs($user)->get(route('leads.index', ['alert_filter' => 'booked']));
        $bookedFilterResponse->assertOk();
        $bookedFilterResponse->assertViewHas('leads', fn ($leads) => $leads->count() === 1 && $leads->first()->phone === '55511122');
    }

    public function test_leads_analytics_custom_date_range_and_hormozi_conversion_metrics(): void
    {
        $user = $this->createAdminUser();
        $agent = User::factory()->create([
            'name' => 'Nadia Youssef',
            'role' => 'agent',
            'is_active' => true,
        ]);
        $customer = Customer::create(['name' => 'Dana Al-Misnad', 'phone' => '55577889']);

        $leadBooked = new Lead([
            'phone' => '55577889',
            'customer_id' => $customer->id,
            'assigned_agent_id' => $agent->id,
            'category' => 'cancel',
            'service_interest' => ['Facial'],
            'needful_done' => 'booked',
            'next_followup_date' => now()->addDays(2),
        ]);
        $leadBooked->created_at = now()->subDays(10);
        $leadBooked->save();

        $leadContacted = new Lead([
            'phone' => '55588990',
            'customer_id' => $customer->id,
            'assigned_agent_id' => $agent->id,
            'category' => 'inquiry',
            'service_interest' => ['Balayage'],
            'needful_done' => 'yes',
            'next_followup_date' => now()->subDays(2),
        ]);
        $leadContacted->created_at = now()->subDays(10);
        $leadContacted->save();

        $startDate = now()->subDays(15)->format('Y-m-d');
        $endDate = now()->subDays(5)->format('Y-m-d');

        $response = $this->actingAs($user)->get(route('leads.analytics', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));

        $response->assertOk();
        $response->assertViewHas('isCustomRange', true);
        $response->assertViewHas('totalLeads', 2);
        $response->assertViewHas('bookedLeadsCount', 1);
        $response->assertViewHas('conversionRate', 50.0);
        $response->assertViewHas('winBackTotal', 1);
        $response->assertViewHas('winBackBooked', 1);
        $response->assertViewHas('winBackRate', 100.0);
        $response->assertSee('Booked Appointments (Conversion Rate)');
        $response->assertSee('Win-Back Rate (Cancel / No-Show)');
        $response->assertSee('Lead Conversion Outcomes');
        $response->assertSee('Agent Closing Leaderboard');
        $response->assertSee('Nadia Youssef');
    }

    public function test_patch_5_ai_script_injects_days_old_angle_and_salon_client_history(): void
    {
        config(['services.openai.key' => 'test-openai-key']);

        $capturedSystemPrompt = null;
        Http::fake(function ($request) use (&$capturedSystemPrompt) {
            $capturedSystemPrompt = data_get($request->data(), 'messages.0.content');
            return Http::response([
                'choices' => [
                    ['message' => ['content' => 'Sentence one. Sentence two. Sentence three?']],
                ],
            ], 200);
        });

        $user = $this->createAdminUser();
        $customer = Customer::create(['name' => 'Fatima Al-Kuwari', 'phone' => '66112233']);

        Appointment::create([
            'customer_name' => 'Fatima Al-Kuwari',
            'phone' => '66112233',
            'customer_id' => $customer->id,
            'appointment_datetime' => now()->subDays(14),
            'booking_date' => now()->subDays(14)->toDateString(),
            'start_time' => '15:00:00',
            'service_name' => 'Keratin Treatment',
            'branch' => 'old_airport',
            'status' => 'completed',
        ]);

        $lead = new Lead([
            'phone' => '66112233',
            'customer_id' => $customer->id,
            'category' => 'follow_up',
            'service_interest' => ['Balayage'],
            'customer_remarks' => 'Asked about blonde tones',
        ]);
        $lead->created_at = now()->subDays(5);
        $lead->save();

        $response = $this->actingAs($user)->postJson(route('leads.ai-script', $lead), [
            'lead_id' => $lead->id,
            'module' => 'lead_management',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('days_old', 5)
            ->assertJsonPath('salon_history.completed_visits', 1)
            ->assertJsonPath('salon_history.last_service', 'Keratin Treatment');

        $selectedAngle = $response->json('angle');
        $this->assertNotEmpty($capturedSystemPrompt);
        $this->assertStringContainsString('5 day(s) old', $capturedSystemPrompt);
        $this->assertStringContainsString($selectedAngle, $capturedSystemPrompt);
        $this->assertStringContainsString('Returning salon client with 1 past completed visit(s). Last completed service: Keratin Treatment', $capturedSystemPrompt);
    }

    public function test_re_cancellation_trap_booking_then_cancel_reopens_exact_booked_lead_and_clears_followup_date(): void
    {
        $user = $this->createAdminUser();
        $agent = User::factory()->create(['name' => 'Nadia Youssef', 'role' => 'agent', 'is_active' => true]);
        $customer = Customer::create(['name' => 'Mona Al-Kuwari', 'phone' => '55544332']);

        $lead = Lead::create([
            'phone' => '55544332',
            'customer_id' => $customer->id,
            'assigned_agent_id' => $agent->id,
            'category' => 'follow_up',
            'service_interest' => ['Facial'],
            'customer_remarks' => 'Interested in hydra facial',
            'needful_done' => 'yes',
            'next_followup_date' => now()->addDays(2),
        ]);

        $service = Service::create(['name' => 'Facial', 'price' => 250, 'duration' => 60]);
        $staff = Staff::create([
            'name' => 'Layla Stylist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $staff->services()->attach($service->id);

        $bookingDate = now()->addDays(3)->setTime(13, 0, 0);

        $this->actingAs($user)->post(route('appointments.store'), [
            'customer_name' => 'Mona Al-Kuwari',
            'phone' => '+97455544332',
            'appointment_datetime' => $bookingDate->format('Y-m-d H:i:s'),
            'service_name' => ['Facial'],
            'branch' => 'old_airport',
            'staff_id' => $staff->id,
            'price' => 250,
        ])->assertRedirect();

        $appointment = Appointment::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $lead->refresh();
        $this->assertEquals('booked', $lead->needful_done);
        $this->assertEquals($appointment->id, $lead->appointment_id);
        $this->assertNull($lead->next_followup_date);
        $this->assertEquals($agent->id, $appointment->fresh()->booking_agent_id);

        // Now cancel that exact appointment on the Enhanced Calendar via updateStatus()
        $this->actingAs($user)
            ->patchJson(route('appointments.updateStatus', $appointment), ['status' => 'cancelled'])
            ->assertOk();

        // Still 1 lead row (reopened in-place, not duplicated)
        $this->assertEquals(1, Lead::where('phone', '55544332')->count());
        $lead->refresh();
        $this->assertEquals('cancel', $lead->category);
        $this->assertNull($lead->needful_done);
        $this->assertNull($lead->next_followup_date);
        $this->assertStringNotContainsString('[Auto-closed: Booked on', (string) $lead->customer_remarks);
        $this->assertStringContainsString('[Reopened: Cancel on ' . now()->format('d M Y') . ']', (string) $lead->customer_remarks);

        // Verify it immediately enters unscheduledLeadsQuery() (Needs Follow-up Date pill)
        $indexResponse = $this->actingAs($user)->get(route('leads.index'));
        $indexResponse->assertOk()
            ->assertViewHas('unscheduledCount', 1)
            ->assertViewHas('bookedCount', 0);
    }

    public function test_bookings_page_update_and_destroy_sync_and_reopen_linked_lead(): void
    {
        $user = $this->createAdminUser();
        $customer = Customer::create(['name' => 'Huda Al-Thani', 'phone' => '55566778']);

        $service = Service::create(['name' => 'Balayage', 'price' => 500, 'duration' => 90]);
        $staff = Staff::create([
            'name' => 'Sara Stylist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $staff->services()->attach($service->id);

        $lead = Lead::create([
            'phone' => '55566778',
            'customer_id' => $customer->id,
            'category' => 'inquiry',
            'service_interest' => ['Balayage'],
            'needful_done' => null,
            'next_followup_date' => now()->addDay(),
        ]);

        $bookingDate = now()->addDays(4)->setTime(14, 0, 0);
        $appointment = Appointment::create([
            'customer_name' => 'Huda Al-Thani',
            'phone' => '55566778',
            'customer_id' => $customer->id,
            'appointment_datetime' => $bookingDate,
            'booking_date' => $bookingDate->toDateString(),
            'start_time' => '14:00:00',
            'service_name' => 'Balayage',
            'branch' => 'old_airport',
            'staff_id' => $staff->id,
            'price' => 500,
            'status' => 'pending',
        ]);

        // 1. Updating status to 'cancelled' on the Bookings page via PUT /appointments/{id}
        $this->actingAs($user)->put(route('appointments.update', $appointment), [
            'customer_name' => 'Huda Al-Thani',
            'phone' => '55566778',
            'appointment_datetime' => $bookingDate->format('Y-m-d H:i:s'),
            'service_name' => ['Balayage'],
            'branch' => 'old_airport',
            'staff_id' => $staff->id,
            'price' => 500,
            'status' => 'cancelled',
        ])->assertRedirect();

        $lead->refresh();
        $this->assertEquals('cancel', $lead->category);
        $this->assertNull($lead->needful_done);
        $this->assertNull($lead->next_followup_date);
        $this->assertEquals($appointment->id, $lead->appointment_id);

        // 2. Rescheduling/updating status back to 'pending' on the Bookings page via PUT /appointments/{id}
        $this->actingAs($user)->put(route('appointments.update', $appointment), [
            'customer_name' => 'Huda Al-Thani',
            'phone' => '55566778',
            'appointment_datetime' => $bookingDate->format('Y-m-d H:i:s'),
            'service_name' => ['Balayage'],
            'branch' => 'old_airport',
            'staff_id' => $staff->id,
            'price' => 500,
            'status' => 'pending',
        ])->assertRedirect();

        $lead->refresh();
        $this->assertEquals('booked', $lead->needful_done);
        $this->assertEquals($appointment->id, $lead->appointment_id);

        // 3. Deleting the booking via DELETE /appointments/{id} reopens the lead instead of leaving it permanently locked as 'booked'
        $this->actingAs($user)->delete(route('appointments.destroy', $appointment))
            ->assertRedirect();

        $lead->refresh();
        $this->assertNull($lead->needful_done);
        $this->assertNull($lead->appointment_id);
        $this->assertNull($lead->next_followup_date);
        $this->assertStringContainsString('[Reopened: Booking deleted on ' . now()->format('d M Y') . ']', (string) $lead->customer_remarks);
    }

    public function test_check_todays_followups_excludes_booked_and_lost_leads(): void
    {
        $user = $this->createAdminUser();

        // Booked lead with today's date (if any legacy row existed) must be excluded
        Lead::create([
            'phone' => '55500001',
            'category' => 'follow_up',
            'service_interest' => ['Facial'],
            'needful_done' => 'booked',
            'next_followup_date' => today(),
        ]);

        // Lost ('no') lead with today's date must be excluded
        Lead::create([
            'phone' => '55500002',
            'category' => 'follow_up',
            'service_interest' => ['Facial'],
            'needful_done' => 'no',
            'next_followup_date' => today(),
        ]);

        // Active open lead ('yes' / Contacted) due today MUST be included
        $openDueLead = Lead::create([
            'phone' => '55500003',
            'category' => 'follow_up',
            'service_interest' => ['Balayage'],
            'needful_done' => 'yes',
            'next_followup_date' => today(),
        ]);

        $response = $this->actingAs($user)->getJson(route('leads.check.followups'));
        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('leads.0.id', $openDueLead->id);
    }

    public function test_multiple_open_leads_for_77132353_are_merged_so_one_booking_equals_one_conversion(): void
    {
        $user = $this->createAdminUser();

        // Attempting to manually create two open leads for 77132353 with different categories/services merges into 1 open row
        $this->actingAs($user)->post(route('leads.store'), [
            'country_code' => '974',
            'phone_number' => '77132353',
            'customer_name' => 'Reem Al-Sulaiti',
            'category' => 'inquiry',
            'service_interest' => ['Facial'],
            'customer_remarks' => 'First inquiry',
            'next_followup_date' => now()->addDay()->format('Y-m-d'),
        ])->assertRedirect();

        $this->actingAs($user)->post(route('leads.store'), [
            'country_code' => '974',
            'phone_number' => '77132353',
            'customer_name' => 'Reem Al-Sulaiti',
            'category' => 'follow_up',
            'service_interest' => ['Hair Color', 'Haircut'],
            'customer_remarks' => 'Second follow-up',
            'next_followup_date' => now()->addDays(2)->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertEquals(1, Lead::where('phone', '77132353')->count());
        $lead = Lead::where('phone', '77132353')->firstOrFail();
        $this->assertEquals(['Facial', 'Hair Color', 'Haircut'], $lead->service_interest);

        // Even if a second open lead row was inserted directly in DB (e.g. legacy row), booking 1 appointment produces strictly 1 'booked' lead
        Lead::create([
            'phone' => '77132353',
            'customer_id' => $lead->customer_id,
            'category' => 'cancel',
            'service_interest' => ['Balayage'],
            'needful_done' => null,
            'next_followup_date' => null,
        ]);
        $this->assertEquals(2, Lead::where('phone', '77132353')->count());

        $service = Service::create(['name' => 'Facial', 'price' => 250, 'duration' => 60]);
        $staff = Staff::create([
            'name' => 'Layla Stylist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $staff->services()->attach($service->id);

        $bookingDate = now()->addDays(3)->setTime(13, 0, 0);
        $this->actingAs($user)->post(route('appointments.store'), [
            'customer_name' => 'Reem Al-Sulaiti',
            'phone' => '+97477132353',
            'appointment_datetime' => $bookingDate->format('Y-m-d H:i:s'),
            'service_name' => ['Facial'],
            'branch' => 'old_airport',
            'staff_id' => $staff->id,
            'price' => 250,
        ])->assertRedirect();

        $this->assertEquals(1, Lead::where('phone', '77132353')->count());
        $this->assertEquals(1, Lead::where('phone', '77132353')->where('needful_done', 'booked')->count());
    }

    public function test_multi_status_category_badges_merged_on_single_row_and_counted_in_analytics(): void
    {
        $user = $this->createAdminUser();
        $customer = Customer::create(['name' => 'Amal Salem', 'phone' => '55592353']);

        // 1. Existing open 'cancel' lead (e.g. from a cancelled appointment)
        $lead = Lead::create([
            'phone' => '55592353',
            'customer_id' => $customer->id,
            'category' => 'cancel',
            'categories' => ['cancel'],
            'service_interest' => ['Facial'],
            'customer_remarks' => 'Cancelled weekend appointment',
            'needful_done' => null,
            'next_followup_date' => null,
        ]);

        // 2. Staff manually logs a new 'inquiry' for the same phone number
        $this->actingAs($user)->post(route('leads.store'), [
            'country_code' => '974',
            'phone_number' => '55592353',
            'customer_name' => 'Amal Salem',
            'category' => 'inquiry',
            'service_interest' => ['Balayage'],
            'customer_remarks' => 'Asked about Balayage pricing',
            'next_followup_date' => now()->addDay()->format('Y-m-d'),
        ])->assertRedirect();

        // Strictly 1 lead row exists, holding BOTH ['cancel', 'inquiry'] and keeping 'cancel' as primary category
        $this->assertEquals(1, Lead::where('phone', '55592353')->count());
        $lead->refresh();
        $this->assertEquals('cancel', $lead->category);
        $this->assertEquals(['cancel', 'inquiry'], $lead->categories);
        $this->assertEquals(['Facial', 'Balayage'], $lead->service_interest);
        $this->assertStringContainsString('Cancelled weekend appointment', (string) $lead->customer_remarks);
        $this->assertStringContainsString('Asked about Balayage pricing', (string) $lead->customer_remarks);

        // 3. Verify both 'Cancelled' and 'Inquiry' badges render side-by-side in leads/index
        $indexResponse = $this->actingAs($user)->get(route('leads.index'));
        $indexResponse->assertOk()
            ->assertSee('<span class="lead-badge cat-cancel">Cancelled</span>', false)
            ->assertSee('<span class="lead-badge cat-inquiry">Inquiry</span>', false);

        // 4. Booking 1 appointment flips the single row to 'booked' and counts as 1 conversion and 1 win-back in analytics
        $service = Service::create(['name' => 'Facial', 'price' => 250, 'duration' => 60]);
        $staff = Staff::create([
            'name' => 'Layla Stylist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $staff->services()->attach($service->id);

        $bookingDate = now()->addDays(3)->setTime(13, 0, 0);
        $this->actingAs($user)->post(route('appointments.store'), [
            'customer_name' => 'Amal Salem',
            'phone' => '+97455592353',
            'appointment_datetime' => $bookingDate->format('Y-m-d H:i:s'),
            'service_name' => ['Facial'],
            'branch' => 'old_airport',
            'staff_id' => $staff->id,
            'price' => 250,
        ])->assertRedirect();

        $lead->refresh();
        $this->assertEquals('booked', $lead->needful_done);
        $this->assertEquals('cancel', $lead->category);
        $this->assertEquals(['cancel', 'inquiry'], $lead->categories);

        $analyticsResponse = $this->actingAs($user)->get(route('leads.analytics', ['period' => 'this_month']));
        $analyticsResponse->assertOk()
            ->assertViewHas('totalLeads', 1)
            ->assertViewHas('bookedLeadsCount', 1)
            ->assertViewHas('conversionRate', 100.0)
            ->assertViewHas('winBackTotal', 1)
            ->assertViewHas('winBackBooked', 1)
            ->assertViewHas('winBackRate', 100.0);
    }
}
