@extends('layouts.app')
@section('title', 'Service Catalog')

<style>
    .svc-cat-rail {
        border: 1px solid #eaecf0;
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
    }

    .svc-cat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 14px;
        font-size: 13.5px;
        color: #344054;
        text-decoration: none;
        border-bottom: 1px solid #f2f4f7;
    }

    .svc-cat-row:hover {
        background: #f9fafb;
        color: #101828;
    }

    .svc-cat-row.active {
        background: #eef2ff;
        color: #3f37c9;
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
        background: #eaecf0;
        color: #475467;
        font-weight: 700;
    }

    .svc-cat-row.active .badge {
        background: #3f37c9;
        color: #fff;
    }

    .svc-group-title {
        font-weight: 800;
        font-size: 16px;
        margin: 22px 0 12px;
        color: #101828;
    }

    .svc-group-title:first-child {
        margin-top: 0;
    }

    .svc-card {
        border: 1px solid #eaecf0;
        border-radius: 10px;
        padding: 14px 16px;
        margin-bottom: 10px;
        background: #fff;
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
        background: #f2f4f7;
    }

    .svc-card .svc-name {
        font-weight: 700;
        font-size: 14.5px;
        color: #101828;
    }

    .svc-card .svc-meta {
        font-size: 12px;
        color: #667085;
    }

    .svc-card .svc-desc {
        font-size: 12.5px;
        color: #667085;
        margin-top: 4px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .svc-card .svc-price {
        font-weight: 700;
        font-size: 15px;
        color: #101828;
        white-space: nowrap;
    }

    .svc-card .treatment-badge {
        display: inline-block;
        background: #f1f2f6;
        border-radius: 999px;
        padding: 2px 10px;
        font-size: 11px;
        font-weight: 600;
        color: #475467;
        margin-top: 6px;
    }
</style>

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Service Catalog</h4>
            <p class="text-muted small mb-0">Manage the services your business offers, organized by category.</p>
        </div>
        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#serviceModal">
            <i class="bx bx-plus me-1"></i> Add Service
        </button>
    </div>

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
            <button type="button" class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#categoryModal">
                <i class="bx bx-plus"></i> Add category
            </button>
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
                        </div>

                        <div class="text-end">
                            <div class="svc-price">{{ number_format($service->price, 2) }} QAR</div>
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
                        </div>
                    </div>
                @endforeach
            @empty
                <div class="text-center text-muted py-5">No services found.</div>
            @endforelse
        </div>
    </div>

    @include('services.main-form')

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
                            <input type="color" name="color" class="form-control form-control-color" value="#3f8cff">
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
    </script>
@endsection
