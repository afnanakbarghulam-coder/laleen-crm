@extends('layouts.app')
@section('title', 'New Agents Target Report')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>New Agents Target Report</h4>
            <p>Enter shift bookings/targets — conversion %, gaps, and recovery math are calculated automatically.</p>
        </div>
        <a href="{{ route('kpi.agents.index') }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i> Back to history</a>
    </div>

    <form method="POST" action="{{ route('kpi.agents.store') }}">
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

        <div class="row g-3">
            <div class="col-md-6">
                <div class="kpi-panel h-100">
                    <h6>Morning Shift</h6>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Bookings</label>
                            <input type="number" name="morning_bookings" class="form-control" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Target</label>
                            <input type="number" name="morning_target" class="form-control" min="0" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Previous period % <span class="text-muted">(optional)</span></label>
                            <input type="number" step="0.1" name="prev_morning_pct" class="form-control" min="0">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="kpi-panel h-100">
                    <h6>Evening Shift</h6>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Bookings</label>
                            <input type="number" name="evening_bookings" class="form-control" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Target</label>
                            <input type="number" name="evening_target" class="form-control" min="0" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Previous period % <span class="text-muted">(optional)</span></label>
                            <input type="number" step="0.1" name="prev_evening_pct" class="form-control" min="0">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('kpi.agents.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Generate Report</button>
        </div>
    </form>
@endsection
