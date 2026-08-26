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
</style>

@section('content')

    <div class="bk-header">
        <div>
            <h4>Bookings Analytics</h4>
            <p>Volume, revenue and status trends across every appointment. Creating, rescheduling and checking out bookings happen on the Enhanced Calendar page.</p>
        </div>
    </div>

    <!-- FILTER BAR (period + branch, both dropdowns - no manual date entry) -->
    <div class="bk-filter-card">
        <form method="GET" action="{{ route('appointments.index') }}" id="bkFilterForm">
            <div class="bk-filter-row">
                <select name="period" class="bk-date-input" onchange="document.getElementById('bkFilterForm').submit()">
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

                <span class="text-muted small ms-1">{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</span>

                @if ($period !== 'this_month' || $branch)
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
                    <span class="bk-kpi-label">Total Revenue</span>
                    <span class="bk-kpi-icon" style="background:rgba(142,168,138,.12); color:#7fa876;">
                        <i class="bx bx-money"></i>
                    </span>
                </div>
                <div class="bk-kpi-value">{{ number_format($totalRevenue, 2) }} <small class="fs-6 fw-semibold text-muted">QAR</small></div>
                <div class="bk-kpi-sub">
                    <span>Across all booking statuses</span>
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
                    <span>Revenue &divide; total bookings</span>
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

    <!-- BOOKINGS LIST (read-only) -->
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
                                <button type="button" class="btn btn-link p-0" onclick="showCustomerProfile('{{ $appointment->phone }}')">
                                    {{ $appointment->customer_name }}
                                </button>
                            </td>
                            <td>{{ $appointment->phone }}</td>
                            <td>{{ $appointment->service_name }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $appointment->branch)) }}</td>
                            <td>{{ $appointment->appointment_datetime->format('d M Y, h:i A') }}</td>
                            <td>{{ number_format($appointment->price, 2) }}</td>
                            <td>{{ $appointment->staff?->name ?? 'N/A' }}</td>
                            <td>
                                @php $sc = $statusColors[$appointment->status] ?? ['bg' => 'rgba(138,125,118,.15)', 'fg' => '#8a7d76']; @endphp
                                <span class="bk-status-chip" style="background:{{ $sc['bg'] }}; color:{{ $sc['fg'] }};">
                                    {{ ucwords(str_replace('_', ' ', $appointment->status)) }}
                                </span>
                            </td>
                            <td>{{ $appointment->agent->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No bookings in this date range</td>
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
@endsection

@section('scripts')
    <script>
        function showCustomerProfile(phone) {
            fetch(`/appointments/customer-profile/${phone}`)
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
                        tbody.innerHTML += `
                            <tr>
                                <td>${a.appointment_datetime}</td>
                                <td>${a.service_name}</td>
                                <td>${a.price}</td>
                                <td>${a.branch}</td>
                                <td>${a.agent}</td>
                            </tr>
                        `;
                    });

                    new bootstrap.Modal(document.getElementById('customerProfileModal')).show();
                })
                .catch(() => alert('No records found'));
        }

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
