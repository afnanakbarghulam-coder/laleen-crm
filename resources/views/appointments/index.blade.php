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
                            <td>
                                @if (auth()->user()->canEdit('bookings'))
                                    @php
                                        $apptData = [
                                            'id' => $appointment->id,
                                            'customer_name' => $appointment->customer_name,
                                            'phone' => $appointment->phone,
                                            'branch' => $appointment->branch,
                                            'date' => $appointment->appointment_datetime->format('Y-m-d'),
                                            'time' => $appointment->appointment_datetime->format('H:i'),
                                            'service_names' => array_values(array_filter(array_map('trim', explode(',', $appointment->service_name)))),
                                            'staff_id' => $appointment->staff_id,
                                            'staff_name' => $appointment->staff->name ?? null,
                                            'status' => $appointment->status,
                                            'price' => (float) $appointment->price,
                                        ];
                                    @endphp
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                        data-appt="{{ json_encode($apptData) }}"
                                        onclick="openBookingEditModal(this)">
                                        <i class="bx bx-edit-alt"></i>
                                        {{ in_array($appointment->status, ['cancelled', 'no_show']) ? 'Reschedule' : 'Edit' }}
                                    </button>
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

                    <div class="modal-header">
                        <h5 class="modal-title">
                            Edit Booking <span class="text-muted" id="editCustomerNameLabel"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="editModalError" class="alert alert-danger d-none"></div>

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
                                    <option value="completed">Completed</option>
                                    <option value="no_show">No Show</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date</label>
                                <input type="date" id="editDate" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Time</label>
                                <input type="time" id="editTime" class="form-control" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Services</label>
                                <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto;">
                                    @foreach ($services as $s)
                                        <div class="form-check">
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
                                </div>
                                <small class="text-muted" id="editServiceTotals">0 services selected</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Team Member</label>
                                <select name="staff_id" id="editStaffSelect" class="form-select" required>
                                    <option value="">-- Select Staff --</option>
                                </select>
                                <small class="text-danger d-none" id="editStaffHelp">No skilled &amp; available staff for this service/time.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="editSaveBtn">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
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

        function openBookingEditModal(btn) {
            const appt = JSON.parse(btn.dataset.appt);
            editCurrentApptId = appt.id;
            editPendingStaffId = appt.staff_id;

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
            document.getElementById('editModalError').classList.add('d-none');
            document.getElementById('editStaffHelp').classList.add('d-none');

            document.querySelectorAll('.edit-service-checkbox').forEach(cb => {
                cb.checked = appt.service_names.includes(cb.value);
            });
            updateEditServiceTotals(false);

            const staffSelect = document.getElementById('editStaffSelect');
            staffSelect.innerHTML = appt.staff_id
                ? `<option value="${appt.staff_id}">${appt.staff_name || 'Current staff'}</option>`
                : '<option value="">-- Select Staff --</option>';
            loadEditAvailableStaff();

            new bootstrap.Modal(document.getElementById('bookingEditModal')).show();
        }
        window.openBookingEditModal = openBookingEditModal;

        function updateEditServiceTotals(recalcPrice = true) {
            const checked = [...document.querySelectorAll('.edit-service-checkbox:checked')];
            const totalPrice = checked.reduce((sum, cb) => sum + (parseFloat(cb.dataset.price) || 0), 0);
            const totalMin = checked.reduce((sum, cb) => sum + (parseInt(cb.dataset.duration) || 0), 0);
            document.getElementById('editServiceTotals').textContent =
                `${checked.length} service${checked.length === 1 ? '' : 's'} selected · ${totalMin} min · ${totalPrice.toFixed(2)} QAR`;
            if (recalcPrice) {
                document.getElementById('editPriceHidden').value = totalPrice.toFixed(2);
            }
        }

        document.querySelectorAll('.edit-service-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                updateEditServiceTotals(true);
                loadEditAvailableStaff();
            });
        });

        function loadEditAvailableStaff() {
            const staffSelect = document.getElementById('editStaffSelect');
            const staffHelp = document.getElementById('editStaffHelp');
            const services = [...document.querySelectorAll('.edit-service-checkbox:checked')].map(cb => cb.value);
            const date = document.getElementById('editDate').value;
            const time = document.getElementById('editTime').value;
            const branch = document.getElementById('editBranch').value;

            staffHelp.classList.add('d-none');
            if (!services.length || !date || !time || !branch) return;

            const params = new URLSearchParams();
            services.forEach(s => params.append('services[]', s));
            params.append('appointment_datetime', `${date}T${time}`);
            params.append('branch', branch);
            if (editCurrentApptId) params.append('exclude_appointment_id', editCurrentApptId);

            fetch(`{{ route('appointments.availableStaff') }}?${params.toString()}`)
                .then(r => r.json())
                .then(list => {
                    const previouslySelected = staffSelect.value;
                    staffSelect.innerHTML = '<option value="">-- Select Staff --</option>';

                    if (!list.length) {
                        staffHelp.classList.remove('d-none');
                        return;
                    }

                    list.forEach(s => {
                        staffSelect.insertAdjacentHTML('beforeend', `<option value="${s.id}">${s.name}</option>`);
                    });

                    const toSelect = editPendingStaffId ?? previouslySelected;
                    if (toSelect && [...staffSelect.options].some(o => o.value == toSelect)) {
                        staffSelect.value = toSelect;
                    }
                    editPendingStaffId = null;
                })
                .catch(() => staffHelp.classList.remove('d-none'));
        }

        document.getElementById('editBranch').addEventListener('change', loadEditAvailableStaff);
        document.getElementById('editDate').addEventListener('change', loadEditAvailableStaff);
        document.getElementById('editTime').addEventListener('change', loadEditAvailableStaff);

        document.getElementById('bookingEditForm').addEventListener('submit', function(e) {
            const date = document.getElementById('editDate').value;
            const time = document.getElementById('editTime').value;
            document.getElementById('editDatetimeHidden').value = (date && time) ? `${date}T${time}` : '';

            const errorEl = document.getElementById('editModalError');
            errorEl.classList.add('d-none');

            if (!document.querySelectorAll('.edit-service-checkbox:checked').length) {
                e.preventDefault();
                errorEl.textContent = 'Select at least one service.';
                errorEl.classList.remove('d-none');
                return;
            }
            if (!document.getElementById('editStaffSelect').value) {
                e.preventDefault();
                errorEl.textContent = 'Select a team member for this booking.';
                errorEl.classList.remove('d-none');
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
