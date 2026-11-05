<?php

namespace App\Services;

use App\Http\Controllers\LeadController;
use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\ClientPackage;
use App\Models\Customer;
use App\Models\KpiMonthlyAd;
use App\Models\KpiStaffEvaluation;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SocialContentPlan;
use App\Models\Staff;
use App\Models\StaffComplaint;
use App\Models\StaffNotice;
use App\Support\ClientMaintenancePlanner;
use App\Support\StaffSalesAnalytics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-Only F.R.I.D.A.Y. Executive Command Center Service.
 *
 * Every database operation in this service is 100% READ-ONLY (SELECT queries only).
 * It never inserts, updates, or deletes any CRM record.
 */
class LaleenCommandCenterService
{
    public const VOICE_PROFILES = [
        'friday_tactical' => [
            'label' => 'F.R.I.D.A.Y. Tactical (Irish/British HUD)',
            'gemini_voice' => 'Aoede',
            'lang_pref' => ['en-IE', 'en-GB', 'en-US'],
            'rate' => 1.08,
            'pitch' => 1.05,
            'metallic_eq' => true,
        ],
        'kore_coo' => [
            'label' => 'Kore (Firm COO)',
            'gemini_voice' => 'Kore',
            'lang_pref' => ['en-IE', 'en-GB', 'en-US'],
            'rate' => 1.08,
            'pitch' => 1.02,
            'metallic_eq' => false,
        ],
        'aoede_advisor' => [
            'label' => 'Aoede (Warm Advisor)',
            'gemini_voice' => 'Aoede',
            'lang_pref' => ['en-IE', 'en-GB', 'en-US'],
            'rate' => 1.06,
            'pitch' => 1.05,
            'metallic_eq' => false,
        ],
    ];

    public const TOOL_LABELS = [
        'read_revenue_and_bookings' => 'Revenue & Bookings',
        'read_leads_and_money_brief' => 'Leads & Money Brief',
        'read_retention_radar' => 'Retention Radar',
        'read_staff_upsells_and_evaluations' => 'Staff Upsells & Evaluations',
        'read_marketing_and_ads_pulse' => 'Marketing & Ads Pulse',
        'lookup_client_or_staff' => 'Client & Staff Directory',
    ];

    /**
     * Ask F.R.I.D.A.Y. a question or voice command in Fast (Flash) or Deep (Thinking Pro) mode.
     */
    public function askFriday(
        string $prompt,
        string $mode = 'fast',
        string $voiceProfile = 'friday_tactical',
        array $history = [],
        ?string $audioBase64 = null,
        ?string $audioMime = null,
        bool $skipTts = false
    ): array {
        $mode = in_array($mode, ['fast', 'deep'], true) ? $mode : 'fast';
        $voiceProfile = array_key_exists($voiceProfile, self::VOICE_PROFILES) ? $voiceProfile : 'friday_tactical';

        $model = $mode === 'deep'
            ? (string) config('services.gemini.thinking_model', 'gemini-2.5-pro')
            : (string) config('services.gemini.command_model', config('services.gemini.model', 'gemini-2.5-flash'));

        $telemetry = $this->getLiveTelemetrySnapshot();
        $toolsUsed = [];
        $preloadedToolContext = $this->preloadRelevantToolsForPrompt($prompt, $toolsUsed, $telemetry);

        $apiKey = (string) (config('services.gemini.api_key') ?: config('services.gemini.key'));
        $replyText = '';

        if ($apiKey !== '') {
            try {
                $replyText = $this->runGeminiConversationLoop(
                    apiKey: $apiKey,
                    model: $model,
                    mode: $mode,
                    prompt: $prompt,
                    history: $history,
                    telemetry: $telemetry,
                    preloadedToolContext: $preloadedToolContext,
                    toolsUsed: $toolsUsed,
                    audioBase64: $audioBase64,
                    audioMime: $audioMime
                );
            } catch (\Throwable $e) {
                Log::warning('F.R.I.D.A.Y. Gemini request failed, using tactical telemetry fallback.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (trim($replyText) === '') {
            $replyText = $this->buildTacticalFallbackReply($prompt, $telemetry, $preloadedToolContext, $toolsUsed);
        }

        $spokenText = $this->cleanTextForSpeech($replyText);
        $speechPayload = $skipTts
            ? [
                'audio_base64' => null,
                'audio_mime' => null,
                'tts_source' => 'deferred',
                'voice_name' => self::VOICE_PROFILES[$voiceProfile]['gemini_voice'] ?? 'Aoede',
            ]
            : $this->synthesizeSpeech($spokenText, $voiceProfile);

        $uniqueTools = array_values(array_unique($toolsUsed));
        $toolBadges = array_map(fn (string $name) => [
            'name' => $name,
            'label' => self::TOOL_LABELS[$name] ?? $name,
        ], $uniqueTools);

        return [
            'reply' => $replyText,
            'spoken_text' => $spokenText,
            'mode' => $mode,
            'model' => $model,
            'voice_profile' => $voiceProfile,
            'voice_meta' => self::VOICE_PROFILES[$voiceProfile],
            'tools_used' => $uniqueTools,
            'tool_badges' => $toolBadges,
            'audio_base64' => $speechPayload['audio_base64'],
            'audio_mime' => $speechPayload['audio_mime'],
            'tts_source' => $speechPayload['tts_source'],
            'telemetry' => $telemetry,
        ];
    }

    /**
     * Build the live read-only telemetry snapshot (cached for 15 seconds for sub-second response latency).
     */
    public function getLiveTelemetrySnapshot(): array
    {
        $stateFingerprint = implode('_', [
            Sale::count(),
            Appointment::count(),
            Lead::count(),
            Customer::count(),
            AdLeadEntry::count(),
        ]);

        if (Cache::get('friday_live_telemetry_fingerprint') !== $stateFingerprint) {
            Cache::forget('friday_live_telemetry_snapshot');
            Cache::put('friday_live_telemetry_fingerprint', $stateFingerprint, 15);
        }

        return Cache::remember('friday_live_telemetry_snapshot', 15, function () {
            $revenue = $this->read_revenue_and_bookings('all', 'today');
            $leads = $this->read_leads_and_money_brief();
            $retention = $this->read_retention_radar();
            $staff = $this->read_staff_upsells_and_evaluations('all');
            $marketing = $this->read_marketing_and_ads_pulse();

            return [
                'generated_at' => now()->format('d M Y, H:i'),
                'revenue' => $revenue,
                'leads' => $leads,
                'retention' => $retention,
                'staff' => $staff,
                'marketing' => $marketing,
            ];
        });
    }

    /**
     * Execute any of the 6 read-only tools by name.
     */
    public function executeTool(string $toolName, array $args = []): array
    {
        return match ($toolName) {
            'read_revenue_and_bookings' => $this->read_revenue_and_bookings(
                (string) ($args['branch'] ?? 'all'),
                (string) ($args['period'] ?? 'today')
            ),
            'read_leads_and_money_brief' => $this->read_leads_and_money_brief(),
            'read_retention_radar' => $this->read_retention_radar(),
            'read_staff_upsells_and_evaluations' => $this->read_staff_upsells_and_evaluations(
                (string) ($args['branch'] ?? 'all')
            ),
            'read_marketing_and_ads_pulse' => $this->read_marketing_and_ads_pulse(),
            'lookup_client_or_staff' => $this->lookup_client_or_staff(
                (string) ($args['query'] ?? '')
            ),
            default => ['error' => "Unknown read-only tool: {$toolName}"],
        };
    }

    /**
     * TOOL 1: Read-Only Revenue & Bookings across Old Airport and Wakrah.
     */
    public function read_revenue_and_bookings(string $branch = 'all', string $period = 'today'): array
    {
        $branch = strtolower(trim($branch));
        $branches = in_array($branch, ['old_airport', 'wakrah'], true)
            ? [$branch]
            : ['old_airport', 'wakrah'];

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $monthStart = now()->startOfMonth()->startOfDay();
        $monthEnd = now()->endOfMonth()->endOfDay();

        $branchStats = [];
        $totalTodayNet = 0.0;
        $totalMtdNet = 0.0;
        $totalTodayCompleted = 0;
        $totalTodayUpcoming = 0;
        $totalTodayCancelledOrNoShow = 0;
        $totalMtdCompleted = 0;

        foreach ($branches as $bKey) {
            $todaySales = Sale::query()
                ->where('branch', $bKey)
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->get(['id', 'total_amount', 'tip_amount']);

            $todayNet = round((float) $todaySales->sum(fn (Sale $s) => max(0.0, (float) $s->total_amount - (float) $s->tip_amount)), 2);

            $mtdSales = Sale::query()
                ->where('branch', $bKey)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->get(['id', 'total_amount', 'tip_amount']);

            $mtdNet = round((float) $mtdSales->sum(fn (Sale $s) => max(0.0, (float) $s->total_amount - (float) $s->tip_amount)), 2);

            $mtdSaleIds = $mtdSales->pluck('id');
            $paymentSplit = [
                'cash' => $mtdSaleIds->isNotEmpty()
                    ? round((float) SalePayment::whereIn('sale_id', $mtdSaleIds)->where('method', 'cash')->sum('amount'), 2)
                    : 0.0,
                'card' => $mtdSaleIds->isNotEmpty()
                    ? round((float) SalePayment::whereIn('sale_id', $mtdSaleIds)->where('method', 'card')->sum('amount'), 2)
                    : 0.0,
                'online_transfer' => $mtdSaleIds->isNotEmpty()
                    ? round((float) SalePayment::whereIn('sale_id', $mtdSaleIds)->where('method', 'online_transfer')->sum('amount'), 2)
                    : 0.0,
            ];

            $todayAppts = Appointment::query()
                ->where('branch', $bKey)
                ->where(function ($q) use ($todayStart, $todayEnd) {
                    $q->whereBetween('appointment_datetime', [$todayStart, $todayEnd])
                        ->orWhere(function ($sub) use ($todayStart) {
                            $sub->whereNull('appointment_datetime')
                                ->whereDate('booking_date', $todayStart->toDateString());
                        });
                })
                ->get(['status', 'price']);

            $completedToday = $todayAppts->where('status', 'completed')->count();
            $upcomingToday = $todayAppts->whereIn('status', ['pending', 'confirmed', 'scheduled', 'arrived', 'in_progress'])->count();
            $cancelledOrNoShowToday = $todayAppts->whereIn('status', ['cancelled', 'no_show'])->count();
            $projectedOpenQarToday = round((float) $todayAppts->whereIn('status', ['pending', 'confirmed', 'scheduled', 'arrived', 'in_progress'])->sum('price'), 2);

            $mtdCompleted = Appointment::query()
                ->where('branch', $bKey)
                ->where('status', 'completed')
                ->whereBetween('appointment_datetime', [$monthStart, $monthEnd])
                ->count();

            $branchStats[$bKey] = [
                'branch_key' => $bKey,
                'branch_label' => $bKey === 'old_airport' ? 'Old Airport' : 'Wakrah',
                'today_net_qar' => $todayNet,
                'mtd_net_qar' => $mtdNet,
                'mtd_payment_split' => $paymentSplit,
                'today_completed_visits' => $completedToday,
                'today_upcoming_bookings' => $upcomingToday,
                'today_projected_open_qar' => $projectedOpenQarToday,
                'today_cancelled_or_no_show' => $cancelledOrNoShowToday,
                'mtd_completed_visits' => $mtdCompleted,
            ];

            $totalTodayNet += $todayNet;
            $totalMtdNet += $mtdNet;
            $totalTodayCompleted += $completedToday;
            $totalTodayUpcoming += $upcomingToday;
            $totalTodayCancelledOrNoShow += $cancelledOrNoShowToday;
            $totalMtdCompleted += $mtdCompleted;
        }

        return [
            'period_requested' => $period,
            'branch_requested' => $branch,
            'today_total_net_qar' => round($totalTodayNet, 2),
            'mtd_total_net_qar' => round($totalMtdNet, 2),
            'today_completed_visits' => $totalTodayCompleted,
            'today_upcoming_bookings' => $totalTodayUpcoming,
            'today_cancelled_or_no_show' => $totalTodayCancelledOrNoShow,
            'mtd_completed_visits' => $totalMtdCompleted,
            'branches' => $branchStats,
        ];
    }

    /**
     * TOOL 2: Read-Only Leads Funnel & Morning Money Brief.
     */
    public function read_leads_and_money_brief(): array
    {
        /** @var LeadController $leadController */
        $leadController = app(LeadController::class);
        $brief = $leadController->buildMorningMoneyBrief();

        $monthStart = now()->startOfMonth()->startOfDay();
        $monthEnd = now()->endOfMonth()->endOfDay();

        $mtdLeads = Lead::query()
            ->with('agent:id,name')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->get(['id', 'assigned_agent_id', 'needful_done', 'category', 'service_interest']);

        $mtdTotal = $mtdLeads->count();
        $mtdBooked = $mtdLeads->where('needful_done', 'booked')->count();
        $mtdConversionPct = $mtdTotal > 0 ? round(($mtdBooked / $mtdTotal) * 100, 1) : 0.0;

        $agentGroups = $mtdLeads->groupBy(fn (Lead $l) => $l->agent?->name ?: 'Unassigned');
        $agentLeaderboard = [];
        foreach ($agentGroups as $agentName => $rows) {
            $total = $rows->count();
            $booked = $rows->where('needful_done', 'booked')->count();
            $conv = $total > 0 ? round(($booked / $total) * 100, 1) : 0.0;
            $agentLeaderboard[] = [
                'agent' => $agentName,
                'total_leads' => $total,
                'booked_leads' => $booked,
                'conversion_pct' => $conv,
                'status' => $conv >= 30.0 ? 'PASS (>=30%)' : 'BELOW FLOOR (<30%)',
            ];
        }
        usort($agentLeaderboard, fn ($a, $b) => $b['booked_leads'] <=> $a['booked_leads']);

        return [
            'due_today_count' => (int) ($brief['due_today_count'] ?? 0),
            'overdue_count' => (int) ($brief['overdue_count'] ?? 0),
            'unscheduled_count' => (int) ($brief['unscheduled_count'] ?? 0),
            'open_leads_count' => (int) ($brief['open_leads_count'] ?? 0),
            'top_three_potential_qar' => (float) ($brief['top_three_potential_qar'] ?? 0),
            'top_money_leads' => $brief['top_money_leads'] ?? [],
            'brief_text' => (string) ($brief['brief_text'] ?? ''),
            'mtd_total_leads' => $mtdTotal,
            'mtd_booked_leads' => $mtdBooked,
            'mtd_conversion_pct' => $mtdConversionPct,
            'conversion_target_pct' => 30.0,
            'conversion_verdict' => $mtdConversionPct >= 30.0 ? 'PASS' : 'NEEDS ATTENTION',
            'agent_leaderboard' => array_slice($agentLeaderboard, 0, 5),
        ];
    }

    /**
     * TOOL 3: Read-Only Retention Radar (Due for Refill, Packages Ending, Missing VIPs).
     * Strictly SELECT-only: never mutates client_packages or customers.
     */
    public function read_retention_radar(): array
    {
        $todayStart = now()->startOfDay();
        $cooldownCutoff = now()->subDays(Customer::RETENTION_COOLDOWN_DAYS);

        $allCustomers = Customer::query()->get(['id', 'name', 'phone', 'loyalty_points', 'last_retention_contacted_at']);
        $customerMap = $allCustomers->keyBy('id');

        $cooldownCustomerIds = $allCustomers
            ->filter(fn (Customer $c) => $c->last_retention_contacted_at !== null && $c->last_retention_contacted_at->gte($cooldownCutoff))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $upcomingActiveCustomerIds = Appointment::query()
            ->whereNotNull('customer_id')
            ->whereIn('status', ['pending', 'confirmed', 'scheduled', 'arrived', 'in_progress'])
            ->where(function ($q) use ($todayStart) {
                $q->whereDate('appointment_datetime', '>=', $todayStart->toDateString())
                    ->orWhere(function ($sub) use ($todayStart) {
                        $sub->whereNull('appointment_datetime')
                            ->whereDate('booking_date', '>=', $todayStart->toDateString());
                    });
            })
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->flip();

        $completedStats = Appointment::query()
            ->whereNotNull('customer_id')
            ->where('status', 'completed')
            ->selectRaw('customer_id, COUNT(*) as completed_count, MAX(appointment_datetime) as last_visit, SUM(COALESCE(price, 0)) as price_sum')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $salesLtv = Sale::query()
            ->whereNotNull('customer_id')
            ->get(['customer_id', 'services_total', 'products_total', 'packages_total', 'discount_amount'])
            ->groupBy('customer_id')
            ->map(fn ($sales) => round((float) $sales->sum(fn ($s) => max(0, (float) $s->services_total + (float) $s->products_total + (float) $s->packages_total - (float) $s->discount_amount)), 2));

        // 1. Due for Refill (read-only via ClientMaintenancePlanner::dueQueue)
        $dueQueue = (new ClientMaintenancePlanner())->dueQueue();
        $dueForRefillMap = [];
        $dueDetailsMap = [];

        foreach ($dueQueue as $row) {
            $cid = (int) $row->customer_id;
            if ($upcomingActiveCustomerIds->has($cid) || $cooldownCustomerIds->has($cid)) {
                continue;
            }

            $stat = $completedStats->get($cid);
            $completedCount = $stat ? (int) $stat->completed_count : (int) ($row->completed_visits_count ?? 1);
            $lastVisitAt = ($stat && $stat->last_visit) ? Carbon::parse($stat->last_visit) : null;
            $daysSinceLastVisit = $lastVisitAt
                ? (int) floor(($todayStart->timestamp - $lastVisitAt->copy()->startOfDay()->timestamp) / 86400)
                : (int) ($row->days_since_visit ?? 0);
            $netLtv = $salesLtv->has($cid)
                ? (float) $salesLtv->get($cid)
                : ($stat ? (float) $stat->price_sum : 0.0);

            $hasExplicitInterval = (bool) ($row->has_explicit_interval ?? true);
            if (!$hasExplicitInterval && ($completedCount >= 2 || $netLtv >= 500) && $daysSinceLastVisit > 45) {
                continue;
            }

            $dueForRefillMap[$cid] = true;
            if (!isset($dueDetailsMap[$cid])) {
                $daysUntil = (int) $row->days_until;
                $dueDetailsMap[$cid] = [
                    'service_name' => $row->service_name,
                    'urgency' => $row->urgency,
                    'days_until' => $daysUntil,
                    'days_overdue' => $daysUntil < 0 ? abs($daysUntil) : 0,
                    'last_staff_name' => $row->last_staff_name ?? '',
                    'branch' => $row->branch ?? '',
                    'completed_visits_count' => $completedCount,
                ];
            }
        }

        $dueForRefill = collect(array_keys($dueForRefillMap))->values();
        $dueLookup = $dueForRefill->flip();

        // 2. Packages Ending (1 session left OR expiring within 21 days, read-only check without mutating expired rows)
        $threeWeeksOut = now()->addDays(21)->endOfDay();
        $activePackages = ClientPackage::query()
            ->withCount(['services as redeemed_services_count' => fn ($q) => $q->where('status', 'redeemed')])
            ->where('status', 'active')
            ->where(function ($q) use ($todayStart) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $todayStart);
            })
            ->where(function ($q) {
                $q->whereNotNull('sale_id')->orWhereNull('appointment_id');
            })
            ->get();

        $packageRenewal = $activePackages
            ->filter(function (ClientPackage $pkg) use ($threeWeeksOut) {
                $remaining = max(0, (int) $pkg->quantity_included - (int) $pkg->redeemed_services_count);
                return $remaining <= 1 || ($pkg->expires_at && $pkg->expires_at->lte($threeWeeksOut));
            })
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $upcomingActiveCustomerIds->has($id) || $cooldownCustomerIds->has($id) || $dueLookup->has($id))
            ->values();
        $pkgLookup = $packageRenewal->flip();

        // 3. Missing VIPs (2+ visits or >=500 QAR LTV, last visit > 45 days ago)
        $fortyFiveDaysAgo = $todayStart->copy()->subDays(45);
        $bleedingVips = collect();
        foreach ($completedStats as $cid => $stat) {
            $cid = (int) $cid;
            if ($upcomingActiveCustomerIds->has($cid) || $cooldownCustomerIds->has($cid)) {
                continue;
            }
            if ($dueLookup->has($cid) || $pkgLookup->has($cid)) {
                continue;
            }

            $visits = (int) $stat->completed_count;
            $ltvVal = (float) ($salesLtv[$cid] ?? $stat->price_sum ?? 0);
            $lastVisitDate = $stat->last_visit ? Carbon::parse($stat->last_visit) : null;

            if (($visits >= 2 || $ltvVal >= 500) && $lastVisitDate && $lastVisitDate->lt($fortyFiveDaysAgo)) {
                $bleedingVips->push($cid);
            }
        }

        $formatTopClients = function ($ids, string $bucket) use ($customerMap, $dueDetailsMap, $salesLtv, $completedStats) {
            return collect($ids)->take(5)->map(function (int $id) use ($customerMap, $dueDetailsMap, $salesLtv, $completedStats, $bucket) {
                $c = $customerMap->get($id);
                $detail = $dueDetailsMap[$id] ?? null;
                $ltv = (float) ($salesLtv[$id] ?? $completedStats->get($id)?->price_sum ?? 0);
                return [
                    'customer_id' => $id,
                    'name' => $c?->name ?? 'Client #' . $id,
                    'phone' => $c?->phone ?? '',
                    'ltv_qar' => round($ltv, 2),
                    'service' => $detail['service_name'] ?? 'Salon Service',
                    'days_overdue' => (int) ($detail['days_overdue'] ?? 0),
                    'bucket' => $bucket,
                ];
            })->values()->all();
        };

        return [
            'due_for_refill_count' => $dueForRefill->count(),
            'package_renewal_count' => $packageRenewal->count(),
            'bleeding_vips_count' => $bleedingVips->count(),
            'total_actionable_clients' => $dueForRefill->count() + $packageRenewal->count() + $bleedingVips->count(),
            'due_for_refill_top' => $formatTopClients($dueForRefill, 'due_for_refill'),
            'package_renewal_top' => $formatTopClients($packageRenewal, 'package_renewal'),
            'bleeding_vips_top' => $formatTopClients($bleedingVips, 'bleeding_vips'),
        ];
    }

    /**
     * TOOL 4: Read-Only Staff Upsells & Weekly Evaluations.
     */
    public function read_staff_upsells_and_evaluations(string $branch = 'all'): array
    {
        $monthStart = now()->startOfMonth();
        $today = now();

        $analytics = new StaffSalesAnalytics($monthStart, $today, StaffSalesAnalytics::DEFAULT_MONTHLY_TARGET);
        $proratedTarget = $analytics->proratedTarget();
        $branchSummary = $analytics->branchComparison();

        $branchKey = strtolower(trim($branch));
        if (in_array($branchKey, ['old_airport', 'wakrah'], true)) {
            $staffRows = $analytics->computedStaff($branchKey)->sortByDesc('upsell')->values()->all();
        } else {
            $staffRows = $analytics->allStaffComputed()->sortByDesc('upsell')->values()->all();
        }

        $weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $evalQuery = KpiStaffEvaluation::query()
            ->whereBetween('evaluation_date', [$weekStart, $weekEnd]);
        if (in_array($branchKey, ['old_airport', 'wakrah'], true)) {
            $evalQuery->where('branch', $branchKey);
        }
        $weekEvals = $evalQuery->get(['branch', 'overall_average']);
        $avgEvalScore = $weekEvals->isNotEmpty()
            ? round((float) $weekEvals->avg('overall_average'), 2)
            : null;

        $openComplaintsCount = StaffComplaint::query()
            ->where('status', '!=', 'resolved')
            ->count();

        $mtdNoticesCount = StaffNotice::query()
            ->whereBetween('notice_date', [$monthStart->toDateString(), $today->toDateString()])
            ->count();

        return [
            'monthly_target_per_stylist_qar' => StaffSalesAnalytics::DEFAULT_MONTHLY_TARGET,
            'prorated_target_qar' => $proratedTarget,
            'green_floor_pct' => 85.0,
            'branch_summary' => $branchSummary,
            'top_stylists' => array_slice($staffRows, 0, 6),
            'bottom_stylists' => array_slice(array_reverse($staffRows), 0, 4),
            'current_week_evaluations_count' => $weekEvals->count(),
            'current_week_avg_score' => $avgEvalScore,
            'open_complaints_count' => $openComplaintsCount,
            'mtd_notices_count' => $mtdNoticesCount,
        ];
    }

    /**
     * TOOL 5: Read-Only Marketing, Monthly Ads & Weekly Content Pulse.
     */
    public function read_marketing_and_ads_pulse(): array
    {
        $now = now();
        $adLeads = AdLeadEntry::query()
            ->whereYear('date', $now->year)
            ->whereMonth('date', $now->month)
            ->get(['id', 'branch', 'ticket_amount', 'category']);

        $totalAdLeads = $adLeads->count();
        $bookedAdLeads = $adLeads->filter(fn (AdLeadEntry $e) => !empty($e->branch) || (float) $e->ticket_amount > 0)->count();
        $adConversionPct = $totalAdLeads > 0 ? round(($bookedAdLeads / $totalAdLeads) * 100, 1) : 0.0;
        $attributedRevenueQar = round((float) $adLeads->sum('ticket_amount'), 2);

        $adsByPhase = KpiMonthlyAd::query()
            ->selectRaw('phase, COUNT(*) as total, SUM(COALESCE(total_budget, 0)) as budget_sum')
            ->groupBy('phase')
            ->get()
            ->keyBy('phase')
            ->map(fn ($row) => [
                'count' => (int) $row->total,
                'budget_qar' => round((float) $row->budget_sum, 2),
            ])
            ->all();

        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekPlans = SocialContentPlan::query()
            ->where('week_start_date', $weekStart)
            ->get(['is_reel_posted', 'are_stories_posted']);

        $reelsPosted = $weekPlans->where('is_reel_posted', true)->count();
        $storiesPosted = $weekPlans->where('are_stories_posted', true)->count();
        $reelTarget = SocialContentPlan::WEEKLY_REEL_TARGET;
        $storiesTarget = SocialContentPlan::WEEKLY_STORIES_TARGET;

        $contentCompliancePct = round((($reelsPosted + $storiesPosted) / max(1, $reelTarget + $storiesTarget)) * 100, 1);

        return [
            'current_month' => $now->format('F Y'),
            'ad_leads_total' => $totalAdLeads,
            'ad_leads_booked' => $bookedAdLeads,
            'ad_conversion_pct' => $adConversionPct,
            'ad_conversion_target_pct' => 20.0,
            'ad_conversion_verdict' => $adConversionPct >= 20.0 ? 'PASS (>=20%)' : 'BELOW FLOOR (<20%)',
            'attributed_ad_revenue_qar' => $attributedRevenueQar,
            'monthly_ads_by_phase' => $adsByPhase,
            'week_start_date' => $weekStart,
            'reels_posted' => $reelsPosted,
            'reels_target' => $reelTarget,
            'stories_posted' => $storiesPosted,
            'stories_target' => $storiesTarget,
            'content_compliance_pct' => $contentCompliancePct,
        ];
    }

    /**
     * TOOL 6: Read-Only Client or Staff Directory Lookup.
     */
    public function lookup_client_or_staff(string $query): array
    {
        $clean = trim($query);
        if ($clean === '') {
            return [
                'query' => '',
                'clients' => [],
                'staff' => [],
            ];
        }

        $customers = Customer::query()
            ->where('name', 'like', "%{$clean}%")
            ->orWhere('phone', 'like', "%{$clean}%")
            ->limit(5)
            ->get();

        $clientResults = $customers->map(function (Customer $c) {
            $completedVisits = Appointment::query()
                ->where('customer_id', $c->id)
                ->where('status', 'completed')
                ->count();

            $lastVisit = Appointment::query()
                ->where('customer_id', $c->id)
                ->where('status', 'completed')
                ->max('appointment_datetime');

            $activePackages = ClientPackage::query()
                ->where('customer_id', $c->id)
                ->where('status', 'active')
                ->pluck('combo_name')
                ->values()
                ->all();

            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'completed_visits' => $completedVisits,
                'ltv_qar' => $c->total_spent,
                'loyalty_points' => (int) $c->loyalty_points,
                'last_visit' => $lastVisit ? Carbon::parse($lastVisit)->format('d M Y') : null,
                'active_packages' => $activePackages,
            ];
        })->values()->all();

        $staffMatches = Staff::query()
            ->where('name', 'like', "%{$clean}%")
            ->orWhere('role', 'like', "%{$clean}%")
            ->limit(5)
            ->get();

        $analytics = new StaffSalesAnalytics(now()->startOfMonth(), now());
        $oaMap = $analytics->computedStaff('old_airport')->keyBy('staff_id');
        $wkMap = $analytics->computedStaff('wakrah')->keyBy('staff_id');

        $staffResults = $staffMatches->map(function (Staff $s) use ($oaMap, $wkMap) {
            $row = $oaMap->get($s->id) ?? $wkMap->get($s->id);
            return [
                'id' => $s->id,
                'name' => $s->name,
                'role' => $s->role,
                'branch' => $s->branch,
                'mtd_upsell_qar' => (float) ($row['upsell'] ?? 0.0),
                'prorated_target_qar' => (float) ($row['prorated_target'] ?? $analyticsProrated = 0.0),
                'target_pct' => (float) ($row['pct'] ?? 0.0),
            ];
        })->values()->all();

        return [
            'query' => $clean,
            'clients' => $clientResults,
            'staff' => $staffResults,
        ];
    }

    /**
     * Synthesize speech via Gemini TTS model (cached for 10 minutes by md5($spokenText)) + return WebAudio/WebSpeech metadata.
     */
    public function synthesizeSpeech(string $spokenText, string $voiceProfile = 'friday_tactical'): array
    {
        $profile = self::VOICE_PROFILES[$voiceProfile] ?? self::VOICE_PROFILES['friday_tactical'];
        $voiceName = $profile['gemini_voice'] ?? 'Aoede';
        $apiKey = (string) (config('services.gemini.api_key') ?: config('services.gemini.key'));
        $primaryTtsModel = (string) config('services.gemini.tts_model', 'gemini-2.5-flash-preview-tts');
        $candidateModels = array_values(array_unique(array_filter([$primaryTtsModel, 'gemini-2.0-flash'])));

        if ($apiKey !== '' && trim($spokenText) !== '') {
            $cacheKey = 'friday_tts_' . md5($voiceProfile . '|' . $voiceName . '|' . trim($spokenText));
            $cachedAudio = Cache::get($cacheKey);
            if (is_array($cachedAudio) && !empty($cachedAudio['audio_base64'])) {
                return $cachedAudio;
            }

            $styledTtsPrompt = 'Read the following text aloud in a brisk, warm, crisp Irish-British female AI assistant voice like FRIDAY from Iron Man—natural, confident, slightly fast-paced, and conversational, speaking only the exact words after the colon: ' . $spokenText;

            foreach ($candidateModels as $ttsModel) {
                try {
                    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$ttsModel}:generateContent?key={$apiKey}";
                    $response = Http::timeout(10)->post($endpoint, [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $styledTtsPrompt],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'responseModalities' => ['AUDIO'],
                            'speechConfig' => [
                                'voiceConfig' => [
                                    'prebuiltVoiceConfig' => [
                                        'voiceName' => $voiceName,
                                    ],
                                ],
                            ],
                        ],
                    ]);

                    if ($response->successful()) {
                        $inlineData = data_get($response->json(), 'candidates.0.content.parts.0.inlineData');
                        $rawBase64 = (string) ($inlineData['data'] ?? '');
                        $mimeType = (string) ($inlineData['mimeType'] ?? 'audio/wav');

                        if ($rawBase64 !== '') {
                            if (str_contains(strtolower($mimeType), 'l16') || str_contains(strtolower($mimeType), 'pcm')) {
                                $pcmBytes = base64_decode($rawBase64, true);
                                if ($pcmBytes !== false) {
                                    $wavBytes = $this->wrapPcm16InWavHeader($pcmBytes, 24000, 1, 16);
                                    $result = [
                                        'audio_base64' => base64_encode($wavBytes),
                                        'audio_mime' => 'audio/wav',
                                        'tts_source' => 'gemini_tts',
                                        'voice_name' => $voiceName,
                                    ];
                                    Cache::put($cacheKey, $result, 600);
                                    return $result;
                                }
                            }

                            $result = [
                                'audio_base64' => $rawBase64,
                                'audio_mime' => $mimeType ?: 'audio/wav',
                                'tts_source' => 'gemini_tts',
                                'voice_name' => $voiceName,
                            ];
                            Cache::put($cacheKey, $result, 600);
                            return $result;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::info("F.R.I.D.A.Y. Gemini TTS attempt ({$ttsModel}) fell back.", ['message' => $e->getMessage()]);
                }
            }
        }

        return [
            'audio_base64' => null,
            'audio_mime' => null,
            'tts_source' => 'web_speech_hud',
            'voice_name' => $voiceName,
        ];
    }

    /**
     * Wrap raw 16-bit signed little-endian PCM bytes in a standard 44-byte RIFF WAV header.
     */
    public function wrapPcm16InWavHeader(string $pcmBytes, int $sampleRate = 24000, int $channels = 1, int $bitsPerSample = 16): string
    {
        $dataSize = strlen($pcmBytes);
        $byteRate = (int) ($sampleRate * $channels * ($bitsPerSample / 8));
        $blockAlign = (int) ($channels * ($bitsPerSample / 8));

        $header = pack(
            'A4VA4A4VvvVVvvA4V',
            'RIFF',
            36 + $dataSize,
            'WAVE',
            'fmt ',
            16,
            1, // PCM format
            $channels,
            $sampleRate,
            $byteRate,
            $blockAlign,
            $bitsPerSample,
            'data',
            $dataSize
        );

        return $header . $pcmBytes;
    }

    /**
     * Build the 3-Way Hybrid Brain System Instruction (50% F.R.I.D.A.Y., 35% Alex Hormozi, 15% Sheryl Sandberg).
     */
    public function buildSystemInstruction(array $telemetry, array $preloadedToolContext, string $mode): string
    {
        $telemetryJson = json_encode($telemetry, JSON_UNESCAPED_UNICODE);
        $preloadedJson = json_encode($preloadedToolContext, JSON_UNESCAPED_UNICODE);
        $depthInstruction = $mode === 'deep'
            ? 'DEEP THINKING MODE: Give a sharp, spoken-style executive diagnosis (2 to 4 crisp spoken sentences max unless Boss explicitly asks for a full breakdown) with exact QAR math, root-cause bottleneck insight, and the #1 immediate move.'
            : 'QUICK CONVERSATION MODE: You are in a live, real-time voice conversation with the Boss. Keep your reply to 1 to 3 short, natural spoken sentences max.';

        return <<<PROMPT
You are F.R.I.D.A.Y., Tony Stark's tactical AI assistant, serving as the live, read-only Executive Voice Secretary and Business Consultant for Laleen Beauty Salon in Qatar.

VOICE & CADENCE RULES (CRITICAL):
- You are speaking aloud in a real-time back-and-forth voice call with the Boss.
- NEVER output markdown bullet lists, numbered lists, asterisks, bold headers, or essay-style walls of text.
- Speak in 1 to 3 crisp, natural conversational sentences using natural contractions ("You've got", "We're sitting at", "Right now, Boss", "That's") and warm Irish/Celtic phrasing ("Right then, Boss", "Grand", "Here's the picture", "Shall I pull up the names?").
- Combine your 3-Way Hybrid Brain seamlessly into your spoken reply:
  * 50% F.R.I.D.A.Y.: Warm, brisk Irish-tactical cadence, zero fluff, addresses the owner as "Boss".
  * 35% Alex Hormozi: Translate raw counts into exact QAR impact, LTV, speed-to-lead, and upsell math.
  * 15% Sheryl Sandberg (COO): Hold the line on our salon benchmarks (2 branches only: Old Airport and Wakrah; 30% lead conversion floor; 20% ad conversion floor; 1,700 QAR/month prorated stylist upsell target at >=85% green floor; 4 Reels + 7 Stories weekly content target).
  * Strictly READ-ONLY access: You never create, edit, or delete database records.

{$depthInstruction}

LIVE SALON TELEMETRY SNAPSHOT (JSON):
{$telemetryJson}

PRE-INSPECTED TOOL DATA FOR THIS QUERY (JSON):
{$preloadedJson}
PROMPT;
    }

    /**
     * Gemini function declarations for the 6 read-only CRM tools.
     */
    public function geminiFunctionDeclarations(): array
    {
        return [
            [
                'name' => 'read_revenue_and_bookings',
                'description' => 'Read net cash/card revenue collected today vs MTD, completed visits, upcoming bookings, and cancellations across Old Airport and Wakrah.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'branch' => ['type' => 'STRING', 'description' => 'all, old_airport, or wakrah'],
                        'period' => ['type' => 'STRING', 'description' => 'today or mtd'],
                    ],
                ],
            ],
            [
                'name' => 'read_leads_and_money_brief',
                'description' => 'Read the Morning Money Brief top 3 callable leads, open pipeline QAR, overdue follow-ups, due-today count, and MTD conversion rate + agent leaderboard.',
            ],
            [
                'name' => 'read_retention_radar',
                'description' => 'Read exact counts and top clients in Due for Refill, Packages Ending, and Missing VIPs.',
            ],
            [
                'name' => 'read_staff_upsells_and_evaluations',
                'description' => 'Read MTD stylist upsell QAR vs prorated 1,700 QAR target, current-week staff evaluation averages, complaints, and notices.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'branch' => ['type' => 'STRING', 'description' => 'all, old_airport, or wakrah'],
                    ],
                ],
            ],
            [
                'name' => 'read_marketing_and_ads_pulse',
                'description' => 'Read current-month AdLeadEntry conversion %, attributed QAR revenue, Monthly Ads Kanban phase counts, and weekly Reels/Stories compliance.',
            ],
            [
                'name' => 'lookup_client_or_staff',
                'description' => 'Search a specific client (by name or phone) or staff member (by name or role) read-only.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => ['type' => 'STRING', 'description' => 'Client name, phone number, or staff name'],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }

    /**
     * Run the Gemini generateContent request and handle any functionCall tool hops.
     */
    private function runGeminiConversationLoop(
        string $apiKey,
        string $model,
        string $mode,
        string $prompt,
        array $history,
        array $telemetry,
        array $preloadedToolContext,
        array &$toolsUsed,
        ?string $audioBase64 = null,
        ?string $audioMime = null
    ): string {
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
        $contents = [];

        foreach (array_slice($history, -6) as $turn) {
            $role = ($turn['role'] ?? 'user') === 'assistant' || ($turn['role'] ?? '') === 'model' ? 'model' : 'user';
            $text = trim((string) ($turn['content'] ?? $turn['text'] ?? ''));
            if ($text !== '') {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $text]],
                ];
            }
        }

        $userParts = [];
        if ($audioBase64 && $audioMime) {
            $userParts[] = [
                'inlineData' => [
                    'mimeType' => $audioMime,
                    'data' => $audioBase64,
                ],
            ];
        }
        $userParts[] = [
            'text' => $prompt !== '' ? $prompt : 'Boss sent a voice command. Listen to the audio and respond as F.R.I.D.A.Y. with live salon metrics.',
        ];

        $contents[] = [
            'role' => 'user',
            'parts' => $userParts,
        ];

        $systemInstruction = $this->buildSystemInstruction($telemetry, $preloadedToolContext, $mode);
        $needsDirectoryLookupTool = $mode === 'deep'
            || (bool) preg_match('/(?:\d{4,}|lookup|find|search|client|customer|staff|stylist|who is)/i', $prompt);
        $maxHops = $mode === 'deep' ? 3 : 2;

        for ($hop = 0; $hop < $maxHops; $hop++) {
            $generationConfig = $mode === 'deep'
                ? [
                    'temperature' => 0.35,
                    'maxOutputTokens' => 900,
                ]
                : [
                    'temperature' => 0.4,
                    'maxOutputTokens' => 140,
                    'thinkingConfig' => [
                        'thinkingBudget' => 0,
                    ],
                ];

            $payload = [
                'systemInstruction' => [
                    'parts' => [['text' => $systemInstruction]],
                ],
                'contents' => $contents,
                'generationConfig' => $generationConfig,
            ];

            if ($mode === 'deep') {
                $payload['tools'] = [
                    [
                        'functionDeclarations' => $this->geminiFunctionDeclarations(),
                    ],
                ];
            } elseif ($needsDirectoryLookupTool && $hop === 0) {
                $payload['tools'] = [
                    [
                        'functionDeclarations' => array_values(array_filter(
                            $this->geminiFunctionDeclarations(),
                            fn (array $decl) => $decl['name'] === 'lookup_client_or_staff'
                        )),
                    ],
                ];
            }

            $response = Http::timeout($mode === 'deep' ? 25 : 10)->post($endpoint, $payload);
            if (!$response->successful()) {
                break;
            }

            $parts = data_get($response->json(), 'candidates.0.content.parts', []);
            if (!is_array($parts) || empty($parts)) {
                break;
            }

            $functionCalls = [];
            $textFragments = [];

            foreach ($parts as $part) {
                if (!empty($part['functionCall']['name'])) {
                    $functionCalls[] = $part['functionCall'];
                }
                if (isset($part['text']) && trim((string) $part['text']) !== '') {
                    $textFragments[] = trim((string) $part['text']);
                }
            }

            if (empty($functionCalls)) {
                return implode("\n\n", $textFragments);
            }

            $contents[] = [
                'role' => 'model',
                'parts' => $parts,
            ];

            $responseParts = [];
            foreach ($functionCalls as $call) {
                $fnName = (string) $call['name'];
                $fnArgs = is_array($call['args'] ?? null) ? $call['args'] : [];
                $toolsUsed[] = $fnName;
                $toolOutput = $this->executeTool($fnName, $fnArgs);

                $responseParts[] = [
                    'functionResponse' => [
                        'name' => $fnName,
                        'response' => [
                            'result' => $toolOutput,
                        ],
                    ],
                ];
            }

            $contents[] = [
                'role' => 'user',
                'parts' => $responseParts,
            ];
        }

        return '';
    }

    /**
     * Pre-inspect relevant read-only tools based on the prompt keywords using the cached
     * telemetry snapshot so zero duplicate DB queries run during a turn.
     */
    private function preloadRelevantToolsForPrompt(string $prompt, array &$toolsUsed, array $telemetry = []): array
    {
        $q = strtolower($prompt);
        $context = [];

        if ($q === '' || str_contains($q, 'morning') || str_contains($q, 'brief') || str_contains($q, 'executive') || str_contains($q, 'overview') || str_contains($q, 'pulse')) {
            $toolsUsed[] = 'read_revenue_and_bookings';
            $toolsUsed[] = 'read_leads_and_money_brief';
            $toolsUsed[] = 'read_retention_radar';
            $context['revenue'] = $telemetry['revenue'] ?? $this->read_revenue_and_bookings('all', 'today');
            $context['leads'] = $telemetry['leads'] ?? $this->read_leads_and_money_brief();
            $context['retention'] = $telemetry['retention'] ?? $this->read_retention_radar();
        }

        if (str_contains($q, 'cash') || str_contains($q, 'revenue') || str_contains($q, 'airport') || str_contains($q, 'wakrah') || str_contains($q, 'booking') || str_contains($q, 'sale')) {
            $toolsUsed[] = 'read_revenue_and_bookings';
            $context['revenue'] = $telemetry['revenue'] ?? $this->read_revenue_and_bookings('all', 'today');
        }

        if (str_contains($q, 'lead') || str_contains($q, 'money') || str_contains($q, 'call') || str_contains($q, 'overdue') || str_contains($q, 'conversion')) {
            $toolsUsed[] = 'read_leads_and_money_brief';
            $context['leads'] = $telemetry['leads'] ?? $this->read_leads_and_money_brief();
        }

        if (str_contains($q, 'retention') || str_contains($q, 'refill') || str_contains($q, 'vip') || str_contains($q, 'package') || str_contains($q, 'churn') || str_contains($q, 'leak')) {
            $toolsUsed[] = 'read_retention_radar';
            $context['retention'] = $telemetry['retention'] ?? $this->read_retention_radar();
        }

        if (str_contains($q, 'staff') || str_contains($q, 'upsell') || str_contains($q, 'stylist') || str_contains($q, 'leaderboard') || str_contains($q, 'evaluation')) {
            $toolsUsed[] = 'read_staff_upsells_and_evaluations';
            $context['staff'] = $telemetry['staff'] ?? $this->read_staff_upsells_and_evaluations('all');
        }

        if (str_contains($q, 'ad') || str_contains($q, 'marketing') || str_contains($q, 'roas') || str_contains($q, 'reel') || str_contains($q, 'content') || str_contains($q, 'story')) {
            $toolsUsed[] = 'read_marketing_and_ads_pulse';
            $context['marketing'] = $telemetry['marketing'] ?? $this->read_marketing_and_ads_pulse();
        }

        if (preg_match('/(?:lookup|find|search|client|customer|who is)\s+([a-z0-9\+\s]{2,30})/i', $prompt, $m)) {
            $searchTerm = trim($m[1]);
            if ($searchTerm !== '') {
                $toolsUsed[] = 'lookup_client_or_staff';
                $context['lookup'] = $this->lookup_client_or_staff($searchTerm);
            }
        }

        if (empty($toolsUsed)) {
            $toolsUsed[] = 'read_revenue_and_bookings';
            $toolsUsed[] = 'read_leads_and_money_brief';
        }

        return $context;
    }

    /**
     * Deterministic F.R.I.D.A.Y. tactical response when Gemini API key is not configured or unreachable.
     */
    private function buildTacticalFallbackReply(string $prompt, array $telemetry, array $preloadedToolContext, array &$toolsUsed): string
    {
        $rev = $telemetry['revenue'];
        $leads = $telemetry['leads'];
        $ret = $telemetry['retention'];
        $staff = $telemetry['staff'];
        $mkt = $telemetry['marketing'];

        $oaToday = number_format((float) ($rev['branches']['old_airport']['today_net_qar'] ?? 0), 0);
        $wkToday = number_format((float) ($rev['branches']['wakrah']['today_net_qar'] ?? 0), 0);
        $mtdNet = number_format((float) ($rev['mtd_total_net_qar'] ?? 0), 0);
        $topQar = number_format((float) ($leads['top_three_potential_qar'] ?? 0), 0);

        $q = strtolower($prompt);

        if (str_contains($q, 'retention') || str_contains($q, 'leak') || str_contains($q, 'vip')) {
            return "Right then, Boss—we've got {$ret['due_for_refill_count']} clients due for a refill, {$ret['package_renewal_count']} with packages ending soon, and {$ret['bleeding_vips_count']} missing VIPs over 45 days away. That's {$ret['total_actionable_clients']} high-LTV clients ready for a call today—shall I read out the top names?";
        }

        if (str_contains($q, 'upsell') || str_contains($q, 'staff') || str_contains($q, 'leaderboard')) {
            $prorated = number_format((float) ($staff['prorated_target_qar'] ?? 0), 0);
            $topName = $staff['top_stylists'][0]['name'] ?? 'our lead stylist';
            $topAmt = number_format((float) ($staff['top_stylists'][0]['upsell'] ?? 0), 0);
            return "Right now, Boss, our prorated stylist upsell benchmark sits at {$prorated} QAR against the 1,700 QAR monthly target. {$topName} is leading the floor at {$topAmt} QAR this month, and we've got {$staff['open_complaints_count']} open complaints.";
        }

        if (str_contains($q, 'marketing') || str_contains($q, 'ad') || str_contains($q, 'roas')) {
            return "Here's the marketing pulse, Boss: we've pulled in {$mkt['ad_leads_total']} ad leads this month at {$mkt['ad_conversion_pct']}% conversion, banking " . number_format((float) $mkt['attributed_ad_revenue_qar'], 0) . " QAR in verified revenue. Weekly content is sitting at {$mkt['reels_posted']} of {$mkt['reels_target']} Reels and {$mkt['stories_posted']} of {$mkt['stories_target']} Stories.";
        }

        return "Right then, Boss—we're sitting at {$oaToday} QAR today in Old Airport and {$wkToday} QAR in Wakrah, bringing us to {$mtdNet} QAR for the month. You've got {$leads['overdue_count']} overdue leads and {$leads['due_today_count']} due today, and calling the top three right now unlocks about {$topQar} QAR.";
    }

    /**
     * Strip markdown formatting, bullet prefixes, and emojis so spoken TTS and Web Speech API sound natural and crisp.
     */
    private function cleanTextForSpeech(string $text): string
    {
        $clean = preg_replace('/^[\s\-\*\d\.]+/m', '', $text);
        $clean = preg_replace('/[*#`_~>]+/', '', (string) $clean);
        $clean = preg_replace('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}]/u', '', (string) $clean);
        $clean = preg_replace('/\s+/', ' ', (string) $clean);
        return trim((string) $clean);
    }
}
