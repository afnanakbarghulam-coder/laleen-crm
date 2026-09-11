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

    .line-item .item-name {
        display: flex;
        flex-direction: column;
    }

    .line-item .discount-note {
        font-size: 11px;
        font-weight: 700;
        color: #8ea88a;
    }

    .line-item .price-col {
        text-align: right;
    }

    .line-item .original-price {
        display: block;
        font-size: 11.5px;
        color: #8a7d76;
        text-decoration: line-through;
    }

    .service-discount-banner {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(142,168,138,0.14);
        border: 1px solid rgba(142,168,138,0.3);
        color: #8ea88a;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 12.5px;
        font-weight: 700;
        margin-top: 10px;
    }

    .redeem-row {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 6px 0;
        font-size: 13px;
    }

    .redeem-row .redeem-meta {
        font-size: 11.5px;
        color: #c9a39a;
    }

    .redeem-row .redeem-expiry {
        font-size: 11px;
        font-weight: 700;
    }

    .redeem-expiry.soon { color: #a8524a; }
    .redeem-expiry.ok { color: #8ea88a; }

    .package-card {
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 10px;
        background: rgba(217, 143, 131,0.04);
    }

    .package-card-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
    }

    .package-card-head .name {
        font-weight: 700;
        font-size: 13.5px;
        color: #e79a91;
    }

    .package-pick-count {
        font-size: 11.5px;
        color: #c9a39a;
        margin-bottom: 6px;
    }

    .package-pick-count.full {
        color: #8ea88a;
        font-weight: 700;
    }

    .package-svc-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 4px 0;
        font-size: 12.5px;
        border-bottom: 1px solid rgba(217, 143, 131,0.07);
    }

    .package-svc-row:last-child {
        border-bottom: none;
    }

    .package-svc-row .immediate-toggle {
        font-size: 12.5px;
        color: #e6d9d5;
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
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
                            <span class="item-name">
                                {{ $item['name'] }} <span class="muted">({{ $item['duration'] }} min)</span>
                                @if (($item['discount_amount'] ?? 0) > 0)
                                    <span class="discount-note">
                                        <i class="bx bx-purchase-tag"></i> −{{ number_format($item['discount_amount'], 2) }} QAR off
                                        @if (!empty($item['discount_reason']))
                                            · {{ $item['discount_reason'] }}
                                        @endif
                                    </span>
                                @endif
                            </span>
                            <span class="price-col">
                                @if (($item['discount_amount'] ?? 0) > 0)
                                    <span class="original-price">{{ number_format($item['original_price'], 2) }} QAR</span>
                                @endif
                                {{ number_format($item['price'], 2) }} QAR
                            </span>
                        </div>
                    @endforeach

                    @if ($serviceDiscountTotal > 0)
                        <div class="service-discount-banner">
                            <span><i class="bx bx-purchase-tag"></i> Service discounts applied</span>
                            <span>−{{ number_format($serviceDiscountTotal, 2) }} QAR</span>
                        </div>
                    @endif

                    @if (count($upsellItems))
                        <h6 class="mt-4">Upsells</h6>
                        @foreach ($upsellItems as $item)
                            <div class="line-item">
                                <span>{{ $item['name'] }} <span class="muted">({{ $item['staff_name'] }})</span></span>
                                <span>{{ number_format($item['amount'], 2) }} QAR</span>
                            </div>
                        @endforeach
                    @endif

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

                    @if (count($pendingPackageServices))
                        <h6 class="mt-4">Redeem Package Services <span class="text-muted">(no extra charge)</span></h6>
                        @foreach ($pendingPackageServices as $svc)
                            <div class="redeem-row">
                                <input type="checkbox" class="form-check-input redeem-checkbox" name="redeem_service_ids[]" value="{{ $svc['id'] }}" id="redeem-{{ $svc['id'] }}" data-duration="{{ $svc['duration'] }}">
                                <label for="redeem-{{ $svc['id'] }}" class="flex-grow-1 mb-0">
                                    {{ $svc['service_name'] }} <span class="muted">({{ $svc['duration'] }} min)</span>
                                    <div class="redeem-meta">
                                        from {{ $svc['combo_name'] }} ·
                                        <span class="redeem-expiry {{ $svc['days_left'] <= 2 ? 'soon' : 'ok' }}">
                                            {{ $svc['days_left'] <= 0 ? 'expires today' : 'expires ' . $svc['expires_at'] . ' (' . $svc['days_left'] . 'd left)' }}
                                        </span>
                                    </div>
                                </label>
                            </div>
                        @endforeach
                    @endif

                    <h6 class="mt-4">Combo Packages</h6>
                    @foreach ($unpaidPackages as $pkg)
                        <div class="line-item">
                            <span class="item-name">{{ $pkg->combo_name }} <span class="muted">(sold at booking, awaiting payment)</span></span>
                            <span class="price-col">{{ number_format($pkg->price_paid, 2) }} QAR</span>
                        </div>
                    @endforeach
                    <div id="packageRows"></div>
                    @if (count($combos))
                        <div class="d-flex gap-2 mt-2">
                            <select id="comboPicker" class="form-select form-select-sm">
                                <option value="">+ Sell a combo package…</option>
                                @foreach ($combos as $combo)
                                    <option value="{{ $combo['id'] }}">{{ $combo['name'] }} ({{ number_format($combo['price'], 2) }} QAR)</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addPackageBtn">Add</button>
                        </div>
                    @else
                        <div class="text-muted small">No active combo packages in the catalog.</div>
                    @endif

                    <h6 class="mt-4">Summary</h6>
                    <div class="summary-row"><span>Estimated Duration</span><span id="sumDuration">0 min</span></div>
                    <div class="summary-row"><span>Services</span><span id="sumServices">0.00</span></div>
                    @if ($serviceDiscountTotal > 0)
                        <div class="summary-row" style="color:#8ea88a;"><span>Service discounts (already applied)</span><span>−{{ number_format($serviceDiscountTotal, 2) }}</span></div>
                    @endif
                    @if (count($upsellItems))
                        <div class="summary-row"><span>Upsells</span><span id="sumUpsells">{{ number_format($upsellsTotal, 2) }}</span></div>
                    @endif
                    <div class="summary-row"><span>Products</span><span id="sumProducts">0.00</span></div>
                    <div class="summary-row"><span>Packages</span><span id="sumPackages">0.00</span></div>
                    <div class="summary-row"><span>Checkout Discount</span><span id="sumDiscount">−0.00</span></div>
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
        const servicesDuration = {{ $servicesDuration }};
        const upsellsTotal = {{ $upsellsTotal }};
        // A combo already sold at booking time still needs to be paid for -
        // it's not something this page's own "sell a package" picker knows
        // about, so it's added to the packages total as a fixed base amount.
        const existingPackagesTotal = {{ $unpaidPackagesTotal }};
        const loyaltyRate = {{ \App\Models\Customer::POINTS_PER_QAR }};
        const ALL_COMBOS = @json($combos);
        let productRows = [];
        let rowSeq = 0;
        let packageRows = [];
        let pkgRowSeq = 0;

        /* ---------------- COMBO PACKAGES ----------------
           No "select N of M to include" step - staff just tick whichever
           pool services are being done today (up to quantity_included).
           Whatever's left over is banked as a generic pending entitlement,
           chosen later at redemption time rather than locked in now. */
        function packageCardHtml(row) {
            const combo = row.combo;
            const todayCount = row.immediateIds.length;
            const capReached = todayCount >= combo.quantity_included;
            const remaining = combo.quantity_included - todayCount;

            const rowsHtml = combo.services.map(s => {
                const immediateChecked = row.immediateIds.includes(s.id);
                const disableCheckbox = !immediateChecked && capReached;
                return `
                    <div class="package-svc-row">
                        <label class="immediate-toggle">
                            <input type="checkbox" class="form-check-input pkg-immediate-cb" data-row="${row.id}" data-svc="${s.id}"
                                ${immediateChecked ? 'checked' : ''} ${disableCheckbox ? 'disabled' : ''}>
                            ${s.name} <span class="text-muted">(${s.duration} min)</span>
                        </label>
                    </div>`;
            }).join('');

            const hiddenInputs = `
                <input type="hidden" name="packages[${row.id}][combo_id]" value="${combo.id}">
                ${row.immediateIds.map(id => `<input type="hidden" name="packages[${row.id}][immediate_service_ids][]" value="${id}">`).join('')}
            `;

            const todayDuration = packageImmediateDuration(row);
            const pendingNote = remaining > 0
                ? ` · ${remaining} service${remaining === 1 ? '' : 's'} will be saved as pending`
                : '';

            return `
                <div class="package-card" data-row-id="${row.id}">
                    <div class="package-card-head">
                        <span class="name">${combo.name} <span class="text-muted">(${combo.price.toFixed(2)} QAR)</span></span>
                        <button type="button" class="btn btn-sm btn-outline-danger pkg-remove-btn" data-row="${row.id}"><i class="bx bx-x"></i></button>
                    </div>
                    <div class="package-pick-count ${remaining === 0 ? 'full' : ''}">${todayCount} / ${combo.quantity_included} chosen for today${pendingNote}</div>
                    <div class="package-pick-count">Today's duration: <strong>${todayDuration} min</strong>${todayCount ? '' : ` (nothing marked "Do today" yet - all ${combo.quantity_included} will be saved as pending)`}</div>
                    ${rowsHtml}
                    ${hiddenInputs}
                </div>`;
        }

        function packageImmediateDuration(row) {
            return row.combo.services
                .filter(s => row.immediateIds.includes(s.id))
                .reduce((sum, s) => sum + s.duration, 0);
        }

        function renderPackageRows() {
            const container = document.getElementById('packageRows');
            container.innerHTML = packageRows.map(packageCardHtml).join('');

            container.querySelectorAll('.pkg-remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    packageRows = packageRows.filter(r => r.id != btn.dataset.row);
                    renderPackageRows();
                    recalc();
                });
            });

            container.querySelectorAll('.pkg-immediate-cb').forEach(cb => {
                cb.addEventListener('change', () => {
                    const row = packageRows.find(r => r.id == cb.dataset.row);
                    const svcId = Number(cb.dataset.svc);
                    if (cb.checked) {
                        if (!row.immediateIds.includes(svcId)) row.immediateIds.push(svcId);
                    } else {
                        row.immediateIds = row.immediateIds.filter(id => id !== svcId);
                    }
                    renderPackageRows();
                });
            });

            recalc();
        }

        document.getElementById('addPackageBtn')?.addEventListener('click', () => {
            const picker = document.getElementById('comboPicker');
            if (!picker.value) return;
            const combo = ALL_COMBOS.find(c => c.id == picker.value);
            if (!combo) return;

            packageRows.push({ id: ++pkgRowSeq, combo, immediateIds: [] });
            picker.value = '';
            renderPackageRows();
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
            const packagesTotal = existingPackagesTotal + packageRows.reduce((s, r) => s + r.combo.price, 0);
            const subtotal = servicesTotal + upsellsTotal + productsTotal + packagesTotal;

            // Total time this visit actually takes: what's already booked,
            // plus only the package services being performed today (pending
            // ones don't occupy any of today's calendar slot) and any
            // previously-purchased pending service just checked off to redeem.
            const packageImmediateMinutes = packageRows.reduce((s, r) => s + packageImmediateDuration(r), 0);
            const redeemMinutes = Array.from(document.querySelectorAll('.redeem-checkbox:checked'))
                .reduce((s, cb) => s + (parseInt(cb.dataset.duration, 10) || 0), 0);
            const totalDuration = servicesDuration + packageImmediateMinutes + redeemMinutes;
            document.getElementById('sumDuration').textContent = totalDuration + ' min';

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
            document.getElementById('sumPackages').textContent = packagesTotal.toFixed(2);
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

        document.querySelectorAll('.redeem-checkbox').forEach(cb => {
            cb.addEventListener('change', recalc);
        });

        recalc();
    </script>
@endsection
