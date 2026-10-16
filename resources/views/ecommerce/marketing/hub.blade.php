@extends('layouts.app')
@section('title', 'Ecommerce Marketing')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Marketing</h4>
            <p>The weekly content factory that creates demand, and the monthly ads plan that scales it.</p>
        </div>
        <a href="{{ route('ecommerce.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Ecommerce</a>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <a href="{{ route('ecommerce.marketing.ads-plan.index') }}" class="kpi-hub-card kpi-master-card">
                <div class="kpi-hub-icon kpi-hub-icon-lg"><i class="bx bx-rocket"></i></div>
                <h5 class="mb-1">Monthly Ads Plan</h5>
                <p class="text-muted mb-2">Kanban board gating every ad through Planning, Production, Testing and Scaling on real CPL.</p>
                <span class="ec-hub-stat">
                    {{ $adsInFlight }} in flight &middot; {{ $adsScaling }} scaling
                </span>
            </a>
        </div>

        <div class="col-md-6">
            <a href="{{ route('ecommerce.marketing.content.index') }}" class="kpi-hub-card kpi-master-card">
                <div class="kpi-hub-icon kpi-hub-icon-lg"><i class="bx bx-calendar"></i></div>
                <h5 class="mb-1">Weekly Content Calendar</h5>
                <p class="text-muted mb-2">Monday&ndash;Sunday pillar rotation with inline status and staff assignment.</p>
                <span class="ec-hub-stat">
                    {{ $contentLive }}/{{ $contentSlots }} slot(s) live
                </span>
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

        .kpi-master-card h5 { font-size: 20px; }

        .ec-hub-stat {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(217, 143, 131, 0.1);
            border: 1px solid rgba(217, 143, 131, 0.2);
        }
    </style>
@endsection
