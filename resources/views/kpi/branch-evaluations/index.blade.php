@extends('layouts.app')
@section('title', 'Branch Evaluations')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Branch Evaluation</h4>
            <p>Mystery shopper and manager assessments, by branch.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('kpi.salon-staff.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Salon Staff</a>
            @moduleEdit('kpis')
                <a href="{{ route('kpi.branch-evaluation.create') }}" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Branch Evaluation</a>
            @endmoduleEdit
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <label class="form-label mb-0 me-1 text-muted small">Filter by Branch</label>
        <select id="branchEvalFilter" class="form-select form-select-sm" style="max-width: 200px;">
            <option value="" @selected(!$branchFilter)>All Branches</option>
            @foreach ($branches as $key => $label)
                <option value="{{ $key }}" @selected($branchFilter === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="kpi-panel p-0" style="overflow:hidden;">
        <div class="table-responsive">
            <table class="table kpi-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Manager</th>
                        <th class="text-end">Score</th>
                        <th class="text-end">Percentage</th>
                        <th>Grade</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($evaluations as $evaluation)
                        @php
                            $gradeBadge = match ($evaluation->grade) {
                                'Excellent' => 'kpi-badge-green',
                                'Pass' => 'kpi-badge-green',
                                default => 'kpi-badge-amber',
                            };
                        @endphp
                        <tr>
                            <td>{{ $evaluation->evaluation_date->format('d M Y') }}</td>
                            <td><span class="kpi-badge kpi-badge-gray">{{ $branches[$evaluation->branch] ?? $evaluation->branch }}</span></td>
                            <td>{{ $evaluation->manager }}</td>
                            <td class="text-end">{{ $evaluation->total_score }}/{{ \App\Models\BranchEvaluation::maxScore() }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format((float) $evaluation->percentage, 2), '0'), '.') }}%</td>
                            <td><span class="kpi-badge {{ $gradeBadge }}">{{ $evaluation->grade }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('kpi.branch-evaluation.show', $evaluation) }}" class="btn btn-sm btn-outline-secondary">View Report</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No branch evaluations logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $evaluations->links() }}</div>

    <script>
        document.getElementById('branchEvalFilter').addEventListener('change', function () {
            var url = new URL(window.location.href);
            if (this.value) {
                url.searchParams.set('branch', this.value);
            } else {
                url.searchParams.delete('branch');
            }
            window.location.href = url.toString();
        });
    </script>
@endsection
