{{-- Daily Attendance Log & Tamper-Proof Salon Check-In Terminal --}}
@php
    $attDate = $attendanceDate ?? now('Asia/Qatar')->toDateString();
    $attBranch = $attendanceBranch ?? '';
    $scopedBranch = $attendanceScopedBranch ?? null;
    $isToday = (bool) ($isAttendanceToday ?? ($attDate === now('Asia/Qatar')->toDateString()));
    $isAdmin = (bool) ($isAdminViewer ?? auth()->user()?->isAdmin());
    $attMembers = $attendanceStaffMembers ?? collect();
    $attRecords = $attendanceMap ?? collect();
    $shiftsByStaff = $attendanceShiftsByStaffId ?? [];
    $serverEpochMs = (int) ($qatarServerEpochMs ?? now('Asia/Qatar')->getTimestampMs());
    $initialClockText = (string) ($qatarClockInitialLabel ?? now('Asia/Qatar')->format('g:i A · D, d M Y'));
    $scopedBranchLabel = match ($scopedBranch) {
        'old_airport' => 'Old Airport',
        'wakrah' => 'Al Wakrah',
        default => null,
    };
    $attReloadUrl = route('staffs.index', array_filter([
        'tab' => 'attendance',
        'att_date' => $attDate,
        'att_branch' => $scopedBranch ? null : $attBranch,
    ]));
@endphp

<style>
    .att-clock-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 14px;
        border-radius: 999px;
        background: rgba(212, 163, 115, 0.12);
        border: 1px solid rgba(212, 163, 115, 0.30);
        color: #f3d9a4;
        font-size: 12.5px;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .att-clock-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #6ee7b7;
        box-shadow: 0 0 8px rgba(110, 231, 183, 0.7);
        flex-shrink: 0;
    }
    .att-checkin-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 15px;
        border-radius: 999px;
        border: none;
        background: linear-gradient(135deg, #d98f83, #c19a8c);
        color: #1c110f;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        transition: filter 0.14s ease, transform 0.12s ease;
        white-space: nowrap;
    }
    .att-checkin-btn:hover { filter: brightness(1.07); transform: translateY(-1px); }
    .att-status-btn {
        border: 1px solid rgba(229, 184, 168, 0.2);
        background: rgba(34, 22, 27, 0.65);
        color: #b8a29a;
        border-radius: 999px;
        padding: 4px 11px;
        font-size: 11.5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.14s ease;
        white-space: nowrap;
    }
    .att-status-btn:hover { color: #f8efe6; border-color: rgba(229, 184, 168, 0.4); }
    .att-status-btn.active-present { background: rgba(110, 231, 183, 0.16); color: #6ee7b7; border-color: rgba(110, 231, 183, 0.42); }
    .att-status-btn.active-absent { background: rgba(253, 164, 175, 0.16); color: #fda4af; border-color: rgba(253, 164, 175, 0.42); }
    .att-status-btn.active-late { background: rgba(245, 158, 11, 0.16); color: #fbbf24; border-color: rgba(245, 158, 11, 0.42); }
    .att-status-btn.active-off { background: rgba(212, 163, 115, 0.16); color: #f3d9a4; border-color: rgba(212, 163, 115, 0.42); }
    .att-locked-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .att-locked-ontime {
        background: rgba(52, 211, 153, 0.14);
        color: #6ee7b7;
        border: 1px solid rgba(52, 211, 153, 0.34);
    }
    .att-locked-late-amber {
        background: rgba(245, 158, 11, 0.14);
        color: #fbbf24;
        border: 1px solid rgba(245, 158, 11, 0.36);
    }
    .att-locked-late-rose {
        background: rgba(251, 113, 133, 0.14);
        color: #fda4af;
        border: 1px solid rgba(251, 113, 133, 0.36);
    }
    .att-shift-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        white-space: nowrap;
    }
</style>

{{-- Header with Live Official Qatar Clock + Date Switcher --}}
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
    <div>
        <h6 class="fw-bold mb-1" style="color: #f8efe6;">
            Daily Attendance &amp; Check-In Terminal
            @if ($scopedBranchLabel)
                <span class="badge ms-1" style="background:rgba(212,163,115,0.16); color:#f3d9a4; font-weight:600;">{{ $scopedBranchLabel }}</span>
            @endif
        </h6>
        <p class="text-muted small mb-0">
            @if ($isToday)
                Official Qatar-time check-in with automatic shift late-minute tracking &amp; tamper-proof lock.
            @else
                Viewing attendance log for {{ \Illuminate\Support\Carbon::parse($attDate, 'Asia/Qatar')->format('D, d M Y') }}.
            @endif
        </p>
    </div>

    <div class="d-flex align-items-center flex-wrap gap-2">
        <div class="att-clock-pill" id="attLiveQatarClock" title="Official server-synced Qatar Time (Asia/Qatar)">
            <span class="att-clock-dot" aria-hidden="true"></span>
            <span>Qatar Time &middot; <span id="attLiveQatarClockText">{{ $initialClockText }}</span></span>
        </div>

        <form method="GET" action="{{ route('staffs.index') }}" class="d-flex align-items-center gap-2 m-0">
            <input type="hidden" name="tab" value="attendance">
            @if (!$scopedBranch)
                <input type="hidden" name="att_branch" value="{{ $attBranch }}">
            @endif
            <input type="date" name="att_date" value="{{ $attDate }}" class="form-control form-control-sm" style="max-width: 158px;" onchange="this.form.submit()">
            @if (!$isToday)
                <a href="{{ route('staffs.index', array_filter(['tab' => 'attendance', 'att_branch' => $scopedBranch ? null : $attBranch])) }}"
                   class="portal-mini-btn text-decoration-none">Today</a>
            @endif
        </form>
    </div>
</div>

{{-- Branch Filter Pills (Admin/HQ) OR Auto-Scoped Branch Indicator (Salon Managers) --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" aria-label="Attendance Location Filter">
    @if ($scopedBranchLabel)
        <div class="d-flex align-items-center gap-2">
            <span class="shift-branch-pill active">
                <i class="bx bx-store-alt me-1"></i>{{ $scopedBranchLabel }} Branch Team
            </span>
            <span class="small" style="color:#9e8780;">Auto-scoped to your assigned salon</span>
        </div>
    @else
        <div class="d-flex flex-wrap align-items-center gap-2">
            @foreach ([
                '' => 'All',
                'old_airport' => 'Old Airport',
                'wakrah' => 'Al Wakrah',
                'remote' => 'Remote',
            ] as $bKey => $bLabel)
                <a href="{{ route('staffs.index', array_filter(['tab' => 'attendance', 'att_date' => $attDate, 'att_branch' => $bKey])) }}"
                   class="shift-branch-pill {{ $attBranch === $bKey ? 'active' : '' }}">
                    {{ $bLabel }}
                </a>
            @endforeach
        </div>
    @endif
    <span class="small" style="color:#9e8780;">{{ $attMembers->count() }} {{ $attMembers->count() === 1 ? 'member' : 'members' }}</span>
</div>

{{-- Attendance Table --}}
<div class="roster-card mb-3">
    <div class="table-responsive">
        <table class="table team-table align-middle mb-0" id="attTerminalTable">
            <thead>
                <tr>
                    <th>Team Member</th>
                    <th>{{ $isToday ? "Today's Shift" : 'Scheduled Shift' }}</th>
                    <th>Check-In / Status</th>
                    <th style="min-width: 150px;">Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attMembers as $member)
                    @php
                        $rec = $attRecords->get($member->id);
                        $curStatus = $rec?->status ?? '';
                        $shift = $shiftsByStaff[$member->id] ?? ['type' => 'unscheduled', 'display' => 'No shift set', 'start_time' => null, 'end_time' => null];
                        $isLockedCheckIn = $rec && $rec->is_locked && $rec->checked_in_at;
                        $isRemoteMember = $member->isRemote();
                        $roleDisplay = $member->user ? \App\Models\User::roleLabel($member->user->role) : ($isRemoteMember ? 'Remote Staff' : 'Salon Staff');
                        $lateMins = (int) ($rec?->late_minutes ?? 0);
                        $lateDurLabel = \App\Models\StaffAttendance::formatLateDuration($lateMins);
                        $checkedInLabel = $rec?->checkedInQatarLabel();
                        $checkedOutLabel = $rec?->checkedOutQatarLabel();
                    @endphp
                    <tr data-att-row="{{ $member->id }}">
                        {{-- 1. Team Member --}}
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @include('partials.avatar', [
                                    'src' => $member->profile_picture,
                                    'name' => $member->name,
                                    'size' => 36,
                                ])
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate" style="color:#f8efe6; font-size:13.5px;">{{ $member->name }}</div>
                                    <div class="small" style="color:#9e8780;">
                                        {{ $roleDisplay }}
                                        @if (!$scopedBranchLabel && $attBranch === '')
                                            &middot; <span style="color:#d4a373;">{{ $member->location_label }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- 2. Today's Shift --}}
                        <td class="text-nowrap">
                            @if ($shift['type'] === 'shift')
                                <span class="fw-medium" style="color:#f8efe6; font-size:13px; font-variant-numeric:tabular-nums;">
                                    {{ $shift['display'] }}
                                </span>
                            @elseif ($shift['type'] === 'off')
                                <span class="att-shift-pill" style="background:rgba(212,163,115,0.14); color:#f3d9a4; border:1px solid rgba(212,163,115,0.28);">
                                    Off Day
                                </span>
                            @elseif ($shift['type'] === 'on_leave')
                                <span class="att-shift-pill" style="background:rgba(251,113,133,0.14); color:#fda4af; border:1px solid rgba(251,113,133,0.30);">
                                    {{ $shift['display'] }}
                                </span>
                            @else
                                <span class="small" style="color:#8f7a73;">{{ $isRemoteMember ? 'Remote' : 'No shift set' }}</span>
                            @endif
                        </td>

                        {{-- 3. Check-In / Status --}}
                        <td>
                            @if ($isLockedCheckIn)
                                {{-- Locked Server-Recorded Check-In Display --}}
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    @if ($curStatus === \App\Models\StaffAttendance::STATUS_LATE)
                                        <span class="att-locked-pill {{ $lateMins >= 30 ? 'att-locked-late-rose' : 'att-locked-late-amber' }}"
                                              title="Locked server-side check-in timestamp">
                                            <i class="bx bx-time-five"></i>
                                            <span>Late &middot; {{ $checkedInLabel }}@if($lateMins > 0) ({{ $lateDurLabel }})@endif</span>
                                        </span>
                                    @else
                                        <span class="att-locked-pill att-locked-ontime"
                                              title="Locked server-side check-in timestamp">
                                            <i class="bx bx-check-circle"></i>
                                            <span>On Time &middot; {{ $checkedInLabel }}</span>
                                        </span>
                                    @endif

                                    @if ($checkedOutLabel)
                                        <span class="small" style="color:#9e8780; font-variant-numeric:tabular-nums;">
                                            Out &middot; {{ $checkedOutLabel }}
                                        </span>
                                    @elseif ($isToday)
                                        @moduleEdit('staff_management')
                                            <button type="button"
                                                class="att-status-btn"
                                                onclick="performStaffCheckOut({{ $member->id }}, '{{ $attDate }}', this)">
                                                Check Out
                                            </button>
                                        @endmoduleEdit
                                    @endif

                                    @if ($isAdmin)
                                        @php
                                            $overridePayload = [
                                                'id' => $rec->id,
                                                'staffName' => $member->name,
                                                'date' => $attDate,
                                                'status' => $rec->status,
                                                'checkedInTime' => $rec->checked_in_at ? $rec->checked_in_at->copy()->timezone('Asia/Qatar')->format('H:i') : '',
                                                'checkedOutTime' => $rec->checked_out_at ? $rec->checked_out_at->copy()->timezone('Asia/Qatar')->format('H:i') : '',
                                                'lateMinutes' => $lateMins,
                                                'notes' => (string) ($rec->notes ?? ''),
                                            ];
                                        @endphp
                                        <button type="button"
                                            class="portal-mini-btn js-open-att-override"
                                            data-payload='@json($overridePayload)'
                                            title="Admin Only: Edit or Reset Check-In">
                                            <i class="bx bx-edit-alt"></i> Reset / Edit
                                        </button>
                                    @endif
                                </div>
                            @elseif ($isToday && !$isRemoteMember)
                                {{-- Today's Live Salon Check-In Terminal Row --}}
                                @moduleEdit('staff_management')
                                    @php
                                        $checkInPayload = [
                                            'staffId' => $member->id,
                                            'staffName' => $member->name,
                                            'shiftDisplay' => $shift['type'] === 'off'
                                                ? 'Off Day (Extra Shift Check-In)'
                                                : ($shift['type'] === 'on_leave' ? ($shift['display'] . ' (Override Check-In)') : $shift['display']),
                                        ];
                                    @endphp
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <button type="button"
                                            class="att-checkin-btn js-open-checkin-modal"
                                            data-payload='@json($checkInPayload)'>
                                            <i class="bx bx-log-in-circle"></i> Check In
                                        </button>
                                        <button type="button"
                                            class="att-status-btn {{ $curStatus === 'absent' ? 'active-absent' : '' }}"
                                            data-att-btn="{{ $member->id }}"
                                            data-status="absent"
                                            onclick="markStaffAttendance({{ $member->id }}, 'absent', '{{ $attDate }}')">
                                            Absent
                                        </button>
                                        <button type="button"
                                            class="att-status-btn {{ $curStatus === 'off' ? 'active-off' : '' }}"
                                            data-att-btn="{{ $member->id }}"
                                            data-status="off"
                                            onclick="markStaffAttendance({{ $member->id }}, 'off', '{{ $attDate }}')">
                                            Off
                                        </button>
                                    </div>
                                @else
                                    <span class="small" style="color:#cbb8b0;">{{ $curStatus ? \App\Models\StaffAttendance::STATUSES[$curStatus] : 'Not checked in' }}</span>
                                @endmoduleEdit
                            @else
                                {{-- Past/Future Dates OR Remote Staff: Manual Status Marking --}}
                                @moduleEdit('staff_management')
                                    <div class="d-flex align-items-center flex-wrap gap-1">
                                        @foreach (\App\Models\StaffAttendance::STATUSES as $stKey => $stLabel)
                                            <button type="button"
                                                class="att-status-btn {{ $curStatus === $stKey ? 'active-' . $stKey : '' }}"
                                                data-att-btn="{{ $member->id }}"
                                                data-status="{{ $stKey }}"
                                                onclick="markStaffAttendance({{ $member->id }}, '{{ $stKey }}', '{{ $attDate }}')">
                                                {{ $stLabel }}
                                            </button>
                                        @endforeach
                                        @if ($curStatus === 'late' && $lateMins > 0)
                                            <span class="small ms-1" style="color:#fbbf24; font-weight:600;">({{ $lateDurLabel }})</span>
                                        @endif
                                        @if ($isAdmin && $rec)
                                            @php
                                                $overridePayload = [
                                                    'id' => $rec->id,
                                                    'staffName' => $member->name,
                                                    'date' => $attDate,
                                                    'status' => $rec->status,
                                                    'checkedInTime' => $rec->checked_in_at ? $rec->checked_in_at->copy()->timezone('Asia/Qatar')->format('H:i') : '',
                                                    'checkedOutTime' => $rec->checked_out_at ? $rec->checked_out_at->copy()->timezone('Asia/Qatar')->format('H:i') : '',
                                                    'lateMinutes' => $lateMins,
                                                    'notes' => (string) ($rec->notes ?? ''),
                                                ];
                                            @endphp
                                            <button type="button"
                                                class="portal-mini-btn ms-1 js-open-att-override"
                                                data-payload='@json($overridePayload)'>
                                                <i class="bx bx-edit-alt"></i>
                                            </button>
                                        @endif
                                    </div>
                                @else
                                    <span class="small" style="color:#cbb8b0;">{{ $curStatus ? \App\Models\StaffAttendance::STATUSES[$curStatus] : 'Not marked' }}</span>
                                @endmoduleEdit
                            @endif
                        </td>

                        {{-- 4. Notes --}}
                        <td>
                            <input type="text"
                                class="form-control form-control-sm"
                                id="attNote{{ $member->id }}"
                                value="{{ $rec?->notes ?? '' }}"
                                placeholder="Optional note…"
                                maxlength="500"
                                @moduleEdit('staff_management')
                                    onblur="saveAttendanceNoteOnly({{ $member->id }}, '{{ $curStatus }}', '{{ $attDate }}', {{ $isLockedCheckIn ? 'true' : 'false' }})"
                                @else
                                    disabled
                                @endmoduleEdit
                            >
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-4" style="color:#9e8780;">No team members match this branch filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@moduleEdit('staff_management')
{{-- Lightweight 1-Step Check-In Confirmation Modal (Prevents Misclicks on Shared Salon Laptop) --}}
<div class="modal sa-modal" id="confirmCheckInModal" tabindex="-1" aria-labelledby="confirmCheckInTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="confirmCheckInTitle" style="color:#f8efe6;">Confirm Check-In?</h5>
                    <div class="small" style="color:#b8a29a;">Recorded using official Qatar server time.</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="confirmCheckInForm" onsubmit="return submitStaffCheckIn(event);">
                <input type="hidden" id="ciStaffId" value="">
                <div class="modal-body">
                    <div class="alert alert-danger py-2 px-3 small d-none mb-3" id="ciErrorAlert"></div>

                    <div class="py-2 d-flex justify-content-between align-items-center" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                        <span class="small" style="color:#9e8780;">Scheduled Shift</span>
                        <span class="fw-semibold" id="ciShiftDisplay" style="color:#f8efe6; font-variant-numeric:tabular-nums;">—</span>
                    </div>
                    <div class="py-2 d-flex justify-content-between align-items-center mb-3" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                        <span class="small" style="color:#9e8780;">Current Qatar Time</span>
                        <span class="fw-bold" id="ciLiveTimeDisplay" style="color:#f3d9a4; font-variant-numeric:tabular-nums;">{{ $initialClockText }}</span>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="ciNotes">Note (optional)</label>
                        <input type="text" id="ciNotes" class="form-control form-control-sm" maxlength="500" placeholder="Optional arrival note">
                    </div>

                    <div class="small" style="color:#9e8780;">
                        <i class="bx bx-lock-alt me-1" style="color:#d4a373;"></i>
                        Once confirmed, check-in time &amp; late minutes are locked and can only be edited by an Admin.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sc-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="sc-btn-rosegold" id="ciConfirmBtn">
                        <i class="bx bx-check"></i> Confirm Check-In
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endmoduleEdit

@if ($isAdmin)
{{-- Admin-Only Reset / Edit Attendance Modal --}}
<div class="modal sa-modal" id="adminAttendanceOverrideModal" tabindex="-1" aria-labelledby="adminAttOverrideTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 470px;">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="adminAttOverrideTitle" style="color:#f8efe6;">Edit / Reset Attendance</h5>
                    <div class="small" id="aaoSubtitle" style="color:#b8a29a;">Admin-only correction for genuine check-in mistakes.</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="adminAttOverrideForm" onsubmit="return submitAdminAttendanceOverride(event);">
                <input type="hidden" id="aaoAttendanceId" value="">
                <div class="modal-body">
                    <div class="alert alert-danger py-2 px-3 small d-none mb-3" id="aaoErrorAlert"></div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="aaoStatus">Status *</label>
                            <select id="aaoStatus" class="form-select" required>
                                @foreach (\App\Models\StaffAttendance::STATUSES as $stKey => $stLabel)
                                    <option value="{{ $stKey }}">{{ $stLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="aaoLateMinutes">Late Minutes</label>
                            <input type="number" id="aaoLateMinutes" class="form-control" min="0" max="1440" placeholder="Auto or manual">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="aaoCheckedInTime">Check-In Time (Qatar)</label>
                            <input type="time" id="aaoCheckedInTime" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="aaoCheckedOutTime">Check-Out Time (Qatar)</label>
                            <input type="time" id="aaoCheckedOutTime" class="form-control">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="aaoNotes">Notes</label>
                        <input type="text" id="aaoNotes" class="form-control" maxlength="500" placeholder="Reason for admin correction">
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="portal-mini-btn" style="border-color:rgba(253,164,175,0.42); color:#fda4af;" onclick="submitAdminAttendanceReset()">
                        <i class="bx bx-reset"></i> Reset Check-In
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="sc-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="sc-btn-rosegold" id="aaoSaveBtn">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
    (function () {
        // Server-offset live Asia/Qatar clock so terminal matches official server clock
        const serverOffsetMs = {{ $serverEpochMs }} - Date.now();
        const headerClockEl = document.getElementById('attLiveQatarClockText');
        const modalClockEl = document.getElementById('ciLiveTimeDisplay');

        function formatQatarClock(ms) {
            const d = new Date(ms);
            try {
                const timePart = new Intl.DateTimeFormat('en-US', {
                    timeZone: 'Asia/Qatar',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true,
                }).format(d);
                const datePart = new Intl.DateTimeFormat('en-GB', {
                    timeZone: 'Asia/Qatar',
                    weekday: 'short',
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                }).format(d);
                return `${timePart} · ${datePart}`;
            } catch (e) {
                return d.toLocaleTimeString();
            }
        }

        function tickQatarClock() {
            const text = formatQatarClock(Date.now() + serverOffsetMs);
            if (headerClockEl) headerClockEl.textContent = text;
            if (modalClockEl) modalClockEl.textContent = text;
        }
        tickQatarClock();
        setInterval(tickQatarClock, 1000);
    })();
</script>

@moduleEdit('staff_management')
<script>
    const attReloadUrl = @json($attReloadUrl);

    document.addEventListener('DOMContentLoaded', function () {
        // 1-step Check-In Modal Trigger
        const ciModalEl = document.getElementById('confirmCheckInModal');
        document.querySelectorAll('.js-open-checkin-modal').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!ciModalEl) return;
                let payload = {};
                try { payload = JSON.parse(btn.dataset.payload || '{}'); } catch (e) {}
                document.getElementById('ciStaffId').value = payload.staffId || '';
                document.getElementById('confirmCheckInTitle').textContent = `Confirm Check-In for ${payload.staffName || 'Staff'}?`;
                document.getElementById('ciShiftDisplay').textContent = payload.shiftDisplay || '—';
                const inlineNote = document.getElementById('attNote' + payload.staffId);
                document.getElementById('ciNotes').value = inlineNote ? inlineNote.value.trim() : '';
                const errEl = document.getElementById('ciErrorAlert');
                if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                const confirmBtn = document.getElementById('ciConfirmBtn');
                if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.innerHTML = '<i class="bx bx-check"></i> Confirm Check-In'; }
                if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(ciModalEl).show();
            });
        });

        // Admin-only Override / Reset Modal Trigger
        const aaoModalEl = document.getElementById('adminAttendanceOverrideModal');
        document.querySelectorAll('.js-open-att-override').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!aaoModalEl) return;
                let payload = {};
                try { payload = JSON.parse(btn.dataset.payload || '{}'); } catch (e) {}
                document.getElementById('aaoAttendanceId').value = payload.id || '';
                document.getElementById('adminAttOverrideTitle').textContent = `Edit / Reset — ${payload.staffName || 'Staff'}`;
                document.getElementById('aaoSubtitle').textContent = `Attendance Date: ${payload.date || ''}`;
                document.getElementById('aaoStatus').value = payload.status || 'present';
                document.getElementById('aaoCheckedInTime').value = payload.checkedInTime || '';
                document.getElementById('aaoCheckedOutTime').value = payload.checkedOutTime || '';
                document.getElementById('aaoLateMinutes').value = payload.lateMinutes ?? 0;
                document.getElementById('aaoNotes').value = payload.notes || '';
                const errEl = document.getElementById('aaoErrorAlert');
                if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(aaoModalEl).show();
            });
        });
    });

    window.submitStaffCheckIn = async function (evt) {
        evt.preventDefault();
        const staffId = document.getElementById('ciStaffId')?.value;
        const notes = document.getElementById('ciNotes')?.value?.trim() || '';
        const errEl = document.getElementById('ciErrorAlert');
        const btn = document.getElementById('ciConfirmBtn');
        if (!staffId) return false;

        if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
        if (btn) { btn.disabled = true; btn.textContent = 'Checking In…'; }

        try {
            const res = await fetch('{{ route('staff-attendance.check-in') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ staff_id: Number(staffId), notes: notes }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                window.location.href = attReloadUrl;
                return false;
            }
            if (errEl) {
                errEl.textContent = data.message || 'Could not record check-in.';
                errEl.classList.remove('d-none');
            }
        } catch (e) {
            if (errEl) {
                errEl.textContent = 'Network error recording check-in.';
                errEl.classList.remove('d-none');
            }
        }
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bx bx-check"></i> Confirm Check-In'; }
        return false;
    };

    window.performStaffCheckOut = async function (staffId, attendanceDate, btnEl) {
        if (!confirm('Record Check-Out time right now?')) return;
        if (btnEl) { btnEl.disabled = true; btnEl.textContent = 'Saving…'; }
        try {
            const res = await fetch('{{ route('staff-attendance.check-out') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ staff_id: staffId, attendance_date: attendanceDate }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                window.location.href = attReloadUrl;
                return;
            }
            alert(data.message || 'Could not record check-out.');
        } catch (e) {
            alert('Network error recording check-out.');
        }
        if (btnEl) { btnEl.disabled = false; btnEl.textContent = 'Check Out'; }
    };

    window.markStaffAttendance = async function (staffId, status, attendanceDate, silent = false) {
        if (!status) return;
        const noteEl = document.getElementById('attNote' + staffId);
        const notes = noteEl ? noteEl.value.trim() : '';
        try {
            const res = await fetch('{{ route('staff-attendance.store') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    staff_id: staffId,
                    attendance_date: attendanceDate,
                    status: status,
                    notes: notes,
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                document.querySelectorAll(`[data-att-btn="${staffId}"]`).forEach(btn => {
                    btn.classList.remove('active-present', 'active-absent', 'active-late', 'active-off');
                    if (btn.dataset.status === status) {
                        btn.classList.add('active-' + status);
                    }
                });
                if (!silent && typeof showStaffToast === 'function') {
                    showStaffToast(data.message);
                }
            } else if (!silent) {
                alert(data.message || 'Could not save attendance.');
            }
        } catch (e) {
            if (!silent) alert('Network error saving attendance.');
        }
    };

    window.saveAttendanceNoteOnly = async function (staffId, fallbackStatus, attendanceDate, isLocked) {
        const activeBtn = document.querySelector(`[data-att-row="${staffId}"] .att-status-btn[class*="active-"]`);
        const resolvedStatus = activeBtn?.dataset.status || fallbackStatus;
        if (!resolvedStatus && !isLocked) return;

        const noteEl = document.getElementById('attNote' + staffId);
        const notes = noteEl ? noteEl.value.trim() : '';

        try {
            await fetch('{{ route('staff-attendance.store') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    staff_id: staffId,
                    attendance_date: attendanceDate,
                    status: resolvedStatus || 'present',
                    notes: notes,
                    notes_only: true,
                }),
            });
        } catch (e) {}
    };

    @if ($isAdmin)
    window.submitAdminAttendanceOverride = async function (evt) {
        evt.preventDefault();
        const attId = document.getElementById('aaoAttendanceId')?.value;
        const errEl = document.getElementById('aaoErrorAlert');
        const saveBtn = document.getElementById('aaoSaveBtn');
        if (!attId) return false;

        if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
        if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }

        const lateVal = document.getElementById('aaoLateMinutes')?.value;
        const payload = {
            status: document.getElementById('aaoStatus')?.value,
            checked_in_time: document.getElementById('aaoCheckedInTime')?.value || null,
            checked_out_time: document.getElementById('aaoCheckedOutTime')?.value || null,
            late_minutes: lateVal !== '' ? Number(lateVal) : null,
            notes: document.getElementById('aaoNotes')?.value?.trim() || '',
        };

        try {
            const res = await fetch(`/staff-attendance/${attId}/admin-override`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                window.location.href = attReloadUrl;
                return false;
            }
            if (errEl) {
                errEl.textContent = data.message || 'Could not update attendance.';
                errEl.classList.remove('d-none');
            }
        } catch (e) {
            if (errEl) {
                errEl.textContent = 'Network error updating attendance.';
                errEl.classList.remove('d-none');
            }
        }
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Changes'; }
        return false;
    };

    window.submitAdminAttendanceReset = async function () {
        const attId = document.getElementById('aaoAttendanceId')?.value;
        if (!attId) return;
        if (!confirm('Reset this attendance check-in? This removes the locked timestamp so the employee can check in again.')) {
            return;
        }
        try {
            const res = await fetch(`/staff-attendance/${attId}/reset`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                window.location.href = attReloadUrl;
                return;
            }
            alert(data.message || 'Could not reset attendance.');
        } catch (e) {
            alert('Network error resetting attendance.');
        }
    };
    @endif
</script>
@endmoduleEdit
