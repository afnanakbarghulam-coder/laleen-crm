@extends('layouts.app')
@section('title', 'Calendar - Staff Schedule')

<style>
    :root {
        --cal-border: rgba(217, 143, 131,0.16);
        --cal-border-strong: rgba(217, 143, 131,0.3);
        --cal-muted: #c9a39a;
        --cal-today: #d98f83;
        --cal-ink: #e79a91;
    }

    .cal-toolbar-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--cal-border);
        border-radius: 16px;
        padding: 14px 18px;
        margin-bottom: 18px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, .3), inset 0 1px 0 rgba(217, 143, 131, .05);
        /* backdrop-filter creates its own stacking context; without an explicit
           z-index here, .cal-scroll's own backdrop-filter context (later in the
           DOM) paints on top and clips the Filters dropdown behind the grid. */
        position: relative;
        z-index: 20;
    }

    .cal-branch-heading {
        font-size: 17px;
        margin: 0 0 10px;
    }

    .cal-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .cal-toolbar-left,
    .cal-toolbar-right {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .cal-toolbar-divider {
        width: 1px;
        align-self: stretch;
        min-height: 28px;
        background: var(--cal-border);
        margin: 0 2px;
    }

    .cal-view-toggle {
        display: inline-flex;
        background: rgba(217, 143, 131,0.08);
        border-radius: 9px;
        padding: 3px;
        height: 38px;
        box-sizing: border-box;
    }

    .cal-view-toggle button {
        border: none;
        background: transparent;
        padding: 0 14px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 7px;
        color: #c9a39a;
        transition: all .15s ease;
        white-space: nowrap;
    }

    .cal-view-toggle button.active {
        background: #241e1c;
        color: var(--cal-ink);
        box-shadow: 0 1px 3px rgba(16, 24, 40, .12);
    }

    .cal-nav {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(217, 143, 131,0.05);
        border-radius: 9px;
        padding: 3px;
    }

    .cal-nav button {
        width: 32px;
        height: 32px;
        border: none;
        background: transparent;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #cbb8b0;
        transition: all .15s ease;
    }

    .cal-nav button:hover {
        background: #241e1c;
        color: var(--cal-ink);
        box-shadow: 0 1px 3px rgba(16, 24, 40, .12);
    }

    .cal-icon-btn {
        width: 38px;
        height: 38px;
        border: 1px solid var(--cal-border-strong);
        background: #241e1c;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #cbb8b0;
        transition: all .15s ease;
    }

    .cal-icon-btn:hover {
        background: rgba(217, 143, 131,0.06);
        border-color: rgba(217, 143, 131,0.32);
    }

    .cal-today-btn {
        height: 38px;
        border: 1px solid var(--cal-border-strong);
        background: #241e1c;
        border-radius: 9px;
        padding: 0 16px;
        font-size: 13px;
        font-weight: 600;
        color: #e79a91;
        transition: all .15s ease;
    }

    .cal-today-btn:hover {
        background: rgba(217, 143, 131,0.06);
        border-color: rgba(217, 143, 131,0.32);
    }

    .cal-date-label {
        font-weight: 700;
        font-size: 16px;
        color: var(--cal-ink);
        min-width: 170px;
        letter-spacing: -.01em;
    }

    /* Compound selector (not just .cal-filter-panel) so this reliably
       outranks luxury-theme.css's global ".dropdown-menu" background rule -
       this page's own style block renders ahead of that stylesheet in the
       document (it sits outside the named content block in this
       extends-based view), so on equal specificity the later, global rule
       would otherwise win despite !important. */
    .dropdown-menu.cal-filter-panel {
        width: 290px;
        padding: 16px;
        /* Solid, opaque background - this panel sits over the staff header
           row and calendar grid, and the translucent .dropdown-menu
           background (--luxe-surface, 60% alpha + blur) let both bleed
           through it. */
        background: #1a1818 !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        border: 1px solid #3a3230 !important;
        border-radius: 14px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .75) !important;
        z-index: 1060 !important;
    }

    .cal-filter-title {
        font-size: 13px;
        font-weight: 700;
        color: var(--cal-ink);
        margin-bottom: 14px;
    }

    .cal-filter-field {
        margin-bottom: 14px;
    }

    .cal-filter-field:last-of-type {
        margin-bottom: 0;
    }

    .cal-filter-field label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #c9a39a;
        letter-spacing: .04em;
        margin-bottom: 6px;
    }

    .cal-filter-select {
        width: 100%;
        height: 38px;
        border: 1px solid var(--cal-border-strong);
        border-radius: 9px;
        padding: 0 12px;
        font-size: 13.5px;
        font-weight: 600;
        color: var(--cal-ink);
        background-color: #241e1c;
        transition: all .15s ease;
    }

    .cal-filter-select:hover {
        border-color: rgba(217, 143, 131,0.32);
    }

    .cal-filter-select:focus {
        outline: none;
        border-color: var(--cal-today);
        box-shadow: 0 0 0 3px rgba(217, 143, 131, .15);
    }

    .cal-filter-footer {
        display: flex;
        justify-content: flex-end;
        margin-top: 16px;
        padding-top: 12px;
        border-top: 1px solid var(--cal-border);
    }

    .cal-filter-clear {
        border: none;
        background: transparent;
        font-size: 12.5px;
        font-weight: 700;
        color: var(--cal-today);
        padding: 5px 8px;
        border-radius: 6px;
        transition: all .15s ease;
    }

    .cal-filter-clear:hover:not(:disabled) {
        background: rgba(217, 143, 131, .08);
    }

    .cal-filter-clear:disabled {
        color: #6b5f59;
        cursor: default;
    }

    .cal-filter-badge {
        background: var(--cal-today);
        color: #241a16;
        font-size: 10px;
        font-weight: 700;
        border-radius: 999px;
        padding: 1px 6px;
        margin-left: 4px;
    }

    .cal-zoom {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        height: 38px;
        box-sizing: border-box;
        border: 1px solid var(--cal-border-strong);
        border-radius: 9px;
        padding: 3px;
        background: #241e1c;
    }

    .cal-zoom button {
        width: 30px;
        height: 30px;
        border: none;
        background: transparent;
        border-radius: 6px;
        color: #cbb8b0;
        font-weight: 700;
        transition: all .15s ease;
    }

    .cal-zoom button:hover {
        background: rgba(217, 143, 131,0.08);
    }

    .cal-add-btn {
        height: 38px;
        display: inline-flex;
        align-items: center;
        border-radius: 9px;
        font-weight: 700;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .08);
    }

    .cal-legend {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--cal-border);
        flex-wrap: wrap;
    }

    .cal-legend > span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px 4px 8px;
        border-radius: 999px;
        background: rgba(217, 143, 131,0.05);
        font-size: 12px;
        font-weight: 600;
        color: #cbb8b0;
        white-space: nowrap;
    }

    .cal-legend .dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    @media (max-width: 640px) {
        .cal-toolbar-divider {
            display: none;
        }
    }

    /* ---------------- DAY / 3-DAY / WEEK GRID VIEW ---------------- */
    .cal-scroll {
        overflow-x: auto;
        border: 1px solid var(--cal-border);
        border-radius: 12px;
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
    }

    .cal-day-grid {
        display: flex;
        min-width: max-content;
        position: relative;
    }

    .cal-time-col {
        width: 68px;
        flex-shrink: 0;
        position: sticky;
        left: 0;
        background: #241e1c;
        z-index: 3;
        border-right: 1px solid var(--cal-border);
    }

    .cal-time-col .cal-staff-header {
        border-right: none;
    }

    .cal-time-cell {
        font-size: 11px;
        color: var(--cal-muted);
        text-align: right;
        padding-right: 8px;
        position: relative;
        top: -6px;
    }

    .cal-staff-col {
        width: 210px;
        flex-shrink: 0;
        border-right: 1px solid var(--cal-border);
        position: relative;
    }

    .cal-staff-header {
        height: 84px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        border-bottom: 1px solid var(--cal-border);
        position: sticky;
        top: 0;
        background: #241e1c;
        z-index: 2;
        padding: 6px;
    }

    .cal-staff-header img {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: cover;
    }

    .cal-staff-header .name {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--cal-ink);
        text-align: center;
        line-height: 1.1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    .cal-staff-header .badge-count {
        font-size: 10.5px;
        color: var(--cal-muted);
    }

    .cal-slots {
        position: relative;
    }

    .cal-slot-cell {
        border-bottom: 1px dashed var(--cal-border);
        cursor: pointer;
        transition: background .1s ease;
    }

    .cal-slot-cell:hover {
        background: rgba(217, 143, 131,0.08);
    }

    .cal-staff-col.is-off .cal-slot-cell {
        cursor: not-allowed;
        background: repeating-linear-gradient(135deg, rgba(217, 143, 131,0.05), rgba(217, 143, 131,0.05) 8px, rgba(217, 143, 131,0.06) 8px, rgba(217, 143, 131,0.06) 16px);
    }

    .cal-staff-col.is-off .cal-slot-cell:hover {
        background: repeating-linear-gradient(135deg, rgba(217, 143, 131,0.05), rgba(217, 143, 131,0.05) 8px, rgba(217, 143, 131,0.06) 8px, rgba(217, 143, 131,0.06) 16px);
    }

    .cal-off-tag {
        position: absolute;
        top: 84px;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 11.5px;
        font-weight: 600;
        color: #c9a39a;
        padding: 4px;
    }

    .cal-appt {
        position: absolute;
        left: 4px;
        right: 4px;
        border-radius: 7px;
        padding: 4px 7px;
        font-size: 11.5px;
        line-height: 1.25;
        overflow: hidden;
        cursor: pointer;
        border-left: 3px solid transparent;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .06);
        transition: box-shadow .12s ease, transform .12s ease;
        z-index: 1;
        /* Without this, grabbing the card by its text starts a native text
           selection instead of the HTML5 drag gesture, so dragstart never
           fires and the drop silently does nothing. */
        user-select: none;
        -webkit-user-drag: element;
    }

    .cal-appt:hover {
        box-shadow: 0 4px 10px rgba(16, 24, 40, .16);
        transform: translateY(-1px);
        z-index: 4;
    }

    .cal-appt .cust {
        font-weight: 700;
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cal-appt .svc {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        opacity: .9;
    }

    .cal-combo-badge {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        max-width: 100%;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: #f8efe6;
        background: rgba(212, 163, 115, 0.24);
        border: 1px solid rgba(229, 184, 168, 0.38);
        border-radius: 999px;
        padding: 1px 6px;
        margin: 1px 0 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    #apptDrawer .drawer-combo-card {
        background: rgba(255, 255, 255, 0.025);
        border: 1px solid rgba(229, 184, 168, 0.22);
        border-radius: 12px;
        padding: 12px 13px;
        margin-bottom: 10px;
        transition: border-color 0.15s ease, background 0.15s ease;
    }

    #apptDrawer .drawer-combo-card.is-clickable {
        cursor: pointer;
    }

    #apptDrawer .drawer-combo-card.is-clickable:hover {
        background: rgba(229, 184, 168, 0.06);
        border-color: rgba(229, 184, 168, 0.42);
    }

    #apptDrawer .drawer-combo-eyebrow {
        display: inline-block;
        font-size: 9.5px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #e5b8a8;
        background: rgba(229, 184, 168, 0.13);
        border-radius: 999px;
        padding: 2px 7px;
        flex-shrink: 0;
    }

    #apptDrawer .drawer-combo-title {
        font-size: 13.5px;
        font-weight: 700;
        color: #f8efe6;
        line-height: 1.3;
    }

    #apptDrawer .drawer-combo-price {
        font-size: 13px;
        font-weight: 700;
        color: #e5b8a8;
        white-space: nowrap;
        flex-shrink: 0;
    }

    #apptDrawer .drawer-combo-trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    #apptDrawer .drawer-combo-empty-hint {
        font-size: 12px;
        color: #d4a373;
        font-weight: 500;
        line-height: 1.35;
    }

    #apptDrawer .drawer-combo-picked-list {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px 8px;
        font-size: 12px;
        line-height: 1.45;
    }

    #apptDrawer .drawer-combo-picked-item {
        color: #f8efe6;
        font-weight: 500;
    }

    #apptDrawer .drawer-combo-picked-item .state-today {
        color: #6ee7b7;
        font-size: 11px;
        font-weight: 600;
    }

    #apptDrawer .drawer-combo-picked-item .state-pending {
        color: #d4a373;
        font-size: 11px;
        font-weight: 600;
    }

    #apptDrawer .drawer-combo-picked-sep {
        color: rgba(248, 239, 230, 0.28);
        font-size: 11px;
    }

    #apptDrawer .drawer-combo-edit-cta {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 11.5px;
        font-weight: 600;
        color: #e5b8a8;
        white-space: nowrap;
        flex-shrink: 0;
    }

    #apptDrawer .drawer-combo-chevron {
        font-size: 15px;
        line-height: 1;
        color: #e5b8a8;
    }

    /* Nested Combo Service Picker Sub-View (#drawerComboSubHeader / #drawerComboSubView / #drawerComboSubFooter) */
    #apptDrawer .drawer-combo-subheader {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    #apptDrawer .drawer-combo-subcounter {
        display: inline-flex;
        align-items: center;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
        color: #e5b8a8;
        background: rgba(229, 184, 168, 0.13);
        border-radius: 999px;
        padding: 3px 9px;
        white-space: nowrap;
        flex-shrink: 0;
    }

    #apptDrawer .drawer-combo-subcounter.is-full {
        color: #6ee7b7;
        background: rgba(110, 231, 183, 0.14);
    }

    #apptDrawer .drawer-combo-subview {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
    }

    #apptDrawer .drawer-combo-search-wrap {
        position: relative;
        padding: 12px 16px 8px;
    }

    #apptDrawer .drawer-combo-search-wrap i {
        position: absolute;
        left: 27px;
        top: 50%;
        transform: translateY(-32%);
        color: #9b8882;
        font-size: 15px;
        pointer-events: none;
    }

    #apptDrawer .drawer-combo-search-input {
        width: 100%;
        background: rgba(255, 255, 255, 0.035);
        border: 1px solid rgba(229, 184, 168, 0.2);
        border-radius: 9px;
        color: #f8efe6;
        font-size: 12.5px;
        padding: 8px 12px 8px 34px;
        outline: none;
        transition: border-color 0.15s ease;
    }

    #apptDrawer .drawer-combo-search-input::placeholder {
        color: #8f7c76;
    }

    #apptDrawer .drawer-combo-search-input:focus {
        border-color: rgba(229, 184, 168, 0.48);
    }

    #apptDrawer .drawer-combo-skip-bar {
        margin: 0 16px 8px;
        padding: 8px 12px;
        border-radius: 9px;
        border: 1px dashed rgba(212, 163, 115, 0.34);
        background: rgba(212, 163, 115, 0.07);
        color: #e8c7a1;
        font-size: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        cursor: pointer;
        text-align: left;
        transition: background 0.15s ease, border-color 0.15s ease;
    }

    #apptDrawer .drawer-combo-skip-bar:hover {
        background: rgba(212, 163, 115, 0.14);
        border-color: rgba(212, 163, 115, 0.55);
    }

    #apptDrawer .drawer-combo-cap-hint {
        margin: 0 16px 6px;
        font-size: 11px;
        color: #d4a373;
        font-weight: 500;
    }

    #apptDrawer .drawer-combo-sublist {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 2px 16px 14px;
    }

    #apptDrawer .drawer-combo-svc-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 2px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        transition: opacity 0.15s ease;
    }

    #apptDrawer .drawer-combo-svc-row:last-child {
        border-bottom: none;
    }

    #apptDrawer .drawer-combo-svc-row.is-dimmed {
        opacity: 0.42;
    }

    #apptDrawer .drawer-combo-svc-name {
        font-size: 13px;
        color: #f8efe6;
        font-weight: 600;
        line-height: 1.3;
    }

    #apptDrawer .drawer-combo-svc-dur {
        font-size: 11.5px;
        color: #9f8c86;
        margin-top: 1px;
    }

    #apptDrawer .drawer-combo-seg {
        display: inline-flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 999px;
        padding: 2px;
        gap: 2px;
        flex-shrink: 0;
    }

    #apptDrawer .drawer-combo-pill-btn {
        border: none;
        border-radius: 999px;
        background: transparent;
        color: #b8a29a;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        line-height: 1.25;
        transition: all 0.15s ease;
    }

    #apptDrawer .drawer-combo-pill-btn.is-today {
        background: rgba(110, 231, 183, 0.18);
        color: #6ee7b7;
    }

    #apptDrawer .drawer-combo-pill-btn.is-pending {
        background: rgba(212, 163, 115, 0.2);
        color: #e8c7a1;
    }

    #apptDrawer .drawer-combo-pill-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    #apptDrawer .drawer-combo-subfooter {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        background: #1b1416;
    }

    #apptDrawer .drawer-combo-apply-btn {
        border: 1px solid rgba(229, 184, 168, 0.45);
        border-radius: 9px;
        background: #e5b8a8;
        color: #1f1416;
        font-size: 12.5px;
        font-weight: 700;
        padding: 8px 15px;
        transition: filter 0.15s ease;
        white-space: nowrap;
    }

    #apptDrawer .drawer-combo-apply-btn:hover {
        filter: brightness(1.05);
    }

    #apptDrawer .drawer-combo-apply-btn:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .cal-appt.status-cancelled {
        opacity: .55;
        text-decoration: line-through;
    }

    .cal-block {
        position: absolute;
        left: 4px;
        right: 4px;
        border-radius: 7px;
        font-size: 11px;
        font-weight: 600;
        color: #c9a39a;
        background: repeating-linear-gradient(135deg, rgba(217, 143, 131,0.07), rgba(217, 143, 131,0.07) 6px, rgba(217, 143, 131,0.14) 6px, rgba(217, 143, 131,0.14) 12px);
        border: 1px dashed rgba(217, 143, 131,0.28);
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 2px 6px;
        cursor: pointer;
        z-index: 1;
    }

    .cal-now-line {
        position: absolute;
        left: 0;
        right: 0;
        height: 0;
        border-top: 2px solid #c07c73;
        z-index: 5;
        pointer-events: none;
    }

    .cal-now-line::before {
        content: '';
        position: absolute;
        left: -4px;
        top: -4px;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #c07c73;
    }

    /* ---------------- WEEK / 3-DAY GRID (staff rows x day cols) ---------------- */
    .cal-week-grid {
        display: grid;
        min-width: max-content;
    }

    .cal-week-grid .cal-week-corner,
    .cal-week-grid .cal-week-daycol {
        position: sticky;
        top: 0;
        background: #241e1c;
        z-index: 2;
        border-bottom: 1px solid var(--cal-border);
        padding: 10px 8px;
        text-align: center;
    }

    .cal-week-corner {
        left: 0;
        z-index: 3;
        border-right: 1px solid var(--cal-border);
    }

    .cal-week-daycol .dow {
        font-size: 11px;
        color: var(--cal-muted);
        font-weight: 600;
        text-transform: uppercase;
    }

    .cal-week-daycol .dnum {
        font-size: 15px;
        font-weight: 700;
        color: var(--cal-ink);
    }

    .cal-week-daycol.is-today .dnum {
        color: #241a16;
        background: var(--cal-today);
        border-radius: 50%;
        width: 26px;
        height: 26px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .cal-week-staffcell {
        position: sticky;
        left: 0;
        background: #241e1c;
        z-index: 1;
        border-right: 1px solid var(--cal-border);
        border-bottom: 1px solid var(--cal-border);
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px;
    }

    .cal-week-staffcell img {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .cal-week-staffcell .name {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--cal-ink);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cal-week-daycell {
        border-right: 1px solid var(--cal-border);
        border-bottom: 1px solid var(--cal-border);
        min-height: 92px;
        padding: 6px;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .cal-week-daycell:hover {
        background: rgba(217, 143, 131,0.06);
    }

    .cal-week-daycell.is-off {
        cursor: not-allowed;
        background: repeating-linear-gradient(135deg, rgba(217, 143, 131,0.05), rgba(217, 143, 131,0.05) 8px, rgba(217, 143, 131,0.06) 8px, rgba(217, 143, 131,0.06) 16px);
    }

    .cal-week-daycell.is-off:hover {
        background: repeating-linear-gradient(135deg, rgba(217, 143, 131,0.05), rgba(217, 143, 131,0.05) 8px, rgba(217, 143, 131,0.06) 8px, rgba(217, 143, 131,0.06) 16px);
    }

    .cal-week-off-label {
        font-size: 10.5px;
        color: #c9a39a;
        font-weight: 600;
        text-align: center;
        margin-top: 6px;
    }

    .cal-chip {
        border-radius: 5px;
        padding: 3px 6px;
        font-size: 11px;
        line-height: 1.25;
        border-left: 3px solid transparent;
        cursor: pointer;
        overflow: hidden;
        user-select: none;
        -webkit-user-drag: element;
    }

    .cal-chip strong {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cal-chip.status-cancelled {
        opacity: .55;
        text-decoration: line-through;
    }

    .cal-chip-block {
        border-radius: 5px;
        padding: 3px 6px;
        font-size: 10.5px;
        font-weight: 600;
        color: #c9a39a;
        background: repeating-linear-gradient(135deg, rgba(217, 143, 131,0.07), rgba(217, 143, 131,0.07) 6px, rgba(217, 143, 131,0.14) 6px, rgba(217, 143, 131,0.14) 12px);
        border: 1px dashed rgba(217, 143, 131,0.28);
    }

    .cal-empty-hint {
        font-size: 11px;
        color: #8a7d76;
        text-align: center;
        margin: auto 0;
    }

    /* ---------------- MONTH VIEW ---------------- */
    .cal-month-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        border-top: 1px solid var(--cal-border);
        border-left: 1px solid var(--cal-border);
    }

    .cal-month-dow {
        text-align: center;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--cal-muted);
        padding: 8px 0;
        border-right: 1px solid var(--cal-border);
        border-bottom: 1px solid var(--cal-border);
        background: rgba(217, 143, 131,0.06);
    }

    .cal-month-cell {
        min-height: 108px;
        border-right: 1px solid var(--cal-border);
        border-bottom: 1px solid var(--cal-border);
        padding: 6px;
        cursor: pointer;
    }

    .cal-month-cell:hover {
        background: rgba(217, 143, 131,0.06);
    }

    .cal-month-cell.out-month {
        background: #241e1c;
        color: rgba(217, 143, 131,0.28);
    }

    .cal-month-daynum {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--cal-ink);
        margin-bottom: 4px;
    }

    .cal-month-cell.out-month .cal-month-daynum {
        color: #8a7d76;
    }

    .cal-month-cell.is-today .cal-month-daynum {
        color: #241a16;
        background: var(--cal-today);
        border-radius: 50%;
        width: 22px;
        height: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11.5px;
    }

    .cal-month-chip {
        font-size: 10px;
        border-radius: 4px;
        padding: 1px 5px;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        border-left: 2px solid transparent;
    }

    .cal-month-more {
        font-size: 10px;
        color: var(--cal-muted);
        font-weight: 600;
    }

    /* ---------------- SLOT QUICK-ACTION POPOVER ---------------- */
    .cal-slot-popover {
        position: fixed;
        z-index: 1050;
        width: 220px;
        background: #241e1c;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(16, 24, 40, .18);
        border: 1px solid rgba(217, 143, 131,0.16);
        display: none;
        overflow: hidden;
    }

    .cal-slot-popover .popover-time {
        padding: 10px 14px;
        font-weight: 700;
        font-size: 13px;
        border-bottom: 1px solid rgba(217, 143, 131,0.16);
        color: #e79a91;
    }

    .cal-slot-popover .popover-item {
        padding: 10px 14px;
        font-size: 13px;
        color: #e79a91;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .cal-slot-popover .popover-item:hover {
        background: rgba(217, 143, 131,0.06);
    }

    /* ---------------- APPOINTMENT DRAWER ---------------- */
    #apptDrawer {
        width: 400px;
    }

    #apptDrawer .status-pill {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 700;
        color: #fff;
    }

    #apptDrawer .drawer-section {
        border-bottom: 1px solid var(--cal-border);
        padding: 14px 0;
    }

    #apptDrawer .svc-row {
        display: flex;
        justify-content: space-between;
        font-size: 13.5px;
        padding: 3px 0;
    }

    #apptDrawer .allergy-alert {
        background: rgba(168,82,74,0.14);
        border: 1px solid rgba(168,82,74,0.3);
        color: #c07c73;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 12.5px;
        display: flex;
        gap: 8px;
        align-items: flex-start;
    }

    #apptDrawer .visit-row {
        display: flex;
        justify-content: space-between;
        font-size: 12.5px;
        padding: 4px 0;
        color: #cbb8b0;
    }

    #apptDrawer .visit-row .badge {
        font-size: 10px;
    }

    #apptDrawer .svc-item {
        border-left: 3px solid #d98f83;
        background: rgba(217, 143, 131,0.1);
        border-radius: 6px;
        padding: 8px 10px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }

    #apptDrawer .svc-item .name {
        font-weight: 700;
        font-size: 13px;
        color: #e79a91;
    }

    #apptDrawer .svc-item .meta {
        font-size: 11px;
        color: #c9a39a;
    }

    #apptDrawer .svc-item .price {
        font-weight: 700;
        font-size: 13px;
        white-space: nowrap;
    }

    #apptDrawer .svc-item .strike {
        text-decoration: line-through;
        color: #c9a39a;
        font-weight: 400;
        margin-right: 4px;
    }

    #apptDrawer .svc-item .icon-btn {
        border: none;
        background: transparent;
        color: #c9a39a;
        padding: 2px 4px;
        font-size: 15px;
    }

    #apptDrawer .svc-item .icon-btn:hover {
        color: #e79a91;
    }

    #apptDrawer .svc-item .icon-btn.danger:hover {
        color: #a8524a;
    }

    #apptDrawer .drawer-add-pill {
        border: 1px solid #8a7d76;
        border-radius: 999px;
        padding: 6px 16px;
        font-size: 12.5px;
        font-weight: 600;
        background: #241e1c;
        color: #e79a91;
    }

    #apptDrawer .drawer-add-pill:hover {
        background: rgba(217, 143, 131,0.06);
    }

    #apptDrawer .drawer-back-btn {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        border: 1px solid rgba(217, 143, 131,0.16);
        background: #241e1c;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #cbb8b0;
    }

    #apptDrawer .drawer-back-btn:hover {
        background: rgba(217, 143, 131,0.06);
    }

    #apptDrawer .svc-catalog-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
    }

    #apptDrawer .svc-catalog-row:hover {
        background: rgba(217, 143, 131,0.06);
    }

    #apptDrawer .svc-catalog-row .meta {
        font-size: 11px;
        color: #c9a39a;
    }

    /* Upsells use a gold/amber accent so they read as visually distinct from
       the coral-accented original booked services above. */
    #apptDrawer .upsell-item {
        border-left: 3px solid #c9a66b;
        background: rgba(201, 166, 107, 0.12);
        border-radius: 6px;
        padding: 8px 10px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }

    #apptDrawer .upsell-item .name {
        font-weight: 700;
        font-size: 13px;
        color: #d9b878;
    }

    #apptDrawer .upsell-item .name::before {
        content: "\2b06\fe0f";
        font-size: 10px;
        margin-right: 4px;
    }

    #apptDrawer .upsell-item .meta {
        font-size: 11px;
        color: #c9b89a;
    }

    #apptDrawer .upsell-item .price {
        font-weight: 700;
        font-size: 13px;
        white-space: nowrap;
        color: #d9b878;
    }

    #apptDrawer .upsell-item .icon-btn {
        border: none;
        background: transparent;
        color: #c9b89a;
        padding: 2px 4px;
        font-size: 15px;
    }

    #apptDrawer .upsell-item .icon-btn:hover {
        color: #d9b878;
    }

    #apptDrawer .upsell-item .icon-btn.danger:hover {
        color: #a8524a;
    }

    #apptDrawer .drawer-add-pill.upsell {
        border-color: #c9a66b;
        color: #d9b878;
    }

    #apptDrawer .drawer-empty-hint {
        font-size: 11.5px;
        color: #8a7d76;
        padding: 4px 0 10px;
    }

    /* Calendar-card upsell badge: a small gold chip so a booking with an
       upsell attached is distinguishable from the customer's original
       booked service at a glance, without opening the drawer. */
    .cal-appt .upsell-flag, .cal-chip .upsell-flag {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        font-size: 10px;
        font-weight: 700;
        color: #1a1a1a;
        background: #c9a66b;
        border-radius: 4px;
        padding: 0 4px;
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    /* ---------------- TOAST NOTIFICATIONS (FLOATING TOP-RIGHT, 5S) ---------------- */
    .cal-toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        left: auto;
        transform: none;
        z-index: 2100;
        display: flex;
        flex-direction: column;
        gap: 10px;
        align-items: flex-end;
        max-width: min(420px, calc(100vw - 32px));
        pointer-events: none;
    }

    .cal-toast {
        background: #1c1513;
        color: #f6e7dc;
        border: 1px solid rgba(217, 143, 131, 0.28);
        padding: 11px 15px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        line-height: 1.42;
        display: flex;
        align-items: flex-start;
        gap: 11px;
        box-shadow: 0 12px 28px rgba(0, 0, 0, .42);
        animation: calToastIn .2s ease;
        pointer-events: auto;
        width: 100%;
    }

    .cal-toast-dot {
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #8ea88a;
        margin-top: 6px;
        flex-shrink: 0;
    }

    .cal-toast.error {
        background: #221615;
        border-color: rgba(168, 82, 74, 0.55);
        color: #f9ebe8;
    }

    .cal-toast.error .cal-toast-dot {
        background: #d97b73;
    }

    .cal-toast .cal-toast-text {
        flex: 1 1 auto;
        min-width: 0;
    }

    .cal-toast .toast-close {
        background: transparent;
        border: 0;
        color: #c9a39a;
        cursor: pointer;
        opacity: .8;
        font-size: 17px;
        line-height: 1;
        padding: 0 2px;
        flex-shrink: 0;
    }

    .cal-toast .toast-close:hover {
        opacity: 1;
        color: #fff;
    }

    @keyframes calToastIn {
        from { opacity: 0; transform: translateX(12px); }
        to { opacity: 1; transform: translateX(0); }
    }

    .drawer-conflict-banner {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 16px;
        background: rgba(168, 82, 74, 0.14);
        border-top: 1px solid rgba(168, 82, 74, 0.38);
        color: #f6e7dc;
        font-size: 0.8rem;
        line-height: 1.42;
    }

    .drawer-conflict-banner-body {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        min-width: 0;
    }

    .drawer-conflict-banner-dot {
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #d97b73;
        margin-top: 5px;
        flex-shrink: 0;
    }

    .drawer-conflict-banner-close {
        background: transparent;
        border: 0;
        color: #c9a39a;
        font-size: 1rem;
        line-height: 1;
        padding: 0 2px;
        cursor: pointer;
        flex-shrink: 0;
    }

    .drawer-conflict-banner-close:hover {
        color: #f6e7dc;
    }

    .cal-appt.is-reverting,
    .cal-appt.is-previewing {
        transition: top .22s cubic-bezier(0.22, 1, 0.36, 1), height .22s cubic-bezier(0.22, 1, 0.36, 1), opacity .18s ease;
    }

    .cal-appt-resize-handle {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 7px;
        cursor: ns-resize;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity .15s ease;
    }

    .cal-appt-resize-handle::after {
        content: '';
        width: 24px;
        height: 2px;
        border-radius: 2px;
        background: currentColor;
        opacity: 0.55;
    }

    .cal-appt:hover .cal-appt-resize-handle {
        opacity: 1;
    }

    /* ---------------- DRAG AND DROP ---------------- */
    .cal-appt[draggable="true"], .cal-chip[draggable="true"] {
        cursor: grab;
    }

    .cal-appt.dragging, .cal-chip.dragging {
        opacity: .4;
    }

    .cal-slot-cell.drag-over {
        background: rgba(217, 143, 131,0.12) !important;
        outline: 2px dashed #d98f83;
        outline-offset: -2px;
    }

    .cal-week-daycell.drag-over {
        background: rgba(217, 143, 131,0.12) !important;
        outline: 2px dashed #d98f83;
        outline-offset: -2px;
    }

    /* ---------------- UNSCHEDULED QUEUE SIDEBAR ---------------- */
    .cal-workspace {
        display: flex;
        align-items: flex-start;
        gap: 16px;
        position: relative;
    }

    .cal-workspace > .cal-scroll {
        flex: 1 1 0%;
        min-width: 0;
    }

    .cal-unscheduled-sidebar {
        width: 290px;
        flex-shrink: 0;
        border: 1px solid var(--cal-border);
        border-radius: 12px;
        background: rgba(36, 30, 28, 0.72);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 210px);
        min-height: 360px;
        transition: width .22s ease, min-width .22s ease;
        overflow: hidden;
    }

    .cal-unscheduled-sidebar.is-collapsed {
        width: 46px;
        min-height: 220px;
    }

    .cal-unscheduled-header {
        padding: 12px 14px;
        border-bottom: 1px solid var(--cal-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        background: #241e1c;
    }

    .cal-unscheduled-sidebar.is-collapsed .cal-unscheduled-header {
        flex-direction: column;
        padding: 12px 6px;
        border-bottom: none;
        height: 100%;
        justify-content: flex-start;
        gap: 14px;
    }

    .cal-unscheduled-title-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    .cal-unscheduled-sidebar.is-collapsed .cal-unscheduled-title-wrap {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        white-space: nowrap;
        gap: 8px;
    }

    .cal-unscheduled-title {
        font-size: 13px;
        font-weight: 700;
        color: var(--cal-ink);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cal-unscheduled-badge {
        background: rgba(201, 166, 107, 0.2);
        color: #d9b878;
        border: 1px solid rgba(201, 166, 107, 0.35);
        font-size: 11px;
        font-weight: 700;
        border-radius: 999px;
        padding: 1px 7px;
        line-height: 1.3;
    }

    .cal-unscheduled-actions {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
    }

    .cal-unscheduled-sidebar.is-collapsed .cal-unscheduled-add-btn {
        display: none;
    }

    .cal-unscheduled-btn {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        border: 1px solid var(--cal-border-strong);
        background: rgba(217, 143, 131, 0.06);
        color: #cbb8b0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        transition: all .15s ease;
    }

    .cal-unscheduled-btn:hover {
        background: rgba(217, 143, 131, 0.14);
        color: var(--cal-ink);
    }

    .cal-unscheduled-sub {
        padding: 8px 14px;
        font-size: 11px;
        color: var(--cal-muted);
        border-bottom: 1px dashed var(--cal-border);
        background: rgba(36, 30, 28, 0.4);
    }

    .cal-unscheduled-body {
        padding: 10px;
        overflow-y: auto;
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .cal-unscheduled-sidebar.is-collapsed .cal-unscheduled-sub,
    .cal-unscheduled-sidebar.is-collapsed .cal-unscheduled-body {
        display: none;
    }

    .cal-unscheduled-card {
        border: 1px solid rgba(201, 166, 107, 0.28);
        border-left: 3px solid #c9a66b;
        background: rgba(201, 166, 107, 0.1);
        border-radius: 8px;
        padding: 9px 10px;
        cursor: grab;
        transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
        user-select: none;
        -webkit-user-drag: element;
    }

    .cal-unscheduled-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, .25);
        border-color: rgba(217, 143, 131, 0.45);
    }

    .cal-unscheduled-card.dragging {
        opacity: .4;
        cursor: grabbing;
    }

    .cal-unscheduled-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        margin-bottom: 3px;
    }

    .cal-unscheduled-card .unsched-customer {
        font-size: 12.5px;
        font-weight: 700;
        color: #e79a91;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cal-unscheduled-card .unsched-grip {
        color: #8a7d76;
        font-size: 14px;
        flex-shrink: 0;
    }

    .cal-unscheduled-card .unsched-service {
        font-size: 11.5px;
        font-weight: 600;
        color: #e6d9d5;
        margin-bottom: 4px;
        line-height: 1.3;
    }

    .cal-unscheduled-card .unsched-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
        font-size: 11px;
        color: var(--cal-muted);
    }

    .cal-unscheduled-card .unsched-contact {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .cal-unscheduled-card .unsched-duration {
        font-size: 10.5px;
        font-weight: 700;
        color: #d9b878;
        background: rgba(201, 166, 107, 0.16);
        padding: 1px 6px;
        border-radius: 999px;
        flex-shrink: 0;
    }

    .cal-unscheduled-empty {
        text-align: center;
        padding: 28px 12px;
        color: #8a7d76;
        font-size: 12px;
    }

    .cal-unscheduled-empty i {
        font-size: 24px;
        display: block;
        margin-bottom: 6px;
        color: rgba(217, 143, 131, 0.35);
    }

    @media (max-width: 991px) {
        .cal-workspace {
            flex-direction: column-reverse;
        }
        .cal-unscheduled-sidebar,
        .cal-unscheduled-sidebar.is-collapsed {
            width: 100%;
            max-height: 280px;
            min-height: auto;
        }
        .cal-unscheduled-sidebar.is-collapsed .cal-unscheduled-header {
            flex-direction: row;
            padding: 10px 14px;
        }
        .cal-unscheduled-sidebar.is-collapsed .cal-unscheduled-title-wrap {
            writing-mode: horizontal-tb;
            transform: none;
        }
    }
</style>

@section('content')
    <div class="cal-toast-container" id="calToastContainer"></div>

    <div class="cal-toolbar-card">
        <h6 class="cal-branch-heading" id="activeBranchHeading">Laleen &mdash; All Locations</h6>
        <div class="cal-toolbar">
            <div class="cal-toolbar-left">
                <div class="cal-view-toggle">
                    <button type="button" class="cal-view-btn active" data-view="day">Day</button>
                    <button type="button" class="cal-view-btn" data-view="3day">3-Day</button>
                    <button type="button" class="cal-view-btn" data-view="week">Week</button>
                    <button type="button" class="cal-view-btn" data-view="month">Month</button>
                </div>

                <div class="cal-toolbar-divider"></div>

                <div class="cal-nav">
                    <button type="button" id="prevBtn" title="Previous"><i class="bx bx-chevron-left"></i></button>
                    <button type="button" id="nextBtn" title="Next"><i class="bx bx-chevron-right"></i></button>
                </div>
                <button type="button" class="cal-today-btn" id="todayBtn">Today</button>
                <div class="cal-date-label" id="dateLabel">&nbsp;</div>
            </div>

            <div class="cal-toolbar-right">
                <div class="cal-zoom" id="zoomControl">
                    <button type="button" id="zoomOutBtn" title="Zoom out"><i class="bx bx-minus"></i></button>
                    <button type="button" id="zoomInBtn" title="Zoom in"><i class="bx bx-plus"></i></button>
                </div>

                <div class="dropdown">
                    <button class="cal-icon-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" title="Filters">
                        <i class="bx bx-filter-alt"></i>
                        <span class="cal-filter-badge d-none" id="filterBadge">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end cal-filter-panel">
                        <div class="cal-filter-title">Filters</div>

                        <div class="cal-filter-field">
                            <label for="filterBranch">Location</label>
                            <select id="filterBranch" class="cal-filter-select">
                                <option value="old_airport">Old Airport</option>
                                <option value="wakrah">Al Wakrah</option>
                            </select>
                        </div>

                        <div class="cal-filter-field">
                            <label for="filterStaff">Team Member</label>
                            <select id="filterStaff" class="cal-filter-select">
                                <option value="">All Team Members</option>
                                @foreach ($staffs as $staff)
                                    <option value="{{ $staff->id }}" data-branch="{{ $staff->branch ?: 'both' }}">{{ $staff->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="cal-filter-footer">
                            <button type="button" class="cal-filter-clear" id="filterClearBtn">Clear filters</button>
                        </div>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn btn-primary cal-add-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bx bx-plus me-1"></i> Add
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" id="addNewAppointment"><i class="bx bx-calendar-plus me-2"></i> New Appointment</a></li>
                        <li><a class="dropdown-item" href="#" id="addNewUnscheduledAppointment"><i class="bx bx-time-five me-2"></i> Unscheduled Booking</a></li>
                        <li><a class="dropdown-item" href="#" id="addBlockTime" data-bs-toggle="modal" data-bs-target="#blockTimeModal"><i class="bx bx-block me-2"></i> Block Time</a></li>
                        <li><a class="dropdown-item" href="{{ route('sales.create') }}"><i class="bx bx-cart-add me-2"></i> New Sale</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="cal-legend" id="calLegend"></div>
    </div>

    <div class="cal-workspace">
        <div class="cal-scroll">
            <div id="calendarBody"></div>
        </div>

        <!-- Collapsible Unscheduled Queue Sidebar -->
        <aside class="cal-unscheduled-sidebar" id="unscheduledSidebar">
            <div class="cal-unscheduled-header">
                <div class="cal-unscheduled-title-wrap">
                    <h6 class="cal-unscheduled-title" id="unscheduledSidebarTitle">Unscheduled (Today)</h6>
                    <span class="cal-unscheduled-badge" id="unscheduledCountBadge">{{ count($pendingTimeBookings ?? []) }}</span>
                </div>
                <div class="cal-unscheduled-actions">
                    <button type="button" class="cal-unscheduled-btn cal-unscheduled-add-btn" id="sidebarAddUnscheduledBtn" title="Add Unscheduled Booking">
                        <i class="bx bx-plus"></i>
                    </button>
                    <button type="button" class="cal-unscheduled-btn" id="toggleUnscheduledSidebarBtn" title="Collapse / Expand Queue">
                        <i class="bx bx-chevron-right" id="toggleUnscheduledIcon"></i>
                    </button>
                </div>
            </div>
            <div class="cal-unscheduled-sub">
                Drag a booking card onto a time &amp; staff slot to schedule it.
            </div>
            <div class="cal-unscheduled-body" id="unscheduledQueueList">
                @forelse ($pendingTimeBookings ?? [] as $booking)
                    <div class="cal-unscheduled-card"
                        data-id="{{ $booking['id'] }}"
                        data-services="{{ $booking['service_name'] }}"
                        draggable="{{ auth()->user()->canEdit('bookings') ? 'true' : 'false' }}"
                        ondragstart="onApptDragStart(event, {{ $booking['id'] }}, null, true)"
                        ondragend="onApptDragEnd(event)"
                        onclick="openDrawer({{ $booking['id'] }})">
                        <div class="cal-unscheduled-card-top">
                            <span class="unsched-customer">{{ $booking['customer_name'] ?: 'Walk-in' }}</span>
                            <i class="bx bx-grid-vertical unsched-grip"></i>
                        </div>
                        @if (!empty($booking['combo_name']))
                            <div class="mb-1"><span class="cal-combo-badge">{{ $booking['combo_name'] }}</span></div>
                        @endif
                        <div class="unsched-service">{{ !empty($booking['is_combo_tbd']) ? 'Services TBD in Salon' : $booking['service_name'] }}</div>
                        <div class="unsched-meta">
                            <span class="unsched-contact"><i class="bx bx-phone"></i> {{ $booking['phone'] ?: 'No phone' }}</span>
                            <span class="unsched-duration">{{ $booking['duration'] ?? 30 }}m</span>
                        </div>
                    </div>
                @empty
                    <div class="cal-unscheduled-empty">
                        <i class="bx bx-calendar-check"></i>
                        No unscheduled bookings for this date.
                    </div>
                @endforelse
            </div>
        </aside>
    </div>

    <div class="cal-slot-popover" id="slotPopover">
        <div class="popover-time" id="slotPopoverTime"></div>
        <div class="popover-item" id="slotPopoverAddAppt"><i class="bx bx-calendar-plus"></i> Add appointment</div>
        <div class="popover-item" id="slotPopoverAddBlock"><i class="bx bx-block"></i> Add blocked time</div>
    </div>

    <!-- Appointment Drawer -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="apptDrawer">
        <div class="offcanvas-header border-bottom" id="drawerMainHeader">
            <div class="flex-grow-1 me-2">
                <div class="d-flex align-items-center gap-2" id="drawerCustomerNameRow">
                    <h5 class="offcanvas-title mb-0" id="drawerCustomer">&nbsp;</h5>
                    <button type="button"
                        class="btn btn-sm py-0 px-1 d-none"
                        id="drawerEditCustomerNameBtn"
                        title="Edit client name"
                        style="border: 1px solid rgba(217, 143, 131, 0.28); background: rgba(217, 143, 131, 0.08); color: #e79a91; font-size: 11px;">✏️</button>
                </div>
                <div class="d-none align-items-center gap-1 my-1" id="drawerCustomerEditWrap">
                    <input type="text"
                        id="drawerCustomerNameInput"
                        class="form-control form-control-sm"
                        maxlength="120"
                        placeholder="Client name"
                        style="background: #1a1514; border: 1px solid rgba(217, 143, 131, 0.45); color: #f3e5e0;">
                    <button type="button" class="btn btn-sm py-1 px-2" id="drawerSaveCustomerNameBtn" title="Save name" style="background: rgba(142, 168, 138, 0.22); border: 1px solid rgba(142, 168, 138, 0.5); color: #b8d8b3; font-weight: 700;">✓</button>
                    <button type="button" class="btn btn-sm py-1 px-2" id="drawerCancelCustomerNameBtn" title="Cancel" style="background: rgba(168, 82, 74, 0.18); border: 1px solid rgba(168, 82, 74, 0.45); color: #e09b95; font-weight: 700;">✕</button>
                </div>
                <div class="text-muted small" id="drawerPhone"></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="drawer-combo-subheader d-none" id="drawerComboSubHeader">
            <button type="button" class="drawer-back-btn" id="drawerComboSubBackBtn" title="Back to booking"><i class="bx bx-arrow-back"></i></button>
            <h6 class="mb-0 fw-bold text-truncate flex-grow-1" id="drawerComboSubTitle" style="color: #f8efe6; font-size: 14.5px;">&nbsp;</h6>
            <span class="drawer-combo-subcounter" id="drawerComboSubCounter">0 / 0 Selected</span>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0 d-flex flex-column">
            <div class="flex-grow-1 overflow-auto" id="drawerMainBody">
                <div class="px-3 pt-3">
                    <span class="status-pill" id="drawerStatusPill"></span>
                </div>

                <div class="px-3 mt-2 d-none" id="drawerAllergyWrap">
                    <div class="allergy-alert">
                        <i class="bx bx-error-circle fs-5"></i>
                        <div>
                            <strong>Allergy / Staff Alert</strong>
                            <div id="drawerAllergyText"></div>
                        </div>
                    </div>
                </div>

                <div class="drawer-section px-3">
                    <div class="text-muted small mb-1"><i class="bx bx-calendar"></i> <span id="drawerDatetime"></span></div>
                    <div class="text-muted small mb-1"><i class="bx bx-map-pin"></i> <span id="drawerBranch"></span></div>
                    <div class="text-muted small"><i class="bx bx-user-voice"></i> with <span id="drawerStaff"></span></div>
                </div>

                <div class="drawer-section px-3 d-none" id="drawerVisitsSection">
                    <h6 class="text-uppercase small fw-bold text-muted mb-2">Recent Visits</h6>
                    <div id="drawerRecentVisits"></div>
                </div>

                <div class="drawer-section px-3" id="drawerServicesSection">
                    <!-- LIST VIEW -->
                    <div id="drawerServicesListView">
                        <h6 class="text-uppercase small fw-bold text-muted mb-2">Services &amp; Combos</h6>
                        <div id="drawerComboPackagesList"></div>
                        <div id="drawerServices"></div>
                        <button type="button" class="drawer-add-pill" id="drawerAddServiceBtn">
                            <i class="bx bx-plus"></i> Add service
                        </button>
                        <div class="svc-row small d-none" id="drawerDiscountRow" style="color:#8ea88a;">
                            <span>Total discount</span>
                            <span id="drawerDiscountTotal"></span>
                        </div>
                        <div class="svc-row fw-bold mt-2 pt-2 border-top">
                            <span>Total</span>
                            <span id="drawerTotal"></span>
                        </div>
                    </div>

                    <!-- ADD SERVICE VIEW -->
                    <div id="drawerServicesAddView" class="d-none">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <button type="button" class="drawer-back-btn" id="drawerAddServiceBack"><i class="bx bx-arrow-back"></i></button>
                            <h6 class="mb-0 text-uppercase small fw-bold text-muted">Add a service</h6>
                        </div>
                        <input type="text" id="drawerServiceSearch" class="form-control form-control-sm mb-2" placeholder="Search by service name">
                        <div id="drawerServiceResults" style="max-height:240px; overflow-y:auto;"></div>
                    </div>

                    <!-- EDIT SERVICE VIEW -->
                    <div id="drawerServicesEditView" class="d-none">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <button type="button" class="drawer-back-btn" id="drawerEditServiceBack"><i class="bx bx-arrow-back"></i></button>
                            <h6 class="mb-0 text-uppercase small fw-bold text-muted">Edit service</h6>
                        </div>

                        <select id="editSvcServiceSelect" class="form-select form-select-sm mb-2"></select>

                        <div class="text-muted small mb-2 d-none" id="editSvcOriginalPriceWrap">
                            Catalog price: <span id="editSvcOriginalPrice"></span> QAR
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small mb-1">Price (QAR)</label>
                                <input type="number" id="editSvcPrice" class="form-control form-control-sm" min="0" step="0.01">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1">Discount</label>
                                <select id="editSvcDiscountType" class="form-select form-select-sm">
                                    <option value="">No discount</option>
                                    <option value="flat">Flat (QAR)</option>
                                    <option value="percent">Percent (%)</option>
                                </select>
                            </div>
                        </div>
                        <input type="number" id="editSvcDiscountValue" class="form-control form-control-sm mt-2 d-none" min="0" step="0.01" placeholder="Discount value">

                        <div class="d-flex align-items-center gap-2 mt-2 p-2 rounded d-none" id="editSvcDiscountSummary" style="background: rgba(142,168,138,0.14);">
                            <span class="fw-bold small" style="color:#8ea88a;">Discount: <span id="editSvcDiscountAmount">0.00</span> QAR</span>
                        </div>
                        <input type="text" id="editSvcDiscountReason" class="form-control form-control-sm mt-2" placeholder="Discount reason (optional)">

                        <div class="row g-2 mt-1">
                            <div class="col-6">
                                <label class="form-label small mb-1">Start time</label>
                                <input type="time" id="editSvcStartTime" class="form-control form-control-sm">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1">Duration (min)</label>
                                <input type="number" id="editSvcDuration" class="form-control form-control-sm" min="5" step="5">
                            </div>
                        </div>

                        <label class="form-label small mb-1 mt-2">Team member</label>
                        <select id="editSvcStaff" class="form-select form-select-sm">
                            <option value="">Unassigned</option>
                            @foreach ($staffs as $staff)
                                <option value="{{ $staff->id }}" data-branch="{{ $staff->branch ?: 'both' }}">{{ $staff->name }}</option>
                            @endforeach
                        </select>

                        <div class="d-flex gap-2 mt-3">
                            <button type="button" class="btn btn-outline-danger" id="editSvcDeleteBtn"><i class="bx bx-trash"></i></button>
                            <button type="button" class="btn btn-dark flex-grow-1" id="editSvcApplyBtn">Apply</button>
                        </div>
                    </div>
                </div>

                <div class="drawer-section px-3" id="drawerUpsellsSection">
                    <!-- LIST VIEW -->
                    <div id="drawerUpsellsListView">
                        <h6 class="text-uppercase small fw-bold text-muted mb-2">Upsells</h6>
                        <div id="drawerUpsells"></div>
                        <button type="button" class="drawer-add-pill upsell" id="drawerAddUpsellBtn">
                            <i class="bx bx-plus"></i> Add upsell
                        </button>
                    </div>

                    <!-- ADD UPSELL VIEW -->
                    <div id="drawerUpsellsAddView" class="d-none">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <button type="button" class="drawer-back-btn" id="drawerAddUpsellBack"><i class="bx bx-arrow-back"></i></button>
                            <h6 class="mb-0 text-uppercase small fw-bold text-muted">Add an upsell</h6>
                        </div>

                        <label class="form-label small mb-1">Type</label>
                        <div class="btn-group w-100 mb-2" role="group">
                            <input type="radio" class="btn-check" name="upsellType" id="upsellTypeService" value="service" checked>
                            <label class="btn btn-outline-secondary btn-sm" for="upsellTypeService">Service</label>
                            <input type="radio" class="btn-check" name="upsellType" id="upsellTypeProduct" value="product">
                            <label class="btn btn-outline-secondary btn-sm" for="upsellTypeProduct">Retail Product</label>
                        </div>

                        <label class="form-label small mb-1" id="upsellItemLabel">Service</label>
                        <select id="upsellItem" class="form-select form-select-sm mb-2">
                            <option value="">Select…</option>
                        </select>

                        <label class="form-label small mb-1">Value (QAR)</label>
                        <input type="number" id="upsellAmount" class="form-control form-control-sm mb-2" min="0" step="0.01">

                        <label class="form-label small mb-1">Staff responsible</label>
                        <select id="upsellStaff" class="form-select form-select-sm mb-2">
                            <option value="">Select staff member</option>
                            @foreach ($activeStaffs as $staff)
                                <option value="{{ $staff->id }}" data-branch="{{ $staff->branch ?: 'both' }}">{{ $staff->name }}</option>
                            @endforeach
                        </select>

                        <button type="button" class="btn btn-dark w-100 mt-2" id="upsellSaveBtn">Save upsell</button>
                    </div>
                </div>
            </div>

            <!-- DEDICATED COMBO SERVICE PICKER SUB-VIEW -->
            <div class="drawer-combo-subview d-none" id="drawerComboSubView">
                <div class="drawer-combo-search-wrap">
                    <i class="bx bx-search"></i>
                    <input type="text"
                        id="drawerComboSubSearch"
                        class="drawer-combo-search-input"
                        placeholder="Search services in this combo..."
                        autocomplete="off">
                </div>
                <button type="button" class="drawer-combo-skip-bar" id="drawerComboSubSkipBtn">
                    <span>Skip selection — Decide services in salon</span>
                    <span style="opacity:0.75; font-size:14px;">&rsaquo;</span>
                </button>
                <div class="drawer-combo-cap-hint d-none" id="drawerComboSubCapHint"></div>
                <div class="drawer-combo-sublist" id="drawerComboSubList"></div>
            </div>

            <div id="drawerConflictBanner" class="drawer-conflict-banner d-none" role="alert" aria-live="polite">
                <div class="drawer-conflict-banner-body">
                    <span class="drawer-conflict-banner-dot" aria-hidden="true"></span>
                    <span id="drawerConflictText"></span>
                </div>
                <button type="button" class="drawer-conflict-banner-close" id="drawerConflictDismiss" aria-label="Dismiss">&times;</button>
            </div>

            <div class="px-3 py-3 border-top" id="drawerMainFooter">
                <div class="d-flex gap-2 mb-2" id="drawerStatusRow"></div>

                <div class="dropdown mb-2" id="drawerQuickActionsWrap">
                    <button class="btn btn-outline-secondary w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bx bx-cog"></i> Quick actions
                    </button>
                    <ul class="dropdown-menu w-100">
                        <li><a class="dropdown-item" href="#" id="drawerRescheduleBtn"><i class="bx bx-calendar-edit me-1"></i> Reschedule</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-warning" href="#" id="drawerNoShowBtn"><i class="bx bx-block me-1"></i> No show</a></li>
                        <li><a class="dropdown-item text-danger" href="#" id="drawerCancelBtn"><i class="bx bx-x-circle me-1"></i> Cancel</a></li>
                    </ul>
                </div>

                <div id="drawerRescheduleForm" class="d-none border rounded p-2 mb-2 bg-light">
                    <input type="hidden" id="drawerRescheduleBranch" value="old_airport">
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="date" id="drawerRescheduleDate" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <input type="time" id="drawerRescheduleTime" class="form-control form-control-sm">
                        </div>
                    </div>
                    <select id="drawerRescheduleStaff" class="form-select form-select-sm mt-2">
                        <option value="">Unassigned</option>
                        @foreach ($staffs as $staff)
                            <option value="{{ $staff->id }}" data-branch="{{ $staff->branch ?: 'both' }}">{{ $staff->name }}</option>
                        @endforeach
                    </select>
                    <div class="d-flex gap-2 mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary flex-grow-1" id="drawerCancelRescheduleBtn">Cancel</button>
                        <button type="button" class="btn btn-sm btn-primary flex-grow-1" id="drawerConfirmRescheduleBtn">Confirm</button>
                    </div>
                </div>

                <a href="#" id="drawerCheckoutBtn" class="btn btn-success w-100">💳 Checkout</a>
                <a href="#" id="drawerProfileLink" class="btn btn-link w-100 mt-1 d-none">
                    <i class="bx bx-user-circle"></i> View Full Profile
                </a>
            </div>

            <div class="drawer-combo-subfooter d-none" id="drawerComboSubFooter">
                <div>
                    <div id="drawerComboSubHoldSummary" style="font-size: 12px; font-weight: 700; color: #f8efe6;">60m hold</div>
                    <div id="drawerComboSubPriceSummary" style="font-size: 11px; color: #a9958f;">Locked package price</div>
                </div>
                <button type="button" class="drawer-combo-apply-btn" id="drawerComboSubApplyBtn">
                    Done &middot; Apply to Booking
                </button>
            </div>
        </div>
    </div>

    <!-- Block Time Modal -->
    <div class="modal fade" id="blockTimeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('staff-blocks.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Block Time</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Team Member</label>
                            <select name="staff_id" id="blockStaffSelect" class="form-select" required>
                                <option value="">-- Select --</option>
                                @foreach ($staffs as $staff)
                                    <option value="{{ $staff->id }}" data-branch="{{ $staff->branch ?: 'both' }}">{{ $staff->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" id="blockDate" class="form-control" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Start Time</label>
                                <input type="time" name="start_time" id="blockStartTime" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">End Time</label>
                                <input type="time" name="end_time" id="blockEndTime" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Reason (optional)</label>
                            <input type="text" name="reason" class="form-control" placeholder="Lunch break, training, etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Block Time</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @moduleEdit('bookings')
        @include('appointments.calendar_book')
    @endmoduleEdit

    <script>
        // Drives every UI gate below: view-only users can browse the calendar
        // and open the drawer, but every mutating control stays hidden/inert.
        const CAN_EDIT_BOOKINGS = @json(auth()->user()->canEdit('bookings'));
        const CAN_EDIT_FINANCE = @json(auth()->user()->canEdit('finance'));

        const STORAGE_KEY = 'laleen_calendar_filters';
        const ZOOM_LEVELS = [40, 56, 72, 90];

        function toLocalISODate(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        // No "All Locations" option exists any more - every view is scoped
        // to one branch to prevent cross-branch booking errors, so this
        // must never resolve to an empty/null branch, even for a session
        // that cached an empty value from before that option was removed.
        const DEFAULT_BRANCH = 'old_airport';

        function normalizeCalendarBranch(raw) {
            if (!raw) return '';
            const slug = String(raw).trim().toLowerCase().replace(/[\s\-]+/g, '_');
            if (slug === 'old_airport' || slug === 'airport') return 'old_airport';
            if (slug === 'wakrah' || slug === 'al_wakrah' || slug === 'alwakrah') return 'wakrah';
            return '';
        }

        function scopeSelectOptionsToBranch(selectEl, branchKey, keepStaffId = null) {
            if (!selectEl) return;
            const normBranch = normalizeCalendarBranch(branchKey) || DEFAULT_BRANCH;
            Array.from(selectEl.options).forEach(opt => {
                if (!opt.value) return;
                const optBranch = normalizeCalendarBranch(opt.dataset.branch) || opt.dataset.branch || 'both';
                const matches = optBranch === 'both'
                    || optBranch === normBranch
                    || (keepStaffId && String(opt.value) === String(keepStaffId));
                opt.hidden = !matches;
                opt.disabled = !matches;
            });
            if (selectEl.value) {
                const selectedOpt = Array.from(selectEl.options).find(o => o.value === selectEl.value);
                if (selectedOpt && selectedOpt.disabled) {
                    selectEl.value = '';
                }
            }
        }

        function loadFilters() {
            let saved = {};
            try {
                saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY)) || {};
            } catch (e) {}
            const state = Object.assign({
                view: 'day',
                date: toLocalISODate(new Date()),
                branch: DEFAULT_BRANCH,
                staff_id: '',
                zoom: 56
            }, saved);
            const urlParams = new URLSearchParams(window.location.search);
            const urlBranch = normalizeCalendarBranch(urlParams.get('branch'));
            if (urlBranch) {
                state.branch = urlBranch;
            } else {
                state.branch = normalizeCalendarBranch(state.branch) || DEFAULT_BRANCH;
            }
            if (urlParams.get('date')) {
                state.date = urlParams.get('date');
            }
            return state;
        }

        function saveFilters() {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        }

        let state = loadFilters();
        window.getActiveCalendarBranch = () => state.branch;
        const CAL_BRANCH_LABELS = { old_airport: 'Old Airport', wakrah: 'Al Wakrah' };
        let latestData = null;
        let nowLineTimer = null;
        const DRAWER_SERVICES_CATALOG = @json($servicesCatalog);
        const DRAWER_PRODUCTS_CATALOG = @json($productsCatalog);
        const DRAWER_STAFFS_CATALOG = @json($activeStaffs->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'branch' => $s->branch ?: 'both'])->values());

        // Hide every mutating "Add" entry point up front for view-only users.
        (function applyCalendarPermissionGates() {
            document.getElementById('addNewAppointment').closest('li').style.display = CAN_EDIT_BOOKINGS ? '' : 'none';
            document.getElementById('addNewUnscheduledAppointment').closest('li').style.display = CAN_EDIT_BOOKINGS ? '' : 'none';
            document.getElementById('addBlockTime').closest('li').style.display = CAN_EDIT_BOOKINGS ? '' : 'none';
            const sidebarAddBtn = document.getElementById('sidebarAddUnscheduledBtn');
            if (sidebarAddBtn) sidebarAddBtn.style.display = CAN_EDIT_BOOKINGS ? '' : 'none';
            const newSaleItem = document.getElementById('addNewAppointment').closest('.dropdown-menu').querySelector('a[href*="/sales/new"]');
            if (newSaleItem) newSaleItem.closest('li').style.display = CAN_EDIT_FINANCE ? '' : 'none';
            if (!CAN_EDIT_BOOKINGS && !CAN_EDIT_FINANCE) {
                document.querySelector('.cal-add-btn')?.closest('.dropdown').style.setProperty('display', 'none');
            }

            document.getElementById('slotPopoverAddAppt').style.display = CAN_EDIT_BOOKINGS ? '' : 'none';
            document.getElementById('slotPopoverAddBlock').style.display = CAN_EDIT_BOOKINGS ? '' : 'none';
        })();

        function to12Hour(time) {
            const [h, m] = time.split(':').map(Number);
            const p = h >= 12 ? 'PM' : 'AM';
            const hh = h % 12 || 12;
            return `${hh}:${m.toString().padStart(2,'0')} ${p}`;
        }

        function fmtDateLabel(dateStr) {
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        }

        function syncToolbar() {
            document.querySelectorAll('.cal-view-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.view === state.view);
            });
            state.branch = normalizeCalendarBranch(state.branch) || DEFAULT_BRANCH;
            document.getElementById('filterBranch').value = state.branch;

            const filterStaffEl = document.getElementById('filterStaff');
            scopeSelectOptionsToBranch(filterStaffEl, state.branch);
            filterStaffEl.value = state.staff_id;
            if (state.staff_id && filterStaffEl.value !== String(state.staff_id)) {
                state.staff_id = '';
                saveFilters();
            }

            scopeSelectOptionsToBranch(document.getElementById('blockStaffSelect'), state.branch);
            if (typeof window.syncCalendarBookBranch === 'function') {
                window.syncCalendarBookBranch(state.branch);
            }

            document.getElementById('activeBranchHeading').textContent =
                'Laleen — ' + (CAL_BRANCH_LABELS[state.branch] || 'Old Airport');

            // Location is now a mandatory branch scope, not an optional
            // filter (there's no "All Locations" to clear back to), so only
            // the team-member filter counts toward the badge/Clear button.
            const activeFilters = state.staff_id ? 1 : 0;
            const badge = document.getElementById('filterBadge');
            badge.textContent = activeFilters;
            badge.classList.toggle('d-none', activeFilters === 0);
            document.getElementById('filterClearBtn').disabled = activeFilters === 0;

            document.getElementById('zoomControl').style.display = (state.view === 'month') ? 'none' : 'inline-flex';
            document.getElementById('blockDate').value = state.date;
        }

        function renderLegend(statusColors) {
            const labels = {
                pending: 'Pending', arrived: 'Arrived', in_progress: 'In Progress',
                completed: 'Completed', no_show: 'No Show', cancelled: 'Cancelled'
            };
            let html = '';
            Object.keys(statusColors).forEach(key => {
                html += `<span><span class="dot" style="background:${statusColors[key]}"></span>${labels[key] || key}</span>`;
            });
            html += `<span><span class="dot" style="background:rgba(217, 143, 131,0.28)"></span>Blocked</span>`;
            document.getElementById('calLegend').innerHTML = html;
        }

        function tint(hex, alpha) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r},${g},${b},${alpha})`;
        }

        /* ---------------- DAY / 3-DAY-AS-COLUMNS RENDER (staff columns, time rows) ---------------- */
        function renderDay(data) {
            const HEADER_H = 84;
            const SLOT_MIN = 30;
            const SLOT_H = state.zoom;
            const PPM = SLOT_H / SLOT_MIN;

            const [sh, sm] = data.ui_start.split(':').map(Number);
            const dayStartMinutes = sh * 60 + sm;

            let html = `<div class="cal-day-grid">`;

            html += `<div class="cal-time-col"><div class="cal-staff-header"></div>`;
            data.time_slots.forEach(t => {
                html += `<div class="cal-time-cell" style="height:${SLOT_H}px">${t.endsWith(':00') ? to12Hour(t) : ''}</div>`;
            });
            html += `</div>`;

            data.staffs.forEach(staff => {
                const isOff = !!data.off[staff.id];
                const apts = data.appointments[staff.id] || [];
                const blocks = (data.blocks && data.blocks[staff.id]) || [];

                html += `<div class="cal-staff-col ${isOff ? 'is-off' : ''}" data-staff-id="${staff.id}" data-staff-name="${staff.name}">
                    <div class="cal-staff-header">
                        <img src="${staff.profile_picture}">
                        <div class="name">${staff.name}</div>
                        <div class="badge-count">${apts.length} appt${apts.length === 1 ? '' : 's'}</div>
                    </div>
                    <div class="cal-slots">`;

                if (isOff) {
                    data.time_slots.forEach(t => {
                        html += `<div class="cal-slot-cell" style="height:${SLOT_H}px" data-time="${t}"
                                onclick="onSlotClick(event, ${staff.id}, '${data.date}', '${t}')"
                                ondragover="onDropTargetDragOver(event)" ondragleave="onDropTargetDragLeave(event)"
                                ondrop="onSlotDrop(event, ${staff.id}, '${data.date}', '${t}')"></div>`;
                    });
                    html += `<div class="cal-off-tag" style="pointer-events:none;">Not working<br>${data.off[staff.id]}</div>`;
                } else {
                    data.time_slots.forEach(t => {
                        html += `<div class="cal-slot-cell" style="height:${SLOT_H}px" data-time="${t}"
                                onclick="onSlotClick(event, ${staff.id}, '${data.date}', '${t}')"
                                ondragover="onDropTargetDragOver(event)" ondragleave="onDropTargetDragLeave(event)"
                                ondrop="onSlotDrop(event, ${staff.id}, '${data.date}', '${t}')"></div>`;
                    });

                    blocks.forEach(b => {
                        const diffMinutes = b.start_minutes - dayStartMinutes;
                        // .cal-block is positioned inside .cal-slots, which already
                        // starts below the 84px sticky header in normal flow - adding
                        // HEADER_H here would double-count it and draw the block too low.
                        const top = diffMinutes * PPM;
                        const height = Math.max((b.end_minutes - b.start_minutes) * PPM, 30);
                        html += `<div class="cal-block" style="top:${top}px;height:${height}px" title="${b.reason}"
                                ondragover="onDropTargetDragOver(event)" ondragleave="onDropTargetDragLeave(event)"
                                ondrop="onSlotDrop(event, ${staff.id}, '${data.date}', '${b.start}')"
                                onclick="event.stopPropagation(); removeBlock(${b.id})">
                                🚫 ${b.reason}
                            </div>`;
                    });

                    apts.forEach(a => {
                        const color = data.status_colors[a.status] || '#c9a39a';
                        const diffMinutes = a.start_minutes - dayStartMinutes;
                        // Same reasoning as .cal-block above: .cal-slots already sits
                        // below the header, so this must not add HEADER_H again.
                        const top = diffMinutes * PPM;
                        const height = Math.max(a.duration * PPM, a.combo_name ? 58 : 46);
                        const displaySvcLabel = a.is_combo_tbd ? 'Services TBD in Salon' : (a.decide_in_salon ? 'Decide in Salon' : a.service_name);
                        const canDragOrResize = CAN_EDIT_BOOKINGS && a.status !== 'completed' && a.status !== 'cancelled';

                        html += `<div class="cal-appt status-${a.status}" data-id="${a.id}" data-staff-id="${staff.id}" data-start="${a.start}" data-duration="${a.duration}"
                                draggable="${canDragOrResize ? 'true' : 'false'}"
                                ondragstart="onApptDragStart(event, ${a.id}, ${a.start_minutes})" ondragend="onApptDragEnd(event)"
                                ondragover="onDropTargetDragOver(event)" ondragleave="onDropTargetDragLeave(event)"
                                ondrop="onSlotDrop(event, ${staff.id}, '${data.date}', '${a.start}')"
                                onclick="event.stopPropagation(); openDrawer(${a.id})"
                                style="top:${top}px;height:${height}px;background:${tint(color, .14)};border-left-color:${color};color:${color};">
                                <span class="cust">${escapeHtml(a.customer_name || 'Walk-in')}</span>
                                ${a.combo_name ? `<span class="cal-combo-badge">${escapeHtml(a.combo_name)}</span>` : ''}
                                <span class="svc">${escapeHtml(displaySvcLabel)}</span>
                                <span class="svc cal-appt-time-label">${to12Hour(a.start)} - ${to12Hour(a.end)}</span>
                                ${a.has_upsell ? `<span class="upsell-flag" title="Upsell by ${escapeHtml(a.upsell_staff_names)}">⬆ Upsell${a.upsell_staff_names ? ' · ' + escapeHtml(a.upsell_staff_names) : ''}</span>` : ''}
                                ${canDragOrResize ? `<div class="cal-appt-resize-handle" title="Drag to resize duration" onmousedown="onApptResizeStart(event, ${a.id}, ${staff.id}, '${data.date}', '${a.start}', ${a.duration}, ${PPM})"></div>` : ''}
                            </div>`;
                    });
                }

                html += `</div></div>`;
            });

            html += `</div>`;
            document.getElementById('calendarBody').innerHTML = html;

            positionNowLine(data, dayStartMinutes, HEADER_H, PPM);
        }

        function positionNowLine(data, dayStartMinutes, HEADER_H, PPM) {
            const grid = document.querySelector('.cal-day-grid');
            if (!grid) return;

            const existing = grid.querySelector('.cal-now-line');
            if (existing) existing.remove();

            if (!data.is_today) return;

            const now = new Date();
            const nowMinutes = now.getHours() * 60 + now.getMinutes();
            if (nowMinutes < dayStartMinutes) return;

            const top = HEADER_H + (nowMinutes - dayStartMinutes) * PPM;
            const line = document.createElement('div');
            line.className = 'cal-now-line';
            line.style.top = top + 'px';
            grid.appendChild(line);
        }

        function onSlotClick(e, staffId, date, time) {
            if (!CAN_EDIT_BOOKINGS) return;
            const popover = document.getElementById('slotPopover');
            const rect = e.target.getBoundingClientRect();

            document.getElementById('slotPopoverTime').textContent = to12Hour(time);

            document.getElementById('slotPopoverAddAppt').onclick = function() {
                hideSlotPopover();
                openCalendarBookModal({
                    datetime: `${date}T${time}`,
                    branch: state.branch,
                    staffId: staffId
                });
            };

            document.getElementById('slotPopoverAddBlock').onclick = function() {
                hideSlotPopover();
                document.getElementById('blockStaffSelect').value = staffId;
                document.getElementById('blockDate').value = date;
                document.getElementById('blockStartTime').value = time;
                const [h, m] = time.split(':').map(Number);
                const endMinutes = h * 60 + m + 30;
                document.getElementById('blockEndTime').value =
                    String(Math.floor(endMinutes / 60) % 24).padStart(2, '0') + ':' + String(endMinutes % 60).padStart(2, '0');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('blockTimeModal')).show();
            };

            let left = rect.right + 8;
            if (left + 230 > window.innerWidth) left = rect.left - 228;
            popover.style.left = left + 'px';
            popover.style.top = rect.top + 'px';
            popover.style.display = 'block';

            setTimeout(() => document.addEventListener('click', outsideSlotPopoverClick), 0);
        }

        function hideSlotPopover() {
            document.getElementById('slotPopover').style.display = 'none';
            document.removeEventListener('click', outsideSlotPopoverClick);
        }

        function outsideSlotPopoverClick(e) {
            const popover = document.getElementById('slotPopover');
            if (!popover.contains(e.target)) {
                hideSlotPopover();
            }
        }

        function removeBlock(blockId) {
            if (!CAN_EDIT_BOOKINGS) return;
            if (!confirm('Remove this blocked time?')) return;
            fetch(`/staff-blocks/${blockId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(() => loadCalendar());
        }

        /* ---------------- WEEK / 3-DAY GRID (staff rows x day cols) ---------------- */
        function renderWeek(data) {
            const dayCount = data.days.length;
            let html = `<div class="cal-week-grid" style="grid-template-columns:180px repeat(${dayCount}, minmax(160px, 1fr))">`;

            html += `<div class="cal-week-corner"></div>`;
            data.days.forEach(d => {
                html += `<div class="cal-week-daycol ${d.is_today ? 'is-today' : ''}">
                    <div class="dow">${d.label}</div>
                    <div class="dnum">${d.day_num}</div>
                </div>`;
            });

            data.staffs.forEach(staff => {
                html += `<div class="cal-week-staffcell">
                    <img src="${staff.profile_picture}">
                    <div class="name">${staff.name}</div>
                </div>`;

                data.days.forEach(day => {
                    const offReason = (data.off[staff.id] || {})[day.date];
                    const dayAppts = ((data.appointments[staff.id] || {})[day.date]) || [];
                    const dayBlocks = ((data.blocks && data.blocks[staff.id]) || {})[day.date] || [];

                    html += `<div class="cal-week-daycell ${offReason ? 'is-off' : ''}"
                        onclick="onWeekCellClick(event, ${staff.id}, '${day.date}')"
                        ondragover="onDropTargetDragOver(event)" ondragleave="onDropTargetDragLeave(event)"
                        ondrop="onWeekCellDrop(event, ${staff.id}, '${day.date}')">`;

                    if (offReason) {
                        html += `<div class="cal-week-off-label">${offReason}</div>`;
                    } else {
                        if (!dayAppts.length && !dayBlocks.length) {
                            html += `<div class="cal-empty-hint">+</div>`;
                        }

                        dayBlocks.forEach(b => {
                            html += `<div class="cal-chip-block" onclick="event.stopPropagation(); removeBlock(${b.id})">🚫 ${b.start}-${b.end} ${b.reason}</div>`;
                        });

                        dayAppts.forEach(a => {
                            const color = data.status_colors[a.status] || '#c9a39a';
                            const displaySvcLabel = a.is_combo_tbd ? 'Services TBD in Salon' : (a.decide_in_salon ? 'Decide in Salon' : a.service_name);
                            html += `<div class="cal-chip status-${a.status}" data-id="${a.id}"
                                draggable="${(!CAN_EDIT_BOOKINGS || a.status === 'completed' || a.status === 'cancelled') ? 'false' : 'true'}"
                                ondragstart="event.stopPropagation(); onApptDragStart(event, ${a.id}, ${a.start_minutes})"
                                ondragend="onApptDragEnd(event)"
                                onclick="event.stopPropagation(); openDrawer(${a.id})"
                                style="background:${tint(color, .14)};border-left-color:${color};color:${color};">
                                <strong>${a.time} · ${escapeHtml(a.customer_name || 'Walk-in')}</strong>
                                ${a.combo_name ? `<div><span class="cal-combo-badge">${escapeHtml(a.combo_name)}</span></div>` : ''}
                                ${escapeHtml(displaySvcLabel)}
                                ${a.has_upsell ? `<span class="upsell-flag">⬆ Upsell</span>` : ''}
                            </div>`;
                        });
                    }

                    html += `</div>`;
                });
            });

            html += `</div>`;
            document.getElementById('calendarBody').innerHTML = html;
        }

        function onWeekCellClick(e, staffId, date) {
            openCalendarBookModal({
                datetime: `${date}T10:00`,
                branch: state.branch,
                staffId: staffId
            });
        }

        /* ---------------- MONTH VIEW ---------------- */
        function renderMonth(data) {
            const dow = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            let html = `<div class="cal-month-grid">`;
            dow.forEach(d => html += `<div class="cal-month-dow">${d}</div>`);

            data.days.forEach(day => {
                const appts = data.appointments[day.date] || [];
                const cls = ['cal-month-cell'];
                if (!day.in_month) cls.push('out-month');
                if (day.is_today) cls.push('is-today');

                html += `<div class="${cls.join(' ')}" onclick="onMonthCellClick('${day.date}')">
                    <div class="cal-month-daynum">${day.day_num}</div>`;

                appts.slice(0, 3).forEach(a => {
                    const color = data.status_colors[a.status] || '#c9a39a';
                    html += `<div class="cal-month-chip status-${a.status}" data-id="${a.id}"
                        onclick="event.stopPropagation(); openDrawer(${a.id})"
                        style="background:${tint(color, .14)};border-left-color:${color};color:${color};">
                        ${a.time} ${escapeHtml(a.customer_name || 'Walk-in')}${a.combo_name ? ` · ${escapeHtml(a.combo_name)}` : (a.decide_in_salon ? ' · Decide in Salon' : '')}
                    </div>`;
                });

                if (appts.length > 3) {
                    html += `<div class="cal-month-more">+${appts.length - 3} more</div>`;
                }

                html += `</div>`;
            });

            html += `</div>`;
            document.getElementById('calendarBody').innerHTML = html;
        }

        function onMonthCellClick(date) {
            state.date = date;
            state.view = 'day';
            saveFilters();
            syncToolbar();
            loadCalendar();
        }

        /* ---------------- UNSCHEDULED QUEUE SIDEBAR ---------------- */
        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function renderUnscheduledQueue(bookings) {
            const listEl = document.getElementById('unscheduledQueueList');
            const badgeEl = document.getElementById('unscheduledCountBadge');
            if (!listEl || !badgeEl) return;

            const items = Array.isArray(bookings) ? bookings : [];
            badgeEl.textContent = items.length;

            if (!items.length) {
                listEl.innerHTML = `
                    <div class="cal-unscheduled-empty">
                        <i class="bx bx-calendar-check"></i>
                        No unscheduled bookings for this date.
                    </div>
                `;
                return;
            }

            listEl.innerHTML = items.map(b => `
                <div class="cal-unscheduled-card"
                    data-id="${b.id}"
                    data-services="${escapeHtml(b.service_name)}"
                    draggable="${CAN_EDIT_BOOKINGS ? 'true' : 'false'}"
                    ondragstart="onApptDragStart(event, ${b.id}, null, true)"
                    ondragend="onApptDragEnd(event)"
                    onclick="openDrawer(${b.id})">
                    <div class="cal-unscheduled-card-top">
                        <span class="unsched-customer">${escapeHtml(b.customer_name || 'Walk-in')}</span>
                        <i class="bx bx-grid-vertical unsched-grip"></i>
                    </div>
                    ${b.combo_name ? `<div class="mb-1"><span class="cal-combo-badge">${escapeHtml(b.combo_name)}</span></div>` : ''}
                    <div class="unsched-service">${escapeHtml(b.is_combo_tbd ? 'Services TBD in Salon' : (b.decide_in_salon ? 'Decide in Salon' : b.service_name))}</div>
                    <div class="unsched-meta">
                        <span class="unsched-contact"><i class="bx bx-phone"></i> ${escapeHtml(b.phone || 'No phone')}</span>
                        <span class="unsched-duration">${b.duration || 30}m</span>
                    </div>
                </div>
            `).join('');
        }

        document.getElementById('toggleUnscheduledSidebarBtn').addEventListener('click', function() {
            const sidebar = document.getElementById('unscheduledSidebar');
            const icon = document.getElementById('toggleUnscheduledIcon');
            const collapsed = sidebar.classList.toggle('is-collapsed');
            icon.className = collapsed ? 'bx bx-chevron-left' : 'bx bx-chevron-right';
        });

        /* ---------------- LOAD / NAV ---------------- */
        function loadCalendar() {
            const params = new URLSearchParams({
                view: state.view,
                date: state.date,
            });
            if (state.branch) params.append('branch', state.branch);
            if (state.staff_id) params.append('staff_id', state.staff_id);

            fetch(`{{ route('appointments.calendar_data') }}?${params.toString()}`)
                .then(r => r.json())
                .then(data => {
                    latestData = data;
                    renderLegend(data.status_colors);
                    renderUnscheduledQueue(data.pending_time_bookings || data.pendingTimeBookings || []);

                    if (data.view === 'month') {
                        document.getElementById('dateLabel').textContent = data.month_label;
                        renderMonth(data);
                    } else if (data.view === 'week' || data.view === '3day') {
                        document.getElementById('dateLabel').textContent =
                            `${fmtDateLabel(data.week_start)} – ${fmtDateLabel(data.week_end)}`;
                        renderWeek(data);
                    } else {
                        document.getElementById('dateLabel').textContent = fmtDateLabel(data.date);
                        renderDay(data);
                    }
                });
        }

        function setView(view) {
            state.view = view;
            saveFilters();
            syncToolbar();
            loadCalendar();
        }

        function shiftDate(delta) {
            const d = new Date(state.date + 'T00:00:00');
            if (state.view === 'month') {
                d.setMonth(d.getMonth() + delta);
            } else {
                const step = state.view === 'week' ? 7 : (state.view === '3day' ? 3 : 1);
                d.setDate(d.getDate() + delta * step);
            }
            state.date = toLocalISODate(d);
            saveFilters();
            syncToolbar();
            loadCalendar();
        }

        document.querySelectorAll('.cal-view-btn').forEach(btn => {
            btn.addEventListener('click', () => setView(btn.dataset.view));
        });
        document.getElementById('prevBtn').addEventListener('click', () => shiftDate(-1));
        document.getElementById('nextBtn').addEventListener('click', () => shiftDate(1));
        document.getElementById('todayBtn').addEventListener('click', () => {
            state.date = toLocalISODate(new Date());
            saveFilters();
            syncToolbar();
            loadCalendar();
        });
        document.getElementById('filterBranch').addEventListener('change', function() {
            state.branch = normalizeCalendarBranch(this.value) || DEFAULT_BRANCH;
            syncToolbar();
            saveFilters();
            loadCalendar();
        });
        document.getElementById('filterStaff').addEventListener('change', function() {
            state.staff_id = this.value;
            saveFilters();
            syncToolbar();
            loadCalendar();
        });
        document.getElementById('filterClearBtn').addEventListener('click', function() {
            // Location is a mandatory branch scope now, not a clearable
            // filter - only the team-member selection resets here.
            state.staff_id = '';
            saveFilters();
            syncToolbar();
            loadCalendar();
        });
        document.getElementById('zoomInBtn').addEventListener('click', () => {
            const idx = ZOOM_LEVELS.indexOf(state.zoom);
            if (idx < ZOOM_LEVELS.length - 1) {
                state.zoom = ZOOM_LEVELS[idx + 1];
                saveFilters();
                loadCalendar();
            }
        });
        document.getElementById('zoomOutBtn').addEventListener('click', () => {
            const idx = ZOOM_LEVELS.indexOf(state.zoom);
            if (idx > 0) {
                state.zoom = ZOOM_LEVELS[idx - 1];
                saveFilters();
                loadCalendar();
            }
        });
        document.getElementById('addNewAppointment').addEventListener('click', function(e) {
            e.preventDefault();
            if (!CAN_EDIT_BOOKINGS) return;
            const now = new Date();
            const pad = n => n.toString().padStart(2, '0');
            openCalendarBookModal({
                datetime: `${state.date}T${pad(now.getHours())}:00`,
                branch: state.branch,
                staffId: state.staff_id || null
            });
        });

        function openNewUnscheduledModal(e) {
            if (e) e.preventDefault();
            if (!CAN_EDIT_BOOKINGS) return;
            openCalendarBookModal({
                date: state.date,
                unscheduled: true,
                branch: state.branch,
                staffId: null
            });
        }
        document.getElementById('addNewUnscheduledAppointment').addEventListener('click', openNewUnscheduledModal);
        document.getElementById('sidebarAddUnscheduledBtn').addEventListener('click', openNewUnscheduledModal);

        syncToolbar();
        loadCalendar();

        // Keep the live "now" line accurate without a full reload.
        nowLineTimer = setInterval(() => {
            if (latestData && latestData.view === 'day' && latestData.is_today) {
                const [sh, sm] = latestData.ui_start.split(':').map(Number);
                positionNowLine(latestData, sh * 60 + sm, 74, state.zoom / 30);
            }
        }, 30000);

        /* ---------------- TOAST NOTIFICATIONS (FLOATING TOP-RIGHT, 5S AUTO-DISMISS) ---------------- */
        function showCalToast(message, type, reason) {
            const container = document.getElementById('calToastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = 'cal-toast' + (type === 'error' ? ' error' : '');
            if (reason) {
                toast.dataset.reason = reason;
            }
            toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
            toast.innerHTML = `
                <span class="cal-toast-dot" aria-hidden="true"></span>
                <span class="cal-toast-text">${escapeHtml(message)}</span>
                <button type="button" class="toast-close" aria-label="Close">&times;</button>
            `;
            let dismissed = false;
            const removeToast = () => {
                if (dismissed) return;
                dismissed = true;
                toast.remove();
            };
            toast.querySelector('.toast-close').addEventListener('click', removeToast);
            container.appendChild(toast);
            setTimeout(removeToast, 5000);
        }
        window.showCalToast = showCalToast;
        window.refreshCalendarView = loadCalendar;

        function extractApiErrorMessage(data, fallback) {
            if (!data || typeof data !== 'object') return fallback;
            if (data.message) return data.message;
            if (data.errors && typeof data.errors === 'object') {
                const firstField = Object.values(data.errors)[0];
                if (Array.isArray(firstField) && firstField[0]) return firstField[0];
                if (typeof firstField === 'string') return firstField;
            }
            return fallback;
        }

        function showDrawerConflict(message, reason) {
            const banner = document.getElementById('drawerConflictBanner');
            const textEl = document.getElementById('drawerConflictText');
            if (!banner || !textEl) {
                showCalToast(message, 'error', reason);
                return;
            }
            textEl.textContent = message || 'Unable to update booking due to a schedule conflict.';
            banner.dataset.reason = reason || '';
            banner.classList.remove('d-none');
        }

        function clearDrawerConflict() {
            const banner = document.getElementById('drawerConflictBanner');
            const textEl = document.getElementById('drawerConflictText');
            if (!banner) return;
            banner.classList.add('d-none');
            banner.dataset.reason = '';
            if (textEl) textEl.textContent = '';
        }

        const drawerConflictDismissBtn = document.getElementById('drawerConflictDismiss');
        if (drawerConflictDismissBtn) {
            drawerConflictDismissBtn.addEventListener('click', clearDrawerConflict);
        }

        /* ---------------- APPOINTMENT DRAWER ---------------- */
        let selectedAppointmentId = null;
        let drawerInstance = null;
        let lastDrawerData = null;

        const STATUS_LABELS = {
            pending: 'Pending', arrived: 'Arrived', in_progress: 'In Progress',
            completed: 'Completed', no_show: 'No Show', cancelled: 'Cancelled'
        };

        function openDrawer(appointmentId) {
            selectedAppointmentId = appointmentId;
            clearDrawerConflict();
            closeDrawerComboSubView();

            document.getElementById('drawerCustomer').textContent = 'Loading…';
            document.getElementById('drawerEditCustomerNameBtn').classList.add('d-none');
            document.getElementById('drawerCustomerEditWrap').classList.add('d-none');
            document.getElementById('drawerCustomerEditWrap').classList.remove('d-flex');
            document.getElementById('drawerCustomerNameRow').classList.remove('d-none');
            document.getElementById('drawerComboPackagesList').innerHTML = '';
            document.getElementById('drawerServices').innerHTML = '';
            document.getElementById('drawerUpsells').innerHTML = '';
            document.getElementById('drawerStatusRow').innerHTML = '';
            document.getElementById('drawerProfileLink').classList.add('d-none');
            document.getElementById('drawerAllergyWrap').classList.add('d-none');
            document.getElementById('drawerVisitsSection').classList.add('d-none');
            document.getElementById('drawerRescheduleForm').classList.add('d-none');
            showDrawerServicesView('list');
            showDrawerUpsellsView('list');

            drawerInstance = bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('apptDrawer'));
            drawerInstance.show();

            fetch(`/appointments/${appointmentId}`)
                .then(r => r.json())
                .then(renderDrawer);
        }

        function renderDrawer(a) {
            lastDrawerData = a;
            const color = (latestData && latestData.status_colors[a.status]) || '#c9a39a';
            const isPaid = Boolean(a.is_paid);
            const isCancelledOrNoShow = ['cancelled', 'no_show'].includes(a.status);
            const isFinal = isCancelledOrNoShow || (a.status === 'completed' && isPaid);

            document.getElementById('drawerCustomer').textContent = a.customer_name || 'Walk-in';
            document.getElementById('drawerEditCustomerNameBtn').classList.toggle('d-none', !CAN_EDIT_BOOKINGS || !a.customer_id);
            document.getElementById('drawerCustomerEditWrap').classList.add('d-none');
            document.getElementById('drawerCustomerEditWrap').classList.remove('d-flex');
            document.getElementById('drawerCustomerNameRow').classList.remove('d-none');
            document.getElementById('drawerPhone').textContent = a.phone;
            document.getElementById('drawerDatetime').textContent = a.appointment_datetime;
            document.getElementById('drawerBranch').textContent = a.branch;
            document.getElementById('drawerStaff').textContent = a.staff_name;

            const pill = document.getElementById('drawerStatusPill');
            pill.textContent = STATUS_LABELS[a.status] || a.status;
            pill.style.background = color;

            // Allergy / staff alert
            const allergyWrap = document.getElementById('drawerAllergyWrap');
            if (a.customer_allergies) {
                document.getElementById('drawerAllergyText').textContent = a.customer_allergies;
                allergyWrap.classList.remove('d-none');
            } else {
                allergyWrap.classList.add('d-none');
            }

            // Recent visit history
            const visitsSection = document.getElementById('drawerVisitsSection');
            if (a.recent_visits && a.recent_visits.length) {
                document.getElementById('drawerRecentVisits').innerHTML = a.recent_visits.map(v => `
                    <div class="visit-row">
                        <span>${v.date} · ${v.service_name}</span>
                        <span class="badge" style="background:${(latestData && latestData.status_colors[v.status]) || '#c9a39a'}">${STATUS_LABELS[v.status] || v.status}</span>
                    </div>
                `).join('');
                visitsSection.classList.remove('d-none');
            } else {
                visitsSection.classList.add('d-none');
            }

            renderDrawerServiceList(a, isFinal);
            showDrawerServicesView('list');
            document.getElementById('drawerAddServiceBtn').classList.toggle('d-none', isFinal || !CAN_EDIT_BOOKINGS);

            renderDrawerUpsellList(a, isFinal);
            showDrawerUpsellsView('list');
            document.getElementById('drawerAddUpsellBtn').classList.toggle('d-none', isFinal || !CAN_EDIT_BOOKINGS);

            if (a.profile_url) {
                const link = document.getElementById('drawerProfileLink');
                link.href = a.profile_url;
                link.classList.remove('d-none');
            }

            // Status-progression button (Mark Arrived / Start Service)
            const statusRow = document.getElementById('drawerStatusRow');
            statusRow.innerHTML = '';
            const nextStep = {
                pending: { label: '🟣 Mark Arrived', status: 'arrived' },
                arrived: { label: '🟠 Start Service', status: 'in_progress' },
            }[a.status];
            if (nextStep && CAN_EDIT_BOOKINGS) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-outline-primary flex-grow-1';
                btn.textContent = nextStep.label;
                btn.onclick = () => updateDrawerStatus(nextStep.status);
                statusRow.appendChild(btn);
            }

            // Quick actions dropdown (Reschedule / No show / Cancel)
            document.getElementById('drawerQuickActionsWrap').classList.toggle('d-none', isFinal || !CAN_EDIT_BOOKINGS);
            document.getElementById('drawerNoShowBtn').classList.toggle('disabled', isFinal);
            document.getElementById('drawerCancelBtn').classList.toggle('disabled', isFinal);

            // Checkout — stays visible whenever the appointment is not cancelled/no_show and not yet paid
            const checkoutBtn = document.getElementById('drawerCheckoutBtn');
            checkoutBtn.href = a.payment_url;
            checkoutBtn.classList.toggle('d-none', isCancelledOrNoShow || isPaid || !CAN_EDIT_FINANCE);

            // Reschedule form & drawer staff scoping
            const drawerBranchKey = normalizeCalendarBranch(a.branch_raw) || state.branch;
            document.getElementById('drawerRescheduleBranch').value = drawerBranchKey;
            document.getElementById('drawerRescheduleDate').value = a.date;
            document.getElementById('drawerRescheduleTime').value = a.time;
            scopeSelectOptionsToBranch(document.getElementById('drawerRescheduleStaff'), drawerBranchKey, a.staff_id);
            scopeSelectOptionsToBranch(document.getElementById('upsellStaff'), drawerBranchKey);
            scopeSelectOptionsToBranch(document.getElementById('editSvcStaff'), drawerBranchKey);
            if (a.staff_id) {
                document.getElementById('drawerRescheduleStaff').value = a.staff_id;
            } else {
                document.getElementById('drawerRescheduleStaff').value = '';
            }
        }

        (function initDrawerCustomerRename() {
            const editBtn = document.getElementById('drawerEditCustomerNameBtn');
            const nameRow = document.getElementById('drawerCustomerNameRow');
            const editWrap = document.getElementById('drawerCustomerEditWrap');
            const nameInput = document.getElementById('drawerCustomerNameInput');
            const saveBtn = document.getElementById('drawerSaveCustomerNameBtn');
            const cancelBtn = document.getElementById('drawerCancelCustomerNameBtn');

            if (!editBtn || !editWrap || !nameInput) return;

            function openEdit() {
                if (!lastDrawerData || !lastDrawerData.customer_id) return;
                nameInput.value = lastDrawerData.customer_name || '';
                nameRow.classList.add('d-none');
                editWrap.classList.remove('d-none');
                editWrap.classList.add('d-flex');
                nameInput.focus();
                nameInput.select();
            }

            function closeEdit() {
                editWrap.classList.add('d-none');
                editWrap.classList.remove('d-flex');
                nameRow.classList.remove('d-none');
            }

            function submitEdit() {
                if (!lastDrawerData || !lastDrawerData.customer_id) return;
                const trimmed = nameInput.value.trim();
                if (trimmed.length < 2) {
                    showCalToast('Client name must be at least 2 characters.', 'error');
                    nameInput.focus();
                    return;
                }

                saveBtn.disabled = true;
                fetch(`/enhanced-calendar/clients/${encodeURIComponent(lastDrawerData.customer_id)}/update-name`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ name: trimmed })
                })
                .then(async r => {
                    const data = await r.json().catch(() => ({}));
                    if (!r.ok || !data.success) {
                        const msg = (data.errors && data.errors.name && data.errors.name[0])
                            || data.message
                            || 'Could not update client name.';
                        throw new Error(msg);
                    }
                    lastDrawerData.customer_name = data.customer.name;
                    document.getElementById('drawerCustomer').textContent = data.customer.name;
                    closeEdit();
                    showCalToast('Client name updated');
                    loadCalendar();
                })
                .catch(err => {
                    showCalToast(err.message || 'Could not update client name.', 'error');
                })
                .finally(() => {
                    saveBtn.disabled = false;
                });
            }

            editBtn.addEventListener('click', openEdit);
            cancelBtn.addEventListener('click', closeEdit);
            saveBtn.addEventListener('click', submitEdit);
            nameInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitEdit();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    closeEdit();
                }
            });
        })();

        /* ---------------- DRAWER: SERVICE LIST / ADD / EDIT ---------------- */
        let editingServiceLineId = null;

        function showDrawerServicesView(view) {
            document.getElementById('drawerServicesListView').classList.toggle('d-none', view !== 'list');
            document.getElementById('drawerServicesAddView').classList.toggle('d-none', view !== 'add');
            document.getElementById('drawerServicesEditView').classList.toggle('d-none', view !== 'edit');
        }

        let drawerComboDrafts = {};
        let activeDrawerComboConfig = null;

        function closeDrawerComboSubView() {
            activeDrawerComboConfig = null;
            document.getElementById('drawerMainHeader').classList.remove('d-none');
            document.getElementById('drawerMainBody').classList.remove('d-none');
            document.getElementById('drawerMainFooter').classList.remove('d-none');
            document.getElementById('drawerComboSubHeader').classList.add('d-none');
            document.getElementById('drawerComboSubView').classList.add('d-none');
            document.getElementById('drawerComboSubFooter').classList.add('d-none');
        }

        function openDrawerComboSubView(clientPackageId) {
            const base = drawerComboDrafts[clientPackageId];
            if (!base) return;

            clearDrawerConflict();
            activeDrawerComboConfig = {
                clientPackageId: Number(clientPackageId),
                pkg: base.pkg,
                todayIds: [...base.todayIds],
                pendingIds: [...base.pendingIds],
                staffMap: { ...base.staffMap },
                branchStaffs: base.branchStaffs || []
            };

            const searchEl = document.getElementById('drawerComboSubSearch');
            if (searchEl) searchEl.value = '';

            document.getElementById('drawerMainHeader').classList.add('d-none');
            document.getElementById('drawerMainBody').classList.add('d-none');
            document.getElementById('drawerMainFooter').classList.add('d-none');
            document.getElementById('drawerComboSubHeader').classList.remove('d-none');
            document.getElementById('drawerComboSubView').classList.remove('d-none');
            document.getElementById('drawerComboSubFooter').classList.remove('d-none');

            renderDrawerComboSubView();
        }

        function renderDrawerComboSubView() {
            if (!activeDrawerComboConfig) return;
            const cfg = activeDrawerComboConfig;
            const pkg = cfg.pkg;
            const pool = Array.isArray(pkg.pool_services) ? pkg.pool_services : [];
            const qtyIncluded = Number(pkg.quantity_included) || 0;
            const usedEarlier = Number(pkg.redeemed_elsewhere_count) || 0;
            const maxForThisVisit = Math.max(0, qtyIncluded - usedEarlier);
            const visitSelected = cfg.todayIds.length + cfg.pendingIds.length;
            const totalSelected = visitSelected + usedEarlier;
            const capReached = visitSelected >= maxForThisVisit && maxForThisVisit > 0;

            document.getElementById('drawerComboSubTitle').textContent = pkg.combo_name || 'Combo Package';
            const counterEl = document.getElementById('drawerComboSubCounter');
            counterEl.textContent = `${totalSelected} / ${qtyIncluded} Selected`;
            counterEl.classList.toggle('is-full', capReached);

            const capHintEl = document.getElementById('drawerComboSubCapHint');
            if (capReached && pool.length > qtyIncluded) {
                capHintEl.textContent = `Max ${qtyIncluded} of ${pool.length} services selected — tap an active pill to swap`;
                capHintEl.classList.remove('d-none');
            } else {
                capHintEl.textContent = '';
                capHintEl.classList.add('d-none');
            }

            const todayDuration = pool
                .filter(s => cfg.todayIds.includes(Number(s.id)))
                .reduce((sum, s) => sum + (Number(s.duration) || 30), 0);

            const holdSummaryEl = document.getElementById('drawerComboSubHoldSummary');
            if (cfg.todayIds.length > 0) {
                const pendingPart = cfg.pendingIds.length > 0 ? ` · ${cfg.pendingIds.length} Pending` : '';
                holdSummaryEl.textContent = `${todayDuration}m salon time (${cfg.todayIds.length} Today${pendingPart})`;
            } else if (cfg.pendingIds.length > 0) {
                holdSummaryEl.textContent = `60m hold · ${cfg.pendingIds.length} Pending`;
            } else {
                holdSummaryEl.textContent = '60m hold · Decide in Salon';
            }
            document.getElementById('drawerComboSubPriceSummary').textContent = `${Number(pkg.price_paid).toFixed(2)} QAR locked package`;

            const query = (document.getElementById('drawerComboSubSearch').value || '').trim().toLowerCase();
            const filtered = query
                ? pool.filter(svc => String(svc.name || '').toLowerCase().includes(query))
                : pool;

            const listEl = document.getElementById('drawerComboSubList');
            if (!filtered.length) {
                listEl.innerHTML = `<div class="text-muted small py-4 text-center">No matching services in this combo.</div>`;
                return;
            }

            listEl.innerHTML = filtered.map(svc => {
                const sid = Number(svc.id);
                const dur = Number(svc.duration) || 30;
                if (svc.state === 'redeemed_elsewhere') {
                    return `
                        <div class="drawer-combo-svc-row is-dimmed">
                            <div>
                                <div class="drawer-combo-svc-name">${escapeHtml(svc.name)}</div>
                                <div class="drawer-combo-svc-dur">${dur} min</div>
                            </div>
                            <span class="drawer-combo-pill-btn">Redeemed</span>
                        </div>`;
                }

                const isToday = cfg.todayIds.includes(sid);
                const isPending = !isToday && cfg.pendingIds.includes(sid);
                const isUnselected = !isToday && !isPending;
                const disableNewPick = isUnselected && (capReached || maxForThisVisit === 0);

                const currentStaff = cfg.staffMap[sid] || '';
                const staffSelectHtml = isToday && cfg.branchStaffs.length ? `
                    <select class="form-select form-select-sm drawer-combo-staff-sel mt-1"
                        data-svc="${sid}"
                        style="max-width: 156px; font-size: 11px; padding: 2px 24px 2px 8px;">
                        <option value="">Main stylist</option>
                        ${cfg.branchStaffs.map(st => `<option value="${st.id}" ${String(currentStaff) === String(st.id) ? 'selected' : ''}>${escapeHtml(st.name)}</option>`).join('')}
                    </select>` : '';

                return `
                    <div class="drawer-combo-svc-row ${disableNewPick ? 'is-dimmed' : ''}">
                        <div class="me-2">
                            <div class="drawer-combo-svc-name">${escapeHtml(svc.name)}</div>
                            <div class="drawer-combo-svc-dur">${dur} min</div>
                            ${staffSelectHtml}
                        </div>
                        <div class="drawer-combo-seg">
                            <button type="button"
                                class="drawer-combo-pill-btn drawer-combo-today-toggle ${isToday ? 'is-today' : ''}"
                                data-svc="${sid}"
                                ${disableNewPick ? 'disabled' : ''}>Today</button>
                            <button type="button"
                                class="drawer-combo-pill-btn drawer-combo-pending-toggle ${isPending ? 'is-pending' : ''}"
                                data-svc="${sid}"
                                ${disableNewPick ? 'disabled' : ''}>Pending</button>
                        </div>
                    </div>`;
            }).join('');

            listEl.querySelectorAll('.drawer-combo-today-toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!activeDrawerComboConfig) return;
                    clearDrawerConflict();
                    const sid = Number(btn.dataset.svc);
                    if (cfg.todayIds.includes(sid)) {
                        cfg.todayIds = cfg.todayIds.filter(id => id !== sid);
                        delete cfg.staffMap[sid];
                    } else {
                        const wasPending = cfg.pendingIds.includes(sid);
                        if (wasPending) {
                            cfg.pendingIds = cfg.pendingIds.filter(id => id !== sid);
                        }
                        if (cfg.todayIds.length + cfg.pendingIds.length < maxForThisVisit) {
                            cfg.todayIds.push(sid);
                        }
                    }
                    renderDrawerComboSubView();
                });
            });

            listEl.querySelectorAll('.drawer-combo-pending-toggle').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!activeDrawerComboConfig) return;
                    clearDrawerConflict();
                    const sid = Number(btn.dataset.svc);
                    if (cfg.pendingIds.includes(sid)) {
                        cfg.pendingIds = cfg.pendingIds.filter(id => id !== sid);
                    } else {
                        const wasToday = cfg.todayIds.includes(sid);
                        if (wasToday) {
                            cfg.todayIds = cfg.todayIds.filter(id => id !== sid);
                            delete cfg.staffMap[sid];
                        }
                        if (cfg.todayIds.length + cfg.pendingIds.length < maxForThisVisit) {
                            cfg.pendingIds.push(sid);
                        }
                    }
                    renderDrawerComboSubView();
                });
            });

            listEl.querySelectorAll('.drawer-combo-staff-sel').forEach(sel => {
                sel.addEventListener('change', () => {
                    if (!activeDrawerComboConfig) return;
                    clearDrawerConflict();
                    const sid = Number(sel.dataset.svc);
                    if (sel.value) {
                        cfg.staffMap[sid] = Number(sel.value);
                    } else {
                        delete cfg.staffMap[sid];
                    }
                });
            });
        }

        function persistDrawerComboConfig(cfg, btnEl) {
            if (!cfg || !selectedAppointmentId) return;

            clearDrawerConflict();
            const applyBtn = document.getElementById('drawerComboSubApplyBtn');
            const skipBtn = document.getElementById('drawerComboSubSkipBtn');
            if (btnEl) btnEl.disabled = true;
            if (applyBtn) applyBtn.disabled = true;
            if (skipBtn) skipBtn.disabled = true;

            fetch(`/appointments/${selectedAppointmentId}/combo-services`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        client_package_id: cfg.clientPackageId,
                        today_service_ids: cfg.todayIds,
                        pending_service_ids: cfg.pendingIds,
                        service_staff: cfg.staffMap
                    })
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        clearDrawerConflict();
                        showCalToast(data.message || 'Combo services updated');
                        closeDrawerComboSubView();
                        loadCalendar();
                        fetch(`/appointments/${selectedAppointmentId}`).then(r => r.json()).then(renderDrawer);
                    } else {
                        showDrawerConflict(extractApiErrorMessage(data, 'Could not update combo services.'), data && data.reason);
                    }
                })
                .catch(() => showDrawerConflict('Could not update combo services.', 'network'))
                .finally(() => {
                    if (btnEl) btnEl.disabled = false;
                    if (applyBtn) applyBtn.disabled = false;
                    if (skipBtn) skipBtn.disabled = false;
                });
        }

        (function initDrawerComboSubViewEvents() {
            const backBtn = document.getElementById('drawerComboSubBackBtn');
            const searchEl = document.getElementById('drawerComboSubSearch');
            const skipBtn = document.getElementById('drawerComboSubSkipBtn');
            const applyBtn = document.getElementById('drawerComboSubApplyBtn');

            if (backBtn) {
                backBtn.addEventListener('click', () => {
                    clearDrawerConflict();
                    closeDrawerComboSubView();
                });
            }
            if (searchEl) {
                searchEl.addEventListener('input', () => renderDrawerComboSubView());
            }
            if (skipBtn) {
                skipBtn.addEventListener('click', () => {
                    if (!activeDrawerComboConfig) return;
                    activeDrawerComboConfig.todayIds = [];
                    activeDrawerComboConfig.pendingIds = [];
                    activeDrawerComboConfig.staffMap = {};
                    persistDrawerComboConfig(activeDrawerComboConfig, skipBtn);
                });
            }
            if (applyBtn) {
                applyBtn.addEventListener('click', () => {
                    if (!activeDrawerComboConfig) return;
                    persistDrawerComboConfig(activeDrawerComboConfig, applyBtn);
                });
            }
        })();

        function renderDrawerComboPackages(a, isFinal) {
            const comboWrap = document.getElementById('drawerComboPackagesList');
            const pkgs = Array.isArray(a.combo_packages) ? a.combo_packages : [];
            if (!pkgs.length) {
                comboWrap.innerHTML = '';
                drawerComboDrafts = {};
                return;
            }

            const drawerBranchKey = normalizeCalendarBranch(a.branch_raw) || state.branch;
            const branchStaffs = DRAWER_STAFFS_CATALOG.filter(st => !st.branch || st.branch === 'both' || normalizeCalendarBranch(st.branch) === drawerBranchKey);

            drawerComboDrafts = {};
            pkgs.forEach(pkg => {
                const todayIds = [];
                const pendingIds = [];
                const staffMap = {};
                (pkg.pool_services || []).forEach(svc => {
                    if (svc.state === 'today') {
                        todayIds.push(Number(svc.id));
                        if (svc.staff_id) staffMap[svc.id] = Number(svc.staff_id);
                    } else if (svc.state === 'pending') {
                        pendingIds.push(Number(svc.id));
                    }
                });
                drawerComboDrafts[pkg.client_package_id] = {
                    pkg,
                    todayIds,
                    pendingIds,
                    staffMap,
                    branchStaffs
                };
            });

            drawDrawerComboDrafts(isFinal);
        }

        function drawDrawerComboDrafts(isFinal) {
            const comboWrap = document.getElementById('drawerComboPackagesList');
            const drafts = Object.values(drawerComboDrafts);
            if (!drafts.length) {
                comboWrap.innerHTML = '';
                return;
            }

            const canEditCombo = !isFinal && CAN_EDIT_BOOKINGS;

            comboWrap.innerHTML = drafts.map(draft => {
                const pkg = draft.pkg;
                const qtyIncluded = Number(pkg.quantity_included) || 0;
                const usedEarlier = Number(pkg.redeemed_elsewhere_count) || 0;
                const pool = Array.isArray(pkg.pool_services) ? pkg.pool_services : [];

                const pickedChips = [];
                pool.forEach(svc => {
                    const sid = Number(svc.id);
                    if (draft.todayIds.includes(sid)) {
                        pickedChips.push(`<span class="drawer-combo-picked-item">${escapeHtml(svc.name)} <span class="state-today">(Today)</span></span>`);
                    } else if (draft.pendingIds.includes(sid)) {
                        pickedChips.push(`<span class="drawer-combo-picked-item">${escapeHtml(svc.name)} <span class="state-pending">(Pending)</span></span>`);
                    }
                });
                if (usedEarlier > 0) {
                    pickedChips.push(`<span class="drawer-combo-picked-item" style="color:#9f8c86;">${usedEarlier} redeemed</span>`);
                }

                const totalSelected = draft.todayIds.length + draft.pendingIds.length + usedEarlier;
                let secondRowHtml = '';

                if (totalSelected === 0) {
                    secondRowHtml = `
                        <div class="drawer-combo-trigger">
                            <span class="drawer-combo-empty-hint">
                                0 / ${qtyIncluded} Selected &middot; Decide in Salon${canEditCombo ? ' &mdash; Click to choose services' : ''}
                            </span>
                            ${canEditCombo ? `<span class="drawer-combo-chevron" aria-hidden="true">&rsaquo;</span>` : ''}
                        </div>`;
                } else {
                    secondRowHtml = `
                        <div class="drawer-combo-trigger">
                            <div class="drawer-combo-picked-list">
                                ${pickedChips.join('<span class="drawer-combo-picked-sep">&middot;</span>')}
                            </div>
                            ${canEditCombo ? `
                                <span class="drawer-combo-edit-cta">
                                    Edit services (${totalSelected}/${qtyIncluded}) <span class="drawer-combo-chevron" aria-hidden="true">&rsaquo;</span>
                                </span>` : ''}
                        </div>`;
                }

                return `
                    <div class="drawer-combo-card ${canEditCombo ? 'is-clickable' : ''}"
                        data-pkg-id="${pkg.client_package_id}"
                        ${canEditCombo ? `data-open-combo-pkg="${pkg.client_package_id}" role="button" tabindex="0"` : ''}>
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <span class="drawer-combo-eyebrow">COMBO</span>
                                <span class="drawer-combo-title text-truncate">${escapeHtml(pkg.combo_name)}</span>
                            </div>
                            <span class="drawer-combo-price">${Number(pkg.price_paid).toFixed(2)} QAR</span>
                        </div>
                        ${secondRowHtml}
                    </div>`;
            }).join('');

            if (canEditCombo) {
                comboWrap.querySelectorAll('[data-open-combo-pkg]').forEach(card => {
                    card.addEventListener('click', () => {
                        openDrawerComboSubView(Number(card.dataset.openComboPkg));
                    });
                    card.addEventListener('keydown', e => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            openDrawerComboSubView(Number(card.dataset.openComboPkg));
                        }
                    });
                });
            }
        }

        function renderDrawerServiceList(a, isFinal) {
            renderDrawerComboPackages(a, isFinal);

            const hasComboCards = Array.isArray(a.combo_packages) && a.combo_packages.length > 0;
            const comboPkgIds = new Set((a.combo_packages || []).map(p => Number(p.client_package_id)));

            // Exclude lines already managed inside a parent Combo card above so
            // services are never shown twice in the drawer; standalone services
            // (or redemptions from an older visit's package not rendered as a
            // combo card) still render cleanly with a subtle Combo Name pill.
            const standaloneServices = (a.services || []).filter(s => {
                if (!hasComboCards) return true;
                if (s.client_package_id && comboPkgIds.has(Number(s.client_package_id))) return false;
                return true;
            });

            const el = document.getElementById('drawerServices');
            if (!hasComboCards && standaloneServices.length === 0 && a.decide_in_salon) {
                const holdMin = Number(a.hold_duration) || 60;
                el.innerHTML = `
                    <div class="svc-item" id="drawerDecideInSalonRow">
                        <div>
                            <div class="name">Decide in Salon</div>
                            <div class="meta">Client will choose services on arrival &middot; ${holdMin} min hold</div>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="price">0.00 QAR</span>
                        </div>
                    </div>
                `;
            } else {
                el.innerHTML = standaloneServices.map(s => {
                    const hasDiscount = Number(s.discount_amount) > 0;
                    const comboBadge = s.combo_name
                        ? `<span class="cal-combo-badge ms-1">${escapeHtml(s.combo_name)}</span>`
                        : '';
                    return `
                    <div class="svc-item" data-line-id="${s.id}">
                        <div>
                            <div class="name">${escapeHtml(s.name)}${comboBadge}</div>
                            <div class="meta">${s.start_time_label} · ${s.duration}min${s.staff_name ? ' · ' + escapeHtml(s.staff_name) : ''}</div>
                            ${hasDiscount ? `
                                <div class="meta" style="color:#8ea88a;" title="${s.discount_reason ? escapeHtml(s.discount_reason) : ''}">
                                    <i class="bx bx-purchase-tag"></i> −${Number(s.discount_amount).toFixed(2)} QAR off ${Number(s.original_price).toFixed(2)}
                                    ${s.discount_reason ? ` · ${escapeHtml(s.discount_reason)}` : ''}
                                </div>
                            ` : ''}
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <span class="price">
                                ${hasDiscount ? `<span class="strike">${Number(s.original_price).toFixed(2)}</span>` : ''}
                                ${Number(s.final_price).toFixed(2)} QAR
                            </span>
                            ${(isFinal || !CAN_EDIT_BOOKINGS) ? '' : `
                                <button type="button" class="icon-btn" data-edit="${s.id}"><i class="bx bx-pencil"></i></button>
                                <button type="button" class="icon-btn danger" data-delete="${s.id}"><i class="bx bx-trash"></i></button>
                            `}
                        </div>
                    </div>
                `;
                }).join('');
            }
            document.getElementById('drawerTotal').textContent = Number(a.price).toFixed(2) + ' QAR';

            const totalDiscount = standaloneServices.reduce((sum, s) => sum + (Number(s.discount_amount) || 0), 0);
            document.getElementById('drawerDiscountRow').classList.toggle('d-none', totalDiscount <= 0);
            document.getElementById('drawerDiscountTotal').textContent = '−' + totalDiscount.toFixed(2) + ' QAR';

            el.querySelectorAll('[data-edit]').forEach(btn => {
                btn.addEventListener('click', () => openEditServiceView(Number(btn.dataset.edit)));
            });
            el.querySelectorAll('[data-delete]').forEach(btn => {
                btn.addEventListener('click', () => deleteServiceLine(Number(btn.dataset.delete)));
            });
        }

        document.getElementById('drawerAddServiceBtn').addEventListener('click', () => {
            showDrawerServicesView('add');
            renderDrawerServiceCatalog('');
        });
        document.getElementById('drawerCheckoutBtn')?.addEventListener('click', function(e) {
            if (!lastDrawerData) return;
            const hasRealServices = Array.isArray(lastDrawerData.services) && lastDrawerData.services.length > 0;
            const hasCombos = Array.isArray(lastDrawerData.combo_packages) && lastDrawerData.combo_packages.length > 0;
            if (lastDrawerData.decide_in_salon && !hasRealServices && !hasCombos) {
                e.preventDefault();
                showDrawerConflict('Please add the service(s) or combo performed before completing checkout.', 'decide_in_salon');
            }
        });
        document.getElementById('drawerAddServiceBack').addEventListener('click', () => showDrawerServicesView('list'));
        document.getElementById('drawerEditServiceBack').addEventListener('click', () => showDrawerServicesView('list'));

        document.getElementById('drawerServiceSearch').addEventListener('input', function() {
            renderDrawerServiceCatalog(this.value);
        });

        function renderDrawerServiceCatalog(query) {
            const q = query.trim().toLowerCase();
            const matches = DRAWER_SERVICES_CATALOG.filter(s => s.name.toLowerCase().includes(q));
            const box = document.getElementById('drawerServiceResults');

            box.innerHTML = matches.map(s => `
                <div class="svc-catalog-row" data-id="${s.id}">
                    <div>
                        <div>${s.name}</div>
                        <div class="meta">${s.duration}min</div>
                    </div>
                    <div>${s.price.toFixed(2)} QAR</div>
                </div>
            `).join('') || '<div class="text-muted small px-2">No services found.</div>';

            box.querySelectorAll('.svc-catalog-row').forEach(row => {
                row.addEventListener('click', () => addServiceToAppointment(Number(row.dataset.id)));
            });
        }

        function addServiceToAppointment(serviceId) {
            clearDrawerConflict();
            fetch(`/appointments/${selectedAppointmentId}/services`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ service_id: serviceId })
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        clearDrawerConflict();
                        showCalToast(data.message || 'Service added');
                        loadCalendar();
                        showDrawerServicesView('list');
                        fetch(`/appointments/${selectedAppointmentId}`).then(r => r.json()).then(renderDrawer);
                    } else {
                        showDrawerConflict(extractApiErrorMessage(data, 'Could not add service.'), data && data.reason);
                    }
                });
        }

        function openEditServiceView(lineId) {
            const line = (lastDrawerData.services || []).find(s => s.id === lineId);
            if (!line) return;
            editingServiceLineId = lineId;

            const select = document.getElementById('editSvcServiceSelect');
            select.innerHTML = DRAWER_SERVICES_CATALOG.map(s =>
                `<option value="${s.id}" ${s.name === line.name ? 'selected' : ''}>${s.name}, ${s.duration}min</option>`
            ).join('');

            document.getElementById('editSvcPrice').value = line.price;
            document.getElementById('editSvcOriginalPriceWrap').classList.remove('d-none');
            document.getElementById('editSvcOriginalPrice').textContent = Number(line.original_price).toFixed(2);
            document.getElementById('editSvcDiscountType').value = line.discount_type || '';
            document.getElementById('editSvcDiscountValue').value = line.discount_value || 0;
            document.getElementById('editSvcDiscountValue').classList.toggle('d-none', !line.discount_type);
            document.getElementById('editSvcDiscountReason').value = line.discount_reason || '';
            const editDrawerBranch = (lastDrawerData && normalizeCalendarBranch(lastDrawerData.branch_raw)) || state.branch;
            scopeSelectOptionsToBranch(document.getElementById('editSvcStaff'), editDrawerBranch, line.staff_id);
            document.getElementById('editSvcStartTime').value = line.start_time;
            document.getElementById('editSvcDuration').value = line.duration;
            document.getElementById('editSvcStaff').value = line.staff_id || '';

            editSvcOriginalPrice = Number(line.original_price);
            updateEditSvcDiscountSummary();

            showDrawerServicesView('edit');
        }

        // Baseline the "Edit service" panel's discount preview is computed
        // against - the catalog price the line was originally added at, not
        // whatever's currently sitting in the price field.
        let editSvcOriginalPrice = 0;

        function updateEditSvcDiscountSummary() {
            const price = parseFloat(document.getElementById('editSvcPrice').value) || 0;
            const discountType = document.getElementById('editSvcDiscountType').value;
            const discountValue = parseFloat(document.getElementById('editSvcDiscountValue').value) || 0;

            let finalPrice = price;
            if (discountType === 'percent') {
                finalPrice = Math.max(0, price * (1 - Math.min(discountValue, 100) / 100));
            } else if (discountType === 'flat') {
                finalPrice = Math.max(0, price - discountValue);
            }

            const discountAmount = Math.max(0, editSvcOriginalPrice - finalPrice);
            const summary = document.getElementById('editSvcDiscountSummary');
            summary.classList.toggle('d-none', discountAmount <= 0);
            document.getElementById('editSvcDiscountAmount').textContent = discountAmount.toFixed(2);
        }

        document.getElementById('editSvcServiceSelect').addEventListener('change', function() {
            const svc = DRAWER_SERVICES_CATALOG.find(s => s.id == this.value);
            if (svc) {
                document.getElementById('editSvcPrice').value = svc.price;
                document.getElementById('editSvcDuration').value = svc.duration;
                // Swapping the service type resets the baseline it's
                // discounted against, matching the controller's behavior.
                editSvcOriginalPrice = svc.price;
                document.getElementById('editSvcOriginalPrice').textContent = Number(svc.price).toFixed(2);
                updateEditSvcDiscountSummary();
            }
        });

        document.getElementById('editSvcDiscountType').addEventListener('change', function() {
            document.getElementById('editSvcDiscountValue').classList.toggle('d-none', !this.value);
            updateEditSvcDiscountSummary();
        });

        ['editSvcPrice', 'editSvcDiscountValue'].forEach(id => {
            document.getElementById(id).addEventListener('input', updateEditSvcDiscountSummary);
        });

        ['editSvcServiceSelect', 'editSvcStartTime', 'editSvcDuration', 'editSvcStaff'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', clearDrawerConflict);
        });

        document.getElementById('editSvcApplyBtn').addEventListener('click', function() {
            clearDrawerConflict();
            const date = lastDrawerData.date;
            const time = document.getElementById('editSvcStartTime').value;

            fetch(`/appointments/${selectedAppointmentId}/services/${editingServiceLineId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        service_id: document.getElementById('editSvcServiceSelect').value,
                        price: document.getElementById('editSvcPrice').value,
                        discount_type: document.getElementById('editSvcDiscountType').value || null,
                        discount_value: document.getElementById('editSvcDiscountValue').value || 0,
                        discount_reason: document.getElementById('editSvcDiscountReason').value || null,
                        start_time: `${date}T${time}`,
                        duration: document.getElementById('editSvcDuration').value,
                        staff_id: document.getElementById('editSvcStaff').value || null,
                    })
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        clearDrawerConflict();
                        showCalToast(data.message || 'Service updated');
                        loadCalendar();
                        showDrawerServicesView('list');
                        fetch(`/appointments/${selectedAppointmentId}`).then(r => r.json()).then(renderDrawer);
                    } else {
                        showDrawerConflict(extractApiErrorMessage(data, 'Could not update service.'), data && data.reason);
                    }
                });
        });

        document.getElementById('editSvcDeleteBtn').addEventListener('click', function() {
            if (editingServiceLineId) deleteServiceLine(editingServiceLineId);
        });

        function deleteServiceLine(lineId) {
            if (!confirm('Remove this service from the appointment?')) return;

            fetch(`/appointments/${selectedAppointmentId}/services/${lineId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        showCalToast(data.message || 'Service removed');
                        loadCalendar();
                        showDrawerServicesView('list');
                        fetch(`/appointments/${selectedAppointmentId}`).then(r => r.json()).then(renderDrawer);
                    } else {
                        showCalToast(data.message || 'Could not remove service.', 'error');
                    }
                });
        }

        /* ---------------- DRAWER: UPSELL LIST / ADD ---------------- */
        function showDrawerUpsellsView(view) {
            document.getElementById('drawerUpsellsListView').classList.toggle('d-none', view !== 'list');
            document.getElementById('drawerUpsellsAddView').classList.toggle('d-none', view !== 'add');
        }

        function renderDrawerUpsellList(a, isFinal) {
            const el = document.getElementById('drawerUpsells');
            const upsells = a.upsells || [];

            if (!upsells.length) {
                el.innerHTML = '<div class="drawer-empty-hint">No upsells logged for this booking yet.</div>';
                return;
            }

            el.innerHTML = upsells.map(u => `
                <div class="upsell-item" data-line-id="${u.id}">
                    <div>
                        <div class="name">${u.name}</div>
                        <div class="meta">${u.staff_name}</div>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="price">${Number(u.amount).toFixed(2)} QAR</span>
                        ${(isFinal || !CAN_EDIT_BOOKINGS) ? '' : `
                            <button type="button" class="icon-btn danger" data-delete-upsell="${u.id}"><i class="bx bx-trash"></i></button>
                        `}
                    </div>
                </div>
            `).join('');

            el.querySelectorAll('[data-delete-upsell]').forEach(btn => {
                btn.addEventListener('click', () => deleteUpsellLine(Number(btn.dataset.deleteUpsell)));
            });
        }

        function currentUpsellType() {
            return document.querySelector('input[name="upsellType"]:checked').value;
        }

        function populateUpsellItemOptions() {
            const type = currentUpsellType();
            const catalog = type === 'product' ? DRAWER_PRODUCTS_CATALOG : DRAWER_SERVICES_CATALOG;

            document.getElementById('upsellItemLabel').textContent = type === 'product' ? 'Retail product' : 'Service';
            document.getElementById('upsellItem').innerHTML = '<option value="">Select…</option>' +
                catalog.map(item => `<option value="${item.id}" data-price="${item.price}">${item.name} (${item.price.toFixed(2)} QAR)</option>`).join('');
            document.getElementById('upsellAmount').value = '';
        }

        document.querySelectorAll('input[name="upsellType"]').forEach(radio => {
            radio.addEventListener('change', populateUpsellItemOptions);
        });

        document.getElementById('upsellItem').addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            document.getElementById('upsellAmount').value = opt.value ? opt.dataset.price : '';
        });

        document.getElementById('drawerAddUpsellBtn').addEventListener('click', () => {
            document.getElementById('upsellTypeService').checked = true;
            document.getElementById('upsellStaff').value = '';
            populateUpsellItemOptions();
            showDrawerUpsellsView('add');
        });
        document.getElementById('drawerAddUpsellBack').addEventListener('click', () => showDrawerUpsellsView('list'));

        document.getElementById('upsellSaveBtn').addEventListener('click', function() {
            const type = currentUpsellType();
            const itemId = document.getElementById('upsellItem').value;
            const amount = document.getElementById('upsellAmount').value;
            const staffId = document.getElementById('upsellStaff').value;

            if (!itemId || amount === '' || !staffId) {
                showCalToast('Select the item, value, and staff member.', 'error');
                return;
            }

            const body = { type, amount, staff_id: staffId };
            body[type === 'product' ? 'product_id' : 'service_id'] = itemId;

            fetch(`/appointments/${selectedAppointmentId}/upsells`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(body)
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        showCalToast(data.message || 'Upsell added');
                        loadCalendar();
                        showDrawerUpsellsView('list');
                        fetch(`/appointments/${selectedAppointmentId}`).then(r => r.json()).then(renderDrawer);
                    } else {
                        showCalToast(data.message || 'Could not add upsell.', 'error');
                    }
                });
        });

        function deleteUpsellLine(lineId) {
            if (!confirm('Remove this upsell from the booking?')) return;

            fetch(`/appointments/${selectedAppointmentId}/upsells/${lineId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        showCalToast(data.message || 'Upsell removed');
                        loadCalendar();
                        fetch(`/appointments/${selectedAppointmentId}`).then(r => r.json()).then(renderDrawer);
                    } else {
                        showCalToast(data.message || 'Could not remove upsell.', 'error');
                    }
                });
        }

        function updateDrawerStatus(status) {
            if (!CAN_EDIT_BOOKINGS) return;
            clearDrawerConflict();
            fetch(`/appointments/${selectedAppointmentId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ status })
                })
                .then(r => r.json().then(res => ({ ok: r.ok, res })))
                .then(({ ok, res }) => {
                    if (!ok || res.success === false) {
                        showDrawerConflict(extractApiErrorMessage(res, 'Could not update status.'), res && res.reason);
                        return;
                    }
                    if (res.redirect) {
                        window.location.href = res.redirect;
                    } else {
                        loadCalendar();
                        fetch(`/appointments/${selectedAppointmentId}`).then(r => r.json()).then(renderDrawer);
                    }
                });
        }

        document.getElementById('drawerNoShowBtn').addEventListener('click', function(e) {
            e.preventDefault();
            if (this.classList.contains('disabled')) return;
            updateDrawerStatus('no_show');
        });
        document.getElementById('drawerCancelBtn').addEventListener('click', function(e) {
            e.preventDefault();
            if (this.classList.contains('disabled')) return;
            updateDrawerStatus('cancelled');
        });

        /* ---------------- RESCHEDULE (drawer form + drag-and-drop + duration resize) ---------------- */
        ['drawerRescheduleDate', 'drawerRescheduleTime', 'drawerRescheduleStaff'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', clearDrawerConflict);
        });

        document.getElementById('drawerRescheduleBtn').addEventListener('click', function(e) {
            e.preventDefault();
            clearDrawerConflict();
            document.getElementById('drawerRescheduleForm').classList.remove('d-none');
        });
        document.getElementById('drawerCancelRescheduleBtn').addEventListener('click', function() {
            clearDrawerConflict();
            document.getElementById('drawerRescheduleForm').classList.add('d-none');
        });
        document.getElementById('drawerConfirmRescheduleBtn').addEventListener('click', function() {
            clearDrawerConflict();
            const date = document.getElementById('drawerRescheduleDate').value;
            const time = document.getElementById('drawerRescheduleTime').value;
            const staffId = document.getElementById('drawerRescheduleStaff').value;
            const branch = document.getElementById('drawerRescheduleBranch').value;
            if (!date || !time) {
                showDrawerConflict('Please pick both a date and start time.', 'validation');
                return;
            }
            rescheduleAppointment(selectedAppointmentId, `${date}T${time}`, staffId, function(ok) {
                if (ok) {
                    clearDrawerConflict();
                    document.getElementById('drawerRescheduleForm').classList.add('d-none');
                    fetch(`/appointments/${selectedAppointmentId}`).then(r => r.json()).then(renderDrawer);
                }
            }, branch, { source: 'drawer' });
        });

        function rescheduleAppointment(appointmentId, datetime, staffId, callback, branch, options) {
            if (!CAN_EDIT_BOOKINGS) return;
            const opts = options || {};
            const isDrawer = opts.source === 'drawer';

            const payload = {
                appointment_datetime: datetime,
                staff_id: staffId ? Number(staffId) : null,
                branch: branch || null
            };
            if (opts.duration) {
                payload.duration = Number(opts.duration);
            }

            fetch(`/appointments/${appointmentId}/reschedule`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok && data.success) {
                        showCalToast(data.message || 'Appointment rescheduled', 'success');
                        loadCalendar();
                        if (callback) callback(true, data);
                    } else {
                        const errMsg = extractApiErrorMessage(data, 'Could not reschedule appointment.');
                        const reason = data && data.reason ? data.reason : '';
                        if (isDrawer) {
                            showDrawerConflict(errMsg, reason);
                        } else {
                            showCalToast(errMsg, 'error', reason);
                        }
                        if (callback) callback(false, data);
                    }
                })
                .catch(() => {
                    const fallbackMsg = 'Could not reschedule appointment. Please try again.';
                    if (isDrawer) {
                        showDrawerConflict(fallbackMsg, 'network');
                    } else {
                        showCalToast(fallbackMsg, 'error', 'network');
                    }
                    if (callback) callback(false, null);
                });
        }

        /* ---------------- DRAG-AND-DROP & DURATION RESIZE ---------------- */
        let draggedAppointmentId = null;
        let draggedApptMinutes = null;
        let draggedFromUnscheduled = false;
        let draggedSourceSnapshot = null;
        let isResizingAppt = false;

        function onApptDragStart(e, id, startMinutes, isUnscheduled = false) {
            if (isResizingAppt) {
                e.preventDefault();
                return;
            }
            draggedAppointmentId = id;
            draggedApptMinutes = startMinutes;
            draggedFromUnscheduled = !!isUnscheduled;
            const cardEl = e.currentTarget;
            draggedSourceSnapshot = {
                el: cardEl,
                parent: cardEl.parentElement,
                nextSibling: cardEl.nextSibling,
                top: cardEl.style.top,
            };
            cardEl.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(id));
        }

        function onApptDragEnd(e) {
            e.currentTarget.classList.remove('dragging');
        }

        function onDropTargetDragOver(e) {
            e.preventDefault();
            e.currentTarget.classList.add('drag-over');
        }

        function onDropTargetDragLeave(e) {
            e.currentTarget.classList.remove('drag-over');
        }

        function removeCardFromUnscheduledSidebar(id) {
            const card = document.querySelector(`.cal-unscheduled-card[data-id="${id}"]`);
            if (card) {
                card.remove();
            }
            if (latestData && Array.isArray(latestData.pending_time_bookings)) {
                latestData.pending_time_bookings = latestData.pending_time_bookings.filter(b => String(b.id) !== String(id));
            }
            const listEl = document.getElementById('unscheduledQueueList');
            const badgeEl = document.getElementById('unscheduledCountBadge');
            const remaining = listEl ? listEl.querySelectorAll('.cal-unscheduled-card').length : 0;
            if (badgeEl) badgeEl.textContent = remaining;
            if (listEl && remaining === 0) {
                listEl.innerHTML = `
                    <div class="cal-unscheduled-empty">
                        <i class="bx bx-calendar-check"></i>
                        No unscheduled bookings for this date.
                    </div>
                `;
            }
        }

        function revertDraggedCardSmoothly(snapshot) {
            if (!snapshot || !snapshot.el || !snapshot.parent || !document.body.contains(snapshot.parent)) {
                return;
            }
            const el = snapshot.el;
            el.classList.add('is-reverting');
            if (el.parentElement !== snapshot.parent) {
                snapshot.parent.insertBefore(el, snapshot.nextSibling || null);
            }
            requestAnimationFrame(() => {
                el.style.top = snapshot.top;
                setTimeout(() => el.classList.remove('is-reverting', 'is-previewing'), 240);
            });
        }

        function onSlotDrop(e, staffId, date, time) {
            e.preventDefault();
            e.currentTarget.classList.remove('drag-over');
            if (!draggedAppointmentId) return;

            const id = draggedAppointmentId;
            const fromUnscheduled = draggedFromUnscheduled;
            const snapshot = draggedSourceSnapshot;
            draggedAppointmentId = null;
            draggedFromUnscheduled = false;
            draggedSourceSnapshot = null;

            if (fromUnscheduled) {
                removeCardFromUnscheduledSidebar(id);
            } else if (snapshot && snapshot.el && latestData && latestData.ui_start) {
                // Optimistically position card onto the target slot so any 422 revert animates smoothly back
                const targetColSlots = document.querySelector(`.cal-staff-col[data-staff-id="${staffId}"] .cal-slots`);
                const [sh, sm] = latestData.ui_start.split(':').map(Number);
                const [th, tm] = time.split(':').map(Number);
                const ppm = state.zoom / 30;
                const newTop = ((th * 60 + tm) - (sh * 60 + sm)) * ppm;
                if (targetColSlots) {
                    snapshot.el.classList.add('is-previewing');
                    if (snapshot.el.parentElement !== targetColSlots) {
                        targetColSlots.appendChild(snapshot.el);
                    }
                    snapshot.el.style.top = `${newTop}px`;
                }
            }

            rescheduleAppointment(id, `${date}T${time}`, staffId, function(ok) {
                if (!ok) {
                    if (fromUnscheduled) {
                        loadCalendar();
                    } else {
                        revertDraggedCardSmoothly(snapshot);
                    }
                }
            }, state.branch, { source: 'drag' });
        }

        function onWeekCellDrop(e, staffId, date) {
            e.preventDefault();
            e.currentTarget.classList.remove('drag-over');
            if (!draggedAppointmentId) return;

            const id = draggedAppointmentId;
            const fromUnscheduled = draggedFromUnscheduled;
            const snapshot = draggedSourceSnapshot;
            const minutes = draggedApptMinutes ?? 600;
            draggedAppointmentId = null;
            draggedFromUnscheduled = false;
            draggedSourceSnapshot = null;

            if (fromUnscheduled) {
                removeCardFromUnscheduledSidebar(id);
            } else if (snapshot && snapshot.el && e.currentTarget) {
                e.currentTarget.appendChild(snapshot.el);
            }

            const hh = String(Math.floor(minutes / 60)).padStart(2, '0');
            const mm = String(minutes % 60).padStart(2, '0');
            rescheduleAppointment(id, `${date}T${hh}:${mm}`, staffId, function(ok) {
                if (!ok) {
                    if (fromUnscheduled) {
                        loadCalendar();
                    } else {
                        revertDraggedCardSmoothly(snapshot);
                    }
                }
            }, state.branch, { source: 'drag' });
        }

        function onApptResizeStart(e, appointmentId, staffId, date, startStr, origDuration, ppm) {
            if (!CAN_EDIT_BOOKINGS) return;
            e.preventDefault();
            e.stopPropagation();

            const cardEl = e.currentTarget.closest('.cal-appt');
            if (!cardEl) return;

            isResizingAppt = true;
            const prevDraggable = cardEl.getAttribute('draggable');
            cardEl.setAttribute('draggable', 'false');

            const startY = e.clientY;
            const origHeight = parseFloat(cardEl.style.height) || Math.max(origDuration * ppm, 46);
            const minHeight = cardEl.querySelector('.cal-combo-badge') ? 58 : 46;
            const timeLabelEl = cardEl.querySelector('.cal-appt-time-label');
            const origTimeText = timeLabelEl ? timeLabelEl.textContent : '';
            const [sh, sm] = startStr.split(':').map(Number);
            const startMinutes = sh * 60 + sm;

            let candidateDuration = origDuration;

            function onMouseMove(moveEv) {
                const deltaY = moveEv.clientY - startY;
                const rawDeltaMins = deltaY / ppm;
                const snappedDelta = Math.round(rawDeltaMins / 15) * 15;
                candidateDuration = Math.max(15, Math.min(720, origDuration + snappedDelta));
                cardEl.style.height = `${Math.max(candidateDuration * ppm, minHeight)}px`;

                if (timeLabelEl) {
                    const endMins = startMinutes + candidateDuration;
                    const endHh = String(Math.floor(endMins / 60) % 24).padStart(2, '0');
                    const endMm = String(endMins % 60).padStart(2, '0');
                    timeLabelEl.textContent = `${to12Hour(startStr)} - ${to12Hour(`${endHh}:${endMm}`)}`;
                }
            }

            function onMouseUp(upEv) {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
                setTimeout(() => { isResizingAppt = false; }, 60);
                if (prevDraggable) cardEl.setAttribute('draggable', prevDraggable);

                if (candidateDuration === origDuration) {
                    cardEl.style.height = `${origHeight}px`;
                    if (timeLabelEl) timeLabelEl.textContent = origTimeText;
                    return;
                }

                rescheduleAppointment(appointmentId, `${date}T${startStr}`, staffId, function(ok) {
                    if (!ok) {
                        cardEl.classList.add('is-reverting');
                        cardEl.style.height = `${origHeight}px`;
                        if (timeLabelEl) timeLabelEl.textContent = origTimeText;
                        setTimeout(() => cardEl.classList.remove('is-reverting'), 240);
                    }
                }, state.branch, { duration: candidateDuration, source: 'drag' });
            }

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        }
    </script>
@endsection
