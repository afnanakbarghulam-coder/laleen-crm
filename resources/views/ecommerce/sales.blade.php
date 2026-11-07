@extends('layouts.app')
@section('title', 'Sales & Usage')

@include('ecommerce._styles')

@php
    $channelBadgeClasses = \App\Models\EcommerceSale::CHANNEL_BADGE_CLASSES;
    $channelGroups = \App\Models\EcommerceSale::CHANNEL_GROUPS;
    $currency = $currency ?? 'PKR';
    $region = $region ?? 'pk';
@endphp

@section('content')
    <div class="ec-header">
        <div>
            <div class="ec-sub text-uppercase fw-bold mb-1" style="font-size: 11px; letter-spacing: .08em; color: var(--ec-gold);">Customers &amp; Sales &middot; {{ $region === 'qa' ? 'Qatar (QAR)' : 'Pakistan (PKR)' }}</div>
            <h4>Sales &amp; Usage ({{ $currency }})</h4>
            <p>Finished goods leaving inventory &mdash; retail sales, backbar use, promos, and damage/expiry, routed to the correct location automatically.</p>
        </div>
        @moduleEdit('ecommerce')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#salesModal">Log New Sale</button>
        @endmoduleEdit
    </div>

    @include('ecommerce.partials._hub_tabs')

    {{-- Date & Channel Filters --}}
    <div class="d-flex justify-content-end align-items-center mb-4">
        <form method="GET" action="{{ route('ecommerce.sales.index') }}" class="d-flex align-items-center flex-wrap gap-2">
            <input type="hidden" name="region" value="{{ $region }}">
            <div class="d-flex align-items-center gap-2">
                <label for="startDateFilter" class="ec-sub mb-0">From</label>
                <input type="date" name="start_date" id="startDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ request('start_date') }}">
                <label for="endDateFilter" class="ec-sub mb-0">To</label>
                <input type="date" name="end_date" id="endDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ request('end_date') }}">
            </div>
            <div class="dropdown">
                <button class="btn btn-sm ec-channel-filter dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    Filter by Channel
                    @if (count($selectedChannels) > 0)
                        <span class="ec-channel-filter-count">{{ count($selectedChannels) }}</span>
                    @endif
                </button>
                <div class="dropdown-menu ec-channel-filter-menu p-3">
                    <div class="ec-channel-filter-utility-row">
                        <button type="button" id="channelSelectAllBtn" class="ec-channel-filter-utility-btn">Select All</button>
                        <button type="button" id="channelClearAllBtn" class="ec-channel-filter-utility-btn">Clear All</button>
                    </div>
                    <div class="ec-channel-filter-list">
                        @php
                            $channelCheckIndex = 0;
                            $activeGroupName = $region === 'qa' ? 'Qatar' : 'Pakistan';
                            $activeGroupChannels = $regionChannels ?? ($channelGroups[$activeGroupName] ?? []);
                        @endphp
                        <div class="ec-channel-filter-group-label">{{ $activeGroupName }}</div>
                        @foreach ($activeGroupChannels as $channel)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="channels[]" value="{{ $channel }}" id="channelCheck{{ $channelCheckIndex }}" {{ in_array($channel, $selectedChannels, true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="channelCheck{{ $channelCheckIndex }}">{{ $channel }}</label>
                            </div>
                            @php $channelCheckIndex++; @endphp
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100 mt-3">Apply Filter</button>
                </div>
            </div>
        </form>
    </div>

    {{-- Top 4 Metric Cards --}}
    <div class="row g-3">
        <div class="col-md-3">
            <div class="ec-card h-100 mb-0">
                <h6>Realized Revenue</h6>
                <div class="ec-value ec-positive">{{ $currency }} {{ number_format($totalRevenue, 2) }}</div>
                <div class="ec-sub">{{ number_format($totalOrders ?? 0) }} paid/delivered order(s)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="ec-card h-100 mb-0" style="border-color: rgba(201, 166, 107, 0.35);">
                <h6>Pending COD / Pipeline</h6>
                <div class="ec-value" style="color: #deb97c;">{{ $currency }} {{ number_format($pendingCodRevenue ?? 0, 2) }}</div>
                <div class="ec-sub">{{ number_format($pendingCodOrders ?? 0) }} unpaid COD order(s)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="ec-card h-100 mb-0">
                <h6>Items Sold (Realized)</h6>
                <div class="ec-value">{{ number_format($totalItemsSoldRevenue, 0) }}</div>
                <div class="ec-sub">{{ $filterText }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="ec-card h-100 mb-0">
                <h6>Items Used/Damaged (Non-Rev)</h6>
                <div class="ec-value ec-negative">{{ number_format($totalItemsUsedDamaged, 0) }}</div>
                <div class="ec-sub">{{ $filterText }}</div>
            </div>
        </div>
    </div>

    {{-- Sales Trend Chart --}}
    <div class="ec-card ec-chart-card mt-3 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0">Realized Sales Trend ({{ $currency }})</h6>
            <div class="ec-chart-legend">
                <span><span class="ec-legend-dot" style="background: #e79a91;"></span>Realized Revenue</span>
                <span><span class="ec-legend-dot" style="background: #c9a66b;"></span>Units Sold</span>
            </div>
        </div>
        @if (count($chartLabels) > 0)
            <canvas id="salesTrendChart" height="90" class="mt-2"></canvas>
        @else
            <div class="ec-chart-empty">No sales data for this period yet.</div>
        @endif
    </div>

    {{-- Recent Sales Table --}}
    @php
        $upsellGroupIds = $sales->where('is_ai_upsell', true)->pluck('order_group_id')->filter()->unique()->flip();
        $codConfirmedGroupIds = $sales->whereNotNull('cod_confirmed_at')->pluck('order_group_id')->filter()->unique()->flip();
    @endphp
    <div class="ec-card">
        <h6 class="mb-3">Recent Sales ({{ $currency }})</h6>
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference ID</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th class="text-end">Quantity</th>
                        <th>Channel</th>
                        <th>Order / Payment Status</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total Revenue</th>
                        <th>Reason/Notes</th>
                        @moduleEdit('ecommerce')<th class="text-end">Actions</th>@endmoduleEdit
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        @php
                            $orderStatus = $sale->order_status ?? 'delivered';
                            $paymentStatus = $sale->payment_status ?? 'paid';
                            $groupId = $sale->order_group_id ?: ('LEGACY-' . $sale->id);
                            $hasAiUpsellInGroup = (bool) $sale->is_ai_upsell || ($sale->order_group_id && $upsellGroupIds->has($sale->order_group_id));
                            $isCodConfirmedInGroup = $sale->cod_confirmed_at !== null || ($sale->order_group_id && $codConfirmedGroupIds->has($sale->order_group_id));
                            $statusBadgeStyle = match ($orderStatus) {
                                'delivered' => 'background: rgba(142, 168, 138, 0.22); color: #a3c49e; border: 1px solid rgba(142, 168, 138, 0.45);',
                                'shipped' => 'background: rgba(96, 165, 250, 0.18); color: #93c5fd; border: 1px solid rgba(96, 165, 250, 0.4);',
                                'pending' => 'background: rgba(201, 166, 107, 0.2); color: #deb97c; border: 1px solid rgba(201, 166, 107, 0.45);',
                                default => 'background: rgba(168, 82, 74, 0.22); color: #eba199; border: 1px solid rgba(168, 82, 74, 0.45);',
                            };
                        @endphp
                        <tr style="{{ $sale->is_ai_upsell ? 'background: rgba(201, 166, 107, 0.05);' : '' }}">
                            <td>{{ $sale->created_at->format('d M Y') }}</td>
                            <td>
                                {{ $sale->reference_id ?? '—' }}
                                @if ($sale->bundle)
                                    <div class="ec-sub" style="font-size: 11px;">📦 {{ $sale->bundle->name }}</div>
                                @endif
                            </td>
                            <td>{{ $sale->customer_name ?? '—' }}</td>
                            <td>
                                <div>{{ $sale->product->name ?? '—' }}</div>
                                @if ($sale->is_ai_upsell || $hasAiUpsellInGroup)
                                    <span class="badge mt-1" style="font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 6px; background: rgba(201, 166, 107, 0.18); color: #deb97c; border: 1px solid rgba(201, 166, 107, 0.38);">
                                        ⚡ AI Upsell Added
                                    </span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($sale->quantity, 0) }}</td>
                            <td><span class="ec-channel-badge {{ $channelBadgeClasses[$sale->channel] ?? 'ec-channel-backbar' }}">{{ $sale->channel }}</span></td>
                            <td>
                                <div class="d-flex align-items-center flex-wrap gap-1">
                                    <span class="ec-badge" style="{{ $statusBadgeStyle }}">{{ strtoupper($orderStatus) }}</span>
                                    <span class="ec-sub" style="font-size: 11px;">
                                        {{ $paymentStatus === 'unpaid_cod' ? 'Unpaid COD' : ucfirst($paymentStatus) }}
                                    </span>
                                </div>
                                @if ($isCodConfirmedInGroup)
                                    <div class="mt-1">
                                        <span class="badge" style="font-size: 10px; font-weight: 600; padding: 2px 7px; border-radius: 6px; background: rgba(142, 168, 138, 0.16); color: #a3c49e; border: 1px solid rgba(142, 168, 138, 0.35);">
                                            COD Confirmed ✓
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="text-end">{{ $currency }} {{ number_format($sale->unit_price, 2) }}</td>
                            <td class="text-end">{{ $currency }} {{ number_format($sale->total_price, 2) }}</td>
                            <td>
                                <div>{{ $sale->reason ?? '—' }}</div>
                                @if ($sale->delivery_notes)
                                    <div class="mt-1">
                                        <span class="badge text-wrap text-start" style="font-size: 10.5px; font-weight: 500; padding: 3px 7px; border-radius: 6px; background: rgba(201, 166, 107, 0.14); color: #deb97c; border: 1px solid rgba(201, 166, 107, 0.32);">
                                            📍 {{ $sale->delivery_notes }}
                                        </span>
                                    </div>
                                @endif
                            </td>
                            @moduleEdit('ecommerce')
                                <td class="text-end">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle py-1 px-2" type="button" data-bs-toggle="dropdown" title="Update Order Status">
                                                Status
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" style="background: #241e1c; border: 1px solid rgba(217, 143, 131, 0.35);">
                                                <li>
                                                    <form action="{{ route('ecommerce.sales.update-status', $groupId) }}" method="POST">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="order_status" value="delivered">
                                                        <input type="hidden" name="payment_status" value="paid">
                                                        <button type="submit" class="dropdown-item text-light" style="font-size: 12.5px;">✓ Mark Delivered &amp; Paid</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form action="{{ route('ecommerce.sales.update-status', $groupId) }}" method="POST">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="order_status" value="shipped">
                                                        <button type="submit" class="dropdown-item text-light" style="font-size: 12.5px;">🚚 Mark Shipped</button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider" style="border-color: rgba(255,255,255,0.1);"></li>
                                                <li>
                                                    <form action="{{ route('ecommerce.sales.update-status', $groupId) }}" method="POST" onsubmit="return confirm('Mark this order as RTO (Return to Origin)? Stock will be restored automatically.');">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="order_status" value="rto">
                                                        <input type="hidden" name="payment_status" value="refunded">
                                                        <button type="submit" class="dropdown-item text-warning" style="font-size: 12.5px;">↩ Mark RTO (Restore Stock)</button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form action="{{ route('ecommerce.sales.update-status', $groupId) }}" method="POST" onsubmit="return confirm('Cancel this order? Stock will be restored automatically.');">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="order_status" value="cancelled">
                                                        <input type="hidden" name="payment_status" value="refunded">
                                                        <button type="submit" class="dropdown-item text-danger" style="font-size: 12.5px;">✕ Mark Cancelled (Restore Stock)</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                        <form action="{{ route('ecommerce.sales.destroy', $sale->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this order? All items in this order group will be restored to inventory.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            @endmoduleEdit
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted">No sales logged yet &mdash; use "Log New Sale" above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @moduleEdit('ecommerce')
        {{-- Log New Sale Modal --}}
        <div class="modal fade" id="salesModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.sales.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Log New Sale</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">What are you selling?</label>
                                <select name="sale_mode" id="salesModeSelect" class="form-select">
                                    <option value="product" selected>Single Product</option>
                                    <option value="bundle" {{ $bundles->isEmpty() ? 'disabled' : '' }}>
                                        Bundle / Kit{{ $bundles->isEmpty() ? ' (create bundles in Pricing & Profit)' : '' }}
                                    </option>
                                </select>
                            </div>

                            <div class="ec-form-grid mb-3">
                                <div id="salesProductWrapper">
                                    <label class="form-label">Finished Product</label>
                                    <select name="ecommerce_product_id" id="salesProductSelect" class="form-select" required>
                                        <option value="" disabled selected>Select product</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" data-unit-price="{{ $salePricesByProduct[$product->id] ?? $product->selling_price ?? '' }}">{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div id="salesBundleWrapper" style="display: none;">
                                    <label class="form-label">Bundle / Kit</label>
                                    <select name="ecommerce_bundle_id" id="salesBundleSelect" class="form-select" disabled>
                                        <option value="" disabled selected>Select bundle</option>
                                        @foreach ($bundles as $bundle)
                                            <option value="{{ $bundle->id }}">{{ $bundle->name }} — PKR {{ number_format($bundle->bundle_price, 2) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label" id="salesQuantityLabel">Quantity Sold</label>
                                    <input type="number" step="1" min="1" name="quantity" id="salesQuantityInput" class="form-control" placeholder="e.g. 5" required>
                                </div>
                            </div>

                            <div id="salesBundleContents" class="ec-accounting-note mb-3" style="display: none;">
                                <span>📦</span>
                                <div id="salesBundleContentsText"></div>
                            </div>

                            <div class="ec-form-grid mb-3">
                                <div>
                                    <label class="form-label">Sales Channel</label>
                                    <select name="channel" id="salesChannelSelect" class="form-select" required>
                                        <option value="" disabled selected>Select channel</option>
                                        <optgroup label="Revenue">
                                            @foreach (\App\Models\EcommerceSale::REVENUE_CHANNELS as $revChannel)
                                                <option value="{{ $revChannel }}">{{ $revChannel }}</option>
                                            @endforeach
                                        </optgroup>
                                        <optgroup label="Non-Revenue">
                                            @foreach (\App\Models\EcommerceSale::NON_REVENUE_CHANNELS as $nonRevChannel)
                                                <option value="{{ $nonRevChannel }}">{{ $nonRevChannel }}</option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                </div>
                                <div id="salesUnitPriceWrapper">
                                    <label class="form-label" id="salesUnitPriceLabel">Unit Price</label>
                                    <input type="number" step="0.01" min="0" name="unit_price" id="salesUnitPriceInput" class="form-control" placeholder="e.g. 1500">
                                </div>
                            </div>

                            <div class="ec-form-grid mb-3">
                                <div>
                                    <label class="form-label">Order Status</label>
                                    <select name="order_status" id="salesOrderStatusSelect" class="form-select">
                                        <option value="delivered" selected>Delivered (Completed)</option>
                                        <option value="shipped">Shipped (In Transit)</option>
                                        <option value="pending">Pending (Awaiting Dispatch)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Payment Status</label>
                                    <select name="payment_status" id="salesPaymentStatusSelect" class="form-select">
                                        <option value="paid" selected>Paid (Recognized Revenue)</option>
                                        <option value="unpaid_cod">Unpaid COD (Pipeline Until Delivered)</option>
                                    </select>
                                </div>
                            </div>

                            <input type="hidden" name="ecommerce_customer_id" id="salesCustomerIdInput" value="">

                            {{-- Dynamic Client Selector (4 States) --}}
                            <div class="mb-3 p-3 rounded-3" style="background: #251d1c; border: 1px solid rgba(217, 143, 131, 0.22);">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0" style="color: #d98f83; font-weight: 700; font-size: 13px; letter-spacing: .02em;">
                                        Select a client <span class="ec-sub fw-normal">(optional)</span>
                                    </label>
                                </div>

                                {{-- STATE 1 & 2: Default "Search" State + Live Search Results State --}}
                                <div id="clientSelectorSearchState">
                                    <div class="position-relative mb-2">
                                        <span class="position-absolute top-50 translate-middle-y" style="left: 13px; color: #8a7b78; pointer-events: none;">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input
                                            type="text"
                                            id="clientSelectorSearchInput"
                                            class="form-control ec-form-control"
                                            style="padding-left: 38px; background: #1c1615; border-color: rgba(217, 143, 131, 0.28);"
                                            placeholder="Search by name or phone"
                                            autocomplete="off"
                                        >
                                    </div>

                                    <button
                                        type="button"
                                        id="clientSelectorAddNewBtn"
                                        class="w-100 d-flex align-items-center gap-2 px-3 py-2 rounded-3 text-start"
                                        style="background: rgba(217, 143, 131, 0.08); border: 1px dashed rgba(217, 143, 131, 0.35); color: #d98f83; font-weight: 600; font-size: 13px; transition: all 0.15s ease;"
                                    >
                                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 24px; height: 24px; background: rgba(217, 143, 131, 0.2); color: #d98f83; font-size: 14px; line-height: 1;">+</span>
                                        <span>+ Add new client</span>
                                    </button>

                                    {{-- Live Search Results List --}}
                                    <div id="clientSelectorResultsWrapper" class="mt-2 rounded-3 overflow-hidden" style="display: none; background: #1c1615; border: 1px solid rgba(217, 143, 131, 0.22);">
                                        <ul
                                            id="clientSelectorResultsList"
                                            class="list-unstyled mb-0"
                                            style="max-height: 220px; overflow-y: auto;"
                                        ></ul>
                                    </div>
                                </div>

                                {{-- STATE 3: "Client Selected" State --}}
                                <div id="clientSelectorSelectedState" style="display: none;">
                                    <div
                                        class="d-flex align-items-center justify-content-between gap-3 p-3 rounded-3"
                                        style="background: #1c1615; border: 1px solid rgba(142, 168, 138, 0.4);"
                                    >
                                        <div class="d-flex align-items-center gap-3 min-w-0">
                                            <div
                                                id="selectedClientAvatar"
                                                class="d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0 fw-bold"
                                                style="width: 42px; height: 42px; background: rgba(217, 143, 131, 0.18); border: 1px solid rgba(217, 143, 131, 0.4); color: #d98f83; font-size: 16px;"
                                            >
                                                A
                                            </div>
                                            <div class="min-w-0">
                                                <div id="selectedClientName" class="fw-bold text-truncate" style="color: #f3e9e7; font-size: 14px;"></div>
                                                <div id="selectedClientPhone" style="color: #8a7b78; font-size: 12.5px; font-family: monospace;"></div>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            id="clientSelectorClearBtn"
                                            class="btn btn-sm d-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0"
                                            style="width: 30px; height: 30px; background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(217, 143, 131, 0.25); color: #c9a39a;"
                                            title="Clear selected client"
                                        >
                                            <i class="bi bi-x-lg" style="font-size: 12px;"></i>
                                        </button>
                                    </div>
                                </div>

                                {{-- STATE 4: "Add New Client" State --}}
                                <div id="clientSelectorNewClientState" style="display: none;">
                                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2" style="border-bottom: 1px solid rgba(217, 143, 131, 0.14);">
                                        <span class="fw-semibold" style="font-size: 12px; color: #c9a39a; text-transform: uppercase; letter-spacing: .04em;">New Client Details</span>
                                        <button
                                            type="button"
                                            id="clientSelectorCancelNewBtn"
                                            class="btn btn-sm py-0 px-2"
                                            style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(217, 143, 131, 0.25); color: #c9a39a; font-size: 11.5px;"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                    <div class="ec-form-grid">
                                        <div>
                                            <label class="form-label">Full Name</label>
                                            <input
                                                type="text"
                                                name="customer_name"
                                                id="salesCustomerNameInput"
                                                class="form-control ec-form-control"
                                                style="background: #1c1615;"
                                                placeholder="e.g. Ayesha Khan"
                                                autocomplete="off"
                                            >
                                        </div>
                                        <div>
                                            <label class="form-label">Phone Number <span class="ec-sub">(WhatsApp)</span></label>
                                            <div class="input-group">
                                                <select
                                                    name="customer_country_code"
                                                    id="salesCustomerCountryCodeSelect"
                                                    class="form-select ec-form-select"
                                                    style="max-width: 122px; background: #1c1615; border-top-right-radius: 0; border-bottom-right-radius: 0;"
                                                >
                                                    <option value="+974" selected>+974 (QA)</option>
                                                    <option value="+92">+92 (PK)</option>
                                                    <option value="+971">+971 (UAE)</option>
                                                    <option value="+966">+966 (KSA)</option>
                                                    <option value="+965">+965 (KW)</option>
                                                    <option value="+973">+973 (BH)</option>
                                                    <option value="+968">+968 (OM)</option>
                                                    <option value="+44">+44 (UK)</option>
                                                    <option value="+1">+1 (US)</option>
                                                </select>
                                                <input
                                                    type="text"
                                                    name="customer_phone"
                                                    id="salesCustomerPhoneInput"
                                                    class="form-control ec-form-control"
                                                    style="background: #1c1615;"
                                                    placeholder="e.g. 5512 3456"
                                                    autocomplete="off"
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Reason/Notes <span class="ec-sub">(optional)</span></label>
                                <input type="text" name="reason" class="form-control" placeholder="e.g. Backbar restock, damaged in transit">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Reference / Order ID <span class="ec-sub">(optional)</span></label>
                                <input type="text" name="reference_id" class="form-control" placeholder="e.g. Shopify order #1042">
                            </div>

                            <div class="mb-2">
                                <span class="ec-sub" style="font-size: 11.5px;">
                                    <em>Est. Unit Economics Reference Only — Deducted via Monthly Expenses</em>
                                </span>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Courier/Shipping Cost (Ref) <span class="ec-sub">(optional)</span></label>
                                    <input type="number" step="0.01" min="0" name="shipping_cost" class="form-control" placeholder="0.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Taxes (Ref) <span class="ec-sub">(optional)</span></label>
                                    <input type="number" step="0.01" min="0" name="tax_amount" class="form-control" placeholder="0.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Attributed Meta Ads (Ref) <span class="ec-sub">(optional)</span></label>
                                    <input type="number" step="0.01" min="0" name="meta_ad_allocation" class="form-control" placeholder="0.00">
                                </div>
                            </div>

                            <div class="ec-grand-total">
                                <span class="ec-grand-total-label">Grand Total</span>
                                <span class="ec-grand-total-value" id="salesGrandTotalValue">{{ $currency }} 0.00</span>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const BUNDLES = @json($bundlesForJs);
            const CUSTOMER_SUGGESTIONS_URL = @json(route('ecommerce.customers.suggestions'));
            const nonRevenueChannels = @json(\App\Models\EcommerceSale::NON_REVENUE_CHANNELS);

            const salesModeSelect = document.getElementById('salesModeSelect');
            const salesProductWrapper = document.getElementById('salesProductWrapper');
            const salesBundleWrapper = document.getElementById('salesBundleWrapper');
            const salesBundleSelect = document.getElementById('salesBundleSelect');
            const salesBundleContents = document.getElementById('salesBundleContents');
            const salesBundleContentsText = document.getElementById('salesBundleContentsText');
            const salesUnitPriceWrapper = document.getElementById('salesUnitPriceWrapper');
            const salesUnitPriceLabel = document.getElementById('salesUnitPriceLabel');
            const salesQuantityLabel = document.getElementById('salesQuantityLabel');
            const salesProductSelect = document.getElementById('salesProductSelect');
            const salesQuantityInput = document.getElementById('salesQuantityInput');
            const salesUnitPriceInput = document.getElementById('salesUnitPriceInput');
            const salesChannelSelect = document.getElementById('salesChannelSelect');
            const salesGrandTotalValue = document.getElementById('salesGrandTotalValue');

            // Dynamic Client Selector elements
            const salesCustomerIdInput = document.getElementById('salesCustomerIdInput');
            const clientSelectorSearchState = document.getElementById('clientSelectorSearchState');
            const clientSelectorSearchInput = document.getElementById('clientSelectorSearchInput');
            const clientSelectorAddNewBtn = document.getElementById('clientSelectorAddNewBtn');
            const clientSelectorResultsWrapper = document.getElementById('clientSelectorResultsWrapper');
            const clientSelectorResultsList = document.getElementById('clientSelectorResultsList');
            const clientSelectorSelectedState = document.getElementById('clientSelectorSelectedState');
            const selectedClientAvatar = document.getElementById('selectedClientAvatar');
            const selectedClientName = document.getElementById('selectedClientName');
            const selectedClientPhone = document.getElementById('selectedClientPhone');
            const clientSelectorClearBtn = document.getElementById('clientSelectorClearBtn');
            const clientSelectorNewClientState = document.getElementById('clientSelectorNewClientState');
            const clientSelectorCancelNewBtn = document.getElementById('clientSelectorCancelNewBtn');
            const salesCustomerNameInput = document.getElementById('salesCustomerNameInput');
            const salesCustomerCountryCodeSelect = document.getElementById('salesCustomerCountryCodeSelect');
            const salesCustomerPhoneInput = document.getElementById('salesCustomerPhoneInput');

            let customerSuggestTimer = null;

            function getCustomerInitial(name) {
                const cleaned = String(name || '').trim();
                return cleaned ? cleaned.charAt(0).toUpperCase() : '?';
            }

            function formatDisplayPhone(phone) {
                const cleaned = String(phone || '').trim();
                if (!cleaned) {
                    return 'No phone on file';
                }
                return '+' + cleaned.replace(/^\+/, '');
            }

            function switchToSearchState() {
                clearTimeout(customerSuggestTimer);
                salesCustomerIdInput.value = '';
                clientSelectorSearchInput.value = '';
                clientSelectorResultsList.innerHTML = '';
                clientSelectorResultsWrapper.style.display = 'none';

                salesCustomerNameInput.value = '';
                salesCustomerPhoneInput.value = '';
                salesCustomerCountryCodeSelect.value = '+974';

                clientSelectorSelectedState.style.display = 'none';
                clientSelectorNewClientState.style.display = 'none';
                clientSelectorSearchState.style.display = '';
            }

            function switchToSelectedClientState(customer) {
                if (!customer) {
                    return;
                }

                salesCustomerIdInput.value = customer.id || '';
                salesCustomerNameInput.value = '';
                salesCustomerPhoneInput.value = '';

                selectedClientAvatar.textContent = getCustomerInitial(customer.name);
                selectedClientName.textContent = customer.name || 'Unnamed Client';
                selectedClientPhone.textContent = formatDisplayPhone(customer.phone);

                clientSelectorSearchState.style.display = 'none';
                clientSelectorNewClientState.style.display = 'none';
                clientSelectorSelectedState.style.display = '';
            }

            function switchToNewClientState() {
                clearTimeout(customerSuggestTimer);
                salesCustomerIdInput.value = '';

                const typedQuery = clientSelectorSearchInput.value.trim();
                if (typedQuery) {
                    const digitsOnly = typedQuery.replace(/\D+/g, '');
                    if (digitsOnly.length >= 5 && digitsOnly.length === typedQuery.replace(/[\s+\-()]/g, '').length) {
                        salesCustomerPhoneInput.value = digitsOnly;
                        salesCustomerNameInput.value = '';
                    } else {
                        salesCustomerNameInput.value = typedQuery;
                        salesCustomerPhoneInput.value = '';
                    }
                } else {
                    salesCustomerNameInput.value = '';
                    salesCustomerPhoneInput.value = '';
                }

                clientSelectorSearchState.style.display = 'none';
                clientSelectorSelectedState.style.display = 'none';
                clientSelectorNewClientState.style.display = '';
                salesCustomerNameInput.focus();
            }

            function renderClientSearchResults(items) {
                clientSelectorResultsList.innerHTML = '';

                if (!Array.isArray(items) || items.length === 0) {
                    const emptyLi = document.createElement('li');
                    emptyLi.style.cssText = 'padding: 12px 14px; color: #8a7b78; font-size: 12.5px; text-align: center;';
                    emptyLi.textContent = 'No matching clients found — click "+ Add new client" above.';
                    clientSelectorResultsList.appendChild(emptyLi);
                    clientSelectorResultsWrapper.style.display = 'block';
                    return;
                }

                items.forEach(function (customer) {
                    const li = document.createElement('li');
                    li.className = 'd-flex align-items-center gap-3 px-3 py-2';
                    li.style.cssText = 'cursor: pointer; border-bottom: 1px solid rgba(217, 143, 131, 0.12); transition: background 0.15s ease;';

                    const avatar = document.createElement('div');
                    avatar.className = 'd-inline-flex align-items-center justify-content-center rounded-circle flex-shrink-0 fw-bold';
                    avatar.style.cssText = 'width: 36px; height: 36px; background: rgba(217, 143, 131, 0.16); border: 1px solid rgba(217, 143, 131, 0.35); color: #d98f83; font-size: 14px;';
                    avatar.textContent = getCustomerInitial(customer.name);

                    const details = document.createElement('div');
                    details.className = 'min-w-0 flex-grow-1';

                    const nameEl = document.createElement('div');
                    nameEl.className = 'fw-semibold text-truncate';
                    nameEl.style.cssText = 'color: #d98f83; font-size: 13.5px;';
                    nameEl.textContent = customer.name || 'Unnamed Client';

                    const phoneEl = document.createElement('div');
                    phoneEl.style.cssText = 'color: #8a7b78; font-size: 12px; font-family: monospace;';
                    phoneEl.textContent = formatDisplayPhone(customer.phone);

                    details.appendChild(nameEl);
                    details.appendChild(phoneEl);

                    li.appendChild(avatar);
                    li.appendChild(details);

                    li.addEventListener('mouseenter', function () {
                        li.style.background = 'rgba(217, 143, 131, 0.12)';
                    });
                    li.addEventListener('mouseleave', function () {
                        li.style.background = 'transparent';
                    });
                    li.addEventListener('click', function () {
                        switchToSelectedClientState(customer);
                    });

                    clientSelectorResultsList.appendChild(li);
                });

                clientSelectorResultsWrapper.style.display = 'block';
            }

            if (clientSelectorSearchInput) {
                clientSelectorSearchInput.addEventListener('input', function () {
                    clearTimeout(customerSuggestTimer);
                    const query = this.value.trim();

                    if (query.length < 1) {
                        clientSelectorResultsList.innerHTML = '';
                        clientSelectorResultsWrapper.style.display = 'none';
                        return;
                    }

                    customerSuggestTimer = setTimeout(function () {
                        fetch(CUSTOMER_SUGGESTIONS_URL + '?q=' + encodeURIComponent(query), {
                            headers: { 'Accept': 'application/json' },
                        })
                            .then(function (res) { return res.ok ? res.json() : []; })
                            .then(function (items) {
                                renderClientSearchResults(items);
                            })
                            .catch(function () {
                                clientSelectorResultsList.innerHTML = '';
                                clientSelectorResultsWrapper.style.display = 'none';
                            });
                    }, 180);
                });
            }

            if (clientSelectorAddNewBtn) {
                clientSelectorAddNewBtn.addEventListener('mouseenter', function () {
                    this.style.background = 'rgba(217, 143, 131, 0.16)';
                });
                clientSelectorAddNewBtn.addEventListener('mouseleave', function () {
                    this.style.background = 'rgba(217, 143, 131, 0.08)';
                });
                clientSelectorAddNewBtn.addEventListener('click', switchToNewClientState);
            }

            if (clientSelectorClearBtn) {
                clientSelectorClearBtn.addEventListener('click', function () {
                    switchToSearchState();
                    clientSelectorSearchInput.focus();
                });
            }

            if (clientSelectorCancelNewBtn) {
                clientSelectorCancelNewBtn.addEventListener('click', function () {
                    switchToSearchState();
                    clientSelectorSearchInput.focus();
                });
            }

            const ACTIVE_CURRENCY = @json($currency);
            const salesOrderStatusSelect = document.getElementById('salesOrderStatusSelect');
            const salesPaymentStatusSelect = document.getElementById('salesPaymentStatusSelect');
            if (salesOrderStatusSelect && salesPaymentStatusSelect) {
                salesOrderStatusSelect.addEventListener('change', function () {
                    if (this.value === 'delivered') {
                        salesPaymentStatusSelect.value = 'paid';
                    }
                });
            }

            const isBundleMode = () => salesModeSelect.value === 'bundle';

            function updateGrandTotal() {
                const quantity = parseFloat(salesQuantityInput.value) || 0;
                let total;

                if (isBundleMode()) {
                    const bundle = BUNDLES[salesBundleSelect.value];
                    const bundlePrice = bundle ? bundle.bundle_price : 0;
                    total = nonRevenueChannels.includes(salesChannelSelect.value) ? 0 : quantity * bundlePrice;
                } else {
                    total = quantity * (parseFloat(salesUnitPriceInput.value) || 0);
                }

                salesGrandTotalValue.textContent = ACTIVE_CURRENCY + ' ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function restoreDefaultPrice() {
                const selectedOption = salesProductSelect.options[salesProductSelect.selectedIndex];
                const price = selectedOption ? selectedOption.getAttribute('data-unit-price') : null;
                salesUnitPriceInput.value = price ? parseFloat(price).toFixed(2) : '';
            }

            function applySaleMode() {
                const bundleMode = isBundleMode();

                salesProductWrapper.style.display = bundleMode ? 'none' : '';
                salesBundleWrapper.style.display = bundleMode ? '' : 'none';
                salesUnitPriceWrapper.style.display = bundleMode ? 'none' : '';

                salesProductSelect.disabled = bundleMode;
                salesProductSelect.required = !bundleMode;
                salesBundleSelect.disabled = !bundleMode;
                salesBundleSelect.required = bundleMode;

                salesQuantityLabel.textContent = bundleMode ? 'Bundles Sold' : 'Quantity Sold';
                salesUnitPriceLabel.textContent = 'Unit Price';

                if (!bundleMode) {
                    salesBundleContents.style.display = 'none';
                }

                applyChannelPricingRules();
                renderBundleContents();
                updateGrandTotal();
            }

            function renderBundleContents() {
                if (!isBundleMode()) {
                    salesBundleContents.style.display = 'none';
                    return;
                }

                const bundle = BUNDLES[salesBundleSelect.value];
                if (!bundle) {
                    salesBundleContents.style.display = 'none';
                    return;
                }

                const qty = parseInt(salesQuantityInput.value, 10) || 1;
                const lines = bundle.items
                    .map((i) => (i.quantity * qty) + ' × ' + i.product_name)
                    .join(', ');
                const savings = bundle.savings > 0
                    ? ' · Saves the customer PKR ' + bundle.savings.toFixed(2) + ' vs buying separately'
                    : '';

                salesBundleContentsText.innerHTML =
                    '<strong>This will deduct:</strong> ' + lines + savings;
                salesBundleContents.style.display = '';
            }

            function applyChannelPricingRules() {
                const nonRevenue = nonRevenueChannels.includes(salesChannelSelect.value);

                if (isBundleMode()) {
                    salesUnitPriceInput.required = false;
                    return;
                }

                if (nonRevenue) {
                    salesUnitPriceInput.value = 0;
                    salesUnitPriceInput.readOnly = true;
                    salesUnitPriceInput.required = false;
                } else {
                    salesUnitPriceInput.readOnly = false;
                    salesUnitPriceInput.required = true;
                    restoreDefaultPrice();
                }
            }

            salesModeSelect.addEventListener('change', applySaleMode);

            salesBundleSelect.addEventListener('change', function () {
                renderBundleContents();
                updateGrandTotal();
            });

            salesProductSelect.addEventListener('change', function () {
                if (!nonRevenueChannels.includes(salesChannelSelect.value)) {
                    restoreDefaultPrice();
                }
                updateGrandTotal();
            });

            salesChannelSelect.addEventListener('change', function () {
                applyChannelPricingRules();
                updateGrandTotal();
            });

            salesQuantityInput.addEventListener('input', function () {
                renderBundleContents();
                updateGrandTotal();
            });

            applySaleMode();
            salesUnitPriceInput.addEventListener('input', updateGrandTotal);

            document.getElementById('salesModal').addEventListener('hidden.bs.modal', function () {
                this.querySelector('form').reset();
                switchToSearchState();
                salesUnitPriceInput.readOnly = false;
                applySaleMode();
            });
        </script>
    @endmoduleEdit

    <script>
        document.getElementById('channelSelectAllBtn').addEventListener('click', function () {
            document.querySelectorAll('input[name="channels[]"]').forEach(function (checkbox) {
                checkbox.checked = true;
            });
        });

        document.getElementById('channelClearAllBtn').addEventListener('click', function () {
            document.querySelectorAll('input[name="channels[]"]').forEach(function (checkbox) {
                checkbox.checked = false;
            });
        });
    </script>

    @if (count($chartLabels) > 0)
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (function () {
                const canvas = document.getElementById('salesTrendChart');
                const ctx = canvas.getContext('2d');
                const revenueFill = ctx.createLinearGradient(0, 0, 0, 240);
                revenueFill.addColorStop(0, 'rgba(223, 166, 166, 0.3)');
                revenueFill.addColorStop(1, 'rgba(223, 166, 166, 0)');

                new Chart(ctx, {
                    data: {
                        labels: @json($chartLabels),
                        datasets: [
                            {
                                type: 'line',
                                label: 'Revenue',
                                data: @json($chartRevenue),
                                borderColor: '#dfa6a6',
                                backgroundColor: revenueFill,
                                fill: true,
                                tension: 0.4,
                                borderWidth: 2,
                                pointRadius: 0,
                                pointBackgroundColor: '#dfa6a6',
                                pointHoverRadius: 6,
                                pointBorderColor: '#241e1c',
                                pointBorderWidth: 2,
                                yAxisID: 'y',
                                order: 1,
                            },
                            {
                                type: 'line',
                                label: 'Units Sold',
                                data: @json($chartUnits),
                                borderColor: '#c9a66b',
                                backgroundColor: 'transparent',
                                fill: false,
                                tension: 0.4,
                                borderWidth: 2,
                                pointRadius: 0,
                                pointBackgroundColor: '#c9a66b',
                                pointHoverRadius: 6,
                                pointBorderColor: '#241e1c',
                                pointBorderWidth: 2,
                                yAxisID: 'y1',
                                order: 2,
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
                                displayColors: true,
                                boxPadding: 4,
                                titleFont: { size: 12, weight: '600' },
                                bodyFont: { size: 12 },
                                callbacks: {
                                    label: function (context) {
                                        if (context.dataset.yAxisID === 'y') {
                                            return '  Revenue: ' + @json($currency) + ' ' + Number(context.parsed.y).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                        }
                                        return '  Units Sold: ' + context.parsed.y;
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
                                position: 'left',
                                beginAtZero: true,
                                grid: { display: true, color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                                ticks: {
                                    color: '#9ca3af',
                                    font: { size: 11 },
                                    callback: function (value) {
                                        return @json($currency) + ' ' + Number(value).toLocaleString('en-US');
                                    },
                                },
                            },
                            y1: {
                                position: 'right',
                                beginAtZero: true,
                                grid: { display: false, drawBorder: false },
                                ticks: { color: '#9ca3af', font: { size: 11 }, precision: 0 },
                            },
                        },
                    },
                });
            })();
        </script>
    @endif
@endsection
