{{-- Attendance Log Sub-Pane (Supports All | Old Airport | Al Wakrah | Remote Staff) --}}
@php
    $attDate = $attendanceDate ?? now('Asia/Qatar')->toDateString();
    $attBranch = $attendanceBranch ?? '';
    $attMembers = $attendanceStaffMembers ?? collect();
    $attRecords = $attendanceMap ?? collect();
@endphp

<style>
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
    }
    .att-status-btn:hover { color: #f8efe6; border-color: rgba(229, 184, 168, 0.4); }
    .att-status-btn.active-present { background: rgba(110, 231, 183, 0.16); color: #6ee7b7; border-color: rgba(110, 231, 183, 0.42); }
    .att-status-btn.active-absent { background: rgba(253, 164, 175, 0.16); color: #fda4af; border-color: rgba(253, 164, 175, 0.42); }
    .att-status-btn.active-late { background: rgba(245, 158, 11, 0.16); color: #fbbf24; border-color: rgba(245, 158, 11, 0.42); }
    .att-status-btn.active-off { background: rgba(212, 163, 115, 0.16); color: #f3d9a4; border-color: rgba(212, 163, 115, 0.42); }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h6 class="fw-bold mb-1" style="color: #f8efe6;">Daily Attendance Log</h6>
        <p class="text-muted small mb-0">Mark daily attendance for Old Airport, Al Wakrah, and Remote staff (syncs live to each employee's /my-profile Absentees card).</p>
    </div>
    <form method="GET" action="{{ route('staffs.index') }}" class="d-flex align-items-center gap-2">
        <input type="hidden" name="tab" value="attendance">
        <input type="hidden" name="att_branch" value="{{ $attBranch }}">
        <input type="date" name="att_date" value="{{ $attDate }}" class="form-control form-control-sm" style="max-width: 165px;" onchange="this.form.submit()">
    </form>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Attendance Location Filter">
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

<div class="roster-card mb-3">
    <div class="table-responsive">
        <table class="table team-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Team Member</th>
                    <th>Location</th>
                    <th>Status ({{ \Illuminate\Support\Carbon::parse($attDate)->format('D, d M Y') }})</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($attMembers as $member)
                    @php
                        $rec = $attRecords->get($member->id);
                        $curStatus = $rec?->status ?? '';
                    @endphp
                    <tr data-att-row="{{ $member->id }}">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @include('partials.avatar', [
                                    'src' => $member->profile_picture,
                                    'name' => $member->name,
                                    'size' => 34,
                                ])
                                <div>
                                    <div class="fw-semibold" style="color:#f8efe6; font-size:13.5px;">{{ $member->name }}</div>
                                    <div class="small" style="color:#9e8780;">{{ $member->user ? \App\Models\User::roleLabel($member->user->role) : 'Staff' }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background:rgba(212,163,115,0.14); color:#f3d9a4;">{{ $member->location_label }}</span>
                        </td>
                        <td>
                            @moduleEdit('staff_management')
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach (\App\Models\StaffAttendance::STATUSES as $stKey => $stLabel)
                                        <button type="button"
                                            class="att-status-btn {{ $curStatus === $stKey ? 'active-' . $stKey : '' }}"
                                            data-att-btn="{{ $member->id }}"
                                            data-status="{{ $stKey }}"
                                            onclick="markStaffAttendance({{ $member->id }}, '{{ $stKey }}', '{{ $attDate }}')">
                                            {{ $stLabel }}
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <span class="small" style="color:#cbb8b0;">{{ $curStatus ? \App\Models\StaffAttendance::STATUSES[$curStatus] : 'Not marked' }}</span>
                            @endmoduleEdit
                        </td>
                        <td>
                            <input type="text"
                                class="form-control form-control-sm"
                                id="attNote{{ $member->id }}"
                                value="{{ $rec?->notes ?? '' }}"
                                placeholder="Optional note…"
                                maxlength="500"
                                @moduleEdit('staff_management')
                                    onblur="if ('{{ $curStatus }}' !== '') { markStaffAttendance({{ $member->id }}, document.querySelector('[data-att-row=\'{{ $member->id }}\'] .att-status-btn[class*=\'active-\']')?.dataset.status || '{{ $curStatus }}', '{{ $attDate }}', true); }"
                                @else
                                    disabled
                                @endmoduleEdit
                            >
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-4" style="color:#9e8780;">No team members match this location filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@moduleEdit('staff_management')
<script>
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
</script>
@endmoduleEdit
