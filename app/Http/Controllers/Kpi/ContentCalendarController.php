<?php

namespace App\Http\Controllers\Kpi;

use App\Http\Controllers\Controller;
use App\Models\SocialContentPlan;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

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

        return view('kpi.content-calendar.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'days' => $days,
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'currentWeek' => $weekStart->toDateString(),
        ]);
    }

    public function updateWeek(Request $request)
    {
        $validated = $request->validate([
            'week_start_date' => 'required|date',
            'days' => 'nullable|array',
            'days.*.reel_content' => 'nullable|string',
            'days.*.stories_content' => 'nullable|string',
            'days.*.is_reel_posted' => 'nullable|boolean',
            'days.*.are_stories_posted' => 'nullable|boolean',
        ]);

        $weekStart = Carbon::parse($validated['week_start_date'])->startOfWeek(Carbon::MONDAY)->toDateString();
        $days = $validated['days'] ?? [];

        foreach (SocialContentPlan::DAYS_OF_WEEK as $dayName) {
            $content = $days[$dayName] ?? [];

            SocialContentPlan::updateOrCreate(
                ['week_start_date' => $weekStart, 'day_of_week' => $dayName],
                [
                    'reel_content' => $content['reel_content'] ?? null,
                    'stories_content' => $content['stories_content'] ?? null,
                    'is_reel_posted' => $request->boolean("days.{$dayName}.is_reel_posted"),
                    'are_stories_posted' => $request->boolean("days.{$dayName}.are_stories_posted"),
                ]
            );
        }

        return redirect()
            ->route('kpi.content-calendar.index', ['week' => $weekStart])
            ->with('success', 'Weekly content plan saved.');
    }
}
