<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\KpiAdsConversionReport;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdLeadFollowUpTwoWaySyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agentZoya;
    private User $agentAreeba;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Boss',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->agentZoya = User::factory()->create([
            'name' => 'Zoya',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => [
                'kpis' => 'edit',
                'leads' => 'edit',
                'bookings' => 'edit',
                'calendar' => 'edit',
                'clients' => 'edit',
            ],
        ]);

        $this->agentAreeba = User::factory()->create([
            'name' => 'Areeba',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => [
                'kpis' => 'edit',
                'leads' => 'edit',
            ],
        ]);
    }

    public function test_send_to_followups_creates_linked_lead_preserves_ad_entry_renders_badges_and_is_idempotent(): void
    {
        Service::create(['name' => 'Hair Treatment', 'price' => 300, 'duration' => 60]);
        $tomorrow = Lead::salonToday()->addDay();
        $tomorrowStr = $tomorrow->toDateString();

        // 1. Create an unbooked Ad Lead (plain log first — stays only in Ads List until triggered)
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => Lead::salonToday()->toDateString(),
                'customer_name' => 'Noor Al-Thani',
                'country_code' => '974',
                'phone_number' => '55901122',
                'category' => 'Hair Treatment',
                'remarks' => 'Asked about Keratin package pricing',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $adEntry = AdLeadEntry::firstOrFail();
        $this->assertNull($adEntry->lead_id);
        $this->assertSame(0, Lead::count());

        // 2. Missing follow_up_date is rejected with 422 validation error
        $this->actingAs($this->agentZoya)
            ->postJson(route('kpi.ad-leads.send-to-followups', $adEntry), [
                'remarks' => 'Missing date should fail',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['follow_up_date']);

        $this->assertSame(0, Lead::count());

        // 3. Agent confirms in "Send to Follow-Ups" modal with chosen date & updated remarks
        $chosenDate = Lead::salonToday()->addDays(2);
        $chosenDateStr = $chosenDate->toDateString();

        $jsonResp = $this->actingAs($this->agentZoya)
            ->postJson(route('kpi.ad-leads.send-to-followups', $adEntry), [
                'follow_up_date' => $chosenDateStr,
                'remarks' => 'Will confirm tomorrow afternoon after checking schedule',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'entry_id' => $adEntry->id,
                'follow_up_date' => $chosenDateStr,
                'follow_up_pill_text' => 'In Follow-Ups · Due ' . $chosenDate->format('d M'),
                'remarks' => 'Will confirm tomorrow afternoon after checking schedule',
            ]);

        $adEntry->refresh();
        $this->assertSame(1, AdLeadEntry::count(), 'Ad Lead must stay in Ads List, never deleted or moved');
        $this->assertSame(1, Lead::count());
        $this->assertNotNull($adEntry->lead_id);
        $this->assertSame('Will confirm tomorrow afternoon after checking schedule', $adEntry->remarks);

        $lead = Lead::findOrFail($adEntry->lead_id);
        $this->assertSame($lead->id, $jsonResp->json('lead_id'));
        $this->assertSame($adEntry->id, $lead->ad_lead_id);
        $this->assertSame($adEntry->customer_id, $lead->customer_id);
        $this->assertSame('+97455901122', $lead->customer->phone);
        $this->assertSame('Noor Al-Thani', $lead->customer->name);
        $this->assertSame('55901122', $lead->phone);
        $this->assertSame(['Hair Treatment'], $lead->service_interest);
        $this->assertSame('Paid Ads · Hair Treatment', $lead->source);
        $this->assertSame($this->agentZoya->id, $lead->assigned_agent_id);
        $this->assertSame($chosenDateStr, $lead->next_followup_date->toDateString());
        $this->assertSame('Will confirm tomorrow afternoon after checking schedule', $lead->customer_remarks);
        $this->assertSame('follow_up', $adEntry->status);
        $this->assertSame($chosenDateStr, $adEntry->follow_up_date->toDateString());

        // 4. Submitting Send to Follow-Ups a second time MUST NOT create a duplicate Lead or duplicate remarks
        $this->actingAs($this->agentZoya)
            ->postJson(route('kpi.ad-leads.send-to-followups', $adEntry), [
                'follow_up_date' => $chosenDateStr,
                'remarks' => 'Will confirm tomorrow afternoon after checking schedule',
            ])
            ->assertOk();

        $this->assertSame(1, Lead::count(), 'Submitting Send to Follow-Ups twice must never duplicate rows');
        $lead->refresh();
        $this->assertSame('Will confirm tomorrow afternoon after checking schedule', $lead->customer_remarks);

        // 5. Ads List renders "In Follow-Ups · Due DD Mon" pill linking to Lead Management + confirmation modal markup
        $adsIndexHtml = $this->actingAs($this->agentZoya)
            ->get(route('kpi.ads.index', ['tab' => 'leads']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('In Follow-Ups · Due ' . $chosenDate->format('d M'), $adsIndexHtml);
        $this->assertStringContainsString('focus_lead=' . $lead->id, $adsIndexHtml);
        $this->assertStringContainsString('id="sendToFollowUpsModal"', $adsIndexHtml);
        $this->assertStringContainsString('Push to Follow-Ups', $adsIndexHtml);
        $this->assertStringContainsString('data-default-date="' . $tomorrowStr . '"', $adsIndexHtml);

        // 6. Lead Management shows the lead on its exact chosen follow-up date with an "Ads" source badge and updated remarks
        $leadsHtml = $this->actingAs($this->agentZoya)
            ->get(route('leads.index', ['followup_date' => $chosenDateStr]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Noor Al-Thani', $leadsHtml);
        $this->assertStringContainsString('Will confirm tomorrow afternoon after checking schedule', $leadsHtml);
        $this->assertStringContainsString('source-ads', $leadsHtml);
        $this->assertStringContainsString('Ads', $leadsHtml);
    }

    public function test_followup_status_or_date_in_ad_lead_modal_auto_syncs_and_deduplicates_by_e164_phone(): void
    {
        Service::create(['name' => 'Hair Color', 'price' => 400, 'duration' => 90]);
        $targetDate = Lead::salonToday()->addDays(2)->toDateString();

        // Pre-existing open Lead in Lead Management stored with E.164 phone format
        $existingCustomer = Customer::create([
            'name' => 'Fatima Hassan',
            'phone' => '+97455903344',
        ]);
        $existingLead = Lead::create([
            'phone' => '+97455903344',
            'customer_id' => $existingCustomer->id,
            'assigned_agent_id' => $this->agentAreeba->id,
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => ['Blowdry'],
            'customer_remarks' => 'Inquired about Friday slot',
            'next_followup_date' => Lead::salonToday()->toDateString(),
        ]);

        // Log an Ad Lead with status = "will_let_you_know" and same phone in local 8-digit form -> MUST link & merge into existing open lead!
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => Lead::salonToday()->toDateString(),
                'customer_name' => 'Fatima Hassan',
                'country_code' => '974',
                'phone_number' => '5590 3344',
                'category' => 'Hair Color',
                'status' => 'will_let_you_know',
                'follow_up_date' => $targetDate,
                'assigned_agent_id' => $this->agentZoya->id,
                'remarks' => 'Will confirm after checking with sister',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $this->assertSame(1, Lead::count(), 'Must deduplicate against existing open Lead matching normalized E.164 phone');
        $this->assertSame(1, AdLeadEntry::count());

        $adEntry = AdLeadEntry::firstOrFail();
        $existingLead->refresh();

        $this->assertSame($existingLead->id, $adEntry->lead_id);
        $this->assertSame($adEntry->id, $existingLead->ad_lead_id);
        $this->assertSame($targetDate, $existingLead->next_followup_date->toDateString());
        $this->assertSame($this->agentZoya->id, $existingLead->assigned_agent_id);
        $this->assertContains('Blowdry', $existingLead->service_interest);
        $this->assertContains('Hair Color', $existingLead->service_interest);
        $this->assertSame(
            'Inquired about Friday slot | Will confirm after checking with sister',
            $existingLead->customer_remarks
        );
        $this->assertSame(
            'Inquired about Friday slot | Will confirm after checking with sister',
            $adEntry->remarks
        );
    }

    public function test_two_way_sync_for_remarks_followup_date_and_status_without_loops_or_duplicate_text(): void
    {
        $dayOne = Lead::salonToday()->addDay()->toDateString();
        $dayThree = Lead::salonToday()->addDays(3)->toDateString();
        $dayFive = Lead::salonToday()->addDays(5)->toDateString();

        // Create Ad Lead with Follow-Up Date set (auto-syncs to Lead Management)
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => Lead::salonToday()->toDateString(),
                'customer_name' => 'Dana Al-Mansoori',
                'country_code' => '974',
                'phone_number' => '55905566',
                'category' => 'Facial',
                'follow_up_date' => $dayOne,
                'assigned_agent_id' => $this->agentZoya->id,
                'remarks' => 'Interested in HydraFacial promo',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $adEntry = AdLeadEntry::firstOrFail();
        $lead = Lead::findOrFail($adEntry->lead_id);
        $this->assertSame($dayOne, $lead->next_followup_date->toDateString());

        // 1. Update remark, follow-up date, and agent from Lead Management -> reflects cleanly in Ads List (no duplicate text)
        $this->actingAs($this->agentZoya)
            ->put(route('leads.update', $lead), [
                'country_code' => '974',
                'phone_number' => '55905566',
                'customer_name' => 'Dana Al-Mansoori',
                'assigned_agent_id' => $this->agentAreeba->id,
                'categories' => ['follow_up'],
                'service_interest' => ['Facial'],
                'next_followup_date' => $dayThree,
                'customer_remarks' => 'Called client — wants afternoon slot on Thursday',
            ])
            ->assertRedirect();

        $adEntry->refresh();
        $lead->refresh();
        $this->assertSame('Called client — wants afternoon slot on Thursday', $adEntry->remarks);
        $this->assertSame($dayThree, $adEntry->follow_up_date->toDateString());
        $this->assertSame($this->agentAreeba->id, $adEntry->assigned_agent_id);
        $this->assertSame('Called client — wants afternoon slot on Thursday', $lead->customer_remarks);

        // 2. Inline status change in Lead Management (Contacted -> Lost) reflects back in Ads List
        $this->actingAs($this->agentAreeba)
            ->patchJson(route('leads.needful-done', $lead), ['needful_done' => 'yes'])
            ->assertOk();

        $adEntry->refresh();
        $this->assertSame('contacted', $adEntry->status);

        $this->actingAs($this->agentAreeba)
            ->patchJson(route('leads.needful-done', $lead), ['needful_done' => 'no'])
            ->assertOk();

        $adEntry->refresh();
        $lead->refresh();
        $this->assertSame('lost', $adEntry->status);
        $this->assertNull($adEntry->follow_up_date);
        $this->assertNull($lead->next_followup_date);

        // 3. Reopen and edit remark + date from Ads List -> reflects cleanly back into Lead Management without duplicating text or rows
        $this->actingAs($this->admin)
            ->put(route('kpi.ad-leads.update', $adEntry), [
                'date' => $adEntry->date->toDateString(),
                'customer_name' => 'Dana Al-Mansoori',
                'country_code' => '974',
                'phone_number' => '55905566',
                'category' => 'Facial',
                'status' => 'follow_up',
                'follow_up_date' => $dayFive,
                'assigned_agent_id' => $this->agentZoya->id,
                'remarks' => 'Client messaged back asking for weekend availability',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $this->assertSame(1, Lead::count(), 'Two-way edit must never create a duplicate Lead');
        $lead->refresh();
        $adEntry->refresh();
        $this->assertNull($lead->needful_done);
        $this->assertSame($dayFive, $lead->next_followup_date->toDateString());
        $this->assertSame($this->agentZoya->id, $lead->assigned_agent_id);
        $this->assertSame('Client messaged back asking for weekend availability', $lead->customer_remarks);
        $this->assertSame('Client messaged back asking for weekend availability', $adEntry->remarks);
    }

    public function test_booking_synced_ad_lead_preserves_14_day_rolling_attribution_with_zero_double_counting(): void
    {
        $service = Service::create([
            'name' => 'Hair Treatment',
            'price' => 300,
            'duration' => 60,
        ]);

        // Log an Ad Lead on Oct 30 and send to Follow-Ups
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => '2026-10-30',
                'customer_name' => 'Sara Al-Marri',
                'country_code' => '974',
                'phone_number' => '55907788',
                'category' => 'Hair Treatment',
                'status' => 'follow_up',
                'follow_up_date' => '2026-11-01',
                'remarks' => 'Wants appointment early November',
            ])
            ->assertRedirect(route('kpi.ads.index'));

        $adEntry = AdLeadEntry::firstOrFail();
        $lead = Lead::findOrFail($adEntry->lead_id);
        $customer = Customer::findOrFail($adEntry->customer_id);
        $this->assertSame('+97455907788', $customer->phone);

        // Book client on Nov 5 (inside 14-day cross-month spillover window [Oct 30 .. Nov 13])
        $appt = Appointment::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'phone' => $customer->phone,
            'service_name' => $service->name,
            'branch' => 'old_airport',
            'booking_date' => '2026-11-05',
            'appointment_datetime' => Carbon::parse('2026-11-05 14:00:00'),
            'status' => 'confirmed',
            'price' => 300,
        ]);

        // Simulate Calendar booking lead auto-close + AdLeadEntry attribution sync
        $lead->update([
            'needful_done' => 'booked',
            'appointment_id' => $appt->id,
            'next_followup_date' => null,
        ]);
        AdLeadEntry::syncFromCustomerActivity($customer, Carbon::parse('2026-11-05'), $customer->phone, $appt->id);

        $adEntry->refresh();
        $this->assertSame(1, AdLeadEntry::count());
        $this->assertSame('old_airport', $adEntry->branch);
        $this->assertSame('booked', $adEntry->status);
        $this->assertNull($adEntry->follow_up_date);
        $this->assertEquals(300.00, (float) $adEntry->ticket_amount);

        // Checkout with upsell (420 QAR net) -> updates AdLeadEntry once without double counting
        Sale::create([
            'appointment_id' => $appt->id,
            'customer_id' => $customer->id,
            'branch' => 'old_airport',
            'services_total' => 420,
            'products_total' => 0,
            'packages_total' => 0,
            'discount_amount' => 0,
            'tip_amount' => 30,
            'total_amount' => 450,
            'payment_method' => 'card',
        ]);
        AdLeadEntry::syncFromCustomerActivity($customer, Carbon::parse('2026-11-05'), $customer->phone, $appt->id);

        $adEntry->refresh();
        $this->assertEquals(420.00, (float) $adEntry->ticket_amount);

        $report = new KpiAdsConversionReport([
            'date_from' => Carbon::parse('2026-10-01'),
            'date_to' => Carbon::parse('2026-10-31'),
        ]);
        $totals = $report->totals();
        $this->assertSame(1, $totals['total_leads']);
        $this->assertSame(1, $totals['total_bookings']);
        $this->assertEquals(420.00, (float) $totals['total_revenue']);

        // Editing remark on Lead Management afterwards must NOT alter branch or attributed ticket_amount
        $this->actingAs($this->admin)
            ->put(route('leads.update', $lead), [
                'country_code' => '974',
                'phone_number' => '55907788',
                'customer_name' => 'Sara Al-Marri',
                'categories' => ['follow_up'],
                'service_interest' => ['Hair Treatment'],
                'next_followup_date' => '2026-11-06',
                'customer_remarks' => 'VIP client loved the service',
            ])
            ->assertRedirect();

        $adEntry->refresh();
        $this->assertSame('old_airport', $adEntry->branch);
        $this->assertEquals(420.00, (float) $adEntry->ticket_amount);
        $this->assertSame('booked', $adEntry->status);
        $this->assertSame('VIP client loved the service', $adEntry->remarks);
    }

    public function test_add_ad_lead_modal_keeps_original_clean_fields_opaque_surface_and_preserves_followup_sync_on_plain_edit(): void
    {
        $adsPage = $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'leads']))
            ->assertOk();

        $adsPage->assertSee('Add Ad Lead');
        $adsPage->assertSee('Sales Consultant');
        $adsPage->assertSee('#adLeadEntryModal .modal-content', false);
        $adsPage->assertSee('#sendToFollowUpsModal .modal-content', false);
        $adsPage->assertSee('background: #241e1c !important;', false);
        $adsPage->assertDontSee('Follow-Up Status');
        $adsPage->assertDontSee('Assigned Agent');
        $adsPage->assertDontSee('Setting a follow-up status');

        // 1. Create a new Ad Lead with only the original Add modal fields
        $this->actingAs($this->admin)
            ->post(route('kpi.ad-leads.store'), [
                'date' => Lead::salonToday()->toDateString(),
                'customer_name' => 'Reem Al-Kuwari',
                'country_code' => '974',
                'phone_number' => '55909911',
                'category' => 'Makeup',
                'remarks' => 'will let u know',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('kpi.ads.index'));

        $entry = AdLeadEntry::where('phone', '55909911')->firstOrFail();
        $this->assertNull($entry->lead_id);

        // Verify the row renders a modal trigger button (not an instant form submit)
        $this->actingAs($this->agentZoya)
            ->get(route('kpi.ads.index', ['tab' => 'leads']))
            ->assertOk()
            ->assertSee('ad-lead-send-followup-btn', false);

        // 2. Push to Follow-Ups via the modal endpoint with confirmed date & remarks
        $expectedDueDate = Lead::salonToday()->addDay()->toDateString();
        $this->actingAs($this->agentZoya)
            ->postJson(route('kpi.ad-leads.send-to-followups', $entry), [
                'follow_up_date' => $expectedDueDate,
                'remarks' => 'Will confirm tomorrow afternoon',
            ])
            ->assertOk();

        $entry->refresh();
        $lead = Lead::findOrFail($entry->lead_id);
        $this->assertSame($expectedDueDate, $lead->next_followup_date->toDateString());
        $this->assertSame('Will confirm tomorrow afternoon', $lead->customer_remarks);
        $this->assertSame('Will confirm tomorrow afternoon', $entry->remarks);
        $this->assertSame($this->agentZoya->id, $lead->assigned_agent_id);

        // 3. Edit remarks using ONLY the clean Edit modal fields (no status/date/agent submitted) -> preserves follow-up link & syncs remark
        $this->actingAs($this->admin)
            ->put(route('kpi.ad-leads.update', $entry), [
                'date' => $entry->date->toDateString(),
                'customer_name' => 'Reem Al-Kuwari',
                'country_code' => '974',
                'phone_number' => '55909911',
                'category' => 'Makeup',
                'remarks' => 'Asked for bridal trial availability',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('kpi.ads.index'));

        $entry->refresh();
        $lead->refresh();
        $this->assertSame(1, Lead::count());
        $this->assertSame($expectedDueDate, $lead->next_followup_date->toDateString());
        $this->assertSame($this->agentZoya->id, $lead->assigned_agent_id);
        $this->assertSame('Asked for bridal trial availability', $lead->customer_remarks);
        $this->assertSame('Asked for bridal trial availability', $entry->remarks);
    }
}
