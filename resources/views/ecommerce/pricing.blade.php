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
        <div class="mb-0">
            <label class="form-label">Finished Product</label>
            <select id="pricingProductSelect" class="form-select">
                <option value="" {{ (string) $selectedProductId === '' ? 'selected' : '' }}>Select a product to price</option>
                @foreach ($products as $product)
                    <option value="{{ $product->id }}" {{ (string) $selectedProductId === (string) $product->id ? 'selected' : '' }}>{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }}</option>
                @endforeach
            </select>
            @if ($products->isEmpty())
                <p class="ec-sub mb-0 mt-2">No products yet &mdash; add some in Inventory &amp; Production first.</p>
            @endif
        </div>
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
                <div class="col-md-4">
                    <label class="form-label">Packaging Cost (Bottle, Pump, Label)</label>
                    <input type="number" step="0.01" min="0" name="packaging_cost" id="pricingPackagingCost" class="form-control pricing-input" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fulfillment Cost (Box &amp; Labor)</label>
                    <input type="number" step="0.01" min="0" name="fulfillment_cost" id="pricingFulfillmentCost" class="form-control pricing-input" value="0">
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
                    <div class="ec-sub">Liquid + Packaging + Fulfillment</div>
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

        const productSelect = document.getElementById('pricingProductSelect');
        const productIdInput = document.getElementById('pricingProductId');
        const liquidCostInput = document.getElementById('pricingLiquidCost');
        const packagingCostInput = document.getElementById('pricingPackagingCost');
        const fulfillmentCostInput = document.getElementById('pricingFulfillmentCost');
        const sellingPriceInput = document.getElementById('pricingSellingPrice');
        const totalCostEl = document.getElementById('pricingTotalCost');
        const grossProfitEl = document.getElementById('pricingGrossProfit');
        const marginEl = document.getElementById('pricingMargin');
        const saveBtn = document.getElementById('pricingSaveBtn');

        function recalculate() {
            const liquid = parseFloat(liquidCostInput.value) || 0;
            const packaging = parseFloat(packagingCostInput.value) || 0;
            const fulfillment = parseFloat(fulfillmentCostInput.value) || 0;
            const sellingPrice = parseFloat(sellingPriceInput.value) || 0;

            const totalCost = liquid + packaging + fulfillment;
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

        function applyPricingForSelectedProduct() {
            const productId = productSelect.value;
            productIdInput.value = productId;

            const saved = Object.prototype.hasOwnProperty.call(pricingData, productId) ? pricingData[productId] : null;
            liquidCostInput.value = saved ? Number(saved.liquid_cost) : 0;
            packagingCostInput.value = saved ? Number(saved.packaging_cost) : 0;
            fulfillmentCostInput.value = saved ? Number(saved.fulfillment_cost) : 0;
            sellingPriceInput.value = saved ? Number(saved.selling_price) : 0;

            if (saveBtn) {
                saveBtn.disabled = productId === '';
            }

            recalculate();
        }

        productSelect.addEventListener('change', applyPricingForSelectedProduct);

        // Re-applies on initial load too, so a product pre-selected via
        // ?product=<id> (e.g. redirected back here right after saving)
        // shows its data immediately instead of resetting to blank.
        applyPricingForSelectedProduct();
    </script>
@endsection
