@extends('layouts.app')
@section('title', 'Laleen Ops — Dashboard')

@section('content')
<style>
    /* =========================================================================
       LALEEN OPS — MINIMALIST EXECUTIVE DASHBOARD (@laleen-minimal-ui)
       Dark Espresso · Rose-Gold · Champagne · Zero Nested Box Overload
       ========================================================================= */
    .laleen-ops-wrap {
        --lo-surface: linear-gradient(180deg, rgba(34, 22, 27, 0.92) 0%, rgba(21, 14, 18, 0.96) 100%);
        --lo-border: rgba(229, 184, 168, 0.14);
        --lo-border-hover: rgba(229, 184, 168, 0.34);
        --lo-hairline: rgba(255, 255, 255, 0.06);
        --lo-cream: #f8efe6;
        --lo-taupe: #b8a29a;
        --lo-rose: #e5b8a8;
        --lo-rose-deep: #d98f83;
        --lo-champagne: #d4a373;
        --lo-emerald: #6ee7b7;
        --lo-amber: #fbbf24;
        --lo-ruby: #fda4af;
        color: var(--lo-cream);
        padding-bottom: 1.5rem;
    }

    /* ---- Compact Executive Top Bar ---- */
    .lo-topbar {
        background: var(--lo-surface);
        border: 1px solid var(--lo-border);
        border-radius: 16px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.25rem;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .lo-brand-title {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 1.38rem;
        font-weight: 700;
        color: var(--lo-cream);
        margin: 0;
        letter-spacing: -0.01em;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
    }

    .lo-period-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-family: inherit;
        font-size: 0.74rem;
        font-weight: 600;
        color: var(--lo-rose);
        background: rgba(229, 184, 168, 0.1);
        border: 1px solid rgba(229, 184, 168, 0.22);
        border-radius: 999px;
        padding: 0.22rem 0.68rem;
        letter-spacing: 0.02em;
    }

    .lo-brand-sub {
        color: var(--lo-taupe);
        font-size: 0.8rem;
        margin: 0.2rem 0 0;
    }

    .lo-filter-form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    .lo-date-field {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(229, 184, 168, 0.18);
        border-radius: 10px;
        padding: 0.28rem 0.6rem;
    }

    .lo-date-field label {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--lo-taupe);
        margin: 0;
    }

    .lo-date-field input[type="date"] {
        background: transparent;
        border: 0;
        color: var(--lo-cream);
        font-size: 0.8rem;
        font-weight: 600;
        outline: none;
        padding: 0.1rem 0;
        color-scheme: dark;
    }

    .lo-btn-apply {
        background: linear-gradient(135deg, var(--lo-rose), var(--lo-rose-deep));
        color: #1c1014;
        border: 0;
        border-radius: 10px;
        padding: 0.42rem 0.9rem;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        transition: opacity 0.18s ease, transform 0.18s ease;
    }

    .lo-btn-apply:hover {
        opacity: 0.92;
        color: #1c1014;
        transform: translateY(-1px);
    }

    .lo-btn-reset {
        background: transparent;
        color: var(--lo-taupe);
        border: 1px solid rgba(229, 184, 168, 0.2);
        border-radius: 10px;
        padding: 0.4rem 0.78rem;
        font-size: 0.76rem;
        font-weight: 600;
        text-decoration: none;
        transition: color 0.18s ease, border-color 0.18s ease;
    }

    .lo-btn-reset:hover {
        color: var(--lo-cream);
        border-color: var(--lo-rose);
    }

    /* ---- Section Headers ---- */
    .lo-section-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin: 1.35rem 0 0.75rem;
    }

    .lo-section-head--first {
        margin-top: 0.25rem;
    }

    .lo-section-title {
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--lo-rose);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .lo-live-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--lo-emerald);
        box-shadow: 0 0 8px rgba(110, 231, 183, 0.65);
        display: inline-block;
    }

    .lo-section-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.65rem;
        font-size: 0.78rem;
        color: var(--lo-taupe);
    }

    .lo-combined-pill {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.45rem;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--lo-border);
        border-radius: 999px;
        padding: 0.28rem 0.85rem;
        font-size: 0.76rem;
        color: var(--lo-taupe);
        font-variant-numeric: tabular-nums;
    }

    .lo-combined-pill strong {
        color: var(--lo-cream);
        font-weight: 700;
    }

    .lo-action-link {
        color: var(--lo-rose);
        font-size: 0.78rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        transition: color 0.18s ease;
    }

    .lo-action-link:hover {
        color: var(--lo-cream);
    }

    /* ---- Compact Live Today Salon Pulse ---- */
    .lo-pulse-grid {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 0.75rem;
    }

    @media (min-width: 576px) {
        .lo-pulse-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1200px) {
        .lo-pulse-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    .lo-pulse-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        background: var(--lo-surface);
        border: 1px solid var(--lo-border);
        border-radius: 14px;
        padding: 0.85rem 1rem;
        text-decoration: none;
        color: var(--lo-cream);
        transition: border-color 0.18s ease, transform 0.18s ease;
    }

    a.lo-pulse-card:hover {
        border-color: var(--lo-border-hover);
        color: var(--lo-cream);
        transform: translateY(-1px);
    }

    .lo-pulse-label {
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: var(--lo-taupe);
        margin-bottom: 0.2rem;
    }

    .lo-pulse-value {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--lo-cream);
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
    }

    .lo-pulse-sub {
        font-size: 0.74rem;
        color: var(--lo-taupe);
        margin-top: 0.22rem;
        line-height: 1.35;
    }

    .lo-pulse-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: rgba(229, 184, 168, 0.09);
        color: var(--lo-rose);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    /* ---- Section 1: Minimalistic 2-Branch Revenue & Expenses Cards ---- */
    .lo-branch-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    @media (min-width: 992px) {
        .lo-branch-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    .lo-branch-card {
        background: var(--lo-surface);
        border: 1px solid var(--lo-border);
        border-radius: 16px;
        padding: 1.2rem 1.35rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .lo-branch-top {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.85rem;
    }

    .lo-branch-eyebrow {
        font-size: 0.74rem;
        font-weight: 700;
        letter-spacing: 0.11em;
        text-transform: uppercase;
        color: var(--lo-rose);
    }

    .lo-margin-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 0.2rem 0.65rem;
        font-size: 0.72rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .lo-margin-pill--positive {
        color: var(--lo-emerald);
        background: rgba(110, 231, 183, 0.1);
        border: 1px solid rgba(110, 231, 183, 0.25);
    }

    .lo-margin-pill--negative {
        color: var(--lo-ruby);
        background: rgba(253, 164, 175, 0.1);
        border: 1px solid rgba(253, 164, 175, 0.25);
    }

    .lo-no-expense-link {
        font-size: 0.74rem;
        color: var(--lo-taupe);
        text-decoration: none;
        transition: color 0.18s ease;
    }

    .lo-no-expense-link span {
        color: var(--lo-rose);
        font-weight: 600;
    }

    .lo-no-expense-link:hover {
        color: var(--lo-cream);
    }

    .lo-rev-primary-label {
        font-size: 0.75rem;
        color: var(--lo-taupe);
        margin-bottom: 0.2rem;
    }

    .lo-rev-primary-val {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--lo-cream);
        line-height: 1.15;
        font-variant-numeric: tabular-nums;
    }

    .lo-branch-split {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        margin-top: 1rem;
        padding-top: 0.9rem;
        border-top: 1px solid var(--lo-hairline);
    }

    .lo-branch-metric-label {
        font-size: 0.73rem;
        color: var(--lo-taupe);
        margin-bottom: 0.2rem;
    }

    .lo-branch-metric-val {
        font-size: 1.04rem;
        font-weight: 700;
        color: var(--lo-cream);
        font-variant-numeric: tabular-nums;
    }

    .lo-text-positive {
        color: var(--lo-emerald) !important;
    }

    .lo-text-negative {
        color: var(--lo-ruby) !important;
    }

    /* ---- Section 2: 2-Column Team KPI Split Scorecards ---- */
    .lo-scorecard-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    @media (min-width: 1200px) {
        .lo-scorecard-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    .lo-split-card {
        display: flex;
        flex-direction: column;
        background: var(--lo-surface);
        border: 1px solid var(--lo-border);
        border-radius: 16px;
        padding: 1.15rem 1.25rem;
        text-decoration: none;
        color: var(--lo-cream);
        transition: border-color 0.18s ease, transform 0.18s ease;
    }

    .lo-split-card:hover {
        border-color: var(--lo-border-hover);
        color: var(--lo-cream);
        transform: translateY(-2px);
    }

    .lo-split-card--self {
        border-color: rgba(212, 163, 115, 0.42);
    }

    @media (min-width: 768px) {
        .lo-split-card {
            flex-direction: row;
            align-items: stretch;
            gap: 1.25rem;
        }
    }

    /* Left Summary Column (~36%) */
    .lo-split-left {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding-bottom: 0.95rem;
        margin-bottom: 0.95rem;
        border-bottom: 1px solid var(--lo-hairline);
    }

    @media (min-width: 768px) {
        .lo-split-left {
            flex: 0 0 36%;
            max-width: 36%;
            padding-bottom: 0;
            margin-bottom: 0;
            padding-right: 1.15rem;
            border-bottom: 0;
            border-right: 1px solid var(--lo-hairline);
        }
    }

    .lo-person-head {
        display: flex;
        align-items: flex-start;
        gap: 0.7rem;
    }

    .lo-avatar {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: rgba(229, 184, 168, 0.12);
        color: var(--lo-rose);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .lo-person-name {
        font-size: 1rem;
        font-weight: 700;
        color: var(--lo-cream);
        line-height: 1.2;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.4rem;
    }

    .lo-self-pill {
        font-size: 0.64rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--lo-champagne);
        background: rgba(212, 163, 115, 0.14);
        border-radius: 999px;
        padding: 0.12rem 0.48rem;
    }

    .lo-person-role {
        font-size: 0.75rem;
        color: var(--lo-taupe);
        margin-top: 0.18rem;
        line-height: 1.3;
    }

    .lo-score-block {
        margin-top: 0.9rem;
    }

    .lo-overall-row {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0.55rem;
    }

    .lo-overall-pct {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 1.72rem;
        font-weight: 700;
        line-height: 1.05;
        color: var(--lo-cream);
        font-variant-numeric: tabular-nums;
    }

    .lo-status-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 0.18rem 0.58rem;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .lo-status-pill--red {
        color: var(--lo-ruby);
        background: rgba(253, 164, 175, 0.12);
    }

    .lo-status-pill--amber {
        color: var(--lo-amber);
        background: rgba(251, 191, 36, 0.12);
    }

    .lo-status-pill--green {
        color: var(--lo-emerald);
        background: rgba(110, 231, 183, 0.12);
    }

    .lo-passing-meta {
        font-size: 0.73rem;
        color: var(--lo-taupe);
        margin-top: 0.32rem;
        font-variant-numeric: tabular-nums;
    }

    /* Right Live Mini Bars Column (~64% — Zero Truncation) */
    .lo-split-right {
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 0.72rem;
    }

    .lo-kpi-row {
        display: flex;
        flex-direction: column;
        gap: 0.28rem;
    }

    .lo-kpi-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.35rem 0.75rem;
    }

    .lo-kpi-label {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--lo-cream);
        line-height: 1.3;
    }

    .lo-kpi-val {
        font-size: 0.76rem;
        font-weight: 600;
        color: var(--lo-taupe);
        font-variant-numeric: tabular-nums;
        line-height: 1.3;
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
    }

    .lo-bar-track {
        width: 100%;
        height: 5px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.07);
        overflow: hidden;
    }

    .lo-bar-fill {
        height: 100%;
        border-radius: 999px;
        transition: width 0.3s ease;
    }

    .lo-bar-fill--red {
        background: var(--lo-ruby);
    }

    .lo-bar-fill--amber {
        background: var(--lo-amber);
    }

    .lo-bar-fill--green {
        background: var(--lo-emerald);
    }

    /* ---- Print Readiness (@laleen-minimal-ui) ---- */
    @media print {
        .lo-filter-form,
        .lo-action-link {
            display: none !important;
        }

        .laleen-ops-wrap,
        .lo-topbar,
        .lo-pulse-card,
        .lo-branch-card,
        .lo-split-card {
            background: #ffffff !important;
            color: #111111 !important;
            border: 1px solid #cccccc !important;
            box-shadow: none !important;
        }

        .lo-brand-title,
        .lo-pulse-value,
        .lo-rev-primary-val,
        .lo-branch-metric-val,
        .lo-person-name,
        .lo-overall-pct,
        .lo-kpi-label {
            color: #111111 !important;
        }

        .lo-brand-sub,
        .lo-pulse-label,
        .lo-pulse-sub,
        .lo-rev-primary-label,
        .lo-branch-metric-label,
        .lo-person-role,
        .lo-passing-meta,
        .lo-kpi-val {
            color: #444444 !important;
        }

        .lo-bar-track {
            background: #e5e7eb !important;
        }
    }
</style>

@php
    $leadsDueTodayCount = (int) ($operationalPulse['leads_due_today'] ?? 0);
    $leadsOverdueCount = (int) ($operationalPulse['leads_overdue'] ?? 0);
    $leadsUnscheduledCount = (int) ($operationalPulse['leads_unscheduled'] ?? 0);
    $leadsActionQueueCount = (int) ($operationalPulse['leads_action_queue'] ?? ($leadsDueTodayCount + $leadsOverdueCount + $leadsUnscheduledCount));
    $viewerUser = auth()->user();
@endphp

<div class="laleen-ops-wrap">

    {{-- ====================================================================
         COMPACT TOP BAR + DATE RANGE FILTER (DEFAULTING TO MONTH-TO-DATE)
         ==================================================================== --}}
    <div class="lo-topbar">
        <div>
            <h1 class="lo-brand-title">
                Laleen Ops
                <span class="lo-period-pill">
                    <i class="bx bx-calendar"></i>
                    {{ $dashFrom->format('d M') }} – {{ $dashTo->format('d M Y') }}
                </span>
            </h1>
            <p class="lo-brand-sub">Executive salon pulse, to-date branch performance, and live team KPI scorecards.</p>
        </div>

        <form method="GET" action="{{ route('dashboard') }}" class="lo-filter-form">
            <div class="lo-date-field">
                <label for="dash_date_from">From</label>
                <input type="date" id="dash_date_from" name="date_from" value="{{ $dashFrom->toDateString() }}">
            </div>
            <div class="lo-date-field">
                <label for="dash_date_to">To</label>
                <input type="date" id="dash_date_to" name="date_to" value="{{ $dashTo->toDateString() }}">
            </div>
            <button type="submit" class="lo-btn-apply">Apply</button>
            @if (request()->filled('date_from') || request()->filled('date_to'))
                <a href="{{ route('dashboard') }}" class="lo-btn-reset">Reset</a>
            @endif
        </form>
    </div>

    {{-- ====================================================================
         LIVE TODAY SALON PULSE (COMPACT STRIP)
         ==================================================================== --}}
    <div class="lo-section-head lo-section-head--first">
        <h2 class="lo-section-title">
            <span class="lo-live-dot"></span>
            Live Today Salon Pulse
        </h2>
        <div class="lo-section-meta">
            <span>{{ now()->format('l, d M Y') }}</span>
        </div>
    </div>

    <div class="lo-pulse-grid">
        @php
            $canBookings = $viewerUser && $viewerUser->canView('bookings');
            $canLeads = $viewerUser && $viewerUser->canView('leads');
            $canStaff = $viewerUser && $viewerUser->canView('staff');
            $canClients = $viewerUser && $viewerUser->canView('clients');
        @endphp

        @if ($canBookings)
            <a href="{{ route('appointments.index') }}" class="lo-pulse-card">
        @else
            <div class="lo-pulse-card">
        @endif
                <div>
                    <div class="lo-pulse-label">Today's Appointments</div>
                    <div class="lo-pulse-value">{{ $operationalPulse['today_appointments'] ?? 0 }}</div>
                    <div class="lo-pulse-sub">{{ $operationalPulse['today_completed'] ?? 0 }} completed today</div>
                </div>
                <span class="lo-pulse-icon"><i class="bx bx-calendar-check"></i></span>
        @if ($canBookings)
            </a>
        @else
            </div>
        @endif

        @if ($canLeads)
            <a href="{{ route('leads.index') }}" class="lo-pulse-card">
        @else
            <div class="lo-pulse-card">
        @endif
                <div>
                    <div class="lo-pulse-label">Leads Action Queue</div>
                    <div class="lo-pulse-value" data-testid="leads-action-queue-count">{{ $leadsActionQueueCount }}</div>
                    <div class="lo-pulse-sub">
                        {{ $leadsDueTodayCount }} due today · {{ $leadsOverdueCount }} overdue · {{ $leadsUnscheduledCount }} unscheduled
                    </div>
                </div>
                <span class="lo-pulse-icon"><i class="bx bx-message-rounded-dots"></i></span>
        @if ($canLeads)
            </a>
        @else
            </div>
        @endif

        @if ($canStaff)
            <a href="{{ route('staffs.index') }}" class="lo-pulse-card">
        @else
            <div class="lo-pulse-card">
        @endif
                <div>
                    <div class="lo-pulse-label">Active Staff on Duty</div>
                    <div class="lo-pulse-value">{{ $operationalPulse['present_staff'] ?? 0 }} / {{ $operationalPulse['active_staff'] ?? 0 }}</div>
                    <div class="lo-pulse-sub">Present vs active salon roster</div>
                </div>
                <span class="lo-pulse-icon"><i class="bx bx-user-check"></i></span>
        @if ($canStaff)
            </a>
        @else
            </div>
        @endif

        @if ($canClients)
            <a href="{{ route('customers.index') }}" class="lo-pulse-card">
        @else
            <div class="lo-pulse-card">
        @endif
                <div>
                    <div class="lo-pulse-label">Total Active Clients</div>
                    <div class="lo-pulse-value">{{ number_format($operationalPulse['total_clients'] ?? 0) }}</div>
                    <div class="lo-pulse-sub">{{ $operationalPulse['total_open_leads'] ?? 0 }} open pipeline leads</div>
                </div>
                <span class="lo-pulse-icon"><i class="bx bx-heart"></i></span>
        @if ($canClients)
            </a>
        @else
            </div>
        @endif
    </div>

    {{-- ====================================================================
         SECTION 1: BRANCH REVENUE & EXPENSES UP-TO-DATE (STRICT ADMIN ONLY)
         ==================================================================== --}}
    @if (!empty($isAdmin) && !empty($branchCards))
        <div class="lo-section-head" data-testid="admin-finance-section-head">
            <h2 class="lo-section-title">Branch Revenue &amp; Expenses — To Date</h2>
            <div class="lo-section-meta">
                @if (!empty($combinedPnl))
                    <span class="lo-combined-pill">
                        <span>Total Revenue: <strong>QAR {{ number_format($combinedPnl['sales'], 2) }}</strong></span>
                        <span>·</span>
                        <span>Total Expenses: <strong>QAR {{ number_format($combinedPnl['expenses'], 2) }}</strong></span>
                        <span>·</span>
                        <span>Net: <strong class="{{ $combinedPnl['profit'] >= 0 ? 'lo-text-positive' : 'lo-text-negative' }}">QAR {{ number_format($combinedPnl['profit'], 2) }}</strong></span>
                    </span>
                @endif
                <a href="{{ route('appointments.revenue.index') }}" class="lo-action-link">
                    View Finance <i class="bx bx-right-arrow-alt"></i>
                </a>
            </div>
        </div>

        <div class="lo-branch-grid" data-testid="admin-branch-pnl-grid">
            @foreach ($branchCards as $row)
                <div class="lo-branch-card" data-branch-card="{{ $row['key'] }}">
                    <div>
                        <div class="lo-branch-top">
                            <span class="lo-branch-eyebrow">{{ $row['header_label'] }}</span>

                            @if (!empty($row['has_expenses']))
                                @if ($row['margin_pct'] !== null)
                                    <span class="lo-margin-pill {{ $row['profit'] >= 0 ? 'lo-margin-pill--positive' : 'lo-margin-pill--negative' }}">
                                        {{ number_format($row['margin_pct'], 1) }}% margin
                                    </span>
                                @endif
                            @else
                                <a href="{{ route('appointments.revenue.index', ['branch' => $row['key']]) }}" class="lo-no-expense-link">
                                    No expenses logged · <span>+ Add Expense</span>
                                </a>
                            @endif
                        </div>

                        <div class="lo-rev-primary-label">Total Revenue (To Date)</div>
                        <div class="lo-rev-primary-val">QAR {{ number_format($row['sales'], 2) }}</div>
                    </div>

                    <div class="lo-branch-split">
                        <div>
                            <div class="lo-branch-metric-label">Total Expenses (To Date)</div>
                            <div class="lo-branch-metric-val">QAR {{ number_format($row['expenses'], 2) }}</div>
                        </div>
                        <div>
                            <div class="lo-branch-metric-label">Net Profit (To Date)</div>
                            <div class="lo-branch-metric-val {{ $row['profit'] >= 0 ? 'lo-text-positive' : 'lo-text-negative' }}">
                                QAR {{ number_format($row['profit'], 2) }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ====================================================================
         SECTION 2: TEAM KPI "SPLIT SCORECARDS" (2-COLUMN WIDE MINI-BAR CARDS)
         ==================================================================== --}}
    @if (!empty($teamScorecards))
        <div class="lo-section-head">
            <h2 class="lo-section-title">Team KPI Split Scorecards</h2>
            <div class="lo-section-meta">
                <span>{{ $dashFrom->format('d M') }} – {{ $dashTo->format('d M Y') }} · Sorted by attention priority</span>
                @if ($viewerUser && $viewerUser->canView('kpis'))
                    <a href="{{ route('kpi.hub') }}" class="lo-action-link">
                        Open KPI Hub <i class="bx bx-right-arrow-alt"></i>
                    </a>
                @endif
            </div>
        </div>

        <div class="lo-scorecard-grid" data-testid="team-split-scorecards">
            @foreach ($teamScorecards as $card)
                <a href="{{ $card['url'] }}"
                   class="lo-split-card {{ !empty($card['is_self']) ? 'lo-split-card--self' : '' }}"
                   data-scorecard-owner="{{ $card['key'] }}"
                   data-scorecard-status="{{ $card['status'] }}">

                    {{-- Left Column (~36%): Identity + Overall % + Status + Passing Count --}}
                    <div class="lo-split-left">
                        <div class="lo-person-head">
                            <span class="lo-avatar">{{ $card['initials'] }}</span>
                            <div>
                                <div class="lo-person-name">
                                    {{ $card['name'] }}
                                    @if (!empty($card['is_self']))
                                        <span class="lo-self-pill">Your Scorecard</span>
                                    @endif
                                </div>
                                <div class="lo-person-role">{{ $card['role'] }}</div>
                            </div>
                        </div>

                        <div class="lo-score-block">
                            <div class="lo-overall-row">
                                <span class="lo-overall-pct">{{ number_format($card['overall_pct'], 1) }}%</span>
                                <span class="lo-status-pill lo-status-pill--{{ $card['status_color'] }}">
                                    {{ $card['status'] }}
                                </span>
                            </div>
                            <div class="lo-passing-meta">
                                {{ $card['passing_count'] }} / {{ $card['total_kpis'] }} KPIs Passing
                            </div>
                        </div>
                    </div>

                    {{-- Right Column (~64%): 3–4 Full-Width Mini KPI Bars (Zero Truncation) --}}
                    <div class="lo-split-right">
                        @foreach ($card['kpis'] as $kpi)
                            <div class="lo-kpi-row">
                                <div class="lo-kpi-head">
                                    <span class="lo-kpi-label">{{ $kpi['label'] }}</span>
                                    <span class="lo-kpi-val">{{ $kpi['value_label'] }}</span>
                                </div>
                                <div class="lo-bar-track">
                                    <div class="lo-bar-fill lo-bar-fill--{{ $kpi['color'] }}"
                                         style="width: {{ max(2, $kpi['pct']) == 2 && $kpi['pct'] <= 0 ? 0 : $kpi['pct'] }}%;"></div>
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
