@extends('layouts.app')
@section('title', 'Net Profit')

@include('ecommerce._styles')

@php
    $currency = $currency ?? 'PKR';
    $region = $region ?? 'pk';
@endphp

@section('content')
    <div class="ec-header">
        <div>
            <div class="ec-sub text-uppercase fw-bold mb-1" style="font-size: 11px; letter-spacing: .08em; color: var(--ec-gold);">Customers &amp; Sales &middot; {{ $region === 'qa' ? 'Qatar (QAR)' : 'Pakistan (PKR)' }}</div>
            <h4>Net Profit ({{ $currency }})</h4>
            <p>True bottom line: realized sales revenue less unit COGS, operating expenses, and non-revenue inventory shrinkage.</p>
        </div>
    </div>

    @include('ecommerce.partials._hub_tabs')

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <a
            href="{{ route('ecommerce.profit.transactions', array_filter(['region' => $region, 'start_date' => $startDate, 'end_date' => $endDate])) }}"
            class="btn btn-sm d-inline-flex align-items-center gap-2 border border-[#d98f83] text-[#d98f83] hover:bg-[#d98f83]/10"
            style="border: 1px solid #d98f83; color: #d98f83; background: transparent; font-weight: 600; border-radius: 8px; padding: 6px 14px; transition: background 0.15s ease;"
            onmouseover="this.style.background='rgba(217, 143, 131, 0.12)'"
            onmouseout="this.style.background='transparent'"
        >
            <i class="bi bi-table"></i>
            <span>View All Transactions</span>
        </a>

        <form method="GET" action="{{ route('ecommerce.net-profit.index') }}" class="d-flex align-items-center flex-wrap gap-2">
            <input type="hidden" name="region" value="{{ $region }}">
            <div class="d-flex align-items-center gap-2">
                <label for="startDateFilter" class="ec-sub mb-0">From</label>
                <input type="date" name="start_date" id="startDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ $startDate }}">
                <label for="endDateFilter" class="ec-sub mb-0">To</label>
                <input type="date" name="end_date" id="endDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ $endDate }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Apply Filter</button>
        </form>
    </div>

    {{-- Row 1: Executive KPI Cards (Realized Revenue, Pending COD Pipeline, Total COGS, Gross Profit, Operating Expenses, True Net Profit) --}}
    <div class="mb-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(195px, 1fr)); gap: 16px;">
        <div class="ec-card mb-0 h-100">
            <h6>Realized Revenue</h6>
            <div class="ec-value">{{ $currency }} {{ number_format($totalRevenue, 2) }}</div>
            <div class="ec-sub">{{ $filterText }} &middot; Paid &amp; Delivered</div>
        </div>

        <div class="ec-card mb-0 h-100" style="border-color: rgba(201, 166, 107, 0.35);">
            <h6>Pending COD / Pipeline</h6>
            <div class="ec-value" style="color: #deb97c;">{{ $currency }} {{ number_format($pendingCodRevenue ?? 0, 2) }}</div>
            <div class="ec-sub">{{ number_format($pendingCodOrders ?? 0) }} unpaid order(s) in transit</div>
        </div>

        <div class="ec-card mb-0 h-100">
            <h6>Total COGS</h6>
            <div class="ec-value ec-negative">{{ $currency }} {{ number_format($totalCogs, 2) }}</div>
            <div class="ec-sub">Bill of Materials cost of realized sales</div>
        </div>

        <div class="ec-card mb-0 h-100">
            <h6>Gross Profit</h6>
            <div class="ec-value {{ $grossProfit >= 0 ? 'ec-positive' : 'ec-negative' }}">{{ $currency }} {{ number_format($grossProfit, 2) }}</div>
            <div class="ec-sub">Revenue &minus; COGS &middot; {{ number_format($grossMargin, 1) }}% margin</div>
        </div>

        <div class="ec-card mb-0 h-100">
            <h6>Operating &amp; Shrinkage</h6>
            <div class="ec-value ec-negative">{{ $currency }} {{ number_format($totalDeductions, 2) }}</div>
            <div class="ec-sub">
                Ops {{ $currency }} {{ number_format($expenseDeductions, 2) }}
                &middot; Shrinkage {{ $currency }} {{ number_format($nonRevenueShrinkageCogs ?? 0, 2) }}
            </div>
        </div>

        <div class="ec-card ec-card-highlight mb-0 h-100">
            <h6>True Net Profit</h6>
            <div class="ec-value {{ $trueNetProfit >= 0 ? 'ec-positive' : 'ec-negative' }}" style="font-size: 24px;">{{ $currency }} {{ number_format($trueNetProfit, 2) }}</div>
            <div class="ec-sub">Gross Profit &minus; Deductions &middot; {{ number_format($trueNetMargin, 1) }}% margin</div>
        </div>
    </div>

    <div class="ec-accounting-note mb-4">
        <span>💡</span>
        <div>
            <strong>Single Source of Truth for Net Profit:</strong>
            Operating Expenses logged in the Expenses tab (Meta Ads, Courier/Shipping, Taxes, etc.) are deducted once here.
            @if (($referenceOrderCostsTotal ?? 0) > 0)
                Per-order shipping/tax/ad fields on individual sales ({{ $currency }} {{ number_format($referenceOrderCostsTotal, 2) }} in this period) are kept as <em>Est. Unit Economics Reference Only — Deducted via Monthly Expenses</em> so they are never double-counted.
            @endif
        </div>
    </div>

    {{-- Row 2: Full-Width Net Profit Trend Chart --}}
    <div class="ec-card ec-chart-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
            <h6 class="mb-0">Net Profit Trend ({{ $currency }})</h6>
            <span class="ec-sub mt-0">{{ $filterText }}</span>
        </div>
        @if (count($chartLabels) > 0)
            <canvas id="netProfitTrendChart" height="90" class="mt-2"></canvas>
        @else
            <div class="ec-chart-empty">No data in the selected range.</div>
        @endif
    </div>

    {{-- Row 3: 50/50 Split — Deduction Breakdown (Left) & Partner Profit Share (Right) --}}
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="ec-card h-100 mb-0">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Deduction Breakdown</h6>
                    <span class="ec-sub mt-0">Total: <strong style="color: #d98f83;">{{ $currency }} {{ number_format($totalDeductions, 2) }}</strong></span>
                </div>
                @if ($totalDeductions <= 0)
                    <div class="ec-chart-empty" style="height: 150px;">No active deductions in this period.</div>
                @else
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                        @foreach ($deductionBreakdown as $row)
                            @if ($row['amount'] > 0)
                                @php
                                    $pct = $totalDeductions > 0 ? ($row['amount'] / $totalDeductions) * 100 : 0;
                                @endphp
                                <div class="d-flex justify-content-between align-items-center px-3 py-2 rounded-3" style="background: #1c1615; border: 1px solid rgba(217, 143, 131, 0.18);">
                                    <div class="me-2 min-w-0">
                                        <div class="fw-semibold text-truncate" style="color: #f3e9e7; font-size: 12.5px;">{{ $row['category'] }}</div>
                                        <div style="color: #8a7b78; font-size: 11px;">{{ number_format($pct, 1) }}% of deductions</div>
                                    </div>
                                    <div class="fw-bold text-end flex-shrink-0" style="color: #d98f83; font-size: 13px; font-variant-numeric: tabular-nums;">
                                        {{ $currency }} {{ number_format($row['amount'], 2) }}
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-6">
            <div class="ec-card h-100 mb-0">
                <h6 style="margin-bottom: 4px;">Partner Profit Share ({{ $currency }})</h6>
                <p class="ec-sub mb-3">{{ $filterText }} &middot; Each partner's equity slice of True Net Profit ({{ $currency }} {{ number_format($trueNetProfit, 2) }}).</p>
                @if ($partnerProfitShare->isEmpty())
                    <div class="ec-chart-empty" style="height: 150px;">No partners yet &mdash; add them on the Partner Ledger tab.</div>
                @else
                    @php
                        $totalEquity = (float) $partnerProfitShare->sum('equity_percentage');
                        $totalAllocated = (float) $partnerProfitShare->sum('share');
                    @endphp
                    @foreach ($partnerProfitShare as $row)
                        <div class="ec-partner-share-row">
                            <div class="ec-partner-share-name">{{ $row['partner']->name }}</div>
                            <div class="ec-partner-share-equity">{{ number_format($row['equity_percentage'], 4) }}% equity</div>
                            <div class="ec-partner-share-amount {{ $row['share'] >= 0 ? 'ec-positive' : 'ec-negative' }}">
                                {{ $currency }} {{ number_format($row['share'], 2) }}
                            </div>
                        </div>
                    @endforeach
                    <div class="ec-partner-share-total">
                        <div style="flex: 1; min-width: 0;">Total allocated</div>
                        <div>{{ number_format($totalEquity, 4) }}%</div>
                        <div style="min-width: 150px; text-align: right; font-weight: 700;">{{ $currency }} {{ number_format($totalAllocated, 2) }}</div>
                    </div>
                    @if (abs($totalEquity - 100) > 0.01)
                        <div class="ec-sub ec-negative mt-2">
                            ⚠ Equity across all partners totals {{ number_format($totalEquity, 4) }}%, not 100% &mdash;
                            {{ $currency }} {{ number_format($trueNetProfit - $totalAllocated, 2) }} of True Net Profit is unallocated.
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    @if (count($chartLabels) > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (function () {
                const CURRENCY = @json($currency);
                const canvas = document.getElementById('netProfitTrendChart');
                const ctx = canvas.getContext('2d');
                const netProfitFill = ctx.createLinearGradient(0, 0, 0, 240);
                netProfitFill.addColorStop(0, 'rgba(223, 166, 166, 0.3)');
                netProfitFill.addColorStop(1, 'rgba(223, 166, 166, 0)');

                new Chart(ctx, {
                    data: {
                        labels: @json($chartLabels),
                        datasets: [
                            {
                                type: 'line',
                                label: 'Net Profit',
                                data: @json($chartNetProfit),
                                borderColor: '#dfa6a6',
                                backgroundColor: netProfitFill,
                                fill: true,
                                tension: 0.4,
                                borderWidth: 2,
                                pointRadius: 0,
                                pointBackgroundColor: '#dfa6a6',
                                pointHoverRadius: 6,
                                pointBorderColor: '#241e1c',
                                pointBorderWidth: 2,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#241e1c',
                                titleColor: '#e79a91',
                                bodyColor: '#f2e6e2',
                                borderColor: 'rgba(217, 143, 131, 0.2)',
                                borderWidth: 1,
                                padding: 12,
                                cornerRadius: 10,
                                displayColors: false,
                                titleFont: { size: 12, weight: '600' },
                                bodyFont: { size: 12 },
                                callbacks: {
                                    label: function (context) {
                                        return '  Net Profit: ' + CURRENCY + ' ' + Number(context.parsed.y).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false, drawBorder: false },
                                ticks: { color: '#9ca3af', font: { size: 11 } },
                            },
                            y: {
                                beginAtZero: true,
                                grid: { display: true, color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                                ticks: {
                                    color: '#9ca3af',
                                    font: { size: 11 },
                                    callback: function (value) {
                                        return CURRENCY + ' ' + Number(value).toLocaleString('en-US');
                                    },
                                },
                            },
                        },
                    },
                });
            })();
        </script>
    @endif
@endsection
