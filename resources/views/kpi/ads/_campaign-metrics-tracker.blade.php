@php
    $filterActionUrl = $filterActionUrl ?? route('kpi.ads.index');
    $isStandaloneEcommerce = $isStandaloneEcommerce ?? false;
    $showBranchFilter = $showBranchFilter ?? false;
    $snapshotPeriodLabel = $snapshotPeriodLabel ?? null;
    $hasUniformPeriod = $hasUniformPeriod ?? true;
    $lastUpdatedHuman = $lastUpdatedHuman ?? null;

    $canEditMetrics = $metricsWorkspace === 'ecommerce'
        ? (auth()->check() && (auth()->user()->canEdit('ecommerce') || auth()->user()->canEdit('kpis')))
        : (auth()->check() && auth()->user()->canEdit('kpis'));

    $accCurr = $metricsSummary['account_currency'] ?? 'USD';
    $locCurr = $metricsSummary['local_currency'] ?? 'QAR';
    $currSym = match ($accCurr) {
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        default => $accCurr . ' ',
    };

    $activeColumns = $populatedMetricColumns
        ?? \App\Http\Controllers\Kpi\AdCampaignMetricsController::resolvePopulatedMetricColumns($campaignMetrics);

    // Build ONLY non-null summary cards (zero '—' placeholders, single clean row)
    $summaryCards = [];

    if (($metricsSummary['spend_local'] ?? null) !== null) {
        $spendSubParts = [];
        if (($metricsSummary['spend_account'] ?? null) !== null && $accCurr !== $locCurr) {
            $spendSubParts[] = $currSym . number_format((float) $metricsSummary['spend_account'], 2) . ' ' . $accCurr;
        }
        $summaryCards[] = [
            'label' => 'Amount Spent',
            'badge' => ($metricsSummary['is_spend_estimated_only'] ?? false)
                ? 'est. from CPM'
                : (($metricsSummary['has_est_spend'] ?? false) ? 'incl. est.' : null),
            'value' => number_format((float) $metricsSummary['spend_local'], 2),
            'unit' => $locCurr,
            'sub' => implode(' · ', $spendSubParts),
        ];
    }

    if (($metricsSummary['impressions'] ?? null) !== null || ($metricsSummary['reach'] ?? null) !== null) {
        $hasImpr = ($metricsSummary['impressions'] ?? null) !== null;
        $imprSub = [];
        if ($hasImpr && ($metricsSummary['reach'] ?? null) !== null) {
            $imprSub[] = 'Reach: ' . number_format((int) $metricsSummary['reach']);
        }
        if (($metricsSummary['frequency'] ?? null) !== null) {
            $imprSub[] = 'Freq: ' . number_format((float) $metricsSummary['frequency'], 2);
        }
        if (!$hasImpr && ($metricsSummary['cpm'] ?? null) !== null) {
            $imprSub[] = 'CPM: ' . $currSym . number_format((float) $metricsSummary['cpm'], 2);
        }
        $summaryCards[] = [
            'label' => $hasImpr ? 'Impressions' : 'Reach',
            'badge' => null,
            'value' => number_format((int) ($hasImpr ? $metricsSummary['impressions'] : $metricsSummary['reach'])),
            'unit' => null,
            'sub' => implode(' · ', $imprSub),
        ];
    }

    if (($metricsSummary['link_clicks'] ?? null) !== null) {
        $clickSub = [];
        if (($metricsSummary['ctr'] ?? null) !== null) {
            $clickSub[] = 'CTR: ' . number_format((float) $metricsSummary['ctr'], 2) . '%';
        }
        if (($metricsSummary['cpc'] ?? null) !== null) {
            $clickSub[] = 'CPC: ' . $currSym . number_format((float) $metricsSummary['cpc'], 2);
        }
        $summaryCards[] = [
            'label' => 'Link Clicks',
            'badge' => null,
            'value' => number_format((int) $metricsSummary['link_clicks']),
            'unit' => null,
            'sub' => implode(' · ', $clickSub),
        ];
    }

    if (($metricsSummary['revenue_local'] ?? null) !== null) {
        $revSub = [];
        if (($metricsSummary['roas'] ?? null) !== null) {
            $revSub[] = 'ROAS: ' . number_format((float) $metricsSummary['roas'], 2) . 'x';
        }
        if (($metricsSummary['roi'] ?? null) !== null) {
            $roiVal = (float) $metricsSummary['roi'];
            $revSub[] = 'ROI: ' . ($roiVal > 0 ? '+' : '') . number_format($roiVal, 1) . '%';
        }
        $summaryCards[] = [
            'label' => 'Revenue',
            'badge' => null,
            'value' => number_format((float) $metricsSummary['revenue_local'], 2),
            'unit' => $locCurr,
            'sub' => implode(' · ', $revSub),
        ];
    } elseif (
        ($metricsSummary['purchases'] ?? null) !== null
        || ($metricsSummary['leads_count'] ?? null) !== null
        || ($metricsSummary['results_count'] ?? null) !== null
    ) {
        $outcomeLabel = ($metricsSummary['purchases'] ?? null) !== null
            ? 'Purchases'
            : (($metricsSummary['leads_count'] ?? null) !== null ? 'Leads' : 'Results');
        $outcomeVal = $metricsSummary['purchases'] ?? $metricsSummary['leads_count'] ?? $metricsSummary['results_count'];
        $outSub = [];
        if (($metricsSummary['cpl_or_cpr'] ?? null) !== null) {
            $outSub[] = 'Cost: ' . $currSym . number_format((float) $metricsSummary['cpl_or_cpr'], 2);
        }
        if (($metricsSummary['cvr'] ?? null) !== null) {
            $outSub[] = 'CVR: ' . number_format((float) $metricsSummary['cvr'], 2) . '%';
        }
        $summaryCards[] = [
            'label' => $outcomeLabel,
            'badge' => null,
            'value' => number_format((int) $outcomeVal),
            'unit' => null,
            'sub' => implode(' · ', $outSub),
        ];
    } elseif (($metricsSummary['cpm'] ?? null) !== null) {
        $cpmSub = [];
        if (($metricsSummary['cpc'] ?? null) !== null) {
            $cpmSub[] = 'CPC: ' . $currSym . number_format((float) $metricsSummary['cpc'], 2);
        }
        if (($metricsSummary['ctr'] ?? null) !== null) {
            $cpmSub[] = 'CTR: ' . number_format((float) $metricsSummary['ctr'], 2) . '%';
        }
        $summaryCards[] = [
            'label' => 'CPM',
            'badge' => null,
            'value' => $currSym . number_format((float) $metricsSummary['cpm'], 2),
            'unit' => null,
            'sub' => implode(' · ', $cpmSub),
        ];
    }
@endphp

<div class="laleen-tracker-root">
    @if ($isStandaloneEcommerce)
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 no-print">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h4 class="mb-0" style="color: #f8efe6; font-weight: 600;">Ad Metrics</h4>
                <div class="d-inline-flex gap-1 ms-2">
                    <a href="{{ route('ecommerce.marketing.ad-metrics', ['region' => 'pk']) }}"
                       class="btn btn-sm py-1 px-2 {{ $metricsRegion === 'pk' ? 'laleen-rose-btn' : 'laleen-ghost-btn' }}">PK (PKR)</a>
                    <a href="{{ route('ecommerce.marketing.ad-metrics', ['region' => 'qa']) }}"
                       class="btn btn-sm py-1 px-2 {{ $metricsRegion === 'qa' ? 'laleen-rose-btn' : 'laleen-ghost-btn' }}">QA (QAR)</a>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('ecommerce.marketing', ['region' => $metricsRegion]) }}" class="btn btn-sm laleen-ghost-btn">
                    Back to Marketing
                </a>
                @if ($canEditMetrics)
                    <button type="button" class="btn btn-sm laleen-rose-btn" data-bs-toggle="modal" data-bs-target="#adScreenshotImportModal">
                        Upload Screenshot
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- Subtle Snapshot Period Badge + Optional Auto-Submitting Branch Pill --}}
    @if ($snapshotPeriodLabel || $lastUpdatedHuman || $showBranchFilter)
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                @if ($snapshotPeriodLabel || $lastUpdatedHuman)
                    <span class="laleen-snapshot-badge">
                        @if ($snapshotPeriodLabel)
                            Snapshot: <strong style="color:#f8efe6; font-weight:600;">{{ $snapshotPeriodLabel }}</strong>
                        @endif
                        @if ($snapshotPeriodLabel && $lastUpdatedHuman)
                            &middot;
                        @endif
                        @if ($lastUpdatedHuman)
                            Updated {{ $lastUpdatedHuman }}
                        @endif
                    </span>
                @endif
            </div>

            @if (!$isStandaloneEcommerce && $showBranchFilter)
                <form method="GET" action="{{ $filterActionUrl }}" class="no-print m-0">
                    <input type="hidden" name="tab" value="metrics">
                    <select name="metrics_branch" class="form-select form-select-sm laleen-branch-pill-select" onchange="this.form.submit()" aria-label="Filter by branch">
                        <option value="" {{ $metricsBranch === '' ? 'selected' : '' }}>All Branches</option>
                        <option value="old_airport" {{ $metricsBranch === 'old_airport' ? 'selected' : '' }}>Old Airport</option>
                        <option value="wakrah" {{ $metricsBranch === 'wakrah' ? 'selected' : '' }}>Al Wakrah</option>
                        <option value="both" {{ $metricsBranch === 'both' ? 'selected' : '' }}>Both Branches</option>
                    </select>
                </form>
            @endif
        </div>
    @endif

    @if ($campaignMetrics->isEmpty())
        <div class="laleen-quiet-empty">
            No campaign metrics for this period yet.
        </div>
    @else
        {{-- Single Clean Row of Active KPI Summary Cards --}}
        @if (count($summaryCards) > 0)
            @php
                $colClass = match (count($summaryCards)) {
                    1 => 'col-12 col-md-4',
                    2 => 'col-6 col-md-6',
                    3 => 'col-6 col-lg-4',
                    default => 'col-6 col-lg-3',
                };
            @endphp
            <div class="row g-3 mb-3">
                @foreach ($summaryCards as $card)
                    <div class="{{ $colClass }}">
                        <div class="laleen-kpi-tile h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="laleen-kpi-label">{{ $card['label'] }}</span>
                                @if (!empty($card['badge']))
                                    <span class="laleen-est-pill">{{ $card['badge'] }}</span>
                                @endif
                            </div>
                            <div class="laleen-kpi-value mt-1">
                                {{ $card['value'] }}
                                @if (!empty($card['unit']))
                                    <small>{{ $card['unit'] }}</small>
                                @endif
                            </div>
                            @if ($card['sub'] !== '')
                                <div class="laleen-kpi-sub">{{ $card['sub'] }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Dynamic Campaign Table (Renders ONLY Populated Non-Null Columns) --}}
        <div class="laleen-surface-card p-0 mb-4" style="overflow: hidden;">
            <div class="table-responsive">
                <table class="table laleen-metrics-table align-middle mb-0" id="adCampaignMetricsTable">
                    <thead>
                        <tr>
                            <th class="laleen-sticky-col" style="min-width: 220px;">Campaign</th>
                            @foreach ($activeColumns as $mKey => $mDef)
                                <th class="text-end" data-col-key="{{ $mKey }}" style="min-width: 108px;">
                                    {{ $mDef['short'] }}
                                </th>
                            @endforeach
                            @if ($canEditMetrics)
                                <th class="text-end no-print" style="width: 56px;"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($campaignMetrics as $row)
                            @php
                                $rowAcc = strtoupper((string) ($row->account_currency ?: 'USD'));
                                $rowLoc = strtoupper((string) ($row->local_currency ?: $locCurr));
                                $rowSym = match ($rowAcc) {
                                    'USD' => '$',
                                    'EUR' => '€',
                                    'GBP' => '£',
                                    default => $rowAcc . ' ',
                                };
                                $branchTitle = match ($row->branch) {
                                    'old_airport' => 'Old Airport',
                                    'wakrah' => 'Al Wakrah',
                                    default => null,
                                };
                                $showRowDate = !$hasUniformPeriod && $row->date_from && $row->date_to;
                            @endphp
                            <tr>
                                <td class="laleen-sticky-col">
                                    <div class="fw-semibold" style="color:#f8efe6;">{{ $row->campaign_name }}</div>
                                    @if ($showRowDate || ($branchTitle && $metricsWorkspace === 'salon') || $row->ad_category)
                                        <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                                            @if ($showRowDate)
                                                <span class="laleen-row-sub">
                                                    {{ $row->date_from->format('d M') }} – {{ $row->date_to->format('d M') }}
                                                </span>
                                            @endif
                                            @if ($branchTitle && $metricsWorkspace === 'salon')
                                                <span class="laleen-mini-tag">{{ $branchTitle }}</span>
                                            @endif
                                            @if ($row->ad_category)
                                                <span class="laleen-mini-tag is-gold">
                                                    {{ $row->ad_category }}{{ $row->link_crm_revenue ? ' · CRM QAR' : '' }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                @foreach ($activeColumns as $mKey => $mDef)
                                    @php $val = $row->{$mKey}; @endphp
                                    <td class="text-end" data-col-key="{{ $mKey }}">
                                        @if ($mKey === 'amount_spent')
                                            @if ($row->amount_spent !== null)
                                                <div class="fw-semibold" style="color:#f8efe6;">
                                                    {{ $rowSym }}{{ number_format((float) $row->amount_spent, 2) }}
                                                </div>
                                                @if ($rowAcc !== $rowLoc && $row->amount_spent_local !== null)
                                                    <div class="laleen-row-sub">{{ number_format((float) $row->amount_spent_local, 2) }} {{ $rowLoc }}</div>
                                                @endif
                                            @elseif ($row->est_amount_spent !== null)
                                                <div class="fw-semibold" style="color:#d4a373;" title="Estimated from Impressions × CPM">
                                                    ~{{ $rowSym }}{{ number_format((float) $row->est_amount_spent, 2) }}
                                                    <span class="laleen-est-pill ms-1">est.</span>
                                                </div>
                                                @if ($rowAcc !== $rowLoc && $row->est_amount_spent_local !== null)
                                                    <div class="laleen-row-sub">~{{ number_format((float) $row->est_amount_spent_local, 2) }} {{ $rowLoc }}</div>
                                                @endif
                                            @endif

                                        @elseif ($mKey === 'revenue')
                                            @if ($row->revenue !== null)
                                                <div class="fw-semibold" style="color:#6ee7b7;">
                                                    {{ $rowSym }}{{ number_format((float) $row->revenue, 2) }}
                                                </div>
                                                @if ($rowAcc !== $rowLoc && $row->revenue_local !== null)
                                                    <div class="laleen-row-sub">{{ number_format((float) $row->revenue_local, 2) }} {{ $rowLoc }}</div>
                                                @endif
                                            @elseif ($row->revenue_local !== null)
                                                <div class="fw-semibold" style="color:#6ee7b7;">
                                                    {{ number_format((float) $row->revenue_local, 2) }} {{ $rowLoc }}
                                                </div>
                                                <div class="laleen-row-sub">CRM Verified</div>
                                            @endif

                                        @elseif ($val !== null)
                                            @if ($mDef['type'] === 'currency')
                                                <span style="color:#f8efe6;">{{ $rowSym }}{{ number_format((float) $val, 2) }}</span>
                                            @elseif ($mDef['type'] === 'percent')
                                                @if ($mKey === 'roi')
                                                    <span style="color:{{ (float) $val >= 0 ? '#6ee7b7' : '#fda4af' }}; font-weight:600;">
                                                        {{ (float) $val > 0 ? '+' : '' }}{{ number_format((float) $val, 2) }}%
                                                    </span>
                                                @else
                                                    <span style="color:#f8efe6;">{{ number_format((float) $val, 2) }}%</span>
                                                @endif
                                            @elseif ($mDef['type'] === 'ratio')
                                                <span class="fw-semibold" style="color:#e5b8a8;">{{ number_format((float) $val, 2) }}x</span>
                                            @elseif ($mDef['type'] === 'integer')
                                                <span style="color:#f8efe6;">{{ number_format((int) $val) }}</span>
                                            @else
                                                <span style="color:#f8efe6;">{{ number_format((float) $val, 2) }}</span>
                                            @endif
                                        @endif
                                    </td>
                                @endforeach

                                @if ($canEditMetrics)
                                    <td class="text-end no-print">
                                        <div class="d-inline-flex align-items-center gap-1" data-del-wrap="{{ $row->id }}">
                                            <button type="button" class="laleen-icon-del" data-ask-del="{{ $row->id }}" title="Remove row">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                            <form method="POST" action="{{ route('kpi.ad-metrics.destroy', $row) }}" class="d-none" data-confirm-del="{{ $row->id }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm laleen-confirm-del-pill">Remove?</button>
                                                <button type="button" class="btn btn-sm laleen-ghost-btn py-0 px-2" data-cancel-del="{{ $row->id }}">✕</button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<style>
    .laleen-surface-card,
    .laleen-kpi-tile,
    .laleen-ad-modal {
        background: linear-gradient(180deg, #22161b 0%, #150e12 100%);
        border: 1px solid rgba(229, 184, 168, 0.14);
        border-radius: 16px;
        color: #f8efe6;
    }

    .laleen-snapshot-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(229, 184, 168, 0.14);
        font-size: 0.78rem;
        color: #b8a29a;
    }

    .laleen-branch-pill-select {
        background-color: #181014 !important;
        border: 1px solid rgba(229, 184, 168, 0.2) !important;
        color: #e5b8a8 !important;
        border-radius: 999px !important;
        padding: 4px 30px 4px 12px !important;
        font-size: 0.78rem !important;
        font-weight: 500;
        cursor: pointer;
    }

    .laleen-quiet-empty {
        padding: 28px 20px;
        text-align: center;
        color: #b8a29a;
        font-size: 0.88rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .laleen-rose-btn {
        background: linear-gradient(135deg, #d98f83 0%, #c4776b 100%);
        color: #180f13 !important;
        font-weight: 600;
        border: none;
        border-radius: 10px;
        padding: 7px 15px;
        transition: opacity 0.15s ease;
    }
    .laleen-rose-btn:hover {
        opacity: 0.92;
        color: #180f13 !important;
    }

    .laleen-ghost-btn {
        background: rgba(255, 255, 255, 0.04);
        color: #e5b8a8 !important;
        border: 1px solid rgba(229, 184, 168, 0.2);
        border-radius: 10px;
        font-weight: 500;
    }
    .laleen-ghost-btn:hover {
        background: rgba(229, 184, 168, 0.12);
    }

    .laleen-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #b8a29a;
    }

    .laleen-input,
    .laleen-cell-input {
        background-color: #120b0f !important;
        border: 1px solid rgba(229, 184, 168, 0.18) !important;
        color: #f8efe6 !important;
        border-radius: 9px;
    }
    .laleen-input:focus,
    .laleen-cell-input:focus {
        border-color: #d98f83 !important;
        box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.18) !important;
    }

    .laleen-live-fx-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 31px;
        padding: 4px 11px;
        border-radius: 9px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(229, 184, 168, 0.16);
        font-size: 0.78rem;
        color: #e5b8a8;
        white-space: nowrap;
    }
    .laleen-fx-dot {
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: #6ee7b7;
        flex-shrink: 0;
    }
    .laleen-fx-bare-input {
        width: 64px;
        background: transparent;
        border: none;
        border-bottom: 1px dashed rgba(229, 184, 168, 0.35);
        color: #f8efe6;
        font-weight: 600;
        font-size: 0.79rem;
        text-align: center;
        padding: 0 2px;
        outline: none;
    }
    .laleen-fx-bare-input:focus {
        border-bottom-color: #d98f83;
    }

    .laleen-kpi-tile {
        padding: 15px 18px;
    }
    .laleen-kpi-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #b8a29a;
    }
    .laleen-kpi-value {
        font-size: 1.38rem;
        font-weight: 700;
        color: #f8efe6;
    }
    .laleen-kpi-value small {
        font-size: 0.8rem;
        font-weight: 600;
        color: #d4a373;
    }
    .laleen-kpi-sub {
        font-size: 0.77rem;
        color: #b8a29a;
        margin-top: 3px;
    }

    .laleen-est-pill {
        font-size: 0.67rem;
        font-weight: 600;
        padding: 2px 7px;
        border-radius: 999px;
        background: rgba(212, 163, 115, 0.16);
        color: #d4a373;
    }

    .laleen-metrics-table th {
        background-color: #1c1216;
        color: #b8a29a;
        font-size: 0.74rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        padding: 11px 12px;
        white-space: nowrap;
    }
    .laleen-metrics-table td {
        padding: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        vertical-align: middle;
        white-space: nowrap;
        background-color: #181014;
    }
    .laleen-metrics-table tbody tr:hover td {
        background-color: #22171c;
    }

    /* Solid opaque sticky Campaign column — prevents scrolled metric bleed-through */
    .laleen-metrics-table th.laleen-sticky-col {
        position: sticky;
        left: 0;
        z-index: 4;
        background-color: #1c1216 !important;
        border-right: 1px solid rgba(229, 184, 168, 0.16);
        box-shadow: 6px 0 14px rgba(0, 0, 0, 0.36);
    }
    .laleen-metrics-table td.laleen-sticky-col {
        position: sticky;
        left: 0;
        z-index: 3;
        background-color: #181014 !important;
        border-right: 1px solid rgba(229, 184, 168, 0.16);
        box-shadow: 6px 0 14px rgba(0, 0, 0, 0.36);
    }
    .laleen-metrics-table tbody tr:hover td.laleen-sticky-col {
        background-color: #22171c !important;
    }

    .laleen-row-sub {
        font-size: 0.72rem;
        color: #b8a29a;
    }
    .laleen-mini-tag {
        font-size: 0.68rem;
        padding: 1px 7px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.06);
        color: #e5b8a8;
    }
    .laleen-mini-tag.is-gold {
        background: rgba(212, 163, 115, 0.15);
        color: #d4a373;
    }

    .laleen-icon-del {
        background: transparent;
        border: none;
        color: #b8a29a;
        padding: 4px 6px;
        border-radius: 6px;
    }
    .laleen-icon-del:hover {
        color: #fda4af;
        background: rgba(253, 164, 175, 0.1);
    }
    .laleen-confirm-del-pill {
        background: rgba(253, 164, 175, 0.18);
        color: #fda4af;
        border: 1px solid rgba(253, 164, 175, 0.35);
        font-size: 0.72rem;
        padding: 2px 8px;
        border-radius: 999px;
    }

    .laleen-ad-modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        padding: 16px 22px;
    }
    .laleen-ad-modal-footer {
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        padding: 12px 22px;
    }

    .laleen-dropzone {
        border: 1px dashed rgba(229, 184, 168, 0.3);
        border-radius: 12px;
        padding: 24px 18px;
        text-align: center;
        cursor: pointer;
        background: rgba(255, 255, 255, 0.02);
        transition: border-color 0.15s ease, background 0.15s ease;
    }
    .laleen-dropzone:hover,
    .laleen-dropzone.is-dragover {
        border-color: #d98f83;
        background: rgba(217, 143, 131, 0.06);
    }
    .laleen-link-accent {
        color: #e5b8a8;
        text-decoration: underline;
    }
    .laleen-kbd {
        background: rgba(255, 255, 255, 0.08);
        color: #f8efe6;
        border-radius: 5px;
        padding: 2px 6px;
        font-size: 0.75rem;
    }

    .laleen-chip-list {
        text-align: left;
    }
    .laleen-file-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(229, 184, 168, 0.12);
        color: #f8efe6;
        font-size: 0.79rem;
    }
    .laleen-chip-remove {
        background: transparent;
        border: none;
        color: #fda4af;
        font-size: 1rem;
        line-height: 1;
        padding: 0 2px;
    }

    .laleen-inline-alert {
        padding: 9px 13px;
        border-radius: 10px;
        font-size: 0.83rem;
    }
    .laleen-inline-alert.is-err {
        background: rgba(253, 164, 175, 0.12);
        color: #fda4af;
        border: 1px solid rgba(253, 164, 175, 0.28);
    }
    .laleen-inline-alert.is-ok {
        background: rgba(110, 231, 183, 0.12);
        color: #6ee7b7;
        border: 1px solid rgba(110, 231, 183, 0.28);
    }

    .laleen-verify-pill {
        display: inline-flex;
        align-items: center;
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 0.76rem;
        font-weight: 600;
    }
    .laleen-verify-pill.is-verified {
        background: rgba(110, 231, 183, 0.14);
        color: #6ee7b7;
    }
    .laleen-verify-pill.is-amber {
        background: rgba(212, 163, 115, 0.18);
        color: #d4a373;
    }
    .laleen-meta-pill {
        font-size: 0.75rem;
        color: #e5b8a8;
        padding: 4px 11px;
        border-radius: 999px;
        background: rgba(229, 184, 168, 0.1);
        border: 1px solid rgba(229, 184, 168, 0.18);
    }

    .laleen-preview-table-wrap {
        overflow-x: auto;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    .laleen-preview-table th {
        color: #b8a29a;
        font-size: 0.74rem;
        font-weight: 600;
        padding: 9px 8px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        white-space: nowrap;
        background: transparent;
    }
    .laleen-preview-table td {
        padding: 8px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        vertical-align: top;
        background: transparent;
    }
    .laleen-merge-tag {
        font-size: 0.67rem;
        padding: 2px 7px;
        border-radius: 999px;
    }
    .laleen-merge-tag.is-merge {
        background: rgba(110, 231, 183, 0.14);
        color: #6ee7b7;
    }
    .laleen-cell-hint {
        font-size: 0.68rem;
        color: #b8a29a;
        margin-top: 2px;
    }
    .laleen-cell-hint.is-est {
        color: #d4a373;
    }
    .laleen-row-del-btn {
        background: transparent;
        border: none;
        color: #b8a29a;
        font-size: 1.1rem;
        line-height: 1;
        padding: 4px;
    }
    .laleen-row-del-btn:hover {
        color: #fda4af;
    }

    @media print {
        .no-print {
            display: none !important;
        }
        .laleen-surface-card,
        .laleen-kpi-tile {
            background: #ffffff !important;
            color: #111111 !important;
            border: 1px solid #dddddd !important;
        }
        .laleen-kpi-value,
        .laleen-metrics-table td,
        .laleen-metrics-table th {
            color: #111111 !important;
            background: #ffffff !important;
        }
        .laleen-metrics-table th.laleen-sticky-col,
        .laleen-metrics-table td.laleen-sticky-col {
            position: static !important;
            background: #ffffff !important;
            box-shadow: none !important;
        }
    }
</style>

<script>
(function () {
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-ask-del]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-ask-del');
                btn.classList.add('d-none');
                document.querySelector(`[data-confirm-del="${id}"]`)?.classList.remove('d-none');
            });
        });

        document.querySelectorAll('[data-cancel-del]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-cancel-del');
                document.querySelector(`[data-confirm-del="${id}"]`)?.classList.add('d-none');
                document.querySelector(`[data-ask-del="${id}"]`)?.classList.remove('d-none');
            });
        });
    });
})();
</script>
