@extends('layouts.app')
@section('title', 'Pricing & Profit')

@include('ecommerce._styles')

@section('content')
    <div class="ec-header">
        <div>
            <h4>Pricing &amp; Profit</h4>
            <p>A standalone sandbox to model costs and margins for any finished product &mdash; experiment freely, save when you're happy.</p>
        </div>
    </div>

    @include('ecommerce._nav')

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
                <button type="button" class="btn btn-outline-primary w-100" id="pricingAutoFillBtn">Auto-Fill Costs from Inventory</button>
            </div>
        </div>
        @if ($products->isEmpty())
            <p class="ec-sub mb-0 mt-2">No products yet &mdash; add some in Inventory &amp; Production first.</p>
        @elseif ($rawMaterials->isEmpty())
            <p class="ec-sub mb-0 mt-2">No raw materials yet &mdash; add some via a restock expense first.</p>
        @endif
    </div>

    <form id="pricingForm" action="{{ route('ecommerce.pricing.store') }}" method="POST">
        @csrf
        <input type="hidden" name="ecommerce_product_id" id="pricingProductId">

        <div class="ec-card">
            <h6 class="mb-3" style="text-transform: none; font-size: 14px; letter-spacing: 0;">Cost Inputs</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Liquid / Formulation Cost</label>
                    <input type="number" step="0.01" min="0" name="liquid_cost" id="pricingLiquidCost" class="form-control pricing-input" value="0">
                </div>
                <div class="col-md-4" id="pricingBottleWrapper">
                    <label class="form-label">Bottle/Jar Cost</label>
                    <input type="number" step="0.01" min="0" name="bottle_cost" id="pricingBottleCost" class="form-control pricing-input" value="0">
                </div>
                <div class="col-md-4" id="pricingLabelWrapper">
                    <label class="form-label">Label Cost</label>
                    <input type="number" step="0.01" min="0" name="label_cost" id="pricingLabelCost" class="form-control pricing-input" value="0">
                </div>
                <div class="col-md-4" id="pricingPumpWrapper">
                    <label class="form-label">Pump/Cap Cost</label>
                    <input type="number" step="0.01" min="0" name="pump_cost" id="pricingPumpCost" class="form-control pricing-input" value="0">
                </div>
                <div class="col-md-4" id="pricingBoxWrapper">
                    <label class="form-label">Outer Box Cost</label>
                    <input type="number" step="0.01" min="0" name="box_cost" id="pricingBoxCost" class="form-control pricing-input" value="0">
                </div>
            </div>
        </div>

        <div class="ec-card">
            <h6 class="mb-3" style="text-transform: none; font-size: 14px; letter-spacing: 0;">Revenue</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Proposed Selling Price (PKR)</label>
                    <input type="number" step="0.01" min="0" name="selling_price" id="pricingSellingPrice" class="form-control pricing-input" value="0">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="ec-card">
                    <h6>Total Cost Per Unit</h6>
                    <div class="ec-value" id="pricingTotalCost">PKR 0.00</div>
                    <div class="ec-sub">Liquid + Bottle + Label + Pump + Box</div>
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

    <script>
        const pricingData = @json($pricingModels);
        const productsForPricing = @json($productsForPricing);
        const rawMaterialsForPricing = @json($rawMaterialsForPricing);
        const componentTypeNames = @json($componentTypeNames);
        const productLineNames = @json($productLineNames);

        // Which packaging component fields are relevant to each Product Line,
        // matching the strict BOM enforced server-side for production runs.
        const VISIBLE_COMPONENTS_BY_PRODUCT_LINE = {
            'Hair Oil': ['Bottle/Jar', 'Label'],
            'Shampoo': ['Bottle/Jar', 'Label', 'Pump/Cap'],
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
            // Tier 1 inventory instead of leaving costs at 0.
            if (!saved && productId !== '') {
                autoFillCostsFromInventory();
            }
        }

        productSelect.addEventListener('change', applyPricingForSelectedProduct);

        // Re-applies on initial load too, so a product pre-selected via
        // ?product=<id> (e.g. redirected back here right after saving)
        // shows its data immediately instead of resetting to blank.
        applyPricingForSelectedProduct();
    </script>
@endsection
