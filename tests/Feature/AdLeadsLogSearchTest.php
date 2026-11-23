<?php

namespace Tests\Feature;

use App\Models\AdCampaignMetric;
use App\Models\AdLeadEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdLeadsLogSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Boss',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_search_filters_by_case_insensitive_name_and_normalized_phone_variants(): void
    {
        $today = now()->toDateString();

        AdLeadEntry::create([
            'date' => $today,
            'customer_name' => 'Fatima Al-Kuwari',
            'phone' => '77132353',
            'category' => 'Facial',
            'ticket_amount' => 250,
            'branch' => 'old_airport',
        ]);

        AdLeadEntry::create([
            'date' => $today,
            'customer_name' => 'Sara Al-Mansoori',
            'phone' => '+974 5590-8877',
            'category' => 'Hair Color',
            'ticket_amount' => 300,
            'branch' => 'wakrah',
        ]);

        // Filter bar UI renders cleanly
        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'leads']))
            ->assertOk()
            ->assertSee('id="adLeadsSearchInput"', false)
            ->assertSee('Name or phone number...')
            ->assertSee('Fatima Al-Kuwari')
            ->assertSee('Sara Al-Mansoori');

        // 1. Partial case-insensitive name search
        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'leads', 'search' => 'fatima']))
            ->assertOk()
            ->assertSee('Fatima Al-Kuwari')
            ->assertDontSee('Sara Al-Mansoori');

        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'leads', 'search' => 'AL-KUW']))
            ->assertOk()
            ->assertSee('Fatima Al-Kuwari')
            ->assertDontSee('Sara Al-Mansoori');

        // 2. Full phone with +974 and spaces
        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'leads', 'search' => '+974 7713 2353']))
            ->assertOk()
            ->assertSee('Fatima Al-Kuwari')
            ->assertDontSee('Sara Al-Mansoori');

        // 3. 00974 prefix with dashes
        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'leads', 'search' => '00974-7713-2353']))
            ->assertOk()
            ->assertSee('Fatima Al-Kuwari')
            ->assertDontSee('Sara Al-Mansoori');

        // 4. 8-digit local number (matching both raw 8-digit and stored formatted +974 number)
        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'leads', 'search' => '77132353']))
            ->assertOk()
            ->assertSee('Fatima Al-Kuwari')
            ->assertDontSee('Sara Al-Mansoori');

        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'leads', 'search' => '55908877']))
            ->assertOk()
            ->assertSee('Sara Al-Mansoori')
            ->assertDontSee('Fatima Al-Kuwari');

        // 5. Last 4 digits
        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'leads', 'search' => '2353']))
            ->assertOk()
            ->assertSee('Fatima Al-Kuwari')
            ->assertDontSee('Sara Al-Mansoori');

        $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'leads', 'search' => '8877']))
            ->assertOk()
            ->assertSee('Sara Al-Mansoori')
            ->assertDontSee('Fatima Al-Kuwari');
    }

    public function test_empty_state_offers_search_all_dates_and_preserves_search_across_pagination_and_actions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 12:00:00', 'Asia/Qatar'));

        try {
            // Older lead from September (outside default November date range)
            $olderLead = AdLeadEntry::create([
                'date' => '2026-09-10',
                'customer_name' => 'Amina Vintage Client',
                'phone' => '77990011',
                'category' => 'Bridal Package',
                'ticket_amount' => 0,
            ]);

            // Searching in default November range yields 0 rows + "Search all dates" CTA
            $emptyResp = $this->actingAs($this->admin)
                ->get(route('kpi.ads.index', ['tab' => 'leads', 'search' => 'Amina Vintage']))
                ->assertOk()
                ->assertDontSee('77990011')
                ->assertSee('Search all dates')
                ->assertSee('all_dates=1', false);

            // Clicking "Search all dates" clears date bounds and finds the September entry
            $this->actingAs($this->admin)
                ->get(route('kpi.ads.index', ['tab' => 'leads', 'search' => 'Amina Vintage', 'all_dates' => 1]))
                ->assertOk()
                ->assertSee('Amina Vintage Client')
                ->assertSee('77990011');

            // Create 22 matching entries in November to verify pagination preserves search query
            for ($i = 1; $i <= 22; $i++) {
                AdLeadEntry::create([
                    'date' => '2026-11-15',
                    'customer_name' => "Noora Batch {$i}",
                    'phone' => sprintf('5500%04d', $i),
                    'category' => 'Facial',
                    'ticket_amount' => 0,
                ]);
            }

            $page1 = $this->actingAs($this->admin)
                ->get(route('kpi.ads.index', ['tab' => 'leads', 'search' => 'Noora Batch']))
                ->assertOk();

            $nextPageUrl = (string) $page1->viewData('adLeadEntries')->nextPageUrl();
            $this->assertStringContainsString('entries_page=2', $nextPageUrl);
            $this->assertMatchesRegularExpression('/search=Noora(%20|\+)Batch/', $nextPageUrl);
            $this->assertStringContainsString('entries_page=2', $page1->getContent());

            // Edit & Delete preserve active search query via return_filters
            $editResp = $this->actingAs($this->admin)
                ->put(route('kpi.ad-leads.update', $olderLead), [
                    'date' => '2026-09-10',
                    'customer_name' => 'Amina Vintage Updated',
                    'country_code' => '974',
                    'phone_number' => '77990011',
                    'category' => 'Bridal Package',
                    'remarks' => 'Edited while searching',
                    'return_filters' => [
                        'tab' => 'leads',
                        'search' => 'Amina Vintage',
                        'all_dates' => '1',
                    ],
                ])
                ->assertRedirect();

            $location = (string) $editResp->headers->get('Location');
            $this->assertMatchesRegularExpression('/search=Amina(%20|\+)Vintage/', $location);
            $this->assertStringContainsString('all_dates=1', $location);

            $deleteResp = $this->actingAs($this->admin)
                ->delete(route('kpi.ad-leads.destroy', $olderLead), [
                    'return_filters' => [
                        'tab' => 'leads',
                        'search' => 'Amina Vintage',
                        'all_dates' => '1',
                    ],
                ])
                ->assertRedirect();

            $delLocation = (string) $deleteResp->headers->get('Location');
            $this->assertMatchesRegularExpression('/search=Amina(%20|\+)Vintage/', $delLocation);
            $this->assertStringContainsString('all_dates=1', $delLocation);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_ads_analytics_and_ad_metrics_remain_unaffected_by_ad_leads_search(): void
    {
        $today = now()->toDateString();

        AdLeadEntry::create([
            'date' => $today,
            'customer_name' => 'Fatima Analytics',
            'phone' => '77112233',
            'category' => 'Facial',
            'ticket_amount' => 400,
            'branch' => 'old_airport',
            'old_airport_amount' => 400,
        ]);

        AdLeadEntry::create([
            'date' => $today,
            'customer_name' => 'Hessa Analytics',
            'phone' => '77445566',
            'category' => 'Hair Color',
            'ticket_amount' => 600,
            'branch' => 'wakrah',
            'wakrah_amount' => 600,
        ]);

        AdCampaignMetric::create([
            'workspace' => 'salon',
            'campaign_name' => 'Salon Glow Campaign',
            'normalized_campaign_key' => 'salon-glow-campaign',
            'date_from' => $today,
            'date_to' => $today,
            'amount_spent' => 100,
            'amount_spent_qar' => 365,
            'conversations' => 20,
        ]);

        $baseline = $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'leads']))
            ->assertOk();

        $filtered = $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'leads', 'search' => 'Fatima']))
            ->assertOk();

        $this->assertSame(2, $baseline->viewData('adLeadEntries')->total());
        $this->assertSame(1, $filtered->viewData('adLeadEntries')->total());

        // Analytics totals & financials must be 100% identical regardless of Ad Leads Log search term
        $this->assertSame(
            $baseline->viewData('analyticsReport')->totals(),
            $filtered->viewData('analyticsReport')->totals()
        );
        $this->assertSame(
            $baseline->viewData('analyticsFinancials'),
            $filtered->viewData('analyticsFinancials')
        );
    }

    public function test_suggestions_endpoint_enforces_auth_deduplicates_prioritizes_in_range_and_matches_name_and_phone(): void
    {
        // User without kpis permission gets 403
        $noKpiUser = User::factory()->create([
            'name' => 'No KPI Staff',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => ['leads' => 'view'],
        ]);

        $this->actingAs($noKpiUser)
            ->getJson(route('kpi.ad-leads.suggestions', ['q' => 'amna']))
            ->assertForbidden();

        // Fewer than 2 characters returns []
        $this->actingAs($this->admin)
            ->getJson(route('kpi.ad-leads.suggestions', ['q' => 'a']))
            ->assertOk()
            ->assertExactJson([]);

        $this->actingAs($this->admin)
            ->getJson(route('kpi.ad-leads.suggestions', ['q' => '   ']))
            ->assertOk()
            ->assertExactJson([]);

        // Duplicate contact: older entry in September + newer entry in November -> must deduplicate keeping latest (Nov)
        AdLeadEntry::create([
            'date' => '2026-09-05',
            'customer_name' => 'Amna Al-Hajri',
            'phone' => '55902003',
            'category' => 'Facial',
            'ticket_amount' => 0,
        ]);
        $amnaLatest = AdLeadEntry::create([
            'date' => '2026-11-18',
            'customer_name' => 'Amna Al-Hajri',
            'phone' => '+974 5590 2003',
            'category' => 'Nails',
            'ticket_amount' => 0,
        ]);

        // Out-of-range contact (October) vs in-range contact (November)
        $outOfRange = AdLeadEntry::create([
            'date' => '2026-10-12',
            'customer_name' => 'Amna Outside Range',
            'phone' => '55909999',
            'category' => 'Hair Color',
            'ticket_amount' => 0,
        ]);

        // Search by name with November from/to range -> in-range entry comes FIRST, outside-range comes SECOND, duplicate collapsed
        $nameResp = $this->actingAs($this->admin)
            ->getJson(route('kpi.ad-leads.suggestions', [
                'q' => 'amna',
                'from' => '2026-11-01',
                'to' => '2026-11-23',
            ]))
            ->assertOk();

        $items = $nameResp->json();
        $this->assertCount(2, $items);

        $this->assertSame([
            'id' => $amnaLatest->id,
            'customer_name' => 'Amna Al-Hajri',
            'phone' => '+974 5590 2003',
            'ad_type' => 'Nails',
            'date' => '18 Nov 2026',
            'raw_date' => '2026-11-18',
            'in_range' => true,
        ], $items[0]);

        $this->assertSame([
            'id' => $outOfRange->id,
            'customer_name' => 'Amna Outside Range',
            'phone' => '55909999',
            'ad_type' => 'Hair Color',
            'date' => '12 Oct 2026',
            'raw_date' => '2026-10-12',
            'in_range' => false,
        ], $items[1]);

        // Search by normalized phone with +974 and space matches both contacts cleanly
        $phoneResp = $this->actingAs($this->admin)
            ->getJson(route('kpi.ad-leads.suggestions', [
                'q' => '+974 5590',
                'from' => '2026-11-01',
                'to' => '2026-11-23',
            ]))
            ->assertOk();

        $phoneItems = $phoneResp->json();
        $this->assertCount(2, $phoneItems);
        $this->assertSame($amnaLatest->id, $phoneItems[0]['id']);
        $this->assertTrue($phoneItems[0]['in_range']);
        $this->assertSame($outOfRange->id, $phoneItems[1]['id']);
        $this->assertFalse($phoneItems[1]['in_range']);
    }
}
