<div class="modal fade" id="comboModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="comboForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="comboModalTitle">New Combo Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="comboFormError" class="alert alert-danger py-2 d-none"></div>

                    <div class="mb-3">
                        <label class="form-label">Combo name</label>
                        <input type="text" id="comboName" class="form-control" required maxlength="255">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Package price (QAR)</label>
                            <input type="number" id="comboPrice" class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Choose any <span class="text-muted">(optional)</span></label>
                            <input type="number" id="comboQuantityIncluded" class="form-control" step="1" min="1" placeholder="e.g. 6">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Validity (days) <span class="text-muted">(optional)</span></label>
                            <input type="number" id="comboValidityDays" class="form-control" step="1" min="1" max="3650" placeholder="e.g. 90">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select id="comboStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <p class="text-muted small mb-3" style="margin-top:-10px">
                        Leave "Choose any" blank to include every service below. Set it lower than the pool size
                        (e.g. 6 of 10) to let the client pick which ones at checkout.
                    </p>

                    <label class="form-label">
                        Eligible service pool
                        <span class="text-muted">(<span id="comboSvcCount">0</span> selected · <span id="comboSvcTotal">0.00</span> QAR regular price)</span>
                    </label>
                    <div class="combo-svc-picker">
                        @forelse ($allServices as $service)
                            <div class="combo-svc-row">
                                <div class="svc-label">
                                    <input type="checkbox" class="form-check-input combo-svc-checkbox"
                                        value="{{ $service->id }}" data-price="{{ $service->price }}" id="combo-svc-{{ $service->id }}">
                                    <label for="combo-svc-{{ $service->id }}" class="mb-0">{{ $service->name }}</label>
                                </div>
                                <span class="svc-price">{{ number_format($service->price, 2) }} QAR</span>
                            </div>
                        @empty
                            <div class="text-muted small px-2 py-1">No services in the catalog yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark" id="comboSubmitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        const modalEl = document.getElementById('comboModal');
        // Bootstrap's vendor script loads later in the page than this inline
        // block, so the instance is created lazily (on first show) rather
        // than eagerly here, where `bootstrap` wouldn't exist yet.
        function comboModalInstance() {
            return bootstrap.Modal.getOrCreateInstance(modalEl);
        }
        const form = document.getElementById('comboForm');
        const errorBox = document.getElementById('comboFormError');
        let editingId = null;

        function updateSelectionSummary() {
            const checked = modalEl.querySelectorAll('.combo-svc-checkbox:checked');
            const total = Array.from(checked).reduce((sum, cb) => sum + (parseFloat(cb.dataset.price) || 0), 0);
            document.getElementById('comboSvcCount').textContent = checked.length;
            document.getElementById('comboSvcTotal').textContent = total.toFixed(2);
        }
        modalEl.querySelectorAll('.combo-svc-checkbox').forEach(cb => cb.addEventListener('change', updateSelectionSummary));

        function resetForm() {
            editingId = null;
            document.getElementById('comboModalTitle').textContent = 'New Combo Package';
            document.getElementById('comboSubmitBtn').textContent = 'Save';
            form.reset();
            errorBox.classList.add('d-none');
            modalEl.querySelectorAll('.combo-svc-checkbox').forEach(cb => cb.checked = false);
            updateSelectionSummary();
        }

        document.getElementById('addComboBtn').addEventListener('click', function() {
            resetForm();
            comboModalInstance().show();
        });

        window.editCombo = function(combo, serviceIds) {
            resetForm();
            editingId = combo.id;
            document.getElementById('comboModalTitle').textContent = 'Edit Combo Package';
            document.getElementById('comboSubmitBtn').textContent = 'Update';
            document.getElementById('comboName').value = combo.name;
            document.getElementById('comboPrice').value = combo.price;
            document.getElementById('comboQuantityIncluded').value = combo.quantity_included || '';
            document.getElementById('comboValidityDays').value = combo.validity_days || '';
            document.getElementById('comboStatus').value = combo.status;
            modalEl.querySelectorAll('.combo-svc-checkbox').forEach(cb => {
                cb.checked = serviceIds.includes(Number(cb.value));
            });
            updateSelectionSummary();
            comboModalInstance().show();
        };

        modalEl.addEventListener('hidden.bs.modal', resetForm);

        function comboCardHtml(combo) {
            const statusClass = combo.status !== 'active' ? 'inactive' : '';
            const validity = combo.validity_days ? ` · Valid ${combo.validity_days} days` : '';
            const servicesLabel = combo.services.map(s => s.name).join(', ');
            const regularPriceHtml = combo.regular_price > combo.price
                ? `<div class="combo-regular-price">${combo.regular_price.toFixed(2)} QAR</div>` : '';
            const poolSize = combo.services.length;
            const pickLabel = combo.quantity_included && combo.quantity_included < poolSize
                ? `Choose any ${combo.quantity_included} of ${poolSize} services`
                : `${poolSize} service${poolSize === 1 ? '' : 's'}`;

            const actionsHtml = `
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-warning combo-edit-btn"
                        data-combo='${JSON.stringify({id: combo.id, name: combo.name, price: combo.price, quantity_included: combo.quantity_included, validity_days: combo.validity_days, status: combo.status}).replace(/'/g, '&apos;')}'
                        data-service-ids='${JSON.stringify(combo.services.map(s => s.id))}'
                        title="Edit"><i class="bi bi-pencil-square"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger combo-delete-btn"
                        data-combo-id="${combo.id}" title="Delete"><i class="bi bi-trash"></i></button>
                </div>`;

            return `
                <div class="combo-card" data-combo-id="${combo.id}">
                    <div class="flex-grow-1">
                        <div>
                            <span class="combo-name">${combo.name}</span>
                            <span class="combo-status ${statusClass}">${combo.status}</span>
                        </div>
                        <div class="combo-services">${servicesLabel}</div>
                        <div class="combo-meta">${pickLabel}${validity}</div>
                    </div>
                    <div class="text-end">
                        <div class="combo-price">${combo.price.toFixed(2)} QAR</div>
                        ${regularPriceHtml}
                        ${actionsHtml}
                    </div>
                </div>`;
        }

        function bindCardActions(card) {
            const editBtn = card.querySelector('.combo-edit-btn');
            if (editBtn) {
                editBtn.addEventListener('click', function() {
                    window.editCombo(JSON.parse(this.dataset.combo.replace(/&apos;/g, "'")), JSON.parse(this.dataset.serviceIds));
                });
            }
            const delBtn = card.querySelector('.combo-delete-btn');
            if (delBtn) {
                delBtn.addEventListener('click', function() {
                    deleteCombo(this.dataset.comboId, card);
                });
            }
        }

        function upsertCard(combo) {
            const list = document.getElementById('comboList');
            document.getElementById('comboEmptyState')?.remove();

            const existing = list.querySelector(`.combo-card[data-combo-id="${combo.id}"]`);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = comboCardHtml(combo).trim();
            const newCard = wrapper.firstChild;
            bindCardActions(newCard);

            if (existing) {
                existing.replaceWith(newCard);
            } else {
                list.appendChild(newCard);
            }
        }

        function deleteCombo(id, card) {
            if (!confirm('Delete this combo package?')) return;

            fetch(`/combos/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        card.remove();
                        const list = document.getElementById('comboList');
                        if (!list.querySelector('.combo-card')) {
                            list.innerHTML = '<div class="text-center text-muted py-5" id="comboEmptyState">No combo packages yet.</div>';
                        }
                    } else {
                        alert(data.message || 'Could not delete this combo package.');
                    }
                })
                .catch(() => alert('Could not delete this combo package.'));
        }

        document.querySelectorAll('.combo-card').forEach(bindCardActions);

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            errorBox.classList.add('d-none');

            const serviceIds = Array.from(modalEl.querySelectorAll('.combo-svc-checkbox:checked')).map(cb => Number(cb.value));

            if (!serviceIds.length) {
                errorBox.textContent = 'Select at least one service for this combo.';
                errorBox.classList.remove('d-none');
                return;
            }

            const payload = {
                name: document.getElementById('comboName').value,
                price: document.getElementById('comboPrice').value,
                quantity_included: document.getElementById('comboQuantityIncluded').value || null,
                validity_days: document.getElementById('comboValidityDays').value || null,
                status: document.getElementById('comboStatus').value,
                service_ids: serviceIds,
            };

            const url = editingId ? `/combos/${editingId}` : '/combos';

            fetch(url, {
                    method: editingId ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(async r => {
                    const data = await r.json();
                    if (!r.ok) throw data;
                    return data;
                })
                .then(data => {
                    upsertCard(data.combo);
                    comboModalInstance().hide();
                })
                .catch(err => {
                    const message = err && err.errors ? Object.values(err.errors).flat().join(' ') : 'Could not save the combo package.';
                    errorBox.textContent = message;
                    errorBox.classList.remove('d-none');
                });
        });
    })();
</script>
