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
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h6 class="mb-0">Tier 1 &mdash; Raw Materials</h6>
            <form method="GET" action="{{ route('ecommerce.inventory.index') }}" class="d-flex align-items-center gap-2">
                <label for="rawMaterialsProductLineFilter" class="ec-sub mb-0">Product Line</label>
                <select name="product_line" id="rawMaterialsProductLineFilter" class="form-select form-select-sm ec-form-select" style="min-width: 160px;" onchange="this.form.submit()">
                    <option value="" {{ $filterProductLine ? '' : 'selected' }}>All</option>
                    @foreach ($filterableProductLines as $productLine)
                        <option value="{{ $productLine->name }}" {{ $filterProductLine === $productLine->name ? 'selected' : '' }}>{{ $productLine->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <p class="ec-sub mb-3">Stock is created and topped up exclusively by logging a restock expense on the Expenses tab.</p>

        @if ($filterProductLine === 'Shampoo')
            <div class="ec-bom-callout mb-3">
                💡 Production Requirements (Shampoo): 1 Bottle/Jar, 1 Pump/Cap, 1 Label, 1 Outer Box, and Liquid Base.
            </div>
        @elseif ($filterProductLine === 'Hair Oil')
            <div class="ec-bom-callout mb-3">
                💡 Production Requirements (Hair Oil): 1 Bottle/Jar, 1 Label, 1 Outer Box, and Liquid Base.
            </div>
        @endif

        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Product Line &mdash; Component Type</th>
                        <th>Remaining / Original</th>
                        <th>Last Unit Cost</th>
                        @moduleEdit('ecommerce')<th></th>@endmoduleEdit
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rawMaterials as $material)
                        <tr>
                            <td>{{ $material->productLine->name ?? 'Uncategorized' }} &mdash; {{ $material->componentType->name ?? 'Uncategorized' }}</td>
                            <td class="{{ $material->current_stock < 0 ? 'ec-negative' : '' }}">{{ number_format($material->current_stock, 2) }} / {{ number_format($material->initial_stock, 2) }} {{ $material->componentType?->name === 'Liquid Base' ? 'ml' : 'units' }}</td>
                            <td>{{ $material->last_purchased_unit_cost !== null ? 'PKR ' . number_format($material->last_purchased_unit_cost, 2) : '—' }}</td>
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
                        <tr><td colspan="4" class="text-center text-muted">No raw materials logged yet</td></tr>
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
                        <th>Unit Size</th>
                        <th>SKU</th>
                        <th>Current Stock</th>
                        @moduleEdit('ecommerce')<th></th>@endmoduleEdit
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->unit_size ?? '—' }}</td>
                            <td>{{ $product->sku ?? '—' }}</td>
                            <td class="{{ $product->stock_pakistan < 0 ? 'ec-negative' : '' }}">{{ number_format($product->stock_pakistan, 2) }}</td>
                            @moduleEdit('ecommerce')
                                <td>
                                    <form action="{{ route('ecommerce.products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('Delete this finished product? Any raw materials used to make it will be refunded back to stock.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            @endmoduleEdit
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">No finished goods yet &mdash; use "+ Add Finished Product" above.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @moduleEdit('ecommerce')
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
                                <select name="ecommerce_product_id" id="productionProductSelect" class="form-select" required>
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

                            <h6 class="mb-2">B. Raw materials used per unit</h6>
                            <p class="ec-sub">Materials are determined automatically by the product's line. Amounts default to the calculated value but can be corrected if a batch needs an override &mdash; restock Tier 1 inventory if a required component is missing.</p>
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
            const materialOptionsHtml = `@foreach ($allRawMaterials as $material)<option value="{{ $material->id }}">{{ $material->name }} ({{ $material->unit_of_measure }})</option>@endforeach`;
            const productsForRecipe = @json($productsForRecipe);
            const rawMaterialsForRecipe = @json($rawMaterialsForRecipe);
            let materialUsedRowIndex = 0;

            function addMaterialUsedRow(presetMaterialId, presetAmountPerUnit) {
                const index = materialUsedRowIndex++;
                const row = document.createElement('div');
                row.className = 'row g-2 align-items-end mb-2 material-used-row';
                row.innerHTML =
                    '<div class="col-7">' +
                        '<select name="materials_used[' + index + '][ecommerce_raw_material_id]" class="form-select" required>' +
                            '<option value="" disabled selected>Select raw material</option>' +
                            materialOptionsHtml +
                        '</select>' +
                    '</div>' +
                    '<div class="col-5">' +
                        '<input type="number" step="0.01" min="0.01" name="materials_used[' + index + '][amount_per_unit]" class="form-control" placeholder="Amount per unit" required>' +
                    '</div>';

                document.getElementById('materialsUsedRows').appendChild(row);

                if (presetMaterialId) {
                    row.querySelector('select').value = presetMaterialId;
                }
                if (presetAmountPerUnit !== undefined && presetAmountPerUnit !== null) {
                    row.querySelector('input[type="number"]').value = presetAmountPerUnit;
                }

                return row;
            }

            // Smart Recipe: selecting a finished product rebuilds Section B from
            // every raw material that shares the product's product_line_id,
            // pre-filling the per-unit amount (unit_size for the Liquid Base
            // component, 1 for packaging) — still fully editable afterward.
            document.getElementById('productionProductSelect').addEventListener('change', function () {
                document.getElementById('materialsUsedRows').innerHTML = '';

                const product = productsForRecipe[this.value];
                if (!product || !product.product_line_id) {
                    return;
                }

                const liquidAmountPerUnit = parseFloat(product.unit_size) || 0;

                Object.keys(rawMaterialsForRecipe).forEach(function (materialId) {
                    const material = rawMaterialsForRecipe[materialId];

                    if (String(material.product_line_id) !== String(product.product_line_id)) {
                        return;
                    }

                    const amountPerUnit = material.component_type_name === 'Liquid Base'
                        ? liquidAmountPerUnit
                        : 1;

                    addMaterialUsedRow(materialId, amountPerUnit);
                });
            });

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
                            <div class="col-md-6">
                                <label class="form-label">Product Line</label>
                                <select name="product_line_id" id="f_product_line_id" class="form-select" required>
                                    <option value="" disabled selected>Select a product line</option>
                                    @foreach ($filterableProductLines as $productLine)
                                        <option value="{{ $productLine->id }}">{{ $productLine->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Unit Size</label>
                                <div class="position-relative">
                                    <input type="number" step="0.01" min="0" name="unit_size" id="f_unit_size" class="form-control" placeholder="e.g. 100" style="padding-right: 38px;">
                                    <span class="position-absolute text-muted" style="right: 14px; top: 50%; transform: translateY(-50%); pointer-events: none;">ml</span>
                                </div>
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
