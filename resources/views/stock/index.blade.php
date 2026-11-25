@extends('layouts.app')
@section('title', 'Stock')

@php
    $branchLabel = \App\Models\StockWeek::branchLabel($branch);
    $statusBadge = $stockWeek->statusBadge();
    $tonePalette = [
        'emerald' => ['bg' => 'rgba(52, 211, 153, 0.14)', 'border' => 'rgba(52, 211, 153, 0.34)', 'color' => '#6ee7b7'],
        'amber' => ['bg' => 'rgba(245, 158, 11, 0.14)', 'border' => 'rgba(245, 158, 11, 0.34)', 'color' => '#fbbf24'],
        'danger' => ['bg' => 'rgba(251, 113, 133, 0.14)', 'border' => 'rgba(251, 113, 133, 0.36)', 'color' => '#fda4af'],
        'champagne' => ['bg' => 'rgba(212, 163, 115, 0.14)', 'border' => 'rgba(212, 163, 115, 0.34)', 'color' => '#f3d9a4'],
    ];
    $activeTone = $tonePalette[$statusBadge['tone']] ?? $tonePalette['champagne'];
    $totalOrderCount = collect($orderListByBranch)->sum(fn ($b) => count($b['rows']));
    $thisWeekStartStr = \App\Models\StockWeek::weekBoundsFor()['start_str'];
    $otherBranch = $branch === 'old_airport' ? 'al_wakrah' : 'old_airport';
@endphp

@section('content')
<style>
    .stk-wrap {
        max-width: 1360px;
        margin: 0 auto;
    }

    .stk-num {
        font-variant-numeric: tabular-nums;
    }

    .stk-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 18px;
    }

    .stk-header h4 {
        margin-bottom: 2px;
        font-weight: 700;
        color: #f8efe6;
    }

    .stk-header p {
        margin-bottom: 0;
        font-size: 13px;
        color: #b8a29a;
    }

    .stk-tabs {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        padding: 4px;
        border-radius: 999px;
        background: #20171a;
        border: 1px solid rgba(217, 143, 131, 0.18);
        margin-bottom: 18px;
    }

    .stk-tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 700;
        color: #c9a39a;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .stk-tab:hover {
        color: #f8efe6;
        background: rgba(217, 143, 131, 0.08);
    }

    .stk-tab.active {
        background: linear-gradient(135deg, #d98f83, #c48479);
        color: #1b1315;
    }

    .stk-card {
        background: linear-gradient(180deg, rgba(33, 22, 25, 0.94) 0%, rgba(23, 16, 18, 0.98) 100%);
        border: 1px solid rgba(217, 143, 131, 0.18);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .stk-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }

    .stk-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 700;
        white-space: nowrap;
        line-height: 1.3;
    }

    .stk-branch-pills {
        display: inline-flex;
        gap: 4px;
        padding: 3px;
        border-radius: 10px;
        background: #140e10;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .stk-branch-pill {
        padding: 5px 13px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 700;
        text-decoration: none;
        color: #b8a29a;
    }

    .stk-branch-pill.active {
        background: rgba(217, 143, 131, 0.18);
        color: #f8efe6;
    }

    .stk-week-nav {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #140e10;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        padding: 4px 8px;
    }

    .stk-week-nav a {
        color: #d6c3bc;
        text-decoration: none;
        padding: 3px 7px;
        border-radius: 6px;
        font-size: 12.5px;
        font-weight: 600;
    }

    .stk-week-nav a:hover {
        background: rgba(217, 143, 131, 0.14);
        color: #fff;
    }

    .stk-input, .stk-select {
        background-color: #140e10 !important;
        border: 1px solid rgba(217, 143, 131, 0.24) !important;
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
        background-position: right 0.75rem center !important;
        background-size: 12px 10px !important;
        padding-right: 2.2rem !important;
    }

    .stk-select option {
        background-color: #140e10;
        color: #f8efe6;
    }

    .stk-input:focus, .stk-select:focus {
        border-color: #d98f83 !important;
        box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.18) !important;
    }

    .stk-qty-input {
        width: 76px;
        text-align: right;
        padding: 4px 7px !important;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .stk-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }

    .stk-table thead th {
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #a8938b;
        padding: 10px 8px;
        background: rgba(217, 143, 131, 0.05);
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        white-space: nowrap;
    }

    .stk-table tbody td {
        padding: 9px 8px;
        font-size: 12.5px;
        color: #f8efe6;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        vertical-align: middle;
    }

    .stk-table tbody tr:hover {
        background: rgba(217, 143, 131, 0.035);
    }

    .stk-filter-pill {
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: transparent;
        color: #b8a29a;
        border-radius: 999px;
        padding: 4px 12px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .stk-filter-pill.active {
        background: rgba(217, 143, 131, 0.18);
        border-color: rgba(217, 143, 131, 0.42);
        color: #f8efe6;
    }

    .stk-btn-primary {
        background: linear-gradient(135deg, #d98f83, #c48479);
        border: none;
        color: #1b1315;
        font-weight: 700;
        font-size: 13px;
        padding: 8px 18px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .stk-btn-primary:hover {
        filter: brightness(1.06);
        color: #1b1315;
    }

    .stk-btn-emerald {
        background: rgba(52, 211, 153, 0.16);
        border: 1px solid rgba(52, 211, 153, 0.38);
        color: #6ee7b7;
        font-weight: 700;
        font-size: 13px;
        padding: 8px 18px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .stk-btn-emerald:hover {
        background: rgba(52, 211, 153, 0.24);
        color: #a7f3d0;
    }

    .stk-btn-ghost {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.14);
        color: #e6d6d0;
        font-weight: 600;
        font-size: 12.5px;
        padding: 7px 14px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
    }

    .stk-btn-ghost:hover {
        border-color: rgba(217, 143, 131, 0.45);
        color: #fff;
    }

    /* Solid opaque modals */
    .stk-modal .modal-content {
        background: #1b1315 !important;
        background-color: #1b1315 !important;
        opacity: 1 !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        border: 1px solid rgba(217, 143, 131, 0.32) !important;
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
</style>

<div class="stk-wrap">
    {{-- TOP HEADER --}}
    <div class="stk-header">
        <div>
            <h4>Stock</h4>
            <p>Saturday &ndash; Friday weekly inventory audit, restock deliveries, branch transfers &amp; WhatsApp reorder lists.</p>
        </div>

        @if ($isAdmin && $pendingApprovalWeeks->isNotEmpty())
            <div class="d-flex align-items-center flex-wrap gap-2">
                @foreach ($pendingApprovalWeeks as $pw)
                    <a href="{{ route('stock.index', ['tab' => 'audit', 'branch' => $pw->branch, 'week' => $pw->week_start_date->toDateString()]) }}"
                       class="stk-pill text-decoration-none"
                       style="background:rgba(245,158,11,0.16); border:1px solid rgba(245,158,11,0.38); color:#fbbf24;">
                        <i class="bx bx-bell"></i>
                        <span>Approve: {{ \App\Models\StockWeek::branchLabel($pw->branch) }} ({{ $pw->week_start_date->format('d M') }})</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- FLASH / VALIDATION BANNERS --}}
    @if (session('success'))
        <div class="alert py-2 px-3 mb-3 d-flex align-items-center gap-2" style="background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.34); color:#6ee7b7; border-radius:12px; font-size:13px;">
            <i class="bx bx-check-circle fs-5"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="alert py-2 px-3 mb-3 d-flex align-items-center gap-2" style="background:rgba(251,113,133,0.12); border:1px solid rgba(251,113,133,0.34); color:#fda4af; border-radius:12px; font-size:13px;">
            <i class="bx bx-error-circle fs-5"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert py-2 px-3 mb-3" style="background:rgba(251,113,133,0.12); border:1px solid rgba(251,113,133,0.34); color:#fda4af; border-radius:12px; font-size:13px;">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 3 MODULE TABS --}}
    <div class="stk-tabs" role="tablist">
        <a href="{{ route('stock.index', ['tab' => 'audit', 'branch' => $branch, 'week' => $bounds['start_str']]) }}"
           class="stk-tab {{ $activeTab === 'audit' ? 'active' : '' }}">
            <i class="bx bx-clipboard"></i>
            <span>Weekly Audit (Sat &ndash; Fri)</span>
        </a>
        <a href="{{ route('stock.index', ['tab' => 'order', 'branch' => $branch, 'week' => $bounds['start_str']]) }}"
           class="stk-tab {{ $activeTab === 'order' ? 'active' : '' }}">
            <i class="bx bx-cart-alt"></i>
            <span>Need to Order</span>
            @if ($totalOrderCount > 0)
                <span class="badge rounded-pill stk-num" style="background:{{ $activeTab === 'order' ? 'rgba(27,19,21,0.22)' : 'rgba(251,113,133,0.22)' }}; color:{{ $activeTab === 'order' ? '#1b1315' : '#fda4af' }}; font-size:11px;">
                    {{ $totalOrderCount }}
                </span>
            @endif
        </a>
        <a href="{{ route('stock.index', ['tab' => 'catalog', 'branch' => $branch, 'week' => $bounds['start_str']]) }}"
           class="stk-tab {{ $activeTab === 'catalog' ? 'active' : '' }}">
            <i class="bx bx-package"></i>
            <span>Stock Catalog &amp; Par Levels</span>
        </a>
    </div>

    {{-- ===================================================================== --}}
    {{-- TAB 1: WEEKLY AUDIT (SAT – FRI)                                       --}}
    {{-- ===================================================================== --}}
    @if ($activeTab === 'audit')
        <div class="stk-card">
            <div class="stk-card-head">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    {{-- Branch Switcher --}}
                    @if ($scopedBranch)
                        <span class="stk-pill" style="background:rgba(212,163,115,0.14); border:1px solid rgba(212,163,115,0.3); color:#f3d9a4;">
                            <i class="bx bx-store"></i> {{ $branchLabel }}
                        </span>
                    @else
                        <div class="stk-branch-pills">
                            @foreach (\App\Models\StockWeek::BRANCHES as $bKey => $bLbl)
                                <a href="{{ route('stock.index', ['tab' => 'audit', 'branch' => $bKey, 'week' => $bounds['start_str']]) }}"
                                   class="stk-branch-pill {{ $branch === $bKey ? 'active' : '' }}">
                                    {{ $bLbl }}
                                </a>
                            @endforeach
                        </div>
                    @endif

                    {{-- Saturday–Friday Week Picker --}}
                    <div class="stk-week-nav stk-num">
                        <a href="{{ route('stock.index', ['tab' => 'audit', 'branch' => $branch, 'week' => $prevWeekStartStr]) }}" title="Previous Sat–Fri week">
                            <i class="bx bx-chevron-left"></i>
                        </a>
                        <span class="px-2 fw-semibold" style="font-size:13px; color:#f8efe6;">{{ $bounds['label'] }}</span>
                        <a href="{{ route('stock.index', ['tab' => 'audit', 'branch' => $branch, 'week' => $nextWeekStartStr]) }}" title="Next Sat–Fri week">
                            <i class="bx bx-chevron-right"></i>
                        </a>
                        @if ($bounds['start_str'] !== $thisWeekStartStr)
                            <a href="{{ route('stock.index', ['tab' => 'audit', 'branch' => $branch, 'week' => $thisWeekStartStr]) }}" style="color:#d98f83;">
                                Current
                            </a>
                        @endif
                    </div>

                    {{-- Status Pill --}}
                    <span class="stk-pill" style="background:{{ $activeTone['bg'] }}; border:1px solid {{ $activeTone['border'] }}; color:{{ $activeTone['color'] }};">
                        {{ $statusBadge['label'] }}
                    </span>
                </div>

                {{-- Quick Movement Actions --}}
                <div class="d-flex align-items-center flex-wrap gap-2">
                    @if ($canLogRestock)
                        <button type="button" class="stk-btn-ghost" data-bs-toggle="modal" data-bs-target="#logRestockModal">
                            <i class="bx bx-plus-circle" style="color:#6ee7b7;"></i>
                            <span>Log Restock</span>
                        </button>
                    @endif
                    @if ($canTransfer)
                        <button type="button" class="stk-btn-ghost" data-bs-toggle="modal" data-bs-target="#transferStockModal">
                            <i class="bx bx-transfer" style="color:#f3d9a4;"></i>
                            <span>Transfer Branch</span>
                        </button>
                    @endif
                </div>
            </div>

            {{-- Status Metadata / Revision Alert --}}
            @if ($stockWeek->status === \App\Models\StockWeek::STATUS_REVISION_REQUESTED && $stockWeek->admin_note)
                <div class="px-4 py-3 d-flex align-items-start gap-2" style="background:rgba(251,113,133,0.10); border-bottom:1px solid rgba(251,113,133,0.24); color:#fda4af; font-size:13px;">
                    <i class="bx bx-error-circle fs-5 mt-0"></i>
                    <div>
                        <strong>Revision Requested by Admin:</strong> {{ $stockWeek->admin_note }}
                    </div>
                </div>
            @elseif ($stockWeek->isSubmitted())
                <div class="px-4 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2" style="background:rgba(245,158,11,0.08); border-bottom:1px solid rgba(245,158,11,0.2); font-size:12.5px; color:#fbbf24;">
                    <span>
                        <i class="bx bx-time-five me-1"></i>
                        Submitted by <strong>{{ $stockWeek->submitter?->name ?? 'Manager' }}</strong>
                        @if ($stockWeek->submitted_at)
                            on {{ $stockWeek->submitted_at->timezone('Asia/Qatar')->format('d M Y, h:i A') }}
                        @endif
                        &mdash; locked pending Admin review.
                    </span>
                </div>
            @elseif ($stockWeek->isApproved())
                <div class="px-4 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2" style="background:rgba(52,211,153,0.08); border-bottom:1px solid rgba(52,211,153,0.2); font-size:12.5px; color:#6ee7b7;">
                    <span>
                        <i class="bx bx-badge-check me-1"></i>
                        Approved by <strong>{{ $stockWeek->approver?->name ?? 'Admin' }}</strong>
                        @if ($stockWeek->approved_at)
                            on {{ $stockWeek->approved_at->timezone('Asia/Qatar')->format('d M Y, h:i A') }}
                        @endif
                        &mdash; Friday closing counts carried forward to next Saturday&rsquo;s opening sheet.
                    </span>
                </div>
            @elseif ($canEditOpening && !$hasPriorApprovedWeek)
                <div class="px-4 py-2" style="background:rgba(212,163,115,0.08); border-bottom:1px solid rgba(212,163,115,0.18); font-size:12.5px; color:#e8cfa9;">
                    <i class="bx bx-info-circle me-1"></i>
                    Fresh start for {{ $branchLabel }}: enter initial <strong>Opening (Sat)</strong> counts once. Future weeks carry forward automatically after Admin approval.
                </div>
            @endif

            {{-- Client-Side Table Filter Row --}}
            <div class="px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <button type="button" class="stk-filter-pill active" data-stk-type="all">All Items ({{ $entries->count() }})</button>
                    <button type="button" class="stk-filter-pill" data-stk-type="backbar">Backbar Supplies</button>
                    <button type="button" class="stk-filter-pill" data-stk-type="retail">Retail Products</button>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="stkCategoryFilter" class="form-select form-select-sm stk-select" style="width:auto; min-width:140px;" aria-label="Filter category">
                        <option value="">All Categories</option>
                        @foreach (\App\Models\StockItem::CATEGORIES as $cKey => $cLabel)
                            <option value="{{ $cKey }}">{{ $cLabel }}</option>
                        @endforeach
                    </select>
                    <input type="text" id="stkSearchInput" class="form-control form-control-sm stk-input" style="width:200px;" placeholder="Search item name...">
                </div>
            </div>

            {{-- WEEKLY AUDIT TABLE & FORM --}}
            <form method="POST" action="{{ route('stock.weeks.save-counts', $stockWeek) }}" id="stockAuditForm">
                @csrf
                <div class="table-responsive">
                    <table class="stk-table stk-num" id="stkAuditTable">
                        <thead>
                            <tr>
                                <th>Item &amp; Unit</th>
                                <th class="text-end">Opening (Sat)</th>
                                <th class="text-end">+ Recv</th>
                                <th class="text-end">Closing (Fri)</th>
                                <th class="text-end">Used</th>
                                <th class="text-end">Sold</th>
                                <th class="text-end">Par</th>
                                <th class="text-end">Order</th>
                                <th class="text-end">Next Sat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                @php
                                    $item = $entry->item;
                                    $par = $item->parForBranch($branch);
                                    $needOrder = $entry->needToOrderQty($par);
                                    $tNet = (float) $entry->transfer_net_qty;
                                    $isRetail = $item->item_type === \App\Models\StockItem::TYPE_RETAIL;
                                    $usedVal = $entry->used_qty;
                                    $negUsage = $entry->hasNegativeUsage();
                                    $shrinkage = $entry->hasRetailShrinkage();
                                @endphp
                                <tr class="stk-entry-row"
                                    data-item-type="{{ $item->item_type }}"
                                    data-category="{{ $item->category }}"
                                    data-search="{{ strtolower($item->name . ' ' . $item->categoryLabel() . ' ' . $item->unit) }}"
                                    data-received="{{ (float) $entry->received_qty }}"
                                    data-transfer="{{ $tNet }}"
                                    data-par="{{ $par }}"
                                    data-retail-sold="{{ (float) $entry->retail_sold_qty }}"
                                    data-is-retail="{{ $isRetail ? '1' : '0' }}">
                                    <td>
                                        <div class="fw-semibold" style="color:#f8efe6;">{{ $item->name }}</div>
                                        <div class="d-flex align-items-center gap-2 mt-1" style="font-size:11.5px; color:#9e8780;">
                                            <span style="color:{{ $isRetail ? '#8aa6ab' : '#e79a91' }}; font-weight:600;">
                                                {{ $isRetail ? 'Retail' : 'Backbar' }}
                                            </span>
                                            <span>&middot;</span>
                                            <span>{{ $item->categoryLabel() }}</span>
                                            <span>&middot;</span>
                                            <span>{{ $item->unit }}</span>
                                        </div>
                                    </td>

                                    <td class="text-end">
                                        @if ($canEditOpening)
                                            <input type="number" step="0.01" min="0"
                                                name="entries[{{ $item->id }}][opening_qty]"
                                                value="{{ \App\Models\StockWeekEntry::formatQty($entry->opening_qty) }}"
                                                class="form-control form-control-sm stk-input stk-qty-input ms-auto js-stk-opening">
                                        @else
                                            <span class="js-stk-opening-static" data-val="{{ (float) $entry->opening_qty }}">
                                                {{ \App\Models\StockWeekEntry::formatQty($entry->opening_qty) }}
                                            </span>
                                        @endif
                                    </td>

                                    <td class="text-end text-nowrap">
                                        <span style="color:{{ (float) $entry->received_qty > 0 ? '#6ee7b7' : '#a8938b' }}; font-weight:600;">
                                            {{ (float) $entry->received_qty > 0 ? '+' . \App\Models\StockWeekEntry::formatQty($entry->received_qty) : '0' }}
                                        </span>
                                        @if (abs($tNet) >= 0.01)
                                            <div class="small" style="color:{{ $tNet > 0 ? '#6ee7b7' : '#fbbf24' }}; font-size:11px;">
                                                {{ $tNet > 0 ? '+' : '' }}{{ \App\Models\StockWeekEntry::formatQty($tNet) }} Tfr
                                            </div>
                                        @endif
                                    </td>

                                    <td class="text-end">
                                        @if ($canEnterCounts)
                                            <input type="number" step="0.01" min="0"
                                                name="entries[{{ $item->id }}][closing_qty]"
                                                value="{{ $entry->closing_qty !== null ? \App\Models\StockWeekEntry::formatQty($entry->closing_qty) : '' }}"
                                                placeholder="Count"
                                                class="form-control form-control-sm stk-input stk-qty-input ms-auto js-stk-closing">
                                        @else
                                            <span class="fw-semibold" style="color:{{ $entry->closing_qty !== null ? '#f8efe6' : '#8f7a73' }};">
                                                {{ \App\Models\StockWeekEntry::formatQty($entry->closing_qty) }}
                                            </span>
                                        @endif
                                    </td>

                                    <td class="text-end text-nowrap js-stk-used-cell">
                                        @if ($usedVal !== null)
                                            <span class="fw-semibold" style="color:{{ ($negUsage || $shrinkage) ? '#fda4af' : '#f8efe6' }};">
                                                {{ \App\Models\StockWeekEntry::formatQty($usedVal) }}
                                            </span>
                                            @if ($negUsage)
                                                <div class="small" style="color:#fda4af; font-size:10.5px;">Check count</div>
                                            @elseif ($shrinkage)
                                                <div class="small" style="color:#fbbf24; font-size:10.5px;">Shrinkage alert</div>
                                            @endif
                                        @else
                                            <span style="color:#8f7a73;">&mdash;</span>
                                        @endif
                                    </td>

                                    <td class="text-end">
                                        @if ($isRetail)
                                            <span style="color:#8aa6ab; font-weight:600;">{{ \App\Models\StockWeekEntry::formatQty($entry->retail_sold_qty) }}</span>
                                        @else
                                            <span style="color:#6d5b56;">&mdash;</span>
                                        @endif
                                    </td>

                                    <td class="text-end" style="color:#cbb8b0;">
                                        {{ $par > 0 ? \App\Models\StockWeekEntry::formatQty($par) : '—' }}
                                    </td>

                                    <td class="text-end text-nowrap js-stk-order-cell">
                                        @if ($needOrder > 0)
                                            <span class="stk-pill" style="background:rgba(251,113,133,0.14); border:1px solid rgba(251,113,133,0.34); color:#fda4af;">
                                                Order {{ \App\Models\StockWeekEntry::formatQty($needOrder) }}
                                            </span>
                                        @elseif ($par > 0)
                                            <span style="color:#6ee7b7; font-size:12px; font-weight:600;">OK</span>
                                        @else
                                            <span style="color:#6d5b56;">&mdash;</span>
                                        @endif
                                    </td>

                                    <td class="text-end text-nowrap" style="color:#cbb8b0; font-size:12px;">
                                        @if ($stockWeek->isApproved())
                                            <span style="color:#6ee7b7;">
                                                <i class="bx bx-right-arrow-alt"></i>
                                                {{ \App\Models\StockWeekEntry::formatQty($entry->currentEffectiveQty()) }}
                                            </span>
                                        @elseif ($entry->closing_qty !== null)
                                            {{ \App\Models\StockWeekEntry::formatQty($entry->closing_qty) }}
                                        @else
                                            <span style="color:#6d5b56;">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5" style="color:#9e8780;">
                                        No stock items yet. Add Backbar supplies in the <strong>Stock Catalog &amp; Par Levels</strong> tab or add retail products in <strong>Products</strong>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- AUDIT ACTION FOOTER --}}
                <div class="px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2" style="border-top:1px solid rgba(255,255,255,0.07); background:rgba(20,14,16,0.5);">
                    <div class="small" style="color:#9e8780;">
                        Formula: <strong>Used = Opening (Sat) + Received &plusmn; Transfers &minus; Closing (Fri)</strong> &middot; Decimals supported (e.g. 1.5).
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        @if ($canEnterCounts)
                            <button type="submit" class="stk-btn-ghost">
                                <i class="bx bx-save"></i>
                                <span>Save Draft Counts</span>
                            </button>
                            @if ($isManager)
                                <button type="submit"
                                    formaction="{{ route('stock.weeks.submit', $stockWeek) }}"
                                    class="stk-btn-primary"
                                    onclick="return confirm('Submit this weekly count for Admin approval? You will not be able to edit after submission.')">
                                    <i class="bx bx-send"></i>
                                    <span>Submit Weekly Count for Approval</span>
                                </button>
                            @endif
                        @endif

                        @if ($isAdmin)
                            @if (!$stockWeek->isApproved())
                                <button type="button" class="stk-btn-ghost" style="border-color:rgba(251,113,133,0.36); color:#fda4af;"
                                    data-bs-toggle="modal" data-bs-target="#requestRevisionModal">
                                    <i class="bx bx-undo"></i>
                                    <span>Request Revision</span>
                                </button>
                                <button type="submit"
                                    formaction="{{ route('stock.weeks.approve', $stockWeek) }}"
                                    class="stk-btn-emerald"
                                    onclick="return confirm('Approve this week permanently and carry Friday closing balances into next Saturday?')">
                                    <i class="bx bx-check-double"></i>
                                    <span>Approve &amp; Carry Forward</span>
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            </form>

            @if ($isAdmin && $stockWeek->isApproved())
                <div class="px-4 py-2 d-flex justify-content-end" style="border-top:1px solid rgba(255,255,255,0.05);">
                    <form method="POST" action="{{ route('stock.weeks.unlock', $stockWeek) }}" onsubmit="return confirm('Unlock this approved weekly sheet for edits?')">
                        @csrf
                        <button type="submit" class="stk-btn-ghost py-1" style="border-color:rgba(245,158,11,0.35); color:#fbbf24; font-size:12px;">
                            <i class="bx bx-lock-open"></i> Unlock Approved Sheet
                        </button>
                    </form>
                </div>
            @endif
        </div>

        {{-- RECENT MOVEMENTS LOG FOR THIS WEEK --}}
        <div class="stk-card">
            <div class="stk-card-head">
                <div>
                    <h6 class="mb-0 fw-bold" style="color:#f8efe6;"><i class="bx bx-transfer-alt me-1" style="color:#d98f83;"></i> Deliveries &amp; Transfers This Week</h6>
                    <span class="small" style="color:#9e8780;">{{ $recentMovements->count() }} recorded {{ Str::plural('movement', $recentMovements->count()) }} &middot; {{ $branchLabel }}</span>
                </div>
            </div>
            @if ($recentMovements->isNotEmpty())
                <div class="table-responsive">
                    <table class="stk-table stk-num">
                        <thead>
                            <tr>
                                <th>Date &amp; Time</th>
                                <th>Item</th>
                                <th>Movement</th>
                                <th class="text-end">Quantity</th>
                                <th>Note</th>
                                <th>Logged By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentMovements as $mv)
                                @php
                                    $mvTone = match ($mv->type) {
                                        'received', 'transfer_in' => '#6ee7b7',
                                        'transfer_out' => '#fbbf24',
                                        default => '#cbb8b0',
                                    };
                                    $mvSign = $mv->type === 'transfer_out' ? '-' : ($mv->type === 'opening_adjustment' ? ($mv->quantity >= 0 ? '+' : '') : '+');
                                @endphp
                                <tr>
                                    <td class="text-nowrap" style="color:#b8a29a;">{{ $mv->created_at?->timezone('Asia/Qatar')->format('d M, h:i A') }}</td>
                                    <td class="fw-semibold">{{ $mv->item?->name ?? 'Item' }}</td>
                                    <td>
                                        <span style="color:{{ $mvTone }}; font-weight:600;">{{ $mv->typeLabel() }}</span>
                                        @if ($mv->counterparty_branch)
                                            <span class="small" style="color:#9e8780;">
                                                ({{ $mv->type === 'transfer_out' ? 'to' : 'from' }} {{ \App\Models\StockWeek::branchLabel($mv->counterparty_branch) }})
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold" style="color:{{ $mvTone }};">
                                        {{ $mvSign }}{{ \App\Models\StockWeekEntry::formatQty($mv->quantity) }} {{ $mv->item?->unit }}
                                    </td>
                                    <td style="color:#b8a29a;">{{ $mv->reference_note ?? '—' }}</td>
                                    <td style="color:#cbb8b0;">{{ $mv->creator?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-4 text-center small" style="color:#9e8780;">No deliveries or branch transfers logged for this week yet.</div>
            @endif
        </div>

    {{-- ===================================================================== --}}
    {{-- TAB 2: NEED TO ORDER                                                  --}}
    {{-- ===================================================================== --}}
    @elseif ($activeTab === 'order')
        <div class="row g-3">
            @foreach ($orderListByBranch as $obKey => $obData)
                <div class="col-12">
                    <div class="stk-card">
                        <div class="stk-card-head">
                            <div>
                                <h6 class="mb-0 fw-bold d-flex align-items-center gap-2" style="color:#f8efe6;">
                                    <i class="bx bx-store" style="color:#d98f83;"></i>
                                    <span>{{ $obData['branch_label'] }} &mdash; Need to Order</span>
                                    <span class="stk-pill stk-num" style="background:{{ count($obData['rows']) > 0 ? 'rgba(251,113,133,0.14)' : 'rgba(52,211,153,0.14)' }}; border:1px solid {{ count($obData['rows']) > 0 ? 'rgba(251,113,133,0.34)' : 'rgba(52,211,153,0.34)' }}; color:{{ count($obData['rows']) > 0 ? '#fda4af' : '#6ee7b7' }};">
                                        {{ count($obData['rows']) }} {{ Str::plural('item', count($obData['rows'])) }} below Par
                                    </span>
                                </h6>
                                <span class="small" style="color:#9e8780;">Audit Week: {{ $obData['week']->rangeLabel() }}</span>
                            </div>

                            <button type="button"
                                class="stk-btn-primary js-copy-whatsapp"
                                data-whatsapp="{{ $obData['whatsapp_text'] }}">
                                <i class="bx bxl-whatsapp fs-5"></i>
                                <span>Copy WhatsApp Order List</span>
                            </button>
                        </div>

                        @if (!empty($obData['rows']))
                            <div class="table-responsive">
                                <table class="stk-table stk-num">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Type</th>
                                            <th>Unit</th>
                                            <th class="text-end">Current Qty</th>
                                            <th class="text-end">Par Level</th>
                                            <th class="text-end">Order Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($obData['rows'] as $oRow)
                                            @php $oItem = $oRow['item']; @endphp
                                            <tr>
                                                <td class="fw-semibold">{{ $oItem->name }}</td>
                                                <td>
                                                    <span style="color:{{ $oItem->item_type === 'retail' ? '#8aa6ab' : '#e79a91' }}; font-weight:600; font-size:12px;">
                                                        {{ $oItem->typeLabel() }}
                                                    </span>
                                                </td>
                                                <td style="color:#b8a29a;">{{ $oItem->unit }}</td>
                                                <td class="text-end">{{ \App\Models\StockWeekEntry::formatQty($oRow['current_qty']) }}</td>
                                                <td class="text-end" style="color:#cbb8b0;">{{ \App\Models\StockWeekEntry::formatQty($oRow['par_level']) }}</td>
                                                <td class="text-end">
                                                    <span class="stk-pill" style="background:rgba(217,143,131,0.16); border:1px solid rgba(217,143,131,0.38); color:#f8efe6;">
                                                        Order {{ \App\Models\StockWeekEntry::formatQty($oRow['order_qty']) }} {{ $oItem->unit }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="py-4 text-center small" style="color:#6ee7b7;">
                                <i class="bx bx-check-circle me-1"></i>
                                All active items in {{ $obData['branch_label'] }} are currently at or above Par Level.
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

    {{-- ===================================================================== --}}
    {{-- TAB 3: STOCK CATALOG & PAR LEVELS                                     --}}
    {{-- ===================================================================== --}}
    @else
        <div class="stk-card">
            <div class="stk-card-head">
                <div>
                    <h6 class="mb-0 fw-bold" style="color:#f8efe6;"><i class="bx bx-package me-1" style="color:#d98f83;"></i> Stock Catalog &amp; Branch Par Levels</h6>
                    <span class="small" style="color:#9e8780;">Backbar salon supplies &amp; auto-synced Retail Products from the Products module.</span>
                </div>
                @if ($canManageCatalog)
                    <button type="button" class="stk-btn-primary" data-bs-toggle="modal" data-bs-target="#addBackbarItemModal">
                        <i class="bx bx-plus"></i>
                        <span>Add Backbar Item</span>
                    </button>
                @endif
            </div>

            <div class="table-responsive">
                <table class="stk-table stk-num">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th class="text-end">Old Airport Par</th>
                            <th class="text-end">Al Wakrah Par</th>
                            <th>Status</th>
                            @if ($canManageCatalog)
                                <th class="text-end">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($catalogItems as $cItem)
                            @php $cIsRetail = $cItem->item_type === \App\Models\StockItem::TYPE_RETAIL; @endphp
                            <tr style="{{ !$cItem->is_active ? 'opacity:0.55;' : '' }}">
                                <td>
                                    <div class="fw-semibold">{{ $cItem->name }}</div>
                                    @if ($cIsRetail && $cItem->product?->sku)
                                        <div class="small" style="color:#8f7a73;">SKU: {{ $cItem->product->sku }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($cIsRetail)
                                        <span class="stk-pill" style="background:rgba(138,166,171,0.14); border:1px solid rgba(138,166,171,0.3); color:#8aa6ab;">
                                            <i class="bx bx-sync"></i> Retail Synced
                                        </span>
                                    @else
                                        <span class="stk-pill" style="background:rgba(217,143,131,0.14); border:1px solid rgba(217,143,131,0.3); color:#e79a91;">
                                            Backbar Supply
                                        </span>
                                    @endif
                                </td>
                                <td style="color:#cbb8b0;">{{ $cItem->categoryLabel() }}</td>
                                <td style="color:#cbb8b0;">{{ $cItem->unit }}</td>

                                @if ($canManageCatalog)
                                    <td class="text-end">
                                        <input type="number" step="0.01" min="0" form="parForm-{{ $cItem->id }}" name="par_level_old_airport"
                                            value="{{ \App\Models\StockWeekEntry::formatQty($cItem->par_level_old_airport) }}"
                                            class="form-control form-control-sm stk-input stk-qty-input ms-auto">
                                    </td>
                                    <td class="text-end">
                                        <input type="number" step="0.01" min="0" form="parForm-{{ $cItem->id }}" name="par_level_al_wakrah"
                                            value="{{ \App\Models\StockWeekEntry::formatQty($cItem->par_level_al_wakrah) }}"
                                            class="form-control form-control-sm stk-input stk-qty-input ms-auto">
                                    </td>
                                @else
                                    <td class="text-end">{{ \App\Models\StockWeekEntry::formatQty($cItem->par_level_old_airport) }}</td>
                                    <td class="text-end">{{ \App\Models\StockWeekEntry::formatQty($cItem->par_level_al_wakrah) }}</td>
                                @endif

                                <td>
                                    @if ($cItem->is_active)
                                        <span class="stk-pill" style="background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.3); color:#6ee7b7;">Active</span>
                                    @else
                                        <span class="stk-pill" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:#a8938b;">Archived</span>
                                    @endif
                                </td>

                                @if ($canManageCatalog)
                                    <td class="text-end text-nowrap">
                                        <form id="parForm-{{ $cItem->id }}" method="POST" action="{{ route('stock.items.update', $cItem) }}" class="d-inline">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-sm py-1 px-2" style="background:rgba(217,143,131,0.16); border:1px solid rgba(217,143,131,0.34); color:#f8efe6; font-size:12px; border-radius:8px;">
                                                Save Par
                                            </button>
                                        </form>
                                        @if (!$cIsRetail && $cItem->is_active)
                                            <form method="POST" action="{{ route('stock.items.destroy', $cItem) }}" class="d-inline ms-1"
                                                  onsubmit="return confirm('Archive {{ addslashes($cItem->name) }} from future weekly sheets?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size:12px; border-radius:8px;" title="Archive">
                                                    <i class="bx bx-archive-in"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManageCatalog ? 8 : 7 }}" class="text-center py-5" style="color:#9e8780;">
                                    No stock items in catalog yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

{{-- MODAL 1: LOG RESTOCK (+ RECEIVED) --}}
@if ($canLogRestock)
<div class="modal fade stk-modal" id="logRestockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.movements.store') }}">
                @csrf
                <input type="hidden" name="week_start_date" value="{{ $bounds['start_str'] }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Log Stock Restock (+ Received)</h5>
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
                            <label class="form-label small text-muted">Stock Item</label>
                            <select name="stock_item_id" class="form-select stk-select" required>
                                <option value="">Select item...</option>
                                @foreach ($catalogItems->where('is_active', true) as $ci)
                                    <option value="{{ $ci->id }}">{{ $ci->name }} ({{ $ci->unit }} · {{ $ci->typeLabel() }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small text-muted">Quantity Received</label>
                            <input type="number" name="quantity" step="0.01" min="0.01" class="form-control stk-input stk-num" required placeholder="e.g. 6">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small text-muted">Supplier / Invoice Note (optional)</label>
                            <input type="text" name="reference_note" class="form-control stk-input" placeholder="e.g. Supplier delivery #412">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="stk-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="stk-btn-primary">Log Delivery</button>
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
                    <h5 class="modal-title fw-bold">Transfer Stock Between Branches</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">From Branch</label>
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
                            <label class="form-label small text-muted">To Branch</label>
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
                            <label class="form-label small text-muted">Stock Item</label>
                            <select name="stock_item_id" class="form-select stk-select" required>
                                <option value="">Select item...</option>
                                @foreach ($catalogItems->where('is_active', true) as $ci)
                                    <option value="{{ $ci->id }}">{{ $ci->name }} ({{ $ci->unit }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small text-muted">Quantity</label>
                            <input type="number" name="quantity" step="0.01" min="0.01" class="form-control stk-input stk-num" required placeholder="e.g. 2">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small text-muted">Transfer Note (optional)</label>
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

{{-- MODAL 3: ADD BACKBAR ITEM (ADMIN ONLY) --}}
@if ($canManageCatalog)
<div class="modal fade stk-modal" id="addBackbarItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.items.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add Backbar Supply Item</h5>
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
                            <label class="form-label small text-muted">Old Airport Par Level</label>
                            <input type="number" name="par_level_old_airport" step="0.01" min="0" value="3" class="form-control stk-input stk-num">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Al Wakrah Par Level</label>
                            <input type="number" name="par_level_al_wakrah" step="0.01" min="0" value="3" class="form-control stk-input stk-num">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="stk-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="stk-btn-primary">Add to Catalog</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL 4: ADMIN REQUEST REVISION --}}
@if ($isAdmin && !$stockWeek->isApproved())
<div class="modal fade stk-modal" id="requestRevisionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('stock.weeks.request-revision', $stockWeek) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Request Count Revision</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small" style="color:#cbb8b0;">
                        This unlocks {{ $branchLabel }}&rsquo;s sheet ({{ $bounds['label'] }}) so the Salon Manager can recount and resubmit.
                    </p>
                    <label class="form-label small text-muted">Note to Salon Manager <span style="color:#fda4af;">*</span></label>
                    <textarea name="admin_note" rows="3" class="form-control stk-input" required placeholder="e.g. Please recount Keratin Serum bottles and verify Tuesday restock invoice..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="stk-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm px-4 py-2 fw-bold" style="background:rgba(251,113,133,0.18); border:1px solid rgba(251,113,133,0.44); color:#fda4af; border-radius:999px;">
                        Unlock &amp; Request Revision
                    </button>
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
            }, 3200);
        }

        function fmtQty(n) {
            const r = Math.round(n * 100) / 100;
            return Number.isInteger(r) ? String(r) : r.toFixed(2).replace(/\.?0+$/, '');
        }

        // Live Used & Need-to-Order preview while typing Friday Closing / Opening counts
        function recalcRow(row) {
            const openingInput = row.querySelector('.js-stk-opening');
            const openingStatic = row.querySelector('.js-stk-opening-static');
            const closingInput = row.querySelector('.js-stk-closing');
            const usedCell = row.querySelector('.js-stk-used-cell');
            const orderCell = row.querySelector('.js-stk-order-cell');
            if (!closingInput || !usedCell) return;

            const opening = openingInput
                ? (parseFloat(openingInput.value) || 0)
                : (openingStatic ? (parseFloat(openingStatic.dataset.val) || 0) : 0);
            const received = parseFloat(row.dataset.received) || 0;
            const transfer = parseFloat(row.dataset.transfer) || 0;
            const par = parseFloat(row.dataset.par) || 0;
            const retailSold = parseFloat(row.dataset.retailSold) || 0;
            const isRetail = row.dataset.isRetail === '1';

            const rawClosing = closingInput.value.trim();
            if (rawClosing === '') {
                usedCell.innerHTML = '<span style="color:#8f7a73;">&mdash;</span>';
                return;
            }

            const closing = parseFloat(rawClosing);
            if (Number.isNaN(closing)) return;

            const used = Math.round((opening + received + transfer - closing) * 100) / 100;
            const negUsage = used < -0.005;
            const shrinkage = isRetail && (used - retailSold) > 0.005;
            const color = (negUsage || shrinkage) ? '#fda4af' : '#f8efe6';

            let warnHtml = '';
            if (negUsage) {
                warnHtml = '<div class="small" style="color:#fda4af; font-size:10.5px;">Check count</div>';
            } else if (shrinkage) {
                warnHtml = '<div class="small" style="color:#fbbf24; font-size:10.5px;">Shrinkage alert</div>';
            }

            usedCell.innerHTML = `<span class="fw-semibold" style="color:${color};">${fmtQty(used)}</span>${warnHtml}`;

            if (orderCell && par > 0) {
                const need = Math.round(Math.max(0, par - closing) * 100) / 100;
                if (need > 0) {
                    orderCell.innerHTML = `<span class="stk-pill" style="background:rgba(251,113,133,0.14); border:1px solid rgba(251,113,133,0.34); color:#fda4af;">Order ${fmtQty(need)}</span>`;
                } else {
                    orderCell.innerHTML = '<span style="color:#6ee7b7; font-size:12px; font-weight:600;">OK</span>';
                }
            }
        }

        document.querySelectorAll('.stk-entry-row').forEach(row => {
            row.querySelector('.js-stk-opening')?.addEventListener('input', () => recalcRow(row));
            row.querySelector('.js-stk-closing')?.addEventListener('input', () => recalcRow(row));
        });

        // Client-side Type / Category / Search filtering on Weekly Audit table
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
