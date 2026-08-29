{{-- @extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

<h4 class="mb-4 fw-bold">Dashboard Overview</h4>

<div class="row g-4">

    <!-- Today's Appointments -->
    <div class="col-md-4 col-lg-3">
        <div class="card dashboard-card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1">Today's Appointments</p>
                    <h3 class="fw-bold mb-0">{{ $todayAppointments ?? 0 }}</h3>
                </div>
                <div class="icon-circle bg-primary-light text-primary">
                    <i class="bx bx-calendar fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Leads -->
    <div class="col-md-4 col-lg-3">
        <div class="card dashboard-card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1">Total Leads</p>
                    <h3 class="fw-bold mb-0">{{ $totalLeads ?? 0 }}</h3>
                </div>
                <div class="icon-circle bg-success-light text-success">
                    <i class="bx bx-user-plus fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Followups -->
    <div class="col-md-4 col-lg-3">
        <div class="card dashboard-card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1">Pending Followups</p>
                    <h3 class="fw-bold mb-0">{{ $pendingFollowups ?? 0 }}</h3>
                </div>
                <div class="icon-circle bg-warning-light text-warning">
                    <i class="bx bx-time-five fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Staff -->
    <div class="col-md-4 col-lg-3">
        <div class="card dashboard-card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1">Total Staff</p>
                    <h3 class="fw-bold mb-0">{{ $availableStaff ?? 0 }}</h3>
                </div>
                <div class="icon-circle bg-purple-light text-purple">
                    <i class="bx bx-group fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Revenue -->
    <div class="col-md-6 col-lg-4">
        <div class="card dashboard-card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1">Monthly Revenue</p>
                    <h3 class="fw-bold mb-0">{{ number_format($monthlyRevenue ?? 0, 2) }} QAR</h3>
                </div>
                <div class="icon-circle bg-info-light text-info">
                    <i class="bx bx-wallet fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Revenue -->
    <div class="col-md-6 col-lg-4">
        <div class="card dashboard-card border-0 shadow-sm h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <p class="text-muted mb-1">Total Revenue</p>
                    <h3 class="fw-bold mb-0">{{ number_format($totalRevenue ?? 0, 2) }} QAR</h3>
                </div>
                <div class="icon-circle bg-danger-light text-danger">
                    <i class="bx bx-bar-chart fs-4"></i>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection --}}




















@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')

<style>
/* ---------------- Ambient glass backdrop ---------------- */
.dash-glass {
    position: relative;
}

.dash-glass::before {
    content: '';
    position: absolute;
    inset: -40px 0 auto 0;
    height: 320px;
    pointer-events: none;
    z-index: 0;
    background:
        radial-gradient(60% 100% at 20% 0%, rgba(217, 143, 131, 0.10), transparent 70%),
        radial-gradient(50% 100% at 90% 10%, rgba(138, 166, 171, 0.08), transparent 70%);
}

.dash-glass > * {
    position: relative;
    z-index: 1;
}

/* ---------------- Glassmorphic HUD cards ---------------- */
.dashboard-card {
    position: relative;
    border-radius: 24px;
    transition: transform 0.35s cubic-bezier(.2, .8, .2, 1), box-shadow 0.35s cubic-bezier(.2, .8, .2, 1), border-color 0.35s ease;
    background: linear-gradient(155deg, rgba(48, 40, 37, 0.55), rgba(26, 20, 18, 0.72));
    border: 1px solid rgba(255, 255, 255, 0.10);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    box-shadow:
        0 24px 60px rgba(0, 0, 0, 0.40),
        0 8px 22px rgba(0, 0, 0, 0.28),
        inset 0 1px 0 rgba(255, 255, 255, 0.07);
    overflow: hidden;
    z-index: 2;
}

/* soft glowing highlight instead of a hard accent line */
.dashboard-card::before {
    content: '';
    position: absolute;
    top: -30%;
    left: 8%;
    width: 55%;
    height: 60%;
    border-radius: 50%;
    background: radial-gradient(closest-side, rgba(217, 143, 131, 0.22), transparent);
    filter: blur(20px);
    opacity: .55;
    pointer-events: none;
    z-index: 0;
    transition: opacity 0.35s ease;
}

.dashboard-card > * {
    position: relative;
    z-index: 1;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    border-color: rgba(255, 255, 255, 0.18);
    box-shadow:
        0 30px 70px rgba(0, 0, 0, 0.48),
        0 10px 26px rgba(0, 0, 0, 0.32),
        inset 0 1px 0 rgba(255, 255, 255, 0.09);
}

.dashboard-card:hover::before {
    opacity: .9;
}

/* Cool cyan-tinted glass channel for the KPI Performance section */
.hud-cyan .dashboard-card::before {
    background: radial-gradient(closest-side, rgba(138, 166, 171, 0.26), transparent);
}

.hud-cyan .live-dot {
    background: #8aa6ab;
    box-shadow: 0 0 8px 2px rgba(138, 166, 171, 0.55);
}

.hud-cyan .live-dot { animation-name: live-pulse-cyan; }

@keyframes live-pulse-cyan {
    0%, 100% { opacity: 1; box-shadow: 0 0 8px 2px rgba(138, 166, 171, 0.55); }
    50% { opacity: .55; box-shadow: 0 0 4px 1px rgba(138, 166, 171, 0.22); }
}

.hud-cyan .section-heading h5 {
    color: #a9c4c9;
}

.icon-circle {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.bg-primary-light { background: rgba(217, 143, 131, 0.14); }
.bg-success-light { background: rgba(142, 168, 138, 0.14); }
.bg-warning-light { background: rgba(201, 166, 107, 0.16); }
.bg-danger-light { background: rgba(168, 82, 74, 0.14); }
.bg-info-light { background: rgba(138, 166, 171, 0.14); }
.bg-purple-light { background: rgba(185, 142, 163, 0.14); }
.bg-orange-light { background: rgba(201, 123, 74, 0.16); }

.text-purple { color: #b98ea3; }
.text-orange { color: #c97b4a; }

.dashboard-title {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #b7a9a4;
    margin-bottom: 8px;
}

.dashboard-value {
    font-size: 25px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: #f7f1ee;
    text-shadow: 0 2px 18px rgba(217, 143, 131, 0.28);
}

.hud-cyan .dashboard-value {
    text-shadow: 0 2px 18px rgba(138, 166, 171, 0.28);
}

.text-loss {
    color: #e39b91 !important;
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
    margin-bottom: 16px;
}

.section-heading h5 {
    font-size: 15px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--luxe-ink);
    margin: 0;
}

.section-heading .section-link {
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.02em;
    color: #a3a3a3;
    text-decoration: none;
    transition: color 0.2s ease;
}

.section-heading .section-link:hover {
    color: var(--luxe-accent);
}

.section-heading h5 {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #d98f83;
    box-shadow: 0 0 8px 2px rgba(217, 143, 131, 0.55);
    animation: live-pulse 2s ease-in-out infinite;
    flex-shrink: 0;
}

@keyframes live-pulse {
    0%, 100% { opacity: 1; box-shadow: 0 0 8px 2px rgba(217, 143, 131, 0.55); }
    50% { opacity: .55; box-shadow: 0 0 4px 1px rgba(217, 143, 131, 0.22); }
}

.mini-ring {
    width: 96px;
    height: 96px;
    flex-shrink: 0;
    position: relative;
}

/* Cyber-pulse neon tracer — a hot glowing point orbiting the ring, dragging a fading light trail behind it */
.mini-ring::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: conic-gradient(
        from 0deg,
        rgba(217, 119, 6, 0) 0deg,
        rgba(255, 214, 150, .95) 6deg,
        rgba(217, 119, 6, .6) 15deg,
        rgba(217, 119, 6, 0) 36deg
    );
    -webkit-mask: radial-gradient(circle, transparent 55%, #000 60%, #000 80%, transparent 85%);
    mask: radial-gradient(circle, transparent 55%, #000 60%, #000 80%, transparent 85%);
    mix-blend-mode: screen;
    filter: drop-shadow(0 0 8px rgba(217, 119, 6, 0.8));
    pointer-events: none;
    animation: nr-tracer-orbit 3.2s linear infinite;
    z-index: 3;
}

@keyframes nr-tracer-orbit {
    to { transform: rotate(360deg); }
}

/* Breathing laser edge — the live progress arc surges with neon intensity */
.mini-ring .apexcharts-radialbar-slice-0 {
    animation: nr-laser-breathe 2.4s ease-in-out infinite;
}

@keyframes nr-laser-breathe {
    0%, 100% {
        filter: drop-shadow(0 0 3px rgba(217, 119, 6, .5)) drop-shadow(0 0 6px rgba(217, 119, 6, .3));
    }
    50% {
        filter: drop-shadow(0 0 10px rgba(217, 119, 6, .95)) drop-shadow(0 0 20px rgba(217, 119, 6, .55));
    }
}

/* Core energy node — a welding-tip orb glowing right at the live tip of the fill line */
.nr-energy-node {
    position: absolute;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: radial-gradient(circle, #fff2d9 0%, #f5a742 45%, rgba(217, 119, 6, 0) 75%);
    transform: translate(-50%, -50%);
    filter: drop-shadow(0 0 8px rgba(217, 119, 6, .85)) drop-shadow(0 0 16px rgba(217, 119, 6, .5));
    animation: nr-node-pulse 1.6s ease-in-out infinite;
    pointer-events: none;
    z-index: 4;
}

@keyframes nr-node-pulse {
    0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
    50% { transform: translate(-50%, -50%) scale(1.35); opacity: .75; }
}

.mini-sparkline {
    width: 100%;
    height: 46px;
    margin-top: 10px;
    opacity: 0;
    animation: sparkline-in 0.6s ease forwards;
    animation-delay: 0.4s;
}

@keyframes sparkline-in {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Flowing neon data-stream sweep along the sparkline trace */
.mini-sparkline svg path {
    stroke-width: 2.5px;
    filter: drop-shadow(0 0 6px rgba(217, 143, 131, .6));
    stroke-dasharray: 8 6;
    animation: nr-sparkline-flow 2.2s linear infinite;
}

@keyframes nr-sparkline-flow {
    to { stroke-dashoffset: -140; }
}

/* Soft glass status pills — fully rounded, gentle glow, breathing pulse */
.dash-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.02em;
    border: 1px solid transparent;
    backdrop-filter: blur(8px);
    transition: box-shadow 0.3s ease;
    animation: nr-badge-breathe 2.6s ease-in-out infinite;
}

@keyframes nr-badge-breathe {
    0%, 100% { opacity: 1; }
    50% { opacity: .72; }
}

.dash-pill-green {
    background: rgba(142, 168, 138, 0.12);
    border-color: rgba(142, 168, 138, 0.4);
    color: #aac9a5;
    box-shadow: 0 0 16px rgba(142, 168, 138, 0.3);
}

.dash-pill-amber {
    background: rgba(201, 166, 107, 0.12);
    border-color: rgba(201, 166, 107, 0.4);
    color: #ddc290;
    box-shadow: 0 0 16px rgba(201, 166, 107, 0.3);
}

.dash-pill-red {
    background: rgba(168, 82, 74, 0.12);
    border-color: rgba(168, 82, 74, 0.4);
    color: #e0998f;
    box-shadow: 0 0 16px rgba(168, 82, 74, 0.3);
}

.dash-pill-gray {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.12);
    color: #a3a3a3;
    box-shadow: none;
}
</style>

<div class="dash-glass">
<div class="row g-4">

    <!-- Welcome Card -->
    <div class="col-12">
        <div class="card dashboard-card p-4">
            <h4 class="fw-bold mb-1">Welcome to Laleen Ops 💇‍♀️</h4>
            <p class="text-muted mb-0">Manage appointments, leads, staff, performance and customer experience.</p>
        </div>
    </div>

</div>

@moduleView('finance')
    <div class="section-heading">
        <h5 class="fw-bold mb-0"><span class="live-dot"></span>Profit &amp; Loss — This Month</h5>
        <a href="{{ route('appointments.revenue.index') }}" class="section-link">{{ $dashFrom->format('d M') }} – {{ $dashTo->format('d M Y') }} · View Finance →</a>
    </div>
    <div class="row g-4">
        @foreach ($branchPnl as $row)
            <div class="col-md-6">
                <a href="{{ route('appointments.revenue.index', ['branch' => $row['key']]) }}" class="text-decoration-none">
                    <div class="card dashboard-card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="dashboard-title">{{ $row['label'] }} — Net Profit</div>
                                    <div class="dashboard-value {{ $row['profit'] < 0 ? 'text-loss' : '' }}">QAR {{ number_format($row['profit'], 2) }}</div>
                                    <div class="small text-muted mt-2">
                                        Sales <span class="fw-semibold text-dark">QAR {{ number_format($row['sales'], 2) }}</span>
                                        &nbsp;·&nbsp;
                                        Expenses <span class="fw-semibold text-dark">QAR {{ number_format($row['expenses'], 2) }}</span>
                                    </div>
                                    <span class="dash-pill dash-pill-{{ $row['color'] }} mt-2">{{ $row['margin_pct'] }}% margin</span>
                                </div>
                                <div class="mini-ring" id="pnlRing{{ $loop->index }}"></div>
                            </div>
                            <div class="mini-sparkline" id="pnlSparkline{{ $loop->index }}"></div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endmoduleView

@moduleView('kpis')
<div class="hud-cyan">
    <div class="section-heading">
        <h5 class="fw-bold mb-0"><span class="live-dot"></span>KPI Performance Highlights</h5>
        <a href="{{ route('kpi.hub') }}" class="section-link">View all KPIs →</a>
    </div>
    <div class="row g-4">
        <!-- Ads Conversion -->
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('kpi.ads.index') }}" class="text-decoration-none">
                <div class="card dashboard-card h-100 shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="dashboard-title">Ads Conversion</div>
                            <div class="dashboard-value">{{ $adsTotals['overall_conversion'] }}%</div>
                            <div class="small text-muted mt-1">Target 20%</div>
                            <span class="dash-pill dash-pill-{{ $adsColor }} mt-2">{{ $adsTotals['overall_met_target'] ? 'On Target' : 'Below Target' }}</span>
                        </div>
                        <div class="mini-ring" id="adsRing"></div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Agents Target — Morning shift recovery -->
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('kpi.agents.index') }}" class="text-decoration-none">
                <div class="card dashboard-card h-100 shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="dashboard-title">Morning Shift Recovery</div>
                            <div class="dashboard-value">{{ $agentShifts['morning']['pct'] }}%</div>
                            <div class="small text-muted mt-1">{{ $agentShifts['morning']['bookings'] }} / {{ $agentShifts['morning']['target'] }} bookings</div>
                            <span class="dash-pill dash-pill-{{ $agentShifts['morning']['border'] }} mt-2">{{ $agentShifts['morning']['pct'] >= 85 ? 'On Track' : ($agentShifts['morning']['pct'] >= 70 ? 'Recovering' : 'At Risk') }}</span>
                        </div>
                        <div class="mini-ring" id="agentMorningRing"></div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Agents Target — Evening shift recovery -->
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('kpi.agents.index') }}" class="text-decoration-none">
                <div class="card dashboard-card h-100 shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="dashboard-title">Evening Shift Recovery</div>
                            <div class="dashboard-value">{{ $agentShifts['evening']['pct'] }}%</div>
                            <div class="small text-muted mt-1">{{ $agentShifts['evening']['bookings'] }} / {{ $agentShifts['evening']['target'] }} bookings</div>
                            <span class="dash-pill dash-pill-{{ $agentShifts['evening']['border'] }} mt-2">{{ $agentShifts['evening']['pct'] >= 85 ? 'On Track' : ($agentShifts['evening']['pct'] >= 70 ? 'Recovering' : 'At Risk') }}</span>
                        </div>
                        <div class="mini-ring" id="agentEveningRing"></div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Staff Sales — per-branch upsells vs target -->
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('kpi.staff-sales.index') }}" class="text-decoration-none">
                <div class="card dashboard-card h-100 shadow-sm">
                    <div class="card-body">
                        <div class="dashboard-title mb-2">Staff Sales — Upsells vs Target</div>
                        @foreach ($staffSalesComparison['branches'] as $b)
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>{{ $b['label'] }}</span><span class="fw-semibold text-dark">{{ $b['team_pct'] }}%</span>
                            </div>
                            <div class="kpi-progress-track {{ !$loop->last ? 'mb-3' : '' }}">
                                <div class="kpi-progress-fill {{ $b['border'] }}" style="width: {{ min(100, $b['team_pct']) }}%"></div>
                            </div>
                        @endforeach
                        @if ($staffSalesComparison['leading_branch'])
                            <div class="small text-muted mt-2">Leading: <span class="fw-semibold text-dark">{{ $staffSalesComparison['leading_branch'] }}</span></div>
                        @endif
                    </div>
                </div>
            </a>
        </div>

        <!-- Chat Quality -->
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('kpi.chat-eval.index') }}" class="text-decoration-none">
                <div class="card dashboard-card h-100 shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="dashboard-title">Chat Quality</div>
                            @if ($chatQuality['avg'] !== null)
                                <div class="dashboard-value">{{ $chatQuality['avg'] }}%</div>
                                <span class="dash-pill dash-pill-{{ $chatQuality['color'] }} mt-2">{{ $chatQuality['grade'] }}</span>
                            @else
                                <div class="small text-muted mt-2">No evaluations logged this month.</div>
                            @endif
                        </div>
                        @if ($chatQuality['avg'] !== null)
                            <div class="mini-ring" id="chatRing"></div>
                        @endif
                    </div>
                </div>
            </a>
        </div>

        <!-- Content KPI compliance -->
        <div class="col-md-6 col-lg-4">
            <a href="{{ route('kpi.content.index') }}" class="text-decoration-none">
                <div class="card dashboard-card h-100 shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="dashboard-title">Content KPI Compliance</div>
                            @if ($contentMetrics['entry_count'] > 0)
                                <div class="dashboard-value">{{ $contentMetrics['overall'] }}%</div>
                                <span class="dash-pill dash-pill-{{ $contentColor }} mt-2">{{ $contentMetrics['grade'] }}</span>
                            @else
                                <div class="small text-muted mt-2">No content logged this month.</div>
                            @endif
                        </div>
                        @if ($contentMetrics['entry_count'] > 0)
                            <div class="mini-ring" id="contentRing"></div>
                        @endif
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
@endmoduleView
</div>

@endsection

@section('scripts')
<script>
    (function () {
        const RING_COLORS = { green: '#8ea88a', amber: '#c9a66b', red: '#a8524a', gray: '#8f8a86' };
        let stagger = 0;
        const STAGGER_STEP = 120;

        function renderRing(selector, value, colorKey) {
            const el = document.querySelector(selector);
            if (!el || typeof ApexCharts === 'undefined') return;
            const delay = stagger;
            stagger += STAGGER_STEP;

            new ApexCharts(el, {
                chart: {
                    type: 'radialBar',
                    height: 96,
                    sparkline: { enabled: true },
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 700,
                        animateGradually: { enabled: true, delay },
                        dynamicAnimation: { enabled: true, speed: 500 },
                    },
                },
                series: [Math.max(0, Math.min(100, value))],
                colors: [RING_COLORS[colorKey] || RING_COLORS.gray],
                plotOptions: {
                    radialBar: {
                        hollow: { size: '58%' },
                        track: { background: 'rgba(217, 143, 131, 0.1)' },
                        dataLabels: {
                            name: { show: false },
                            value: {
                                show: true,
                                fontSize: '15px',
                                fontWeight: 700,
                                fontFamily: "'Poppins', -apple-system, BlinkMacSystemFont, sans-serif",
                                color: '#e9dfda',
                                formatter: (v) => Number(v).toFixed(1) + '%',
                            },
                        },
                    },
                },
            }).render().then(() => {
                // The value arc's path is drawn gradually after render() resolves,
                // so wait for that draw-in animation to finish before sampling it.
                setTimeout(() => placeEnergyNode(el), delay + 900);
            });
        }

        // Core energy node: sample the rendered arc's actual end point and drop
        // a glowing orb exactly there, so it looks like a live welding tip.
        function placeEnergyNode(ringEl) {
            const arc = ringEl.querySelector('.apexcharts-radialbar-slice-0');
            if (!arc || !arc.getTotalLength()) return;
            const len = arc.getTotalLength();
            const tip = arc.getPointAtLength(len);
            const node = document.createElement('div');
            node.className = 'nr-energy-node';
            node.style.left = tip.x + 'px';
            node.style.top = tip.y + 'px';
            ringEl.appendChild(node);
        }

        function renderSparkline(selector, data, colorHex) {
            const el = document.querySelector(selector);
            if (!el || typeof ApexCharts === 'undefined' || !data.length) return;
            const delay = stagger;
            stagger += STAGGER_STEP;

            new ApexCharts(el, {
                chart: {
                    type: 'area',
                    height: 46,
                    sparkline: { enabled: true },
                    animations: {
                        enabled: true,
                        easing: 'easeinout',
                        speed: 800,
                        animateGradually: { enabled: true, delay },
                    },
                },
                series: [{ data }],
                colors: [colorHex],
                fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: 0 } },
                stroke: { curve: 'smooth', width: 2 },
                tooltip: { enabled: false },
            }).render();
        }

        @if ($branchPnl)
            @foreach ($branchPnl as $row)
                renderRing('#pnlRing{{ $loop->index }}', {{ $row['margin_pct'] }}, '{{ $row['color'] }}');
                renderSparkline('#pnlSparkline{{ $loop->index }}', @json($row['trend']), '#f59e0b');
            @endforeach
        @endif

        @if ($adsTotals)
            renderRing('#adsRing', {{ $adsTotals['overall_conversion'] }}, '{{ $adsColor }}');
        @endif

        @if ($agentShifts)
            renderRing('#agentMorningRing', {{ $agentShifts['morning']['pct'] }}, '{{ $agentShifts['morning']['border'] }}');
            renderRing('#agentEveningRing', {{ $agentShifts['evening']['pct'] }}, '{{ $agentShifts['evening']['border'] }}');
        @endif

        @if ($chatQuality && $chatQuality['avg'] !== null)
            renderRing('#chatRing', {{ $chatQuality['avg'] }}, '{{ $chatQuality['color'] }}');
        @endif

        @if ($contentMetrics && $contentMetrics['entry_count'] > 0)
            renderRing('#contentRing', {{ $contentMetrics['overall'] }}, '{{ $contentColor }}');
        @endif

        // Smooth count-up ticker: animate each metric from 0 to its final value on load.
        function animateCountUp(el) {
            const raw = el.textContent.trim();
            const match = raw.match(/^([^\d-]*)(-?[\d,]+(?:\.\d+)?)(.*)$/);
            if (!match) return;
            const [, prefix, numStr, suffix] = match;
            const target = parseFloat(numStr.replace(/,/g, ''));
            if (isNaN(target)) return;
            const decimals = (numStr.split('.')[1] || '').length;
            const duration = 1100;
            const start = performance.now();

            function frame(now) {
                const progress = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - progress, 3);
                const value = target * eased;
                el.textContent = prefix + value.toLocaleString('en-US', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals,
                }) + suffix;
                if (progress < 1) requestAnimationFrame(frame);
                else el.textContent = raw;
            }
            requestAnimationFrame(frame);
        }

        document.querySelectorAll('.dashboard-value').forEach(animateCountUp);
    })();
</script>
@endsection