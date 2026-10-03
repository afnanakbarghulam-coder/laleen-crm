@extends('layouts.app')
@section('title', 'New Sale')

<style>
    .checkout-wrap {
        display: flex;
        justify-content: center;
    }

    .checkout-drawer {
        width: 100%;
        max-width: 640px;
        margin: 0 auto;
        background: #241e1c;
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 14px;
        box-shadow: 0 8px 24px rgba(16, 24, 40, .08);
        overflow: hidden;
    }

    .checkout-header, .checkout-section {
        padding: 8px 20px;
        border-bottom: 1px solid rgba(217, 143, 131,0.07);
    }

    .checkout-section h6 {
        font-size: 12.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #c9a39a;
        margin-bottom: 4px;
    }

    .product-row {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 2px 0;
        font-size: 14px;
    }

    .summary-row.total {
        font-size: 16px;
        font-weight: 700;
        border-top: 1px solid rgba(217, 143, 131,0.16);
        margin-top: 4px;
        padding-top: 6px;
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

    /* ---------------- CLIENT PICKER ---------------- */
    .sale-client-picker {
        position: relative;
    }

    .sale-client-trigger {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 7px 10px;
        background-color: rgba(20, 16, 14, 0.5);
        border: 1px solid rgba(217, 143, 131, 0.3);
        border-radius: 6px;
        cursor: pointer;
        transition: border-color .15s ease;
    }

    .sale-client-trigger:hover {
        border-color: rgba(217, 143, 131, 0.5);
    }

    .sale-client-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(185,142,163,0.14);
        color: #b98ea3;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .sale-client-label {
        font-weight: 700;
        font-size: 13px;
        color: #e79a91;
        line-height: 1.2;
    }

    .sale-client-sub {
        font-size: 11.5px;
        color: #8d7f79;
    }

    .sale-client-panel {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        width: 100%;
        min-width: 280px;
        z-index: 1070;
        background: #1c1715;
        border: 1px solid rgba(217, 143, 131, 0.3);
        border-radius: 10px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, .35);
        padding: 10px;
        max-height: 320px;
        overflow-y: auto;
    }

    .sale-client-search-wrap {
        position: relative;
        margin-bottom: 6px;
    }

    .sale-client-search-wrap i {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #8d7f79;
        font-size: 14px;
    }

    .sale-client-search-wrap input {
        width: 100%;
        background-color: #241e1c;
        border: 1px solid rgba(217, 143, 131, 0.2);
        border-radius: 6px;
        padding: 6px 10px 6px 30px;
        color: #e79a91;
        font-size: 13px;
    }

    .sale-client-search-wrap input:focus {
        outline: none;
        border-color: #d98f83;
    }

    .sale-client-row {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
    }

    .sale-client-row:hover {
        background: rgba(217, 143, 131, 0.08);
    }

    .sale-client-row-avatar {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: rgba(217, 143, 131, 0.14);
        color: #e79a91;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11.5px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .sale-client-row-name {
        font-weight: 600;
        color: #e79a91;
    }

    .sale-client-row-phone {
        font-size: 11px;
        color: #8d7f79;
    }

    .sale-client-footer {
        text-align: right;
        margin-top: 4px;
        padding-top: 6px;
        border-top: 1px solid rgba(217, 143, 131, 0.16);
    }

    .sale-client-footer button {
        background: none;
        border: none;
        color: #c9a39a;
        font-size: 12px;
        font-weight: 600;
        padding: 2px 4px;
    }

    .sale-client-footer button:hover {
        color: #e79a91;
        text-decoration: underline;
    }
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
                    <div class="sale-client-picker" id="saleClientPicker">
                        <div class="sale-client-trigger" id="saleClientTrigger">
                            <div class="sale-client-avatar" id="saleClientAvatar"><i class="bx bx-user-plus"></i></div>
                            <div>
                                <div class="sale-client-label" id="saleClientLabel">Add client</div>
                                <div class="sale-client-sub" id="saleClientSub">Search or add a new client</div>
                            </div>
                        </div>

                        <input type="hidden" name="customer_id" id="saleCustomerId" value="">
                        <input type="hidden" name="customer_phone" id="saleCustomerPhone" value="">
                        <input type="hidden" name="customer_name" id="saleCustomerName" value="">

                        <div class="sale-client-panel d-none" id="saleClientPanel">
                            <div class="sale-client-search-wrap">
                                <i class="bx bx-search"></i>
                                <input type="text" id="saleClientSearch" placeholder="Search by name or phone">
                            </div>

                            <div class="sale-client-row" id="saleAddNewClientRow">
                                <div class="sale-client-row-avatar"><i class="bx bx-plus"></i></div>
                                <div class="sale-client-row-name">Add new client</div>
                            </div>

                            <div id="saleAddClientForm" class="d-none border rounded p-2 my-2">
                                <input type="text" id="saleNewClientName" class="form-control form-control-sm mb-2" placeholder="Client name">
                                <div class="input-group input-group-sm mb-1">
                                    <select id="saleNewClientCountryCode" class="form-select" style="max-width: 100px;">
                                        <option value="+974" selected>🇶🇦 +974</option>
                                        <option value="+971">🇦🇪 +971</option>
                                        <option value="+966">🇸🇦 +966</option>
                                        <option value="+973">🇧🇭 +973</option>
                                        <option value="+965">🇰🇼 +965</option>
                                        <option value="+968">🇴🇲 +968</option>
                                        <option value="+20">🇪🇬 +20</option>
                                        <option value="+91">🇮🇳 +91</option>
                                        <option value="+92">🇵🇰 +92</option>
                                        <option value="+63">🇵🇭 +63</option>
                                        <option value="+44">🇬🇧 +44</option>
                                        <option value="+1">🇺🇸 +1</option>
                                    </select>
                                    <input type="tel" id="saleNewClientPhone" class="form-control" placeholder="Phone number">
                                </div>
                                <small id="saleNewClientPhoneError" class="text-danger d-none mb-2 d-block">Enter a valid phone number.</small>
                                <button type="button" class="btn btn-sm btn-primary w-100" id="saleConfirmNewClientBtn">Add Client</button>
                            </div>

                            <div id="saleClientResults"></div>

                            <div class="sale-client-footer">
                                <button type="button" id="saleClientCloseBtn">Close</button>
                            </div>
                        </div>
                    </div>
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

        /* ---------------- CLIENT PICKER ---------------- */
        let selectedSaleClient = null; // { id, name, phone } or null (id omitted for a brand-new client)
        let saleClientSearchTimer = null;

        function saleUpdateTrigger() {
            const avatar = document.getElementById('saleClientAvatar');
            const label = document.getElementById('saleClientLabel');
            const sub = document.getElementById('saleClientSub');

            document.getElementById('saleCustomerId').value = selectedSaleClient && selectedSaleClient.id ? selectedSaleClient.id : '';
            document.getElementById('saleCustomerName').value = selectedSaleClient ? (selectedSaleClient.name || '') : '';
            document.getElementById('saleCustomerPhone').value = selectedSaleClient ? (selectedSaleClient.phone || '') : '';

            if (!selectedSaleClient) {
                avatar.innerHTML = '<i class="bx bx-user-plus"></i>';
                label.textContent = 'Add client';
                sub.textContent = 'Search or add a new client';
                return;
            }

            avatar.textContent = selectedSaleClient.name ? selectedSaleClient.name.charAt(0).toUpperCase() : '?';
            label.textContent = selectedSaleClient.name || 'Client';
            sub.textContent = selectedSaleClient.phone || '';
        }

        function saleOpenClientPanel() {
            document.getElementById('saleClientPanel').classList.remove('d-none');
            document.getElementById('saleAddClientForm').classList.add('d-none');
            document.getElementById('saleClientSearch').value = '';
            saleRenderClientResults('');
        }

        function saleCloseClientPanel() {
            document.getElementById('saleClientPanel').classList.add('d-none');
        }

        document.getElementById('saleClientTrigger').addEventListener('click', function(e) {
            e.stopPropagation();
            const panel = document.getElementById('saleClientPanel');
            if (panel.classList.contains('d-none')) {
                saleOpenClientPanel();
            } else {
                saleCloseClientPanel();
            }
        });

        document.getElementById('saleClientPanel').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        document.addEventListener('click', function() {
            saleCloseClientPanel();
        });

        document.getElementById('saleClientCloseBtn').addEventListener('click', saleCloseClientPanel);

        function saleRenderClientResults(query) {
            const box = document.getElementById('saleClientResults');
            box.innerHTML = '<div class="text-muted small px-2">Searching…</div>';

            fetch("{{ route('customers.search') }}?q=" + encodeURIComponent(query))
                .then(r => r.json())
                .then(list => {
                    if (!list.length) {
                        box.innerHTML = '<div class="text-muted small px-2">No clients found.</div>';
                        return;
                    }
                    box.innerHTML = list.map(c => `
                        <div class="sale-client-row" data-id="${c.id}" data-name="${c.name.replace(/"/g,'&quot;')}" data-phone="${c.phone}">
                            <div class="sale-client-row-avatar">${c.initials}</div>
                            <div>
                                <div class="sale-client-row-name">${c.name}</div>
                                <div class="sale-client-row-phone">${c.phone}</div>
                            </div>
                        </div>
                    `).join('');

                    box.querySelectorAll('.sale-client-row').forEach(row => {
                        row.addEventListener('click', () => saleSelectExistingClient({
                            id: row.dataset.id, name: row.dataset.name, phone: row.dataset.phone
                        }));
                    });
                });
        }

        document.getElementById('saleClientSearch').addEventListener('input', function() {
            clearTimeout(saleClientSearchTimer);
            const q = this.value;
            saleClientSearchTimer = setTimeout(() => saleRenderClientResults(q), 300);
        });

        function saleSelectExistingClient(client) {
            selectedSaleClient = { id: client.id, name: client.name, phone: client.phone };
            saleUpdateTrigger();
            saleCloseClientPanel();
        }

        document.getElementById('saleAddNewClientRow').addEventListener('click', () => {
            document.getElementById('saleAddClientForm').classList.remove('d-none');
        });

        document.getElementById('saleConfirmNewClientBtn').addEventListener('click', () => {
            const name = document.getElementById('saleNewClientName').value.trim();
            const countryCode = document.getElementById('saleNewClientCountryCode').value;
            const rawPhone = document.getElementById('saleNewClientPhone').value.trim();
            const errorEl = document.getElementById('saleNewClientPhoneError');
            const localDigits = rawPhone.replace(/\D/g, '');
            const fullPhone = countryCode + localDigits;

            errorEl.classList.add('d-none');

            if (!name) {
                errorEl.textContent = "Please enter the client's name.";
                errorEl.classList.remove('d-none');
                return;
            }

            // E.164-style check: + then 7-15 digits total, first digit non-zero.
            if (!/^\+[1-9]\d{6,14}$/.test(fullPhone)) {
                errorEl.textContent = localDigits
                    ? 'Enter a valid phone number for the selected country.'
                    : 'Please enter a phone number.';
                errorEl.classList.remove('d-none');
                return;
            }

            selectedSaleClient = { name, phone: fullPhone };
            saleUpdateTrigger();
            saleCloseClientPanel();
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
