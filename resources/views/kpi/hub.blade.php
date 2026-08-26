@extends('layouts.app')
@section('title', 'KPIs')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>KPIs</h4>
            <p>Track performance across marketing, agents, staff sales, chat quality, and content.</p>
        </div>
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
            <a href="{{ route('kpi.agents.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-target-lock"></i></div>
                <h6 class="mb-1">Agents Target Report</h6>
                <p class="text-muted small mb-0">Morning &amp; evening shift booking targets with recovery math.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('kpi.staff-sales.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-store"></i></div>
                <h6 class="mb-1">Staff Sales Performance</h6>
                <p class="text-muted small mb-0">Per-branch upsell tracking against prorated monthly targets.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('kpi.chat-eval.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-chat"></i></div>
                <h6 class="mb-1">Chat Evaluation Report</h6>
                <p class="text-muted small mb-0">15-question quality scorecard with performance journey trend.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('kpi.content.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-image-alt"></i></div>
                <h6 class="mb-1">Content KPI Report</h6>
                <p class="text-muted small mb-0">Daily posting &amp; standards compliance for feed and stories.</p>
            </a>
        </div>
    </div>
@endsection
