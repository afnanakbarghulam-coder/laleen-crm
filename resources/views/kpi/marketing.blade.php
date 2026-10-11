@extends('layouts.app')
@section('title', 'Marketing KPIs')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Marketing</h4>
            <p>Manage ad conversions and weekly content schedules.</p>
        </div>
        <a href="{{ route('kpi.hub') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Categories</a>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('kpi.ads.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-trending-up"></i></div>
                <h6 class="mb-1">Ads Conversion Report</h6>
                <p class="text-muted small mb-0">Leads-to-booking conversion by category, branch split, and 20% target tracking.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('kpi.content-calendar.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-calendar"></i></div>
                <h6 class="mb-1">Weekly Content Calendar</h6>
                <p class="text-muted small mb-0">Plan daily reels and stories schedules.</p>
            </a>
        </div>
    </div>
@endsection
