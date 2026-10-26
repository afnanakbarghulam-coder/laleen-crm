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
    .access-badge.manager { background: rgba(185, 142, 163, 0.16); color: #b98ea3; }
    .access-badge.agent { background: rgba(217, 143, 131, 0.14); color: var(--luxe-accent); }
    .access-badge.staff { background: rgba(142, 168, 138, 0.16); color: #b7cdb3; }
    .access-badge.user { background: var(--luxe-surface-2); color: var(--luxe-muted); }

    .staff-quick-add-row td {
        background: rgba(217, 143, 131, 0.06);
        vertical-align: middle;
        padding-top: 6px;
        padding-bottom: 6px;
    }

    .staff-quick-add-row input, .staff-quick-add-row select {
        min-width: 100px;
    }

    .staff-toast-container {
        position: fixed;
        top: 16px;
        right: 16px;
        z-index: 2000;
    }

    .staff-toast {
        background: #241e1c;
        border: 1px solid rgba(217, 143, 131, 0.24);
        color: #e9dfda;
        border-radius: 8px;
        padding: 10px 16px;
        margin-bottom: 8px;
        font-size: 13px;
        box-shadow: 0 4px 14px rgba(0,0,0,.3);
    }

    .staff-toast.error {
        border-color: rgba(168, 82, 74, 0.5);
        color: #e79a91;
    }

    .severity-badge, .status-badge, .ack-badge {
        font-size: 10.5px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        white-space: nowrap;
    }

    .severity-badge.low { background: rgba(142, 168, 138, 0.16); color: #b7cdb3; }
    .severity-badge.medium { background: rgba(217, 172, 89, 0.18); color: #e0b96b; }
    .severity-badge.high { background: rgba(168, 82, 74, 0.16); color: #d4948c; }
    .status-badge.open { background: rgba(168, 82, 74, 0.16); color: #d4948c; }
    .status-badge.resolved { background: rgba(142, 168, 138, 0.16); color: #b7cdb3; }
    .ack-badge.y { background: rgba(142, 168, 138, 0.16); color: #b7cdb3; }
    .ack-badge.n { background: var(--luxe-surface-2); color: var(--luxe-muted); }

    .ref-badge {
        font-family: monospace;
        font-size: 11.5px;
        font-weight: 700;
        color: var(--luxe-accent);
        background: rgba(217, 143, 131, 0.1);
        padding: 2px 6px;
        border-radius: 4px;
        white-space: nowrap;
    }

    .ref-badge-action {
        cursor: pointer;
        border: 1px solid rgba(217, 143, 131, 0.25);
        transition: all .15s ease;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .ref-badge-action:hover {
        background: rgba(217, 143, 131, 0.22);
        border-color: var(--luxe-accent);
        color: #f2e6e2;
    }

    .client-picker {
        position: relative;
    }

    .client-picker-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 60;
        background: #241e1c;
        border: 1px solid rgba(217, 143, 131, 0.24);
        border-radius: 6px;
        max-height: 220px;
        overflow-y: auto;
        margin-top: 2px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, .35);
    }

    .client-picker-result {
        padding: 6px 10px;
        cursor: pointer;
        font-size: 12.5px;
        color: #e9dfda;
    }

    .client-picker-result:hover {
        background: rgba(217, 143, 131, 0.12);
    }

    .client-picker-result .cpr-phone {
        color: var(--luxe-muted);
        font-size: 11px;
        margin-left: 4px;
    }

    .staff-tagselect {
        position: relative;
        min-width: 170px;
    }

    .staff-tagselect-control {
        min-height: 31px;
        border: 1px solid var(--luxe-border-strong);
        border-radius: 6px;
        background: var(--luxe-surface-2);
        padding: 3px 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 4px;
        cursor: pointer;
    }

    .staff-tagselect-control i {
        color: var(--luxe-muted);
        font-size: 16px;
        flex-shrink: 0;
    }

    .staff-tagselect-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        flex: 1;
    }

    .staff-tagselect-placeholder {
        color: var(--luxe-muted);
        font-size: 12.5px;
    }

    .staff-tag-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: rgba(217, 143, 131, 0.16);
        color: var(--luxe-accent);
        border-radius: 999px;
        padding: 1px 6px 1px 9px;
        font-size: 11.5px;
        font-weight: 700;
        white-space: nowrap;
    }

    .staff-tag-remove {
        background: none;
        border: none;
        color: inherit;
        line-height: 1;
        font-size: 14px;
        cursor: pointer;
        padding: 0;
        opacity: .7;
    }

    .staff-tag-remove:hover {
        opacity: 1;
    }

    .staff-tagselect-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 60;
        background: #241e1c;
        border: 1px solid rgba(217, 143, 131, 0.24);
        border-radius: 6px;
        margin-top: 2px;
        padding: 6px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, .35);
        min-width: 200px;
    }

    .staff-tagselect-options {
        max-height: 180px;
        overflow-y: auto;
        margin-top: 6px;
    }

    .staff-tagselect-option {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 5px 6px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12.5px;
        color: #e9dfda;
    }

    .staff-tagselect-option:hover {
        background: rgba(217, 143, 131, 0.12);
    }

    .staff-tagselect-option input[type="checkbox"] {
        margin: 0;
    }

    .staff-tagselect-empty {
        padding: 8px;
        color: var(--luxe-muted);
        font-size: 12px;
    }

    /* ---------------- STAFF WORKSPACE ---------------- */
    .svc-catalog-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 7px 8px;
        border-radius: 6px;
        font-size: 13px;
    }

    .svc-catalog-row:hover {
        background: rgba(217, 143, 131, 0.06);
    }

    .svc-catalog-row .meta {
        font-size: 11px;
        color: var(--luxe-muted);
    }

    .svc-cat-header {
        font-weight: 700;
        font-size: 12.5px;
        color: var(--luxe-ink);
        background: rgba(217, 143, 131, 0.06);
        border-radius: 6px;
    }

    .ws-subtab-pane {
        display: none;
    }

    .ws-subtab-pane.active {
        display: block;
    }

    .ws-staff-picker {
        position: relative;
    }

    .ws-staff-trigger {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        background-color: rgba(20, 16, 14, 0.5);
        border: 1px solid var(--luxe-border-strong);
        border-radius: 8px;
        cursor: pointer;
        transition: border-color .15s ease;
    }

    .ws-staff-trigger:hover {
        border-color: rgba(217, 143, 131, 0.5);
    }

    .ws-staff-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: rgba(185, 142, 163, 0.14);
        color: #b98ea3;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .ws-staff-name {
        font-weight: 700;
        font-size: 13.5px;
        color: var(--luxe-ink);
    }

    .ws-staff-sub {
        font-size: 11.5px;
        color: var(--luxe-muted);
    }

    .ws-staff-panel {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        width: 100%;
        min-width: 260px;
        z-index: 1070;
        background: #1c1715;
        border: 1px solid var(--luxe-border-strong);
        border-radius: 10px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, .35);
        padding: 10px;
        max-height: 360px;
        overflow-y: auto;
    }

    .ws-staff-search-wrap {
        position: relative;
        margin-bottom: 6px;
    }

    .ws-staff-search-wrap i {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--luxe-muted);
        font-size: 14px;
    }

    .ws-staff-search-wrap input {
        width: 100%;
        background-color: #241e1c;
        border: 1px solid rgba(217, 143, 131, 0.2);
        border-radius: 6px;
        padding: 6px 10px 6px 30px;
        color: var(--luxe-ink);
        font-size: 13px;
    }

    .ws-staff-search-wrap input:focus {
        outline: none;
        border-color: var(--luxe-accent);
    }

    .ws-staff-row {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 7px 6px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
    }

    .ws-staff-row:hover {
        background: rgba(217, 143, 131, 0.08);
    }

    .ws-staff-row.active {
        background: rgba(217, 143, 131, 0.14);
    }

    .ws-staff-row-avatar {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: rgba(217, 143, 131, 0.14);
        color: var(--luxe-ink);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11.5px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .ws-staff-row-sub {
        font-size: 11px;
        color: var(--luxe-muted);
    }

    .payroll-summary-row {
        cursor: pointer;
        transition: background-color .15s ease;
    }

    .payroll-summary-row:hover {
        background-color: rgba(217, 143, 131, 0.06);
    }

    .payroll-chevron {
        display: inline-block;
        transition: transform .2s ease;
        color: var(--luxe-muted);
    }

    .payroll-summary-row[aria-expanded="true"] .payroll-chevron {
        transform: rotate(90deg);
        color: var(--luxe-accent);
    }

    .payroll-detail-row > td {
        background: rgba(20, 16, 14, 0.45);
        border-bottom: 1px solid var(--luxe-border-strong);
    }

    .payroll-subcard {
        border: 1px solid rgba(217, 143, 131, 0.16);
        border-radius: 10px;
        background: rgba(36, 30, 28, 0.65);
        padding: 12px 14px;
        height: 100%;
    }

    .btn-download-notice {
        background: rgba(217, 143, 131, 0.14) !important;
        border: 1px solid var(--luxe-accent, #d98f83) !important;
        color: #f2e6e2 !important;
        font-weight: 600;
        padding: 5px 14px;
        border-radius: 999px;
        transition: all .2s ease;
    }

    .btn-download-notice:hover,
    .btn-download-notice:focus {
        background: var(--luxe-accent, #d98f83) !important;
        border-color: var(--luxe-accent, #d98f83) !important;
        color: #241a16 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(217, 143, 131, 0.28);
    }

    /* ---------------- HR & DISCIPLINARY MINIMALISTIC POLISH ---------------- */
    #staff-pane-complaints .hr-filter-bar .form-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--luxe-muted);
        margin-bottom: 3px;
    }

    #staff-pane-complaints .hr-table-card {
        border: 1px solid rgba(217, 143, 131, 0.14) !important;
        border-radius: 12px !important;
        background: rgba(36, 30, 28, 0.5) !important;
        overflow: hidden;
    }

    #staff-pane-complaints .team-table thead th {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--luxe-muted);
        padding: 10px 14px;
        background: rgba(20, 16, 14, 0.35);
        border-bottom: 1px solid rgba(217, 143, 131, 0.16);
    }

    #staff-pane-complaints .team-table > tbody > tr > td {
        padding: 10px 14px;
        font-size: 13px;
        color: var(--luxe-body);
        border-color: rgba(217, 143, 131, 0.1);
    }
</style>

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Team members <span class="badge bg-secondary">{{ $staff->count() }}</span></h4>
            <p class="text-muted small mb-0">Manage your staff, payroll, operations, and CRM access.</p>
        </div>
        @moduleEdit('staff_management')
            <button id="globalAddStaffBtn" class="btn btn-dark {{ $activeTab === 'directory' ? '' : 'd-none' }}" data-bs-toggle="modal" data-bs-target="#addStaffModal" onclick="resetStaffForm()">
                <i class="bx bx-plus me-1"></i> Add
            </button>
        @endmoduleEdit
    </div>

    <div class="d-flex gap-2 mb-3">
        <a href="{{ route('staffs.index') }}" class="btn btn-sm btn-dark">Team members</a>
        <a href="{{ route('shifts.index') }}" class="btn btn-sm btn-outline-secondary">Scheduled shifts</a>
        @if (auth()->user()->isSuperAdmin())
            <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">Staff Access</a>
        @endif
    </div>

    <div class="d-flex gap-2 mb-4 flex-wrap">
        <button type="button" class="staff-tab-btn btn btn-sm {{ $activeTab === 'directory' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="directory">Staff Directory</button>
        <button type="button" class="staff-tab-btn btn btn-sm {{ $activeTab === 'workspace' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="workspace">Staff Workspace</button>
        <button type="button" class="staff-tab-btn btn btn-sm {{ $activeTab === 'payroll' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="payroll">Payroll &amp; Overtime</button>
        <button type="button" class="staff-tab-btn btn btn-sm {{ $activeTab === 'complaints' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="complaints">HR &amp; Disciplinary</button>
    </div>

    {{-- ================= STAFF DIRECTORY ================= --}}
    <div id="staff-pane-directory" class="staff-tab-pane {{ $activeTab === 'directory' ? '' : 'd-none' }}">
        <div class="mb-3">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                <i class="bx bx-filter-alt me-1"></i> Filters
            </button>

            <div class="collapse mt-3 {{ request()->hasAny(['branch', 'status', 'search']) ? 'show' : '' }}" id="filterCollapse">
                <div class="card card-body shadow-sm border-0">
                    <form method="GET" action="{{ route('staffs.index') }}">
                        <input type="hidden" name="tab" value="directory">
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
                                <a href="{{ route('staffs.index', ['tab' => 'directory']) }}" class="btn btn-secondary w-100">Reset</a>
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
                                    @moduleEdit('staff_management')
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <button type="button" class="dropdown-item edit-btn"
                                                    data-staff='@json($member)'
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
                                    @endmoduleEdit
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
    </div>

    {{-- ================= STAFF WORKSPACE ================= --}}
    <div id="staff-pane-workspace" class="staff-tab-pane {{ $activeTab === 'workspace' ? '' : 'd-none' }}">
        @moduleEdit('staff_management')
            <div class="row g-3">
                <div class="col-md-4 col-lg-3">
                    <div class="ws-staff-picker" id="wsStaffPicker">
                        <label class="form-label small text-muted mb-1">Select staff member</label>
                        <div class="ws-staff-trigger" id="wsStaffTrigger">
                            <div class="ws-staff-avatar" id="wsStaffAvatar">?</div>
                            <div class="flex-grow-1">
                                <div class="ws-staff-name" id="wsStaffName">Select a team member</div>
                                <div class="ws-staff-sub" id="wsStaffSub">Click to search</div>
                            </div>
                            <i class="bx bx-chevron-down"></i>
                        </div>

                        <div class="ws-staff-panel d-none" id="wsStaffPanel">
                            <div class="ws-staff-search-wrap">
                                <i class="bx bx-search"></i>
                                <input type="text" id="wsStaffSearch" placeholder="Search team members">
                            </div>
                            <div id="wsStaffResults" class="ws-staff-results"></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8 col-lg-9">
                    <div id="wsNoStaffNotice" class="alert alert-light border small {{ $selectedWorkspaceStaffId ? 'd-none' : '' }}">
                        No team members yet — add one from Staff Directory first.
                    </div>

                    <form method="POST" id="wsForm" class="{{ $selectedWorkspaceStaffId ? '' : 'd-none' }}">
                        @csrf
                        <input type="hidden" name="_method" value="PUT">

                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="ws-subtab-btn btn btn-sm btn-dark" data-ws-pane="services">Services <span class="badge rounded-pill" id="wsServiceCount">0</span></button>
                            <button type="button" class="ws-subtab-btn btn btn-sm btn-outline-secondary" data-ws-pane="location">Location</button>
                            <button type="button" class="ws-subtab-btn btn btn-sm btn-outline-secondary" data-ws-pane="settings">Settings</button>
                        </div>

                        <!-- SERVICES -->
                        <div class="ws-subtab-pane active" id="ws-services">
                            <h6 class="fw-bold">Services</h6>
                            <p class="text-muted small">Choose the services this team member provides. This controls who shows up as available on the booking calendar.</p>

                            <input type="text" id="wsServiceSearch" class="form-control form-control-sm mb-2" placeholder="Search services">

                            <div class="svc-catalog-row svc-cat-header">
                                <label class="mb-0 flex-grow-1"><input type="checkbox" id="wsAllServices" class="form-check-input me-2">All services</label>
                            </div>

                            <div id="wsServiceList" style="max-height:360px; overflow-y:auto;">
                                @foreach ($categories as $cat)
                                    <div class="svc-cat-block" data-cat-name="{{ strtolower($cat->name) }}">
                                        <div class="svc-catalog-row svc-cat-header mt-2">
                                            <label class="mb-0 flex-grow-1">
                                                <input type="checkbox" class="form-check-input me-2 ws-cat-checkbox" data-cat="{{ $cat->id }}">
                                                {{ $cat->name }} <span class="text-muted">({{ $cat->services->count() }})</span>
                                            </label>
                                        </div>
                                        @foreach ($cat->services as $svc)
                                            <div class="svc-catalog-row" data-svc-name="{{ strtolower($svc->name) }}" data-cat="{{ $cat->id }}">
                                                <label class="mb-0 flex-grow-1">
                                                    <input type="checkbox" name="service_ids[]" value="{{ $svc->id }}" class="form-check-input me-2 ws-svc-checkbox" data-cat="{{ $cat->id }}">
                                                    {{ $svc->name }}
                                                    <span class="meta">{{ $svc->duration }}min</span>
                                                </label>
                                                <span>{{ number_format($svc->price, 2) }} QAR</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach

                                @php $wsUncategorized = $services->whereNull('category_id'); @endphp
                                @if ($wsUncategorized->count())
                                    <div class="svc-cat-block" data-cat-name="uncategorized">
                                        <div class="svc-catalog-row svc-cat-header mt-2">
                                            <span>Uncategorized</span>
                                        </div>
                                        @foreach ($wsUncategorized as $svc)
                                            <div class="svc-catalog-row" data-svc-name="{{ strtolower($svc->name) }}">
                                                <label class="mb-0 flex-grow-1">
                                                    <input type="checkbox" name="service_ids[]" value="{{ $svc->id }}" class="form-check-input me-2 ws-svc-checkbox">
                                                    {{ $svc->name }}
                                                    <span class="meta">{{ $svc->duration }}min</span>
                                                </label>
                                                <span>{{ number_format($svc->price, 2) }} QAR</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- LOCATION -->
                        <div class="ws-subtab-pane" id="ws-location">
                            <h6 class="fw-bold">Location</h6>
                            <p class="text-muted small">Which branch does this team member work from?</p>

                            <select name="branch" id="wsBranch" class="form-select" style="max-width: 320px;" required>
                                <option value="old_airport">Old Airport</option>
                                <option value="wakrah">Wakrah</option>
                                <option value="both">Both</option>
                            </select>
                        </div>

                        <!-- SETTINGS -->
                        <div class="ws-subtab-pane" id="ws-settings">
                            <h6 class="fw-bold">Settings</h6>
                            <p class="text-muted small">Calendar bookability</p>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="bookable" id="wsBookable" value="1">
                                <label class="form-check-label" for="wsBookable">
                                    Bookable on the calendar
                                    <div class="text-muted small">Turn off for staff who don't provide services directly (e.g. receptionists, managers)</div>
                                </label>
                            </div>

                            <div class="alert alert-light border small mb-0">
                                <i class="bx bx-info-circle me-1"></i>
                                Working hours and time off are managed in
                                <a href="{{ route('shifts.index') }}">Scheduled shifts</a>.
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-dark">Save Workspace</button>
                        </div>
                    </form>
                </div>
            </div>
        @endmoduleEdit
    </div>

    {{-- ================= PAYROLL & OVERTIME ================= --}}
    <div id="staff-pane-payroll" class="staff-tab-pane {{ $activeTab === 'payroll' ? '' : 'd-none' }}">
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h6 class="fw-bold mb-1">Payroll &amp; Wage Settings</h6>
                    <p class="text-muted small mb-0">Net Salary = Base Salary + Overtime Pay &minus; Deductions. Click any staff row to expand their overtime entries and deductions for the selected date range.</p>
                </div>
                @moduleEdit('staff_management')
                    <button type="button" class="btn btn-primary btn-sm" onclick="openLogOvertimeModal()">
                        <i class="bx bx-plus me-1"></i> Log Overtime
                    </button>
                @endmoduleEdit
            </div>

            <form method="GET" action="{{ route('staffs.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="payroll">
                <div class="col-md-3">
                    <label class="form-label">Branch</label>
                    <select name="payroll_branch" class="form-select">
                        <option value="">Both branches</option>
                        <option value="old_airport" {{ $payrollBranch === 'old_airport' ? 'selected' : '' }}>Old Airport</option>
                        <option value="wakrah" {{ $payrollBranch === 'wakrah' ? 'selected' : '' }}>Al Wakrah</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="payroll_from" class="form-control" value="{{ $payrollFrom->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="payroll_to" class="form-control" value="{{ $payrollTo->format('Y-m-d') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                    <a href="{{ route('staffs.index', ['tab' => 'payroll']) }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>

        <div class="card mb-4">
            <div class="table-responsive">
                <table class="table team-table align-middle mb-0" id="payrollAccordionTable">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th class="text-end">Base Salary</th>
                            <th class="text-end">Overtime Hours</th>
                            <th class="text-end">Overtime Pay</th>
                            <th class="text-end">Deductions</th>
                            <th class="text-end">Net Salary</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payrollRows as $row)
                            @php
                                $rowOtEntries = $row['overtime_entries'] ?? collect();
                                $rowDedEntries = $row['deduction_entries'] ?? collect();
                            @endphp
                            <tr class="payroll-summary-row" data-bs-toggle="collapse" data-bs-target="#payroll-details-{{ $row['staff_id'] }}" aria-expanded="false" aria-controls="payroll-details-{{ $row['staff_id'] }}">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bx bx-chevron-right payroll-chevron me-2"></i>
                                        <div>
                                            <div class="team-name">{{ $row['name'] }}</div>
                                            <div class="text-muted small">{{ ucwords(str_replace('_', ' ', $row['branch'])) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap">
                                    QAR {{ number_format($row['base_salary'], 2) }}
                                    @moduleEdit('staff_management')
                                        <button type="button" class="btn btn-sm btn-link text-warning p-0 ms-1"
                                            title="Quick-edit wages &amp; commission"
                                            onclick='event.stopPropagation(); openEditWagesModal({{ $row["staff_id"] }}, @json($row["name"]), {{ (float) $row["base_salary"] }}, {{ (float) $row["hourly_wage"] }}, {{ (float) $row["commission_rate"] }})'>
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    @endmoduleEdit
                                </td>
                                <td class="text-end">{{ number_format($row['overtime_hours'], 2) }}</td>
                                <td class="text-end">QAR {{ number_format($row['overtime_pay'], 2) }}</td>
                                <td class="text-end text-danger">- QAR {{ number_format($row['deductions'], 2) }}</td>
                                <td class="text-end fw-bold">QAR {{ number_format($row['net_salary'], 2) }}</td>
                            </tr>
                            <tr class="collapse payroll-detail-row" id="payroll-details-{{ $row['staff_id'] }}">
                                <td colspan="6" class="p-3">
                                    <div class="row g-3">
                                        <div class="col-lg-6">
                                            <div class="payroll-subcard">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="fw-bold small text-uppercase text-muted">
                                                        <i class="bx bx-time-five me-1"></i> Overtime Entries ({{ $payrollFrom->format('d M Y') }} &ndash; {{ $payrollTo->format('d M Y') }})
                                                    </span>
                                                    @moduleEdit('staff_management')
                                                        <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2"
                                                            onclick="openLogOvertimeModal({{ $row['staff_id'] }})">
                                                            <i class="bx bx-plus"></i> Add Overtime
                                                        </button>
                                                    @endmoduleEdit
                                                </div>
                                                @if ($rowOtEntries->count())
                                                    <div class="table-responsive">
                                                        <table class="table table-sm mb-0 align-middle">
                                                            <thead>
                                                                <tr>
                                                                    <th>Date</th>
                                                                    <th class="text-end">Hours</th>
                                                                    <th class="text-end">Rate</th>
                                                                    <th class="text-end">Pay</th>
                                                                    <th>Customer</th>
                                                                    <th>Note</th>
                                                                    <th></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($rowOtEntries as $entry)
                                                                    <tr data-ot-row="{{ $entry->id }}">
                                                                        <td class="text-nowrap">{{ $entry->entry_date->format('d M Y') }}</td>
                                                                        <td class="text-end">{{ number_format($entry->hours, 2) }}</td>
                                                                        <td class="text-end">{{ $entry->rate !== null ? 'QAR ' . number_format($entry->rate, 2) : 'Default' }}</td>
                                                                        <td class="text-end fw-semibold">QAR {{ number_format($entry->pay(), 2) }}</td>
                                                                        <td class="small">{{ $entry->customer_name ?: '—' }}</td>
                                                                        <td class="small text-muted">{{ $entry->note ?: '—' }}</td>
                                                                        <td class="text-end">
                                                                            @moduleEdit('staff_management')
                                                                                <button type="button" class="btn btn-sm btn-link text-danger p-0"
                                                                                    onclick="deleteOt({{ $entry->id }})" title="Delete overtime entry">
                                                                                    <i class="bi bi-trash"></i>
                                                                                </button>
                                                                            @endmoduleEdit
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <p class="text-muted small mb-0">No overtime entries for {{ $row['name'] }} in this period.</p>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="col-lg-6">
                                            <div class="payroll-subcard">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="fw-bold small text-uppercase text-muted">
                                                        <i class="bx bx-minus-circle me-1"></i> Deductions ({{ $payrollFrom->format('d M Y') }} &ndash; {{ $payrollTo->format('d M Y') }})
                                                    </span>
                                                    <span class="small text-danger fw-semibold">Total: - QAR {{ number_format($row['deductions'], 2) }}</span>
                                                </div>
                                                @if ($rowDedEntries->count())
                                                    <div class="table-responsive">
                                                        <table class="table table-sm mb-0 align-middle">
                                                            <thead>
                                                                <tr>
                                                                    <th>Date</th>
                                                                    <th class="text-end">Amount</th>
                                                                    <th>Reason</th>
                                                                    <th>Complaint Ref</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($rowDedEntries as $deduction)
                                                                    <tr>
                                                                        <td class="text-nowrap">{{ $deduction->deduction_date->format('d M Y') }}</td>
                                                                        <td class="text-end text-danger fw-semibold">- QAR {{ number_format($deduction->amount, 2) }}</td>
                                                                        <td>{{ $deduction->reason }}</td>
                                                                        <td>
                                                                            @if ($deduction->complaint)
                                                                                <span class="ref-badge">{{ $deduction->complaint->reference_number }}</span>
                                                                            @else
                                                                                <span class="text-muted small">—</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <p class="text-muted small mb-0">No deductions recorded for {{ $row['name'] }} in this period.</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No active staff match this branch filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ================= HR & DISCIPLINARY (COMPLAINTS, DEDUCTIONS & NOTICES) ================= --}}
    <div id="staff-pane-complaints" class="staff-tab-pane {{ $activeTab === 'complaints' ? '' : 'd-none' }}">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <h6 class="fw-bold mb-0">Complaints &amp; Feedback</h6>
                <p class="text-muted small mb-0">Click any incident row to expand its Full Incident Detail, What Happened &amp; Corrective Action, and HR Resolution panel.</p>
            </div>
            @moduleEdit('staff_management')
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#logComplaintModal">
                    <i class="bx bx-plus me-1"></i> Log Complaint
                </button>
            @endmoduleEdit
        </div>

        <form method="GET" action="{{ route('staffs.index') }}" id="complaintsFilterForm" class="row g-2 align-items-end hr-filter-bar mb-2">
            <input type="hidden" name="tab" value="complaints">
            @if ($deductionsDate)
                <input type="hidden" name="deductions_date" value="{{ $deductionsDate }}">
            @endif
            @if ($deductionsFrom)
                <input type="hidden" name="deductions_from" value="{{ $deductionsFrom }}">
            @endif
            @if ($deductionsTo)
                <input type="hidden" name="deductions_to" value="{{ $deductionsTo }}">
            @endif

            <div class="col-sm-6 col-md-3">
                <label class="form-label">Search Ref # / Client</label>
                <input type="text" name="complaints_search" id="complaintsSearchInput" class="form-control form-control-sm" value="{{ $complaintsSearch }}" placeholder="e.g. CMP-2026-001">
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label">Staff member</label>
                <select name="complaints_staff" class="form-select form-select-sm">
                    <option value="">All staff</option>
                    @foreach ($allStaff as $s)
                        <option value="{{ $s->id }}" {{ $complaintsStaffFilter === $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4 col-md-2">
                <label class="form-label">From Date</label>
                <input type="date" name="complaints_from" class="form-control form-control-sm" value="{{ $complaintsFrom ?: $complaintsDate }}">
            </div>
            <div class="col-sm-4 col-md-2">
                <label class="form-label">To Date</label>
                <input type="date" name="complaints_to" class="form-control form-control-sm" value="{{ $complaintsTo ?: $complaintsDate }}">
            </div>
            <div class="col-sm-4 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                @if ($complaintsStaffFilter || $complaintsSearch || $complaintsDate || $complaintsFrom || $complaintsTo)
                    <a href="{{ route('staffs.index', array_filter(['tab' => 'complaints', 'deductions_date' => $deductionsDate, 'deductions_from' => $deductionsFrom, 'deductions_to' => $deductionsTo])) }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
                @endif
            </div>
        </form>

        <div class="card hr-table-card p-0 mb-2">
            <div class="table-responsive">
                <table class="table team-table mb-0" id="complaintsTable">
                    <thead>
                        <tr>
                            <th style="min-width:125px;">Ref #</th>
                            <th style="min-width:140px;">Date &amp; Time</th>
                            <th style="min-width:120px;">Branch</th>
                            <th style="min-width:200px;">Client</th>
                            <th style="min-width:150px;">Service Received</th>
                            <th style="min-width:150px;">Category</th>
                            <th style="min-width:150px;">Staff Involved</th>
                            <th style="min-width:120px;">Deduction</th>
                            <th style="min-width:200px;">What Happened</th>
                            <th style="min-width:130px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($complaints as $complaint)
                            @php
                                $incidentDeductions = $complaint->deductions ?? collect();
                                $incidentNotices = $complaint->notices ?? collect();
                                $isResolved = $complaint->is_staff_mistake !== null || $incidentNotices->count() > 0;
                                $whatHappenedText = $complaint->what_happened ?: $complaint->description;
                                $clientDisplayName = $complaint->customer->name ?? $complaint->customer_name ?: '—';
                                $serviceDisplayName = $complaint->service->name ?? '—';
                                $apptDateDisplay = $complaint->complaint_date->format('d M Y');
                                $noticePayloads = $incidentNotices->map(fn ($n) => array_merge($n->toEditPayload(), [
                                    'client_name' => $clientDisplayName,
                                    'service_received' => $serviceDisplayName,
                                    'appt_date' => $apptDateDisplay,
                                ]))->values();
                            @endphp
                            <tr class="payroll-summary-row" data-entry-row="{{ $complaint->id }}" data-entry='@json($complaint->toEditPayload())'
                                data-bs-toggle="collapse" data-bs-target="#complaint-details-{{ $complaint->id }}" aria-expanded="false" aria-controls="complaint-details-{{ $complaint->id }}">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bx bx-chevron-right payroll-chevron me-1"></i>
                                        <div>
                                            <span class="ref-badge">{{ $complaint->reference_number }}</span>
                                            @if ($isResolved)
                                                <div class="mt-1"><span class="status-badge resolved">Resolved</span></div>
                                            @else
                                                <div class="mt-1"><span class="status-badge open">Pending HR</span></div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $complaint->complaint_date->format('d M Y') }}<div class="text-muted small">{{ $complaint->timeLabel() }}</div></td>
                                <td>{{ \App\Models\StaffComplaint::BRANCHES[$complaint->branch] ?? $complaint->branch }}</td>
                                <td>{{ $complaint->customer_name }}<div class="text-muted small">{{ $complaint->customer_phone ?: '—' }}</div></td>
                                <td>{{ $complaint->service->name ?? '—' }}</td>
                                <td>{{ $complaint->category }}</td>
                                <td class="fw-semibold">{{ $complaint->staffMembers->pluck('name')->implode(', ') ?: '—' }}</td>
                                <td>
                                    @if ($complaint->deduction_applied === 'Y')
                                        <span class="status-badge open">QAR {{ number_format($complaint->deduction_amount, 2) }}</span>
                                    @else
                                        <span class="text-muted small">No</span>
                                    @endif
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit($whatHappenedText, 70) }}</td>
                                <td class="text-end" onclick="event.stopPropagation();">
                                    @moduleEdit('staff_management')
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="event.stopPropagation(); openEditComplaintModal({{ $complaint->id }})" title="Edit What Happened &amp; Corrective Action"><i class="bi bi-pencil-square"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); deleteCo({{ $complaint->id }})" title="Delete"><i class="bi bi-trash"></i></button>
                                        </div>
                                    @endmoduleEdit
                                </td>
                            </tr>
                            <tr class="collapse payroll-detail-row" id="complaint-details-{{ $complaint->id }}">
                                <td colspan="10" class="p-3">
                                    <div class="row g-3">
                                        <!-- Section 1 (Left): Full Incident Detail (static meta-data) -->
                                        <div class="col-lg-3">
                                            <div class="payroll-subcard">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span class="fw-bold small text-uppercase text-muted">
                                                        <i class="bx bx-info-circle me-1"></i> Full Incident Detail
                                                    </span>
                                                    <span class="ref-badge">{{ $complaint->reference_number }}</span>
                                                </div>
                                                <div class="small d-flex flex-column gap-2">
                                                    <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1">
                                                        <span class="text-muted">Client</span>
                                                        <span class="fw-semibold text-end">{{ $complaint->customer_name }} <span class="text-muted">({{ $complaint->customer_phone ?: '—' }})</span></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1">
                                                        <span class="text-muted">Service</span>
                                                        <span class="fw-semibold text-end">{{ $complaint->service->name ?? '—' }}</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1">
                                                        <span class="text-muted">Category</span>
                                                        <span class="fw-semibold text-end">{{ $complaint->category }}</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between border-bottom border-secondary border-opacity-25 pb-1">
                                                        <span class="text-muted">Branch &amp; Date</span>
                                                        <span class="text-end">{{ \App\Models\StaffComplaint::BRANCHES[$complaint->branch] ?? $complaint->branch }} &bull; {{ $complaint->complaint_date->format('d M Y') }}</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-muted">Staff Involved</span>
                                                        <span class="fw-semibold text-end">{{ $complaint->staffMembers->pluck('name')->implode(', ') ?: '—' }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Section 2 (Middle): What Happened & Corrective Action -->
                                        <div class="col-lg-4">
                                            <div class="payroll-subcard d-flex flex-column gap-3">
                                                <div>
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="fw-bold small text-uppercase text-muted">
                                                            <i class="bx bx-message-square-detail me-1"></i> What Happened
                                                        </span>
                                                        @moduleEdit('staff_management')
                                                            <button type="button" class="btn btn-sm btn-link text-warning p-0"
                                                                onclick="openEditComplaintModal({{ $complaint->id }})" title="Edit What Happened &amp; Corrective Action">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </button>
                                                        @endmoduleEdit
                                                    </div>
                                                    <div class="p-2 rounded border border-secondary border-opacity-25 small" style="white-space: pre-wrap; background: rgba(20, 16, 14, 0.35);">{{ $whatHappenedText ?: '—' }}</div>
                                                </div>
                                                <div>
                                                    <div class="fw-bold small text-uppercase text-muted mb-1">
                                                        <i class="bx bx-check-shield me-1"></i> Corrective Action to be taken
                                                    </div>
                                                    <div class="p-2 rounded border border-secondary border-opacity-25 small" style="white-space: pre-wrap; background: rgba(20, 16, 14, 0.35);">{{ $complaint->corrective_action ?: '—' }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Section 3 (Right): HR Resolution (Per-Staff Grid) -->
                                        <div class="col-lg-5">
                                            <div class="payroll-subcard" x-data="{ editMode: {{ $complaint->is_resolved ? 'false' : 'true' }} }">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <span class="fw-bold small text-uppercase text-muted">
                                                        <i class="bx bx-gavel me-1"></i> HR Resolution
                                                    </span>
                                                    <div class="d-flex align-items-center gap-2">
                                                        @if ($complaint->is_resolved)
                                                            <span class="status-badge resolved">Resolved</span>
                                                        @else
                                                            <span class="status-badge open">Action Required</span>
                                                        @endif
                                                        @moduleEdit('staff_management')
                                                            <button type="button" class="btn btn-sm btn-link text-warning p-0"
                                                                x-show="!editMode" x-cloak
                                                                @click="editMode = true"
                                                                title="Edit HR Resolution">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </button>
                                                        @endmoduleEdit
                                                    </div>
                                                </div>

                                                @php
                                                    $initialStaffState = [];
                                                    foreach ($complaint->staff as $staffMember) {
                                                        $staffDed = $incidentDeductions->firstWhere('staff_id', $staffMember->id);
                                                        $staffNotice = $incidentNotices->firstWhere('staff_id', $staffMember->id);
                                                        $hasDed = $staffDed && $staffDed->amount > 0;
                                                        $hasNotice = (bool) $staffNotice;
                                                        $initialStaffState[$staffMember->id] = [
                                                            'mistake' => ($hasDed || $hasNotice || $complaint->is_staff_mistake) ? 'yes' : 'no',
                                                            'deduction' => $hasDed ? 'yes' : 'no',
                                                            'deduction_amount' => $hasDed ? (float) $staffDed->amount : '',
                                                            'notice' => $hasNotice,
                                                            'corrective_action' => $staffNotice ? ($staffNotice->corrective_actions ?? '') : '',
                                                        ];
                                                    }
                                                @endphp

                                                <!-- Read-Only Saved State -->
                                                <div x-show="!editMode" x-cloak class="small d-flex flex-column gap-2">
                                                    @forelse ($complaint->staff as $staff)
                                                        @php
                                                            $staffDed = $incidentDeductions->firstWhere('staff_id', $staff->id);
                                                            $staffNotice = $incidentNotices->firstWhere('staff_id', $staff->id);
                                                            $hasDed = $staffDed && $staffDed->amount > 0;
                                                            $hasNotice = (bool) $staffNotice;
                                                            $staffMistake = ($hasDed || $hasNotice || $complaint->is_staff_mistake);
                                                        @endphp
                                                        <div class="p-2 rounded border border-secondary border-opacity-25" style="background: rgba(20, 16, 14, 0.35);">
                                                            <div class="fw-semibold mb-1">{{ $staff->name }}</div>
                                                            <div class="text-muted">
                                                                Mistake: <span class="fw-semibold {{ $staffMistake ? 'text-warning' : 'text-body' }}">{{ $staffMistake ? 'Yes' : 'No' }}</span>
                                                                | Deduction: <span class="fw-semibold {{ $hasDed ? 'text-danger' : 'text-body' }}">{{ $hasDed ? 'QAR ' . number_format($staffDed->amount, 2) : 'None' }}</span>
                                                                | Notice: <span class="fw-semibold {{ $hasNotice ? 'text-info' : 'text-body' }}">{{ $hasNotice ? 'Issued' : 'None' }}</span>
                                                            </div>
                                                            @if ($hasNotice && !empty($staffNotice->corrective_actions))
                                                                <div class="text-muted mt-1">
                                                                    Corrective Action: <span class="text-body">{{ $staffNotice->corrective_actions }}</span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @empty
                                                        <p class="text-muted small mb-0">No staff members attached to this complaint.</p>
                                                    @endforelse

                                                    @if ($incidentNotices->count())
                                                        <div class="mt-1">
                                                            <a href="javascript:void(0)" class="btn btn-sm btn-outline-info w-100"
                                                                onclick='openViewNoticeModal(@json($complaint->reference_number), @json($noticePayloads))'>
                                                                <i class="bi bi-file-earmark-text me-1"></i> View Generated Staff Notice{{ $incidentNotices->count() > 1 ? 's (' . $incidentNotices->count() . ')' : '' }}
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- Interactive Edit Form -->
                                                <div x-show="editMode" x-cloak>
                                                    @moduleEdit('staff_management')
                                                        <form action="{{ route('complaints.resolve', $complaint->id) }}" method="POST" x-data="hrResolution(@js($initialStaffState))">
                                                            @csrf
                                                            <div class="d-flex flex-column gap-2 mb-3">
                                                                @forelse ($complaint->staff as $staff)
                                                                    <div class="p-2 rounded border border-secondary border-opacity-25" style="background: rgba(20, 16, 14, 0.35);">
                                                                        <div class="row g-2 align-items-start">
                                                                            <!-- Staff Name -->
                                                                            <div class="col-md-3">
                                                                                <label class="form-label small text-muted mb-1">Staff Member</label>
                                                                                <div class="fw-semibold small pt-1">{{ $staff->name }}</div>
                                                                            </div>

                                                                            <!-- Pillar 1: Staff Mistake -->
                                                                            <div class="col-md-3">
                                                                                <label class="form-label small text-muted mb-1 d-block">Staff Mistake</label>
                                                                                <div class="d-flex gap-2 align-items-center pt-1">
                                                                                    <div class="form-check form-check-inline mb-0 me-1">
                                                                                        <input class="form-check-input" type="radio"
                                                                                            name="staff[{{ $staff->id }}][mistake]"
                                                                                            id="mistakeYes-{{ $complaint->id }}-{{ $staff->id }}"
                                                                                            value="yes"
                                                                                            x-model="staff[{{ $staff->id }}].mistake">
                                                                                        <label class="form-check-label small" for="mistakeYes-{{ $complaint->id }}-{{ $staff->id }}">Yes</label>
                                                                                    </div>
                                                                                    <div class="form-check form-check-inline mb-0">
                                                                                        <input class="form-check-input" type="radio"
                                                                                            name="staff[{{ $staff->id }}][mistake]"
                                                                                            id="mistakeNo-{{ $complaint->id }}-{{ $staff->id }}"
                                                                                            value="no"
                                                                                            x-model="staff[{{ $staff->id }}].mistake">
                                                                                        <label class="form-check-label small" for="mistakeNo-{{ $complaint->id }}-{{ $staff->id }}">No</label>
                                                                                    </div>
                                                                                </div>
                                                                            </div>

                                                                            <!-- Pillar 2: Deduction (strictly shown when Mistake == 'yes') -->
                                                                            <div class="col-md-3" x-show="staff[{{ $staff->id }}].mistake == 'yes'" x-cloak>
                                                                                <label class="form-label small text-muted mb-1 d-block">Deduction</label>
                                                                                <div class="d-flex gap-2 align-items-center pt-1">
                                                                                    <div class="form-check form-check-inline mb-0 me-1">
                                                                                        <input class="form-check-input" type="radio"
                                                                                            name="staff[{{ $staff->id }}][deduction]"
                                                                                            id="dedYes-{{ $complaint->id }}-{{ $staff->id }}"
                                                                                            value="yes"
                                                                                            x-model="staff[{{ $staff->id }}].deduction">
                                                                                        <label class="form-check-label small" for="dedYes-{{ $complaint->id }}-{{ $staff->id }}">Yes</label>
                                                                                    </div>
                                                                                    <div class="form-check form-check-inline mb-0">
                                                                                        <input class="form-check-input" type="radio"
                                                                                            name="staff[{{ $staff->id }}][deduction]"
                                                                                            id="dedNo-{{ $complaint->id }}-{{ $staff->id }}"
                                                                                            value="no"
                                                                                            x-model="staff[{{ $staff->id }}].deduction">
                                                                                        <label class="form-check-label small" for="dedNo-{{ $complaint->id }}-{{ $staff->id }}">No</label>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="mt-2" x-show="staff[{{ $staff->id }}].deduction == 'yes'" x-cloak>
                                                                                    <input type="number" class="form-control form-control-sm"
                                                                                        name="staff[{{ $staff->id }}][deduction_amount]"
                                                                                        x-model="staff[{{ $staff->id }}].deduction_amount"
                                                                                        min="0" step="0.01" placeholder="Amount (QAR)">
                                                                                </div>
                                                                            </div>

                                                                            <!-- Pillar 3: Generate Notice (strictly shown when Mistake == 'yes') -->
                                                                            <div class="col-md-3" x-show="staff[{{ $staff->id }}].mistake == 'yes'" x-cloak>
                                                                                <label class="form-label small text-muted mb-1 d-block">Notice</label>
                                                                                <div class="form-check pt-1 mb-0">
                                                                                    <input class="form-check-input" type="checkbox"
                                                                                        name="staff[{{ $staff->id }}][notice]"
                                                                                        id="noticeChk-{{ $complaint->id }}-{{ $staff->id }}"
                                                                                        value="1"
                                                                                        x-model="staff[{{ $staff->id }}].notice">
                                                                                    <label class="form-check-label small" for="noticeChk-{{ $complaint->id }}-{{ $staff->id }}">Generate Notice</label>
                                                                                </div>
                                                                                <div class="mt-2" x-show="staff[{{ $staff->id }}].notice == true" x-cloak>
                                                                                    <label class="form-label small text-muted mb-1">Corrective action to be taken</label>
                                                                                    <input type="text" class="form-control form-control-sm"
                                                                                        name="staff[{{ $staff->id }}][corrective_action]"
                                                                                        x-model="staff[{{ $staff->id }}].corrective_action"
                                                                                        placeholder="Corrective action...">
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                @empty
                                                                    <p class="text-muted small mb-0">No staff members attached to this complaint.</p>
                                                                @endforelse
                                                            </div>

                                                            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                                                                @if ($incidentNotices->count())
                                                                    <a href="javascript:void(0)" class="btn btn-sm btn-outline-info"
                                                                        onclick='openViewNoticeModal(@json($complaint->reference_number), @json($noticePayloads))'>
                                                                        <i class="bi bi-file-earmark-text me-1"></i> View Notice{{ $incidentNotices->count() > 1 ? 's (' . $incidentNotices->count() . ')' : '' }}
                                                                    </a>
                                                                @else
                                                                    <span></span>
                                                                @endif

                                                                <div class="d-flex gap-2 ms-auto">
                                                                    @if ($complaint->is_resolved)
                                                                        <button type="button" class="btn btn-outline-secondary btn-sm" @click="editMode = false">
                                                                            Cancel
                                                                        </button>
                                                                    @endif
                                                                    @if ($complaint->staff->count())
                                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                                            <i class="bi bi-check2-circle me-1"></i> Save Resolutions
                                                                        </button>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </form>
                                                    @else
                                                        <p class="text-muted small mb-0">Pending HR resolution.</p>
                                                    @endmoduleEdit
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr id="coEmptyRow"><td colspan="10" class="text-center text-muted py-4">No complaints logged.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mb-3">{{ $complaints->links() }}</div>

        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-2">
            <div>
                <h6 class="fw-bold mb-0">Deductions</h6>
                <p class="text-muted small mb-0">Deductions are managed through the complaint HR Resolution panel. Click any linked complaint reference to jump to or copy it.</p>
            </div>
            <form method="GET" action="{{ route('staffs.index') }}" class="row g-2 align-items-end hr-filter-bar" style="max-width: 500px;">
                <input type="hidden" name="tab" value="complaints">
                @if ($complaintsStaffFilter)
                    <input type="hidden" name="complaints_staff" value="{{ $complaintsStaffFilter }}">
                @endif
                @if ($complaintsSearch)
                    <input type="hidden" name="complaints_search" value="{{ $complaintsSearch }}">
                @endif
                @if ($complaintsDate)
                    <input type="hidden" name="complaints_date" value="{{ $complaintsDate }}">
                @endif
                @if ($complaintsFrom)
                    <input type="hidden" name="complaints_from" value="{{ $complaintsFrom }}">
                @endif
                @if ($complaintsTo)
                    <input type="hidden" name="complaints_to" value="{{ $complaintsTo }}">
                @endif

                <div class="col-sm-4">
                    <label class="form-label">From Date</label>
                    <input type="date" name="deductions_from" class="form-control form-control-sm" value="{{ $deductionsFrom ?: $deductionsDate }}">
                </div>
                <div class="col-sm-4">
                    <label class="form-label">To Date</label>
                    <input type="date" name="deductions_to" class="form-control form-control-sm" value="{{ $deductionsTo ?: $deductionsDate }}">
                </div>
                <div class="col-sm-4 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                    @if ($deductionsDate || $deductionsFrom || $deductionsTo)
                        <a href="{{ route('staffs.index', array_filter(['tab' => 'complaints', 'complaints_staff' => $complaintsStaffFilter, 'complaints_search' => $complaintsSearch, 'complaints_date' => $complaintsDate, 'complaints_from' => $complaintsFrom, 'complaints_to' => $complaintsTo])) }}" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
                    @endif
                </div>
            </form>
        </div>
        <div class="card hr-table-card p-0 mb-2">
            <div class="table-responsive">
                <table class="table team-table mb-0" id="deductionsTable">
                    <thead>
                        <tr>
                            <th style="min-width:130px;">Date</th>
                            <th style="min-width:150px;">Staff</th>
                            <th style="min-width:110px;">Amount</th>
                            <th style="min-width:170px;">Reason</th>
                            <th style="min-width:220px;">Linked complaint</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deductions as $deduction)
                            <tr data-entry-row="{{ $deduction->id }}" data-entry='@json($deduction->toEditPayload())'>
                                <td>{{ $deduction->deduction_date->format('d M Y') }}</td>
                                <td class="fw-semibold">{{ $deduction->staff->name ?? '—' }}</td>
                                <td>QAR {{ number_format($deduction->amount, 2) }}</td>
                                <td>{{ $deduction->reason }}</td>
                                <td>
                                    @if ($deduction->complaint && $deduction->complaint->reference_number)
                                        <span class="ref-badge ref-badge-action"
                                            title="Click to copy &amp; filter by {{ $deduction->complaint->reference_number }}"
                                            onclick='filterByComplaintRef(@json($deduction->complaint->reference_number))'>
                                            {{ $deduction->complaint->reference_number }}
                                            <i class="bi bi-copy" style="font-size: 10px;"></i>
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr id="dedEmptyRow"><td colspan="5" class="text-center text-muted py-4">No deductions logged.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mb-4">{{ $deductions->links() }}</div>
    </div>

    @moduleEdit('staff_management')
        <!-- Quick-Edit Wage Settings Modal -->
        <div class="modal fade" id="editWagesModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" id="editWagesForm">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h5 class="modal-title" id="editWagesModalTitle">Edit Wage Settings</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Base Salary (QAR / period)</label>
                                <input type="number" name="base_salary" id="ewBaseSalary" class="form-control" min="0" step="0.01" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Hourly Wage (QAR)</label>
                                <input type="number" name="hourly_wage" id="ewHourlyWage" class="form-control" min="0" step="0.01" required>
                            </div>
                            <div class="mb-1">
                                <label class="form-label">Commission Rate (%)</label>
                                <input type="number" name="commission_rate" id="ewCommissionRate" class="form-control" min="0" max="100" step="0.1" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Wages</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Log Overtime Modal -->
        <div class="modal fade" id="logOvertimeModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Log Overtime Entry</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Date *</label>
                                <input type="date" class="form-control" id="otQaDate" value="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Staff Member *</label>
                                <select class="form-select" id="otQaStaff">
                                    <option value="" data-hourly-wage="">Select staff</option>
                                    @foreach ($allStaff as $s)
                                        <option value="{{ $s->id }}" data-hourly-wage="{{ (float) ($s->hourly_wage ?? 0) }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Hours *</label>
                                <input type="number" min="0" max="24" step="1" class="form-control" id="otQaHours" placeholder="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Minutes (0-59)</label>
                                <input type="number" min="0" max="59" step="1" class="form-control" id="otQaMinutes" placeholder="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Rate (QAR/hr)</label>
                                <input type="number" min="0" step="0.01" class="form-control" id="otQaRate" placeholder="Auto-filled">
                            </div>
                        </div>
                        <div class="small text-muted mb-3 d-none" id="otQaCalcPreview"></div>
                        <div class="mb-3">
                            <label class="form-label">Customer Name <span class="text-muted small">(optional)</span></label>
                            <input type="text" class="form-control" id="otQaCustomerName" placeholder="Optional customer name">
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Note <span class="text-muted small">(optional)</span></label>
                            <input type="text" class="form-control" id="otQaNote" placeholder="Reason or shift note">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="otQaSaveBtn">Save Overtime</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Log Complaint Modal -->
        <div class="modal fade" id="logComplaintModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Log Complaint</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Date *</label>
                                <input type="date" class="form-control" id="coQaDate" value="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Time</label>
                                <input type="time" class="form-control" id="coQaTime" value="{{ now()->format('H:i') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Branch *</label>
                                <select class="form-select" id="coQaBranch">
                                    <option value="">Select branch</option>
                                    @foreach (\App\Models\StaffComplaint::BRANCHES as $val => $label)
                                        <option value="{{ $val }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Client *</label>
                                <div class="client-picker">
                                    <input type="text" class="form-control" id="coQaClientQuery" placeholder="Search client (name/phone)" autocomplete="off">
                                    <input type="hidden" id="coQaClientName">
                                    <input type="hidden" id="coQaClientPhone">
                                    <div class="client-picker-results d-none" id="coQaClientResults"></div>
                                </div>
                                <button type="button" class="btn btn-link btn-sm p-0 mt-1" onclick="openQuickAddClientModal('coQa')">+ Add new client</button>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Service Received *</label>
                                <select class="form-select" id="coQaService">
                                    <option value="">Select service</option>
                                    @foreach ($services as $svc)
                                        <option value="{{ $svc->id }}">{{ $svc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Category *</label>
                                <select class="form-select" id="coQaCategory">
                                    @foreach (\App\Models\StaffComplaint::CATEGORIES as $cat)
                                        <option value="{{ $cat }}">{{ $cat }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Staff Involved *</label>
                                <div class="staff-tagselect" id="coQaStaffWrap">
                                    <div class="staff-tagselect-control" onclick="toggleStaffDropdown('coQa')">
                                        <div class="staff-tagselect-chips" id="coQaStaffChips">
                                            <span class="staff-tagselect-placeholder">Select staff...</span>
                                        </div>
                                        <i class="bx bx-chevron-down"></i>
                                    </div>
                                    <div class="staff-tagselect-dropdown d-none" id="coQaStaffDropdown">
                                        <input type="text" class="form-control form-control-sm" placeholder="Search staff..." id="coQaStaffSearch" oninput="filterStaffOptions('coQa')">
                                        <div class="staff-tagselect-options" id="coQaStaffOptions"></div>
                                    </div>
                                    <input type="hidden" data-field="staff_ids" data-multi="1" id="coQaStaffValue" value="">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">What Happened *</label>
                            <textarea class="form-control" id="coQaWhatHappened" rows="3" placeholder="Describe what happened..."></textarea>
                        </div>

                        <div class="mb-1">
                            <label class="form-label">Corrective Action to be taken <span class="text-muted small">(optional)</span></label>
                            <textarea class="form-control" id="coQaCorrectiveAction" rows="3" placeholder="Optional global corrective action (can also be customized per staff member during HR Resolution)..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="coQaSaveBtn">Save Complaint</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Complaint Modal (What Happened & Corrective Action) -->
        <div class="modal fade" id="editComplaintModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <form method="POST" id="editComplaintForm" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="editComplaintModalTitle">Edit Incident Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">What Happened *</label>
                            <textarea class="form-control" name="what_happened" id="editCoWhatHappened" rows="4" required placeholder="Describe what happened..."></textarea>
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Corrective Action <span class="text-muted small">(optional)</span></label>
                            <textarea class="form-control" name="corrective_action" id="editCoCorrectiveAction" rows="4" placeholder="Optional global corrective action..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bx bx-check me-1"></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Deduction Modal -->
        <div class="modal fade" id="addDeductionModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Deduction</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Date *</label>
                                <input type="date" class="form-control" id="dedQaDate" value="{{ now()->format('Y-m-d') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Amount (QAR) *</label>
                                <input type="number" min="0" step="0.01" class="form-control" id="dedQaAmount" placeholder="QAR">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Staff Member *</label>
                            <select class="form-select" id="dedQaStaff">
                                <option value="">Select staff</option>
                                @foreach ($allStaff as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason *</label>
                            <input type="text" class="form-control" id="dedQaReason" placeholder="Reason for deduction">
                        </div>
                        <div class="mb-1">
                            <label class="form-label">Linked Complaint <span class="text-muted small">(optional)</span></label>
                            <select class="form-select" id="dedQaComplaint">
                                <option value="">No linked complaint</option>
                                @foreach ($openComplaints as $c)
                                    <option value="{{ $c->id }}">{{ $c->reference_number }} — {{ $c->staffMembers->pluck('name')->implode(', ') ?: '—' }} ({{ $c->complaint_date->format('d M Y') }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="dedQaSaveBtn">Save Deduction</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="quickAddClientModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add new client</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Client name</label>
                            <input type="text" class="form-control" id="qacName">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact number</label>
                            <input type="text" class="form-control" id="qacPhone">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="qacConfirmBtn">Add Client</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="generateNoticeModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Generate staff notice</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3" id="gnContext"></p>

                        <div class="mb-3">
                            <label class="form-label">Notify</label>
                            <div id="gnStaffChecks"></div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Notice type</label>
                                <select class="form-select" id="gnType">
                                    @foreach (\App\Models\StaffNotice::TYPES as $t)
                                        <option value="{{ $t }}">{{ $t }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" id="gnSubject">
                            </div>
                        </div>

                        <p class="small mb-2 d-none" id="gnAiStatus"></p>

                        <div class="mb-3">
                            <label class="form-label">Summary of what happened</label>
                            <textarea class="form-control" id="gnSummary" rows="4"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Corrective actions to be taken</label>
                            <textarea class="form-control" id="gnCorrectiveActions" rows="3" placeholder="e.g. Re-training on service standards, formal apology to client, schedule adjustment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="gnConfirmBtn">Draft Notice(s)</button>
                    </div>
                </div>
            </div>
        </div>

        @include('staff.main-form')

        <script>
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    editStaff(
                        JSON.parse(this.dataset.staff),
                        this.dataset.hasAccess === '1',
                        this.dataset.accessRole,
                        this.dataset.accessEmail
                    );
                });
            });
        </script>
    @endmoduleEdit

    <!-- View Generated Staff Notice Modal -->
    <div class="modal fade" id="viewNoticeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewNoticeModalTitle">Generated Staff Notice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewNoticeModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script>
        document.querySelectorAll('.staff-tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.staff-tab-btn').forEach(b => {
                    b.classList.remove('btn-dark');
                    b.classList.add('btn-outline-secondary');
                });
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-dark');

                document.querySelectorAll('.staff-tab-pane').forEach(p => p.classList.add('d-none'));
                document.getElementById('staff-pane-' + this.dataset.tab).classList.remove('d-none');

                const globalAddBtn = document.getElementById('globalAddStaffBtn');
                if (globalAddBtn) {
                    globalAddBtn.classList.toggle('d-none', this.dataset.tab !== 'directory');
                }

                const url = new URL(window.location.href);
                url.searchParams.set('tab', this.dataset.tab);
                history.replaceState(null, '', url.toString());
            });
        });

        window.openEditWagesModal = function (staffId, staffName, baseSalary, hourlyWage, commissionRate) {
            const modalEl = document.getElementById('editWagesModal');
            if (!modalEl) return;
            document.getElementById('editWagesModalTitle').textContent = `Edit Wage Settings — ${staffName}`;
            document.getElementById('editWagesForm').action = `/staffs/${staffId}/wages`;
            document.getElementById('ewBaseSalary').value = Number(baseSalary || 0).toFixed(2);
            document.getElementById('ewHourlyWage').value = Number(hourlyWage || 0).toFixed(2);
            document.getElementById('ewCommissionRate').value = Number(commissionRate || 0).toFixed(1);
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        };

        function updateOvertimeRateAndPreview(autoFillRate = false) {
            const staffSelect = document.getElementById('otQaStaff');
            const rateInput = document.getElementById('otQaRate');
            const hoursInput = document.getElementById('otQaHours');
            const minutesInput = document.getElementById('otQaMinutes');
            const previewEl = document.getElementById('otQaCalcPreview');
            if (!staffSelect || !rateInput) return;

            if (autoFillRate) {
                const selectedOpt = staffSelect.options[staffSelect.selectedIndex];
                const staffId = Number(staffSelect.value || 0);
                const staffObj = (typeof ALL_STAFF !== 'undefined' ? ALL_STAFF : []).find(s => s.id === staffId);
                const wage = staffObj !== undefined
                    ? Number(staffObj.hourly_wage || 0)
                    : Number(selectedOpt?.dataset?.hourlyWage || 0);
                rateInput.value = staffId ? wage.toFixed(2) : '';
            }

            if (previewEl) {
                const hrs = Number(hoursInput?.value || 0);
                const mins = Number(minutesInput?.value || 0);
                const rate = Number(rateInput.value || 0);
                const totalDec = hrs + (mins / 60);
                if (totalDec > 0 && rate >= 0 && rateInput.value !== '') {
                    const estPay = (totalDec * rate).toFixed(2);
                    previewEl.textContent = `Duration: ${totalDec.toFixed(2)} hrs × QAR ${rate.toFixed(2)}/hr = QAR ${estPay}`;
                    previewEl.classList.remove('d-none');
                } else {
                    previewEl.textContent = '';
                    previewEl.classList.add('d-none');
                }
            }
        }

        document.getElementById('otQaStaff')?.addEventListener('change', () => updateOvertimeRateAndPreview(true));
        document.getElementById('otQaHours')?.addEventListener('input', () => updateOvertimeRateAndPreview(false));
        document.getElementById('otQaMinutes')?.addEventListener('input', () => updateOvertimeRateAndPreview(false));
        document.getElementById('otQaRate')?.addEventListener('input', () => updateOvertimeRateAndPreview(false));

        window.openLogOvertimeModal = function (preselectedStaffId = '') {
            const modalEl = document.getElementById('logOvertimeModal');
            if (!modalEl) return;
            const staffSelect = document.getElementById('otQaStaff');
            if (staffSelect) {
                staffSelect.value = preselectedStaffId ? String(preselectedStaffId) : '';
            }
            document.getElementById('otQaHours').value = '';
            document.getElementById('otQaMinutes').value = '0';
            document.getElementById('otQaCustomerName').value = '';
            document.getElementById('otQaNote').value = '';
            updateOvertimeRateAndPreview(true);
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        };

        /* ---------------- STAFF WORKSPACE ---------------- */
        (function() {
            const picker = document.getElementById('wsStaffPicker');
            if (!picker) return; // view-only users don't get the Workspace form at all

            const STAFF_WORKSPACE_DATA = @json($staffWorkspaceOptions);
            let wsSelectedStaffId = @json($selectedWorkspaceStaffId);

            function wsBranchLabel(branch) {
                return branch === 'old_airport' ? 'Old Airport' : branch === 'wakrah' ? 'Wakrah' : 'Both branches';
            }

            function wsInitials(name) {
                return name ? name.charAt(0).toUpperCase() : '?';
            }

            function wsOpenPanel() {
                document.getElementById('wsStaffPanel').classList.remove('d-none');
                document.getElementById('wsStaffSearch').value = '';
                wsRenderStaffResults('');
            }

            function wsClosePanel() {
                document.getElementById('wsStaffPanel').classList.add('d-none');
            }

            document.getElementById('wsStaffTrigger').addEventListener('click', function(e) {
                e.stopPropagation();
                const panel = document.getElementById('wsStaffPanel');
                panel.classList.contains('d-none') ? wsOpenPanel() : wsClosePanel();
            });
            document.getElementById('wsStaffPanel').addEventListener('click', e => e.stopPropagation());
            document.addEventListener('click', wsClosePanel);

            function wsRenderStaffResults(query) {
                const q = query.trim().toLowerCase();
                const box = document.getElementById('wsStaffResults');
                const matches = STAFF_WORKSPACE_DATA.filter(s => s.name.toLowerCase().includes(q));

                if (!matches.length) {
                    box.innerHTML = '<div class="text-muted small px-2">No team members found.</div>';
                    return;
                }

                box.innerHTML = matches.map(s => `
                    <div class="ws-staff-row ${s.id === wsSelectedStaffId ? 'active' : ''}" data-id="${s.id}">
                        <div class="ws-staff-row-avatar">${wsInitials(s.name)}</div>
                        <div>
                            <div>${s.name}</div>
                            <div class="ws-staff-row-sub">${wsBranchLabel(s.branch)}</div>
                        </div>
                    </div>
                `).join('');

                box.querySelectorAll('.ws-staff-row').forEach(row => {
                    row.addEventListener('click', () => wsSelectStaff(Number(row.dataset.id)));
                });
            }

            document.getElementById('wsStaffSearch').addEventListener('input', function() {
                wsRenderStaffResults(this.value);
            });

            function wsUpdateServiceCount() {
                document.getElementById('wsServiceCount').textContent =
                    document.querySelectorAll('.ws-svc-checkbox:checked').length;
            }

            function wsSelectStaff(id) {
                const member = STAFF_WORKSPACE_DATA.find(s => s.id === id);
                if (!member) return;
                wsSelectedStaffId = id;

                document.getElementById('wsStaffAvatar').textContent = wsInitials(member.name);
                document.getElementById('wsStaffName').textContent = member.name;
                document.getElementById('wsStaffSub').textContent = wsBranchLabel(member.branch);

                document.getElementById('wsForm').action = `/staffs/${id}/workspace`;
                document.getElementById('wsBranch').value = member.branch;
                document.getElementById('wsBookable').checked = !!member.bookable;

                document.querySelectorAll('.ws-svc-checkbox').forEach(cb => {
                    cb.checked = member.service_ids.includes(Number(cb.value));
                });
                document.querySelectorAll('.ws-cat-checkbox').forEach(cb => {
                    const catBoxes = document.querySelectorAll(`.ws-svc-checkbox[data-cat="${cb.dataset.cat}"]`);
                    cb.checked = catBoxes.length > 0 && [...catBoxes].every(b => b.checked);
                });
                document.getElementById('wsAllServices').checked =
                    document.querySelectorAll('.ws-svc-checkbox').length > 0 &&
                    [...document.querySelectorAll('.ws-svc-checkbox')].every(b => b.checked);
                wsUpdateServiceCount();

                document.getElementById('wsNoStaffNotice').classList.add('d-none');
                document.getElementById('wsForm').classList.remove('d-none');

                wsClosePanel();
            }

            document.getElementById('wsAllServices').addEventListener('change', function() {
                document.querySelectorAll('.ws-svc-checkbox, .ws-cat-checkbox').forEach(cb => cb.checked = this.checked);
                wsUpdateServiceCount();
            });
            document.querySelectorAll('.ws-cat-checkbox').forEach(cb => {
                cb.addEventListener('change', function() {
                    document.querySelectorAll(`.ws-svc-checkbox[data-cat="${this.dataset.cat}"]`).forEach(sc => sc.checked = this.checked);
                    wsUpdateServiceCount();
                });
            });
            document.querySelectorAll('.ws-svc-checkbox').forEach(cb => cb.addEventListener('change', wsUpdateServiceCount));

            document.getElementById('wsServiceSearch').addEventListener('input', function() {
                const q = this.value.trim().toLowerCase();
                document.querySelectorAll('#wsServiceList .svc-catalog-row[data-svc-name]').forEach(row => {
                    row.style.display = row.dataset.svcName.includes(q) ? '' : 'none';
                });
            });

            document.querySelectorAll('.ws-subtab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.ws-subtab-btn').forEach(b => {
                        b.classList.remove('btn-dark');
                        b.classList.add('btn-outline-secondary');
                    });
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('btn-dark');
                    document.querySelectorAll('.ws-subtab-pane').forEach(p => p.classList.remove('active'));
                    document.getElementById('ws-' + this.dataset.wsPane).classList.add('active');
                });
            });

            if (wsSelectedStaffId) {
                wsSelectStaff(wsSelectedStaffId);
            }
        })();

        /* ---------------- PAYROLL & OPERATIONS: INLINE LEDGERS ---------------- */
        window.hrResolution = function (initialStaff = {}) {
            return {
                staff: initialStaff
            };
        };

        const CAN_EDIT_STAFF = @json(auth()->check() && auth()->user()->canEdit('staff_management'));
        const CSRF_TOKEN = '{{ csrf_token() }}';
        const ALL_STAFF = @json($allStaffOptions);
        const ALL_SERVICES = @json($serviceOptions);
        const OPEN_COMPLAINTS = @json($openComplaintOptions);
        const COMPLAINT_CATEGORIES = @json(\App\Models\StaffComplaint::CATEGORIES);
        const COMPLAINT_BRANCHES = @json(\App\Models\StaffComplaint::BRANCHES);
        const NOTICE_TYPES = @json(\App\Models\StaffNotice::TYPES);
        const CUSTOMER_SEARCH_URL = "{{ route('customers.search') }}";

        function escapeHtml(value) {
            if (value === null || value === undefined) return '';
            const div = document.createElement('div');
            div.textContent = value;
            return div.innerHTML;
        }

        function showStaffToast(message, type = 'success') {
            let container = document.querySelector('.staff-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'staff-toast-container';
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            toast.className = 'staff-toast' + (type === 'error' ? ' error' : '');
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3500);
        }

        function staffOptionsHtml(selectedId) {
            let opts = '<option value="">Select staff</option>';
            opts += ALL_STAFF.map(s => `<option value="${s.id}" ${s.id === selectedId ? 'selected' : ''}>${escapeHtml(s.name)}</option>`).join('');
            return opts;
        }

        /* ---------- STAFF INVOLVED: tag-style multi-select (search + checkable list + removable chips) ---------- */
        function staffTagSelectHtml(prefix, selectedIds) {
            const ids = (selectedIds || []).join(',');
            return `
                <div class="staff-tagselect" id="${prefix}StaffWrap">
                    <div class="staff-tagselect-control" onclick="toggleStaffDropdown('${prefix}')">
                        <div class="staff-tagselect-chips" id="${prefix}StaffChips"></div>
                        <i class="bx bx-chevron-down"></i>
                    </div>
                    <div class="staff-tagselect-dropdown d-none" id="${prefix}StaffDropdown">
                        <input type="text" class="form-control form-control-sm" placeholder="Search staff..." id="${prefix}StaffSearch" oninput="filterStaffOptions('${prefix}')">
                        <div class="staff-tagselect-options" id="${prefix}StaffOptions"></div>
                    </div>
                    <input type="hidden" data-field="staff_ids" data-multi="1" id="${prefix}StaffValue" value="${ids}">
                </div>
            `;
        }

        function staffTagSelectedIds(prefix) {
            const value = document.getElementById(prefix + 'StaffValue')?.value || '';
            return value ? value.split(',').map(Number) : [];
        }

        function renderStaffTagChips(prefix) {
            const ids = staffTagSelectedIds(prefix);
            const box = document.getElementById(prefix + 'StaffChips');
            if (!box) return;
            if (!ids.length) {
                box.innerHTML = '<span class="staff-tagselect-placeholder">Select staff...</span>';
                return;
            }
            box.innerHTML = ids.map(id => {
                const staff = ALL_STAFF.find(s => s.id === id);
                if (!staff) return '';
                return `<span class="staff-tag-chip">${escapeHtml(staff.name)}<button type="button" class="staff-tag-remove" onclick="event.stopPropagation(); removeStaffTag('${prefix}', ${id})">&times;</button></span>`;
            }).join('');
        }

        function renderStaffTagOptions(prefix, filter = '') {
            const ids = staffTagSelectedIds(prefix);
            const box = document.getElementById(prefix + 'StaffOptions');
            if (!box) return;
            const q = filter.trim().toLowerCase();
            const list = ALL_STAFF.filter(s => !q || s.name.toLowerCase().includes(q));
            if (!list.length) {
                box.innerHTML = '<div class="staff-tagselect-empty">No matches.</div>';
                return;
            }
            box.innerHTML = list.map(s => `
                <label class="staff-tagselect-option">
                    <input type="checkbox" value="${s.id}" ${ids.includes(s.id) ? 'checked' : ''} onchange="toggleStaffTag('${prefix}', ${s.id}, this.checked)">
                    ${escapeHtml(s.name)}
                </label>
            `).join('');
        }

        function renderStaffTagSelect(prefix) {
            renderStaffTagChips(prefix);
            renderStaffTagOptions(prefix);
        }

        window.toggleStaffDropdown = function (prefix) {
            const dropdown = document.getElementById(prefix + 'StaffDropdown');
            if (!dropdown) return;
            const wasHidden = dropdown.classList.contains('d-none');
            document.querySelectorAll('.staff-tagselect-dropdown').forEach(d => d.classList.add('d-none'));
            if (wasHidden) {
                dropdown.classList.remove('d-none');
                renderStaffTagOptions(prefix);
                document.getElementById(prefix + 'StaffSearch')?.focus();
            }
        };

        window.filterStaffOptions = function (prefix) {
            renderStaffTagOptions(prefix, document.getElementById(prefix + 'StaffSearch')?.value || '');
        };

        window.toggleStaffTag = function (prefix, staffId, checked) {
            const valueInput = document.getElementById(prefix + 'StaffValue');
            let ids = staffTagSelectedIds(prefix);
            ids = checked ? [...new Set([...ids, staffId])] : ids.filter(id => id !== staffId);
            valueInput.value = ids.join(',');
            renderStaffTagChips(prefix);
            document.getElementById(prefix + 'StaffSearch')?.focus();
        };

        window.removeStaffTag = function (prefix, staffId) {
            const valueInput = document.getElementById(prefix + 'StaffValue');
            valueInput.value = staffTagSelectedIds(prefix).filter(id => id !== staffId).join(',');
            renderStaffTagSelect(prefix);
        };

        document.addEventListener('click', function (e) {
            document.querySelectorAll('.staff-tagselect').forEach(wrap => {
                if (!wrap.contains(e.target)) {
                    wrap.querySelector('.staff-tagselect-dropdown')?.classList.add('d-none');
                }
            });
        });

        function serviceOptionsHtml(selectedId) {
            let opts = '<option value="">Select service</option>';
            opts += ALL_SERVICES.map(s => `<option value="${s.id}" ${s.id === selectedId ? 'selected' : ''}>${escapeHtml(s.name)}</option>`).join('');
            return opts;
        }

        function plainOptionsHtml(list, selected) {
            return list.map(v => `<option value="${v}" ${v === selected ? 'selected' : ''}>${v}</option>`).join('');
        }

        function branchLabel(code) {
            return COMPLAINT_BRANCHES[code] || code || '—';
        }

        function branchOptionsHtml(selected) {
            let opts = '<option value="">Select branch</option>';
            opts += Object.keys(COMPLAINT_BRANCHES).map(code => `<option value="${code}" ${code === selected ? 'selected' : ''}>${COMPLAINT_BRANCHES[code]}</option>`).join('');
            return opts;
        }

        function complaintOptionsHtml(selectedId) {
            let opts = '<option value="">No linked complaint</option>';
            opts += OPEN_COMPLAINTS.map(c => `<option value="${c.id}" ${c.id === selectedId ? 'selected' : ''}>${escapeHtml(c.reference_number)} — ${escapeHtml(c.staff_name)} (${c.complaint_date_label})</option>`).join('');
            return opts;
        }

        function complaintLabel(id) {
            if (!id) return '—';
            const c = OPEN_COMPLAINTS.find(x => x.id === id);
            return c ? escapeHtml(c.reference_number) : '—';
        }

        /* ---------- CLIENT PICKER (search existing customers / quick-add new) ---------- */
        const clientPickerTimers = {};

        function clientPickerCellHtml(prefix, name, phone) {
            const display = name ? (phone ? `${name} (${phone})` : name) : '';
            return `
                <div class="client-picker">
                    <input type="text" class="form-control form-control-sm" id="${prefix}ClientQuery" placeholder="Search client (name/phone)" autocomplete="off" value="${escapeHtml(display)}">
                    <input type="hidden" data-field="customer_name" id="${prefix}ClientName" value="${escapeHtml(name || '')}">
                    <input type="hidden" data-field="customer_phone" id="${prefix}ClientPhone" value="${escapeHtml(phone || '')}">
                    <div class="client-picker-results d-none" id="${prefix}ClientResults"></div>
                </div>
                <button type="button" class="btn btn-link btn-sm p-0 mt-1" onclick="openQuickAddClientModal('${prefix}')">+ Add new client</button>
            `;
        }

        function wireClientPicker(prefix) {
            const input = document.getElementById(prefix + 'ClientQuery');
            if (!input || input.dataset.wired) return;
            input.dataset.wired = '1';

            input.addEventListener('input', function () {
                const nameField = document.getElementById(prefix + 'ClientName');
                const phoneField = document.getElementById(prefix + 'ClientPhone');
                if (nameField) nameField.value = '';
                if (phoneField) phoneField.value = '';

                clearTimeout(clientPickerTimers[prefix]);
                const q = this.value.trim();
                const results = document.getElementById(prefix + 'ClientResults');
                if (!q) {
                    results?.classList.add('d-none');
                    if (results) results.innerHTML = '';
                    return;
                }
                clientPickerTimers[prefix] = setTimeout(() => searchClients(prefix, q), 300);
            });

            input.addEventListener('blur', function () {
                setTimeout(() => document.getElementById(prefix + 'ClientResults')?.classList.add('d-none'), 150);
            });
        }

        function searchClients(prefix, q) {
            fetch(`${CUSTOMER_SEARCH_URL}?q=` + encodeURIComponent(q))
                .then(r => r.json())
                .then(list => {
                    const box = document.getElementById(prefix + 'ClientResults');
                    if (!box) return;
                    if (!list.length) {
                        box.innerHTML = '<div class="client-picker-result text-muted">No matches — use "+ Add new client".</div>';
                    } else {
                        box.innerHTML = list.map(c => `
                            <div class="client-picker-result" data-id="${c.id}" data-name="${escapeHtml(c.name)}" data-phone="${escapeHtml(c.phone)}">
                                ${escapeHtml(c.name)} <span class="cpr-phone">${escapeHtml(c.phone)}</span>
                            </div>
                        `).join('');
                        box.querySelectorAll('.client-picker-result').forEach(row => {
                            row.addEventListener('mousedown', function (e) {
                                e.preventDefault();
                                selectClient(prefix, this.dataset.name, this.dataset.phone);
                            });
                        });
                    }
                    box.classList.remove('d-none');
                });
        }

        function selectClient(prefix, name, phone) {
            const query = document.getElementById(prefix + 'ClientQuery');
            const nameField = document.getElementById(prefix + 'ClientName');
            const phoneField = document.getElementById(prefix + 'ClientPhone');
            if (query) query.value = phone ? `${name} (${phone})` : name;
            if (nameField) nameField.value = name || '';
            if (phoneField) phoneField.value = phone || '';
            document.getElementById(prefix + 'ClientResults')?.classList.add('d-none');
        }

        let activeClientPrefix = null;
        window.openQuickAddClientModal = function (prefix) {
            activeClientPrefix = prefix;
            document.getElementById('qacName').value = '';
            document.getElementById('qacPhone').value = '';
            new bootstrap.Modal(document.getElementById('quickAddClientModal')).show();
        };

        const qacConfirmBtn = document.getElementById('qacConfirmBtn');
        if (qacConfirmBtn) {
            qacConfirmBtn.addEventListener('click', function () {
                const name = document.getElementById('qacName').value.trim();
                const phone = document.getElementById('qacPhone').value.trim();
                if (!name || !phone) {
                    showStaffToast('Enter both a name and a contact number.', 'error');
                    return;
                }
                if (activeClientPrefix) selectClient(activeClientPrefix, name, phone);
                bootstrap.Modal.getInstance(document.getElementById('quickAddClientModal'))?.hide();
            });
        }

        window.toggleDeductionAmount = function (prefix, value) {
            const amountInput = document.getElementById(prefix + 'DeductionAmount');
            if (!amountInput) return;
            amountInput.classList.toggle('d-none', value !== 'Y');
            if (value !== 'Y') amountInput.value = '';
        };

        function readRowFields(row) {
            const fields = {};
            row.querySelectorAll('[data-field]').forEach(input => {
                if (input.dataset.multi === '1') {
                    fields[input.dataset.field] = input.value ? input.value.split(',') : [];
                } else if (input.tagName === 'SELECT' && input.multiple) {
                    fields[input.dataset.field] = Array.from(input.selectedOptions).map(o => o.value);
                } else {
                    fields[input.dataset.field] = input.value;
                }
            });
            return fields;
        }

        function jsonFetch(url, method, body) {
            return fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(body)
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })));
        }

        function jsonDelete(url) {
            return fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' } })
                .then(r => r.json().then(data => ({ ok: r.ok, data })));
        }

        function maybeShowEmptyState(tableSelector, emptyId, colspan, message) {
            const tbody = document.querySelector(`${tableSelector} tbody`);
            if (tbody.querySelectorAll('tr[data-entry-row]').length === 0 && !document.getElementById(emptyId)) {
                const tr = document.createElement('tr');
                tr.id = emptyId;
                tr.innerHTML = `<td colspan="${colspan}" class="text-center text-muted py-4">${message}</td>`;
                tbody.appendChild(tr);
            }
        }

        function actionsHtml(id, mode, editFn, deleteFn, saveFn, cancelFn) {
            if (!CAN_EDIT_STAFF) return '';
            if (mode === 'edit') {
                return `<div class="d-flex gap-1 justify-content-end">
                    <button type="button" class="btn btn-sm btn-primary" onclick="${saveFn}(${id})" title="Save"><i class="bi bi-check-lg"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="${cancelFn}(${id})" title="Cancel"><i class="bi bi-x-lg"></i></button>
                </div>`;
            }
            return `<div class="d-flex gap-1 justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-warning" onclick="${editFn}(${id})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="${deleteFn}(${id})" title="Delete"><i class="bi bi-trash"></i></button>
            </div>`;
        }

        /* ---------- OVERTIME ---------- */
        let otEntries = {};
        document.querySelectorAll('#overtimeTable tr[data-entry-row]').forEach(row => {
            const entry = JSON.parse(row.dataset.entry);
            otEntries[entry.id] = entry;
        });

        function otViewRowHtml(e) {
            return `
                <td>${e.entry_date_label}</td>
                <td class="fw-semibold">${escapeHtml(e.staff_name)}</td>
                <td>${e.hours}</td>
                <td>${e.rate !== null ? 'QAR ' + Number(e.rate).toFixed(2) : 'Default'}</td>
                <td>QAR ${Number(e.pay).toFixed(2)}</td>
                <td>${escapeHtml(e.note) || '—'}</td>
                <td class="text-end">${actionsHtml(e.id, 'view', 'startEditOt', 'deleteOt')}</td>
            `;
        }

        function otEditRowHtml(e) {
            return `
                <td><input type="date" class="form-control form-control-sm" data-field="entry_date" value="${e.entry_date}"></td>
                <td><select class="form-select form-select-sm" data-field="staff_id">${staffOptionsHtml(e.staff_id)}</select></td>
                <td><input type="number" min="0.25" max="24" step="0.25" class="form-control form-control-sm" data-field="hours" value="${e.hours}"></td>
                <td><input type="number" min="0" step="0.01" class="form-control form-control-sm" data-field="rate" placeholder="Default" value="${e.rate !== null ? e.rate : ''}"></td>
                <td class="text-muted small">—</td>
                <td><input type="text" class="form-control form-control-sm" data-field="note" value="${escapeHtml(e.note || '')}"></td>
                <td class="text-end">${actionsHtml(e.id, 'edit', '', '', 'saveEditOt', 'cancelEditOt')}</td>
            `;
        }

        window.startEditOt = function (id) {
            const row = document.querySelector(`#overtimeTable tr[data-entry-row="${id}"]`);
            if (row) row.innerHTML = otEditRowHtml(otEntries[id]);
        };
        window.cancelEditOt = function (id) {
            const row = document.querySelector(`#overtimeTable tr[data-entry-row="${id}"]`);
            if (row) row.innerHTML = otViewRowHtml(otEntries[id]);
        };
        window.saveEditOt = function (id) {
            const row = document.querySelector(`#overtimeTable tr[data-entry-row="${id}"]`);
            jsonFetch(`/staff-overtime/${id}`, 'PUT', readRowFields(row)).then(({ ok, data }) => {
                if (ok && data.success) {
                    otEntries[id] = data.entry;
                    row.innerHTML = otViewRowHtml(data.entry);
                    showStaffToast(data.message || 'Overtime entry updated.');
                } else {
                    const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not update entry.');
                    showStaffToast(firstError, 'error');
                }
            });
        };
        window.deleteOt = function (id) {
            if (!confirm('Delete this overtime entry?')) return;
            jsonDelete(`/staff-overtime/${id}`).then(({ ok, data }) => {
                if (ok && data.success) {
                    showStaffToast(data.message || 'Overtime entry deleted.');
                    window.location.reload();
                } else {
                    showStaffToast(data.message || 'Could not delete entry.', 'error');
                }
            });
        };

        const otQaSaveBtn = document.getElementById('otQaSaveBtn');
        if (otQaSaveBtn) {
            otQaSaveBtn.addEventListener('click', function () {
                const hoursVal = Number(document.getElementById('otQaHours').value || 0);
                const minutesVal = Number(document.getElementById('otQaMinutes').value || 0);
                const fields = {
                    entry_date: document.getElementById('otQaDate').value,
                    staff_id: document.getElementById('otQaStaff').value,
                    hours: hoursVal,
                    minutes: minutesVal,
                    rate: document.getElementById('otQaRate').value,
                    customer_name: document.getElementById('otQaCustomerName').value,
                    note: document.getElementById('otQaNote').value,
                };
                if (!fields.entry_date || !fields.staff_id || (hoursVal <= 0 && minutesVal <= 0)) {
                    showStaffToast('Date, staff, and overtime duration (hours or minutes) are required.', 'error');
                    return;
                }
                if (minutesVal < 0 || minutesVal > 59) {
                    showStaffToast('Minutes must be between 0 and 59.', 'error');
                    return;
                }
                jsonFetch(`{{ route('staff-overtime.store') }}`, 'POST', fields).then(({ ok, data }) => {
                    if (ok && data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('logOvertimeModal'))?.hide();
                        showStaffToast(data.message || 'Overtime entry logged.');
                        window.location.reload();
                    } else {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not add entry.');
                        showStaffToast(firstError, 'error');
                    }
                });
            });
        }

        /* ---------- COMPLAINTS ---------- */
        let coEntries = {};
        document.querySelectorAll('#complaintsTable tr[data-entry-row]').forEach(row => {
            const entry = JSON.parse(row.dataset.entry);
            coEntries[entry.id] = entry;
        });

        function coActionsHtml(id, mode) {
            if (!CAN_EDIT_STAFF) return '';
            if (mode === 'edit') {
                return `<div class="d-flex gap-1 justify-content-end" onclick="event.stopPropagation();">
                    <button type="button" class="btn btn-sm btn-primary" onclick="event.stopPropagation(); saveEditCo(${id})" title="Save"><i class="bi bi-check-lg"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation(); cancelEditCo(${id})" title="Cancel"><i class="bi bi-x-lg"></i></button>
                </div>`;
            }
            return `<div class="d-flex gap-1 justify-content-end" onclick="event.stopPropagation();">
                <button type="button" class="btn btn-sm btn-outline-info" onclick="event.stopPropagation(); openGenerateNoticeModal(${id})" title="Generate staff notice"><i class="bi bi-file-earmark-text"></i></button>
                <button type="button" class="btn btn-sm btn-outline-warning" onclick="event.stopPropagation(); openEditComplaintModal(${id})" title="Edit What Happened & Corrective Action"><i class="bi bi-pencil-square"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); deleteCo(${id})" title="Delete"><i class="bi bi-trash"></i></button>
            </div>`;
        }

        function coDeductionCellHtml(e) {
            return e.deduction_applied === 'Y'
                ? `<span class="status-badge open">QAR ${Number(e.deduction_amount).toFixed(2)}</span>`
                : '<span class="text-muted small">No</span>';
        }

        function coViewRowHtml(e) {
            return `
                <td>
                    <div class="d-flex align-items-center">
                        <i class="bx bx-chevron-right payroll-chevron me-1"></i>
                        <div>
                            <span class="ref-badge">${escapeHtml(e.reference_number)}</span>
                            ${e.notice_count > 0 ? `<div class="text-muted small mt-1">${e.notice_count} notice${e.notice_count > 1 ? 's' : ''}</div>` : ''}
                        </div>
                    </div>
                </td>
                <td>${e.complaint_date_label}<div class="text-muted small">${e.time_label}</div></td>
                <td>${branchLabel(e.branch)}</td>
                <td>${escapeHtml(e.customer_name)}<div class="text-muted small">${escapeHtml(e.customer_phone) || '—'}</div></td>
                <td>${escapeHtml(e.service_name) || '—'}</td>
                <td>${escapeHtml(e.category)}</td>
                <td class="fw-semibold">${escapeHtml(e.staff_names)}</td>
                <td>${coDeductionCellHtml(e)}</td>
                <td>${escapeHtml(e.description)}</td>
                <td class="text-end" onclick="event.stopPropagation();">${coActionsHtml(e.id, 'view')}</td>
            `;
        }

        function coEditRowHtml(e) {
            const prefix = `coEdit${e.id}`;
            return `
                <td class="text-muted small">${escapeHtml(e.reference_number)}</td>
                <td>
                    <input type="date" class="form-control form-control-sm mb-1" data-field="complaint_date" value="${e.complaint_date}">
                    <input type="time" class="form-control form-control-sm" data-field="complaint_time" value="${e.complaint_time || ''}">
                </td>
                <td><select class="form-select form-select-sm" data-field="branch">${branchOptionsHtml(e.branch)}</select></td>
                <td>${clientPickerCellHtml(prefix, e.customer_name, e.customer_phone)}</td>
                <td><select class="form-select form-select-sm" data-field="service_id">${serviceOptionsHtml(e.service_id)}</select></td>
                <td><select class="form-select form-select-sm" data-field="category">${plainOptionsHtml(COMPLAINT_CATEGORIES, e.category)}</select></td>
                <td>${staffTagSelectHtml(prefix, e.staff_ids)}</td>
                <td>
                    <select class="form-select form-select-sm" data-field="deduction_applied" onchange="toggleDeductionAmount('${prefix}', this.value)">
                        <option value="N" ${e.deduction_applied === 'N' ? 'selected' : ''}>No</option>
                        <option value="Y" ${e.deduction_applied === 'Y' ? 'selected' : ''}>Yes</option>
                    </select>
                    <input type="number" min="0" step="0.01" class="form-control form-control-sm mt-1 ${e.deduction_applied === 'Y' ? '' : 'd-none'}" data-field="deduction_amount" id="${prefix}DeductionAmount" placeholder="QAR" value="${e.deduction_amount || ''}">
                </td>
                <td><input type="text" class="form-control form-control-sm" data-field="description" value="${escapeHtml(e.description)}"></td>
                <td class="text-end" onclick="event.stopPropagation();">${coActionsHtml(e.id, 'edit')}</td>
            `;
        }

        window.startEditCo = function (id) {
            const row = document.querySelector(`#complaintsTable tr[data-entry-row="${id}"]`);
            if (!row) return;
            row.removeAttribute('data-bs-toggle');
            row.innerHTML = coEditRowHtml(coEntries[id]);
            wireClientPicker(`coEdit${id}`);
            renderStaffTagSelect(`coEdit${id}`);
        };
        window.cancelEditCo = function (id) {
            const row = document.querySelector(`#complaintsTable tr[data-entry-row="${id}"]`);
            if (!row) return;
            row.setAttribute('data-bs-toggle', 'collapse');
            row.innerHTML = coViewRowHtml(coEntries[id]);
        };
        window.saveEditCo = function (id) {
            const row = document.querySelector(`#complaintsTable tr[data-entry-row="${id}"]`);
            const fields = readRowFields(row);
            if (!fields.staff_ids || !fields.staff_ids.length) {
                showStaffToast('Select at least one staff member involved.', 'error');
                return;
            }
            if (fields.deduction_applied === 'Y' && !fields.deduction_amount) {
                showStaffToast('Enter a deduction amount, or set Deduction Applied to No.', 'error');
                return;
            }
            jsonFetch(`/staff-complaints/${id}`, 'PUT', fields).then(({ ok, data }) => {
                if (ok && data.success) {
                    showStaffToast(data.message || 'Complaint updated.');
                    window.location.reload();
                } else {
                    const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not update complaint.');
                    showStaffToast(firstError, 'error');
                }
            });
        };
        window.deleteCo = function (id) {
            if (!confirm('Delete this complaint? Any linked deduction will be removed too.')) return;
            jsonDelete(`/staff-complaints/${id}`).then(({ ok, data }) => {
                if (ok && data.success) {
                    document.querySelector(`#complaintsTable tr[data-entry-row="${id}"]`)?.remove();
                    document.getElementById(`complaint-details-${id}`)?.remove();
                    delete coEntries[id];
                    showStaffToast(data.message || 'Complaint deleted.');
                    maybeShowEmptyState('#complaintsTable', 'coEmptyRow', 10, 'No complaints logged.');
                } else {
                    showStaffToast(data.message || 'Could not delete complaint.', 'error');
                }
            });
        };

        /* ---------- GENERATE STAFF NOTICE MODAL (from a complaint) ---------- */
        let activeNoticeComplaintId = null;

        window.openGenerateNoticeModal = function (id) {
            const e = coEntries[id];
            if (!e) return;
            activeNoticeComplaintId = id;

            document.getElementById('gnContext').textContent = `${e.reference_number} — ${e.staff_names}`;
            document.getElementById('gnStaffChecks').innerHTML = (e.staff_ids || []).map(staffId => {
                const staff = ALL_STAFF.find(s => s.id === Number(staffId));
                if (!staff) return '';
                return `<div class="form-check">
                    <input class="form-check-input" type="checkbox" value="${staff.id}" id="gnStaff${staff.id}" checked>
                    <label class="form-check-label" for="gnStaff${staff.id}">${escapeHtml(staff.name)}</label>
                </div>`;
            }).join('');

            document.getElementById('gnType').value = 'Written Warning';
            document.getElementById('gnSubject').value = `Complaint ${e.reference_number}: ${e.category}`;

            // Instant local draft so the modal isn't blank while the AI call is in flight.
            let fallbackSummary = `Complaint ${e.reference_number} logged on ${e.complaint_date_label} at ${branchLabel(e.branch)}.`;
            if (e.service_name) fallbackSummary += ` Service: ${e.service_name}.`;
            fallbackSummary += `\n\n${e.description}`;

            const summaryField = document.getElementById('gnSummary');
            const correctiveField = document.getElementById('gnCorrectiveActions');
            summaryField.value = fallbackSummary;
            correctiveField.value = '';

            new bootstrap.Modal(document.getElementById('generateNoticeModal')).show();
            requestAiNoticeDraft(id, fallbackSummary);
        };

        function requestAiNoticeDraft(complaintId, fallbackSummary) {
            const summaryField = document.getElementById('gnSummary');
            const correctiveField = document.getElementById('gnCorrectiveActions');
            const status = document.getElementById('gnAiStatus');
            const confirmBtn = document.getElementById('gnConfirmBtn');

            status.textContent = 'Drafting with AI…';
            status.classList.remove('d-none', 'text-danger');
            status.classList.add('text-muted');
            summaryField.disabled = true;
            correctiveField.disabled = true;
            confirmBtn.disabled = true;

            fetch(`/staff-complaints/${complaintId}/draft-notice-ai`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                    body: '{}',
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    // Only apply the draft if this response still matches the open modal
                    // (guards against a stray response landing after the user moved on).
                    if (activeNoticeComplaintId !== complaintId) return;

                    if (ok && data.success) {
                        summaryField.value = data.summary || fallbackSummary;
                        correctiveField.value = data.corrective_actions || '';
                        if (data.source === 'ai') {
                            status.textContent = 'Drafted with AI — review before sending.';
                            status.classList.remove('text-danger');
                        } else {
                            status.textContent = (data.note || 'Used a standard template — review before sending.');
                            status.classList.add('text-danger');
                        }
                    } else {
                        status.textContent = 'Could not reach AI — using the standard template. Review before sending.';
                        status.classList.add('text-danger');
                    }
                })
                .catch(() => {
                    if (activeNoticeComplaintId !== complaintId) return;
                    status.textContent = 'Could not reach AI — using the standard template. Review before sending.';
                    status.classList.add('text-danger');
                })
                .finally(() => {
                    if (activeNoticeComplaintId !== complaintId) return;
                    summaryField.disabled = false;
                    correctiveField.disabled = false;
                    confirmBtn.disabled = false;
                });
        }

        const gnConfirmBtn = document.getElementById('gnConfirmBtn');
        if (gnConfirmBtn) {
            gnConfirmBtn.addEventListener('click', function () {
                const staffIds = Array.from(document.querySelectorAll('#gnStaffChecks input:checked')).map(c => c.value);
                const fields = {
                    staff_ids: staffIds,
                    type: document.getElementById('gnType').value,
                    subject: document.getElementById('gnSubject').value,
                    summary: document.getElementById('gnSummary').value,
                    corrective_actions: document.getElementById('gnCorrectiveActions').value,
                };
                if (!staffIds.length) {
                    showStaffToast('Select at least one staff member to notify.', 'error');
                    return;
                }
                if (!fields.subject.trim() || !fields.summary.trim()) {
                    showStaffToast('Subject and summary are required.', 'error');
                    return;
                }
                jsonFetch(`/staff-complaints/${activeNoticeComplaintId}/generate-notice`, 'POST', fields).then(({ ok, data }) => {
                    if (ok && data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('generateNoticeModal'))?.hide();
                        showStaffToast(data.message || 'Staff notice(s) issued.');
                        window.location.reload();
                    } else {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not draft notice.');
                        showStaffToast(firstError, 'error');
                    }
                });
            });
        }

        window.toggleHrResolutionDeduction = function (complaintId, value) {
            const wrap = document.getElementById(`hrResDedWrap-${complaintId}`);
            const input = document.getElementById(`hrResDedInput-${complaintId}`);
            if (!wrap || !input) return;
            const show = value === 'Y';
            wrap.classList.toggle('d-none', !show);
            input.required = show;
            if (!show) {
                input.value = '';
            }
        };

        window.openEditComplaintModal = function (id) {
            const e = coEntries[id];
            if (!e) return;
            const modalEl = document.getElementById('editComplaintModal');
            const formEl = document.getElementById('editComplaintForm');
            const titleEl = document.getElementById('editComplaintModalTitle');
            const whatHappenedEl = document.getElementById('editCoWhatHappened');
            const correctiveActionEl = document.getElementById('editCoCorrectiveAction');
            if (!modalEl || !formEl) return;

            formEl.action = `/staff-complaints/${id}`;
            if (titleEl) {
                titleEl.textContent = `Edit Incident Details — ${e.reference_number || ''}`;
            }
            if (whatHappenedEl) {
                whatHappenedEl.value = e.what_happened || e.description || '';
            }
            if (correctiveActionEl) {
                correctiveActionEl.value = e.corrective_action || '';
            }

            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        };

        const logComplaintModalEl = document.getElementById('logComplaintModal');
        if (logComplaintModalEl) {
            logComplaintModalEl.addEventListener('show.bs.modal', function () {
                const ca = document.getElementById('coQaCorrectiveAction');
                if (ca) ca.value = '';
            });
        }

        const NOTICE_MANAGERS = @json(\App\Models\StaffNotice::MANAGERS);
        let noticeStore = {};

        function renderNoticeAckHtml(n) {
            const isAck = (n.acknowledged === 'Y') || !!n.acknowledged_by;
            if (isAck) {
                const byName = n.acknowledged_by || 'Manager';
                const onDate = n.acknowledged_at_label || n.notice_date_label || '';
                const label = n.acknowledged_by
                    ? `Acknowledged by ${escapeHtml(byName)}${onDate ? ' on ' + escapeHtml(onDate) : ''}`
                    : 'Acknowledged';
                return `<span class="ack-badge y"><i class="bi bi-check-circle-fill me-1"></i>${label}</span>`;
            }

            if (!CAN_EDIT_STAFF) {
                return `<span class="ack-badge n">Pending Acknowledgment</span>`;
            }

            const opts = NOTICE_MANAGERS.map(m => `<option value="${escapeHtml(m)}">${escapeHtml(m)}</option>`).join('');
            return `
                <div class="d-flex align-items-center gap-1" data-html2canvas-ignore="true">
                    <select class="form-select form-select-sm py-0 px-2" id="ack-manager-${n.id}" style="width: auto; min-width: 95px; height: 28px; font-size: 12px;">
                        ${opts}
                    </select>
                    <button type="button" class="btn btn-sm btn-primary py-0 px-2" id="ack-btn-${n.id}" style="height: 28px; font-size: 12px;" onclick="acknowledgeNotice(${n.id})">
                        <i class="bi bi-check2 me-1"></i>Acknowledge
                    </button>
                </div>
            `;
        }

        window.openViewNoticeModal = function (referenceNumber, notices) {
            const modalEl = document.getElementById('viewNoticeModal');
            const titleEl = document.getElementById('viewNoticeModalTitle');
            const bodyEl = document.getElementById('viewNoticeModalBody');
            if (!modalEl || !bodyEl) return;

            titleEl.textContent = `Staff Notice — ${referenceNumber}`;
            const list = Array.isArray(notices) ? notices : [];

            if (!list.length) {
                bodyEl.innerHTML = '<p class="text-muted small mb-0">No staff notices found for this incident.</p>';
            } else {
                list.forEach(item => {
                    if (item && item.id) {
                        noticeStore[item.id] = Object.assign({}, item, noticeStore[item.id] || {});
                    }
                });

                bodyEl.innerHTML = list.map(raw => {
                    const n = (raw && raw.id && noticeStore[raw.id]) ? noticeStore[raw.id] : raw;
                    return `
                        <div class="payroll-subcard mb-3" id="notice-card-${n.id}" data-notice-card="${n.id}" data-staff-name="${escapeHtml(n.staff_name || 'Staff')}" data-notice-date="${escapeHtml(n.notice_date || n.notice_date_label || '')}">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                <div>
                                    <div class="fw-bold">${escapeHtml(n.staff_name || '—')} &bull; <span class="text-warning">${escapeHtml(n.type || 'Written Warning')}</span></div>
                                    <div class="text-muted small">${escapeHtml(n.notice_date_label || '')} &mdash; ${escapeHtml(n.subject || '')}</div>
                                    <div class="text-muted small mt-1">Client: ${escapeHtml(n.client_name || '—')} | Service: ${escapeHtml(n.service_received || '—')} | Appt Date: ${escapeHtml(n.appt_date || '—')}</div>
                                </div>
                                <div id="notice-ack-wrap-${n.id}">
                                    ${renderNoticeAckHtml(n)}
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="fw-bold small text-uppercase text-muted mb-1">What Happened</div>
                                <div class="p-2 rounded border border-secondary border-opacity-25 small" style="white-space: pre-wrap;">${escapeHtml(n.description || '—')}</div>
                            </div>
                            <div class="mb-2">
                                <div class="fw-bold small text-uppercase text-muted mb-1">Corrective Action to be taken</div>
                                <div class="p-2 rounded border border-secondary border-opacity-25 small" style="white-space: pre-wrap;">${escapeHtml(n.corrective_actions || '—')}</div>
                            </div>
                            <div class="d-flex justify-content-end mt-2 pt-2 border-top border-secondary border-opacity-25" data-html2canvas-ignore="true">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-download-notice" id="download-jpg-btn-${n.id}" onclick="downloadNoticeJpg(${n.id})">
                                    <i class="bi bi-download me-1"></i> Download JPG
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        };

        window.acknowledgeNotice = function (noticeId) {
            const selectEl = document.getElementById(`ack-manager-${noticeId}`);
            const btnEl = document.getElementById(`ack-btn-${noticeId}`);
            const wrapEl = document.getElementById(`notice-ack-wrap-${noticeId}`);
            const manager = selectEl?.value || '';

            if (!manager) {
                showStaffToast('Please select a manager.', 'error');
                return;
            }

            if (btnEl) btnEl.disabled = true;
            if (selectEl) selectEl.disabled = true;

            jsonFetch(`/staff-notices/${noticeId}/acknowledge`, 'POST', { acknowledged_by: manager })
                .then(({ ok, data }) => {
                    if (ok && data.success && data.entry) {
                        noticeStore[noticeId] = Object.assign({}, noticeStore[noticeId] || {}, data.entry);
                        if (wrapEl) {
                            wrapEl.innerHTML = renderNoticeAckHtml(noticeStore[noticeId]);
                        }
                        showStaffToast(data.message || `Acknowledged by ${manager}.`);
                    } else {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not acknowledge notice.');
                        showStaffToast(firstError, 'error');
                        if (btnEl) btnEl.disabled = false;
                        if (selectEl) selectEl.disabled = false;
                    }
                })
                .catch(() => {
                    showStaffToast('Network error while acknowledging notice.', 'error');
                    if (btnEl) btnEl.disabled = false;
                    if (selectEl) selectEl.disabled = false;
                });
        };

        window.downloadNoticeJpg = function (noticeId) {
            const cardEl = document.getElementById(`notice-card-${noticeId}`);
            const btnEl = document.getElementById(`download-jpg-btn-${noticeId}`);
            if (!cardEl) return;

            if (typeof html2canvas === 'undefined') {
                showStaffToast('Image export library is still loading. Please try again in a moment.', 'error');
                return;
            }

            const stored = noticeStore[noticeId] || {};
            const rawStaff = stored.staff_name || cardEl.dataset.staffName || 'Staff';
            const rawDate = stored.notice_date || stored.notice_date_label || cardEl.dataset.noticeDate || new Date().toISOString().slice(0, 10);

            const cleanStaff = String(rawStaff).trim().replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'Staff';
            const cleanDate = String(rawDate).trim().replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'Date';
            const filename = `Staff-Notice-${cleanStaff}-${cleanDate}.jpg`;

            const origHtml = btnEl ? btnEl.innerHTML : '';
            if (btnEl) {
                btnEl.disabled = true;
                btnEl.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Exporting...';
            }

            html2canvas(cardEl, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#241e1c',
                logging: false,
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = filename;
                link.href = canvas.toDataURL('image/jpeg', 0.95);
                document.body.appendChild(link);
                link.click();
                link.remove();
            }).catch(() => {
                showStaffToast('Could not generate JPG image.', 'error');
            }).finally(() => {
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.innerHTML = origHtml;
                }
            });
        };

        const coQaSaveBtn = document.getElementById('coQaSaveBtn');
        if (coQaSaveBtn) {
            wireClientPicker('coQa');
            renderStaffTagSelect('coQa');
            coQaSaveBtn.addEventListener('click', function () {
                const whatHappened = document.getElementById('coQaWhatHappened')?.value || '';
                const correctiveAction = document.getElementById('coQaCorrectiveAction')?.value || '';
                const fields = {
                    complaint_date: document.getElementById('coQaDate').value,
                    complaint_time: document.getElementById('coQaTime').value,
                    branch: document.getElementById('coQaBranch').value,
                    staff_ids: staffTagSelectedIds('coQa'),
                    customer_name: document.getElementById('coQaClientName').value,
                    customer_phone: document.getElementById('coQaClientPhone').value,
                    service_id: document.getElementById('coQaService').value,
                    category: document.getElementById('coQaCategory').value,
                    what_happened: whatHappened,
                    description: whatHappened,
                    corrective_action: correctiveAction,
                };
                if (!fields.complaint_date || !fields.branch || !fields.staff_ids.length || !fields.customer_name.trim() || !fields.service_id || !fields.category || !fields.what_happened.trim()) {
                    showStaffToast('Date, branch, staff involved, client name, service received, category, and What Happened are required.', 'error');
                    return;
                }
                jsonFetch(`{{ route('staff-complaints.store') }}`, 'POST', fields).then(({ ok, data }) => {
                    if (ok && data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('logComplaintModal'))?.hide();
                        showStaffToast(data.message || 'Complaint logged.');
                        window.location.reload();
                    } else {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not add complaint.');
                        showStaffToast(firstError, 'error');
                    }
                });
            });
        }

        /* ---------- DEDUCTIONS ---------- */
        window.filterByComplaintRef = function (referenceNumber) {
            if (!referenceNumber) return;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(referenceNumber).catch(() => {});
            }

            // Check if the complaint row is already visible on the current page
            const matchingEntry = Object.values(coEntries).find(e => e.reference_number === referenceNumber);
            if (matchingEntry) {
                const row = document.querySelector(`#complaintsTable tr[data-entry-row="${matchingEntry.id}"]`);
                const detailEl = document.getElementById(`complaint-details-${matchingEntry.id}`);
                if (row) {
                    const searchInput = document.getElementById('complaintsSearchInput');
                    if (searchInput) searchInput.value = referenceNumber;
                    showStaffToast(`Copied ${referenceNumber} & jumped to complaint.`);
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (detailEl && !detailEl.classList.contains('show')) {
                        bootstrap.Collapse.getOrCreateInstance(detailEl).show();
                    }
                    return;
                }
            }

            // Otherwise submit the filter form to load that complaint
            const searchInput = document.getElementById('complaintsSearchInput');
            const filterForm = document.getElementById('complaintsFilterForm');
            if (searchInput && filterForm) {
                searchInput.value = referenceNumber;
                filterForm.submit();
            }
        };

        let dedEntries = {};
        document.querySelectorAll('#deductionsTable tr[data-entry-row]').forEach(row => {
            const entry = JSON.parse(row.dataset.entry);
            dedEntries[entry.id] = entry;
        });

        function dedViewRowHtml(e) {
            const refCode = e.complaint_reference || (OPEN_COMPLAINTS.find(x => x.id === e.complaint_id)?.reference_number) || '';
            const refCell = refCode
                ? `<span class="ref-badge ref-badge-action" title="Click to copy &amp; filter by ${escapeHtml(refCode)}" onclick="filterByComplaintRef('${escapeHtml(refCode)}')">${escapeHtml(refCode)} <i class="bi bi-copy" style="font-size: 10px;"></i></span>`
                : '<span class="text-muted small">—</span>';
            return `
                <td>${e.deduction_date_label}</td>
                <td class="fw-semibold">${escapeHtml(e.staff_name)}</td>
                <td>QAR ${Number(e.amount).toFixed(2)}</td>
                <td>${escapeHtml(e.reason)}</td>
                <td>${refCell}</td>
            `;
        }

        const dedQaSaveBtn = document.getElementById('dedQaSaveBtn');
        if (dedQaSaveBtn) {
            dedQaSaveBtn.addEventListener('click', function () {
                const fields = {
                    deduction_date: document.getElementById('dedQaDate').value,
                    staff_id: document.getElementById('dedQaStaff').value,
                    amount: document.getElementById('dedQaAmount').value,
                    reason: document.getElementById('dedQaReason').value,
                    complaint_id: document.getElementById('dedQaComplaint').value,
                };
                if (!fields.deduction_date || !fields.staff_id || !fields.amount || !fields.reason.trim()) {
                    showStaffToast('Date, staff, amount, and reason are required.', 'error');
                    return;
                }
                jsonFetch(`{{ route('staff-deductions.store') }}`, 'POST', fields).then(({ ok, data }) => {
                    if (ok && data.success) {
                        bootstrap.Modal.getInstance(document.getElementById('addDeductionModal'))?.hide();
                        showStaffToast(data.message || 'Deduction logged.');
                        window.location.reload();
                    } else {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not add deduction.');
                        showStaffToast(firstError, 'error');
                    }
                });
            });
        }

        /* ---------- NOTICES ---------- */
        window.deleteNo = function (id) {
            if (!confirm('Delete this notice?')) return;
            jsonDelete(`/staff-notices/${id}`).then(({ ok, data }) => {
                if (ok && data.success) {
                    document.querySelector(`[data-notice-card="${id}"]`)?.remove();
                    showStaffToast(data.message || 'Notice deleted.');
                    window.location.reload();
                } else {
                    showStaffToast(data.message || 'Could not delete notice.', 'error');
                }
            });
        };

    </script>
@endsection
