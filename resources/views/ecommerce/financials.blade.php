@extends('layouts.app')
@section('title', 'Financials & Inventory')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Financials &amp; Inventory</h4>
            <p>Partner capital, operating costs, unit economics, stock positions and the true bottom line.</p>
        </div>
        <a href="{{ route('ecommerce.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Ecommerce</a>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('ecommerce.partners.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-group"></i></div>
                <h6 class="mb-1">Partner Ledger</h6>
                <p class="text-muted small mb-0">Capital injections, distributions, equalisation and available runway.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('ecommerce.expenses.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-receipt"></i></div>
                <h6 class="mb-1">Expenses</h6>
                <p class="text-muted small mb-0">Online-only operating costs and raw material restocks, isolated from salon spend.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('ecommerce.inventory.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-box"></i></div>
                <h6 class="mb-1">Inventory &amp; Production</h6>
                <p class="text-muted small mb-0">Two-tier raw materials and finished goods, plus the production runs between them.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('ecommerce.pricing.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-calculator"></i></div>
                <h6 class="mb-1">Pricing &amp; Profit</h6>
                <p class="text-muted small mb-0">The BOM cost model — the single source of truth for unit COGS and margin.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('ecommerce.stock.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-layer"></i></div>
                <h6 class="mb-1">Stock Levels</h6>
                <p class="text-muted small mb-0">Pakistan and Qatar positions, capital tied up in stock, and velocity cover.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('ecommerce.sales.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-cart"></i></div>
                <h6 class="mb-1">Sales &amp; Usage</h6>
                <p class="text-muted small mb-0">Retail sales, backbar use, promos and damage, routed by channel.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('ecommerce.net-profit.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-line-chart"></i></div>
                <h6 class="mb-1">Net Profit</h6>
                <p class="text-muted small mb-0">Revenue less unit COGS, per-sale costs and operating expenses — split by partner equity.</p>
            </a>
        </div>
    </div>
@endsection
