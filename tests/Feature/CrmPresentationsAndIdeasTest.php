<?php

namespace Tests\Feature;

use App\Models\CrmIdea;
use App\Models\CrmIdeaVote;
use App\Models\CrmPresentation;
use App\Models\OpsActionItem;
use App\Models\Staff;
use App\Models\StaffContract;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CrmPresentationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmPresentationsAndIdeasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $agent;
    private User $secondAgent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Afnan Owner',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'name' => 'Rhea Wakrah',
            'email' => 'rhea@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('manager'),
        ]);

        $this->agent = User::factory()->create([
            'name' => 'Zoya Chat',
            'email' => 'zoya@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('agent'),
        ]);

        $this->secondAgent = User::factory()->create([
            'name' => 'Areeba Chat',
            'email' => 'areeba@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
            'permissions' => User::defaultPermissionsForRole('agent'),
        ]);
    }

    public function test_crm_guides_renders_presentations_and_ideas_tabs_with_preseeded_12_slide_giveaway_playbook(): void
    {
        // Re-running seeder is idempotent and never duplicates the pinned deck
        $this->seed(CrmPresentationSeeder::class);
        $this->assertSame(1, CrmPresentation::where('slug', CrmPresentationSeeder::GIVEAWAY_SLUG)->count());

        $playbook = CrmPresentation::where('slug', CrmPresentationSeeder::GIVEAWAY_SLUG)->firstOrFail();
        $this->assertTrue($playbook->is_pinned);
        $this->assertSame('Growth Playbook', $playbook->category);
        $this->assertSame(
            'Laleen Beauty Salon — 1,000 QAR VIP Makeover Giveaway Plan (Old Airport & Al Wakrah)',
            $playbook->title
        );
        $this->assertCount(12, $playbook->slides);

        // Verify all 6 consulting layouts appear across the 12 slides in the exact specified sequence
        $slideLayouts = array_map(fn (array $s) => $s['layout'] ?? '', $playbook->slides);
        foreach (array_keys(CrmPresentation::LAYOUTS) as $expectedLayout) {
            $this->assertContains($expectedLayout, $slideLayouts, "Expected layout [{$expectedLayout}] in seeded 12-slide playbook.");
        }
        $this->assertSame(
            ['cover', 'big_metric', 'timeline', 'comparison', 'cards', 'big_metric', 'cards', 'cards', 'timeline', 'comparison', 'table', 'cover'],
            $slideLayouts
        );

        // Verify key content across summary and all 12 slides (including Contact Number + Email -> Auto-CRM Sync)
        $this->assertStringContainsString('Contact Number & Email', (string) $playbook->summary);
        $this->assertStringContainsString('CRM phone & email database', (string) $playbook->summary);

        $slidesJson = json_encode($playbook->slides, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('1,000 QAR VIP Makeover Giveaway Plan', $slidesJson);
        $this->assertStringContainsString('1,000 QAR', $slidesJson);
        $this->assertStringContainsString('500 QAR', $slidesJson);
        $this->assertStringContainsString('100 QAR', $slidesJson);
        $this->assertStringContainsString('10,000 QAR', $slidesJson);
        $this->assertStringContainsString('Auto-Saved to CRM', $slidesJson);
        $this->assertStringContainsString('Contact Number, Email & Branch', $slidesJson);
        $this->assertStringContainsString('Automatic CRM Database Sync', $slidesJson);
        $this->assertStringContainsString('Ads, Form-to-CRM Sync & Auto-DM', $slidesJson);
        $this->assertStringContainsString('2x Free Makeover', $slidesJson);
        $this->assertStringContainsString('More than one person can win 2nd Prize', $slidesJson);
        $this->assertStringContainsString('Zoya & Areeba', $slidesJson);
        $this->assertStringContainsString('Ahana & Rhea', $slidesJson);
        $this->assertStringContainsString('Arbab', $slidesJson);
        $this->assertStringNotContainsString('Faye', $slidesJson);

        // Staff and Admin can render /guide, /guide/presentations, and /guide/ideas with 200 OK
        $guideResp = $this->actingAs($this->agent)->get(route('guide.index'));
        $guideResp->assertOk();
        $guideResp->assertSee('Role &amp; SOP Guides', false);
        $guideResp->assertSee('Presentations');
        $guideResp->assertSee('Brainstorm &amp; Ideas', false);
        $guideResp->assertSee('1,000 QAR VIP Makeover Giveaway Plan');
        $guideResp->assertSee('12 Slides');
        $guideResp->assertSee('Present Deck');
        $guideResp->assertDontSee('Faye');

        // Admin/Manager view renders '+ New Presentation' (no '+ +'), 'Paste Deck JSON', and minimalistic outside cards
        $adminPresResp = $this->actingAs($this->admin)->get(route('guide.presentations.index'));
        $adminPresResp->assertOk();
        $adminPresResp->assertSee('+ New Presentation');
        $adminPresResp->assertDontSee('+ + New Presentation');
        $adminPresResp->assertSee('Paste Deck JSON (Instant Import)');
        $adminPresResp->assertDontSee('data-slide-layout=', false);
        $adminPresResp->assertDontSee('for 16:9 consulting layouts');
        $adminPresResp->assertDontSee('Arrow keys (&larr; / &rarr;) navigate slides', false);

        $fullHtml = (string) $adminPresResp->getContent();
        $this->assertSame(1, preg_match('/id="gdPresentationsGrid".*?<\/article>\s*<\/div>/s', $fullHtml, $gridMatch));
        $outsideGridHtml = $gridMatch[0];
        $this->assertStringContainsString('Pinned', $outsideGridHtml);
        $this->assertStringContainsString('Growth Playbook', $outsideGridHtml);
        $this->assertStringContainsString('12 Slides', $outsideGridHtml);
        $this->assertStringContainsString('Present Deck', $outsideGridHtml);
        $this->assertStringNotContainsString('Big Metric', $outsideGridHtml);
        $this->assertStringNotContainsString('Big metric', $outsideGridHtml);
        $this->assertStringNotContainsString('Comparison', $outsideGridHtml);
        $this->assertStringNotContainsString('Timeline', $outsideGridHtml);
        $this->assertStringNotContainsString('Cards', $outsideGridHtml);
        $this->assertStringNotContainsString('Table', $outsideGridHtml);
        $this->assertStringNotContainsString('gd-track-step', $outsideGridHtml);

        $presResp = $this->actingAs($this->agent)->get(route('guide.presentations.index'));
        $presResp->assertOk();
        $presResp->assertSee('Laleen Beauty Salon — 1,000 QAR VIP Makeover Giveaway Plan (Old Airport & Al Wakrah)');

        $ideasResp = $this->actingAs($this->agent)->get(route('guide.ideas.index'));
        $ideasResp->assertOk();
        $ideasResp->assertSee('+ Submit Idea');
    }

    public function test_editing_existing_presentation_updates_slides_table_rows_and_all_six_layout_types(): void
    {
        $deck = CrmPresentation::create([
            'title' => 'Initial Draft Playbook',
            'category' => 'Branch SOP',
            'summary' => 'Draft summary',
            'is_pinned' => false,
            'slides' => CrmPresentation::sanitizeSlides([
                ['layout' => 'cover', 'title' => 'Draft Slide 1', 'subtitle' => 'Draft subtitle'],
            ]),
            'created_by' => $this->admin->id,
        ]);

        $updateResp = $this->actingAs($this->manager)->put(route('guide.presentations.update', $deck), [
            'title' => 'Updated Al Wakrah VIP Consultation Deck',
            'category' => 'Laleen Rituals',
            'summary' => 'Chair-side porosity diagnostic and retail ritual close.',
            'is_pinned' => '1',
            'slides' => [
                [
                    'layout' => 'cover',
                    'eyebrow' => 'LALEEN RITUALS · AL WAKRAH',
                    'title' => 'VIP Hair Ritual Consultation',
                    'subtitle' => 'Elevating chair upgrades with botanical home-care kits.',
                    'highlight_metric' => 'Al Wakrah & Old Airport',
                ],
                [
                    'layout' => 'big_metric',
                    'eyebrow' => 'AVERAGE TICKET LIFT',
                    'title' => 'Target Ritual Bundle Ticket',
                    'highlight_metric' => '890 QAR',
                    'subtitle' => '3 scheduled gloss rituals plus full-size botanical serum.',
                ],
                [
                    'layout' => 'timeline',
                    'eyebrow' => 'CONSULTATION FLOW',
                    'title' => '3-Step Chair Sequence',
                    'table_rows_text' => "Step 1 | Diagnostic | 2-min strand test\nStep 2 | Ritual Match | Show Tier 2 mask\nStep 3 | Home Lock | Pair take-home serum",
                ],
                [
                    'layout' => 'comparison',
                    'eyebrow' => 'OFFER CONTRAST',
                    'title' => 'Single Session vs. Ritual Pass',
                    'table_rows_text' => "Single Session | 350 QAR | Standalone treatment only\n3-Visit Ritual Pass | 890 QAR | 3 visits + full-size serum",
                ],
                [
                    'layout' => 'cards',
                    'eyebrow' => 'FLOOR CHECKLIST',
                    'title' => 'Three Non-Negotiable Touchpoints',
                    'table_rows_text' => "01 | Tray Display | Keep serum bottle on station\n02 | Price Card | Present 2-option menu card\n03 | Refill Date | Lock 4-week slot at desk",
                ],
                [
                    'layout' => 'table',
                    'eyebrow' => 'SCOREBOARD',
                    'title' => 'Branch Ownership Matrix',
                    'table_rows_text' => "Owner | Role | Target KPI\nRhea | Wakrah Floor Lead | 35% ritual attachment\nAhana | Old Airport Lead | 85% 30-day rebook",
                    'footer_note' => 'Reviewed every Monday in staff KPI huddle.',
                ],
            ],
        ]);

        $updateResp->assertRedirect(route('guide.index', ['section' => 'presentations']));

        $deck->refresh();
        $this->assertSame('Updated Al Wakrah VIP Consultation Deck', $deck->title);
        $this->assertSame('Laleen Rituals', $deck->category);
        $this->assertTrue($deck->is_pinned);
        $this->assertCount(6, $deck->slides);
        $this->assertSame(
            ['cover', 'big_metric', 'timeline', 'comparison', 'cards', 'table'],
            array_column($deck->slides, 'layout')
        );
        $this->assertSame('890 QAR', $deck->slides[1]['highlight_metric']);
        $this->assertCount(3, $deck->slides[2]['table_rows']);
        $this->assertSame(['Owner', 'Role', 'Target KPI'], $deck->slides[5]['headers']);
        $this->assertCount(2, $deck->slides[5]['rows']);

        // Confirm updated presentation renders 200 OK on Presentations page
        $this->actingAs($this->manager)
            ->get(route('guide.presentations.index'))
            ->assertOk()
            ->assertSee('Updated Al Wakrah VIP Consultation Deck');
    }

    public function test_paste_deck_json_instant_import_creates_valid_presentation_and_returns_422_on_invalid_json(): void
    {
        // 1. Non-admin / non-manager role receives 403 Forbidden
        $this->actingAs($this->agent)->postJson(route('guide.presentations.import'), [
            'deck_json' => '{"title":"Blocked","category":"Branch SOP","slides":[{"layout":"cover","title":"S1"}]}',
        ])->assertForbidden();

        // 2. Valid Deck JSON (even wrapped in ```json fences) creates a CrmPresentation cleanly
        $validPayload = [
            'title' => 'Ramadan Bridal Transformation Playbook',
            'category' => 'Marketing Strategy',
            'summary' => '14-day pre-Eid VSL & deposit funnel across Old Airport and Al Wakrah.',
            'is_pinned' => true,
            'slides' => [
                [
                    'layout' => 'cover',
                    'eyebrow' => 'RAMADAN CAMPAIGN',
                    'title' => 'Ramadan Bridal Transformation Playbook',
                    'subtitle' => 'Pre-Eid chair utilization and 200 QAR WhatsApp deposit locks.',
                    'highlight_metric' => 'Old Airport & Al Wakrah',
                ],
                [
                    'layout' => 'big_metric',
                    'eyebrow' => 'BRIDAL PACKAGE ANCHOR',
                    'title' => 'Signature Bridal Glow Package',
                    'highlight_metric' => '1,850 QAR',
                    'subtitle' => 'Combines hair gloss, hydra-facial, and luxury mani-pedi.',
                ],
                [
                    'layout' => 'timeline',
                    'eyebrow' => 'LAUNCH SEQUENCE',
                    'title' => '14-Day Pre-Eid Rollout',
                    'table_rows' => [
                        ['Day 1', 'VIP Blast', 'Broadcast early-bird slots to top 500 CRM guests.'],
                        ['Days 2–10', 'Reels VSL', 'Run transformation Reels into WhatsApp deposit link.'],
                        ['Days 11–14', 'Peak Lock', 'Fill final evening chairs with 200 QAR deposits.'],
                    ],
                ],
                [
                    'layout' => 'table',
                    'eyebrow' => 'OWNERSHIP',
                    'title' => 'Campaign KPIs',
                    'headers' => ['Owner', 'Focus', 'Target'],
                    'rows' => [
                        ['Ayan', 'Meta & TikTok VSL', '< 18 QAR CPL'],
                        ['Zoya & Areeba', 'WhatsApp Deposits', '< 5 Min SLA'],
                    ],
                ],
            ],
        ];

        $fencedJson = "```json\n" . json_encode($validPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```";

        $importResp = $this->actingAs($this->admin)->post(route('guide.presentations.import'), [
            'deck_json' => $fencedJson,
        ]);

        $importResp->assertRedirect(route('guide.index', ['section' => 'presentations']));

        $imported = CrmPresentation::where('title', 'Ramadan Bridal Transformation Playbook')->firstOrFail();
        $this->assertSame('Marketing Strategy', $imported->category);
        $this->assertTrue($imported->is_pinned);
        $this->assertCount(4, $imported->slides);
        $this->assertSame(['cover', 'big_metric', 'timeline', 'table'], array_column($imported->slides, 'layout'));
        $this->assertSame(['Owner', 'Focus', 'Target'], $imported->slides[3]['headers']);

        // 3. Malformed JSON returns 422 validation error on deck_json (never 500)
        $this->actingAs($this->admin)->postJson(route('guide.presentations.import'), [
            'deck_json' => '{"title": "Broken JSON", "slides": [',
        ])->assertStatus(422)->assertJsonValidationErrors(['deck_json']);

        // 4. Schema-invalid JSON (unsupported layout or missing slides) returns 422 validation error
        $this->actingAs($this->admin)->postJson(route('guide.presentations.import'), [
            'deck_json' => json_encode([
                'title' => 'Invalid Layout Deck',
                'category' => 'Growth Playbook',
                'slides' => [
                    ['layout' => 'pie_chart_3d', 'title' => 'Unsupported Layout'],
                ],
            ]),
        ])->assertStatus(422)->assertJsonValidationErrors(['deck_json']);

        // 5. Confidential salary/PIN keys in JSON are strictly rejected with 422
        $this->actingAs($this->admin)->postJson(route('guide.presentations.import'), [
            'deck_json' => json_encode([
                'title' => 'Leaky Deck',
                'category' => 'Branch SOP',
                'base_salary' => 9000,
                'slides' => [
                    ['layout' => 'cover', 'title' => 'Slide 1'],
                ],
            ]),
        ])->assertStatus(422)->assertJsonValidationErrors(['deck_json']);
    }

    public function test_staff_can_submit_idea_toggle_idempotent_upvote_post_comments_and_respect_creator_edit_rules(): void
    {
        // 1. Staff member submits a new idea
        $storeResp = $this->actingAs($this->agent)->post(route('guide.ideas.store'), [
            'title' => 'VIP Comment Auto-DM Transformation Hook <script>alert(1)</script>',
            'category' => 'Reels Hook',
            'description' => 'Show 3-second before/after hair gloss reveal and ask viewers to comment VIP.',
        ]);

        $storeResp->assertRedirect(route('guide.index', ['section' => 'ideas']));

        $idea = CrmIdea::firstOrFail();
        $this->assertSame('VIP Comment Auto-DM Transformation Hook alert(1)', $idea->title);
        $this->assertSame('Reels Hook', $idea->category);
        $this->assertSame(CrmIdea::STATUS_NEW, $idea->status);
        $this->assertSame(0, $idea->upvotes_count);
        $this->assertSame($this->agent->id, $idea->created_by);

        // 2. Toggle upvote (1st click = 1 vote, 2nd click by same user removes vote -> 0, then 2 distinct users -> 2)
        $this->actingAs($this->agent)->post(route('guide.ideas.vote', $idea))->assertRedirect();
        $this->assertSame(1, $idea->fresh()->upvotes_count);
        $this->assertSame(1, CrmIdeaVote::where('idea_id', $idea->id)->where('user_id', $this->agent->id)->count());

        $this->actingAs($this->agent)->post(route('guide.ideas.vote', $idea))->assertRedirect();
        $this->assertSame(0, $idea->fresh()->upvotes_count);

        $this->actingAs($this->agent)->postJson(route('guide.ideas.vote', $idea))
            ->assertOk()
            ->assertJsonPath('voted', true)
            ->assertJsonPath('upvotes_count', 1);

        $this->actingAs($this->secondAgent)->postJson(route('guide.ideas.vote', $idea))
            ->assertOk()
            ->assertJsonPath('voted', true)
            ->assertJsonPath('upvotes_count', 2);

        // 3. Post a comment
        $this->actingAs($this->secondAgent)->post(route('guide.ideas.comments.store', $idea), [
            'body' => 'Love this! We can shoot two hair gloss clips at Old Airport tomorrow.',
        ])->assertRedirect(route('guide.index', ['section' => 'ideas']));

        $this->assertDatabaseHas('crm_idea_comments', [
            'idea_id' => $idea->id,
            'user_id' => $this->secondAgent->id,
            'body' => 'Love this! We can shoot two hair gloss clips at Old Airport tomorrow.',
        ]);

        // 4. Creator can edit while status is 'New Idea', but another non-admin user gets 403
        $this->actingAs($this->secondAgent)->put(route('guide.ideas.update', $idea), [
            'title' => 'Hijacked Title',
            'category' => 'Giveaway',
            'description' => 'Unauthorized edit attempt.',
        ])->assertForbidden();

        $this->actingAs($this->secondAgent)->delete(route('guide.ideas.destroy', $idea))
            ->assertForbidden();

        $this->actingAs($this->agent)->put(route('guide.ideas.update', $idea), [
            'title' => 'VIP Auto-DM Gloss Reveal Hook',
            'category' => 'Reels Hook',
            'description' => 'Updated hook with Old Airport & Al Wakrah branch tags.',
        ])->assertRedirect(route('guide.index', ['section' => 'ideas']));

        $this->assertSame('VIP Auto-DM Gloss Reveal Hook', $idea->fresh()->title);
    }

    public function test_admin_converts_idea_to_ops_task_with_due_at_and_blocks_non_admins_from_admin_routes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 14:00:00', 'Asia/Qatar'));

        try {
            $marketingUser = User::factory()->create([
                'name' => 'Ayan Marketing',
                'email' => 'ayan@laleen.qa',
                'role' => 'marketing_specialist',
                'is_active' => true,
                'permissions' => User::defaultPermissionsForRole('marketing_specialist'),
            ]);

            $idea = CrmIdea::create([
                'title' => 'Print Acrylic Giveaway QR Stands for Both Receptions',
                'category' => 'Giveaway',
                'description' => 'Place QR stands on Old Airport & Al Wakrah front desks linking to the 7-day VSL countdown.',
                'status' => CrmIdea::STATUS_NEW,
                'upvotes_count' => 3,
                'created_by' => $this->agent->id,
            ]);

            // Non-admin roles receive 403 on Convert to Ops Task, Idea Status Update, and Presentation Delete
            $seededPresentation = CrmPresentation::firstOrFail();

            foreach ([$this->agent, $this->manager, $marketingUser] as $nonAdmin) {
                $this->actingAs($nonAdmin)->post(route('guide.ideas.convert', $idea), [
                    'assigned_user_ids' => [$marketingUser->id],
                    'timeframe' => '48h',
                ])->assertForbidden();

                $this->actingAs($nonAdmin)->patch(route('guide.ideas.status', $idea), [
                    'status' => CrmIdea::STATUS_APPROVED,
                ])->assertForbidden();

                $this->actingAs($nonAdmin)->delete(route('guide.presentations.destroy', $seededPresentation))
                    ->assertForbidden();
            }

            // Agent also cannot create or update presentations (403), whereas Manager & Admin can
            $this->actingAs($this->agent)->post(route('guide.presentations.store'), [
                'title' => 'Unauthorized Deck',
                'category' => 'Branch SOP',
                'slides' => [['title' => 'Slide 1', 'body_points' => 'Point 1']],
            ])->assertForbidden();

            $this->actingAs($this->manager)->post(route('guide.presentations.store'), [
                'title' => 'Wakrah Floor Upsell Playbook',
                'category' => 'Branch SOP',
                'summary' => 'Chair-side tier 2/3 ritual upgrades.',
                'slides' => [
                    [
                        'eyebrow' => 'FLOOR SOP',
                        'title' => 'Consultation First <script>evil()</script>',
                        'layout' => 'checklist',
                        'highlight_metric' => '35% Upsell Target',
                        'body_points' => "Check hair porosity\nRecommend botanical mask add-on",
                    ],
                ],
            ])->assertRedirect(route('guide.index', ['section' => 'presentations']));

            $mgrDeck = CrmPresentation::where('title', 'Wakrah Floor Upsell Playbook')->firstOrFail();
            $this->assertSame('Consultation First evil()', $mgrDeck->slides[0]['title']);

            // Admin converts idea into a live Ops Tracker task with 72h (3 Days) timeframe
            $convertResp = $this->actingAs($this->admin)->post(route('guide.ideas.convert', $idea), [
                'assigned_user_ids' => [$marketingUser->id, $this->manager->id],
                'timeframe' => '72h',
                'priority' => 'urgent',
            ]);

            $convertResp->assertRedirect(route('guide.index', ['section' => 'ideas']));

            $idea->refresh();
            $this->assertSame(CrmIdea::STATUS_CONVERTED, $idea->status);
            $this->assertNotNull($idea->ops_task_id);

            $opsTask = OpsActionItem::findOrFail($idea->ops_task_id);
            $this->assertSame('Print Acrylic Giveaway QR Stands for Both Receptions', $opsTask->title);
            $this->assertSame('Marketing & Ads', $opsTask->scope);
            $this->assertSame('urgent', $opsTask->priority);
            $this->assertSame(72, $opsTask->sla_hours);
            $this->assertEqualsCanonicalizing([$marketingUser->id, $this->manager->id], $opsTask->assigned_user_ids);
            $this->assertTrue($opsTask->due_at->equalTo(now()->addHours(72)));

            // Once converted, original non-admin creator can no longer edit or delete the idea (403)
            $this->actingAs($this->agent)->delete(route('guide.ideas.destroy', $idea))
                ->assertForbidden();

            // Admin can delete a presentation cleanly
            $this->actingAs($this->admin)->delete(route('guide.presentations.destroy', $mgrDeck))
                ->assertRedirect(route('guide.index', ['section' => 'presentations']));
            $this->assertDatabaseMissing('crm_presentations', ['id' => $mgrDeck->id]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_zero_salary_compensation_or_pin_data_is_exposed_in_guides_presentations_or_ideas(): void
    {
        $staff = Staff::create([
            'name' => 'Confidential Stylist',
            'branch' => 'old_airport',
            'employment_type' => 'Stylist',
            'bookable' => true,
            'base_salary' => 9432.00,
            'hourly_wage' => 58.50,
            'contract_pin' => '7391',
        ]);

        $this->agent->update(['contract_pin' => '4826']);

        StaffContract::create([
            'staff_id' => $staff->id,
            'contract_number' => 'CNT-2026-777',
            'template_key' => 'salon_beautician',
            'role_title' => 'Stylist',
            'branch' => 'old_airport',
            'base_salary' => 13579.00,
            'currency' => 'QAR',
            'start_date' => today()->toDateString(),
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        foreach ([$this->admin, $this->agent] as $viewer) {
            $html = $this->actingAs($viewer)->get(route('guide.index', ['section' => 'presentations']))->getContent();
            $this->assertStringNotContainsString('9432', (string) $html);
            $this->assertStringNotContainsString('13579', (string) $html);
            $this->assertStringNotContainsString('7391', (string) $html);
            $this->assertStringNotContainsString('4826', (string) $html);
            $this->assertStringNotContainsString('contract_pin', (string) $html);
        }
    }
}
