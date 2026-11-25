@extends('layouts.app')

@section('title', 'Bookings Analytics')

<style>
    :root {
        --bk-border: rgba(217, 143, 131,0.16);
        --bk-border-strong: rgba(217, 143, 131,0.3);
        --bk-muted: #c9a39a;
        --bk-ink: #e79a91;
        --bk-primary: #d98f83;
        --bk-success: #8ea88a;
        --bk-danger: #a8524a;
        --bk-warning: #c9a66b;
        --bk-info: #8aa6ab;
    }

    .bk-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 18px;
    }

    .bk-header h4 {
        margin-bottom: 2px;
    }

    .bk-header p {
        color: var(--bk-muted);
        margin-bottom: 0;
        font-size: 13.5px;
    }

    /* ---------------- FILTER BAR ---------------- */
    .bk-filter-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--bk-border);
        border-radius: 16px;
        padding: 14px 18px;
        margin-bottom: 20px;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
    }

    .bk-filter-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .bk-filter-divider {
        width: 1px;
        align-self: stretch;
        min-height: 26px;
        background: var(--bk-border);
        margin: 0 2px;
    }

    .bk-date-input {
        height: 36px;
        border: 1px solid var(--bk-border-strong);
        border-radius: 9px;
        padding: 0 10px;
        font-size: 13px;
        font-weight: 600;
        color: var(--bk-ink);
        background-color: #241e1c;
    }

    .bk-date-input:focus {
        outline: none;
        border-color: var(--bk-primary);
        box-shadow: 0 0 0 3px rgba(217, 143, 131, .15);
    }

    .bk-apply-btn {
        height: 36px;
        border-radius: 9px;
        font-weight: 700;
        font-size: 13px;
        padding: 0 16px;
    }

    .bk-search-wrap {
        position: relative;
        display: flex;
        align-items: center;
        flex: 1 1 240px;
        min-width: 200px;
        max-width: 320px;
    }

    .bk-search-wrap i {
        position: absolute;
        left: 10px;
        color: var(--bk-muted);
        font-size: 15px;
        pointer-events: none;
    }

    .bk-search-input {
        width: 100%;
        padding-left: 30px !important;
        font-weight: 500 !important;
    }

    /* ---------------- KPI CARDS ---------------- */
    .bk-kpi-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--bk-border);
        border-radius: 16px;
        padding: 18px 20px;
        height: 100%;
        transition: all .2s ease;
    }

    .bk-kpi-card:hover {
        box-shadow: 0 8px 24px rgba(16, 24, 40, .08);
        transform: translateY(-2px);
    }

    .bk-kpi-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .bk-kpi-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--bk-muted);
    }

    .bk-kpi-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .bk-kpi-value {
        font-size: 26px;
        font-weight: 700;
        color: var(--bk-ink);
        letter-spacing: -.01em;
        line-height: 1.2;
    }

    .bk-kpi-sub {
        display: flex;
        gap: 14px;
        margin-top: 10px;
        font-size: 12.5px;
        color: var(--bk-muted);
    }

    .bk-kpi-sub b {
        color: var(--bk-ink);
        font-weight: 700;
    }

    /* ---------------- CHART CARDS ---------------- */
    .bk-chart-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--bk-border);
        border-radius: 16px;
        padding: 18px 20px;
        height: 100%;
    }

    .bk-chart-card h6 {
        font-weight: 700;
        margin-bottom: 2px;
    }

    .bk-chart-card .bk-chart-sub {
        font-size: 12.5px;
        color: var(--bk-muted);
        margin-bottom: 10px;
    }

    /* ---------------- BRANCH TABLE ---------------- */
    .bk-branch-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--bk-muted);
        font-weight: 700;
        border-top: none;
    }

    .bk-branch-table td {
        font-size: 13.5px;
        vertical-align: middle;
    }

    /* ---------------- LIST CARD ---------------- */
    .bk-section-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--bk-border);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .bk-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid var(--bk-border);
    }

    .bk-section-head h6 {
        margin-bottom: 0;
        font-weight: 700;
    }

    .bk-table {
        margin-bottom: 0;
    }

    .bk-table thead th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--bk-muted);
        font-weight: 700;
        border-top: none;
        background: rgba(217, 143, 131,0.05);
        white-space: nowrap;
    }

    .bk-table tbody td {
        font-size: 13.5px;
        vertical-align: middle;
    }

    .bk-status-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11.5px;
        font-weight: 600;
        padding: 2px 10px;
        border-radius: 999px;
        white-space: nowrap;
    }

    .bk-combo-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        color: #f8efe6;
        background: rgba(212, 163, 115, 0.2);
        border: 1px solid rgba(229, 184, 168, 0.36);
        white-space: nowrap;
    }

    .bk-pending-chip {
        display: inline-flex;
        align-items: center;
        padding: 1px 7px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 600;
        color: #d4a373;
        background: rgba(212, 163, 115, 0.14);
    }

    .bk-decide-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        color: #c9a39a;
        background: rgba(201, 163, 154, 0.12);
        border: 1px solid rgba(201, 163, 154, 0.28);
        white-space: nowrap;
    }

    .bk-decide-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid rgba(201, 163, 154, 0.4);
        border-radius: 999px;
        padding: 4px 13px;
        font-size: 12px;
        font-weight: 600;
        background: transparent;
        color: #c9a39a;
        transition: all 0.15s ease;
    }

    .bk-decide-pill:hover {
        background: rgba(201, 163, 154, 0.1);
        color: #e79a91;
    }

    .bk-decide-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        background: rgba(255, 255, 255, 0.025);
        border: 1px solid rgba(201, 163, 154, 0.25);
        border-radius: 10px;
        padding: 10px 13px;
    }

    .bk-edit-combo-card {
        background: rgba(255, 255, 255, 0.025);
        border: 1px solid rgba(229, 184, 168, 0.22);
        border-radius: 12px;
        padding: 12px 14px;
        cursor: pointer;
        transition: border-color 0.15s ease, background 0.15s ease;
    }

    .bk-edit-combo-card:hover {
        background: rgba(229, 184, 168, 0.06);
        border-color: rgba(229, 184, 168, 0.42);
    }

    .bk-edit-combo-trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .bk-edit-combo-picked-list {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px 8px;
        font-size: 12.5px;
        color: #f8efe6;
    }

    .bk-edit-combo-sub-back {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 1px solid rgba(229, 184, 168, 0.24);
        background: rgba(255, 255, 255, 0.03);
        color: #f8efe6;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: background 0.15s ease, border-color 0.15s ease;
    }

    .bk-edit-combo-sub-back:hover {
        background: rgba(229, 184, 168, 0.12);
        border-color: rgba(229, 184, 168, 0.48);
    }

    .bk-edit-combo-counter-pill {
        display: inline-flex;
        align-items: center;
        font-size: 11px;
        font-weight: 700;
        color: #e5b8a8;
        background: rgba(229, 184, 168, 0.13);
        border-radius: 999px;
        padding: 3px 10px;
        white-space: nowrap;
    }

    .bk-edit-combo-counter-pill.is-full {
        color: #6ee7b7;
        background: rgba(110, 231, 183, 0.14);
    }

    .bk-edit-combo-search-wrap {
        position: relative;
        margin-bottom: 10px;
    }

    .bk-edit-combo-search-wrap i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9b8882;
        font-size: 15px;
        pointer-events: none;
    }

    .bk-edit-combo-search-input {
        width: 100%;
        background: rgba(255, 255, 255, 0.035);
        border: 1px solid rgba(229, 184, 168, 0.2);
        border-radius: 9px;
        color: #f8efe6;
        font-size: 13px;
        padding: 8px 12px 8px 35px;
        outline: none;
    }

    .bk-edit-combo-search-input:focus {
        border-color: rgba(229, 184, 168, 0.48);
    }

    .bk-edit-combo-skip-bar {
        width: 100%;
        margin-bottom: 10px;
        padding: 8px 12px;
        border-radius: 9px;
        border: 1px dashed rgba(212, 163, 115, 0.34);
        background: rgba(212, 163, 115, 0.07);
        color: #e8c7a1;
        font-size: 12.5px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        cursor: pointer;
        text-align: left;
        transition: background 0.15s ease, border-color 0.15s ease;
    }

    .bk-edit-combo-skip-bar:hover {
        background: rgba(212, 163, 115, 0.14);
        border-color: rgba(212, 163, 115, 0.55);
    }

    .bk-edit-combo-svc-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 2px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        transition: opacity 0.15s ease;
    }

    .bk-edit-combo-svc-row:last-child {
        border-bottom: none;
    }

    .bk-edit-combo-svc-row.is-dimmed {
        opacity: 0.42;
    }

    .bk-combo-seg {
        display: inline-flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 999px;
        padding: 2px;
        gap: 2px;
        flex-shrink: 0;
    }

    .bk-combo-mode-pill {
        border: none;
        border-radius: 999px;
        background: transparent;
        color: #b8a29a;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 11px;
        transition: all 0.15s ease;
    }

    .bk-combo-mode-pill.is-today {
        background: rgba(110, 231, 183, 0.18);
        color: #6ee7b7;
    }

    .bk-combo-mode-pill.is-pending {
        background: rgba(212, 163, 115, 0.2);
        color: #e8c7a1;
    }

    .bk-combo-mode-pill:disabled {
        opacity: 0.38;
        cursor: not-allowed;
    }

    .bk-conflict-banner {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 11px 20px;
        background: rgba(168, 82, 74, 0.14);
        border-top: 1px solid rgba(168, 82, 74, 0.38);
        color: #f8efe6;
        font-size: 0.8125rem;
        line-height: 1.45;
    }

    .bk-conflict-banner-body {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        min-width: 0;
    }

    .bk-conflict-banner-dot {
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #fda4af;
        margin-top: 6px;
        flex-shrink: 0;
    }

    .bk-conflict-banner-close {
        background: transparent;
        border: 0;
        color: #b8a29a;
        font-size: 1rem;
        line-height: 1;
        padding: 2px 4px;
        cursor: pointer;
        flex-shrink: 0;
    }

    .bk-conflict-banner-close:hover {
        color: #f8efe6;
    }

    .bk-toast-notice {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 2100;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 400px;
        padding: 11px 15px;
        border-radius: 12px;
        background: linear-gradient(180deg, rgba(34, 22, 27, 0.96) 0%, rgba(21, 14, 18, 0.98) 100%);
        border: 1px solid rgba(253, 164, 175, 0.35);
        color: #f8efe6;
        font-size: 13px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.42);
    }
</style>

@section('content')

    <div class="bk-header">
        <div>
            <h4>Bookings Analytics</h4>
            <p>Volume, revenue and status trends across every appointment. Search, edit or reschedule a booking below - creating new bookings and checking out still happen on the Enhanced Calendar page.</p>
        </div>
    </div>

    <!-- FILTER BAR (period + branch dropdowns; "Custom Range" reveals date pickers) -->
    <div class="bk-filter-card">
        <form method="GET" action="{{ route('appointments.index') }}" id="bkFilterForm">
            <div class="bk-filter-row">
                <select name="period" id="bkPeriod" class="bk-date-input" onchange="bkOnPeriodChange(this)">
                    @foreach (\App\Http\Controllers\AppointmentController::PERIOD_LABELS as $key => $label)
                        <option value="{{ $key }}" {{ $period === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                <div class="bk-filter-divider"></div>

                <select name="branch" class="bk-date-input" onchange="document.getElementById('bkFilterForm').submit()">
                    <option value="">All Branches</option>
                    @foreach (\App\Http\Controllers\AppointmentController::BRANCH_LABELS as $key => $label)
                        <option value="{{ $key }}" {{ $branch === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                <div class="bk-filter-divider"></div>

                <div class="bk-search-wrap">
                    <i class="bx bx-search"></i>
                    <input type="text" name="search" id="bkSearch" class="bk-date-input bk-search-input"
                        placeholder="Search by customer name or phone" value="{{ $search }}" autocomplete="off">
                </div>

                <div id="bkCustomDates" style="align-items: center; gap: 8px; {{ $period === 'custom' ? 'display:flex;' : 'display:none;' }}">
                    <div class="bk-filter-divider"></div>
                    <input type="date" name="from" id="bkFrom" class="bk-date-input" value="{{ $from->format('Y-m-d') }}">
                    <span class="text-muted small">to</span>
                    <input type="date" name="to" id="bkTo" class="bk-date-input" value="{{ $to->format('Y-m-d') }}">
                    <button type="submit" class="btn btn-primary bk-apply-btn">Apply</button>
                </div>

                <span class="text-muted small ms-1" id="bkRangeLabel" style="{{ $period === 'custom' ? 'display:none;' : '' }}">{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</span>

                @if ($period !== 'this_month' || $branch || $search)
                    <a href="{{ route('appointments.index') }}" class="btn btn-outline-secondary bk-apply-btn ms-auto">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <!-- KPI CARDS -->
    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
            <div class="bk-kpi-card">
                <div class="bk-kpi-top">
                    <span class="bk-kpi-label">Total Bookings</span>
                    <span class="bk-kpi-icon" style="background:rgba(217, 143, 131,.1); color:var(--bk-primary);">
                        <i class="bx bx-calendar-check"></i>
                    </span>
                </div>
                <div class="bk-kpi-value">{{ number_format($totalBookings) }}</div>
                <div class="bk-kpi-sub">
                    <span>{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="bk-kpi-card">
                <div class="bk-kpi-top">
                    <span class="bk-kpi-label">Scheduled Pipeline</span>
                    <span class="bk-kpi-icon" style="background:rgba(142,168,138,.12); color:#7fa876;">
                        <i class="bx bx-money"></i>
                    </span>
                </div>
                <div class="bk-kpi-value">{{ number_format($projectedRevenue ?? $totalRevenue, 2) }} <small class="fs-6 fw-semibold text-muted">QAR</small></div>
                <div class="bk-kpi-sub flex-wrap">
                    <span>Collected (Paid): <b>{{ number_format($collectedRevenue ?? 0, 2) }} QAR</b></span>
                    <span>Excludes cancelled &amp; no-show</span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="bk-kpi-card">
                <div class="bk-kpi-top">
                    <span class="bk-kpi-label">Avg. Booking Value</span>
                    <span class="bk-kpi-icon" style="background:rgba(138,166,171,.1); color:#8aa6ab;">
                        <i class="bx bx-trending-up"></i>
                    </span>
                </div>
                <div class="bk-kpi-value">{{ number_format($avgBookingValue, 2) }} <small class="fs-6 fw-semibold text-muted">QAR</small></div>
                <div class="bk-kpi-sub">
                    <span>Active pipeline &divide; {{ number_format($activeBookingsCount ?? $totalBookings) }} active bookings</span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="bk-kpi-card">
                <div class="bk-kpi-top">
                    <span class="bk-kpi-label">Unique Customers</span>
                    <span class="bk-kpi-icon" style="background:rgba(217, 143, 131,.1); color:var(--bk-primary);">
                        <i class="bx bx-user"></i>
                    </span>
                </div>
                <div class="bk-kpi-value">{{ number_format($uniqueCustomers) }}</div>
                <div class="bk-kpi-sub">
                    <span>Distinct phone numbers, excludes walk-ins</span>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 1 -->
    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="bk-chart-card">
                <h6>Bookings Trend</h6>
                <div class="bk-chart-sub">{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</div>
                <div id="bkTrendChart"></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="bk-chart-card">
                <h6>Status Breakdown</h6>
                <div class="bk-chart-sub">Share of bookings by status</div>
                <div id="bkStatusChart"></div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 2: BRANCH COMPARISON -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="bk-chart-card">
                <h6>Branch Performance</h6>
                <div class="bk-chart-sub">Bookings vs. revenue by branch &middot; {{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</div>
                <div class="row g-4 align-items-center">
                    <div class="col-lg-8">
                        <div id="bkBranchChart"></div>
                    </div>
                    <div class="col-lg-4">
                        <div class="table-responsive">
                            <table class="table bk-branch-table">
                                <thead>
                                    <tr>
                                        <th>Branch</th>
                                        <th>Bookings</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($branchStats as $stat)
                                        <tr>
                                            <td>{{ $stat['label'] }}</td>
                                            <td>{{ $stat['count'] }}</td>
                                            <td>{{ number_format($stat['revenue'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- BOOKINGS LIST -->
    <div class="bk-section-card">
        <div class="bk-section-head">
            <h6>Bookings <span class="badge bg-primary">{{ $appointments->total() }}</span></h6>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table bk-table align-middle">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Service</th>
                        <th>Branch</th>
                        <th>Date &amp; Time</th>
                        <th>Price (QAR)</th>
                        <th>Staff</th>
                        <th>Status</th>
                        <th>Agent</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $statusColors = [
                            'pending'     => ['bg' => 'rgba(201,166,107,.12)', 'fg' => '#c9a66b'],
                            'arrived'     => ['bg' => 'rgba(185,142,163,.12)', 'fg' => '#b98ea3'],
                            'in_progress' => ['bg' => 'rgba(201,123,74,.12)', 'fg' => '#c97b4a'],
                            'completed'   => ['bg' => 'rgba(142,168,138,.12)', 'fg' => '#8ea88a'],
                            'no_show'     => ['bg' => 'rgba(138,125,118,.15)', 'fg' => '#8a7d76'],
                            'cancelled'   => ['bg' => 'rgba(168,82,74,.12)', 'fg' => '#a8524a'],
                        ];
                    @endphp
                    @forelse($appointments as $appointment)
                        <tr>
                            <td>
                                @if (!empty($appointment->phone))
                                    <button type="button" class="btn btn-link p-0" onclick="showCustomerProfile('{{ $appointment->phone }}')">
                                        {{ $appointment->customer_name }}
                                    </button>
                                @else
                                    <span>{{ $appointment->customer_name ?: 'Walk-in' }}</span>
                                @endif
                            </td>
                            <td>{{ $appointment->phone }}</td>
                            <td>
                                @php
                                    $rowComboName = $appointment->display_combo_name;
                                    $rowPendingNames = $appointment->appointmentServices->where('is_pending', true)->pluck('name')->values()->all();
                                @endphp
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    @if ($rowComboName)
                                        <span class="bk-combo-badge">{{ $rowComboName }}</span>
                                    @endif
                                    @if ($appointment->is_freebie_redemption)
                                        <span class="bk-combo-badge" style="color: #6ee7b7; border-color: rgba(110,231,183,0.35); background: rgba(110,231,183,0.12);">Freebie · 0.00 QAR</span>
                                    @elseif ($appointment->freebie_package_id)
                                        <span class="bk-combo-badge">Service + Freebies</span>
                                    @endif
                                    @if ($appointment->decide_in_salon && !$rowComboName)
                                        <span class="bk-decide-badge">Decide in Salon</span>
                                    @else
                                        <span>{{ $appointment->is_combo_tbd ? 'Choose in Salon' : $appointment->service_name }}</span>
                                    @endif
                                    @if (!empty($rowPendingNames))
                                        <span class="bk-pending-chip">Pending: {{ implode(', ', $rowPendingNames) }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $appointment->branch_label ?? ucwords(str_replace('_', ' ', $appointment->branch)) }}</td>
                            <td>{{ $appointment->appointment_datetime->format('d M Y, h:i A') }}</td>
                            <td>{{ number_format($appointment->price, 2) }}</td>
                            <td>{{ $appointment->staff?->name ?? 'N/A' }}</td>
                            <td>
                                @php $sc = $statusColors[$appointment->status] ?? ['bg' => 'rgba(138,125,118,.15)', 'fg' => '#8a7d76']; @endphp
                                <span class="bk-status-chip" style="background:{{ $sc['bg'] }}; color:{{ $sc['fg'] }};">
                                    {{ ucwords(str_replace('_', ' ', $appointment->status)) }}
                                </span>
                            </td>
                            <td>{{ $appointment->booking_agent_display_name ?? '—' }}</td>
                            <td>
                                @if (auth()->user()->canEdit('bookings') || auth()->user()->canEdit('finance'))
                                    @php
                                        $isPaidBooking = (bool) ($appointment->paid_at || $appointment->sale);
                                        $standaloneSvcNames = $appointment->appointmentServices
                                            ->where('is_pending', false)
                                            ->whereNull('client_package_id')
                                            ->whereNull('combo_id')
                                            ->pluck('name')
                                            ->values()
                                            ->all();
                                        if (empty($standaloneSvcNames) && !$rowComboName && !$appointment->decide_in_salon) {
                                            $standaloneSvcNames = array_values(array_filter(
                                                array_map('trim', explode(',', (string) $appointment->service_name)),
                                                fn ($n) => $n !== '' && !in_array(strtolower($n), ['decide in salon', 'choose in salon', 'services tbd in salon'], true)
                                            ));
                                        }
                                        $isUnresolvedRowDecide = (bool) $appointment->decide_in_salon && empty($standaloneSvcNames) && !$rowComboName;
                                        $apptData = [
                                            'id' => $appointment->id,
                                            'customer_name' => $appointment->customer_name,
                                            'phone' => $appointment->phone,
                                            'branch' => $appointment->branch,
                                            'date' => $appointment->appointment_datetime->format('Y-m-d'),
                                            'time' => $appointment->appointment_datetime->format('H:i'),
                                            'service_names' => $standaloneSvcNames,
                                            'combo_name' => $rowComboName,
                                            'is_combo_tbd' => $appointment->is_combo_tbd,
                                            'decide_in_salon' => (bool) $appointment->decide_in_salon,
                                            'hold_duration' => $appointment->effective_hold_duration,
                                            'staff_id' => $appointment->staff_id,
                                            'staff_name' => $appointment->staff->name ?? null,
                                            'status' => $appointment->status,
                                            'is_paid' => $isPaidBooking,
                                            'payment_url' => route('appointments.revenue.payment', $appointment->id),
                                            'price' => (float) $appointment->price,
                                        ];
                                    @endphp
                                    <div class="d-flex gap-2 justify-content-center">
                                        @if (auth()->user()->canEdit('finance') && !$isPaidBooking && !in_array($appointment->status, ['cancelled', 'no_show']))
                                            <a href="{{ route('appointments.revenue.payment', $appointment->id) }}"
                                                @if ($isUnresolvedRowDecide)
                                                    onclick="event.preventDefault(); bkShowToast('Please add the service(s) or combo performed before completing checkout.');"
                                                @endif
                                                class="btn btn-sm btn-outline-success" title="Checkout &amp; Record Payment">
                                                💳 Checkout
                                            </a>
                                        @endif

                                        @if (auth()->user()->canEdit('bookings'))
                                            @if ($isPaidBooking)
                                                <span class="badge d-inline-flex align-items-center gap-1 px-2 py-1"
                                                    style="background: rgba(142, 168, 138, 0.14); border: 1px solid rgba(142, 168, 138, 0.36); color: #a7c7a2; font-weight: 600; font-size: 11.5px;"
                                                    title="Paid and closed — only Finance editors can reverse">
                                                    <i class="bx bx-lock-alt"></i> Paid
                                                </span>
                                                @if (auth()->user()->canEdit('finance'))
                                                    <form action="{{ route('appointments.destroy', $appointment->id) }}" method="POST"
                                                        onsubmit="return confirm('Reverse paid sale and delete this booking? This action cannot be undone.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Reverse Paid Sale &amp; Delete Booking">
                                                            <i class="bx bx-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-appt="{{ json_encode($apptData) }}"
                                                    onclick="openBookingEditModal(this)">
                                                    <i class="bx bx-edit-alt"></i>
                                                    {{ in_array($appointment->status, ['cancelled', 'no_show']) ? 'Reschedule' : 'Edit' }}
                                                </button>

                                                <form action="{{ route('appointments.destroy', $appointment->id) }}" method="POST"
                                                    onsubmit="return confirm('Are you sure you want to delete this booking? This action cannot be undone.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Booking">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">No bookings in this date range</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($appointments->hasPages())
            <div class="p-3 border-top" style="border-color: var(--bk-border) !important;">
                {{ $appointments->links() }}
            </div>
        @endif
    </div>

    @include('appointments.customer_profile')

    <!-- Edit / Reschedule Booking Modal -->
    <div class="modal fade" id="bookingEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="bookingEditForm" action="">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="customer_name" id="editCustomerName">
                    <input type="hidden" name="phone" id="editPhone">
                    <input type="hidden" name="appointment_datetime" id="editDatetimeHidden">
                    <input type="hidden" name="price" id="editPriceHidden" value="0">

                    <div class="modal-header" id="editModalMainHeader">
                        <h5 class="modal-title">
                            Edit Booking <span class="text-muted" id="editCustomerNameLabel"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-header d-none align-items-center gap-2" id="editModalComboSubHeader">
                        <button type="button" class="bk-edit-combo-sub-back" id="editModalComboBackBtn" title="Back to booking"><i class="bx bx-arrow-back"></i></button>
                        <h5 class="modal-title mb-0 text-truncate flex-grow-1" id="editModalComboTitle">&nbsp;</h5>
                        <span class="bk-edit-combo-counter-pill" id="editModalComboCounter">0 / 0 Selected</span>
                        <button type="button" class="btn-close ms-1" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="editModalMainBody">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Branch</label>
                                <select name="branch" id="editBranch" class="form-select" required>
                                    @foreach (\App\Http\Controllers\AppointmentController::BRANCH_LABELS as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" id="editStatus" class="form-select">
                                    <option value="pending">Pending</option>
                                    <option value="arrived">Arrived</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed" id="editStatusCompletedOpt">Completed (Checkout Required)</option>
                                    <option value="no_show">No Show</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <small class="text-warning d-none" id="editStatusCheckoutHint">
                                    Selecting Completed on an unpaid booking will redirect you to Checkout.
                                </small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date</label>
                                <input type="date" id="editDate" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Time</label>
                                <input type="time" id="editTime" class="form-control" required>
                            </div>

                            <div class="col-12 d-none" id="editComboPackagesWrap">
                                <label class="form-label">Combo Package</label>
                                <div id="editComboPackagesContainer"></div>
                                <div id="editComboHiddenInputs"></div>
                            </div>

                            <div class="col-12 d-none" id="editRedeemPackagesWrap">
                                <label class="form-label">Redeem Package <span class="text-muted fw-normal">(0.00 QAR &middot; active balance)</span></label>
                                <div id="editRedeemPackagesContainer" class="p-2 rounded" style="background: rgba(212, 163, 115, 0.08); border: 1px solid rgba(229, 184, 168, 0.24);"></div>
                            </div>

                            <div class="col-12">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label mb-0" id="editStandaloneServicesLabel">Services</label>
                                    <button type="button" class="bk-decide-pill d-none" id="editDecideSalonPill">Decide in Salon</button>
                                </div>
                                <input type="hidden" name="decide_in_salon" id="editDecideInSalonHidden" value="0">
                                <div id="editDecideInSalonCard" class="bk-decide-card mb-2 d-none">
                                    <div>
                                        <div class="fw-semibold" style="color:#f8efe6; font-size:13.5px;">Decide in Salon</div>
                                        <div class="text-muted" style="font-size:11.5px;">Client will choose services on arrival &middot; <span id="editDecideHoldMin">60</span> min hold</div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="bk-decide-badge">0.00 QAR</span>
                                        <button type="button" class="btn btn-sm p-0 border-0 text-muted" id="editDecideInSalonRemove" title="Remove Decide in Salon" style="font-size:18px; line-height:1;">&times;</button>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <input type="text" id="editServiceSearch" class="form-control form-control-sm" placeholder="Search services..." autocomplete="off">
                                </div>
                                <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto;">
                                    @foreach ($services as $s)
                                        <div class="form-check edit-service-row">
                                            <input class="form-check-input edit-service-checkbox" type="checkbox"
                                                name="service_name[]" value="{{ $s->name }}"
                                                id="editSvc{{ $s->id }}"
                                                data-price="{{ $s->price }}" data-duration="{{ $s->duration }}">
                                            <label class="form-check-label d-flex justify-content-between" for="editSvc{{ $s->id }}">
                                                <span>{{ $s->name }}</span>
                                                <span class="text-muted">{{ number_format($s->price, 2) }} QAR &middot; {{ $s->duration }} min</span>
                                            </label>
                                        </div>
                                    @endforeach
                                    <div class="text-muted small d-none" id="editServiceNoResults">No services match your search.</div>
                                </div>
                                <small class="text-muted" id="editServiceTotals">0 services selected</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Team Member</label>
                                <select name="staff_id" id="editStaffSelect" class="form-select">
                                    <option value="">-- Select Staff (Unassigned) --</option>
                                </select>
                                <small class="text-danger d-none" id="editStaffHelp">No skilled &amp; available staff for this service/time.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-body d-none" id="editModalComboSubView">
                        <div class="bk-edit-combo-search-wrap">
                            <i class="bx bx-search"></i>
                            <input type="text"
                                id="editModalComboSearch"
                                class="bk-edit-combo-search-input"
                                placeholder="Search services in this combo..."
                                autocomplete="off">
                        </div>
                        <button type="button" class="bk-edit-combo-skip-bar" id="editModalComboSkipBtn">
                            <span>Skip selection — Decide services in salon</span>
                            <span style="opacity:0.75; font-size:15px;">&rsaquo;</span>
                        </button>
                        <div class="small mb-2 d-none" id="editModalComboCapHint" style="color:#d4a373; font-weight:500;"></div>
                        <div id="editModalComboList" style="max-height: 320px; overflow-y: auto;"></div>
                    </div>
                    <div id="editModalError" class="bk-conflict-banner d-none" role="alert">
                        <div class="bk-conflict-banner-body">
                            <span class="bk-conflict-banner-dot"></span>
                            <span id="editModalErrorText"></span>
                        </div>
                        <button type="button" class="bk-conflict-banner-close" id="editModalErrorDismiss" aria-label="Dismiss">&times;</button>
                    </div>
                    <div class="modal-footer" id="editModalMainFooter">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="editSaveBtn">Save changes</button>
                    </div>
                    <div class="modal-footer d-none justify-content-between" id="editModalComboSubFooter">
                        <small class="text-muted" id="editModalComboHoldSummary">60m hold &middot; Decide in Salon</small>
                        <button type="button" class="btn btn-primary" id="editModalComboApplyBtn">Done &middot; Apply to Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        const bkAllActiveStaff = @json(($activeStaffs ?? collect())->values());

        function bkNormalizeBranchKey(raw) {
            const val = String(raw || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
            if (!val) return '';
            if (val === 'old_airport' || val.includes('airport')) return 'old_airport';
            if (val === 'wakrah' || val === 'al_wakrah' || val.includes('wakrah')) return 'wakrah';
            return val;
        }

        function bkStaffForBranch(branch) {
            const norm = bkNormalizeBranchKey(branch);
            return (bkAllActiveStaff || []).filter(s => {
                if (!s.branch || s.branch === 'remote' || s.is_remote) return false;
                if (!norm || s.branch === 'both') return true;
                return bkNormalizeBranchKey(s.branch) === norm;
            });
        }

        function bkShowToast(message) {
            const existing = document.getElementById('bkToastNotice');
            if (existing) existing.remove();
            const toast = document.createElement('div');
            toast.id = 'bkToastNotice';
            toast.className = 'bk-toast-notice';
            toast.innerHTML = `<span class="bk-conflict-banner-dot" style="margin-top:0;"></span><span style="flex:1;">${escHtml(message)}</span><button type="button" class="bk-conflict-banner-close" aria-label="Dismiss">&times;</button>`;
            toast.querySelector('button').addEventListener('click', () => toast.remove());
            document.body.appendChild(toast);
            setTimeout(() => { if (toast.isConnected) toast.remove(); }, 5000);
        }

        function showEditModalError(message) {
            const banner = document.getElementById('editModalError');
            const textEl = document.getElementById('editModalErrorText');
            if (!banner || !textEl) return;
            textEl.textContent = message || 'Unable to save changes to this booking.';
            banner.classList.remove('d-none');
        }

        function hideEditModalError() {
            const banner = document.getElementById('editModalError');
            const textEl = document.getElementById('editModalErrorText');
            if (textEl) textEl.textContent = '';
            if (banner) banner.classList.add('d-none');
        }

        document.getElementById('editModalErrorDismiss')?.addEventListener('click', hideEditModalError);

        function showCustomerProfile(phone) {
            if (!phone || !String(phone).trim()) return;
            fetch(`/appointments/customer-profile/${encodeURIComponent(phone)}`)
                .then(res => {
                    if (!res.ok) throw new Error();
                    return res.json();
                })
                .then(data => {
                    document.getElementById('profileName').innerText = data.customer_name;
                    document.getElementById('profilePhone').innerText = data.phone;
                    document.getElementById('profileVisits').innerText = data.total_visits;
                    document.getElementById('profileFirstVisit').innerText = data.first_visit;

                    if (data.total_visits <= 1) {
                        document.getElementById('lastVisitRow').style.display = 'none';
                    } else {
                        document.getElementById('lastVisitRow').style.display = 'block';
                        document.getElementById('profileLastVisit').innerText = data.last_visit;
                    }

                    document.getElementById('profileServices').innerText = data.services_taken;
                    document.getElementById('profileRevenue').innerText = data.lifetime_revenue;

                    const freebieBanner = document.getElementById('profileFreebieBanner');
                    const availFreebies = Array.isArray(data.available_freebie_credits) ? data.available_freebie_credits : [];
                    if (freebieBanner) {
                        if (availFreebies.length > 0) {
                            const fFirst = availFreebies[0];
                            const fDays = fFirst.days_left <= 0 ? 'Expires today' : `${fFirst.days_left}d left`;
                            const fUrl = `/appointments-calendar?open_booking=1&customer_id=${encodeURIComponent(data.customer_id || '')}&name=${encodeURIComponent(data.customer_name || '')}&phone=${encodeURIComponent(data.phone || '')}&redeem_freebie_credit_id=${encodeURIComponent(fFirst.credit_id)}`;
                            freebieBanner.classList.remove('d-none');
                            freebieBanner.innerHTML = `<div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                <span><strong>🎁 ${availFreebies.length} Freebie(s) Available</strong> — ${escHtml(fFirst.package_name)} · <span style="color:#6ee7b7; font-weight:700;">${escHtml(fDays)}</span></span>
                                <a href="${fUrl}" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size:11.5px;">Redeem Freebie</a>
                            </div>`;
                        } else {
                            freebieBanner.classList.add('d-none');
                            freebieBanner.innerHTML = '';
                        }
                    }

                    const fullLink = document.getElementById('profileFullLink');
                    if (data.customer_id) {
                        fullLink.href = `/customers/${data.customer_id}`;
                        fullLink.classList.remove('d-none');
                    } else {
                        fullLink.classList.add('d-none');
                    }

                    let tbody = document.getElementById('profileAppointments');
                    tbody.innerHTML = '';
                    data.appointments.forEach(a => {
                        const safeCombo = a.combo_name
                            ? `<span class="bk-combo-badge me-1">${String(a.combo_name).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</span>`
                            : '';
                        const svcDisplay = (a.decide_in_salon && !a.combo_name)
                            ? `<span class="bk-decide-badge">Decide in Salon</span>`
                            : escHtml(a.is_combo_tbd ? 'Choose in Salon' : a.service_name);
                        tbody.innerHTML += `
                            <tr>
                                <td>${a.appointment_datetime}</td>
                                <td>${safeCombo}${svcDisplay}</td>
                                <td>${a.price}</td>
                                <td>${a.branch}</td>
                                <td>${a.agent}</td>
                            </tr>
                        `;
                    });

                    new bootstrap.Modal(document.getElementById('customerProfileModal')).show();
                })
                .catch(() => bkShowToast('No client profile records found.'));
        }

        function bkOnPeriodChange(sel) {
            const customDates = document.getElementById('bkCustomDates');
            const rangeLabel = document.getElementById('bkRangeLabel');
            if (sel.value === 'custom') {
                customDates.style.display = 'flex';
                rangeLabel.style.display = 'none';
            } else {
                document.getElementById('bkFilterForm').submit();
            }
        }

        /* ---------------- SEARCH (customer name or phone) ---------------- */
        let bkSearchTimer = null;
        const bkSearchInput = document.getElementById('bkSearch');
        bkSearchInput.addEventListener('input', function() {
            clearTimeout(bkSearchTimer);
            bkSearchTimer = setTimeout(() => document.getElementById('bkFilterForm').submit(), 500);
        });
        bkSearchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(bkSearchTimer);
                document.getElementById('bkFilterForm').submit();
            }
        });

        /* ---------------- EDIT / RESCHEDULE BOOKING MODAL ---------------- */
        let editCurrentApptId = null;
        let editPendingStaffId = null;
        let editComboDrafts = [];
        let activeEditComboSubDraft = null;
        let editDecideInSalon = false;
        let editHoldDuration = 60;

        function closeEditModalComboSubView() {
            activeEditComboSubDraft = null;
            document.getElementById('editModalMainHeader').classList.remove('d-none');
            document.getElementById('editModalMainBody').classList.remove('d-none');
            document.getElementById('editModalMainFooter').classList.remove('d-none');
            document.getElementById('editModalComboSubHeader').classList.add('d-none');
            document.getElementById('editModalComboSubView').classList.add('d-none');
            document.getElementById('editModalComboSubFooter').classList.add('d-none');
        }

        function openEditModalComboSubView(idx) {
            const base = editComboDrafts[idx];
            if (!base) return;
            hideEditModalError();

            activeEditComboSubDraft = {
                idx,
                pkg: base.pkg,
                todayIds: [...base.todayIds],
                pendingIds: [...base.pendingIds]
            };

            const searchEl = document.getElementById('editModalComboSearch');
            if (searchEl) searchEl.value = '';

            document.getElementById('editModalMainHeader').classList.add('d-none');
            document.getElementById('editModalMainBody').classList.add('d-none');
            document.getElementById('editModalMainFooter').classList.add('d-none');
            document.getElementById('editModalComboSubHeader').classList.remove('d-none');
            document.getElementById('editModalComboSubView').classList.remove('d-none');
            document.getElementById('editModalComboSubFooter').classList.remove('d-none');

            renderEditModalComboSubView();
        }

        function renderEditModalComboSubView() {
            if (!activeEditComboSubDraft) return;
            const cfg = activeEditComboSubDraft;
            const pkg = cfg.pkg;
            const pool = Array.isArray(pkg.pool_services) ? pkg.pool_services : [];
            const qtyIncluded = Number(pkg.quantity_included) || 0;
            const usedEarlier = Number(pkg.redeemed_elsewhere_count) || 0;
            const maxVisit = Math.max(0, qtyIncluded - usedEarlier);
            const visitSelected = cfg.todayIds.length + cfg.pendingIds.length;
            const totalSelected = visitSelected + usedEarlier;
            const capReached = visitSelected >= maxVisit && maxVisit > 0;

            document.getElementById('editModalComboTitle').textContent = pkg.combo_name || 'Combo Package';
            const counterEl = document.getElementById('editModalComboCounter');
            counterEl.textContent = `${totalSelected} / ${qtyIncluded} Selected`;
            counterEl.classList.toggle('is-full', capReached);

            const capHintEl = document.getElementById('editModalComboCapHint');
            if (capReached && pool.length > qtyIncluded) {
                capHintEl.textContent = `Max ${qtyIncluded} of ${pool.length} services selected — tap an active pill to swap`;
                capHintEl.classList.remove('d-none');
            } else {
                capHintEl.textContent = '';
                capHintEl.classList.add('d-none');
            }

            const todayDur = pool
                .filter(s => cfg.todayIds.includes(Number(s.id)))
                .reduce((sum, s) => sum + (Number(s.duration) || 30), 0);
            const holdSummaryEl = document.getElementById('editModalComboHoldSummary');
            if (cfg.todayIds.length > 0) {
                holdSummaryEl.textContent = `${todayDur}m salon time (${cfg.todayIds.length} Today${cfg.pendingIds.length ? ` · ${cfg.pendingIds.length} Pending` : ''})`;
            } else if (cfg.pendingIds.length > 0) {
                holdSummaryEl.textContent = `60m hold · ${cfg.pendingIds.length} Pending`;
            } else {
                holdSummaryEl.textContent = '60m hold · Decide in Salon';
            }

            const query = (document.getElementById('editModalComboSearch').value || '').trim().toLowerCase();
            const filtered = query
                ? pool.filter(svc => String(svc.name || '').toLowerCase().includes(query))
                : pool;

            const listEl = document.getElementById('editModalComboList');
            if (!filtered.length) {
                listEl.innerHTML = `<div class="text-muted small py-4 text-center">No matching services in this combo.</div>`;
                return;
            }

            listEl.innerHTML = filtered.map(svc => {
                const sid = Number(svc.id);
                const dur = Number(svc.duration) || 30;
                if (svc.state === 'redeemed_elsewhere') {
                    return `<div class="bk-edit-combo-svc-row is-dimmed">
                        <div>
                            <div class="fw-semibold" style="color:#f8efe6; font-size:13px;">${escHtml(svc.name)}</div>
                            <div class="text-muted" style="font-size:11.5px;">${dur} min</div>
                        </div>
                        <span class="bk-combo-mode-pill">Redeemed</span>
                    </div>`;
                }

                const isToday = cfg.todayIds.includes(sid);
                const isPending = !isToday && cfg.pendingIds.includes(sid);
                const disableNewPick = !isToday && !isPending && (capReached || maxVisit === 0);

                return `<div class="bk-edit-combo-svc-row ${disableNewPick ? 'is-dimmed' : ''}">
                    <div>
                        <div class="fw-semibold" style="color:#f8efe6; font-size:13px;">${escHtml(svc.name)}</div>
                        <div class="text-muted" style="font-size:11.5px;">${dur} min</div>
                    </div>
                    <div class="bk-combo-seg">
                        <button type="button" class="bk-combo-mode-pill bk-edit-sub-today ${isToday ? 'is-today' : ''}"
                            data-svc="${sid}" ${disableNewPick ? 'disabled' : ''}>Today</button>
                        <button type="button" class="bk-combo-mode-pill bk-edit-sub-pending ${isPending ? 'is-pending' : ''}"
                            data-svc="${sid}" ${disableNewPick ? 'disabled' : ''}>Pending</button>
                    </div>
                </div>`;
            }).join('');

            listEl.querySelectorAll('.bk-edit-sub-today').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!activeEditComboSubDraft) return;
                    const sid = Number(btn.dataset.svc);
                    if (cfg.todayIds.includes(sid)) {
                        cfg.todayIds = cfg.todayIds.filter(id => id !== sid);
                    } else {
                        if (cfg.pendingIds.includes(sid)) {
                            cfg.pendingIds = cfg.pendingIds.filter(id => id !== sid);
                        }
                        if (cfg.todayIds.length + cfg.pendingIds.length < maxVisit) {
                            cfg.todayIds.push(sid);
                        }
                    }
                    renderEditModalComboSubView();
                });
            });

            listEl.querySelectorAll('.bk-edit-sub-pending').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!activeEditComboSubDraft) return;
                    const sid = Number(btn.dataset.svc);
                    if (cfg.pendingIds.includes(sid)) {
                        cfg.pendingIds = cfg.pendingIds.filter(id => id !== sid);
                    } else {
                        if (cfg.todayIds.includes(sid)) {
                            cfg.todayIds = cfg.todayIds.filter(id => id !== sid);
                        }
                        if (cfg.todayIds.length + cfg.pendingIds.length < maxVisit) {
                            cfg.pendingIds.push(sid);
                        }
                    }
                    renderEditModalComboSubView();
                });
            });
        }

        function commitEditModalComboSubView() {
            if (!activeEditComboSubDraft) return;
            const target = editComboDrafts[activeEditComboSubDraft.idx];
            if (target) {
                target.todayIds = [...activeEditComboSubDraft.todayIds];
                target.pendingIds = [...activeEditComboSubDraft.pendingIds];
            }
            closeEditModalComboSubView();
            renderEditModalCombos();
            updateEditServiceTotals(true);
            loadEditAvailableStaff();
        }

        (function initEditModalComboSubEvents() {
            const backBtn = document.getElementById('editModalComboBackBtn');
            const searchEl = document.getElementById('editModalComboSearch');
            const skipBtn = document.getElementById('editModalComboSkipBtn');
            const applyBtn = document.getElementById('editModalComboApplyBtn');

            if (backBtn) backBtn.addEventListener('click', closeEditModalComboSubView);
            if (searchEl) searchEl.addEventListener('input', renderEditModalComboSubView);
            if (skipBtn) {
                skipBtn.addEventListener('click', () => {
                    if (!activeEditComboSubDraft) return;
                    activeEditComboSubDraft.todayIds = [];
                    activeEditComboSubDraft.pendingIds = [];
                    commitEditModalComboSubView();
                });
            }
            if (applyBtn) applyBtn.addEventListener('click', commitEditModalComboSubView);
        })();

        function escHtml(s) {
            return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function populateEditStaffOptions(availableIdsSet, branch, preferredId) {
            const staffSelect = document.getElementById('editStaffSelect');
            const branchStaff = bkStaffForBranch(branch);
            staffSelect.innerHTML = '<option value="">-- Select Staff (Unassigned) --</option>';

            const availableList = availableIdsSet
                ? branchStaff.filter(s => availableIdsSet.has(String(s.id)))
                : branchStaff;
            const unavailableList = availableIdsSet
                ? branchStaff.filter(s => !availableIdsSet.has(String(s.id)))
                : [];

            availableList.forEach(s => {
                staffSelect.insertAdjacentHTML('beforeend', `<option value="${s.id}">${escHtml(s.name)}</option>`);
            });
            unavailableList.forEach(s => {
                staffSelect.insertAdjacentHTML('beforeend', `<option value="${s.id}">${escHtml(s.name)} · Unavailable</option>`);
            });

            if (preferredId && [...staffSelect.options].some(o => String(o.value) === String(preferredId))) {
                staffSelect.value = String(preferredId);
            }
        }

        function renderEditModalCombos() {
            const wrap = document.getElementById('editComboPackagesWrap');
            const container = document.getElementById('editComboPackagesContainer');
            const hidden = document.getElementById('editComboHiddenInputs');
            const standaloneLabel = document.getElementById('editStandaloneServicesLabel');

            if (!editComboDrafts.length) {
                wrap.classList.add('d-none');
                container.innerHTML = '';
                hidden.innerHTML = '';
                standaloneLabel.textContent = 'Services';
                return;
            }

            wrap.classList.remove('d-none');
            standaloneLabel.textContent = 'Additional Standalone Services (Optional)';

            container.innerHTML = editComboDrafts.map((draft, idx) => {
                const pkg = draft.pkg;
                const qtyIncluded = Number(pkg.quantity_included) || 0;
                const usedEarlier = Number(pkg.redeemed_elsewhere_count) || 0;
                const pool = Array.isArray(pkg.pool_services) ? pkg.pool_services : [];

                const pickedChips = [];
                pool.forEach(svc => {
                    const sid = Number(svc.id);
                    if (draft.todayIds.includes(sid)) {
                        pickedChips.push(`<span>${escHtml(svc.name)} <span style="color:#6ee7b7; font-size:11px; font-weight:600;">(Today)</span></span>`);
                    } else if (draft.pendingIds.includes(sid)) {
                        pickedChips.push(`<span>${escHtml(svc.name)} <span style="color:#d4a373; font-size:11px; font-weight:600;">(Pending)</span></span>`);
                    }
                });
                if (usedEarlier > 0) {
                    pickedChips.push(`<span class="text-muted">${usedEarlier} redeemed</span>`);
                }

                const totalSelected = draft.todayIds.length + draft.pendingIds.length + usedEarlier;
                const secondRowHtml = totalSelected === 0
                    ? `<div class="bk-edit-combo-trigger">
                            <span class="small" style="color:#d4a373; font-weight:500;">0 / ${qtyIncluded} Selected &middot; Decide in Salon &mdash; Click to choose services</span>
                            <span style="color:#e5b8a8; font-size:16px; line-height:1;">&rsaquo;</span>
                       </div>`
                    : `<div class="bk-edit-combo-trigger">
                            <div class="bk-edit-combo-picked-list">
                                ${pickedChips.join('<span style="color:rgba(248,239,230,0.28);">&middot;</span>')}
                            </div>
                            <span class="small fw-semibold" style="color:#e5b8a8; white-space:nowrap;">Edit services (${totalSelected}/${qtyIncluded}) &rsaquo;</span>
                       </div>`;

                return `<div class="bk-edit-combo-card mb-2" data-open-edit-combo="${idx}" role="button" tabindex="0">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <span class="bk-combo-badge">COMBO</span>
                            <strong class="text-truncate" style="color:#f8efe6;">${escHtml(pkg.combo_name)}</strong>
                        </div>
                        <strong style="color:#e5b8a8; white-space:nowrap;">${Number(pkg.price_paid).toFixed(2)} QAR</strong>
                    </div>
                    ${secondRowHtml}
                </div>`;
            }).join('');

            hidden.innerHTML = editComboDrafts.map((draft, idx) => `
                <input type="hidden" name="combo_packages[${idx}][client_package_id]" value="${draft.pkg.client_package_id}">
                ${draft.todayIds.map(sid => `<input type="hidden" name="combo_packages[${idx}][today_service_ids][]" value="${sid}">`).join('')}
                ${draft.pendingIds.map(sid => `<input type="hidden" name="combo_packages[${idx}][pending_service_ids][]" value="${sid}">`).join('')}
            `).join('');

            container.querySelectorAll('[data-open-edit-combo]').forEach(card => {
                card.addEventListener('click', () => openEditModalComboSubView(Number(card.dataset.openEditCombo)));
                card.addEventListener('keydown', e => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openEditModalComboSubView(Number(card.dataset.openEditCombo));
                    }
                });
            });
        }

        function openBookingEditModal(btn) {
            const appt = JSON.parse(btn.dataset.appt);
            editCurrentApptId = appt.id;
            editPendingStaffId = appt.staff_id;
            editComboDrafts = [];
            closeEditModalComboSubView();

            document.getElementById('bookingEditForm').action = `/appointments/${appt.id}`;
            document.getElementById('editCustomerName').value = appt.customer_name || '';
            document.getElementById('editPhone').value = appt.phone || '';
            document.getElementById('editCustomerNameLabel').textContent =
                '— ' + (appt.customer_name || 'Walk-in') + (appt.phone ? ' · ' + appt.phone : '');
            document.getElementById('editBranch').value = appt.branch;
            document.getElementById('editStatus').value = appt.status;
            document.getElementById('editDate').value = appt.date;
            document.getElementById('editTime').value = appt.time;
            document.getElementById('editPriceHidden').value = appt.price || 0;
            hideEditModalError();
            document.getElementById('editStaffHelp').classList.add('d-none');

            document.querySelectorAll('.edit-service-checkbox').forEach(cb => {
                cb.checked = appt.service_names.includes(cb.value);
            });
            editDecideInSalon = Boolean(appt.decide_in_salon) && !appt.service_names.length && !appt.combo_name;
            editHoldDuration = Number(appt.hold_duration) || 60;
            renderEditModalCombos();
            updateEditServiceTotals(false);
            document.getElementById('editServiceSearch').value = '';
            document.querySelectorAll('.edit-service-row').forEach(row => row.style.display = '');
            document.getElementById('editServiceNoResults').classList.add('d-none');

            populateEditStaffOptions(null, appt.branch, appt.staff_id);

            let editRedeemableItems = [];
            const resetRedeemWrap = document.getElementById('editRedeemPackagesWrap');
            const resetRedeemContainer = document.getElementById('editRedeemPackagesContainer');
            if (resetRedeemWrap) resetRedeemWrap.classList.add('d-none');
            if (resetRedeemContainer) resetRedeemContainer.innerHTML = '';

            fetch(`/appointments/${appt.id}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(detail => {
                    if (editCurrentApptId !== appt.id) return;
                    const pkgs = Array.isArray(detail.combo_packages) ? detail.combo_packages : [];
                    editComboDrafts = pkgs.map(pkg => {
                        const todayIds = [];
                        const pendingIds = [];
                        (pkg.pool_services || []).forEach(svc => {
                            if (svc.state === 'today') todayIds.push(Number(svc.id));
                            else if (svc.state === 'pending') pendingIds.push(Number(svc.id));
                        });
                        return { pkg, todayIds, pendingIds };
                    });

                    editRedeemableItems = Array.isArray(detail.redeemable_package_services) ? detail.redeemable_package_services : [];
                    if (editRedeemableItems.length && resetRedeemWrap && resetRedeemContainer) {
                        resetRedeemWrap.classList.remove('d-none');
                        resetRedeemContainer.innerHTML = editRedeemableItems.map(item => `
                            <label class="d-flex align-items-center justify-content-between gap-2 py-1 px-1 mb-0" style="cursor:pointer; font-size:13px;">
                                <span class="d-inline-flex align-items-center gap-2">
                                    <input type="checkbox" class="form-check-input m-0 edit-redeem-checkbox"
                                        name="redeem_service_ids[]"
                                        value="${item.id}"
                                        data-package-id="${item.client_package_id}"
                                        data-service-name="${item.service_name}"
                                        data-duration="${item.duration || 30}"
                                        data-remaining="${item.remaining_count || 1}">
                                    <span>${item.service_name} <span class="text-muted">(${item.duration || 30} min &middot; ${item.combo_name}, ${item.remaining_count} left)</span></span>
                                </span>
                                <span style="color:#a7c7a2; font-weight:600; font-size:12px;">0.00 QAR</span>
                            </label>
                        `).join('');

                        const syncEditRedeemCaps = () => {
                            const cbs = [...resetRedeemContainer.querySelectorAll('.edit-redeem-checkbox')];
                            const counts = {};
                            cbs.forEach(cb => {
                                if (cb.checked) counts[cb.dataset.packageId] = (counts[cb.dataset.packageId] || 0) + 1;
                            });
                            cbs.forEach(cb => {
                                const cap = parseInt(cb.dataset.remaining || '1', 10) || 1;
                                cb.disabled = !cb.checked && (counts[cb.dataset.packageId] || 0) >= cap;
                            });
                        };

                        resetRedeemContainer.querySelectorAll('.edit-redeem-checkbox').forEach(cb => {
                            cb.addEventListener('change', () => {
                                syncEditRedeemCaps();
                                hideEditModalError();
                                updateEditServiceTotals(true);
                                loadEditAvailableStaff();
                            });
                        });
                    }

                    if (editComboDrafts.length) {
                        editDecideInSalon = false;
                    }
                    renderEditModalCombos();
                    updateEditServiceTotals(true);
                    loadEditAvailableStaff();
                })
                .catch(() => loadEditAvailableStaff());

            new bootstrap.Modal(document.getElementById('bookingEditModal')).show();
        }
        window.openBookingEditModal = openBookingEditModal;

        function updateEditServiceTotals(recalcPrice = true) {
            const checked = [...document.querySelectorAll('.edit-service-checkbox:checked')];
            const checkedRedeems = [...document.querySelectorAll('.edit-redeem-checkbox:checked')];
            if (checked.length > 0 || editComboDrafts.length > 0 || checkedRedeems.length > 0) {
                editDecideInSalon = false;
            }

            const decideCard = document.getElementById('editDecideInSalonCard');
            const decidePill = document.getElementById('editDecideSalonPill');
            const decideHidden = document.getElementById('editDecideInSalonHidden');
            const decideHoldSpan = document.getElementById('editDecideHoldMin');
            if (decideHoldSpan) decideHoldSpan.textContent = String(editHoldDuration || 60);
            if (decideCard) decideCard.classList.toggle('d-none', !(editDecideInSalon && !checked.length && !editComboDrafts.length && !checkedRedeems.length));
            if (decidePill) decidePill.classList.toggle('d-none', checked.length > 0 || editComboDrafts.length > 0 || checkedRedeems.length > 0 || editDecideInSalon);
            if (decideHidden) decideHidden.value = (editDecideInSalon && !checked.length && !editComboDrafts.length && !checkedRedeems.length) ? '1' : '0';

            const remainingRedeems = checkedRedeems.map(cb => String(cb.dataset.serviceName || '').trim().toLowerCase());
            let standalonePrice = 0;
            checked.forEach(cb => {
                const nameLower = String(cb.value || '').trim().toLowerCase();
                const matchIdx = remainingRedeems.indexOf(nameLower);
                if (matchIdx >= 0) {
                    remainingRedeems.splice(matchIdx, 1);
                } else {
                    standalonePrice += (parseFloat(cb.dataset.price) || 0);
                }
            });
            const standaloneMin = checked.reduce((sum, cb) => sum + (parseInt(cb.dataset.duration) || 0), 0);
            const extraRedeemMin = checkedRedeems.reduce((sum, cb) => {
                const isAlreadyChecked = checked.some(sc => String(sc.value || '').trim().toLowerCase() === String(cb.dataset.serviceName || '').trim().toLowerCase());
                return sum + (isAlreadyChecked ? 0 : (parseInt(cb.dataset.duration) || 0));
            }, 0);
            const comboLockedPrice = editComboDrafts.reduce((sum, d) => sum + (Number(d.pkg.price_paid) || 0), 0);
            const comboMin = editComboDrafts.reduce((sum, d) => {
                return sum + (d.pkg.pool_services || [])
                    .filter(s => d.todayIds.includes(Number(s.id)))
                    .reduce((acc, s) => acc + (Number(s.duration) || 0), 0);
            }, 0);
            const totalPrice = standalonePrice + comboLockedPrice;
            const totalMin = (standaloneMin + extraRedeemMin + comboMin) || (editComboDrafts.length ? 60 : (editDecideInSalon ? (editHoldDuration || 60) : 0));

            if (editComboDrafts.length) {
                document.getElementById('editServiceTotals').textContent =
                    `Combo locked + ${checked.length} extra service${checked.length === 1 ? '' : 's'} · ${totalMin} min · ${totalPrice.toFixed(2)} QAR`;
            } else if (editDecideInSalon && !checked.length) {
                document.getElementById('editServiceTotals').textContent =
                    `Decide in Salon · 1h (${totalMin} min hold) · 0.00 QAR`;
            } else {
                document.getElementById('editServiceTotals').textContent =
                    `${checked.length} service${checked.length === 1 ? '' : 's'} selected · ${totalMin} min · ${totalPrice.toFixed(2)} QAR`;
            }
            if (recalcPrice) {
                document.getElementById('editPriceHidden').value = totalPrice.toFixed(2);
            }
        }

        document.getElementById('editDecideSalonPill')?.addEventListener('click', () => {
            if (document.querySelectorAll('.edit-service-checkbox:checked').length || editComboDrafts.length) return;
            editDecideInSalon = true;
            hideEditModalError();
            updateEditServiceTotals(true);
            loadEditAvailableStaff();
        });

        document.getElementById('editDecideInSalonRemove')?.addEventListener('click', () => {
            editDecideInSalon = false;
            updateEditServiceTotals(true);
            loadEditAvailableStaff();
        });

        document.querySelectorAll('.edit-service-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                hideEditModalError();
                updateEditServiceTotals(true);
                loadEditAvailableStaff();
            });
        });

        document.getElementById('editServiceSearch').addEventListener('input', function() {
            const needle = this.value.trim().toLowerCase();
            let anyVisible = false;

            document.querySelectorAll('.edit-service-row').forEach(row => {
                const name = row.querySelector('.form-check-label span').textContent.toLowerCase();
                const matches = !needle || name.includes(needle);
                row.style.display = matches ? '' : 'none';
                if (matches) anyVisible = true;
            });

            document.getElementById('editServiceNoResults').classList.toggle('d-none', anyVisible);
        });

        function loadEditAvailableStaff() {
            const staffSelect = document.getElementById('editStaffSelect');
            const staffHelp = document.getElementById('editStaffHelp');
            const standaloneServices = [...document.querySelectorAll('.edit-service-checkbox:checked')].map(cb => cb.value);
            const comboTodayServices = [];
            editComboDrafts.forEach(d => {
                (d.pkg.pool_services || []).forEach(svc => {
                    if (d.todayIds.includes(Number(svc.id))) {
                        comboTodayServices.push(svc.name);
                    }
                });
            });
            const services = [...new Set([...standaloneServices, ...comboTodayServices])];
            const date = document.getElementById('editDate').value;
            const time = document.getElementById('editTime').value;
            const branch = document.getElementById('editBranch').value;
            const preferredId = editPendingStaffId ?? staffSelect.value;

            staffHelp.classList.add('d-none');
            if ((!services.length && !editComboDrafts.length && !editDecideInSalon) || !date || !time || !branch) {
                populateEditStaffOptions(null, branch, preferredId);
                return;
            }

            const params = new URLSearchParams();
            if (services.length) {
                services.forEach(s => params.append('services[]', s));
            } else {
                params.append('decide_in_salon', '1');
            }
            params.append('appointment_datetime', `${date}T${time}`);
            params.append('branch', branch);
            if (editCurrentApptId) params.append('exclude_appointment_id', editCurrentApptId);

            fetch(`{{ route('appointments.availableStaff') }}?${params.toString()}`)
                .then(r => r.json())
                .then(list => {
                    const currentVal = editPendingStaffId ?? staffSelect.value;
                    const availableIds = new Set((Array.isArray(list) ? list : []).map(s => String(s.id)));
                    populateEditStaffOptions(availableIds, branch, currentVal);
                    if (!availableIds.size) {
                        staffHelp.classList.remove('d-none');
                    }
                    editPendingStaffId = null;
                })
                .catch(() => {
                    populateEditStaffOptions(null, branch, preferredId);
                    staffHelp.classList.remove('d-none');
                });
        }

        document.getElementById('editBranch').addEventListener('change', () => {
            hideEditModalError();
            loadEditAvailableStaff();
        });
        document.getElementById('editDate').addEventListener('change', () => {
            hideEditModalError();
            loadEditAvailableStaff();
        });
        document.getElementById('editTime').addEventListener('change', () => {
            hideEditModalError();
            loadEditAvailableStaff();
        });
        document.getElementById('editStaffSelect').addEventListener('change', hideEditModalError);
        document.getElementById('editStatus').addEventListener('change', hideEditModalError);

        document.getElementById('bookingEditForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = this;
            const saveBtn = document.getElementById('editSaveBtn');
            const date = document.getElementById('editDate').value;
            const time = document.getElementById('editTime').value;
            document.getElementById('editDatetimeHidden').value = (date && time) ? `${date}T${time}` : '';

            hideEditModalError();

            const checkedCount = document.querySelectorAll('.edit-service-checkbox:checked').length;
            const requestedStatus = document.getElementById('editStatus').value;

            if (!checkedCount && !editComboDrafts.length && !editDecideInSalon && !['cancelled', 'no_show'].includes(requestedStatus)) {
                showEditModalError('Please select at least one service, combo package, or choose Decide in Salon.');
                return;
            }

            if (requestedStatus === 'completed' && editDecideInSalon && !checkedCount && !editComboDrafts.length) {
                showEditModalError('Please add the service(s) or combo performed before completing checkout.');
                return;
            }

            const origLabel = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving…';

            try {
                const resp = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || form.querySelector('input[name="_token"]')?.value || '',
                    },
                    body: new FormData(form),
                });

                const payload = await resp.json().catch(() => ({}));
                if (!resp.ok || payload.success === false) {
                    const firstErr = payload?.errors ? Object.values(payload.errors).flat()[0] : null;
                    showEditModalError(payload?.message || firstErr || 'Unable to save booking changes.');
                    return;
                }

                if (payload.redirect) {
                    window.location.href = payload.redirect;
                } else {
                    window.location.reload();
                }
            } catch (err) {
                showEditModalError('Network error while saving booking. Please try again.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = origLabel;
            }
        });

        // Bookings trend
        new ApexCharts(document.querySelector('#bkTrendChart'), {
            chart: { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'Bookings', data: @json($dailyTrend->values()) }],
            xaxis: { categories: @json($dailyTrend->keys()), labels: { rotate: -45, style: { fontSize: '11px' } } },
            yaxis: { labels: { formatter: (v) => v.toFixed(0) } },
            colors: ['#d98f83'],
            fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .05 } },
            stroke: { curve: 'smooth', width: 2.5 },
            dataLabels: { enabled: false },
            tooltip: { y: { formatter: (v) => v.toFixed(0) + ' bookings' } },
            grid: { borderColor: 'rgba(217, 143, 131,0.16)' },
        }).render();

        // Status breakdown donut
        new ApexCharts(document.querySelector('#bkStatusChart'), {
            chart: { type: 'donut', height: 320, fontFamily: 'inherit' },
            series: @json($statusCounts->values()),
            labels: @json($statusLabels),
            colors: ['#c9a66b', '#b98ea3', '#c97b4a', '#8ea88a', '#8a7d76', '#a8524a'],
            dataLabels: { enabled: true, formatter: (val) => val.toFixed(1) + '%' },
            legend: { position: 'bottom' },
            tooltip: { y: { formatter: (v) => v + ' bookings' } },
            plotOptions: {
                pie: {
                    donut: {
                        labels: {
                            show: true,
                            total: { show: true, label: 'Total', formatter: () => '{{ $totalBookings }}' }
                        }
                    }
                }
            },
        }).render();

        // Branch comparison
        new ApexCharts(document.querySelector('#bkBranchChart'), {
            chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [
                { name: 'Bookings', data: @json($branchStats->pluck('count')) },
                { name: 'Revenue (QAR)', data: @json($branchStats->pluck('revenue')) },
            ],
            xaxis: { categories: @json($branchStats->pluck('label')) },
            yaxis: [
                { min: 0, title: { text: 'Bookings' }, labels: { formatter: (v) => v.toFixed(0) } },
                { min: 0, opposite: true, title: { text: 'Revenue (QAR)' }, labels: { formatter: (v) => v.toFixed(0) } },
            ],
            colors: ['#d98f83', '#8aa6ab'],
            plotOptions: { bar: { columnWidth: '45%', borderRadius: 6 } },
            dataLabels: { enabled: false },
            legend: { position: 'top', horizontalAlign: 'left' },
            grid: { borderColor: 'rgba(217, 143, 131,0.16)' },
        }).render();
    </script>
@endsection
