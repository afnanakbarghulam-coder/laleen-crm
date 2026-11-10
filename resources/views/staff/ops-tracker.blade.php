@extends('layouts.app')

@section('title', 'Ops & Action Tracker — Staff Management')

<style>
    .ops-tracker-wrap {
        --ops-bg-card: #241c1c;
        --ops-bg-elevated: #2c2222;
        --ops-bg-drawer: #1a1414;
        --ops-border: rgba(217, 143, 131, 0.16);
        --ops-border-strong: rgba(217, 143, 131, 0.3);
        --ops-ink: #f3e8e4;
        --ops-body: #dfd0ca;
        --ops-muted: #b8a099;
        --ops-accent: #d98f83;
    }

    .ops-owner-badge {
        background: #7f1d1d !important;
        color: #ffffff !important;
        font-weight: 700;
        font-size: 11px;
        padding: 3px 9px;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.22);
    }

    .ops-score-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 700;
    }

    .ops-score-green {
        background: rgba(16, 185, 129, 0.16);
        color: #6ee7b7;
        border: 1px solid rgba(16, 185, 129, 0.45);
    }

    .ops-score-amber {
        background: rgba(245, 158, 11, 0.16);
        color: #fcd34d;
        border: 1px solid rgba(245, 158, 11, 0.45);
    }

    .ops-score-red {
        background: rgba(239, 68, 68, 0.16);
        color: #fca5a5;
        border: 1px solid rgba(239, 68, 68, 0.45);
    }

    /* Primary Rose-Gold + Add Task Pill Button */
    .btn-ops-add-task {
        background: linear-gradient(135deg, #e09b8f 0%, #c97c70 100%);
        color: #1a1210 !important;
        font-weight: 700;
        font-size: 13px;
        padding: 6px 16px;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.18);
        box-shadow: 0 4px 14px rgba(217, 143, 131, 0.25);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
    }

    .btn-ops-add-task:hover {
        filter: brightness(1.06);
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(217, 143, 131, 0.35);
    }

    .ops-filter-pill {
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 600;
        padding: 5px 13px;
        border: 1px solid var(--ops-border);
        background: rgba(36, 28, 28, 0.65);
        color: var(--ops-body);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all .15s ease;
    }

    .ops-filter-pill:hover {
        border-color: var(--ops-accent);
        color: var(--ops-ink);
    }

    .ops-filter-pill.active {
        background: var(--ops-accent);
        border-color: var(--ops-accent);
        color: #1e1614;
        font-weight: 700;
    }

    /* Compact Staff Access Popover Dropdown */
    .ops-staff-dropdown-wrap {
        position: relative;
        display: inline-flex;
        align-items: center;
    }

    .ops-staff-dropdown-trigger {
        cursor: pointer;
        user-select: none;
    }

    .ops-staff-clear-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 17px;
        height: 17px;
        border-radius: 50%;
        background: rgba(26, 18, 16, 0.22);
        color: #1e1614 !important;
        font-size: 10.5px;
        font-weight: 800;
        text-decoration: none;
        line-height: 1;
        margin-left: 2px;
        transition: background-color .15s ease;
    }

    .ops-staff-clear-btn:hover {
        background: rgba(26, 18, 16, 0.4);
    }

    .ops-staff-dropdown-menu {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        min-width: 245px;
        max-height: 300px;
        overflow-y: auto;
        background: #1a1414;
        border: 1px solid rgba(217, 143, 131, 0.34);
        border-radius: 12px;
        padding: 6px;
        box-shadow: 0 14px 36px rgba(0, 0, 0, 0.65);
        z-index: 1075;
        display: none;
    }

    .ops-staff-dropdown-menu.open {
        display: block;
    }

    .ops-staff-dropdown-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 7px 11px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--ops-ink);
        text-decoration: none;
        transition: background-color .14s ease, color .14s ease;
    }

    .ops-staff-dropdown-item:hover {
        background: rgba(217, 143, 131, 0.14);
        color: #ffffff;
    }

    .ops-staff-dropdown-item.active {
        background: rgba(217, 143, 131, 0.24);
        color: #ffffff;
        font-weight: 700;
    }

    .ops-staff-count-badge {
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        background: rgba(217, 143, 131, 0.16);
        color: #f3e8e4;
        border: 1px solid rgba(217, 143, 131, 0.28);
        white-space: nowrap;
    }

    /* Locked Read-Only Assignee Pill Badges on Rows */
    .ops-locked-assignee-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11.5px;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 999px;
        background: rgba(217, 143, 131, 0.12);
        color: var(--ops-ink);
        border: 1px solid rgba(217, 143, 131, 0.26);
        white-space: nowrap;
    }

    .ops-area-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 2px 7px;
        border-radius: 6px;
        background: rgba(217, 143, 131, 0.1);
        color: #e7b8b0;
        border: 1px solid rgba(217, 143, 131, 0.2);
        white-space: nowrap;
    }

    .ops-carryover-tag {
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 999px;
        background: rgba(245, 158, 11, 0.15);
        color: #fcd34d;
        border: 1px solid rgba(245, 158, 11, 0.35);
        white-space: nowrap;
    }

    .ops-overdue-badge {
        font-size: 11.5px;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 999px;
        background: #7f1d1d;
        color: #ffffff;
        border: 1px solid rgba(252, 165, 165, 0.4);
        white-space: nowrap;
    }

    .ops-timeleft-badge {
        font-size: 11.5px;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.06);
        color: var(--ops-muted);
        border: 1px solid rgba(255, 255, 255, 0.1);
        white-space: nowrap;
    }

    .ops-done-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11.5px;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 999px;
        background: rgba(16, 185, 129, 0.15);
        color: #6ee7b7;
        border: 1px solid rgba(16, 185, 129, 0.35);
        white-space: nowrap;
    }

    /* Ultra-minimalistic 2-line closed row styles */
    .ops-compact-table > tbody > tr > td {
        padding-top: 9px;
        padding-bottom: 9px;
        vertical-align: middle;
    }

    .ops-inline-pencil-btn {
        border: none;
        background: transparent;
        color: var(--ops-muted);
        opacity: 0.65;
        font-size: 12.5px;
        line-height: 1;
        padding: 2px 5px;
        border-radius: 4px;
        cursor: pointer;
        transition: opacity .15s ease, color .15s ease, background-color .15s ease;
    }

    .ops-inline-pencil-btn:hover {
        opacity: 1;
        color: var(--ops-ink);
        background: rgba(217, 143, 131, 0.16);
    }

    .ops-meta-line {
        font-size: 12px;
        color: var(--ops-muted);
        margin-top: 4px;
        line-height: 1.3;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }

    .ops-meta-note {
        color: var(--ops-body);
    }

    .ops-status-circle {
        width: 20px;
        height: 20px;
        border-radius: 5px;
        border: 1.5px solid rgba(217, 143, 131, 0.45);
        background: rgba(20, 16, 14, 0.55);
        color: transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 800;
        cursor: pointer;
        flex-shrink: 0;
        margin-top: 1px;
        padding: 0;
        transition: all .15s ease;
    }

    .ops-status-circle:hover {
        border-color: #10b981;
        background: rgba(16, 185, 129, 0.14);
        color: #6ee7b7;
    }

    .ops-status-circle.is-done {
        border-color: #10b981;
        background: rgba(16, 185, 129, 0.22);
        color: #6ee7b7;
    }

    .ops-status-circle.is-done:hover {
        border-color: #f59e0b;
        background: rgba(245, 158, 11, 0.18);
        color: #fcd34d;
    }

    .ops-status-circle:disabled,
    .ops-status-circle.is-disabled {
        opacity: 0.38;
        cursor: not-allowed;
        border-color: rgba(217, 143, 131, 0.25);
        background: rgba(20, 16, 14, 0.35);
    }

    .ops-assigned-only-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        padding: 4px 11px;
        background: rgba(255, 255, 255, 0.04);
        color: var(--ops-muted);
        border: 1px solid rgba(255, 255, 255, 0.1);
        opacity: 0.72;
        cursor: not-allowed;
        white-space: nowrap;
    }

    .btn-ops-mark-done {
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 12px;
        background: rgba(16, 185, 129, 0.14);
        color: #6ee7b7;
        border: 1px solid rgba(16, 185, 129, 0.4);
        white-space: nowrap;
        transition: all .15s ease;
    }

    .btn-ops-mark-done:hover {
        background: #10b981;
        border-color: #10b981;
        color: #111815;
    }

    .btn-ops-reopen {
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        padding: 4px 12px;
        background: rgba(245, 158, 11, 0.14);
        color: #fcd34d;
        border: 1px solid rgba(245, 158, 11, 0.4);
        white-space: nowrap;
        transition: all .15s ease;
    }

    .btn-ops-reopen:hover {
        background: #f59e0b;
        border-color: #f59e0b;
        color: #1c150c;
    }

    .ops-row-completed {
        opacity: 0.78;
        background: rgba(20, 16, 14, 0.3);
    }

    /* ================= RIGHT-HAND SLIDE-OVER DRAWER ================= */
    .ops-drawer-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(10, 7, 7, 0.62);
        backdrop-filter: blur(3px);
        z-index: 1085;
        opacity: 0;
        pointer-events: none;
        transition: opacity .22s ease;
    }

    .ops-drawer-backdrop.open {
        opacity: 1;
        pointer-events: auto;
    }

    .ops-slide-drawer {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: 420px;
        max-width: 100vw;
        background: var(--ops-bg-drawer);
        border-left: 1px solid rgba(217, 143, 131, 0.28);
        box-shadow: -16px 0 48px rgba(0, 0, 0, 0.65);
        z-index: 1090;
        display: flex;
        flex-direction: column;
        transform: translateX(100%);
        transition: transform .24s cubic-bezier(0.22, 1, 0.36, 1);
        color: var(--ops-ink);
    }

    .ops-slide-drawer.open {
        transform: translateX(0);
    }

    .ops-drawer-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 22px 14px;
        border-bottom: 1px solid rgba(217, 143, 131, 0.16);
        background: #241c1c;
    }

    .ops-drawer-title {
        font-size: 15.5px;
        font-weight: 800;
        color: var(--ops-ink);
        margin: 0;
    }

    .ops-drawer-close {
        border: 1px solid rgba(217, 143, 131, 0.22);
        background: rgba(255, 255, 255, 0.04);
        color: var(--ops-muted);
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        cursor: pointer;
        transition: all .15s ease;
    }

    .ops-drawer-close:hover {
        border-color: var(--ops-accent);
        color: var(--ops-ink);
        background: rgba(217, 143, 131, 0.14);
    }

    .ops-drawer-body {
        flex: 1;
        overflow-y: auto;
        padding: 18px 22px;
    }

    .ops-drawer-footer {
        padding: 14px 22px 18px;
        border-top: 1px solid rgba(217, 143, 131, 0.16);
        background: #241c1c;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ops-drawer-field-label {
        font-size: 11.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--ops-muted);
        margin-bottom: 6px;
        display: block;
    }

    .ops-drawer-input,
    .ops-drawer-textarea {
        width: 100%;
        background: #241c1c;
        border: 1px solid rgba(217, 143, 131, 0.26);
        border-radius: 10px;
        padding: 9px 12px;
        font-size: 13px;
        color: var(--ops-ink);
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .ops-drawer-input:focus,
    .ops-drawer-textarea:focus {
        outline: none;
        border-color: var(--ops-accent);
        box-shadow: 0 0 0 3px rgba(217, 143, 131, 0.15);
    }

    /* Selectable Pill Chips for Area & Timeframe */
    .ops-chip-group {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
    }

    .ops-chip-option {
        position: relative;
        margin: 0;
        cursor: pointer;
    }

    .ops-chip-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .ops-chip-pill {
        display: inline-block;
        padding: 6px 13px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: #241c1c;
        color: var(--ops-body);
        border: 1px solid rgba(217, 143, 131, 0.22);
        transition: all .15s ease;
        user-select: none;
    }

    .ops-chip-option:hover .ops-chip-pill {
        border-color: rgba(217, 143, 131, 0.45);
        color: var(--ops-ink);
    }

    .ops-chip-option input[type="radio"]:checked + .ops-chip-pill {
        background: var(--ops-accent);
        border-color: var(--ops-accent);
        color: #1a1210;
        font-weight: 700;
        box-shadow: 0 3px 10px rgba(217, 143, 131, 0.25);
    }

    /* Scrollable Dark Staff Access Checklist in Drawer */
    .ops-drawer-staff-list {
        background: #241c1c;
        border: 1px solid rgba(217, 143, 131, 0.24);
        border-radius: 10px;
        max-height: 210px;
        overflow-y: auto;
        padding: 6px;
    }

    .ops-drawer-staff-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 7px 10px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        color: var(--ops-ink);
        user-select: none;
        transition: background-color .12s ease;
        margin: 0;
    }

    .ops-drawer-staff-row:hover {
        background: rgba(217, 143, 131, 0.12);
    }

    .ops-drawer-staff-row input[type="checkbox"] {
        accent-color: var(--ops-accent);
        width: 16px;
        height: 16px;
        cursor: pointer;
        flex-shrink: 0;
    }

    .ops-role-chip {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 999px;
        background: rgba(217, 143, 131, 0.12);
        color: var(--ops-muted);
        white-space: nowrap;
    }

    .ops-locked-title-box {
        background: #241c1c;
        border: 1px solid rgba(217, 143, 131, 0.24);
        border-left: 3px solid var(--ops-accent);
        border-radius: 10px;
        padding: 10px 13px;
        font-size: 13.5px;
        font-weight: 700;
        color: var(--ops-ink);
    }

    .ops-locked-timeframe-box {
        background: rgba(20, 16, 14, 0.55);
        border: 1px solid rgba(217, 143, 131, 0.2);
        border-radius: 10px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 600;
        color: var(--ops-muted);
    }

    .btn-ops-drawer-save {
        flex: 1;
        background: linear-gradient(135deg, #e09b8f 0%, #c97c70 100%);
        color: #1a1210 !important;
        font-weight: 700;
        font-size: 13px;
        padding: 8px 16px;
        border-radius: 999px;
        border: none;
        box-shadow: 0 4px 14px rgba(217, 143, 131, 0.25);
        transition: filter .15s ease;
    }

    .btn-ops-drawer-save:hover {
        filter: brightness(1.06);
    }

    /* Dark Luxury Confirmation Modal */
    .ops-confirm-modal .modal-content {
        background: #241c1c;
        border: 1px solid rgba(217, 143, 131, 0.32);
        border-radius: 14px;
        color: #f3e8e4;
        box-shadow: 0 20px 48px rgba(0, 0, 0, 0.55);
    }

    .ops-confirm-modal .modal-header {
        border-bottom: 1px solid rgba(217, 143, 131, 0.14);
        padding: 16px 20px 12px;
    }

    .ops-confirm-modal .modal-body {
        padding: 16px 20px;
        font-size: 13.5px;
        color: #dfd0ca;
    }

    .ops-confirm-modal .modal-footer {
        border-top: 1px solid rgba(217, 143, 131, 0.14);
        padding: 12px 20px 16px;
    }

    .ops-confirm-task-box {
        background: rgba(20, 16, 14, 0.55);
        border: 1px solid rgba(217, 143, 131, 0.22);
        border-radius: 8px;
        padding: 10px 12px;
        font-weight: 700;
        color: #f3e8e4;
        margin-bottom: 10px;
    }
</style>

@section('content')
@php
    $scoreColorClass = $completionRate >= 90
        ? 'ops-score-green'
        : ($completionRate >= 75 ? 'ops-score-amber' : 'ops-score-red');
@endphp

<div class="ops-tracker-wrap">
    {{-- Top Staff Management Sub-Nav Bar --}}
    <div class="d-flex gap-2 mb-3 flex-wrap align-items-center">
        <a href="{{ route('staffs.index') }}" class="btn btn-sm btn-outline-secondary">
            👥 Staff Directory &amp; Payroll
        </a>
        <a href="{{ route('shifts.index') }}" class="btn btn-sm btn-outline-secondary">
            Scheduled shifts
        </a>
        <a href="{{ route('staff.ops-tracker.index') }}" class="btn btn-sm btn-dark d-inline-flex align-items-center gap-2">
            <span>⚡ Ops &amp; Action Tracker</span>
            @if ($pendingOwnerCount > 0)
                <span class="ops-owner-badge">{{ $pendingOwnerCount }} Pending Owner</span>
            @endif
        </a>
        @if (auth()->user()->isSuperAdmin())
            <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">
                Staff Access
            </a>
        @endif
    </div>

    {{-- Clean Header & Week Switcher --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h4 class="fw-bold mb-0">Ops &amp; Action Tracker</h4>
            <span class="ops-score-pill {{ $scoreColorClass }}">
                Weekly Score: {{ $completedCount }}/{{ $totalCount }} ({{ $completionRate }}%)
            </span>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="{{ route('staff.ops-tracker.index', ['week' => $prevWeek, 'status' => $status, 'mine' => $mine ? 1 : null, 'staff_id' => $selectedStaffId]) }}"
               class="btn btn-sm btn-outline-secondary">
                &#9664; Prev Week
            </a>
            <span class="badge bg-dark border border-secondary px-3 py-2 fw-semibold" style="font-size: 12.5px;">
                Week of {{ $monday->format('d M') }} &ndash; {{ $sunday->format('d M Y') }}
            </span>
            <a href="{{ route('staff.ops-tracker.index', ['week' => $nextWeek, 'status' => $status, 'mine' => $mine ? 1 : null, 'staff_id' => $selectedStaffId]) }}"
               class="btn btn-sm btn-outline-secondary">
                Next Week &#9654;
            </a>
            @if (! $isCurrentWeek)
                <a href="{{ route('staff.ops-tracker.index', ['status' => $status, 'mine' => $mine ? 1 : null, 'staff_id' => $selectedStaffId]) }}"
                   class="btn btn-sm btn-primary">
                    This Week
                </a>
            @endif
        </div>
    </div>

    {{-- Compact 3-Part Filter Bar (Left: Status + Pending Me + Staff Access Popover | Right: + Add Task) --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            {{-- 1. Status Pills --}}
            <a href="{{ route('staff.ops-tracker.index', ['week' => $monday->toDateString(), 'status' => 'open']) }}"
               class="ops-filter-pill {{ $status === 'open' && ! $mine && ! $selectedStaffId ? 'active' : '' }}">
                Open ({{ $openCount }})
            </a>

            <a href="{{ route('staff.ops-tracker.index', ['week' => $monday->toDateString(), 'status' => 'completed']) }}"
               class="ops-filter-pill {{ $status === 'completed' ? 'active' : '' }}">
                ✅ Completed ({{ $completedCount }})
            </a>

            {{-- 2. Pending Me Quick Pill --}}
            <a href="{{ route('staff.ops-tracker.index', ['week' => $monday->toDateString(), 'status' => 'open', 'mine' => 1]) }}"
               class="ops-filter-pill {{ $mine && $status === 'open' ? 'active' : '' }}">
                🔴 Pending Me ({{ $myOpenCount }})
            </a>

            {{-- 3. Custom Dark Staff Access Popover Dropdown --}}
            <div class="ops-staff-dropdown-wrap" id="opsStaffFilterDropdownWrap">
                <div role="button"
                     tabindex="0"
                     id="opsStaffFilterTrigger"
                     class="ops-filter-pill ops-staff-dropdown-trigger {{ $selectedStaffId ? 'active' : '' }}"
                     onclick="toggleOpsStaffFilterMenu(event)">
                    <span>👤 Staff: {{ $selectedStaffName ?? 'All Staff' }} ▾</span>
                    @if ($selectedStaffId)
                        <a href="{{ route('staff.ops-tracker.index', ['week' => $monday->toDateString(), 'status' => 'open']) }}"
                           class="ops-staff-clear-btn"
                           title="Reset to All Staff"
                           onclick="event.stopPropagation();">✕</a>
                    @endif
                </div>

                <div class="ops-staff-dropdown-menu" id="opsStaffFilterMenu">
                    <a href="{{ route('staff.ops-tracker.index', ['week' => $monday->toDateString(), 'status' => 'open']) }}"
                       class="ops-staff-dropdown-item {{ ! $selectedStaffId && ! $mine && $status === 'open' ? 'active' : '' }}">
                        <span>All Staff ({{ $openCount }})</span>
                        <span class="ops-staff-count-badge">{{ $openCount }}</span>
                    </a>
                    @foreach ($assigneeOptions as $staffOption)
                        <a href="{{ route('staff.ops-tracker.index', ['week' => $monday->toDateString(), 'status' => 'open', 'staff_id' => $staffOption['id']]) }}"
                           class="ops-staff-dropdown-item {{ (int) $selectedStaffId === (int) $staffOption['id'] ? 'active' : '' }}">
                            <span class="text-truncate">{{ $staffOption['name'] }}</span>
                            <span class="ops-staff-count-badge" title="{{ $staffOption['name'] }} — {{ $staffOption['open_count'] }}">{{ $staffOption['name'] }} — {{ $staffOption['open_count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        @moduleEdit('staff_management')
            <button type="button"
                    class="btn-ops-add-task"
                    id="opsOpenCreateDrawerBtn"
                    onclick="openOpsCreateDrawer()">
                <i class="bx bx-plus"></i> Add Task
            </button>
        @endmoduleEdit
    </div>

    {{-- Ultra-Minimalistic 2-Line Checklist Table --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table team-table ops-compact-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Task &amp; Note</th>
                        <th style="width: 230px;">Pending With</th>
                        <th style="width: 230px;">Deadline / Status</th>
                        <th class="text-end" style="width: 185px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $assignedAtStamp = ($item->ball_assigned_at ?? $item->created_at)
                                ? ($item->ball_assigned_at ?? $item->created_at)->format('d M Y, h:i A')
                                : now()->format('d M Y, h:i A');

                            $completedAtStamp = ($item->completed_at ?? $item->updated_at)
                                ? ($item->completed_at ?? $item->updated_at)->format('d M Y, h:i A')
                                : now()->format('d M Y, h:i A');

                            $currentAssigneeIds = is_array($item->assigned_user_ids)
                                ? array_values(array_map('intval', $item->assigned_user_ids))
                                : [];

                            $itemSlaHours = (int) ($item->sla_hours ?: 48);
                            $itemTimeframeLabel = $slaOptions[$itemSlaHours] ?? ($itemSlaHours . 'h');

                            $editPayload = [
                                'id' => $item->id,
                                'title' => $item->title,
                                'scope' => $item->scope,
                                'notes' => (string) ($item->notes ?? ''),
                                'assigned_user_ids' => $currentAssigneeIds,
                                'assigned_names' => $item->assignee_list,
                                'sla_hours' => $itemSlaHours,
                                'timeframe_label' => $itemTimeframeLabel,
                                'update_url' => route('staff.ops-tracker.update', $item),
                            ];
                        @endphp
                        @php
                            $canCompleteTask = $item->canBeCompletedBy(auth()->user());
                            $isAdminViewer = \App\Models\OpsActionItem::isAdminOrOwner(auth()->user());
                        @endphp
                        <tr class="{{ $item->is_completed ? 'ops-row-completed' : '' }}"
                            data-ops-item-id="{{ $item->id }}"
                            data-sla-hours="{{ $itemSlaHours }}">
                            {{-- Col 1: Ultra-Minimalistic 2-Line Task & Note --}}
                            <td>
                                <div class="d-flex align-items-start gap-2">
                                    @if ($canCompleteTask)
                                        <form method="POST"
                                              action="{{ route('staff.ops-tracker.toggle', $item) }}"
                                              id="opsToggleForm-{{ $item->id }}"
                                              class="m-0">
                                            @csrf
                                            <button type="button"
                                                    class="ops-status-circle {{ $item->is_completed ? 'is-done' : '' }}"
                                                    onclick="openOpsConfirmModal({{ $item->id }}, @js($item->title), @js($item->is_completed ? 'reopen' : 'complete'))"
                                                    title="{{ $item->is_completed ? 'Move Back to Pending' : 'Mark Task as Completed' }}">
                                                ✓
                                            </button>
                                        </form>
                                    @else
                                        <button type="button"
                                                class="ops-status-circle is-disabled {{ $item->is_completed ? 'is-done' : '' }}"
                                                disabled
                                                title="Only Admin or assigned staff can complete this task">
                                            ✓
                                        </button>
                                    @endif

                                    <div class="flex-grow-1">
                                        {{-- Line 1: Task Title + Area Pill + Carryover + Tiny Inline Pencil Icon --}}
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="fw-semibold {{ $item->is_completed ? 'text-decoration-line-through text-muted' : '' }}" style="font-size: 13.5px; line-height: 1.25;">
                                                {{ $item->title }}
                                            </span>
                                            <span class="ops-area-badge">{{ $item->scope }}</span>
                                            @if (! $item->is_completed && $item->carryover_weeks > 0)
                                                <span class="ops-carryover-tag">Carryover ({{ $item->carryover_weeks }}w)</span>
                                            @endif
                                            @moduleEdit('staff_management')
                                                <button type="button"
                                                        class="ops-inline-pencil-btn"
                                                        data-sla-hours="{{ $itemSlaHours }}"
                                                        onclick="openOpsEditDrawer(@js($editPayload))"
                                                        title="Edit note &amp; assignees">
                                                    &#9998;
                                                </button>
                                            @endmoduleEdit
                                        </div>

                                        {{-- Line 2: Compact Muted Metadata Row (Note + Assigned Timestamp + Completed Timestamp) --}}
                                        <div class="ops-meta-line">
                                            @if ($item->notes)
                                                <span class="ops-meta-note">{{ $item->notes }}</span>
                                                <span>&bull;</span>
                                            @endif
                                            <span>🕒 Assigned: {{ $assignedAtStamp }}</span>
                                            @if ($item->is_completed)
                                                <span>&bull;</span>
                                                <span class="text-success fw-semibold">
                                                    ✓ Completed: {{ $completedAtStamp }}{{ $item->completed_by ? ' (' . $item->completed_by . ')' : '' }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Col 2: Read-Only Assignee Pill Badges (Vertically Centered) --}}
                            <td class="align-middle">
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    @foreach ($item->assignee_list as $assigneeName)
                                        <span class="ops-locked-assignee-pill">
                                            👤 {{ $assigneeName }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Col 3: Deadline / Status Pill (Vertically Centered) --}}
                            <td class="align-middle">
                                @if (! $item->is_completed)
                                    @if ($item->is_overdue)
                                        <span class="ops-overdue-badge">🚨 Overdue</span>
                                    @else
                                        <span class="ops-timeleft-badge">⏳ {{ $item->hours_remaining }}h left</span>
                                    @endif
                                @else
                                    <span class="ops-done-badge">
                                        ✓ Completed: {{ $completedAtStamp }}
                                    </span>
                                @endif
                            </td>

                            {{-- Col 4: Explicit Action Button (Mark Done / Back to Pending / Assigned Only Lock) + Admin-Only Delete --}}
                            <td class="text-end align-middle">
                                <div class="d-inline-flex align-items-center justify-content-end gap-2">
                                    @if ($canCompleteTask)
                                        @if (! $item->is_completed)
                                            <button type="button"
                                                    class="btn btn-sm btn-ops-mark-done"
                                                    onclick="openOpsConfirmModal({{ $item->id }}, @js($item->title), 'complete')">
                                                ✓ Mark Done
                                            </button>
                                        @else
                                            <button type="button"
                                                    class="btn btn-sm btn-ops-reopen"
                                                    onclick="openOpsConfirmModal({{ $item->id }}, @js($item->title), 'reopen')">
                                                ↺ Back to Pending
                                            </button>
                                        @endif
                                    @else
                                        <span class="ops-assigned-only-pill"
                                              title="Only Admin or assigned staff can complete this task">
                                            🔒 Assigned Only
                                        </span>
                                    @endif

                                    @if ($isAdminViewer)
                                        <form method="POST"
                                              action="{{ route('staff.ops-tracker.destroy', $item) }}"
                                              id="opsDeleteForm-{{ $item->id }}"
                                              class="m-0 d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button"
                                                    class="btn btn-sm btn-link text-danger p-1"
                                                    onclick="openOpsConfirmModal({{ $item->id }}, @js($item->title), 'delete')"
                                                    title="Delete task">
                                                <i class="bx bx-trash fs-5"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">
                                No tasks in this view. Click <strong>+ Add Task</strong> above to get started.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================= RIGHT-SIDE SLIDE-OVER DRAWER (Create + Edit Note/Staff) ================= --}}
    @moduleEdit('staff_management')
        <div class="ops-drawer-backdrop" id="opsDrawerBackdrop" onclick="closeOpsTaskDrawer()"></div>

        {{-- 1. CREATE MODE DRAWER (+ Add Task) --}}
        <aside class="ops-slide-drawer" id="opsCreateDrawer" aria-hidden="true">
            <div class="ops-drawer-header">
                <h5 class="ops-drawer-title">Add Action Item</h5>
                <button type="button" class="ops-drawer-close" onclick="closeOpsTaskDrawer()" title="Close (Esc)">
                    &#10005;
                </button>
            </div>

            <form method="POST" action="{{ route('staff.ops-tracker.store') }}" id="opsCreateDrawerForm" class="d-flex flex-column flex-grow-1 overflow-hidden m-0">
                @csrf
                <input type="hidden" name="week_start_date" value="{{ $monday->toDateString() }}">

                <div class="ops-drawer-body">
                    {{-- Field 1: Task / Issue * --}}
                    <div class="mb-3">
                        <label class="ops-drawer-field-label" for="opsCreateTitleInput">Task / Issue *</label>
                        <input type="text"
                               name="title"
                               id="opsCreateTitleInput"
                               class="ops-drawer-input"
                               value="{{ old('title') }}"
                               placeholder="What needs to be done or followed up?"
                               required>
                    </div>

                    {{-- Field 2: Note / Context (Optional) --}}
                    <div class="mb-3">
                        <label class="ops-drawer-field-label" for="opsCreateNotesInput">Note / Context (Optional)</label>
                        <textarea name="notes"
                                  id="opsCreateNotesInput"
                                  rows="2"
                                  class="ops-drawer-textarea"
                                  placeholder="Optional context or details...">{{ old('notes') }}</textarea>
                    </div>

                    {{-- Field 3: Area (Selectable Pill Chips) --}}
                    <div class="mb-3">
                        <label class="ops-drawer-field-label">Area</label>
                        <div class="ops-chip-group">
                            @foreach ($scopes as $scopeOpt)
                                <label class="ops-chip-option">
                                    <input type="radio"
                                           name="scope"
                                           value="{{ $scopeOpt }}"
                                           {{ old('scope', 'Laleen Rituals') === $scopeOpt ? 'checked' : '' }}>
                                    <span class="ops-chip-pill">{{ $scopeOpt }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Field 4: Pending With (Staff Access — Select 1 or more) * --}}
                    <div class="mb-3">
                        <label class="ops-drawer-field-label">Pending With (Staff Access &mdash; Select 1 or more) *</label>
                        <div class="ops-drawer-staff-list">
                            @forelse ($assigneeOptions as $idx => $opt)
                                <label class="ops-drawer-staff-row">
                                    <span class="d-inline-flex align-items-center gap-2">
                                        <input type="checkbox"
                                               name="assigned_user_ids[]"
                                               value="{{ $opt['id'] }}"
                                               {{ $idx === 0 ? 'checked' : '' }}>
                                        <span>{{ $opt['name'] }}</span>
                                    </span>
                                    <span class="ops-role-chip">{{ $opt['role_label'] }}</span>
                                </label>
                            @empty
                                <div class="text-muted small px-2 py-2">No active Staff Access users found.</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Field 5: Timeframe (Selectable Pill Chips) --}}
                    <div class="mb-2">
                        <label class="ops-drawer-field-label">Timeframe</label>
                        <div class="ops-chip-group">
                            @foreach ($slaOptions as $hoursVal => $hoursLabel)
                                <label class="ops-chip-option">
                                    <input type="radio"
                                           name="sla_hours"
                                           value="{{ $hoursVal }}"
                                           {{ (int) old('sla_hours', 48) === (int) $hoursVal ? 'checked' : '' }}>
                                    <span class="ops-chip-pill">{{ $hoursLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="ops-drawer-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2" onclick="closeOpsTaskDrawer()">
                        Cancel
                    </button>
                    <button type="submit" class="btn-ops-drawer-save">
                        + Save Task
                    </button>
                </div>
            </form>
        </aside>

        {{-- 2. EDIT MODE DRAWER (Update Note & Staff for Row ✎) --}}
        <aside class="ops-slide-drawer" id="opsEditDrawer" aria-hidden="true">
            <div class="ops-drawer-header">
                <h5 class="ops-drawer-title">Update Note &amp; Staff</h5>
                <button type="button" class="ops-drawer-close" onclick="closeOpsTaskDrawer()" title="Close (Esc)">
                    &#10005;
                </button>
            </div>

            <form method="POST" action="" id="opsEditDrawerForm" class="d-flex flex-column flex-grow-1 overflow-hidden m-0">
                @csrf
                @method('PATCH')

                <div class="ops-drawer-body">
                    {{-- Locked Read-Only Task Title Display Block (Immutable) --}}
                    <div class="mb-3">
                        <label class="ops-drawer-field-label">Task / Issue (Locked)</label>
                        <div class="ops-locked-title-box" id="opsEditDrawerLockedTitle"></div>
                    </div>

                    {{-- Editable Note / Context --}}
                    <div class="mb-3">
                        <label class="ops-drawer-field-label" for="opsEditDrawerNotesInput">Note / Context</label>
                        <textarea name="notes"
                                  id="opsEditDrawerNotesInput"
                                  rows="3"
                                  class="ops-drawer-textarea"
                                  placeholder="Add or update note..."></textarea>
                    </div>

                    {{-- Editable Pending With (Staff Access — Select 1 or more) --}}
                    <div class="mb-3">
                        <label class="ops-drawer-field-label">Pending With (Staff Access &mdash; Select 1 or more)</label>
                        <div class="ops-drawer-staff-list" id="opsEditDrawerStaffList">
                            @foreach ($assigneeOptions as $opt)
                                <label class="ops-drawer-staff-row">
                                    <span class="d-inline-flex align-items-center gap-2">
                                        <input type="checkbox"
                                               name="assigned_user_ids[]"
                                               value="{{ $opt['id'] }}"
                                               data-user-id="{{ $opt['id'] }}"
                                               data-user-name="{{ strtolower($opt['name']) }}"
                                               class="ops-edit-drawer-cb">
                                        <span>{{ $opt['name'] }}</span>
                                    </span>
                                    <span class="ops-role-chip">{{ $opt['role_label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Admin-Only Timeframe Control (Editable for Admin/Owner, Locked Read-Only for Non-Admin) --}}
                    @if (\App\Models\OpsActionItem::isAdminOrOwner(auth()->user()))
                        <div class="mb-2" id="opsEditDrawerTimeframeSection">
                            <label class="ops-drawer-field-label">Timeframe (Admin Only)</label>
                            <div class="ops-chip-group">
                                @foreach ($slaOptions as $hoursVal => $hoursLabel)
                                    <label class="ops-chip-option">
                                        <input type="radio"
                                               name="sla_hours"
                                               value="{{ $hoursVal }}"
                                               data-sla-value="{{ $hoursVal }}"
                                               class="ops-edit-drawer-sla-radio">
                                        <span class="ops-chip-pill">{{ $hoursLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="mb-2" id="opsEditDrawerTimeframeLocked">
                            <label class="ops-drawer-field-label">Timeframe</label>
                            <div class="ops-locked-timeframe-box" id="opsEditDrawerLockedTimeframe">
                                🔒 Timeframe: 48h (2 Days) &mdash; Admin Only
                            </div>
                        </div>
                    @endif
                </div>

                <div class="ops-drawer-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2" onclick="closeOpsTaskDrawer()">
                        Cancel
                    </button>
                    <button type="submit" class="btn-ops-drawer-save">
                        ✓ Save Changes
                    </button>
                </div>
            </form>
        </aside>
    @endmoduleEdit

    {{-- Sleek Centered Dark-Luxury Confirmation Modal --}}
    <div class="modal fade ops-confirm-modal" id="opsActionConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold mb-0" id="opsConfirmModalTitle">Mark Task as Completed?</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="ops-confirm-task-box" id="opsConfirmModalTaskTitle">""</div>
                    <p class="mb-0" id="opsConfirmModalMessage">
                        Are you sure this operational item is fully resolved?
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-sm btn-success fw-bold" id="opsConfirmModalSubmitBtn" onclick="executeOpsConfirmedAction()">
                        ✓ Yes, Complete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let pendingOpsConfirmFormId = null;

    function openOpsCreateDrawer() {
        const backdropEl = document.getElementById('opsDrawerBackdrop');
        const createDrawer = document.getElementById('opsCreateDrawer');
        const editDrawer = document.getElementById('opsEditDrawer');

        if (editDrawer) editDrawer.classList.remove('open');
        if (backdropEl) backdropEl.classList.add('open');
        if (createDrawer) {
            createDrawer.classList.add('open');
            setTimeout(function () {
                const titleInput = document.getElementById('opsCreateTitleInput');
                if (titleInput) titleInput.focus();
            }, 180);
        }
    }

    function openOpsEditDrawer(payload) {
        if (!payload) return;
        const backdropEl = document.getElementById('opsDrawerBackdrop');
        const createDrawer = document.getElementById('opsCreateDrawer');
        const editDrawer = document.getElementById('opsEditDrawer');
        const formEl = document.getElementById('opsEditDrawerForm');
        const lockedTitleEl = document.getElementById('opsEditDrawerLockedTitle');
        const notesInput = document.getElementById('opsEditDrawerNotesInput');
        const lockedTimeframeEl = document.getElementById('opsEditDrawerLockedTimeframe');

        if (createDrawer) createDrawer.classList.remove('open');
        if (formEl && payload.update_url) {
            formEl.action = payload.update_url;
        }
        if (lockedTitleEl) {
            lockedTitleEl.textContent = payload.title || '';
        }
        if (notesInput) {
            notesInput.value = payload.notes || '';
        }

        const currentSlaHours = parseInt(payload.sla_hours || 48, 10);
        document.querySelectorAll('.ops-edit-drawer-sla-radio').forEach(function (radio) {
            const val = parseInt(radio.getAttribute('data-sla-value') || radio.value || '48', 10);
            radio.checked = val === currentSlaHours;
        });

        if (lockedTimeframeEl) {
            const tfLabel = payload.timeframe_label || (currentSlaHours + 'h');
            lockedTimeframeEl.textContent = '🔒 Timeframe: ' + tfLabel + ' — Admin Only';
        }

        const assignedIds = Array.isArray(payload.assigned_user_ids)
            ? payload.assigned_user_ids.map(function (v) { return parseInt(v, 10); })
            : [];
        const assignedNamesLower = Array.isArray(payload.assigned_names)
            ? payload.assigned_names.map(function (n) { return String(n).toLowerCase(); })
            : [];

        document.querySelectorAll('.ops-edit-drawer-cb').forEach(function (cb) {
            const uid = parseInt(cb.getAttribute('data-user-id') || '0', 10);
            const uName = cb.getAttribute('data-user-name') || '';
            cb.checked = assignedIds.indexOf(uid) !== -1 || assignedNamesLower.indexOf(uName) !== -1;
        });

        if (backdropEl) backdropEl.classList.add('open');
        if (editDrawer) {
            editDrawer.classList.add('open');
            setTimeout(function () {
                if (notesInput) notesInput.focus();
            }, 180);
        }
    }

    function closeOpsTaskDrawer() {
        const backdropEl = document.getElementById('opsDrawerBackdrop');
        const createDrawer = document.getElementById('opsCreateDrawer');
        const editDrawer = document.getElementById('opsEditDrawer');

        if (backdropEl) backdropEl.classList.remove('open');
        if (createDrawer) createDrawer.classList.remove('open');
        if (editDrawer) editDrawer.classList.remove('open');
    }

    function openOpsConfirmModal(itemId, taskTitle, actionType) {
        const titleEl = document.getElementById('opsConfirmModalTitle');
        const taskBoxEl = document.getElementById('opsConfirmModalTaskTitle');
        const messageEl = document.getElementById('opsConfirmModalMessage');
        const submitBtnEl = document.getElementById('opsConfirmModalSubmitBtn');

        if (taskBoxEl) {
            taskBoxEl.textContent = '"' + (taskTitle || '') + '"';
        }

        if (actionType === 'reopen') {
            pendingOpsConfirmFormId = 'opsToggleForm-' + itemId;
            if (titleEl) titleEl.textContent = 'Move Back to Pending?';
            if (messageEl) messageEl.textContent = "Reopen '" + (taskTitle || '') + "' and restart its active SLA timer?";
            if (submitBtnEl) {
                submitBtnEl.textContent = '↺ Yes, Move to Pending';
                submitBtnEl.className = 'btn btn-sm btn-warning fw-bold text-dark';
            }
        } else if (actionType === 'delete') {
            pendingOpsConfirmFormId = 'opsDeleteForm-' + itemId;
            if (titleEl) titleEl.textContent = 'Delete this task permanently?';
            if (messageEl) messageEl.textContent = 'This operational task will be permanently removed from the weekly checklist.';
            if (submitBtnEl) {
                submitBtnEl.textContent = '🗑️ Yes, Delete';
                submitBtnEl.className = 'btn btn-sm btn-danger fw-bold';
            }
        } else {
            pendingOpsConfirmFormId = 'opsToggleForm-' + itemId;
            if (titleEl) titleEl.textContent = 'Mark Task as Completed?';
            if (messageEl) messageEl.textContent = 'Are you sure this operational item is fully resolved?';
            if (submitBtnEl) {
                submitBtnEl.textContent = '✓ Yes, Complete';
                submitBtnEl.className = 'btn btn-sm btn-success fw-bold';
            }
        }

        const modalEl = document.getElementById('opsActionConfirmModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            const modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            modalInstance.show();
            setTimeout(function () {
                if (submitBtnEl) submitBtnEl.focus();
            }, 150);
        } else if (pendingOpsConfirmFormId) {
            const formEl = document.getElementById(pendingOpsConfirmFormId);
            if (formEl) formEl.submit();
        }
    }

    function executeOpsConfirmedAction() {
        if (!pendingOpsConfirmFormId) return;
        const formEl = document.getElementById(pendingOpsConfirmFormId);
        if (formEl) {
            formEl.submit();
        }
    }

    function toggleOpsStaffFilterMenu(event) {
        if (event) {
            event.stopPropagation();
        }
        const menuEl = document.getElementById('opsStaffFilterMenu');
        if (menuEl) {
            menuEl.classList.toggle('open');
        }
    }

    function closeOpsStaffFilterMenu() {
        const menuEl = document.getElementById('opsStaffFilterMenu');
        if (menuEl) {
            menuEl.classList.remove('open');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('click', function (e) {
            const wrapEl = document.getElementById('opsStaffFilterDropdownWrap');
            if (wrapEl && !wrapEl.contains(e.target)) {
                closeOpsStaffFilterMenu();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeOpsTaskDrawer();
                closeOpsStaffFilterMenu();
            }
        });

        const modalEl = document.getElementById('opsActionConfirmModal');
        if (modalEl) {
            modalEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    executeOpsConfirmedAction();
                }
            });
        }
    });
</script>
@endsection
