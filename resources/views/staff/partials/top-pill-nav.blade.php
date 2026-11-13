@php
    $inPlaceTabs = $inPlaceTabs ?? false;
    $activePill = $activePill ?? 'directory';
    $ownerPendingBadge = (int) ($pendingOwnerActionCount ?? $pendingOwnerCount ?? 0);
@endphp

<style>
    .laleen-staff-pill-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        padding: 6px;
        margin-bottom: 20px;
        border-radius: 999px;
        background: linear-gradient(180deg, rgba(34, 22, 27, 0.92) 0%, rgba(21, 14, 18, 0.96) 100%);
        border: 1px solid rgba(229, 184, 168, 0.14);
        width: fit-content;
        max-width: 100%;
    }

    .laleen-staff-pill-bar .ui-chip.staff-nav-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 16px;
        border-radius: 999px;
        border: 1px solid transparent;
        background: transparent;
        color: #b8a29a;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.16s ease;
        line-height: 1.2;
    }

    .laleen-staff-pill-bar .ui-chip.staff-nav-pill:hover {
        color: #f8efe6;
        background: rgba(229, 184, 168, 0.08);
    }

    .laleen-staff-pill-bar .ui-chip.staff-nav-pill.active {
        background: linear-gradient(135deg, #d98f83, #c19a8c) !important;
        color: #1c110f !important;
        font-weight: 700;
        border-color: rgba(248, 239, 230, 0.22);
        box-shadow: 0 4px 14px rgba(217, 143, 131, 0.22);
    }

    .staff-pill-alert-dot {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: #7f1d1d;
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.22);
    }

    @media print {
        .laleen-staff-pill-bar { display: none !important; }
    }
</style>

<nav class="laleen-staff-pill-bar" aria-label="Staff Management Navigation">
    @if ($inPlaceTabs)
        <button type="button"
            class="staff-tab-btn ui-chip staff-nav-pill {{ $activePill === 'directory' ? 'active' : '' }}"
            data-tab="directory">
            Directory &amp; Profiles
        </button>

        <button type="button"
            class="staff-tab-btn ui-chip staff-nav-pill {{ $activePill === 'payroll' ? 'active' : '' }}"
            data-tab="payroll">
            Contracts &amp; Payroll
        </button>

        <button type="button"
            class="staff-tab-btn ui-chip staff-nav-pill {{ $activePill === 'shifts_ops' ? 'active' : '' }}"
            data-tab="shifts_ops">
            <span>Shifts &amp; Ops Actions</span>
            @if ($ownerPendingBadge > 0)
                <span class="staff-pill-alert-dot">{{ $ownerPendingBadge }} Pending Owner</span>
            @endif
        </button>

        <button type="button"
            class="staff-tab-btn ui-chip staff-nav-pill {{ $activePill === 'complaints' ? 'active' : '' }}"
            data-tab="complaints">
            HR &amp; Disciplinary
        </button>
    @else
        <a href="{{ route('staffs.index', ['tab' => 'directory']) }}"
            class="ui-chip staff-nav-pill {{ $activePill === 'directory' ? 'active' : '' }}">
            Directory &amp; Profiles
        </a>

        <a href="{{ route('staffs.index', ['tab' => 'contracts']) }}"
            class="ui-chip staff-nav-pill {{ $activePill === 'payroll' ? 'active' : '' }}">
            Contracts &amp; Payroll
        </a>

        <a href="{{ route('shifts.index') }}"
            class="ui-chip staff-nav-pill {{ $activePill === 'shifts_ops' ? 'active' : '' }}">
            <span>Shifts &amp; Ops Actions</span>
            @if ($ownerPendingBadge > 0)
                <span class="staff-pill-alert-dot">{{ $ownerPendingBadge }} Pending Owner</span>
            @endif
        </a>

        <a href="{{ route('staffs.index', ['tab' => 'complaints']) }}"
            class="ui-chip staff-nav-pill {{ $activePill === 'complaints' ? 'active' : '' }}">
            HR &amp; Disciplinary
        </a>
    @endif
</nav>
