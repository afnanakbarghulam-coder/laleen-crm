@extends('layouts.app')
@section('title', 'Agents Target Reports')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Agents Target Reports</h4>
            <p>History of saved reports. Click any row to view the full breakdown.</p>
        </div>
        <a href="{{ route('kpi.agents.create') }}" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Report</a>
    </div>

    <div class="kpi-panel p-0" style="overflow:hidden;">
        <div class="table-responsive">
            <table class="table kpi-table mb-0">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th class="text-end">Morning</th>
                        <th class="text-end">Evening</th>
                        <th class="text-end">Combined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        @php $shifts = $report->shiftStats(); $combined = $report->combined(); @endphp
                        <tr>
                            <td><a href="{{ route('kpi.agents.show', $report) }}">{{ $report->date_from->format('d M Y') }} – {{ $report->date_to->format('d M Y') }}</a></td>
                            <td class="text-end"><span class="kpi-badge kpi-badge-{{ $shifts['morning']['border'] }}">{{ $shifts['morning']['pct'] }}%</span></td>
                            <td class="text-end"><span class="kpi-badge kpi-badge-{{ $shifts['evening']['border'] }}">{{ $shifts['evening']['pct'] }}%</span></td>
                            <td class="text-end fw-semibold">{{ $combined['pct'] }}%</td>
                            <td class="text-end">
                                <form action="{{ route('kpi.agents.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this report?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bx bx-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No reports saved yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $reports->links() }}</div>
@endsection
