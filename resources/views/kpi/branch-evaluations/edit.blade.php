@extends('layouts.app')
@section('title', 'Edit Branch Evaluation')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Edit Branch Evaluation</h4>
            <p>Mystery shopper and manager assessment &mdash; every question scores 1 (Fail) or 5 (Pass).</p>
        </div>
        <a href="{{ route('kpi.branch-evaluation.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Branch Evaluations</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please check the form</strong> &mdash; every question needs an answer, and Branch, Manager, and Date are required.
        </div>
    @endif

    <form method="POST" action="{{ route('kpi.branch-evaluation.update', $evaluation) }}">
        @csrf
        @method('PUT')

        <div class="kpi-panel mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Branch</label>
                    <select name="branch" id="branch_select" class="form-select" required>
                        <option value="">Select branch&hellip;</option>
                        @foreach ($branches as $key => $label)
                            <option value="{{ $key }}" @selected(old('branch', $evaluation->branch) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Manager</label>
                    <input type="text" name="manager" id="manager_input" class="form-control" value="{{ old('manager', $evaluation->manager) }}" placeholder="Auto-filled from Branch" readonly required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Evaluation Date</label>
                    <input type="date" name="evaluation_date" class="form-control" value="{{ old('evaluation_date', $evaluation->evaluation_date->format('Y-m-d')) }}" required>
                </div>
            </div>
        </div>

        @php $num = 1; @endphp
        @foreach ($sections as $title => $questions)
            <div class="be-section">
                <div class="be-section-header">
                    <div class="be-section-title">{{ $title }}</div>
                    <div class="be-section-fraction">{{ count($questions) }} question{{ count($questions) > 1 ? 's' : '' }}</div>
                </div>
                <div class="be-section-table">
                    @foreach ($questions as $field => $text)
                        @php $savedAnswer = old("answers.$field", $evaluation->answers[$field] ?? null); @endphp
                        <div class="be-q-row">
                            <div class="be-q-text">{{ $num }}. {{ $text }}</div>
                            <div class="be-q-choices" role="radiogroup" aria-label="{{ $text }}">
                                <label class="be-q-pill fail">
                                    <input type="radio" name="answers[{{ $field }}]" value="1" @checked($savedAnswer == 1) required>
                                    <span>1 &middot; Fail</span>
                                </label>
                                <label class="be-q-pill pass">
                                    <input type="radio" name="answers[{{ $field }}]" value="5" @checked($savedAnswer == 5) required>
                                    <span>5 &middot; Pass</span>
                                </label>
                            </div>
                        </div>
                        @php $num++; @endphp
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="kpi-panel mb-4">
            <label class="form-label">Action Plan</label>
            <textarea name="action_plan" class="form-control" rows="4" placeholder="Coaching notes, urgent fixes, and positives to reinforce&hellip;">{{ old('action_plan', $evaluation->action_plan) }}</textarea>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary btn-lg px-5">Update Report</button>
        </div>
    </form>

    <style>
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

        .be-section-title {
            font-weight: 700;
            font-size: 13.5px;
            letter-spacing: .03em;
            color: var(--luxe-ink);
        }

        .be-section-fraction {
            font-size: 12px;
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

        .be-q-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 20px;
            border-bottom: 1px solid var(--luxe-border);
        }

        .be-q-row:last-child {
            border-bottom: none;
        }

        .be-q-text {
            font-size: 13.5px;
            color: var(--luxe-body);
            flex: 1 1 320px;
        }

        .be-q-choices {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .be-q-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--luxe-border-strong);
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--luxe-muted);
            cursor: pointer;
            transition: background .15s ease, color .15s ease, border-color .15s ease;
        }

        .be-q-pill input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
        }

        .be-q-pill:hover {
            border-color: var(--luxe-accent);
        }

        .be-q-pill.fail:has(input:checked) {
            background: rgba(168, 82, 74, .16);
            border-color: rgba(168, 82, 74, .5);
            color: #e2a299;
        }

        .be-q-pill.pass:has(input:checked) {
            background: rgba(142, 168, 138, .16);
            border-color: rgba(142, 168, 138, .5);
            color: #9fc19a;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var branchSelect = document.getElementById('branch_select');
            var managerInput = document.getElementById('manager_input');

            function syncManagerFromBranch() {
                if (branchSelect.value === 'wakrah') {
                    managerInput.value = 'Rhea';
                } else if (branchSelect.value === 'old_airport') {
                    managerInput.value = 'Ahana';
                } else {
                    managerInput.value = '';
                }
            }

            branchSelect.addEventListener('change', syncManagerFromBranch);
            syncManagerFromBranch();
        });
    </script>
@endsection
