<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\SocialContentPlan;
use App\Models\SocialContentWeek;
use App\Models\SocialLateReplyLog;
use App\Models\User;
use App\Services\WeeklyContentPlanPdfParser;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

class ContentCalendarController extends Controller
{
    public function index(Request $request)
    {
        $weekStart = $request->filled('week')
            ? Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        $weekStartStr = $weekStart->toDateString();

        foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
            try {
                SocialContentPlan::firstOrCreate(
                    [
                        'week_start_date' => $weekStartStr,
                        'day_of_week' => $dayName,
                    ],
                    [
                        'date' => $weekStart->copy()->addDays($idx)->toDateString(),
                        'branch' => 'All Branches',
                        'target_branch' => 'All Branches',
                        'status' => 'pending',
                    ]
                );
            } catch (QueryException $e) {
                if (!str_contains($e->getMessage(), 'UNIQUE constraint failed') && !str_contains($e->getMessage(), 'Duplicate entry')) {
                    throw $e;
                }
            }
        }

        $plansByDay = SocialContentPlan::where('week_start_date', $weekStartStr)
            ->get()
            ->keyBy(fn (SocialContentPlan $p) => SocialContentPlan::normalizeDayName($p->day_of_week));

        $days = collect(SocialContentPlan::DAYS_OF_WEEK)->map(fn ($dayName) => $plansByDay->get($dayName));

        $weekMeta = null;
        if (Schema::hasTable('social_content_weeks')) {
            $weekMeta = SocialContentWeek::where('week_start_date', $weekStartStr)->first();
        }

        $staff = User::orderBy('name')->get(['id', 'name']);
        $weekEnd = $weekStart->copy()->addDays(6);

        $referenceMonthDate = $weekEnd->month !== $weekStart->month && now('Asia/Qatar')->format('Y-m') === $weekEnd->format('Y-m')
            ? $weekEnd->copy()
            : $weekStart->copy();
        $monthStart = $referenceMonthDate->copy()->startOfMonth()->startOfDay();
        $monthEnd = $referenceMonthDate->copy()->endOfMonth()->endOfDay();

        $weekLateReplyLogs = collect();
        $monthLateReplyLogs = collect();
        if (Schema::hasTable('social_late_reply_logs')) {
            $weekLateReplyLogs = SocialLateReplyLog::with('logger:id,name')
                ->whereBetween('incident_at', [$weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()])
                ->orderByDesc('incident_at')
                ->orderByDesc('id')
                ->get();

            $monthLateReplyLogs = SocialLateReplyLog::with('logger:id,name')
                ->whereBetween('incident_at', [$monthStart, $monthEnd])
                ->orderByDesc('incident_at')
                ->orderByDesc('id')
                ->get();
        }

        return view('kpi.content-calendar.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekMeta' => $weekMeta,
            'days' => $days,
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'currentWeek' => $weekStartStr,
            'staff' => $staff,
            'weeklyStats' => $this->weeklyStats($weekStartStr),
            'weekLateReplyLogs' => $weekLateReplyLogs,
            'monthLateReplyLogs' => $monthLateReplyLogs,
            'lateRepliesMonthLabel' => $monthStart->format('F Y'),
        ]);
    }

    /**
     * Upload a 1-Week Social Media Content Plan PDF and extract structured metadata + 7 daily deliverables via Gemini.
     */
    public function importPdf(Request $request, WeeklyContentPlanPdfParser $parser)
    {
        $validated = $request->validate([
            'week_start_date' => 'required|date',
            'plan_pdf' => 'required_without:pdf|nullable|file|mimes:pdf|max:10240',
            'pdf' => 'required_without:plan_pdf|nullable|file|mimes:pdf|max:10240',
            'auto_detect_week' => 'nullable|boolean',
        ]);

        $pdfFile = $request->file('plan_pdf') ?? $request->file('pdf');

        try {
            $extracted = $parser->parsePdf($pdfFile);
        } catch (Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        $selectedMonday = Carbon::parse($validated['week_start_date'])->startOfWeek(Carbon::MONDAY);
        $autoDetect = $request->boolean('auto_detect_week', true);

        if ($autoDetect && !empty($extracted['explicit_week_start_date'])) {
            $targetMonday = Carbon::parse($extracted['explicit_week_start_date'])->startOfWeek(Carbon::MONDAY);
        } else {
            $targetMonday = $selectedMonday;
        }

        $weekStartStr = $targetMonday->toDateString();
        $weekEnd = $targetMonday->copy()->addDays(6);
        $rangeLabel = $targetMonday->format('j M') . ' – ' . $weekEnd->format('j M, Y');

        $weekMeta = null;

        DB::transaction(function () use ($weekStartStr, $targetMonday, $extracted, $pdfFile, &$weekMeta) {
            if (Schema::hasTable('social_content_weeks')) {
                $existingMeta = SocialContentWeek::where('week_start_date', $weekStartStr)->first();

                $weekMeta = SocialContentWeek::updateOrCreate(
                    ['week_start_date' => $weekStartStr],
                    [
                        'strategy_objective' => $extracted['strategy_objective'] ?? $existingMeta?->strategy_objective,
                        'pro_tips' => !empty($extracted['pro_tips']) ? $extracted['pro_tips'] : ($existingMeta?->pro_tips ?? []),
                        'hashtags' => $extracted['hashtags'] ?? $existingMeta?->hashtags,
                        'source_pdf_name' => $pdfFile->getClientOriginalName(),
                        'uploaded_at' => now(),
                        'uploaded_by' => auth()->id(),
                    ]
                );
            }

            foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
                $dayData = $extracted['days'][$dayName] ?? [];

                $existing = SocialContentPlan::where([
                    'week_start_date' => $weekStartStr,
                    'day_of_week' => $dayName,
                ])->first();

                $preservedBranch = $existing ? $existing->effectiveBranch() : 'All Branches';
                $preservedAssignedTo = $existing?->assigned_to;
                $preservedAssignedUserId = $existing?->assigned_user_id;

                $alreadyPosted = $existing && $existing->isCompleted();
                $preservedProofUrl = $existing?->effectiveProofUrl() ?: null;
                $preservedStoryVerified = (bool) ($existing?->is_story_verified ?? false);
                $preservedStatus = $alreadyPosted ? 'posted' : ($existing?->status ?: 'pending');
                $preservedPostedAt = $existing?->posted_at ?? $existing?->reel_posted_at ?? $existing?->stories_posted_at;

                $plan = SocialContentPlan::firstOrNew([
                    'week_start_date' => $weekStartStr,
                    'day_of_week' => $dayName,
                ]);

                $plan->date = $targetMonday->copy()->addDays($idx)->toDateString();
                $plan->branch = $preservedBranch;
                $plan->target_branch = $preservedBranch === 'Al Wakrah' ? 'Wakrah' : $preservedBranch;
                $plan->assigned_to = $preservedAssignedTo;
                $plan->assigned_user_id = $preservedAssignedUserId;

                if (filled($dayData['focus'] ?? null)) {
                    $plan->focus = $dayData['focus'];
                }
                if (filled($dayData['format'] ?? null)) {
                    $plan->format = $dayData['format'];
                }
                if (filled($dayData['visual_concept'] ?? null)) {
                    $plan->visual_concept = $dayData['visual_concept'];
                }
                if (filled($dayData['caption_cta'] ?? null)) {
                    $plan->caption_cta = $dayData['caption_cta'];
                }

                $plan->proof_url = $preservedProofUrl;
                $plan->is_story_verified = $preservedStoryVerified;
                $plan->status = $preservedStatus;
                $plan->posted_at = $preservedPostedAt;

                $this->syncLegacyColumns($plan);
                $plan->save();
            }
        });

        $message = "Content plan imported for {$rangeLabel}";

        if ($request->expectsJson()) {
            session()->flash('success', $message);

            return response()->json([
                'message' => $message,
                'week_start_date' => $weekStartStr,
                'redirect_url' => route('kpi.content-calendar.index', ['week' => $weekStartStr]),
                'week_meta' => $weekMeta,
                'weekly_stats' => $this->weeklyStats($weekStartStr),
            ]);
        }

        return redirect()
            ->route('kpi.content-calendar.index', ['week' => $weekStartStr])
            ->with('success', $message);
    }

    /**
     * Permanently clear weekly metadata and all 7 daily deliverable slots strictly for the specified week_start_date.
     */
    public function destroyWeek(Request $request)
    {
        $validated = $request->validate([
            'week_start_date' => 'required|date',
        ]);

        $targetMonday = Carbon::parse($validated['week_start_date'])->startOfWeek(Carbon::MONDAY);
        $weekStartStr = $targetMonday->toDateString();
        $weekEnd = $targetMonday->copy()->addDays(6);
        $rangeLabel = $targetMonday->format('j M') . ' – ' . $weekEnd->format('j M, Y');

        DB::transaction(function () use ($weekStartStr) {
            if (Schema::hasTable('social_content_weeks')) {
                SocialContentWeek::where('week_start_date', $weekStartStr)->delete();
            }

            SocialContentPlan::where('week_start_date', $weekStartStr)->delete();
        });

        $flashMessage = "Success: Content calendar for {$rangeLabel} has been deleted.";

        if ($request->expectsJson()) {
            session()->flash('success', $flashMessage);

            return response()->json([
                'message' => $flashMessage,
                'week_start_date' => $weekStartStr,
                'redirect_url' => route('kpi.content-calendar.index', ['week' => $weekStartStr]),
            ]);
        }

        return redirect()
            ->route('kpi.content-calendar.index', ['week' => $weekStartStr])
            ->with('success', $flashMessage);
    }

    /**
     * Inline update for weekly strategy objective, pro-tips, and hashtags.
     */
    public function updateWeekMeta(Request $request)
    {
        $validated = $request->validate([
            'week_start_date' => 'required|date',
            'strategy_objective' => 'nullable|string|max:10000',
            'hashtags' => 'nullable|string|max:3000',
            'pro_tips' => 'nullable',
        ]);

        $weekStartStr = Carbon::parse($validated['week_start_date'])->startOfWeek(Carbon::MONDAY)->toDateString();

        $proTips = null;
        $hasProTipsKey = $request->has('pro_tips');
        if ($hasProTipsKey) {
            $rawTips = $request->input('pro_tips');
            if (is_array($rawTips)) {
                $proTips = [];
                foreach ($rawTips as $entry) {
                    if (is_array($entry)) {
                        $title = trim((string) ($entry['title'] ?? ''));
                        $body = trim((string) ($entry['body'] ?? ''));
                        if ($title !== '' || $body !== '') {
                            $proTips[] = [
                                'title' => $title !== '' ? $title : 'Pro-Tip',
                                'body' => $body,
                            ];
                        }
                    } elseif (is_string($entry) && trim($entry) !== '') {
                        $proTips[] = ['title' => 'Pro-Tip', 'body' => trim($entry)];
                    }
                }
            } elseif (is_string($rawTips)) {
                $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawTips) ?: [])));
                $proTips = [];
                foreach ($lines as $line) {
                    if (str_contains($line, ':')) {
                        [$t, $b] = explode(':', $line, 2);
                        $proTips[] = ['title' => trim($t) ?: 'Pro-Tip', 'body' => trim($b)];
                    } else {
                        $proTips[] = ['title' => 'Pro-Tip', 'body' => $line];
                    }
                }
            }
        }

        $existing = SocialContentWeek::where('week_start_date', $weekStartStr)->first();

        $payload = [];
        if ($request->exists('strategy_objective')) {
            $val = trim((string) ($validated['strategy_objective'] ?? ''));
            $payload['strategy_objective'] = $val !== '' ? $val : null;
        }
        if ($request->exists('hashtags')) {
            $val = trim((string) ($validated['hashtags'] ?? ''));
            $payload['hashtags'] = $val !== '' ? $val : null;
        }
        if ($hasProTipsKey) {
            $payload['pro_tips'] = $proTips ?? [];
        } elseif (!$existing) {
            $payload['pro_tips'] = [];
        }

        $meta = SocialContentWeek::updateOrCreate(
            ['week_start_date' => $weekStartStr],
            $payload
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Weekly strategy saved.',
                'week_meta' => [
                    'strategy_objective' => $meta->strategy_objective,
                    'hashtags' => $meta->hashtags,
                    'pro_tips' => $meta->normalizedProTips(),
                ],
            ]);
        }

        return redirect()
            ->route('kpi.content-calendar.index', ['week' => $weekStartStr])
            ->with('success', 'Weekly strategy saved.');
    }

    /**
     * Inline update for a single daily deliverable row (focus, format, visual_concept, caption_cta, branch, assigned_to, proof_url, status).
     */
    public function updateDayInline(Request $request, SocialContentPlan $plan)
    {
        $validated = $request->validate([
            'branch' => ['nullable', Rule::in(SocialContentPlan::BRANCHES)],
            'target_branch' => ['nullable', Rule::in(SocialContentPlan::BRANCHES)],
            'assigned_user_id' => 'nullable|integer|exists:users,id',
            'assigned_to' => 'nullable|string|max:255',
            'focus' => 'nullable|string|max:255',
            'format' => 'nullable|string|max:120',
            'visual_concept' => 'nullable|string|max:5000',
            'caption_cta' => 'nullable|string|max:5000',
            'proof_url' => 'nullable|string|max:2048',
            'is_story_verified' => 'nullable|boolean',
            'status' => ['nullable', Rule::in(SocialContentPlan::STATUSES)],
        ]);

        if ($request->exists('branch') || $request->exists('target_branch')) {
            $rawBranch = $validated['branch'] ?? $validated['target_branch'] ?? 'All Branches';
            $normalizedBranch = SocialContentPlan::normalizeBranch($rawBranch);
            $plan->branch = $normalizedBranch;
            $plan->target_branch = $normalizedBranch === 'Al Wakrah' ? 'Wakrah' : $normalizedBranch;
        }

        if ($request->exists('assigned_user_id')) {
            $uid = $validated['assigned_user_id'] ?? null;
            $plan->assigned_user_id = $uid ?: null;
            $plan->assigned_to = $uid ? User::where('id', $uid)->value('name') : null;
        } elseif ($request->exists('assigned_to')) {
            $assigneeName = trim((string) ($validated['assigned_to'] ?? ''));
            $plan->assigned_to = $assigneeName !== '' ? $assigneeName : null;
            $plan->assigned_user_id = $assigneeName !== ''
                ? User::where('name', $assigneeName)->value('id')
                : null;
        }

        if ($request->exists('focus')) {
            $val = trim((string) ($validated['focus'] ?? ''));
            $plan->focus = $val !== '' ? $val : null;
        }

        if ($request->exists('format')) {
            $val = trim((string) ($validated['format'] ?? ''));
            $plan->format = $val !== '' ? $val : null;
            if (!$plan->isStoryFormat() && $plan->is_story_verified && empty($plan->proof_url)) {
                $plan->is_story_verified = false;
                $plan->status = 'pending';
                $plan->posted_at = null;
            }
        }

        if ($request->exists('visual_concept')) {
            $val = trim((string) ($validated['visual_concept'] ?? ''));
            $plan->visual_concept = $val !== '' ? $val : null;
        }

        if ($request->exists('caption_cta')) {
            $val = trim((string) ($validated['caption_cta'] ?? ''));
            $plan->caption_cta = $val !== '' ? $val : null;
        }

        if ($request->exists('proof_url')) {
            $rawProof = trim((string) ($validated['proof_url'] ?? ''));
            if ($rawProof === '') {
                $plan->proof_url = null;
            } else {
                $normalizedUrl = $this->normalizeProofUrl($rawProof);
                if ($normalizedUrl === null) {
                    return response()->json([
                        'message' => 'Please enter a valid live URL (e.g., https://instagram.com/reel/...).',
                    ], 422);
                }
                $plan->proof_url = $normalizedUrl;
            }
        }

        if ($request->exists('is_story_verified')) {
            $wantsStoryVerified = $request->boolean('is_story_verified');
            if ($wantsStoryVerified && !$plan->isStoryFormat()) {
                return response()->json([
                    'message' => 'Story verification is only available for Story formats. Please attach a live Post/Reel URL.',
                ], 422);
            }
            $plan->is_story_verified = $wantsStoryVerified;
        }

        $hasValidProof = filled($plan->proof_url) && filter_var($plan->proof_url, FILTER_VALIDATE_URL) !== false;
        $hasStoryProof = $plan->isStoryFormat() && (bool) $plan->is_story_verified;

        if ($request->exists('status')) {
            if ($validated['status'] === 'posted') {
                if (!$hasValidProof && !$hasStoryProof) {
                    return response()->json([
                        'message' => $this->missingProofMessage($plan),
                    ], 422);
                }
                $plan->status = 'posted';
                $plan->posted_at = $plan->posted_at ?? now();
            } else {
                $plan->status = 'pending';
                $plan->posted_at = null;
            }
        } elseif ($request->exists('proof_url') || $request->exists('is_story_verified')) {
            // Entering a valid live URL or checking Story Verified automatically unlocks/marks Posted
            if ($hasValidProof || $hasStoryProof) {
                $plan->status = 'posted';
                $plan->posted_at = $plan->posted_at ?? now();
            } else {
                $plan->status = 'pending';
                $plan->posted_at = null;
            }
        }

        $this->syncLegacyColumns($plan);
        $plan->save();

        return response()->json([
            'message' => 'Saved.',
            'plan' => $this->serializePlan($plan),
            'weekly_stats' => $this->weeklyStats($plan->week_start_date),
        ]);
    }

    public function updateWeek(Request $request)
    {
        $validated = $request->validate([
            'week_start_date' => 'required|date',
            'days' => 'nullable|array',
            'days.*.branch' => ['nullable', Rule::in(SocialContentPlan::BRANCHES)],
            'days.*.target_branch' => ['nullable', Rule::in(SocialContentPlan::BRANCHES)],
            'days.*.assigned_user_id' => 'nullable|integer|exists:users,id',
            'days.*.assigned_to' => 'nullable|string|max:255',
            'days.*.focus' => 'nullable|string|max:255',
            'days.*.format' => 'nullable|string|max:120',
            'days.*.visual_concept' => 'nullable|string|max:5000',
            'days.*.caption_cta' => 'nullable|string|max:5000',
            'days.*.proof_url' => 'nullable|string|max:2048',
            'days.*.is_story_verified' => 'nullable|boolean',
            'days.*.status' => ['nullable', Rule::in(SocialContentPlan::STATUSES)],
            'days.*.reel_pillar' => ['nullable', Rule::in(SocialContentPlan::REEL_PILLARS)],
            'days.*.reel_content' => 'nullable|string|max:5000',
            'days.*.reel_link' => 'nullable|string|max:2048',
            'days.*.stories_content' => 'nullable|string|max:5000',
            'days.*.is_reel_posted' => 'nullable|boolean',
            'days.*.are_stories_posted' => 'nullable|boolean',
            'days.*.is_reel_skipped' => 'nullable|boolean',
        ]);

        $weekStartCarbon = Carbon::parse($validated['week_start_date'])->startOfWeek(Carbon::MONDAY);
        $weekStart = $weekStartCarbon->toDateString();
        $days = $validated['days'] ?? [];
        $gateErrors = [];

        $requestedSkipDays = collect(SocialContentPlan::DAYS_OF_WEEK)
            ->filter(fn ($dayName) => $request->boolean("days.{$dayName}.is_reel_skipped"));

        if ($requestedSkipDays->count() > SocialContentPlan::MAX_REEL_SKIP_DAYS) {
            $gateErrors[] = "Weekly target is 4 Reels — you can only mark up to 3 days per week as 'No Reel Scheduled'.";
        }

        $allowedSkipDays = $requestedSkipDays->take(SocialContentPlan::MAX_REEL_SKIP_DAYS);

        DB::transaction(function () use ($weekStart, $weekStartCarbon, $days, $request, $allowedSkipDays, &$gateErrors) {
            foreach (SocialContentPlan::DAYS_OF_WEEK as $idx => $dayName) {
                $content = $days[$dayName] ?? [];
                $existing = SocialContentPlan::where(['week_start_date' => $weekStart, 'day_of_week' => $dayName])->first();

                $usesUnifiedFields = array_key_exists('focus', $content)
                    || array_key_exists('format', $content)
                    || array_key_exists('visual_concept', $content)
                    || array_key_exists('caption_cta', $content)
                    || array_key_exists('proof_url', $content)
                    || array_key_exists('status', $content)
                    || array_key_exists('is_story_verified', $content);

                $rawBranch = $content['branch'] ?? $content['target_branch'] ?? ($existing?->effectiveBranch() ?? 'All Branches');
                $normalizedBranch = SocialContentPlan::normalizeBranch($rawBranch);
                $targetBranch = $normalizedBranch === 'Al Wakrah' ? 'Wakrah' : $normalizedBranch;

                if ($usesUnifiedFields) {
                    $plan = $existing ?: new SocialContentPlan([
                        'week_start_date' => $weekStart,
                        'day_of_week' => $dayName,
                    ]);

                    $plan->date = $weekStartCarbon->copy()->addDays($idx)->toDateString();
                    $plan->branch = $normalizedBranch;
                    $plan->target_branch = $targetBranch;
                    $plan->assigned_to = $content['assigned_to'] ?? $plan->assigned_to;
                    $plan->assigned_user_id = $content['assigned_user_id'] ?? $plan->assigned_user_id;
                    $plan->focus = $content['focus'] ?? $plan->focus;
                    $plan->format = $content['format'] ?? $plan->format;
                    $plan->visual_concept = $content['visual_concept'] ?? $plan->visual_concept;
                    $plan->caption_cta = $content['caption_cta'] ?? $plan->caption_cta;

                    $rawProof = trim((string) ($content['proof_url'] ?? ''));
                    $plan->proof_url = $rawProof !== '' ? $this->normalizeProofUrl($rawProof) : null;
                    $plan->is_story_verified = $plan->isStoryFormat() && $request->boolean("days.{$dayName}.is_story_verified");

                    $wantsPosted = ($content['status'] ?? null) === 'posted'
                        || filled($plan->proof_url)
                        || $plan->is_story_verified;

                    $hasValidProof = filled($plan->proof_url) && filter_var($plan->proof_url, FILTER_VALIDATE_URL) !== false;
                    $hasStoryProof = $plan->isStoryFormat() && $plan->is_story_verified;

                    if (($content['status'] ?? null) === 'posted' && !$hasValidProof && !$hasStoryProof) {
                        $plan->status = 'pending';
                        $plan->posted_at = null;
                        $gateErrors[] = $this->missingProofMessage($plan);
                    } elseif ($wantsPosted && ($hasValidProof || $hasStoryProof)) {
                        $plan->status = 'posted';
                        $plan->posted_at = $plan->posted_at ?? now();
                    } else {
                        $plan->status = 'pending';
                        $plan->posted_at = null;
                    }

                    $this->syncLegacyColumns($plan);
                    $plan->save();
                    continue;
                }

                // Legacy form submission compatibility
                $reelContent = $content['reel_content'] ?? null;
                $reelLink = $content['reel_link'] ?? null;
                $storiesContent = $content['stories_content'] ?? null;

                $reelSkipped = $allowedSkipDays->contains($dayName);

                $wantsReelPosted = $request->boolean("days.{$dayName}.is_reel_posted");
                $reelPosted = $wantsReelPosted && !$reelSkipped;
                if (!$reelSkipped && $wantsReelPosted && (empty($reelContent) || empty($reelLink))) {
                    $reelPosted = false;
                    $gateErrors[] = "Gatekeeper: {$dayName}'s Reel cannot be marked Posted without both a Reel Concept and a Reel Link (Drive/Live URL).";
                }

                $wantsStoriesPosted = $request->boolean("days.{$dayName}.are_stories_posted");
                $storiesPosted = $wantsStoriesPosted;
                if ($wantsStoriesPosted && empty($storiesContent)) {
                    $storiesPosted = false;
                    $gateErrors[] = "Gatekeeper: {$dayName}'s Stories cannot be marked Posted without Daily Stories content.";
                }

                SocialContentPlan::updateOrCreate(
                    ['week_start_date' => $weekStart, 'day_of_week' => $dayName],
                    [
                        'date' => $weekStartCarbon->copy()->addDays($idx)->toDateString(),
                        'branch' => $normalizedBranch,
                        'target_branch' => $targetBranch,
                        'assigned_to' => $content['assigned_to'] ?? null,
                        'reel_pillar' => $content['reel_pillar'] ?? null,
                        'reel_content' => $reelContent,
                        'reel_link' => $reelLink,
                        'stories_content' => $storiesContent,
                        'visual_concept' => $reelContent ?: $storiesContent,
                        'proof_url' => $reelLink,
                        'status' => ($reelPosted || ($storiesPosted && empty($reelContent))) ? 'posted' : 'pending',
                        'is_reel_skipped' => $reelSkipped,
                        'is_reel_posted' => $reelPosted,
                        'are_stories_posted' => $storiesPosted,
                        'reel_posted_at' => $reelPosted
                            ? ($existing && $existing->is_reel_posted ? $existing->reel_posted_at : now())
                            : null,
                        'stories_posted_at' => $storiesPosted
                            ? ($existing && $existing->are_stories_posted ? $existing->stories_posted_at : now())
                            : null,
                    ]
                );
            }
        });

        if (!empty($gateErrors)) {
            return redirect()
                ->route('kpi.content-calendar.index', ['week' => $weekStart])
                ->with('error', implode(' ', array_unique($gateErrors)));
        }

        return redirect()
            ->route('kpi.content-calendar.index', ['week' => $weekStart])
            ->with('success', 'Weekly content plan saved.');
    }

    /**
     * Toggle Posted / Story Verified status for a daily deliverable row (with legacy 'reel' / 'stories' / 'skip_reel' compatibility).
     */
    public function toggleStatus(Request $request, SocialContentPlan $plan)
    {
        $validated = $request->validate([
            'field' => ['nullable', Rule::in(['posted', 'status', 'story_verified', 'reel', 'stories', 'skip_reel'])],
            'proof_url' => 'nullable|string|max:2048',
            'is_story_verified' => 'nullable|boolean',
            'format' => 'nullable|string|max:120',
            'reel_content' => 'nullable|string|max:5000',
            'reel_link' => 'nullable|string|max:2048',
            'stories_content' => 'nullable|string|max:5000',
        ]);

        $field = $validated['field'] ?? 'posted';

        // Unified status / story_verified toggle
        if (in_array($field, ['posted', 'status', 'story_verified'], true)) {
            if ($request->exists('format')) {
                $plan->format = trim((string) $validated['format']) ?: $plan->format;
            }

            if ($request->exists('proof_url')) {
                $rawProof = trim((string) ($validated['proof_url'] ?? ''));
                if ($rawProof === '') {
                    $plan->proof_url = null;
                } else {
                    $normalizedUrl = $this->normalizeProofUrl($rawProof);
                    if ($normalizedUrl === null) {
                        return response()->json([
                            'message' => 'Please enter a valid live URL (e.g., https://instagram.com/reel/...).',
                        ], 422);
                    }
                    $plan->proof_url = $normalizedUrl;
                }
            }

            if ($field === 'story_verified') {
                if (!$plan->isStoryFormat()) {
                    return response()->json([
                        'message' => 'Story verification is only available for Story formats. Please attach a live Post/Reel URL.',
                    ], 422);
                }

                $turningStoryOn = $request->exists('is_story_verified')
                    ? $request->boolean('is_story_verified')
                    : !$plan->is_story_verified;

                $plan->is_story_verified = $turningStoryOn;

                if ($turningStoryOn) {
                    $plan->status = 'posted';
                    $plan->posted_at = $plan->posted_at ?? now();
                } elseif (empty($plan->proof_url)) {
                    $plan->status = 'pending';
                    $plan->posted_at = null;
                }
            } else {
                if ($request->exists('is_story_verified') && $plan->isStoryFormat()) {
                    $plan->is_story_verified = $request->boolean('is_story_verified');
                }

                $turningOn = $plan->status !== 'posted';

                if ($turningOn) {
                    $hasValidProof = filled($plan->proof_url) && filter_var($plan->proof_url, FILTER_VALIDATE_URL) !== false;
                    $hasStoryProof = $plan->isStoryFormat() && (bool) $plan->is_story_verified;

                    if (!$hasValidProof && !$hasStoryProof) {
                        return response()->json([
                            'message' => $this->missingProofMessage($plan),
                        ], 422);
                    }

                    $plan->status = 'posted';
                    $plan->posted_at = $plan->posted_at ?? now();
                } else {
                    $plan->status = 'pending';
                    $plan->posted_at = null;
                    if ($plan->isStoryFormat() && empty($plan->proof_url)) {
                        $plan->is_story_verified = false;
                    }
                }
            }

            $this->syncLegacyColumns($plan);
            $plan->save();

            return response()->json([
                'plan' => $this->serializePlan($plan),
                'status' => $plan->status,
                'is_story_verified' => $plan->is_story_verified,
                'proof_url' => $plan->proof_url,
                'weekly_stats' => $this->weeklyStats($plan->week_start_date),
            ]);
        }

        // Legacy field support ('skip_reel', 'reel', 'stories')
        if ($field === 'skip_reel') {
            $turningOn = !$plan->is_reel_skipped;

            if ($turningOn) {
                $skippedCount = $this->skippedReelDaysCount($plan->week_start_date, $plan->id);

                if ($skippedCount >= SocialContentPlan::MAX_REEL_SKIP_DAYS) {
                    return response()->json([
                        'message' => "Weekly target is 4 Reels — you can only mark up to 3 days per week as 'No Reel Scheduled'.",
                    ], 422);
                }

                $plan->is_reel_skipped = true;
                $plan->is_reel_posted = false;
                $plan->reel_posted_at = null;
            } else {
                $plan->is_reel_skipped = false;
            }

            $plan->save();

            return response()->json([
                'is_reel_skipped' => $plan->is_reel_skipped,
                'is_reel_posted' => $plan->is_reel_posted,
                'reel_posted_at' => optional($plan->reel_posted_at)->toIso8601String(),
                'weekly_stats' => $this->weeklyStats($plan->week_start_date),
            ]);
        }

        if ($field === 'reel') {
            if ($plan->is_reel_skipped) {
                return response()->json([
                    'message' => "Gatekeeper: {$plan->day_of_week} is marked as a Rest Day — re-enable the Reel before marking it Posted.",
                ], 422);
            }

            $plan->reel_content = $validated['reel_content'] ?? null;
            $plan->reel_link = $validated['reel_link'] ?? null;

            $turningOn = !$plan->is_reel_posted;

            if ($turningOn) {
                if (empty($plan->reel_content) || empty($plan->reel_link)) {
                    return response()->json([
                        'message' => "Gatekeeper: {$plan->day_of_week}'s Reel cannot be marked Posted without both a Reel Concept and a Reel Link (Drive/Live URL).",
                    ], 422);
                }

                $plan->is_reel_posted = true;
                $plan->reel_posted_at = now();
                $plan->proof_url = $plan->reel_link;
                $plan->status = 'posted';
                $plan->posted_at = $plan->reel_posted_at;
            } else {
                $plan->is_reel_posted = false;
                $plan->reel_posted_at = null;
                $plan->status = 'pending';
                $plan->posted_at = null;
            }
        } else {
            $plan->stories_content = $validated['stories_content'] ?? null;

            $turningOn = !$plan->are_stories_posted;

            if ($turningOn) {
                if (empty($plan->stories_content)) {
                    return response()->json([
                        'message' => "Gatekeeper: {$plan->day_of_week}'s Stories cannot be marked Posted without Daily Stories content.",
                    ], 422);
                }

                $plan->are_stories_posted = true;
                $plan->stories_posted_at = now();
            } else {
                $plan->are_stories_posted = false;
                $plan->stories_posted_at = null;
            }
        }

        $plan->save();

        return response()->json([
            'is_reel_posted' => $plan->is_reel_posted,
            'are_stories_posted' => $plan->are_stories_posted,
            'reel_posted_at' => optional($plan->reel_posted_at)->toIso8601String(),
            'stories_posted_at' => optional($plan->stories_posted_at)->toIso8601String(),
            'weekly_stats' => $this->weeklyStats($plan->week_start_date),
        ]);
    }

    /**
     * Mirror unified deliverable columns onto legacy columns so KpiHubController,
     * DashboardScorecardBuilder, and LaleenCommandCenterService stay 100% consistent.
     */
    private function syncLegacyColumns(SocialContentPlan $plan): void
    {
        $isPosted = $plan->status === 'posted';
        $conceptText = $plan->visual_concept ?: $plan->focus;

        if ($plan->isStoryFormat()) {
            $plan->stories_content = $conceptText ?: $plan->stories_content;
            $plan->are_stories_posted = $isPosted;
            $plan->stories_posted_at = $isPosted ? ($plan->posted_at ?? now()) : null;
            if (filled($plan->proof_url)) {
                $plan->reel_link = $plan->proof_url;
            }
        } else {
            $plan->reel_content = $conceptText ?: $plan->reel_content;
            if (filled($plan->caption_cta)) {
                $plan->stories_content = $plan->caption_cta;
            }
            $plan->reel_link = $plan->proof_url;
            $plan->is_reel_skipped = false;
            $plan->is_reel_posted = $isPosted;
            $plan->are_stories_posted = $isPosted;
            $plan->reel_posted_at = $isPosted ? ($plan->posted_at ?? now()) : null;
            $plan->stories_posted_at = $isPosted ? ($plan->posted_at ?? now()) : null;
        }
    }

    private function normalizeProofUrl(string $raw): ?string
    {
        $clean = trim($raw);
        if ($clean === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $clean) && str_contains($clean, '.') && !str_contains($clean, ' ')) {
            $clean = 'https://' . ltrim($clean, '/');
        }

        if (filter_var($clean, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($clean, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $clean;
    }

    private function missingProofMessage(SocialContentPlan $plan): string
    {
        if ($plan->isStoryFormat()) {
            return "Add a live URL or tick 'Story Posted' to mark {$plan->day_of_week} as Posted.";
        }

        return "Add a live Post / Reel URL to mark {$plan->day_of_week} as Posted.";
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlan(SocialContentPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'day_of_week' => $plan->day_of_week,
            'date' => $plan->date,
            'branch' => $plan->effectiveBranch(),
            'assigned_user_id' => $plan->assigned_user_id,
            'assigned_to' => $plan->assigned_to,
            'focus' => $plan->focus,
            'format' => $plan->format,
            'is_story_format' => $plan->isStoryFormat(),
            'visual_concept' => $plan->effectiveVisualConcept(),
            'caption_cta' => $plan->caption_cta,
            'proof_url' => $plan->effectiveProofUrl(),
            'is_story_verified' => (bool) $plan->is_story_verified,
            'status' => $plan->isCompleted() ? 'posted' : 'pending',
            'posted_at' => optional($plan->posted_at)->toIso8601String(),
        ];
    }

    private function skippedReelDaysCount(string $weekStart, ?int $excludePlanId = null): int
    {
        return SocialContentPlan::where('week_start_date', $weekStart)
            ->where('is_reel_skipped', true)
            ->when($excludePlanId, fn ($query) => $query->where('id', '!=', $excludePlanId))
            ->count();
    }

    private function weeklyStats(string $weekStart): array
    {
        $plans = SocialContentPlan::where('week_start_date', $weekStart)->get();

        $totalDays = 7;
        $completedCount = min($totalDays, $plans->filter(fn (SocialContentPlan $p) => $p->isCompleted())->count());
        $executionPct = (int) round(($completedCount / $totalDays) * 100);

        $reelsCount = 0;
        $storiesCount = 0;
        $postsCount = 0;

        foreach ($plans as $plan) {
            $fmt = trim((string) ($plan->format ?? ''));
            if ($fmt === '') {
                continue;
            }
            if ($plan->isStoryFormat($fmt)) {
                $storiesCount++;
            } elseif ($plan->isReelFormat($fmt)) {
                $reelsCount++;
            } else {
                $postsCount++;
            }
        }

        $reelsSkipped = $plans->where('is_reel_skipped', true)->count();
        $reelsPosted = $plans->where('is_reel_posted', true)->where('is_reel_skipped', false)->count();
        $storiesPosted = $plans->where('are_stories_posted', true)->count();

        return [
            'completed_count' => $completedCount,
            'total_days' => $totalDays,
            'execution_pct' => $executionPct,
            'consistency_pct' => $executionPct,
            'reels_count' => $reelsCount,
            'posts_count' => $postsCount,
            'stories_count' => $storiesCount,
            'reels_target' => SocialContentPlan::WEEKLY_REEL_TARGET,
            'stories_target' => SocialContentPlan::WEEKLY_STORIES_TARGET,
            'max_reel_skip_days' => SocialContentPlan::MAX_REEL_SKIP_DAYS,
            'reels_posted' => $reelsPosted,
            'reels_skipped' => $reelsSkipped,
            'stories_posted' => $storiesPosted,
        ];
    }
}
