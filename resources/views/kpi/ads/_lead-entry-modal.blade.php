{{-- Shared Add/Edit modal for Ad Lead Data Entry rows. JS toggles it between
     "create" (form posts to ad-leads.store) and "edit" (posts to ad-leads.update
     for the clicked row) — see resetAdLeadEntryForm()/editAdLeadEntry() below. --}}
<div class="modal fade" id="adLeadEntryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="adLeadEntryForm" action="{{ route('kpi.ad-leads.store') }}">
                @csrf
                <input type="hidden" name="_method" id="adLeadEntryFormMethod">

                <div class="modal-header">
                    <h5 class="modal-title" id="adLeadEntryModalTitle">Add Ad Lead</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" id="adLeadDate" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" id="adLeadCustomerName" class="form-control" placeholder="Customer name" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact / Phone Number</label>
                            <div class="input-group">
                                <select name="country_code" id="adLeadCountryCode" class="form-select" style="max-width: 110px; flex: 0 0 110px;">
                                    @foreach (\App\Models\Lead::COUNTRY_CODES as $code => $label)
                                        <option value="{{ $code }}" title="{{ $label }}" {{ $code === '974' ? 'selected' : '' }}>+{{ $code }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="phone_number" id="adLeadPhoneNumber" class="form-control" placeholder="e.g. WhatsApp number" required maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-6" x-data="{ selectedCategory: '{{ $categories[0] ?? '' }}' }">
                            <label class="form-label">Ad Type / Category</label>
                            <select name="category" id="adLeadCategory" class="form-select" x-model="selectedCategory" required>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                                <option value="create_new">+ Create New Category...</option>
                            </select>
                            <input type="text" name="new_category" id="adLeadNewCategory" placeholder="Enter new category name..." class="form-control form-input mt-2" x-show="selectedCategory === 'create_new'" :required="selectedCategory === 'create_new'" x-cloak>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ticket / Revenue Amount (QAR)</label>
                            <input type="number" name="ticket_amount" id="adLeadTicket" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Branch <span class="text-muted">(leave blank if unbooked)</span></label>
                            <select name="branch" id="adLeadBranch" class="form-select">
                                <option value="">— Unbooked —</option>
                                @foreach ($branches as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Remarks</label>
                            <input type="text" name="remarks" id="adLeadRemarks" class="form-control" placeholder="e.g. will let u know" maxlength="255">
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
                            <div class="p-3 rounded border border-secondary border-opacity-25" style="background: rgba(20, 16, 14, 0.35);">
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
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.resetAdLeadEntryForm = function () {
        document.getElementById('adLeadEntryModalTitle').innerText = 'Add Ad Lead';
        document.getElementById('adLeadEntryForm').action = '{{ route('kpi.ad-leads.store') }}';
        document.getElementById('adLeadEntryFormMethod').value = '';
        document.getElementById('adLeadEntryForm').reset();
        document.getElementById('adLeadDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('adLeadCustomerName').value = '';
        document.getElementById('adLeadCountryCode').value = '974';
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
        document.getElementById('adLeadCountryCode').value = entry.country_code || '974';
        document.getElementById('adLeadPhoneNumber').value = entry.phone_number || '';
        const catSelect = document.getElementById('adLeadCategory');
        if (catSelect) {
            catSelect.value = entry.category || '';
            catSelect.dispatchEvent(new Event('change', { bubbles: true }));
            catSelect.dispatchEvent(new Event('input', { bubbles: true }));
        }
        document.getElementById('adLeadTicket').value = entry.ticket_amount || 0;
        document.getElementById('adLeadBranch').value = entry.branch || '';
        document.getElementById('adLeadRemarks').value = entry.remarks || '';

        new bootstrap.Modal(document.getElementById('adLeadEntryModal')).show();
    };

    document.querySelectorAll('.ad-lead-edit-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            editAdLeadEntry(JSON.parse(this.dataset.entry));
        });
    });
</script>
