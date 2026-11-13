@php
    $inPlaceSubTabs = $inPlaceSubTabs ?? false;
    $activeSub = $activeSub ?? 'shifts';
@endphp

<style>
    .so-subnav-bar {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        padding-bottom: 14px;
        margin-bottom: 18px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        width: 100%;
    }

    .so-sub-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 15px;
        border-radius: 999px;
        border: 1px solid rgba(229, 184, 168, 0.16);
        background: rgba(34, 22, 27, 0.72);
        color: #b8a29a;
        font-size: 12.5px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.16s ease;
        line-height: 1.25;
    }

    .so-sub-pill:hover {
        color: #f8efe6;
        border-color: rgba(229, 184, 168, 0.3);
        background: rgba(229, 184, 168, 0.1);
    }

    .so-sub-pill.active {
        background: linear-gradient(135deg, #d98f83, #c19a8c) !important;
        color: #1c110f !important;
        border-color: rgba(248, 239, 230, 0.24);
        font-weight: 700;
        box-shadow: 0 3px 12px rgba(217, 143, 131, 0.2);
    }

    @media print {
        .so-subnav-bar { display: none !important; }
    }
</style>

<div class="so-subnav-bar" role="tablist" aria-label="Shifts and Ops Actions Sub-Navigation">
    @if ($inPlaceSubTabs)
        <button type="button"
            class="ui-chip so-sub-pill {{ $activeSub === 'shifts' ? 'active' : '' }}"
            data-so-sub="shifts">
            ⏰ Scheduled Shifts
        </button>
        <button type="button"
            class="ui-chip so-sub-pill {{ $activeSub === 'ops_tracker' ? 'active' : '' }}"
            data-so-sub="ops_tracker">
            ⚡ Ops &amp; Action Tracker
        </button>
    @else
        <a href="{{ route('shifts.index') }}"
            class="ui-chip so-sub-pill {{ $activeSub === 'shifts' ? 'active' : '' }}">
            ⏰ Scheduled Shifts
        </a>
        <a href="{{ route('staff.ops-tracker.index') }}"
            class="ui-chip so-sub-pill {{ $activeSub === 'ops_tracker' ? 'active' : '' }}">
            ⚡ Ops &amp; Action Tracker
        </a>
    @endif
</div>
