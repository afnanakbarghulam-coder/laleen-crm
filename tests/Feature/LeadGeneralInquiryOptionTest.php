<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadGeneralInquiryOptionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Boss',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->agent = User::factory()->create([
            'name' => 'Zoya',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => [
                'leads' => 'edit',
                'kpis' => 'edit',
            ],
        ]);
    }

    public function test_offer_inquiry_general_inquiry_is_top_option_without_polluting_services_catalog(): void
    {
        Service::create([
            'name' => 'HydraFacial',
            'price' => 350,
            'duration' => 60,
        ]);

        $this->assertSame([
            'Offer Inquiry / General Inquiry',
            'Hairstyling / General Hairstyle',
        ], Lead::GENERAL_SERVICE_INTERESTS);

        $response = $this->actingAs($this->admin)
            ->get(route('leads.index', ['tab' => 'all']))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('data-section-header="general"', $html);
        $this->assertStringContainsString('General Inquiries', $html);
        $this->assertStringContainsString('<optgroup label="General Inquiries">', $html);

        // Both centralized options must appear under General Inquiries BEFORE "Service Combos" and "Individual Services"
        $posGeneralHeader = strpos($html, 'data-section-header="general"');
        $posOffer = strpos($html, 'data-value="' . Lead::GENERAL_INQUIRY_OPTION . '"');
        $posHairstyle = strpos($html, 'data-value="' . Lead::GENERAL_HAIRSTYLE_OPTION . '"');
        $posCombos = strpos($html, 'data-section-header="combos"');
        $posServices = strpos($html, 'data-section-header="services"');

        $this->assertNotFalse($posGeneralHeader);
        $this->assertNotFalse($posOffer);
        $this->assertNotFalse($posHairstyle);
        $this->assertNotFalse($posCombos);
        $this->assertNotFalse($posServices);

        $this->assertLessThan($posOffer, $posGeneralHeader);
        $this->assertLessThan($posHairstyle, $posOffer);
        $this->assertLessThan($posCombos, $posHairstyle);
        $this->assertLessThan($posServices, $posHairstyle);

        // Master services catalog must NEVER contain any fake general inquiry row
        $this->assertSame(1, Service::count());
        foreach (Lead::GENERAL_SERVICE_INTERESTS as $generalOption) {
            $this->assertDatabaseMissing('services', ['name' => $generalOption]);
        }
    }

    public function test_create_lead_with_offer_inquiry_requires_selection_displays_cleanly_and_replaces_on_edit(): void
    {
        Service::create([
            'name' => 'HydraFacial',
            'price' => 350,
            'duration' => 60,
        ]);

        $todayStr = Lead::salonToday()->toDateString();

        // 1. Blank service_interest must still be rejected (field remains required)
        $this->actingAs($this->agent)
            ->post(route('leads.store'), [
                'country_code' => '974',
                'phone_number' => '55881122',
                'customer_name' => 'Hessa Al-Kuwari',
                'category' => 'inquiry',
                'service_interest' => [],
                'next_followup_date' => $todayStr,
                'customer_remarks' => 'Asking about Ramadan offers',
            ])
            ->assertSessionHasErrors(['service_interest']);

        $this->assertSame(0, Lead::count());

        // 2. Saving with "Offer Inquiry / General Inquiry" succeeds cleanly
        $this->actingAs($this->agent)
            ->post(route('leads.store'), [
                'country_code' => '974',
                'phone_number' => '55881122',
                'customer_name' => 'Hessa Al-Kuwari',
                'category' => 'inquiry',
                'service_interest' => [Lead::GENERAL_INQUIRY_OPTION],
                'next_followup_date' => $todayStr,
                'customer_remarks' => 'Asked about current salon offers',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $lead = Lead::firstOrFail();
        $this->assertSame([Lead::GENERAL_INQUIRY_OPTION], $lead->service_interest);
        $this->assertSame(1, Service::count(), 'Master services table must stay untouched');

        // 3. Displays cleanly on Lead Management list (with general inquiry tag styling)
        $this->actingAs($this->agent)
            ->get(route('leads.index', ['tab' => 'due_today']))
            ->assertOk()
            ->assertSee('Hessa Al-Kuwari')
            ->assertSee(Lead::GENERAL_INQUIRY_OPTION)
            ->assertSee('lead-service-general');

        // 4. Editing lead to switch to a specific service smoothly replaces "Offer Inquiry / General Inquiry"
        // (even if both were submitted together, normalization strips the placeholder cleanly)
        $this->actingAs($this->agent)
            ->put(route('leads.update', $lead), [
                'country_code' => '974',
                'phone_number' => '55881122',
                'customer_name' => 'Hessa Al-Kuwari',
                'categories' => ['follow_up'],
                'service_interest' => [Lead::GENERAL_INQUIRY_OPTION, 'HydraFacial'],
                'next_followup_date' => $todayStr,
                'customer_remarks' => 'Decided on HydraFacial',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame(['HydraFacial'], $lead->service_interest);

        $this->actingAs($this->agent)
            ->get(route('leads.index', ['tab' => 'due_today']))
            ->assertOk()
            ->assertSee('HydraFacial')
            ->assertDontSee('lead-service-general rounded px-1.5 py-0.5 text-[11px]">Offer Inquiry / General Inquiry', false);
    }

    public function test_ads_list_send_to_followups_falls_back_to_offer_inquiry_for_non_service_campaigns(): void
    {
        Service::create([
            'name' => 'Hair Treatment',
            'price' => 300,
            'duration' => 60,
        ]);

        $dueDate = Lead::salonToday()->addDay()->toDateString();

        // A. Ad Lead with campaign "Ramadan Deal" (not a specific salon service) -> falls back to "Offer Inquiry / General Inquiry"
        $offerAd = AdLeadEntry::create([
            'date' => Lead::salonToday()->toDateString(),
            'customer_name' => 'Maha Al-Sulaiti',
            'phone' => '55883344',
            'category' => 'Ramadan Deal',
            'ticket_amount' => 0,
            'remarks' => 'Asked about Ramadan offer details',
        ]);

        $this->actingAs($this->agent)
            ->postJson(route('kpi.ad-leads.send-to-followups', $offerAd), [
                'follow_up_date' => $dueDate,
                'remarks' => 'Will decide which package tomorrow',
            ])
            ->assertOk();

        $offerLead = Lead::findOrFail($offerAd->fresh()->lead_id);
        $this->assertSame([Lead::GENERAL_INQUIRY_OPTION], $offerLead->service_interest);
        $this->assertSame('Paid Ads · Ramadan Deal', $offerLead->source);

        // B. Ad Lead whose category matches an actual salon service ("Hair Treatment") -> maps directly to that service
        $serviceAd = AdLeadEntry::create([
            'date' => Lead::salonToday()->toDateString(),
            'customer_name' => 'Latifa Al-Naimi',
            'phone' => '55885566',
            'category' => 'Hair Treatment',
            'ticket_amount' => 0,
            'remarks' => 'Wants keratin treatment',
        ]);

        $this->actingAs($this->agent)
            ->postJson(route('kpi.ad-leads.send-to-followups', $serviceAd), [
                'follow_up_date' => $dueDate,
                'remarks' => 'Wants keratin treatment',
            ])
            ->assertOk();

        $serviceLead = Lead::findOrFail($serviceAd->fresh()->lead_id);
        $this->assertSame(['Hair Treatment'], $serviceLead->service_interest);
    }

    public function test_ai_closing_script_handles_offer_inquiry_general_inquiry_gracefully_in_en_and_ar(): void
    {
        config(['services.openai.key' => null, 'services.gemini.key' => null]);

        Service::create([
            'name' => 'Offer Blowdry Special',
            'price' => 199,
            'duration' => 45,
        ]);

        $lead = Lead::create([
            'phone' => '55887799',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => [Lead::GENERAL_INQUIRY_OPTION],
            'next_followup_date' => Lead::salonToday()->toDateString(),
            'customer_remarks' => 'Asking about this week offers',
        ]);

        // English opener
        $enResp = $this->actingAs($this->agent)
            ->postJson(route('leads.ai-script', $lead), [
                'mode' => 'opener',
                'language' => 'en',
                'customer_name' => 'Aisha',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'service_pricing' => [
                    'matched_services' => [],
                    'primary_price_qar' => null,
                ],
            ]);

        $enScript = (string) $enResp->json('script');
        $this->assertStringContainsString('current salon offers', $enScript);
        $this->assertStringNotContainsString('Offer Inquiry / General Inquiry', $enScript);

        // Arabic opener
        $arResp = $this->actingAs($this->agent)
            ->postJson(route('leads.ai-script', $lead), [
                'mode' => 'opener',
                'language' => 'ar',
                'customer_name' => 'عائشة',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'service_pricing' => [
                    'matched_services' => [],
                    'primary_price_qar' => null,
                ],
            ]);

        $arScript = (string) $arResp->json('script');
        $this->assertStringContainsString('عروضنا الحالية في الصالون', $arScript);
        $this->assertStringNotContainsString('Offer Inquiry / General Inquiry', $arScript);
    }

    public function test_hairstyling_general_hairstyle_lifecycle_filter_sync_and_ai_scripts(): void
    {
        config(['services.openai.key' => null, 'services.gemini.key' => null]);

        Service::create([
            'name' => 'Hollywood Waves',
            'price' => 250,
            'duration' => 60,
        ]);

        Service::create([
            'name' => 'Haircut & Blowdry',
            'price' => 180,
            'duration' => 45,
        ]);

        $todayStr = Lead::salonToday()->toDateString();

        // 1. Create a lead with "Hairstyling / General Hairstyle"
        $this->actingAs($this->agent)
            ->post(route('leads.store'), [
                'country_code' => '974',
                'phone_number' => '55992211',
                'customer_name' => 'Noora Al-Thani',
                'category' => 'inquiry',
                'service_interest' => [Lead::GENERAL_HAIRSTYLE_OPTION],
                'next_followup_date' => $todayStr,
                'customer_remarks' => 'Has a wedding this weekend, undecided on hairstyle',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $hairLead = Lead::where('phone', '55992211')->firstOrFail();
        $this->assertSame([Lead::GENERAL_HAIRSTYLE_OPTION], $hairLead->service_interest);
        $this->assertSame(2, Service::count(), 'Master services table must stay untouched');
        $this->assertDatabaseMissing('services', ['name' => Lead::GENERAL_HAIRSTYLE_OPTION]);

        // Also create another lead with Offer Inquiry to verify service_interest filtering isolates cleanly
        Lead::create([
            'phone' => '55992299',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'service_interest' => [Lead::GENERAL_INQUIRY_OPTION],
            'next_followup_date' => $todayStr,
            'customer_remarks' => 'General offer question',
        ]);

        // 2. Table display & Lead Management Service Interest filter work cleanly for Hairstyling / General Hairstyle
        $this->actingAs($this->agent)
            ->get(route('leads.index', ['tab' => 'all', 'service_interest' => Lead::GENERAL_HAIRSTYLE_OPTION]))
            ->assertOk()
            ->assertSee('Noora Al-Thani')
            ->assertSee('lead-service-general rounded px-1.5 py-0.5 text-[11px]">' . Lead::GENERAL_HAIRSTYLE_OPTION, false)
            ->assertDontSee('General offer question');

        // 3. AI Closing Script & Price Inquiry Objection handle "Hairstyling / General Hairstyle" without fixed price/duration
        $enResp = $this->actingAs($this->agent)
            ->postJson(route('leads.ai-script', $hairLead), [
                'mode' => 'opener',
                'language' => 'en',
                'customer_name' => 'Noora',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'service_pricing' => [
                    'matched_services' => [],
                    'primary_price_qar' => null,
                ],
            ]);

        $enScript = (string) $enResp->json('script');
        $this->assertStringContainsString('hairstyling options', $enScript);
        $this->assertStringNotContainsString('Hairstyling / General Hairstyle', $enScript);
        $this->assertStringNotContainsString('180 QAR', $enScript);

        $arResp = $this->actingAs($this->agent)
            ->postJson(route('leads.ai-script', $hairLead), [
                'mode' => 'objection',
                'objection_type' => 'price_inquiry',
                'language' => 'ar',
                'customer_name' => 'نورة',
            ])
            ->assertOk();

        $arScript = (string) $arResp->json('script');
        $this->assertStringContainsString('تسريحات الشعر', $arScript);
        $this->assertStringNotContainsString('Hairstyling / General Hairstyle', $arScript);

        // 4. Ads List -> Send to Follow-Ups maps general hairstyling ad category cleanly
        $hairstyleAd = AdLeadEntry::create([
            'date' => $todayStr,
            'customer_name' => 'Dana Al-Mansoori',
            'phone' => '55993322',
            'category' => 'Hairstyling',
            'ticket_amount' => 0,
            'remarks' => 'Asking about event hairstyling',
        ]);

        $this->actingAs($this->agent)
            ->postJson(route('kpi.ad-leads.send-to-followups', $hairstyleAd), [
                'follow_up_date' => Lead::salonToday()->addDay()->toDateString(),
                'remarks' => 'Will send inspo photos tonight',
            ])
            ->assertOk();

        $syncedHairLead = Lead::findOrFail($hairstyleAd->fresh()->lead_id);
        $this->assertSame([Lead::GENERAL_HAIRSTYLE_OPTION], $syncedHairLead->service_interest);

        // 5. Editing the lead to pick a specific hairstyle smoothly replaces "Hairstyling / General Hairstyle"
        $this->actingAs($this->agent)
            ->put(route('leads.update', $hairLead), [
                'country_code' => '974',
                'phone_number' => '55992211',
                'customer_name' => 'Noora Al-Thani',
                'categories' => ['follow_up'],
                'service_interest' => [Lead::GENERAL_HAIRSTYLE_OPTION, 'Hollywood Waves'],
                'next_followup_date' => $todayStr,
                'customer_remarks' => 'Confirmed Hollywood Waves for Saturday',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $hairLead->refresh();
        $this->assertSame(['Hollywood Waves'], $hairLead->service_interest);

        $this->actingAs($this->agent)
            ->get(route('leads.index', ['tab' => 'due_today']))
            ->assertOk()
            ->assertSee('Hollywood Waves')
            ->assertDontSee('lead-service-general rounded px-1.5 py-0.5 text-[11px]">' . Lead::GENERAL_HAIRSTYLE_OPTION, false);
    }
}
