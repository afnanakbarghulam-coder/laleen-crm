@extends('layouts.app')
@section('title', 'New Staff Sales Report')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>New Staff Sales Performance Report</h4>
            <p>Enter each staff member's upsell total — prorated targets, gaps, and team achievement are calculated automatically.</p>
        </div>
        <a href="{{ route('kpi.staff-sales.index') }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i> Back to history</a>
    </div>

    <form method="POST" action="{{ route('kpi.staff-sales.store') }}" id="staffForm">
        @csrf

        <div class="kpi-panel">
            <h6>Report Details</h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Branch</label>
                    <select name="branch" class="form-select" required>
                        <option value="">Select branch</option>
                        @foreach ($branches as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Monthly target / staff (QAR)</label>
                    <input type="number" step="0.01" name="monthly_target_per_staff" class="form-control" value="1700" required>
                </div>
            </div>
        </div>

        <div class="kpi-panel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Staff Upsell</h6>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="addStaffBtn"><i class="bx bx-plus"></i> Add staff member</button>
            </div>
            <div class="table-responsive">
                <table class="table kpi-table align-middle">
                    <thead><tr><th style="min-width:220px;">Staff name</th><th>Total upsell (QAR)</th><th></th></tr></thead>
                    <tbody id="staffRows"></tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('kpi.staff-sales.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Generate Report</button>
        </div>
    </form>
@endsection

@section('scripts')
<script>
    let staffIndex = 0;

    function addStaffRow(name = '', upsell = '') {
        const idx = staffIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" name="staff[${idx}][name]" class="form-control form-control-sm" value="${name}" required></td>
            <td><input type="number" step="0.01" name="staff[${idx}][upsell]" class="form-control form-control-sm" min="0" value="${upsell}" required></td>
            <td><span class="kpi-form-row-remove" onclick="this.closest('tr').remove()"><i class="bx bx-trash"></i></span></td>
        `;
        document.getElementById('staffRows').appendChild(tr);
    }

    document.getElementById('addStaffBtn').addEventListener('click', () => addStaffRow());
    addStaffRow();
    addStaffRow();

    document.getElementById('staffForm').addEventListener('submit', function (e) {
        if (document.getElementById('staffRows').children.length === 0) {
            e.preventDefault();
            alert('Add at least one staff member.');
        }
    });
</script>
@endsection
