@php
    $currentTab = $activeTab ?? (request()->routeIs('products.rd.*') || in_array(request()->query('tab'), ['rd', 'rd-sourcing'], true) ? 'rd' : 'catalog');
@endphp

<div class="laleen-prod-header no-print mb-4">
    <nav class="laleen-prod-tabs" aria-label="Products navigation">
        <a href="{{ route('products.index') }}"
           class="laleen-prod-tab {{ $currentTab === 'catalog' ? 'is-active' : '' }}">
            Products Catalog
        </a>
        <a href="{{ route('products.rd.index') }}"
           class="laleen-prod-tab {{ $currentTab === 'rd' ? 'is-active' : '' }}">
            R&amp;D &amp; Sourcing
        </a>
    </nav>

    @if ($currentTab === 'rd')
        <div class="d-flex align-items-center gap-3">
            <span id="rdSaveIndicator" class="laleen-rd-save-indicator" aria-live="polite"></span>
            @moduleEdit('products')
                <button type="button" class="laleen-rd-text-btn" id="rdToggleAddRowBtn">+ Add Row</button>
                <span style="color: rgba(255,255,255,0.16);">&middot;</span>
                <button type="button" class="laleen-rd-text-btn" id="rdToggleAddSupplierBtn">+ Add Supplier</button>
            @endmoduleEdit
        </div>
    @endif
</div>

<style>
    .laleen-prod-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.10);
        padding-bottom: 10px;
    }

    .laleen-prod-tabs {
        display: inline-flex;
        align-items: center;
        gap: 20px;
    }

    .laleen-prod-tab {
        position: relative;
        padding: 4px 0 10px 0;
        margin-bottom: -11px;
        font-size: 0.86rem;
        font-weight: 500;
        color: #9e8981 !important;
        text-decoration: none !important;
        border-bottom: 2px solid transparent;
        transition: color 0.15s ease, border-color 0.15s ease;
    }

    .laleen-prod-tab:hover {
        color: #f8efe6 !important;
    }

    .laleen-prod-tab.is-active {
        color: #f8efe6 !important;
        font-weight: 600;
        border-bottom-color: #e5b8a8;
    }

    .laleen-rd-text-btn {
        background: transparent;
        border: none;
        padding: 2px 0;
        font-size: 0.8rem;
        font-weight: 500;
        color: #b8a29a;
        cursor: pointer;
        transition: color 0.15s ease;
    }

    .laleen-rd-text-btn:hover {
        color: #f8efe6;
    }

    .laleen-rd-save-indicator {
        font-size: 0.76rem;
        color: #b8a29a;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .laleen-rd-save-indicator.is-visible {
        opacity: 1;
    }

    @media print {
        .no-print {
            display: none !important;
        }
    }
</style>
