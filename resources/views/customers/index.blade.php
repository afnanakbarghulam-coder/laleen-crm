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

    .btn-row-wa {
        background: rgba(74, 158, 110, 0.12);
        border: 1px solid rgba(74, 158, 110, 0.32);
        color: #93d5ad;
    }

    .btn-row-wa:hover {
        background: rgba(74, 158, 110, 0.24);
        color: #fff;
    }

    .btn-row-rebook {
        background: rgba(201, 166, 107, 0.12);
        border: 1px solid rgba(201, 166, 107, 0.32);
        color: #ebd09b;
    }

    .btn-row-rebook:hover {
        background: rgba(201, 166, 107, 0.24);
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
                            $waPhone = $tierInfo['wa_phone'] ?: preg_replace('/\D+/', '', (string) $customer->phone);
                            $points = (int) ($customer->loyalty_points ?? 0);
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
                                    @if ($waPhone)
                                        <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener"
                                            class="btn-row-action btn-row-wa"
                                            data-tooltip="Chat on WhatsApp">
                                            <i class="bx bxl-whatsapp"></i> WhatsApp
                                        </a>
                                    @endif
                                    <a href="{{ route('appointments.calendar', ['customer_id' => $customer->id, 'phone' => $customer->phone, 'name' => $customer->name]) }}"
                                        class="btn-row-action btn-row-rebook"
                                        data-tooltip="Book on Calendar">
                                        <i class="bx bx-calendar-plus"></i> Rebook
                                    </a>
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
@endsection
