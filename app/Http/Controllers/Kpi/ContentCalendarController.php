<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\SocialContentPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContentCalendarController extends Controller
{
    public function index(Request $request)
    {
        $weekStart = $request->filled('week')
            ? Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        // Ensure every day of the selected week has a row to edit, even on
        // a week nobody has touched yet. Wrapped per-day: two overlapping
        // requests for the same never-visited week can both pass
        // firstOrCreate's existence check before either INSERT commits —
        // the loser just means the row already exists, so swallow it.
        foreach (SocialContentPlan::DAYS_OF_WEEK as $dayName) {
            try {
                SocialContentPlan::firstOrCreate([
                    'week_start_date' => $weekStart->toDateString(),
                    'day_of_week' => $dayName,
                ]);
            } catch (QueryException $e) {
                if (!str_contains($e->getMessage(), 'UNIQUE constraint failed') && !str_contains($e->getMessage(), 'Duplicate entry')) {
                    throw $e;
                }
            }
        }

        $plansByDay = SocialContentPlan::where('week_start_date', $weekStart->toDateString())
            ->get()
            ->keyBy('day_of_week');

        $days = collect(SocialContentPlan::DAYS_OF_WEEK)->map(fn ($dayName) => $plansByDay->get($dayName));

        $staff = User::orderBy('name')->get(['id', 'name']);

        return view('kpi.content-calendar.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'days' => $days,
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'currentWeek' => $weekStart->toDateString(),
            'staff' => $staff,
            'weeklyStats' => $this->weeklyStats($weekStart->toDateString()),
        ]);
    }

    public function updateWeek(Request $request)
    {
        $validated = $request->validate([
            'week_start_date' => 'required|date',
            'days' => 'nullable|array',
            'days.*.target_branch' => ['nullable', Rule::in(SocialContentPlan::BRANCHES)],
            'days.*.assigned_to' => 'nullable|string|max:255',
            'days.*.reel_pillar' => ['nullable', Rule::in(SocialContentPlan::REEL_PILLARS)],
            'days.*.reel_content' => 'nullable|string|max:5000',
            'days.*.reel_link' => 'nullable|string|max:2048',
            'days.*.stories_content' => 'nullable|string|max:5000',
            'days.*.is_reel_posted' => 'nullable|boolean',
            'days.*.are_stories_posted' => 'nullable|boolean',
            'days.*.is_reel_skipped' => 'nullable|boolean',
        ]);

        $weekStart = Carbon::parse($validated['week_start_date'])->startOfWeek(Carbon::MONDAY)->toDateString();
        $days = $validated['days'] ?? [];
        $gateErrors = [];

        // Rest-Day guardrail: at most MAX_REEL_SKIP_DAYS (3) days per week can
        // be marked "No Reel Scheduled" — the first 3 requested (in Mon->Sun
        // order) win; any beyond that are silently kept as scheduled Reel
        // days and the submitter is warned.
        $requestedSkipDays = collect(SocialContentPlan::DAYS_OF_WEEK)
            ->filter(fn ($dayName) => $request->boolean("days.{$dayName}.is_reel_skipped"));

        if ($requestedSkipDays->count() > SocialContentPlan::MAX_REEL_SKIP_DAYS) {
            $gateErrors[] = "Weekly target is 4 Reels — you can only mark up to 3 days per week as 'No Reel Scheduled'.";
        }

        $allowedSkipDays = $requestedSkipDays->take(SocialContentPlan::MAX_REEL_SKIP_DAYS);

        DB::transaction(function () use ($weekStart, $days, $request, $allowedSkipDays, &$gateErrors) {
            foreach (SocialContentPlan::DAYS_OF_WEEK as $dayName) {
                $content = $days[$dayName] ?? [];
                $existing = SocialContentPlan::where(['week_start_date' => $weekStart, 'day_of_week' => $dayName])->first();

                $reelContent = $content['reel_content'] ?? null;
                $reelLink = $content['reel_link'] ?? null;
                $storiesContent = $content['stories_content'] ?? null;

                $reelSkipped = $allowedSkipDays->contains($dayName);

                // Server-Side Posted Gatekeeper: a day can only be marked Posted
                // if its required content is actually present — the checkbox
                // alone is too easy to rubber-stamp. A Rest Day can never be
                // marked Posted.
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
                        'target_branch' => $content['target_branch'] ?? 'All Branches',
                        'assigned_to' => $content['assigned_to'] ?? null,
                        'reel_pillar' => $content['reel_pillar'] ?? null,
                        'reel_content' => $reelContent,
                        'reel_link' => $reelLink,
                        'stories_content' => $storiesContent,
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
     * Instant per-cell save + status toggle used by the status pills — saves
     * whatever text/link was last typed in that cell, then flips the Posted
     * flag (reel or stories) or the Rest Day flag for that row, subject to
     * the same gatekeeper rules enforced in updateWeek().
     */
    public function toggleStatus(Request $request, SocialContentPlan $plan)
    {
        $validated = $request->validate([
            'field' => ['required', Rule::in(['reel', 'stories', 'skip_reel'])],
            'reel_content' => 'nullable|string|max:5000',
            'reel_link' => 'nullable|string|max:2048',
            'stories_content' => 'nullable|string|max:5000',
        ]);

        if ($validated['field'] === 'skip_reel') {
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

        if ($validated['field'] === 'reel') {
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
            } else {
                $plan->is_reel_posted = false;
                $plan->reel_posted_at = null;
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

        $reelsSkipped = $plans->where('is_reel_skipped', true)->count();
        $reelsPosted = $plans->where('is_reel_posted', true)->where('is_reel_skipped', false)->count();
        $storiesPosted = $plans->where('are_stories_posted', true)->count();

        $target = SocialContentPlan::WEEKLY_REEL_TARGET + SocialContentPlan::WEEKLY_STORIES_TARGET;
        $consistencyPct = min(100, (int) round(((min($reelsPosted, SocialContentPlan::WEEKLY_REEL_TARGET) + $storiesPosted) / $target) * 100));

        return [
            'reels_target' => SocialContentPlan::WEEKLY_REEL_TARGET,
            'stories_target' => SocialContentPlan::WEEKLY_STORIES_TARGET,
            'max_reel_skip_days' => SocialContentPlan::MAX_REEL_SKIP_DAYS,
            'reels_posted' => $reelsPosted,
            'reels_skipped' => $reelsSkipped,
            'stories_posted' => $storiesPosted,
            'consistency_pct' => $consistencyPct,
        ];
    }
}
