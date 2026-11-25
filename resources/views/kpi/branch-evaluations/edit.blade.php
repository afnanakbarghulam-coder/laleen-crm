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
            <strong>Please check the form</strong> &mdash; every question needs an answer, every <strong>Fail (1)</strong> question requires a remark explaining the issue, and Branch, Manager, and Date are required.
        </div>
    @endif

    <form method="POST" action="{{ route('kpi.branch-evaluation.update', $evaluation) }}" id="branchEvalForm">
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
                        @php
                            $savedAnswer = old("answers.$field", $evaluation->answers[$field] ?? null);
                            $savedRemark = old("remarks.$field", $evaluation->remarkFor($field) ?? '');
                            $isFailSelected = (string) $savedAnswer === '1';
                            $showRemarkBox = $isFailSelected || trim((string) $savedRemark) !== '' || $errors->has("remarks.$field");
                        @endphp
                        <div class="be-q-row {{ $isFailSelected ? 'is-fail-row' : '' }}" data-field="{{ $field }}">
                            <div class="be-q-main">
                                <div class="be-q-text">{{ $num }}. {{ $text }}</div>
                                <div class="be-q-choices" role="radiogroup" aria-label="{{ $text }}">
                                    <button type="button" class="be-remark-toggle {{ $showRemarkBox ? 'd-none' : '' }}" data-remark-toggle="{{ $field }}" title="Add optional remark">
                                        + Remark
                                    </button>
                                    <label class="be-q-pill fail">
                                        <input type="radio" name="answers[{{ $field }}]" value="1" data-answer-field="{{ $field }}" @checked($savedAnswer == 1) required>
                                        <span>1 &middot; Fail</span>
                                    </label>
                                    <label class="be-q-pill pass">
                                        <input type="radio" name="answers[{{ $field }}]" value="5" data-answer-field="{{ $field }}" @checked($savedAnswer == 5) required>
                                        <span>5 &middot; Pass</span>
                                    </label>
                                </div>
                            </div>
                            <div class="be-remark-wrap {{ $showRemarkBox ? '' : 'd-none' }} {{ $isFailSelected ? 'is-required' : '' }}" data-remark-wrap="{{ $field }}">
                                <div class="be-remark-head">
                                    <label class="be-remark-label" for="remark_{{ $field }}">
                                        <span data-remark-heading="{{ $field }}">{{ $isFailSelected ? 'Failure Remark' : 'Remark (Optional)' }}</span>
                                        <span class="be-remark-req-pill {{ $isFailSelected ? '' : 'd-none' }}" data-remark-req="{{ $field }}">Required on Fail</span>
                                    </label>
                                </div>
                                <input
                                    type="text"
                                    id="remark_{{ $field }}"
                                    name="remarks[{{ $field }}]"
                                    value="{{ $savedRemark }}"
                                    maxlength="1000"
                                    class="form-control form-control-sm be-remark-input @error("remarks.$field") is-invalid @enderror"
                                    placeholder="{{ $isFailSelected ? 'Explain what failed and what needs fixing…' : 'Optional observation or note…' }}"
                                    data-remark-input="{{ $field }}"
                                    @if ($isFailSelected) required @endif
                                >
                                @error("remarks.$field")
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
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
            flex-direction: column;
            gap: 10px;
            padding: 13px 20px;
            border-bottom: 1px solid var(--luxe-border);
            transition: background 0.15s ease;
        }

        .be-q-row.is-fail-row {
            background: rgba(251, 113, 133, 0.05);
        }

        .be-q-row:last-child {
            border-bottom: none;
        }

        .be-q-main {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            width: 100%;
        }

        .be-q-text {
            font-size: 13.5px;
            color: var(--luxe-body);
            flex: 1 1 320px;
        }

        .be-q-choices {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .be-remark-toggle {
            background: transparent;
            border: 1px dashed rgba(212, 163, 115, 0.35);
            color: var(--luxe-muted);
            border-radius: 999px;
            padding: 5px 11px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .be-remark-toggle:hover {
            color: var(--luxe-ink);
            border-color: var(--luxe-accent);
        }

        .be-remark-wrap {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid rgba(229, 184, 168, 0.14);
        }

        .be-remark-wrap.is-required {
            background: rgba(251, 113, 133, 0.08);
            border-color: rgba(251, 113, 133, 0.42);
        }

        .be-remark-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .be-remark-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--luxe-muted);
            margin: 0;
        }

        .be-remark-wrap.is-required .be-remark-label {
            color: #fda4af;
        }

        .be-remark-req-pill {
            display: inline-flex;
            align-items: center;
            padding: 1px 8px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 700;
            background: rgba(251, 113, 133, 0.18);
            color: #fecdd3;
            border: 1px solid rgba(251, 113, 133, 0.4);
        }

        .be-remark-input.is-missing {
            border-color: #fb7185 !important;
            box-shadow: 0 0 0 2px rgba(251, 113, 133, 0.25) !important;
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

            function syncQuestionRemarkState(field, forceExpand) {
                var row = document.querySelector('.be-q-row[data-field="' + field + '"]');
                if (!row) return;
                var checked = row.querySelector('input[type="radio"]:checked');
                var isFail = checked && checked.value === '1';
                var wrap = row.querySelector('[data-remark-wrap="' + field + '"]');
                var toggle = row.querySelector('[data-remark-toggle="' + field + '"]');
                var input = row.querySelector('[data-remark-input="' + field + '"]');
                var reqPill = row.querySelector('[data-remark-req="' + field + '"]');
                var heading = row.querySelector('[data-remark-heading="' + field + '"]');

                row.classList.toggle('is-fail-row', !!isFail);
                if (wrap) wrap.classList.toggle('is-required', !!isFail);
                if (reqPill) reqPill.classList.toggle('d-none', !isFail);
                if (heading) heading.textContent = isFail ? 'Failure Remark' : 'Remark (Optional)';

                if (input) {
                    input.required = !!isFail;
                    input.placeholder = isFail
                        ? 'Explain what failed and what needs fixing…'
                        : 'Optional observation or note…';
                    if (!isFail) {
                        input.classList.remove('is-missing');
                    }
                }

                var hasText = input && input.value.trim() !== '';
                var shouldShow = isFail || hasText || !!forceExpand;
                if (wrap) wrap.classList.toggle('d-none', !shouldShow);
                if (toggle) toggle.classList.toggle('d-none', shouldShow);
            }

            document.querySelectorAll('[data-answer-field]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    var field = this.getAttribute('data-answer-field');
                    syncQuestionRemarkState(field, false);
                    if (this.value === '1') {
                        var input = document.querySelector('[data-remark-input="' + field + '"]');
                        if (input && input.value.trim() === '') {
                            input.focus();
                        }
                    }
                });
            });

            document.querySelectorAll('[data-remark-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var field = this.getAttribute('data-remark-toggle');
                    syncQuestionRemarkState(field, true);
                    var input = document.querySelector('[data-remark-input="' + field + '"]');
                    if (input) input.focus();
                });
            });

            document.querySelectorAll('[data-remark-input]').forEach(function (input) {
                input.addEventListener('input', function () {
                    if (this.value.trim() !== '') {
                        this.classList.remove('is-missing');
                    }
                });
            });

            var form = document.getElementById('branchEvalForm');
            if (form) {
                form.addEventListener('submit', function (evt) {
                    var firstMissing = null;
                    document.querySelectorAll('.be-q-row[data-field]').forEach(function (row) {
                        var field = row.getAttribute('data-field');
                        var checked = row.querySelector('input[type="radio"]:checked');
                        var input = row.querySelector('[data-remark-input="' + field + '"]');
                        if (checked && checked.value === '1' && input && input.value.trim() === '') {
                            syncQuestionRemarkState(field, true);
                            input.classList.add('is-missing');
                            if (!firstMissing) firstMissing = input;
                        }
                    });
                    if (firstMissing) {
                        evt.preventDefault();
                        firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstMissing.focus();
                    }
                });
            }
        });
    </script>
@endsection
