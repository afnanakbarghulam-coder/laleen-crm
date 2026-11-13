<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\BranchEvaluation;
use App\Models\Customer;
use App\Models\KpiAdsConversionReport;
use App\Models\KpiAgentTargetReport;
use App\Models\KpiChatEvaluation;
use App\Models\KpiMonthlyAd;
use App\Models\KpiStaffEvaluation;
use App\Models\Lead;
use App\Models\SocialContentPlan;
use App\Models\SocialLateReplyLog;
use App\Models\Staff;
use App\Models\StaffComplaint;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
        $nowQatar = now('Asia/Qatar');
        $rawMonth = trim((string) $request->query('month', $nowQatar->format('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
            $rawMonth = $nowQatar->format('Y-m');
        }

        $monthStart = Carbon::createFromFormat('Y-m-d', $rawMonth . '-01', 'Asia/Qatar')->startOfMonth()->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
        $isCurrentMonth = ($rawMonth === $nowQatar->format('Y-m'));

        $scorecard = $this->buildExecutiveScorecard($rawMonth, $monthStart, $monthEnd, $isCurrentMonth);

        return view('kpi.hub', [
            'selectedMonth' => $rawMonth,
            'selectedMonthLabel' => $monthStart->format('F Y'),
            'isCurrentMonth' => $isCurrentMonth,
            'periodRangeLabel' => $scorecard['period_range_label'],
            'shortPeriodBadge' => $scorecard['short_period_badge'],
            'daysElapsed' => $scorecard['days_elapsed'],
            'daysInMonth' => $scorecard['days_in_month'],
            'scorecard' => $scorecard,
        ]);
    }

    public function storeLateReply(Request $request)
    {
        return $this->updateLateReplies($request);
    }

    public function updateLateReplies(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $isLegacyActionCall = $request->filled('action') && !$request->filled('incident_at');

        $validated = $request->validate([
            'incident_at' => $isLegacyActionCall ? 'nullable|date' : 'required|date',
            'screenshot' => 'nullable|image|max:5120',
            'notes' => 'nullable|string|max:500',
            'action' => 'nullable|in:increment,decrement,set',
            'count' => 'nullable|integer|min:0|max:999',
            'week_start_date' => 'nullable|date_format:Y-m-d',
            'month' => 'nullable|string|max:7',
        ]);

        $nowQatar = now('Asia/Qatar');
        $incidentAt = !empty($validated['incident_at'])
            ? Carbon::parse($validated['incident_at'], 'Asia/Qatar')->setTimezone('Asia/Qatar')
            : null;

        $targetMonth = !empty($validated['month']) && preg_match('/^\d{4}-\d{2}$/', $validated['month'])
            ? $validated['month']
            : ($incidentAt?->format('Y-m') ?? $nowQatar->format('Y-m'));

        if (!$incidentAt) {
            $incidentAt = ($targetMonth === $nowQatar->format('Y-m'))
                ? $nowQatar->copy()
                : Carbon::createFromFormat('Y-m-d H:i:s', $targetMonth . '-01 12:00:00', 'Asia/Qatar');
        }

        $monthStart = Carbon::createFromFormat('Y-m-d', $targetMonth . '-01', 'Asia/Qatar')->startOfMonth()->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $action = $validated['action'] ?? 'increment';

        if ($action === 'decrement') {
            $latestLog = SocialLateReplyLog::query()
                ->whereBetween('incident_at', [$monthStart, $monthEnd])
                ->orderByDesc('incident_at')
                ->orderByDesc('id')
                ->first();

            if ($latestLog) {
                if (filled($latestLog->screenshot_path) && Storage::disk('public')->exists($latestLog->screenshot_path)) {
                    Storage::disk('public')->delete($latestLog->screenshot_path);
                }
                $latestLog->delete();
            } else {
                $inMonthLegacyRow = $this->inMonthContentPlans($monthStart, $monthEnd)
                    ->first(fn (SocialContentPlan $p) => (int) $p->late_replies_count > 0);
                if ($inMonthLegacyRow) {
                    $inMonthLegacyRow->update([
                        'late_replies_count' => max(0, (int) $inMonthLegacyRow->late_replies_count - 1),
                    ]);
                }
            }
        } elseif ($action === 'set' && isset($validated['count'])) {
            $existingLogs = SocialLateReplyLog::query()
                ->whereBetween('incident_at', [$monthStart, $monthEnd])
                ->get();
            foreach ($existingLogs as $existingLog) {
                if (filled($existingLog->screenshot_path) && Storage::disk('public')->exists($existingLog->screenshot_path)) {
                    Storage::disk('public')->delete($existingLog->screenshot_path);
                }
                $existingLog->delete();
            }

            foreach ($this->inMonthContentPlans($monthStart, $monthEnd) as $planRow) {
                if ((int) $planRow->late_replies_count > 0) {
                    $planRow->update(['late_replies_count' => 0]);
                }
            }

            $desiredCount = max(0, (int) $validated['count']);
            for ($i = 0; $i < $desiredCount; $i++) {
                SocialLateReplyLog::create([
                    'incident_at' => $incidentAt,
                    'notes' => isset($validated['notes']) ? (trim((string) $validated['notes']) ?: null) : null,
                    'logged_by' => $request->user()->id,
                ]);
            }
        } else {
            $screenshotPath = $request->hasFile('screenshot')
                ? $request->file('screenshot')->store('uploads/late-replies', 'public')
                : null;

            SocialLateReplyLog::create([
                'incident_at' => $incidentAt,
                'screenshot_path' => $screenshotPath,
                'notes' => isset($validated['notes']) ? (trim((string) $validated['notes']) ?: null) : null,
                'logged_by' => $request->user()->id,
            ]);
        }

        $totalMonthLateReplies = SocialLateReplyLog::query()
            ->whereBetween('incident_at', [$monthStart, $monthEnd])
            ->count()
            + (int) $this->inMonthContentPlans($monthStart, $monthEnd)->sum('late_replies_count');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'month' => $targetMonth,
                'late_replies_count' => $totalMonthLateReplies,
            ]);
        }

        return redirect()
            ->route('kpi.hub', ['month' => $targetMonth])
            ->with('success', "Social media late reply log updated ({$totalMonthLateReplies} recorded for {$monthStart->format('M Y')}).");
    }

    public function destroyLateReply(Request $request, SocialLateReplyLog $lateReplyLog)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $rawMonth = trim((string) $request->input('month', ''));
        $targetMonth = preg_match('/^\d{4}-\d{2}$/', $rawMonth)
            ? $rawMonth
            : ($lateReplyLog->incident_at?->format('Y-m') ?? now('Asia/Qatar')->format('Y-m'));

        if (filled($lateReplyLog->screenshot_path) && Storage::disk('public')->exists($lateReplyLog->screenshot_path)) {
            Storage::disk('public')->delete($lateReplyLog->screenshot_path);
        }

        $lateReplyLog->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'month' => $targetMonth,
            ]);
        }

        return redirect()
            ->route('kpi.hub', ['month' => $targetMonth])
            ->with('success', 'Late reply breach record removed.');
    }

    public function lateReplyScreenshot(SocialLateReplyLog $lateReplyLog)
    {
        if (blank($lateReplyLog->screenshot_path) || !Storage::disk('public')->exists($lateReplyLog->screenshot_path)) {
            abort(404);
        }

        return Storage::disk('public')->response($lateReplyLog->screenshot_path);
    }

    public function buildExecutiveScorecard(string $selectedMonth, Carbon $monthStart, Carbon $monthEnd, bool $isCurrentMonth): array
    {
        // =========================================================================
        // DYNAMIC MONTH-TO-DATE WINDOW (01 – CURRENT DAY)
        // =========================================================================
        $nowQatar = now('Asia/Qatar');
        $periodStart = $monthStart->copy()->startOfDay();
        $periodEnd = $isCurrentMonth ? $nowQatar->copy()->endOfDay() : $monthEnd->copy()->endOfDay();
        $daysInMonth = max(1, (int) $monthStart->daysInMonth);
        $daysElapsed = $isCurrentMonth ? max(1, (int) $nowQatar->day) : $daysInMonth;

        $periodRangeLabel = $isCurrentMonth
            ? sprintf('%s – %s (MTD • Day %d/%d)', $periodStart->format('M j'), $periodEnd->format('M j, Y'), $daysElapsed, $daysInMonth)
            : sprintf('%s – %s', $periodStart->format('M j'), $periodEnd->format('M j, Y'));

        $shortPeriodBadge = sprintf('%s – %s', $periodStart->format('M j'), $periodEnd->format('M j'));

        // =========================================================================
        // C. SANA — Organic Content & Social Media Replies (01 – Current Day)
        // =========================================================================
        $sanaData = $this->buildSanaSection(
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            monthStart: $monthStart,
            isCurrentMonth: $isCurrentMonth,
            daysElapsed: $daysElapsed,
            shortPeriodBadge: $shortPeriodBadge
        );
        $sanaOverallScorePct = $sanaData['overall_score_pct'];
        $sanaFailingCount = $sanaData['below_floor_count'];
        $effectiveLateReplies = $sanaData['late_replies_count'];

        // =========================================================================
        // A. AYAN — Marketing, Ads Revenue & Content Oversight (01 – Current Day)
        // =========================================================================
        $adLeadColumns = ['id', 'date', 'customer_id', 'phone', 'branch', 'ticket_amount', 'category', 'lead_id'];
        if (Schema::hasColumn('ad_lead_entries', 'old_airport_amount')) {
            $adLeadColumns[] = 'old_airport_amount';
        }
        if (Schema::hasColumn('ad_lead_entries', 'wakrah_amount')) {
            $adLeadColumns[] = 'wakrah_amount';
        }

        $monthAdLeads = AdLeadEntry::query()
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->get($adLeadColumns);

        $oaBookedAdLeads = $monthAdLeads->filter(fn (AdLeadEntry $e) => $e->hasBranchConversion('old_airport'));
        $wkBookedAdLeads = $monthAdLeads->filter(fn (AdLeadEntry $e) => $e->hasBranchConversion('wakrah'));

        $oaAdRevenueQar = round((float) $monthAdLeads->sum(fn (AdLeadEntry $e) => $e->branchRevenue('old_airport')), 2);
        $wkAdRevenueQar = round((float) $monthAdLeads->sum(fn (AdLeadEntry $e) => $e->branchRevenue('wakrah')), 2);
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
        $pipelineBookedAdLeadsCount = $this->countPipelineScheduledAdLeads($monthAdLeads, $monthStart, $monthEnd);
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
            'pipeline_booked_ad_leads' => $pipelineBookedAdLeadsCount,
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
        $referenceDate = $isCurrentMonth ? $nowQatar->copy() : $monthEnd->copy();

        $ladenData = $this->buildLadenSection(
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            monthEnd: $monthEnd,
            isCurrentMonth: $isCurrentMonth,
            daysInMonth: $daysInMonth,
            daysElapsed: $daysElapsed
        );

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
            isCurrentMonth: $isCurrentMonth
        );

        $rheaData = $this->buildBranchManagerScorecard(
            owner: 'Rhea',
            role: 'Wakrah Branch Manager',
            branch: 'wakrah',
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            isCurrentMonth: $isCurrentMonth
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
            'is_current_month' => $isCurrentMonth,
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

    /**
     * Load all SocialContentPlan day rows whose exact calendar date (week_start_date + day offset)
     * falls strictly within [$rangeStart->toDateString(), $rangeEnd->toDateString()].
     * Uses plain 'Y-m-d' strings in SQL so SQLite and MySQL behave 100% identically.
     */
    private function inMonthContentPlans(Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        $minDateStr = $rangeStart->toDateString();
        $maxDateStr = $rangeEnd->toDateString();
        $firstOverlapMondayStr = $rangeStart->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $lastOverlapMondayStr = $rangeEnd->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        return SocialContentPlan::query()
            ->where('week_start_date', '>=', $firstOverlapMondayStr)
            ->where('week_start_date', '<=', $lastOverlapMondayStr)
            ->get()
            ->filter(function (SocialContentPlan $plan) use ($minDateStr, $maxDateStr): bool {
                $exactDateStr = $plan->exactDate()->toDateString();

                return $exactDateStr >= $minDateStr && $exactDateStr <= $maxDateStr;
            })
            ->values();
    }

    private function buildSanaSection(
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $monthStart,
        bool $isCurrentMonth,
        int $daysElapsed,
        string $shortPeriodBadge
    ): array {
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
        $effectiveEnd = $periodEnd->gt($monthEnd) ? $monthEnd : $periodEnd;

        $firstInMonthMondayStr = ($monthStart->isMonday()
            ? $monthStart->copy()
            : $monthStart->copy()->next(Carbon::MONDAY)
        )->toDateString();

        // Strict same-month isolation via exactDate() (excludes Oct 26-31 and Dec 1-6 bleed)
        $inMonthPlans = $this->inMonthContentPlans($monthStart->copy()->startOfDay(), $effectiveEnd);

        if ($isCurrentMonth) {
            $inMonthWeekStart = now('Asia/Qatar')->startOfWeek(Carbon::MONDAY)->toDateString();
        } else {
            $inMonthWeekStart = $firstInMonthMondayStr;
            $latestActiveWeek = $inMonthPlans
                ->filter(
                    fn (SocialContentPlan $p) => substr((string) $p->week_start_date, 0, 10) >= $firstInMonthMondayStr
                        && (
                            $p->is_reel_posted
                            || $p->are_stories_posted
                            || filled($p->reel_content)
                            || filled($p->stories_content)
                            || filled($p->reel_link)
                        )
                )
                ->max('week_start_date');

            if ($latestActiveWeek) {
                $inMonthWeekStart = substr((string) $latestActiveWeek, 0, 10);
            }
        }

        $weekPlans = SocialContentPlan::query()
            ->where('week_start_date', $inMonthWeekStart)
            ->get();

        $publishedReels = $weekPlans->filter(fn (SocialContentPlan $p) => $p->is_reel_posted && !$p->is_reel_skipped)->count();
        $publishedStories = $weekPlans->where('are_stories_posted', true)->count();

        $mtdPublishedReels = $inMonthPlans
            ->filter(fn (SocialContentPlan $p) => $p->is_reel_posted && !$p->is_reel_skipped)
            ->count();
        $mtdPublishedStories = $inMonthPlans->where('are_stories_posted', true)->count();
        $mtdItemsCompleted = $mtdPublishedReels + $mtdPublishedStories;

        $lateReplyLogs = Schema::hasTable('social_late_reply_logs')
            ? SocialLateReplyLog::with('logger:id,name')
                ->whereBetween('incident_at', [$monthStart->copy()->startOfDay(), $effectiveEnd])
                ->orderByDesc('incident_at')
                ->orderByDesc('id')
                ->get()
            : collect();

        $legacyInMonthLateReplies = (int) $inMonthPlans->sum('late_replies_count');
        $effectiveLateReplies = $lateReplyLogs->count() + $legacyInMonthLateReplies;

        $hasMtdSocialActivity = $lateReplyLogs->isNotEmpty() || (
            $inMonthPlans->isNotEmpty() && (
                $mtdItemsCompleted > 0
                || $legacyInMonthLateReplies > 0
                || $inMonthPlans->contains(
                    fn (SocialContentPlan $p) => filled($p->reel_content) || filled($p->stories_content) || filled($p->reel_link)
                )
            )
        );

        // Option A Smooth Daily-Prorated MTD Targets (with current Doha day grace until midnight)
        $daysInMonth = max(1, (int) $monthStart->daysInMonth);
        $completedDays = $isCurrentMonth ? max(0, $daysElapsed - 1) : $daysInMonth;
        $fullMonthStoriesTarget = $daysInMonth;
        $fullMonthReelsTarget = (int) floor(($daysInMonth * self::SANA_WEEKLY_REELS_TARGET) / 7);
        $monthlyItemsTarget = $fullMonthReelsTarget + $fullMonthStoriesTarget;
        $elapsedWeeks = max(1, min(5, (int) ceil($daysElapsed / 7)));

        if ($completedDays === 0) {
            // Day 1 grace: if calendar has Day 1 plan/post activity, treat prior-day required target as 0 (100% PASS);
            // if calendar is completely empty, score 0% FAIL.
            $mtdStoriesTarget = 0;
            $mtdReelsTarget = 0;
            $mtdItemsTarget = 0;
            $sanaContentPassing = $hasMtdSocialActivity;
            $weeklyContentExecutionPct = $hasMtdSocialActivity ? 100.0 : 0.0;
        } else {
            $mtdStoriesTarget = $completedDays;
            $mtdReelsTarget = (int) floor(($completedDays * self::SANA_WEEKLY_REELS_TARGET) / 7);
            $mtdItemsTarget = $mtdReelsTarget + $mtdStoriesTarget;

            // Cap category excess so extra Reels cannot mask missing Stories as 100% FAIL
            $creditedReels = min($mtdPublishedReels, $mtdReelsTarget);
            $creditedStories = min($mtdPublishedStories, $mtdStoriesTarget);

            $weeklyContentExecutionPct = !$hasMtdSocialActivity
                ? 0.0
                : ($mtdItemsTarget > 0
                    ? round(min(100.0, (($creditedReels + $creditedStories) / $mtdItemsTarget) * 100), 1)
                    : 100.0);

            $sanaContentPassing = $hasMtdSocialActivity
                && ($mtdPublishedReels >= $mtdReelsTarget)
                && ($mtdPublishedStories >= $mtdStoriesTarget);
        }

        $sanaRepliesPassing = $hasMtdSocialActivity && ($effectiveLateReplies === 0);
        $sanaRepliesScorePct = $sanaRepliesPassing ? 100.0 : 0.0;
        $sanaOverallScorePct = round(($weeklyContentExecutionPct + $sanaRepliesScorePct) / 2, 1);
        $sanaPassingCount = ($sanaContentPassing ? 1 : 0) + ($sanaRepliesPassing ? 1 : 0);
        $sanaTotalKpis = 2;
        $sanaFailingCount = $sanaTotalKpis - $sanaPassingCount;

        return [
            'owner' => 'Sana',
            'role' => 'Organic Content & Social Media Replies',
            'week_start_date' => $inMonthWeekStart,
            'in_month_week_start' => $inMonthWeekStart,
            'period_badge' => $shortPeriodBadge,
            'completed_days' => $completedDays,
            'published_reels' => $publishedReels,
            'reels_target' => self::SANA_WEEKLY_REELS_TARGET,
            'published_stories' => $publishedStories,
            'stories_target' => self::SANA_WEEKLY_STORIES_TARGET,
            'elapsed_weeks' => $elapsedWeeks,
            'monthly_items_target' => $monthlyItemsTarget,
            'mtd_reels_target' => $mtdReelsTarget,
            'mtd_stories_target' => $mtdStoriesTarget,
            'mtd_items_target' => $mtdItemsTarget,
            'cumulative_mtd_target' => $mtdItemsTarget,
            'mtd_items_completed' => $mtdItemsCompleted,
            'weekly_execution_pct' => $weeklyContentExecutionPct,
            'content_passing' => $sanaContentPassing,
            'mtd_published_reels' => $mtdPublishedReels,
            'mtd_published_stories' => $mtdPublishedStories,
            'late_replies_count' => $effectiveLateReplies,
            'late_reply_logs' => $lateReplyLogs,
            'replies_sla_passing' => $sanaRepliesPassing,
            'replies_sla_status_label' => !$hasMtdSocialActivity
                ? 'NO ACTIVITY LOGGED (0% SLA)'
                : ($sanaRepliesPassing
                    ? '100% SLA PASS (0 Late Replies)'
                    : "SLA BREACH ({$effectiveLateReplies} Reported Late " . ($effectiveLateReplies === 1 ? 'Reply' : 'Replies') . ')'),
            'overall_score_pct' => $sanaOverallScorePct,
            'passing_kpis_count' => $sanaPassingCount,
            'total_kpis_count' => $sanaTotalKpis,
            'below_floor_count' => $sanaFailingCount,
            'is_passing_all' => $sanaFailingCount === 0,
        ];
    }

    /**
     * Count unpaid AdLeadEntry customer profiles in the selected month that already have
     * an active ('pending', 'confirmed', 'arrived', 'in_progress') unpaid appointment
     * on the Enhanced Calendar this month on or after the ad lead date.
     */
    private function countPipelineScheduledAdLeads(Collection $monthAdLeads, Carbon $monthStart, Carbon $monthEnd): int
    {
        $unpaidLeads = $monthAdLeads->reject(fn (AdLeadEntry $e) => $e->isBooked());
        if ($unpaidLeads->isEmpty() || !Schema::hasTable('appointments')) {
            return 0;
        }

        $cohorts = [];
        $allCustomerIds = [];
        $allRawPhones = [];

        foreach ($unpaidLeads as $entry) {
            $normPhone = Customer::normalizePhone($entry->phone);
            $customerId = $entry->customer_id ? (int) $entry->customer_id : null;
            $key = $customerId ? "cid:{$customerId}" : ($normPhone !== '' ? "ph:{$normPhone}" : "entry:{$entry->id}");
            $entryDateStr = Carbon::parse($entry->date)->toDateString();

            if (!isset($cohorts[$key])) {
                $cohorts[$key] = [
                    'customer_id' => $customerId,
                    'norm_phone' => $normPhone,
                    'min_date_str' => $entryDateStr,
                ];
            } else {
                if ($entryDateStr < $cohorts[$key]['min_date_str']) {
                    $cohorts[$key]['min_date_str'] = $entryDateStr;
                }
                if (!$cohorts[$key]['customer_id'] && $customerId) {
                    $cohorts[$key]['customer_id'] = $customerId;
                }
                if ($cohorts[$key]['norm_phone'] === '' && $normPhone !== '') {
                    $cohorts[$key]['norm_phone'] = $normPhone;
                }
            }

            if ($customerId) {
                $allCustomerIds[] = $customerId;
            }
            if ($entry->phone) {
                $allRawPhones[] = $entry->phone;
            }
        }

        $allCustomerIds = array_values(array_unique($allCustomerIds));
        [, $phoneCandidates, $strippedDigits] = AdLeadEntry::buildPhoneLookupSets($allRawPhones);

        if (empty($allCustomerIds) && empty($phoneCandidates) && empty($strippedDigits)) {
            return 0;
        }

        $activeAppointments = Appointment::query()
            ->whereIn('status', ['pending', 'confirmed', 'arrived', 'in_progress'])
            ->whereNull('paid_at')
            ->doesntHave('sale')
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('appointment_datetime', [$monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay()])
                    ->orWhere(function ($sq) use ($monthStart, $monthEnd) {
                        $sq->whereNull('appointment_datetime')
                            ->whereBetween('booking_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                    });
            })
            ->where(function ($q) use ($allCustomerIds, $phoneCandidates, $strippedDigits) {
                if (!empty($allCustomerIds)) {
                    $q->orWhereIn('customer_id', $allCustomerIds);
                }
                AdLeadEntry::applyPhoneCohortClause($q, $phoneCandidates, $strippedDigits, 'or', 'phone');
            })
            ->get(['id', 'customer_id', 'phone', 'appointment_datetime', 'booking_date', 'created_at', 'status']);

        if ($activeAppointments->isEmpty()) {
            return 0;
        }

        $scheduledCount = 0;
        foreach ($cohorts as $cohort) {
            $hasScheduled = $activeAppointments->contains(function (Appointment $appt) use ($cohort) {
                $matchesCustomer = $cohort['customer_id']
                    && $appt->customer_id
                    && (int) $appt->customer_id === (int) $cohort['customer_id'];

                $matchesPhone = $cohort['norm_phone'] !== ''
                    && Customer::normalizePhone($appt->phone) === $cohort['norm_phone'];

                if (!$matchesCustomer && !$matchesPhone) {
                    return false;
                }

                return $appt->appointment_date->toDateString() >= $cohort['min_date_str'];
            });

            if ($hasScheduled) {
                $scheduledCount++;
            }
        }

        return $scheduledCount;
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
            ->where('role', 'agent')
            ->where('name', 'like', '%' . $owner . '%')
            ->pluck('id');

        $dailyTarget = self::AGENT_DAILY_BOOKING_QUOTA; // 13 bookings/day
        $agentMtdTarget = $dailyTarget * $daysElapsed;
        $agentFullMonthTarget = $dailyTarget * $daysInMonth;

        $qatarTz = 'Asia/Qatar';
        $appTz = config('app.timezone', $qatarTz);
        $refDateStr = $referenceDate->copy()->timezone($qatarTz)->format('Y-m-d');
        $todayStart = Carbon::createFromFormat('Y-m-d', $refDateStr, $qatarTz)->startOfDay()->timezone($appTz);
        $todayEnd = Carbon::createFromFormat('Y-m-d', $refDateStr, $qatarTz)->endOfDay()->timezone($appTz);
        $mtdStart = Carbon::createFromFormat('Y-m-d', $periodStart->copy()->timezone($qatarTz)->format('Y-m-d'), $qatarTz)->startOfDay()->timezone($appTz);
        $mtdEnd = Carbon::createFromFormat('Y-m-d', $periodEnd->copy()->timezone($qatarTz)->format('Y-m-d'), $qatarTz)->endOfDay()->timezone($appTz);

        $todayBookings = 0;
        $mtdBookings = 0;

        if ($agentUserIds->isNotEmpty()) {
            $mtdRawAppointments = Appointment::query()
                ->scheduledForAgentQuota($agentUserIds)
                ->whereBetween('created_at', [$mtdStart, $mtdEnd])
                ->get([
                    'id',
                    'booking_agent_id',
                    'customer_id',
                    'phone',
                    'customer_name',
                    'booking_date',
                    'appointment_datetime',
                    'created_at',
                ]);

            $dedupedMtdAppointments = Appointment::deduplicateForAgentQuota($mtdRawAppointments);
            $mtdBookings = $dedupedMtdAppointments->count();
            $todayBookings = $dedupedMtdAppointments
                ->filter(fn (Appointment $appt) => $appt->created_at && $appt->created_at->between($todayStart, $todayEnd))
                ->count();
        }

        $todayAttainmentPct = min(100.0, round(($todayBookings / $dailyTarget) * 100, 1));
        $mtdAttainmentPct = min(100.0, round(($mtdBookings / max(1, $agentMtdTarget)) * 100, 1));
        $dailyAvgPace = round($mtdBookings / max(1, $daysInMonth), 1);
        $dailyAvgPct = min(100.0, round(($dailyAvgPace / $dailyTarget) * 100, 1));
        $isPassing = ($mtdBookings >= $agentMtdTarget);

        return [
            'owner' => $owner,
            'role' => $role,
            'daily_target' => $dailyTarget,
            'today_bookings' => $todayBookings,
            'today_attainment_pct' => $todayAttainmentPct,
            'today_remaining_needed' => max(0, $dailyTarget - $todayBookings),
            'daily_avg_pace' => $dailyAvgPace,
            'daily_avg_pct' => $dailyAvgPct,
            'mtd_bookings' => $mtdBookings,
            'mtd_target' => $agentMtdTarget,
            'full_month_target' => $agentFullMonthTarget,
            'mtd_attainment_pct' => $mtdAttainmentPct,
            'booking_passing' => $isPassing,
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
        bool $isCurrentMonth
    ): array {
        $nowQatar = now('Asia/Qatar');

        // KPI 1: Monday Staff Evaluation Compliance (Target: 100%)
        $activeBranchStaffCount = Staff::salonEvaluatable($branch, $periodEnd->toDateString())->count();

        $allMondays = [];
        foreach (CarbonPeriod::create($periodStart->copy()->startOfDay(), $periodEnd->copy()->startOfDay()) as $day) {
            if ($day->isMonday()) {
                $allMondays[] = $day->toDateString();
            }
        }

        $evalRows = KpiStaffEvaluation::query()
            ->where('branch', $branch)
            ->whereDate('evaluation_date', '>=', $periodStart->toDateString())
            ->whereDate('evaluation_date', '<=', $periodEnd->toDateString())
            ->get(['staff_id', 'evaluation_date']);

        $perMondayStats = [];
        foreach ($allMondays as $mondayStr) {
            $eligibleIds = Staff::salonEvaluatable($branch, $mondayStr)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $expectedCount = count($eligibleIds);
            $completedCount = $expectedCount > 0
                ? $evalRows
                    ->filter(function (KpiStaffEvaluation $e) use ($mondayStr, $eligibleIds): bool {
                        $evalMondayStr = Carbon::parse($e->evaluation_date, 'Asia/Qatar')
                            ->startOfWeek(Carbon::MONDAY)
                            ->toDateString();

                        return $evalMondayStr === $mondayStr
                            && in_array((int) $e->staff_id, $eligibleIds, true);
                    })
                    ->pluck('staff_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->count()
                : 0;

            $perMondayStats[$mondayStr] = [
                'expected' => $expectedCount,
                'completed' => $completedCount,
                'is_complete' => $expectedCount > 0 && $completedCount >= $expectedCount,
            ];
        }

        $currentWeekMondayStr = $nowQatar->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $inMonTueGrace = $isCurrentMonth
            && ($nowQatar->isMonday() || $nowQatar->isTuesday())
            && isset($perMondayStats[$currentWeekMondayStr]);

        if (empty($allMondays)) {
            $countedMondays = 0;
            $expectedEvaluationsMtd = 0;
            $completedEvaluationsMtd = 0;
            $evalCompliancePct = 0.0;
            $evalCompliancePassing = false;
        } elseif ($inMonTueGrace && !$perMondayStats[$currentWeekMondayStr]['is_complete']) {
            $priorStats = array_filter(
                $perMondayStats,
                fn (array $stat, string $mondayKey) => $mondayKey !== $currentWeekMondayStr,
                ARRAY_FILTER_USE_BOTH
            );

            if (!empty($priorStats)) {
                $countedMondays = count($priorStats);
                $expectedEvaluationsMtd = array_sum(array_column($priorStats, 'expected'));
                $completedEvaluationsMtd = array_sum(array_column($priorStats, 'completed'));
                $evalCompliancePct = $expectedEvaluationsMtd > 0
                    ? min(100.0, round(($completedEvaluationsMtd / $expectedEvaluationsMtd) * 100, 1))
                    : 0.0;
                $evalCompliancePassing = ($expectedEvaluationsMtd > 0 && $completedEvaluationsMtd >= $expectedEvaluationsMtd);
            } else {
                $currentStat = $perMondayStats[$currentWeekMondayStr];
                $countedMondays = 1;
                $expectedEvaluationsMtd = $currentStat['expected'];
                $completedEvaluationsMtd = $currentStat['completed'];
                $evalCompliancePct = $expectedEvaluationsMtd > 0
                    ? min(100.0, round(($completedEvaluationsMtd / $expectedEvaluationsMtd) * 100, 1))
                    : 0.0;
                $evalCompliancePassing = ($expectedEvaluationsMtd > 0);
            }
        } else {
            $countedMondays = count($perMondayStats);
            $expectedEvaluationsMtd = array_sum(array_column($perMondayStats, 'expected'));
            $completedEvaluationsMtd = array_sum(array_column($perMondayStats, 'completed'));
            $evalCompliancePct = $expectedEvaluationsMtd > 0
                ? min(100.0, round(($completedEvaluationsMtd / $expectedEvaluationsMtd) * 100, 1))
                : 0.0;
            $evalCompliancePassing = ($expectedEvaluationsMtd > 0 && $completedEvaluationsMtd >= $expectedEvaluationsMtd);
        }

        // KPI 2: Branch Mystery Shopper Average (Target: >= 85.0%)
        $audits = BranchEvaluation::query()
            ->where('branch', $branch)
            ->whereDate('evaluation_date', '>=', $periodStart->toDateString())
            ->whereDate('evaluation_date', '<=', $periodEnd->toDateString())
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
            'mondays_elapsed_count' => $countedMondays,
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

    /**
     * Build Laden's Customer Service, Bookings, Chat Quality & Complaint SLA scorecard section.
     * Ensures Lead Conversion Rate only counts leads with needful_done === 'booked' (when not linked
     * to a cancelled/no-show booking) OR a linked active non-cancelled/non-no-show Appointment.
     */
    private function buildLadenSection(
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $monthEnd,
        bool $isCurrentMonth,
        int $daysInMonth,
        int $daysElapsed
    ): array {
        $qatarTz = 'Asia/Qatar';
        $appTz = config('app.timezone', $qatarTz);
        $referenceDate = $isCurrentMonth ? now($qatarTz) : $monthEnd->copy()->timezone($qatarTz);
        $refDateStr = $referenceDate->format('Y-m-d');
        $todayStart = Carbon::createFromFormat('Y-m-d', $refDateStr, $qatarTz)->startOfDay()->timezone($appTz);
        $todayEnd = Carbon::createFromFormat('Y-m-d', $refDateStr, $qatarTz)->endOfDay()->timezone($appTz);
        $mtdStart = Carbon::createFromFormat('Y-m-d', $periodStart->copy()->timezone($qatarTz)->format('Y-m-d'), $qatarTz)->startOfDay()->timezone($appTz);
        $mtdEnd = Carbon::createFromFormat('Y-m-d', $periodEnd->copy()->timezone($qatarTz)->format('Y-m-d'), $qatarTz)->endOfDay()->timezone($appTz);

        $chatAgentIds = User::where('role', 'agent')->pluck('id');
        $todayBookingsCount = 0;
        $mtdBookingsCount = 0;

        if ($chatAgentIds->isNotEmpty()) {
            $mtdRawAppointments = Appointment::query()
                ->scheduledForAgentQuota($chatAgentIds)
                ->whereBetween('created_at', [$mtdStart, $mtdEnd])
                ->get([
                    'id',
                    'booking_agent_id',
                    'customer_id',
                    'phone',
                    'customer_name',
                    'booking_date',
                    'appointment_datetime',
                    'created_at',
                ]);

            $dedupedMtdAppointments = Appointment::deduplicateForAgentQuota($mtdRawAppointments);
            $mtdBookingsCount = $dedupedMtdAppointments->count();
            $todayBookingsCount = $dedupedMtdAppointments
                ->filter(fn (Appointment $appt) => $appt->created_at && $appt->created_at->between($todayStart, $todayEnd))
                ->count();
        }

        $dailyTarget = self::LADEN_DAILY_BOOKING_QUOTA; // 26 (13/day x 2 Chat Agents)
        $fullMonthTargetBookings = $dailyTarget * $daysInMonth;
        $mtdTargetBookings = $dailyTarget * $daysElapsed;
        $todayBookingQuotaPct = round(min(100.0, ($todayBookingsCount / $dailyTarget) * 100), 1);
        $mtdBookingQuotaPct = min(100.0, round(($mtdBookingsCount / max(1, $mtdTargetBookings)) * 100, 1));
        $ladenBookingPassing = ($mtdBookingsCount >= $mtdTargetBookings);
        $bookingStatusLabel = $mtdBookingQuotaPct >= 100.0
            ? 'ON TARGET'
            : ($mtdBookingQuotaPct >= 85.0 ? 'NEAR TARGET' : 'BELOW QUOTA');

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
        $monthLeads = Lead::with('appointment:id,status')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get(['id', 'appointment_id', 'needful_done', 'assigned_agent_id', 'next_followup_date']);

        $totalLeadsCount = $monthLeads->count();
        $bookedLeadsCount = $monthLeads->filter(fn (Lead $lead) => $lead->isBookedConversion())->count();
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

        // KPI 4: Complaint Resolution SLA (<= 48 Hours from incident date/time)
        $monthComplaints = StaffComplaint::with('notices')
            ->whereDate('complaint_date', '>=', $periodStart->toDateString())
            ->whereDate('complaint_date', '<=', $periodEnd->toDateString())
            ->get();

        $totalComplaintsCount = $monthComplaints->count();
        $resolvedWithin48hCount = 0;
        $breachingResolvedCount = 0;
        $openWithin48hCount = 0;
        $openComplaintsCount = 0;
        $breachingOpenCount = 0;
        $oldestOpenAgeHours = 0;

        foreach ($monthComplaints as $complaint) {
            $complaintDateStr = $complaint->complaint_date
                ? Carbon::parse($complaint->complaint_date)->toDateString()
                : ($complaint->created_at ? Carbon::parse($complaint->created_at)->toDateString() : $periodStart->toDateString());

            if (filled($complaint->complaint_time)) {
                $slaStartAt = Carbon::parse($complaintDateStr . ' ' . trim((string) $complaint->complaint_time));
            } elseif ($complaint->created_at && Carbon::parse($complaint->created_at)->toDateString() === $complaintDateStr) {
                $slaStartAt = Carbon::parse($complaint->created_at);
            } else {
                $slaStartAt = Carbon::parse($complaintDateStr)->startOfDay();
                if ($complaint->created_at && Carbon::parse($complaint->created_at)->lt($slaStartAt)) {
                    $slaStartAt = Carbon::parse($complaint->created_at);
                }
            }

            $isResolved = ($complaint->is_staff_mistake !== null || $complaint->notices->isNotEmpty());

            if ($isResolved) {
                $resolvedAt = $complaint->resolved_at
                    ? Carbon::parse($complaint->resolved_at)
                    : ($complaint->updated_at ? Carbon::parse($complaint->updated_at) : $slaStartAt);
                $resolutionHours = max(0.0, $slaStartAt->diffInMinutes($resolvedAt, false) / 60.0);
                if ($resolutionHours <= self::LADEN_COMPLAINT_SLA_HOURS) {
                    $resolvedWithin48hCount++;
                } else {
                    $breachingResolvedCount++;
                }
            } else {
                $openComplaintsCount++;
                $openElapsedHours = max(0.0, $slaStartAt->diffInMinutes(now(), false) / 60.0);
                $ageHours = (int) floor($openElapsedHours);
                if ($ageHours > $oldestOpenAgeHours) {
                    $oldestOpenAgeHours = $ageHours;
                }
                if ($openElapsedHours > self::LADEN_COMPLAINT_SLA_HOURS) {
                    $breachingOpenCount++;
                } else {
                    $openWithin48hCount++;
                }
            }
        }

        $compliantComplaintsCount = $resolvedWithin48hCount + $openWithin48hCount;
        $complaintSlaPct = $totalComplaintsCount > 0
            ? round(($compliantComplaintsCount / $totalComplaintsCount) * 100, 1)
            : 100.0;

        $ladenComplaintPassing = ($breachingResolvedCount === 0 && $breachingOpenCount === 0);

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

        return [
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
            'lead_status_label' => $ladenLeadConvPassing ? 'ON TRACK' : 'BELOW FLOOR',
            'lead_conv_passing' => $ladenLeadConvPassing,
            'unassigned_leads_count' => $unassignedLeadsCount,
            'overdue_followups_count' => $overdueFollowupsCount,
            'complaint_sla_hours' => self::LADEN_COMPLAINT_SLA_HOURS,
            'total_complaints_count' => $totalComplaintsCount,
            'resolved_within_48h_count' => $resolvedWithin48hCount,
            'breaching_resolved_count' => $breachingResolvedCount,
            'open_within_48h_count' => $openWithin48hCount,
            'open_complaints_count' => $openComplaintsCount,
            'breaching_open_count' => $breachingOpenCount,
            'oldest_open_age_hours' => $oldestOpenAgeHours,
            'complaint_sla_pct' => $complaintSlaPct,
            'complaint_sla_label' => $totalComplaintsCount === 0
                ? '100% — 0 Complaints'
                : "{$compliantComplaintsCount} / {$totalComplaintsCount} Within 48h SLA ({$complaintSlaPct}%)",
            'complaint_passing' => $ladenComplaintPassing,
            'overall_score_pct' => $ladenOverallScorePct,
            'passing_kpis_count' => $ladenPassingCount,
            'total_kpis_count' => $ladenTotalKpis,
            'below_floor_count' => $ladenFailingCount,
            'is_passing_all' => $ladenFailingCount === 0,
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
