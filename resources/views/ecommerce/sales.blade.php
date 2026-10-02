<div class="ec-card">
    <h6 class="mb-3">Recent Sales</h6>
    <div class="table-responsive">
        <table class="table ec-table align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Channel</th>
                    <th>Total Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr>
                        <td>{{ $sale->created_at->format('d M Y') }}</td>
                        <td>{{ $sale->product->name ?? '—' }}</td>
                        <td>{{ $sale->quantity }}</td>
                        <td><span class="ec-badge" style="background: rgba(217,143,131,0.14); color: var(--ec-ink);">{{ $sale->channel }}</span></td>
                        <td>PKR {{ number_format($sale->total_price, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">No sales logged yet &mdash; use "Log New Sale" above.</td></tr>
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
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Quantity Sold</label>
                                <input type="number" step="1" min="1" name="quantity" class="form-control" placeholder="e.g. 5" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Unit Price</label>
                                <input type="number" step="0.01" min="0" name="unit_price" id="salesUnitPriceInput" class="form-control" placeholder="e.g. 1500" required>
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label">Sales Channel</label>
                            <select name="channel" class="form-select" required>
                                <option value="" disabled selected>Select channel</option>
                                <option value="Shopify (Pakistan)">Shopify (Pakistan)</option>
                                <option value="Organic (Pakistan)">Organic (Pakistan)</option>
                                <option value="Salon (Qatar)">Salon (Qatar)</option>
                                <option value="Organic (Qatar)">Organic (Qatar)</option>
                            </select>
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

        salesProductSelect.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-unit-price');
            if (price) {
                salesUnitPriceInput.value = parseFloat(price).toFixed(2);
            }
        });

        document.getElementById('salesModal').addEventListener('hidden.bs.modal', function () {
            this.querySelector('form').reset();
        });
    </script>
@endmoduleEdit
