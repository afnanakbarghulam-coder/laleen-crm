{{-- Shared Add/Edit modal for Ad Lead Data Entry rows. JS toggles it between
     "create" (form posts to ad-leads.store) and "edit" (posts to ad-leads.update
     for the clicked row) — see resetAdLeadEntryForm()/editAdLeadEntry() below.
     Pass `ajaxMode => true` when reused as a shortcut outside Ad Leads Log (e.g. Lead Management). --}}
@php
    $categories = isset($categories) && is_array($categories)
        ? $categories
        : \App\Models\AdLeadEntry::availableCategories();
    $branches = isset($branches) && is_array($branches)
        ? $branches
        : \App\Models\AdLeadEntry::BRANCHES;
    $adLeadAjaxMode = ! empty($ajaxMode);
    $defaultAdLeadDate = \App\Models\Lead::salonToday()->format('Y-m-d');
@endphp
<style>
    #adLeadEntryModal.modal.show {
        opacity: 1 !important;
    }

    #adLeadEntryModal .modal-content {
        background: #241e1c !important;
        background-color: #241e1c !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        border: 1px solid rgba(217, 143, 131, 0.24) !important;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.72) !important;
        opacity: 1 !important;
    }

    #adLeadEntryModal .modal-body,
    #adLeadEntryModal .modal-header,
    #adLeadEntryModal .modal-footer {
        background: #241e1c !important;
        background-color: #241e1c !important;
    }
</style>

<div class="modal fade" id="adLeadEntryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #241e1c !important; background-color: #241e1c !important; backdrop-filter: none !important; -webkit-backdrop-filter: none !important;">
            <form method="POST"
                id="adLeadEntryForm"
                action="{{ route('kpi.ad-leads.store') }}"
                data-ajax-mode="{{ $adLeadAjaxMode ? '1' : '0' }}"
                data-categories-url="{{ route('kpi.ad-leads.categories') }}"
                data-default-date="{{ $defaultAdLeadDate }}">
                @csrf
                <input type="hidden" name="_method" id="adLeadEntryFormMethod">
                @if (! $adLeadAjaxMode)
                    @foreach (request()->only(['tab', 'entries_from', 'entries_to', 'search', 'all_dates', 'month', 'entries_page']) as $rfKey => $rfVal)
                        @if ($rfVal !== null && $rfVal !== '')
                            <input type="hidden" name="return_filters[{{ $rfKey }}]" value="{{ $rfVal }}">
                        @endif
                    @endforeach
                @endif

                <div class="modal-header">
                    <h5 class="modal-title" id="adLeadEntryModalTitle">Add Ad Lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="adLeadEntryErrorAlert" class="alert alert-danger py-2 px-3 small d-none mb-3" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" id="adLeadDate" class="form-control" value="{{ $defaultAdLeadDate }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" id="adLeadCustomerName" class="form-control" placeholder="Customer name" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="adLeadPhoneNumber">Contact / Phone Number</label>
                            <div class="input-group">
                                <x-country-code-picker name="country_code" id="adLeadCountryCode" value="974" inputClass="" />
                                <input type="text" name="phone_number" id="adLeadPhoneNumber" class="form-control" placeholder="e.g. WhatsApp number" required maxlength="25">
                            </div>
                        </div>
                        <div class="col-md-6" x-data="{ selectedCategory: @js($categories[0] ?? '') }">
                            <label class="form-label">Ad Type / Category</label>
                            <select name="category" id="adLeadCategory" class="form-select" x-model="selectedCategory" required>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                                <option value="create_new">+ Create New Category...</option>
                            </select>
                            <input type="text" name="new_category" id="adLeadNewCategory" placeholder="Enter new category name..." class="form-control form-input mt-2" x-show="selectedCategory === 'create_new'" :required="selectedCategory === 'create_new'" x-cloak>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <input type="text" name="remarks" id="adLeadRemarks" class="form-control" placeholder="e.g. will let u know" maxlength="2000">
                        </div>
                        <div class="col-12 d-none" id="adLeadAttributionReadonlyBox">
                            <div class="p-3 rounded border border-secondary border-opacity-25 d-flex justify-content-between align-items-center flex-wrap gap-3" style="background: #1b1614;">
                                <div>
                                    <div class="small text-muted text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.04em;">Branch (Auto-Synced from Calendar)</div>
                                    <div class="mt-1" id="adLeadBranchReadonly">
                                        <span class="text-muted small">Unbooked</span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted text-uppercase fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.04em;">Ticket / Revenue (QAR)</div>
                                    <div class="mt-1 fw-semibold" id="adLeadTicketReadonly">—</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 mt-3" id="adLeadModalAiBox" x-data="{
                            leadId: null,
                            isLoading: false,
                            script: '',
                            copied: false,
                            errorMsg: '',
                            resetAiState(id = null) {
                                this.leadId = id;
                                this.isLoading = false;
                                this.script = '';
                                this.copied = false;
                                this.errorMsg = '';
                            },
                            generateScript() {
                                this.isLoading = true;
                                this.errorMsg = '';
                                this.copied = false;
                                const catEl = document.getElementById('adLeadCategory');
                                const newCatEl = document.getElementById('adLeadNewCategory');
                                const remarksEl = document.getElementById('adLeadRemarks');
                                const custNameEl = document.getElementById('adLeadCustomerName');
                                const categoryVal = (catEl && catEl.value === 'create_new' && newCatEl) ? newCatEl.value : (catEl ? catEl.value : '');
                                const remarksVal = remarksEl ? remarksEl.value : '';
                                const customerNameVal = custNameEl ? custNameEl.value : '';
                                const url = this.leadId ? `/kpis/ad-leads/${this.leadId}/ai-script` : `{{ route('kpi.ad-leads.ai-script.inline') }}`;

                                fetch(url, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({
                                        lead_id: this.leadId,
                                        name: customerNameVal,
                                        customer_name: customerNameVal,
                                        category: categoryVal,
                                        remarks: remarksVal
                                    })
                                })
                                .then(r => r.json())
                                .then(data => {
                                    if (data && data.script) {
                                        this.script = data.script;
                                    } else {
                                        this.errorMsg = 'Could not generate script. Please try again.';
                                    }
                                    this.isLoading = false;
                                })
                                .catch(() => {
                                    this.errorMsg = 'Network error while generating AI script.';
                                    this.isLoading = false;
                                })
                                .finally(() => {
                                    this.isLoading = false;
                                });
                            },
                            copyToWhatsApp() {
                                if (!this.script) return;
                                navigator.clipboard.writeText(this.script).then(() => {
                                    this.copied = true;
                                    setTimeout(() => { this.copied = false; }, 2500);
                                });
                            }
                        }" @ad-lead-modal-reset.window="resetAiState($event.detail.id)">
                            <div class="p-3 rounded border border-secondary border-opacity-25" style="background: #1b1614;">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <span class="small fw-bold text-uppercase text-muted">Sales Consultant</span>
                                    <button type="button"
                                        class="btn btn-sm btn-primary"
                                        @click="generateScript()"
                                        :disabled="isLoading">
                                        <span x-show="!isLoading">🧠 Generate AI Closing Script</span>
                                        <span x-show="isLoading" x-cloak>
                                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                            Generating...
                                        </span>
                                    </button>
                                </div>

                                <div x-show="isLoading" x-cloak class="mt-2 small text-muted">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                                        <span>Crafting high-converting 2-sentence WhatsApp closing script...</span>
                                    </div>
                                </div>

                                <div x-show="errorMsg && !isLoading" x-cloak class="mt-2 small text-danger" x-text="errorMsg"></div>

                                <div x-show="script && !isLoading" x-cloak class="mt-2">
                                    <div class="p-2 rounded mb-2 small border border-secondary border-opacity-25" style="background: rgba(255, 255, 255, 0.04); border-left: 3px solid var(--bs-primary, #c98a7b) !important; line-height: 1.5;" x-text="script"></div>
                                    <button type="button" class="btn btn-sm btn-success fw-semibold" @click="copyToWhatsApp()">
                                        <i class="bx bxl-whatsapp me-1"></i>
                                        <span x-text="copied ? 'Copied to Clipboard!' : 'Copy to WhatsApp'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="adLeadSaveBtn">
                        <span id="adLeadSaveLabel">Save</span>
                        <span id="adLeadSaveSpinner" class="d-none">
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            Saving...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const adLeadBranchLabels = @json($branches);

    window.syncAdLeadCategoryOptions = function (categoryList, preferredValue = null) {
        const catSelect = document.getElementById('adLeadCategory');
        if (!catSelect || !Array.isArray(categoryList)) return;

        const currentVal = preferredValue !== null ? preferredValue : catSelect.value;
        catSelect.innerHTML = '';

        categoryList.forEach(function (catName) {
            const opt = document.createElement('option');
            opt.value = catName;
            opt.textContent = catName;
            catSelect.appendChild(opt);
        });

        const createOpt = document.createElement('option');
        createOpt.value = 'create_new';
        createOpt.textContent = '+ Create New Category...';
        catSelect.appendChild(createOpt);

        if (currentVal && Array.from(catSelect.options).some(o => o.value === currentVal)) {
            catSelect.value = currentVal;
        } else if (catSelect.options.length > 0 && catSelect.options[0].value !== 'create_new') {
            catSelect.value = catSelect.options[0].value;
        }

        catSelect.dispatchEvent(new Event('change', { bubbles: true }));
        catSelect.dispatchEvent(new Event('input', { bubbles: true }));
    };

    window.refreshAdLeadCategories = function () {
        const form = document.getElementById('adLeadEntryForm');
        const url = form ? form.dataset.categoriesUrl : '';
        if (!url) return;

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (data && Array.isArray(data.categories) && data.categories.length > 0) {
                    const catSelect = document.getElementById('adLeadCategory');
                    const currentVal = catSelect ? catSelect.value : null;
                    window.syncAdLeadCategoryOptions(data.categories, currentVal === 'create_new' ? 'create_new' : (currentVal || data.categories[0]));
                }
            })
            .catch(() => {});
    };

    window.setAdLeadModalError = function (msg) {
        const errEl = document.getElementById('adLeadEntryErrorAlert');
        if (!errEl) return;
        if (msg) {
            errEl.textContent = msg;
            errEl.classList.remove('d-none');
        } else {
            errEl.textContent = '';
            errEl.classList.add('d-none');
        }
    };

    window.setAdLeadModalSaving = function (loading) {
        const btn = document.getElementById('adLeadSaveBtn');
        const lbl = document.getElementById('adLeadSaveLabel');
        const spn = document.getElementById('adLeadSaveSpinner');
        if (btn) btn.disabled = loading;
        if (lbl) lbl.classList.toggle('d-none', loading);
        if (spn) spn.classList.toggle('d-none', !loading);
    };

    window.resetAdLeadEntryForm = function () {
        const form = document.getElementById('adLeadEntryForm');
        document.getElementById('adLeadEntryModalTitle').innerText = 'Add Ad Lead';
        form.action = '{{ route('kpi.ad-leads.store') }}';
        document.getElementById('adLeadEntryFormMethod').value = '';
        form.reset();
        window.setAdLeadModalError('');
        window.setAdLeadModalSaving(false);

        const defaultDate = form.dataset.defaultDate || new Date().toISOString().slice(0, 10);
        document.getElementById('adLeadDate').value = defaultDate;
        document.getElementById('adLeadCustomerName').value = '';

        const ccEl = document.getElementById('adLeadCountryCode');
        if (ccEl) {
            ccEl.value = '974';
            ccEl.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const attrBox = document.getElementById('adLeadAttributionReadonlyBox');
        if (attrBox) attrBox.classList.add('d-none');
        const catSelect = document.getElementById('adLeadCategory');
        const newCatInput = document.getElementById('adLeadNewCategory');
        if (newCatInput) newCatInput.value = '';
        if (catSelect) {
            if (catSelect.options.length > 0 && catSelect.options[0].value !== 'create_new') {
                catSelect.value = catSelect.options[0].value;
            }
            catSelect.dispatchEvent(new Event('change', { bubbles: true }));
            catSelect.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (form.dataset.ajaxMode === '1') {
            window.refreshAdLeadCategories();
        }
        window.dispatchEvent(new CustomEvent('ad-lead-modal-reset', { detail: { id: null } }));
    };

    window.editAdLeadEntry = function (entry) {
        resetAdLeadEntryForm();

        document.getElementById('adLeadEntryModalTitle').innerText = 'Edit Ad Lead';
        document.getElementById('adLeadEntryForm').action = `/kpis/ad-leads/${entry.id}`;
        document.getElementById('adLeadEntryFormMethod').value = 'PUT';
        window.dispatchEvent(new CustomEvent('ad-lead-modal-reset', { detail: { id: entry.id } }));

        document.getElementById('adLeadDate').value = entry.date ? entry.date.slice(0, 10) : '';
        document.getElementById('adLeadCustomerName').value = entry.customer_name || '';
        const ccEl = document.getElementById('adLeadCountryCode');
        if (ccEl) {
            ccEl.value = entry.country_code || '974';
            ccEl.dispatchEvent(new Event('change', { bubbles: true }));
        }
        document.getElementById('adLeadPhoneNumber').value = entry.phone_number || '';
        const catSelect = document.getElementById('adLeadCategory');
        if (catSelect) {
            catSelect.value = entry.category || '';
            catSelect.dispatchEvent(new Event('change', { bubbles: true }));
            catSelect.dispatchEvent(new Event('input', { bubbles: true }));
        }
        document.getElementById('adLeadRemarks').value = entry.remarks || '';

        const attrBox = document.getElementById('adLeadAttributionReadonlyBox');
        const branchEl = document.getElementById('adLeadBranchReadonly');
        const ticketEl = document.getElementById('adLeadTicketReadonly');
        if (attrBox && branchEl && ticketEl) {
            attrBox.classList.remove('d-none');
            const branchLabel = entry.branch && adLeadBranchLabels[entry.branch] ? adLeadBranchLabels[entry.branch] : null;
            branchEl.innerHTML = branchLabel
                ? `<span class="kpi-badge kpi-badge-green">${branchLabel}</span>`
                : '<span class="text-muted small">Unbooked</span>';
            const amt = parseFloat(entry.ticket_amount || 0);
            ticketEl.textContent = amt > 0 ? `${amt.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} QAR` : (branchLabel ? '0.00 QAR' : '—');
        }

        new bootstrap.Modal(document.getElementById('adLeadEntryModal')).show();
    };

    document.querySelectorAll('.ad-lead-edit-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            editAdLeadEntry(JSON.parse(this.dataset.entry));
        });
    });

    (function bindAdLeadAjaxSubmit() {
        const form = document.getElementById('adLeadEntryForm');
        const modalEl = document.getElementById('adLeadEntryModal');
        if (!form || form.dataset.ajaxMode !== '1') return;

        let isSubmitting = false;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            if (isSubmitting) return;

            window.setAdLeadModalError('');
            isSubmitting = true;
            window.setAdLeadModalSaving(true);

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                    || form.querySelector('input[name="_token"]')?.value
                    || '{{ csrf_token() }}';

                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: new FormData(form),
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok || !data || data.success === false) {
                    const firstFieldErr = data && data.errors
                        ? Object.values(data.errors).flat()[0]
                        : null;
                    window.setAdLeadModalError(firstFieldErr || data.message || 'Could not save ad lead. Please check the fields and try again.');
                    return;
                }

                if (Array.isArray(data.categories) && data.categories.length > 0) {
                    window.syncAdLeadCategoryOptions(data.categories, data.categories[0]);
                }

                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const inst = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
                    inst.hide();
                }

                window.dispatchEvent(new CustomEvent('ad-lead-saved', { detail: data }));
            } catch (err) {
                window.setAdLeadModalError('Network error while saving ad lead. Please try again.');
            } finally {
                isSubmitting = false;
                window.setAdLeadModalSaving(false);
            }
        });
    })();
</script>
