@extends('layouts.app')
@section('title', 'Ecommerce')

@section('content')
    <div class="kpi-header mb-4">
        <div>
            <h4>Ecommerce</h4>
            <p>Partner capital, unified customer LTV &amp; sales, creative growth engines, and acquisition performance.</p>
        </div>
    </div>

    <div class="row g-4">
        {{-- Card 1: Financials & Inventory --}}
        <div class="col-md-6">
            <a href="{{ route('ecommerce.partners.index') }}" class="ec-workstation-card bg-white/5 hover:bg-white/10 backdrop-blur-lg border border-[#d98f83]/20 shadow-xl">
                <div class="ec-workstation-top">
                    <div class="ec-workstation-icon">
                        <i class="bx bx-wallet"></i>
                    </div>
                    <span class="ec-workstation-arrow" aria-hidden="true">&rarr;</span>
                </div>
                <div class="ec-workstation-body">
                    <h5 class="ec-workstation-title">Financials &amp; Inventory</h5>
                    <p class="ec-workstation-desc">
                        Partner ledger, capital runway, expenses, two-tier inventory &amp; batch production, and stock levels.
                    </p>
                </div>
                <div class="ec-workstation-footer">
                    <span class="ec-workstation-badge {{ $runway >= 0 ? 'ec-badge-emerald' : 'ec-badge-danger' }}">
                        Runway: PKR {{ number_format($runway, 2) }}
                    </span>
                </div>
            </a>
        </div>

        {{-- Card 2: Customers & Sales --}}
        <div class="col-md-6">
            <a href="{{ route('ecommerce.customers.index') }}" class="ec-workstation-card bg-white/5 hover:bg-white/10 backdrop-blur-lg border border-[#d98f83]/20 shadow-xl">
                <div class="ec-workstation-top">
                    <div class="ec-workstation-icon">
                        <i class="bx bx-user-check"></i>
                    </div>
                    <span class="ec-workstation-arrow" aria-hidden="true">&rarr;</span>
                </div>
                <div class="ec-workstation-body">
                    <h5 class="ec-workstation-title">Customers &amp; Sales</h5>
                    <p class="ec-workstation-desc">
                        Unified customer directory, sales &amp; usage, refill queue, and net profit.
                    </p>
                </div>
                <div class="ec-workstation-footer">
                    <span class="ec-workstation-badge ec-badge-emerald">
                        {{ number_format($totalCustomersCount, 0) }} Profiles &middot; {{ number_format($totalSalesCount, 0) }} Orders
                    </span>
                </div>
            </a>
        </div>

        {{-- Card 3: Marketing & Ads --}}
        <div class="col-md-6">
            <a href="{{ route('ecommerce.marketing') }}" class="ec-workstation-card bg-white/5 hover:bg-white/10 backdrop-blur-lg border border-[#d98f83]/20 shadow-xl">
                <div class="ec-workstation-top">
                    <div class="ec-workstation-icon">
                        <i class="bx bx-rocket"></i>
                    </div>
                    <span class="ec-workstation-arrow" aria-hidden="true">&rarr;</span>
                </div>
                <div class="ec-workstation-body">
                    <h5 class="ec-workstation-title">Marketing &amp; Ads</h5>
                    <p class="ec-workstation-desc">
                        Weekly content calendar, Reels &amp; Stories production schedule, and 70/20/10 creative ad Kanban.
                    </p>
                </div>
                <div class="ec-workstation-footer">
                    <span class="ec-workstation-badge">
                        {{ $liveSlotsCount }}/7 slots live &middot; {{ $inFlightAdsCount }} ad(s) in flight
                    </span>
                </div>
            </a>
        </div>

        {{-- Card 4: Performance & KPIs --}}
        <div class="col-md-6">
            <a href="{{ route('ecommerce.dashboard') }}" class="ec-workstation-card bg-white/5 hover:bg-white/10 backdrop-blur-lg border border-[#d98f83]/20 shadow-xl">
                <div class="ec-workstation-top">
                    <div class="ec-workstation-icon">
                        <i class="bx bx-bar-chart-alt-2"></i>
                    </div>
                    <span class="ec-workstation-arrow" aria-hidden="true">&rarr;</span>
                </div>
                <div class="ec-workstation-body">
                    <h5 class="ec-workstation-title">Performance &amp; KPIs</h5>
                    <p class="ec-workstation-desc">
                        Blended CAC, MER, AOV, salon backbar conversion efficiency, and daily Shopify + Meta snapshots.
                    </p>
                </div>
                <div class="ec-workstation-footer">
                    <span class="ec-workstation-badge ec-badge-emerald">
                        AOV: PKR {{ number_format($aov, 0) }}
                    </span>
                </div>
            </a>
        </div>
    </div>

    <style>
        .ec-workstation-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            padding: 26px 28px;
            /* Frosted glassmorphism translucent fill */
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.06) 0%, rgba(37, 29, 28, 0.42) 100%);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            /* Delicate glass catch-light border */
            border: 1px solid rgba(217, 143, 131, 0.2) !important;
            border-top-color: rgba(255, 255, 255, 0.14) !important;
            border-radius: 16px;
            text-decoration: none;
            color: inherit;
            /* shadow-xl floating depth */
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.38),
                        0 8px 10px -6px rgba(0, 0, 0, 0.28),
                        inset 0 1px 0 0 rgba(255, 255, 255, 0.08);
            transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1),
                        background 0.22s ease,
                        border-color 0.22s ease,
                        box-shadow 0.22s ease;
        }

        .ec-workstation-card:hover {
            transform: translateY(-3px);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.1) 0%, rgba(37, 29, 28, 0.56) 100%);
            border-color: rgba(217, 143, 131, 0.36) !important;
            border-top-color: rgba(255, 255, 255, 0.22) !important;
            box-shadow: 0 0 0 1px rgba(217, 143, 131, 0.3),
                        0 24px 34px -6px rgba(0, 0, 0, 0.48),
                        inset 0 1px 0 0 rgba(255, 255, 255, 0.14);
            color: inherit;
        }

        .ec-workstation-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .ec-workstation-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            font-size: 25px;
            border-radius: 14px;
            background: rgba(217, 143, 131, 0.14);
            border: 1px solid rgba(217, 143, 131, 0.26);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
            color: #e79a91;
            transition: background 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
        }

        .ec-workstation-card:hover .ec-workstation-icon {
            background: rgba(217, 143, 131, 0.22);
            border-color: rgba(217, 143, 131, 0.4);
        }

        .ec-workstation-arrow {
            font-size: 20px;
            font-weight: 600;
            color: #a89692;
            transition: color 0.2s ease, transform 0.2s ease;
        }

        .ec-workstation-card:hover .ec-workstation-arrow {
            color: #e79a91;
            transform: translateX(3px);
        }

        .ec-workstation-body {
            flex: 1;
            margin-bottom: 20px;
        }

        .ec-workstation-title {
            font-size: 19px;
            font-weight: 700;
            color: #e79a91;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
        }

        .ec-workstation-desc {
            font-size: 13.5px;
            line-height: 1.55;
            color: #c2b2ae;
            margin-bottom: 0;
        }

        .ec-workstation-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .ec-workstation-badge {
            display: inline-flex;
            align-items: center;
            font-size: 12px;
            font-weight: 600;
            padding: 5px 13px;
            border-radius: 20px;
            background: rgba(217, 143, 131, 0.14);
            border: 1px solid rgba(217, 143, 131, 0.28);
            color: #f0b0a7;
            font-variant-numeric: tabular-nums;
            backdrop-filter: blur(8px);
        }

        .ec-workstation-badge.ec-badge-emerald {
            background: rgba(142, 168, 138, 0.2);
            border-color: rgba(142, 168, 138, 0.42);
            color: #a3c49e;
        }

        .ec-workstation-badge.ec-badge-danger {
            background: rgba(168, 82, 74, 0.22);
            border-color: rgba(168, 82, 74, 0.45);
            color: #eba199;
        }
    </style>
@endsection
