@extends('layouts.app')
@section('title', 'Pricing & Profit')

@include('ecommerce._styles')

@php
    $canEdit = auth()->check() && auth()->user()->canEdit('ecommerce');
@endphp

@section('content')
    <div class="ec-header">
        <div>
            <div class="ec-sub text-uppercase fw-bold mb-1" style="font-size: 11px; letter-spacing: .08em; color: var(--ec-gold);">Financials &amp; Inventory</div>
            <h4>Pricing &amp; Profit</h4>
            <p>A standalone sandbox to model costs and margins for any finished product &mdash; experiment freely, save when you're happy.</p>
        </div>
    </div>

    @include('ecommerce.partials._financials_tabs')

    <div class="ec-card">
        <div class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="form-label">Finished Product</label>
                <select id="pricingProductSelect" class="form-select">
                    <option value="" {{ (string) $selectedProductId === '' ? 'selected' : '' }}>Select a product to price</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" {{ (string) $selectedProductId === (string) $product->id ? 'selected' : '' }}>{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-outline-primary w-100" id="pricingAutoFillBtn" {{ $canEdit ? '' : 'disabled' }}>Auto-Fill Costs from Inventory</button>
            </div>
        </div>
        @if ($products->isEmpty())
            <p class="ec-sub mb-0 mt-2">No products yet &mdash; add some in Inventory &amp; Production first.</p>
        @elseif ($rawMaterials->isEmpty())
            <p class="ec-sub mb-0 mt-2">No raw materials yet &mdash; add some via a restock expense first.</p>
        @endif
        @unless ($canEdit)
            <p class="ec-sub mb-0 mt-2">View-only access &mdash; select a product to review its saved costs and margin. Editing is disabled.</p>
        @endunless
    </div>

    <form id="pricingForm" action="{{ route('ecommerce.pricing.store') }}" method="POST">
        @csrf
        <input type="hidden" name="ecommerce_product_id" id="pricingProductId">

        <div class="ec-card">
            <h6 class="mb-3" style="text-transform: none; font-size: 14px; letter-spacing: 0;">Cost Inputs</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Liquid Base Cost</label>
                    <input type="number" step="0.01" min="0" name="liquid_cost" id="pricingLiquidCost" class="form-control pricing-input" value="0" {{ $canEdit ? '' : 'readonly' }}>
                </div>
                <div class="col-md-4" id="pricingBottleWrapper">
                    <label class="form-label">Bottle/Jar Cost</label>
                    <input type="number" step="0.01" min="0" name="bottle_cost" id="pricingBottleCost" class="form-control pricing-input" value="0" {{ $canEdit ? '' : 'readonly' }}>
                </div>
                <div class="col-md-4" id="pricingLabelWrapper">
                    <label class="form-label">Label Cost</label>
                    <input type="number" step="0.01" min="0" name="label_cost" id="pricingLabelCost" class="form-control pricing-input" value="0" {{ $canEdit ? '' : 'readonly' }}>
                </div>
                <div class="col-md-4" id="pricingPumpWrapper">
                    <label class="form-label">Pump/Cap Cost</label>
                    <input type="number" step="0.01" min="0" name="pump_cost" id="pricingPumpCost" class="form-control pricing-input" value="0" {{ $canEdit ? '' : 'readonly' }}>
                </div>
                <div class="col-md-4" id="pricingBoxWrapper">
                    <label class="form-label">Outer Box Cost</label>
                    <input type="number" step="0.01" min="0" name="box_cost" id="pricingBoxCost" class="form-control pricing-input" value="0" {{ $canEdit ? '' : 'readonly' }}>
                </div>
            </div>
        </div>

        <div class="ec-card">
            <h6 class="mb-3" style="text-transform: none; font-size: 14px; letter-spacing: 0;">Revenue</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Proposed Selling Price (PKR)</label>
                    <input type="number" step="0.01" min="0" name="selling_price" id="pricingSellingPrice" class="form-control pricing-input" value="0" {{ $canEdit ? '' : 'readonly' }}>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="ec-card">
                    <h6>Total Cost Per Unit</h6>
                    <div class="ec-value" id="pricingTotalCost">PKR 0.00</div>
                    <div class="ec-sub"><span id="totalCostFormulaText">Select a product</span></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="ec-card">
                    <h6>Gross Profit (PKR)</h6>
                    <div class="ec-value" id="pricingGrossProfit">PKR 0.00</div>
                    <div class="ec-sub">Selling Price &minus; Total Cost</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="ec-card">
                    <h6>Profit Margin (%)</h6>
                    <div class="ec-value" id="pricingMargin">0.00%</div>
                    <div class="ec-sub">Gross Profit &divide; Selling Price</div>
                </div>
            </div>
        </div>

        @moduleEdit('ecommerce')
            <button type="submit" class="btn btn-primary" id="pricingSaveBtn" disabled>Save Pricing Model</button>
        @endmoduleEdit
    </form>

    <div class="ec-card mt-4">
        <h6 class="mb-3" style="text-transform: none; font-size: 14px; letter-spacing: 0;">Saved Pricing Models</h6>
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Finished Product</th>
                        <th>Total Cost</th>
                        <th>Selling Price</th>
                        <th>Gross Profit</th>
                        <th>Profit Margin (%)</th>
                        @moduleEdit('ecommerce')<th></th>@endmoduleEdit
                    </tr>
                </thead>
                <tbody>
                    @forelse ($savedPricingModels as $savedModel)
                        @php
                            $savedTotalCost = (float) $savedModel->liquid_cost + (float) $savedModel->bottle_cost + (float) $savedModel->label_cost + (float) $savedModel->pump_cost + (float) $savedModel->box_cost;
                            $savedSellingPrice = (float) $savedModel->selling_price;
                            $savedGrossProfit = $savedSellingPrice - $savedTotalCost;
                            $savedMargin = $savedSellingPrice > 0 ? ($savedGrossProfit / $savedSellingPrice) * 100 : 0;
                        @endphp
                        <tr>
                            <td>{{ $savedModel->product->name ?? 'Deleted Product' }}</td>
                            <td>PKR {{ number_format($savedTotalCost, 2) }}</td>
                            <td>PKR {{ number_format($savedSellingPrice, 2) }}</td>
                            <td class="{{ $savedGrossProfit < 0 ? 'ec-negative' : 'ec-positive' }}">PKR {{ number_format($savedGrossProfit, 2) }}</td>
                            <td class="{{ $savedMargin < 0 ? 'ec-negative' : 'ec-positive' }}">{{ number_format($savedMargin, 2) }}%</td>
                            @moduleEdit('ecommerce')
                                <td>
                                    <form action="{{ route('ecommerce.pricing.destroy', $savedModel->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this pricing model?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            @endmoduleEdit
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No pricing models saved yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const CAN_EDIT = @json($canEdit);
        const pricingData = @json($pricingModels);
        const productsForPricing = @json($productsForPricing);
        const rawMaterialsForPricing = @json($rawMaterialsForPricing);
        const componentTypeNames = @json($componentTypeNames);
        const productLineNames = @json($productLineNames);

        // Which packaging component fields are relevant to each Product Line,
        // matching the strict BOM enforced server-side for production runs.
        const VISIBLE_COMPONENTS_BY_PRODUCT_LINE = {
            'Hair Oil': ['Bottle/Jar', 'Label', 'Outer Box'],
            'Shampoo': ['Bottle/Jar', 'Label', 'Pump/Cap', 'Outer Box'],
        };

        const TOTAL_COST_FORMULA_TEXT_BY_PRODUCT_LINE = {
            'Hair Oil': 'Liquid + Bottle + Label + Box',
            'Shampoo': 'Liquid + Bottle + Label + Box + Pump',
        };

        const productSelect = document.getElementById('pricingProductSelect');
        const productIdInput = document.getElementById('pricingProductId');
        const liquidCostInput = document.getElementById('pricingLiquidCost');
        const bottleCostInput = document.getElementById('pricingBottleCost');
        const labelCostInput = document.getElementById('pricingLabelCost');
        const pumpCostInput = document.getElementById('pricingPumpCost');
        const boxCostInput = document.getElementById('pricingBoxCost');
        const sellingPriceInput = document.getElementById('pricingSellingPrice');
        const totalCostEl = document.getElementById('pricingTotalCost');
        const totalCostFormulaEl = document.getElementById('totalCostFormulaText');
        const grossProfitEl = document.getElementById('pricingGrossProfit');
        const marginEl = document.getElementById('pricingMargin');
        const saveBtn = document.getElementById('pricingSaveBtn');

        const COMPONENT_FIELD_WRAPPERS = {
            'Bottle/Jar': document.getElementById('pricingBottleWrapper'),
            'Label': document.getElementById('pricingLabelWrapper'),
            'Pump/Cap': document.getElementById('pricingPumpWrapper'),
            'Outer Box': document.getElementById('pricingBoxWrapper'),
        };
        const COMPONENT_FIELD_INPUTS = {
            'Bottle/Jar': bottleCostInput,
            'Label': labelCostInput,
            'Pump/Cap': pumpCostInput,
            'Outer Box': boxCostInput,
        };

        // Shows only the packaging fields relevant to the given Product Line
        // name, resetting (and keeping at 0) every field that's hidden so it
        // never silently contributes to the total cost.
        function updateVisibleComponentFields(productLineName) {
            const visibleComponents = VISIBLE_COMPONENTS_BY_PRODUCT_LINE[productLineName] || [];

            Object.keys(COMPONENT_FIELD_WRAPPERS).forEach(function (componentTypeName) {
                const isVisible = visibleComponents.includes(componentTypeName);

                COMPONENT_FIELD_WRAPPERS[componentTypeName].style.display = isVisible ? '' : 'none';

                if (!isVisible) {
                    COMPONENT_FIELD_INPUTS[componentTypeName].value = 0;
                }
            });

            totalCostFormulaEl.textContent = productSelect.value === ''
                ? 'Select a product'
                : (TOTAL_COST_FORMULA_TEXT_BY_PRODUCT_LINE[productLineName] || 'Liquid');

            return visibleComponents;
        }

        function recalculate() {
            const liquid = parseFloat(liquidCostInput.value) || 0;
            const bottle = parseFloat(bottleCostInput.value) || 0;
            const label = parseFloat(labelCostInput.value) || 0;
            const pump = parseFloat(pumpCostInput.value) || 0;
            const box = parseFloat(boxCostInput.value) || 0;
            const sellingPrice = parseFloat(sellingPriceInput.value) || 0;

            const totalCost = liquid + bottle + label + pump + box;
            const grossProfit = sellingPrice - totalCost;
            const margin = sellingPrice > 0 ? (grossProfit / sellingPrice) * 100 : 0;

            totalCostEl.textContent = 'PKR ' + totalCost.toFixed(2);
            grossProfitEl.textContent = 'PKR ' + grossProfit.toFixed(2);
            marginEl.textContent = margin.toFixed(2) + '%';

            grossProfitEl.classList.toggle('ec-positive', grossProfit >= 0);
            grossProfitEl.classList.toggle('ec-negative', grossProfit < 0);
            marginEl.classList.toggle('ec-positive', margin >= 0);
            marginEl.classList.toggle('ec-negative', margin < 0);
        }

        document.querySelectorAll('.pricing-input').forEach(function (input) {
            input.addEventListener('input', recalculate);
        });

        // Smart BOM auto-fill: finds every raw material sharing the selected
        // product's product_line_id, prices the Liquid Base component against
        // the product's parsed unit_size, and maps each packaging component's
        // last_purchased_unit_cost directly onto its own input — but only for
        // component types the current Product Line's BOM actually shows.
        function autoFillCostsFromInventory() {
            const productId = productSelect.value;
            const product = productsForPricing[productId];

            if (!product || !product.product_line_id) {
                return;
            }

            const productLineName = productLineNames[product.product_line_id];
            const visibleComponents = updateVisibleComponentFields(productLineName);
            const unitSizeValue = parseFloat(product.unit_size) || 0;
            let liquidCost = 0;

            Object.keys(rawMaterialsForPricing).forEach(function (materialId) {
                const material = rawMaterialsForPricing[materialId];

                if (String(material.product_line_id) !== String(product.product_line_id)) {
                    return;
                }

                const componentTypeName = componentTypeNames[material.component_type_id];
                const unitCost = Number(material.last_purchased_unit_cost) || 0;

                if (componentTypeName === 'Liquid Base') {
                    liquidCost = unitCost * unitSizeValue;
                } else if (visibleComponents.includes(componentTypeName)) {
                    COMPONENT_FIELD_INPUTS[componentTypeName].value = unitCost.toFixed(2);
                }
            });

            liquidCostInput.value = liquidCost.toFixed(2);
            recalculate();
        }

        document.getElementById('pricingAutoFillBtn').addEventListener('click', autoFillCostsFromInventory);

        function applyPricingForSelectedProduct() {
            const productId = productSelect.value;
            productIdInput.value = productId;

            const saved = Object.prototype.hasOwnProperty.call(pricingData, productId) ? pricingData[productId] : null;
            liquidCostInput.value = saved ? Number(saved.liquid_cost) : 0;
            bottleCostInput.value = saved ? Number(saved.bottle_cost) : 0;
            labelCostInput.value = saved ? Number(saved.label_cost) : 0;
            pumpCostInput.value = saved ? Number(saved.pump_cost) : 0;
            boxCostInput.value = saved ? Number(saved.box_cost) : 0;
            sellingPriceInput.value = saved ? Number(saved.selling_price) : 0;

            // Apply visibility after loading saved values so any field made
            // irrelevant by the product's line is forced back to 0 rather
            // than silently keeping a stale saved amount.
            const product = productsForPricing[productId];
            const productLineName = product ? productLineNames[product.product_line_id] : undefined;
            updateVisibleComponentFields(productLineName);

            if (saveBtn) {
                saveBtn.disabled = productId === '';
            }

            recalculate();

            // No saved pricing model yet for this product — auto-fill from
            // Tier 1 inventory instead of leaving costs at 0. Skipped for
            // view-only users so they only ever see persisted figures.
            if (CAN_EDIT && !saved && productId !== '') {
                autoFillCostsFromInventory();
            }
        }

        productSelect.addEventListener('change', applyPricingForSelectedProduct);

        // Re-applies on initial load too, so a product pre-selected via
        // ?product=<id> (e.g. redirected back here right after saving)
        // shows its data immediately instead of resetting to blank.
        applyPricingForSelectedProduct();
    </script>

    {{-- ============ Bundles & Kits ============ --}}
    <div id="bundles"></div>
    <div class="ec-card mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
            <h6 class="mb-0">Bundles &amp; Kits</h6>
            @moduleEdit('ecommerce')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bundleModal">+ Create Bundle</button>
            @endmoduleEdit
        </div>
        <p class="ec-sub mb-3">Multi-item kits sold at a single price. Selling one decrements every component's stock and splits the revenue across them.</p>
        @if ($bundles->isEmpty())
            <div class="ec-chart-empty" style="height: 120px;">No bundles yet &mdash; create one to start selling kits.</div>
        @else
            <div class="row g-3">
                @foreach ($bundles as $bundle)
                    <div class="col-md-4">
                        <div class="ec-bundle-card">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="ec-bundle-name">{{ $bundle->name }}</div>
                                @moduleEdit('ecommerce')
                                    <form action="{{ route('ecommerce.bundles.destroy', $bundle->id) }}" method="POST" onsubmit="return confirm('Delete this bundle?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endmoduleEdit
                            </div>
                            <div class="ec-value" style="font-size: 20px; margin-top: 6px;">PKR {{ number_format($bundle->bundle_price, 2) }}</div>
                            @if ($bundle->savingsVersusStandalone() > 0)
                                <span class="ec-badge ec-badge-bundle">Saves PKR {{ number_format($bundle->savingsVersusStandalone(), 2) }}</span>
                            @endif
                            <ul class="ec-bundle-items">
                                @foreach ($bundle->items as $item)
                                    <li>&bull; {{ $item->quantity }}&times; {{ $item->product->name ?? 'Deleted product' }}</li>
                                @endforeach
                            </ul>
                            <div class="ec-sub" style="margin-top: 8px;">
                                COGS PKR {{ number_format($bundle->bundleUnitCogs(), 2) }} &middot;
                                Margin {{ number_format($bundle->grossMarginPercent(), 1) }}%
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @moduleEdit('ecommerce')
        {{-- Create Bundle Modal --}}
        <div class="modal fade" id="bundleModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: #1c1615; border: 1px solid rgba(217, 143, 131, 0.25); color: #f3e9e7;">
                    <form action="{{ route('ecommerce.bundles.store') }}" method="POST" id="bundleForm">
                        @csrf
                        <div class="modal-header" style="border-bottom: 1px solid rgba(217, 143, 131, 0.16);">
                            <h5 class="modal-title" style="color: #d98f83; font-weight: 700;">Create Bundle / Kit</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="ec-form-grid mb-3">
                                <div>
                                    <label class="form-label">Bundle Name</label>
                                    <input type="text" name="name" id="bundleNameInput" class="form-control ec-form-control" placeholder="e.g. Hair Repair Kit" required>
                                </div>
                                <div>
                                    <label class="form-label">Bundle Price (PKR)</label>
                                    <input type="number" step="0.01" min="0" name="bundle_price" id="bundlePriceInput" class="form-control ec-form-control" placeholder="e.g. 3500" required>
                                </div>
                            </div>

                            <label class="form-label">Products in this bundle</label>
                            <div id="bundleItemsRows"></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="bundleAddItemBtn">+ Add Product</button>

                            {{-- Live Profit Footer (3-Column Calculation Bar) --}}
                            <div class="row g-3 mt-4 pt-3" style="border-top: 1px solid rgba(217, 143, 131, 0.16);">
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3 h-100" style="background: #251d1c; border: 1px solid rgba(255, 255, 255, 0.08);">
                                        <div class="text-uppercase fw-semibold mb-1" style="font-size: 11px; letter-spacing: .05em; color: #c9a39a;">Total BOM COGS</div>
                                        <div class="fw-bold" id="bundleLiveCogs" style="font-size: 20px; color: #f3e9e7; font-variant-numeric: tabular-nums;">PKR 0.00</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3 h-100" style="background: #251d1c; border: 1px solid rgba(255, 255, 255, 0.08);">
                                        <div class="text-uppercase fw-semibold mb-1" style="font-size: 11px; letter-spacing: .05em; color: #c9a39a;">Gross Profit</div>
                                        <div class="fw-bold" id="bundleLiveProfit" style="font-size: 20px; color: #f3e9e7; font-variant-numeric: tabular-nums;">PKR 0.00</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-3 h-100" style="background: #251d1c; border: 1px solid rgba(255, 255, 255, 0.08);">
                                        <div class="text-uppercase fw-semibold mb-1" style="font-size: 11px; letter-spacing: .05em; color: #c9a39a;">Margin</div>
                                        <div class="fw-bold" id="bundleLiveMargin" style="font-size: 20px; color: #d98f83; font-variant-numeric: tabular-nums;">0%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer" style="border-top: 1px solid rgba(217, 143, 131, 0.16);">
                            <button type="submit" class="btn btn-primary">Save Bundle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const bundleProductOptionsHtml = `@foreach ($products as $product)<option value="{{ $product->id }}" data-cogs="{{ $product->unit_cogs }}">{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }} (COGS: PKR {{ number_format((float) $product->unit_cogs, 2) }})</option>@endforeach`;

            const bundleItemsRows = document.getElementById('bundleItemsRows');
            const bundlePriceInput = document.getElementById('bundlePriceInput');
            const bundleLiveCogsEl = document.getElementById('bundleLiveCogs');
            const bundleLiveProfitEl = document.getElementById('bundleLiveProfit');
            const bundleLiveMarginEl = document.getElementById('bundleLiveMargin');
            let bundleItemIndex = 0;

            function recalculateBundleFooter() {
                let totalBomCogs = 0;

                bundleItemsRows.querySelectorAll('.bundle-item-row').forEach(function (row) {
                    const selectEl = row.querySelector('.bundle-product-select');
                    const qtyInput = row.querySelector('.bundle-qty-input');
                    const selectedOption = selectEl ? selectEl.options[selectEl.selectedIndex] : null;
                    const unitCogs = selectedOption ? (parseFloat(selectedOption.getAttribute('data-cogs')) || 0) : 0;
                    const qty = parseInt(qtyInput ? qtyInput.value : 0, 10) || 0;

                    if (selectEl && selectEl.value && qty > 0) {
                        totalBomCogs += unitCogs * qty;
                    }
                });

                const bundlePrice = parseFloat(bundlePriceInput.value) || 0;
                const grossProfit = bundlePrice - totalBomCogs;
                const margin = bundlePrice > 0 ? (grossProfit / bundlePrice) * 100 : 0;

                bundleLiveCogsEl.textContent = 'PKR ' + totalBomCogs.toFixed(2);
                bundleLiveProfitEl.textContent = 'PKR ' + grossProfit.toFixed(2);
                bundleLiveMarginEl.textContent = (bundlePrice > 0 ? margin.toFixed(1).replace(/\.0$/, '') : '0') + '%';

                bundleLiveProfitEl.style.color = grossProfit > 0 ? '#8ea88a' : (grossProfit < 0 ? '#d98f83' : '#f3e9e7');
                bundleLiveMarginEl.style.color = margin >= 60 ? '#8ea88a' : '#d98f83';
            }

            function addBundleItemRow() {
                const index = bundleItemIndex++;
                const row = document.createElement('div');
                row.className = 'row g-2 align-items-end mb-2 bundle-item-row';
                row.innerHTML =
                    '<div class="col-7">' +
                        '<select name="items[' + index + '][ecommerce_product_id]" class="form-select ec-form-select bundle-product-select" required>' +
                            '<option value="" data-cogs="0" disabled selected>Select product</option>' + bundleProductOptionsHtml +
                        '</select>' +
                    '</div>' +
                    '<div class="col-3">' +
                        '<input type="number" step="1" min="1" value="1" name="items[' + index + '][quantity]" class="form-control ec-form-control bundle-qty-input" required>' +
                    '</div>' +
                    '<div class="col-2">' +
                        '<button type="button" class="btn btn-sm btn-outline-danger w-100 bundle-remove-btn"><i class="bi bi-trash"></i></button>' +
                    '</div>';

                bundleItemsRows.appendChild(row);
                row.querySelector('.bundle-product-select').addEventListener('change', recalculateBundleFooter);
                row.querySelector('.bundle-qty-input').addEventListener('input', recalculateBundleFooter);
                row.querySelector('.bundle-remove-btn').addEventListener('click', function () {
                    row.remove();
                    recalculateBundleFooter();
                });
            }

            document.getElementById('bundleAddItemBtn').addEventListener('click', function () {
                addBundleItemRow();
                recalculateBundleFooter();
            });
            bundlePriceInput.addEventListener('input', recalculateBundleFooter);

            document.getElementById('bundleModal').addEventListener('shown.bs.modal', function () {
                if (bundleItemsRows.children.length === 0) {
                    addBundleItemRow();
                }
                recalculateBundleFooter();
            });

            document.getElementById('bundleModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('bundleForm').reset();
                bundleItemsRows.innerHTML = '';
                recalculateBundleFooter();
            });
        </script>
    @endmoduleEdit
@endsection
