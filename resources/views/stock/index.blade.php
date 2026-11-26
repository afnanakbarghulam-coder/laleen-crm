@extends('layouts.app')
@section('title', 'Stock')

@php
    $branchLabel = \App\Models\StockWeek::branchLabel($branch);
    $statusDisplay = match ($stockWeek->status) {
        \App\Models\StockWeek::STATUS_SUBMITTED => ['label' => 'Pending Approval', 'tone' => 'amber'],
        \App\Models\StockWeek::STATUS_REVISION_REQUESTED => ['label' => 'Needs Revision', 'tone' => 'danger'],
        \App\Models\StockWeek::STATUS_APPROVED => ['label' => 'Approved', 'tone' => 'emerald'],
        default => ['label' => 'Open', 'tone' => 'champagne'],
    };
    $tonePalette = [
        'emerald' => ['bg' => 'rgba(52, 211, 153, 0.12)', 'border' => 'rgba(52, 211, 153, 0.30)', 'color' => '#6ee7b7'],
        'amber' => ['bg' => 'rgba(245, 158, 11, 0.12)', 'border' => 'rgba(245, 158, 11, 0.30)', 'color' => '#fbbf24'],
        'danger' => ['bg' => 'rgba(251, 113, 133, 0.12)', 'border' => 'rgba(251, 113, 133, 0.32)', 'color' => '#fda4af'],
        'champagne' => ['bg' => 'rgba(212, 163, 115, 0.12)', 'border' => 'rgba(212, 163, 115, 0.28)', 'color' => '#f3d9a4'],
    ];
    $activeTone = $tonePalette[$statusDisplay['tone']] ?? $tonePalette['champagne'];
    $totalOrderCount = collect($orderListByBranch)->sum(fn ($b) => count($b['rows']));
    $thisWeekStartStr = \App\Models\StockWeek::weekBoundsFor()['start_str'];
    $compactRangeLabel = $stockWeek->week_start_date->format('j M') . ' – ' . $stockWeek->week_end_date->format('j M');
    $otherBranch = $branch === 'old_airport' ? 'al_wakrah' : 'old_airport';
    $activeCatalogItems = $catalogItems->where('is_active', true)->values();
    $hasEntries = $entries->isNotEmpty();
    $isFirstWeekEditable = $canEditOpening && !$hasPriorApprovedWeek;
    $allOpeningsZero = $hasEntries && $entries->every(fn ($e) => abs((float) $e->opening_qty) < 0.005);
    $isOpenOrRevision = in_array($stockWeek->status, [\App\Models\StockWeek::STATUS_OPEN, \App\Models\StockWeek::STATUS_REVISION_REQUESTED], true);
@endphp

@section('content')
<style>
    .stk-wrap {
        max-width: 1080px;
        margin: 0 auto;
    }

    .stk-num {
        font-variant-numeric: tabular-nums;
    }

    /* Header */
    .stk-header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }

    .stk-header h4 {
        margin: 0 0 3px;
        font-weight: 700;
        color: #f8efe6;
        letter-spacing: -0.01em;
    }

    .stk-header p {
        margin: 0;
        font-size: 13.5px;
        color: #a8938b;
    }

    /* Minimal Pill Tabs */
    .stk-tabs {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px;
        border-radius: 999px;
        background: rgba(25, 17, 20, 0.9);
        border: 1px solid rgba(229, 184, 168, 0.14);
        margin-bottom: 20px;
    }

    .stk-tab {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 7px 18px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
        color: #b8a29a;
        text-decoration: none;
        transition: color 0.15s ease, background 0.15s ease;
    }

    .stk-tab:hover {
        color: #f8efe6;
    }

    .stk-tab.active {
        background: linear-gradient(135deg, #d98f83, #c48479);
        color: #1b1315;
        font-weight: 700;
    }

    .stk-tab-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: rgba(251, 113, 133, 0.22);
        color: #fda4af;
    }

    .stk-tab.active .stk-tab-badge {
        background: rgba(27, 19, 21, 0.2);
        color: #1b1315;
    }

    /* Main Card Surface (Anti-Nesting: single 1px border, internal hairline dividers only) */
    .stk-card {
        background: linear-gradient(180deg, rgba(34, 22, 27, 0.94) 0%, rgba(21, 14, 18, 0.98) 100%);
        border: 1px solid rgba(229, 184, 168, 0.14);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 16px;
    }

    .stk-primary-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 16px 22px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .stk-filter-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 22px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    /* Branch & Week Controls */
    .stk-branch-seg {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        padding: 3px;
        border-radius: 10px;
        background: rgba(15, 10, 12, 0.72);
    }

    .stk-branch-opt {
        padding: 5px 12px;
        border-radius: 7px;
        font-size: 12.5px;
        font-weight: 600;
        text-decoration: none;
        color: #a8938b;
        transition: color 0.15s ease, background 0.15s ease;
    }

    .stk-branch-opt:hover {
        color: #f8efe6;
    }

    .stk-branch-opt.active {
        background: rgba(217, 143, 131, 0.18);
        color: #f8efe6;
    }

    .stk-week-ctrl {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 6px;
        border-radius: 10px;
        background: rgba(15, 10, 12, 0.72);
        font-size: 13px;
        color: #f8efe6;
    }

    .stk-week-ctrl a {
        color: #b8a29a;
        text-decoration: none;
        padding: 3px 6px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
    }

    .stk-week-ctrl a:hover {
        background: rgba(217, 143, 131, 0.14);
        color: #f8efe6;
    }

    .stk-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
        line-height: 1.3;
    }

    .stk-save-status {
        font-size: 12px;
        font-weight: 600;
        color: #8f7a73;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
        transition: color 0.15s ease;
    }

    .stk-save-status.is-saving {
        color: #f3d9a4;
    }

    .stk-save-status.is-saved {
        color: #6ee7b7;
    }

    .stk-save-status.is-error {
        color: #fda4af;
    }

    /* Lightweight Filter Pills */
    .stk-type-pill {
        border: none;
        background: transparent;
        color: #9e8780;
        border-radius: 999px;
        padding: 4px 12px;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
        transition: color 0.15s ease, background 0.15s ease;
    }

    .stk-type-pill:hover {
        color: #f8efe6;
    }

    .stk-type-pill.active {
        background: rgba(217, 143, 131, 0.16);
        color: #f8efe6;
    }

    /* Clean Inputs */
    .stk-input,
    .stk-select {
        background-color: #140e10 !important;
        border: 1px solid rgba(229, 184, 168, 0.18) !important;
        color: #f8efe6 !important;
        border-radius: 9px !important;
        font-size: 13px;
        color-scheme: dark;
    }

    .stk-select {
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        appearance: none !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23e5b8a8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.7rem center !important;
        background-size: 11px 9px !important;
        padding-right: 2rem !important;
    }

    .stk-select option {
        background-color: #140e10;
        color: #f8efe6;
    }

    .stk-input:focus,
    .stk-select:focus {
        border-color: #d98f83 !important;
        box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.2) !important;
    }

    .stk-qty-input {
        width: 78px;
        text-align: right;
        padding: 6px 9px !important;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .stk-qty-remaining {
        width: 84px;
        background-color: #110b0d !important;
        border: 1px solid rgba(229, 184, 168, 0.34) !important;
        text-align: right;
        padding: 6px 9px !important;
        font-weight: 700;
        font-size: 13.5px;
        color: #f8efe6 !important;
        font-variant-numeric: tabular-nums;
    }

    .stk-qty-remaining:focus {
        border-color: #e5b8a8 !important;
        box-shadow: 0 0 0 3px rgba(217, 143, 131, 0.24) !important;
    }

    .stk-input-invalid {
        border-color: #fb7185 !important;
        box-shadow: 0 0 0 2px rgba(251, 113, 133, 0.22) !important;
    }

    /* Inline Quick-Add + Button */
    .stk-quick-add-btn {
        width: 24px;
        height: 24px;
        border-radius: 7px;
        border: 1px solid rgba(229, 184, 168, 0.22);
        background: rgba(217, 143, 131, 0.08);
        color: #e5b8a8;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
        transition: all 0.14s ease;
        padding: 0;
        flex-shrink: 0;
    }

    .stk-quick-add-btn:hover {
        background: rgba(217, 143, 131, 0.22);
        border-color: #d98f83;
        color: #f8efe6;
    }

    /* Scannable 6-Column Balance Sheet Table */
    .stk-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }

    .stk-table thead th {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #9e8780;
        padding: 12px 14px;
        background: rgba(255, 255, 255, 0.015);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        white-space: nowrap;
    }

    .stk-table tbody td {
        padding: 12px 14px;
        font-size: 13.5px;
        color: #f8efe6;
        border-bottom: 1px solid rgba(255, 255, 255, 0.045);
        vertical-align: middle;
    }

    .stk-table tbody tr:last-child td {
        border-bottom: none;
    }

    .stk-table tbody tr:hover {
        background: rgba(217, 143, 131, 0.03);
    }

    .stk-item-name {
        font-weight: 600;
        color: #f8efe6;
        line-height: 1.3;
    }

    .stk-item-meta {
        font-size: 12px;
        color: #967f78;
        margin-top: 2px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }

    .stk-row-err {
        font-size: 11.5px;
        font-weight: 600;
        color: #fda4af;
        margin-top: 4px;
    }

    .stk-total-val {
        font-weight: 700;
        font-size: 14px;
        color: #f3d9a4;
    }

    /* Buttons */
    .stk-btn-primary {
        background: linear-gradient(135deg, #d98f83, #c48479);
        border: none;
        color: #1b1315;
        font-weight: 700;
        font-size: 13px;
        padding: 7px 16px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        cursor: pointer;
    }

    .stk-btn-primary:hover {
        filter: brightness(1.06);
        color: #1b1315;
    }

    .stk-btn-emerald {
        background: rgba(52, 211, 153, 0.16);
        border: 1px solid rgba(52, 211, 153, 0.36);
        color: #6ee7b7;
        font-weight: 700;
        font-size: 13px;
        padding: 7px 17px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    .stk-btn-emerald:hover {
        background: rgba(52, 211, 153, 0.24);
        color: #a7f3d0;
    }

    .stk-btn-ghost {
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #d6c3bc;
        font-weight: 600;
        font-size: 12.5px;
        padding: 6.5px 14px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
        cursor: pointer;
    }

    .stk-btn-ghost:hover {
        border-color: rgba(217, 143, 131, 0.42);
        color: #f8efe6;
    }

    .stk-btn-rose-outline {
        background: transparent;
        border: 1px solid rgba(251, 113, 133, 0.36);
        color: #fda4af;
        font-weight: 600;
        font-size: 13px;
        padding: 7px 15px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
    }

    .stk-btn-rose-outline:hover {
        background: rgba(251, 113, 133, 0.12);
        color: #fecdd3;
    }

    /* Footer Bar */
    .stk-footer-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 14px 22px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        background: rgba(16, 11, 13, 0.45);
    }

    /* Actionable Empty State */
    .stk-empty {
        padding: 56px 24px;
        text-align: center;
    }

    .stk-empty-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        margin: 0 auto 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(217, 143, 131, 0.10);
        color: #d98f83;
        font-size: 22px;
    }

    .stk-empty h6 {
        margin: 0 0 6px;
        font-size: 16px;
        font-weight: 700;
        color: #f8efe6;
    }

    .stk-empty p {
        margin: 0 auto 18px;
        max-width: 440px;
        font-size: 13px;
        color: #9e8780;
        line-height: 1.5;
    }

    /* Collapsible Deliveries & Transfers Disclosure */
    .stk-disclosure {
        background: linear-gradient(180deg, rgba(31, 20, 24, 0.88) 0%, rgba(21, 14, 18, 0.94) 100%);
        border: 1px solid rgba(229, 184, 168, 0.12);
        border-radius: 14px;
        overflow: hidden;
    }

    .stk-disclosure summary {
        list-style: none;
        cursor: pointer;
        padding: 13px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 13px;
        font-weight: 600;
        color: #cbb8b0;
        user-select: none;
    }

    .stk-disclosure summary::-webkit-details-marker {
        display: none;
    }

    .stk-disclosure summary:hover {
        color: #f8efe6;
    }

    .stk-disclosure-chevron {
        transition: transform 0.18s ease;
        color: #9e8780;
    }

    .stk-disclosure[open] .stk-disclosure-chevron {
        transform: rotate(180deg);
    }

    .stk-disclosure-body {
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    /* Solid Opaque Dark Modals */
    .stk-modal .modal-content {
        background: #1b1315 !important;
        background-color: #1b1315 !important;
        opacity: 1 !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        border: 1px solid rgba(229, 184, 168, 0.28) !important;
        border-radius: 16px;
        box-shadow: 0 24px 64px rgba(0, 0, 0, 0.85) !important;
        color: #f8efe6;
    }

    .stk-modal .modal-header,
    .stk-modal .modal-body,
    .stk-modal .modal-footer {
        background: #1b1315 !important;
        background-color: #1b1315 !important;
    }

    .stk-modal .modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }

    .stk-modal .modal-header .btn-close {
        position: static !important;
        transform: none !important;
        margin: 0 0 0 auto !important;
        background-color: transparent !important;
        box-shadow: none !important;
        opacity: 0.78;
    }

    .stk-modal .modal-footer {
        border-top: 1px solid rgba(255, 255, 255, 0.07);
    }

    .stk-toast {
        position: fixed;
        top: 22px;
        right: 24px;
        z-index: 1095;
        background: #1b1315;
        border: 1px solid rgba(110, 231, 183, 0.44);
        color: #6ee7b7;
        border-radius: 12px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 600;
        box-shadow: 0 18px 46px rgba(0, 0, 0, 0.65);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    @media print {
        .stk-tabs, .stk-filter-bar, .stk-footer-bar, .stk-btn-primary, .stk-btn-ghost, .stk-btn-emerald, .stk-btn-rose-outline, .stk-quick-add-btn {
            display: none !important;
        }
        .stk-card, .stk-disclosure {
            background: #ffffff !important;
            color: #111111 !important;
            border-color: #dddddd !important;
        }
        .stk-table th, .stk-table td, .stk-item-name {
            color: #111111 !important;
        }
    }
</style>

<div class="stk-wrap">
    {{-- PAGE HEADER --}}
    <div class="stk-header">
        <div>
            <h4>Stock</h4>
            <p>Weekly Saturday&ndash;Friday stock counts per branch.</p>
        </div>

        @if ($isAdmin && $pendingApprovalWeeks->isNotEmpty())
            <div class="d-flex align-items-center flex-wrap gap-2">
                @foreach ($pendingApprovalWeeks as $pw)
                    <a href="{{ route('stock.index', ['tab' => 'audit', 'branch' => $pw->branch, 'week' => $pw->week_start_date->toDateString()]) }}"
                       class="stk-pill text-decoration-none"
                       style="background:rgba(245,158,11,0.14); border:1px solid rgba(245,158,11,0.34); color:#fbbf24;">
                        <i class="bx bx-time-five"></i>
                        <span>Review {{ \App\Models\StockWeek::branchLabel($pw->branch) }} ({{ $pw->week_start_date->format('j M') }})</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- FLASH / VALIDATION MESSAGES --}}
    @if (session('success'))
        <div class="mb-3 px-3 py-2 d-flex align-items-center gap-2" style="background:rgba(52,211,153,0.10); border:1px solid rgba(52,211,153,0.28); color:#6ee7b7; border-radius:12px; font-size:13px;">
            <i class="bx bx-check-circle fs-5"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="mb-3 px-3 py-2 d-flex align-items-center gap-2" style="background:rgba(251,113,133,0.10); border:1px solid rgba(251,113,133,0.28); color:#fda4af; border-radius:12px; font-size:13px;">
            <i class="bx bx-error-circle fs-5"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-3 px-3 py-2" style="background:rgba(251,113,133,0.10); border:1px solid rgba(251,113,133,0.28); color:#fda4af; border-radius:12px; font-size:13px;">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 3 PLAIN-ENGLISH TABS --}}
    <div class="stk-tabs" role="tablist">
        <a href="{{ route('stock.index', ['tab' => 'audit', 'branch' => $branch, 'week' => $bounds['start_str']]) }}"
           class="stk-tab {{ $activeTab === 'audit' ? 'active' : '' }}">
            <span>Weekly Count</span>
        </a>
        <a href="{{ route('stock.index', ['tab' => 'order', 'branch' => $branch, 'week' => $bounds['start_str']]) }}"
           class="stk-tab {{ $activeTab === 'order' ? 'active' : '' }}">
            <span>To Order</span>
            <span id="stkOrderTabBadge" class="stk-tab-badge stk-num {{ $totalOrderCount > 0 ? '' : 'd-none' }}">{{ $totalOrderCount }}</span>
        </a>
        <a href="{{ route('stock.index', ['tab' => 'catalog', 'branch' => $branch, 'week' => $bounds['start_str']]) }}"
           class="stk-tab {{ $activeTab === 'catalog' ? 'active' : '' }}">
            <span>Items</span>
        </a>
    </div>

    {{-- ===================================================================== --}}
    {{-- TAB 1: WEEKLY COUNT (LIVE BALANCE SHEET)                              --}}
    {{-- ===================================================================== --}}
    @if ($activeTab === 'audit')
        <div class="stk-card">
            {{-- ROW 1: PRIMARY CONTROL BAR --}}
            <div class="stk-primary-bar">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    @if ($scopedBranch)
                        <span class="stk-pill" style="background:rgba(212,163,115,0.12); border:1px solid rgba(212,163,115,0.26); color:#f3d9a4;">
                            {{ $branchLabel }}
                        </span>
                    @else
                        <div class="stk-branch-seg">
                            @foreach (\App\Models\StockWeek::BRANCHES as $bKey => $bLbl)
                                <a href="{{ route('stock.index', ['tab' => 'audit', 'branch' => $bKey, 'week' => $bounds['start_str']]) }}"
                                   class="stk-branch-opt {{ $branch === $bKey ? 'active' : '' }}">
                                    {{ $bLbl }}
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <div class="stk-week-ctrl stk-num">
                        <a href="{{ route('stock.index', ['tab' => 'audit', 'branch' => $branch, 'week' => $prevWeekStartStr]) }}" title="Previous week" aria-label="Previous week">
                            <i class="bx bx-chevron-left"></i>
                        </a>
                        <span class="px-2 fw-semibold">{{ $compactRangeLabel }}</span>
                        <a href="{{ route('stock.index', ['tab' => 'audit', 'branch' => $branch, 'week' => $nextWeekStartStr]) }}" title="Next week" aria-label="Next week">
                            <i class="bx bx-chevron-right"></i>
                        </a>
                        @if ($bounds['start_str'] !== $thisWeekStartStr)
                            <a href="{{ route('stock.index', ['tab' => 'audit', 'branch' => $branch, 'week' => $thisWeekStartStr]) }}" style="color:#d98f83; font-size:12px; font-weight:600;">
                                Today
                            </a>
                        @endif
                    </div>

                    <span class="stk-pill" style="background:{{ $activeTone['bg'] }}; border:1px solid {{ $activeTone['border'] }}; color:{{ $activeTone['color'] }};">
                        {{ $statusDisplay['label'] }}
                    </span>

                    @if ($hasEntries && $canEnterCounts)
                        <span id="stkSaveIndicator" class="stk-save-status ms-1" aria-live="polite"></span>
                    @endif
                </div>

                @if ($hasEntries && ($canLogRestock || $canTransfer))
                    <div class="d-flex align-items-center gap-2">
                        @if ($canTransfer)
                            <button type="button" class="stk-btn-ghost" data-bs-toggle="modal" data-bs-target="#transferStockModal">
                                <i class="bx bx-transfer"></i>
                                <span>Transfer</span>
                            </button>
                        @endif
                        @if ($canLogRestock)
                            <button type="button" class="stk-btn-primary" data-bs-toggle="modal" data-bs-target="#logRestockModal">
                                <i class="bx bx-plus"></i>
                                <span>Add Stock</span>
                            </button>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Revision Note (only when Admin explicitly requested a revision) --}}
            @if ($stockWeek->status === \App\Models\StockWeek::STATUS_REVISION_REQUESTED && $stockWeek->admin_note)
                <div class="px-4 py-2 d-flex align-items-center gap-2" style="background:rgba(251,113,133,0.08); border-bottom:1px solid rgba(251,113,133,0.2); font-size:12.5px; color:#fda4af;">
                    <i class="bx bx-message-alt-error"></i>
                    <span><strong>Revision note:</strong> {{ $stockWeek->admin_note }}</span>
                </div>
            @endif

            @if (!$hasEntries)
                {{-- ACTIONABLE MINIMAL EMPTY STATE --}}
                <div class="stk-empty">
                    <div class="stk-empty-icon">
                        <i class="bx bx-package"></i>
                    </div>
                    <h6>No stock items added yet</h6>
                    <p>Add your salon supplies (hair color, Botox, wax) or retail products to start your weekly count.</p>
                    @if ($canManageCatalog)
                        <button type="button" class="stk-btn-primary px-4 py-2" data-bs-toggle="modal" data-bs-target="#addBackbarItemModal">
                            <i class="bx bx-plus"></i>
                            <span>Add First Item</span>
                        </button>
                    @else
                        <a href="{{ route('stock.index', ['tab' => 'catalog', 'branch' => $branch, 'week' => $bounds['start_str']]) }}" class="stk-btn-ghost">
                            <span>View Items</span>
                        </a>
                    @endif
                </div>
            @else
                {{-- ROW 2: LIGHT INLINE FILTER STRIP --}}
                <div class="stk-filter-bar">
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="stk-type-pill active" data-stk-type="all">All</button>
                        <button type="button" class="stk-type-pill" data-stk-type="backbar">Backbar</button>
                        <button type="button" class="stk-type-pill" data-stk-type="retail">Retail</button>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <select id="stkCategoryFilter" class="form-select form-select-sm stk-select" style="width:auto; min-width:136px;" aria-label="Filter category">
                            <option value="">All Categories</option>
                            @foreach (\App\Models\StockItem::CATEGORIES as $cKey => $cLabel)
                                <option value="{{ $cKey }}">{{ $cLabel }}</option>
                            @endforeach
                        </select>
                        <input type="text" id="stkSearchInput" class="form-control form-control-sm stk-input" style="width:185px;" placeholder="Search items...">
                    </div>
                </div>

                {{-- Tiny subtle hint ONLY if every Opening is still 0 on Week 1 --}}
                @if ($isFirstWeekEditable && $allOpeningsZero)
                    <div id="stkZeroOpeningHint" class="px-4 py-2" style="border-bottom:1px solid rgba(255,255,255,0.04); font-size:12px; color:#8f7a73;">
                        Enter your starting Opening count for each item &mdash; changes save automatically.
                    </div>
                @endif

                {{-- 6-COLUMN LIVE BALANCE SHEET TABLE: Item | Opening | Added | Total | Used | Remaining --}}
                <form method="POST" action="{{ route('stock.weeks.submit', $stockWeek) }}" id="stockAuditForm">
                    @csrf
                    <div class="table-responsive">
                        <table class="stk-table stk-num" id="stkAuditTable">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Opening</th>
                                    <th class="text-end">Added</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Used</th>
                                    <th class="text-end">Remaining</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($entries as $entry)
                                    @php
                                        $item = $entry->item;
                                        $par = $item->parForBranch($branch);
                                        $received = round((float) $entry->received_qty, 2);
                                        $tNet = round((float) $entry->transfer_net_qty, 2);
                                        $netAdded = $entry->netAddedQty();
                                        $totalQty = $entry->totalAvailableQty();
                                        $usedQty = $entry->effectiveUsedQty();
                                        $remainingQty = $entry->currentEffectiveQty();
                                        $needOrder = $entry->needToOrderQty($par);
                                        $isRetail = $item->item_type === \App\Models\StockItem::TYPE_RETAIL;
                                        $retailSold = round((float) $entry->retail_sold_qty, 2);
                                        $shrinkage = $isRetail && ($usedQty - $retailSold) > 0.005;

                                        $addedParts = [];
                                        if ($received > 0) {
                                            $addedParts[] = 'Restock: +' . \App\Models\StockWeekEntry::formatQty($received);
                                        }
                                        if (abs($tNet) >= 0.01) {
                                            $addedParts[] = 'Transfers: ' . ($tNet > 0 ? '+' : '') . \App\Models\StockWeekEntry::formatQty($tNet);
                                        }
                                        $addedTooltip = !empty($addedParts) ? implode(' · ', $addedParts) : null;
                                    @endphp
                                    <tr class="stk-entry-row"
                                        data-entry-id="{{ $entry->id }}"
                                        data-item-id="{{ $item->id }}"
                                        data-item-name="{{ $item->name }}"
                                        data-item-unit="{{ $item->unit }}"
                                        data-save-url="{{ route('stock.weeks.entries.update', ['stockWeek' => $stockWeek->id, 'stockWeekEntry' => $entry->id]) }}"
                                        data-item-type="{{ $item->item_type }}"
                                        data-category="{{ $item->category }}"
                                        data-search="{{ strtolower($item->name . ' ' . $item->categoryLabel() . ' ' . $item->unit) }}"
                                        data-opening="{{ round((float) $entry->opening_qty, 2) }}"
                                        data-received="{{ $received }}"
                                        data-transfer="{{ $tNet }}"
                                        data-par="{{ $par }}"
                                        data-retail-sold="{{ $retailSold }}"
                                        data-is-retail="{{ $isRetail ? '1' : '0' }}">
                                        {{-- 1. Item (+ inline Order pill ONLY when below target & retail sold mismatch ONLY if Used > Sold) --}}
                                        <td>
                                            <div class="d-flex align-items-center flex-wrap gap-2">
                                                <span class="stk-item-name">{{ $item->name }}</span>
                                                <span class="js-stk-order-pill stk-pill {{ $needOrder > 0 ? '' : 'd-none' }}"
                                                      style="background:rgba(251,113,133,0.12); border:1px solid rgba(251,113,133,0.32); color:#fda4af;">
                                                    Order +{{ \App\Models\StockWeekEntry::formatQty($needOrder) }}
                                                </span>
                                            </div>
                                            <div class="stk-item-meta">
                                                <span>{{ $item->unit }} &middot; {{ $isRetail ? 'Retail' : 'Backbar' }}</span>
                                                <span class="js-stk-retail-mismatch {{ $shrinkage ? '' : 'd-none' }}" style="color:#fbbf24; font-weight:600;">
                                                    &middot; {{ \App\Models\StockWeekEntry::formatQty($retailSold) }} sold
                                                </span>
                                            </div>
                                            <div class="stk-row-err js-stk-row-err d-none"></div>
                                        </td>

                                        {{-- 2. Opening --}}
                                        <td class="text-end">
                                            @if ($isFirstWeekEditable)
                                                <input type="number" step="0.01" min="0"
                                                    name="entries[{{ $item->id }}][opening_qty]"
                                                    value="{{ \App\Models\StockWeekEntry::formatQty($entry->opening_qty) }}"
                                                    aria-label="Opening for {{ $item->name }}"
                                                    class="form-control form-control-sm stk-input stk-qty-input ms-auto js-stk-opening">
                                            @else
                                                <span class="js-stk-opening-static" data-val="{{ round((float) $entry->opening_qty, 2) }}" style="color:#cbb8b0;">
                                                    {{ \App\Models\StockWeekEntry::formatQty($entry->opening_qty) }}
                                                </span>
                                            @endif
                                        </td>

                                        {{-- 3. Added (+ compact inline + quick-add button) --}}
                                        <td class="text-end text-nowrap">
                                            <div class="d-inline-flex align-items-center justify-content-end gap-2">
                                                <span class="js-stk-added-val"
                                                      @if($addedTooltip) title="{{ $addedTooltip }}" @endif
                                                      style="color:{{ $netAdded > 0 ? '#6ee7b7' : ($netAdded < 0 ? '#fbbf24' : '#8f7a73') }}; font-weight:600;">
                                                    {{ $netAdded > 0 ? '+' : '' }}{{ \App\Models\StockWeekEntry::formatQty($netAdded) }}
                                                </span>
                                                @if ($canLogRestock)
                                                    <button type="button"
                                                        class="stk-quick-add-btn js-stk-quick-add"
                                                        data-item-id="{{ $item->id }}"
                                                        data-item-name="{{ $item->name }}"
                                                        data-item-unit="{{ $item->unit }}"
                                                        title="Add stock to {{ $item->name }}"
                                                        aria-label="Add stock to {{ $item->name }}">+</button>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- 4. Total (Bold read-only live balance: Opening + Added) --}}
                                        <td class="text-end text-nowrap">
                                            <span class="stk-total-val js-stk-total-val">{{ \App\Models\StockWeekEntry::formatQty($totalQty) }}</span>
                                        </td>

                                        {{-- 5. Used (Bi-directional input: updates Remaining = Total - Used) --}}
                                        <td class="text-end">
                                            @if ($canEnterCounts)
                                                <input type="number" step="0.01" min="0"
                                                    name="entries[{{ $item->id }}][used_qty]"
                                                    value="{{ \App\Models\StockWeekEntry::formatQty($usedQty) }}"
                                                    aria-label="Used for {{ $item->name }}"
                                                    class="form-control form-control-sm stk-input stk-qty-input ms-auto js-stk-used">
                                            @else
                                                <span class="fw-semibold" style="color:#d6c3bc;">
                                                    {{ \App\Models\StockWeekEntry::formatQty($usedQty) }}
                                                </span>
                                            @endif
                                        </td>

                                        {{-- 6. Remaining (Bi-directional primary input: updates Used = Total - Remaining) --}}
                                        <td class="text-end">
                                            @if ($canEnterCounts)
                                                <input type="number" step="0.01" min="0"
                                                    name="entries[{{ $item->id }}][closing_qty]"
                                                    value="{{ \App\Models\StockWeekEntry::formatQty($remainingQty) }}"
                                                    aria-label="Remaining for {{ $item->name }}"
                                                    class="form-control form-control-sm stk-qty-remaining ms-auto js-stk-remaining">
                                            @else
                                                <span class="fw-bold" style="color:#f8efe6;">
                                                    {{ \App\Models\StockWeekEntry::formatQty($remainingQty) }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- ULTRA-CLEAN SINGLE-STEP FOOTER BAR --}}
                    @if ($isOpenOrRevision)
                        @if ($isManager && $canEnterCounts)
                            <div class="stk-footer-bar">
                                <span class="small" style="color:#8f7a73;">All counts auto-save as you type.</span>
                                <button type="submit"
                                    formaction="{{ route('stock.weeks.submit', $stockWeek) }}"
                                    class="stk-btn-primary js-stk-workflow-submit"
                                    data-confirm="Submit Friday count for Admin approval?">
                                    Submit Friday Count for Approval
                                </button>
                            </div>
                        @elseif ($isAdmin)
                            <div class="stk-footer-bar">
                                <span class="small" style="color:#8f7a73;">All counts auto-save as you type.</span>
                                <button type="submit"
                                    formaction="{{ route('stock.weeks.approve', $stockWeek) }}"
                                    class="stk-btn-emerald js-stk-workflow-submit"
                                    data-confirm="Approve this week and carry Remaining balances into next Saturday?">
                                    Approve &amp; Carry Forward to Next Week
                                </button>
                            </div>
                        @elseif ($isOpsLead)
                            <div class="stk-footer-bar">
                                <span class="small" style="color:#8f7a73;">Use <strong>+</strong> or <strong>Transfer</strong> to record deliveries.</span>
                            </div>
                        @endif
                    @elseif ($stockWeek->isSubmitted())
                        <div class="stk-footer-bar">
                            <span class="small d-inline-flex align-items-center gap-2" style="color:#fbbf24;">
                                <i class="bx bx-lock-alt"></i>
                                <span>
                                    Submitted for Admin approval
                                    @if ($stockWeek->submitted_at)
                                        &middot; {{ $stockWeek->submitted_at->timezone('Asia/Qatar')->format('j M, g:i A') }}
                                    @endif
                                </span>
                            </span>
                            @if ($isAdmin)
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="stk-btn-rose-outline" data-bs-toggle="modal" data-bs-target="#requestRevisionModal">
                                        Request Revision
                                    </button>
                                    <button type="submit"
                                        formaction="{{ route('stock.weeks.approve', $stockWeek) }}"
                                        class="stk-btn-emerald js-stk-workflow-submit"
                                        data-confirm="Approve this count and carry Remaining balances forward to next Saturday?">
                                        Approve &amp; Carry Forward
                                    </button>
                                </div>
                            @endif
                        </div>
                    @elseif ($stockWeek->isApproved())
                        <div class="stk-footer-bar">
                            <span class="small d-inline-flex align-items-center gap-2" style="color:#6ee7b7;">
                                <i class="bx bx-check-circle"></i>
                                <span>
                                    Approved &middot; Remaining balances carried forward to next Saturday
                                </span>
                            </span>
                            @if ($isAdmin)
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none"
                                    style="color:#a8938b; font-size:12px;"
                                    onclick="if(confirm('Unlock this approved week for edits?')) document.getElementById('stkUnlockWeekForm').submit();">
                                    Unlock
                                </button>
                            @endif
                        </div>
                    @endif
                </form>

                @if ($isAdmin && $stockWeek->isApproved())
                    <form id="stkUnlockWeekForm" method="POST" action="{{ route('stock.weeks.unlock', $stockWeek) }}" class="d-none">
                        @csrf
                    </form>
                @endif
            @endif
        </div>

        {{-- COLLAPSIBLE DELIVERIES & TRANSFERS DISCLOSURE --}}
        <details class="stk-disclosure" id="stkMovementsDisclosure" {{ $recentMovements->isNotEmpty() ? 'open' : '' }}>
            <summary>
                <span class="d-inline-flex align-items-center gap-2">
                    <i class="bx bx-transfer-alt" style="color:#d98f83;"></i>
                    <span>Deliveries &amp; Transfers This Week (<span id="stkMovementsCount">{{ $recentMovements->count() }}</span>)</span>
                </span>
                <i class="bx bx-chevron-down fs-5 stk-disclosure-chevron"></i>
            </summary>
            <div class="stk-disclosure-body">
                <div class="table-responsive {{ $recentMovements->isEmpty() ? 'd-none' : '' }}" id="stkMovementsTableWrap">
                    <table class="stk-table stk-num">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Activity</th>
                                <th class="text-end">Qty</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody id="stkMovementsTbody">
                            @foreach ($recentMovements as $mv)
                                @php
                                    $mvColor = match ($mv->type) {
                                        'received', 'transfer_in' => '#6ee7b7',
                                        'transfer_out' => '#fbbf24',
                                        default => '#cbb8b0',
                                    };
                                    $mvSign = $mv->type === 'transfer_out' ? '-' : ($mv->type === 'opening_adjustment' ? ($mv->quantity >= 0 ? '+' : '') : '+');
                                @endphp
                                <tr>
                                    <td class="text-nowrap" style="color:#9e8780; font-size:12.5px;">
                                        {{ $mv->created_at?->timezone('Asia/Qatar')->format('j M, g:i A') }}
                                    </td>
                                    <td class="fw-semibold">{{ $mv->item?->name ?? 'Item' }}</td>
                                    <td style="font-size:12.5px;">
                                        <span style="color:{{ $mvColor }}; font-weight:600;">{{ $mv->typeLabel() }}</span>
                                        @if ($mv->counterparty_branch)
                                            <span style="color:#8f7a73;">
                                                &middot; {{ $mv->type === 'transfer_out' ? 'to' : 'from' }} {{ \App\Models\StockWeek::branchLabel($mv->counterparty_branch) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold" style="color:{{ $mvColor }};">
                                        {{ $mvSign }}{{ \App\Models\StockWeekEntry::formatQty($mv->quantity) }} {{ $mv->item?->unit }}
                                    </td>
                                    <td style="color:#9e8780; font-size:12.5px;">{{ $mv->reference_note ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div id="stkMovementsEmpty" class="py-4 text-center small {{ $recentMovements->isEmpty() ? '' : 'd-none' }}" style="color:#8f7a73;">
                    No deliveries or branch transfers recorded this week.
                </div>
            </div>
        </details>

    {{-- ===================================================================== --}}
    {{-- TAB 2: TO ORDER                                                       --}}
    {{-- ===================================================================== --}}
    @elseif ($activeTab === 'order')
        @foreach ($orderListByBranch as $obKey => $obData)
            <div class="stk-card">
                <div class="stk-primary-bar">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="mb-0 fw-bold" style="color:#f8efe6;">{{ $obData['branch_label'] }}</h6>
                            @if (count($obData['rows']) > 0)
                                <span class="stk-pill stk-num" style="background:rgba(251,113,133,0.12); border:1px solid rgba(251,113,133,0.3); color:#fda4af;">
                                    {{ count($obData['rows']) }} to order
                                </span>
                            @else
                                <span class="stk-pill" style="background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.28); color:#6ee7b7;">
                                    All stocked
                                </span>
                            @endif
                        </div>
                        <div class="small mt-1" style="color:#967f78;">Week of {{ $obData['week']->rangeLabel() }}</div>
                    </div>

                    @if (!empty($obData['rows']))
                        <button type="button"
                            class="stk-btn-primary js-copy-whatsapp"
                            data-whatsapp="{{ $obData['whatsapp_text'] }}">
                            <i class="bx bxl-whatsapp fs-5"></i>
                            <span>Copy WhatsApp Order List</span>
                        </button>
                    @endif
                </div>

                @if (!empty($obData['rows']))
                    <div class="table-responsive">
                        <table class="stk-table stk-num">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">Current</th>
                                    <th class="text-end">Target (Par)</th>
                                    <th class="text-end">To Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($obData['rows'] as $oRow)
                                    @php
                                        $oItem = $oRow['item'];
                                        $oIsRetail = $oItem->item_type === \App\Models\StockItem::TYPE_RETAIL;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="stk-item-name">{{ $oItem->name }}</div>
                                            <div class="stk-item-meta">{{ $oItem->unit }} &middot; {{ $oIsRetail ? 'Retail' : 'Backbar' }}</div>
                                        </td>
                                        <td class="text-end" style="color:#cbb8b0;">{{ \App\Models\StockWeekEntry::formatQty($oRow['current_qty']) }}</td>
                                        <td class="text-end" style="color:#9e8780;">{{ \App\Models\StockWeekEntry::formatQty($oRow['par_level']) }}</td>
                                        <td class="text-end">
                                            <span class="stk-pill" style="background:rgba(217,143,131,0.16); border:1px solid rgba(217,143,131,0.34); color:#f8efe6;">
                                                +{{ \App\Models\StockWeekEntry::formatQty($oRow['order_qty']) }} {{ $oItem->unit }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-5 text-center small" style="color:#6ee7b7;">
                        <i class="bx bx-check-circle me-1"></i>
                        Every active item in {{ $obData['branch_label'] }} is at or above Target Stock.
                    </div>
                @endif
            </div>
        @endforeach

    {{-- ===================================================================== --}}
    {{-- TAB 3: ITEMS (CATALOG & TARGET STOCK)                                 --}}
    {{-- ===================================================================== --}}
    @else
        <div class="stk-card">
            <div class="stk-primary-bar">
                <div>
                    <h6 class="mb-0 fw-bold" style="color:#f8efe6;">Items &amp; Target Stock</h6>
                    <div class="small mt-1" style="color:#967f78;">Salon backbar supplies and auto-synced retail products.</div>
                </div>
                @if ($canManageCatalog)
                    <button type="button" class="stk-btn-primary" data-bs-toggle="modal" data-bs-target="#addBackbarItemModal">
                        <i class="bx bx-plus"></i>
                        <span>Add Item</span>
                    </button>
                @endif
            </div>

            @if ($catalogItems->isEmpty())
                <div class="stk-empty">
                    <div class="stk-empty-icon">
                        <i class="bx bx-package"></i>
                    </div>
                    <h6>No stock items added yet</h6>
                    <p>Add your salon supplies (hair color, Botox, wax) or retail products to set weekly target stock levels.</p>
                    @if ($canManageCatalog)
                        <button type="button" class="stk-btn-primary px-4 py-2" data-bs-toggle="modal" data-bs-target="#addBackbarItemModal">
                            <i class="bx bx-plus"></i>
                            <span>Add First Item</span>
                        </button>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="stk-table stk-num">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Category</th>
                                <th class="text-end">Old Airport Target</th>
                                <th class="text-end">Al Wakrah Target</th>
                                @if ($canManageCatalog)
                                    <th class="text-end">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($catalogItems as $cItem)
                                @php $cIsRetail = $cItem->item_type === \App\Models\StockItem::TYPE_RETAIL; @endphp
                                <tr style="{{ !$cItem->is_active ? 'opacity:0.48;' : '' }}">
                                    <td>
                                        <div class="stk-item-name">
                                            {{ $cItem->name }}
                                            @if (!$cItem->is_active)
                                                <span class="ms-1 small" style="color:#9e8780; font-weight:500;">(Archived)</span>
                                            @endif
                                        </div>
                                        <div class="stk-item-meta">
                                            <span>{{ $cItem->unit }} &middot; {{ $cIsRetail ? 'Retail (Synced)' : 'Backbar' }}</span>
                                            @if ($cIsRetail && $cItem->product?->sku)
                                                <span>&middot; {{ $cItem->product->sku }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td style="color:#b8a29a; font-size:13px;">{{ $cItem->categoryLabel() }}</td>

                                    @if ($canManageCatalog)
                                        <td class="text-end">
                                            <input type="number" step="0.01" min="0" form="parForm-{{ $cItem->id }}" name="par_level_old_airport"
                                                value="{{ \App\Models\StockWeekEntry::formatQty($cItem->par_level_old_airport) }}"
                                                aria-label="Old Airport target for {{ $cItem->name }}"
                                                class="form-control form-control-sm stk-input stk-qty-input ms-auto">
                                        </td>
                                        <td class="text-end">
                                            <input type="number" step="0.01" min="0" form="parForm-{{ $cItem->id }}" name="par_level_al_wakrah"
                                                value="{{ \App\Models\StockWeekEntry::formatQty($cItem->par_level_al_wakrah) }}"
                                                aria-label="Al Wakrah target for {{ $cItem->name }}"
                                                class="form-control form-control-sm stk-input stk-qty-input ms-auto">
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <form id="parForm-{{ $cItem->id }}" method="POST" action="{{ route('stock.items.update', $cItem) }}" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="stk-btn-ghost py-1 px-3" style="font-size:12px;">
                                                    Save
                                                </button>
                                            </form>
                                            @if (!$cIsRetail && $cItem->is_active)
                                                <form method="POST" action="{{ route('stock.items.destroy', $cItem) }}" class="d-inline ms-1"
                                                      onsubmit="return confirm('Archive {{ addslashes($cItem->name) }} from future weekly counts?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm p-1" style="color:#9e8780;" title="Archive">
                                                        <i class="bx bx-archive-in"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    @else
                                        <td class="text-end" style="color:#cbb8b0;">{{ \App\Models\StockWeekEntry::formatQty($cItem->par_level_old_airport) }}</td>
                                        <td class="text-end" style="color:#cbb8b0;">{{ \App\Models\StockWeekEntry::formatQty($cItem->par_level_al_wakrah) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>

{{-- COMPACT INLINE QUICK-ADD STOCK MODAL (PRE-SELECTED TO EXACT ROW ITEM) --}}
@if ($canLogRestock)
<div class="modal fade stk-modal" id="quickAddStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form id="quickAddStockForm">
                <input type="hidden" id="qaStockItemId" name="stock_item_id" value="">
                <div class="modal-header py-3">
                    <div>
                        <h6 class="modal-title fw-bold mb-0" id="qaItemTitle" style="color:#f8efe6;">Add Stock</h6>
                        <div class="small" style="color:#9e8780;" id="qaItemSub">{{ $branchLabel }}</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label small text-muted" for="qaQuantity">Quantity (<span id="qaUnitLabel">Unit</span>)</label>
                        <input type="number" id="qaQuantity" name="quantity" step="0.01" min="0.01" class="form-control stk-input stk-num" required placeholder="e.g. 2">
                    </div>
                    <div class="mb-1">
                        <label class="form-label small text-muted" for="qaNote">Note (optional)</label>
                        <input type="text" id="qaNote" name="reference_note" class="form-control stk-input" placeholder="Supplier or invoice #">
                    </div>
                    <div id="qaErrorMsg" class="small mt-2 d-none" style="color:#fda4af;"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="stk-btn-ghost py-1 px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="qaSubmitBtn" class="stk-btn-primary py-1 px-4">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL 1: ADD STOCK (HEADER DROPDOWN BUTTON) --}}
@if ($canLogRestock)
<div class="modal fade stk-modal" id="logRestockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.movements.store') }}">
                @csrf
                <input type="hidden" name="week_start_date" value="{{ $bounds['start_str'] }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add Stock Delivery</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small text-muted">Branch</label>
                            @if ($scopedBranch)
                                <input type="hidden" name="branch" value="{{ $scopedBranch }}">
                                <div class="form-control stk-input">{{ $branchLabel }}</div>
                            @else
                                <select name="branch" class="form-select stk-select" required>
                                    @foreach (\App\Models\StockWeek::BRANCHES as $bKey => $bLbl)
                                        <option value="{{ $bKey }}" {{ $branch === $bKey ? 'selected' : '' }}>{{ $bLbl }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted">Item</label>
                            <select name="stock_item_id" class="form-select stk-select" required>
                                <option value="">Select item...</option>
                                @foreach ($activeCatalogItems as $ci)
                                    <option value="{{ $ci->id }}">{{ $ci->name }} ({{ $ci->unit }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small text-muted">Quantity Added</label>
                            <input type="number" name="quantity" step="0.01" min="0.01" class="form-control stk-input stk-num" required placeholder="e.g. 6">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small text-muted">Note (optional)</label>
                            <input type="text" name="reference_note" class="form-control stk-input" placeholder="Supplier or invoice #">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="stk-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="stk-btn-primary">Add Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL 2: TRANSFER BETWEEN BRANCHES --}}
@if ($canTransfer)
<div class="modal fade stk-modal" id="transferStockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.transfer') }}">
                @csrf
                <input type="hidden" name="week_start_date" value="{{ $bounds['start_str'] }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Transfer Between Branches</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">From</label>
                            @if ($scopedBranch)
                                <input type="hidden" name="from_branch" value="{{ $scopedBranch }}">
                                <div class="form-control stk-input">{{ $branchLabel }}</div>
                            @else
                                <select name="from_branch" id="stkTfrFrom" class="form-select stk-select" required>
                                    @foreach (\App\Models\StockWeek::BRANCHES as $bKey => $bLbl)
                                        <option value="{{ $bKey }}" {{ $branch === $bKey ? 'selected' : '' }}>{{ $bLbl }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">To</label>
                            @if ($scopedBranch)
                                <input type="hidden" name="to_branch" value="{{ $otherBranch }}">
                                <div class="form-control stk-input">{{ \App\Models\StockWeek::branchLabel($otherBranch) }}</div>
                            @else
                                <select name="to_branch" id="stkTfrTo" class="form-select stk-select" required>
                                    @foreach (\App\Models\StockWeek::BRANCHES as $bKey => $bLbl)
                                        <option value="{{ $bKey }}" {{ $otherBranch === $bKey ? 'selected' : '' }}>{{ $bLbl }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted">Item</label>
                            <select name="stock_item_id" class="form-select stk-select" required>
                                <option value="">Select item...</option>
                                @foreach ($activeCatalogItems as $ci)
                                    <option value="{{ $ci->id }}">{{ $ci->name }} ({{ $ci->unit }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small text-muted">Quantity</label>
                            <input type="number" name="quantity" step="0.01" min="0.01" class="form-control stk-input stk-num" required placeholder="e.g. 2">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small text-muted">Note (optional)</label>
                            <input type="text" name="reference_note" class="form-control stk-input" placeholder="e.g. Sent with driver">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="stk-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="stk-btn-primary">Confirm Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL 3: ADD STOCK ITEM (ADMIN ONLY) --}}
@if ($canManageCatalog)
<div class="modal fade stk-modal" id="addBackbarItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.items.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add Stock Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small text-muted">Item Name</label>
                            <input type="text" name="name" class="form-control stk-input" required maxlength="255" placeholder="e.g. L'Oréal Majirel 7.1 Ash Blonde">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Category</label>
                            <select name="category" class="form-select stk-select" required>
                                @foreach (\App\Models\StockItem::CATEGORIES as $cKey => $cLbl)
                                    @if ($cKey !== 'retail')
                                        <option value="{{ $cKey }}">{{ $cLbl }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Unit</label>
                            <input type="text" name="unit" list="stkUnitSuggestions" class="form-control stk-input" required maxlength="40" placeholder="Bottle, Tube, Box...">
                            <datalist id="stkUnitSuggestions">
                                @foreach (\App\Models\StockItem::COMMON_UNITS as $u)
                                    <option value="{{ $u }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Old Airport Target (Par)</label>
                            <input type="number" name="par_level_old_airport" step="0.01" min="0" value="3" class="form-control stk-input stk-num">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Al Wakrah Target (Par)</label>
                            <input type="number" name="par_level_al_wakrah" step="0.01" min="0" value="3" class="form-control stk-input stk-num">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="stk-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="stk-btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL 4: ADMIN REQUEST REVISION (ONLY WHEN SUBMITTED) --}}
@if ($isAdmin && $stockWeek->isSubmitted())
<div class="modal fade stk-modal" id="requestRevisionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.weeks.request-revision', $stockWeek) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Request Revision</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-3" style="color:#b8a29a;">
                        This unlocks {{ $branchLabel }}&rsquo;s count ({{ $compactRangeLabel }}) so the manager can recount and resubmit.
                    </p>
                    <label class="form-label small text-muted">Note to Manager <span style="color:#fda4af;">*</span></label>
                    <textarea name="admin_note" rows="3" class="form-control stk-input" required placeholder="e.g. Please recount Keratin Serum bottles on the retail shelf."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="stk-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="stk-btn-rose-outline fw-bold px-4">Send Back for Revision</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
    (function () {
        const csrfToken = '{{ csrf_token() }}';
        const activeBranch = '{{ $branch }}';
        const activeWeekStart = '{{ $bounds['start_str'] }}';
        const movementsStoreUrl = '{{ route('stock.movements.store') }}';
        const saveIndicator = document.getElementById('stkSaveIndicator');
        const orderTabBadge = document.getElementById('stkOrderTabBadge');
        const zeroOpeningHint = document.getElementById('stkZeroOpeningHint');

        function showStockToast(message) {
            const existing = document.getElementById('stkToast');
            if (existing) existing.remove();
            const toast = document.createElement('div');
            toast.id = 'stkToast';
            toast.className = 'stk-toast';
            toast.innerHTML = '<i class="bx bx-check-circle fs-5"></i><span></span>';
            toast.querySelector('span').textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.transition = 'opacity 0.25s ease';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 260);
            }, 3000);
        }

        function fmtQty(n) {
            const r = Math.round(n * 100) / 100;
            return Number.isInteger(r) ? String(r) : r.toFixed(2).replace(/\.?0+$/, '');
        }

        function setSaveStatus(state, text) {
            if (!saveIndicator) return;
            saveIndicator.classList.remove('is-saving', 'is-saved', 'is-error');
            if (state === 'saving') {
                saveIndicator.classList.add('is-saving');
                saveIndicator.textContent = text || 'Saving...';
            } else if (state === 'saved') {
                saveIndicator.classList.add('is-saved');
                saveIndicator.textContent = text || 'All changes saved ✓';
            } else if (state === 'error') {
                saveIndicator.classList.add('is-error');
                saveIndicator.textContent = text || 'Could not save';
            } else {
                saveIndicator.textContent = '';
            }
        }

        function updateOrderTabBadge(count) {
            if (!orderTabBadge || typeof count !== 'number') return;
            orderTabBadge.textContent = String(count);
            orderTabBadge.classList.toggle('d-none', count <= 0);
        }

        function setRowError(row, msg, inputEl) {
            const errEl = row.querySelector('.js-stk-row-err');
            row.querySelectorAll('.stk-input-invalid').forEach(el => el.classList.remove('stk-input-invalid'));
            if (msg) {
                if (errEl) {
                    errEl.textContent = msg;
                    errEl.classList.remove('d-none');
                }
                if (inputEl) inputEl.classList.add('stk-input-invalid');
            } else {
                if (errEl) {
                    errEl.textContent = '';
                    errEl.classList.add('d-none');
                }
            }
        }

        function updateRowBadges(row, used, remaining) {
            const par = parseFloat(row.dataset.par) || 0;
            const retailSold = parseFloat(row.dataset.retailSold) || 0;
            const isRetail = row.dataset.isRetail === '1';

            const orderPill = row.querySelector('.js-stk-order-pill');
            if (orderPill) {
                const need = par > 0 ? Math.round(Math.max(0, par - remaining) * 100) / 100 : 0;
                if (need > 0) {
                    orderPill.textContent = `Order +${fmtQty(need)}`;
                    orderPill.classList.remove('d-none');
                } else {
                    orderPill.classList.add('d-none');
                }
            }

            const mismatchEl = row.querySelector('.js-stk-retail-mismatch');
            if (mismatchEl && isRetail) {
                const isMismatch = (used - retailSold) > 0.005;
                mismatchEl.classList.toggle('d-none', !isMismatch);
            }
        }

        function applyServerEntryToRow(row, entry) {
            row.dataset.opening = String(entry.opening_qty);
            row.dataset.received = String(entry.received_qty);
            row.dataset.transfer = String(entry.transfer_net_qty);

            const openingInput = row.querySelector('.js-stk-opening');
            const openingStatic = row.querySelector('.js-stk-opening-static');
            if (openingInput && document.activeElement !== openingInput) {
                openingInput.value = fmtQty(entry.opening_qty);
            } else if (openingStatic) {
                openingStatic.dataset.val = String(entry.opening_qty);
                openingStatic.textContent = fmtQty(entry.opening_qty);
            }

            const addedEl = row.querySelector('.js-stk-added-val');
            if (addedEl) {
                const added = Number(entry.added_qty) || 0;
                addedEl.textContent = (added > 0 ? '+' : '') + fmtQty(added);
                addedEl.style.color = added > 0 ? '#6ee7b7' : (added < 0 ? '#fbbf24' : '#8f7a73');
                const parts = [];
                if (entry.received_qty > 0) parts.push(`Restock: +${fmtQty(entry.received_qty)}`);
                if (Math.abs(entry.transfer_net_qty) >= 0.01) {
                    parts.push(`Transfers: ${entry.transfer_net_qty > 0 ? '+' : ''}${fmtQty(entry.transfer_net_qty)}`);
                }
                if (parts.length) addedEl.title = parts.join(' · ');
            }

            const totalEl = row.querySelector('.js-stk-total-val');
            if (totalEl) totalEl.textContent = fmtQty(entry.total_qty);

            const usedInput = row.querySelector('.js-stk-used');
            if (usedInput && document.activeElement !== usedInput) {
                usedInput.value = fmtQty(entry.used_qty);
            }

            const remInput = row.querySelector('.js-stk-remaining');
            if (remInput && document.activeElement !== remInput) {
                remInput.value = fmtQty(entry.closing_qty);
            }

            if (zeroOpeningHint && entry.opening_qty > 0) {
                zeroOpeningHint.classList.add('d-none');
            }

            setRowError(row, null, null);
            updateRowBadges(row, Number(entry.used_qty) || 0, Number(entry.closing_qty) || 0);
        }

        // Per-row debounced auto-save
        const rowTimers = new WeakMap();
        const inFlightSaves = new Set();

        async function persistRowChange(row, changedField, payload) {
            const url = row.dataset.saveUrl;
            if (!url) return;

            setSaveStatus('saving', 'Saving...');
            const savePromise = (async () => {
                try {
                    const res = await fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(Object.assign({ changed_field: changedField }, payload)),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success && data.entry) {
                        applyServerEntryToRow(row, data.entry);
                        updateOrderTabBadge(data.total_order_count);
                        setSaveStatus('saved', 'All changes saved ✓');
                    } else {
                        const errMsg = data.errors
                            ? Object.values(data.errors).flat()[0]
                            : (data.message || 'Could not save change');
                        const targetInput = changedField === 'used'
                            ? row.querySelector('.js-stk-used')
                            : (changedField === 'remaining' ? row.querySelector('.js-stk-remaining') : row.querySelector('.js-stk-opening'));
                        setRowError(row, errMsg, targetInput);
                        setSaveStatus('error', errMsg);
                    }
                } catch (err) {
                    setRowError(row, 'Network error while saving', null);
                    setSaveStatus('error', 'Network error');
                }
            })();

            inFlightSaves.add(savePromise);
            try {
                await savePromise;
            } finally {
                inFlightSaves.delete(savePromise);
            }
        }

        function scheduleRowSave(row, changedField, payload, immediate = false) {
            const existing = rowTimers.get(row);
            if (existing) clearTimeout(existing);
            if (immediate) {
                persistRowChange(row, changedField, payload);
                return;
            }
            rowTimers.set(row, setTimeout(() => {
                rowTimers.delete(row);
                persistRowChange(row, changedField, payload);
            }, 420));
        }

        function getRowOpening(row) {
            const openingInput = row.querySelector('.js-stk-opening');
            const openingStatic = row.querySelector('.js-stk-opening-static');
            if (openingInput) {
                const v = parseFloat(openingInput.value);
                return Number.isNaN(v) ? 0 : Math.max(0, Math.round(v * 100) / 100);
            }
            if (openingStatic) {
                return parseFloat(openingStatic.dataset.val) || 0;
            }
            return parseFloat(row.dataset.opening) || 0;
        }

        function getRowAdded(row) {
            return Math.round(((parseFloat(row.dataset.received) || 0) + (parseFloat(row.dataset.transfer) || 0)) * 100) / 100;
        }

        function handleRowInput(row, changedField, immediate = false) {
            const openingInput = row.querySelector('.js-stk-opening');
            const usedInput = row.querySelector('.js-stk-used');
            const remInput = row.querySelector('.js-stk-remaining');
            const totalEl = row.querySelector('.js-stk-total-val');

            const opening = getRowOpening(row);
            const added = getRowAdded(row);
            const total = Math.round((opening + added) * 100) / 100;
            if (totalEl) totalEl.textContent = fmtQty(total);

            if (changedField === 'opening') {
                const currentUsed = Math.max(0, Math.round((parseFloat(usedInput?.value) || 0) * 100) / 100);
                if (total + 0.005 < currentUsed) {
                    setRowError(row, 'Cannot be lower than Used balance', openingInput);
                    setSaveStatus('error', 'Cannot exceed Total balance');
                    return;
                }
                const newRem = Math.round(Math.max(0, total - currentUsed) * 100) / 100;
                if (remInput) remInput.value = fmtQty(newRem);
                setRowError(row, null, null);
                updateRowBadges(row, currentUsed, newRem);
                scheduleRowSave(row, 'opening', { opening_qty: opening }, immediate);
                return;
            }

            if (changedField === 'used') {
                const raw = usedInput ? usedInput.value.trim() : '';
                if (raw === '' && !immediate) return;
                const usedVal = raw === '' ? 0 : Math.round(parseFloat(raw) * 100) / 100;
                if (Number.isNaN(usedVal) || usedVal < 0) {
                    setRowError(row, 'Must be 0 or greater', usedInput);
                    return;
                }
                if (usedVal > total + 0.005) {
                    setRowError(row, 'Cannot exceed Total balance', usedInput);
                    setSaveStatus('error', 'Cannot exceed Total balance');
                    return;
                }
                const newRem = Math.round(Math.max(0, total - usedVal) * 100) / 100;
                if (remInput) remInput.value = fmtQty(newRem);
                setRowError(row, null, null);
                updateRowBadges(row, usedVal, newRem);
                scheduleRowSave(row, 'used', { used_qty: usedVal }, immediate);
                return;
            }

            if (changedField === 'remaining') {
                const raw = remInput ? remInput.value.trim() : '';
                if (raw === '' && !immediate) return;
                const remVal = raw === '' ? 0 : Math.round(parseFloat(raw) * 100) / 100;
                if (Number.isNaN(remVal) || remVal < 0) {
                    setRowError(row, 'Must be 0 or greater', remInput);
                    return;
                }
                if (remVal > total + 0.005) {
                    setRowError(row, 'Cannot exceed Total balance', remInput);
                    setSaveStatus('error', 'Cannot exceed Total balance');
                    return;
                }
                const newUsed = Math.round(Math.max(0, total - remVal) * 100) / 100;
                if (usedInput) usedInput.value = fmtQty(newUsed);
                setRowError(row, null, null);
                updateRowBadges(row, newUsed, remVal);
                scheduleRowSave(row, 'remaining', { closing_qty: remVal }, immediate);
            }
        }

        document.querySelectorAll('.stk-entry-row').forEach(row => {
            const bindField = (selector, field) => {
                const el = row.querySelector(selector);
                if (!el) return;
                el.addEventListener('input', () => handleRowInput(row, field, false));
                el.addEventListener('blur', () => handleRowInput(row, field, true));
                el.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        handleRowInput(row, field, true);
                        el.blur();
                    }
                });
            };
            bindField('.js-stk-opening', 'opening');
            bindField('.js-stk-used', 'used');
            bindField('.js-stk-remaining', 'remaining');
        });

        // Prevent accidental submit while an invalid row exists; confirm cleanly on workflow buttons
        document.querySelectorAll('.js-stk-workflow-submit').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const invalidInput = document.querySelector('.stk-input-invalid');
                if (invalidInput) {
                    e.preventDefault();
                    invalidInput.focus();
                    setSaveStatus('error', 'Fix highlighted value first');
                    return;
                }
                const msg = btn.dataset.confirm;
                if (msg && !confirm(msg)) {
                    e.preventDefault();
                }
            });
        });

        // Inline Quick-Add + modal (pre-selected to exact item)
        const qaModalEl = document.getElementById('quickAddStockModal');
        const qaForm = document.getElementById('quickAddStockForm');
        const qaItemIdInput = document.getElementById('qaStockItemId');
        const qaItemTitle = document.getElementById('qaItemTitle');
        const qaUnitLabel = document.getElementById('qaUnitLabel');
        const qaQuantity = document.getElementById('qaQuantity');
        const qaNote = document.getElementById('qaNote');
        const qaErrorMsg = document.getElementById('qaErrorMsg');
        const qaSubmitBtn = document.getElementById('qaSubmitBtn');
        let qaModalInstance = null;

        document.querySelectorAll('.js-stk-quick-add').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!qaModalEl) return;
                qaItemIdInput.value = btn.dataset.itemId || '';
                qaItemTitle.textContent = btn.dataset.itemName || 'Add Stock';
                qaUnitLabel.textContent = btn.dataset.itemUnit || 'Unit';
                qaQuantity.value = '';
                qaNote.value = '';
                qaErrorMsg.textContent = '';
                qaErrorMsg.classList.add('d-none');
                qaModalInstance = bootstrap.Modal.getOrCreateInstance(qaModalEl);
                qaModalInstance.show();
                setTimeout(() => qaQuantity.focus(), 180);
            });
        });

        qaForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const itemId = qaItemIdInput.value;
            const qty = parseFloat(qaQuantity.value);
            if (!itemId || Number.isNaN(qty) || qty <= 0) {
                qaErrorMsg.textContent = 'Enter a quantity greater than 0.';
                qaErrorMsg.classList.remove('d-none');
                return;
            }

            qaSubmitBtn.disabled = true;
            qaSubmitBtn.textContent = 'Adding...';
            qaErrorMsg.classList.add('d-none');

            try {
                const res = await fetch(movementsStoreUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        branch: activeBranch,
                        week_start_date: activeWeekStart,
                        stock_item_id: Number(itemId),
                        quantity: qty,
                        reference_note: qaNote.value.trim() || null,
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success && data.entry) {
                    const row = document.querySelector(`.stk-entry-row[data-item-id="${itemId}"]`);
                    if (row) {
                        applyServerEntryToRow(row, data.entry);
                    }
                    updateOrderTabBadge(data.total_order_count);

                    // Prepend movement into collapsible Deliveries & Transfers table live
                    if (data.movement) {
                        const countEl = document.getElementById('stkMovementsCount');
                        const tableWrap = document.getElementById('stkMovementsTableWrap');
                        const emptyEl = document.getElementById('stkMovementsEmpty');
                        const tbody = document.getElementById('stkMovementsTbody');
                        if (countEl && typeof data.movements_count === 'number') {
                            countEl.textContent = String(data.movements_count);
                        }
                        if (tableWrap) tableWrap.classList.remove('d-none');
                        if (emptyEl) emptyEl.classList.add('d-none');
                        if (tbody) {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td class="text-nowrap" style="color:#9e8780; font-size:12.5px;"></td>
                                <td class="fw-semibold"></td>
                                <td style="font-size:12.5px;"><span style="color:#6ee7b7; font-weight:600;"></span></td>
                                <td class="text-end fw-semibold" style="color:#6ee7b7;"></td>
                                <td style="color:#9e8780; font-size:12.5px;"></td>
                            `;
                            tr.children[0].textContent = data.movement.date_label;
                            tr.children[1].textContent = data.movement.item_name;
                            tr.children[2].querySelector('span').textContent = data.movement.type_label;
                            tr.children[3].textContent = data.movement.qty_label;
                            tr.children[4].textContent = data.movement.note;
                            tbody.prepend(tr);
                        }
                    }

                    setSaveStatus('saved', 'All changes saved ✓');
                    qaModalInstance?.hide();
                    showStockToast(data.message || 'Stock added.');
                } else {
                    const errMsg = data.errors
                        ? Object.values(data.errors).flat()[0]
                        : (data.message || 'Unable to add stock.');
                    qaErrorMsg.textContent = errMsg;
                    qaErrorMsg.classList.remove('d-none');
                }
            } catch (err) {
                qaErrorMsg.textContent = 'Network error adding stock.';
                qaErrorMsg.classList.remove('d-none');
            } finally {
                qaSubmitBtn.disabled = false;
                qaSubmitBtn.textContent = 'Add';
            }
        });

        // Inline Type / Category / Search filtering
        let activeType = 'all';
        const typeBtns = document.querySelectorAll('[data-stk-type]');
        const catSelect = document.getElementById('stkCategoryFilter');
        const searchInput = document.getElementById('stkSearchInput');

        function applyAuditFilters() {
            const cat = catSelect ? catSelect.value : '';
            const q = searchInput ? searchInput.value.trim().toLowerCase() : '';
            document.querySelectorAll('.stk-entry-row').forEach(row => {
                const matchType = activeType === 'all' || row.dataset.itemType === activeType;
                const matchCat = !cat || row.dataset.category === cat;
                const matchSearch = !q || (row.dataset.search || '').includes(q);
                row.style.display = (matchType && matchCat && matchSearch) ? '' : 'none';
            });
        }

        typeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                activeType = btn.dataset.stkType;
                typeBtns.forEach(b => b.classList.toggle('active', b === btn));
                applyAuditFilters();
            });
        });
        catSelect?.addEventListener('change', applyAuditFilters);
        searchInput?.addEventListener('input', applyAuditFilters);

        // Auto-flip destination branch in transfer modal when Admin switches source branch
        const tfrFrom = document.getElementById('stkTfrFrom');
        const tfrTo = document.getElementById('stkTfrTo');
        tfrFrom?.addEventListener('change', () => {
            if (tfrTo) {
                tfrTo.value = tfrFrom.value === 'old_airport' ? 'al_wakrah' : 'old_airport';
            }
        });

        // Copy WhatsApp Order List buttons
        document.querySelectorAll('.js-copy-whatsapp').forEach(btn => {
            btn.addEventListener('click', async () => {
                const text = btn.dataset.whatsapp || '';
                try {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        await navigator.clipboard.writeText(text);
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        ta.remove();
                    }
                    showStockToast('WhatsApp order list copied to clipboard.');
                } catch (e) {
                    showStockToast('Copy failed — please select text manually.');
                }
            });
        });
    })();
</script>
@endsection
