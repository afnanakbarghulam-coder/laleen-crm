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
</style>

@section('content')

    <div class="la-header">
        <div>
            <h4>Leads Analytics</h4>
            <p>Category mix, Needful Done status, and live follow-up health.</p>
        </div>
        <a href="{{ route('leads.index') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Back to Leads
        </a>
    </div>

    <div class="la-filter-card">
        <form method="GET" action="{{ route('leads.analytics') }}" id="laFilterForm">
            <select name="period" class="la-date-input" onchange="document.getElementById('laFilterForm').submit()">
                @foreach (\App\Http\Controllers\LeadController::PERIOD_LABELS as $key => $label)
                    <option value="{{ $key }}" {{ $period === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
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
                    <span class="la-kpi-label">Needful Done</span>
                    <span class="la-kpi-icon" style="background:rgba(142,168,138,.12); color:#7fa876;"><i class="bx bx-check-circle"></i></span>
                </div>
                <div class="la-kpi-value">{{ number_format($needfulCounts['yes']) }}</div>
                <div class="la-kpi-sub">Marked Yes in this period</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="la-kpi-card">
                <div class="la-kpi-top">
                    <span class="la-kpi-label">Cancelled</span>
                    <span class="la-kpi-icon" style="background:rgba(168,82,74,.1); color:#a8524a;"><i class="bx bx-x-circle"></i></span>
                </div>
                <div class="la-kpi-value">{{ number_format($categoryCounts['cancel'] ?? 0) }}</div>
                <div class="la-kpi-sub">Cancelled leads this period</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="la-kpi-card">
                <div class="la-kpi-top">
                    <span class="la-kpi-label">Overdue Right Now</span>
                    <span class="la-kpi-icon" style="background:rgba(168,82,74,.1); color:#a8524a;"><i class="bx bx-error-circle"></i></span>
                </div>
                <div class="la-kpi-value" style="color:#a8524a;">{{ number_format($followupPerformance['overdue']) }}</div>
                <div class="la-kpi-sub">Live snapshot &middot; all-time, not period-limited</div>
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
                <h6>Needful Done Status</h6>
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
        series: [{{ $needfulCounts['yes'] }}, {{ $needfulCounts['no'] }}, {{ $needfulCounts['unset'] }}],
        labels: ['Yes', 'No', 'Not Set'],
        colors: ['#8ea88a', '#a8524a', '#8a7d76'],
    }).render();

    new ApexCharts(document.querySelector('#laFollowupChart'), {
        ...laDonutBase,
        series: [{{ $followupPerformance['completed'] }}, {{ $followupPerformance['overdue'] }}, {{ $followupPerformance['upcoming'] }}, {{ $followupPerformance['no_date'] }}],
        labels: ['Completed', 'Overdue', 'Upcoming', 'No Date Set'],
        colors: ['#8ea88a', '#a8524a', '#c9a66b', '#8a7d76'],
    }).render();
</script>
@endsection
