<?php

namespace Tests\Feature;

use App\Models\AdCampaignMetric;
use App\Models\AdLeadEntry;
use App\Models\KpiAdsConversionReport;
use App\Models\User;
use App\Services\AdMetricsScreenshotParser;
use App\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class AdMetricsScreenshotImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(ExchangeRateService::CACHE_KEY_USD_RATES);

        $this->admin = User::factory()->create([
            'name' => 'Afnan Admin',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_tracker_and_upload_modal_contain_zero_date_inputs_and_single_upload_button(): void
    {
        $hubRes = $this->actingAs($this->admin)->get(route('kpi.marketing.index'));

        $hubRes->assertOk()
            ->assertSee('Ads Conversion Report')
            ->assertSee('Weekly Content Calendar')
            ->assertSee('Monthly Ads Plan')
            ->assertDontSee('25-Metric Ad Tracker')
            ->assertDontSee('Upload Ads Screenshot')
            ->assertDontSee('Upload Screenshot');

        // 1. Ad Leads Log (/kpis/ads and /kpis/ads?tab=leads) MUST NOT render Upload Screenshot or upload modal
        foreach ([route('kpi.ads.index'), route('kpi.ads.index', ['tab' => 'leads'])] as $leadsUrl) {
            $leadsTabRes = $this->actingAs($this->admin)->get($leadsUrl);
            $leadsTabRes->assertOk()
                ->assertSee('Add Entry')
                ->assertDontSee('Upload Screenshot')
                ->assertDontSee('id="adScreenshotImportModal"', false);
        }

        // 2. Ads Analytics (/kpis/ads?tab=analytics) MUST NOT render Upload Screenshot or upload modal
        $analyticsTabRes = $this->actingAs($this->admin)->get(route('kpi.ads.index', ['tab' => 'analytics']));
        $analyticsTabRes->assertOk()
            ->assertSee('Ads Analytics')
            ->assertDontSee('Upload Screenshot')
            ->assertDontSee('id="adScreenshotImportModal"', false);

        // 3. Ad Metrics (/kpis/ads?tab=metrics) MUST render Upload Screenshot exactly once + upload modal
        $trackerRes = $this->actingAs($this->admin)->get(route('kpi.ads.index', ['tab' => 'metrics']));
        $trackerRes->assertOk()
            ->assertSee('Ad Metrics')
            ->assertSee('Upload Screenshot')
            ->assertSee('id="adScreenshotImportModal"', false)
            ->assertSee('No campaign metrics for this period yet.');

        $fullHtml = $trackerRes->getContent();
        $this->assertSame(1, substr_count($fullHtml, 'data-bs-target="#adScreenshotImportModal"'));
        $this->assertSame(1, substr_count($fullHtml, 'Upload Screenshot'));

        // Inspect #ads-pane-metrics + #adScreenshotImportModal (starts at id="ads-pane-metrics")
        $metricsAndModalPos = strpos($fullHtml, 'id="ads-pane-metrics"');
        $this->assertNotFalse($metricsAndModalPos);
        $metricsAndModalHtml = substr($fullHtml, $metricsAndModalPos);

        // Neither tracker pane nor upload modal may render manual From / To date inputs
        $this->assertStringNotContainsString('type="date"', $metricsAndModalHtml);
        $this->assertStringNotContainsString('metrics_from', $metricsAndModalHtml);
        $this->assertStringNotContainsString('metrics_to', $metricsAndModalHtml);
        $this->assertStringNotContainsString('Period From', $metricsAndModalHtml);
        $this->assertStringNotContainsString('Period To', $metricsAndModalHtml);

        // Also check standalone Ecommerce Ad Metrics page
        $ecRes = $this->actingAs($this->admin)->get(route('ecommerce.marketing.ad-metrics', ['region' => 'qa']));
        $ecRes->assertOk();
        $this->assertStringNotContainsString('type="date"', $ecRes->getContent());
    }

    public function test_rolling_campaign_upsert_extracts_dates_automatically_updates_4_campaigns_in_place_next_day_and_ignores_ranking_columns(): void
    {
        Storage::fake('local');
        Cache::forget(ExchangeRateService::CACHE_KEY_USD_RATES);

        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'rates' => [
                    'USD' => 1.0,
                    'QAR' => 3.6425,
                    'PKR' => 279.20,
                ],
            ], 200),
        ]);

        // --- DAY 1 UPLOAD: Screenshot header shows "1 Nov 2026 - 16 Nov 2026" ---
        $mockDay1 = Mockery::mock(AdMetricsScreenshotParser::class)->makePartial();
        $mockDay1->shouldReceive('parseScreenshots')
            ->once()
            ->andReturnUsing(function () use ($mockDay1) {
                return $mockDay1->normalizeExtractionPayload([
                    'account_currency' => '$',
                    'date_range' => '1 Nov 2026 - 16 Nov 2026',
                    'campaigns' => [
                        [
                            'campaign_name' => 'Frizzy, dry, ads',
                            'impressions' => '20,000',
                            'cpm' => '$5.50',
                            'link_clicks' => '250',
                            'cpc' => '$0.44',
                            'ctr' => '1.25%',
                            'conversion_rate_ranking' => 'Above average',
                            'engagement_rate_ranking' => 'Below average (bottom 35% of ads)',
                            'quality_ranking' => '—',
                            'cvr' => '—',
                            'amount_spent' => '$110.00',
                        ],
                        [
                            'campaign_name' => '6 Services 99 QAR',
                            'impressions' => 12000,
                            'cpm' => 3.33,
                            'link_clicks' => 180,
                            'cpc' => 0.22,
                            'ctr' => 1.50,
                            'conversion_rate_ranking' => '—',
                            'amount_spent' => '$40.00',
                        ],
                        [
                            'campaign_name' => 'hair color offer',
                            'impressions' => 15000,
                            'cpm' => 4.00,
                            'link_clicks' => 200,
                            'cpc' => 0.30,
                            'ctr' => 1.33,
                            'conversion_rate_ranking' => 'Average',
                            'amount_spent' => '$60.00',
                        ],
                        [
                            'campaign_name' => '7 services offer 99',
                            'impressions' => 8000,
                            'cpm' => 3.75,
                            'link_clicks' => 120,
                            'cpc' => 0.25,
                            'ctr' => 1.50,
                            'amount_spent' => '$30.00',
                        ],
                        [
                            'campaign_name' => 'Total results',
                            'impressions' => 55000,
                            'link_clicks' => 750,
                            'amount_spent' => 240.00,
                        ],
                    ],
                ]);
            });

        $this->app->instance(AdMetricsScreenshotParser::class, $mockDay1);

        // Notice NO date_from / date_to passed in request!
        $previewDay1 = $this->actingAs($this->admin)->postJson(route('kpi.ad-metrics.extract'), [
            'screenshots' => [UploadedFile::fake()->image('nov-01-16.png', 1440, 800)],
            'workspace' => 'salon',
            'region' => 'qa',
            'branch' => 'both',
        ]);

        $previewDay1->assertOk()
            ->assertJsonPath('date_from', '2026-11-01')
            ->assertJsonPath('date_to', '2026-11-16')
            ->assertJsonPath('period_label', '01 Nov 2026 – 16 Nov 2026')
            ->assertJsonPath('fx_rate_to_local', 3.6425)
            ->assertJsonCount(4, 'campaigns');

        $confirmDay1 = $this->actingAs($this->admin)->postJson(route('kpi.ad-metrics.confirm'), [
            'import_log_id' => $previewDay1->json('import_log_id'),
            'workspace' => 'salon',
            'region' => 'qa',
            'branch' => 'both',
            'date_from' => $previewDay1->json('date_from'),
            'date_to' => $previewDay1->json('date_to'),
            'account_currency' => 'USD',
            'local_currency' => 'QAR',
            'campaigns' => $previewDay1->json('campaigns'),
        ]);

        $confirmDay1->assertOk()->assertJsonPath('saved_count', 4);
        $this->assertSame(4, AdCampaignMetric::count());

        $frizzyDay1 = AdCampaignMetric::where('campaign_name', 'Frizzy, dry, ads')->firstOrFail();
        $this->assertSame('2026-11-01', $frizzyDay1->date_from->toDateString());
        $this->assertSame('2026-11-16', $frizzyDay1->date_to->toDateString());
        // Qualitative Conversion rate ranking / Engagement rate ranking NEVER populate cvr or engagement_rate!
        $this->assertNull($frizzyDay1->cvr);
        $this->assertNull($frizzyDay1->engagement_rate);
        // $110 * 3.6425 = 400.68 QAR
        $this->assertEquals(400.68, $frizzyDay1->amount_spent_qar);

        // --- DAY 2 CUMULATIVE UPLOAD: Screenshot header shows "1 Nov 2026 - 17 Nov 2026" ---
        $mockDay2 = Mockery::mock(AdMetricsScreenshotParser::class)->makePartial();
        $mockDay2->shouldReceive('parseScreenshots')
            ->once()
            ->andReturnUsing(function () use ($mockDay2) {
                return $mockDay2->normalizeExtractionPayload([
                    'account_currency' => 'USD',
                    'date_from' => '2026-11-01',
                    'date_to' => '2026-11-17',
                    'campaigns' => [
                        [
                            'campaign_name' => '  frizzy, DRY, ads ',
                            'impressions' => 23500,
                            'cpm' => 5.40,
                            'link_clicks' => 295,
                            'cpc' => 0.43,
                            'ctr' => 1.26,
                            'amount_spent' => 126.90,
                        ],
                        [
                            'campaign_name' => '6 Services 99 QAR',
                            'impressions' => 14000,
                            'cpm' => 3.40,
                            'link_clicks' => 210,
                            'cpc' => 0.23,
                            'ctr' => 1.50,
                            'amount_spent' => 47.60,
                        ],
                        [
                            'campaign_name' => 'hair color offer',
                            'impressions' => 17200,
                            'cpm' => 4.10,
                            'link_clicks' => 230,
                            'cpc' => 0.31,
                            'ctr' => 1.34,
                            'amount_spent' => 70.52,
                        ],
                        [
                            'campaign_name' => '7 services offer 99',
                            'impressions' => 9500,
                            'cpm' => 3.80,
                            'link_clicks' => 145,
                            'cpc' => 0.25,
                            'ctr' => 1.53,
                            'amount_spent' => 36.10,
                        ],
                    ],
                ]);
            });

        $this->app->instance(AdMetricsScreenshotParser::class, $mockDay2);

        $previewDay2 = $this->actingAs($this->admin)->postJson(route('kpi.ad-metrics.extract'), [
            'screenshots' => [UploadedFile::fake()->image('nov-01-17.png', 1440, 800)],
            'workspace' => 'salon',
            'region' => 'qa',
        ]);

        $previewDay2->assertOk()
            ->assertJsonPath('date_from', '2026-11-01')
            ->assertJsonPath('date_to', '2026-11-17')
            ->assertJsonPath('campaigns.0.merge_mode', 'merge');

        $confirmDay2 = $this->actingAs($this->admin)->postJson(route('kpi.ad-metrics.confirm'), [
            'import_log_id' => $previewDay2->json('import_log_id'),
            'workspace' => 'salon',
            'region' => 'qa',
            'date_from' => $previewDay2->json('date_from'),
            'date_to' => $previewDay2->json('date_to'),
            'account_currency' => 'USD',
            'local_currency' => 'QAR',
            'campaigns' => $previewDay2->json('campaigns'),
        ]);

        $confirmDay2->assertOk()->assertJsonPath('saved_count', 4);

        // CRITICAL: Row count MUST remain 4 (updated in place, ZERO duplicate date rows!)
        $this->assertSame(4, AdCampaignMetric::count());

        $frizzyDay2 = AdCampaignMetric::where('normalized_campaign_key', 'frizzy, dry, ads')->firstOrFail();
        $this->assertSame($frizzyDay1->id, $frizzyDay2->id);
        $this->assertSame('2026-11-01', $frizzyDay2->date_from->toDateString());
        $this->assertSame('2026-11-17', $frizzyDay2->date_to->toDateString());
        $this->assertSame(23500, $frizzyDay2->impressions);
        $this->assertSame(295, $frizzyDay2->link_clicks);
        $this->assertEquals(126.90, $frizzyDay2->amount_spent);

        // Tracker page shows "Snapshot: 01 Nov 2026 – 17 Nov 2026" and hides empty Revenue / ROAS / CVR columns
        $trackerHtml = $this->actingAs($this->admin)
            ->get(route('kpi.ads.index', ['tab' => 'metrics']))
            ->assertOk()
            ->assertSee('01 Nov 2026 – 17 Nov 2026')
            ->getContent();

        $paneHtml = substr($trackerHtml, (int) strpos($trackerHtml, 'id="ads-pane-metrics"'));
        $this->assertStringNotContainsString('data-col-key="revenue"', $paneHtml);
        $this->assertStringNotContainsString('data-col-key="roas"', $paneHtml);
        $this->assertStringNotContainsString('data-col-key="cvr"', $paneHtml);
    }

    public function test_cropped_horizontal_scroll_screenshot_preserves_existing_period_and_reveals_merged_conversion_columns(): void
    {
        // Seed initial row with period 01–17 Nov and traffic columns
        AdCampaignMetric::mergeAndUpsert([
            'workspace' => 'salon',
            'region' => 'qa',
            'branch' => 'old_airport',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-17',
            'account_currency' => 'USD',
            'local_currency' => 'QAR',
            'fx_rate_to_local' => 3.6415,
        ], [
            'campaign_name' => 'Frizzy, dry, ads',
            'impressions' => 20000,
            'cpm' => 5.00,
            'link_clicks' => 250,
            'cpc' => 0.40,
            'ctr' => 1.25,
        ], $this->admin->id);

        // Merge Screenshot 2 where top date bar was cropped out (date_from = null, date_to = null)
        $merged = AdCampaignMetric::mergeAndUpsert([
            'workspace' => 'salon',
            'region' => 'qa',
            'date_from' => null,
            'date_to' => null,
            'account_currency' => 'USD',
            'local_currency' => 'QAR',
            'fx_rate_to_local' => 3.6415,
        ], [
            'campaign_name' => 'Frizzy, dry, ads',
            'amount_spent' => 100.00,
            'reach' => 16000,
            'purchases' => 5,
            'revenue' => 450.00,
        ], $this->admin->id);

        $this->assertSame(1, AdCampaignMetric::count());
        // Existing period 01–17 Nov and Screenshot 1 columns remain intact!
        $this->assertSame('2026-11-01', $merged->date_from->toDateString());
        $this->assertSame('2026-11-17', $merged->date_to->toDateString());
        $this->assertSame(20000, $merged->impressions);
        $this->assertSame(250, $merged->link_clicks);
        $this->assertEquals(4.50, $merged->roas);
        $this->assertEquals(350.00, $merged->roi);
        $this->assertEquals(90.00, $merged->aov);
        $this->assertEquals(2.00, $merged->cvr);

        $page = $this->actingAs($this->admin)->get(route('kpi.ads.index', ['tab' => 'metrics']));
        $paneHtml = substr($page->getContent(), (int) strpos($page->getContent(), 'id="ads-pane-metrics"'));
        $this->assertStringContainsString('data-col-key="revenue"', $paneHtml);
        $this->assertStringContainsString('data-col-key="roas"', $paneHtml);
        $this->assertStringContainsString('data-col-key="cvr"', $paneHtml);
        $this->assertStringContainsString('ROAS: 4.50x', $paneHtml);
    }

    public function test_linking_crm_ad_lead_revenue_and_gemini_vision_date_extraction(): void
    {
        AdLeadEntry::create([
            'date' => '2026-11-05',
            'customer_name' => 'Noor Al Thani',
            'phone' => '+97455112233',
            'category' => 'Hair Color',
            'branch' => 'old_airport',
            'ticket_amount' => 728.30,
            'created_by' => $this->admin->id,
        ]);

        $metric = AdCampaignMetric::mergeAndUpsert([
            'workspace' => 'salon',
            'region' => 'qa',
            'branch' => 'both',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-17',
            'account_currency' => 'USD',
            'local_currency' => 'QAR',
            'fx_rate_to_local' => 3.6415,
        ], [
            'campaign_name' => 'hair color offer',
            'ad_category' => 'Hair Color',
            'link_crm_revenue' => true,
            'amount_spent' => 50.00,
            'impressions' => 12000,
            'link_clicks' => 200,
            'leads_count' => 20,
        ], $this->admin->id);

        $this->assertEquals(728.30, $metric->revenue_qar);
        $this->assertEquals(4.00, $metric->roas);

        config(['services.gemini.key' => 'test-gemini-vision-key']);
        Cache::forget(ExchangeRateService::CACHE_KEY_USD_RATES);

        Http::fake([
            'open.er-api.com/*' => Http::response([
                'rates' => ['USD' => 1.0, 'QAR' => 3.6415],
            ], 200),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'account_currency' => 'USD',
                                        'date_from' => '2026-11-01',
                                        'date_to' => '2026-11-16',
                                        'campaigns' => [
                                            [
                                                'campaign_name' => '6 Services 99 QAR',
                                                'impressions' => 19324,
                                                'cpm' => 1.32,
                                                'link_clicks' => 589,
                                                'conversion_rate_ranking' => '—',
                                            ],
                                            [
                                                'campaign_name' => '6 Services 99 QAR',
                                                'amount_spent' => 25.51,
                                                'reach' => 14200,
                                            ],
                                        ],
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $parser = app(AdMetricsScreenshotParser::class);
        $parsed = $parser->parseScreenshots([
            UploadedFile::fake()->image('left.png', 1200, 700),
            UploadedFile::fake()->image('right.png', 1200, 700),
        ]);

        $this->assertSame('2026-11-01', $parsed['date_from']);
        $this->assertSame('2026-11-16', $parsed['date_to']);
        $this->assertSame('01 Nov 2026 – 16 Nov 2026', $parsed['period_label']);
        $this->assertNull($parsed['campaigns'][0]['cvr']);
        $this->assertEquals(25.51, $parsed['campaigns'][0]['amount_spent']);
    }

    public function test_ads_analytics_tab_renders_minimal_single_screen_layout_slide_over_drawers_and_snapshot_crud(): void
    {
        // Seed Old Airport (2 bookings = 3,500 QAR) vs Al Wakrah (3 bookings = 3,600 QAR) + 1 unbooked lead
        AdLeadEntry::create([
            'date' => '2026-11-03',
            'customer_name' => 'Aisha',
            'phone' => '+97455001001',
            'category' => 'Hair Treatment',
            'branch' => 'old_airport',
            'ticket_amount' => 1800,
            'created_by' => $this->admin->id,
        ]);
        AdLeadEntry::create([
            'date' => '2026-11-04',
            'customer_name' => 'Dana',
            'phone' => '+97455001002',
            'category' => 'Hair Treatment',
            'branch' => 'old_airport',
            'ticket_amount' => 1700,
            'created_by' => $this->admin->id,
        ]);
        AdLeadEntry::create([
            'date' => '2026-11-05',
            'customer_name' => 'Hessa',
            'phone' => '+97455001003',
            'category' => 'Facial',
            'branch' => 'wakrah',
            'ticket_amount' => 1200,
            'created_by' => $this->admin->id,
        ]);
        AdLeadEntry::create([
            'date' => '2026-11-06',
            'customer_name' => 'Lulwa',
            'phone' => '+97455001004',
            'category' => 'Facial',
            'branch' => 'wakrah',
            'ticket_amount' => 1200,
            'created_by' => $this->admin->id,
        ]);
        AdLeadEntry::create([
            'date' => '2026-11-07',
            'customer_name' => 'Sara',
            'phone' => '+97455001005',
            'category' => 'Facial',
            'branch' => 'wakrah',
            'ticket_amount' => 1200,
            'created_by' => $this->admin->id,
        ]);
        AdLeadEntry::create([
            'date' => '2026-11-08',
            'customer_name' => 'Maha',
            'phone' => '+97455001006',
            'category' => 'Nails',
            'branch' => null,
            'ticket_amount' => 0,
            'created_by' => $this->admin->id,
        ]);

        $res = $this->actingAs($this->admin)->get(route('kpi.ads.index', [
            'tab' => 'analytics',
            'analytics_from' => '2026-11-01',
            'analytics_to' => '2026-11-17',
        ]));

        $res->assertOk()
            // Upload Screenshot remains strictly absent on Ads Analytics
            ->assertDontSee('Upload Screenshot')
            ->assertDontSee('id="adScreenshotImportModal"', false)
            // Verbose subtitles and redundant columns removed
            ->assertDontSee('Live performance breakdown computed straight from')
            ->assertDontSee('Rule-based recommendations')
            ->assertDontSee('Pick a date range and generate')
            ->assertDontSee('vs 20% Target')
            // Compact 3-column funnel strip + Profitability & Return panel + inline branch split + 7-column breakdown + slide-over drawers
            ->assertSee('Total Leads')
            ->assertSee('Booked Visits')
            ->assertSee('Overall Conversion')
            ->assertSee('Profitability & Return')
            ->assertSee('Ad Revenue')
            ->assertSee('All-In (+1,530 QAR Fee)')
            ->assertSee('Ad Spend Only')
            ->assertSee('Old Airport')
            ->assertSee('Al Wakrah')
            ->assertSee('49.3%')
            ->assertSee('50.7%')
            ->assertSee('Category Conversion Breakdown')
            ->assertSee('data-open-ads-drawer="adsInsightsDrawer"', false)
            ->assertSee('data-open-ads-drawer="adsSnapshotsDrawer"', false)
            ->assertSee('id="adsInsightsDrawer"', false)
            ->assertSee('id="adsSnapshotsDrawer"', false)
            ->assertSee('laleen-neon-comet', false)
            ->assertSee('neonCometGlide', false)
            ->assertDontSee('prefers-reduced-motion', false)
            ->assertSee('laleen-split-spark', false)
            ->assertSee('z-index: 9999', false)
            ->assertSee('+ Save Current Period Snapshot');

        // Verify the 4 KPI summary cards render underneath Category Conversion Breakdown at the bottom
        $analyticsHtml = $res->getContent();
        $breakdownPos = strpos($analyticsHtml, 'Category Conversion Breakdown');
        $kpiCardPos = strpos($analyticsHtml, 'Overall Conversion');
        $this->assertNotFalse($breakdownPos);
        $this->assertNotFalse($kpiCardPos);
        $this->assertGreaterThan($breakdownPos, $kpiCardPos, '4 KPI summary cards must render underneath Category Conversion Breakdown.');

        // Verify 1-click snapshot creation & deletion still work end-to-end
        $storeSnap = $this->actingAs($this->admin)->post(route('kpi.ads.store'), [
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-17',
        ]);
        $storeSnap->assertRedirect();
        $this->assertSame(1, KpiAdsConversionReport::count());

        $snapshot = KpiAdsConversionReport::firstOrFail();
        $this->actingAs($this->admin)->get(route('kpi.ads.show', $snapshot))->assertOk();

        $delSnap = $this->actingAs($this->admin)->delete(route('kpi.ads.destroy', $snapshot));
        $delSnap->assertRedirect();
        $this->assertSame(0, KpiAdsConversionReport::count());
    }

    public function test_gemini_503_and_timeout_fail_over_cleanly_and_snapshot_date_never_falls_back_to_today(): void
    {
        Storage::fake('local');
        Cache::forget(ExchangeRateService::CACHE_KEY_USD_RATES);
        config([
            'services.gemini.key' => 'test-gemini-failover-key',
            'services.gemini.vision_model' => 'gemini-2.5-flash',
            'services.openai.key' => null,
        ]);

        // Seed an older campaign row that previously had 2026-11-17 so we prove confirm syncs the whole workspace to 01-16 Nov
        AdCampaignMetric::mergeAndUpsert([
            'workspace' => 'salon',
            'region' => 'qa',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-17',
            'account_currency' => 'USD',
            'local_currency' => 'QAR',
            'fx_rate_to_local' => 3.6415,
        ], [
            'campaign_name' => 'Legacy Stale Row',
            'impressions' => 5000,
            'cpm' => 3.00,
        ], $this->admin->id);

        $seenModels = [];

        Http::fake(function (HttpClientRequest $req) use (&$seenModels) {
            $url = $req->url();
            if (str_contains($url, 'open.er-api.com')) {
                return Http::response(['rates' => ['USD' => 1.0, 'QAR' => 3.6415]], 200);
            }

            $body = $req->data();

            if (str_contains($url, 'models/gemini-2.5-flash:')) {
                $seenModels[] = 'gemini-2.5-flash';
                // Assert thinkingBudget = 0 IS sent on gemini-2.5-* models
                $this->assertSame(0, data_get($body, 'generationConfig.thinkingConfig.thinkingBudget'));

                return Http::response([
                    'error' => [
                        'code' => 503,
                        'message' => 'This model is currently experiencing high demand.',
                        'status' => 'UNAVAILABLE',
                    ],
                ], 503);
            }

            if (str_contains($url, 'models/gemini-2.0-flash:')) {
                $seenModels[] = 'gemini-2.0-flash';
                // Assert thinkingConfig is NEVER sent on gemini-2.0-* models
                $this->assertNull(data_get($body, 'generationConfig.thinkingConfig'));

                throw new ConnectionException('cURL error 28: Operation timed out after 20000 milliseconds');
            }

            if (str_contains($url, 'models/gemini-2.5-flash-lite:')) {
                $seenModels[] = 'gemini-2.5-flash-lite';
                $this->assertSame(0, data_get($body, 'generationConfig.thinkingConfig.thinkingBudget'));

                return Http::response([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    [
                                        'text' => json_encode([
                                            'account_currency' => 'USD',
                                            'date_from' => '1 Nov 2026',
                                            'date_to' => '16 Nov 2026',
                                            'campaigns' => [
                                                [
                                                    'campaign_name' => 'Frizzy, dry, ads',
                                                    'impressions' => 24866,
                                                    'cpm' => 5.85,
                                                    'link_clicks' => 201,
                                                    'cpc' => 0.72,
                                                    'ctr' => 0.81,
                                                ],
                                                [
                                                    'campaign_name' => '6 Services 99 QAR',
                                                    'impressions' => 30977,
                                                    'cpm' => 4.63,
                                                    'link_clicks' => 667,
                                                    'cpc' => 0.22,
                                                    'ctr' => 2.15,
                                                ],
                                            ],
                                        ]),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200);
            }

            return Http::response(['error' => 'Unexpected URL'], 500);
        });

        $extractRes = $this->actingAs($this->admin)->postJson(route('kpi.ad-metrics.extract'), [
            'screenshots' => [UploadedFile::fake()->image('meta-01-16-nov.png', 1440, 800)],
            'workspace' => 'salon',
            'region' => 'qa',
        ]);

        $extractRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('date_from', '2026-11-01')
            ->assertJsonPath('date_to', '2026-11-16')
            ->assertJsonPath('period_label', '01 Nov 2026 – 16 Nov 2026')
            ->assertJsonCount(2, 'campaigns');

        $this->assertSame(['gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-2.5-flash-lite'], $seenModels);

        // Confirm through Step 2 and verify all workspace rows + Snapshot badge reflect 01 Nov 2026 – 16 Nov 2026 (NEVER 17 Nov)
        $confirmRes = $this->actingAs($this->admin)->postJson(route('kpi.ad-metrics.confirm'), [
            'import_log_id' => $extractRes->json('import_log_id'),
            'workspace' => 'salon',
            'region' => 'qa',
            'date_from' => $extractRes->json('date_from'),
            'date_to' => $extractRes->json('date_to'),
            'account_currency' => 'USD',
            'local_currency' => 'QAR',
            'campaigns' => $extractRes->json('campaigns'),
        ]);
        $confirmRes->assertOk();

        foreach (AdCampaignMetric::where('workspace', 'salon')->get() as $row) {
            $this->assertSame('2026-11-01', $row->date_from->toDateString());
            $this->assertSame('2026-11-16', $row->date_to->toDateString());
        }

        $trackerPage = $this->actingAs($this->admin)->get(route('kpi.ads.index', ['tab' => 'metrics']));
        $trackerPage->assertOk()
            ->assertSee('01 Nov 2026 – 16 Nov 2026')
            ->assertDontSee('17 Nov 2026');
    }

    public function test_ads_analytics_aligns_to_ad_metrics_snapshot_dates_and_computes_marketing_cost_net_profit_roas_and_roi(): void
    {
        // 1. Empty Ad Metrics state -> zero division-by-zero errors when ad_spend_qar = 0
        $emptyRes = $this->actingAs($this->admin)->get(route('kpi.ads.index', ['tab' => 'analytics']));
        $emptyRes->assertOk()
            ->assertSee('Profitability & Return')
            ->assertSee('Meta Ad Spend:')
            ->assertSee('1,530')
            ->assertSee('Net Profit')
            ->assertSee('ROAS')
            ->assertSee('Net ROI');

        $emptyFin = $emptyRes->viewData('analyticsFinancials');
        $this->assertEquals(0.00, $emptyFin['ad_spend_qar']);
        $this->assertEquals(1530.00, $emptyFin['marketing_fee_qar']);
        $this->assertNull($emptyFin['roas']);
        $this->assertNull($emptyFin['roi_percent']);

        // 2. Seed Ad Metrics snapshot for 2026-11-01 to 2026-11-16 totaling exactly 1,836.63 QAR spend
        AdCampaignMetric::mergeAndUpsert([
            'workspace' => 'salon',
            'region' => 'qa',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-16',
            'account_currency' => 'QAR',
            'local_currency' => 'QAR',
            'fx_rate_to_local' => 1.0000,
        ], [
            'campaign_name' => 'Frizzy, dry, ads',
            'amount_spent' => 1000.00,
        ], $this->admin->id);

        AdCampaignMetric::mergeAndUpsert([
            'workspace' => 'salon',
            'region' => 'qa',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-16',
            'account_currency' => 'QAR',
            'local_currency' => 'QAR',
            'fx_rate_to_local' => 1.0000,
        ], [
            'campaign_name' => '6 Services 99 QAR',
            'amount_spent' => 836.63,
        ], $this->admin->id);

        // Seed 7,100.00 QAR verified revenue inside 01–16 Nov + 900.00 QAR on 17 Nov (outside snapshot)
        AdLeadEntry::create([
            'date' => '2026-11-05',
            'customer_name' => 'Fatima',
            'phone' => '+97455110001',
            'category' => 'Hair Treatment',
            'branch' => 'old_airport',
            'ticket_amount' => 3500.00,
            'created_by' => $this->admin->id,
        ]);
        AdLeadEntry::create([
            'date' => '2026-11-14',
            'customer_name' => 'Noora',
            'phone' => '+97455110002',
            'category' => 'Facial',
            'branch' => 'wakrah',
            'ticket_amount' => 3600.00,
            'created_by' => $this->admin->id,
        ]);
        AdLeadEntry::create([
            'date' => '2026-11-17',
            'customer_name' => 'Outside Snapshot Lead',
            'phone' => '+97455110003',
            'category' => 'Nails',
            'branch' => 'wakrah',
            'ticket_amount' => 900.00,
            'created_by' => $this->admin->id,
        ]);

        // Open Ads Analytics WITHOUT explicit ?analytics_from / ?analytics_to -> must auto-align to 01 Nov – 16 Nov 2026!
        $alignedRes = $this->actingAs($this->admin)->get(route('kpi.ads.index', ['tab' => 'analytics']));
        $alignedRes->assertOk();

        $this->assertSame('2026-11-01', $alignedRes->viewData('analyticsFrom')->format('Y-m-d'));
        $this->assertSame('2026-11-16', $alignedRes->viewData('analyticsTo')->format('Y-m-d'));

        $fin = $alignedRes->viewData('analyticsFinancials');
        $this->assertEquals(7100.00, $fin['ad_revenue_qar']);
        $this->assertEquals(1836.63, $fin['ad_spend_qar']);
        $this->assertEquals(1530.00, $fin['marketing_fee_qar']);
        $this->assertEquals(3366.63, $fin['total_marketing_cost_qar']);
        $this->assertEquals(3733.37, $fin['net_profit_qar']);
        $this->assertEquals(3.87, $fin['roas']);
        $this->assertEquals(110.9, $fin['roi_percent']);

        $alignedRes->assertSee('01 Nov 2026 – 16 Nov 2026')
            ->assertSee('7,100')
            ->assertSee('1,836.63')
            ->assertSee('1,530')
            ->assertSee('3,366.63')
            ->assertSee('+3,733.37')
            ->assertSee('3.87x')
            ->assertSee('+110.9%');
    }
}
