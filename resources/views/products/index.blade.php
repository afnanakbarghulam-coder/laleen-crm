@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Retail Products</h4>
        @moduleEdit('products')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
                + Add Product
            </button>
        @endmoduleEdit
    </div>

    <table class="table table-bordered align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>SKU</th>
                <th>Price (QAR)</th>
                <th>Stock</th>
                <th width="120">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->sku ?? '—' }}</td>
                    <td>{{ number_format($product->price, 2) }}</td>
                    <td>
                        @php
                            $stockQty = (int) ($product->stock_quantity ?? 0);
                            $lowThresh = (int) ($product->low_stock_threshold ?? 3);
                        @endphp
                        @if ($stockQty <= 0)
                            <span class="badge bg-danger">{{ $stockQty }} · Out of Stock</span>
                        @elseif ($stockQty <= $lowThresh)
                            <span class="badge bg-warning text-dark">{{ $stockQty }} · Low Stock</span>
                        @else
                            <span class="badge bg-success">{{ $stockQty }} in stock</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        @moduleEdit('products')
                            <button type="button" class="btn btn-sm btn-outline-warning edit-btn" data-product='@json($product)' title="Edit">
                                <i class="bi bi-pencil-square"></i></button>

                            <form action="{{ route('products.destroy', $product->id) }}" method="POST" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Delete this product?')">
                                    <i class="bi bi-trash"></i></button>
                            </form>
                        @else
                            <span class="text-muted small">—</span>
                        @endmoduleEdit
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No products yet</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @moduleEdit('products')
        @include('products.main-form')

        <script>
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    editProduct(JSON.parse(this.dataset.product));
                });
            });

            function editProduct(product) {
                const modal = new bootstrap.Modal(document.getElementById('productModal'));

                document.getElementById('modalTitle').innerText = 'Edit Product';
                document.getElementById('productForm').action = `/products/${product.id}`;
                document.getElementById('formMethod').value = 'PUT';

                document.getElementById('productName').value = product.name;
                document.getElementById('productSku').value = product.sku ?? '';
                document.getElementById('productPrice').value = product.price;
                if (document.getElementById('productStock')) {
                    document.getElementById('productStock').value = product.stock_quantity ?? 0;
                }
                if (document.getElementById('productLowStockThreshold')) {
                    document.getElementById('productLowStockThreshold').value = product.low_stock_threshold ?? 3;
                }

                modal.show();
            }

            document.getElementById('productModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('modalTitle').innerText = 'Add Product';
                document.getElementById('productForm').action = '{{ route('products.store') }}';
                document.getElementById('formMethod').value = '';
                document.getElementById('productForm').reset();
            });
        </script>
    @endmoduleEdit
@endsection
