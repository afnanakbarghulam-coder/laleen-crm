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
                        <th>Name</th>
                        <th>Type</th>
                        <th>Current Stock</th>
                        <th>Unit of Measure</th>
                        @moduleEdit('ecommerce')<th></th>@endmoduleEdit
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rawMaterials as $material)
                        <tr>
                            <td>{{ $material->name }}</td>
                            <td>{{ $material->type ?? '—' }}</td>
                            <td class="{{ $material->current_stock < 0 ? 'ec-negative' : '' }}">{{ number_format($material->current_stock, 2) }}</td>
                            <td>{{ $material->unit_of_measure }}</td>
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
                        <tr><td colspan="5" class="text-center text-muted">No raw materials logged yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="ec-card">
        <h6 class="mb-3">Tier 2 &mdash; Finished Goods</h6>
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Current Stock</th>
                        <th>Recipe</th>
                        @moduleEdit('ecommerce')<th></th>@endmoduleEdit
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->sku ?? '—' }}</td>
                            <td class="{{ $product->current_stock < 0 ? 'ec-negative' : '' }}">{{ number_format($product->current_stock, 2) }}</td>
                            <td>
                                @forelse ($product->rawMaterials as $ingredient)
                                    <span class="ec-sub">{{ $ingredient->name }} &times; {{ number_format($ingredient->pivot->quantity_required, 4) }} {{ $ingredient->unit_of_measure }}/unit</span><br>
                                @empty
                                    <span class="ec-sub">No recipe set</span>
                                @endforelse
                            </td>
                            @moduleEdit('ecommerce')
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-warning recipe-btn" data-product='@json($product)' title="Manage Recipe"><i class="bi bi-list-check"></i></button>
                                </td>
                            @endmoduleEdit
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No finished goods yet &mdash; add one on the Unit Economics tab.</td></tr>
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
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <input type="text" name="type" class="form-control" placeholder="e.g. Liquid, Packaging">
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
                                <label class="form-label">Quantity Produced</label>
                                <input type="number" step="0.01" min="0.01" name="quantity_produced" class="form-control" placeholder="e.g. 100" required>
                            </div>
                            <p class="ec-sub mb-0">Finished stock will increase by this amount, and each recipe ingredient's raw material stock will decrease by quantity &times; its required amount per unit.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Log Run</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Manage Recipe Modal --}}
        <div class="modal fade" id="recipeModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="recipeModalTitle">Manage Recipe</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table ec-table align-middle" id="recipeItemsTable">
                            <thead>
                                <tr>
                                    <th>Raw Material</th>
                                    <th>Qty Required / Unit</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="recipeItemsBody"></tbody>
                        </table>

                        <form id="recipeAddForm" method="POST" class="row g-2 align-items-end mt-2">
                            @csrf
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Raw Material</label>
                                <select name="ecommerce_raw_material_id" class="form-select" required>
                                    @foreach ($rawMaterials as $material)
                                        <option value="{{ $material->id }}">{{ $material->name }} ({{ $material->unit_of_measure }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-4">
                                <label class="form-label small text-muted mb-1">Qty Required / Unit</label>
                                <input type="number" step="0.0001" min="0.0001" name="quantity_required" class="form-control" required>
                            </div>
                            <div class="col-2">
                                <button type="submit" class="btn btn-primary w-100">Add</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
            const recipeCsrfToken = '{{ csrf_token() }}';

            document.querySelectorAll('.recipe-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const product = JSON.parse(this.dataset.product);
                    const modal = new bootstrap.Modal(document.getElementById('recipeModal'));

                    document.getElementById('recipeModalTitle').innerText = 'Manage Recipe — ' + product.name;
                    document.getElementById('recipeAddForm').action = '{{ url('ecommerce/products') }}/' + product.id + '/recipe';

                    const body = document.getElementById('recipeItemsBody');
                    body.innerHTML = '';

                    if (!product.raw_materials || product.raw_materials.length === 0) {
                        body.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No ingredients yet</td></tr>';
                    } else {
                        product.raw_materials.forEach(function (ingredient) {
                            const row = document.createElement('tr');
                            const nameCell = document.createElement('td');
                            nameCell.textContent = ingredient.name;
                            const qtyCell = document.createElement('td');
                            qtyCell.textContent = parseFloat(ingredient.pivot.quantity_required).toFixed(4) + ' ' + ingredient.unit_of_measure;
                            const actionCell = document.createElement('td');

                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '{{ url('ecommerce/products') }}/' + product.id + '/recipe/' + ingredient.id;
                            form.onsubmit = function () { return confirm('Remove this ingredient?'); };
                            form.innerHTML =
                                '<input type="hidden" name="_token" value="' + recipeCsrfToken + '">' +
                                '<input type="hidden" name="_method" value="DELETE">' +
                                '<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>';

                            actionCell.appendChild(form);
                            row.appendChild(nameCell);
                            row.appendChild(qtyCell);
                            row.appendChild(actionCell);
                            body.appendChild(row);
                        });
                    }

                    modal.show();
                });
            });
        </script>
    @endmoduleEdit
@endsection
