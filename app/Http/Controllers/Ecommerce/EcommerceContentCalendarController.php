<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceContentCalendar;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Ecommerce weekly content calendar.
 *
 * Mirrors the salon calendar's execution model (week navigation, a Reel
 * module and a Stories module per day, each independently gated before it
 * can be marked Posted) but reads and writes ONLY ecommerce_content_calendar.
 * It never touches social_content_plans, so the two stay fully isolated.
 */
class EcommerceContentCalendarController extends Controller
{
    public function index(Request $request)
    {
        $weekStart = $request->filled('week')
            ? Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        // Guarantee a row per day for the selected week, even one nobody has
        // opened before. Wrapped per-day: two overlapping requests for the
        // same new week can both clear firstOrCreate's existence check before
        // either INSERT lands — the loser just means the row already exists.
        foreach (EcommerceContentCalendar::DAYS_OF_WEEK as $dayName) {
            try {
                EcommerceContentCalendar::firstOrCreate(
                    ['week_start_date' => $weekStart->toDateString(), 'day_of_week' => $dayName],
                    ['content_type' => EcommerceContentCalendar::DEFAULT_PILLAR_BY_DAY[$dayName] ?? null]
                );
            } catch (QueryException $e) {
                if (!str_contains($e->getMessage(), 'UNIQUE constraint failed') && !str_contains($e->getMessage(), 'Duplicate entry')) {
                    throw $e;
                }
            }
        }

        $rows = EcommerceContentCalendar::where('week_start_date', $weekStart->toDateString())
            ->get()
            ->keyBy('day_of_week');

        $days = collect(EcommerceContentCalendar::DAYS_OF_WEEK)->map(fn ($d) => $rows->get($d))->filter()->values();

        return view('ecommerce.marketing.content-calendar', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'days' => $days,
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'currentWeek' => $weekStart->toDateString(),
            'isCurrentWeek' => $weekStart->toDateString() === now()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'weeklyStats' => EcommerceContentCalendar::weeklyStats($days),
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'contentTypes' => EcommerceContentCalendar::CONTENT_TYPES,
            'branches' => EcommerceContentCalendar::BRANCHES,
        ]);
    }

    /** Bulk save of the whole week from the main form. */
    public function updateWeek(Request $request)
    {
        $validated = $request->validate([
            'week_start_date' => 'required|date',
            'days' => 'nullable|array',
            'days.*.content_type' => ['nullable', Rule::in(EcommerceContentCalendar::CONTENT_TYPES)],
            'days.*.target_branch' => ['nullable', Rule::in(EcommerceContentCalendar::BRANCHES)],
            'days.*.objective' => 'nullable|string|max:255',
            'days.*.script_concept' => 'nullable|string|max:5000',
            'days.*.reel_link' => 'nullable|string|max:2048',
            'days.*.stories_content' => 'nullable|string|max:5000',
            'days.*.person_responsible' => 'nullable|string|max:255',
            'days.*.is_reel_posted' => 'nullable|boolean',
            'days.*.are_stories_posted' => 'nullable|boolean',
            'days.*.is_reel_skipped' => 'nullable|boolean',
        ]);

        $weekStart = Carbon::parse($validated['week_start_date'])->startOfWeek(Carbon::MONDAY)->toDateString();
        $days = $validated['days'] ?? [];
        $gateErrors = [];

        // Rest-day guardrail: at most MAX_REEL_SKIP_DAYS per week. The first
        // three requested (Mon->Sun order) win; the rest stay scheduled.
        $requestedSkips = collect(EcommerceContentCalendar::DAYS_OF_WEEK)
            ->filter(fn ($d) => $request->boolean("days.{$d}.is_reel_skipped"));

        if ($requestedSkips->count() > EcommerceContentCalendar::MAX_REEL_SKIP_DAYS) {
            $gateErrors[] = 'Weekly target is ' . EcommerceContentCalendar::WEEKLY_REEL_TARGET
                . " Reels — you can only mark up to " . EcommerceContentCalendar::MAX_REEL_SKIP_DAYS
                . " days per week as 'No Reel Day'.";
        }

        $allowedSkips = $requestedSkips->take(EcommerceContentCalendar::MAX_REEL_SKIP_DAYS);

        DB::transaction(function () use ($weekStart, $days, $request, $allowedSkips, &$gateErrors) {
            foreach (EcommerceContentCalendar::DAYS_OF_WEEK as $dayName) {
                $content = $days[$dayName] ?? [];
                $existing = EcommerceContentCalendar::where([
                    'week_start_date' => $weekStart,
                    'day_of_week' => $dayName,
                ])->first();

                $script = $content['script_concept'] ?? null;
                $link = $content['reel_link'] ?? null;
                $stories = $content['stories_content'] ?? null;
                $skipped = $allowedSkips->contains($dayName);

                // A Reel can only be Posted with both a concept and a live
                // link — and a Rest Day can never be Posted at all.
                $wantsReelPosted = $request->boolean("days.{$dayName}.is_reel_posted");
                $reelPosted = $wantsReelPosted && !$skipped;
                if (!$skipped && $wantsReelPosted && (blank($script) || blank($link))) {
                    $reelPosted = false;
                    $gateErrors[] = "Gatekeeper: {$dayName}'s Reel cannot be marked Posted without both a Script/Concept and a Reel Link.";
                }

                $wantsStoriesPosted = $request->boolean("days.{$dayName}.are_stories_posted");
                $storiesPosted = $wantsStoriesPosted;
                if ($wantsStoriesPosted && blank($stories)) {
                    $storiesPosted = false;
                    $gateErrors[] = "Gatekeeper: {$dayName}'s Stories cannot be marked Posted without Daily Stories content.";
                }

                EcommerceContentCalendar::updateOrCreate(
                    ['week_start_date' => $weekStart, 'day_of_week' => $dayName],
                    [
                        'content_type' => $content['content_type'] ?? null,
                        'target_branch' => $content['target_branch'] ?? 'All Branches',
                        'objective' => $content['objective'] ?? null,
                        'script_concept' => $script,
                        'reel_link' => $link,
                        'stories_content' => $stories,
                        'person_responsible' => $content['person_responsible'] ?? null,
                        'is_reel_skipped' => $skipped,
                        'is_reel_posted' => $reelPosted,
                        'are_stories_posted' => $storiesPosted,
                        // Preserve the original timestamp on a row that was
                        // already posted, so re-saving doesn't reset history.
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

        $redirect = redirect()->route('ecommerce.marketing.content.index', ['week' => $weekStart]);

        return empty($gateErrors)
            ? $redirect->with('success', 'Weekly content plan saved.')
            : $redirect->with('error', implode(' ', array_unique($gateErrors)));
    }

    /**
     * Instant per-cell save + status toggle behind the status pills.
     * Saves whatever was last typed in that cell, then flips its flag
     * subject to the same gates enforced in updateWeek().
     */
    public function toggleStatus(Request $request, EcommerceContentCalendar $content)
    {
        $validated = $request->validate([
            'field' => ['required', Rule::in(['reel', 'stories', 'skip_reel'])],
            'script_concept' => 'nullable|string|max:5000',
            'reel_link' => 'nullable|string|max:2048',
            'stories_content' => 'nullable|string|max:5000',
        ]);

        if ($validated['field'] === 'skip_reel') {
            $turningOn = !$content->is_reel_skipped;

            if ($turningOn) {
                $skipped = EcommerceContentCalendar::where('week_start_date', $content->week_start_date)
                    ->where('is_reel_skipped', true)
                    ->where('id', '!=', $content->id)
                    ->count();

                if ($skipped >= EcommerceContentCalendar::MAX_REEL_SKIP_DAYS) {
                    return response()->json([
                        'message' => 'Weekly target is ' . EcommerceContentCalendar::WEEKLY_REEL_TARGET
                            . " Reels — you can only mark up to " . EcommerceContentCalendar::MAX_REEL_SKIP_DAYS
                            . " days per week as 'No Reel Day'.",
                    ], 422);
                }

                $content->is_reel_skipped = true;
                $content->is_reel_posted = false;
                $content->reel_posted_at = null;
            } else {
                $content->is_reel_skipped = false;
            }
        } elseif ($validated['field'] === 'reel') {
            if ($content->is_reel_skipped) {
                return response()->json([
                    'message' => "Gatekeeper: {$content->day_of_week} is a No Reel Day — schedule the reel before marking it Posted.",
                ], 422);
            }

            $content->script_concept = $validated['script_concept'] ?? null;
            $content->reel_link = $validated['reel_link'] ?? null;

            if (!$content->is_reel_posted) {
                if (blank($content->script_concept) || blank($content->reel_link)) {
                    return response()->json([
                        'message' => "Gatekeeper: {$content->day_of_week}'s Reel cannot be marked Posted without both a Script/Concept and a Reel Link.",
                    ], 422);
                }

                $content->is_reel_posted = true;
                $content->reel_posted_at = now();
            } else {
                $content->is_reel_posted = false;
                $content->reel_posted_at = null;
            }
        } else {
            $content->stories_content = $validated['stories_content'] ?? null;

            if (!$content->are_stories_posted) {
                if (blank($content->stories_content)) {
                    return response()->json([
                        'message' => "Gatekeeper: {$content->day_of_week}'s Stories cannot be marked Posted without Daily Stories content.",
                    ], 422);
                }

                $content->are_stories_posted = true;
                $content->stories_posted_at = now();
            } else {
                $content->are_stories_posted = false;
                $content->stories_posted_at = null;
            }
        }

        $content->save();

        $weekRows = EcommerceContentCalendar::where('week_start_date', $content->week_start_date)->get();

        return response()->json([
            'is_reel_posted' => $content->is_reel_posted,
            'is_reel_skipped' => $content->is_reel_skipped,
            'are_stories_posted' => $content->are_stories_posted,
            'weekly_stats' => EcommerceContentCalendar::weeklyStats($weekRows),
        ]);
    }
}
