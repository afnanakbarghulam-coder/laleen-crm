@php
    $formRegion = strtolower((string) ($region ?? request('region', 'pk')));
    $formCurrency = $currency ?? ($formRegion === 'qa' ? 'QAR' : 'PKR');
@endphp

<div class="modal fade laleen-ma-modal" id="monthlyAdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 900px;">
        <div class="modal-content">
            <form method="POST" id="monthlyAdForm" action="{{ route('ecommerce.marketing.ads-plan.store') }}">
                @csrf
                <input type="hidden" name="_method" id="monthlyAdFormMethod">
                <input type="hidden" name="region" value="{{ $formRegion }}">

                <div class="modal-header">
                    <h5 class="modal-title" id="monthlyAdModalTitle">Add Ad</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    {{-- Zone 1: Campaign & Offer Brief --}}
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="laleen-ma-lbl" for="maAdName">Ad / Campaign Name *</label>
                            <input type="text" name="ad_name" id="maAdName" class="form-control form-control-sm laleen-ma-input" required>
                        </div>
                        <div class="col-md-3">
                            <label class="laleen-ma-lbl" for="maPlanMonth">Plan Month *</label>
                            <input type="month" name="plan_month" id="maPlanMonth" class="form-control form-control-sm laleen-ma-input" required>
                        </div>
                        <div class="col-md-4">
                            <label class="laleen-ma-lbl" for="maPersonResponsible">Person Responsible *</label>
                            <select name="person_responsible" id="maPersonResponsible" class="form-select form-select-sm laleen-ma-input" required>
                                <option value="">— Select —</option>
                                @foreach ($staff as $member)
                                    <option value="{{ $member->name }}">{{ $member->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="laleen-ma-lbl" for="maTargetBranch">Target Branch</label>
                            <select name="target_branch" id="maTargetBranch" class="form-select form-select-sm laleen-ma-input">
                                @foreach (\App\Models\EcommerceMonthlyAd::BRANCHES as $branch)
                                    <option value="{{ $branch }}">{{ $branch }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="laleen-ma-lbl" for="maTotalBudget">Planned Budget ({{ $formCurrency }})</label>
                            <input type="number" step="0.01" min="0" name="total_budget" id="maTotalBudget" class="form-control form-control-sm laleen-ma-input" placeholder="0.00">
                        </div>
                        <div class="col-md-3">
                            <label class="laleen-ma-lbl" for="maOfferPrice">Ticket / Offer Price ({{ $formCurrency }})</label>
                            <input type="number" step="0.01" min="0" name="offer_price" id="maOfferPrice" class="form-control form-control-sm laleen-ma-input" placeholder="e.g. 180">
                        </div>
                        <div class="col-md-2">
                            <label class="laleen-ma-lbl" for="maLiveDate">Live Date</label>
                            <input type="date" name="live_date" id="maLiveDate" class="form-control form-control-sm laleen-ma-input">
                        </div>

                        <div class="col-md-6">
                            <label class="laleen-ma-lbl" for="maOffer">Offer Summary</label>
                            <input type="text" name="offer" id="maOffer" class="form-control form-control-sm laleen-ma-input" placeholder="What offer does this campaign promote?">
                        </div>
                        <div class="col-md-6">
                            <label class="laleen-ma-lbl" for="maPreFilledMessage">Pre-Filled Message / Caption</label>
                            <input type="text" name="pre_filled_message" id="maPreFilledMessage" class="form-control form-control-sm laleen-ma-input" placeholder="Ready-to-paste caption or WhatsApp greeting">
                        </div>
                    </div>

                    {{-- Zone 2: Dynamic Unlimited Creatives --}}
                    <div class="laleen-ma-section-divider my-3"></div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold" style="color: #f8efe6; font-size: 0.86rem;">Creatives</span>
                            <span class="laleen-ma-count-chip" id="maCreativesCountChip">1 Creative</span>
                        </div>
                        <button type="button" class="btn btn-sm laleen-ma-ghost-btn" id="maAddCreativeBtn">
                            + Add Creative
                        </button>
                    </div>

                    <div id="maCreativesList" class="laleen-ma-form-creatives"></div>

                    {{-- Zone 3: Creative Deadline --}}
                    <div class="laleen-ma-section-divider my-3"></div>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="laleen-ma-lbl" for="maFinalCreativeDeadline">Creative Deadline</label>
                            <input type="date" name="final_creative_deadline" id="maFinalCreativeDeadline" class="form-control form-control-sm laleen-ma-input">
                        </div>
                    </div>

                    {{-- Zone 4: Stage-Gated Testing Metrics (Testing / Scaling / Killed only) --}}
                    <div id="maTestingMetricsSection" class="d-none">
                        <div class="laleen-ma-section-divider my-3"></div>

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-semibold" style="color: #f8efe6; font-size: 0.86rem;">Testing Metrics</span>
                        </div>

                        <div class="row g-2">
                            <div class="col-6 col-md-3">
                                <label class="laleen-ma-lbl" for="maAggressiveReaction">Aggressive Audience Reaction?</label>
                                <select name="aggressive_reaction" id="maAggressiveReaction" class="form-select form-select-sm laleen-ma-input" data-testing-input>
                                    <option value="">— Select —</option>
                                    <option value="yes">Yes</option>
                                    <option value="no">No</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="laleen-ma-lbl" for="maCpm">CPM ({{ $formCurrency }})</label>
                                <input type="number" step="0.01" min="0" name="cpm" id="maCpm" class="form-control form-control-sm laleen-ma-input" placeholder="0.00" data-testing-input>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="laleen-ma-lbl" for="maCtr">CTR (%)</label>
                                <input type="number" step="0.01" min="0" max="100" name="ctr" id="maCtr" class="form-control form-control-sm laleen-ma-input" placeholder="0.00" data-testing-input>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="laleen-ma-lbl" for="maLeadConversionStatus">Lead Conversion Status</label>
                                <select name="lead_conversion_status" id="maLeadConversionStatus" class="form-select form-select-sm laleen-ma-input" data-testing-input>
                                    <option value="">— Select —</option>
                                    <option value="yes">Yes</option>
                                    <option value="no">No</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-sm laleen-ma-ghost-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm laleen-ma-rose-btn">Save Ad</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .laleen-ma-modal .modal-content {
        background: linear-gradient(180deg, #22161b 0%, #150e12 100%);
        border: 1px solid rgba(229, 184, 168, 0.2);
        border-radius: 16px;
        color: #f8efe6;
    }
    .laleen-ma-modal .modal-header,
    .laleen-ma-modal .modal-footer {
        border-color: rgba(255, 255, 255, 0.07);
        padding: 13px 20px;
    }
    .laleen-ma-modal .modal-body { padding: 16px 20px; }
    .laleen-ma-lbl { display: block; font-size: 0.72rem; font-weight: 500; color: #b8a29a; margin-bottom: 3px; }
    .laleen-ma-input {
        background-color: #120b0f !important;
        border: 1px solid rgba(229, 184, 168, 0.18) !important;
        color: #f8efe6 !important;
        border-radius: 8px;
        font-size: 0.8rem;
        color-scheme: dark;
    }
    .laleen-ma-input:focus {
        border-color: #d98f83 !important;
        box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.16) !important;
    }
    .laleen-ma-input option { background-color: #191115; color: #f8efe6; }
    .laleen-ma-size-select { padding-right: 1.65rem !important; }
    .laleen-ma-section-divider { height: 1px; background: rgba(255, 255, 255, 0.07); }
    .laleen-ma-form-creatives { display: flex; flex-direction: column; }
    .laleen-ma-form-crow { padding: 10px 0; border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
    .laleen-ma-form-crow:first-child { padding-top: 2px; }
    .laleen-ma-form-crow:last-child { border-bottom: none; padding-bottom: 2px; }
    .laleen-ma-crow-label { font-size: 0.74rem; font-weight: 600; color: #e5b8a8; }
    .laleen-ma-crow-remove {
        background: transparent;
        border: none;
        color: #9e8981;
        font-size: 1rem;
        line-height: 1;
        padding: 0 4px;
        cursor: pointer;
    }
    .laleen-ma-crow-remove:hover { color: #fda4af; }
    .laleen-ma-url-preview {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        border: 1px solid rgba(229, 184, 168, 0.2);
        border-left: none;
        border-radius: 0 8px 8px 0;
        background: rgba(229, 184, 168, 0.08);
        color: #d4a373 !important;
        text-decoration: none !important;
        font-size: 0.78rem;
        font-weight: 600;
        padding: 0 8px;
    }
    .laleen-ma-url-preview.is-empty { opacity: 0.35; pointer-events: none; }
    .laleen-ma-url-preview:hover { background: rgba(229, 184, 168, 0.18); color: #f8efe6 !important; }
</style>

<script>
(function () {
    const DEFAULT_SIZE_BY_TYPE = { reel: '9:16', story: '9:16', post: '4:5' };
    let isEditMode = false;

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function isValidHttpUrl(val) {
        if (!val) return false;
        try {
            const u = new URL(val.trim());
            return u.protocol === 'http:' || u.protocol === 'https:';
        } catch {
            return false;
        }
    }

    function createCreativeRowElement(data = {}, index = 0) {
        const type = ['reel', 'story', 'post'].includes(data.creative_type) ? data.creative_type : 'reel';
        const media = ['video', 'image'].includes(data.media_type) ? data.media_type : 'video';
        const defaultSize = DEFAULT_SIZE_BY_TYPE[type] || '9:16';
        const size = ['9:16', '4:5', '1:1', '16:9'].includes(data.size) ? data.size : defaultSize;
        const sizeWasCustom = Boolean(data.size && data.size !== defaultSize);
        const name = data.name || '';
        const refUrl = data.reference_url || '';
        const requireName = !isEditMode || Boolean(name || refUrl);

        const row = document.createElement('div');
        row.className = 'laleen-ma-form-crow';
        row.setAttribute('data-ma-crow', '');

        row.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="laleen-ma-crow-label" data-crow-heading>Creative ${index + 1}</span>
                <button type="button" class="laleen-ma-crow-remove" data-crow-remove title="Remove creative" aria-label="Remove creative">&times;</button>
            </div>
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-3">
                    <input type="text"
                           class="form-control form-control-sm laleen-ma-input"
                           data-crow-field="name"
                           value="${escapeHtml(name)}"
                           placeholder="Creative ${index + 1} — Angle / Hook"
                           ${requireName ? 'required' : ''}>
                </div>
                <div class="col-4 col-md-2">
                    <select class="form-select form-select-sm laleen-ma-input" data-crow-field="creative_type" aria-label="Creative Type">
                        <option value="reel" ${type === 'reel' ? 'selected' : ''}>Reel</option>
                        <option value="story" ${type === 'story' ? 'selected' : ''}>Story</option>
                        <option value="post" ${type === 'post' ? 'selected' : ''}>Post</option>
                    </select>
                </div>
                <div class="col-4 col-md-2">
                    <select class="form-select form-select-sm laleen-ma-input" data-crow-field="media_type" aria-label="Media Format">
                        <option value="video" ${media === 'video' ? 'selected' : ''}>Video</option>
                        <option value="image" ${media === 'image' ? 'selected' : ''}>Image</option>
                    </select>
                </div>
                <div class="col-4 col-md-3">
                    <select class="form-select form-select-sm laleen-ma-input laleen-ma-size-select"
                            data-crow-field="size"
                            data-size-touched="${sizeWasCustom ? '1' : '0'}"
                            aria-label="Creative Size">
                        <option value="9:16" ${size === '9:16' ? 'selected' : ''}>9:16 (1080×1920)</option>
                        <option value="4:5" ${size === '4:5' ? 'selected' : ''}>4:5 (1080×1350)</option>
                        <option value="1:1" ${size === '1:1' ? 'selected' : ''}>1:1 (1080×1080)</option>
                        <option value="16:9" ${size === '16:9' ? 'selected' : ''}>16:9 (1920×1080)</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <div class="d-flex">
                        <input type="url"
                               class="form-control form-control-sm laleen-ma-input"
                               style="border-top-right-radius: 0; border-bottom-right-radius: 0;"
                               data-crow-field="reference_url"
                               value="${escapeHtml(refUrl)}"
                               placeholder="https://...">
                        <a href="${isValidHttpUrl(refUrl) ? escapeHtml(refUrl) : '#'}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="laleen-ma-url-preview ${isValidHttpUrl(refUrl) ? '' : 'is-empty'}"
                           data-crow-preview
                           title="Open reference link">↗</a>
                    </div>
                </div>
            </div>
        `;

        const typeSel = row.querySelector('[data-crow-field="creative_type"]');
        const sizeSel = row.querySelector('[data-crow-field="size"]');
        const nameInput = row.querySelector('[data-crow-field="name"]');
        const urlInput = row.querySelector('[data-crow-field="reference_url"]');
        const previewLink = row.querySelector('[data-crow-preview]');
        const removeBtn = row.querySelector('[data-crow-remove]');

        sizeSel.addEventListener('change', () => {
            sizeSel.dataset.sizeTouched = '1';
        });

        typeSel.addEventListener('change', () => {
            if (sizeSel.dataset.sizeTouched !== '1') {
                sizeSel.value = DEFAULT_SIZE_BY_TYPE[typeSel.value] || '9:16';
            }
        });

        urlInput.addEventListener('input', () => {
            const raw = urlInput.value.trim();
            if (isValidHttpUrl(raw)) {
                previewLink.href = raw;
                previewLink.classList.remove('is-empty');
            } else {
                previewLink.href = '#';
                previewLink.classList.add('is-empty');
            }
            if (raw !== '') {
                nameInput.required = true;
            } else if (isEditMode && nameInput.value.trim() === '') {
                nameInput.required = false;
            }
        });

        removeBtn.addEventListener('click', () => {
            const list = document.getElementById('maCreativesList');
            if (list && list.querySelectorAll('[data-ma-crow]').length > 1) {
                row.remove();
                reindexCreativeRows();
            }
        });

        return row;
    }

    function reindexCreativeRows() {
        const list = document.getElementById('maCreativesList');
        if (!list) return;
        const rows = Array.from(list.querySelectorAll('[data-ma-crow]'));

        rows.forEach((row, idx) => {
            const num = idx + 1;
            const heading = row.querySelector('[data-crow-heading]');
            if (heading) heading.textContent = `Creative ${num}`;

            const nameInput = row.querySelector('[data-crow-field="name"]');
            if (nameInput) nameInput.placeholder = `Creative ${num} — Angle / Hook`;

            row.querySelectorAll('[data-crow-field]').forEach((el) => {
                const field = el.getAttribute('data-crow-field');
                el.name = `creatives[${idx}][${field}]`;
            });

            const removeBtn = row.querySelector('[data-crow-remove]');
            if (removeBtn) {
                removeBtn.classList.toggle('d-none', rows.length <= 1);
            }
        });

        const chip = document.getElementById('maCreativesCountChip');
        if (chip) {
            chip.textContent = `${rows.length} ${rows.length === 1 ? 'Creative' : 'Creatives'}`;
        }
    }

    function renderCreativesList(creativesArray) {
        const list = document.getElementById('maCreativesList');
        if (!list) return;
        list.innerHTML = '';

        const items = Array.isArray(creativesArray) && creativesArray.length > 0
            ? creativesArray
            : [{ name: '', creative_type: 'reel', media_type: 'video', size: '9:16', reference_url: '' }];

        items.forEach((c, idx) => {
            list.appendChild(createCreativeRowElement(c, idx));
        });

        reindexCreativeRows();
    }

    function setTestingSectionVisible(visible, adData = {}) {
        const section = document.getElementById('maTestingMetricsSection');
        if (!section) return;
        section.classList.toggle('d-none', !visible);

        section.querySelectorAll('[data-testing-input]').forEach((el) => {
            el.disabled = !visible;
        });

        const reactionEl = document.getElementById('maAggressiveReaction');
        const cpmEl = document.getElementById('maCpm');
        const ctrEl = document.getElementById('maCtr');
        const leadsEl = document.getElementById('maLeadConversionStatus');

        if (reactionEl) reactionEl.value = visible ? (adData.aggressive_reaction || '') : '';
        if (cpmEl) cpmEl.value = visible ? (adData.cpm ?? '') : '';
        if (ctrEl) ctrEl.value = visible ? (adData.ctr ?? '') : '';
        if (leadsEl) leadsEl.value = visible ? (adData.lead_conversion_status || '') : '';
    }

    function ensureSelectValue(selectEl, value, fallbackValue = '') {
        if (!selectEl) return;
        selectEl.querySelectorAll('option[data-legacy-opt]').forEach((o) => o.remove());
        const target = String(value ?? '').trim();
        if (!target) {
            selectEl.value = fallbackValue;
            return;
        }
        const exists = Array.from(selectEl.options).some((o) => o.value === target);
        if (!exists) {
            const opt = document.createElement('option');
            opt.value = target;
            opt.textContent = target;
            opt.setAttribute('data-legacy-opt', '1');
            selectEl.appendChild(opt);
        }
        selectEl.value = target;
    }

    function openModalSafely(modalEl) {
        if (!modalEl) return;
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else if (window.jQuery) {
            window.jQuery(modalEl).modal('show');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('monthlyAdModal');
        if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        document.getElementById('maAddCreativeBtn')?.addEventListener('click', () => {
            const list = document.getElementById('maCreativesList');
            if (!list) return;
            const idx = list.querySelectorAll('[data-ma-crow]').length;
            const row = createCreativeRowElement({}, idx);
            list.appendChild(row);
            reindexCreativeRows();
            row.querySelector('[data-crow-field="name"]')?.focus();
        });

        renderCreativesList([]);
        setTestingSectionVisible(false);

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.monthly-ad-edit-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            let parsed = {};
            try {
                parsed = JSON.parse(btn.getAttribute('data-ad') || '{}') || {};
            } catch (_) {
                parsed = {};
            }
            window.editMonthlyAd(parsed, btn.getAttribute('data-update-url') || '');
        });

        document.addEventListener('submit', (e) => {
            const delForm = e.target.closest('.laleen-ma-delete-form');
            if (!delForm) return;
            const promptMsg = delForm.getAttribute('data-confirm') || 'Delete this ad?';
            if (!window.confirm(promptMsg)) {
                e.preventDefault();
            }
        });
    });

    window.resetMonthlyAdForm = function () {
        isEditMode = false;
        document.getElementById('monthlyAdModalTitle').textContent = 'Add Ad';
        document.getElementById('monthlyAdForm').action = '{{ route('ecommerce.marketing.ads-plan.store') }}';
        document.getElementById('monthlyAdFormMethod').value = '';
        document.getElementById('monthlyAdForm').reset();
        document.querySelectorAll('#maPersonResponsible option[data-legacy-opt], #maTargetBranch option[data-legacy-opt]').forEach((o) => o.remove());
        document.getElementById('maPlanMonth').value = new Date().toISOString().slice(0, 7);
        renderCreativesList([]);
        setTestingSectionVisible(false);
    };

    window.editMonthlyAd = function (ad, explicitUpdateUrl = '') {
        const safeAd = (ad && typeof ad === 'object') ? ad : {};
        window.resetMonthlyAdForm();
        isEditMode = true;

        const phase = String(safeAd.phase || 'Planning').trim();
        const showTestingMetrics = ['Testing', 'Scaling', 'Killed'].includes(phase);

        document.getElementById('monthlyAdModalTitle').textContent = 'Edit Ad';
        document.getElementById('monthlyAdForm').action = explicitUpdateUrl || `/ecommerce/marketing/ads-plan/${safeAd.id}`;
        document.getElementById('monthlyAdFormMethod').value = 'PUT';

        document.getElementById('maAdName').value = safeAd.ad_name || '';
        document.getElementById('maPlanMonth').value = safeAd.plan_month || new Date().toISOString().slice(0, 7);
        ensureSelectValue(document.getElementById('maPersonResponsible'), safeAd.person_responsible, '');
        ensureSelectValue(document.getElementById('maTargetBranch'), safeAd.target_branch, 'All Branches');
        document.getElementById('maTotalBudget').value = safeAd.total_budget ?? '';
        document.getElementById('maOfferPrice').value = safeAd.offer_price ?? '';
        document.getElementById('maLiveDate').value = safeAd.live_date || '';
        document.getElementById('maOffer').value = safeAd.offer || '';
        document.getElementById('maPreFilledMessage').value = safeAd.pre_filled_message || '';
        document.getElementById('maFinalCreativeDeadline').value = safeAd.final_creative_deadline || '';

        const safeCreatives = Array.isArray(safeAd.creatives) ? safeAd.creatives : [];
        renderCreativesList(safeCreatives);
        setTestingSectionVisible(showTestingMetrics, safeAd);

        openModalSafely(document.getElementById('monthlyAdModal'));
    };
})();
</script>
