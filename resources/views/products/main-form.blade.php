<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="productForm">
                @csrf
                <input type="hidden" name="_method" id="formMethod">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-2">
                        <label>Product Name</label>
                        <input type="text" name="name" id="productName" class="form-control" required>
                    </div>

                    <div class="mb-2">
                        <label>SKU (optional)</label>
                        <input type="text" name="sku" id="productSku" class="form-control">
                    </div>

                    <div class="mb-2">
                        <label>Price (QAR)</label>
                        <input type="number" name="price" id="productPrice" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" id="submitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
