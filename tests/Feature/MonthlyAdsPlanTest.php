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

    public function test_legacy_zero_creatives_cards_with_long_descriptions_render_safely_and_support_edit_and_delete(): void
    {
        $month = now()->format('Y-m');
        $longUnbrokenOffer = str_repeat('SuperLongUnbrokenKeratinOfferString99QAR_', 8)
            . " plus Sana's \"Bridal & Glow\" multi-word promotional package across Doha Old Airport and Al Wakrah branches!";

        // Seed 0-creative legacy cards across Planning, Testing, Scaling, and Killed
        $planningZero = KpiMonthlyAd::create([
            'ad_name'            => "Legacy Zero-Creative Card — Sana's SuperLongUnbrokenCampaignTitle_" . str_repeat('X', 65),
            'person_responsible' => 'Legacy Staff Name',
            'offer'              => $longUnbrokenOffer,
            'plan_month'         => $month,
            'target_branch'      => 'All Branches',
            'total_budget'       => 500,
            'phase'              => 'Planning',
            'creatives'          => null,
        ]);

        $testingZero = KpiMonthlyAd::create([
            'ad_name'            => 'Zero Creative Testing Card',
            'person_responsible' => $this->admin->name,
            'offer'              => $longUnbrokenOffer,
            'plan_month'         => $month,
            'target_branch'      => 'Old Airport',
            'phase'              => 'Testing',
            'creatives'          => [],
        ]);

        $scalingZero = KpiMonthlyAd::create([
            'ad_name'            => 'Zero Creative Scaling Card',
            'person_responsible' => $this->admin->name,
            'plan_month'         => $month,
            'target_branch'      => 'Wakrah',
            'phase'              => 'Scaling',
            'creatives'          => null,
        ]);

        $killedZero = KpiMonthlyAd::create([
            'ad_name'            => 'Zero Creative Killed Graveyard Card',
            'person_responsible' => $this->admin->name,
            'plan_month'         => $month,
            'target_branch'      => 'All Branches',
            'phase'              => 'Killed',
            'testing_notes'      => str_repeat('LongUnbrokenGraveyardNote_', 10),
            'creatives'          => null,
        ]);

        // 1. Render board: verify flex overflow classes, pinned action container, 0 Creatives badge, and valid JSON data-ad
        $board = $this->actingAs($this->admin)->get(route('kpi.ads-plan.index'));
        $board->assertOk()
            ->assertSee('0 Creatives')
            ->assertSee('min-w-0 flex-1', false)
            ->assertSee('break-words whitespace-normal', false)
            ->assertSee('shrink-0 relative z-10 flex items-center gap-1.5', false)
            ->assertSee('p-1.5 cursor-pointer', false)
            ->assertSee(route('kpi.ads-plan.destroy', $planningZero), false)
            ->assertSee(route('kpi.ads-plan.destroy', $killedZero), false);

        // 2. Edit (PUT) the legacy 0-creatives card submitting the default untouched blank Creative 1 row -> saves cleanly as 0 Creatives
        $this->actingAs($this->admin)
            ->put(route('kpi.ads-plan.update', $planningZero), [
                'plan_month'         => $month,
                'ad_name'            => 'Edited Legacy Zero-Creative Card',
                'person_responsible' => 'Legacy Staff Name',
                'offer'              => 'Updated clean offer summary',
                'target_branch'      => 'Old Airport',
                'total_budget'       => 750,
                'creatives'          => [
                    [
                        'name'          => '',
                        'creative_type' => 'reel',
                        'media_type'    => 'video',
                        'size'          => '9:16',
                        'reference_url' => '',
                    ],
                ],
            ])
            ->assertRedirect(route('kpi.ads-plan.index'))
            ->assertSessionHasNoErrors();

        $planningZero->refresh();
        $this->assertSame('Edited Legacy Zero-Creative Card', $planningZero->ad_name);
        $this->assertSame('Updated clean offer summary', $planningZero->offer);
        $this->assertCount(0, $planningZero->resolvedCreatives());

        // 3. Edit via PATCH adding 1 creative onto the legacy 0-creatives card
        $this->actingAs($this->admin)
            ->patch(route('kpi.ads-plan.update', $planningZero), [
                'plan_month'         => $month,
                'ad_name'            => 'Edited Legacy Card With 1 Creative',
                'person_responsible' => $this->admin->name,
                'target_branch'      => 'Old Airport',
                'total_budget'       => 900,
                'creatives'          => [
                    [
                        'name'          => 'Fresh Hook 1',
                        'creative_type' => 'reel',
                        'media_type'    => 'video',
                        'size'          => '9:16',
                        'reference_url' => 'https://www.instagram.com/reel/fresh1',
                    ],
                ],
            ])
            ->assertRedirect(route('kpi.ads-plan.index'))
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $planningZero->fresh()->resolvedCreatives());

        // 4. Delete cards cleanly across Planning & Production, Testing, Scaling, and Killed Tests Graveyard
        foreach ([$planningZero, $testingZero, $scalingZero, $killedZero] as $card) {
            $this->actingAs($this->admin)
                ->delete(route('kpi.ads-plan.destroy', $card))
                ->assertRedirect(route('kpi.ads-plan.index'));

            $this->assertDatabaseMissing('monthly_ads', ['id' => $card->id]);
        }
    }

    public function test_streamlined_planning_form_removes_five_legacy_fields_and_testing_stage_saves_and_renders_four_evaluation_metrics(): void
    {
        $month = now()->format('Y-m');

        // 1. Verify the 5 removed legacy labels no longer render anywhere in Monthly Ads Plan
        $initialBoard = $this->actingAs($this->admin)->get(route('kpi.ads-plan.index'));
        $initialBoard->assertOk()
            ->assertSee('Creative Deadline')
            ->assertSee('Testing Metrics')
            ->assertSee('Aggressive Audience Reaction?')
            ->assertSee('CPM (QAR)')
            ->assertSee('CTR (%)')
            ->assertSee('Lead Conversion Status')
            ->assertDontSee('Test Ends')
            ->assertDontSee('Winning Creative')
            ->assertDontSee('Cost / WhatsApp Msg (QAR)')
            ->assertDontSee('Funnel Verdict')
            ->assertDontSee('Audience &amp; Funnel Notes', false);

        // 2. Create and update an ad in Planning & Production cleanly without any testing fields (even when Creative Deadline is set without Live Date)
        $this->actingAs($this->admin)
            ->post(route('kpi.ads-plan.store'), [
                'plan_month'              => $month,
                'ad_name'                 => 'Clean Planning Offer Batch',
                'person_responsible'      => $this->admin->name,
                'offer'                   => '7 Services 99 QAR',
                'target_branch'           => 'All Branches',
                'total_budget'            => 1200,
                'final_creative_deadline' => now()->addDays(2)->toDateString(),
                'creatives'               => [
                    [
                        'name'          => 'Hook 1 — 99 QAR Reveal',
                        'creative_type' => 'reel',
                        'media_type'    => 'video',
                        'size'          => '9:16',
                        'reference_url' => 'https://www.instagram.com/reel/plan99',
                    ],
                ],
            ])
            ->assertRedirect(route('kpi.ads-plan.index'))
            ->assertSessionHasNoErrors();

        $ad = KpiMonthlyAd::where('ad_name', 'Clean Planning Offer Batch')->firstOrFail();
        $this->assertSame('Planning', $ad->phase);

        $this->actingAs($this->admin)
            ->put(route('kpi.ads-plan.update', $ad), [
                'plan_month'              => $month,
                'ad_name'                 => 'Clean Planning Offer Batch (Edited)',
                'person_responsible'      => $this->admin->name,
                'offer'                   => '7 Services 99 QAR — Updated',
                'target_branch'           => 'Old Airport',
                'total_budget'            => 1400,
                'final_creative_deadline' => now()->addDays(3)->toDateString(),
                'creatives'               => $ad->creatives,
            ])
            ->assertRedirect(route('kpi.ads-plan.index'))
            ->assertSessionHasNoErrors();

        // 3. Move to Testing -> initially renders dashes ('—') for the 4 testing metrics on the card
        $this->actingAs($this->admin)
            ->patch(route('kpi.ads-plan.complete-production', $ad))
            ->assertRedirect(route('kpi.ads-plan.index'));

        $unfilledBoard = $this->actingAs($this->admin)->get(route('kpi.ads-plan.index'));
        $unfilledBoard->assertOk()
            ->assertSee('Reaction: <strong>—</strong>', false)
            ->assertSee('CPM: <strong>—</strong>', false)
            ->assertSee('CTR: <strong>—</strong>', false)
            ->assertSee('Leads: <strong>—</strong>', false);

        // 4. Reject invalid testing metric inputs with 422
        $this->actingAs($this->admin)
            ->putJson(route('kpi.ads-plan.update', $ad), [
                'plan_month'             => $month,
                'ad_name'                => 'Clean Planning Offer Batch (Edited)',
                'person_responsible'     => $this->admin->name,
                'target_branch'          => 'Old Airport',
                'aggressive_reaction'    => 'maybe',
                'cpm'                    => -5,
                'ctr'                    => 125,
                'lead_conversion_status' => 'invalid',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aggressive_reaction', 'cpm', 'ctr', 'lead_conversion_status']);

        // 5. Save valid 4 testing metrics on the Testing card -> persists & displays on Testing and Scaling cards
        $this->actingAs($this->admin)
            ->put(route('kpi.ads-plan.update', $ad), [
                'plan_month'             => $month,
                'ad_name'                => 'Clean Planning Offer Batch (Edited)',
                'person_responsible'     => $this->admin->name,
                'target_branch'          => 'Old Airport',
                'total_budget'           => 1400,
                'creatives'              => $ad->fresh()->creatives,
                'aggressive_reaction'    => 'yes',
                'cpm'                    => 42,
                'ctr'                    => 2.35,
                'lead_conversion_status' => 'yes',
            ])
            ->assertRedirect(route('kpi.ads-plan.index'))
            ->assertSessionHasNoErrors();

        $ad->refresh();
        $this->assertSame('yes', $ad->aggressive_reaction);
        $this->assertSame('42.00', (string) $ad->cpm);
        $this->assertSame('2.35', (string) $ad->ctr);
        $this->assertSame('yes', $ad->lead_conversion_status);

        $testingBoard = $this->actingAs($this->admin)->get(route('kpi.ads-plan.index'));
        $testingBoard->assertOk()
            ->assertSee('Reaction: <strong>Yes</strong>', false)
            ->assertSee('CPM: <strong>42 QAR</strong>', false)
            ->assertSee('CTR: <strong>2.35%</strong>', false)
            ->assertSee('Leads: <strong>Yes</strong>', false);

        // Move to Scaling -> 4 metrics remain visible on the Scaling card
        $this->actingAs($this->admin)
            ->patch(route('kpi.ads-plan.scale', $ad))
            ->assertRedirect(route('kpi.ads-plan.index'));

        $scalingBoard = $this->actingAs($this->admin)->get(route('kpi.ads-plan.index'));
        $scalingBoard->assertOk()
            ->assertSee('Reaction: <strong>Yes</strong>', false)
            ->assertSee('CPM: <strong>42 QAR</strong>', false)
            ->assertSee('CTR: <strong>2.35%</strong>', false)
            ->assertSee('Leads: <strong>Yes</strong>', false);
    }
}
