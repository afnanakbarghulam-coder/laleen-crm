@extends('layouts.app')
@section('title', 'New Sale')

<style>
    .checkout-wrap {
        display: flex;
        justify-content: flex-end;
    }

    .checkout-drawer {
        width: 100%;
        max-width: 520px;
        background: #241e1c;
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 14px;
        box-shadow: 0 8px 24px rgba(16, 24, 40, .08);
        overflow: hidden;
    }

    .checkout-header, .checkout-section {
        padding: 18px 22px;
        border-bottom: 1px solid rgba(217, 143, 131,0.07);
    }

    .checkout-section h6 {
        font-size: 12.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #c9a39a;
        margin-bottom: 12px;
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
        font-size: 18px;
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
</style>

@section('content')
    <div class="checkout-wrap">
        <div class="checkout-drawer">
            <div class="checkout-header">
                <h5 class="mb-0">New Sale</h5>
                <div class="text-muted small">Retail-only walk-in sale</div>
            </div>

            <form method="POST" action="{{ route('sales.store') }}" id="newSaleForm">
                @csrf

                <div class="checkout-section">
                    <h6>Client (optional)</h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="text" name="customer_phone" id="salePhone" class="form-control form-control-sm" placeholder="Phone">
                        </div>
                        <div class="col-6">
                            <input type="text" name="customer_name" id="saleCustomerName" class="form-control form-control-sm" placeholder="Name">
                        </div>
                    </div>
                    <small id="saleCustomerInfo" class="d-none d-block mt-1 text-success"></small>
                </div>

                <div class="checkout-section">
                    <h6>Branch</h6>
                    <select name="branch" class="form-select form-select-sm" required>
                        <option value="">-- Select Branch --</option>
                        <option value="old_airport">Old Airport</option>
                        <option value="wakrah">Wakrah</option>
                        <option value="home_service">Home Service</option>
                    </select>
                </div>

                <div class="checkout-section">
                    <h6>Products</h6>
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
                </div>

                <div class="checkout-section">
                    <h6>Discount &amp; Tip</h6>
                    <div class="row g-2 align-items-end">
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
                </div>

                <div class="checkout-section">
                    <div class="summary-row"><span>Products</span><span id="sumProducts">0.00</span></div>
                    <div class="summary-row"><span>Discount</span><span id="sumDiscount">−0.00</span></div>
                    <div class="summary-row"><span>Tip</span><span id="sumTip">+0.00</span></div>
                    <div class="summary-row total"><span>Total Due</span><span id="sumTotal">0.00 QAR</span></div>
                </div>

                <div class="checkout-section">
                    <h6>Payment</h6>
                    <div class="row g-2">
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
                    <div class="mt-2 d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-link btn-sm p-0" id="fillCashBtn">Full amount to cash</button>
                        <span class="remaining-pill" id="remainingPill">—</span>
                    </div>
                </div>

                <div class="checkout-section">
                    <button type="submit" class="btn btn-primary w-100" id="completeSaleBtn">Complete Sale</button>
                    <a href="{{ route('appointments.calendar') }}" class="btn btn-link w-100 mt-1 text-center">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        let productRows = [];
        let rowSeq = 0;
        let lookupTimer = null;

        function lookupCustomer(phone) {
            const info = document.getElementById('saleCustomerInfo');
            if (!phone || phone.replace(/\D/g, '').length < 4) {
                info.classList.add('d-none');
                return;
            }

            fetch("{{ route('customers.lookup') }}?phone=" + encodeURIComponent(phone))
                .then(res => res.json())
                .then(data => {
                    if (!data.found) {
                        info.classList.add('d-none');
                        return;
                    }
                    const nameField = document.getElementById('saleCustomerName');
                    if (!nameField.value && data.name) nameField.value = data.name;

                    info.textContent = `Returning client · ${data.visit_count} visit${data.visit_count === 1 ? '' : 's'}`;
                    info.classList.remove('d-none');
                });
        }

        document.getElementById('salePhone').addEventListener('input', function() {
            clearTimeout(lookupTimer);
            const phone = this.value;
            lookupTimer = setTimeout(() => lookupCustomer(phone), 400);
        });

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

            const discountType = document.getElementById('discountType').value;
            const discountValue = parseFloat(document.getElementById('discountValue').value || 0);
            let discountAmount = 0;
            if (discountType === 'percent') {
                discountAmount = productsTotal * Math.min(discountValue, 100) / 100;
            } else if (discountType === 'flat') {
                discountAmount = Math.min(discountValue, productsTotal);
            }

            const tip = parseFloat(document.getElementById('tipAmount').value || 0);
            const total = Math.max(0, productsTotal - discountAmount) + tip;

            document.getElementById('sumProducts').textContent = productsTotal.toFixed(2);
            document.getElementById('sumDiscount').textContent = '−' + discountAmount.toFixed(2);
            document.getElementById('sumTip').textContent = '+' + tip.toFixed(2);
            document.getElementById('sumTotal').textContent = total.toFixed(2) + ' QAR';

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
