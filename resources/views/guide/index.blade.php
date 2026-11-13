@extends('layouts.app')
@section('title', 'Laleen Team Guide')

@section('content')
    @php
        use App\Http\Controllers\GuideController;

        $playbookCount = count($playbooks);
        $hasMultiplePlaybooks = $playbookCount > 1;
        $toneClass = match ($scopeBadge['tone'] ?? 'champagne') {
            'emerald' => 'gd-scope-emerald',
            'amber' => 'gd-scope-amber',
            'rose' => 'gd-scope-rose',
            default => 'gd-scope-champagne',
        };
    @endphp

    <div class="gd-hub-wrapper" id="guideHubRoot" data-initial-tab="{{ $activeTab }}">
        {{-- =====================================================================
             1. CLEAN HERO HEADER
             ===================================================================== --}}
        <header class="gd-hero mb-4">
            <div class="gd-hero-main">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h1 class="gd-hero-title mb-0">Laleen Team Guide</h1>
                        <span class="gd-scope-pill {{ $toneClass }}">{{ $scopeBadge['label'] }}</span>
                    </div>
                    <p class="gd-hero-subtitle mb-0">Your daily workflow, booking rules, and targets.</p>
                </div>

                <div class="gd-hero-tools gd-no-print">
                    <div class="gd-search-wrap">
                        <i class="bx bx-search gd-search-icon"></i>
                        <input
                            type="search"
                            id="gdSearchInput"
                            class="gd-search-input"
                            placeholder="Search workflows or buttons… (/)"
                            autocomplete="off"
                            aria-label="Search guide"
                        >
                        <button type="button" id="gdSearchClear" class="gd-search-clear d-none" title="Clear search">&times;</button>
                    </div>

                    <button type="button" class="gd-print-btn" onclick="window.print()" title="Print or Save PDF">
                        <i class="bx bx-printer"></i>
                        <span>Print / Save PDF</span>
                    </button>
                </div>
            </div>

            @if (!empty($showAdminPreviewBar))
                <div class="gd-preview-row gd-no-print">
                    <span class="gd-preview-label">Preview Role:</span>
                    <div class="gd-preview-pills">
                        @foreach ($previewRoles as $roleKey => $roleLabel)
                            <a href="{{ route('guide.index', $roleKey === 'all' ? [] : ['preview_role' => $roleKey]) }}"
                               class="gd-preview-pill {{ $activePreviewRole === $roleKey ? 'active' : '' }}">
                                {{ $roleLabel }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div id="gdSearchStatus" class="gd-search-status d-none gd-no-print" aria-live="polite"></div>
        </header>

        {{-- =====================================================================
             2. HORIZONTAL CATEGORY PILLS (NO SIDEBAR SQUEEZE)
             ===================================================================== --}}
        @if ($hasMultiplePlaybooks)
            <nav class="gd-cat-bar gd-no-print mb-4" aria-label="Guide Categories">
                <button type="button"
                        class="gd-cat-pill {{ $activeTab === 'all' ? 'active' : '' }}"
                        data-playbook-btn="all">
                    <i class="bx bx-grid-alt"></i>
                    <span>All</span>
                </button>

                @foreach ($playbooks as $pbKey => $playbook)
                    <button type="button"
                            class="gd-cat-pill {{ $activeTab === $pbKey ? 'active' : '' }}"
                            data-playbook-btn="{{ $pbKey }}">
                        <i class="bx {{ $playbook['icon'] }}"></i>
                        <span>{{ $playbook['short_title'] }}</span>
                    </button>
                @endforeach
            </nav>
        @endif

        {{-- =====================================================================
             3. ZERO-MODULE FALLBACK vs. CLEAN FULL-WIDTH PLAYBOOKS
             ===================================================================== --}}
        @if (empty($playbooks))
            <article class="gd-card">
                <div class="gd-card-head">
                    <div class="gd-card-head-left">
                        <span class="gd-card-orb"><i class="bx bx-user-check"></i></span>
                        <h3 class="gd-card-title mb-0">General Account &amp; Getting Module Access</h3>
                    </div>
                    <a href="{{ route('dashboard') }}" class="gd-action-btn gd-no-print">Open Dashboard &nearr;</a>
                </div>

                <div class="gd-track">
                    <div class="gd-track-step">
                        <div class="gd-track-head">
                            <span class="gd-track-dot">1</span>
                            <span class="gd-track-line"></span>
                        </div>
                        <div class="gd-track-title">Account Active</div>
                        <div class="gd-track-desc">Your Laleen CRM login is active and ready.</div>
                    </div>
                    <div class="gd-track-step">
                        <div class="gd-track-head">
                            <span class="gd-track-dot">2</span>
                            <span class="gd-track-line"></span>
                        </div>
                        <div class="gd-track-title">Request Modules</div>
                        <div class="gd-track-desc">Ask your Admin to enable your workspace modules.</div>
                    </div>
                    <div class="gd-track-step">
                        <div class="gd-track-head">
                            <span class="gd-track-dot">3</span>
                        </div>
                        <div class="gd-track-title">Refresh Guide</div>
                        <div class="gd-track-desc">Reload <span class="g-ui-pill">CRM Guide &amp; SOPs</span> once enabled.</div>
                    </div>
                </div>
            </article>
        @else
            <div id="gdPlaybooksContainer">
                @foreach ($playbooks as $pbKey => $playbook)
                    @php
                        $isSectionVisible = ($activeTab === 'all' || $activeTab === $pbKey);
                    @endphp
                    <section class="gd-playbook-section {{ $isSectionVisible ? '' : 'd-none' }}"
                             data-playbook-section="{{ $pbKey }}"
                             id="playbook-{{ $pbKey }}">

                        <div class="gd-section-header">
                            <h2 class="gd-section-title mb-0">{{ $playbook['title'] }}</h2>
                        </div>

                        {{-- ROLE TARGETS STRIP --}}
                        @if (!empty($playbook['targets']))
                            <div class="gd-targets-strip" data-playbook-targets>
                                @foreach ($playbook['targets'] as $target)
                                    <div class="gd-stat-item">
                                        <i class="bx {{ $target['icon'] }} gd-stat-icon"></i>
                                        <div>
                                            <span class="gd-stat-val">{{ $target['value'] }}</span>
                                            <span class="gd-stat-lbl">{{ $target['label'] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- CLEAN 2-ZONE SOP CARDS --}}
                        <div class="gd-cards-stack">
                            @foreach ($playbook['cards'] as $card)
                                <article class="gd-card" data-sop-card id="{{ $card['id'] }}">
                                    {{-- HEADER ROW: ICON + CLEAR TITLE + INLINE TARGET TAG + ACTION BUTTON --}}
                                    <div class="gd-card-head">
                                        <div class="gd-card-head-left">
                                            <span class="gd-card-orb">
                                                <i class="bx {{ $card['icon'] ?? 'bx-layer' }}"></i>
                                            </span>
                                            <div class="d-flex align-items-center flex-wrap gap-2">
                                                <h3 class="gd-card-title mb-0">{{ $card['title'] }}</h3>
                                                @if (!empty($card['target']))
                                                    <span class="gd-target-tag">{{ $card['target'] }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        @if (!empty($card['actions']))
                                            <div class="gd-card-actions gd-no-print">
                                                @foreach ($card['actions'] as $action)
                                                    <a href="{{ $action['url'] }}" class="gd-action-btn">
                                                        {{ $action['label'] }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    {{-- OPTIONAL BORDERLESS INLINE VISUAL STRIPS (ZERO EXTRA BOX WRAPPER) --}}
                                    @if (!empty($card['inline_strip']))
                                        @if ($card['inline_strip'] === 'lead_priority')
                                            <div class="gd-inline-strip" aria-label="Lead Priority Order">
                                                <span class="gd-pill-red">🔴 No Show</span>
                                                <span class="gd-strip-arr">&rarr;</span>
                                                <span class="gd-pill-orange">🟠 Cancelled</span>
                                                <span class="gd-strip-arr">&rarr;</span>
                                                <span class="gd-pill-yellow">🟡 Follow-Up</span>
                                                <span class="gd-strip-arr">&rarr;</span>
                                                <span class="gd-pill-purple">🟣 Combos</span>
                                                <span class="gd-strip-arr">&rarr;</span>
                                                <span class="gd-pill-neutral">⚪ Inquiry</span>
                                            </div>
                                        @elseif ($card['inline_strip'] === 'ads_separation')
                                            <div class="gd-inline-strip" aria-label="Paid Ads Separation Flow">
                                                <span class="gd-pill-neutral">Paid Ads Log &amp; Core Leads = <strong>100% Separate</strong></span>
                                                <span class="gd-strip-arr">&rarr;</span>
                                                <span class="gd-pill-neutral">Shared 8-Digit Phone</span>
                                                <span class="gd-strip-arr">&rarr;</span>
                                                <span class="gd-pill-green">Paid POS Auto-Sync</span>
                                            </div>
                                        @elseif ($card['inline_strip'] === 'manager_window')
                                            <div class="gd-inline-strip" aria-label="Manager Weekly Evaluation Window">
                                                <span class="gd-pill-green">🟢 Mon: Open</span>
                                                <span class="gd-strip-dot">&middot;</span>
                                                <span class="gd-pill-green">🟢 Tue: Open</span>
                                                <span class="gd-strip-dot">&middot;</span>
                                                <span class="gd-pill-muted">🔒 Wed&ndash;Sun: Locked</span>
                                            </div>
                                        @endif
                                    @endif

                                    {{-- ZONE 1: BORDERLESS HORIZONTAL STEP TRACK (1 ─── 2 ─── 3) --}}
                                    <div class="gd-track">
                                        @foreach ($card['flow'] as $idx => $step)
                                            <div class="gd-track-step">
                                                <div class="gd-track-head">
                                                    <span class="gd-track-dot">{{ $idx + 1 }}</span>
                                                    @if (!$loop->last)
                                                        <span class="gd-track-line" aria-hidden="true"></span>
                                                    @endif
                                                </div>
                                                <div class="gd-track-title">{{ $step['title'] }}</div>
                                                <div class="gd-track-desc">{!! GuideController::renderInlineChips($step['desc']) !!}</div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- ZONE 2: SINGLE-LINE HAIRLINE GUARDRAIL FOOTER (✓ · ✕) --}}
                                    <div class="gd-guardrail-row">
                                        <div class="gd-guard-item gd-guard-do">
                                            <span class="gd-guard-icon-do">&check;</span>
                                            <span>{!! GuideController::renderInlineChips($card['do_rule']) !!}</span>
                                        </div>
                                        <div class="gd-guard-item gd-guard-avoid">
                                            <span class="gd-guard-icon-avoid">&times;</span>
                                            <span>{!! GuideController::renderInlineChips($card['avoid_rule']) !!}</span>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <div id="gdEmptySearchState" class="gd-card text-center py-5 d-none">
                    <h4 class="mb-1" style="color: #f8efe6; font-size: 16px;">No matching workflows</h4>
                    <p class="text-muted small mb-3">Try searching for "Booking Agent", "Combo", "Monday", or "Checkout".</p>
                    <button type="button" class="gd-action-btn mx-auto" id="gdResetSearchBtn">Clear Search</button>
                </div>
            </div>
        @endif
    </div>

    <style>
        .gd-hub-wrapper {
            --gd-bg-1: rgba(34, 22, 27, 0.92);
            --gd-bg-2: rgba(21, 14, 18, 0.96);
            --gd-border: rgba(229, 184, 168, 0.14);
            --gd-border-strong: rgba(229, 184, 168, 0.3);
            --gd-hairline: rgba(255, 255, 255, 0.07);
            --gd-rose: #e5b8a8;
            --gd-champagne: #d4a373;
            --gd-cream: #f8efe6;
            --gd-muted: #b8a29a;
            max-width: 1040px;
            margin: 0 auto;
            padding-bottom: 32px;
            color: var(--gd-cream);
        }

        /* INLINE UI BUTTON CHIP */
        .g-ui-pill {
            display: inline-flex;
            align-items: center;
            padding: 1px 7px;
            margin: 0 1px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            line-height: 1.45;
            background: rgba(229, 184, 168, 0.15);
            border: 1px solid rgba(229, 184, 168, 0.34);
            color: #fcefe9;
            white-space: nowrap;
        }

        /* 1. HERO HEADER */
        .gd-hero {
            padding: 4px 0 16px;
            border-bottom: 1px solid var(--gd-hairline);
        }

        .gd-hero-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .gd-hero-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--gd-cream);
            letter-spacing: -0.015em;
        }

        .gd-hero-subtitle {
            font-size: 13.5px;
            color: var(--gd-muted);
            margin-top: 2px;
        }

        .gd-scope-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .gd-scope-emerald {
            background: rgba(52, 211, 153, 0.12);
            border: 1px solid rgba(52, 211, 153, 0.34);
            color: #6ee7b7;
        }

        .gd-scope-amber {
            background: rgba(245, 158, 11, 0.14);
            border: 1px solid rgba(245, 158, 11, 0.38);
            color: #fcd34d;
        }

        .gd-scope-rose {
            background: rgba(229, 184, 168, 0.14);
            border: 1px solid rgba(229, 184, 168, 0.34);
            color: #f3d2c6;
        }

        .gd-scope-champagne {
            background: rgba(212, 163, 115, 0.14);
            border: 1px solid rgba(212, 163, 115, 0.34);
            color: #f3d9a4;
        }

        .gd-hero-tools {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .gd-search-wrap {
            position: relative;
            width: 250px;
        }

        .gd-search-icon {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gd-muted);
            font-size: 15px;
            pointer-events: none;
        }

        .gd-search-input {
            width: 100%;
            height: 36px;
            padding: 5px 28px 5px 34px;
            border-radius: 9px;
            border: 1px solid var(--gd-border-strong);
            background: rgba(20, 13, 16, 0.72);
            color: var(--gd-cream);
            font-size: 12.5px;
            outline: none;
            transition: border-color 0.16s ease;
        }

        .gd-search-input::placeholder {
            color: #8f7a73;
        }

        .gd-search-input:focus {
            border-color: var(--gd-rose);
        }

        .gd-search-clear {
            position: absolute;
            right: 7px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: var(--gd-muted);
            font-size: 17px;
            line-height: 1;
            cursor: pointer;
            padding: 2px 4px;
        }

        .gd-print-btn {
            height: 36px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0 13px;
            border-radius: 9px;
            border: 1px solid var(--gd-border-strong);
            background: rgba(229, 184, 168, 0.08);
            color: var(--gd-cream);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.16s ease;
            white-space: nowrap;
        }

        .gd-print-btn:hover {
            background: rgba(229, 184, 168, 0.18);
        }

        /* ADMIN PREVIEW COMPACT LINE */
        .gd-preview-row {
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .gd-preview-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--gd-muted);
        }

        .gd-preview-pills {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .gd-preview-pill {
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            text-decoration: none;
            color: var(--gd-muted);
            border: 1px solid var(--gd-border);
            background: transparent;
            transition: all 0.15s ease;
        }

        .gd-preview-pill:hover {
            color: var(--gd-cream);
            border-color: var(--gd-border-strong);
        }

        .gd-preview-pill.active {
            background: rgba(229, 184, 168, 0.2);
            border-color: var(--gd-rose);
            color: var(--gd-cream);
        }

        .gd-search-status {
            margin-top: 8px;
            font-size: 12px;
            color: #f3d9a4;
        }

        /* 2. HORIZONTAL CATEGORY PILLS */
        .gd-cat-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .gd-cat-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 999px;
            border: 1px solid var(--gd-border);
            background: rgba(28, 18, 22, 0.72);
            color: var(--gd-muted);
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.16s ease;
        }

        .gd-cat-pill:hover {
            color: var(--gd-cream);
            border-color: var(--gd-border-strong);
        }

        .gd-cat-pill.active {
            background: linear-gradient(135deg, #d98f83, #c19a8c);
            border-color: transparent;
            color: #1c110f;
            font-weight: 700;
        }

        /* 3. PLAYBOOK SECTION & TARGET STRIP */
        .gd-playbook-section {
            margin-bottom: 34px;
        }

        .gd-section-header {
            margin-bottom: 12px;
        }

        .gd-section-title {
            font-size: 17px;
            font-weight: 800;
            color: var(--gd-cream);
            letter-spacing: -0.01em;
        }

        .gd-targets-strip {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 22px;
            padding: 12px 18px;
            margin-bottom: 16px;
            border-radius: 13px;
            background: rgba(34, 22, 27, 0.65);
            border: 1px solid var(--gd-border);
        }

        .gd-stat-item {
            display: inline-flex;
            align-items: center;
            gap: 9px;
        }

        .gd-stat-icon {
            font-size: 18px;
            color: var(--gd-champagne);
        }

        .gd-stat-val {
            font-size: 15px;
            font-weight: 800;
            color: var(--gd-cream);
            margin-right: 5px;
        }

        .gd-stat-lbl {
            font-size: 12px;
            color: var(--gd-muted);
        }

        /* 4. CLEAN 2-ZONE SOP CARDS (ZERO NESTED BOXES) */
        .gd-cards-stack {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .gd-card {
            background: linear-gradient(180deg, var(--gd-bg-1) 0%, var(--gd-bg-2) 100%);
            border: 1px solid var(--gd-border);
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.26);
            transition: border-color 0.18s ease;
        }

        .gd-card:hover {
            border-color: var(--gd-border-strong);
        }

        .gd-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .gd-card-head-left {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .gd-card-orb {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(229, 184, 168, 0.12);
            color: var(--gd-rose);
            font-size: 18px;
        }

        .gd-card-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--gd-cream);
            letter-spacing: -0.01em;
        }

        .gd-target-tag {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 9px;
            border-radius: 999px;
            background: rgba(212, 163, 115, 0.12);
            color: #f3d9a4;
        }

        .gd-card-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .gd-action-btn {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            background: linear-gradient(135deg, #d98f83, #c19a8c);
            color: #1c110f !important;
            border: none;
            cursor: pointer;
            transition: opacity 0.15s ease;
            white-space: nowrap;
        }

        .gd-action-btn:hover {
            opacity: 0.92;
        }

        /* BORDERLESS INLINE VISUAL STRIPS */
        .gd-inline-strip {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
            margin-bottom: 16px;
        }

        .gd-pill-red,
        .gd-pill-orange,
        .gd-pill-yellow,
        .gd-pill-purple,
        .gd-pill-green,
        .gd-pill-neutral,
        .gd-pill-muted {
            font-size: 11.5px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 999px;
        }

        .gd-pill-red { background: rgba(244, 63, 94, 0.14); color: #fda4af; }
        .gd-pill-orange { background: rgba(249, 115, 22, 0.14); color: #fdba74; }
        .gd-pill-yellow { background: rgba(234, 179, 8, 0.14); color: #fde047; }
        .gd-pill-purple { background: rgba(168, 85, 247, 0.14); color: #d8b4fe; }
        .gd-pill-green { background: rgba(52, 211, 153, 0.14); color: #6ee7b7; }
        .gd-pill-neutral { background: rgba(255, 255, 255, 0.06); color: #e7dbd6; }
        .gd-pill-muted { background: rgba(255, 255, 255, 0.04); color: var(--gd-muted); }

        .gd-strip-arr,
        .gd-strip-dot {
            color: var(--gd-muted);
            font-size: 12px;
        }

        /* ZONE 1: BORDERLESS HORIZONTAL STEP TRACK (1 ─── 2 ─── 3) */
        .gd-track {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .gd-track-step {
            min-width: 0;
        }

        .gd-track-head {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .gd-track-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11.5px;
            font-weight: 800;
            background: rgba(229, 184, 168, 0.18);
            border: 1px solid rgba(229, 184, 168, 0.42);
            color: var(--gd-cream);
        }

        .gd-track-line {
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, rgba(229, 184, 168, 0.34), rgba(229, 184, 168, 0.06));
        }

        .gd-track-title {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--gd-cream);
            margin-bottom: 3px;
        }

        .gd-track-desc {
            font-size: 12.5px;
            line-height: 1.5;
            color: #d8c8c1;
        }

        @media (max-width: 767.98px) {
            .gd-track {
                grid-template-columns: 1fr;
                gap: 14px;
            }
            .gd-track-line {
                display: none;
            }
        }

        /* ZONE 2: SINGLE-LINE HAIRLINE GUARDRAIL FOOTER */
        .gd-guardrail-row {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid var(--gd-hairline);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            font-size: 12px;
            line-height: 1.45;
        }

        .gd-guard-item {
            display: inline-flex;
            align-items: baseline;
            gap: 6px;
            flex: 1 1 280px;
        }

        .gd-guard-do {
            color: #d9ece4;
        }

        .gd-guard-avoid {
            color: #ebd4d8;
        }

        .gd-guard-icon-do {
            font-weight: 800;
            color: #6ee7b7;
            flex-shrink: 0;
        }

        .gd-guard-icon-avoid {
            font-weight: 800;
            color: #fda4af;
            flex-shrink: 0;
        }

        /* CLEAN PRINT / SAVE PDF STYLESHEET */
        @media print {
            #layout-menu,
            #layout-navbar,
            .gd-no-print,
            footer {
                display: none !important;
            }

            body,
            .layout-wrapper,
            .layout-page,
            .content-wrapper,
            .container-xxl {
                background: #ffffff !important;
                color: #111111 !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
            }

            .gd-hub-wrapper {
                max-width: 100% !important;
                color: #111111 !important;
            }

            .gd-playbook-section,
            .gd-card {
                display: block !important;
                background: #ffffff !important;
                color: #111111 !important;
                border: 1px solid #cccccc !important;
                box-shadow: none !important;
                break-inside: avoid;
                margin-bottom: 14px !important;
            }

            .gd-targets-strip {
                background: #f8f8f8 !important;
                border: 1px solid #cccccc !important;
            }

            .gd-hero-title,
            .gd-section-title,
            .gd-card-title,
            .gd-stat-val,
            .gd-track-title,
            .gd-track-desc,
            .gd-guard-do,
            .gd-guard-avoid,
            .g-ui-pill {
                color: #111111 !important;
            }

            .g-ui-pill {
                background: #eeeeee !important;
                border: 1px solid #999999 !important;
            }

            .gd-hero-subtitle,
            .gd-stat-lbl {
                color: #444444 !important;
            }

            .gd-guardrail-row {
                border-top: 1px solid #dddddd !important;
            }
        }
    </style>

    <script>
        (function () {
            const root = document.getElementById('guideHubRoot');
            if (!root) return;

            const navButtons = Array.from(document.querySelectorAll('[data-playbook-btn]'));
            const sections = Array.from(document.querySelectorAll('[data-playbook-section]'));
            const searchInput = document.getElementById('gdSearchInput');
            const searchClear = document.getElementById('gdSearchClear');
            const searchStatus = document.getElementById('gdSearchStatus');
            const emptyState = document.getElementById('gdEmptySearchState');
            const resetSearchBtn = document.getElementById('gdResetSearchBtn');

            let activePlaybook = root.getAttribute('data-initial-tab') || 'all';

            function applyFilter() {
                const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
                const isSearching = query.length > 0;

                if (searchClear) {
                    searchClear.classList.toggle('d-none', !isSearching);
                }

                let matchedCards = 0;

                sections.forEach(section => {
                    const pbKey = section.getAttribute('data-playbook-section');
                    const sectionCards = Array.from(section.querySelectorAll('[data-sop-card]'));
                    const sectionHeaderText = (section.querySelector('.gd-section-header')?.textContent || '').toLowerCase();
                    const targetsText = (section.querySelector('[data-playbook-targets]')?.textContent || '').toLowerCase();

                    if (!isSearching) {
                        sectionCards.forEach(c => c.classList.remove('d-none'));
                        const showSection = (activePlaybook === 'all' || activePlaybook === pbKey);
                        section.classList.toggle('d-none', !showSection);
                        if (showSection) {
                            matchedCards += sectionCards.length;
                        }
                        return;
                    }

                    let visibleInSection = 0;
                    sectionCards.forEach(card => {
                        const haystack = (card.textContent + ' ' + sectionHeaderText + ' ' + targetsText).toLowerCase();
                        const hit = haystack.includes(query);
                        card.classList.toggle('d-none', !hit);
                        if (hit) {
                            visibleInSection++;
                            matchedCards++;
                        }
                    });

                    section.classList.toggle('d-none', visibleInSection === 0);
                });

                if (searchStatus) {
                    if (isSearching) {
                        searchStatus.classList.remove('d-none');
                        searchStatus.textContent = matchedCards === 1
                            ? `1 workflow matched "${searchInput.value.trim()}"`
                            : `${matchedCards} workflows matched "${searchInput.value.trim()}"`;
                    } else {
                        searchStatus.classList.add('d-none');
                        searchStatus.textContent = '';
                    }
                }

                if (emptyState) {
                    emptyState.classList.toggle('d-none', !(isSearching && matchedCards === 0));
                }
            }

            navButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    activePlaybook = this.getAttribute('data-playbook-btn') || 'all';
                    navButtons.forEach(b => b.classList.toggle('active', b === this));

                    if (searchInput && searchInput.value.trim() !== '') {
                        searchInput.value = '';
                    }

                    try {
                        const url = new URL(window.location.href);
                        url.searchParams.set('tab', activePlaybook);
                        window.history.replaceState({}, '', url.toString());
                    } catch (e) {}

                    applyFilter();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', applyFilter);
            }

            function clearSearch() {
                if (!searchInput) return;
                searchInput.value = '';
                applyFilter();
                searchInput.focus();
            }

            if (searchClear) {
                searchClear.addEventListener('click', clearSearch);
            }
            if (resetSearchBtn) {
                resetSearchBtn.addEventListener('click', clearSearch);
            }

            document.addEventListener('keydown', function (e) {
                if (!searchInput) return;
                const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
                const isInputFocused = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target?.isContentEditable;

                if (((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') || (!isInputFocused && e.key === '/')) {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                } else if (e.key === 'Escape' && document.activeElement === searchInput && searchInput.value !== '') {
                    e.preventDefault();
                    clearSearch();
                }
            });
        })();
    </script>
@endsection
