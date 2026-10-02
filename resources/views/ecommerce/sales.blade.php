@moduleEdit('ecommerce')
    {{-- Log Sale Modal --}}
    <div class="modal fade" id="salesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('ecommerce.sales.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Log Sale</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Finished Product</label>
                            <select name="ecommerce_product_id" class="form-select" required>
                                <option value="" disabled selected>Select product</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity Sold</label>
                            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" placeholder="e.g. 5" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sales Channel</label>
                            <select name="sales_channel" class="form-select" required>
                                <option value="" disabled selected>Select channel</option>
                                <option value="Shopify (Pakistan)">Shopify (Pakistan)</option>
                                <option value="Organic (Pakistan)">Organic (Pakistan)</option>
                                <option value="Salon (Qatar)">Salon (Qatar)</option>
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
@endmoduleEdit
