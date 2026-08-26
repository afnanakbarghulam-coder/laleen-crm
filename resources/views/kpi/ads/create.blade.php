@extends('layouts.app')
@section('title', 'New Ads Conversion Report')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>New Ads Conversion Report</h4>
            <p>Enter raw leads/bookings data — conversion %, revenue, and target status are calculated automatically.</p>
        </div>
        <a href="{{ route('kpi.ads.index') }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i> Back to history</a>
    </div>

    <form method="POST" action="{{ route('kpi.ads.store') }}" id="adsForm">
        @csrf

        <div class="kpi-panel">
            <h6>Period</h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control" required>
                </div>
            </div>
        </div>

        <div class="kpi-panel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Categories</h6>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="addCategoryBtn"><i class="bx bx-plus"></i> Add category</button>
            </div>
            <div class="table-responsive">
                <table class="table kpi-table align-middle" id="categoryTable">
                    <thead>
                        <tr>
                            <th style="min-width:180px;">Category name</th>
                            <th>Leads</th>
                            <th>Bookings</th>
                            <th>Avg ticket (QAR)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="categoryRows"></tbody>
                </table>
            </div>
        </div>

        <div class="kpi-panel">
            <h6>Branch Split</h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Old Airport — bookings</label>
                    <input type="number" name="old_airport_bookings" class="form-control" min="0" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Old Airport — revenue (QAR)</label>
                    <input type="number" step="0.01" name="old_airport_revenue" class="form-control" min="0" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Al Wakrah — bookings</label>
                    <input type="number" name="wakrah_bookings" class="form-control" min="0" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Al Wakrah — revenue (QAR)</label>
                    <input type="number" step="0.01" name="wakrah_revenue" class="form-control" min="0" value="0">
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('kpi.ads.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Generate Report</button>
        </div>
    </form>
@endsection

@section('scripts')
<script>
    let categoryIndex = 0;

    function addCategoryRow(name = '', leads = '', bookings = '', avgTicket = '') {
        const idx = categoryIndex++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" name="categories[${idx}][name]" class="form-control form-control-sm" value="${name}" required></td>
            <td><input type="number" name="categories[${idx}][leads]" class="form-control form-control-sm" min="0" value="${leads}" required></td>
            <td><input type="number" name="categories[${idx}][bookings]" class="form-control form-control-sm" min="0" value="${bookings}" required></td>
            <td><input type="number" step="0.01" name="categories[${idx}][avg_ticket]" class="form-control form-control-sm" min="0" value="${avgTicket}" required></td>
            <td><span class="kpi-form-row-remove" onclick="this.closest('tr').remove()"><i class="bx bx-trash"></i></span></td>
        `;
        document.getElementById('categoryRows').appendChild(tr);
    }

    document.getElementById('addCategoryBtn').addEventListener('click', () => addCategoryRow());

    // Seed with a few common categories to start from.
    ['Hair Styling & Color', 'Skincare & Facials', 'Nail Care & Spa'].forEach(name => addCategoryRow(name));

    document.getElementById('adsForm').addEventListener('submit', function (e) {
        if (document.getElementById('categoryRows').children.length === 0) {
            e.preventDefault();
            alert('Add at least one category.');
        }
    });
</script>
@endsection
