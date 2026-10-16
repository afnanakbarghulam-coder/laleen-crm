@extends('layouts.app')
@section('title', 'Ecommerce')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Ecommerce</h4>
            <p>Partner capital, unit economics, inventory, growth engines, and the content that feeds them.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <a href="{{ route('ecommerce.partners.index') }}" class="kpi-hub-card kpi-master-card">
                <div class="kpi-hub-icon kpi-hub-icon-lg"><i class="bx bx-wallet"></i></div>
                <h5 class="mb-1">Financials &amp; Inventory</h5>
                <p class="text-muted mb-2">Partner ledger, expenses, pricing, stock, sales and net profit.</p>
                <span class="ec-hub-stat {{ $runway >= 0 ? 'ec-positive' : 'ec-negative' }}">
                    Runway: PKR {{ number_format($runway, 2) }}
                </span>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('ecommerce.marketing') }}" class="kpi-hub-card kpi-master-card">
                <div class="kpi-hub-icon kpi-hub-icon-lg"><i class="bx bx-rocket"></i></div>
                <h5 class="mb-1">Marketing &amp; Ads</h5>
                <p class="text-muted mb-2">Weekly content factory and the monthly ads plan.</p>
                <span class="ec-hub-stat">
                    {{ $contentLive }}/{{ $contentSlots }} slots live &middot; {{ $adsInFlight }} ad(s) in flight
                </span>
            </a>
        </div>

        <div class="col-md-4">
            <a href="{{ route('ecommerce.operations') }}" class="kpi-hub-card kpi-master-card">
                <div class="kpi-hub-icon kpi-hub-icon-lg"><i class="bx bx-trending-up"></i></div>
                <h5 class="mb-1">Operations &amp; Growth</h5>
                <p class="text-muted mb-2">Bundles, the refill queue and velocity stock alerts.</p>
                <span class="ec-hub-stat {{ $reorderCount > 0 ? 'ec-negative' : 'ec-positive' }}">
                    {{ $bundleCount }} bundle(s) &middot; {{ $reorderCount }} SKU(s) need reorder
                </span>
            </a>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12">
            <a href="{{ route('ecommerce.dashboard') }}" class="kpi-hub-card ec-hub-wide">
                <div class="kpi-hub-icon"><i class="bx bx-bar-chart-alt-2"></i></div>
                <div>
                    <h6 class="mb-1">Ecommerce KPIs</h6>
                    <p class="text-muted small mb-0">Blended MER, CAC, AOV and repeat purchase rate from your daily Shopify + Meta snapshots.</p>
                </div>
                <i class="bx bx-chevron-right ec-hub-chevron"></i>
            </a>
        </div>
    </div>

    <style>
        .kpi-master-card {
            padding: 32px 28px;
            text-align: center;
        }

        .kpi-hub-icon-lg {
            width: 64px;
            height: 64px;
            font-size: 30px;
            border-radius: 16px;
            margin: 0 auto 18px;
        }

        .kpi-master-card h5 {
            font-size: 20px;
        }

        .ec-hub-stat {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(217, 143, 131, 0.1);
            border: 1px solid rgba(217, 143, 131, 0.2);
        }

        .ec-hub-wide {
            display: flex;
            align-items: center;
            gap: 18px;
            text-align: left;
        }

        .ec-hub-wide > div:nth-child(2) { flex: 1; min-width: 0; }

        .ec-hub-chevron {
            font-size: 24px;
            color: #d98f83;
            flex-shrink: 0;
        }
    </style>
@endsection
