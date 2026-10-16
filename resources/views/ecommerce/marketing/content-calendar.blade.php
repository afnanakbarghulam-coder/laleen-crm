@extends('layouts.app')
@section('title', 'Weekly Content Calendar')

@include('ecommerce._styles')

@php
    $canEdit = auth()->check() && auth()->user()->canEdit('ecommerce');
@endphp

@section('content')
    <div class="ec-header">
        <div>
            <h4>Weekly Content Calendar</h4>
            <p>Plan, assign and verify each day's Reel and Stories &mdash; nothing counts as Posted without proof.</p>
        </div>
        <a href="{{ route('ecommerce.marketing') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Marketing</a>
    </div>

    {{-- ============ Week navigation + execution pills ============ --}}
    <div class="ec-card mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <a href="{{ route('ecommerce.marketing.content.index', ['week' => $previousWeek]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bx bx-chevron-left me-1"></i>Previous Week
            </a>
            <div class="text-center">
                <div class="cc-week-label">{{ $weekStart->format('d M') }} &ndash; {{ $weekEnd->format('d M, Y') }}</div>
                <div class="ec-sub mb-0">Week of {{ $weekStart->format('l, d F Y') }}</div>
                @unless ($isCurrentWeek)
                    <a href="{{ route('ecommerce.marketing.content.index') }}" class="cc-today-jump mt-2">
                        <i class="bx bx-calendar-check me-1"></i>Today / Current Week
                    </a>
                @endunless
            </div>
            <a href="{{ route('ecommerce.marketing.content.index', ['week' => $nextWeek]) }}" class="btn btn-sm btn-outline-secondary">
                Next Week<i class="bx bx-chevron-right ms-1"></i>
            </a>
        </div>

        <div class="cc-consistency-strip mt-3">
            <div class="cc-consistency-item">🎬 Reels Posted: <strong id="ccReelsPostedCount">{{ $weeklyStats['reels_posted'] }}</strong>/{{ $weeklyStats['reels_target'] }}
                <span class="cc-rest-pill">(No Reel Days: <strong id="ccRestDaysCount">{{ $weeklyStats['reels_skipped'] }}</strong>/{{ $weeklyStats['max_reel_skip_days'] }})</span>
            </div>
            <div class="cc-consistency-item">📱 Stories Posted: <strong id="ccStoriesPostedCount">{{ $weeklyStats['stories_posted'] }}</strong>/{{ $weeklyStats['stories_target'] }}</div>
            <div class="cc-consistency-item">🔥 Weekly Execution: <strong id="ccConsistencyPct">{{ $weeklyStats['consistency_pct'] }}</strong>%</div>
        </div>
        <div class="cc-progress-track mt-2">
            <div class="cc-progress-fill" id="ccProgressFill" style="width: {{ $weeklyStats['consistency_pct'] }}%;"></div>
        </div>
    </div>

    <form method="POST" action="{{ route('ecommerce.marketing.content.update-week') }}">
        @csrf
        <input type="hidden" name="week_start_date" value="{{ $currentWeek }}">

        <div class="ec-card p-0 cc-panel">
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
                                $d = $plan->day_of_week;
                            @endphp
                            <tr class="{{ $isToday ? 'cc-today-row' : '' }}">
                                {{-- ---- Left: day + date + branch + assignee ---- --}}
                                <td class="cc-col-day">
                                    <span class="cc-day-badge">{{ strtoupper($d) }}</span>
                                    <div class="cc-day-date">
                                        {{ $rowDate->format('d M') }}
                                        @if ($isToday)<span class="cc-today-chip">TODAY</span>@endif
                                    </div>
                                    <div class="cc-day-meta">
                                        <select class="cc-mini-select" name="days[{{ $d }}][target_branch]" {{ $canEdit ? '' : 'disabled' }}>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch }}" @selected($plan->target_branch === $branch)>{{ $branch }}</option>
                                            @endforeach
                                        </select>
                                        <select class="cc-mini-select" name="days[{{ $d }}][person_responsible]" {{ $canEdit ? '' : 'disabled' }}>
                                            <option value="">&mdash; Assign &mdash;</option>
                                            @foreach ($staff as $member)
                                                <option value="{{ $member->name }}" @selected($plan->person_responsible === $member->name)>{{ $member->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>

                                {{-- ---- Middle: 8:00 PM Reel ---- --}}
                                <td>
                                    <div class="cc-textarea-wrapper">
                                        <div id="reel-{{ $d }}-active" class="cc-reel-active {{ $plan->is_reel_skipped ? 'd-none' : '' }}">
                                            <div class="cc-cell-topbar">
                                                <select class="cc-pillar-select" name="days[{{ $d }}][content_type]" {{ $canEdit ? '' : 'disabled' }}>
                                                    <option value="">Pillar&hellip;</option>
                                                    @foreach ($contentTypes as $pillar)
                                                        <option value="{{ $pillar }}" @selected($plan->content_type === $pillar)>{{ $pillar }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button"
                                                    class="cc-status-pill {{ $plan->is_reel_posted ? 'is-posted' : '' }}"
                                                    data-plan-id="{{ $plan->id }}" data-field="reel"
                                                    data-target="reel-{{ $d }}" data-day="{{ $d }}"
                                                    {{ $canEdit ? '' : 'disabled' }}>{{ $plan->is_reel_posted ? 'Posted ✓' : 'Pending' }}</button>
                                                <button type="button"
                                                    class="cc-skip-pill {{ $plan->is_reel_posted ? 'd-none' : '' }}"
                                                    id="reel-{{ $d }}-skip-btn"
                                                    data-plan-id="{{ $plan->id }}" data-day="{{ $d }}"
                                                    {{ $canEdit ? '' : 'disabled' }}>&mdash; No Reel Day</button>
                                            </div>
                                            <input type="text" class="cc-objective-input" name="days[{{ $d }}][objective]"
                                                value="{{ $plan->objective }}" placeholder="Objective — e.g. drive first purchase"
                                                {{ $canEdit ? '' : 'readonly' }}>
                                            <textarea id="reel-{{ $d }}-textarea" name="days[{{ $d }}][script_concept]"
                                                class="cc-textarea {{ $plan->is_reel_posted ? 'is-posted' : '' }}" rows="3"
                                                placeholder="Reel script / concept for {{ $d }}&hellip;"
                                                {{ $canEdit ? '' : 'readonly' }}>{{ $plan->script_concept }}</textarea>
                                            <div class="cc-link-row">
                                                <input type="text" class="cc-link-input" id="reel-{{ $d }}-link"
                                                    name="days[{{ $d }}][reel_link]" value="{{ $plan->reel_link }}"
                                                    placeholder="🔗 Creative Asset / Live URL" {{ $canEdit ? '' : 'readonly' }}>
                                                <a href="{{ $plan->reel_link }}" target="_blank" rel="noopener"
                                                    class="cc-link-open {{ $plan->reel_link ? '' : 'd-none' }}"
                                                    id="reel-{{ $d }}-open" title="Open link"><i class="bx bx-link-external"></i></a>
                                            </div>
                                        </div>

                                        <div id="reel-{{ $d }}-rest-card" class="cc-rest-day-card {{ $plan->is_reel_skipped ? '' : 'd-none' }}">
                                            <span>🌙 No Reel Day<small>(Stories-only)</small></span>
                                            <button type="button" class="cc-schedule-reel-btn" id="reel-{{ $d }}-schedule-btn"
                                                data-plan-id="{{ $plan->id }}" data-day="{{ $d }}"
                                                {{ $canEdit ? '' : 'disabled' }}>+ Schedule Reel</button>
                                        </div>

                                        <input type="hidden" id="reel-{{ $d }}-input" name="days[{{ $d }}][is_reel_posted]" value="{{ $plan->is_reel_posted ? 1 : 0 }}">
                                        <input type="hidden" id="reel-{{ $d }}-skip-input" name="days[{{ $d }}][is_reel_skipped]" value="{{ $plan->is_reel_skipped ? 1 : 0 }}">
                                    </div>
                                </td>

                                {{-- ---- Right: Daily Stories ---- --}}
                                <td>
                                    <div class="cc-textarea-wrapper">
                                        <div class="cc-cell-topbar">
                                            <button type="button" class="cc-template-chip" data-target="stories-{{ $d }}" {{ $canEdit ? '' : 'disabled' }}>
                                                + Fill 3-Story Template
                                            </button>
                                            <button type="button"
                                                class="cc-status-pill {{ $plan->are_stories_posted ? 'is-posted' : '' }}"
                                                data-plan-id="{{ $plan->id }}" data-field="stories"
                                                data-target="stories-{{ $d }}" data-day="{{ $d }}"
                                                {{ $canEdit ? '' : 'disabled' }}>{{ $plan->are_stories_posted ? 'Posted ✓' : 'Pending' }}</button>
                                        </div>
                                        <textarea id="stories-{{ $d }}-textarea" name="days[{{ $d }}][stories_content]"
                                            class="cc-textarea {{ $plan->are_stories_posted ? 'is-posted' : '' }}" rows="4"
                                            placeholder="&bull; Morning&#10;&bull; Afternoon&#10;&bull; Evening"
                                            {{ $canEdit ? '' : 'readonly' }}>{{ $plan->stories_content }}</textarea>
                                        <input type="hidden" id="stories-{{ $d }}-input" name="days[{{ $d }}][are_stories_posted]" value="{{ $plan->are_stories_posted ? 1 : 0 }}">
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
                <button type="submit" class="btn btn-primary btn-lg px-5"><i class="bx bx-save me-1"></i>Save Weekly Plan</button>
            </div>
        @endif
    </form>

    <style>
        .cc-week-label { font-size: 18px; font-weight: 700; color: var(--ec-ink); }

        .cc-today-jump {
            display: inline-flex; align-items: center; font-size: 12px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .04em; color: var(--ec-primary);
            text-decoration: none; border: 1px solid var(--ec-border-strong);
            border-radius: 999px; padding: 4px 12px; transition: background .15s ease;
        }
        .cc-today-jump:hover { background: rgba(217,143,131,0.1); }

        .cc-consistency-strip { display: flex; flex-wrap: wrap; gap: 10px 22px; justify-content: center; }
        .cc-consistency-item { font-size: 13px; color: var(--ec-muted); }
        .cc-consistency-item strong { color: var(--ec-ink); font-size: 14px; }
        .cc-rest-pill { font-size: 11px; color: var(--ec-muted); opacity: .75; margin-left: 4px; }
        .cc-rest-pill strong { color: var(--ec-muted); font-size: 11px; }

        .cc-progress-track { height: 6px; border-radius: 999px; background: rgba(255,255,255,0.06); overflow: hidden; }
        .cc-progress-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--ec-primary), var(--ec-success)); transition: width .4s ease; }

        .cc-panel { overflow: hidden; }
        .cc-table { margin-bottom: 0; min-width: 900px; }
        .cc-table thead tr { background: rgba(217,143,131,0.06); }
        .cc-table thead th {
            color: var(--ec-ink); font-size: 12px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .06em; padding: 16px 18px; border-bottom: 1px solid var(--ec-border-strong); white-space: nowrap;
        }
        .cc-table tbody td { padding: 0; border-color: var(--ec-border); vertical-align: top; }
        .cc-table tbody tr:not(:last-child) td { border-bottom: 1px solid var(--ec-border); }
        .cc-today-row td { background: rgba(217,143,131,0.05); box-shadow: inset 3px 0 0 var(--ec-primary); }

        .cc-col-day { width: 190px; padding: 16px 18px !important; vertical-align: top !important; }
        .cc-day-badge {
            display: inline-block; font-size: 12.5px; font-weight: 700; letter-spacing: .04em;
            color: var(--ec-ink); background: rgba(217,143,131,0.14); border-radius: 999px; padding: 6px 14px;
        }
        .cc-day-date { margin-top: 8px; font-size: 12px; color: var(--ec-muted); display: flex; align-items: center; gap: 6px; }
        .cc-today-chip {
            display: inline-block; font-size: 10px; font-weight: 700; letter-spacing: .05em;
            color: #241a16; background: var(--ec-primary); border-radius: 999px; padding: 2px 8px;
        }
        .cc-day-meta { margin-top: 12px; display: flex; flex-direction: column; gap: 6px; }

        .cc-mini-select, .cc-pillar-select, .cc-link-input, .cc-objective-input {
            border-radius: 8px; border: 1px solid var(--ec-border);
            background: rgba(255,255,255,0.03); color: #f2e6e2;
        }
        .cc-mini-select { width: 100%; font-size: 12px; padding: 5px 8px; }
        .cc-pillar-select { flex: 1; min-width: 0; font-size: 11.5px; padding: 5px 8px; }
        .cc-mini-select:disabled, .cc-pillar-select:disabled { opacity: .6; }

        .cc-objective-input { width: calc(100% - 28px); margin: 8px 14px 0; font-size: 12.5px; font-weight: 600; padding: 6px 10px; }

        .cc-textarea-wrapper { height: 100%; display: flex; flex-direction: column; }
        .cc-reel-active { display: flex; flex-direction: column; height: 100%; }
        .cc-cell-topbar { display: flex; align-items: center; gap: 8px; padding: 10px 14px 0; }

        .cc-textarea {
            width: 100%; min-height: 80px; padding: 10px 18px; background: transparent; border: none;
            outline: none; resize: vertical; color: #f2e6e2; font-size: 13.5px; line-height: 1.5;
            font-family: inherit; transition: opacity .15s ease, box-shadow .15s ease;
        }
        .cc-textarea::placeholder { color: var(--ec-muted); opacity: .7; }
        .cc-textarea:focus { background: rgba(217,143,131,0.05); box-shadow: inset 0 0 0 1px var(--ec-primary); }
        .cc-textarea.is-posted { opacity: .55; box-shadow: inset 0 0 0 1px rgba(142,168,138,.45); }

        .cc-status-pill, .cc-skip-pill, .cc-template-chip {
            flex-shrink: 0; display: inline-flex; align-items: center; font-weight: 700;
            border-radius: 999px; cursor: pointer; white-space: nowrap;
        }
        .cc-status-pill {
            font-size: 11px; letter-spacing: .03em; text-transform: uppercase; padding: 4px 10px;
            border: 1px solid var(--ec-border-strong); background: rgba(255,255,255,0.03); color: var(--ec-muted);
            transition: background .15s ease, color .15s ease, border-color .15s ease;
        }
        .cc-status-pill:hover { border-color: var(--ec-primary); }
        .cc-status-pill.is-posted { background: rgba(142,168,138,.16); color: #b7cdb3; border-color: rgba(142,168,138,.45); }
        .cc-status-pill:disabled, .cc-skip-pill:disabled, .cc-template-chip:disabled { opacity: .5; cursor: not-allowed; }

        .cc-skip-pill {
            font-size: 10.5px; font-weight: 600; padding: 4px 9px;
            border: 1px dashed var(--ec-border); background: transparent; color: var(--ec-muted);
        }
        .cc-skip-pill:hover { border-color: var(--ec-muted); color: #f2e6e2; }

        .cc-template-chip {
            margin-right: auto; font-size: 11.5px; font-weight: 600;
            color: var(--ec-primary); background: rgba(217,143,131,0.12);
            border: 1px solid var(--ec-border); padding: 5px 10px;
        }
        .cc-template-chip:hover { border-color: var(--ec-primary); }

        .cc-link-row { display: flex; align-items: center; gap: 6px; padding: 0 14px 14px; margin-top: 6px; }
        .cc-link-input { flex: 1; min-width: 0; font-size: 12px; padding: 6px 10px; }
        .cc-link-open {
            flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px; border-radius: 50%; border: 1px solid var(--ec-border-strong);
            color: var(--ec-primary); text-decoration: none;
        }
        .cc-link-open:hover { background: rgba(217,143,131,0.1); }

        .cc-rest-day-card {
            flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 14px;
            min-height: 90px; padding: 18px; color: var(--ec-muted); font-size: 13px;
            background: rgba(255,255,255,0.02);
        }
        .cc-rest-day-card small { display: block; font-size: 11px; opacity: .8; margin-top: 2px; }
        .cc-schedule-reel-btn {
            font-size: 11.5px; font-weight: 700; color: var(--ec-primary); background: rgba(217,143,131,0.12);
            border: 1px solid var(--ec-border-strong); border-radius: 999px; padding: 5px 14px; cursor: pointer;
        }
        .cc-schedule-reel-btn:hover { border-color: var(--ec-primary); }
        .cc-schedule-reel-btn:disabled { opacity: .6; cursor: not-allowed; }

        @keyframes cc-shake {
            10%,90% { transform: translateX(-1px); } 20%,80% { transform: translateX(2px); }
            30%,50%,70% { transform: translateX(-4px); } 40%,60% { transform: translateX(4px); }
        }
        .cc-shake { animation: cc-shake .5s; }

        .cc-toast {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(20px);
            max-width: 90vw; background: #241e1c; border: 1px solid var(--ec-danger); color: #f2e6e2;
            font-size: 13px; font-weight: 600; padding: 12px 20px; border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,.35); opacity: 0; pointer-events: none;
            transition: opacity .2s ease, transform .2s ease; z-index: 2000;
        }
        .cc-toast-show { opacity: 1; transform: translateX(-50%) translateY(0); }
    </style>

    <script>
        const CC_CSRF_TOKEN = @json(csrf_token());
        const CC_TOGGLE_URL = @json(route('ecommerce.marketing.content.toggle-status', ['content' => '__ID__']));
        const CC_STORY_TEMPLATE = @json(\App\Models\EcommerceContentCalendar::STORY_TEMPLATE);
        const CC_MAX_SKIP_DAYS = {{ $weeklyStats['max_reel_skip_days'] }};

        function ccToast(message) {
            let el = document.getElementById('ccGateToast');
            if (!el) {
                el = document.createElement('div');
                el.id = 'ccGateToast';
                el.className = 'cc-toast';
                document.body.appendChild(el);
            }
            el.textContent = message;
            el.classList.remove('cc-toast-show');
            void el.offsetWidth;
            el.classList.add('cc-toast-show');
            clearTimeout(window.__ccToastTimer);
            window.__ccToastTimer = setTimeout(() => el.classList.remove('cc-toast-show'), 4000);
        }

        function ccShake(el) {
            if (!el) return;
            el.classList.remove('cc-shake');
            void el.offsetWidth;
            el.classList.add('cc-shake');
        }

        function ccUpdateStrip(stats) {
            document.getElementById('ccReelsPostedCount').textContent = stats.reels_posted;
            document.getElementById('ccStoriesPostedCount').textContent = stats.stories_posted;
            document.getElementById('ccRestDaysCount').textContent = stats.reels_skipped;
            document.getElementById('ccConsistencyPct').textContent = stats.consistency_pct;
            document.getElementById('ccProgressFill').style.width = stats.consistency_pct + '%';
        }

        const ccCurrentSkipCount = () => parseInt(document.getElementById('ccRestDaysCount').textContent, 10) || 0;

        function ccPatch(planId, payload) {
            return fetch(CC_TOGGLE_URL.replace('__ID__', planId), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CC_CSRF_TOKEN,
                },
                body: JSON.stringify(payload),
            }).then((res) => res.json().then((data) => ({ ok: res.ok, data })));
        }

        // ---- Posted / Pending toggles ----
        document.querySelectorAll('.cc-status-pill').forEach(function (pill) {
            pill.addEventListener('click', function () {
                if (pill.disabled) return;

                const target = pill.dataset.target;
                const field = pill.dataset.field;
                const day = pill.dataset.day;
                const textarea = document.getElementById(target + '-textarea');
                const hidden = document.getElementById(target + '-input');
                const turningOn = hidden.value !== '1';
                const payload = { field };

                if (field === 'reel') {
                    const link = document.getElementById(target + '-link');
                    payload.script_concept = textarea.value;
                    payload.reel_link = link ? link.value : '';

                    if (turningOn && (!textarea.value.trim() || !link.value.trim())) {
                        ccToast("Gatekeeper: " + day + "'s Reel cannot be marked Posted without both a Script/Concept and a Reel Link.");
                        if (!textarea.value.trim()) ccShake(textarea);
                        if (!link.value.trim()) ccShake(link);
                        return;
                    }
                } else {
                    payload.stories_content = textarea.value;
                    if (turningOn && !textarea.value.trim()) {
                        ccToast("Gatekeeper: " + day + "'s Stories cannot be marked Posted without Daily Stories content.");
                        ccShake(textarea);
                        return;
                    }
                }

                pill.disabled = true;
                ccPatch(pill.dataset.planId, payload)
                    .then(({ ok, data }) => {
                        if (!ok) { ccToast(data.message || 'Something went wrong.'); ccShake(pill); return; }
                        const posted = field === 'reel' ? data.is_reel_posted : data.are_stories_posted;
                        hidden.value = posted ? '1' : '0';
                        pill.classList.toggle('is-posted', posted);
                        pill.textContent = posted ? 'Posted ✓' : 'Pending';
                        textarea.classList.toggle('is-posted', posted);
                        if (field === 'reel') {
                            const skipBtn = document.getElementById('reel-' + day + '-skip-btn');
                            if (skipBtn) skipBtn.classList.toggle('d-none', posted);
                        }
                        ccUpdateStrip(data.weekly_stats);
                    })
                    .catch(() => ccToast('Network error — status not saved.'))
                    .finally(() => { pill.disabled = false; });
            });
        });

        // ---- No Reel Day / Schedule Reel ----
        document.querySelectorAll('.cc-skip-pill, .cc-schedule-reel-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                const day = btn.dataset.day;
                const skipInput = document.getElementById('reel-' + day + '-skip-input');
                const turningOn = skipInput.value !== '1';

                if (turningOn && ccCurrentSkipCount() >= CC_MAX_SKIP_DAYS) {
                    ccToast("Weekly target is 4 Reels — you can only mark up to " + CC_MAX_SKIP_DAYS + " days per week as 'No Reel Day'.");
                    return;
                }

                ccPatch(btn.dataset.planId, { field: 'skip_reel' })
                    .then(({ ok, data }) => {
                        if (!ok) { ccToast(data.message || 'Something went wrong.'); return; }
                        const skipped = data.is_reel_skipped;
                        skipInput.value = skipped ? '1' : '0';
                        document.getElementById('reel-' + day + '-active').classList.toggle('d-none', skipped);
                        document.getElementById('reel-' + day + '-rest-card').classList.toggle('d-none', !skipped);

                        if (skipped) {
                            const postedInput = document.getElementById('reel-' + day + '-input');
                            const pill = document.querySelector('.cc-status-pill[data-target="reel-' + day + '"]');
                            const textarea = document.getElementById('reel-' + day + '-textarea');
                            if (postedInput) postedInput.value = '0';
                            if (pill) { pill.classList.remove('is-posted'); pill.textContent = 'Pending'; }
                            if (textarea) textarea.classList.remove('is-posted');
                        }
                        ccUpdateStrip(data.weekly_stats);
                    })
                    .catch(() => ccToast('Network error — status not saved.'));
            });
        });

        // ---- 3-Story template ----
        document.querySelectorAll('.cc-template-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                if (chip.disabled) return;
                const textarea = document.getElementById(chip.dataset.target + '-textarea');
                if (textarea && !textarea.value.trim()) {
                    textarea.value = CC_STORY_TEMPLATE;
                    textarea.focus();
                }
            });
        });

        // ---- Reveal the open-link button as soon as a URL is typed ----
        document.querySelectorAll('.cc-link-input').forEach(function (input) {
            const openBtn = document.getElementById(input.id.replace('-link', '') + '-open');
            input.addEventListener('input', function () {
                if (!openBtn) return;
                const has = input.value.trim().length > 0;
                openBtn.classList.toggle('d-none', !has);
                openBtn.href = input.value.trim();
            });
        });
    </script>
@endsection
