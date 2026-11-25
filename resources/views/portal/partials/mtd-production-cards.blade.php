{{-- Minimalistic MTD Service Production KPI Section (Salon Manager & Salon Staff Only) --}}
@if (!empty($mtdProduction))
@php
    $canOpenBreakdown = !empty($productionBreakdownUrl);
@endphp
<style>
    .mp-mtd-header-right {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .mp-breakdown-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        color: #f3d9a4;
        background: rgba(212, 163, 115, 0.12);
        border: 1px solid rgba(212, 163, 115, 0.34);
        cursor: pointer;
        transition: all 0.18s ease;
        white-space: nowrap;
        font-family: 'Poppins', sans-serif;
    }

    .mp-breakdown-btn:hover,
    .mp-breakdown-btn:focus-visible {
        background: rgba(229, 184, 168, 0.18);
        border-color: rgba(229, 184, 168, 0.56);
        color: #f8efe6;
        outline: none;
    }

    .luxe-kpi-card.mp-mtd-card {
        min-height: 214px;
    }

    .luxe-kpi-card.mp-mtd-card--clickable {
        cursor: pointer;
    }

    .luxe-kpi-card.mp-mtd-card--clickable:hover,
    .luxe-kpi-card.mp-mtd-card--clickable:focus-visible {
        border-color: rgba(229, 184, 168, 0.42);
        box-shadow: 0 14px 32px rgba(0, 0, 0, 0.48), 0 0 0 1px rgba(229, 184, 168, 0.14);
        outline: none;
    }

    .mp-mtd-card-hint {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: auto;
        padding-top: 10px;
        border-top: 1px solid rgba(255, 255, 255, 0.055);
        font-size: 11.5px;
        font-weight: 500;
        color: #b8a29a;
        transition: color 0.16s ease;
    }

    .luxe-kpi-card.mp-mtd-card--clickable:hover .mp-mtd-card-hint,
    .luxe-kpi-card.mp-mtd-card--clickable:focus-visible .mp-mtd-card-hint {
        color: #e5b8a8;
    }
</style>

<div class="luxe-scorecard-scope">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 px-1">
        <div class="d-flex align-items-center gap-2">
            <span class="mp-icon-badge" aria-hidden="true">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9L12 3z"></path>
                    <path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15z"></path>
                </svg>
            </span>
            <h3 class="mb-0" style="font-size:15px; font-weight:700; color:#f8efe6; font-family:'Poppins', sans-serif;">
                MTD Service Production
            </h3>
        </div>
        <div class="mp-mtd-header-right">
            <span class="luxe-period-chip">{{ $mtdProduction['month_label'] }} &middot; Asia/Qatar</span>
            @if ($canOpenBreakdown)
                <button type="button"
                        class="mp-breakdown-btn js-open-production-breakdown"
                        aria-haspopup="dialog"
                        aria-controls="mtdProductionDrawer">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                    <span>View Breakdown</span>
                </button>
            @endif
        </div>
    </div>

    <div class="luxe-kpi-grid luxe-kpi-grid--2">
        {{-- Card 1: Services Performed (MTD) --}}
        <article class="luxe-kpi-card mp-mtd-card {{ $canOpenBreakdown ? 'mp-mtd-card--clickable js-open-production-breakdown' : '' }}"
                 @if ($canOpenBreakdown) role="button" tabindex="0" aria-label="View itemized MTD service production breakdown" @endif>
            <div>
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb orb-rose" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <span class="luxe-owner-tag tag-rose">{{ $mtdProduction['month_label'] }}</span>
                </div>

                <div class="luxe-card-label">Services Performed (MTD)</div>

                <div class="luxe-card-hero">
                    {{ number_format((int) $mtdProduction['total_services']) }}
                    <span class="luxe-unit">{{ (int) $mtdProduction['total_services'] === 1 ? 'service' : 'services' }}</span>
                </div>

                <div class="luxe-card-delta mb-2">
                    {{ $mtdProduction['services_subtitle'] }}
                </div>
            </div>

            @if ($canOpenBreakdown)
                <div class="mp-mtd-card-hint">
                    <span>View itemized list</span>
                    <span aria-hidden="true">&rarr;</span>
                </div>
            @endif
        </article>

        {{-- Card 2: Service Production (MTD) --}}
        <article class="luxe-kpi-card mp-mtd-card {{ $canOpenBreakdown ? 'mp-mtd-card--clickable js-open-production-breakdown' : '' }}"
                 @if ($canOpenBreakdown) role="button" tabindex="0" aria-label="View itemized MTD service production statement" @endif>
            <div>
                <div class="luxe-card-top">
                    <div class="luxe-icon-orb orb-champagne" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 12h2"></path>
                            <line x1="2" y1="10" x2="22" y2="10"></line>
                        </svg>
                    </div>
                    <span class="luxe-owner-tag tag-champagne">Net Paid</span>
                </div>

                <div class="luxe-card-label">Service Production (MTD)</div>

                <div class="luxe-card-hero" style="color:#f3d9a4;">
                    {{ $mtdProduction['formatted_revenue'] }}
                </div>

                <div class="luxe-card-delta mb-2">
                    {{ $mtdProduction['revenue_subtitle'] }}
                </div>
            </div>

            @if ($canOpenBreakdown)
                <div class="mp-mtd-card-hint">
                    <span>View itemized list</span>
                    <span aria-hidden="true">&rarr;</span>
                </div>
            @endif
        </article>
    </div>
</div>
@endif
