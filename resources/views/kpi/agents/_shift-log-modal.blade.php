{{-- Shared Add/Edit modal for Agent Shift Log rows. JS toggles it between
     "create" (form posts to agent-shift-logs.store) and "edit" (posts to
     agent-shift-logs.update for the clicked row). --}}
<div class="modal fade" id="shiftLogModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="shiftLogForm" action="{{ route('kpi.agent-shift-logs.store') }}">
                @csrf
                <input type="hidden" name="_method" id="shiftLogFormMethod">

                <div class="modal-header">
                    <h5 class="modal-title" id="shiftLogModalTitle">Log Shift Sign-In</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" id="shiftLogDate" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Agent</label>
                            <select name="user_id" id="shiftLogAgent" class="form-select" required>
                                <option value="">— Select agent —</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Shift</label>
                            <select name="shift" id="shiftLogShift" class="form-select" required>
                                @foreach (\App\Models\AgentShiftLog::SHIFTS as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sign-In (Check-In) Time</label>
                            <input type="time" name="check_in_time" id="shiftLogCheckIn" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sign-Out (Check-Out) Time <span class="text-muted">(optional — add when the shift ends)</span></label>
                            <input type="time" name="check_out_time" id="shiftLogCheckOut" class="form-control">
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
    window.resetShiftLogForm = function () {
        document.getElementById('shiftLogModalTitle').innerText = 'Log Shift Sign-In';
        document.getElementById('shiftLogForm').action = '{{ route('kpi.agent-shift-logs.store') }}';
        document.getElementById('shiftLogFormMethod').value = '';
        document.getElementById('shiftLogForm').reset();
        document.getElementById('shiftLogDate').value = new Date().toISOString().slice(0, 10);
    };

    window.editShiftLog = function (log) {
        resetShiftLogForm();

        document.getElementById('shiftLogModalTitle').innerText = 'Edit Shift Sign-In';
        document.getElementById('shiftLogForm').action = `/kpis/agent-shift-logs/${log.id}`;
        document.getElementById('shiftLogFormMethod').value = 'PUT';

        document.getElementById('shiftLogDate').value = log.date ? log.date.slice(0, 10) : '';
        document.getElementById('shiftLogAgent').value = log.user_id || '';
        document.getElementById('shiftLogShift').value = log.shift || '';
        document.getElementById('shiftLogCheckIn').value = log.check_in_time ? log.check_in_time.slice(0, 5) : '';
        document.getElementById('shiftLogCheckOut').value = log.check_out_time ? log.check_out_time.slice(0, 5) : '';

        new bootstrap.Modal(document.getElementById('shiftLogModal')).show();
    };

    document.querySelectorAll('.shift-log-edit-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            editShiftLog(JSON.parse(this.dataset.log));
        });
    });
</script>
