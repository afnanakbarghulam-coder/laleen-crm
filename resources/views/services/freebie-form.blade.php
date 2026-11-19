@php
    $masterFreebieServices = ($allServices ?? collect())->map(function ($svc) {
        return [
            'id' => (int) $svc->id,
            'name' => (string) $svc->name,
            'category' => (string) ($svc->category?->name ?? 'Uncategorized'),
            'duration' => (int) ($svc->duration ?: 30),
            'price' => round((float) ($svc->price ?? 0), 2),
            'staff_count' => $svc->relationLoaded('staff') ? $svc->staff->count() : $svc->staff()->count(),
        ];
    })->values();
@endphp

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

    /* Custom Dark Rose/Blush Searchable Combobox for Main Anchor Service */
    #freebieModal .freebie-anchor-combo {
        position: relative;
    }

    #freebieModal .freebie-anchor-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
    }

    #freebieModal .freebie-anchor-search-icon {
        position: absolute;
        left: 11px;
        color: #b8a29a;
        font-size: 14px;
        pointer-events: none;
    }

    #freebieModal .freebie-anchor-input {
        width: 100%;
        padding: 7px 62px 7px 32px;
        border-radius: 8px;
        border: 1px solid rgba(229, 184, 168, 0.22);
        background: rgba(21, 14, 18, 0.85);
        color: #f8efe6;
        font-size: 13.5px;
        line-height: 1.4;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    #freebieModal .freebie-anchor-input::placeholder {
        color: rgba(184, 162, 154, 0.65);
    }

    #freebieModal .freebie-anchor-input:focus {
        outline: none;
        border-color: rgba(229, 184, 168, 0.58);
        box-shadow: 0 0 0 3px rgba(217, 143, 131, 0.16);
    }

    #freebieModal .freebie-anchor-controls {
        position: absolute;
        right: 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    #freebieModal .freebie-anchor-clear-btn,
    #freebieModal .freebie-anchor-toggle-btn {
        border: none;
        background: transparent;
        color: #b8a29a;
        width: 22px;
        height: 22px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        font-size: 15px;
        line-height: 1;
        transition: background 0.12s ease, color 0.12s ease;
    }

    #freebieModal .freebie-anchor-clear-btn:hover,
    #freebieModal .freebie-anchor-toggle-btn:hover {
        background: rgba(229, 184, 168, 0.14);
        color: #f8efe6;
    }

    #freebieModal .freebie-anchor-selected-meta {
        margin-top: 5px;
        font-size: 11.5px;
        color: #e5b8a8;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    #freebieModal .freebie-anchor-dropdown {
        position: absolute;
        top: calc(100% + 5px);
        left: 0;
        right: 0;
        z-index: 1080;
        max-height: 250px;
        overflow-y: auto;
        background: linear-gradient(180deg, #271a1f 0%, #191115 100%);
        border: 1px solid rgba(229, 184, 168, 0.28);
        border-radius: 10px;
        box-shadow: 0 14px 34px rgba(0, 0, 0, 0.55);
        padding: 4px 0;
    }

    #freebieModal .freebie-anchor-option {
        width: 100%;
        border: none;
        background: transparent;
        text-align: left;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        cursor: pointer;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        transition: background 0.12s ease;
    }

    #freebieModal .freebie-anchor-option:last-child {
        border-bottom: none;
    }

    #freebieModal .freebie-anchor-option:hover,
    #freebieModal .freebie-anchor-option.is-highlighted {
        background: rgba(229, 184, 168, 0.11);
    }

    #freebieModal .freebie-anchor-option.is-selected {
        background: rgba(229, 184, 168, 0.16);
    }

    #freebieModal .freebie-anchor-opt-name {
        color: #f8efe6;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.25;
    }

    #freebieModal .freebie-anchor-opt-cat {
        color: #b8a29a;
        font-size: 11px;
        margin-top: 2px;
    }

    #freebieModal .freebie-anchor-opt-right {
        text-align: right;
        flex-shrink: 0;
    }

    #freebieModal .freebie-anchor-opt-price {
        color: #e5b8a8;
        font-size: 12.5px;
        font-weight: 700;
        white-space: nowrap;
    }

    #freebieModal .freebie-anchor-opt-dur {
        color: #b8a29a;
        font-size: 11px;
        white-space: nowrap;
    }

    /* Eligible Mini-Services Pool */
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
        transition: opacity 0.14s ease, background 0.12s ease;
    }

    #freebieModal .freebie-svc-row:last-child {
        border-bottom: none;
    }

    #freebieModal .freebie-svc-row:hover:not(.is-anchor-excluded) {
        background: rgba(229, 184, 168, 0.05);
    }

    #freebieModal .freebie-svc-row.is-anchor-excluded {
        opacity: 0.45;
    }

    #freebieModal .freebie-svc-row.is-anchor-excluded .freebie-svc-label {
        cursor: not-allowed;
    }

    #freebieModal .freebie-svc-label {
        display: flex;
        align-items: center;
        gap: 9px;
        min-width: 0;
        cursor: pointer;
    }

    #freebieModal .freebie-svc-cat-tag {
        font-size: 11px;
        color: #b8a29a;
        margin-left: 4px;
    }

    #freebieModal .freebie-anchor-excluded-pill {
        display: inline-flex;
        align-items: center;
        padding: 1px 7px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: #e5b8a8;
        background: rgba(229, 184, 168, 0.14);
        margin-left: 6px;
        white-space: nowrap;
    }

    #freebieModal .freebie-svc-meta {
        font-size: 11.5px;
        color: #b8a29a;
        white-space: nowrap;
        flex-shrink: 0;
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
                        <div class="col-md-6">
                            <label class="form-label" for="freebieName">Package Name</label>
                            <input type="text" id="freebieName" name="name" class="form-control" placeholder="e.g. Hair Color + 2 Free Services" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="freebieAnchorSearch">Main Anchor Service</label>
                            <input type="hidden" id="freebieAnchorService" name="anchor_service_id" value="">
                            <div class="freebie-anchor-combo" id="freebieAnchorCombo">
                                <div class="freebie-anchor-input-wrap">
                                    <i class="bx bx-search freebie-anchor-search-icon" aria-hidden="true"></i>
                                    <input type="text"
                                        id="freebieAnchorSearch"
                                        class="freebie-anchor-input"
                                        placeholder="Search service or category…"
                                        autocomplete="off"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-expanded="false"
                                        aria-controls="freebieAnchorDropdown">
                                    <div class="freebie-anchor-controls">
                                        <button type="button" id="freebieAnchorClearBtn" class="freebie-anchor-clear-btn d-none" title="Clear anchor service" aria-label="Clear anchor service">&times;</button>
                                        <button type="button" id="freebieAnchorToggleBtn" class="freebie-anchor-toggle-btn" title="Toggle services list" aria-label="Toggle services list">
                                            <i class="bx bx-chevron-down"></i>
                                        </button>
                                    </div>
                                </div>
                                <div id="freebieAnchorSelectedMeta" class="freebie-anchor-selected-meta d-none"></div>
                                <div id="freebieAnchorDropdown" class="freebie-anchor-dropdown d-none" role="listbox"></div>
                            </div>
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

                    <input type="text" id="freebieServiceSearch" class="form-control form-control-sm mb-2" placeholder="Search mini-services by name or category…" autocomplete="off">

                    <div class="freebie-svc-picker" id="freebieSvcPicker">
                        @forelse ($masterFreebieServices as $service)
                            <div class="freebie-svc-row"
                                data-service-id="{{ $service['id'] }}"
                                data-search-text="{{ strtolower($service['name'] . ' ' . $service['category']) }}">
                                <label class="freebie-svc-label mb-0" for="freebie-svc-{{ $service['id'] }}">
                                    <input type="checkbox" class="form-check-input mt-0 freebie-svc-checkbox"
                                        value="{{ $service['id'] }}"
                                        id="freebie-svc-{{ $service['id'] }}">
                                    <span class="text-truncate">
                                        <span style="color: #f8efe6; font-size: 13px; font-weight: 500;">{{ $service['name'] }}</span>
                                        <span class="freebie-svc-cat-tag">· {{ $service['category'] }}</span>
                                        <span class="freebie-anchor-excluded-pill d-none">Anchor service</span>
                                    </span>
                                </label>
                                <span class="freebie-svc-meta">{{ $service['duration'] }} min · {{ number_format($service['price'], 2) }} QAR</span>
                            </div>
                        @empty
                            <div class="text-muted small px-2 py-2">No services in the catalog yet.</div>
                        @endforelse
                        <div class="text-muted small px-2 py-3 text-center d-none" id="freebiePoolNoMatch">No services match your search.</div>
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
        const MASTER_SERVICES = @json($masterFreebieServices);
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
        const anchorCombo = document.getElementById('freebieAnchorCombo');
        const anchorHiddenInput = document.getElementById('freebieAnchorService');
        const anchorSearchInput = document.getElementById('freebieAnchorSearch');
        const anchorClearBtn = document.getElementById('freebieAnchorClearBtn');
        const anchorToggleBtn = document.getElementById('freebieAnchorToggleBtn');
        const anchorSelectedMeta = document.getElementById('freebieAnchorSelectedMeta');
        const anchorDropdown = document.getElementById('freebieAnchorDropdown');
        const poolNoMatch = document.getElementById('freebiePoolNoMatch');

        let editingId = null;
        let pendingDelete = null;
        let selectedAnchorId = null;
        let anchorDropdownOpen = false;
        let anchorHighlightIdx = -1;
        let filteredAnchorOptions = [];

        function escHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function findMasterService(id) {
            const num = Number(id);
            if (!num) return null;
            return MASTER_SERVICES.find(s => Number(s.id) === num) || null;
        }

        /* ---------------- ANCHOR EXCLUSION GUARD ON ELIGIBLE POOL ---------------- */
        function syncAnchorExclusionInPool() {
            const anchorIdNum = selectedAnchorId ? Number(selectedAnchorId) : 0;
            modalEl.querySelectorAll('.freebie-svc-row').forEach(row => {
                const rowSvcId = Number(row.dataset.serviceId);
                const cb = row.querySelector('.freebie-svc-checkbox');
                const pill = row.querySelector('.freebie-anchor-excluded-pill');
                const isAnchor = anchorIdNum > 0 && rowSvcId === anchorIdNum;

                row.classList.toggle('is-anchor-excluded', isAnchor);
                if (pill) pill.classList.toggle('d-none', !isAnchor);
                if (cb) {
                    if (isAnchor && cb.checked) {
                        cb.checked = false;
                    }
                    cb.disabled = isAnchor;
                }
            });
            updateEligibleCount();
        }

        /* ---------------- SEARCHABLE ANCHOR COMBOBOX ---------------- */
        function setSelectedAnchor(serviceId) {
            const svc = findMasterService(serviceId);
            selectedAnchorId = svc ? Number(svc.id) : null;
            anchorHiddenInput.value = svc ? String(svc.id) : '';

            if (svc) {
                anchorSearchInput.value = svc.name;
                anchorClearBtn.classList.remove('d-none');
                const teamText = svc.staff_count > 0
                    ? `${svc.staff_count} team member${svc.staff_count === 1 ? '' : 's'}`
                    : 'No team assigned';
                anchorSelectedMeta.innerHTML = `
                    <span>${escHtml(svc.category)} · ${svc.duration} min · ${escHtml(teamText)}</span>
                    <span style="font-weight:700; color:#f8efe6;">${Number(svc.price).toFixed(2)} QAR</span>
                `;
                anchorSelectedMeta.classList.remove('d-none');
            } else {
                anchorSearchInput.value = '';
                anchorClearBtn.classList.add('d-none');
                anchorSelectedMeta.innerHTML = '';
                anchorSelectedMeta.classList.add('d-none');
            }

            syncAnchorExclusionInPool();
        }

        function renderAnchorOptions(query) {
            const q = String(query || '').trim().toLowerCase();
            const currentAnchor = findMasterService(selectedAnchorId);
            const effectiveQuery = (currentAnchor && q === currentAnchor.name.toLowerCase()) ? '' : q;

            filteredAnchorOptions = MASTER_SERVICES.filter(s => {
                if (!effectiveQuery) return true;
                return s.name.toLowerCase().includes(effectiveQuery)
                    || s.category.toLowerCase().includes(effectiveQuery);
            });

            if (!filteredAnchorOptions.length) {
                anchorDropdown.innerHTML = '<div class="px-3 py-3 text-center small" style="color:#b8a29a;">No matching services in catalog.</div>';
                anchorHighlightIdx = -1;
                return;
            }

            if (anchorHighlightIdx >= filteredAnchorOptions.length) {
                anchorHighlightIdx = 0;
            }

            anchorDropdown.innerHTML = filteredAnchorOptions.map((s, idx) => {
                const isSelected = selectedAnchorId && Number(s.id) === Number(selectedAnchorId);
                const isHighlighted = idx === anchorHighlightIdx;
                return `
                    <button type="button"
                        class="freebie-anchor-option ${isSelected ? 'is-selected' : ''} ${isHighlighted ? 'is-highlighted' : ''}"
                        data-anchor-id="${s.id}"
                        data-opt-idx="${idx}"
                        role="option"
                        aria-selected="${isSelected ? 'true' : 'false'}">
                        <div class="min-w-0">
                            <div class="freebie-anchor-opt-name text-truncate">${escHtml(s.name)}</div>
                            <div class="freebie-anchor-opt-cat">${escHtml(s.category)}</div>
                        </div>
                        <div class="freebie-anchor-opt-right">
                            <div class="freebie-anchor-opt-price">${Number(s.price).toFixed(2)} QAR</div>
                            <div class="freebie-anchor-opt-dur">${s.duration} min</div>
                        </div>
                    </button>`;
            }).join('');

            anchorDropdown.querySelectorAll('.freebie-anchor-option').forEach(btn => {
                btn.addEventListener('mousedown', function(ev) {
                    ev.preventDefault();
                    setSelectedAnchor(Number(this.dataset.anchorId));
                    closeAnchorDropdown();
                });
            });
        }

        function openAnchorDropdown(queryOverride) {
            anchorDropdownOpen = true;
            anchorHighlightIdx = -1;
            renderAnchorOptions(queryOverride !== undefined ? queryOverride : anchorSearchInput.value);
            anchorDropdown.classList.remove('d-none');
            anchorSearchInput.setAttribute('aria-expanded', 'true');
        }

        function closeAnchorDropdown() {
            anchorDropdownOpen = false;
            anchorHighlightIdx = -1;
            anchorDropdown.classList.add('d-none');
            anchorSearchInput.setAttribute('aria-expanded', 'false');
            const currentSvc = findMasterService(selectedAnchorId);
            anchorSearchInput.value = currentSvc ? currentSvc.name : '';
        }

        anchorSearchInput.addEventListener('focus', () => openAnchorDropdown(''));
        anchorSearchInput.addEventListener('input', () => {
            if (selectedAnchorId) {
                const currentSvc = findMasterService(selectedAnchorId);
                if (!currentSvc || anchorSearchInput.value.trim() !== currentSvc.name) {
                    selectedAnchorId = null;
                    anchorHiddenInput.value = '';
                    anchorSelectedMeta.classList.add('d-none');
                    anchorClearBtn.classList.toggle('d-none', !anchorSearchInput.value.trim());
                    syncAnchorExclusionInPool();
                }
            } else {
                anchorClearBtn.classList.toggle('d-none', !anchorSearchInput.value.trim());
            }
            openAnchorDropdown(anchorSearchInput.value);
        });

        anchorSearchInput.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!anchorDropdownOpen) {
                    openAnchorDropdown(anchorSearchInput.value);
                }
                if (filteredAnchorOptions.length) {
                    anchorHighlightIdx = (anchorHighlightIdx + 1) % filteredAnchorOptions.length;
                    renderAnchorOptions(anchorSearchInput.value);
                    anchorDropdown.querySelector('.is-highlighted')?.scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (filteredAnchorOptions.length) {
                    anchorHighlightIdx = anchorHighlightIdx <= 0 ? filteredAnchorOptions.length - 1 : anchorHighlightIdx - 1;
                    renderAnchorOptions(anchorSearchInput.value);
                    anchorDropdown.querySelector('.is-highlighted')?.scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter' && anchorDropdownOpen) {
                e.preventDefault();
                const target = filteredAnchorOptions[anchorHighlightIdx >= 0 ? anchorHighlightIdx : 0];
                if (target) {
                    setSelectedAnchor(target.id);
                    closeAnchorDropdown();
                }
            } else if (e.key === 'Escape' && anchorDropdownOpen) {
                e.preventDefault();
                closeAnchorDropdown();
            }
        });

        anchorToggleBtn.addEventListener('click', function() {
            if (anchorDropdownOpen) {
                closeAnchorDropdown();
            } else {
                anchorSearchInput.focus();
                openAnchorDropdown('');
            }
        });

        anchorClearBtn.addEventListener('click', function() {
            setSelectedAnchor(null);
            anchorSearchInput.focus();
            openAnchorDropdown('');
        });

        document.addEventListener('click', function(e) {
            if (anchorDropdownOpen && anchorCombo && !anchorCombo.contains(e.target)) {
                closeAnchorDropdown();
            }
        });

        /* ---------------- ELIGIBLE MINI-SERVICES POOL ---------------- */
        function updateEligibleCount() {
            const count = modalEl.querySelectorAll('.freebie-svc-checkbox:checked:not(:disabled)').length;
            const countEl = document.getElementById('freebieEligibleCount');
            if (countEl) countEl.textContent = count;
        }
        modalEl.querySelectorAll('.freebie-svc-checkbox').forEach(cb => cb.addEventListener('change', updateEligibleCount));

        function filterServices() {
            const q = (searchInput ? searchInput.value : '').trim().toLowerCase();
            let visibleCount = 0;
            modalEl.querySelectorAll('.freebie-svc-row').forEach(row => {
                const haystack = (row.dataset.searchText || row.textContent || '').toLowerCase();
                const matches = !q || haystack.includes(q);
                row.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });
            if (poolNoMatch) {
                poolNoMatch.classList.toggle('d-none', visibleCount > 0 || MASTER_SERVICES.length === 0);
            }
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
            setSelectedAnchor(null);
            closeAnchorDropdown();
            modalEl.querySelectorAll('.freebie-svc-checkbox').forEach(cb => {
                cb.checked = false;
                cb.disabled = false;
            });
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
            document.getElementById('freebiePrice').value = pkg.price ?? '';
            document.getElementById('freebieChoicesCount').value = pkg.free_choices_count || 2;
            document.getElementById('freebieValidityDays').value = pkg.validity_days || 7;
            document.getElementById('freebieIsActive').value = pkg.is_active ? '1' : '0';

            const idSet = new Set((eligibleIds || []).map(Number));
            modalEl.querySelectorAll('.freebie-svc-checkbox').forEach(cb => {
                cb.checked = idSet.has(Number(cb.value));
            });

            setSelectedAnchor(pkg.anchor_service_id || null);
            closeAnchorDropdown();
            freebieModalInstance().show();
        };

        modalEl.addEventListener('hidden.bs.modal', resetForm);

        function renderCardHtml(pkg) {
            const statusClass = pkg.is_active ? '' : 'inactive';
            const statusText = pkg.is_active ? 'active' : 'inactive';
            const poolSize = (pkg.eligible_services || []).length;
            const eligibleNames = (pkg.eligible_services || [])
                .map(s => `${escHtml(s.name)} (${Number(s.price || 0).toFixed(2)} QAR)`)
                .join(' · ');
            const staffLabel = pkg.anchor_staff_count > 0
                ? `${pkg.anchor_staff_count} team member${pkg.anchor_staff_count === 1 ? '' : 's'}`
                : 'No team members assigned';
            const catHtml = pkg.anchor_category_name
                ? ` <span style="color: #b8a29a; font-weight: 400;">(${escHtml(pkg.anchor_category_name)})</span>`
                : '';

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
                            Anchor: ${escHtml(pkg.anchor_service_name)}${catHtml} · ${pkg.anchor_duration} min · ${Number(pkg.anchor_price || 0).toFixed(2)} QAR · ${escHtml(staffLabel)}
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

            const anchorId = Number(anchorHiddenInput.value);
            if (!anchorId) {
                errorBox.textContent = 'Please select a Main Anchor Service from the catalog.';
                errorBox.classList.remove('d-none');
                anchorSearchInput.focus();
                openAnchorDropdown('');
                return;
            }

            const eligibleIds = Array.from(modalEl.querySelectorAll('.freebie-svc-checkbox:checked:not(:disabled)'))
                .map(cb => Number(cb.value))
                .filter(id => id !== anchorId);

            if (!eligibleIds.length) {
                errorBox.textContent = 'Select at least one eligible mini-service for this package.';
                errorBox.classList.remove('d-none');
                return;
            }

            const payload = {
                name: document.getElementById('freebieName').value,
                anchor_service_id: anchorId,
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
