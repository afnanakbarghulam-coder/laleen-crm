@extends('layouts.app')
@section('title', 'Clients')

<style>
    /* Unified Toolbar: Segmented Filter Tabs + Integrated Search */
    .clients-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        padding: 8px 10px;
        border-radius: 14px;
        background: rgba(28, 23, 22, 0.72);
        border: 1px solid rgba(217, 143, 131, 0.14);
        margin-bottom: 18px;
    }

    .clients-tabs {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 4px;
    }

    .clients-tab {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 7px 13px;
        border-radius: 9px;
        border: 1px solid transparent;
        background: transparent;
        color: #b8a39d;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        white-space: nowrap;
        transition: all 0.15s ease;
    }

    .clients-tab:hover {
        color: #f4eae6;
        background: rgba(255, 255, 255, 0.04);
    }

    .clients-tab.active {
        background: rgba(217, 143, 131, 0.18);
        border-color: rgba(231, 154, 145, 0.42);
        color: #fff;
        font-weight: 600;
    }

    .clients-tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 19px;
        padding: 0 6px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.07);
        color: #d6c3bd;
    }

    .clients-tab.active .clients-tab-count {
        background: rgba(231, 154, 145, 0.28);
        color: #fff;
    }

    .clients-search-form {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0;
        min-width: 250px;
    }

    .clients-search-wrap {
        position: relative;
        flex: 1;
    }

    .clients-search-wrap i {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        color: #9e8780;
        font-size: 15px;
        pointer-events: none;
    }

    .clients-search-input {
        width: 100%;
        padding: 6px 12px 6px 32px;
        border-radius: 9px;
        border: 1px solid rgba(217, 143, 131, 0.18);
        background: rgba(18, 14, 13, 0.55);
        color: #f4eae6;
        font-size: 13px;
        outline: none;
        transition: border-color 0.15s ease;
    }

    .clients-search-input::placeholder {
        color: #8c7670;
    }

    .clients-search-input:focus {
        border-color: rgba(231, 154, 145, 0.5);
    }

    /* Clean Borderless Table Card */
    .clients-table-card {
        border-radius: 14px;
        background: rgba(28, 23, 22, 0.62);
        border: 1px solid rgba(217, 143, 131, 0.13);
        overflow: visible;
    }

    .clients-clean-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse;
    }

    .clients-clean-table thead th {
        border: none !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07) !important;
        padding: 13px 16px !important;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #a99089;
        white-space: nowrap;
        background: transparent !important;
    }

    .clients-clean-table tbody td {
        border: none !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.045) !important;
        padding: 14px 16px !important;
        vertical-align: middle;
        font-size: 13.5px;
    }

    .clients-clean-table tbody tr:last-child td {
        border-bottom: none !important;
    }

    .clients-clean-table tbody tr {
        transition: background 0.14s ease;
    }

    .clients-clean-table tbody tr:hover {
        background: rgba(217, 143, 131, 0.045);
    }

    .client-name-link {
        color: #f5ebe7;
        font-weight: 600;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.14s ease;
    }

    .client-name-link:hover {
        color: #e79a91;
    }

    .client-phone-sub {
        font-size: 12px;
        color: #9e8780;
        margin-top: 2px;
    }

    /* Compact single-line status pills */
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        white-space: nowrap;
        line-height: 1.3;
    }

    .status-pill-vip {
        background: rgba(201, 166, 107, 0.16);
        color: #f0d290;
        border: 1px solid rgba(201, 166, 107, 0.32);
    }

    .status-pill-slipping {
        background: rgba(186, 84, 75, 0.18);
        color: #f29b94;
        border: 1px solid rgba(186, 84, 75, 0.36);
    }

    .status-pill-second {
        background: rgba(217, 130, 95, 0.16);
        color: #f5b094;
        border: 1px solid rgba(217, 130, 95, 0.34);
    }

    .status-pill-package {
        background: rgba(138, 166, 171, 0.16);
        color: #b4d5db;
        border: 1px solid rgba(138, 166, 171, 0.34);
    }

    .status-dot-active {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 12.5px;
        color: #96827c;
        white-space: nowrap;
    }

    .status-dot-active::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #8ea88a;
        display: inline-block;
    }

    .points-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        background: rgba(201, 166, 107, 0.1);
        color: #e4c485;
        white-space: nowrap;
    }

    /* Compact Single-Line Row Action Buttons */
    .row-actions-group {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        flex-wrap: nowrap;
    }

    .btn-row-action {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 500;
        text-decoration: none;
        white-space: nowrap;
        transition: all 0.14s ease;
        line-height: 1.2;
    }

    .btn-row-ai {
        background: rgba(217, 143, 131, 0.14);
        border: 1px solid rgba(231, 154, 145, 0.38);
        color: #f4c4bc;
        cursor: pointer;
    }

    .btn-row-ai:hover {
        background: rgba(217, 143, 131, 0.28);
        border-color: rgba(231, 154, 145, 0.65);
        color: #fff;
    }

    .btn-row-profile {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #cbb8b2;
    }

    .btn-row-profile:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
    }

    /* Leads-style AI Script Modal */
    .ai-modal-card {
        background: #1c1615;
        border: 1px solid rgba(217, 143, 131, 0.28);
        border-radius: 16px;
        color: #f4eae6;
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.65);
    }

    .ai-context-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 500;
        background: rgba(217, 143, 131, 0.12);
        border: 1px solid rgba(217, 143, 131, 0.25);
        color: #e9c2bb;
    }

    .ai-lang-toggle {
        display: inline-flex;
        border-radius: 9px;
        padding: 2px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(217, 143, 131, 0.22);
    }

    .ai-lang-btn {
        border: none;
        background: transparent;
        color: #b8a39d;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 12px;
        border-radius: 7px;
        cursor: pointer;
        transition: all 0.14s ease;
    }

    .ai-lang-btn.active {
        background: rgba(217, 143, 131, 0.24);
        color: #fff;
    }

    .ai-script-box {
        background: rgba(14, 11, 10, 0.65);
        border: 1px solid rgba(217, 143, 131, 0.2);
        border-radius: 12px;
        padding: 14px 16px;
        min-height: 84px;
        font-size: 14px;
        line-height: 1.55;
        color: #f6ece8;
        white-space: pre-wrap;
    }

    .btn-header-subtle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 13px;
        border-radius: 9px;
        font-size: 13px;
        font-weight: 500;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(217, 143, 131, 0.22);
        color: #dfcfca;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .btn-header-subtle:hover {
        background: rgba(217, 143, 131, 0.12);
        border-color: rgba(231, 154, 145, 0.45);
        color: #fff;
    }
</style>

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1">Clients</h4>
            <div class="text-muted small">Manage client profiles, loyalty points, and repeat bookings</div>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <form action="{{ route('customers.sync-retention-leads') }}" method="POST" class="m-0">
                @csrf
                <button type="submit" class="btn-header-subtle tooltip-bottom"
                    data-tooltip="Sends overdue clients to the Leads page for follow-up">
                    <i class="bx bx-Revision"></i>
                    <span>Send Overdue to Leads</span>
                </button>
            </form>
            <a href="{{ route('customers.beauty-planning') }}"
                class="btn-header-subtle tooltip-bottom"
                data-tooltip="Track when clients are due for roots, nails, or facials">
                <i class="bx bx-bell"></i>
                <span>Beauty Planning</span>
                @if ($dueCustomerIds->count())
                    <span class="badge rounded-pill bg-danger ms-1">{{ $dueCustomerIds->count() }}</span>
                @endif
            </a>
            <a href="{{ route('appointments.calendar') }}" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> Book Appointment
            </a>
        </div>
    </div>

    @php
        $currentSegment = $activeSegment ?? request('segment', 'all');
        $counts = $segmentCounts ?? [
            'all' => $customers->total(),
            'second_visit_zone' => 0,
            'bleeding_vips' => 0,
            'package_renewal' => 0,
            'idle_points' => 0,
        ];
        $retentionSegments = ['second_visit_zone', 'bleeding_vips', 'package_renewal', 'idle_points'];
        $isRetentionSegment = in_array($currentSegment, $retentionSegments, true);
        $segmentToAiMode = [
            'second_visit_zone' => 'second_visit',
            'bleeding_vips' => 'vip_winback',
            'package_renewal' => 'package_expiry',
            'idle_points' => 'idle_points',
        ];
        $segmentLabels = [
            'second_visit_zone' => 'Need 2nd Visit',
            'bleeding_vips' => 'Missing VIP',
            'package_renewal' => 'Package Ending',
            'idle_points' => 'Unused Points',
        ];
        $activeAiMode = $segmentToAiMode[$currentSegment] ?? 'maintenance';
        $activeBucketLabel = $segmentLabels[$currentSegment] ?? 'Client Retention';
    @endphp

    <!-- Unified Toolbar: Filter Tabs + Integrated Search -->
    <div class="clients-toolbar" role="navigation" aria-label="Client Filters">
        <div class="clients-tabs">
            <a href="{{ route('customers.index', array_filter(['search' => request('search'), 'segment' => 'all'])) }}"
                class="clients-tab {{ $currentSegment === 'all' ? 'active' : '' }} tooltip-bottom tooltip-align-left"
                data-tooltip="Every client in your database.">
                <span>All Clients</span>
                <span class="clients-tab-count">{{ $counts['all'] }}</span>
            </a>
            <a href="{{ route('customers.index', array_filter(['search' => request('search'), 'segment' => 'ltv'])) }}"
                class="clients-tab {{ $currentSegment === 'ltv' ? 'active' : '' }} tooltip-bottom"
                data-tooltip="Clients ranked from highest total spend to lowest.">
                <span>Top LTV</span>
                <span class="clients-tab-count">{{ $counts['all'] }}</span>
            </a>
            <a href="{{ route('customers.index', array_filter(['search' => request('search'), 'segment' => 'second_visit_zone'])) }}"
                class="clients-tab {{ $currentSegment === 'second_visit_zone' ? 'active' : '' }} tooltip-bottom"
                data-tooltip="Came once over 2 weeks ago and hasn't booked again.">
                <span>Need 2nd Visit</span>
                <span class="clients-tab-count">{{ $counts['second_visit_zone'] }}</span>
            </a>
            <a href="{{ route('customers.index', array_filter(['search' => request('search'), 'segment' => 'bleeding_vips'])) }}"
                class="clients-tab {{ $currentSegment === 'bleeding_vips' ? 'active' : '' }} tooltip-bottom"
                data-tooltip="Regular clients who haven't visited in over 45 days.">
                <span>Missing VIPs</span>
                <span class="clients-tab-count">{{ $counts['bleeding_vips'] }}</span>
            </a>
            <a href="{{ route('customers.index', array_filter(['search' => request('search'), 'segment' => 'package_renewal'])) }}"
                class="clients-tab {{ $currentSegment === 'package_renewal' ? 'active' : '' }} tooltip-bottom"
                data-tooltip="Has 1 package session left or expires within 3 weeks.">
                <span>Packages Ending</span>
                <span class="clients-tab-count">{{ $counts['package_renewal'] }}</span>
            </a>
            <a href="{{ route('customers.index', array_filter(['search' => request('search'), 'segment' => 'idle_points'])) }}"
                class="clients-tab {{ $currentSegment === 'idle_points' ? 'active' : '' }} tooltip-bottom"
                data-tooltip="Has 30+ points (QAR discount) unused for 30+ days.">
                <span>Unused Points</span>
                <span class="clients-tab-count">{{ $counts['idle_points'] }}</span>
            </a>
        </div>

        <form method="GET" action="{{ route('customers.index') }}" class="clients-search-form">
            @if ($currentSegment !== 'all')
                <input type="hidden" name="segment" value="{{ $currentSegment }}">
            @endif
            <div class="clients-search-wrap">
                <i class="bx bx-search"></i>
                <input type="text" name="search" class="clients-search-input"
                    placeholder="Search name or phone..." value="{{ request('search') }}">
            </div>
            @if (request('search') || $currentSegment !== 'all')
                <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            @endif
        </form>
    </div>

    <div class="clients-table-card mb-3">
        <div class="table-responsive" style="overflow: visible;">
            <table class="clients-clean-table">
                <thead>
                    <tr>
                        <th>CLIENT</th>
                        <th>STATUS</th>
                        <th>POINTS</th>
                        <th>VISITS</th>
                        <th>LAST VISIT</th>
                        <th>TOTAL SPENT</th>
                        <th class="text-end">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        @php
                            $tierInfo = $customerTiers[$customer->id] ?? ['badges' => [], 'wa_phone' => preg_replace('/\D+/', '', (string) $customer->phone)];
                            $points = (int) ($customer->loyalty_points ?? 0);
                            $lastVisitFormatted = ($lastVisit[$customer->id] ?? null)
                                ? \Illuminate\Support\Carbon::parse($lastVisit[$customer->id])->format('d M Y')
                                : 'No past visit';
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div>
                                        <a href="{{ route('customers.show', $customer->id) }}" class="client-name-link">
                                            {{ $customer->name ?? 'Unnamed' }}
                                        </a>
                                        @if ($dueCustomerIds->contains($customer->id))
                                            <span class="badge bg-warning text-dark ms-1"
                                                data-tooltip="Due for a service touch-up"><i class="bx bx-bell"></i></span>
                                        @endif
                                        <div class="client-phone-sub">{{ $customer->phone }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap">
                                <div class="d-inline-flex align-items-center gap-1 flex-nowrap">
                                    @forelse ($tierInfo['badges'] as $badge)
                                        <span class="status-pill {{ $badge['class'] }}"
                                            data-tooltip="{{ $badge['tooltip'] ?? '' }}">
                                            {{ $badge['label'] }}
                                        </span>
                                    @empty
                                        <span class="status-dot-active">Active</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="text-nowrap">
                                @if ($points > 0)
                                    <span class="points-chip" data-tooltip="1 point = 1 QAR discount at checkout.">
                                        💎 {{ $points }} pts
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-nowrap">{{ $customer->appointments_count }}</td>
                            <td class="text-nowrap">
                                @if ($lastVisit[$customer->id] ?? null)
                                    {{ \Illuminate\Support\Carbon::parse($lastVisit[$customer->id])->format('d M Y') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-nowrap fw-semibold">
                                {{ number_format($ltv[$customer->id] ?? 0, 2) }} QAR
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="row-actions-group">
                                    @if ($isRetentionSegment)
                                        <button type="button"
                                            class="btn-row-action btn-row-ai js-open-client-ai-script"
                                            data-endpoint="{{ route('customers.ai-retention-script', $customer->id) }}"
                                            data-client-name="{{ $customer->name ?? 'Valued Client' }}"
                                            data-client-phone="{{ $customer->phone }}"
                                            data-bucket-label="{{ $activeBucketLabel }}"
                                            data-mode="{{ $activeAiMode }}"
                                            data-last-visit="{{ $lastVisitFormatted }}"
                                            data-points="{{ $points }}"
                                            data-service="{{ $tierInfo['last_service'] ?? 'Signature Salon Ritual' }}"
                                            data-staff="{{ $tierInfo['last_staff'] ?? 'your stylist' }}"
                                            data-branch="{{ $tierInfo['last_branch'] ?? 'old_airport' }}"
                                            data-days-overdue="{{ $tierInfo['days_since'] ?? 30 }}"
                                            data-tooltip="Generate personalized WhatsApp script">
                                            ✨ AI Script
                                        </button>
                                    @endif
                                    <a href="{{ route('customers.show', $customer->id) }}"
                                        class="btn-row-action btn-row-profile tooltip-align-right"
                                        data-tooltip="Open client profile">
                                        Profile
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No clients found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $customers->links() }}

    @if ($isRetentionSegment)
        <!-- Leads-Style AI Warm Outreach Script Modal -->
        <div class="modal fade" id="clientAiScriptModal" tabindex="-1" aria-labelledby="clientAiScriptModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content ai-modal-card">
                    <div class="modal-header border-0 pb-2 d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="modal-title fw-bold mb-1" id="clientAiScriptModalLabel">
                                ✨ AI Outreach Script — <span id="aiModalClientName">Client</span>
                            </h6>
                            <div class="text-muted small" id="aiModalClientPhone"></div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="ai-lang-toggle" role="group" aria-label="Script Language">
                                <button type="button" class="ai-lang-btn active" data-lang="en">EN</button>
                                <button type="button" class="ai-lang-btn" data-lang="ar">عربي</button>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                    </div>
                    <div class="modal-body pt-2">
                        <div class="mb-3">
                            <span class="ai-context-pill" id="aiModalBucketContext"></span>
                        </div>
                        <div class="ai-script-box" id="aiModalScriptOutput">Generating personalized script...</div>
                    </div>
                    <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="aiModalRegenerateBtn">
                            🔄 Regenerate
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="aiModalCopyBtn">
                            📋 Copy to WhatsApp
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalEl = document.getElementById('clientAiScriptModal');
                if (!modalEl) return;

                const nameEl = document.getElementById('aiModalClientName');
                const phoneEl = document.getElementById('aiModalClientPhone');
                const contextEl = document.getElementById('aiModalBucketContext');
                const outputEl = document.getElementById('aiModalScriptOutput');
                const copyBtn = document.getElementById('aiModalCopyBtn');
                const regenBtn = document.getElementById('aiModalRegenerateBtn');
                const langBtns = modalEl.querySelectorAll('.ai-lang-btn');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

                let activeState = {
                    endpoint: '',
                    mode: 'maintenance',
                    service: 'Signature Salon Ritual',
                    staff: 'your stylist',
                    branch: 'old_airport',
                    daysOverdue: 30,
                    language: 'en'
                };

                function fetchAiScript() {
                    if (!activeState.endpoint) return;
                    outputEl.textContent = activeState.language === 'ar'
                        ? 'جاري كتابة رسالة مخصصة...'
                        : 'Generating personalized script...';
                    outputEl.setAttribute('dir', activeState.language === 'ar' ? 'rtl' : 'ltr');
                    regenBtn.disabled = true;

                    fetch(activeState.endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            mode: activeState.mode,
                            language: activeState.language,
                            service_name: activeState.service,
                            last_staff_name: activeState.staff,
                            branch: activeState.branch,
                            days_overdue: activeState.daysOverdue
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        outputEl.textContent = data.script || 'Could not generate script. Please try again.';
                    })
                    .catch(() => {
                        outputEl.textContent = 'Could not generate script right now. Please try again.';
                    })
                    .finally(() => {
                        regenBtn.disabled = false;
                    });
                }

                document.querySelectorAll('.js-open-client-ai-script').forEach(btn => {
                    btn.addEventListener('click', function () {
                        activeState.endpoint = this.dataset.endpoint;
                        activeState.mode = this.dataset.mode || 'maintenance';
                        activeState.service = this.dataset.service || 'Signature Salon Ritual';
                        activeState.staff = this.dataset.staff || 'your stylist';
                        activeState.branch = this.dataset.branch || 'old_airport';
                        activeState.daysOverdue = parseInt(this.dataset.daysOverdue || '30', 10);

                        nameEl.textContent = this.dataset.clientName || 'Client';
                        phoneEl.textContent = this.dataset.clientPhone || '';

                        const parts = [this.dataset.bucketLabel || 'Retention'];
                        if (this.dataset.lastVisit) {
                            parts.push('Last visit ' + this.dataset.lastVisit);
                        }
                        if (parseInt(this.dataset.points || '0', 10) > 0) {
                            parts.push(this.dataset.points + ' pts');
                        }
                        contextEl.textContent = parts.join(' · ');

                        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        bsModal.show();
                        fetchAiScript();
                    });
                });

                langBtns.forEach(btn => {
                    btn.addEventListener('click', function () {
                        langBtns.forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        activeState.language = this.dataset.lang || 'en';
                        fetchAiScript();
                    });
                });

                regenBtn.addEventListener('click', function () {
                    fetchAiScript();
                });

                copyBtn.addEventListener('click', function () {
                    const text = (outputEl.textContent || '').trim();
                    if (!text) return;
                    navigator.clipboard.writeText(text).then(() => {
                        const orig = copyBtn.innerHTML;
                        copyBtn.innerHTML = '✅ Copied!';
                        setTimeout(() => {
                            copyBtn.innerHTML = orig;
                        }, 1600);
                    });
                });
            });
        </script>
    @endif
@endsection
