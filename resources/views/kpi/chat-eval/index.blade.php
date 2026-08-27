@extends('layouts.app')
@section('title', 'Chat Evaluation Reports')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Chat Evaluation Reports</h4>
            <p>History of saved evaluations. Click any row to view the full scorecard.</p>
        </div>
        @moduleEdit('kpis')
            <a href="{{ route('kpi.chat-eval.create') }}" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Evaluation</a>
        @endmoduleEdit
    </div>

    <div class="kpi-panel p-0" style="overflow:hidden;">
        <div class="table-responsive">
            <table class="table kpi-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Coordinator</th>
                        <th class="text-end">Chats Reviewed</th>
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
                            <td><a href="{{ route('kpi.chat-eval.show', $report) }}">{{ $report->eval_date->format('d M Y') }}</a></td>
                            <td>{{ $report->coordinator_name }}</td>
                            <td class="text-end">{{ $report->chats_reviewed }}</td>
                            <td class="text-end">{{ $report->totalScore() }} ({{ $report->percentage() }}%)</td>
                            <td><span class="kpi-badge {{ $badge }}">{{ $grade }}</span></td>
                            <td class="text-end">
                                @moduleEdit('kpis')
                                    <form action="{{ route('kpi.chat-eval.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this evaluation?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bx bx-trash"></i></button>
                                    </form>
                                @endmoduleEdit
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
