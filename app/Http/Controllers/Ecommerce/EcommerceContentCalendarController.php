<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceContentCalendar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ecommerce weekly content calendar — the Monday-Sunday pillar rotation.
 *
 * Backed by the existing ecommerce_content_calendar table, which is already
 * entirely separate from the salon's social_content_plans / SocialContentPlan.
 * A second ecommerce-side table would have been a third parallel calendar with
 * identical columns, so this controller owns the existing one instead.
 */
class EcommerceContentCalendarController extends Controller
{
    public function index()
    {
        $calendar = EcommerceContentCalendar::all()
            ->sortBy([
                fn ($a, $b) => $a->dayIndex() <=> $b->dayIndex(),
                fn ($a, $b) => $a->id <=> $b->id,
            ])
            ->values();

        return view('ecommerce.marketing.content-calendar', [
            'calendar' => $calendar,
            'calendarByDay' => collect(EcommerceContentCalendar::DAYS_OF_WEEK)
                ->mapWithKeys(fn ($day) => [$day => $calendar->where('day_of_week', $day)->values()]),
            'statusCounts' => collect(EcommerceContentCalendar::STATUSES)
                ->mapWithKeys(fn ($status) => [$status => $calendar->where('status', $status)->count()]),
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'days' => EcommerceContentCalendar::DAYS_OF_WEEK,
            'contentTypes' => EcommerceContentCalendar::CONTENT_TYPES,
            'statuses' => EcommerceContentCalendar::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        EcommerceContentCalendar::create($this->validated($request));

        return back()->with('success', 'Content slot added.');
    }

    public function update(Request $request, EcommerceContentCalendar $content)
    {
        $content->update($this->validated($request));

        return back()->with('success', 'Content slot updated.');
    }

    /**
     * Inline status / assignee change from the board. Only ever writes the
     * fields the dropdowns own, so a quick status flip can never blank out
     * the script concept or objective.
     */
    public function updateStatus(Request $request, EcommerceContentCalendar $content)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(EcommerceContentCalendar::STATUSES)],
            'person_responsible' => 'nullable|string|max:255',
        ]);

        $content->update(array_filter(
            $validated,
            fn ($value, $key) => $request->has($key),
            ARRAY_FILTER_USE_BOTH
        ));

        return back()->with('success', 'Content slot updated.');
    }

    public function destroy(EcommerceContentCalendar $content)
    {
        $content->delete();

        return back()->with('success', 'Content slot removed.');
    }

    /** Seeds an empty board with one slot per day on its default pillar. */
    public function seedWeek()
    {
        if (EcommerceContentCalendar::exists()) {
            return back()->withErrors(['error' => 'The content calendar already has rows — seeding is only for an empty board.']);
        }

        foreach (EcommerceContentCalendar::DAYS_OF_WEEK as $day) {
            EcommerceContentCalendar::create([
                'day_of_week' => $day,
                'content_type' => EcommerceContentCalendar::DEFAULT_PILLAR_BY_DAY[$day] ?? null,
                'status' => 'Scripted',
            ]);
        }

        return back()->with('success', 'Seeded a full Monday–Sunday content week.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'day_of_week' => ['required', Rule::in(EcommerceContentCalendar::DAYS_OF_WEEK)],
            'content_type' => ['nullable', Rule::in(EcommerceContentCalendar::CONTENT_TYPES)],
            'objective' => 'nullable|string|max:255',
            'script_concept' => 'nullable|string|max:5000',
            'person_responsible' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(EcommerceContentCalendar::STATUSES)],
        ]);
    }
}
