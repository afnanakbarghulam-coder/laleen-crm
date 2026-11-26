@php
    $todayQatar = $todayQatar ?? \Illuminate\Support\Carbon::now('Asia/Qatar');
    $canEditOffers = isset($canEditOffers) ? (bool) $canEditOffers : (bool) (auth()->user()?->canEdit('services'));
    $canManageOfferGraphics = isset($canManageOfferGraphics)
        ? (bool) $canManageOfferGraphics
        : (bool) (auth()->user()?->canManageMonthlyOfferGraphics());
    $isStandaloneOffersPage = (bool) ($isStandaloneOffersPage ?? false);
    $offersCollection = collect($monthlyOffers ?? []);
    $combosList = collect($combos ?? []);
    $freebiesList = collect($freebiePackages ?? []);
    $catalogServicesList = collect($catalogServices ?? []);
    $oldServiceIds = array_values(array_unique(array_map('intval', (array) old('service_ids', []))));
    $pillarDefs = \App\Models\MonthlyOffer::PILLAR_DEFINITIONS;

    $offerCategories = \App\Models\MonthlyOffer::CATEGORIES;
    $categorySectionDefs = array_merge($offerCategories, ['uncategorized' => 'Uncategorized']);
    $resolveOfferCatKey = static fn ($offer): string => array_key_exists((string) ($offer->category ?? ''), $offerCategories)
        ? (string) $offer->category
        : 'uncategorized';

    $offerStatusCounts = ['active' => 0, 'upcoming' => 0, 'expired' => 0, 'all' => $offersCollection->count()];
    $offerCategoryActiveCounts = array_fill_keys(array_keys($categorySectionDefs), 0);
    $offersByCategory = array_fill_keys(array_keys($categorySectionDefs), []);
    foreach ($offersCollection as $o) {
        $st = $o->liveStatus($todayQatar)['key'];
        if (isset($offerStatusCounts[$st])) {
            $offerStatusCounts[$st]++;
        }
        $catKey = $resolveOfferCatKey($o);
        $offersByCategory[$catKey][] = $o;
        if ($st === 'active') {
            $offerCategoryActiveCounts[$catKey]++;
        }
    }

    $offerToneMap = [
        'emerald' => ['bg' => 'rgba(52, 211, 153, 0.14)', 'border' => 'rgba(52, 211, 153, 0.34)', 'color' => '#6ee7b7'],
        'amber' => ['bg' => 'rgba(245, 158, 11, 0.14)', 'border' => 'rgba(245, 158, 11, 0.34)', 'color' => '#fbbf24'],
        'muted' => ['bg' => 'rgba(255, 255, 255, 0.06)', 'border' => 'rgba(255, 255, 255, 0.12)', 'color' => '#b8a29a'],
    ];
    $offerTypeStyles = [
        'combo' => ['bg' => 'rgba(212, 163, 115, 0.14)', 'border' => 'rgba(212, 163, 115, 0.34)', 'color' => '#f3d9a4'],
        'service_freebie' => ['bg' => 'rgba(52, 211, 153, 0.12)', 'border' => 'rgba(52, 211, 153, 0.30)', 'color' => '#6ee7b7'],
        'standalone' => ['bg' => 'rgba(217, 143, 131, 0.15)', 'border' => 'rgba(217, 143, 131, 0.34)', 'color' => '#e79a91'],
    ];

    $offerFieldNamesTab1 = ['title', 'category', 'offer_type', 'combo_id', 'service_freebie_id', 'service_ids', 'promo_price_qar', 'branch_scope', 'valid_from', 'valid_until', 'terms_and_conditions', 'staff_notes'];
    $offerFieldNamesTab2 = array_column($pillarDefs, 'key');
    $offerFieldNamesTab3 = ['whatsapp_script', 'video_shooting_script', 'graphic_post'];
    $hasOfferTab1Err = $errors->hasAny($offerFieldNamesTab1);
    $hasOfferTab2Err = $errors->hasAny($offerFieldNamesTab2);
    $hasOfferTab3Err = $errors->hasAny($offerFieldNamesTab3);
    $hasAnyOfferValidationErr = $hasOfferTab1Err || $hasOfferTab2Err || $hasOfferTab3Err;
@endphp

<style>
    .mo-num {
        font-variant-numeric: tabular-nums;
    }

    .mo-toolbar {
        background: linear-gradient(180deg, rgba(33, 22, 25, 0.94) 0%, rgba(23, 16, 18, 0.98) 100%);
        border: 1px solid rgba(217, 143, 131, 0.18);
        border-radius: 16px;
        padding: 14px 18px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .mo-pill-group {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .mo-filter-btn {
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(20, 14, 16, 0.65);
        color: #b8a29a;
        border-radius: 999px;
        padding: 5px 13px;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.14s ease;
    }

    .mo-filter-btn:hover {
        color: #f8efe6;
        border-color: rgba(217, 143, 131, 0.4);
    }

    .mo-filter-btn.active {
        background: linear-gradient(135deg, #d98f83, #c48479);
        border-color: transparent;
        color: #1b1315;
    }

    /* Quiet Category Filter Strip & Minimalistic Section Headings */
    .mo-cat-strip {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        margin: -4px 0 22px;
    }

    .mo-cat-pill {
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(24, 18, 19, 0.52);
        color: rgba(255, 255, 255, 0.56);
        border-radius: 999px;
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.14s ease;
    }

    .mo-cat-pill:hover {
        color: #ffffff;
        border-color: rgba(221, 162, 158, 0.34);
    }

    .mo-cat-pill.active {
        background: rgba(221, 162, 158, 0.16);
        border-color: rgba(221, 162, 158, 0.44);
        color: #f8efe6;
        font-weight: 600;
    }

    .mo-cat-pill-count {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.40);
        font-variant-numeric: tabular-nums;
    }

    .mo-cat-pill.active .mo-cat-pill-count {
        color: #DDA29E;
    }

    .mo-sections-wrap {
        display: flex;
        flex-direction: column;
        gap: 28px;
    }

    .mo-cat-section-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }

    .mo-cat-section-title {
        font-size: 11.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.16em;
        color: rgba(255, 255, 255, 0.45);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }

    .mo-cat-section-rule {
        flex: 1 1 auto;
        height: 1px;
        background: rgba(255, 255, 255, 0.06);
    }

    /* Ultra-Minimalistic 3-Tier Luxury Offer Cards Grid */
    .mo-cards-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
        align-items: stretch;
    }

    @media (min-width: 768px) {
        .mo-cards-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1280px) {
        .mo-cards-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    .mo-card-col {
        min-width: 0;
        display: flex;
    }

    .mo-card {
        width: 100%;
        height: 100%;
        position: relative;
        background: rgba(24, 18, 19, 0.84);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        padding: 24px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        cursor: pointer;
        transition: border-color 0.16s ease, box-shadow 0.16s ease;
    }

    .mo-card:hover {
        border-color: rgba(221, 162, 158, 0.40);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.24);
    }

    .mo-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
        line-height: 1.35;
    }

    .mo-title {
        font-size: 17.5px;
        font-weight: 500;
        color: #ffffff;
        margin: 0;
        line-height: 1.35;
        letter-spacing: -0.01em;
    }

    .mo-meta-line {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 6px;
        font-size: 12px;
        color: rgba(255, 255, 255, 0.50);
        line-height: 1.35;
    }

    .mo-status-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
    }

    .mo-status-dot.is-emerald { background: #34d399; }
    .mo-status-dot.is-amber { background: #fbbf24; }
    .mo-status-dot.is-muted { background: rgba(255, 255, 255, 0.36); }

    .mo-price {
        font-size: 20px;
        font-weight: 600;
        color: #ffffff;
        line-height: 1.15;
        letter-spacing: -0.02em;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .mo-price-unit {
        font-size: 12px;
        font-weight: 500;
        color: rgba(255, 255, 255, 0.50);
        margin-left: 2px;
    }

    .mo-clamp-1 {
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .mo-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .mo-summary-line {
        margin-top: 16px;
        font-size: 13.5px;
        color: rgba(255, 255, 255, 0.60);
        line-height: 1.45;
        min-height: 20px;
    }

    .mo-card-footer {
        margin-top: 24px;
        padding-top: 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .mo-ghost-copy-btn {
        background: transparent;
        border: none;
        padding: 2px 0;
        color: rgba(255, 255, 255, 0.65);
        font-size: 12px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: color 0.14s ease;
        line-height: 1.25;
        white-space: nowrap;
    }

    .mo-ghost-copy-btn:hover {
        color: #ffffff;
    }

    .mo-ghost-copy-btn.is-copied {
        color: #6ee7b7;
    }

    .mo-playbook-link {
        background: transparent;
        border: none;
        padding: 2px 0;
        color: rgba(221, 162, 158, 0.80);
        font-size: 12px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        cursor: pointer;
        white-space: nowrap;
        transition: color 0.14s ease;
    }

    .mo-playbook-link:hover,
    .mo-card:hover .mo-playbook-link {
        color: #DDA29E;
    }

    /* 3-Dot Overflow Menu (Admin / services editors only) */
    .mo-card-menu {
        position: relative;
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
    }

    .mo-menu-trigger {
        width: 28px;
        height: 28px;
        padding: 0;
        border: none;
        border-radius: 8px;
        background: transparent;
        color: rgba(255, 255, 255, 0.45);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        line-height: 1;
        cursor: pointer;
        transition: color 0.14s ease, background 0.14s ease;
    }

    .mo-menu-trigger:hover,
    .mo-card-menu.is-open .mo-menu-trigger {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.06);
    }

    .mo-menu-popover {
        position: absolute;
        bottom: calc(100% + 6px);
        right: 0;
        min-width: 172px;
        background: #1c1416;
        border: 1px solid rgba(221, 162, 158, 0.24);
        border-radius: 12px;
        padding: 5px;
        box-shadow: 0 18px 44px rgba(0, 0, 0, 0.76);
        z-index: 45;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .mo-menu-popover form {
        margin: 0;
        padding: 0;
        display: flex;
    }

    .mo-menu-item {
        width: 100%;
        background: transparent;
        border: none;
        border-radius: 8px;
        padding: 7px 10px;
        color: #e6d6d0;
        font-size: 12.5px;
        font-weight: 500;
        text-align: left;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: background 0.12s ease, color 0.12s ease;
    }

    .mo-menu-item:hover {
        background: rgba(255, 255, 255, 0.07);
        color: #ffffff;
    }

    .mo-menu-item.is-danger {
        color: #fda4af;
    }

    .mo-menu-item.is-danger:hover {
        background: rgba(251, 113, 133, 0.14);
        color: #fecdd3;
    }

    .mo-menu-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.06);
        margin: 3px 6px;
    }

    .mo-menu-section-label {
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: rgba(255, 255, 255, 0.38);
        padding: 4px 10px 2px;
    }

    .mo-menu-move-item {
        justify-content: space-between;
        padding: 5.5px 10px;
        font-size: 12px;
    }

    .mo-menu-move-item.is-current,
    .mo-menu-move-item:disabled {
        color: #DDA29E;
        background: rgba(221, 162, 158, 0.08);
        cursor: default;
        opacity: 1;
    }

    .mo-move-check {
        color: #DDA29E;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
    }

    /* Drawer Buttons & Tab Readiness Indicators */
    .mo-playbook-btn {
        background: linear-gradient(135deg, #d98f83, #c48479);
        border: none;
        color: #1b1315;
        border-radius: 999px;
        padding: 6.5px 15px;
        font-size: 12.5px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    .mo-playbook-btn:hover {
        filter: brightness(1.06);
        color: #1b1315;
    }

    .mo-copy-btn {
        background: rgba(52, 211, 153, 0.12);
        border: 1px solid rgba(52, 211, 153, 0.30);
        color: #6ee7b7;
        border-radius: 999px;
        padding: 6px 13px;
        font-size: 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
    }

    .mo-copy-btn:hover {
        background: rgba(52, 211, 153, 0.22);
        color: #a7f3d0;
    }

    .mo-drawer-tab-meta {
        display: inline-flex;
        align-items: center;
        font-size: 10.5px;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 999px;
        margin-left: 5px;
        background: rgba(255, 255, 255, 0.08);
        color: inherit;
    }

    .mo-drawer-tab.active .mo-drawer-tab-meta {
        background: rgba(27, 19, 21, 0.20);
        color: #1b1315;
    }

    .mo-drawer-tab-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
        margin-left: 6px;
        background: rgba(255, 255, 255, 0.22);
        vertical-align: middle;
    }

    .mo-drawer-tab-dot.is-ready {
        background: #34d399;
    }

    .mo-drawer-tab.active .mo-drawer-tab-dot.is-ready {
        background: #1b1315;
    }

    /* Form Controls */
    .mo-input, .mo-select {
        background-color: #130d0f !important;
        border: 1px solid rgba(217, 143, 131, 0.26) !important;
        color: #f8efe6 !important;
        border-radius: 10px !important;
        font-size: 13px;
        color-scheme: dark;
    }

    .mo-select {
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        appearance: none !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23e5b8a8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.75rem center !important;
        background-size: 12px 10px !important;
        padding-right: 2.2rem !important;
    }

    .mo-select option {
        background-color: #130d0f;
        color: #f8efe6;
    }

    .mo-input:focus, .mo-select:focus {
        border-color: #d98f83 !important;
        box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.18) !important;
    }

    .mo-input.is-field-invalid, .mo-select.is-field-invalid {
        border-color: #fb7185 !important;
        box-shadow: 0 0 0 2px rgba(251, 113, 133, 0.22) !important;
    }

    /* 3-Tab Solid Opaque Modal */
    .mo-modal .modal-content {
        background: #1b1315 !important;
        background-color: #1b1315 !important;
        opacity: 1 !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        border: 1px solid rgba(217, 143, 131, 0.34) !important;
        border-radius: 16px;
        box-shadow: 0 24px 64px rgba(0, 0, 0, 0.85) !important;
        color: #f8efe6;
    }

    .mo-modal .modal-header,
    .mo-modal .modal-body,
    .mo-modal .modal-footer {
        background: #1b1315 !important;
        background-color: #1b1315 !important;
    }

    .mo-modal .modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }

    .mo-modal .modal-header .btn-close {
        position: static !important;
        transform: none !important;
        margin: 0 0 0 auto !important;
        background-color: transparent !important;
        box-shadow: none !important;
        opacity: 0.78;
    }

    .mo-modal .modal-footer {
        border-top: 1px solid rgba(255, 255, 255, 0.07);
    }

    .mo-step-bar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        padding: 12px 24px;
        background: #150e10 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .mo-step-tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: transparent;
        color: #b8a29a;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.14s ease;
    }

    .mo-footer-ghost-btn {
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(229, 184, 168, 0.24) !important;
        color: #f2e6e1 !important;
        border-radius: 999px !important;
        font-size: 12.5px;
        font-weight: 600;
        padding: 6px 15px !important;
    }

    .mo-footer-ghost-btn:hover {
        background: rgba(255, 255, 255, 0.11) !important;
        border-color: rgba(229, 184, 168, 0.45) !important;
        color: #ffffff !important;
    }

    .mo-footer-next-btn {
        background: rgba(217, 143, 131, 0.16) !important;
        border: 1px solid rgba(217, 143, 131, 0.42) !important;
        color: #f8efe6 !important;
        border-radius: 999px !important;
        font-size: 12.5px;
        font-weight: 700;
        padding: 6px 16px !important;
    }

    .mo-footer-next-btn:hover {
        background: rgba(217, 143, 131, 0.28) !important;
        border-color: rgba(217, 143, 131, 0.65) !important;
        color: #ffffff !important;
    }

    .mo-step-tab:hover {
        color: #f8efe6;
        border-color: rgba(217, 143, 131, 0.35);
    }

    .mo-step-tab.active {
        background: rgba(217, 143, 131, 0.18);
        border-color: rgba(217, 143, 131, 0.45);
        color: #f8efe6;
    }

    .mo-step-num {
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 10.5px;
        font-weight: 700;
        background: rgba(255, 255, 255, 0.08);
        color: inherit;
    }

    .mo-step-tab.active .mo-step-num {
        background: #d98f83;
        color: #1b1315;
    }

    .mo-tab-err-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #fb7185;
        display: inline-block;
    }

    .mo-pillar-hint {
        font-size: 11.5px;
        color: #967f78;
        margin-bottom: 5px;
    }

    .mo-script-textarea {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 12.5px !important;
        line-height: 1.55;
    }

    /* Collapsible Searchable Multi-Service Catalog Picker */
    .mo-svc-picker {
        background: #130d0f;
        border: 1px solid rgba(217, 143, 131, 0.26);
        border-radius: 12px;
        overflow: hidden;
        transition: border-color 0.14s ease;
    }

    .mo-svc-picker.is-open {
        border-color: rgba(217, 143, 131, 0.52);
    }

    .mo-svc-selected-strip {
        padding: 8px 12px;
        background: rgba(217, 143, 131, 0.06);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .mo-svc-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 9px;
        border-radius: 999px;
        background: rgba(217, 143, 131, 0.16);
        border: 1px solid rgba(217, 143, 131, 0.38);
        color: #f8efe6;
        font-size: 11.5px;
        font-weight: 600;
        line-height: 1.25;
    }

    .mo-svc-pill-remove {
        background: transparent;
        border: none;
        color: #f3c4bd;
        padding: 0;
        line-height: 1;
        font-size: 14px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
    }

    .mo-svc-pill-remove:hover {
        color: #ffffff;
    }

    .mo-svc-search-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: #100a0c;
        cursor: text;
    }

    .mo-svc-picker.is-open .mo-svc-search-bar {
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }

    .mo-svc-search-input {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        color: #f8efe6 !important;
        font-size: 12.5px;
        width: 100%;
        padding: 2px 0;
        outline: none;
    }

    .mo-svc-search-input::placeholder {
        color: #8f7a73;
    }

    .mo-svc-search-clear {
        background: rgba(255, 255, 255, 0.07);
        border: none;
        color: #cbb8b0;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        font-size: 13px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
    }

    .mo-svc-search-clear:hover {
        background: rgba(217, 143, 131, 0.24);
        color: #ffffff;
    }

    .mo-svc-toggle-btn {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.09);
        color: #cbb8b0;
        width: 24px;
        height: 24px;
        border-radius: 7px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        line-height: 1;
        cursor: pointer;
        flex-shrink: 0;
        transition: all 0.14s ease;
    }

    .mo-svc-toggle-btn:hover {
        background: rgba(217, 143, 131, 0.16);
        border-color: rgba(217, 143, 131, 0.36);
        color: #f8efe6;
    }

    .mo-svc-picker.is-open .mo-svc-toggle-btn i {
        transform: rotate(180deg);
    }

    .mo-svc-toggle-btn i {
        transition: transform 0.14s ease;
    }

    .mo-svc-list {
        max-height: 224px;
        overflow-y: auto;
    }

    .mo-svc-dropdown-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 12px;
        background: #171012;
        border-top: 1px solid rgba(255, 255, 255, 0.07);
    }

    .mo-svc-done-btn {
        background: rgba(217, 143, 131, 0.20);
        border: 1px solid rgba(217, 143, 131, 0.45);
        color: #f8efe6;
        border-radius: 999px;
        padding: 4px 14px;
        font-size: 12px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        cursor: pointer;
        transition: all 0.14s ease;
    }

    .mo-svc-done-btn:hover {
        background: rgba(217, 143, 131, 0.34);
        border-color: rgba(217, 143, 131, 0.68);
        color: #ffffff;
    }

    .mo-svc-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 12px;
        margin: 0;
        cursor: pointer;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        transition: background 0.12s ease;
    }

    .mo-svc-row:last-child {
        border-bottom: none;
    }

    .mo-svc-row:hover {
        background: rgba(217, 143, 131, 0.08);
    }

    .mo-svc-row.is-selected {
        background: rgba(217, 143, 131, 0.14);
    }

    .mo-svc-checkbox {
        accent-color: #d98f83;
        width: 15px;
        height: 15px;
        cursor: pointer;
        flex-shrink: 0;
    }

    /* Minimizable T&Cs Information Callout Bar + Quick-Insert Chips */
    .mo-tcs-callout {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(221, 162, 158, 0.25);
        border-radius: 10px;
        padding: 11px 14px;
        margin-bottom: 10px;
        transition: padding 0.14s ease, border-color 0.14s ease;
    }

    .mo-tcs-callout.is-minimized {
        padding: 8px 12px;
        border-color: rgba(221, 162, 158, 0.18);
    }

    .mo-tcs-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        cursor: pointer;
        user-select: none;
    }

    .mo-tcs-toggle-pill {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 11px;
        font-weight: 600;
        color: #d9a79f;
        background: rgba(221, 162, 158, 0.08);
        border: 1px solid rgba(221, 162, 158, 0.22);
        border-radius: 999px;
        padding: 2px 9px;
        flex-shrink: 0;
        transition: all 0.14s ease;
    }

    .mo-tcs-header:hover .mo-tcs-toggle-pill {
        background: rgba(221, 162, 158, 0.18);
        color: #f8efe6;
    }

    .mo-tcs-chip {
        background: rgba(221, 162, 158, 0.10);
        border: 1px solid rgba(221, 162, 158, 0.30);
        color: #f3d9a4;
        border-radius: 999px;
        padding: 3px 10px;
        font-size: 11.5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.14s ease;
    }

    .mo-tcs-chip:hover {
        background: rgba(221, 162, 158, 0.22);
        border-color: rgba(221, 162, 158, 0.55);
        color: #ffffff;
    }

    /* Executive Offer Playbook Slide-Over Drawer */
    .mo-drawer-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(7, 4, 5, 0.72);
        z-index: 1075;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
    }

    .mo-drawer-backdrop.is-open {
        opacity: 1;
        pointer-events: auto;
    }

    .mo-drawer {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: min(94vw, 720px);
        background: #171012;
        border-left: 1px solid rgba(229, 184, 168, 0.24);
        box-shadow: -20px 0 60px rgba(0, 0, 0, 0.75);
        z-index: 1080;
        display: flex;
        flex-direction: column;
        transform: translateX(102%);
        transition: transform 0.24s cubic-bezier(0.22, 1, 0.36, 1);
        color: #f8efe6;
    }

    .mo-drawer.is-open {
        transform: translateX(0);
    }

    .mo-drawer-head {
        padding: 20px 24px 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        background: #1c1316;
        flex-shrink: 0;
    }

    .mo-drawer-tabs {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 10px 24px;
        background: #130d0f;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        overflow-x: auto;
        flex-shrink: 0;
    }

    .mo-drawer-tab {
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: transparent;
        color: #b8a29a;
        border-radius: 999px;
        padding: 6px 14px;
        font-size: 12.5px;
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
    }

    .mo-drawer-tab:hover {
        color: #f8efe6;
    }

    .mo-drawer-tab.active {
        background: linear-gradient(135deg, #d98f83, #c48479);
        border-color: transparent;
        color: #1b1315;
        font-weight: 700;
    }

    .mo-drawer-body {
        padding: 22px 24px 32px;
        overflow-y: auto;
        flex: 1 1 auto;
    }

    .mo-pillar-row {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 14px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .mo-pillar-row:first-child {
        padding-top: 0;
    }

    .mo-pillar-row:last-child {
        border-bottom: none;
    }

    .mo-pillar-circle {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: rgba(217, 143, 131, 0.16);
        border: 1px solid rgba(217, 143, 131, 0.38);
        color: #f3d9a4;
        font-size: 12.5px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .mo-script-box {
        background: #110b0d;
        border: 1px solid rgba(229, 184, 168, 0.16);
        border-radius: 12px;
        padding: 16px 18px;
        font-size: 13px;
        line-height: 1.65;
        color: #f2e6e1;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .mo-toast {
        position: fixed;
        top: 22px;
        right: 24px;
        z-index: 1110;
        background: #1b1315;
        border: 1px solid rgba(110, 231, 183, 0.44);
        color: #6ee7b7;
        border-radius: 12px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 600;
        box-shadow: 0 18px 46px rgba(0, 0, 0, 0.65);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Graphic Post Ghost Button, Lightbox, Drawer Tab & Modal Dropzone */
    .mo-ghost-graphic-btn:hover {
        color: #DDA29E;
    }

    .mo-lightbox {
        position: fixed;
        inset: 0;
        z-index: 1095;
        background: rgba(8, 6, 7, 0.88);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.18s ease;
    }

    .mo-lightbox.is-open {
        opacity: 1;
        pointer-events: auto;
    }

    .mo-lightbox-panel {
        width: min(94vw, 900px);
        max-height: 94vh;
        background: #150f11;
        border: 1px solid rgba(255, 255, 255, 0.10);
        border-radius: 18px;
        box-shadow: 0 28px 80px rgba(0, 0, 0, 0.78);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        color: #f8efe6;
    }

    .mo-lightbox-head {
        padding: 16px 22px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        background: #1a1214;
    }

    .mo-lightbox-body {
        padding: 20px 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #0d090a;
        overflow: auto;
        flex: 1 1 auto;
    }

    .mo-lightbox-img {
        max-height: 72vh;
        max-width: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.10);
        display: block;
        margin: 0 auto;
        background: #140e10;
    }

    .mo-lightbox-foot {
        padding: 14px 22px;
        border-top: 1px solid rgba(255, 255, 255, 0.07);
        background: #181113;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .mo-graphic-btn {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #efe4de;
        border-radius: 999px;
        padding: 7px 14px;
        font-size: 12.5px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.14s ease;
    }

    .mo-graphic-btn:hover {
        border-color: rgba(221, 162, 158, 0.45);
        color: #ffffff;
        background: rgba(221, 162, 158, 0.10);
    }

    .mo-graphic-btn.is-primary {
        background: linear-gradient(135deg, #d98f83, #c48479);
        border-color: transparent;
        color: #1b1315;
    }

    .mo-graphic-btn.is-primary:hover {
        filter: brightness(1.06);
        color: #1b1315;
    }

    .mo-graphic-btn.is-danger {
        color: #fda4af;
        border-color: rgba(251, 113, 133, 0.24);
    }

    .mo-graphic-btn.is-danger:hover {
        background: rgba(251, 113, 133, 0.12);
        border-color: rgba(251, 113, 133, 0.45);
        color: #fecdd3;
    }

    .mo-graphic-preview-wrap {
        position: relative;
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.10);
        background: #0f0a0c;
        padding: 14px;
        text-align: center;
        cursor: zoom-in;
        transition: border-color 0.16s ease;
    }

    .mo-graphic-preview-wrap:hover {
        border-color: rgba(221, 162, 158, 0.42);
    }

    .mo-graphic-preview-img {
        max-height: 440px;
        max-width: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 10px;
        display: block;
        margin: 0 auto;
    }

    .mo-graphic-preview-hint {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 10px;
        font-size: 11.5px;
        color: rgba(255, 255, 255, 0.48);
    }

    .mo-graphic-dropzone {
        border: 1px dashed rgba(221, 162, 158, 0.30);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.02);
        padding: 16px 18px;
        text-align: center;
        cursor: pointer;
        transition: border-color 0.15s ease, background 0.15s ease;
    }

    .mo-graphic-dropzone:hover,
    .mo-graphic-dropzone.is-dragover {
        border-color: rgba(221, 162, 158, 0.72);
        background: rgba(221, 162, 158, 0.07);
    }

    .mo-modal-graphic-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.10);
    }

    .mo-modal-graphic-thumb {
        width: 54px;
        height: 54px;
        border-radius: 9px;
        object-fit: cover;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: #0f0a0c;
        flex-shrink: 0;
    }
</style>

@if (session('success') && $isStandaloneOffersPage)
    <div class="alert py-2 px-3 mb-3 d-flex align-items-center gap-2" style="background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.34); color:#6ee7b7; border-radius:12px; font-size:13px;">
        <i class="bx bx-check-circle fs-5"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if ($hasAnyOfferValidationErr)
    <div class="alert py-2 px-3 mb-3" style="background:rgba(251,113,133,0.12); border:1px solid rgba(251,113,133,0.34); color:#fda4af; border-radius:12px; font-size:13px;">
        <div class="fw-bold mb-1">Please complete all required Master Offer fields:</div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- FILTER & STATUS TOOLBAR --}}
<div class="mo-toolbar" id="monthlyOffersBoard">
    <div class="mo-pill-group" role="group" aria-label="Filter offers by status">
        <button type="button" class="mo-filter-btn active" data-mo-status="active">
            Active ({{ $offerStatusCounts['active'] }})
        </button>
        <button type="button" class="mo-filter-btn" data-mo-status="upcoming">
            Upcoming ({{ $offerStatusCounts['upcoming'] }})
        </button>
        <button type="button" class="mo-filter-btn" data-mo-status="expired">
            Expired ({{ $offerStatusCounts['expired'] }})
        </button>
        <button type="button" class="mo-filter-btn" data-mo-status="all">
            All ({{ $offerStatusCounts['all'] }})
        </button>
    </div>

    <div class="d-flex align-items-center flex-wrap gap-2">
        <select id="moBranchFilter" class="form-select form-select-sm mo-select" style="width:auto; min-width:136px;" aria-label="Filter by branch">
            <option value="all">All Branches</option>
            <option value="old_airport">Old Airport</option>
            <option value="al_wakrah">Al Wakrah</option>
        </select>
        <input type="text" id="moSearchInput" class="form-control form-control-sm mo-input" style="width:190px;" placeholder="Search offers...">
        @if ($canEditOffers && $isStandaloneOffersPage)
            <button type="button" class="btn btn-sm fw-bold px-3 py-2" id="addOfferStandaloneBtn"
                style="background:linear-gradient(135deg,#d98f83,#c48479); color:#1b1315; border:none; border-radius:999px;">
                <i class="bx bx-plus me-1"></i> Add Monthly Offer
            </button>
        @endif
    </div>
</div>

{{-- QUIET CATEGORY FILTER STRIP --}}
<div class="mo-cat-strip" id="moCategoryFilterStrip" role="tablist" aria-label="Filter offers by category">
    <button type="button" class="mo-cat-pill active" data-mo-cat="all" role="tab" aria-selected="true">
        <span>All</span>
        <span class="mo-cat-pill-count js-mo-cat-count" data-cat-count="all">{{ $offerStatusCounts['active'] }}</span>
    </button>
    @foreach ($offerCategories as $catKey => $catLabel)
        <button type="button" class="mo-cat-pill" data-mo-cat="{{ $catKey }}" role="tab" aria-selected="false">
            <span>{{ $catLabel }}</span>
            <span class="mo-cat-pill-count js-mo-cat-count" data-cat-count="{{ $catKey }}">{{ $offerCategoryActiveCounts[$catKey] }}</span>
        </button>
    @endforeach
    <button type="button"
            class="mo-cat-pill {{ $offerCategoryActiveCounts['uncategorized'] > 0 ? '' : 'd-none' }}"
            data-mo-cat="uncategorized"
            role="tab"
            aria-selected="false">
        <span>Uncategorized</span>
        <span class="mo-cat-pill-count js-mo-cat-count" data-cat-count="uncategorized">{{ $offerCategoryActiveCounts['uncategorized'] }}</span>
    </button>
</div>

{{-- MINIMALISTIC CATEGORY-GROUPED OFFER SECTIONS --}}
<div class="mo-sections-wrap" id="moSectionsWrap">
    @foreach ($categorySectionDefs as $sectionCatKey => $sectionCatLabel)
        @php
            $sectionOffers = $offersByCategory[$sectionCatKey] ?? [];
            $sectionActiveCount = $offerCategoryActiveCounts[$sectionCatKey] ?? 0;
        @endphp
        <section class="mo-cat-section {{ $sectionActiveCount > 0 ? '' : 'd-none' }}" data-mo-section="{{ $sectionCatKey }}">
            <div class="mo-cat-section-head">
                <span class="mo-cat-section-title">
                    <span>{{ strtoupper($sectionCatLabel) }}</span>
                    <span>&middot;</span>
                    <span class="mo-num js-mo-section-count">{{ $sectionActiveCount }}</span>
                </span>
                <span class="mo-cat-section-rule" aria-hidden="true"></span>
            </div>
            <div class="mo-cards-grid" data-mo-section-grid="{{ $sectionCatKey }}">
                @foreach ($sectionOffers as $offer)
                    @php
                        $st = $offer->liveStatus($todayQatar);
                        $effPrice = $offer->effectivePrice();
                        $includedList = $offer->includedServicesList();
                        $serviceNamesOnly = array_values(array_filter(array_map(
                            fn ($inc) => ($inc['meta'] ?? null) === 'Combo Pool' ? null : trim((string) ($inc['name'] ?? '')),
                            $includedList
                        )));
                        $summaryLine = '';
                        if (!empty($serviceNamesOnly)) {
                            $shownNames = array_slice($serviceNamesOnly, 0, 3);
                            $extraCount = max(0, count($serviceNamesOnly) - 3);
                            $summaryLine = implode(' · ', $shownNames) . ($extraCount > 0 ? " (+{$extraCount} more)" : '');
                        } elseif (filled($offer->dream_outcome)) {
                            $summaryLine = trim((string) $offer->dream_outcome);
                        }
                        $statusWord = match ($st['key']) {
                            'active' => 'Active',
                            'upcoming' => 'Upcoming',
                            'expired' => 'Expired',
                            default => 'Archived',
                        };
                        $dateShortCue = match ($st['key']) {
                            'active' => $offer->valid_until ? ('Ends ' . $offer->valid_until->format('d M')) : null,
                            'upcoming' => $offer->valid_from ? ('Starts ' . $offer->valid_from->format('d M')) : null,
                            'expired' => $offer->valid_until ? ('Ended ' . $offer->valid_until->format('d M')) : null,
                            default => $offer->valid_until ? ('Ends ' . $offer->valid_until->format('d M')) : null,
                        };
                        $playbookPayload = $offer->toPlaybookPayload($todayQatar);
                        $searchHaystack = strtolower(
                            $offer->title . ' ' . ($offer->categoryLabel() ?? 'uncategorized') . ' ' . $offer->typeBadgeLabel() . ' ' . $offer->branchScopeLabel() . ' '
                            . implode(' ', array_column($includedList, 'name')) . ' '
                            . $offer->terms_and_conditions . ' '
                            . ($offer->dream_outcome ?? '') . ' '
                            . ($offer->problem_solved ?? '') . ' '
                            . ($offer->conditional_guarantee ?? '') . ' '
                            . ($offer->transparent_pricing ?? '') . ' '
                            . ($offer->capacity_scarcity ?? '')
                        );
                    @endphp
                    <div class="mo-card-col"
                         data-mo-card-id="{{ $offer->id }}"
                         data-mo-card-status="{{ $st['key'] }}"
                         data-mo-card-branch="{{ $offer->branch_scope }}"
                         data-mo-card-category="{{ $sectionCatKey }}"
                         data-mo-card-search="{{ $searchHaystack }}"
                         style="{{ $st['key'] !== 'active' ? 'display:none;' : '' }}">
                        <article class="mo-card js-mo-card-open" data-offer-id="{{ $offer->id }}" tabindex="0" role="button" aria-label="Open Playbook for {{ $offer->title }}">
                            <div>
                                {{-- TIER 1: HEADER (Title + Price + 1 Quiet Meta Line) --}}
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <h5 class="mo-title mo-clamp-2 min-w-0 flex-grow-1">{{ $offer->title }}</h5>
                                    <div class="mo-price mo-num">
                                        {{ number_format($effPrice, 0) }}<span class="mo-price-unit">QAR</span>
                                    </div>
                                </div>

                                <div class="mo-meta-line mo-num">
                                    <span class="d-inline-flex align-items-center gap-1">
                                        <span class="mo-status-dot is-{{ $st['tone'] }}"></span>
                                        <span>{{ $statusWord }}</span>
                                    </span>
                                    <span>&middot;</span>
                                    <span>{{ $offer->branchScopeLabel() }}</span>
                                    @if ($dateShortCue)
                                        <span>&middot;</span>
                                        <span>{{ $dateShortCue }}</span>
                                    @endif
                                </div>

                                {{-- TIER 2: SINGLE QUIET CONTENT LINE (Plain-Text Service Summary or 1-Line Dream Outcome) --}}
                                @if ($summaryLine !== '')
                                    <div class="mo-summary-line mo-clamp-1" title="{{ $summaryLine }}">{{ $summaryLine }}</div>
                                @endif
                            </div>

                            {{-- TIER 3: MINIMAL UNCROWDED FOOTER --}}
                            <div class="mo-card-footer">
                                <div class="d-flex align-items-center gap-3 flex-wrap min-w-0">
                                    <button type="button"
                                        class="mo-ghost-copy-btn js-mo-copy"
                                        data-copy-text="{{ $offer->whatsappCopyText() }}">
                                        <i class="bx bxl-whatsapp fs-6"></i>
                                        <span class="js-mo-copy-label">Copy WhatsApp</span>
                                    </button>

                                    <button type="button"
                                        class="mo-ghost-copy-btn mo-ghost-graphic-btn js-mo-open-graphic {{ $offer->hasGraphicPost() ? '' : 'd-none' }}"
                                        data-offer-id="{{ $offer->id }}"
                                        title="View promotional graphic post">
                                        <i class="bx bx-image-alt fs-6"></i>
                                        <span>Graphic</span>
                                    </button>
                                </div>

                                <div class="d-flex align-items-center gap-3 flex-shrink-0">
                                    <button type="button"
                                        class="mo-playbook-link js-mo-open-playbook"
                                        data-offer-id="{{ $offer->id }}">
                                        <span>Playbook &rarr;</span>
                                    </button>

                                    @if ($canEditOffers || $canManageOfferGraphics)
                                        <div class="mo-card-menu js-mo-card-menu js-mo-stop-prop">
                                            <button type="button"
                                                class="mo-menu-trigger js-mo-menu-trigger"
                                                aria-haspopup="menu"
                                                aria-expanded="false"
                                                aria-label="Offer actions for {{ $offer->title }}"
                                                title="More actions">
                                                <i class="bx bx-dots-horizontal-rounded"></i>
                                            </button>
                                            <div class="mo-menu-popover js-mo-menu-popover d-none" role="menu">
                                                @if ($canEditOffers)
                                                    <button type="button"
                                                        class="mo-menu-item js-mo-edit-btn"
                                                        role="menuitem"
                                                        data-offer-id="{{ $offer->id }}">
                                                        <i class="bi bi-pencil-square"></i>
                                                        <span>Edit Offer</span>
                                                    </button>
                                                @endif
                                                @if ($canManageOfferGraphics)
                                                    <button type="button"
                                                        class="mo-menu-item js-mo-quick-upload-graphic"
                                                        role="menuitem"
                                                        data-offer-id="{{ $offer->id }}"
                                                        data-graphic-upload-url="{{ route('offers.graphic.upload', $offer) }}">
                                                        <i class="bx bx-image-add"></i>
                                                        <span class="js-mo-upload-graphic-label">{{ $offer->hasGraphicPost() ? 'Change Graphic' : 'Upload Graphic' }}</span>
                                                    </button>
                                                @endif
                                                @if ($canEditOffers)
                                                    <div class="mo-menu-divider"></div>
                                                    <div class="mo-menu-section-label">Move to</div>
                                                    @foreach ($offerCategories as $moveCatKey => $moveCatLabel)
                                                        @php $isCurrentCat = ($sectionCatKey === $moveCatKey); @endphp
                                                        <button type="button"
                                                            class="mo-menu-item mo-menu-move-item js-mo-move-cat {{ $isCurrentCat ? 'is-current' : '' }}"
                                                            role="menuitem"
                                                            data-offer-id="{{ $offer->id }}"
                                                            data-move-url="{{ route('offers.move-category', $offer) }}"
                                                            data-target-category="{{ $moveCatKey }}"
                                                            data-target-label="{{ $moveCatLabel }}"
                                                            {{ $isCurrentCat ? 'disabled aria-current=true' : '' }}>
                                                            <span>{{ $moveCatLabel }}</span>
                                                            <span class="mo-move-check {{ $isCurrentCat ? '' : 'd-none' }}" aria-hidden="true">&#10003;</span>
                                                        </button>
                                                    @endforeach
                                                    <div class="mo-menu-divider"></div>
                                                    <form method="POST" action="{{ route('offers.toggle', $offer) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="mo-menu-item" role="menuitem">
                                                            <i class="bx {{ $offer->is_active ? 'bx-archive-in' : 'bx-revision' }}"></i>
                                                            <span>{{ $offer->is_active ? 'Archive / Unpublish' : 'Activate Offer' }}</span>
                                                        </button>
                                                    </form>
                                                    <div class="mo-menu-divider"></div>
                                                    <form method="POST" action="{{ route('offers.destroy', $offer) }}"
                                                          onsubmit="return confirm('Delete monthly offer {{ addslashes($offer->title) }}?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="mo-menu-item is-danger" role="menuitem">
                                                            <i class="bi bi-trash"></i>
                                                            <span>Delete</span>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Embedded JSON Playbook payload (powers both Drawer & Edit Modal cleanly) --}}
                            <script type="application/json" id="moOfferData-{{ $offer->id }}">@json($playbookPayload)</script>
                        </article>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>

<div id="moEmptyState" class="text-center py-5 {{ $offerStatusCounts['active'] > 0 ? 'd-none' : '' }}" style="color:#9e8780;">
    <i class="bx bx-purchase-tag-alt fs-2 d-block mb-2" style="color:#d98f83;"></i>
    <div class="fw-semibold" style="color:#cbb8b0;">No monthly offers match this view.</div>
    <div class="small mt-1">Switch filter tabs above{{ $canEditOffers ? ' or publish a new Master Offer.' : '.' }}</div>
</div>

{{-- EXECUTIVE OFFER PLAYBOOK SLIDE-OVER DRAWER (4 TABS — ALL OPERATIONAL ROLES) --}}
<div id="moPlaybookBackdrop" class="mo-drawer-backdrop" aria-hidden="true"></div>
<aside id="moPlaybookDrawer" class="mo-drawer" role="dialog" aria-modal="true" aria-labelledby="moDrawerTitle" aria-hidden="true">
    <div class="mo-drawer-head">
        <div class="d-flex align-items-start justify-content-between gap-3">
            <div class="min-w-0">
                <div class="d-flex align-items-center flex-wrap gap-1 mb-2" id="moDrawerBadges"></div>
                <h4 class="fw-bold mb-1" id="moDrawerTitle" style="color:#f8efe6; font-size:20px; line-height:1.25;">Offer Playbook</h4>
                <div class="small mo-num" id="moDrawerMeta" style="color:#a8938b;"></div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <div class="text-end mo-num me-2">
                    <div id="moDrawerPrice" style="font-size:22px; font-weight:800; color:#f3d9a4; line-height:1.1;"></div>
                </div>
                @if ($canEditOffers)
                    <button type="button" id="moDrawerEditBtn" class="btn btn-sm btn-outline-warning px-2 py-1" title="Edit Master Offer">
                        <i class="bi bi-pencil-square"></i>
                    </button>
                @endif
                <button type="button" id="moDrawerCloseBtn" class="btn-close btn-close-white" aria-label="Close Playbook"></button>
            </div>
        </div>
    </div>

    <div class="mo-drawer-tabs" role="tablist" aria-label="Offer Playbook Sections">
        <button type="button" class="mo-drawer-tab active" data-drawer-tab="pillars">
            <span>7-Pillar Architecture</span>
            <span id="moDrawerTabPillarsCount" class="mo-drawer-tab-meta mo-num">7/7</span>
        </button>
        <button type="button" class="mo-drawer-tab" data-drawer-tab="terms">Terms &amp; Conditions</button>
        <button type="button" class="mo-drawer-tab" data-drawer-tab="whatsapp">
            <span>WhatsApp Scripts</span>
            <span id="moDrawerTabWaDot" class="mo-drawer-tab-dot" title="Custom WhatsApp script readiness"></span>
        </button>
        <button type="button" class="mo-drawer-tab" data-drawer-tab="video">
            <span>Video Shooting Script</span>
            <span id="moDrawerTabVideoDot" class="mo-drawer-tab-dot" title="Video shooting script readiness"></span>
        </button>
        <button type="button" class="mo-drawer-tab" data-drawer-tab="graphic">
            <span>Graphic Post</span>
            <span id="moDrawerTabGraphicDot" class="mo-drawer-tab-dot" title="Graphic post readiness"></span>
        </button>
    </div>

    <div class="mo-drawer-body">
        {{-- Drawer Tab 1: 7-Pillar Architecture --}}
        <div class="mo-drawer-pane" data-drawer-pane="pillars">
            <div id="moDrawerPillarsList"></div>
            <div id="moDrawerIncludedWrap" class="mt-4 pt-3 d-none" style="border-top:1px solid rgba(255,255,255,0.08);">
                <div class="small fw-bold text-uppercase mb-2" style="letter-spacing:0.05em; color:#a8938b;">Included Services</div>
                <div id="moDrawerIncludedList" class="d-flex flex-column gap-2"></div>
            </div>
        </div>

        {{-- Drawer Tab 2: Terms & Conditions --}}
        <div class="mo-drawer-pane d-none" data-drawer-pane="terms">
            <div class="d-flex align-items-center flex-wrap gap-3 mb-4 pb-3" style="border-bottom:1px solid rgba(255,255,255,0.07);">
                <div>
                    <div class="small text-uppercase fw-bold" style="font-size:10.5px; letter-spacing:0.05em; color:#9e8780;">Branch Scope</div>
                    <div class="fw-semibold" id="moDrawerTermsBranch" style="color:#f8efe6;"></div>
                </div>
                <div style="width:1px; height:28px; background:rgba(255,255,255,0.08);"></div>
                <div>
                    <div class="small text-uppercase fw-bold" style="font-size:10.5px; letter-spacing:0.05em; color:#9e8780;">Validity Window (Qatar)</div>
                    <div class="fw-semibold mo-num" id="moDrawerTermsDates" style="color:#f8efe6;"></div>
                </div>
            </div>

            <div class="small text-uppercase fw-bold mb-2" style="font-size:11px; letter-spacing:0.05em; color:#a8938b;">Terms &amp; Conditions</div>
            <ul id="moDrawerTermsList" class="mb-4" style="padding-left:18px; color:#e6d6d0; line-height:1.65; font-size:13.5px;"></ul>

            <div id="moDrawerNotesWrap" class="d-none pt-3" style="border-top:1px solid rgba(255,255,255,0.07);">
                <div class="small text-uppercase fw-bold mb-2" style="font-size:11px; letter-spacing:0.05em; color:#d4a373;">
                    <i class="bx bx-info-circle me-1"></i> Internal Staff Notes
                </div>
                <div id="moDrawerNotesText" class="mo-script-box" style="color:#e8cfa9;"></div>
            </div>
        </div>

        {{-- Drawer Tab 3: WhatsApp Scripts --}}
        <div class="mo-drawer-pane d-none" data-drawer-pane="whatsapp">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div class="small fw-semibold" id="moDrawerWaHeading" style="color:#cbb8b0;">Ready-to-send WhatsApp / DM Sales Script</div>
                <div class="d-flex align-items-center gap-2">
                    @if ($canEditOffers)
                        <button type="button" id="moDrawerAddWaBtn" class="btn btn-sm btn-outline-warning d-none" style="border-radius:999px; font-size:12px;">
                            <i class="bx bx-edit-alt me-1"></i> Add Custom Script
                        </button>
                    @endif
                    <button type="button" id="moDrawerCopyWaBtn" class="mo-copy-btn">
                        <i class="bx bxl-whatsapp fs-5"></i>
                        <span>Copy WhatsApp Script</span>
                    </button>
                </div>
            </div>
            <div id="moDrawerWaFallbackNotice" class="small mb-3 px-3 py-2 d-none" style="border-radius:10px; background:rgba(212,163,115,0.10); border:1px solid rgba(212,163,115,0.26); color:#f3d9a4;">
                No custom WhatsApp script saved yet &mdash; showing auto-formatted client pitch from the Offer Playbook.
            </div>
            <div id="moDrawerWaContent" class="mo-script-box"></div>
        </div>

        {{-- Drawer Tab 4: Video Shooting Script --}}
        <div class="mo-drawer-pane d-none" data-drawer-pane="video">
            <div id="moDrawerVideoHasScript">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div class="small fw-semibold" style="color:#cbb8b0;">Production Shoot Brief &amp; Script</div>
                    <button type="button" id="moDrawerCopyVideoBtn" class="mo-copy-btn">
                        <i class="bx bx-copy"></i>
                        <span>Copy Video Script</span>
                    </button>
                </div>
                <div id="moDrawerVideoContent" class="mo-script-box"></div>
            </div>
            <div id="moDrawerVideoEmpty" class="text-center py-5 d-none">
                <i class="bx bx-video fs-1 d-block mb-2" style="color:#8f7a73;"></i>
                <div class="fw-semibold mb-1" style="color:#cbb8b0;">No video shooting script added yet</div>
                <div class="small mb-3" style="color:#8f7a73;">Paste the 3-second hook, before/after shots, value stack visuals, and booking CTA here.</div>
                @if ($canEditOffers)
                    <button type="button" id="moDrawerAddVideoBtn" class="mo-playbook-btn">
                        <i class="bx bx-plus"></i>
                        <span>Add Script</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Drawer Tab 5: Graphic Post --}}
        <div class="mo-drawer-pane d-none" data-drawer-pane="graphic">
            <div id="moDrawerGraphicHasImage" class="d-none">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div class="small fw-semibold" style="color:#cbb8b0;">Promotional Graphic Post</div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <button type="button" id="moDrawerCopyGraphicBtn" class="mo-graphic-btn is-primary">
                            <i class="bx bx-copy"></i>
                            <span class="js-mo-graphic-copy-label">Copy Image</span>
                        </button>
                        <a href="#" id="moDrawerDownloadGraphicLink" class="mo-graphic-btn" download>
                            <i class="bx bx-download"></i>
                            <span>Download Graphic</span>
                        </a>
                        @if ($canManageOfferGraphics)
                            <button type="button" id="moDrawerReplaceGraphicBtn" class="mo-graphic-btn">
                                <i class="bx bx-refresh"></i>
                                <span>Replace</span>
                            </button>
                            <button type="button" id="moDrawerRemoveGraphicBtn" class="mo-graphic-btn is-danger">
                                <i class="bi bi-trash"></i>
                                <span>Remove</span>
                            </button>
                        @endif
                    </div>
                </div>
                <div id="moDrawerGraphicPreviewWrap" class="mo-graphic-preview-wrap" role="button" tabindex="0" aria-label="Expand graphic post in full-size viewer">
                    <img id="moDrawerGraphicImg" class="mo-graphic-preview-img" src="" alt="Offer Graphic Post">
                    <div class="mo-graphic-preview-hint">
                        <i class="bx bx-expand-alt"></i>
                        <span>Click image to open full-size lightbox</span>
                    </div>
                </div>
            </div>

            <div id="moDrawerGraphicEmpty" class="text-center py-5 d-none">
                <i class="bx bx-image-alt fs-1 d-block mb-2" style="color:#8f7a73;"></i>
                <div class="fw-semibold mb-1" style="color:#cbb8b0;">No graphic post uploaded yet</div>
                @if ($canManageOfferGraphics)
                    <div class="small mb-3" style="color:#8f7a73;">Upload the promotional flyer so chat agents and managers can view, copy, or download it in 1 click.</div>
                    <div id="moDrawerGraphicDropzone" class="mo-graphic-dropzone mx-auto" style="max-width:420px;">
                        <div class="d-inline-flex align-items-center gap-2 fw-semibold mb-1" style="color:#f3d9a4; font-size:13px;">
                            <i class="bx bx-upload fs-5"></i>
                            <span>+ Upload Graphic Post</span>
                        </div>
                        <div class="small" style="color:#8f7a73; font-size:11.5px;">Drop an image here or click to browse &middot; JPG, PNG, WEBP up to 10 MB</div>
                    </div>
                @else
                    <div class="small" style="color:#8f7a73;">Pending creative upload</div>
                @endif
            </div>
        </div>
    </div>
</aside>

{{-- SLEEK DARK GRAPHIC POST LIGHTBOX MODAL (ALL OPERATIONAL ROLES) --}}
<div id="moGraphicLightbox" class="mo-lightbox" role="dialog" aria-modal="true" aria-labelledby="moLightboxTitle" aria-hidden="true">
    <div class="mo-lightbox-panel">
        <div class="mo-lightbox-head">
            <div class="min-w-0">
                <h5 id="moLightboxTitle" class="fw-semibold mb-1 text-truncate" style="color:#f8efe6; font-size:17px;">Offer Graphic</h5>
                <div id="moLightboxMeta" class="small mo-num" style="color:#a8938b;"></div>
            </div>
            <div class="d-flex align-items-center gap-3 flex-shrink-0">
                <div id="moLightboxPrice" class="mo-num fw-bold" style="color:#f3d9a4; font-size:18px;"></div>
                <button type="button" id="moLightboxCloseBtn" class="btn-close btn-close-white" aria-label="Close Graphic Viewer"></button>
            </div>
        </div>
        <div class="mo-lightbox-body">
            <img id="moLightboxImg" class="mo-lightbox-img" src="" alt="Offer Graphic Post">
        </div>
        <div class="mo-lightbox-foot">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <button type="button" id="moLightboxCopyBtn" class="mo-graphic-btn is-primary">
                    <i class="bx bx-copy"></i>
                    <span class="js-mo-graphic-copy-label">Copy Image</span>
                </button>
                <a href="#" id="moLightboxDownloadLink" class="mo-graphic-btn" download>
                    <i class="bx bx-download"></i>
                    <span>Download</span>
                </a>
            </div>
            @if ($canManageOfferGraphics)
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <button type="button" id="moLightboxReplaceBtn" class="mo-graphic-btn">
                        <i class="bx bx-refresh"></i>
                        <span>Replace</span>
                    </button>
                    <button type="button" id="moLightboxRemoveBtn" class="mo-graphic-btn is-danger">
                        <i class="bi bi-trash"></i>
                        <span>Remove</span>
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>

@if ($canManageOfferGraphics)
    <input type="file" id="moQuickGraphicFileInput" class="d-none" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
@endif

{{-- 3-TAB ADD / EDIT MONTHLY OFFER MODAL (SERVICES FULL EDIT / ADMIN ONLY) --}}
@if ($canEditOffers)
<div class="modal fade mo-modal" id="monthlyOfferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('offers.store') }}" id="monthlyOfferForm" enctype="multipart/form-data" novalidate>
                @csrf
                <input type="hidden" name="_method" id="moFormMethod" value="POST">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="moModalTitle">Add Monthly Offer</h5>
                        <div class="small" style="color:#9e8780;">Master Offer Playbook &middot; Basics, 7-Pillar Architecture &amp; Scripts</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                {{-- 3-Tab Step Bar --}}
                <div class="mo-step-bar" id="moModalTabs" role="tablist">
                    <button type="button" class="mo-step-tab active" data-mo-step="1">
                        <span class="mo-step-num">1</span>
                        <span>Basics, Price &amp; T&amp;Cs</span>
                        <span class="mo-tab-err-dot {{ $hasOfferTab1Err ? '' : 'd-none' }}" data-step-err="1"></span>
                    </button>
                    <button type="button" class="mo-step-tab" data-mo-step="2">
                        <span class="mo-step-num">2</span>
                        <span>7-Pillar Architecture</span>
                        <span style="font-size:10px; color:#f3d9a4; font-weight:700;">7 Req</span>
                        <span class="mo-tab-err-dot {{ $hasOfferTab2Err ? '' : 'd-none' }}" data-step-err="2"></span>
                    </button>
                    <button type="button" class="mo-step-tab" data-mo-step="3">
                        <span class="mo-step-num">3</span>
                        <span>Scripts &amp; Graphic</span>
                        <span class="mo-tab-err-dot {{ $hasOfferTab3Err ? '' : 'd-none' }}" data-step-err="3"></span>
                    </button>
                </div>

                <div class="modal-body" style="max-height: 62vh; overflow-y: auto;">
                    <div id="moClientValidationBanner" class="alert py-2 px-3 mb-3 d-none" style="background:rgba(251,113,133,0.12); border:1px solid rgba(251,113,133,0.34); color:#fda4af; border-radius:10px; font-size:12.5px;"></div>

                    {{-- TAB 1: BASICS, PRICE & T&CS --}}
                    <div class="mo-step-pane" data-mo-step-pane="1">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small text-muted" for="moTitle">Offer Title <span style="color:#fda4af;">*</span></label>
                                <input type="text" name="title" id="moTitle" class="form-control mo-input" data-mo-required="1" data-mo-label="Offer Title" maxlength="255" placeholder="e.g. November Glass-Hair Botox Experience" value="{{ old('title') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted" for="moCategory">Category <span style="color:#fda4af;">*</span></label>
                                <select name="category" id="moCategory" class="form-select mo-select" data-mo-required="1" data-mo-label="Category">
                                    <option value="">Choose a category...</option>
                                    @foreach ($offerCategories as $catKey => $catLabel)
                                        <option value="{{ $catKey }}" {{ old('category') === $catKey ? 'selected' : '' }}>{{ $catLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted" for="moOfferType">Offer Type <span style="color:#fda4af;">*</span></label>
                                <select name="offer_type" id="moOfferType" class="form-select mo-select" data-mo-required="1" data-mo-label="Offer Type">
                                    <option value="combo" {{ old('offer_type', 'combo') === 'combo' ? 'selected' : '' }}>Linked Combo</option>
                                    <option value="service_freebie" {{ old('offer_type') === 'service_freebie' ? 'selected' : '' }}>Linked Service + Freebies</option>
                                    <option value="standalone" {{ old('offer_type') === 'standalone' ? 'selected' : '' }}>Selected Service(s) / Standalone Offer</option>
                                </select>
                            </div>

                            <div class="col-12" id="moComboWrap">
                                <label class="form-label small text-muted" for="moComboId">Select Combo (price &amp; services sync live) <span style="color:#fda4af;">*</span></label>
                                <select name="combo_id" id="moComboId" class="form-select mo-select" data-mo-label="Linked Combo">
                                    <option value="">Choose a combo...</option>
                                    @foreach ($combosList as $cb)
                                        <option value="{{ $cb->id }}" {{ (string) old('combo_id') === (string) $cb->id ? 'selected' : '' }}>
                                            {{ $cb->name }} — {{ number_format((float) $cb->price, 0) }} QAR ({{ $cb->services->count() }} services)
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 d-none" id="moFreebieWrap">
                                <label class="form-label small text-muted" for="moFreebieId">Select Service + Freebies Package (price &amp; freebies sync live) <span style="color:#fda4af;">*</span></label>
                                <select name="service_freebie_id" id="moFreebieId" class="form-select mo-select" data-mo-label="Linked Service + Freebies">
                                    <option value="">Choose a Service + Freebies offer...</option>
                                    @foreach ($freebiesList as $fp)
                                        <option value="{{ $fp->id }}" {{ (string) old('service_freebie_id') === (string) $fp->id ? 'selected' : '' }}>
                                            {{ $fp->name }} — {{ number_format((float) $fp->price, 0) }} QAR
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Collapsible Searchable Single / Multi-Service Catalog Picker --}}
                            <div class="col-12 d-none" id="moServicePickerWrap">
                                <div class="d-flex align-items-baseline justify-content-between gap-2 mb-1">
                                    <label class="form-label small text-muted mb-0" for="moSvcSearchInput">Select Service(s) from Catalog</label>
                                    <span class="small" style="font-size:11px; color:#8f7a73;">Pick one or multiple services &middot; names sync live</span>
                                </div>
                                <div class="mo-svc-picker" id="moSvcPickerBox">
                                    <div id="moSvcSelectedStrip" class="mo-svc-selected-strip d-none">
                                        <div class="d-flex align-items-center justify-content-between gap-2">
                                            <span id="moSvcSelectedCount" class="small mo-num fw-semibold" style="font-size:11.5px; color:#f3d9a4;">0 services selected</span>
                                            <button type="button" id="moSvcClearAllBtn" class="btn btn-link p-0 d-none" style="font-size:11.5px; color:#d98f83; text-decoration:none;">Clear all</button>
                                        </div>
                                        <div id="moSvcSelectedPills" class="d-flex flex-wrap gap-1"></div>
                                    </div>

                                    <div class="mo-svc-search-bar" id="moSvcTriggerBar">
                                        <i class="bx bx-search" style="color:#9e8780; font-size:15px;"></i>
                                        <input type="text" id="moSvcSearchInput" class="mo-svc-search-input" placeholder="Search or select services..." autocomplete="off">
                                        <button type="button" id="moSvcSearchClear" class="mo-svc-search-clear d-none" aria-label="Clear service search">&times;</button>
                                        <button type="button" id="moSvcToggleBtn" class="mo-svc-toggle-btn" aria-expanded="false" aria-controls="moSvcDropdownPanel" aria-label="Toggle services list">
                                            <i class="bx bx-chevron-down"></i>
                                        </button>
                                    </div>

                                    <div id="moSvcDropdownPanel" class="d-none">
                                        <div id="moSvcList" class="mo-svc-list">
                                            @forelse ($catalogServicesList as $svc)
                                                @php
                                                    $catName = $svc->category?->name ?? 'Uncategorized';
                                                    $isChecked = in_array((int) $svc->id, $oldServiceIds, true);
                                                @endphp
                                                <label class="mo-svc-row {{ $isChecked ? 'is-selected' : '' }}"
                                                       data-svc-row="1"
                                                       data-svc-id="{{ $svc->id }}"
                                                       data-svc-name="{{ $svc->name }}"
                                                       data-svc-search="{{ strtolower($svc->name . ' ' . $catName) }}">
                                                    <span class="d-flex align-items-center gap-2 min-w-0">
                                                        <input type="checkbox"
                                                               name="service_ids[]"
                                                               value="{{ $svc->id }}"
                                                               class="mo-svc-checkbox js-mo-svc-check"
                                                               data-svc-name="{{ $svc->name }}"
                                                               {{ $isChecked ? 'checked' : '' }}>
                                                        <span class="min-w-0">
                                                            <span class="d-block fw-semibold text-truncate" style="font-size:12.5px; color:#f8efe6;">{{ $svc->name }}</span>
                                                            <span class="d-block" style="font-size:11px; color:#9e8780;">{{ $catName }}@if ($svc->duration) &middot; {{ $svc->duration }} min @endif</span>
                                                        </span>
                                                    </span>
                                                    <span class="small mo-num fw-semibold flex-shrink-0" style="font-size:12px; color:#e5b8a8;">
                                                        {{ number_format((float) $svc->price, 0) }} QAR
                                                    </span>
                                                </label>
                                            @empty
                                                <div class="text-center py-3 small" style="color:#8f7a73;">No services available in the catalog yet.</div>
                                            @endforelse
                                            <div id="moSvcEmptySearch" class="text-center py-3 small d-none" style="color:#8f7a73;">No services match your search.</div>
                                        </div>

                                        <div class="mo-svc-dropdown-footer">
                                            <span id="moSvcFooterHint" class="small mo-num" style="font-size:11.5px; color:#9e8780;">Select one or more services</span>
                                            <button type="button" id="moSvcDoneBtn" class="mo-svc-done-btn">Done</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6" id="moPromoPriceWrap">
                                <label class="form-label small text-muted" for="moPromoPrice">Promo Price (QAR) <span class="d-none" id="moPriceReqStar" style="color:#fda4af;">*</span></label>
                                <input type="number" step="0.01" min="0" name="promo_price_qar" id="moPromoPrice" class="form-control mo-input mo-num" data-mo-label="Promo Price (QAR)" placeholder="Auto-synced" value="{{ old('promo_price_qar') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted" for="moBranchScope">Branch Scope <span style="color:#fda4af;">*</span></label>
                                <select name="branch_scope" id="moBranchScope" class="form-select mo-select" data-mo-required="1" data-mo-label="Branch Scope">
                                    <option value="all" {{ old('branch_scope', 'all') === 'all' ? 'selected' : '' }}>All Branches</option>
                                    <option value="old_airport" {{ old('branch_scope') === 'old_airport' ? 'selected' : '' }}>Old Airport</option>
                                    <option value="al_wakrah" {{ old('branch_scope') === 'al_wakrah' ? 'selected' : '' }}>Al Wakrah</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small text-muted" for="moValidFrom">Valid From (Qatar Date) <span style="color:#fda4af;">*</span></label>
                                <input type="date" name="valid_from" id="moValidFrom" class="form-control mo-input mo-num" data-mo-required="1" data-mo-label="Valid From"
                                    value="{{ old('valid_from', $todayQatar->copy()->startOfMonth()->toDateString()) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted" for="moValidUntil">Valid Until (Qatar Date) <span style="color:#fda4af;">*</span></label>
                                <input type="date" name="valid_until" id="moValidUntil" class="form-control mo-input mo-num" data-mo-required="1" data-mo-label="Valid Until"
                                    value="{{ old('valid_until', $todayQatar->copy()->endOfMonth()->toDateString()) }}">
                            </div>

                            <div class="col-12">
                                <div class="mo-tcs-callout" id="moTcsInfoBar">
                                    <div class="mo-tcs-header" id="moTcsHeaderToggle" role="button" tabindex="0" aria-expanded="true" aria-controls="moTcsBody">
                                        <div class="d-flex align-items-center gap-2 min-w-0" style="color:#f3d9a4; font-size:12px; font-weight:700;">
                                            <i class="bx bx-info-circle fs-6 flex-shrink-0"></i>
                                            <span id="moTcsTitleExpanded">Make sure to specify (if applicable):</span>
                                            <span id="moTcsTitleMinimized" class="d-none text-truncate" style="font-weight:600; color:#e5d2c9;">
                                                Make sure to specify: <span style="font-weight:400; color:#b8a29a;">Free services, color shades &amp; freebie timeframe</span>
                                            </span>
                                        </div>
                                        <span class="mo-tcs-toggle-pill" id="moTcsToggleLabel">Minimize &#9652;</span>
                                    </div>
                                    <div id="moTcsBody" class="mt-2">
                                        <ul class="mb-2 ps-3" style="font-size:11.5px; color:#cbb8b0; line-height:1.5;">
                                            <li><strong style="color:#f2e6e1;">Free Services List:</strong> Exact complimentary services included in the offer.</li>
                                            <li><strong style="color:#f2e6e1;">Global Hair Color Shades:</strong> Which shades are included or excluded (e.g., standard single-process global shades vs. fashion tones / bleach / highlights).</li>
                                            <li><strong style="color:#f2e6e1;">Pending Free Services Timeframe:</strong> How many days the client has to claim any pending/unused free services (e.g., same visit only, or valid within 14/30 days).</li>
                                        </ul>
                                        <div class="d-flex flex-wrap gap-1 pt-1">
                                            <button type="button" class="mo-tcs-chip js-mo-tcs-chip" data-tcs-line="Complimentary services included: [list exact free services]">+ Free Services</button>
                                            <button type="button" class="mo-tcs-chip js-mo-tcs-chip" data-tcs-line="Global hair color shades: [specify included/excluded shades]">+ Color Shades</button>
                                            <button type="button" class="mo-tcs-chip js-mo-tcs-chip" data-tcs-line="Pending free services must be redeemed within [X days / same visit]">+ Freebie Timeframe</button>
                                        </div>
                                    </div>
                                </div>

                                <label class="form-label small text-muted" for="moTerms">Terms &amp; Conditions (one per line) <span style="color:#fda4af;">*</span></label>
                                <textarea name="terms_and_conditions" id="moTerms" rows="3" class="form-control mo-input" data-mo-required="1" data-mo-label="Terms & Conditions"
                                    placeholder="Valid Sunday to Wednesday only&#10;Advance booking required via WhatsApp&#10;Cannot be combined with other discounts or vouchers">{{ old('terms_and_conditions') }}</textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label small text-muted" for="moStaffNotes">Internal Staff Notes (optional)</label>
                                <textarea name="staff_notes" id="moStaffNotes" rows="2" class="form-control mo-input"
                                    placeholder="e.g. Reception: confirm hair length tier before starting service.">{{ old('staff_notes') }}</textarea>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input type="hidden" name="is_active" value="0">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="moIsActive" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="moIsActive">Published / Active on Offers Board</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- TAB 2: 7-PILLAR OFFER ARCHITECTURE (ALL 7 REQUIRED) --}}
                    <div class="mo-step-pane d-none" data-mo-step-pane="2">
                        <div class="d-flex flex-column gap-3">
                            @foreach ($pillarDefs as $pDef)
                                <div>
                                    <label class="form-label small fw-bold mb-0" for="moPillar_{{ $pDef['key'] }}" style="color:#f8efe6;">
                                        {{ $pDef['number'] }}. {{ $pDef['label'] }} <span style="color:#fda4af;">*</span>
                                    </label>
                                    <div class="mo-pillar-hint">{{ $pDef['hint'] }}</div>
                                    <textarea name="{{ $pDef['key'] }}"
                                        id="moPillar_{{ $pDef['key'] }}"
                                        rows="2"
                                        class="form-control mo-input"
                                        data-mo-required="1"
                                        data-mo-label="{{ $pDef['number'] }}. {{ $pDef['label'] }}"
                                        placeholder="{{ $pDef['placeholder'] }}">{{ old($pDef['key']) }}</textarea>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- TAB 3: SCRIPTS & GRAPHIC (OPTIONAL ON INITIAL SAVE) --}}
                    <div class="mo-step-pane d-none" data-mo-step-pane="3">
                        <div class="d-flex flex-column gap-3">
                            <div>
                                <label class="form-label small fw-bold mb-0" for="moGraphicFileInput" style="color:#f8efe6;">
                                    Offer Graphic Post <span class="fw-normal" style="color:#9e8780;">(optional — flyer / social post for quick WhatsApp sharing)</span>
                                </label>
                                <div class="mo-pillar-hint">Upload the promotional flyer (JPG, PNG, or WEBP up to 10 MB). Team members can view, copy, or download it in 1 click.</div>
                                <input type="file" name="graphic_post" id="moGraphicFileInput" class="d-none" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                <input type="hidden" name="remove_graphic_post" id="moRemoveGraphicInput" value="0">

                                <div id="moModalGraphicEmpty" class="mo-graphic-dropzone" role="button" tabindex="0" aria-label="Upload offer graphic post">
                                    <div class="d-inline-flex align-items-center gap-2 fw-semibold" style="color:#f3d9a4; font-size:13px;">
                                        <i class="bx bx-image-add fs-5"></i>
                                        <span>Drag &amp; drop graphic post here or click to browse</span>
                                    </div>
                                    <div class="small mt-1" style="color:#8f7a73; font-size:11.5px;">JPG, PNG, WEBP &middot; up to 10 MB</div>
                                </div>

                                <div id="moModalGraphicPreview" class="mo-modal-graphic-card d-none">
                                    <div class="d-flex align-items-center gap-3 min-w-0">
                                        <img id="moModalGraphicThumb" class="mo-modal-graphic-thumb" src="" alt="Offer Graphic Thumbnail">
                                        <div class="min-w-0">
                                            <div id="moModalGraphicName" class="small fw-semibold text-truncate" style="color:#f8efe6;">offer-graphic.png</div>
                                            <div id="moModalGraphicMeta" class="small" style="color:#9e8780; font-size:11.5px;">Current graphic post</div>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                        <button type="button" id="moModalGraphicChangeBtn" class="mo-graphic-btn" style="padding:5px 11px; font-size:12px;">Change</button>
                                        <button type="button" id="moModalGraphicRemoveBtn" class="mo-graphic-btn is-danger" style="padding:5px 11px; font-size:12px;">Remove</button>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="form-label small fw-bold mb-0" for="moWhatsappScript" style="color:#f8efe6;">
                                    WhatsApp / DM Scripts <span class="fw-normal" style="color:#9e8780;">(optional — can paste now or right after saving)</span>
                                </label>
                                <div class="mo-pillar-hint">Ready-to-send replies for chat agents (First Inquiry Reply, Price Objection / Length Confirmation, Follow-up &amp; Booking Close).</div>
                                <textarea name="whatsapp_script" id="moWhatsappScript" rows="5" class="form-control mo-input mo-script-textarea"
                                    placeholder="[1. First Inquiry Reply]&#10;Hi darling ✨ Our November Glass-Hair Experience is 299 QAR and includes...&#10;&#10;[2. Price Objection / Length Confirmation]&#10;We confirm your exact hair length tier upfront before we begin — zero chair surprises...&#10;&#10;[3. Follow-Up & Booking Close]&#10;We only have 6 slots left this week across Old Airport & Al Wakrah. Which branch suits you best?">{{ old('whatsapp_script') }}</textarea>
                            </div>

                            <div>
                                <label class="form-label small fw-bold mb-0" for="moVideoScript" style="color:#f8efe6;">
                                    Video Shooting Script <span class="fw-normal" style="color:#9e8780;">(optional — shoot brief for social media team)</span>
                                </label>
                                <div class="mo-pillar-hint">3-Second Hook, Before/Problem Shot, Consultation &amp; Value Stack Visuals, Guarantee &amp; Scarcity CTA.</div>
                                <textarea name="video_shooting_script" id="moVideoScript" rows="5" class="form-control mo-input mo-script-textarea"
                                    placeholder="[0:00–0:03 Hook]&#10;Close-up hair flip under ring light: 'Doha humidity ruining your blowout in 20 minutes?'&#10;&#10;[0:03–0:10 Before / Problem]&#10;Show frizzy before strand + stylist scalp consultation...&#10;&#10;[0:10–0:22 Value Stack Visuals]&#10;Fast cuts of clarifying detox, steam treatment, and gloss finish...&#10;&#10;[0:22–0:30 Guarantee & Scarcity CTA]&#10;'Backed by our 7-Day Silk Promise — only 25 slots this week. DM GLOW to book.'">{{ old('video_shooting_script') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" id="moPrevStepBtn" class="btn btn-sm mo-footer-ghost-btn d-none">
                            <i class="bx bx-chevron-left"></i> Previous
                        </button>
                        <button type="button" id="moNextStepBtn" class="btn btn-sm mo-footer-next-btn">
                            Next <i class="bx bx-chevron-right"></i>
                        </button>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm mo-footer-ghost-btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn fw-bold px-4" style="background:linear-gradient(135deg,#d98f83,#c48479); color:#1b1315; border:none; border-radius:999px;">
                            Save Monthly Offer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
    (function () {
        if (window.__laleenMonthlyOffersBound) return;
        window.__laleenMonthlyOffersBound = true;

        const pillarKeys = @json(array_column($pillarDefs, 'key'));

        function showMoToast(msg) {
            const existing = document.getElementById('moToastEl');
            if (existing) existing.remove();
            const el = document.createElement('div');
            el.id = 'moToastEl';
            el.className = 'mo-toast';
            el.innerHTML = '<i class="bx bx-check-circle fs-5"></i><span></span>';
            el.querySelector('span').textContent = msg;
            document.body.appendChild(el);
            setTimeout(() => {
                el.style.transition = 'opacity 0.25s ease';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 260);
            }, 3000);
        }

        async function copyTextWithFeedback(text, successMsg) {
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    ta.remove();
                }
                showMoToast(successMsg);
            } catch (e) {
                showMoToast('Unable to copy automatically.');
            }
        }

        function getOfferPayloadById(offerId) {
            const scriptEl = document.getElementById(`moOfferData-${offerId}`);
            if (!scriptEl) return null;
            try {
                return JSON.parse(scriptEl.textContent || '{}');
            } catch (e) {
                return null;
            }
        }

        // ---------------- FILTERING (Status + Category + Branch + Search) ----------------
        let activeStatus = 'active';
        let activeCategory = 'all';
        const statusBtns = document.querySelectorAll('[data-mo-status]');
        const catBtns = Array.from(document.querySelectorAll('[data-mo-cat]'));
        const catSections = Array.from(document.querySelectorAll('[data-mo-section]'));
        const branchSelect = document.getElementById('moBranchFilter');
        const searchInput = document.getElementById('moSearchInput');
        const cards = Array.from(document.querySelectorAll('.mo-card-col'));
        const emptyState = document.getElementById('moEmptyState');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('#monthlyOfferForm input[name="_token"]')?.value
            || '{{ csrf_token() }}';

        function setActiveCategory(catKey) {
            activeCategory = catKey || 'all';
            catBtns.forEach(btn => {
                const isSelected = btn.dataset.moCat === activeCategory;
                btn.classList.toggle('active', isSelected);
                btn.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            });
        }

        function applyOfferFilters() {
            const br = branchSelect ? branchSelect.value : 'all';
            const q = searchInput ? searchInput.value.trim().toLowerCase() : '';
            const baseCountsByCat = { all: 0, hair: 0, makeup: 0, skin: 0, combo: 0, uncategorized: 0 };

            // Pass 1: evaluate status + branch + search to compute live category strip counts
            const baseMatchMap = new Map();
            cards.forEach(card => {
                const cStatus = card.dataset.moCardStatus;
                const cBranch = card.dataset.moCardBranch;
                const cCat = card.dataset.moCardCategory || 'uncategorized';
                const cSearch = card.dataset.moCardSearch || '';

                const matchBase = (activeStatus === 'all' || cStatus === activeStatus)
                    && (br === 'all' || cBranch === 'all' || cBranch === br)
                    && (!q || cSearch.includes(q));

                baseMatchMap.set(card, matchBase);
                if (matchBase) {
                    baseCountsByCat.all++;
                    if (Object.prototype.hasOwnProperty.call(baseCountsByCat, cCat)) {
                        baseCountsByCat[cCat]++;
                    } else {
                        baseCountsByCat.uncategorized++;
                    }
                }
            });

            // Hide Uncategorized pill whenever 0 uncategorized offers match; fall back cleanly to All if it was selected
            const uncatBtn = catBtns.find(b => b.dataset.moCat === 'uncategorized');
            if (uncatBtn) {
                uncatBtn.classList.toggle('d-none', baseCountsByCat.uncategorized === 0);
                if (activeCategory === 'uncategorized' && baseCountsByCat.uncategorized === 0) {
                    setActiveCategory('all');
                }
            }

            // Update pill counts
            document.querySelectorAll('.js-mo-cat-count').forEach(el => {
                const key = el.dataset.catCount;
                if (key && Object.prototype.hasOwnProperty.call(baseCountsByCat, key)) {
                    el.textContent = String(baseCountsByCat[key]);
                }
            });

            // Pass 2: show/hide cards & category sections
            let visibleCount = 0;
            const visibleBySection = { hair: 0, makeup: 0, skin: 0, combo: 0, uncategorized: 0 };

            cards.forEach(card => {
                const cCat = card.dataset.moCardCategory || 'uncategorized';
                const show = Boolean(baseMatchMap.get(card)) && (activeCategory === 'all' || cCat === activeCategory);
                card.style.display = show ? '' : 'none';
                if (show) {
                    visibleCount++;
                    if (Object.prototype.hasOwnProperty.call(visibleBySection, cCat)) {
                        visibleBySection[cCat]++;
                    }
                }
            });

            const showSectionHeadings = activeCategory === 'all';
            catSections.forEach(section => {
                const secKey = section.dataset.moSection;
                const secCount = visibleBySection[secKey] || 0;
                const shouldShowSection = secCount > 0 && (activeCategory === 'all' || activeCategory === secKey);
                section.classList.toggle('d-none', !shouldShowSection);
                const headEl = section.querySelector('.mo-cat-section-head');
                if (headEl) {
                    headEl.classList.toggle('d-none', !showSectionHeadings);
                }
                const countEl = section.querySelector('.js-mo-section-count');
                if (countEl) {
                    countEl.textContent = String(secCount);
                }
            });

            if (emptyState) {
                emptyState.classList.toggle('d-none', visibleCount > 0);
            }
        }

        statusBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                activeStatus = btn.dataset.moStatus;
                statusBtns.forEach(b => b.classList.toggle('active', b === btn));
                applyOfferFilters();
            });
        });
        catBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                setActiveCategory(btn.dataset.moCat);
                applyOfferFilters();
            });
        });
        branchSelect?.addEventListener('change', applyOfferFilters);
        searchInput?.addEventListener('input', applyOfferFilters);

        // 1-Click Move to Category inside card overflow menu (Admin / full editors only)
        document.querySelectorAll('.js-mo-move-cat').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (btn.disabled) return;

                const offerId = btn.dataset.offerId;
                const moveUrl = btn.dataset.moveUrl;
                const targetCategory = btn.dataset.targetCategory;
                const targetLabel = btn.dataset.targetLabel || targetCategory;
                const cardCol = btn.closest('.mo-card-col');
                if (!offerId || !moveUrl || !targetCategory || !cardCol) return;

                const menuItems = Array.from(cardCol.querySelectorAll('.js-mo-move-cat'));
                menuItems.forEach(item => { item.disabled = true; });

                try {
                    const res = await fetch(moveUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ category: targetCategory }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        throw new Error(data.message || 'Could not move offer category.');
                    }

                    const newCat = data.category || targetCategory;
                    const newLabel = data.category_label || targetLabel;

                    // Move DOM card node smoothly into target category grid
                    cardCol.dataset.moCardCategory = newCat;
                    const targetGrid = document.querySelector(`[data-mo-section-grid="${newCat}"]`);
                    if (targetGrid && cardCol.parentElement !== targetGrid) {
                        targetGrid.appendChild(cardCol);
                    }

                    // Keep embedded JSON Playbook payload in sync for Drawer & Edit Modal
                    const scriptEl = document.getElementById(`moOfferData-${offerId}`);
                    if (scriptEl) {
                        try {
                            const payload = JSON.parse(scriptEl.textContent || '{}');
                            payload.category = newCat;
                            payload.category_label = newLabel;
                            scriptEl.textContent = JSON.stringify(payload);
                            if (activeDrawerOffer && String(activeDrawerOffer.id) === String(offerId)) {
                                activeDrawerOffer.category = newCat;
                                activeDrawerOffer.category_label = newLabel;
                            }
                        } catch (err) {}
                    }

                    // Sync checkmarks and current highlight inside the card's Move To menu
                    menuItems.forEach(item => {
                        const isCur = item.dataset.targetCategory === newCat;
                        item.classList.toggle('is-current', isCur);
                        item.disabled = isCur;
                        if (isCur) {
                            item.setAttribute('aria-current', 'true');
                        } else {
                            item.removeAttribute('aria-current');
                        }
                        item.querySelector('.mo-move-check')?.classList.toggle('d-none', !isCur);
                    });

                    closeAllCardMenus();
                    applyOfferFilters();
                    showMoToast(`Moved to ${newLabel}`);
                } catch (err) {
                    // Restore interactive state on failure
                    const prevCat = cardCol.dataset.moCardCategory || '';
                    menuItems.forEach(item => {
                        const isCur = item.dataset.targetCategory === prevCat;
                        item.disabled = isCur;
                    });
                    showMoToast(err?.message || 'Unable to move offer right now.');
                }
            });
        });

        // Quick Copy buttons on Offer Cards (stops propagation so card Playbook does not open)
        document.querySelectorAll('.js-mo-copy').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                copyTextWithFeedback(btn.dataset.copyText || '', 'WhatsApp script copied to clipboard.');
                const labelEl = btn.querySelector('.js-mo-copy-label');
                if (labelEl) {
                    const orig = labelEl.dataset.origLabel || labelEl.textContent;
                    labelEl.dataset.origLabel = orig;
                    labelEl.textContent = 'Copied \u2713';
                    btn.classList.add('is-copied');
                    clearTimeout(btn._copyResetTimer);
                    btn._copyResetTimer = setTimeout(() => {
                        labelEl.textContent = orig;
                        btn.classList.remove('is-copied');
                    }, 1800);
                }
            });
        });

        // 3-Dot Overflow Action Popover Menu (Admin / services editors)
        function closeAllCardMenus() {
            document.querySelectorAll('.js-mo-card-menu.is-open').forEach(menu => {
                menu.classList.remove('is-open');
                menu.querySelector('.js-mo-menu-popover')?.classList.add('d-none');
                menu.querySelector('.js-mo-menu-trigger')?.setAttribute('aria-expanded', 'false');
            });
        }

        document.querySelectorAll('.js-mo-menu-trigger').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const menu = trigger.closest('.js-mo-card-menu');
                const popover = menu?.querySelector('.js-mo-menu-popover');
                if (!menu || !popover) return;
                const willOpen = popover.classList.contains('d-none');
                closeAllCardMenus();
                if (willOpen) {
                    menu.classList.add('is-open');
                    popover.classList.remove('d-none');
                    trigger.setAttribute('aria-expanded', 'true');
                }
            });
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.js-mo-card-menu')) {
                closeAllCardMenus();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.querySelector('.js-mo-card-menu.is-open')) {
                closeAllCardMenus();
            }
        });

        // Prevent footer Edit/Archive/Delete wrappers from triggering full-card Playbook open
        document.querySelectorAll('.js-mo-stop-prop').forEach(el => {
            el.addEventListener('click', (e) => e.stopPropagation());
        });

        // ---------------- 4-TAB EXECUTIVE OFFER PLAYBOOK DRAWER ----------------
        const drawerBackdrop = document.getElementById('moPlaybookBackdrop');
        const drawerEl = document.getElementById('moPlaybookDrawer');
        if (drawerBackdrop && drawerBackdrop.parentElement !== document.body) {
            document.body.appendChild(drawerBackdrop);
        }
        if (drawerEl && drawerEl.parentElement !== document.body) {
            document.body.appendChild(drawerEl);
        }

        const drawerTabs = Array.from(document.querySelectorAll('[data-drawer-tab]'));
        const drawerPanes = Array.from(document.querySelectorAll('[data-drawer-pane]'));
        let activeDrawerOffer = null;

        function setDrawerTab(tabKey) {
            drawerTabs.forEach(t => t.classList.toggle('active', t.dataset.drawerTab === tabKey));
            drawerPanes.forEach(p => p.classList.toggle('d-none', p.dataset.drawerPane !== tabKey));
        }

        drawerTabs.forEach(tab => {
            tab.addEventListener('click', () => setDrawerTab(tab.dataset.drawerTab));
        });

        function closePlaybookDrawer() {
            drawerBackdrop?.classList.remove('is-open');
            drawerEl?.classList.remove('is-open');
            drawerBackdrop?.setAttribute('aria-hidden', 'true');
            drawerEl?.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function openPlaybookDrawer(offer, initialTab = 'pillars') {
            if (!offer || !drawerEl) return;
            activeDrawerOffer = offer;

            const toneColors = {
                emerald: 'background:rgba(52,211,153,0.14); border:1px solid rgba(52,211,153,0.34); color:#6ee7b7;',
                amber: 'background:rgba(245,158,11,0.14); border:1px solid rgba(245,158,11,0.34); color:#fbbf24;',
                muted: 'background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:#b8a29a;',
            };
            const typeColors = {
                combo: 'background:rgba(212,163,115,0.14); border:1px solid rgba(212,163,115,0.34); color:#f3d9a4;',
                service_freebie: 'background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.30); color:#6ee7b7;',
                standalone: 'background:rgba(217,143,131,0.15); border:1px solid rgba(217,143,131,0.34); color:#e79a91;',
            };

            const badgesEl = document.getElementById('moDrawerBadges');
            if (badgesEl) {
                badgesEl.innerHTML = `
                    <span class="mo-badge" style="${typeColors[offer.offer_type] || typeColors.standalone}">${escapeHtml(offer.type_label)}</span>
                    <span class="mo-badge" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:#d6c3bc;"><i class="bx bx-map-pin"></i> ${escapeHtml(offer.branch_label)}</span>
                    <span class="mo-badge mo-num" style="${toneColors[offer.status?.tone] || toneColors.muted}">${escapeHtml(offer.status?.label || '')}</span>
                `;
            }

            document.getElementById('moDrawerTitle').textContent = offer.title || 'Offer Playbook';
            const drawerMetaParts = [
                offer.status_word || '',
                offer.category_label || '',
                offer.branch_label || '',
                offer.date_short_cue || (offer.valid_range_label ? `Valid ${offer.valid_range_label}` : ''),
            ].filter(Boolean);
            document.getElementById('moDrawerMeta').textContent = drawerMetaParts.join(' · ');
            document.getElementById('moDrawerPrice').textContent = `${Number(offer.effective_price_qar || 0).toLocaleString('en-US', { maximumFractionDigits: 0 })} QAR`;

            // Update Playbook Drawer tab readiness indicators
            const tabPillarsCount = document.getElementById('moDrawerTabPillarsCount');
            if (tabPillarsCount) {
                tabPillarsCount.textContent = `${offer.filled_pillars_count ?? 0}/7`;
            }
            const tabWaDot = document.getElementById('moDrawerTabWaDot');
            if (tabWaDot) {
                tabWaDot.classList.toggle('is-ready', Boolean(offer.has_whatsapp_script));
                tabWaDot.title = offer.has_whatsapp_script ? 'Custom WhatsApp script ready' : 'Using auto-formatted pitch';
            }
            const tabVideoDot = document.getElementById('moDrawerTabVideoDot');
            if (tabVideoDot) {
                tabVideoDot.classList.toggle('is-ready', Boolean(offer.has_video_script));
                tabVideoDot.title = offer.has_video_script ? 'Video shooting script ready' : 'No video shooting script yet';
            }
            renderDrawerGraphicState(offer);

            // Tab 1: 7-Pillar Architecture
            const pillarsListEl = document.getElementById('moDrawerPillarsList');
            if (pillarsListEl) {
                pillarsListEl.innerHTML = (offer.pillars || []).map(p => `
                    <div class="mo-pillar-row">
                        <span class="mo-pillar-circle mo-num">${p.number}</span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-bold" style="color:#f8efe6; font-size:14px;">${escapeHtml(p.label)}</div>
                            <div class="small mb-1" style="color:#8f7a73; font-size:11.5px;">${escapeHtml(p.hint)}</div>
                            <div style="color:${p.value ? '#eadbd6' : '#705c56'}; font-size:13.5px; line-height:1.55; white-space:pre-line;">
                                ${p.value ? escapeHtml(p.value) : 'Not added yet.'}
                            </div>
                        </div>
                    </div>
                `).join('');
            }

            // Linked inclusions
            const incWrap = document.getElementById('moDrawerIncludedWrap');
            const incList = document.getElementById('moDrawerIncludedList');
            if (incWrap && incList) {
                const items = offer.included_services || [];
                if (items.length > 0) {
                    incWrap.classList.remove('d-none');
                    incList.innerHTML = items.map(inc => `
                        <div class="d-flex align-items-center justify-content-between gap-2 py-1" style="border-bottom:1px solid rgba(255,255,255,0.04); font-size:13px;">
                            <span class="d-flex align-items-center gap-2">
                                <i class="bx ${inc.is_free ? 'bx-gift' : 'bx-check'}" style="color:${inc.is_free ? '#6ee7b7' : '#d98f83'};"></i>
                                <span>${escapeHtml(inc.name)}</span>
                            </span>
                            ${inc.is_free
                                ? '<span class="mo-badge" style="background:rgba(52,211,153,0.14); border:1px solid rgba(52,211,153,0.34); color:#6ee7b7;">FREE</span>'
                                : (inc.meta ? `<span class="small mo-num" style="color:#9e8780;">${escapeHtml(inc.meta)}</span>` : '')}
                        </div>
                    `).join('');
                } else {
                    incWrap.classList.add('d-none');
                }
            }

            // Tab 2: Terms & Conditions
            document.getElementById('moDrawerTermsBranch').textContent = offer.branch_label || 'All Branches';
            document.getElementById('moDrawerTermsDates').textContent = offer.valid_range_label || '';
            const termsListEl = document.getElementById('moDrawerTermsList');
            if (termsListEl) {
                termsListEl.innerHTML = (offer.terms_lines || []).map(t => `<li class="mb-1">${escapeHtml(t)}</li>`).join('');
            }
            const notesWrap = document.getElementById('moDrawerNotesWrap');
            const notesText = document.getElementById('moDrawerNotesText');
            if (offer.staff_notes && offer.staff_notes.trim() !== '') {
                notesWrap?.classList.remove('d-none');
                if (notesText) notesText.textContent = offer.staff_notes;
            } else {
                notesWrap?.classList.add('d-none');
            }

            // Tab 3: WhatsApp Scripts
            const waFallback = document.getElementById('moDrawerWaFallbackNotice');
            const waContent = document.getElementById('moDrawerWaContent');
            const waAddBtn = document.getElementById('moDrawerAddWaBtn');
            if (offer.has_whatsapp_script) {
                waFallback?.classList.add('d-none');
                waAddBtn?.classList.add('d-none');
                if (waContent) waContent.textContent = offer.whatsapp_script;
            } else {
                waFallback?.classList.remove('d-none');
                waAddBtn?.classList.remove('d-none');
                if (waContent) waContent.textContent = offer.auto_whatsapp_pitch || '';
            }

            // Tab 4: Video Shooting Script
            const vidHas = document.getElementById('moDrawerVideoHasScript');
            const vidEmpty = document.getElementById('moDrawerVideoEmpty');
            const vidContent = document.getElementById('moDrawerVideoContent');
            if (offer.has_video_script) {
                vidHas?.classList.remove('d-none');
                vidEmpty?.classList.add('d-none');
                if (vidContent) vidContent.textContent = offer.video_shooting_script;
            } else {
                vidHas?.classList.add('d-none');
                vidEmpty?.classList.remove('d-none');
            }

            setDrawerTab(initialTab);
            drawerBackdrop?.classList.add('is-open');
            drawerEl.classList.add('is-open');
            drawerBackdrop?.setAttribute('aria-hidden', 'false');
            drawerEl.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        // Full card click + Playbook -> link both open the 4-tab Offer Playbook drawer
        document.querySelectorAll('.js-mo-card-open').forEach(cardEl => {
            cardEl.addEventListener('click', (e) => {
                if (e.target.closest('button, a, form, input, select, .js-mo-card-menu')) return;
                closeAllCardMenus();
                const payload = getOfferPayloadById(cardEl.dataset.offerId);
                if (payload) openPlaybookDrawer(payload, 'pillars');
            });
            cardEl.addEventListener('keydown', (e) => {
                if ((e.key === 'Enter' || e.key === ' ') && e.target === cardEl) {
                    e.preventDefault();
                    closeAllCardMenus();
                    const payload = getOfferPayloadById(cardEl.dataset.offerId);
                    if (payload) openPlaybookDrawer(payload, 'pillars');
                }
            });
        });

        document.querySelectorAll('.js-mo-open-playbook').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                closeAllCardMenus();
                const payload = getOfferPayloadById(btn.dataset.offerId);
                if (payload) openPlaybookDrawer(payload, 'pillars');
            });
        });

        document.getElementById('moDrawerCloseBtn')?.addEventListener('click', closePlaybookDrawer);
        drawerBackdrop?.addEventListener('click', closePlaybookDrawer);

        document.getElementById('moDrawerCopyWaBtn')?.addEventListener('click', () => {
            if (!activeDrawerOffer) return;
            copyTextWithFeedback(activeDrawerOffer.whatsapp_copy_text || '', 'WhatsApp script copied to clipboard.');
        });

        document.getElementById('moDrawerCopyVideoBtn')?.addEventListener('click', () => {
            if (!activeDrawerOffer || !activeDrawerOffer.video_shooting_script) return;
            copyTextWithFeedback(activeDrawerOffer.video_shooting_script, 'Video shooting script copied to clipboard.');
        });

        // ---------------- GRAPHIC POST LIGHTBOX, DRAWER TAB & QUICK UPLOAD/REMOVE ----------------
        const lightboxEl = document.getElementById('moGraphicLightbox');
        if (lightboxEl && lightboxEl.parentElement !== document.body) {
            document.body.appendChild(lightboxEl);
        }
        const quickGraphicInput = document.getElementById('moQuickGraphicFileInput');
        let activeLightboxOffer = null;
        let pendingQuickUploadOfferId = null;

        function renderDrawerGraphicState(offer) {
            if (!offer) return;
            const hasGraphic = Boolean(offer.has_graphic_post && offer.graphic_post_url);
            const tabGraphicDot = document.getElementById('moDrawerTabGraphicDot');
            if (tabGraphicDot) {
                tabGraphicDot.classList.toggle('is-ready', hasGraphic);
                tabGraphicDot.title = hasGraphic ? 'Graphic post uploaded' : 'No graphic post uploaded yet';
            }
            const hasWrap = document.getElementById('moDrawerGraphicHasImage');
            const emptyWrap = document.getElementById('moDrawerGraphicEmpty');
            const imgEl = document.getElementById('moDrawerGraphicImg');
            const dlLink = document.getElementById('moDrawerDownloadGraphicLink');
            if (hasGraphic) {
                hasWrap?.classList.remove('d-none');
                emptyWrap?.classList.add('d-none');
                if (imgEl) {
                    imgEl.src = offer.graphic_post_url;
                    imgEl.alt = `${offer.title || 'Offer'} Graphic Post`;
                }
                if (dlLink) {
                    dlLink.href = offer.graphic_download_url || offer.graphic_post_url;
                    if (offer.graphic_filename) {
                        dlLink.setAttribute('download', offer.graphic_filename);
                    }
                }
            } else {
                hasWrap?.classList.add('d-none');
                emptyWrap?.classList.remove('d-none');
                if (imgEl) imgEl.removeAttribute('src');
            }
        }

        function openGraphicLightbox(offer) {
            if (!offer || !offer.has_graphic_post || !offer.graphic_post_url || !lightboxEl) return;
            activeLightboxOffer = offer;
            document.getElementById('moLightboxTitle').textContent = offer.title || 'Offer Graphic';
            const metaParts = [
                offer.category_label || '',
                offer.branch_label || '',
                offer.date_short_cue || (offer.valid_until ? `Valid until ${offer.valid_until}` : ''),
            ].filter(Boolean);
            document.getElementById('moLightboxMeta').textContent = metaParts.join(' · ');
            document.getElementById('moLightboxPrice').textContent = `${Number(offer.effective_price_qar || 0).toLocaleString('en-US', { maximumFractionDigits: 0 })} QAR`;

            const imgEl = document.getElementById('moLightboxImg');
            if (imgEl) {
                imgEl.src = offer.graphic_post_url;
                imgEl.alt = `${offer.title || 'Offer'} Graphic Post`;
            }
            const dlLink = document.getElementById('moLightboxDownloadLink');
            if (dlLink) {
                dlLink.href = offer.graphic_download_url || offer.graphic_post_url;
                if (offer.graphic_filename) {
                    dlLink.setAttribute('download', offer.graphic_filename);
                }
            }

            lightboxEl.classList.add('is-open');
            lightboxEl.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeGraphicLightbox() {
            if (!lightboxEl) return;
            lightboxEl.classList.remove('is-open');
            lightboxEl.setAttribute('aria-hidden', 'true');
            activeLightboxOffer = null;
            if (!drawerEl?.classList.contains('is-open')) {
                document.body.style.overflow = '';
            }
        }

        function syncOfferGraphicState(offerId, graphicData) {
            const scriptEl = document.getElementById(`moOfferData-${offerId}`);
            let updatedOffer = null;
            if (scriptEl) {
                try {
                    const payload = JSON.parse(scriptEl.textContent || '{}');
                    const hasImg = Boolean(graphicData && graphicData.graphic_post_url);
                    payload.has_graphic_post = hasImg;
                    payload.graphic_post_url = hasImg ? graphicData.graphic_post_url : null;
                    payload.graphic_download_url = hasImg ? (graphicData.download_url || graphicData.graphic_download_url || null) : null;
                    payload.graphic_filename = hasImg ? (graphicData.graphic_filename || null) : null;
                    scriptEl.textContent = JSON.stringify(payload);
                    updatedOffer = payload;
                } catch (e) {}
            }

            const hasImg = Boolean(updatedOffer && updatedOffer.has_graphic_post);
            const cardCol = document.querySelector(`.mo-card-col[data-mo-card-id="${offerId}"]`);
            if (cardCol) {
                cardCol.querySelector('.js-mo-open-graphic')?.classList.toggle('d-none', !hasImg);
                const uploadLabel = cardCol.querySelector('.js-mo-upload-graphic-label');
                if (uploadLabel) {
                    uploadLabel.textContent = hasImg ? 'Change Graphic' : 'Upload Graphic';
                }
            }

            if (activeDrawerOffer && String(activeDrawerOffer.id) === String(offerId) && updatedOffer) {
                activeDrawerOffer = updatedOffer;
                renderDrawerGraphicState(updatedOffer);
            }

            if (activeLightboxOffer && String(activeLightboxOffer.id) === String(offerId)) {
                if (hasImg && updatedOffer) {
                    openGraphicLightbox(updatedOffer);
                } else {
                    closeGraphicLightbox();
                }
            }

            return updatedOffer;
        }

        function validateGraphicFileClient(file) {
            if (!file) return 'Please select an image file.';
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            const allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            if (!allowedTypes.includes(file.type) && !allowedExts.includes(ext)) {
                return 'Please choose a JPG, PNG, or WEBP image.';
            }
            if (file.size > 10 * 1024 * 1024) {
                return 'Image exceeds the 10 MB limit.';
            }
            return null;
        }

        async function uploadOfferGraphicFile(offerId, file) {
            const errMsg = validateGraphicFileClient(file);
            if (errMsg) {
                showMoToast(errMsg);
                return;
            }
            const offer = getOfferPayloadById(offerId);
            if (!offer || !offer.graphic_upload_url) return;

            const wasReplacing = Boolean(offer.has_graphic_post);
            const fd = new FormData();
            fd.append('graphic_post', file);

            try {
                const res = await fetch(offer.graphic_upload_url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: fd,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.ok) {
                    const firstErr = data?.errors?.graphic_post?.[0] || data?.message || 'Unable to upload graphic post.';
                    throw new Error(firstErr);
                }

                syncOfferGraphicState(offerId, data);
                showMoToast(wasReplacing ? 'Graphic post updated' : 'Graphic post uploaded');
            } catch (err) {
                showMoToast(err?.message || 'Unable to upload graphic post.');
            }
        }

        async function removeOfferGraphic(offer) {
            if (!offer || !offer.graphic_delete_url) return;
            if (!window.confirm('Remove the graphic post from this offer?')) return;

            try {
                const res = await fetch(offer.graphic_delete_url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.ok) {
                    throw new Error(data?.message || 'Unable to remove graphic post.');
                }
                syncOfferGraphicState(offer.id, null);
                showMoToast('Graphic post removed');
            } catch (err) {
                showMoToast(err?.message || 'Unable to remove graphic post.');
            }
        }

        async function convertBlobToPngBlob(blob) {
            if (blob.type === 'image/png') return blob;
            const objUrl = URL.createObjectURL(blob);
            try {
                const img = await new Promise((resolve, reject) => {
                    const image = new Image();
                    image.onload = () => resolve(image);
                    image.onerror = reject;
                    image.src = objUrl;
                });
                const canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth || img.width;
                canvas.height = img.naturalHeight || img.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                return await new Promise((resolve, reject) => {
                    canvas.toBlob((out) => (out ? resolve(out) : reject(new Error('PNG export failed'))), 'image/png');
                });
            } finally {
                URL.revokeObjectURL(objUrl);
            }
        }

        async function copyOfferGraphicToClipboard(offer, triggerBtn) {
            if (!offer || !offer.has_graphic_post) return;
            const labelEl = triggerBtn?.querySelector('.js-mo-graphic-copy-label');
            const origLabel = labelEl?.dataset.origLabel || labelEl?.textContent || 'Copy Image';
            if (labelEl) {
                labelEl.dataset.origLabel = origLabel;
                labelEl.textContent = 'Copying...';
            }

            try {
                if (!navigator.clipboard || typeof navigator.clipboard.write !== 'function' || typeof window.ClipboardItem === 'undefined') {
                    throw new Error('Clipboard image API unavailable');
                }
                const fetchUrl = offer.graphic_download_url || offer.graphic_post_url;
                const res = await fetch(fetchUrl, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('Image fetch failed');
                const blob = await res.blob();
                const pngBlob = await convertBlobToPngBlob(blob);
                await navigator.clipboard.write([new window.ClipboardItem({ 'image/png': pngBlob })]);

                if (labelEl) {
                    labelEl.textContent = 'Copied \u2713';
                    clearTimeout(triggerBtn._copyImgTimer);
                    triggerBtn._copyImgTimer = setTimeout(() => {
                        labelEl.textContent = origLabel;
                    }, 2000);
                }
                showMoToast('Graphic copied to clipboard — paste in WhatsApp');
            } catch (err) {
                if (labelEl) labelEl.textContent = origLabel;
                showMoToast('Direct image copy is not supported in this browser — use Download instead.');
            }
        }

        function triggerQuickGraphicPicker(offerId) {
            if (!quickGraphicInput || !offerId) return;
            pendingQuickUploadOfferId = String(offerId);
            quickGraphicInput.value = '';
            quickGraphicInput.click();
        }

        quickGraphicInput?.addEventListener('change', async () => {
            const file = quickGraphicInput.files?.[0];
            const targetOfferId = pendingQuickUploadOfferId;
            pendingQuickUploadOfferId = null;
            if (!file || !targetOfferId) return;
            await uploadOfferGraphicFile(targetOfferId, file);
        });

        // Card footer quiet "Graphic" button -> opens Lightbox directly
        document.querySelectorAll('.js-mo-open-graphic').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                closeAllCardMenus();
                const payload = getOfferPayloadById(btn.dataset.offerId);
                if (payload && payload.has_graphic_post) {
                    openGraphicLightbox(payload);
                }
            });
        });

        // Card overflow "..." Upload / Change Graphic quick action
        document.querySelectorAll('.js-mo-quick-upload-graphic').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeAllCardMenus();
                triggerQuickGraphicPicker(btn.dataset.offerId);
            });
        });

        // Lightbox controls
        document.getElementById('moLightboxCloseBtn')?.addEventListener('click', closeGraphicLightbox);
        lightboxEl?.addEventListener('click', (e) => {
            if (e.target === lightboxEl) closeGraphicLightbox();
        });
        document.getElementById('moLightboxCopyBtn')?.addEventListener('click', (e) => {
            if (activeLightboxOffer) copyOfferGraphicToClipboard(activeLightboxOffer, e.currentTarget);
        });
        document.getElementById('moLightboxReplaceBtn')?.addEventListener('click', () => {
            if (activeLightboxOffer) triggerQuickGraphicPicker(activeLightboxOffer.id);
        });
        document.getElementById('moLightboxRemoveBtn')?.addEventListener('click', () => {
            if (activeLightboxOffer) removeOfferGraphic(activeLightboxOffer);
        });

        // Drawer 5th Tab (Graphic Post) controls
        const drawerGraphicPreviewWrap = document.getElementById('moDrawerGraphicPreviewWrap');
        drawerGraphicPreviewWrap?.addEventListener('click', () => {
            if (activeDrawerOffer && activeDrawerOffer.has_graphic_post) {
                openGraphicLightbox(activeDrawerOffer);
            }
        });
        drawerGraphicPreviewWrap?.addEventListener('keydown', (e) => {
            if ((e.key === 'Enter' || e.key === ' ') && activeDrawerOffer && activeDrawerOffer.has_graphic_post) {
                e.preventDefault();
                openGraphicLightbox(activeDrawerOffer);
            }
        });
        document.getElementById('moDrawerCopyGraphicBtn')?.addEventListener('click', (e) => {
            if (activeDrawerOffer) copyOfferGraphicToClipboard(activeDrawerOffer, e.currentTarget);
        });
        document.getElementById('moDrawerReplaceGraphicBtn')?.addEventListener('click', () => {
            if (activeDrawerOffer) triggerQuickGraphicPicker(activeDrawerOffer.id);
        });
        document.getElementById('moDrawerRemoveGraphicBtn')?.addEventListener('click', () => {
            if (activeDrawerOffer) removeOfferGraphic(activeDrawerOffer);
        });

        const drawerGraphicDropzone = document.getElementById('moDrawerGraphicDropzone');
        if (drawerGraphicDropzone) {
            drawerGraphicDropzone.addEventListener('click', () => {
                if (activeDrawerOffer) triggerQuickGraphicPicker(activeDrawerOffer.id);
            });
            ['dragenter', 'dragover'].forEach(evtName => {
                drawerGraphicDropzone.addEventListener(evtName, (e) => {
                    e.preventDefault();
                    drawerGraphicDropzone.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(evtName => {
                drawerGraphicDropzone.addEventListener(evtName, (e) => {
                    e.preventDefault();
                    drawerGraphicDropzone.classList.remove('is-dragover');
                });
            });
            drawerGraphicDropzone.addEventListener('drop', (e) => {
                const file = e.dataTransfer?.files?.[0];
                if (file && activeDrawerOffer) {
                    uploadOfferGraphicFile(activeDrawerOffer.id, file);
                }
            });
        }

        // Layered Escape key handling: Lightbox first, then Playbook Drawer
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            if (lightboxEl?.classList.contains('is-open')) {
                closeGraphicLightbox();
                return;
            }
            if (drawerEl?.classList.contains('is-open')) {
                closePlaybookDrawer();
            }
        });

        // ---------------- 3-TAB ADD / EDIT MODAL WIRING ----------------
        const modalEl = document.getElementById('monthlyOfferModal');
        const formEl = document.getElementById('monthlyOfferForm');
        const categorySelect = document.getElementById('moCategory');
        const typeSelect = document.getElementById('moOfferType');
        const comboWrap = document.getElementById('moComboWrap');
        const freebieWrap = document.getElementById('moFreebieWrap');
        const servicePickerWrap = document.getElementById('moServicePickerWrap');
        const promoPriceInput = document.getElementById('moPromoPrice');
        const priceReqStar = document.getElementById('moPriceReqStar');
        const comboSelect = document.getElementById('moComboId');
        const freebieSelect = document.getElementById('moFreebieId');
        const svcPickerBox = document.getElementById('moSvcPickerBox');
        const svcTriggerBar = document.getElementById('moSvcTriggerBar');
        const svcToggleBtn = document.getElementById('moSvcToggleBtn');
        const svcDropdownPanel = document.getElementById('moSvcDropdownPanel');
        const svcDoneBtn = document.getElementById('moSvcDoneBtn');
        const svcFooterHint = document.getElementById('moSvcFooterHint');
        const svcSearchInput = document.getElementById('moSvcSearchInput');
        const svcSearchClear = document.getElementById('moSvcSearchClear');
        const svcRows = Array.from(document.querySelectorAll('[data-svc-row="1"]'));
        const svcCheckboxes = Array.from(document.querySelectorAll('.js-mo-svc-check'));
        const svcSelectedStrip = document.getElementById('moSvcSelectedStrip');
        const svcSelectedCount = document.getElementById('moSvcSelectedCount');
        const svcSelectedPills = document.getElementById('moSvcSelectedPills');
        const svcClearAllBtn = document.getElementById('moSvcClearAllBtn');
        const svcEmptySearch = document.getElementById('moSvcEmptySearch');
        const tcsInfoBar = document.getElementById('moTcsInfoBar');
        const tcsHeaderToggle = document.getElementById('moTcsHeaderToggle');
        const tcsBody = document.getElementById('moTcsBody');
        const tcsTitleExpanded = document.getElementById('moTcsTitleExpanded');
        const tcsTitleMinimized = document.getElementById('moTcsTitleMinimized');
        const tcsToggleLabel = document.getElementById('moTcsToggleLabel');
        const termsTextarea = document.getElementById('moTerms');
        const stepTabs = Array.from(document.querySelectorAll('[data-mo-step]'));
        const stepPanes = Array.from(document.querySelectorAll('[data-mo-step-pane]'));
        const prevBtn = document.getElementById('moPrevStepBtn');
        const nextBtn = document.getElementById('moNextStepBtn');
        const clientValBanner = document.getElementById('moClientValidationBanner');
        const TCS_MINIMIZED_STORAGE_KEY = 'laleen_mo_tcs_callout_minimized';
        let currentModalStep = 1;

        function isServiceDropdownOpen() {
            return Boolean(svcDropdownPanel && !svcDropdownPanel.classList.contains('d-none'));
        }

        function openServiceDropdown() {
            if (!svcDropdownPanel || !svcPickerBox) return;
            svcDropdownPanel.classList.remove('d-none');
            svcPickerBox.classList.add('is-open');
            svcToggleBtn?.setAttribute('aria-expanded', 'true');
        }

        function closeServiceDropdown(clearQuery = false) {
            if (!svcDropdownPanel || !svcPickerBox) return;
            svcDropdownPanel.classList.add('d-none');
            svcPickerBox.classList.remove('is-open');
            svcToggleBtn?.setAttribute('aria-expanded', 'false');
            if (clearQuery && svcSearchInput) {
                svcSearchInput.value = '';
                filterCatalogServices();
            }
        }

        function renderSelectedServicesStrip() {
            const selected = svcCheckboxes.filter(cb => cb.checked);
            svcRows.forEach(row => {
                const cb = row.querySelector('.js-mo-svc-check');
                row.classList.toggle('is-selected', Boolean(cb && cb.checked));
            });

            if (svcDoneBtn) {
                svcDoneBtn.textContent = selected.length > 0 ? `Done (${selected.length})` : 'Done';
            }
            if (svcFooterHint) {
                svcFooterHint.textContent = selected.length > 0
                    ? `${selected.length} ${selected.length === 1 ? 'service' : 'services'} selected`
                    : 'Select one or more services';
            }

            if (!svcSelectedStrip || !svcSelectedPills || !svcSelectedCount) return;

            if (selected.length === 0) {
                svcSelectedStrip.classList.add('d-none');
                svcSelectedPills.innerHTML = '';
                svcClearAllBtn?.classList.add('d-none');
                return;
            }

            svcSelectedStrip.classList.remove('d-none');
            svcSelectedCount.textContent = `${selected.length} ${selected.length === 1 ? 'service' : 'services'} selected`;
            svcClearAllBtn?.classList.toggle('d-none', selected.length <= 1);
            svcSelectedPills.innerHTML = selected.map(cb => `
                <span class="mo-svc-pill">
                    <span>${escapeHtml(cb.dataset.svcName || '')}</span>
                    <button type="button" class="mo-svc-pill-remove" data-remove-svc-id="${escapeHtml(cb.value)}" aria-label="Remove ${escapeHtml(cb.dataset.svcName || 'service')}">&times;</button>
                </span>
            `).join('');
        }

        function filterCatalogServices() {
            if (!svcSearchInput) return;
            const q = svcSearchInput.value.trim().toLowerCase();
            svcSearchClear?.classList.toggle('d-none', q === '');
            let visible = 0;
            svcRows.forEach(row => {
                const haystack = row.dataset.svcSearch || '';
                const show = !q || haystack.includes(q);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            if (svcEmptySearch) {
                svcEmptySearch.classList.toggle('d-none', svcRows.length === 0 || visible > 0);
            }
        }

        function setSelectedServiceIds(ids) {
            const idSet = new Set((ids || []).map(id => String(id)));
            svcCheckboxes.forEach(cb => {
                cb.checked = idSet.has(String(cb.value));
            });
            closeServiceDropdown(true);
            renderSelectedServicesStrip();
        }

        svcTriggerBar?.addEventListener('click', (e) => {
            if (e.target.closest('#moSvcToggleBtn') || e.target.closest('#moSvcSearchClear')) {
                return;
            }
            openServiceDropdown();
            svcSearchInput?.focus();
        });

        svcToggleBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (isServiceDropdownOpen()) {
                closeServiceDropdown(false);
            } else {
                openServiceDropdown();
                svcSearchInput?.focus();
            }
        });

        svcSearchInput?.addEventListener('focus', openServiceDropdown);
        svcSearchInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
        svcSearchInput?.addEventListener('input', () => {
            openServiceDropdown();
            filterCatalogServices();
        });

        svcSearchClear?.addEventListener('click', (e) => {
            e.stopPropagation();
            if (!svcSearchInput) return;
            svcSearchInput.value = '';
            filterCatalogServices();
            svcSearchInput.focus();
        });

        svcDoneBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeServiceDropdown(false);
        });

        // Close dropdown when clicking anywhere outside the picker inside the modal
        modalEl?.addEventListener('click', (e) => {
            if (isServiceDropdownOpen() && svcPickerBox && !svcPickerBox.contains(e.target)) {
                closeServiceDropdown(false);
            }
        });

        // Capture Escape at window level BEFORE Bootstrap modal dismisses so Escape closes ONLY the service dropdown
        let lastSvcEscClosedAt = 0;
        window.addEventListener('keydown', (e) => {
            const isEsc = e.key === 'Escape' || e.key === 'Esc' || e.keyCode === 27;
            if (isEsc && isServiceDropdownOpen()) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                lastSvcEscClosedAt = Date.now();
                closeServiceDropdown(false);
                svcToggleBtn?.focus();
            }
        }, true);

        // Belt-and-suspenders guard so Bootstrap never dismisses the modal on the same Escape press
        modalEl?.addEventListener('hide.bs.modal', (e) => {
            if (Date.now() - lastSvcEscClosedAt < 200) {
                e.preventDefault();
            }
        });

        modalEl?.addEventListener('hidden.bs.modal', () => {
            closeServiceDropdown(true);
        });

        svcCheckboxes.forEach(cb => cb.addEventListener('change', renderSelectedServicesStrip));
        svcSelectedPills?.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('[data-remove-svc-id]');
            if (!removeBtn) return;
            e.stopPropagation();
            const targetId = String(removeBtn.dataset.removeSvcId);
            const cb = svcCheckboxes.find(el => String(el.value) === targetId);
            if (cb) {
                cb.checked = false;
                renderSelectedServicesStrip();
            }
        });
        svcClearAllBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            svcCheckboxes.forEach(cb => { cb.checked = false; });
            renderSelectedServicesStrip();
        });

        // Minimizable T&Cs Information Callout Bar (remembers preference in localStorage)
        function applyTcsCalloutState(minimized, persist = false) {
            if (!tcsInfoBar || !tcsBody) return;
            tcsInfoBar.classList.toggle('is-minimized', minimized);
            tcsBody.classList.toggle('d-none', minimized);
            tcsTitleExpanded?.classList.toggle('d-none', minimized);
            tcsTitleMinimized?.classList.toggle('d-none', !minimized);
            tcsHeaderToggle?.setAttribute('aria-expanded', minimized ? 'false' : 'true');
            if (tcsToggleLabel) {
                tcsToggleLabel.innerHTML = minimized ? 'Show &#9662;' : 'Minimize &#9652;';
            }
            if (persist) {
                try {
                    localStorage.setItem(TCS_MINIMIZED_STORAGE_KEY, minimized ? '1' : '0');
                } catch (err) {}
            }
        }

        function initTcsCalloutPreference() {
            let savedMinimized = false;
            try {
                savedMinimized = localStorage.getItem(TCS_MINIMIZED_STORAGE_KEY) === '1';
            } catch (err) {}
            applyTcsCalloutState(savedMinimized, false);
        }

        tcsHeaderToggle?.addEventListener('click', () => {
            const currentlyMinimized = tcsInfoBar?.classList.contains('is-minimized');
            applyTcsCalloutState(!currentlyMinimized, true);
        });
        tcsHeaderToggle?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const currentlyMinimized = tcsInfoBar?.classList.contains('is-minimized');
                applyTcsCalloutState(!currentlyMinimized, true);
            }
        });
        initTcsCalloutPreference();

        // 1-Click T&Cs Quick-Insert Starter Chips
        document.querySelectorAll('.js-mo-tcs-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                if (!termsTextarea) return;
                const line = chip.dataset.tcsLine || '';
                if (!line) return;
                const existing = termsTextarea.value;
                const needsNewline = existing.length > 0 && !existing.endsWith('\n');
                const prefix = existing + (needsNewline ? '\n' : '');
                termsTextarea.value = prefix + line;
                termsTextarea.focus();

                const bracketStart = termsTextarea.value.indexOf('[', prefix.length);
                const bracketEnd = termsTextarea.value.indexOf(']', prefix.length);
                if (bracketStart !== -1 && bracketEnd !== -1 && bracketEnd > bracketStart) {
                    termsTextarea.setSelectionRange(bracketStart, bracketEnd + 1);
                } else {
                    termsTextarea.setSelectionRange(termsTextarea.value.length, termsTextarea.value.length);
                }
            });
        });

        function setModalStep(step) {
            currentModalStep = Math.max(1, Math.min(3, Number(step) || 1));
            stepTabs.forEach(t => t.classList.toggle('active', Number(t.dataset.moStep) === currentModalStep));
            stepPanes.forEach(p => p.classList.toggle('d-none', Number(p.dataset.moStepPane) !== currentModalStep));
            prevBtn?.classList.toggle('d-none', currentModalStep === 1);
            nextBtn?.classList.toggle('d-none', currentModalStep === 3);
        }

        stepTabs.forEach(t => t.addEventListener('click', () => setModalStep(t.dataset.moStep)));
        prevBtn?.addEventListener('click', () => setModalStep(currentModalStep - 1));
        nextBtn?.addEventListener('click', () => setModalStep(currentModalStep + 1));

        function clearModalErrors() {
            if (clientValBanner) {
                clientValBanner.textContent = '';
                clientValBanner.classList.add('d-none');
            }
            formEl?.querySelectorAll('.is-field-invalid').forEach(el => el.classList.remove('is-field-invalid'));
            document.querySelectorAll('[data-step-err]').forEach(dot => dot.classList.add('d-none'));
        }

        function syncOfferTypeFields() {
            if (!typeSelect) return;
            const t = typeSelect.value;
            comboWrap?.classList.toggle('d-none', t !== 'combo');
            freebieWrap?.classList.toggle('d-none', t !== 'service_freebie');
            servicePickerWrap?.classList.toggle('d-none', t !== 'standalone');
            priceReqStar?.classList.toggle('d-none', t !== 'standalone');
            svcCheckboxes.forEach(cb => { cb.disabled = t !== 'standalone'; });
            closeServiceDropdown(true);
            if (promoPriceInput) {
                promoPriceInput.placeholder = t === 'standalone' ? 'e.g. 299' : 'Auto-synced';
            }
        }

        typeSelect?.addEventListener('change', syncOfferTypeFields);

        // Client-side validation across all 3 tabs before submit so hidden tabs never silently fail
        formEl?.addEventListener('submit', (e) => {
            clearModalErrors();
            const missingByStep = { 1: [], 2: [], 3: [] };
            let firstInvalidEl = null;

            const markInvalid = (step, el, label) => {
                missingByStep[step].push(label);
                el?.classList.add('is-field-invalid');
                if (!firstInvalidEl && el) firstInvalidEl = el;
            };

            // Step 1 checks
            const step1Pane = document.querySelector('[data-mo-step-pane="1"]');
            step1Pane?.querySelectorAll('[data-mo-required="1"]').forEach(el => {
                if (String(el.value || '').trim() === '') {
                    markInvalid(1, el, el.dataset.moLabel || el.name);
                }
            });
            const t = typeSelect?.value;
            if (t === 'combo' && (!comboSelect || !comboSelect.value)) {
                markInvalid(1, comboSelect, 'Linked Combo');
            } else if (t === 'service_freebie' && (!freebieSelect || !freebieSelect.value)) {
                markInvalid(1, freebieSelect, 'Linked Service + Freebies');
            } else if (t === 'standalone' && (!promoPriceInput || String(promoPriceInput.value).trim() === '')) {
                markInvalid(1, promoPriceInput, 'Promo Price (QAR)');
            }

            // Step 2 checks (all 7 pillars required)
            const step2Pane = document.querySelector('[data-mo-step-pane="2"]');
            step2Pane?.querySelectorAll('[data-mo-required="1"]').forEach(el => {
                if (String(el.value || '').trim() === '') {
                    markInvalid(2, el, el.dataset.moLabel || el.name);
                }
            });

            let firstBadStep = null;
            [1, 2, 3].forEach(s => {
                const dot = document.querySelector(`[data-step-err="${s}"]`);
                const hasErr = missingByStep[s].length > 0;
                dot?.classList.toggle('d-none', !hasErr);
                if (hasErr && firstBadStep === null) firstBadStep = s;
            });

            if (firstBadStep !== null) {
                e.preventDefault();
                setModalStep(firstBadStep);
                const allMissing = [...missingByStep[1], ...missingByStep[2], ...missingByStep[3]];
                if (clientValBanner) {
                    clientValBanner.textContent = `Please fill required fields: ${allMissing.slice(0, 4).join(', ')}${allMissing.length > 4 ? ` (+${allMissing.length - 4} more)` : ''}.`;
                    clientValBanner.classList.remove('d-none');
                }
                setTimeout(() => firstInvalidEl?.focus(), 60);
            }
        });

        // Modal Tab 3 Graphic Post Uploader
        const modalGraphicFileInput = document.getElementById('moGraphicFileInput');
        const modalRemoveGraphicInput = document.getElementById('moRemoveGraphicInput');
        const modalGraphicEmpty = document.getElementById('moModalGraphicEmpty');
        const modalGraphicPreview = document.getElementById('moModalGraphicPreview');
        const modalGraphicThumb = document.getElementById('moModalGraphicThumb');
        const modalGraphicName = document.getElementById('moModalGraphicName');
        const modalGraphicMeta = document.getElementById('moModalGraphicMeta');
        let modalGraphicObjectUrl = null;

        function revokeModalGraphicUrl() {
            if (modalGraphicObjectUrl) {
                URL.revokeObjectURL(modalGraphicObjectUrl);
                modalGraphicObjectUrl = null;
            }
        }

        function renderModalGraphicState({ url = null, name = '', meta = '' } = {}) {
            if (!modalGraphicEmpty || !modalGraphicPreview) return;
            if (url) {
                modalGraphicEmpty.classList.add('d-none');
                modalGraphicPreview.classList.remove('d-none');
                if (modalGraphicThumb) modalGraphicThumb.src = url;
                if (modalGraphicName) modalGraphicName.textContent = name || 'offer-graphic.png';
                if (modalGraphicMeta) modalGraphicMeta.textContent = meta || 'Current graphic post';
            } else {
                modalGraphicEmpty.classList.remove('d-none');
                modalGraphicPreview.classList.add('d-none');
                if (modalGraphicThumb) modalGraphicThumb.removeAttribute('src');
            }
        }

        function handleModalGraphicFileSelection(file) {
            if (!file) return;
            const errMsg = validateGraphicFileClient(file);
            if (errMsg) {
                showMoToast(errMsg);
                if (modalGraphicFileInput) modalGraphicFileInput.value = '';
                return;
            }
            revokeModalGraphicUrl();
            modalGraphicObjectUrl = URL.createObjectURL(file);
            if (modalRemoveGraphicInput) modalRemoveGraphicInput.value = '0';
            const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            renderModalGraphicState({
                url: modalGraphicObjectUrl,
                name: file.name,
                meta: `Ready to upload · ${sizeMb} MB`,
            });
        }

        modalGraphicFileInput?.addEventListener('change', () => {
            const file = modalGraphicFileInput.files?.[0];
            if (file) handleModalGraphicFileSelection(file);
        });

        modalGraphicEmpty?.addEventListener('click', () => modalGraphicFileInput?.click());
        modalGraphicEmpty?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                modalGraphicFileInput?.click();
            }
        });
        if (modalGraphicEmpty) {
            ['dragenter', 'dragover'].forEach(evtName => {
                modalGraphicEmpty.addEventListener(evtName, (e) => {
                    e.preventDefault();
                    modalGraphicEmpty.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(evtName => {
                modalGraphicEmpty.addEventListener(evtName, (e) => {
                    e.preventDefault();
                    modalGraphicEmpty.classList.remove('is-dragover');
                });
            });
            modalGraphicEmpty.addEventListener('drop', (e) => {
                const file = e.dataTransfer?.files?.[0];
                if (!file || !modalGraphicFileInput) return;
                try {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    modalGraphicFileInput.files = dt.files;
                } catch (err) {}
                handleModalGraphicFileSelection(file);
            });
        }

        document.getElementById('moModalGraphicChangeBtn')?.addEventListener('click', () => {
            modalGraphicFileInput?.click();
        });

        document.getElementById('moModalGraphicRemoveBtn')?.addEventListener('click', () => {
            revokeModalGraphicUrl();
            if (modalGraphicFileInput) modalGraphicFileInput.value = '';
            if (modalRemoveGraphicInput) modalRemoveGraphicInput.value = '1';
            renderModalGraphicState({});
        });

        window.openMonthlyOfferCreateModal = function () {
            if (!modalEl || !formEl) return;
            clearModalErrors();
            revokeModalGraphicUrl();
            if (modalGraphicFileInput) modalGraphicFileInput.value = '';
            if (modalRemoveGraphicInput) modalRemoveGraphicInput.value = '0';
            renderModalGraphicState({});
            document.getElementById('moModalTitle').textContent = 'Add Monthly Offer';
            formEl.action = '{{ route('offers.store') }}';
            document.getElementById('moFormMethod').value = 'POST';
            document.getElementById('moTitle').value = '';
            if (categorySelect) {
                categorySelect.value = (activeCategory !== 'all' && activeCategory !== 'uncategorized') ? activeCategory : '';
            }
            typeSelect.value = 'combo';
            if (comboSelect) comboSelect.value = '';
            if (freebieSelect) freebieSelect.value = '';
            setSelectedServiceIds([]);
            promoPriceInput.value = '';
            document.getElementById('moBranchScope').value = 'all';
            document.getElementById('moValidFrom').value = '{{ $todayQatar->copy()->startOfMonth()->toDateString() }}';
            document.getElementById('moValidUntil').value = '{{ $todayQatar->copy()->endOfMonth()->toDateString() }}';
            document.getElementById('moTerms').value = '';
            document.getElementById('moStaffNotes').value = '';
            document.getElementById('moIsActive').checked = true;
            pillarKeys.forEach(k => {
                const el = document.getElementById(`moPillar_${k}`);
                if (el) el.value = '';
            });
            document.getElementById('moWhatsappScript').value = '';
            document.getElementById('moVideoScript').value = '';
            syncOfferTypeFields();
            renderSelectedServicesStrip();
            setModalStep(1);
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        };

        function openMonthlyOfferEditModal(offer, initialStep = 1, focusFieldId = null) {
            if (!modalEl || !formEl || !offer) return;
            clearModalErrors();
            revokeModalGraphicUrl();
            if (modalGraphicFileInput) modalGraphicFileInput.value = '';
            if (modalRemoveGraphicInput) modalRemoveGraphicInput.value = '0';
            if (offer.has_graphic_post && offer.graphic_post_url) {
                renderModalGraphicState({
                    url: offer.graphic_post_url,
                    name: offer.graphic_filename || 'offer-graphic.png',
                    meta: 'Current graphic post',
                });
            } else {
                renderModalGraphicState({});
            }
            document.getElementById('moModalTitle').textContent = 'Edit Monthly Offer';
            formEl.action = `/monthly-offers/${offer.id}`;
            document.getElementById('moFormMethod').value = 'PUT';
            document.getElementById('moTitle').value = offer.title || '';
            if (categorySelect) {
                categorySelect.value = offer.category || '';
            }
            typeSelect.value = offer.offer_type || 'standalone';
            if (comboSelect) comboSelect.value = offer.combo_id || '';
            if (freebieSelect) freebieSelect.value = offer.service_freebie_id || '';
            setSelectedServiceIds(offer.service_ids || []);
            promoPriceInput.value = offer.promo_price_qar ?? '';
            document.getElementById('moBranchScope').value = offer.branch_scope || 'all';
            document.getElementById('moValidFrom').value = offer.valid_from || '';
            document.getElementById('moValidUntil').value = offer.valid_until || '';
            document.getElementById('moTerms').value = offer.terms_and_conditions || '';
            document.getElementById('moStaffNotes').value = offer.staff_notes || '';
            document.getElementById('moIsActive').checked = Boolean(offer.is_active);
            pillarKeys.forEach(k => {
                const el = document.getElementById(`moPillar_${k}`);
                if (el) el.value = offer[k] || '';
            });
            document.getElementById('moWhatsappScript').value = offer.whatsapp_script || '';
            document.getElementById('moVideoScript').value = offer.video_shooting_script || '';
            syncOfferTypeFields();
            renderSelectedServicesStrip();
            setModalStep(initialStep);
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            if (focusFieldId) {
                setTimeout(() => document.getElementById(focusFieldId)?.focus(), 220);
            }
        }

        document.getElementById('addOfferBtn')?.addEventListener('click', window.openMonthlyOfferCreateModal);
        document.getElementById('addOfferStandaloneBtn')?.addEventListener('click', window.openMonthlyOfferCreateModal);

        document.querySelectorAll('.js-mo-edit-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                closeAllCardMenus();
                const offer = getOfferPayloadById(btn.dataset.offerId);
                if (offer) openMonthlyOfferEditModal(offer, 1);
            });
        });

        // Drawer quick-edit shortcuts (Admin only)
        document.getElementById('moDrawerEditBtn')?.addEventListener('click', () => {
            if (!activeDrawerOffer) return;
            const target = activeDrawerOffer;
            closePlaybookDrawer();
            openMonthlyOfferEditModal(target, 1);
        });
        document.getElementById('moDrawerAddWaBtn')?.addEventListener('click', () => {
            if (!activeDrawerOffer) return;
            const target = activeDrawerOffer;
            closePlaybookDrawer();
            openMonthlyOfferEditModal(target, 3, 'moWhatsappScript');
        });
        document.getElementById('moDrawerAddVideoBtn')?.addEventListener('click', () => {
            if (!activeDrawerOffer) return;
            const target = activeDrawerOffer;
            closePlaybookDrawer();
            openMonthlyOfferEditModal(target, 3, 'moVideoScript');
        });

        syncOfferTypeFields();
        renderSelectedServicesStrip();

        @if ($hasAnyOfferValidationErr && $canEditOffers)
            // Re-open modal automatically on server-side validation error at the first tab with errors
            if (modalEl) {
                const firstErrStep = {{ $hasOfferTab1Err ? 1 : ($hasOfferTab2Err ? 2 : 3) }};
                setModalStep(firstErrStep);
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        @endif
    })();
</script>
