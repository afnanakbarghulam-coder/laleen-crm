@extends('layouts.app')
@section('title', ($customer->name ?: 'Customer') . ' — Unified LTV Profile')

@include('ecommerce._styles')

@php
    $channelBadgeClasses = \App\Models\EcommerceSale::CHANNEL_BADGE_CLASSES;

    $daysSinceLastPurchase = $customer->last_purchase_at
        ? (int) $customer->last_purchase_at->copy()->startOfDay()->diffInDays(now()->startOfDay())
        : null;

    if ($daysSinceLastPurchase === null || $daysSinceLastPurchase > 45) {
        $healthLabel = 'Churned';
        $healthClass = 'ec-health-churned';
    } elseif ($daysSinceLastPurchase >= 31) {
        $healthLabel = 'Slipping';
        $healthClass = 'ec-health-slipping';
    } else {
        $healthLabel = 'Active';
        $healthClass = 'ec-health-active';
    }

    $isVip = $customer->total_orders >= 2;
    $waName = $customer->name ?: 'there';
    $latestProduct = $customer->latest_product;
    $waText = "Hi {$waName}, checking in from Laleen! It's been a little while since your last order of {$latestProduct}...";
    $waLink = 'https://wa.me/' . $customer->phone . '?text=' . rawurlencode($waText);
    $avgOrderValue = $customer->total_orders > 0 ? ((float) $customer->total_spend / $customer->total_orders) : 0;
@endphp

@section('content')
    <div class="ec-header">
        <div>
            <div class="ec-sub text-uppercase fw-bold mb-1" style="font-size: 11px; letter-spacing: .08em; color: var(--ec-gold);">
                Customers &amp; Sales &middot; Unified Identity Profile
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h4 class="mb-0">{{ $customer->name ?: 'Unnamed Customer' }}</h4>
                <span class="ec-health-badge {{ $healthClass }}">{{ $healthLabel }}</span>
                @if ($isVip)
                    <span class="ec-vip-badge mb-0" style="padding: 2px 10px; font-size: 11px;">🌟 VIP Repeat Buyer</span>
                @endif
            </div>
            <p class="mt-1">
                <span style="font-variant-numeric: tabular-nums;">+{{ $customer->phone }}</span>
                @if ($customer->email)
                    &middot; {{ $customer->email }}
                @endif
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('ecommerce.customers.index') }}" class="btn btn-outline-secondary btn-sm">
                &larr; Back to Directory
            </a>
            <a href="{{ $waLink }}" target="_blank" rel="noopener" class="ec-wa-action-btn">
                <i class="bx bxl-whatsapp" style="font-size: 16px;"></i> Message on WhatsApp
            </a>
        </div>
    </div>

    {{-- Customer Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="ec-card ec-card-highlight mb-0 h-100">
                <h6>Lifetime Value (LTV)</h6>
                <div class="ec-value ec-positive">PKR {{ number_format($customer->total_spend, 2) }}</div>
                <div class="ec-sub">Avg. Order Value: PKR {{ number_format($avgOrderValue, 2) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card mb-0 h-100">
                <h6>Total Orders</h6>
                <div class="ec-value">{{ number_format($customer->total_orders, 0) }}</div>
                <div class="ec-sub">
                    Across {{ $channelsShopped->count() }} channel(s):
                    {{ $channelsShopped->isNotEmpty() ? $channelsShopped->implode(', ') : 'None yet' }}
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card mb-0 h-100">
                <h6>Last Purchase</h6>
                <div class="ec-value" style="font-size: 22px;">
                    {{ $customer->last_purchase_at ? $customer->last_purchase_at->format('d M Y') : '—' }}
                </div>
                <div class="ec-sub">
                    {{ $customer->last_purchase_at ? $customer->last_purchase_at->diffForHumans() . ' · Latest: ' . $latestProduct : 'No purchase recorded' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Products Purchased Breakdown --}}
    <div class="ec-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h6 class="mb-0">Products Purchased Summary</h6>
                <p class="ec-sub mb-0 mt-1">Aggregated breakdown of every finished product this customer has bought across all channels.</p>
            </div>
            <span class="ec-badge ec-badge-bundle">{{ $productBreakdown->count() }} unique product(s)</span>
        </div>

        @if ($productBreakdown->isEmpty())
            <div class="ec-chart-empty" style="height: 110px;">No products purchased yet.</div>
        @else
            <div class="table-responsive">
                <table class="table ec-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="text-end">Times Ordered</th>
                            <th class="text-end">Total Units Bought</th>
                            <th class="text-end">Total Spend</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($productBreakdown as $row)
                            <tr>
                                <td class="fw-semibold" style="color: #f3e9e7;">{{ $row['product_name'] }}</td>
                                <td><span class="ec-unit-badge">{{ $row['sku'] ?: '—' }}</span></td>
                                <td class="text-end">{{ number_format($row['orders_count'], 0) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($row['total_units'], 0) }}</td>
                                <td class="text-end fw-bold" style="color: var(--ec-success); font-variant-numeric: tabular-nums;">
                                    PKR {{ number_format($row['total_spent'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Complete Order History Table --}}
    <div class="ec-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h6 class="mb-0">Complete Order &amp; Purchase History ({{ $customer->sales->count() }})</h6>
                <p class="ec-sub mb-0 mt-1">Chronological log of all transactions linked to this customer identity.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table ec-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference ID</th>
                        <th>Product Purchased</th>
                        <th>Channel</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total Amount</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customer->sales as $sale)
                        <tr>
                            <td>
                                <div>{{ $sale->created_at?->format('d M Y') ?? '—' }}</div>
                                <div class="ec-sub" style="font-size: 11px;">{{ $sale->created_at?->format('h:i A') }}</div>
                            </td>
                            <td>{{ $sale->reference_id ?: '—' }}</td>
                            <td>
                                <span class="fw-semibold" style="color: #f3e9e7;">{{ $sale->product->name ?? 'Deleted Product' }}</span>
                                @if ($sale->bundle)
                                    <span class="ec-badge ec-badge-bundle ms-1">Bundle: {{ $sale->bundle->name }}</span>
                                @endif
                            </td>
                            <td>
                                <span class="ec-channel-badge {{ $channelBadgeClasses[$sale->channel] ?? 'ec-channel-shopify' }}">
                                    {{ $sale->channel }}
                                </span>
                            </td>
                            <td class="text-end">{{ number_format($sale->quantity, 0) }}</td>
                            <td class="text-end" style="font-variant-numeric: tabular-nums;">PKR {{ number_format($sale->unit_price, 2) }}</td>
                            <td class="text-end fw-bold" style="color: var(--ec-success); font-variant-numeric: tabular-nums;">
                                PKR {{ number_format($sale->total_price, 2) }}
                            </td>
                            <td class="ec-sub">{{ $sale->reason ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No orders logged for this customer yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .ec-health-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .ec-health-badge::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .ec-health-active {
            background: rgba(142, 168, 138, 0.18);
            border: 1px solid rgba(142, 168, 138, 0.42);
            color: #9fc49a;
        }

        .ec-health-slipping {
            background: rgba(201, 166, 107, 0.18);
            border: 1px solid rgba(201, 166, 107, 0.45);
            color: #deb97c;
        }

        .ec-health-churned {
            background: rgba(168, 82, 74, 0.2);
            border: 1px solid rgba(168, 82, 74, 0.45);
            color: #e6958c;
        }

        .ec-wa-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: rgba(37, 211, 102, 0.16);
            border: 1px solid rgba(37, 211, 102, 0.45);
            color: #4ddb84;
            border-radius: 20px;
            padding: 6px 15px;
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.15s ease, transform 0.15s ease;
        }

        .ec-wa-action-btn:hover {
            background: rgba(37, 211, 102, 0.28);
            border-color: rgba(37, 211, 102, 0.65);
            color: #72ef9f;
            transform: translateY(-1px);
        }
    </style>
@endsection
