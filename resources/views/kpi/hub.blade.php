@extends('layouts.app')
@section('title', 'KPIs')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>KPIs</h4>
            <p>Track performance across marketing, agents, staff sales, chat quality, and content.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <a href="{{ route('kpi.marketing') }}" class="kpi-hub-card kpi-master-card">
                <div class="kpi-hub-icon kpi-hub-icon-lg"><i class="bx bx-line-chart"></i></div>
                <h5 class="mb-1">Marketing</h5>
                <p class="text-muted mb-0">Manage ad conversions and weekly content schedules.</p>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('kpi.customer-service') }}" class="kpi-hub-card kpi-master-card">
                <div class="kpi-hub-icon kpi-hub-icon-lg"><i class="bx bx-support"></i></div>
                <h5 class="mb-1">Customer Service</h5>
                <p class="text-muted mb-0">Track agent targets and evaluate chat quality.</p>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('kpi.salon-staff') }}" class="kpi-hub-card kpi-master-card">
                <div class="kpi-hub-icon kpi-hub-icon-lg"><i class="bx bx-cut"></i></div>
                <h5 class="mb-1">Salon Staff</h5>
                <p class="text-muted mb-0">Monitor staff sales performance and weekly evaluations.</p>
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
