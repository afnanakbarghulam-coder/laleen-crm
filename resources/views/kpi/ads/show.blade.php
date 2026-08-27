@extends('layouts.app')
@section('title', 'Ads Conversion Report')

@php
    $categories = $report->computedCategories();
    $totals = $report->totals();
    $criticalOrBelow = array_filter($categories, fn ($c) => in_array($c['status'], ['Below', 'Critical']));
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('kpi.ads.index', ['tab' => 'reports']) }}" class="btn btn-outline-secondary btn-sm"><i class="bx bx-arrow-back me-1"></i> Back to history</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="exportReportJpg('reportCapture', 'ads-conversion-report')"><i class="bx bx-download me-1"></i> Export as JPG</button>
    </div>

    <div id="reportCapture" class="kpi-report-capture">
        <div class="kpi-header">
            <div>
                <h4>Ads Conversion Report</h4>
                <p>{{ $report->date_from->format('d M Y') }} – {{ $report->date_to->format('d M Y') }}</p>
            </div>
            <span class="kpi-badge {{ $totals['overall_met_target'] ? 'kpi-badge-green' : 'kpi-badge-red' }}">
                Overall Conversion: {{ $totals['overall_conversion'] }}% {{ $totals['overall_met_target'] ? '(Target met)' : '(Below 20% target)' }}
            </span>
        </div>

        @if (count($criticalOrBelow))
            <div class="kpi-alert kpi-alert-red">
                <i class="bx bx-error-circle"></i>
                <div>
                    <strong>{{ count($criticalOrBelow) }} of {{ count($categories) }} {{ Str::plural('category', count($criticalOrBelow)) }} below the 20% conversion target:</strong>
                    {{ collect($criticalOrBelow)->pluck('name')->join(', ') }}.
                </div>
            </div>
        @else
            <div class="kpi-alert kpi-alert-green">
                <i class="bx bx-check-circle"></i>
                <div>All categories are meeting or nearing the 20% conversion target.</div>
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Overall Conversion</div>
                    <div class="kpi-stat-value">{{ $totals['overall_conversion'] }}%</div>
                    <div class="kpi-stat-sub">Target: 20%</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Total Leads</div>
                    <div class="kpi-stat-value">{{ $totals['total_leads'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Total Bookings</div>
                    <div class="kpi-stat-value">{{ $totals['total_bookings'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Total Revenue</div>
                    <div class="kpi-stat-value">{{ number_format($totals['total_revenue'], 0) }} <small class="fs-6">QAR</small></div>
                </div>
            </div>
        </div>

        <div class="kpi-panel">
            <h6>Category Breakdown</h6>
            <div class="table-responsive">
                <table class="table kpi-table align-middle">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Leads</th>
                            <th>Bookings</th>
                            <th style="min-width:140px;">Conversion</th>
                            <th>Avg Ticket</th>
                            <th>Revenue</th>
                            <th>vs 20% Target</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $c)
                            @php
                                $barColor = match($c['status']) { 'Above' => 'green', 'Near' => 'amber', 'Below' => 'amber', default => 'red' };
                                $badgeColor = match($c['status']) { 'Above' => 'kpi-badge-green', 'Near' => 'kpi-badge-amber', 'Below' => 'kpi-badge-amber', default => 'kpi-badge-red' };
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $c['name'] }}</td>
                                <td>{{ $c['leads'] }}</td>
                                <td>{{ $c['bookings'] }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="kpi-progress-track" style="width:80px;">
                                            <div class="kpi-progress-fill {{ $barColor }}" style="width:{{ min($c['conversion'] / 20 * 100, 100) }}%"></div>
                                        </div>
                                        <span class="small fw-semibold">{{ $c['conversion'] }}%</span>
                                    </div>
                                </td>
                                <td>{{ number_format($c['avg_ticket'], 2) }}</td>
                                <td>{{ number_format($c['revenue'], 2) }}</td>
                                <td>{{ $c['pct_of_target'] }}%</td>
                                <td><span class="kpi-badge {{ $badgeColor }}">{{ $c['status'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="kpi-panel h-100">
                    <h6>Branch Performance</h6>
                    <table class="table kpi-table">
                        <thead><tr><th>Branch</th><th class="text-end">Bookings</th><th class="text-end">Revenue (QAR)</th></tr></thead>
                        <tbody>
                            <tr>
                                <td class="fw-semibold">Old Airport</td>
                                <td class="text-end">{{ $report->old_airport_bookings }}</td>
                                <td class="text-end">{{ number_format($report->old_airport_revenue, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Al Wakrah</td>
                                <td class="text-end">{{ $report->wakrah_bookings }}</td>
                                <td class="text-end">{{ number_format($report->wakrah_revenue, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="kpi-panel h-100">
                    <h6>Category Action Recommendations</h6>
                    @foreach ($categories as $c)
                        <div class="mb-2 small">
                            <strong>{{ $c['name'] }}:</strong> {{ $report->statusRecommendation($c['status']) }}
                        </div>
                    @endforeach
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
