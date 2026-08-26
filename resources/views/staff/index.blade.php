@extends('layouts.app')
@section('title', 'Team Members')

<style>
    .select2-container--open {
        z-index: 1060 !important;
    }

    .team-table thead th {
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--luxe-muted);
        border-bottom: 1px solid var(--luxe-border-strong);
    }

    .team-table td {
        vertical-align: middle;
    }

    .team-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        object-fit: cover;
    }

    .team-name {
        font-weight: 700;
        color: var(--luxe-ink);
    }

    .team-contact a {
        display: block;
        font-size: 12.5px;
        color: var(--luxe-accent);
        text-decoration: none;
    }

    .team-contact .phone {
        color: var(--luxe-muted);
    }

    .team-rating {
        color: var(--luxe-muted);
        font-size: 12.5px;
    }

    .access-badge {
        font-size: 10.5px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
    }

    .access-badge.admin { background: rgba(168, 82, 74, 0.16); color: #d4948c; }
    .access-badge.agent { background: rgba(213, 180, 169, 0.14); color: var(--luxe-accent); }
    .access-badge.staff { background: rgba(142, 168, 138, 0.16); color: #b7cdb3; }
    .access-badge.user { background: var(--luxe-surface-2); color: var(--luxe-muted); }
</style>

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Team members <span class="badge bg-secondary">{{ $staff->count() }}</span></h4>
            <p class="text-muted small mb-0">Manage your staff, their services, and CRM access.</p>
        </div>
        @if (auth()->user()->role === 'admin')
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addStaffModal" onclick="resetStaffForm()">
                <i class="bx bx-plus me-1"></i> Add
            </button>
        @endif
    </div>

    <div class="d-flex gap-2 mb-3">
        <a href="{{ route('staffs.index') }}" class="btn btn-sm btn-dark">Team members</a>
        <a href="{{ route('shifts.index') }}" class="btn btn-sm btn-outline-secondary">Scheduled shifts</a>
    </div>

    <div class="mb-3">
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
            <i class="bx bx-filter-alt me-1"></i> Filters
        </button>

        <div class="collapse mt-3 {{ request()->hasAny(['branch', 'status', 'search']) ? 'show' : '' }}" id="filterCollapse">
            <div class="card card-body shadow-sm border-0">
                <form method="GET" action="{{ route('staffs.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Location</label>
                            <select name="branch" class="form-select">
                                <option value="">All Locations</option>
                                <option value="old_airport" {{ request('branch') == 'old_airport' ? 'selected' : '' }}>Old Airport</option>
                                <option value="wakrah" {{ request('branch') == 'wakrah' ? 'selected' : '' }}>Wakrah</option>
                                <option value="both" {{ request('branch') == 'both' ? 'selected' : '' }}>Both</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All</option>
                                <option value="present" {{ request('status') == 'present' ? 'selected' : '' }}>Present</option>
                                <option value="on-leave" {{ request('status') == 'on-leave' ? 'selected' : '' }}>On Leave</option>
                                <option value="sick" {{ request('status') == 'sick' ? 'selected' : '' }}>Sick</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search team members</label>
                            <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, email or phone">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">Apply</button>
                            <a href="{{ route('staffs.index') }}" class="btn btn-secondary w-100">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table team-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Access</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staff as $member)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img class="team-avatar" src="{{ $member->profile_picture ? asset(str_replace('\\', '/', $member->profile_picture)) : asset('design/sneat-admin-template/assets/img/avatars/1.png') }}">
                                    <div>
                                        <div class="team-name">{{ $member->name }}</div>
                                        <div class="text-muted small">{{ ucwords(str_replace('_', ' ', $member->branch)) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="team-contact">
                                @if ($member->email)
                                    <a href="mailto:{{ $member->email }}">{{ $member->email }}</a>
                                @endif
                                @if ($member->phone)
                                    <span class="phone">{{ $member->phone }}</span>
                                @endif
                                @if (!$member->email && !$member->phone)
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($member->user)
                                    <span class="access-badge {{ $member->user->role }}">{{ ucfirst($member->user->role) }}</span>
                                @else
                                    <span class="text-muted small">No system access</span>
                                @endif
                            </td>
                            <td class="team-rating">No reviews yet</td>
                            <td>
                                @php $todayOff = $member->timeOffs->first(); @endphp
                                @if ($todayOff)
                                    <span class="badge {{ $todayOff->reason === 'sick' ? 'bg-danger' : 'bg-warning' }}">
                                        {{ $todayOff->reason === 'sick' ? 'Sick' : ($todayOff->reason === 'unpaid' ? 'Unpaid leave' : ($todayOff->reason === 'other' ? 'Time off' : 'On leave')) }}
                                    </span>
                                @else
                                    <span class="badge bg-success">Present</span>
                                @endif
                                @if (!$member->bookable)
                                    <span class="badge bg-light text-dark border">Not bookable</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if (auth()->user()->role === 'admin')
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <button type="button" class="dropdown-item edit-btn"
                                                data-staff='@json($member)'
                                                data-service-ids='@json($member->services->pluck("id"))'
                                                data-has-access="{{ $member->user ? 1 : 0 }}"
                                                data-access-role="{{ $member->user->role ?? '' }}"
                                                data-access-email="{{ $member->user->email ?? '' }}">
                                                <i class="bx bx-edit-alt me-1"></i> Edit
                                            </button>
                                            <form action="{{ route('staffs.destroy', $member->id) }}" method="POST"
                                                onsubmit="return confirm('Remove this team member?');">
                                                @csrf @method('DELETE')
                                                <button class="dropdown-item text-danger" type="submit">
                                                    <i class="bx bx-trash me-1"></i> Remove
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No team members found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if (auth()->user()->role === 'admin')
        @include('staff.main-form')

        <script>
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    editStaff(
                        JSON.parse(this.dataset.staff),
                        JSON.parse(this.dataset.serviceIds),
                        this.dataset.hasAccess === '1',
                        this.dataset.accessRole,
                        this.dataset.accessEmail
                    );
                });
            });
        </script>
    @endif
@endsection
