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
                        <tr><td colspan="5" class="text-center text-muted">No finished goods yet &mdash; use "+ Add Finished Product" above.</td></tr>
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
                                <label class="form-label">Selling Price (PKR)</label>
                                <input type="number" step="0.01" min="0" name="selling_price" id="f_selling_price" class="form-control calc-field" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Liquid Cost / ml (PKR)</label>
                                <input type="number" step="0.0001" min="0" name="liquid_cost_per_ml" id="f_liquid_cost_per_ml" class="form-control calc-field" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Volume (ml)</label>
                                <input type="number" step="0.01" min="0" name="volume_ml" id="f_volume_ml" class="form-control calc-field" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bottle Cost (PKR)</label>
                                <input type="number" step="0.01" min="0" name="bottle_cost" id="f_bottle_cost" class="form-control calc-field" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Pump Cost (PKR)</label>
                                <input type="number" step="0.01" min="0" name="pump_cost" id="f_pump_cost" class="form-control calc-field" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Label Cost (PKR)</label>
                                <input type="number" step="0.01" min="0" name="label_cost" id="f_label_cost" class="form-control calc-field" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Box Cost (PKR)</label>
                                <input type="number" step="0.01" min="0" name="box_cost" id="f_box_cost" class="form-control calc-field" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Labor &amp; Bottling (PKR)</label>
                                <input type="number" step="0.01" min="0" name="labor_cost" id="f_labor_cost" class="form-control calc-field" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Shipping Materials (PKR)</label>
                                <input type="number" step="0.01" min="0" name="shipping_cost" id="f_shipping_cost" class="form-control calc-field" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Gateway Fee %</label>
                                <input type="number" step="0.01" min="0" max="100" name="payment_gateway_fee_percent" id="f_payment_gateway_fee_percent" class="form-control calc-field" value="2.5" required>
                            </div>

                            <div class="col-12">
                                <div class="ec-card mb-0">
                                    <div class="row text-center">
                                        <div class="col"><h6>Total COGS</h6><div id="calc_cogs" class="ec-value">PKR 0.00</div></div>
                                        <div class="col"><h6>Gross Profit</h6><div id="calc_profit" class="ec-value">PKR 0.00</div></div>
                                        <div class="col"><h6>Gross Margin %</h6><div id="calc_margin" class="ec-value">0%</div></div>
                                        <div class="col"><h6>Breakeven CAC</h6><div id="calc_cac" class="ec-value">PKR 0.00</div></div>
                                    </div>
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
            const productCalcFieldIds = ['selling_price', 'liquid_cost_per_ml', 'volume_ml', 'bottle_cost', 'pump_cost', 'label_cost', 'box_cost', 'labor_cost', 'shipping_cost', 'payment_gateway_fee_percent'];

            function productNum(id) {
                return parseFloat(document.getElementById('f_' + id).value) || 0;
            }

            function runProductCalculator() {
                const liquidCost = productNum('liquid_cost_per_ml') * productNum('volume_ml');
                const totalCogs = liquidCost + productNum('bottle_cost') + productNum('pump_cost') + productNum('label_cost') + productNum('box_cost') + productNum('labor_cost') + productNum('shipping_cost');
                const sellingPrice = productNum('selling_price');
                const gatewayFee = sellingPrice * (productNum('payment_gateway_fee_percent') / 100);
                const grossProfit = sellingPrice - totalCogs - gatewayFee;
                const margin = sellingPrice > 0 ? (grossProfit / sellingPrice) * 100 : 0;

                document.getElementById('calc_cogs').textContent = 'PKR ' + totalCogs.toFixed(2);
                document.getElementById('calc_profit').textContent = 'PKR ' + grossProfit.toFixed(2);
                document.getElementById('calc_margin').textContent = margin.toFixed(1) + '%';
                document.getElementById('calc_cac').textContent = 'PKR ' + grossProfit.toFixed(2);
            }

            productCalcFieldIds.forEach(function (id) {
                document.getElementById('f_' + id).addEventListener('input', runProductCalculator);
            });

            document.getElementById('addProductModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('productForm').reset();
                document.getElementById('f_payment_gateway_fee_percent').value = 2.5;
                runProductCalculator();
            });
        </script>

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
