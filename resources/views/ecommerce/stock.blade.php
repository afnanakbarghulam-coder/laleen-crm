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

    <div class="ec-card">
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align: middle;">Product Name</th>
                        <th rowspan="2" style="vertical-align: middle;">Unit Size</th>
                        <th rowspan="2" style="vertical-align: middle;">Last Produced</th>
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
                            $lastProductionRun = $product->productionRuns->first();
                            $productionHistory = $product->productionRuns->map(fn ($run) => [
                                'date' => $run->created_at->format('d M Y, h:i A'),
                                'quantity' => number_format((float) $run->quantity_produced, 2),
                                'logged_by' => $run->creator->name ?? '—',
                            ]);
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
                        <tr><td colspan="9" class="text-center text-muted">No finished goods yet &mdash; add some in Inventory &amp; Production first.</td></tr>
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
                    rows.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No production runs yet</td></tr>';
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
