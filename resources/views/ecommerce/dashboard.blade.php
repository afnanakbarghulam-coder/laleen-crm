@extends('layouts.app')
@section('title', 'Ecommerce P&L')

@include('ecommerce._styles')

@section('content')
    <div class="ec-header">
        <div>
            <h4>Unified Ecommerce P&amp;L</h4>
            <p>Revenue, cost of goods, operating expenses and the 3-way partner split in one view.</p>
        </div>
    </div>

    @include('ecommerce._nav')

    <div class="ec-card">
        <form method="GET" action="{{ route('ecommerce.dashboard') }}" class="row g-3 align-items-end">
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">From</label>
                <input type="date" name="from" class="form-control ec-form-control" value="{{ $from->toDateString() }}">
            </div>
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">To</label>
                <input type="date" name="to" class="form-control ec-form-control" value="{{ $to->toDateString() }}">
            </div>
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">Product (unit cost source)</label>
                <select name="product_id" class="form-select ec-form-select">
                    @forelse ($products as $product)
                        <option value="{{ $product->id }}" {{ $productId == $product->id ? 'selected' : '' }}>
                            {{ $product->name }} (PKR {{ number_format($product->total_cogs, 2) }}/unit)
                        </option>
                    @empty
                        <option value="">No products yet</option>
                    @endforelse
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">Gross Revenue (PKR)</label>
                <input type="number" step="0.01" min="0" name="gross_revenue" class="form-control ec-form-control" value="{{ $grossRevenue }}">
            </div>
            <div class="col-auto">
                <label class="form-label small text-muted mb-1">Units Sold</label>
                <input type="number" min="0" name="units_sold" class="form-control ec-form-control" value="{{ $unitsSold }}">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Recalculate</button>
            </div>
        </form>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Gross Revenue</h6>
                <div class="ec-value">PKR {{ number_format($grossRevenue, 2) }}</div>
                <div class="ec-sub">{{ $unitsSold }} units @ PKR {{ number_format($unitCost, 2) }} unit cost</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total COGS</h6>
                <div class="ec-value">PKR {{ number_format($totalCogs, 2) }}</div>
                <div class="ec-sub">Units Sold &times; Calculated Unit Cost</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Gross Operating Profit</h6>
                <div class="ec-value {{ $grossOperatingProfit >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($grossOperatingProfit, 2) }}</div>
                <div class="ec-sub">Gross Revenue &minus; Total COGS</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total Ecommerce OpEx</h6>
                <div class="ec-value">PKR {{ number_format($totalOpex, 2) }}</div>
                <div class="ec-sub">{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Net Ecommerce Profit</h6>
                <div class="ec-value {{ $netProfit >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($netProfit, 2) }}</div>
                <div class="ec-sub">Gross Operating Profit &minus; Total OpEx</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Partner Profit Share</h6>
                <div class="ec-value {{ $equalShare >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($equalShare, 2) }}</div>
                <div class="ec-sub">Net Profit &divide; {{ max($partners->count(), 1) }} partners</div>
            </div>
        </div>
    </div>

    <div class="ec-card">
        <h6 class="mb-3">Partner Payout Preview</h6>
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Partner</th>
                        <th>Equity %</th>
                        <th>Equal Share (Net Profit / {{ max($partners->count(), 1) }})</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($partners as $partner)
                        <tr>
                            <td>{{ $partner->name }}</td>
                            <td>{{ number_format($partner->equity_percentage, 2) }}%</td>
                            <td class="{{ $equalShare >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($equalShare, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted">No partners added yet &mdash; add them on the Partner Ledger tab.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
