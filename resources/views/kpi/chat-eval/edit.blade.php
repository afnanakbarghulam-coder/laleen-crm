@extends('layouts.app')
@section('title', 'Edit Chat Evaluation')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Edit Chat Evaluation</h4>
            <p>15-question weighted scorecard, grouped by category &mdash; Pass scores the question's full weight, Fail scores 0.</p>
        </div>
        <a href="{{ route('kpi.chat-eval.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Chat Evaluations</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please check the form</strong> &mdash; every question needs an answer, and Agent and Date are required.
        </div>
    @endif

    <form method="POST" action="{{ route('kpi.chat-eval.update', $report) }}">
        @csrf
        @method('PUT')

        <div class="kpi-panel mb-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Agent</label>
                    <select name="agent_id" class="form-select" required>
                        <option value="">Select agent&hellip;</option>
                        @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}" @selected((string) old('agent_id', $report->agent_id) === (string) $agent->id)>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Evaluation Date</label>
                    <input type="date" name="evaluation_date" class="form-control" value="{{ old('evaluation_date', $report->evaluation_date->format('Y-m-d')) }}" required>
                </div>
            </div>
        </div>

        @foreach (\App\Models\KpiChatEvaluation::BREAKDOWN_GROUPS as $category => $questionNumbers)
            <div class="ce-section">
                <div class="ce-section-header">
                    <div class="ce-section-title">{{ $category }}</div>
                    <div class="ce-section-fraction">Max {{ collect($questionNumbers)->sum(fn ($n) => $questions[$n]['weight']) }} points</div>
                </div>
                <div class="ce-section-table">
                    @foreach ($questionNumbers as $num)
                        @php
                            $question = $questions[$num];
                            $saved = $report->answers["q$num"] ?? [];
                            $savedPassed = ($saved['score'] ?? 0) >= $question['weight'];
                            $savedAnswer = old("answers.q$num.answer", $savedPassed ? 'pass' : 'fail');
                            $remarkValue = old("answers.q$num.remark", $saved['remark'] ?? '');
                        @endphp
                        {{-- Crucial for the edit view: a question that already has a saved
                             remark starts with the field visible, not hidden. --}}
                        <div class="ce-q-row" data-remark-open="{{ $remarkValue ? 'true' : 'false' }}">
                            <div class="ce-q-main">
                                <div class="ce-q-text">{{ $num }}. {{ $question['text'] }} <span class="ce-q-weight">({{ $question['weight'] }} pts)</span></div>
                                <div class="ce-q-choices" role="radiogroup" aria-label="{{ $question['text'] }}">
                                    <label class="ce-q-pill fail">
                                        <input type="radio" name="answers[q{{ $num }}][answer]" value="fail" @checked($savedAnswer === 'fail') required>
                                        <span>Fail &middot; 0</span>
                                    </label>
                                    <label class="ce-q-pill pass">
                                        <input type="radio" name="answers[q{{ $num }}][answer]" value="pass" @checked($savedAnswer === 'pass') required>
                                        <span>Pass &middot; {{ $question['weight'] }}</span>
                                    </label>
                                    <button type="button" class="ce-remark-toggle" data-remark-toggle>
                                        <i class="bx bx-message-rounded-dots"></i>
                                        <span data-remark-toggle-label>{{ $remarkValue ? '− Remark' : '+ Remark' }}</span>
                                    </button>
                                </div>
                            </div>
                            <input
                                type="text"
                                name="answers[q{{ $num }}][remark]"
                                class="ce-q-remark"
                                placeholder="Add a remark (optional)&hellip;"
                                value="{{ $remarkValue }}"
                                data-remark-field
                                style="{{ $remarkValue ? '' : 'display:none;' }}"
                            >
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="ce-section">
            <div class="ce-section-header">
                <div class="ce-section-title">Overall Evaluation Notes</div>
            </div>
            <div class="ce-notes-body">
                <textarea name="notes" class="form-control" rows="4" placeholder="Enter overall feedback or summary notes here&hellip;">{{ old('notes', $report->notes) }}</textarea>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn btn-primary btn-lg px-5">Update Report</button>
        </div>
    </form>

    <style>
        .ce-section {
            margin-bottom: 14px;
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
            padding: 10px 18px;
        }

        .ce-section-title {
            font-weight: 700;
            font-size: 14px;
            letter-spacing: .02em;
            color: var(--luxe-ink);
        }

        .ce-section-fraction {
            font-size: 12px;
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
            padding: 9px 18px;
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

        .ce-q-weight {
            color: var(--luxe-muted);
            font-size: 12px;
        }

        .ce-q-choices {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .ce-q-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--luxe-border-strong);
            border-radius: 999px;
            padding: 5px 12px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--luxe-muted);
            cursor: pointer;
            transition: background .15s ease, color .15s ease, border-color .15s ease;
        }

        .ce-q-pill input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
        }

        .ce-q-pill:hover {
            border-color: var(--luxe-accent);
        }

        .ce-q-pill.fail:has(input:checked) {
            background: rgba(168, 82, 74, .16);
            border-color: rgba(168, 82, 74, .5);
            color: #e2a299;
        }

        .ce-q-pill.pass:has(input:checked) {
            background: rgba(142, 168, 138, .16);
            border-color: rgba(142, 168, 138, .5);
            color: #9fc19a;
        }

        .ce-remark-toggle {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            background: none;
            border: none;
            padding: 0;
            margin-left: 4px;
            font-size: 11.5px;
            color: var(--luxe-muted);
            cursor: pointer;
            transition: color .15s ease;
            white-space: nowrap;
        }

        .ce-remark-toggle:hover {
            color: var(--luxe-accent);
        }

        .ce-remark-toggle .bx {
            font-size: 13px;
        }

        .ce-q-remark {
            width: 100%;
            margin-top: 8px;
            background: var(--luxe-surface-2);
            border: 1px solid var(--luxe-border);
            border-radius: var(--luxe-radius-sm);
            padding: 7px 12px;
            font-size: 13px;
            color: var(--luxe-body);
        }

        .ce-q-remark::placeholder {
            color: var(--luxe-muted);
        }

        .ce-q-remark:focus {
            outline: none;
            border-color: var(--luxe-accent);
            background: var(--luxe-surface-hover);
        }

        .ce-notes-body {
            padding: 14px 18px;
        }
    </style>

    <script>
        document.querySelectorAll('[data-remark-toggle]').forEach(function (btn) {
            var row = btn.closest('.ce-q-row');
            var input = row.querySelector('[data-remark-field]');
            var label = btn.querySelector('[data-remark-toggle-label]');

            function setOpen(open) {
                input.style.display = open ? '' : 'none';
                label.textContent = open ? '− Remark' : '+ Remark';
                row.dataset.remarkOpen = open ? 'true' : 'false';
            }

            btn.addEventListener('click', function () {
                setOpen(row.dataset.remarkOpen !== 'true');
            });
        });
    </script>
@endsection
