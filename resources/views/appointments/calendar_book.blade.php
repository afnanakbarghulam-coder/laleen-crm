<!-- Fresha-style Booking Drawer -->
<style>
    #calendarBookModal {
        --bs-offcanvas-width: 620px;
    }

    #calendarBookModal .offcanvas-body {
        padding: 0;
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
    }

    .fb-body-row {
        flex: 1 1 auto;
        display: flex;
        overflow: hidden;
        min-height: 0;
    }

    .fb-rail {
        width: 132px;
        flex-shrink: 0;
        border-right: 1px solid rgba(217, 143, 131,0.16);
        padding: 22px 10px;
        text-align: center;
        cursor: pointer;
    }

    .fb-rail:hover {
        background: #241e1c;
    }

    .fb-rail .client-icon {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: rgba(185,142,163,0.14);
        color: #b98ea3;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin: 0 auto 10px;
        overflow: hidden;
    }

    .fb-rail .client-icon img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .fb-rail-label-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }

    .fb-rail .client-label {
        font-weight: 700;
        font-size: 12.5px;
        color: #e79a91;
        line-height: 1.2;
        word-break: break-word;
    }

    .fb-rail .client-sub {
        font-size: 10.5px;
        color: #c9a39a;
        line-height: 1.2;
        margin-top: 2px;
    }

    .fb-rail-edit-btn,
    .fb-client-edit-btn {
        border: 1px solid rgba(217, 143, 131, 0.24);
        background: rgba(217, 143, 131, 0.08);
        color: #e79a91;
        border-radius: 6px;
        padding: 2px 5px;
        font-size: 11px;
        line-height: 1;
        cursor: pointer;
        flex-shrink: 0;
        transition: all .15s ease;
    }

    .fb-rail-edit-btn:hover,
    .fb-client-edit-btn:hover {
        background: rgba(217, 143, 131, 0.2);
        border-color: rgba(217, 143, 131, 0.45);
    }

    .fb-inline-name-input {
        background: #1a1514 !important;
        border: 1px solid rgba(217, 143, 131, 0.45) !important;
        color: #f3e5e0 !important;
        font-size: 12.5px !important;
        padding: 4px 8px !important;
        border-radius: 6px !important;
    }

    .fb-inline-save-btn,
    .fb-inline-cancel-btn {
        border: 1px solid rgba(217, 143, 131, 0.3);
        border-radius: 6px;
        padding: 3px 8px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.1;
        cursor: pointer;
    }

    .fb-inline-save-btn {
        background: rgba(142, 168, 138, 0.22);
        border-color: rgba(142, 168, 138, 0.5);
        color: #b8d8b3;
    }

    .fb-inline-save-btn:hover {
        background: rgba(142, 168, 138, 0.35);
    }

    .fb-inline-cancel-btn {
        background: rgba(168, 82, 74, 0.18);
        border-color: rgba(168, 82, 74, 0.45);
        color: #e09b95;
    }

    .fb-inline-cancel-btn:hover {
        background: rgba(168, 82, 74, 0.3);
    }

    .fb-content-col {
        flex: 1 1 auto;
        overflow-y: auto;
        position: relative;
    }

    .fb-content {
        padding: 22px 24px;
    }

    .fb-panel-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
    }

    .fb-panel-header h5 {
        margin: 0;
        font-weight: 800;
    }

    .fb-back-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid rgba(217, 143, 131,0.16);
        background: #241e1c;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #cbb8b0;
    }

    .fb-back-btn:hover {
        background: rgba(217, 143, 131,0.06);
    }

    .fb-date-label {
        font-size: 21px;
        font-weight: 800;
        color: #e79a91;
        cursor: pointer;
    }

    .fb-date-sub {
        font-size: 12.5px;
        color: #c9a39a;
    }

    .fb-section-title {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #c9a39a;
        margin: 22px 0 10px;
    }

    .fb-svc-row {
        border-left: 3px solid #d98f83;
        background: rgba(217, 143, 131,0.1);
        border-radius: 6px;
        padding: 8px 12px;
        margin-bottom: 8px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .fb-svc-row-main {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }

    .fb-svc-row .name {
        font-weight: 700;
        font-size: 13.5px;
        color: #e79a91;
    }

    .fb-svc-row .meta {
        font-size: 11.5px;
        color: #c9a39a;
    }

    .fb-svc-row .price {
        font-weight: 700;
        font-size: 13.5px;
        white-space: nowrap;
    }

    .fb-price-edit {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .fb-price-edit .currency {
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        color: #c9a39a;
    }

    .fb-price-edit .price-input {
        width: 64px;
        background: transparent;
        border: none;
        border-bottom: 1px solid rgba(217, 143, 131, 0.4);
        color: #e79a91;
        font-weight: 700;
        font-size: 13.5px;
        text-align: right;
        padding: 1px 2px;
    }

    .fb-price-edit .price-input:focus {
        outline: none;
        border-bottom-color: #d98f83;
    }

    .fb-price-edit .price-input::-webkit-outer-spin-button,
    .fb-price-edit .price-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .fb-price-edit .price-input[type=number] {
        -moz-appearance: textfield;
    }

    .fb-orig-strike {
        font-size: 11.5px;
        font-weight: 500;
        color: #8a7d76;
        text-decoration: line-through;
        white-space: nowrap;
    }

    .fb-discount-toggle-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px dashed rgba(217, 143, 131, 0.38);
        background: transparent;
        color: #c9a39a;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 9px;
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.14s ease;
        white-space: nowrap;
    }

    .fb-discount-toggle-btn:hover {
        border-color: rgba(217, 143, 131, 0.72);
        color: #f3e6e2;
        background: rgba(217, 143, 131, 0.1);
    }

    .fb-discount-toggle-btn.has-discount {
        border-style: solid;
        border-color: rgba(142, 168, 138, 0.42);
        background: rgba(142, 168, 138, 0.14);
        color: #9ec099;
        font-weight: 700;
    }

    .fb-discount-row {
        display: flex;
        align-items: center;
        gap: 7px;
        padding-top: 7px;
        margin-top: 4px;
        border-top: 1px dashed rgba(217, 143, 131, 0.22);
        flex-wrap: nowrap;
    }

    .fb-disc-mode-toggle {
        display: inline-flex;
        align-items: center;
        border: 1px solid rgba(217, 143, 131, 0.32);
        border-radius: 999px;
        overflow: hidden;
        flex-shrink: 0;
        background: rgba(17, 13, 11, 0.55);
    }

    .fb-disc-mode-btn {
        border: none;
        background: transparent;
        color: #b7a29c;
        font-size: 10.5px;
        font-weight: 700;
        padding: 3px 8px;
        line-height: 1.2;
        cursor: pointer;
        transition: all 0.12s ease;
    }

    .fb-disc-mode-btn.is-active {
        background: rgba(217, 143, 131, 0.24);
        color: #f6ebe6;
    }

    .fb-disc-val-input {
        width: 64px;
        flex-shrink: 0;
        background: rgba(17, 13, 11, 0.5);
        border: 1px solid rgba(217, 143, 131, 0.32);
        border-radius: 6px;
        color: #f3e6e2;
        font-size: 12px;
        font-weight: 700;
        text-align: right;
        padding: 2px 6px;
    }

    .fb-disc-val-input:focus {
        outline: none;
        border-color: #d98f83;
    }

    .fb-disc-val-input::-webkit-outer-spin-button,
    .fb-disc-val-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .fb-disc-val-input[type=number] {
        -moz-appearance: textfield;
    }

    .fb-discount-badge {
        flex-shrink: 0;
        font-size: 10.5px;
        font-weight: 700;
        color: #8ea88a;
        background: rgba(142, 168, 138, 0.14);
        padding: 2px 8px;
        border-radius: 999px;
        white-space: nowrap;
    }

    .fb-discount-reason {
        flex: 1;
        min-width: 0;
        background: transparent;
        border: none;
        border-bottom: 1px solid rgba(217, 143, 131, 0.28);
        color: #e6d9d5;
        font-size: 11.5px;
        padding: 2px 4px;
    }

    .fb-discount-reason:focus {
        outline: none;
        border-bottom-color: #d98f83;
    }

    .fb-discount-reason::placeholder {
        color: #8a7d76;
    }

    .fb-disc-clear-btn {
        border: none;
        background: transparent;
        color: #a98f87;
        font-size: 14px;
        line-height: 1;
        padding: 0 3px;
        cursor: pointer;
        flex-shrink: 0;
    }

    .fb-disc-clear-btn:hover {
        color: #e79a91;
    }

    .fb-footer .total-discount {
        font-size: 11px;
        font-weight: 600;
        color: #9ec099;
        letter-spacing: 0.01em;
    }

    .fb-svc-row .remove-btn {
        border: none;
        background: transparent;
        color: #c9a39a;
        font-size: 16px;
        line-height: 1;
        padding: 0 2px;
    }

    .fb-svc-row .remove-btn:hover {
        color: #a8524a;
    }

    .fb-add-pill {
        border: 1px solid #8a7d76;
        border-radius: 999px;
        padding: 7px 18px;
        font-size: 13px;
        font-weight: 600;
        background: #241e1c;
        color: #e79a91;
    }

    .fb-add-pill:hover {
        background: rgba(217, 143, 131,0.06);
    }

    .fb-decide-pill {
        border: 1px solid #8a7d76;
        border-radius: 999px;
        padding: 7px 18px;
        font-size: 13px;
        font-weight: 600;
        background: transparent;
        color: #c9a39a;
    }

    .fb-decide-pill:hover {
        background: rgba(201, 163, 154, 0.08);
        color: #e79a91;
    }

    .fb-decide-tag {
        flex-shrink: 0;
        font-size: 10.5px;
        font-weight: 700;
        color: #c9a39a;
        background: rgba(201, 163, 154, 0.14);
        padding: 2px 8px;
        border-radius: 999px;
        white-space: nowrap;
    }

    .fb-search-wrap {
        position: relative;
        margin-bottom: 16px;
    }

    .fb-search-wrap i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #c9a39a;
    }

    .fb-search-wrap input {
        padding-left: 34px;
        border-radius: 10px;
    }

    .fb-client-row, .fb-service-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 8px;
        border-radius: 8px;
        cursor: pointer;
    }

    .fb-client-row:hover, .fb-service-row:hover {
        background: rgba(217, 143, 131,0.06);
    }

    .fb-client-row.add-new {
        background: rgba(185,142,163,0.08);
    }

    .fb-client-row.add-new:hover {
        background: rgba(185,142,163,0.1);
    }

    .fb-client-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: rgba(217, 143, 131,0.06);
        color: #cbb8b0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        flex-shrink: 0;
    }

    .fb-client-row.add-new .fb-client-avatar {
        background: rgba(185,142,163,0.14);
        color: #b98ea3;
    }

    .fb-client-name {
        font-weight: 700;
        font-size: 13.5px;
        color: #e79a91;
    }

    .fb-client-phone {
        font-size: 12px;
        color: #c9a39a;
    }

    .fb-country-select {
        flex: 0 0 108px;
        max-width: 108px;
        font-size: 12.5px;
    }

    .fb-country-dropdown {
        flex: 0 0 108px;
        max-width: 108px;
        position: relative;
    }

    .fb-country-toggle {
        width: 100%;
        height: 100%;
        text-align: left;
        border-top-right-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }

    .fb-country-menu {
        width: 240px;
        max-height: 280px;
        overflow-y: auto;
        padding: 0;
        background: #241e1c;
        border: 1px solid rgba(217, 143, 131, 0.25);
    }

    .fb-country-search-wrap {
        position: sticky;
        top: 0;
        padding: 8px;
        background: #241e1c;
        border-bottom: 1px solid rgba(217, 143, 131, 0.16);
        z-index: 1;
    }

    .fb-country-option {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 10px;
        font-size: 13px;
        color: #e9d9d4;
    }

    .fb-country-option:hover,
    .fb-country-option:focus {
        background: rgba(217, 143, 131, 0.1);
        color: #e79a91;
    }

    .fb-country-option .fb-country-dial {
        margin-left: auto;
        color: #c9a39a;
        font-size: 12px;
    }

    .fb-country-empty {
        padding: 10px;
        font-size: 12.5px;
        color: #c9a39a;
        text-align: center;
    }

    .fb-phone-group:focus-within .form-select,
    .fb-phone-group:focus-within .form-control {
        border-color: var(--luxe-accent, #e79a91) !important;
        box-shadow: 0 0 0 3px var(--luxe-accent-soft, rgba(217, 143, 131, 0.25)) !important;
    }

    .fb-service-row .svc-info .name {
        font-weight: 700;
        font-size: 13.5px;
        color: #e79a91;
    }

    .fb-service-row .svc-info .meta {
        font-size: 11.5px;
        color: #c9a39a;
    }

    .fb-service-row .svc-price {
        font-weight: 700;
        font-size: 13.5px;
        margin-left: auto;
    }

    .fb-cat-label {
        font-size: 12px;
        font-weight: 700;
        color: #c9a39a;
        margin-bottom: 8px;
    }

    .fb-footer {
        flex-shrink: 0;
        border-top: 1px solid rgba(217, 143, 131,0.16);
        padding: 14px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #241e1c;
    }

    .fb-footer .total-label {
        font-weight: 700;
        font-size: 13.5px;
        color: #e79a91;
    }

    .fb-footer .total-meta {
        font-size: 12px;
        color: #c9a39a;
    }

    .fb-footer .total-price {
        font-weight: 800;
        font-size: 16px;
        color: #e79a91;
    }

    #calendarBookModal .btn-close {
        position: absolute;
        top: 16px;
        right: 16px;
        z-index: 5;
    }

    .fb-combo-pill {
        border: 1px solid #8a7d76;
        border-radius: 999px;
        padding: 7px 18px;
        font-size: 13px;
        font-weight: 600;
        background: transparent;
        color: #b98ea3;
    }

    .fb-combo-pill:hover {
        background: rgba(185, 142, 163, 0.08);
    }

    .fb-combo-card {
        gap: 0;
        cursor: pointer;
        border-color: rgba(229, 184, 168, 0.24) !important;
        background: linear-gradient(180deg, rgba(34, 22, 27, 0.92) 0%, rgba(21, 14, 18, 0.96) 100%) !important;
        transition: border-color 0.16s ease, transform 0.16s ease;
    }

    .fb-combo-card:hover {
        border-color: rgba(229, 184, 168, 0.48) !important;
    }

    .fb-combo-header-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: #e5b8a8;
        background: rgba(229, 184, 168, 0.12);
        padding: 2px 8px;
        border-radius: 999px;
    }

    .fb-combo-price-lock {
        font-size: 13px;
        font-weight: 800;
        color: #f8efe6;
        white-space: nowrap;
    }

    .fb-combo-trigger-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        font-size: 12px;
        color: #b8a29a;
    }

    .fb-combo-picked-chips {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px 10px;
        min-width: 0;
    }

    .fb-combo-picked-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11.5px;
        font-weight: 600;
        color: #f8efe6;
    }

    .fb-combo-picked-chip .mode-today {
        color: #6ee7b7;
        font-weight: 700;
    }

    .fb-combo-picked-chip .mode-pending {
        color: #d4a373;
        font-weight: 700;
    }

    .fb-combo-edit-cta {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 11.5px;
        font-weight: 700;
        color: #e5b8a8;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .fb-combo-config-nav {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 14px;
        padding-right: 34px;
    }

    .fb-combo-config-nav h5 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: #f8efe6;
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .fb-combo-counter-pill {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        color: #e5b8a8;
        background: rgba(229, 184, 168, 0.14);
        white-space: nowrap;
        flex-shrink: 0;
    }

    .fb-combo-counter-pill.is-full {
        color: #6ee7b7;
        background: rgba(110, 231, 183, 0.14);
    }

    .fb-skip-salon-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        width: 100%;
        padding: 9px 12px;
        margin-bottom: 10px;
        border-radius: 10px;
        border: 1px solid rgba(212, 163, 115, 0.28);
        background: rgba(212, 163, 115, 0.08);
        color: #d4a373;
        font-size: 12px;
        font-weight: 600;
        text-align: left;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .fb-skip-salon-bar:hover {
        background: rgba(212, 163, 115, 0.15);
        border-color: rgba(212, 163, 115, 0.5);
        color: #f8efe6;
    }

    .fb-combo-cap-hint {
        font-size: 11.5px;
        color: #d4a373;
        margin-bottom: 8px;
    }

    .fb-combo-svc-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 2px;
        font-size: 13px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        flex-wrap: wrap;
        transition: opacity 0.15s ease;
    }

    .fb-combo-svc-row.is-dimmed {
        opacity: 0.38;
    }

    .fb-combo-svc-title {
        font-size: 13px;
        color: #f8efe6;
        font-weight: 600;
    }

    .fb-combo-svc-meta {
        font-size: 11.5px;
        color: #b8a29a;
        font-weight: 400;
    }

    .fb-combo-mode-group {
        display: inline-flex;
        align-items: center;
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 999px;
        padding: 2px;
        gap: 2px;
        background: rgba(255, 255, 255, 0.02);
    }

    .fb-combo-mode-btn {
        border: 0;
        border-radius: 999px;
        background: transparent;
        color: #b8a29a;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 11px;
        line-height: 1.35;
        transition: all 0.15s ease;
    }

    .fb-combo-mode-btn.is-today {
        background: rgba(110, 231, 183, 0.18);
        color: #6ee7b7;
    }

    .fb-combo-mode-btn.is-pending {
        background: rgba(212, 163, 115, 0.2);
        color: #d4a373;
    }

    .fb-combo-mode-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .fb-combo-apply-btn {
        background: linear-gradient(135deg, #e5b8a8 0%, #d98f83 100%);
        color: #1b1215;
        border: 0;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 800;
        padding: 8px 20px;
        transition: filter 0.15s ease;
    }

    .fb-combo-apply-btn:hover {
        filter: brightness(1.06);
        color: #1b1215;
    }

    .fb-combo-staff-select,
    .fb-redeem-staff-select {
        margin: 2px 0 6px;
        font-size: 12px;
        max-width: 220px;
        margin-left: auto;
    }

    .fb-redeem-pick-row.disabled {
        opacity: 0.45;
        cursor: not-allowed;
        pointer-events: none;
    }

    .fb-redeem-pill {
        border: 1px solid #8a7d76;
        border-radius: 999px;
        padding: 7px 18px;
        font-size: 13px;
        font-weight: 600;
        background: transparent;
        color: #8ea88a;
    }

    .fb-redeem-pill:hover {
        background: rgba(142, 168, 138, 0.08);
    }

    .fb-redeem-pill .badge {
        background: #8ea88a;
        color: #171310;
        font-weight: 700;
    }

    .fb-redeem-row-meta {
        font-size: 11px;
        color: #c9a39a;
    }

    .fb-redeem-expiry {
        font-weight: 700;
    }

    .fb-redeem-expiry.soon { color: #a8524a; }
    .fb-redeem-expiry.ok { color: #8ea88a; }

    .fb-redeem-pick-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 8px;
        border-radius: 8px;
    }

    .fb-redeem-pick-row:hover {
        background: rgba(217, 143, 131,0.06);
    }

    .fb-conflict-banner {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 11px 20px;
        background: rgba(168, 82, 74, 0.14);
        border-top: 1px solid rgba(168, 82, 74, 0.38);
        color: #f6e7dc;
        font-size: 0.8125rem;
        line-height: 1.45;
        flex-shrink: 0;
    }

    .fb-conflict-banner-body {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        min-width: 0;
    }

    .fb-conflict-banner-dot {
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: #d97b73;
        margin-top: 6px;
        flex-shrink: 0;
    }

    .fb-conflict-banner-close {
        background: transparent;
        border: 0;
        color: #c9a39a;
        font-size: 1rem;
        line-height: 1;
        padding: 2px 4px;
        cursor: pointer;
        flex-shrink: 0;
    }

    .fb-conflict-banner-close:hover {
        color: #f6e7dc;
    }

    .fb-freebie-pill {
        border: 1px solid rgba(229, 184, 168, 0.36);
        border-radius: 999px;
        padding: 7px 16px;
        font-size: 13px;
        font-weight: 600;
        background: transparent;
        color: #e5b8a8;
    }

    .fb-freebie-pill:hover {
        background: rgba(229, 184, 168, 0.08);
    }

    .fb-freebie-alert-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        margin: 12px 0 6px;
        border-left: 2px solid #6ee7b7;
        background: rgba(110, 231, 183, 0.06);
        border-radius: 0 8px 8px 0;
        font-size: 12.5px;
        color: #f8efe6;
    }

    .fb-freebie-alert-cta {
        border: 1px solid rgba(110, 231, 183, 0.45);
        background: rgba(110, 231, 183, 0.14);
        color: #6ee7b7;
        border-radius: 999px;
        padding: 3px 11px;
        font-size: 11.5px;
        font-weight: 700;
        white-space: nowrap;
        cursor: pointer;
    }

    .fb-freebie-alert-cta:hover {
        background: rgba(110, 231, 183, 0.24);
        color: #f8efe6;
    }

    .fb-freebie-slot-row {
        padding: 8px 0;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .fb-freebie-slot-select {
        background: #1b1514;
        border: 1px solid rgba(229, 184, 168, 0.22);
        color: #f8efe6;
        font-size: 12px;
        border-radius: 7px;
        padding: 4px 8px;
    }

    #bookAgent.fb-agent-invalid {
        border-color: #ef4444 !important;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important;
    }

    .fb-agent-inline-error {
        margin-top: 6px;
        font-size: 0.78rem;
        font-weight: 500;
        color: #fca5a5;
        line-height: 1.35;
    }

    .fb-edit-mode-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        flex-wrap: wrap;
        padding: 7px 12px;
        margin-bottom: 10px;
        border-radius: 10px;
        background: rgba(229, 184, 168, 0.1);
        border: 1px solid rgba(229, 184, 168, 0.28);
    }

    .fb-edit-mode-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11.5px;
        font-weight: 700;
        color: #f8efe6;
        letter-spacing: 0.02em;
    }

    .fb-edit-mode-pill i {
        color: #e5b8a8;
        font-size: 14px;
    }

    .fb-edit-mode-hint {
        font-size: 11px;
        color: #b8a29a;
    }

    .fb-combo-swap-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-top: 7px;
        padding-top: 7px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .fb-combo-swap-label {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 600;
        color: #c9a39a;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .fb-combo-swap-select {
        background-color: #181113 !important;
        border: 1px solid rgba(229, 184, 168, 0.24) !important;
        color: #f8efe6 !important;
        font-size: 11.5px !important;
        border-radius: 8px !important;
        padding: 3px 26px 3px 9px !important;
        max-width: 240px;
    }

    .fb-combo-swap-select:focus {
        border-color: #e5b8a8 !important;
        box-shadow: 0 0 0 2px rgba(229, 184, 168, 0.14) !important;
    }
</style>

<div class="offcanvas offcanvas-end" tabindex="-1" id="calendarBookModal" aria-labelledby="calendarBookModalLabel">
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>

    <form action="{{ route('appointments.store') }}" method="POST" id="calendarBookForm" class="offcanvas-body">
        @csrf
        <input type="hidden" name="_method" id="bookMethodHidden" value="" disabled>
        <input type="hidden" name="then" id="bookThen" value="">
        <input type="hidden" name="appointment_datetime" id="bookDatetimeHidden">
        <input type="hidden" name="booking_date" id="bookDateHidden">
        <input type="hidden" name="unscheduled" id="bookUnscheduledHidden" value="0">
        <input type="hidden" name="customer_name" id="bookCustomerNameHidden" value="{{ $bookingPrefill['customer_name'] ?? '' }}">
        <input type="hidden" name="phone" id="bookPhoneHidden" value="{{ $bookingPrefill['phone'] ?? '' }}">
        <input type="hidden" name="price" id="bookPriceHidden" value="0">
        <input type="hidden" name="branch" id="bookBranch" value="{{ $bookingPrefill['branch'] ?? 'old_airport' }}">
        <div id="svcHiddenInputs"></div>

        <div class="fb-body-row">
            <!-- RAIL -->
            <div class="fb-rail" id="fbRail">
                <div class="client-icon" id="fbRailIcon">
                    @if (!empty($bookingPrefill['customer_name']))
                        {{ strtoupper(substr($bookingPrefill['customer_name'], 0, 1)) }}
                    @else
                        <i class="bx bx-user-plus"></i>
                    @endif
                </div>
                <div class="fb-rail-label-row" id="fbRailLabelRow">
                    <div class="client-label" id="fbRailLabel">{{ !empty($bookingPrefill['customer_name']) ? $bookingPrefill['customer_name'] : 'Add client' }}</div>
                    <button type="button"
                        class="fb-rail-edit-btn {{ !empty($bookingPrefill['customer_name']) ? '' : 'd-none' }}"
                        id="fbRailEditNameBtn"
                        title="Edit client name">✏️</button>
                </div>
                <div id="fbRailInlineEditWrap" class="d-none mt-1" onclick="event.stopPropagation()">
                    <input type="text"
                        id="fbRailInlineNameInput"
                        class="form-control form-control-sm fb-inline-name-input text-center"
                        maxlength="120"
                        placeholder="Client name">
                    <div class="d-flex justify-content-center gap-1 mt-1">
                        <button type="button" class="fb-inline-save-btn" id="fbRailSaveNameBtn" title="Save name">✓</button>
                        <button type="button" class="fb-inline-cancel-btn" id="fbRailCancelNameBtn" title="Cancel">✕</button>
                    </div>
                </div>
                <div class="client-sub" id="fbRailSub">{{ !empty($bookingPrefill['phone']) ? $bookingPrefill['phone'] : 'Search or add a new client' }}</div>
            </div>

            <!-- CONTENT -->
            <div class="fb-content-col">
                <!-- PANEL: MAIN -->
                <div id="fbPanelMain" class="fb-content">
                    <div id="fbEditModeBanner" class="fb-edit-mode-banner d-none">
                        <span class="fb-edit-mode-pill"><i class="bx bx-edit-alt"></i> Editing Booking</span>
                        <span class="fb-edit-mode-hint">Swap combos, change services, or update specialists</span>
                    </div>
                    <div class="fb-date-label" id="fbDateLabel">&nbsp;</div>
                    <div class="fb-date-sub" id="fbDateSub"></div>

                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <input type="date" id="bookDateInput" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <input type="time" id="bookTimeInput" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="bookUnscheduledToggle">
                        <label class="form-check-label small" for="bookUnscheduledToggle" style="color: #c9a39a; cursor: pointer;">
                            Pending time &mdash; add to <strong>Unscheduled Queue</strong>
                        </label>
                    </div>

                    <!-- LIVE CLIENT FREEBIE ALERT BANNER -->
                    <div id="fbFreebieAlertBanner" class="fb-freebie-alert-banner d-none"></div>

                    <div class="fb-section-title">Services</div>
                    <div id="fbSvcList"></div>
                    <div id="fbComboList"></div>
                    <div id="fbFreebieList"></div>
                    <div id="fbRedeemList"></div>
                    <div id="fbFreebieRedeemList"></div>
                    <input type="hidden" name="decide_in_salon" id="bookDecideInSalonHidden" value="0">
                    <div id="fbComboHiddenInputs"></div>
                    <div id="fbFreebieHiddenInputs"></div>
                    <div id="fbRedeemHiddenInputs"></div>
                    <div id="fbFreebieRedeemHiddenInputs"></div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="fb-add-pill" id="fbOpenServicePanel">
                            <i class="bx bx-plus"></i> Add service
                        </button>
                        <button type="button" class="fb-combo-pill" id="fbOpenComboPanel">
                            <i class="bx bx-plus"></i> Add Combo
                        </button>
                        <button type="button" class="fb-freebie-pill" id="fbOpenFreebiePanel">
                            <i class="bx bx-gift"></i> Service + Freebies
                        </button>
                        <button type="button" class="fb-decide-pill" id="fbDecideSalonPill">
                            Decide in Salon
                        </button>
                        <button type="button" class="fb-redeem-pill d-none" id="fbOpenRedeemPanel">
                            Redeem Package <span class="badge rounded-pill" id="fbRedeemBadge">0</span>
                        </button>
                    </div>

                    <div id="fbTeamMemberSection">
                        <div class="fb-section-title">Team Member</div>
                        <select id="bookStaffSelect" name="staff_id" class="form-select form-select-sm">
                            <option value="">-- Select Staff (Unassigned) --</option>
                        </select>
                        <div id="bookStaffOvertimeAlert"></div>
                        <small id="bookStaffHelp" class="d-none" style="color: #d99b93; font-size: 0.75rem;">
                            No eligible staff free for this slot &mdash; pick another time/stylist or leave unassigned.
                        </small>
                    </div>

                    @php
                        $resolvedBookingAgentOptions = $bookingAgentOptions ?? \App\Models\Appointment::bookingAgentOptions();
                    @endphp
                    <div class="fb-section-title">Booking Agent <span class="text-danger">*</span></div>
                    <select id="bookAgent" name="booking_agent_id" class="form-select form-select-sm" aria-required="true" aria-describedby="bookAgentError">
                        <option value="">-- Select Agent --</option>
                        @foreach ($resolvedBookingAgentOptions as $agentOpt)
                            <option value="{{ $agentOpt['value'] }}">{{ $agentOpt['label'] }}</option>
                        @endforeach
                    </select>
                    <div id="bookAgentError" class="fb-agent-inline-error d-none" role="alert">
                        Please select a Booking Agent (Salon Staff, Areeba, or Zoya) before saving the appointment.
                    </div>

                    <div class="fb-section-title">Notes</div>
                    <textarea id="bookNotes" name="notes" class="form-control form-control-sm" rows="2" placeholder="Anything the team should know..."></textarea>
                </div>

                <!-- PANEL: SELECT CLIENT -->
                <div id="fbPanelClient" class="fb-content d-none">
                    <div class="fb-panel-header">
                        <button type="button" class="fb-back-btn" onclick="fbShowPanel('main')"><i class="bx bx-arrow-back"></i></button>
                        <h5>Select a client</h5>
                    </div>

                    <div class="fb-search-wrap">
                        <i class="bx bx-search"></i>
                        <input type="text" id="fbClientSearch" class="form-control" placeholder="Search by name or phone">
                    </div>

                    <div class="fb-client-row add-new" onclick="fbShowAddClientForm()">
                        <div class="fb-client-avatar"><i class="bx bx-plus"></i></div>
                        <div class="fb-client-name">Add new client</div>
                    </div>

                    <div id="fbAddClientForm" class="d-none border rounded p-3 my-2">
                        <input type="text" id="fbNewClientName" class="form-control form-control-sm mb-2" placeholder="Client name">
                        <div class="input-group input-group-sm mb-1 fb-phone-group">
                            <div class="dropdown fb-country-dropdown">
                                <button type="button" id="fbNewClientCountryToggle" class="form-select fb-country-select fb-country-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span id="fbNewClientCountryDisplay">🇶🇦 +974</span>
                                </button>
                                <ul class="dropdown-menu fb-country-menu" aria-labelledby="fbNewClientCountryToggle">
                                    <li class="fb-country-search-wrap">
                                        <input type="text" id="countrySearch" placeholder="Search code or country..." class="form-control form-control-sm" autocomplete="off">
                                    </li>
                                    <li><a href="#" class="dropdown-item fb-country-option" data-iso="QA" data-code="+974" data-country="Qatar" onclick="fbSelectCountry(this); return false;">🇶🇦 Qatar <span class="fb-country-dial">+974</span></a></li>
                                    <li><a href="#" class="dropdown-item fb-country-option" data-iso="AE" data-code="+971" data-country="United Arab Emirates" onclick="fbSelectCountry(this); return false;">🇦🇪 United Arab Emirates <span class="fb-country-dial">+971</span></a></li>
                                    <li><a href="#" class="dropdown-item fb-country-option" data-iso="SA" data-code="+966" data-country="Saudi Arabia" onclick="fbSelectCountry(this); return false;">🇸🇦 Saudi Arabia <span class="fb-country-dial">+966</span></a></li>
                                    <li><a href="#" class="dropdown-item fb-country-option" data-iso="BH" data-code="+973" data-country="Bahrain" onclick="fbSelectCountry(this); return false;">🇧🇭 Bahrain <span class="fb-country-dial">+973</span></a></li>
                                    <li><a href="#" class="dropdown-item fb-country-option" data-iso="KW" data-code="+965" data-country="Kuwait" onclick="fbSelectCountry(this); return false;">🇰🇼 Kuwait <span class="fb-country-dial">+965</span></a></li>
                                    <li><a href="#" class="dropdown-item fb-country-option" data-iso="OM" data-code="+968" data-country="Oman" onclick="fbSelectCountry(this); return false;">🇴🇲 Oman <span class="fb-country-dial">+968</span></a></li>
                                    <li><a href="#" class="dropdown-item fb-country-option" data-iso="EG" data-code="+20" data-country="Egypt" onclick="fbSelectCountry(this); return false;">🇪🇬 Egypt <span class="fb-country-dial">+20</span></a></li>
                                    <li><a href="#" class="dropdown-item fb-country-option" data-iso="IN" data-code="+91" data-country="India" onclick="fbSelectCountry(this); return false;">🇮🇳 India <span class="fb-country-dial">+91</span></a></li>
                                    <li><a href="#" class="dropdown-item fb-country-option" data-iso="PK" data-code="+92" data-country="Pakistan" onclick="fbSelectCountry(this); return false;">🇵🇰 Pakistan <span class="fb-country-dial">+92</span></a></li>
                                    <li><a href="#" class="dropdown-item fb-country-option" data-iso="PH" data-code="+63" data-country="Philippines" onclick="fbSelectCountry(this); return false;">🇵🇭 Philippines <span class="fb-country-dial">+63</span></a></li>
                                    <li><a href="#" class="dropdown-item fb-country-option" data-iso="GB" data-code="+44" data-country="United Kingdom" onclick="fbSelectCountry(this); return false;">🇬🇧 United Kingdom <span class="fb-country-dial">+44</span></a></li>
                                    <li><a href="#" class="dropdown-item fb-country-option" data-iso="US" data-code="+1" data-country="United States" onclick="fbSelectCountry(this); return false;">🇺🇸 United States <span class="fb-country-dial">+1</span></a></li>
                                    <li class="fb-country-empty d-none" id="fbCountryNoResults">No matches found</li>
                                </ul>
                            </div>
                            <input type="hidden" id="fbNewClientCountryCode" value="+974">
                            <input type="tel" id="fbNewClientPhone" class="form-control" placeholder="Phone number" inputmode="tel" autocomplete="tel-national">
                        </div>
                        <small id="fbNewClientPhoneError" class="text-danger d-none mb-2 d-block">Enter a valid phone number.</small>
                        <button type="button" class="btn btn-sm btn-primary w-100" onclick="fbConfirmNewClient()">Add Client</button>
                    </div>

                    <hr>
                    <div id="fbClientResults"></div>
                </div>

                <!-- PANEL: SELECT SERVICE -->
                <div id="fbPanelService" class="fb-content d-none">
                    <div class="fb-panel-header">
                        <button type="button" class="fb-back-btn" onclick="fbShowPanel('main')"><i class="bx bx-arrow-back"></i></button>
                        <h5>Add a service</h5>
                    </div>

                    <div class="fb-search-wrap">
                        <i class="bx bx-search"></i>
                        <input type="text" id="fbServiceSearch" class="form-control" placeholder="Search by service name">
                    </div>

                    <button type="button" class="fb-skip-salon-bar" id="fbServiceSkipSalonBtn">
                        <span>Skip selection &mdash; Decide service in salon</span>
                        <span>&rsaquo;</span>
                    </button>

                    <div class="fb-cat-label">All Services <span id="fbSvcCount"></span></div>
                    <div id="fbServiceResults"></div>
                </div>

                <!-- PANEL: SELECT COMBO -->
                <div id="fbPanelCombo" class="fb-content d-none">
                    <div class="fb-panel-header">
                        <button type="button" class="fb-back-btn" onclick="fbShowPanel('main')"><i class="bx bx-arrow-back"></i></button>
                        <h5>Add a combo package</h5>
                    </div>

                    <div class="fb-search-wrap" id="fbComboCatalogSearchWrap">
                        <i class="bx bx-search"></i>
                        <input type="text" id="fbComboCatalogSearch" class="form-control" placeholder="Search combo packages...">
                    </div>

                    <div id="fbComboPickerList"></div>
                </div>

                <!-- PANEL: CONFIGURE COMBO SERVICES (NESTED SUB-VIEW) -->
                <div id="fbPanelComboConfig" class="fb-content d-none">
                    <div class="fb-combo-config-nav">
                        <button type="button" class="fb-back-btn" id="fbComboConfigBackBtn" aria-label="Back"><i class="bx bx-arrow-back"></i></button>
                        <h5 id="fbComboConfigTitle">Combo Package</h5>
                        <span class="fb-combo-counter-pill" id="fbComboConfigCounter">0 / 0 Selected</span>
                    </div>

                    <div class="fb-search-wrap mb-2">
                        <i class="bx bx-search"></i>
                        <input type="text" id="fbComboServiceSearch" class="form-control" placeholder="Search services in this combo..." autocomplete="off">
                    </div>

                    <button type="button" class="fb-skip-salon-bar" id="fbComboSkipSalonBtn">
                        <span>Skip selection &mdash; Decide services in salon</span>
                        <span>&rsaquo;</span>
                    </button>

                    <div class="fb-combo-cap-hint d-none" id="fbComboCapHint"></div>
                    <div id="fbComboConfigServiceList"></div>
                </div>

                <!-- PANEL: REDEEM PENDING PACKAGE SERVICE -->
                <div id="fbPanelRedeem" class="fb-content d-none">
                    <div class="fb-panel-header">
                        <button type="button" class="fb-back-btn" onclick="fbShowPanel('main')"><i class="bx bx-arrow-back"></i></button>
                        <h5>Redeem package service</h5>
                    </div>

                    <div id="fbRedeemPickerList"></div>
                </div>

                <!-- PANEL: SELECT SERVICE + FREEBIES PACKAGE -->
                <div id="fbPanelFreebie" class="fb-content d-none">
                    <div class="fb-panel-header">
                        <button type="button" class="fb-back-btn" onclick="fbShowPanel('main')"><i class="bx bx-arrow-back"></i></button>
                        <h5>Service + Freebies</h5>
                    </div>

                    <div class="fb-search-wrap" id="fbFreebieCatalogSearchWrap">
                        <i class="bx bx-search"></i>
                        <input type="text" id="fbFreebieCatalogSearch" class="form-control" placeholder="Search Service + Freebies packages…">
                    </div>

                    <div id="fbFreebiePickerList"></div>
                </div>
            </div>
        </div>

        <!-- INLINE CONFLICT / ELIGIBILITY BANNER -->
        <div id="fbBookingErrorBanner" class="fb-conflict-banner d-none" role="alert" aria-live="polite">
            <div class="fb-conflict-banner-body">
                <span class="fb-conflict-banner-dot" aria-hidden="true"></span>
                <span id="fbBookingErrorText"></span>
            </div>
            <button type="button" class="fb-conflict-banner-close" id="fbBookingErrorDismiss" aria-label="Dismiss">&times;</button>
        </div>

        <!-- SUB-VIEW STICKY FOOTER (COMBO PICKER) -->
        <div class="fb-footer d-none" id="fbComboConfigFooter">
            <div>
                <div class="total-label" id="fbComboFooterHoldLabel">Hold: 60 min</div>
                <div class="total-meta" id="fbComboFooterPriceLabel">0.00 QAR locked</div>
            </div>
            <button type="button" class="fb-combo-apply-btn" id="fbComboConfigApplyBtn">Done &middot; Apply to Booking</button>
        </div>

        <!-- FOOTER -->
        <div class="fb-footer" id="fbMainFooter">
            <div class="total-label">Total</div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="total-meta" id="fbFooterDuration">0min</div>
                    <div class="total-discount d-none" id="fbFooterDiscount">Discount: −0.00 QAR</div>
                    <div class="total-price" id="fbFooterPrice">0 QAR</div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="offcanvas">Cancel</button>
                <button type="submit" class="btn btn-outline-success btn-sm" id="fbCheckoutBtn">Checkout</button>
                <button type="submit" class="btn btn-dark btn-sm" id="fbSaveBtn">Save</button>
            </div>
        </div>
    </form>
</div>

@php
    $bookableStaffCatalog = collect($activeStaffs ?? $staffs ?? [])->map(fn($s) => [
        'id' => $s->id,
        'name' => $s->name,
        'branch' => $s->branch ?: 'both',
        'skill_ids' => $s->relationLoaded('services')
            ? $s->services->pluck('id')->map(fn($id) => (int) $id)->values()
            : [],
        'skills' => $s->relationLoaded('services')
            ? $s->services->pluck('name')->values()
            : [],
    ])->values();
@endphp

<script>
    (function() {
        const ALL_SERVICES = @json($servicesCatalog ?? []);
        const ALL_COMBOS = @json($combosCatalog ?? []);
        const ALL_FREEBIE_PACKAGES = @json($freebiePackagesCatalog ?? []);
        const ALL_STAFFS = @json($bookableStaffCatalog);
        const SERVER_BOOKING_PREFILL = @json($bookingPrefill ?? null);
        const FB_BRANCH_LABELS = { old_airport: 'Old Airport', wakrah: 'Al Wakrah' };

        function fbNormalizeBranch(raw) {
            if (!raw) return '';
            const slug = String(raw).trim().toLowerCase().replace(/[\s\-]+/g, '_');
            if (slug === 'old_airport' || slug === 'airport') return 'old_airport';
            if (slug === 'wakrah' || slug === 'al_wakrah' || slug === 'alwakrah') return 'wakrah';
            return '';
        }

        function fbResolveActiveCalendarBranch() {
            if (typeof window.getActiveCalendarBranch === 'function') {
                const active = fbNormalizeBranch(window.getActiveCalendarBranch());
                if (active) return active;
            }
            const filterEl = document.getElementById('filterBranch');
            const fromDom = filterEl ? fbNormalizeBranch(filterEl.value) : '';
            return fromDom || 'old_airport';
        }

        function fbBranchLabel(branchKey) {
            const norm = fbNormalizeBranch(branchKey) || fbResolveActiveCalendarBranch();
            return FB_BRANCH_LABELS[norm] || 'Old Airport';
        }

        function fbShowBookingError(message, reason) {
            const banner = document.getElementById('fbBookingErrorBanner');
            const textEl = document.getElementById('fbBookingErrorText');
            if (!banner || !textEl) return;
            textEl.textContent = message || 'Unable to save booking. Please review the selected slot and staff member.';
            banner.dataset.reason = reason || '';
            banner.classList.remove('d-none');
        }

        function fbClearBookingError() {
            const banner = document.getElementById('fbBookingErrorBanner');
            const textEl = document.getElementById('fbBookingErrorText');
            if (!banner) return;
            banner.classList.add('d-none');
            banner.dataset.reason = '';
            if (textEl) textEl.textContent = '';
        }

        const FB_BOOKING_AGENT_ERROR_MSG = 'Please select a Booking Agent (Salon Staff, Areeba, or Zoya) before saving the appointment.';

        function fbIsValidBookingAgentSelection(val) {
            const trimmed = String(val || '').trim();
            if (!trimmed) return false;
            const bookAgentEl = document.getElementById('bookAgent');
            if (!bookAgentEl) return false;
            return Array.from(bookAgentEl.options).some(opt => opt.value !== '' && opt.value === trimmed);
        }

        function fbShowAgentError(message, showToast = true) {
            const bookAgentEl = document.getElementById('bookAgent');
            const errEl = document.getElementById('bookAgentError');
            const msg = message || FB_BOOKING_AGENT_ERROR_MSG;
            fbShowPanel('main');
            if (bookAgentEl) {
                bookAgentEl.classList.add('fb-agent-invalid', 'is-invalid');
                bookAgentEl.setAttribute('aria-invalid', 'true');
            }
            if (errEl) {
                errEl.textContent = msg;
                errEl.classList.remove('d-none');
            }
            if (bookAgentEl) {
                bookAgentEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => bookAgentEl.focus(), 50);
            }
            if (showToast && typeof window.showCalToast === 'function') {
                window.showCalToast(msg, 'error');
            }
        }

        function fbClearAgentError() {
            const bookAgentEl = document.getElementById('bookAgent');
            const errEl = document.getElementById('bookAgentError');
            if (bookAgentEl) {
                bookAgentEl.classList.remove('fb-agent-invalid', 'is-invalid');
                bookAgentEl.removeAttribute('aria-invalid');
            }
            if (errEl) {
                errEl.classList.add('d-none');
            }
        }

        const fbDismissBtn = document.getElementById('fbBookingErrorDismiss');
        if (fbDismissBtn) {
            fbDismissBtn.addEventListener('click', fbClearBookingError);
        }

        let fbLatestAvailableStaffMeta = new Map();

        function fbUpdateBookStaffOvertimeAlert() {
            const alertBox = document.getElementById('bookStaffOvertimeAlert');
            const staffSelect = document.getElementById('bookStaffSelect');
            if (!alertBox || !staffSelect) return;

            const sid = String(staffSelect.value || '');
            const meta = sid ? fbLatestAvailableStaffMeta.get(sid) : null;
            if (!meta || !meta.is_overtime || !meta.shift_end) {
                alertBox.innerHTML = '';
                staffSelect.classList.remove('is-overtime');
                return;
            }

            const startVal = document.getElementById('bookTimeInput')?.value || '';
            const startMin = window.LaleenSliceEngine ? window.LaleenSliceEngine.parseHHMM(startVal) : null;
            const totalDur = (typeof fbComputeUnifiedTimeline === 'function' ? fbComputeUnifiedTimeline().timeline.spanMinutes : 0) || 60;
            const endLabel = (startMin !== null && window.LaleenSliceEngine)
                ? window.LaleenSliceEngine.formatHHMM(startMin + totalDur)
                : '';
            const deltaLabel = window.LaleenSliceEngine
                ? window.LaleenSliceEngine.formatOvertimeDelta(meta.overtime_minutes)
                : `+${meta.overtime_minutes}m`;
            const staffName = meta.name || 'Specialist';
            const message = `Overtime booking: runs until ${endLabel || meta.shift_end} (${deltaLabel} past ${staffName}'s shift end at ${meta.shift_end}). Confirm overtime with specialist.`;

            staffSelect.classList.add('is-overtime');
            alertBox.innerHTML = window.LaleenSliceEngine
                ? window.LaleenSliceEngine.renderOvertimeAlert({ overtimeAlert: { message } })
                : `<div class="laleen-ot-alert" role="alert"><i class="bx bx-time-five"></i><span>${fbEscHtml(message)}</span></div>`;
        }

        function fbPopulateBranchStaffOptions(branchKey, preferredId, availableMetaMap) {
            const staffSelect = document.getElementById('bookStaffSelect');
            if (!staffSelect) return;
            const normBranch = fbNormalizeBranch(branchKey) || 'old_airport';
            const branchStaff = ALL_STAFFS.filter(s => !s.branch || s.branch === 'both' || s.branch === normBranch);

            staffSelect.innerHTML = '<option value="">-- Select Staff (Unassigned) --</option>';

            const hasAvailFilter = availableMetaMap instanceof Map;
            fbLatestAvailableStaffMeta = hasAvailFilter ? availableMetaMap : new Map();

            if (hasAvailFilter) {
                const inShiftList = [];
                const overtimeList = [];
                const unavailableList = [];

                branchStaff.forEach(s => {
                    const entry = availableMetaMap.get(String(s.id));
                    if (!entry) {
                        unavailableList.push(s);
                    } else if (entry.is_overtime) {
                        overtimeList.push({ staff: s, entry });
                    } else {
                        inShiftList.push({ staff: s, entry });
                    }
                });

                overtimeList.sort((a, b) => (a.entry.overtime_minutes - b.entry.overtime_minutes) || String(a.staff.name).localeCompare(String(b.staff.name)));

                inShiftList.forEach(({ staff }) => {
                    staffSelect.insertAdjacentHTML('beforeend', `<option value="${staff.id}">${fbEscHtml(staff.name)}</option>`);
                });
                overtimeList.forEach(({ staff, entry }) => {
                    const deltaLabel = window.LaleenSliceEngine
                        ? window.LaleenSliceEngine.formatOvertimeDelta(entry.overtime_minutes)
                        : `+${entry.overtime_minutes}m`;
                    const suffix = entry.shift_end ? ` (OT ${deltaLabel} · shift ends ${entry.shift_end})` : ` (OT ${deltaLabel})`;
                    staffSelect.insertAdjacentHTML('beforeend', `<option value="${staff.id}">${fbEscHtml(staff.name + suffix)}</option>`);
                });
                unavailableList.forEach(s => {
                    const isPreferred = preferredId && String(s.id) === String(preferredId);
                    staffSelect.insertAdjacentHTML('beforeend', `<option value="${s.id}" ${isPreferred ? '' : 'disabled'}>${fbEscHtml(s.name)} (Unavailable)</option>`);
                });
            } else {
                branchStaff.forEach(s => {
                    staffSelect.insertAdjacentHTML('beforeend', `<option value="${s.id}">${fbEscHtml(s.name)}</option>`);
                });
            }

            staffSelect.disabled = false;

            if (preferredId && [...staffSelect.options].some(o => String(o.value) === String(preferredId))) {
                staffSelect.value = String(preferredId);
            }
            fbUpdateBookStaffOvertimeAlert();
        }

        let editingAppointmentId = null;
        const FB_STORE_URL = "{{ route('appointments.store') }}";
        let selectedServices = [];
        let decideInSalon = false;
        let selectedCombos = [];
        let fbActiveComboDraft = null;
        let comboRowSeq = 0;
        let selectedFreebiePackages = []; // [{ rowId, pkg, anchorStaffId, anchorParallel, slots: [{ slotNumber, serviceId: ''|number, staffId, isParallel }] }]
        let freebieRowSeq = 0;
        let selectedClient = null; // { id, name, phone } or null
        let pendingStaffId = null;
        let staffRequestSeq = 0;
        let clientSearchTimer = null;
        let clientPendingPackages = []; // this client's redeemable package services, fetched once picked
        let selectedRedemptions = []; // subset of the above actually being applied to this booking
        let redeemStaffMap = {}; // { "pkgId:svcId": staffId }
        let redeemParallelMap = {}; // { "pkgId:svcId": boolean }
        let redeemStaffOptionsMap = {}; // { "pkgId:svcId": { sig, options } }
        let redeemStaffRequestSeq = 0;
        let clientFreebieCredits = []; // available non-expired freebie credits for selected client
        let selectedFreebieRedemptions = []; // [{ credit_id, package_name, slot_number, days_left, eligible_services, service_id, staff_id, is_parallel }]
        let pendingAutoRedeemFreebieCreditId = null;

        function fbCurrentBaseTimeHHMM() {
            const isUnscheduled = document.getElementById('bookUnscheduledToggle').checked;
            const timeVal = document.getElementById('bookTimeInput').value || '';
            return (!isUnscheduled && timeVal) ? timeVal : '';
        }

        function fbCurrentDaySnapshot() {
            if (!window.LaleenSliceEngine) return null;
            const branch = fbNormalizeBranch(document.getElementById('bookBranch').value) || fbResolveActiveCalendarBranch();
            const dateVal = document.getElementById('bookDateInput').value;
            return dateVal ? window.LaleenSliceEngine.getCachedDaySnapshot(branch, dateVal) : null;
        }

        function fbHasAnyActiveTodaySlices() {
            if (selectedServices.length > 0) return true;
            if (selectedRedemptions.length > 0) return true;
            if (selectedFreebiePackages.length > 0) return true;
            if (selectedFreebieRedemptions.length > 0) return true;
            return selectedCombos.some(r => Array.isArray(r.immediateIds) && r.immediateIds.length > 0);
        }

        function fbComputeUnifiedTimeline() {
            const baseTime = fbCurrentBaseTimeHHMM();
            const rawItems = [];

            selectedServices.forEach((s, idx) => {
                rawItems.push({
                    uid: `svc:${idx}`,
                    kind: 'standalone',
                    idx,
                    service_id: s.id || null,
                    name: s.name,
                    duration: Number(s.duration) || 30,
                    staff_id: s.staff_id || '',
                    is_parallel: Boolean(s.is_parallel)
                });
            });

            selectedFreebiePackages.forEach((fpRow, fpIdx) => {
                const pkg = fpRow.pkg;
                rawItems.push({
                    uid: `freebie_anchor:${fpRow.rowId}`,
                    kind: 'freebie_anchor',
                    rowId: fpRow.rowId,
                    fpIdx,
                    service_id: Number(pkg.anchor_service_id),
                    name: pkg.anchor_service_name,
                    duration: Number(pkg.anchor_duration) || 60,
                    staff_id: fpRow.anchorStaffId || '',
                    is_parallel: Boolean(fpRow.anchorParallel)
                });
                const poolMap = new Map((pkg.eligible_services || []).map(es => [Number(es.id), es]));
                (fpRow.slots || []).forEach(sl => {
                    if (!sl.serviceId) return;
                    const es = poolMap.get(Number(sl.serviceId));
                    if (!es) return;
                    rawItems.push({
                        uid: `freebie_slot:${fpRow.rowId}:${sl.slotNumber}`,
                        kind: 'freebie_slot',
                        rowId: fpRow.rowId,
                        slotNumber: sl.slotNumber,
                        service_id: Number(es.id),
                        name: es.name,
                        duration: Number(es.duration) || 30,
                        staff_id: sl.staffId || '',
                        is_parallel: Boolean(sl.isParallel)
                    });
                });
            });

            selectedFreebieRedemptions.forEach((fr, frIdx) => {
                const chosen = (fr.eligible_services || []).find(es => Number(es.id) === Number(fr.service_id)) || (fr.eligible_services || [])[0];
                rawItems.push({
                    uid: `freebie_redeem:${fr.credit_id}`,
                    kind: 'freebie_redeem',
                    frIdx,
                    creditId: fr.credit_id,
                    service_id: chosen ? Number(chosen.id) : null,
                    name: chosen ? chosen.name : 'Freebie Service',
                    duration: chosen ? (Number(chosen.duration) || 30) : 30,
                    staff_id: fr.staff_id || '',
                    is_parallel: Boolean(fr.is_parallel)
                });
            });

            selectedRedemptions.forEach((r, idx) => {
                rawItems.push({
                    uid: `redeem:${r.id}`,
                    kind: 'redemption',
                    idx,
                    token: r.id,
                    service_id: r.service_id || null,
                    name: r.service_name,
                    duration: Number(r.duration) || 30,
                    staff_id: redeemStaffMap[r.id] || '',
                    is_parallel: Boolean(redeemParallelMap[r.id])
                });
            });

            selectedCombos.forEach(row => {
                const poolById = new Map((row.combo.services || []).map(cs => [Number(cs.id), cs]));
                (row.immediateIds || []).forEach((sid, idx) => {
                    const cs = poolById.get(Number(sid));
                    if (!cs) return;
                    rawItems.push({
                        uid: `combo:${row.id}:${sid}`,
                        kind: 'combo',
                        rowId: row.id,
                        sid: Number(sid),
                        idx,
                        service_id: Number(sid),
                        name: cs.name,
                        duration: Number(cs.duration) || 30,
                        staff_id: (row.serviceStaff && row.serviceStaff[sid]) ? String(row.serviceStaff[sid]) : '',
                        is_parallel: Boolean(row.serviceParallel && row.serviceParallel[sid])
                    });
                });
            });

            const tl = window.LaleenSliceEngine
                ? window.LaleenSliceEngine.computeTimeline(baseTime, rawItems.map((it, pos) => Object.assign({}, it, { sort_order: pos })))
                : { slices: rawItems, spanMinutes: rawItems.reduce((a, b) => a + b.duration, 0) };

            const byUid = new Map();
            tl.slices.forEach(sl => {
                byUid.set(sl.uid, sl);
                if (sl.kind === 'standalone' && selectedServices[sl.idx]) {
                    selectedServices[sl.idx].is_parallel = Boolean(sl.is_parallel);
                } else if (sl.kind === 'redemption') {
                    redeemParallelMap[sl.token] = Boolean(sl.is_parallel);
                } else if (sl.kind === 'combo') {
                    const targetRow = selectedCombos.find(r => Number(r.id) === Number(sl.rowId));
                    if (targetRow) {
                        targetRow.serviceParallel = targetRow.serviceParallel || {};
                        targetRow.serviceParallel[sl.sid] = Boolean(sl.is_parallel);
                    }
                } else if (sl.kind === 'freebie_anchor') {
                    const fpRow = selectedFreebiePackages.find(r => Number(r.rowId) === Number(sl.rowId));
                    if (fpRow) fpRow.anchorParallel = Boolean(sl.is_parallel);
                } else if (sl.kind === 'freebie_slot') {
                    const fpRow = selectedFreebiePackages.find(r => Number(r.rowId) === Number(sl.rowId));
                    const slotObj = fpRow ? (fpRow.slots || []).find(s => Number(s.slotNumber) === Number(sl.slotNumber)) : null;
                    if (slotObj) slotObj.isParallel = Boolean(sl.is_parallel);
                } else if (sl.kind === 'freebie_redeem') {
                    const frObj = selectedFreebieRedemptions.find(r => Number(r.credit_id) === Number(sl.creditId));
                    if (frObj) frObj.is_parallel = Boolean(sl.is_parallel);
                }
            });

            return { timeline: tl, byUid };
        }

        function fbAutoAssignUnpickedTodaySlices() {
            if (!window.LaleenSliceEngine) return false;
            const branch = fbNormalizeBranch(document.getElementById('bookBranch').value) || fbResolveActiveCalendarBranch();
            const daySnap = fbCurrentDaySnapshot();
            let changed = false;

            const pass = fbComputeUnifiedTimeline();
            pass.timeline.slices.forEach((sl, pos) => {
                const prior = pass.timeline.slices.slice(0, pos);
                if (sl.kind === 'standalone') {
                    const item = selectedServices[sl.idx];
                    if (item && !item.manualStaff && !item.staff_id) {
                        const picked = window.LaleenSliceEngine.pickAutoSpecialist({
                            branch,
                            serviceId: item.id,
                            serviceName: item.name,
                            startMin: sl.startMin,
                            endMin: sl.endMin,
                            clickedStaffId: pendingStaffId,
                            daySnapshot: daySnap,
                            excludeApptId: editingAppointmentId || null,
                            priorSlices: prior,
                            allStaffs: ALL_STAFFS
                        });
                        if (picked) {
                            item.staff_id = String(picked);
                            sl.staff_id = String(picked);
                            changed = true;
                        }
                    }
                } else if (sl.kind === 'redemption') {
                    if (!redeemStaffMap[sl.token]) {
                        const picked = window.LaleenSliceEngine.pickAutoSpecialist({
                            branch,
                            serviceId: sl.service_id,
                            serviceName: sl.name,
                            startMin: sl.startMin,
                            endMin: sl.endMin,
                            clickedStaffId: pendingStaffId,
                            daySnapshot: daySnap,
                            excludeApptId: editingAppointmentId || null,
                            priorSlices: prior,
                            allStaffs: ALL_STAFFS
                        });
                        if (picked) {
                            redeemStaffMap[sl.token] = String(picked);
                            sl.staff_id = String(picked);
                            changed = true;
                        }
                    }
                } else if (sl.kind === 'combo') {
                    const row = selectedCombos.find(r => Number(r.id) === Number(sl.rowId));
                    if (row && !(row.serviceStaff && row.serviceStaff[sl.sid])) {
                        const picked = window.LaleenSliceEngine.pickAutoSpecialist({
                            branch,
                            serviceId: sl.sid,
                            serviceName: sl.name,
                            startMin: sl.startMin,
                            endMin: sl.endMin,
                            clickedStaffId: pendingStaffId,
                            daySnapshot: daySnap,
                            excludeApptId: editingAppointmentId || null,
                            priorSlices: prior,
                            allStaffs: ALL_STAFFS
                        });
                        if (picked) {
                            row.serviceStaff = row.serviceStaff || {};
                            row.serviceStaff[sl.sid] = String(picked);
                            sl.staff_id = String(picked);
                            changed = true;
                        }
                    }
                } else if (sl.kind === 'freebie_anchor') {
                    const fpRow = selectedFreebiePackages.find(r => Number(r.rowId) === Number(sl.rowId));
                    if (fpRow && !fpRow.anchorStaffId) {
                        const picked = window.LaleenSliceEngine.pickAutoSpecialist({
                            branch,
                            serviceId: sl.service_id,
                            serviceName: sl.name,
                            startMin: sl.startMin,
                            endMin: sl.endMin,
                            clickedStaffId: pendingStaffId,
                            daySnapshot: daySnap,
                            excludeApptId: editingAppointmentId || null,
                            priorSlices: prior,
                            allStaffs: ALL_STAFFS
                        });
                        if (picked) {
                            fpRow.anchorStaffId = String(picked);
                            sl.staff_id = String(picked);
                            changed = true;
                        }
                    }
                } else if (sl.kind === 'freebie_slot') {
                    const fpRow = selectedFreebiePackages.find(r => Number(r.rowId) === Number(sl.rowId));
                    const slotObj = fpRow ? (fpRow.slots || []).find(s => Number(s.slotNumber) === Number(sl.slotNumber)) : null;
                    if (slotObj && !slotObj.staffId) {
                        const picked = window.LaleenSliceEngine.pickAutoSpecialist({
                            branch,
                            serviceId: sl.service_id,
                            serviceName: sl.name,
                            startMin: sl.startMin,
                            endMin: sl.endMin,
                            clickedStaffId: null,
                            daySnapshot: daySnap,
                            excludeApptId: editingAppointmentId || null,
                            priorSlices: prior,
                            allStaffs: ALL_STAFFS
                        });
                        if (picked) {
                            slotObj.staffId = String(picked);
                            sl.staff_id = String(picked);
                            changed = true;
                        }
                    }
                } else if (sl.kind === 'freebie_redeem') {
                    const frObj = selectedFreebieRedemptions.find(r => Number(r.credit_id) === Number(sl.creditId));
                    if (frObj && !frObj.staff_id && sl.service_id) {
                        const picked = window.LaleenSliceEngine.pickAutoSpecialist({
                            branch,
                            serviceId: sl.service_id,
                            serviceName: sl.name,
                            startMin: sl.startMin,
                            endMin: sl.endMin,
                            clickedStaffId: pendingStaffId,
                            daySnapshot: daySnap,
                            excludeApptId: editingAppointmentId || null,
                            priorSlices: prior,
                            allStaffs: ALL_STAFFS
                        });
                        if (picked) {
                            frObj.staff_id = String(picked);
                            sl.staff_id = String(picked);
                            changed = true;
                        }
                    }
                }
            });

            return changed;
        }

        function fbEnsureDaySnapshotAndRefresh() {
            if (!window.LaleenSliceEngine) return;
            const branch = fbNormalizeBranch(document.getElementById('bookBranch').value) || fbResolveActiveCalendarBranch();
            const dateVal = document.getElementById('bookDateInput').value;
            if (!dateVal) return;

            window.LaleenSliceEngine.ensureDaySnapshot(branch, dateVal).then(() => {
                fbAutoAssignUnpickedTodaySlices();
                fbRenderAllListsWithoutRecursion();
                if (fbActiveComboDraft) {
                    fbRenderComboConfigPanel();
                }
            });
        }

        function fbRenderAllListsWithoutRecursion() {
            fbRenderServices(true);
            fbRenderCombos(true);
            fbRenderFreebiePackages(true);
            fbRenderRedemptions(true);
            fbRenderFreebieRedemptions(true);
            fbRenderFreebieAlertBanner();
            fbUpdateTeamMemberVisibility();
            fbSyncHiddenAndTotals();
        }

        function fbSetModalMode(appointmentId) {
            const form = document.getElementById('calendarBookForm');
            const methodInput = document.getElementById('bookMethodHidden');
            const editBanner = document.getElementById('fbEditModeBanner');
            const saveBtn = document.getElementById('fbSaveBtn');

            if (appointmentId) {
                editingAppointmentId = Number(appointmentId);
                if (form) form.action = `/appointments/${editingAppointmentId}/full-edit`;
                if (methodInput) {
                    methodInput.value = 'PUT';
                    methodInput.disabled = false;
                }
                if (editBanner) editBanner.classList.remove('d-none');
                if (saveBtn) saveBtn.textContent = 'Save Changes';
            } else {
                editingAppointmentId = null;
                if (form) form.action = FB_STORE_URL;
                if (methodInput) {
                    methodInput.value = '';
                    methodInput.disabled = true;
                }
                if (editBanner) editBanner.classList.add('d-none');
                if (saveBtn) saveBtn.textContent = 'Save';
            }
        }

        window.openCalendarBookModal = function(prefill) {
            prefill = prefill || {};
            fbSetModalMode(null);

            selectedServices = [];
            decideInSalon = false;
            document.getElementById('bookDecideInSalonHidden').value = '0';
            selectedCombos = [];
            fbActiveComboDraft = null;
            selectedFreebiePackages = [];
            selectedClient = null;
            clientPendingPackages = [];
            selectedRedemptions = [];
            redeemStaffMap = {};
            redeemParallelMap = {};
            redeemStaffOptionsMap = {};
            clientFreebieCredits = [];
            selectedFreebieRedemptions = [];
            pendingAutoRedeemFreebieCreditId = prefill.redeemFreebieCreditId ? Number(prefill.redeemFreebieCreditId) : null;
            pendingStaffId = prefill.staffId || null;
            fbClearBookingError();
            fbClearAgentError();

            fbLatestAvailableStaffMeta = new Map();
            document.getElementById('bookStaffSelect').innerHTML = '<option value="">-- Select Staff (Unassigned) --</option>';
            document.getElementById('bookStaffSelect').classList.remove('is-overtime');
            const otAlertEl = document.getElementById('bookStaffOvertimeAlert');
            if (otAlertEl) otAlertEl.innerHTML = '';
            document.getElementById('bookStaffHelp').classList.add('d-none');
            const bookAgentEl = document.getElementById('bookAgent');
            if (bookAgentEl) {
                const prefillAgentVal = prefill.bookingAgentId ? String(prefill.bookingAgentId) : '';
                bookAgentEl.value = fbIsValidBookingAgentSelection(prefillAgentVal) ? prefillAgentVal : '';
            }
            document.getElementById('bookNotes').value = '';
            document.getElementById('bookThen').value = '';
            document.getElementById('fbAddClientForm').classList.add('d-none');
            document.getElementById('fbClientSearch').value = '';
            document.getElementById('fbClientResults').innerHTML = '';
            document.getElementById('fbNewClientName').value = '';
            document.getElementById('fbNewClientPhone').value = '';
            document.getElementById('fbNewClientCountryCode').value = '+974';
            document.getElementById('fbNewClientCountryDisplay').textContent = '🇶🇦 +974';
            fbResetCountrySearch();
            document.getElementById('fbNewClientPhoneError').classList.add('d-none');

            if (prefill.client && (prefill.client.name || prefill.client.phone || prefill.client.id)) {
                selectedClient = {
                    id: prefill.client.id || null,
                    name: prefill.client.name || 'Client',
                    phone: prefill.client.phone || ''
                };
            } else if (prefill.customerName || prefill.phone || prefill.customerId) {
                selectedClient = {
                    id: prefill.customerId || null,
                    name: prefill.customerName || 'Client',
                    phone: prefill.phone || ''
                };
            }

            const wantedIds = Array.isArray(prefill.serviceIds)
                ? prefill.serviceIds.map(id => Number(id)).filter(Boolean)
                : [];
            const wantedNames = Array.isArray(prefill.services)
                ? prefill.services.map(s => String(s || '').trim().toLowerCase()).filter(Boolean)
                : [];

            if (wantedIds.length || wantedNames.length) {
                ALL_SERVICES.forEach(svc => {
                    const matchId = svc.id && wantedIds.includes(Number(svc.id));
                    const matchName = svc.name && wantedNames.includes(svc.name.trim().toLowerCase());
                    if ((matchId || matchName) && !selectedServices.some(s => s.name === svc.name)) {
                        selectedServices.push(Object.assign({}, svc, {
                            catalog_price: Number(svc.price) || 0,
                            original_price: Number(svc.price) || 0,
                            price: Number(svc.price) || 0,
                            discount_type: 'fixed',
                            discount_value: 0,
                            discount_amount: 0,
                            discount_reason: '',
                            discountOpen: false,
                            staff_id: '',
                            is_parallel: false,
                            manualStaff: false
                        }));
                    }
                });
            }

            if (prefill.datetime) {
                const [d, t] = prefill.datetime.split('T');
                document.getElementById('bookDateInput').value = d;
                document.getElementById('bookTimeInput').value = t || '';
            } else if (prefill.date) {
                document.getElementById('bookDateInput').value = prefill.date;
                document.getElementById('bookTimeInput').value = '';
            } else if (!document.getElementById('bookDateInput').value) {
                const now = new Date();
                const yyyy = now.getFullYear();
                const mm = String(now.getMonth() + 1).padStart(2, '0');
                const dd = String(now.getDate()).padStart(2, '0');
                document.getElementById('bookDateInput').value = `${yyyy}-${mm}-${dd}`;
                const hh = Math.min(20, Math.max(12, now.getHours() + 1));
                document.getElementById('bookTimeInput').value = `${String(hh).padStart(2, '0')}:00`;
            }

            const unscheduledToggle = document.getElementById('bookUnscheduledToggle');
            unscheduledToggle.checked = !!prefill.unscheduled;
            document.getElementById('bookTimeInput').disabled = !!prefill.unscheduled;
            if (prefill.unscheduled) {
                document.getElementById('bookTimeInput').value = '';
            }

            const resolvedBranch = fbNormalizeBranch(prefill.branch) || fbResolveActiveCalendarBranch();
            document.getElementById('bookBranch').value = resolvedBranch;

            fbAutoAssignUnpickedTodaySlices();
            fbUpdateRail();
            fbRenderAllListsWithoutRecursion();
            fbUpdateRedeemButton();
            if (selectedClient && selectedClient.id) {
                fbLoadClientPendingPackages();
            } else if (selectedClient && selectedClient.phone) {
                fbResolveClientByPhone(selectedClient.phone);
            }
            fbUpdateDateLabel();
            fbShowPanel('main');

            const modalEl = document.getElementById('calendarBookModal');
            const drawer = bootstrap.Offcanvas.getOrCreateInstance(modalEl);
            drawer.show();

            fbEnsureDaySnapshotAndRefresh();
            loadAvailableStaff();
        };

        window.openCalendarEditModal = function(appt) {
            if (!appt || !appt.id) return;
            fbSetModalMode(appt.id);

            selectedServices = [];
            decideInSalon = false;
            document.getElementById('bookDecideInSalonHidden').value = '0';
            selectedCombos = [];
            fbActiveComboDraft = null;
            selectedFreebiePackages = [];
            selectedClient = {
                id: appt.customer_id || null,
                name: appt.customer_name || 'Client',
                phone: appt.phone || ''
            };
            clientPendingPackages = [];
            selectedRedemptions = [];
            redeemStaffMap = {};
            redeemParallelMap = {};
            redeemStaffOptionsMap = {};
            clientFreebieCredits = [];
            selectedFreebieRedemptions = [];
            pendingAutoRedeemFreebieCreditId = null;
            pendingStaffId = appt.staff_id ? String(appt.staff_id) : null;
            fbClearBookingError();
            fbClearAgentError();

            fbLatestAvailableStaffMeta = new Map();
            document.getElementById('bookStaffSelect').innerHTML = '<option value="">-- Select Staff (Unassigned) --</option>';
            document.getElementById('bookStaffSelect').classList.remove('is-overtime');
            const otAlertEl = document.getElementById('bookStaffOvertimeAlert');
            if (otAlertEl) otAlertEl.innerHTML = '';
            document.getElementById('bookStaffHelp').classList.add('d-none');

            const bookAgentEl = document.getElementById('bookAgent');
            if (bookAgentEl) {
                const existingAgentVal = appt.booking_agent_select_value ? String(appt.booking_agent_select_value) : '';
                bookAgentEl.value = fbIsValidBookingAgentSelection(existingAgentVal) ? existingAgentVal : '';
            }

            document.getElementById('bookNotes').value = appt.notes || '';
            document.getElementById('bookThen').value = '';
            document.getElementById('fbAddClientForm').classList.add('d-none');
            document.getElementById('fbClientSearch').value = '';
            document.getElementById('fbClientResults').innerHTML = '';
            document.getElementById('fbNewClientName').value = '';
            document.getElementById('fbNewClientPhone').value = '';
            document.getElementById('fbNewClientPhoneError').classList.add('d-none');

            const resolvedBranch = fbNormalizeBranch(appt.branch_raw) || fbResolveActiveCalendarBranch();
            document.getElementById('bookBranch').value = resolvedBranch;
            document.getElementById('bookDateInput').value = appt.date || '';
            const isUnsched = Boolean(appt.is_unscheduled) || !appt.time;
            document.getElementById('bookUnscheduledToggle').checked = isUnsched;
            document.getElementById('bookTimeInput').disabled = isUnsched;
            document.getElementById('bookTimeInput').value = isUnsched ? '' : (appt.time || '');

            // 1. Pre-populate Originating Combo Packages & External Package Redemptions
            const comboPkgs = Array.isArray(appt.combo_packages) ? appt.combo_packages : [];
            comboPkgs.forEach(pkg => {
                const poolArr = Array.isArray(pkg.pool_services)
                    ? pkg.pool_services
                    : (Array.isArray(pkg.pool) ? pkg.pool : []);
                if (pkg.is_originating) {
                    const catalogCombo = ALL_COMBOS.find(c => Number(c.id) === Number(pkg.combo_id)) || {
                        id: Number(pkg.combo_id),
                        name: pkg.combo_name || 'Combo',
                        price: Number(pkg.price_paid || 0),
                        quantity_included: Number(pkg.quantity_included || poolArr.length || 1),
                        effective_duration: 60,
                        services: poolArr.map(s => ({
                            id: Number(s.id),
                            name: s.name,
                            duration: Number(s.duration || 30),
                            price: Number(s.catalog_price || 0)
                        }))
                    };

                    const todayEntries = poolArr
                        .filter(s => s.state === 'today')
                        .sort((a, b) => (Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0)));
                    const immediateIds = (Array.isArray(pkg.ordered_today_ids) && pkg.ordered_today_ids.length)
                        ? pkg.ordered_today_ids.map(id => Number(id))
                        : todayEntries.map(s => Number(s.id));
                    const pendingIds = poolArr.filter(s => s.state === 'pending').map(s => Number(s.id));
                    const serviceStaff = {};
                    const serviceParallel = {};
                    todayEntries.forEach(s => {
                        if (s.staff_id) serviceStaff[Number(s.id)] = String(s.staff_id);
                        serviceParallel[Number(s.id)] = Boolean(s.is_parallel);
                    });

                    selectedCombos.push({
                        id: ++comboRowSeq,
                        combo: catalogCombo,
                        immediateIds,
                        pendingIds,
                        serviceStaff,
                        serviceParallel
                    });
                } else {
                    poolArr.filter(s => s.state === 'today').forEach(s => {
                        const token = `${pkg.client_package_id}:${s.id}`;
                        selectedRedemptions.push({
                            id: token,
                            client_package_id: Number(pkg.client_package_id),
                            service_id: Number(s.id),
                            service_name: s.name,
                            combo_name: pkg.combo_name || 'Package',
                            duration: Number(s.duration || 30)
                        });
                        if (s.staff_id) redeemStaffMap[token] = String(s.staff_id);
                        redeemParallelMap[token] = Boolean(s.is_parallel);
                    });
                }
            });

            // 2. Pre-populate Service + Freebies Anchor Packages
            const freebiePkgs = Array.isArray(appt.freebie_packages) ? appt.freebie_packages : [];
            freebiePkgs.forEach(fp => {
                const catalogFp = ALL_FREEBIE_PACKAGES.find(p => Number(p.id) === Number(fp.package_id));
                if (!catalogFp) return;
                const slots = (Array.isArray(fp.slots) && fp.slots.length)
                    ? fp.slots.map((sl, i) => ({
                        slotNumber: Number(sl.slot_number || (i + 1)),
                        serviceId: sl.mode === 'same_day' && sl.service_id ? Number(sl.service_id) : '',
                        staffId: sl.staff_id ? String(sl.staff_id) : '',
                        isParallel: Boolean(sl.is_parallel)
                    }))
                    : Array.from({ length: Number(catalogFp.free_choices_count) || 1 }, (_, i) => ({
                        slotNumber: i + 1,
                        serviceId: '',
                        staffId: '',
                        isParallel: false
                    }));

                const fpCatalogPrice = Number(fp.catalog_price !== undefined ? fp.catalog_price : catalogFp.price) || 0;
                const fpOrigPrice = Number(fp.original_price !== undefined ? fp.original_price : fpCatalogPrice) || 0;
                const fpNetPrice = Number(fp.price !== undefined ? fp.price : fpCatalogPrice) || 0;
                const fpDiscAmt = Number(fp.discount_amount !== undefined ? fp.discount_amount : Math.max(0, fpOrigPrice - fpNetPrice)) || 0;
                const fpDiscType = fp.discount_type === 'percent' ? 'percent' : 'fixed';
                const fpDiscVal = Number(fp.discount_value !== undefined ? fp.discount_value : (fpDiscType === 'fixed' ? fpDiscAmt : 0)) || 0;

                selectedFreebiePackages.push({
                    rowId: ++freebieRowSeq,
                    pkg: catalogFp,
                    anchorStaffId: fp.anchor_staff_id ? String(fp.anchor_staff_id) : '',
                    anchorParallel: Boolean(fp.anchor_is_parallel),
                    catalog_price: fpCatalogPrice,
                    original_price: fpOrigPrice,
                    price: fpNetPrice,
                    discount_type: fpDiscType,
                    discount_value: fpDiscVal,
                    discount_amount: fpDiscAmt,
                    discount_reason: fp.discount_reason || '',
                    discountOpen: fpDiscAmt > 0,
                    slots
                });
            });

            // 3. Pre-populate External Freebie Credit Redemptions
            const freebieRedeems = Array.isArray(appt.freebie_redemptions) ? appt.freebie_redemptions : [];
            freebieRedeems.forEach(fr => {
                selectedFreebieRedemptions.push({
                    credit_id: Number(fr.credit_id),
                    package_name: fr.package_name || 'Service + Freebies',
                    slot_number: Number(fr.slot_number || 1),
                    days_left: Number(fr.days_left || 0),
                    eligible_services: Array.isArray(fr.eligible_services) ? fr.eligible_services : [],
                    service_id: Number(fr.service_id || 0),
                    staff_id: fr.staff_id ? String(fr.staff_id) : '',
                    is_parallel: Boolean(fr.is_parallel)
                });
            });

            // 4. Pre-populate Standalone Services
            const allSvcLines = Array.isArray(appt.services) ? appt.services : [];
            const standaloneLines = allSvcLines.filter(s =>
                !s.is_pending
                && !s.client_package_id
                && !s.combo_id
                && !s.combo_name
                && !s.is_package_redemption
                && !s.freebie_package_id
                && !s.is_freebie_redemption
            );
            standaloneLines.forEach(s => {
                const catalogMatch = ALL_SERVICES.find(c =>
                    (s.service_id && Number(c.id) === Number(s.service_id))
                    || String(c.name || '').trim().toLowerCase() === String(s.name || '').trim().toLowerCase()
                );
                const catalogP = catalogMatch ? (Number(catalogMatch.price) || 0) : (Number(s.original_price !== undefined ? s.original_price : s.price) || 0);
                const origP = Number(s.original_price !== undefined ? s.original_price : catalogP) || 0;
                const netP = Number(s.final_price !== undefined ? s.final_price : s.price) || 0;
                const discAmt = Number(s.discount_amount !== undefined ? s.discount_amount : Math.max(0, origP - netP)) || 0;
                const discType = s.discount_type === 'percent' ? 'percent' : 'fixed';
                const discVal = Number(s.discount_value !== undefined ? s.discount_value : (discType === 'fixed' ? discAmt : 0)) || 0;

                selectedServices.push({
                    id: catalogMatch ? Number(catalogMatch.id) : (s.service_id ? Number(s.service_id) : null),
                    name: s.name,
                    duration: Number(s.duration || (catalogMatch ? catalogMatch.duration : 30)),
                    catalog_price: catalogP,
                    price: netP,
                    original_price: origP,
                    discount_type: discType,
                    discount_value: discVal,
                    discount_amount: discAmt,
                    discount_reason: s.discount_reason || '',
                    discountOpen: discAmt > 0,
                    staff_id: s.staff_id ? String(s.staff_id) : '',
                    is_parallel: Boolean(s.is_parallel),
                    manualStaff: Boolean(s.staff_id)
                });
            });

            // 5. Decide in Salon fallback
            if (
                appt.decide_in_salon
                && !selectedServices.length
                && !selectedCombos.length
                && !selectedRedemptions.length
                && !selectedFreebiePackages.length
                && !selectedFreebieRedemptions.length
            ) {
                decideInSalon = true;
            }

            fbUpdateRail();
            fbRenderAllListsWithoutRecursion();
            fbUpdateRedeemButton();
            if (selectedClient && selectedClient.id) {
                fbLoadClientPendingPackages();
            }
            fbUpdateDateLabel();
            fbShowPanel('main');

            const modalEl = document.getElementById('calendarBookModal');
            const drawer = bootstrap.Offcanvas.getOrCreateInstance(modalEl);
            drawer.show();

            fbEnsureDaySnapshotAndRefresh();
            loadAvailableStaff();
        };

        function fbShowPanel(name) {
            const panelMap = {
                main: 'fbPanelMain',
                client: 'fbPanelClient',
                service: 'fbPanelService',
                combo: 'fbPanelCombo',
                comboconfig: 'fbPanelComboConfig',
                redeem: 'fbPanelRedeem',
                freebie: 'fbPanelFreebie',
            };
            Object.entries(panelMap).forEach(([key, id]) => {
                const el = document.getElementById(id);
                if (el) el.classList.toggle('d-none', key !== name);
            });

            const isComboConfig = name === 'comboconfig';
            document.getElementById('fbMainFooter')?.classList.toggle('d-none', isComboConfig);
            document.getElementById('fbComboConfigFooter')?.classList.toggle('d-none', !isComboConfig);

            if (name === 'client') {
                fbRenderClientResults('');
            }
            if (name === 'service') {
                const svcSearch = document.getElementById('fbServiceSearch');
                if (svcSearch) svcSearch.value = '';
                fbRenderServiceResults('');
            }
            if (name === 'combo') {
                const comboSearch = document.getElementById('fbComboCatalogSearch');
                if (comboSearch) comboSearch.value = '';
                fbRenderComboPicker('');
            }
            if (name === 'freebie') {
                const fSearch = document.getElementById('fbFreebieCatalogSearch');
                if (fSearch) fSearch.value = '';
                fbRenderFreebiePicker('');
            }
            if (name === 'redeem') {
                fbRenderRedeemPicker();
            }
        }
        window.fbShowPanel = fbShowPanel;

        function fbUpdateDateLabel() {
            const dateVal = document.getElementById('bookDateInput').value;
            const timeVal = document.getElementById('bookTimeInput').value;
            const isUnscheduled = document.getElementById('bookUnscheduledToggle').checked;
            const branchLabel = fbBranchLabel(document.getElementById('bookBranch').value);
            if (!dateVal) return;
            const d = new Date(dateVal + 'T00:00:00');
            document.getElementById('fbDateLabel').textContent =
                d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });

            if (isUnscheduled || !timeVal) {
                document.getElementById('fbDateSub').textContent = `Unscheduled · ${branchLabel} · Drag onto calendar to assign slot`;
            } else {
                const [h, m] = timeVal.split(':').map(Number);
                const p = h >= 12 ? 'PM' : 'AM';
                const hh = h % 12 || 12;
                document.getElementById('fbDateSub').textContent = `${hh}:${m.toString().padStart(2,'0')} ${p} · ${branchLabel} · Doesn't repeat`;
            }
        }

        window.syncCalendarBookBranch = function(branchKey) {
            const norm = fbNormalizeBranch(branchKey) || fbResolveActiveCalendarBranch();
            const branchEl = document.getElementById('bookBranch');
            if (!branchEl) return;
            branchEl.value = norm;
            fbUpdateDateLabel();
            const modalEl = document.getElementById('calendarBookModal');
            if (modalEl && modalEl.classList.contains('show')) {
                fbEnsureDaySnapshotAndRefresh();
                loadAvailableStaff();
            }
        };

        document.getElementById('bookDateInput').addEventListener('change', () => {
            fbClearBookingError();
            fbUpdateDateLabel();
            fbEnsureDaySnapshotAndRefresh();
            loadAvailableStaff();
        });
        document.getElementById('bookTimeInput').addEventListener('change', () => {
            fbClearBookingError();
            fbUpdateDateLabel();
            fbRenderAllListsWithoutRecursion();
            if (fbActiveComboDraft) fbRenderComboConfigPanel();
            loadAvailableStaff();
        });
        document.getElementById('bookUnscheduledToggle').addEventListener('change', function() {
            fbClearBookingError();
            const timeInput = document.getElementById('bookTimeInput');
            timeInput.disabled = this.checked;
            if (this.checked) {
                timeInput.value = '';
            } else if (!timeInput.value) {
                const now = new Date();
                timeInput.value = `${String(now.getHours()).padStart(2, '0')}:00`;
            }
            fbUpdateDateLabel();
            fbRenderAllListsWithoutRecursion();
            loadAvailableStaff();
        });
        document.getElementById('bookStaffSelect').addEventListener('change', function() {
            fbClearBookingError();
            pendingStaffId = this.value || null;
            fbUpdateBookStaffOvertimeAlert();
        });

        const bookAgentSelectEl = document.getElementById('bookAgent');
        if (bookAgentSelectEl) {
            bookAgentSelectEl.addEventListener('change', function() {
                if (fbIsValidBookingAgentSelection(this.value)) {
                    fbClearAgentError();
                    fbClearBookingError();
                }
            });
        }

        document.getElementById('calendarBookForm').addEventListener('change', function(ev) {
            if (ev.target && (ev.target.matches('.fb-combo-staff-select') || ev.target.matches('.fb-redeem-staff-select'))) {
                fbClearBookingError();
            }
        });

        /* ---------------- RAIL / CLIENT ---------------- */
        function fbUpdateRail() {
            const icon = document.getElementById('fbRailIcon');
            const label = document.getElementById('fbRailLabel');
            const sub = document.getElementById('fbRailSub');
            const editBtn = document.getElementById('fbRailEditNameBtn');
            const inlineEditWrap = document.getElementById('fbRailInlineEditWrap');
            const labelRow = document.getElementById('fbRailLabelRow');

            document.getElementById('bookCustomerNameHidden').value = selectedClient ? (selectedClient.name || '') : '';
            document.getElementById('bookPhoneHidden').value = selectedClient ? (selectedClient.phone || '') : '';

            if (inlineEditWrap) inlineEditWrap.classList.add('d-none');
            if (labelRow) labelRow.classList.remove('d-none');

            if (!selectedClient) {
                icon.innerHTML = '<i class="bx bx-user-plus"></i>';
                label.textContent = 'Add client';
                sub.textContent = 'Search or add a new client';
                if (editBtn) editBtn.classList.add('d-none');
                return;
            }

            fbClearBookingError();
            icon.innerHTML = selectedClient.name ? selectedClient.name.charAt(0).toUpperCase() : '?';
            label.textContent = selectedClient.name || 'Client';
            sub.textContent = selectedClient.phone || '';
            if (editBtn) editBtn.classList.remove('d-none');
        }

        window.quickRenameCalendarClient = function(customerId, newName) {
            const trimmed = String(newName || '').trim();
            return fetch(`/enhanced-calendar/clients/${encodeURIComponent(customerId)}/update-name`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ name: trimmed })
            }).then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok || !data.success) {
                    const msg = (data.errors && data.errors.name && data.errors.name[0])
                        || data.message
                        || 'Please enter a valid client name (at least 2 characters).';
                    throw new Error(msg);
                }
                const updated = data.customer;
                if (selectedClient && String(selectedClient.id) === String(updated.id)) {
                    selectedClient.name = updated.name;
                    fbUpdateRail();
                }
                if (typeof loadCalendar === 'function') {
                    loadCalendar();
                }
                return updated;
            });
        };

        document.getElementById('fbRail').addEventListener('click', () => fbShowPanel('client'));

        (function initRailInlineRename() {
            const editBtn = document.getElementById('fbRailEditNameBtn');
            const labelRow = document.getElementById('fbRailLabelRow');
            const editWrap = document.getElementById('fbRailInlineEditWrap');
            const nameInput = document.getElementById('fbRailInlineNameInput');
            const saveBtn = document.getElementById('fbRailSaveNameBtn');
            const cancelBtn = document.getElementById('fbRailCancelNameBtn');

            if (!editBtn || !editWrap || !nameInput) return;

            function openRailEdit(e) {
                if (e) e.stopPropagation();
                if (!selectedClient) return;
                nameInput.value = selectedClient.name || '';
                labelRow.classList.add('d-none');
                editWrap.classList.remove('d-none');
                nameInput.focus();
                nameInput.select();
            }

            function closeRailEdit(e) {
                if (e) e.stopPropagation();
                editWrap.classList.add('d-none');
                labelRow.classList.remove('d-none');
            }

            function submitRailEdit(e) {
                if (e) e.stopPropagation();
                if (!selectedClient) return;
                const trimmed = nameInput.value.trim();
                if (trimmed.length < 2) {
                    if (typeof showCalToast === 'function') {
                        showCalToast('Client name must be at least 2 characters.', 'error');
                    }
                    nameInput.focus();
                    return;
                }

                if (selectedClient.id) {
                    saveBtn.disabled = true;
                    window.quickRenameCalendarClient(selectedClient.id, trimmed)
                        .then(updated => {
                            selectedClient.name = updated.name;
                            fbUpdateRail();
                            if (typeof showCalToast === 'function') {
                                showCalToast('Client name updated');
                            }
                        })
                        .catch(err => {
                            if (typeof showCalToast === 'function') {
                                showCalToast(err.message || 'Could not update client name.', 'error');
                            }
                        })
                        .finally(() => {
                            saveBtn.disabled = false;
                        });
                } else {
                    selectedClient.name = trimmed;
                    fbUpdateRail();
                    if (typeof showCalToast === 'function') {
                        showCalToast('Client name updated');
                    }
                }
            }

            editBtn.addEventListener('click', openRailEdit);
            cancelBtn.addEventListener('click', closeRailEdit);
            saveBtn.addEventListener('click', submitRailEdit);
            nameInput.addEventListener('keydown', function(e) {
                e.stopPropagation();
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitRailEdit(e);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    closeRailEdit(e);
                }
            });
        })();

        window.fbShowAddClientForm = function() {
            document.getElementById('fbAddClientForm').classList.remove('d-none');
        };

        window.fbSelectCountry = function(el) {
            document.getElementById('fbNewClientCountryCode').value = el.dataset.code;
            document.getElementById('fbNewClientCountryDisplay').textContent = el.textContent.trim().split(/\s+/)[0] + ' ' + el.dataset.code;
            fbResetCountrySearch();
            bootstrap.Dropdown.getOrCreateInstance(document.getElementById('fbNewClientCountryToggle')).hide();
        };

        function fbResetCountrySearch() {
            const search = document.getElementById('countrySearch');
            search.value = '';
            fbFilterCountries('');
        }

        function fbFilterCountries(term) {
            const needle = term.trim().toLowerCase();
            const options = document.querySelectorAll('.fb-country-option');
            let anyVisible = false;

            options.forEach(function(option) {
                const iso = (option.dataset.iso || '').toLowerCase();
                const code = (option.dataset.code || '').toLowerCase();
                const country = (option.dataset.country || '').toLowerCase();
                const matches = !needle
                    || iso.includes(needle)
                    || code.includes(needle)
                    || code.replace('+', '').includes(needle.replace('+', ''))
                    || country.includes(needle);

                option.closest('li').style.display = matches ? '' : 'none';
                if (matches) anyVisible = true;
            });

            document.getElementById('fbCountryNoResults').classList.toggle('d-none', anyVisible);
        }

        (function() {
            const search = document.getElementById('countrySearch');
            search.addEventListener('click', (e) => e.stopPropagation());
            search.addEventListener('keydown', (e) => e.stopPropagation());
            search.addEventListener('keyup', () => fbFilterCountries(search.value));
            search.addEventListener('input', () => fbFilterCountries(search.value));

            document.getElementById('fbNewClientCountryToggle').addEventListener('shown.bs.dropdown', () => {
                search.focus();
            });
        })();

        function fbLookupPhoneRequest(fullPhone) {
            return fetch("{{ route('customers.lookup') }}?phone=" + encodeURIComponent(fullPhone), {
                headers: { 'Accept': 'application/json' }
            }).then(r => r.json()).catch(() => ({ found: false }));
        }

        function fbResolveClientByPhone(fullPhone) {
            if (!fullPhone) return Promise.resolve(null);
            return fbLookupPhoneRequest(fullPhone).then(data => {
                if (data && data.found && data.id && selectedClient && selectedClient.phone === fullPhone) {
                    selectedClient.id = data.id;
                    if (!selectedClient.name || selectedClient.name === 'Client') {
                        selectedClient.name = data.name || selectedClient.name;
                    }
                    fbUpdateRail();
                    fbLoadClientPendingPackages();
                    return data;
                }
                return null;
            });
        }

        document.getElementById('fbNewClientPhone').addEventListener('blur', function() {
            const countryCode = document.getElementById('fbNewClientCountryCode').value;
            const localDigits = this.value.trim().replace(/\D/g, '');
            if (localDigits.length < 4) return;
            const fullPhone = countryCode + localDigits;
            fbLookupPhoneRequest(fullPhone).then(data => {
                if (data && data.found) {
                    const nameInput = document.getElementById('fbNewClientName');
                    if (nameInput && !nameInput.value.trim() && data.name) {
                        nameInput.value = data.name;
                    }
                }
            });
        });

        window.fbConfirmNewClient = function() {
            const name = document.getElementById('fbNewClientName').value.trim();
            const countryCode = document.getElementById('fbNewClientCountryCode').value;
            const rawPhone = document.getElementById('fbNewClientPhone').value.trim();
            const errorEl = document.getElementById('fbNewClientPhoneError');
            const localDigits = rawPhone.replace(/\D/g, '');
            const fullPhone = countryCode + localDigits;

            errorEl.classList.add('d-none');

            if (!name) {
                errorEl.textContent = "Please enter the client's name.";
                errorEl.classList.remove('d-none');
                return;
            }

            // E.164-style check: + then 7-15 digits total, first digit non-zero.
            if (!/^\+[1-9]\d{6,14}$/.test(fullPhone)) {
                errorEl.textContent = localDigits
                    ? 'Enter a valid phone number for the selected country.'
                    : 'Please enter a phone number.';
                errorEl.classList.remove('d-none');
                return;
            }

            selectedClient = { name, phone: fullPhone };
            selectedRedemptions = [];
            selectedFreebieRedemptions = [];
            redeemStaffMap = {};
            redeemStaffOptionsMap = {};
            fbLoadClientPendingPackages();
            fbUpdateRail();
            fbShowPanel('main');
            fbResolveClientByPhone(fullPhone);
        };

        function fbSelectExistingClient(client) {
            selectedClient = { id: client.id, name: client.name, phone: client.phone };
            selectedRedemptions = [];
            selectedFreebieRedemptions = [];
            redeemStaffMap = {};
            redeemStaffOptionsMap = {};
            fbUpdateRail();
            fbShowPanel('main');
            fbLoadClientPendingPackages();
        }

        /* ---------------- REDEEM PENDING PACKAGE SERVICE ---------------- */
        function fbPackageRemainingSlots(pkgId) {
            const pkgEntries = clientPendingPackages.filter(p => p.client_package_id === pkgId);
            if (!pkgEntries.length) return 0;
            const serverRemaining = (pkgEntries[0].remaining_count !== undefined && pkgEntries[0].remaining_count !== null)
                ? Number(pkgEntries[0].remaining_count)
                : pkgEntries.length;
            const pickedForPkg = selectedRedemptions.filter(r => r.client_package_id === pkgId).length;
            const unpickedOptions = pkgEntries.filter(p => !selectedRedemptions.some(r => r.id === p.id)).length;
            return Math.max(0, Math.min(serverRemaining - pickedForPkg, unpickedOptions));
        }

        function fbTotalRemainingRedeemSlots() {
            const seenPkgIds = new Set();
            let total = 0;
            clientPendingPackages.forEach(p => {
                if (!seenPkgIds.has(p.client_package_id)) {
                    seenPkgIds.add(p.client_package_id);
                    total += fbPackageRemainingSlots(p.client_package_id);
                }
            });
            return total;
        }

        function fbLoadClientPendingPackages() {
            clientPendingPackages = [];
            clientFreebieCredits = [];
            fbUpdateRedeemButton();
            fbRenderFreebieAlertBanner();

            if (!selectedClient || !selectedClient.id) return;

            fetch(`/customers/${selectedClient.id}/pending-packages`)
                .then(res => res.json())
                .then(data => {
                    clientPendingPackages = Array.isArray(data) ? data : [];
                    fbUpdateRedeemButton();
                })
                .catch(() => {});

            fetch(`/customers/${selectedClient.id}/freebie-credits`)
                .then(res => res.json())
                .then(data => {
                    clientFreebieCredits = Array.isArray(data) ? data : [];
                    if (pendingAutoRedeemFreebieCreditId) {
                        const targetCredit = clientFreebieCredits.find(c => Number(c.credit_id) === Number(pendingAutoRedeemFreebieCreditId));
                        pendingAutoRedeemFreebieCreditId = null;
                        if (targetCredit) {
                            fbActivateFreebieRedemption(targetCredit.credit_id);
                            return;
                        }
                    }
                    fbRenderFreebieAlertBanner();
                })
                .catch(() => {});
        }

        function fbUpdateRedeemButton() {
            const btn = document.getElementById('fbOpenRedeemPanel');
            const totalRemaining = fbTotalRemainingRedeemSlots();
            btn.classList.toggle('d-none', clientPendingPackages.length === 0);
            document.getElementById('fbRedeemBadge').textContent = totalRemaining;
        }

        function fbRenderRedeemPicker() {
            const box = document.getElementById('fbRedeemPickerList');
            const available = clientPendingPackages.filter(p => !selectedRedemptions.some(r => r.id === p.id));

            if (!available.length) {
                box.innerHTML = '<div class="text-muted small px-2">Nothing left to redeem for this client.</div>';
                return;
            }

            box.innerHTML = available.map((p, i) => {
                const pkgSlotsLeft = fbPackageRemainingSlots(p.client_package_id);
                const isCapped = pkgSlotsLeft <= 0;
                const expiryHtml = p.expires_at === null
                    ? '<span class="fb-redeem-expiry ok">starts at Visit 1 checkout</span>'
                    : `<span class="fb-redeem-expiry ${p.days_left <= 2 ? 'soon' : 'ok'}">${p.days_left <= 0 ? 'expires today' : 'expires ' + p.expires_at + ' (' + p.days_left + 'd left)'}</span>`;
                const capNote = isCapped
                    ? ' · <span class="text-warning">package slot limit reached</span>'
                    : ` · <span>${pkgSlotsLeft} slot${pkgSlotsLeft === 1 ? '' : 's'} left</span>`;

                return `
                <div class="fb-redeem-pick-row ${isCapped ? 'disabled' : ''}" data-idx="${i}" data-disabled="${isCapped ? '1' : '0'}">
                    <div class="flex-grow-1">
                        <div class="name">${p.service_name} <span class="text-muted">(${p.duration} min)</span></div>
                        <div class="fb-redeem-row-meta">
                            from ${p.combo_name} · ${expiryHtml}${capNote}
                        </div>
                    </div>
                </div>
            `;
            }).join('');

            box.querySelectorAll('.fb-redeem-pick-row').forEach(row => {
                row.addEventListener('click', () => {
                    if (row.dataset.disabled === '1') return;
                    const item = available[Number(row.dataset.idx)];
                    if (!item || fbPackageRemainingSlots(item.client_package_id) <= 0) return;
                    decideInSalon = false;
                    selectedRedemptions.push(item);
                    fbAutoAssignUnpickedTodaySlices();
                    fbRenderAllListsWithoutRecursion();
                    fbUpdateRedeemButton();
                    loadAvailableStaff();
                    fbShowPanel('main');
                });
            });
        }

        function fbRefreshRedeemStaffOptions() {
            fbRenderRedemptions(true);
        }

        function fbRenderRedemptions(skipSync = false) {
            const list = document.getElementById('fbRedeemList');
            const branch = fbNormalizeBranch(document.getElementById('bookBranch').value) || fbResolveActiveCalendarBranch();
            const daySnap = fbCurrentDaySnapshot();
            const { timeline, byUid } = fbComputeUnifiedTimeline();

            list.innerHTML = selectedRedemptions.map((r, i) => {
                const sl = byUid.get(`redeem:${r.id}`);
                const slicePos = sl ? timeline.slices.indexOf(sl) : 0;
                const priorSlices = slicePos > 0 ? timeline.slices.slice(0, slicePos) : [];
                const meta = window.LaleenSliceEngine
                    ? window.LaleenSliceEngine.buildSpecialistSelectMeta({
                        branch,
                        serviceId: r.service_id || null,
                        serviceName: r.service_name,
                        startMin: sl ? sl.startMin : null,
                        endMin: sl ? sl.endMin : null,
                        selectedStaffId: redeemStaffMap[r.id] || '',
                        daySnapshot: daySnap,
                        excludeApptId: editingAppointmentId || null,
                        priorSlices,
                        allStaffs: ALL_STAFFS
                    })
                    : { html: '', unmappedFallback: false, hasConflict: false, needsSelection: false };

                const reorderBtns = selectedRedemptions.length > 1 ? `
                    <div class="laleen-reorder-group me-1">
                        <button type="button" class="laleen-reorder-btn fb-redeem-move-btn" data-idx="${i}" data-dir="-1" ${i === 0 ? 'disabled' : ''} title="Move earlier">↑</button>
                        <button type="button" class="laleen-reorder-btn fb-redeem-move-btn" data-idx="${i}" data-dir="1" ${i === selectedRedemptions.length - 1 ? 'disabled' : ''} title="Move later">↓</button>
                    </div>` : '';

                const parallelBtn = (sl && sl.can_parallel) ? `
                    <button type="button" class="laleen-parallel-pill fb-redeem-parallel-btn ${sl.is_parallel ? 'is-active' : ''}" data-token="${fbEscHtml(r.id)}">
                        ${sl.is_parallel ? '⇉ Same time' : 'Same time'}
                    </button>` : '';

                return `
                <div class="fb-svc-row">
                    <div class="fb-svc-row-main">
                        <div class="d-flex align-items-center gap-1 min-w-0">
                            ${reorderBtns}
                            <div class="min-w-0">
                                <div class="name">${fbEscHtml(r.service_name)} <span class="fb-decide-tag">free · package</span></div>
                                <div class="laleen-slice-sub mt-1">
                                    <span class="laleen-time-pill">${fbEscHtml(sl ? sl.timeBadge : `${r.duration}m`)}</span>
                                    <span class="meta">from ${fbEscHtml(r.combo_name)}</span>
                                    ${meta.unmappedFallback ? '<span class="laleen-unmapped-hint">Unmapped skill</span>' : ''}
                                </div>
                            </div>
                        </div>
                        <button type="button" class="remove-btn fb-redeem-remove-btn" data-idx="${i}"><i class="bx bx-x"></i></button>
                    </div>
                    <div class="d-flex align-items-center justify-content-end gap-2 pt-1">
                        ${parallelBtn}
                        <select class="form-select form-select-sm laleen-specialist-pill fb-redeem-staff-select ${meta.needsSelection ? 'is-needs-specialist' : ''} ${meta.isOvertime ? 'is-overtime' : ''} ${meta.hasConflict ? 'is-conflict' : ''}" data-token="${fbEscHtml(r.id)}">
                            ${meta.html}
                        </select>
                    </div>
                    ${window.LaleenSliceEngine ? window.LaleenSliceEngine.renderOvertimeAlert(meta) : ''}
                </div>
            `;
            }).join('');

            list.querySelectorAll('.fb-redeem-remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const removed = selectedRedemptions.splice(Number(btn.dataset.idx), 1)[0];
                    if (removed) {
                        delete redeemStaffMap[removed.id];
                        delete redeemParallelMap[removed.id];
                        delete redeemStaffOptionsMap[removed.id];
                    }
                    fbRenderAllListsWithoutRecursion();
                    fbUpdateRedeemButton();
                    loadAvailableStaff();
                });
            });

            list.querySelectorAll('.fb-redeem-move-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = Number(btn.dataset.idx);
                    const nextIdx = idx + Number(btn.dataset.dir);
                    if (idx < 0 || nextIdx < 0 || nextIdx >= selectedRedemptions.length) return;
                    [selectedRedemptions[idx], selectedRedemptions[nextIdx]] = [selectedRedemptions[nextIdx], selectedRedemptions[idx]];
                    fbRenderAllListsWithoutRecursion();
                });
            });

            list.querySelectorAll('.fb-redeem-parallel-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const token = btn.dataset.token;
                    redeemParallelMap[token] = !redeemParallelMap[token];
                    fbRenderAllListsWithoutRecursion();
                });
            });

            list.querySelectorAll('.fb-redeem-staff-select').forEach(sel => {
                sel.addEventListener('change', () => {
                    const token = sel.dataset.token;
                    if (sel.value) {
                        redeemStaffMap[token] = sel.value;
                    } else {
                        delete redeemStaffMap[token];
                    }
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
            });

            if (!skipSync) {
                fbUpdateTeamMemberVisibility();
                fbSyncHiddenAndTotals();
            }
        }

        document.getElementById('fbOpenRedeemPanel').addEventListener('click', () => fbShowPanel('redeem'));

        function fbRenderClientResults(query) {
            const box = document.getElementById('fbClientResults');
            box.innerHTML = '<div class="text-muted small px-2">Searching…</div>';

            fetch("{{ route('customers.search') }}?q=" + encodeURIComponent(query))
                .then(r => r.json())
                .then(list => {
                    if (!list.length) {
                        box.innerHTML = '<div class="text-muted small px-2">No clients found.</div>';
                        return;
                    }
                    box.innerHTML = list.map(c => {
                        const safeName = String(c.name || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                        const safePhone = String(c.phone || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                        const safeInitials = String(c.initials || '?').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        return `
                        <div class="fb-client-row" data-id="${c.id}" data-name="${safeName}" data-phone="${safePhone}">
                            <div class="fb-client-avatar">${safeInitials}</div>
                            <div class="flex-grow-1 min-w-0 fb-client-view-mode">
                                <div class="fb-client-name">${safeName}</div>
                                <div class="fb-client-phone">${safePhone}</div>
                            </div>
                            <button type="button" class="fb-client-edit-btn ms-auto" title="Edit name" aria-label="Edit client name">✏️</button>
                            <div class="fb-client-edit-mode d-none flex-grow-1 d-flex align-items-center gap-1" onclick="event.stopPropagation()">
                                <input type="text" class="form-control form-control-sm fb-inline-name-input fb-client-edit-input" value="${safeName}" maxlength="120" placeholder="Client name">
                                <button type="button" class="fb-inline-save-btn fb-client-save-btn" title="Save name">✓</button>
                                <button type="button" class="fb-inline-save-btn fb-client-save-select-btn" title="Save &amp; Select">✓ Select</button>
                                <button type="button" class="fb-inline-cancel-btn fb-client-cancel-btn" title="Cancel">✕</button>
                            </div>
                        </div>
                        `;
                    }).join('');

                    box.querySelectorAll('.fb-client-row').forEach(row => {
                        const viewMode = row.querySelector('.fb-client-view-mode');
                        const editBtn = row.querySelector('.fb-client-edit-btn');
                        const editMode = row.querySelector('.fb-client-edit-mode');
                        const editInput = row.querySelector('.fb-client-edit-input');
                        const saveBtn = row.querySelector('.fb-client-save-btn');
                        const saveSelectBtn = row.querySelector('.fb-client-save-select-btn');
                        const cancelBtn = row.querySelector('.fb-client-cancel-btn');
                        const nameEl = row.querySelector('.fb-client-name');
                        const avatarEl = row.querySelector('.fb-client-avatar');

                        row.addEventListener('click', () => {
                            if (editMode && !editMode.classList.contains('d-none')) return;
                            fbSelectExistingClient({
                                id: row.dataset.id, name: row.dataset.name, phone: row.dataset.phone
                            });
                        });

                        function enterEdit(e) {
                            if (e) e.stopPropagation();
                            editInput.value = row.dataset.name || '';
                            viewMode.classList.add('d-none');
                            editBtn.classList.add('d-none');
                            editMode.classList.remove('d-none');
                            editInput.focus();
                            editInput.select();
                        }

                        function exitEdit(e) {
                            if (e) e.stopPropagation();
                            editMode.classList.add('d-none');
                            viewMode.classList.remove('d-none');
                            editBtn.classList.remove('d-none');
                        }

                        function performSave(e, selectAfterSave) {
                            if (e) e.stopPropagation();
                            const newName = editInput.value.trim();
                            if (newName.length < 2) {
                                if (typeof showCalToast === 'function') {
                                    showCalToast('Client name must be at least 2 characters.', 'error');
                                }
                                editInput.focus();
                                return;
                            }

                            saveBtn.disabled = true;
                            if (saveSelectBtn) saveSelectBtn.disabled = true;

                            window.quickRenameCalendarClient(row.dataset.id, newName)
                                .then(updated => {
                                    row.dataset.name = updated.name;
                                    nameEl.textContent = updated.name;
                                    avatarEl.textContent = updated.initials || (updated.name ? updated.name.charAt(0).toUpperCase() : '?');
                                    exitEdit();
                                    if (typeof showCalToast === 'function') {
                                        showCalToast('Client name updated');
                                    }
                                    if (selectAfterSave) {
                                        fbSelectExistingClient({
                                            id: updated.id,
                                            name: updated.name,
                                            phone: updated.phone
                                        });
                                    }
                                })
                                .catch(err => {
                                    if (typeof showCalToast === 'function') {
                                        showCalToast(err.message || 'Could not update client name.', 'error');
                                    }
                                })
                                .finally(() => {
                                    saveBtn.disabled = false;
                                    if (saveSelectBtn) saveSelectBtn.disabled = false;
                                });
                        }

                        editBtn.addEventListener('click', enterEdit);
                        cancelBtn.addEventListener('click', exitEdit);
                        saveBtn.addEventListener('click', e => performSave(e, false));
                        if (saveSelectBtn) {
                            saveSelectBtn.addEventListener('click', e => performSave(e, true));
                        }
                        editInput.addEventListener('keydown', function(e) {
                            e.stopPropagation();
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                performSave(e, false);
                            } else if (e.key === 'Escape') {
                                e.preventDefault();
                                exitEdit(e);
                            }
                        });
                    });
                });
        }

        document.getElementById('fbClientSearch').addEventListener('input', function() {
            clearTimeout(clientSearchTimer);
            const q = this.value;
            clientSearchTimer = setTimeout(() => fbRenderClientResults(q), 300);
        });

        /* ---------------- SERVICES & ITEM DISCOUNTS ---------------- */
        function fbRound2(v) {
            return Math.round((Number(v) || 0) * 100) / 100;
        }

        function fbItemCatalogPrice(item) {
            if (item && item.catalog_price !== undefined && item.catalog_price !== null) {
                return fbRound2(Math.max(0, Number(item.catalog_price) || 0));
            }
            if (item && item.pkg && item.pkg.price !== undefined) {
                return fbRound2(Math.max(0, Number(item.pkg.price) || 0));
            }
            return fbRound2(Math.max(0, Number(item?.original_price ?? item?.price) || 0));
        }

        function fbServiceDiscount(item) {
            const orig = Number(item?.original_price ?? fbItemCatalogPrice(item)) || 0;
            const net = Number(item?.price) || 0;
            return fbRound2(Math.max(0, orig - net));
        }

        function fbFormatCompactNum(n) {
            const r = fbRound2(n);
            return Number.isInteger(r) ? String(r) : r.toFixed(2).replace(/\.?0+$/, '');
        }

        function fbDiscountPillText(item) {
            const disc = fbServiceDiscount(item);
            if (disc <= 0) return '+ Discount';
            if (item.discount_type === 'percent' && Number(item.discount_value) > 0) {
                return `−${disc.toFixed(2)} QAR (${fbFormatCompactNum(item.discount_value)}%)`;
            }
            return `−${disc.toFixed(2)} QAR`;
        }

        function fbApplyDiscountToItem(item, mode, rawVal) {
            const base = fbItemCatalogPrice(item);
            const normMode = mode === 'percent' ? 'percent' : 'fixed';
            let val = parseFloat(rawVal);
            let clamped = false;
            if (isNaN(val) || val < 0) {
                val = 0;
            }
            if (normMode === 'percent') {
                if (val > 100) {
                    val = 100;
                    clamped = true;
                }
                val = fbRound2(val);
                const discAmt = fbRound2(Math.min(base, base * (val / 100)));
                item.catalog_price = base;
                item.original_price = base;
                item.discount_type = 'percent';
                item.discount_value = val;
                item.discount_amount = discAmt;
                item.price = fbRound2(Math.max(0, base - discAmt));
            } else {
                if (val > base) {
                    val = base;
                    clamped = true;
                }
                val = fbRound2(val);
                item.catalog_price = base;
                item.original_price = base;
                item.discount_type = 'fixed';
                item.discount_value = val;
                item.discount_amount = val;
                item.price = fbRound2(Math.max(0, base - val));
            }
            return clamped;
        }

        function fbApplyFinalPriceToItem(item, rawFinal) {
            const catalog = fbItemCatalogPrice(item);
            let entered = parseFloat(rawFinal);
            if (isNaN(entered) || entered < 0) {
                entered = 0;
            }
            entered = fbRound2(entered);
            item.catalog_price = catalog;

            if (entered < catalog) {
                const discAmt = fbRound2(catalog - entered);
                item.original_price = catalog;
                item.price = entered;
                item.discount_type = 'fixed';
                item.discount_value = discAmt;
                item.discount_amount = discAmt;
                item.discountOpen = true;
            } else {
                item.original_price = entered;
                item.price = entered;
                item.discount_type = 'fixed';
                item.discount_value = 0;
                item.discount_amount = 0;
            }
        }

        function fbResetItemDiscount(item) {
            const catalog = fbItemCatalogPrice(item);
            item.catalog_price = catalog;
            item.original_price = catalog;
            item.price = catalog;
            item.discount_type = 'fixed';
            item.discount_value = 0;
            item.discount_amount = 0;
            item.discount_reason = '';
            item.discountOpen = false;
        }

        function fbRenderDiscountEditorHtml(item, kind, keyAttr) {
            const disc = fbServiceDiscount(item);
            const isOpen = Boolean(item.discountOpen);
            const activeType = item.discount_type === 'percent' ? 'percent' : 'fixed';
            const valStr = Number(item.discount_value) > 0 ? fbFormatCompactNum(item.discount_value) : '';
            const safeReason = String(item.discount_reason || '').replace(/"/g, '&quot;');
            const placeholderVal = activeType === 'percent' ? '0%' : '0.00';

            return `
                <div class="fb-discount-row fb-discount-editor ${isOpen ? '' : 'd-none'}" data-disc-kind="${kind}" data-disc-key="${keyAttr}">
                    <div class="fb-disc-mode-toggle" role="group" aria-label="Discount type">
                        <button type="button" class="fb-disc-mode-btn ${activeType === 'fixed' ? 'is-active' : ''}" data-disc-kind="${kind}" data-disc-key="${keyAttr}" data-mode="fixed">QAR</button>
                        <button type="button" class="fb-disc-mode-btn ${activeType === 'percent' ? 'is-active' : ''}" data-disc-kind="${kind}" data-disc-key="${keyAttr}" data-mode="percent">%</button>
                    </div>
                    <input type="number" step="0.01" min="0" max="${activeType === 'percent' ? '100' : fbItemCatalogPrice(item).toFixed(2)}" inputmode="decimal"
                        class="fb-disc-val-input" data-disc-kind="${kind}" data-disc-key="${keyAttr}"
                        placeholder="${placeholderVal}" value="${valStr}" aria-label="Discount value">
                    <input type="text" maxlength="150" class="fb-discount-reason" data-disc-kind="${kind}" data-disc-key="${keyAttr}"
                        placeholder="Reason (optional)" value="${safeReason}">
                    <button type="button" class="fb-disc-clear-btn ${disc > 0 ? '' : 'd-none'}" data-disc-kind="${kind}" data-disc-key="${keyAttr}" title="Remove discount" aria-label="Remove discount">&times;</button>
                </div>
            `;
        }

        function fbSyncDiscountCardDom(cardEl, item, source) {
            if (!cardEl || !item) return;
            const disc = fbServiceDiscount(item);
            const isOpen = Boolean(item.discountOpen);
            const activeType = item.discount_type === 'percent' ? 'percent' : 'fixed';

            const strikeEl = cardEl.querySelector('.fb-orig-strike');
            if (strikeEl) {
                strikeEl.textContent = Number(item.original_price || fbItemCatalogPrice(item)).toFixed(2);
                strikeEl.classList.toggle('d-none', disc <= 0);
            }

            const priceInput = cardEl.querySelector('.price-input');
            if (priceInput && source !== 'price') {
                priceInput.value = Number(item.price || 0).toFixed(2);
            }

            const toggleBtn = cardEl.querySelector('.fb-discount-toggle-btn');
            if (toggleBtn) {
                toggleBtn.textContent = fbDiscountPillText(item);
                toggleBtn.classList.toggle('has-discount', disc > 0);
            }

            const editorEl = cardEl.querySelector('.fb-discount-editor');
            if (editorEl) {
                editorEl.classList.toggle('d-none', !isOpen);
            }

            cardEl.querySelectorAll('.fb-disc-mode-btn').forEach(btn => {
                btn.classList.toggle('is-active', btn.dataset.mode === activeType);
            });

            const valInput = cardEl.querySelector('.fb-disc-val-input');
            if (valInput) {
                valInput.max = activeType === 'percent' ? '100' : fbItemCatalogPrice(item).toFixed(2);
                valInput.placeholder = activeType === 'percent' ? '0%' : '0.00';
                if (source !== 'discount') {
                    valInput.value = Number(item.discount_value) > 0 ? fbFormatCompactNum(item.discount_value) : '';
                }
            }

            const clearBtn = cardEl.querySelector('.fb-disc-clear-btn');
            if (clearBtn) {
                clearBtn.classList.toggle('d-none', disc <= 0);
            }
        }

        function fbBindDiscountEvents(containerEl, kind, resolveItemFn) {
            if (!containerEl) return;

            containerEl.querySelectorAll(`.fb-discount-toggle-btn[data-disc-kind="${kind}"]`).forEach(btn => {
                btn.addEventListener('click', () => {
                    const item = resolveItemFn(btn.dataset.discKey);
                    if (!item) return;
                    const cardEl = btn.closest('.fb-svc-row');
                    item.discountOpen = !item.discountOpen;
                    fbSyncDiscountCardDom(cardEl, item, 'toggle');
                    if (item.discountOpen) {
                        cardEl?.querySelector('.fb-disc-val-input')?.focus();
                    }
                });
            });

            containerEl.querySelectorAll(`.fb-disc-mode-btn[data-disc-kind="${kind}"]`).forEach(btn => {
                btn.addEventListener('click', () => {
                    const item = resolveItemFn(btn.dataset.discKey);
                    if (!item) return;
                    const cardEl = btn.closest('.fb-svc-row');
                    const nextMode = btn.dataset.mode === 'percent' ? 'percent' : 'fixed';
                    const valInput = cardEl?.querySelector('.fb-disc-val-input');
                    const currentVal = valInput ? valInput.value : item.discount_value;
                    item.discountOpen = true;
                    fbApplyDiscountToItem(item, nextMode, currentVal);
                    fbSyncDiscountCardDom(cardEl, item, 'mode');
                    fbSyncHiddenAndTotals();
                    valInput?.focus();
                });
            });

            containerEl.querySelectorAll(`.fb-disc-val-input[data-disc-kind="${kind}"]`).forEach(input => {
                input.addEventListener('input', () => {
                    const item = resolveItemFn(input.dataset.discKey);
                    if (!item) return;
                    const cardEl = input.closest('.fb-svc-row');
                    item.discountOpen = true;
                    const clamped = fbApplyDiscountToItem(item, item.discount_type || 'fixed', input.value);
                    if (clamped) {
                        input.value = fbFormatCompactNum(item.discount_value);
                    }
                    fbSyncDiscountCardDom(cardEl, item, 'discount');
                    fbSyncHiddenAndTotals();
                });
                input.addEventListener('focus', () => input.select());
            });

            containerEl.querySelectorAll(`.fb-discount-reason[data-disc-kind="${kind}"]`).forEach(input => {
                input.addEventListener('input', () => {
                    const item = resolveItemFn(input.dataset.discKey);
                    if (!item) return;
                    item.discount_reason = input.value;
                    fbSyncHiddenAndTotals();
                });
            });

            containerEl.querySelectorAll(`.fb-disc-clear-btn[data-disc-kind="${kind}"]`).forEach(btn => {
                btn.addEventListener('click', () => {
                    const item = resolveItemFn(btn.dataset.discKey);
                    if (!item) return;
                    const cardEl = btn.closest('.fb-svc-row');
                    fbResetItemDiscount(item);
                    const reasonEl = cardEl?.querySelector('.fb-discount-reason');
                    if (reasonEl) reasonEl.value = '';
                    fbSyncDiscountCardDom(cardEl, item, 'clear');
                    fbSyncHiddenAndTotals();
                });
            });

            containerEl.querySelectorAll(`.price-input[data-disc-kind="${kind}"]`).forEach(input => {
                input.addEventListener('input', () => {
                    const item = resolveItemFn(input.dataset.discKey);
                    if (!item) return;
                    const cardEl = input.closest('.fb-svc-row');
                    fbApplyFinalPriceToItem(item, input.value);
                    fbSyncDiscountCardDom(cardEl, item, 'price');
                    fbSyncHiddenAndTotals();
                });
                input.addEventListener('blur', () => {
                    const item = resolveItemFn(input.dataset.discKey);
                    if (!item) return;
                    input.value = Number(item.price || 0).toFixed(2);
                });
                input.addEventListener('focus', () => input.select());
            });
        }

        function fbRenderServices(skipSync = false) {
            const list = document.getElementById('fbSvcList');
            const hasRealItems = selectedServices.length > 0 || selectedCombos.length > 0 || selectedRedemptions.length > 0;
            if (hasRealItems) {
                decideInSalon = false;
            }

            if (decideInSalon && !hasRealItems) {
                list.innerHTML = `
                    <div class="fb-svc-row" id="fbDecideInSalonCard">
                        <div class="fb-svc-row-main">
                            <div>
                                <div class="name">Decide in Salon</div>
                                <div class="meta">Client will choose services on arrival &middot; 60 min hold</div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fb-decide-tag">0.00 QAR</span>
                                <button type="button" class="remove-btn" id="fbDecideInSalonRemove" title="Remove Decide in Salon"><i class="bx bx-x"></i></button>
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('fbDecideInSalonRemove').addEventListener('click', () => {
                    decideInSalon = false;
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
                if (!skipSync) {
                    fbUpdateTeamMemberVisibility();
                    fbSyncHiddenAndTotals();
                    loadAvailableStaff();
                }
                return;
            }

            const branch = fbNormalizeBranch(document.getElementById('bookBranch').value) || fbResolveActiveCalendarBranch();
            const daySnap = fbCurrentDaySnapshot();
            const { timeline, byUid } = fbComputeUnifiedTimeline();

            list.innerHTML = selectedServices.map((s, i) => {
                const discount = fbServiceDiscount(s);
                const sl = byUid.get(`svc:${i}`);
                const slicePos = sl ? timeline.slices.indexOf(sl) : 0;
                const priorSlices = slicePos > 0 ? timeline.slices.slice(0, slicePos) : [];
                const meta = window.LaleenSliceEngine
                    ? window.LaleenSliceEngine.buildSpecialistSelectMeta({
                        branch,
                        serviceId: s.id || null,
                        serviceName: s.name,
                        startMin: sl ? sl.startMin : null,
                        endMin: sl ? sl.endMin : null,
                        selectedStaffId: s.staff_id || '',
                        daySnapshot: daySnap,
                        excludeApptId: editingAppointmentId || null,
                        priorSlices,
                        allStaffs: ALL_STAFFS
                    })
                    : { html: '', unmappedFallback: false, hasConflict: false, needsSelection: false };

                const reorderBtns = selectedServices.length > 1 ? `
                    <div class="laleen-reorder-group me-1">
                        <button type="button" class="laleen-reorder-btn fb-svc-move-btn" data-idx="${i}" data-dir="-1" ${i === 0 ? 'disabled' : ''} title="Move earlier">↑</button>
                        <button type="button" class="laleen-reorder-btn fb-svc-move-btn" data-idx="${i}" data-dir="1" ${i === selectedServices.length - 1 ? 'disabled' : ''} title="Move later">↓</button>
                    </div>` : '';

                const parallelBtn = (sl && sl.can_parallel) ? `
                    <button type="button" class="laleen-parallel-pill fb-svc-parallel-btn ${sl.is_parallel ? 'is-active' : ''}" data-idx="${i}">
                        ${sl.is_parallel ? '⇉ Same time' : 'Same time'}
                    </button>` : '';

                return `
                <div class="fb-svc-row" data-svc-idx="${i}">
                    <div class="fb-svc-row-main">
                        <div class="d-flex align-items-center gap-1 min-w-0">
                            ${reorderBtns}
                            <div class="min-w-0">
                                <div class="name">${fbEscHtml(s.name)}</div>
                                <div class="laleen-slice-sub mt-1">
                                    <span class="laleen-time-pill">${fbEscHtml(sl ? sl.timeBadge : `${s.duration}m`)}</span>
                                    ${meta.unmappedFallback ? '<span class="laleen-unmapped-hint">Unmapped skill</span>' : ''}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <span class="fb-orig-strike ${discount > 0 ? '' : 'd-none'}">${Number(s.original_price || fbItemCatalogPrice(s)).toFixed(2)}</span>
                            <div class="fb-price-edit">
                                <span class="currency">QAR</span>
                                <input type="number" step="0.01" min="0" inputmode="decimal" class="price-input" data-idx="${i}" data-disc-kind="svc" data-disc-key="${i}" value="${Number(s.price || 0).toFixed(2)}" aria-label="Final price for ${fbEscHtml(s.name)}">
                            </div>
                            <button type="button" class="remove-btn fb-svc-remove-btn" data-idx="${i}"><i class="bx bx-x"></i></button>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between gap-2 pt-1 flex-wrap">
                        <button type="button" class="fb-discount-toggle-btn ${discount > 0 ? 'has-discount' : ''}" data-disc-kind="svc" data-disc-key="${i}">
                            ${fbDiscountPillText(s)}
                        </button>
                        <div class="d-flex align-items-center gap-2 ms-auto">
                            ${parallelBtn}
                            <select class="form-select form-select-sm laleen-specialist-pill fb-svc-staff-select ${meta.needsSelection ? 'is-needs-specialist' : ''} ${meta.isOvertime ? 'is-overtime' : ''} ${meta.hasConflict ? 'is-conflict' : ''}" data-idx="${i}">
                                ${meta.html}
                            </select>
                        </div>
                    </div>
                    ${fbRenderDiscountEditorHtml(s, 'svc', i)}
                    ${window.LaleenSliceEngine ? window.LaleenSliceEngine.renderOvertimeAlert(meta) : ''}
                </div>
            `;
            }).join('');

            list.querySelectorAll('.fb-svc-remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectedServices.splice(Number(btn.dataset.idx), 1);
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
            });

            list.querySelectorAll('.fb-svc-move-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = Number(btn.dataset.idx);
                    const nextIdx = idx + Number(btn.dataset.dir);
                    if (idx < 0 || nextIdx < 0 || nextIdx >= selectedServices.length) return;
                    [selectedServices[idx], selectedServices[nextIdx]] = [selectedServices[nextIdx], selectedServices[idx]];
                    fbRenderAllListsWithoutRecursion();
                });
            });

            list.querySelectorAll('.fb-svc-parallel-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = Number(btn.dataset.idx);
                    selectedServices[idx].is_parallel = !selectedServices[idx].is_parallel;
                    fbRenderAllListsWithoutRecursion();
                });
            });

            list.querySelectorAll('.fb-svc-staff-select').forEach(sel => {
                sel.addEventListener('change', () => {
                    const idx = Number(sel.dataset.idx);
                    selectedServices[idx].staff_id = sel.value || '';
                    selectedServices[idx].manualStaff = true;
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
            });

            fbBindDiscountEvents(list, 'svc', key => selectedServices[Number(key)]);

            if (!skipSync) {
                fbUpdateTeamMemberVisibility();
                fbSyncHiddenAndTotals();
                loadAvailableStaff();
            }
        }

        function fbSyncHiddenAndTotals() {
            const hasRealItems = selectedServices.length > 0
                || selectedCombos.length > 0
                || selectedRedemptions.length > 0
                || selectedFreebiePackages.length > 0
                || selectedFreebieRedemptions.length > 0;
            if (hasRealItems) {
                decideInSalon = false;
            }

            const decidePill = document.getElementById('fbDecideSalonPill');
            if (decidePill) {
                decidePill.classList.toggle('d-none', hasRealItems || decideInSalon);
            }

            document.getElementById('bookDecideInSalonHidden').value =
                (decideInSalon && !hasRealItems) ? '1' : '0';

            const { timeline, byUid } = fbComputeUnifiedTimeline();

            const hidden = document.getElementById('svcHiddenInputs');
            hidden.innerHTML = selectedServices.map((s, idx) => {
                const sl = byUid.get(`svc:${idx}`);
                const sortOrd = sl ? sl.sort_order : idx;
                const isPar = sl && sl.is_parallel ? '1' : '0';
                const discAmt = fbServiceDiscount(s);
                const discType = discAmt > 0 ? (s.discount_type === 'percent' ? 'percent' : 'fixed') : '';
                const discVal = discAmt > 0 ? Number(s.discount_value || discAmt).toFixed(2) : '0';
                return `
                <input type="hidden" name="service_name[]" value="${s.name.replace(/"/g,'&quot;')}">
                <input type="hidden" name="service_price[]" value="${Number(s.price || 0).toFixed(2)}">
                <input type="hidden" name="service_discount_type[]" value="${discType}">
                <input type="hidden" name="service_discount_value[]" value="${discVal}">
                <input type="hidden" name="service_discount_reason[]" value="${(s.discount_reason || '').replace(/"/g,'&quot;')}">
                <input type="hidden" name="service_staff[]" value="${s.staff_id || ''}">
                <input type="hidden" name="service_sort_order[]" value="${sortOrd}">
                <input type="hidden" name="service_is_parallel[]" value="${isPar}">
            `;
            }).join('');

            const comboHidden = document.getElementById('fbComboHiddenInputs');
            comboHidden.innerHTML = selectedCombos.map(row => {
                const staffInputs = Object.entries(row.serviceStaff || {})
                    .filter(([, staffId]) => staffId)
                    .map(([sid, staffId]) => `<input type="hidden" name="packages[${row.id}][service_staff][${sid}]" value="${staffId}">`)
                    .join('');
                const sortAndParallelInputs = (row.immediateIds || []).map((sid, sIdx) => {
                    const sl = byUid.get(`combo:${row.id}:${sid}`);
                    const sortOrd = sl ? sl.sort_order : sIdx;
                    const isPar = sl && sl.is_parallel ? '1' : '0';
                    return `
                        <input type="hidden" name="packages[${row.id}][service_sort_order][${sid}]" value="${sortOrd}">
                        <input type="hidden" name="packages[${row.id}][service_parallel][${sid}]" value="${isPar}">
                    `;
                }).join('');
                return `
                <input type="hidden" name="packages[${row.id}][combo_id]" value="${row.combo.id}">
                ${row.immediateIds.map(id => `<input type="hidden" name="packages[${row.id}][immediate_service_ids][]" value="${id}">`).join('')}
                ${(row.pendingIds || []).map(id => `<input type="hidden" name="packages[${row.id}][pending_service_ids][]" value="${id}">`).join('')}
                ${staffInputs}
                ${sortAndParallelInputs}
            `;
            }).join('');

            const redeemHidden = document.getElementById('fbRedeemHiddenInputs');
            redeemHidden.innerHTML = selectedRedemptions.map((r, rIdx) => {
                const assignedStaff = redeemStaffMap[r.id] || '';
                const sl = byUid.get(`redeem:${r.id}`);
                const sortOrd = sl ? sl.sort_order : rIdx;
                const isPar = sl && sl.is_parallel ? '1' : '0';
                return `
                <input type="hidden" name="redeem_service_ids[]" value="${r.id}">
                ${assignedStaff ? `<input type="hidden" name="redeem_service_staff[${r.id}]" value="${assignedStaff}">` : ''}
                <input type="hidden" name="redeem_service_sort_order[${r.id}]" value="${sortOrd}">
                <input type="hidden" name="redeem_service_parallel[${r.id}]" value="${isPar}">
            `;
            }).join('');

            const freebieHidden = document.getElementById('fbFreebieHiddenInputs');
            if (freebieHidden) {
                freebieHidden.innerHTML = selectedFreebiePackages.map((fpRow, fpIdx) => {
                    const anchorSl = byUid.get(`freebie_anchor:${fpRow.rowId}`);
                    const anchorSort = anchorSl ? anchorSl.sort_order : 0;
                    const anchorPar = anchorSl && anchorSl.is_parallel ? '1' : '0';
                    const fpDiscAmt = fbServiceDiscount(fpRow);
                    const fpDiscType = fpDiscAmt > 0 ? (fpRow.discount_type === 'percent' ? 'percent' : 'fixed') : '';
                    const fpDiscVal = fpDiscAmt > 0 ? Number(fpRow.discount_value || fpDiscAmt).toFixed(2) : '0';
                    const fpNetPrice = Number(fpRow.price !== undefined ? fpRow.price : fpRow.pkg.price).toFixed(2);
                    const slotInputs = (fpRow.slots || []).map((slotObj, sIdx) => {
                        const mode = slotObj.serviceId ? 'same_day' : 'save_for_later';
                        const sl = slotObj.serviceId ? byUid.get(`freebie_slot:${fpRow.rowId}:${slotObj.slotNumber}`) : null;
                        const sortOrd = sl ? sl.sort_order : (sIdx + 1);
                        const isPar = sl && sl.is_parallel ? '1' : '0';
                        return `
                            <input type="hidden" name="freebie_packages[${fpIdx}][slots][${sIdx}][mode]" value="${mode}">
                            <input type="hidden" name="freebie_packages[${fpIdx}][slots][${sIdx}][service_id]" value="${slotObj.serviceId || ''}">
                            <input type="hidden" name="freebie_packages[${fpIdx}][slots][${sIdx}][staff_id]" value="${slotObj.staffId || ''}">
                            <input type="hidden" name="freebie_packages[${fpIdx}][slots][${sIdx}][sort_order]" value="${sortOrd}">
                            <input type="hidden" name="freebie_packages[${fpIdx}][slots][${sIdx}][is_parallel]" value="${isPar}">
                        `;
                    }).join('');
                    return `
                        <input type="hidden" name="freebie_packages[${fpIdx}][package_id]" value="${fpRow.pkg.id}">
                        <input type="hidden" name="freebie_packages[${fpIdx}][anchor_staff_id]" value="${fpRow.anchorStaffId || ''}">
                        <input type="hidden" name="freebie_packages[${fpIdx}][anchor_sort_order]" value="${anchorSort}">
                        <input type="hidden" name="freebie_packages[${fpIdx}][anchor_is_parallel]" value="${anchorPar}">
                        <input type="hidden" name="freebie_packages[${fpIdx}][price]" value="${fpNetPrice}">
                        <input type="hidden" name="freebie_packages[${fpIdx}][discount_type]" value="${fpDiscType}">
                        <input type="hidden" name="freebie_packages[${fpIdx}][discount_value]" value="${fpDiscVal}">
                        <input type="hidden" name="freebie_packages[${fpIdx}][discount_reason]" value="${(fpRow.discount_reason || '').replace(/"/g,'&quot;')}">
                        ${slotInputs}
                    `;
                }).join('');
            }

            const freebieRedeemHidden = document.getElementById('fbFreebieRedeemHiddenInputs');
            if (freebieRedeemHidden) {
                freebieRedeemHidden.innerHTML = selectedFreebieRedemptions.map((fr, frIdx) => {
                    const sl = byUid.get(`freebie_redeem:${fr.credit_id}`);
                    const sortOrd = sl ? sl.sort_order : frIdx;
                    const isPar = sl && sl.is_parallel ? '1' : '0';
                    return `
                        <input type="hidden" name="redeem_freebies[${frIdx}][credit_id]" value="${fr.credit_id}">
                        <input type="hidden" name="redeem_freebies[${frIdx}][service_id]" value="${fr.service_id || ''}">
                        <input type="hidden" name="redeem_freebies[${frIdx}][staff_id]" value="${fr.staff_id || ''}">
                        <input type="hidden" name="redeem_freebies[${frIdx}][sort_order]" value="${sortOrd}">
                        <input type="hidden" name="redeem_freebies[${frIdx}][is_parallel]" value="${isPar}">
                    `;
                }).join('');
            }

            // Sync hidden Team Member select to first Today slice's specialist when per-service pickers are active
            if (timeline.slices.length > 0) {
                const firstStaff = timeline.slices[0].staff_id || '';
                const staffSelect = document.getElementById('bookStaffSelect');
                if (staffSelect) {
                    if (firstStaff && [...staffSelect.options].some(o => String(o.value) === String(firstStaff))) {
                        staffSelect.value = String(firstStaff);
                    } else if (!firstStaff) {
                        staffSelect.value = '';
                    }
                }
            }

            const comboPackagesPrice = selectedCombos.reduce((sum, r) => sum + (Number(r.combo.price) || 0), 0);
            const freebiePackagesNet = selectedFreebiePackages.reduce((sum, fp) => sum + (Number(fp.price !== undefined ? fp.price : fp.pkg.price) || 0), 0);
            const freebiePackagesOrig = selectedFreebiePackages.reduce((sum, fp) => sum + (Number(fp.original_price !== undefined ? fp.original_price : fp.pkg.price) || 0), 0);
            const standaloneNet = selectedServices.reduce((sum, s) => sum + (Number(s.price) || 0), 0);
            const standaloneOrig = selectedServices.reduce((sum, s) => sum + (Number(s.original_price !== undefined ? s.original_price : s.price) || 0), 0);

            const totalPrice = fbRound2(standaloneNet + comboPackagesPrice + freebiePackagesNet);
            const subtotalBeforeDiscount = fbRound2(standaloneOrig + comboPackagesPrice + freebiePackagesOrig);
            const totalDiscount = fbRound2(
                selectedServices.reduce((sum, s) => sum + fbServiceDiscount(s), 0)
                + selectedFreebiePackages.reduce((sum, fp) => sum + fbServiceDiscount(fp), 0)
            );

            let totalMin = timeline.spanMinutes || 0;
            if (totalMin === 0 && selectedCombos.length > 0) {
                totalMin = Math.max(...selectedCombos.map(r => Number(r.combo.default_duration) || 60));
            } else if (totalMin === 0 && decideInSalon && !hasRealItems) {
                totalMin = 60;
            }

            document.getElementById('bookPriceHidden').value = totalPrice.toFixed(2);
            document.getElementById('fbFooterPrice').textContent = totalPrice.toFixed(2) + ' QAR';

            const discountEl = document.getElementById('fbFooterDiscount');
            discountEl.classList.toggle('d-none', totalDiscount <= 0);
            discountEl.textContent = `Subtotal ${subtotalBeforeDiscount.toFixed(2)} · Discount −${totalDiscount.toFixed(2)}`;

            const hrs = Math.floor(totalMin / 60);
            const mins = totalMin % 60;
            document.getElementById('fbFooterDuration').textContent =
                totalMin ? (hrs ? (mins ? `${hrs}h ${mins}min` : `${hrs}h`) : `${mins}min`) : '0min';

            fbClearBookingError();
        }

        /* ---------------- COMBO PACKAGES (COMPACT CARD + NESTED SUB-VIEW) ---------------- */
        function fbEscHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function fbComboImmediateDuration(row) {
            return (row.combo.services || [])
                .filter(s => (row.immediateIds || []).includes(s.id))
                .reduce((sum, s) => sum + (Number(s.duration) || 0), 0);
        }

        function fbComboCardHtml(row) {
            const combo = row.combo;
            const immediateIds = row.immediateIds || [];
            const pendingIds = row.pendingIds || [];
            const pickedCount = immediateIds.length + pendingIds.length;
            const qty = Number(combo.quantity_included) || 0;
            const branch = fbNormalizeBranch(document.getElementById('bookBranch').value) || fbResolveActiveCalendarBranch();
            const daySnap = fbCurrentDaySnapshot();
            const { timeline, byUid } = fbComputeUnifiedTimeline();
            const poolById = new Map((combo.services || []).map(cs => [Number(cs.id), cs]));

            const swapComboHtml = ALL_COMBOS.length > 1 ? `
                <div class="fb-combo-swap-row" onclick="event.stopPropagation()">
                    <span class="fb-combo-swap-label"><i class="bx bx-transfer-alt"></i> Change Combo</span>
                    <select class="form-select form-select-sm fb-combo-swap-select" data-row="${row.id}" aria-label="Change Combo">
                        ${ALL_COMBOS.map(c => `<option value="${c.id}" ${Number(c.id) === Number(combo.id) ? 'selected' : ''}>${fbEscHtml(c.name)} (${Number(c.price).toFixed(0)} QAR)</option>`).join('')}
                    </select>
                </div>` : '';

            if (pickedCount === 0) {
                return `
                    <div class="fb-svc-row fb-combo-card" data-row-id="${row.id}" role="button" tabindex="0" aria-label="Configure ${fbEscHtml(combo.name)}">
                        <div class="fb-svc-row-main">
                            <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                                <span class="fb-combo-header-pill">Combo</span>
                                <span class="name">${fbEscHtml(combo.name)}</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="fb-combo-price-lock">${Number(combo.price).toFixed(2)} QAR</span>
                                <button type="button" class="remove-btn fb-combo-remove-btn" data-row="${row.id}" title="Remove combo"><i class="bx bx-x"></i></button>
                            </div>
                        </div>
                        ${swapComboHtml}
                        <div class="fb-combo-trigger-row">
                            <span>0 / ${qty} Selected &middot; Decide in Salon &mdash; Click to choose services</span>
                            <span class="fb-combo-edit-cta">&rsaquo;</span>
                        </div>
                    </div>`;
            }

            const todayRowsHtml = immediateIds.map((sid, idx) => {
                const cs = poolById.get(Number(sid));
                if (!cs) return '';
                const sl = byUid.get(`combo:${row.id}:${sid}`);
                const slicePos = sl ? timeline.slices.indexOf(sl) : 0;
                const priorSlices = slicePos > 0 ? timeline.slices.slice(0, slicePos) : [];
                const meta = window.LaleenSliceEngine
                    ? window.LaleenSliceEngine.buildSpecialistSelectMeta({
                        branch,
                        serviceId: Number(sid),
                        serviceName: cs.name,
                        startMin: sl ? sl.startMin : null,
                        endMin: sl ? sl.endMin : null,
                        selectedStaffId: (row.serviceStaff && row.serviceStaff[sid]) ? row.serviceStaff[sid] : '',
                        daySnapshot: daySnap,
                        excludeApptId: editingAppointmentId || null,
                        priorSlices,
                        allStaffs: ALL_STAFFS
                    })
                    : { html: '', unmappedFallback: false, hasConflict: false, needsSelection: false };

                const reorderBtns = immediateIds.length > 1 ? `
                    <div class="laleen-reorder-group">
                        <button type="button" class="laleen-reorder-btn fb-combo-inline-move" data-row="${row.id}" data-svc="${sid}" data-dir="-1" ${idx === 0 ? 'disabled' : ''} title="Move earlier">↑</button>
                        <button type="button" class="laleen-reorder-btn fb-combo-inline-move" data-row="${row.id}" data-svc="${sid}" data-dir="1" ${idx === immediateIds.length - 1 ? 'disabled' : ''} title="Move later">↓</button>
                    </div>` : '';

                const parallelBtn = (sl && sl.can_parallel) ? `
                    <button type="button" class="laleen-parallel-pill fb-combo-inline-parallel ${sl.is_parallel ? 'is-active' : ''}" data-row="${row.id}" data-svc="${sid}">
                        ${sl.is_parallel ? '⇉ Same time' : 'Same time'}
                    </button>` : '';

                const otAlertHtml = window.LaleenSliceEngine ? window.LaleenSliceEngine.renderOvertimeAlert(meta) : '';

                return `
                    <div class="laleen-slice-row flex-column align-items-stretch" onclick="event.stopPropagation()">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="laleen-slice-left">
                                ${reorderBtns}
                                <div class="laleen-slice-title-wrap">
                                    <span class="laleen-slice-title">${fbEscHtml(cs.name)}</span>
                                    <span class="laleen-slice-sub">
                                        <span class="laleen-time-pill">${fbEscHtml(sl ? sl.timeBadge : `${cs.duration}m`)}</span>
                                        ${meta.unmappedFallback ? '<span class="laleen-unmapped-hint">Unmapped skill</span>' : ''}
                                    </span>
                                </div>
                            </div>
                            <div class="laleen-slice-right">
                                ${parallelBtn}
                                <select class="form-select form-select-sm laleen-specialist-pill fb-combo-staff-select ${meta.needsSelection ? 'is-needs-specialist' : ''} ${meta.isOvertime ? 'is-overtime' : ''} ${meta.hasConflict ? 'is-conflict' : ''}"
                                    data-row="${row.id}" data-svc="${sid}">
                                    ${meta.html}
                                </select>
                            </div>
                        </div>
                        ${otAlertHtml}
                    </div>`;
            }).join('');

            const pendingNames = pendingIds.map(id => poolById.get(Number(id))?.name).filter(Boolean);
            const pendingHtml = pendingNames.length > 0
                ? `<div class="laleen-pending-summary" onclick="event.stopPropagation()"><span>Pending next visit: ${fbEscHtml(pendingNames.join(', '))} (0 min today)</span></div>`
                : '';

            return `
                <div class="fb-svc-row fb-combo-card" data-row-id="${row.id}">
                    <div class="fb-svc-row-main">
                        <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                            <span class="fb-combo-header-pill">Combo</span>
                            <span class="name">${fbEscHtml(combo.name)}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-link btn-sm p-0 fb-combo-edit-cta text-decoration-none fb-combo-open-cfg-btn" data-row="${row.id}">
                                Edit (${pickedCount}/${qty}) &rsaquo;
                            </button>
                            <span class="fb-combo-price-lock">${Number(combo.price).toFixed(2)} QAR</span>
                            <button type="button" class="remove-btn fb-combo-remove-btn" data-row="${row.id}" title="Remove combo"><i class="bx bx-x"></i></button>
                        </div>
                    </div>
                    ${swapComboHtml}
                    ${todayRowsHtml ? `<div class="laleen-slice-track">${todayRowsHtml}</div>` : ''}
                    ${pendingHtml}
                </div>`;
        }

        function fbSwapComboRow(rowId, newComboId) {
            const row = selectedCombos.find(r => Number(r.id) === Number(rowId));
            const newCombo = ALL_COMBOS.find(c => Number(c.id) === Number(newComboId));
            if (!row || !newCombo || Number(row.combo.id) === Number(newCombo.id)) return;

            const qty = Math.max(0, Number(newCombo.quantity_included) || 0);
            const pool = Array.isArray(newCombo.services) ? newCombo.services : [];
            const defaultImmediateIds = pool.slice(0, qty > 0 ? qty : pool.length).map(s => Number(s.id));

            row.combo = newCombo;
            row.immediateIds = defaultImmediateIds;
            row.pendingIds = [];
            row.serviceStaff = {};
            row.serviceParallel = {};

            fbClearBookingError();
            fbAutoAssignUnpickedTodaySlices();
            fbRenderAllListsWithoutRecursion();
            loadAvailableStaff();
        }

        function fbRenderCombos(skipSync = false) {
            const container = document.getElementById('fbComboList');
            container.innerHTML = selectedCombos.map(fbComboCardHtml).join('');

            container.querySelectorAll('.fb-combo-swap-select').forEach(sel => {
                sel.addEventListener('click', (e) => e.stopPropagation());
                sel.addEventListener('change', (e) => {
                    e.stopPropagation();
                    fbSwapComboRow(Number(sel.dataset.row), Number(sel.value));
                });
            });

            container.querySelectorAll('.fb-combo-remove-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    selectedCombos = selectedCombos.filter(r => r.id != btn.dataset.row);
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
            });

            container.querySelectorAll('.fb-combo-open-cfg-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    fbOpenComboConfigForRow(Number(btn.dataset.row));
                });
            });

            container.querySelectorAll('.fb-combo-card[role="button"]').forEach(card => {
                card.addEventListener('click', (e) => {
                    if (e.target.closest('.fb-combo-remove-btn')) return;
                    fbOpenComboConfigForRow(Number(card.dataset.rowId));
                });
                card.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        if (e.target.closest('.fb-combo-remove-btn')) return;
                        e.preventDefault();
                        fbOpenComboConfigForRow(Number(card.dataset.rowId));
                    }
                });
            });

            container.querySelectorAll('.fb-combo-inline-move').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const row = selectedCombos.find(r => Number(r.id) === Number(btn.dataset.row));
                    if (!row) return;
                    const sid = Number(btn.dataset.svc);
                    const idx = row.immediateIds.indexOf(sid);
                    const nextIdx = idx + Number(btn.dataset.dir);
                    if (idx < 0 || nextIdx < 0 || nextIdx >= row.immediateIds.length) return;
                    [row.immediateIds[idx], row.immediateIds[nextIdx]] = [row.immediateIds[nextIdx], row.immediateIds[idx]];
                    fbRenderAllListsWithoutRecursion();
                });
            });

            container.querySelectorAll('.fb-combo-inline-parallel').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const row = selectedCombos.find(r => Number(r.id) === Number(btn.dataset.row));
                    if (!row) return;
                    const sid = Number(btn.dataset.svc);
                    row.serviceParallel = row.serviceParallel || {};
                    row.serviceParallel[sid] = !row.serviceParallel[sid];
                    fbRenderAllListsWithoutRecursion();
                });
            });

            container.querySelectorAll('.fb-combo-staff-select[data-row]').forEach(sel => {
                sel.addEventListener('change', (e) => {
                    e.stopPropagation();
                    const row = selectedCombos.find(r => Number(r.id) === Number(sel.dataset.row));
                    if (!row) return;
                    const sid = Number(sel.dataset.svc);
                    row.serviceStaff = row.serviceStaff || {};
                    if (sel.value) {
                        row.serviceStaff[sid] = sel.value;
                    } else {
                        delete row.serviceStaff[sid];
                    }
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
            });

            if (!skipSync) {
                fbUpdateTeamMemberVisibility();
                fbSyncHiddenAndTotals();
            }
        }

        function fbComputeDraftComboTimeline(draft) {
            const baseTime = fbCurrentBaseTimeHHMM();
            const poolById = new Map((draft.combo.services || []).map(cs => [Number(cs.id), cs]));
            const items = (draft.immediateIds || []).map((sid, idx) => {
                const cs = poolById.get(Number(sid));
                return {
                    service_id: Number(sid),
                    name: cs ? cs.name : 'Service',
                    duration: cs ? (Number(cs.duration) || 30) : 30,
                    staff_id: (draft.serviceStaff && draft.serviceStaff[sid]) ? String(draft.serviceStaff[sid]) : '',
                    sort_order: idx,
                    is_parallel: Boolean(draft.serviceParallel && draft.serviceParallel[sid])
                };
            });
            if (!window.LaleenSliceEngine) {
                return { slices: items, spanMinutes: items.reduce((a, b) => a + b.duration, 0) };
            }
            const tl = window.LaleenSliceEngine.computeTimeline(baseTime, items);
            tl.slices.forEach(sl => {
                draft.serviceParallel = draft.serviceParallel || {};
                draft.serviceParallel[sl.service_id] = Boolean(sl.is_parallel);
            });
            return tl;
        }

        function fbOpenComboConfigForNew(combo) {
            fbActiveComboDraft = {
                isNew: true,
                rowId: null,
                combo: combo,
                immediateIds: [],
                pendingIds: [],
                serviceStaff: {},
                serviceParallel: {},
                serviceStaffOptions: {},
            };
            const searchInput = document.getElementById('fbComboServiceSearch');
            if (searchInput) searchInput.value = '';
            fbShowPanel('comboconfig');
            fbRenderComboConfigPanel();
        }

        function fbOpenComboConfigForRow(rowId) {
            const existing = selectedCombos.find(r => Number(r.id) === Number(rowId));
            if (!existing) return;
            fbActiveComboDraft = {
                isNew: false,
                rowId: existing.id,
                combo: existing.combo,
                immediateIds: [...(existing.immediateIds || [])],
                pendingIds: [...(existing.pendingIds || [])],
                serviceStaff: Object.assign({}, existing.serviceStaff || {}),
                serviceParallel: Object.assign({}, existing.serviceParallel || {}),
                serviceStaffOptions: Object.assign({}, existing.serviceStaffOptions || {}),
            };
            const searchInput = document.getElementById('fbComboServiceSearch');
            if (searchInput) searchInput.value = '';
            fbShowPanel('comboconfig');
            fbRenderComboConfigPanel();
        }

        function fbCommitActiveComboDraft(forceDecideInSalon = false) {
            if (!fbActiveComboDraft) return;
            const draft = fbActiveComboDraft;
            if (forceDecideInSalon) {
                draft.immediateIds = [];
                draft.pendingIds = [];
                draft.serviceStaff = {};
                draft.serviceParallel = {};
                draft.serviceStaffOptions = {};
            }

            if (draft.isNew) {
                selectedCombos.push({
                    id: ++comboRowSeq,
                    combo: draft.combo,
                    immediateIds: [...draft.immediateIds],
                    pendingIds: [...draft.pendingIds],
                    serviceStaff: Object.assign({}, draft.serviceStaff),
                    serviceParallel: Object.assign({}, draft.serviceParallel || {}),
                    serviceStaffOptions: Object.assign({}, draft.serviceStaffOptions),
                });
            } else {
                const target = selectedCombos.find(r => Number(r.id) === Number(draft.rowId));
                if (target) {
                    target.immediateIds = [...draft.immediateIds];
                    target.pendingIds = [...draft.pendingIds];
                    target.serviceStaff = Object.assign({}, draft.serviceStaff);
                    target.serviceParallel = Object.assign({}, draft.serviceParallel || {});
                    target.serviceStaffOptions = Object.assign({}, draft.serviceStaffOptions);
                }
            }

            fbActiveComboDraft = null;
            decideInSalon = false;
            fbAutoAssignUnpickedTodaySlices();
            fbRenderAllListsWithoutRecursion();
            loadAvailableStaff();
            fbShowPanel('main');
        }

        function fbRenderComboConfigPanel() {
            const draft = fbActiveComboDraft;
            if (!draft) return;

            const combo = draft.combo;
            const qty = Number(combo.quantity_included) || 0;
            const selectedCount = draft.immediateIds.length + draft.pendingIds.length;
            const capReached = selectedCount >= qty;
            const defaultHold = Number(combo.default_duration) || 60;
            const draftTl = fbComputeDraftComboTimeline(draft);
            const sliceBySvcId = new Map(draftTl.slices.map(sl => [Number(sl.service_id), sl]));
            const todaySpanText = window.LaleenSliceEngine
                ? window.LaleenSliceEngine.formatSpanDuration(draftTl.spanMinutes)
                : `${draftTl.spanMinutes} min`;

            document.getElementById('fbComboConfigTitle').textContent = combo.name;
            const counterEl = document.getElementById('fbComboConfigCounter');
            counterEl.textContent = `${selectedCount} / ${qty} Selected`;
            counterEl.classList.toggle('is-full', capReached && qty > 0);

            const capHintEl = document.getElementById('fbComboCapHint');
            if (capReached && qty > 0) {
                capHintEl.textContent = `Max ${qty} of ${(combo.services || []).length} services selected — deselect one to swap.`;
                capHintEl.classList.remove('d-none');
            } else {
                capHintEl.textContent = '';
                capHintEl.classList.add('d-none');
            }

            const holdLabelEl = document.getElementById('fbComboFooterHoldLabel');
            if (holdLabelEl) {
                holdLabelEl.textContent = draft.immediateIds.length > 0
                    ? `Today's visit: ${todaySpanText}`
                    : `Calendar hold: ${defaultHold} min · Decide in salon`;
            }
            const priceLabelEl = document.getElementById('fbComboFooterPriceLabel');
            if (priceLabelEl) {
                priceLabelEl.textContent = `${Number(combo.price).toFixed(2)} QAR locked`;
            }

            const query = (document.getElementById('fbComboServiceSearch')?.value || '').trim().toLowerCase();
            const orderedServices = [
                ...draft.immediateIds.map(id => (combo.services || []).find(s => Number(s.id) === Number(id))).filter(Boolean),
                ...(combo.services || []).filter(s => !draft.immediateIds.includes(Number(s.id)))
            ];
            const filteredServices = orderedServices.filter(s => !query || s.name.toLowerCase().includes(query));
            const listEl = document.getElementById('fbComboConfigServiceList');

            if (!filteredServices.length) {
                listEl.innerHTML = '<div class="text-muted small py-3 text-center">No services in this combo match your search.</div>';
                return;
            }

            const branch = fbNormalizeBranch(document.getElementById('bookBranch').value) || fbResolveActiveCalendarBranch();
            const daySnap = fbCurrentDaySnapshot();

            listEl.innerHTML = filteredServices.map(s => {
                const sid = Number(s.id);
                const isToday = draft.immediateIds.includes(sid);
                const isPending = !isToday && draft.pendingIds.includes(sid);
                const isSelected = isToday || isPending;
                const isRowDimmed = !isSelected && capReached;
                const disablePills = !isSelected && capReached;

                let reorderHtml = '';
                let timeHtml = `<div class="fb-combo-svc-meta">${s.duration} min</div>`;
                let staffAndParallelHtml = '';
                let draftOtAlertHtml = '';

                if (isToday) {
                    const sl = sliceBySvcId.get(sid);
                    const tIdx = draft.immediateIds.indexOf(sid);
                    const priorSlices = draftTl.slices.slice(0, tIdx);
                    const meta = window.LaleenSliceEngine
                        ? window.LaleenSliceEngine.buildSpecialistSelectMeta({
                            branch,
                            serviceId: sid,
                            serviceName: s.name,
                            startMin: sl ? sl.startMin : null,
                            endMin: sl ? sl.endMin : null,
                            selectedStaffId: draft.serviceStaff[sid] || '',
                            daySnapshot: daySnap,
                            excludeApptId: editingAppointmentId || null,
                            priorSlices,
                            allStaffs: ALL_STAFFS
                        })
                        : { html: '', unmappedFallback: false, hasConflict: false, needsSelection: false };

                    timeHtml = `<div class="laleen-slice-sub mt-1"><span class="laleen-time-pill">${fbEscHtml(sl ? sl.timeBadge : `${s.duration}m`)}</span>${meta.unmappedFallback ? '<span class="laleen-unmapped-hint">Unmapped skill</span>' : ''}</div>`;
                    reorderHtml = draft.immediateIds.length > 1 ? `
                        <div class="laleen-reorder-group me-1">
                            <button type="button" class="laleen-reorder-btn fb-draft-move-btn" data-svc="${sid}" data-dir="-1" ${tIdx === 0 ? 'disabled' : ''} title="Move earlier">↑</button>
                            <button type="button" class="laleen-reorder-btn fb-draft-move-btn" data-svc="${sid}" data-dir="1" ${tIdx === draft.immediateIds.length - 1 ? 'disabled' : ''} title="Move later">↓</button>
                        </div>` : '';

                    const parallelHtml = (sl && sl.can_parallel) ? `
                        <button type="button" class="laleen-parallel-pill fb-draft-parallel-btn ${sl.is_parallel ? 'is-active' : ''}" data-svc="${sid}">
                            ${sl.is_parallel ? '⇉ Same time' : 'Same time'}
                        </button>` : '';

                    staffAndParallelHtml = `
                        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap justify-content-end">
                            ${parallelHtml}
                            <select class="form-select form-select-sm laleen-specialist-pill fb-combo-staff-select ${meta.needsSelection ? 'is-needs-specialist' : ''} ${meta.isOvertime ? 'is-overtime' : ''} ${meta.hasConflict ? 'is-conflict' : ''}" data-draft-svc="${sid}">
                                ${meta.html}
                            </select>
                        </div>`;
                    draftOtAlertHtml = window.LaleenSliceEngine ? window.LaleenSliceEngine.renderOvertimeAlert(meta) : '';
                }

                return `
                    <div class="fb-combo-svc-row flex-column align-items-stretch ${isRowDimmed ? 'is-dimmed' : ''}" data-svc-id="${sid}">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-1 min-w-0">
                                ${reorderHtml}
                                <div class="min-w-0">
                                    <div class="fb-combo-svc-title">${fbEscHtml(s.name)}</div>
                                    ${timeHtml}
                                </div>
                            </div>
                            <div class="d-flex flex-column align-items-end ms-auto">
                                <div class="fb-combo-mode-group">
                                    <button type="button"
                                        class="fb-combo-mode-btn fb-draft-today-btn ${isToday ? 'is-today' : ''}"
                                        data-svc="${sid}"
                                        ${disablePills ? 'disabled' : ''}>Today</button>
                                    <button type="button"
                                        class="fb-combo-mode-btn fb-draft-pending-btn ${isPending ? 'is-pending' : ''}"
                                        data-svc="${sid}"
                                        ${disablePills ? 'disabled' : ''}>Pending</button>
                                </div>
                                ${staffAndParallelHtml}
                            </div>
                        </div>
                        ${draftOtAlertHtml}
                    </div>`;
            }).join('');

            listEl.querySelectorAll('.fb-draft-today-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!fbActiveComboDraft) return;
                    const svcId = Number(btn.dataset.svc);
                    const totalAllowed = Number(fbActiveComboDraft.combo.quantity_included) || 0;
                    const wasToday = fbActiveComboDraft.immediateIds.includes(svcId);
                    const wasPending = fbActiveComboDraft.pendingIds.includes(svcId);
                    const currentPicked = fbActiveComboDraft.immediateIds.length + fbActiveComboDraft.pendingIds.length;

                    if (wasToday) {
                        fbActiveComboDraft.immediateIds = fbActiveComboDraft.immediateIds.filter(id => id !== svcId);
                        delete fbActiveComboDraft.serviceStaff[svcId];
                        if (fbActiveComboDraft.serviceParallel) delete fbActiveComboDraft.serviceParallel[svcId];
                    } else if (wasPending || currentPicked < totalAllowed) {
                        if (wasPending) {
                            fbActiveComboDraft.pendingIds = fbActiveComboDraft.pendingIds.filter(id => id !== svcId);
                        }
                        fbActiveComboDraft.immediateIds.push(svcId);
                        const svcObj = (fbActiveComboDraft.combo.services || []).find(item => Number(item.id) === svcId);
                        if (svcObj && window.LaleenSliceEngine) {
                            const previewTl = fbComputeDraftComboTimeline(fbActiveComboDraft);
                            const sl = previewTl.slices.find(x => Number(x.service_id) === svcId);
                            const autoId = window.LaleenSliceEngine.pickAutoSpecialist({
                                branch,
                                serviceId: svcId,
                                serviceName: svcObj.name,
                                startMin: sl ? sl.startMin : null,
                                endMin: sl ? sl.endMin : null,
                                clickedStaffId: pendingStaffId,
                                daySnapshot: daySnap,
                                excludeApptId: editingAppointmentId || null,
                                priorSlices: previewTl.slices.slice(0, fbActiveComboDraft.immediateIds.length - 1),
                                allStaffs: ALL_STAFFS
                            });
                            if (autoId) fbActiveComboDraft.serviceStaff[svcId] = String(autoId);
                        }
                    }
                    fbRenderComboConfigPanel();
                });
            });

            listEl.querySelectorAll('.fb-draft-pending-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!fbActiveComboDraft) return;
                    const svcId = Number(btn.dataset.svc);
                    const totalAllowed = Number(fbActiveComboDraft.combo.quantity_included) || 0;
                    const wasPending = fbActiveComboDraft.pendingIds.includes(svcId);
                    const wasToday = fbActiveComboDraft.immediateIds.includes(svcId);
                    const currentPicked = fbActiveComboDraft.immediateIds.length + fbActiveComboDraft.pendingIds.length;

                    if (wasPending) {
                        fbActiveComboDraft.pendingIds = fbActiveComboDraft.pendingIds.filter(id => id !== svcId);
                    } else if (wasToday) {
                        fbActiveComboDraft.immediateIds = fbActiveComboDraft.immediateIds.filter(id => id !== svcId);
                        delete fbActiveComboDraft.serviceStaff[svcId];
                        if (fbActiveComboDraft.serviceParallel) delete fbActiveComboDraft.serviceParallel[svcId];
                        fbActiveComboDraft.pendingIds.push(svcId);
                    } else if (currentPicked < totalAllowed) {
                        fbActiveComboDraft.pendingIds.push(svcId);
                    }
                    fbRenderComboConfigPanel();
                });
            });

            listEl.querySelectorAll('.fb-draft-move-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!fbActiveComboDraft) return;
                    const svcId = Number(btn.dataset.svc);
                    const idx = fbActiveComboDraft.immediateIds.indexOf(svcId);
                    const nextIdx = idx + Number(btn.dataset.dir);
                    if (idx < 0 || nextIdx < 0 || nextIdx >= fbActiveComboDraft.immediateIds.length) return;
                    [fbActiveComboDraft.immediateIds[idx], fbActiveComboDraft.immediateIds[nextIdx]] = [fbActiveComboDraft.immediateIds[nextIdx], fbActiveComboDraft.immediateIds[idx]];
                    fbRenderComboConfigPanel();
                });
            });

            listEl.querySelectorAll('.fb-draft-parallel-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!fbActiveComboDraft) return;
                    const svcId = Number(btn.dataset.svc);
                    fbActiveComboDraft.serviceParallel = fbActiveComboDraft.serviceParallel || {};
                    fbActiveComboDraft.serviceParallel[svcId] = !fbActiveComboDraft.serviceParallel[svcId];
                    fbRenderComboConfigPanel();
                });
            });

            listEl.querySelectorAll('.fb-combo-staff-select[data-draft-svc]').forEach(sel => {
                sel.addEventListener('change', () => {
                    if (!fbActiveComboDraft) return;
                    const svcId = Number(sel.dataset.draftSvc);
                    if (sel.value) {
                        fbActiveComboDraft.serviceStaff[svcId] = sel.value;
                    } else {
                        delete fbActiveComboDraft.serviceStaff[svcId];
                    }
                    fbRenderComboConfigPanel();
                });
            });
        }

        function fbRefreshDraftComboStaffOptions() {
            if (fbActiveComboDraft) fbRenderComboConfigPanel();
        }

        document.getElementById('fbComboConfigBackBtn').addEventListener('click', () => {
            const returnTo = (fbActiveComboDraft && fbActiveComboDraft.isNew) ? 'combo' : 'main';
            fbActiveComboDraft = null;
            fbShowPanel(returnTo);
        });
        document.getElementById('fbComboServiceSearch').addEventListener('input', fbRenderComboConfigPanel);
        document.getElementById('fbComboSkipSalonBtn').addEventListener('click', () => fbCommitActiveComboDraft(true));
        document.getElementById('fbComboConfigApplyBtn').addEventListener('click', () => fbCommitActiveComboDraft(false));

        /* Hide the bottom TEAM MEMBER field whenever >= 1 active (Today) service
           row with per-service specialist pickers is present (or when unscheduled).
           Show TEAM MEMBER ONLY for 0/N "Decide in Salon" combo holds or bare Decide in Salon holds! */
        function fbUpdateTeamMemberVisibility() {
            const isUnscheduled = document.getElementById('bookUnscheduledToggle').checked;
            const hasTodaySlices = fbHasAnyActiveTodaySlices();
            const hide = isUnscheduled || hasTodaySlices;
            document.getElementById('fbTeamMemberSection').classList.toggle('d-none', hide);
            document.getElementById('bookStaffSelect').required = false;
        }

        function fbAutoAssignMainStaffForCombo() {
            const { timeline } = fbComputeUnifiedTimeline();
            if (!timeline.slices.length) return;
            const staffSelect = document.getElementById('bookStaffSelect');
            if (!staffSelect || staffSelect.disabled || !staffSelect.options.length) return;
            const firstStaff = timeline.slices[0].staff_id || '';
            if (firstStaff && [...staffSelect.options].some(o => String(o.value) === String(firstStaff))) {
                staffSelect.value = String(firstStaff);
            }
        }

        function fbRefreshComboStaffOptions() {
            fbRenderCombos(true);
            fbRefreshDraftComboStaffOptions();
        }

        function fbRenderComboPicker(query = '') {
            const box = document.getElementById('fbComboPickerList');
            const searchWrap = document.getElementById('fbComboCatalogSearchWrap');
            if (searchWrap) {
                searchWrap.classList.toggle('d-none', ALL_COMBOS.length === 0);
            }

            if (!ALL_COMBOS.length) {
                box.innerHTML = '<div class="text-muted small px-2">No active combo packages in the catalog.</div>';
                return;
            }

            const q = String(query || '').trim().toLowerCase();
            const filtered = ALL_COMBOS.filter(c => {
                if (!q) return true;
                if (String(c.name || '').toLowerCase().includes(q)) return true;
                return (c.services || []).some(s => String(s.name || '').toLowerCase().includes(q));
            });

            if (!filtered.length) {
                box.innerHTML = '<div class="text-muted small px-2 py-2">No combo packages match your search.</div>';
                return;
            }

            box.innerHTML = filtered.map(c => {
                const origIdx = ALL_COMBOS.indexOf(c);
                return `
                <div class="fb-service-row" data-idx="${origIdx}">
                    <div class="svc-info">
                        <div class="name">${fbEscHtml(c.name)}</div>
                        <div class="meta">Choose any ${c.quantity_included} of ${c.services.length} services &middot; or decide in salon</div>
                    </div>
                    <div class="svc-price">${Number(c.price).toFixed(2)} QAR &rsaquo;</div>
                </div>
            `;
            }).join('');

            box.querySelectorAll('.fb-service-row').forEach(row => {
                row.addEventListener('click', () => {
                    const combo = ALL_COMBOS[Number(row.dataset.idx)];
                    if (!combo) return;
                    fbOpenComboConfigForNew(combo);
                });
            });
        }

        document.getElementById('fbOpenComboPanel').addEventListener('click', () => fbShowPanel('combo'));
        document.getElementById('fbComboCatalogSearch')?.addEventListener('input', function() {
            fbRenderComboPicker(this.value);
        });

        /* ---------------- SERVICE + FREEBIES PACKAGES & REDEMPTIONS ---------------- */
        function fbRenderFreebiePicker(query = '') {
            const box = document.getElementById('fbFreebiePickerList');
            const searchWrap = document.getElementById('fbFreebieCatalogSearchWrap');
            if (!box) return;
            if (searchWrap) {
                searchWrap.classList.toggle('d-none', ALL_FREEBIE_PACKAGES.length === 0);
            }
            if (!ALL_FREEBIE_PACKAGES.length) {
                box.innerHTML = '<div class="text-muted small px-2">No active Service + Freebies packages in the catalog.</div>';
                return;
            }
            const q = String(query || '').trim().toLowerCase();
            const filtered = ALL_FREEBIE_PACKAGES.filter(p => {
                if (!q) return true;
                if (String(p.name || '').toLowerCase().includes(q)) return true;
                if (String(p.anchor_service_name || '').toLowerCase().includes(q)) return true;
                return (p.eligible_services || []).some(s => String(s.name || '').toLowerCase().includes(q));
            });
            if (!filtered.length) {
                box.innerHTML = '<div class="text-muted small px-2 py-2">No Service + Freebies packages match your search.</div>';
                return;
            }
            box.innerHTML = filtered.map(p => {
                const origIdx = ALL_FREEBIE_PACKAGES.indexOf(p);
                const poolCnt = (p.eligible_services || []).length;
                return `
                <div class="fb-service-row" data-freebie-idx="${origIdx}">
                    <div class="svc-info">
                        <div class="name">${fbEscHtml(p.name)}</div>
                        <div class="meta">${fbEscHtml(p.anchor_service_name)} (${p.anchor_duration} min) &middot; Choose ${p.free_choices_count} Free from ${poolCnt} &middot; Valid ${p.validity_days}d</div>
                    </div>
                    <div class="svc-price">${Number(p.price).toFixed(2)} QAR &rsaquo;</div>
                </div>`;
            }).join('');

            box.querySelectorAll('.fb-service-row[data-freebie-idx]').forEach(row => {
                row.addEventListener('click', () => {
                    const pkg = ALL_FREEBIE_PACKAGES[Number(row.dataset.freebieIdx)];
                    if (!pkg) return;
                    const count = Number(pkg.free_choices_count) || 2;
                    const slots = [];
                    for (let i = 1; i <= count; i++) {
                        slots.push({ slotNumber: i, serviceId: '', staffId: '', isParallel: false });
                    }
                    decideInSalon = false;
                    selectedFreebiePackages.push({
                        rowId: ++freebieRowSeq,
                        pkg,
                        anchorStaffId: '',
                        anchorParallel: false,
                        catalog_price: Number(pkg.price) || 0,
                        original_price: Number(pkg.price) || 0,
                        price: Number(pkg.price) || 0,
                        discount_type: 'fixed',
                        discount_value: 0,
                        discount_amount: 0,
                        discount_reason: '',
                        discountOpen: false,
                        slots
                    });
                    fbAutoAssignUnpickedTodaySlices();
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                    fbShowPanel('main');
                });
            });
        }

        document.getElementById('fbOpenFreebiePanel')?.addEventListener('click', () => fbShowPanel('freebie'));
        document.getElementById('fbFreebieCatalogSearch')?.addEventListener('input', function() {
            fbRenderFreebiePicker(this.value);
        });

        function fbRenderFreebiePackages(skipSync = false) {
            const container = document.getElementById('fbFreebieList');
            if (!container) return;
            const branch = fbNormalizeBranch(document.getElementById('bookBranch').value) || fbResolveActiveCalendarBranch();
            const daySnap = fbCurrentDaySnapshot();
            const { timeline, byUid } = fbComputeUnifiedTimeline();

            container.innerHTML = selectedFreebiePackages.map(fpRow => {
                const pkg = fpRow.pkg;
                const anchorSl = byUid.get(`freebie_anchor:${fpRow.rowId}`);
                const anchorPos = anchorSl ? timeline.slices.indexOf(anchorSl) : 0;
                const anchorPrior = anchorPos > 0 ? timeline.slices.slice(0, anchorPos) : [];
                const anchorMeta = window.LaleenSliceEngine
                    ? window.LaleenSliceEngine.buildSpecialistSelectMeta({
                        branch,
                        serviceId: Number(pkg.anchor_service_id),
                        serviceName: pkg.anchor_service_name,
                        startMin: anchorSl ? anchorSl.startMin : null,
                        endMin: anchorSl ? anchorSl.endMin : null,
                        selectedStaffId: fpRow.anchorStaffId || '',
                        daySnapshot: daySnap,
                        excludeApptId: editingAppointmentId || null,
                        priorSlices: anchorPrior,
                        allStaffs: ALL_STAFFS
                    })
                    : { html: '', unmappedFallback: false, hasConflict: false, needsSelection: false };

                const slotsHtml = (fpRow.slots || []).map(slotObj => {
                    const selectedSvcId = slotObj.serviceId ? Number(slotObj.serviceId) : 0;
                    const optionsHtml = [
                        `<option value="" ${!selectedSvcId ? 'selected' : ''}>Save for Later (Pending — Valid ${pkg.validity_days} Days)</option>`,
                        ...(pkg.eligible_services || []).map(es => `<option value="${es.id}" ${Number(es.id) === selectedSvcId ? 'selected' : ''}>${fbEscHtml(es.name)} (${es.duration} min) — 0.00 QAR (Freebie)</option>`)
                    ].join('');

                    let sameDaySubRow = '';
                    if (selectedSvcId) {
                        const esObj = (pkg.eligible_services || []).find(x => Number(x.id) === selectedSvcId);
                        const sl = byUid.get(`freebie_slot:${fpRow.rowId}:${slotObj.slotNumber}`);
                        const slPos = sl ? timeline.slices.indexOf(sl) : 0;
                        const slPrior = slPos > 0 ? timeline.slices.slice(0, slPos) : [];
                        const slMeta = window.LaleenSliceEngine && esObj
                            ? window.LaleenSliceEngine.buildSpecialistSelectMeta({
                                branch,
                                serviceId: Number(esObj.id),
                                serviceName: esObj.name,
                                startMin: sl ? sl.startMin : null,
                                endMin: sl ? sl.endMin : null,
                                selectedStaffId: slotObj.staffId || '',
                                daySnapshot: daySnap,
                                excludeApptId: editingAppointmentId || null,
                                priorSlices: slPrior,
                                allStaffs: ALL_STAFFS
                            })
                            : { html: '', unmappedFallback: false, hasConflict: false, needsSelection: false };

                        const parallelBtn = (sl && sl.can_parallel) ? `
                            <button type="button" class="laleen-parallel-pill fb-freebie-slot-parallel ${sl.is_parallel ? 'is-active' : ''}"
                                data-row="${fpRow.rowId}" data-slot="${slotObj.slotNumber}">
                                ${sl.is_parallel ? '⇉ Same time' : 'Same time'}
                            </button>` : '';

                        sameDaySubRow = `
                            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                <span class="laleen-slice-sub">
                                    <span class="laleen-time-pill">${fbEscHtml(sl ? sl.timeBadge : `${esObj ? esObj.duration : 30}m`)}</span>
                                    <span style="color:#6ee7b7; font-size:11px; font-weight:700;">0.00 QAR (Freebie)</span>
                                </span>
                                <div class="d-flex align-items-center gap-2 ms-auto">
                                    ${parallelBtn}
                                    <select class="form-select form-select-sm laleen-specialist-pill fb-freebie-slot-staff ${slMeta.needsSelection ? 'is-needs-specialist' : ''} ${slMeta.isOvertime ? 'is-overtime' : ''} ${slMeta.hasConflict ? 'is-conflict' : ''}"
                                        data-row="${fpRow.rowId}" data-slot="${slotObj.slotNumber}">
                                        ${slMeta.html}
                                    </select>
                                </div>
                            </div>
                            ${window.LaleenSliceEngine ? window.LaleenSliceEngine.renderOvertimeAlert(slMeta) : ''}`;
                    }

                    return `
                        <div class="fb-freebie-slot-row">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <span style="font-size:11.5px; font-weight:700; color:#e5b8a8; white-space:nowrap;">Freebie #${slotObj.slotNumber}</span>
                                <select class="form-select form-select-sm fb-freebie-slot-select flex-grow-1"
                                    data-row="${fpRow.rowId}" data-slot="${slotObj.slotNumber}">
                                    ${optionsHtml}
                                </select>
                            </div>
                            ${sameDaySubRow}
                        </div>`;
                }).join('');

                const anchorOtAlertHtml = window.LaleenSliceEngine ? window.LaleenSliceEngine.renderOvertimeAlert(anchorMeta) : '';
                if (fpRow.catalog_price === undefined) {
                    fpRow.catalog_price = Number(pkg.price) || 0;
                    fpRow.original_price = Number(pkg.price) || 0;
                    fpRow.price = Number(pkg.price) || 0;
                    fpRow.discount_type = 'fixed';
                    fpRow.discount_value = 0;
                    fpRow.discount_amount = 0;
                    fpRow.discount_reason = '';
                    fpRow.discountOpen = false;
                }
                const fpDisc = fbServiceDiscount(fpRow);

                return `
                    <div class="fb-svc-row fb-combo-card" data-freebie-row="${fpRow.rowId}">
                        <div class="fb-svc-row-main">
                            <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                                <span class="fb-combo-header-pill">Service + Freebies</span>
                                <span class="name">${fbEscHtml(pkg.name)}</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                <span class="fb-orig-strike ${fpDisc > 0 ? '' : 'd-none'}">${Number(fpRow.original_price || pkg.price).toFixed(2)}</span>
                                <div class="fb-price-edit">
                                    <span class="currency">QAR</span>
                                    <input type="number" step="0.01" min="0" inputmode="decimal" class="price-input" data-disc-kind="freebie" data-disc-key="${fpRow.rowId}" value="${Number(fpRow.price !== undefined ? fpRow.price : pkg.price).toFixed(2)}" aria-label="Final price for ${fbEscHtml(pkg.name)}">
                                </div>
                                <button type="button" class="remove-btn fb-freebie-remove-btn" data-row="${fpRow.rowId}" title="Remove package"><i class="bx bx-x"></i></button>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between gap-2 pt-1">
                            <button type="button" class="fb-discount-toggle-btn ${fpDisc > 0 ? 'has-discount' : ''}" data-disc-kind="freebie" data-disc-key="${fpRow.rowId}">
                                ${fbDiscountPillText(fpRow)}
                            </button>
                            <span style="font-size:11px; color:#9a8981;">Base: ${Number(pkg.price).toFixed(2)} QAR</span>
                        </div>
                        ${fbRenderDiscountEditorHtml(fpRow, 'freebie', fpRow.rowId)}
                        <div class="laleen-slice-row flex-column align-items-stretch mt-1">
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <div class="laleen-slice-left">
                                    <div class="laleen-slice-title-wrap">
                                        <span class="laleen-slice-title">Anchor: ${fbEscHtml(pkg.anchor_service_name)}</span>
                                        <span class="laleen-slice-sub">
                                            <span class="laleen-time-pill">${fbEscHtml(anchorSl ? anchorSl.timeBadge : `${pkg.anchor_duration}m`)}</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="laleen-slice-right">
                                    <select class="form-select form-select-sm laleen-specialist-pill fb-freebie-anchor-staff ${anchorMeta.needsSelection ? 'is-needs-specialist' : ''} ${anchorMeta.isOvertime ? 'is-overtime' : ''} ${anchorMeta.hasConflict ? 'is-conflict' : ''}"
                                        data-row="${fpRow.rowId}">
                                        ${anchorMeta.html}
                                    </select>
                                </div>
                            </div>
                            ${anchorOtAlertHtml}
                        </div>
                        ${slotsHtml}
                    </div>`;
            }).join('');

            container.querySelectorAll('.fb-freebie-remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectedFreebiePackages = selectedFreebiePackages.filter(r => Number(r.rowId) !== Number(btn.dataset.row));
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
            });

            container.querySelectorAll('.fb-freebie-anchor-staff').forEach(sel => {
                sel.addEventListener('change', () => {
                    const row = selectedFreebiePackages.find(r => Number(r.rowId) === Number(sel.dataset.row));
                    if (!row) return;
                    row.anchorStaffId = sel.value || '';
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
            });

            container.querySelectorAll('.fb-freebie-slot-select').forEach(sel => {
                sel.addEventListener('change', () => {
                    const row = selectedFreebiePackages.find(r => Number(r.rowId) === Number(sel.dataset.row));
                    const slotObj = row ? (row.slots || []).find(s => Number(s.slotNumber) === Number(sel.dataset.slot)) : null;
                    if (!slotObj) return;
                    slotObj.serviceId = sel.value || '';
                    slotObj.staffId = '';
                    slotObj.isParallel = false;
                    fbAutoAssignUnpickedTodaySlices();
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
            });

            container.querySelectorAll('.fb-freebie-slot-staff').forEach(sel => {
                sel.addEventListener('change', () => {
                    const row = selectedFreebiePackages.find(r => Number(r.rowId) === Number(sel.dataset.row));
                    const slotObj = row ? (row.slots || []).find(s => Number(s.slotNumber) === Number(sel.dataset.slot)) : null;
                    if (!slotObj) return;
                    slotObj.staffId = sel.value || '';
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
            });

            container.querySelectorAll('.fb-freebie-slot-parallel').forEach(btn => {
                btn.addEventListener('click', () => {
                    const row = selectedFreebiePackages.find(r => Number(r.rowId) === Number(btn.dataset.row));
                    const slotObj = row ? (row.slots || []).find(s => Number(s.slotNumber) === Number(btn.dataset.slot)) : null;
                    if (!slotObj) return;
                    slotObj.isParallel = !slotObj.isParallel;
                    fbRenderAllListsWithoutRecursion();
                });
            });

            fbBindDiscountEvents(container, 'freebie', key => selectedFreebiePackages.find(r => Number(r.rowId) === Number(key)));

            if (!skipSync) {
                fbUpdateTeamMemberVisibility();
                fbSyncHiddenAndTotals();
            }
        }

        function fbRenderFreebieAlertBanner() {
            const banner = document.getElementById('fbFreebieAlertBanner');
            if (!banner) return;
            const unpicked = clientFreebieCredits.filter(c => !selectedFreebieRedemptions.some(r => Number(r.credit_id) === Number(c.credit_id)));
            if (!unpicked.length) {
                banner.classList.add('d-none');
                banner.innerHTML = '';
                return;
            }
            const first = unpicked[0];
            const daysText = first.days_left <= 0 ? 'Expires today' : `${first.days_left}d left`;
            banner.classList.remove('d-none');
            banner.innerHTML = `
                <div class="min-w-0">
                    <span style="font-weight:700; color:#f8efe6;">🎁 ${unpicked.length} Freebie(s) Available</span>
                    <span style="color:#b8a29a;"> — ${fbEscHtml(first.package_name)} · </span>
                    <span style="color:#6ee7b7; font-weight:700;">${fbEscHtml(daysText)}</span>
                </div>
                <button type="button" class="fb-freebie-alert-cta" id="fbQuickRedeemFreebieBtn">Redeem Freebie</button>
            `;
            banner.querySelector('#fbQuickRedeemFreebieBtn')?.addEventListener('click', () => {
                fbActivateFreebieRedemption(first.credit_id);
            });
        }

        function fbActivateFreebieRedemption(creditId) {
            const credit = clientFreebieCredits.find(c => Number(c.credit_id) === Number(creditId));
            if (!credit) return;
            if (selectedFreebieRedemptions.some(r => Number(r.credit_id) === Number(credit.credit_id))) return;
            const firstEligible = (credit.eligible_services || [])[0];
            if (!firstEligible) return;
            decideInSalon = false;
            selectedFreebieRedemptions.push({
                credit_id: Number(credit.credit_id),
                package_name: credit.package_name,
                slot_number: credit.slot_number,
                days_left: credit.days_left,
                eligible_services: credit.eligible_services || [],
                service_id: Number(firstEligible.id),
                staff_id: '',
                is_parallel: false
            });
            fbAutoAssignUnpickedTodaySlices();
            fbRenderAllListsWithoutRecursion();
            loadAvailableStaff();
        }

        function fbRenderFreebieRedemptions(skipSync = false) {
            const list = document.getElementById('fbFreebieRedeemList');
            if (!list) return;
            const branch = fbNormalizeBranch(document.getElementById('bookBranch').value) || fbResolveActiveCalendarBranch();
            const daySnap = fbCurrentDaySnapshot();
            const { timeline, byUid } = fbComputeUnifiedTimeline();

            list.innerHTML = selectedFreebieRedemptions.map((fr, idx) => {
                const chosen = (fr.eligible_services || []).find(s => Number(s.id) === Number(fr.service_id)) || (fr.eligible_services || [])[0];
                const sl = byUid.get(`freebie_redeem:${fr.credit_id}`);
                const slPos = sl ? timeline.slices.indexOf(sl) : 0;
                const slPrior = slPos > 0 ? timeline.slices.slice(0, slPos) : [];
                const meta = window.LaleenSliceEngine && chosen
                    ? window.LaleenSliceEngine.buildSpecialistSelectMeta({
                        branch,
                        serviceId: Number(chosen.id),
                        serviceName: chosen.name,
                        startMin: sl ? sl.startMin : null,
                        endMin: sl ? sl.endMin : null,
                        selectedStaffId: fr.staff_id || '',
                        daySnapshot: daySnap,
                        excludeApptId: editingAppointmentId || null,
                        priorSlices: slPrior,
                        allStaffs: ALL_STAFFS
                    })
                    : { html: '', unmappedFallback: false, hasConflict: false, needsSelection: false };

                const optionsHtml = (fr.eligible_services || []).map(es => `
                    <option value="${es.id}" ${Number(es.id) === Number(fr.service_id) ? 'selected' : ''}>
                        ${fbEscHtml(es.name)} (${es.duration} min)
                    </option>`).join('');

                return `
                    <div class="fb-svc-row" style="border-left-color:#6ee7b7;">
                        <div class="fb-svc-row-main">
                            <div class="min-w-0">
                                <div class="name">🎁 Freebie #${fr.slot_number} <span class="fb-decide-tag" style="color:#6ee7b7; background:rgba(110,231,183,0.14);">0.00 QAR</span></div>
                                <div class="meta">${fbEscHtml(fr.package_name)} &middot; ${fr.days_left <= 0 ? 'Expires today' : `${fr.days_left}d left`}</div>
                            </div>
                            <button type="button" class="remove-btn fb-freebie-redeem-remove" data-idx="${idx}" title="Cancel redemption"><i class="bx bx-x"></i></button>
                        </div>
                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap pt-1">
                            <select class="form-select form-select-sm fb-freebie-slot-select fb-freebie-redeem-svc flex-grow-1" data-idx="${idx}">
                                ${optionsHtml}
                            </select>
                            <select class="form-select form-select-sm laleen-specialist-pill fb-freebie-redeem-staff ${meta.needsSelection ? 'is-needs-specialist' : ''} ${meta.isOvertime ? 'is-overtime' : ''} ${meta.hasConflict ? 'is-conflict' : ''}" data-idx="${idx}">
                                ${meta.html}
                            </select>
                        </div>
                        ${window.LaleenSliceEngine ? window.LaleenSliceEngine.renderOvertimeAlert(meta) : ''}
                    </div>`;
            }).join('');

            list.querySelectorAll('.fb-freebie-redeem-remove').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectedFreebieRedemptions.splice(Number(btn.dataset.idx), 1);
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
            });

            list.querySelectorAll('.fb-freebie-redeem-svc').forEach(sel => {
                sel.addEventListener('change', () => {
                    const fr = selectedFreebieRedemptions[Number(sel.dataset.idx)];
                    if (!fr) return;
                    fr.service_id = Number(sel.value);
                    fr.staff_id = '';
                    fbAutoAssignUnpickedTodaySlices();
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
            });

            list.querySelectorAll('.fb-freebie-redeem-staff').forEach(sel => {
                sel.addEventListener('change', () => {
                    const fr = selectedFreebieRedemptions[Number(sel.dataset.idx)];
                    if (!fr) return;
                    fr.staff_id = sel.value || '';
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                });
            });

            if (!skipSync) {
                fbUpdateTeamMemberVisibility();
                fbSyncHiddenAndTotals();
            }
        }

        function fbRenderServiceResults(query) {
            const box = document.getElementById('fbServiceResults');
            const q = query.trim().toLowerCase();
            const matches = ALL_SERVICES.filter(s => s.name.toLowerCase().includes(q));

            document.getElementById('fbSvcCount').textContent = `(${matches.length})`;

            box.innerHTML = matches.map((s, i) => `
                <div class="fb-service-row" data-idx="${ALL_SERVICES.indexOf(s)}">
                    <div class="svc-info">
                        <div class="name">${s.name}</div>
                        <div class="meta">${s.duration}min</div>
                    </div>
                    <div class="svc-price">${s.price.toFixed(2)} QAR</div>
                </div>
            `).join('') || '<div class="text-muted small px-2">No services found.</div>';

            box.querySelectorAll('.fb-service-row').forEach(row => {
                row.addEventListener('click', () => {
                    const svc = ALL_SERVICES[Number(row.dataset.idx)];
                    if (selectedServices.some(s => s.name === svc.name)) {
                        fbShowPanel('main');
                        return;
                    }
                    decideInSalon = false;
                    selectedServices.push(Object.assign({}, svc, {
                        catalog_price: Number(svc.price) || 0,
                        original_price: Number(svc.price) || 0,
                        price: Number(svc.price) || 0,
                        discount_type: 'fixed',
                        discount_value: 0,
                        discount_amount: 0,
                        discount_reason: '',
                        discountOpen: false,
                        staff_id: '',
                        is_parallel: false,
                        manualStaff: false
                    }));
                    fbAutoAssignUnpickedTodaySlices();
                    fbRenderAllListsWithoutRecursion();
                    loadAvailableStaff();
                    fbShowPanel('main');
                });
            });
        }

        document.getElementById('fbOpenServicePanel').addEventListener('click', () => fbShowPanel('service'));
        document.getElementById('fbDecideSalonPill')?.addEventListener('click', () => {
            if (selectedServices.length || selectedCombos.length || selectedRedemptions.length) return;
            decideInSalon = true;
            fbRenderServices();
        });
        document.getElementById('fbServiceSkipSalonBtn')?.addEventListener('click', () => {
            if (!selectedCombos.length && !selectedRedemptions.length) {
                decideInSalon = true;
                selectedServices = [];
            }
            fbRenderServices();
            fbShowPanel('main');
        });
        document.getElementById('fbServiceSearch').addEventListener('input', function() {
            fbRenderServiceResults(this.value);
        });

        /* ---------------- STAFF AVAILABILITY ---------------- */
        function loadAvailableStaff() {
            const staffSelect = document.getElementById('bookStaffSelect');
            const staffHelp = document.getElementById('bookStaffHelp');

            const desiredStaffId = staffSelect.value || pendingStaffId || '';
            if (desiredStaffId) {
                pendingStaffId = String(desiredStaffId);
            }

            const dateVal = document.getElementById('bookDateInput').value;
            const timeVal = document.getElementById('bookTimeInput').value;
            const isUnscheduled = document.getElementById('bookUnscheduledToggle').checked || !timeVal;
            const branch = fbNormalizeBranch(document.getElementById('bookBranch').value) || fbResolveActiveCalendarBranch();
            document.getElementById('bookBranch').value = branch;
            const datetime = (!isUnscheduled && dateVal && timeVal) ? `${dateVal}T${timeVal}` : '';

            staffHelp.classList.add('d-none');

            const comboImmediateNames = [];
            selectedCombos.forEach(row => {
                row.combo.services.forEach(s => {
                    if (row.immediateIds.includes(s.id) && !(row.serviceStaff && row.serviceStaff[s.id])) {
                        comboImmediateNames.push(s.name);
                    }
                });
            });
            const redeemNames = selectedRedemptions
                .filter(r => !redeemStaffMap[r.id])
                .map(r => r.service_name);
            const hasImmediate = selectedServices.length > 0 || comboImmediateNames.length > 0 || redeemNames.length > 0;

            // Populate active-branch staff immediately so the clicked column's
            // stylist remains selected even if off-duty, busy, or missing a skill.
            fbPopulateBranchStaffOptions(branch, desiredStaffId);

            if (!datetime || !branch) {
                return;
            }

            const requestId = ++staffRequestSeq;
            const params = new URLSearchParams();
            if (hasImmediate) {
                selectedServices.forEach(s => params.append('services[]', s.name));
                redeemNames.forEach(name => params.append('services[]', name));
                comboImmediateNames.forEach(name => params.append('services[]', name));
            } else {
                params.append('decide_in_salon', '1');
            }
            params.append('appointment_datetime', datetime);
            params.append('branch', branch);
            if (editingAppointmentId) {
                params.append('exclude_appointment_id', String(editingAppointmentId));
            }

            fetch("{{ route('appointments.availableStaff') }}?" + params.toString())
                .then(res => res.json())
                .then(data => {
                    if (requestId !== staffRequestSeq) return;
                    const list = Array.isArray(data) ? data : [];
                    const availMetaMap = new Map(list.map(s => [String(s.id), s]));

                    fbPopulateBranchStaffOptions(branch, pendingStaffId || desiredStaffId, availMetaMap);

                    if (!list.length) {
                        staffHelp.classList.remove('d-none');
                    }

                    fbAutoAssignMainStaffForCombo();
                })
                .catch(err => console.error('Fetch error:', err));
        }

        /* ---------------- SUBMIT (AJAX WITH INLINE CONFLICT BANNER) ---------------- */
        let fbIsSubmitting = false;
        document.getElementById('calendarBookForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            if (fbIsSubmitting) return;

            fbClearBookingError();
            fbClearAgentError();

            const form = this;
            const dateVal = document.getElementById('bookDateInput').value;
            const timeVal = document.getElementById('bookTimeInput').value;
            const isUnscheduled = document.getElementById('bookUnscheduledToggle').checked || !timeVal;
            const resolvedBranch = fbNormalizeBranch(document.getElementById('bookBranch').value) || fbResolveActiveCalendarBranch();

            document.getElementById('bookBranch').value = resolvedBranch;
            document.getElementById('bookDateHidden').value = dateVal || '';
            document.getElementById('bookUnscheduledHidden').value = isUnscheduled ? '1' : '0';
            document.getElementById('bookDatetimeHidden').value = (!isUnscheduled && dateVal && timeVal) ? `${dateVal}T${timeVal}` : '';
            document.getElementById('bookStaffSelect').required = false;

            if (!selectedClient) {
                document.getElementById('bookThen').value = '';
                fbShowBookingError('Please select an existing client or add a new client before booking.', 'validation');
                fbShowPanel('client');
                return;
            }

            if (!selectedServices.length && !decideInSalon && !selectedCombos.length && !selectedRedemptions.length && !selectedFreebiePackages.length && !selectedFreebieRedemptions.length) {
                document.getElementById('bookThen').value = '';
                fbShowBookingError('Please add at least one service, combo package, Service + Freebies, or choose Decide in Salon.', 'validation');
                fbShowPanel('service');
                return;
            }

            if (document.getElementById('bookThen').value === 'checkout' && decideInSalon && !selectedServices.length && !selectedCombos.length && !selectedRedemptions.length && !selectedFreebiePackages.length && !selectedFreebieRedemptions.length) {
                document.getElementById('bookThen').value = '';
                fbShowPanel('main');
                fbShowBookingError('Please add the service(s) or combo performed before completing checkout.', 'decide_in_salon');
                return;
            }

            const bookAgentEl = document.getElementById('bookAgent');
            if (!bookAgentEl || !fbIsValidBookingAgentSelection(bookAgentEl.value)) {
                document.getElementById('bookThen').value = '';
                fbShowAgentError(FB_BOOKING_AGENT_ERROR_MSG, true);
                return;
            }

            const saveBtn = document.getElementById('fbSaveBtn');
            const checkoutBtn = document.getElementById('fbCheckoutBtn');
            const prevSaveText = saveBtn ? saveBtn.textContent : 'Save';
            fbIsSubmitting = true;
            if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }
            if (checkoutBtn) checkoutBtn.disabled = true;

            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = csrfMeta ? csrfMeta.content : (form.querySelector('input[name="_token"]')?.value || '');
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: new FormData(form),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok || payload.success === false) {
                    let errMsg = payload.message || '';
                    const hasBookingAgentError = Boolean(payload.errors && payload.errors.booking_agent_id);
                    if (payload.errors && typeof payload.errors === 'object') {
                        const firstFieldErrors = hasBookingAgentError
                            ? payload.errors.booking_agent_id
                            : Object.values(payload.errors)[0];
                        if (Array.isArray(firstFieldErrors) && firstFieldErrors[0]) {
                            errMsg = firstFieldErrors[0];
                        } else if (typeof firstFieldErrors === 'string') {
                            errMsg = firstFieldErrors;
                        }
                    }
                    fbShowPanel('main');
                    if (hasBookingAgentError) {
                        fbShowAgentError(errMsg || FB_BOOKING_AGENT_ERROR_MSG, true);
                    }
                    fbShowBookingError(errMsg || 'Unable to save booking due to a schedule or eligibility conflict.', payload.reason || '');
                    return;
                }

                if (payload.redirect) {
                    window.location.href = payload.redirect;
                    return;
                }

                const modalEl = document.getElementById('calendarBookModal');
                const drawer = bootstrap.Offcanvas.getInstance(modalEl);
                const editedId = editingAppointmentId;
                if (drawer) drawer.hide();

                if (window.LaleenSliceEngine) {
                    window.LaleenSliceEngine.invalidateSnapshots();
                }

                if (typeof window.refreshCalendarView === 'function') {
                    window.refreshCalendarView();
                } else {
                    window.location.reload();
                }

                if (editedId && typeof window.refreshOpenAppointmentDrawer === 'function') {
                    window.refreshOpenAppointmentDrawer(editedId);
                }

                if (typeof window.showCalToast === 'function') {
                    window.showCalToast(payload.message || (editedId ? 'Booking updated successfully.' : 'Appointment booked successfully.'), 'success');
                }
            } catch (err) {
                fbShowPanel('main');
                fbShowBookingError('Network error while saving booking. Please try again.', 'network');
            } finally {
                fbIsSubmitting = false;
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = prevSaveText; }
                if (checkoutBtn) checkoutBtn.disabled = false;
            }
        });

        document.getElementById('fbCheckoutBtn').addEventListener('click', function() {
            document.getElementById('bookThen').value = 'checkout';
        });
        document.getElementById('fbSaveBtn').addEventListener('click', function() {
            document.getElementById('bookThen').value = '';
        });

        /* ---------------- AUTO-OPEN BOOKING DRAWER FROM URL / SERVER PREFILL ---------------- */
        (function autoOpenFromQuery() {
            const qs = new URLSearchParams(window.location.search);
            const srv = SERVER_BOOKING_PREFILL || {};

            const hasUrlTrigger = qs.get('open_booking') === '1'
                || qs.has('customer_id')
                || qs.has('name')
                || qs.has('customer_name')
                || qs.has('phone')
                || qs.has('redeem_freebie_credit_id')
                || qs.has('service_ids')
                || qs.has('services')
                || qs.has('service_name');

            if (!srv.auto_open && !hasUrlTrigger) return;

            const customerId = srv.customer_id || (qs.get('customer_id') ? Number(qs.get('customer_id')) : null);
            const customerName = srv.customer_name || qs.get('name') || qs.get('customer_name') || '';
            const phone = srv.phone || qs.get('phone') || '';
            const branch = fbNormalizeBranch(srv.branch || qs.get('branch')) || fbResolveActiveCalendarBranch();
            const staffId = srv.staff_id || (qs.get('staff_id') ? Number(qs.get('staff_id')) : null);
            const redeemFreebieCreditId = srv.redeem_freebie_credit_id || (qs.get('redeem_freebie_credit_id') ? Number(qs.get('redeem_freebie_credit_id')) : null);

            const urlServiceIds = (qs.get('service_ids') || '')
                .split(',')
                .map(s => Number(s.trim()))
                .filter(Boolean);
            const serviceIds = (Array.isArray(srv.service_ids) && srv.service_ids.length)
                ? srv.service_ids
                : urlServiceIds;

            const urlServices = [qs.get('services') || '', qs.get('service_name') || '']
                .join(',')
                .split(',')
                .map(s => s.trim())
                .filter(Boolean);
            const services = (Array.isArray(srv.services) && srv.services.length)
                ? srv.services
                : urlServices;

            const triggerOpen = () => {
                window.openCalendarBookModal({
                    customerId: customerId,
                    customerName: customerName,
                    phone: phone,
                    branch: branch,
                    staffId: staffId,
                    redeemFreebieCreditId: redeemFreebieCreditId,
                    serviceIds: serviceIds,
                    services: services,
                    date: qs.get('date') || null,
                    datetime: qs.get('datetime') || null,
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', triggerOpen);
            } else {
                setTimeout(triggerOpen, 0);
            }
        })();
    })();
</script>
