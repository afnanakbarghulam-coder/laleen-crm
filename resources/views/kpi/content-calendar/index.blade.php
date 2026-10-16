@extends('layouts.app')
@section('title', 'Weekly Content Calendar')

@php
    $canEdit = auth()->check() && auth()->user()->canEdit('kpis');
    $isCurrentWeek = $weekStart->toDateString() === now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
@endphp

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Weekly Content Calendar</h4>
            <p>Plan, assign, and verify daily reels and stories — nothing counts as Posted without proof.</p>
        </div>
        <a href="{{ route('kpi.marketing.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Marketing</a>
    </div>

    <div class="kpi-panel mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <a href="{{ route('kpi.content-calendar.index', ['week' => $previousWeek]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bx bx-chevron-left me-1"></i>Previous Week
            </a>
            <div class="text-center">
                <div class="cc-week-label">{{ $weekStart->format('d M') }} &ndash; {{ $weekEnd->format('d M, Y') }}</div>
                <div class="kpi-stat-sub mb-0">Week of {{ $weekStart->format('l, d F Y') }}</div>
                @unless ($isCurrentWeek)
                    <a href="{{ route('kpi.content-calendar.index') }}" class="cc-today-jump mt-2">
                        <i class="bx bx-calendar-check me-1"></i>Today / Current Week
                    </a>
                @endunless
            </div>
            <a href="{{ route('kpi.content-calendar.index', ['week' => $nextWeek]) }}" class="btn btn-sm btn-outline-secondary">
                Next Week<i class="bx bx-chevron-right ms-1"></i>
            </a>
        </div>

        <div class="cc-consistency-strip mt-3">
            <div class="cc-consistency-item">
                🎬 Reels Posted: <strong id="ccReelsPostedCount">{{ $weeklyStats['reels_posted'] }}</strong>/{{ $weeklyStats['reels_target'] }}
                <span class="cc-rest-pill">(Rest Days: <strong id="ccRestDaysCount">{{ $weeklyStats['reels_skipped'] }}</strong>/{{ $weeklyStats['max_reel_skip_days'] }})</span>
            </div>
            <div class="cc-consistency-item">📱 Stories Posted: <strong id="ccStoriesPostedCount">{{ $weeklyStats['stories_posted'] }}</strong>/{{ $weeklyStats['stories_target'] }}</div>
            <div class="cc-consistency-item">🔥 Weekly Execution: <strong id="ccConsistencyPct">{{ $weeklyStats['consistency_pct'] }}</strong>%</div>
        </div>
        <div class="cc-progress-track mt-2">
            <div class="cc-progress-fill" id="ccProgressFill" style="width: {{ $weeklyStats['consistency_pct'] }}%;"></div>
        </div>
    </div>

    <form method="POST" action="{{ route('kpi.content-calendar.update') }}" id="ccWeekForm">
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
                            @php
                                $rowDate = \Carbon\Carbon::parse($plan->week_start_date)->addDays($loop->index);
                                $isToday = $rowDate->isToday();
                            @endphp
                            <tr class="{{ $isToday ? 'cc-today-row' : '' }}">
                                <td class="cc-col-day">
                                    <span class="cc-day-badge">{{ strtoupper($plan->day_of_week) }}</span>
                                    <div class="cc-day-date">
                                        {{ $rowDate->format('d M') }}
                                        @if ($isToday)
                                            <span class="cc-today-chip">TODAY</span>
                                        @endif
                                    </div>
                                    <div class="cc-day-meta">
                                        <select class="cc-mini-select" name="days[{{ $plan->day_of_week }}][target_branch]" {{ $canEdit ? '' : 'disabled' }}>
                                            @foreach (\App\Models\SocialContentPlan::BRANCHES as $branch)
                                                <option value="{{ $branch }}" @selected($plan->target_branch === $branch)>{{ $branch }}</option>
                                            @endforeach
                                        </select>
                                        <select class="cc-mini-select" name="days[{{ $plan->day_of_week }}][assigned_to]" {{ $canEdit ? '' : 'disabled' }}>
                                            <option value="">&mdash; Assign &mdash;</option>
                                            @foreach ($staff as $member)
                                                <option value="{{ $member->name }}" @selected($plan->assigned_to === $member->name)>{{ $member->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <div class="position-relative cc-textarea-wrapper">
                                        <div id="reel-{{ $plan->day_of_week }}-active" class="cc-reel-active {{ $plan->is_reel_skipped ? 'd-none' : '' }}">
                                            <div class="cc-cell-topbar">
                                                <select class="cc-pillar-select" name="days[{{ $plan->day_of_week }}][reel_pillar]" {{ $canEdit ? '' : 'disabled' }}>
                                                    <option value="">Pillar&hellip;</option>
                                                    @foreach (\App\Models\SocialContentPlan::REEL_PILLARS as $pillar)
                                                        <option value="{{ $pillar }}" @selected($plan->reel_pillar === $pillar)>{{ $pillar }}</option>
                                                    @endforeach
                                                </select>
                                                <button
                                                    type="button"
                                                    class="cc-status-pill {{ $plan->is_reel_posted ? 'is-posted' : '' }}"
                                                    data-plan-id="{{ $plan->id }}"
                                                    data-field="reel"
                                                    data-target="reel-{{ $plan->day_of_week }}"
                                                    data-day="{{ $plan->day_of_week }}"
                                                    {{ $canEdit ? '' : 'disabled' }}
                                                >{{ $plan->is_reel_posted ? 'Posted ✓' : 'Pending' }}</button>
                                                <button
                                                    type="button"
                                                    class="cc-skip-pill {{ $plan->is_reel_posted ? 'd-none' : '' }}"
                                                    id="reel-{{ $plan->day_of_week }}-skip-btn"
                                                    data-plan-id="{{ $plan->id }}"
                                                    data-day="{{ $plan->day_of_week }}"
                                                    {{ $canEdit ? '' : 'disabled' }}
                                                >&mdash; No Reel Day</button>
                                            </div>
                                            <textarea
                                                id="reel-{{ $plan->day_of_week }}-textarea"
                                                name="days[{{ $plan->day_of_week }}][reel_content]"
                                                class="cc-textarea {{ $plan->is_reel_posted ? 'is-posted' : '' }}"
                                                rows="3"
                                                placeholder="Reel concept for {{ $plan->day_of_week }}&hellip;"
                                                {{ $canEdit ? '' : 'readonly' }}
                                            >{{ $plan->reel_content }}</textarea>
                                            <div class="cc-link-row">
                                                <input
                                                    type="text"
                                                    class="cc-link-input"
                                                    id="reel-{{ $plan->day_of_week }}-link"
                                                    name="days[{{ $plan->day_of_week }}][reel_link]"
                                                    value="{{ $plan->reel_link }}"
                                                    placeholder="🔗 Reel Asset / Live URL"
                                                    {{ $canEdit ? '' : 'readonly' }}
                                                >
                                                <a
                                                    href="{{ $plan->reel_link }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="cc-link-open {{ $plan->reel_link ? '' : 'd-none' }}"
                                                    id="reel-{{ $plan->day_of_week }}-open"
                                                    title="Open link"
                                                ><i class="bx bx-link-external"></i></a>
                                            </div>
                                        </div>

                                        <div id="reel-{{ $plan->day_of_week }}-rest-card" class="cc-rest-day-card {{ $plan->is_reel_skipped ? '' : 'd-none' }}">
                                            <span>🌙 No Reel Scheduled<small>(Stories-Only Day)</small></span>
                                            <button
                                                type="button"
                                                class="cc-schedule-reel-btn"
                                                id="reel-{{ $plan->day_of_week }}-schedule-btn"
                                                data-plan-id="{{ $plan->id }}"
                                                data-day="{{ $plan->day_of_week }}"
                                                {{ $canEdit ? '' : 'disabled' }}
                                            >+ Schedule Reel</button>
                                        </div>

                                        <input type="hidden" id="reel-{{ $plan->day_of_week }}-input" name="days[{{ $plan->day_of_week }}][is_reel_posted]" value="{{ $plan->is_reel_posted ? 1 : 0 }}">
                                        <input type="hidden" id="reel-{{ $plan->day_of_week }}-skip-input" name="days[{{ $plan->day_of_week }}][is_reel_skipped]" value="{{ $plan->is_reel_skipped ? 1 : 0 }}">
                                    </div>
                                </td>
                                <td>
                                    <div class="position-relative cc-textarea-wrapper">
                                        <div class="cc-cell-topbar">
                                            <button type="button" class="cc-template-chip" data-target="stories-{{ $plan->day_of_week }}" {{ $canEdit ? '' : 'disabled' }}>
                                                + Fill 3-Story Template
                                            </button>
                                            <button
                                                type="button"
                                                class="cc-status-pill {{ $plan->are_stories_posted ? 'is-posted' : '' }}"
                                                data-plan-id="{{ $plan->id }}"
                                                data-field="stories"
                                                data-target="stories-{{ $plan->day_of_week }}"
                                                data-day="{{ $plan->day_of_week }}"
                                                {{ $canEdit ? '' : 'disabled' }}
                                            >{{ $plan->are_stories_posted ? 'Posted ✓' : 'Pending' }}</button>
                                        </div>
                                        <textarea
                                            id="stories-{{ $plan->day_of_week }}-textarea"
                                            name="days[{{ $plan->day_of_week }}][stories_content]"
                                            class="cc-textarea {{ $plan->are_stories_posted ? 'is-posted' : '' }}"
                                            rows="3"
                                            placeholder="&bull; Story idea 1&#10;&bull; Story idea 2&#10;&bull; Story idea 3"
                                            {{ $canEdit ? '' : 'readonly' }}
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

        @if ($canEdit)
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="bx bx-save me-1"></i>Save Weekly Plan
                </button>
            </div>
        @endif
    </form>

    <style>
        .cc-week-label {
            font-size: 18px;
            font-weight: 700;
            color: var(--luxe-ink);
        }

        .cc-today-jump {
            display: inline-flex;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--luxe-accent);
            text-decoration: none;
            border: 1px solid var(--luxe-border-strong);
            border-radius: 999px;
            padding: 4px 12px;
            transition: background .15s ease, color .15s ease;
        }

        .cc-today-jump:hover {
            background: var(--luxe-surface-hover);
            color: var(--luxe-accent-hover);
        }

        .cc-consistency-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 22px;
            justify-content: center;
        }

        .cc-consistency-item {
            font-size: 13px;
            color: var(--luxe-muted);
        }

        .cc-consistency-item strong {
            color: var(--luxe-ink);
            font-size: 14px;
        }

        .cc-rest-pill {
            font-size: 11px;
            color: var(--luxe-muted);
            opacity: .75;
            margin-left: 4px;
        }

        .cc-rest-pill strong {
            color: var(--luxe-muted);
            font-size: 11px;
        }

        .cc-progress-track {
            height: 6px;
            border-radius: 999px;
            background: var(--luxe-surface-2);
            overflow: hidden;
        }

        .cc-progress-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--luxe-accent), var(--luxe-success));
            transition: width .4s ease;
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

        .cc-today-row td {
            background: var(--luxe-surface-hover);
            box-shadow: inset 3px 0 0 var(--luxe-accent);
        }

        .cc-col-day {
            width: 190px;
            padding: 16px 18px !important;
            vertical-align: top !important;
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

        .cc-day-date {
            margin-top: 8px;
            font-size: 12px;
            color: var(--luxe-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .cc-today-chip {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .05em;
            color: var(--luxe-accent-ink);
            background: var(--luxe-accent);
            border-radius: 999px;
            padding: 2px 8px;
        }

        .cc-day-meta {
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .cc-mini-select {
            width: 100%;
            font-size: 12px;
            padding: 5px 8px;
            border-radius: var(--luxe-radius-sm, 8px);
            border: 1px solid var(--luxe-border);
            background: var(--luxe-surface-2);
            color: var(--luxe-body);
        }

        .cc-mini-select:disabled {
            opacity: .6;
        }

        .cc-textarea {
            width: 100%;
            min-height: 90px;
            padding: 12px 18px;
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
            display: flex;
            flex-direction: column;
        }

        .cc-cell-topbar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px 0 14px;
        }

        .cc-pillar-select {
            flex: 1;
            min-width: 0;
            font-size: 11.5px;
            padding: 5px 8px;
            border-radius: var(--luxe-radius-sm, 8px);
            border: 1px solid var(--luxe-border);
            background: var(--luxe-surface-2);
            color: var(--luxe-body);
        }

        .cc-template-chip {
            flex: 1;
            min-width: 0;
            text-align: left;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--luxe-accent);
            background: var(--luxe-accent-soft);
            border: 1px solid var(--luxe-border);
            border-radius: 999px;
            padding: 5px 10px;
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease;
        }

        .cc-template-chip:hover {
            border-color: var(--luxe-accent);
        }

        .cc-template-chip:disabled,
        .cc-pillar-select:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .cc-status-pill {
            flex-shrink: 0;
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

        .cc-status-pill:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .cc-status-pill.is-posted {
            background: rgba(142, 168, 138, .16);
            color: #b7cdb3;
            border-color: rgba(142, 168, 138, .45);
        }

        .cc-skip-pill {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: .02em;
            padding: 4px 9px;
            border-radius: 999px;
            border: 1px dashed var(--luxe-border);
            background: transparent;
            color: var(--luxe-muted);
            cursor: pointer;
            transition: border-color .15s ease, color .15s ease;
        }

        .cc-skip-pill:hover {
            border-color: var(--luxe-muted);
            color: var(--luxe-body);
        }

        .cc-skip-pill:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .cc-reel-active {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .cc-rest-day-card {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            min-height: 90px;
            padding: 18px;
            color: var(--luxe-muted);
            font-size: 13px;
            background: var(--luxe-surface-2);
            opacity: .9;
        }

        .cc-rest-day-card small {
            display: block;
            font-size: 11px;
            color: var(--luxe-muted);
            margin-top: 2px;
            opacity: .8;
        }

        .cc-schedule-reel-btn {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--luxe-accent);
            background: var(--luxe-accent-soft);
            border: 1px solid var(--luxe-border-strong);
            border-radius: 999px;
            padding: 5px 14px;
            cursor: pointer;
            transition: background .15s ease, border-color .15s ease;
        }

        .cc-schedule-reel-btn:hover {
            border-color: var(--luxe-accent);
        }

        .cc-schedule-reel-btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .cc-link-row {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0 14px 14px 14px;
            margin-top: 6px;
        }

        .cc-link-input {
            flex: 1;
            min-width: 0;
            font-size: 12px;
            padding: 6px 10px;
            border-radius: var(--luxe-radius-sm, 8px);
            border: 1px solid var(--luxe-border);
            background: var(--luxe-surface-2);
            color: var(--luxe-body);
        }

        .cc-link-open {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 1px solid var(--luxe-border-strong);
            color: var(--luxe-accent);
            text-decoration: none;
            transition: background .15s ease, color .15s ease;
        }

        .cc-link-open:hover {
            background: var(--luxe-surface-hover);
            color: var(--luxe-accent-hover);
        }

        @keyframes cc-shake {
            10%, 90% { transform: translateX(-1px); }
            20%, 80% { transform: translateX(2px); }
            30%, 50%, 70% { transform: translateX(-4px); }
            40%, 60% { transform: translateX(4px); }
        }

        .cc-shake {
            animation: cc-shake .5s;
        }

        .cc-toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            max-width: 90vw;
            background: var(--luxe-surface-solid);
            border: 1px solid var(--luxe-danger);
            color: var(--luxe-body);
            font-size: 13px;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: var(--luxe-radius-sm, 10px);
            box-shadow: 0 10px 30px rgba(0,0,0,.35);
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s ease, transform .2s ease;
            z-index: 2000;
        }

        .cc-toast-show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
    </style>

    <script>
        const CC_CSRF_TOKEN = @json(csrf_token());
        const CC_TOGGLE_URL_TEMPLATE = @json(route('kpi.content-calendar.toggle-status', ['plan' => '__PLAN__']));
        const CC_STORY_TEMPLATE = "• Morning (Slot Availability / BTS): \n• Afternoon (Transformation / Proof): \n• Evening (Offer + WhatsApp Poll CTA): ";
        const CC_MAX_REEL_SKIP_DAYS = {{ $weeklyStats['max_reel_skip_days'] }};

        function ccShowToast(message) {
            let toast = document.getElementById('ccGateToast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'ccGateToast';
                toast.className = 'cc-toast';
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.classList.remove('cc-toast-show');
            void toast.offsetWidth;
            toast.classList.add('cc-toast-show');
            clearTimeout(window.__ccToastTimer);
            window.__ccToastTimer = setTimeout(function () {
                toast.classList.remove('cc-toast-show');
            }, 4000);
        }

        function ccShake(el) {
            if (!el) return;
            el.classList.remove('cc-shake');
            void el.offsetWidth;
            el.classList.add('cc-shake');
        }

        function ccUpdateConsistencyStrip(stats) {
            document.getElementById('ccReelsPostedCount').textContent = stats.reels_posted;
            document.getElementById('ccStoriesPostedCount').textContent = stats.stories_posted;
            document.getElementById('ccConsistencyPct').textContent = stats.consistency_pct;
            document.getElementById('ccProgressFill').style.width = stats.consistency_pct + '%';
            document.getElementById('ccRestDaysCount').textContent = stats.reels_skipped;
        }

        function ccCurrentRestDaysCount() {
            return parseInt(document.getElementById('ccRestDaysCount').textContent, 10) || 0;
        }

        document.querySelectorAll('.cc-status-pill').forEach(function (pill) {
            pill.addEventListener('click', function () {
                if (pill.disabled) return;

                var target = pill.getAttribute('data-target');
                var field = pill.getAttribute('data-field');
                var planId = pill.getAttribute('data-plan-id');
                var day = pill.getAttribute('data-day');
                var textarea = document.getElementById(target + '-textarea');
                var hiddenInput = document.getElementById(target + '-input');
                var turningOn = hiddenInput.value !== '1';
                var payload = { field: field };

                if (field === 'reel') {
                    var linkInput = document.getElementById(target + '-link');
                    payload.reel_content = textarea.value;
                    payload.reel_link = linkInput ? linkInput.value : '';

                    if (turningOn && (!textarea.value.trim() || !linkInput.value.trim())) {
                        ccShowToast("Gatekeeper: " + day + "'s Reel cannot be marked Posted without both a Reel Concept and a Reel Link (Drive/Live URL).");
                        if (!textarea.value.trim()) ccShake(textarea);
                        if (linkInput && !linkInput.value.trim()) ccShake(linkInput);
                        return;
                    }
                } else {
                    payload.stories_content = textarea.value;

                    if (turningOn && !textarea.value.trim()) {
                        ccShowToast("Gatekeeper: " + day + "'s Stories cannot be marked Posted without Daily Stories content.");
                        ccShake(textarea);
                        return;
                    }
                }

                pill.disabled = true;

                fetch(CC_TOGGLE_URL_TEMPLATE.replace('__PLAN__', planId), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CC_CSRF_TOKEN,
                    },
                    body: JSON.stringify(payload),
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            ccShowToast((result.data && result.data.message) || 'Something went wrong — please try again.');
                            ccShake(pill);
                            return;
                        }

                        var posted = field === 'reel' ? result.data.is_reel_posted : result.data.are_stories_posted;
                        hiddenInput.value = posted ? '1' : '0';
                        pill.classList.toggle('is-posted', posted);
                        pill.textContent = posted ? 'Posted ✓' : 'Pending';
                        textarea.classList.toggle('is-posted', posted);

                        if (field === 'reel') {
                            var skipBtn = document.getElementById('reel-' + day + '-skip-btn');
                            if (skipBtn) skipBtn.classList.toggle('d-none', posted);
                        }

                        ccUpdateConsistencyStrip(result.data.weekly_stats);
                    })
                    .catch(function () {
                        ccShowToast('Network error — status not saved. Please try again.');
                    })
                    .finally(function () {
                        pill.disabled = false;
                    });
            });
        });

        document.querySelectorAll('.cc-template-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                if (chip.disabled) return;
                var target = chip.getAttribute('data-target');
                var textarea = document.getElementById(target + '-textarea');
                if (textarea && !textarea.value.trim()) {
                    textarea.value = CC_STORY_TEMPLATE;
                    textarea.focus();
                }
            });
        });

        function ccHandleSkipToggle(planId, day) {
            var skipInput = document.getElementById('reel-' + day + '-skip-input');
            var turningOn = skipInput.value !== '1';

            if (turningOn && ccCurrentRestDaysCount() >= CC_MAX_REEL_SKIP_DAYS) {
                ccShowToast("Weekly target is 4 Reels — you can only mark up to 3 days per week as 'No Reel Scheduled'.");
                return;
            }

            fetch(CC_TOGGLE_URL_TEMPLATE.replace('__PLAN__', planId), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CC_CSRF_TOKEN,
                },
                body: JSON.stringify({ field: 'skip_reel' }),
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        ccShowToast((result.data && result.data.message) || 'Something went wrong — please try again.');
                        return;
                    }

                    var skipped = result.data.is_reel_skipped;
                    skipInput.value = skipped ? '1' : '0';

                    var activeBlock = document.getElementById('reel-' + day + '-active');
                    var restCard = document.getElementById('reel-' + day + '-rest-card');
                    if (activeBlock) activeBlock.classList.toggle('d-none', skipped);
                    if (restCard) restCard.classList.toggle('d-none', !skipped);

                    if (skipped) {
                        var postedInput = document.getElementById('reel-' + day + '-input');
                        var pill = document.querySelector('.cc-status-pill[data-target="reel-' + day + '"]');
                        var textarea = document.getElementById('reel-' + day + '-textarea');
                        if (postedInput) postedInput.value = '0';
                        if (pill) {
                            pill.classList.remove('is-posted');
                            pill.textContent = 'Pending';
                        }
                        if (textarea) textarea.classList.remove('is-posted');
                    }

                    ccUpdateConsistencyStrip(result.data.weekly_stats);
                })
                .catch(function () {
                    ccShowToast('Network error — status not saved. Please try again.');
                });
        }

        document.querySelectorAll('.cc-skip-pill, .cc-schedule-reel-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                ccHandleSkipToggle(btn.getAttribute('data-plan-id'), btn.getAttribute('data-day'));
            });
        });

        document.querySelectorAll('.cc-link-input').forEach(function (input) {
            var dayTarget = input.id.replace('-link', '');
            var openBtn = document.getElementById(dayTarget + '-open');

            function ccSyncOpenButton() {
                if (!openBtn) return;
                var hasLink = input.value.trim().length > 0;
                openBtn.classList.toggle('d-none', !hasLink);
                openBtn.href = input.value.trim();
            }

            input.addEventListener('input', ccSyncOpenButton);
        });
    </script>
@endsection
