<style>
    :root {
        --ec-border: rgba(217, 143, 131, 0.16);
        --ec-border-strong: rgba(217, 143, 131, 0.3);
        --ec-muted: #c9a39a;
        --ec-ink: #e79a91;
        --ec-primary: #d98f83;
        --ec-success: #8ea88a;
        --ec-danger: #a8524a;
        --ec-warning: #c9a66b;
        --ec-info: #8aa6ab;
        --ec-neutral: #9a9088;
        --ec-gold: #c9a66b;
    }

    .ec-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 18px;
    }

    .ec-header h4 {
        margin-bottom: 2px;
    }

    .ec-header p {
        color: var(--ec-muted);
        margin-bottom: 0;
        font-size: 13.5px;
    }

    .ec-tabs {
        display: inline-flex;
        background: rgba(217, 143, 131, 0.08);
        border-radius: 9px;
        padding: 3px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .ec-tab {
        border: none;
        background: transparent;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 7px;
        color: #c9a39a;
        text-decoration: none;
        white-space: nowrap;
    }

    .ec-tab.active {
        background: #241e1c;
        color: var(--ec-ink);
        box-shadow: 0 1px 3px rgba(16, 24, 40, .12);
    }

    .ec-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--ec-border);
        border-radius: 16px;
        padding: 18px 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
    }

    .ec-card h6 {
        color: var(--ec-muted);
        font-size: 12px;
        letter-spacing: .5px;
        text-transform: uppercase;
        margin-bottom: 8px;
    }

    .ec-card .ec-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--ec-ink);
    }

    .ec-card .ec-sub {
        font-size: 12.5px;
        color: var(--ec-muted);
        margin-top: 4px;
    }

    .ec-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11.5px;
        font-weight: 600;
    }

    .ec-badge-injection { background: rgba(142, 168, 138, 0.18); color: var(--ec-success); }
    .ec-badge-distribution { background: rgba(201, 166, 107, 0.18); color: var(--ec-gold); }
    .ec-badge-live { background: rgba(142, 168, 138, 0.18); color: var(--ec-success); }

    /* Smart velocity stock alerts */
    .ec-badge-reorder { background: rgba(168, 82, 74, 0.2); color: #e0938a; white-space: nowrap; }
    .ec-badge-healthy { background: rgba(142, 168, 138, 0.16); color: var(--ec-success); white-space: nowrap; }
    .ec-badge-stalled { background: rgba(154, 144, 136, 0.16); color: var(--ec-neutral); white-space: nowrap; }
    .ec-badge-bundle  { background: rgba(201, 166, 107, 0.18); color: var(--ec-gold); }

    .ec-velocity-rate {
        font-weight: 700;
        font-size: 13.5px;
        color: var(--ec-ink);
        white-space: nowrap;
    }

    /* ---- Dashboard of Truth: KPI cards ---- */
    .ec-kpi-card {
        border: 1px solid var(--ec-border);
        border-left: 3px solid var(--ec-border-strong);
        height: 100%;
        transition: border-color .15s ease;
    }

    .ec-kpi-card.ec-kpi-good { border-left-color: var(--ec-success); }
    .ec-kpi-card.ec-kpi-bad  { border-left-color: #c96a60; box-shadow: 0 0 0 1px rgba(201, 106, 96, 0.25); }

    .ec-kpi-value {
        font-size: 30px;
        font-weight: 800;
        line-height: 1.15;
        color: var(--ec-ink);
        margin: 6px 0 2px;
        letter-spacing: -0.01em;
    }

    .ec-kpi-flag {
        margin-top: 8px;
        font-size: 11.5px;
        font-weight: 600;
    }

    /* ---- Weekly Content Factory board ---- */
    .ec-content-board { min-width: 1250px; }

    .ec-day-column {
        background: rgba(255, 255, 255, 0.015);
        border: 1px solid var(--ec-border);
        border-radius: 10px;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .ec-day-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 10px 12px;
        border-bottom: 1px solid var(--ec-border);
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: .05em;
        color: var(--ec-ink);
    }

    .ec-day-body {
        padding: 10px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .ec-content-slot {
        background: rgba(217, 143, 131, 0.05);
        border: 1px solid var(--ec-border);
        border-radius: 9px;
        padding: 10px;
    }

    .ec-content-slot.is-live {
        background: rgba(142, 168, 138, 0.08);
        border-color: rgba(142, 168, 138, 0.35);
    }

    .ec-pillar-badge {
        display: inline-block;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .03em;
        color: var(--ec-ink);
        background: var(--ec-accent-soft, rgba(217, 143, 131, 0.14));
        border-radius: 20px;
        padding: 3px 9px;
    }

    .ec-slot-objective {
        margin-top: 8px;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--ec-body, #f2e6e2);
    }

    .ec-slot-script {
        margin-top: 4px;
        font-size: 11.5px;
        line-height: 1.45;
        color: var(--ec-muted);
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .ec-slot-controls {
        margin-top: 10px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .ec-status-scripted            { background: rgba(154, 144, 136, 0.18); color: var(--ec-neutral); }
    .ec-status-filmed              { background: rgba(201, 166, 107, 0.18); color: var(--ec-gold); }
    .ec-status-live-on-shopify-meta{ background: rgba(142, 168, 138, 0.18); color: var(--ec-success); }

    /* Bundles, refill queue & backbar conversion */
    .ec-bundle-card {
        background: rgba(217, 143, 131, 0.05);
        border: 1px solid var(--ec-border);
        border-radius: 10px;
        padding: 14px 16px;
        height: 100%;
    }

    .ec-bundle-name {
        font-weight: 700;
        color: var(--ec-ink);
        font-size: 14px;
    }

    .ec-bundle-items {
        list-style: none;
        padding: 0;
        margin: 8px 0 0 0;
        font-size: 12.5px;
        color: var(--ec-muted);
    }

    .ec-bundle-items li { padding: 2px 0; }

    .ec-wa-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(37, 211, 102, 0.14);
        border: 1px solid rgba(37, 211, 102, 0.4);
        color: #4ddb84;
        border-radius: 20px;
        padding: 5px 14px;
        font-size: 12.5px;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        transition: background .15s ease;
    }

    .ec-wa-btn:hover { background: rgba(37, 211, 102, 0.24); color: #6ce79c; }

    .ec-conversion-meter {
        height: 8px;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.06);
        overflow: hidden;
        margin-top: 10px;
    }

    .ec-conversion-fill {
        height: 100%;
        border-radius: 20px;
        background: linear-gradient(90deg, var(--ec-primary), var(--ec-success));
    }

    /* Explains why BOM/inventory purchases are absent from Operating Expenses. */
    .ec-accounting-note {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: rgba(217, 143, 131, 0.06);
        border: 1px solid var(--ec-border);
        border-left: 3px solid var(--ec-primary);
        border-radius: 10px;
        padding: 12px 16px;
        margin-bottom: 18px;
        font-size: 12.5px;
        line-height: 1.55;
        color: var(--ec-muted);
    }

    .ec-accounting-note strong { color: var(--ec-ink); }

    .ec-partner-share-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 0;
        border-bottom: 1px solid var(--ec-border);
    }

    .ec-partner-share-row:last-child { border-bottom: none; }

    .ec-partner-share-name {
        flex: 1;
        min-width: 0;
        font-weight: 600;
        color: var(--ec-ink);
        font-size: 13.5px;
    }

    .ec-partner-share-equity {
        flex-shrink: 0;
        font-size: 12px;
        color: var(--ec-muted);
        background: rgba(217, 143, 131, 0.1);
        border-radius: 20px;
        padding: 3px 12px;
    }

    .ec-partner-share-amount {
        flex-shrink: 0;
        min-width: 150px;
        text-align: right;
        font-weight: 700;
        font-size: 14.5px;
    }

    .ec-partner-share-total {
        display: flex;
        align-items: center;
        gap: 14px;
        padding-top: 14px;
        margin-top: 4px;
        border-top: 1px solid var(--ec-border-strong);
        font-size: 12.5px;
        color: var(--ec-muted);
    }

    .ec-form-control, .ec-form-select {
        background-color: #241e1c;
        border: 1px solid var(--ec-border-strong);
        color: var(--ec-ink);
    }

    .ec-form-control:focus, .ec-form-select:focus {
        background-color: #241e1c;
        border-color: var(--ec-primary);
        color: var(--ec-ink);
        box-shadow: 0 0 0 3px rgba(217, 143, 131, .15);
    }

    .ec-channel-filter {
        width: auto;
        background-color: #241e1c;
        border: 1px solid var(--ec-border-strong);
        color: var(--ec-ink);
        font-size: 12.5px;
        font-weight: 600;
        border-radius: 20px;
        padding: 5px 14px;
    }

    .ec-channel-filter:focus,
    .ec-channel-filter:hover {
        border-color: var(--ec-primary);
        color: var(--ec-ink);
        box-shadow: 0 0 0 3px rgba(217, 143, 131, .15);
    }

    .ec-channel-filter-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 17px;
        height: 17px;
        padding: 0 5px;
        margin-left: 6px;
        border-radius: 20px;
        background: var(--ec-primary);
        color: #241e1c;
        font-size: 10.5px;
        font-weight: 700;
    }

    .ec-channel-filter-menu {
        background-color: #241e1c;
        border: 1px solid var(--ec-border-strong);
        border-radius: 12px;
        min-width: 260px;
        box-shadow: 0 8px 24px rgba(16, 24, 40, .25);
    }

    .ec-channel-filter-list {
        max-height: min(220px, 50vh);
        overflow-y: auto;
    }

    .ec-channel-filter-menu .form-check {
        padding-left: 1.6em;
        margin-bottom: 6px;
    }

    .ec-channel-filter-menu .form-check-label {
        color: #cbb8b0;
        font-size: 13px;
    }

    .ec-channel-filter-menu .form-check-input {
        background-color: #1a1513;
        border: 1px solid var(--ec-border-strong);
    }

    .ec-channel-filter-menu .form-check-input:checked {
        background-color: var(--ec-primary);
        border-color: var(--ec-primary);
    }

    .ec-channel-filter-group-label {
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: var(--ec-muted);
        opacity: .75;
        margin: 12px 0 6px;
    }

    .ec-channel-filter-group-label:first-child {
        margin-top: 0;
    }

    .ec-channel-filter-utility-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--ec-border);
    }

    .ec-channel-filter-utility-btn {
        background: none;
        border: none;
        padding: 0;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .02em;
        color: var(--ec-primary);
    }

    .ec-channel-filter-utility-btn:hover {
        color: var(--ec-ink);
        text-decoration: underline;
    }

    .ec-chart-card canvas {
        max-height: 260px;
    }

    .ec-chart-legend {
        display: flex;
        align-items: center;
        gap: 18px;
        font-size: 11.5px;
        color: var(--ec-muted);
        letter-spacing: .03em;
    }

    .ec-legend-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }

    .ec-chart-empty {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 220px;
        color: var(--ec-muted);
        font-size: 13px;
    }

    .ec-date-filter {
        width: auto;
        background-color: #241e1c;
        border: 1px solid var(--ec-border-strong);
        color: var(--ec-ink);
        font-size: 12.5px;
        color-scheme: dark;
    }

    .ec-date-filter:focus {
        background-color: #241e1c;
        border-color: var(--ec-primary);
        color: var(--ec-ink);
        box-shadow: 0 0 0 3px rgba(217, 143, 131, .15);
    }

    .modal .form-control:read-only {
        background-color: rgba(154, 144, 136, 0.08);
        color: var(--ec-muted);
        cursor: not-allowed;
    }

    .ec-table {
        color: #cbb8b0;
    }

    .ec-table th {
        color: var(--ec-muted);
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .5px;
        border-color: var(--ec-border);
    }

    .ec-table td {
        border-color: var(--ec-border);
        vertical-align: middle;
    }

    /* .ec-value.ec-positive/.ec-negative (not just .ec-positive) to outrank
       .ec-card .ec-value's color on specificity instead of source order. */
    .ec-value.ec-positive { color: var(--ec-success); }
    .ec-value.ec-negative { color: var(--ec-danger); }

    .ec-card-highlight {
        background: linear-gradient(160deg, rgba(217, 143, 131, 0.16), rgba(36, 30, 28, 0.6));
        border-color: var(--ec-border-strong);
        box-shadow: 0 0 0 1px rgba(217, 143, 131, 0.3), 0 12px 28px rgba(16, 24, 40, .18);
    }

    .ec-card-highlight h6 { color: var(--ec-primary); }
    .ec-card-highlight .ec-value { font-size: 30px; }

    .ec-breakdown-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 0;
        border-bottom: 1px solid var(--ec-border);
    }

    .ec-breakdown-row:last-child { border-bottom: none; }

    .ec-breakdown-label {
        flex: 0 0 160px;
        font-size: 13px;
        font-weight: 600;
        color: #cbb8b0;
    }

    .ec-breakdown-bar-track {
        flex: 1;
        height: 8px;
        border-radius: 20px;
        background: rgba(217, 143, 131, 0.1);
        overflow: hidden;
    }

    .ec-breakdown-bar-fill {
        height: 100%;
        border-radius: 20px;
        background: linear-gradient(90deg, var(--ec-primary), var(--ec-gold));
    }

    .ec-breakdown-amount {
        flex: 0 0 150px;
        text-align: right;
        font-size: 13.5px;
        font-weight: 700;
        color: var(--ec-ink);
        font-variant-numeric: tabular-nums;
    }

    .ec-breakdown-pct {
        flex: 0 0 56px;
        text-align: right;
        font-size: 11.5px;
        color: var(--ec-muted);
    }

    .ec-table-group-header {
        text-align: center;
        color: var(--ec-muted);
        font-size: 11px;
        letter-spacing: .8px;
        text-transform: uppercase;
        font-weight: 700;
    }

    .ec-col-divider {
        border-right: 1px solid var(--ec-border-strong) !important;
    }

    .ec-stock-emphasis {
        font-weight: 700;
        color: #f3e9e7;
        font-size: 14.5px;
    }

    .projected-remaining {
        font-size: 12.5px;
        color: var(--ec-muted);
        margin-top: 2px;
    }

    .projected-remaining.ec-negative {
        color: var(--ec-danger);
        font-weight: 700;
    }

    .ec-unit-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        background: rgba(217, 143, 131, 0.1);
        color: var(--ec-muted);
    }

    .ec-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    @media (max-width: 575.98px) {
        .ec-form-grid {
            grid-template-columns: 1fr;
        }
    }

    .ec-grand-total {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(217, 143, 131, 0.08);
        border: 1px solid var(--ec-border-strong);
        border-radius: 12px;
        padding: 14px 18px;
        margin-top: 4px;
    }

    .ec-grand-total .ec-grand-total-label {
        font-size: 12px;
        letter-spacing: .5px;
        text-transform: uppercase;
        color: var(--ec-muted);
        font-weight: 600;
    }

    .ec-grand-total .ec-grand-total-value {
        font-size: 28px;
        font-weight: 800;
        color: var(--ec-ink);
        font-variant-numeric: tabular-nums;
    }

    .ec-channel-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: .2px;
        white-space: nowrap;
    }

    .ec-bom-callout {
        background: rgba(217, 143, 131, 0.08);
        border: 1px solid var(--ec-border-strong);
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 600;
        color: var(--ec-ink);
    }

    .ec-channel-shopify { background: rgba(142, 168, 138, 0.18); color: var(--ec-success); }
    .ec-channel-organic { background: rgba(138, 166, 171, 0.18); color: var(--ec-info); }
    .ec-channel-salon { background: rgba(201, 166, 107, 0.2); color: var(--ec-gold); }
    .ec-channel-backbar { background: rgba(154, 144, 136, 0.2); color: var(--ec-neutral); }
    .ec-channel-damage { background: rgba(168, 82, 74, 0.16); color: var(--ec-danger); }
</style>
