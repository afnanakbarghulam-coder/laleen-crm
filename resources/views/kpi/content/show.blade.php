@extends('layouts.app')
@section('title', 'Content KPI Report')

@php
    $metrics = $report->metrics();
    $gradeBadge = match($metrics['grade']) { 'Excellent' => 'kpi-badge-green', 'Pass' => 'kpi-badge-green', 'Warning' => 'kpi-badge-amber', default => 'kpi-badge-red' };
    $flagged = $report->flaggedDays();

    function ynBadge($value) {
        if ($value === 'Y' || $value === true) return '<span class="kpi-yn-badge yes">Y</span>';
        if ($value === 'N' || $value === false) return '<span class="kpi-yn-badge no">N</span>';
        return '<span class="kpi-yn-badge na">N/A</span>';
    }
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('kpi.content.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bx bx-arrow-back me-1"></i> Back to history</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="exportReportJpg('reportCapture', 'content-kpi-report')"><i class="bx bx-download me-1"></i> Export as JPG</button>
    </div>

    <div id="reportCapture" class="kpi-report-capture">
        <div class="kpi-header">
            <div class="d-flex align-items-center gap-3">
                <img src="{{ asset('images/laleen logo1.PNG') }}" style="width:44px;height:44px;border-radius:8px;" alt="Laleen">
                <div>
                    <h4 class="mb-0">Content KPI Report — {{ $report->creator_name }}</h4>
                    <p>{{ $report->date_from->format('d M Y') }} – {{ $report->date_to->format('d M Y') }}</p>
                </div>
            </div>
            <span class="kpi-badge {{ $gradeBadge }}">{{ $metrics['grade'] }} — {{ $metrics['overall'] }}%</span>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card"><div class="kpi-stat-label">Feed Posted</div><div class="kpi-stat-value">{{ $metrics['feed_posted'] }}%</div></div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card"><div class="kpi-stat-label">Stories Posted</div><div class="kpi-stat-value">{{ $metrics['stories_posted'] }}%</div></div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card"><div class="kpi-stat-label">Standards — Feed</div><div class="kpi-stat-value">{{ $metrics['standards_feed'] }}%</div></div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card"><div class="kpi-stat-label">Standards — Stories</div><div class="kpi-stat-value">{{ $metrics['standards_stories'] }}%</div></div>
            </div>
        </div>

        <div class="kpi-panel">
            <h6>Metrics vs Targets</h6>
            @foreach ([
                ['Feed Posted', $metrics['feed_posted'], 100],
                ['Stories Posted', $metrics['stories_posted'], 100],
                ['Standards Met — Feed', $metrics['standards_feed'], 90],
                ['Standards Met — Stories', $metrics['standards_stories'], 90],
            ] as [$label, $value, $target])
                <div class="mb-2">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>{{ $label }}</span>
                        <span>{{ $value }}% <span class="text-muted">/ target {{ $target }}%</span></span>
                    </div>
                    <div class="kpi-progress-track">
                        <div class="kpi-progress-fill {{ $value >= $target ? 'green' : ($value >= $target - 20 ? 'amber' : 'red') }}" style="width:{{ min($value, 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="kpi-panel">
            <h6>Daily Breakdown</h6>
            <div class="table-responsive">
                <table class="table kpi-table align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Activity</th>
                            <th>Feed Post</th>
                            <th>Stories</th>
                            <th>Std. Feed</th>
                            <th>Std. Stories</th>
                            <th>Issues</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report->entries as $e)
                            <tr>
                                <td>{{ $e->entry_date->format('d M') }}</td>
                                <td>{{ $e->dayName() }}</td>
                                <td>{{ $e->activity_type ?: '—' }}</td>
                                <td>{!! $e->feed_scheduled ? ($e->feed_posted ? ynBadge('Y') : ynBadge('N')) : ynBadge('NA') !!}</td>
                                <td>{!! $e->stories_scheduled ? ($e->stories_posted ? ynBadge('Y') : ynBadge('N')) : ynBadge('NA') !!}</td>
                                <td>{!! ynBadge($e->standards_feed) !!}</td>
                                <td>{!! ynBadge($e->standards_stories) !!}</td>
                                <td class="small">{{ $e->issues ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($flagged->count())
            <div class="kpi-alert kpi-alert-amber">
                <i class="bx bx-flag"></i>
                <div>
                    <strong>{{ $flagged->count() }} flagged {{ Str::plural('day', $flagged->count()) }}:</strong>
                    @foreach ($flagged as $f)
                        <div class="small mt-1">{{ $f->entry_date->format('d M') }} — {{ $f->issues }}</div>
                    @endforeach
                </div>
            </div>
        @endif

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
