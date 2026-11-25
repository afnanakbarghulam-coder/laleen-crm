<style>
    /* =====================================================================
       SHARED LALEEN LUXE KPI SCORECARD DESIGN TOKENS (KPIs Hub + My Profile)
       ===================================================================== */
    .luxe-scorecard-scope {
        --lx-bg-deep: #140c0f;
        --lx-bg-card: #1d1216;
        --lx-bg-card-hover: #25171c;
        --lx-border: rgba(229, 184, 168, 0.14);
        --lx-border-strong: rgba(229, 184, 168, 0.30);
        --lx-cream: #f8efe6;
        --lx-muted: #b8a29a;
        --lx-rose-gold: #e5b8a8;
        --lx-champagne: #d4a373;
        --lx-emerald: #34d399;
        --lx-amber: #f59e0b;
        --lx-ruby: #fb7185;
    }

    .luxe-staff-detail-banner {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        padding: 16px 20px;
        margin-bottom: 18px;
        background: linear-gradient(180deg, rgba(33, 21, 26, 0.94) 0%, rgba(21, 14, 18, 0.98) 100%);
        border: 1px solid var(--lx-border-strong, rgba(229, 184, 168, 0.24));
        border-radius: 16px;
    }

    .luxe-avatar-initials {
        font-size: 13.5px;
        font-weight: 800;
        letter-spacing: 0.06em;
    }

    .luxe-staff-role {
        font-size: 12.5px;
        color: var(--lx-muted, #b8a29a);
    }

    .luxe-period-chip {
        font-size: 11.5px;
        font-weight: 600;
        color: var(--lx-muted, #b8a29a);
        background: rgba(255, 255, 255, 0.035);
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 5px 12px;
        border-radius: 999px;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .luxe-kpi-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 20px;
    }

    .luxe-kpi-grid--2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .luxe-kpi-grid--3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    @media (max-width: 1199px) {
        .luxe-kpi-grid,
        .luxe-kpi-grid--3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767px) {
        .luxe-kpi-grid,
        .luxe-kpi-grid--2,
        .luxe-kpi-grid--3 {
            grid-template-columns: 1fr;
        }
    }

    .luxe-kpi-card {
        background: linear-gradient(180deg, rgba(35, 22, 27, 0.92) 0%, rgba(23, 14, 18, 0.96) 100%);
        border: 1px solid var(--lx-border, rgba(229, 184, 168, 0.14));
        border-radius: 18px;
        padding: 20px 20px 18px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 248px;
        position: relative;
        overflow: hidden;
        transition: transform 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease;
    }

    .luxe-kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(229, 184, 168, 0.35), transparent);
    }

    .luxe-kpi-card:hover {
        transform: translateY(-2px);
        border-color: var(--lx-border-strong, rgba(229, 184, 168, 0.30));
        box-shadow: 0 14px 32px rgba(0, 0, 0, 0.45);
    }

    .luxe-card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 14px;
    }

    .luxe-icon-orb {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .orb-rose {
        background: radial-gradient(circle, rgba(229, 184, 168, 0.25) 0%, rgba(229, 184, 168, 0.06) 100%);
        border: 1px solid rgba(229, 184, 168, 0.40);
        color: var(--lx-rose-gold, #e5b8a8);
        box-shadow: 0 0 18px rgba(229, 184, 168, 0.20);
    }

    .orb-champagne {
        background: radial-gradient(circle, rgba(212, 163, 115, 0.25) 0%, rgba(212, 163, 115, 0.06) 100%);
        border: 1px solid rgba(212, 163, 115, 0.40);
        color: var(--lx-champagne, #d4a373);
        box-shadow: 0 0 18px rgba(212, 163, 115, 0.20);
    }

    .orb-emerald {
        background: radial-gradient(circle, rgba(52, 211, 153, 0.24) 0%, rgba(52, 211, 153, 0.05) 100%);
        border: 1px solid rgba(52, 211, 153, 0.40);
        color: var(--lx-emerald, #34d399);
        box-shadow: 0 0 18px rgba(52, 211, 153, 0.20);
    }

    .orb-amber {
        background: radial-gradient(circle, rgba(245, 158, 11, 0.24) 0%, rgba(245, 158, 11, 0.05) 100%);
        border: 1px solid rgba(245, 158, 11, 0.40);
        color: var(--lx-amber, #f59e0b);
        box-shadow: 0 0 18px rgba(245, 158, 11, 0.18);
    }

    .orb-berry,
    .orb-ruby {
        background: radial-gradient(circle, rgba(251, 113, 133, 0.24) 0%, rgba(251, 113, 133, 0.05) 100%);
        border: 1px solid rgba(251, 113, 133, 0.40);
        color: var(--lx-ruby, #fb7185);
        box-shadow: 0 0 18px rgba(251, 113, 133, 0.20);
    }

    .luxe-owner-tag {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.08em;
        padding: 3px 9px;
        border-radius: 999px;
        text-decoration: none;
        border: 1px solid var(--lx-border, rgba(229, 184, 168, 0.18));
        color: var(--lx-muted, #b8a29a);
        background: rgba(255, 255, 255, 0.03);
        transition: all 0.2s ease;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }

    .tag-pass {
        border-color: rgba(52, 211, 153, 0.40);
        color: var(--lx-emerald, #34d399);
        background: rgba(52, 211, 153, 0.10);
    }

    .tag-ruby {
        border-color: rgba(251, 113, 133, 0.40);
        color: #fda4af;
        background: rgba(251, 113, 133, 0.12);
    }

    .tag-champagne {
        border-color: rgba(212, 163, 115, 0.36);
        color: #f3d9a4;
        background: rgba(212, 163, 115, 0.10);
    }

    .tag-rose {
        border-color: rgba(229, 184, 168, 0.32);
        color: #e5b8a8;
        background: rgba(229, 184, 168, 0.08);
    }

    a.luxe-owner-tag:hover {
        color: var(--lx-cream, #f8efe6);
        border-color: var(--lx-rose-gold, #e5b8a8);
    }

    .luxe-card-label {
        font-size: 15px;
        font-weight: 600;
        color: var(--lx-cream, #f8efe6);
        line-height: 1.35;
        margin-bottom: 10px;
    }

    .luxe-card-subnote {
        font-size: 11px;
        font-weight: 400;
        color: var(--lx-muted, #b8a29a);
        line-height: 1.35;
        margin-top: -6px;
        margin-bottom: 10px;
        opacity: 0.85;
    }

    .luxe-card-hero {
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
        font-variant-numeric: tabular-nums;
        font-size: 36px;
        font-weight: 800;
        color: var(--lx-cream, #f8efe6);
        letter-spacing: -0.025em;
        line-height: 1.08;
        margin-bottom: 8px;
    }

    .luxe-unit {
        font-size: 18px;
        font-weight: 600;
        color: var(--lx-rose-gold, #e5b8a8);
    }

    .luxe-card-delta {
        font-size: 12px;
        color: var(--lx-muted, #b8a29a);
        margin-bottom: 14px;
        line-height: 1.45;
        white-space: normal;
        word-break: break-word;
    }

    .hl-emerald { color: var(--lx-emerald, #34d399); font-weight: 700; }
    .hl-rose { color: var(--lx-rose-gold, #e5b8a8); font-weight: 700; }
    .hl-champagne { color: var(--lx-champagne, #d4a373); font-weight: 700; }
    .hl-ruby { color: var(--lx-ruby, #fb7185); font-weight: 700; }

    .luxe-card-viz {
        margin-top: auto;
        padding-top: 6px;
    }

    .luxe-bar-track {
        width: 100%;
        height: 7px;
        background: rgba(255, 255, 255, 0.07);
        border-radius: 999px;
        overflow: hidden;
        margin-bottom: 6px;
    }

    .luxe-bar-fill {
        height: 100%;
        border-radius: 999px;
        transition: width 0.4s ease;
    }

    .fill-rose-gold {
        background: linear-gradient(90deg, #c98a78, #e5b8a8);
        box-shadow: 0 0 12px rgba(229, 184, 168, 0.45);
    }

    .fill-champagne {
        background: linear-gradient(90deg, #b58352, #d4a373);
        box-shadow: 0 0 12px rgba(212, 163, 115, 0.45);
    }

    .fill-emerald {
        background: linear-gradient(90deg, #10b981, #34d399);
        box-shadow: 0 0 12px rgba(52, 211, 153, 0.45);
    }

    .fill-ruby {
        background: linear-gradient(90deg, #e11d48, #fb7185);
        box-shadow: 0 0 12px rgba(251, 113, 133, 0.45);
    }

    .luxe-bar-scale {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 10.5px;
        color: var(--lx-muted, #b8a29a);
        font-weight: 500;
        font-variant-numeric: tabular-nums;
    }
</style>
