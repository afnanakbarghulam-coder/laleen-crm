<!-- Fresha-style Booking Drawer -->
<style>
    #calendarBookModal {
        --bs-offcanvas-width: 620px;
    }

    #calendarBookModal .offcanvas-body {
        padding: 0;
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
    }

    .fb-body-row {
        flex: 1 1 auto;
        display: flex;
        overflow: hidden;
        min-height: 0;
    }

    .fb-rail {
        width: 108px;
        flex-shrink: 0;
        border-right: 1px solid rgba(217, 143, 131,0.16);
        padding: 22px 10px;
        text-align: center;
        cursor: pointer;
    }

    .fb-rail:hover {
        background: #241e1c;
    }

    .fb-rail .client-icon {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: rgba(185,142,163,0.14);
        color: #b98ea3;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin: 0 auto 10px;
        overflow: hidden;
    }

    .fb-rail .client-icon img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .fb-rail .client-label {
        font-weight: 700;
        font-size: 12.5px;
        color: #e79a91;
        line-height: 1.2;
    }

    .fb-rail .client-sub {
        font-size: 10.5px;
        color: #c9a39a;
        line-height: 1.2;
        margin-top: 2px;
    }

    .fb-content-col {
        flex: 1 1 auto;
        overflow-y: auto;
        position: relative;
    }

    .fb-content {
        padding: 22px 24px;
    }

    .fb-panel-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
    }

    .fb-panel-header h5 {
        margin: 0;
        font-weight: 800;
    }

    .fb-back-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid rgba(217, 143, 131,0.16);
        background: #241e1c;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #cbb8b0;
    }

    .fb-back-btn:hover {
        background: rgba(217, 143, 131,0.06);
    }

    .fb-date-label {
        font-size: 21px;
        font-weight: 800;
        color: #e79a91;
        cursor: pointer;
    }

    .fb-date-sub {
        font-size: 12.5px;
        color: #c9a39a;
    }

    .fb-section-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #c9a39a;
        margin: 22px 0 10px;
    }

    .fb-svc-row {
        border-left: 3px solid #d98f83;
        background: rgba(217, 143, 131,0.1);
        border-radius: 6px;
        padding: 8px 12px;
        margin-bottom: 8px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .fb-svc-row-main {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }

    .fb-svc-row .name {
        font-weight: 700;
        font-size: 13.5px;
        color: #e79a91;
    }

    .fb-svc-row .meta {
        font-size: 11.5px;
        color: #c9a39a;
    }

    .fb-svc-row .price {
        font-weight: 700;
        font-size: 13.5px;
        white-space: nowrap;
    }

    .fb-price-edit {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .fb-price-edit .currency {
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        color: #c9a39a;
    }

    .fb-price-edit .price-input {
        width: 62px;
        background: transparent;
        border: none;
        border-bottom: 1px solid rgba(217, 143, 131, 0.4);
        color: #e79a91;
        font-weight: 700;
        font-size: 13.5px;
        text-align: right;
        padding: 1px 2px;
    }

    .fb-price-edit .price-input:focus {
        outline: none;
        border-bottom-color: #d98f83;
    }

    .fb-price-edit .price-input::-webkit-outer-spin-button,
    .fb-price-edit .price-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .fb-price-edit .price-input[type=number] {
        -moz-appearance: textfield;
    }

    .fb-discount-row {
        display: flex;
        align-items: center;
        gap: 8px;
        padding-top: 6px;
        border-top: 1px dashed rgba(217, 143, 131, 0.25);
    }

    .fb-discount-badge {
        flex-shrink: 0;
        font-size: 10.5px;
        font-weight: 700;
        color: #8ea88a;
        background: rgba(142, 168, 138, 0.14);
        padding: 2px 8px;
        border-radius: 999px;
        white-space: nowrap;
    }

    .fb-discount-reason {
        flex: 1;
        min-width: 0;
        background: transparent;
        border: none;
        border-bottom: 1px solid rgba(217, 143, 131, 0.3);
        color: #e6d9d5;
        font-size: 12px;
        padding: 1px 4px;
    }

    .fb-discount-reason:focus {
        outline: none;
        border-bottom-color: #d98f83;
    }

    .fb-discount-reason::placeholder {
        color: #8a7d76;
    }

    .fb-footer .total-discount {
        font-size: 11.5px;
        font-weight: 700;
        color: #8ea88a;
    }

    .fb-svc-row .remove-btn {
        border: none;
        background: transparent;
        color: #c9a39a;
        font-size: 16px;
        line-height: 1;
        padding: 0 2px;
    }

    .fb-svc-row .remove-btn:hover {
        color: #a8524a;
    }

    .fb-add-pill {
        border: 1px solid #8a7d76;
        border-radius: 999px;
        padding: 7px 18px;
        font-size: 13px;
        font-weight: 600;
        background: #241e1c;
        color: #e79a91;
    }

    .fb-add-pill:hover {
        background: rgba(217, 143, 131,0.06);
    }

    .fb-decide-pill {
        border: 1px solid #8a7d76;
        border-radius: 999px;
        padding: 7px 18px;
        font-size: 13px;
        font-weight: 600;
        background: transparent;
        color: #c9a39a;
    }

    .fb-decide-pill:hover {
        background: rgba(201, 163, 154, 0.08);
        color: #e79a91;
    }

    .fb-decide-tag {
        flex-shrink: 0;
        font-size: 10.5px;
        font-weight: 700;
        color: #c9a39a;
        background: rgba(201, 163, 154, 0.14);
        padding: 2px 8px;
        border-radius: 999px;
        white-space: nowrap;
    }

    .fb-search-wrap {
        position: relative;
        margin-bottom: 16px;
    }

    .fb-search-wrap i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #c9a39a;
    }

    .fb-search-wrap input {
        padding-left: 34px;
        border-radius: 10px;
    }

    .fb-client-row, .fb-service-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 8px;
        border-radius: 8px;
        cursor: pointer;
    }

    .fb-client-row:hover, .fb-service-row:hover {
        background: rgba(217, 143, 131,0.06);
    }

    .fb-client-row.add-new {
        background: rgba(185,142,163,0.08);
    }

    .fb-client-row.add-new:hover {
        background: rgba(185,142,163,0.1);
    }

    .fb-client-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: rgba(217, 143, 131,0.06);
        color: #cbb8b0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        flex-shrink: 0;
    }

    .fb-client-row.add-new .fb-client-avatar {
        background: rgba(185,142,163,0.14);
        color: #b98ea3;
    }

    .fb-client-name {
        font-weight: 700;
        font-size: 13.5px;
        color: #e79a91;
    }

    .fb-client-phone {
        font-size: 12px;
        color: #c9a39a;
    }

    .fb-country-select {
        flex: 0 0 108px;
        max-width: 108px;
        font-size: 12.5px;
    }

    .fb-phone-group:focus-within .form-select,
    .fb-phone-group:focus-within .form-control {
        border-color: var(--luxe-accent, #e79a91) !important;
        box-shadow: 0 0 0 3px var(--luxe-accent-soft, rgba(217, 143, 131, 0.25)) !important;
    }

    .fb-service-row .svc-info .name {
        font-weight: 700;
        font-size: 13.5px;
        color: #e79a91;
    }

    .fb-service-row .svc-info .meta {
        font-size: 11.5px;
        color: #c9a39a;
    }

    .fb-service-row .svc-price {
        font-weight: 700;
        font-size: 13.5px;
        margin-left: auto;
    }

    .fb-cat-label {
        font-size: 12px;
        font-weight: 700;
        color: #c9a39a;
        margin-bottom: 8px;
    }

    .fb-footer {
        flex-shrink: 0;
        border-top: 1px solid rgba(217, 143, 131,0.16);
        padding: 14px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #241e1c;
    }

    .fb-footer .total-label {
        font-weight: 700;
        font-size: 13.5px;
        color: #e79a91;
    }

    .fb-footer .total-meta {
        font-size: 12px;
        color: #c9a39a;
    }

    .fb-footer .total-price {
        font-weight: 800;
        font-size: 16px;
        color: #e79a91;
    }

    #calendarBookModal .btn-close {
        position: absolute;
        top: 16px;
        right: 16px;
        z-index: 5;
    }

    .fb-combo-pill {
        border: 1px solid #8a7d76;
        border-radius: 999px;
        padding: 7px 18px;
        font-size: 13px;
        font-weight: 600;
        background: transparent;
        color: #b98ea3;
    }

    .fb-combo-pill:hover {
        background: rgba(185, 142, 163, 0.08);
    }

    .fb-combo-card {
        gap: 8px;
    }

    .fb-combo-today-duration {
        font-size: 11px;
        color: #c9a39a;
    }

    .fb-combo-svc-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 4px 0;
        font-size: 12.5px;
        border-top: 1px dashed rgba(217, 143, 131, 0.15);
    }

    .fb-combo-immediate-toggle {
        font-size: 12.5px;
        color: #e6d9d5;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 0;
        cursor: pointer;
    }

    .fb-combo-staff-select {
        margin: 2px 0 6px;
        font-size: 12px;
        max-width: 220px;
        margin-left: auto;
    }

    .fb-redeem-pill {
        border: 1px solid #8a7d76;
        border-radius: 999px;
        padding: 7px 18px;
        font-size: 13px;
        font-weight: 600;
        background: transparent;
        color: #8ea88a;
    }

    .fb-redeem-pill:hover {
        background: rgba(142, 168, 138, 0.08);
    }

    .fb-redeem-pill .badge {
        background: #8ea88a;
        color: #171310;
        font-weight: 700;
    }

    .fb-redeem-row-meta {
        font-size: 11px;
        color: #c9a39a;
    }

    .fb-redeem-expiry {
        font-weight: 700;
    }

    .fb-redeem-expiry.soon { color: #a8524a; }
    .fb-redeem-expiry.ok { color: #8ea88a; }

    .fb-redeem-pick-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 8px;
        border-radius: 8px;
    }

    .fb-redeem-pick-row:hover {
        background: rgba(217, 143, 131,0.06);
    }
</style>

<div class="offcanvas offcanvas-end" tabindex="-1" id="calendarBookModal" aria-labelledby="calendarBookModalLabel">
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>

    <form action="{{ route('appointments.store') }}" method="POST" id="calendarBookForm" class="offcanvas-body">
        @csrf
        <input type="hidden" name="then" id="bookThen" value="">
        <input type="hidden" name="appointment_datetime" id="bookDatetimeHidden">
        <input type="hidden" name="customer_name" id="bookCustomerNameHidden">
        <input type="hidden" name="phone" id="bookPhoneHidden">
        <input type="hidden" name="price" id="bookPriceHidden" value="0">
        <div id="svcHiddenInputs"></div>

        <div class="fb-body-row">
            <!-- RAIL -->
            <div class="fb-rail" id="fbRail">
                <div class="client-icon" id="fbRailIcon"><i class="bx bx-user-plus"></i></div>
                <div class="client-label" id="fbRailLabel">Add client</div>
                <div class="client-sub" id="fbRailSub">Search or add a new client</div>
            </div>

            <!-- CONTENT -->
            <div class="fb-content-col">
                <!-- PANEL: MAIN -->
                <div id="fbPanelMain" class="fb-content">
                    <div class="fb-date-label" id="fbDateLabel">&nbsp;</div>
                    <div class="fb-date-sub" id="fbDateSub"></div>

                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <input type="date" id="bookDateInput" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <input type="time" id="bookTimeInput" class="form-control form-control-sm">
                        </div>
                    </div>

                    <div class="mt-2">
                        <select id="bookBranch" name="branch" class="form-select form-select-sm">
                            <option value="">-- Select Branch --</option>
                            <option value="old_airport">Old Airport</option>
                            <option value="wakrah">Wakrah</option>
                            <option value="home_service">Home Service</option>
                        </select>
                    </div>

                    <div class="fb-section-title">Services</div>
                    <div id="fbSvcList"></div>
                    <div id="fbComboList"></div>
                    <div id="fbRedeemList"></div>
                    <input type="hidden" name="decide_in_salon" id="bookDecideInSalonHidden" value="0">
                    <div id="fbComboHiddenInputs"></div>
                    <div id="fbRedeemHiddenInputs"></div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="fb-add-pill" id="fbOpenServicePanel">
                            <i class="bx bx-plus"></i> Add service
                        </button>
                        <button type="button" class="fb-decide-pill" id="fbDecideInSalonBtn">
                            Decide in Salon
                        </button>
                        <button type="button" class="fb-combo-pill" id="fbOpenComboPanel">
                            <i class="bx bx-plus"></i> Add Combo
                        </button>
                        <button type="button" class="fb-redeem-pill d-none" id="fbOpenRedeemPanel">
                            Redeem Package <span class="badge rounded-pill" id="fbRedeemBadge">0</span>
                        </button>
                    </div>

                    <div id="fbTeamMemberSection">
                        <div class="fb-section-title">Team Member</div>
                        <select id="bookStaffSelect" name="staff_id" class="form-select form-select-sm" required>
                            <option value="">-- Select Staff --</option>
                        </select>
                        <small id="bookStaffHelp" class="text-danger d-none">
                            No staff available for the selected service &amp; time
                        </small>
                    </div>

                    <div class="fb-section-title">Booking Agent</div>
                    <select id="bookAgent" name="booking_agent_id" class="form-select form-select-sm">
                        <option value="">-- Select Agent --</option>
                        @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                        @endforeach
                    </select>

                    <div class="fb-section-title">Notes</div>
                    <textarea id="bookNotes" name="notes" class="form-control form-control-sm" rows="2" placeholder="Anything the team should know..."></textarea>
                </div>

                <!-- PANEL: SELECT CLIENT -->
                <div id="fbPanelClient" class="fb-content d-none">
                    <div class="fb-panel-header">
                        <button type="button" class="fb-back-btn" onclick="fbShowPanel('main')"><i class="bx bx-arrow-back"></i></button>
                        <h5>Select a client</h5>
                    </div>

                    <div class="fb-search-wrap">
                        <i class="bx bx-search"></i>
                        <input type="text" id="fbClientSearch" class="form-control" placeholder="Search by name or phone">
                    </div>

                    <div class="fb-client-row add-new" onclick="fbShowAddClientForm()">
                        <div class="fb-client-avatar"><i class="bx bx-plus"></i></div>
                        <div class="fb-client-name">Add new client</div>
                    </div>

                    <div id="fbAddClientForm" class="d-none border rounded p-3 my-2">
                        <input type="text" id="fbNewClientName" class="form-control form-control-sm mb-2" placeholder="Client name">
                        <div class="input-group input-group-sm mb-1 fb-phone-group">
                            <select id="fbNewClientCountryCode" class="form-select fb-country-select">
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
                            <input type="tel" id="fbNewClientPhone" class="form-control" placeholder="Phone number" inputmode="tel" autocomplete="tel-national">
                        </div>
                        <small id="fbNewClientPhoneError" class="text-danger d-none mb-2 d-block">Enter a valid phone number.</small>
                        <button type="button" class="btn btn-sm btn-primary w-100" onclick="fbConfirmNewClient()">Add Client</button>
                    </div>

                    <hr>
                    <div id="fbClientResults"></div>
                </div>

                <!-- PANEL: SELECT SERVICE -->
                <div id="fbPanelService" class="fb-content d-none">
                    <div class="fb-panel-header">
                        <button type="button" class="fb-back-btn" onclick="fbShowPanel('main')"><i class="bx bx-arrow-back"></i></button>
                        <h5>Add a service</h5>
                    </div>

                    <div class="fb-search-wrap">
                        <i class="bx bx-search"></i>
                        <input type="text" id="fbServiceSearch" class="form-control" placeholder="Search by service name">
                    </div>

                    <div class="fb-cat-label">All Services <span id="fbSvcCount"></span></div>
                    <div id="fbServiceResults"></div>
                </div>

                <!-- PANEL: SELECT COMBO -->
                <div id="fbPanelCombo" class="fb-content d-none">
                    <div class="fb-panel-header">
                        <button type="button" class="fb-back-btn" onclick="fbShowPanel('main')"><i class="bx bx-arrow-back"></i></button>
                        <h5>Add a combo package</h5>
                    </div>

                    <div id="fbComboPickerList"></div>
                </div>

                <!-- PANEL: REDEEM PENDING PACKAGE SERVICE -->
                <div id="fbPanelRedeem" class="fb-content d-none">
                    <div class="fb-panel-header">
                        <button type="button" class="fb-back-btn" onclick="fbShowPanel('main')"><i class="bx bx-arrow-back"></i></button>
                        <h5>Redeem package service</h5>
                    </div>

                    <div id="fbRedeemPickerList"></div>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="fb-footer">
            <div class="total-label">Total</div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="total-meta" id="fbFooterDuration">0min</div>
                    <div class="total-discount d-none" id="fbFooterDiscount">Discount: −0.00 QAR</div>
                    <div class="total-price" id="fbFooterPrice">0 QAR</div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
                <button type="submit" class="btn btn-outline-success btn-sm" id="fbCheckoutBtn">Checkout</button>
                <button type="submit" class="btn btn-dark btn-sm" id="fbSaveBtn">Save</button>
            </div>
        </div>
    </form>
</div>

<script>
    (function() {
        const ALL_SERVICES = @json($services->map(fn($s) => ['name' => $s->name, 'price' => (float) $s->price, 'duration' => (int) $s->duration]));
        const ALL_COMBOS = @json($combosCatalog ?? []);

        let selectedServices = [];
        let decideInSalon = false;
        let selectedCombos = [];
        let comboRowSeq = 0;
        let selectedClient = null; // { id, name, phone } or null
        let pendingStaffId = null;
        let staffRequestSeq = 0;
        let clientSearchTimer = null;
        let clientPendingPackages = []; // this client's redeemable package services, fetched once picked
        let selectedRedemptions = []; // subset of the above actually being applied to this booking

        window.openCalendarBookModal = function(prefill) {
            prefill = prefill || {};

            selectedServices = [];
            decideInSalon = false;
            document.getElementById('bookDecideInSalonHidden').value = '0';
            selectedCombos = [];
            selectedClient = null;
            clientPendingPackages = [];
            selectedRedemptions = [];
            pendingStaffId = prefill.staffId || null;

            document.getElementById('bookStaffSelect').innerHTML = '<option value="">-- Select Staff --</option>';
            document.getElementById('bookStaffHelp').classList.add('d-none');
            document.getElementById('bookNotes').value = '';
            document.getElementById('bookThen').value = '';
            document.getElementById('fbAddClientForm').classList.add('d-none');
            document.getElementById('fbClientSearch').value = '';
            document.getElementById('fbClientResults').innerHTML = '';
            document.getElementById('fbNewClientName').value = '';
            document.getElementById('fbNewClientPhone').value = '';
            document.getElementById('fbNewClientCountryCode').value = '+974';
            document.getElementById('fbNewClientPhoneError').classList.add('d-none');

            if (prefill.datetime) {
                const [d, t] = prefill.datetime.split('T');
                document.getElementById('bookDateInput').value = d;
                document.getElementById('bookTimeInput').value = t;
            }
            if (prefill.branch) {
                document.getElementById('bookBranch').value = prefill.branch;
            }

            fbUpdateRail();
            fbRenderServices();
            fbRenderCombos();
            fbRenderRedemptions();
            fbUpdateRedeemButton();
            fbUpdateDateLabel();
            fbShowPanel('main');

            const modalEl = document.getElementById('calendarBookModal');
            const drawer = bootstrap.Offcanvas.getOrCreateInstance(modalEl);
            drawer.show();

            loadAvailableStaff();
        };

        function fbShowPanel(name) {
            ['Main', 'Client', 'Service', 'Combo', 'Redeem'].forEach(p => {
                document.getElementById('fbPanel' + p).classList.toggle('d-none', p.toLowerCase() !== name);
            });
            if (name === 'client') {
                fbRenderClientResults('');
            }
            if (name === 'service') {
                fbRenderServiceResults('');
            }
            if (name === 'combo') {
                fbRenderComboPicker();
            }
            if (name === 'redeem') {
                fbRenderRedeemPicker();
            }
        }
        window.fbShowPanel = fbShowPanel;

        function fbUpdateDateLabel() {
            const dateVal = document.getElementById('bookDateInput').value;
            const timeVal = document.getElementById('bookTimeInput').value;
            if (!dateVal) return;
            const d = new Date(dateVal + 'T00:00:00');
            document.getElementById('fbDateLabel').textContent =
                d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });

            if (timeVal) {
                const [h, m] = timeVal.split(':').map(Number);
                const p = h >= 12 ? 'PM' : 'AM';
                const hh = h % 12 || 12;
                document.getElementById('fbDateSub').textContent = `${hh}:${m.toString().padStart(2,'0')} ${p} · Doesn't repeat`;
            }
        }
        document.getElementById('bookDateInput').addEventListener('change', () => { fbUpdateDateLabel(); loadAvailableStaff(); fbRefreshComboStaffOptions(); });
        document.getElementById('bookTimeInput').addEventListener('change', () => { fbUpdateDateLabel(); loadAvailableStaff(); fbRefreshComboStaffOptions(); });
        document.getElementById('bookBranch').addEventListener('change', () => { loadAvailableStaff(); fbRefreshComboStaffOptions(); });

        /* ---------------- RAIL / CLIENT ---------------- */
        function fbUpdateRail() {
            const icon = document.getElementById('fbRailIcon');
            const label = document.getElementById('fbRailLabel');
            const sub = document.getElementById('fbRailSub');

            document.getElementById('bookCustomerNameHidden').value = selectedClient ? (selectedClient.name || '') : '';
            document.getElementById('bookPhoneHidden').value = selectedClient ? (selectedClient.phone || '') : '';

            if (!selectedClient) {
                icon.innerHTML = '<i class="bx bx-user-plus"></i>';
                label.textContent = 'Add client';
                sub.textContent = 'Search or add a new client';
                return;
            }

            icon.innerHTML = selectedClient.name ? selectedClient.name.charAt(0).toUpperCase() : '?';
            label.textContent = selectedClient.name || 'Client';
            sub.textContent = selectedClient.phone || '';
        }

        document.getElementById('fbRail').addEventListener('click', () => fbShowPanel('client'));

        window.fbShowAddClientForm = function() {
            document.getElementById('fbAddClientForm').classList.remove('d-none');
        };

        window.fbConfirmNewClient = function() {
            const name = document.getElementById('fbNewClientName').value.trim();
            const countryCode = document.getElementById('fbNewClientCountryCode').value;
            const rawPhone = document.getElementById('fbNewClientPhone').value.trim();
            const errorEl = document.getElementById('fbNewClientPhoneError');
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

            selectedClient = { name, phone: fullPhone };
            selectedRedemptions = [];
            fbLoadClientPendingPackages();
            fbUpdateRail();
            fbShowPanel('main');
        };

        function fbSelectExistingClient(client) {
            selectedClient = { id: client.id, name: client.name, phone: client.phone };
            selectedRedemptions = [];
            fbUpdateRail();
            fbShowPanel('main');
            fbLoadClientPendingPackages();
        }

        /* ---------------- REDEEM PENDING PACKAGE SERVICE ---------------- */
        function fbLoadClientPendingPackages() {
            clientPendingPackages = [];
            fbUpdateRedeemButton();

            if (!selectedClient || !selectedClient.id) return;

            fetch(`/customers/${selectedClient.id}/pending-packages`)
                .then(res => res.json())
                .then(data => {
                    clientPendingPackages = Array.isArray(data) ? data : [];
                    fbUpdateRedeemButton();
                })
                .catch(() => {});
        }

        function fbUpdateRedeemButton() {
            const btn = document.getElementById('fbOpenRedeemPanel');
            const available = clientPendingPackages.filter(p => !selectedRedemptions.some(r => r.id === p.id));
            btn.classList.toggle('d-none', clientPendingPackages.length === 0);
            document.getElementById('fbRedeemBadge').textContent = available.length;
        }

        function fbRenderRedeemPicker() {
            const box = document.getElementById('fbRedeemPickerList');
            const available = clientPendingPackages.filter(p => !selectedRedemptions.some(r => r.id === p.id));

            if (!available.length) {
                box.innerHTML = '<div class="text-muted small px-2">Nothing left to redeem for this client.</div>';
                return;
            }

            box.innerHTML = available.map((p, i) => `
                <div class="fb-redeem-pick-row" data-idx="${i}">
                    <div class="flex-grow-1">
                        <div class="name">${p.service_name} <span class="text-muted">(${p.duration} min)</span></div>
                        <div class="fb-redeem-row-meta">
                            from ${p.combo_name} ·
                            <span class="fb-redeem-expiry ${p.days_left <= 2 ? 'soon' : 'ok'}">
                                ${p.days_left <= 0 ? 'expires today' : 'expires ' + p.expires_at + ' (' + p.days_left + 'd left)'}
                            </span>
                        </div>
                    </div>
                </div>
            `).join('');

            box.querySelectorAll('.fb-redeem-pick-row').forEach(row => {
                row.addEventListener('click', () => {
                    const item = available[Number(row.dataset.idx)];
                    selectedRedemptions.push(item);
                    fbRenderRedemptions();
                    fbUpdateRedeemButton();
                    loadAvailableStaff();
                    fbShowPanel('main');
                });
            });
        }

        function fbRenderRedemptions() {
            const list = document.getElementById('fbRedeemList');

            list.innerHTML = selectedRedemptions.map((r, i) => `
                <div class="fb-svc-row">
                    <div class="fb-svc-row-main">
                        <div>
                            <div class="name">${r.service_name} <span class="fb-decide-tag">free · package</span></div>
                            <div class="meta">${r.duration} min · from ${r.combo_name}</div>
                        </div>
                        <button type="button" class="remove-btn fb-redeem-remove-btn" data-idx="${i}"><i class="bx bx-x"></i></button>
                    </div>
                </div>
            `).join('');

            list.querySelectorAll('.fb-redeem-remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectedRedemptions.splice(Number(btn.dataset.idx), 1);
                    fbRenderRedemptions();
                    fbUpdateRedeemButton();
                    fbSyncHiddenAndTotals();
                    loadAvailableStaff();
                });
            });

            fbSyncHiddenAndTotals();
        }

        document.getElementById('fbOpenRedeemPanel').addEventListener('click', () => fbShowPanel('redeem'));

        function fbRenderClientResults(query) {
            const box = document.getElementById('fbClientResults');
            box.innerHTML = '<div class="text-muted small px-2">Searching…</div>';

            fetch("{{ route('customers.search') }}?q=" + encodeURIComponent(query))
                .then(r => r.json())
                .then(list => {
                    if (!list.length) {
                        box.innerHTML = '<div class="text-muted small px-2">No clients found.</div>';
                        return;
                    }
                    box.innerHTML = list.map(c => `
                        <div class="fb-client-row" data-id="${c.id}" data-name="${c.name.replace(/"/g,'&quot;')}" data-phone="${c.phone}">
                            <div class="fb-client-avatar">${c.initials}</div>
                            <div>
                                <div class="fb-client-name">${c.name}</div>
                                <div class="fb-client-phone">${c.phone}</div>
                            </div>
                        </div>
                    `).join('');

                    box.querySelectorAll('.fb-client-row').forEach(row => {
                        row.addEventListener('click', () => fbSelectExistingClient({
                            id: row.dataset.id, name: row.dataset.name, phone: row.dataset.phone
                        }));
                    });
                });
        }

        document.getElementById('fbClientSearch').addEventListener('input', function() {
            clearTimeout(clientSearchTimer);
            const q = this.value;
            clientSearchTimer = setTimeout(() => fbRenderClientResults(q), 300);
        });

        /* ---------------- SERVICES ---------------- */
        function fbServiceDiscount(s) {
            return Math.max(0, (Number(s.original_price) || 0) - (Number(s.price) || 0));
        }

        function fbRenderServices() {
            const list = document.getElementById('fbSvcList');

            if (decideInSalon && !selectedServices.length) {
                list.innerHTML = `
                    <div class="fb-svc-row">
                        <div class="fb-svc-row-main">
                            <div>
                                <div class="name">Decide in Salon</div>
                                <div class="meta">0 min</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fb-decide-tag">0.00 QAR</span>
                                <button type="button" class="remove-btn" id="fbDecideInSalonRemove"><i class="bx bx-x"></i></button>
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('fbDecideInSalonRemove').addEventListener('click', () => {
                    decideInSalon = false;
                    fbRenderServices();
                });
                fbSyncHiddenAndTotals();
                loadAvailableStaff();
                return;
            }

            list.innerHTML = selectedServices.map((s, i) => {
                const discount = fbServiceDiscount(s);
                return `
                <div class="fb-svc-row">
                    <div class="fb-svc-row-main">
                        <div>
                            <div class="name">${s.name}</div>
                            <div class="meta">${s.duration} min</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="fb-price-edit">
                                <span class="currency">QAR</span>
                                <input type="number" step="0.01" min="0" inputmode="decimal" class="price-input" data-idx="${i}" value="${s.price.toFixed(2)}">
                            </div>
                            <button type="button" class="remove-btn" data-idx="${i}"><i class="bx bx-x"></i></button>
                        </div>
                    </div>
                    ${discount > 0 ? `
                    <div class="fb-discount-row">
                        <span class="fb-discount-badge">−${discount.toFixed(2)} QAR off ${s.original_price.toFixed(2)}</span>
                        <input type="text" class="fb-discount-reason" data-idx="${i}" placeholder="Reason (optional)" value="${(s.discount_reason || '').replace(/"/g,'&quot;')}">
                    </div>` : ''}
                </div>
            `;
            }).join('');

            list.querySelectorAll('.remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectedServices.splice(Number(btn.dataset.idx), 1);
                    fbRenderServices();
                });
            });

            // Manual price override: staff can type a custom price for this
            // specific booking without touching the service's master price.
            // The discount badge/reason row and footer total/hidden inputs
            // resync on each keystroke; the price list itself only fully
            // re-renders when a discount note needs to appear/disappear, so
            // typing doesn't steal focus.
            list.querySelectorAll('.price-input').forEach(input => {
                input.addEventListener('input', () => {
                    const idx = Number(input.dataset.idx);
                    const val = parseFloat(input.value);
                    const hadDiscount = fbServiceDiscount(selectedServices[idx]) > 0;
                    selectedServices[idx].price = (isNaN(val) || val < 0) ? 0 : val;
                    const hasDiscount = fbServiceDiscount(selectedServices[idx]) > 0;

                    if (hadDiscount !== hasDiscount) {
                        fbRenderServices();
                        list.querySelector(`.price-input[data-idx="${idx}"]`)?.focus();
                    } else if (hasDiscount) {
                        const badge = list.querySelector(`.fb-svc-row:nth-child(${idx + 1}) .fb-discount-badge`);
                        if (badge) badge.textContent = `−${fbServiceDiscount(selectedServices[idx]).toFixed(2)} QAR off ${selectedServices[idx].original_price.toFixed(2)}`;
                    }
                    fbSyncHiddenAndTotals();
                });
                input.addEventListener('focus', () => input.select());
            });

            list.querySelectorAll('.fb-discount-reason').forEach(input => {
                input.addEventListener('input', () => {
                    selectedServices[Number(input.dataset.idx)].discount_reason = input.value;
                    fbSyncHiddenAndTotals();
                });
            });

            fbSyncHiddenAndTotals();
            loadAvailableStaff();
        }

        function fbSyncHiddenAndTotals() {
            document.getElementById('bookDecideInSalonHidden').value =
                (decideInSalon && !selectedServices.length) ? '1' : '0';

            const hidden = document.getElementById('svcHiddenInputs');
            hidden.innerHTML = selectedServices.map(s => `
                <input type="hidden" name="service_name[]" value="${s.name.replace(/"/g,'&quot;')}">
                <input type="hidden" name="service_price[]" value="${s.price}">
                <input type="hidden" name="service_discount_reason[]" value="${(s.discount_reason || '').replace(/"/g,'&quot;')}">
            `).join('');

            const comboHidden = document.getElementById('fbComboHiddenInputs');
            comboHidden.innerHTML = selectedCombos.map(row => `
                <input type="hidden" name="packages[${row.id}][combo_id]" value="${row.combo.id}">
                ${row.immediateIds.map(id => `<input type="hidden" name="packages[${row.id}][immediate_service_ids][]" value="${id}">`).join('')}
                ${Object.entries(row.serviceStaff || {}).filter(([, staffId]) => staffId).map(([sid, staffId]) => `<input type="hidden" name="packages[${row.id}][service_staff][${sid}]" value="${staffId}">`).join('')}
            `).join('');

            const redeemHidden = document.getElementById('fbRedeemHiddenInputs');
            redeemHidden.innerHTML = selectedRedemptions.map(r => `
                <input type="hidden" name="redeem_service_ids[]" value="${r.id}">
            `).join('');

            // Package services - new or redeemed - are already covered by
            // what the client already paid, so only their duration - never
            // a QAR amount - feeds into this visit's running total here.
            const comboImmediateMinutes = selectedCombos.reduce((sum, r) => sum + fbComboImmediateDuration(r), 0);
            const redeemMinutes = selectedRedemptions.reduce((sum, r) => sum + (r.duration || 0), 0);

            const totalPrice = selectedServices.reduce((sum, s) => sum + (Number(s.price) || 0), 0);
            const totalDiscount = selectedServices.reduce((sum, s) => sum + fbServiceDiscount(s), 0);
            const totalMin = selectedServices.reduce((sum, s) => sum + s.duration, 0) + comboImmediateMinutes + redeemMinutes;

            document.getElementById('bookPriceHidden').value = totalPrice.toFixed(2);
            document.getElementById('fbFooterPrice').textContent = totalPrice.toFixed(2) + ' QAR';

            const discountEl = document.getElementById('fbFooterDiscount');
            discountEl.classList.toggle('d-none', totalDiscount <= 0);
            discountEl.textContent = `Discount: −${totalDiscount.toFixed(2)} QAR`;

            const hrs = Math.floor(totalMin / 60);
            const mins = totalMin % 60;
            document.getElementById('fbFooterDuration').textContent =
                totalMin ? (hrs ? `${hrs}h ${mins}min` : `${mins}min`) : '0min';
        }

        /* ---------------- COMBO PACKAGES ---------------- */
        function fbComboImmediateDuration(row) {
            return row.combo.services
                .filter(s => row.immediateIds.includes(s.id))
                .reduce((sum, s) => sum + s.duration, 0);
        }

        function fbComboCardHtml(row) {
            const combo = row.combo;
            const todayCount = row.immediateIds.length;
            const capReached = todayCount >= combo.quantity_included;
            const remaining = combo.quantity_included - todayCount;

            // No "select N of M to include" step anymore - staff just tick
            // whichever pool services are being done today (up to the
            // package's quantity_included). Whatever's left over is banked
            // as a generic pending entitlement, not locked to a specific
            // service - it's chosen later, at redemption time.
            const rowsHtml = combo.services.map(s => {
                const immediateChecked = row.immediateIds.includes(s.id);
                const disableCheckbox = !immediateChecked && capReached;
                const staffEntry = row.serviceStaffOptions[s.id];
                const staffOpts = staffEntry ? staffEntry.options : null;
                const currentStaff = row.serviceStaff[s.id] || '';
                const staffSelectHtml = immediateChecked ? `
                        <select class="form-select form-select-sm fb-combo-staff-select" data-row="${row.id}" data-svc="${s.id}">
                            <option value="">-- Same as main staff --</option>
                            ${(staffOpts || []).map(st => `<option value="${st.id}" ${currentStaff == st.id ? 'selected' : ''}>${st.name}</option>`).join('')}
                        </select>` : '';
                return `
                    <div class="fb-combo-svc-row">
                        <label class="fb-combo-immediate-toggle">
                            <input type="checkbox" class="form-check-input fb-combo-immediate-cb" data-row="${row.id}" data-svc="${s.id}"
                                ${immediateChecked ? 'checked' : ''} ${disableCheckbox ? 'disabled' : ''}>
                            ${s.name} <span class="text-muted">(${s.duration} min)</span>
                        </label>
                        ${staffSelectHtml}
                    </div>`;
            }).join('');

            const todayDuration = fbComboImmediateDuration(row);
            const metaPendingNote = remaining > 0
                ? ` · ${remaining} service${remaining === 1 ? '' : 's'} will be saved as pending`
                : '';

            return `
                <div class="fb-svc-row fb-combo-card" data-row-id="${row.id}">
                    <div class="fb-svc-row-main">
                        <div>
                            <div class="name">${combo.name}</div>
                            <div class="meta">${combo.price.toFixed(2)} QAR · ${todayCount} / ${combo.quantity_included} chosen for today${metaPendingNote}</div>
                        </div>
                        <button type="button" class="remove-btn fb-combo-remove-btn" data-row="${row.id}"><i class="bx bx-x"></i></button>
                    </div>
                    <div class="fb-combo-today-duration">Today's duration: <strong>${todayDuration} min</strong>${todayCount ? '' : ` (nothing marked "Do today" yet - all ${combo.quantity_included} will be saved as pending)`}</div>
                    ${rowsHtml}
                </div>`;
        }

        function fbRenderCombos() {
            const container = document.getElementById('fbComboList');
            container.innerHTML = selectedCombos.map(fbComboCardHtml).join('');

            fbUpdateTeamMemberVisibility();

            container.querySelectorAll('.fb-combo-remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectedCombos = selectedCombos.filter(r => r.id != btn.dataset.row);
                    fbRenderCombos();
                    fbSyncHiddenAndTotals();
                    loadAvailableStaff();
                });
            });

            container.querySelectorAll('.fb-combo-immediate-cb').forEach(cb => {
                cb.addEventListener('change', () => {
                    const row = selectedCombos.find(r => r.id == cb.dataset.row);
                    const svcId = Number(cb.dataset.svc);
                    if (cb.checked) {
                        if (!row.immediateIds.includes(svcId)) row.immediateIds.push(svcId);
                    } else {
                        row.immediateIds = row.immediateIds.filter(id => id !== svcId);
                        delete row.serviceStaff[svcId];
                        delete row.serviceStaffOptions[svcId];
                    }
                    fbRenderCombos();
                    fbSyncHiddenAndTotals();
                    loadAvailableStaff();
                });
            });

            container.querySelectorAll('.fb-combo-staff-select').forEach(sel => {
                sel.addEventListener('change', () => {
                    const row = selectedCombos.find(r => r.id == sel.dataset.row);
                    const svcId = Number(sel.dataset.svc);
                    if (sel.value) {
                        row.serviceStaff[svcId] = sel.value;
                    } else {
                        delete row.serviceStaff[svcId];
                    }
                    fbSyncHiddenAndTotals();
                    loadAvailableStaff();
                });
            });

            fbSyncHiddenAndTotals();
            fbRefreshComboStaffOptions();
        }

        /* A combo's services are each assigned their own staff inline, so
           the appointment-wide "Team Member" field is redundant noise once
           a combo is in the booking - hidden (not removed: it still carries
           a valid staff_id for the appointment record itself, auto-filled
           in loadAvailableStaff's callback) whenever any combo is present. */
        function fbUpdateTeamMemberVisibility() {
            const hide = selectedCombos.length > 0;
            document.getElementById('fbTeamMemberSection').classList.toggle('d-none', hide);
            // A hidden-but-required field blocks native form submission
            // silently (a display:none control can't be focused for the
            // validation prompt), so the requirement only applies while
            // the field itself is visible and actionable.
            document.getElementById('bookStaffSelect').required = !hide;
        }

        /* With the Team Member field hidden behind a combo, its value still
           has to be a real staff id for the appointment record - filled in
           automatically instead of asking the user to redundantly repick
           someone already assigned inline to one of today's combo services. */
        function fbAutoAssignMainStaffForCombo() {
            if (!selectedCombos.length) return;

            const staffSelect = document.getElementById('bookStaffSelect');
            // Never override an explicit value already sitting there
            // (a pending reschedule id, or one the flow set for another
            // reason) - this only fills in a genuinely empty selection.
            if (!staffSelect || staffSelect.disabled || !staffSelect.options.length || staffSelect.value) return;

            let preferred = null;
            for (const row of selectedCombos) {
                for (const sid of row.immediateIds) {
                    if (row.serviceStaff[sid]) { preferred = row.serviceStaff[sid]; break; }
                }
                if (preferred) break;
            }

            if (preferred && [...staffSelect.options].some(o => o.value == preferred)) {
                staffSelect.value = preferred;
            } else if (staffSelect.options.length > 1) {
                staffSelect.value = staffSelect.options[1].value;
            }
        }

        /* Per-service staff picker for combo services marked "Do today" -
           each fetches independently from the main staff dropdown, since a
           combo can split its services across different team members. */
        function fbComboStaffSignature() {
            const dateVal = document.getElementById('bookDateInput').value;
            const timeVal = document.getElementById('bookTimeInput').value;
            const branch = document.getElementById('bookBranch').value;
            return `${dateVal}T${timeVal}|${branch}`;
        }

        let comboStaffRequestSeq = 0;

        function fbFetchComboServiceStaff(row, svcId, svcName) {
            const dateVal = document.getElementById('bookDateInput').value;
            const timeVal = document.getElementById('bookTimeInput').value;
            const branch = document.getElementById('bookBranch').value;
            const datetime = (dateVal && timeVal) ? `${dateVal}T${timeVal}` : '';
            if (!datetime || !branch) return;

            const sig = fbComboStaffSignature();
            const requestId = ++comboStaffRequestSeq;

            const params = new URLSearchParams();
            params.append('services[]', svcName);
            params.append('appointment_datetime', datetime);
            params.append('branch', branch);

            fetch("{{ route('appointments.availableStaff') }}?" + params.toString())
                .then(res => res.json())
                .then(data => {
                    if (requestId !== comboStaffRequestSeq) return;
                    row.serviceStaffOptions[svcId] = { sig, options: data };

                    const select = document.querySelector(`.fb-combo-staff-select[data-row="${row.id}"][data-svc="${svcId}"]`);
                    if (!select) return;
                    const current = row.serviceStaff[svcId] || '';
                    select.innerHTML = '<option value="">-- Same as main staff --</option>' +
                        data.map(st => `<option value="${st.id}">${st.name}</option>`).join('');
                    if (current && data.some(st => st.id == current)) {
                        select.value = current;
                    } else if (current) {
                        delete row.serviceStaff[svcId];
                        fbSyncHiddenAndTotals();
                    }
                })
                .catch(err => console.error('Fetch error:', err));
        }

        function fbRefreshComboStaffOptions() {
            selectedCombos.forEach(row => {
                row.immediateIds.forEach(svcId => {
                    const entry = row.serviceStaffOptions[svcId];
                    if (!entry || entry.sig !== fbComboStaffSignature()) {
                        const svc = row.combo.services.find(s => s.id === svcId);
                        if (svc) fbFetchComboServiceStaff(row, svcId, svc.name);
                    }
                });
            });
        }

        function fbRenderComboPicker() {
            const box = document.getElementById('fbComboPickerList');

            if (!ALL_COMBOS.length) {
                box.innerHTML = '<div class="text-muted small px-2">No active combo packages in the catalog.</div>';
                return;
            }

            box.innerHTML = ALL_COMBOS.map((c, i) => `
                <div class="fb-service-row" data-idx="${i}">
                    <div class="svc-info">
                        <div class="name">${c.name}</div>
                        <div class="meta">Choose any ${c.quantity_included} of ${c.services.length} services</div>
                    </div>
                    <div class="svc-price">${c.price.toFixed(2)} QAR</div>
                </div>
            `).join('');

            box.querySelectorAll('.fb-service-row').forEach(row => {
                row.addEventListener('click', () => {
                    const combo = ALL_COMBOS[Number(row.dataset.idx)];

                    // No pre-selection - staff just tick whichever pool
                    // services are being done today (up to quantity_included);
                    // whatever's left over is automatically banked as a
                    // pending entitlement, not locked to a specific service.
                    selectedCombos.push({ id: ++comboRowSeq, combo, immediateIds: [], serviceStaff: {}, serviceStaffOptions: {} });
                    fbRenderCombos();
                    fbShowPanel('main');
                });
            });
        }

        document.getElementById('fbOpenComboPanel').addEventListener('click', () => fbShowPanel('combo'));

        function fbRenderServiceResults(query) {
            const box = document.getElementById('fbServiceResults');
            const q = query.trim().toLowerCase();
            const matches = ALL_SERVICES.filter(s => s.name.toLowerCase().includes(q));

            document.getElementById('fbSvcCount').textContent = `(${matches.length})`;

            box.innerHTML = matches.map((s, i) => `
                <div class="fb-service-row" data-idx="${ALL_SERVICES.indexOf(s)}">
                    <div class="svc-info">
                        <div class="name">${s.name}</div>
                        <div class="meta">${s.duration}min</div>
                    </div>
                    <div class="svc-price">${s.price.toFixed(2)} QAR</div>
                </div>
            `).join('') || '<div class="text-muted small px-2">No services found.</div>';

            box.querySelectorAll('.fb-service-row').forEach(row => {
                row.addEventListener('click', () => {
                    const svc = ALL_SERVICES[Number(row.dataset.idx)];
                    if (selectedServices.some(s => s.name === svc.name)) {
                        fbShowPanel('main');
                        return;
                    }
                    decideInSalon = false;
                    selectedServices.push(Object.assign({}, svc, { original_price: svc.price, discount_reason: '' }));
                    fbRenderServices();
                    fbShowPanel('main');
                });
            });
        }

        document.getElementById('fbOpenServicePanel').addEventListener('click', () => fbShowPanel('service'));
        document.getElementById('fbDecideInSalonBtn').addEventListener('click', () => {
            decideInSalon = true;
            selectedServices = [];
            fbRenderServices();
        });
        document.getElementById('fbServiceSearch').addEventListener('input', function() {
            fbRenderServiceResults(this.value);
        });

        /* ---------------- STAFF AVAILABILITY ---------------- */
        function loadAvailableStaff() {
            const staffSelect = document.getElementById('bookStaffSelect');
            const staffHelp = document.getElementById('bookStaffHelp');

            const dateVal = document.getElementById('bookDateInput').value;
            const timeVal = document.getElementById('bookTimeInput').value;
            const branch = document.getElementById('bookBranch').value;
            const datetime = (dateVal && timeVal) ? `${dateVal}T${timeVal}` : '';

            staffSelect.innerHTML = '<option value="">-- Select Staff --</option>';
            staffHelp.classList.add('d-none');
            staffSelect.disabled = true;

            // Combo services marked "Do today" need a skilled staff member
            // just like a manually-picked service does, unless staff already
            // assigned that specific one to someone else on its own dropdown
            // - and redeeming a pending service is exactly the same. Pending
            // (not-yet-redeemed) combo services don't, since nothing happens
            // with them at this visit.
            const comboImmediateNames = [];
            selectedCombos.forEach(row => {
                row.combo.services.forEach(s => {
                    if (row.immediateIds.includes(s.id) && !(row.serviceStaff && row.serviceStaff[s.id])) {
                        comboImmediateNames.push(s.name);
                    }
                });
            });
            const redeemNames = selectedRedemptions.map(r => r.service_name);
            const hasImmediate = selectedServices.length > 0 || comboImmediateNames.length > 0 || redeemNames.length > 0;
            const decideMode = decideInSalon || (selectedCombos.length > 0 && !hasImmediate);

            if ((!hasImmediate && !decideMode) || !datetime || !branch) return;

            const requestId = ++staffRequestSeq;
            const params = new URLSearchParams();
            if (hasImmediate) {
                selectedServices.forEach(s => params.append('services[]', s.name));
                redeemNames.forEach(name => params.append('services[]', name));
                comboImmediateNames.forEach(name => params.append('services[]', name));
            } else {
                params.append('decide_in_salon', '1');
            }
            params.append('appointment_datetime', datetime);
            params.append('branch', branch);

            fetch("{{ route('appointments.availableStaff') }}?" + params.toString())
                .then(res => res.json())
                .then(data => {
                    if (requestId !== staffRequestSeq) return;

                    if (!data.length) {
                        staffHelp.classList.remove('d-none');
                        return;
                    }

                    staffSelect.disabled = false;
                    data.forEach(staff => {
                        staffSelect.insertAdjacentHTML('beforeend', `<option value="${staff.id}">${staff.name}</option>`);
                    });

                    if (pendingStaffId && [...staffSelect.options].some(o => o.value == pendingStaffId)) {
                        staffSelect.value = pendingStaffId;
                    }
                    pendingStaffId = null;

                    fbAutoAssignMainStaffForCombo();
                })
                .catch(err => console.error('Fetch error:', err));
        }

        /* ---------------- SUBMIT ---------------- */
        document.getElementById('calendarBookForm').addEventListener('submit', function(e) {
            const dateVal = document.getElementById('bookDateInput').value;
            const timeVal = document.getElementById('bookTimeInput').value;
            document.getElementById('bookDatetimeHidden').value = (dateVal && timeVal) ? `${dateVal}T${timeVal}` : '';

            if (!selectedClient) {
                e.preventDefault();
                alert('Please select an existing client or add a new client before booking.');
                fbShowPanel('client');
                return;
            }

            if (!selectedServices.length && !decideInSalon && !selectedCombos.length && !selectedRedemptions.length) {
                e.preventDefault();
                alert('Please add at least one service, combo package, or redeemed service.');
                fbShowPanel('service');
            }
        });

        document.getElementById('fbCheckoutBtn').addEventListener('click', function() {
            document.getElementById('bookThen').value = 'checkout';
        });
        document.getElementById('fbSaveBtn').addEventListener('click', function() {
            document.getElementById('bookThen').value = '';
        });
    })();
</script>
