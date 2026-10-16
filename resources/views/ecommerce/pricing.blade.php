@extends('layouts.app')
@section('title', 'Pricing & Profit')

@include('ecommerce._styles')

@php
    $canEdit = auth()->check() && auth()->user()->canEdit('ecommerce');
@endphp

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

    {{-- ============ Offer Builder / Tier Stacker (Pricing Sandbox) ============ --}}
    <div id="tier-stacker"></div>
    <div class="ec-card mt-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <h6 class="mb-0" style="text-transform: none; font-size: 16px; letter-spacing: 0;">Bundle / Tier Stacker (Offer Builder Sandbox)</h6>
                <p class="ec-sub mb-0 mt-1">
                    Select 2 to 4 existing products to aggregate their exact BOM COGS, test a Blended 3x Tier Price, and validate Bundle Gross Margin % &amp; Total Gross Profit before pushing high-AOV offers to Meta Ads.
                </p>
            </div>
            <span class="ec-badge ec-badge-bundle" id="stackerStatusBadge">Select 2&ndash;4 products</span>
        </div>

        <div class="row g-3 mt-1">
            @for ($slot = 1; $slot <= 4; $slot++)
                <div class="col-md-3">
                    <label class="form-label" for="stackerProductSlot{{ $slot }}">
                        Product Slot {{ $slot }}
                        <span class="ec-sub">{{ $slot <= 2 ? '(Required)' : '(Optional)' }}</span>
                    </label>
                    <select id="stackerProductSlot{{ $slot }}" class="form-select stacker-product-select" data-slot="{{ $slot }}">
                        <option value="">{{ $slot <= 2 ? 'Select product #' . $slot : '— None (Optional) —' }}</option>
                        @foreach ($products as $product)
                            @php
                                $sp = $stackerProducts[$product->id] ?? null;
                                $spCogs = $sp ? number_format($sp['bom_cogs'], 2) : '0.00';
                            @endphp
                            <option value="{{ $product->id }}">
                                {{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }} — COGS PKR {{ $spCogs }}
                            </option>
                        @endforeach
                    </select>
                    <div class="ec-sub mt-1" id="stackerSlotMeta{{ $slot }}">No item selected</div>
                </div>
            @endfor
        </div>

        <div class="row g-3 align-items-end mt-2">
            <div class="col-md-5">
                <label class="form-label" for="stackerTierPriceInput">Blended 3x Tier Price (PKR)</label>
                <div class="d-flex gap-2">
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="stackerTierPriceInput"
                        class="form-control"
                        placeholder="e.g. 4500.00"
                        value="0"
                    >
                    <button type="button" class="btn btn-outline-primary text-nowrap" id="stackerSnap3xBtn" title="Set price to 3x Aggregated BOM COGS">
                        Use 3x COGS
                    </button>
                </div>
                <div class="ec-sub mt-1" id="stackerPriceHint">
                    3x BOM Floor: <strong id="stacker3xFloorValue">PKR 0.00</strong> &middot;
                    Standalone Retail Value: <strong id="stackerStandaloneValue">PKR 0.00</strong>
                </div>
            </div>
            <div class="col-md-7">
                <div class="ec-accounting-note mb-0" id="stackerBreakdownBox">
                    <span>📦</span>
                    <div id="stackerBreakdownText">
                        Pick at least <strong>2 products</strong> above to aggregate their exact BOM COGS and unlock tier margin validation.
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-md-4">
                <div class="ec-card mb-0">
                    <h6>Aggregated BOM COGS</h6>
                    <div class="ec-value" id="stackerTotalCogs">PKR 0.00</div>
                    <div class="ec-sub" id="stackerCogsSub">Exact sum of BOM COGS (2&ndash;4 items)</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="ec-card mb-0">
                    <h6>Total Gross Profit (PKR)</h6>
                    <div class="ec-value" id="stackerGrossProfit">PKR 0.00</div>
                    <div class="ec-sub">Blended 3x Tier Price &minus; Aggregated BOM COGS</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="ec-card mb-0">
                    <h6>Bundle Gross Margin (%)</h6>
                    <div class="ec-value" id="stackerGrossMargin">0.00%</div>
                    <div class="ec-sub" id="stackerMarginSub">Total Gross Profit &divide; Blended Tier Price</div>
                </div>
            </div>
        </div>
    </div>

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
        const stackerProducts = @json($stackerProducts);

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

        // ---- Offer Builder / Tier Stacker (2 to 4 products) ----------------
        const stackerSelects = Array.from(document.querySelectorAll('.stacker-product-select'));
        const stackerTierPriceInput = document.getElementById('stackerTierPriceInput');
        const stackerSnap3xBtn = document.getElementById('stackerSnap3xBtn');
        const stackerStatusBadge = document.getElementById('stackerStatusBadge');
        const stacker3xFloorValue = document.getElementById('stacker3xFloorValue');
        const stackerStandaloneValue = document.getElementById('stackerStandaloneValue');
        const stackerBreakdownText = document.getElementById('stackerBreakdownText');
        const stackerTotalCogsEl = document.getElementById('stackerTotalCogs');
        const stackerCogsSubEl = document.getElementById('stackerCogsSub');
        const stackerGrossProfitEl = document.getElementById('stackerGrossProfit');
        const stackerGrossMarginEl = document.getElementById('stackerGrossMargin');
        const stackerMarginSubEl = document.getElementById('stackerMarginSub');
        let stackerPriceManuallyEdited = false;

        function getSelectedStackerItems() {
            const selected = [];
            stackerSelects.forEach(function (selectEl) {
                const slot = selectEl.getAttribute('data-slot');
                const metaEl = document.getElementById('stackerSlotMeta' + slot);
                const prodId = selectEl.value;
                const item = prodId && stackerProducts[prodId] ? stackerProducts[prodId] : null;

                if (item) {
                    selected.push(item);
                    if (metaEl) {
                        metaEl.innerHTML =
                            'BOM COGS: <strong>PKR ' + Number(item.bom_cogs).toFixed(2) + '</strong>' +
                            ' · Retail: PKR ' + Number(item.standalone_price).toFixed(2) +
                            ' <span class="opacity-75">(' + item.cogs_source + ')</span>';
                    }
                } else if (metaEl) {
                    metaEl.textContent = 'No item selected';
                }
            });
            return selected;
        }

        function recalculateStacker(autoSet3xPrice) {
            const selected = getSelectedStackerItems();
            const count = selected.length;

            const aggregatedCogs = selected.reduce((sum, item) => sum + (Number(item.bom_cogs) || 0), 0);
            const standaloneTotal = selected.reduce((sum, item) => sum + (Number(item.standalone_price) || 0), 0);
            const floor3x = aggregatedCogs * 3;

            stacker3xFloorValue.textContent = 'PKR ' + floor3x.toFixed(2);
            stackerStandaloneValue.textContent = 'PKR ' + standaloneTotal.toFixed(2);

            if (count < 2) {
                stackerStatusBadge.textContent = count === 0
                    ? 'Select 2–4 products'
                    : 'Select 1 more product (min 2)';
                stackerStatusBadge.className = 'ec-badge ec-badge-bundle';

                stackerTotalCogsEl.textContent = 'PKR ' + aggregatedCogs.toFixed(2);
                stackerCogsSubEl.textContent = count === 1
                    ? '1 item selected — pick at least 2 for a valid bundle'
                    : 'Exact sum of BOM COGS (2–4 items)';
                stackerGrossProfitEl.textContent = 'PKR 0.00';
                stackerGrossMarginEl.textContent = '0.00%';
                stackerGrossProfitEl.classList.remove('ec-positive', 'ec-negative');
                stackerGrossMarginEl.classList.remove('ec-positive', 'ec-negative');
                stackerMarginSubEl.textContent = 'Requires 2–4 selected products';

                stackerBreakdownText.innerHTML =
                    'Pick at least <strong>2 products</strong> (up to 4) above to aggregate their exact BOM COGS and validate your Blended 3x Tier Price.';
                return;
            }

            if (autoSet3xPrice && !stackerPriceManuallyEdited) {
                stackerTierPriceInput.value = floor3x.toFixed(2);
            }

            const tierPrice = parseFloat(stackerTierPriceInput.value) || 0;
            const grossProfit = tierPrice - aggregatedCogs;
            const grossMargin = tierPrice > 0 ? (grossProfit / tierPrice) * 100 : 0;
            const customerSavings = standaloneTotal - tierPrice;

            stackerStatusBadge.textContent = count + '-Item Tier Stack Active';
            stackerStatusBadge.className = 'ec-badge ' + (grossMargin >= 66.6 ? 'ec-badge-injection' : 'ec-badge-bundle');

            stackerTotalCogsEl.textContent = 'PKR ' + aggregatedCogs.toFixed(2);
            stackerCogsSubEl.textContent = count + ' items aggregated · 3x Floor = PKR ' + floor3x.toFixed(2);

            stackerGrossProfitEl.textContent = 'PKR ' + grossProfit.toFixed(2);
            stackerGrossMarginEl.textContent = grossMargin.toFixed(2) + '%';

            stackerGrossProfitEl.classList.toggle('ec-positive', grossProfit > 0);
            stackerGrossProfitEl.classList.toggle('ec-negative', grossProfit < 0);
            stackerGrossMarginEl.classList.toggle('ec-positive', grossMargin >= 66.6);
            stackerGrossMarginEl.classList.toggle('ec-negative', grossMargin < 50 || grossProfit < 0);

            stackerMarginSubEl.textContent = grossMargin >= 66.6
                ? '✓ Clears 3x BOM COGS benchmark — ready for Meta Ads'
                : (grossProfit > 0 ? '⚠ Below 3x BOM floor (66.7% margin target)' : '⚠ Selling below aggregated BOM COGS');

            const itemBreakdown = selected
                .map((item) => item.name + ' (COGS PKR ' + Number(item.bom_cogs).toFixed(2) + ')')
                .join(' + ');

            const savingsNote = standaloneTotal > 0
                ? (customerSavings > 0
                    ? ' · Customer saves <strong>PKR ' + customerSavings.toFixed(2) + '</strong> vs standalone'
                    : ' · Priced PKR ' + Math.abs(customerSavings).toFixed(2) + ' above standalone retail')
                : '';

            stackerBreakdownText.innerHTML =
                '<strong>' + count + '-Item BOM Stack:</strong> ' + itemBreakdown +
                ' = <strong>PKR ' + aggregatedCogs.toFixed(2) + '</strong>' + savingsNote;
        }

        stackerSelects.forEach(function (selectEl) {
            selectEl.addEventListener('change', function () {
                recalculateStacker(true);
            });
        });

        stackerTierPriceInput.addEventListener('input', function () {
            stackerPriceManuallyEdited = true;
            recalculateStacker(false);
        });

        stackerSnap3xBtn.addEventListener('click', function () {
            const selected = getSelectedStackerItems();
            const aggregatedCogs = selected.reduce((sum, item) => sum + (Number(item.bom_cogs) || 0), 0);
            stackerPriceManuallyEdited = false;
            stackerTierPriceInput.value = (aggregatedCogs * 3).toFixed(2);
            recalculateStacker(false);
        });

        recalculateStacker(false);
    </script>
@endsection
