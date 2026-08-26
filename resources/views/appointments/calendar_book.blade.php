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
        border-right: 1px solid rgba(213,180,169,0.16);
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
        color: #b6a49b;
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
        border: 1px solid rgba(213,180,169,0.16);
        background: #241e1c;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #cbb8b0;
    }

    .fb-back-btn:hover {
        background: rgba(213,180,169,0.06);
    }

    .fb-date-label {
        font-size: 21px;
        font-weight: 800;
        color: #e79a91;
        cursor: pointer;
    }

    .fb-date-sub {
        font-size: 12.5px;
        color: #b6a49b;
    }

    .fb-section-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #b6a49b;
        margin: 22px 0 10px;
    }

    .fb-svc-row {
        border-left: 3px solid #d5b4a9;
        background: rgba(213,180,169,0.1);
        border-radius: 6px;
        padding: 8px 12px;
        margin-bottom: 8px;
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
        color: #b6a49b;
    }

    .fb-svc-row .price {
        font-weight: 700;
        font-size: 13.5px;
        white-space: nowrap;
    }

    .fb-svc-row .remove-btn {
        border: none;
        background: transparent;
        color: #b6a49b;
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
        background: rgba(213,180,169,0.06);
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
        color: #b6a49b;
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
        background: rgba(213,180,169,0.06);
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
        background: rgba(213,180,169,0.06);
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

    .fb-client-row.walkin .fb-client-avatar {
        background: rgba(213,180,169,0.06);
        color: #cbb8b0;
    }

    .fb-client-name {
        font-weight: 700;
        font-size: 13.5px;
        color: #e79a91;
    }

    .fb-client-phone {
        font-size: 12px;
        color: #b6a49b;
    }

    .fb-service-row .svc-info .name {
        font-weight: 700;
        font-size: 13.5px;
        color: #e79a91;
    }

    .fb-service-row .svc-info .meta {
        font-size: 11.5px;
        color: #b6a49b;
    }

    .fb-service-row .svc-price {
        font-weight: 700;
        font-size: 13.5px;
        margin-left: auto;
    }

    .fb-cat-label {
        font-size: 12px;
        font-weight: 700;
        color: #b6a49b;
        margin-bottom: 8px;
    }

    .fb-footer {
        flex-shrink: 0;
        border-top: 1px solid rgba(213,180,169,0.16);
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
        color: #b6a49b;
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
                <div class="client-sub" id="fbRailSub">Or leave empty for walk-ins</div>
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
                    <button type="button" class="fb-add-pill" id="fbOpenServicePanel">
                        <i class="bx bx-plus"></i> Add service
                    </button>

                    <div class="fb-section-title">Team Member</div>
                    <select id="bookStaffSelect" name="staff_id" class="form-select form-select-sm" required>
                        <option value="">-- Select Staff --</option>
                    </select>
                    <small id="bookStaffHelp" class="text-danger d-none">
                        No staff available for the selected service &amp; time
                    </small>

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
                        <input type="text" id="fbClientSearch" class="form-control" placeholder="Search client or leave empty">
                    </div>

                    <div class="fb-client-row add-new" onclick="fbShowAddClientForm()">
                        <div class="fb-client-avatar"><i class="bx bx-plus"></i></div>
                        <div class="fb-client-name">Add new client</div>
                    </div>

                    <div class="fb-client-row walkin" onclick="fbSelectWalkIn()">
                        <div class="fb-client-avatar"><i class="bx bx-walk"></i></div>
                        <div class="fb-client-name">Walk-In</div>
                    </div>

                    <div id="fbAddClientForm" class="d-none border rounded p-3 my-2">
                        <input type="text" id="fbNewClientName" class="form-control form-control-sm mb-2" placeholder="Client name">
                        <input type="text" id="fbNewClientPhone" class="form-control form-control-sm mb-2" placeholder="Phone number">
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
            </div>
        </div>

        <!-- FOOTER -->
        <div class="fb-footer">
            <div class="total-label">Total</div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="total-meta" id="fbFooterDuration">0min</div>
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

        let selectedServices = [];
        let selectedClient = null; // { id, name, phone } or null
        let pendingStaffId = null;
        let staffRequestSeq = 0;
        let clientSearchTimer = null;

        window.openCalendarBookModal = function(prefill) {
            prefill = prefill || {};

            selectedServices = [];
            selectedClient = null;
            pendingStaffId = prefill.staffId || null;

            document.getElementById('bookStaffSelect').innerHTML = '<option value="">-- Select Staff --</option>';
            document.getElementById('bookStaffHelp').classList.add('d-none');
            document.getElementById('bookNotes').value = '';
            document.getElementById('bookThen').value = '';
            document.getElementById('fbAddClientForm').classList.add('d-none');
            document.getElementById('fbClientSearch').value = '';
            document.getElementById('fbClientResults').innerHTML = '';

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
            fbUpdateDateLabel();
            fbShowPanel('main');

            const modalEl = document.getElementById('calendarBookModal');
            const drawer = bootstrap.Offcanvas.getOrCreateInstance(modalEl);
            drawer.show();

            loadAvailableStaff();
        };

        function fbShowPanel(name) {
            ['Main', 'Client', 'Service'].forEach(p => {
                document.getElementById('fbPanel' + p).classList.toggle('d-none', p.toLowerCase() !== name);
            });
            if (name === 'client') {
                fbRenderClientResults('');
            }
            if (name === 'service') {
                fbRenderServiceResults('');
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
        document.getElementById('bookDateInput').addEventListener('change', () => { fbUpdateDateLabel(); loadAvailableStaff(); });
        document.getElementById('bookTimeInput').addEventListener('change', () => { fbUpdateDateLabel(); loadAvailableStaff(); });
        document.getElementById('bookBranch').addEventListener('change', loadAvailableStaff);

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
                sub.textContent = 'Or leave empty for walk-ins';
                return;
            }

            if (selectedClient.walkin) {
                icon.innerHTML = '<i class="bx bx-walk"></i>';
                label.textContent = 'Walk-In';
                sub.textContent = 'No client profile';
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
            const phone = document.getElementById('fbNewClientPhone').value.trim();
            if (!phone) {
                alert('Please enter a phone number.');
                return;
            }
            selectedClient = { name, phone, walkin: false };
            fbUpdateRail();
            fbShowPanel('main');
        };

        window.fbSelectWalkIn = function() {
            selectedClient = { name: 'Walk-in', phone: '', walkin: true };
            fbUpdateRail();
            fbShowPanel('main');
        };

        function fbSelectExistingClient(client) {
            selectedClient = { id: client.id, name: client.name, phone: client.phone, walkin: false };
            fbUpdateRail();
            fbShowPanel('main');
        }

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
        function fbRenderServices() {
            const list = document.getElementById('fbSvcList');
            list.innerHTML = selectedServices.map((s, i) => `
                <div class="fb-svc-row">
                    <div>
                        <div class="name">${s.name}</div>
                        <div class="meta">${s.duration} min</div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="price">${s.price.toFixed(2)} QAR</span>
                        <button type="button" class="remove-btn" data-idx="${i}"><i class="bx bx-x"></i></button>
                    </div>
                </div>
            `).join('');

            list.querySelectorAll('.remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectedServices.splice(Number(btn.dataset.idx), 1);
                    fbRenderServices();
                });
            });

            const hidden = document.getElementById('svcHiddenInputs');
            hidden.innerHTML = selectedServices.map(s =>
                `<input type="hidden" name="service_name[]" value="${s.name.replace(/"/g,'&quot;')}">`
            ).join('');

            const totalPrice = selectedServices.reduce((sum, s) => sum + s.price, 0);
            const totalMin = selectedServices.reduce((sum, s) => sum + s.duration, 0);

            document.getElementById('bookPriceHidden').value = totalPrice.toFixed(2);
            document.getElementById('fbFooterPrice').textContent = totalPrice.toFixed(2) + ' QAR';
            const hrs = Math.floor(totalMin / 60);
            const mins = totalMin % 60;
            document.getElementById('fbFooterDuration').textContent =
                totalMin ? (hrs ? `${hrs}h ${mins}min` : `${mins}min`) : '0min';

            loadAvailableStaff();
        }

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
                    selectedServices.push(Object.assign({}, svc));
                    fbRenderServices();
                    fbShowPanel('main');
                });
            });
        }

        document.getElementById('fbOpenServicePanel').addEventListener('click', () => fbShowPanel('service'));
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

            if (!selectedServices.length || !datetime || !branch) return;

            const requestId = ++staffRequestSeq;
            const params = new URLSearchParams();
            selectedServices.forEach(s => params.append('services[]', s.name));
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
                })
                .catch(err => console.error('Fetch error:', err));
        }

        /* ---------------- SUBMIT ---------------- */
        document.getElementById('calendarBookForm').addEventListener('submit', function(e) {
            const dateVal = document.getElementById('bookDateInput').value;
            const timeVal = document.getElementById('bookTimeInput').value;
            document.getElementById('bookDatetimeHidden').value = (dateVal && timeVal) ? `${dateVal}T${timeVal}` : '';

            if (!selectedServices.length) {
                e.preventDefault();
                alert('Please add at least one service.');
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
