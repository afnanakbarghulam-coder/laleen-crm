{{-- Minimalistic MTD Service Production KPI Section (Salon Manager & Salon Staff Only) --}}
@if (!empty($mtdProduction))
<div class="luxe-scorecard-scope">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 px-1">
        <div class="d-flex align-items-center gap-2">
            <span class="mp-icon-badge"><i class="bx bx-sparkles"></i></span>
            <h3 class="mb-0" style="font-size:15px; font-weight:700; color:#f8efe6; font-family:'Poppins', sans-serif;">
                MTD Service Production
            </h3>
        </div>
        <span class="luxe-period-chip">{{ $mtdProduction['month_label'] }} &middot; Asia/Qatar</span>
    </div>

    <div class="luxe-kpi-grid luxe-kpi-grid--2">
        {{-- Card 1: Services Performed (MTD) --}}
        <article class="luxe-kpi-card">
            <div class="luxe-card-top">
                <div class="luxe-icon-orb orb-rose" aria-hidden="true">
                    <i class="bx bx-spa fs-5"></i>
                </div>
                <span class="luxe-owner-tag">{{ $mtdProduction['month_label'] }}</span>
            </div>
            <div class="luxe-kpi-label mt-2">Services Performed (MTD)</div>
            <div class="luxe-kpi-hero mp-num mt-1" style="font-family:'Poppins', sans-serif; font-variant-numeric:tabular-nums;">
                <span class="luxe-hero-main">{{ number_format((int) $mtdProduction['total_services']) }}</span>
                <span class="luxe-hero-unit">{{ (int) $mtdProduction['total_services'] === 1 ? 'service' : 'services' }}</span>
            </div>
            <div class="small mp-num mt-2" style="color:#a8938b;">
                {{ $mtdProduction['services_subtitle'] }}
            </div>
        </article>

        {{-- Card 2: Service Production (MTD) --}}
        <article class="luxe-kpi-card">
            <div class="luxe-card-top">
                <div class="luxe-icon-orb orb-gold" aria-hidden="true">
                    <i class="bx bx-wallet-alt fs-5"></i>
                </div>
                <span class="luxe-owner-tag">Net Paid</span>
            </div>
            <div class="luxe-kpi-label mt-2">Service Production (MTD)</div>
            <div class="luxe-kpi-hero mp-num mt-1" style="font-family:'Poppins', sans-serif; font-variant-numeric:tabular-nums;">
                <span class="luxe-hero-main" style="color:#f3d9a4;">{{ $mtdProduction['formatted_revenue'] }}</span>
            </div>
            <div class="small mp-num mt-2" style="color:#a8938b;">
                {{ $mtdProduction['revenue_subtitle'] }}
            </div>
        </article>
    </div>
</div>
@endif
