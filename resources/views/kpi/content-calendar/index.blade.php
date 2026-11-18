@extends('layouts.app')
@section('title', 'Weekly Content Calendar')

@php
    $canEdit = auth()->check() && auth()->user()->canEdit('kpis');
    $isCurrentWeek = $weekStart->toDateString() === now()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
    $rangeDisplay = $weekStart->format('j M') . ' – ' . $weekEnd->format('j M, Y');

    $proTipsList = $weekMeta ? $weekMeta->normalizedProTips() : [];
    $hasWeekMeta = $weekMeta && ($weekMeta->hasAnyMetadata() || filled($weekMeta->source_pdf_name));
    $hasDayData = $days->contains(function ($p) {
        if (!$p) {
            return false;
        }

        return filled($p->focus)
            || filled($p->format)
            || filled($p->effectiveVisualConcept())
            || filled($p->caption_cta)
            || filled($p->effectiveProofUrl())
            || $p->isCompleted()
            || filled($p->assigned_to);
    });
    $hasWeekData = $hasWeekMeta || $hasDayData;

    $proTipsTextareaValue = collect($proTipsList)
        ->map(fn ($tip) => ($tip['title'] && $tip['title'] !== 'Pro-Tip') ? "{$tip['title']}: {$tip['body']}" : $tip['body'])
        ->implode("\n");

    $presetFormats = \App\Models\SocialContentPlan::FORMATS;
    $branchOptions = \App\Models\SocialContentPlan::BRANCH_OPTIONS;
@endphp

@section('content')
<div class="wcc-flat-wrap">
    {{-- ROW 1: PAGE TITLE & QUIET TEXT ACTIONS --}}
    <div class="wcc-top-row no-print">
        <h1 class="wcc-page-title">Weekly Content Calendar</h1>

        <div class="wcc-quiet-actions">
            <span id="wccSaveIndicator" class="wcc-save-indicator" aria-live="polite"></span>

            @if ($hasWeekData)
                <button
                    type="button"
                    class="wcc-text-action {{ filled($weekMeta?->hashtags) ? '' : 'd-none' }}"
                    id="wccCopyHashtagsBtn"
                    data-hashtags="{{ $weekMeta?->hashtags }}"
                >Copy Hashtags</button>
                <span class="wcc-sep {{ filled($weekMeta?->hashtags) ? '' : 'd-none' }}" id="wccCopyHashtagsSep">&middot;</span>

                <button
                    type="button"
                    class="wcc-text-action"
                    id="wccToggleStrategyBtn"
                    aria-expanded="false"
                >Strategy &amp; Tips &#9662;</button>
                <span class="wcc-sep">&middot;</span>
            @endif

            @if ($canEdit)
                <button type="button" class="wcc-text-action is-accent" id="wccOpenPdfModalBtn">
                    &uarr; Upload PDF
                </button>
                <span class="wcc-sep">&middot;</span>

                @if ($hasWeekData)
                    <button
                        type="button"
                        class="wcc-delete-plan-btn text-xs text-white/50 hover:text-rose-400 transition cursor-pointer"
                        id="wccOpenDeleteModalBtn"
                    >Delete Plan</button>
                    <span class="wcc-sep">&middot;</span>
                @endif
            @endif

            <a href="{{ route('kpi.marketing.index') }}" class="wcc-text-action">
                &larr; Marketing
            </a>
        </div>
    </div>

    {{-- ROW 2: FLAT WEEK & EXECUTION HAIRLINE STRIP --}}
    <div class="wcc-week-strip border-b border-white/10 pb-3 mb-2">
        <div class="wcc-week-left">
            <a href="{{ route('kpi.content-calendar.index', ['week' => $previousWeek]) }}" class="wcc-week-nav-link no-print">&lt; Previous</a>
            <span class="wcc-sep no-print">&middot;</span>
            <strong class="wcc-week-label">{{ $rangeDisplay }}</strong>
            <span class="wcc-sep no-print">&middot;</span>
            <a href="{{ route('kpi.content-calendar.index', ['week' => $nextWeek]) }}" class="wcc-week-nav-link no-print">Next &gt;</a>
            @unless ($isCurrentWeek)
                <span class="wcc-sep no-print">&middot;</span>
                <a href="{{ route('kpi.content-calendar.index') }}" class="wcc-week-today-link no-print">Current Week</a>
            @endunless
        </div>

        <div class="wcc-week-right">
            <span><strong id="wccCompletedCount">{{ $weeklyStats['completed_count'] }}</strong>/{{ $weeklyStats['total_days'] }} Posted (<span id="wccExecutionPct">{{ $weeklyStats['execution_pct'] }}%</span>)</span>
            <span class="wcc-sep">&middot;</span>
            <span class="wcc-breakdown-text">
                <span id="wccReelsCount">{{ $weeklyStats['reels_count'] }}</span> Reels
                &middot; <span id="wccPostsCount">{{ $weeklyStats['posts_count'] }}</span> Posts
                &middot; <span id="wccStoriesCount">{{ $weeklyStats['stories_count'] }}</span> Stories
            </span>
        </div>
    </div>

    {{-- HIDDEN-BY-DEFAULT STRATEGY & TIPS PLAIN-TEXT PANEL (Only Expands When Clicked) --}}
    @if ($hasWeekMeta || $canEdit)
        <div class="wcc-strategy-drawer d-none border-b border-white/10 pb-4 mb-3" id="wccStrategyBody">
            <div id="wccStrategyReadView" class="{{ !$hasWeekMeta && $canEdit ? 'd-none' : '' }}">
                <div class="wcc-strategy-columns">
                    <div>
                        <div class="wcc-plain-eyebrow">Strategy Objective</div>
                        <p class="wcc-plain-copy" id="wccStrategyObjectiveDisplay">{{ $weekMeta?->strategy_objective ?: '—' }}</p>
                    </div>
                    <div>
                        <div class="wcc-plain-eyebrow">Pro-Tips</div>
                        <div id="wccProTipsDisplay" class="wcc-plain-tips">
                            @forelse ($proTipsList as $tip)
                                <div class="wcc-plain-tip-line">
                                    <strong>{{ $tip['title'] }}:</strong> {{ $tip['body'] }}
                                </div>
                            @empty
                                <span class="wcc-muted-text">No pro-tips configured.</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="wcc-strategy-hashtags-line">
                    <div class="wcc-hashtags-inline">
                        <span class="wcc-plain-eyebrow me-2">Hashtags</span>
                        <span id="wccHashtagsDisplay" class="wcc-hashtags-copy">{{ $weekMeta?->hashtags ?: '—' }}</span>
                    </div>
                    @if ($canEdit)
                        <button type="button" class="wcc-text-action no-print" id="wccEditStrategyBtn">Edit</button>
                    @endif
                </div>
            </div>

            @if ($canEdit)
                <div id="wccStrategyEditView" class="{{ !$hasWeekMeta ? '' : 'd-none' }} no-print">
                    <div class="wcc-strategy-columns">
                        <div>
                            <label class="wcc-plain-eyebrow" for="wccInputStrategyObjective">Strategy Objective</label>
                            <textarea
                                id="wccInputStrategyObjective"
                                class="wcc-flat-textarea js-wcc-autosize bg-transparent border-0 ring-0 focus:ring-0 focus:outline-none p-0 resize-none overflow-hidden w-full text-sm leading-relaxed"
                                rows="2"
                                placeholder="Weekly campaign focus, offer positioning, and Roman Urdu / English notes..."
                            >{{ $weekMeta?->strategy_objective }}</textarea>
                        </div>
                        <div>
                            <label class="wcc-plain-eyebrow" for="wccInputProTips">Pro-Tips (Title: Recommendation per line)</label>
                            <textarea
                                id="wccInputProTips"
                                class="wcc-flat-textarea js-wcc-autosize bg-transparent border-0 ring-0 focus:ring-0 focus:outline-none p-0 resize-none overflow-hidden w-full text-sm leading-relaxed"
                                rows="2"
                                placeholder="Pin Best Performers: Pin the 99 QAR Reel to top of profile"
                            >{{ $proTipsTextareaValue }}</textarea>
                        </div>
                    </div>

                    <div class="wcc-strategy-edit-bar">
                        <input
                            type="text"
                            id="wccInputHashtags"
                            class="wcc-url-line-input bg-transparent border-0 border-b border-white/10 focus:border-white/30 p-0 pb-1 text-xs w-full"
                            style="max-width: 520px;"
                            placeholder="#LaleenBeautySalon #DohaSalons #QatarBeauty #DohaHair #QatarOffers"
                            value="{{ $weekMeta?->hashtags }}"
                        >
                        <div class="d-flex align-items-center gap-3">
                            <button type="button" class="wcc-text-action" id="wccCancelStrategyBtn">Cancel</button>
                            <button type="button" class="wcc-text-action is-accent" id="wccSaveStrategyBtn">Save Strategy</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- READ-ONLY MONTHLY SLA BREACH FEEDBACK (Flat Hairline Only When Present) --}}
    @php
        $ccMonthBreaches = collect($monthLateReplyLogs ?? []);
        $ccWeekBreaches = collect($weekLateReplyLogs ?? []);
    @endphp
    @if ($ccMonthBreaches->isNotEmpty())
        <div class="wcc-sla-flat border-b border-white/10 pb-3 mb-2">
            <div class="wcc-sla-flat-title">
                Social Reply SLA Breaches ({{ $ccMonthBreaches->count() }} in {{ $lateRepliesMonthLabel ?? $weekStart->format('F Y') }}
                @if ($ccWeekBreaches->isNotEmpty())
                    &middot; {{ $ccWeekBreaches->count() }} this week
                @endif
                )
            </div>
            @foreach ($ccMonthBreaches as $breachLog)
                @php
                    $ccProofUrl = $breachLog->screenshotUrl();
                    $ccIncidentStr = $breachLog->incident_at
                        ? $breachLog->incident_at->copy()->setTimezone('Asia/Qatar')->format('d M Y, g:i A')
                        : '—';
                @endphp
                <div class="wcc-sla-flat-item">
                    <span>
                        <strong>{{ $ccIncidentStr }} (Doha)</strong>
                        @if (filled($breachLog->notes))
                            &mdash; {{ $breachLog->notes }}
                        @endif
                        <span class="wcc-muted-text">(Logged by {{ $breachLog->logger?->name ?? 'Admin' }})</span>
                    </span>
                    @if ($ccProofUrl)
                        <a href="{{ $ccProofUrl }}" target="_blank" rel="noopener" class="wcc-text-action">View Screenshot &nearr;</a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- 2. BORDERLESS EDITORIAL 7-DAY TABLE --}}
    <div class="wcc-table-wrap">
        <table class="wcc-flat-table" id="wccDeliverablesTable">
            <thead>
                <tr class="border-b border-white/10">
                    <th class="wcc-th-day">Day &amp; Assign</th>
                    <th class="wcc-th-focus">Focus &amp; Format</th>
                    <th class="wcc-th-visual">Visual / Video Concept</th>
                    <th class="wcc-th-caption">Caption Hook &amp; CTA</th>
                    <th class="wcc-th-proof">Proof &amp; Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($days as $plan)
                    @php
                        $rowDate = \Carbon\Carbon::parse($plan->week_start_date)->addDays($loop->index);
                        $isToday = $rowDate->isToday();
                        $effectiveBranch = $plan->effectiveBranch();
                        $currentFormat = trim((string) ($plan->format ?? ''));
                        $isStoryRow = $plan->isStoryFormat($currentFormat);
                        $isPostedRow = $plan->isCompleted();
                        $visualValue = $plan->effectiveVisualConcept();
                        $proofValue = $plan->effectiveProofUrl();
                    @endphp
                    <tr
                        class="wcc-day-row border-b border-white/10 {{ $isToday ? 'is-today' : '' }}"
                        data-plan-id="{{ $plan->id }}"
                        data-inline-url="{{ route('kpi.content-calendar.inline-update', $plan) }}"
                        data-toggle-url="{{ route('kpi.content-calendar.toggle-status', $plan) }}"
                        data-day-name="{{ $plan->day_of_week }}"
                    >
                        {{-- COL 1: DAY & ASSIGN --}}
                        <td class="py-5 align-top wcc-td-day">
                            <div class="wcc-day-name">{{ strtoupper($plan->day_of_week) }}</div>
                            <div class="wcc-day-sub">
                                {{ $rowDate->format('d M') }}
                                @if ($isToday)
                                    <span class="wcc-today-dot">&middot; Today</span>
                                @endif
                            </div>

                            <div class="wcc-select-stack">
                                @if ($canEdit)
                                    <select
                                        class="wcc-flat-select js-wcc-field bg-transparent border-0 p-0 pr-4 text-xs opacity-75 hover:opacity-100 focus:ring-0 cursor-pointer"
                                        data-field="branch"
                                        aria-label="{{ $plan->day_of_week }} Branch"
                                    >
                                        @foreach ($branchOptions as $branchOpt)
                                            <option value="{{ $branchOpt }}" @selected($effectiveBranch === $branchOpt)>{{ $branchOpt }}</option>
                                        @endforeach
                                    </select>

                                    <select
                                        class="wcc-flat-select js-wcc-field bg-transparent border-0 p-0 pr-4 text-xs opacity-75 hover:opacity-100 focus:ring-0 cursor-pointer"
                                        data-field="assigned_to"
                                        aria-label="{{ $plan->day_of_week }} Assignee"
                                    >
                                        <option value="">Assign</option>
                                        @foreach ($staff as $member)
                                            <option value="{{ $member->name }}" @selected($plan->assigned_to === $member->name)>{{ $member->name }}</option>
                                        @endforeach
                                        @if (filled($plan->assigned_to) && !$staff->contains('name', $plan->assigned_to))
                                            <option value="{{ $plan->assigned_to }}" selected>{{ $plan->assigned_to }}</option>
                                        @endif
                                    </select>
                                @else
                                    <div class="text-xs opacity-75">{{ $effectiveBranch }}</div>
                                    <div class="text-xs opacity-60">{{ $plan->assigned_to ?: 'Unassigned' }}</div>
                                @endif
                            </div>
                        </td>

                        {{-- COL 2: FOCUS & FORMAT --}}
                        <td class="py-5 align-top wcc-td-focus">
                            <input
                                type="text"
                                class="wcc-flat-focus js-wcc-text-field text-sm font-medium bg-transparent border-0 p-0 focus:ring-0 focus:outline-none w-full"
                                data-field="focus"
                                value="{{ $plan->focus }}"
                                placeholder="Topic or offer..."
                                aria-label="{{ $plan->day_of_week }} Focus"
                                {{ $canEdit ? '' : 'readonly' }}
                            >

                            <div class="mt-1.5">
                                @if ($canEdit)
                                    <select
                                        class="wcc-flat-select js-wcc-format bg-transparent border-0 p-0 pr-4 text-xs opacity-75 hover:opacity-100 focus:ring-0 cursor-pointer"
                                        data-field="format"
                                        aria-label="{{ $plan->day_of_week }} Format"
                                    >
                                        <option value="">Format</option>
                                        @foreach ($presetFormats as $fmtOption)
                                            <option value="{{ $fmtOption }}" @selected($currentFormat === $fmtOption)>{{ $fmtOption }}</option>
                                        @endforeach
                                        @if ($currentFormat !== '' && !in_array($currentFormat, $presetFormats, true))
                                            <option value="{{ $currentFormat }}" selected>{{ $currentFormat }}</option>
                                        @endif
                                    </select>
                                @else
                                    <span class="text-xs opacity-75">{{ $currentFormat ?: '—' }}</span>
                                @endif
                            </div>
                        </td>

                        {{-- COL 3: VISUAL / VIDEO CONCEPT --}}
                        <td class="py-5 align-top wcc-td-visual">
                            <textarea
                                class="wcc-flat-textarea js-wcc-text-field js-wcc-autosize bg-transparent border-0 ring-0 focus:ring-0 focus:outline-none p-0 resize-none overflow-hidden w-full text-sm leading-relaxed"
                                data-field="visual_concept"
                                rows="1"
                                placeholder="Visual or video concept..."
                                aria-label="{{ $plan->day_of_week }} Visual Concept"
                                {{ $canEdit ? '' : 'readonly' }}
                            >{{ $visualValue }}</textarea>
                        </td>

                        {{-- COL 4: CAPTION HOOK & CTA --}}
                        <td class="py-5 align-top wcc-td-caption">
                            <textarea
                                class="wcc-flat-textarea js-wcc-text-field js-wcc-autosize js-wcc-caption-input bg-transparent border-0 ring-0 focus:ring-0 focus:outline-none p-0 resize-none overflow-hidden w-full text-sm leading-relaxed"
                                data-field="caption_cta"
                                rows="1"
                                placeholder="Caption hook & CTA..."
                                aria-label="{{ $plan->day_of_week }} Caption Hook & CTA"
                                {{ $canEdit ? '' : 'readonly' }}
                            >{{ $plan->caption_cta }}</textarea>

                            <button
                                type="button"
                                class="wcc-copy-caption js-wcc-copy-caption text-xs opacity-45 hover:opacity-100 mt-1.5 inline-block no-print"
                                aria-label="Copy {{ $plan->day_of_week }} caption"
                            >Copy caption</button>
                        </td>

                        {{-- COL 5: PROOF & STATUS --}}
                        <td class="py-5 align-top wcc-td-proof">
                            <div class="wcc-status-line">
                                <button
                                    type="button"
                                    class="wcc-plain-status js-wcc-status-btn {{ $isPostedRow ? 'is-posted' : '' }}"
                                    data-status="{{ $isPostedRow ? 'posted' : 'pending' }}"
                                    {{ $canEdit ? '' : 'disabled' }}
                                >{!! $isPostedRow ? '&#10003; Posted' : 'Pending' !!}</button>

                                @if ($canEdit)
                                    <button
                                        type="button"
                                        class="wcc-story-inline js-wcc-story-btn {{ ($isStoryRow && !$isPostedRow) ? '' : 'd-none' }}"
                                        data-verified="{{ $plan->is_story_verified ? '1' : '0' }}"
                                        title="Mark 24h Story as Posted"
                                    >&middot; Mark Posted</button>
                                @endif
                            </div>

                            <div class="wcc-url-line mt-2">
                                <input
                                    type="url"
                                    class="wcc-url-line-input js-wcc-proof-input bg-transparent border-0 border-b border-white/10 focus:border-white/30 p-0 pb-1 text-xs w-full"
                                    data-field="proof_url"
                                    value="{{ $proofValue }}"
                                    placeholder="Paste live URL..."
                                    aria-label="{{ $plan->day_of_week }} Live URL"
                                    {{ $canEdit ? '' : 'readonly' }}
                                >
                                <a
                                    href="{{ $proofValue ?: '#' }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="wcc-open-url js-wcc-open-proof {{ filled($proofValue) ? '' : 'd-none' }} no-print"
                                    title="Open live post"
                                >&nearr;</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- MINIMALIST UPLOAD PDF MODAL --}}
@if ($canEdit)
    <div class="wcc-modal-backdrop d-none no-print" id="wccPdfModal" role="dialog" aria-modal="true" aria-labelledby="wccPdfModalTitle">
        <div class="wcc-modal-dialog">
            <div class="wcc-modal-head">
                <div>
                    <h2 class="wcc-modal-title" id="wccPdfModalTitle">Upload Plan PDF</h2>
                    <p class="wcc-modal-sub">Imports Monday–Sunday deliverables, strategy objective, pro-tips &amp; hashtags into <strong>{{ $rangeDisplay }}</strong>.</p>
                </div>
                <button type="button" class="wcc-modal-close" id="wccClosePdfModalBtn" aria-label="Close">&times;</button>
            </div>

            <form
                method="POST"
                action="{{ route('kpi.content-calendar.import-pdf') }}"
                enctype="multipart/form-data"
                id="wccPdfUploadForm"
            >
                @csrf
                <input type="hidden" name="week_start_date" value="{{ $currentWeek }}">

                <label for="wccPdfFileInput" class="wcc-dropzone" id="wccDropzone">
                    <input
                        type="file"
                        id="wccPdfFileInput"
                        name="plan_pdf"
                        accept="application/pdf,.pdf"
                        class="wcc-file-hidden"
                        required
                    >
                    <div id="wccDropzoneIdle">
                        <div class="wcc-dropzone-title" id="wccSelectedFileName">Select or drop weekly plan PDF</div>
                        <div class="wcc-dropzone-hint">PDF up to 10 MB &middot; Preserves existing branch, assignee &amp; posted URLs</div>
                    </div>
                    <div class="d-none" id="wccDropzoneLoading">
                        <span class="wcc-spinner" aria-hidden="true"></span>
                        <div class="wcc-dropzone-title">Extracting 7-Day Plan...</div>
                        <div class="wcc-dropzone-hint">Parsing strategy, pro-tips, hashtags &amp; daily deliverables</div>
                    </div>
                </label>

                <label class="wcc-autodetect-row">
                    <input type="checkbox" name="auto_detect_week" value="1" checked>
                    <span>Auto-map to explicit calendar week if dates appear in PDF</span>
                </label>

                <div class="wcc-modal-error d-none" id="wccPdfModalError" role="alert"></div>

                <div class="wcc-modal-foot">
                    <button type="button" class="wcc-text-action" id="wccCancelPdfModalBtn">Cancel</button>
                    <button type="submit" class="wcc-upload-submit-btn" id="wccSubmitPdfBtn">Import PDF</button>
                </div>
            </form>
        </div>
    </div>

    @if ($hasWeekData)
        <div class="wcc-modal-backdrop d-none no-print" id="wccDeleteModal" role="dialog" aria-modal="true" aria-labelledby="wccDeleteModalTitle">
            <div class="wcc-modal-dialog" style="max-width: 430px;">
                <div class="wcc-modal-head">
                    <div>
                        <h2 class="wcc-modal-title" id="wccDeleteModalTitle">Delete Weekly Content Plan?</h2>
                    </div>
                    <button type="button" class="wcc-modal-close" id="wccCloseDeleteModalBtn" aria-label="Close">&times;</button>
                </div>

                <p class="wcc-modal-sub" style="margin-bottom: 18px;">
                    This will permanently clear all 7 daily slots, captions, strategy notes, and hashtags for <strong>{{ $rangeDisplay }}</strong>. Other weeks will not be affected.
                </p>

                <form method="POST" action="{{ route('kpi.content-calendar.destroy-week') }}" id="wccDeleteWeekForm">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="week_start_date" value="{{ $currentWeek }}">

                    <div class="wcc-modal-foot">
                        <button type="button" class="wcc-text-action" id="wccCancelDeleteModalBtn">Cancel</button>
                        <button type="submit" class="wcc-delete-confirm-btn" id="wccConfirmDeleteBtn">Delete Plan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endif

<div class="wcc-toast no-print" id="wccToast" role="status" aria-live="polite"></div>

<style>
    .wcc-flat-wrap {
        max-width: 100%;
        color: #f8efe6;
    }

    /* Row 1: Page Title & Quiet Actions */
    .wcc-top-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 14px;
    }
    .wcc-page-title {
        font-size: 1.15rem;
        font-weight: 600;
        color: #f8efe6;
        letter-spacing: -0.01em;
        margin: 0;
    }
    .wcc-quiet-actions {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .wcc-text-action {
        background: transparent;
        border: none;
        padding: 0;
        font-size: 0.8rem;
        font-weight: 500;
        color: #b8a29a;
        text-decoration: none;
        cursor: pointer;
        transition: color 0.15s ease;
    }
    .wcc-text-action:hover {
        color: #f8efe6;
    }
    .wcc-text-action.is-accent {
        color: #e5b8a8;
    }
    .wcc-text-action.is-accent:hover {
        color: #f8efe6;
    }
    .wcc-delete-plan-btn {
        background: transparent;
        border: 0;
        padding: 0;
        font-size: 0.75rem;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.50);
        cursor: pointer;
        transition: color 0.15s ease;
    }
    .wcc-delete-plan-btn:hover {
        color: #fb7185;
    }
    .wcc-delete-confirm-btn {
        background: transparent;
        border: 0;
        border-bottom: 1px solid #fb7185;
        padding: 2px 0;
        font-size: 0.82rem;
        font-weight: 600;
        color: #fda4af;
        cursor: pointer;
        transition: color 0.15s ease, opacity 0.15s ease;
    }
    .wcc-delete-confirm-btn:hover:not(:disabled) {
        color: #fff;
    }
    .wcc-delete-confirm-btn:disabled {
        opacity: 0.5;
        cursor: wait;
    }
    .wcc-sep {
        color: rgba(255, 255, 255, 0.18);
        font-size: 0.78rem;
        user-select: none;
    }
    .wcc-save-indicator {
        font-size: 0.75rem;
        color: #9e8981;
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    .wcc-save-indicator.is-visible {
        opacity: 1;
    }

    /* Flat Notices */
    .wcc-flat-notice {
        font-size: 0.8rem;
        padding: 0 0 10px 0;
        margin-bottom: 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.10);
    }
    .wcc-flat-notice.is-success {
        color: #6ee7b7;
    }
    .wcc-flat-notice.is-error {
        color: #fda4af;
    }

    /* Row 2: Flat Week & Execution Hairline Strip */
    .wcc-week-strip {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.10);
        padding-bottom: 12px;
        margin-bottom: 8px;
    }
    .wcc-week-left,
    .wcc-week-right {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 0.82rem;
        color: #9e8981;
    }
    .wcc-week-label {
        color: #f8efe6;
        font-weight: 600;
        font-size: 0.86rem;
    }
    .wcc-week-nav-link {
        color: #9e8981;
        text-decoration: none;
        transition: color 0.15s ease;
    }
    .wcc-week-nav-link:hover {
        color: #f8efe6;
    }
    .wcc-week-today-link {
        color: #e5b8a8;
        font-size: 0.76rem;
        text-decoration: none;
    }
    .wcc-week-today-link:hover {
        color: #f8efe6;
    }
    .wcc-week-right strong {
        color: #f8efe6;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    .wcc-breakdown-text {
        color: #8a756e;
    }

    /* Hidden-by-Default Plain Strategy Block */
    .wcc-strategy-drawer {
        padding-top: 12px;
        padding-bottom: 16px;
        margin-bottom: 8px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.10);
    }
    .wcc-strategy-columns {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
    }
    .wcc-plain-eyebrow {
        display: block;
        font-size: 0.7rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #9e8981;
        margin-bottom: 4px;
    }
    .wcc-plain-copy {
        font-size: 0.84rem;
        line-height: 1.6;
        color: #d8c7c0;
        white-space: pre-line;
        margin: 0;
    }
    .wcc-plain-tips {
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 0.83rem;
        line-height: 1.55;
        color: #d8c7c0;
    }
    .wcc-plain-tip-line strong {
        color: #f8efe6;
        font-weight: 500;
    }
    .wcc-strategy-hashtags-line {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 12px;
    }
    .wcc-hashtags-inline {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        gap: 8px;
    }
    .wcc-hashtags-copy {
        font-size: 0.8rem;
        color: #b8a29a;
    }
    .wcc-strategy-edit-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
        margin-top: 12px;
    }

    /* Flat SLA Strip */
    .wcc-sla-flat {
        padding: 8px 0 12px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.10);
        font-size: 0.79rem;
    }
    .wcc-sla-flat-title {
        color: #fda4af;
        font-weight: 600;
        margin-bottom: 4px;
    }
    .wcc-sla-flat-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        color: #d8c7c0;
        padding: 2px 0;
    }
    .wcc-muted-text {
        color: #8a756e;
    }

    /* Borderless Editorial 7-Day Table */
    .wcc-table-wrap {
        width: 100%;
        overflow-x: visible;
    }
    @media (max-width: 900px) {
        .wcc-table-wrap {
            overflow-x: auto;
        }
    }

    .wcc-flat-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    @media (max-width: 900px) {
        .wcc-flat-table {
            min-width: 820px;
        }
    }

    .wcc-flat-table thead th {
        text-align: left;
        font-size: 0.75rem;
        font-weight: 500;
        color: #9e8981;
        padding: 10px 18px 10px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.10);
        white-space: nowrap;
    }
    .wcc-flat-table thead th:last-child,
    .wcc-flat-table tbody td:last-child {
        padding-right: 0;
    }

    .wcc-th-day { width: 13%; }
    .wcc-th-focus { width: 18%; }
    .wcc-th-visual { width: 27%; }
    .wcc-th-caption { width: 27%; }
    .wcc-th-proof { width: 15%; }

    .wcc-flat-table tbody tr {
        border-bottom: 1px solid rgba(255, 255, 255, 0.10);
    }
    .wcc-flat-table tbody td {
        padding: 20px 18px 20px 0;
        vertical-align: top;
        color: #f8efe6;
    }

    /* Column 1: Day & Assign */
    .wcc-day-name {
        font-size: 0.76rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        color: #f8efe6;
    }
    .wcc-day-sub {
        font-size: 0.75rem;
        color: #8a756e;
        margin-top: 1px;
        font-variant-numeric: tabular-nums;
    }
    .wcc-today-dot {
        color: #e5b8a8;
        font-weight: 500;
    }
    .wcc-select-stack {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 3px;
        margin-top: 8px;
    }

    /* Borderless Inline Selects (Identical to R&D & Sourcing) */
    .wcc-flat-select {
        background-color: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        outline: none !important;
        border-radius: 0 !important;
        color: #d8c7c0;
        font-size: 0.76rem;
        line-height: 1.4;
        padding: 0 14px 0 0 !important;
        max-width: 100%;
        opacity: 0.75;
        cursor: pointer;
        transition: opacity 0.15s ease, color 0.15s ease;
    }
    .wcc-flat-select:hover,
    .wcc-flat-select:focus {
        opacity: 1;
        color: #f8efe6;
    }
    .wcc-flat-select option {
        background-color: #191115;
        color: #f8efe6;
    }

    /* Column 2: Bold Borderless Focus */
    .wcc-flat-focus {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        outline: none !important;
        padding: 0 !important;
        color: #f8efe6;
        font-size: 0.875rem;
        font-weight: 500;
        line-height: 1.45;
        width: 100%;
    }
    .wcc-flat-focus::placeholder {
        color: #6e5c56;
        font-weight: 400;
    }

    /* Column 3 & 4: Full-Height Borderless Auto-Sizing Textareas (Zero Scrollbars, Zero Resize Handles) */
    .wcc-flat-textarea {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        outline: none !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100%;
        min-height: 22px;
        resize: none !important;
        overflow: hidden !important;
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
        color: #d8c7c0;
        font-size: 0.855rem;
        line-height: 1.625;
        display: block;
    }
    .wcc-flat-textarea:focus {
        color: #f8efe6;
    }
    .wcc-flat-textarea::placeholder {
        color: #6e5c56;
    }
    .wcc-flat-textarea::-webkit-scrollbar,
    .wcc-flat-textarea::-webkit-resizer {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }

    .wcc-copy-caption {
        background: transparent;
        border: 0;
        padding: 0;
        margin-top: 6px;
        font-size: 0.74rem;
        color: #d8c7c0;
        opacity: 0.45;
        cursor: pointer;
        transition: opacity 0.15s ease, color 0.15s ease;
    }
    .wcc-copy-caption:hover {
        opacity: 1;
        color: #f8efe6;
    }

    /* Column 5: Minimalist Proof & Status */
    .wcc-status-line {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        line-height: 1.4;
    }
    .wcc-plain-status {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        padding: 0 !important;
        font-size: 0.8rem;
        font-weight: 400;
        color: #8a756e;
        cursor: pointer;
        transition: color 0.15s ease;
    }
    .wcc-plain-status:hover:not(:disabled) {
        color: #d8c7c0;
    }
    .wcc-plain-status.is-posted {
        color: #6ee7b7;
        font-weight: 500;
    }
    .wcc-story-inline {
        background: transparent;
        border: 0;
        padding: 0;
        font-size: 0.75rem;
        color: #b8a29a;
        opacity: 0.75;
        cursor: pointer;
        transition: opacity 0.15s ease, color 0.15s ease;
    }
    .wcc-story-inline:hover {
        opacity: 1;
        color: #6ee7b7;
    }

    .wcc-url-line {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
    }
    .wcc-url-line-input {
        background: transparent !important;
        border: 0 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.10) !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        outline: none !important;
        padding: 0 0 4px 0 !important;
        font-size: 0.75rem;
        color: #d8c7c0;
        width: 100%;
        transition: border-color 0.15s ease, color 0.15s ease;
    }
    .wcc-url-line-input::placeholder {
        color: #6e5c56;
    }
    .wcc-url-line-input:focus {
        border-bottom-color: rgba(255, 255, 255, 0.32) !important;
        color: #f8efe6;
    }
    .wcc-open-url {
        color: #b8a29a;
        font-size: 0.78rem;
        text-decoration: none;
        flex-shrink: 0;
    }
    .wcc-open-url:hover {
        color: #f8efe6;
    }

    /* Minimalist PDF Modal */
    .wcc-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 2050;
        background: rgba(12, 8, 10, 0.78);
        backdrop-filter: blur(3px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .wcc-modal-dialog {
        width: 100%;
        max-width: 460px;
        background: #191115;
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 8px;
        padding: 1.35rem 1.45rem;
        color: #f8efe6;
    }
    .wcc-modal-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }
    .wcc-modal-title {
        font-size: 0.96rem;
        font-weight: 600;
        margin: 0 0 4px 0;
        color: #f8efe6;
    }
    .wcc-modal-sub {
        font-size: 0.77rem;
        color: #9e8981;
        margin: 0;
        line-height: 1.45;
    }
    .wcc-modal-close {
        background: transparent;
        border: 0;
        color: #9e8981;
        font-size: 1.3rem;
        line-height: 1;
        padding: 0;
        cursor: pointer;
    }
    .wcc-dropzone {
        display: block;
        border-top: 1px solid rgba(255, 255, 255, 0.10);
        border-bottom: 1px solid rgba(255, 255, 255, 0.10);
        padding: 1.4rem 0.75rem;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.15s ease;
    }
    .wcc-dropzone:hover,
    .wcc-dropzone.is-dragover {
        border-color: rgba(229, 184, 168, 0.45);
    }
    .wcc-file-hidden {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }
    .wcc-dropzone-title {
        font-size: 0.84rem;
        font-weight: 500;
        color: #f8efe6;
        margin-bottom: 3px;
    }
    .wcc-dropzone-hint {
        font-size: 0.73rem;
        color: #8a756e;
    }
    .wcc-spinner {
        display: inline-block;
        width: 18px;
        height: 18px;
        border: 2px solid rgba(229, 184, 168, 0.22);
        border-top-color: #e5b8a8;
        border-radius: 50%;
        animation: wccSpin 0.75s linear infinite;
        margin-bottom: 6px;
    }
    @keyframes wccSpin {
        to { transform: rotate(360deg); }
    }
    .wcc-autodetect-row {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.75rem;
        color: #9e8981;
        margin-top: 12px;
        cursor: pointer;
    }
    .wcc-modal-error {
        margin-top: 10px;
        font-size: 0.77rem;
        color: #fda4af;
    }
    .wcc-modal-foot {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 16px;
        margin-top: 16px;
    }
    .wcc-upload-submit-btn {
        background: transparent;
        border: 0;
        border-bottom: 1px solid #e5b8a8;
        padding: 2px 0;
        font-size: 0.82rem;
        font-weight: 600;
        color: #f8efe6;
        cursor: pointer;
    }
    .wcc-upload-submit-btn:disabled {
        opacity: 0.5;
        cursor: wait;
    }

    /* Subtle Bottom Toast */
    .wcc-toast {
        position: fixed;
        right: 1.25rem;
        bottom: 1.25rem;
        z-index: 2100;
        padding: 6px 12px;
        background: #191115;
        border-bottom: 1px solid rgba(229, 184, 168, 0.45);
        color: #f8efe6;
        font-size: 0.77rem;
        opacity: 0;
        transform: translateY(6px);
        pointer-events: none;
        transition: opacity 0.16s ease, transform 0.16s ease;
    }
    .wcc-toast.is-visible {
        opacity: 1;
        transform: translateY(0);
    }
    .wcc-toast.is-error {
        border-bottom-color: #fda4af;
        color: #fda4af;
    }

    @media print {
        .no-print {
            display: none !important;
        }
        .wcc-flat-wrap,
        .wcc-flat-table tbody td,
        .wcc-flat-focus,
        .wcc-flat-textarea,
        .wcc-flat-select,
        .wcc-url-line-input,
        .wcc-day-name,
        .wcc-week-label {
            color: #111 !important;
            background: transparent !important;
        }
        .wcc-week-strip,
        .wcc-flat-table thead th,
        .wcc-flat-table tbody tr {
            border-bottom-color: #ccc !important;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || '{{ csrf_token() }}';
    const currentWeek = @json($currentWeek);
    const metaUpdateUrl = @json(route('kpi.content-calendar.update-meta'));

    const toastEl = document.getElementById('wccToast');
    const saveIndicator = document.getElementById('wccSaveIndicator');
    let toastTimer = null;
    let saveTimer = null;

    function flashSaved(label = 'Saved') {
        if (!saveIndicator) return;
        saveIndicator.textContent = label;
        saveIndicator.classList.add('is-visible');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            saveIndicator.classList.remove('is-visible');
        }, 1600);
    }

    function showToast(message, isError = false) {
        if (!isError) {
            flashSaved(message);
            return;
        }
        if (!toastEl || !message) return;
        toastEl.textContent = message;
        toastEl.classList.toggle('is-error', Boolean(isError));
        toastEl.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toastEl.classList.remove('is-visible');
        }, 3000);
    }

    // Full-height auto-sizing textareas (zero scrollbars, zero cut-off lines)
    function autoResizeTextarea(el) {
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
    }

    function autoResizeAllTextareas() {
        document.querySelectorAll('.js-wcc-autosize').forEach(autoResizeTextarea);
    }

    autoResizeAllTextareas();
    requestAnimationFrame(autoResizeAllTextareas);
    window.addEventListener('load', autoResizeAllTextareas);
    window.addEventListener('resize', autoResizeAllTextareas);
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(autoResizeAllTextareas);
    }

    document.querySelectorAll('.js-wcc-autosize').forEach((ta) => {
        ta.addEventListener('input', () => autoResizeTextarea(ta));
        ta.addEventListener('focus', () => autoResizeTextarea(ta));
    });

    function updateWeeklyStatsBanner(stats) {
        if (!stats) return;
        const completedEl = document.getElementById('wccCompletedCount');
        const pctEl = document.getElementById('wccExecutionPct');
        const reelsEl = document.getElementById('wccReelsCount');
        const postsEl = document.getElementById('wccPostsCount');
        const storiesEl = document.getElementById('wccStoriesCount');

        if (completedEl && typeof stats.completed_count !== 'undefined') {
            completedEl.textContent = stats.completed_count;
        }
        if (pctEl && typeof stats.execution_pct !== 'undefined') {
            pctEl.textContent = stats.execution_pct + '%';
        }
        if (reelsEl && typeof stats.reels_count !== 'undefined') {
            reelsEl.textContent = stats.reels_count;
        }
        if (postsEl && typeof stats.posts_count !== 'undefined') {
            postsEl.textContent = stats.posts_count;
        }
        if (storiesEl && typeof stats.stories_count !== 'undefined') {
            storiesEl.textContent = stats.stories_count;
        }
    }

    function isStoryFormatValue(formatStr) {
        return (formatStr || '').trim().toLowerCase().includes('story');
    }

    function syncRowUi(row, plan) {
        if (!row || !plan) return;
        const isPosted = plan.status === 'posted';

        const statusBtn = row.querySelector('.js-wcc-status-btn');
        if (statusBtn) {
            statusBtn.dataset.status = isPosted ? 'posted' : 'pending';
            statusBtn.classList.toggle('is-posted', isPosted);
            statusBtn.innerHTML = isPosted ? '&#10003; Posted' : 'Pending';
        }

        const proofInput = row.querySelector('.js-wcc-proof-input');
        const openLink = row.querySelector('.js-wcc-open-proof');
        if (proofInput && typeof plan.proof_url === 'string') {
            proofInput.value = plan.proof_url;
            proofInput.dataset.lastSaved = plan.proof_url;
        }
        if (openLink) {
            if (plan.proof_url) {
                openLink.href = plan.proof_url;
                openLink.classList.remove('d-none');
            } else {
                openLink.href = '#';
                openLink.classList.add('d-none');
            }
        }

        const storyBtn = row.querySelector('.js-wcc-story-btn');
        if (storyBtn) {
            storyBtn.classList.toggle('d-none', !(plan.is_story_format && !isPosted));
            storyBtn.dataset.verified = plan.is_story_verified ? '1' : '0';
        }
    }

    async function patchRow(row, payload) {
        const res = await fetch(row.dataset.inlineUrl, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.message || 'Unable to save update.');
        }

        syncRowUi(row, data.plan);
        updateWeeklyStatsBanner(data.weekly_stats);
        return data;
    }

    // Inline selects (Branch & Assignee)
    document.querySelectorAll('.js-wcc-field').forEach((sel) => {
        sel.addEventListener('change', async () => {
            const row = sel.closest('.wcc-day-row');
            const field = sel.dataset.field;
            try {
                await patchRow(row, { [field]: sel.value });
                flashSaved('Saved');
            } catch (err) {
                showToast(err.message, true);
            }
        });
    });

    // Format select
    document.querySelectorAll('.js-wcc-format').forEach((sel) => {
        sel.addEventListener('change', async () => {
            const row = sel.closest('.wcc-day-row');
            const statusBtn = row.querySelector('.js-wcc-status-btn');
            const storyBtn = row.querySelector('.js-wcc-story-btn');
            const isPosted = statusBtn?.dataset.status === 'posted';
            if (storyBtn) {
                storyBtn.classList.toggle('d-none', !(isStoryFormatValue(sel.value) && !isPosted));
            }
            try {
                await patchRow(row, { format: sel.value });
                flashSaved('Saved');
            } catch (err) {
                showToast(err.message, true);
            }
        });
    });

    // Focus, Visual Concept & Caption blur save
    document.querySelectorAll('.js-wcc-text-field').forEach((input) => {
        input.dataset.lastSaved = input.value;

        if (input.tagName === 'INPUT') {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur();
                }
            });
        }

        input.addEventListener('blur', async () => {
            if (input.readOnly) return;
            if (input.value === input.dataset.lastSaved) return;
            const row = input.closest('.wcc-day-row');
            const field = input.dataset.field;
            try {
                await patchRow(row, { [field]: input.value });
                input.dataset.lastSaved = input.value;
                flashSaved('Saved');
            } catch (err) {
                showToast(err.message, true);
            }
        });
    });

    // Live Proof URL input
    document.querySelectorAll('.js-wcc-proof-input').forEach((input) => {
        input.dataset.lastSaved = input.value;

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            }
        });

        input.addEventListener('blur', async () => {
            if (input.readOnly) return;
            const trimmed = input.value.trim();
            if (trimmed === (input.dataset.lastSaved || '').trim()) return;
            const row = input.closest('.wcc-day-row');
            try {
                const data = await patchRow(row, { proof_url: trimmed });
                input.dataset.lastSaved = data.plan?.proof_url || '';
                flashSaved(data.plan?.status === 'posted' ? 'Posted' : 'Saved');
            } catch (err) {
                showToast(err.message, true);
            }
        });
    });

    // Status toggle (Pending <-> ✓ Posted)
    document.querySelectorAll('.js-wcc-status-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const row = btn.closest('.wcc-day-row');
            const proofInput = row.querySelector('.js-wcc-proof-input');
            const formatSelect = row.querySelector('.js-wcc-format');
            try {
                const res = await fetch(row.dataset.toggleUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        field: 'posted',
                        proof_url: proofInput ? proofInput.value.trim() : '',
                        format: formatSelect ? formatSelect.value : '',
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || 'Paste a live URL first to mark Posted.');
                }
                syncRowUi(row, data.plan);
                updateWeeklyStatsBanner(data.weekly_stats);
                flashSaved(data.plan?.status === 'posted' ? 'Posted' : 'Pending');
            } catch (err) {
                showToast(err.message, true);
            }
        });
    });

    // Inline "Mark Posted" for 24h Story formats
    document.querySelectorAll('.js-wcc-story-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const row = btn.closest('.wcc-day-row');
            const formatSelect = row.querySelector('.js-wcc-format');
            try {
                const res = await fetch(row.dataset.toggleUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        field: 'story_verified',
                        is_story_verified: true,
                        format: formatSelect ? formatSelect.value : '',
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || 'Unable to verify story.');
                }
                syncRowUi(row, data.plan);
                updateWeeklyStatsBanner(data.weekly_stats);
                flashSaved('Story Posted');
            } catch (err) {
                showToast(err.message, true);
            }
        });
    });

    // Clipboard helper + "Copy caption" below caption
    async function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    }

    document.querySelectorAll('.js-wcc-copy-caption').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const row = btn.closest('.wcc-day-row');
            const ta = row.querySelector('.js-wcc-caption-input');
            const text = (ta?.value || '').trim();
            if (!text) {
                showToast('No caption text to copy yet.', true);
                return;
            }
            try {
                await copyToClipboard(text);
                btn.textContent = '✓ Copied';
                setTimeout(() => { btn.textContent = 'Copy caption'; }, 1500);
            } catch (_) {
                showToast('Could not copy caption.', true);
            }
        });
    });

    // Strategy & Tips Toggle + Copy Hashtags + Inline Save
    const toggleStrategyBtn = document.getElementById('wccToggleStrategyBtn');
    const strategyBody = document.getElementById('wccStrategyBody');
    const copyHashtagsBtn = document.getElementById('wccCopyHashtagsBtn');
    const copyHashtagsSep = document.getElementById('wccCopyHashtagsSep');
    const editStrategyBtn = document.getElementById('wccEditStrategyBtn');
    const cancelStrategyBtn = document.getElementById('wccCancelStrategyBtn');
    const saveStrategyBtn = document.getElementById('wccSaveStrategyBtn');
    const readView = document.getElementById('wccStrategyReadView');
    const editView = document.getElementById('wccStrategyEditView');

    if (toggleStrategyBtn && strategyBody) {
        toggleStrategyBtn.addEventListener('click', () => {
            const isHidden = strategyBody.classList.toggle('d-none');
            toggleStrategyBtn.setAttribute('aria-expanded', String(!isHidden));
            toggleStrategyBtn.innerHTML = isHidden
                ? 'Strategy &amp; Tips &#9662;'
                : 'Strategy &amp; Tips &#9652;';
            if (!isHidden) {
                requestAnimationFrame(autoResizeAllTextareas);
            }
        });
    }

    if (copyHashtagsBtn) {
        copyHashtagsBtn.addEventListener('click', async () => {
            const tags = (copyHashtagsBtn.dataset.hashtags || '').trim();
            if (!tags) return;
            try {
                await copyToClipboard(tags);
                copyHashtagsBtn.textContent = '✓ Copied';
                setTimeout(() => { copyHashtagsBtn.textContent = 'Copy Hashtags'; }, 1500);
            } catch (_) {
                showToast('Could not copy hashtags.', true);
            }
        });
    }

    if (editStrategyBtn && readView && editView) {
        editStrategyBtn.addEventListener('click', () => {
            readView.classList.add('d-none');
            editView.classList.remove('d-none');
            requestAnimationFrame(autoResizeAllTextareas);
        });
    }

    if (cancelStrategyBtn && readView && editView) {
        cancelStrategyBtn.addEventListener('click', () => {
            editView.classList.add('d-none');
            readView.classList.remove('d-none');
        });
    }

    if (saveStrategyBtn) {
        saveStrategyBtn.addEventListener('click', async () => {
            const objective = document.getElementById('wccInputStrategyObjective')?.value || '';
            const proTipsRaw = document.getElementById('wccInputProTips')?.value || '';
            const hashtags = document.getElementById('wccInputHashtags')?.value || '';

            saveStrategyBtn.disabled = true;
            try {
                const res = await fetch(metaUpdateUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        week_start_date: currentWeek,
                        strategy_objective: objective,
                        pro_tips: proTipsRaw,
                        hashtags: hashtags,
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || 'Unable to save strategy.');
                }

                const meta = data.week_meta || {};
                const objDisp = document.getElementById('wccStrategyObjectiveDisplay');
                const tagsDisp = document.getElementById('wccHashtagsDisplay');
                const tipsContainer = document.getElementById('wccProTipsDisplay');

                if (objDisp) objDisp.textContent = meta.strategy_objective || '—';
                if (tagsDisp) tagsDisp.textContent = meta.hashtags || '—';
                if (copyHashtagsBtn) {
                    copyHashtagsBtn.dataset.hashtags = meta.hashtags || '';
                    copyHashtagsBtn.classList.toggle('d-none', !meta.hashtags);
                    copyHashtagsSep?.classList.toggle('d-none', !meta.hashtags);
                }
                if (tipsContainer) {
                    const tips = Array.isArray(meta.pro_tips) ? meta.pro_tips : [];
                    if (tips.length === 0) {
                        tipsContainer.innerHTML = '<span class="wcc-muted-text">No pro-tips configured.</span>';
                    } else {
                        tipsContainer.innerHTML = '';
                        tips.forEach((tip) => {
                            const div = document.createElement('div');
                            div.className = 'wcc-plain-tip-line';
                            const strong = document.createElement('strong');
                            strong.textContent = (tip.title || 'Pro-Tip') + ': ';
                            div.appendChild(strong);
                            div.appendChild(document.createTextNode(tip.body || ''));
                            tipsContainer.appendChild(div);
                        });
                    }
                }

                if (editView && readView) {
                    editView.classList.add('d-none');
                    readView.classList.remove('d-none');
                }
                flashSaved('Strategy saved');
            } catch (err) {
                showToast(err.message, true);
            } finally {
                saveStrategyBtn.disabled = false;
            }
        });
    }

    // Upload PDF Modal
    const pdfModal = document.getElementById('wccPdfModal');
    const openPdfBtn = document.getElementById('wccOpenPdfModalBtn');
    const closePdfBtn = document.getElementById('wccClosePdfModalBtn');
    const cancelPdfBtn = document.getElementById('wccCancelPdfModalBtn');
    const pdfForm = document.getElementById('wccPdfUploadForm');
    const pdfFileInput = document.getElementById('wccPdfFileInput');
    const selectedFileName = document.getElementById('wccSelectedFileName');
    const dropzone = document.getElementById('wccDropzone');
    const dropzoneIdle = document.getElementById('wccDropzoneIdle');
    const dropzoneLoading = document.getElementById('wccDropzoneLoading');
    const submitPdfBtn = document.getElementById('wccSubmitPdfBtn');
    const modalError = document.getElementById('wccPdfModalError');

    if (pdfModal && pdfModal.parentElement !== document.body) {
        document.body.appendChild(pdfModal);
    }
    if (toastEl && toastEl.parentElement !== document.body) {
        document.body.appendChild(toastEl);
    }

    function openPdfModal() {
        if (!pdfModal) return;
        if (modalError) {
            modalError.classList.add('d-none');
            modalError.textContent = '';
        }
        pdfModal.classList.remove('d-none');
    }

    function closePdfModal() {
        if (!pdfModal) return;
        pdfModal.classList.add('d-none');
    }

    openPdfBtn?.addEventListener('click', openPdfModal);
    closePdfBtn?.addEventListener('click', closePdfModal);
    cancelPdfBtn?.addEventListener('click', closePdfModal);
    pdfModal?.addEventListener('click', (e) => {
        if (e.target === pdfModal) closePdfModal();
    });

    pdfFileInput?.addEventListener('change', () => {
        const file = pdfFileInput.files?.[0];
        if (file && selectedFileName) {
            selectedFileName.textContent = file.name;
        }
    });

    if (dropzone && pdfFileInput) {
        ['dragenter', 'dragover'].forEach((evt) => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach((evt) => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', (e) => {
            const files = e.dataTransfer?.files;
            if (files && files.length > 0) {
                pdfFileInput.files = files;
                if (selectedFileName) {
                    selectedFileName.textContent = files[0].name;
                }
            }
        });
    }

    pdfForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!pdfFileInput?.files?.length) {
            if (modalError) {
                modalError.textContent = 'Please select a PDF file.';
                modalError.classList.remove('d-none');
            }
            return;
        }

        if (modalError) modalError.classList.add('d-none');
        dropzoneIdle?.classList.add('d-none');
        dropzoneLoading?.classList.remove('d-none');
        if (submitPdfBtn) submitPdfBtn.disabled = true;

        try {
            const formData = new FormData(pdfForm);
            const res = await fetch(pdfForm.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: formData,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const errMsg = data.errors
                    ? Object.values(data.errors).flat().join(' ')
                    : (data.message || 'Failed to parse PDF plan.');
                throw new Error(errMsg);
            }

            flashSaved('Imported');
            autoResizeAllTextareas();
            window.location.href = data.redirect_url || window.location.href;
        } catch (err) {
            dropzoneLoading?.classList.add('d-none');
            dropzoneIdle?.classList.remove('d-none');
            if (submitPdfBtn) submitPdfBtn.disabled = false;
            if (modalError) {
                modalError.textContent = err.message;
                modalError.classList.remove('d-none');
            }
        }
    });

    // Weekly Reset Confirmation Modal
    const deleteModal = document.getElementById('wccDeleteModal');
    const openDeleteBtn = document.getElementById('wccOpenDeleteModalBtn');
    const closeDeleteBtn = document.getElementById('wccCloseDeleteModalBtn');
    const cancelDeleteBtn = document.getElementById('wccCancelDeleteModalBtn');
    const deleteForm = document.getElementById('wccDeleteWeekForm');
    const confirmDeleteBtn = document.getElementById('wccConfirmDeleteBtn');

    if (deleteModal && deleteModal.parentElement !== document.body) {
        document.body.appendChild(deleteModal);
    }

    function openDeleteModal() {
        if (!deleteModal) return;
        deleteModal.classList.remove('d-none');
    }

    function closeDeleteModal() {
        if (!deleteModal) return;
        deleteModal.classList.add('d-none');
    }

    openDeleteBtn?.addEventListener('click', openDeleteModal);
    closeDeleteBtn?.addEventListener('click', closeDeleteModal);
    cancelDeleteBtn?.addEventListener('click', closeDeleteModal);
    deleteModal?.addEventListener('click', (e) => {
        if (e.target === deleteModal) closeDeleteModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (deleteModal && !deleteModal.classList.contains('d-none')) {
                closeDeleteModal();
            }
            if (pdfModal && !pdfModal.classList.contains('d-none')) {
                closePdfModal();
            }
        }
    });

    deleteForm?.addEventListener('submit', () => {
        if (confirmDeleteBtn) {
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = 'Deleting...';
        }
    });

    // Auto-fade top confirmation flash banner cleanly after 5 seconds if untouched
    document.querySelectorAll('.flash-msg').forEach((banner) => {
        let fadeTimer = null;
        const scheduleFade = () => {
            clearTimeout(fadeTimer);
            fadeTimer = setTimeout(() => {
                banner.classList.add('flash-hide');
                setTimeout(() => banner.remove(), 700);
            }, 5000);
        };
        scheduleFade();
        banner.addEventListener('mouseenter', () => clearTimeout(fadeTimer));
        banner.addEventListener('mouseleave', scheduleFade);
        banner.querySelector('.btn-close')?.addEventListener('click', () => {
            clearTimeout(fadeTimer);
            banner.remove();
        });
    });
});
</script>
@endsection
