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

    <div class="row">
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total Revenue</h6>
                <div class="ec-value ec-positive">PKR {{ number_format($totalRevenue, 2) }}</div>
                <div class="ec-sub">Across all revenue channels, all time</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total Items Sold (Revenue Channels)</h6>
                <div class="ec-value">{{ number_format($totalItemsSoldRevenue, 0) }}</div>
                <div class="ec-sub">Shopify, Organic &amp; Salon branches</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total Items Used/Damaged (Non-Revenue)</h6>
                <div class="ec-value ec-negative">{{ number_format($totalItemsUsedDamaged, 0) }}</div>
                <div class="ec-sub">Backbar use &amp; damage/expiry write-offs</div>
            </div>
        </div>
    </div>

    <div class="ec-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h6 class="mb-0">Recent Sales</h6>
            <form method="GET" action="{{ route('ecommerce.sales.index') }}" class="dropdown">
                <button class="btn btn-sm ec-channel-filter dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    Filter by Channel
                    @if (count($selectedChannels) > 0)
                        <span class="ec-channel-filter-count">{{ count($selectedChannels) }}</span>
                    @endif
                </button>
                <div class="dropdown-menu ec-channel-filter-menu p-3">
                    <div class="ec-channel-filter-list">
                        @foreach ($channels as $channel)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="channels[]" value="{{ $channel }}" id="channelCheck{{ $loop->index }}" {{ in_array($channel, $selectedChannels, true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="channelCheck{{ $loop->index }}">{{ $channel }}</label>
                            </div>
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100 mt-3">Apply Filter</button>
                </div>
            </form>
        </div>
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
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted">No sales logged yet &mdash; use "Log New Sale" above.</td></tr>
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
                            <div class="ec-form-grid mb-3">
                                <div>
                                    <label class="form-label">Finished Product</label>
                                    <select name="ecommerce_product_id" id="salesProductSelect" class="form-select" required>
                                        <option value="" disabled selected>Select product</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" data-unit-price="{{ $salePricesByProduct[$product->id] ?? $product->selling_price ?? '' }}">{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Quantity Sold</label>
                                    <input type="number" step="1" min="1" name="quantity" id="salesQuantityInput" class="form-control" placeholder="e.g. 5" required>
                                </div>
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
                                <div>
                                    <label class="form-label">Unit Price</label>
                                    <input type="number" step="0.01" min="0" name="unit_price" id="salesUnitPriceInput" class="form-control" placeholder="e.g. 1500">
                                </div>
                            </div>

                            <div class="ec-form-grid mb-3">
                                <div>
                                    <label class="form-label">Customer Name <span class="ec-sub">(optional)</span></label>
                                    <input type="text" name="customer_name" class="form-control" placeholder="Optional">
                                </div>
                                <div>
                                    <label class="form-label">Reason/Notes <span class="ec-sub">(optional)</span></label>
                                    <input type="text" name="reason" class="form-control" placeholder="e.g. Backbar restock, damaged in transit">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Reference / Order ID <span class="ec-sub">(optional)</span></label>
                                <input type="text" name="reference_id" class="form-control" placeholder="e.g. Shopify order #1042">
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
            const salesProductSelect = document.getElementById('salesProductSelect');
            const salesQuantityInput = document.getElementById('salesQuantityInput');
            const salesUnitPriceInput = document.getElementById('salesUnitPriceInput');
            const salesChannelSelect = document.getElementById('salesChannelSelect');
            const salesGrandTotalValue = document.getElementById('salesGrandTotalValue');
            const nonRevenueChannels = ['Backbar Use (Qatar)', 'Damage/Expiry (Pakistan)', 'Damage/Expiry (Qatar)'];

            function updateGrandTotal() {
                const quantity = parseFloat(salesQuantityInput.value) || 0;
                const unitPrice = parseFloat(salesUnitPriceInput.value) || 0;
                const total = quantity * unitPrice;
                salesGrandTotalValue.textContent = 'PKR ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function restoreDefaultPrice() {
                const selectedOption = salesProductSelect.options[salesProductSelect.selectedIndex];
                const price = selectedOption ? selectedOption.getAttribute('data-unit-price') : null;
                salesUnitPriceInput.value = price ? parseFloat(price).toFixed(2) : '';
            }

            salesProductSelect.addEventListener('change', function () {
                if (!nonRevenueChannels.includes(salesChannelSelect.value)) {
                    restoreDefaultPrice();
                }
                updateGrandTotal();
            });

            salesChannelSelect.addEventListener('change', function () {
                if (nonRevenueChannels.includes(this.value)) {
                    salesUnitPriceInput.value = 0;
                    salesUnitPriceInput.readOnly = true;
                    salesUnitPriceInput.required = false;
                } else {
                    salesUnitPriceInput.readOnly = false;
                    salesUnitPriceInput.required = true;
                    restoreDefaultPrice();
                }
                updateGrandTotal();
            });

            salesQuantityInput.addEventListener('input', updateGrandTotal);
            salesUnitPriceInput.addEventListener('input', updateGrandTotal);

            document.getElementById('salesModal').addEventListener('hidden.bs.modal', function () {
                this.querySelector('form').reset();
                salesUnitPriceInput.required = true;
                salesUnitPriceInput.readOnly = false;
                updateGrandTotal();
            });
        </script>
    @endmoduleEdit
@endsection
