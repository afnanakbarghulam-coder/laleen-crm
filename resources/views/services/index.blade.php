@extends('layouts.app')
@section('title', 'Service Catalog')

<style>
    .svc-cat-rail {
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 10px;
        overflow: hidden;
        background: #241e1c;
    }

    .svc-cat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 14px;
        font-size: 13.5px;
        color: #e79a91;
        text-decoration: none;
        border-bottom: 1px solid rgba(217, 143, 131,0.07);
    }

    .svc-cat-row:hover {
        background: rgba(217, 143, 131,0.06);
        color: #e79a91;
    }

    .svc-cat-row.active {
        background: rgba(217, 143, 131,0.1);
        color: #b98ea3;
        font-weight: 700;
    }

    .svc-cat-row .cat-dot {
        display: inline-block;
        width: 9px;
        height: 9px;
        border-radius: 50%;
        margin-right: 8px;
    }

    .svc-cat-row .badge {
        background: rgba(217, 143, 131,0.16);
        color: #cbb8b0;
        font-weight: 700;
    }

    .svc-cat-row.active .badge {
        background: #b98ea3;
        color: #fff;
    }

    .svc-group-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin: 22px 0 12px;
    }

    .svc-group-header:first-child {
        margin-top: 0;
    }

    .svc-group-title {
        font-weight: 800;
        font-size: 16px;
        margin: 0;
        color: #e79a91;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .svc-group-count {
        font-size: 11px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        background: rgba(217, 143, 131, 0.12);
        color: #cbb8b0;
    }

    .svc-group-select-label {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 12px;
        font-weight: 600;
        color: #b8a29a;
        cursor: pointer;
        user-select: none;
        margin: 0;
    }

    .svc-group-select-label:hover {
        color: #f8efe6;
    }

    .svc-card {
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 10px;
        background: #241e1c;
        display: flex;
        gap: 14px;
        align-items: flex-start;
        transition: border-color 0.14s ease, background 0.14s ease, box-shadow 0.14s ease;
    }

    .svc-card.is-selected {
        border-color: rgba(221, 162, 158, 0.55) !important;
        background: linear-gradient(180deg, rgba(221, 162, 158, 0.10) 0%, rgba(36, 30, 28, 0.98) 100%) !important;
        box-shadow: 0 0 0 1px rgba(221, 162, 158, 0.22);
    }

    .svc-check-wrap {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding-top: 2px;
        flex-shrink: 0;
        cursor: pointer;
    }

    .svc-dark-checkbox {
        appearance: none;
        -webkit-appearance: none;
        width: 17px;
        height: 17px;
        border-radius: 5px;
        border: 1.5px solid rgba(221, 162, 158, 0.36);
        background: #161012;
        cursor: pointer;
        position: relative;
        flex-shrink: 0;
        transition: background 0.12s ease, border-color 0.12s ease;
        margin: 0;
    }

    .svc-dark-checkbox:hover {
        border-color: #DDA29E;
    }

    .svc-dark-checkbox:checked {
        background: #DDA29E;
        border-color: #DDA29E;
    }

    .svc-dark-checkbox:checked::after {
        content: '';
        position: absolute;
        left: 4.5px;
        top: 1.5px;
        width: 5px;
        height: 9px;
        border: solid #1b1315;
        border-width: 0 2px 2px 0;
        transform: rotate(42deg);
    }

    .svc-dark-checkbox:indeterminate {
        background: rgba(221, 162, 158, 0.28);
        border-color: #DDA29E;
    }

    .svc-dark-checkbox:indeterminate::after {
        content: '';
        position: absolute;
        left: 3px;
        top: 6px;
        width: 8px;
        height: 2px;
        background: #f8efe6;
        border-radius: 1px;
    }

    .svc-toolbar-select-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        padding: 6px 2px 10px;
        margin-bottom: 6px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .svc-steps-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11.5px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        padding: 3px 10px;
        border-radius: 999px;
        margin-top: 6px;
        border: 1px solid rgba(212, 163, 115, 0.34);
        background: rgba(212, 163, 115, 0.12);
        color: #f3d9a4;
        cursor: pointer;
        transition: all 0.14s ease;
    }

    .svc-steps-pill:hover {
        background: rgba(212, 163, 115, 0.24);
        border-color: rgba(212, 163, 115, 0.58);
        color: #ffffff;
    }

    .svc-steps-pill.is-empty {
        border-color: rgba(217, 143, 131, 0.28);
        background: rgba(217, 143, 131, 0.08);
        color: #d9a79f;
    }

    .svc-steps-pill.is-empty:hover {
        background: rgba(217, 143, 131, 0.20);
        border-color: rgba(217, 143, 131, 0.50);
        color: #f8efe6;
    }

    /* Sticky / Fixed Bottom-Center Dark Luxury Bulk Action Bar */
    .svc-bulk-bar {
        position: fixed;
        bottom: 22px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1050;
        width: min(92vw, 720px);
        background: #1b1315;
        border: 1px solid rgba(221, 162, 158, 0.38);
        border-radius: 16px;
        padding: 12px 18px;
        box-shadow: 0 22px 60px rgba(0, 0, 0, 0.78);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .svc-bulk-count-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        background: rgba(221, 162, 158, 0.16);
        border: 1px solid rgba(221, 162, 158, 0.34);
        color: #f3d5d2;
        font-size: 12.5px;
        font-weight: 700;
        white-space: nowrap;
    }

    .svc-bulk-clear-btn {
        background: transparent;
        border: 1px solid rgba(255, 255, 255, 0.14);
        color: #cbb8b0;
        border-radius: 999px;
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .svc-bulk-clear-btn:hover {
        color: #fff;
        border-color: rgba(221, 162, 158, 0.45);
    }

    .svc-bulk-select {
        min-width: 205px;
        max-width: 260px;
        background: #130d0f !important;
        border: 1px solid rgba(221, 162, 158, 0.28) !important;
        color: #f8efe6 !important;
        border-radius: 10px !important;
        font-size: 13px !important;
        padding: 6px 32px 6px 12px !important;
        color-scheme: dark;
    }

    .svc-bulk-select option {
        background: #130d0f;
        color: #f8efe6;
    }

    .svc-bulk-move-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 18px;
        border-radius: 999px;
        border: none;
        background: linear-gradient(135deg, #DDA29E, #c68d84);
        color: #1c110f;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
        transition: filter 0.14s ease, opacity 0.14s ease;
    }

    .svc-bulk-move-btn:hover:not(:disabled) {
        filter: brightness(1.07);
    }

    .svc-bulk-move-btn:disabled {
        opacity: 0.42;
        cursor: not-allowed;
    }

    .svc-bulk-toast {
        position: fixed;
        top: 22px;
        right: 24px;
        z-index: 1095;
        background: #1b1315;
        border: 1px solid rgba(110, 231, 183, 0.42);
        color: #6ee7b7;
        border-radius: 12px;
        padding: 11px 16px;
        font-size: 13px;
        font-weight: 600;
        box-shadow: 0 18px 46px rgba(0, 0, 0, 0.65);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .svc-bulk-toast.is-error {
        border-color: rgba(251, 113, 133, 0.45);
        color: #fda4af;
    }

    .svc-cat-row .cat-dot.is-uncat {
        background: transparent;
        border: 1.5px dashed #b8a29a;
    }

    .svc-card img.svc-thumb {
        width: 56px;
        height: 56px;
        border-radius: 8px;
        object-fit: cover;
        flex-shrink: 0;
        background: rgba(217, 143, 131,0.07);
    }

    .svc-card .svc-name {
        font-weight: 700;
        font-size: 14.5px;
        color: #e79a91;
    }

    .svc-card .svc-meta {
        font-size: 12px;
        color: #c9a39a;
    }

    .svc-card .svc-desc {
        font-size: 12.5px;
        color: #c9a39a;
        margin-top: 4px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .svc-card .svc-price {
        font-weight: 700;
        font-size: 15px;
        color: #e79a91;
        white-space: nowrap;
    }

    .svc-card .treatment-badge {
        display: inline-block;
        background: rgba(217, 143, 131,0.08);
        border-radius: 999px;
        padding: 2px 10px;
        font-size: 11px;
        font-weight: 600;
        color: #cbb8b0;
        margin-top: 6px;
    }

    .svc-top-tabs {
        display: inline-flex;
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 999px;
        background: #241e1c;
        padding: 3px;
        margin-bottom: 18px;
    }

    .svc-top-tab {
        border: none;
        background: transparent;
        color: #c9a39a;
        font-size: 13.5px;
        font-weight: 700;
        padding: 7px 20px;
        border-radius: 999px;
    }

    .svc-top-tab.active {
        background: #b98ea3;
        color: #fff;
    }

    .svc-tab-pane {
        display: none;
    }

    .svc-tab-pane.active {
        display: block;
    }

    .combo-card {
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 10px;
        background: #241e1c;
        display: flex;
        gap: 14px;
        align-items: flex-start;
        justify-content: space-between;
    }

    .combo-card .combo-name {
        font-weight: 700;
        font-size: 14.5px;
        color: #e79a91;
    }

    .combo-card .combo-status {
        display: inline-block;
        margin-left: 8px;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 1px 8px;
        border-radius: 999px;
        background: rgba(142, 168, 138, 0.14);
        color: #8ea88a;
        vertical-align: middle;
    }

    .combo-card .combo-status.inactive {
        background: rgba(201, 163, 154, 0.14);
        color: #c9a39a;
    }

    .combo-card .combo-services {
        font-size: 12.5px;
        color: #c9a39a;
        margin-top: 4px;
    }

    .combo-card .combo-meta {
        font-size: 12px;
        color: #c9a39a;
        margin-top: 4px;
    }

    .combo-card .combo-price {
        font-weight: 700;
        font-size: 15px;
        color: #e79a91;
        white-space: nowrap;
    }

    .combo-card .combo-regular-price {
        font-size: 11.5px;
        color: #c9a39a;
        text-decoration: line-through;
        white-space: nowrap;
    }

    #comboModal .combo-svc-picker {
        max-height: 260px;
        overflow-y: auto;
        border: 1px solid rgba(217, 143, 131,0.16);
        border-radius: 8px;
        padding: 6px 4px;
    }

    #comboModal .combo-svc-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 7px 10px;
        border-bottom: 1px solid rgba(217, 143, 131,0.07);
    }

    #comboModal .combo-svc-row:last-child {
        border-bottom: none;
    }

    #comboModal .combo-svc-row .svc-label {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    #comboModal .combo-svc-row .svc-price {
        font-size: 12px;
        color: #c9a39a;
        white-space: nowrap;
    }

    @media print {
        .svc-top-tabs, .svc-tab-action, .svc-cat-rail, .svc-bulk-bar, .svc-toolbar-select-row, .svc-check-wrap, .svc-group-select-label, .btn, form[method="GET"] {
            display: none !important;
        }
        .svc-tab-pane {
            display: block !important;
        }
        .svc-card, .combo-card {
            background: #ffffff !important;
            color: #111111 !important;
            border-color: #dddddd !important;
        }
        .svc-card .svc-name, .combo-card .combo-name, .combo-card .combo-price, .svc-card .svc-price {
            color: #111111 !important;
        }
        .svc-card .svc-meta, .combo-card .combo-meta, .combo-card .combo-services {
            color: #444444 !important;
        }
    }
</style>

@section('content')
    <style>
        .svc-bulk-bar {
            background: #1b1315 !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }
        .svc-bulk-select {
            width: auto !important;
            min-width: 210px !important;
            max-width: 250px !important;
            flex: 0 0 auto !important;
            background: #130d0f !important;
            background-color: #130d0f !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23DDA29E' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.85rem center !important;
            background-size: 12px 10px !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
            appearance: none !important;
            border: 1px solid rgba(221, 162, 158, 0.30) !important;
            color: #f8efe6 !important;
        }
        .svc-bulk-select:focus {
            background: #130d0f !important;
            border-color: #DDA29E !important;
            box-shadow: 0 0 0 2px rgba(221, 162, 158, 0.22) !important;
        }
        .svc-offers-tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 19px;
            height: 19px;
            padding: 0 6px;
            margin-left: 6px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            background: rgba(52, 211, 153, 0.16);
            color: #6ee7b7;
            transition: background 0.14s ease, color 0.14s ease;
        }
        .svc-top-tab.active .svc-offers-tab-badge {
            background: rgba(27, 19, 21, 0.22);
            color: #1b1315;
        }
    </style>
    @php
        $initialCatalogTab = in_array(request('tab'), ['services', 'combos', 'freebies', 'offers'], true) ? request('tab') : 'services';
        $activeOffersBadgeCount = collect($monthlyOffers ?? [])
            ->filter(fn ($o) => $o->liveStatus($todayQatar ?? null)['key'] === 'active')
            ->count();
    @endphp
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Service Catalog</h4>
            <p class="text-muted small mb-0">Manage salon services, multi-visit combos, Service + Freebies, and Monthly Offers.</p>
        </div>
        @moduleEdit('services')
            <button class="btn btn-dark svc-tab-action {{ $initialCatalogTab !== 'services' ? 'd-none' : '' }}" data-tab="services" data-bs-toggle="modal" data-bs-target="#serviceModal">
                <i class="bx bx-plus me-1"></i> Add Service
            </button>
            <button class="btn btn-dark svc-tab-action {{ $initialCatalogTab !== 'combos' ? 'd-none' : '' }}" data-tab="combos" id="addComboBtn">
                <i class="bx bx-plus me-1"></i> Add Combo
            </button>
            <button class="btn btn-dark svc-tab-action {{ $initialCatalogTab !== 'freebies' ? 'd-none' : '' }}" data-tab="freebies" id="addFreebieBtn">
                <i class="bx bx-plus me-1"></i> Add Service + Freebies
            </button>
            <button class="btn btn-dark svc-tab-action {{ $initialCatalogTab !== 'offers' ? 'd-none' : '' }}" data-tab="offers" id="addOfferBtn">
                <i class="bx bx-plus me-1"></i> Add Monthly Offer
            </button>
        @endmoduleEdit
    </div>

    <div class="svc-top-tabs">
        <button type="button" class="svc-top-tab {{ $initialCatalogTab === 'services' ? 'active' : '' }}" data-tab="services">Services</button>
        <button type="button" class="svc-top-tab {{ $initialCatalogTab === 'combos' ? 'active' : '' }}" data-tab="combos">Combos</button>
        <button type="button" class="svc-top-tab {{ $initialCatalogTab === 'freebies' ? 'active' : '' }}" data-tab="freebies">Service + Freebies</button>
        <button type="button" class="svc-top-tab {{ $initialCatalogTab === 'offers' ? 'active' : '' }}" data-tab="offers">
            <span>Offers</span>
            @if ($activeOffersBadgeCount > 0)
                <span class="svc-offers-tab-badge">{{ $activeOffersBadgeCount }}</span>
            @endif
        </button>
    </div>

    <!-- TAB: SERVICES -->
    <div class="svc-tab-pane {{ $initialCatalogTab === 'services' ? 'active' : '' }}" id="tab-services">
    @php
        $activeCatParam = request('category_id');
        $visibleServiceCount = $services->flatten(1)->count();
    @endphp
    <div class="row g-3">
        <div class="col-md-3">
            <div class="svc-cat-rail mb-3">
                <a href="{{ route('services.index', array_filter(['search' => request('search')])) }}"
                   class="svc-cat-row {{ !$activeCatParam ? 'active' : '' }}">
                    <span>All categories</span>
                    <span class="badge rounded-pill" data-cat-count="all">{{ $totalServicesCount ?? $categories->sum('services_count') }}</span>
                </a>
                <a href="{{ route('services.index', array_filter(['category_id' => 'uncategorized', 'search' => request('search')])) }}"
                   class="svc-cat-row {{ $activeCatParam === 'uncategorized' ? 'active' : '' }}">
                    <span><span class="cat-dot is-uncat"></span>Uncategorized</span>
                    <span class="badge rounded-pill" data-cat-count="uncategorized">{{ $uncategorizedCount ?? 0 }}</span>
                </a>
                @foreach ($categories as $cat)
                    <a href="{{ route('services.index', array_filter(['category_id' => $cat->id, 'search' => request('search')])) }}"
                        class="svc-cat-row {{ (string) $activeCatParam === (string) $cat->id ? 'active' : '' }}">
                        <span><span class="cat-dot" style="background:{{ $cat->color }}"></span>{{ $cat->name }}</span>
                        <span class="badge rounded-pill" data-cat-count="{{ $cat->id }}">{{ $cat->services_count }}</span>
                    </a>
                @endforeach
            </div>
            @moduleEdit('services')
                <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="bx bx-plus"></i> Add category
                </button>
            @endmoduleEdit
        </div>

        <div class="col-md-9" style="padding-bottom: 84px;">
            <form method="GET" action="{{ route('services.index') }}" class="mb-2">
                @if ($activeCatParam)
                    <input type="hidden" name="category_id" value="{{ $activeCatParam }}">
                @endif
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bx bx-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search service name" value="{{ request('search') }}">
                    <button class="btn btn-outline-secondary" type="submit">Search</button>
                </div>
            </form>

            @moduleEdit('services')
                @if ($visibleServiceCount > 0)
                    <div class="svc-toolbar-select-row">
                        <label class="svc-group-select-label" for="svcSelectAllVisible">
                            <input type="checkbox" id="svcSelectAllVisible" class="svc-dark-checkbox">
                            <span>Select all visible ({{ $visibleServiceCount }})</span>
                        </label>
                        <span class="small" style="color: #8f7a73;">Tip: Shift+Click to select a range</span>
                    </div>
                @endif
            @endmoduleEdit

            @php $groupIdx = 0; @endphp
            @forelse ($services as $groupName => $groupServices)
                <div class="svc-group-header">
                    <div class="svc-group-title">
                        <span>{{ $groupName }}</span>
                        <span class="svc-group-count">{{ $groupServices->count() }}</span>
                    </div>
                    @moduleEdit('services')
                        <label class="svc-group-select-label">
                            <input type="checkbox"
                                class="svc-dark-checkbox svc-group-select-all"
                                data-group-index="{{ $groupIdx }}">
                            <span>Select all</span>
                        </label>
                    @endmoduleEdit
                </div>

                @php
                    $canEditServiceSteps = (bool) (auth()->user()?->canEditServiceSteps());
                @endphp
                @foreach ($groupServices as $service)
                    @php
                        $svcSteps = $service->normalizedSteps();
                        $svcStepsCount = count($svcSteps);
                        $svcStaffIds = $service->staff->pluck('id')->values();
                    @endphp
                    <div class="svc-card" data-service-card="{{ $service->id }}">
                        @moduleEdit('services')
                            <label class="svc-check-wrap" title="Select {{ $service->name }}" onclick="event.stopPropagation();">
                                <input type="checkbox"
                                    class="svc-dark-checkbox svc-bulk-check"
                                    value="{{ $service->id }}"
                                    data-service-id="{{ $service->id }}"
                                    data-group-index="{{ $groupIdx }}"
                                    aria-label="Select {{ $service->name }}">
                            </label>
                        @endmoduleEdit

                        @if ($service->photo)
                            <img src="{{ asset($service->photo) }}" class="svc-thumb">
                        @else
                            <img src="{{ asset('design/sneat-admin-template/assets/img/avatars/1.png') }}" class="svc-thumb">
                        @endif

                        <div class="flex-grow-1">
                            <div class="svc-name">{{ $service->name }}</div>
                            <div class="svc-meta">
                                {{ $service->duration }} min
                                @if ($service->staff->count())
                                    · {{ $service->staff->count() }} team member{{ $service->staff->count() === 1 ? '' : 's' }}
                                @else
                                    · <span class="text-warning">No team members assigned</span>
                                @endif
                            </div>
                            @if ($service->description)
                                <div class="svc-desc">{{ $service->description }}</div>
                            @endif
                            <div class="d-flex align-items-center flex-wrap gap-1">
                                <button type="button"
                                    class="svc-steps-pill js-svc-steps-pill {{ $svcStepsCount === 0 ? 'is-empty' : '' }}"
                                    data-service-id="{{ $service->id }}"
                                    data-service='@json($service)'
                                    data-staff-ids='@json($svcStaffIds)'
                                    title="{{ $svcStepsCount > 0 ? 'View or edit treatment steps' : ($canEditServiceSteps ? 'Add treatment steps' : 'View treatment steps') }}">
                                    <i class="bx bx-list-ol"></i>
                                    <span class="js-svc-steps-pill-label">
                                        @if ($svcStepsCount > 0)
                                            {{ $svcStepsCount }} {{ $svcStepsCount === 1 ? 'step' : 'steps' }}
                                        @else
                                            {{ $canEditServiceSteps ? '+ Steps' : '0 steps' }}
                                        @endif
                                    </span>
                                </button>
                                @if ($service->treatment_type)
                                    <span class="treatment-badge">{{ $service->treatment_type }}</span>
                                @endif
                                @if ($service->rebooking_interval_days)
                                    <span class="treatment-badge"><i class="bx bx-refresh"></i> Re-book every {{ $service->rebooking_interval_days }}d</span>
                                @endif
                            </div>
                        </div>

                        <div class="text-end">
                            <div class="svc-price">{{ number_format($service->price, 2) }} QAR</div>
                            @moduleEdit('services')
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-warning edit-btn"
                                        data-service-id="{{ $service->id }}"
                                        data-service='@json($service)'
                                        data-staff-ids='@json($svcStaffIds)'
                                        title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <form action="{{ route('services.destroy', $service->id) }}" method="POST" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"
                                            onclick="return confirm('Delete this service?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            @endmoduleEdit
                        </div>
                    </div>
                @endforeach
                @php $groupIdx++; @endphp
            @empty
                <div class="text-center text-muted py-5">No services found.</div>
            @endforelse
        </div>
    </div>

    @moduleEdit('services')
    {{-- Floating Sticky Bulk Category Action Bar (Services Tab Only, appears when selectedCount > 0) --}}
    <div id="svcBulkActionBar" class="svc-bulk-bar d-none" role="region" aria-label="Bulk category actions">
        <div class="d-flex align-items-center gap-2">
            <span class="svc-bulk-count-pill" id="svcBulkCountBadge">0 services selected</span>
            <button type="button" class="svc-bulk-clear-btn" id="svcBulkClearBtn">Clear</button>
        </div>

        <div class="d-flex align-items-center flex-wrap flex-sm-nowrap gap-2">
            <select id="svcBulkCategorySelect" class="form-select svc-bulk-select" aria-label="Target category">
                <option value="">Move to category...</option>
                <option value="__uncategorized__">-- Uncategorized --</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
            <button type="button" id="svcBulkMoveBtn" class="svc-bulk-move-btn" disabled>
                <i class="bx bx-transfer-alt"></i>
                <span id="svcBulkMoveBtnText">Move Selected</span>
            </button>
        </div>
    </div>
    @endmoduleEdit
    </div>
    <!-- /TAB: SERVICES -->

    <!-- TAB: COMBOS -->
    <div class="svc-tab-pane {{ $initialCatalogTab === 'combos' ? 'active' : '' }}" id="tab-combos">
        <div id="comboList">
            @forelse ($combos as $combo)
                <div class="combo-card" data-combo-id="{{ $combo->id }}">
                    <div class="flex-grow-1">
                        <div>
                            <span class="combo-name">{{ $combo->name }}</span>
                            <span class="combo-status {{ $combo->status !== 'active' ? 'inactive' : '' }}">{{ $combo->status }}</span>
                        </div>
                        <div class="combo-services">{{ $combo->services->pluck('name')->join(', ') }}</div>
                        <div class="combo-meta">
                            @php $poolSize = $combo->services->count(); @endphp
                            @if ($combo->quantity_included && $combo->quantity_included < $poolSize)
                                Choose any {{ $combo->quantity_included }} of {{ $poolSize }} services
                            @else
                                {{ $poolSize }} service{{ $poolSize === 1 ? '' : 's' }}
                            @endif
                            · Valid {{ $combo->validity_days ?: 7 }} days from Visit 1 payment
                        </div>
                    </div>

                    <div class="text-end">
                        <div class="combo-price">{{ number_format($combo->price, 2) }} QAR</div>
                        @php
                            $regular = $combo->services->sum('price');
                            $comboEditData = [
                                'id' => $combo->id,
                                'name' => $combo->name,
                                'price' => $combo->price,
                                'quantity_included' => $combo->quantity_included,
                                'validity_days' => $combo->validity_days,
                                'status' => $combo->status,
                            ];
                            $comboServiceIds = $combo->services->pluck('id');
                        @endphp
                        @if ($regular > $combo->price)
                            <div class="combo-regular-price">{{ number_format($regular, 2) }} QAR</div>
                        @endif
                        @moduleEdit('services')
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-warning combo-edit-btn"
                                    data-combo='@json($comboEditData)'
                                    data-service-ids='@json($comboServiceIds)'
                                    title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger combo-delete-btn"
                                    data-combo-id="{{ $combo->id }}" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        @endmoduleEdit
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-5" id="comboEmptyState">No combo packages yet.</div>
            @endforelse
        </div>
    </div>
    <!-- /TAB: COMBOS -->

    <!-- TAB: SERVICE + FREEBIES -->
    <div class="svc-tab-pane {{ $initialCatalogTab === 'freebies' ? 'active' : '' }}" id="tab-freebies">
        <div id="freebieList">
            @forelse ($freebiePackages ?? [] as $fPkg)
                @php
                    $fAnchor = $fPkg->anchorService;
                    $fStaffCount = $fAnchor ? $fAnchor->staff->count() : 0;
                    $fPoolSize = $fPkg->eligibleServices->count();
                    $fEditData = [
                        'id' => $fPkg->id,
                        'name' => $fPkg->name,
                        'anchor_service_id' => $fPkg->anchor_service_id,
                        'price' => (float) $fPkg->price,
                        'free_choices_count' => $fPkg->effective_choices_count,
                        'validity_days' => $fPkg->effective_validity_days,
                        'is_active' => (bool) $fPkg->is_active,
                    ];
                    $fEligibleIds = $fPkg->eligibleServices->pluck('id')->values();
                @endphp
                <div class="combo-card freebie-pkg-card" data-freebie-id="{{ $fPkg->id }}">
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="combo-name">{{ $fPkg->name }}</span>
                            <span class="combo-status {{ !$fPkg->is_active ? 'inactive' : '' }}">{{ $fPkg->is_active ? 'active' : 'inactive' }}</span>
                        </div>
                        <div class="combo-meta mt-1" style="color: #f8efe6; font-weight: 600;">
                            Anchor: {{ $fAnchor?->name ?? 'Anchor Service' }}
                            @if ($fAnchor?->category?->name)
                                <span style="color: #b8a29a; font-weight: 400;">({{ $fAnchor->category->name }})</span>
                            @endif
                            · {{ $fAnchor?->duration ?? 60 }} min
                            · {{ number_format((float) ($fAnchor?->price ?? 0), 2) }} QAR
                            @if ($fStaffCount > 0)
                                · {{ $fStaffCount }} team member{{ $fStaffCount === 1 ? '' : 's' }}
                            @else
                                · No team members assigned
                            @endif
                        </div>
                        <div class="combo-meta">
                            Choose {{ $fPkg->effective_choices_count }} Free (from {{ $fPoolSize }} eligible) · Valid {{ $fPkg->effective_validity_days }} days
                        </div>
                        <div class="combo-services mt-1">
                            {{ $fPkg->eligibleServices->map(fn ($es) => $es->name . ' (' . number_format((float) $es->price, 2) . ' QAR)')->join(' · ') }}
                        </div>
                    </div>

                    <div class="text-end shrink-0">
                        <div class="combo-price">{{ number_format($fPkg->price, 2) }} QAR</div>
                        @moduleEdit('services')
                            <div class="mt-2 d-flex justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-outline-warning freebie-edit-btn"
                                    data-pkg='@json($fEditData)'
                                    data-eligible-ids='@json($fEligibleIds)'
                                    title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger freebie-delete-btn"
                                    data-freebie-id="{{ $fPkg->id }}"
                                    data-freebie-name="{{ $fPkg->name }}"
                                    title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        @endmoduleEdit
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-5" id="freebieEmptyState">No Service + Freebies packages yet.</div>
            @endforelse
        </div>
    </div>
    <!-- /TAB: SERVICE + FREEBIES -->

    <!-- TAB: MONTHLY OFFERS -->
    <div class="svc-tab-pane {{ $initialCatalogTab === 'offers' ? 'active' : '' }}" id="tab-offers">
        @include('services.partials.offers-board', [
            'monthlyOffers' => $monthlyOffers ?? collect(),
            'todayQatar' => $todayQatar ?? now('Asia/Qatar'),
            'canEditOffers' => (bool) (auth()->user()?->canEdit('services')),
            'combos' => $combos ?? collect(),
            'freebiePackages' => $freebiePackages ?? collect(),
            'catalogServices' => $allServices ?? collect(),
            'isStandaloneOffersPage' => false,
        ])
    </div>
    <!-- /TAB: MONTHLY OFFERS -->

    @include('services.main-form')

    @moduleEdit('services')
    @include('services.combo-form')
    @include('services.freebie-form')

    <!-- Add Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <form method="POST" action="{{ route('service-categories.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category name</label>
                            <input type="text" name="name" class="form-control" required maxlength="100">
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" class="form-control form-control-color" value="#d98f83">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endmoduleEdit

    <script>
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const service = JSON.parse(this.dataset.service);
                const staffIds = JSON.parse(this.dataset.staffIds || '[]');
                window.openServiceModal(service, staffIds, 'basic');
            });
        });

        document.querySelectorAll('.js-svc-steps-pill').forEach(btn => {
            btn.addEventListener('click', function() {
                const service = JSON.parse(this.dataset.service);
                const staffIds = JSON.parse(this.dataset.staffIds || '[]');
                window.openServiceModal(service, staffIds, 'steps');
            });
        });

        window.syncServiceStepsCard = function(serviceId, savedSteps) {
            const stepsArr = Array.isArray(savedSteps) ? savedSteps : [];
            const count = stepsArr.length;
            const canEditSteps = @json((bool) (auth()->user()?->canEditServiceSteps()));

            document.querySelectorAll(`.js-svc-steps-pill[data-service-id="${serviceId}"]`).forEach(pill => {
                try {
                    const data = JSON.parse(pill.dataset.service || '{}');
                    data.steps = stepsArr;
                    pill.dataset.service = JSON.stringify(data);
                } catch (e) {}

                pill.classList.toggle('is-empty', count === 0);
                const labelEl = pill.querySelector('.js-svc-steps-pill-label');
                if (labelEl) {
                    labelEl.textContent = count > 0
                        ? `${count} ${count === 1 ? 'step' : 'steps'}`
                        : (canEditSteps ? '+ Steps' : '0 steps');
                }
            });

            document.querySelectorAll(`.edit-btn[data-service-id="${serviceId}"]`).forEach(btn => {
                try {
                    const data = JSON.parse(btn.dataset.service || '{}');
                    data.steps = stepsArr;
                    btn.dataset.service = JSON.stringify(data);
                } catch (e) {}
            });
        };

        /* ---------------- TOP TABS (Services / Combos / Service + Freebies / Offers) ---------------- */
        document.querySelectorAll('.svc-top-tab').forEach(tabBtn => {
            tabBtn.addEventListener('click', function() {
                const tab = this.dataset.tab;

                document.querySelectorAll('.svc-top-tab').forEach(b => b.classList.toggle('active', b === this));
                document.querySelectorAll('.svc-tab-pane').forEach(p => p.classList.toggle('active', p.id === 'tab-' + tab));
                document.querySelectorAll('.svc-tab-action').forEach(b => b.classList.toggle('d-none', b.dataset.tab !== tab));

                const url = new URL(window.location.href);
                url.searchParams.set('tab', tab);
                history.replaceState(null, '', url.toString());

                // Hide bulk bar cleanly when navigating away from the Services tab
                const bulkBar = document.getElementById('svcBulkActionBar');
                if (bulkBar && tab !== 'services') {
                    bulkBar.classList.add('d-none');
                } else if (typeof window.syncSvcBulkBar === 'function') {
                    window.syncSvcBulkBar();
                }
            });
        });

        @moduleEdit('services')
        /* ---------------- BULK CATEGORY ASSIGNMENT (Services Tab) ---------------- */
        (function () {
            function showSvcBulkToast(message, isError = false) {
                const existing = document.getElementById('svcBulkToastEl');
                if (existing) existing.remove();
                const toast = document.createElement('div');
                toast.id = 'svcBulkToastEl';
                toast.className = 'svc-bulk-toast' + (isError ? ' is-error' : '');
                toast.innerHTML = `<i class="bx ${isError ? 'bx-error-circle' : 'bx-check-circle'} fs-5"></i><span></span>`;
                toast.querySelector('span').textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.transition = 'opacity 0.25s ease';
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 260);
                }, 4000);
            }

            // Show toast carried across clean reload after bulk move
            try {
                const pendingToast = sessionStorage.getItem('svcBulkMoveToast');
                if (pendingToast) {
                    sessionStorage.removeItem('svcBulkMoveToast');
                    showSvcBulkToast(pendingToast, false);
                }
            } catch (e) {}

            const checkboxes = Array.from(document.querySelectorAll('.svc-bulk-check'));
            const groupSelectAlls = Array.from(document.querySelectorAll('.svc-group-select-all'));
            const selectAllVisible = document.getElementById('svcSelectAllVisible');
            const bulkBar = document.getElementById('svcBulkActionBar');
            const countBadge = document.getElementById('svcBulkCountBadge');
            const clearBtn = document.getElementById('svcBulkClearBtn');
            const categorySelect = document.getElementById('svcBulkCategorySelect');
            const moveBtn = document.getElementById('svcBulkMoveBtn');
            const moveBtnText = document.getElementById('svcBulkMoveBtnText');

            if (!bulkBar || checkboxes.length === 0) return;

            let lastCheckedIndex = null;

            function getSelectedIds() {
                return checkboxes.filter(cb => cb.checked).map(cb => Number(cb.value));
            }

            function syncBulkUI() {
                const selectedIds = getSelectedIds();
                const count = selectedIds.length;
                const servicesTabActive = document.getElementById('tab-services')?.classList.contains('active');

                checkboxes.forEach(cb => {
                    const card = document.querySelector(`[data-service-card="${cb.value}"]`);
                    if (card) card.classList.toggle('is-selected', cb.checked);
                });

                // Sync per-group "Select all" checkboxes (with indeterminate state)
                groupSelectAlls.forEach(gcb => {
                    const gIdx = gcb.dataset.groupIndex;
                    const groupBoxes = checkboxes.filter(cb => cb.dataset.groupIndex === gIdx);
                    const checkedCount = groupBoxes.filter(cb => cb.checked).length;
                    gcb.checked = groupBoxes.length > 0 && checkedCount === groupBoxes.length;
                    gcb.indeterminate = checkedCount > 0 && checkedCount < groupBoxes.length;
                });

                // Sync toolbar "Select all visible" checkbox
                if (selectAllVisible) {
                    selectAllVisible.checked = checkboxes.length > 0 && count === checkboxes.length;
                    selectAllVisible.indeterminate = count > 0 && count < checkboxes.length;
                }

                if (countBadge) {
                    countBadge.textContent = `${count} ${count === 1 ? 'service' : 'services'} selected`;
                }

                bulkBar.classList.toggle('d-none', count === 0 || !servicesTabActive);

                if (moveBtn && categorySelect) {
                    moveBtn.disabled = count === 0 || categorySelect.value === '';
                }
            }

            window.syncSvcBulkBar = syncBulkUI;

            // Shift+Click range selection across visible service checkboxes
            checkboxes.forEach((cb, idx) => {
                cb.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (e.shiftKey && lastCheckedIndex !== null && lastCheckedIndex !== idx) {
                        const start = Math.min(lastCheckedIndex, idx);
                        const end = Math.max(lastCheckedIndex, idx);
                        const targetState = cb.checked;
                        for (let i = start; i <= end; i++) {
                            checkboxes[i].checked = targetState;
                        }
                    }
                    lastCheckedIndex = idx;
                    syncBulkUI();
                });
            });

            // Per-group Select all
            groupSelectAlls.forEach(gcb => {
                gcb.addEventListener('change', function () {
                    const gIdx = this.dataset.groupIndex;
                    const targetState = this.checked;
                    checkboxes
                        .filter(cb => cb.dataset.groupIndex === gIdx)
                        .forEach(cb => { cb.checked = targetState; });
                    syncBulkUI();
                });
            });

            // Toolbar Select all visible
            selectAllVisible?.addEventListener('change', function () {
                const targetState = this.checked;
                checkboxes.forEach(cb => { cb.checked = targetState; });
                syncBulkUI();
            });

            // Clear selection
            clearBtn?.addEventListener('click', function () {
                checkboxes.forEach(cb => { cb.checked = false; });
                lastCheckedIndex = null;
                if (categorySelect) categorySelect.value = '';
                syncBulkUI();
            });

            categorySelect?.addEventListener('change', syncBulkUI);

            // Submit Bulk Move
            moveBtn?.addEventListener('click', async function () {
                const serviceIds = getSelectedIds();
                const rawTarget = categorySelect ? categorySelect.value : '';
                if (serviceIds.length === 0 || rawTarget === '') return;

                const payloadCategoryId = rawTarget === '__uncategorized__' ? null : Number(rawTarget);

                moveBtn.disabled = true;
                if (moveBtnText) moveBtnText.textContent = 'Moving…';

                try {
                    const res = await fetch('{{ route('services.bulk-category') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            service_ids: serviceIds,
                            category_id: payloadCategoryId,
                        }),
                    });

                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success) {
                        // Immediately sync sidebar badges before reloading to preserve active filter/search
                        if (data.category_counts) {
                            Object.entries(data.category_counts).forEach(([catId, cnt]) => {
                                const b = document.querySelector(`[data-cat-count="${catId}"]`);
                                if (b) b.textContent = String(cnt);
                            });
                        }
                        const uncatBadge = document.querySelector('[data-cat-count="uncategorized"]');
                        if (uncatBadge && typeof data.uncategorized_count === 'number') {
                            uncatBadge.textContent = String(data.uncategorized_count);
                        }
                        const allBadge = document.querySelector('[data-cat-count="all"]');
                        if (allBadge && typeof data.total_count === 'number') {
                            allBadge.textContent = String(data.total_count);
                        }

                        try {
                            sessionStorage.setItem('svcBulkMoveToast', data.message || 'Services updated.');
                        } catch (e) {}

                        window.location.reload();
                        return;
                    }

                    const errMsg = data.errors
                        ? Object.values(data.errors).flat()[0]
                        : (data.message || 'Could not move selected services.');
                    showSvcBulkToast(errMsg, true);
                } catch (err) {
                    showSvcBulkToast('Network error updating categories.', true);
                } finally {
                    if (moveBtnText) moveBtnText.textContent = 'Move Selected';
                    syncBulkUI();
                }
            });
        })();
        @endmoduleEdit
    </script>
@endsection
