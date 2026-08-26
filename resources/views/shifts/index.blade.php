@extends('layouts.app')
@section('title', 'Scheduled Shifts')

<style>
    .team-tabs .btn { border-radius: 999px; }

    .roster-card { overflow-x: auto; }

    .roster-table { border-collapse: separate; border-spacing: 0; min-width: 900px; width: 100%; }

    .roster-table th, .roster-table td {
        border-bottom: 1px solid rgba(213,180,169,0.16);
        border-right: 1px solid rgba(213,180,169,0.16);
        padding: 10px 12px;
        vertical-align: top;
    }

    .roster-table th:last-child, .roster-table td:last-child { border-right: none; }

    .roster-table thead th {
        background: rgba(213,180,169,0.06);
        text-align: left;
        min-width: 120px;
    }

    .roster-table thead th .day-name { font-size: 12.5px; font-weight: 700; color: #f5e0e0; }
    .roster-table thead th .day-total { font-size: 11px; color: #b6a49b; }

    .roster-member-col { min-width: 190px; position: sticky; left: 0; background: #241e1c; z-index: 1; }

    .roster-member { display: flex; align-items: center; gap: 8px; }
    .roster-avatar { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
    .roster-member-name { font-weight: 700; font-size: 13px; color: #f5e0e0; }
    .roster-member-hours { font-size: 11.5px; color: #b6a49b; }

    .roster-edit-btn {
        border: none; background: transparent; color: #b6a49b; padding: 4px;
    }
    .roster-edit-btn:hover { color: #b98ea3; }

    .shift-pill {
        display: block;
        background: rgba(213,180,169,0.1);
        color: #b98ea3;
        border-radius: 6px;
        font-size: 11.5px;
        font-weight: 600;
        padding: 5px 8px;
        margin-bottom: 4px;
        text-align: center;
        white-space: nowrap;
    }

    .shift-pill.timeoff { background: rgba(213,180,169,0.07); color: #b6a49b; }
    .shift-pill.timeoff.sick { background: rgba(168,82,74,0.14); color: #c07c73; }

    .roster-info-banner {
        background: rgba(213,180,169,0.1);
        color: #b98ea3;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 13px;
        margin-top: 16px;
    }

    /* Shift modal */
    #shiftModal .modal-dialog { max-width: 720px; }
    #shiftModal .nav-tabs .nav-link { color: #b6a49b; font-weight: 600; font-size: 13.5px; }
    #shiftModal .nav-tabs .nav-link.active { color: #b98ea3; border-color: rgba(213,180,169,0.16) rgba(213,180,169,0.16) #241e1c; }
    #shiftModal .shift-tab-pane { display: none; }
    #shiftModal .shift-tab-pane.active { display: block; }

    .shift-day-row { border: 1px solid rgba(213,180,169,0.16); border-radius: 10px; padding: 10px 14px; margin-bottom: 10px; }
    .shift-day-header { display: flex; justify-content: space-between; align-items: center; }
    .shift-blocks { margin-top: 8px; }
    .shift-blocks:empty { margin-top: 0; }
    .shift-block-row { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
    .shift-block-row input[type="time"] { max-width: 130px; }

    .timeoff-row {
        display: flex; justify-content: space-between; align-items: center;
        border: 1px solid rgba(213,180,169,0.16); border-radius: 8px; padding: 8px 12px; margin-bottom: 8px; font-size: 13px;
    }
    .timeoff-row .badge.on-leave { background: rgba(201,166,107,0.16); color: #c9a66b; }
    .timeoff-row .badge.sick { background: rgba(168,82,74,0.14); color: #c07c73; }
    .timeoff-row .badge.unpaid, .timeoff-row .badge.other { background: rgba(213,180,169,0.07); color: #cbb8b0; }
</style>

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Scheduled shifts</h4>
            <p class="text-muted small mb-0">Manage your team's recurring working hours and time off.</p>
        </div>
        @if (auth()->user()->role === 'admin')
            <button class="btn btn-dark" id="addShiftBtn"><i class="bx bx-plus me-1"></i> Add</button>
        @endif
    </div>

    <div class="team-tabs d-flex gap-2 mb-3">
        <a href="{{ route('staffs.index') }}" class="btn btn-sm btn-outline-secondary">Team members</a>
        <a href="{{ route('shifts.index') }}" class="btn btn-sm btn-dark">Scheduled shifts</a>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <form method="GET" action="{{ route('shifts.index') }}" id="branchFilterForm">
            <input type="hidden" name="week" value="{{ $weekStart->format('Y-m-d') }}">
            <select name="branch" class="form-select form-select-sm" onchange="document.getElementById('branchFilterForm').submit()">
                <option value="">All Locations</option>
                <option value="old_airport" {{ request('branch') == 'old_airport' ? 'selected' : '' }}>Old Airport</option>
                <option value="wakrah" {{ request('branch') == 'wakrah' ? 'selected' : '' }}>Wakrah</option>
                <option value="both" {{ request('branch') == 'both' ? 'selected' : '' }}>Both</option>
            </select>
        </form>

        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('shifts.index', ['week' => $weekStart->copy()->subWeek()->format('Y-m-d'), 'branch' => request('branch')]) }}">
                <i class="bx bx-chevron-left"></i>
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('shifts.index', ['branch' => request('branch')]) }}">This week</a>
            <span class="small fw-semibold">{{ $weekStart->format('j M') }} - {{ $weekEnd->format('j M, Y') }}</span>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('shifts.index', ['week' => $weekStart->copy()->addWeek()->format('Y-m-d'), 'branch' => request('branch')]) }}">
                <i class="bx bx-chevron-right"></i>
            </a>
        </div>
    </div>

    <div class="card roster-card">
        <table class="roster-table">
            <thead>
                <tr>
                    <th class="roster-member-col">Team member</th>
                    @foreach ($days as $date)
                        <th>
                            <div class="day-name">{{ $date->format('D, j M') }}</div>
                            <div class="day-total">{{ $dayTotals[$date->format('Y-m-d')] }}h</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($roster as $row)
                    <tr>
                        <td class="roster-member-col">
                            <div class="roster-member">
                                <img class="roster-avatar" src="{{ $row['staff']->profile_picture ? asset(str_replace('\\', '/', $row['staff']->profile_picture)) : asset('design/sneat-admin-template/assets/img/avatars/1.png') }}">
                                <div class="flex-grow-1">
                                    <div class="roster-member-name">{{ $row['staff']->name }}</div>
                                    <div class="roster-member-hours">{{ $row['weekly_hours'] }}h</div>
                                </div>
                                @if (auth()->user()->role === 'admin')
                                    <button type="button" class="roster-edit-btn shift-edit-btn" data-staff-id="{{ $row['staff']->id }}" title="Edit schedule">
                                        <i class="bx bx-edit-alt"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                        @foreach ($days as $date)
                            @php $cell = $row['cells'][$date->format('Y-m-d')]; @endphp
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
                        <td colspan="8" class="text-center text-muted py-4">No team members found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="roster-info-banner">
        <i class="bx bx-info-circle me-1"></i>
        The team roster shows availability for bookings and is not linked to your business opening hours.
    </div>

    @if (auth()->user()->role === 'admin')
        @include('shifts.config-modal')

        <script>
            window.SHIFT_CONFIGS = @json($shiftConfigs);

            document.querySelectorAll('.shift-edit-btn').forEach(btn => {
                btn.addEventListener('click', () => openShiftModal(Number(btn.dataset.staffId)));
            });

            document.getElementById('addShiftBtn').addEventListener('click', () => openShiftModal(null));
        </script>
    @endif
@endsection
