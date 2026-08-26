@extends('layouts.app')
@section('title', 'Agents Target Report')

@php
    $shifts = $report->shiftStats();
    $combined = $report->combined();
    $recovery = $report->recoveryMath();
    $overallBorder = $report->borderFor($combined['pct']);
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('kpi.agents.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bx bx-arrow-back me-1"></i> Back to history</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="exportReportJpg('reportCapture', 'agents-target-report')"><i class="bx bx-download me-1"></i> Export as JPG</button>
    </div>

    <div id="reportCapture" class="kpi-report-capture">
        <div class="kpi-header">
            <div>
                <h4>Agents Target Report</h4>
                <p>{{ $report->date_from->format('d M Y') }} – {{ $report->date_to->format('d M Y') }} &middot; Target: 13 bookings/day/shift (403/month)</p>
            </div>
            <span class="kpi-badge kpi-badge-{{ $overallBorder }}">Combined: {{ $combined['pct'] }}%</span>
        </div>

        <div class="kpi-alert kpi-alert-{{ $overallBorder }}">
            <i class="bx bx-info-circle"></i>
            <div>
                @if ($combined['pct'] >= 85)
                    Both shifts are tracking well against target. Combined gap is {{ $combined['gap'] }} bookings.
                @else
                    Combined bookings are at {{ $combined['pct'] }}% of target with a gap of {{ $combined['gap'] }} bookings.
                    {{ $recovery['days_remaining'] }} {{ Str::plural('day', $recovery['days_remaining']) }} remain this month to recover.
                @endif
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Overall %</div>
                    <div class="kpi-stat-value">{{ $combined['pct'] }}%</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Total Bookings</div>
                    <div class="kpi-stat-value">{{ $combined['bookings'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Days Remaining</div>
                    <div class="kpi-stat-value">{{ $recovery['days_remaining'] }}</div>
                    <div class="kpi-stat-sub">In current month</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Total Gap</div>
                    <div class="kpi-stat-value">{{ $combined['gap'] }}</div>
                    <div class="kpi-stat-sub">vs entered target</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            @foreach (['morning' => 'Morning Shift', 'evening' => 'Evening Shift'] as $key => $label)
                @php $s = $shifts[$key]; @endphp
                <div class="col-md-6">
                    <div class="kpi-shift-card {{ $s['border'] }}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">{{ $label }}</h6>
                                <div class="text-muted small">{{ $s['bookings'] }} of {{ $s['target'] }} bookings</div>
                            </div>
                            <span class="kpi-gap-pill">Gap: {{ $s['gap'] }}</span>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-3 mb-2">
                            <div class="kpi-shift-big-pct">{{ $s['pct'] }}%</div>
                            <div class="text-end small text-muted">
                                Daily avg: {{ $s['daily_avg'] }}<br>
                                @if ($s['change'] !== null)
                                    vs prior: <span class="{{ $s['change'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $s['change'] >= 0 ? '+' : '' }}{{ $s['change'] }}%</span>
                                @endif
                            </div>
                        </div>
                        <div class="kpi-progress-track">
                            <div class="kpi-progress-fill {{ $s['border'] }}" style="width:{{ min($s['pct'], 100) }}%"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="kpi-panel">
            <h6>Combined Total</h6>
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>{{ $combined['bookings'] }} of {{ $combined['target'] }} bookings</span>
                <span>{{ $combined['pct'] }}%</span>
            </div>
            <div class="kpi-progress-track">
                <div class="kpi-progress-fill {{ $overallBorder }}" style="width:{{ min($combined['pct'], 100) }}%"></div>
            </div>
        </div>

        <div class="kpi-panel">
            <h6>Recovery Math <span class="text-muted small fw-normal">— to reach 88% of the 403/shift monthly target</span></h6>
            <div class="table-responsive">
                <table class="table kpi-table">
                    <thead><tr><th>Shift</th><th class="text-end">88% Target</th><th class="text-end">Remaining Needed</th><th class="text-end">Bookings/Day Needed</th></tr></thead>
                    <tbody>
                        @foreach (['morning' => 'Morning', 'evening' => 'Evening'] as $key => $label)
                            @php $r = $recovery[$key]; @endphp
                            <tr>
                                <td class="fw-semibold">{{ $label }}</td>
                                <td class="text-end">{{ $r['target_88'] }}</td>
                                <td class="text-end">{{ $r['on_track'] ? '—' : $r['remaining_needed'] }}</td>
                                <td class="text-end fw-semibold">{{ $r['on_track'] ? 'On track' : $r['per_day'] . '/day' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($shifts['morning']['prev_pct'] !== null || $shifts['evening']['prev_pct'] !== null)
            <div class="kpi-panel">
                <h6>Previous Period Comparison</h6>
                <div class="table-responsive">
                    <table class="table kpi-table">
                        <thead><tr><th>Shift</th><th class="text-end">Previous %</th><th class="text-end">Current %</th><th class="text-end">Change</th></tr></thead>
                        <tbody>
                            @foreach (['morning' => 'Morning', 'evening' => 'Evening'] as $key => $label)
                                @php $s = $shifts[$key]; @endphp
                                @if ($s['prev_pct'] !== null)
                                    <tr>
                                        <td class="fw-semibold">{{ $label }}</td>
                                        <td class="text-end">{{ $s['prev_pct'] }}%</td>
                                        <td class="text-end">{{ $s['pct'] }}%</td>
                                        <td class="text-end {{ $s['change'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $s['change'] >= 0 ? '+' : '' }}{{ $s['change'] }}%</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <div class="kpi-insight-card">
                    <i class="bx bx-sun"></i>Morning shift is at <strong>{{ $shifts['morning']['pct'] }}%</strong> of its target with a daily average of {{ $shifts['morning']['daily_avg'] }} bookings.
                </div>
            </div>
            <div class="col-md-6">
                <div class="kpi-insight-card">
                    <i class="bx bx-moon"></i>Evening shift is at <strong>{{ $shifts['evening']['pct'] }}%</strong> of its target with a daily average of {{ $shifts['evening']['daily_avg'] }} bookings.
                </div>
            </div>
            <div class="col-md-6">
                <div class="kpi-insight-card">
                    <i class="bx bx-trending-up"></i>Combined performance sits at <strong>{{ $combined['pct'] }}%</strong> of the entered target, a gap of {{ $combined['gap'] }} bookings.
                </div>
            </div>
            <div class="col-md-6">
                <div class="kpi-insight-card">
                    <i class="bx bx-calendar"></i><strong>{{ $recovery['days_remaining'] }}</strong> {{ Str::plural('day', $recovery['days_remaining']) }} remain this month to hit the 88% recovery target.
                </div>
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
