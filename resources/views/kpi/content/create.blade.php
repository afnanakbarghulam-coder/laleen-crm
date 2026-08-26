@extends('layouts.app')
@section('title', 'New Content KPI Report')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>New Content KPI Report</h4>
            <p>Log daily posting activity — posted %, standards compliance, and overall score are calculated automatically.</p>
        </div>
        <a href="{{ route('kpi.content.index') }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i> Back to history</a>
    </div>

    <form method="POST" action="{{ route('kpi.content.store') }}" id="contentForm">
        @csrf

        <div class="kpi-panel">
            <h6>Report Details</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Creator</label>
                    <input type="text" name="creator_name" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" required>
                </div>
            </div>
        </div>

        <div class="kpi-panel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Daily Log</h6>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="addDayBtn"><i class="bx bx-plus"></i> Add day</button>
            </div>
            <div class="table-responsive">
                <table class="table kpi-table align-middle" id="dayTable">
                    <thead>
                        <tr>
                            <th style="min-width:150px;">Date</th>
                            <th style="min-width:140px;">Activity Type</th>
                            <th>Feed Sched.</th>
                            <th>Stories Sched.</th>
                            <th>Feed Posted</th>
                            <th>Stories Posted</th>
                            <th>Std. Feed</th>
                            <th>Std. Stories</th>
                            <th style="min-width:160px;">Issues</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="dayRows"></tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('kpi.content.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Generate Report</button>
        </div>
    </form>
@endsection

@section('scripts')
<script>
    let dayIndex = 0;

    function checkbox(name) {
        return `<div class="form-check form-switch d-flex justify-content-center">
            <input class="form-check-input" type="checkbox" name="${name}" value="1" checked>
        </div>`;
    }

    function naSelect(name) {
        return `<select name="${name}" class="form-select form-select-sm">
            <option value="Y">Yes</option>
            <option value="N">No</option>
            <option value="NA" selected>N/A</option>
        </select>`;
    }

    function addDayRow() {
        const idx = dayIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="date" name="entries[${idx}][entry_date]" class="form-control form-control-sm" required></td>
            <td><input type="text" name="entries[${idx}][activity_type]" class="form-control form-control-sm" placeholder="Reel, Carousel..."></td>
            <td>${checkbox(`entries[${idx}][feed_scheduled]`)}</td>
            <td>${checkbox(`entries[${idx}][stories_scheduled]`)}</td>
            <td>${checkbox(`entries[${idx}][feed_posted]`)}</td>
            <td>${checkbox(`entries[${idx}][stories_posted]`)}</td>
            <td>${naSelect(`entries[${idx}][standards_feed]`)}</td>
            <td>${naSelect(`entries[${idx}][standards_stories]`)}</td>
            <td><input type="text" name="entries[${idx}][issues]" class="form-control form-control-sm" placeholder="Optional"></td>
            <td><span class="kpi-form-row-remove" onclick="this.closest('tr').remove()"><i class="bx bx-trash"></i></span></td>
        `;
        document.getElementById('dayRows').appendChild(tr);
    }

    document.getElementById('addDayBtn').addEventListener('click', addDayRow);
    for (let i = 0; i < 7; i++) addDayRow();

    document.getElementById('contentForm').addEventListener('submit', function (e) {
        if (document.getElementById('dayRows').children.length === 0) {
            e.preventDefault();
            alert('Add at least one day.');
        }
    });
</script>
@endsection
