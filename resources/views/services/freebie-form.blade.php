<style>
    #freebieModal .modal-content {
        background: linear-gradient(180deg, rgba(34, 22, 27, 0.96) 0%, rgba(21, 14, 18, 0.98) 100%);
        border: 1px solid rgba(229, 184, 168, 0.18);
        border-radius: 16px;
        color: #f8efe6;
    }

    #freebieModal .modal-header,
    #freebieModal .modal-footer {
        border-color: rgba(255, 255, 255, 0.06);
    }

    #freebieModal .form-label {
        font-size: 12px;
        font-weight: 600;
        color: #b8a29a;
        margin-bottom: 5px;
    }

    #freebieModal .freebie-svc-picker {
        max-height: 240px;
        overflow-y: auto;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        padding: 2px 0;
    }

    #freebieModal .freebie-svc-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 6px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    #freebieModal .freebie-svc-row:last-child {
        border-bottom: none;
    }

    #freebieModal .freebie-svc-row:hover {
        background: rgba(229, 184, 168, 0.05);
    }

    #freebieModal .freebie-svc-label {
        display: flex;
        align-items: center;
        gap: 9px;
        min-width: 0;
        cursor: pointer;
    }

    #freebieModal .freebie-svc-meta {
        font-size: 11.5px;
        color: #b8a29a;
        white-space: nowrap;
    }

    .freebie-save-btn {
        background: linear-gradient(135deg, #e5b8a8 0%, #d98f83 100%);
        color: #1b1215;
        font-weight: 700;
        border: none;
    }

    .freebie-save-btn:hover {
        filter: brightness(1.06);
        color: #1b1215;
    }
</style>

<div class="modal fade" id="freebieModal" tabindex="-1" aria-labelledby="freebieModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="freebieForm" method="POST" action="{{ route('service-freebies.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="freebieModalTitle" style="color: #f8efe6;">New Service + Freebies Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div id="freebieFormError" class="py-2 px-3 mb-3 d-none" style="border-left: 2px solid #fda4af; background: rgba(253, 164, 175, 0.08); color: #fda4af; font-size: 12.5px;"></div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label" for="freebieName">Package Name</label>
                            <input type="text" id="freebieName" name="name" class="form-control" placeholder="e.g. Hair Color + 2 Free Services" required maxlength="255">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="freebieAnchorService">Main Anchor Service</label>
                            <select id="freebieAnchorService" name="anchor_service_id" class="form-select" required>
                                <option value="">Select anchor service…</option>
                                @foreach ($allServices as $svc)
                                    @php $teamCnt = $svc->staff->count(); @endphp
                                    <option value="{{ $svc->id }}" data-duration="{{ $svc->duration }}" data-staff-count="{{ $teamCnt }}">
                                        {{ $svc->name }} ({{ $svc->duration }} min · {{ $teamCnt }} team{{ $teamCnt === 1 ? '' : '' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-3 col-6">
                            <label class="form-label" for="freebiePrice">Package Price (QAR)</label>
                            <input type="number" id="freebiePrice" name="price" class="form-control" step="0.01" min="0" placeholder="299.00" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label" for="freebieChoicesCount">Free Mini-Services</label>
                            <input type="number" id="freebieChoicesCount" name="free_choices_count" class="form-control" step="1" min="1" max="20" value="2" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label" for="freebieValidityDays">Validity (Days)</label>
                            <input type="number" id="freebieValidityDays" name="validity_days" class="form-control" step="1" min="1" max="3650" value="7" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label" for="freebieIsActive">Status</label>
                            <select id="freebieIsActive" name="is_active" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">
                            Eligible Mini-Services Pool
                            <span style="color: #e5b8a8; font-weight: 700;">(<span id="freebieEligibleCount">0</span> selected)</span>
                        </label>
                        <span style="font-size: 11.5px; color: #b8a29a;">Clients redeem at 0.00 QAR within validity window</span>
                    </div>

                    <input type="text" id="freebieServiceSearch" class="form-control form-control-sm mb-2" placeholder="Search mini-services to include in pool…" autocomplete="off">

                    <div class="freebie-svc-picker">
                        @forelse ($allServices as $service)
                            <div class="freebie-svc-row">
                                <label class="freebie-svc-label mb-0" for="freebie-svc-{{ $service->id }}">
                                    <input type="checkbox" class="form-check-input mt-0 freebie-svc-checkbox"
                                        value="{{ $service->id }}"
                                        id="freebie-svc-{{ $service->id }}">
                                    <span style="color: #f8efe6; font-size: 13px; font-weight: 500;">{{ $service->name }}</span>
                                </label>
                                <span class="freebie-svc-meta">{{ $service->duration }} min · {{ number_format($service->price, 2) }} QAR</span>
                            </div>
                        @empty
                            <div class="text-muted small px-2 py-2">No services in the catalog yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm px-3 freebie-save-btn" id="freebieSubmitBtn">Save Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Minimalist Delete Confirmation Modal for Service + Freebies -->
<div class="modal fade" id="freebieDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="background: linear-gradient(180deg, rgba(34, 22, 27, 0.96) 0%, rgba(21, 14, 18, 0.98) 100%); border: 1px solid rgba(229, 184, 168, 0.18); border-radius: 14px; color: #f8efe6;">
            <div class="modal-body p-4">
                <h6 class="fw-bold mb-2" style="color: #f8efe6;">Delete Service + Freebies?</h6>
                <p class="small mb-3" style="color: #b8a29a;" id="freebieDeleteLabel">This package will be permanently removed from the catalog.</p>
                <div id="freebieDeleteError" class="small mb-2 d-none" style="color: #fda4af;"></div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" id="freebieConfirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const modalEl = document.getElementById('freebieModal');
        const deleteModalEl = document.getElementById('freebieDeleteModal');
        if (!modalEl) return;

        function freebieModalInstance() {
            return bootstrap.Modal.getOrCreateInstance(modalEl);
        }
        function deleteModalInstance() {
            return bootstrap.Modal.getOrCreateInstance(deleteModalEl);
        }

        const form = document.getElementById('freebieForm');
        const errorBox = document.getElementById('freebieFormError');
        const searchInput = document.getElementById('freebieServiceSearch');
        let editingId = null;
        let pendingDelete = null;

        function escHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function updateEligibleCount() {
            const count = modalEl.querySelectorAll('.freebie-svc-checkbox:checked').length;
            const countEl = document.getElementById('freebieEligibleCount');
            if (countEl) countEl.textContent = count;
        }
        modalEl.querySelectorAll('.freebie-svc-checkbox').forEach(cb => cb.addEventListener('change', updateEligibleCount));

        function filterServices() {
            const q = (searchInput ? searchInput.value : '').trim().toLowerCase();
            modalEl.querySelectorAll('.freebie-svc-row').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = (!q || text.includes(q)) ? '' : 'none';
            });
        }
        if (searchInput) searchInput.addEventListener('input', filterServices);

        function resetForm() {
            editingId = null;
            document.getElementById('freebieModalTitle').textContent = 'New Service + Freebies Package';
            document.getElementById('freebieSubmitBtn').textContent = 'Save Package';
            form.reset();
            document.getElementById('freebieChoicesCount').value = 2;
            document.getElementById('freebieValidityDays').value = 7;
            document.getElementById('freebieIsActive').value = '1';
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
            modalEl.querySelectorAll('.freebie-svc-checkbox').forEach(cb => { cb.checked = false; });
            if (searchInput) {
                searchInput.value = '';
                filterServices();
            }
            updateEligibleCount();
        }

        const addBtn = document.getElementById('addFreebieBtn');
        if (addBtn) {
            addBtn.addEventListener('click', function() {
                resetForm();
                freebieModalInstance().show();
            });
        }

        window.editFreebiePackage = function(pkg, eligibleIds) {
            resetForm();
            editingId = pkg.id;
            document.getElementById('freebieModalTitle').textContent = 'Edit Service + Freebies Package';
            document.getElementById('freebieSubmitBtn').textContent = 'Update Package';
            document.getElementById('freebieName').value = pkg.name || '';
            document.getElementById('freebieAnchorService').value = pkg.anchor_service_id || '';
            document.getElementById('freebiePrice').value = pkg.price ?? '';
            document.getElementById('freebieChoicesCount').value = pkg.free_choices_count || 2;
            document.getElementById('freebieValidityDays').value = pkg.validity_days || 7;
            document.getElementById('freebieIsActive').value = pkg.is_active ? '1' : '0';

            const idSet = new Set((eligibleIds || []).map(Number));
            modalEl.querySelectorAll('.freebie-svc-checkbox').forEach(cb => {
                cb.checked = idSet.has(Number(cb.value));
            });
            updateEligibleCount();
            freebieModalInstance().show();
        };

        modalEl.addEventListener('hidden.bs.modal', resetForm);

        function renderCardHtml(pkg) {
            const statusClass = pkg.is_active ? '' : 'inactive';
            const statusText = pkg.is_active ? 'active' : 'inactive';
            const poolSize = (pkg.eligible_services || []).length;
            const eligibleNames = (pkg.eligible_services || []).map(s => escHtml(s.name)).join(' · ');
            const staffLabel = pkg.anchor_staff_count > 0
                ? `${pkg.anchor_staff_count} team member${pkg.anchor_staff_count === 1 ? '' : 's'}`
                : 'No team members assigned';

            const editJson = JSON.stringify({
                id: pkg.id,
                name: pkg.name,
                anchor_service_id: pkg.anchor_service_id,
                price: pkg.price,
                free_choices_count: pkg.free_choices_count,
                validity_days: pkg.validity_days,
                is_active: pkg.is_active
            }).replace(/'/g, '&apos;');
            const idsJson = JSON.stringify((pkg.eligible_services || []).map(s => s.id));

            return `
                <div class="combo-card freebie-pkg-card" data-freebie-id="${pkg.id}">
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="combo-name">${escHtml(pkg.name)}</span>
                            <span class="combo-status ${statusClass}">${statusText}</span>
                        </div>
                        <div class="combo-meta mt-1" style="color: #f8efe6; font-weight: 600;">
                            Anchor: ${escHtml(pkg.anchor_service_name)} · ${pkg.anchor_duration} min · ${escHtml(staffLabel)}
                        </div>
                        <div class="combo-meta">
                            Choose ${pkg.free_choices_count} Free (from ${poolSize} eligible) · Valid ${pkg.validity_days} days
                        </div>
                        <div class="combo-services mt-1">${eligibleNames}</div>
                    </div>
                    <div class="text-end shrink-0">
                        <div class="combo-price">${Number(pkg.price).toFixed(2)} QAR</div>
                        <div class="mt-2 d-flex justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-outline-warning freebie-edit-btn"
                                data-pkg='${editJson}'
                                data-eligible-ids='${idsJson}'
                                title="Edit">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger freebie-delete-btn"
                                data-freebie-id="${pkg.id}"
                                data-freebie-name="${escHtml(pkg.name)}"
                                title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
        }

        function bindFreebieCardActions(card) {
            const editBtn = card.querySelector('.freebie-edit-btn');
            if (editBtn) {
                editBtn.addEventListener('click', function() {
                    const pkg = JSON.parse(this.dataset.pkg.replace(/&apos;/g, "'"));
                    const ids = JSON.parse(this.dataset.eligibleIds || '[]');
                    window.editFreebiePackage(pkg, ids);
                });
            }
            const delBtn = card.querySelector('.freebie-delete-btn');
            if (delBtn) {
                delBtn.addEventListener('click', function() {
                    pendingDelete = { id: this.dataset.freebieId, name: this.dataset.freebieName, card };
                    const lbl = document.getElementById('freebieDeleteLabel');
                    if (lbl) lbl.textContent = `Permanently remove "${this.dataset.freebieName}" from the catalog?`;
                    const errEl = document.getElementById('freebieDeleteError');
                    if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                    deleteModalInstance().show();
                });
            }
        }

        document.querySelectorAll('.freebie-pkg-card').forEach(bindFreebieCardActions);

        const confirmDelBtn = document.getElementById('freebieConfirmDeleteBtn');
        if (confirmDelBtn) {
            confirmDelBtn.addEventListener('click', function() {
                if (!pendingDelete) return;
                const { id, card } = pendingDelete;
                confirmDelBtn.disabled = true;

                fetch(`/service-freebie-packages/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                    .then(async r => ({ ok: r.ok, data: await r.json().catch(() => ({})) }))
                    .then(({ ok, data }) => {
                        if (ok && data.success) {
                            card.remove();
                            const list = document.getElementById('freebieList');
                            if (list && !list.querySelector('.freebie-pkg-card')) {
                                list.innerHTML = '<div class="text-center text-muted py-5" id="freebieEmptyState">No Service + Freebies packages yet.</div>';
                            }
                            deleteModalInstance().hide();
                            pendingDelete = null;
                        } else {
                            const errEl = document.getElementById('freebieDeleteError');
                            if (errEl) {
                                errEl.textContent = data.message || 'Unable to delete package.';
                                errEl.classList.remove('d-none');
                            }
                        }
                    })
                    .finally(() => { confirmDelBtn.disabled = false; });
            });
        }

        function upsertFreebieCard(pkg) {
            const list = document.getElementById('freebieList');
            if (!list) return;
            document.getElementById('freebieEmptyState')?.remove();

            const existing = list.querySelector(`.freebie-pkg-card[data-freebie-id="${pkg.id}"]`);
            const wrap = document.createElement('div');
            wrap.innerHTML = renderCardHtml(pkg).trim();
            const newCard = wrap.firstElementChild;
            bindFreebieCardActions(newCard);

            if (existing) {
                existing.replaceWith(newCard);
            } else {
                list.prepend(newCard);
            }
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            errorBox.classList.add('d-none');

            const eligibleIds = Array.from(modalEl.querySelectorAll('.freebie-svc-checkbox:checked')).map(cb => Number(cb.value));
            if (!eligibleIds.length) {
                errorBox.textContent = 'Select at least one eligible mini-service for this package.';
                errorBox.classList.remove('d-none');
                return;
            }

            const payload = {
                name: document.getElementById('freebieName').value,
                anchor_service_id: Number(document.getElementById('freebieAnchorService').value),
                price: document.getElementById('freebiePrice').value,
                free_choices_count: Number(document.getElementById('freebieChoicesCount').value || 2),
                validity_days: Number(document.getElementById('freebieValidityDays').value || 7),
                is_active: document.getElementById('freebieIsActive').value === '1',
                eligible_service_ids: eligibleIds
            };

            const url = editingId ? `/service-freebie-packages/${editingId}` : '/service-freebie-packages';
            const submitBtn = document.getElementById('freebieSubmitBtn');
            submitBtn.disabled = true;

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
                    const data = await r.json().catch(() => ({}));
                    if (!r.ok) throw data;
                    return data;
                })
                .then(data => {
                    upsertFreebieCard(data.package);
                    freebieModalInstance().hide();
                })
                .catch(err => {
                    const msg = err && err.errors
                        ? Object.values(err.errors).flat().join(' ')
                        : (err && err.message ? err.message : 'Could not save the Service + Freebies package.');
                    errorBox.textContent = msg;
                    errorBox.classList.remove('d-none');
                })
                .finally(() => { submitBtn.disabled = false; });
        });
    })();
</script>
