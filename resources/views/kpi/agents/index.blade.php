@extends('layouts.app')
@section('title', 'Agents Target Reports')

@section('content')
    <div class="d-flex gap-2 mb-4">
        <button type="button" class="agents-tab-btn btn btn-sm {{ $activeTab === 'shifts' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="shifts">Agent Shift Log</button>
        <button type="button" class="agents-tab-btn btn btn-sm {{ $activeTab === 'reports' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="reports">Agent Target Reports</button>
    </div>

    {{-- ================= AGENT SHIFT LOG ================= --}}
    <div id="agents-pane-shifts" class="agents-tab-pane {{ $activeTab === 'shifts' ? '' : 'd-none' }}">
        <div class="kpi-header">
            <div>
                <h4>Agent Shift Log</h4>
                <p>Log each agent's exact check-in and check-out time for their Morning or Evening shift — only bookings that agent's own account creates strictly inside that window count toward their target.</p>
            </div>
            @moduleEdit('kpis')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#shiftLogModal" onclick="resetShiftLogForm()">
                    <i class="bx bx-plus me-1"></i> Log Sign-In
                </button>
            @endmoduleEdit
        </div>

        <div class="kpi-panel mb-3">
            <form method="GET" action="{{ route('kpi.agents.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="shifts">
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="logs_from" class="form-control" value="{{ $logsFrom?->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="logs_to" class="form-control" value="{{ $logsTo?->format('Y-m-d') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if ($logsFrom || $logsTo)
                        <a href="{{ route('kpi.agents.index', ['tab' => 'shifts']) }}" class="btn btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="kpi-panel p-0 mb-4" style="overflow:hidden;">
            <div class="table-responsive">
                <table class="table kpi-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Agent</th>
                            <th>Shift</th>
                            <th>Check-In</th>
                            <th>Check-Out</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shiftLogs as $log)
                            <tr>
                                <td>{{ $log->date->format('d M Y') }}</td>
                                <td>{{ $log->agent->name ?? '—' }}</td>
                                <td>
                                    <span class="kpi-badge {{ $log->shift === 'morning' ? 'kpi-badge-green' : 'kpi-badge-amber' }}">
                                        {{ \App\Models\AgentShiftLog::SHIFTS[$log->shift] }}
                                    </span>
                                </td>
                                <td>{{ $log->check_in_time ? \Illuminate\Support\Carbon::parse($log->check_in_time)->format('g:i A') : '—' }}</td>
                                <td>
                                    @if ($log->check_out_time)
                                        {{ \Illuminate\Support\Carbon::parse($log->check_out_time)->format('g:i A') }}
                                    @else
                                        <span class="kpi-badge kpi-badge-amber">In progress</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @moduleEdit('kpis')
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-outline-warning shift-log-edit-btn"
                                                data-log='@json($log->toEditPayload())'
                                                title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form action="{{ route('kpi.agent-shift-logs.destroy', $log) }}" method="POST" onsubmit="return confirm('Delete this shift log entry?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    @endmoduleEdit
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No shift sign-ins logged{{ ($logsFrom || $logsTo) ? ' for this period' : ' yet' }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-4">{{ $shiftLogs->links() }}</div>

        @moduleEdit('kpis')
            @include('kpi.agents._shift-log-modal')
        @endmoduleEdit
    </div>

    {{-- ================= AGENT TARGET REPORTS ================= --}}
    <div id="agents-pane-reports" class="agents-tab-pane {{ $activeTab === 'reports' ? '' : 'd-none' }}">
        <div class="kpi-header">
            <div>
                <h4>Agent Target Reports</h4>
                <p>Pick a date range and generate a saved snapshot — Morning/Evening bookings, targets, and achievement % are calculated automatically from the Agent Shift Log and bookings.</p>
            </div>
        </div>

        @moduleEdit('kpis')
            <div class="kpi-panel mb-3">
                <form method="POST" action="{{ route('kpi.agents.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" name="date_from" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" name="date_to" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="bx bx-plus me-1"></i> Generate Report</button>
                    </div>
                </form>
            </div>
        @endmoduleEdit

        <div class="kpi-panel p-0" style="overflow:hidden;">
            <div class="table-responsive">
                <table class="table kpi-table mb-0">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th class="text-end">Morning %</th>
                            <th class="text-end">Evening %</th>
                            <th class="text-end">Combined %</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            @php $shifts = $report->shiftStats(); $combined = $report->combined(); @endphp
                            <tr>
                                <td><a href="{{ route('kpi.agents.show', $report) }}">{{ $report->date_from->format('d M Y') }} – {{ $report->date_to->format('d M Y') }}</a></td>
                                <td class="text-end">
                                    <span class="kpi-badge kpi-badge-{{ $shifts['morning']['border'] }}">{{ $shifts['morning']['pct'] }}%</span>
                                </td>
                                <td class="text-end">
                                    <span class="kpi-badge kpi-badge-{{ $shifts['evening']['border'] }}">{{ $shifts['evening']['pct'] }}%</span>
                                </td>
                                <td class="text-end">
                                    <span class="kpi-badge {{ $combined['pct'] >= 85 ? 'kpi-badge-green' : ($combined['pct'] >= 70 ? 'kpi-badge-amber' : 'kpi-badge-red') }}">{{ $combined['pct'] }}%</span>
                                </td>
                                <td class="text-end">
                                    @moduleEdit('kpis')
                                        <form action="{{ route('kpi.agents.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this report?')">
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
    </div>

    <script>
        document.querySelectorAll('.agents-tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.agents-tab-btn').forEach(b => {
                    b.classList.remove('btn-dark');
                    b.classList.add('btn-outline-secondary');
                });
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-dark');

                document.querySelectorAll('.agents-tab-pane').forEach(p => p.classList.add('d-none'));
                document.getElementById('agents-pane-' + this.dataset.tab).classList.remove('d-none');
            });
        });
    </script>
@endsection
