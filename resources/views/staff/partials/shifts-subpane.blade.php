@php
    $rosterRows = $shiftRoster ?? $roster ?? [];
    $weekDays = $shiftDays ?? $days ?? collect();
    $totalsByDay = $shiftDayTotals ?? $dayTotals ?? [];
    $wStart = $shiftWeekStart ?? $weekStart ?? now()->startOfWeek(\Illuminate\Support\Carbon::SUNDAY);
    $wEnd = $shiftWeekEnd ?? $weekEnd ?? $wStart->copy()->addDays(6);
    $selectedBranch = $shiftBranch ?? request('branch', '');
    $configsMap = $shiftConfigs ?? [];
@endphp

<style>
    .roster-card {
        overflow-x: auto;
        background: linear-gradient(180deg, rgba(34, 22, 27, 0.92) 0%, rgba(21, 14, 18, 0.96) 100%);
        border: 1px solid rgba(229, 184, 168, 0.14);
        border-radius: 16px;
    }

    .roster-table { border-collapse: separate; border-spacing: 0; min-width: 900px; width: 100%; }

    .roster-table th, .roster-table td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        border-right: 1px solid rgba(255, 255, 255, 0.05);
        padding: 10px 12px;
        vertical-align: top;
    }

    .roster-table th:last-child, .roster-table td:last-child { border-right: none; }

    .roster-table thead th {
        background: rgba(217, 143, 131, 0.06);
        text-align: left;
        min-width: 120px;
    }

    .roster-table thead th .day-name { font-size: 12.5px; font-weight: 700; color: #e5b8a8; }
    .roster-table thead th .day-total { font-size: 11px; color: #b8a29a; }

    .roster-member-col { min-width: 195px; position: sticky; left: 0; background: #1c1317; z-index: 1; }

    .roster-member { display: flex; align-items: center; gap: 8px; }
    .roster-avatar { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
    .roster-member-name { font-weight: 700; font-size: 13px; color: #f8efe6; }
    .roster-member-hours { font-size: 11.5px; color: #b8a29a; }

    .roster-edit-btn {
        border: none; background: transparent; color: #b8a29a; padding: 4px; cursor: pointer;
    }
    .roster-edit-btn:hover { color: #e5b8a8; }

    .shift-pill {
        display: block;
        background: rgba(229, 184, 168, 0.11);
        color: #f8efe6;
        border: 1px solid rgba(229, 184, 168, 0.2);
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        padding: 4px 8px;
        margin-bottom: 4px;
        text-align: center;
        white-space: nowrap;
    }

    .shift-pill.timeoff { background: rgba(212, 163, 115, 0.12); color: #d4a373; border-color: rgba(212, 163, 115, 0.26); }
    .shift-pill.timeoff.sick { background: rgba(253, 164, 175, 0.12); color: #fda4af; border-color: rgba(253, 164, 175, 0.28); }

    .shift-branch-pill {
        display: inline-flex;
        align-items: center;
        padding: 5px 13px;
        border-radius: 999px;
        border: 1px solid rgba(229, 184, 168, 0.16);
        background: rgba(34, 22, 27, 0.65);
        color: #b8a29a;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .shift-branch-pill:hover {
        color: #f8efe6;
        border-color: rgba(229, 184, 168, 0.3);
    }

    .shift-branch-pill.active {
        background: rgba(229, 184, 168, 0.16);
        color: #f8efe6;
        border-color: rgba(229, 184, 168, 0.42);
        font-weight: 700;
    }

    #shiftModal .modal-dialog { max-width: 720px; }
    #shiftModal .nav-tabs .nav-link { color: #c9a39a; font-weight: 600; font-size: 13.5px; }
    #shiftModal .nav-tabs .nav-link.active { color: #e5b8a8; border-color: rgba(217, 143, 131, 0.16) rgba(217, 143, 131, 0.16) #241e1c; }
    #shiftModal .shift-tab-pane { display: none; }
    #shiftModal .shift-tab-pane.active { display: block; }

    .shift-day-row { border: 1px solid rgba(217, 143, 131, 0.16); border-radius: 10px; padding: 10px 14px; margin-bottom: 10px; }
    .shift-day-header { display: flex; justify-content: space-between; align-items: center; }
    .shift-blocks { margin-top: 8px; }
    .shift-blocks:empty { margin-top: 0; }
    .shift-block-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
    .shift-block-row input[type="time"] { max-width: 130px; }

    .timeoff-row {
        display: flex; justify-content: space-between; align-items: center;
        border: 1px solid rgba(217, 143, 131, 0.16); border-radius: 8px; padding: 8px 12px; margin-bottom: 8px; font-size: 13px;
    }
    .timeoff-row .badge.on-leave { background: rgba(201, 166, 107, 0.16); color: #c9a66b; }
    .timeoff-row .badge.sick { background: rgba(168, 82, 74, 0.14); color: #c07c73; }
    .timeoff-row .badge.unpaid, .timeoff-row .badge.other { background: rgba(217, 143, 131, 0.07); color: #cbb8b0; }

    @media print {
        .roster-card { background: #ffffff !important; color: #111111 !important; border-color: #dddddd !important; }
        .roster-member-col { background: #ffffff !important; }
    }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h6 class="fw-bold mb-1" style="color: #f8efe6;">Scheduled Shifts</h6>
        <p class="text-muted small mb-0">Manage recurring weekly working hours, branch coverage, and staff leave.</p>
    </div>
    @moduleEdit('staff_management')
        <button type="button" class="sc-btn-rosegold" id="addShiftBtn">
            + Add Shift Schedule
        </button>
    @endmoduleEdit
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2" aria-label="Branch Shift Selector">
        <a href="{{ route('staffs.index', array_filter(['tab' => 'shifts', 'shift_week' => $wStart->format('Y-m-d')])) }}"
           class="shift-branch-pill {{ $selectedBranch === '' ? 'active' : '' }}">
            All Locations
        </a>
        <a href="{{ route('staffs.index', ['tab' => 'shifts', 'shift_branch' => 'old_airport', 'shift_week' => $wStart->format('Y-m-d')]) }}"
           class="shift-branch-pill {{ $selectedBranch === 'old_airport' ? 'active' : '' }}">
            Old Airport
        </a>
        <a href="{{ route('staffs.index', ['tab' => 'shifts', 'shift_branch' => 'wakrah', 'shift_week' => $wStart->format('Y-m-d')]) }}"
           class="shift-branch-pill {{ $selectedBranch === 'wakrah' ? 'active' : '' }}">
            Al Wakrah
        </a>
        <a href="{{ route('staffs.index', ['tab' => 'shifts', 'shift_branch' => 'both', 'shift_week' => $wStart->format('Y-m-d')]) }}"
           class="shift-branch-pill {{ $selectedBranch === 'both' ? 'active' : '' }}">
            Both Branches
        </a>
    </div>

    <div class="d-flex align-items-center gap-2">
        <a class="sc-btn-ghost" href="{{ route('staffs.index', array_filter(['tab' => 'shifts', 'shift_week' => $wStart->copy()->subWeek()->format('Y-m-d'), 'shift_branch' => $selectedBranch])) }}">
            &#9664; Prev
        </a>
        <a class="sc-btn-ghost" href="{{ route('staffs.index', array_filter(['tab' => 'shifts', 'shift_branch' => $selectedBranch])) }}">
            This week
        </a>
        <span class="sc-inline-chip">{{ $wStart->format('j M') }} &ndash; {{ $wEnd->format('j M, Y') }}</span>
        <a class="sc-btn-ghost" href="{{ route('staffs.index', array_filter(['tab' => 'shifts', 'shift_week' => $wStart->copy()->addWeek()->format('Y-m-d'), 'shift_branch' => $selectedBranch])) }}">
            Next &#9654;
        </a>
    </div>
</div>

<div class="roster-card mb-3">
    <table class="roster-table">
        <thead>
            <tr>
                <th class="roster-member-col">Team Member</th>
                @foreach ($weekDays as $date)
                    <th>
                        <div class="day-name">{{ $date->format('D, j M') }}</div>
                        <div class="day-total">{{ $totalsByDay[$date->format('Y-m-d')] ?? 0 }}h</div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rosterRows as $row)
                <tr>
                    <td class="roster-member-col">
                        <div class="roster-member">
                            <img class="roster-avatar" src="{{ $row['staff']->profile_picture ? asset(str_replace('\\', '/', $row['staff']->profile_picture)) : asset('design/sneat-admin-template/assets/img/avatars/1.png') }}">
                            <div class="flex-grow-1">
                                <div class="roster-member-name">{{ $row['staff']->name }}</div>
                                <div class="roster-member-hours">{{ $row['weekly_hours'] }}h &bull; {{ $row['staff']->branch === 'wakrah' ? 'Al Wakrah' : ($row['staff']->branch === 'old_airport' ? 'Old Airport' : 'Both') }}</div>
                            </div>
                            @moduleEdit('staff_management')
                                <button type="button" class="roster-edit-btn shift-edit-btn" data-staff-id="{{ $row['staff']->id }}" title="Edit shift schedule">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                            @endmoduleEdit
                        </div>
                    </td>
                    @foreach ($weekDays as $date)
                        @php $cell = $row['cells'][$date->format('Y-m-d')] ?? ['type' => 'off', 'blocks' => []]; @endphp
                        <td>
                            @if ($cell['type'] === 'working')
                                @foreach ($cell['blocks'] as $block)
                                    <span class="shift-pill">{{ $block['label'] }}</span>
                                @endforeach
                            @elseif ($cell['type'] === 'timeoff')
                                <span class="shift-pill timeoff {{ $cell['reason'] === 'sick' ? 'sick' : '' }}">
                                    {{ $cell['reason'] === 'sick' ? 'Sick' : ($cell['reason'] === 'unpaid' ? 'Unpaid' : ($cell['reason'] === 'other' ? 'Time off' : 'Leave')) }}
                                </span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No team members match this branch filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="laleen-contract-guardrail pt-2" style="border-top: 1px solid rgba(255, 255, 255, 0.06);">
    <span style="color: #6ee7b7;">✓ Unpaid leave logged here syncs automatically to monthly Payroll deductions</span>
    <span style="color: #fda4af;">✕ Roster hours control booking slots separately from salon opening hours</span>
</div>

@moduleEdit('staff_management')
    @include('shifts.config-modal')

    <script>
        window.SHIFT_CONFIGS = @json($configsMap);

        document.querySelectorAll('#so-subpane-shifts .shift-edit-btn').forEach(btn => {
            btn.addEventListener('click', () => openShiftModal(Number(btn.dataset.staffId)));
        });

        document.getElementById('addShiftBtn')?.addEventListener('click', () => openShiftModal(null));
    </script>
@endmoduleEdit
