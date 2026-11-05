<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\BranchEvaluation;
use App\Models\KpiAdsConversionReport;
use App\Models\KpiAgentTargetReport;
use App\Models\KpiChatEvaluation;
use App\Models\KpiMonthlyAd;
use App\Models\KpiStaffEvaluation;
use App\Models\Lead;
use App\Models\SocialContentPlan;
use App\Models\Staff;
use App\Models\StaffComplaint;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class KpiHubController extends Controller
{
    public const AYAN_BRANCH_AD_REVENUE_TARGET_QAR = 15000.0;
    public const AYAN_COMBINED_AD_REVENUE_TARGET_QAR = 30000.0;
    public const AYAN_AD_CONVERSION_TARGET_PCT = 20.0;

    public const AGENT_DAILY_BOOKING_QUOTA = 13;
    public const LADEN_DAILY_BOOKING_QUOTA = 26;
    public const LADEN_CHAT_EVAL_TARGET_PCT = 85.0;
    public const LADEN_LEAD_CONVERSION_TARGET_PCT = 30.0;
    public const LADEN_COMPLAINT_SLA_HOURS = 48;

    public const SANA_WEEKLY_REELS_TARGET = 4;
    public const SANA_WEEKLY_STORIES_TARGET = 7;

    public const BRANCH_STAFF_EVAL_COMPLIANCE_TARGET_PCT = 100.0;
    public const BRANCH_MYSTERY_SHOPPER_TARGET_PCT = 85.0;

    public function index(Request $request)
    {
        $rawMonth = trim((string) $request->query('month', now()->format('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
            $rawMonth = now()->format('Y-m');
        }

        $monthStart = Carbon::createFromFormat('Y-m-d', $rawMonth . '-01')->startOfMonth()->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
        $isCurrentMonth = ($rawMonth === now()->format('Y-m'));

        $scorecard = $this->buildExecutiveScorecard($rawMonth, $monthStart, $monthEnd, $isCurrentMonth);

        return view('kpi.hub', [
            'selectedMonth' => $rawMonth,
            'selectedMonthLabel' => $monthStart->format('F Y'),
            'periodRangeLabel' => $scorecard['period_range_label'],
            'shortPeriodBadge' => $scorecard['short_period_badge'],
            'daysElapsed' => $scorecard['days_elapsed'],
            'daysInMonth' => $scorecard['days_in_month'],
            'scorecard' => $scorecard,
        ]);
    }

    public function updateLateReplies(Request $request)
    {
        $validated = $request->validate([
            'action' => 'nullable|in:increment,decrement,set',
            'count' => 'nullable|integer|min:0|max:999',
            'week_start_date' => 'nullable|date_format:Y-m-d',
            'month' => 'nullable|string|max:7',
        ]);

        $weekStart = $validated['week_start_date']
            ?? now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $mondayPlan = SocialContentPlan::firstOrCreate(
            [
                'week_start_date' => $weekStart,
                'day_of_week' => 'Monday',
            ],
            [
                'target_branch' => 'All Branches',
                'is_reel_posted' => false,
                'are_stories_posted' => false,
                'is_reel_skipped' => false,
                'late_replies_count' => 0,
            ]
        );

        $action = $validated['action'] ?? 'increment';

        if ($action === 'set' && isset($validated['count'])) {
            // Reset other days of the same week to 0 and set Monday's counter cleanly
            SocialContentPlan::where('week_start_date', $weekStart)
                ->where('id', '!=', $mondayPlan->id)
                ->update(['late_replies_count' => 0]);
            $mondayPlan->update(['late_replies_count' => max(0, (int) $validated['count'])]);
        } elseif ($action === 'decrement') {
            $weekRows = SocialContentPlan::where('week_start_date', $weekStart)
                ->where('late_replies_count', '>', 0)
                ->orderByDesc('late_replies_count')
                ->first();
            if ($weekRows) {
                $weekRows->update([
                    'late_replies_count' => max(0, (int) $weekRows->late_replies_count - 1),
                ]);
            }
        } else {
            $mondayPlan->update([
                'late_replies_count' => (int) $mondayPlan->late_replies_count + 1,
            ]);
        }

        $totalWeekLateReplies = (int) SocialContentPlan::where('week_start_date', $weekStart)->sum('late_replies_count');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'week_start_date' => $weekStart,
                'late_replies_count' => $totalWeekLateReplies,
            ]);
        }

        $redirectParams = [];
        if (!empty($validated['month'])) {
            $redirectParams['month'] = $validated['month'];
        }

        return redirect()
            ->route('kpi.hub', $redirectParams)
            ->with('success', "Social media late replies counter updated ({$totalWeekLateReplies} reported).");
    }

    public function buildExecutiveScorecard(string $selectedMonth, Carbon $monthStart, Carbon $monthEnd, bool $isCurrentMonth): array
    {
        // =========================================================================
        // DYNAMIC MONTH-TO-DATE WINDOW (01 – CURRENT DAY)
        // =========================================================================
        $periodStart = $monthStart->copy()->startOfDay();
        $periodEnd = $isCurrentMonth ? now()->copy()->endOfDay() : $monthEnd->copy()->endOfDay();
        $daysInMonth = max(1, (int) $monthStart->daysInMonth);
        $daysElapsed = $isCurrentMonth ? max(1, (int) now()->day) : $daysInMonth;

        $periodRangeLabel = $isCurrentMonth
            ? sprintf('%s – %s (MTD • Day %d/%d)', $periodStart->format('M j'), $periodEnd->format('M j, Y'), $daysElapsed, $daysInMonth)
            : sprintf('%s – %s', $periodStart->format('M j'), $periodEnd->format('M j, Y'));

        $shortPeriodBadge = sprintf('%s – %s', $periodStart->format('M j'), $periodEnd->format('M j'));

        // =========================================================================
        // C. SANA — Organic Content & Social Media Replies (01 – Current Day)
        // =========================================================================
        $currentWeekMonday = $isCurrentMonth
            ? now()->startOfWeek(Carbon::MONDAY)->toDateString()
            : $monthStart->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        $weekPlans = SocialContentPlan::query()
            ->whereDate('week_start_date', $currentWeekMonday)
            ->get();

        // If viewing a historical month where a later week in that month has data, prefer the most recent populated week in that month
        if (!$isCurrentMonth && $weekPlans->isEmpty()) {
            $latestWeekInMonth = SocialContentPlan::query()
                ->whereBetween('week_start_date', [$periodStart, $periodEnd])
                ->max('week_start_date');
            if ($latestWeekInMonth) {
                $currentWeekMonday = Carbon::parse($latestWeekInMonth)->toDateString();
                $weekPlans = SocialContentPlan::whereDate('week_start_date', $currentWeekMonday)->get();
            }
        }

        $publishedReels = $weekPlans->where('is_reel_posted', true)->count();
        $publishedStories = $weekPlans->where('are_stories_posted', true)->count();
        $weeklyItemsCompleted = $publishedReels + $publishedStories;
        $weeklyItemsTarget = self::SANA_WEEKLY_REELS_TARGET + self::SANA_WEEKLY_STORIES_TARGET; // 11

        $monthContentPlans = SocialContentPlan::query()
            ->whereBetween('week_start_date', [
                $periodStart->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                $periodEnd,
            ])
            ->get();

        $mtdPublishedReels = $monthContentPlans->where('is_reel_posted', true)->count();
        $mtdPublishedStories = $monthContentPlans->where('are_stories_posted', true)->count();
        $mtdItemsCompleted = $mtdPublishedReels + $mtdPublishedStories;

        // Prorate the active week's target by elapsed days in the current week (1=Mon .. 7=Sun)
        // so Monday/Tuesday mornings reflect true MTD/weekly pace rather than crashing to 0% FAIL.
        $dayOfWeekIso = $isCurrentMonth ? (int) now()->dayOfWeekIso : 7;
        $proratedReelsTarget = $isCurrentMonth
            ? (int) floor((self::SANA_WEEKLY_REELS_TARGET * $dayOfWeekIso) / 7)
            : self::SANA_WEEKLY_REELS_TARGET;
        $proratedStoriesTarget = $isCurrentMonth
            ? (int) floor((self::SANA_WEEKLY_STORIES_TARGET * $dayOfWeekIso) / 7)
            : self::SANA_WEEKLY_STORIES_TARGET;
        $proratedWeeklyItemsTarget = $isCurrentMonth
            ? max(1, $proratedReelsTarget + $proratedStoriesTarget)
            : $weeklyItemsTarget;

        $effectiveItemsCompleted = max($weeklyItemsCompleted, $mtdItemsCompleted);
        $weeklyContentExecutionPct = round(min(100.0, ($effectiveItemsCompleted / $proratedWeeklyItemsTarget) * 100), 1);

        $weekLateReplies = (int) $weekPlans->sum('late_replies_count');
        $monthLateReplies = (int) $monthContentPlans->sum('late_replies_count');
        $effectiveLateReplies = max($weekLateReplies, $monthLateReplies);

        $sanaContentPassing = ($publishedReels >= self::SANA_WEEKLY_REELS_TARGET && $publishedStories >= self::SANA_WEEKLY_STORIES_TARGET)
            || ($isCurrentMonth && $publishedReels >= $proratedReelsTarget && $publishedStories >= $proratedStoriesTarget && $effectiveItemsCompleted >= $proratedWeeklyItemsTarget);
        $sanaRepliesPassing = ($effectiveLateReplies === 0);
        $sanaRepliesScorePct = $sanaRepliesPassing ? 100.0 : 0.0;
        $sanaOverallScorePct = round(($weeklyContentExecutionPct + $sanaRepliesScorePct) / 2, 1);
        $sanaPassingCount = ($sanaContentPassing ? 1 : 0) + ($sanaRepliesPassing ? 1 : 0);
        $sanaTotalKpis = 2;
        $sanaFailingCount = $sanaTotalKpis - $sanaPassingCount;

        $sanaData = [
            'owner' => 'Sana',
            'role' => 'Organic Content & Social Media Replies',
            'week_start_date' => $currentWeekMonday,
            'period_badge' => $shortPeriodBadge,
            'published_reels' => $publishedReels,
            'reels_target' => self::SANA_WEEKLY_REELS_TARGET,
            'prorated_reels_target' => $proratedReelsTarget,
            'published_stories' => $publishedStories,
            'stories_target' => self::SANA_WEEKLY_STORIES_TARGET,
            'prorated_stories_target' => $proratedStoriesTarget,
            'prorated_weekly_target' => $proratedWeeklyItemsTarget,
            'weekly_execution_pct' => $weeklyContentExecutionPct,
            'content_passing' => $sanaContentPassing,
            'mtd_published_reels' => $mtdPublishedReels,
            'mtd_published_stories' => $mtdPublishedStories,
            'late_replies_count' => $effectiveLateReplies,
            'replies_sla_passing' => $sanaRepliesPassing,
            'replies_sla_status_label' => $sanaRepliesPassing
                ? '100% SLA PASS (0 Late Replies)'
                : "SLA BREACH ({$effectiveLateReplies} Reported Late " . ($effectiveLateReplies === 1 ? 'Reply' : 'Replies') . ')',
            'overall_score_pct' => $sanaOverallScorePct,
            'passing_kpis_count' => $sanaPassingCount,
            'total_kpis_count' => $sanaTotalKpis,
            'below_floor_count' => $sanaFailingCount,
            'is_passing_all' => $sanaFailingCount === 0,
        ];

        // =========================================================================
        // A. AYAN — Marketing, Ads Revenue & Content Oversight (01 – Current Day)
        // =========================================================================
        $monthAdLeads = AdLeadEntry::query()
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->get(['id', 'branch', 'ticket_amount', 'category', 'lead_id']);

        $oaAdLeads = $monthAdLeads->where('branch', 'old_airport');
        $wkAdLeads = $monthAdLeads->where('branch', 'wakrah');
        $oaBookedAdLeads = $oaAdLeads->filter(fn (AdLeadEntry $e) => $e->isBooked());
        $wkBookedAdLeads = $wkAdLeads->filter(fn (AdLeadEntry $e) => $e->isBooked());

        $oaAdRevenueQar = round((float) $oaAdLeads->sum('ticket_amount'), 2);
        $wkAdRevenueQar = round((float) $wkAdLeads->sum('ticket_amount'), 2);
        $totalAdRevenueQar = round($oaAdRevenueQar + $wkAdRevenueQar, 2);

        $dailyBranchAdTarget = round(self::AYAN_BRANCH_AD_REVENUE_TARGET_QAR / $daysInMonth, 2);
        $mtdBranchAdTarget = round((self::AYAN_BRANCH_AD_REVENUE_TARGET_QAR / $daysInMonth) * $daysElapsed, 0);
        $mtdCombinedAdTarget = round($mtdBranchAdTarget * 2, 0);

        $oaAdTargetPct = round(min(100.0, ($oaAdRevenueQar / self::AYAN_BRANCH_AD_REVENUE_TARGET_QAR) * 100), 1);
        $wkAdTargetPct = round(min(100.0, ($wkAdRevenueQar / self::AYAN_BRANCH_AD_REVENUE_TARGET_QAR) * 100), 1);
        $combinedAdTargetPct = round(min(100.0, ($totalAdRevenueQar / self::AYAN_COMBINED_AD_REVENUE_TARGET_QAR) * 100), 1);

        $oaAdMtdPct = min(100.0, round(($oaAdRevenueQar / max(1.0, $mtdBranchAdTarget)) * 100, 1));
        $wkAdMtdPct = min(100.0, round(($wkAdRevenueQar / max(1.0, $mtdBranchAdTarget)) * 100, 1));
        $combinedAdMtdPct = min(100.0, round(($totalAdRevenueQar / max(1.0, $mtdCombinedAdTarget)) * 100, 1));

        $totalAdLeadsCount = $monthAdLeads->count();
        $bookedAdLeadsCount = $monthAdLeads->filter(fn (AdLeadEntry $e) => $e->isBooked())->count();
        $adConversionPct = $totalAdLeadsCount > 0 ? round(($bookedAdLeadsCount / $totalAdLeadsCount) * 100, 1) : 0.0;
        $adConversionAchievementPct = round(min(100.0, ($adConversionPct / self::AYAN_AD_CONVERSION_TARGET_PCT) * 100), 1);

        $monthlyAds = KpiMonthlyAd::query()
            ->where('plan_month', $selectedMonth)
            ->get(['id', 'phase', 'total_budget', 'target_branch']);

        $scalingAdsCount = $monthlyAds->where('phase', 'Scaling')->count();
        $testingAdsCount = $monthlyAds->where('phase', 'Testing')->count();

        // Ayan KPI 3: Reflects Sana's Organic Content + Social Replies SLA
        $ayanKpi3Pct = $sanaOverallScorePct;
        $ayanKpi3Passing = ($sanaFailingCount === 0);

        $oaRevPassing = ($oaAdRevenueQar >= $mtdBranchAdTarget);
        $wkRevPassing = ($wkAdRevenueQar >= $mtdBranchAdTarget);
        $ayanConvPassing = ($adConversionPct >= self::AYAN_AD_CONVERSION_TARGET_PCT);

        $ayanPassingCount = ($oaRevPassing ? 1 : 0) + ($wkRevPassing ? 1 : 0) + ($ayanConvPassing ? 1 : 0) + ($ayanKpi3Passing ? 1 : 0);
        $ayanTotalKpis = 4;
        $ayanFailingCount = $ayanTotalKpis - $ayanPassingCount;
        $ayanOverallScorePct = round(($oaAdMtdPct + $wkAdMtdPct + $adConversionAchievementPct + $ayanKpi3Pct) / 4, 1);

        $ayanData = [
            'owner' => 'Ayan',
            'role' => 'Marketing, Ads Revenue & Content Oversight',
            'branch_target_qar' => self::AYAN_BRANCH_AD_REVENUE_TARGET_QAR,
            'combined_target_qar' => self::AYAN_COMBINED_AD_REVENUE_TARGET_QAR,
            'daily_branch_target_qar' => $dailyBranchAdTarget,
            'mtd_branch_target_qar' => $mtdBranchAdTarget,
            'mtd_combined_target_qar' => $mtdCombinedAdTarget,
            'old_airport_ad_revenue_qar' => $oaAdRevenueQar,
            'old_airport_ad_bookings' => $oaBookedAdLeads->count(),
            'old_airport_target_pct' => $oaAdTargetPct,
            'old_airport_mtd_pct' => $oaAdMtdPct,
            'old_airport_mtd_passing' => $oaRevPassing,
            'wakrah_ad_revenue_qar' => $wkAdRevenueQar,
            'wakrah_ad_bookings' => $wkBookedAdLeads->count(),
            'wakrah_target_pct' => $wkAdTargetPct,
            'wakrah_mtd_pct' => $wkAdMtdPct,
            'wakrah_mtd_passing' => $wkRevPassing,
            'total_ad_revenue_qar' => $totalAdRevenueQar,
            'combined_target_pct' => $combinedAdTargetPct,
            'combined_mtd_pct' => $combinedAdMtdPct,
            'total_ad_leads' => $totalAdLeadsCount,
            'booked_ad_leads' => $bookedAdLeadsCount,
            'ad_conversion_pct' => $adConversionPct,
            'ad_conversion_target_pct' => self::AYAN_AD_CONVERSION_TARGET_PCT,
            'ad_conversion_achievement_pct' => $adConversionAchievementPct,
            'monthly_ads_total' => $monthlyAds->count(),
            'scaling_ads_count' => $scalingAdsCount,
            'testing_ads_count' => $testingAdsCount,
            'social_oversight_pct' => $ayanKpi3Pct,
            'social_late_replies_count' => $effectiveLateReplies,
            'social_oversight_passing' => $ayanKpi3Passing,
            'overall_score_pct' => $ayanOverallScorePct,
            'passing_kpis_count' => $ayanPassingCount,
            'total_kpis_count' => $ayanTotalKpis,
            'below_floor_count' => $ayanFailingCount,
            'is_passing_all' => $ayanFailingCount === 0,
        ];

        // =========================================================================
        // B. LADEN — Customer Service, Bookings, Chat Eval & Complaint SLA (01 – Current Day)
        // =========================================================================
        // KPI 1: Daily Booking Quota (26 Bookings / Day = 13/Day x 2 Chat Agents)
        $referenceDate = $isCurrentMonth ? now()->copy() : $monthEnd->copy();

        $todayBookingsCount = Appointment::query()
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereDate('created_at', $referenceDate->toDateString())
            ->count();

        $mtdBookingsCount = Appointment::query()
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->count();

        $dailyTarget = self::LADEN_DAILY_BOOKING_QUOTA; // 26 (13/day x 2 Chat Agents)
        $fullMonthTargetBookings = $dailyTarget * $daysInMonth;
        $mtdTargetBookings = $dailyTarget * $daysElapsed;
        $todayBookingQuotaPct = round(min(100.0, ($todayBookingsCount / $dailyTarget) * 100), 1);
        $mtdBookingQuotaPct = min(100.0, round(($mtdBookingsCount / max(1, $mtdTargetBookings)) * 100, 1));
        $ladenBookingPassing = ($mtdBookingsCount >= $mtdTargetBookings || $todayBookingsCount >= $dailyTarget);
        $bookingStatusLabel = ($mtdBookingQuotaPct >= 100.0 || $todayBookingQuotaPct >= 100.0)
            ? 'ON TARGET'
            : (($mtdBookingQuotaPct >= 85.0 || $todayBookingQuotaPct >= 85.0) ? 'NEAR TARGET' : 'BELOW QUOTA');

        // KPI 2: Agent Chat Evaluation Score (Target: >= 85%)
        $monthChatEvals = KpiChatEvaluation::query()
            ->whereBetween('evaluation_date', [$periodStart, $periodEnd])
            ->get(['id', 'agent_id', 'percentage', 'total_score']);

        $chatEvalsCount = $monthChatEvals->count();
        $chatEvalAvgPct = $chatEvalsCount > 0
            ? round((float) $monthChatEvals->avg('percentage'), 1)
            : 0.0;
        $ladenChatPassing = ($chatEvalsCount > 0 && $chatEvalAvgPct >= self::LADEN_CHAT_EVAL_TARGET_PCT);
        $chatEvalAchievementPct = round(min(100.0, ($chatEvalAvgPct / self::LADEN_CHAT_EVAL_TARGET_PCT) * 100), 1);
        $chatStatusLabel = $ladenChatPassing ? 'PASS' : 'BELOW TARGET';

        // KPI 3: Lead Management & Conversion Rate (Target: >= 30%)
        $monthLeads = Lead::query()
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get(['id', 'needful_done', 'assigned_agent_id', 'next_followup_date']);

        $totalLeadsCount = $monthLeads->count();
        $bookedLeadsCount = $monthLeads->where('needful_done', 'booked')->count();
        $leadConversionPct = $totalLeadsCount > 0
            ? round(($bookedLeadsCount / $totalLeadsCount) * 100, 1)
            : 0.0;
        $ladenLeadConvPassing = ($totalLeadsCount > 0 && $leadConversionPct >= self::LADEN_LEAD_CONVERSION_TARGET_PCT);
        $leadConvAchievementPct = round(min(100.0, ($leadConversionPct / self::LADEN_LEAD_CONVERSION_TARGET_PCT) * 100), 1);
        $leadStatusLabel = $ladenLeadConvPassing ? 'ON TRACK' : 'BELOW FLOOR';

        $openPipelineLeads = Lead::query()
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
            })
            ->where(function ($q) {
                $q->whereNull('category')->orWhere('category', '!=', 'cancel');
            })
            ->get(['id', 'assigned_agent_id', 'next_followup_date']);

        $unassignedLeadsCount = $openPipelineLeads->whereNull('assigned_agent_id')->count();
        $overdueFollowupsCount = Lead::query()
            ->whereNotNull('next_followup_date')
            ->whereDate('next_followup_date', '<', today())
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
            })
            ->where(function ($q) {
                $q->whereNull('category')->orWhere('category', '!=', 'cancel');
            })
            ->count();

        // KPI 4: Complaint Resolution SLA (<= 48 Hours)
        $monthComplaints = StaffComplaint::with('notices')
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('complaint_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                  ->orWhereBetween('created_at', [$periodStart, $periodEnd]);
            })
            ->get();

        $totalComplaintsCount = $monthComplaints->count();
        $resolvedWithin48hCount = 0;
        $openComplaintsCount = 0;
        $breachingOpenCount = 0;
        $oldestOpenAgeHours = 0;

        foreach ($monthComplaints as $complaint) {
            $createdAt = $complaint->created_at ? Carbon::parse($complaint->created_at) : Carbon::parse($complaint->complaint_date);
            $isResolved = ($complaint->is_staff_mistake !== null || $complaint->notices->isNotEmpty());

            if ($isResolved) {
                $resolvedAt = $complaint->resolved_at
                    ? Carbon::parse($complaint->resolved_at)
                    : ($complaint->updated_at ? Carbon::parse($complaint->updated_at) : $createdAt);
                $resolutionHours = max(0.0, $createdAt->diffInMinutes($resolvedAt, false) / 60.0);
                if ($resolutionHours <= self::LADEN_COMPLAINT_SLA_HOURS) {
                    $resolvedWithin48hCount++;
                }
            } else {
                $openComplaintsCount++;
                $ageHours = (int) max(0, floor($createdAt->diffInMinutes(now(), false) / 60));
                if ($ageHours > $oldestOpenAgeHours) {
                    $oldestOpenAgeHours = $ageHours;
                }
                if ($ageHours > self::LADEN_COMPLAINT_SLA_HOURS) {
                    $breachingOpenCount++;
                }
            }
        }

        $complaintSlaPct = $totalComplaintsCount > 0
            ? round(($resolvedWithin48hCount / $totalComplaintsCount) * 100, 1)
            : 100.0;

        $ladenComplaintPassing = ($totalComplaintsCount === 0 || ($resolvedWithin48hCount === $totalComplaintsCount && $breachingOpenCount === 0));

        $ladenPassingCount = ($ladenBookingPassing ? 1 : 0)
            + ($ladenChatPassing ? 1 : 0)
            + ($ladenLeadConvPassing ? 1 : 0)
            + ($ladenComplaintPassing ? 1 : 0);
        $ladenTotalKpis = 4;
        $ladenFailingCount = $ladenTotalKpis - $ladenPassingCount;

        $ladenOverallScorePct = round((
            $mtdBookingQuotaPct +
            $chatEvalAchievementPct +
            $leadConvAchievementPct +
            $complaintSlaPct
        ) / 4, 1);

        $ladenData = [
            'owner' => 'Laden',
            'role' => 'Customer Service, Bookings, Chat Quality & Complaints SLA',
            'daily_booking_target' => $dailyTarget,
            'today_bookings_count' => $todayBookingsCount,
            'today_booking_quota_pct' => $todayBookingQuotaPct,
            'mtd_bookings_count' => $mtdBookingsCount,
            'mtd_target_bookings' => $mtdTargetBookings,
            'full_month_target_bookings' => $fullMonthTargetBookings,
            'mtd_booking_quota_pct' => $mtdBookingQuotaPct,
            'booking_status_label' => $bookingStatusLabel,
            'booking_passing' => $ladenBookingPassing,
            'chat_eval_target_pct' => self::LADEN_CHAT_EVAL_TARGET_PCT,
            'chat_evals_count' => $chatEvalsCount,
            'total_evaluations_count' => $chatEvalsCount,
            'chat_eval_avg_pct' => $chatEvalAvgPct,
            'average_score_percent' => $chatEvalAvgPct,
            'chat_eval_achievement_pct' => $chatEvalAchievementPct,
            'chat_status_label' => $chatStatusLabel,
            'chat_passing' => $ladenChatPassing,
            'lead_conversion_target_pct' => self::LADEN_LEAD_CONVERSION_TARGET_PCT,
            'total_leads_count' => $totalLeadsCount,
            'booked_leads_count' => $bookedLeadsCount,
            'lead_conversion_pct' => $leadConversionPct,
            'lead_conv_achievement_pct' => $leadConvAchievementPct,
            'lead_status_label' => $leadStatusLabel,
            'lead_conv_passing' => $ladenLeadConvPassing,
            'unassigned_leads_count' => $unassignedLeadsCount,
            'overdue_followups_count' => $overdueFollowupsCount,
            'complaint_sla_hours' => self::LADEN_COMPLAINT_SLA_HOURS,
            'total_complaints_count' => $totalComplaintsCount,
            'resolved_within_48h_count' => $resolvedWithin48hCount,
            'open_complaints_count' => $openComplaintsCount,
            'breaching_open_count' => $breachingOpenCount,
            'oldest_open_age_hours' => $oldestOpenAgeHours,
            'complaint_sla_pct' => $complaintSlaPct,
            'complaint_sla_label' => $totalComplaintsCount === 0
                ? '100% — 0 Complaints'
                : "{$resolvedWithin48hCount} / {$totalComplaintsCount} Resolved <= 48h ({$complaintSlaPct}%)",
            'complaint_passing' => $ladenComplaintPassing,
            'overall_score_pct' => $ladenOverallScorePct,
            'passing_kpis_count' => $ladenPassingCount,
            'total_kpis_count' => $ladenTotalKpis,
            'below_floor_count' => $ladenFailingCount,
            'is_passing_all' => $ladenFailingCount === 0,
        ];

        // =========================================================================
        // D. AHANA (Old Airport) & RHEA (Wakrah) — Branch Managers (01 – Current Day)
        // =========================================================================
        $mondaysElapsedCount = 0;
        foreach (CarbonPeriod::create($periodStart->copy()->startOfDay(), $periodEnd->copy()->startOfDay()) as $day) {
            if ($day->isMonday()) {
                $mondaysElapsedCount++;
            }
        }

        $ahanaData = $this->buildBranchManagerScorecard(
            owner: 'Ahana',
            role: 'Old Airport Branch Manager',
            branch: 'old_airport',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            mondaysElapsedCount: $mondaysElapsedCount
        );

        $rheaData = $this->buildBranchManagerScorecard(
            owner: 'Rhea',
            role: 'Wakrah Branch Manager',
            branch: 'wakrah',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            mondaysElapsedCount: $mondaysElapsedCount
        );

        // =========================================================================
        // E. AREEBA & ZOYA — Customer Service Chat Agents (13 Bookings / Day)
        // =========================================================================
        $areebaData = $this->buildChatAgentScorecard(
            owner: 'Areeba',
            role: 'Customer Service Chat Agent',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            referenceDate: $referenceDate,
            daysElapsed: $daysElapsed,
            daysInMonth: $daysInMonth
        );

        $zoyaData = $this->buildChatAgentScorecard(
            owner: 'Zoya',
            role: 'Customer Service Chat Agent',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            referenceDate: $referenceDate,
            daysElapsed: $daysElapsed,
            daysInMonth: $daysInMonth
        );

        return [
            'month' => $selectedMonth,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'period_range_label' => $periodRangeLabel,
            'short_period_badge' => $shortPeriodBadge,
            'days_elapsed' => $daysElapsed,
            'days_in_month' => $daysInMonth,
            'mondays_elapsed_count' => $mondaysElapsedCount,
            'ayan' => $ayanData,
            'laden' => $ladenData,
            'sana' => $sanaData,
            'ahana' => $ahanaData,
            'rhea' => $rheaData,
            'areeba' => $areebaData,
            'zoya' => $zoyaData,
        ];
    }

    private function buildChatAgentScorecard(
        string $owner,
        string $role,
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $referenceDate,
        int $daysElapsed,
        int $daysInMonth
    ): array {
        $agentUserIds = User::query()
            ->where('name', 'like', '%' . $owner . '%')
            ->pluck('id');

        $dailyTarget = self::AGENT_DAILY_BOOKING_QUOTA; // 13 bookings/day
        $agentMtdTarget = $dailyTarget * $daysElapsed;
        $agentFullMonthTarget = $dailyTarget * $daysInMonth;

        $todayBookings = 0;
        $mtdBookings = 0;
        $chatEvalCount = 0;
        $chatEvalAvgPct = 0.0;

        if ($agentUserIds->isNotEmpty()) {
            $todayBookings = Appointment::query()
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($agentUserIds) {
                    $q->whereIn('booking_agent_id', $agentUserIds)
                      ->orWhereIn('created_by', $agentUserIds);
                })
                ->whereDate('created_at', $referenceDate->toDateString())
                ->count();

            $mtdBookings = Appointment::query()
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($agentUserIds) {
                    $q->whereIn('booking_agent_id', $agentUserIds)
                      ->orWhereIn('created_by', $agentUserIds);
                })
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->count();

            $evals = KpiChatEvaluation::query()
                ->whereIn('agent_id', $agentUserIds)
                ->whereBetween('evaluation_date', [$periodStart, $periodEnd])
                ->get(['id', 'percentage']);

            $chatEvalCount = $evals->count();
            $chatEvalAvgPct = $chatEvalCount > 0
                ? round((float) $evals->avg('percentage'), 1)
                : 0.0;
        }

        $todayAttainmentPct = min(100.0, round(($todayBookings / $dailyTarget) * 100, 1));
        $mtdAttainmentPct = min(100.0, round(($mtdBookings / max(1, $agentMtdTarget)) * 100, 1));
        $isPassing = ($mtdBookings >= $agentMtdTarget || $mtdAttainmentPct >= 85.0 || $todayBookings >= $dailyTarget);
        $chatEvalPassing = ($chatEvalCount > 0 && $chatEvalAvgPct >= self::LADEN_CHAT_EVAL_TARGET_PCT);

        return [
            'owner' => $owner,
            'role' => $role,
            'daily_target' => $dailyTarget,
            'today_bookings' => $todayBookings,
            'today_attainment_pct' => $todayAttainmentPct,
            'today_remaining_needed' => max(0, $dailyTarget - $todayBookings),
            'mtd_bookings' => $mtdBookings,
            'mtd_target' => $agentMtdTarget,
            'full_month_target' => $agentFullMonthTarget,
            'mtd_attainment_pct' => $mtdAttainmentPct,
            'booking_passing' => $isPassing,
            'chat_eval_count' => $chatEvalCount,
            'chat_eval_avg_pct' => $chatEvalAvgPct,
            'chat_eval_passing' => $chatEvalPassing,
            'overall_score_pct' => $mtdAttainmentPct,
            'passing_kpis_count' => $isPassing ? 1 : 0,
            'total_kpis_count' => 1,
            'below_floor_count' => $isPassing ? 0 : 1,
            'is_passing_all' => $isPassing,
        ];
    }

    private function buildBranchManagerScorecard(
        string $owner,
        string $role,
        string $branch,
        Carbon $periodStart,
        Carbon $periodEnd,
        int $mondaysElapsedCount
    ): array {
        // KPI 1: Monday Staff Evaluation Compliance (Target: 100%)
        $activeBranchStaffCount = Staff::active()
            ->whereIn('branch', [$branch, 'both'])
            ->count();

        $expectedEvaluationsMtd = $activeBranchStaffCount * $mondaysElapsedCount;

        $evalRows = KpiStaffEvaluation::query()
            ->where('branch', $branch)
            ->whereBetween('evaluation_date', [$periodStart, $periodEnd])
            ->get(['staff_id', 'evaluation_date']);

        $completedEvaluationsMtd = $evalRows
            ->unique(fn (KpiStaffEvaluation $e) => $e->staff_id . '|' . Carbon::parse($e->evaluation_date)->startOfWeek(Carbon::MONDAY)->toDateString())
            ->count();

        $evalCompliancePct = $expectedEvaluationsMtd > 0
            ? min(100.0, round(($completedEvaluationsMtd / $expectedEvaluationsMtd) * 100, 1))
            : 100.0;

        $evalCompliancePassing = ($evalCompliancePct >= 100.0 || $completedEvaluationsMtd >= $expectedEvaluationsMtd);

        // KPI 2: Branch Mystery Shopper Average (Target: >= 85.0%)
        $audits = BranchEvaluation::query()
            ->where('branch', $branch)
            ->whereBetween('evaluation_date', [$periodStart, $periodEnd])
            ->get(['id', 'percentage', 'total_score', 'grade', 'evaluation_date']);

        $auditsCountMtd = $audits->count();
        $mysteryShopperAvgPct = $auditsCountMtd > 0
            ? round((float) $audits->avg('percentage'), 1)
            : 0.0;

        $mysteryShopperAttainmentPct = $auditsCountMtd > 0
            ? min(100.0, round(($mysteryShopperAvgPct / self::BRANCH_MYSTERY_SHOPPER_TARGET_PCT) * 100, 1))
            : 0.0;

        $mysteryShopperPassing = ($auditsCountMtd > 0 && $mysteryShopperAvgPct >= self::BRANCH_MYSTERY_SHOPPER_TARGET_PCT);

        $passingCount = ($evalCompliancePassing ? 1 : 0) + ($mysteryShopperPassing ? 1 : 0);
        $totalKpis = 2;
        $failingCount = $totalKpis - $passingCount;
        $overallPct = round(($evalCompliancePct + $mysteryShopperAttainmentPct) / 2, 1);

        return [
            'owner' => $owner,
            'role' => $role,
            'branch' => $branch,
            'active_staff_count' => $activeBranchStaffCount,
            'mondays_elapsed_count' => $mondaysElapsedCount,
            'expected_evaluations_mtd' => $expectedEvaluationsMtd,
            'completed_evaluations_mtd' => $completedEvaluationsMtd,
            'eval_compliance_pct' => $evalCompliancePct,
            'eval_compliance_passing' => $evalCompliancePassing,
            'audits_count_mtd' => $auditsCountMtd,
            'mystery_shopper_avg_pct' => $mysteryShopperAvgPct,
            'mystery_shopper_target_pct' => self::BRANCH_MYSTERY_SHOPPER_TARGET_PCT,
            'mystery_shopper_attainment_pct' => $mysteryShopperAttainmentPct,
            'mystery_shopper_passing' => $mysteryShopperPassing,
            'overall_score_pct' => $overallPct,
            'passing_kpis_count' => $passingCount,
            'total_kpis_count' => $totalKpis,
            'below_floor_count' => $failingCount,
            'is_passing_all' => $failingCount === 0,
        ];
    }

    public function operations()
    {
        return view('kpi.operations');
    }

    public function marketing()
    {
        return view('kpi.marketing');
    }

    public function customerService()
    {
        return view('kpi.customer-service');
    }

    public function salonStaff()
    {
        return view('kpi.salon-staff');
    }
}
