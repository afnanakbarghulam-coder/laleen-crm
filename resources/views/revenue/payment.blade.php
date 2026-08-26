@extends('layouts.app')
@section('title', 'Checkout')

<style>
    .checkout-shell {
        max-width: 900px;
        margin: 0 auto;
        background: #241e1c;
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 14px;
        box-shadow: 0 8px 24px rgba(16, 24, 40, .08);
        overflow: hidden;
    }

    .checkout-header {
        padding: 18px 24px;
        border-bottom: 1px solid rgba(217, 143, 131,0.16);
    }

    .checkout-split {
        display: flex;
        flex-wrap: wrap;
    }

    .checkout-col-left {
        flex: 1 1 380px;
        padding: 20px 24px;
        border-right: 1px solid rgba(217, 143, 131,0.07);
    }

    .checkout-col-right {
        flex: 1 1 320px;
        padding: 20px 24px;
        background: #241e1c;
    }

    .checkout-col-left h6, .checkout-col-right h6 {
        font-size: 12.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #c9a39a;
        margin-bottom: 12px;
    }

    .line-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        font-size: 14px;
    }

    .line-item .muted {
        color: #c9a39a;
        font-size: 12.5px;
    }

    .product-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 14px;
    }

    .summary-row.total {
        font-size: 19px;
        font-weight: 700;
        border-top: 1px solid rgba(217, 143, 131,0.16);
        margin-top: 8px;
        padding-top: 10px;
    }

    .remaining-pill {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 700;
    }

    .remaining-ok { background: rgba(142,168,138,0.14); color: #8ea88a; }
    .remaining-due { background: rgba(168,82,74,0.14); color: #a8524a; }

    .loyalty-note {
        background: rgba(201,166,107,0.14);
        border: 1px solid rgba(201,166,107,0.3);
        color: #c97b4a;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 12.5px;
        margin-top: 10px;
    }
</style>

@section('content')
    <div class="checkout-shell">
        <div class="checkout-header d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Checkout</h5>
                <div class="text-muted small">
                    {{ $appointment->customer_name }} · {{ $appointment->phone }}<br>
                    {{ $appointment->appointment_datetime->format('D, d M Y · h:i A') }}
                    @if ($appointment->staff)
                        · with {{ $appointment->staff->name }}
                    @endif
                </div>
            </div>
            <a href="{{ route('appointments.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        </div>

        <form method="POST" action="{{ route('appointments.revenue.storePayment', $appointment->id) }}" id="checkoutForm">
            @csrf

            <div class="checkout-split">
                <!-- LEFT: services + products review -->
                <div class="checkout-col-left">
                    <h6>Services</h6>
                    @foreach ($serviceItems as $item)
                        <div class="line-item">
                            <span>{{ $item['name'] }} <span class="muted">({{ $item['duration'] }} min)</span></span>
                            <span>{{ number_format($item['price'], 2) }} QAR</span>
                        </div>
                    @endforeach

                    <h6 class="mt-4">Retail Products</h6>
                    <div id="productRows"></div>

                    <div class="d-flex gap-2 mt-2">
                        <select id="productPicker" class="form-select form-select-sm">
                            <option value="">+ Add a product…</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->price }}">
                                    {{ $product->name }} ({{ number_format($product->price, 2) }} QAR)
                                </option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addProductBtn">Add</button>
                    </div>

                    <h6 class="mt-4">Summary</h6>
                    <div class="summary-row"><span>Services</span><span id="sumServices">0.00</span></div>
                    <div class="summary-row"><span>Products</span><span id="sumProducts">0.00</span></div>
                    <div class="summary-row"><span>Discount</span><span id="sumDiscount">−0.00</span></div>
                    <div class="summary-row"><span>Tip</span><span id="sumTip">+0.00</span></div>
                    <div class="summary-row total"><span>Total Due</span><span id="sumTotal">0.00 QAR</span></div>

                    @if ($appointment->customer_id)
                        <div class="loyalty-note">
                            <i class="bx bx-gift"></i> This client will earn <strong id="loyaltyPreview">0</strong> loyalty points on this checkout.
                        </div>
                    @endif
                </div>

                <!-- RIGHT: payment methods, discount, tip -->
                <div class="checkout-col-right">
                    <h6>Discount &amp; Tip</h6>
                    <div class="row g-2 align-items-end mb-4">
                        <div class="col-5">
                            <label class="form-label small mb-1">Discount Type</label>
                            <select name="discount_type" id="discountType" class="form-select form-select-sm">
                                <option value="">None</option>
                                <option value="flat">Flat (QAR)</option>
                                <option value="percent">Percent (%)</option>
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label small mb-1">Value</label>
                            <input type="number" name="discount_value" id="discountValue" class="form-control form-control-sm" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1">Tip (QAR)</label>
                            <input type="number" name="tip_amount" id="tipAmount" class="form-control form-control-sm" min="0" step="0.01" value="0">
                        </div>
                    </div>

                    <h6>Payment Method</h6>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small mb-1"><i class="bx bx-money"></i> Cash</label>
                            <input type="number" name="payments[cash]" id="payCash" class="form-control form-control-sm pay-input" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1"><i class="bx bx-credit-card"></i> Card</label>
                            <input type="number" name="payments[card]" id="payCard" class="form-control form-control-sm pay-input" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-1"><i class="bx bx-transfer"></i> Online</label>
                            <input type="number" name="payments[online_transfer]" id="payOnline" class="form-control form-control-sm pay-input" min="0" step="0.01" value="0">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <button type="button" class="btn btn-link btn-sm p-0" id="fillCashBtn">Full amount to cash</button>
                        <span class="remaining-pill" id="remainingPill">—</span>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="completeSaleBtn">Complete Sale</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        const servicesTotal = {{ $servicesTotal }};
        const loyaltyRate = {{ \App\Models\Customer::POINTS_PER_QAR }};
        let productRows = [];
        let rowSeq = 0;

        function renderProductRows() {
            const container = document.getElementById('productRows');
            container.innerHTML = '';

            productRows.forEach(row => {
                const div = document.createElement('div');
                div.className = 'product-row';
                div.innerHTML = `
                    <span class="flex-grow-1">${row.name} × ${row.qty}</span>
                    <span>${(row.price * row.qty).toFixed(2)} QAR</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove="${row.id}"><i class="bx bx-x"></i></button>
                    <input type="hidden" name="products[${row.id}][product_id]" value="${row.productId}">
                    <input type="hidden" name="products[${row.id}][quantity]" value="${row.qty}">
                `;
                container.appendChild(div);
            });

            container.querySelectorAll('[data-remove]').forEach(btn => {
                btn.addEventListener('click', () => {
                    productRows = productRows.filter(r => r.id != btn.dataset.remove);
                    renderProductRows();
                    recalc();
                });
            });

            recalc();
        }

        document.getElementById('addProductBtn').addEventListener('click', () => {
            const picker = document.getElementById('productPicker');
            const opt = picker.options[picker.selectedIndex];
            if (!opt.value) return;

            productRows.push({
                id: ++rowSeq,
                productId: opt.value,
                name: opt.dataset.name,
                price: parseFloat(opt.dataset.price),
                qty: 1
            });
            picker.value = '';
            renderProductRows();
        });

        function recalc() {
            const productsTotal = productRows.reduce((s, r) => s + r.price * r.qty, 0);
            const subtotal = servicesTotal + productsTotal;

            const discountType = document.getElementById('discountType').value;
            const discountValue = parseFloat(document.getElementById('discountValue').value || 0);
            let discountAmount = 0;
            if (discountType === 'percent') {
                discountAmount = subtotal * Math.min(discountValue, 100) / 100;
            } else if (discountType === 'flat') {
                discountAmount = Math.min(discountValue, subtotal);
            }

            const tip = parseFloat(document.getElementById('tipAmount').value || 0);
            const total = Math.max(0, subtotal - discountAmount) + tip;

            document.getElementById('sumServices').textContent = servicesTotal.toFixed(2);
            document.getElementById('sumProducts').textContent = productsTotal.toFixed(2);
            document.getElementById('sumDiscount').textContent = '−' + discountAmount.toFixed(2);
            document.getElementById('sumTip').textContent = '+' + tip.toFixed(2);
            document.getElementById('sumTotal').textContent = total.toFixed(2) + ' QAR';

            const loyaltyPreview = document.getElementById('loyaltyPreview');
            if (loyaltyPreview) loyaltyPreview.textContent = Math.floor(total * loyaltyRate);

            window.checkoutTotal = total;
            updateRemaining();
        }

        function updateRemaining() {
            const cash = parseFloat(document.getElementById('payCash').value || 0);
            const card = parseFloat(document.getElementById('payCard').value || 0);
            const online = parseFloat(document.getElementById('payOnline').value || 0);
            const paid = cash + card + online;
            const remaining = (window.checkoutTotal || 0) - paid;

            const pill = document.getElementById('remainingPill');
            if (Math.abs(remaining) < 0.01) {
                pill.textContent = 'Fully paid';
                pill.className = 'remaining-pill remaining-ok';
            } else if (remaining > 0) {
                pill.textContent = remaining.toFixed(2) + ' QAR remaining';
                pill.className = 'remaining-pill remaining-due';
            } else {
                pill.textContent = Math.abs(remaining).toFixed(2) + ' QAR change due';
                pill.className = 'remaining-pill remaining-due';
            }
        }

        document.getElementById('fillCashBtn').addEventListener('click', () => {
            document.getElementById('payCash').value = (window.checkoutTotal || 0).toFixed(2);
            document.getElementById('payCard').value = 0;
            document.getElementById('payOnline').value = 0;
            updateRemaining();
        });

        ['discountType', 'discountValue', 'tipAmount'].forEach(id => {
            document.getElementById(id).addEventListener('input', recalc);
            document.getElementById(id).addEventListener('change', recalc);
        });

        document.querySelectorAll('.pay-input').forEach(el => {
            el.addEventListener('input', updateRemaining);
        });

        recalc();
    </script>
@endsection
