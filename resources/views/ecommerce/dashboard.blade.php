@extends('layouts.app')
@section('title', 'Ecommerce KPIs')

@include('ecommerce._styles')

@php
    $canEdit = auth()->check() && auth()->user()->canEdit('ecommerce');

    // null = no data (render an em dash), true = healthy, false = needs attention.
    $borderClass = fn ($healthy) => $healthy === null ? '' : ($healthy ? 'ec-kpi-good' : 'ec-kpi-bad');
@endphp

@section('content')
    <div class="ec-header">
        <div>
            <h4>Ecommerce KPIs</h4>
            <p>Live sales &amp; customer retention economics paired with your daily ad spend.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @moduleEdit('ecommerce')
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kpiModal">+ Enter Daily Ad Spend</button>
            @endmoduleEdit
            <a href="{{ route('ecommerce.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Ecommerce</a>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <p class="ec-sub mb-0">{{ $filterText }} &middot; {{ $metrics['day_count'] }} day(s) of ad spend logged</p>
        <form method="GET" action="{{ route('ecommerce.dashboard') }}" class="d-flex align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <label for="startDateFilter" class="ec-sub mb-0">From</label>
                <input type="date" name="start_date" id="startDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ $startDate }}">
                <label for="endDateFilter" class="ec-sub mb-0">To</label>
                <input type="date" name="end_date" id="endDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ $endDate }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Apply Filter</button>
        </form>
    </div>

    @unless ($metrics['has_ad_spend'] ?? $metrics['has_data'])
        <div class="ec-accounting-note">
            <span>📊</span>
            <div>
                <strong>No ad spend logged in this range.</strong>
                Click <strong>+ Enter Daily Ad Spend</strong> to calculate Return on Ad Spend (ROAS) and Cost Per New Customer alongside your live sales metrics.
            </div>
        </div>
    @endunless

    <style>
        .ec-kpi-grid-row {
            position: relative;
            z-index: 30;
            overflow: visible !important;
        }
        .ec-kpi-col {
            position: relative;
            overflow: visible !important;
            z-index: 1;
        }
        .ec-kpi-col:hover,
        .ec-kpi-col:focus-within {
            z-index: 50 !important;
        }
        .ec-kpi-card {
            position: relative;
            overflow: visible !important;
            z-index: 1;
        }
        .ec-kpi-card:hover,
        .ec-kpi-card:focus-within {
            z-index: 50 !important;
        }
        .ec-kpi-title-row {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .ec-info-trigger {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--ec-muted, #c9a39a);
            opacity: 0.75;
            cursor: pointer;
            transition: opacity 0.18s ease, color 0.18s ease;
            outline: none;
        }
        .ec-info-trigger:hover,
        .ec-info-trigger:focus,
        .ec-kpi-title-row.group:hover .ec-info-trigger {
            opacity: 1;
            color: #d98f83;
        }
        .ec-info-tooltip {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 8px;
            width: 18rem;
            padding: 16px;
            background: #1c1615;
            border: 1px solid rgba(217, 143, 131, 0.5);
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8);
            z-index: 100 !important;
            font-size: 0.875rem;
            font-weight: 400;
            line-height: 1.45;
            color: #e5e7eb;
            text-transform: none;
            letter-spacing: normal;
            text-align: left;
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.15s ease, visibility 0.15s ease;
        }
        .ec-info-tooltip hr {
            margin: 12px 0;
            border: 0;
            border-top: 1px solid rgba(217, 143, 131, 0.2);
            opacity: 1;
        }
        .ec-info-tooltip strong {
            color: #d98f83;
            font-weight: 600;
        }
        .ec-info-trigger:hover .ec-info-tooltip,
        .ec-info-trigger:focus .ec-info-tooltip,
        .ec-kpi-title-row.group:hover .ec-info-tooltip,
        .ec-info-tooltip.is-visible {
            display: block !important;
            opacity: 1;
            visibility: visible;
        }
        .ec-summary-strip-card {
            position: relative;
            z-index: 1;
        }
    </style>

    {{-- ============ The Big 4 Ecommerce KPIs ============ --}}
    <div class="row g-3 ec-kpi-grid-row">
        <div class="col-6 col-lg-3 ec-kpi-col relative hover:z-50">
            <div class="ec-card ec-kpi-card {{ $borderClass($metrics['mer_healthy']) }} relative hover:z-50 text-center d-flex flex-column justify-content-center py-4 h-100">
                <h6 class="text-uppercase mb-2 relative group flex items-center gap-2 ec-kpi-title-row" style="letter-spacing: 0.08em;">
                    <span>Return on Ad Spend (ROAS)</span>
                    <span class="ec-info-trigger relative flex items-center" tabindex="0" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false" @focus="tooltip = true" @blur="tooltip = false" aria-label="What is Return on Ad Spend (ROAS)?">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <div x-cloak class="ec-info-tooltip absolute top-full left-1/2 -translate-x-1/2 mt-2 w-72 p-4 bg-[#1c1615] border border-[#d98f83]/50 rounded-lg shadow-[0_10px_40px_rgba(0,0,0,0.8)] z-[100] text-sm text-gray-200 normal-case tracking-normal text-left" :class="{ 'is-visible': tooltip }" role="tooltip">
                            For every PKR 1 you feed into the advertising machine, how many PKR come back. If you spend 10k and make 30k, your ROAS is 3.0x.
                            <hr class="my-3 border-[#d98f83]/20">
                            <div><strong>Formula:</strong> Total Revenue &divide; Total Ad Spend</div>
                        </div>
                    </span>
                </h6>
                <div class="ec-kpi-value my-1" style="font-size: 2rem; font-weight: 800;">
                    {{ $metrics['mer'] === null ? '—' : number_format($metrics['mer'], 2) . 'x' }}
                </div>
                <div class="ec-sub mt-1">Target: &ge; {{ number_format($merTarget, 1) }}x</div>
                @if ($metrics['mer'] !== null)
                    <div class="ec-kpi-flag {{ $metrics['mer_healthy'] ? 'ec-positive' : 'ec-negative' }} mt-2">
                        {{ $metrics['mer_healthy'] ? '✓ Efficient' : '⚠ Below target' }}
                    </div>
                @endif
            </div>
        </div>

        <div class="col-6 col-lg-3 ec-kpi-col relative hover:z-50">
            <div class="ec-card ec-kpi-card {{ $borderClass($metrics['cac_healthy']) }} relative hover:z-50 text-center d-flex flex-column justify-content-center py-4 h-100">
                <h6 class="text-uppercase mb-2 relative group flex items-center gap-2 ec-kpi-title-row" style="letter-spacing: 0.08em;">
                    <span>Cost Per New Customer</span>
                    <span class="ec-info-trigger relative flex items-center" tabindex="0" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false" @focus="tooltip = true" @blur="tooltip = false" aria-label="What is Cost Per New Customer?">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <div x-cloak class="ec-info-tooltip absolute top-full left-1/2 -translate-x-1/2 mt-2 w-72 p-4 bg-[#1c1615] border border-[#d98f83]/50 rounded-lg shadow-[0_10px_40px_rgba(0,0,0,0.8)] z-[100] text-sm text-gray-200 normal-case tracking-normal text-left" :class="{ 'is-visible': tooltip }" role="tooltip">
                            The exact price you pay to acquire a brand-new buyer. As long as this stays lower than your Average Order Value (AOV), you profit on day one.
                            <hr class="my-3 border-[#d98f83]/20">
                            <div><strong>Formula:</strong> Total Ad Spend &divide; New Customers Acquired</div>
                        </div>
                    </span>
                </h6>
                <div class="ec-kpi-value my-1" style="font-size: 2rem; font-weight: 800;">
                    {{ $metrics['cac'] === null ? '—' : 'PKR ' . number_format($metrics['cac'], 2) }}
                </div>
                <div class="ec-sub mt-1">Target: &lt; {{ ($metrics['aov'] ?? 0) > 0 ? 'PKR ' . number_format($metrics['aov'], 0) . ' (AOV)' : 'AOV' }}</div>
                @if ($metrics['cac_healthy'] !== null)
                    <div class="ec-kpi-flag {{ $metrics['cac_healthy'] ? 'ec-positive' : 'ec-negative' }} mt-2">
                        {{ $metrics['cac_healthy'] ? '✓ Below AOV' : '⚠ Exceeds AOV' }}
                    </div>
                @endif
            </div>
        </div>

        <div class="col-6 col-lg-3 ec-kpi-col relative hover:z-50">
            <div class="ec-card ec-kpi-card relative hover:z-50 text-center d-flex flex-column justify-content-center py-4 h-100">
                <h6 class="text-uppercase mb-2 relative group flex items-center gap-2 ec-kpi-title-row" style="letter-spacing: 0.08em;">
                    <span>Average Order Value</span>
                    <span class="ec-info-trigger relative flex items-center" tabindex="0" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false" @focus="tooltip = true" @blur="tooltip = false" aria-label="What is Average Order Value?">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <div x-cloak class="ec-info-tooltip absolute top-full left-1/2 -translate-x-1/2 mt-2 w-72 p-4 bg-[#1c1615] border border-[#d98f83]/50 rounded-lg shadow-[0_10px_40px_rgba(0,0,0,0.8)] z-[100] text-sm text-gray-200 normal-case tracking-normal text-left" :class="{ 'is-visible': tooltip }" role="tooltip">
                            The average size of a customer's cart. Push this higher through bundles so you can afford to spend more on ads to acquire a buyer.
                            <hr class="my-3 border-[#d98f83]/20">
                            <div><strong>Formula:</strong> Total Revenue &divide; Total Orders</div>
                        </div>
                    </span>
                </h6>
                <div class="ec-kpi-value my-1" style="font-size: 2rem; font-weight: 800;">
                    PKR {{ number_format($metrics['aov'] ?? 0, 2) }}
                </div>
                <div class="ec-sub mt-1">{{ number_format($metrics['total_orders'] ?? 0) }} orders in range</div>
                @if (($metrics['total_orders'] ?? 0) > 0)
                    <div class="ec-kpi-flag ec-sub mt-2">✓ Live from Sales Ledger</div>
                @endif
            </div>
        </div>

        <div class="col-6 col-lg-3 ec-kpi-col relative hover:z-50">
            <div class="ec-card ec-kpi-card {{ $borderClass($metrics['repeat_rate_healthy']) }} relative hover:z-50 text-center d-flex flex-column justify-content-center py-4 h-100">
                <h6 class="text-uppercase mb-2 relative group flex items-center gap-2 ec-kpi-title-row" style="letter-spacing: 0.08em;">
                    <span>Repeat Customer Rate</span>
                    <span class="ec-info-trigger relative flex items-center" tabindex="0" x-data="{ tooltip: false }" @mouseenter="tooltip = true" @mouseleave="tooltip = false" @focus="tooltip = true" @blur="tooltip = false" aria-label="What is Repeat Customer Rate?">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <div x-cloak class="ec-info-tooltip absolute top-full left-1/2 -translate-x-1/2 mt-2 w-72 p-4 bg-[#1c1615] border border-[#d98f83]/50 rounded-lg shadow-[0_10px_40px_rgba(0,0,0,0.8)] z-[100] text-sm text-gray-200 normal-case tracking-normal text-left" :class="{ 'is-visible': tooltip }" role="tooltip">
                            The percentage of your buyer base that returns for a second purchase. Proves product quality and builds pure-margin lifetime value.
                            <hr class="my-3 border-[#d98f83]/20">
                            <div><strong>Formula:</strong> Repeat Customers &divide; Total Unique Customers</div>
                        </div>
                    </span>
                </h6>
                <div class="ec-kpi-value my-1" style="font-size: 2rem; font-weight: 800;">
                    {{ number_format($metrics['repeat_rate'] ?? 0, 1) }}%
                </div>
                <div class="ec-sub mt-1">Target: &ge; {{ number_format($repeatRateTarget, 0) }}%</div>
                @if ($metrics['repeat_rate'] !== null)
                    <div class="ec-kpi-flag {{ $metrics['repeat_rate_healthy'] ? 'ec-positive' : 'ec-negative' }} mt-2">
                        {{ $metrics['repeat_rate_healthy'] ? '✓ Healthy retention' : '⚠ Below target' }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="ec-card ec-summary-strip-card mt-3">
        <div class="d-flex flex-wrap justify-content-between gap-4">
            <div><span class="ec-sub">Revenue</span><div class="ec-stock-emphasis">PKR {{ number_format($metrics['total_revenue'], 2) }}</div></div>
            <div><span class="ec-sub">Ad Spend</span><div class="ec-stock-emphasis">PKR {{ number_format($metrics['total_ad_spend'], 2) }}</div></div>
            <div><span class="ec-sub">Orders</span><div class="ec-stock-emphasis">{{ number_format($metrics['total_orders']) }}</div></div>
            <div><span class="ec-sub">New Customers</span><div class="ec-stock-emphasis">{{ number_format($metrics['new_customers']) }}</div></div>
            <div><span class="ec-sub">Repeat Customers</span><div class="ec-stock-emphasis">{{ number_format($metrics['repeat_customers']) }}</div></div>
        </div>
    </div>

    {{-- ============ Logged Daily Ad Spend ============ --}}
    @if ($kpiRows->isNotEmpty())
        <div class="ec-card mt-3">
            <h6 class="mb-3">Daily Ad Spend Log</h6>
            <div class="table-responsive">
                <table class="table ec-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-end">Ad Spend</th>
                            <th class="text-end">Orders</th>
                            <th class="text-end">ROAS</th>
                            @moduleEdit('ecommerce')<th></th>@endmoduleEdit
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($kpiRows as $row)
                            @php $rowRoas = (float) $row->total_ad_spend > 0 ? (float) $row->total_revenue / (float) $row->total_ad_spend : null; @endphp
                            <tr>
                                <td>{{ $row->date->format('d M Y') }}</td>
                                <td class="text-end">PKR {{ number_format($row->total_revenue, 2) }}</td>
                                <td class="text-end">PKR {{ number_format($row->total_ad_spend, 2) }}</td>
                                <td class="text-end">{{ number_format($row->total_orders) }}</td>
                                <td class="text-end {{ $rowRoas === null ? '' : ($rowRoas >= $merTarget ? 'ec-positive' : 'ec-negative') }}">
                                    {{ $rowRoas === null ? '—' : number_format($rowRoas, 2) . 'x' }}
                                </td>
                                @moduleEdit('ecommerce')
                                    <td class="text-end">
                                        <form action="{{ route('ecommerce.kpis.destroy', $row->id) }}" method="POST" onsubmit="return confirm('Delete this ad spend entry?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                @endmoduleEdit
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @moduleEdit('ecommerce')
        {{-- Enter Daily Ad Spend Modal --}}
        <div class="modal fade" id="kpiModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.kpis.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Enter Daily Ad Spend</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="ec-sub mb-3">Revenue, order counts, and customer retention are calculated automatically from your Sales &amp; Customers database. Enter the day's total ad spend below.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Date</label>
                                    <input type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Total Ad Spend (PKR)</label>
                                    <input type="number" step="0.01" min="0" name="total_ad_spend" id="kpiAdSpend" class="form-control" placeholder="0.00" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save Ad Spend</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            document.getElementById('kpiModal').addEventListener('hidden.bs.modal', function () {
                this.querySelector('form').reset();
            });
        </script>
    @endmoduleEdit
@endsection
