@extends('layouts.app')
@section('title', 'Chat Evaluation Report')

@php
    $answers = $report->computedAnswers();
    $pct = $report->percentage();
    $grade = $report->grade();
    $gradeBadge = match($grade) { 'Excellent' => 'kpi-badge-green', 'Pass' => 'kpi-badge-green', 'Warning' => 'kpi-badge-amber', default => 'kpi-badge-red' };
    $history = $report->history(10);
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('kpi.chat-eval.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bx bx-arrow-back me-1"></i> Back to history</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="exportReportJpg('reportCapture', 'chat-evaluation-report')"><i class="bx bx-download me-1"></i> Export as JPG</button>
    </div>

    <div id="reportCapture" class="kpi-report-capture">
        <div class="kpi-header">
            <div class="d-flex align-items-center gap-3">
                <img src="{{ asset('images/laleen logo1.PNG') }}" style="width:44px;height:44px;border-radius:8px;" alt="Laleen">
                <div>
                    <h4 class="mb-0">Chat Evaluation Report</h4>
                    <p>{{ $report->coordinator_name }} &middot; {{ $report->eval_date->format('d M Y') }}</p>
                </div>
            </div>
            <span class="kpi-badge {{ $gradeBadge }}">{{ $grade }} — {{ $pct }}%</span>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3 col-6">
                <div class="kpi-stat-card"><div class="kpi-stat-label">Date</div><div class="kpi-stat-value" style="font-size:18px;">{{ $report->eval_date->format('d M Y') }}</div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="kpi-stat-card"><div class="kpi-stat-label">Coordinator</div><div class="kpi-stat-value" style="font-size:18px;">{{ $report->coordinator_name }}</div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="kpi-stat-card"><div class="kpi-stat-label">Chats Reviewed</div><div class="kpi-stat-value">{{ $report->chats_reviewed }}</div></div>
            </div>
            <div class="col-md-3 col-6">
                <div class="kpi-stat-card"><div class="kpi-stat-label">Final Score</div><div class="kpi-stat-value">{{ $report->totalScore() }} <small class="fs-6">/ {{ \App\Models\KpiChatEvaluation::maxPossible() }}</small></div></div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card"><div class="kpi-stat-label">Score %</div><div class="kpi-stat-value">{{ $pct }}%</div></div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card"><div class="kpi-stat-label">Passed Questions</div><div class="kpi-stat-value">{{ $report->passedCount() }} / 15</div></div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card"><div class="kpi-stat-label">Failed Questions</div><div class="kpi-stat-value">{{ $report->failedCount() }} / 15</div></div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card"><div class="kpi-stat-label">Consecutive Excellent</div><div class="kpi-stat-value">{{ $report->consecutiveExcellent() }}</div></div>
            </div>
        </div>

        <div class="kpi-panel">
            <h6>Question Breakdown</h6>
            <div class="table-responsive">
                <table class="table kpi-table align-middle">
                    <thead><tr><th>#</th><th>Question</th><th>Answer</th><th class="text-end">Score</th></tr></thead>
                    <tbody>
                        @foreach ($answers as $a)
                            <tr>
                                <td>{{ $a['number'] }}</td>
                                <td>{{ $a['text'] }}</td>
                                <td><span class="kpi-yn-badge {{ $a['passed'] ? 'yes' : 'no' }}">{{ $a['answer'] }}</span></td>
                                <td class="text-end">{{ $a['score'] }} / {{ $a['max'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="kpi-panel">
            <h6>Score Breakdown</h6>
            @foreach ($answers as $a)
                <div class="mb-2">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>{{ $a['number'] }}. {{ $a['text'] }}</span>
                        <span>{{ $a['score'] }}/{{ $a['max'] }}</span>
                    </div>
                    <div class="kpi-progress-track">
                        <div class="kpi-progress-fill {{ $a['passed'] ? 'green' : 'red' }}" style="width:{{ $a['max'] > 0 ? min($a['score'] / $a['max'] * 100, 100) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($history->count() > 1)
            <div class="kpi-panel">
                <h6>Performance Journey — {{ $report->coordinator_name }}</h6>
                <div id="journeyChart"></div>
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

    @if ($history->count() > 1)
    new ApexCharts(document.querySelector('#journeyChart'), {
        chart: { type: 'line', height: 260, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'Score %', data: @json($history->pluck('pct')) }],
        xaxis: { categories: @json($history->pluck('date')) },
        colors: ['#d98f83'],
        stroke: { curve: 'smooth', width: 3 },
        markers: { size: 4 },
        yaxis: { min: 0, max: 100 },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(217,143,131,0.15)' },
        tooltip: { y: { formatter: (v) => v + '%' } },
    }).render();
    @endif
</script>
@endsection
