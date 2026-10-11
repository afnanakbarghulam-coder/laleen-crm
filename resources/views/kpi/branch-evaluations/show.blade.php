@extends('layouts.app')
@section('title', 'Branch Evaluation Report')

@php
    $branchLabel = \App\Models\BranchEvaluation::BRANCHES[$evaluation->branch] ?? $evaluation->branch;
    $visit = $evaluation->visitPosition();
    $sectionResults = $evaluation->sectionResults();
    $questionResults = $evaluation->questionResults();

    $sectionsForPartial = $sectionResults->map(function ($section) use ($questionResults) {
        return [
            'title' => $section['title'],
            'score' => $section['score'],
            'max' => $section['max'],
            'questions' => $questionResults->where('section', $section['title'])->map(fn ($q) => [
                'num' => $q['num'],
                'question' => $q['question'],
                'answer' => $q['status'] === 'pass' ? '✅ Pass' : '❌ Fail',
                'score' => $q['answer'] . ' / ' . \App\Models\BranchEvaluation::POINTS_PER_QUESTION,
                'status' => $q['status'],
            ])->values(),
        ];
    });

    $criticalSections = $sectionResults->where('tier', 'red')->values();
    $perfectSections = $sectionResults->where('perfect', true)->values();

    $pctDisplay = rtrim(rtrim(number_format((float) $evaluation->percentage, 2), '0'), '.');

    $bannerTier = match ($evaluation->grade) {
        'Excellent' => 'green',
        'Pass' => 'green',
        default => 'amber',
    };

    $bannerTitle = match ($evaluation->grade) {
        'Excellent' => $evaluation->grade . ' — ' . $pctDisplay . '% · No action required',
        'Pass' => $evaluation->grade . ' — ' . $pctDisplay . '% · Minor coaching recommended',
        default => $evaluation->grade . ' — ' . $pctDisplay . '% · Formal coaching required within 48 hours',
    };

    $bannerSub = match (true) {
        $criticalSections->isEmpty() => 'No sections scored below 50% this visit.',
        $bannerTier === 'amber' => 'Driven primarily by shortfalls in: ' . $criticalSections->pluck('title')->implode(', ') . '.',
        default => 'Still worth watching despite the overall grade: ' . $criticalSections->pluck('title')->implode(', ') . '.',
    };

    $gradeColor = $evaluation->grade === 'Warning' ? 'var(--luxe-warning)' : '#b7cdb3';
@endphp

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Branch Evaluation Report — {{ $branchLabel }}</h4>
            <p>Mystery Shopper Assessment &middot; Manager: {{ $evaluation->manager }} &middot; {{ $evaluation->evaluation_date->format('d F Y') }}</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('kpi.branch-evaluation.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Branch Evaluations</a>
            @moduleEdit('kpis')
                <a href="{{ route('kpi.branch-evaluation.edit', $evaluation) }}" class="btn btn-sm btn-primary"><i class="bx bx-pencil me-1"></i>Edit Report</a>
            @endmoduleEdit
        </div>
    </div>

    <div class="be-pill-row">
        <div class="be-pill">
            <div class="be-pill-label">Branch</div>
            <div class="be-pill-value">{{ $branchLabel }}</div>
        </div>
        <div class="be-pill">
            <div class="be-pill-label">Manager</div>
            <div class="be-pill-value">{{ $evaluation->manager }}</div>
        </div>
        <div class="be-pill">
            <div class="be-pill-label">Visit</div>
            <div class="be-pill-value">Visit {{ $visit['number'] }} of {{ $visit['total'] }}</div>
        </div>
        <div class="be-pill">
            <div class="be-pill-label">Date</div>
            <div class="be-pill-value">{{ $evaluation->evaluation_date->format('d M Y') }}</div>
        </div>
    </div>

    <div class="be-warning-banner tier-{{ $bannerTier }}">
        <i class="bx bx-{{ $bannerTier === 'green' ? 'check-shield' : 'error' }}"></i>
        <div>
            <div class="be-warning-title">{{ $bannerTitle }}</div>
            <div class="be-warning-sub">{{ $bannerSub }}</div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Total Score</div>
                <div class="kpi-stat-value">{{ $evaluation->total_score }} <small class="fs-6">/ {{ \App\Models\BranchEvaluation::maxScore() }}</small></div>
                <div class="kpi-stat-sub">{{ $pctDisplay }}% overall</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Grade</div>
                <div class="kpi-stat-value" style="color: {{ $gradeColor }}; font-size: 20px;">{{ $evaluation->grade }}</div>
                <div class="kpi-stat-sub">Target: 75%+</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Critical Sections</div>
                <div class="kpi-stat-value" style="color: #d4948c; font-size: 20px;">{{ $criticalSections->count() }}</div>
                <div class="kpi-stat-sub">{{ $criticalSections->isNotEmpty() ? $criticalSections->pluck('title')->implode(', ') : 'None' }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="kpi-stat-card">
                <div class="kpi-stat-label">Perfect Sections</div>
                <div class="kpi-stat-value" style="color: #b7cdb3; font-size: 20px;">{{ $perfectSections->count() }}</div>
                <div class="kpi-stat-sub">{{ $perfectSections->isNotEmpty() ? $perfectSections->pluck('title')->implode(', ') : 'None' }}</div>
            </div>
        </div>
    </div>

    @foreach ($sectionsForPartial as $section)
        @include('kpi.branch-evaluations._section', $section)
    @endforeach

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="kpi-panel h-100">
                <h6>Section Breakdown</h6>
                @foreach ($sectionResults as $section)
                    @php
                        $sPctDisplay = rtrim(rtrim(number_format($section['percentage'], 1), '0'), '.');
                    @endphp
                    <div class="be-breakdown-row">
                        <span class="be-breakdown-title">{{ $section['title'] }}</span>
                        <span class="be-breakdown-value">{{ $section['score'] }}/{{ $section['max'] }} &middot; {{ $sPctDisplay }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="col-lg-6">
            <div class="kpi-panel h-100">
                <h6>Action Plan</h6>
                @if ($evaluation->action_plan)
                    <p class="be-action-text">{{ $evaluation->action_plan }}</p>
                @else
                    <p class="text-muted small mb-0">No action plan notes were recorded for this visit.</p>
                @endif
            </div>
        </div>
    </div>

    <style>
        .be-pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .be-pill {
            display: inline-flex;
            flex-direction: column;
            gap: 2px;
            background: var(--luxe-surface);
            border: 1px solid var(--luxe-border);
            border-radius: 999px;
            padding: 8px 20px;
        }

        .be-pill-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--luxe-muted);
        }

        .be-pill-value {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--luxe-ink);
        }

        .be-warning-banner {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            background: rgba(201, 166, 107, .12);
            border: 1px solid rgba(201, 166, 107, .4);
            border-radius: var(--luxe-radius-sm);
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .be-warning-banner.tier-green {
            background: rgba(142, 168, 138, .12);
            border-color: rgba(142, 168, 138, .4);
        }

        .be-warning-banner > .bx {
            font-size: 24px;
            line-height: 1.3;
            color: var(--luxe-warning);
            flex-shrink: 0;
        }

        .be-warning-banner.tier-green > .bx {
            color: var(--luxe-success);
        }

        .be-warning-title {
            font-weight: 700;
            font-size: 15px;
            color: var(--luxe-warning);
            margin-bottom: 4px;
        }

        .be-warning-banner.tier-green .be-warning-title {
            color: #b7cdb3;
        }

        .be-warning-sub {
            font-size: 13px;
            line-height: 1.5;
            color: var(--luxe-muted);
        }

        .be-section {
            margin-bottom: 20px;
        }

        .be-section-header {
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

        .be-section-header.tier-green {
            border-left-color: var(--luxe-success);
        }

        .be-section-header.tier-red {
            border-left-color: var(--luxe-danger);
        }

        .be-section-header.tier-amber {
            border-left-color: var(--luxe-warning);
        }

        .be-section-title {
            font-weight: 700;
            font-size: 13.5px;
            letter-spacing: .03em;
            color: var(--luxe-ink);
        }

        .be-section-fraction {
            font-size: 13px;
            font-weight: 600;
            color: var(--luxe-muted);
        }

        .be-section-table {
            background: var(--luxe-surface);
            border: 1px solid var(--luxe-border);
            border-top: none;
            border-radius: 0 0 var(--luxe-radius) var(--luxe-radius);
            overflow: hidden;
        }

        .be-answer {
            font-weight: 600;
            font-size: 13px;
        }

        .be-answer.is-pass {
            color: #9fc19a;
        }

        .be-answer.is-fail {
            color: #e2a299;
        }

        .be-answer.is-warn {
            color: var(--luxe-warning);
        }

        .be-breakdown-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--luxe-border);
            font-size: 13px;
        }

        .be-breakdown-row:last-child {
            border-bottom: none;
        }

        .be-breakdown-title {
            color: var(--luxe-body);
            font-weight: 600;
        }

        .be-breakdown-value {
            color: var(--luxe-muted);
            white-space: nowrap;
        }

        .be-action-text {
            font-size: 13px;
            line-height: 1.6;
            color: var(--luxe-muted);
            white-space: pre-wrap;
            margin: 0;
        }
    </style>
@endsection
