@extends('layouts.app')
@section('title', 'Staff Sales Performance')

@section('content')
    <div class="d-flex gap-2 mb-4">
        <button type="button" class="staff-sales-tab-btn btn btn-sm {{ $activeTab === 'sales' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="sales">Sales &amp; Upsells</button>
        <button type="button" class="staff-sales-tab-btn btn btn-sm {{ $activeTab === 'analytics' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="analytics">Analytics</button>
    </div>

    {{-- ================= SALES & UPSELLS ================= --}}
    <div id="staff-sales-pane-sales" class="staff-sales-tab-pane {{ $activeTab === 'sales' ? '' : 'd-none' }}">
        @php
            $salesStaff = $salesAnalytics->computedStaff($branch);
            $salesTotals = $salesAnalytics->totals($branch);
            $salesProrated = $salesAnalytics->proratedTarget();
        @endphp

        <div class="kpi-header">
            <div>
                <h4>Sales &amp; Upsells</h4>
                <p>Live per-staff upsell totals for the selected branch and date range, pulled straight from the calendar's logged upsells — nothing to generate or save.</p>
            </div>
            <span class="kpi-badge kpi-badge-{{ $salesTotals['border'] }}">Team: {{ $salesTotals['team_pct'] }}%</span>
        </div>

        <div class="kpi-panel mb-3">
            <form method="GET" action="{{ route('kpi.staff-sales.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="sales">
                <div class="col-md-3">
                    <label class="form-label">Branch</label>
                    <select name="sales_branch" class="form-select">
                        @foreach ($branches as $key => $label)
                            <option value="{{ $key }}" {{ $branch === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" name="sales_from" class="form-control" value="{{ $salesFrom->format('Y-m-d') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" name="sales_to" class="form-control" value="{{ $salesTo->format('Y-m-d') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Target/staff (QAR)</label>
                    <input type="number" step="0.01" min="0" name="sales_target" class="form-control" value="{{ $salesTarget }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('kpi.staff-sales.index', ['tab' => 'sales']) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Team Total</div>
                    <div class="kpi-stat-value">{{ number_format($salesTotals['team_total'], 0) }} <small class="fs-6">QAR</small></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Prorated Target / Staff</div>
                    <div class="kpi-stat-value">{{ number_format($salesProrated, 0) }} <small class="fs-6">QAR</small></div>
                    <div class="kpi-stat-sub">{{ $salesAnalytics->daysElapsed() }} of 31 days</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Top Performer</div>
                    <div class="kpi-stat-value" style="font-size:19px;">{{ $salesTotals['top_performer'] }}</div>
                    <div class="kpi-stat-sub">{{ number_format($salesTotals['top_performer_amount'], 2) }} QAR</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Zero Upsell</div>
                    <div class="kpi-stat-value">{{ $salesTotals['zero_upsell_count'] }}</div>
                    <div class="kpi-stat-sub">of {{ $salesTotals['staff_count'] }} staff</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            @forelse ($salesStaff as $s)
                <div class="col-md-6">
                    <div class="kpi-shift-card {{ $s['border'] }}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">{{ $s['name'] }}</h6>
                                <div class="text-muted small">{{ number_format($s['upsell'], 2) }} of {{ number_format($s['prorated_target'], 2) }} QAR</div>
                            </div>
                            <span class="kpi-gap-pill">{{ $s['gap'] > 0 ? 'Gap: ' . number_format($s['gap'], 0) : 'Exceeded by ' . number_format(abs($s['gap']), 0) }}</span>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-3 mb-2">
                            <div class="kpi-shift-big-pct">{{ $s['pct'] }}%</div>
                        </div>
                        <div class="kpi-progress-track">
                            <div class="kpi-progress-fill {{ $s['border'] }}" style="width:{{ min($s['pct'], 100) }}%"></div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <p class="text-muted small mb-0">No active staff found for {{ $branches[$branch] }}.</p>
                </div>
            @endforelse
        </div>

        <div class="kpi-panel">
            <h6>Team Total</h6>
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>{{ number_format($salesTotals['team_total'], 2) }} of {{ number_format($salesTotals['team_target'], 2) }} QAR</span>
                <span>{{ $salesTotals['team_pct'] }}%</span>
            </div>
            <div class="kpi-progress-track">
                <div class="kpi-progress-fill {{ $salesTotals['border'] }}" style="width:{{ min($salesTotals['team_pct'], 100) }}%"></div>
            </div>
        </div>
    </div>

    {{-- ================= ANALYTICS ================= --}}
    <div id="staff-sales-pane-analytics" class="staff-sales-tab-pane {{ $activeTab === 'analytics' ? '' : 'd-none' }}">
        @php
            $overall = $analytics->overallTotals();
            $branchComparison = $analytics->branchComparison();
            $topPerformers = $analytics->topPerformers(10);
        @endphp

        <div class="kpi-header">
            <div>
                <h4>Staff Sales Analytics</h4>
                <p>Cross-branch comparison and staff rankings, computed live for the selected date range.</p>
            </div>
            <span class="kpi-badge kpi-badge-{{ $overall['border'] }}">Overall: {{ $overall['team_pct'] }}%</span>
        </div>

        <div class="kpi-panel mb-3">
            <form method="GET" action="{{ route('kpi.staff-sales.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="analytics">
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="analytics_from" class="form-control" value="{{ $analyticsFrom->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="analytics_to" class="form-control" value="{{ $analyticsTo->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Target/staff (QAR)</label>
                    <input type="number" step="0.01" min="0" name="analytics_target" class="form-control" value="{{ $analyticsTarget }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="{{ route('kpi.staff-sales.index', ['tab' => 'analytics']) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Team Total (All Branches)</div>
                    <div class="kpi-stat-value">{{ number_format($overall['team_total'], 0) }} <small class="fs-6">QAR</small></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Prorated Target / Staff</div>
                    <div class="kpi-stat-value">{{ number_format($analytics->proratedTarget(), 0) }} <small class="fs-6">QAR</small></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Staff Tracked</div>
                    <div class="kpi-stat-value">{{ $overall['staff_count'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Zero Upsell</div>
                    <div class="kpi-stat-value">{{ $overall['zero_upsell_count'] }}</div>
                </div>
            </div>
        </div>

        <div class="kpi-panel mb-3">
            <h6>Branch Performance Comparison</h6>
            <p class="text-muted small mb-3">Old Airport vs. Al Wakrah — upsell revenue and target achievement, side by side.</p>
            <div class="row g-3">
                @foreach ($branchComparison['branches'] as $b)
                    <div class="col-md-6">
                        <div class="kpi-stat-card h-100 {{ $branchComparison['leading_branch'] === $b['label'] ? 'border border-success' : '' }}">
                            <div class="kpi-stat-label">{{ $b['label'] }} {{ $branchComparison['leading_branch'] === $b['label'] ? '👑' : '' }}</div>
                            <div class="kpi-stat-value">{{ number_format($b['team_total'], 0) }} <small class="fs-6">QAR</small></div>
                            <div class="kpi-stat-sub mb-2">
                                {{ $b['team_pct'] }}% of {{ number_format($b['team_target'], 0) }} QAR target
                                &middot; {{ $b['staff_count'] }} {{ Str::plural('staff', $b['staff_count']) }}
                            </div>
                            <div class="kpi-progress-track">
                                <div class="kpi-progress-fill {{ $b['border'] }}" style="width:{{ min($b['team_pct'], 100) }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 small text-muted">
                @if (collect($branchComparison['branches'])->sum('team_total') == 0)
                    No upsells logged for either branch in this period.
                @elseif ($branchComparison['leading_branch'])
                    <strong>{{ $branchComparison['leading_branch'] }}</strong> is ahead by {{ number_format($branchComparison['gap'], 2) }} QAR in upsell revenue.
                @else
                    Both branches are performing evenly.
                @endif
            </div>
        </div>

        <div class="kpi-panel">
            <h6>Top Performing Staff</h6>
            <p class="text-muted small mb-3">Ranked by total upsell revenue across all branches for this period.</p>
            <div class="table-responsive">
                <table class="table kpi-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:48px;">#</th>
                            <th>Staff</th>
                            <th>Branch</th>
                            <th class="text-end">Upsell (QAR)</th>
                            <th style="min-width:140px;">Target</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topPerformers as $i => $p)
                            <tr>
                                <td class="fw-semibold">{{ $i + 1 }}</td>
                                <td class="fw-semibold">{{ $p['name'] }}</td>
                                <td>{{ $branches[$p['branch']] ?? ucfirst($p['branch']) }}</td>
                                <td class="text-end">{{ number_format($p['upsell'], 2) }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="kpi-progress-track" style="width:80px;">
                                            <div class="kpi-progress-fill {{ $p['border'] }}" style="width:{{ min($p['pct'], 100) }}%"></div>
                                        </div>
                                        <span class="small fw-semibold">{{ $p['pct'] }}%</span>
                                    </div>
                                </td>
                                <td><span class="kpi-badge kpi-badge-{{ $p['border'] }}">{{ $p['pct'] >= 85 ? 'On Target' : ($p['pct'] >= 50 ? 'Behind' : 'At Risk') }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No active staff found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.staff-sales-tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.staff-sales-tab-btn').forEach(b => {
                    b.classList.remove('btn-dark');
                    b.classList.add('btn-outline-secondary');
                });
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-dark');

                document.querySelectorAll('.staff-sales-tab-pane').forEach(p => p.classList.add('d-none'));
                document.getElementById('staff-sales-pane-' + this.dataset.tab).classList.remove('d-none');
            });
        });
    </script>
@endsection
