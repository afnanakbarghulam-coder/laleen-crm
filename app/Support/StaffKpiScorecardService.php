<?php

namespace App\Support;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\BranchEvaluation;
use App\Models\KpiChatEvaluation;
use App\Models\KpiMonthlyAd;
use App\Models\KpiStaffEvaluation;
use App\Models\Lead;
use App\Models\OpsActionItem;
use App\Models\SocialContentPlan;
use App\Models\SocialLateReplyLog;
use App\Models\Staff;
use App\Models\StaffComplaint;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Single Source of Truth for Staff Executive KPI Scorecards across both:
 *   - KPIs Module (`/kpis` — Executive KPI Dashboard)
 *   - Staff Self-Service Portal (`/my-profile?tab=performance` & Admin Preview)
 *
 * Zero duplicated KPI math: both surfaces invoke the exact same section builders
 * and canonical card-presentation builders.
 */
class StaffKpiScorecardService
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

    public const ARBAB_COMPLETION_TARGET_PCT = 85.0;
    public const ARBAB_SLA_TARGET_PCT = 85.0;

    public const SUNDUS_WEEKLY_BRANCH_EVAL_TARGET = 3;

    /**
     * Build the full Executive KPI Scorecard payload used by `/kpis`.
     *
     * @return array<string, mixed>
     */
    public function buildExecutiveScorecard(
        string $selectedMonth,
        Carbon $monthStart,
        Carbon $monthEnd,
        bool $isCurrentMonth
    ): array {
        $window = $this->resolvePeriodWindow($selectedMonth, $monthStart, $monthEnd, $isCurrentMonth);

        $sanaData = $this->buildSanaSection(
            periodStart: $window['period_start'],
            periodEnd: $window['period_end'],
            monthStart: $monthStart,
            isCurrentMonth: $isCurrentMonth,
            daysElapsed: $window['days_elapsed'],
            shortPeriodBadge: $window['short_period_badge']
        );

        $ayanData = $this->buildAyanSection(
            selectedMonth: $selectedMonth,
            periodStart: $window['period_start'],
            periodEnd: $window['period_end'],
            daysInMonth: $window['days_in_month'],
            daysElapsed: $window['days_elapsed'],
            sanaData: $sanaData
        );

        $ladenData = $this->buildLadenSection(
            periodStart: $window['period_start'],
            periodEnd: $window['period_end'],
            monthEnd: $monthEnd,
            isCurrentMonth: $isCurrentMonth,
            daysInMonth: $window['days_in_month'],
            daysElapsed: $window['days_elapsed']
        );

        $mondaysElapsedCount = 0;
        foreach (CarbonPeriod::create($window['period_start']->copy()->startOfDay(), $window['period_end']->copy()->startOfDay()) as $day) {
            if ($day->isMonday()) {
                $mondaysElapsedCount++;
            }
        }

        $ahanaData = $this->buildBranchManagerScorecard(
            owner: 'Ahana',
            role: 'Old Airport Branch Manager',
            branch: 'old_airport',
            periodStart: $window['period_start'],
            periodEnd: $window['period_end'],
            isCurrentMonth: $isCurrentMonth
        );

        $rheaData = $this->buildBranchManagerScorecard(
            owner: 'Rhea',
            role: 'Wakrah Branch Manager',
            branch: 'wakrah',
            periodStart: $window['period_start'],
            periodEnd: $window['period_end'],
            isCurrentMonth: $isCurrentMonth
        );

        $areebaData = $this->buildChatAgentScorecard(
            owner: 'Areeba',
            role: 'Customer Service Chat Agent',
            periodStart: $window['period_start'],
            periodEnd: $window['period_end'],
            referenceDate: $window['reference_date'],
            daysElapsed: $window['days_elapsed'],
            daysInMonth: $window['days_in_month']
        );

        $zoyaData = $this->buildChatAgentScorecard(
            owner: 'Zoya',
            role: 'Customer Service Chat Agent',
            periodStart: $window['period_start'],
            periodEnd: $window['period_end'],
            referenceDate: $window['reference_date'],
            daysElapsed: $window['days_elapsed'],
            daysInMonth: $window['days_in_month']
        );

        $arbabData = $this->buildArbabSection(
            periodStart: $window['period_start'],
            periodEnd: $window['period_end'],
            referenceDate: $window['reference_date']
        );

        $sundusData = $this->buildSundusSection(
            periodStart: $window['period_start'],
            periodEnd: $window['period_end'],
            referenceDate: $window['reference_date'],
            isCurrentMonth: $isCurrentMonth
        );

        $salonStaffRows = $this->buildSalonStaffSections(
            periodStart: $window['period_start'],
            periodEnd: $window['period_end']
        );

        return [
            'month' => $selectedMonth,
            'is_current_month' => $isCurrentMonth,
            'period_start' => $window['period_start']->toDateString(),
            'period_end' => $window['period_end']->toDateString(),
            'period_range_label' => $window['period_range_label'],
            'short_period_badge' => $window['short_period_badge'],
            'days_elapsed' => $window['days_elapsed'],
            'days_in_month' => $window['days_in_month'],
            'mondays_elapsed_count' => $mondaysElapsedCount,
            'sundus' => $sundusData,
            'ayan' => $ayanData,
            'laden' => $ladenData,
            'sana' => $sanaData,
            'ahana' => $ahanaData,
            'rhea' => $rheaData,
            'areeba' => $areebaData,
            'zoya' => $zoyaData,
            'arbab' => $arbabData,
            'salon_staff_list' => $salonStaffRows,
        ];
    }

    /**
     * Build the presentation view-models (header meta + exact KPI cards) for ALL executive & salon staff owners on `/kpis`.
     *
     * @param  array<string, mixed>  $scorecard
     * @return array<string, array<string, mixed>>
     */
    public function buildAllExecutiveViewModels(array $scorecard, ?User $viewer = null): array
    {
        $ctx = $this->extractContextFromExecutiveScorecard($scorecard);

        $models = [
            'sundus' => $this->presentSundus($scorecard['sundus'] ?? [], $ctx, $viewer),
            'ahana' => $this->presentBranchManager('ahana', $scorecard['ahana'], $ctx, $viewer),
            'rhea' => $this->presentBranchManager('rhea', $scorecard['rhea'], $ctx, $viewer),
            'laden' => $this->presentLaden($scorecard['laden'], $ctx, $viewer),
            'arbab' => $this->presentArbab($scorecard['arbab'], $ctx, $viewer),
            'ayan' => $this->presentAyan($scorecard['ayan'], $scorecard['sana'], $ctx, $viewer),
            'sana' => $this->presentSana($scorecard['sana'], $ctx, $viewer),
            'areeba' => $this->presentChatAgent('areeba', $scorecard['areeba'], $ctx, $viewer),
            'zoya' => $this->presentChatAgent('zoya', $scorecard['zoya'], $ctx, $viewer),
        ];

        foreach (($scorecard['salon_staff_list'] ?? []) as $staffRow) {
            $ownerKey = (string) ($staffRow['owner_key'] ?? '');
            if ($ownerKey === '') {
                continue;
            }
            $vm = $this->presentSalonStaff(
                displayName: (string) $staffRow['name'],
                roleLabel: 'Salon Staff',
                periodLabel: $ctx['period_range_label'],
                weeklyEvaluations: $staffRow['weekly_evaluations'] ?? [],
                myComplaints: $staffRow['complaints'] ?? [],
                ownerKey: $ownerKey
            );
            $vm['staff_id'] = (int) $staffRow['staff_id'];
            $vm['branch'] = (string) $staffRow['branch'];
            $vm['branch_label'] = (string) $staffRow['branch_label'];
            $vm['team_group'] = 'salon_staff';
            $models[$ownerKey] = $vm;
        }

        return $models;
    }

    /**
     * Resolve a User / Staff member to their exact KPI scorecard key.
     * Checks normalized name / first-name aliases first, then falls back cleanly by role & branch.
     */
    public function resolveScorecardKey(User $user, ?Staff $staff = null): ?string
    {
        $roleSlug = strtolower(trim((string) $user->role));
        $candidates = array_filter([
            strtolower(trim((string) ($staff?->name ?? ''))),
            strtolower(trim((string) ($user->name ?? ''))),
            strtolower(trim(explode('@', (string) ($user->email ?? ''))[0] ?? '')),
        ]);

        $namedKeys = ['sundus', 'sundas', 'laden', 'arbab', 'sana', 'ayan', 'areeba', 'zoya', 'ahana', 'rhea'];
        foreach ($candidates as $haystack) {
            foreach ($namedKeys as $key) {
                if ($haystack === $key || preg_match('/\b' . preg_quote($key, '/') . '\b/i', $haystack)) {
                    return $key === 'sundas' ? 'sundus' : $key;
                }
            }
            // Email local-part prefix check (e.g., sundus@..., laden.khan@..., arbab.ops@..., sana.kazi@...)
            foreach ($namedKeys as $key) {
                if (preg_match('/^' . preg_quote($key, '/') . '([._-]|$)/i', $haystack)) {
                    return $key === 'sundas' ? 'sundus' : $key;
                }
            }
        }

        // Role & branch fallback so renamed/new staff always receive the exact KPIs-module scorecard for their role
        return match ($roleSlug) {
            'sales_coordinator' => 'laden',
            'ops_lead' => 'arbab',
            'marketing_specialist' => 'ayan',
            'social_media_editor' => 'sana',
            'salon_manager', 'manager' => $this->resolveBranchManagerKey($user, $staff),
            'agent' => 'chat_agent',
            'salon_staff' => 'salon_staff',
            default => null,
        };
    }

    /**
     * Build the single-employee Performance scorecard strictly for `/my-profile?tab=performance`
     * (and Admin Preview). Computes ONLY the target employee's section using the exact same
     * section builder and card presenter as `/kpis` — zero cross-staff leakage, zero duplicated math.
     *
     * @param  list<array<string, mixed>>|null  $weeklyEvaluations
     * @param  list<array<string, mixed>>|null  $myComplaints
     * @return array<string, mixed>
     */
    public function buildForPortalMember(
        User $targetUser,
        ?Staff $staff,
        User $effectiveViewer,
        ?Carbon $asOf = null,
        ?array $weeklyEvaluations = null,
        ?array $myComplaints = null
    ): array {
        $nowQatar = ($asOf ? $asOf->copy()->timezone('Asia/Qatar') : now('Asia/Qatar'));
        $selectedMonth = $nowQatar->format('Y-m');
        $monthStart = $nowQatar->copy()->startOfMonth()->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
        $isCurrentMonth = true;
        $window = $this->resolvePeriodWindow($selectedMonth, $monthStart, $monthEnd, $isCurrentMonth, $nowQatar);

        $ctx = [
            'month' => $selectedMonth,
            'month_label' => $monthStart->format('F Y'),
            'is_current_month' => $isCurrentMonth,
            'period_start' => $window['period_start']->toDateString(),
            'period_end' => $window['period_end']->toDateString(),
            'period_range_label' => $window['period_range_label'],
            'short_period_badge' => $window['short_period_badge'],
            'days_elapsed' => $window['days_elapsed'],
            'days_in_month' => $window['days_in_month'],
        ];

        $key = $this->resolveScorecardKey($targetUser, $staff);
        $displayName = $staff?->name ?: $targetUser->name;
        $roleLabel = User::roleLabel($targetUser->role);

        if ($key === 'sundus') {
            $explicitIds = $targetUser->exists ? collect([$targetUser->id]) : null;
            $data = $this->buildSundusSection(
                periodStart: $window['period_start'],
                periodEnd: $window['period_end'],
                referenceDate: $window['reference_date'],
                isCurrentMonth: $isCurrentMonth,
                explicitUserIds: $explicitIds
            );
            $vm = $this->presentSundus($data, $ctx, $effectiveViewer, $displayName);
            $vm['display_name'] = $displayName;
            $vm['role_subtitle'] = "{$roleLabel} — Branch Execution Audits ({$data['total_kpis_count']} KPIs)";
            $vm['raw'] = $data;

            return $vm;
        }

        if ($key === 'laden') {
            $data = $this->buildLadenSection(
                $window['period_start'],
                $window['period_end'],
                $monthEnd,
                $isCurrentMonth,
                $window['days_in_month'],
                $window['days_elapsed']
            );
            $vm = $this->presentLaden($data, $ctx, $effectiveViewer);
            $vm['display_name'] = $displayName;
            $vm['role_subtitle'] = "{$roleLabel} — {$data['total_kpis_count']} Executive KPIs";
            $vm['raw'] = $data;

            return $vm;
        }

        if ($key === 'ayan') {
            $sanaData = $this->buildSanaSection(
                $window['period_start'],
                $window['period_end'],
                $monthStart,
                $isCurrentMonth,
                $window['days_elapsed'],
                $window['short_period_badge']
            );
            $data = $this->buildAyanSection(
                $selectedMonth,
                $window['period_start'],
                $window['period_end'],
                $window['days_in_month'],
                $window['days_elapsed'],
                $sanaData
            );
            $vm = $this->presentAyan($data, $sanaData, $ctx, $effectiveViewer);
            $vm['display_name'] = $displayName;
            $vm['role_subtitle'] = "{$roleLabel} — {$data['total_kpis_count']} Executive KPIs";
            $vm['raw'] = $data;

            return $vm;
        }

        if ($key === 'sana') {
            $data = $this->buildSanaSection(
                $window['period_start'],
                $window['period_end'],
                $monthStart,
                $isCurrentMonth,
                $window['days_elapsed'],
                $window['short_period_badge']
            );
            $vm = $this->presentSana($data, $ctx, $effectiveViewer);
            $vm['display_name'] = $displayName;
            $vm['role_subtitle'] = "{$roleLabel} — {$data['total_kpis_count']} Executive KPIs";
            $vm['raw'] = $data;

            return $vm;
        }

        if (in_array($key, ['ahana', 'rhea'], true)) {
            $branch = ($staff && in_array($staff->branch, ['old_airport', 'wakrah'], true))
                ? $staff->branch
                : ($key === 'rhea' ? 'wakrah' : 'old_airport');
            $branchLabel = $branch === 'wakrah' ? 'Al Wakrah' : 'Old Airport';
            $ownerKey = $branch === 'wakrah' ? 'rhea' : 'ahana';
            $ownerName = $branch === 'wakrah' ? 'Rhea' : 'Ahana';

            $data = $this->buildBranchManagerScorecard(
                owner: $ownerName,
                role: "{$branchLabel} Branch Manager",
                branch: $branch,
                periodStart: $window['period_start'],
                periodEnd: $window['period_end'],
                isCurrentMonth: $isCurrentMonth
            );
            $vm = $this->presentBranchManager($ownerKey, $data, $ctx, $effectiveViewer, $displayName);
            $vm['display_name'] = $displayName;
            $vm['role_subtitle'] = "{$roleLabel} ({$branchLabel}) — {$data['total_kpis_count']} Executive KPIs";
            $vm['raw'] = $data;

            return $vm;
        }

        if (in_array($key, ['areeba', 'zoya', 'chat_agent'], true)) {
            if (in_array($key, ['areeba', 'zoya'], true)) {
                $ownerName = $key === 'areeba' ? 'Areeba' : 'Zoya';
                $data = $this->buildChatAgentScorecard(
                    owner: $ownerName,
                    role: 'Customer Service Chat Agent',
                    periodStart: $window['period_start'],
                    periodEnd: $window['period_end'],
                    referenceDate: $window['reference_date'],
                    daysElapsed: $window['days_elapsed'],
                    daysInMonth: $window['days_in_month']
                );
                $vm = $this->presentChatAgent($key, $data, $ctx, $effectiveViewer, $displayName);
            } else {
                $agentIds = $targetUser->exists ? collect([$targetUser->id]) : collect();
                $data = $this->buildChatAgentScorecardForUserIds(
                    owner: $displayName,
                    role: 'Customer Service Chat Agent',
                    agentUserIds: $agentIds,
                    periodStart: $window['period_start'],
                    periodEnd: $window['period_end'],
                    referenceDate: $window['reference_date'],
                    daysElapsed: $window['days_elapsed'],
                    daysInMonth: $window['days_in_month']
                );
                $vm = $this->presentChatAgent('chat_agent', $data, $ctx, $effectiveViewer, $displayName);
            }

            $vm['display_name'] = $displayName;
            $vm['role_subtitle'] = "{$roleLabel} — 13 Bookings/Day Quota";
            $vm['raw'] = $data;

            return $vm;
        }

        if ($key === 'arbab') {
            $data = $this->buildArbabSection(
                periodStart: $window['period_start'],
                periodEnd: $window['period_end'],
                referenceDate: $window['reference_date']
            );
            $vm = $this->presentArbab($data, $ctx, $effectiveViewer, $displayName);
            $vm['display_name'] = $displayName;
            $vm['role_subtitle'] = "{$roleLabel} — {$data['total_kpis_count']} Operational KPIs";
            $vm['raw'] = $data;

            return $vm;
        }

        if ($key === 'salon_staff') {
            return $this->presentSalonStaff(
                displayName: $displayName,
                roleLabel: $roleLabel,
                periodLabel: $ctx['period_range_label'],
                weeklyEvaluations: $weeklyEvaluations ?? [],
                myComplaints: $myComplaints ?? []
            );
        }

        // Graceful Admin / unassigned-role fallback
        $canViewKpis = $effectiveViewer->isAdmin() || $effectiveViewer->canView('kpis');

        return [
            'owner_key' => 'general',
            'initials' => $this->initialsFor($displayName),
            'name' => $displayName,
            'display_name' => $displayName,
            'role_subtitle' => $roleLabel,
            'period_label' => $ctx['period_range_label'],
            'overall_pct' => null,
            'overall_badge' => null,
            'orb_class' => 'orb-rose',
            'tag_class' => 'tag-muted',
            'columns' => 3,
            'cards' => [],
            'action_label' => $canViewKpis ? 'Open Executive KPIs' : null,
            'action_url' => $canViewKpis ? route('kpi.hub') : null,
            'empty_message' => 'No individual KPI targets are configured for this role. View team-wide scorecards in Executive KPIs.',
            'raw' => [],
        ];
    }

    /**
     * Load all SocialContentPlan day rows whose exact calendar date falls strictly within [$rangeStart, $rangeEnd].
     */
    public function inMonthContentPlans(Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        if (!Schema::hasTable('social_content_plans')) {
            return collect();
        }

        $minDateStr = $rangeStart->toDateString();
        $maxDateStr = $rangeEnd->toDateString();
        $firstMondayStr = $rangeStart->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $lastMondayStr = $rangeEnd->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        return SocialContentPlan::query()
            ->whereDate('week_start_date', '>=', $firstMondayStr)
            ->whereDate('week_start_date', '<=', $lastMondayStr)
            ->get()
            ->filter(function (SocialContentPlan $plan) use ($minDateStr, $maxDateStr): bool {
                $exact = $plan->exactDate()->toDateString();

                return $exact >= $minDateStr && $exact <= $maxDateStr;
            })
            ->values();
    }

    public static function healthOrbClass(float $pct): string
    {
        if ($pct >= 80.0) {
            return 'orb-emerald';
        }
        if ($pct >= 50.0) {
            return 'orb-amber';
        }

        return 'orb-berry';
    }

    public static function healthBarClass(float $pct): string
    {
        if ($pct >= 80.0) {
            return 'fill-emerald';
        }
        if ($pct >= 50.0) {
            return 'fill-champagne';
        }

        return 'fill-rose-gold';
    }

    // =========================================================================
    // SECTION BUILDERS (SINGLE SOURCE OF TRUTH FOR ALL KPI FORMULAS)
    // =========================================================================

    private function resolvePeriodWindow(
        string $selectedMonth,
        Carbon $monthStart,
        Carbon $monthEnd,
        bool $isCurrentMonth,
        ?Carbon $nowOverride = null
    ): array {
        $nowQatar = $nowOverride ? $nowOverride->copy()->timezone('Asia/Qatar') : now('Asia/Qatar');
        $periodStart = $monthStart->copy()->startOfDay();
        $periodEnd = $isCurrentMonth ? $nowQatar->copy()->endOfDay() : $monthEnd->copy()->endOfDay();
        $daysInMonth = max(1, (int) $monthStart->daysInMonth);
        $daysElapsed = $isCurrentMonth ? max(1, (int) $nowQatar->day) : $daysInMonth;
        $referenceDate = $isCurrentMonth ? $nowQatar->copy() : $monthEnd->copy();

        $periodRangeLabel = $isCurrentMonth
            ? sprintf('%s – %s (MTD • Day %d/%d)', $periodStart->format('M j'), $periodEnd->format('M j, Y'), $daysElapsed, $daysInMonth)
            : sprintf('%s – %s', $periodStart->format('M j'), $periodEnd->format('M j, Y'));

        $shortPeriodBadge = sprintf('%s – %s', $periodStart->format('M j'), $periodEnd->format('M j'));

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'days_in_month' => $daysInMonth,
            'days_elapsed' => $daysElapsed,
            'reference_date' => $referenceDate,
            'period_range_label' => $periodRangeLabel,
            'short_period_badge' => $shortPeriodBadge,
        ];
    }

    private function buildSanaSection(
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $monthStart,
        bool $isCurrentMonth,
        int $daysElapsed,
        string $shortPeriodBadge
    ): array {
        $effectiveEnd = $isCurrentMonth
            ? $periodEnd->copy()->endOfDay()
            : $monthStart->copy()->endOfMonth()->endOfDay();

        $inMonthPlans = $this->inMonthContentPlans($monthStart, $effectiveEnd);

        $firstInMonthMonday = $monthStart->copy()->startOfDay();
        while (!$firstInMonthMonday->isMonday()) {
            $firstInMonthMonday->addDay();
        }
        $firstInMonthMondayStr = $firstInMonthMonday->toDateString();

        if ($isCurrentMonth) {
            $currentMonday = now('Asia/Qatar')->startOfWeek(Carbon::MONDAY);
            $inMonthWeekStart = $currentMonday->lt($monthStart)
                ? $firstInMonthMondayStr
                : $currentMonday->toDateString();
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

        $weekPlans = Schema::hasTable('social_content_plans')
            ? SocialContentPlan::query()->where('week_start_date', $inMonthWeekStart)->get()
            : collect();

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

        $daysInMonth = max(1, (int) $monthStart->daysInMonth);
        $completedDays = $isCurrentMonth ? max(0, $daysElapsed - 1) : $daysInMonth;
        $fullMonthStoriesTarget = $daysInMonth;
        $fullMonthReelsTarget = (int) floor(($daysInMonth * self::SANA_WEEKLY_REELS_TARGET) / 7);
        $monthlyItemsTarget = $fullMonthReelsTarget + $fullMonthStoriesTarget;
        $elapsedWeeks = max(1, min(5, (int) ceil($daysElapsed / 7)));

        if ($completedDays === 0) {
            $mtdStoriesTarget = 0;
            $mtdReelsTarget = 0;
            $mtdItemsTarget = 0;
            $sanaContentPassing = $hasMtdSocialActivity;
            $weeklyContentExecutionPct = $hasMtdSocialActivity ? 100.0 : 0.0;
        } else {
            $mtdStoriesTarget = $completedDays;
            $mtdReelsTarget = (int) floor(($completedDays * self::SANA_WEEKLY_REELS_TARGET) / 7);
            $mtdItemsTarget = $mtdReelsTarget + $mtdStoriesTarget;

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

    private function buildAyanSection(
        string $selectedMonth,
        Carbon $periodStart,
        Carbon $periodEnd,
        int $daysInMonth,
        int $daysElapsed,
        array $sanaData
    ): array {
        $adLeadColumns = ['id', 'date', 'customer_id', 'phone', 'branch', 'ticket_amount', 'category', 'lead_id'];
        if (Schema::hasTable('ad_lead_entries') && Schema::hasColumn('ad_lead_entries', 'old_airport_amount')) {
            $adLeadColumns[] = 'old_airport_amount';
        }
        if (Schema::hasTable('ad_lead_entries') && Schema::hasColumn('ad_lead_entries', 'wakrah_amount')) {
            $adLeadColumns[] = 'wakrah_amount';
        }

        $monthAdLeads = Schema::hasTable('ad_lead_entries')
            ? AdLeadEntry::query()->whereBetween('date', [$periodStart, $periodEnd])->get($adLeadColumns)
            : collect();

        $oaBookedAdLeads = $monthAdLeads->filter(fn (AdLeadEntry $e) => $e->isBooked() && $e->branch === 'old_airport');
        $wkBookedAdLeads = $monthAdLeads->filter(fn (AdLeadEntry $e) => $e->isBooked() && $e->branch === 'wakrah');

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
        $adConversionPct = $totalAdLeadsCount > 0 ? round(($bookedAdLeadsCount / $totalAdLeadsCount) * 100, 1) : 0.0;
        $adConversionAchievementPct = round(min(100.0, ($adConversionPct / self::AYAN_AD_CONVERSION_TARGET_PCT) * 100), 1);

        $monthlyAds = Schema::hasTable('kpi_monthly_ads')
            ? KpiMonthlyAd::query()->where('plan_month', $selectedMonth)->get(['id', 'phase', 'total_budget', 'target_branch'])
            : collect();

        $scalingAdsCount = $monthlyAds->where('phase', 'Scaling')->count();
        $testingAdsCount = $monthlyAds->where('phase', 'Testing')->count();

        $ayanKpi3Pct = (float) ($sanaData['overall_score_pct'] ?? 0);
        $ayanKpi3Passing = ((int) ($sanaData['below_floor_count'] ?? 1) === 0);
        $effectiveLateReplies = (int) ($sanaData['late_replies_count'] ?? 0);

        $oaRevPassing = ($oaAdRevenueQar >= $mtdBranchAdTarget);
        $wkRevPassing = ($wkAdRevenueQar >= $mtdBranchAdTarget);
        $ayanConvPassing = ($adConversionPct >= self::AYAN_AD_CONVERSION_TARGET_PCT);

        $ayanPassingCount = ($oaRevPassing ? 1 : 0) + ($wkRevPassing ? 1 : 0) + ($ayanConvPassing ? 1 : 0) + ($ayanKpi3Passing ? 1 : 0);
        $ayanTotalKpis = 4;
        $ayanFailingCount = $ayanTotalKpis - $ayanPassingCount;
        $ayanOverallScorePct = round(($oaAdMtdPct + $wkAdMtdPct + $adConversionAchievementPct + $ayanKpi3Pct) / 4, 1);

        return [
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
    }

    private function buildLadenSection(
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $monthEnd,
        bool $isCurrentMonth,
        int $daysInMonth,
        int $daysElapsed
    ): array {
        $qatarTz = AgentBookingQuotaCalculator::QATAR_TIMEZONE;
        $referenceDate = $isCurrentMonth ? now($qatarTz) : $monthEnd->copy()->timezone($qatarTz);
        $todayBounds = AgentBookingQuotaCalculator::qatarDayBounds($referenceDate);

        $chatAgentIds = User::where('role', 'agent')->pluck('id');
        $todayBookingsCount = 0;
        $mtdBookingsCount = 0;

        if ($chatAgentIds->isNotEmpty()) {
            $dedupedMtdAppointments = AgentBookingQuotaCalculator::fetchForRange(
                $chatAgentIds,
                $periodStart,
                $periodEnd
            );
            $mtdBookingsCount = $dedupedMtdAppointments->count();
            $todayBookingsCount = $dedupedMtdAppointments
                ->filter(fn (Appointment $appt) => AgentBookingQuotaCalculator::isCreatedWithinBounds($appt, $todayBounds))
                ->count();
        }

        $dailyTarget = self::LADEN_DAILY_BOOKING_QUOTA;
        $fullMonthTargetBookings = $dailyTarget * $daysInMonth;
        $mtdTargetBookings = $dailyTarget * $daysElapsed;
        $todayBookingQuotaPct = round(min(100.0, ($todayBookingsCount / $dailyTarget) * 100), 1);
        $mtdBookingQuotaPct = min(100.0, round(($mtdBookingsCount / max(1, $mtdTargetBookings)) * 100, 1));
        $ladenBookingPassing = ($mtdBookingsCount >= $mtdTargetBookings);
        $bookingStatusLabel = $mtdBookingQuotaPct >= 100.0
            ? 'ON TARGET'
            : ($mtdBookingQuotaPct >= 85.0 ? 'NEAR TARGET' : 'BELOW QUOTA');

        // KPI 2: Agent Chat Evaluation Score (Target: >= 85%)
        $monthChatEvals = Schema::hasTable('kpi_chat_evaluations')
            ? KpiChatEvaluation::query()
                ->whereBetween('evaluation_date', [$periodStart, $periodEnd])
                ->get(['id', 'agent_id', 'percentage', 'total_score'])
            : collect();

        $chatEvalsCount = $monthChatEvals->count();
        $chatEvalAvgPct = $chatEvalsCount > 0
            ? round((float) $monthChatEvals->avg('percentage'), 1)
            : 0.0;
        $ladenChatPassing = ($chatEvalsCount > 0 && $chatEvalAvgPct >= self::LADEN_CHAT_EVAL_TARGET_PCT);
        $chatEvalAchievementPct = round(min(100.0, ($chatEvalAvgPct / self::LADEN_CHAT_EVAL_TARGET_PCT) * 100), 1);
        $chatStatusLabel = $ladenChatPassing ? 'PASS' : 'BELOW TARGET';

        // KPI 3: Lead Management & Conversion Rate (Target: >= 30%)
        $monthLeads = Schema::hasTable('leads')
            ? Lead::with('appointment:id,status')
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->get(['id', 'appointment_id', 'needful_done', 'assigned_agent_id', 'next_followup_date'])
            : collect();

        $totalLeadsCount = $monthLeads->count();
        $bookedLeadsCount = $monthLeads->filter(fn (Lead $lead) => $lead->isBookedConversion())->count();
        $leadConversionPct = $totalLeadsCount > 0
            ? round(($bookedLeadsCount / $totalLeadsCount) * 100, 1)
            : 0.0;
        $ladenLeadConvPassing = ($totalLeadsCount > 0 && $leadConversionPct >= self::LADEN_LEAD_CONVERSION_TARGET_PCT);
        $leadConvAchievementPct = round(min(100.0, ($leadConversionPct / self::LADEN_LEAD_CONVERSION_TARGET_PCT) * 100), 1);
        $leadStatusLabel = $ladenLeadConvPassing ? 'ON TRACK' : 'BELOW FLOOR';

        $openPipelineLeads = Schema::hasTable('leads')
            ? Lead::query()
                ->where(function ($q) {
                    $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
                })
                ->get(['id', 'assigned_agent_id', 'next_followup_date'])
            : collect();

        $unassignedLeadsCount = $openPipelineLeads->whereNull('assigned_agent_id')->count();
        $overdueFollowupsCount = Schema::hasTable('leads')
            ? Lead::query()
                ->whereNotNull('next_followup_date')
                ->whereDate('next_followup_date', '<', today())
                ->where(function ($q) {
                    $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
                })
                ->count()
            : 0;

        // KPI 4: Complaint Resolution SLA (<= 48 Hours)
        $monthComplaints = Schema::hasTable('staff_complaints')
            ? StaffComplaint::with('notices')
                ->whereDate('complaint_date', '>=', $periodStart->toDateString())
                ->whereDate('complaint_date', '<=', $periodEnd->toDateString())
                ->get()
            : collect();

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
            'lead_status_label' => $leadStatusLabel,
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

        return $this->buildChatAgentScorecardForUserIds(
            owner: $owner,
            role: $role,
            agentUserIds: $agentUserIds,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            referenceDate: $referenceDate,
            daysElapsed: $daysElapsed,
            daysInMonth: $daysInMonth
        );
    }

    private function buildChatAgentScorecardForUserIds(
        string $owner,
        string $role,
        Collection $agentUserIds,
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $referenceDate,
        int $daysElapsed,
        int $daysInMonth
    ): array {
        $dailyTarget = self::AGENT_DAILY_BOOKING_QUOTA;
        $agentMtdTarget = $dailyTarget * $daysElapsed;
        $agentFullMonthTarget = $dailyTarget * $daysInMonth;
        $todayBounds = AgentBookingQuotaCalculator::qatarDayBounds($referenceDate);

        $todayBookings = 0;
        $mtdBookings = 0;

        if ($agentUserIds->isNotEmpty()) {
            $dedupedMtdAppointments = AgentBookingQuotaCalculator::fetchForRange(
                $agentUserIds,
                $periodStart,
                $periodEnd
            );
            $mtdBookings = $dedupedMtdAppointments->count();
            $todayBookings = $dedupedMtdAppointments
                ->filter(fn (Appointment $appt) => AgentBookingQuotaCalculator::isCreatedWithinBounds($appt, $todayBounds))
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

        $activeBranchStaffCount = Staff::salonEvaluatable($branch, $periodEnd->toDateString())->count();

        $allMondays = [];
        foreach (CarbonPeriod::create($periodStart->copy()->startOfDay(), $periodEnd->copy()->startOfDay()) as $day) {
            if ($day->isMonday()) {
                $allMondays[] = $day->toDateString();
            }
        }

        $evalRows = Schema::hasTable('kpi_staff_evaluations')
            ? KpiStaffEvaluation::query()
                ->where('branch', $branch)
                ->whereDate('evaluation_date', '>=', $periodStart->toDateString())
                ->whereDate('evaluation_date', '<=', $periodEnd->toDateString())
                ->get(['staff_id', 'evaluation_date'])
            : collect();

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
        $audits = Schema::hasTable('branch_evaluations')
            ? BranchEvaluation::query()
                ->where('branch', $branch)
                ->whereDate('evaluation_date', '>=', $periodStart->toDateString())
                ->whereDate('evaluation_date', '<=', $periodEnd->toDateString())
                ->get(['id', 'percentage', 'total_score', 'grade', 'evaluation_date'])
            : collect();

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
     * Build Arbab (Operations Lead) KPIs: Overdue Tasks, Ops Actions Completion, SLA On-Time.
     * Matches DashboardScorecardBuilder::buildArbabCard() formulas cleanly.
     */
    private function buildArbabSection(Carbon $periodStart, Carbon $periodEnd, Carbon $referenceDate): array
    {
        $currentMonday = $referenceDate->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        OpsActionItem::$referenceWeekMonday = $currentMonday->toDateString();

        $activeWeekItems = Schema::hasTable('ops_action_items')
            ? OpsActionItem::forWeek($currentMonday)->get()
            : collect();
        $periodItems = Schema::hasTable('ops_action_items')
            ? OpsActionItem::query()->whereBetween('created_at', [$periodStart, $periodEnd])->get()
            : collect();
        $openItems = Schema::hasTable('ops_action_items')
            ? OpsActionItem::query()->where('is_completed', false)->executionOrder()->get()
            : collect();

        /** @var Collection<int, OpsActionItem> $opsItems */
        $opsItems = $activeWeekItems->concat($periodItems)->concat($openItems)->unique('id')->values();
        $totalCount = $opsItems->count();
        $completedCount = $opsItems->where('is_completed', true)->count();
        $pendingCount = $openItems->count();
        $overdueCount = $openItems->filter(fn (OpsActionItem $i) => (bool) $i->is_overdue)->count();
        $urgentCount = $openItems->filter(fn (OpsActionItem $i) => $i->priority === 'urgent')->count();

        $onTimeCount = $opsItems->filter(function (OpsActionItem $item): bool {
            if (!$item->is_completed) {
                return !$item->is_overdue;
            }
            if (!$item->completed_at) {
                return true;
            }

            return Carbon::parse($item->completed_at)->lte($item->effectiveDueAt());
        })->count();

        if ($totalCount === 0) {
            $zeroOverduePct = 100.0;
            $completionPct = 100.0;
            $slaPct = 100.0;
        } else {
            $zeroOverduePct = $overdueCount === 0
                ? 100.0
                : max(0.0, 45.0 - (($overdueCount - 1) * 20.0));
            $completionPct = round(($completedCount / $totalCount) * 100, 1);
            $slaPct = round(($onTimeCount / $totalCount) * 100, 1);
        }

        $overduePassing = ($overdueCount === 0);
        $completionPassing = ($totalCount === 0 || $completionPct >= self::ARBAB_COMPLETION_TARGET_PCT);
        $slaPassing = ($totalCount === 0 || $slaPct >= self::ARBAB_SLA_TARGET_PCT);

        $passingCount = ($overduePassing ? 1 : 0) + ($completionPassing ? 1 : 0) + ($slaPassing ? 1 : 0);
        $totalKpis = 3;
        $overallPct = round(($zeroOverduePct + $completionPct + $slaPct) / 3, 1);

        $openItemList = $openItems->take(12)->map(fn (OpsActionItem $i) => [
            'id' => $i->id,
            'title' => $i->title,
            'scope' => $i->scope,
            'priority' => $i->priority,
            'is_overdue' => (bool) $i->is_overdue,
            'due_label' => $i->effectiveDueAt()->timezone('Asia/Qatar')->format('d M, g:i A'),
            'assignees' => implode(', ', $i->assignee_list),
        ])->values()->all();

        return [
            'owner' => 'Arbab',
            'role' => 'Operations Lead',
            'total_count' => $totalCount,
            'completed_count' => $completedCount,
            'pending_count' => $pendingCount,
            'overdue_count' => $overdueCount,
            'urgent_count' => $urgentCount,
            'on_time_count' => $onTimeCount,
            'zero_overdue_pct' => $zeroOverduePct,
            'completion_pct' => $completionPct,
            'sla_pct' => $slaPct,
            'overdue_passing' => $overduePassing,
            'completion_passing' => $completionPassing,
            'sla_passing' => $slaPassing,
            'overall_score_pct' => $overallPct,
            'passing_kpis_count' => $passingCount,
            'total_kpis_count' => $totalKpis,
            'below_floor_count' => $totalKpis - $passingCount,
            'is_passing_all' => $passingCount === $totalKpis,
            'open_items' => $openItemList,
        ];
    }

    /**
     * Resolve Sundus's user ID(s) reliably (matches `Sundus` / `Sundas` by name or email).
     *
     * @return Collection<int, int>
     */
    public function resolveSundusUserIds(): Collection
    {
        if (!Schema::hasTable('users')) {
            return collect();
        }

        return User::query()
            ->where(function ($q) {
                $q->whereRaw("LOWER(COALESCE(name, '')) LIKE '%sundus%'")
                    ->orWhereRaw("LOWER(COALESCE(name, '')) LIKE '%sundas%'")
                    ->orWhereRaw("LOWER(COALESCE(email, '')) LIKE '%sundus%'")
                    ->orWhereRaw("LOWER(COALESCE(email, '')) LIKE '%sundas%'");
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /**
     * Build Sundus (Admin — Branch Execution Audits) Execution KPIs:
     * 1. Old Airport Branch Evaluations (Target: 3 / week, scaled cleanly to MTD weeks)
     * 2. Al Wakrah Branch Evaluations (Target: 3 / week, scaled cleanly to MTD weeks)
     *
     * STRICT ATTRIBUTION: counts ONLY Branch Evaluations submitted under Sundus's login
     * (`created_by IN sundusUserIds`). Evaluations submitted by any other admin are excluded.
     */
    private function buildSundusSection(
        Carbon $periodStart,
        Carbon $periodEnd,
        Carbon $referenceDate,
        bool $isCurrentMonth,
        ?Collection $explicitUserIds = null
    ): array {
        $sundusIds = $explicitUserIds && $explicitUserIds->isNotEmpty()
            ? $explicitUserIds->map(fn ($id) => (int) $id)->values()
            : $this->resolveSundusUserIds();

        // Weekly cadence scaling: count distinct Mondays elapsed in [$periodStart, $periodEnd] (minimum 1 week)
        $mondaysCount = 0;
        foreach (CarbonPeriod::create($periodStart->copy()->startOfDay(), $periodEnd->copy()->startOfDay()) as $day) {
            if ($day->isMonday()) {
                $mondaysCount++;
            }
        }
        $weeksElapsed = max(1, $mondaysCount);
        $weeklyTarget = self::SUNDUS_WEEKLY_BRANCH_EVAL_TARGET;
        $mtdTargetPerBranch = $weeksElapsed * $weeklyTarget;

        // Current Monday–Sunday week bounds in Asia/Qatar (clamped to selected period for past months)
        $weekStart = $referenceDate->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        if (!$isCurrentMonth) {
            if ($weekStart->lt($periodStart)) {
                $weekStart = $periodStart->copy()->startOfDay();
            }
            if ($weekEnd->gt($periodEnd)) {
                $weekEnd = $periodEnd->copy()->endOfDay();
            }
        }

        $oaMtd = 0;
        $wkMtd = 0;
        $oaWeek = 0;
        $wkWeek = 0;

        if ($sundusIds->isNotEmpty() && Schema::hasTable('branch_evaluations') && Schema::hasColumn('branch_evaluations', 'created_by')) {
            $mtdRows = BranchEvaluation::query()
                ->whereIn('created_by', $sundusIds)
                ->whereDate('evaluation_date', '>=', $periodStart->toDateString())
                ->whereDate('evaluation_date', '<=', $periodEnd->toDateString())
                ->get(['branch', 'evaluation_date']);

            $oaMtd = $mtdRows->where('branch', 'old_airport')->count();
            $wkMtd = $mtdRows->where('branch', 'wakrah')->count();

            $weekRows = BranchEvaluation::query()
                ->whereIn('created_by', $sundusIds)
                ->whereDate('evaluation_date', '>=', $weekStart->toDateString())
                ->whereDate('evaluation_date', '<=', $weekEnd->toDateString())
                ->get(['branch', 'evaluation_date']);

            $oaWeek = $weekRows->where('branch', 'old_airport')->count();
            $wkWeek = $weekRows->where('branch', 'wakrah')->count();
        }

        $oaPct = min(100.0, round(($oaMtd / max(1, $mtdTargetPerBranch)) * 100, 1));
        $wkPct = min(100.0, round(($wkMtd / max(1, $mtdTargetPerBranch)) * 100, 1));
        $oaPassing = ($oaMtd >= $mtdTargetPerBranch);
        $wkPassing = ($wkMtd >= $mtdTargetPerBranch);

        $passingCount = ($oaPassing ? 1 : 0) + ($wkPassing ? 1 : 0);
        $totalKpis = 2;
        $overallPct = round(($oaPct + $wkPct) / 2, 1);

        return [
            'owner' => 'Sundus',
            'role' => 'Admin — Branch Execution Audits',
            'sundus_user_ids' => $sundusIds->all(),
            'weekly_target' => $weeklyTarget,
            'weeks_elapsed' => $weeksElapsed,
            'mtd_target_per_branch' => $mtdTargetPerBranch,
            'old_airport_mtd' => $oaMtd,
            'old_airport_week' => $oaWeek,
            'old_airport_pct' => $oaPct,
            'old_airport_passing' => $oaPassing,
            'wakrah_mtd' => $wkMtd,
            'wakrah_week' => $wkWeek,
            'wakrah_pct' => $wkPct,
            'wakrah_passing' => $wkPassing,
            'overall_score_pct' => $overallPct,
            'passing_kpis_count' => $passingCount,
            'total_kpis_count' => $totalKpis,
            'below_floor_count' => $totalKpis - $passingCount,
            'is_passing_all' => $passingCount === $totalKpis,
        ];
    }

    /**
     * Batch-build lightweight MTD scorecards for all active branch beauticians (`Salon Staff`)
     * using O(1) batch queries so `/kpis` can display & inspect them cleanly under the Salon Staff section.
     *
     * @return list<array<string, mixed>>
     */
    private function buildSalonStaffSections(Carbon $periodStart, Carbon $periodEnd): array
    {
        if (!Schema::hasTable('staff')) {
            return [];
        }

        $beauticians = Staff::salonEvaluatable(null, $periodEnd->toDateString())
            ->with('user:id,name,role')
            ->orderBy('name')
            ->get(['id', 'name', 'branch', 'is_remote', 'bookable', 'user_id']);

        if ($beauticians->isEmpty()) {
            return [];
        }

        $staffIds = $beauticians->pluck('id')->map(fn ($id) => (int) $id)->all();

        $evalsByStaff = Schema::hasTable('kpi_staff_evaluations')
            ? KpiStaffEvaluation::query()
                ->whereIn('staff_id', $staffIds)
                ->whereDate('evaluation_date', '>=', $periodStart->toDateString())
                ->whereDate('evaluation_date', '<=', $periodEnd->toDateString())
                ->orderByDesc('evaluation_date')
                ->orderByDesc('id')
                ->get(['id', 'staff_id', 'evaluation_date', 'overall_average'])
                ->groupBy('staff_id')
            : collect();

        $complaintsByStaff = [];
        if (Schema::hasTable('staff_complaints') && Schema::hasTable('complaint_staff')) {
            $mtdComplaints = StaffComplaint::query()
                ->with([
                    'staffMembers' => fn ($q) => $q->whereIn('staff.id', $staffIds)->select('staff.id'),
                    'notices:id,complaint_id',
                ])
                ->whereHas('staffMembers', fn ($q) => $q->whereIn('staff.id', $staffIds))
                ->whereDate('complaint_date', '>=', $periodStart->toDateString())
                ->whereDate('complaint_date', '<=', $periodEnd->toDateString())
                ->orderByDesc('complaint_date')
                ->orderByDesc('id')
                ->get(['id', 'complaint_date', 'is_staff_mistake', 'resolved_at']);

            foreach ($mtdComplaints as $complaint) {
                $isResolved = $complaint->resolved_at !== null
                    || $complaint->is_staff_mistake !== null
                    || $complaint->notices->isNotEmpty();

                foreach ($complaint->staffMembers as $member) {
                    $complaintsByStaff[(int) $member->id][] = [
                        'status' => $isResolved ? 'Resolved' : 'Under Review',
                    ];
                }
            }
        }

        $rows = [];
        foreach ($beauticians as $s) {
            $branchKey = strtolower(trim((string) $s->branch)) === 'wakrah' ? 'wakrah' : 'old_airport';
            $branchLabel = $branchKey === 'wakrah' ? 'Al Wakrah' : 'Old Airport';

            $evals = $evalsByStaff->get($s->id, collect())
                ->map(fn (KpiStaffEvaluation $e) => [
                    'score' => (float) $e->overall_average,
                    'date_label' => $e->evaluation_date
                        ? Carbon::parse($e->evaluation_date, 'Asia/Qatar')->format('d M Y')
                        : '—',
                ])
                ->values()
                ->all();

            $complaints = $complaintsByStaff[(int) $s->id] ?? [];

            $evalCount = count($evals);
            $evalAvg = $evalCount > 0 ? round(collect($evals)->avg('score'), 2) : null;
            $evalAvgPct = $evalAvg !== null ? min(100.0, round(($evalAvg / 5.0) * 100, 1)) : 0.0;
            $evalPass = $evalAvg !== null && $evalAvg >= 4.0;
            $complaintsCount = count($complaints);
            $complaintsClean = $complaintsCount === 0;
            $passing = ($evalPass ? 1 : 0) + ($complaintsClean ? 1 : 0);
            $overallPct = round(($evalAvgPct + ($complaintsClean ? 100.0 : max(0.0, 100.0 - ($complaintsCount * 25.0)))) / 2, 1);

            $rows[] = [
                'owner_key' => 'staff_' . $s->id,
                'staff_id' => (int) $s->id,
                'name' => $s->name,
                'initials' => $this->initialsFor($s->name),
                'branch' => $branchKey,
                'branch_label' => $branchLabel,
                'eval_count' => $evalCount,
                'eval_avg' => $evalAvg,
                'eval_pass' => $evalPass,
                'complaints_count' => $complaintsCount,
                'complaints_clean' => $complaintsClean,
                'passing_kpis_count' => $passing,
                'total_kpis_count' => 2,
                'overall_score_pct' => $overallPct,
                'weekly_evaluations' => $evals,
                'complaints' => $complaints,
            ];
        }

        return $rows;
    }

    // =========================================================================
    // CANONICAL PRESENTATION VIEW-MODELS (SHARED BY `/kpis` & `/my-profile`)
    // =========================================================================

    private function extractContextFromExecutiveScorecard(array $scorecard): array
    {
        $monthStr = (string) ($scorecard['month'] ?? now('Asia/Qatar')->format('Y-m'));
        $monthCarbon = Carbon::createFromFormat('Y-m-d', $monthStr . '-01');

        return [
            'month' => $monthStr,
            'month_label' => $monthCarbon->format('F Y'),
            'is_current_month' => (bool) ($scorecard['is_current_month'] ?? true),
            'period_start' => (string) ($scorecard['period_start'] ?? $monthCarbon->copy()->startOfMonth()->toDateString()),
            'period_end' => (string) ($scorecard['period_end'] ?? now('Asia/Qatar')->toDateString()),
            'period_range_label' => (string) ($scorecard['period_range_label'] ?? ''),
            'short_period_badge' => (string) ($scorecard['short_period_badge'] ?? ''),
            'days_elapsed' => (int) ($scorecard['days_elapsed'] ?? max(1, now('Asia/Qatar')->day)),
            'days_in_month' => (int) ($scorecard['days_in_month'] ?? 30),
        ];
    }

    private function presentAyan(array $ayan, array $sana, array $ctx, ?User $viewer): array
    {
        $oaRev = (float) ($ayan['old_airport_ad_revenue_qar'] ?? 0);
        $oaMtdPct = (float) ($ayan['old_airport_mtd_pct'] ?? 0);
        $oaPass = !empty($ayan['old_airport_mtd_passing']);

        $wkRev = (float) ($ayan['wakrah_ad_revenue_qar'] ?? 0);
        $wkMtdPct = (float) ($ayan['wakrah_mtd_pct'] ?? 0);
        $wkPass = !empty($ayan['wakrah_mtd_passing']);

        $mtdBranchTarget = (float) ($ayan['mtd_branch_target_qar'] ?? 2500);
        $adConv = (float) ($ayan['ad_conversion_pct'] ?? 0);
        $adConvBarPct = (float) ($ayan['ad_conversion_achievement_pct'] ?? 0);
        $adConvPass = $adConv >= self::AYAN_AD_CONVERSION_TARGET_PCT;

        $ayanOversightPct = (float) ($ayan['social_oversight_pct'] ?? 0);
        $ayanOversightPass = !empty($ayan['social_oversight_passing']);
        $overallPct = (float) ($ayan['overall_score_pct'] ?? 0);
        $passing = (int) ($ayan['passing_kpis_count'] ?? 0);
        $total = (int) ($ayan['total_kpis_count'] ?? 4);

        $sanaPostedTotal = (int) ($sana['mtd_items_completed'] ?? 0);
        $sanaCumulativeTarget = (int) ($sana['mtd_items_target'] ?? 0);
        $contentPass = !empty($sana['content_passing']);
        $repliesPass = !empty($sana['replies_sla_passing']);
        $lateCount = (int) ($sana['late_replies_count'] ?? 0);
        $sanaWeek = (string) ($sana['in_month_week_start'] ?? now('Asia/Qatar')->startOfWeek(Carbon::MONDAY)->toDateString());

        $adsUrl = $this->safeUrl($viewer, 'kpis', 'kpi.ads.index', ['month' => $ctx['month']]);
        $contentUrl = $this->safeUrl($viewer, 'kpis', 'kpi.content-calendar.index', ['week' => $sanaWeek]);

        return [
            'owner_key' => 'ayan',
            'initials' => 'AY',
            'name' => 'Ayan',
            'display_name' => 'Ayan',
            'role_subtitle' => "Marketing & Ads Lead — {$total} Executive KPIs",
            'period_label' => $ctx['period_range_label'],
            'overall_pct' => $overallPct,
            'passing_count' => $passing,
            'total_kpis' => $total,
            'overall_badge' => number_format($overallPct, 1) . "% Overall ({$passing}/{$total} Passing)",
            'orb_class' => self::healthOrbClass($overallPct),
            'tag_class' => $passing === $total ? 'tag-pass' : 'tag-ayan',
            'columns' => 2,
            'action_label' => $adsUrl ? 'Open Ads Conversion' : null,
            'action_url' => $adsUrl,
            'cards' => [
                [
                    'owner_key' => 'ayan',
                    'icon' => 'dollar',
                    'orb_class' => 'orb-rose',
                    'tag_label' => 'AYAN',
                    'tag_class' => 'tag-ayan',
                    'tag_url' => $adsUrl,
                    'tag_title' => 'Open Ads Conversion',
                    'label' => 'Old Airport Ad Revenue',
                    'hero_main' => number_format($oaRev, 0),
                    'hero_unit' => 'QAR',
                    'delta_segments' => [
                        ['text' => number_format($oaMtdPct, 0) . '%', 'class' => $oaPass ? 'hl-emerald' : 'hl-rose'],
                        ['text' => ' of ' . number_format($mtdBranchTarget, 0) . ' QAR MTD target (15K Full Mo)'],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $oaMtdPct)),
                    'bar_class' => $oaPass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0%',
                    'scale_right' => '100%',
                ],
                [
                    'owner_key' => 'ayan',
                    'icon' => 'dollar',
                    'orb_class' => 'orb-champagne',
                    'tag_label' => 'AYAN',
                    'tag_class' => 'tag-ayan',
                    'tag_url' => $adsUrl,
                    'tag_title' => 'Open Ads Conversion',
                    'label' => 'Wakrah Ad Revenue',
                    'hero_main' => number_format($wkRev, 0),
                    'hero_unit' => 'QAR',
                    'delta_segments' => [
                        ['text' => number_format($wkMtdPct, 0) . '%', 'class' => $wkPass ? 'hl-emerald' : 'hl-champagne'],
                        ['text' => ' of ' . number_format($mtdBranchTarget, 0) . ' QAR MTD target (15K Full Mo)'],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $wkMtdPct)),
                    'bar_class' => $wkPass ? 'fill-emerald' : 'fill-champagne',
                    'scale_left' => '0%',
                    'scale_right' => '100%',
                ],
                [
                    'owner_key' => 'ayan',
                    'icon' => 'trending-up',
                    'orb_class' => 'orb-emerald',
                    'tag_label' => 'AYAN',
                    'tag_class' => 'tag-ayan',
                    'tag_url' => $adsUrl,
                    'tag_title' => 'Open Ads Conversion',
                    'label' => 'Ad Booking Conversion',
                    'hero_main' => number_format($adConv, 1) . '%',
                    'hero_unit' => null,
                    'delta_segments' => [
                        ['text' => 'Target: '],
                        ['text' => '≥ 20.0%', 'class' => $adConvPass ? 'hl-emerald' : 'hl-ruby'],
                        ['text' => ' booked (' . ($ayan['booked_ad_leads'] ?? 0) . '/' . ($ayan['total_ad_leads'] ?? 0) . ')'],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $adConvBarPct)),
                    'bar_class' => $adConvPass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0%',
                    'scale_right' => '20% Target',
                ],
                [
                    'owner_key' => 'ayan',
                    'icon' => 'instagram',
                    'orb_class' => $ayanOversightPass ? 'orb-emerald' : 'orb-berry',
                    'tag_label' => 'AYAN',
                    'tag_class' => 'tag-ayan',
                    'tag_url' => $contentUrl,
                    'tag_title' => 'Open Content Plan',
                    'label' => 'Content & Social SLA Oversight',
                    'hero_main' => number_format($ayanOversightPct, 1) . '%',
                    'hero_unit' => null,
                    'delta_segments' => [
                        ['text' => "{$sanaPostedTotal}/{$sanaCumulativeTarget} Content", 'class' => $contentPass ? 'hl-emerald' : 'hl-rose'],
                        ['text' => ' • '],
                        ['text' => "{$lateCount} Late Replies", 'class' => $repliesPass ? 'hl-emerald' : 'hl-ruby'],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $ayanOversightPct)),
                    'bar_class' => $ayanOversightPass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0%',
                    'scale_right' => '100%',
                ],
            ],
        ];
    }

    private function presentLaden(array $laden, array $ctx, ?User $viewer): array
    {
        $dailyTarget = (int) ($laden['daily_booking_target'] ?? 26);
        $todayBk = (int) ($laden['today_bookings_count'] ?? 0);
        $mtdBk = (int) ($laden['mtd_bookings_count'] ?? 0);
        $mtdBkTarget = (int) ($laden['mtd_target_bookings'] ?? ($dailyTarget * $ctx['days_elapsed']));
        $mtdBkPct = (float) ($laden['mtd_booking_quota_pct'] ?? 0);
        $fullMoBkTarget = (int) ($laden['full_month_target_bookings'] ?? ($dailyTarget * $ctx['days_in_month']));
        $bkPass = !empty($laden['booking_passing']);

        $chatAvg = (float) ($laden['chat_eval_avg_pct'] ?? 0);
        $chatBarPct = (float) ($laden['chat_eval_achievement_pct'] ?? 0);
        $chatPass = !empty($laden['chat_passing']);

        $leadConv = (float) ($laden['lead_conversion_pct'] ?? 0);
        $leadConvBarPct = (float) ($laden['lead_conv_achievement_pct'] ?? 0);
        $bookedLeads = (int) ($laden['booked_leads_count'] ?? 0);
        $totalLeads = (int) ($laden['total_leads_count'] ?? 0);
        $leadPass = !empty($laden['lead_conv_passing']);
        $unassigned = (int) ($laden['unassigned_leads_count'] ?? 0);
        $overdue = (int) ($laden['overdue_followups_count'] ?? 0);

        $cmpPct = (float) ($laden['complaint_sla_pct'] ?? 100);
        $cmpPass = !empty($laden['complaint_passing']);
        $openCmp = (int) ($laden['open_complaints_count'] ?? 0);

        $overallPct = (float) ($laden['overall_score_pct'] ?? 0);
        $passing = (int) ($laden['passing_kpis_count'] ?? 0);
        $total = (int) ($laden['total_kpis_count'] ?? 4);

        $agentsUrl = $this->safeUrl($viewer, 'kpis', 'kpi.agents.index', [
            'month' => $ctx['month'],
            'logs_from' => $ctx['period_start'],
            'logs_to' => $ctx['period_end'],
        ]);
        $chatEvalUrl = $this->safeUrl($viewer, 'kpis', 'kpi.chat-eval.index', ['month' => $ctx['month']]);
        $leadsUrl = $this->safeUrl($viewer, 'leads', 'leads.index', [
            'start_date' => $ctx['period_start'],
            'end_date' => $ctx['period_end'],
        ]);
        $complaintsUrl = $this->safeUrl($viewer, 'staff', 'staffs.index', [
            'tab' => 'complaints',
            'complaints_from' => $ctx['period_start'],
            'complaints_to' => $ctx['period_end'],
        ]);
        $kpiHubUrl = $this->safeUrl($viewer, 'kpis', 'kpi.hub', ['month' => $ctx['month']]);

        return [
            'owner_key' => 'laden',
            'initials' => 'LA',
            'name' => 'Laden',
            'display_name' => 'Laden',
            'role_subtitle' => "Customer Service & Sales Lead — {$total} Executive KPIs",
            'period_label' => $ctx['period_range_label'],
            'overall_pct' => $overallPct,
            'passing_count' => $passing,
            'total_kpis' => $total,
            'overall_badge' => number_format($overallPct, 1) . "% Overall ({$passing}/{$total} Passing)",
            'orb_class' => self::healthOrbClass($overallPct),
            'tag_class' => $passing === $total ? 'tag-pass' : 'tag-laden',
            'columns' => 2,
            'action_label' => $kpiHubUrl ? 'Open Executive KPIs' : null,
            'action_url' => $kpiHubUrl,
            'cards' => [
                [
                    'owner_key' => 'laden',
                    'icon' => 'calendar-check',
                    'orb_class' => 'orb-amber',
                    'tag_label' => 'LADEN',
                    'tag_class' => 'tag-laden',
                    'tag_url' => $agentsUrl,
                    'tag_title' => 'Open Agent Targets',
                    'label' => 'Daily Booking Quota',
                    'hero_main' => (string) $mtdBk,
                    'hero_unit' => "/ {$mtdBkTarget}",
                    'delta_segments' => [
                        ['text' => number_format($mtdBkPct, 0) . '% MTD', 'class' => $bkPass ? 'hl-emerald' : 'hl-champagne'],
                        ['text' => " ({$todayBk}/{$dailyTarget} Today • {$fullMoBkTarget} Full Mo)"],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $mtdBkPct)),
                    'bar_class' => $bkPass ? 'fill-emerald' : 'fill-champagne',
                    'scale_left' => '0%',
                    'scale_right' => '100%',
                ],
                [
                    'owner_key' => 'laden',
                    'icon' => 'chat-lines',
                    'orb_class' => 'orb-rose',
                    'tag_label' => 'LADEN',
                    'tag_class' => 'tag-laden',
                    'tag_url' => $chatEvalUrl,
                    'tag_title' => 'Grade Agent Chats',
                    'label' => 'Agent Chat Quality',
                    'hero_main' => number_format($chatAvg, 1) . '%',
                    'hero_unit' => null,
                    'delta_segments' => [
                        ['text' => 'Target: '],
                        ['text' => '≥ 85.0%', 'class' => $chatPass ? 'hl-emerald' : 'hl-ruby'],
                        ['text' => ' avg quality (' . ($laden['total_evaluations_count'] ?? 0) . ' graded)'],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $chatBarPct)),
                    'bar_class' => $chatPass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0%',
                    'scale_right' => '85% Target',
                ],
                [
                    'owner_key' => 'laden',
                    'icon' => 'users-check',
                    'orb_class' => 'orb-berry',
                    'tag_label' => 'LADEN',
                    'tag_class' => 'tag-laden',
                    'tag_url' => $leadsUrl,
                    'tag_title' => 'Open CRM Leads',
                    'label' => 'Lead Conversion Rate',
                    'hero_main' => number_format($leadConv, 1) . '%',
                    'hero_unit' => null,
                    'delta_segments' => [
                        ['text' => 'Target: '],
                        ['text' => "≥ 30.0% ({$bookedLeads}/{$totalLeads})", 'class' => $leadPass ? 'hl-emerald' : 'hl-champagne'],
                        ['text' => ' • '],
                        ['text' => "{$unassigned} Unassigned", 'class' => $unassigned > 0 ? 'hl-ruby' : 'hl-emerald'],
                        ['text' => ' • '],
                        ['text' => "{$overdue} Overdue", 'class' => $overdue > 0 ? 'hl-champagne' : 'hl-emerald'],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $leadConvBarPct)),
                    'bar_class' => $leadPass ? 'fill-emerald' : 'fill-champagne',
                    'scale_left' => '0%',
                    'scale_right' => '30% Target',
                ],
                [
                    'owner_key' => 'laden',
                    'icon' => 'shield-check',
                    'orb_class' => $cmpPass ? 'orb-emerald' : 'orb-ruby',
                    'tag_label' => 'LADEN',
                    'tag_class' => 'tag-laden',
                    'tag_url' => $complaintsUrl,
                    'tag_title' => 'Resolve Complaints',
                    'label' => 'Complaint Resolution SLA',
                    'hero_main' => number_format($cmpPct, 0) . '%',
                    'hero_unit' => null,
                    'delta_segments' => [
                        ['text' => "{$openCmp} Open", 'class' => $openCmp === 0 ? 'hl-emerald' : ($cmpPass ? 'hl-champagne' : 'hl-ruby')],
                        ['text' => ' • ≤ 48h resolution'],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $cmpPct)),
                    'bar_class' => $cmpPass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0%',
                    'scale_right' => '100% SLA',
                ],
            ],
        ];
    }

    private function presentSana(array $sana, array $ctx, ?User $viewer): array
    {
        $reels = (int) ($sana['mtd_published_reels'] ?? 0);
        $reelsTarget = (int) ($sana['mtd_reels_target'] ?? 0);
        $stories = (int) ($sana['mtd_published_stories'] ?? 0);
        $storiesTarget = (int) ($sana['mtd_stories_target'] ?? 0);
        $postedTotal = (int) ($sana['mtd_items_completed'] ?? ($reels + $stories));
        $cumulativeTarget = (int) ($sana['mtd_items_target'] ?? 0);
        $execPct = (float) ($sana['weekly_execution_pct'] ?? 0);
        $contentPass = !empty($sana['content_passing']);

        $lateCount = (int) ($sana['late_replies_count'] ?? 0);
        $repliesPass = !empty($sana['replies_sla_passing']);
        $overallPct = (float) ($sana['overall_score_pct'] ?? 0);
        $passing = (int) ($sana['passing_kpis_count'] ?? 0);
        $total = (int) ($sana['total_kpis_count'] ?? 2);
        $sanaWeek = (string) ($sana['in_month_week_start'] ?? now('Asia/Qatar')->startOfWeek(Carbon::MONDAY)->toDateString());

        $contentUrl = $this->safeUrl($viewer, 'kpis', 'kpi.content-calendar.index', ['week' => $sanaWeek]);

        return [
            'owner_key' => 'sana',
            'initials' => 'SA',
            'name' => 'Sana',
            'display_name' => 'Sana',
            'role_subtitle' => "Organic Content & Social — {$total} Executive KPIs",
            'period_label' => $ctx['period_range_label'],
            'overall_pct' => $overallPct,
            'passing_count' => $passing,
            'total_kpis' => $total,
            'overall_badge' => number_format($overallPct, 1) . "% Overall ({$passing}/{$total} Passing)",
            'orb_class' => self::healthOrbClass($overallPct),
            'tag_class' => $passing === $total ? 'tag-pass' : 'tag-sana',
            'columns' => 2,
            'action_label' => $contentUrl ? 'Open Content Calendar' : null,
            'action_url' => $contentUrl,
            'cards' => [
                [
                    'owner_key' => 'sana',
                    'icon' => 'instagram',
                    'orb_class' => 'orb-champagne',
                    'tag_label' => 'SANA',
                    'tag_class' => 'tag-sana',
                    'tag_url' => $contentUrl,
                    'tag_title' => 'Open Weekly Content Schedule',
                    'label' => 'Organic Content Plan (MTD)',
                    'hero_main' => (string) $postedTotal,
                    'hero_unit' => "/ {$cumulativeTarget}",
                    'delta_segments' => [
                        ['text' => "{$reels}/{$reelsTarget} Reels", 'class' => ($contentPass || ($reelsTarget > 0 && $reels >= $reelsTarget)) ? 'hl-emerald' : 'hl-rose'],
                        ['text' => ' • '],
                        ['text' => "{$stories}/{$storiesTarget} Stories", 'class' => ($contentPass || ($storiesTarget > 0 && $stories >= $storiesTarget)) ? 'hl-emerald' : 'hl-champagne'],
                        ['text' => ' • ' . $ctx['short_period_badge']],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $execPct)),
                    'bar_class' => $contentPass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0%',
                    'scale_right' => '100%',
                ],
                [
                    'owner_key' => 'sana',
                    'icon' => 'message-circle',
                    'orb_class' => $repliesPass ? 'orb-emerald' : 'orb-berry',
                    'tag_label' => 'SANA',
                    'tag_class' => 'tag-sana',
                    'tag_url' => $contentUrl,
                    'tag_title' => 'Open Weekly Content Schedule',
                    'label' => 'Social Reply SLA',
                    'hero_main' => (string) $lateCount,
                    'hero_unit' => 'Late',
                    'delta_segments' => [
                        ['text' => 'Target: '],
                        ['text' => '0 late replies', 'class' => $repliesPass ? 'hl-emerald' : 'hl-ruby'],
                        ['text' => ' • Syncs to Ayan'],
                    ],
                    'bar_pct' => $repliesPass ? 100.0 : 4.0,
                    'bar_class' => $repliesPass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0%',
                    'scale_right' => '100% SLA',
                    'is_late_reply_card' => true,
                    'late_count' => $lateCount,
                ],
            ],
        ];
    }

    private function presentBranchManager(
        string $ownerKey,
        array $mgr,
        array $ctx,
        ?User $viewer,
        ?string $displayNameOverride = null
    ): array {
        $branch = (string) ($mgr['branch'] ?? ($ownerKey === 'rhea' ? 'wakrah' : 'old_airport'));
        $branchLabel = $branch === 'wakrah' ? 'Wakrah' : 'Old Airport';
        $ownerName = (string) ($mgr['owner'] ?? ($ownerKey === 'rhea' ? 'Rhea' : 'Ahana'));
        $tagUpper = strtoupper($ownerName);
        $tagClassKey = $ownerKey === 'rhea' ? 'tag-rhea' : 'tag-ahana';

        $expected = (int) ($mgr['expected_evaluations_mtd'] ?? 0);
        $completed = (int) ($mgr['completed_evaluations_mtd'] ?? 0);
        $mondays = (int) ($mgr['mondays_elapsed_count'] ?? 0);
        $evalPct = (float) ($mgr['eval_compliance_pct'] ?? 0);
        $evalPass = !empty($mgr['eval_compliance_passing']);

        $auditsCount = (int) ($mgr['audits_count_mtd'] ?? 0);
        $mysteryAvg = (float) ($mgr['mystery_shopper_avg_pct'] ?? 0);
        $mysteryAttain = (float) ($mgr['mystery_shopper_attainment_pct'] ?? 0);
        $mysteryPass = !empty($mgr['mystery_shopper_passing']);

        $overallPct = (float) ($mgr['overall_score_pct'] ?? 0);
        $passing = (int) ($mgr['passing_kpis_count'] ?? 0);
        $total = (int) ($mgr['total_kpis_count'] ?? 2);

        $evalUrl = $this->safeUrl($viewer, 'kpis', 'kpi.evaluations.index', [
            'branch' => $branch,
            'month' => $ctx['month'],
        ]);
        $auditUrl = $this->safeUrl($viewer, 'kpis', 'kpi.branch-evaluation.index', [
            'branch' => $branch,
            'month' => $ctx['month'],
        ]);

        return [
            'owner_key' => $ownerKey,
            'initials' => $this->initialsFor($displayNameOverride ?: $ownerName),
            'name' => $ownerName,
            'display_name' => $displayNameOverride ?: $ownerName,
            'role_subtitle' => "{$branchLabel} Branch Manager — {$total} Executive KPIs",
            'period_label' => $ctx['period_range_label'],
            'overall_pct' => $overallPct,
            'passing_count' => $passing,
            'total_kpis' => $total,
            'overall_badge' => number_format($overallPct, 1) . "% Overall ({$passing}/{$total} Passing)",
            'orb_class' => self::healthOrbClass($overallPct),
            'tag_class' => $passing === $total ? 'tag-pass' : $tagClassKey,
            'columns' => 2,
            'action_label' => $evalUrl ? 'Open Staff Evaluations' : null,
            'action_url' => $evalUrl,
            'cards' => [
                [
                    'owner_key' => $ownerKey,
                    'icon' => 'star',
                    'orb_class' => $evalPass ? 'orb-emerald' : 'orb-rose',
                    'tag_label' => $tagUpper,
                    'tag_class' => $tagClassKey,
                    'tag_url' => $evalUrl,
                    'tag_title' => 'Open Salon Staff Evaluations',
                    'label' => 'Monday Staff Evaluation Compliance',
                    'hero_main' => number_format($evalPct, 1) . '%',
                    'hero_unit' => null,
                    'delta_segments' => [
                        ['text' => "{$completed}/{$expected} Evaluated", 'class' => $evalPass ? 'hl-emerald' : 'hl-rose'],
                        ['text' => ' • ' . $mondays . ' ' . Str::plural('Mon', $mondays) . ' MTD (100% Target)'],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $evalPct)),
                    'bar_class' => $evalPass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0%',
                    'scale_right' => '100%',
                ],
                [
                    'owner_key' => $ownerKey,
                    'icon' => 'clipboard-check',
                    'orb_class' => $mysteryPass ? 'orb-emerald' : 'orb-rose',
                    'tag_label' => $tagUpper,
                    'tag_class' => $tagClassKey,
                    'tag_url' => $auditUrl,
                    'tag_title' => "Open {$branchLabel} Branch Evaluations",
                    'label' => 'Branch Mystery Shopper Score',
                    'hero_main' => number_format($mysteryAvg, 1) . '%',
                    'hero_unit' => null,
                    'delta_segments' => [
                        ['text' => 'Target: '],
                        ['text' => '≥ 85.0% avg', 'class' => $mysteryPass ? 'hl-emerald' : 'hl-ruby'],
                        ['text' => " ({$auditsCount} Evaluations MTD)"],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $mysteryAttain)),
                    'bar_class' => $mysteryPass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0%',
                    'scale_right' => '85% Target',
                ],
            ],
        ];
    }

    private function presentChatAgent(
        string $ownerKey,
        array $agent,
        array $ctx,
        ?User $viewer,
        ?string $displayNameOverride = null
    ): array {
        $ownerName = (string) ($agent['owner'] ?? ucfirst($ownerKey));
        $tagUpper = strtoupper(explode(' ', trim($displayNameOverride ?: $ownerName))[0] ?: 'AGENT');
        $tagClassKey = match ($ownerKey) {
            'areeba' => 'tag-areeba',
            'zoya' => 'tag-zoya',
            default => 'tag-rose',
        };

        $isCurrent = (bool) $ctx['is_current_month'];
        $todayBk = (int) ($agent['today_bookings'] ?? 0);
        $todayPct = (float) ($agent['today_attainment_pct'] ?? 0);
        $mtdBk = (int) ($agent['mtd_bookings'] ?? 0);
        $mtdTarget = (int) ($agent['mtd_target'] ?? (self::AGENT_DAILY_BOOKING_QUOTA * $ctx['days_elapsed']));
        $fullMoTarget = (int) ($agent['full_month_target'] ?? (self::AGENT_DAILY_BOOKING_QUOTA * $ctx['days_in_month']));
        $mtdPct = (float) ($agent['mtd_attainment_pct'] ?? 0);
        $dailyAvg = (float) ($agent['daily_avg_pace'] ?? 0);
        $dailyAvgPct = (float) ($agent['daily_avg_pct'] ?? 0);
        $bkPass = !empty($agent['booking_passing']);
        $card2Pass = $isCurrent ? ($todayBk >= self::AGENT_DAILY_BOOKING_QUOTA) : ($dailyAvg >= (float) self::AGENT_DAILY_BOOKING_QUOTA);
        $card2BarPct = $isCurrent ? $todayPct : $dailyAvgPct;
        $overallPct = (float) ($agent['overall_score_pct'] ?? $mtdPct);
        $passing = (int) ($agent['passing_kpis_count'] ?? ($bkPass ? 1 : 0));
        $total = (int) ($agent['total_kpis_count'] ?? 1);

        $agentsUrl = $this->safeUrl($viewer, 'kpis', 'kpi.agents.index', ['month' => $ctx['month']]);

        return [
            'owner_key' => $ownerKey,
            'initials' => $this->initialsFor($displayNameOverride ?: $ownerName),
            'name' => $ownerName,
            'display_name' => $displayNameOverride ?: $ownerName,
            'role_subtitle' => 'Customer Service Chat Agent — 13 Bookings/Day Quota',
            'period_label' => $ctx['period_range_label'],
            'overall_pct' => $overallPct,
            'passing_count' => $passing,
            'total_kpis' => $total,
            'overall_badge' => number_format($overallPct, 1) . "% MTD Quota ({$mtdBk}/{$mtdTarget} Bookings)",
            'orb_class' => self::healthOrbClass($overallPct),
            'tag_class' => $bkPass ? 'tag-pass' : $tagClassKey,
            'columns' => 2,
            'action_label' => $agentsUrl ? 'Open Agent Scorecard' : null,
            'action_url' => $agentsUrl,
            'cards' => [
                [
                    'owner_key' => $ownerKey,
                    'icon' => 'calendar-check',
                    'orb_class' => $bkPass ? 'orb-emerald' : 'orb-rose',
                    'tag_label' => $tagUpper,
                    'tag_class' => $tagClassKey,
                    'tag_url' => $agentsUrl,
                    'tag_title' => 'Open Agent Booking Targets',
                    'label' => 'MTD Booking Quota',
                    'label_title' => AgentBookingQuotaCalculator::UI_TOOLTIP_COPY,
                    'subnote' => AgentBookingQuotaCalculator::UI_TOOLTIP_COPY,
                    'hero_main' => (string) $mtdBk,
                    'hero_unit' => "/ {$mtdTarget}",
                    'delta_segments' => [
                        ['text' => number_format($mtdPct, 1) . '%', 'class' => $bkPass ? 'hl-emerald' : 'hl-rose'],
                        ['text' => " of MTD Target (13/day × {$ctx['days_elapsed']} days • {$fullMoTarget} Full Mo)"],
                    ],
                    'bar_pct' => min(100.0, max(0.0, $mtdPct)),
                    'bar_class' => $bkPass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0%',
                    'scale_right' => '100%',
                ],
                [
                    'owner_key' => $ownerKey,
                    'icon' => 'clock',
                    'orb_class' => $card2Pass ? 'orb-emerald' : 'orb-rose',
                    'tag_label' => $tagUpper,
                    'tag_class' => $tagClassKey,
                    'tag_url' => $agentsUrl,
                    'tag_title' => 'Open Agent Shift Logs',
                    'label' => $isCurrent ? "Today's Daily Booking Target" : 'Daily Avg Pace',
                    'label_title' => AgentBookingQuotaCalculator::UI_TOOLTIP_COPY,
                    'subnote' => AgentBookingQuotaCalculator::UI_TOOLTIP_COPY,
                    'hero_main' => (string) ($isCurrent ? $todayBk : $dailyAvg),
                    'hero_unit' => '/ 13',
                    'delta_segments' => $isCurrent
                        ? [
                            ['text' => number_format($todayPct, 1) . '%', 'class' => $card2Pass ? 'hl-emerald' : 'hl-rose'],
                            ['text' => ' of Daily Quota (' . ($todayBk >= 13 ? 'Daily Quota Met!' : (max(0, 13 - $todayBk) . ' left today')) . ')'],
                        ]
                        : [
                            ['text' => "{$dailyAvg} / 13 per day", 'class' => $card2Pass ? 'hl-emerald' : 'hl-rose'],
                            ['text' => " across {$ctx['month_label']}"],
                        ],
                    'bar_pct' => min(100.0, max(0.0, $card2BarPct)),
                    'bar_class' => $card2Pass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0%',
                    'scale_right' => '100%',
                ],
            ],
        ];
    }

    private function presentArbab(
        array $arbab,
        array $ctx,
        ?User $viewer,
        ?string $displayNameOverride = null
    ): array {
        $totalCount = (int) ($arbab['total_count'] ?? 0);
        $completedCount = (int) ($arbab['completed_count'] ?? 0);
        $pendingCount = (int) ($arbab['pending_count'] ?? 0);
        $overdueCount = (int) ($arbab['overdue_count'] ?? 0);
        $urgentCount = (int) ($arbab['urgent_count'] ?? 0);
        $onTimeCount = (int) ($arbab['on_time_count'] ?? 0);

        $zeroOverduePct = (float) ($arbab['zero_overdue_pct'] ?? 100);
        $completionPct = (float) ($arbab['completion_pct'] ?? 100);
        $slaPct = (float) ($arbab['sla_pct'] ?? 100);

        $overduePass = !empty($arbab['overdue_passing']);
        $completionPass = !empty($arbab['completion_passing']);
        $slaPass = !empty($arbab['sla_passing']);

        $overallPct = (float) ($arbab['overall_score_pct'] ?? 0);
        $passing = (int) ($arbab['passing_kpis_count'] ?? 0);
        $total = (int) ($arbab['total_kpis_count'] ?? 3);

        $opsUrl = $this->safeUrl($viewer, 'staff', 'staff.ops-tracker.index');

        return [
            'owner_key' => 'arbab',
            'initials' => $this->initialsFor($displayNameOverride ?: 'Arbab'),
            'name' => 'Arbab',
            'display_name' => $displayNameOverride ?: 'Arbab',
            'role_subtitle' => "Operations Lead — {$total} Operational KPIs",
            'period_label' => $ctx['period_range_label'],
            'overall_pct' => $overallPct,
            'passing_count' => $passing,
            'total_kpis' => $total,
            'overall_badge' => number_format($overallPct, 1) . "% Overall ({$passing}/{$total} Passing)",
            'orb_class' => self::healthOrbClass($overallPct),
            'tag_class' => $passing === $total ? 'tag-pass' : 'tag-arbab',
            'columns' => 3,
            'action_label' => $opsUrl ? 'Open Ops Tracker' : null,
            'action_url' => $opsUrl,
            'ops_items' => $arbab['open_items'] ?? [],
            'cards' => [
                [
                    'owner_key' => 'arbab',
                    'icon' => 'alert-triangle',
                    'orb_class' => $overduePass ? 'orb-emerald' : 'orb-ruby',
                    'tag_label' => 'ARBAB',
                    'tag_class' => 'tag-arbab',
                    'tag_url' => $opsUrl,
                    'tag_title' => 'Open Ops Tracker',
                    'label' => 'Overdue Tasks',
                    'hero_main' => (string) $overdueCount,
                    'hero_unit' => 'Overdue',
                    'delta_segments' => [
                        ['text' => "{$pendingCount} Open", 'class' => $overduePass ? 'hl-emerald' : 'hl-ruby'],
                        ['text' => " • {$urgentCount} Urgent • Target: 0 overdue"],
                    ],
                    'bar_pct' => $overduePass ? 100.0 : max(6.0, $zeroOverduePct),
                    'bar_class' => $overduePass ? 'fill-emerald' : 'fill-ruby',
                    'scale_left' => '0%',
                    'scale_right' => '0 Overdue Target',
                ],
                [
                    'owner_key' => 'arbab',
                    'icon' => 'tasks',
                    'orb_class' => $completionPass ? 'orb-emerald' : 'orb-amber',
                    'tag_label' => 'ARBAB',
                    'tag_class' => 'tag-arbab',
                    'tag_url' => $opsUrl,
                    'tag_title' => 'Open Ops Tracker',
                    'label' => 'Ops Actions',
                    'hero_main' => (string) $completedCount,
                    'hero_unit' => "/ {$totalCount}",
                    'delta_segments' => [
                        ['text' => number_format($completionPct, 1) . '%', 'class' => $completionPass ? 'hl-emerald' : 'hl-champagne'],
                        ['text' => ' completed (Target: ≥ 85%)'],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $completionPct)),
                    'bar_class' => $completionPass ? 'fill-emerald' : 'fill-champagne',
                    'scale_left' => '0%',
                    'scale_right' => '85% Target',
                ],
                [
                    'owner_key' => 'arbab',
                    'icon' => 'check-circle',
                    'orb_class' => $slaPass ? 'orb-emerald' : 'orb-rose',
                    'tag_label' => 'ARBAB',
                    'tag_class' => 'tag-arbab',
                    'tag_url' => $opsUrl,
                    'tag_title' => 'Open Ops Tracker',
                    'label' => 'SLA On-Time',
                    'hero_main' => ((int) round($slaPct)) . '%',
                    'hero_unit' => null,
                    'delta_segments' => [
                        ['text' => "{$onTimeCount}/{$totalCount} on time", 'class' => $slaPass ? 'hl-emerald' : 'hl-rose'],
                        ['text' => ' • Target: ≥ 85% SLA'],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $slaPct)),
                    'bar_class' => $slaPass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0%',
                    'scale_right' => '100% SLA',
                ],
            ],
        ];
    }

    private function presentSundus(
        array $sundus,
        array $ctx,
        ?User $viewer,
        ?string $displayNameOverride = null
    ): array {
        $weeksElapsed = (int) ($sundus['weeks_elapsed'] ?? 1);
        $weeklyTarget = (int) ($sundus['weekly_target'] ?? self::SUNDUS_WEEKLY_BRANCH_EVAL_TARGET);
        $mtdTarget = (int) ($sundus['mtd_target_per_branch'] ?? ($weeksElapsed * $weeklyTarget));

        $oaMtd = (int) ($sundus['old_airport_mtd'] ?? 0);
        $oaWeek = (int) ($sundus['old_airport_week'] ?? 0);
        $oaPct = (float) ($sundus['old_airport_pct'] ?? 0);
        $oaPass = !empty($sundus['old_airport_passing']);

        $wkMtd = (int) ($sundus['wakrah_mtd'] ?? 0);
        $wkWeek = (int) ($sundus['wakrah_week'] ?? 0);
        $wkPct = (float) ($sundus['wakrah_pct'] ?? 0);
        $wkPass = !empty($sundus['wakrah_passing']);

        $overallPct = (float) ($sundus['overall_score_pct'] ?? 0);
        $passing = (int) ($sundus['passing_kpis_count'] ?? 0);
        $total = (int) ($sundus['total_kpis_count'] ?? 2);

        $oaUrl = $this->safeUrl($viewer, 'operations|kpis', 'kpi.branch-evaluation.index', [
            'branch' => 'old_airport',
            'month' => $ctx['month'],
        ]);
        $wkUrl = $this->safeUrl($viewer, 'operations|kpis', 'kpi.branch-evaluation.index', [
            'branch' => 'wakrah',
            'month' => $ctx['month'],
        ]);

        return [
            'owner_key' => 'sundus',
            'initials' => $this->initialsFor($displayNameOverride ?: 'Sundus'),
            'name' => 'Sundus',
            'display_name' => $displayNameOverride ?: 'Sundus',
            'role_subtitle' => "Admin — Branch Execution Audits ({$total} KPIs)",
            'period_label' => $ctx['period_range_label'],
            'overall_pct' => $overallPct,
            'passing_count' => $passing,
            'total_kpis' => $total,
            'overall_badge' => number_format($overallPct, 1) . "% Overall ({$passing}/{$total} Passing)",
            'orb_class' => self::healthOrbClass($overallPct),
            'tag_class' => $passing === $total ? 'tag-pass' : 'tag-champagne',
            'columns' => 2,
            'action_label' => $oaUrl ? 'Open Branch Evaluations' : null,
            'action_url' => $this->safeUrl($viewer, 'operations|kpis', 'kpi.branch-evaluation.index', ['month' => $ctx['month']]),
            'cards' => [
                [
                    'owner_key' => 'sundus',
                    'icon' => 'clipboard-check',
                    'orb_class' => $oaPass ? 'orb-emerald' : 'orb-rose',
                    'tag_label' => 'SUNDUS',
                    'tag_class' => $oaPass ? 'tag-pass' : 'tag-rose',
                    'tag_url' => $oaUrl,
                    'tag_title' => 'View Old Airport Branch Evaluations',
                    'label' => 'Old Airport Branch Evaluations',
                    'subnote' => 'Counts only audits submitted under Sundus\'s login',
                    'hero_main' => (string) $oaMtd,
                    'hero_unit' => "/ {$mtdTarget} MTD",
                    'delta_segments' => [
                        ['text' => "{$oaWeek}/{$weeklyTarget} This Week", 'class' => $oaWeek >= $weeklyTarget ? 'hl-emerald' : 'hl-champagne'],
                        ['text' => " • " . number_format($oaPct, 1) . "% MTD (Target: {$weeklyTarget}/week × {$weeksElapsed}w)"],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $oaPct)),
                    'bar_class' => $oaPass ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0',
                    'scale_right' => "{$mtdTarget} MTD Target",
                ],
                [
                    'owner_key' => 'sundus',
                    'icon' => 'clipboard-check',
                    'orb_class' => $wkPass ? 'orb-emerald' : 'orb-champagne',
                    'tag_label' => 'SUNDUS',
                    'tag_class' => $wkPass ? 'tag-pass' : 'tag-champagne',
                    'tag_url' => $wkUrl,
                    'tag_title' => 'View Al Wakrah Branch Evaluations',
                    'label' => 'Al Wakrah Branch Evaluations',
                    'subnote' => 'Counts only audits submitted under Sundus\'s login',
                    'hero_main' => (string) $wkMtd,
                    'hero_unit' => "/ {$mtdTarget} MTD",
                    'delta_segments' => [
                        ['text' => "{$wkWeek}/{$weeklyTarget} This Week", 'class' => $wkWeek >= $weeklyTarget ? 'hl-emerald' : 'hl-champagne'],
                        ['text' => " • " . number_format($wkPct, 1) . "% MTD (Target: {$weeklyTarget}/week × {$weeksElapsed}w)"],
                    ],
                    'bar_pct' => min(100.0, max(4.0, $wkPct)),
                    'bar_class' => $wkPass ? 'fill-emerald' : 'fill-champagne',
                    'scale_left' => '0',
                    'scale_right' => "{$mtdTarget} MTD Target",
                ],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $weeklyEvaluations
     * @param  list<array<string, mixed>>  $myComplaints
     */
    private function presentSalonStaff(
        string $displayName,
        string $roleLabel,
        string $periodLabel,
        array $weeklyEvaluations,
        array $myComplaints,
        string $ownerKey = 'salon_staff'
    ): array {
        $evalCount = count($weeklyEvaluations);
        $evalAvg = $evalCount > 0
            ? round(collect($weeklyEvaluations)->avg(fn ($e) => (float) $e['score']), 2)
            : null;
        $evalAvgPct = $evalAvg !== null ? min(100.0, round(($evalAvg / 5.0) * 100, 1)) : 0.0;
        $evalPass = $evalAvg !== null && $evalAvg >= 4.0;
        $latestEvalDate = $evalCount > 0 ? $weeklyEvaluations[0]['date_label'] : null;

        $complaintsCount = count($myComplaints);
        $unresolvedCount = collect($myComplaints)->where('status', 'Under Review')->count();
        $complaintsClean = $complaintsCount === 0;

        $passing = ($evalPass ? 1 : 0) + ($complaintsClean ? 1 : 0);
        $total = 2;
        $overallPct = round((($evalAvgPct) + ($complaintsClean ? 100.0 : max(0.0, 100.0 - ($complaintsCount * 25.0)))) / 2, 1);

        return [
            'owner_key' => $ownerKey,
            'initials' => $this->initialsFor($displayName),
            'name' => $displayName,
            'display_name' => $displayName,
            'role_subtitle' => "{$roleLabel} — Service Quality & Evaluations",
            'period_label' => $periodLabel,
            'overall_pct' => $overallPct,
            'passing_count' => $passing,
            'total_kpis' => $total,
            'overall_badge' => $evalCount > 0
                ? (number_format($overallPct, 1) . "% Overall ({$passing}/{$total} Passing)")
                : ($complaintsClean ? 'Clean Service Record' : "{$complaintsCount} Service " . Str::plural('Review', $complaintsCount)),
            'orb_class' => self::healthOrbClass($evalCount > 0 ? $overallPct : ($complaintsClean ? 100.0 : 40.0)),
            'tag_class' => $passing === $total ? 'tag-pass' : 'tag-rose',
            'columns' => 3,
            'action_label' => null,
            'action_url' => null,
            'cards' => [
                [
                    'owner_key' => $ownerKey,
                    'icon' => 'star',
                    'orb_class' => $evalPass ? 'orb-emerald' : 'orb-champagne',
                    'tag_label' => $evalPass ? 'PASSING' : 'QUALITY',
                    'tag_class' => $evalPass ? 'tag-pass' : 'tag-champagne',
                    'tag_url' => null,
                    'label' => 'Weekly Evaluation Average',
                    'hero_main' => $evalAvg !== null ? number_format($evalAvg, 2) : '—',
                    'hero_unit' => '/ 5.0',
                    'delta_segments' => [
                        ['text' => $evalAvg !== null ? (number_format($evalAvgPct, 0) . '%') : 'Not scored yet', 'class' => $evalPass ? 'hl-emerald' : 'hl-champagne'],
                        ['text' => $evalAvg !== null ? ' average score (Target: ≥ 4.0)' : ' • Weekly manager evaluations'],
                    ],
                    'bar_pct' => $evalAvgPct,
                    'bar_class' => $evalPass ? 'fill-emerald' : 'fill-champagne',
                    'scale_left' => '0',
                    'scale_right' => '5.0 Max',
                ],
                [
                    'owner_key' => $ownerKey,
                    'icon' => 'check-circle',
                    'orb_class' => $evalCount > 0 ? 'orb-emerald' : 'orb-rose',
                    'tag_label' => 'REVIEWS',
                    'tag_class' => 'tag-muted',
                    'tag_url' => null,
                    'label' => 'Evaluations Completed',
                    'hero_main' => (string) $evalCount,
                    'hero_unit' => null,
                    'delta_segments' => [
                        ['text' => $latestEvalDate ? "Latest: {$latestEvalDate}" : '0 recorded', 'class' => $evalCount > 0 ? 'hl-emerald' : 'hl-rose'],
                        ['text' => ' • Weekly branch evaluations'],
                    ],
                    'bar_pct' => $evalCount > 0 ? min(100.0, round(($evalCount / 4) * 100, 1)) : 0.0,
                    'bar_class' => $evalCount > 0 ? 'fill-emerald' : 'fill-rose-gold',
                    'scale_left' => '0',
                    'scale_right' => '4 / Month',
                ],
                [
                    'owner_key' => $ownerKey,
                    'icon' => 'shield-check',
                    'orb_class' => $complaintsClean ? 'orb-emerald' : 'orb-ruby',
                    'tag_label' => $complaintsClean ? 'CLEAN RECORD' : 'REVIEW',
                    'tag_class' => $complaintsClean ? 'tag-pass' : 'tag-ruby',
                    'tag_url' => null,
                    'label' => 'Complaints Count',
                    'hero_main' => (string) $complaintsCount,
                    'hero_unit' => null,
                    'delta_segments' => [
                        ['text' => $complaintsClean ? '0 complaints' : ($unresolvedCount > 0 ? "{$unresolvedCount} under review" : 'All resolved'), 'class' => $complaintsClean ? 'hl-emerald' : ($unresolvedCount > 0 ? 'hl-ruby' : 'hl-champagne')],
                        ['text' => ' • Target: 0 service complaints'],
                    ],
                    'bar_pct' => $complaintsClean ? 100.0 : max(8.0, 100.0 - ($complaintsCount * 25.0)),
                    'bar_class' => $complaintsClean ? 'fill-emerald' : 'fill-ruby',
                    'scale_left' => 'Target: 0',
                    'scale_right' => $complaintsClean ? '100% Clean' : 'Logged',
                ],
            ],
        ];
    }

    private function resolveBranchManagerKey(User $user, ?Staff $staff): string
    {
        $branch = ($staff && in_array($staff->branch, ['old_airport', 'wakrah'], true))
            ? $staff->branch
            : (KpiStaffEvaluation::defaultBranchFor($user) ?? 'old_airport');

        return $branch === 'wakrah' ? 'rhea' : 'ahana';
    }

    /**
     * Permission-safe URL generator: never renders clickable drill-down links for
     * `salon_staff` or any user who lacks view access to the target CRM module.
     */
    private function safeUrl(?User $viewer, string $module, string $routeName, array $params = []): ?string
    {
        if (!$viewer) {
            return route($routeName, $params);
        }

        if ($viewer->isSalonStaff()) {
            return null;
        }

        if (!$viewer->isAdmin()) {
            $allowedModules = array_filter(array_map('trim', explode('|', $module)));
            $canAccess = false;
            foreach ($allowedModules as $mod) {
                if ($viewer->canView($mod)) {
                    $canAccess = true;
                    break;
                }
            }
            if (!$canAccess) {
                return null;
            }
        }

        return route($routeName, $params);
    }

    private function initialsFor(string $name): string
    {
        $parts = collect(preg_split('/\s+/', trim($name)))->filter();
        if ($parts->count() >= 2) {
            return mb_strtoupper(mb_substr($parts->first(), 0, 1) . mb_substr($parts->get(1), 0, 1));
        }

        $word = (string) $parts->first();

        return $word !== '' ? mb_strtoupper(mb_substr($word, 0, 2)) : 'LK';
    }
}
