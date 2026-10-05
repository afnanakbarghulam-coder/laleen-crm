@extends('layouts.app')
@section('title', 'Stock Levels')

@include('ecommerce._styles')

@section('content')
    <div class="ec-header">
        <div>
            <h4>Stock Levels</h4>
            <p>Multi-location finished goods inventory &mdash; Pakistan is the manufacturing master, Qatar is the retail satellite.</p>
        </div>
        @moduleEdit('ecommerce')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#stockTransferModal">Transfer to Qatar</button>
        @endmoduleEdit
    </div>

    @include('ecommerce._nav')

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <p class="ec-sub mb-0">Scopes Batch History only &mdash; live stock and capital figures below always reflect right now.</p>
        <form method="GET" action="{{ route('ecommerce.stock.index') }}" class="d-flex align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <label for="startDateFilter" class="ec-sub mb-0">From</label>
                <input type="date" name="start_date" id="startDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ $startDate }}">
                <label for="endDateFilter" class="ec-sub mb-0">To</label>
                <input type="date" name="end_date" id="endDateFilter" class="form-control form-control-sm ec-date-filter" value="{{ $endDate }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Apply Filter</button>
        </form>
    </div>

    <div class="d-flex align-items-center gap-2 mb-2">
        <h6 class="mb-0" style="text-transform: none; font-size: 14px; letter-spacing: 0; color: var(--ec-ink);">Live Inventory Snapshot</h6>
        <span class="ec-badge ec-badge-live">Live (Unfiltered)</span>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <div class="ec-card ec-card-highlight">
                <h6>Total Capital in Inventory</h6>
                <div class="ec-value">PKR {{ number_format($totalCapitalInInventory, 2) }}</div>
                <div class="ec-sub">Unsold stock (Pakistan + Qatar) &times; BOM cost per unit</div>
            </div>
        </div>
    </div>

    <div class="ec-card">
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align: middle;">Product Name</th>
                        <th rowspan="2" style="vertical-align: middle;">Unit Size</th>
                        <th rowspan="2" style="vertical-align: middle;">Last Produced</th>
                        <th rowspan="2" style="vertical-align: middle;">Total Value</th>
                        <th colspan="3" class="ec-table-group-header">Pakistan (Warehouse)</th>
                        <th colspan="3" class="ec-table-group-header">Qatar (Retail)</th>
                    </tr>
                    <tr>
                        <th>Total Produced</th>
                        <th>Sold (Shopify/Organic)</th>
                        <th class="ec-col-divider">Remaining</th>
                        <th>Transferred In</th>
                        <th>Sold (Salon)</th>
                        <th>Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php
                            $totalProduced = (float) $product->stock_pakistan + (float) $product->sold_pakistan;
                            $transferredIn = (float) $product->stock_qatar + (float) $product->sold_qatar;
                            // "Last Produced" always reflects the true most recent run,
                            // regardless of the date filter — it's a live fact, not a
                            // historical report. Only the Batch History modal's list
                            // (below) is scoped to the selected date range.
                            $lastProductionRun = $product->productionRuns->first();
                            $productionHistory = $product->productionRuns
                                ->filter(fn ($run) => $run->created_at->between($historyRangeFrom, $historyRangeTo->copy()->endOfDay()))
                                ->map(fn ($run) => [
                                    'date' => $run->created_at->format('d M Y, h:i A'),
                                    'quantity' => number_format((float) $run->quantity_produced, 2),
                                    'logged_by' => $run->creator->name ?? '—',
                                ])
                                ->values();

                            $pricingModel = $pricingModels->get($product->id);
                            $unitCost = $pricingModel
                                ? (float) $pricingModel->liquid_cost + (float) $pricingModel->bottle_cost + (float) $pricingModel->label_cost + (float) $pricingModel->box_cost + (float) $pricingModel->pump_cost
                                : 0.0;
                            $totalStockBothLocations = (float) $product->stock_pakistan + (float) $product->stock_qatar;
                            $totalValue = $totalStockBothLocations * $unitCost;
                        @endphp
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>
                                @if ($product->unit_size)
                                    <span class="ec-unit-badge">{{ $product->unit_size }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($lastProductionRun)
                                    <button type="button"
                                            class="btn btn-link p-0 view-production-history-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#productionHistoryModal"
                                            data-product-name="{{ $product->name }}"
                                            data-history='@json($productionHistory)'>
                                        {{ $lastProductionRun->created_at->format('d M Y') }}
                                    </button>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="ec-stock-emphasis">PKR {{ number_format($totalValue, 2) }}</td>
                            <td>{{ number_format($totalProduced, 0) }}</td>
                            <td>{{ number_format($product->sold_pakistan, 0) }}</td>
                            <td class="ec-col-divider ec-stock-emphasis {{ $product->stock_pakistan < 0 ? 'ec-negative' : '' }}">
                                {{ number_format($product->stock_pakistan, 0) }}
                                @if ($product->stock_pakistan < 10)
                                    <span class="badge bg-danger">Low Stock</span>
                                @endif
                            </td>
                            <td>{{ number_format($transferredIn, 0) }}</td>
                            <td>{{ number_format($product->sold_qatar, 0) }}</td>
                            <td class="ec-stock-emphasis {{ $product->stock_qatar < 0 ? 'ec-negative' : '' }}">
                                {{ number_format($product->stock_qatar, 0) }}
                                @if ($product->stock_qatar < 10)
                                    <span class="badge bg-danger">Low Stock</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted">No finished goods yet &mdash; add some in Inventory &amp; Production first.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Production Batch History Modal --}}
    <div class="modal fade" id="productionHistoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productionHistoryModalTitle">Production History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="ec-sub mb-3">Showing runs from {{ $historyRangeFrom->format('d M Y') }} to {{ $historyRangeTo->format('d M Y') }}</p>
                    <div class="table-responsive">
                        <table class="table ec-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Quantity Produced</th>
                                    <th>Logged By</th>
                                </tr>
                            </thead>
                            <tbody id="productionHistoryRows"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.view-production-history-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const history = JSON.parse(this.dataset.history);
                const productName = this.dataset.productName;
                const rows = document.getElementById('productionHistoryRows');

                document.getElementById('productionHistoryModalTitle').textContent = 'Production History — ' + productName;
                rows.innerHTML = '';

                if (history.length === 0) {
                    rows.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No production runs in the selected date range</td></tr>';
                    return;
                }

                history.forEach(function (run) {
                    const tr = document.createElement('tr');
                    [run.date, run.quantity, run.logged_by].forEach(function (value) {
                        const td = document.createElement('td');
                        td.textContent = value;
                        tr.appendChild(td);
                    });
                    rows.appendChild(tr);
                });
            });
        });
    </script>

    @moduleEdit('ecommerce')
        {{-- Transfer to Qatar Modal --}}
        <div class="modal fade" id="stockTransferModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.stock.transfer') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Transfer to Qatar</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Finished Product</label>
                                <select name="ecommerce_product_id" class="form-select" required>
                                    <option value="" disabled selected>Select product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }} &mdash; {{ number_format($product->stock_pakistan, 0) }} in Pakistan</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" placeholder="e.g. 10" required>
                            </div>
                            <p class="ec-sub mb-0">Moves stock from Pakistan to Qatar. Cannot exceed what's currently available in Pakistan.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Transfer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endmoduleEdit
@endsection
