@extends('layouts.app')

@section('title', 'Lead Management')

<style>
    .popup-box {
        position: fixed;
        bottom: 25px;
        right: 25px;
        width: 310px;
        background: #241e1c;
        border-radius: 12px;
        padding: 20px 18px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
        border-left: 6px solid #c9a66b;
        animation: popupFade 0.35s ease-out;
        display: none;
        z-index: 9999;
    }

    .popup-content h4 {
        font-size: 18px;
        font-weight: 600;
        color: #c97b4a;
        margin-bottom: 10px;
        padding-left: 5px;
    }

    .popup-content ul {
        margin: 0;
        padding-left: 18px;
    }

    .popup-content ul li {
        margin-bottom: 6px;
        font-size: 14px;
    }

    .close-icon {
        position: absolute;
        top: 8px;
        right: 12px;
        font-size: 22px;
        font-weight: bold;
        cursor: pointer;
        color: #c9a39a;
        transition: 0.2s ease;
    }

    .close-icon:hover {
        color: #e79a91;
        transform: scale(1.1);
    }

    @keyframes popupFade {
        from {
            opacity: 0;
            transform: translateY(20px) scale(0.95);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .lead-badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 7px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
        line-height: 1.3;
    }

    .lead-badge.cat-follow_up { background: rgba(142,168,138,.15); color: #8ea88a; }
    .lead-badge.cat-inquiry { background: rgba(138,166,171,.15); color: #8aa6ab; }
    .lead-badge.cat-no_show { background: rgba(201,166,107,.15); color: #c9a66b; }
    .lead-badge.cat-cancel { background: rgba(168,82,74,.15); color: #a8524a; }
    .lead-badge.cat-more { background: rgba(201,166,107,.18); color: #f3d9a4; padding: 2px 6px; }

    .leads-compact-table {
        width: 100% !important;
        table-layout: auto !important;
        margin-bottom: 0;
    }

    .leads-compact-table th {
        white-space: nowrap !important;
        vertical-align: middle !important;
        padding: 9px 8px !important;
        font-size: 11px !important;
        letter-spacing: 0.04em !important;
    }

    .leads-compact-table td {
        white-space: nowrap !important;
        vertical-align: middle !important;
        padding: 8px 8px !important;
        font-size: 12.5px !important;
    }

    .lead-contact-phone {
        font-weight: 700;
        font-size: 12.5px;
        color: #f2e6e2;
        line-height: 1.25;
        white-space: nowrap;
    }

    .lead-client-link {
        display: block;
        max-width: 135px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 11px;
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        margin-top: 2px;
        line-height: 1.2;
    }

    .lead-client-link:hover {
        color: #8ea88a;
    }

    .lead-remarks-truncate {
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 11px;
        color: rgba(255, 255, 255, 0.5);
        margin-top: 3px;
        line-height: 1.25;
    }

    .lead-service-tag {
        display: inline-flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 4px;
        padding: 2px 6px;
        font-size: 11px;
        font-weight: 500;
        color: #e2d5cf;
        white-space: nowrap;
        line-height: 1.3;
    }

    .lead-service-more {
        background: rgba(201, 166, 107, 0.14);
        border-color: rgba(201, 166, 107, 0.32);
        color: #f3d9a4;
        font-weight: 600;
    }

    .lead-retention-pill {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 1px 6px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 600;
        line-height: 1.25;
        white-space: nowrap;
        margin-right: 4px;
    }

    .lead-retention-pill.pill-refill-due {
        background: rgba(217, 143, 131, 0.18);
        border: 1px solid rgba(231, 154, 145, 0.38);
        color: #f4c4bc;
    }

    .lead-retention-pill.pill-package-ending {
        background: rgba(138, 166, 171, 0.18);
        border: 1px solid rgba(138, 166, 171, 0.36);
        color: #b4d5db;
    }

    .lead-retention-pill.pill-missing-vip {
        background: rgba(201, 166, 107, 0.18);
        border: 1px solid rgba(201, 166, 107, 0.36);
        color: #f0d290;
    }

    .btn-ai-script {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 4px !important;
        padding: 4px 9px !important;
        border-radius: 7px !important;
        font-size: 11.5px !important;
        font-weight: 600 !important;
        white-space: nowrap !important;
        width: auto !important;
        height: auto !important;
        line-height: 1.3 !important;
    }

    .btn-action-icon {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 28px !important;
        height: 28px !important;
        padding: 0 !important;
        border-radius: 7px !important;
        flex-shrink: 0 !important;
    }

    .ai-closer-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 6px;
        background: rgba(201, 166, 107, 0.14);
        border: 1px solid rgba(201, 166, 107, 0.3);
        color: #f3d9a4;
        font-size: 11.5px;
        font-weight: 700;
        white-space: nowrap;
    }

    .ai-drawer-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 12px;
        align-items: stretch;
    }

    .ai-drawer-left {
        grid-column: span 12 / span 12;
    }

    .ai-drawer-right {
        grid-column: span 12 / span 12;
    }

    @media (min-width: 992px) {
        .ai-drawer-left {
            grid-column: span 7 / span 7;
        }

        .ai-drawer-right {
            grid-column: span 5 / span 5;
        }
    }

    .ai-script-box {
        position: relative;
        min-height: 54px;
        padding: 10px 135px 10px 14px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-left: 3px solid var(--bs-primary, #c98a7b) !important;
        font-size: 13.5px;
        line-height: 1.5;
        color: #f5ebe7;
        display: flex;
        align-items: center;
    }

    .ai-script-box[dir="rtl"] {
        padding: 10px 14px 10px 135px;
        border-left: 1px solid rgba(255, 255, 255, 0.1) !important;
        border-right: 3px solid var(--bs-primary, #c98a7b) !important;
    }

    .ai-copy-inside-btn {
        position: absolute;
        bottom: 8px;
        right: 8px;
        padding: 4px 10px !important;
        border-radius: 999px !important;
        font-size: 11.5px !important;
        font-weight: 600 !important;
        white-space: nowrap !important;
    }

    .ai-script-box[dir="rtl"] .ai-copy-inside-btn {
        right: auto;
        left: 8px;
    }

    .ai-icon-tool-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 9px;
        border-radius: 6px;
        font-size: 11.5px;
        font-weight: 600;
        border: 1px solid rgba(255, 255, 255, 0.14);
        background: rgba(255, 255, 255, 0.04);
        color: #cbb8b0;
        cursor: pointer;
        transition: all 0.18s ease;
        white-space: nowrap;
    }

    .ai-icon-tool-btn:hover:not(:disabled) {
        border-color: rgba(217, 143, 131, 0.5);
        color: #fff;
        background: rgba(217, 143, 131, 0.12);
    }

    .needful-done-select {
        border: 1px solid rgba(138,125,118,.28);
        border-radius: 999px;
        padding: 3px 22px 3px 8px;
        width: 155px;
        max-width: 155px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        background-color: rgba(138,125,118,.18);
        color: #8a7d76;
        transition: all 0.2s ease;
        text-overflow: ellipsis;
    }

    .needful-done-select.needful-booked,
    .needful-done-badge.needful-booked {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 999px;
        padding: 3px 9px;
        font-size: 11px;
        font-weight: 600;
        background-color: rgba(74, 222, 128, .15);
        color: #4ade80;
        border: 1px solid rgba(74, 222, 128, .4);
        box-shadow: 0 0 12px rgba(74, 222, 128, .18);
        cursor: default;
        white-space: nowrap;
    }
    .needful-done-select.needful-yes {
        background-color: rgba(234, 179, 8, .16);
        color: #eab308;
        border-color: rgba(234, 179, 8, .35);
    }
    .needful-done-select.needful-no {
        background-color: rgba(168, 82, 74, .14);
        color: #a8524a;
        border-color: rgba(168, 82, 74, .3);
    }

    .lead-quick-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 13px;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid rgba(217, 143, 131, 0.2);
        background: rgba(36, 30, 28, 0.72);
        color: #cbb8b0;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .lead-quick-pill:hover {
        border-color: rgba(217, 143, 131, 0.45);
        color: #f2e6e2;
        transform: translateY(-1px);
    }

    .lead-quick-pill.is-active {
        background: rgba(217, 143, 131, 0.18);
        border-color: #d98f83;
        color: #e79a91;
        box-shadow: 0 0 12px rgba(217, 143, 131, 0.15);
    }

    .lead-quick-pill.pill-overdue.is-active,
    .lead-quick-pill.pill-overdue:hover {
        border-color: #ef4444;
        color: #fca5a5;
        background: rgba(239, 68, 68, 0.14);
    }

    .lead-quick-pill.pill-unscheduled.is-active,
    .lead-quick-pill.pill-unscheduled:hover {
        border-color: #f59e0b;
        color: #fcd34d;
        background: rgba(245, 158, 11, 0.14);
    }

    .lead-quick-pill.pill-booked.is-active,
    .lead-quick-pill.pill-booked:hover {
        border-color: #4ade80;
        color: #4ade80;
        background: rgba(74, 222, 128, 0.14);
    }

    .lead-pill-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: rgba(255, 255, 255, 0.08);
    }

    .lead-client-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11.5px;
        color: #8ea88a;
        margin-top: 3px;
    }

    .ai-lang-toggle {
        display: inline-flex;
        align-items: center;
        background: rgba(36, 30, 28, 0.85);
        border: 1px solid rgba(201, 166, 107, 0.28);
        border-radius: 999px;
        padding: 2px;
        gap: 2px;
    }

    .ai-lang-btn {
        border: none;
        background: transparent;
        color: #b8a69e;
        font-size: 11.5px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.18s ease;
        line-height: 1.3;
    }

    .ai-lang-btn:hover {
        color: #f2e6e2;
    }

    .ai-lang-btn.is-active {
        background: rgba(201, 166, 107, 0.22);
        color: #f3d9a4;
        box-shadow: 0 0 8px rgba(201, 166, 107, 0.18);
    }

    .ai-objection-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid rgba(217, 143, 131, 0.22);
        background: rgba(36, 30, 28, 0.75);
        color: #d6c5be;
        cursor: pointer;
        transition: all 0.18s ease;
        white-space: nowrap;
    }

    .ai-objection-chip:hover:not(:disabled) {
        border-color: rgba(217, 143, 131, 0.55);
        background: rgba(217, 143, 131, 0.12);
        color: #fff;
        transform: translateY(-1px);
    }

    .ai-objection-chip.is-active {
        background: rgba(217, 143, 131, 0.22);
        border-color: #d98f83;
        color: #f7c7be;
        box-shadow: 0 0 12px rgba(217, 143, 131, 0.2);
    }

    .ai-objection-chip:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .ai-reply-input {
        background: rgba(24, 19, 18, 0.78) !important;
        border: 1px solid rgba(201, 166, 107, 0.25) !important;
        color: #f2e6e2 !important;
        font-size: 12.5px !important;
    }

    .ai-reply-input::placeholder {
        color: #8f7e77 !important;
    }

    .ai-reply-input:focus {
        border-color: #d98f83 !important;
        box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.15) !important;
    }

    .morning-money-brief {
        background: linear-gradient(135deg, rgba(36, 28, 25, 0.88) 0%, rgba(28, 22, 20, 0.92) 100%);
        border: 1px solid rgba(201, 166, 107, 0.32);
        border-left: 4px solid #c9a66b;
        border-radius: 12px;
        padding: 10px 16px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        font-size: 12px;
        color: #f2e6e2;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18);
    }

    .morning-brief-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        white-space: nowrap;
    }
</style>
@section('content')
    <!-- Page Navbar with Add Button -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0">Lead Management</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('leads.analytics') }}" class="btn btn-outline-secondary">
                <i class="bx bx-bar-chart-alt-2 me-1"></i> Analytics
            </a>
            @moduleEdit('leads')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeadModal">
                    <i class="bx bx-plus me-1"></i> Add Lead
                </button>
            @endmoduleEdit
        </div>
    </div>

    <!-- Morning Money Brief Executive Banner -->
    <div class="morning-money-brief py-2.5 px-4 text-xs" id="morningMoneyBriefBanner" x-data="{
        briefText: @js($morningBriefText ?? ''),
        dueToday: {{ (int) ($dueTodayCount ?? 0) }},
        overdue: {{ (int) ($overdueCount ?? 0) }},
        unscheduled: {{ (int) ($unscheduledCount ?? 0) }},
        isRefreshing: false,
        refreshBrief() {
            if (this.isRefreshing) return;
            this.isRefreshing = true;
            fetch('{{ route('leads.ai-morning-brief') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({})
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.brief_text) {
                    this.briefText = data.brief_text;
                    if (typeof data.due_today_count !== 'undefined') this.dueToday = data.due_today_count;
                    if (typeof data.overdue_count !== 'undefined') this.overdue = data.overdue_count;
                    if (typeof data.unscheduled_count !== 'undefined') this.unscheduled = data.unscheduled_count;
                }
            })
            .catch(() => {})
            .finally(() => {
                this.isRefreshing = false;
            });
        }
    }">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="fw-bold text-uppercase" style="color: #f3d9a4; letter-spacing: 0.03em; font-size: 11px;">
                ⚡ Morning Money Brief
            </span>
            <span class="morning-brief-chip" style="color: #93c5fd; border-color: rgba(147, 197, 253, 0.28);">
                📅 Due Today: <strong x-text="dueToday">{{ $dueTodayCount ?? 0 }}</strong>
            </span>
            <span class="morning-brief-chip" style="color: #fca5a5; border-color: rgba(252, 165, 165, 0.28);">
                🔴 Overdue: <strong x-text="overdue">{{ $overdueCount ?? 0 }}</strong>
            </span>
            <span class="morning-brief-chip" style="color: #fcd34d; border-color: rgba(252, 211, 77, 0.28);">
                🟠 Needs Follow-up Date: <strong x-text="unscheduled">{{ $unscheduledCount ?? 0 }}</strong>
            </span>
        </div>

        <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-between">
            <div class="fw-medium" style="color: #f5ebe7; line-height: 1.4;" x-text="briefText">
                {{ $morningBriefText ?? '' }}
            </div>
            <button type="button"
                class="ai-icon-tool-btn flex-shrink-0"
                @click="refreshBrief()"
                :disabled="isRefreshing"
                title="Refresh AI Morning Brief">
                <span x-show="!isRefreshing">✨ Refresh AI Brief</span>
                <span x-show="isRefreshing" x-cloak>
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    Refreshing...
                </span>
            </button>
        </div>
    </div>

    <!-- Action Filter Bar & Filter Section -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <!-- Quick Action Filter Pills -->
            <div class="d-flex align-items-center gap-2 flex-wrap" id="leadQuickFilterBar"
                data-overdue-ids='@json($overdueLeads->pluck("id")->values())'
                data-unscheduled-ids='@json($unscheduledLeads->pluck("id")->values())'>
                <a href="{{ route('leads.index') }}"
                    class="lead-quick-pill {{ empty($alertFilter) ? 'is-active' : '' }}">
                    <span>All Leads</span>
                </a>
                <a href="{{ route('leads.index', ['alert_filter' => 'overdue']) }}"
                    class="lead-quick-pill pill-overdue {{ ($alertFilter ?? null) === 'overdue' ? 'is-active' : '' }}"
                    id="overdueLeadsPill">
                    <span>🔴 Overdue Follow-ups</span>
                    <span class="lead-pill-count" id="overdueLeadsPillCount">{{ $overdueCount ?? $overdueLeads->count() }}</span>
                </a>
                <a href="{{ route('leads.index', ['alert_filter' => 'unscheduled']) }}"
                    class="lead-quick-pill pill-unscheduled {{ ($alertFilter ?? null) === 'unscheduled' ? 'is-active' : '' }}"
                    id="unscheduledLeadsPill">
                    <span>🟠 Needs Follow-up Date</span>
                    <span class="lead-pill-count" id="unscheduledLeadsPillCount">{{ $unscheduledCount ?? $unscheduledLeads->count() }}</span>
                </a>
                <a href="{{ route('leads.index', ['alert_filter' => 'booked']) }}"
                    class="lead-quick-pill pill-booked {{ ($alertFilter ?? null) === 'booked' ? 'is-active' : '' }}"
                    id="bookedLeadsPill">
                    <span>🟢 Booked</span>
                    <span class="lead-pill-count" id="bookedLeadsPillCount">{{ $bookedCount ?? 0 }}</span>
                </a>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse"
                    aria-expanded="false" aria-controls="filterCollapse">
                    <i class="bx bx-filter-alt me-1"></i> Filters
                </button>

                <form method="GET" action="{{ route('leads.index') }}" id="followupDateForm" class="d-flex align-items-center gap-2 mb-0">
                    @if (!empty($alertFilter))
                        <input type="hidden" name="alert_filter" value="{{ $alertFilter }}">
                    @endif
                    <label class="form-label mb-0 small text-muted">Next Follow-up Date</label>
                    <input type="date" name="followup_date" class="form-control form-control-sm" style="width: auto;"
                        value="{{ request('followup_date') }}" onchange="this.form.submit()">
                    @if (request('followup_date'))
                        <a href="{{ route('leads.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </form>
            </div>
        </div>

        <div class="collapse mt-3 {{ request()->hasAny(['category', 'phone_number', 'start_date', 'end_date', 'agent_id']) ? 'show' : '' }}" id="filterCollapse">
            <div class="card card-body shadow-sm border-0">
                <form method="GET" action="{{ route('leads.index') }}">
                    @if (request('followup_date'))
                        <input type="hidden" name="followup_date" value="{{ request('followup_date') }}">
                    @endif
                    <div class="leads-filter-grid">
                        <div>
                            <label class="form-label">Category</label>
                            <select name="category[]" class="form-select leads-multiselect" multiple data-placeholder="All Categories">
                                @foreach (\App\Models\Lead::CATEGORIES as $key => $label)
                                    <option value="{{ $key }}" {{ in_array($key, (array) request('category', [])) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Agent</label>
                            <select name="agent_id" class="form-select">
                                <option value="">All Agents</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}" {{ (string) request('agent_id') === (string) $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">From</label>
                            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                        </div>

                        <div>
                            <label class="form-label">To</label>
                            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                        </div>

                        <div class="leads-filter-grid-span-2">
                            <label class="form-label">Contact (WhatsApp Number)</label>
                            <div class="input-group">
                                <select name="country_code" class="form-select" style="width: 110px; flex: 0 0 110px;">
                                    @foreach (\App\Models\Lead::COUNTRY_CODES as $code => $label)
                                        <option value="{{ $code }}" title="{{ $label }}" {{ request('country_code', '974') === $code ? 'selected' : '' }}>+{{ $code }} ({{ $label }})</option>
                                    @endforeach
                                </select>
                                <input type="text" name="phone_number" class="form-control" placeholder="XXXXXXXX"
                                    value="{{ request('phone_number') }}">
                            </div>
                        </div>

                        <div class="leads-filter-grid-span-2 d-flex gap-2 align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                            <a href="{{ route('leads.index') }}" class="btn btn-secondary w-100">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .leads-filter-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 1rem;
            align-items: end;
        }

        @media (min-width: 768px) {
            .leads-filter-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .leads-filter-grid-span-2 {
                grid-column: span 2 / span 2;
            }
        }
    </style>


    <!-- Lead Table -->
    <div class="card">
        <h5 class="card-header">All Leads</h5>
        <div class="table-responsive">
            <table class="table leads-compact-table w-full table-auto">
                <thead>
                    <tr>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">DATE</th>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">CONTACT</th>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">CATEGORY</th>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">SERVICES &amp; NOTES</th>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">AGENT</th>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">STATUS</th>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">FOLLOW-UP</th>
                        <th class="px-2.5 py-2.5 whitespace-nowrap">ACTIONS</th>
                    </tr>
                </thead>
                @forelse($leads as $lead)
                    @php
                        $isBookedOrClosed = in_array($lead->needful_done, \App\Models\Lead::CLOSED_STATUSES, true)
                            || in_array(strtolower((string) ($lead->status ?? '')), ['booked', 'closed'], true);
                        $leadCategories = $lead->categories ?? ($lead->category ? [$lead->category] : []);
                        $leadCategories = \App\Models\Lead::normalizeCategories($leadCategories);
                        $visibleCategories = array_slice($leadCategories, 0, 2);
                        $extraCategoriesCount = max(0, count($leadCategories) - 2);
                        $leadCatLabel = !empty($leadCategories)
                            ? implode(' + ', array_map(fn($c) => \App\Models\Lead::CATEGORIES[$c] ?? $c, $leadCategories))
                            : 'Inquiry';
                        $leadAgeDays = max(0, (int) floor(($lead->created_at ?? now())->diffInDays(now())));
                        $servicesList = is_array($lead->service_interest) ? array_values(array_filter($lead->service_interest)) : [];
                        $visibleServices = array_slice($servicesList, 0, 2);
                        $extraServicesCount = max(0, count($servicesList) - 2);
                        $allServicesTooltip = implode(', ', $servicesList);
                    @endphp
                    <tbody class="table-border-bottom-0" x-data="{
                        showAiPanel: false,
                        isLoading: false,
                        isClosed: {{ $isBookedOrClosed ? 'true' : 'false' }},
                        script: '',
                        copied: false,
                        errorMsg: '',
                        language: 'en',
                        activeMode: 'opener',
                        activeObjection: '',
                        clientReply: '',
                        setLanguage(lang) {
                            this.language = lang;
                            if (this.script && !this.isLoading) {
                                if (this.activeMode === 'objection' && this.activeObjection) {
                                    this.generateObjection(this.activeObjection);
                                } else {
                                    this.generateScript();
                                }
                            }
                        },
                        regenerateCurrent() {
                            if (this.activeMode === 'objection' && this.activeObjection) {
                                this.generateObjection(this.activeObjection);
                            } else {
                                this.generateScript();
                            }
                        },
                        generateScript() {
                            if (this.isClosed) return;
                            this.showAiPanel = true;
                            this.isLoading = true;
                            this.errorMsg = '';
                            this.copied = false;
                            this.activeMode = 'opener';
                            this.activeObjection = '';
                            fetch('{{ route('leads.ai-script', $lead) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({
                                    lead_id: {{ $lead->id }},
                                    module: 'lead_management',
                                    mode: 'opener',
                                    language: this.language,
                                    name: @js($lead->customer->name ?? $lead->name ?? ''),
                                    customer_name: @js($lead->customer->name ?? $lead->name ?? ''),
                                    categories: @js($leadCategories ?: ['inquiry']),
                                    service_interest: @js(!empty($lead->service_interest) ? implode(', ', (array) $lead->service_interest) : ''),
                                    remarks: @js($lead->customer_remarks ?? '')
                                })
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data && data.script) {
                                    this.script = data.script;
                                    if (data.language) this.language = data.language;
                                } else {
                                    this.errorMsg = 'Could not generate script. Please try again.';
                                }
                                this.isLoading = false;
                            })
                            .catch(() => {
                                this.errorMsg = 'Network error while generating AI script.';
                                this.isLoading = false;
                            })
                            .finally(() => {
                                this.isLoading = false;
                            });
                        },
                        generateObjection(type) {
                            if (this.isClosed) return;
                            this.showAiPanel = true;
                            this.isLoading = true;
                            this.errorMsg = '';
                            this.copied = false;
                            this.activeMode = 'objection';
                            this.activeObjection = type;
                            fetch('{{ route('leads.ai-script', $lead) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({
                                    lead_id: {{ $lead->id }},
                                    module: 'lead_management',
                                    mode: 'objection',
                                    objection_type: type,
                                    client_reply: this.clientReply,
                                    language: this.language,
                                    name: @js($lead->customer->name ?? $lead->name ?? ''),
                                    customer_name: @js($lead->customer->name ?? $lead->name ?? ''),
                                    categories: @js($leadCategories ?: ['inquiry']),
                                    service_interest: @js(!empty($lead->service_interest) ? implode(', ', (array) $lead->service_interest) : ''),
                                    remarks: @js($lead->customer_remarks ?? '')
                                })
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data && data.script) {
                                    this.script = data.script;
                                    if (data.language) this.language = data.language;
                                } else {
                                    this.errorMsg = 'Could not generate objection reply. Please try again.';
                                }
                                this.isLoading = false;
                            })
                            .catch(() => {
                                this.errorMsg = 'Network error while generating AI reply.';
                                this.isLoading = false;
                            })
                            .finally(() => {
                                this.isLoading = false;
                            });
                        },
                        copyToWhatsApp() {
                            if (!this.script) return;
                            navigator.clipboard.writeText(this.script).then(() => {
                                this.copied = true;
                                setTimeout(() => { this.copied = false; }, 2500);
                            });
                        }
                    }" style="border-top: none;">
                        <tr @if (!$isBookedOrClosed) @click="if (!isClosed) showAiPanel = !showAiPanel" style="cursor: pointer;" @endif>
                            <!-- 1. DATE -->
                            <td class="px-2.5 py-2.5 whitespace-nowrap">
                                <div class="d-inline-flex align-items-center gap-1 whitespace-nowrap">
                                    @if (!$isBookedOrClosed)
                                        <i class="bx text-muted" x-show="!isClosed" :class="showAiPanel ? 'bx-chevron-down' : 'bx-chevron-right'"></i>
                                    @endif
                                    <span>{{ $lead->created_at->format('d M y') }}</span>
                                </div>
                            </td>

                            <!-- 2. CONTACT -->
                            <td class="px-2.5 py-2.5 whitespace-nowrap" @click.stop>
                                <div class="lead-contact-phone">{{ $lead->phone }}</div>
                                @if ($lead->customer)
                                    <a href="{{ route('customers.show', $lead->customer_id) }}" target="_blank"
                                        class="lead-client-link text-xs text-white/60 truncate max-w-[135px]"
                                        title="{{ $lead->customer->name ?: 'Linked client' }}">
                                        <i class="bx bx-check-circle"></i> {{ $lead->customer->name ?: 'Linked client' }}
                                    </a>
                                @endif
                            </td>

                            <!-- 3. CATEGORY -->
                            <td class="px-2.5 py-2.5 whitespace-nowrap" title="{{ $leadCatLabel }}">
                                @if (!empty($visibleCategories))
                                    <div class="d-inline-flex align-items-center gap-1 flex-nowrap">
                                        @foreach ($visibleCategories as $catKey)
                                            <span class="lead-badge cat-{{ $catKey }}">{{ \App\Models\Lead::CATEGORIES[$catKey] ?? $catKey }}</span>
                                        @endforeach
                                        @if ($extraCategoriesCount > 0)
                                            <span class="lead-badge cat-more" title="{{ $leadCatLabel }}">+{{ $extraCategoriesCount }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            <!-- 4. SERVICES & NOTES -->
                            <td class="px-2.5 py-2.5 whitespace-nowrap">
                                @if (!empty($visibleServices))
                                    <div class="d-flex align-items-center gap-1 flex-nowrap" title="{{ $allServicesTooltip }}">
                                        @foreach ($visibleServices as $srv)
                                            <span class="lead-service-tag bg-white/5 border border-white/10 rounded px-1.5 py-0.5 text-[11px]">{{ $srv }}</span>
                                        @endforeach
                                        @if ($extraServicesCount > 0)
                                            <span class="lead-service-tag lead-service-more bg-white/5 border border-white/10 rounded px-1.5 py-0.5 text-[11px]"
                                                title="{{ $allServicesTooltip }}">
                                                +{{ $extraServicesCount }}
                                            </span>
                                        @endif
                                    </div>
                                @elseif (empty($lead->customer_remarks))
                                    <span class="text-muted">—</span>
                                @endif

                                @if (!empty($lead->customer_remarks))
                                    @php
                                        $rawRemark = trim((string) $lead->customer_remarks);
                                        $retentionBadgeLabel = null;
                                        $retentionBadgeClass = null;
                                        $remarkDetailText = $rawRemark;

                                        if (str_contains($rawRemark, '[Refill Due]')) {
                                            $retentionBadgeLabel = '🔄 Refill Due';
                                            $retentionBadgeClass = 'pill-refill-due';
                                            $remarkDetailText = trim(str_replace('[Refill Due]', '', $rawRemark), " \t\n\r\0\x0B|·");
                                        } elseif (str_contains($rawRemark, '[Package Ending]')) {
                                            $retentionBadgeLabel = '🎟️ Package Ending';
                                            $retentionBadgeClass = 'pill-package-ending';
                                            $remarkDetailText = trim(str_replace('[Package Ending]', '', $rawRemark), " \t\n\r\0\x0B|·");
                                        } elseif (str_contains($rawRemark, '[Missing VIP]')) {
                                            $retentionBadgeLabel = '👑 Missing VIP';
                                            $retentionBadgeClass = 'pill-missing-vip';
                                            $remarkDetailText = trim(str_replace('[Missing VIP]', '', $rawRemark), " \t\n\r\0\x0B|·");
                                        }
                                    @endphp
                                    <div class="lead-remarks-truncate text-[11px] text-white/60 truncate max-w-[220px] mt-0.5"
                                        title="{{ $rawRemark }}">
                                        @if ($retentionBadgeLabel)
                                            <span class="lead-retention-pill {{ $retentionBadgeClass }}">{{ $retentionBadgeLabel }}</span>
                                            <span>{{ $remarkDetailText }}</span>
                                        @else
                                            <i class="bx bx-message-square-detail me-1" style="font-size: 10px;"></i>{{ $rawRemark }}
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <!-- 5. AGENT -->
                            <td class="px-2.5 py-2.5 whitespace-nowrap">{{ $lead->agent->name ?? '—' }}</td>

                            <!-- 6. STATUS -->
                            <td class="px-2.5 py-2.5 whitespace-nowrap" @click.stop>
                                @if ($lead->needful_done === 'booked')
                                    <span class="needful-done-badge needful-booked" title="Auto-verified via Calendar Booking">
                                        <span>✅ Booked</span>
                                        <i class="bx bx-lock-alt" style="font-size: 11px; opacity: 0.85;"></i>
                                    </span>
                                @else
                                    @moduleEdit('leads')
                                        <select class="needful-done-select w-[155px] text-xs py-1 px-2 {{ $lead->needful_done ? 'needful-' . $lead->needful_done : '' }}"
                                            data-lead-id="{{ $lead->id }}"
                                            data-previous="{{ $lead->needful_done }}"
                                            data-url="{{ route('leads.needful-done', $lead->id) }}"
                                            @change="isClosed = ($event.target.value === 'no'); if (isClosed) showAiPanel = false;">
                                            <option value="" {{ !$lead->needful_done ? 'selected' : '' }}>— Pending</option>
                                            @foreach (\App\Models\Lead::MANUAL_NEEDFUL_STATUSES as $key => $label)
                                                <option value="{{ $key }}" {{ $lead->needful_done === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="needful-done-select {{ $lead->needful_done ? 'needful-' . $lead->needful_done : '' }}">
                                            {{ $lead->needful_done ? (\App\Models\Lead::NEEDFUL_STATUSES[$lead->needful_done] ?? $lead->needful_done) : '— Pending' }}
                                        </span>
                                    @endmoduleEdit
                                @endif
                            </td>

                            <!-- 7. FOLLOW-UP -->
                            <td class="px-2.5 py-2.5 whitespace-nowrap">{{ (!in_array($lead->needful_done, \App\Models\Lead::CLOSED_STATUSES, true) && $lead->next_followup_date) ? $lead->next_followup_date->format('d M y') : '—' }}</td>

                            <!-- 8. ACTIONS -->
                            <td class="px-2.5 py-2.5 whitespace-nowrap" @click.stop>
                                <div class="d-inline-flex gap-1.5 align-items-center flex-nowrap whitespace-nowrap">
                                    @if (!$isBookedOrClosed)
                                        <button type="button"
                                            class="btn btn-sm btn-primary btn-ai-script px-2.5 py-1 rounded-lg text-xs font-medium whitespace-nowrap"
                                            x-show="!isClosed"
                                            @click="generateScript()"
                                            :disabled="isLoading"
                                            title="Generate AI Closing Script">
                                            <span x-show="!isLoading">✨ AI Script</span>
                                            <span x-show="isLoading" x-cloak>
                                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                                ...
                                            </span>
                                        </button>
                                    @endif

                                    @moduleEdit('leads')
                                        <!-- Edit Button -->
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-action-icon" title="Edit Lead"
                                            data-bs-toggle="modal" data-bs-target="#editLeadModal{{ $lead->id }}">
                                            <i class="bx bx-edit-alt"></i>
                                        </button>

                                        <!-- Delete Button -->
                                        <form action="{{ route('leads.destroy', $lead->id) }}" method="POST" class="d-inline m-0"
                                            onsubmit="return confirm('Are you sure you want to delete this lead?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-action-icon" title="Delete Lead">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </form>
                                    @endmoduleEdit
                                </div>
                            </td>
                        </tr>
                        @if (!$isBookedOrClosed)
                            <tr x-show="showAiPanel && !isClosed" x-cloak class="bg-dark bg-opacity-10">
                                <td colspan="8" class="p-2" style="white-space: normal !important;">
                                    <div class="d-flex flex-column gap-2 p-3 rounded border border-secondary border-opacity-25" style="background: rgba(20, 16, 14, 0.55);">
                                        <!-- Top Toolbar (1 single flex row) -->
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="ai-closer-badge">
                                                    ✨ AI Closer &middot; {{ $leadCatLabel }} ({{ $leadAgeDays }}d)
                                                </span>

                                                <div class="ai-lang-toggle" role="group" aria-label="Script Language">
                                                    <button type="button"
                                                        class="ai-lang-btn"
                                                        :class="{ 'is-active': language === 'en' }"
                                                        @click="setLanguage('en')">
                                                        EN
                                                    </button>
                                                    <button type="button"
                                                        class="ai-lang-btn"
                                                        :class="{ 'is-active': language === 'ar' }"
                                                        @click="setLanguage('ar')">
                                                        عربي
                                                    </button>
                                                </div>

                                                <div class="d-inline-flex align-items-center gap-1 flex-wrap">
                                                    <button type="button"
                                                        class="ai-objection-chip text-xs"
                                                        :class="{ 'is-active': activeMode === 'objection' && activeObjection === 'price_high' }"
                                                        :disabled="isLoading"
                                                        @click="generateObjection('price_high')">
                                                        💰 Price
                                                    </button>
                                                    <button type="button"
                                                        class="ai-objection-chip text-xs"
                                                        :class="{ 'is-active': activeMode === 'objection' && activeObjection === 'busy_later' }"
                                                        :disabled="isLoading"
                                                        @click="generateObjection('busy_later')">
                                                        ⏳ Busy
                                                    </button>
                                                    <button type="button"
                                                        class="ai-objection-chip text-xs"
                                                        :class="{ 'is-active': activeMode === 'objection' && activeObjection === 'price_inquiry' }"
                                                        :disabled="isLoading"
                                                        @click="generateObjection('price_inquiry')">
                                                        🤔 Price Info
                                                    </button>
                                                    <button type="button"
                                                        class="ai-objection-chip text-xs"
                                                        :class="{ 'is-active': activeMode === 'objection' && activeObjection === 'ghosted' }"
                                                        :disabled="isLoading"
                                                        @click="generateObjection('ghosted')">
                                                        👻 Follow-up
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center gap-1">
                                                <button type="button"
                                                    class="ai-icon-tool-btn"
                                                    @click="regenerateCurrent()"
                                                    :disabled="isLoading"
                                                    title="Regenerate script">
                                                    <span>↻ Regenerate</span>
                                                </button>
                                                <button type="button"
                                                    class="ai-icon-tool-btn"
                                                    x-on:click="showAiPanel = false"
                                                    title="Close AI panel">
                                                    <span>✕</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Bottom Content Split (7 cols script box / 5 cols custom reply input) -->
                                        <div class="ai-drawer-grid grid-cols-12 gap-3">
                                            <!-- Left (7 cols): Generated WhatsApp script box with tucked Copy to WhatsApp button -->
                                            <div class="ai-drawer-left">
                                                <div x-show="isLoading" x-cloak class="ai-script-box text-muted small">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                                                        <span>Writing short WhatsApp message...</span>
                                                    </div>
                                                </div>

                                                <div x-show="errorMsg && !isLoading" x-cloak class="ai-script-box text-danger small" x-text="errorMsg"></div>

                                                <div x-show="script && !isLoading" x-cloak
                                                    class="ai-script-box text-sm leading-relaxed"
                                                    :dir="language === 'ar' ? 'rtl' : 'ltr'">
                                                    <span x-text="script"></span>
                                                    <button type="button"
                                                        class="btn btn-sm btn-success ai-copy-inside-btn"
                                                        @click="copyToWhatsApp()">
                                                        <i class="bx bxl-whatsapp me-1"></i>
                                                        <span x-text="copied ? 'Copied!' : 'Copy to WhatsApp'"></span>
                                                    </button>
                                                </div>

                                                <div x-show="!script && !isLoading && !errorMsg" x-cloak class="ai-script-box text-muted small">
                                                    <span>Click <strong>✨ AI Script</strong> or pick an objection pill (`💰 Price`, `⏳ Busy`, `🤔 Price Info`, `👻 Follow-up`) to generate a 1–2 sentence reply.</span>
                                                </div>
                                            </div>

                                            <!-- Right (5 cols): Custom reply input with inline ⚡ Reply button -->
                                            <div class="ai-drawer-right d-flex align-items-center">
                                                <div class="input-group input-group-sm w-100">
                                                    <input type="text"
                                                        class="form-control ai-reply-input"
                                                        placeholder="Paste client WhatsApp reply..."
                                                        x-model="clientReply"
                                                        @keydown.enter.prevent="generateObjection('custom_reply')">
                                                    <button type="button"
                                                        class="btn btn-primary fw-semibold whitespace-nowrap"
                                                        style="border-radius: 0 6px 6px 0 !important; padding: 5px 12px !important; font-size: 12px !important;"
                                                        :disabled="isLoading"
                                                        @click="generateObjection('custom_reply')">
                                                        ⚡ Reply
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody class="table-border-bottom-0">
                        <tr>
                            <td colspan="8" class="text-center text-muted">No leads found</td>
                        </tr>
                    </tbody>
                @endforelse

            </table>
        </div>
    </div>
    <!--/ Basic Bootstrap Table -->

    <div id="followupPopup" class="popup-box">
        <span class="close-icon" onclick="closePopup()">×</span>

        <div class="popup-content">
            <h4>🔔 Pending Follow-ups</h4>
            <p id="followupMessage"></p>
        </div>
    </div>


    {{-- Edit modals render here rather than inside the table body above: a <form>
         placed directly in <tbody> (before any <tr>) gets corrupted by the browser's
         table foster-parenting rules, which silently drops any hidden input added to
         it by JavaScript after page load. --}}
    @moduleEdit('leads')
        @foreach ($leads as $lead)
            @include('leads.edit', ['lead' => $lead])
        @endforeach

        {{-- The unscheduled-leads banner above can link to leads outside the table's own
             filter/pagination, so their edit modals need rendering here too (skipping any
             already in $leads to avoid duplicate modal IDs). --}}
        @foreach ($unscheduledLeads->whereNotIn('id', $leads->pluck('id')) as $lead)
            @include('leads.edit', ['lead' => $lead])
        @endforeach

        @include('leads.create')
    @endmoduleEdit

    <script>
        // Smart client linking: as the country code / phone number change in the
        // Add or any Edit modal, check the combined number against the clients
        // table and surface a match/new badge, auto-filling the customer name.
        (function() {
            let lookupTimer = null;

            function handlePhoneChange(el) {
                const form = el.closest('form');
                const matchBox = form.querySelector('.lead-client-match');
                const newBox = form.querySelector('.lead-client-new');
                const hiddenId = form.querySelector('.lead-customer-id-input');
                const nameInput = form.querySelector('.lead-customer-name');
                const countryCode = form.querySelector('.lead-country-code').value;
                const numberVal = form.querySelector('.lead-phone-number').value;
                // Match the server's normalization: Qatar (the default/legacy
                // convention every existing client record uses) stays
                // unprefixed; only a non-Qatar code is actually prepended.
                const digits = (countryCode !== '974' ? countryCode + numberVal : numberVal).replace(/\D/g, '');

                clearTimeout(lookupTimer);
                hiddenId.value = '';
                matchBox.classList.add('d-none');
                newBox.classList.add('d-none');

                if (numberVal.replace(/\D/g, '').length < 6) return;

                lookupTimer = setTimeout(() => {
                    fetch(`{{ route('customers.lookup') }}?phone=${encodeURIComponent(digits)}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.found) {
                                hiddenId.value = data.id;
                                if (nameInput && data.name) nameInput.value = data.name;
                                matchBox.querySelector('.lead-client-visits').textContent = data.visit_count;
                                matchBox.querySelector('.lead-client-visits-wrap')?.classList.remove('d-none');
                                matchBox.querySelector('.lead-client-profile-link').href = data.profile_url;
                                matchBox.classList.remove('d-none');
                                newBox.classList.add('d-none');
                            } else {
                                newBox.classList.remove('d-none');
                                matchBox.classList.add('d-none');
                            }
                        })
                        .catch(() => {});
                }, 400);
            }

            document.addEventListener('input', function(e) {
                if (e.target.matches('.lead-phone-number')) handlePhoneChange(e.target);
            });
            document.addEventListener('change', function(e) {
                if (e.target.matches('.lead-country-code')) handlePhoneChange(e.target);
            });
        })();

        // Open the native calendar picker on any click inside the Next
        // Follow-up Date field (icon or text area alike) instead of making
        // staff hit the small calendar glyph precisely. Delegated on
        // document since the Edit modal is repeated once per lead row.
        document.addEventListener('click', function(e) {
            const dateInput = e.target.closest('.next-followup-date-input');
            if (dateInput && typeof dateInput.showPicker === 'function') {
                try {
                    dateInput.showPicker();
                } catch (err) {
                    // Ignored - e.g. browser blocks showPicker() outside a user gesture.
                }
            }
        });

        window.updateQuickFilterPillCounts = function(leadId, oldStatus, newStatus) {
            const bar = document.getElementById('leadQuickFilterBar');
            if (!bar) return;

            const idNum = Number(leadId);
            const closedStatuses = ['booked', 'no'];
            const wasClosed = closedStatuses.includes(oldStatus);
            const isNowClosed = closedStatuses.includes(newStatus);

            let overdueIds = [];
            let unscheduledIds = [];
            try { overdueIds = JSON.parse(bar.dataset.overdueIds || '[]'); } catch (e) {}
            try { unscheduledIds = JSON.parse(bar.dataset.unscheduledIds || '[]'); } catch (e) {}

            const overdueEl = document.getElementById('overdueLeadsPillCount');
            if (overdueEl && overdueIds.includes(idNum) && wasClosed !== isNowClosed) {
                const cur = Math.max(0, (parseInt(overdueEl.textContent, 10) || 0) + (isNowClosed ? -1 : 1));
                overdueEl.textContent = cur;
            }

            const unscheduledEl = document.getElementById('unscheduledLeadsPillCount');
            if (unscheduledEl && unscheduledIds.includes(idNum) && wasClosed !== isNowClosed) {
                const cur = Math.max(0, (parseInt(unscheduledEl.textContent, 10) || 0) + (isNowClosed ? -1 : 1));
                unscheduledEl.textContent = cur;
            }

            const bookedEl = document.getElementById('bookedLeadsPillCount');
            if (bookedEl) {
                const wasBooked = oldStatus === 'booked';
                const isNowBooked = newStatus === 'booked';
                if (wasBooked !== isNowBooked) {
                    const cur = Math.max(0, (parseInt(bookedEl.textContent, 10) || 0) + (isNowBooked ? 1 : -1));
                    bookedEl.textContent = cur;
                }
            }
        };

        // Inline "Needful Done" toggle straight from the leads table.
        document.addEventListener('change', function(e) {
            if (!e.target.matches('.needful-done-select')) return;

            const select = e.target;
            const previous = select.dataset.previous ?? '';
            const nextVal = select.value;

            fetch(select.dataset.url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ needful_done: nextVal }),
            })
                .then(res => {
                    if (!res.ok) throw new Error();
                    select.classList.remove('needful-booked', 'needful-yes', 'needful-no');
                    if (nextVal) select.classList.add('needful-' + nextVal);
                    select.dataset.previous = nextVal;
                    if (select.dataset.leadId && typeof window.updateQuickFilterPillCounts === 'function') {
                        window.updateQuickFilterPillCounts(select.dataset.leadId, previous, nextVal);
                    }
                })
                .catch(() => {
                    select.value = previous;
                    alert('Could not update Needful Done. Please try again.');
                });
        });

        document.addEventListener("DOMContentLoaded", function() {

            fetch("{{ route('leads.check.followups') }}")
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        showFollowupNotification(data.leads);
                    }
                })
                .catch(error => console.log(error));
        });

        function showFollowupNotification(leads) {
            let popup = document.getElementById("followupPopup");

            let listHTML = "<ul>";
            leads.forEach(lead => {
                listHTML += `<li><strong>${lead.phone}</strong></li>`;
            });
            listHTML += "</ul>";

            document.getElementById("followupMessage").innerHTML =
                `You have <strong>${leads.length}</strong> lead(s) due for follow-up today:<br><br>${listHTML}`;

            popup.style.display = "block";
        }

        function closePopup() {
            document.getElementById("followupPopup").style.display = "none";
        }

        function toggleLeadAlertAccordion(headerEl) {
            const body = headerEl.nextElementSibling;
            const chevron = headerEl.querySelector('.kpi-alert-chevron');
            const isOpening = body.style.display === 'none';
            body.style.display = isOpening ? 'block' : 'none';
            chevron.classList.toggle('is-open', isOpening);
        }
    </script>


    @if (session('existing_lead_id'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                let modalId = 'editLeadModal{{ session('existing_lead_id') }}';
                let modalEl = document.getElementById(modalId);

                if (modalEl) {
                    let modal = new bootstrap.Modal(modalEl);
                    modal.show();

                    // Optional: scroll to modal for better UX
                    modalEl.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            });
        </script>
    @endif

@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            $('.leads-multiselect').select2({
                width: '100%',
                allowClear: true,
                closeOnSelect: false,
            });
        });
    </script>
@endsection
