@extends('layouts.app')

@section('title', 'Staff Access')

@section('content')
    @php
        $rolesMap = \App\Models\User::ROLES;
        $modulesMap = \App\Models\User::MODULES;
        $rolePresetsMap = \App\Models\User::ROLE_PERMISSION_PRESETS;

        $initialFormStates = [
            'new' => [
                'role' => 'user',
                'permissions' => \App\Models\User::defaultPermissionsForRole('user'),
            ],
        ];

        foreach ($users as $u) {
            $initialFormStates[(string) $u->id] = [
                'role' => (string) $u->role,
                'permissions' => $u->effectivePermissionsMap(),
            ];
        }

        $selectedFilterRole = (string) request('role', '');
        $selectedFilterRoleLabel = $selectedFilterRole !== '' && isset($rolesMap[$selectedFilterRole])
            ? $rolesMap[$selectedFilterRole]
            : 'All Roles';
    @endphp

    <style>
        [x-cloak] { display: none !important; }

        .sa-shell {
            max-width: 1120px;
            margin: 0 auto;
        }
        .sa-nav-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 15px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: #b8a29a;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(229, 184, 168, 0.16);
            transition: all 0.16s ease;
        }
        .sa-nav-pill:hover {
            color: #f8efe6;
            border-color: rgba(229, 184, 168, 0.38);
        }
        .sa-nav-pill.active {
            background: linear-gradient(135deg, #e5b8a8 0%, #d49a89 100%);
            color: #1f1417;
            border-color: transparent;
            box-shadow: 0 6px 16px rgba(229, 184, 168, 0.22);
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
            padding: 18px 22px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .sa-btn-rosegold {
            background: linear-gradient(135deg, #e5b8a8 0%, #d49a89 100%);
            color: #1f1417 !important;
            border: none;
            border-radius: 999px;
            font-weight: 700;
            font-size: 13px;
            padding: 8px 18px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .sa-btn-rosegold:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(229, 184, 168, 0.25);
        }

        .sa-btn-ghost {
            background: rgba(255, 255, 255, 0.04);
            color: #f8efe6;
            border: 1px solid rgba(229, 184, 168, 0.24);
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            padding: 7px 15px;
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

        .sa-inline-chip {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 9px;
            border-radius: 999px;
            background: rgba(212, 163, 115, 0.14);
            color: #d4a373;
        }

        .sa-preset-btn {
            background: rgba(229, 184, 168, 0.1);
            color: #e5b8a8;
            border: 1px solid rgba(229, 184, 168, 0.28);
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            padding: 3px 11px;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .sa-preset-btn:hover {
            background: rgba(229, 184, 168, 0.2);
            color: #f8efe6;
        }

        /* Role Badges */
        .sa-role-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            background: rgba(229, 184, 168, 0.12);
            color: #e5b8a8;
            border: 1px solid rgba(229, 184, 168, 0.22);
        }
        .sa-role-badge.role-admin {
            background: rgba(110, 231, 183, 0.12);
            color: #6ee7b7;
            border-color: rgba(110, 231, 183, 0.28);
        }
        .sa-role-badge.role-marketing_specialist,
        .sa-role-badge.role-social_media_editor {
            background: rgba(212, 163, 115, 0.16);
            color: #d4a373;
            border-color: rgba(212, 163, 115, 0.32);
        }

        /* Dark Espresso Modals & Custom Selects (Zero White/Blue Native Dropdown) */
        .sa-modal .modal-dialog,
        .sa-modal .modal-content,
        .sa-modal .modal-body {
            overflow: visible !important;
        }
        .sa-modal .modal-content {
            background: linear-gradient(180deg, #22161b 0%, #160e12 100%) !important;
            border: 1px solid rgba(229, 184, 168, 0.2) !important;
            color: #f8efe6;
            border-radius: 18px !important;
        }
        .sa-modal .modal-header,
        .sa-modal .modal-footer {
            border-color: rgba(255, 255, 255, 0.06) !important;
        }
        .sa-modal .modal-header .btn-close {
            background-color: #1b1214 !important;
            border: 1px solid rgba(229, 184, 168, 0.24) !important;
            border-radius: 10px !important;
            filter: invert(1) grayscale(100%) brightness(180%);
            opacity: 0.85;
        }
        .sa-modal .modal-header .btn-close:hover {
            border-color: #e5b8a8 !important;
            opacity: 1;
        }
        .sa-modal .form-control,
        .sa-modal .form-select,
        .sa-filter-bar .form-control,
        .sa-filter-bar .form-select {
            background-color: #1b1214 !important;
            color: #f5ebe6 !important;
            border: 1px solid rgba(229, 184, 168, 0.22);
            border-radius: 10px;
            color-scheme: dark;
        }
        .sa-modal .form-select option,
        .sa-filter-bar .form-select option {
            background-color: #1b1214 !important;
            color: #f5ebe6 !important;
        }
        .sa-modal .form-control:focus,
        .sa-filter-bar .form-control:focus {
            border-color: #e5b8a8;
            box-shadow: 0 0 0 3px rgba(229, 184, 168, 0.12);
        }

        .sa-cselect-wrap {
            position: relative;
            width: 100%;
        }
        .sa-cselect-wrap.is-open {
            z-index: 70;
        }
        .sa-cselect-trigger {
            width: 100%;
            min-height: 39px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            background: #1b1214;
            color: #f5ebe6;
            border: 1px solid rgba(229, 184, 168, 0.22);
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            line-height: 1.35;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .sa-cselect-trigger:hover,
        .sa-cselect-wrap.is-open .sa-cselect-trigger {
            border-color: #e5b8a8;
            box-shadow: 0 0 0 3px rgba(229, 184, 168, 0.1);
        }
        .sa-cselect-label {
            min-width: 0;
            flex: 1 1 auto;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sa-cselect-caret {
            color: #e5b8a8;
            font-size: 11px;
            flex-shrink: 0;
        }
        .sa-cselect-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            min-width: 100%;
            max-height: 260px;
            overflow-y: auto;
            background: #1b1214;
            border: 1px solid rgba(229, 184, 168, 0.3);
            border-radius: 12px;
            padding: 6px;
            z-index: 1120;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.68);
        }
        .sa-cselect-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            border: none;
            background: transparent;
            color: #f5ebe6;
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 13px;
            text-align: left;
            cursor: pointer;
        }
        .sa-cselect-option:hover,
        .sa-cselect-option.is-selected {
            background: rgba(229, 184, 168, 0.16);
            color: #f8efe6;
        }

        /* Clean Hairline Module Permission Matrix */
        .sa-perm-table-wrap {
            max-height: 360px;
            overflow-y: auto;
            padding-right: 2px;
        }
        .sa-perm-table th {
            font-size: 11px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #b8a29a !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            padding: 8px 10px;
            position: sticky;
            top: 0;
            background: #1b1115;
            z-index: 2;
        }
        .sa-perm-table td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            padding: 7px 10px;
            font-size: 13px;
        }
        .sa-perm-table tr:last-child td {
            border-bottom: none !important;
        }
        .sa-radio {
            cursor: pointer;
            background-color: #1b1214;
            border-color: rgba(229, 184, 168, 0.35);
        }
        .sa-radio:checked {
            background-color: #e5b8a8 !important;
            border-color: #e5b8a8 !important;
        }
    </style>

    <div class="sa-shell">
        <!-- Top Navigation Pills & Primary CTA -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('staffs.index') }}" class="sa-nav-pill">Team Members</a>
                <a href="{{ route('shifts.index') }}" class="sa-nav-pill">Scheduled Shifts</a>
                <a href="{{ route('users.index') }}" class="sa-nav-pill active">Staff Access</a>
            </div>
            <button type="button" class="sa-btn-rosegold" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bx bx-plus"></i> Add Staff
            </button>
        </div>

        <!-- Inline Filter Row -->
        <form method="GET" action="{{ route('users.index') }}" class="sa-filter-bar mb-4" x-data="staffAccessFilter">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small mb-1" style="color: #b8a29a;">Role</label>
                    <div class="sa-cselect-wrap" :class="{ 'is-open': filterRoleOpen }" @click.outside="filterRoleOpen = false">
                        <input type="hidden" name="role" :value="filterRole">
                        <button type="button" class="sa-cselect-trigger" @click="filterRoleOpen = !filterRoleOpen">
                            <span class="sa-cselect-label" x-text="filterRoleLabel()">{{ $selectedFilterRoleLabel }}</span>
                            <span class="sa-cselect-caret">▾</span>
                        </button>
                        <div class="sa-cselect-menu z-50" x-show="filterRoleOpen" x-cloak>
                            <button type="button" class="sa-cselect-option" :class="{ 'is-selected': filterRole === '' }" @click="selectFilterRole('')">All Roles</button>
                            @foreach ($rolesMap as $rKey => $rLabel)
                                <button type="button" class="sa-cselect-option" :class="{ 'is-selected': filterRole === '{{ $rKey }}' }" @click="selectFilterRole('{{ $rKey }}')">{{ $rLabel }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <label class="form-label small mb-1" style="color: #b8a29a;">Name / Email</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search by name or email">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="sa-btn-rosegold w-100 justify-content-center">Filter</button>
                    <a href="{{ route('users.index') }}" class="sa-btn-ghost w-100 justify-content-center">Reset</a>
                </div>
            </div>
        </form>

        <!-- Staff Access Accounts Table -->
        <div class="sa-card">
            <div class="sa-card-header">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold mb-0" style="color: #f8efe6; font-size: 16px;">Staff Access Accounts</h5>
                    <span class="sa-inline-chip">{{ $users->count() }} Accounts</span>
                </div>
            </div>
            <div class="table-responsive text-nowrap" style="overflow: visible;">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Profile</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td>
                                    @if($user->profile_photo)
                                        <img src="{{ asset($user->profile_photo) }}" alt="Profile" class="rounded-circle" width="38" height="38" style="object-fit: cover;">
                                    @else
                                        <i class="bx bx-user-circle fs-2" style="color: #b8a29a;"></i>
                                    @endif
                                </td>
                                <td class="fw-semibold" style="color: #f8efe6;">{{ $user->name }}</td>
                                <td style="color: #cbb8b0;">{{ $user->email }}</td>
                                <td>
                                    <span class="sa-role-badge role-{{ $user->role }}">
                                        {{ $user->roleBadgeLabel() }}
                                    </span>
                                </td>
                                <td>
                                    @if ($user->is_active)
                                        <span class="badge" style="background: rgba(110, 231, 183, 0.14); color: #6ee7b7; border: 1px solid rgba(110, 231, 183, 0.28);">Active</span>
                                    @else
                                        <span class="badge" style="background: rgba(255, 255, 255, 0.06); color: #b8a29a;">Deactivated</span>
                                    @endif
                                </td>
                                <td style="color: #b8a29a;">{{ $user->created_at->format('d M Y') }}</td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <button type="button" class="sa-btn-ghost py-1 px-2 dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="bx bx-dots-horizontal-rounded"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end" style="background: #1b1214; border: 1px solid rgba(229, 184, 168, 0.24);">
                                            <a class="dropdown-item" style="color: #f5ebe6;" href="javascript:void(0);" data-bs-toggle="modal"
                                                data-bs-target="#editUserModal{{ $user->id }}">
                                                <i class="bx bx-edit-alt me-1"></i> Edit
                                            </a>
                                            @if ($user->id !== auth()->id())
                                                <form action="{{ route('users.toggle-active', $user->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="dropdown-item" style="color: #f5ebe6;" type="submit">
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
                                                    <button class="dropdown-item" style="color: #fda4af;" type="submit">
                                                        <i class="bx bx-trash me-1"></i> Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No staff members found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modals rendered outside table --}}
    @foreach ($users as $user)
        @include('users.edit', ['user' => $user])
    @endforeach

    @include('users.create')

    <script>
        (function () {
            const rolesMap = {{ \Illuminate\Support\Js::from($rolesMap) }};
            const modulesMap = {{ \Illuminate\Support\Js::from($modulesMap) }};
            const rolePresets = {{ \Illuminate\Support\Js::from($rolePresetsMap) }};
            const initialStates = {{ \Illuminate\Support\Js::from($initialFormStates) }};
            const initialFilterRole = {{ \Illuminate\Support\Js::from($selectedFilterRole) }};

            window.__saRolesMap = rolesMap;

            const registerStaffAccessForms = function () {
                if (!window.Alpine || window.__laleenStaffAccessAlpineRegistered) {
                    return;
                }
                window.__laleenStaffAccessAlpineRegistered = true;

                window.Alpine.data('staffAccessFilter', function () {
                    return {
                        filterRoleOpen: false,
                        filterRole: initialFilterRole || '',

                        filterRoleLabel() {
                            return this.filterRole ? (rolesMap[this.filterRole] || this.filterRole) : 'All Roles';
                        },

                        selectFilterRole(roleKey) {
                            this.filterRole = roleKey;
                            this.filterRoleOpen = false;
                        },
                    };
                });

                window.Alpine.data('staffAccessUserForm', function (uid) {
                    const seed = initialStates[String(uid)] || initialStates['new'] || { role: 'user', permissions: {} };

                    return {
                        uid: String(uid),
                        role: seed.role || 'user',
                        permissions: Object.assign({}, seed.permissions || {}),
                        overriddenSlugs: {},
                        roleMenuOpen: false,

                        roleLabel(rKey) {
                            return rolesMap[rKey] || rKey || 'User';
                        },

                        selectRole(newRole) {
                            this.role = newRole;
                            this.roleMenuOpen = false;

                            const preset = rolePresets[newRole] || rolePresets['user'] || {};
                            Object.keys(modulesMap).forEach((slug) => {
                                if (!this.overriddenSlugs[slug]) {
                                    this.permissions[slug] = preset[slug] || 'none';
                                }
                            });
                        },

                        setModulePermission(slug, level) {
                            this.permissions[slug] = level;
                            this.overriddenSlugs[slug] = true;
                        },

                        applyRolePreset() {
                            this.overriddenSlugs = {};
                            const preset = rolePresets[this.role] || rolePresets['user'] || {};
                            Object.keys(modulesMap).forEach((slug) => {
                                this.permissions[slug] = preset[slug] || 'none';
                            });
                        },
                    };
                });
            };

            if (window.Alpine) {
                registerStaffAccessForms();
            } else {
                document.addEventListener('alpine:init', registerStaffAccessForms);
            }
        })();
    </script>
@endsection
