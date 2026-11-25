{{-- Admin-Only Leave Requests Queue & Manual Leave Logger inside Shifts & Ops Actions --}}
@if ($isAdminViewer ?? false)
@php
    $leavesCollection = $staffLeaves ?? collect();
    $pendingLeaves = $leavesCollection->where('status', \App\Models\StaffLeave::STATUS_PENDING)->values();
    $historyLeaves = $leavesCollection->where('status', '!=', \App\Models\StaffLeave::STATUS_PENDING)->values();
    $conflictsMap = $leaveConflictsById ?? [];
    $deductionSuggestionsMap = $leaveDeductionSuggestionsById ?? [];
    $eligibleStaffList = $leaveEligibleStaff ?? collect();
    $leavesReloadUrl = route('staffs.index', ['tab' => 'leaves']);

    $badgeStyleFor = static function (string $tone): string {
        return match ($tone) {
            'emerald' => 'background:rgba(52,211,153,0.14); color:#6ee7b7; border:1px solid rgba(52,211,153,0.30);',
            'amber' => 'background:rgba(245,158,11,0.14); color:#fbbf24; border:1px solid rgba(245,158,11,0.34);',
            'danger' => 'background:rgba(251,113,133,0.14); color:#fda4af; border:1px solid rgba(251,113,133,0.34);',
            default => 'background:rgba(255,255,255,0.06); color:#cbb8b0; border:1px solid rgba(255,255,255,0.10);',
        };
    };
@endphp

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h6 class="fw-bold mb-1" style="color:#f8efe6;">Annual &amp; Sick Leave Requests</h6>
        <p class="text-muted small mb-0">Approve or reject branch staff leave requests. Approved leaves automatically block Enhanced Calendar bookings; uncertified, late (&gt;3 days), or rejected sick leaves can record a linked salary deduction.</p>
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
        @php
            $bookingConflicts = $conflictsMap[$leave->id] ?? [];
            $complianceBadges = $leave->complianceBadges();
            $dedSuggestion = $deductionSuggestionsMap[$leave->id] ?? null;
            $isSickLeave = $leave->isSick();
            $isComplianceRisk = $leave->isComplianceRisk();
            $dateRangeLabel = $leave->start_date->equalTo($leave->end_date)
                ? $leave->start_date->format('d M Y')
                : ($leave->start_date->format('d M') . ' – ' . $leave->end_date->format('d M Y'));
        @endphp
        <div class="roster-card p-3 mb-2">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="min-w-0 flex-grow-1">
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                        <span class="fw-bold" style="color:#f8efe6; font-size:14.5px;">{{ $leave->staff?->name ?? 'Staff' }}</span>
                        <span class="badge" style="background:rgba(212,163,115,0.14); color:#f3d9a4;">{{ $leave->staff?->location_label ?? 'Salon' }}</span>
                        <span class="badge" style="background:{{ $isSickLeave ? 'rgba(253,164,175,0.16); color:#fda4af;' : 'rgba(110,231,183,0.14); color:#6ee7b7;' }}">
                            {{ $leave->typeLabel() }}
                        </span>
                        <span class="small" style="color:#cbb8b0;">
                            {{ $leave->start_date->format('d M Y') }} &ndash; {{ $leave->end_date->format('d M Y') }}
                            ({{ $leave->total_days }} {{ $leave->total_days === 1 ? 'day' : 'days' }})
                        </span>
                    </div>

                    @if (!empty($complianceBadges))
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @foreach ($complianceBadges as $cb)
                                <span class="badge" style="{{ $badgeStyleFor($cb['tone']) }} font-weight:600; font-size:11px;">
                                    {{ $cb['label'] }}
                                </span>
                            @endforeach
                        </div>
                    @endif

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

                <div style="min-width: 280px;">
                    <input type="text" id="leaveNotes{{ $leave->id }}" class="form-control form-control-sm mb-2" placeholder="Optional admin notes…" maxlength="500">
                    @if ($isSickLeave)
                        @php
                            $modalPayload = [
                                'leaveId' => $leave->id,
                                'staffName' => $leave->staff?->name ?? 'Staff',
                                'dateRange' => $dateRangeLabel,
                                'totalDays' => (int) $leave->total_days,
                                'isComplianceRisk' => $isComplianceRisk,
                                'deductionApplied' => (bool) $leave->deduction_applied,
                                'monthlySalary' => $dedSuggestion['monthly_salary'] ?? null,
                                'suggestedAmount' => $dedSuggestion['suggested_amount'] ?? null,
                                'defaultReason' => $dedSuggestion['default_reason'] ?? $leave->defaultDeductionReason(),
                            ];
                        @endphp
                        <div class="d-flex flex-wrap gap-2">
                            @if ($isComplianceRisk)
                                <button type="button" class="btn btn-sm flex-fill js-open-sick-review"
                                    style="background:rgba(245,158,11,0.16); color:#fbbf24; border:1px solid rgba(245,158,11,0.38); font-weight:600;"
                                    data-action="approve"
                                    data-payload='@json($modalPayload)'>
                                    <i class="bx bx-check-shield"></i> Approve / Close with Deduction
                                </button>
                            @else
                                <button type="button" class="btn btn-sm flex-fill"
                                    style="background:rgba(110,231,183,0.16); color:#6ee7b7; border:1px solid rgba(110,231,183,0.36); font-weight:600;"
                                    onclick="reviewStaffLeave({{ $leave->id }}, 'approve')">
                                    <i class="bx bx-check"></i> Approve
                                </button>
                            @endif
                            <button type="button" class="btn btn-sm flex-fill js-open-sick-review"
                                style="background:rgba(253,164,175,0.14); color:#fda4af; border:1px solid rgba(253,164,175,0.36); font-weight:600;"
                                data-action="reject"
                                data-payload='@json($modalPayload)'>
                                <i class="bx bx-x"></i> Reject
                            </button>
                        </div>
                    @else
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
                    @endif
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
                    <th>Type &amp; Compliance</th>
                    <th>Dates</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Admin Notes</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($historyLeaves as $leave)
                    @php $histBadges = $leave->complianceBadges(); @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold" style="color:#f8efe6;">{{ $leave->staff?->name ?? '—' }}</div>
                            <div class="small" style="color:#9e8780;">{{ $leave->staff?->location_label ?? '' }}</div>
                        </td>
                        <td>
                            <div class="fw-medium" style="color:#f8efe6;">{{ $leave->typeLabel() }}</div>
                            @if (!empty($histBadges))
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    @foreach ($histBadges as $hb)
                                        <span class="badge" style="{{ $badgeStyleFor($hb['tone']) }} font-weight:600; font-size:10.5px;">
                                            {{ $hb['label'] }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
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

{{-- Sick Leave Review & Linked Salary Deduction Confirmation Modal --}}
<div class="modal fade sa-modal" id="sickLeaveReviewModal" tabindex="-1" aria-labelledby="sickLeaveReviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="sickLeaveReviewTitle" style="color:#f8efe6;">Review Sick Leave</h5>
                    <div class="small" style="color:#b8a29a;" id="slrSubtitle">Confirm decision and optional salary deduction.</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="sickLeaveReviewForm" onsubmit="return submitSickLeaveReviewModal(event);">
                <input type="hidden" id="slrLeaveId" value="">
                <input type="hidden" id="slrAction" value="reject">
                <div class="modal-body">
                    <div class="alert alert-danger py-2 px-3 small d-none mb-3" id="slrErrorAlert"></div>

                    <div class="p-3 mb-3" style="border-radius:12px; background:rgba(251,113,133,0.08); border:1px solid rgba(251,113,133,0.24);">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="slrRecordDeduction" checked>
                            <label class="form-check-label fw-semibold" for="slrRecordDeduction" style="color:#f8efe6;">
                                Record Salary Deduction
                            </label>
                        </div>
                        <div class="small mt-1" style="color:#cbb8b0;">
                            Creates one linked entry in <strong>Deductions &amp; Penalties</strong> and updates the employee's portal immediately.
                        </div>
                    </div>

                    <div id="slrDeductionFields">
                        <div class="mb-3">
                            <label class="form-label" for="slrDeductionAmount">Deduction Amount (QAR) *</label>
                            <input type="number" step="0.01" min="0.01" max="999999" id="slrDeductionAmount" class="form-control" placeholder="e.g. 100.00">
                            <div class="small mt-1" id="slrSalaryHint" style="color:#9e8780;"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="slrDeductionReason">Deduction Reason</label>
                            <input type="text" id="slrDeductionReason" class="form-control" maxlength="500" placeholder="Unapproved sick leave — missing medical certificate">
                        </div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label" for="slrAdminNotes">Admin Notes (optional)</label>
                        <input type="text" id="slrAdminNotes" class="form-control" maxlength="500" placeholder="Visible to the employee in Leave History">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sc-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="sc-btn-rosegold" id="slrConfirmBtn">Confirm Decision</button>
                </div>
            </form>
        </div>
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
                    <button type="button" class="sc-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="sc-btn-rosegold">Save Approved Leave</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.reviewStaffLeave = async function (leaveId, action, extraPayload = {}, silentError = false) {
        if (action === 'cancel' && !confirm('Revoke this approved leave? This will restore Enhanced Calendar availability immediately.')) {
            return { ok: false, cancelled: true };
        }
        const notesEl = document.getElementById('leaveNotes' + leaveId);
        const adminNotes = (typeof extraPayload.admin_notes === 'string')
            ? extraPayload.admin_notes.trim()
            : (notesEl ? notesEl.value.trim() : '');

        const body = Object.assign({ action: action, admin_notes: adminNotes }, extraPayload, { admin_notes: adminNotes });

        try {
            const res = await fetch(`/staff-leaves/${leaveId}/review`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(body),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.success) {
                window.location.href = @json($leavesReloadUrl);
                return { ok: true, data };
            }
            const errMsg = data.message || 'Could not update leave request.';
            if (!silentError) alert(errMsg);
            return { ok: false, message: errMsg };
        } catch (e) {
            const errMsg = 'Network error updating leave request.';
            if (!silentError) alert(errMsg);
            return { ok: false, message: errMsg };
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('sickLeaveReviewModal');
        const toggleEl = document.getElementById('slrRecordDeduction');
        const fieldsEl = document.getElementById('slrDeductionFields');
        const amountEl = document.getElementById('slrDeductionAmount');
        const reasonEl = document.getElementById('slrDeductionReason');
        const notesEl = document.getElementById('slrAdminNotes');
        const hintEl = document.getElementById('slrSalaryHint');
        const titleEl = document.getElementById('sickLeaveReviewTitle');
        const subtitleEl = document.getElementById('slrSubtitle');
        const confirmBtn = document.getElementById('slrConfirmBtn');
        const errorAlert = document.getElementById('slrErrorAlert');

        function syncDeductionFieldsVisibility() {
            if (!toggleEl || !fieldsEl) return;
            const checked = toggleEl.checked;
            fieldsEl.classList.toggle('d-none', !checked);
            if (amountEl) {
                amountEl.required = checked;
            }
        }

        toggleEl?.addEventListener('change', syncDeductionFieldsVisibility);

        document.querySelectorAll('.js-open-sick-review').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!modalEl) return;
                let payload = {};
                try {
                    payload = JSON.parse(btn.dataset.payload || '{}');
                } catch (err) {
                    payload = {};
                }
                const action = btn.dataset.action || 'reject';
                const leaveId = payload.leaveId || '';
                const inlineNotes = document.getElementById('leaveNotes' + leaveId);

                document.getElementById('slrLeaveId').value = leaveId;
                document.getElementById('slrAction').value = action;
                if (errorAlert) {
                    errorAlert.classList.add('d-none');
                    errorAlert.textContent = '';
                }

                const daysText = (payload.totalDays || 1) + ((payload.totalDays || 1) === 1 ? ' day' : ' days');
                if (titleEl) {
                    titleEl.textContent = action === 'approve'
                        ? 'Approve / Close Sick Leave with Deduction'
                        : 'Reject Sick Leave & Deduction';
                }
                if (subtitleEl) {
                    subtitleEl.textContent = `${payload.staffName || 'Employee'} · ${payload.dateRange || ''} (${daysText})`;
                }
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = action === 'approve' ? 'Approve & Save' : 'Reject & Save';
                }

                // Auto-check deduction for rejected or non-compliant sick leaves (unless already deducted)
                if (toggleEl) {
                    toggleEl.checked = !payload.deductionApplied && (action === 'reject' || !!payload.isComplianceRisk);
                    toggleEl.disabled = !!payload.deductionApplied;
                }
                if (amountEl) {
                    amountEl.value = payload.suggestedAmount != null ? Number(payload.suggestedAmount).toFixed(2) : '';
                }
                if (hintEl) {
                    if (payload.monthlySalary != null && payload.suggestedAmount != null) {
                        hintEl.textContent = `Auto-calculated pro-rata daily rate: ${Number(payload.monthlySalary).toLocaleString()} QAR ÷ 30 × ${payload.totalDays || 1} = ${Number(payload.suggestedAmount).toFixed(2)} QAR (editable).`;
                    } else {
                        hintEl.textContent = 'No active QAR monthly salary on file — enter the deduction amount manually.';
                    }
                }
                if (reasonEl) {
                    reasonEl.value = payload.defaultReason || '';
                }
                if (notesEl) {
                    notesEl.value = inlineNotes ? inlineNotes.value.trim() : '';
                }

                syncDeductionFieldsVisibility();

                if (window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });
        });
    });

    window.submitSickLeaveReviewModal = async function (evt) {
        evt.preventDefault();
        const leaveId = document.getElementById('slrLeaveId')?.value;
        const action = document.getElementById('slrAction')?.value || 'reject';
        const recordDeduction = !!document.getElementById('slrRecordDeduction')?.checked;
        const amountRaw = document.getElementById('slrDeductionAmount')?.value;
        const reason = document.getElementById('slrDeductionReason')?.value?.trim() || '';
        const adminNotes = document.getElementById('slrAdminNotes')?.value?.trim() || '';
        const errorAlert = document.getElementById('slrErrorAlert');
        const confirmBtn = document.getElementById('slrConfirmBtn');

        if (errorAlert) {
            errorAlert.classList.add('d-none');
            errorAlert.textContent = '';
        }

        if (recordDeduction && (!amountRaw || Number(amountRaw) <= 0)) {
            if (errorAlert) {
                errorAlert.textContent = 'Please enter a valid Deduction Amount (QAR) greater than 0.';
                errorAlert.classList.remove('d-none');
            }
            return false;
        }

        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Saving…';
        }

        const payload = {
            admin_notes: adminNotes,
            record_deduction: recordDeduction,
        };
        if (recordDeduction) {
            payload.deduction_amount = Number(amountRaw);
            payload.deduction_reason = reason;
        }

        const result = await window.reviewStaffLeave(leaveId, action, payload, true);
        if (!result || !result.ok) {
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = action === 'approve' ? 'Approve & Save' : 'Reject & Save';
            }
            if (errorAlert) {
                errorAlert.textContent = (result && result.message) ? result.message : 'Could not update sick leave request.';
                errorAlert.classList.remove('d-none');
            }
        }
        return false;
    };
</script>
@endif
