@extends('layouts.app')
@section('title', 'Unit Economics')

@include('ecommerce._styles')

@section('content')
    <div class="ec-header">
        <div>
            <h4>Product Cost &amp; Unit Economics Calculator</h4>
            <p>Bill of materials, gross margin and breakeven CAC per SKU.</p>
        </div>
        @moduleEdit('ecommerce')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetProductForm()">+ Add Product</button>
        @endmoduleEdit
    </div>

    @include('ecommerce._nav')

    <div class="ec-card">
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Selling Price</th>
                        <th>Total COGS</th>
                        <th>Gross Profit</th>
                        <th>Gross Margin %</th>
                        <th>Breakeven CAC</th>
                        @moduleEdit('ecommerce')<th width="100">Actions</th>@endmoduleEdit
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->sku }}</td>
                            <td>PKR {{ number_format($product->selling_price, 2) }}</td>
                            <td>PKR {{ number_format($product->total_cogs, 2) }}</td>
                            <td class="{{ $product->gross_profit >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($product->gross_profit, 2) }}</td>
                            <td>{{ number_format($product->gross_margin_percent, 1) }}%</td>
                            <td>PKR {{ number_format($product->breakeven_cac, 2) }}</td>
                            @moduleEdit('ecommerce')
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-warning edit-btn" data-product='@json($product)' title="Edit"><i class="bi bi-pencil-square"></i></button>
                                    <form action="{{ route('ecommerce.products.destroy', $product->id) }}" method="POST" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this product?')"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            @endmoduleEdit
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">No product cost sheets yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @moduleEdit('ecommerce')
        <div class="modal fade" id="productModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="productForm" action="{{ route('ecommerce.products.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="_method" id="formMethod" value="">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalTitle">Add Product</h5>
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
            const calcFieldIds = ['selling_price', 'liquid_cost_per_ml', 'volume_ml', 'bottle_cost', 'pump_cost', 'label_cost', 'box_cost', 'labor_cost', 'shipping_cost', 'payment_gateway_fee_percent'];

            function num(id) {
                return parseFloat(document.getElementById('f_' + id).value) || 0;
            }

            function runCalculator() {
                const liquidCost = num('liquid_cost_per_ml') * num('volume_ml');
                const totalCogs = liquidCost + num('bottle_cost') + num('pump_cost') + num('label_cost') + num('box_cost') + num('labor_cost') + num('shipping_cost');
                const sellingPrice = num('selling_price');
                const gatewayFee = sellingPrice * (num('payment_gateway_fee_percent') / 100);
                const grossProfit = sellingPrice - totalCogs - gatewayFee;
                const margin = sellingPrice > 0 ? (grossProfit / sellingPrice) * 100 : 0;

                document.getElementById('calc_cogs').textContent = 'PKR ' + totalCogs.toFixed(2);
                document.getElementById('calc_profit').textContent = 'PKR ' + grossProfit.toFixed(2);
                document.getElementById('calc_margin').textContent = margin.toFixed(1) + '%';
                document.getElementById('calc_cac').textContent = 'PKR ' + grossProfit.toFixed(2);
            }

            calcFieldIds.forEach(function (id) {
                document.getElementById('f_' + id).addEventListener('input', runCalculator);
            });

            function resetProductForm() {
                document.getElementById('modalTitle').innerText = 'Add Product';
                document.getElementById('productForm').action = '{{ route('ecommerce.products.store') }}';
                document.getElementById('formMethod').value = '';
                document.getElementById('productForm').reset();
                document.getElementById('f_payment_gateway_fee_percent').value = 2.5;
                runCalculator();
            }

            document.querySelectorAll('.edit-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const product = JSON.parse(this.dataset.product);
                    const modal = new bootstrap.Modal(document.getElementById('productModal'));

                    document.getElementById('modalTitle').innerText = 'Edit Product';
                    document.getElementById('productForm').action = '{{ url('ecommerce/products') }}/' + product.id;
                    document.getElementById('formMethod').value = 'PUT';

                    calcFieldIds.forEach(function (id) {
                        document.getElementById('f_' + id).value = product[id];
                    });
                    document.getElementById('f_name').value = product.name;
                    document.getElementById('f_sku').value = product.sku;

                    runCalculator();
                    modal.show();
                });
            });

            document.getElementById('productModal').addEventListener('hidden.bs.modal', resetProductForm);
        </script>
    @endmoduleEdit
@endsection
