@extends('layouts.app')
@section('title', 'Net Profit')

@include('ecommerce._styles')

@section('content')
    <div class="ec-header">
        <div>
            <h4>Net Profit</h4>
            <p>True bottom line after stickers, bottles, labels, courier, packaging, taxes, and ad spend are deducted from sales revenue.</p>
        </div>
    </div>

    @include('ecommerce._nav')

    <div class="row">
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total Revenue</h6>
                <div class="ec-value">PKR {{ number_format($totalRevenue, 2) }}</div>
                <div class="ec-sub">All completed sales, all channels</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total Deductions</h6>
                <div class="ec-value ec-negative">PKR {{ number_format($totalDeductions, 2) }}</div>
                <div class="ec-sub">Stickers, bottles, labels, courier, packaging, taxes &amp; Meta ads</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card ec-card-highlight">
                <h6>Net Profit</h6>
                <div class="ec-value {{ $netProfit >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($netProfit, 2) }}</div>
                <div class="ec-sub">Revenue &minus; Deductions &middot; {{ number_format($netMargin, 1) }}% margin</div>
            </div>
        </div>
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
@endsection
