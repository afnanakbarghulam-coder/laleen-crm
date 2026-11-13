<?php

namespace Tests\Feature;

use App\Models\KpiContentEntry;
use App\Models\KpiContentReport;
use App\Models\SocialContentPlan;
use App\Models\SocialLateReplyLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SanaKpiPipelineAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $sana;
    private User $ayan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin Director',
            'email' => 'admin.sana.audit@laleen.qa',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->sana = User::factory()->create([
            'name' => 'Sana Content',
            'email' => 'sana.audit@laleen.qa',
            'role' => 'staff',
            'is_active' => true,
            'permissions' => ['kpis' => 'edit', 'dashboard' => 'view'],
        ]);

        $this->ayan = User::factory()->create([
            'name' => 'Ayan Marketing',
            'email' => 'ayan.audit@laleen.qa',
            'role' => 'manager',
            'is_active' => true,
            'permissions' => ['kpis' => 'edit', 'dashboard' => 'view'],
        ]);
    }

    public function test_admin_only_late_reply_evidence_log_screenshot_proof_viewer_and_cross_week_delete(): void
    {
        Storage::fake('public');
        $this->travelTo(Carbon::parse('2026-11-12 14:00:00', 'Asia/Qatar'));

        // Seed full in-month posts from Nov 1 through Nov 12 so Content KPI is 100% PASSING
        $this->seedCompletedRange(
            Carbon::parse('2026-11-01', 'Asia/Qatar'),
            Carbon::parse('2026-11-12', 'Asia/Qatar')
        );

        // Non-admin users (Sana & Ayan, even with kpis=edit) get 403 on logging late replies
        $fakeShot = UploadedFile::fake()->image('sana-attempt.jpg');
        $this->actingAs($this->sana)
            ->post(route('kpi.late-replies.update'), [
                'incident_at' => '2026-11-04T15:30',
                'screenshot' => $fakeShot,
                'notes' => 'Should be forbidden',
                'month' => '2026-11',
            ])
            ->assertForbidden();

        $this->actingAs($this->ayan)
            ->post(route('kpi.late-replies.update'), [
                'incident_at' => '2026-11-04T15:30',
                'notes' => 'Manager non-admin attempt',
                'month' => '2026-11',
            ])
            ->assertForbidden();

        $this->assertSame(0, SocialLateReplyLog::count());

        // Non-admin hub HTML must NOT render the #logLateReplyModal or '+' trigger
        $sanaHubBefore = $this->actingAs($this->sana)->get(route('kpi.hub', ['month' => '2026-11']));
        $sanaHubBefore->assertOk();
        $sanaHubBefore->assertDontSee('id="logLateReplyModal"', false);
        $sanaHubBefore->assertDontSee('data-bs-target="#logLateReplyModal"', false);

        // Admin logs a breach in Week 1 (Nov 4) with screenshot + note while viewing Week 2 (Nov 12)
        $adminShot = UploadedFile::fake()->image('late-dm-proof.jpg', 800, 600);
        $this->actingAs($this->admin)
            ->post(route('kpi.late-replies.update'), [
                'incident_at' => '2026-11-04T16:45',
                'screenshot' => $adminShot,
                'notes' => 'Instagram DM @client — 3h 15m reply delay',
                'month' => '2026-11',
            ])
            ->assertRedirect(route('kpi.hub', ['month' => '2026-11']));

        $log = SocialLateReplyLog::first();
        $this->assertNotNull($log);
        $this->assertSame($this->admin->id, $log->logged_by);
        $this->assertSame('2026-11-04 16:45:00', $log->incident_at->copy()->setTimezone('Asia/Qatar')->format('Y-m-d H:i:s'));
        $this->assertNotNull($log->screenshot_path);
        Storage::disk('public')->assertExists($log->screenshot_path);

        // Verify Sana's Card 2 and Ayan's Card 4 both drop to SLA FAIL (Content=100% + Replies=0% => Overall=50.0%)
        $adminHubAfter = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']));
        $adminHubAfter->assertOk();
        $scorecard = $adminHubAfter->viewData('scorecard');

        $this->assertSame(1, $scorecard['sana']['late_replies_count']);
        $this->assertFalse($scorecard['sana']['replies_sla_passing']);
        $this->assertSame(50.0, (float) $scorecard['sana']['overall_score_pct']);
        $this->assertSame(1, $scorecard['ayan']['social_late_replies_count']);
        $this->assertSame(50.0, (float) $scorecard['ayan']['social_oversight_pct']);
        $this->assertFalse($scorecard['ayan']['social_oversight_passing']);

        // Admin sees View Proof (1) AND Delete form
        $adminHubAfter->assertSee('View Proof (1)');
        $adminHubAfter->assertSee('Instagram DM @client — 3h 15m reply delay');
        $adminHubAfter->assertSee('action="' . route('kpi.late-replies.destroy', $log) . '"', false);
        $adminHubAfter->assertSee('luxe-trash-btn', false);

        // Sana (non-admin KPI viewer) CAN see View Proof (1) + stream screenshot, but CANNOT see Delete button or delete via HTTP
        $sanaHubAfter = $this->actingAs($this->sana)->get(route('kpi.hub', ['month' => '2026-11']));
        $sanaHubAfter->assertOk();
        $sanaHubAfter->assertSee('View Proof (1)');
        $sanaHubAfter->assertSee('Instagram DM @client — 3h 15m reply delay');
        $sanaHubAfter->assertDontSee('action="' . route('kpi.late-replies.destroy', $log) . '"', false);
        $sanaHubAfter->assertDontSee('luxe-trash-btn', false);

        $this->actingAs($this->sana)
            ->get(route('kpi.late-replies.screenshot', $log))
            ->assertOk();

        // /kpis/content-calendar surfaces the read-only breach feedback strip + screenshot link to Sana
        $calendarResp = $this->actingAs($this->sana)
            ->get(route('kpi.content-calendar.index', ['week' => '2026-11-09']));
        $calendarResp->assertOk();
        $calendarResp->assertSee('Social Reply SLA Breaches');
        $calendarResp->assertSee('Instagram DM @client — 3h 15m reply delay');
        $calendarResp->assertSee(route('kpi.late-replies.screenshot', $log), false);

        // Non-admin delete attempt returns 403
        $this->actingAs($this->sana)
            ->delete(route('kpi.late-replies.destroy', $log))
            ->assertForbidden();
        $this->actingAs($this->ayan)
            ->delete(route('kpi.late-replies.destroy', $log))
            ->assertForbidden();

        // Admin deletes the Week 1 breach while in Week 2 -> removes screenshot file and restores 100% SLA!
        $savedPath = $log->screenshot_path;
        $this->actingAs($this->admin)
            ->delete(route('kpi.late-replies.destroy', $log), ['month' => '2026-11'])
            ->assertRedirect(route('kpi.hub', ['month' => '2026-11']));

        $this->assertSame(0, SocialLateReplyLog::count());
        Storage::disk('public')->assertMissing($savedPath);

        $restored = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard');
        $this->assertSame(0, $restored['sana']['late_replies_count']);
        $this->assertTrue($restored['sana']['replies_sla_passing']);
        $this->assertSame(100.0, (float) $restored['sana']['overall_score_pct']);
        $this->assertTrue($restored['ayan']['social_oversight_passing']);
        $this->assertSame(100.0, (float) $restored['ayan']['social_oversight_pct']);
    }

    public function test_strict_same_month_isolation_excludes_straddling_oct_and_dec_days_and_preserves_monday_first_months(): void
    {
        // Viewing historical November 2026 from December 5, 2026
        $this->travelTo(Carbon::parse('2026-12-05 12:00:00', 'Asia/Qatar'));

        // Seed straddling Week '2026-10-26': Monday Oct 26 .. Saturday Oct 31 posted + legacy late reply on Monday Oct 26,
        // while Sunday Nov 1 is UNPOSTED/EMPTY.
        foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
            $isOctDay = $idx < 6; // Mon Oct 26 .. Sat Oct 31
            SocialContentPlan::create([
                'week_start_date' => '2026-10-26',
                'day_of_week' => $dayName,
                'reel_content' => $isOctDay ? "Oct Reel {$dayName}" : null,
                'reel_link' => $isOctDay ? "https://drive.google.com/oct-{$idx}" : null,
                'is_reel_posted' => $isOctDay && $idx < 4,
                'is_reel_skipped' => false,
                'stories_content' => $isOctDay ? "Oct Story {$dayName}" : null,
                'are_stories_posted' => $isOctDay,
                'late_replies_count' => $dayName === 'Monday' ? 3 : 0,
            ]);
        }

        // Seed straddling Week '2026-11-30': Monday Nov 30 is UNPOSTED/EMPTY,
        // while Tuesday Dec 1 .. Sunday Dec 6 are posted + legacy late reply on Tuesday Dec 1.
        foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
            $isDecDay = $idx >= 1; // Tue Dec 1 .. Sun Dec 6
            SocialContentPlan::create([
                'week_start_date' => '2026-11-30',
                'day_of_week' => $dayName,
                'reel_content' => $isDecDay ? "Dec Reel {$dayName}" : null,
                'reel_link' => $isDecDay ? "https://drive.google.com/dec-{$idx}" : null,
                'is_reel_posted' => $isDecDay && $idx <= 4,
                'is_reel_skipped' => false,
                'stories_content' => $isDecDay ? "Dec Story {$dayName}" : null,
                'are_stories_posted' => $isDecDay,
                'late_replies_count' => $dayName === 'Tuesday' ? 2 : 0,
            ]);
        }

        // Also log SocialLateReplyLog rows just outside November boundaries (Oct 31 23:59:59 & Dec 1 00:00:01)
        SocialLateReplyLog::create([
            'incident_at' => Carbon::parse('2026-10-31 23:59:59', 'Asia/Qatar'),
            'notes' => 'October late reply',
            'logged_by' => $this->admin->id,
        ]);
        SocialLateReplyLog::create([
            'incident_at' => Carbon::parse('2026-12-01 00:00:01', 'Asia/Qatar'),
            'notes' => 'December late reply',
            'logged_by' => $this->admin->id,
        ]);

        // November 2026 must see ZERO posts and ZERO late replies from Oct 26-31 or Dec 1-6!
        $novResp = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']));
        $novResp->assertOk();
        $novSana = $novResp->viewData('scorecard')['sana'];

        $this->assertSame(0, $novSana['mtd_published_reels']);
        $this->assertSame(0, $novSana['mtd_published_stories']);
        $this->assertSame(0, $novSana['mtd_items_completed']);
        $this->assertSame(0, $novSana['late_replies_count']);
        $this->assertSame(0.0, (float) $novSana['overall_score_pct']);
        $this->assertSame('2026-11-02', $novSana['in_month_week_start'], 'Historical Nov 2026 link must open in-month Monday 2026-11-02, not Oct 26');

        // Verify Monday-1st month (June 2026: 2026-06-01 is a Monday) is never dropped by SQLite string comparison
        $this->travelTo(Carbon::parse('2026-06-08 10:00:00', 'Asia/Qatar'));
        foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
            SocialContentPlan::create([
                'week_start_date' => '2026-06-01',
                'day_of_week' => $dayName,
                'reel_content' => $idx < 4 ? "June Wk1 Reel {$idx}" : null,
                'reel_link' => $idx < 4 ? "https://drive.google.com/june-{$idx}" : null,
                'is_reel_posted' => $idx < 4,
                'is_reel_skipped' => $idx >= 4,
                'stories_content' => "June Wk1 Story {$idx}",
                'are_stories_posted' => true,
            ]);
        }

        $juneSana = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => '2026-06']))
            ->viewData('scorecard')['sana'];

        $this->assertSame(4, $juneSana['mtd_published_reels']);
        $this->assertSame(7, $juneSana['mtd_published_stories']);
        $this->assertSame(11, $juneSana['mtd_items_completed']);
        $this->assertSame(11, $juneSana['mtd_items_target']);
        $this->assertSame(100.0, (float) $juneSana['weekly_execution_pct']);
        $this->assertTrue($juneSana['content_passing']);
    }

    public function test_option_a_daily_proration_eliminates_monday_morning_cliff_and_caps_category_excess(): void
    {
        // 1. Day 1 Grace Check (Sunday Nov 1, 2026 at 10:00 AM Doha)
        $this->travelTo(Carbon::parse('2026-11-01 10:00:00', 'Asia/Qatar'));

        $emptyDay1 = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard');
        $this->assertSame(0.0, (float) $emptyDay1['sana']['overall_score_pct']);
        $this->assertFalse($emptyDay1['sana']['content_passing']);

        $sundayNov1 = SocialContentPlan::create([
            'week_start_date' => '2026-10-26',
            'day_of_week' => 'Sunday',
            'reel_content' => null,
            'is_reel_posted' => false,
            'is_reel_skipped' => true,
            'stories_content' => 'Nov 1 Welcome Story',
            'are_stories_posted' => false,
        ]);

        $plannedDay1 = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard');
        $this->assertTrue($plannedDay1['sana']['content_passing']);
        $this->assertTrue($plannedDay1['sana']['replies_sla_passing']);
        $this->assertSame(100.0, (float) $plannedDay1['sana']['overall_score_pct']);

        // Mark Nov 1 Story as posted and complete all of Week 1 (Nov 2 - Nov 8: 4 Reels + 7 Stories) => 8 Stories + 4 Reels total
        $sundayNov1->update(['are_stories_posted' => true]);
        foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
            SocialContentPlan::create([
                'week_start_date' => '2026-11-02',
                'day_of_week' => $dayName,
                'reel_content' => $idx < 4 ? "Wk1 Reel {$idx}" : null,
                'reel_link' => $idx < 4 ? "https://drive.google.com/wk1-{$idx}" : null,
                'is_reel_posted' => $idx < 4,
                'is_reel_skipped' => $idx >= 4,
                'stories_content' => "Wk1 Story {$idx}",
                'are_stories_posted' => true,
            ]);
        }

        // 2. Monday Morning Cliff Check: Monday Nov 9 at 09:00 AM Asia/Qatar (completedDays = 8 -> 4 Reels + 8 Stories = 12 target)
        $this->travelTo(Carbon::parse('2026-11-09 09:00:00', 'Asia/Qatar'));

        $mondayMorning = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard');
        $this->assertSame(8, $mondayMorning['sana']['completed_days']);
        $this->assertSame(4, $mondayMorning['sana']['mtd_reels_target']);
        $this->assertSame(8, $mondayMorning['sana']['mtd_stories_target']);
        $this->assertSame(12, $mondayMorning['sana']['mtd_items_target']);
        $this->assertSame(12, $mondayMorning['sana']['mtd_items_completed']);
        $this->assertSame(100.0, (float) $mondayMorning['sana']['weekly_execution_pct']);
        $this->assertTrue($mondayMorning['sana']['content_passing']);
        $this->assertSame(100.0, (float) $mondayMorning['sana']['overall_score_pct']);
        $this->assertSame(100.0, (float) $mondayMorning['ayan']['social_oversight_pct']);
        $this->assertTrue($mondayMorning['ayan']['social_oversight_passing']);

        // 3. Category Excess Cap Check:
        // On Nov 8 (completedDays = 7 -> target = 4 Reels + 7 Stories = 11),
        // if Sana posted 5 Reels + 6 Stories (11 items total, missing 1 Story), execution % must be 10/11 = 90.9% FAIL (NOT 100.0% FAIL!)
        SocialContentPlan::query()->delete();
        $this->travelTo(Carbon::parse('2026-11-08 10:00:00', 'Asia/Qatar'));

        SocialContentPlan::create([
            'week_start_date' => '2026-10-26',
            'day_of_week' => 'Sunday',
            'reel_content' => 'Extra Sunday Reel',
            'reel_link' => 'https://drive.google.com/sun-reel',
            'is_reel_posted' => true,
            'is_reel_skipped' => false,
            'stories_content' => 'Missing Story Concept',
            'are_stories_posted' => false, // Missing Sunday Nov 1 story!
        ]);

        foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
            if ($idx >= 6) {
                continue; // Mon Nov 2 .. Sat Nov 7 = 6 days
            }
            SocialContentPlan::create([
                'week_start_date' => '2026-11-02',
                'day_of_week' => $dayName,
                'reel_content' => $idx < 4 ? "Reel {$idx}" : null,
                'reel_link' => $idx < 4 ? "https://drive.google.com/r-{$idx}" : null,
                'is_reel_posted' => $idx < 4, // +4 Reels = 5 Reels total
                'is_reel_skipped' => $idx >= 4,
                'stories_content' => "Story {$idx}",
                'are_stories_posted' => true, // +6 Stories = 6 Stories total
            ]);
        }

        $excessCheck = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']))->viewData('scorecard')['sana'];
        $this->assertSame(5, $excessCheck['mtd_published_reels']);
        $this->assertSame(6, $excessCheck['mtd_published_stories']);
        $this->assertSame(11, $excessCheck['mtd_items_completed']);
        $this->assertSame(11, $excessCheck['mtd_items_target']);
        $this->assertFalse($excessCheck['content_passing']);
        $this->assertSame(90.9, (float) $excessCheck['weekly_execution_pct'], 'Excess Reels must be capped so missing Stories never show 100.0% FAIL');
    }

    public function test_legacy_content_route_redirects_dashboard_ignores_legacy_reports_and_hub_renders_mtd_bars(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 11:00:00', 'Asia/Qatar'));

        // 1. GET /kpis/content redirects cleanly to /kpis/content-calendar
        $this->actingAs($this->admin)
            ->get(route('kpi.content.index'))
            ->assertRedirect(route('kpi.content-calendar.index'));

        // 2. Seed legacy KpiContentEntry + KpiContentReport while SocialContentPlan is empty:
        // /dashboard must read exclusively from SocialContentPlan (0.0% Fail, NOT legacy entries)
        KpiContentEntry::create([
            'creator_name' => 'Sana Legacy',
            'entry_date' => '2026-11-03',
            'activity_type' => 'Stories Only',
            'stories_posted' => 'Y',
            'standards_stories' => 'Y',
            'created_by' => $this->admin->id,
        ]);
        KpiContentReport::create([
            'creator_name' => 'Sana Legacy',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-09',
            'created_by' => $this->admin->id,
        ]);

        $dashResp = $this->actingAs($this->admin)->get(route('dashboard'));
        $dashResp->assertOk();
        $dashResp->assertViewHas('contentMetrics', function (array $metrics): bool {
            return (float) $metrics['overall'] === 0.0 && $metrics['grade'] === 'Fail';
        });

        // 3. Seed completed Nov 1 - Nov 8 posts and check Card 1 & Card 2 MTD ratios + .viz-bar rendering
        $this->seedCompletedRange(
            Carbon::parse('2026-11-01', 'Asia/Qatar'),
            Carbon::parse('2026-11-08', 'Asia/Qatar')
        );

        $hubResp = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => '2026-11']));
        $hubResp->assertOk();
        $hubResp->assertSee('Organic Content Plan (MTD)');
        $hubResp->assertSee('4/4 Reels');
        $hubResp->assertSee('8/8 Stories');
        $hubResp->assertSee('100% SLA');
        $hubResp->assertDontSee('gradRuby', false);
        $hubResp->assertSee(route('kpi.content-calendar.index', ['week' => '2026-11-09']), false);
    }

    private function seedCompletedRange(Carbon $start, Carbon $end): void
    {
        $cursorMonday = $start->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $lastMonday = $end->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();

        while ($cursorMonday->lte($lastMonday)) {
            $mondayStr = $cursorMonday->toDateString();
            foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
                $exactDay = $cursorMonday->copy()->addDays($idx);
                if ($exactDay->lt($start->copy()->startOfDay()) || $exactDay->gt($end->copy()->endOfDay())) {
                    continue;
                }

                $postReel = $idx < 4;
                SocialContentPlan::updateOrCreate(
                    [
                        'week_start_date' => $mondayStr,
                        'day_of_week' => $dayName,
                    ],
                    [
                        'reel_content' => $postReel ? "Reel {$exactDay->toDateString()}" : null,
                        'reel_link' => $postReel ? "https://drive.google.com/reel-{$exactDay->toDateString()}" : null,
                        'is_reel_posted' => $postReel,
                        'is_reel_skipped' => !$postReel,
                        'stories_content' => "Story {$exactDay->toDateString()}",
                        'are_stories_posted' => true,
                    ]
                );
            }
            $cursorMonday->addWeek();
        }
    }
}
