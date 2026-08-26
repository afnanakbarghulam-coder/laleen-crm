@extends('layouts.app')
@section('title', 'Staff Sales Performance Report')

@php
    $staff = $report->computedStaff();
    $totals = $report->totals();
    $prorated = $report->proratedTarget();
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('kpi.staff-sales.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bx bx-arrow-back me-1"></i> Back to history</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="exportReportJpg('reportCapture', 'staff-sales-report')"><i class="bx bx-download me-1"></i> Export as JPG</button>
    </div>

    <div id="reportCapture" class="kpi-report-capture">
        <div class="kpi-header">
            <div>
                <h4>Staff Sales Performance — {{ $branches[$report->branch] }}</h4>
                <p>{{ $report->date_from->format('d M Y') }} – {{ $report->date_to->format('d M Y') }} &middot; Prorated target: {{ number_format($prorated, 2) }} QAR/staff ({{ $report->daysElapsed() }} of 31 days)</p>
            </div>
            <span class="kpi-badge {{ $totals['team_pct'] >= 85 ? 'kpi-badge-green' : ($totals['team_pct'] >= 50 ? 'kpi-badge-amber' : 'kpi-badge-red') }}">Team: {{ $totals['team_pct'] }}%</span>
        </div>

        <div class="kpi-alert {{ $totals['zero_upsell_count'] > 0 ? 'kpi-alert-red' : 'kpi-alert-green' }}">
            <i class="bx bx-info-circle"></i>
            <div>
                Team achieved {{ number_format($totals['team_total'], 2) }} of {{ number_format($totals['team_target'], 2) }} QAR prorated target ({{ $totals['team_pct'] }}%).
                @if ($totals['zero_upsell_count'] > 0)
                    {{ $totals['zero_upsell_count'] }} {{ Str::plural('staff member', $totals['zero_upsell_count']) }} recorded zero upsell.
                @endif
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Team Total</div>
                    <div class="kpi-stat-value">{{ number_format($totals['team_total'], 0) }} <small class="fs-6">QAR</small></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Prorated Target / Staff</div>
                    <div class="kpi-stat-value">{{ number_format($prorated, 0) }} <small class="fs-6">QAR</small></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Top Performer</div>
                    <div class="kpi-stat-value" style="font-size:19px;">{{ $totals['top_performer'] }}</div>
                    <div class="kpi-stat-sub">{{ number_format($totals['top_performer_amount'], 2) }} QAR</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Zero Upsell</div>
                    <div class="kpi-stat-value">{{ $totals['zero_upsell_count'] }}</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            @foreach ($staff as $s)
                <div class="col-md-6">
                    <div class="kpi-shift-card {{ $s['border'] }}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">{{ $s['name'] }}</h6>
                                <div class="text-muted small">{{ number_format($s['upsell'], 2) }} of {{ number_format($s['prorated_target'], 2) }} QAR</div>
                            </div>
                            <span class="kpi-gap-pill">{{ $s['gap'] > 0 ? 'Gap: ' . number_format($s['gap'], 0) : 'Exceeded by ' . number_format(abs($s['gap']), 0) }}</span>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-3 mb-2">
                            <div class="kpi-shift-big-pct">{{ $s['pct'] }}%</div>
                        </div>
                        <div class="kpi-progress-track">
                            <div class="kpi-progress-fill {{ $s['border'] }}" style="width:{{ min($s['pct'], 100) }}%"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="kpi-panel">
            <h6>Team Total</h6>
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>{{ number_format($totals['team_total'], 2) }} of {{ number_format($totals['team_target'], 2) }} QAR</span>
                <span>{{ $totals['team_pct'] }}%</span>
            </div>
            <div class="kpi-progress-track">
                <div class="kpi-progress-fill {{ $totals['team_pct'] >= 85 ? 'green' : ($totals['team_pct'] >= 50 ? 'amber' : 'red') }}" style="width:{{ min($totals['team_pct'], 100) }}%"></div>
            </div>
        </div>

        <div class="kpi-footer">
            Generated {{ $report->created_at->format('d M Y, h:i A') }} @if($report->creator) by {{ $report->creator->name }} @endif &middot; Laleen Beauty Salon
        </div>
    </div>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    function exportReportJpg(elementId, filename) {
        const el = document.getElementById(elementId);
        html2canvas(el, { backgroundColor: '#14100e', scale: 2 }).then(canvas => {
            const link = document.createElement('a');
            link.download = filename + '-' + new Date().toISOString().slice(0, 10) + '.jpg';
            link.href = canvas.toDataURL('image/jpeg', 0.95);
            link.click();
        });
    }
</script>
@endsection
