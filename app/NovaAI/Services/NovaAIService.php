<?php

namespace App\NovaAI\Services;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ClientPackage;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Staff;
use App\NovaAI\Support\CustomerRetentionAnalytics;
use App\Support\BranchFinancialSummaryService;
use App\Support\StaffSalesAnalytics;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Nova's analytical engine: pulls a fresh business snapshot straight from
 * the CRM's own tables (today's and the trailing week's revenue, the
 * trailing week's appointment-status funnel, month-to-date branch net
 * profit/margin, sale-item-attributed staff performance, month-to-date
 * staff upsell target performance, service volume, aggregate customer &
 * retention intelligence, and clients sitting on an unredeemed combo
 * package balance) and hands it to Gemini alongside the admin's question,
 * under a system instruction that keeps Nova speaking like an executive
 * advisor rather than a generic chatbot. Never throws back to the
 * controller - a missing key or a failed request degrades to a plain,
 * clearly-labelled notice instead of breaking the widget.
 *
 * NOVA READ-ONLY INTEGRATION RULE (see app/NovaAI/README.md): every
 * section below only reads existing CRM models/tables or already-reusable
 * services without changing them. This class must never require a change
 * to an existing CRM controller, calculation, schema, or workflow -
 * Nova-specific calculations (like BranchFinancialSummaryService) are
 * kept as independent, read-only copies rather than making CRM code
 * depend on Nova.
 */
class NovaAIService
{
    private const STAFF_PERFORMANCE_DAYS = 7;
    private const SERVICE_COUNT_DAYS = 7;
    private const PACKAGE_LOOKAHEAD = 15;
    private const APPOINTMENT_FUNNEL_DAYS = 7;

    /**
     * The only statuses Appointment::status ever actually holds (enforced by
     * AppointmentController's own validation - see 'pending,arrived,in_progress,
     * completed,no_show,cancelled' on create and 'arrived,in_progress,completed,
     * no_show,cancelled' on updateStatus). There is no "rescheduled" status.
     */
    private const APPOINTMENT_STATUSES = ['pending', 'arrived', 'in_progress', 'completed', 'no_show', 'cancelled'];

    private const APPOINTMENT_FUNNEL_BRANCHES = ['old_airport', 'wakrah', 'home_service'];

    /** In-process memoization only - avoids re-reading the prompt file if ask() runs more than once per request. */
    private static ?string $cachedPersona = null;

    public function ask(string $message): string
    {
        $apiKey = config('services.gemini.key');

        if (empty($apiKey)) {
            return "Nova isn't connected yet - ask an administrator to set GEMINI_API_KEY in the environment configuration.";
        }

        try {
            $model = config('services.gemini.model', 'gemini-3.5-flash-lite');

            // The key travels as a header, never a URL query string - a query
            // string ends up verbatim in cURL/Guzzle exception messages (and
            // any proxy/access log along the way), which is exactly how a key
            // leaks into storage/logs/laravel.log the first time a request
            // fails for any reason.
            //
            // This network's path to Google is flaky specifically *after* the
            // TCP/TLS handshake completes - connect succeeds fast, then the
            // response hangs with 0 bytes received until the full timeout,
            // which is the classic symptom of a lost HTTP/2 frame with no
            // clean reset on an unstable link. Forcing HTTP/1.1 (a fresh
            // connection per request instead of one multiplexed stream that
            // can stall entirely) plus IPv4 and a short connect cap are the
            // standard mitigations.
            //
            // The retry itself only fires for that kind of network-level
            // flakiness (a thrown ConnectionException) or a genuine 5xx on
            // Gemini's side - never for a 429/4xx. Those fail identically on
            // every attempt, so retrying just burns through the quota three
            // times as fast for the exact same outcome; `throw: false` keeps
            // a rejected-but-answered request (e.g. a 429) flowing into the
            // ordinary "not successful" branch below instead of being turned
            // into a generic exception that loses the real status/body.
            $response = Http::timeout(20)
                ->connectTimeout(8)
                ->retry(2, 500, function ($exception) {
                    if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                        return true;
                    }

                    return $exception instanceof \Illuminate\Http\Client\RequestException
                        && $exception->response->serverError();
                }, throw: false)
                ->withOptions(['curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                ]])
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                    [
                        'systemInstruction' => [
                            'parts' => [['text' => $this->persona()]],
                        ],
                        'contents' => [
                            ['role' => 'user', 'parts' => [['text' => $this->buildPrompt($message)]]],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.6,
                            'maxOutputTokens' => 800,
                        ],
                    ]
                );

            if (!$response->successful()) {
                Log::warning('Nova AI request failed', [
                    'status' => $response->status(),
                    'body' => $this->redactKey($response->body(), $apiKey),
                ]);

                if ($response->status() === 429) {
                    return "Nova's Gemini quota is exhausted for now (429 from the API). Wait a bit before asking again, or check the plan/billing on the Gemini API key.";
                }

                return "Nova couldn't reach the analysis engine just now (the request failed). Try again in a moment.";
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (empty(trim((string) $text))) {
                Log::warning('Nova AI returned an empty response', ['raw' => $response->json()]);

                return "Nova didn't get a usable answer that time - try rephrasing the question.";
            }

            return trim($text);
        } catch (\Throwable $e) {
            // Belt-and-braces: even with the key out of the URL, never let a
            // raw exception message (which can echo request details) reach
            // the log without a pass through the redactor first.
            Log::warning('Nova AI errored', ['message' => $this->redactKey($e->getMessage(), $apiKey)]);

            return 'Nova hit an unexpected error reaching the analysis engine. Try again shortly.';
        }
    }

    /** Strips a literal API key out of any string before it's ever logged. */
    private function redactKey(?string $text, ?string $apiKey): string
    {
        $text = (string) $text;

        return $apiKey ? str_replace($apiKey, '[redacted]', $text) : $text;
    }

    /**
     * Behavior/tone only - never the place for live data, since this is
     * sent once as Gemini's systemInstruction rather than per-question.
     * Lives in resources/prompts/nova-system.md (content, not code) so the
     * prompt can be iterated on without touching this class; memoized in
     * a static property for the lifetime of the process.
     */
    private function persona(): string
    {
        if (self::$cachedPersona !== null) {
            return self::$cachedPersona;
        }

        try {
            $contents = trim(File::get(resource_path('prompts/nova-system.md')));

            if ($contents === '') {
                throw new \RuntimeException('Nova system prompt file is empty.');
            }
        } catch (\Throwable $e) {
            Log::warning('Nova system prompt could not be loaded - using fallback persona', [
                'message' => $e->getMessage(),
            ]);

            $contents = $this->fallbackPersona();
        }

        return self::$cachedPersona = $contents;
    }

    /**
     * Only reached if resources/prompts/nova-system.md is missing, unreadable,
     * or empty - keeps Nova grounded and honest even without her full
     * executive prompt, rather than ever sending Gemini an empty
     * systemInstruction.
     */
    private function fallbackPersona(): string
    {
        return "You are Nova, the executive AI advisor for Laleen Ops. Speak with "
            . "direct, evidence-based executive judgment. Base every figure strictly "
            . "on the supplied business snapshot and never invent one; if data is "
            . "missing, say so plainly. Any commission figure is an estimate, not a "
            . 'posted payroll ledger.';
    }

    private function buildPrompt(string $message): string
    {
        return 'BUSINESS SNAPSHOT (as of ' . now()->format('D, d M Y H:i') . "):\n"
            . $this->buildSnapshot()
            . "\n\nADMIN'S QUESTION:\n{$message}";
    }

    /* ---------------- data gathering ---------------- */

    /**
     * TEMPORARY CURRENT-CAPABILITY BOUNDARY: the "CURRENT DATA BOUNDARIES"
     * section of resources/prompts/nova-system.md tells Nova exactly which
     * sections below she has (still no ad spend, leads/CAC,
     * expenses/profitability, retention, LTV, staff targets/utilization).
     * If this method grows new sections in a later stage, that part of the
     * prompt must be revised to match, or Nova will keep disclaiming data
     * she actually has.
     */
    private function buildSnapshot(): string
    {
        $sections = [
            $this->revenueSection(),
            $this->appointmentFunnelSection(),
            $this->branchFinancialSection(),
            $this->staffPerformanceSection(),
            $this->staffTargetSection(),
            $this->serviceVolumeSection(),
            $this->customerRetentionSection(),
            $this->pendingPackagesSection(),
        ];

        return implode("\n\n", array_filter($sections));
    }

    private function revenueSection(): string
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $weekStart = now()->copy()->subDays(6)->startOfDay();
        $monthStart = now()->copy()->startOfMonth();

        $today = Sale::whereBetween('created_at', [$todayStart, $todayEnd])
            ->selectRaw('branch, COUNT(*) as sales, SUM(total_amount) as revenue')
            ->groupBy('branch')
            ->get();

        $week = (float) Sale::whereBetween('created_at', [$weekStart, $todayEnd])->sum('total_amount');
        $month = (float) Sale::whereBetween('created_at', [$monthStart, $todayEnd])->sum('total_amount');

        $lines = ['REVENUE'];

        if ($today->isEmpty()) {
            $lines[] = '- No sales recorded yet today.';
        }

        foreach ($today as $row) {
            $lines[] = sprintf(
                '- Today, %s: %d sale(s), %.2f QAR',
                self::branchLabel($row->branch),
                $row->sales,
                (float) $row->revenue
            );
        }

        $lines[] = sprintf('- Trailing 7 days (all branches): %.2f QAR', $week);
        $lines[] = sprintf('- Month to date (all branches): %.2f QAR', $month);

        return implode("\n", $lines);
    }

    /**
     * Real, code-enforced appointment status data (see APPOINTMENT_STATUSES)
     * - not an estimate, not the ambiguous appointment-service rows
     * serviceVolumeSection() reads. Windowed on appointment_datetime (when
     * the visit is/was scheduled to happen), not created_at, and capped at
     * now() so a booking scheduled for later this week never counts toward
     * a historical outcome. A pending appointment whose scheduled time has
     * already passed stays counted as pending - it is not reclassified as
     * a no-show, cancellation, or completion just because time has moved on.
     */
    private function appointmentFunnelSection(): string
    {
        $from = now()->copy()->subDays(self::APPOINTMENT_FUNNEL_DAYS - 1)->startOfDay();
        $to = now();

        $rows = Appointment::whereBetween('appointment_datetime', [$from, $to])
            ->selectRaw('branch, status, COUNT(*) as count')
            ->groupBy('branch', 'status')
            ->get();

        $header = 'APPOINTMENT FUNNEL (last ' . self::APPOINTMENT_FUNNEL_DAYS
            . ' days scheduled, ' . $from->format('d M') . ' - ' . $to->format('d M') . ')';

        if ($rows->isEmpty()) {
            return "{$header}\n- No appointments scheduled in this window.";
        }

        $lines = [$header];
        $lines[] = $this->formatFunnelLine('All branches', $rows);

        foreach (self::APPOINTMENT_FUNNEL_BRANCHES as $branch) {
            $branchRows = $rows->where('branch', $branch);

            if ($branchRows->isEmpty()) {
                continue;
            }

            $lines[] = $this->formatFunnelLine(self::branchLabel($branch), $branchRows);
        }

        return implode("\n", $lines);
    }

    /**
     * Counts are the verified fact; the three rates are calculations with
     * explicit, narrow denominators - never invented as 0% when nothing in
     * that population exists yet. Definitions (no established convention
     * existed anywhere else in the app to reuse - see Stage 3A audit):
     *   - cancellation rate = cancelled / total scheduled in the period
     *   - attendance-decision population = arrived + in_progress + completed + no_show
     *     (excludes cancelled and still-unresolved pending)
     *   - show rate = (arrived + in_progress + completed) / attendance-decision population
     *   - no-show rate = no_show / attendance-decision population
     */
    private function formatFunnelLine(string $label, $rows): string
    {
        $counts = array_fill_keys(self::APPOINTMENT_STATUSES, 0);

        foreach ($rows as $row) {
            if (array_key_exists($row->status, $counts)) {
                $counts[$row->status] += (int) $row->count;
            }
        }

        $total = array_sum($counts);
        $showed = $counts['arrived'] + $counts['in_progress'] + $counts['completed'];
        $attendanceDecided = $showed + $counts['no_show'];

        $showRate = $attendanceDecided > 0 ? round($showed / $attendanceDecided * 100, 1) . '%' : 'N/A';
        $noShowRate = $attendanceDecided > 0 ? round($counts['no_show'] / $attendanceDecided * 100, 1) . '%' : 'N/A';
        $cancellationRate = $total > 0 ? round($counts['cancelled'] / $total * 100, 1) . '%' : 'N/A';

        return sprintf(
            '- %s: %d scheduled | %d completed | %d arrived/in-progress | %d cancelled | %d no-show | %d pending'
                . ' - show rate %s, no-show rate %s, cancellation rate %s',
            $label,
            $total,
            $counts['completed'],
            $counts['arrived'] + $counts['in_progress'],
            $counts['cancelled'],
            $counts['no_show'],
            $counts['pending'],
            $showRate,
            $noShowRate,
            $cancellationRate
        );
    }

    /**
     * The same "CRM net profit" calculation already trusted and displayed
     * on the Finance dashboard - BranchFinancialSummaryService, extracted
     * from FinanceController::branchBreakdown() in Stage 3C, not a second,
     * independently-derived figure. Month-to-date to match that
     * dashboard's own default reporting period. Only the two branches the
     * finance dashboard has ever covered are included here - Home Service
     * was never part of that breakdown and is not fabricated in this
     * section either.
     */
    private function branchFinancialSection(): string
    {
        $from = now()->startOfMonth();
        $to = now()->endOfDay();

        $branches = (new BranchFinancialSummaryService())->summarize($from, $to);

        $header = 'BRANCH FINANCIAL PERFORMANCE (month to date, '
            . $from->format('d M') . ' - ' . $to->format('d M') . ')';

        $lines = [$header];

        $combinedSales = 0.0;
        $combinedExpenses = 0.0;

        foreach ($branches as $branch) {
            $lines[] = $this->formatBranchFinancialLine(
                $branch['label'],
                $branch['sales'],
                $branch['expenses'],
                $branch['profit'],
                $branch['margin_percent']
            );
            $combinedSales += $branch['sales'];
            $combinedExpenses += $branch['expenses'];
        }

        $combinedProfit = $combinedSales - $combinedExpenses;
        $combinedMargin = $combinedSales > 0 ? ($combinedProfit / $combinedSales) * 100 : null;
        $lines[] = $this->formatBranchFinancialLine(
            'Combined (Old Airport + Al Wakrah)',
            $combinedSales,
            $combinedExpenses,
            $combinedProfit,
            $combinedMargin
        );

        $lines[] = '- CRM net profit = recorded sales revenue minus recorded expenses in the CRM;'
            . ' no service/product cost data exists to calculate gross margin or contribution margin.';
        $lines[] = '- Home Service is not included in this branch financial breakdown.';

        return implode("\n", $lines);
    }

    private function formatBranchFinancialLine(string $label, float $sales, float $expenses, float $profit, ?float $marginPercent): string
    {
        $marginStr = $marginPercent === null ? 'N/A' : round($marginPercent, 1) . '%';

        return sprintf(
            '- %s: %.2f QAR revenue, %.2f QAR recorded expenses, CRM net profit %.2f QAR, CRM profit margin %s',
            $label,
            $sales,
            $expenses,
            $profit,
            $marginStr
        );
    }

    private function staffPerformanceSection(): string
    {
        $from = now()->copy()->subDays(self::STAFF_PERFORMANCE_DAYS - 1)->startOfDay();
        $to = now()->endOfDay();

        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNotNull('sale_items.staff_id')
            ->where('sale_items.type', 'service')
            ->whereBetween('sales.created_at', [$from, $to])
            ->groupBy('sale_items.staff_id')
            ->selectRaw('sale_items.staff_id, COUNT(*) as services_done, SUM(sale_items.total) as revenue')
            ->orderByDesc('revenue')
            ->get();

        $header = 'STAFF PERFORMANCE (last ' . self::STAFF_PERFORMANCE_DAYS . ' days, revenue attributed per service performed)';

        if ($rows->isEmpty()) {
            return "{$header}\n- No staff-attributed service revenue in this window.";
        }

        $staffById = Staff::whereIn('id', $rows->pluck('staff_id'))->get()->keyBy('id');

        $lines = [$header];
        foreach ($rows as $row) {
            $staff = $staffById->get($row->staff_id);
            $name = $staff->name ?? "Staff #{$row->staff_id}";
            $revenue = (float) $row->revenue;
            $line = sprintf('- %s: %d service(s), %.2f QAR attributed revenue', $name, $row->services_done, $revenue);

            if ($staff && $staff->commission_rate) {
                $estimate = round($revenue * ((float) $staff->commission_rate / 100), 2);
                $line .= sprintf(
                    ' (est. commission at %.1f%%: %.2f QAR, not a posted ledger)',
                    (float) $staff->commission_rate,
                    $estimate
                );
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * NOVA READ-ONLY INTEGRATION RULE: this reads the CRM's existing
     * App\Support\StaffSalesAnalytics directly - the exact class that
     * already powers the "Sales & Upsells"/"Analytics" KPI screens and the
     * dashboard's branch comparison card - without changing it in any way.
     * No third constructor argument is passed, so Nova always inherits
     * whatever StaffSalesAnalytics::DEFAULT_MONTHLY_TARGET actually is
     * rather than hardcoding a copy of it that could drift. Month-to-date
     * to match how both existing callers (UserController::dashboard(),
     * Kpi\StaffSalesController) already use this class by default.
     *
     * This is an UPSELL target only - StaffSalesAnalytics has no concept
     * of total sales, salary, or general productivity, and neither does
     * this section. Rows within a branch are sorted by achievement
     * ascending (lowest first) as the more executive-useful ordering than
     * the class's own alphabetical default - a read-only presentation
     * choice, not a recalculation.
     */
    private function staffTargetSection(): string
    {
        $from = now()->startOfMonth();
        $to = now()->endOfDay();

        $analytics = new StaffSalesAnalytics($from, $to);

        $header = 'STAFF TARGET PERFORMANCE (month to date, upsell target only, '
            . $from->format('d M') . ' - ' . $to->format('d M') . ')';

        $lines = [$header];
        $anyStaff = false;

        foreach (StaffSalesAnalytics::BRANCHES as $key => $label) {
            $staffRows = $analytics->computedStaff($key)->sortBy('pct')->values();

            if ($staffRows->isEmpty()) {
                continue;
            }

            $anyStaff = true;
            $lines[] = $label . ':';

            foreach ($staffRows as $row) {
                $lines[] = $this->formatStaffTargetLine($row);
            }

            $totals = $analytics->totals($key);
            $borderCounts = $staffRows->countBy('border');

            $lines[] = sprintf(
                '- %s summary: %d green / %d amber / %d red, team %.2f QAR of %.2f QAR target (%.1f%%),'
                    . ' top performer %s (%.2f QAR)',
                $label,
                $borderCounts->get('green', 0),
                $borderCounts->get('amber', 0),
                $borderCounts->get('red', 0),
                $totals['team_total'],
                $totals['team_target'],
                $totals['team_pct'],
                $totals['top_performer'],
                $totals['top_performer_amount']
            );
        }

        if (!$anyStaff) {
            $lines[] = '- No staff recorded against Old Airport or Al Wakrah for this period.';
        }

        $lines[] = '- This is the CRM\'s existing UPSELL target only - a flat '
            . StaffSalesAnalytics::DEFAULT_MONTHLY_TARGET . ' QAR/month target per staff member, prorated'
            . ' by days elapsed - never total sales, salary, or overall productivity. Missing this target'
            . ' does not by itself mean someone is a poor performer: apply the usual skill/will/process/'
            . 'opportunity/training/management reasoning before concluding anything about a specific'
            . ' person. It also does not measure attendance, actual hours worked, or opportunity/customer'
            . ' volume - stylists have no clock-in attendance system in this CRM.';

        return implode("\n", $lines);
    }

    private function formatStaffTargetLine(array $row): string
    {
        return sprintf(
            '- %s: %.2f QAR upsell | target %.2f QAR | %.1f%% | gap %.2f QAR | %s',
            $row['name'],
            $row['upsell'],
            $row['prorated_target'],
            $row['pct'],
            $row['gap'],
            strtoupper($row['border'])
        );
    }

    private function serviceVolumeSection(): string
    {
        $from = now()->copy()->subDays(self::SERVICE_COUNT_DAYS - 1)->startOfDay();

        $rows = AppointmentService::where('start_time', '>=', $from)
            ->selectRaw('name, COUNT(*) as count')
            ->groupBy('name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $header = 'SERVICE VOLUME (last ' . self::SERVICE_COUNT_DAYS . ' days, top services by count)';

        if ($rows->isEmpty()) {
            return "{$header}\n- No services logged in this window.";
        }

        $lines = [$header];
        foreach ($rows as $row) {
            $lines[] = sprintf('- %s: %d', $row->name, $row->count);
        }

        return implode("\n", $lines);
    }

    /**
     * NOVA READ-ONLY INTEGRATION RULE: reads Appointment/Sale directly and
     * consumes the CRM's existing, unmodified ClientMaintenancePlanner - see
     * App\NovaAI\Support\CustomerRetentionAnalytics for the exact query
     * definitions (deliberately more precise than CustomerController's own
     * last-visit/LTV queries, which have no status or future-date filter at
     * all - a discovered nuance, not a CRM bug this stage touches).
     * Aggregate-only by design: no customer name, phone, or email ever
     * appears in this section - individual-customer retrieval is explicitly
     * a later, question-aware stage.
     */
    private function customerRetentionSection(): string
    {
        $data = (new CustomerRetentionAnalytics())->summary();

        $lines = ['CUSTOMER & RETENTION INTELLIGENCE'];

        if ($data['customers_with_visit_history'] === 0) {
            $lines[] = '- No customers with a qualifying (non-cancelled, non-no-show, already-happened) visit yet.';
            return implode("\n", $lines);
        }

        $lines[] = sprintf('- Customers with completed visit history: %d', $data['customers_with_visit_history']);
        $lines[] = sprintf(
            '- Repeat customers (2+ qualifying visits): %d (%s of customers with visit history)',
            $data['repeat_customers'],
            $data['repeat_rate_percent'] === null ? 'N/A' : $data['repeat_rate_percent'] . '%'
        );
        $lines[] = sprintf(
            '- Revenue-based lifetime customer spend (all-time, not profit): %.2f QAR total across %d customers with a sale, average %s',
            $data['total_lifetime_spend'],
            $data['customers_with_spend'],
            $data['average_lifetime_spend'] === null ? 'N/A' : number_format($data['average_lifetime_spend'], 2) . ' QAR/customer'
        );
        $lines[] = sprintf(
            '- Repeat-customer revenue: %.2f QAR (%s of total lifetime spend)',
            $data['repeat_customer_revenue'],
            $data['repeat_revenue_share_percent'] === null ? 'N/A' : $data['repeat_revenue_share_percent'] . '%'
        );
        $lines[] = sprintf(
            '- Rebooking/maintenance (distinct customers, any tracked service): %d overdue | %d due soon | %d upcoming',
            $data['overdue_customers'],
            $data['due_soon_customers'],
            $data['upcoming_customers']
        );
        $lines[] = sprintf(
            '- Dormant customers (no qualifying visit in %d+ days - Nova-defined threshold, not an established CRM rule): %d, of which %d have prior spend and are potentially reactivatable',
            CustomerRetentionAnalytics::DORMANT_THRESHOLD_DAYS,
            $data['dormant_customers'],
            $data['potentially_reactivatable']
        );

        $branch = $data['branch_distribution'];
        $lines[] = sprintf(
            '- Branch history (2+ visits required to classify): %d primarily Old Airport, %d primarily Al Wakrah, %d primarily Home Service, %d mixed, %d insufficient history (fewer than 2 visits)',
            $branch['old_airport'],
            $branch['wakrah'],
            $branch['home_service'],
            $branch['mixed'],
            $branch['insufficient_history']
        );

        if (!empty($data['top_maintenance_services'])) {
            $lines[] = 'Top maintenance opportunities (by service, overdue + due soon):';
            foreach ($data['top_maintenance_services'] as $service) {
                $lines[] = sprintf('- %s: %d overdue / %d due soon', $service['service'], $service['overdue'], $service['due_soon']);
            }
        }

        return implode("\n", $lines);
    }

    private function pendingPackagesSection(): string
    {
        $packages = ClientPackage::query()
            ->where('status', 'active')
            ->where('expires_at', '>=', now())
            ->with('customer')
            ->get()
            ->filter(fn ($pkg) => $pkg->remaining_count > 0)
            ->sortBy('expires_at')
            ->take(self::PACKAGE_LOOKAHEAD);

        if ($packages->isEmpty()) {
            return "UNREDEEMED COMBO PACKAGE BALANCES\n- No client currently has an unused package balance.";
        }

        $lines = ['UNREDEEMED COMBO PACKAGE BALANCES (soonest-expiring first)'];
        foreach ($packages as $pkg) {
            $daysLeft = max(0, (int) floor(now()->diffInDays($pkg->expires_at, false)));
            $lines[] = sprintf(
                '- %s: %d service(s) left on "%s", expires %s (%dd left)',
                optional($pkg->customer)->name ?? 'Unknown client',
                $pkg->remaining_count,
                $pkg->combo_name,
                $pkg->expires_at->format('d M'),
                $daysLeft
            );
        }

        return implode("\n", $lines);
    }

    private static function branchLabel(?string $branch): string
    {
        return match ($branch) {
            'old_airport' => 'Old Airport',
            'wakrah' => 'Al Wakrah',
            'home_service' => 'Home Service',
            default => $branch ?: 'Unknown branch',
        };
    }
}
