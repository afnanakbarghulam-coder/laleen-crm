@extends('layouts.app')
@section('title', 'Salon Staff KPIs')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Salon Staff</h4>
            <p>Monitor staff sales performance and weekly evaluations.</p>
        </div>
        <a href="{{ route('kpi.hub') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Categories</a>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('kpi.staff-sales.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-store"></i></div>
                <h6 class="mb-1">Staff Sales Performance</h6>
                <p class="text-muted small mb-0">Per-branch upsell tracking against prorated monthly targets.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('kpi.evaluations.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-star"></i></div>
                <h6 class="mb-1">Salon Staff Evaluation</h6>
                <p class="text-muted small mb-0">Monthly ROI-focused performance scoring by branch leaders.</p>
            </a>
        </div>
    </div>
@endsection
