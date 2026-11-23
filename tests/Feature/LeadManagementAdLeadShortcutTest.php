<?php

namespace Tests\Feature;

use App\Models\AdCategory;
use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadManagementAdLeadShortcutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $leadsOnlyAgent;
    private User $viewOnlyUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Boss',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Chat agent with Lead Management edit access ONLY (no KPI module permission)
        $this->leadsOnlyAgent = User::factory()->create([
            'name' => 'Areeba Khan',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => [
                'leads' => 'edit',
            ],
        ]);

        $this->viewOnlyUser = User::factory()->create([
            'name' => 'View Only Staff',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => [
                'leads' => 'view',
            ],
        ]);
    }

    public function test_leads_page_renders_add_ad_lead_shortcut_and_shared_modals_with_dynamic_categories(): void
    {
        AdCategory::create(['name' => 'Eid Glow Offer']);

        $response = $this->actingAs($this->leadsOnlyAgent)
            ->get(route('leads.index'))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('id="openAddAdLeadShortcutBtn"', $html);
        $this->assertStringContainsString('Add Ad Lead', $html);
        $this->assertStringContainsString('id="adLeadEntryModal"', $html);
        $this->assertStringContainsString('id="adLeadEntryForm"', $html);
        $this->assertStringContainsString('data-ajax-mode="1"', $html);
        $this->assertStringContainsString('id="adLeadSavedToast"', $html);
        $this->assertStringContainsString('id="sendToFollowUpsModal"', $html);

        // Dynamic category from AdCategory table is present in the shared modal dropdown
        $this->assertStringContainsString('Eid Glow Offer', $html);
        $this->assertStringContainsString('Sales Consultant', $html);

        // View-only user does NOT see the Add Ad Lead shortcut button
        $this->actingAs($this->viewOnlyUser)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertDontSee('id="openAddAdLeadShortcutBtn"', false);
    }

    public function test_leads_only_agent_can_fetch_categories_save_ad_lead_generate_ai_script_and_send_to_followups_without_403(): void
    {
        config(['services.openai.key' => null, 'services.gemini.key' => null]);

        AdCategory::create(['name' => 'Winter Keratin Promo']);
        $todayStr = Lead::salonToday()->toDateString();
        $tomorrowStr = Lead::salonToday()->addDay()->toDateString();

        // 1. Fetch dynamic categories without 403
        $catResp = $this->actingAs($this->leadsOnlyAgent)
            ->getJson(route('kpi.ad-leads.categories'))
            ->assertOk();
        $this->assertContains('Winter Keratin Promo', $catResp->json('categories'));

        // 2. Inline AI closing script inside shared modal works without 403
        $this->actingAs($this->leadsOnlyAgent)
            ->postJson(route('kpi.ad-leads.ai-script.inline'), [
                'customer_name' => 'Mariam Al-Khater',
                'category' => 'Winter Keratin Promo',
                'remarks' => 'Asked for weekend slot',
            ])
            ->assertOk()
            ->assertJsonStructure(['script']);

        // 3. Submit new Ad Lead via exact Ad Leads store endpoint (JSON mode used by Lead Management shortcut)
        $storeResp = $this->actingAs($this->leadsOnlyAgent)
            ->postJson(route('kpi.ad-leads.store'), [
                'date' => $todayStr,
                'customer_name' => 'Mariam Al-Khater',
                'country_code' => '974',
                'phone_number' => '55442211',
                'category' => 'Winter Keratin Promo',
                'remarks' => 'Will let you know tonight',
            ])
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Ad lead saved to Ad Leads Log.',
                'entry' => [
                    'date' => $todayStr,
                    'customer_name' => 'Mariam Al-Khater',
                    'category' => 'Winter Keratin Promo',
                    'remarks' => 'Will let you know tonight',
                ],
            ]);

        $entryId = (int) $storeResp->json('entry.id');
        $sendUrl = (string) $storeResp->json('send_to_followups_url');
        $this->assertNotEmpty($sendUrl);

        $entry = AdLeadEntry::findOrFail($entryId);
        $this->assertSame('Mariam Al-Khater', $entry->customer_name);
        $this->assertSame('Winter Keratin Promo', $entry->category);
        $this->assertSame('Will let you know tonight', $entry->remarks);
        $this->assertNotNull($entry->customer_id, 'Automatic canonical customer profile must be created');
        $this->assertSame('+97455442211', Customer::findOrFail($entry->customer_id)->phone);

        // 4. Confirm the newly created Ad Lead immediately appears in Ad Leads Log
        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index'))
            ->assertOk()
            ->assertSee('Mariam Al-Khater')
            ->assertSee('Winter Keratin Promo')
            ->assertSee('Will let you know tonight');

        // 5. Click "Send to Follow-Ups" from post-save toast (same leads-only agent, zero 403, zero duplicates)
        $syncResp = $this->actingAs($this->leadsOnlyAgent)
            ->postJson($sendUrl, [
                'follow_up_date' => $tomorrowStr,
                'remarks' => 'Follow up after 4 PM tomorrow',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'entry_id' => $entry->id,
                'follow_up_date' => $tomorrowStr,
                'remarks' => 'Follow up after 4 PM tomorrow',
            ]);

        $leadId = (int) $syncResp->json('lead_id');
        $lead = Lead::findOrFail($leadId);
        $this->assertSame($entry->id, $lead->ad_lead_id);
        $this->assertSame($lead->id, $entry->fresh()->lead_id);
        $this->assertSame($tomorrowStr, $lead->next_followup_date?->toDateString());
        $this->assertSame([Lead::GENERAL_INQUIRY_OPTION], $lead->service_interest);
        $this->assertSame(1, Lead::count());

        // Calling Send to Follow-Ups a second time updates in-place with ZERO duplicate Lead rows
        $this->actingAs($this->leadsOnlyAgent)
            ->postJson($sendUrl, [
                'follow_up_date' => $tomorrowStr,
                'remarks' => 'Confirmed call at 5 PM',
            ])
            ->assertOk();
        $this->assertSame(1, Lead::count());
        $this->assertSame('Confirmed call at 5 PM', $lead->fresh()->customer_remarks);

        // 6. Verify 14-day rolling calendar revenue attribution still triggers cleanly on this ad lead
        $service = Service::create(['name' => 'Keratin', 'price' => 450, 'duration' => 90]);
        $stylist = Staff::create([
            'name' => 'Layla Stylist',
            'branch' => 'old_airport',
            'availability_status' => 'present',
            'bookable' => true,
        ]);
        $stylist->services()->attach($service->id);

        $this->actingAs($this->admin)
            ->postJson(route('appointments.store'), [
                'customer_name' => 'Mariam Al-Khater',
                'phone' => '+97455442211',
                'appointment_datetime' => $tomorrowStr . ' 13:00:00',
                'service_name' => ['Keratin'],
                'branch' => 'old_airport',
                'staff_id' => $stylist->id,
                'booking_agent_id' => $this->leadsOnlyAgent->id,
                'price' => 450,
            ])
            ->assertCreated();

        $entry->refresh();
        $this->assertTrue($entry->isBooked());
        $this->assertSame(450.0, (float) $entry->ticket_amount);
        $this->assertSame('old_airport', $entry->branch);
    }

    public function test_view_only_user_gets_403_on_ad_lead_store_and_send_to_followups(): void
    {
        $todayStr = Lead::salonToday()->toDateString();

        $this->actingAs($this->viewOnlyUser)
            ->postJson(route('kpi.ad-leads.store'), [
                'date' => $todayStr,
                'customer_name' => 'Blocked Client',
                'country_code' => '974',
                'phone_number' => '55449900',
                'category' => 'Facial',
            ])
            ->assertForbidden();

        $entry = AdLeadEntry::create([
            'date' => $todayStr,
            'customer_name' => 'Existing Ad Lead',
            'phone' => '55449901',
            'category' => 'Facial',
            'ticket_amount' => 0,
        ]);

        $this->actingAs($this->viewOnlyUser)
            ->postJson(route('kpi.ad-leads.send-to-followups', $entry), [
                'follow_up_date' => $todayStr,
                'remarks' => 'Should fail',
            ])
            ->assertForbidden();
    }
}
