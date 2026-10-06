@extends('layouts.app')
@section('title', 'Weekly Content Calendar')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Weekly Content Calendar</h4>
            <p>Plan daily reels and stories schedules.</p>
        </div>
        <a href="{{ route('kpi.hub') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to KPIs</a>
    </div>

    <div class="kpi-panel mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <a href="{{ route('kpi.content-calendar.index', ['week' => $previousWeek]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bx bx-chevron-left me-1"></i>Previous Week
            </a>
            <div class="text-center">
                <div class="cc-week-label">{{ $weekStart->format('d M') }} &ndash; {{ $weekEnd->format('d M, Y') }}</div>
                <div class="kpi-stat-sub mb-0">Week of {{ $weekStart->format('l, d F Y') }}</div>
            </div>
            <a href="{{ route('kpi.content-calendar.index', ['week' => $nextWeek]) }}" class="btn btn-sm btn-outline-secondary">
                Next Week<i class="bx bx-chevron-right ms-1"></i>
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('kpi.content-calendar.update') }}">
        @csrf
        <input type="hidden" name="week_start_date" value="{{ $currentWeek }}">

        <div class="kpi-panel p-0 cc-panel">
            <div class="table-responsive">
                <table class="table align-middle mb-0 cc-table">
                    <thead>
                        <tr>
                            <th class="cc-col-day">Day</th>
                            <th>8:00 PM Reel</th>
                            <th>Daily Stories</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($days as $plan)
                            <tr>
                                <td class="cc-col-day">
                                    <span class="cc-day-badge">{{ strtoupper($plan->day_of_week) }}</span>
                                </td>
                                <td>
                                    <div class="position-relative cc-textarea-wrapper">
                                        <button
                                            type="button"
                                            class="cc-status-pill {{ $plan->is_reel_posted ? 'is-posted' : '' }}"
                                            data-target="reel-{{ $plan->day_of_week }}"
                                        >{{ $plan->is_reel_posted ? 'Posted ✓' : 'Pending' }}</button>
                                        <textarea
                                            id="reel-{{ $plan->day_of_week }}-textarea"
                                            name="days[{{ $plan->day_of_week }}][reel_content]"
                                            class="cc-textarea {{ $plan->is_reel_posted ? 'is-posted' : '' }}"
                                            rows="3"
                                            placeholder="Reel concept for {{ $plan->day_of_week }}&hellip;"
                                        >{{ $plan->reel_content }}</textarea>
                                        <input type="hidden" id="reel-{{ $plan->day_of_week }}-input" name="days[{{ $plan->day_of_week }}][is_reel_posted]" value="{{ $plan->is_reel_posted ? 1 : 0 }}">
                                    </div>
                                </td>
                                <td>
                                    <div class="position-relative cc-textarea-wrapper">
                                        <button
                                            type="button"
                                            class="cc-status-pill {{ $plan->are_stories_posted ? 'is-posted' : '' }}"
                                            data-target="stories-{{ $plan->day_of_week }}"
                                        >{{ $plan->are_stories_posted ? 'Posted ✓' : 'Pending' }}</button>
                                        <textarea
                                            id="stories-{{ $plan->day_of_week }}-textarea"
                                            name="days[{{ $plan->day_of_week }}][stories_content]"
                                            class="cc-textarea {{ $plan->are_stories_posted ? 'is-posted' : '' }}"
                                            rows="3"
                                            placeholder="&bull; Story idea 1&#10;&bull; Story idea 2&#10;&bull; Story idea 3"
                                        >{{ $plan->stories_content }}</textarea>
                                        <input type="hidden" id="stories-{{ $plan->day_of_week }}-input" name="days[{{ $plan->day_of_week }}][are_stories_posted]" value="{{ $plan->are_stories_posted ? 1 : 0 }}">
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary btn-lg px-5">
                <i class="bx bx-save me-1"></i>Save Weekly Plan
            </button>
        </div>
    </form>

    <style>
        .cc-week-label {
            font-size: 18px;
            font-weight: 700;
            color: var(--luxe-ink);
        }

        .cc-panel {
            overflow: hidden;
        }

        .cc-table {
            margin-bottom: 0;
        }

        .cc-table thead tr {
            background: var(--luxe-bg-elevated);
        }

        .cc-table thead th {
            color: var(--luxe-ink);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 16px 18px;
            border-bottom: 1px solid var(--luxe-border-strong);
            white-space: nowrap;
        }

        .cc-table tbody td {
            padding: 0;
            border-color: var(--luxe-border);
            vertical-align: top;
        }

        .cc-col-day {
            width: 160px;
            padding: 16px 18px !important;
            vertical-align: middle !important;
        }

        .cc-day-badge {
            display: inline-block;
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: .04em;
            color: var(--luxe-ink);
            background: var(--luxe-accent-soft);
            border-radius: 999px;
            padding: 6px 14px;
        }

        .cc-textarea {
            width: 100%;
            min-height: 90px;
            padding: 16px 18px;
            padding-top: 40px;
            background: transparent;
            border: none;
            outline: none;
            resize: vertical;
            color: var(--luxe-body);
            font-size: 13.5px;
            line-height: 1.5;
            font-family: inherit;
            transition: opacity .15s ease, box-shadow .15s ease;
        }

        .cc-textarea::placeholder {
            color: var(--luxe-muted);
            opacity: .7;
        }

        .cc-textarea:focus {
            background: var(--luxe-surface-hover);
            box-shadow: inset 0 0 0 1px var(--luxe-accent);
        }

        .cc-textarea.is-posted {
            opacity: .55;
            box-shadow: inset 0 0 0 1px rgba(142, 168, 138, .45);
        }

        .cc-table tbody tr:not(:last-child) td {
            border-bottom: 1px solid var(--luxe-border);
        }

        .cc-textarea-wrapper {
            height: 100%;
        }

        .cc-status-pill {
            position: absolute;
            top: 10px;
            right: 12px;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid var(--luxe-border-strong);
            background: var(--luxe-bg-elevated);
            color: var(--luxe-muted);
            cursor: pointer;
            transition: background .15s ease, color .15s ease, border-color .15s ease;
        }

        .cc-status-pill:hover {
            border-color: var(--luxe-accent);
        }

        .cc-status-pill.is-posted {
            background: rgba(142, 168, 138, .16);
            color: #b7cdb3;
            border-color: rgba(142, 168, 138, .45);
        }
    </style>

    <script>
        document.querySelectorAll('.cc-status-pill').forEach(function (pill) {
            pill.addEventListener('click', function () {
                var target = pill.getAttribute('data-target');
                var textarea = document.getElementById(target + '-textarea');
                var input = document.getElementById(target + '-input');
                var isPosted = input.value === '1';

                isPosted = !isPosted;

                input.value = isPosted ? '1' : '0';
                pill.classList.toggle('is-posted', isPosted);
                pill.textContent = isPosted ? 'Posted ✓' : 'Pending';
                textarea.classList.toggle('is-posted', isPosted);
            });
        });
    </script>
@endsection
