@php
    $modalWorkspace = $metricsWorkspace ?? 'salon';
    $modalRegion = $metricsRegion ?? ($modalWorkspace === 'ecommerce' ? 'pk' : 'qa');
    $modalBranch = in_array(($metricsBranch ?? ''), ['old_airport', 'wakrah', 'both'], true) ? $metricsBranch : 'both';
    $modalLocalCurrency = $metricsLocalCurrency ?? 'QAR';
    $modalDefaultFx = (float) ($metricsDefaultFx ?? 3.6415);
    $stayInKpis = $stayInKpis ?? true;
    $crmCategories = $categories ?? \App\Models\AdLeadEntry::availableCategories();
    $allDefs = $metricDefinitions ?? \App\Models\AdCampaignMetric::METRIC_DEFINITIONS;
@endphp

<div class="modal fade" id="adScreenshotImportModal" tabindex="-1" aria-labelledby="adScreenshotImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content laleen-ad-modal">
            <div class="modal-header laleen-ad-modal-header">
                <h5 class="modal-title mb-0" id="adScreenshotImportModalLabel" style="color: #f8efe6; font-weight: 600;">Upload Ads Screenshot</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div id="adImportAlertBox" class="laleen-inline-alert d-none mb-3" role="alert"></div>

                {{-- ================= STEP 1: COMPACT CONTROLS & DROPZONE ================= --}}
                <div id="adImportStep1">
                    <input type="hidden" id="adImportWorkspaceHidden" value="{{ $modalWorkspace }}">
                    <input type="hidden" id="adImportRegionHidden" value="{{ $modalRegion }}">

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        @if ($modalWorkspace === 'salon')
                            <div class="d-flex align-items-center gap-2">
                                <label class="laleen-label mb-0" for="adImportBranch">Branch</label>
                                <select id="adImportBranch" class="form-select form-select-sm laleen-input" style="width: auto; min-width: 150px;">
                                    <option value="both" {{ $modalBranch === 'both' ? 'selected' : '' }}>Both Branches</option>
                                    <option value="old_airport" {{ $modalBranch === 'old_airport' ? 'selected' : '' }}>Old Airport</option>
                                    <option value="wakrah" {{ $modalBranch === 'wakrah' ? 'selected' : '' }}>Al Wakrah</option>
                                </select>
                            </div>
                        @endif

                        <div class="laleen-live-fx-pill ms-auto" style="width: auto;">
                            <span class="laleen-fx-dot"></span>
                            <span>Live rate: 1 USD =</span>
                            <input type="number" step="0.0001" min="0.0001" id="adImportFxRate"
                                   class="laleen-fx-bare-input"
                                   value="{{ number_format($modalDefaultFx, 4, '.', '') }}"
                                   aria-label="USD to {{ $modalLocalCurrency }} exchange rate">
                            <span>{{ $modalLocalCurrency }}</span>
                        </div>
                    </div>

                    <div id="adScreenshotDropzone" class="laleen-dropzone" tabindex="0">
                        <input type="file" id="adScreenshotFileInput" accept="image/png,image/jpeg,image/webp" multiple class="d-none">
                        <div id="adDropzonePrompt">
                            <div class="fw-medium" style="color: #f8efe6; font-size: 0.94rem;">
                                Drop screenshot(s), <span class="laleen-link-accent">browse</span>, or press <kbd class="laleen-kbd">Ctrl+V</kbd>
                            </div>
                        </div>

                        <div id="adFileChipList" class="laleen-chip-list d-none"></div>
                    </div>
                </div>

                {{-- ================= STEP 2: EDITABLE PREVIEW ================= --}}
                <div id="adImportStep2" class="d-none">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="laleen-meta-pill" id="adPreviewPeriodBadge"></span>
                            <span id="adPreviewVerificationBadge" class="laleen-verify-pill d-none"></span>
                        </div>

                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <div class="laleen-live-fx-pill py-1 px-2" id="adPreviewFxPillWrap" style="width: auto;">
                                <span id="adPreviewCurrCode">1 USD =</span>
                                <input type="number" step="0.0001" min="0.0001" id="adPreviewFxInput" class="laleen-fx-bare-input" style="width: 68px;">
                                <span id="adPreviewLocalCode">{{ $modalLocalCurrency }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="laleen-preview-table-wrap">
                        <table class="table laleen-preview-table mb-0" id="adPreviewTable">
                            <thead id="adPreviewThead"></thead>
                            <tbody id="adPreviewTbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer laleen-ad-modal-footer">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <button type="button" id="adBackToStep1Btn" class="btn btn-sm laleen-ghost-btn d-none">
                            Back
                        </button>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm laleen-ghost-btn" data-bs-dismiss="modal">Cancel</button>

                        <button type="button" id="adExtractBtn" class="btn btn-sm laleen-rose-btn">
                            <span id="adExtractBtnLabel">Extract Metrics</span>
                            <span id="adExtractBtnSpinner" class="d-none">
                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                Extracting...
                            </span>
                        </button>

                        <button type="button" id="adConfirmImportBtn" class="btn btn-sm laleen-rose-btn d-none">
                            <span id="adConfirmBtnLabel">Save Metrics</span>
                            <span id="adConfirmBtnSpinner" class="d-none">
                                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                Saving...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__laleenAdScreenshotModalBound) return;
    window.__laleenAdScreenshotModalBound = true;

    const METRIC_DEFS = @json($allDefs);
    const CRM_CATEGORIES = @json(array_values($crmCategories));
    const EXTRACT_URL = @json(route('kpi.ad-metrics.extract'));
    const CONFIRM_URL = @json(route('kpi.ad-metrics.confirm'));
    const CSRF_TOKEN = @json(csrf_token());
    const STAY_IN_KPIS = @json((bool) $stayInKpis);

    let stagedFiles = [];
    let previewState = null;

    const el = (id) => document.getElementById(id);

    function showAlert(msg, type = 'error') {
        const box = el('adImportAlertBox');
        if (!box) return;
        box.textContent = msg;
        box.className = 'laleen-inline-alert mb-3 ' + (type === 'success' ? 'is-ok' : 'is-err');
        box.classList.remove('d-none');
    }

    function clearAlert() {
        el('adImportAlertBox')?.classList.add('d-none');
    }

    function addFiles(fileList) {
        clearAlert();
        const allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
        Array.from(fileList || []).forEach((file) => {
            if (!allowed.includes(file.type) && !/\.(png|jpe?g|webp)$/i.test(file.name || '')) {
                showAlert('Only PNG, JPG, or WEBP screenshot images are supported.');
                return;
            }
            if (stagedFiles.length >= 8) {
                showAlert('Up to 8 screenshots can be uploaded in one batch.');
                return;
            }
            stagedFiles.push(file);
        });
        renderFileChips();
    }

    function renderFileChips() {
        const list = el('adFileChipList');
        const prompt = el('adDropzonePrompt');
        if (!list || !prompt) return;

        if (stagedFiles.length === 0) {
            list.classList.add('d-none');
            list.innerHTML = '';
            prompt.classList.remove('d-none');
            return;
        }

        prompt.classList.add('d-none');
        list.classList.remove('d-none');

        let html = '<div class="d-flex flex-wrap align-items-center gap-2 w-100">';
        stagedFiles.forEach((f, idx) => {
            const kb = Math.max(1, Math.round(f.size / 1024));
            const safeName = (f.name || ('screenshot-' + (idx + 1) + '.png')).replace(/[<>&"]/g, '');
            html += `<span class="laleen-file-chip">
                <span>${safeName}</span>
                <small style="color:#b8a29a;">${kb} KB</small>
                <button type="button" class="laleen-chip-remove" data-idx="${idx}" title="Remove">&times;</button>
            </span>`;
        });
        html += `<button type="button" class="btn btn-sm laleen-ghost-btn ms-auto" id="adAddMoreFilesBtn">+ Add More</button>`;
        html += '</div>';
        list.innerHTML = html;

        list.querySelectorAll('.laleen-chip-remove').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                stagedFiles.splice(parseInt(btn.getAttribute('data-idx'), 10), 1);
                renderFileChips();
            });
        });

        el('adAddMoreFilesBtn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            el('adScreenshotFileInput')?.click();
        });
    }

    function switchStep(step) {
        clearAlert();
        const isStep2 = (step === 2);
        el('adImportStep1')?.classList.toggle('d-none', isStep2);
        el('adImportStep2')?.classList.toggle('d-none', !isStep2);
        el('adBackToStep1Btn')?.classList.toggle('d-none', !isStep2);
        el('adExtractBtn')?.classList.toggle('d-none', isStep2);
        el('adConfirmImportBtn')?.classList.toggle('d-none', !isStep2);
    }

    function detectedPreviewMetricKeys() {
        const allKeys = Object.keys(METRIC_DEFS);
        if (!previewState || !Array.isArray(previewState.campaigns)) {
            return ['amount_spent', 'impressions', 'cpm', 'link_clicks', 'cpc', 'ctr'];
        }
        const populated = allKeys.filter((col) => {
            if (col === 'amount_spent') {
                return previewState.campaigns.some((r) => (r.amount_spent !== null && r.amount_spent !== undefined && r.amount_spent !== '') || r.est_amount_spent !== null);
            }
            return previewState.campaigns.some((r) => r[col] !== null && r[col] !== undefined && r[col] !== '');
        });
        return populated.length > 0 ? populated : ['amount_spent', 'impressions', 'cpm', 'link_clicks', 'cpc', 'ctr'];
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderStep2Preview() {
        if (!previewState) return;

        const vBadge = el('adPreviewVerificationBadge');
        if (vBadge) {
            const st = previewState.verification?.status;
            if (st === 'verified' || st === 'mismatch') {
                vBadge.className = 'laleen-verify-pill ' + (st === 'verified' ? 'is-verified' : 'is-amber');
                vBadge.textContent = previewState.verification.label || '';
                vBadge.classList.remove('d-none');
            } else {
                vBadge.classList.add('d-none');
            }
        }

        const pBadge = el('adPreviewPeriodBadge');
        if (pBadge) {
            const periodPart = previewState.period_label
                ? previewState.period_label
                : 'Date cropped (preserving existing period)';
            pBadge.textContent = `${periodPart} · ${previewState.campaigns.length} campaign(s)`;
        }

        const accCurr = previewState.account_currency || 'USD';
        const locCurr = previewState.local_currency || 'QAR';
        const fxPill = el('adPreviewFxPillWrap');
        const currCode = el('adPreviewCurrCode');
        const fxInput = el('adPreviewFxInput');
        const localCode = el('adPreviewLocalCode');

        if (currCode) currCode.textContent = `1 ${accCurr} =`;
        if (localCode) localCode.textContent = locCurr;
        if (fxInput) {
            fxInput.value = Number(previewState.fx_rate_to_local || 1).toFixed(4);
            fxInput.disabled = (accCurr === locCurr);
        }
        if (fxPill) {
            fxPill.classList.toggle('d-none', accCurr === locCurr);
        }

        const cols = detectedPreviewMetricKeys();
        const isSalon = previewState.workspace === 'salon';

        let thead = '<tr>';
        thead += '<th style="min-width: 210px;">Campaign</th>';
        if (isSalon) {
            thead += '<th style="min-width: 120px;">Branch</th>';
            thead += '<th style="min-width: 165px;">CRM Offer</th>';
        }
        cols.forEach((k) => {
            const def = METRIC_DEFS[k] || { short: k };
            thead += `<th class="text-end" style="min-width: 104px;">${escapeHtml(def.short || def.label)}</th>`;
        });
        thead += '<th style="width: 38px;"></th>';
        thead += '</tr>';
        el('adPreviewThead').innerHTML = thead;

        let tbody = '';
        if (previewState.campaigns.length === 0) {
            tbody = `<tr><td colspan="${cols.length + (isSalon ? 4 : 2)}" class="text-center py-4" style="color:#b8a29a;">No campaign rows detected in this screenshot.</td></tr>`;
        } else {
            previewState.campaigns.forEach((row, idx) => {
                const mergeNote = row.merge_mode === 'merge'
                    ? `<div class="mt-1"><span class="laleen-merge-tag is-merge">Updates existing${row.preserved_count ? ' (+' + row.preserved_count + ' kept)' : ''}</span></div>`
                    : '';

                tbody += `<tr data-row-idx="${idx}">`;
                tbody += `<td>
                    <input type="text" class="form-control form-control-sm laleen-cell-input fw-semibold" data-field="campaign_name" value="${escapeHtml(row.campaign_name)}">
                    ${mergeNote}
                </td>`;

                if (isSalon) {
                    const br = row.branch || 'both';
                    tbody += `<td>
                        <select class="form-select form-select-sm laleen-cell-input" data-field="branch">
                            <option value="both" ${br === 'both' ? 'selected' : ''}>Both</option>
                            <option value="old_airport" ${br === 'old_airport' ? 'selected' : ''}>Old Airport</option>
                            <option value="wakrah" ${br === 'wakrah' ? 'selected' : ''}>Al Wakrah</option>
                        </select>
                    </td>`;

                    let catOpts = '<option value="">Optional</option>';
                    CRM_CATEGORIES.forEach((c) => {
                        const sel = (row.ad_category === c) ? 'selected' : '';
                        catOpts += `<option value="${escapeHtml(c)}" ${sel}>${escapeHtml(c)}</option>`;
                    });

                    tbody += `<td>
                        <select class="form-select form-select-sm laleen-cell-input mb-1" data-field="ad_category">${catOpts}</select>
                        <label class="d-inline-flex align-items-center gap-1 small" style="color:#b8a29a; cursor:pointer;">
                            <input type="checkbox" class="form-check-input mt-0" data-field="link_crm_revenue" ${row.link_crm_revenue ? 'checked' : ''}>
                            <span>Link CRM QAR</span>
                        </label>
                    </td>`;
                }

                cols.forEach((colKey) => {
                    const val = row[colKey];
                    const displayVal = (val === null || val === undefined) ? '' : val;
                    let subHint = '';
                    if (colKey === 'amount_spent') {
                        const fx = Number(previewState.fx_rate_to_local || 1);
                        if (val !== null && val !== undefined && val !== '' && accCurr !== locCurr) {
                            subHint = `<div class="laleen-cell-hint">${(Number(val) * fx).toFixed(2)} ${escapeHtml(locCurr)}</div>`;
                        } else if ((val === null || val === undefined || val === '') && row.impressions && row.cpm) {
                            const est = ((Number(row.impressions) / 1000) * Number(row.cpm));
                            subHint = `<div class="laleen-cell-hint is-est">~${est.toFixed(2)} est.</div>`;
                        }
                    }

                    tbody += `<td class="text-end">
                        <input type="text" inputmode="decimal" class="form-control form-control-sm laleen-cell-input text-end" data-metric="${colKey}" value="${escapeHtml(displayVal)}">
                        ${subHint}
                    </td>`;
                });

                tbody += `<td class="text-center">
                    <button type="button" class="laleen-row-del-btn" data-remove-row="${idx}" title="Exclude row">&times;</button>
                </td>`;
                tbody += `</tr>`;
            });
        }

        const tbodyEl = el('adPreviewTbody');
        tbodyEl.innerHTML = tbody;

        tbodyEl.querySelectorAll('tr[data-row-idx]').forEach((tr) => {
            const idx = parseInt(tr.getAttribute('data-row-idx'), 10);
            tr.querySelectorAll('[data-field]').forEach((inp) => {
                inp.addEventListener('change', () => {
                    const f = inp.getAttribute('data-field');
                    previewState.campaigns[idx][f] = inp.type === 'checkbox' ? inp.checked : inp.value;
                });
                if (inp.type === 'text') {
                    inp.addEventListener('input', () => {
                        previewState.campaigns[idx][inp.getAttribute('data-field')] = inp.value;
                    });
                }
            });

            tr.querySelectorAll('[data-metric]').forEach((inp) => {
                inp.addEventListener('input', () => {
                    const m = inp.getAttribute('data-metric');
                    const raw = inp.value.trim();
                    previewState.campaigns[idx][m] = raw === '' ? null : raw;
                });
            });
        });

        tbodyEl.querySelectorAll('[data-remove-row]').forEach((btn) => {
            btn.addEventListener('click', () => {
                previewState.campaigns.splice(parseInt(btn.getAttribute('data-remove-row'), 10), 1);
                renderStep2Preview();
            });
        });
    }

    async function runExtractPreview() {
        clearAlert();
        if (stagedFiles.length === 0) {
            showAlert('Please drop, browse, or paste (Ctrl+V) at least one screenshot.');
            return;
        }

        const workspace = el('adImportWorkspaceHidden')?.value || 'salon';
        const region = el('adImportRegionHidden')?.value || 'qa';

        const formData = new FormData();
        stagedFiles.forEach((f) => formData.append('screenshots[]', f));
        formData.append('workspace', workspace);
        formData.append('region', region);
        if (workspace === 'salon' && el('adImportBranch')) {
            formData.append('branch', el('adImportBranch').value || 'both');
        }
        formData.append('account_currency', 'USD');
        if (el('adImportFxRate')?.value) {
            formData.append('fx_rate_to_local', el('adImportFxRate').value);
        }

        const btn = el('adExtractBtn');
        if (btn) btn.disabled = true;
        el('adExtractBtnLabel')?.classList.add('d-none');
        el('adExtractBtnSpinner')?.classList.remove('d-none');

        try {
            const resp = await fetch(EXTRACT_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                },
                body: formData,
            });
            let data = null;
            try {
                data = await resp.json();
            } catch (_) {
                data = null;
            }
            if (!resp.ok || !data || !data.success) {
                const errMsg = data?.message
                    || (data?.errors ? Object.values(data.errors).flat().join(' ') : 'AI vision servers are temporarily busy. Please click Extract Metrics again in a few seconds.');
                showAlert(errMsg);
                return;
            }

            previewState = data;
            switchStep(2);
            renderStep2Preview();
        } catch (err) {
            showAlert('Network error while analyzing screenshot. Please try again.');
        } finally {
            if (btn) btn.disabled = false;
            el('adExtractBtnLabel')?.classList.remove('d-none');
            el('adExtractBtnSpinner')?.classList.add('d-none');
        }
    }

    async function runConfirmImport() {
        clearAlert();
        if (!previewState || !Array.isArray(previewState.campaigns) || previewState.campaigns.length === 0) {
            showAlert('There are no campaign rows to save.');
            return;
        }

        const btn = el('adConfirmImportBtn');
        if (btn) btn.disabled = true;
        el('adConfirmBtnLabel')?.classList.add('d-none');
        el('adConfirmBtnSpinner')?.classList.remove('d-none');

        const payload = {
            import_log_id: previewState.import_log_id,
            workspace: previewState.workspace,
            region: previewState.region,
            date_from: previewState.date_from || null,
            date_to: previewState.date_to || null,
            account_currency: previewState.account_currency || 'USD',
            local_currency: previewState.local_currency || 'QAR',
            fx_rate_to_local: parseFloat(el('adPreviewFxInput')?.value || previewState.fx_rate_to_local || 1) || 1,
            stay_in_kpis: STAY_IN_KPIS,
            campaigns: previewState.campaigns,
        };

        try {
            const resp = await fetch(CONFIRM_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                },
                body: JSON.stringify(payload),
            });
            const data = await resp.json();
            if (!resp.ok || !data.success) {
                const errMsg = data?.message
                    || (data?.errors ? Object.values(data.errors).flat().join(' ') : 'Failed to save campaign metrics.');
                showAlert(errMsg);
                return;
            }

            window.location.href = data.redirect_url || window.location.href;
        } catch (err) {
            showAlert('Network error while saving metrics. Please try again.');
        } finally {
            if (btn) btn.disabled = false;
            el('adConfirmBtnLabel')?.classList.remove('d-none');
            el('adConfirmBtnSpinner')?.classList.add('d-none');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const dropzone = el('adScreenshotDropzone');
        const fileInput = el('adScreenshotFileInput');
        const modalEl = el('adScreenshotImportModal');

        dropzone?.addEventListener('click', (e) => {
            if (e.target.closest('.laleen-chip-remove') || e.target.closest('#adAddMoreFilesBtn')) return;
            fileInput?.click();
        });

        fileInput?.addEventListener('change', () => {
            if (fileInput.files?.length) {
                addFiles(fileInput.files);
                fileInput.value = '';
            }
        });

        ['dragenter', 'dragover'].forEach((evName) => {
            dropzone?.addEventListener(evName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach((evName) => {
            dropzone?.addEventListener(evName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('is-dragover');
            });
        });

        dropzone?.addEventListener('drop', (e) => {
            if (e.dataTransfer?.files?.length) {
                addFiles(e.dataTransfer.files);
            }
        });

        document.addEventListener('paste', (e) => {
            if (!modalEl || !modalEl.classList.contains('show')) return;
            const items = e.clipboardData?.items;
            if (!items) return;
            const imgs = [];
            for (let i = 0; i < items.length; i++) {
                if (items[i].type && items[i].type.startsWith('image/')) {
                    const blob = items[i].getAsFile();
                    if (blob) {
                        const ext = (blob.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                        imgs.push(new File([blob], `pasted-ads-${Date.now()}-${i + 1}.${ext}`, { type: blob.type }));
                    }
                }
            }
            if (imgs.length > 0) {
                e.preventDefault();
                if (!el('adImportStep2')?.classList.contains('d-none')) {
                    switchStep(1);
                }
                addFiles(imgs);
            }
        });

        el('adExtractBtn')?.addEventListener('click', runExtractPreview);
        el('adConfirmImportBtn')?.addEventListener('click', runConfirmImport);
        el('adBackToStep1Btn')?.addEventListener('click', () => switchStep(1));

        el('adPreviewFxInput')?.addEventListener('input', (e) => {
            if (!previewState) return;
            const v = parseFloat(e.target.value);
            if (v > 0) {
                previewState.fx_rate_to_local = v;
                renderStep2Preview();
            }
        });
    });
})();
</script>
