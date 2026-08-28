@extends('layouts.app')
@section('title', 'Content KPI & Calendar')

@section('content')
    <style>
        .content-quick-add-row td {
            background: rgba(217, 143, 131, 0.06);
            vertical-align: middle;
            padding-top: 6px;
            padding-bottom: 6px;
        }
        .content-quick-add-row input, .content-quick-add-row select {
            min-width: 90px;
        }
        #contentCalendarTable td input.form-control, #contentCalendarTable td select.form-select {
            font-size: 12.5px;
        }
        .content-toast-container {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 2000;
        }
        .content-toast {
            background: #241e1c;
            border: 1px solid rgba(217, 143, 131, 0.24);
            color: #e9dfda;
            border-radius: 8px;
            padding: 10px 16px;
            margin-bottom: 8px;
            font-size: 13px;
            box-shadow: 0 4px 14px rgba(0,0,0,.3);
        }
        .content-toast.error {
            border-color: rgba(168, 82, 74, 0.5);
            color: #e79a91;
        }
    </style>

    <div class="d-flex gap-2 mb-4">
        <button type="button" class="content-tab-btn btn btn-sm {{ $activeTab === 'calendar' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="calendar">Content Calendar</button>
        <button type="button" class="content-tab-btn btn btn-sm {{ $activeTab === 'reports' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="reports">Content Reports &amp; Analytics</button>
    </div>

    {{-- ================= CONTENT CALENDAR ================= --}}
    <div id="content-pane-calendar" class="content-tab-pane {{ $activeTab === 'calendar' ? '' : 'd-none' }}">
        <div class="kpi-header">
            <div>
                <h4>Content Calendar</h4>
                <p>Daily scheduled posts and shoot schedules — type straight into the grid below, it saves immediately. Feeds the Reports &amp; Analytics tab automatically.</p>
            </div>
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
                <table class="table kpi-table mb-0" id="contentCalendarTable">
                    <thead>
                        <tr>
                            <th style="min-width:130px;">Date</th>
                            <th>Day</th>
                            <th style="min-width:130px;">Creator</th>
                            <th style="min-width:170px;">Activity Type</th>
                            <th style="min-width:160px;">Shoot Schedule</th>
                            <th style="min-width:110px;">Stories Posted</th>
                            <th style="min-width:110px;">Feed Posted</th>
                            <th style="min-width:110px;">Std. Stories</th>
                            <th style="min-width:110px;">Std. Feed</th>
                            <th style="min-width:90px;">Event</th>
                            <th style="min-width:90px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @moduleEdit('kpis')
                            <tr class="content-quick-add-row" id="contentQuickAddRow">
                                <td><input type="date" class="form-control form-control-sm" id="qaDate" value="{{ now()->format('Y-m-d') }}" onchange="this.closest('tr').querySelector('[data-day-display]').textContent = computeDayName(this.value)"></td>
                                <td class="text-muted small"><span data-day-display>{{ now()->format('l') }}</span></td>
                                <td><input type="text" class="form-control form-control-sm" id="qaCreator" list="contentCreatorList" placeholder="Creator"></td>
                                <td>
                                    <select class="form-select form-select-sm" id="qaActivityType" onchange="updateActivityTypeVisibility(this)">
                                        <option value="">Select type</option>
                                        @foreach (\App\Models\KpiContentEntry::ACTIVITY_TYPES as $t)
                                            <option value="{{ $t }}">{{ $t }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td data-feed-schedule-cell>
                                    <input type="text" class="form-control form-control-sm d-none" id="qaFeedSchedule" placeholder="Shoot schedule">
                                    <span class="text-muted small feed-schedule-na">N/A</span>
                                </td>
                                <td data-tracking-field="stories_posted">
                                    <select class="form-select form-select-sm" id="qaStoriesPosted">
                                        <option value="N">No</option>
                                        <option value="Y">Yes</option>
                                    </select>
                                    <span class="text-muted small d-none tracking-na">N/A</span>
                                </td>
                                <td data-tracking-field="feed_posted">
                                    <select class="form-select form-select-sm" id="qaFeedPosted">
                                        <option value="N">No</option>
                                        <option value="Y">Yes</option>
                                    </select>
                                    <span class="text-muted small d-none tracking-na">N/A</span>
                                </td>
                                <td data-tracking-field="standards_stories">
                                    <select class="form-select form-select-sm" id="qaStandardsStories">
                                        <option value="N">No</option>
                                        <option value="Y">Yes</option>
                                    </select>
                                    <span class="text-muted small d-none tracking-na">N/A</span>
                                </td>
                                <td data-tracking-field="standards_feed">
                                    <select class="form-select form-select-sm" id="qaStandardsFeed">
                                        <option value="N">No</option>
                                        <option value="Y">Yes</option>
                                    </select>
                                    <span class="text-muted small d-none tracking-na">N/A</span>
                                </td>
                                <td data-tracking-field="event">
                                    <select class="form-select form-select-sm" id="qaEvent">
                                        <option value="N">No</option>
                                        <option value="Y">Yes</option>
                                    </select>
                                    <span class="text-muted small d-none tracking-na">N/A</span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-primary" id="qaSaveBtn" title="Add entry"><i class="bx bx-plus"></i></button>
                                </td>
                            </tr>
                            <datalist id="contentCreatorList">
                                @foreach ($creators as $c)
                                    <option value="{{ $c }}">
                                @endforeach
                            </datalist>
                        @endmoduleEdit

                        @forelse ($entries as $entry)
                            @php $visible = $entry->visibleFields(); @endphp
                            <tr data-entry-row="{{ $entry->id }}" data-entry='@json($entry->toEditPayload())'>
                                <td>{{ $entry->entry_date->format('d M Y') }}</td>
                                <td>{{ $entry->dayName() }}</td>
                                <td class="fw-semibold">{{ $entry->creator_name }}</td>
                                <td>{{ $entry->activity_type ?: '—' }}</td>
                                <td class="small">{{ $entry->feed_post_schedule ?: '—' }}</td>
                                <td>
                                    @if (in_array('stories_posted', $visible))
                                        <span class="kpi-yn-badge {{ $entry->stories_posted === 'Y' ? 'yes' : 'no' }}">{{ $entry->stories_posted }}</span>
                                    @else
                                        <span class="kpi-yn-badge na">NA</span>
                                    @endif
                                </td>
                                <td>
                                    @if (in_array('feed_posted', $visible))
                                        <span class="kpi-yn-badge {{ $entry->feed_posted === 'Y' ? 'yes' : 'no' }}">{{ $entry->feed_posted }}</span>
                                    @else
                                        <span class="kpi-yn-badge na">NA</span>
                                    @endif
                                </td>
                                <td>
                                    @if (in_array('standards_stories', $visible))
                                        <span class="kpi-yn-badge {{ $entry->standards_stories === 'Y' ? 'yes' : 'no' }}">{{ $entry->standards_stories }}</span>
                                    @else
                                        <span class="kpi-yn-badge na">NA</span>
                                    @endif
                                </td>
                                <td>
                                    @if (in_array('standards_feed', $visible))
                                        <span class="kpi-yn-badge {{ $entry->standards_feed === 'Y' ? 'yes' : 'no' }}">{{ $entry->standards_feed }}</span>
                                    @else
                                        <span class="kpi-yn-badge na">NA</span>
                                    @endif
                                </td>
                                <td>
                                    @if (in_array('event', $visible))
                                        <span class="kpi-yn-badge {{ $entry->event === 'Y' ? 'yes' : 'no' }}">{{ $entry->event }}</span>
                                    @else
                                        <span class="kpi-yn-badge na">NA</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @moduleEdit('kpis')
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="startEditContentRow({{ $entry->id }})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteContentRow({{ $entry->id }})" title="Delete"><i class="bi bi-trash"></i></button>
                                        </div>
                                    @endmoduleEdit
                                </td>
                            </tr>
                        @empty
                            <tr id="contentEmptyRow"><td colspan="11" class="text-center text-muted py-4">No content calendar entries logged{{ ($calendarFrom || $calendarTo || $calendarCreator) ? ' for this filter' : ' yet' }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-4">{{ $entries->links() }}</div>
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

        /* ---------------- CONTENT CALENDAR: INLINE SPREADSHEET GRID ---------------- */
        const CAN_EDIT_KPIS = @json(auth()->check() && auth()->user()->canEdit('kpis'));
        const CSRF_TOKEN = '{{ csrf_token() }}';
        const CONTENT_ACTIVITY_TYPES = @json(\App\Models\KpiContentEntry::ACTIVITY_TYPES);
        const CONTENT_FIELD_VISIBILITY = @json(\App\Models\KpiContentEntry::FIELD_VISIBILITY);
        const TRACKING_FIELDS = ['stories_posted', 'feed_posted', 'standards_stories', 'standards_feed', 'event'];

        // Local-time parse (avoids the UTC-midnight day-shift bug) so this always
        // agrees with the server's Carbon::parse($date)->format('l').
        function computeDayName(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr + 'T00:00:00');
            if (isNaN(d.getTime())) return '—';
            return d.toLocaleDateString('en-US', { weekday: 'long' });
        }

        // Shoot Schedule only applies to activity types that include an Event,
        // and the five Y/N tracking fields only apply to certain activity types
        // (KpiContentEntry::FIELD_VISIBILITY) — hide the input (not the column,
        // so the grid stays aligned) for whichever don't apply
        // to the row's selected activity type, and clear any value they held.
        window.updateActivityTypeVisibility = function (activityTypeEl) {
            const row = activityTypeEl.closest('tr');
            const visible = CONTENT_FIELD_VISIBILITY[activityTypeEl.value] || [];

            const feedCell = row.querySelector('[data-feed-schedule-cell]');
            if (feedCell) {
                const input = feedCell.querySelector('input');
                const naSpan = feedCell.querySelector('.feed-schedule-na');
                const hide = !visible.includes('event');
                if (input) {
                    input.classList.toggle('d-none', hide);
                    if (hide) input.value = '';
                }
                if (naSpan) naSpan.classList.toggle('d-none', !hide);
            }

            TRACKING_FIELDS.forEach(field => {
                const cell = row.querySelector(`[data-tracking-field="${field}"]`);
                if (!cell) return;
                const select = cell.querySelector('select');
                const naSpan = cell.querySelector('.tracking-na');
                const hide = !visible.includes(field);
                if (select) {
                    select.classList.toggle('d-none', hide);
                    if (hide) select.value = 'N';
                }
                if (naSpan) naSpan.classList.toggle('d-none', !hide);
            });
        };

        function showContentToast(message, type = 'success') {
            let container = document.querySelector('.content-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'content-toast-container';
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            toast.className = 'content-toast' + (type === 'error' ? ' error' : '');
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3500);
        }

        function escapeHtml(value) {
            if (value === null || value === undefined) return '';
            const div = document.createElement('div');
            div.textContent = value;
            return div.innerHTML;
        }

        function ynBadgeHtml(value) {
            const cls = value === 'Y' ? 'yes' : (value === 'N' ? 'no' : 'na');
            return `<span class="kpi-yn-badge ${cls}">${value}</span>`;
        }

        function contentActionsHtml(id, mode) {
            if (!CAN_EDIT_KPIS) return '';
            if (mode === 'edit') {
                return `<div class="d-flex gap-1 justify-content-end">
                    <button type="button" class="btn btn-sm btn-primary" onclick="saveEditContentRow(${id})" title="Save"><i class="bi bi-check-lg"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cancelEditContentRow(${id})" title="Cancel"><i class="bi bi-x-lg"></i></button>
                </div>`;
            }
            return `<div class="d-flex gap-1 justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-warning" onclick="startEditContentRow(${id})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteContentRow(${id})" title="Delete"><i class="bi bi-trash"></i></button>
            </div>`;
        }

        function trackingFieldCellHtml(field, value, visible) {
            const hide = !visible.includes(field);
            return `<td data-tracking-field="${field}">
                <select class="form-select form-select-sm ${hide ? 'd-none' : ''}" data-field="${field}">
                    <option value="N" ${value === 'N' ? 'selected' : ''}>No</option>
                    <option value="Y" ${value === 'Y' ? 'selected' : ''}>Yes</option>
                </select>
                <span class="text-muted small tracking-na ${hide ? '' : 'd-none'}">N/A</span>
            </td>`;
        }

        function contentViewRowHtml(e) {
            const visible = CONTENT_FIELD_VISIBILITY[e.activity_type] || [];
            const badgeFor = field => ynBadgeHtml(visible.includes(field) ? e[field] : 'NA');

            return `
                <td>${e.entry_date_label}</td>
                <td>${e.day_name}</td>
                <td class="fw-semibold">${escapeHtml(e.creator_name)}</td>
                <td>${escapeHtml(e.activity_type) || '—'}</td>
                <td class="small">${escapeHtml(e.feed_post_schedule) || '—'}</td>
                <td>${badgeFor('stories_posted')}</td>
                <td>${badgeFor('feed_posted')}</td>
                <td>${badgeFor('standards_stories')}</td>
                <td>${badgeFor('standards_feed')}</td>
                <td>${badgeFor('event')}</td>
                <td class="text-end">${contentActionsHtml(e.id, 'view')}</td>
            `;
        }

        function contentActivityTypeSelectHtml(selected) {
            const opts = ['<option value="">Select type</option>']
                .concat(CONTENT_ACTIVITY_TYPES.map(t => `<option value="${t}" ${t === selected ? 'selected' : ''}>${t}</option>`))
                .join('');
            return `<select class="form-select form-select-sm" data-field="activity_type" onchange="updateActivityTypeVisibility(this)">${opts}</select>`;
        }

        function contentEditRowHtml(e) {
            const visible = CONTENT_FIELD_VISIBILITY[e.activity_type] || [];
            const hideFeedSchedule = !visible.includes('event');
            return `
                <td><input type="date" class="form-control form-control-sm" data-field="entry_date" value="${e.entry_date}" onchange="this.closest('tr').querySelector('[data-day-display]').textContent = computeDayName(this.value)"></td>
                <td class="text-muted small"><span data-day-display>${computeDayName(e.entry_date)}</span></td>
                <td><input type="text" class="form-control form-control-sm" data-field="creator_name" list="contentCreatorList" value="${escapeHtml(e.creator_name)}"></td>
                <td>${contentActivityTypeSelectHtml(e.activity_type)}</td>
                <td data-feed-schedule-cell>
                    <input type="text" class="form-control form-control-sm ${hideFeedSchedule ? 'd-none' : ''}" data-field="feed_post_schedule" value="${escapeHtml(e.feed_post_schedule)}">
                    <span class="text-muted small feed-schedule-na ${hideFeedSchedule ? '' : 'd-none'}">N/A</span>
                </td>
                ${trackingFieldCellHtml('stories_posted', e.stories_posted, visible)}
                ${trackingFieldCellHtml('feed_posted', e.feed_posted, visible)}
                ${trackingFieldCellHtml('standards_stories', e.standards_stories, visible)}
                ${trackingFieldCellHtml('standards_feed', e.standards_feed, visible)}
                ${trackingFieldCellHtml('event', e.event, visible)}
                <td class="text-end">${contentActionsHtml(e.id, 'edit')}</td>
            `;
        }

        let contentEntries = {};
        document.querySelectorAll('#contentCalendarTable tr[data-entry-row]').forEach(row => {
            const entry = JSON.parse(row.dataset.entry);
            contentEntries[entry.id] = entry;
        });

        window.startEditContentRow = function (id) {
            const row = document.querySelector(`tr[data-entry-row="${id}"]`);
            if (row) row.innerHTML = contentEditRowHtml(contentEntries[id]);
        };

        window.cancelEditContentRow = function (id) {
            const row = document.querySelector(`tr[data-entry-row="${id}"]`);
            if (row) row.innerHTML = contentViewRowHtml(contentEntries[id]);
        };

        function readRowFields(row) {
            const fields = {};
            row.querySelectorAll('[data-field]').forEach(input => {
                fields[input.dataset.field] = input.value;
            });
            return fields;
        }

        window.saveEditContentRow = function (id) {
            const row = document.querySelector(`tr[data-entry-row="${id}"]`);
            const fields = readRowFields(row);

            fetch(`/kpis/content-entries/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(fields)
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        contentEntries[id] = data.entry;
                        row.innerHTML = contentViewRowHtml(data.entry);
                        showContentToast(data.message || 'Entry updated.');
                    } else {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not update entry.');
                        showContentToast(firstError, 'error');
                    }
                });
        };

        window.deleteContentRow = function (id) {
            if (!confirm('Delete this calendar entry?')) return;

            fetch(`/kpis/content-entries/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        const row = document.querySelector(`tr[data-entry-row="${id}"]`);
                        if (row) row.remove();
                        delete contentEntries[id];
                        showContentToast(data.message || 'Entry deleted.');
                        maybeShowEmptyState();
                    } else {
                        showContentToast(data.message || 'Could not delete entry.', 'error');
                    }
                });
        };

        function maybeShowEmptyState() {
            const tbody = document.querySelector('#contentCalendarTable tbody');
            if (tbody.querySelectorAll('tr[data-entry-row]').length === 0 && !document.getElementById('contentEmptyRow')) {
                const tr = document.createElement('tr');
                tr.id = 'contentEmptyRow';
                tr.innerHTML = '<td colspan="11" class="text-center text-muted py-4">No content calendar entries logged yet.</td>';
                tbody.appendChild(tr);
            }
        }

        const qaSaveBtn = document.getElementById('qaSaveBtn');
        if (qaSaveBtn) {
            qaSaveBtn.addEventListener('click', function () {
                const fields = {
                    entry_date: document.getElementById('qaDate').value,
                    creator_name: document.getElementById('qaCreator').value,
                    activity_type: document.getElementById('qaActivityType').value,
                    feed_post_schedule: document.getElementById('qaFeedSchedule').value,
                    stories_posted: document.getElementById('qaStoriesPosted').value,
                    feed_posted: document.getElementById('qaFeedPosted').value,
                    standards_stories: document.getElementById('qaStandardsStories').value,
                    standards_feed: document.getElementById('qaStandardsFeed').value,
                    event: document.getElementById('qaEvent').value,
                };

                if (!fields.entry_date || !fields.creator_name.trim() || !fields.activity_type) {
                    showContentToast('Date, Creator, and Activity Type are required.', 'error');
                    return;
                }

                fetch(`{{ route('kpi.content-entries.store') }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF_TOKEN,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(fields)
                    })
                    .then(r => r.json().then(data => ({ ok: r.ok, data })))
                    .then(({ ok, data }) => {
                        if (ok && data.success) {
                            contentEntries[data.entry.id] = data.entry;

                            const emptyRow = document.getElementById('contentEmptyRow');
                            if (emptyRow) emptyRow.remove();

                            const tr = document.createElement('tr');
                            tr.setAttribute('data-entry-row', data.entry.id);
                            tr.innerHTML = contentViewRowHtml(data.entry);
                            document.getElementById('contentQuickAddRow').insertAdjacentElement('afterend', tr);

                            // Reset the quick-add row for the next entry.
                            document.getElementById('qaCreator').value = '';
                            document.getElementById('qaActivityType').value = '';
                            document.getElementById('qaFeedSchedule').value = '';
                            document.getElementById('qaStoriesPosted').value = 'N';
                            document.getElementById('qaFeedPosted').value = 'N';
                            document.getElementById('qaStandardsStories').value = 'N';
                            document.getElementById('qaStandardsFeed').value = 'N';
                            document.getElementById('qaEvent').value = 'N';
                            updateActivityTypeVisibility(document.getElementById('qaActivityType'));

                            showContentToast(data.message || 'Entry added.');
                        } else {
                            const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not add entry.');
                            showContentToast(firstError, 'error');
                        }
                    });
            });
        }
    </script>
@endsection
