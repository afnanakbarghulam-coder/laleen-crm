{{-- Admin-Only Leave Requests Queue & Manual Leave Logger inside Shifts & Ops Actions --}}
@if ($isAdminViewer ?? false)
@php
    $leavesCollection = $staffLeaves ?? collect();
    $pendingLeaves = $leavesCollection->where('status', \App\Models\StaffLeave::STATUS_PENDING)->values();
    $historyLeaves = $leavesCollection->where('status', '!=', \App\Models\StaffLeave::STATUS_PENDING)->values();
    $conflictsMap = $leaveConflictsById ?? [];
    $eligibleStaffList = $leaveEligibleStaff ?? collect();
@endphp

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h6 class="fw-bold mb-1" style="color:#f8efe6;">Annual &amp; Sick Leave Requests</h6>
        <p class="text-muted small mb-0">Approve or reject branch staff leave requests. Approved leaves automatically block Enhanced Calendar bookings.</p>
    </div>
    <button type="button" class="sc-btn-rosegold" data-bs-toggle="modal" data-bs-target="#adminManualLeaveModal">
        + Log Approved Leave
    </button>
</div>

{{-- Pending Requests Queue --}}
<div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="small fw-bold text-uppercase" style="letter-spacing:0.06em; color:#d4a373;">Pending Approval</span>
        <span class="badge" style="background:rgba(245,158,11,0.16); color:#fbbf24;">{{ $pendingLeaves->count() }}</span>
    </div>

    @forelse ($pendingLeaves as $leave)
        @php $bookingConflicts = $conflictsMap[$leave->id] ?? []; @endphp
        <div class="roster-card p-3 mb-2">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="min-w-0 flex-grow-1">
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                        <span class="fw-bold" style="color:#f8efe6; font-size:14.5px;">{{ $leave->staff?->name ?? 'Staff' }}</span>
                        <span class="badge" style="background:rgba(212,163,115,0.14); color:#f3d9a4;">{{ $leave->staff?->location_label ?? 'Salon' }}</span>
                        <span class="badge" style="background:{{ $leave->leave_type === 'sick' ? 'rgba(253,164,175,0.16); color:#fda4af;' : 'rgba(110,231,183,0.14); color:#6ee7b7;' }}">
                            {{ $leave->typeLabel() }}
                        </span>
                        <span class="small" style="color:#cbb8b0;">
                            {{ $leave->start_date->format('d M Y') }} &ndash; {{ $leave->end_date->format('d M Y') }}
                            ({{ $leave->total_days }} {{ $leave->total_days === 1 ? 'day' : 'days' }})
                        </span>
                    </div>

                    @if ($leave->reason)
                        <div class="small mb-2" style="color:#cbb8b0;">{{ $leave->reason }}</div>
                    @endif

                    @if ($leave->medical_certificate_path)
                        <div class="mb-2">
                            <a href="{{ route('staff-leaves.certificate', ['leave' => $leave->id, 'inline' => 1]) }}" target="_blank" class="portal-mini-btn text-decoration-none">
                                <i class="bx bx-file"></i> Preview Medical Certificate
                            </a>
                        </div>
                    @endif

                    {{-- Automatic Existing Booking Conflict Warning --}}
                    @if (!empty($bookingConflicts))
                        <div class="p-2 px-3 mt-2" style="border-radius:10px; background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.34); color:#fbbf24; font-size:12.5px;">
                            <div class="fw-bold mb-1">
                                <i class="bx bx-error-circle me-1"></i>
                                Existing Booking Conflict Warning ({{ count($bookingConflicts) }} {{ count($bookingConflicts) === 1 ? 'appointment' : 'appointments' }})
                            </div>
                            <ul class="mb-0 ps-3 small" style="color:#fde68a;">
                                @foreach ($bookingConflicts as $c)
                                    <li>{{ $c['customer_name'] }} &mdash; {{ $c['date'] }} at {{ $c['time'] }} ({{ $c['branch'] }})</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div style="min-width: 260px;">
                    <input type="text" id="leaveNotes{{ $leave->id }}" class="form-control form-control-sm mb-2" placeholder="Optional admin notes…" maxlength="500">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm flex-fill"
                            style="background:rgba(110,231,183,0.16); color:#6ee7b7; border:1px solid rgba(110,231,183,0.36); font-weight:600;"
                            onclick="reviewStaffLeave({{ $leave->id }}, 'approve')">
                            <i class="bx bx-check"></i> Approve
                        </button>
                        <button type="button" class="btn btn-sm flex-fill"
                            style="background:rgba(253,164,175,0.14); color:#fda4af; border:1px solid rgba(253,164,175,0.36); font-weight:600;"
                            onclick="reviewStaffLeave({{ $leave->id }}, 'reject')">
                            <i class="bx bx-x"></i> Reject
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="roster-card p-3 text-center small" style="color:#9e8780;">
            No pending leave requests right now.
        </div>
    @endforelse
</div>

{{-- Leave History --}}
<div class="roster-card mb-3">
    <div class="table-responsive">
        <table class="table team-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Type</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Admin Notes</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($historyLeaves as $leave)
                    <tr>
                        <td>
                            <div class="fw-semibold" style="color:#f8efe6;">{{ $leave->staff?->name ?? '—' }}</div>
                            <div class="small" style="color:#9e8780;">{{ $leave->staff?->location_label ?? '' }}</div>
                        </td>
                        <td>{{ $leave->typeLabel() }}</td>
                        <td class="text-nowrap">{{ $leave->start_date->format('d M') }} &ndash; {{ $leave->end_date->format('d M Y') }}</td>
                        <td>{{ $leave->total_days }}</td>
                        <td>
                            @php
                                $stStyle = match ($leave->status) {
                                    'approved' => 'background:rgba(110,231,183,0.14); color:#6ee7b7; border:1px solid rgba(110,231,183,0.3);',
                                    'rejected' => 'background:rgba(253,164,175,0.14); color:#fda4af; border:1px solid rgba(253,164,175,0.32);',
                                    default => 'background:rgba(255,255,255,0.06); color:#b8a29a;',
                                };
                            @endphp
                            <span class="badge" style="{{ $stStyle }}">{{ $leave->statusLabel() }}</span>
                        </td>
                        <td class="small" style="color:#cbb8b0;">{{ $leave->admin_notes ?: '—' }}</td>
                        <td class="text-end text-nowrap">
                            @if ($leave->medical_certificate_path)
                                <a href="{{ route('staff-leaves.certificate', ['leave' => $leave->id, 'inline' => 1]) }}" target="_blank" class="portal-mini-btn text-decoration-none me-1">
                                    <i class="bx bx-file"></i> Cert
                                </a>
                            @endif
                            @if ($leave->status === 'approved')
                                <button type="button" class="portal-mini-btn" style="border-color:rgba(253,164,175,0.34); color:#fda4af;"
                                    onclick="reviewStaffLeave({{ $leave->id }}, 'cancel')">
                                    Revoke
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 small" style="color:#9e8780;">No leave history yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Manual Approved Leave Modal --}}
<div class="modal fade sa-modal" id="adminManualLeaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" style="color:#f8efe6;">Log Approved Leave</h5>
                    <div class="small" style="color:#b8a29a;">Immediately approves leave for a branch employee and blocks Enhanced Calendar slots.</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('staff-leaves.manual.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="mlStaffId">Leave-Eligible Employee *</label>
                        <select id="mlStaffId" name="staff_id" class="form-select" required>
                            <option value="">-- Select branch employee --</option>
                            @foreach ($eligibleStaffList as $es)
                                <option value="{{ $es->id }}">{{ $es->name }} · {{ $es->location_label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="mlType">Leave Type *</label>
                            <select id="mlType" name="leave_type" class="form-select" required>
                                <option value="annual">Annual Leave</option>
                                <option value="sick">Sick Leave</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="mlStart">Start Date *</label>
                            <input type="date" id="mlStart" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="mlEnd">End Date *</label>
                            <input type="date" id="mlEnd" name="end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="mlReason">Reason</label>
                        <textarea id="mlReason" name="reason" class="form-control" rows="2" maxlength="1000" placeholder="Optional reason"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="mlNotes">Admin Notes</label>
                        <input type="text" id="mlNotes" name="admin_notes" class="form-control" maxlength="500" placeholder="e.g., Approved in person">
                    </div>
                    <div class="mb-1">
                        <label class="form-label" for="mlCert">Medical Certificate (optional for Sick Leave)</label>
                        <input type="file" id="mlCert" name="medical_certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sa-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn-rosegold">Save Approved Leave</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.reviewStaffLeave = async function (leaveId, action) {
        if (action === 'cancel' && !confirm('Revoke this approved leave? This will restore Enhanced Calendar availability immediately.')) {
            return;
        }
        const notesEl = document.getElementById('leaveNotes' + leaveId);
        const adminNotes = notesEl ? notesEl.value.trim() : '';

        try {
            const res = await fetch(`/staff-leaves/${leaveId}/review`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: action, admin_notes: adminNotes }),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                window.location.href = '{{ route('staffs.index', ['tab' => 'leaves']) }}';
            } else {
                alert(data.message || 'Could not update leave request.');
            }
        } catch (e) {
            alert('Network error updating leave request.');
        }
    };
</script>
@endif
