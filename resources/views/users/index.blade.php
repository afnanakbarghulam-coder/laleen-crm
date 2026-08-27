@extends('layouts.app')

@section('title', 'Staff Access')

@section('content')
    <!-- Page Navbar with Add Button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Staff Access</h4>
            <p class="text-muted small mb-0">Provision and manage CRM logins &amp; roles for your team.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bx bx-plus me-1"></i> Add Staff
        </button>
    </div>

    <div class="d-flex gap-2 mb-3">
        <a href="{{ route('staffs.index') }}" class="btn btn-sm btn-outline-secondary">Team members</a>
        <a href="{{ route('shifts.index') }}" class="btn btn-sm btn-outline-secondary">Scheduled shifts</a>
        <a href="{{ route('users.index') }}" class="btn btn-sm btn-dark">Staff Access</a>
    </div>

    <!-- Filter Section -->
    <div class="mb-4">
        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse"
            aria-expanded="false" aria-controls="filterCollapse">
            <i class="bx bx-filter-alt me-1"></i> Filters
        </button>

        <div class="collapse mt-3" id="filterCollapse">
            <div class="card card-body shadow-sm border-0">
                <form method="GET" action="{{ route('users.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="">All Roles</option>
                                <option value="admin" {{ request('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                                <option value="manager" {{ request('role') == 'manager' ? 'selected' : '' }}>Manager</option>
                                <option value="agent" {{ request('role') == 'agent' ? 'selected' : '' }}>Agent</option>
                                <option value="staff" {{ request('role') == 'staff' ? 'selected' : '' }}>Staff</option>
                                <option value="user" {{ request('role') == 'user' ? 'selected' : '' }}>User</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Name / Email</label>
                            <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                                placeholder="Search by name or email">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                            <a href="{{ route('users.index') }}" class="btn btn-secondary w-100">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <h5 class="card-header">All Users</h5>
        <div class="table-responsive text-nowrap">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Profile</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>
                                @if($user->profile_photo)
                                    <img src="{{ asset($user->profile_photo) }}" alt="Profile" class="rounded-circle" width="40" height="40">
                                @else
                                    <i class="bx bx-user fs-2 text-secondary"></i>
                                @endif
                            </td>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td class="text-capitalize">{{ $user->role }}</td>
                            <td>
                                @if ($user->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Deactivated</span>
                                @endif
                            </td>
                            <td>{{ $user->created_at->format('d M Y') }}</td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn p-0 dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal"
                                            data-bs-target="#editUserModal{{ $user->id }}">
                                            <i class="bx bx-edit-alt me-1"></i> Edit
                                        </a>
                                        @if ($user->id !== auth()->id())
                                            <form action="{{ route('users.toggle-active', $user->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button class="dropdown-item" type="submit">
                                                    @if ($user->is_active)
                                                        <i class="bx bx-block me-1"></i> Deactivate
                                                    @else
                                                        <i class="bx bx-check-circle me-1"></i> Reactivate
                                                    @endif
                                                </button>
                                            </form>
                                            <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="d-inline"
                                                onsubmit="return confirm('Permanently delete this staff account? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="dropdown-item text-danger" type="submit">
                                                    <i class="bx bx-trash me-1"></i> Delete
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>

                        @include('users.edit', ['user' => $user])
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">No staff members found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('users.create')
@endsection
