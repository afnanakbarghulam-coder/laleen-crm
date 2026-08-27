@extends('layouts.app')
@section('title', 'New Ads Conversion Report')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>New Ads Conversion Report</h4>
            <p>Pick a date range — leads, bookings, conversion %, revenue, and branch split are calculated automatically from the Ad Leads Data Entry log.</p>
        </div>
        <a href="{{ route('kpi.ads.index', ['tab' => 'reports']) }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i> Back to history</a>
    </div>

    <form method="POST" action="{{ route('kpi.ads.store') }}">
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

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('kpi.ads.index', ['tab' => 'reports']) }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Generate Report</button>
        </div>
    </form>
@endsection
