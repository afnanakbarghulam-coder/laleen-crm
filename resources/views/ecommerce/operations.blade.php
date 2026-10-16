@extends('layouts.app')
@section('title', 'Operations & Growth')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Operations &amp; Growth</h4>
            <p>The engines that lift AOV, bring customers back, and keep stock ahead of demand.</p>
        </div>
        <a href="{{ route('ecommerce.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Ecommerce</a>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('ecommerce.sales.index') }}#bundles" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-package"></i></div>
                <h6 class="mb-1">Bundles &amp; Kits</h6>
                <p class="text-muted small mb-2">Multi-item kits at one price. Selling one decrements every component and splits revenue across them.</p>
                <span class="ec-ops-stat">{{ $bundleCount }} bundle(s) configured</span>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('ecommerce.sales.index') }}#refill-queue" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-conversation"></i></div>
                <h6 class="mb-1">30-Day Refill Queue</h6>
                <p class="text-muted small mb-2">Customers 25&ndash;35 days past purchase who haven't re-ordered, with one-tap WhatsApp scripts.</p>
                <span class="ec-ops-stat {{ $refillDueCount > 0 ? 'ec-ops-stat-action' : '' }}">{{ $refillDueCount }} customer(s) due</span>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('ecommerce.stock.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-trending-down"></i></div>
                <h6 class="mb-1">Velocity Stock Alerts</h6>
                <p class="text-muted small mb-2">30-day sales velocity and days-of-cover per SKU, flagging anything inside the reorder window.</p>
                <span class="ec-ops-stat {{ $reorderCount > 0 ? 'ec-ops-stat-action' : '' }}">{{ $reorderCount }} SKU(s) need reorder</span>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('ecommerce.sales.index') }}#backbar-conversion" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-transfer-alt"></i></div>
                <h6 class="mb-1">Backbar Conversion</h6>
                <p class="text-muted small mb-0">Whether stylists are turning wash-basin treatments into front-desk retail sales.</p>
            </a>
        </div>
    </div>

    <style>
        .ec-ops-stat {
            display: inline-block;
            font-size: 11.5px;
            font-weight: 600;
            padding: 3px 11px;
            border-radius: 20px;
            background: rgba(217, 143, 131, 0.1);
            border: 1px solid rgba(217, 143, 131, 0.2);
            color: #c9a39a;
        }

        .ec-ops-stat-action {
            background: rgba(168, 82, 74, 0.16);
            border-color: rgba(168, 82, 74, 0.35);
            color: #e0938a;
        }
    </style>
@endsection
