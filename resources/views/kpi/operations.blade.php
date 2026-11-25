@extends('layouts.app')
@section('title', 'Operations — Customer Service, Marketing & Staff')

@section('content')
    <div class="kpi-header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h4 class="mb-1">Operations Hub</h4>
            <p class="mb-0">Manage daily operational workflows across Marketing, Customer Service, and Salon Staff.</p>
        </div>
    </div>

    {{-- 3 Main Department Cards (Marketing, Customer Service, Salon Staff) --}}
    <div class="row g-4">
        <div class="col-md-4">
            <a href="{{ route('kpi.marketing.index') }}" class="kpi-hub-card kpi-master-card">
                <div class="kpi-hub-icon kpi-hub-icon-lg"><i class="bx bx-line-chart"></i></div>
                <h5 class="mb-1">Marketing</h5>
                <p class="text-muted mb-0">Manage ad conversions and weekly content schedules.</p>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('kpi.customer-service.index') }}" class="kpi-hub-card kpi-master-card">
                <div class="kpi-hub-icon kpi-hub-icon-lg"><i class="bx bx-support"></i></div>
                <h5 class="mb-1">Customer Service</h5>
                <p class="text-muted mb-0">Track agent targets and evaluate chat quality.</p>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('kpi.salon-staff.index') }}" class="kpi-hub-card kpi-master-card">
                <div class="kpi-hub-icon kpi-hub-icon-lg"><i class="bx bx-cut"></i></div>
                <h5 class="mb-1">Salon Staff</h5>
                <p class="text-muted mb-0">Manage monthly salon staff evaluations and comprehensive branch assessments.</p>
            </a>
        </div>
    </div>

    <style>
        .kpi-master-card {
            padding: 32px 28px;
            text-align: center;
        }

        .kpi-hub-icon-lg {
            width: 64px;
            height: 64px;
            font-size: 30px;
            border-radius: 16px;
            margin: 0 auto 18px;
        }

        .kpi-master-card h5 {
            font-size: 20px;
        }
    </style>
@endsection
