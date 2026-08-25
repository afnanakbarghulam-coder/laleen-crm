@extends('layouts.app')
@section('title', 'Revenue Report')

@section('content')
    <h4 class="mb-3">Revenue Report</h4>

    <div class="card mb-4">
        <div class="card-body">
            <!-- Filter Section -->
            <div class="mb-4">
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse"
                    data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                    <i class="bx bx-filter-alt me-1"></i> Filters
                </button>

                <div class="collapse mt-3 {{ request()->hasAny(['period', 'branch', 'staff_id', 'payment_method']) ? 'show' : '' }}"
                    id="filterCollapse">
                    <div class="card card-body shadow-sm border-0">
                        <form method="GET" action="{{ route('appointments.revenue.index') }}">
                            <div class="row g-3 align-items-end">

                                <!-- Period Filter -->
                                <div class="col-md-3">
                                    <label class="form-label">Period</label>
                                    <select name="period" class="form-select">
                                        <option value="daily" {{ request('period') == 'daily' ? 'selected' : '' }}>Daily
                                        </option>
                                        <option value="weekly" {{ request('period') == 'weekly' ? 'selected' : '' }}>Weekly
                                        </option>
                                        <option value="monthly" {{ request('period') == 'monthly' ? 'selected' : '' }}>
                                            Monthly</option>
                                        <option value="all" {{ request('period') == 'all' ? 'selected' : '' }}>All
                                        </option>
                                    </select>
                                </div>

                                <!-- Branch Filter -->
                                <div class="col-md-3">
                                    <label class="form-label">Branch</label>
                                    <select name="branch" class="form-select">
                                        <option value="">All Branches</option>
                                        <option value="old_airport"
                                            {{ request('branch') == 'old_airport' ? 'selected' : '' }}>Old Airport</option>
                                        <option value="wakrah" {{ request('branch') == 'wakrah' ? 'selected' : '' }}>Wakrah
                                        </option>
                                        <option value="home_service"
                                            {{ request('branch') == 'home_service' ? 'selected' : '' }}>Home Service
                                        </option>
                                    </select>
                                </div>

                                <!-- Staff Filter -->
                                <div class="col-md-3">
                                    <label class="form-label">Staff</label>
                                    <select name="staff_id" class="form-select">
                                        <option value="">All Staff</option>
                                        @foreach ($staff as $s)
                                            <option value="{{ $s->id }}"
                                                {{ request('staff_id') == $s->id ? 'selected' : '' }}>
                                                {{ $s->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Payment Method Filter -->
                                <div class="col-md-3">
                                    <label class="form-label">Payment Method</label>
                                    <select name="payment_method" class="form-select">
                                        <option value="">All</option>
                                        <option value="cash" {{ request('payment_method') == 'cash' ? 'selected' : '' }}>
                                            Cash</option>
                                        <option value="card" {{ request('payment_method') == 'card' ? 'selected' : '' }}>
                                            Card</option>
                                        <option value="online_transfer"
                                            {{ request('payment_method') == 'online_transfer' ? 'selected' : '' }}>Online
                                            Transfer</option>
                                    </select>
                                </div>


                                <!-- From & To Date Filters -->
                                <div class="col-md-3">
                                    <label class="form-label">Revenue From</label>
                                    <input type="date" name="from" class="form-control"
                                        value="{{ request('from') }}">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Revenue To</label>
                                    <input type="date" name="to" class="form-control" value="{{ request('to') }}">
                                </div>

                                <!-- Filter & Reset Buttons -->
                                <div class="col-md-12 d-flex gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                    <a href="{{ route('appointments.revenue.index') }}"
                                        class="btn btn-secondary w-100">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body pt-0">
            <div class="row g-3 mb-2">
                <div class="col-6 col-md-2">
                    <div class="border rounded p-3 text-center">
                        <div class="fs-5 fw-bold">{{ number_format($totalRevenue, 2) }}</div>
                        <div class="small text-muted">Total Revenue</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="border rounded p-3 text-center">
                        <div class="fs-5 fw-bold">{{ number_format($totalServices, 2) }}</div>
                        <div class="small text-muted">Services</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="border rounded p-3 text-center">
                        <div class="fs-5 fw-bold">{{ number_format($totalProducts, 2) }}</div>
                        <div class="small text-muted">Retail Products</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="border rounded p-3 text-center">
                        <div class="fs-5 fw-bold">{{ number_format($totalTips, 2) }}</div>
                        <div class="small text-muted">Tips</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="border rounded p-3 text-center">
                        <div class="fs-5 fw-bold">{{ number_format($paymentTotals['cash'] ?? 0, 2) }}</div>
                        <div class="small text-muted">Cash</div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="border rounded p-3 text-center">
                        <div class="fs-5 fw-bold">{{ number_format($paymentTotals['card'] ?? 0, 2) }}</div>
                        <div class="small text-muted">Card</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Services</th>
                                <th>Products</th>
                                <th>Staff</th>
                                <th>Branch</th>
                                <th>Discount</th>
                                <th>Tip</th>
                                <th>Total (QAR)</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sales as $sale)
                                <tr>
                                    <td>{{ $sale->created_at->format('d M Y H:i') }}</td>
                                    <td>{{ $sale->customer->name ?? $sale->appointment?->customer_name ?? '—' }}</td>
                                    <td>
                                        @foreach ($sale->items->where('type', 'service') as $item)
                                            <div class="small">{{ $item->name }}</div>
                                        @endforeach
                                    </td>
                                    <td>
                                        @forelse ($sale->items->where('type', 'product') as $item)
                                            <div class="small">{{ $item->name }} × {{ $item->quantity }}</div>
                                        @empty
                                            <span class="text-muted">—</span>
                                        @endforelse
                                    </td>
                                    <td>{{ $sale->staff->name ?? '-' }}</td>
                                    <td>{{ ucwords(str_replace('_', ' ', $sale->branch)) }}</td>
                                    <td>{{ number_format($sale->discount_amount, 2) }}</td>
                                    <td>{{ number_format($sale->tip_amount, 2) }}</td>
                                    <td class="fw-semibold">{{ number_format($sale->total_amount, 2) }}</td>
                                    <td>
                                        @foreach ($sale->payments as $payment)
                                            <div class="small">{{ ucfirst(str_replace('_', ' ', $payment->method)) }}: {{ number_format($payment->amount, 2) }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center">No payments found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endsection
