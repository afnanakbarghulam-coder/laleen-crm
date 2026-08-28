@extends('layouts.app')
@section('title', 'Content KPI & Calendar')

@section('content')
    <div class="d-flex gap-2 mb-4">
        <button type="button" class="content-tab-btn btn btn-sm {{ $activeTab === 'calendar' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="calendar">Content Calendar</button>
        <button type="button" class="content-tab-btn btn btn-sm {{ $activeTab === 'reports' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="reports">Content Reports &amp; Analytics</button>
    </div>

    {{-- ================= CONTENT CALENDAR ================= --}}
    <div id="content-pane-calendar" class="content-tab-pane {{ $activeTab === 'calendar' ? '' : 'd-none' }}">
        <div class="kpi-header">
            <div>
                <h4>Content Calendar</h4>
                <p>Daily scheduled posts, shoot schedules, and story flows — updated directly here. Feeds the Reports &amp; Analytics tab automatically.</p>
            </div>
            @moduleEdit('kpis')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contentEntryModal" onclick="resetContentEntryForm()">
                    <i class="bx bx-plus me-1"></i> Add Entry
                </button>
            @endmoduleEdit
        </div>

        <div class="kpi-panel mb-3">
            <form method="GET" action="{{ route('kpi.content.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="calendar">
                <div class="col-md-3">
                    <label class="form-label">Creator</label>
                    <select name="calendar_creator" class="form-select">
                        <option value="">All creators</option>
                        @foreach ($creators as $c)
                            <option value="{{ $c }}" {{ $calendarCreator === $c ? 'selected' : '' }}>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="calendar_from" class="form-control" value="{{ $calendarFrom?->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="calendar_to" class="form-control" value="{{ $calendarTo?->format('Y-m-d') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if ($calendarFrom || $calendarTo || $calendarCreator)
                        <a href="{{ route('kpi.content.index', ['tab' => 'calendar']) }}" class="btn btn-outline-secondary">Reset</a>
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
                            <th>Day</th>
                            <th>Week</th>
                            <th>Creator</th>
                            <th>Activity Type</th>
                            <th>Feed Post / Shoot Schedule</th>
                            <th>Story Theme</th>
                            <th>Story Flow</th>
                            <th>Posted</th>
                            <th>Std. Feed</th>
                            <th>Std. Stories</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td>{{ $entry->entry_date->format('d M Y') }}</td>
                                <td>{{ $entry->dayName() }}</td>
                                <td>W{{ $entry->weekNumber() }}</td>
                                <td class="fw-semibold">{{ $entry->creator_name }}</td>
                                <td>{{ $entry->activity_type ?: '—' }}</td>
                                <td class="small">{{ $entry->feed_post_schedule ?: '—' }}</td>
                                <td class="small">{{ $entry->story_theme ?: '—' }}</td>
                                <td class="small">{{ $entry->story_flow ?: '—' }}</td>
                                <td><span class="kpi-yn-badge {{ $entry->feed_posted === 'Y' ? 'yes' : 'no' }}">{{ $entry->feed_posted }}</span></td>
                                <td><span class="kpi-yn-badge {{ $entry->standards_feed === 'Y' ? 'yes' : ($entry->standards_feed === 'N' ? 'no' : 'na') }}">{{ $entry->standards_feed }}</span></td>
                                <td><span class="kpi-yn-badge {{ $entry->standards_stories === 'Y' ? 'yes' : ($entry->standards_stories === 'N' ? 'no' : 'na') }}">{{ $entry->standards_stories }}</span></td>
                                <td class="text-end">
                                    @moduleEdit('kpis')
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-outline-warning content-entry-edit-btn"
                                                data-entry='@json($entry->toEditPayload())'
                                                title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form action="{{ route('kpi.content-entries.destroy', $entry) }}" method="POST" onsubmit="return confirm('Delete this calendar entry?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    @endmoduleEdit
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="text-center text-muted py-4">No content calendar entries logged{{ ($calendarFrom || $calendarTo || $calendarCreator) ? ' for this filter' : ' yet' }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-4">{{ $entries->links() }}</div>

        @moduleEdit('kpis')
            @include('kpi.content._content-entry-modal')
        @endmoduleEdit
    </div>

    {{-- ================= CONTENT REPORTS & ANALYTICS ================= --}}
    <div id="content-pane-reports" class="content-tab-pane {{ $activeTab === 'reports' ? '' : 'd-none' }}">
        <div class="kpi-header">
            <div>
                <h4>Content Reports &amp; Analytics</h4>
                <p>Pick a creator and date range to generate a saved snapshot — execution score and grade are calculated automatically from the Content Calendar.</p>
            </div>
        </div>

        @moduleEdit('kpis')
            <div class="kpi-panel mb-3">
                <form method="POST" action="{{ route('kpi.content.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label">Creator</label>
                        <select name="creator_name" class="form-select" required>
                            <option value="">Select creator</option>
                            @foreach ($creators as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
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
                            <th>Creator</th>
                            <th>Period</th>
                            <th class="text-end">Overall Score</th>
                            <th>Grade</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            @php $metrics = $report->metrics(); $badge = match($metrics['grade']) { 'Excellent' => 'kpi-badge-green', 'Pass' => 'kpi-badge-green', 'Warning' => 'kpi-badge-amber', default => 'kpi-badge-red' }; @endphp
                            <tr>
                                <td class="fw-semibold">{{ $report->creator_name }}</td>
                                <td><a href="{{ route('kpi.content.show', $report) }}">{{ $report->date_from->format('d M Y') }} – {{ $report->date_to->format('d M Y') }}</a></td>
                                <td class="text-end">{{ $metrics['overall'] }}%</td>
                                <td><span class="kpi-badge {{ $badge }}">{{ $metrics['grade'] }}</span></td>
                                <td class="text-end">
                                    @moduleEdit('kpis')
                                        <form action="{{ route('kpi.content.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this report?')">
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
        document.querySelectorAll('.content-tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.content-tab-btn').forEach(b => {
                    b.classList.remove('btn-dark');
                    b.classList.add('btn-outline-secondary');
                });
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-dark');

                document.querySelectorAll('.content-tab-pane').forEach(p => p.classList.add('d-none'));
                document.getElementById('content-pane-' + this.dataset.tab).classList.remove('d-none');
            });
        });
    </script>
@endsection
