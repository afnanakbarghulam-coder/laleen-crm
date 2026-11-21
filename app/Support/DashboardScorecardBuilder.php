<?php

namespace App\Support;

use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\BranchEvaluation;
use App\Models\KpiChatEvaluation;
use App\Models\KpiStaffEvaluation;
use App\Models\Lead;
use App\Models\OpsActionItem;
use App\Models\SocialContentPlan;
use App\Models\SocialLateReplyLog;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DashboardScorecardBuilder
{
    public const AGENT_DAILY_BOOKING_QUOTA = 13;
    public const LADEN_DAILY_BOOKING_QUOTA = 26;
    public const CHAT_QA_TARGET_PCT = 85.0;
    public const LEAD_CONVERSION_TARGET_PCT = 30.0;
    public const AD_CONVERSION_TARGET_PCT = 20.0;
    public const BRANCH_AD_MONTHLY_TARGET_QAR = 15000.0;
    public const COMBINED_AD_MONTHLY_TARGET_QAR = 30000.0;
    public const BRANCH_AUDIT_TARGET_PCT = 85.0;

    private Carbon $from;
    private Carbon $to;
    private ?User $viewer;

    public function __construct(Carbon $from, Carbon $to, ?User $viewer = null)
    {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
        $this->viewer = $viewer;
    }

    /**
     * Build all 8 tracked staff Split Scorecards, each standardized to strictly 3 KPIs,
     * enforcing the Primary Output Gate, bottleneck-first auto-sorting, and non-admin viewer self-pinning.
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(): array
    {
        $periodDays = max(1, (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1);
        $daysInMonth = max(1, (int) $this->from->daysInMonth);
        $nowQatar = now('Asia/Qatar');
        $isCurrentPeriod = $this->to->toDateString() >= $nowQatar->toDateString()
            && $this->from->toDateString() <= $nowQatar->toDateString();
        $referenceDate = $isCurrentPeriod ? $nowQatar : $this->to->copy();

        $sanaMetrics = $this->computeSanaMetrics($periodDays, $isCurrentPeriod);

        $cards = [
            $this->buildAyanCard($periodDays, $daysInMonth, $sanaMetrics),
            $this->buildSanaCard($sanaMetrics),
            $this->buildChatAgentCard('zoya', 'Zoya', 'Chat Agent', $periodDays, $referenceDate),
            $this->buildChatAgentCard('areeba', 'Areeba', 'Chat Agent', $periodDays, $referenceDate),
            $this->buildLadenCard($periodDays, $referenceDate),
            $this->buildBranchManagerCard(
                'ahana',
                'Ahana',
                'Old Airport',
                'old_airport',
                $periodDays,
                $daysInMonth,
                $isCurrentPeriod
            ),
            $this->buildBranchManagerCard(
                'rhea',
                'Rhea',
                'Al Wakrah',
                'wakrah',
                $periodDays,
                $daysInMonth,
                $isCurrentPeriod
            ),
            $this->buildArbabCard(),
        ];

        usort($cards, function (array $a, array $b): int {
            if ($a['is_self'] !== $b['is_self']) {
                return $a['is_self'] ? -1 : 1;
            }

            if ($a['status_rank'] !== $b['status_rank']) {
                return $a['status_rank'] <=> $b['status_rank'];
            }

            if ($a['overall_pct'] !== $b['overall_pct']) {
                return $a['overall_pct'] <=> $b['overall_pct'];
            }

            if ($a['primary_pct'] !== $b['primary_pct']) {
                return $a['primary_pct'] <=> $b['primary_pct'];
            }

            return strcmp($a['name'], $b['name']);
        });

        return array_values($cards);
    }

    /**
     * Format a single thin-line KPI item with muted Red (<50%), Amber (50-84.9%), Green (85%+) thresholds.
     *
     * @return array<string, mixed>
     */
    private function makeKpiBar(
        string $label,
        string $valueLabel,
        float $pct,
        bool $isPrimary = false,
        ?bool $explicitPassing = null
    ): array {
        $clampedPct = max(0.0, min(100.0, round($pct, 1)));
        $color = match (true) {
            $clampedPct >= 85.0 => 'green',
            $clampedPct >= 50.0 => 'amber',
            default => 'red',
        };

        return [
            'label' => $label,
            'value_label' => $valueLabel,
            'pct' => $clampedPct,
            'color' => $color,
            'passing' => $explicitPassing ?? ($clampedPct >= 85.0),
            'is_primary' => $isPrimary,
        ];
    }

    /**
     * Finalize a Split Scorecard with weighted overall %, rounded whole-number display,
     * Primary Output Gate, and viewer matching.
     *
     * @param  array<int, array<string, mixed>>  $kpis
     * @return array<string, mixed>
     */
    private function finalizeCard(
        string $key,
        string $name,
        string $role,
        string $url,
        array $kpis
    ): array {
        $primaryPct = (float) ($kpis[0]['pct'] ?? 0.0);
        $secondaryKpis = array_slice($kpis, 1);

        if (!empty($secondaryKpis)) {
            $secondaryAvg = array_sum(array_map(fn (array $k) => (float) $k['pct'], $secondaryKpis)) / count($secondaryKpis);
            $overallPct = round(($primaryPct * 0.5) + ($secondaryAvg * 0.5), 1);
        } else {
            $overallPct = round($primaryPct, 1);
        }

        // Primary Output Gate: if #1 output KPI is < 50% of pace/target, NEVER allow 'On Target' (green)
        if ($primaryPct < 50.0) {
            if ($primaryPct < 25.0 || $overallPct < 60.0) {
                $status = 'Off Track';
                $statusColor = 'red';
                $statusRank = 0;
            } else {
                $status = 'At Risk';
                $statusColor = 'amber';
                $statusRank = 1;
            }
        } else {
            if ($overallPct >= 85.0) {
                $status = 'On Target';
                $statusColor = 'green';
                $statusRank = 2;
            } elseif ($overallPct >= 50.0) {
                $status = 'At Risk';
                $statusColor = 'amber';
                $statusRank = 1;
            } else {
                $status = 'Off Track';
                $statusColor = 'red';
                $statusRank = 0;
            }
        }

        $passingCount = count(array_filter($kpis, fn (array $k) => !empty($k['passing'])));
        $totalKpis = count($kpis);

        return [
            'key' => $key,
            'name' => $name,
            'role' => $role,
            'url' => $url,
            'kpis' => $kpis,
            'passing_count' => $passingCount,
            'total_kpis' => $totalKpis,
            'primary_pct' => $primaryPct,
            'overall_pct' => $overallPct,
            'overall_rounded' => (int) round($overallPct),
            'status' => $status,
            'status_color' => $statusColor,
            'status_rank' => $statusRank,
            'is_self' => $this->isViewerSelf($key),
        ];
    }

    private function resolveKpiUrl(string $routeName, array $params = []): string
    {
        if ($this->viewer && !$this->viewer->isAdmin() && !$this->viewer->canView('kpis')) {
            return '#';
        }

        return route($routeName, $params);
    }

    private function resolveOpsUrl(): string
    {
        if (!$this->viewer || $this->viewer->isAdmin() || $this->viewer->canView('staff')) {
            return route('staff.ops-tracker.index');
        }

        if ($this->viewer->canView('kpis')) {
            return route('kpi.operations');
        }

        return '#';
    }

    private function formatCompactQarTarget(float $amount): string
    {
        if ($amount >= 1000) {
            $k = round($amount / 1000, 1);

            return (floor($k) == $k ? (string) (int) $k : (string) $k) . 'k';
        }

        return number_format($amount, 0);
    }

    private function isViewerSelf(string $key): bool
    {
        if (!$this->viewer || $this->viewer->isAdmin()) {
            return false;
        }

        $cleanKey = strtolower(trim($key));
        $viewerName = strtolower(trim((string) $this->viewer->name));
        $emailLocal = strtolower(trim(explode('@', (string) $this->viewer->email)[0] ?? ''));

        if ($viewerName !== '' && preg_match('/\b' . preg_quote($cleanKey, '/') . '\b/i', $viewerName)) {
            return true;
        }

        return $emailLocal !== '' && ($emailLocal === $cleanKey || str_starts_with($emailLocal, $cleanKey));
    }

    /**
     * @return array<string, mixed>
     */
    private function computeSanaMetrics(int $periodDays, bool $isCurrentPeriod): array
    {
        $minDateStr = $this->from->toDateString();
        $maxDateStr = $this->to->toDateString();
        $firstMondayStr = $this->from->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $lastMondayStr = $this->to->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        $inRangePlans = SocialContentPlan::query()
            ->whereDate('week_start_date', '>=', $firstMondayStr)
            ->whereDate('week_start_date', '<=', $lastMondayStr)
            ->get()
            ->filter(function (SocialContentPlan $plan) use ($minDateStr, $maxDateStr): bool {
                $exact = $plan->exactDate()->toDateString();

                return $exact >= $minDateStr && $exact <= $maxDateStr;
            })
            ->values();

        $publishedReels = $inRangePlans
            ->filter(fn (SocialContentPlan $p) => $p->is_reel_posted && !$p->is_reel_skipped)
            ->count();
        $publishedStories = $inRangePlans->where('are_stories_posted', true)->count();

        $lateReplyLogsCount = Schema::hasTable('social_late_reply_logs')
            ? SocialLateReplyLog::query()->whereBetween('incident_at', [$this->from, $this->to])->count()
            : 0;
        $legacyLateReplies = (int) $inRangePlans->sum('late_replies_count');
        $lateRepliesCount = $lateReplyLogsCount + $legacyLateReplies;

        $hasActivity = $lateReplyLogsCount > 0
            || $publishedReels > 0
            || $publishedStories > 0
            || $legacyLateReplies > 0
            || $inRangePlans->contains(
                fn (SocialContentPlan $p) => filled($p->reel_content) || filled($p->stories_content) || filled($p->reel_link)
            );

        $completedDays = $isCurrentPeriod ? max(0, $periodDays - 1) : $periodDays;

        if ($completedDays === 0) {
            $reelsTarget = 1;
            $storiesTarget = 1;
            $reelsPct = $hasActivity ? 100.0 : 0.0;
            $storiesPct = $hasActivity ? 100.0 : 0.0;
        } else {
            $storiesTarget = max(1, $completedDays);
            $reelsTarget = max(1, (int) floor(($completedDays * SocialContentPlan::WEEKLY_REEL_TARGET) / 7));
            $reelsPct = $hasActivity ? min(100.0, round(($publishedReels / $reelsTarget) * 100, 1)) : 0.0;
            $storiesPct = $hasActivity ? min(100.0, round(($publishedStories / $storiesTarget) * 100, 1)) : 0.0;
        }

        $replySlaPct = ($hasActivity && $lateRepliesCount === 0) ? 100.0 : 0.0;
        $compositePct = round(($reelsPct * 0.5) + ((($storiesPct + $replySlaPct) / 2) * 0.5), 1);

        return [
            'has_activity' => $hasActivity,
            'published_reels' => $publishedReels,
            'reels_target' => $reelsTarget,
            'reels_pct' => $reelsPct,
            'published_stories' => $publishedStories,
            'stories_target' => $storiesTarget,
            'stories_pct' => $storiesPct,
            'late_replies' => $lateRepliesCount,
            'reply_sla_pct' => $replySlaPct,
            'composite_pct' => $compositePct,
        ];
    }

    /**
     * Sana (Content): Reels (X / Y), Daily Stories (X / Y), Reply SLA (X%)
     *
     * @param  array<string, mixed>  $sanaMetrics
     * @return array<string, mixed>
     */
    private function buildSanaCard(array $sanaMetrics): array
    {
        $replyPct = (float) $sanaMetrics['reply_sla_pct'];

        $kpis = [
            $this->makeKpiBar(
                'Reels',
                $sanaMetrics['published_reels'] . ' / ' . $sanaMetrics['reels_target'],
                (float) $sanaMetrics['reels_pct'],
                true
            ),
            $this->makeKpiBar(
                'Daily Stories',
                $sanaMetrics['published_stories'] . ' / ' . $sanaMetrics['stories_target'],
                (float) $sanaMetrics['stories_pct']
            ),
            $this->makeKpiBar(
                'Reply SLA',
                ((int) round($replyPct)) . '%',
                $replyPct
            ),
        ];

        return $this->finalizeCard(
            'sana',
            'Sana',
            'Content',
            $this->resolveKpiUrl('kpi.content-calendar.index'),
            $kpis
        );
    }

    /**
     * Ayan (Paid Ads): Ad Revenue (QAR X / 15k), Ad Conversion (X%), Funnel Pace (X%)
     *
     * @param  array<string, mixed>  $sanaMetrics
     * @return array<string, mixed>
     */
    private function buildAyanCard(int $periodDays, int $daysInMonth, array $sanaMetrics): array
    {
        $adLeadColumns = ['id', 'date', 'customer_id', 'phone', 'branch', 'ticket_amount', 'category', 'lead_id'];
        if (Schema::hasColumn('ad_lead_entries', 'old_airport_amount')) {
            $adLeadColumns[] = 'old_airport_amount';
        }
        if (Schema::hasColumn('ad_lead_entries', 'wakrah_amount')) {
            $adLeadColumns[] = 'wakrah_amount';
        }

        $adLeads = AdLeadEntry::query()
            ->whereBetween('date', [$this->from->copy()->startOfDay(), $this->to->copy()->endOfDay()])
            ->get($adLeadColumns);

        $oaRevenue = round((float) $adLeads->sum(fn (AdLeadEntry $e) => $e->branchRevenue('old_airport')), 0);
        $wkRevenue = round((float) $adLeads->sum(fn (AdLeadEntry $e) => $e->branchRevenue('wakrah')), 0);
        $totalAdRevenue = $oaRevenue + $wkRevenue;

        $branchProratedTarget = max(1.0, round((self::BRANCH_AD_MONTHLY_TARGET_QAR / $daysInMonth) * $periodDays, 0));
        $combinedProratedTarget = max(1.0, $branchProratedTarget * 2);

        $combinedAdPct = min(100.0, round(($totalAdRevenue / $combinedProratedTarget) * 100, 1));
        $oaAdPct = min(100.0, round(($oaRevenue / $branchProratedTarget) * 100, 1));
        $wkAdPct = min(100.0, round(($wkRevenue / $branchProratedTarget) * 100, 1));
        $branchBalancePct = round(($oaAdPct + $wkAdPct) / 2, 1);

        $totalLeadsCount = $adLeads->count();
        $bookedLeadsCount = $adLeads->filter(fn (AdLeadEntry $e) => $e->isBooked())->count();
        $adConversionPct = $totalLeadsCount > 0 ? round(($bookedLeadsCount / $totalLeadsCount) * 100, 1) : 0.0;
        $adConversionAttainmentPct = min(100.0, round(($adConversionPct / self::AD_CONVERSION_TARGET_PCT) * 100, 1));

        $funnelPacePct = round(($branchBalancePct + (float) $sanaMetrics['composite_pct']) / 2, 1);

        $kpis = [
            $this->makeKpiBar(
                'Ad Revenue',
                'QAR ' . number_format($totalAdRevenue, 0) . ' / ' . $this->formatCompactQarTarget($combinedProratedTarget),
                $combinedAdPct,
                true
            ),
            $this->makeKpiBar(
                'Ad Conversion',
                ((int) round($adConversionPct)) . '%',
                $adConversionAttainmentPct
            ),
            $this->makeKpiBar(
                'Funnel Pace',
                ((int) round($funnelPacePct)) . '%',
                $funnelPacePct
            ),
        ];

        return $this->finalizeCard(
            'ayan',
            'Ayan',
            'Paid Ads',
            $this->resolveKpiUrl('kpi.ads.index'),
            $kpis
        );
    }

    /**
     * Zoya & Areeba (Chat Agent): Bookings MTD (X / Y), Today's Quota (X / 13), Chat QA (<5m) (X%)
     *
     * @return array<string, mixed>
     */
    private function buildChatAgentCard(
        string $key,
        string $name,
        string $role,
        int $periodDays,
        Carbon $referenceDate
    ): array {
        $agentUserIds = User::query()
            ->where('role', 'agent')
            ->where('name', 'like', '%' . $name . '%')
            ->pluck('id');

        $dailyTarget = self::AGENT_DAILY_BOOKING_QUOTA;
        $mtdTarget = max(1, $dailyTarget * $periodDays);

        $refDateStr = $referenceDate->toDateString();
        $todayStart = Carbon::parse($refDateStr)->startOfDay();
        $todayEnd = Carbon::parse($refDateStr)->endOfDay();

        $mtdBookings = 0;
        $todayBookings = 0;

        if ($agentUserIds->isNotEmpty()) {
            $rawAppointments = Appointment::query()
                ->scheduledForAgentQuota($agentUserIds)
                ->whereBetween('created_at', [$this->from, $this->to])
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

            $deduped = Appointment::deduplicateForAgentQuota($rawAppointments);
            $mtdBookings = $deduped->count();
            $todayBookings = $deduped
                ->filter(fn (Appointment $appt) => $appt->created_at && $appt->created_at->between($todayStart, $todayEnd))
                ->count();
        }

        $mtdPct = min(100.0, round(($mtdBookings / $mtdTarget) * 100, 1));
        $todayPct = min(100.0, round(($todayBookings / $dailyTarget) * 100, 1));

        $chatEvals = $agentUserIds->isNotEmpty()
            ? KpiChatEvaluation::query()
                ->forAgentIds($agentUserIds)
                ->whereBetween('evaluation_date', [$this->from->copy()->startOfDay(), $this->to->copy()->endOfDay()])
                ->get(['id', 'percentage'])
            : collect();

        $evalCount = $chatEvals->count();
        $chatAvgPct = $evalCount > 0 ? round((float) $chatEvals->avg('percentage'), 1) : 0.0;
        $chatAttainmentPct = $evalCount > 0
            ? min(100.0, round(($chatAvgPct / self::CHAT_QA_TARGET_PCT) * 100, 1))
            : 0.0;

        $kpis = [
            $this->makeKpiBar(
                'Bookings MTD',
                $mtdBookings . ' / ' . $mtdTarget,
                $mtdPct,
                true
            ),
            $this->makeKpiBar(
                "Today's Quota",
                $todayBookings . ' / ' . $dailyTarget,
                $todayPct
            ),
            $this->makeKpiBar(
                'Chat QA (<5m)',
                ((int) round($chatAvgPct)) . '%',
                $chatAttainmentPct
            ),
        ];

        return $this->finalizeCard(
            $key,
            $name,
            $role,
            $this->resolveKpiUrl('kpi.agents.index'),
            $kpis
        );
    }

    /**
     * Laden (Sales Lead): Team Bookings (X / Y), Overdue Leads (N Overdue), Lead Conversion (X%)
     *
     * @return array<string, mixed>
     */
    private function buildLadenCard(int $periodDays, Carbon $referenceDate): array
    {
        $chatAgentIds = User::where('role', 'agent')->pluck('id');
        $dailyTarget = self::LADEN_DAILY_BOOKING_QUOTA;
        $mtdTarget = max(1, $dailyTarget * $periodDays);

        $mtdBookings = 0;

        if ($chatAgentIds->isNotEmpty()) {
            $rawAppointments = Appointment::query()
                ->scheduledForAgentQuota($chatAgentIds)
                ->whereBetween('created_at', [$this->from, $this->to])
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

            $deduped = Appointment::deduplicateForAgentQuota($rawAppointments);
            $mtdBookings = $deduped->count();
        }

        $bookingsPct = min(100.0, round(($mtdBookings / $mtdTarget) * 100, 1));

        $overdueLeadsCount = Lead::open()
            ->whereNotNull('next_followup_date')
            ->whereDate('next_followup_date', '<', today()->toDateString())
            ->count();

        $overdueSweepPct = $overdueLeadsCount === 0
            ? 100.0
            : max(0.0, 80.0 - ($overdueLeadsCount * 20.0));

        $periodLeads = Lead::with('appointment:id,status')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->get(['id', 'appointment_id', 'needful_done']);
        $totalLeadsCount = $periodLeads->count();
        $bookedLeadsCount = $periodLeads->filter(fn (Lead $l) => $l->isBookedConversion())->count();
        $leadConvPct = $totalLeadsCount > 0 ? round(($bookedLeadsCount / $totalLeadsCount) * 100, 1) : 0.0;
        $leadConvAttainmentPct = min(100.0, round(($leadConvPct / self::LEAD_CONVERSION_TARGET_PCT) * 100, 1));

        $kpis = [
            $this->makeKpiBar(
                'Team Bookings',
                $mtdBookings . ' / ' . $mtdTarget,
                $bookingsPct,
                true
            ),
            $this->makeKpiBar(
                'Overdue Leads',
                $overdueLeadsCount . ' Overdue',
                $overdueSweepPct
            ),
            $this->makeKpiBar(
                'Lead Conversion',
                ((int) round($leadConvPct)) . '%',
                $leadConvAttainmentPct
            ),
        ];

        return $this->finalizeCard(
            'laden',
            'Laden',
            'Sales Lead',
            $this->resolveKpiUrl('kpi.customer-service.index'),
            $kpis
        );
    }

    /**
     * Ahana (Old Airport) & Rhea (Al Wakrah): Chair Upsells (QAR X / Y), Staff Evals (X / Y), Branch Audit (X%)
     *
     * @return array<string, mixed>
     */
    private function buildBranchManagerCard(
        string $key,
        string $name,
        string $role,
        string $branch,
        int $periodDays,
        int $daysInMonth,
        bool $isCurrentPeriod
    ): array {
        $analytics = new StaffSalesAnalytics($this->from, $this->to);
        $upsellTotals = $analytics->totals($branch);
        $upsellActual = round((float) ($upsellTotals['team_total'] ?? 0.0), 0);
        $upsellTarget = round((float) ($upsellTotals['team_target'] ?? 0.0), 0);
        if ($upsellTarget <= 0) {
            $upsellTarget = max(1.0, round((StaffSalesAnalytics::DEFAULT_MONTHLY_TARGET / $daysInMonth) * $periodDays, 0));
            $upsellPct = min(100.0, round(($upsellActual / $upsellTarget) * 100, 1));
        } else {
            $upsellPct = min(100.0, round((float) ($upsellTotals['team_pct'] ?? 0.0), 1));
        }

        // Monday Staff Evaluations Compliance
        $allMondays = [];
        foreach (CarbonPeriod::create($this->from->copy()->startOfDay(), $this->to->copy()->startOfDay()) as $day) {
            if ($day->isMonday()) {
                $allMondays[] = $day->toDateString();
            }
        }

        $evalRows = KpiStaffEvaluation::query()
            ->where('branch', $branch)
            ->whereDate('evaluation_date', '>=', $this->from->toDateString())
            ->whereDate('evaluation_date', '<=', $this->to->toDateString())
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
                        $evalMonday = Carbon::parse($e->evaluation_date, 'Asia/Qatar')
                            ->startOfWeek(Carbon::MONDAY)
                            ->toDateString();

                        return $evalMonday === $mondayStr && in_array((int) $e->staff_id, $eligibleIds, true);
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

        $nowQatar = now('Asia/Qatar');
        $currentMondayStr = $nowQatar->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $inMonTueGrace = $isCurrentPeriod
            && ($nowQatar->isMonday() || $nowQatar->isTuesday())
            && isset($perMondayStats[$currentMondayStr])
            && !$perMondayStats[$currentMondayStr]['is_complete'];

        $statsToCount = $perMondayStats;
        if ($inMonTueGrace && count($perMondayStats) > 1) {
            unset($statsToCount[$currentMondayStr]);
        }

        $expectedEvals = array_sum(array_column($statsToCount, 'expected'));
        $completedEvals = array_sum(array_column($statsToCount, 'completed'));
        $evalCompliancePct = $expectedEvals > 0
            ? min(100.0, round(($completedEvals / $expectedEvals) * 100, 1))
            : 0.0;

        // Branch Audit / Mystery Shopper Score
        $audits = BranchEvaluation::query()
            ->where('branch', $branch)
            ->whereDate('evaluation_date', '>=', $this->from->toDateString())
            ->whereDate('evaluation_date', '<=', $this->to->toDateString())
            ->get(['id', 'percentage']);
        $auditsCount = $audits->count();
        $auditAvgPct = $auditsCount > 0 ? round((float) $audits->avg('percentage'), 1) : 0.0;
        $auditAttainmentPct = $auditsCount > 0
            ? min(100.0, round(($auditAvgPct / self::BRANCH_AUDIT_TARGET_PCT) * 100, 1))
            : 0.0;

        $kpis = [
            $this->makeKpiBar(
                'Chair Upsells',
                'QAR ' . number_format($upsellActual, 0) . ' / ' . number_format($upsellTarget, 0),
                $upsellPct,
                true
            ),
            $this->makeKpiBar(
                'Staff Evals',
                $completedEvals . ' / ' . $expectedEvals,
                $evalCompliancePct
            ),
            $this->makeKpiBar(
                'Branch Audit',
                ((int) round($auditAvgPct)) . '%',
                $auditAttainmentPct
            ),
        ];

        return $this->finalizeCard(
            $key,
            $name,
            $role,
            $this->resolveKpiUrl('kpi.staff-sales.index', ['branch' => $branch]),
            $kpis
        );
    }

    /**
     * Arbab (Ops Lead): Overdue Tasks (N Overdue), Ops Actions (X / Y), SLA On-Time (X%)
     *
     * @return array<string, mixed>
     */
    private function buildArbabCard(): array
    {
        $currentMonday = now()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $activeWeekItems = OpsActionItem::forWeek($currentMonday)->get();
        $periodItems = OpsActionItem::query()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->get();

        /** @var Collection<int, OpsActionItem> $opsItems */
        $opsItems = $activeWeekItems->concat($periodItems)->unique('id')->values();
        $totalCount = $opsItems->count();
        $completedCount = $opsItems->where('is_completed', true)->count();
        $overdueCount = $opsItems->filter(fn (OpsActionItem $i) => !$i->is_completed && $i->is_overdue)->count();

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
            $zeroOverduePct = 0.0;
            $completionPct = 0.0;
            $slaPct = 0.0;
        } else {
            $zeroOverduePct = $overdueCount === 0
                ? 100.0
                : max(0.0, 45.0 - (($overdueCount - 1) * 20.0));
            $completionPct = round(($completedCount / $totalCount) * 100, 1);
            $slaPct = round(($onTimeCount / $totalCount) * 100, 1);
        }

        $kpis = [
            $this->makeKpiBar(
                'Overdue Tasks',
                $overdueCount . ' Overdue',
                $zeroOverduePct,
                true
            ),
            $this->makeKpiBar(
                'Ops Actions',
                $completedCount . ' / ' . $totalCount,
                $completionPct
            ),
            $this->makeKpiBar(
                'SLA On-Time',
                ((int) round($slaPct)) . '%',
                $slaPct
            ),
        ];

        return $this->finalizeCard(
            'arbab',
            'Arbab',
            'Ops Lead',
            $this->resolveOpsUrl(),
            $kpis
        );
    }
}
