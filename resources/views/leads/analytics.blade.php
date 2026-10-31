@extends('layouts.app')

@section('title', 'Leads Analytics')

<style>
    :root {
        --la-border: rgba(217, 143, 131,0.16);
        --la-border-strong: rgba(217, 143, 131,0.3);
        --la-muted: #c9a39a;
        --la-ink: #e79a91;
        --la-primary: #d98f83;
    }

    .la-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 18px;
    }

    .la-header h4 { margin-bottom: 2px; }
    .la-header p { color: var(--la-muted); margin-bottom: 0; font-size: 13.5px; }

    .la-filter-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--la-border);
        border-radius: 16px;
        padding: 14px 18px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .la-date-input {
        height: 36px;
        border: 1px solid var(--la-border-strong);
        border-radius: 9px;
        padding: 0 10px;
        font-size: 13px;
        font-weight: 600;
        color: var(--la-ink);
        background-color: #241e1c;
    }

    .la-kpi-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--la-border);
        border-radius: 16px;
        padding: 18px 20px;
        height: 100%;
    }

    .la-kpi-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .la-kpi-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--la-muted);
    }

    .la-kpi-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .la-kpi-value {
        font-size: 26px;
        font-weight: 700;
        color: var(--la-ink);
        letter-spacing: -.01em;
        line-height: 1.2;
    }

    .la-kpi-sub {
        margin-top: 10px;
        font-size: 12.5px;
        color: var(--la-muted);
    }

    .la-chart-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--la-border);
        border-radius: 16px;
        padding: 18px 20px;
        height: 100%;
    }

    .la-chart-card h6 { font-weight: 700; margin-bottom: 2px; }
    .la-chart-card .la-chart-sub { font-size: 12.5px; color: var(--la-muted); margin-bottom: 10px; }

    .la-filter-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--la-muted);
    }

    .la-rate-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }

    .la-rate-badge.rate-good {
        background: rgba(74, 222, 128, 0.15);
        color: #4ade80;
        border: 1px solid rgba(74, 222, 128, 0.35);
    }

    .la-rate-badge.rate-warn {
        background: rgba(234, 179, 8, 0.15);
        color: #eab308;
        border: 1px solid rgba(234, 179, 8, 0.35);
    }
</style>

@section('content')

    <div class="la-header">
        <div>
            <h4>Leads Analytics</h4>
            <p>Conversion outcomes, win-back performance, live follow-up health, and agent closing leaderboard.</p>
        </div>
        <a href="{{ route('leads.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Back to Leads
        </a>
    </div>

    <div class="la-filter-card justify-content-between">
        <form method="GET" action="{{ route('leads.analytics') }}" id="laFilterForm" class="d-flex align-items-center gap-3 flex-wrap mb-0">
            <div class="d-flex align-items-center gap-2">
                <label class="la-filter-label mb-0">Preset</label>
                <select name="period" class="la-date-input"
                    onchange="const f = document.getElementById('laFilterForm'); f.querySelector('[name=start_date]').value=''; f.querySelector('[name=end_date]').value=''; f.submit();">
                    @if (!empty($isCustomRange))
                        <option value="custom" selected disabled>Custom Date Range</option>
                    @endif
                    @foreach (\App\Http\Controllers\LeadController::PERIOD_LABELS as $key => $label)
                        <option value="{{ $key }}" {{ $period === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="d-flex align-items-center gap-2">
                <label class="la-filter-label mb-0" for="laStartDate">From</label>
                <input type="date" id="laStartDate" name="start_date" class="la-date-input" value="{{ $startDate ?? '' }}">
            </div>

            <div class="d-flex align-items-center gap-2">
                <label class="la-filter-label mb-0" for="laEndDate">To</label>
                <input type="date" id="laEndDate" name="end_date" class="la-date-input" value="{{ $endDate ?? '' }}">
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="submit" class="btn btn-sm btn-primary px-3" style="height: 36px;">
                    <i class="bx bx-filter-alt me-1"></i> Apply Filter
                </button>
                @if (!empty($isCustomRange))
                    <a href="{{ route('leads.analytics') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center" style="height: 36px;">
                        Reset
                    </a>
                @endif
            </div>
        </form>
        <span class="text-muted small">{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }} &middot; leads created in this range</span>
    </div>

    <!-- KPI CARDS -->
    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
            <div class="la-kpi-card">
                <div class="la-kpi-top">
                    <span class="la-kpi-label">Total Leads</span>
                    <span class="la-kpi-icon" style="background:rgba(217,143,131,.1); color:var(--la-primary);"><i class="bx bx-user-plus"></i></span>
                </div>
                <div class="la-kpi-value">{{ number_format($totalLeads) }}</div>
                <div class="la-kpi-sub">Created in selected period</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="la-kpi-card">
                <div class="la-kpi-top">
                    <span class="la-kpi-label">Booked Appointments (Conversion Rate)</span>
                    <span class="la-kpi-icon" style="background:rgba(74,222,128,.12); color:#4ade80;"><i class="bx bx-calendar-check"></i></span>
                </div>
                <div class="d-flex align-items-baseline gap-2">
                    <div class="la-kpi-value" style="color:#4ade80;">{{ number_format($conversionRate ?? 0, 1) }}%</div>
                    <span class="small text-muted">({{ number_format($bookedLeadsCount ?? 0) }} booked)</span>
                </div>
                <div class="la-kpi-sub">Target: &ge; 30%</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="la-kpi-card">
                <div class="la-kpi-top">
                    <span class="la-kpi-label">Win-Back Rate (Cancel / No-Show)</span>
                    <span class="la-kpi-icon" style="background:rgba(201,166,107,.14); color:#c9a66b;"><i class="bx bx-revision"></i></span>
                </div>
                <div class="la-kpi-value">{{ $winBackBooked ?? 0 }} / {{ $winBackTotal ?? 0 }} <span class="fs-6 fw-semibold text-muted">recovered ({{ number_format($winBackRate ?? 0, 1) }}%)</span></div>
                <div class="la-kpi-sub">Cancelled &amp; No-show leads converted to Booked</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="la-kpi-card">
                <div class="la-kpi-top">
                    <span class="la-kpi-label">Overdue Right Now</span>
                    <span class="la-kpi-icon" style="background:rgba(168,82,74,.1); color:#a8524a;"><i class="bx bx-error-circle"></i></span>
                </div>
                <div class="la-kpi-value" style="color:#a8524a;">{{ number_format($followupPerformance['overdue']) }}</div>
                <div class="la-kpi-sub">Live snapshot &middot; requires immediate action</div>
            </div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="row g-3 mb-3">
        <div class="col-xl-4">
            <div class="la-chart-card">
                <h6>Leads by Category</h6>
                <div class="la-chart-sub">Selected period</div>
                <div id="laCategoryChart"></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="la-chart-card">
                <h6>Lead Conversion Outcomes</h6>
                <div class="la-chart-sub">Selected period</div>
                <div id="laNeedfulChart"></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="la-chart-card">
                <h6>Follow-up Performance</h6>
                <div class="la-chart-sub">Live, all-time snapshot</div>
                <div id="laFollowupChart"></div>
            </div>
        </div>
    </div>

    <!-- AGENT CLOSING LEADERBOARD -->
    <div class="la-chart-card mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h6 class="mb-1">Agent Closing Leaderboard</h6>
                <div class="la-chart-sub mb-0">Conversion performance for leads created in {{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</div>
            </div>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th class="text-center">Leads Assigned (Period)</th>
                        <th class="text-center">Booked (Won)</th>
                        <th class="text-center">Conversion Rate %</th>
                        <th class="text-center">Current Overdue Leads</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agentLeaderboard ?? [] as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['name'] }}</td>
                            <td class="text-center">{{ number_format($row['assigned']) }}</td>
                            <td class="text-center">
                                <span class="fw-bold" style="color: #4ade80;">{{ number_format($row['booked']) }}</span>
                            </td>
                            <td class="text-center">
                                <span class="la-rate-badge {{ $row['conversion_rate'] >= 30 ? 'rate-good' : 'rate-warn' }}">
                                    {{ number_format($row['conversion_rate'], 1) }}%
                                </span>
                            </td>
                            <td class="text-center">
                                @if ($row['overdue'] > 0)
                                    <span class="badge rounded-pill" style="background: rgba(168,82,74,.18); color: #ef4444;">
                                        {{ number_format($row['overdue']) }} overdue
                                    </span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">No agents found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection

@section('scripts')
<script>
    // Explicit colors everywhere text can appear - ApexCharts' own light/dark
    // heuristics can't be trusted against this theme's near-black cards, and
    // an animated entrance risks a screenshot landing on a mid-animation,
    // near-invisible frame. Both are switched off here.
    const laTextColor = '#f2e6e2';
    const laMutedColor = '#c9a39a';

    const laDonutBase = {
        chart: { type: 'donut', height: 300, fontFamily: 'inherit', foreColor: laTextColor, animations: { enabled: false } },
        dataLabels: {
            enabled: true,
            formatter: (v) => v.toFixed(1) + '%',
            style: { colors: ['#ffffff'] },
            dropShadow: { enabled: true, top: 0, left: 0, blur: 2, opacity: 0.65 },
        },
        legend: { position: 'bottom', labels: { colors: laTextColor } },
        stroke: { colors: ['#241e1c'] },
    };

    new ApexCharts(document.querySelector('#laCategoryChart'), {
        ...laDonutBase,
        series: @json($categorySeries),
        labels: @json($categoryLabels),
        colors: ['#8ea88a', '#8aa6ab', '#c9a66b', '#a8524a', '#8a7d76'],
        plotOptions: { pie: { donut: { labels: { show: true,
            name: { color: laTextColor },
            value: { color: laTextColor },
            total: { show: true, label: 'Total', color: laMutedColor, formatter: () => '{{ $totalLeads }}' },
        } } } },
    }).render();

    new ApexCharts(document.querySelector('#laNeedfulChart'), {
        ...laDonutBase,
        series: [{{ $needfulCounts['booked'] ?? 0 }}, {{ $needfulCounts['yes'] ?? 0 }}, {{ $needfulCounts['no'] ?? 0 }}, {{ $needfulCounts['unset'] ?? 0 }}],
        labels: ['Booked Appointment', 'In Progress / Contacted', 'Lost / Not Interested', 'Unworked / Pending'],
        colors: ['#4ade80', '#eab308', '#a8524a', '#8a7d76'],
    }).render();

    new ApexCharts(document.querySelector('#laFollowupChart'), {
        ...laDonutBase,
        series: [{{ $followupPerformance['completed'] }}, {{ $followupPerformance['overdue'] }}, {{ $followupPerformance['upcoming'] }}, {{ $followupPerformance['no_date'] }}],
        labels: ['Completed (Booked)', 'Overdue', 'Upcoming', 'No Date Set'],
        colors: ['#4ade80', '#a8524a', '#c9a66b', '#8a7d76'],
    }).render();
</script>
@endsection
