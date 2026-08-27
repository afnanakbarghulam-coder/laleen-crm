@extends('layouts.app')
@section('title', 'Staff Sales Reports')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Staff Sales Performance Reports</h4>
            <p>History of saved reports. Click any row to view the full breakdown.</p>
        </div>
        @moduleEdit('kpis')
            <a href="{{ route('kpi.staff-sales.create') }}" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Report</a>
        @endmoduleEdit
    </div>

    <div class="kpi-panel p-0" style="overflow:hidden;">
        <div class="table-responsive">
            <table class="table kpi-table mb-0">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Period</th>
                        <th class="text-end">Staff</th>
                        <th class="text-end">Team Total (QAR)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr>
                            <td class="fw-semibold">{{ $branches[$report->branch] }}</td>
                            <td><a href="{{ route('kpi.staff-sales.show', $report) }}">{{ $report->date_from->format('d M Y') }} – {{ $report->date_to->format('d M Y') }}</a></td>
                            <td class="text-end">{{ $report->entries_count }}</td>
                            <td class="text-end">{{ number_format($report->entries->sum('total_upsell') ?? 0, 2) }}</td>
                            <td class="text-end">
                                @moduleEdit('kpis')
                                    <form action="{{ route('kpi.staff-sales.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this report?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bx bx-trash"></i></button>
                                    </form>
                                @endmoduleEdit
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
