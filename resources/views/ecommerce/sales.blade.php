@extends('layouts.app')
@section('title', 'Sales & Usage')

@include('ecommerce._styles')

@php
    $channelBadgeClasses = [
        'Shopify (Pakistan)' => 'ec-channel-shopify',
        'Organic (Pakistan)' => 'ec-channel-organic',
        'Salon (Old Airport)' => 'ec-channel-salon',
        'Salon (Wakrah)' => 'ec-channel-salon',
        'Organic (Qatar)' => 'ec-channel-organic',
        'Backbar Use (Qatar)' => 'ec-channel-backbar',
        'Damage/Expiry (Pakistan)' => 'ec-channel-damage',
        'Damage/Expiry (Qatar)' => 'ec-channel-damage',
    ];

    $channelGroups = [
        'Pakistan' => ['Shopify (Pakistan)', 'Organic (Pakistan)', 'Damage/Expiry (Pakistan)'],
        'Qatar' => ['Salon (Old Airport)', 'Salon (Wakrah)', 'Organic (Qatar)', 'Backbar Use (Qatar)', 'Damage/Expiry (Qatar)'],
    ];
@endphp

@section('content')
    <div class="ec-header">
        <div>
            <h4>Sales &amp; Usage</h4>
            <p>Finished goods leaving inventory &mdash; retail sales, backbar use, promos, and damage/expiry, routed to the correct location automatically.</p>
        </div>
        @moduleEdit('ecommerce')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#salesModal">Log New Sale</button>
        @endmoduleEdit
    </div>

    @include('ecommerce._nav')

    <div class="d-flex justify-content-end align-items-center mb-4">
        <form method="GET" action="{{ route('ecommerce.sales.index') }}" class="d-flex align-items-center flex-wrap gap-2">
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
                        @php $channelCheckIndex = 0; @endphp
                        @foreach ($channelGroups as $groupName => $groupChannels)
                            <div class="ec-channel-filter-group-label">{{ $groupName }}</div>
                            @foreach ($groupChannels as $channel)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="channels[]" value="{{ $channel }}" id="channelCheck{{ $channelCheckIndex }}" {{ in_array($channel, $selectedChannels, true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="channelCheck{{ $channelCheckIndex }}">{{ $channel }}</label>
                                </div>
                                @php $channelCheckIndex++; @endphp
                            @endforeach
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100 mt-3">Apply Filter</button>
                </div>
            </div>
        </form>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total Revenue</h6>
                <div class="ec-value ec-positive">PKR {{ number_format($totalRevenue, 2) }}</div>
                <div class="ec-sub">{{ $filterText }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total Items Sold (Revenue Channels)</h6>
                <div class="ec-value">{{ number_format($totalItemsSoldRevenue, 0) }}</div>
                <div class="ec-sub">{{ $filterText }} (Revenue)</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total Items Used/Damaged (Non-Revenue)</h6>
                <div class="ec-value ec-negative">{{ number_format($totalItemsUsedDamaged, 0) }}</div>
                <div class="ec-sub">{{ $filterText }} (Non-Revenue)</div>
            </div>
        </div>
    </div>

    {{-- ============ Salon Backbar-to-Retail Conversion ============ --}}
    <div id="backbar-conversion"></div>
    @php
        $bbEfficiency = $backbarConversion['efficiency'];
        $bbMeterWidth = $bbEfficiency === null ? 0 : min(100, max(0, $bbEfficiency));
    @endphp
    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <div class="ec-card ec-card-highlight">
                <h6>Salon Backbar Conversion Efficiency</h6>
                <div class="ec-value {{ $bbEfficiency === null ? '' : ($bbEfficiency >= 100 ? 'ec-positive' : 'ec-negative') }}">
                    {{ $bbEfficiency === null ? '—' : number_format($bbEfficiency, 0) . '%' }}
                </div>
                <div class="ec-sub">
                    {{ number_format($backbarConversion['total_retail_units'], 0) }} retail units sold per
                    {{ number_format($backbarConversion['total_backbar_units'], 0) }} backbar units used
                </div>
                <div class="ec-conversion-meter">
                    <div class="ec-conversion-fill" style="width: {{ $bbMeterWidth }}%;"></div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="ec-card">
                <h6 style="margin-bottom: 4px;">Are stylists converting wash-basin treatments into retail?</h6>
                <p class="ec-sub mb-3">
                    Backbar consumption is logged salon-wide (no branch), so the ratio is per product; branch columns split the retail side only.
                </p>
                @if ($backbarConversion['by_product']->isEmpty())
                    <div class="ec-chart-empty">No backbar or salon retail activity logged yet.</div>
                @else
                    <div class="table-responsive">
                        <table class="table ec-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-end">Backbar Used</th>
                                    <th class="text-end">Retail Sold</th>
                                    <th class="text-end">Old Airport</th>
                                    <th class="text-end">Wakrah</th>
                                    <th class="text-end">Retail Revenue</th>
                                    <th class="text-end">Conversion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($backbarConversion['by_product'] as $row)
                                    <tr>
                                        <td>{{ $row['product']->name ?? 'Deleted product' }}</td>
                                        <td class="text-end">{{ number_format($row['backbar_units'], 0) }}</td>
                                        <td class="text-end">{{ number_format($row['retail_units'], 0) }}</td>
                                        <td class="text-end">{{ number_format($row['old_airport_units'], 0) }}</td>
                                        <td class="text-end">{{ number_format($row['wakrah_units'], 0) }}</td>
                                        <td class="text-end">PKR {{ number_format($row['retail_revenue'], 2) }}</td>
                                        <td class="text-end {{ $row['ratio'] === null ? '' : ($row['ratio'] >= 100 ? 'ec-positive' : 'ec-negative') }}">
                                            {{ $row['ratio'] === null ? '—' : number_format($row['ratio'], 0) . '%' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ============ 30-Day WhatsApp Refill Queue ============ --}}
    <div id="refill-queue"></div>
    <div class="ec-card mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
            <h6 class="mb-0">Refill Due (Day {{ $refillWindowStartDays }}&ndash;{{ $refillWindowEndDays }})</h6>
            <span class="ec-badge ec-badge-injection">{{ $refillQueue->count() }} customer(s) due</span>
        </div>
        <p class="ec-sub mb-3">
            Customers whose last order landed {{ $refillWindowStartDays }}&ndash;{{ $refillWindowEndDays }} days ago and who haven't re-ordered since.
            Log a phone number on a sale to surface it here.
        </p>
        @if ($refillQueue->isEmpty())
            <div class="ec-chart-empty">Nobody is due a refill nudge right now.</div>
        @else
            <div class="table-responsive">
                <table class="table ec-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Last Bought</th>
                            <th class="text-end">Days Ago</th>
                            <th>Products</th>
                            <th class="text-end">Spent</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($refillQueue as $row)
                            @php
                                $waText = 'Hi ' . $row['customer_name'] . '! It has been ' . $row['days_since']
                                    . ' days since your ' . $row['products']->implode(', ')
                                    . ' from Laleen. Most customers run out right about now — want us to send your refill out today?';
                                $waLink = 'https://wa.me/' . $row['phone'] . '?text=' . rawurlencode($waText);
                            @endphp
                            <tr>
                                <td>{{ $row['customer_name'] === 'there' ? '—' : $row['customer_name'] }}</td>
                                <td>{{ $row['display_phone'] }}</td>
                                <td>{{ $row['last_purchase_at']->format('d M Y') }}</td>
                                <td class="text-end">{{ $row['days_since'] }}</td>
                                <td>{{ $row['products']->implode(', ') }}</td>
                                <td class="text-end">PKR {{ number_format($row['total_spent'], 2) }}</td>
                                <td class="text-end">
                                    <a href="{{ $waLink }}" target="_blank" rel="noopener" class="ec-wa-btn">
                                        <i class="bi bi-whatsapp"></i> Send Refill
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ============ Bundles & Kits ============ --}}
    <div id="bundles"></div>
    <div class="ec-card mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
            <h6 class="mb-0">Bundles &amp; Kits</h6>
            @moduleEdit('ecommerce')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bundleModal">+ Create Bundle</button>
            @endmoduleEdit
        </div>
        <p class="ec-sub mb-3">Multi-item kits sold at a single price. Selling one decrements every component's stock and splits the revenue across them.</p>
        @if ($bundles->isEmpty())
            <div class="ec-chart-empty">No bundles yet &mdash; create one to start selling kits.</div>
        @else
            <div class="row g-3">
                @foreach ($bundles as $bundle)
                    <div class="col-md-4">
                        <div class="ec-bundle-card">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="ec-bundle-name">{{ $bundle->name }}</div>
                                @moduleEdit('ecommerce')
                                    <form action="{{ route('ecommerce.bundles.destroy', $bundle->id) }}" method="POST" onsubmit="return confirm('Delete this bundle?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endmoduleEdit
                            </div>
                            <div class="ec-value" style="font-size: 20px; margin-top: 6px;">PKR {{ number_format($bundle->bundle_price, 2) }}</div>
                            @if ($bundle->savingsVersusStandalone() > 0)
                                <span class="ec-badge ec-badge-bundle">Saves PKR {{ number_format($bundle->savingsVersusStandalone(), 2) }}</span>
                            @endif
                            <ul class="ec-bundle-items">
                                @foreach ($bundle->items as $item)
                                    <li>&bull; {{ $item->quantity }}&times; {{ $item->product->name ?? 'Deleted product' }}</li>
                                @endforeach
                            </ul>
                            <div class="ec-sub" style="margin-top: 8px;">
                                COGS PKR {{ number_format($bundle->bundleUnitCogs(), 2) }} &middot;
                                Margin {{ number_format($bundle->grossMarginPercent(), 1) }}%
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="ec-card ec-chart-card mt-3 mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0">Sales Trend</h6>
            <div class="ec-chart-legend">
                <span><span class="ec-legend-dot" style="background: #e79a91;"></span>Revenue</span>
                <span><span class="ec-legend-dot" style="background: #c9a66b;"></span>Units Sold</span>
            </div>
        </div>
        @if (count($chartLabels) > 0)
            <canvas id="salesTrendChart" height="90" class="mt-2"></canvas>
        @else
            <div class="ec-chart-empty">No sales data for this period yet.</div>
        @endif
    </div>

    <div class="ec-card">
        <h6 class="mb-3">Recent Sales</h6>
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
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total Revenue</th>
                        <th>Reason/Notes</th>
                        @moduleEdit('ecommerce')<th class="text-end">Actions</th>@endmoduleEdit
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td>{{ $sale->created_at->format('d M Y') }}</td>
                            <td>{{ $sale->reference_id ?? '—' }}</td>
                            <td>{{ $sale->customer_name ?? '—' }}</td>
                            <td>{{ $sale->product->name ?? '—' }}</td>
                            <td class="text-end">{{ number_format($sale->quantity, 0) }}</td>
                            <td><span class="ec-channel-badge {{ $channelBadgeClasses[$sale->channel] ?? 'ec-channel-backbar' }}">{{ $sale->channel }}</span></td>
                            <td class="text-end">PKR {{ number_format($sale->unit_price, 2) }}</td>
                            <td class="text-end">PKR {{ number_format($sale->total_price, 2) }}</td>
                            <td>{{ $sale->reason ?? '—' }}</td>
                            @moduleEdit('ecommerce')
                                <td class="text-end">
                                    <form action="{{ route('ecommerce.sales.destroy', $sale->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this record? This will add the items back to your inventory.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            @endmoduleEdit
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted">No sales logged yet &mdash; use "Log New Sale" above.</td></tr>
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
                                        Bundle / Kit{{ $bundles->isEmpty() ? ' (none created yet)' : '' }}
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
                                            <option value="Shopify (Pakistan)">Shopify (Pakistan)</option>
                                            <option value="Organic (Pakistan)">Organic (Pakistan)</option>
                                            <option value="Salon (Old Airport)">Salon (Old Airport)</option>
                                            <option value="Salon (Wakrah)">Salon (Wakrah)</option>
                                            <option value="Organic (Qatar)">Organic (Qatar)</option>
                                        </optgroup>
                                        <optgroup label="Non-Revenue">
                                            <option value="Backbar Use (Qatar)">Backbar Use (Qatar)</option>
                                            <option value="Damage/Expiry (Pakistan)">Damage/Expiry (Pakistan)</option>
                                            <option value="Damage/Expiry (Qatar)">Damage/Expiry (Qatar)</option>
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
                                    <label class="form-label">Customer Name <span class="ec-sub">(optional)</span></label>
                                    <input type="text" name="customer_name" class="form-control" placeholder="Optional">
                                </div>
                                <div>
                                    <label class="form-label">Customer WhatsApp <span class="ec-sub">(powers refill queue)</span></label>
                                    <input type="text" name="customer_phone" class="form-control" placeholder="e.g. +974 5512 3456">
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

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Courier/Shipping Cost (PKR) <span class="ec-sub">(optional)</span></label>
                                    <input type="number" step="0.01" min="0" name="shipping_cost" class="form-control" placeholder="0.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Taxes (PKR) <span class="ec-sub">(optional)</span></label>
                                    <input type="number" step="0.01" min="0" name="tax_amount" class="form-control" placeholder="0.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Attributed Meta Ads Cost (CAC) <span class="ec-sub">(optional)</span></label>
                                    <input type="number" step="0.01" min="0" name="meta_ad_allocation" class="form-control" placeholder="0.00">
                                </div>
                            </div>

                            <div class="ec-grand-total">
                                <span class="ec-grand-total-label">Grand Total</span>
                                <span class="ec-grand-total-value" id="salesGrandTotalValue">PKR 0.00</span>
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
            const nonRevenueChannels = ['Backbar Use (Qatar)', 'Damage/Expiry (Pakistan)', 'Damage/Expiry (Qatar)'];

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

                salesGrandTotalValue.textContent = 'PKR ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function restoreDefaultPrice() {
                const selectedOption = salesProductSelect.options[salesProductSelect.selectedIndex];
                const price = selectedOption ? selectedOption.getAttribute('data-unit-price') : null;
                salesUnitPriceInput.value = price ? parseFloat(price).toFixed(2) : '';
            }

            // Bundles price themselves from the saved bundle record, so the
            // manual Unit Price field is swapped out for a read-only mirror of
            // the bundle price and excluded from the POST.
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
                    // Never submitted in bundle mode — the server prices it.
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
                salesUnitPriceInput.readOnly = false;
                applySaleMode();
            });
        </script>

        {{-- Create Bundle Modal --}}
        <div class="modal fade" id="bundleModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.bundles.store') }}" method="POST" id="bundleForm">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Create Bundle / Kit</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="ec-form-grid mb-3">
                                <div>
                                    <label class="form-label">Bundle Name</label>
                                    <input type="text" name="name" id="bundleNameInput" class="form-control" placeholder="e.g. Hair Repair Kit" required>
                                </div>
                                <div>
                                    <label class="form-label">Bundle Price (PKR)</label>
                                    <input type="number" step="0.01" min="0" name="bundle_price" id="bundlePriceInput" class="form-control" placeholder="e.g. 3500" required>
                                </div>
                            </div>

                            <label class="form-label">Products in this bundle</label>
                            <div id="bundleItemsRows"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="bundleAddItemBtn">+ Add Product</button>

                            <div class="ec-accounting-note mt-3" id="bundleSummary" style="display: none;">
                                <span>💰</span>
                                <div id="bundleSummaryText"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save Bundle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            // Standalone prices so the modal can show live bundle savings.
            const BUNDLE_PRODUCT_PRICES = @json($products->mapWithKeys(fn ($p) => [$p->id => (float) ($salePricesByProduct[$p->id] ?? $p->selling_price ?? 0)]));
            const bundleProductOptionsHtml = `@foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }}</option>@endforeach`;

            const bundleItemsRows = document.getElementById('bundleItemsRows');
            const bundlePriceInput = document.getElementById('bundlePriceInput');
            const bundleSummary = document.getElementById('bundleSummary');
            const bundleSummaryText = document.getElementById('bundleSummaryText');
            let bundleItemIndex = 0;

            function refreshBundleSummary() {
                let standalone = 0;
                let lines = 0;

                bundleItemsRows.querySelectorAll('.bundle-item-row').forEach(function (row) {
                    const productId = row.querySelector('.bundle-product-select').value;
                    const qty = parseInt(row.querySelector('.bundle-qty-input').value, 10) || 0;
                    if (productId && qty > 0) {
                        standalone += (BUNDLE_PRODUCT_PRICES[productId] || 0) * qty;
                        lines++;
                    }
                });

                if (lines === 0) {
                    bundleSummary.style.display = 'none';
                    return;
                }

                const price = parseFloat(bundlePriceInput.value) || 0;
                const savings = standalone - price;
                bundleSummaryText.innerHTML =
                    '<strong>Standalone value:</strong> PKR ' + standalone.toFixed(2) +
                    ' · <strong>Bundle price:</strong> PKR ' + price.toFixed(2) +
                    (savings > 0
                        ? ' · Customer saves PKR ' + savings.toFixed(2)
                        : (savings < 0 ? ' · ⚠ Priced ABOVE standalone by PKR ' + Math.abs(savings).toFixed(2) : ''));
                bundleSummary.style.display = '';
            }

            function addBundleItemRow() {
                const index = bundleItemIndex++;
                const row = document.createElement('div');
                row.className = 'row g-2 align-items-end mb-2 bundle-item-row';
                row.innerHTML =
                    '<div class="col-7">' +
                        '<select name="items[' + index + '][ecommerce_product_id]" class="form-select bundle-product-select" required>' +
                            '<option value="" disabled selected>Select product</option>' + bundleProductOptionsHtml +
                        '</select>' +
                    '</div>' +
                    '<div class="col-3">' +
                        '<input type="number" step="1" min="1" value="1" name="items[' + index + '][quantity]" class="form-control bundle-qty-input" required>' +
                    '</div>' +
                    '<div class="col-2">' +
                        '<button type="button" class="btn btn-sm btn-outline-danger w-100 bundle-remove-btn"><i class="bi bi-trash"></i></button>' +
                    '</div>';

                bundleItemsRows.appendChild(row);
                row.querySelector('.bundle-product-select').addEventListener('change', refreshBundleSummary);
                row.querySelector('.bundle-qty-input').addEventListener('input', refreshBundleSummary);
                row.querySelector('.bundle-remove-btn').addEventListener('click', function () {
                    row.remove();
                    refreshBundleSummary();
                });
            }

            document.getElementById('bundleAddItemBtn').addEventListener('click', function () {
                addBundleItemRow();
                refreshBundleSummary();
            });
            bundlePriceInput.addEventListener('input', refreshBundleSummary);

            document.getElementById('bundleModal').addEventListener('shown.bs.modal', function () {
                if (bundleItemsRows.children.length === 0) {
                    addBundleItemRow();
                }
            });

            document.getElementById('bundleModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('bundleForm').reset();
                bundleItemsRows.innerHTML = '';
                bundleSummary.style.display = 'none';
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
                                            return '  Revenue: PKR ' + Number(context.parsed.y).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
                                        return 'PKR ' + Number(value).toLocaleString('en-US');
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
