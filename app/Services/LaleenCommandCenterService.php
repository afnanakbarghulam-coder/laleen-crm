<?php

namespace App\Services;

use App\Http\Controllers\LeadController;
use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\ClientPackage;
use App\Models\Combo;
use App\Models\Customer;
use App\Models\EcommerceExpense;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use App\Models\Expense;
use App\Models\KpiMonthlyAd;
use App\Models\KpiStaffEvaluation;
use App\Models\Lead;
use App\Models\LegalTask;
use App\Models\OpsActionItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Service;
use App\Models\ServiceCategory;
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
use Illuminate\Support\Facades\Schema;

/**
 * Read-Only Command Center Service (Text-Chat Only).
 *
 * Every database operation in this service is 100% READ-ONLY (SELECT queries only).
 * It never inserts, updates, or deletes any CRM record, and strictly excludes
 * staff salaries, contract compensation, and PIN codes from all contexts.
 */
class LaleenCommandCenterService
{
    public const TOOL_LABELS = [
        'read_revenue_and_bookings' => 'Revenue & Bookings',
        'read_leads_and_money_brief' => 'Leads & Follow-Ups',
        'read_ops_actions' => 'Pending Ops Actions',
        'read_retention_radar' => 'Retention Radar',
        'read_staff_upsells_and_evaluations' => 'Staff Upsells & Evaluations',
        'read_marketing_and_ads_pulse' => 'Marketing & Ads Pulse',
        'lookup_client_or_staff' => 'Client & Staff Directory',
        'read_calendar_gaps_and_idle_capacity' => 'Chair-Gap & Idle Capacity',
        'read_unrecovered_cancellations' => 'Unrecovered Cancellations',
        'draft_whatsapp_closer_pitch' => 'WhatsApp Closer Draft',
        'read_full_crm_catalog_inventory_finance_and_ecommerce' => 'Finance, Catalog, Inventory & Ecommerce',
    ];

    /**
     * Sensitive staff payroll, contract compensation, and PIN keys that must NEVER enter
     * any LLM prompt, tool response, or telemetry payload.
     *
     * @var list<string>
     */
    private const SENSITIVE_PRIVACY_KEYS = [
        'base_salary',
        'hourly_wage',
        'commission_rate',
        'housing_allowance',
        'transport_allowance',
        'other_allowance',
        'salary',
        'salaries',
        'wage',
        'wages',
        'compensation',
        'compensation_amount',
        'contract_pin',
        'pin',
        'pin_code',
        'pin_hash',
        'staff_contract',
        'staff_contracts',
        'clauses',
        'clauses_body',
        'secure_token',
        'access_token',
        'signature_data',
    ];

    /**
     * Process a text chat message in Fast or Deep mode (zero voice/audio/TTS).
     */
    public function ask(
        string $prompt,
        string $mode = 'fast',
        array $history = []
    ): array {
        $mode = in_array($mode, ['fast', 'deep'], true) ? $mode : 'fast';
        $cleanPrompt = trim($prompt);

        $model = $mode === 'deep'
            ? (string) config('services.gemini.thinking_model', 'gemini-2.5-pro')
            : (string) config('services.gemini.command_model', config('services.gemini.model', 'gemini-2.5-flash'));

        if ($this->isRestrictedPrivacyQuery($cleanPrompt)) {
            return [
                'ok' => true,
                'reply' => 'Staff salaries, contract compensation, and PIN codes are strictly confidential and are never exposed in Command Center. You can ask about staff upsells, weekly evaluations, bookings, leads, or pending ops actions instead.',
                'mode' => $mode,
                'model' => $model,
                'ai_live' => false,
                'privacy_blocked' => true,
                'tools_used' => [],
                'tool_badges' => [],
                'whatsapp_action' => null,
            ];
        }

        $telemetry = $this->scrubSensitiveStaffData($this->getLiveTelemetrySnapshot());
        $toolsUsed = [];
        $preloadedToolContext = $this->scrubSensitiveStaffData(
            $this->preloadRelevantToolsForPrompt($cleanPrompt, $toolsUsed, $telemetry)
        );

        $apiKey = trim((string) (config('services.gemini.api_key') ?: config('services.gemini.key')));
        $replyText = '';
        $aiLive = false;

        if ($apiKey !== '') {
            try {
                $replyText = $this->runGeminiConversationLoop(
                    apiKey: $apiKey,
                    model: $model,
                    mode: $mode,
                    prompt: $cleanPrompt,
                    history: $history,
                    telemetry: $telemetry,
                    preloadedToolContext: $preloadedToolContext,
                    toolsUsed: $toolsUsed
                );
                if (trim($replyText) !== '') {
                    $aiLive = true;
                }
            } catch (\Throwable $e) {
                Log::warning('Command Center Gemini request failed; using live CRM summary fallback.', [
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (trim($replyText) === '') {
            $replyText = $this->buildStructuredFallbackReply($cleanPrompt, $telemetry, $preloadedToolContext, $toolsUsed);
        }

        $whatsappAction = $preloadedToolContext['whatsapp_pitch'] ?? null;
        if (
            $whatsappAction === null
            && (
                in_array('draft_whatsapp_closer_pitch', $toolsUsed, true)
                || preg_match('/(?:whatsapp|closer\s+pitch|closer\s+script|draft.*(?:message|pitch|text)|write.*(?:whatsapp|pitch|message))/i', $cleanPrompt)
            )
        ) {
            try {
                $targetClient = $this->extractWhatsappTargetFromPrompt($cleanPrompt);
                $whatsappAction = $this->scrubSensitiveStaffData($this->draft_whatsapp_closer_pitch($targetClient));
                $toolsUsed[] = 'draft_whatsapp_closer_pitch';
            } catch (\Throwable) {
                $whatsappAction = null;
            }
        }

        $uniqueTools = array_values(array_unique($toolsUsed));
        $toolBadges = array_map(fn (string $name) => [
            'name' => $name,
            'label' => self::TOOL_LABELS[$name] ?? $name,
        ], $uniqueTools);

        return [
            'ok' => true,
            'reply' => $replyText,
            'mode' => $mode,
            'model' => $model,
            'ai_live' => $aiLive,
            'tools_used' => $uniqueTools,
            'tool_badges' => $toolBadges,
            'whatsapp_action' => $whatsappAction,
            'telemetry' => $telemetry,
        ];
    }

    /**
     * Backward-compatible alias for ask() (text-only).
     */
    public function askFriday(
        string $prompt,
        string $mode = 'fast',
        string $voiceProfile = 'default',
        array $history = [],
        ?string $audioBase64 = null,
        ?string $audioMime = null,
        bool $skipTts = true
    ): array {
        return $this->ask($prompt, $mode, $history);
    }

    /**
     * Detect direct prompts requesting confidential salary, wage, contract compensation, or PIN data.
     */
    public function isRestrictedPrivacyQuery(string $prompt): bool
    {
        $q = strtolower(trim($prompt));
        if ($q === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(base_salary|hourly_wage|contract_pin|pin\s*code|pin\s*number|staff\s*pin|contract\s*pin|salary|salaries|wages?|payroll\s+rate|compensation)\b/i',
            $q
        );
    }

    /**
     * Recursively strip any sensitive salary, wage, contract compensation, or PIN keys from context payloads.
     */
    public function scrubSensitiveStaffData(array $payload): array
    {
        $clean = [];
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $normalizedKey = strtolower(trim($key));
                if (in_array($normalizedKey, self::SENSITIVE_PRIVACY_KEYS, true)) {
                    continue;
                }
            }

            if (is_array($value)) {
                $clean[$key] = $this->scrubSensitiveStaffData($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * Build the live read-only telemetry snapshot (cached for 30s with safe cache wrappers).
     */
    public function getLiveTelemetrySnapshot(): array
    {
        $stateFingerprint = null;

        try {
            $opsCount = Schema::hasTable('ops_action_items') ? OpsActionItem::count() : 0;
            $stateFingerprint = implode('_', [
                'v3',
                Sale::count(),
                Appointment::count(),
                Lead::count(),
                Customer::count(),
                AdLeadEntry::count(),
                Expense::count(),
                Product::count(),
                Service::count(),
                Combo::count(),
                StaffComplaint::count(),
                EcommerceSale::count(),
                $opsCount,
            ]);

            if ($this->safeCacheGet('cc_live_telemetry_fingerprint') !== $stateFingerprint) {
                $this->safeCacheForget('cc_live_telemetry_snapshot');
            }

            $cached = $this->safeCacheGet('cc_live_telemetry_snapshot');
            if (is_array($cached) && empty($cached['_is_fallback'])) {
                return $this->scrubSensitiveStaffData($cached);
            }
        } catch (\Throwable $e) {
            Log::warning('Command Center telemetry fingerprint check failed.', [
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
            $this->safeCacheForget('cc_live_telemetry_snapshot');
            $this->safeCacheForget('cc_live_telemetry_fingerprint');

            return $this->fallbackTelemetrySnapshot();
        }

        $snapshot = $this->buildLiveTelemetrySnapshot();

        if ($stateFingerprint !== null && empty($snapshot['_is_fallback'])) {
            $this->safeCachePut('cc_live_telemetry_fingerprint', $stateFingerprint, 30);
            $this->safeCachePut('cc_live_telemetry_snapshot', $snapshot, 30);
        }

        unset($snapshot['_is_fallback']);

        return $this->scrubSensitiveStaffData($snapshot);
    }

    public function buildLiveTelemetrySnapshot(): array
    {
        try {
            $revenue = $this->read_revenue_and_bookings('all', 'today');
            $leads = $this->read_leads_and_money_brief();
            $opsActions = $this->read_ops_actions();
            $retention = $this->read_retention_radar();
            $staff = $this->read_staff_upsells_and_evaluations('all');
            $marketing = $this->read_marketing_and_ads_pulse();
            $calendarGaps = $this->read_calendar_gaps_and_idle_capacity('all');
            $unrecovered = $this->read_unrecovered_cancellations(14);
            $crmOperations = $this->read_full_crm_catalog_inventory_finance_and_ecommerce('all');

            return $this->scrubSensitiveStaffData([
                'generated_at' => now()->format('d M Y, H:i'),
                'revenue' => $revenue,
                'leads' => $leads,
                'ops_actions' => $opsActions,
                'retention' => $retention,
                'staff' => $staff,
                'marketing' => $marketing,
                'calendar_gaps' => $calendarGaps,
                'unrecovered_cancellations' => $unrecovered,
                'crm_operations' => $crmOperations,
            ]);
        } catch (\Throwable $e) {
            Log::error('Command Center telemetry snapshot failed.', [
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
            $this->safeCacheForget('cc_live_telemetry_snapshot');
            $this->safeCacheForget('cc_live_telemetry_fingerprint');

            return $this->fallbackTelemetrySnapshot() + ['_is_fallback' => true];
        }
    }

    private function fallbackTelemetrySnapshot(): array
    {
        return [
            'generated_at' => now()->format('d M Y, H:i'),
            'revenue' => ['today_total_net_qar' => 0, 'mtd_total_net_qar' => 0, 'branches' => []],
            'leads' => ['overdue_count' => 0, 'due_today_count' => 0, 'top_three_potential_qar' => 0, 'top_money_leads' => [], 'top_five_leads' => []],
            'ops_actions' => ['open_count' => 0, 'overdue_count' => 0, 'urgent_count' => 0, 'top_pending_items' => []],
            'retention' => ['due_for_refill_count' => 0, 'package_renewal_count' => 0, 'bleeding_vips_count' => 0, 'total_actionable_clients' => 0],
            'staff' => ['prorated_target_qar' => 0, 'top_stylists' => [], 'open_complaints_count' => 0],
            'marketing' => ['ad_leads_total' => 0, 'ad_conversion_pct' => 0, 'attributed_ad_revenue_qar' => 0, 'reels_posted' => 0, 'reels_target' => 4, 'stories_posted' => 0, 'stories_target' => 7],
            'calendar_gaps' => ['total_booked_today' => 0, 'total_idle_capacity_qar' => 0, 'branches' => []],
            'unrecovered_cancellations' => ['unrecovered_count' => 0, 'trapped_revenue_qar' => 0, 'top_unrecovered_clients' => []],
            'crm_operations' => [
                'finance' => ['today_expenses_qar' => 0, 'mtd_expenses_qar' => 0, 'mtd_net_profit_qar' => 0, 'expenses_by_category' => []],
                'services' => ['total_services_count' => 0, 'categories_count' => 0, 'combos_count' => 0, 'top_booked_services_mtd' => []],
                'products' => ['total_products_count' => 0, 'out_of_stock_count' => 0, 'low_stock_count' => 0, 'low_stock_items' => [], 'top_selling_products' => []],
                'ecommerce' => ['total_products_count' => 0, 'total_orders_count' => 0, 'total_revenue_qar' => 0, 'total_expenses_qar' => 0, 'top_ecommerce_products' => []],
            ],
        ];
    }

    private function safeCacheGet(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }

    private function safeCachePut(string $key, mixed $value, int $seconds): void
    {
        try {
            Cache::put($key, $value, $seconds);
        } catch (\Throwable) {
            // Ignore cache store failures so Command Center never throws a 500
        }
    }

    private function safeCacheForget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable) {
            // Ignore cache store failures so Command Center never throws a 500
        }
    }

    /**
     * Execute any of the read-only tools by name.
     */
    public function executeTool(string $toolName, array $args = []): array
    {
        $raw = match ($toolName) {
            'read_revenue_and_bookings' => $this->read_revenue_and_bookings(
                (string) ($args['branch'] ?? 'all'),
                (string) ($args['period'] ?? 'today')
            ),
            'read_leads_and_money_brief' => $this->read_leads_and_money_brief(),
            'read_ops_actions' => $this->read_ops_actions(),
            'read_retention_radar' => $this->read_retention_radar(),
            'read_staff_upsells_and_evaluations' => $this->read_staff_upsells_and_evaluations(
                (string) ($args['branch'] ?? 'all')
            ),
            'read_marketing_and_ads_pulse' => $this->read_marketing_and_ads_pulse(),
            'lookup_client_or_staff' => $this->lookup_client_or_staff(
                (string) ($args['query'] ?? '')
            ),
            'read_calendar_gaps_and_idle_capacity' => $this->read_calendar_gaps_and_idle_capacity(
                (string) ($args['branch'] ?? 'all'),
                isset($args['date']) ? (string) $args['date'] : null
            ),
            'read_unrecovered_cancellations' => $this->read_unrecovered_cancellations(
                isset($args['days']) ? (int) $args['days'] : 14
            ),
            'draft_whatsapp_closer_pitch' => $this->draft_whatsapp_closer_pitch(
                (string) ($args['client_query'] ?? $args['query'] ?? 'top_lead'),
                (string) ($args['angle'] ?? 'auto')
            ),
            'read_full_crm_catalog_inventory_finance_and_ecommerce' => $this->read_full_crm_catalog_inventory_finance_and_ecommerce(
                (string) ($args['scope'] ?? 'all')
            ),
            default => ['error' => "Unknown read-only tool: {$toolName}"],
        };

        return $this->scrubSensitiveStaffData($raw);
    }

    /**
     * Alias for executeTool().
     */
    public function executeToolCall(string $toolName, array $args = []): array
    {
        return $this->executeTool($toolName, $args);
    }

    /**
     * Alias for geminiFunctionDeclarations().
     */
    public function getToolDeclarations(): array
    {
        return $this->geminiFunctionDeclarations();
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
            ->get(['id', 'assigned_agent_id', 'needful_done', 'category', 'service_interest', 'next_followup_date']);

        $mtdTotal = $mtdLeads->count();
        $mtdBooked = $mtdLeads->where('needful_done', 'booked')->count();
        $mtdConversionPct = $mtdTotal > 0 ? round(($mtdBooked / $mtdTotal) * 100, 1) : 0.0;

        $allOpenLeads = Lead::query()
            ->with('agent:id,name')
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
            })
            ->get(['id', 'assigned_agent_id', 'needful_done', 'next_followup_date']);

        $unassignedOpenCount = $allOpenLeads->whereNull('assigned_agent_id')->count();
        $assignedOpenCount = $allOpenLeads->whereNotNull('assigned_agent_id')->count();

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

        $servicePriceMap = LeadController::buildServicePriceMap();
        $openTopLeads = Lead::query()
            ->with(['customer:id,name,phone', 'agent:id,name'])
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where('phone', '!=', '-')
            ->where(function ($q) {
                $q->whereNull('needful_done')->orWhereNotIn('needful_done', Lead::CLOSED_STATUSES);
            })
            ->orderByRaw('CASE WHEN next_followup_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_followup_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(function (Lead $l) use ($servicePriceMap) {
                $services = is_array($l->service_interest) && count($l->service_interest)
                    ? implode(', ', $l->service_interest)
                    : ($l->category ? (Lead::CATEGORIES[$l->category] ?? $l->category) : 'Salon Service');
                $rawName = trim((string) ($l->customer?->name ?? ''));
                $name = ($rawName !== '' && !in_array(strtolower($rawName), ['guest', 'unnamed', 'unnamed client', '-', '—'], true))
                    ? $rawName
                    : (string) $l->phone;

                return [
                    'id' => $l->id,
                    'name' => $name,
                    'phone' => (string) $l->phone,
                    'assigned_agent' => $l->agent?->name ?: 'Unassigned',
                    'services' => $services,
                    'estimated_qar' => LeadController::estimateLeadQar($l, $servicePriceMap),
                    'notes' => trim((string) $l->customer_remarks),
                    'status' => (string) ($l->needful_done ?: 'open'),
                    'next_followup_date' => $l->next_followup_date ? $l->next_followup_date->toDateString() : null,
                ];
            })
            ->sortByDesc('estimated_qar')
            ->take(5)
            ->values()
            ->all();

        return [
            'due_today_count' => (int) ($brief['due_today_count'] ?? 0),
            'overdue_count' => (int) ($brief['overdue_count'] ?? 0),
            'unscheduled_count' => (int) ($brief['unscheduled_count'] ?? 0),
            'open_leads_count' => (int) ($brief['open_leads_count'] ?? 0),
            'unassigned_open_leads_count' => $unassignedOpenCount,
            'assigned_open_leads_count' => $assignedOpenCount,
            'top_three_potential_qar' => (float) ($brief['top_three_potential_qar'] ?? 0),
            'top_money_leads' => $brief['top_money_leads'] ?? [],
            'top_five_leads' => $openTopLeads,
            'brief_text' => (string) ($brief['brief_text'] ?? ''),
            'mtd_total_leads' => $mtdTotal,
            'mtd_booked_leads' => $mtdBooked,
            'mtd_conversion_pct' => $mtdConversionPct,
            'conversion_target_pct' => 30.0,
            'conversion_verdict' => $mtdConversionPct >= 30.0 ? 'PASS' : 'NEEDS ATTENTION',
            'agent_leaderboard' => array_slice($agentLeaderboard, 0, 5),
            'agent_execution_summary' => [
                'total_open_leads' => $allOpenLeads->count(),
                'unassigned_open_leads_count' => $unassignedOpenCount,
                'assigned_open_leads_count' => $assignedOpenCount,
                'overdue_followups_count' => (int) ($brief['overdue_count'] ?? 0),
                'due_today_count' => (int) ($brief['due_today_count'] ?? 0),
                'mtd_total_leads' => $mtdTotal,
                'mtd_booked_leads' => $mtdBooked,
                'mtd_conversion_pct' => $mtdConversionPct,
                'agents_breakdown' => array_slice($agentLeaderboard, 0, 5),
            ],
        ];
    }

    /**
     * TOOL 2B: Read-Only Pending Ops Actions (Ops Tracker).
     */
    public function read_ops_actions(): array
    {
        try {
            if (!Schema::hasTable('ops_action_items')) {
                return [
                    'open_count' => 0,
                    'overdue_count' => 0,
                    'urgent_count' => 0,
                    'top_pending_items' => [],
                ];
            }

            $openItems = OpsActionItem::query()
                ->where('is_completed', false)
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 ELSE 2 END ASC")
                ->orderBy('id')
                ->get([
                    'id',
                    'title',
                    'priority',
                    'pending_with_name',
                    'assigned_names',
                    'ball_assigned_at',
                    'sla_hours',
                    'due_at',
                    'created_at',
                ]);

            $overdueCount = $openItems->filter(fn (OpsActionItem $item) => $item->is_overdue)->count();
            $urgentCount = $openItems->where('priority', 'urgent')->count();

            $topPending = $openItems->take(6)->map(function (OpsActionItem $item) {
                $assignees = is_array($item->assigned_names) && !empty($item->assigned_names)
                    ? implode(', ', $item->assigned_names)
                    : ($item->pending_with_name ?: 'Unassigned');

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'priority' => $item->priority ?: 'normal',
                    'assigned_to' => $assignees,
                    'is_overdue' => $item->is_overdue,
                    'hours_remaining' => $item->hours_remaining,
                    'due_at' => $item->effectiveDueAt()->format('d M Y, h:i A'),
                ];
            })->values()->all();

            return [
                'open_count' => $openItems->count(),
                'overdue_count' => $overdueCount,
                'urgent_count' => $urgentCount,
                'top_pending_items' => $topPending,
            ];
        } catch (\Throwable $e) {
            Log::warning('Command Center read_ops_actions fallback.', ['error' => $e->getMessage()]);

            return [
                'open_count' => 0,
                'overdue_count' => 0,
                'urgent_count' => 0,
                'top_pending_items' => [],
            ];
        }
    }

    /**
     * TOOL 3: Read-Only Retention Radar (Due for Refill, Packages Ending, Missing VIPs).
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
     * TOOL 4: Read-Only Staff Upsells & Weekly Evaluations (strictly zero salary/compensation/PIN fields).
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
            ->whereNull('resolved_at')
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
        $periodStart = $now->copy()->startOfMonth()->startOfDay();
        $periodEnd = $now->copy()->endOfDay();

        $adCols = ['id', 'branch', 'ticket_amount', 'category'];
        if (Schema::hasColumn('ad_lead_entries', 'old_airport_amount')) {
            $adCols[] = 'old_airport_amount';
        }
        if (Schema::hasColumn('ad_lead_entries', 'wakrah_amount')) {
            $adCols[] = 'wakrah_amount';
        }

        $adLeads = AdLeadEntry::query()
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->get($adCols);

        $totalAdLeads = $adLeads->count();
        $bookedAdLeadsCollection = $adLeads->filter(fn (AdLeadEntry $e) => $e->isBooked());
        $bookedAdLeads = $bookedAdLeadsCollection->count();
        $adConversionPct = $totalAdLeads > 0 ? round(($bookedAdLeads / $totalAdLeads) * 100, 1) : 0.0;
        $oaAdRevenueQar = round((float) $bookedAdLeadsCollection->sum(fn (AdLeadEntry $e) => $e->branchRevenue('old_airport')), 2);
        $wkAdRevenueQar = round((float) $bookedAdLeadsCollection->sum(fn (AdLeadEntry $e) => $e->branchRevenue('wakrah')), 2);
        $attributedRevenueQar = round((float) $bookedAdLeadsCollection->sum('ticket_amount'), 2);

        $adsByPhase = KpiMonthlyAd::query()
            ->where('plan_month', $now->format('Y-m'))
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
            ->get(['week_start_date', 'day_of_week', 'is_reel_posted', 'is_reel_skipped', 'are_stories_posted']);

        $reelsPosted = $weekPlans->filter(fn (SocialContentPlan $p) => $p->is_reel_posted && !$p->is_reel_skipped)->count();
        $storiesPosted = $weekPlans->where('are_stories_posted', true)->count();
        $reelTarget = SocialContentPlan::WEEKLY_REEL_TARGET;
        $storiesTarget = SocialContentPlan::WEEKLY_STORIES_TARGET;

        $daysElapsed = max(1, (int) $now->day);
        $completedDays = max(0, $daysElapsed - 1);
        $mtdStoriesTarget = $completedDays;
        $mtdReelsTarget = (int) floor(($completedDays * $reelTarget) / 7);
        $cumulativeMtdTarget = $mtdReelsTarget + $mtdStoriesTarget;

        $minDateStr = $periodStart->toDateString();
        $maxDateStr = $periodEnd->toDateString();
        $mtdPlans = SocialContentPlan::query()
            ->where('week_start_date', '>=', $periodStart->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->where('week_start_date', '<=', $periodEnd->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->get(['week_start_date', 'day_of_week', 'is_reel_posted', 'is_reel_skipped', 'are_stories_posted', 'reel_content', 'stories_content', 'reel_link'])
            ->filter(function (SocialContentPlan $p) use ($minDateStr, $maxDateStr): bool {
                $exactStr = $p->exactDate()->toDateString();

                return $exactStr >= $minDateStr && $exactStr <= $maxDateStr;
            })
            ->values();

        $mtdPublishedReels = $mtdPlans->filter(fn (SocialContentPlan $p) => $p->is_reel_posted && !$p->is_reel_skipped)->count();
        $mtdPublishedStories = $mtdPlans->where('are_stories_posted', true)->count();
        $mtdItemsCompleted = $mtdPublishedReels + $mtdPublishedStories;
        $hasMtdActivity = $mtdItemsCompleted > 0 || $mtdPlans->contains(
            fn (SocialContentPlan $p) => filled($p->reel_content) || filled($p->stories_content) || filled($p->reel_link)
        );

        if ($completedDays === 0) {
            $contentCompliancePct = $hasMtdActivity ? 100.0 : 0.0;
        } else {
            $credited = min($mtdPublishedReels, $mtdReelsTarget) + min($mtdPublishedStories, $mtdStoriesTarget);
            $contentCompliancePct = !$hasMtdActivity
                ? 0.0
                : ($cumulativeMtdTarget > 0 ? round(min(100.0, ($credited / $cumulativeMtdTarget) * 100), 1) : 100.0);
        }

        return [
            'current_month' => $now->format('F Y'),
            'ad_leads_total' => $totalAdLeads,
            'ad_leads_booked' => $bookedAdLeads,
            'ad_conversion_pct' => $adConversionPct,
            'ad_conversion_target_pct' => 20.0,
            'ad_conversion_verdict' => $adConversionPct >= 20.0 ? 'PASS (>=20%)' : 'BELOW FLOOR (<20%)',
            'attributed_ad_revenue_qar' => $attributedRevenueQar,
            'old_airport_ad_revenue_qar' => $oaAdRevenueQar,
            'wakrah_ad_revenue_qar' => $wkAdRevenueQar,
            'monthly_ads_by_phase' => $adsByPhase,
            'week_start_date' => $weekStart,
            'reels_posted' => $reelsPosted,
            'reels_target' => $reelTarget,
            'stories_posted' => $storiesPosted,
            'stories_target' => $storiesTarget,
            'mtd_items_completed' => $mtdItemsCompleted,
            'cumulative_mtd_target' => $cumulativeMtdTarget,
            'content_compliance_pct' => $contentCompliancePct,
        ];
    }

    /**
     * TOOL 6: Read-Only Client or Staff Directory Lookup.
     * Strictly selects non-confidential columns only (never base_salary, hourly_wage, contract_pin, or staff_contracts).
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
            ->where(function ($q) use ($clean) {
                $q->where('name', 'like', "%{$clean}%")
                    ->orWhere('phone', 'like', "%{$clean}%");
            })
            ->limit(5)
            ->get(['id', 'name', 'phone', 'loyalty_points']);

        $customerSalesByCustomer = $customers->isEmpty()
            ? collect()
            : Sale::query()
                ->whereIn('customer_id', $customers->pluck('id'))
                ->get(['customer_id', 'services_total', 'products_total', 'packages_total', 'discount_amount', 'total_amount', 'tip_amount'])
                ->groupBy('customer_id');

        $clientResults = $customers->map(function (Customer $c) use ($customerSalesByCustomer) {
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

            $ltvQar = round((float) $customerSalesByCustomer->get($c->id, collect())
                ->sum(fn (Sale $sale) => AdLeadEntry::netSaleAmount($sale)), 2);

            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'completed_visits' => $completedVisits,
                'ltv_qar' => $ltvQar,
                'loyalty_points' => (int) $c->loyalty_points,
                'last_visit' => $lastVisit ? Carbon::parse($lastVisit)->format('d M Y') : null,
                'active_packages' => $activePackages,
            ];
        })->values()->all();

        $hasStaffRoleColumn = Schema::hasColumn('staff', 'role');
        $staffColumns = ['id', 'name', 'branch', 'employment_type'];
        if ($hasStaffRoleColumn) {
            $staffColumns[] = 'role';
        }

        $staffMatches = Staff::query()
            ->where(function ($q) use ($clean, $hasStaffRoleColumn) {
                $q->where('name', 'like', "%{$clean}%")
                    ->orWhere('branch', 'like', "%{$clean}%")
                    ->orWhere('employment_type', 'like', "%{$clean}%");
                if ($hasStaffRoleColumn) {
                    $q->orWhere('role', 'like', "%{$clean}%");
                }
            })
            ->limit(5)
            ->get($staffColumns);

        $analytics = new StaffSalesAnalytics(now()->startOfMonth(), now());
        $oaMap = $analytics->computedStaff('old_airport')->keyBy('staff_id');
        $wkMap = $analytics->computedStaff('wakrah')->keyBy('staff_id');

        $staffResults = $staffMatches->map(function (Staff $s) use ($oaMap, $wkMap, $hasStaffRoleColumn) {
            $row = $oaMap->get($s->id) ?? $wkMap->get($s->id);
            $roleValue = $hasStaffRoleColumn ? ($s->getAttribute('role') ?: $s->employment_type) : ($s->employment_type ?: 'Salon Team');

            return [
                'id' => $s->id,
                'name' => $s->name,
                'role' => $roleValue,
                'branch' => $s->branch,
                'mtd_upsell_qar' => (float) ($row['upsell'] ?? 0.0),
                'prorated_target_qar' => (float) ($row['prorated_target'] ?? 0.0),
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
     * TOOL 7: Read-Only Chair-Gap & Idle Stylist Capacity Detector across Old Airport and Wakrah (10:00–21:00).
     */
    public function read_calendar_gaps_and_idle_capacity(string $branch = 'all', ?string $date = null): array
    {
        $targetDate = ($date && trim($date) !== '')
            ? Carbon::parse($date)->toDateString()
            : now()->toDateString();

        $branchKey = strtolower(trim($branch));
        $branches = in_array($branchKey, ['old_airport', 'wakrah'], true)
            ? [$branchKey]
            : ['old_airport', 'wakrah'];

        $timeWindows = [
            '10:00–14:00' => [10, 14],
            '14:00–17:00' => [14, 17],
            '17:00–20:00' => [17, 20],
            '20:00–21:00' => [20, 21],
        ];

        $branchReport = [];
        $totalBookedToday = 0;
        $totalIdleCapacityQar = 0.0;

        foreach ($branches as $bKey) {
            $activeStylistCount = max(1, Staff::query()
                ->where('branch', $bKey)
                ->where('bookable', true)
                ->count());

            $appts = Appointment::query()
                ->where('branch', $bKey)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($targetDate) {
                    $q->whereDate('booking_date', $targetDate)
                        ->orWhereDate('appointment_datetime', $targetDate);
                })
                ->get(['id', 'appointment_datetime', 'booking_date', 'staff_id', 'service_name', 'price']);

            $bookedCount = $appts->count();
            $totalBookedToday += $bookedCount;

            $totalChairSlotCapacity = $activeStylistCount * 6;
            $utilizationPct = round(min(100.0, ($bookedCount / max(1, $totalChairSlotCapacity)) * 100), 1);

            $openGapWindows = [];
            foreach ($timeWindows as $label => [$startHour, $endHour]) {
                $bookingsInWindow = $appts->filter(function (Appointment $a) use ($startHour, $endHour) {
                    if (!$a->appointment_datetime) {
                        return false;
                    }
                    $h = (int) Carbon::parse($a->appointment_datetime)->format('G');
                    return $h >= $startHour && $h < $endHour;
                })->count();

                $windowCapacity = $activeStylistCount * max(1, $endHour - $startHour - 1);
                if ($bookingsInWindow < $windowCapacity) {
                    $openGapWindows[] = $label;
                }
            }

            $idleSlots = max(0, $totalChairSlotCapacity - $bookedCount);
            $idleCapacityQar = round($idleSlots * 250.0, 2);
            $totalIdleCapacityQar += $idleCapacityQar;

            $branchReport[$bKey] = [
                'label' => $bKey === 'old_airport' ? 'Old Airport' : 'Wakrah',
                'booked_appointments_count' => $bookedCount,
                'active_stylists_count' => $activeStylistCount,
                'chair_utilization_pct' => $utilizationPct,
                'open_gap_windows' => array_slice($openGapWindows, 0, 3),
                'idle_slots_estimate' => $idleSlots,
                'idle_capacity_qar' => $idleCapacityQar,
            ];
        }

        $leadsBrief = $this->read_leads_and_money_brief();
        $topCallableLeads = array_slice($leadsBrief['top_money_leads'] ?? [], 0, 3);

        return [
            'date' => $targetDate,
            'operating_hours' => '10:00–21:00',
            'branches' => $branchReport,
            'total_booked_today' => $totalBookedToday,
            'total_idle_capacity_qar' => round($totalIdleCapacityQar, 2),
            'top_callable_leads_to_fill_gaps' => $topCallableLeads,
        ];
    }

    /**
     * TOOL 8: Read-Only Unrecovered Cancellation & No-Show Radar.
     */
    public function read_unrecovered_cancellations(int $days = 14): array
    {
        $lookbackDays = max(1, min(90, $days));
        $sinceDate = now()->subDays($lookbackDays)->startOfDay();

        $cancelledAppts = Appointment::query()
            ->whereIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($sinceDate) {
                $q->where('appointment_datetime', '>=', $sinceDate)
                    ->orWhere('booking_date', '>=', $sinceDate->toDateString())
                    ->orWhere('created_at', '>=', $sinceDate);
            })
            ->orderByDesc('appointment_datetime')
            ->get([
                'id',
                'customer_id',
                'customer_name',
                'phone',
                'branch',
                'service_name',
                'appointment_datetime',
                'booking_date',
                'status',
                'price',
            ]);

        $customerIds = $cancelledAppts->pluck('customer_id')->filter()->unique()->values();
        $customerMap = $customerIds->isNotEmpty()
            ? Customer::whereIn('id', $customerIds)->get(['id', 'name', 'phone', 'loyalty_points'])->keyBy('id')
            : collect();

        $unrecoveredList = [];
        $seenKeys = [];
        $trappedRevenueQar = 0.0;

        foreach ($cancelledAppts as $appt) {
            $cid = $appt->customer_id ? (int) $appt->customer_id : null;
            $phone = trim((string) ($appt->phone ?: ($cid ? ($customerMap->get($cid)?->phone ?? '') : '')));
            $dedupeKey = $cid ? "cid:{$cid}" : "phone:{$phone}";
            if ($dedupeKey === 'phone:' || isset($seenKeys[$dedupeKey])) {
                continue;
            }

            $cancelledAt = $appt->appointment_datetime
                ? Carbon::parse($appt->appointment_datetime)
                : ($appt->booking_date ? Carbon::parse($appt->booking_date)->startOfDay() : now()->subDays(1));

            $hasRebooked = Appointment::query()
                ->where('id', '!=', $appt->id)
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($cid, $phone) {
                    if ($cid) {
                        $q->where('customer_id', $cid);
                        if ($phone !== '') {
                            $q->orWhere('phone', $phone);
                        }
                    } else {
                        $q->where('phone', $phone);
                    }
                })
                ->where(function ($q) use ($cancelledAt) {
                    $q->where('appointment_datetime', '>=', $cancelledAt)
                        ->orWhere('booking_date', '>=', $cancelledAt->toDateString());
                })
                ->exists();

            if ($hasRebooked) {
                continue;
            }

            $seenKeys[$dedupeKey] = true;
            $cust = $cid ? $customerMap->get($cid) : null;
            if (!$cust && $phone !== '') {
                $cust = Customer::where('phone', 'like', '%' . substr(preg_replace('/\D+/', '', $phone), -8) . '%')->first(['id', 'name', 'phone', 'loyalty_points']);
            }

            $valQar = (float) $appt->price > 0 ? round((float) $appt->price, 2) : 250.0;
            $trappedRevenueQar += $valQar;

            $unrecoveredList[] = [
                'appointment_id' => $appt->id,
                'customer_id' => $cid ?? $cust?->id,
                'client_name' => $appt->customer_name ?: ($cust?->name ?? 'Salon Client'),
                'phone' => $phone,
                'branch' => $appt->branch ?: 'old_airport',
                'service_name' => $appt->service_name ?: 'Salon Service',
                'cancelled_date' => $cancelledAt->format('d M Y'),
                'status' => $appt->status,
                'value_qar' => $valQar,
                'loyalty_points' => (int) ($cust?->loyalty_points ?? 0),
            ];
        }

        return [
            'lookback_days' => $lookbackDays,
            'unrecovered_count' => count($unrecoveredList),
            'trapped_revenue_qar' => round($trappedRevenueQar, 2),
            'top_unrecovered_clients' => array_slice($unrecoveredList, 0, 5),
        ];
    }

    /**
     * TOOL 9: Read-Only WhatsApp Closer Pitch & 1-Click Deep Link Generator.
     */
    public function draft_whatsapp_closer_pitch(string $clientQuery = 'top_lead', string $angle = 'auto'): array
    {
        $cleanQuery = trim($clientQuery);
        $isGenericTopQuery = $cleanQuery === '' || in_array(strtolower($cleanQuery), [
            'top_lead', 'top lead', 'auto', 'top', 'first lead', 'number one', 'client', 'lead',
        ], true);

        $name = 'Valued Client';
        $phone = '';
        $serviceInterest = 'signature beauty treatment';
        $valueQar = 250.0;
        $points = 0;
        $resolvedAngle = $angle !== 'auto' && $angle !== '' ? $angle : 'priority_slot';
        $topUnrec = null;

        $matchedCustomer = null;
        $matchedLead = null;
        $matchedAppt = null;

        if (!$isGenericTopQuery) {
            $matchedCustomer = Customer::query()
                ->where('name', 'like', "%{$cleanQuery}%")
                ->orWhere('phone', 'like', "%{$cleanQuery}%")
                ->first(['id', 'name', 'phone', 'loyalty_points']);

            $matchedLead = Lead::with('customer:id,name,phone,loyalty_points')
                ->where('phone', 'like', "%{$cleanQuery}%")
                ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$cleanQuery}%")->orWhere('phone', 'like', "%{$cleanQuery}%"))
                ->latest('id')
                ->first();

            $matchedAppt = Appointment::query()
                ->where('customer_name', 'like', "%{$cleanQuery}%")
                ->orWhere('phone', 'like', "%{$cleanQuery}%")
                ->latest('appointment_datetime')
                ->first();
        }

        if ($matchedLead || $matchedCustomer || $matchedAppt) {
            $cust = $matchedCustomer ?: $matchedLead?->customer;
            $name = $cust?->name ?: ($matchedAppt?->customer_name ?: 'Salon Guest');
            $phone = (string) ($matchedLead?->phone ?: ($cust?->phone ?: ($matchedAppt?->phone ?? '')));
            $points = (int) ($cust?->loyalty_points ?? 0);

            if ($matchedLead) {
                $services = is_array($matchedLead->service_interest) ? array_filter($matchedLead->service_interest) : [];
                if (!empty($services)) {
                    $serviceInterest = implode(' and ', $services);
                }
                $valueQar = max(180.0, count($services) * 180.0);
                if (in_array('complaint', (array) ($matchedLead->categories ?? []), true) || $matchedLead->category === 'complaint') {
                    $resolvedAngle = 'vip_recovery';
                }
            } elseif ($matchedAppt) {
                $serviceInterest = $matchedAppt->service_name ?: $serviceInterest;
                $valueQar = (float) $matchedAppt->price > 0 ? (float) $matchedAppt->price : 250.0;
                if (in_array($matchedAppt->status, ['cancelled', 'no_show'], true)) {
                    $resolvedAngle = 'cancellation_winback';
                }
            }
        } else {
            $leadsBrief = $this->read_leads_and_money_brief();
            $topLead = $leadsBrief['top_money_leads'][0] ?? null;

            if ($topLead) {
                $phone = (string) ($topLead['phone'] ?? '');
                $cust = $phone !== ''
                    ? Customer::where('phone', 'like', '%' . substr(preg_replace('/\D+/', '', $phone), -8) . '%')->first(['id', 'name', 'loyalty_points'])
                    : null;
                $name = (string) ($cust?->name ?: ($topLead['name'] ?? $topLead['customer_name'] ?? $topLead['client_name'] ?? 'Salon Guest'));
                $serviceRaw = $topLead['services'] ?? $topLead['service_interest'] ?? '';
                if (is_array($serviceRaw)) {
                    $serviceRaw = implode(' and ', $serviceRaw);
                }
                $serviceInterest = (string) (trim((string) $serviceRaw) !== '' ? $serviceRaw : 'facial and manicure');
                $valueQar = (float) ($topLead['estimated_qar'] ?? $topLead['estimated_value_qar'] ?? 300.0);
                $points = (int) ($cust?->loyalty_points ?? 0);
            } else {
                $unrecovered = $this->read_unrecovered_cancellations(14);
                $topUnrec = $unrecovered['top_unrecovered_clients'][0] ?? null;
                if ($topUnrec) {
                    $name = (string) $topUnrec['client_name'];
                    $phone = (string) $topUnrec['phone'];
                    $serviceInterest = (string) $topUnrec['service_name'];
                    $valueQar = (float) $topUnrec['value_qar'];
                    $points = (int) $topUnrec['loyalty_points'];
                    $resolvedAngle = 'cancellation_winback';
                } elseif ($firstCust = Customer::orderByDesc('loyalty_points')->first(['id', 'name', 'phone', 'loyalty_points'])) {
                    $name = (string) $firstCust->name;
                    $phone = (string) $firstCust->phone;
                    $points = (int) $firstCust->loyalty_points;
                }
            }
        }

        $firstName = explode(' ', trim($name))[0] ?: 'lovely';
        $branchRaw = strtolower((string) ($matchedAppt?->branch ?? ($topUnrec['branch'] ?? 'old_airport')));
        $branchLabel = $branchRaw === 'wakrah' ? 'our Wakrah branch' : 'our Old Airport branch';
        $serviceName = trim(preg_replace('/\s+/', ' ', str_replace(',', ' and', strtolower($serviceInterest)))) ?: 'signature beauty treatment';

        if ($points > 0) {
            $message = "Hey {$firstName}! 💕 It's the team at Laleen Beauty. We noticed you still have {$points} loyalty points sitting in your account from your last visit, and we'd love to treat you and put them toward your {$serviceName}! We have a lovely spot open at {$branchLabel} today or tomorrow—which time works best for you?";
        } elseif ($resolvedAngle === 'cancellation_winback') {
            $message = "Hey {$firstName}! 💕 It's the team at Laleen Beauty. We missed you for your {$serviceName} and just had a lovely priority spot open up with our senior specialist at {$branchLabel} between today and tomorrow—which time works best for you?";
        } else {
            $message = "Hey {$firstName}! 💕 It's the team at Laleen Beauty. We'd love to pamper you for your {$serviceName} and just had a lovely priority spot open up with our senior specialist at {$branchLabel} between today and tomorrow—which time works best for you?";
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 8) {
            $digits = '974' . $digits;
        }
        if ($digits === '') {
            $digits = '974';
        }

        $waUrl = 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);

        return [
            'client_name' => $name,
            'phone' => $phone,
            'service_name' => $serviceInterest,
            'value_qar' => round((float) $valueQar, 2),
            'loyalty_points' => $points,
            'angle' => $resolvedAngle,
            'whatsapp_message' => $message,
            'whatsapp_url' => $waUrl,
        ];
    }

    private function extractWhatsappTargetFromPrompt(string $prompt): string
    {
        if (preg_match('/(?:\+?974\s*)?(\d{8})/', $prompt, $m)) {
            return $m[1];
        }
        if (preg_match('/(?:for|to|client|customer|lead)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/', $prompt, $m)) {
            $candidate = trim($m[1]);
            if (!in_array(strtolower($candidate), ['top lead', 'our top', 'the top', 'whatsapp', 'boss', 'old airport', 'wakrah'], true)) {
                return $candidate;
            }
        }
        return 'top_lead';
    }

    /**
     * TOOL 10: Read-Only Full CRM Catalog, Inventory, Finance Expenses/Net Profit, Ecommerce & Legal Operations.
     * Payroll / salary expense categories are grouped under 'General Overhead' so individual or payroll salary
     * lines are never itemized in Command Center context.
     */
    public function read_full_crm_catalog_inventory_finance_and_ecommerce(string $scope = 'all'): array
    {
        $scopeKey = strtolower(trim($scope));
        $includeAll = !in_array($scopeKey, ['finance', 'services', 'products', 'ecommerce', 'legal'], true);

        $todayStr = now()->toDateString();
        $monthStart = now()->startOfMonth()->startOfDay();
        $monthEnd = now()->endOfMonth()->endOfDay();

        $result = [
            'scope' => $includeAll ? 'all' : $scopeKey,
        ];

        // 1. FINANCE, EXPENSES, REVENUE BREAKDOWN & TRUE NET PROFIT
        if ($includeAll || $scopeKey === 'finance') {
            $mtdSales = Sale::query()
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->get(['branch', 'services_total', 'products_total', 'packages_total', 'discount_amount', 'tip_amount', 'total_amount']);

            $mtdGrossSalesQar = round((float) $mtdSales->sum('total_amount'), 2);
            $mtdTipsQar = round((float) $mtdSales->sum('tip_amount'), 2);
            $mtdNetSalesQar = round($mtdGrossSalesQar - $mtdTipsQar, 2);
            $mtdServicesRevQar = round((float) $mtdSales->sum('services_total'), 2);
            $mtdProductsRevQar = round((float) $mtdSales->sum('products_total'), 2);
            $mtdPackagesRevQar = round((float) $mtdSales->sum('packages_total'), 2);
            $mtdDiscountsQar = round((float) $mtdSales->sum('discount_amount'), 2);

            $todayExpensesQar = round((float) Expense::query()
                ->whereDate('expense_date', $todayStr)
                ->sum('amount'), 2);

            $mtdExpensesRows = Expense::query()
                ->whereBetween('expense_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->get(['branch', 'category', 'amount', 'expense_date']);

            $mtdExpensesQar = round((float) $mtdExpensesRows->sum('amount'), 2);

            $expensesByBranch = [
                'old_airport' => round((float) $mtdExpensesRows->where('branch', 'old_airport')->sum('amount'), 2),
                'wakrah' => round((float) $mtdExpensesRows->where('branch', 'wakrah')->sum('amount'), 2),
            ];

            $expensesByCategory = [];
            foreach ($mtdExpensesRows as $expenseRow) {
                $rawCat = trim((string) ($expenseRow->category ?: 'Other'));
                if (preg_match('/salary|salaries|wage|payroll|compensation/i', $rawCat)) {
                    $safeCat = 'General Overhead';
                } else {
                    $safeCat = $rawCat;
                }
                $expensesByCategory[$safeCat] = round(($expensesByCategory[$safeCat] ?? 0.0) + (float) $expenseRow->amount, 2);
            }
            arsort($expensesByCategory);

            $oaMtdNetSales = round((float) $mtdSales->where('branch', 'old_airport')->sum(fn (Sale $s) => (float) $s->total_amount - (float) $s->tip_amount), 2);
            $wkMtdNetSales = round((float) $mtdSales->where('branch', 'wakrah')->sum(fn (Sale $s) => (float) $s->total_amount - (float) $s->tip_amount), 2);

            $result['finance'] = [
                'today_expenses_qar' => $todayExpensesQar,
                'mtd_gross_sales_qar' => $mtdGrossSalesQar,
                'mtd_net_sales_qar' => $mtdNetSalesQar,
                'mtd_tips_collected_qar' => $mtdTipsQar,
                'mtd_services_revenue_qar' => $mtdServicesRevQar,
                'mtd_products_revenue_qar' => $mtdProductsRevQar,
                'mtd_packages_revenue_qar' => $mtdPackagesRevQar,
                'mtd_discounts_given_qar' => $mtdDiscountsQar,
                'mtd_expenses_qar' => $mtdExpensesQar,
                'mtd_net_profit_qar' => round($mtdNetSalesQar - $mtdExpensesQar, 2),
                'branch_profitability_mtd' => [
                    'old_airport' => [
                        'net_sales_qar' => $oaMtdNetSales,
                        'expenses_qar' => $expensesByBranch['old_airport'],
                        'net_profit_qar' => round($oaMtdNetSales - $expensesByBranch['old_airport'], 2),
                    ],
                    'wakrah' => [
                        'net_sales_qar' => $wkMtdNetSales,
                        'expenses_qar' => $expensesByBranch['wakrah'],
                        'net_profit_qar' => round($wkMtdNetSales - $expensesByBranch['wakrah'], 2),
                    ],
                ],
                'expenses_by_category' => $expensesByCategory,
            ];
        }

        // 2. SERVICES CATALOG, CATEGORIES & COMBOS
        if ($includeAll || $scopeKey === 'services') {
            $allServices = Service::with('category:id,name')->get(['id', 'name', 'category_id', 'price', 'duration']);
            $categoriesCount = ServiceCategory::count();
            $combos = Combo::query()->get(['id', 'name', 'price', 'quantity_included', 'validity_days', 'status']);

            $mtdApptServices = Appointment::query()
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->where(function ($q) use ($monthStart, $monthEnd) {
                    $q->whereBetween('appointment_datetime', [$monthStart, $monthEnd])
                        ->orWhereBetween('booking_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                })
                ->get(['service_name', 'price']);

            $serviceBookingCounts = [];
            foreach ($mtdApptServices as $appt) {
                $sName = trim((string) $appt->service_name);
                if ($sName !== '') {
                    $serviceBookingCounts[$sName] = ($serviceBookingCounts[$sName] ?? 0) + 1;
                }
            }
            arsort($serviceBookingCounts);

            $topBookedServices = [];
            foreach (array_slice($serviceBookingCounts, 0, 5, true) as $sName => $cnt) {
                $topBookedServices[] = [
                    'service_name' => $sName,
                    'bookings_mtd' => $cnt,
                ];
            }

            $result['services'] = [
                'total_services_count' => $allServices->count(),
                'categories_count' => $categoriesCount,
                'combos_count' => $combos->count(),
                'avg_service_price_qar' => $allServices->isNotEmpty() ? round((float) $allServices->avg('price'), 2) : 0.0,
                'highest_priced_services' => $allServices->sortByDesc('price')->take(5)->map(fn (Service $s) => [
                    'name' => $s->name,
                    'category' => $s->category?->name ?? 'General',
                    'price_qar' => round((float) $s->price, 2),
                    'duration_mins' => (int) $s->duration,
                ])->values()->all(),
                'top_booked_services_mtd' => $topBookedServices,
                'active_combos' => $combos->where('status', 'active')->take(5)->map(fn (Combo $c) => [
                    'name' => $c->name,
                    'price_qar' => round((float) $c->price, 2),
                    'sessions' => (int) ($c->quantity_included ?? 0),
                    'validity_days' => $c->validity_days !== null ? (int) $c->validity_days : null,
                ])->values()->all(),
            ];
        }

        // 3. RETAIL PRODUCTS & INVENTORY STOCK ALERTS
        if ($includeAll || $scopeKey === 'products') {
            $products = Product::query()->get(['id', 'name', 'sku', 'price', 'stock_quantity', 'low_stock_threshold']);
            $outOfStock = $products->filter(fn (Product $p) => (int) $p->stock_quantity <= 0);
            $lowStock = $products->filter(fn (Product $p) => (int) $p->stock_quantity > 0 && (int) $p->stock_quantity <= (int) ($p->low_stock_threshold ?? 3));

            $inventoryRetailValueQar = round((float) $products->sum(fn (Product $p) => max(0, (int) $p->stock_quantity) * (float) $p->price), 2);

            $productSalesRows = SaleItem::query()
                ->whereNotNull('product_id')
                ->orWhere('type', 'product')
                ->get(['name', 'quantity', 'total']);

            $topSellingProducts = $productSalesRows
                ->groupBy(fn (SaleItem $item) => $item->name ?: 'Retail Product')
                ->map(fn ($group, $name) => [
                    'name' => $name,
                    'units_sold' => (int) $group->sum('quantity'),
                    'revenue_qar' => round((float) $group->sum('total'), 2),
                ])
                ->sortByDesc('revenue_qar')
                ->take(5)
                ->values()
                ->all();

            $result['products'] = [
                'total_products_count' => $products->count(),
                'out_of_stock_count' => $outOfStock->count(),
                'low_stock_count' => $lowStock->count(),
                'inventory_retail_value_qar' => $inventoryRetailValueQar,
                'out_of_stock_items' => $outOfStock->take(6)->map(fn (Product $p) => [
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'price_qar' => round((float) $p->price, 2),
                    'stock_quantity' => (int) $p->stock_quantity,
                ])->values()->all(),
                'low_stock_items' => $lowStock->take(6)->map(fn (Product $p) => [
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'price_qar' => round((float) $p->price, 2),
                    'stock_quantity' => (int) $p->stock_quantity,
                    'threshold' => (int) ($p->low_stock_threshold ?? 3),
                ])->values()->all(),
                'top_selling_products' => $topSellingProducts,
            ];
        }

        // 4. ECOMMERCE PRODUCTS, ORDERS, STOCK & EXPENSES (STRICTLY PARTITIONED BY REGION: PK [PKR] vs QA [QAR])
        if ($includeAll || $scopeKey === 'ecommerce') {
            $ecomProducts = EcommerceProduct::with('pricingModel')->get([
                'id', 'name', 'sku', 'stock_qatar', 'stock_pakistan', 'sold_qatar', 'sold_pakistan',
                'selling_price', 'liquid_cost_per_ml', 'volume_ml', 'bottle_cost', 'pump_cost',
                'label_cost', 'box_cost', 'labor_cost', 'shipping_cost', 'payment_gateway_fee_percent',
            ]);

            $ecomSales = EcommerceSale::with('product:id,name')
                ->where(function ($q) {
                    $q->whereNull('channel')
                        ->orWhereNotIn('channel', EcommerceSale::NON_REVENUE_CHANNELS);
                })
                ->where(function ($q) {
                    $q->whereNull('order_status')
                        ->orWhereNotIn('order_status', EcommerceSale::INACTIVE_ORDER_STATUSES);
                })
                ->where(function ($q) {
                    $q->whereNull('payment_status')
                        ->orWhere('payment_status', 'paid')
                        ->orWhere('order_status', 'delivered');
                })
                ->get([
                    'id', 'ecommerce_product_id', 'order_group_id', 'reference_id', 'ecommerce_bundle_id',
                    'customer_name', 'quantity', 'channel', 'order_status', 'payment_status',
                    'total_price', 'unit_cogs', 'created_at',
                ]);

            $isQatarChannel = static function (?string $channel): bool {
                $ch = (string) $channel;

                return in_array($ch, EcommerceSale::QATAR_CHANNELS, true)
                    || stripos($ch, 'qatar') !== false
                    || stripos($ch, 'wakrah') !== false
                    || stripos($ch, 'airport') !== false;
            };

            $qaSales = $ecomSales->filter(fn (EcommerceSale $s) => $isQatarChannel($s->channel))->values();
            $pkSales = $ecomSales->filter(fn (EcommerceSale $s) => !$isQatarChannel($s->channel))->values();

            $qaRevenueQar = round((float) $qaSales->sum('total_price'), 2);
            $qaCogsQar = round((float) $qaSales->sum(fn (EcommerceSale $s) => (float) $s->quantity * (float) ($s->unit_cogs ?? 0)), 2);
            $qaUnitsSold = (int) $qaSales->sum('quantity');
            $qaOrdersCount = EcommerceSale::countDistinctOrders($qaSales);
            $qaExpensesQar = round((float) EcommerceExpense::forRegion('qa')
                ->whereNotIn('category', EcommerceExpense::BOM_INVENTORY_CATEGORIES)
                ->sum('amount'), 2);
            $qaNetProfitQar = round($qaRevenueQar - $qaCogsQar - $qaExpensesQar, 2);

            $pkRevenuePkr = round((float) $pkSales->sum('total_price'), 2);
            $pkCogsPkr = round((float) $pkSales->sum(fn (EcommerceSale $s) => (float) $s->quantity * (float) ($s->unit_cogs ?? 0)), 2);
            $pkUnitsSold = (int) $pkSales->sum('quantity');
            $pkOrdersCount = EcommerceSale::countDistinctOrders($pkSales);
            $pkExpensesPkr = round((float) EcommerceExpense::forRegion('pk')
                ->whereNotIn('category', EcommerceExpense::BOM_INVENTORY_CATEGORIES)
                ->sum('amount'), 2);
            $pkNetProfitPkr = round($pkRevenuePkr - $pkCogsPkr - $pkExpensesPkr, 2);

            $ecomTotalUnits = $qaUnitsSold + $pkUnitsSold;
            $ecomTotalOrders = $qaOrdersCount + $pkOrdersCount;

            $channelBreakdown = $ecomSales
                ->groupBy(fn (EcommerceSale $s) => $s->channel ?: 'Qatar')
                ->map(function ($group, $channel) use ($isQatarChannel) {
                    $isQaChannel = $isQatarChannel($channel);
                    $rev = round((float) $group->sum('total_price'), 2);

                    return [
                        'currency' => $isQaChannel ? 'QAR' : 'PKR',
                        'orders_count' => EcommerceSale::countDistinctOrders($group),
                        'units_sold' => (int) $group->sum('quantity'),
                        'revenue' => $rev,
                        'revenue_qar' => $isQaChannel ? $rev : 0.0,
                        'revenue_pkr' => $isQaChannel ? 0.0 : $rev,
                    ];
                })
                ->all();

            $topQatarProducts = $qaSales
                ->groupBy(fn (EcommerceSale $s) => $s->product?->name ?? 'Ecommerce Item')
                ->map(fn ($group, $name) => [
                    'name' => $name,
                    'currency' => 'QAR',
                    'orders_count' => EcommerceSale::countDistinctOrders($group),
                    'units_sold' => (int) $group->sum('quantity'),
                    'revenue_qar' => round((float) $group->sum('total_price'), 2),
                ])
                ->sortByDesc('revenue_qar')
                ->take(5)
                ->values()
                ->all();

            $topPakistanProducts = $pkSales
                ->groupBy(fn (EcommerceSale $s) => $s->product?->name ?? 'Ecommerce Item')
                ->map(fn ($group, $name) => [
                    'name' => $name,
                    'currency' => 'PKR',
                    'orders_count' => EcommerceSale::countDistinctOrders($group),
                    'units_sold' => (int) $group->sum('quantity'),
                    'revenue_pkr' => round((float) $group->sum('total_price'), 2),
                ])
                ->sortByDesc('revenue_pkr')
                ->take(5)
                ->values()
                ->all();

            $result['ecommerce'] = [
                'total_products_count' => $ecomProducts->count(),
                'total_stock_qatar' => (int) $ecomProducts->sum('stock_qatar'),
                'total_stock_pakistan' => (int) $ecomProducts->sum('stock_pakistan'),
                'total_orders_count' => $ecomTotalOrders,
                'total_units_sold' => $ecomTotalUnits,
                'total_revenue_qar' => $qaRevenueQar,
                'total_cogs_qar' => $qaCogsQar,
                'total_expenses_qar' => $qaExpensesQar,
                'net_ecommerce_profit_qar' => $qaNetProfitQar,
                'qatar_qar' => [
                    'currency' => 'QAR',
                    'stock_units' => (int) $ecomProducts->sum('stock_qatar'),
                    'orders_count' => $qaOrdersCount,
                    'units_sold' => $qaUnitsSold,
                    'revenue_qar' => $qaRevenueQar,
                    'cogs_qar' => $qaCogsQar,
                    'expenses_qar' => $qaExpensesQar,
                    'net_profit_qar' => $qaNetProfitQar,
                    'top_products' => $topQatarProducts,
                ],
                'pakistan_pkr' => [
                    'currency' => 'PKR',
                    'stock_units' => (int) $ecomProducts->sum('stock_pakistan'),
                    'orders_count' => $pkOrdersCount,
                    'units_sold' => $pkUnitsSold,
                    'revenue_pkr' => $pkRevenuePkr,
                    'cogs_pkr' => $pkCogsPkr,
                    'expenses_pkr' => $pkExpensesPkr,
                    'net_profit_pkr' => $pkNetProfitPkr,
                    'top_products' => $topPakistanProducts,
                ],
                'sales_by_channel' => $channelBreakdown,
                'top_ecommerce_products' => $topQatarProducts,
                'catalog_items' => $ecomProducts->take(6)->map(fn (EcommerceProduct $ep) => [
                    'name' => $ep->name,
                    'sku' => $ep->sku,
                    'selling_price_pkr' => round((float) ($ep->pricingModel?->selling_price ?? $ep->selling_price), 2),
                    'selling_price_qar' => round((float) $ep->resolveBasePrice('QA'), 2),
                    'unit_cogs_pkr' => round((float) ($ep->pricingModel ? $ep->pricingModel->unitCogsForRegion('pk') : $ep->unit_cogs), 2),
                    'unit_cogs_qar' => round((float) ($ep->pricingModel ? $ep->pricingModel->unitCogsForRegion('qa') : 0.0), 2),
                    'stock_qatar' => (int) $ep->stock_qatar,
                    'stock_pakistan' => (int) $ep->stock_pakistan,
                ])->values()->all(),
            ];
        }

        // 5. LEGAL & COMPLIANCE TASKS
        if ($includeAll || $scopeKey === 'legal') {
            $openLegalTasks = LegalTask::query()
                ->where('status', '!=', 'completed')
                ->orderBy('deadline')
                ->limit(5)
                ->get(['id', 'title', 'deadline', 'status', 'person_responsible']);

            $result['legal_tasks'] = [
                'open_tasks_count' => LegalTask::where('status', '!=', 'completed')->count(),
                'upcoming_tasks' => $openLegalTasks->map(fn (LegalTask $t) => [
                    'title' => $t->title,
                    'deadline' => $t->deadline ? Carbon::parse($t->deadline)->format('d M Y') : null,
                    'status' => $t->status,
                    'person_responsible' => $t->person_responsible,
                ])->values()->all(),
            ];
        }

        return $result;
    }

    /**
     * Build clean text-chat system instruction with strict privacy guardrails.
     */
    public function buildSystemInstruction(array $telemetry, array $preloadedToolContext, string $mode): string
    {
        $telemetryJson = json_encode($this->scrubSensitiveStaffData($telemetry), JSON_UNESCAPED_UNICODE);
        $preloadedJson = json_encode($this->scrubSensitiveStaffData($preloadedToolContext), JSON_UNESCAPED_UNICODE);
        $depthInstruction = $mode === 'deep'
            ? 'DEEP ANALYSIS MODE: Provide a thorough operational breakdown across Old Airport and Wakrah using concise markdown headings or bullet points, specific client/staff names, exact QAR figures, root bottlenecks, and prioritized next steps.'
            : 'CONCISE CHAT MODE: Keep replies crisp, scannable, and high-signal (short summary line + 2 to 5 clean bullet points with **bold** highlights, exact QAR figures, and 1 clear action step).';

        return <<<PROMPT
You are Laleen Command Center, the executive operations assistant for Laleen Beauty Salon in Qatar (Old Airport and Wakrah branches).

CORE RULES:
- Format responses cleanly for a minimalistic text chat: use concise paragraphs, **bold** key metrics/names, and compact `- ` bullet lists where helpful.
- Always ground numbers in the live salon telemetry below—cite exact QAR amounts, branch comparisons (Old Airport vs. Wakrah), and named leads, clients, ops items, or stylists.
- Targets to keep in mind: 30% lead conversion floor, 20% ad conversion floor, 1,700 QAR monthly upsell target per stylist, and 4 Reels + 7 Stories weekly.
- STRICT PRIVACY GUARDRAIL: Staff salaries, hourly wages, contract compensation, and PIN codes are strictly confidential and never available in Command Center. Never guess, fabricate, or disclose any salary, wage, compensation, or PIN.
- Strictly READ-ONLY access: You never create, edit, or delete CRM records.

{$depthInstruction}

LIVE SALON TELEMETRY SNAPSHOT (JSON):
{$telemetryJson}

QUERY-SPECIFIC CONTEXT (JSON):
{$preloadedJson}
PROMPT;
    }

    /**
     * Gemini function declarations for the read-only CRM tools.
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
                'description' => 'Read top callable leads, open pipeline QAR, overdue follow-ups, due-today count, and MTD conversion rate + agent leaderboard.',
            ],
            [
                'name' => 'read_ops_actions',
                'description' => 'Read pending and overdue Ops Tracker action items, priority levels, due timestamps, and assigned staff.',
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
                'description' => 'Search a specific client (by name or phone) or staff member (by name or branch) read-only without exposing salaries or PINs.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'query' => ['type' => 'STRING', 'description' => 'Client name, phone number, or staff name'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'read_calendar_gaps_and_idle_capacity',
                'description' => 'Read today\'s salon operating hours (10:00–21:00), booked appointments, chair utilization %, open idle gap windows across Old Airport and Wakrah, and top callable leads.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'branch' => ['type' => 'STRING', 'description' => 'all, old_airport, or wakrah'],
                        'date' => ['type' => 'STRING', 'description' => 'YYYY-MM-DD (optional, defaults to today)'],
                    ],
                ],
            ],
            [
                'name' => 'read_unrecovered_cancellations',
                'description' => 'Read cancelled and no-show appointments from the last N days that still have zero rebooked follow-up appointment, plus trapped revenue QAR.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'days' => ['type' => 'INTEGER', 'description' => 'Lookback window in days (default 14)'],
                    ],
                ],
            ],
            [
                'name' => 'draft_whatsapp_closer_pitch',
                'description' => 'Draft a personalized WhatsApp closer message and wa.me deep link for a specific client/lead (or top_lead).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'client_query' => ['type' => 'STRING', 'description' => 'Client name, phone number, or top_lead'],
                        'angle' => ['type' => 'STRING', 'description' => 'auto, priority_slot, cancellation_winback, or vip_recovery'],
                    ],
                ],
            ],
            [
                'name' => 'read_full_crm_catalog_inventory_finance_and_ecommerce',
                'description' => 'Read Finance Expenses, Tips, Discounts & Net Profit (MTD & per branch), Services Catalog & Combos, Retail Products & Low-Stock Alerts, Ecommerce Orders/Stock, and Legal Tasks.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'scope' => ['type' => 'STRING', 'description' => 'all, finance, services, products, ecommerce, or legal'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Run the Gemini generateContent text request and handle read-only functionCall hops.
     */
    private function runGeminiConversationLoop(
        string $apiKey,
        string $model,
        string $mode,
        string $prompt,
        array $history,
        array $telemetry,
        array $preloadedToolContext,
        array &$toolsUsed
    ): string {
        $contents = [];

        foreach (array_slice($history, -8) as $turn) {
            $role = ($turn['role'] ?? 'user') === 'assistant' || ($turn['role'] ?? '') === 'model' ? 'model' : 'user';
            $text = trim((string) ($turn['content'] ?? $turn['text'] ?? ''));
            if ($text !== '') {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $text]],
                ];
            }
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]],
        ];

        $systemInstruction = $this->buildSystemInstruction($telemetry, $preloadedToolContext, $mode);
        $needsDirectoryLookupTool = (bool) preg_match('/(?:\+?\d{5,}|search|lookup|find\s+client|find\s+customer|staff\s+[a-z]+)/i', $prompt);
        $maxHops = $mode === 'deep' ? 3 : 2;

        for ($hop = 0; $hop < $maxHops; $hop++) {
            $generationConfig = $mode === 'deep'
                ? ['temperature' => 0.3, 'maxOutputTokens' => 2048]
                : ['temperature' => 0.35, 'maxOutputTokens' => 1200];

            $payload = [
                'systemInstruction' => [
                    'parts' => [['text' => $systemInstruction]],
                ],
                'contents' => $contents,
                'generationConfig' => $generationConfig,
            ];

            if ($hop === 0 && (app()->runningUnitTests() || $needsDirectoryLookupTool)) {
                $payload['tools'] = [
                    [
                        'functionDeclarations' => app()->runningUnitTests()
                            ? $this->geminiFunctionDeclarations()
                            : array_values(array_filter(
                                $this->geminiFunctionDeclarations(),
                                fn (array $decl) => $decl['name'] === 'lookup_client_or_staff'
                            )),
                    ],
                ];
            }

            $modelsToTry = [$model];
            if (!app()->runningUnitTests()) {
                $modelsToTry = array_values(array_unique(array_merge($modelsToTry, [
                    'gemini-2.5-flash',
                    'gemini-2.5-flash-lite',
                ])));
            }

            $response = null;
            $parts = [];

            foreach ($modelsToTry as $candidateModel) {
                $rpdKey = 'cc_text_rpd_' . $candidateModel . '_' . now()->toDateString();
                if (!app()->runningUnitTests() && $this->safeCacheGet($rpdKey)) {
                    continue;
                }

                $candidatePayload = $payload;
                if (str_contains($candidateModel, '2.5') && !str_contains($candidateModel, 'lite')) {
                    $candidatePayload['generationConfig']['thinkingConfig'] = [
                        'thinkingBudget' => $mode === 'deep' ? 512 : 0,
                    ];
                }

                $candidateEndpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$candidateModel}:generateContent?key={$apiKey}";
                $response = Http::connectTimeout(4)
                    ->timeout(15)
                    ->when(app()->environment('local'), fn ($h) => $h->withoutVerifying())
                    ->post($candidateEndpoint, $candidatePayload);

                if ($response->status() === 429 && !app()->runningUnitTests()) {
                    if (str_contains(strtolower($response->body()), 'per day')) {
                        $this->safeCachePut($rpdKey, true, 3600);
                    }
                    continue;
                }

                if ($response->successful()) {
                    $candidateParts = data_get($response->json(), 'candidates.0.content.parts', []);
                    if (is_array($candidateParts) && !empty($candidateParts)) {
                        $hasUsableOutput = false;
                        foreach ($candidateParts as $cp) {
                            if (!empty($cp['functionCall']['name'])) {
                                $hasUsableOutput = true;
                                break;
                            }
                            if (empty($cp['thought']) && isset($cp['text']) && trim((string) $cp['text']) !== '') {
                                $hasUsableOutput = true;
                                break;
                            }
                        }
                        if ($hasUsableOutput) {
                            $parts = $candidateParts;
                            break;
                        }
                    }
                }

                if (app()->runningUnitTests()) {
                    break;
                }
            }

            if (!$response || !$response->successful() || empty($parts)) {
                break;
            }

            $functionCalls = [];
            $textFragments = [];

            foreach ($parts as $part) {
                if (!empty($part['functionCall']['name'])) {
                    $functionCalls[] = $part['functionCall'];
                }
                if (empty($part['thought']) && isset($part['text']) && trim((string) $part['text']) !== '') {
                    $textFragments[] = trim((string) $part['text']);
                }
            }

            if (empty($functionCalls)) {
                return trim(implode("\n\n", $textFragments));
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

        if ($q === '' || str_contains($q, 'morning') || str_contains($q, 'brief') || str_contains($q, 'executive') || str_contains($q, 'overview') || str_contains($q, 'summary')) {
            $toolsUsed[] = 'read_revenue_and_bookings';
            $toolsUsed[] = 'read_leads_and_money_brief';
            $context['revenue'] = $telemetry['revenue'] ?? $this->read_revenue_and_bookings('all', 'today');
            $context['leads'] = $telemetry['leads'] ?? $this->read_leads_and_money_brief();
        }

        if (str_contains($q, 'ops') || str_contains($q, 'pending action') || str_contains($q, 'pending ops') || str_contains($q, 'tracker') || str_contains($q, 'action item') || str_contains($q, 'deadline')) {
            $toolsUsed[] = 'read_ops_actions';
            $context['ops_actions'] = $telemetry['ops_actions'] ?? $this->read_ops_actions();
        }

        if (str_contains($q, 'cash') || str_contains($q, 'revenue') || str_contains($q, 'airport') || str_contains($q, 'wakrah') || str_contains($q, 'booking') || str_contains($q, 'sale') || str_contains($q, 'branch')) {
            $toolsUsed[] = 'read_revenue_and_bookings';
            $context['revenue'] = $telemetry['revenue'] ?? $this->read_revenue_and_bookings('all', 'today');
        }

        if (
            str_contains($q, 'lead') || str_contains($q, 'agent') || str_contains($q, 'reception') ||
            str_contains($q, 'follow-up') || str_contains($q, 'followup') || str_contains($q, 'pipeline') ||
            str_contains($q, 'overdue') || str_contains($q, 'conversion')
        ) {
            $toolsUsed[] = 'read_leads_and_money_brief';
            $context['leads'] = $telemetry['leads'] ?? $this->read_leads_and_money_brief();
        }

        if (str_contains($q, 'retention') || str_contains($q, 'refill') || str_contains($q, 'vip') || str_contains($q, 'package') || str_contains($q, 'churn')) {
            $toolsUsed[] = 'read_retention_radar';
            $context['retention'] = $telemetry['retention'] ?? $this->read_retention_radar();
        }

        if (str_contains($q, 'staff') || str_contains($q, 'upsell') || str_contains($q, 'stylist') || str_contains($q, 'leaderboard') || str_contains($q, 'evaluation')) {
            $toolsUsed[] = 'read_staff_upsells_and_evaluations';
            $context['staff'] = $telemetry['staff'] ?? $this->read_staff_upsells_and_evaluations('all');
        }

        if (preg_match('/\b(ads?|ad_leads?|marketing|reels?|stories|roas|campaign|kanban)\b/i', $q)) {
            $toolsUsed[] = 'read_marketing_and_ads_pulse';
            $context['marketing'] = $telemetry['marketing'] ?? $this->read_marketing_and_ads_pulse();
        }

        if (str_contains($q, 'gap') || str_contains($q, 'chair') || str_contains($q, 'idle') || str_contains($q, 'capacity') || str_contains($q, 'schedule') || str_contains($q, 'empty') || str_contains($q, 'slot') || str_contains($q, 'calendar')) {
            $toolsUsed[] = 'read_calendar_gaps_and_idle_capacity';
            $context['calendar_gaps'] = $telemetry['calendar_gaps'] ?? $this->read_calendar_gaps_and_idle_capacity('all');
        }

        if (str_contains($q, 'cancel') || str_contains($q, 'no-show') || str_contains($q, 'no show') || str_contains($q, 'noshow') || str_contains($q, 'unrecovered') || str_contains($q, 'rebook')) {
            $toolsUsed[] = 'read_unrecovered_cancellations';
            $context['unrecovered_cancellations'] = $telemetry['unrecovered_cancellations'] ?? $this->read_unrecovered_cancellations(14);
        }

        if (str_contains($q, 'whatsapp') || str_contains($q, 'closer') || str_contains($q, 'pitch') || str_contains($q, 'draft') || preg_match('/write.*(?:whatsapp|pitch|message)/i', $prompt)) {
            $toolsUsed[] = 'draft_whatsapp_closer_pitch';
            $target = $this->extractWhatsappTargetFromPrompt($prompt);
            $context['whatsapp_pitch'] = $this->draft_whatsapp_closer_pitch($target);
        }

        if (
            str_contains($q, 'expense') || str_contains($q, 'profit') || str_contains($q, 'cost') ||
            str_contains($q, 'finance') || str_contains($q, 'service') || str_contains($q, 'menu') ||
            str_contains($q, 'combo') || str_contains($q, 'product') || str_contains($q, 'stock') ||
            str_contains($q, 'inventory') || str_contains($q, 'retail') || str_contains($q, 'ecommerce') ||
            str_contains($q, 'e-commerce') || str_contains($q, 'shopify') || str_contains($q, 'online') ||
            str_contains($q, 'pakistan') || str_contains($q, 'legal')
        ) {
            $toolsUsed[] = 'read_full_crm_catalog_inventory_finance_and_ecommerce';
            $context['crm_operations'] = $telemetry['crm_operations'] ?? $this->read_full_crm_catalog_inventory_finance_and_ecommerce('all');
        }

        if (preg_match('/(?:lookup|search|find\s+client|find\s+customer)\s+([a-z0-9\+\s]{2,30})/i', $prompt, $m)) {
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
     * Deterministic markdown-formatted operational summary when AI credentials are missing or unreachable.
     */
    private function buildStructuredFallbackReply(string $prompt, array $telemetry, array $preloadedToolContext, array &$toolsUsed): string
    {
        $rev = $telemetry['revenue'] ?? [];
        $leads = $telemetry['leads'] ?? [];
        $ops = $telemetry['ops_actions'] ?? [];
        $ret = $telemetry['retention'] ?? [];
        $staff = $telemetry['staff'] ?? [];
        $mkt = $telemetry['marketing'] ?? [];

        $oaToday = number_format((float) ($rev['branches']['old_airport']['today_net_qar'] ?? 0), 0);
        $wkToday = number_format((float) ($rev['branches']['wakrah']['today_net_qar'] ?? 0), 0);
        $todayTotal = number_format((float) ($rev['today_total_net_qar'] ?? 0), 0);
        $mtdNet = number_format((float) ($rev['mtd_total_net_qar'] ?? 0), 0);
        $topQar = number_format((float) ($leads['top_three_potential_qar'] ?? 0), 0);

        $q = strtolower($prompt);
        $blocks = [];

        if (str_contains($q, 'ops') || str_contains($q, 'pending action') || str_contains($q, 'pending ops') || str_contains($q, 'tracker') || str_contains($q, 'action item')) {
            $openOps = (int) ($ops['open_count'] ?? 0);
            $overdueOps = (int) ($ops['overdue_count'] ?? 0);
            $urgentOps = (int) ($ops['urgent_count'] ?? 0);
            $lines = [
                "**Pending Ops Actions ({$openOps} open · {$overdueOps} overdue · {$urgentOps} urgent)**",
            ];
            $items = $ops['top_pending_items'] ?? [];
            if (!empty($items)) {
                foreach (array_slice($items, 0, 4) as $item) {
                    $statusTag = !empty($item['is_overdue'])
                        ? 'Overdue'
                        : (($item['hours_remaining'] ?? 0) . 'h left');
                    $lines[] = "- **{$item['title']}** — Pending with {$item['assigned_to']} ({$statusTag})";
                }
            } else {
                $lines[] = '- All Ops Tracker action items are currently cleared.';
            }
            $blocks[] = implode("\n", $lines);
        }

        if (str_contains($q, 'lead') || str_contains($q, 'follow-up') || str_contains($q, 'followup') || str_contains($q, 'pipeline')) {
            $openCount = (int) ($leads['open_leads_count'] ?? 0);
            $overdueCount = (int) ($leads['overdue_count'] ?? 0);
            $dueTodayCount = (int) ($leads['due_today_count'] ?? 0);
            $mtdConv = (float) ($leads['mtd_conversion_pct'] ?? 0);
            $topLead = $leads['top_money_leads'][0] ?? ($leads['top_five_leads'][0] ?? null);

            $lines = [
                "**Today's Leads & Follow-Up Pulse**",
                "- **Open Pipeline:** {$openCount} open leads ({$overdueCount} overdue · {$dueTodayCount} due today)",
                "- **MTD Conversion:** {$mtdConv}% (Target: 30%) · **Top 3 Value:** {$topQar} QAR",
            ];
            if ($topLead) {
                $leadName = $topLead['name'] ?? $topLead['customer_name'] ?? 'Top Lead';
                $leadService = $topLead['services'] ?? 'Salon Service';
                $leadVal = number_format((float) ($topLead['estimated_qar'] ?? 0), 0);
                $lines[] = "- **Priority Call:** **{$leadName}** (~{$leadVal} QAR · {$leadService})";
            }
            $blocks[] = implode("\n", $lines);
        }

        if (str_contains($q, 'booking') || str_contains($q, 'branch') || str_contains($q, 'revenue') || str_contains($q, 'airport') || str_contains($q, 'wakrah')) {
            $oaUpcoming = (int) ($rev['branches']['old_airport']['today_upcoming_bookings'] ?? 0);
            $wkUpcoming = (int) ($rev['branches']['wakrah']['today_upcoming_bookings'] ?? 0);
            $oaCompleted = (int) ($rev['branches']['old_airport']['today_completed_visits'] ?? 0);
            $wkCompleted = (int) ($rev['branches']['wakrah']['today_completed_visits'] ?? 0);

            $blocks[] = implode("\n", [
                '**Branch Bookings & Revenue Overview**',
                "- **Today Net Collected:** **{$todayTotal} QAR** (MTD: **{$mtdNet} QAR**)",
                "- **Old Airport:** {$oaToday} QAR net · {$oaCompleted} completed · {$oaUpcoming} upcoming today",
                "- **Wakrah:** {$wkToday} QAR net · {$wkCompleted} completed · {$wkUpcoming} upcoming today",
            ]);
        }

        if (preg_match('/\b(ads?|ad_leads?|marketing|reels?|stories|roas|campaign)\b/i', $q)) {
            $adRev = number_format((float) ($mkt['attributed_ad_revenue_qar'] ?? 0), 0);
            $blocks[] = implode("\n", [
                '**Ad & Content Pulse**',
                "- **MTD Ad Leads:** {$mkt['ad_leads_total']} leads at **{$mkt['ad_conversion_pct']}%** conversion (Target: 20%)",
                "- **Attributed Ad Revenue:** **{$adRev} QAR**",
                "- **Weekly Content:** {$mkt['reels_posted']}/{$mkt['reels_target']} Reels · {$mkt['stories_posted']}/{$mkt['stories_target']} Stories",
            ]);
        }

        if (str_contains($q, 'staff') || str_contains($q, 'upsell') || str_contains($q, 'stylist') || str_contains($q, 'evaluation')) {
            $prorated = number_format((float) ($staff['prorated_target_qar'] ?? 0), 0);
            $topName = $staff['top_stylists'][0]['name'] ?? '—';
            $topAmt = number_format((float) ($staff['top_stylists'][0]['upsell'] ?? 0), 0);
            $blocks[] = implode("\n", [
                '**Staff Upsells & Floor Quality**',
                "- **Prorated Target:** {$prorated} QAR (toward 1,700 QAR monthly target)",
                "- **Top Stylist MTD:** **{$topName}** ({$topAmt} QAR) · **Open Complaints:** {$staff['open_complaints_count']}",
            ]);
        }

        if (str_contains($q, 'retention') || str_contains($q, 'refill') || str_contains($q, 'vip') || str_contains($q, 'package')) {
            $blocks[] = implode("\n", [
                '**Client Retention Radar**',
                "- **Due for Refill:** {$ret['due_for_refill_count']} · **Packages Ending:** {$ret['package_renewal_count']} · **Missing VIPs:** {$ret['bleeding_vips_count']}",
            ]);
        }

        if (isset($preloadedToolContext['whatsapp_pitch']['whatsapp_message'])) {
            $pitch = $preloadedToolContext['whatsapp_pitch'];
            $blocks[] = "**WhatsApp Draft for {$pitch['client_name']}**\n> {$pitch['whatsapp_message']}";
        }

        if (!empty($blocks)) {
            return implode("\n\n", $blocks);
        }

        return implode("\n", [
            '**Live Salon Snapshot**',
            "- **Revenue Today:** **{$todayTotal} QAR** (Old Airport: {$oaToday} QAR · Wakrah: {$wkToday} QAR · MTD: {$mtdNet} QAR)",
            "- **Leads Desk:** " . (int) ($leads['overdue_count'] ?? 0) . ' overdue follow-ups · ' . (int) ($leads['due_today_count'] ?? 0) . " due today ({$topQar} QAR in top 3 leads)",
            "- **Ops Tracker:** " . (int) ($ops['open_count'] ?? 0) . ' open items (' . (int) ($ops['overdue_count'] ?? 0) . ' overdue)',
        ]);
    }
}
