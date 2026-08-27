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

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Name</th>
                <th>SKU</th>
                <th>Price (QAR)</th>
                <th width="120">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->sku ?? '—' }}</td>
                    <td>{{ number_format($product->price, 2) }}</td>
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
                    <td colspan="4" class="text-center">No products yet</td>
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
