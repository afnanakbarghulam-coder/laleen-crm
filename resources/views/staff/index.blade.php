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
</style>

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Team members <span class="badge bg-secondary">{{ $staff->count() }}</span></h4>
            <p class="text-muted small mb-0">Manage your staff, payroll, operations, and CRM access.</p>
        </div>
        @moduleEdit('staff_management')
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addStaffModal" onclick="resetStaffForm()">
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
        <button type="button" class="staff-tab-btn btn btn-sm {{ $activeTab === 'payroll' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="payroll">Payroll &amp; Overtime</button>
        <button type="button" class="staff-tab-btn btn btn-sm {{ $activeTab === 'complaints' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="complaints">Complaints &amp; Deductions</button>
        <button type="button" class="staff-tab-btn btn btn-sm {{ $activeTab === 'notices' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="notices">Notices</button>
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

    {{-- ================= PAYROLL & OVERTIME ================= --}}
    <div id="staff-pane-payroll" class="staff-tab-pane {{ $activeTab === 'payroll' ? '' : 'd-none' }}">
        <div class="mb-3">
            <h6 class="fw-bold mb-1">Payroll</h6>
            <p class="text-muted small mb-3">Net Salary = Base Salary + Overtime Pay &minus; Deductions. Commission is never part of this calculation.</p>

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
                <table class="table team-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th>Branch</th>
                            <th class="text-end">Base Salary</th>
                            <th class="text-end">Overtime Hours</th>
                            <th class="text-end">Overtime Pay</th>
                            <th class="text-end">Deductions</th>
                            <th class="text-end">Net Salary</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payrollRows as $row)
                            <tr>
                                <td class="team-name">{{ $row['name'] }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $row['branch'])) }}</td>
                                <td class="text-end">QAR {{ number_format($row['base_salary'], 2) }}</td>
                                <td class="text-end">{{ number_format($row['overtime_hours'], 2) }}</td>
                                <td class="text-end">QAR {{ number_format($row['overtime_pay'], 2) }}</td>
                                <td class="text-end text-danger">- QAR {{ number_format($row['deductions'], 2) }}</td>
                                <td class="text-end fw-bold">QAR {{ number_format($row['net_salary'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No active staff match this branch filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <h6 class="fw-bold mb-3">Overtime ledger</h6>
        <div class="card p-0 mb-3" style="overflow:hidden;">
            <div class="table-responsive">
                <table class="table team-table mb-0" id="overtimeTable">
                    <thead>
                        <tr>
                            <th style="min-width:130px;">Date</th>
                            <th style="min-width:160px;">Staff</th>
                            <th style="min-width:90px;">Hours</th>
                            <th style="min-width:110px;">Rate (QAR/hr)</th>
                            <th style="min-width:100px;">Pay</th>
                            <th style="min-width:150px;">Note</th>
                            <th style="min-width:90px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @moduleEdit('staff_management')
                            <tr class="staff-quick-add-row" id="otQuickAddRow">
                                <td><input type="date" class="form-control form-control-sm" id="otQaDate" value="{{ now()->format('Y-m-d') }}"></td>
                                <td>
                                    <select class="form-select form-select-sm" id="otQaStaff">
                                        <option value="">Select staff</option>
                                        @foreach ($allStaff as $s)
                                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" min="0.25" max="24" step="0.25" class="form-control form-control-sm" id="otQaHours" placeholder="Hours"></td>
                                <td><input type="number" min="0" step="0.01" class="form-control form-control-sm" id="otQaRate" placeholder="Default"></td>
                                <td class="text-muted small">—</td>
                                <td><input type="text" class="form-control form-control-sm" id="otQaNote" placeholder="Note"></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-primary" id="otQaSaveBtn" title="Add entry"><i class="bx bx-plus"></i></button>
                                </td>
                            </tr>
                        @endmoduleEdit

                        @forelse ($overtimeEntries as $entry)
                            <tr data-entry-row="{{ $entry->id }}" data-entry='@json($entry->toEditPayload())'>
                                <td>{{ $entry->entry_date->format('d M Y') }}</td>
                                <td class="fw-semibold">{{ $entry->staff->name ?? '—' }}</td>
                                <td>{{ $entry->hours }}</td>
                                <td>{{ $entry->rate !== null ? 'QAR ' . number_format($entry->rate, 2) : 'Default' }}</td>
                                <td>QAR {{ number_format($entry->pay(), 2) }}</td>
                                <td>{{ $entry->note ?: '—' }}</td>
                                <td class="text-end">
                                    @moduleEdit('staff_management')
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="startEditOt({{ $entry->id }})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteOt({{ $entry->id }})" title="Delete"><i class="bi bi-trash"></i></button>
                                        </div>
                                    @endmoduleEdit
                                </td>
                            </tr>
                        @empty
                            <tr id="otEmptyRow"><td colspan="7" class="text-center text-muted py-4">No overtime entries logged for this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mb-4">{{ $overtimeEntries->links() }}</div>
    </div>

    {{-- ================= COMPLAINTS & DEDUCTIONS ================= --}}
    <div id="staff-pane-complaints" class="staff-tab-pane {{ $activeTab === 'complaints' ? '' : 'd-none' }}">
        <form method="GET" action="{{ route('staffs.index') }}" class="row g-3 align-items-end mb-4">
            <input type="hidden" name="tab" value="complaints">
            <div class="col-md-4">
                <label class="form-label">Staff member</label>
                <select name="complaints_staff" class="form-select">
                    <option value="">All staff</option>
                    @foreach ($allStaff as $s)
                        <option value="{{ $s->id }}" {{ $complaintsStaffFilter === $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                @if ($complaintsStaffFilter)
                    <a href="{{ route('staffs.index', ['tab' => 'complaints']) }}" class="btn btn-outline-secondary w-100">Reset</a>
                @endif
            </div>
        </form>

        <h6 class="fw-bold mb-1">Complaints &amp; Feedback</h6>
        <p class="text-muted small mb-3">Deduction Applied automatically syncs to that staff member's deductions total in Payroll &amp; Overtime.</p>
        <div class="card p-0 mb-3" style="overflow:hidden;">
            <div class="table-responsive">
                <table class="table team-table mb-0" id="complaintsTable">
                    <thead>
                        <tr>
                            <th style="min-width:110px;">Ref #</th>
                            <th style="min-width:140px;">Date &amp; Time</th>
                            <th style="min-width:120px;">Branch</th>
                            <th style="min-width:220px;">Client</th>
                            <th style="min-width:160px;">Service Received</th>
                            <th style="min-width:160px;">Category</th>
                            <th style="min-width:150px;">Staff Involved</th>
                            <th style="min-width:130px;">Deduction</th>
                            <th style="min-width:220px;">Feedback / Complaint Detail</th>
                            <th style="min-width:170px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @moduleEdit('staff_management')
                            <tr class="staff-quick-add-row" id="coQuickAddRow">
                                <td class="text-muted small">Auto</td>
                                <td>
                                    <input type="date" class="form-control form-control-sm mb-1" id="coQaDate" value="{{ now()->format('Y-m-d') }}">
                                    <input type="time" class="form-control form-control-sm" id="coQaTime" value="{{ now()->format('H:i') }}">
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" id="coQaBranch">
                                        <option value="">Select branch</option>
                                        @foreach (\App\Models\StaffComplaint::BRANCHES as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <div class="client-picker">
                                        <input type="text" class="form-control form-control-sm" id="coQaClientQuery" placeholder="Search client (name/phone)" autocomplete="off">
                                        <input type="hidden" id="coQaClientName">
                                        <input type="hidden" id="coQaClientPhone">
                                        <div class="client-picker-results d-none" id="coQaClientResults"></div>
                                    </div>
                                    <button type="button" class="btn btn-link btn-sm p-0 mt-1" onclick="openQuickAddClientModal('coQa')">+ Add new client</button>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" id="coQaService">
                                        <option value="">Select service</option>
                                        @foreach ($services as $svc)
                                            <option value="{{ $svc->id }}">{{ $svc->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" id="coQaCategory">
                                        @foreach (\App\Models\StaffComplaint::CATEGORIES as $cat)
                                            <option value="{{ $cat }}">{{ $cat }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
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
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" id="coQaDeductionApplied" onchange="toggleDeductionAmount('coQa', this.value)">
                                        <option value="N">No</option>
                                        <option value="Y">Yes</option>
                                    </select>
                                    <input type="number" min="0" step="0.01" class="form-control form-control-sm mt-1 d-none" id="coQaDeductionAmount" placeholder="QAR">
                                </td>
                                <td><input type="text" class="form-control form-control-sm" id="coQaDescription" placeholder="Feedback / complaint detail"></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-primary" id="coQaSaveBtn" title="Add complaint"><i class="bx bx-plus"></i></button>
                                </td>
                            </tr>
                        @endmoduleEdit

                        @forelse ($complaints as $complaint)
                            <tr data-entry-row="{{ $complaint->id }}" data-entry='@json($complaint->toEditPayload())'>
                                <td>
                                    <span class="ref-badge">{{ $complaint->reference_number }}</span>
                                    @if ($complaint->notices()->count())
                                        <div class="text-muted small mt-1">{{ $complaint->notices()->count() }} notice{{ $complaint->notices()->count() > 1 ? 's' : '' }}</div>
                                    @endif
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
                                <td>{{ $complaint->description }}</td>
                                <td class="text-end">
                                    @moduleEdit('staff_management')
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="openGenerateNoticeModal({{ $complaint->id }})" title="Generate staff notice"><i class="bi bi-file-earmark-text"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="startEditCo({{ $complaint->id }})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCo({{ $complaint->id }})" title="Delete"><i class="bi bi-trash"></i></button>
                                        </div>
                                    @endmoduleEdit
                                </td>
                            </tr>
                        @empty
                            <tr id="coEmptyRow"><td colspan="10" class="text-center text-muted py-4">No complaints logged.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mb-4">{{ $complaints->links() }}</div>

        <h6 class="fw-bold mb-3">Deductions</h6>
        <div class="card p-0 mb-3" style="overflow:hidden;">
            <div class="table-responsive">
                <table class="table team-table mb-0" id="deductionsTable">
                    <thead>
                        <tr>
                            <th style="min-width:130px;">Date</th>
                            <th style="min-width:150px;">Staff</th>
                            <th style="min-width:110px;">Amount</th>
                            <th style="min-width:170px;">Reason</th>
                            <th style="min-width:220px;">Linked complaint</th>
                            <th style="min-width:90px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @moduleEdit('staff_management')
                            <tr class="staff-quick-add-row" id="dedQuickAddRow">
                                <td><input type="date" class="form-control form-control-sm" id="dedQaDate" value="{{ now()->format('Y-m-d') }}"></td>
                                <td>
                                    <select class="form-select form-select-sm" id="dedQaStaff">
                                        <option value="">Select staff</option>
                                        @foreach ($allStaff as $s)
                                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="number" min="0" step="0.01" class="form-control form-control-sm" id="dedQaAmount" placeholder="QAR"></td>
                                <td><input type="text" class="form-control form-control-sm" id="dedQaReason" placeholder="Reason"></td>
                                <td>
                                    <select class="form-select form-select-sm" id="dedQaComplaint">
                                        <option value="">No linked complaint</option>
                                        @foreach ($openComplaints as $c)
                                            <option value="{{ $c->id }}">{{ $c->reference_number }} — {{ $c->staffMembers->pluck('name')->implode(', ') ?: '—' }} ({{ $c->complaint_date->format('d M Y') }})</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-primary" id="dedQaSaveBtn" title="Add deduction"><i class="bx bx-plus"></i></button>
                                </td>
                            </tr>
                        @endmoduleEdit

                        @forelse ($deductions as $deduction)
                            <tr data-entry-row="{{ $deduction->id }}" data-entry='@json($deduction->toEditPayload())'>
                                <td>{{ $deduction->deduction_date->format('d M Y') }}</td>
                                <td class="fw-semibold">{{ $deduction->staff->name ?? '—' }}</td>
                                <td>QAR {{ number_format($deduction->amount, 2) }}</td>
                                <td>{{ $deduction->reason }}</td>
                                <td>{{ $deduction->complaint->reference_number ?? '—' }}</td>
                                <td class="text-end">
                                    @moduleEdit('staff_management')
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="startEditDed({{ $deduction->id }})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteDed({{ $deduction->id }})" title="Delete"><i class="bi bi-trash"></i></button>
                                        </div>
                                    @endmoduleEdit
                                </td>
                            </tr>
                        @empty
                            <tr id="dedEmptyRow"><td colspan="6" class="text-center text-muted py-4">No deductions logged.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mb-4">{{ $deductions->links() }}</div>
    </div>

    {{-- ================= NOTICES ================= --}}
    <div id="staff-pane-notices" class="staff-tab-pane {{ $activeTab === 'notices' ? '' : 'd-none' }}">
        <p class="text-muted small mb-3">Notices are drafted from the Complaints &amp; Feedback tab (via "Generate staff notice" on a complaint row) — they can be reviewed, edited, or removed here, but not created directly.</p>

        <form method="GET" action="{{ route('staffs.index') }}" class="row g-3 align-items-end mb-4">
            <input type="hidden" name="tab" value="notices">
            <div class="col-md-4">
                <label class="form-label">Staff member</label>
                <select name="notices_staff" class="form-select">
                    <option value="">All staff</option>
                    @foreach ($allStaff as $s)
                        <option value="{{ $s->id }}" {{ $noticesStaffFilter === $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                @if ($noticesStaffFilter)
                    <a href="{{ route('staffs.index', ['tab' => 'notices']) }}" class="btn btn-outline-secondary w-100">Reset</a>
                @endif
            </div>
        </form>

        <div class="card p-0 mb-3" style="overflow:hidden;">
            <div class="table-responsive">
                <table class="table team-table mb-0" id="noticesTable">
                    <thead>
                        <tr>
                            <th style="min-width:130px;">Date</th>
                            <th style="min-width:150px;">Staff</th>
                            <th style="min-width:150px;">Type</th>
                            <th style="min-width:150px;">Subject</th>
                            <th style="min-width:200px;">Description</th>
                            <th style="min-width:200px;">Corrective Actions</th>
                            <th style="min-width:120px;">Acknowledged</th>
                            <th style="min-width:90px;"></th>
                        </tr>
                    </thead>
                    <tbody>

                        @forelse ($notices as $notice)
                            <tr data-entry-row="{{ $notice->id }}" data-entry='@json($notice->toEditPayload())'>
                                <td>{{ $notice->notice_date->format('d M Y') }}</td>
                                <td class="fw-semibold">{{ $notice->staff->name ?? '—' }}</td>
                                <td>{{ $notice->type }}</td>
                                <td>{{ $notice->subject }}</td>
                                <td>{{ $notice->description ?: '—' }}</td>
                                <td>{{ $notice->corrective_actions ?: '—' }}</td>
                                <td><span class="ack-badge {{ strtolower($notice->acknowledged) }}">{{ $notice->acknowledged === 'Y' ? 'Acknowledged' : 'Not acknowledged' }}</span></td>
                                <td class="text-end">
                                    @moduleEdit('staff_management')
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-outline-warning" onclick="startEditNo({{ $notice->id }})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteNo({{ $notice->id }})" title="Delete"><i class="bi bi-trash"></i></button>
                                        </div>
                                    @endmoduleEdit
                                </td>
                            </tr>
                        @empty
                            <tr id="noEmptyRow"><td colspan="8" class="text-center text-muted py-4">No notices logged.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mb-4">{{ $notices->links() }}</div>
    </div>

    @moduleEdit('staff_management')
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
                        JSON.parse(this.dataset.serviceIds),
                        this.dataset.hasAccess === '1',
                        this.dataset.accessRole,
                        this.dataset.accessEmail
                    );
                });
            });
        </script>
    @endmoduleEdit

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
            });
        });

        /* ---------------- PAYROLL & OPERATIONS: INLINE LEDGERS ---------------- */
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
                    document.querySelector(`#overtimeTable tr[data-entry-row="${id}"]`)?.remove();
                    delete otEntries[id];
                    showStaffToast(data.message || 'Overtime entry deleted.');
                    maybeShowEmptyState('#overtimeTable', 'otEmptyRow', 7, 'No overtime entries logged for this range.');
                } else {
                    showStaffToast(data.message || 'Could not delete entry.', 'error');
                }
            });
        };

        const otQaSaveBtn = document.getElementById('otQaSaveBtn');
        if (otQaSaveBtn) {
            otQaSaveBtn.addEventListener('click', function () {
                const fields = {
                    entry_date: document.getElementById('otQaDate').value,
                    staff_id: document.getElementById('otQaStaff').value,
                    hours: document.getElementById('otQaHours').value,
                    rate: document.getElementById('otQaRate').value,
                    note: document.getElementById('otQaNote').value,
                };
                if (!fields.entry_date || !fields.staff_id || !fields.hours) {
                    showStaffToast('Date, staff, and hours are required.', 'error');
                    return;
                }
                jsonFetch(`{{ route('staff-overtime.store') }}`, 'POST', fields).then(({ ok, data }) => {
                    if (ok && data.success) {
                        otEntries[data.entry.id] = data.entry;
                        document.getElementById('otEmptyRow')?.remove();
                        const tr = document.createElement('tr');
                        tr.setAttribute('data-entry-row', data.entry.id);
                        tr.innerHTML = otViewRowHtml(data.entry);
                        document.getElementById('otQuickAddRow').insertAdjacentElement('afterend', tr);
                        document.getElementById('otQaStaff').value = '';
                        document.getElementById('otQaHours').value = '';
                        document.getElementById('otQaRate').value = '';
                        document.getElementById('otQaNote').value = '';
                        showStaffToast(data.message || 'Overtime entry logged.');
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
                return `<div class="d-flex gap-1 justify-content-end">
                    <button type="button" class="btn btn-sm btn-primary" onclick="saveEditCo(${id})" title="Save"><i class="bi bi-check-lg"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cancelEditCo(${id})" title="Cancel"><i class="bi bi-x-lg"></i></button>
                </div>`;
            }
            return `<div class="d-flex gap-1 justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-info" onclick="openGenerateNoticeModal(${id})" title="Generate staff notice"><i class="bi bi-file-earmark-text"></i></button>
                <button type="button" class="btn btn-sm btn-outline-warning" onclick="startEditCo(${id})" title="Edit"><i class="bi bi-pencil-square"></i></button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCo(${id})" title="Delete"><i class="bi bi-trash"></i></button>
            </div>`;
        }

        function coDeductionCellHtml(e) {
            return e.deduction_applied === 'Y'
                ? `<span class="status-badge open">QAR ${Number(e.deduction_amount).toFixed(2)}</span>`
                : '<span class="text-muted small">No</span>';
        }

        function coViewRowHtml(e) {
            return `
                <td><span class="ref-badge">${escapeHtml(e.reference_number)}</span>${e.notice_count > 0 ? `<div class="text-muted small mt-1">${e.notice_count} notice${e.notice_count > 1 ? 's' : ''}</div>` : ''}</td>
                <td>${e.complaint_date_label}<div class="text-muted small">${e.time_label}</div></td>
                <td>${branchLabel(e.branch)}</td>
                <td>${escapeHtml(e.customer_name)}<div class="text-muted small">${escapeHtml(e.customer_phone) || '—'}</div></td>
                <td>${escapeHtml(e.service_name) || '—'}</td>
                <td>${escapeHtml(e.category)}</td>
                <td class="fw-semibold">${escapeHtml(e.staff_names)}</td>
                <td>${coDeductionCellHtml(e)}</td>
                <td>${escapeHtml(e.description)}</td>
                <td class="text-end">${coActionsHtml(e.id, 'view')}</td>
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
                <td class="text-end">${coActionsHtml(e.id, 'edit')}</td>
            `;
        }

        window.startEditCo = function (id) {
            const row = document.querySelector(`#complaintsTable tr[data-entry-row="${id}"]`);
            if (!row) return;
            row.innerHTML = coEditRowHtml(coEntries[id]);
            wireClientPicker(`coEdit${id}`);
            renderStaffTagSelect(`coEdit${id}`);
        };
        window.cancelEditCo = function (id) {
            const row = document.querySelector(`#complaintsTable tr[data-entry-row="${id}"]`);
            if (row) row.innerHTML = coViewRowHtml(coEntries[id]);
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
                    coEntries[id] = data.entry;
                    row.innerHTML = coViewRowHtml(data.entry);
                    showStaffToast(data.message || 'Complaint updated.');
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
                        coEntries[activeNoticeComplaintId] = data.entry;
                        const row = document.querySelector(`#complaintsTable tr[data-entry-row="${activeNoticeComplaintId}"]`);
                        if (row) row.innerHTML = coViewRowHtml(data.entry);

                        (data.notices || []).forEach(notice => {
                            noEntries[notice.id] = notice;
                            document.getElementById('noEmptyRow')?.remove();
                            const tr = document.createElement('tr');
                            tr.setAttribute('data-entry-row', notice.id);
                            tr.innerHTML = noViewRowHtml(notice);
                            document.querySelector('#noticesTable tbody').prepend(tr);
                        });

                        bootstrap.Modal.getInstance(document.getElementById('generateNoticeModal'))?.hide();
                        showStaffToast(data.message || 'Staff notice(s) drafted — see the Notices tab.');
                    } else {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not draft notice.');
                        showStaffToast(firstError, 'error');
                    }
                });
            });
        }

        const coQaSaveBtn = document.getElementById('coQaSaveBtn');
        if (coQaSaveBtn) {
            wireClientPicker('coQa');
            renderStaffTagSelect('coQa');
            coQaSaveBtn.addEventListener('click', function () {
                const fields = {
                    complaint_date: document.getElementById('coQaDate').value,
                    complaint_time: document.getElementById('coQaTime').value,
                    branch: document.getElementById('coQaBranch').value,
                    staff_ids: staffTagSelectedIds('coQa'),
                    customer_name: document.getElementById('coQaClientName').value,
                    customer_phone: document.getElementById('coQaClientPhone').value,
                    service_id: document.getElementById('coQaService').value,
                    category: document.getElementById('coQaCategory').value,
                    description: document.getElementById('coQaDescription').value,
                    deduction_applied: document.getElementById('coQaDeductionApplied').value,
                    deduction_amount: document.getElementById('coQaDeductionAmount').value,
                };
                if (!fields.complaint_date || !fields.branch || !fields.staff_ids.length || !fields.customer_name.trim() || !fields.service_id || !fields.category || !fields.description.trim()) {
                    showStaffToast('Date, branch, staff involved, client name, service received, category, and feedback detail are required.', 'error');
                    return;
                }
                if (fields.deduction_applied === 'Y' && !fields.deduction_amount) {
                    showStaffToast('Enter a deduction amount, or set Deduction Applied to No.', 'error');
                    return;
                }
                jsonFetch(`{{ route('staff-complaints.store') }}`, 'POST', fields).then(({ ok, data }) => {
                    if (ok && data.success) {
                        coEntries[data.entry.id] = data.entry;
                        document.getElementById('coEmptyRow')?.remove();
                        const tr = document.createElement('tr');
                        tr.setAttribute('data-entry-row', data.entry.id);
                        tr.innerHTML = coViewRowHtml(data.entry);
                        document.getElementById('coQuickAddRow').insertAdjacentElement('afterend', tr);

                        document.getElementById('coQaClientQuery').value = '';
                        document.getElementById('coQaClientName').value = '';
                        document.getElementById('coQaClientPhone').value = '';
                        document.getElementById('coQaStaffValue').value = '';
                        renderStaffTagSelect('coQa');
                        document.getElementById('coQaService').value = '';
                        document.getElementById('coQaDescription').value = '';
                        document.getElementById('coQaDeductionApplied').value = 'N';
                        toggleDeductionAmount('coQa', 'N');

                        const msg = data.entry.deduction_applied === 'Y'
                            ? `${data.message} Refresh to see the synced deduction under Deductions / Payroll.`
                            : data.message;
                        showStaffToast(msg || 'Complaint logged.');
                    } else {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not add complaint.');
                        showStaffToast(firstError, 'error');
                    }
                });
            });
        }

        /* ---------- DEDUCTIONS ---------- */
        let dedEntries = {};
        document.querySelectorAll('#deductionsTable tr[data-entry-row]').forEach(row => {
            const entry = JSON.parse(row.dataset.entry);
            dedEntries[entry.id] = entry;
        });

        function dedViewRowHtml(e) {
            return `
                <td>${e.deduction_date_label}</td>
                <td class="fw-semibold">${escapeHtml(e.staff_name)}</td>
                <td>QAR ${Number(e.amount).toFixed(2)}</td>
                <td>${escapeHtml(e.reason)}</td>
                <td>${complaintLabel(e.complaint_id)}</td>
                <td class="text-end">${actionsHtml(e.id, 'view', 'startEditDed', 'deleteDed')}</td>
            `;
        }

        function dedEditRowHtml(e) {
            return `
                <td><input type="date" class="form-control form-control-sm" data-field="deduction_date" value="${e.deduction_date}"></td>
                <td><select class="form-select form-select-sm" data-field="staff_id">${staffOptionsHtml(e.staff_id)}</select></td>
                <td><input type="number" min="0" step="0.01" class="form-control form-control-sm" data-field="amount" value="${e.amount}"></td>
                <td><input type="text" class="form-control form-control-sm" data-field="reason" value="${escapeHtml(e.reason)}"></td>
                <td><select class="form-select form-select-sm" data-field="complaint_id">${complaintOptionsHtml(e.complaint_id)}</select></td>
                <td class="text-end">${actionsHtml(e.id, 'edit', '', '', 'saveEditDed', 'cancelEditDed')}</td>
            `;
        }

        window.startEditDed = function (id) {
            const row = document.querySelector(`#deductionsTable tr[data-entry-row="${id}"]`);
            if (row) row.innerHTML = dedEditRowHtml(dedEntries[id]);
        };
        window.cancelEditDed = function (id) {
            const row = document.querySelector(`#deductionsTable tr[data-entry-row="${id}"]`);
            if (row) row.innerHTML = dedViewRowHtml(dedEntries[id]);
        };
        window.saveEditDed = function (id) {
            const row = document.querySelector(`#deductionsTable tr[data-entry-row="${id}"]`);
            jsonFetch(`/staff-deductions/${id}`, 'PUT', readRowFields(row)).then(({ ok, data }) => {
                if (ok && data.success) {
                    dedEntries[id] = data.entry;
                    row.innerHTML = dedViewRowHtml(data.entry);
                    showStaffToast(data.message || 'Deduction updated.');
                } else {
                    const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not update deduction.');
                    showStaffToast(firstError, 'error');
                }
            });
        };
        window.deleteDed = function (id) {
            if (!confirm('Delete this deduction?')) return;
            jsonDelete(`/staff-deductions/${id}`).then(({ ok, data }) => {
                if (ok && data.success) {
                    document.querySelector(`#deductionsTable tr[data-entry-row="${id}"]`)?.remove();
                    delete dedEntries[id];
                    showStaffToast(data.message || 'Deduction deleted.');
                    maybeShowEmptyState('#deductionsTable', 'dedEmptyRow', 6, 'No deductions logged.');
                } else {
                    showStaffToast(data.message || 'Could not delete deduction.', 'error');
                }
            });
        };

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
                        dedEntries[data.entry.id] = data.entry;
                        document.getElementById('dedEmptyRow')?.remove();
                        const tr = document.createElement('tr');
                        tr.setAttribute('data-entry-row', data.entry.id);
                        tr.innerHTML = dedViewRowHtml(data.entry);
                        document.getElementById('dedQuickAddRow').insertAdjacentElement('afterend', tr);
                        document.getElementById('dedQaStaff').value = '';
                        document.getElementById('dedQaAmount').value = '';
                        document.getElementById('dedQaReason').value = '';
                        document.getElementById('dedQaComplaint').value = '';
                        showStaffToast(data.message || 'Deduction logged.');
                    } else {
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not add deduction.');
                        showStaffToast(firstError, 'error');
                    }
                });
            });
        }

        /* ---------- NOTICES ---------- */
        let noEntries = {};
        document.querySelectorAll('#noticesTable tr[data-entry-row]').forEach(row => {
            const entry = JSON.parse(row.dataset.entry);
            noEntries[entry.id] = entry;
        });

        function noViewRowHtml(e) {
            return `
                <td>${e.notice_date_label}</td>
                <td class="fw-semibold">${escapeHtml(e.staff_name)}</td>
                <td>${escapeHtml(e.type)}</td>
                <td>${escapeHtml(e.subject)}</td>
                <td>${escapeHtml(e.description) || '—'}</td>
                <td>${escapeHtml(e.corrective_actions) || '—'}</td>
                <td><span class="ack-badge ${e.acknowledged.toLowerCase()}">${e.acknowledged === 'Y' ? 'Acknowledged' : 'Not acknowledged'}</span></td>
                <td class="text-end">${actionsHtml(e.id, 'view', 'startEditNo', 'deleteNo')}</td>
            `;
        }

        function noEditRowHtml(e) {
            return `
                <td><input type="date" class="form-control form-control-sm" data-field="notice_date" value="${e.notice_date}"></td>
                <td><select class="form-select form-select-sm" data-field="staff_id">${staffOptionsHtml(e.staff_id)}</select></td>
                <td><select class="form-select form-select-sm" data-field="type">${plainOptionsHtml(NOTICE_TYPES, e.type)}</select></td>
                <td><input type="text" class="form-control form-control-sm" data-field="subject" value="${escapeHtml(e.subject)}"></td>
                <td><input type="text" class="form-control form-control-sm" data-field="description" value="${escapeHtml(e.description || '')}"></td>
                <td><input type="text" class="form-control form-control-sm" data-field="corrective_actions" value="${escapeHtml(e.corrective_actions || '')}"></td>
                <td><select class="form-select form-select-sm" data-field="acknowledged"><option value="N" ${e.acknowledged === 'N' ? 'selected' : ''}>No</option><option value="Y" ${e.acknowledged === 'Y' ? 'selected' : ''}>Yes</option></select></td>
                <td class="text-end">${actionsHtml(e.id, 'edit', '', '', 'saveEditNo', 'cancelEditNo')}</td>
            `;
        }

        window.startEditNo = function (id) {
            const row = document.querySelector(`#noticesTable tr[data-entry-row="${id}"]`);
            if (row) row.innerHTML = noEditRowHtml(noEntries[id]);
        };
        window.cancelEditNo = function (id) {
            const row = document.querySelector(`#noticesTable tr[data-entry-row="${id}"]`);
            if (row) row.innerHTML = noViewRowHtml(noEntries[id]);
        };
        window.saveEditNo = function (id) {
            const row = document.querySelector(`#noticesTable tr[data-entry-row="${id}"]`);
            jsonFetch(`/staff-notices/${id}`, 'PUT', readRowFields(row)).then(({ ok, data }) => {
                if (ok && data.success) {
                    noEntries[id] = data.entry;
                    row.innerHTML = noViewRowHtml(data.entry);
                    showStaffToast(data.message || 'Notice updated.');
                } else {
                    const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not update notice.');
                    showStaffToast(firstError, 'error');
                }
            });
        };
        window.deleteNo = function (id) {
            if (!confirm('Delete this notice?')) return;
            jsonDelete(`/staff-notices/${id}`).then(({ ok, data }) => {
                if (ok && data.success) {
                    document.querySelector(`#noticesTable tr[data-entry-row="${id}"]`)?.remove();
                    delete noEntries[id];
                    showStaffToast(data.message || 'Notice deleted.');
                    maybeShowEmptyState('#noticesTable', 'noEmptyRow', 8, 'No notices logged.');
                } else {
                    showStaffToast(data.message || 'Could not delete notice.', 'error');
                }
            });
        };

    </script>
@endsection
