@extends('layouts.app')
@section('title', 'Ads Conversion Reports')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Ads Conversion Reports</h4>
            <p>History of saved reports. Click any row to view the full breakdown.</p>
        </div>
        @moduleEdit('kpis')
            <a href="{{ route('kpi.ads.create') }}" class="btn btn-primary"><i class="bx bx-plus me-1"></i> New Report</a>
        @endmoduleEdit
    </div>

    <div class="kpi-panel p-0" style="overflow:hidden;">
        <div class="table-responsive">
            <table class="table kpi-table mb-0">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Categories</th>
                        <th class="text-end">Total Leads</th>
                        <th class="text-end">Total Bookings</th>
                        <th class="text-end">Overall Conversion</th>
                        <th class="text-end">Total Revenue (QAR)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        @php $totals = $report->totals(); @endphp
                        <tr>
                            <td><a href="{{ route('kpi.ads.show', $report) }}">{{ $report->date_from->format('d M Y') }} – {{ $report->date_to->format('d M Y') }}</a></td>
                            <td>{{ count($report->categories) }}</td>
                            <td class="text-end">{{ $totals['total_leads'] }}</td>
                            <td class="text-end">{{ $totals['total_bookings'] }}</td>
                            <td class="text-end">
                                <span class="kpi-badge {{ $totals['overall_met_target'] ? 'kpi-badge-green' : 'kpi-badge-red' }}">{{ $totals['overall_conversion'] }}%</span>
                            </td>
                            <td class="text-end">{{ number_format($totals['total_revenue'], 2) }}</td>
                            <td class="text-end">
                                @moduleEdit('kpis')
                                    <form action="{{ route('kpi.ads.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this report?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bx bx-trash"></i></button>
                                    </form>
                                @endmoduleEdit
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No reports saved yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $reports->links() }}</div>
@endsection
