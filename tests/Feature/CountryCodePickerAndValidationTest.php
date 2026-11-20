<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryCodePickerAndValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Owner',
            'email' => 'admin@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        Service::create([
            'name' => 'Hydra Facial',
            'price' => 250,
            'duration' => 60,
        ]);
    }

    public function test_shared_country_config_provides_200_plus_countries_and_renders_searchable_picker_on_leads_and_ad_leads_pages(): void
    {
        $countries = config('countries.list', []);
        $pinnedIsos = config('countries.pinned_isos', []);

        $this->assertGreaterThanOrEqual(200, count($countries));
        $this->assertSame('+974', config('countries.default_dial_code'));
        $this->assertSame('QA', $pinnedIsos[0] ?? null);

        foreach ($countries as $row) {
            $this->assertArrayHasKey('iso', $row);
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('dial_code', $row);
            $this->assertStringStartsWith('+', $row['dial_code']);
        }

        // Verify international codes outside the old 13-country list exist in the global dataset
        $dialSet = collect($countries)->pluck('dial_code')->all();
        foreach (['+974', '+962', '+90', '+880', '+33', '+212', '+61', '+81'] as $expectedDial) {
            $this->assertContains($expectedDial, $dialSet);
        }

        // 1. Leads page renders the searchable dark picker and zero native <select name="country_code">
        $leadsRes = $this->actingAs($this->admin)->get(route('leads.index'));
        $leadsRes->assertOk()
            ->assertSee('data-ccp-picker', false)
            ->assertSee('ccp-search-input', false)
            ->assertSee('Search code e.g. 974, +962, 880 or country...', false)
            ->assertSee('window.__LALEEN_COUNTRIES__', false)
            ->assertDontSee('<select name="country_code"', false);

        // 2. KPIs -> Ad Leads Log page renders the searchable dark picker and zero native <select name="country_code">
        $adLeadsRes = $this->actingAs($this->admin)->get(route('kpi.ads.index'));
        $adLeadsRes->assertOk()
            ->assertSee('data-ccp-picker', false)
            ->assertSee('id="adLeadCountryCode"', false)
            ->assertSee('Search code e.g. 974, +962, 880 or country...', false)
            ->assertSee('window.__LALEEN_COUNTRIES__', false)
            ->assertDontSee('<select name="country_code"', false);
    }

    public function test_country_code_picker_is_never_nested_in_label_and_supports_modal_focus_trap_and_click_to_search(): void
    {
        // Seed one lead so the Edit Lead modal is also rendered in DOM on /leads
        $customer = Customer::create([
            'name' => 'Modal Focus Lead',
            'phone' => '55112233',
        ]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'phone' => '55112233',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'next_followup_date' => now()->addDay()->toDateString(),
        ]);

        foreach ([route('leads.index'), route('kpi.ads.index')] as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            $xpath = new \DOMXPath($dom);

            // 1. Every rendered picker must exist and NEVER be nested inside a <label>
            $pickers = $xpath->query('//*[@data-ccp-picker]');
            $this->assertGreaterThan(0, $pickers->length, "Expected at least one country code picker on {$url}");

            $pickersInsideLabels = $xpath->query('//label//*[@data-ccp-picker]');
            $this->assertSame(
                0,
                $pickersInsideLabels->length,
                "Country code picker must never be wrapped inside a <label> on {$url}"
            );

            // 2. Search input inside each picker must be a real text input with full placeholder
            $searchInputs = $xpath->query('//*[@data-ccp-picker]//input[contains(@class, "ccp-search-input")]');
            $this->assertSame($pickers->length, $searchInputs->length);
        }

        // Verify explicit label `for` bindings to phone inputs (not the picker)
        $leadsHtml = $this->actingAs($this->admin)->get(route('leads.index', ['tab' => 'all']))->getContent();
        $this->assertStringContainsString('for="addLeadPhoneNumber"', $leadsHtml);
        $this->assertStringContainsString('for="editLeadPhoneNumber-' . $lead->id . '"', $leadsHtml);
        $this->assertStringContainsString('for="filterLeadPhoneNumber"', $leadsHtml);

        // Verify modal-safe host mounting (fixes Bootstrap 5 FocusTrap focus-stealing), anti-truncation width, & focus/click guards
        $this->assertStringContainsString("root.closest('.modal') || document.body", $leadsHtml);
        $this->assertStringContainsString('min-width: 310px;', $leadsHtml);
        $this->assertStringContainsString('width: 340px;', $leadsHtml);
        $this->assertStringContainsString("dropdown.addEventListener('focusin'", $leadsHtml);
        $this->assertStringContainsString("searchInput.focus({ preventScroll: true })", $leadsHtml);

        $adLeadsHtml = $this->actingAs($this->admin)->get(route('kpi.ads.index'))->getContent();
        $this->assertStringContainsString('for="adLeadPhoneNumber"', $adLeadsHtml);
        $this->assertStringContainsString("root.closest('.modal') || document.body", $adLeadsHtml);
    }

    public function test_creating_and_updating_lead_and_ad_lead_with_international_and_qatar_codes_succeeds_and_normalizes_accurately(): void
    {
        // --- PART A: LEADS MODULE (Create +962 Jordan, Update to +880 Bangladesh, Create +974 Qatar) ---
        $storeLeadRes = $this->actingAs($this->admin)->post(route('leads.store'), [
            'country_code' => '962',
            'phone_number' => '079 123 4567',
            'customer_name' => 'Rania Al-Majali',
            'category' => 'inquiry',
            'service_interest' => ['Hydra Facial'],
            'next_followup_date' => now()->addDay()->toDateString(),
            'customer_remarks' => 'Visiting Doha from Amman',
        ]);
        $storeLeadRes->assertRedirect();

        $intlLead = Lead::where('phone', '962791234567')->first();
        $this->assertNotNull($intlLead, 'Expected Jordan (+962) lead to normalize to 962791234567');
        $this->assertSame(['962', '791234567'], Lead::splitPhone($intlLead->phone));
        $this->assertNotNull(Customer::where('phone', '962791234567')->first());

        // Edit modal on Leads page pre-fills +962 and 791234567 cleanly
        $this->actingAs($this->admin)->get(route('leads.index', ['tab' => 'all']))
            ->assertOk()
            ->assertSee('value="962"', false)
            ->assertSee('value="791234567"', false);

        // Update Lead from +962 to +880 (Bangladesh)
        $updateLeadRes = $this->actingAs($this->admin)->put(route('leads.update', $intlLead), [
            'country_code' => '+880',
            'phone_number' => '01712-345678',
            'customer_name' => 'Rania Updated',
            'categories' => ['follow_up'],
            'service_interest' => ['Hydra Facial'],
            'next_followup_date' => now()->addDays(2)->toDateString(),
        ]);
        $updateLeadRes->assertRedirect();

        $intlLead->refresh();
        $this->assertSame('8801712345678', $intlLead->phone);
        $this->assertSame(['880', '1712345678'], Lead::splitPhone($intlLead->phone));

        // Create Qatar (+974) Lead and verify 8-digit local storage + 8-digit validation guardrail
        $this->actingAs($this->admin)->post(route('leads.store'), [
            'country_code' => '974',
            'phone_number' => '5544 7788',
            'customer_name' => 'Dana Al-Thani',
            'category' => 'inquiry',
            'service_interest' => ['Hydra Facial'],
            'next_followup_date' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $qatarLead = Lead::where('phone', '55447788')->first();
        $this->assertNotNull($qatarLead);
        $this->assertSame(['974', '55447788'], Lead::splitPhone($qatarLead->phone));

        // Short 5-digit Qatar (+974) number is rejected with 422
        $this->actingAs($this->admin)->postJson(route('leads.store'), [
            'country_code' => '974',
            'phone_number' => '12345',
            'customer_name' => 'Invalid Qatar Phone',
            'category' => 'inquiry',
            'service_interest' => ['Hydra Facial'],
            'next_followup_date' => now()->addDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('phone_number');

        // --- PART B: KPIs -> AD LEADS LOG (Create +90 Turkey, Update to +33 France, Create +974 Qatar) ---
        $defaultCategory = AdLeadEntry::availableCategories()[0] ?? 'Facial Ads';

        $storeAdRes = $this->actingAs($this->admin)->post(route('kpi.ad-leads.store'), [
            'date' => now()->toDateString(),
            'customer_name' => 'Elif Yilmaz',
            'country_code' => '90',
            'phone_number' => '532 123 4567',
            'category' => $defaultCategory,
            'remarks' => 'Instagram ad inquiry',
        ]);
        $storeAdRes->assertRedirect(route('kpi.ads.index'));

        $adEntry = AdLeadEntry::where('phone', '905321234567')->first();
        $this->assertNotNull($adEntry, 'Expected Turkey (+90) ad lead to normalize to 905321234567');
        $this->assertSame(['90', '5321234567'], Lead::splitPhone($adEntry->phone));

        // Update Ad Lead from +90 (Turkey) to +33 (France)
        $updateAdRes = $this->actingAs($this->admin)->put(route('kpi.ad-leads.update', $adEntry), [
            'date' => now()->toDateString(),
            'customer_name' => 'Claire Moreau',
            'country_code' => '+33',
            'phone_number' => '06 12 34 56 78',
            'category' => $defaultCategory,
            'remarks' => 'Updated to French WhatsApp number',
        ]);
        $updateAdRes->assertRedirect(route('kpi.ads.index'));

        $adEntry->refresh();
        $this->assertSame('33612345678', $adEntry->phone);
        $this->assertSame(['33', '612345678'], Lead::splitPhone($adEntry->phone));

        // Ad Lead with +974 normalizes cleanly to 8-digit local phone
        $this->actingAs($this->admin)->post(route('kpi.ad-leads.store'), [
            'date' => now()->toDateString(),
            'customer_name' => 'Hissa Al-Kuwari',
            'country_code' => '+974',
            'phone_number' => '6611 8899',
            'category' => $defaultCategory,
        ])->assertRedirect(route('kpi.ads.index'));

        $this->assertNotNull(AdLeadEntry::where('phone', '66118899')->first());
    }
}
