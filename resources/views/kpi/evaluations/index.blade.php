@extends('layouts.app')
@section('title', 'Salon Staff Evaluation')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Salon Staff Evaluation</h4>
            <p>Monthly ROI-focused performance scoring by branch leaders.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('kpi.hub') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to KPIs</a>
            @moduleEdit('kpis')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newEvaluationModal">
                    <i class="bx bx-plus me-1"></i> New Evaluation
                </button>
            @endmoduleEdit
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="kpi-panel p-0" style="overflow:hidden;">
        <div class="table-responsive">
            <table class="table kpi-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Staff Member</th>
                        <th>Evaluated By</th>
                        <th class="text-end">Overall Score</th>
                        <th>Leader Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($evaluations as $evaluation)
                        @php
                            $avg = (float) $evaluation->overall_average;
                            $badge = $avg >= 4 ? 'kpi-badge-green' : ($avg >= 3 ? 'kpi-badge-amber' : 'kpi-badge-red');
                        @endphp
                        <tr>
                            <td>{{ $evaluation->evaluation_date->format('d M Y') }}</td>
                            <td>{{ $evaluation->staff->name ?? '—' }}</td>
                            <td>{{ $evaluation->evaluator->name ?? '—' }}</td>
                            <td class="text-end"><span class="kpi-badge {{ $badge }}">{{ number_format($avg, 2) }} / 5</span></td>
                            <td class="text-muted small">{{ $evaluation->leader_notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No evaluations saved yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $evaluations->links() }}</div>

    <div class="modal fade" id="newEvaluationModal" tabindex="-1" @if ($errors->any()) data-autoshow="1" @endif>
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('kpi.evaluations.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">New Staff Evaluation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Staff Member</label>
                                <select name="staff_id" class="form-select" required>
                                    <option value="">Select staff member&hellip;</option>
                                    @foreach ($staffList as $staff)
                                        <option value="{{ $staff->id }}" @selected(old('staff_id') == $staff->id)>{{ $staff->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Evaluation Date</label>
                                <input type="date" name="evaluation_date" class="form-control" value="{{ old('evaluation_date', now()->format('Y-m-d')) }}" required>
                            </div>
                        </div>

                        @foreach ($rubric as $field => $block)
                            <div class="se-score-block">
                                <h6 class="se-score-title">{{ $block['title'] }}</h6>

                                <div class="se-choice-row" role="radiogroup" aria-label="{{ $block['title'] }} score">
                                    <div class="se-choice se-choice-1">
                                        <input type="radio" name="{{ $field }}" id="{{ $field }}-1" value="1" @checked(old($field) == 1) required>
                                        <label for="{{ $field }}-1">1</label>
                                        <p class="se-choice-text">{{ $block['one_star'] }}</p>
                                    </div>
                                    <div class="se-choice se-choice-5">
                                        <input type="radio" name="{{ $field }}" id="{{ $field }}-5" value="5" @checked(old($field) == 5) required>
                                        <label for="{{ $field }}-5">5</label>
                                        <p class="se-choice-text">{{ $block['five_star'] }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="mt-4">
                            <label class="form-label">Leader Notes</label>
                            <textarea name="leader_notes" class="form-control" rows="3" placeholder="Coaching notes, context, or action items&hellip;">{{ old('leader_notes') }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Evaluation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* The modal's <form> sits between .modal-content and .modal-header/
           body/footer, which breaks Bootstrap's flex-column scroll chain
           (.modal-dialog-scrollable expects those as direct children). Make
           the form itself a flex column so .modal-body still shrinks and
           scrolls internally instead of overflowing the modal. */
        #newEvaluationModal .modal-content > form {
            display: flex;
            flex-direction: column;
            min-height: 0;
            overflow: hidden;
        }

        .se-score-block {
            background: var(--luxe-surface);
            border: 1px solid var(--luxe-border);
            border-radius: var(--luxe-radius-sm);
            padding: 16px 18px;
            margin-bottom: 16px;
        }

        .se-score-title {
            color: var(--luxe-ink);
            font-weight: 700;
            font-size: 14.5px;
            margin-bottom: 14px;
        }

        .se-choice-row {
            display: flex;
            justify-content: space-between;
            gap: 2rem;
        }

        .se-choice {
            display: flex;
            flex-direction: column;
            flex: 1 1 0;
        }

        .se-choice-5 {
            align-items: flex-end;
            text-align: right;
        }

        .se-choice input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
        }

        .se-choice label {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid var(--luxe-border-strong);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            color: var(--luxe-muted);
            cursor: pointer;
            margin-bottom: 10px;
            transition: background .15s ease, color .15s ease, border-color .15s ease;
        }

        .se-choice label:hover {
            border-color: var(--luxe-accent);
        }

        .se-choice input[type="radio"]:checked + label {
            background: var(--luxe-accent);
            border-color: var(--luxe-accent);
            color: var(--luxe-accent-ink);
        }

        .se-choice input[type="radio"]:focus-visible + label {
            outline: 2px solid var(--luxe-accent);
            outline-offset: 2px;
        }

        .se-choice-text {
            margin: 0;
            font-size: 13px;
            line-height: 1.45;
            color: var(--luxe-muted);
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEl = document.getElementById('newEvaluationModal');
            if (modalEl.dataset.autoshow === '1') {
                new bootstrap.Modal(modalEl).show();
            }
        });
    </script>
@endsection
