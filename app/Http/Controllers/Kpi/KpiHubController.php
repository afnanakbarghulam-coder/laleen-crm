<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\SocialContentPlan;
use App\Models\SocialLateReplyLog;
use App\Support\StaffKpiScorecardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class KpiHubController extends Controller
{
    public const AYAN_BRANCH_AD_REVENUE_TARGET_QAR = StaffKpiScorecardService::AYAN_BRANCH_AD_REVENUE_TARGET_QAR;
    public const AYAN_COMBINED_AD_REVENUE_TARGET_QAR = StaffKpiScorecardService::AYAN_COMBINED_AD_REVENUE_TARGET_QAR;
    public const AYAN_AD_CONVERSION_TARGET_PCT = StaffKpiScorecardService::AYAN_AD_CONVERSION_TARGET_PCT;

    public const AGENT_DAILY_BOOKING_QUOTA = StaffKpiScorecardService::AGENT_DAILY_BOOKING_QUOTA;
    public const LADEN_DAILY_BOOKING_QUOTA = StaffKpiScorecardService::LADEN_DAILY_BOOKING_QUOTA;
    public const LADEN_CHAT_EVAL_TARGET_PCT = StaffKpiScorecardService::LADEN_CHAT_EVAL_TARGET_PCT;
    public const LADEN_LEAD_CONVERSION_TARGET_PCT = StaffKpiScorecardService::LADEN_LEAD_CONVERSION_TARGET_PCT;
    public const LADEN_COMPLAINT_SLA_HOURS = StaffKpiScorecardService::LADEN_COMPLAINT_SLA_HOURS;

    public const SANA_WEEKLY_REELS_TARGET = StaffKpiScorecardService::SANA_WEEKLY_REELS_TARGET;
    public const SANA_WEEKLY_STORIES_TARGET = StaffKpiScorecardService::SANA_WEEKLY_STORIES_TARGET;

    public const BRANCH_STAFF_EVAL_COMPLIANCE_TARGET_PCT = StaffKpiScorecardService::BRANCH_STAFF_EVAL_COMPLIANCE_TARGET_PCT;
    public const BRANCH_MYSTERY_SHOPPER_TARGET_PCT = StaffKpiScorecardService::BRANCH_MYSTERY_SHOPPER_TARGET_PCT;

    public function __construct(
        private readonly StaffKpiScorecardService $scorecardService
    ) {}

    public function index(Request $request)
    {
        if (strtolower(trim((string) $request->query('tab', ''))) === 'operations') {
            return redirect()->route('operations.index');
        }

        $nowQatar = now('Asia/Qatar');
        $rawMonth = trim((string) $request->query('month', $nowQatar->format('Y-m')));
        if (!preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
            $rawMonth = $nowQatar->format('Y-m');
        }

        $monthStart = Carbon::createFromFormat('Y-m-d', $rawMonth . '-01', 'Asia/Qatar')->startOfMonth()->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
        $isCurrentMonth = ($rawMonth === $nowQatar->format('Y-m'));

        $scorecard = $this->buildExecutiveScorecard($rawMonth, $monthStart, $monthEnd, $isCurrentMonth);
        $scorecardViewModels = $this->scorecardService->buildAllExecutiveViewModels($scorecard, $request->user());

        return view('kpi.hub', [
            'selectedMonth' => $rawMonth,
            'selectedMonthLabel' => $monthStart->format('F Y'),
            'isCurrentMonth' => $isCurrentMonth,
            'periodRangeLabel' => $scorecard['period_range_label'],
            'shortPeriodBadge' => $scorecard['short_period_badge'],
            'daysElapsed' => $scorecard['days_elapsed'],
            'daysInMonth' => $scorecard['days_in_month'],
            'scorecard' => $scorecard,
            'scorecardViewModels' => $scorecardViewModels,
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
                $inMonthLegacyRow = $this->scorecardService->inMonthContentPlans($monthStart, $monthEnd)
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

            foreach ($this->scorecardService->inMonthContentPlans($monthStart, $monthEnd) as $planRow) {
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
            + (int) $this->scorecardService->inMonthContentPlans($monthStart, $monthEnd)->sum('late_replies_count');

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
        return $this->scorecardService->buildExecutiveScorecard($selectedMonth, $monthStart, $monthEnd, $isCurrentMonth);
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
