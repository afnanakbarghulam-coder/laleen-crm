@extends('layouts.app')
@section('title', 'New Chat Evaluation')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>New Chat Evaluation</h4>
            <p>15-question weighted scorecard &mdash; Pass scores the question's full weight, Fail scores 0.</p>
        </div>
        <a href="{{ route('kpi.chat-eval.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Chat Evaluations</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please check the form</strong> &mdash; every question needs an answer, and Agent and Date are required.
        </div>
    @endif

    <form method="POST" action="{{ route('kpi.chat-eval.store') }}">
        @csrf

        <div class="kpi-panel mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Agent</label>
                    <select name="agent_id" class="form-select" required>
                        <option value="">Select agent&hellip;</option>
                        @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}" @selected((string) old('agent_id') === (string) $agent->id)>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Evaluation Date</label>
                    <input type="date" name="evaluation_date" class="form-control" value="{{ old('evaluation_date', now()->format('Y-m-d')) }}" required>
                </div>
            </div>
        </div>

        <div class="ce-section">
            <div class="ce-section-header">
                <div class="ce-section-title">15-Question Scorecard</div>
                <div class="ce-section-fraction">Max {{ \App\Models\KpiChatEvaluation::maxScore() }} points</div>
            </div>
            <div class="ce-section-table">
                @foreach ($questions as $num => $question)
                    <div class="ce-q-row">
                        <div class="ce-q-text">{{ $num }}. {{ $question['text'] }} <span class="ce-q-weight">({{ $question['weight'] }} pts)</span></div>
                        <div class="ce-q-choices" role="radiogroup" aria-label="{{ $question['text'] }}">
                            <label class="ce-q-pill fail">
                                <input type="radio" name="answers[{{ $num }}]" value="fail" @checked(old("answers.$num") === 'fail') required>
                                <span>Fail &middot; 0</span>
                            </label>
                            <label class="ce-q-pill pass">
                                <input type="radio" name="answers[{{ $num }}]" value="pass" @checked(old("answers.$num") === 'pass') required>
                                <span>Pass &middot; {{ $question['weight'] }}</span>
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn btn-primary btn-lg px-5">Generate Report</button>
        </div>
    </form>

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
            font-size: 13.5px;
            letter-spacing: .03em;
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
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 20px;
            border-bottom: 1px solid var(--luxe-border);
        }

        .ce-q-row:last-child {
            border-bottom: none;
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
            gap: 8px;
            flex-shrink: 0;
        }

        .ce-q-pill {
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
    </style>
@endsection
