@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<style>
    /* =========================================================================
       MINIMALIST EXECUTIVE DASHBOARD (@laleen-minimal-ui)
       Dark Espresso · Rose-Gold · Champagne · Quiet Uniform Surfaces
       ========================================================================= */
    .laleen-dash {
        --ld-surface: linear-gradient(180deg, rgba(34, 22, 27, 0.92) 0%, rgba(21, 14, 18, 0.96) 100%);
        --ld-border: rgba(229, 184, 168, 0.13);
        --ld-border-hover: rgba(229, 184, 168, 0.28);
        --ld-hairline: rgba(255, 255, 255, 0.06);
        --ld-cream: #f8efe6;
        --ld-taupe: #a9938b;
        --ld-rose: #e5b8a8;
        --ld-champagne: #d4a373;
        --ld-emerald: #6ee7b7;
        --ld-ruby: #fda4af;
        /* Muted thin-line bar & status dot tones */
        --ld-tone-green: #7bc4a4;
        --ld-tone-amber: #d6af6b;
        --ld-tone-red: #d88892;
        color: var(--ld-cream);
        padding-bottom: 1.25rem;
    }

    /* ---- Clean Section Headers ---- */
    .ld-section-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin: 1.5rem 0 0.75rem;
    }

    .ld-section-head--first {
        margin-top: 0;
    }

    .ld-section-title {
        font-size: 0.73rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--ld-rose);
        margin: 0;
    }

    .ld-section-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.65rem;
        font-size: 0.76rem;
        color: var(--ld-taupe);
    }

    .ld-combined-pill {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.45rem;
        background: rgba(255, 255, 255, 0.025);
        border: 1px solid var(--ld-border);
        border-radius: 999px;
        padding: 0.24rem 0.8rem;
        font-size: 0.74rem;
        color: var(--ld-taupe);
        font-variant-numeric: tabular-nums;
    }

    .ld-combined-pill strong {
        color: var(--ld-cream);
        font-weight: 600;
    }

    .ld-action-link {
        color: var(--ld-rose);
        font-size: 0.76rem;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        transition: color 0.16s ease;
    }

    .ld-action-link:hover {
        color: var(--ld-cream);
    }

    /* ---- Section 1: Minimalistic 2-Branch Revenue & Expenses Cards (Admin Only) ---- */
    .ld-branch-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.875rem;
    }

    @media (min-width: 992px) {
        .ld-branch-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    .ld-branch-card {
        background: var(--ld-surface);
        border: 1px solid var(--ld-border);
        border-radius: 16px;
        padding: 1.15rem 1.3rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .ld-branch-top {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .ld-branch-eyebrow {
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.11em;
        text-transform: uppercase;
        color: var(--ld-rose);
    }

    .ld-margin-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 0.16rem 0.6rem;
        font-size: 0.71rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .ld-margin-pill--positive {
        color: var(--ld-tone-green);
        background: rgba(123, 196, 164, 0.1);
    }

    .ld-margin-pill--negative {
        color: var(--ld-tone-red);
        background: rgba(216, 136, 146, 0.1);
    }

    .ld-no-expense-link {
        font-size: 0.73rem;
        color: var(--ld-taupe);
        text-decoration: none;
        transition: color 0.16s ease;
    }

    .ld-no-expense-link span {
        color: var(--ld-rose);
        font-weight: 500;
    }

    .ld-no-expense-link:hover {
        color: var(--ld-cream);
    }

    .ld-rev-primary-label {
        font-size: 0.73rem;
        color: var(--ld-taupe);
        margin-bottom: 0.15rem;
    }

    .ld-rev-primary-val {
        font-size: 1.6rem;
        font-weight: 600;
        color: var(--ld-cream);
        line-height: 1.15;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }

    .ld-branch-split {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        margin-top: 0.9rem;
        padding-top: 0.85rem;
        border-top: 1px solid var(--ld-hairline);
    }

    .ld-branch-metric-label {
        font-size: 0.72rem;
        color: var(--ld-taupe);
        margin-bottom: 0.15rem;
    }

    .ld-branch-metric-val {
        font-size: 0.98rem;
        font-weight: 600;
        color: var(--ld-cream);
        font-variant-numeric: tabular-nums;
    }

    .ld-text-positive {
        color: var(--ld-emerald) !important;
    }

    .ld-text-negative {
        color: var(--ld-ruby) !important;
    }

    /* ---- Section 2: Minimalistic 2-Column Team KPI Split Scorecards ---- */
    .ld-scorecard-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.875rem; /* gap-3.5 */
    }

    @media (min-width: 1200px) {
        .ld-scorecard-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    .ld-split-card {
        display: flex;
        flex-direction: column;
        gap: 0.9rem;
        background: var(--ld-surface);
        border: 1px solid var(--ld-border);
        border-radius: 16px;
        padding: 1rem; /* p-4 uniform compact height */
        text-decoration: none;
        color: var(--ld-cream);
        transition: border-color 0.16s ease, transform 0.16s ease;
    }

    .ld-split-card:hover {
        border-color: var(--ld-border-hover);
        color: var(--ld-cream);
        transform: translateY(-1px);
    }

    .ld-split-card--self {
        border-color: rgba(212, 163, 115, 0.36);
    }

    @media (min-width: 640px) {
        .ld-split-card {
            flex-direction: row;
            align-items: stretch;
            gap: 1.35rem; /* clean open spacing — zero vertical border line */
        }
    }

    /* Ultra-Clean Left Column (~30% width, no vertical border) */
    .ld-split-left {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    @media (min-width: 640px) {
        .ld-split-left {
            flex: 0 0 30%;
            max-width: 30%;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.65rem;
        }
    }

    .ld-person-name {
        font-size: 0.94rem;
        font-weight: 500;
        color: var(--ld-cream);
        line-height: 1.25;
        display: flex;
        align-items: center;
        gap: 0.4rem;
        white-space: nowrap;
    }

    .ld-self-tag {
        font-size: 0.63rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--ld-champagne);
    }

    .ld-person-role {
        font-size: 0.74rem;
        color: var(--ld-taupe);
        margin-top: 0.12rem;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ld-score-wrap {
        display: flex;
        align-items: baseline;
        gap: 0.5rem;
    }

    @media (min-width: 640px) {
        .ld-score-wrap {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.18rem;
        }
    }

    .ld-score-num {
        font-size: 1.5rem;
        font-weight: 600;
        line-height: 1.05;
        letter-spacing: -0.02em;
        color: var(--ld-cream);
        font-variant-numeric: tabular-nums;
    }

    .ld-status-inline {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.72rem;
        font-weight: 400;
        color: var(--ld-taupe);
        white-space: nowrap;
    }

    .ld-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .ld-status-dot--red {
        background: var(--ld-tone-red);
    }

    .ld-status-dot--amber {
        background: var(--ld-tone-amber);
    }

    .ld-status-dot--green {
        background: var(--ld-tone-green);
    }

    /* Minimalistic Right Column (~70% width — Strictly 3 Single-Line Rows) */
    .ld-split-right {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 0.6rem;
    }

    .ld-kpi-row {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .ld-kpi-line {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.75rem;
        white-space: nowrap;
    }

    .ld-kpi-label {
        font-size: 0.76rem;
        font-weight: 400;
        color: rgba(248, 239, 230, 0.86);
        line-height: 1.25;
    }

    .ld-kpi-val {
        font-size: 0.75rem;
        font-weight: 500;
        color: var(--ld-taupe);
        font-variant-numeric: tabular-nums;
        line-height: 1.25;
    }

    .ld-bar-track {
        width: 100%;
        height: 2.5px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.06);
        overflow: hidden;
    }

    .ld-bar-fill {
        height: 100%;
        border-radius: 999px;
        transition: width 0.25s ease;
    }

    .ld-bar-fill--red {
        background: var(--ld-tone-red);
    }

    .ld-bar-fill--amber {
        background: var(--ld-tone-amber);
    }

    .ld-bar-fill--green {
        background: var(--ld-tone-green);
    }

    /* ---- Print Readiness (@laleen-minimal-ui) ---- */
    @media print {
        .ld-action-link {
            display: none !important;
        }

        .laleen-dash,
        .ld-branch-card,
        .ld-split-card {
            background: #ffffff !important;
            color: #111111 !important;
            border: 1px solid #cccccc !important;
            box-shadow: none !important;
        }

        .ld-rev-primary-val,
        .ld-branch-metric-val,
        .ld-person-name,
        .ld-score-num,
        .ld-kpi-label {
            color: #111111 !important;
        }

        .ld-rev-primary-label,
        .ld-branch-metric-label,
        .ld-person-role,
        .ld-status-inline,
        .ld-kpi-val {
            color: #444444 !important;
        }

        .ld-bar-track {
            background: #e5e7eb !important;
        }
    }
</style>

@php
    $viewerUser = auth()->user();
    $showAdminFinance = !empty($isAdmin) && !empty($branchCards);
@endphp

<div class="laleen-dash">

    {{-- ====================================================================
         SECTION 1: BRANCH REVENUE & EXPENSES UP-TO-DATE (STRICT ADMIN ONLY)
         ==================================================================== --}}
    @if ($showAdminFinance)
        <div class="ld-section-head ld-section-head--first" data-testid="admin-finance-section-head">
            <h2 class="ld-section-title">Branch Revenue &amp; Expenses — To Date</h2>
            <div class="ld-section-meta">
                @if (!empty($combinedPnl))
                    <span class="ld-combined-pill">
                        <span>Total Revenue: <strong>QAR {{ number_format($combinedPnl['sales'], 2) }}</strong></span>
                        <span>·</span>
                        <span>Total Expenses: <strong>QAR {{ number_format($combinedPnl['expenses'], 2) }}</strong></span>
                        <span>·</span>
                        <span>Net: <strong class="{{ $combinedPnl['profit'] >= 0 ? 'ld-text-positive' : 'ld-text-negative' }}">QAR {{ number_format($combinedPnl['profit'], 2) }}</strong></span>
                    </span>
                @endif
                <a href="{{ route('appointments.revenue.index') }}" class="ld-action-link">
                    View Finance <i class="bx bx-right-arrow-alt"></i>
                </a>
            </div>
        </div>

        <div class="ld-branch-grid" data-testid="admin-branch-pnl-grid">
            @foreach ($branchCards as $row)
                <div class="ld-branch-card" data-branch-card="{{ $row['key'] }}">
                    <div>
                        <div class="ld-branch-top">
                            <span class="ld-branch-eyebrow">{{ $row['header_label'] }}</span>

                            @if (!empty($row['has_expenses']))
                                @if ($row['margin_pct'] !== null)
                                    <span class="ld-margin-pill {{ $row['profit'] >= 0 ? 'ld-margin-pill--positive' : 'ld-margin-pill--negative' }}">
                                        {{ number_format($row['margin_pct'], 1) }}% margin
                                    </span>
                                @endif
                            @else
                                <a href="{{ route('appointments.revenue.index', ['branch' => $row['key']]) }}" class="ld-no-expense-link">
                                    No expenses logged · <span>+ Add Expense</span>
                                </a>
                            @endif
                        </div>

                        <div class="ld-rev-primary-label">Total Revenue (To Date)</div>
                        <div class="ld-rev-primary-val">QAR {{ number_format($row['sales'], 2) }}</div>
                    </div>

                    <div class="ld-branch-split">
                        <div>
                            <div class="ld-branch-metric-label">Total Expenses (To Date)</div>
                            <div class="ld-branch-metric-val">QAR {{ number_format($row['expenses'], 2) }}</div>
                        </div>
                        <div>
                            <div class="ld-branch-metric-label">Net Profit (To Date)</div>
                            <div class="ld-branch-metric-val {{ $row['profit'] >= 0 ? 'ld-text-positive' : 'ld-text-negative' }}">
                                QAR {{ number_format($row['profit'], 2) }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ====================================================================
         SECTION 2: MINIMALISTIC 2-COLUMN TEAM KPI SCORECARDS
         ==================================================================== --}}
    @if (!empty($teamScorecards))
        <div class="ld-section-head {{ !$showAdminFinance ? 'ld-section-head--first' : '' }}">
            <h2 class="ld-section-title">Team KPI Scorecards</h2>
            <div class="ld-section-meta">
                @if ($viewerUser && $viewerUser->canView('kpis'))
                    <a href="{{ route('kpi.hub') }}" class="ld-action-link">
                        Open KPI Hub <i class="bx bx-right-arrow-alt"></i>
                    </a>
                @endif
            </div>
        </div>

        <div class="ld-scorecard-grid" data-testid="team-split-scorecards">
            @foreach ($teamScorecards as $card)
                <a href="{{ $card['url'] }}"
                   class="ld-split-card {{ !empty($card['is_self']) ? 'ld-split-card--self' : '' }}"
                   data-scorecard-owner="{{ $card['key'] }}"
                   data-scorecard-status="{{ $card['status'] }}">

                    {{-- Left Column (~30%): Name + Short Role + Rounded Whole-Number % + 6px Status Dot --}}
                    <div class="ld-split-left">
                        <div>
                            <div class="ld-person-name">
                                {{ $card['name'] }}
                                @if (!empty($card['is_self']))
                                    <span class="ld-self-tag">You</span>
                                @endif
                            </div>
                            <div class="ld-person-role">{{ $card['role'] }}</div>
                        </div>

                        <div class="ld-score-wrap">
                            <span class="ld-score-num">{{ $card['overall_rounded'] }}%</span>
                            <span class="ld-status-inline">
                                <span class="ld-status-dot ld-status-dot--{{ $card['status_color'] }}"></span>
                                {{ $card['status'] }}
                            </span>
                        </div>
                    </div>

                    {{-- Right Column (~70%): Strictly 3 Single-Line Thin-Bar KPIs --}}
                    <div class="ld-split-right">
                        @foreach ($card['kpis'] as $kpi)
                            <div class="ld-kpi-row">
                                <div class="ld-kpi-line">
                                    <span class="ld-kpi-label">{{ $kpi['label'] }}</span>
                                    <span class="ld-kpi-val">{{ $kpi['value_label'] }}</span>
                                </div>
                                <div class="ld-bar-track">
                                    <div class="ld-bar-fill ld-bar-fill--{{ $kpi['color'] }}"
                                         style="width: {{ $kpi['pct'] <= 0 ? 0 : $kpi['pct'] }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </a>
            @endforeach
        </div>
    @endif

</div>
@endsection
