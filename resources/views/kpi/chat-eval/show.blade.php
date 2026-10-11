@extends('layouts.app')
@section('title', 'Chat Evaluation Report')

@php
    $questionResults = $report->questionResults();
    $sectionBreakdown = $report->sectionBreakdown();
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
            <div class="kpi-panel p-0" style="overflow:hidden;">
                <div class="table-responsive">
                    <table class="table kpi-table mb-0">
                        <thead>
                            <tr>
                                <th style="width:56px;">#</th>
                                <th>Question</th>
                                <th style="width:140px;">Answer</th>
                                <th class="text-end" style="width:100px;">Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($questionResults as $q)
                                <tr>
                                    <td>{{ $q['num'] }}</td>
                                    <td>{{ $q['text'] }}</td>
                                    <td>
                                        <span class="ce-answer is-{{ $q['passed'] ? 'pass' : 'fail' }}">
                                            {{ $q['passed'] ? '✅ Pass' : '❌ Fail' }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ $q['score'] }} / {{ $q['weight'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="kpi-panel h-100">
                <h6>Score Breakdown</h6>
                @foreach ($sectionBreakdown as $group)
                    <div class="ce-breakdown-row">
                        <span class="ce-breakdown-title">{{ $group['label'] }}</span>
                        <span class="ce-breakdown-value">{{ $group['score'] }}/{{ $group['max'] }}</span>
                    </div>
                    <div class="kpi-progress-track mb-2">
                        <div class="kpi-progress-fill {{ $group['percentage'] >= 90 ? 'green' : ($group['percentage'] < 50 ? 'red' : 'amber') }}" style="width:{{ $group['percentage'] }}%"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <style>
        .ce-answer {
            font-weight: 600;
            font-size: 13px;
        }

        .ce-answer.is-pass {
            color: #9fc19a;
        }

        .ce-answer.is-fail {
            color: #e2a299;
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
    </style>
@endsection
