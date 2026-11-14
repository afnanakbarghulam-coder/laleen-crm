@php
    $hubOpsItems = $opsItems ?? collect();
    $hubOpsOpen = $opsOpenCount ?? 0;
    $hubOpsCompleted = $opsCompletedCount ?? 0;
    $hubOpsTotal = $opsTotalCount ?? 0;
    $hubOpsRate = $opsCompletionRate ?? 0;
    $hubOpsMyOpen = $opsMyOpenCount ?? 0;
    $hubOpsStatus = $opsStatus ?? 'open';
    $hubOpsMine = $opsMine ?? false;
    $hubOpsStaffId = $opsSelectedStaffId ?? null;
    $hubOpsStaffName = $opsSelectedStaffName ?? null;
    $hubOpsAssignees = $opsAssigneeOptions ?? collect();
    $hubOpsMon = $opsMonday ?? now()->startOfWeek(\Illuminate\Support\Carbon::MONDAY);
    $hubOpsSun = $opsSunday ?? $hubOpsMon->copy()->endOfWeek(\Illuminate\Support\Carbon::SUNDAY);
    $hubOpsPrev = $opsPrevWeek ?? $hubOpsMon->copy()->subWeek()->toDateString();
    $hubOpsNext = $opsNextWeek ?? $hubOpsMon->copy()->addWeek()->toDateString();
    $hubOpsIsCurrent = $opsIsCurrentWeek ?? true;
    $hubOpsScopes = $opsScopes ?? \App\Models\OpsActionItem::SCOPES;
    $hubOpsSla = $opsSlaOptions ?? \App\Models\OpsActionItem::SLA_OPTIONS;

    $hubScoreClass = $hubOpsRate >= 90
        ? 'hub-ops-score-green'
        : ($hubOpsRate >= 75 ? 'hub-ops-score-amber' : 'hub-ops-score-red');

    $authHubUser = auth()->user();
    $canVerifyHubOps = $authHubUser
        && (\App\Models\OpsActionItem::isAdminOrOwner($authHubUser) || \App\Models\OpsActionItem::isArbab($authHubUser));
@endphp

<style>
    .hub-ops-score-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid rgba(229, 184, 168, 0.24);
        background: rgba(34, 22, 27, 0.88);
        color: #f8efe6;
    }
    .hub-ops-score-green { border-color: rgba(110, 231, 183, 0.35); color: #6ee7b7; }
    .hub-ops-score-amber { border-color: rgba(212, 163, 115, 0.38); color: #d4a373; }
    .hub-ops-score-red { border-color: rgba(253, 164, 175, 0.35); color: #fda4af; }

    .hub-ops-filter-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 13px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid rgba(229, 184, 168, 0.16);
        background: rgba(34, 22, 27, 0.65);
        color: #b8a29a;
        transition: all 0.15s ease;
    }
    .hub-ops-filter-pill:hover {
        color: #f8efe6;
        border-color: rgba(229, 184, 168, 0.32);
    }
    .hub-ops-filter-pill.active {
        background: rgba(229, 184, 168, 0.16);
        color: #f8efe6;
        border-color: rgba(229, 184, 168, 0.44);
        font-weight: 700;
    }

    .hub-ops-dropdown-wrap { position: relative; display: inline-block; }
    .hub-ops-dropdown-menu {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        min-width: 220px;
        background: #211519;
        border: 1px solid rgba(229, 184, 168, 0.22);
        border-radius: 12px;
        padding: 6px;
        z-index: 1050;
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.45);
    }
    .hub-ops-dropdown-menu.open { display: block; }
    .hub-ops-dropdown-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 10px;
        border-radius: 8px;
        color: #f8efe6;
        font-size: 12px;
        text-decoration: none;
    }
    .hub-ops-dropdown-item:hover,
    .hub-ops-dropdown-item.active {
        background: rgba(229, 184, 168, 0.14);
        color: #f8efe6;
    }

    .hub-ops-card {
        background: linear-gradient(180deg, rgba(34, 22, 27, 0.92) 0%, rgba(21, 14, 18, 0.96) 100%);
        border: 1px solid rgba(229, 184, 168, 0.14);
        border-radius: 16px;
        overflow: hidden;
    }

    .hub-ops-circle {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: 1.5px solid rgba(229, 184, 168, 0.4);
        background: transparent;
        color: transparent;
        font-size: 11px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .hub-ops-circle:hover { border-color: #6ee7b7; color: #6ee7b7; }
    .hub-ops-circle.is-done {
        background: rgba(110, 231, 183, 0.18);
        border-color: #6ee7b7;
        color: #6ee7b7;
    }

    .hub-ops-assignee-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        background: rgba(229, 184, 168, 0.09);
        border: 1px solid rgba(229, 184, 168, 0.18);
        color: #f8efe6;
    }

    .hub-ops-awaiting-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.09);
        color: #b8a29a;
        white-space: nowrap;
    }

    .hub-ops-verified-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        background: rgba(110, 231, 183, 0.10);
        border: 1px solid rgba(110, 231, 183, 0.24);
        color: #6ee7b7;
        white-space: nowrap;
    }

    .hub-ops-verify-modal .modal-content {
        background: linear-gradient(180deg, rgba(34, 22, 27, 0.98) 0%, rgba(20, 13, 17, 0.99) 100%);
        border: 1px solid rgba(229, 184, 168, 0.22);
        border-radius: 16px;
        color: #f8efe6;
        box-shadow: 0 24px 56px rgba(0, 0, 0, 0.62);
    }
    .hub-ops-verify-modal .modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        padding: 16px 20px;
    }
    .hub-ops-verify-modal .modal-body {
        padding: 18px 20px;
    }
    .hub-ops-verify-modal .modal-footer {
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        padding: 14px 20px;
    }
    .hub-ops-verify-textarea {
        width: 100%;
        background: rgba(18, 11, 14, 0.78);
        border: 1px solid rgba(229, 184, 168, 0.24);
        border-radius: 10px;
        color: #f8efe6;
        font-size: 13px;
        padding: 10px 12px;
        resize: vertical;
        outline: none;
        transition: border-color 0.15s ease;
    }
    .hub-ops-verify-textarea:focus {
        border-color: #d98f83;
    }
    .hub-ops-verify-textarea::placeholder {
        color: rgba(184, 162, 154, 0.68);
    }
    .hub-ops-verify-btn {
        background: linear-gradient(135deg, #e09b8f 0%, #c97c70 100%);
        color: #1c110f;
        font-weight: 700;
        font-size: 12.5px;
        padding: 7px 16px;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.16);
        transition: opacity 0.15s ease, filter 0.15s ease;
    }
    .hub-ops-verify-btn:disabled {
        opacity: 0.42;
        cursor: not-allowed;
        filter: grayscale(0.25);
    }

    .hub-ops-drawer-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(10, 6, 8, 0.62);
        z-index: 1080;
        display: none;
    }
    .hub-ops-drawer-backdrop.open { display: block; }

    .hub-ops-slide-drawer {
        position: fixed;
        top: 0;
        right: -460px;
        width: 100%;
        max-width: 430px;
        height: 100vh;
        background: linear-gradient(180deg, #24171c 0%, #170f13 100%);
        border-left: 1px solid rgba(229, 184, 168, 0.22);
        z-index: 1090;
        display: flex;
        flex-direction: column;
        transition: right 0.22s ease;
        box-shadow: -12px 0 36px rgba(0, 0, 0, 0.5);
    }
    .hub-ops-slide-drawer.open { right: 0; }

    .hub-ops-dark-select {
        background-color: #1b1214 !important;
        color: #f8efe6 !important;
        border: 1px solid rgba(229, 184, 168, 0.24) !important;
        border-radius: 10px !important;
        color-scheme: dark;
    }
    .hub-ops-dark-select:focus {
        border-color: #e5b8a8 !important;
        box-shadow: 0 0 0 3px rgba(229, 184, 168, 0.14) !important;
    }
    .hub-ops-dark-select option,
    .hub-ops-dark-select optgroup {
        background-color: #1b1214;
        color: #f8efe6;
    }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <h6 class="fw-bold mb-0" style="color: #f8efe6;">Ops &amp; Action Tracker</h6>
        <span class="hub-ops-score-pill {{ $hubScoreClass }}">
            Weekly Score: {{ $hubOpsCompleted }}/{{ $hubOpsTotal }} ({{ $hubOpsRate }}%)
        </span>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('staffs.index', array_filter(['tab' => 'ops_tracker', 'ops_week' => $hubOpsPrev, 'ops_status' => $hubOpsStatus, 'ops_mine' => $hubOpsMine ? 1 : null, 'ops_staff_id' => $hubOpsStaffId])) }}"
           class="sc-btn-ghost">
            &#9664; Prev Week
        </a>
        <span class="sc-inline-chip">
            Week of {{ $hubOpsMon->format('d M') }} &ndash; {{ $hubOpsSun->format('d M Y') }}
        </span>
        <a href="{{ route('staffs.index', array_filter(['tab' => 'ops_tracker', 'ops_week' => $hubOpsNext, 'ops_status' => $hubOpsStatus, 'ops_mine' => $hubOpsMine ? 1 : null, 'ops_staff_id' => $hubOpsStaffId])) }}"
           class="sc-btn-ghost">
            Next Week &#9654;
        </a>
        @if (!$hubOpsIsCurrent)
            <a href="{{ route('staffs.index', array_filter(['tab' => 'ops_tracker', 'ops_status' => $hubOpsStatus, 'ops_mine' => $hubOpsMine ? 1 : null, 'ops_staff_id' => $hubOpsStaffId])) }}"
               class="sc-btn-rosegold">
                This Week
            </a>
        @endif
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a href="{{ route('staffs.index', ['tab' => 'ops_tracker', 'ops_week' => $hubOpsMon->toDateString(), 'ops_status' => 'open']) }}"
           class="hub-ops-filter-pill {{ $hubOpsStatus === 'open' && !$hubOpsMine && !$hubOpsStaffId ? 'active' : '' }}">
            Open ({{ $hubOpsOpen }})
        </a>

        <a href="{{ route('staffs.index', ['tab' => 'ops_tracker', 'ops_week' => $hubOpsMon->toDateString(), 'ops_status' => 'completed']) }}"
           class="hub-ops-filter-pill {{ $hubOpsStatus === 'completed' ? 'active' : '' }}">
            ✅ Completed ({{ $hubOpsCompleted }})
        </a>

        <a href="{{ route('staffs.index', ['tab' => 'ops_tracker', 'ops_week' => $hubOpsMon->toDateString(), 'ops_status' => 'open', 'ops_mine' => 1]) }}"
           class="hub-ops-filter-pill {{ $hubOpsMine && $hubOpsStatus === 'open' ? 'active' : '' }}">
            🔴 Pending Me ({{ $hubOpsMyOpen }})
        </a>

        <div class="hub-ops-dropdown-wrap" id="hubOpsStaffDropdownWrap">
            <button type="button"
                    class="hub-ops-filter-pill {{ $hubOpsStaffId ? 'active' : '' }}"
                    onclick="toggleHubOpsStaffMenu(event)">
                👤 Staff: {{ $hubOpsStaffName ?? 'All Staff' }} ▾
            </button>
            <div class="hub-ops-dropdown-menu" id="hubOpsStaffMenu">
                <a href="{{ route('staffs.index', ['tab' => 'ops_tracker', 'ops_week' => $hubOpsMon->toDateString(), 'ops_status' => 'open']) }}"
                   class="hub-ops-dropdown-item {{ !$hubOpsStaffId && !$hubOpsMine && $hubOpsStatus === 'open' ? 'active' : '' }}">
                    <span>All Staff</span>
                    <span>{{ $hubOpsOpen }}</span>
                </a>
                @foreach ($hubOpsAssignees as $opt)
                    <a href="{{ route('staffs.index', ['tab' => 'ops_tracker', 'ops_week' => $hubOpsMon->toDateString(), 'ops_status' => 'open', 'ops_staff_id' => $opt['id']]) }}"
                       class="hub-ops-dropdown-item {{ (int) $hubOpsStaffId === (int) $opt['id'] ? 'active' : '' }}">
                        <span class="text-truncate">{{ $opt['name'] }}</span>
                        <span>{{ $opt['open_count'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    @moduleEdit('staff_management')
        <button type="button" class="sc-btn-rosegold" onclick="openHubOpsCreateDrawer()">
            + Add Task
        </button>
    @endmoduleEdit
</div>

<div class="hub-ops-card mb-3">
    <div class="table-responsive">
        <table class="table team-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Task &amp; Note</th>
                    <th style="width: 220px;">Pending With</th>
                    <th style="width: 200px;">Deadline / Status</th>
                    <th class="text-end" style="width: 175px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($hubOpsItems as $item)
                    @php
                        $assignedStamp = ($item->ball_assigned_at ?? $item->created_at)
                            ? ($item->ball_assigned_at ?? $item->created_at)->format('d M Y, h:i A')
                            : now()->format('d M Y, h:i A');
                        $completedStamp = ($item->completed_at ?? $item->updated_at)
                            ? ($item->completed_at ?? $item->updated_at)->format('d M Y, h:i A')
                            : now()->format('d M Y, h:i A');
                        $canCompleteItem = $item->canBeCompletedBy(auth()->user());
                        $isOwnerViewer = \App\Models\OpsActionItem::isAdminOrOwner(auth()->user());
                        $itemSlaHrs = (int) ($item->sla_hours ?: 48);
                        $savedVerifyRemark = trim((string) ($item->completion_remarks ?: $item->proof_note ?? ''));
                        $verifierDisplay = trim((string) ($item->completed_by ?: 'Ops Lead'));
                        $editTaskPayload = [
                            'id' => $item->id,
                            'title' => $item->title,
                            'notes' => (string) ($item->notes ?? ''),
                            'assigned_user_ids' => is_array($item->assigned_user_ids) ? array_values(array_map('intval', $item->assigned_user_ids)) : [],
                            'assigned_names' => $item->assignee_list,
                            'sla_hours' => $itemSlaHrs,
                            'keep_deadline_label' => $item->currentDeadlineOptionLabel(),
                            'update_url' => route('staff.ops-tracker.update', $item),
                        ];
                        $verifyModalPayload = [
                            'id' => $item->id,
                            'title' => $item->title,
                            'assignees' => $item->assignee_list,
                            'toggle_url' => route('staff.ops-tracker.toggle', $item),
                            'mode' => $item->is_completed ? 'reopen' : 'complete',
                        ];
                    @endphp
                    <tr data-ops-row-id="{{ $item->id }}">
                        <td>
                            <div class="d-flex align-items-start gap-2">
                                @if ($canCompleteItem)
                                    <button type="button"
                                            class="hub-ops-circle {{ $item->is_completed ? 'is-done' : '' }}"
                                            onclick="openHubOpsVerifyModal(@js($verifyModalPayload))"
                                            title="{{ $item->is_completed ? 'Move Back to Pending' : 'Confirm & Verify Task Completion' }}">
                                        ✓
                                    </button>
                                @elseif ($item->is_completed)
                                    <span class="hub-ops-circle is-done" style="cursor: default;" aria-hidden="true">✓</span>
                                @endif

                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="fw-semibold {{ $item->is_completed ? 'text-decoration-line-through text-muted' : '' }}" style="color: #f8efe6; font-size: 13.5px;">
                                            {{ $item->title }}
                                        </span>
                                        <span class="sc-inline-chip">{{ $item->scope }}</span>
                                        @if (!$item->is_completed && $item->carryover_weeks > 0)
                                            <span class="sc-status-badge sc-status-pending_signature">Carryover ({{ $item->carryover_weeks }}w)</span>
                                        @endif
                                        @moduleEdit('staff_management')
                                            <button type="button"
                                                    class="btn btn-sm btn-link p-0 text-decoration-none"
                                                    style="color: #e5b8a8;"
                                                    onclick="openHubOpsEditDrawer(@js($editTaskPayload))"
                                                    title="Update note &amp; assignees">
                                                &#9998;
                                            </button>
                                        @endmoduleEdit
                                    </div>
                                    <div class="small mt-1" style="color: #b8a29a; font-size: 11.5px;">
                                        @if ($item->notes)
                                            <span>{{ $item->notes }}</span> &bull;
                                        @endif
                                        <span>Assigned: {{ $assignedStamp }}</span>
                                        @if ($item->is_completed)
                                            &bull; <span style="color: #6ee7b7;">Completed: {{ $completedStamp }}</span>
                                        @endif
                                    </div>
                                    @if ($item->is_completed)
                                        <div class="d-flex flex-wrap align-items-center gap-2 mt-1" style="font-size: 11.5px;">
                                            <span class="ui-chip hub-ops-verified-chip">
                                                Verified by {{ $verifierDisplay }} &middot; {{ $completedStamp }}
                                            </span>
                                            @if ($savedVerifyRemark !== '')
                                                <span style="color: #e5b8a8;">&ldquo;{{ $savedVerifyRemark }}&rdquo;</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach ($item->assignee_list as $assigneeName)
                                    <span class="hub-ops-assignee-pill">👤 {{ $assigneeName }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            @if (!$item->is_completed)
                                @if ($item->is_overdue)
                                    <span class="sc-status-badge" style="background: rgba(253, 164, 175, 0.14); color: #fda4af; border: 1px solid rgba(253, 164, 175, 0.3);">🚨 Overdue</span>
                                @else
                                    <span class="sc-status-badge sc-status-pending_signature">⏳ {{ $item->hours_remaining }}h left</span>
                                @endif
                            @else
                                <span class="sc-status-badge sc-status-active">✓ Completed</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex align-items-center gap-1">
                                @if ($canCompleteItem)
                                    <button type="button"
                                            class="sc-action-pill"
                                            onclick="openHubOpsVerifyModal(@js($verifyModalPayload))">
                                        {{ $item->is_completed ? '↺ Reopen' : '✓ Mark Done' }}
                                    </button>
                                @elseif (!$item->is_completed)
                                    <span class="ui-chip hub-ops-awaiting-pill" title="Only Admin or Arbab can verify and mark this task completed">
                                        Awaiting Verification
                                    </span>
                                @else
                                    <span class="ui-chip hub-ops-verified-chip">✓ Verified</span>
                                @endif

                                @if ($isOwnerViewer)
                                    <form method="POST" action="{{ route('staff.ops-tracker.destroy', $item) }}" class="m-0 d-inline" onsubmit="return confirm('Delete this task permanently?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="sc-action-pill sc-action-danger" title="Delete task">✕</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            No operational tasks in this view. Click <strong>+ Add Task</strong> above to log an action item.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@moduleEdit('staff_management')
    <div class="hub-ops-drawer-backdrop" id="hubOpsDrawerBackdrop" onclick="closeHubOpsDrawers()"></div>

    <aside class="hub-ops-slide-drawer" id="hubOpsCreateDrawer" aria-hidden="true">
        <div class="d-flex justify-content-between align-items-center p-3" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
            <h6 class="fw-bold mb-0" style="color: #f8efe6;">Add Action Item</h6>
            <button type="button" class="sc-action-pill" onclick="closeHubOpsDrawers()">✕</button>
        </div>

        <form method="POST" action="{{ route('staff.ops-tracker.store') }}" class="d-flex flex-column flex-grow-1 overflow-hidden m-0">
            @csrf
            <input type="hidden" name="return_to_staff_hub" value="1">
            <input type="hidden" name="week_start_date" value="{{ $hubOpsMon->toDateString() }}">

            <div class="p-3 flex-grow-1 overflow-auto">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Task / Issue *</label>
                    <input type="text" name="title" id="hubOpsCreateTitle" class="form-control form-control-sm" placeholder="What needs to be done or followed up?" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Note / Context (Optional)</label>
                    <textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="Optional context or details..."></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Area</label>
                    <select name="scope" class="form-select form-select-sm">
                        @foreach ($hubOpsScopes as $scopeOpt)
                            <option value="{{ $scopeOpt }}" {{ $scopeOpt === 'Laleen Rituals' ? 'selected' : '' }}>{{ $scopeOpt }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Pending With (Staff Access) *</label>
                    <div class="p-2 rounded" style="background: rgba(20, 14, 17, 0.6); border: 1px solid rgba(229, 184, 168, 0.16); max-height: 185px; overflow-y: auto;">
                        @forelse ($hubOpsAssignees as $idx => $opt)
                            <label class="d-flex justify-content-between align-items-center py-1 px-1 small" style="cursor: pointer;">
                                <span class="d-inline-flex align-items-center gap-2">
                                    <input type="checkbox" name="assigned_user_ids[]" value="{{ $opt['id'] }}" {{ $idx === 0 ? 'checked' : '' }}>
                                    <span style="color: #f8efe6;">{{ $opt['name'] }}</span>
                                </span>
                                <span class="text-muted" style="font-size: 11px;">{{ ucwords(str_replace(['_', '-'], ' ', (string) ($opt['role_label'] ?? ''))) }}</span>
                            </label>
                        @empty
                            <div class="text-muted small">No active Staff Access users found.</div>
                        @endforelse
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-semibold">Timeframe</label>
                    <select name="sla_hours" class="form-select form-select-sm hub-ops-dark-select">
                        @foreach ($hubOpsSla as $hoursVal => $hoursLabel)
                            <option value="{{ $hoursVal }}" {{ (int) $hoursVal === 48 ? 'selected' : '' }}>{{ $hoursLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="p-3 d-flex justify-content-end gap-2" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                <button type="button" class="sc-btn-ghost" onclick="closeHubOpsDrawers()">Cancel</button>
                <button type="submit" class="sc-btn-rosegold">+ Save Task</button>
            </div>
        </form>
    </aside>

    <aside class="hub-ops-slide-drawer" id="hubOpsEditDrawer" aria-hidden="true">
        <div class="d-flex justify-content-between align-items-center p-3" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
            <h6 class="fw-bold mb-0" style="color: #f8efe6;">Update Note &amp; Staff</h6>
            <button type="button" class="sc-action-pill" onclick="closeHubOpsDrawers()">✕</button>
        </div>

        <form method="POST" action="" id="hubOpsEditForm" class="d-flex flex-column flex-grow-1 overflow-hidden m-0">
            @csrf
            @method('PATCH')

            <div class="p-3 flex-grow-1 overflow-auto">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Task / Issue (Locked)</label>
                    <div class="p-2 rounded small fw-semibold" id="hubOpsEditLockedTitle" style="background: rgba(20, 14, 17, 0.6); border: 1px solid rgba(229, 184, 168, 0.16); color: #f8efe6;"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Note / Context</label>
                    <textarea name="notes" id="hubOpsEditNotes" rows="3" class="form-control form-control-sm" placeholder="Add or update note..."></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Pending With (Staff Access)</label>
                    <div class="p-2 rounded" style="background: rgba(20, 14, 17, 0.6); border: 1px solid rgba(229, 184, 168, 0.16); max-height: 185px; overflow-y: auto;">
                        @foreach ($hubOpsAssignees as $opt)
                            <label class="d-flex justify-content-between align-items-center py-1 px-1 small" style="cursor: pointer;">
                                <span class="d-inline-flex align-items-center gap-2">
                                    <input type="checkbox" name="assigned_user_ids[]" value="{{ $opt['id'] }}"
                                           data-uid="{{ $opt['id'] }}" data-uname="{{ strtolower($opt['name']) }}"
                                           class="hub-ops-edit-cb">
                                    <span style="color: #f8efe6;">{{ $opt['name'] }}</span>
                                </span>
                                <span class="text-muted" style="font-size: 11px;">{{ ucwords(str_replace(['_', '-'], ' ', (string) ($opt['role_label'] ?? ''))) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @if (auth()->user()?->isAdmin())
                    <div class="mb-2" id="hubOpsEditTimeframeSection">
                        <label class="form-label small fw-semibold" for="hubOpsEditDeadlineAction">Timeframe / Extend Deadline (Admin Only)</label>
                        <select name="deadline_action" id="hubOpsEditDeadlineAction" class="form-select form-select-sm hub-ops-dark-select">
                            <option value="keep" id="hubOpsEditKeepDeadlineOpt" selected>Keep Current Deadline</option>
                            <optgroup label="Extend from Current Time">
                                <option value="extend_24">+24 Hours (from now)</option>
                                <option value="extend_48">+48 Hours (from now)</option>
                                <option value="extend_72">+72 Hours / 3 Days (from now)</option>
                                <option value="extend_168">+1 Week (from now)</option>
                            </optgroup>
                            <optgroup label="Standard Timeframe Presets">
                                <option value="preset_24">24h (1 Day)</option>
                                <option value="preset_48">48h (2 Days)</option>
                                <option value="preset_72">3 Days</option>
                                <option value="preset_168">1 Week</option>
                            </optgroup>
                        </select>
                    </div>
                @endif
            </div>

            <div class="p-3 d-flex justify-content-end gap-2" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                <button type="button" class="sc-btn-ghost" onclick="closeHubOpsDrawers()">Cancel</button>
                <button type="submit" class="sc-btn-rosegold">✓ Save Changes</button>
            </div>
        </form>
    </aside>
@endmoduleEdit

@if ($canVerifyHubOps)
    <div class="modal fade hub-ops-verify-modal" id="hubOpsVerifyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
            <form method="POST" action="" id="hubOpsVerifyForm" class="modal-content m-0">
                @csrf
                <input type="hidden" name="is_completed" id="hubOpsVerifyIsCompletedInput" value="1">

                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h6 class="modal-title fw-bold mb-0" id="hubOpsVerifyModalTitle" style="color: #f8efe6; font-size: 15.5px;">
                        Confirm &amp; Verify Task Completion
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div style="padding-bottom: 14px; margin-bottom: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.06);">
                        <div class="fw-semibold mb-2" id="hubOpsVerifyTaskTitle" style="color: #f8efe6; font-size: 14px; line-height: 1.4;"></div>
                        <div class="d-flex flex-wrap align-items-center gap-1">
                            <span style="font-size: 11.5px; color: #b8a29a; margin-right: 4px;">Pending With:</span>
                            <div class="d-inline-flex flex-wrap gap-1" id="hubOpsVerifyAssigneesWrap"></div>
                        </div>
                    </div>

                    <div id="hubOpsVerifyRemarksGroup">
                        <label class="form-label small fw-semibold mb-1" for="hubOpsVerifyRemarksInput" style="color: #e5b8a8;">
                            Verification Feedback / Remarks *
                        </label>
                        <textarea name="completion_remarks"
                                  id="hubOpsVerifyRemarksInput"
                                  rows="3"
                                  maxlength="1000"
                                  class="hub-ops-verify-textarea"
                                  placeholder="Add verification notes on how this task was checked and completed..."></textarea>
                        <div class="small mt-2" id="hubOpsVerifyValidationHint" style="color: #fda4af; font-size: 11.5px;">
                            Verification feedback is required (min 3 characters) before marking this task completed.
                        </div>
                    </div>

                    <div id="hubOpsReopenNoticeGroup" class="d-none" style="font-size: 13px; color: #b8a29a;">
                        Move this completed task back to active Pending execution and restart its SLA timer?
                    </div>
                </div>

                <div class="modal-footer d-flex justify-content-end gap-2">
                    <button type="button" class="sc-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="hub-ops-verify-btn" id="hubOpsVerifySubmitBtn" disabled>
                        Confirm &amp; Mark Completed
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

<script>
    window.toggleHubOpsStaffMenu = function (event) {
        if (event) event.stopPropagation();
        document.getElementById('hubOpsStaffMenu')?.classList.toggle('open');
    };

    window.syncHubOpsVerifyButtonState = function () {
        const modeInput = document.getElementById('hubOpsVerifyIsCompletedInput');
        const remarksEl = document.getElementById('hubOpsVerifyRemarksInput');
        const submitBtn = document.getElementById('hubOpsVerifySubmitBtn');
        const hintEl = document.getElementById('hubOpsVerifyValidationHint');
        if (!submitBtn || !modeInput) return;

        if (modeInput.value === '0') {
            submitBtn.disabled = false;
            return;
        }

        const trimmed = (remarksEl ? remarksEl.value : '').trim();
        const isValid = trimmed.length >= 3;
        submitBtn.disabled = !isValid;

        if (hintEl) {
            if (isValid) {
                hintEl.style.color = '#6ee7b7';
                hintEl.textContent = '✓ Verification note ready to record.';
            } else {
                hintEl.style.color = '#fda4af';
                hintEl.textContent = 'Verification feedback is required (min 3 characters) before marking this task completed.';
            }
        }
    };

    window.openHubOpsVerifyModal = function (payload) {
        if (!payload) return;
        const modalEl = document.getElementById('hubOpsVerifyModal');
        const formEl = document.getElementById('hubOpsVerifyForm');
        const modeInput = document.getElementById('hubOpsVerifyIsCompletedInput');
        const modalTitleEl = document.getElementById('hubOpsVerifyModalTitle');
        const taskTitleEl = document.getElementById('hubOpsVerifyTaskTitle');
        const assigneesWrap = document.getElementById('hubOpsVerifyAssigneesWrap');
        const remarksGroup = document.getElementById('hubOpsVerifyRemarksGroup');
        const reopenGroup = document.getElementById('hubOpsReopenNoticeGroup');
        const remarksInput = document.getElementById('hubOpsVerifyRemarksInput');
        const submitBtn = document.getElementById('hubOpsVerifySubmitBtn');

        if (!modalEl || !formEl) return;

        formEl.action = payload.toggle_url || '';
        if (taskTitleEl) taskTitleEl.textContent = payload.title || '';

        if (assigneesWrap) {
            assigneesWrap.innerHTML = '';
            const names = Array.isArray(payload.assignees) && payload.assignees.length
                ? payload.assignees
                : ['Ops Team'];
            names.forEach(function (name) {
                const chip = document.createElement('span');
                chip.className = 'ui-chip hub-ops-assignee-pill';
                chip.textContent = '👤 ' + name;
                assigneesWrap.appendChild(chip);
            });
        }

        const isReopen = payload.mode === 'reopen';
        if (modeInput) modeInput.value = isReopen ? '0' : '1';

        if (isReopen) {
            if (modalTitleEl) modalTitleEl.textContent = 'Move Task Back to Pending?';
            remarksGroup?.classList.add('d-none');
            reopenGroup?.classList.remove('d-none');
            if (remarksInput) {
                remarksInput.required = false;
                remarksInput.value = '';
            }
            if (submitBtn) {
                submitBtn.textContent = '↺ Confirm Reopen';
                submitBtn.disabled = false;
            }
        } else {
            if (modalTitleEl) modalTitleEl.textContent = 'Confirm & Verify Task Completion';
            remarksGroup?.classList.remove('d-none');
            reopenGroup?.classList.add('d-none');
            if (remarksInput) {
                remarksInput.required = true;
                remarksInput.value = '';
            }
            if (submitBtn) {
                submitBtn.textContent = 'Confirm & Mark Completed';
                submitBtn.disabled = true;
            }
            window.syncHubOpsVerifyButtonState();
        }

        if (window.bootstrap && window.bootstrap.Modal) {
            const inst = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            inst.show();
            if (!isReopen) {
                setTimeout(function () { remarksInput?.focus(); }, 160);
            }
        }
    };

    document.getElementById('hubOpsVerifyRemarksInput')?.addEventListener('input', window.syncHubOpsVerifyButtonState);

    document.getElementById('hubOpsVerifyForm')?.addEventListener('submit', function (e) {
        const modeInput = document.getElementById('hubOpsVerifyIsCompletedInput');
        const remarksInput = document.getElementById('hubOpsVerifyRemarksInput');
        if (modeInput && modeInput.value === '1') {
            const trimmed = (remarksInput ? remarksInput.value : '').trim();
            if (trimmed.length < 3) {
                e.preventDefault();
                window.syncHubOpsVerifyButtonState();
                remarksInput?.focus();
            } else if (remarksInput) {
                remarksInput.value = trimmed;
            }
        }
    });

    window.openHubOpsCreateDrawer = function () {
        document.getElementById('hubOpsEditDrawer')?.classList.remove('open');
        document.getElementById('hubOpsDrawerBackdrop')?.classList.add('open');
        document.getElementById('hubOpsCreateDrawer')?.classList.add('open');
        setTimeout(() => document.getElementById('hubOpsCreateTitle')?.focus(), 160);
    };

    window.openHubOpsEditDrawer = function (payload) {
        if (!payload) return;
        document.getElementById('hubOpsCreateDrawer')?.classList.remove('open');
        const form = document.getElementById('hubOpsEditForm');
        if (form && payload.update_url) form.action = payload.update_url;
        const titleEl = document.getElementById('hubOpsEditLockedTitle');
        if (titleEl) titleEl.textContent = payload.title || '';
        const notesEl = document.getElementById('hubOpsEditNotes');
        if (notesEl) notesEl.value = payload.notes || '';
        const keepOptEl = document.getElementById('hubOpsEditKeepDeadlineOpt');
        if (keepOptEl) {
            keepOptEl.textContent = payload.keep_deadline_label || 'Keep Current Deadline';
        }
        const deadlineEl = document.getElementById('hubOpsEditDeadlineAction');
        if (deadlineEl) {
            deadlineEl.value = 'keep';
        }

        const ids = Array.isArray(payload.assigned_user_ids) ? payload.assigned_user_ids.map(Number) : [];
        const names = Array.isArray(payload.assigned_names) ? payload.assigned_names.map(n => String(n).toLowerCase()) : [];
        document.querySelectorAll('.hub-ops-edit-cb').forEach(cb => {
            cb.checked = ids.includes(Number(cb.dataset.uid)) || names.includes(cb.dataset.uname || '');
        });

        document.getElementById('hubOpsDrawerBackdrop')?.classList.add('open');
        document.getElementById('hubOpsEditDrawer')?.classList.add('open');
    };

    window.closeHubOpsDrawers = function () {
        document.getElementById('hubOpsDrawerBackdrop')?.classList.remove('open');
        document.getElementById('hubOpsCreateDrawer')?.classList.remove('open');
        document.getElementById('hubOpsEditDrawer')?.classList.remove('open');
    };

    document.addEventListener('click', function (e) {
        const wrap = document.getElementById('hubOpsStaffDropdownWrap');
        if (wrap && !wrap.contains(e.target)) {
            document.getElementById('hubOpsStaffMenu')?.classList.remove('open');
        }
    });
</script>
