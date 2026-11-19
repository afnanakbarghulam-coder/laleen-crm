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

    .svc-group-title {
        font-weight: 800;
        font-size: 16px;
        margin: 22px 0 12px;
        color: #e79a91;
    }

    .svc-group-title:first-child {
        margin-top: 0;
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
        .svc-top-tabs, .svc-tab-action, .svc-cat-rail, .btn, form[method="GET"] {
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
    @php
        $initialCatalogTab = in_array(request('tab'), ['services', 'combos', 'freebies'], true) ? request('tab') : 'services';
    @endphp
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Service Catalog</h4>
            <p class="text-muted small mb-0">Manage salon services, multi-visit combos, and Service + Freebies offers.</p>
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
        @endmoduleEdit
    </div>

    <div class="svc-top-tabs">
        <button type="button" class="svc-top-tab {{ $initialCatalogTab === 'services' ? 'active' : '' }}" data-tab="services">Services</button>
        <button type="button" class="svc-top-tab {{ $initialCatalogTab === 'combos' ? 'active' : '' }}" data-tab="combos">Combos</button>
        <button type="button" class="svc-top-tab {{ $initialCatalogTab === 'freebies' ? 'active' : '' }}" data-tab="freebies">Service + Freebies</button>
    </div>

    <!-- TAB: SERVICES -->
    <div class="svc-tab-pane {{ $initialCatalogTab === 'services' ? 'active' : '' }}" id="tab-services">
    <div class="row g-3">
        <div class="col-md-3">
            <div class="svc-cat-rail mb-3">
                <a href="{{ route('services.index') }}" class="svc-cat-row {{ !request('category_id') ? 'active' : '' }}">
                    <span>All categories</span>
                    <span class="badge rounded-pill">{{ $categories->sum('services_count') }}</span>
                </a>
                @foreach ($categories as $cat)
                    <a href="{{ route('services.index', ['category_id' => $cat->id]) }}"
                        class="svc-cat-row {{ request('category_id') == $cat->id ? 'active' : '' }}">
                        <span><span class="cat-dot" style="background:{{ $cat->color }}"></span>{{ $cat->name }}</span>
                        <span class="badge rounded-pill">{{ $cat->services_count }}</span>
                    </a>
                @endforeach
            </div>
            @moduleEdit('services')
                <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="bx bx-plus"></i> Add category
                </button>
            @endmoduleEdit
        </div>

        <div class="col-md-9">
            <form method="GET" action="{{ route('services.index') }}" class="mb-3">
                @if (request('category_id'))
                    <input type="hidden" name="category_id" value="{{ request('category_id') }}">
                @endif
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bx bx-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search service name" value="{{ request('search') }}">
                    <button class="btn btn-outline-secondary" type="submit">Search</button>
                </div>
            </form>

            @forelse ($services as $groupName => $groupServices)
                <div class="svc-group-title">{{ $groupName }}</div>

                @foreach ($groupServices as $service)
                    <div class="svc-card">
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
                            @if ($service->treatment_type)
                                <span class="treatment-badge">{{ $service->treatment_type }}</span>
                            @endif
                            @if ($service->rebooking_interval_days)
                                <span class="treatment-badge"><i class="bx bx-refresh"></i> Re-book every {{ $service->rebooking_interval_days }}d</span>
                            @endif
                        </div>

                        <div class="text-end">
                            <div class="svc-price">{{ number_format($service->price, 2) }} QAR</div>
                            @moduleEdit('services')
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-warning edit-btn"
                                        data-service='@json($service)'
                                        data-staff-ids='@json($service->staff->pluck("id"))'
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
            @empty
                <div class="text-center text-muted py-5">No services found.</div>
            @endforelse
        </div>
    </div>
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

    @moduleEdit('services')
    @include('services.main-form')
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
                const staffIds = JSON.parse(this.dataset.staffIds);
                editService(service, staffIds);
            });
        });

        function editService(service, staffIds) {
            const modalEl = document.getElementById('serviceModal');
            const modal = new bootstrap.Modal(modalEl);

            document.getElementById('modalTitle').innerText = 'Edit Service';
            document.getElementById('serviceForm').action = `/services/${service.id}`;
            document.getElementById('formMethod').value = 'PUT';

            document.getElementById('serviceName').value = service.name;
            document.getElementById('serviceCategory').value = service.category_id || '';
            document.getElementById('serviceTreatmentType').value = service.treatment_type || '';
            document.getElementById('serviceDescription').value = service.description || '';
            document.getElementById('servicePrice').value = service.price;
            document.getElementById('serviceDuration').value = service.duration;
            document.getElementById('serviceRebookingInterval').value = service.rebooking_interval_days || '';

            if (service.photo) {
                const preview = document.getElementById('photoPreview');
                preview.src = `/${service.photo}`;
                preview.classList.remove('d-none');
            }

            modalEl.querySelectorAll('.team-checkbox').forEach(cb => {
                cb.checked = staffIds.includes(Number(cb.value));
            });
            document.getElementById('teamCountBadge').textContent = staffIds.length;

            modal.show();
        }

        /* ---------------- TOP TABS (Services / Combos) ---------------- */
        document.querySelectorAll('.svc-top-tab').forEach(tabBtn => {
            tabBtn.addEventListener('click', function() {
                const tab = this.dataset.tab;

                document.querySelectorAll('.svc-top-tab').forEach(b => b.classList.toggle('active', b === this));
                document.querySelectorAll('.svc-tab-pane').forEach(p => p.classList.toggle('active', p.id === 'tab-' + tab));
                document.querySelectorAll('.svc-tab-action').forEach(b => b.classList.toggle('d-none', b.dataset.tab !== tab));
            });
        });
    </script>
@endsection
