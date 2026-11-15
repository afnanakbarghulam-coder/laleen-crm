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
        width: 62px;
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

    .fb-discount-row {
        display: flex;
        align-items: center;
        gap: 8px;
        padding-top: 6px;
        border-top: 1px dashed rgba(217, 143, 131, 0.25);
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
        border-bottom: 1px solid rgba(217, 143, 131, 0.3);
        color: #e6d9d5;
        font-size: 12px;
        padding: 1px 4px;
    }

    .fb-discount-reason:focus {
        outline: none;
        border-bottom-color: #d98f83;
    }

    .fb-discount-reason::placeholder {
        color: #8a7d76;
    }

    .fb-footer .total-discount {
        font-size: 11.5px;
        font-weight: 700;
        color: #8ea88a;
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
</style>

<div class="offcanvas offcanvas-end" tabindex="-1" id="calendarBookModal" aria-labelledby="calendarBookModalLabel">
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>

    <form action="{{ route('appointments.store') }}" method="POST" id="calendarBookForm" class="offcanvas-body">
        @csrf
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

                    <div class="fb-section-title">Services</div>
                    <div id="fbSvcList"></div>
                    <div id="fbComboList"></div>
                    <div id="fbRedeemList"></div>
                    <input type="hidden" name="decide_in_salon" id="bookDecideInSalonHidden" value="0">
                    <div id="fbComboHiddenInputs"></div>
                    <div id="fbRedeemHiddenInputs"></div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="fb-add-pill" id="fbOpenServicePanel">
                            <i class="bx bx-plus"></i> Add service
                        </button>
                        <button type="button" class="fb-combo-pill" id="fbOpenComboPanel">
                            <i class="bx bx-plus"></i> Add Combo
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
                        <small id="bookStaffHelp" class="d-none" style="color: #d99b93; font-size: 0.75rem;">
                            No eligible staff free for this slot &mdash; pick another time/stylist or leave unassigned.
                        </small>
                    </div>

                    <div class="fb-section-title">Booking Agent</div>
                    <select id="bookAgent" name="booking_agent_id" class="form-select form-select-sm">
                        <option value="">-- Select Agent --</option>
                        @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                        @endforeach
                    </select>

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
    ])->values();
@endphp

<script>
    (function() {
        const ALL_SERVICES = @json($servicesCatalog ?? []);
        const ALL_COMBOS = @json($combosCatalog ?? []);
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

        const fbDismissBtn = document.getElementById('fbBookingErrorDismiss');
        if (fbDismissBtn) {
            fbDismissBtn.addEventListener('click', fbClearBookingError);
        }

        function fbPopulateBranchStaffOptions(branchKey, preferredId, availableIdsSet) {
            const staffSelect = document.getElementById('bookStaffSelect');
            if (!staffSelect) return;
            const normBranch = fbNormalizeBranch(branchKey) || 'old_airport';
            const branchStaff = ALL_STAFFS.filter(s => !s.branch || s.branch === 'both' || s.branch === normBranch);

            staffSelect.innerHTML = '<option value="">-- Select Staff (Unassigned) --</option>';

            const availableList = availableIdsSet
                ? branchStaff.filter(s => availableIdsSet.has(String(s.id)))
                : branchStaff;
            const unavailableList = availableIdsSet
                ? branchStaff.filter(s => !availableIdsSet.has(String(s.id)))
                : [];

            availableList.forEach(s => {
                staffSelect.insertAdjacentHTML('beforeend', `<option value="${s.id}">${s.name}</option>`);
            });
            unavailableList.forEach(s => {
                staffSelect.insertAdjacentHTML('beforeend', `<option value="${s.id}">${s.name} · Unavailable</option>`);
            });
            staffSelect.disabled = false;

            if (preferredId && [...staffSelect.options].some(o => String(o.value) === String(preferredId))) {
                staffSelect.value = String(preferredId);
            }
        }

        let selectedServices = [];
        let decideInSalon = false;
        let selectedCombos = [];
        let fbActiveComboDraft = null;
        let comboRowSeq = 0;
        let selectedClient = null; // { id, name, phone } or null
        let pendingStaffId = null;
        let staffRequestSeq = 0;
        let clientSearchTimer = null;
        let clientPendingPackages = []; // this client's redeemable package services, fetched once picked
        let selectedRedemptions = []; // subset of the above actually being applied to this booking
        let redeemStaffMap = {}; // { "pkgId:svcId": staffId }
        let redeemStaffOptionsMap = {}; // { "pkgId:svcId": { sig, options } }
        let redeemStaffRequestSeq = 0;

        window.openCalendarBookModal = function(prefill) {
            prefill = prefill || {};

            selectedServices = [];
            decideInSalon = false;
            document.getElementById('bookDecideInSalonHidden').value = '0';
            selectedCombos = [];
            fbActiveComboDraft = null;
            selectedClient = null;
            clientPendingPackages = [];
            selectedRedemptions = [];
            redeemStaffMap = {};
            redeemStaffOptionsMap = {};
            pendingStaffId = prefill.staffId || null;
            fbClearBookingError();

            document.getElementById('bookStaffSelect').innerHTML = '<option value="">-- Select Staff (Unassigned) --</option>';
            document.getElementById('bookStaffHelp').classList.add('d-none');
            const bookAgentEl = document.getElementById('bookAgent');
            if (bookAgentEl) {
                bookAgentEl.value = prefill.bookingAgentId ? String(prefill.bookingAgentId) : '';
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
                        selectedServices.push(Object.assign({}, svc, { original_price: svc.price, discount_reason: '' }));
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

            fbUpdateRail();
            fbRenderServices();
            fbRenderCombos();
            fbRenderRedemptions();
            fbUpdateRedeemButton();
            if (selectedClient && selectedClient.id) {
                fbLoadClientPendingPackages();
            } else if (selectedClient && selectedClient.phone) {
                fbResolveClientByPhone(selectedClient.phone);
            }
            fbUpdateDateLabel();
            fbUpdateTeamMemberVisibility();
            fbShowPanel('main');

            const modalEl = document.getElementById('calendarBookModal');
            const drawer = bootstrap.Offcanvas.getOrCreateInstance(modalEl);
            drawer.show();

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
                loadAvailableStaff();
                fbRefreshComboStaffOptions();
                fbRefreshRedeemStaffOptions();
            }
        };

        document.getElementById('bookDateInput').addEventListener('change', () => { fbClearBookingError(); fbUpdateDateLabel(); loadAvailableStaff(); fbRefreshComboStaffOptions(); fbRefreshRedeemStaffOptions(); });
        document.getElementById('bookTimeInput').addEventListener('change', () => { fbClearBookingError(); fbUpdateDateLabel(); loadAvailableStaff(); fbRefreshComboStaffOptions(); fbRefreshRedeemStaffOptions(); });
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
            fbUpdateTeamMemberVisibility();
            loadAvailableStaff();
        });
        document.getElementById('bookStaffSelect').addEventListener('change', function() {
            fbClearBookingError();
            pendingStaffId = this.value || null;
        });

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
            fbUpdateRedeemButton();

            if (!selectedClient || !selectedClient.id) return;

            fetch(`/customers/${selectedClient.id}/pending-packages`)
                .then(res => res.json())
                .then(data => {
                    clientPendingPackages = Array.isArray(data) ? data : [];
                    fbUpdateRedeemButton();
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
                    fbRenderServices();
                    fbRenderRedemptions();
                    fbUpdateRedeemButton();
                    loadAvailableStaff();
                    fbShowPanel('main');
                });
            });
        }

        function fbFetchRedeemServiceStaff(redemption) {
            const dateVal = document.getElementById('bookDateInput').value;
            const timeVal = document.getElementById('bookTimeInput').value;
            const branch = document.getElementById('bookBranch').value;
            const datetime = (dateVal && timeVal) ? `${dateVal}T${timeVal}` : '';
            if (!datetime || !branch) return;

            const token = redemption.id;
            const sig = fbComboStaffSignature();
            const requestId = ++redeemStaffRequestSeq;

            const params = new URLSearchParams();
            params.append('services[]', redemption.service_name);
            params.append('appointment_datetime', datetime);
            params.append('branch', branch);

            fetch("{{ route('appointments.availableStaff') }}?" + params.toString())
                .then(res => res.json())
                .then(data => {
                    redeemStaffOptionsMap[token] = { sig, options: data };

                    const select = document.querySelector(`.fb-redeem-staff-select[data-token="${token}"]`);
                    if (!select) return;
                    const current = redeemStaffMap[token] || '';
                    select.innerHTML = '<option value="">-- Same as main staff --</option>' +
                        data.map(st => `<option value="${st.id}">${st.name}</option>`).join('');
                    if (current && data.some(st => st.id == current)) {
                        select.value = current;
                    } else if (current) {
                        delete redeemStaffMap[token];
                        fbUpdateTeamMemberVisibility();
                        fbSyncHiddenAndTotals();
                        loadAvailableStaff();
                    }
                })
                .catch(err => console.error('Fetch error:', err));
        }

        function fbRefreshRedeemStaffOptions() {
            selectedRedemptions.forEach(r => {
                const entry = redeemStaffOptionsMap[r.id];
                if (!entry || entry.sig !== fbComboStaffSignature()) {
                    fbFetchRedeemServiceStaff(r);
                }
            });
        }

        function fbRenderRedemptions() {
            const list = document.getElementById('fbRedeemList');

            list.innerHTML = selectedRedemptions.map((r, i) => {
                const staffEntry = redeemStaffOptionsMap[r.id];
                const staffOpts = staffEntry ? staffEntry.options : [];
                const currentStaff = redeemStaffMap[r.id] || '';
                return `
                <div class="fb-svc-row">
                    <div class="fb-svc-row-main">
                        <div>
                            <div class="name">${r.service_name} <span class="fb-decide-tag">free · package</span></div>
                            <div class="meta">${r.duration} min · from ${r.combo_name}</div>
                        </div>
                        <button type="button" class="remove-btn fb-redeem-remove-btn" data-idx="${i}"><i class="bx bx-x"></i></button>
                    </div>
                    <div class="d-flex align-items-center justify-content-between gap-2 pt-1">
                        <span class="meta">Stylist for this service:</span>
                        <select class="form-select form-select-sm fb-redeem-staff-select" data-token="${r.id}">
                            <option value="">-- Same as main staff --</option>
                            ${(staffOpts || []).map(st => `<option value="${st.id}" ${currentStaff == st.id ? 'selected' : ''}>${st.name}</option>`).join('')}
                        </select>
                    </div>
                </div>
            `;
            }).join('');

            fbUpdateTeamMemberVisibility();

            list.querySelectorAll('.fb-redeem-remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const removed = selectedRedemptions.splice(Number(btn.dataset.idx), 1)[0];
                    if (removed) {
                        delete redeemStaffMap[removed.id];
                        delete redeemStaffOptionsMap[removed.id];
                    }
                    fbRenderRedemptions();
                    fbUpdateRedeemButton();
                    fbSyncHiddenAndTotals();
                    loadAvailableStaff();
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
                    fbUpdateTeamMemberVisibility();
                    fbSyncHiddenAndTotals();
                    loadAvailableStaff();
                });
            });

            fbSyncHiddenAndTotals();
            fbRefreshRedeemStaffOptions();
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

        /* ---------------- SERVICES ---------------- */
        function fbServiceDiscount(s) {
            return Math.max(0, (Number(s.original_price) || 0) - (Number(s.price) || 0));
        }

        function fbRenderServices() {
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
                    fbRenderServices();
                });
                fbSyncHiddenAndTotals();
                loadAvailableStaff();
                return;
            }

            list.innerHTML = selectedServices.map((s, i) => {
                const discount = fbServiceDiscount(s);
                return `
                <div class="fb-svc-row">
                    <div class="fb-svc-row-main">
                        <div>
                            <div class="name">${s.name}</div>
                            <div class="meta">${s.duration} min</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="fb-price-edit">
                                <span class="currency">QAR</span>
                                <input type="number" step="0.01" min="0" inputmode="decimal" class="price-input" data-idx="${i}" value="${s.price.toFixed(2)}">
                            </div>
                            <button type="button" class="remove-btn" data-idx="${i}"><i class="bx bx-x"></i></button>
                        </div>
                    </div>
                    ${discount > 0 ? `
                    <div class="fb-discount-row">
                        <span class="fb-discount-badge">−${discount.toFixed(2)} QAR off ${s.original_price.toFixed(2)}</span>
                        <input type="text" class="fb-discount-reason" data-idx="${i}" placeholder="Reason (optional)" value="${(s.discount_reason || '').replace(/"/g,'&quot;')}">
                    </div>` : ''}
                </div>
            `;
            }).join('');

            list.querySelectorAll('.remove-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectedServices.splice(Number(btn.dataset.idx), 1);
                    fbRenderServices();
                });
            });

            // Manual price override: staff can type a custom price for this
            // specific booking without touching the service's master price.
            // The discount badge/reason row and footer total/hidden inputs
            // resync on each keystroke; the price list itself only fully
            // re-renders when a discount note needs to appear/disappear, so
            // typing doesn't steal focus.
            list.querySelectorAll('.price-input').forEach(input => {
                input.addEventListener('input', () => {
                    const idx = Number(input.dataset.idx);
                    const val = parseFloat(input.value);
                    const hadDiscount = fbServiceDiscount(selectedServices[idx]) > 0;
                    selectedServices[idx].price = (isNaN(val) || val < 0) ? 0 : val;
                    const hasDiscount = fbServiceDiscount(selectedServices[idx]) > 0;

                    if (hadDiscount !== hasDiscount) {
                        fbRenderServices();
                        list.querySelector(`.price-input[data-idx="${idx}"]`)?.focus();
                    } else if (hasDiscount) {
                        const badge = list.querySelector(`.fb-svc-row:nth-child(${idx + 1}) .fb-discount-badge`);
                        if (badge) badge.textContent = `−${fbServiceDiscount(selectedServices[idx]).toFixed(2)} QAR off ${selectedServices[idx].original_price.toFixed(2)}`;
                    }
                    fbSyncHiddenAndTotals();
                });
                input.addEventListener('focus', () => input.select());
            });

            list.querySelectorAll('.fb-discount-reason').forEach(input => {
                input.addEventListener('input', () => {
                    selectedServices[Number(input.dataset.idx)].discount_reason = input.value;
                    fbSyncHiddenAndTotals();
                });
            });

            fbSyncHiddenAndTotals();
            loadAvailableStaff();
        }

        function fbSyncHiddenAndTotals() {
            const hasRealItems = selectedServices.length > 0 || selectedCombos.length > 0 || selectedRedemptions.length > 0;
            if (hasRealItems) {
                decideInSalon = false;
            }

            const decidePill = document.getElementById('fbDecideSalonPill');
            if (decidePill) {
                decidePill.classList.toggle('d-none', hasRealItems || decideInSalon);
            }

            document.getElementById('bookDecideInSalonHidden').value =
                (decideInSalon && !hasRealItems) ? '1' : '0';

            const hidden = document.getElementById('svcHiddenInputs');
            hidden.innerHTML = selectedServices.map(s => `
                <input type="hidden" name="service_name[]" value="${s.name.replace(/"/g,'&quot;')}">
                <input type="hidden" name="service_price[]" value="${s.price}">
                <input type="hidden" name="service_discount_reason[]" value="${(s.discount_reason || '').replace(/"/g,'&quot;')}">
            `).join('');

            const comboHidden = document.getElementById('fbComboHiddenInputs');
            comboHidden.innerHTML = selectedCombos.map(row => `
                <input type="hidden" name="packages[${row.id}][combo_id]" value="${row.combo.id}">
                ${row.immediateIds.map(id => `<input type="hidden" name="packages[${row.id}][immediate_service_ids][]" value="${id}">`).join('')}
                ${(row.pendingIds || []).map(id => `<input type="hidden" name="packages[${row.id}][pending_service_ids][]" value="${id}">`).join('')}
                ${Object.entries(row.serviceStaff || {}).filter(([, staffId]) => staffId).map(([sid, staffId]) => `<input type="hidden" name="packages[${row.id}][service_staff][${sid}]" value="${staffId}">`).join('')}
            `).join('');

            const redeemHidden = document.getElementById('fbRedeemHiddenInputs');
            redeemHidden.innerHTML = selectedRedemptions.map(r => {
                const assignedStaff = redeemStaffMap[r.id] || '';
                return `
                <input type="hidden" name="redeem_service_ids[]" value="${r.id}">
                ${assignedStaff ? `<input type="hidden" name="redeem_service_staff[${r.id}]" value="${assignedStaff}">` : ''}
            `;
            }).join('');

            // Redeemed package services are already covered (0 QAR), while newly
            // added Combo packages lock in the Combo QAR price immediately (even
            // when 0 sub-services are chosen upfront — "Choose in Salon").
            const comboImmediateMinutes = selectedCombos.reduce((sum, r) => sum + fbComboImmediateDuration(r), 0);
            const redeemMinutes = selectedRedemptions.reduce((sum, r) => sum + (r.duration || 0), 0);
            const comboPackagesPrice = selectedCombos.reduce((sum, r) => sum + (Number(r.combo.price) || 0), 0);

            const totalPrice = selectedServices.reduce((sum, s) => sum + (Number(s.price) || 0), 0) + comboPackagesPrice;
            const totalDiscount = selectedServices.reduce((sum, s) => sum + fbServiceDiscount(s), 0);
            let totalMin = selectedServices.reduce((sum, s) => sum + s.duration, 0) + comboImmediateMinutes + redeemMinutes;
            if (totalMin === 0 && selectedCombos.length > 0) {
                totalMin = Math.max(...selectedCombos.map(r => Number(r.combo.default_duration) || 60));
            } else if (totalMin === 0 && decideInSalon && !hasRealItems) {
                totalMin = 60;
            }

            document.getElementById('bookPriceHidden').value = totalPrice.toFixed(2);
            document.getElementById('fbFooterPrice').textContent = totalPrice.toFixed(2) + ' QAR';

            const discountEl = document.getElementById('fbFooterDiscount');
            discountEl.classList.toggle('d-none', totalDiscount <= 0);
            discountEl.textContent = `Discount: −${totalDiscount.toFixed(2)} QAR`;

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

            let secondRowHtml = '';
            if (pickedCount === 0) {
                secondRowHtml = `
                    <div class="fb-combo-trigger-row">
                        <span>0 / ${qty} Selected &middot; Decide in Salon &mdash; Click to choose services</span>
                        <span class="fb-combo-edit-cta">&rsaquo;</span>
                    </div>`;
            } else {
                const pickedChips = [];
                (combo.services || []).forEach(s => {
                    if (immediateIds.includes(s.id)) {
                        pickedChips.push(`<span class="fb-combo-picked-chip">${fbEscHtml(s.name)} <span class="mode-today">(Today)</span></span>`);
                    } else if (pendingIds.includes(s.id)) {
                        pickedChips.push(`<span class="fb-combo-picked-chip">${fbEscHtml(s.name)} <span class="mode-pending">(Pending)</span></span>`);
                    }
                });

                secondRowHtml = `
                    <div class="fb-combo-trigger-row">
                        <div class="fb-combo-picked-chips">${pickedChips.join('<span style="color:rgba(255,255,255,0.2);">&middot;</span>')}</div>
                        <span class="fb-combo-edit-cta">Edit services (${pickedCount}/${qty}) &rsaquo;</span>
                    </div>`;
            }

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
                    ${secondRowHtml}
                </div>`;
        }

        function fbRenderCombos() {
            const container = document.getElementById('fbComboList');
            container.innerHTML = selectedCombos.map(fbComboCardHtml).join('');

            fbUpdateTeamMemberVisibility();

            container.querySelectorAll('.fb-combo-remove-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    selectedCombos = selectedCombos.filter(r => r.id != btn.dataset.row);
                    fbRenderCombos();
                    fbSyncHiddenAndTotals();
                    loadAvailableStaff();
                });
            });

            container.querySelectorAll('.fb-combo-card').forEach(card => {
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

            fbSyncHiddenAndTotals();
            fbRefreshComboStaffOptions();
        }

        function fbOpenComboConfigForNew(combo) {
            fbActiveComboDraft = {
                isNew: true,
                rowId: null,
                combo: combo,
                immediateIds: [],
                pendingIds: [],
                serviceStaff: {},
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
                serviceStaffOptions: Object.assign({}, existing.serviceStaffOptions || {}),
            };
            const searchInput = document.getElementById('fbComboServiceSearch');
            if (searchInput) searchInput.value = '';
            fbShowPanel('comboconfig');
            fbRenderComboConfigPanel();
            fbRefreshDraftComboStaffOptions();
        }

        function fbCommitActiveComboDraft(forceDecideInSalon = false) {
            if (!fbActiveComboDraft) return;
            const draft = fbActiveComboDraft;
            if (forceDecideInSalon) {
                draft.immediateIds = [];
                draft.pendingIds = [];
                draft.serviceStaff = {};
                draft.serviceStaffOptions = {};
            }

            if (draft.isNew) {
                selectedCombos.push({
                    id: ++comboRowSeq,
                    combo: draft.combo,
                    immediateIds: [...draft.immediateIds],
                    pendingIds: [...draft.pendingIds],
                    serviceStaff: Object.assign({}, draft.serviceStaff),
                    serviceStaffOptions: Object.assign({}, draft.serviceStaffOptions),
                });
            } else {
                const target = selectedCombos.find(r => Number(r.id) === Number(draft.rowId));
                if (target) {
                    target.immediateIds = [...draft.immediateIds];
                    target.pendingIds = [...draft.pendingIds];
                    target.serviceStaff = Object.assign({}, draft.serviceStaff);
                    target.serviceStaffOptions = Object.assign({}, draft.serviceStaffOptions);
                }
            }

            fbActiveComboDraft = null;
            decideInSalon = false;
            fbRenderServices();
            fbRenderCombos();
            fbSyncHiddenAndTotals();
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
            const todayMin = fbComboImmediateDuration(draft);

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
                    ? `Today's services: ${todayMin} min`
                    : `Calendar hold: ${defaultHold} min · Decide in salon`;
            }
            const priceLabelEl = document.getElementById('fbComboFooterPriceLabel');
            if (priceLabelEl) {
                priceLabelEl.textContent = `${Number(combo.price).toFixed(2)} QAR locked`;
            }

            const query = (document.getElementById('fbComboServiceSearch')?.value || '').trim().toLowerCase();
            const filteredServices = (combo.services || []).filter(s => !query || s.name.toLowerCase().includes(query));
            const listEl = document.getElementById('fbComboConfigServiceList');

            if (!filteredServices.length) {
                listEl.innerHTML = '<div class="text-muted small py-3 text-center">No services in this combo match your search.</div>';
                return;
            }

            listEl.innerHTML = filteredServices.map(s => {
                const isToday = draft.immediateIds.includes(s.id);
                const isPending = !isToday && draft.pendingIds.includes(s.id);
                const isSelected = isToday || isPending;
                const isRowDimmed = !isSelected && capReached;
                const disablePills = !isSelected && capReached;

                const staffEntry = draft.serviceStaffOptions[s.id];
                const staffOpts = staffEntry ? staffEntry.options : null;
                const currentStaff = draft.serviceStaff[s.id] || '';
                const staffSelectHtml = isToday ? `
                    <select class="form-select form-select-sm fb-combo-staff-select mt-1" data-draft-svc="${s.id}" style="max-width:190px; font-size:11.5px;">
                        <option value="">-- Same as main staff --</option>
                        ${(staffOpts || []).map(st => `<option value="${st.id}" ${String(currentStaff) === String(st.id) ? 'selected' : ''}>${fbEscHtml(st.name)}</option>`).join('')}
                    </select>` : '';

                return `
                    <div class="fb-combo-svc-row ${isRowDimmed ? 'is-dimmed' : ''}" data-svc-id="${s.id}">
                        <div>
                            <div class="fb-combo-svc-title">${fbEscHtml(s.name)}</div>
                            <div class="fb-combo-svc-meta">${s.duration} min</div>
                        </div>
                        <div class="d-flex flex-column align-items-end ms-auto">
                            <div class="fb-combo-mode-group">
                                <button type="button"
                                    class="fb-combo-mode-btn fb-draft-today-btn ${isToday ? 'is-today' : ''}"
                                    data-svc="${s.id}"
                                    ${disablePills ? 'disabled' : ''}>Today</button>
                                <button type="button"
                                    class="fb-combo-mode-btn fb-draft-pending-btn ${isPending ? 'is-pending' : ''}"
                                    data-svc="${s.id}"
                                    ${disablePills ? 'disabled' : ''}>Pending</button>
                            </div>
                            ${staffSelectHtml}
                        </div>
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
                    } else if (wasPending) {
                        fbActiveComboDraft.pendingIds = fbActiveComboDraft.pendingIds.filter(id => id !== svcId);
                        fbActiveComboDraft.immediateIds.push(svcId);
                        const svcObj = (fbActiveComboDraft.combo.services || []).find(item => item.id === svcId);
                        if (svcObj) fbFetchComboServiceStaff(fbActiveComboDraft, svcId, svcObj.name);
                    } else if (currentPicked < totalAllowed) {
                        fbActiveComboDraft.immediateIds.push(svcId);
                        const svcObj = (fbActiveComboDraft.combo.services || []).find(item => item.id === svcId);
                        if (svcObj) fbFetchComboServiceStaff(fbActiveComboDraft, svcId, svcObj.name);
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
                        fbActiveComboDraft.pendingIds.push(svcId);
                    } else if (currentPicked < totalAllowed) {
                        fbActiveComboDraft.pendingIds.push(svcId);
                    }
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
                });
            });
        }

        function fbRefreshDraftComboStaffOptions() {
            if (!fbActiveComboDraft) return;
            fbActiveComboDraft.immediateIds.forEach(svcId => {
                const entry = fbActiveComboDraft.serviceStaffOptions[svcId];
                if (!entry || entry.sig !== fbComboStaffSignature()) {
                    const svc = (fbActiveComboDraft.combo.services || []).find(s => s.id === svcId);
                    if (svc) fbFetchComboServiceStaff(fbActiveComboDraft, svcId, svc.name);
                }
            });
        }

        document.getElementById('fbComboConfigBackBtn').addEventListener('click', () => {
            const returnTo = (fbActiveComboDraft && fbActiveComboDraft.isNew) ? 'combo' : 'main';
            fbActiveComboDraft = null;
            fbShowPanel(returnTo);
        });
        document.getElementById('fbComboServiceSearch').addEventListener('input', fbRenderComboConfigPanel);
        document.getElementById('fbComboSkipSalonBtn').addEventListener('click', () => fbCommitActiveComboDraft(true));
        document.getElementById('fbComboConfigApplyBtn').addEventListener('click', () => fbCommitActiveComboDraft(false));

        /* Keep Team Member visible on the main drawer unless the booking is
           unscheduled or every active service already has an explicit per-line
           stylist override. */
        function fbUpdateTeamMemberVisibility() {
            const isUnscheduled = document.getElementById('bookUnscheduledToggle').checked;
            const allComboImmediateOverridden = selectedCombos.length > 0
                && selectedServices.length === 0
                && !selectedRedemptions.length
                && selectedCombos.every(r => r.immediateIds.length > 0 && r.immediateIds.every(sid => Boolean(r.serviceStaff && r.serviceStaff[sid])));
            const allRedemptionsAssignedInline = selectedRedemptions.length > 0
                && selectedServices.length === 0
                && !selectedCombos.length
                && selectedRedemptions.every(r => Boolean(redeemStaffMap[r.id]));
            const hide = isUnscheduled || allComboImmediateOverridden || allRedemptionsAssignedInline;
            document.getElementById('fbTeamMemberSection').classList.toggle('d-none', hide);
            document.getElementById('bookStaffSelect').required = false;
        }

        /* With the Team Member field hidden behind a multi-stylist combo or
           redemption, adopt the first explicitly chosen inline stylist if any;
           otherwise leave unassigned so unassigned bypass rules work cleanly. */
        function fbAutoAssignMainStaffForCombo() {
            if (!selectedCombos.length && !selectedRedemptions.length) return;

            const staffSelect = document.getElementById('bookStaffSelect');
            if (!staffSelect || staffSelect.disabled || !staffSelect.options.length || staffSelect.value) return;

            let preferred = null;
            for (const row of selectedCombos) {
                for (const sid of row.immediateIds) {
                    if (row.serviceStaff[sid]) { preferred = row.serviceStaff[sid]; break; }
                }
                if (preferred) break;
            }
            if (!preferred) {
                for (const r of selectedRedemptions) {
                    if (redeemStaffMap[r.id]) { preferred = redeemStaffMap[r.id]; break; }
                }
            }

            if (preferred && [...staffSelect.options].some(o => o.value == preferred)) {
                staffSelect.value = preferred;
            }
        }

        /* Per-service staff picker for combo services marked "Today" -
           each fetches independently from the main staff dropdown, since a
           combo can split its services across different team members. */
        function fbComboStaffSignature() {
            const dateVal = document.getElementById('bookDateInput').value;
            const timeVal = document.getElementById('bookTimeInput').value;
            const branch = document.getElementById('bookBranch').value;
            return `${dateVal}T${timeVal}|${branch}`;
        }

        let comboStaffRequestSeq = 0;

        function fbFetchComboServiceStaff(row, svcId, svcName) {
            const dateVal = document.getElementById('bookDateInput').value;
            const timeVal = document.getElementById('bookTimeInput').value;
            const branch = fbNormalizeBranch(document.getElementById('bookBranch').value) || fbResolveActiveCalendarBranch();
            const datetime = (dateVal && timeVal) ? `${dateVal}T${timeVal}` : '';
            if (!datetime || !branch) return;

            const sig = fbComboStaffSignature();
            const requestId = ++comboStaffRequestSeq;

            const params = new URLSearchParams();
            params.append('services[]', svcName);
            params.append('appointment_datetime', datetime);
            params.append('branch', branch);

            fetch("{{ route('appointments.availableStaff') }}?" + params.toString())
                .then(res => res.json())
                .then(data => {
                    if (requestId !== comboStaffRequestSeq) return;
                    const availSet = new Set((Array.isArray(data) ? data : []).map(st => String(st.id)));
                    const branchStaff = ALL_STAFFS.filter(s => !s.branch || s.branch === 'both' || s.branch === branch);
                    const mergedOptions = [
                        ...branchStaff.filter(s => availSet.has(String(s.id))).map(s => ({ id: s.id, name: s.name })),
                        ...branchStaff.filter(s => !availSet.has(String(s.id))).map(s => ({ id: s.id, name: `${s.name} · Unavailable` })),
                    ];
                    row.serviceStaffOptions[svcId] = { sig, options: mergedOptions };

                    const select = row === fbActiveComboDraft
                        ? document.querySelector(`.fb-combo-staff-select[data-draft-svc="${svcId}"]`)
                        : document.querySelector(`.fb-combo-staff-select[data-row="${row.id}"][data-svc="${svcId}"]`);
                    if (!select) return;
                    const current = row.serviceStaff[svcId] || '';
                    select.innerHTML = '<option value="">-- Same as main staff --</option>' +
                        mergedOptions.map(st => `<option value="${st.id}">${fbEscHtml(st.name)}</option>`).join('');
                    if (current && mergedOptions.some(st => String(st.id) === String(current))) {
                        select.value = String(current);
                    }
                })
                .catch(err => console.error('Fetch error:', err));
        }

        function fbRefreshComboStaffOptions() {
            selectedCombos.forEach(row => {
                row.immediateIds.forEach(svcId => {
                    const entry = row.serviceStaffOptions[svcId];
                    if (!entry || entry.sig !== fbComboStaffSignature()) {
                        const svc = row.combo.services.find(s => s.id === svcId);
                        if (svc) fbFetchComboServiceStaff(row, svcId, svc.name);
                    }
                });
            });
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
                    selectedServices.push(Object.assign({}, svc, { original_price: svc.price, discount_reason: '' }));
                    fbRenderServices();
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

            fetch("{{ route('appointments.availableStaff') }}?" + params.toString())
                .then(res => res.json())
                .then(data => {
                    if (requestId !== staffRequestSeq) return;
                    const list = Array.isArray(data) ? data : [];
                    const availSet = new Set(list.map(s => String(s.id)));

                    fbPopulateBranchStaffOptions(branch, pendingStaffId || desiredStaffId, availSet);

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
                fbShowBookingError('Please select an existing client or add a new client before booking.', 'validation');
                fbShowPanel('client');
                return;
            }

            if (!selectedServices.length && !decideInSalon && !selectedCombos.length && !selectedRedemptions.length) {
                fbShowBookingError('Please add at least one service, combo package, or choose Decide in Salon.', 'validation');
                fbShowPanel('service');
                return;
            }

            if (document.getElementById('bookThen').value === 'checkout' && decideInSalon && !selectedServices.length && !selectedCombos.length && !selectedRedemptions.length) {
                document.getElementById('bookThen').value = '';
                fbShowPanel('main');
                fbShowBookingError('Please add the service(s) or combo performed before completing checkout.', 'decide_in_salon');
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
                    if (payload.errors && typeof payload.errors === 'object') {
                        const firstFieldErrors = Object.values(payload.errors)[0];
                        if (Array.isArray(firstFieldErrors) && firstFieldErrors[0]) {
                            errMsg = firstFieldErrors[0];
                        } else if (typeof firstFieldErrors === 'string') {
                            errMsg = firstFieldErrors;
                        }
                    }
                    fbShowPanel('main');
                    fbShowBookingError(errMsg || 'Unable to save booking due to a schedule or eligibility conflict.', payload.reason || '');
                    return;
                }

                if (payload.redirect) {
                    window.location.href = payload.redirect;
                    return;
                }

                const modalEl = document.getElementById('calendarBookModal');
                const drawer = bootstrap.Offcanvas.getInstance(modalEl);
                if (drawer) drawer.hide();

                if (typeof window.refreshCalendarView === 'function') {
                    window.refreshCalendarView();
                } else {
                    window.location.reload();
                }

                if (typeof window.showCalToast === 'function') {
                    window.showCalToast(payload.message || 'Appointment booked successfully.', 'success');
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
                || qs.has('service_ids')
                || qs.has('services')
                || qs.has('service_name');

            if (!srv.auto_open && !hasUrlTrigger) return;

            const customerId = srv.customer_id || (qs.get('customer_id') ? Number(qs.get('customer_id')) : null);
            const customerName = srv.customer_name || qs.get('name') || qs.get('customer_name') || '';
            const phone = srv.phone || qs.get('phone') || '';
            const branch = fbNormalizeBranch(srv.branch || qs.get('branch')) || fbResolveActiveCalendarBranch();
            const staffId = srv.staff_id || (qs.get('staff_id') ? Number(qs.get('staff_id')) : null);

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
