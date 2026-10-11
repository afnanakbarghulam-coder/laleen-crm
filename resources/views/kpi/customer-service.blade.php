@extends('layouts.app')
@section('title', 'Customer Service KPIs')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Customer Service</h4>
            <p>Track agent targets and evaluate chat quality.</p>
        </div>
        <a href="{{ route('kpi.hub') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Categories</a>
    </div>

    <div class="row g-3">
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('kpi.agents.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-target-lock"></i></div>
                <h6 class="mb-1">Agents Target Report</h6>
                <p class="text-muted small mb-0">Morning &amp; evening shift booking targets with recovery math.</p>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('kpi.chat-eval.index') }}" class="kpi-hub-card">
                <div class="kpi-hub-icon"><i class="bx bx-chat"></i></div>
                <h6 class="mb-1">Chat Evaluation Report</h6>
                <p class="text-muted small mb-0">15-question quality scorecard with performance journey trend.</p>
            </a>
        </div>
    </div>
@endsection
