<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\ShiftPattern;
use App\Models\StaffTimeOff;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $weekStart = $request->filled('week')
            ? Carbon::parse($request->week)->startOfWeek(Carbon::SUNDAY)
            : Carbon::now()->startOfWeek(Carbon::SUNDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $days = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));

        $query = Staff::with([
            'shiftPatterns.blocks',
            'timeOffs' => fn ($q) => $q->where('start_date', '<=', $weekEnd)->where('end_date', '>=', $weekStart),
        ]);

        if ($request->filled('branch')) {
            $query->where('branch', $request->branch);
        }

        $staff = $query->orderBy('name')->get();

        $roster = [];
        $dayTotals = array_fill_keys($days->map->format('Y-m-d')->all(), 0);

        foreach ($staff as $member) {
            $weeklyMinutes = 0;
            $cells = [];

            foreach ($days as $date) {
                $dateKey = $date->format('Y-m-d');
                $timeOff = $member->timeOffs->first(fn ($t) => $t->coversDate($date));

                if ($timeOff) {
                    $cells[$dateKey] = ['type' => 'timeoff', 'reason' => $timeOff->reason, 'blocks' => []];
                    continue;
                }

                $pattern = $member->activePatternFor($date);
                $blocks = $pattern
                    ? $pattern->blocks->where('day_of_week', $date->dayOfWeek)->sortBy('start_time')
                    : collect();

                if ($blocks->isEmpty()) {
                    $cells[$dateKey] = ['type' => 'off', 'blocks' => []];
                    continue;
                }

                $formatted = $blocks->map(function ($block) use (&$weeklyMinutes, &$dayTotals, $dateKey) {
                    $minutes = $block->durationMinutes();
                    $weeklyMinutes += $minutes;
                    $dayTotals[$dateKey] += $minutes;

                    return [
                        'label' => $this->formatTimeRange($block->start_time, $block->end_time),
                    ];
                });

                $cells[$dateKey] = ['type' => 'working', 'blocks' => $formatted->values()->all()];
            }

            $activePattern = $member->activePatternFor(Carbon::now());

            $roster[] = [
                'staff' => $member,
                'weekly_hours' => round($weeklyMinutes / 60, 1),
                'cells' => $cells,
                'config' => [
                    'pattern' => $activePattern ? [
                        'repeat_frequency' => $activePattern->repeat_frequency,
                        'start_date' => optional($activePattern->start_date)->format('Y-m-d'),
                        'end_date' => optional($activePattern->end_date)->format('Y-m-d'),
                        'blocks' => $activePattern->blocks->map(fn ($b) => [
                            'day_of_week' => $b->day_of_week,
                            'start_time' => substr($b->start_time, 0, 5),
                            'end_time' => substr($b->end_time, 0, 5),
                        ])->values()->all(),
                    ] : null,
                    'time_offs' => $member->timeOffs()->orderByDesc('start_date')->get()->map(fn ($t) => [
                        'id' => $t->id,
                        'start_date' => $t->start_date->format('Y-m-d'),
                        'end_date' => $t->end_date->format('Y-m-d'),
                        'reason' => $t->reason,
                        'notes' => $t->notes,
                    ])->values()->all(),
                ],
            ];
        }

        $dayTotalsFormatted = collect($dayTotals)->map(fn ($m) => round($m / 60, 1))->all();

        $shiftConfigs = [];
        foreach ($roster as $row) {
            $shiftConfigs[$row['staff']->id] = [
                'name' => $row['staff']->name,
                'pattern' => $row['config']['pattern'],
                'time_offs' => $row['config']['time_offs'],
            ];
        }

        return view('shifts.index', [
            'roster' => $roster,
            'days' => $days,
            'dayTotals' => $dayTotalsFormatted,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'allStaff' => Staff::orderBy('name')->get(['id', 'name', 'branch']),
            'shiftConfigs' => $shiftConfigs,
        ]);
    }

    public function savePattern(Request $request, Staff $staff)
    {
        $data = $request->validate([
            'repeat_frequency' => ['required', Rule::in(['weekly', 'does_not_repeat'])],
            'start_date' => 'required|date',
            'no_end_date' => 'nullable|boolean',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'blocks' => 'nullable|array',
        ]);

        $errors = [];
        foreach ($request->input('blocks', []) as $day => $blocks) {
            foreach ($blocks as $i => $block) {
                if (empty($block['start']) || empty($block['end'])) {
                    continue;
                }
                if ($block['end'] <= $block['start']) {
                    $errors["blocks.$day.$i"] = 'End time must be after start time.';
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($request, $staff, $data) {
            $pattern = ShiftPattern::updateOrCreate(
                ['staff_id' => $staff->id],
                [
                    'repeat_frequency' => $data['repeat_frequency'],
                    'start_date' => $data['start_date'],
                    'end_date' => $request->boolean('no_end_date') ? null : ($data['end_date'] ?? null),
                ]
            );

            $pattern->blocks()->delete();

            foreach ($request->input('blocks', []) as $day => $blocks) {
                foreach ($blocks as $block) {
                    if (empty($block['start']) || empty($block['end'])) {
                        continue;
                    }
                    $pattern->blocks()->create([
                        'day_of_week' => (int) $day,
                        'start_time' => $block['start'] . ':00',
                        'end_time' => $block['end'] . ':00',
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', 'Shift schedule updated for ' . $staff->name . '.');
    }

    public function storeTimeOff(Request $request, Staff $staff)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => ['required', Rule::in(['on-leave', 'sick', 'unpaid', 'other'])],
            'notes' => 'nullable|string|max:500',
        ]);

        $timeOff = $staff->timeOffs()->create($data);

        return response()->json([
            'success' => true,
            'timeOff' => [
                'id' => $timeOff->id,
                'start_date' => $timeOff->start_date->format('Y-m-d'),
                'end_date' => $timeOff->end_date->format('Y-m-d'),
                'reason' => $timeOff->reason,
                'notes' => $timeOff->notes,
            ],
        ]);
    }

    public function destroyTimeOff(StaffTimeOff $timeOff)
    {
        $timeOff->delete();

        return response()->json(['success' => true]);
    }

    private function formatTimeRange(string $start, string $end): string
    {
        $format = function ($t) {
            $carbon = Carbon::createFromFormat('H:i:s', strlen($t) === 5 ? $t . ':00' : $t);
            return strtolower($carbon->format($carbon->minute === 0 ? 'ga' : 'g:ia'));
        };

        return $format($start) . ' - ' . $format($end);
    }
}
