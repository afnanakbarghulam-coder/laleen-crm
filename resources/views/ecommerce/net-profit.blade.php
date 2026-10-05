@extends('layouts.app')
@section('title', 'Net Profit')

@include('ecommerce._styles')

@php
    $channelBadgeClasses = [
        'Shopify (Pakistan)' => 'ec-channel-shopify',
        'Organic (Pakistan)' => 'ec-channel-organic',
        'Salon (Old Airport)' => 'ec-channel-salon',
        'Salon (Wakrah)' => 'ec-channel-salon',
        'Organic (Qatar)' => 'ec-channel-organic',
        'Backbar Use (Qatar)' => 'ec-channel-backbar',
        'Damage/Expiry (Pakistan)' => 'ec-channel-damage',
        'Damage/Expiry (Qatar)' => 'ec-channel-damage',
    ];
@endphp

@section('content')
    <div class="ec-header">
        <div>
            <h4>Net Profit</h4>
            <p>True bottom line after stickers, bottles, labels, courier, packaging, taxes, and ad spend are deducted from sales revenue.</p>
        </div>
    </div>

    @include('ecommerce._nav')

    <div class="d-flex justify-content-end align-items-center mb-4">
        <form method="GET" action="{{ route('ecommerce.net-profit.index') }}" class="d-flex align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <label for="startDateFilter" class="ec-sub mb-0">From</label>
                <input type="date" name="start_date" id="startDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ $startDate }}">
                <label for="endDateFilter" class="ec-sub mb-0">To</label>
                <input type="date" name="end_date" id="endDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ $endDate }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Apply Filter</button>
        </form>
    </div>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Gross Revenue</h6>
                <div class="ec-value">PKR {{ number_format($totalRevenue, 2) }}</div>
                <div class="ec-sub">{{ $filterText }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total COGS</h6>
                <div class="ec-value ec-negative">PKR {{ number_format($totalCogs, 2) }}</div>
                <div class="ec-sub">Bill of Materials cost of goods sold</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Gross Profit</h6>
                <div class="ec-value {{ $grossProfit >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($grossProfit, 2) }}</div>
                <div class="ec-sub">Revenue &minus; COGS &middot; {{ number_format($grossMargin, 1) }}% margin</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <div class="ec-card">
                <h6>Operating Expenses</h6>
                <div class="ec-value ec-negative">PKR {{ number_format($totalDeductions, 2) }}</div>
                <div class="ec-sub">Stickers, bottles, labels, courier, packaging, taxes &amp; Meta ads</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="ec-card ec-card-highlight">
                <h6>True Net Profit</h6>
                <div class="ec-value {{ $trueNetProfit >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($trueNetProfit, 2) }}</div>
                <div class="ec-sub">Gross Profit &minus; Operating Expenses &middot; {{ number_format($trueNetMargin, 1) }}% margin</div>
            </div>
        </div>
    </div>

    <div class="ec-card ec-chart-card mt-3 mb-3">
        <h6>Net Profit Trend</h6>
        <div class="ec-sub" style="margin-bottom: 4px;">{{ $filterText }}</div>
        @if (count($chartLabels) > 0)
            <canvas id="netProfitTrendChart" height="90" class="mt-2"></canvas>
        @else
            <div class="ec-chart-empty">No data in the selected range.</div>
        @endif
    </div>

    <div class="ec-card">
        <h6 style="margin-bottom: 16px;">Deduction Breakdown</h6>
        @if ($totalDeductions <= 0)
            <div class="ec-chart-empty">No deductions logged yet for these categories.</div>
        @else
            @foreach ($deductionBreakdown as $row)
                @php
                    $pct = $totalDeductions > 0 ? ($row['amount'] / $totalDeductions) * 100 : 0;
                @endphp
                <div class="ec-breakdown-row">
                    <div class="ec-breakdown-label">{{ $row['category'] }}</div>
                    <div class="ec-breakdown-bar-track">
                        <div class="ec-breakdown-bar-fill" style="width: {{ max($pct, $row['amount'] > 0 ? 1.5 : 0) }}%;"></div>
                    </div>
                    <div class="ec-breakdown-amount">PKR {{ number_format($row['amount'], 2) }}</div>
                    <div class="ec-breakdown-pct">{{ number_format($pct, 1) }}%</div>
                </div>
            @endforeach
        @endif
    </div>

    <div class="ec-card mt-3">
        <h6 class="mb-3">Per-Transaction Profitability</h6>
        <p class="ec-sub mb-3">{{ $filterText }} &middot; COGS is each sale's BOM cost snapshot at the time it was logged, not today's pricing.</p>
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Channel</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Total Revenue</th>
                        <th class="text-end">Total COGS</th>
                        <th class="text-end">Gross Profit</th>
                        <th class="text-end">Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        @php
                            $saleRevenue = (float) $sale->total_price;
                            $saleCogs = (float) $sale->quantity * (float) ($sale->unit_cogs ?? 0);
                            $saleGrossProfit = $saleRevenue - $saleCogs;
                            $saleMargin = $saleRevenue > 0 ? ($saleGrossProfit / $saleRevenue) * 100 : 0;
                        @endphp
                        <tr>
                            <td>{{ $sale->created_at->format('d M Y') }}</td>
                            <td>{{ $sale->product->name ?? '—' }}</td>
                            <td><span class="ec-channel-badge {{ $channelBadgeClasses[$sale->channel] ?? 'ec-channel-backbar' }}">{{ $sale->channel }}</span></td>
                            <td class="text-end">{{ number_format($sale->quantity, 0) }}</td>
                            <td class="text-end">PKR {{ number_format($saleRevenue, 2) }}</td>
                            <td class="text-end">PKR {{ number_format($saleCogs, 2) }}</td>
                            <td class="text-end {{ $saleGrossProfit >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($saleGrossProfit, 2) }}</td>
                            <td class="text-end {{ $saleMargin >= 0 ? 'ec-positive' : 'ec-negative' }}">{{ number_format($saleMargin, 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">No sales in this date range</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if (count($chartLabels) > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (function () {
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
                                        return '  Net Profit: PKR ' + Number(context.parsed.y).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
                                        return 'PKR ' + Number(value).toLocaleString('en-US');
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
