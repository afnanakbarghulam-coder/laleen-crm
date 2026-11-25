{{-- Slide-Over Drawer: MTD Service Production — Service Statement --}}
@if (!empty($mtdProduction) && !empty($productionBreakdownUrl))
<style>
    .mp-drawer-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(10, 6, 8, 0.72);
        backdrop-filter: blur(3px);
        z-index: 10040;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.22s ease;
    }

    .mp-drawer-backdrop.is-open {
        opacity: 1;
        pointer-events: auto;
    }

    .mp-drawer {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: min(760px, 100vw);
        background: #170f13;
        border-left: 1px solid rgba(229, 184, 168, 0.22);
        box-shadow: -24px 0 60px rgba(0, 0, 0, 0.68);
        z-index: 10050;
        display: flex;
        flex-direction: column;
        color: #f8efe6;
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
        transform: translateX(100%);
        transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
    }

    .mp-drawer.is-open {
        transform: translateX(0);
    }

    .mp-drawer-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 22px 16px;
        background: #1b1116;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        flex-shrink: 0;
    }

    .mp-drawer-title {
        font-size: 16.5px;
        font-weight: 700;
        color: #f8efe6;
        margin: 0;
        line-height: 1.3;
    }

    .mp-drawer-close {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.10);
        background: rgba(255, 255, 255, 0.04);
        color: #cbb8b0;
        font-size: 20px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        transition: all 0.15s ease;
    }

    .mp-drawer-close:hover {
        background: rgba(229, 184, 168, 0.14);
        border-color: rgba(229, 184, 168, 0.38);
        color: #f8efe6;
    }

    .mp-drawer-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        padding: 14px 22px;
        background: #140c10;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        flex-shrink: 0;
    }

    @media (max-width: 640px) {
        .mp-drawer-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 12px 16px;
        }
        .mp-drawer-header,
        .mp-drawer-controls,
        .mp-drawer-body,
        .mp-drawer-footer {
            padding-left: 16px !important;
            padding-right: 16px !important;
        }
    }

    .mp-drawer-stat {
        padding: 10px 12px;
        border-radius: 12px;
        background: #1d1318;
        border: 1px solid rgba(255, 255, 255, 0.065);
    }

    .mp-drawer-stat-label {
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #9e8780;
        margin-bottom: 4px;
    }

    .mp-drawer-stat-val {
        font-size: 17px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: #f8efe6;
        line-height: 1.2;
    }

    .mp-drawer-stat-val--gold {
        color: #f3d9a4;
    }

    .mp-drawer-controls {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 12px 22px;
        background: #170f13;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        flex-shrink: 0;
    }

    .mp-drawer-controls-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .mp-drawer-subtabs {
        display: inline-flex;
        padding: 3px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        gap: 3px;
    }

    .mp-drawer-subtab {
        border: none;
        background: transparent;
        color: #b8a29a;
        font-size: 12px;
        font-weight: 600;
        padding: 5px 14px;
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .mp-drawer-subtab.is-active {
        background: linear-gradient(135deg, #d98f83, #c19a8c);
        color: #1c110f;
    }

    .mp-drawer-search {
        position: relative;
        flex: 1 1 200px;
        max-width: 260px;
    }

    .mp-drawer-search input {
        width: 100%;
        background: #22161b;
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 999px;
        padding: 6px 12px 6px 32px;
        font-size: 12px;
        color: #f8efe6;
        outline: none;
    }

    .mp-drawer-search input::placeholder {
        color: #8f7a73;
    }

    .mp-drawer-search input:focus {
        border-color: rgba(229, 184, 168, 0.45);
    }

    .mp-drawer-search-icon {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        color: #9e8780;
        pointer-events: none;
    }

    .mp-drawer-filters {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }

    .mp-drawer-filter-pill {
        border: 1px solid rgba(255, 255, 255, 0.09);
        background: rgba(255, 255, 255, 0.03);
        color: #b8a29a;
        font-size: 11.5px;
        font-weight: 600;
        padding: 4px 11px;
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.15s ease;
        font-variant-numeric: tabular-nums;
    }

    .mp-drawer-filter-pill:hover {
        color: #f8efe6;
        border-color: rgba(229, 184, 168, 0.30);
    }

    .mp-drawer-filter-pill.is-active {
        background: rgba(229, 184, 168, 0.16);
        border-color: rgba(229, 184, 168, 0.44);
        color: #f8efe6;
    }

    .mp-drawer-body {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 14px 22px 20px;
        background: #170f13;
    }

    .mp-stmt-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
    }

    .mp-stmt-table thead th {
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #9e8780;
        padding: 8px 10px 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        text-align: left;
        white-space: nowrap;
    }

    .mp-stmt-table tbody td {
        padding: 11px 10px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        vertical-align: middle;
        color: #f8efe6;
    }

    .mp-stmt-table tbody tr:last-child td {
        border-bottom: none;
    }

    .mp-stmt-table tbody tr:hover td {
        background: rgba(255, 255, 255, 0.02);
    }

    .mp-stmt-type {
        display: inline-flex;
        align-items: center;
        padding: 2px 9px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 600;
        white-space: nowrap;
    }

    .mp-stmt-type--standalone {
        background: rgba(229, 184, 168, 0.12);
        color: #e5b8a8;
        border: 1px solid rgba(229, 184, 168, 0.28);
    }

    .mp-stmt-type--combo {
        background: rgba(212, 163, 115, 0.14);
        color: #f3d9a4;
        border: 1px solid rgba(212, 163, 115, 0.34);
    }

    .mp-stmt-type--freebie {
        background: rgba(52, 211, 153, 0.11);
        color: #6ee7b7;
        border: 1px solid rgba(52, 211, 153, 0.28);
    }

    .mp-stmt-subline {
        font-size: 11px;
        color: #9e8780;
        margin-top: 2px;
        line-height: 1.3;
    }

    .mp-stmt-price {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        color: #f8efe6;
    }

    .mp-stmt-price--zero {
        color: #9e8780;
        font-weight: 600;
    }

    .mp-stmt-price-note {
        font-size: 10px;
        font-weight: 500;
        color: #9e8780;
        margin-top: 1px;
    }

    .mp-drawer-empty {
        text-align: center;
        padding: 46px 20px;
        color: #9e8780;
    }

    .mp-drawer-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 22px;
        background: #1b1116;
        border-top: 1px solid rgba(229, 184, 168, 0.18);
        flex-shrink: 0;
    }

    .mp-drawer-footer-count {
        font-size: 12.5px;
        color: #b8a29a;
        font-variant-numeric: tabular-nums;
    }

    .mp-drawer-footer-total {
        font-size: 17px;
        font-weight: 800;
        color: #f3d9a4;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.01em;
    }
</style>

<div id="mtdProductionDrawerBackdrop" class="mp-drawer-backdrop" aria-hidden="true"></div>

<aside id="mtdProductionDrawer"
       class="mp-drawer"
       role="dialog"
       aria-modal="true"
       aria-labelledby="mtdDrawerTitle"
       aria-hidden="true"
       data-breakdown-url="{{ $productionBreakdownUrl }}"
       data-default-staff="{{ $profile['name'] }}"
       data-default-month="{{ $mtdProduction['month_label'] }}">
    {{-- Sticky Header --}}
    <div class="mp-drawer-header">
        <div class="min-w-0">
            <h4 class="mp-drawer-title" id="mtdDrawerTitle">
                {{ $profile['name'] }} &mdash; Service Production
            </h4>
            <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                <span class="mp-pill mp-pill-role mp-num" id="mtdDrawerMonthPill">
                    {{ $mtdProduction['month_label'] }} &middot; Asia/Qatar
                </span>
                <span class="small" style="color:#9e8780;">Completed &amp; paid service statement</span>
            </div>
        </div>
        <button type="button" class="mp-drawer-close" id="mtdDrawerCloseBtn" aria-label="Close breakdown drawer">
            &times;
        </button>
    </div>

    {{-- Compact 4-Metric Summary Strip --}}
    <div class="mp-drawer-summary">
        <div class="mp-drawer-stat">
            <div class="mp-drawer-stat-label">Total Services</div>
            <div class="mp-drawer-stat-val" id="mtdSumTotal">{{ number_format((int) $mtdProduction['total_services']) }}</div>
        </div>
        <div class="mp-drawer-stat">
            <div class="mp-drawer-stat-label">Standalone</div>
            <div class="mp-drawer-stat-val" id="mtdSumStandalone">{{ number_format((int) ($mtdProduction['standalone_paid_count'] ?? $mtdProduction['standalone_count'])) }}</div>
        </div>
        <div class="mp-drawer-stat">
            <div class="mp-drawer-stat-label">Combo / Freebie</div>
            <div class="mp-drawer-stat-val" id="mtdSumComboFreebie">{{ number_format((int) ($mtdProduction['combo_and_freebie_count'] ?? ($mtdProduction['combo_count'] + $mtdProduction['freebie_count']))) }}</div>
        </div>
        <div class="mp-drawer-stat">
            <div class="mp-drawer-stat-label">Net Production QAR</div>
            <div class="mp-drawer-stat-val mp-drawer-stat-val--gold" id="mtdSumRevenue">{{ $mtdProduction['formatted_revenue_exact'] ?? number_format((float) $mtdProduction['total_revenue_qar'], 2) . ' QAR' }}</div>
        </div>
    </div>

    {{-- View Toggle & Filter Bar --}}
    <div class="mp-drawer-controls">
        <div class="mp-drawer-controls-top">
            <div class="mp-drawer-subtabs" role="tablist" aria-label="Breakdown view">
                <button type="button" class="mp-drawer-subtab is-active" data-view="itemized" role="tab" aria-selected="true">
                    Itemized Log
                </button>
                <button type="button" class="mp-drawer-subtab" data-view="by_service" role="tab" aria-selected="false">
                    By Service
                </button>
            </div>

            <div class="mp-drawer-search">
                <svg class="mp-drawer-search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="search" id="mtdDrawerSearch" placeholder="Search service name..." autocomplete="off" aria-label="Filter by service name">
            </div>
        </div>

        <div class="mp-drawer-filters" id="mtdDrawerFilterBar">
            <button type="button" class="mp-drawer-filter-pill is-active" data-type="all">All</button>
            <button type="button" class="mp-drawer-filter-pill" data-type="standalone">Standalone</button>
            <button type="button" class="mp-drawer-filter-pill" data-type="combo">Combo</button>
            <button type="button" class="mp-drawer-filter-pill" data-type="freebie">Freebie</button>
        </div>
    </div>

    {{-- Scrollable Statement Body --}}
    <div class="mp-drawer-body" id="mtdDrawerBody">
        <div class="mp-drawer-empty">
            <div class="small">Loading service statement...</div>
        </div>
    </div>

    {{-- Sticky Footer Total Bar --}}
    <div class="mp-drawer-footer">
        <div class="mp-drawer-footer-count" id="mtdDrawerFooterCount">
            Showing {{ (int) $mtdProduction['total_services'] }} {{ (int) $mtdProduction['total_services'] === 1 ? 'service' : 'services' }}
        </div>
        <div class="mp-drawer-footer-total" id="mtdDrawerFooterTotal">
            {{ $mtdProduction['formatted_revenue_exact'] ?? number_format((float) $mtdProduction['total_revenue_qar'], 2) . ' QAR' }}
        </div>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const drawer = document.getElementById('mtdProductionDrawer');
    const backdrop = document.getElementById('mtdProductionDrawerBackdrop');
    if (!drawer || !backdrop) return;

    // Hoist above layout wrappers / fixed navbar stacking contexts in Admin Preview & CRM layout
    if (backdrop.parentElement !== document.body) {
        document.body.appendChild(backdrop);
    }
    if (drawer.parentElement !== document.body) {
        document.body.appendChild(drawer);
    }

    const closeBtn = document.getElementById('mtdDrawerCloseBtn');
    const bodyEl = document.getElementById('mtdDrawerBody');
    const titleEl = document.getElementById('mtdDrawerTitle');
    const monthPillEl = document.getElementById('mtdDrawerMonthPill');
    const sumTotalEl = document.getElementById('mtdSumTotal');
    const sumStandaloneEl = document.getElementById('mtdSumStandalone');
    const sumComboFreebieEl = document.getElementById('mtdSumComboFreebie');
    const sumRevenueEl = document.getElementById('mtdSumRevenue');
    const filterBarEl = document.getElementById('mtdDrawerFilterBar');
    const searchInput = document.getElementById('mtdDrawerSearch');
    const footerCountEl = document.getElementById('mtdDrawerFooterCount');
    const footerTotalEl = document.getElementById('mtdDrawerFooterTotal');
    const subtabBtns = Array.from(drawer.querySelectorAll('.mp-drawer-subtab'));
    const filterBtns = Array.from(drawer.querySelectorAll('.mp-drawer-filter-pill'));

    const state = {
        loaded: false,
        loading: false,
        data: null,
        view: 'itemized',
        typeFilter: 'all',
        search: '',
    };

    function escHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatExactQarFromCents(cents) {
        const val = (Number(cents || 0) / 100);
        return val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' QAR';
    }

    function toCents(amount) {
        return Math.round(Number(amount || 0) * 100);
    }

    function openDrawer() {
        drawer.classList.add('is-open');
        backdrop.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        backdrop.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        if (!state.loaded && !state.loading) {
            fetchBreakdown();
        } else if (state.loaded) {
            render();
        }
    }

    function closeDrawer() {
        drawer.classList.remove('is-open');
        backdrop.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function fetchBreakdown() {
        const url = drawer.dataset.breakdownUrl;
        if (!url) return;

        state.loading = true;
        bodyEl.innerHTML = '<div class="mp-drawer-empty"><div class="spinner-border spinner-border-sm mb-2" style="color:#e5b8a8;" role="status"></div><div class="small">Loading completed service statement...</div></div>';

        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('Request failed with status ' + res.status);
                }
                return res.json();
            })
            .then(function (payload) {
                state.data = payload;
                state.loaded = true;
                state.loading = false;
                hydrateHeaderAndSummary(payload);
                render();
            })
            .catch(function () {
                state.loading = false;
                bodyEl.innerHTML = '<div class="mp-drawer-empty">' +
                    '<div class="fw-semibold mb-1" style="color:#fda4af;">Unable to load service production statement</div>' +
                    '<div class="small mb-3">Please check your connection and try again.</div>' +
                    '<button type="button" class="mp-btn-ghost py-1 px-3" id="mtdDrawerRetryBtn">Retry</button>' +
                    '</div>';
                const retryBtn = document.getElementById('mtdDrawerRetryBtn');
                if (retryBtn) {
                    retryBtn.addEventListener('click', fetchBreakdown);
                }
            });
    }

    function hydrateHeaderAndSummary(payload) {
        const summary = payload.summary || {};
        if (titleEl && payload.staff_name) {
            titleEl.textContent = payload.staff_name + ' — Service Production';
        }
        if (monthPillEl && payload.month_label) {
            monthPillEl.textContent = payload.month_label + ' · ' + (payload.timezone || 'Asia/Qatar');
        }
        if (sumTotalEl) {
            sumTotalEl.textContent = Number(summary.total_services || 0).toLocaleString('en-US');
        }
        if (sumStandaloneEl) {
            const standalonePaid = summary.standalone_paid_count !== undefined
                ? summary.standalone_paid_count
                : (summary.standalone_count || 0);
            sumStandaloneEl.textContent = Number(standalonePaid).toLocaleString('en-US');
        }
        if (sumComboFreebieEl) {
            const comboFreebie = summary.combo_and_freebie_count !== undefined
                ? summary.combo_and_freebie_count
                : (Number(summary.combo_count || 0) + Number(summary.freebie_count || 0));
            sumComboFreebieEl.textContent = Number(comboFreebie).toLocaleString('en-US');
        }
        if (sumRevenueEl) {
            sumRevenueEl.textContent = summary.formatted_revenue_exact || formatExactQarFromCents(toCents(summary.total_revenue_qar || 0));
        }

        // Update counts on filter pills
        const items = Array.isArray(payload.items) ? payload.items : [];
        const counts = { all: items.length, standalone: 0, combo: 0, freebie: 0 };
        items.forEach(function (it) {
            if (it.type === 'combo') counts.combo++;
            else if (it.type === 'freebie') counts.freebie++;
            else counts.standalone++;
        });
        const labels = { all: 'All', standalone: 'Standalone', combo: 'Combo', freebie: 'Freebie' };
        filterBtns.forEach(function (btn) {
            const t = btn.dataset.type || 'all';
            btn.textContent = labels[t] + ' (' + (counts[t] || 0) + ')';
        });
    }

    function render() {
        if (!state.data) return;

        const monthLabel = state.data.month_label || drawer.dataset.defaultMonth || 'this month';
        const allItems = Array.isArray(state.data.items) ? state.data.items : [];
        const allByService = Array.isArray(state.data.by_service) ? state.data.by_service : [];
        const showClientFirstName = Boolean(state.data.show_client_first_name);
        const query = state.search.trim().toLowerCase();

        if (filterBarEl) {
            filterBarEl.style.display = state.view === 'itemized' ? 'flex' : 'none';
        }

        // Global empty state (0 completed services in month)
        if (allItems.length === 0) {
            bodyEl.innerHTML = '<div class="mp-drawer-empty">' +
                '<div class="fw-semibold mb-1" style="color:#cbb8b0; font-size:14px;">No completed &amp; paid services recorded for ' + escHtml(monthLabel) + ' yet.</div>' +
                '<div class="small" style="color:#8f7a73;">Completed appointments assigned to you automatically appear here with their net credited QAR value.</div>' +
                '</div>';
            footerCountEl.textContent = 'Showing 0 services';
            footerTotalEl.textContent = '0.00 QAR';
            return;
        }

        if (state.view === 'by_service') {
            const filteredGroups = allByService.filter(function (g) {
                if (!query) return true;
                return String(g.service_name || '').toLowerCase().includes(query);
            });

            if (filteredGroups.length === 0) {
                renderFilteredEmpty();
                return;
            }

            let totalServicesCount = 0;
            let totalCents = 0;
            const rowsHtml = filteredGroups.map(function (g) {
                const cnt = Number(g.count || 0);
                const rowCents = toCents(g.total_qar);
                totalServicesCount += cnt;
                totalCents += rowCents;

                return '<tr>' +
                    '<td><div class="fw-semibold">' + escHtml(g.service_name) + '</div></td>' +
                    '<td class="text-end mp-num" style="color:#cbb8b0;">' + cnt.toLocaleString('en-US') + '</td>' +
                    '<td class="text-end mp-num" style="color:#b8a29a;">' + escHtml(g.formatted_avg_price) + '</td>' +
                    '<td class="text-end"><span class="mp-stmt-price" style="color:#f3d9a4;">' + escHtml(g.formatted_total) + '</span></td>' +
                    '</tr>';
            }).join('');

            bodyEl.innerHTML = '<div class="table-responsive">' +
                '<table class="mp-stmt-table">' +
                '<thead><tr>' +
                '<th>Service Name</th>' +
                '<th class="text-end">Count</th>' +
                '<th class="text-end">Avg Price (QAR)</th>' +
                '<th class="text-end">Total Credited (QAR)</th>' +
                '</tr></thead>' +
                '<tbody>' + rowsHtml + '</tbody>' +
                '</table>' +
                '</div>';

            footerCountEl.textContent = 'Showing ' + totalServicesCount + ' ' + (totalServicesCount === 1 ? 'service' : 'services') +
                ' across ' + filteredGroups.length + ' ' + (filteredGroups.length === 1 ? 'type' : 'types');
            footerTotalEl.textContent = formatExactQarFromCents(totalCents);
            return;
        }

        // Default view: Itemized Log
        const filteredItems = allItems.filter(function (it) {
            if (state.typeFilter !== 'all' && it.type !== state.typeFilter) {
                return false;
            }
            if (query) {
                const hay = (String(it.service_name || '') + ' ' + String(it.subline || '') + ' ' + String(it.booking_ref || '')).toLowerCase();
                if (!hay.includes(query)) return false;
            }
            return true;
        });

        if (filteredItems.length === 0) {
            renderFilteredEmpty();
            return;
        }

        let totalCents = 0;
        const rowsHtml = filteredItems.map(function (it) {
            const cents = toCents(it.credited_qar);
            totalCents += cents;

            const typeCls = it.type === 'combo'
                ? 'mp-stmt-type--combo'
                : (it.type === 'freebie' ? 'mp-stmt-type--freebie' : 'mp-stmt-type--standalone');

            const refCell = (showClientFirstName && it.client_first_name)
                ? '<div class="mp-num fw-semibold" style="color:#cbb8b0;">' + escHtml(it.booking_ref) + '</div><div class="mp-stmt-subline">' + escHtml(it.client_first_name) + '</div>'
                : '<span class="mp-num fw-semibold" style="color:#cbb8b0;">' + escHtml(it.booking_ref) + '</span>';

            const sublineHtml = it.subline
                ? '<div class="mp-stmt-subline">' + escHtml(it.subline) + '</div>'
                : '';

            const priceCls = cents === 0 ? 'mp-stmt-price mp-stmt-price--zero' : 'mp-stmt-price';
            const priceNoteHtml = it.price_note
                ? '<div class="mp-stmt-price-note">' + escHtml(it.price_note) + '</div>'
                : '';

            return '<tr>' +
                '<td class="text-nowrap mp-num" style="color:#cbb8b0; font-size:12px;">' + escHtml(it.date_label) + '</td>' +
                '<td><div class="fw-semibold">' + escHtml(it.service_name) + '</div>' + sublineHtml + '</td>' +
                '<td><span class="mp-stmt-type ' + typeCls + '">' + escHtml(it.type_label) + '</span></td>' +
                '<td>' + refCell + '</td>' +
                '<td class="text-end"><div class="' + priceCls + '">' + escHtml(it.formatted_price) + '</div>' + priceNoteHtml + '</td>' +
                '</tr>';
        }).join('');

        const refHeader = showClientFirstName ? 'Booking Ref / Client' : 'Booking Ref';

        bodyEl.innerHTML = '<div class="table-responsive">' +
            '<table class="mp-stmt-table">' +
            '<thead><tr>' +
            '<th>Date &amp; Time</th>' +
            '<th>Service</th>' +
            '<th>Type</th>' +
            '<th>' + refHeader + '</th>' +
            '<th class="text-end">Price (QAR)</th>' +
            '</tr></thead>' +
            '<tbody>' + rowsHtml + '</tbody>' +
            '</table>' +
            '</div>';

        const shown = filteredItems.length;
        const suffix = shown < allItems.length ? (' of ' + allItems.length) : '';
        footerCountEl.textContent = 'Showing ' + shown + ' ' + (shown === 1 ? 'service' : 'services') + suffix;
        footerTotalEl.textContent = formatExactQarFromCents(totalCents);
    }

    function renderFilteredEmpty() {
        bodyEl.innerHTML = '<div class="mp-drawer-empty">' +
            '<div class="fw-semibold mb-1" style="color:#cbb8b0;">No services match your current filter.</div>' +
            '<button type="button" class="mp-btn-ghost py-1 px-3 mt-2" id="mtdDrawerResetBtn">Reset Filters</button>' +
            '</div>';
        footerCountEl.textContent = 'Showing 0 services';
        footerTotalEl.textContent = '0.00 QAR';

        const resetBtn = document.getElementById('mtdDrawerResetBtn');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                state.typeFilter = 'all';
                state.search = '';
                if (searchInput) searchInput.value = '';
                filterBtns.forEach(function (b) { b.classList.toggle('is-active', b.dataset.type === 'all'); });
                render();
            });
        }
    }

    // Triggers (View Breakdown button + clickable MTD cards)
    document.querySelectorAll('.js-open-production-breakdown').forEach(function (el) {
        el.addEventListener('click', openDrawer);
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openDrawer();
            }
        });
    });

    closeBtn?.addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
            closeDrawer();
        }
    });

    subtabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.view = btn.dataset.view || 'itemized';
            subtabBtns.forEach(function (b) {
                const active = b === btn;
                b.classList.toggle('is-active', active);
                b.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            render();
        });
    });

    filterBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            state.typeFilter = btn.dataset.type || 'all';
            filterBtns.forEach(function (b) {
                b.classList.toggle('is-active', b === btn);
            });
            render();
        });
    });

    searchInput?.addEventListener('input', function () {
        state.search = searchInput.value || '';
        render();
    });
});
</script>
@endif
