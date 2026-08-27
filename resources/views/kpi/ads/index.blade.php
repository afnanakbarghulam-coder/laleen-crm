@extends('layouts.app')
@section('title', 'Ads Conversion Reports')

@section('content')
    <div class="d-flex gap-2 mb-4">
        <button type="button" class="ads-tab-btn btn btn-sm {{ $activeTab === 'leads' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="leads">Ad Leads Log</button>
        <button type="button" class="ads-tab-btn btn btn-sm {{ $activeTab === 'reports' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="reports">Ads Conversion Reports</button>
        <button type="button" class="ads-tab-btn btn btn-sm {{ $activeTab === 'analytics' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="analytics">Ads Analytics</button>
    </div>

    {{-- ================= AD LEADS LOG ================= --}}
    <div id="ads-pane-leads" class="ads-tab-pane {{ $activeTab === 'leads' ? '' : 'd-none' }}">
        <div class="kpi-header">
            <div>
                <h4>Ad Leads Data Entry</h4>
                <p>Log every incoming ad lead here — it automatically feeds the Ads Conversion Report and Analytics for any matching date range.</p>
            </div>
            @moduleEdit('kpis')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adLeadEntryModal" onclick="resetAdLeadEntryForm()">
                    <i class="bx bx-plus me-1"></i> Add Entry
                </button>
            @endmoduleEdit
        </div>

        <div class="kpi-panel mb-3">
            <form method="GET" action="{{ route('kpi.ads.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="leads">
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="entries_from" class="form-control" value="{{ $entriesFrom?->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="entries_to" class="form-control" value="{{ $entriesTo?->format('Y-m-d') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if ($entriesFrom || $entriesTo)
                        <a href="{{ route('kpi.ads.index', ['tab' => 'leads']) }}" class="btn btn-outline-secondary">Reset</a>
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
                            <th>Contact</th>
                            <th>Ad Type</th>
                            <th class="text-end">Ticket (QAR)</th>
                            <th>Branch</th>
                            <th>Remarks</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($adLeadEntries as $entry)
                            @php [$entryCountryCode, $entryPhoneNumber] = \App\Models\Lead::splitPhone($entry->phone); @endphp
                            <tr>
                                <td>{{ $entry->date->format('d M Y') }}</td>
                                <td>{{ $entry->phone }}</td>
                                <td>{{ $entry->category }}</td>
                                <td class="text-end">{{ $entry->ticket_amount > 0 ? number_format($entry->ticket_amount, 2) : '—' }}</td>
                                <td>
                                    @if ($entry->branch)
                                        <span class="kpi-badge kpi-badge-green">{{ $branches[$entry->branch] }}</span>
                                    @else
                                        <span class="text-muted small">Unbooked</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $entry->remarks ?? '—' }}</td>
                                <td class="text-end">
                                    @moduleEdit('kpis')
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-outline-warning ad-lead-edit-btn"
                                                data-entry='@json(array_merge($entry->toArray(), ["country_code" => $entryCountryCode, "phone_number" => $entryPhoneNumber]))'
                                                title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form action="{{ route('kpi.ad-leads.destroy', $entry) }}" method="POST" onsubmit="return confirm('Delete this ad lead entry?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    @endmoduleEdit
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No ad leads logged{{ ($entriesFrom || $entriesTo) ? ' for this period' : ' yet' }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-4">{{ $adLeadEntries->links() }}</div>

        @moduleEdit('kpis')
            @include('kpi.ads._lead-entry-modal', ['uid' => 'new', 'entry' => null])
        @endmoduleEdit
    </div>

    {{-- ================= ADS CONVERSION REPORTS ================= --}}
    <div id="ads-pane-reports" class="ads-tab-pane {{ $activeTab === 'reports' ? '' : 'd-none' }}">
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
                                <td>{{ count($report->computedCategories()) }}</td>
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
    </div>

    {{-- ================= ADS ANALYTICS ================= --}}
    <div id="ads-pane-analytics" class="ads-tab-pane {{ $activeTab === 'analytics' ? '' : 'd-none' }}">
        @php
            $analyticsCategories = $analyticsReport->computedCategories();
            $analyticsTotals = $analyticsReport->totals();
        @endphp

        <div class="kpi-header">
            <div>
                <h4>Ads Analytics</h4>
                <p>Live performance breakdown computed straight from the Ad Leads Log — nothing needs to be saved first.</p>
            </div>
            <span class="kpi-badge {{ $analyticsTotals['overall_met_target'] ? 'kpi-badge-green' : 'kpi-badge-red' }}">
                Overall Conversion: {{ $analyticsTotals['overall_conversion'] }}% {{ $analyticsTotals['overall_met_target'] ? '(Target met)' : '(Below 20% target)' }}
            </span>
        </div>

        <div class="kpi-panel mb-3">
            <form method="GET" action="{{ route('kpi.ads.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="analytics">
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="analytics_from" class="form-control" value="{{ $analyticsFrom->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="analytics_to" class="form-control" value="{{ $analyticsTo->format('Y-m-d') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('kpi.ads.index', ['tab' => 'analytics']) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Overall Conversion</div>
                    <div class="kpi-stat-value">{{ $analyticsTotals['overall_conversion'] }}%</div>
                    <div class="kpi-stat-sub">Target: 20%</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Total Leads</div>
                    <div class="kpi-stat-value">{{ $analyticsTotals['total_leads'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Total Bookings</div>
                    <div class="kpi-stat-value">{{ $analyticsTotals['total_bookings'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Total Revenue</div>
                    <div class="kpi-stat-value">{{ number_format($analyticsTotals['total_revenue'], 0) }} <small class="fs-6">QAR</small></div>
                </div>
            </div>
        </div>

        <div class="kpi-panel mb-3">
            <h6>Category Conversion Breakdown</h6>
            <div class="table-responsive">
                <table class="table kpi-table align-middle">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Leads</th>
                            <th>Bookings</th>
                            <th style="min-width:140px;">Conversion</th>
                            <th>Avg Ticket</th>
                            <th>Revenue</th>
                            <th>vs 20% Target</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($analyticsCategories as $c)
                            @php
                                $barColor = match($c['status']) { 'Above' => 'green', 'Near' => 'amber', 'Below' => 'amber', default => 'red' };
                                $badgeColor = match($c['status']) { 'Above' => 'kpi-badge-green', 'Near' => 'kpi-badge-amber', 'Below' => 'kpi-badge-amber', default => 'kpi-badge-red' };
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $c['name'] }}</td>
                                <td>{{ $c['leads'] }}</td>
                                <td>{{ $c['bookings'] }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="kpi-progress-track" style="width:80px;">
                                            <div class="kpi-progress-fill {{ $barColor }}" style="width:{{ min($c['conversion'] / 20 * 100, 100) }}%"></div>
                                        </div>
                                        <span class="small fw-semibold">{{ $c['conversion'] }}%</span>
                                    </div>
                                </td>
                                <td>{{ number_format($c['avg_ticket'], 2) }}</td>
                                <td>{{ number_format($c['revenue'], 2) }}</td>
                                <td>{{ $c['pct_of_target'] }}%</td>
                                <td><span class="kpi-badge {{ $badgeColor }}">{{ $c['status'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No ad leads logged for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="kpi-panel">
            <h6>Branch Performance</h6>
            <table class="table kpi-table">
                <thead><tr><th>Branch</th><th class="text-end">Bookings</th><th class="text-end">Revenue (QAR)</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold">Old Airport</td>
                        <td class="text-end">{{ $analyticsReport->old_airport_bookings }}</td>
                        <td class="text-end">{{ number_format($analyticsReport->old_airport_revenue, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Al Wakrah</td>
                        <td class="text-end">{{ $analyticsReport->wakrah_bookings }}</td>
                        <td class="text-end">{{ number_format($analyticsReport->wakrah_revenue, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.querySelectorAll('.ads-tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.ads-tab-btn').forEach(b => {
                    b.classList.remove('btn-dark');
                    b.classList.add('btn-outline-secondary');
                });
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-dark');

                document.querySelectorAll('.ads-tab-pane').forEach(p => p.classList.add('d-none'));
                document.getElementById('ads-pane-' + this.dataset.tab).classList.remove('d-none');
            });
        });
    </script>
@endsection
