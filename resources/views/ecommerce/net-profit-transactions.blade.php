@extends('layouts.app')
@section('title', 'Per-Transaction Profitability')

@include('ecommerce._styles')

@php
    $channelBadgeClasses = \App\Models\EcommerceSale::CHANNEL_BADGE_CLASSES;
@endphp

@section('content')
    <div class="mb-3">
        <a
            href="{{ route('ecommerce.net-profit.index', array_filter(['start_date' => $startDate, 'end_date' => $endDate])) }}"
            class="d-inline-flex align-items-center gap-2 text-decoration-none fw-semibold"
            style="color: #d98f83; font-size: 13px; padding: 6px 12px; border-radius: 8px; background: rgba(217, 143, 131, 0.08); border: 1px solid rgba(217, 143, 131, 0.25); transition: background 0.15s ease;"
            onmouseover="this.style.background='rgba(217, 143, 131, 0.16)'"
            onmouseout="this.style.background='rgba(217, 143, 131, 0.08)'"
        >
            <span>&larr; Back to Net Profit Dashboard</span>
        </a>
    </div>

    <div class="ec-header">
        <div>
            <div class="ec-sub text-uppercase fw-bold mb-1" style="font-size: 11px; letter-spacing: .08em; color: var(--ec-gold);">Customers &amp; Sales &middot; Net Profit</div>
            <h4>Per-Transaction Profitability</h4>
            <p>{{ $filterText }} &middot; Each cost column is a snapshot of the product's BOM at the time the sale was logged, not today's pricing.</p>
        </div>

        <form method="GET" action="{{ route('ecommerce.profit.transactions') }}" class="d-flex align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <label for="startDateFilter" class="ec-sub mb-0">From</label>
                <input type="date" name="start_date" id="startDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ $startDate }}">
                <label for="endDateFilter" class="ec-sub mb-0">To</label>
                <input type="date" name="end_date" id="endDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ $endDate }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Apply Filter</button>
        </form>
    </div>

    <div class="ec-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h6 class="mb-0">Per-Transaction Profitability Ledger</h6>
            <span class="ec-sub mt-0">Showing {{ $sales->count() }} of {{ $sales->total() }} transactions</span>
        </div>

        <div class="table-responsive" style="overflow-x: auto;">
            <table class="table ec-table align-middle text-nowrap mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Channel</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Total Revenue</th>
                        <th class="text-end">Liquid Cost</th>
                        <th class="text-end">Bottle Cost</th>
                        <th class="text-end">Label Cost</th>
                        <th class="text-end">Pump Cost</th>
                        <th class="text-end">Box Cost</th>
                        <th class="text-end">Shipping</th>
                        <th class="text-end">Taxes</th>
                        <th class="text-end">Meta Ads</th>
                        <th class="text-end">Gross Profit</th>
                        <th class="text-end">Gross Margin %</th>
                        <th class="text-end">True Net Profit</th>
                        <th class="text-end">Net Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        @php
                            $saleRevenue = (float) $sale->total_price;
                            $saleLiquidCost = (float) $sale->quantity * (float) ($sale->unit_liquid_cost ?? 0);
                            $saleBottleCost = (float) $sale->quantity * (float) ($sale->unit_bottle_cost ?? 0);
                            $saleLabelCost = (float) $sale->quantity * (float) ($sale->unit_label_cost ?? 0);
                            $salePumpCost = (float) $sale->quantity * (float) ($sale->unit_pump_cost ?? 0);
                            $saleBoxCost = (float) $sale->quantity * (float) ($sale->unit_outer_box_cost ?? 0);
                            $itemizedCogs = $saleLiquidCost + $saleBottleCost + $saleLabelCost + $salePumpCost + $saleBoxCost;
                            $saleCogs = $itemizedCogs > 0 ? $itemizedCogs : ((float) $sale->quantity * (float) ($sale->unit_cogs ?? 0));
                            $saleGrossProfit = $saleRevenue - $saleCogs;
                            $saleGrossMargin = $saleRevenue > 0 ? ($saleGrossProfit / $saleRevenue) * 100 : 0;

                            $saleShipping = (float) ($sale->shipping_cost ?? 0);
                            $saleTax = (float) ($sale->tax_amount ?? 0);
                            $saleMetaAd = (float) ($sale->meta_ad_allocation ?? 0);
                            $saleTrueNetProfit = $saleGrossProfit - $saleShipping - $saleTax - $saleMetaAd;
                            $saleNetMargin = $saleRevenue > 0 ? ($saleTrueNetProfit / $saleRevenue) * 100 : 0;
                        @endphp
                        <tr>
                            <td>{{ $sale->created_at->format('d M Y') }}</td>
                            <td>{{ $sale->product->name ?? '—' }}</td>
                            <td><span class="ec-channel-badge {{ $channelBadgeClasses[$sale->channel] ?? 'ec-channel-backbar' }}">{{ $sale->channel }}</span></td>
                            <td class="text-end">{{ number_format($sale->quantity, 0) }}</td>
                            <td class="text-end">PKR {{ number_format($saleRevenue, 2) }}</td>
                            <td class="text-end">{{ $saleLiquidCost > 0 ? 'PKR ' . number_format($saleLiquidCost, 2) : '—' }}</td>
                            <td class="text-end">{{ $saleBottleCost > 0 ? 'PKR ' . number_format($saleBottleCost, 2) : '—' }}</td>
                            <td class="text-end">{{ $saleLabelCost > 0 ? 'PKR ' . number_format($saleLabelCost, 2) : '—' }}</td>
                            <td class="text-end">{{ $salePumpCost > 0 ? 'PKR ' . number_format($salePumpCost, 2) : '—' }}</td>
                            <td class="text-end">{{ $saleBoxCost > 0 ? 'PKR ' . number_format($saleBoxCost, 2) : '—' }}</td>
                            <td class="text-end">{{ $saleShipping > 0 ? 'PKR ' . number_format($saleShipping, 2) : '—' }}</td>
                            <td class="text-end">{{ $saleTax > 0 ? 'PKR ' . number_format($saleTax, 2) : '—' }}</td>
                            <td class="text-end">{{ $saleMetaAd > 0 ? 'PKR ' . number_format($saleMetaAd, 2) : '—' }}</td>
                            <td class="text-end {{ $saleGrossProfit >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($saleGrossProfit, 2) }}</td>
                            <td class="text-end {{ $saleGrossMargin >= 0 ? 'ec-positive' : 'ec-negative' }}">{{ number_format($saleGrossMargin, 1) }}%</td>
                            <td class="text-end {{ $saleTrueNetProfit >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($saleTrueNetProfit, 2) }}</td>
                            <td class="text-end {{ $saleNetMargin >= 0 ? 'ec-positive' : 'ec-negative' }}">{{ number_format($saleNetMargin, 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="17" class="text-center text-muted py-4">No sales in this date range</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($sales->hasPages())
            <div class="mt-3 pt-3" style="border-top: 1px solid var(--ec-border);">
                {{ $sales->links() }}
            </div>
        @endif
    </div>
@endsection
