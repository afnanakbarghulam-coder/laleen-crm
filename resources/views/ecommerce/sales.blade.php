@extends('layouts.app')
@section('title', 'Sales & Usage')

@include('ecommerce._styles')

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

    <div class="ec-card">
        <h6 class="mb-3">Recent Sales</h6>
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Channel</th>
                        <th>Reason/Notes</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td>{{ $sale->created_at->format('d M Y') }}</td>
                            <td>{{ $sale->customer_name ?? '—' }}</td>
                            <td>{{ $sale->product->name ?? '—' }}</td>
                            <td>{{ $sale->quantity }}</td>
                            <td><span class="ec-badge" style="background: rgba(217,143,131,0.14); color: var(--ec-ink);">{{ $sale->channel }}</span></td>
                            <td>{{ $sale->reason ?? '—' }}</td>
                            <td>PKR {{ number_format($sale->total_price, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No sales logged yet &mdash; use "Log New Sale" above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @moduleEdit('ecommerce')
        {{-- Log New Sale Modal --}}
        <div class="modal fade" id="salesModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.sales.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Log New Sale</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Finished Product</label>
                                <select name="ecommerce_product_id" id="salesProductSelect" class="form-select" required>
                                    <option value="" disabled selected>Select product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" data-unit-price="{{ $salePricesByProduct[$product->id] ?? $product->selling_price ?? '' }}">{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Customer Name <span class="ec-sub">(optional)</span></label>
                                <input type="text" name="customer_name" class="form-control" placeholder="Optional">
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Quantity Sold</label>
                                    <input type="number" step="1" min="1" name="quantity" class="form-control" placeholder="e.g. 5" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Unit Price</label>
                                    <input type="number" step="0.01" min="0" name="unit_price" id="salesUnitPriceInput" class="form-control" placeholder="e.g. 1500">
                                </div>
                            </div>
                            <div class="mb-3 mt-3">
                                <label class="form-label">Sales Channel</label>
                                <select name="channel" id="salesChannelSelect" class="form-select" required>
                                    <option value="" disabled selected>Select channel</option>
                                    <optgroup label="Revenue">
                                        <option value="Shopify (Pakistan)">Shopify (Pakistan)</option>
                                        <option value="Organic (Pakistan)">Organic (Pakistan)</option>
                                        <option value="Salon (Qatar)">Salon (Qatar)</option>
                                        <option value="Organic (Qatar)">Organic (Qatar)</option>
                                    </optgroup>
                                    <optgroup label="Non-Revenue">
                                        <option value="Backbar Use (Qatar)">Backbar Use (Qatar)</option>
                                        <option value="Damage/Expiry (Pakistan)">Damage/Expiry (Pakistan)</option>
                                        <option value="Damage/Expiry (Qatar)">Damage/Expiry (Qatar)</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reason/Notes <span class="ec-sub">(optional)</span></label>
                                <input type="text" name="reason" class="form-control" placeholder="e.g. Backbar restock, damaged in transit">
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
            const salesUnitPriceInput = document.getElementById('salesUnitPriceInput');
            const salesChannelSelect = document.getElementById('salesChannelSelect');
            const nonRevenueChannels = ['Backbar Use (Qatar)', 'Damage/Expiry (Pakistan)', 'Damage/Expiry (Qatar)'];

            salesProductSelect.addEventListener('change', function () {
                const selectedOption = this.options[this.selectedIndex];
                const price = selectedOption.getAttribute('data-unit-price');
                if (price && !nonRevenueChannels.includes(salesChannelSelect.value)) {
                    salesUnitPriceInput.value = parseFloat(price).toFixed(2);
                }
            });

            salesChannelSelect.addEventListener('change', function () {
                if (nonRevenueChannels.includes(this.value)) {
                    salesUnitPriceInput.value = 0;
                    salesUnitPriceInput.required = false;
                } else {
                    salesUnitPriceInput.required = true;
                }
            });

            document.getElementById('salesModal').addEventListener('hidden.bs.modal', function () {
                this.querySelector('form').reset();
                salesUnitPriceInput.required = true;
            });
        </script>
    @endmoduleEdit
@endsection
