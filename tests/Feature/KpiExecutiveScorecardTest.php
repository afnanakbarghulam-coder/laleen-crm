<?php

namespace Tests\Feature;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\BranchEvaluation;
use App\Models\Customer;
use App\Models\KpiChatEvaluation;
use App\Models\KpiMonthlyAd;
use App\Models\KpiStaffEvaluation;
use App\Models\Lead;
use App\Models\SocialContentPlan;
use App\Models\Staff;
use App\Models\StaffComplaint;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class KpiExecutiveScorecardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'afnanakbarghulam@gmail.com',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_kpis_executive_scorecard_calculates_ayan_laden_and_sana_metrics_and_late_replies_sla(): void
    {
        $now = now();
        $weekMonday = $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        // 1. Seed Ayan's AdLeadEntry rows across Old Airport (15,000 QAR) and Wakrah (7,500 QAR)
        AdLeadEntry::create([
            'date' => $now->toDateString(),
            'customer_name' => 'OA Ad Client',
            'phone' => '+97455001122',
            'category' => 'Facial',
            'branch' => 'old_airport',
            'ticket_amount' => 15000,
        ]);

        AdLeadEntry::create([
            'date' => $now->toDateString(),
            'customer_name' => 'Wakrah Ad Client',
            'phone' => '+97455001133',
            'category' => 'Hair Treatment',
            'branch' => 'wakrah',
            'ticket_amount' => 7500,
        ]);

        // Unbooked ad lead (1 unbooked, 2 booked = 66.7% ad conversion >= 20% target)
        AdLeadEntry::create([
            'date' => $now->toDateString(),
            'customer_name' => 'Unbooked Ad Lead',
            'phone' => '+97455001144',
            'category' => 'Nails',
            'branch' => null,
            'ticket_amount' => 0,
        ]);

        KpiMonthlyAd::create([
            'ad_name' => 'Scaling Facial Promo',
            'person_responsible' => 'Ayan',
            'offer' => 'HydraFacial',
            'plan_month' => $now->format('Y-m'),
            'target_branch' => 'Old Airport',
            'phase' => 'Scaling',
            'total_budget' => 2500,
        ]);

        // 2. Seed Chat Agents (Areeba & Zoya) and Laden's 13/day Bookings, 85% Chat Evaluation, 30% Lead Conversion, and 48h Complaint SLA
        $areebaUser = User::factory()->create([
            'name' => 'Areeba Khan',
            'email' => 'areeba@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
        ]);

        $zoyaUser = User::factory()->create([
            'name' => 'Zoya Ali',
            'email' => 'zoya@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
        ]);

        // Areeba books 13 appointments today (100% of today's 13/day target)
        for ($i = 1; $i <= 13; $i++) {
            Appointment::create([
                'customer_name' => "Areeba Client {$i}",
                'phone' => '+9746600' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'service_name' => 'Manicure',
                'appointment_datetime' => $now->copy()->setTime(10 + ($i % 8), 0),
                'booking_date' => $now->toDateString(),
                'branch' => $i % 2 === 0 ? 'old_airport' : 'wakrah',
                'booking_agent_id' => $areebaUser->id,
                'created_by' => $areebaUser->id,
                'status' => 'confirmed',
                'price' => 150,
            ]);
        }

        // Zoya books 13 appointments today (so team total today is 13 + 13 = 26 = 100% of Laden's 26/day target, or let's test Zoya at 6 and add 7 front-desk/other bookings or assert Laden's 26/day math)
        for ($j = 1; $j <= 6; $j++) {
            Appointment::create([
                'customer_name' => "Zoya Client {$j}",
                'phone' => '+9746699' . str_pad((string) $j, 4, '0', STR_PAD_LEFT),
                'service_name' => 'Pedicure',
                'appointment_datetime' => $now->copy()->setTime(11 + ($j % 6), 0),
                'booking_date' => $now->toDateString(),
                'branch' => $j % 2 === 0 ? 'old_airport' : 'wakrah',
                'booking_agent_id' => $zoyaUser->id,
                'created_by' => $zoyaUser->id,
                'status' => 'confirmed',
                'price' => 180,
            ]);
        }

        KpiChatEvaluation::create([
            'agent_id' => $areebaUser->id,
            'evaluator_id' => $this->admin->id,
            'evaluation_date' => $now->toDateString(),
            'answers' => [],
            'total_score' => 92,
            'percentage' => 92.00,
        ]);

        KpiChatEvaluation::create([
            'agent_id' => $zoyaUser->id,
            'evaluator_id' => $this->admin->id,
            'evaluation_date' => $now->toDateString(),
            'answers' => [],
            'total_score' => 88,
            'percentage' => 88.00,
        ]);

        // 2 leads total, 1 booked = 50% conversion (>= 30% floor)
        Lead::create([
            'phone' => '+97477001111',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'needful_done' => 'booked',
        ]);
        Lead::create([
            'phone' => '+97477002222',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'needful_done' => null,
        ]);

        // 2 complaints: 1 resolved within 12h (<= 48h SLA pass), 1 open created 60h ago (> 48h SLA breach)
        $resolvedComplaint = StaffComplaint::create([
            'complaint_date' => $now->toDateString(),
            'branch' => 'old_airport',
            'customer_name' => 'Resolved Guest',
            'customer_phone' => '+97455110000',
            'category' => 'Wait Time',
            'description' => 'Waited 15 mins',
            'what_happened' => 'Waited 15 mins',
            'is_staff_mistake' => false,
        ]);
        $resolvedComplaint->forceFill([
            'created_at' => $now->copy()->subHours(24),
            'updated_at' => $now->copy()->subHours(12),
        ])->saveQuietly();

        $openBreachingComplaint = StaffComplaint::create([
            'complaint_date' => $now->copy()->subDays(2)->toDateString(),
            'branch' => 'wakrah',
            'customer_name' => 'Open Guest',
            'customer_phone' => '+97455119999',
            'category' => 'Service Quality',
            'description' => 'Follow up needed',
            'what_happened' => 'Follow up needed',
            'is_staff_mistake' => null,
        ]);
        $openBreachingComplaint->forceFill([
            'created_at' => $now->copy()->subHours(60),
            'updated_at' => $now->copy()->subHours(60),
        ])->saveQuietly();

        // 3. Seed Sana's Weekly Content Plan (4 Reels + 7 Stories = 100% Execution, 0 Late Replies)
        foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
            SocialContentPlan::create([
                'week_start_date' => $weekMonday,
                'day_of_week' => $dayName,
                'reel_content' => $idx < 4 ? "Reel #{$idx}" : null,
                'is_reel_posted' => $idx < 4,
                'is_reel_skipped' => $idx >= 4,
                'stories_content' => "Story #{$idx}",
                'are_stories_posted' => true,
                'late_replies_count' => 0,
            ]);
        }

        // 3B. Seed Ahana (Old Airport) & Rhea (Wakrah) Branch Manager KPIs
        // Active stylists: 2 in old_airport, 2 in wakrah
        $oaStylist1 = Staff::create(['name' => 'OA Stylist 1', 'branch' => 'old_airport', 'bookable' => true]);
        $oaStylist2 = Staff::create(['name' => 'OA Stylist 2', 'branch' => 'old_airport', 'bookable' => true]);
        $wkStylist1 = Staff::create(['name' => 'WK Stylist 1', 'branch' => 'wakrah', 'bookable' => true]);
        $wkStylist2 = Staff::create(['name' => 'WK Stylist 2', 'branch' => 'wakrah', 'bookable' => true]);

        $mondaysInMtd = [];
        foreach (CarbonPeriod::create($now->copy()->startOfMonth()->startOfDay(), $now->copy()->startOfDay()) as $day) {
            if ($day->isMonday()) {
                $mondaysInMtd[] = $day->toDateString();
            }
        }

        // Ahana evaluates 100% of Old Airport stylists on every Monday elapsed MTD
        foreach ($mondaysInMtd as $mondayDate) {
            foreach ([$oaStylist1, $oaStylist2] as $stylist) {
                KpiStaffEvaluation::create([
                    'staff_id' => $stylist->id,
                    'evaluator_id' => $this->admin->id,
                    'branch' => 'old_airport',
                    'evaluation_date' => $mondayDate,
                    'score_cleanliness' => 5,
                    'score_dress_code' => 5,
                    'score_attitude' => 5,
                    'score_sales' => 5,
                    'score_teamwork' => 5,
                    'score_communication' => 5,
                    'overall_average' => 5.00,
                ]);
            }
            // Rhea evaluates 1 of 2 Wakrah stylists on every Monday elapsed MTD (50% compliance when Mondays > 0)
            KpiStaffEvaluation::create([
                'staff_id' => $wkStylist1->id,
                'evaluator_id' => $this->admin->id,
                'branch' => 'wakrah',
                'evaluation_date' => $mondayDate,
                'score_cleanliness' => 5,
                'score_dress_code' => 5,
                'score_attitude' => 5,
                'score_sales' => 5,
                'score_teamwork' => 5,
                'score_communication' => 5,
                'overall_average' => 5.00,
            ]);
        }

        // Branch Mystery Shopper Evaluations:
        // Ahana (Old Airport): 2 visits averaging 90.0% (88.0% + 92.0%) -> PASS (>= 85%)
        BranchEvaluation::create([
            'branch' => 'old_airport',
            'manager' => 'Ahana',
            'evaluation_date' => $now->copy()->startOfMonth()->toDateString(),
            'answers' => [],
            'total_score' => 119,
            'percentage' => 88.00,
            'grade' => 'Pass',
        ]);
        BranchEvaluation::create([
            'branch' => 'old_airport',
            'manager' => 'Ahana',
            'evaluation_date' => $now->toDateString(),
            'answers' => [],
            'total_score' => 124,
            'percentage' => 92.00,
            'grade' => 'Excellent',
        ]);

        // Rhea (Wakrah): 2 visits averaging 80.0% (75.0% + 85.0%) -> BELOW 85% TARGET
        BranchEvaluation::create([
            'branch' => 'wakrah',
            'manager' => 'Rhea',
            'evaluation_date' => $now->copy()->startOfMonth()->toDateString(),
            'answers' => [],
            'total_score' => 101,
            'percentage' => 75.00,
            'grade' => 'Pass',
        ]);
        BranchEvaluation::create([
            'branch' => 'wakrah',
            'manager' => 'Rhea',
            'evaluation_date' => $now->toDateString(),
            'answers' => [],
            'total_score' => 115,
            'percentage' => 85.00,
            'grade' => 'Pass',
        ]);

        // Verify GET /kpis loads HTTP 200 with exact scorecard metrics and clean dark-luxury UI
        $response = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => $now->format('Y-m')]));
        $response->assertOk();
        $response->assertSee('Executive KPI Dashboard');
        $response->assertSee('Key indicators. Clear insights. Smarter decisions.');
        $response->assertSee('Ayan');
        $response->assertSee('Laden');
        $response->assertSee('Sana');
        $response->assertSee('Ahana');
        $response->assertSee('Rhea');
        $response->assertSee('Areeba');
        $response->assertSee('Zoya');
        $response->assertSee('Customer Service Chat Agent');
        $response->assertSee('MTD Booking Quota');
        $response->assertSee("Today's Daily Booking Target", false);
        $response->assertDontSee('Individual Chat Evaluation Score');
        $response->assertSee('Old Airport Branch Manager');
        $response->assertSee('Wakrah Branch Manager');
        $response->assertSee('Monday Staff Evaluation Compliance');
        $response->assertSee('Branch Mystery Shopper Score');
        $response->assertSee('KPIs PASSING');
        $response->assertSee('Click to inspect individual KPIs');
        $response->assertSee('Back to All Staff Scorecards');
        $response->assertSee('Old Airport Ad Revenue');
        $response->assertSee('Wakrah Ad Revenue');
        $response->assertSee('Paid Ad Conversion');
        $response->assertSee('Daily Booking Quota');
        $response->assertSee('Agent Chat Quality');
        $response->assertSee('Lead Conversion Rate');
        $response->assertSee('Complaint Resolution SLA');
        $response->assertSee('Weekly Content Plan');
        $response->assertSee('Social Reply SLA');
        $response->assertSee('Select Staff Scorecard');
        $response->assertDontSee('>Ads Kanban<', false);
        $response->assertDontSee('>Chat Audits<', false);
        $response->assertDontSee('>Content Plan<', false);
        $response->assertDontSee('Operations (Customer Service & Marketing)');
        $response->assertDontSee('Organic & Social Demand');
        $response->assertDontSee('Paid Ads & Growth Engine');
        $response->assertDontSee('Bookings, Chat QA & Retention');
        $response->assertDontSee('Formula:');
        $response->assertDontSee('Weighted Score');
        $response->assertDontSee('BELOW FLOOR');

        $scorecard = $response->viewData('scorecard');
        $expectedDaysElapsed = max(1, (int) $now->day);
        $expectedDaysInMonth = max(1, (int) $now->daysInMonth);
        $expectedMtdBranchTarget = round((15000.0 / $expectedDaysInMonth) * $expectedDaysElapsed, 0);
        $expectedAgentMtdBookingsTarget = 13 * $expectedDaysElapsed;
        $expectedLadenMtdBookingsTarget = 26 * $expectedDaysElapsed;

        $this->assertSame($expectedDaysElapsed, $scorecard['days_elapsed']);
        $this->assertSame($expectedDaysInMonth, $scorecard['days_in_month']);
        $response->assertSee($now->copy()->startOfMonth()->format('M j') . ' – ' . $now->format('M j, Y'));

        // Assert Ayan's metrics (15,000 QAR per branch monthly, prorated MTD target)
        $this->assertSame(15000.0, (float) $scorecard['ayan']['branch_target_qar']);
        $this->assertSame($expectedMtdBranchTarget, (float) $scorecard['ayan']['mtd_branch_target_qar']);
        $this->assertSame($expectedMtdBranchTarget * 2, (float) $scorecard['ayan']['mtd_combined_target_qar']);
        $this->assertSame(15000.0, (float) $scorecard['ayan']['old_airport_ad_revenue_qar']);
        $this->assertSame(100.0, (float) $scorecard['ayan']['old_airport_target_pct']);
        $this->assertSame(100.0, (float) $scorecard['ayan']['old_airport_mtd_pct']);
        $this->assertSame(7500.0, (float) $scorecard['ayan']['wakrah_ad_revenue_qar']);
        $this->assertSame(50.0, (float) $scorecard['ayan']['wakrah_target_pct']);
        $this->assertSame(66.7, (float) $scorecard['ayan']['ad_conversion_pct']);
        $this->assertSame(0, $scorecard['ayan']['social_late_replies_count']);
        $this->assertTrue($scorecard['ayan']['social_oversight_passing']);
        $this->assertSame(4, $scorecard['ayan']['total_kpis_count']);
        $expectedWkMtdPct = min(100.0, round((7500.0 / max(1.0, $expectedMtdBranchTarget)) * 100, 1));
        $this->assertSame($expectedWkMtdPct, (float) $scorecard['ayan']['wakrah_mtd_pct']);
        $this->assertSame(round((100.0 + $expectedWkMtdPct + 100.0 + 100.0) / 4, 1), (float) $scorecard['ayan']['overall_score_pct']);

        // Assert Laden's metrics (26/day team bookings prorated MTD, 85% chat eval, 30% lead conversion, 48h complaint SLA)
        $this->assertSame(26, $scorecard['laden']['daily_booking_target']);
        $this->assertSame($expectedLadenMtdBookingsTarget, $scorecard['laden']['mtd_target_bookings']);
        $this->assertSame(19, $scorecard['laden']['today_bookings_count']);
        $this->assertSame(round((19 / 26) * 100, 1), (float) $scorecard['laden']['today_booking_quota_pct']);
        $this->assertSame(26 * $now->daysInMonth, $scorecard['laden']['full_month_target_bookings']);
        $this->assertSame(90.0, (float) $scorecard['laden']['chat_eval_avg_pct']);
        $this->assertSame(90.0, (float) $scorecard['laden']['average_score_percent']);
        $this->assertSame('PASS', $scorecard['laden']['chat_status_label']);
        $this->assertTrue($scorecard['laden']['chat_passing']);
        $this->assertGreaterThanOrEqual(30.0, (float) $scorecard['laden']['lead_conversion_pct']);
        $this->assertSame('ON TRACK', $scorecard['laden']['lead_status_label']);
        $this->assertTrue($scorecard['laden']['lead_conv_passing']);
        $this->assertArrayHasKey('unassigned_leads_count', $scorecard['laden']);
        $this->assertArrayHasKey('overdue_followups_count', $scorecard['laden']);
        $this->assertSame(2, $scorecard['laden']['total_complaints_count']);
        $this->assertSame(1, $scorecard['laden']['resolved_within_48h_count']);
        $this->assertSame(50.0, (float) $scorecard['laden']['complaint_sla_pct']);
        $this->assertSame(1, $scorecard['laden']['open_complaints_count']);
        $this->assertGreaterThanOrEqual(60, $scorecard['laden']['oldest_open_age_hours']);

        $this->assertSame(4, $scorecard['laden']['total_kpis_count']);
        $this->assertSame(2, $scorecard['laden']['passing_kpis_count']);
        $expectedMtdBkPct = min(100.0, round(($scorecard['laden']['mtd_bookings_count'] / max(1, $expectedLadenMtdBookingsTarget)) * 100, 1));
        $this->assertSame(round(($expectedMtdBkPct + 100.0 + 100.0 + 50.0) / 4, 1), (float) $scorecard['laden']['overall_score_pct']);

        // Assert Sana's metrics (4 Reels + 7 Stories, 0 Late Replies)
        $this->assertSame(4, $scorecard['sana']['published_reels']);
        $this->assertSame(7, $scorecard['sana']['published_stories']);
        $this->assertSame(100.0, (float) $scorecard['sana']['weekly_execution_pct']);
        $this->assertSame(0, $scorecard['sana']['late_replies_count']);
        $this->assertTrue($scorecard['sana']['replies_sla_passing']);
        $this->assertTrue($scorecard['sana']['is_passing_all']);
        $this->assertSame(2, $scorecard['sana']['total_kpis_count']);
        $this->assertSame(2, $scorecard['sana']['passing_kpis_count']);
        $this->assertSame(100.0, (float) $scorecard['sana']['overall_score_pct']);

        // Assert Ahana's metrics (Old Airport Branch Manager: 100% Monday Staff Eval Compliance + 90.0% Mystery Shopper Avg)
        $expectedMondaysCount = count($mondaysInMtd);
        $this->assertSame('Ahana', $scorecard['ahana']['owner']);
        $this->assertSame('old_airport', $scorecard['ahana']['branch']);
        $this->assertSame(2, $scorecard['ahana']['active_staff_count']);
        $this->assertSame($expectedMondaysCount, $scorecard['ahana']['mondays_elapsed_count']);
        $this->assertSame(2 * $expectedMondaysCount, $scorecard['ahana']['expected_evaluations_mtd']);
        $this->assertSame(2 * $expectedMondaysCount, $scorecard['ahana']['completed_evaluations_mtd']);
        $this->assertSame(100.0, (float) $scorecard['ahana']['eval_compliance_pct']);
        $this->assertTrue($scorecard['ahana']['eval_compliance_passing']);
        $this->assertSame(2, $scorecard['ahana']['audits_count_mtd']);
        $this->assertSame(90.0, (float) $scorecard['ahana']['mystery_shopper_avg_pct']);
        $this->assertSame(100.0, (float) $scorecard['ahana']['mystery_shopper_attainment_pct']);
        $this->assertTrue($scorecard['ahana']['mystery_shopper_passing']);
        $this->assertSame(2, $scorecard['ahana']['passing_kpis_count']);
        $this->assertSame(2, $scorecard['ahana']['total_kpis_count']);
        $this->assertSame(100.0, (float) $scorecard['ahana']['overall_score_pct']);

        // Assert Rhea's metrics (Wakrah Branch Manager: 50% Monday Staff Eval Compliance when Mondays > 0 + 80.0% Mystery Shopper Avg)
        $this->assertSame('Rhea', $scorecard['rhea']['owner']);
        $this->assertSame('wakrah', $scorecard['rhea']['branch']);
        $this->assertSame(2, $scorecard['rhea']['active_staff_count']);
        $this->assertSame($expectedMondaysCount, $scorecard['rhea']['mondays_elapsed_count']);
        $this->assertSame(2 * $expectedMondaysCount, $scorecard['rhea']['expected_evaluations_mtd']);
        $this->assertSame(1 * $expectedMondaysCount, $scorecard['rhea']['completed_evaluations_mtd']);
        $expectedRheaEvalPct = $expectedMondaysCount > 0 ? 50.0 : 100.0;
        $this->assertSame($expectedRheaEvalPct, (float) $scorecard['rhea']['eval_compliance_pct']);
        $this->assertSame(2, $scorecard['rhea']['audits_count_mtd']);
        $this->assertSame(80.0, (float) $scorecard['rhea']['mystery_shopper_avg_pct']);
        $this->assertFalse($scorecard['rhea']['mystery_shopper_passing']);
        $expectedRheaMysteryAttainment = min(100.0, round((80.0 / 85.0) * 100, 1));
        $this->assertSame($expectedRheaMysteryAttainment, (float) $scorecard['rhea']['mystery_shopper_attainment_pct']);
        $this->assertSame(round(($expectedRheaEvalPct + $expectedRheaMysteryAttainment) / 2, 1), (float) $scorecard['rhea']['overall_score_pct']);

        // Assert Areeba's metrics (13 bookings today = 100% today target, 13 / (13 * days_elapsed) MTD quota)
        $this->assertSame('Areeba', $scorecard['areeba']['owner']);
        $this->assertSame(13, $scorecard['areeba']['daily_target']);
        $this->assertSame(13, $scorecard['areeba']['today_bookings']);
        $this->assertSame(100.0, (float) $scorecard['areeba']['today_attainment_pct']);
        $this->assertSame(13, $scorecard['areeba']['mtd_bookings']);
        $this->assertSame($expectedAgentMtdBookingsTarget, $scorecard['areeba']['mtd_target']);
        $this->assertSame(13 * $expectedDaysInMonth, $scorecard['areeba']['full_month_target']);
        $expectedAreebaMtdPct = min(100.0, round((13 / max(1, $expectedAgentMtdBookingsTarget)) * 100, 1));
        $this->assertSame($expectedAreebaMtdPct, (float) $scorecard['areeba']['mtd_attainment_pct']);
        $this->assertSame($expectedAreebaMtdPct, (float) $scorecard['areeba']['overall_score_pct']);
        $this->assertSame(1, $scorecard['areeba']['total_kpis_count']);

        // Assert Zoya's metrics (6 bookings today = 46.2% today target, 6 / (13 * days_elapsed) MTD quota)
        $this->assertSame('Zoya', $scorecard['zoya']['owner']);
        $this->assertSame(13, $scorecard['zoya']['daily_target']);
        $this->assertSame(6, $scorecard['zoya']['today_bookings']);
        $this->assertSame(round((6 / 13) * 100, 1), (float) $scorecard['zoya']['today_attainment_pct']);
        $this->assertSame(6, $scorecard['zoya']['mtd_bookings']);
        $this->assertSame($expectedAgentMtdBookingsTarget, $scorecard['zoya']['mtd_target']);
        $this->assertSame(13 * $expectedDaysInMonth, $scorecard['zoya']['full_month_target']);
        $expectedZoyaMtdPct = min(100.0, round((6 / max(1, $expectedAgentMtdBookingsTarget)) * 100, 1));
        $this->assertSame($expectedZoyaMtdPct, (float) $scorecard['zoya']['mtd_attainment_pct']);
        $this->assertSame($expectedZoyaMtdPct, (float) $scorecard['zoya']['overall_score_pct']);
        $this->assertSame(1, $scorecard['zoya']['total_kpis_count']);

        // 4. Test POST /kpis/late-replies increment -> breaches Sana's KPI 2 AND Ayan's KPI 3
        $this->actingAs($this->admin)
            ->post(route('kpi.late-replies.update'), [
                'action' => 'increment',
                'week_start_date' => $weekMonday,
                'month' => $now->format('Y-m'),
            ])
            ->assertRedirect(route('kpi.hub', ['month' => $now->format('Y-m')]));

        $afterInc = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => $now->format('Y-m')]))->viewData('scorecard');
        $this->assertSame(1, $afterInc['sana']['late_replies_count']);
        $this->assertFalse($afterInc['sana']['replies_sla_passing']);
        $this->assertSame(1, $afterInc['ayan']['social_late_replies_count']);
        $this->assertFalse($afterInc['ayan']['social_oversight_passing']);

        // 5. Test POST /kpis/late-replies decrement -> restores 0 late replies & PASS status
        $this->actingAs($this->admin)
            ->post(route('kpi.late-replies.update'), [
                'action' => 'decrement',
                'week_start_date' => $weekMonday,
                'month' => $now->format('Y-m'),
            ])
            ->assertRedirect(route('kpi.hub', ['month' => $now->format('Y-m')]));

        $afterDec = $this->actingAs($this->admin)->get(route('kpi.hub', ['month' => $now->format('Y-m')]))->viewData('scorecard');
        $this->assertSame(0, $afterDec['sana']['late_replies_count']);
        $this->assertTrue($afterDec['sana']['replies_sla_passing']);
        $this->assertTrue($afterDec['ayan']['social_oversight_passing']);

        // 6. Verify GET /kpis/operations and GET /operations load the clean 3-department Operations Hub
        $opsResponse = $this->actingAs($this->admin)->get(route('kpi.operations'));
        $opsResponse->assertOk();
        $opsResponse->assertSee('Operations Hub');
        $opsResponse->assertSee('KPIs (Executive Scorecards)');
        $opsResponse->assertSee('Operations');
        $opsResponse->assertDontSee('Operations (Customer Service & Marketing)');
        $opsResponse->assertSee('Marketing');
        $opsResponse->assertSee('Customer Service');
        $opsResponse->assertSee('Salon Staff');
        $opsResponse->assertSee('Manage monthly salon staff evaluations and comprehensive branch assessments.');
        $opsResponse->assertDontSee('Marketing & Content Operations');
        $opsResponse->assertDontSee('Customer Service & QA Operations');
        $opsResponse->assertDontSee('Salon Staff Operations');

        $aliasOpsResponse = $this->actingAs($this->admin)->get(route('kpis.operations'));
        $aliasOpsResponse->assertOk();

        // 7. Verify GET /kpis/salon-staff shows only Salon Staff Evaluation & Branch Evaluation (no Staff Sales Performance)
        $salonStaffResponse = $this->actingAs($this->admin)->get(route('kpi.salon-staff.index'));
        $salonStaffResponse->assertOk();
        $salonStaffResponse->assertSee('Manage monthly salon staff evaluations and comprehensive branch assessments.');
        $salonStaffResponse->assertSee('Salon Staff Evaluation');
        $salonStaffResponse->assertSee('Branch Evaluation');
        $salonStaffResponse->assertDontSee('Staff Sales Performance');
    }

    public function test_all_nine_boss_approved_kpi_and_operations_fixes(): void
    {
        $now = now();

        // Fix #1 & #2: Agent booking created today for next month's booking_date counts ONCE today by created_at,
        // and KpiAgentTargetReport credits bookings even without shift clock-out.
        $areeba = User::factory()->create([
            'name' => 'Areeba Khan',
            'email' => 'areeba.fix@laleen.qa',
            'role' => 'agent',
            'is_active' => true,
        ]);

        $futureAppt = Appointment::create([
            'customer_name' => 'Future Visit Client',
            'phone' => '+97455770001',
            'service_name' => 'Facial',
            'appointment_datetime' => $now->copy()->addMonth()->setTime(14, 0),
            'booking_date' => $now->copy()->addMonth()->toDateString(),
            'branch' => 'old_airport',
            'booking_agent_id' => $areeba->id,
            'created_by' => $areeba->id,
            'status' => 'confirmed',
            'price' => 300,
        ]);
        $futureAppt->forceFill(['created_at' => $now])->saveQuietly();

        $report = new \App\Models\KpiAgentTargetReport([
            'date_from' => $now->copy()->startOfDay(),
            'date_to' => $now->copy()->endOfDay(),
        ]);
        $this->assertSame(1, $report->combined()['bookings']);

        $liveAgentCards = \App\Models\KpiAgentTargetReport::liveAgentScorecards();
        $areebaLive = collect($liveAgentCards)->firstWhere('id', $areeba->id);
        $this->assertNotNull($areebaLive);
        $this->assertSame(1, $areebaLive['today_bookings']);
        $this->assertSame(1, $areebaLive['mtd_bookings']);

        // Fix #4: Paid Ad Lead created in Core CRM (/leads with source = Instagram) + standalone POS Sale auto-creates & updates AdLeadEntry
        $product = \App\Models\Product::create([
            'name' => 'Luxury Hair Mask',
            'price' => 250,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->post(route('leads.store'), [
                'country_code' => '974',
                'phone_number' => '55881122',
                'customer_name' => 'Instagram Ad Client',
                'category' => 'inquiry',
                'source' => 'Instagram',
                'service_interest' => ['Hair Treatment'],
                'next_followup_date' => $now->toDateString(),
                'customer_remarks' => 'Instagram Ad inquiry',
            ])
            ->assertRedirect();

        $autoEntry = AdLeadEntry::where('phone', '55881122')->first();
        $this->assertNotNull($autoEntry, 'Paid Ad Lead in Core CRM must auto-create AdLeadEntry');
        $this->assertFalse($autoEntry->isBooked(), 'Unbooked AdLeadEntry with 0 ticket_amount must not count as booked');

        // Even if branch is set with ticket_amount = 0 and not booked, isBooked() must be false
        $unbookedWithBranch = AdLeadEntry::create([
            'date' => $now->toDateString(),
            'customer_name' => 'Zero Ticket Inquiry',
            'phone' => '55889900',
            'category' => 'Facial',
            'branch' => 'old_airport',
            'ticket_amount' => 0,
        ]);
        $this->assertFalse($unbookedWithBranch->isBooked());

        // Standalone Sale via SaleController::store() syncs revenue & branch to the Paid Ad Lead's AdLeadEntry
        $this->actingAs($this->admin)
            ->post(route('sales.store'), [
                'customer_id' => $autoEntry->customer_id,
                'branch' => 'wakrah',
                'products' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
                'payments' => ['card' => 500],
            ])
            ->assertRedirect(route('appointments.revenue.index'));

        $autoEntry->refresh();
        $this->assertTrue($autoEntry->isBooked());
        $this->assertSame('wakrah', $autoEntry->branch);
        $this->assertSame(500.0, (float) $autoEntry->ticket_amount);

        // Fix #7: Overdue follow-up lead with category = 'cancel' is excluded from Laden's overdue_followups_count
        Lead::create([
            'phone' => '55990011',
            'category' => 'cancel',
            'categories' => ['cancel'],
            'next_followup_date' => $now->copy()->subDays(2)->toDateString(),
            'needful_done' => null,
        ]);
        Lead::create([
            'phone' => '55990022',
            'category' => 'inquiry',
            'categories' => ['inquiry'],
            'next_followup_date' => $now->copy()->subDays(2)->toDateString(),
            'needful_done' => null,
        ]);

        // Fix #8: Complaint resolved within 4h keeps <= 48h SLA pass even if updated_at is modified 5 days later
        $complaint = StaffComplaint::create([
            'complaint_date' => $now->toDateString(),
            'branch' => 'old_airport',
            'customer_name' => 'SLA Protected Client',
            'customer_phone' => '55443322',
            'category' => 'Service Quality',
            'description' => 'Resolved quickly',
            'what_happened' => 'Resolved quickly',
            'is_staff_mistake' => false,
        ]);
        $complaint->forceFill([
            'created_at' => $now->copy()->subHours(10),
            'resolved_at' => $now->copy()->subHours(6), // resolved in 4 hours (<= 48h)
            'updated_at' => $now->copy()->addDays(5),   // edited 5 days later
        ])->saveQuietly();

        $scorecard = $this->actingAs($this->admin)
            ->get(route('kpi.hub', ['month' => $now->format('Y-m')]))
            ->viewData('scorecard');

        $this->assertSame(1, $scorecard['laden']['overdue_followups_count'], 'Cancelled lead must be excluded from overdue follow-ups count');
        $this->assertSame(1, $scorecard['laden']['resolved_within_48h_count'], 'resolved_at must protect complaint 48h SLA from later note edits');
        $this->assertTrue($scorecard['laden']['complaint_passing']);
    }
}
