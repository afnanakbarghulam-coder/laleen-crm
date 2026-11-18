<?php

namespace Tests\Feature;

use App\Models\EcommerceMonthlyAd;
use App\Models\KpiMonthlyAd;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthlyAdsPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name'      => 'Afnan Owner',
            'email'     => 'afnanakbarghulam@gmail.com',
            'role'      => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_three_column_board_renders_planning_and_production_and_migrates_legacy_production_records_safely(): void
    {
        $month = now()->format('Y-m');

        // Insert a raw legacy "Production" row with legacy 3-column creatives to verify migration & zero-orphan grouping
        $legacyId = DB::table('monthly_ads')->insertGetId([
            'ad_name'               => 'Legacy Keratin Batch',
            'person_responsible'    => $this->admin->name,
            'offer'                 => 'Keratin Smooth 299 QAR',
            'plan_month'            => $month,
            'target_branch'         => 'Old Airport',
            'total_budget'          => 1200,
            'phase'                 => 'Production',
            'creative_1_name'       => 'Legacy Hook A',
            'creative_asset_link'   => 'https://www.instagram.com/reel/legacy-a',
            'creative_2_name'       => 'Legacy Hook B',
            'creative_asset_link_2' => 'not-a-url-folder-note',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        // Re-run the idempotent upgrade migration up() against the legacy Production row
        $migration = require database_path('migrations/2026_11_18_163000_upgrade_monthly_ads_three_column_and_dynamic_creatives.php');
        $migration->up();

        $migrated = KpiMonthlyAd::findOrFail($legacyId);
        $this->assertSame('Planning', $migrated->phase);
        $this->assertSame('Planning & Production', $migrated->phaseLabel());
        $this->assertCount(2, $migrated->resolvedCreatives());
        $this->assertSame('https://www.instagram.com/reel/legacy-a', $migrated->resolvedCreatives()[0]['reference_url']);

        // Also seed Testing & Scaling cards
        KpiMonthlyAd::create([
            'ad_name'            => 'Testing HydraFacial Ad',
            'person_responsible' => $this->admin->name,
            'plan_month'         => $month,
            'target_branch'      => 'Wakrah',
            'phase'              => 'Testing',
            'total_budget'       => 800,
        ]);
        KpiMonthlyAd::create([
            'ad_name'            => 'Scaling Bridal Package Ad',
            'person_responsible' => $this->admin->name,
            'plan_month'         => $month,
            'target_branch'      => 'All Branches',
            'phase'              => 'Scaling',
            'total_budget'       => 2000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('kpi.ads-plan.index'));
        $response->assertOk();

        // Exact 3 columns on board, zero standalone Production header, zero 3-Creative Batch subtitle, zero 1400px horizontal scroll
        $adsByPhase = $response->viewData('adsByPhase');
        $this->assertSame(['Planning', 'Testing', 'Scaling'], $adsByPhase->keys()->all());
        $this->assertCount(1, $adsByPhase['Planning']);
        $this->assertCount(1, $adsByPhase['Testing']);
        $this->assertCount(1, $adsByPhase['Scaling']);

        $response->assertSee('Planning &amp; Production', false);
        $response->assertSee('Testing');
        $response->assertSee('Scaling');
        $response->assertSee('Creative Batches &amp; Funnel Testing', false);
        $response->assertDontSee('3-Creative Batch');
        $response->assertDontSee('min-width: 1400px', false);
        $response->assertDontSee('<strong>Production</strong>', false);
        $response->assertSee('Legacy Keratin Batch');
    }

    public function test_creating_and_updating_ad_with_four_plus_creatives_specs_and_reference_links(): void
    {
        $month = now()->format('Y-m');

        // 1. Invalid reference_url is rejected cleanly with 422
        $this->actingAs($this->admin)
            ->postJson(route('kpi.ads-plan.store'), [
                'plan_month'         => $month,
                'ad_name'            => 'Invalid URL Test',
                'person_responsible' => $this->admin->name,
                'target_branch'      => 'All Branches',
                'creatives'          => [
                    [
                        'name'          => 'Hook 1',
                        'creative_type' => 'reel',
                        'media_type'    => 'video',
                        'size'          => '9:16',
                        'reference_url' => 'not_a_valid_url',
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['creatives.0.reference_url']);

        // 2. Store an ad with 5 creatives across Reel, Story, Post, Video, Image, all 4 aspect sizes, and reference URLs
        $storeResponse = $this->actingAs($this->admin)
            ->post(route('kpi.ads-plan.store'), [
                'plan_month'         => $month,
                'ad_name'            => 'November 5-Angle Rituals Push',
                'person_responsible' => $this->admin->name,
                'offer'              => '4-Service Luxury Combo — 180 QAR',
                'target_branch'      => 'All Branches',
                'total_budget'       => 3500,
                'offer_price'        => 180,
                'live_date'          => now()->addDays(5)->toDateString(),
                'creatives'          => [
                    [
                        'name'          => 'Angle 1 — ASMR Hair Spa',
                        'creative_type' => 'reel',
                        'media_type'    => 'video',
                        'size'          => '9:16',
                        'reference_url' => 'https://www.instagram.com/reel/C1RefLink',
                    ],
                    [
                        'name'          => 'Angle 2 — Doha Salon Tour',
                        'creative_type' => 'story',
                        'media_type'    => 'video',
                        'size'          => '9:16',
                        'reference_url' => 'https://www.tiktok.com/@laleen/video/123456',
                    ],
                    [
                        'name'          => 'Angle 3 — Price Breakdown Card',
                        'creative_type' => 'post',
                        'media_type'    => 'image',
                        'size'          => '4:5',
                        'reference_url' => '',
                    ],
                    [
                        'name'          => 'Angle 4 — Before & After Carousel',
                        'creative_type' => 'post',
                        'media_type'    => 'image',
                        'size'          => '1:1',
                        'reference_url' => 'https://drive.google.com/file/d/creative4',
                    ],
                    [
                        'name'          => 'Angle 5 — Widescreen YouTube Cut',
                        'creative_type' => 'reel',
                        'media_type'    => 'video',
                        'size'          => '16:9',
                        'reference_url' => 'https://www.facebook.com/ads/library/?id=998877',
                    ],
                ],
            ]);

        $storeResponse->assertRedirect(route('kpi.ads-plan.index'));
        $storeResponse->assertSessionHasNoErrors();

        $ad = KpiMonthlyAd::where('ad_name', 'November 5-Angle Rituals Push')->firstOrFail();
        $this->assertSame('Planning', $ad->phase);

        $resolved = $ad->resolvedCreatives();
        $this->assertCount(5, $resolved);
        $this->assertSame('reel', $resolved[0]['creative_type']);
        $this->assertSame('9:16', $resolved[0]['size']);
        $this->assertSame('post', $resolved[2]['creative_type']);
        $this->assertSame('4:5', $resolved[2]['size']);
        $this->assertNull($resolved[2]['reference_url']);
        $this->assertSame('1:1', $resolved[3]['size']);
        $this->assertSame('https://drive.google.com/file/d/creative4', $resolved[3]['reference_url']);
        $this->assertSame('16:9', $resolved[4]['size']);

        // Board renders all 5 creatives, specs, and Ref ↗ links cleanly
        $board = $this->actingAs($this->admin)->get(route('kpi.ads-plan.index'));
        $board->assertOk();
        $board->assertSee('5 Creatives');
        $board->assertSee('C1 &middot; Reel &middot; Video 9:16', false);
        $board->assertSee('C2 &middot; Story &middot; Video 9:16', false);
        $board->assertSee('C3 &middot; Post &middot; Image 4:5', false);
        $board->assertSee('C4 &middot; Post &middot; Image 1:1', false);
        $board->assertSee('C5 &middot; Reel &middot; Video 16:9', false);
        $board->assertSee('Angle 4 — Before &amp; After Carousel', false);
        $board->assertSee('href="https://drive.google.com/file/d/creative4"', false);
        $board->assertSee('Ref ↗');

        // 3. Update the ad with Creative 4 selected as Winning Creative + 4 creatives
        $this->actingAs($this->admin)
            ->put(route('kpi.ads-plan.update', $ad), [
                'plan_month'         => $month,
                'ad_name'            => 'November 5-Angle Rituals Push (Updated)',
                'person_responsible' => $this->admin->name,
                'target_branch'      => 'Old Airport',
                'total_budget'       => 4000,
                'winning_creative'   => 'Creative 4',
                'cost_per_message'   => 6.50,
                'sales_verdict'      => 'High Messages + Sales (Scale)',
                'creatives'          => array_slice($ad->creatives, 0, 4),
            ])
            ->assertRedirect(route('kpi.ads-plan.index'))
            ->assertSessionHasNoErrors();

        $ad->refresh();
        $this->assertSame('November 5-Angle Rituals Push (Updated)', $ad->ad_name);
        $this->assertCount(4, $ad->resolvedCreatives());
        $this->assertSame('Creative 4', $ad->winning_creative);
        $this->assertSame('Angle 4 — Before & After Carousel', $ad->winningCreativeDetails()['name']);
    }

    public function test_gate_progression_moves_smoothly_from_planning_and_production_to_testing_scaling_and_killed(): void
    {
        $month = now()->format('Y-m');

        $ad = KpiMonthlyAd::create([
            'ad_name'            => 'Gate Flow Campaign',
            'person_responsible' => $this->admin->name,
            'plan_month'         => $month,
            'target_branch'      => 'All Branches',
            'total_budget'       => 1500,
            'phase'              => 'Planning',
            'creatives'          => [
                [
                    'name'          => 'Hook 1 — Salon Reveal',
                    'creative_type' => 'reel',
                    'media_type'    => 'video',
                    'size'          => '9:16',
                    'reference_url' => 'https://www.instagram.com/reel/gate1',
                ],
                [
                    'name'          => 'Hook 2 — Offer Post',
                    'creative_type' => 'post',
                    'media_type'    => 'image',
                    'size'          => '4:5',
                    'reference_url' => null,
                ],
            ],
        ]);

        // 1. Planning & Production -> Testing
        $this->actingAs($this->admin)
            ->patch(route('kpi.ads-plan.complete-production', $ad))
            ->assertRedirect(route('kpi.ads-plan.index'))
            ->assertSessionHas('success');

        $ad->refresh();
        $this->assertSame('Testing', $ad->phase);
        $this->assertNotNull($ad->testing_started_at);
        $this->assertNotNull($ad->testing_ends_at);

        // 2. Testing -> Scaling
        $this->actingAs($this->admin)
            ->patch(route('kpi.ads-plan.scale', $ad))
            ->assertRedirect(route('kpi.ads-plan.index'))
            ->assertSessionHas('success');

        $ad->refresh();
        $this->assertSame('Scaling', $ad->phase);
        $this->assertNotNull($ad->verdict_at);

        // 3. Scaling -> Killed (Graveyard)
        $this->actingAs($this->admin)
            ->patch(route('kpi.ads-plan.kill', $ad))
            ->assertRedirect(route('kpi.ads-plan.index'))
            ->assertSessionHas('success');

        $ad->refresh();
        $this->assertSame('Killed', $ad->phase);

        $board = $this->actingAs($this->admin)->get(route('kpi.ads-plan.index'));
        $board->assertOk();
        $this->assertCount(1, $board->viewData('killedAds'));
        $board->assertSee('Killed Tests Graveyard (1)');

        // 4. Verify E-Commerce Monthly Ads Plan also supports 4+ creatives & 3-column gate flow
        $this->actingAs($this->admin)
            ->post(route('ecommerce.marketing.ads-plan.store'), [
                'region'             => 'qa',
                'plan_month'         => $month,
                'ad_name'            => 'Qatar Hair Oil 4-Creative Batch',
                'person_responsible' => $this->admin->name,
                'target_branch'      => 'All Branches',
                'total_budget'       => 900,
                'creatives'          => [
                    ['name' => 'C1 Hook', 'creative_type' => 'reel',  'media_type' => 'video', 'size' => '9:16', 'reference_url' => 'https://www.instagram.com/reel/qa1'],
                    ['name' => 'C2 Hook', 'creative_type' => 'story', 'media_type' => 'video', 'size' => '9:16', 'reference_url' => null],
                    ['name' => 'C3 Hook', 'creative_type' => 'post',  'media_type' => 'image', 'size' => '4:5',  'reference_url' => null],
                    ['name' => 'C4 Hook', 'creative_type' => 'post',  'media_type' => 'image', 'size' => '1:1',  'reference_url' => 'https://www.instagram.com/p/qa4'],
                ],
            ])
            ->assertRedirect();

        $ecomAd = EcommerceMonthlyAd::where('ad_name', 'Qatar Hair Oil 4-Creative Batch')->firstOrFail();
        $this->assertCount(4, $ecomAd->resolvedCreatives());

        $this->actingAs($this->admin)
            ->patch(route('ecommerce.marketing.ads-plan.complete-production', $ecomAd))
            ->assertRedirect();
        $this->assertSame('Testing', $ecomAd->fresh()->phase);

        $this->actingAs($this->admin)
            ->patch(route('ecommerce.marketing.ads-plan.scale', $ecomAd))
            ->assertRedirect();
        $this->assertSame('Scaling', $ecomAd->fresh()->phase);
    }
}
