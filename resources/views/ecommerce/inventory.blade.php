@extends('layouts.app')
@section('title', 'Inventory & Production')

@include('ecommerce._styles')

@section('content')
    <div class="ec-header">
        <div>
            <h4>Two-Tier Inventory &amp; Production</h4>
            <p>Raw materials on hand, finished goods in stock, and the production runs that convert one into the other.</p>
        </div>
        @moduleEdit('ecommerce')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productionModal">+ Log Production Run</button>
        @endmoduleEdit
    </div>

    @include('ecommerce._nav')

    <div class="ec-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Tier 1 &mdash; Raw Materials</h6>
            @moduleEdit('ecommerce')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#rawMaterialModal">+ Add Raw Material</button>
            @endmoduleEdit
        </div>
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Remaining / Original</th>
                        @moduleEdit('ecommerce')<th></th>@endmoduleEdit
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rawMaterials as $material)
                        <tr>
                            <td>{{ $material->type ?: '—' }}</td>
                            <td class="{{ $material->current_stock < 0 ? 'ec-negative' : '' }}">{{ number_format($material->current_stock, 2) }} / {{ number_format($material->initial_stock, 2) }} {{ $material->unit_of_measure }}</td>
                            @moduleEdit('ecommerce')
                                <td>
                                    <form action="{{ route('ecommerce.raw-materials.destroy', $material->id) }}" method="POST" onsubmit="return confirm('Delete this raw material?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            @endmoduleEdit
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">No raw materials logged yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="ec-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Tier 2 &mdash; Finished Goods</h6>
            @moduleEdit('ecommerce')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">+ Add Finished Product</button>
            @endmoduleEdit
        </div>
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Current Stock</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->sku ?? '—' }}</td>
                            <td class="{{ $product->current_stock < 0 ? 'ec-negative' : '' }}">{{ number_format($product->current_stock, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">No finished goods yet &mdash; use "+ Add Finished Product" above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @moduleEdit('ecommerce')
        {{-- Add Raw Material Modal --}}
        <div class="modal fade" id="rawMaterialModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.raw-materials.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Add Raw Material</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <select name="type" id="rawMaterialTypeSelect" class="form-select">
                                    <option value="" selected>Select a category</option>
                                    @foreach ($rawMaterialTypes as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                    <option value="__add_new__">+ Add New Category</option>
                                </select>
                                <input type="text" id="rawMaterialTypeCustomInput" class="form-control mt-2" placeholder="Type the new category name" style="display: none;">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Current Stock</label>
                                <input type="number" step="0.01" min="0" name="current_stock" class="form-control" value="0" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Unit of Measure</label>
                                <input type="text" name="unit_of_measure" class="form-control" placeholder="e.g. ml, units, boxes" required>
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
            const rawMaterialTypeSelect = document.getElementById('rawMaterialTypeSelect');
            const rawMaterialTypeCustomInput = document.getElementById('rawMaterialTypeCustomInput');

            rawMaterialTypeSelect.addEventListener('change', function () {
                if (this.value === '__add_new__') {
                    rawMaterialTypeCustomInput.style.display = 'block';
                    rawMaterialTypeCustomInput.name = 'type';
                    rawMaterialTypeCustomInput.required = true;
                    rawMaterialTypeSelect.removeAttribute('name');
                    rawMaterialTypeCustomInput.focus();
                } else {
                    rawMaterialTypeCustomInput.style.display = 'none';
                    rawMaterialTypeCustomInput.removeAttribute('name');
                    rawMaterialTypeCustomInput.required = false;
                    rawMaterialTypeCustomInput.value = '';
                    rawMaterialTypeSelect.name = 'type';
                }
            });

            document.getElementById('rawMaterialModal').addEventListener('hidden.bs.modal', function () {
                this.querySelector('form').reset();
                rawMaterialTypeCustomInput.style.display = 'none';
                rawMaterialTypeCustomInput.removeAttribute('name');
                rawMaterialTypeCustomInput.required = false;
                rawMaterialTypeCustomInput.value = '';
                rawMaterialTypeSelect.name = 'type';
            });
        </script>

        {{-- Log Production Run Modal --}}
        <div class="modal fade" id="productionModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.production.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Log Production Run</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <h6 class="mb-3">A. What was produced?</h6>
                            <div class="mb-3">
                                <label class="form-label">Finished Product</label>
                                <select name="ecommerce_product_id" class="form-select" required>
                                    <option value="" disabled selected>Select product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Quantity Produced</label>
                                <input type="number" step="0.01" min="0.01" name="quantity_produced" class="form-control" placeholder="e.g. 100" required>
                            </div>

                            <hr style="border-color: var(--ec-border);">

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">B. Raw materials used for this entire batch</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addMaterialUsedRowBtn">+ Add Raw Material</button>
                            </div>
                            <p class="ec-sub">Enter the <strong>total</strong> quantity of each raw material consumed for this whole production run &mdash; not per unit.</p>
                            <div id="materialsUsedRows"></div>
                            @if ($rawMaterials->isEmpty())
                                <p class="ec-sub mb-0">No raw materials yet &mdash; add some in Tier 1 above first.</p>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Log Run</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const materialOptionsHtml = `@foreach ($rawMaterials as $material)<option value="{{ $material->id }}">{{ $material->name }} ({{ $material->unit_of_measure }})</option>@endforeach`;
            let materialUsedRowIndex = 0;

            function addMaterialUsedRow() {
                const index = materialUsedRowIndex++;
                const row = document.createElement('div');
                row.className = 'row g-2 align-items-end mb-2 material-used-row';
                row.innerHTML =
                    '<div class="col-6">' +
                        '<select name="materials_used[' + index + '][ecommerce_raw_material_id]" class="form-select" required>' +
                            '<option value="" disabled selected>Select raw material</option>' +
                            materialOptionsHtml +
                        '</select>' +
                    '</div>' +
                    '<div class="col-4">' +
                        '<input type="number" step="0.01" min="0.01" name="materials_used[' + index + '][quantity_used]" class="form-control" placeholder="Total used" required>' +
                    '</div>' +
                    '<div class="col-2">' +
                        '<button type="button" class="btn btn-outline-danger w-100 remove-material-used-row">&times;</button>' +
                    '</div>';

                document.getElementById('materialsUsedRows').appendChild(row);

                row.querySelector('.remove-material-used-row').addEventListener('click', function () {
                    row.remove();
                });
            }

            document.getElementById('addMaterialUsedRowBtn').addEventListener('click', addMaterialUsedRow);

            document.getElementById('productionModal').addEventListener('hidden.bs.modal', function () {
                this.querySelector('form').reset();
                document.getElementById('materialsUsedRows').innerHTML = '';
            });
        </script>

        {{-- Add Finished Product Modal --}}
        <div class="modal fade" id="addProductModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="productForm" action="{{ route('ecommerce.products.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Add Finished Product</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Product Name</label>
                                <input type="text" name="name" id="f_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SKU</label>
                                <input type="text" name="sku" id="f_sku" class="form-control" placeholder="e.g. RS-100" required>
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
            document.getElementById('addProductModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('productForm').reset();
            });
        </script>
    @endmoduleEdit
@endsection
