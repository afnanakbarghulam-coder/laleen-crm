@extends('layouts.app')
@section('title', 'Chat Evaluation Report')

@php
    $questionResults = $report->questionResults()->keyBy('num');
    $sectionBreakdown = $report->sectionBreakdown()->keyBy('label');
    $grade = $report->grade();
    $gradeBadge = match ($grade) {
        'Excellent' => 'kpi-badge-green',
        'Pass' => 'kpi-badge-green',
        'Warning' => 'kpi-badge-amber',
        default => 'kpi-badge-red',
    };
    $pctDisplay = rtrim(rtrim(number_format((float) $report->percentage, 2), '0'), '.');
@endphp

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Chat Evaluation Report</h4>
            <p>{{ $report->agent->name ?? '—' }} &middot; {{ $report->evaluation_date->format('d M Y') }} &middot; Evaluated by {{ $report->evaluator->name ?? '—' }}</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('kpi.chat-eval.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Chat Evaluations</a>
            @moduleEdit('kpis')
                <a href="{{ route('kpi.chat-eval.edit', $report) }}" class="btn btn-sm btn-primary"><i class="bx bx-pencil me-1"></i>Edit Report</a>
            @endmoduleEdit
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Total Score</div>
                <div class="kpi-stat-value">{{ $report->total_score }} <small class="fs-6">/ {{ \App\Models\KpiChatEvaluation::maxScore() }}</small></div>
                <div class="kpi-stat-sub">{{ $pctDisplay }}% overall</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Grade</div>
                <div class="kpi-stat-value"><span class="kpi-badge {{ $gradeBadge }}" style="font-size:14px;">{{ $grade }}</span></div>
                <div class="kpi-stat-sub">Excellent 90%+ &middot; Pass 75%+</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Passed Questions</div>
                <div class="kpi-stat-value">{{ $report->passedCount() }} <small class="fs-6">/ {{ count(\App\Models\KpiChatEvaluation::QUESTIONS) }}</small></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Failed Questions</div>
                <div class="kpi-stat-value">{{ $report->failedCount() }} <small class="fs-6">/ {{ count(\App\Models\KpiChatEvaluation::QUESTIONS) }}</small></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            @foreach (\App\Models\KpiChatEvaluation::BREAKDOWN_GROUPS as $category => $questionNumbers)
                @php $group = $sectionBreakdown[$category]; @endphp
                <div class="ce-section">
                    <div class="ce-section-header">
                        <div class="ce-section-title">{{ $category }}</div>
                        <div class="ce-section-fraction">{{ $group['score'] }}/{{ $group['max'] }}</div>
                    </div>
                    <div class="ce-section-table">
                        @foreach ($questionNumbers as $num)
                            @php $q = $questionResults[$num]; @endphp
                            <div class="ce-q-row">
                                <div class="ce-q-main">
                                    <div class="ce-q-text">{{ $num }}. {{ $q['text'] }}</div>
                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                        <span class="ce-answer is-{{ $q['passed'] ? 'pass' : 'fail' }}">
                                            {{ $q['passed'] ? '✅ Pass' : '❌ Fail' }}
                                        </span>
                                        <span class="ce-q-score">{{ $q['score'] }} / {{ $q['weight'] }}</span>
                                    </div>
                                </div>
                                @if ($q['remark'])
                                    <div class="ce-remark">{{ $q['remark'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($report->notes)
                <div class="ce-leader-notes">
                    <div class="ce-leader-notes-title">Leader Notes</div>
                    <p class="ce-leader-notes-text">{{ $report->notes }}</p>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="kpi-panel h-100">
                <h6>Score Breakdown</h6>
                @foreach ($sectionBreakdown as $group)
                    <div class="ce-breakdown-row">
                        <span class="ce-breakdown-title">{{ $group['label'] }}</span>
                        <span class="ce-breakdown-value">{{ $group['score'] }}/{{ $group['max'] }}</span>
                    </div>
                    <div class="ce-progress-track mb-3">
                        <div class="ce-progress-fill tier-{{ $group['tier'] }}" style="width:{{ $group['percentage'] }}%"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <style>
        .ce-section {
            margin-bottom: 20px;
        }

        .ce-section-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: var(--luxe-surface);
            border: 1px solid var(--luxe-border);
            border-left: 5px solid var(--luxe-accent);
            border-radius: var(--luxe-radius) var(--luxe-radius) 0 0;
            padding: 14px 20px;
        }

        .ce-section-title {
            font-weight: 700;
            font-size: 14px;
            letter-spacing: .02em;
            color: var(--luxe-ink);
        }

        .ce-section-fraction {
            font-size: 13px;
            font-weight: 600;
            color: var(--luxe-muted);
        }

        .ce-section-table {
            background: var(--luxe-surface);
            border: 1px solid var(--luxe-border);
            border-top: none;
            border-radius: 0 0 var(--luxe-radius) var(--luxe-radius);
            overflow: hidden;
        }

        .ce-q-row {
            padding: 14px 20px;
            border-bottom: 1px solid var(--luxe-border);
        }

        .ce-q-row:last-child {
            border-bottom: none;
        }

        .ce-q-main {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .ce-q-text {
            font-size: 13.5px;
            color: var(--luxe-body);
            flex: 1 1 320px;
        }

        .ce-q-score {
            font-size: 12.5px;
            color: var(--luxe-muted);
            white-space: nowrap;
        }

        .ce-answer {
            font-weight: 600;
            font-size: 13px;
            white-space: nowrap;
        }

        .ce-answer.is-pass {
            color: #9fc19a;
        }

        .ce-answer.is-fail {
            color: #e2a299;
        }

        .ce-remark {
            margin-top: 10px;
            padding: 8px 12px;
            border-left: 2px solid var(--luxe-border-strong);
            border-radius: 0 var(--luxe-radius-sm) var(--luxe-radius-sm) 0;
            background: var(--luxe-surface-2);
            color: var(--luxe-muted);
            font-size: 12.5px;
            font-style: italic;
        }

        .ce-breakdown-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 12.5px;
            margin-bottom: 4px;
        }

        .ce-breakdown-title {
            color: var(--luxe-body);
            font-weight: 600;
        }

        .ce-breakdown-value {
            color: var(--luxe-muted);
            white-space: nowrap;
        }

        .ce-progress-track {
            width: 100%;
            height: 8px;
            border-radius: 999px;
            background: var(--luxe-surface-2);
            overflow: hidden;
        }

        .ce-progress-fill {
            height: 100%;
            border-radius: 999px;
            transition: width .4s ease;
        }

        .ce-progress-fill.tier-green {
            background: rgba(16, 185, 129, .8);
        }

        .ce-progress-fill.tier-amber {
            background: rgba(234, 179, 8, .8);
        }

        .ce-progress-fill.tier-red {
            background: rgba(244, 63, 94, .8);
        }

        .ce-leader-notes {
            background: var(--luxe-surface-2);
            border-left: 2px solid var(--luxe-accent);
            border-radius: 0 var(--luxe-radius-sm) var(--luxe-radius-sm) 0;
            padding: 16px 18px;
            margin-top: 24px;
        }

        .ce-leader-notes-title {
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--luxe-accent);
            margin-bottom: 8px;
        }

        .ce-leader-notes-text {
            font-size: 13.5px;
            line-height: 1.6;
            color: var(--luxe-muted);
            white-space: pre-wrap;
            margin: 0;
        }
    </style>
@endsection
