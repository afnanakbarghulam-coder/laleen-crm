<?php

namespace Tests\Feature;

use App\Models\SocialContentPlan;
use App\Models\SocialContentWeek;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeeklyContentCalendarPdfImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $sana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Afnan Admin',
            'email' => 'afnan@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->sana = User::factory()->create([
            'name' => 'Sana Content',
            'email' => 'sana@laleen.qa',
            'role' => 'staff',
            'is_active' => true,
            'permissions' => [
                'kpis' => 'edit',
            ],
        ]);
    }

    public function test_weekly_content_calendar_renders_unified_seven_day_layout(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('kpi.content-calendar.index', ['week' => '2026-11-16']));

        $response->assertOk()
            ->assertSee('Weekly Content Calendar')
            ->assertSee('Upload PDF')
            ->assertSee('16 Nov – 22 Nov, 2026')
            ->assertSee('0</strong>/7 Posted (<span id="wccExecutionPct">0%</span>)', false)
            ->assertSee('Day &amp; Assign', false)
            ->assertSee('Focus &amp; Format', false)
            ->assertSee('Visual / Video Concept')
            ->assertSee('Caption Hook &amp; CTA', false)
            ->assertSee('Proof &amp; Status', false)
            ->assertSee('Copy caption')
            ->assertSee('Paste live URL...')
            ->assertSee('resize-none overflow-hidden', false)
            ->assertDontSee('Delete Plan')
            ->assertDontSee('id="wccOpenDeleteModalBtn"', false)
            ->assertDontSee('id="wccCopyHashtagsBtn"', false)
            ->assertDontSee('id="wccToggleStrategyBtn"', false)
            ->assertDontSee('8:00 PM Reel')
            ->assertDontSee('Daily Stories')
            ->assertDontSee('wcc-status-pill')
            ->assertDontSee('wcc-format-select');

        foreach (SocialContentPlan::DAYS_OF_WEEK as $dayName) {
            $response->assertSee(strtoupper($dayName));
        }

        $this->assertSame(7, SocialContentPlan::where('week_start_date', '2026-11-16')->count());
    }

    public function test_uploading_plan_pdf_extracts_seven_days_and_strategy_metadata_preserving_existing_assignments_and_proof(): void
    {
        // Pre-populate Monday with an existing branch, staff assignment, and live proof URL
        SocialContentPlan::create([
            'week_start_date' => '2026-11-16',
            'date' => '2026-11-16',
            'day_of_week' => 'Monday',
            'branch' => 'Old Airport',
            'target_branch' => 'Old Airport',
            'assigned_user_id' => $this->sana->id,
            'assigned_to' => $this->sana->name,
            'proof_url' => 'https://instagram.com/reel/already_live_monday',
            'status' => 'posted',
            'is_reel_posted' => true,
            'reel_link' => 'https://instagram.com/reel/already_live_monday',
        ]);

        $geminiPayload = [
            'explicit_week_start_date' => null,
            'strategy_objective' => 'Is hafte ka main goal 7 Services 99 QAR offer aur winter frizzy hair transformations se walk-ins aur WhatsApp bookings barhana hai.',
            'pro_tips' => [
                [
                    'title' => 'Pin Best Performers',
                    'body' => 'Pin the 7 Services 99 QAR Reel and Hair Color transformation to top of profile.',
                ],
                [
                    'title' => 'Story Highlights Setup',
                    'body' => 'Save daily Q&A and branch walk-in availability under November Offers.',
                ],
            ],
            'hashtags' => '#LaleenBeautySalon #DohaSalons #QatarBeauty #DohaHair #QatarOffers #SalonsInDoha',
            'days' => [
                [
                    'day_number' => 1,
                    'day_name' => 'Monday',
                    'focus' => 'Day 1: Monday - 7 Services 99 QAR',
                    'format' => 'Reel / Short Video',
                    'visual_concept' => 'Fast-cut salon walkthrough showing all 7 pampering services included in 99 QAR.',
                    'caption_cta' => '"7 full salon services for just 99 QAR! DM or WhatsApp now to reserve your slot."',
                ],
                [
                    'day_number' => 2,
                    'day_name' => 'Tuesday',
                    'focus' => 'Hair Color Offer',
                    'format' => 'Carousel (Before & After)',
                    'visual_concept' => '3-slide swipe transformation from brassy tones to rich espresso gloss.',
                    'caption_cta' => '"Swipe to see the glossy espresso blend. Book your color consultation today!"',
                ],
                [
                    'day_number' => 3,
                    'day_name' => 'Wednesday',
                    'focus' => 'Frizzy & Dry Hair',
                    'format' => 'Before / After Reel',
                    'visual_concept' => 'Split-screen comb glide test before and after protein treatment.',
                    'caption_cta' => 'Say goodbye to Doha humidity frizz. Tap link in bio to book.',
                ],
                [
                    'day_number' => 4,
                    'day_name' => 'Thursday',
                    'focus' => 'Weekend Glow Package',
                    'format' => 'Static Graphic / Story',
                    'visual_concept' => 'Clean luxury champagne graphic listing Thursday evening open slots.',
                    'caption_cta' => 'Last 4 slots open for Thursday evening pampering — reply BOOK.',
                ],
                [
                    'day_number' => 5,
                    'day_name' => 'Friday',
                    'focus' => 'Salon POV Experience',
                    'format' => 'POV Reel / Trendy Audio',
                    'visual_concept' => 'Client POV arriving at Laleen, Arabic coffee welcome, and blowout reveal.',
                    'caption_cta' => 'Your Friday self-care ritual starts at Laleen. Visit Old Airport or Al Wakrah.',
                ],
                [
                    'day_number' => 6,
                    'day_name' => 'Saturday',
                    'focus' => 'Branch Q&A + Live Slots',
                    'format' => 'Story Series (3 Frames)',
                    'visual_concept' => 'Frame 1: Poll on hair concerns. Frame 2: Stylist tip. Frame 3: Direct WhatsApp sticker.',
                    'caption_cta' => 'Tap the WhatsApp sticker to claim your Saturday slot.',
                ],
                [
                    'day_number' => 7,
                    'day_name' => 'Sunday',
                    'focus' => 'Bridal & Party Styling',
                    'format' => 'Static Offer Post',
                    'visual_concept' => 'Editorial portrait of signature bridal glam + hairstyling bundle.',
                    'caption_cta' => 'Planning an event this month? Save this post & message us for bridal packages.',
                ],
            ],
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode($geminiPayload, JSON_UNESCAPED_UNICODE)],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $fakePdf = UploadedFile::fake()->createWithContent(
            'Laleen_Weekly_Content_Plan.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF"
        );

        $response = $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('kpi.content-calendar.import-pdf'), [
                'week_start_date' => '2026-11-16',
                'plan_pdf' => $fakePdf,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Content plan imported for 16 Nov – 22 Nov, 2026')
            ->assertJsonPath('week_start_date', '2026-11-16');

        Http::assertSent(function (HttpClientRequest $req) {
            $body = $req->data();
            $inlineMime = data_get($body, 'contents.0.parts.0.inline_data.mime_type');
            $respMime = data_get($body, 'generationConfig.responseMimeType');

            return $inlineMime === 'application/pdf' && $respMime === 'application/json';
        });

        // Verify Weekly Metadata saved accurately (including Roman Urdu & Hashtags)
        $weekMeta = SocialContentWeek::where('week_start_date', '2026-11-16')->first();
        $this->assertNotNull($weekMeta);
        $this->assertStringContainsString('Is hafte ka main goal 7 Services 99 QAR', (string) $weekMeta->strategy_objective);
        $this->assertStringContainsString('#LaleenBeautySalon', (string) $weekMeta->hashtags);
        $this->assertCount(2, $weekMeta->normalizedProTips());
        $this->assertSame('Pin Best Performers', $weekMeta->normalizedProTips()[0]['title']);
        $this->assertSame('Laleen_Weekly_Content_Plan.pdf', $weekMeta->source_pdf_name);

        // Verify Monday stripped "Day 1: Monday -" prefix, stripped outer caption quotes, AND preserved existing branch/assignee/proof
        $monday = SocialContentPlan::where('week_start_date', '2026-11-16')
            ->where('day_of_week', 'Monday')
            ->firstOrFail();

        $this->assertSame('7 Services 99 QAR', $monday->focus);
        $this->assertSame('Reel / Short Video', $monday->format);
        $this->assertSame('7 full salon services for just 99 QAR! DM or WhatsApp now to reserve your slot.', $monday->caption_cta);
        $this->assertSame('Old Airport', $monday->effectiveBranch());
        $this->assertSame('Sana Content', $monday->assigned_to);
        $this->assertSame('https://instagram.com/reel/already_live_monday', $monday->proof_url);
        $this->assertSame('posted', $monday->status);

        // Verify Tuesday Carousel & Saturday Story Series
        $tuesday = SocialContentPlan::where('week_start_date', '2026-11-16')->where('day_of_week', 'Tuesday')->firstOrFail();
        $this->assertSame('Hair Color Offer', $tuesday->focus);
        $this->assertSame('Carousel (Before & After)', $tuesday->format);
        $this->assertSame('pending', $tuesday->status);

        $saturday = SocialContentPlan::where('week_start_date', '2026-11-16')->where('day_of_week', 'Saturday')->firstOrFail();
        $this->assertSame('Story Series (3 Frames)', $saturday->format);
        $this->assertTrue($saturday->isStoryFormat());

        // Verify GET page renders flash banner + Delete Plan + Strategy & Tips + Copy Hashtags + Imported Rows
        $page = $this->actingAs($this->admin)->get(route('kpi.content-calendar.index', ['week' => '2026-11-16']));
        $page->assertOk()
            ->assertSee('<strong>Success:</strong> Content plan imported for 16 Nov – 22 Nov, 2026', false)
            ->assertSee('Copy Hashtags')
            ->assertSee('Strategy &amp; Tips', false)
            ->assertSee('Delete Plan')
            ->assertSee('Delete Weekly Content Plan?')
            ->assertDontSee('PDF: Laleen_Weekly')
            ->assertSee('7 Services 99 QAR')
            ->assertSee('Carousel (Before &amp; After)', false)
            ->assertSee('Story Series (3 Frames)');
    }

    public function test_inline_editing_persists_focus_format_visual_concept_caption_cta_branch_and_assigned_to(): void
    {
        $this->actingAs($this->sana)->get(route('kpi.content-calendar.index', ['week' => '2026-11-16']))->assertOk();

        $wednesday = SocialContentPlan::where('week_start_date', '2026-11-16')
            ->where('day_of_week', 'Wednesday')
            ->firstOrFail();

        $this->actingAs($this->sana)
            ->patchJson(route('kpi.content-calendar.inline-update', $wednesday), [
                'branch' => 'Al Wakrah',
                'assigned_to' => $this->sana->name,
                'focus' => 'Keratin Smooth Offer',
                'format' => 'Before / After Reel',
                'visual_concept' => 'Close-up shine test under ring light at Al Wakrah branch.',
                'caption_cta' => 'Silky smooth hair for 3 months — WhatsApp Al Wakrah now!',
            ])
            ->assertOk()
            ->assertJsonPath('plan.branch', 'Al Wakrah')
            ->assertJsonPath('plan.assigned_to', 'Sana Content')
            ->assertJsonPath('plan.focus', 'Keratin Smooth Offer')
            ->assertJsonPath('plan.format', 'Before / After Reel')
            ->assertJsonPath('weekly_stats.reels_count', 1);

        $wednesday->refresh();
        $this->assertSame('Al Wakrah', $wednesday->branch);
        $this->assertSame('Sana Content', $wednesday->assigned_to);
        $this->assertSame($this->sana->id, $wednesday->assigned_user_id);
        $this->assertSame('Keratin Smooth Offer', $wednesday->focus);
        $this->assertSame('Before / After Reel', $wednesday->format);
        $this->assertSame('Close-up shine test under ring light at Al Wakrah branch.', $wednesday->visual_concept);
        $this->assertSame('Silky smooth hair for 3 months — WhatsApp Al Wakrah now!', $wednesday->caption_cta);
    }

    public function test_marking_posted_requires_proof_url_or_story_verified_and_recalculates_weekly_execution_pct(): void
    {
        $this->actingAs($this->sana)->get(route('kpi.content-calendar.index', ['week' => '2026-11-16']))->assertOk();

        $plans = SocialContentPlan::where('week_start_date', '2026-11-16')
            ->get()
            ->keyBy('day_of_week');

        $monday = $plans->get('Monday');
        $monday->update([
            'focus' => '7 Services 99 QAR',
            'format' => 'Reel / Short Video',
            'visual_concept' => 'Walkthrough reel',
        ]);

        // 1. Attempting to mark a Reel Posted without proof_url fails with 422
        $this->actingAs($this->sana)
            ->patchJson(route('kpi.content-calendar.toggle-status', $monday), [
                'field' => 'posted',
                'proof_url' => '',
            ])
            ->assertStatus(422);

        // 2. Attempting story_verified on a non-Story format fails with 422
        $this->actingAs($this->sana)
            ->patchJson(route('kpi.content-calendar.toggle-status', $monday), [
                'field' => 'story_verified',
            ])
            ->assertStatus(422);

        // 3. Providing a valid live proof_url unlocks Posted and updates Weekly Execution: 1/7 -> 14%
        $resOne = $this->actingAs($this->sana)
            ->patchJson(route('kpi.content-calendar.inline-update', $monday), [
                'proof_url' => 'https://instagram.com/reel/laleen_mon_99qar',
            ]);

        $resOne->assertOk()
            ->assertJsonPath('plan.status', 'posted')
            ->assertJsonPath('weekly_stats.completed_count', 1)
            ->assertJsonPath('weekly_stats.execution_pct', 14);

        // 4. Story format ('Story Series (3 Frames)') can be marked Posted via is_story_verified without permanent URL
        $saturday = $plans->get('Saturday');
        $saturday->update([
            'focus' => 'Saturday Flash Slots',
            'format' => 'Story Series (3 Frames)',
        ]);

        $resStory = $this->actingAs($this->sana)
            ->patchJson(route('kpi.content-calendar.toggle-status', $saturday), [
                'field' => 'story_verified',
                'format' => 'Story Series (3 Frames)',
            ]);

        $resStory->assertOk()
            ->assertJsonPath('plan.is_story_verified', true)
            ->assertJsonPath('plan.status', 'posted')
            ->assertJsonPath('weekly_stats.completed_count', 2)
            ->assertJsonPath('weekly_stats.execution_pct', 29);

        // 5. Complete remaining 5 days with proof URLs -> 7/7 = 100% Weekly Execution
        foreach (['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Sunday'] as $dayName) {
            $dayPlan = $plans->get($dayName);
            $lastRes = $this->actingAs($this->sana)
                ->patchJson(route('kpi.content-calendar.inline-update', $dayPlan), [
                    'format' => 'Reel / Short Video',
                    'proof_url' => 'https://instagram.com/p/laleen_' . strtolower($dayName),
                ])
                ->assertOk();
        }

        $lastRes->assertJsonPath('weekly_stats.completed_count', 7)
            ->assertJsonPath('weekly_stats.execution_pct', 100);
    }

    public function test_explicit_week_auto_detection_and_inline_week_strategy_meta_update(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    'explicit_week_start_date' => '2026-11-25', // Wednesday inside 23 Nov – 29 Nov week
                                    'strategy_objective' => 'End-of-month payday offer push.',
                                    'pro_tips' => [['title' => 'Timing', 'body' => 'Post Reels at 7:30 PM Doha time.']],
                                    'hashtags' => '#LaleenPayday #QatarSalons',
                                    'days' => [
                                        [
                                            'day_number' => 1,
                                            'day_name' => 'Monday',
                                            'focus' => 'Payday 99 QAR',
                                            'format' => 'Reel / Short Video',
                                            'visual_concept' => 'Payday pampering showcase',
                                            'caption_cta' => 'Treat yourself this payday!',
                                        ],
                                    ],
                                ])],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $fakePdf = UploadedFile::fake()->createWithContent(
            'Payday_Week_Plan.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF"
        );

        // Auto-detect maps 2026-11-25 to Monday 2026-11-23 (23 Nov – 29 Nov, 2026)
        $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('kpi.content-calendar.import-pdf'), [
                'week_start_date' => '2026-11-16',
                'auto_detect_week' => '1',
                'plan_pdf' => $fakePdf,
            ])
            ->assertOk()
            ->assertJsonPath('week_start_date', '2026-11-23')
            ->assertJsonPath('message', 'Content plan imported for 23 Nov – 29 Nov, 2026');

        // Inline edit of weekly strategy metadata
        $this->actingAs($this->sana)
            ->patchJson(route('kpi.content-calendar.update-meta'), [
                'week_start_date' => '2026-11-23',
                'strategy_objective' => 'Updated Roman Urdu + English strategy objective.',
                'pro_tips' => "Pin Reel: Keep top offer pinned\nDM Fast: Reply within 10 minutes",
                'hashtags' => '#LaleenBeautySalon #DohaBeauty',
            ])
            ->assertOk()
            ->assertJsonPath('week_meta.strategy_objective', 'Updated Roman Urdu + English strategy objective.')
            ->assertJsonPath('week_meta.hashtags', '#LaleenBeautySalon #DohaBeauty');

        $meta = SocialContentWeek::where('week_start_date', '2026-11-23')->firstOrFail();
        $this->assertCount(2, $meta->normalizedProTips());
        $this->assertSame('Pin Reel', $meta->normalizedProTips()[0]['title']);
    }

    public function test_delete_week_plan_clears_target_week_sets_flash_notification_and_leaves_adjacent_weeks_untouched(): void
    {
        // Seed Target Week: 2026-11-16 (16 Nov – 22 Nov, 2026)
        SocialContentWeek::create([
            'week_start_date' => '2026-11-16',
            'strategy_objective' => 'Week 1 strategy to delete',
            'pro_tips' => [['title' => 'Tip 1', 'body' => 'Pin top reel']],
            'hashtags' => '#WeekOne #LaleenBeautySalon',
            'source_pdf_name' => 'Week1.pdf',
            'uploaded_at' => now(),
        ]);

        foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
            SocialContentPlan::create([
                'week_start_date' => '2026-11-16',
                'date' => \Carbon\Carbon::parse('2026-11-16')->addDays($idx)->toDateString(),
                'day_of_week' => $dayName,
                'branch' => 'Old Airport',
                'assigned_to' => $this->sana->name,
                'focus' => "Week 1 {$dayName} Offer",
                'format' => 'Reel / Short Video',
                'visual_concept' => "Visual brief for {$dayName}",
                'caption_cta' => "Caption for {$dayName}",
                'proof_url' => 'https://instagram.com/reel/week1_' . strtolower($dayName),
                'status' => 'posted',
                'is_reel_posted' => true,
            ]);
        }

        // Seed Adjacent Week: 2026-11-23 (23 Nov – 29 Nov, 2026) — MUST remain 100% untouched
        SocialContentWeek::create([
            'week_start_date' => '2026-11-23',
            'strategy_objective' => 'Protected Week 2 strategy objective',
            'pro_tips' => [['title' => 'Keep Safe', 'body' => 'Must not be deleted']],
            'hashtags' => '#ProtectedWeekTwo',
            'source_pdf_name' => 'Week2_Protected.pdf',
            'uploaded_at' => now(),
        ]);

        SocialContentPlan::create([
            'week_start_date' => '2026-11-23',
            'date' => '2026-11-23',
            'day_of_week' => 'Monday',
            'branch' => 'Al Wakrah',
            'assigned_to' => $this->sana->name,
            'focus' => 'Protected Next Week 99 QAR',
            'format' => 'Carousel (Before & After)',
            'visual_concept' => 'Protected carousel concept',
            'caption_cta' => 'Protected caption CTA',
            'proof_url' => 'https://instagram.com/p/protected_week2',
            'status' => 'posted',
            'is_reel_posted' => true,
        ]);

        // Execute DELETE for 2026-11-16
        $deleteResp = $this->actingAs($this->admin)
            ->delete(route('kpi.content-calendar.destroy-week'), [
                'week_start_date' => '2026-11-16',
            ]);

        $deleteResp->assertRedirect(route('kpi.content-calendar.index', ['week' => '2026-11-16']))
            ->assertSessionHas('success', 'Success: Content calendar for 16 Nov – 22 Nov, 2026 has been deleted.');

        // Verify DB cleared for 2026-11-16 immediately after DELETE
        $this->assertNull(SocialContentWeek::where('week_start_date', '2026-11-16')->first());
        $this->assertSame(0, SocialContentPlan::where('week_start_date', '2026-11-16')->count());

        // Follow redirect -> confirmation banner renders cleanly & Delete Plan hides on fresh empty week
        $afterPage = $this->actingAs($this->admin)
            ->get(route('kpi.content-calendar.index', ['week' => '2026-11-16']));

        $afterPage->assertOk()
            ->assertSee('<strong>Success:</strong> Content calendar for 16 Nov – 22 Nov, 2026 has been deleted.', false)
            ->assertDontSee('Success: Success:')
            ->assertDontSee('Delete Plan')
            ->assertDontSee('Week 1 Monday Offer');

        // Verify Adjacent Week (2026-11-23) is 100% intact
        $keptMeta = SocialContentWeek::where('week_start_date', '2026-11-23')->first();
        $this->assertNotNull($keptMeta);
        $this->assertSame('Protected Week 2 strategy objective', $keptMeta->strategy_objective);
        $this->assertSame('#ProtectedWeekTwo', $keptMeta->hashtags);

        $keptMonday = SocialContentPlan::where('week_start_date', '2026-11-23')
            ->where('day_of_week', 'Monday')
            ->first();
        $this->assertNotNull($keptMonday);
        $this->assertSame('Protected Next Week 99 QAR', $keptMonday->focus);
        $this->assertSame('Protected carousel concept', $keptMonday->visual_concept);
        $this->assertSame('https://instagram.com/p/protected_week2', $keptMonday->proof_url);
        $this->assertSame('posted', $keptMonday->status);
    }
}
