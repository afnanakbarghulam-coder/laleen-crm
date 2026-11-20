@php
    $analyticsCategories = $analyticsReport->computedCategories();
    $analyticsTotals = $analyticsReport->totals();
    $branchComparison = $analyticsReport->branchComparison();
    $insights = $analyticsReport->actionableInsights();

    $oa = $branchComparison['old_airport'];
    $wk = $branchComparison['wakrah'];
    $hasBranchRev = ($branchComparison['total_revenue'] ?? 0) > 0;
    $hasBranchBookings = ($branchComparison['total_bookings'] ?? 0) > 0;
    $oaShare = $hasBranchRev ? $oa['revenue_share'] : ($hasBranchBookings ? $oa['booking_share'] : 0);
    $wkShare = $hasBranchRev ? $wk['revenue_share'] : ($hasBranchBookings ? $wk['booking_share'] : 0);

    $insightsCount = count($insights);
    $snapshotsCount = method_exists($reports, 'total') ? $reports->total() : count($reports);

    $fin = $analyticsFinancials ?? \App\Http\Controllers\Kpi\AdsConversionController::computeAnalyticsFinancials(
        collect(),
        (float) ($analyticsTotals['total_revenue'] ?? 0.0),
        $analyticsFrom,
        $analyticsTo
    );
@endphp

<div class="laleen-analytics-root">
    {{-- 1. Compact Single-Row Header, Inline Date Filter & Drawer Triggers --}}
    <div class="laleen-analytics-topbar mb-3 no-print">
        <form method="GET" action="{{ route('kpi.ads.index') }}" class="d-flex align-items-center flex-wrap gap-2 m-0">
            <input type="hidden" name="tab" value="analytics">
            <h5 class="mb-0 me-2" style="color: #f8efe6; font-weight: 600; font-size: 1.08rem;">Ads Analytics</h5>

            <div class="d-inline-flex align-items-center gap-1">
                <label class="laleen-slim-lbl" for="analyticsFromInput">From</label>
                <input type="date" id="analyticsFromInput" name="analytics_from"
                       class="form-control form-control-sm laleen-slim-date"
                       value="{{ $analyticsFrom->format('Y-m-d') }}">
            </div>

            <div class="d-inline-flex align-items-center gap-1">
                <label class="laleen-slim-lbl" for="analyticsToInput">To</label>
                <input type="date" id="analyticsToInput" name="analytics_to"
                       class="form-control form-control-sm laleen-slim-date"
                       value="{{ $analyticsTo->format('Y-m-d') }}">
            </div>

            <button type="submit" class="btn btn-sm laleen-analytics-rose-btn">Filter</button>
            <a href="{{ route('kpi.ads.index', ['tab' => 'analytics']) }}" class="btn btn-sm laleen-analytics-ghost-btn">Reset</a>
        </form>

        <div class="d-flex align-items-center flex-wrap gap-2">
            <button type="button" class="btn btn-sm laleen-analytics-ghost-btn" data-open-ads-drawer="adsInsightsDrawer">
                Insights <span class="laleen-count-chip ms-1">{{ $insightsCount }}</span>
            </button>
            <button type="button" class="btn btn-sm laleen-analytics-ghost-btn" data-open-ads-drawer="adsSnapshotsDrawer">
                Saved Snapshots <span class="laleen-count-chip ms-1">{{ $snapshotsCount }}</span>
            </button>
        </div>
    </div>

    {{-- Compact Inline 2-Column Branch Split Bar --}}
    <div class="laleen-branch-split-bar mb-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                    <div class="d-inline-flex align-items-center gap-2">
                        <span class="laleen-branch-dot is-oa"></span>
                        <span class="fw-semibold" style="color:#f8efe6; font-size:0.86rem;">Old Airport</span>
                        @if ($branchComparison['leading_branch'] === 'Old Airport')
                            <span class="laleen-lead-tag">Leading</span>
                        @endif
                    </div>
                    <div class="laleen-branch-stats">
                        {{ $oa['bookings'] }} {{ \Illuminate\Support\Str::plural('booking', $oa['bookings']) }}
                        &middot; {{ number_format($oa['revenue'], 0) }} QAR
                        &middot; <strong style="color:#e5b8a8;">{{ $oaShare }}%</strong>
                    </div>
                </div>
            </div>

            <div class="col-md-6 laleen-branch-col-right">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                    <div class="d-inline-flex align-items-center gap-2">
                        <span class="laleen-branch-dot is-wk"></span>
                        <span class="fw-semibold" style="color:#f8efe6; font-size:0.86rem;">Al Wakrah</span>
                        @if ($branchComparison['leading_branch'] === 'Al Wakrah')
                            <span class="laleen-lead-tag">Leading</span>
                        @endif
                    </div>
                    <div class="laleen-branch-stats">
                        {{ $wk['bookings'] }} {{ \Illuminate\Support\Str::plural('booking', $wk['bookings']) }}
                        &middot; {{ number_format($wk['revenue'], 0) }} QAR
                        &middot; <strong style="color:#d4a373;">{{ $wkShare }}%</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="laleen-split-neon-wrap mt-2" id="adsBranchNeonBar">
            <div class="laleen-split-track">
                @if ($oaShare > 0 || $wkShare > 0)
                    <div class="laleen-split-oa {{ $wkShare <= 0 ? 'is-full' : '' }}" style="width: {{ $oaShare }}%;"></div>
                    <div class="laleen-split-wk {{ $oaShare <= 0 ? 'is-full' : '' }}" style="width: {{ $wkShare }}%;"></div>
                @else
                    <div class="laleen-split-oa" style="width: 50%; opacity: 0.45;"></div>
                    <div class="laleen-split-wk" style="width: 50%; opacity: 0.45;"></div>
                @endif
            </div>
            @if ($oaShare > 0 && $wkShare > 0)
                <span class="laleen-split-spark" style="left: {{ $oaShare }}%;" aria-hidden="true"></span>
            @endif
            <div class="laleen-neon-comet" id="adsBranchNeonComet" aria-hidden="true">
                <span class="laleen-neon-comet-tail"></span>
                <span class="laleen-neon-comet-orb"></span>
            </div>
        </div>
    </div>

    {{-- 3. Tighter Category Conversion Breakdown Table (7 Crisp Columns) --}}
    <div class="laleen-analytics-card p-0" style="overflow: hidden;">
        <div class="px-3 py-2 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid rgba(255,255,255,0.06);">
            <span class="fw-semibold" style="color:#f8efe6; font-size:0.88rem;">Category Conversion Breakdown</span>
            <span class="small" style="color:#b8a29a; font-size:0.76rem;">
                {{ $analyticsFrom->format('d M Y') }} – {{ $analyticsTo->format('d M Y') }}
            </span>
        </div>

        <div class="table-responsive">
            <table class="table laleen-dense-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="text-end">Leads</th>
                        <th class="text-end">Bookings</th>
                        <th style="min-width: 150px;">Conversion</th>
                        <th class="text-end">Avg Ticket</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($analyticsCategories as $c)
                        @php
                            $barColor = match($c['status']) {
                                'Above' => 'green',
                                'Near', 'Below' => 'amber',
                                default => 'red',
                            };
                            $badge = $analyticsReport->categoryBadge($c);
                        @endphp
                        <tr>
                            <td class="fw-semibold" style="color:#f8efe6;">{{ $c['name'] }}</td>
                            <td class="text-end">{{ $c['leads'] }}</td>
                            <td class="text-end">{{ $c['bookings'] }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="kpi-progress-track" style="width: 72px;">
                                        <div class="kpi-progress-fill {{ $barColor }}" style="width:{{ min($c['conversion'] / 20 * 100, 100) }}%"></div>
                                    </div>
                                    <span class="small fw-semibold">{{ $c['conversion'] }}%</span>
                                </div>
                            </td>
                            <td class="text-end">{{ number_format($c['avg_ticket'], 2) }}</td>
                            <td class="text-end">{{ number_format($c['revenue'], 2) }} <small class="text-muted">QAR</small></td>
                            <td class="text-end"><span class="kpi-badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4" style="color:#b8a29a;">No ad leads logged for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- BAND 1: Clean 3-Column Funnel Strip (Leads -> Booked Visits -> Overall Conversion) --}}
    <div class="row g-2 mt-3">
        <div class="col-12 col-md-4">
            <div class="laleen-slim-kpi">
                <span class="laleen-slim-kpi-label">Total Leads</span>
                <div class="laleen-slim-kpi-val">{{ number_format($analyticsTotals['total_leads']) }}</div>
                <div class="laleen-slim-kpi-sub">Incoming ad inquiries</div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="laleen-slim-kpi">
                <span class="laleen-slim-kpi-label">Booked Visits</span>
                <div class="laleen-slim-kpi-val">{{ number_format($analyticsTotals['total_bookings']) }}</div>
                <div class="laleen-slim-kpi-sub">Enhanced Calendar bookings</div>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="laleen-slim-kpi {{ $analyticsTotals['overall_met_target'] ? 'is-conv-met' : 'is-conv-below' }}">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="laleen-slim-kpi-label">Overall Conversion</span>
                    <span class="laleen-target-pill {{ $analyticsTotals['overall_met_target'] ? 'is-met' : 'is-below' }}">
                        Target 20%
                    </span>
                </div>
                <div class="laleen-slim-kpi-val" style="color: {{ $analyticsTotals['overall_met_target'] ? '#34D399' : '#f8efe6' }};">
                    {{ $analyticsTotals['overall_conversion'] }}%
                </div>
                <div class="laleen-slim-kpi-sub">Lead-to-paid visit rate</div>
            </div>
        </div>
    </div>

    {{-- BAND 2: Structured Financial P&L & Return Panel --}}
    @php
        $revFormatted = abs($fin['ad_revenue_qar'] - round($fin['ad_revenue_qar'])) < 0.005
            ? number_format($fin['ad_revenue_qar'], 0)
            : number_format($fin['ad_revenue_qar'], 2);
        $pnlPeriodText = ($fin['snapshot_differs'] && $fin['snapshot_range_full'])
            ? ('Ad Spend Snapshot: ' . $fin['snapshot_range_full'])
            : ($fin['snapshot_range_short'] ?? ($analyticsFrom->format('d M') . ' – ' . $analyticsTo->format('d M')));
    @endphp
    <div class="laleen-analytics-card laleen-pnl-card mt-2"
         id="adsProfitabilityPanel"
         data-revenue="{{ $fin['ad_revenue_qar'] }}"
         data-ad-spend="{{ $fin['ad_spend_qar'] }}"
         data-fee="{{ $fin['marketing_fee_qar'] }}">

        {{-- Card Header Bar (Title + Cost Mode Segmented Toggle + Ad Metrics Link) --}}
        <div class="laleen-pnl-header">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span class="fw-semibold" style="color:#f8efe6; font-size:0.9rem;">Profitability &amp; Return</span>
                <span class="laleen-pnl-period-chip">{{ $pnlPeriodText }}</span>
                @if ($fin['is_spend_estimated'] || $fin['has_est_spend'])
                    <span class="laleen-est-chip">{{ $fin['is_spend_estimated'] ? 'est. from CPM' : 'incl. est.' }}</span>
                @endif
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2 no-print">
                <div class="laleen-seg-toggle" id="adsCostModeToggle" role="group" aria-label="Marketing cost calculation mode">
                    <button type="button" class="laleen-seg-btn active" data-cost-mode="all_in">
                        All-In (+{{ number_format($fin['marketing_fee_qar'], 0) }} QAR Fee)
                    </button>
                    <button type="button" class="laleen-seg-btn" data-cost-mode="ad_only">
                        Ad Spend Only
                    </button>
                </div>

                <a href="{{ route('kpi.ads.index', ['tab' => 'metrics']) }}" class="btn btn-sm laleen-analytics-ghost-btn">
                    Ad Metrics &#8599;
                </a>
            </div>
        </div>

        {{-- Left-to-Right Visual Equation Grid --}}
        <div class="laleen-pnl-grid">
            {{-- Column 1: Ad Revenue --}}
            <div class="laleen-pnl-cell">
                <span class="laleen-slim-kpi-label">Ad Revenue</span>
                <div class="laleen-pnl-val">
                    {{ $revFormatted }} <small>QAR</small>
                </div>
                <div class="laleen-slim-kpi-sub">
                    From {{ number_format($analyticsTotals['total_bookings']) }} {{ \Illuminate\Support\Str::plural('booking', $analyticsTotals['total_bookings']) }}
                </div>
            </div>

            <div class="laleen-pnl-op" aria-hidden="true">&minus;</div>

            {{-- Column 2: Total Cost + Itemized Breakdown --}}
            <div class="laleen-pnl-cell">
                <span class="laleen-slim-kpi-label">Total Cost</span>
                <div class="laleen-pnl-val">
                    <span id="adsPnlTotalCostVal">{{ number_format($fin['total_marketing_cost_qar'], 2) }}</span> <small>QAR</small>
                </div>
                <div class="laleen-pnl-breakdown">
                    <div>
                        Meta Ad Spend: <strong style="color:#e5b8a8;">{{ number_format($fin['ad_spend_qar'], 2) }} QAR</strong>@if ($fin['ad_spend_usd'] > 0) <span>(${{ number_format($fin['ad_spend_usd'], 2) }})</span>@endif
                    </div>
                    <div id="adsPnlFeeLine">
                        Marketing Fee: <span class="laleen-fee-amt">{{ number_format($fin['marketing_fee_qar'], 2) }} QAR</span>
                        <span class="laleen-fee-excl-tag d-none" id="adsPnlFeeExclBadge">Excluded</span>
                    </div>
                </div>
            </div>

            <div class="laleen-pnl-op" aria-hidden="true">=</div>

            {{-- Column 3: Net Profit (Hero Cell) --}}
            <div class="laleen-pnl-cell laleen-pnl-hero {{ $fin['net_profit_qar'] >= 0 ? 'is-pos' : 'is-neg' }}" id="adsPnlNetProfitCell">
                <span class="laleen-slim-kpi-label">Net Profit</span>
                <div class="laleen-pnl-val" id="adsPnlNetProfitWrap" style="color: {{ $fin['net_profit_qar'] >= 0 ? '#34D399' : '#fda4af' }};">
                    <span id="adsPnlNetProfitVal">{{ ($fin['net_profit_qar'] > 0 ? '+' : '') . number_format($fin['net_profit_qar'], 2) }}</span> <small>QAR</small>
                </div>
                <div class="laleen-slim-kpi-sub">Revenue &minus; Total Cost</div>
            </div>

            <div class="laleen-pnl-vsep" aria-hidden="true"></div>

            {{-- Column 4: ROAS --}}
            <div class="laleen-pnl-cell laleen-pnl-return-cell">
                <span class="laleen-slim-kpi-label">ROAS</span>
                <div class="laleen-pnl-val" style="color:#e5b8a8;">
                    {{ $fin['roas'] !== null ? number_format($fin['roas'], 2) . 'x' : '—' }}
                </div>
                <div class="laleen-slim-kpi-sub">Revenue &divide; Ad Spend</div>
            </div>

            {{-- Column 5: Net ROI --}}
            <div class="laleen-pnl-cell laleen-pnl-return-cell">
                <span class="laleen-slim-kpi-label">Net ROI</span>
                <div class="laleen-pnl-val" id="adsPnlRoiWrap" style="color: {{ ($fin['roi_percent'] ?? 0) >= 0 ? '#34D399' : '#fda4af' }};">
                    <span id="adsPnlRoiVal">{{ $fin['roi_percent'] !== null ? (($fin['roi_percent'] > 0 ? '+' : '') . number_format($fin['roi_percent'], 1) . '%') : '—' }}</span>
                </div>
                <div class="laleen-slim-kpi-sub">Net Profit &divide; Total Cost</div>
            </div>
        </div>
    </div>

    {{-- 4A. Right-Side Slide-Over Drawer: Actionable Insights --}}
    <div id="adsDrawerBackdrop" class="laleen-drawer-backdrop d-none no-print"></div>

    <aside id="adsInsightsDrawer" class="laleen-slide-drawer no-print" aria-hidden="true" aria-labelledby="adsInsightsDrawerTitle">
        <div class="laleen-drawer-header">
            <div class="d-flex align-items-center gap-2">
                <h6 class="mb-0" id="adsInsightsDrawerTitle" style="color:#f8efe6; font-weight:600;">Actionable Insights</h6>
                <span class="laleen-count-chip">{{ $insightsCount }}</span>
            </div>
            <button type="button" class="laleen-drawer-close" data-close-ads-drawer aria-label="Close">&times;</button>
        </div>

        <div class="laleen-drawer-body">
            @forelse ($insights as $insight)
                @php
                    $actionBadge = match ($insight['action']) {
                        'scale' => ['label' => 'Scale', 'class' => 'kpi-badge-green'],
                        'fix' => ['label' => 'Fix', 'class' => 'kpi-badge-amber'],
                        default => ['label' => 'Pause', 'class' => 'kpi-badge-red'],
                    };
                @endphp
                <div class="laleen-insight-item">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="fw-semibold" style="color:#f8efe6; font-size:0.88rem;">{{ $insight['name'] }}</span>
                            @if ($insight['low_sample'])
                                <span class="laleen-sample-note">Small sample</span>
                            @endif
                        </div>
                        <span class="kpi-badge {{ $actionBadge['class'] }}" style="min-width:58px; text-align:center;">
                            {{ $actionBadge['label'] }}
                        </span>
                    </div>
                    <div class="small" style="color:#b8a29a; line-height:1.45;">{{ $insight['reason'] }}</div>
                </div>
            @empty
                <div class="text-center py-4 small" style="color:#b8a29a;">
                    No category insights for this period yet.
                </div>
            @endforelse
        </div>
    </aside>

    {{-- 4B. Right-Side Slide-Over Drawer: Saved Snapshots (Ads Conversion Reports) --}}
    <aside id="adsSnapshotsDrawer" class="laleen-slide-drawer no-print" aria-hidden="true" aria-labelledby="adsSnapshotsDrawerTitle">
        <div class="laleen-drawer-header">
            <div class="d-flex align-items-center gap-2">
                <h6 class="mb-0" id="adsSnapshotsDrawerTitle" style="color:#f8efe6; font-weight:600;">Saved Snapshots</h6>
                <span class="laleen-count-chip">{{ $snapshotsCount }}</span>
            </div>
            <button type="button" class="laleen-drawer-close" data-close-ads-drawer aria-label="Close">&times;</button>
        </div>

        <div class="laleen-drawer-body">
            @moduleEdit('kpis')
                <form method="POST" action="{{ route('kpi.ads.store') }}" class="laleen-save-snapshot-bar mb-3">
                    @csrf
                    <input type="hidden" name="date_from" value="{{ $analyticsFrom->format('Y-m-d') }}">
                    <input type="hidden" name="date_to" value="{{ $analyticsTo->format('Y-m-d') }}">
                    <div class="small" style="color:#b8a29a;">
                        Active period: <strong style="color:#f8efe6;">{{ $analyticsFrom->format('d M Y') }} – {{ $analyticsTo->format('d M Y') }}</strong>
                    </div>
                    <button type="submit" class="btn btn-sm laleen-analytics-rose-btn">
                        + Save Current Period Snapshot
                    </button>
                </form>
            @endmoduleEdit

            @forelse ($reports as $report)
                @php $totals = $report->totals(); @endphp
                <div class="laleen-snapshot-item">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <a href="{{ route('kpi.ads.show', $report) }}" class="laleen-snapshot-link">
                                {{ $report->date_from->format('d M Y') }} – {{ $report->date_to->format('d M Y') }} ↗
                            </a>
                            <div class="small mt-1" style="color:#b8a29a; font-size:0.77rem;">
                                {{ $totals['total_leads'] }} leads
                                &middot; {{ $totals['total_bookings'] }} bookings
                                &middot; {{ number_format($totals['total_revenue'], 0) }} QAR
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <span class="kpi-badge {{ $totals['overall_met_target'] ? 'kpi-badge-green' : 'kpi-badge-red' }}">
                                {{ $totals['overall_conversion'] }}%
                            </span>
                            @moduleEdit('kpis')
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button" class="laleen-snap-del-icon" data-ask-snap-del="{{ $report->id }}" title="Delete snapshot">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                    <form action="{{ route('kpi.ads.destroy', $report) }}" method="POST" class="d-none m-0" data-confirm-snap-del="{{ $report->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm laleen-snap-confirm-pill">Remove?</button>
                                        <button type="button" class="btn btn-sm laleen-analytics-ghost-btn py-0 px-2" data-cancel-snap-del="{{ $report->id }}">&times;</button>
                                    </form>
                                </div>
                            @endmoduleEdit
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-4 small" style="color:#b8a29a;">
                    No saved snapshots yet.
                </div>
            @endforelse

            @if (method_exists($reports, 'hasPages') && $reports->hasPages())
                <div class="d-flex justify-content-end mt-3">
                    {{ $reports->links() }}
                </div>
            @endif
        </div>
    </aside>
</div>

<style>
    .laleen-analytics-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 14px;
        background: linear-gradient(180deg, rgba(34, 22, 27, 0.92) 0%, rgba(21, 14, 18, 0.96) 100%);
        border: 1px solid rgba(229, 184, 168, 0.14);
        border-radius: 14px;
    }

    .laleen-slim-lbl {
        font-size: 0.74rem;
        font-weight: 600;
        color: #b8a29a;
        margin: 0;
    }

    .laleen-slim-date {
        background-color: #120b0f !important;
        border: 1px solid rgba(229, 184, 168, 0.18) !important;
        color: #f8efe6 !important;
        border-radius: 8px;
        font-size: 0.78rem;
        padding: 4px 8px;
        width: auto;
    }

    .laleen-analytics-rose-btn {
        background: linear-gradient(135deg, #d98f83 0%, #c4776b 100%);
        color: #180f13 !important;
        font-weight: 600;
        border: none;
        border-radius: 8px;
        font-size: 0.78rem;
        padding: 5px 12px;
    }
    .laleen-analytics-rose-btn:hover {
        opacity: 0.92;
        color: #180f13 !important;
    }

    .laleen-analytics-ghost-btn {
        background: rgba(255, 255, 255, 0.04);
        color: #e5b8a8 !important;
        border: 1px solid rgba(229, 184, 168, 0.2);
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 500;
        padding: 5px 11px;
    }
    .laleen-analytics-ghost-btn:hover {
        background: rgba(229, 184, 168, 0.12);
    }

    .laleen-count-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 18px;
        padding: 0 6px;
        border-radius: 999px;
        background: rgba(229, 184, 168, 0.16);
        color: #f8efe6;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .laleen-slim-kpi,
    .laleen-branch-split-bar,
    .laleen-analytics-card {
        background: linear-gradient(180deg, rgba(34, 22, 27, 0.92) 0%, rgba(21, 14, 18, 0.96) 100%);
        border: 1px solid rgba(229, 184, 168, 0.14);
        border-radius: 13px;
        color: #f8efe6;
    }

    .laleen-slim-kpi {
        padding: 10px 14px;
        height: 100%;
    }
    .laleen-slim-kpi-label {
        font-size: 0.73rem;
        font-weight: 600;
        color: #b8a29a;
    }
    .laleen-slim-kpi-val {
        font-size: 1.28rem;
        font-weight: 700;
        color: #f8efe6;
        line-height: 1.25;
        margin-top: 2px;
    }
    .laleen-slim-kpi-val small {
        font-size: 0.76rem;
        font-weight: 600;
        color: #d4a373;
    }
    .laleen-slim-kpi-sub {
        font-size: 0.72rem;
        color: #b8a29a;
        margin-top: 3px;
        line-height: 1.3;
    }
    .laleen-est-chip {
        font-size: 0.65rem;
        font-weight: 600;
        padding: 1px 6px;
        border-radius: 999px;
        background: rgba(212, 163, 115, 0.16);
        color: #d4a373;
    }
    .laleen-slim-kpi.is-conv-met {
        border-color: rgba(52, 211, 153, 0.28);
        background: linear-gradient(180deg, rgba(24, 38, 34, 0.72) 0%, rgba(21, 14, 18, 0.96) 100%);
    }
    .laleen-slim-kpi.is-conv-below {
        border-color: rgba(253, 164, 175, 0.22);
    }

    /* Band 2: Structured Profitability & Return Panel */
    .laleen-pnl-card {
        overflow: hidden;
    }
    .laleen-pnl-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    .laleen-pnl-period-chip {
        font-size: 0.71rem;
        color: #b8a29a;
        padding: 2px 9px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.04);
    }
    .laleen-seg-toggle {
        display: inline-flex;
        padding: 2px;
        border-radius: 9px;
        background: rgba(12, 8, 10, 0.65);
        border: 1px solid rgba(229, 184, 168, 0.16);
    }
    .laleen-seg-btn {
        background: transparent;
        border: none;
        color: #b8a29a;
        font-size: 0.73rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 7px;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .laleen-seg-btn:hover {
        color: #f8efe6;
    }
    .laleen-seg-btn.active {
        background: rgba(217, 143, 131, 0.22);
        color: #f8efe6;
    }

    .laleen-pnl-grid {
        display: grid;
        grid-template-columns: minmax(130px, 1fr) auto minmax(205px, 1.35fr) auto minmax(165px, 1.15fr) 1px minmax(105px, 0.8fr) minmax(115px, 0.85fr);
        align-items: center;
        gap: 10px;
        padding: 14px 16px;
    }
    @media (max-width: 1199px) {
        .laleen-pnl-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .laleen-pnl-op,
        .laleen-pnl-vsep {
            display: none !important;
        }
    }
    @media (max-width: 575px) {
        .laleen-pnl-grid {
            grid-template-columns: 1fr;
        }
    }

    .laleen-pnl-cell {
        min-width: 0;
    }
    .laleen-pnl-val {
        font-size: 1.3rem;
        font-weight: 700;
        color: #f8efe6;
        line-height: 1.25;
        margin-top: 2px;
    }
    .laleen-pnl-val small {
        font-size: 0.76rem;
        font-weight: 600;
        color: #d4a373;
    }
    .laleen-pnl-op {
        font-size: 1.2rem;
        font-weight: 500;
        color: rgba(229, 184, 168, 0.42);
        padding: 0 2px;
        user-select: none;
    }
    .laleen-pnl-vsep {
        width: 1px;
        align-self: stretch;
        background: rgba(255, 255, 255, 0.07);
        margin: 0 4px;
    }
    .laleen-pnl-breakdown {
        font-size: 0.72rem;
        color: #b8a29a;
        margin-top: 4px;
        line-height: 1.42;
    }
    .laleen-pnl-breakdown .is-excluded .laleen-fee-amt {
        text-decoration: line-through;
        opacity: 0.48;
    }
    .laleen-fee-excl-tag {
        font-size: 0.64rem;
        font-weight: 600;
        padding: 1px 6px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.07);
        color: #b8a29a;
        margin-left: 4px;
    }

    .laleen-pnl-hero {
        padding: 10px 14px;
        border-radius: 11px;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }
    .laleen-pnl-hero.is-pos {
        background: rgba(52, 211, 153, 0.06);
        border-color: rgba(52, 211, 153, 0.26);
    }
    .laleen-pnl-hero.is-neg {
        background: rgba(253, 164, 175, 0.06);
        border-color: rgba(253, 164, 175, 0.24);
    }
    .laleen-pnl-return-cell {
        padding-left: 6px;
    }

    .laleen-target-pill {
        font-size: 0.66rem;
        font-weight: 600;
        padding: 1px 7px;
        border-radius: 999px;
    }
    .laleen-target-pill.is-met {
        background: rgba(110, 231, 183, 0.14);
        color: #6ee7b7;
    }
    .laleen-target-pill.is-below {
        background: rgba(253, 164, 175, 0.14);
        color: #fda4af;
    }

    .laleen-branch-split-bar {
        padding: 10px 16px 12px;
        overflow: visible !important;
        position: relative;
    }
    @media (min-width: 768px) {
        .laleen-branch-col-right {
            border-left: 1px solid rgba(255, 255, 255, 0.06);
            padding-left: 16px;
        }
    }
    .laleen-branch-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        display: inline-block;
    }
    .laleen-branch-dot.is-oa { background: #d98f83; }
    .laleen-branch-dot.is-wk { background: #d4a373; }

    .laleen-lead-tag {
        font-size: 0.66rem;
        font-weight: 600;
        padding: 1px 7px;
        border-radius: 999px;
        background: rgba(110, 231, 183, 0.14);
        color: #6ee7b7;
    }
    .laleen-branch-stats {
        font-size: 0.79rem;
        color: #b8a29a;
    }

    .laleen-split-neon-wrap {
        position: relative;
        overflow: visible !important;
        padding: 5px 0;
        cursor: pointer;
    }
    .laleen-split-track {
        position: relative;
        display: flex;
        height: 5px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.07);
        overflow: visible !important;
        box-shadow:
            0 0 8px rgba(244, 143, 177, 0.24),
            0 0 12px rgba(255, 200, 87, 0.18);
        filter: drop-shadow(0 0 4px rgba(244, 143, 177, 0.24));
    }
    .laleen-split-oa {
        height: 100%;
        border-radius: 999px 0 0 999px;
        background: linear-gradient(90deg, #d97593 0%, #F48FB1 35%, #ffd6e2 50%, #DDA29E 75%, #F48FB1 100%);
        background-size: 200% 100%;
        box-shadow:
            0 0 6px rgba(244, 143, 177, 0.34),
            0 0 11px rgba(221, 162, 158, 0.22);
        animation: neonEnergyFlow 2.8s linear infinite !important;
    }
    .laleen-split-oa.is-full {
        border-radius: 999px;
    }
    .laleen-split-wk {
        height: 100%;
        border-radius: 0 999px 999px 0;
        background: linear-gradient(90deg, #FFC857 0%, #ffe9ad 35%, #ffb62e 65%, #d4a373 85%, #FFC857 100%);
        background-size: 200% 100%;
        box-shadow:
            0 0 6px rgba(255, 200, 87, 0.34),
            0 0 11px rgba(212, 163, 115, 0.22);
        animation: neonEnergyFlow 2.8s linear infinite !important;
    }
    .laleen-split-wk.is-full {
        border-radius: 999px;
    }
    .laleen-split-spark {
        position: absolute;
        top: 50%;
        width: 6.5px;
        height: 6.5px;
        border-radius: 999px;
        transform: translate(-50%, -50%);
        background: #ffffff;
        box-shadow:
            0 0 5px rgba(255, 255, 255, 0.8),
            0 0 10px rgba(244, 143, 177, 0.42),
            0 0 14px rgba(255, 200, 87, 0.28);
        pointer-events: none;
        z-index: 3;
        animation: laleenSparkPulse 1.9s ease-in-out infinite !important;
    }
    .laleen-neon-comet {
        position: absolute;
        top: 50%;
        left: 2%;
        transform: translate(-50%, -50%);
        pointer-events: none;
        z-index: 4;
        will-change: left, transform;
        animation: neonCometGlide 2.6s ease-in-out infinite alternate !important;
    }
    .laleen-neon-comet.is-tracking {
        animation: none !important;
        transition: left 0.08s cubic-bezier(0.22, 1, 0.36, 1) !important;
    }
    .laleen-neon-comet-tail {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 96px;
        height: 6px;
        border-radius: 999px;
        transform: translate(-50%, -50%);
        background: linear-gradient(
            90deg,
            rgba(244, 143, 177, 0) 0%,
            rgba(244, 143, 177, 0.38) 26%,
            rgba(255, 255, 255, 0.64) 50%,
            rgba(255, 200, 87, 0.40) 74%,
            rgba(255, 200, 87, 0) 100%
        );
        filter: blur(0.6px) drop-shadow(0 0 6px rgba(255, 220, 205, 0.48));
    }
    .laleen-neon-comet-orb {
        position: relative;
        display: block;
        width: 7.5px;
        height: 7.5px;
        border-radius: 999px;
        background: #FFFFFF;
        box-shadow:
            0 0 6px rgba(255, 255, 255, 0.85),
            0 0 12px rgba(244, 143, 177, 0.45),
            0 0 18px rgba(255, 200, 87, 0.30);
    }
    @keyframes neonCometGlide {
        0% {
            left: 2%;
        }
        100% {
            left: 98%;
        }
    }
    @keyframes neonEnergyFlow {
        0% {
            background-position: 200% 0;
        }
        100% {
            background-position: 0% 0;
        }
    }
    @keyframes laleenSparkPulse {
        0%, 100% {
            opacity: 0.85;
            transform: translate(-50%, -50%) scale(0.92);
        }
        50% {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1.22);
        }
    }

    .laleen-dense-table th {
        background: rgba(15, 10, 13, 0.55);
        color: #b8a29a;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        padding: 8px 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        white-space: nowrap;
    }
    .laleen-dense-table td {
        padding: 9px 12px;
        font-size: 0.84rem;
        color: #f8efe6;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        background: transparent;
    }
    .laleen-dense-table tbody tr:last-child td {
        border-bottom: none;
    }
    .laleen-dense-table tbody tr:hover td {
        background: rgba(229, 184, 168, 0.04);
    }

    /* Slide-Over Drawers (portaled directly to document.body above navbar & sidebar) */
    .laleen-drawer-backdrop {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        background: rgba(10, 6, 8, 0.66);
        backdrop-filter: blur(2px);
        z-index: 9998;
    }
    .laleen-slide-drawer {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: min(460px, 94vw);
        height: 100vh;
        height: 100dvh;
        background: linear-gradient(180deg, #22161b 0%, #140d11 100%);
        border-left: 1px solid rgba(229, 184, 168, 0.18);
        box-shadow: -12px 0 36px rgba(0, 0, 0, 0.58);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        transform: translateX(104%);
        transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .laleen-slide-drawer.is-open {
        transform: translateX(0);
    }

    .laleen-drawer-header {
        position: sticky;
        top: 0;
        z-index: 2;
        flex-shrink: 0;
        background: #22161b;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }
    .laleen-drawer-close {
        background: transparent;
        border: none;
        color: #b8a29a;
        font-size: 1.45rem;
        line-height: 1;
        padding: 0 4px;
    }
    .laleen-drawer-close:hover {
        color: #f8efe6;
    }
    .laleen-drawer-body {
        padding: 16px 20px;
        overflow-y: auto;
        flex: 1 1 auto;
    }

    .laleen-insight-item,
    .laleen-snapshot-item {
        padding: 12px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    .laleen-insight-item:last-child,
    .laleen-snapshot-item:last-child {
        border-bottom: none;
    }

    .laleen-sample-note {
        font-size: 0.68rem;
        padding: 1px 7px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.06);
        color: #b8a29a;
    }

    .laleen-save-snapshot-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        padding-bottom: 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }

    .laleen-snapshot-link {
        color: #e5b8a8;
        font-weight: 600;
        font-size: 0.86rem;
        text-decoration: none;
    }
    .laleen-snapshot-link:hover {
        color: #f8efe6;
        text-decoration: underline;
    }

    .laleen-snap-del-icon {
        background: transparent;
        border: none;
        color: #b8a29a;
        padding: 3px 6px;
        border-radius: 6px;
    }
    .laleen-snap-del-icon:hover {
        color: #fda4af;
        background: rgba(253, 164, 175, 0.1);
    }
    .laleen-snap-confirm-pill {
        background: rgba(253, 164, 175, 0.18);
        color: #fda4af;
        border: 1px solid rgba(253, 164, 175, 0.35);
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 999px;
    }

    @media print {
        .no-print {
            display: none !important;
        }
        .laleen-slim-kpi,
        .laleen-branch-split-bar,
        .laleen-analytics-card {
            background: #ffffff !important;
            color: #111111 !important;
            border: 1px solid #dddddd !important;
        }
        .laleen-slim-kpi-val,
        .laleen-dense-table td,
        .laleen-dense-table th {
            color: #111111 !important;
            background: #ffffff !important;
        }
    }
</style>

<script>
(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const backdrop = document.getElementById('adsDrawerBackdrop');
        const drawers = document.querySelectorAll('.laleen-slide-drawer');

        // Portal backdrop and slide-over drawers directly onto document.body so no ancestor
        // transform / backdrop-filter / overflow container or top navbar ever clips them.
        if (backdrop && backdrop.parentElement !== document.body) {
            document.body.appendChild(backdrop);
        }
        drawers.forEach((d) => {
            if (d.parentElement !== document.body) {
                document.body.appendChild(d);
            }
        });

        function closeAllDrawers() {
            drawers.forEach((d) => {
                d.classList.remove('is-open');
                d.setAttribute('aria-hidden', 'true');
            });
            backdrop?.classList.add('d-none');
            document.body.style.overflow = '';
        }

        function openDrawer(id) {
            closeAllDrawers();
            const target = document.getElementById(id);
            if (!target) return;
            if (target.parentElement !== document.body) {
                document.body.appendChild(target);
            }
            backdrop?.classList.remove('d-none');
            target.classList.add('is-open');
            target.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        document.querySelectorAll('[data-open-ads-drawer]').forEach((btn) => {
            btn.addEventListener('click', () => {
                openDrawer(btn.getAttribute('data-open-ads-drawer'));
            });
        });

        document.querySelectorAll('[data-close-ads-drawer]').forEach((btn) => {
            btn.addEventListener('click', closeAllDrawers);
        });

        backdrop?.addEventListener('click', closeAllDrawers);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllDrawers();
            }
        });

        // Inline 2-step snapshot delete (zero browser confirm() dialogs)
        document.querySelectorAll('[data-ask-snap-del]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-ask-snap-del');
                btn.classList.add('d-none');
                document.querySelector(`[data-confirm-snap-del="${id}"]`)?.classList.remove('d-none');
            });
        });

        document.querySelectorAll('[data-cancel-snap-del]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-cancel-snap-del');
                document.querySelector(`[data-confirm-snap-del="${id}"]`)?.classList.add('d-none');
                document.querySelector(`[data-ask-snap-del="${id}"]`)?.classList.remove('d-none');
            });
        });

        // Interactive cursor-follow neon comet on the branch split bar
        const branchCard = document.querySelector('.laleen-branch-split-bar');
        const neonBar = document.getElementById('adsBranchNeonBar');
        const neonComet = document.getElementById('adsBranchNeonComet');

        if (branchCard && neonBar && neonComet) {
            branchCard.addEventListener('mousemove', (e) => {
                const rect = neonBar.getBoundingClientRect();
                if (rect.width <= 0) return;
                const relX = Math.max(6, Math.min(rect.width - 6, e.clientX - rect.left));
                neonComet.classList.add('is-tracking');
                neonComet.style.left = `${relX}px`;
            });

            branchCard.addEventListener('mouseleave', () => {
                neonComet.classList.remove('is-tracking');
                neonComet.style.left = '';
            });
        }

        // Live Cost-Mode Segmented Toggle: All-In (+1,530 QAR Fee) vs Ad Spend Only
        const pnlPanel = document.getElementById('adsProfitabilityPanel');
        const costModeToggle = document.getElementById('adsCostModeToggle');

        if (pnlPanel && costModeToggle) {
            const rev = parseFloat(pnlPanel.dataset.revenue || '0');
            const adSpend = parseFloat(pnlPanel.dataset.adSpend || '0');
            const fee = parseFloat(pnlPanel.dataset.fee || '1530');

            const totalCostEl = document.getElementById('adsPnlTotalCostVal');
            const feeLineEl = document.getElementById('adsPnlFeeLine');
            const feeExclBadge = document.getElementById('adsPnlFeeExclBadge');
            const netCellEl = document.getElementById('adsPnlNetProfitCell');
            const netWrapEl = document.getElementById('adsPnlNetProfitWrap');
            const netValEl = document.getElementById('adsPnlNetProfitVal');
            const roiWrapEl = document.getElementById('adsPnlRoiWrap');
            const roiValEl = document.getElementById('adsPnlRoiVal');

            const fmt2 = (n) => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const fmt1 = (n) => n.toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

            costModeToggle.querySelectorAll('[data-cost-mode]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const mode = btn.getAttribute('data-cost-mode');
                    costModeToggle.querySelectorAll('.laleen-seg-btn').forEach((b) => b.classList.remove('active'));
                    btn.classList.add('active');

                    const includeFee = (mode !== 'ad_only');
                    const totalCost = adSpend + (includeFee ? fee : 0);
                    const netProfit = rev - totalCost;

                    if (totalCostEl) totalCostEl.textContent = fmt2(totalCost);

                    if (feeLineEl) feeLineEl.classList.toggle('is-excluded', !includeFee);
                    if (feeExclBadge) feeExclBadge.classList.toggle('d-none', includeFee);

                    const isPos = netProfit >= 0;
                    if (netCellEl) {
                        netCellEl.classList.toggle('is-pos', isPos);
                        netCellEl.classList.toggle('is-neg', !isPos);
                    }
                    if (netWrapEl) netWrapEl.style.color = isPos ? '#34D399' : '#fda4af';
                    if (netValEl) netValEl.textContent = (netProfit > 0 ? '+' : '') + fmt2(netProfit);

                    if (totalCost > 0 && (adSpend > 0 || rev > 0)) {
                        const roi = (netProfit / totalCost) * 100;
                        if (roiWrapEl) roiWrapEl.style.color = roi >= 0 ? '#34D399' : '#fda4af';
                        if (roiValEl) roiValEl.textContent = (roi > 0 ? '+' : '') + fmt1(roi) + '%';
                    } else {
                        if (roiWrapEl) roiWrapEl.style.color = '#b8a29a';
                        if (roiValEl) roiValEl.textContent = '—';
                    }
                });
            });
        }

        // Auto-open Saved Snapshots drawer if user clicked snapshot pagination
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('reports_page') || urlParams.get('open') === 'snapshots') {
            openDrawer('adsSnapshotsDrawer');
        }
    });
})();
</script>
