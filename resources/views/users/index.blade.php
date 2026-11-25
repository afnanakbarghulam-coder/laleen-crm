@extends('layouts.app')

@section('title', 'Roles & Access — Staff Management')

@section('content')
    @php
        $rolesMap = \App\Models\User::ACTIVE_ROLES;
        $legacyRolesMap = \App\Models\User::LEGACY_ROLES;
        $selectedFilterRole = (string) request('role', '');
        $activeView = $activeView ?? 'all';

        $accessUsersMap = [];
        foreach ($users as $u) {
            $accessUsersMap[(string) $u->id] = [
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'email' => (string) $u->email,
                'role' => (string) $u->role,
                'is_active' => (bool) $u->is_active,
                'staff_id' => $u->staff ? (int) $u->staff->id : null,
                'permissions' => $u->effectivePermissionsMap(),
            ];
        }

        $formatBranchLabel = static function (?string $branch, ?string $role = null, bool $bookable = true, bool $isRemote = false): string {
            if ($isRemote) {
                return 'Remote';
            }

            $cleanRole = strtolower(trim((string) $role));
            $raw = strtolower(trim((string) $branch));
            if ($raw === '' || $raw === 'remote' || ($cleanRole === 'agent' && !$bookable)) {
                return 'Remote';
            }

            return match ($raw) {
                'old_airport' => 'Old Airport',
                'wakrah', 'al_wakrah' => 'Al Wakrah',
                'both' => 'Old Airport & Al Wakrah',
                default => 'Remote',
            };
        };

        $formatAccessScope = static function (\App\Models\User $u): string {
            if ($u->isAdmin()) {
                return 'Full CRM Access';
            }
            if ($u->isSalonStaff()) {
                return 'Portal Only (/my-profile)';
            }
            $enabled = collect($u->effectivePermissionsMap())
                ->filter(fn ($lvl) => in_array($lvl, ['view', 'edit'], true))
                ->count();

            return $enabled === 1 ? '1 Module Enabled' : "{$enabled} Modules Enabled";
        };
    @endphp

    <style>
        [x-cloak] { display: none !important; }

        .ra-shell {
            max-width: 1180px;
            margin: 0 auto;
        }

        .ra-view-pills {
            display: inline-flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .ra-view-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            color: #b8a29a;
            background: rgba(34, 22, 27, 0.72);
            border: 1px solid rgba(229, 184, 168, 0.16);
            transition: all 0.15s ease;
        }

        .ra-view-pill:hover {
            color: #f8efe6;
            border-color: rgba(229, 184, 168, 0.34);
        }

        .ra-view-pill.is-active {
            background: rgba(229, 184, 168, 0.16);
            color: #f8efe6;
            border-color: rgba(229, 184, 168, 0.44);
        }

        .ra-view-count {
            font-size: 11px;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            color: #e5b8a8;
        }

        .sa-btn-rosegold {
            background: linear-gradient(135deg, #d98f83, #c19a8c);
            color: #1c110f !important;
            border: none;
            border-radius: 999px;
            font-weight: 700;
            font-size: 13px;
            padding: 8px 18px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: filter 0.15s ease;
        }

        .sa-btn-rosegold:hover { filter: brightness(1.06); }

        .sa-btn-ghost {
            background: rgba(255, 255, 255, 0.04);
            color: #f8efe6;
            border: 1px solid rgba(229, 184, 168, 0.24);
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 600;
            padding: 7px 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            cursor: pointer;
        }

        .sa-btn-ghost:hover {
            background: rgba(229, 184, 168, 0.12);
            color: #f8efe6;
        }

        .sa-card {
            background: linear-gradient(180deg, rgba(34, 22, 27, 0.92) 0%, rgba(21, 14, 18, 0.96) 100%);
            border: 1px solid rgba(229, 184, 168, 0.14);
            border-radius: 16px;
            overflow: visible;
        }

        .sa-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .ra-table thead th {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #9e8780;
            padding: 11px 16px;
            border-bottom: 1px solid rgba(229, 184, 168, 0.14);
            background: rgba(18, 12, 14, 0.35);
        }

        .ra-table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }

        .ra-table tbody tr:last-child td { border-bottom: none; }

        .sa-role-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
            background: rgba(229, 184, 168, 0.12);
            color: #e5b8a8;
            border: 1px solid rgba(229, 184, 168, 0.24);
        }

        .sa-role-badge.role-admin {
            background: rgba(110, 231, 183, 0.12);
            color: #6ee7b7;
            border-color: rgba(110, 231, 183, 0.28);
        }

        .sa-role-badge.role-salon_staff {
            background: rgba(212, 163, 115, 0.16);
            color: #f3d9a4;
            border-color: rgba(212, 163, 115, 0.34);
        }

        .sa-role-badge.role-marketing_specialist,
        .sa-role-badge.role-social_media_editor {
            background: rgba(212, 163, 115, 0.14);
            color: #d4a373;
            border-color: rgba(212, 163, 115, 0.3);
        }

        .ra-status-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
        }

        .ra-status-pill.is-active {
            background: rgba(110, 231, 183, 0.12);
            color: #6ee7b7;
            border: 1px solid rgba(110, 231, 183, 0.28);
        }

        .ra-status-pill.is-disabled {
            background: rgba(253, 164, 175, 0.12);
            color: #fda4af;
            border: 1px solid rgba(253, 164, 175, 0.3);
        }

        .ra-unlinked-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(245, 158, 11, 0.13);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.32);
        }

        .ra-mini-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid rgba(229, 184, 168, 0.28);
            background: rgba(229, 184, 168, 0.08);
            color: #f3dfd7;
            cursor: pointer;
            text-decoration: none;
        }

        .ra-mini-link-btn:hover {
            background: rgba(229, 184, 168, 0.18);
            border-color: #d98f83;
            color: #fff;
        }

        .ra-filter-bar .form-control,
        .ra-filter-bar .form-select {
            background-color: #1b1214 !important;
            color: #f5ebe6 !important;
            border: 1px solid rgba(229, 184, 168, 0.22) !important;
            border-radius: 10px;
            color-scheme: dark;
            height: 40px;
            line-height: 1.4;
        }

        .ra-filter-bar .form-select {
            appearance: none;
            -webkit-appearance: none;
            padding: 0.45rem 2.25rem 0.45rem 0.85rem;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23d4a373' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.8rem center !important;
            background-size: 13px 10px !important;
        }

        .ra-filter-bar .sa-btn-rosegold,
        .ra-filter-bar .sa-btn-ghost {
            height: 40px;
        }

        .ra-filter-bar .form-select option {
            background-color: #1b1214;
            color: #f5ebe6;
        }

        .ra-more-btn::after {
            display: none !important;
        }
    </style>

    <div class="ra-shell">
        {{-- Shared Unified 5-Tab Staff Management Header + Primary + Add Login CTA --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 pt-1 mb-3">
            <div class="laleen-top-pill-wrap">
                @include('staff.partials.top-pill-nav', ['inPlaceTabs' => false, 'activePill' => 'roles_access'])
            </div>
            <button type="button" class="sa-btn-rosegold" onclick="openAddLoginModal()">
                <i class="bx bx-plus"></i> Add Login
            </button>
        </div>

        {{-- Status / View Pills + Filter Bar --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
            <div class="ra-view-pills" role="tablist" aria-label="Account Views">
                <a href="{{ route('users.index', array_filter(['view' => 'all', 'role' => request('role'), 'search' => request('search')])) }}"
                   class="ra-view-pill {{ $activeView === 'all' ? 'is-active' : '' }}">
                    <span>All Accounts</span>
                    <span class="ra-view-count">{{ $allAccountsCount }}</span>
                </a>
                <a href="{{ route('users.index', array_filter(['view' => 'unlinked_staff', 'search' => request('search')])) }}"
                   class="ra-view-pill {{ $activeView === 'unlinked_staff' ? 'is-active' : '' }}">
                    <span>Staff Without Login</span>
                    <span class="ra-view-count">{{ $staffWithoutLoginCount }}</span>
                </a>
                <a href="{{ route('users.index', array_filter(['view' => 'disabled', 'role' => request('role'), 'search' => request('search')])) }}"
                   class="ra-view-pill {{ $activeView === 'disabled' ? 'is-active' : '' }}">
                    <span>Disabled Accounts</span>
                    <span class="ra-view-count">{{ $disabledAccountsCount }}</span>
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('users.index') }}" class="ra-filter-bar mb-4">
            <input type="hidden" name="view" value="{{ $activeView }}">
            <div class="row g-2 align-items-end">
                @if ($activeView !== 'unlinked_staff')
                    <div class="col-md-3">
                        <label class="form-label small mb-1" style="color: #b8a29a;">Role</label>
                        <select name="role" class="form-select">
                            <option value="">All Roles</option>
                            @foreach ($rolesMap as $rKey => $rLabel)
                                <option value="{{ $rKey }}" {{ $selectedFilterRole === $rKey ? 'selected' : '' }}>
                                    {{ $rLabel }}
                                </option>
                            @endforeach
                            @if (\App\Models\User::isLegacyRole($selectedFilterRole))
                                <option value="{{ $selectedFilterRole }}" selected>
                                    {{ $legacyRolesMap[$selectedFilterRole] ?? $selectedFilterRole }} (Legacy)
                                </option>
                            @endif
                        </select>
                    </div>
                @endif
                <div class="{{ $activeView === 'unlinked_staff' ? 'col-md-9' : 'col-md-6' }}">
                    <label class="form-label small mb-1" style="color: #b8a29a;">Name / Email</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search by name or email">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="sa-btn-rosegold w-100 justify-content-center">Filter</button>
                    <a href="{{ route('users.index', ['view' => $activeView]) }}" class="sa-btn-ghost w-100 justify-content-center">Reset</a>
                </div>
            </div>
        </form>

        {{-- VIEW A: STAFF WITHOUT LOGIN --}}
        @if ($activeView === 'unlinked_staff')
            <div class="sa-card">
                <div class="sa-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold mb-0" style="color: #f8efe6; font-size: 16px;">Staff Without Login</h5>
                        <span class="sa-inline-chip">{{ $unlinkedStaff->count() }} Team Members</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table ra-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Team Member</th>
                                <th>Branch &amp; Type</th>
                                <th>Contact</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($unlinkedStaff as $st)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @include('partials.avatar', [
                                                'src' => $st->profile_picture,
                                                'name' => $st->name,
                                                'size' => 38,
                                            ])
                                            <div>
                                                <div class="fw-semibold" style="color: #f8efe6;">{{ $st->name }}</div>
                                                <div class="small" style="color: #9e8780;">
                                                    Suggested: {{ ($st->bookable && !$st->isRemote()) ? 'Salon Staff' : 'Chat Agent' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="color: #f5ebe6; font-size: 13px;">{{ $st->location_label }}</div>
                                        <div class="small" style="color: #9e8780;">{{ ($st->bookable && !$st->isRemote()) ? 'Bookable Specialist' : 'Remote / Operations' }}</div>
                                    </td>
                                    <td>
                                        <div class="small" style="color: #cbb8b0;">{{ $st->email ?: '—' }}</div>
                                        @if ($st->phone)
                                            <div class="small" style="color: #9e8780;">{{ $st->phone }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex align-items-center gap-2">
                                            <a href="{{ route('portal.admin-preview', $st) }}" class="ra-mini-link-btn" title="Preview Portal Profile">
                                                <i class="bx bx-show"></i> Preview
                                            </a>
                                            <button type="button" class="sa-btn-rosegold py-1 px-3" style="font-size: 12px;"
                                                    onclick="openAddLoginModal({ mode: 'existing_staff', staffId: {{ $st->id }} })">
                                                <i class="bx bx-user-plus"></i> Grant Access
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4" style="color: #9e8780;">
                                        Every team member in the Staff Directory already has a login account.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            {{-- VIEW B: ALL ACCOUNTS / DISABLED ACCOUNTS --}}
            <div class="sa-card">
                <div class="sa-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold mb-0" style="color: #f8efe6; font-size: 16px;">
                            {{ $activeView === 'disabled' ? 'Disabled Accounts' : 'Roles & Access Accounts' }}
                        </h5>
                        <span class="sa-inline-chip">{{ $users->count() }} {{ $users->count() === 1 ? 'Account' : 'Accounts' }}</span>
                    </div>
                </div>
                <div class="table-responsive" style="overflow: visible;">
                    <table class="table ra-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Role &amp; Access Scope</th>
                                <th>Linked HR Profile</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $user)
                                @php
                                    $linkedStaff = $user->staff;
                                    $displayRole = $user->role === 'agent' ? 'Chat Agent' : $user->roleBadgeLabel();
                                    $scopeSubtitle = $formatAccessScope($user);
                                    $avatarSource = $linkedStaff?->profile_picture ?: $user->profile_photo;
                                @endphp
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @include('partials.avatar', [
                                                'src' => $avatarSource,
                                                'name' => $user->name,
                                                'size' => 38,
                                            ])
                                            <div class="min-w-0">
                                                <div class="fw-semibold" style="color: #f8efe6;">{{ $user->name }}</div>
                                                <div class="small" style="color: #b8a29a;">{{ $user->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column align-items-start gap-1">
                                            <span class="sa-role-badge role-{{ $user->role }}">
                                                {{ $displayRole }}
                                                @if (\App\Models\User::isLegacyRole($user->role))
                                                    <span class="ms-1" style="opacity:0.78; font-weight:500;">(Legacy)</span>
                                                @endif
                                            </span>
                                            <span class="small" style="color: #9e8780;">{{ $scopeSubtitle }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($linkedStaff)
                                            <div class="d-flex flex-column gap-1">
                                                <div class="fw-medium" style="color: #f5ebe6; font-size: 13px;">
                                                    {{ $linkedStaff->name }}
                                                    <span style="color: #9e8780;">&middot; {{ $linkedStaff->location_label }}</span>
                                                </div>
                                            </div>
                                        @else
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="ra-unlinked-badge">Unlinked</span>
                                                <button type="button" class="ra-mini-link-btn"
                                                        onclick="openManageAccessModal({{ $user->id }}, true)">
                                                    <i class="bx bx-link"></i> Link Profile
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($user->is_active)
                                            <span class="ra-status-pill is-active">Active</span>
                                        @else
                                            <span class="ra-status-pill is-disabled">Disabled</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex align-items-center gap-2">
                                            <button type="button" class="ra-mini-link-btn"
                                                    onclick="openManageAccessModal({{ $user->id }})">
                                                Manage
                                            </button>
                                            <div class="dropdown">
                                                <button type="button" class="sa-btn-ghost ra-more-btn hide-arrow py-1 px-2 dropdown-toggle" data-bs-toggle="dropdown" aria-label="More actions for {{ $user->name }}">
                                                    <i class="bx bx-dots-horizontal-rounded"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end" style="background: #1b1315; border: 1px solid rgba(229, 184, 168, 0.24);">
                                                    <button type="button" class="dropdown-item" style="color: #f5ebe6;"
                                                            onclick="openManageAccessModal({{ $user->id }})">
                                                        <i class="bx bx-slider-alt me-1"></i> Manage Role &amp; Access
                                                    </button>
                                                    @if ($linkedStaff)
                                                        <a class="dropdown-item" style="color: #f3d9a4;" href="{{ route('portal.admin-preview', $linkedStaff) }}">
                                                            <i class="bx bx-show me-1"></i> Preview Portal Profile
                                                        </a>
                                                    @endif
                                                    <button type="button" class="dropdown-item" style="color: #f5ebe6;"
                                                            onclick="openQuickResetPasswordModal({{ $user->id }}, {{ \Illuminate\Support\Js::from($user->name) }}, {{ \Illuminate\Support\Js::from($user->email) }})">
                                                        <i class="bx bx-key me-1"></i> Reset Password
                                                    </button>
                                                    @if ((int) $user->id !== (int) auth()->id())
                                                        <div class="dropdown-divider" style="border-color: rgba(255,255,255,0.08);"></div>
                                                        <form action="{{ route('users.toggle-active', $user->id) }}" method="POST" class="m-0">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button class="dropdown-item" style="color: {{ $user->is_active ? '#fbbf24' : '#6ee7b7' }};" type="submit">
                                                                @if ($user->is_active)
                                                                    <i class="bx bx-block me-1"></i> Disable Account
                                                                @else
                                                                    <i class="bx bx-check-circle me-1"></i> Re-enable Account
                                                                @endif
                                                            </button>
                                                        </form>
                                                        <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="m-0"
                                                              onsubmit="return confirm('Permanently delete this login account? Their HR profile will stay intact.');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="dropdown-item" style="color: #fda4af;" type="submit">
                                                                <i class="bx bx-trash me-1"></i> Delete Account
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4" style="color: #9e8780;">No accounts match the current filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- Shared Unified Add Login, Manage Role & Access, and Reset Password Modals --}}
    @include('users.partials.unified-access-modals', [
        'linkableStaffList' => $allLinkableStaff,
        'accessUsersMap' => $accessUsersMap,
    ])
@endsection
