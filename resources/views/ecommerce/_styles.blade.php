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
    .ec-badge-distribution { background: rgba(168, 82, 74, 0.18); color: var(--ec-danger); }

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

    .ec-positive { color: var(--ec-success); }
    .ec-negative { color: var(--ec-danger); }

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

    .ec-channel-shopify { background: rgba(142, 168, 138, 0.18); color: var(--ec-success); }
    .ec-channel-organic { background: rgba(138, 166, 171, 0.18); color: var(--ec-info); }
    .ec-channel-salon { background: rgba(201, 166, 107, 0.2); color: var(--ec-gold); }
    .ec-channel-backbar { background: rgba(154, 144, 136, 0.2); color: var(--ec-neutral); }
    .ec-channel-damage { background: rgba(168, 82, 74, 0.16); color: var(--ec-danger); }
</style>
