@extends('layouts.app')
@section('title', 'Chat Evaluation Reports')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Chat Evaluation Reports</h4>
            <p>15-question weighted scorecard, out of {{ \App\Models\KpiChatEvaluation::maxScore() }} points.</p>
        </div>
        @moduleEdit('kpis')
            <a href="{{ route('kpi.chat-eval.create') }}" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Evaluation</a>
        @endmoduleEdit
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
                        <th>Agent</th>
                        <th>Evaluator</th>
                        <th class="text-end">Score</th>
                        <th>Grade</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        @php
                            $grade = $report->grade();
                            $badge = match($grade) { 'Excellent' => 'kpi-badge-green', 'Pass' => 'kpi-badge-green', 'Warning' => 'kpi-badge-amber', default => 'kpi-badge-red' };
                        @endphp
                        <tr>
                            <td><a href="{{ route('kpi.chat-eval.show', $report) }}">{{ $report->evaluation_date->format('d M Y') }}</a></td>
                            <td>{{ $report->agent->name ?? '—' }}</td>
                            <td>{{ $report->evaluator->name ?? '—' }}</td>
                            <td class="text-end">{{ $report->total_score }}/{{ \App\Models\KpiChatEvaluation::maxScore() }} ({{ rtrim(rtrim(number_format((float) $report->percentage, 2), '0'), '.') }}%)</td>
                            <td><span class="kpi-badge {{ $badge }}">{{ $grade }}</span></td>
                            <td class="text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="{{ route('kpi.chat-eval.show', $report) }}" class="btn btn-sm btn-outline-secondary">View Report</a>
                                    @moduleEdit('kpis')
                                        <a href="{{ route('kpi.chat-eval.edit', $report) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bx bx-pencil"></i></a>
                                        <form action="{{ route('kpi.chat-eval.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this evaluation?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bx bx-trash"></i></button>
                                        </form>
                                    @endmoduleEdit
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No evaluations saved yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $reports->links() }}</div>
@endsection
