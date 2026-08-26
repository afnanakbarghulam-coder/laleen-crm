@extends('layouts.app')
@section('title', 'New Chat Evaluation')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>New Chat Evaluation</h4>
            <p>Answer each question and award a score — totals, grade, and pass/fail counts are calculated automatically.</p>
        </div>
        <a href="{{ route('kpi.chat-eval.index') }}" class="btn btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i> Back to history</a>
    </div>

    <form method="POST" action="{{ route('kpi.chat-eval.store') }}">
        @csrf

        <div class="kpi-panel">
            <h6>Evaluation Details</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Date</label>
                    <input type="date" name="eval_date" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Coordinator</label>
                    <input type="text" name="coordinator_name" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Chats Reviewed</label>
                    <input type="number" name="chats_reviewed" class="form-control" min="0" required>
                </div>
            </div>
        </div>

        <div class="kpi-panel">
            <h6>Question Bank <span class="text-muted small fw-normal">— {{ array_sum(array_column($questions, 'max')) }} points total</span></h6>
            <div class="table-responsive">
                <table class="table kpi-table align-middle">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Question</th>
                            <th style="width:110px;">Max</th>
                            <th style="width:130px;">Answer</th>
                            <th style="width:110px;">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($questions as $num => $q)
                            <tr>
                                <td>{{ $num }}</td>
                                <td>{{ $q['text'] }}</td>
                                <td>{{ $q['max'] }}</td>
                                <td>
                                    <select name="answers[{{ $num }}][answer]" class="form-select form-select-sm answer-select" data-max="{{ $q['max'] }}" data-target="score-{{ $num }}" required>
                                        <option value="Yes">Yes</option>
                                        <option value="No">No</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.1" min="0" max="{{ $q['max'] }}" id="score-{{ $num }}" name="answers[{{ $num }}][score]" class="form-control form-control-sm" value="{{ $q['max'] }}" required>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('kpi.chat-eval.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Generate Evaluation</button>
        </div>
    </form>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.answer-select').forEach(select => {
        select.addEventListener('change', function () {
            const scoreInput = document.getElementById(this.dataset.target);
            scoreInput.value = this.value === 'Yes' ? this.dataset.max : 0;
        });
    });
</script>
@endsection
