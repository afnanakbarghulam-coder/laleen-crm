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
             TOP SUB-TAB NAVIGATION (GUIDES · PRESENTATIONS · BRAINSTORM & IDEAS)
             ===================================================================== --}}
        @php
            $currentSection = $activeSection ?? 'guides';
        @endphp
        <nav class="gd-top-subtabs gd-no-print mb-4" aria-label="CRM Guides Sections">
            <button type="button"
                    class="gd-subtab-btn {{ $currentSection === 'guides' ? 'active' : '' }}"
                    data-guide-section-btn="guides">
                <i class="bx bx-book-open"></i>
                <span>Role &amp; SOP Guides</span>
            </button>

            <button type="button"
                    class="gd-subtab-btn {{ $currentSection === 'presentations' ? 'active' : '' }}"
                    data-guide-section-btn="presentations">
                <i class="bx bx-slideshow"></i>
                <span>Presentations</span>
                <span class="gd-subtab-count">{{ isset($presentations) ? $presentations->count() : 0 }}</span>
            </button>

            <button type="button"
                    class="gd-subtab-btn {{ $currentSection === 'ideas' ? 'active' : '' }}"
                    data-guide-section-btn="ideas">
                <i class="bx bx-bulb"></i>
                <span>Brainstorm &amp; Ideas</span>
                <span class="gd-subtab-count">{{ isset($ideas) ? $ideas->count() : 0 }}</span>
            </button>
        </nav>

        <div id="gdSectionGuides" data-guide-section="guides" class="{{ $currentSection === 'guides' ? '' : 'd-none' }}">
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
                    <p class="text-muted small mb-3">Try searching for "Booking Agent", "Combo", "Complaint", "Monday", or "Checkout".</p>
                    <button type="button" class="gd-action-btn mx-auto" id="gdResetSearchBtn">Clear Search</button>
                </div>
            </div>
        @endif
        </div>

        <div id="gdSectionPresentations" data-guide-section="presentations" class="{{ $currentSection === 'presentations' ? '' : 'd-none' }}">
            @include('guide.partials.presentations')
        </div>

        <div id="gdSectionIdeas" data-guide-section="ideas" class="{{ $currentSection === 'ideas' ? '' : 'd-none' }}">
            @include('guide.partials.ideas')
        </div>
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
        .g-ui-pill,
        .ui-chip {
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
            gap: 12px 20px;
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
            .g-ui-pill,
            .ui-chip {
                color: #111111 !important;
            }

            .g-ui-pill,
            .ui-chip {
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

        /* TOP SUB-TABS */
        .gd-top-subtabs {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            padding: 5px;
            border-radius: 14px;
            background: rgba(20, 13, 16, 0.78);
            border: 1px solid var(--gd-border);
        }

        .gd-subtab-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            border-radius: 10px;
            border: 1px solid transparent;
            background: transparent;
            color: var(--gd-muted);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.16s ease;
        }

        .gd-subtab-btn:hover {
            color: var(--gd-cream);
        }

        .gd-subtab-btn.active {
            background: linear-gradient(135deg, rgba(217, 143, 131, 0.22), rgba(212, 163, 115, 0.18));
            border-color: rgba(229, 184, 168, 0.36);
            color: var(--gd-cream);
        }

        .gd-subtab-count {
            font-size: 11px;
            font-weight: 800;
            padding: 1px 7px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            color: #f3d9a4;
        }

        .gd-subbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .gd-status-filter-row {
            display: flex;
            align-items: center;
            gap: 7px;
            flex-wrap: wrap;
        }

        .gd-ghost-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--gd-border-strong);
            background: rgba(255, 255, 255, 0.04);
            color: var(--gd-cream);
            cursor: pointer;
            transition: background 0.15s ease;
            white-space: nowrap;
        }

        .gd-ghost-btn:hover {
            background: rgba(229, 184, 168, 0.14);
        }

        .gd-ghost-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .gd-danger-btn {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid rgba(244, 63, 94, 0.34);
            background: rgba(244, 63, 94, 0.1);
            color: #fda4af;
            cursor: pointer;
            white-space: nowrap;
        }

        .gd-danger-btn:hover {
            background: rgba(244, 63, 94, 0.2);
        }

        .gd-muted-caption {
            font-size: 12px;
            color: var(--gd-muted);
        }

        /* MINIMALISTIC OUTSIDE PRESENTATION CARD */
        .gd-pres-card {
            padding: 18px 22px;
        }

        .gd-pres-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .gd-pres-main {
            flex: 1 1 auto;
            min-width: 0;
        }

        .gd-pres-summary {
            font-size: 13px;
            line-height: 1.52;
            color: var(--gd-muted);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            max-width: 720px;
        }

        .gd-pres-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            flex-wrap: wrap;
        }

        @media (max-width: 767.98px) {
            .gd-pres-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }

            .gd-pres-actions {
                width: 100%;
            }
        }

        /* 16:9 SLIDE DECK PLAYER */
        .gd-player-shell {
            background: linear-gradient(180deg, rgba(28, 18, 23, 0.98) 0%, rgba(16, 10, 14, 0.99) 100%);
            border: 1px solid rgba(229, 184, 168, 0.28);
            border-radius: 18px;
            padding: 18px 22px;
            box-shadow: 0 20px 48px rgba(0, 0, 0, 0.48);
        }

        .gd-player-shell.gd-is-fullscreen,
        .gd-player-shell:fullscreen {
            position: fixed;
            inset: 0;
            z-index: 9999;
            border-radius: 0;
            display: flex !important;
            flex-direction: column;
            justify-content: space-between;
            padding: 24px 36px;
            overflow-y: auto;
        }

        .gd-player-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .gd-player-deck-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--gd-cream);
        }

        .gd-slide-counter {
            font-size: 12px;
            font-weight: 700;
            color: #f3d9a4;
            padding: 3px 10px;
            border-radius: 999px;
            background: rgba(212, 163, 115, 0.12);
        }

        .gd-player-progress-track {
            width: 100%;
            height: 3px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            overflow: hidden;
            margin-bottom: 18px;
        }

        .gd-player-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #d98f83, #d4a373);
            transition: width 0.22s ease;
        }

        .gd-slide-stage {
            aspect-ratio: 16 / 9;
            min-height: 380px;
            width: 100%;
            border-radius: 14px;
            padding: 32px 38px;
            background: radial-gradient(circle at top right, rgba(229, 184, 168, 0.1), transparent 54%), rgba(24, 15, 19, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            outline: none;
            overflow-y: auto;
        }

        .gd-player-shell:fullscreen .gd-slide-stage,
        .gd-player-shell.gd-is-fullscreen .gd-slide-stage {
            flex: 1 1 auto;
            aspect-ratio: auto;
            padding: 46px 58px;
        }

        @media (max-width: 767.98px) {
            .gd-slide-stage {
                aspect-ratio: auto;
                min-height: 320px;
                padding: 22px 20px;
            }
        }

        .gd-slide-canvas {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            justify-content: center;
            width: 100%;
        }

        .gd-slide-eyebrow {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.11em;
            text-transform: uppercase;
            color: var(--gd-champagne);
            margin-bottom: 8px;
        }

        .gd-slide-heading {
            font-size: clamp(20px, 2.3vw, 28px);
            font-weight: 800;
            color: var(--gd-cream);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }

        .gd-slide-subtitle {
            font-size: 13.5px;
            line-height: 1.55;
            color: var(--gd-muted);
            margin-bottom: 16px;
            max-width: 760px;
        }

        .gd-slide-head-bar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .gd-slide-metric-pill {
            display: inline-flex;
            align-items: center;
            padding: 5px 14px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 800;
            background: linear-gradient(135deg, rgba(217, 143, 131, 0.2), rgba(212, 163, 115, 0.2));
            border: 1px solid rgba(229, 184, 168, 0.42);
            color: #fcefe9;
            white-space: nowrap;
        }

        /* 1. COVER LAYOUT */
        .gd-layout-cover {
            display: flex;
            flex-direction: column;
            justify-content: center;
            max-width: 780px;
            padding: 12px 0;
        }

        .gd-cover-accent-bar {
            width: 68px;
            height: 3px;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--gd-rose), var(--gd-champagne));
            margin: 14px 0 18px;
        }

        .gd-cover-title {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(28px, 3.6vw, 46px);
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.02em;
            color: var(--gd-cream);
            margin: 0;
        }

        .gd-cover-subtitle {
            font-size: clamp(14px, 1.4vw, 17px);
            line-height: 1.62;
            color: #e6d6cf;
            margin: 0 0 18px;
            max-width: 680px;
        }

        /* 2. BIG METRIC LAYOUT */
        .gd-layout-big-metric {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            padding: 10px 0;
            max-width: 760px;
        }

        .gd-big-metric-figure {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: clamp(46px, 6.2vw, 78px);
            font-weight: 900;
            line-height: 1.04;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, #fff6f0 0%, #e5b8a8 55%, #d4a373 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            color: var(--gd-rose);
            margin: 4px 0 14px;
        }

        .gd-big-metric-label {
            font-size: clamp(18px, 2vw, 24px);
            font-weight: 800;
            color: var(--gd-cream);
            margin-bottom: 8px;
        }

        .gd-big-metric-takeaway {
            font-size: 14.5px;
            line-height: 1.6;
            color: #e4d4cd;
            max-width: 680px;
            margin: 0;
        }

        .gd-slide-points {
            list-style: none;
            padding: 0;
            margin: 4px 0 0;
            display: flex;
            flex-direction: column;
            gap: 9px;
            max-width: 740px;
        }

        .gd-slide-points li {
            position: relative;
            padding-left: 18px;
            font-size: 13.5px;
            line-height: 1.56;
            color: #eadcd6;
        }

        .gd-slide-points li::before {
            content: "";
            position: absolute;
            left: 0;
            top: 8px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--gd-rose);
        }

        /* 3. TIMELINE LAYOUT (3 TO 5 HORIZONTAL STEP CARDS) */
        .gd-timeline-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(148px, 1fr));
            gap: 12px;
            width: 100%;
            margin-top: 4px;
        }

        .gd-timeline-card {
            padding: 14px 14px 15px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.025);
            border-top: 2px solid rgba(229, 184, 168, 0.45);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .gd-timeline-badge {
            display: inline-flex;
            align-self: flex-start;
            padding: 2px 9px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: rgba(229, 184, 168, 0.14);
            color: var(--gd-rose);
        }

        .gd-timeline-title {
            font-size: 13.5px;
            font-weight: 800;
            color: var(--gd-cream);
            line-height: 1.3;
        }

        .gd-timeline-desc {
            font-size: 12px;
            line-height: 1.5;
            color: var(--gd-muted);
        }

        /* 4. COMPARISON LAYOUT (2-COLUMN SIDE-BY-SIDE) */
        .gd-comparison-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            width: 100%;
            margin-top: 4px;
        }

        @media (max-width: 767.98px) {
            .gd-comparison-grid {
                grid-template-columns: 1fr;
            }
        }

        .gd-comparison-card {
            padding: 18px 20px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid rgba(229, 184, 168, 0.16);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .gd-comparison-card:first-child {
            background: linear-gradient(180deg, rgba(229, 184, 168, 0.08) 0%, rgba(255, 255, 255, 0.02) 100%);
            border-color: rgba(229, 184, 168, 0.32);
        }

        .gd-comparison-tag {
            display: inline-flex;
            align-self: flex-start;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            background: rgba(212, 163, 115, 0.16);
            color: #f5dec9;
        }

        .gd-comparison-title {
            font-size: 16px;
            font-weight: 800;
            color: var(--gd-cream);
        }

        .gd-comparison-desc {
            font-size: 13px;
            line-height: 1.58;
            color: #e4d5cf;
        }

        /* 5. CARDS LAYOUT (3 OR 4 CLEAN GRID TILES) */
        .gd-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(195px, 1fr));
            gap: 13px;
            width: 100%;
            margin-top: 4px;
        }

        .gd-consulting-tile {
            padding: 15px 16px;
            border-radius: 13px;
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid rgba(255, 255, 255, 0.07);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .gd-tile-num {
            min-width: 28px;
            width: fit-content;
            align-self: flex-start;
            padding: 0 9px;
            height: 26px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.03em;
            background: rgba(229, 184, 168, 0.15);
            color: var(--gd-rose);
        }

        .gd-tile-title {
            font-size: 14px;
            font-weight: 800;
            color: var(--gd-cream);
        }

        .gd-tile-desc {
            font-size: 12.5px;
            line-height: 1.5;
            color: var(--gd-muted);
        }

        /* 6. TABLE LAYOUT */
        .gd-slide-table-wrap {
            margin-top: 4px;
            margin-bottom: 8px;
            overflow-x: auto;
        }

        .gd-slide-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }

        .gd-slide-table th {
            text-align: left;
            padding: 8px 11px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--gd-champagne);
            border-bottom: 1px solid rgba(229, 184, 168, 0.26);
        }

        .gd-slide-table td {
            padding: 8px 11px;
            color: #efe3de;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            vertical-align: top;
        }

        .gd-slide-table td:first-child {
            font-weight: 700;
            color: var(--gd-cream);
            white-space: nowrap;
        }

        .gd-slide-footer {
            margin-top: 16px;
            padding-top: 11px;
            border-top: 1px solid rgba(255, 255, 255, 0.07);
            font-size: 12px;
            color: var(--gd-muted);
        }

        .gd-layout-chip {
            text-transform: capitalize;
        }

        .gd-code-textarea {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            line-height: 1.55;
        }

        .gd-json-error-alert {
            padding: 11px 14px;
            border-radius: 10px;
            background: rgba(253, 164, 175, 0.1);
            border: 1px solid rgba(253, 164, 175, 0.32);
            color: #fecdd3;
            font-size: 12.5px;
            line-height: 1.5;
        }

        .gd-move-btn {
            padding: 3px 9px;
            font-size: 11.5px;
            line-height: 1.2;
        }

        .gd-player-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 14px;
        }

        .gd-slide-dots {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .gd-slide-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            border: none;
            padding: 0;
            background: rgba(255, 255, 255, 0.18);
            cursor: pointer;
            transition: all 0.16s ease;
        }

        .gd-slide-dot.active {
            width: 24px;
            background: var(--gd-rose);
        }

        /* BRAINSTORM & IDEAS */
        .gd-idea-desc {
            font-size: 13.5px;
            line-height: 1.6;
            color: #e6d7d0;
            white-space: pre-line;
        }

        .gd-upvote-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            border: 1px solid var(--gd-border-strong);
            background: rgba(255, 255, 255, 0.04);
            color: var(--gd-cream);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .gd-upvote-btn:hover {
            border-color: var(--gd-rose);
        }

        .gd-upvote-btn.voted {
            background: linear-gradient(135deg, rgba(217, 143, 131, 0.28), rgba(212, 163, 115, 0.22));
            border-color: var(--gd-rose);
            color: #fff6f2;
        }

        .gd-upvote-count {
            padding: 1px 7px;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.26);
            font-size: 11.5px;
        }

        .gd-comments-drawer {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid var(--gd-hairline);
        }

        .gd-comments-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .gd-comment-row {
            padding-bottom: 9px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .gd-comment-author {
            font-size: 12px;
            font-weight: 700;
            color: var(--gd-rose);
        }

        .gd-comment-body {
            font-size: 12.5px;
            color: #e6d7d0;
            margin-top: 2px;
        }

        .gd-comment-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* MODALS & FORMS */
        .gd-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 2050;
            background: rgba(8, 5, 7, 0.78);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }

        .gd-modal-dialog {
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow-y: auto;
            background: linear-gradient(180deg, #24171c 0%, #160e12 100%);
            border: 1px solid rgba(229, 184, 168, 0.28);
            border-radius: 16px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.6);
            color: var(--gd-cream);
        }

        .gd-modal-lg {
            max-width: 780px;
        }

        .gd-modal-header,
        .gd-modal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 22px;
        }

        .gd-modal-header {
            border-bottom: 1px solid var(--gd-hairline);
        }

        .gd-modal-footer {
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid var(--gd-hairline);
        }

        .gd-modal-body {
            padding: 18px 22px;
        }

        .gd-modal-close {
            border: none;
            background: transparent;
            color: var(--gd-muted);
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
        }

        .gd-form-label {
            display: block;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--gd-muted);
            margin-bottom: 6px;
        }

        .gd-form-control {
            width: 100%;
            padding: 8px 12px;
            border-radius: 9px;
            border: 1px solid var(--gd-border-strong);
            background: rgba(14, 9, 12, 0.78);
            color: var(--gd-cream);
            font-size: 13px;
            outline: none;
        }

        .gd-form-control:focus {
            border-color: var(--gd-rose);
        }

        .gd-check-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--gd-cream);
            cursor: pointer;
        }

        .gd-repeater-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .gd-repeater-item {
            padding: 14px 16px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--gd-border);
        }

        .gd-assignee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 8px;
            max-height: 210px;
            overflow-y: auto;
            padding: 4px 0;
        }

        .gd-assignee-chip {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 7px 10px;
            border-radius: 9px;
            border: 1px solid var(--gd-border);
            background: rgba(255, 255, 255, 0.03);
            font-size: 12.5px;
            color: var(--gd-cream);
            cursor: pointer;
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

        (function () {
            /* =====================================================================
               1. TOP SUB-TABS (GUIDES · PRESENTATIONS · BRAINSTORM & IDEAS)
               ===================================================================== */
            const sectionBtns = Array.from(document.querySelectorAll('[data-guide-section-btn]'));
            const sectionPanels = Array.from(document.querySelectorAll('[data-guide-section]'));
            const heroTools = document.querySelector('.gd-hero-tools');

            function activateSection(secKey, syncUrl) {
                sectionBtns.forEach(btn => {
                    btn.classList.toggle('active', btn.getAttribute('data-guide-section-btn') === secKey);
                });
                sectionPanels.forEach(panel => {
                    panel.classList.toggle('d-none', panel.getAttribute('data-guide-section') !== secKey);
                });
                if (heroTools) {
                    heroTools.classList.toggle('d-none', secKey !== 'guides');
                }
                if (syncUrl) {
                    try {
                        const url = new URL(window.location.href);
                        url.pathname = '/guide';
                        if (secKey === 'guides') {
                            url.searchParams.delete('section');
                        } else {
                            url.searchParams.set('section', secKey);
                        }
                        window.history.replaceState({}, '', url.toString());
                    } catch (e) {}
                }
            }

            sectionBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    activateSection(this.getAttribute('data-guide-section-btn') || 'guides', true);
                });
            });

            const presentationsHubEl = document.getElementById('gdPresentationsHub');
            const openImportOnLoad = presentationsHubEl && presentationsHubEl.getAttribute('data-open-import-on-load') === '1';
            const initialActiveBtn = document.querySelector('[data-guide-section-btn].active');
            if (openImportOnLoad) {
                activateSection('presentations', false);
            } else if (initialActiveBtn) {
                activateSection(initialActiveBtn.getAttribute('data-guide-section-btn') || 'guides', false);
            }

            /* =====================================================================
               2. MODAL HELPERS
               ===================================================================== */
            function openModal(id) {
                const el = document.getElementById(id);
                if (el) el.classList.remove('d-none');
            }

            function closeModal(id) {
                const el = document.getElementById(id);
                if (el) el.classList.add('d-none');
            }

            document.querySelectorAll('[data-close-modal]').forEach(btn => {
                btn.addEventListener('click', function () {
                    closeModal(this.getAttribute('data-close-modal'));
                });
            });

            document.querySelectorAll('.gd-modal-backdrop').forEach(backdrop => {
                backdrop.addEventListener('click', function (e) {
                    if (e.target === backdrop) {
                        backdrop.classList.add('d-none');
                    }
                });
            });

            /* =====================================================================
               3. PRESENTATIONS FILTER + 16:9 SLIDE DECK PLAYER (6 LAYOUTS)
               ===================================================================== */
            const decks = Array.isArray(window.__laleenPresentations) ? window.__laleenPresentations : [];
            const layoutsMap = window.__laleenPresentationLayouts || {
                cover: 'Cover — Serif Headline & Subtitle',
                big_metric: 'Big Metric — Hero Figure & Takeaway',
                timeline: 'Timeline — Horizontal Step Cards (3–5)',
                comparison: 'Comparison — 2-Column Side-by-Side',
                cards: 'Cards — 3 or 4 Strategy Grid Tiles',
                table: 'Table — Executive KPI & Ownership Matrix',
            };

            const legacyLayoutAliases = {
                hero: 'cover',
                closing: 'cover',
                metric: 'big_metric',
                funnel: 'timeline',
                checklist: 'cards',
                matrix: 'table',
            };

            function normalizeSlideLayout(raw) {
                const key = String(raw || '').trim().toLowerCase();
                if (Object.prototype.hasOwnProperty.call(layoutsMap, key)) return key;
                if (Object.prototype.hasOwnProperty.call(legacyLayoutAliases, key)) return legacyLayoutAliases[key];
                return 'cards';
            }

            const layoutBadgeLabels = {
                cover: 'Cover',
                big_metric: 'Big Metric',
                timeline: 'Timeline',
                comparison: 'Comparison',
                cards: 'Cards',
                table: 'Table',
            };

            const presCatBtns = Array.from(document.querySelectorAll('[data-pres-cat]'));
            const presCards = Array.from(document.querySelectorAll('[data-pres-card]'));

            presCatBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    const cat = this.getAttribute('data-pres-cat') || 'all';
                    presCatBtns.forEach(b => b.classList.toggle('active', b === this));
                    presCards.forEach(card => {
                        const match = cat === 'all' || card.getAttribute('data-pres-category') === cat;
                        card.classList.toggle('d-none', !match);
                    });
                });
            });

            const playerShell = document.getElementById('gdDeckPlayerShell');
            const playerCategory = document.getElementById('gdPlayerCategoryBadge');
            const playerLayoutBadge = document.getElementById('gdPlayerLayoutBadge');
            const playerDeckTitle = document.getElementById('gdPlayerDeckTitle');
            const slideIndicator = document.getElementById('gdSlideIndicator');
            const progressFill = document.getElementById('gdSlideProgressFill');
            const slideStage = document.getElementById('gdSlideStage');
            const slideCanvas = document.getElementById('gdSlideCanvas');
            const slideFooter = document.getElementById('gdSlideFooter');
            const slideDots = document.getElementById('gdSlideDots');
            const prevSlideBtn = document.getElementById('gdPrevSlideBtn');
            const nextSlideBtn = document.getElementById('gdNextSlideBtn');
            const closePlayerBtn = document.getElementById('gdClosePlayerBtn');
            const toggleFullscreenBtn = document.getElementById('gdToggleFullscreenBtn');

            let currentDeck = null;
            let currentSlideIndex = 0;

            function makeEl(tag, className, text) {
                const el = document.createElement(tag);
                if (className) el.className = className;
                if (text !== undefined && text !== null) el.textContent = String(text);
                return el;
            }

            function buildStandardSlideHead(slide, eyebrowText) {
                const headWrap = makeEl('div', 'gd-slide-head-bar');
                const left = makeEl('div');
                left.appendChild(makeEl('div', 'gd-slide-eyebrow', eyebrowText));
                left.appendChild(makeEl('h3', 'gd-slide-heading', slide.title || ''));
                const sub = slide.subtitle || (Array.isArray(slide.body_points) && slide.body_points[0] ? slide.body_points[0] : '');
                if (sub) {
                    left.appendChild(makeEl('div', 'gd-slide-subtitle mb-0', sub));
                }
                headWrap.appendChild(left);

                if (slide.highlight_metric && String(slide.highlight_metric).trim() !== '') {
                    headWrap.appendChild(makeEl('span', 'gd-slide-metric-pill', slide.highlight_metric));
                }

                return headWrap;
            }

            function extractStructuredItems(slide, fallbackPrefix) {
                const rows = Array.isArray(slide.table_rows) ? slide.table_rows.filter(r => Array.isArray(r) && r.length > 0) : [];
                if (rows.length > 0) {
                    return rows.map((r, idx) => {
                        if (r.length >= 3) {
                            return {
                                badge: String(r[0] ?? `${fallbackPrefix} ${idx + 1}`),
                                title: String(r[1] ?? ''),
                                desc: String(r.slice(2).join(' · ')),
                            };
                        }
                        if (r.length === 2) {
                            return {
                                badge: `${fallbackPrefix} ${idx + 1}`,
                                title: String(r[0] ?? ''),
                                desc: String(r[1] ?? ''),
                            };
                        }
                        return {
                            badge: `${fallbackPrefix} ${idx + 1}`,
                            title: String(r[0] ?? ''),
                            desc: '',
                        };
                    });
                }

                const pts = Array.isArray(slide.body_points) ? slide.body_points : [];
                return pts.map((pt, idx) => {
                    const str = String(pt || '');
                    const colonIdx = str.indexOf(':');
                    if (colonIdx > 0 && colonIdx < 36) {
                        return {
                            badge: `${fallbackPrefix} ${idx + 1}`,
                            title: str.slice(0, colonIdx).trim(),
                            desc: str.slice(colonIdx + 1).trim(),
                        };
                    }
                    return {
                        badge: `${fallbackPrefix} ${idx + 1}`,
                        title: `Point ${idx + 1}`,
                        desc: str,
                    };
                });
            }

            function renderCurrentSlide() {
                if (!currentDeck || !Array.isArray(currentDeck.slides) || currentDeck.slides.length === 0) return;

                const total = currentDeck.slides.length;
                if (currentSlideIndex < 0) currentSlideIndex = 0;
                if (currentSlideIndex >= total) currentSlideIndex = total - 1;

                const slide = currentDeck.slides[currentSlideIndex] || {};
                const layout = normalizeSlideLayout(slide.layout);
                const eyebrowText = slide.eyebrow || `SLIDE ${String(currentSlideIndex + 1).padStart(2, '0')}`;

                if (playerCategory) playerCategory.textContent = currentDeck.category || 'Growth Playbook';
                if (playerLayoutBadge) playerLayoutBadge.textContent = layoutBadgeLabels[layout] || 'Slide';
                if (playerDeckTitle) playerDeckTitle.textContent = currentDeck.title || 'Presentation';
                if (slideIndicator) slideIndicator.textContent = `Slide ${currentSlideIndex + 1} of ${total}`;
                if (progressFill) {
                    const pct = Math.max(4, ((currentSlideIndex + 1) / total) * 100);
                    progressFill.style.width = `${pct.toFixed(2)}%`;
                }
                if (slideStage) {
                    slideStage.setAttribute('data-active-layout', layout);
                }

                if (slideCanvas) {
                    slideCanvas.replaceChildren();

                    if (layout === 'cover') {
                        const box = makeEl('div', 'gd-layout-cover');
                        box.appendChild(makeEl('div', 'gd-slide-eyebrow', eyebrowText));
                        box.appendChild(makeEl('h3', 'gd-cover-title', slide.title || ''));
                        box.appendChild(makeEl('div', 'gd-cover-accent-bar'));

                        const subtitleText = slide.subtitle
                            || (Array.isArray(slide.body_points) && slide.body_points.length > 0 ? slide.body_points.join(' · ') : '');
                        if (subtitleText) {
                            box.appendChild(makeEl('p', 'gd-cover-subtitle', subtitleText));
                        }
                        if (slide.highlight_metric && String(slide.highlight_metric).trim() !== '') {
                            const pillWrap = makeEl('div');
                            pillWrap.appendChild(makeEl('span', 'gd-slide-metric-pill', slide.highlight_metric));
                            box.appendChild(pillWrap);
                        }
                        slideCanvas.appendChild(box);
                    } else if (layout === 'big_metric') {
                        const box = makeEl('div', 'gd-layout-big-metric');
                        box.appendChild(makeEl('div', 'gd-slide-eyebrow', eyebrowText));
                        const heroVal = (slide.highlight_metric && String(slide.highlight_metric).trim() !== '')
                            ? String(slide.highlight_metric)
                            : (slide.title || 'KPI');
                        box.appendChild(makeEl('div', 'gd-big-metric-figure', heroVal));
                        if (slide.title) {
                            box.appendChild(makeEl('div', 'gd-big-metric-label', slide.title));
                        }
                        const pts = Array.isArray(slide.body_points) ? slide.body_points.filter(Boolean) : [];
                        if (pts.length > 1) {
                            if (slide.subtitle && slide.subtitle !== pts[0]) {
                                box.appendChild(makeEl('p', 'gd-big-metric-takeaway mb-2', slide.subtitle));
                            }
                            const ul = makeEl('ul', 'gd-slide-points');
                            pts.forEach(pt => {
                                ul.appendChild(makeEl('li', '', pt));
                            });
                            box.appendChild(ul);
                        } else {
                            const takeaway = slide.subtitle || (pts[0] ? pts[0] : '');
                            if (takeaway) {
                                box.appendChild(makeEl('p', 'gd-big-metric-takeaway', takeaway));
                            }
                        }
                        slideCanvas.appendChild(box);
                    } else if (layout === 'timeline') {
                        slideCanvas.appendChild(buildStandardSlideHead(slide, eyebrowText));
                        const grid = makeEl('div', 'gd-timeline-grid');
                        const steps = extractStructuredItems(slide, 'Step').slice(0, 5);
                        steps.forEach(st => {
                            const card = makeEl('div', 'gd-timeline-card');
                            card.appendChild(makeEl('span', 'gd-timeline-badge', st.badge));
                            if (st.title) card.appendChild(makeEl('div', 'gd-timeline-title', st.title));
                            if (st.desc) card.appendChild(makeEl('div', 'gd-timeline-desc', st.desc));
                            grid.appendChild(card);
                        });
                        slideCanvas.appendChild(grid);
                    } else if (layout === 'comparison') {
                        slideCanvas.appendChild(buildStandardSlideHead(slide, eyebrowText));
                        const grid = makeEl('div', 'gd-comparison-grid');
                        const cols = Array.isArray(slide.table_rows) && slide.table_rows.length >= 2
                            ? slide.table_rows.slice(0, 2).map((r, i) => ({
                                title: String(r[0] ?? `Option ${i + 1}`),
                                tag: r.length >= 3 ? String(r[1] ?? '') : '',
                                desc: String(r.length >= 3 ? r.slice(2).join(' · ') : (r[1] ?? '')),
                            }))
                            : extractStructuredItems(slide, 'Option').slice(0, 2).map(it => ({
                                title: it.title,
                                tag: it.badge,
                                desc: it.desc,
                            }));

                        cols.forEach(c => {
                            const card = makeEl('div', 'gd-comparison-card');
                            if (c.tag) card.appendChild(makeEl('span', 'gd-comparison-tag', c.tag));
                            card.appendChild(makeEl('div', 'gd-comparison-title', c.title));
                            if (c.desc) card.appendChild(makeEl('div', 'gd-comparison-desc', c.desc));
                            grid.appendChild(card);
                        });
                        slideCanvas.appendChild(grid);
                    } else if (layout === 'table') {
                        slideCanvas.appendChild(buildStandardSlideHead(slide, eyebrowText));
                        const wrap = makeEl('div', 'gd-slide-table-wrap');
                        const tbl = makeEl('table', 'gd-slide-table');
                        const thead = document.createElement('thead');
                        const tbody = document.createElement('tbody');

                        let headers = Array.isArray(slide.headers) && slide.headers.length > 0 ? slide.headers : [];
                        let bodyRows = Array.isArray(slide.rows) && slide.rows.length > 0 ? slide.rows : [];

                        if (headers.length === 0 && Array.isArray(slide.table_rows) && slide.table_rows.length > 0) {
                            if (slide.table_rows.length > 1) {
                                headers = slide.table_rows[0];
                                bodyRows = slide.table_rows.slice(1);
                            } else {
                                bodyRows = slide.table_rows;
                            }
                        }

                        if (headers.length > 0) {
                            const trHead = document.createElement('tr');
                            headers.forEach(h => {
                                trHead.appendChild(makeEl('th', '', h ?? ''));
                            });
                            thead.appendChild(trHead);
                        }

                        bodyRows.forEach(rCells => {
                            if (!Array.isArray(rCells)) return;
                            const tr = document.createElement('tr');
                            rCells.forEach(val => {
                                tr.appendChild(makeEl('td', '', val ?? ''));
                            });
                            tbody.appendChild(tr);
                        });

                        tbl.appendChild(thead);
                        tbl.appendChild(tbody);
                        wrap.appendChild(tbl);
                        slideCanvas.appendChild(wrap);
                    } else {
                        // 'cards' (default 3 or 4 clean grid tiles)
                        slideCanvas.appendChild(buildStandardSlideHead(slide, eyebrowText));
                        const grid = makeEl('div', 'gd-cards-grid');
                        const tiles = extractStructuredItems(slide, '0').slice(0, 4);
                        tiles.forEach((tile, idx) => {
                            const card = makeEl('div', 'gd-consulting-tile');
                            const badgeLabel = tile.badge.startsWith('0 ')
                                ? String(idx + 1).padStart(2, '0')
                                : tile.badge;
                            card.appendChild(makeEl('span', 'gd-tile-num', badgeLabel));
                            if (tile.title) card.appendChild(makeEl('div', 'gd-tile-title', tile.title));
                            if (tile.desc) card.appendChild(makeEl('div', 'gd-tile-desc', tile.desc));
                            grid.appendChild(card);
                        });
                        slideCanvas.appendChild(grid);
                    }
                }

                if (slideFooter) {
                    const hasFooter = Boolean(slide.footer_note && String(slide.footer_note).trim() !== '');
                    slideFooter.classList.toggle('d-none', !hasFooter);
                    slideFooter.textContent = hasFooter ? String(slide.footer_note) : '';
                }

                if (slideDots) {
                    slideDots.replaceChildren();
                    for (let i = 0; i < total; i++) {
                        const dot = document.createElement('button');
                        dot.type = 'button';
                        dot.className = 'gd-slide-dot' + (i === currentSlideIndex ? ' active' : '');
                        dot.title = `Slide ${i + 1}`;
                        dot.addEventListener('click', () => {
                            currentSlideIndex = i;
                            renderCurrentSlide();
                        });
                        slideDots.appendChild(dot);
                    }
                }

                if (prevSlideBtn) prevSlideBtn.disabled = currentSlideIndex === 0;
                if (nextSlideBtn) nextSlideBtn.disabled = currentSlideIndex >= total - 1;
            }

            function openDeckPlayer(deckId) {
                const found = decks.find(d => Number(d.id) === Number(deckId));
                if (!found || !playerShell) return;
                currentDeck = found;
                currentSlideIndex = 0;
                playerShell.classList.remove('d-none');
                renderCurrentSlide();
                playerShell.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (slideStage) slideStage.focus({ preventScroll: true });
            }

            function closeDeckPlayer() {
                if (!playerShell) return;
                if (document.fullscreenElement) {
                    document.exitFullscreen().catch(() => {});
                }
                playerShell.classList.remove('gd-is-fullscreen');
                playerShell.classList.add('d-none');
                currentDeck = null;
            }

            document.querySelectorAll('[data-open-deck]').forEach(btn => {
                btn.addEventListener('click', function () {
                    openDeckPlayer(this.getAttribute('data-open-deck'));
                });
            });

            if (prevSlideBtn) {
                prevSlideBtn.addEventListener('click', function () {
                    if (currentSlideIndex > 0) {
                        currentSlideIndex--;
                        renderCurrentSlide();
                    }
                });
            }

            if (nextSlideBtn) {
                nextSlideBtn.addEventListener('click', function () {
                    if (currentDeck && currentSlideIndex < currentDeck.slides.length - 1) {
                        currentSlideIndex++;
                        renderCurrentSlide();
                    }
                });
            }

            if (closePlayerBtn) {
                closePlayerBtn.addEventListener('click', closeDeckPlayer);
            }

            if (toggleFullscreenBtn && playerShell) {
                toggleFullscreenBtn.addEventListener('click', function () {
                    if (document.fullscreenElement) {
                        document.exitFullscreen().catch(() => {});
                        playerShell.classList.remove('gd-is-fullscreen');
                    } else if (playerShell.requestFullscreen) {
                        playerShell.requestFullscreen().catch(() => {
                            playerShell.classList.toggle('gd-is-fullscreen');
                        });
                    } else {
                        playerShell.classList.toggle('gd-is-fullscreen');
                    }
                });
            }

            document.addEventListener('keydown', function (e) {
                if (!currentDeck || !playerShell || playerShell.classList.contains('d-none')) return;
                const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
                if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target?.isContentEditable) return;

                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    if (currentSlideIndex < currentDeck.slides.length - 1) {
                        currentSlideIndex++;
                        renderCurrentSlide();
                    }
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    if (currentSlideIndex > 0) {
                        currentSlideIndex--;
                        renderCurrentSlide();
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    if (playerShell.classList.contains('gd-is-fullscreen')) {
                        playerShell.classList.remove('gd-is-fullscreen');
                    } else {
                        closeDeckPlayer();
                    }
                }
            });

            /* =====================================================================
               4. PRESENTATION CREATE / EDIT / IMPORT JSON MODAL + SLIDE REPEATER
               ===================================================================== */
            const presForm = document.getElementById('gdPresentationForm');
            const importJsonForm = document.getElementById('gdImportDeckJsonForm');
            const presModeTabsWrap = document.getElementById('gdPresModeTabsWrap');
            const presModeBtns = Array.from(document.querySelectorAll('[data-pres-mode-btn]'));
            const presMethodInput = document.getElementById('gdPresentationMethod');
            const presModalTitle = document.getElementById('gdPresModalTitle');
            const presTitleInput = document.getElementById('gdPresTitleInput');
            const presCatInput = document.getElementById('gdPresCategoryInput');
            const presSummaryInput = document.getElementById('gdPresSummaryInput');
            const presPinnedInput = document.getElementById('gdPresPinnedInput');
            const repeaterContainer = document.getElementById('gdSlidesRepeater');
            const repeaterCountEl = document.getElementById('gdRepeaterCount');
            const addSlideRowBtn = document.getElementById('gdAddSlideRowBtn');
            const newPresentationBtn = document.getElementById('gdNewPresentationBtn');
            const storePresentationUrl = presForm ? presForm.getAttribute('action') : '';

            function setPresentationModalMode(mode) {
                const isJson = mode === 'json';
                presModeBtns.forEach(btn => {
                    btn.classList.toggle('active', btn.getAttribute('data-pres-mode-btn') === (isJson ? 'json' : 'manual'));
                });
                if (presForm) presForm.classList.toggle('d-none', isJson);
                if (importJsonForm) importJsonForm.classList.toggle('d-none', !isJson);
            }

            presModeBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    setPresentationModalMode(this.getAttribute('data-pres-mode-btn') || 'manual');
                });
            });

            function reindexSlideRows() {
                if (!repeaterContainer) return;
                const items = Array.from(repeaterContainer.querySelectorAll('.gd-repeater-item'));
                if (repeaterCountEl) repeaterCountEl.textContent = String(items.length);

                items.forEach((item, idx) => {
                    const numLabel = item.querySelector('[data-slide-num]');
                    if (numLabel) numLabel.textContent = `Slide ${idx + 1}`;

                    item.querySelectorAll('[data-field]').forEach(input => {
                        const field = input.getAttribute('data-field');
                        input.name = `slides[${idx}][${field}]`;
                    });

                    const upBtn = item.querySelector('[data-move-slide="up"]');
                    const downBtn = item.querySelector('[data-move-slide="down"]');
                    const removeBtn = item.querySelector('[data-remove-slide]');
                    if (upBtn) upBtn.disabled = idx === 0;
                    if (downBtn) downBtn.disabled = idx >= items.length - 1;
                    if (removeBtn) removeBtn.disabled = items.length <= 1;
                });
            }

            function formatTableRowsForEditor(data) {
                if (typeof data.table_rows_text === 'string') {
                    return data.table_rows_text;
                }
                if (Array.isArray(data.table_rows) && data.table_rows.length > 0) {
                    return data.table_rows
                        .filter(r => Array.isArray(r))
                        .map(r => r.map(c => String(c ?? '').trim()).join(' | '))
                        .join('\n');
                }
                if (Array.isArray(data.headers) && data.headers.length > 0) {
                    const all = [data.headers, ...(Array.isArray(data.rows) ? data.rows : [])];
                    return all
                        .filter(r => Array.isArray(r))
                        .map(r => r.map(c => String(c ?? '').trim()).join(' | '))
                        .join('\n');
                }
                return '';
            }

            function buildSlideRow(slideData, defaultLayout) {
                const data = slideData || {};
                const wrapper = document.createElement('div');
                wrapper.className = 'gd-repeater-item';

                const head = document.createElement('div');
                head.className = 'd-flex align-items-center justify-content-between gap-2 mb-2';
                const badge = document.createElement('span');
                badge.className = 'gd-target-tag';
                badge.setAttribute('data-slide-num', '1');
                badge.textContent = 'Slide';

                const controlsGroup = document.createElement('div');
                controlsGroup.className = 'd-flex align-items-center gap-1';

                const upBtn = document.createElement('button');
                upBtn.type = 'button';
                upBtn.className = 'gd-ghost-btn gd-move-btn';
                upBtn.setAttribute('data-move-slide', 'up');
                upBtn.title = 'Move Slide Up';
                upBtn.textContent = '↑';
                upBtn.addEventListener('click', () => {
                    const prev = wrapper.previousElementSibling;
                    if (prev && repeaterContainer) {
                        repeaterContainer.insertBefore(wrapper, prev);
                        reindexSlideRows();
                    }
                });

                const downBtn = document.createElement('button');
                downBtn.type = 'button';
                downBtn.className = 'gd-ghost-btn gd-move-btn';
                downBtn.setAttribute('data-move-slide', 'down');
                downBtn.title = 'Move Slide Down';
                downBtn.textContent = '↓';
                downBtn.addEventListener('click', () => {
                    const next = wrapper.nextElementSibling;
                    if (next && repeaterContainer) {
                        repeaterContainer.insertBefore(next, wrapper);
                        reindexSlideRows();
                    }
                });

                const rmBtn = document.createElement('button');
                rmBtn.type = 'button';
                rmBtn.className = 'gd-danger-btn';
                rmBtn.setAttribute('data-remove-slide', '1');
                rmBtn.textContent = 'Remove';
                rmBtn.addEventListener('click', () => {
                    wrapper.remove();
                    reindexSlideRows();
                });

                controlsGroup.appendChild(upBtn);
                controlsGroup.appendChild(downBtn);
                controlsGroup.appendChild(rmBtn);
                head.appendChild(badge);
                head.appendChild(controlsGroup);
                wrapper.appendChild(head);

                const grid = document.createElement('div');
                grid.className = 'row g-2';

                function makeCol(colClass, labelText, fieldName, tagName, value, placeholderText, rowsCount) {
                    const col = document.createElement('div');
                    col.className = colClass;
                    const lbl = document.createElement('label');
                    lbl.className = 'gd-form-label';
                    lbl.textContent = labelText;
                    const ctrl = document.createElement(tagName);
                    ctrl.className = 'gd-form-control';
                    ctrl.setAttribute('data-field', fieldName);
                    if (placeholderText) ctrl.placeholder = placeholderText;

                    if (tagName === 'select') {
                        Object.entries(layoutsMap).forEach(([val, text]) => {
                            const opt = document.createElement('option');
                            opt.value = val;
                            opt.textContent = String(text);
                            ctrl.appendChild(opt);
                        });
                        ctrl.value = normalizeSlideLayout(value || defaultLayout || 'cards');
                    } else if (tagName === 'textarea') {
                        ctrl.rows = rowsCount || 2;
                        ctrl.value = value || '';
                    } else {
                        ctrl.type = 'text';
                        ctrl.value = value || '';
                        if (fieldName === 'title') ctrl.required = true;
                    }
                    col.appendChild(lbl);
                    col.appendChild(ctrl);
                    return col;
                }

                const ptsStr = Array.isArray(data.body_points)
                    ? data.body_points.join('\n')
                    : (data.body_points || '');

                const tableStr = formatTableRowsForEditor(data);

                grid.appendChild(makeCol('col-md-3', 'Eyebrow', 'eyebrow', 'input', data.eyebrow || '', 'e.g., STEP 1'));
                grid.appendChild(makeCol('col-md-5', 'Slide Title', 'title', 'input', data.title || '', 'Concise headline'));
                grid.appendChild(makeCol('col-md-4', 'Visual Layout', 'layout', 'select', data.layout || defaultLayout || 'cards'));
                grid.appendChild(makeCol('col-md-4', 'Highlight Metric', 'highlight_metric', 'input', data.highlight_metric || '', 'e.g., 2,500 QAR'));
                grid.appendChild(makeCol('col-md-4', 'Subtitle / Takeaway', 'subtitle', 'input', data.subtitle || '', '1-line executive takeaway'));
                grid.appendChild(makeCol('col-md-4', 'Footer Note', 'footer_note', 'input', data.footer_note || '', 'Optional bottom note'));
                grid.appendChild(makeCol('col-md-6', 'Key Points (1 line per bullet)', 'body_points', 'textarea', ptsStr, 'Concise supporting bullet points...', 3));
                grid.appendChild(makeCol('col-md-6', 'Cards / Timeline / Table Rows (Col 1 | Col 2 | Col 3)', 'table_rows_text', 'textarea', tableStr, 'Day 1 | Launch | Live VSL & QR stands', 3));

                wrapper.appendChild(grid);
                return wrapper;
            }

            if (repeaterContainer && repeaterContainer.children.length === 0) {
                repeaterContainer.appendChild(buildSlideRow({}, 'cover'));
                reindexSlideRows();
            }

            if (newPresentationBtn && presForm && repeaterContainer) {
                newPresentationBtn.addEventListener('click', function () {
                    presForm.setAttribute('action', storePresentationUrl);
                    if (presMethodInput) presMethodInput.value = 'POST';
                    if (presModalTitle) presModalTitle.textContent = 'New Strategy Presentation';
                    if (presModeTabsWrap) presModeTabsWrap.classList.remove('d-none');
                    setPresentationModalMode('manual');
                    if (presTitleInput) presTitleInput.value = '';
                    if (presCatInput) presCatInput.selectedIndex = 0;
                    if (presSummaryInput) presSummaryInput.value = '';
                    if (presPinnedInput) presPinnedInput.checked = false;
                    repeaterContainer.replaceChildren(buildSlideRow({}, 'cover'));
                    reindexSlideRows();
                    openModal('gdPresentationModal');
                });
            }

            if (addSlideRowBtn && repeaterContainer) {
                addSlideRowBtn.addEventListener('click', function () {
                    repeaterContainer.appendChild(buildSlideRow({}, 'cards'));
                    reindexSlideRows();
                });
            }

            document.querySelectorAll('[data-edit-deck]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const deckId = Number(this.getAttribute('data-edit-deck'));
                    const deck = decks.find(d => Number(d.id) === deckId);
                    if (!deck || !presForm || !repeaterContainer) return;

                    presForm.setAttribute('action', deck.update_url);
                    if (presMethodInput) presMethodInput.value = 'PUT';
                    if (presModalTitle) presModalTitle.textContent = 'Edit Presentation';
                    if (presModeTabsWrap) presModeTabsWrap.classList.add('d-none');
                    setPresentationModalMode('manual');
                    if (presTitleInput) presTitleInput.value = deck.title || '';
                    if (presCatInput) presCatInput.value = deck.category || 'Growth Playbook';
                    if (presSummaryInput) presSummaryInput.value = deck.summary || '';
                    if (presPinnedInput) presPinnedInput.checked = Boolean(deck.is_pinned);

                    repeaterContainer.replaceChildren();
                    const slidesToLoad = Array.isArray(deck.slides) && deck.slides.length > 0 ? deck.slides : [{ layout: 'cover' }];
                    slidesToLoad.forEach((s, idx) => repeaterContainer.appendChild(buildSlideRow(s, idx === 0 ? 'cover' : 'cards')));
                    reindexSlideRows();
                    openModal('gdPresentationModal');
                });
            });

            /* =====================================================================
               5. BRAINSTORM & IDEAS FILTERS, COMMENTS, EDIT & CONVERT TO OPS
               ===================================================================== */
            const ideasMap = window.__laleenIdeasMap || {};
            const ideaCatBtns = Array.from(document.querySelectorAll('[data-idea-cat-btn]'));
            const ideaStatusBtns = Array.from(document.querySelectorAll('[data-idea-status-btn]'));
            const ideaCards = Array.from(document.querySelectorAll('[data-idea-card]'));
            const emptyIdeasFilter = document.getElementById('gdEmptyIdeasFilter');

            let selectedIdeaCat = 'all';
            let selectedIdeaStatus = 'all';

            function filterIdeaCards() {
                let visible = 0;
                ideaCards.forEach(card => {
                    const catMatch = selectedIdeaCat === 'all' || card.getAttribute('data-idea-category') === selectedIdeaCat;
                    const stMatch = selectedIdeaStatus === 'all' || card.getAttribute('data-idea-status') === selectedIdeaStatus;
                    const show = catMatch && stMatch;
                    card.classList.toggle('d-none', !show);
                    if (show) visible++;
                });
                if (emptyIdeasFilter) {
                    emptyIdeasFilter.classList.toggle('d-none', visible > 0 || ideaCards.length === 0);
                }
            }

            ideaCatBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    selectedIdeaCat = this.getAttribute('data-idea-cat-btn') || 'all';
                    ideaCatBtns.forEach(b => b.classList.toggle('active', b === this));
                    filterIdeaCards();
                });
            });

            ideaStatusBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    selectedIdeaStatus = this.getAttribute('data-idea-status-btn') || 'all';
                    ideaStatusBtns.forEach(b => b.classList.toggle('active', b === this));
                    filterIdeaCards();
                });
            });

            document.querySelectorAll('[data-toggle-comments]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-toggle-comments');
                    const drawer = document.getElementById(`gdCommentsDrawer-${id}`);
                    if (drawer) drawer.classList.toggle('d-none');
                });
            });

            const ideaForm = document.getElementById('gdIdeaForm');
            const ideaMethodInput = document.getElementById('gdIdeaFormMethod');
            const ideaModalTitle = document.getElementById('gdIdeaModalTitle');
            const ideaTitleInput = document.getElementById('gdIdeaTitleInput');
            const ideaCatInput = document.getElementById('gdIdeaCategoryInput');
            const ideaDescInput = document.getElementById('gdIdeaDescInput');
            const ideaStatusWrap = document.getElementById('gdIdeaStatusFieldWrap');
            const ideaStatusInput = document.getElementById('gdIdeaStatusInput');
            const ideaSubmitBtn = document.getElementById('gdIdeaSubmitBtn');
            const newIdeaBtn = document.getElementById('gdNewIdeaBtn');
            const storeIdeaUrl = ideaForm ? ideaForm.getAttribute('action') : '';

            if (newIdeaBtn && ideaForm) {
                newIdeaBtn.addEventListener('click', function () {
                    ideaForm.setAttribute('action', storeIdeaUrl);
                    if (ideaMethodInput) ideaMethodInput.value = 'POST';
                    if (ideaModalTitle) ideaModalTitle.textContent = 'Submit Campaign or CRM Idea';
                    if (ideaSubmitBtn) ideaSubmitBtn.textContent = 'Submit Idea';
                    if (ideaTitleInput) ideaTitleInput.value = '';
                    if (ideaCatInput) ideaCatInput.selectedIndex = 0;
                    if (ideaDescInput) ideaDescInput.value = '';
                    if (ideaStatusWrap) ideaStatusWrap.classList.add('d-none');
                    openModal('gdIdeaModal');
                });
            }

            document.querySelectorAll('[data-edit-idea]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-edit-idea');
                    const item = ideasMap[id];
                    if (!item || !ideaForm) return;

                    ideaForm.setAttribute('action', item.update_url);
                    if (ideaMethodInput) ideaMethodInput.value = 'PUT';
                    if (ideaModalTitle) ideaModalTitle.textContent = 'Edit Idea';
                    if (ideaSubmitBtn) ideaSubmitBtn.textContent = 'Save Changes';
                    if (ideaTitleInput) ideaTitleInput.value = item.title || '';
                    if (ideaCatInput) ideaCatInput.value = item.category || 'Giveaway';
                    if (ideaDescInput) ideaDescInput.value = item.description || '';
                    if (ideaStatusWrap && ideaStatusInput) {
                        ideaStatusWrap.classList.remove('d-none');
                        ideaStatusInput.value = item.status || 'New Idea';
                    }
                    openModal('gdIdeaModal');
                });
            });

            const convertForm = document.getElementById('gdConvertOpsForm');
            const convertTitle = document.getElementById('gdConvertIdeaTitle');
            const convertCategory = document.getElementById('gdConvertIdeaCategory');

            document.querySelectorAll('[data-convert-idea]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-convert-idea');
                    const item = ideasMap[id];
                    if (!item || !convertForm) return;

                    convertForm.setAttribute('action', item.convert_url);
                    if (convertTitle) convertTitle.textContent = item.title || '';
                    if (convertCategory) convertCategory.textContent = item.category || 'Idea';
                    convertForm.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = false; });
                    openModal('gdConvertOpsModal');
                });
            });
        })();
    </script>
@endsection
