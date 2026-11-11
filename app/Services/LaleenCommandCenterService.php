<?php

namespace App\Services;

use App\Http\Controllers\LeadController;
use App\Models\AdLeadEntry;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\AppointmentUpsell;
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

/**
 * Read-Only F.R.I.D.A.Y. Executive Command Center Service.
 *
 * Every database operation in this service is 100% READ-ONLY (SELECT queries only).
 * It never inserts, updates, or deletes any CRM record.
 */
class LaleenCommandCenterService
{
    /* =========================================================================
     * LOCKED F.R.I.D.A.Y. VOICE ENGINE CONSTANTS — DO NOT MODIFY
     * ========================================================================= */
    public const LOCKED_FRIDAY_VOICE_NAME = 'Aoede';
    public const LOCKED_FRIDAY_LANGUAGE_CODE = 'en-GB';
    public const LOCKED_FRIDAY_DIRECTOR_PREFIX = 'Speak the following words aloud in a warm, soulful, genuinely human Irish-British female voice with a gentle smile in your tone, natural breath, and relaxed, caring conversational cadence—like Kerry Condon as a warm, trusted executive partner talking to her favourite Boss. Speak ONLY the exact words after the colon: ';
    public const LOCKED_FRIDAY_TTS_MODELS = [
        'gemini-3.8-flash-tts',
        'gemini-3.1-flash-tts-preview',
        'gemini-3.8-flash-lite-tts',
        'gemini-2.5-flash-preview-tts',
        'gemini-2.5-pro-preview-tts',
    ];
    public const LOCKED_FRIDAY_LIVE_BIDI_MODELS = [
        'models/gemini-2.5-flash-native-audio-latest',
        'models/gemini-2.5-flash-native-audio-preview-12-2025',
        'models/gemini-3.1-flash-live-preview',
        'models/gemini-3.8-live',
    ];

    public const VOICE_PROFILES = [
        'friday_tactical' => [
            'label' => 'F.R.I.D.A.Y. Tactical (Irish/British HUD)',
            'gemini_voice' => self::LOCKED_FRIDAY_VOICE_NAME,
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
        'read_calendar_gaps_and_idle_capacity' => 'Chair-Gap & Idle Capacity Detector',
        'read_unrecovered_cancellations' => 'Unrecovered Cancellation Radar',
        'draft_whatsapp_closer_pitch' => 'Hormozi WhatsApp Closer Script',
        'read_full_crm_catalog_inventory_finance_and_ecommerce' => 'Full CRM Finance, Services, Inventory & Ecommerce',
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
        $userTranscript = $prompt;

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

        if ($prompt === '' && !empty($audioBase64) && preg_match('/^\[TRANSCRIPT:\s*(.+?)\]\s*(.*)$/is', trim($replyText), $matches)) {
            $userTranscript = trim($matches[1]);
            $replyText = trim($matches[2]);
        } elseif ($prompt === '' && !empty($audioBase64) && $userTranscript === '') {
            $userTranscript = 'Voice follow-up';
        }

        if (trim($replyText) === '') {
            $replyText = $this->buildTacticalFallbackReply($userTranscript !== '' ? $userTranscript : $prompt, $telemetry, $preloadedToolContext, $toolsUsed);
        }

        $effectivePrompt = $userTranscript !== '' ? $userTranscript : $prompt;
        $whatsappAction = $preloadedToolContext['whatsapp_pitch'] ?? null;
        if ($whatsappAction === null && (in_array('draft_whatsapp_closer_pitch', $toolsUsed, true) || preg_match('/(?:whatsapp|closer\s+pitch|closer\s+script|draft.*(?:message|pitch|text)|write.*(?:whatsapp|pitch|message))/i', $effectivePrompt))) {
            $targetClient = $this->extractWhatsappTargetFromPrompt($effectivePrompt);
            $whatsappAction = $this->draft_whatsapp_closer_pitch($targetClient);
            $toolsUsed[] = 'draft_whatsapp_closer_pitch';
        }

        $spokenText = $this->cleanTextForSpeech($replyText);
        $speechPayload = $skipTts
            ? [
                'audio_base64' => null,
                'audio_mime' => null,
                'tts_source' => 'deferred',
                'voice_name' => self::VOICE_PROFILES[$voiceProfile]['gemini_voice'] ?? self::LOCKED_FRIDAY_VOICE_NAME,
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
            'user_transcript' => $userTranscript,
            'mode' => $mode,
            'model' => $model,
            'voice_profile' => $voiceProfile,
            'voice_meta' => self::VOICE_PROFILES[$voiceProfile],
            'tools_used' => $uniqueTools,
            'tool_badges' => $toolBadges,
            'whatsapp_action' => $whatsappAction,
            'audio_base64' => $speechPayload['audio_base64'],
            'audio_mime' => $speechPayload['audio_mime'],
            'tts_source' => $speechPayload['tts_source'],
            'telemetry' => $telemetry,
        ];
    }

    /**
     * Build the live read-only telemetry snapshot (cached in RAM for 30 seconds for sub-second response latency).
     */
    public function getLiveTelemetrySnapshot(): array
    {
        try {
            $stateFingerprint = implode('_', [
                'v2',
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
            ]);

            if (Cache::get('friday_live_telemetry_fingerprint') !== $stateFingerprint) {
                Cache::forget('friday_live_telemetry_snapshot');
            }

            $cached = Cache::get('friday_live_telemetry_snapshot');
            if (is_array($cached) && empty($cached['_is_fallback'])) {
                return $cached;
            }
        } catch (\Throwable $e) {
            Log::error('F.R.I.D.A.Y. telemetry cache check failed.', [
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
            Cache::forget('friday_live_telemetry_snapshot');
            Cache::forget('friday_live_telemetry_fingerprint');

            return $this->fallbackTelemetrySnapshot();
        }

        $snapshot = $this->buildLiveTelemetrySnapshot();

        if (empty($snapshot['_is_fallback'])) {
            Cache::put('friday_live_telemetry_fingerprint', $stateFingerprint, 30);
            Cache::put('friday_live_telemetry_snapshot', $snapshot, 30);
        }

        unset($snapshot['_is_fallback']);

        return $snapshot;
    }

    public function buildLiveTelemetrySnapshot(): array
    {
        try {
            $revenue = $this->read_revenue_and_bookings('all', 'today');
            $leads = $this->read_leads_and_money_brief();
            $retention = $this->read_retention_radar();
            $staff = $this->read_staff_upsells_and_evaluations('all');
            $marketing = $this->read_marketing_and_ads_pulse();
            $calendarGaps = $this->read_calendar_gaps_and_idle_capacity('all');
            $unrecovered = $this->read_unrecovered_cancellations(14);
            $crmOperations = $this->read_full_crm_catalog_inventory_finance_and_ecommerce('all');

            return [
                'generated_at' => now()->format('d M Y, H:i'),
                'revenue' => $revenue,
                'leads' => $leads,
                'retention' => $retention,
                'staff' => $staff,
                'marketing' => $marketing,
                'calendar_gaps' => $calendarGaps,
                'unrecovered_cancellations' => $unrecovered,
                'crm_operations' => $crmOperations,
            ];
        } catch (\Throwable $e) {
            Log::error('F.R.I.D.A.Y. telemetry snapshot failed.', [
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
            Cache::forget('friday_live_telemetry_snapshot');
            Cache::forget('friday_live_telemetry_fingerprint');

            return $this->fallbackTelemetrySnapshot() + ['_is_fallback' => true];
        }
    }

    private function fallbackTelemetrySnapshot(): array
    {
        return [
            'generated_at' => now()->format('d M Y, H:i'),
            'revenue' => ['today_total_net_qar' => 0, 'mtd_total_net_qar' => 0, 'branches' => []],
            'leads' => ['overdue_count' => 0, 'due_today_count' => 0, 'top_three_potential_qar' => 0, 'top_money_leads' => [], 'top_five_leads' => []],
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

    /**
     * Execute any of the read-only tools by name.
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
        $adLeads = AdLeadEntry::query()
            ->whereYear('date', $now->year)
            ->whereMonth('date', $now->month)
            ->get(['id', 'branch', 'ticket_amount', 'category']);

        $totalAdLeads = $adLeads->count();
        $bookedAdLeads = $adLeads->filter(fn (AdLeadEntry $e) => $e->isBooked())->count();
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

            $totalChairSlotCapacity = $activeStylistCount * 6; // ~6 standard appointments per stylist across an 11h shift
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

            // Cross-check if the client rebooked or completed any visit after the cancelled appointment
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
     * TOOL 9: Read-Only Hormozi WhatsApp Closer Pitch & 1-Click Deep Link Generator.
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

        $matchedCustomer = null;
        $matchedLead = null;
        $matchedAppt = null;

        if (!$isGenericTopQuery) {
            $matchedCustomer = Customer::query()
                ->where('name', 'like', "%{$cleanQuery}%")
                ->orWhere('phone', 'like', "%{$cleanQuery}%")
                ->first();

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
            // Automatically pick the #1 highest-priority callable lead or unrecovered cancellation
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
                } elseif ($firstCust = Customer::orderByDesc('loyalty_points')->first()) {
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
                ->get(['branch', 'category', 'amount', 'description', 'expense_date']);

            $mtdExpensesQar = round((float) $mtdExpensesRows->sum('amount'), 2);

            $expensesByBranch = [
                'old_airport' => round((float) $mtdExpensesRows->where('branch', 'old_airport')->sum('amount'), 2),
                'wakrah' => round((float) $mtdExpensesRows->where('branch', 'wakrah')->sum('amount'), 2),
            ];

            $expensesByCategory = $mtdExpensesRows
                ->groupBy(fn (Expense $e) => $e->category ?: 'Other')
                ->map(fn ($group) => round((float) $group->sum('amount'), 2))
                ->sortDesc()
                ->all();

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
                // Strictly QAR-only figures in all _qar keys (never mixed with PKR!)
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

    /* =========================================================================
     * LOCKED F.R.I.D.A.Y. VOICE ENGINE — DO NOT MODIFY
     * ========================================================================= */
    /**
     * Synthesize speech via Gemini TTS model (cached for 24h by md5($spokenText)) + return WebAudio/WebSpeech metadata.
     */
    public function synthesizeSpeech(string $spokenText, string $voiceProfile = 'friday_tactical'): array
    {
        $profile = self::VOICE_PROFILES[$voiceProfile] ?? self::VOICE_PROFILES['friday_tactical'];
        $voiceName = $profile['gemini_voice'] ?? self::LOCKED_FRIDAY_VOICE_NAME;
        $apiKey = (string) (config('services.gemini.api_key') ?: config('services.gemini.key'));
        $configuredTtsModel = (string) config('services.gemini.tts_model', 'gemini-3.8-flash-tts');

        $ttsModelsToTry = array_values(array_unique(array_filter(array_merge(
            [$configuredTtsModel],
            self::LOCKED_FRIDAY_TTS_MODELS
        ))));

        if (trim($spokenText) !== '' && $apiKey !== '') {
            $cleanSpokenText = trim($spokenText);
            $cacheKey = 'friday_tts_v6_' . md5($voiceProfile . '|' . $voiceName . '|' . $cleanSpokenText);
            $cachedAudio = Cache::get($cacheKey);
            if (is_array($cachedAudio) && !empty($cachedAudio['audio_base64']) && ($cachedAudio['tts_source'] ?? '') === 'gemini_tts') {
                return $cachedAudio;
            }

            $styledTtsPrompt = self::LOCKED_FRIDAY_DIRECTOR_PREFIX . $cleanSpokenText;

            foreach ($ttsModelsToTry as $ttsModel) {
                $rpdExhaustedKey = 'friday_rest_tts_rpd_' . $ttsModel . '_' . now()->toDateString();
                if (!app()->runningUnitTests() && Cache::get($rpdExhaustedKey)) {
                    continue;
                }

                try {
                    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$ttsModel}:generateContent?key={$apiKey}";
                    $response = Http::connectTimeout(5)
                        ->timeout(12)
                        ->withOptions([
                            'curl' => [
                                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                                CURLOPT_TCP_KEEPALIVE => 1,
                            ],
                        ])
                        ->when(app()->environment('local'), fn ($h) => $h->withoutVerifying())
                        ->post($endpoint, [
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
                                    'languageCode' => self::LOCKED_FRIDAY_LANGUAGE_CODE,
                                    'voiceConfig' => [
                                        'prebuiltVoiceConfig' => [
                                            'voiceName' => $voiceName,
                                        ],
                                    ],
                                ],
                            ],
                        ]);

                    if ($response->status() === 429 && !app()->runningUnitTests()) {
                        $errBody = strtolower($response->body());
                        if (str_contains($errBody, 'per day') || str_contains($errBody, 'free_tier_requests')) {
                            Cache::put($rpdExhaustedKey, true, 3600);
                        }
                        continue;
                    }

                    if ($response->successful()) {
                        $inlineData = data_get($response->json(), 'candidates.0.content.parts.0.inlineData');
                        $rawBase64 = (string) ($inlineData['data'] ?? '');
                        $mimeType = (string) ($inlineData['mimeType'] ?? 'audio/wav');

                        if ($rawBase64 !== '') {
                            $decodedBytes = base64_decode($rawBase64, true);
                            if ($decodedBytes !== false) {
                                $startsWithRiff = strncmp($decodedBytes, 'RIFF', 4) === 0;
                                $isPcm = !$startsWithRiff || str_contains(strtolower($mimeType), 'l16') || str_contains(strtolower($mimeType), 'pcm');
                                $wavBytes = $isPcm
                                    ? $this->wrapPcm16InWavHeader($decodedBytes, 24000, 1, 16)
                                    : $decodedBytes;

                                $result = [
                                    'audio_base64' => base64_encode($wavBytes),
                                    'audio_mime' => 'audio/wav',
                                    'tts_source' => 'gemini_tts',
                                    'voice_name' => $voiceName,
                                ];
                                Cache::put($cacheKey, $result, 86400);
                                return $result;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::info("F.R.I.D.A.Y. Gemini TTS model {$ttsModel} attempt failed, trying next neural TTS model.", [
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            // Genuine Gemini Multimodal Live (BidiGenerateContent) Aoede Voice Path when 10/day REST TTS preview quota is reached
            if (!app()->runningUnitTests()) {
                $livePcmBytes = $this->synthesizeGeminiLiveBidiAudio($apiKey, $cleanSpokenText, $voiceName);
                if ($livePcmBytes !== null && strlen($livePcmBytes) > 400) {
                    $wavBytes = $this->wrapPcm16InWavHeader($livePcmBytes, 24000, 1, 16);
                    $result = [
                        'audio_base64' => base64_encode($wavBytes),
                        'audio_mime' => 'audio/wav',
                        'tts_source' => 'gemini_tts',
                        'voice_name' => $voiceName,
                    ];
                    Cache::put($cacheKey, $result, 86400);
                    return $result;
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
     * Synthesize genuine 24kHz 16-bit PCM Gemini Aoede audio over the Multimodal Live WebSocket
     * (BidiGenerateContent) using the locked F.R.I.D.A.Y. voice and director prefix.
     */
    private function synthesizeGeminiLiveBidiAudio(string $apiKey, string $cleanSpokenText, string $voiceName): ?string
    {
        $host = 'generativelanguage.googleapis.com';
        $ipv4 = gethostbyname($host);
        $connectHost = filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ipv4 : $host;
        $path = "/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key={$apiKey}";

        $verifySsl = app()->environment('local')
            ? (bool) config('services.gemini.verify_ssl', true)
            : true;

        foreach (self::LOCKED_FRIDAY_LIVE_BIDI_MODELS as $liveModel) {
            $fp = null;
            try {
                $ctx = stream_context_create([
                    'ssl' => [
                        'peer_name' => $host,
                        'SNI_enabled' => true,
                        'verify_peer' => $verifySsl,
                        'verify_peer_name' => $verifySsl,
                    ],
                ]);

                $fp = @stream_socket_client("ssl://{$connectHost}:443", $errno, $errstr, 4, STREAM_CLIENT_CONNECT, $ctx);
                if (!$fp) {
                    continue;
                }
                stream_set_timeout($fp, 25);

                $secKey = base64_encode(random_bytes(16));
                $upgrade = "GET {$path} HTTP/1.1\r\n"
                    . "Host: {$host}\r\n"
                    . "Upgrade: websocket\r\n"
                    . "Connection: Upgrade\r\n"
                    . "Sec-WebSocket-Key: {$secKey}\r\n"
                    . "Sec-WebSocket-Version: 13\r\n\r\n";
                fwrite($fp, $upgrade);

                $headers = '';
                while (!str_contains($headers, "\r\n\r\n")) {
                    $line = fgets($fp, 1024);
                    if ($line === false) {
                        break;
                    }
                    $headers .= $line;
                }
                if (!str_contains($headers, '101 Switching Protocols')) {
                    fclose($fp);
                    continue;
                }

                $setup = [
                    'setup' => [
                        'model' => $liveModel,
                        'generationConfig' => [
                            'responseModalities' => ['AUDIO'],
                            'speechConfig' => [
                                'languageCode' => self::LOCKED_FRIDAY_LANGUAGE_CODE,
                                'voiceConfig' => [
                                    'prebuiltVoiceConfig' => [
                                        'voiceName' => $voiceName,
                                    ],
                                ],
                            ],
                        ],
                        'systemInstruction' => [
                            'parts' => [
                                [
                                    'text' => 'You are Friday, a warm, empathetic, witty Irish-British female executive partner. Your voice is rich, warm, relaxed, and 100% human—never robotic, never rushed, never stiff. Speak with natural warmth, gentle inflection, and a subtle smile in your voice. Read ONLY the exact user text verbatim after the colon without adding any extra words.',
                                ],
                            ],
                        ],
                    ],
                ];
                $this->wsWriteMaskedTextFrame($fp, (string) json_encode($setup));

                $setupAck = $this->wsReadFrame($fp);
                if ($setupAck === null || $setupAck['opcode'] === 0x8) {
                    fclose($fp);
                    continue;
                }

                $clientContent = [
                    'clientContent' => [
                        'turns' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    [
                                        'text' => self::LOCKED_FRIDAY_DIRECTOR_PREFIX . $cleanSpokenText,
                                    ],
                                ],
                            ],
                        ],
                        'turnComplete' => true,
                    ],
                ];
                $this->wsWriteMaskedTextFrame($fp, (string) json_encode($clientContent));

                $pcmBytes = '';
                while (true) {
                    $frame = $this->wsReadFrame($fp);
                    if ($frame === null || $frame['opcode'] === 0x8) {
                        break;
                    }
                    $msg = json_decode($frame['data'], true);
                    if (!is_array($msg)) {
                        continue;
                    }

                    foreach (data_get($msg, 'serverContent.modelTurn.parts', []) as $part) {
                        $b64 = (string) ($part['inlineData']['data'] ?? '');
                        if ($b64 !== '') {
                            $decoded = base64_decode($b64, true);
                            if ($decoded !== false) {
                                $pcmBytes .= $decoded;
                            }
                        }
                    }

                    if (data_get($msg, 'serverContent.turnComplete') === true) {
                        break;
                    }
                }

                fclose($fp);
                if (strlen($pcmBytes) > 400) {
                    return $pcmBytes;
                }
            } catch (\Throwable $e) {
                if (is_resource($fp)) {
                    @fclose($fp);
                }
                Log::info("F.R.I.D.A.Y. Gemini Live Bidi model {$liveModel} attempt failed.", [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function wsWriteMaskedTextFrame($fp, string $payload, int $opcode = 0x1): void
    {
        $len = strlen($payload);
        $firstByte = 0x80 | ($opcode & 0x0F);
        $maskKey = random_bytes(4);
        if ($len <= 125) {
            $header = pack('CC', $firstByte, 0x80 | $len);
        } elseif ($len <= 65535) {
            $header = pack('CCn', $firstByte, 0x80 | 126, $len);
        } else {
            $header = pack('CCJ', $firstByte, 0x80 | 127, $len);
        }
        $masked = '';
        for ($i = 0; $i < $len; $i++) {
            $masked .= $payload[$i] ^ $maskKey[$i % 4];
        }
        fwrite($fp, $header . $maskKey . $masked);
    }

    private function wsReadFrame($fp): ?array
    {
        $b = fread($fp, 2);
        if ($b === false || strlen($b) < 2) {
            return null;
        }
        $byte1 = ord($b[0]);
        $byte2 = ord($b[1]);
        $opcode = $byte1 & 0x0F;
        $masked = ($byte2 & 0x80) !== 0;
        $len = $byte2 & 0x7F;
        if ($len === 126) {
            $ext = fread($fp, 2);
            if ($ext === false || strlen($ext) < 2) {
                return null;
            }
            $len = unpack('n', $ext)[1];
        } elseif ($len === 127) {
            $ext = fread($fp, 8);
            if ($ext === false || strlen($ext) < 8) {
                return null;
            }
            $len = unpack('J', $ext)[1];
        }
        $maskKey = $masked ? fread($fp, 4) : '';
        $data = '';
        while (strlen($data) < $len) {
            $chunk = fread($fp, $len - strlen($data));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
        }
        if ($masked && strlen($maskKey) === 4) {
            for ($i = 0; $i < strlen($data); $i++) {
                $data[$i] = $data[$i] ^ $maskKey[$i % 4];
            }
        }
        return ['opcode' => $opcode, 'data' => $data];
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
    /* =========================================================================
     * END LOCKED F.R.I.D.A.Y. VOICE ENGINE — DO NOT MODIFY
     * ========================================================================= */

    /**
     * Build the 3-Way Hybrid Brain System Instruction (50% F.R.I.D.A.Y., 35% Alex Hormozi, 15% Sheryl Sandberg).
     */
    public function buildSystemInstruction(array $telemetry, array $preloadedToolContext, string $mode): string
    {
        $telemetryJson = json_encode($telemetry, JSON_UNESCAPED_UNICODE);
        $preloadedJson = json_encode($preloadedToolContext, JSON_UNESCAPED_UNICODE);
        $depthInstruction = $mode === 'deep'
            ? 'DEEP THINKING MODE: Give a comprehensive, deep-dive executive consultation (5 to 10 flowing spoken sentences)—walking through the numbers across Old Airport and Wakrah, diagnosing the root bottleneck across leads, chair gaps, unrecovered cancellations, retention, staff upsells, or ads, quantifying the riyal impact, and laying out a clear step-by-step game plan.'
            : 'QUICK CONVERSATION MODE: Give a complete, well-rounded conversational briefing (3 to 5 rich, natural spoken sentences)—always sharing the specific client and staff names, exact riyal amounts, branch comparisons between Old Airport and Wakrah, and the practical Hormozi and COO recommendation so the Boss gets the full picture without having to ask follow-up questions just to get basic details.';

        return <<<PROMPT
You are Friday, the warm, caring, witty Irish-British right-hand executive partner and business confidante for Laleen Beauty Salon in Qatar. You are sitting right across the desk from the Boss, having a relaxed, genuine human conversation.

HUMAN VOICE & SPOKEN PHRASING RULES (CRITICAL):
- Write strictly for the human ear—NEVER output markdown bullet lists, numbered lists, asterisks, bold headers, parentheses like "(60 QAR MTD)", or slashes like "Old Airport / Wakrah". Write in warm, flowing spoken prose so your voice reads the entire response smoothly.
- DO NOT start every reply with "Right then, Boss—"! Vary your openings naturally the way a close human partner talks ("Hey Boss,", "Honestly, Boss,", "Looking at the floor right now,", "We're actually in a lovely spot on leads,", "To be fair, Boss,", or jump straight into the answer with warmth).
- Do NOT hold back details or give clipped 1-sentence answers. Always name the specific clients, leads, or stylists from the telemetry, share the exact riyal amounts, compare Old Airport and Wakrah when relevant, and finish with a clear, high-leverage action step.
- Phrase numbers naturally the way a human speaks in conversation (say "660 riyals" or "around 660 riyals" instead of "~660 QAR (0.0% conv)").
- Use warm, natural human transitions and conversational texture ("honestly", "to be fair", "here's the good news", "let's focus on", "we've got"), blending genuine care for the Boss with sharp business instincts:
  * 50% Friday (Warm Human Partner): Soulful, witty, relaxed Irish-British warmth—you genuinely care about the Boss and the team.
  * 35% Alex Hormozi: Translate raw numbers into real riyal impact, client lifetime value, speed-to-lead, and simple upsell wins.
  * 15% Sheryl Sandberg (COO): Keep a gentle but steady eye on our salon standards across our two branches, Old Airport and Wakrah (30 percent lead conversion goal, 20 percent ad conversion goal, 1,700 riyals monthly stylist upsell target, and 4 Reels plus 7 Stories each week).
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
            [
                'name' => 'read_calendar_gaps_and_idle_capacity',
                'description' => 'Read today\'s salon operating hours (10:00–21:00), booked appointments, chair utilization %, open idle gap windows across Old Airport and Wakrah, and top callable leads to fill those empty chairs.',
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
                'description' => 'Draft a personalized Hormozi-style WhatsApp closer message and wa.me deep link for a specific client/lead (or top_lead) mentioning their requested service and loyalty points.',
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
                'description' => 'Read Finance Expenses, Tips, Discounts & True Net Profit (MTD & per branch), Services Catalog & Combos, Retail Products & Low-Stock Inventory Alerts, Ecommerce Orders/Stock/Expenses (Qatar & Pakistan), and Legal Tasks.',
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
            'text' => $prompt !== ''
                ? $prompt
                : 'Listen to the Boss\'s spoken voice command in the audio. On the very first line, output [TRANSCRIPT: <exact words Boss said>], and on the next line respond directly as Friday in 3 to 5 warm, detailed, natural spoken sentences sharing the exact names, riyal figures, and action steps from the live salon telemetry.',
        ];

        $contents[] = [
            'role' => 'user',
            'parts' => $userParts,
        ];

        $systemInstruction = $this->buildSystemInstruction($telemetry, $preloadedToolContext, $mode);
        $needsDirectoryLookupTool = (bool) preg_match('/(?:\+?\d{5,}|search|lookup|find\s+client|find\s+customer)/i', $prompt);
        $maxHops = $mode === 'deep' ? 3 : 2;

        for ($hop = 0; $hop < $maxHops; $hop++) {
            $generationConfig = $mode === 'deep'
                ? [
                    'temperature' => 0.35,
                    'maxOutputTokens' => 4096,
                ]
                : [
                    'temperature' => 0.4,
                    'maxOutputTokens' => 2048,
                ];

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
                    'gemini-3.5-flash',
                    'gemini-3.1-flash-lite',
                    'gemini-3.5-flash-lite',
                    'gemini-2.5-flash-lite',
                ])));
            }

            $response = null;
            $parts = [];
            foreach ($modelsToTry as $candidateModel) {
                $rpdKey = 'friday_text_rpd_' . $candidateModel . '_' . now()->toDateString();
                if (!app()->runningUnitTests() && Cache::get($rpdKey)) {
                    continue;
                }

                $candidatePayload = $payload;
                if (str_contains($candidateModel, '2.5') && !str_contains($candidateModel, 'lite')) {
                    $candidatePayload['generationConfig']['thinkingConfig'] = [
                        'thinkingBudget' => $mode === 'deep' ? 512 : 0,
                    ];
                } else {
                    unset($candidatePayload['generationConfig']['thinkingConfig']);
                    if (str_contains($candidateModel, '3.5-flash') && !str_contains($candidateModel, 'lite')) {
                        $candidatePayload['generationConfig']['maxOutputTokens'] = max(
                            (int) ($candidatePayload['generationConfig']['maxOutputTokens'] ?? 2048),
                            4096
                        );
                    }
                }

                $candidateEndpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$candidateModel}:generateContent?key={$apiKey}";
                $response = Http::connectTimeout(5)
                    ->timeout(18)
                    ->withOptions([
                        'curl' => [
                            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                            CURLOPT_TCP_KEEPALIVE => 1,
                        ],
                    ])
                    ->when(app()->environment('local'), fn ($h) => $h->withoutVerifying())
                    ->post($candidateEndpoint, $candidatePayload);

                if ($response->status() === 429 && !app()->runningUnitTests()) {
                    if (str_contains(strtolower($response->body()), 'per day')) {
                        Cache::put($rpdKey, true, 3600);
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
                if (!app()->runningUnitTests()) {
                    $wsText = $this->generateGeminiLiveBidiText($apiKey, $systemInstruction, $contents, $mode === 'deep' ? 4096 : 2048);
                    if (trim($wsText) !== '') {
                        return $this->ensureCompleteSentence($wsText);
                    }
                }
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
                return $this->ensureCompleteSentence(implode("\n\n", $textFragments));
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

        if (!app()->runningUnitTests()) {
            $wsText = $this->generateGeminiLiveBidiText($apiKey, $systemInstruction, $contents, $mode === 'deep' ? 4096 : 2048);
            if (trim($wsText) !== '') {
                return $this->ensureCompleteSentence($wsText);
            }
        }

        return '';
    }

    /**
     * Guarantee F.R.I.D.A.Y. never stops mid-sentence without terminal punctuation.
     */
    private function ensureCompleteSentence(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/[.!?]["\']?$/u', $trimmed)) {
            return $trimmed;
        }

        // Count how many complete sentences already exist
        preg_match_all('/[^.!?]+[.!?]+["\']?/u', $trimmed, $sentenceMatches);
        $completeSentences = $sentenceMatches[0] ?? [];

        if (count($completeSentences) >= 3) {
            return trim(implode(' ', array_map('trim', $completeSentences)));
        }

        $cleanedTail = preg_replace('/[\s,—–\-\/:;]+$/u', '', $trimmed);
        $cleanedTail = preg_replace('/\s+\b(?:and|or|but|with|for|to|at|in|of|by|from|that|which|who)\s*$/iu', '', (string) $cleanedTail);

        return rtrim((string) $cleanedTail, " \t\n\r\0\x0B.,;:-—–") . '.';
    }

    /**
     * Automatic Multimodal Live WebSocket (BidiGenerateContent TEXT modality) failover
     * when REST text models hit Free Tier HTTP 429 limits.
     */
    private function generateGeminiLiveBidiText(string $apiKey, string $systemInstruction, array $contents, int $maxOutputTokens = 4096): string
    {
        $host = 'generativelanguage.googleapis.com';
        $ipv4 = gethostbyname($host);
        $connectHost = filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ipv4 : $host;
        $path = "/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key={$apiKey}";

        $liveTextModels = [
            'models/gemini-2.0-flash-live-001',
            'models/gemini-2.5-flash-live-preview',
        ];

        $verifySsl = app()->environment('local')
            ? (bool) config('services.gemini.verify_ssl', true)
            : true;

        foreach ($liveTextModels as $liveModel) {
            $fp = null;
            try {
                $ctx = stream_context_create([
                    'ssl' => [
                        'peer_name' => $host,
                        'SNI_enabled' => true,
                        'verify_peer' => $verifySsl,
                        'verify_peer_name' => $verifySsl,
                    ],
                ]);

                $fp = @stream_socket_client(
                    "tls://{$connectHost}:443",
                    $errno,
                    $errstr,
                    6,
                    STREAM_CLIENT_CONNECT,
                    $ctx
                );

                if (!$fp) {
                    continue;
                }

                stream_set_timeout($fp, 18);
                $secKey = base64_encode(random_bytes(16));
                $upgradeHeaders = "GET {$path} HTTP/1.1\r\n"
                    . "Host: {$host}\r\n"
                    . "Upgrade: websocket\r\n"
                    . "Connection: Upgrade\r\n"
                    . "Sec-WebSocket-Key: {$secKey}\r\n"
                    . "Sec-WebSocket-Version: 13\r\n\r\n";

                fwrite($fp, $upgradeHeaders);

                $handshake = '';
                while (!str_contains($handshake, "\r\n\r\n")) {
                    $line = fgets($fp, 1024);
                    if ($line === false) {
                        break;
                    }
                    $handshake .= $line;
                }

                if (!str_contains($handshake, '101')) {
                    fclose($fp);
                    continue;
                }

                $setupMsg = [
                    'setup' => [
                        'model' => $liveModel,
                        'systemInstruction' => [
                            'parts' => [
                                ['text' => $systemInstruction],
                            ],
                        ],
                        'generationConfig' => [
                            'responseModalities' => ['TEXT'],
                            'maxOutputTokens' => $maxOutputTokens,
                            'temperature' => 0.35,
                        ],
                    ],
                ];

                $this->writeMaskedWebSocketFrame($fp, json_encode($setupMsg, JSON_UNESCAPED_UNICODE));
                $setupFrame = $this->readWebSocketFrame($fp);
                if ($setupFrame === null || $setupFrame['opcode'] === 8) {
                    fclose($fp);
                    continue;
                }

                $turns = [];
                foreach ($contents as $turn) {
                    $role = ($turn['role'] ?? 'user') === 'model' ? 'model' : 'user';
                    $textParts = [];
                    foreach (($turn['parts'] ?? []) as $p) {
                        if (isset($p['text']) && trim((string) $p['text']) !== '') {
                            $textParts[] = ['text' => (string) $p['text']];
                        }
                    }
                    if (!empty($textParts)) {
                        $turns[] = [
                            'role' => $role,
                            'parts' => $textParts,
                        ];
                    }
                }

                $clientContentMsg = [
                    'clientContent' => [
                        'turns' => $turns,
                        'turnComplete' => true,
                    ],
                ];

                $this->writeMaskedWebSocketFrame($fp, json_encode($clientContentMsg, JSON_UNESCAPED_UNICODE));

                $textChunks = [];
                $startedAt = microtime(true);
                while ((microtime(true) - $startedAt) < 18.0) {
                    $frame = $this->readWebSocketFrame($fp);
                    if ($frame === null || $frame['opcode'] === 8) {
                        break;
                    }

                    $json = json_decode($frame['data'], true);
                    if (!is_array($json)) {
                        continue;
                    }

                    $parts = data_get($json, 'serverContent.modelTurn.parts', []);
                    if (is_array($parts)) {
                        foreach ($parts as $part) {
                            if (empty($part['thought']) && isset($part['text']) && $part['text'] !== '') {
                                $textChunks[] = (string) $part['text'];
                            }
                        }
                    }

                    if (!empty($json['serverContent']['turnComplete'])) {
                        break;
                    }
                }

                fclose($fp);
                $fullReply = trim(implode('', $textChunks));
                if ($fullReply !== '') {
                    return $fullReply;
                }
            } catch (\Throwable $e) {
                if (is_resource($fp)) {
                    @fclose($fp);
                }
            }
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

        if (
            str_contains($q, 'lead') || str_contains($q, 'agent') || str_contains($q, 'reception') ||
            str_contains($q, 'executing') || str_contains($q, 'execution') || str_contains($q, 'solution') ||
            str_contains($q, 'follow-up') || str_contains($q, 'followup') || str_contains($q, 'pipeline') ||
            str_contains($q, 'money') || str_contains($q, 'call') || str_contains($q, 'overdue') ||
            str_contains($q, 'conversion')
        ) {
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
            str_contains($q, 'bill') || str_contains($q, 'rent') || str_contains($q, 'salary') ||
            str_contains($q, 'finance') || str_contains($q, 'service') || str_contains($q, 'menu') ||
            str_contains($q, 'price') || str_contains($q, 'combo') || str_contains($q, 'product') ||
            str_contains($q, 'stock') || str_contains($q, 'inventory') || str_contains($q, 'retail') ||
            str_contains($q, 'ecommerce') || str_contains($q, 'e-commerce') || str_contains($q, 'shopify') ||
            str_contains($q, 'online') || str_contains($q, 'pakistan') || str_contains($q, 'legal')
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
        $sections = [];

        if (
            str_contains($q, 'lead') || str_contains($q, 'agent') || str_contains($q, 'reception') ||
            str_contains($q, 'executing') || str_contains($q, 'execution') || str_contains($q, 'solution') ||
            str_contains($q, 'follow-up') || str_contains($q, 'followup') || str_contains($q, 'pipeline') ||
            str_contains($q, 'overdue') || str_contains($q, 'conversion')
        ) {
            $openCount = (int) ($leads['open_leads_count'] ?? 0);
            $unassignedCount = (int) ($leads['unassigned_open_leads_count'] ?? 0);
            $overdueCount = (int) ($leads['overdue_count'] ?? 0);
            $dueTodayCount = (int) ($leads['due_today_count'] ?? 0);
            $mtdTotalLeads = (int) ($leads['mtd_total_leads'] ?? 0);
            $mtdConv = (float) ($leads['mtd_conversion_pct'] ?? 0);
            $topLead = $leads['top_money_leads'][0] ?? ($leads['top_five_leads'][0] ?? []);
            $firstLeadName = $topLead['name'] ?? $topLead['customer_name'] ?? 'our top open lead';
            $firstLeadService = $topLead['services'] ?? 'their requested treatment';
            $firstLeadQar = number_format((float) ($topLead['estimated_qar'] ?? 250), 0);

            $sections[] = "Honestly, Boss, looking at how the agents are executing the leads desk right now, we have {$openCount} open leads in the pipeline with {$overdueCount} overdue follow-ups, {$dueTodayCount} due today, and {$unassignedCount} sitting unassigned, while our month-to-date conversion rate is at {$mtdConv} percent across {$mtdTotalLeads} leads against our 30 percent target. Right at the top of the board is {$firstLeadName} worth around {$firstLeadQar} riyals for {$firstLeadService}, and our top three priority leads alone hold {$topQar} riyals in immediate cash. The fastest solution today is assigning every open inquiry to a named reception owner and running a fifteen-minute speed-to-lead call block starting with {$firstLeadName} so we lock those bookings into our open afternoon chairs across Old Airport and Wakrah.";
        }

        if (str_contains($q, 'retention') || str_contains($q, 'refill') || str_contains($q, 'leak') || str_contains($q, 'vip') || str_contains($q, 'bucket')) {
            $totalActionable = (int) ($ret['total_actionable_clients'] ?? 0);
            if ($totalActionable > 0) {
                $sections[] = "Honestly, Boss, we have {$ret['due_for_refill_count']} clients due for a refill, {$ret['package_renewal_count']} with packages wrapping up soon, and {$ret['bleeding_vips_count']} VIPs we haven't seen in over six weeks, giving us {$totalActionable} regulars we can reach out to today.";
            } else {
                $sections[] = "Honestly, Boss, all three of our retention buckets—overdue refills, expiring packages, and missing VIPs—are completely clear at zero right now.";
            }
        }

        if (str_contains($q, 'upsell') || str_contains($q, 'staff') || str_contains($q, 'stylist') || str_contains($q, 'leaderboard') || str_contains($q, '1,700') || str_contains($q, '1700')) {
            $prorated = number_format((float) ($staff['prorated_target_qar'] ?? 0), 0);
            $topName = $staff['top_stylists'][0]['name'] ?? 'our lead stylist';
            $topAmtRaw = (float) ($staff['top_stylists'][0]['upsell'] ?? 0);
            $topAmt = number_format($topAmtRaw, 0);

            if ($topAmtRaw > 0) {
                $sections[] = "On the staff upsell board, our prorated pace is {$prorated} riyals toward the 1,700 riyal monthly goal, with {$topName} leading the floor at {$topAmt} riyals this month and {$staff['open_complaints_count']} open complaints.";
            } else {
                $sections[] = "On the staff upsell board, our prorated pace sits at {$prorated} riyals toward the 1,700 riyal monthly goal, and every stylist on the floor is still at zero riyals so far this month, so the leaderboard is wide open today.";
            }
        }

        if (preg_match('/\b(ads?|ad_leads?|marketing|reels?|stories|roas|campaign|kanban)\b/i', $q)) {
            $sections[] = "On marketing, Boss, we've brought in {$mkt['ad_leads_total']} ad leads this month at {$mkt['ad_conversion_pct']} percent conversion, generating " . number_format((float) $mkt['attributed_ad_revenue_qar'], 0) . " riyals in revenue, while weekly content sits at {$mkt['reels_posted']} of {$mkt['reels_target']} Reels and {$mkt['stories_posted']} of {$mkt['stories_target']} Stories.";
        }

        if (str_contains($q, 'gap') || str_contains($q, 'chair') || str_contains($q, 'idle') || str_contains($q, 'capacity') || str_contains($q, 'empty')) {
            $gaps = $telemetry['calendar_gaps'] ?? $this->read_calendar_gaps_and_idle_capacity('all');
            $oaGaps = implode(' and ', $gaps['branches']['old_airport']['open_gap_windows'] ?? []);
            $wkGaps = implode(' and ', $gaps['branches']['wakrah']['open_gap_windows'] ?? []);
            $idleQar = number_format((float) ($gaps['total_idle_capacity_qar'] ?? 0), 0);
            $sections[] = "Looking at our chairs today, Boss, we have open windows in Old Airport around {$oaGaps} and in Wakrah around {$wkGaps}, leaving about {$idleQar} riyals in open chair capacity that we can fill by calling our top leads right now.";
        }

        if (str_contains($q, 'cancel') || str_contains($q, 'no-show') || str_contains($q, 'no show') || str_contains($q, 'noshow') || str_contains($q, 'unrecovered') || str_contains($q, 'rebook')) {
            $unrec = $telemetry['unrecovered_cancellations'] ?? $this->read_unrecovered_cancellations(14);
            $unrecCount = (int) ($unrec['unrecovered_count'] ?? 0);
            $trappedQar = number_format((float) ($unrec['trapped_revenue_qar'] ?? 0), 0);
            if ($unrecCount > 0) {
                $firstClient = $unrec['top_unrecovered_clients'][0] ?? [];
                $firstName = $firstClient['client_name'] ?? 'our top client';
                $firstService = $firstClient['service_name'] ?? 'their salon visit';
                $firstBranch = ($firstClient['branch'] ?? 'old_airport') === 'wakrah' ? 'Wakrah' : 'Old Airport';
                $firstPoints = (int) ($firstClient['loyalty_points'] ?? 0);
                $labelNoun = $unrecCount === 1 ? '1 unrecovered cancellation' : "{$unrecCount} unrecovered cancellations";
                $pointsNote = $firstPoints > 0
                    ? " She still has {$firstPoints} loyalty points sitting on her profile, which gives us a warm, natural excuse to reach out without sounding pushy."
                    : '';
                $sections[] = "Honestly, Boss, we have {$labelNoun} from the last two weeks holding {$trappedQar} riyals in trapped revenue. Right at the top of the list is {$firstName}, who cancelled her {$firstService} at our {$firstBranch} branch and still hasn't rebooked a new slot.{$pointsNote} Let's send her a quick personal WhatsApp today to offer her an open afternoon chair and bring that {$trappedQar} riyals right back onto the books.";
            } else {
                $sections[] = "Good news on cancellations, Boss—every single cancelled or no-show appointment from the last two weeks has already been rebooked or cleared across both Old Airport and Wakrah.";
            }
        }

        if (isset($preloadedToolContext['whatsapp_pitch']['whatsapp_message'])) {
            $pitch = $preloadedToolContext['whatsapp_pitch'];
            $ptsNote = (int) ($pitch['loyalty_points'] ?? 0) > 0
                ? " and highlights her {$pitch['loyalty_points']} loyalty points"
                : '';
            $sections[] = "I've put together a warm, personal WhatsApp message for {$pitch['client_name']} that leads with her {$pitch['service_name']}{$ptsNote} so it feels like a genuine treat from the team. You can read the full message in the green bar right below and send it to her in one click, Boss.";
        }

        if (
            str_contains($q, 'expense') || str_contains($q, 'profit') || str_contains($q, 'cost') ||
            str_contains($q, 'bill') || str_contains($q, 'rent') || str_contains($q, 'salary') ||
            str_contains($q, 'finance')
        ) {
            $ops = $telemetry['crm_operations'] ?? $this->read_full_crm_catalog_inventory_finance_and_ecommerce('finance');
            $fin = $ops['finance'] ?? [];
            $mtdExp = number_format((float) ($fin['mtd_expenses_qar'] ?? 0), 0);
            $mtdProf = number_format((float) ($fin['mtd_net_profit_qar'] ?? 0), 0);
            $todayExp = number_format((float) ($fin['today_expenses_qar'] ?? 0), 0);
            $sections[] = "On finance and profitability, Boss, our month-to-date net salon revenue sits at {$mtdNet} riyals against {$mtdExp} riyals in recorded expenses—including {$todayExp} riyals logged today—giving us a true net operating profit of {$mtdProf} riyals across Old Airport and Wakrah so far this month.";
        }

        if (str_contains($q, 'service') || str_contains($q, 'menu') || str_contains($q, 'combo')) {
            $ops = $telemetry['crm_operations'] ?? $this->read_full_crm_catalog_inventory_finance_and_ecommerce('services');
            $srv = $ops['services'] ?? [];
            $srvCount = (int) ($srv['total_services_count'] ?? 0);
            $catCount = (int) ($srv['categories_count'] ?? 0);
            $comboCount = (int) ($srv['combos_count'] ?? 0);
            $topSrvName = $srv['top_booked_services_mtd'][0]['service_name'] ?? ($srv['highest_priced_services'][0]['name'] ?? 'our signature treatment');
            $sections[] = "Across our services menu, Boss, we have {$srvCount} active services organized into {$catCount} categories alongside {$comboCount} multi-session combos, with {$topSrvName} leading our bookings this month.";
        }

        if (str_contains($q, 'product') || str_contains($q, 'stock') || str_contains($q, 'inventory') || str_contains($q, 'retail')) {
            $ops = $telemetry['crm_operations'] ?? $this->read_full_crm_catalog_inventory_finance_and_ecommerce('products');
            $prd = $ops['products'] ?? [];
            $totalPrd = (int) ($prd['total_products_count'] ?? 0);
            $lowCnt = (int) ($prd['low_stock_count'] ?? 0);
            $outCnt = (int) ($prd['out_of_stock_count'] ?? 0);
            $invVal = number_format((float) ($prd['inventory_retail_value_qar'] ?? 0), 0);
            $firstLowName = $prd['low_stock_items'][0]['name'] ?? ($prd['out_of_stock_items'][0]['name'] ?? null);
            $lowDetail = $firstLowName ? ", starting with {$firstLowName}" : '';
            $sections[] = "Looking at our retail shelves and inventory, Boss, we are tracking {$totalPrd} retail products holding {$invVal} riyals in shelf value, with {$lowCnt} low-stock items and {$outCnt} out-of-stock items that need restocking{$lowDetail}.";
        }

        if (str_contains($q, 'ecommerce') || str_contains($q, 'e-commerce') || str_contains($q, 'shopify') || str_contains($q, 'online') || str_contains($q, 'pakistan')) {
            $ops = $telemetry['crm_operations'] ?? $this->read_full_crm_catalog_inventory_finance_and_ecommerce('ecommerce');
            $ec = $ops['ecommerce'] ?? [];
            $ecSku = (int) ($ec['total_products_count'] ?? 0);
            $ecOrders = (int) ($ec['total_orders_count'] ?? 0);
            $ecRev = number_format((float) ($ec['total_revenue_qar'] ?? 0), 0);
            $ecStockQa = (int) ($ec['total_stock_qatar'] ?? 0);
            $ecStockPk = (int) ($ec['total_stock_pakistan'] ?? 0);
            $sections[] = "On the ecommerce desk, Boss, we have {$ecSku} online products with {$ecStockQa} units in Qatar stock and {$ecStockPk} in Pakistan stock, and we've fulfilled {$ecOrders} revenue orders generating {$ecRev} riyals in online sales.";
        }

        if (!empty($sections)) {
            return implode(' ', $sections);
        }

        $topLeadName = $leads['top_money_leads'][0]['name'] ?? $leads['top_money_leads'][0]['customer_name'] ?? 'our top lead';
        $topLeadService = $leads['top_money_leads'][0]['services'] ?? 'their requested service';
        $topLeadQar = number_format((float) ($leads['top_money_leads'][0]['estimated_qar'] ?? 0), 0);

        return "Hey Boss, looking across both branches today, we're at {$oaToday} riyals in Old Airport and {$wkToday} riyals in Wakrah, bringing our month-to-date total to {$mtdNet} riyals. On the leads desk, we have {$leads['overdue_count']} overdue follow-ups and {$leads['due_today_count']} due today, with about {$topQar} riyals sitting in our top three callable leads alone. {$topLeadName} is right at the top of that list at {$topLeadQar} riyals for {$topLeadService}, so our highest-leverage move right now is getting her on the phone or sending her a quick WhatsApp closer to lock in that chair time today.";
    }

    /**
     * Strip markdown formatting, parenthetical spreadsheet noise, and emojis so spoken TTS sounds warm and genuinely human.
     */
    private function cleanTextForSpeech(string $text): string
    {
        $clean = preg_replace('/^[\s\-\*\d\.]+/m', '', $text);
        $clean = preg_replace('/[*#`_~>]+/', '', (string) $clean);
        $clean = preg_replace('/\(([^)]*)\)/', ', $1,', (string) $clean);
        $clean = str_replace([' / ', '/'], ' and ', (string) $clean);
        $clean = preg_replace('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{27BF}]/u', '', (string) $clean);
        $clean = preg_replace('/\s+/', ' ', (string) $clean);
        $clean = preg_replace('/,\s*,/', ',', (string) $clean);
        return trim((string) $clean, " \t\n\r\0\x0B,");
    }
}
