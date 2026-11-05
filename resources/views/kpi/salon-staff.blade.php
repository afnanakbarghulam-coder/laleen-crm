@extends('layouts.app')
@section('title', 'Salon Staff KPIs')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Salon Staff</h4>
            <p>Manage monthly salon staff evaluations and comprehensive branch assessments.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('kpi.operations') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Operations</a>
            <a href="{{ route('kpi.hub') }}" class="btn btn-sm btn-outline-primary"><i class="bx bx-line-chart me-1"></i>Executive KPIs</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <a href="{{ route('kpi.evaluations.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-star"></i></div>
                <h6 class="mb-1">Salon Staff Evaluation</h6>
                <p class="text-muted small mb-0">Monthly ROI-focused performance scoring by branch leaders.</p>
            </a>
        </div>
        <div class="col-md-6">
            <a href="{{ route('kpi.branch-evaluation.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-clipboard"></i></div>
                <h6 class="mb-1">Branch Evaluation</h6>
                <p class="text-muted small mb-0">Comprehensive mystery shopper and manager assessments.</p>
            </a>
        </div>
    </div>
@endsection
