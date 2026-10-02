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
                revenueFill.addColorStop(0, 'rgba(231, 154, 145, 0.32)');
                revenueFill.addColorStop(1, 'rgba(231, 154, 145, 0)');

                new Chart(ctx, {
                    data: {
                        labels: @json($chartLabels),
                        datasets: [
                            {
                                type: 'line',
                                label: 'Revenue',
                                data: @json($chartRevenue),
                                borderColor: '#e79a91',
                                backgroundColor: revenueFill,
                                fill: true,
                                tension: 0.4,
                                borderWidth: 2,
                                pointRadius: 3,
                                pointBackgroundColor: '#e79a91',
                                pointHoverRadius: 5,
                                pointBorderColor: '#241e1c',
                                pointBorderWidth: 2,
                                yAxisID: 'y',
                                order: 1,
                            },
                            {
                                type: 'bar',
                                label: 'Units Sold',
                                data: @json($chartUnits),
                                backgroundColor: 'rgba(201, 166, 107, 0.4)',
                                hoverBackgroundColor: 'rgba(201, 166, 107, 0.6)',
                                borderRadius: 5,
                                borderSkipped: false,
                                barPercentage: 0.45,
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
                                grid: { display: false, drawBorder: false },
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
