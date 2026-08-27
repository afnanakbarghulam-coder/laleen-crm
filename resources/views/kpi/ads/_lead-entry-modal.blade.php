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
                        <div class="col-md-6">
                            <label class="form-label">Ad Type / Category</label>
                            <select name="category" id="adLeadCategory" class="form-select" required>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
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
        document.getElementById('adLeadCountryCode').value = '974';
    };

    window.editAdLeadEntry = function (entry) {
        resetAdLeadEntryForm();

        document.getElementById('adLeadEntryModalTitle').innerText = 'Edit Ad Lead';
        document.getElementById('adLeadEntryForm').action = `/kpis/ad-leads/${entry.id}`;
        document.getElementById('adLeadEntryFormMethod').value = 'PUT';

        document.getElementById('adLeadDate').value = entry.date ? entry.date.slice(0, 10) : '';
        document.getElementById('adLeadCountryCode').value = entry.country_code || '974';
        document.getElementById('adLeadPhoneNumber').value = entry.phone_number || '';
        document.getElementById('adLeadCategory').value = entry.category || '';
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
