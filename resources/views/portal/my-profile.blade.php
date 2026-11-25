@php
    $isAdminPreview = (bool) ($isAdminPreview ?? false);
    $isLeaveEligible = (bool) ($isLeaveEligible ?? false);
    $layoutView = (!$isAdminPreview && ($isSalonStaff ?? false)) ? 'layouts.portal' : 'layouts.app';
    $makeTabUrl = $portalTabRoute ?? fn (string $tab, array $extra = []) => route('portal.profile', array_merge(['tab' => $tab], $extra));
@endphp

@extends($layoutView)

@section('title', $isAdminPreview ? ("Admin Preview — {$profile['name']}'s Portal Profile") : 'My Profile — Laleen Staff Portal')

@section('content')
<style>
    .mp-wrap {
        max-width: 1020px;
        margin: 0 auto;
    }

    .mp-card {
        background: linear-gradient(180deg, rgba(34, 22, 27, 0.94) 0%, rgba(21, 14, 18, 0.98) 100%);
        border: 1px solid rgba(229, 184, 168, 0.14);
        border-radius: 16px;
        padding: 20px 22px;
        color: #f8efe6;
    }

    .mp-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 18px;
    }

    .mp-hero-left {
        display: flex;
        align-items: center;
        gap: 16px;
        min-width: 0;
    }

    .mp-name {
        font-family: 'Playfair Display', serif;
        font-size: 22px;
        font-weight: 700;
        color: #f8efe6;
        line-height: 1.2;
        margin: 0;
    }

    .mp-badges {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 6px;
    }

    .mp-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        white-space: nowrap;
    }

    .mp-pill-role {
        background: rgba(212, 163, 115, 0.16);
        color: #f3d9a4;
        border: 1px solid rgba(212, 163, 115, 0.32);
    }

    .mp-pill-emerald {
        background: rgba(110, 231, 183, 0.12);
        color: #6ee7b7;
        border: 1px solid rgba(110, 231, 183, 0.28);
    }

    .mp-pill-amber {
        background: rgba(245, 158, 11, 0.14);
        color: #fbbf24;
        border: 1px solid rgba(245, 158, 11, 0.32);
    }

    .mp-pill-danger {
        background: rgba(253, 164, 175, 0.14);
        color: #fda4af;
        border: 1px solid rgba(253, 164, 175, 0.32);
    }

    .mp-pill-muted {
        background: rgba(255, 255, 255, 0.05);
        color: #cbb8b0;
        border: 1px solid rgba(255, 255, 255, 0.09);
    }

    .mp-contact-strip {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        padding-top: 14px;
        margin-top: 14px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        font-size: 12.5px;
        color: #cbb8b0;
    }

    .mp-contact-item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .mp-contact-item i {
        color: #d98f83;
        font-size: 15px;
    }

    .mp-tabs {
        display: flex;
        align-items: center;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 4px;
        margin-bottom: 20px;
        -webkit-overflow-scrolling: touch;
    }

    .mp-tab-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        border: 1px solid rgba(229, 184, 168, 0.16);
        background: rgba(34, 22, 27, 0.65);
        color: #b8a29a;
        transition: all 0.15s ease;
    }

    .mp-tab-link:hover {
        color: #f8efe6;
        border-color: rgba(229, 184, 168, 0.35);
    }

    .mp-tab-link.is-active {
        background: linear-gradient(135deg, #d98f83, #c19a8c);
        color: #1c110f;
        border-color: transparent;
    }

    .mp-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        padding-bottom: 12px;
        margin-bottom: 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .mp-section-title {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        font-size: 15px;
        font-weight: 700;
        color: #f8efe6;
        margin: 0;
    }

    .mp-icon-badge {
        width: 30px;
        height: 30px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(217, 143, 131, 0.14);
        color: #e5b8a8;
        font-size: 16px;
        flex-shrink: 0;
    }

    .mp-branch-name {
        font-family: 'Playfair Display', serif;
        font-size: 20px;
        font-weight: 700;
        color: #f8efe6;
        margin-bottom: 2px;
    }

    .mp-branch-sub {
        font-size: 12.5px;
        color: #b8a29a;
        margin-bottom: 14px;
    }

    .mp-shift-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding-top: 12px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .mp-shift-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        font-size: 13px;
    }

    .mp-shift-days { font-weight: 600; color: #e5b8a8; }
    .mp-shift-hours { color: #f8efe6; font-variant-numeric: tabular-nums; }

    .mp-skill-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .mp-skill-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 500;
        background: rgba(229, 184, 168, 0.08);
        border: 1px solid rgba(229, 184, 168, 0.18);
        color: #f8efe6;
    }

    .mp-skill-meta { font-size: 11px; color: #b8a29a; }

    .mp-off-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #b8a29a;
        margin-bottom: 8px;
    }

    .mp-list-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        font-size: 13px;
    }
    .mp-list-row:last-child { border-bottom: none; padding-bottom: 0; }

    .mp-kpi-bar-track {
        height: 6px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.07);
        overflow: hidden;
        margin-top: 5px;
    }

    .mp-kpi-bar-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #d98f83, #d4a373);
    }

    .mp-table {
        width: 100%;
        color: #f8efe6;
        margin-bottom: 0;
    }
    .mp-table thead th {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #b8a29a;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        padding: 8px 10px;
    }
    .mp-table tbody td {
        font-size: 13px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        padding: 10px;
        vertical-align: middle;
    }
    .mp-table tbody tr:last-child td { border-bottom: none; }

    .mp-stat-num {
        font-family: 'Playfair Display', serif;
        font-size: 26px;
        font-weight: 700;
        color: #f8efe6;
        line-height: 1.1;
    }

    .mp-btn-ghost {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 999px;
        border: 1px solid rgba(229, 184, 168, 0.25);
        background: rgba(255, 255, 255, 0.03);
        color: #f8efe6;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s ease;
    }

    .mp-btn-ghost:hover {
        background: rgba(229, 184, 168, 0.12);
        border-color: rgba(229, 184, 168, 0.45);
        color: #fff;
    }

    .mp-btn-rosegold {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 18px;
        border-radius: 999px;
        border: none;
        background: linear-gradient(135deg, #d98f83, #c19a8c);
        color: #1c110f;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
    }

    .mp-modal .modal-content {
        background: #1b1315 !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        border: 1px solid rgba(229, 184, 168, 0.22) !important;
        border-radius: 16px !important;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.72) !important;
        color: #f8efe6;
    }

    .mp-modal .modal-header { border-bottom: 1px solid rgba(255, 255, 255, 0.06); }
    .mp-modal .modal-footer { border-top: 1px solid rgba(255, 255, 255, 0.06); }
    .mp-modal .form-label { font-size: 12px; font-weight: 600; color: #d6c3bc; }
    .mp-modal .form-control,
    .mp-modal .form-select {
        background-color: #130d0f !important;
        border: 1px solid rgba(229, 184, 168, 0.22) !important;
        color: #f8efe6 !important;
        border-radius: 10px;
        color-scheme: dark;
    }
    .mp-modal .form-control:focus,
    .mp-modal .form-select:focus {
        border-color: #d98f83 !important;
        box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.18) !important;
    }
</style>

<div class="mp-wrap">
    @if ($isAdminPreview)
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 px-3 py-2"
             style="border-radius:14px; background:rgba(212,163,115,0.12); border:1px solid rgba(212,163,115,0.34);">
            <div class="d-flex align-items-center gap-2" style="font-size:13px; color:#f3d9a4; font-weight:600;">
                <i class="bx bx-show fs-5"></i>
                <span>Admin Preview &mdash; Viewing {{ $profile['name'] }}'s Portal Profile</span>
            </div>
            <a href="{{ route('users.index') }}" class="mp-btn-ghost py-1 px-3">
                <i class="bx bx-arrow-back"></i>
                <span>Back to Roles &amp; Access</span>
            </a>
        </div>
    @endif

    {{-- Profile Header Card --}}
    <div class="mp-card mb-3">
        <div class="mp-hero mb-0">
            <div class="mp-hero-left">
                @include('partials.avatar', [
                    'src' => $profile['avatar_raw'] ?? null,
                    'name' => $profile['name'],
                    'size' => 64,
                ])
                <div class="min-w-0">
                    <h1 class="mp-name text-truncate">{{ $profile['name'] }}</h1>
                    <div class="mp-badges">
                        <span class="mp-pill mp-pill-role">{{ $profile['role_badge'] }}</span>
                        <span class="mp-pill mp-pill-{{ $profile['status_tone'] }}">{{ $profile['status_label'] }}</span>
                        @if ($profile['employment_type'])
                            <span class="mp-pill mp-pill-muted">{{ $profile['employment_type'] }}</span>
                        @endif
                    </div>
                </div>
            </div>

            @if (!$isAdminPreview)
                <button type="button" class="mp-btn-ghost" data-bs-toggle="modal" data-bs-target="#portalChangePasswordModal">
                    <i class="bx bx-lock-alt"></i>
                    <span>Change Password</span>
                </button>
            @endif
        </div>

        <div class="mp-contact-strip">
            <span class="mp-contact-item">
                <i class="bx bx-envelope"></i>
                <span>{{ $profile['email'] }}</span>
            </span>
            @if ($profile['phone'])
                <span class="mp-contact-item">
                    <i class="bx bx-phone"></i>
                    <span>{{ $profile['phone'] }}</span>
                </span>
            @endif
            @if ($profile['start_date'])
                <span class="mp-contact-item">
                    <i class="bx bx-calendar-check"></i>
                    <span>Joined {{ $profile['start_date'] }}</span>
                </span>
            @endif
        </div>
    </div>

    {{-- Portal Navigation Tabs (Leaves strictly hidden for Remote / non-eligible roles) --}}
    <nav class="mp-tabs" role="tablist" aria-label="My Profile Sections">
        <a href="{{ $makeTabUrl('overview') }}"
            class="mp-tab-link {{ $activeTab === 'overview' ? 'is-active' : '' }}" role="tab">
            <i class="bx bx-user"></i>
            <span>Overview</span>
        </a>
        <a href="{{ $makeTabUrl('contracts') }}"
            class="mp-tab-link {{ $activeTab === 'contracts' ? 'is-active' : '' }}" role="tab">
            <i class="bx bx-file"></i>
            <span>Contracts</span>
        </a>
        <a href="{{ $makeTabUrl('documents') }}"
            class="mp-tab-link {{ $activeTab === 'documents' ? 'is-active' : '' }}" role="tab">
            <i class="bx bx-folder"></i>
            <span>Documents</span>
            @if (!empty($documentsPayload))
                <span class="badge rounded-pill" style="background:rgba(0,0,0,0.22); color:inherit; font-size:10.5px;">{{ count($documentsPayload) }}</span>
            @endif
        </a>
        @if ($isLeaveEligible)
            <a href="{{ $makeTabUrl('leaves') }}"
                class="mp-tab-link {{ $activeTab === 'leaves' ? 'is-active' : '' }}" role="tab">
                <i class="bx bx-calendar-event"></i>
                <span>Leaves</span>
            </a>
        @endif
    </nav>

    {{-- ===================================================================== --}}
    {{-- TAB 1: OVERVIEW (ROLE-TAILORED OPERATIONAL WIDGETS)                   --}}
    {{-- ===================================================================== --}}
    @if ($activeTab === 'overview')
        <div class="row g-3">
            {{-- Card 1: Location & Shift Hours (ALL Roles) --}}
            <div class="col-12 col-md-6">
                <div class="mp-card h-100">
                    <div class="mp-section-header">
                        <h2 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-map-pin"></i></span>
                            <span>{{ $branchInfo['is_remote'] ? 'Work Location' : 'Branch Location' }}</span>
                        </h2>
                        @if ($shiftSchedule['weekly_hours'] > 0)
                            <span class="mp-pill mp-pill-role">{{ $shiftSchedule['weekly_hours'] }}h / wk</span>
                        @endif
                    </div>

                    <div class="mp-branch-name">{{ $branchInfo['label'] }}</div>
                    <div class="mp-branch-sub">{{ $branchInfo['subtitle'] }}</div>

                    <div class="mp-shift-list">
                        <div class="mp-off-label mb-0">{{ $branchInfo['is_remote'] ? 'Working Hours' : 'Working Shift Hours' }}</div>
                        @if ($shiftSchedule['has_schedule'])
                            @foreach ($shiftSchedule['rows'] as $row)
                                <div class="mp-shift-row">
                                    <span class="mp-shift-days">{{ $row['days'] }}</span>
                                    <span class="mp-shift-hours">{{ $row['hours'] }}</span>
                                </div>
                            @endforeach
                        @else
                            <div class="small" style="color:#9e8780;">
                                {{ $branchInfo['is_remote'] ? 'Standard remote team schedule' : 'No weekly shift hours published yet.' }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Card 2: Salon Staff Skills Card OR Role-Specific KPI / Ops Tracker Card --}}
            @if ($isSalonStaff && $skillsPayload)
                <div class="col-12 col-md-6">
                    <div class="mp-card h-100">
                        <div class="mp-section-header">
                            <h2 class="mp-section-title">
                                <span class="mp-icon-badge"><i class="bx bx-spa"></i></span>
                                <span>{{ $skillsPayload['title'] }}</span>
                            </h2>
                            @if (!empty($skillsPayload['items']))
                                <span class="mp-pill mp-pill-muted">{{ count($skillsPayload['items']) }}</span>
                            @endif
                        </div>

                        @if (!empty($skillsPayload['items']))
                            <div class="mp-skill-grid">
                                @foreach ($skillsPayload['items'] as $skill)
                                    <span class="mp-skill-chip">
                                        <span>{{ $skill['name'] }}</span>
                                        @if (!empty($skill['duration']))
                                            <span class="mp-skill-meta">{{ $skill['duration'] }}m</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <div class="small" style="color:#9e8780;">No service skills assigned yet. Contact management to update your service list.</div>
                        @endif
                    </div>
                </div>
            @elseif ($roleKpiPayload)
                <div class="col-12 col-md-6">
                    <div class="mp-card h-100">
                        <div class="mp-section-header">
                            <h2 class="mp-section-title">
                                <span class="mp-icon-badge"><i class="bx {{ $roleKpiPayload['icon'] }}"></i></span>
                                <span>{{ $roleKpiPayload['title'] }}</span>
                            </h2>
                            @if (!empty($roleKpiPayload['action_url']) && !empty($roleKpiPayload['action_label']))
                                <a href="{{ $roleKpiPayload['action_url'] }}" class="mp-btn-ghost py-1 px-3" style="font-size:11.5px;">
                                    {{ $roleKpiPayload['action_label'] }} <i class="bx bx-right-arrow-alt"></i>
                                </a>
                            @endif
                        </div>
                        <div class="small mb-3" style="color:#9e8780;">{{ $roleKpiPayload['subtitle'] }}</div>

                        @if ($roleKpiPayload['mode'] === 'ops_tracker')
                            <div class="d-flex align-items-center gap-4 mb-3 pb-3" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                                <div>
                                    <div class="mp-off-label mb-1">Pending Items</div>
                                    <div class="mp-stat-num">{{ $roleKpiPayload['pending_count'] }}</div>
                                </div>
                                <div>
                                    <div class="mp-off-label mb-1">Overdue / Urgent</div>
                                    <div class="mp-stat-num" style="color:{{ $roleKpiPayload['overdue_urgent_count'] > 0 ? '#fda4af' : '#6ee7b7' }};">
                                        {{ $roleKpiPayload['overdue_urgent_count'] }}
                                    </div>
                                </div>
                            </div>
                            @if (!empty($roleKpiPayload['items']))
                                @foreach ($roleKpiPayload['items'] as $op)
                                    <div class="mp-list-row">
                                        <div class="min-w-0">
                                            <div class="fw-semibold" style="color:#f8efe6;">{{ $op['title'] }}</div>
                                            <div class="small" style="color:#9e8780;">
                                                {{ $op['scope'] }} &middot; Due {{ $op['due_label'] }}
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-1">
                                            @if ($op['is_overdue'])
                                                <span class="mp-pill mp-pill-danger">Overdue</span>
                                            @elseif ($op['priority'] === 'urgent')
                                                <span class="mp-pill mp-pill-amber">Urgent</span>
                                            @else
                                                <span class="mp-pill mp-pill-muted">Pending</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="small" style="color:#6ee7b7;">All Ops Tracker items are completed.</div>
                            @endif
                        @elseif (!empty($roleKpiPayload['metrics']))
                            <div class="d-flex flex-column gap-3">
                                @foreach ($roleKpiPayload['metrics'] as $m)
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center small">
                                            <span style="color:#cbb8b0;">{{ $m['label'] }}</span>
                                            <span class="fw-bold" style="color:#f8efe6;">{{ $m['value'] }}</span>
                                        </div>
                                        <div class="mp-kpi-bar-track">
                                            <div class="mp-kpi-bar-fill" style="width: {{ max(0, min(100, (float) $m['pct'])) }}%;"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="small" style="color:#9e8780;">{{ $roleKpiPayload['empty_message'] ?? 'KPIs will appear here once configured.' }}</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Card 3: Absentees Card (ALL Roles — Live from Attendance Log) --}}
            <div class="col-12 col-md-5">
                <div class="mp-card h-100">
                    <div class="mp-section-header">
                        <h2 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-user-x"></i></span>
                            <span>Absentees</span>
                        </h2>
                        <span class="small" style="color:#9e8780;">Attendance Log</span>
                    </div>

                    <div class="d-flex align-items-center gap-4 mb-3 pb-3" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                        <div>
                            <div class="mp-off-label mb-1">{{ $absenteesPayload['month_label'] }}</div>
                            <div class="mp-stat-num" style="color:{{ $absenteesPayload['month_count'] > 0 ? '#fda4af' : '#6ee7b7' }};">
                                {{ $absenteesPayload['month_count'] }}
                            </div>
                        </div>
                        <div>
                            <div class="mp-off-label mb-1">YTD ({{ $absenteesPayload['year_label'] }})</div>
                            <div class="mp-stat-num">{{ $absenteesPayload['ytd_count'] }}</div>
                        </div>
                    </div>

                    <div class="mp-off-label">Recent Absent Dates</div>
                    @if (!empty($absenteesPayload['recent_dates']))
                        @foreach ($absenteesPayload['recent_dates'] as $ab)
                            <div class="mp-list-row">
                                <span class="fw-semibold" style="color:#fda4af;">{{ $ab['date_label'] }}</span>
                                @if ($ab['notes'])
                                    <span class="small" style="color:#9e8780;">{{ $ab['notes'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <div class="small" style="color:#9e8780;">No absences recorded in {{ $absenteesPayload['year_label'] }}.</div>
                    @endif
                </div>
            </div>

            {{-- Card 4: Off Days (ALL Roles — Weekly Fixed + Next 30 Days in Asia/Qatar) --}}
            <div class="col-12 col-md-7">
                <div class="mp-card h-100">
                    <div class="mp-section-header">
                        <h2 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-calendar-x"></i></span>
                            <span>Off Days</span>
                        </h2>
                        <span class="small" style="color:#9e8780;">Qatar Time &middot; {{ $todayQatarLabel }}</span>
                    </div>

                    <div class="mb-3">
                        <div class="mp-off-label">Weekly Fixed Off Day(s)</div>
                        @if (!empty($offDaysPayload['weekly_off_days']))
                            <div class="mp-skill-grid">
                                @foreach ($offDaysPayload['weekly_off_days'] as $dayName)
                                    <span class="mp-pill mp-pill-role">{{ $dayName }}</span>
                                @endforeach
                            </div>
                        @else
                            <div class="small" style="color:#9e8780;">No fixed weekly off day set.</div>
                        @endif
                    </div>

                    @if (!empty($offDaysPayload['upcoming_weekly_dates']))
                        <div class="mb-3 pt-2" style="border-top:1px solid rgba(255,255,255,0.06);">
                            <div class="mp-off-label">Upcoming Weekly Offs (Next 30 Days)</div>
                            <div class="mp-skill-grid">
                                @foreach ($offDaysPayload['upcoming_weekly_dates'] as $uDate)
                                    <span class="mp-skill-chip">
                                        {{ $uDate['label'] }}
                                        @if ($uDate['is_today'])
                                            <span class="mp-skill-meta">(Today)</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="pt-2" style="border-top:1px solid rgba(255,255,255,0.06);">
                        <div class="mp-off-label">Approved Leaves &amp; Scheduled Time Off (Next 30 Days)</div>
                        @if (!empty($offDaysPayload['scheduled_time_offs']))
                            @foreach ($offDaysPayload['scheduled_time_offs'] as $to)
                                <div class="mp-list-row">
                                    <div>
                                        <div class="fw-semibold" style="color:#f8efe6;">{{ $to['date_range'] }}</div>
                                        @if ($to['notes'])
                                            <div class="small" style="color:#b8a29a;">{{ $to['notes'] }}</div>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="small" style="color:#9e8780;">{{ $to['days'] }} {{ $to['days'] === 1 ? 'day' : 'days' }}</span>
                                        <span class="mp-pill {{ $to['reason'] === 'sick' ? 'mp-pill-danger' : 'mp-pill-amber' }}">
                                            {{ $to['reason_label'] }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="small" style="color:#9e8780;">No additional scheduled time off in the next 30 days.</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- SALON MANAGER ONLY: Pending Complaints for Action --}}
            @if ($isSalonManager && $branchPendingComplaints !== null)
                <div class="col-12">
                    <div class="mp-card">
                        <div class="mp-section-header">
                            <h2 class="mp-section-title">
                                <span class="mp-icon-badge"><i class="bx bx-error-circle"></i></span>
                                <span>Pending Complaints for Action &mdash; {{ $branchPendingComplaints['branch_label'] }}</span>
                            </h2>
                            <span class="mp-pill {{ count($branchPendingComplaints['items']) > 0 ? 'mp-pill-amber' : 'mp-pill-emerald' }}">
                                {{ count($branchPendingComplaints['items']) }} Open
                            </span>
                        </div>

                        @if (!empty($branchPendingComplaints['items']))
                            <div class="table-responsive">
                                <table class="mp-table">
                                    <thead>
                                        <tr>
                                            <th>Ref ID</th>
                                            <th>Date</th>
                                            <th>Category</th>
                                            <th>Assigned Staff</th>
                                            <th>Status</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($branchPendingComplaints['items'] as $cmp)
                                            <tr>
                                                <td class="fw-semibold" style="color:#f3d9a4;">{{ $cmp['ref'] }}</td>
                                                <td>{{ $cmp['date_label'] }}</td>
                                                <td>{{ $cmp['category'] }}</td>
                                                <td>{{ $cmp['staff_names'] }}</td>
                                                <td><span class="mp-pill mp-pill-amber">{{ $cmp['status'] }}</span></td>
                                                <td class="text-end">
                                                    <a href="{{ $cmp['resolve_url'] }}" class="mp-btn-ghost py-1 px-3" style="font-size:11.5px;">
                                                        Open / Resolve
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="small" style="color:#6ee7b7;">No pending complaints in {{ $branchPendingComplaints['branch_label'] }}.</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- SALON STAFF ONLY: My Complaints (STRICT PRIVACY — zero client PII) --}}
            @if ($isSalonStaff && $myComplaints !== null)
                <div class="col-12">
                    <div class="mp-card">
                        <div class="mp-section-header">
                            <h2 class="mp-section-title">
                                <span class="mp-icon-badge"><i class="bx bx-message-square-error"></i></span>
                                <span>My Complaints</span>
                            </h2>
                            <span class="mp-pill mp-pill-muted">{{ count($myComplaints) }}</span>
                        </div>

                        @if (!empty($myComplaints))
                            <div class="table-responsive">
                                <table class="mp-table">
                                    <thead>
                                        <tr>
                                            <th>Ref ID</th>
                                            <th>Date</th>
                                            <th>Category</th>
                                            <th>Brief Summary</th>
                                            <th>Status</th>
                                            <th>Resolution / Outcome</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($myComplaints as $mc)
                                            <tr>
                                                <td class="fw-semibold text-nowrap" style="color:#f3d9a4;">{{ $mc['ref'] }}</td>
                                                <td class="text-nowrap">{{ $mc['date_label'] }}</td>
                                                <td>{{ $mc['category'] }}</td>
                                                <td style="max-width:260px; color:#cbb8b0;">{{ $mc['summary'] }}</td>
                                                <td>
                                                    <span class="mp-pill {{ $mc['status'] === 'Resolved' ? 'mp-pill-emerald' : 'mp-pill-amber' }}">
                                                        {{ $mc['status'] }}
                                                    </span>
                                                </td>
                                                <td style="color:#cbb8b0;">{{ $mc['outcome'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="small" style="color:#9e8780;">No complaints linked to your profile.</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- SALON STAFF ONLY: Weekly Evaluations (Read-Only) --}}
            @if ($isSalonStaff && $weeklyEvaluations !== null)
                <div class="col-12">
                    <div class="mp-card">
                        <div class="mp-section-header">
                            <h2 class="mp-section-title">
                                <span class="mp-icon-badge"><i class="bx bx-award"></i></span>
                                <span>Weekly Evaluations</span>
                            </h2>
                            <span class="mp-pill mp-pill-muted">{{ count($weeklyEvaluations) }}</span>
                        </div>

                        @if (!empty($weeklyEvaluations))
                            <div class="table-responsive">
                                <table class="mp-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Evaluator</th>
                                            <th>Score</th>
                                            <th>Manager Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($weeklyEvaluations as $ev)
                                            <tr>
                                                <td class="text-nowrap">{{ $ev['date_label'] }}</td>
                                                <td>{{ $ev['evaluator'] }}</td>
                                                <td>
                                                    <span class="mp-pill {{ (float) $ev['score'] >= 4.0 ? 'mp-pill-emerald' : ((float) $ev['score'] >= 3.0 ? 'mp-pill-amber' : 'mp-pill-danger') }}">
                                                        {{ $ev['score'] }} / 5.0
                                                    </span>
                                                </td>
                                                <td style="color:#cbb8b0;">{{ $ev['notes'] ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="small" style="color:#9e8780;">No weekly evaluations recorded yet.</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ALL ROLES: Deductions & Penalties (Read-Only, strictly own) --}}
            <div class="col-12">
                <div class="mp-card">
                    <div class="mp-section-header">
                        <h2 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-receipt"></i></span>
                            <span>Deductions &amp; Penalties</span>
                        </h2>
                        <span class="mp-pill {{ $deductionsPayload['month_total'] > 0 ? 'mp-pill-danger' : 'mp-pill-emerald' }}">
                            {{ $deductionsPayload['month_label'] }}: QAR {{ number_format($deductionsPayload['month_total'], 2) }}
                        </span>
                    </div>

                    @if (!empty($deductionsPayload['items']))
                        <div class="table-responsive">
                            <table class="mp-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount (QAR)</th>
                                        <th>Reason</th>
                                        <th>Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($deductionsPayload['items'] as $ded)
                                        <tr>
                                            <td class="text-nowrap">{{ $ded['date_label'] }}</td>
                                            <td class="fw-semibold text-nowrap" style="color:#fda4af;">QAR {{ number_format($ded['amount'], 2) }}</td>
                                            <td style="color:#cbb8b0;">{{ $ded['reason'] }}</td>
                                            <td>
                                                <span class="mp-pill {{ $ded['is_manual'] ? 'mp-pill-muted' : 'mp-pill-amber' }}">
                                                    {{ $ded['source'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="small" style="color:#9e8780;">No deductions or penalties recorded on your profile.</div>
                    @endif
                </div>
            </div>
        </div>

    {{-- ===================================================================== --}}
    {{-- TAB 2: CONTRACTS (STRICTLY OWN, READ-ONLY)                            --}}
    {{-- ===================================================================== --}}
    @elseif ($activeTab === 'contracts')
        <div class="d-flex flex-column gap-3">
            <div class="mp-card">
                <div class="mp-section-header">
                    <h2 class="mp-section-title">
                        <span class="mp-icon-badge"><i class="bx bx-file"></i></span>
                        <span>Active Employment Contract</span>
                    </h2>
                    @if ($contractsPayload['active_contract'])
                        <span class="mp-pill {{ $contractsPayload['active_contract']['status'] === 'active' ? 'mp-pill-emerald' : 'mp-pill-amber' }}">
                            {{ $contractsPayload['active_contract']['status_label'] }}
                        </span>
                    @endif
                </div>

                @if ($contractsPayload['active_contract'])
                    @php $ac = $contractsPayload['active_contract']; @endphp
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="mp-off-label mb-1">Job Title / Role</div>
                            <div class="fw-semibold">{{ $ac['role_title'] }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="mp-off-label mb-1">Contract Type</div>
                            <div>{{ $ac['contract_type'] }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="mp-off-label mb-1">Start Date</div>
                            <div>{{ $ac['start_date'] }}</div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="mp-off-label mb-1">End / Renewal Date</div>
                            <div>{{ $ac['expiry_date'] }}</div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 pt-2" style="border-top:1px solid rgba(255,255,255,0.06);">
                        <a href="{{ $ac['view_url'] }}" target="_blank" class="mp-btn-rosegold">
                            <i class="bx bx-show"></i> View Signed Contract
                        </a>
                        <a href="{{ $ac['download_url'] }}" class="mp-btn-ghost">
                            <i class="bx bx-download"></i> Download PDF
                        </a>
                    </div>
                @else
                    <div class="small" style="color:#9e8780;">No digital employment contract is linked to your profile yet.</div>
                @endif
            </div>

            @if (!empty($contractsPayload['signed_documents']))
                <div class="mp-card">
                    <div class="mp-section-header">
                        <h2 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-paperclip"></i></span>
                            <span>Signed Contract Attachments</span>
                        </h2>
                    </div>
                    @foreach ($contractsPayload['signed_documents'] as $sDoc)
                        <div class="mp-list-row">
                            <div>
                                <div class="fw-semibold">{{ $sDoc['title'] }}</div>
                                <div class="small" style="color:#9e8780;">
                                    Issued: {{ $sDoc['issue_date'] }} &middot; Renewal/Expiry: {{ $sDoc['expiry_date'] }}
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ $sDoc['view_url'] }}" target="_blank" class="mp-btn-ghost py-1 px-3" style="font-size:12px;">
                                    <i class="bx bx-show"></i> View
                                </a>
                                <a href="{{ $sDoc['download_url'] }}" class="mp-btn-ghost py-1 px-3" style="font-size:12px;">
                                    <i class="bx bx-download"></i> Download
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if (!empty($contractsPayload['prior_contracts']))
                <div class="mp-card">
                    <div class="mp-section-header">
                        <h2 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-history"></i></span>
                            <span>Prior Contract History</span>
                        </h2>
                    </div>
                    <div class="table-responsive">
                        <table class="mp-table">
                            <thead>
                                <tr>
                                    <th>Contract #</th>
                                    <th>Role</th>
                                    <th>Start &ndash; End</th>
                                    <th>Status</th>
                                    <th class="text-end">Document</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($contractsPayload['prior_contracts'] as $pc)
                                    <tr>
                                        <td class="fw-semibold">{{ $pc['contract_number'] }}</td>
                                        <td>{{ $pc['role_title'] }}</td>
                                        <td>{{ $pc['start_date'] }} &ndash; {{ $pc['expiry_date'] }}</td>
                                        <td><span class="mp-pill mp-pill-muted">{{ $pc['status_label'] }}</span></td>
                                        <td class="text-end">
                                            <a href="{{ $pc['download_url'] }}" class="mp-btn-ghost py-1 px-3" style="font-size:12px;">
                                                <i class="bx bx-download"></i> PDF
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

    {{-- ===================================================================== --}}
    {{-- TAB 3: DOCUMENTS (STRICTLY OWN PRIVATE STORAGE, READ-ONLY)            --}}
    {{-- ===================================================================== --}}
    @elseif ($activeTab === 'documents')
        <div class="mp-card">
            <div class="mp-section-header">
                <h2 class="mp-section-title">
                    <span class="mp-icon-badge"><i class="bx bx-folder"></i></span>
                    <span>Personal Documents</span>
                </h2>
                <span class="small" style="color:#9e8780;">Private HR Vault &middot; Read-only</span>
            </div>

            @if (!empty($documentsPayload))
                <div class="table-responsive">
                    <table class="mp-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Title &amp; Reference</th>
                                <th>Issue Date</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th class="text-end">File</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($documentsPayload as $doc)
                                <tr>
                                    <td><span class="mp-pill mp-pill-role">{{ $doc['category_label'] }}</span></td>
                                    <td>
                                        <div class="fw-semibold">{{ $doc['title'] }}</div>
                                        @if ($doc['document_number'])
                                            <div class="small" style="color:#9e8780;">No. {{ $doc['document_number'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-nowrap">{{ $doc['issue_date_label'] }}</td>
                                    <td class="text-nowrap">{{ $doc['expiry_date_label'] }}</td>
                                    <td>
                                        <span class="mp-pill mp-pill-{{ $doc['expiry_status']['tone'] }}">
                                            {{ $doc['expiry_status']['label'] }}
                                        </span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ $doc['view_url'] }}" target="_blank" class="mp-btn-ghost py-1 px-2 me-1" style="font-size:12px;">
                                            <i class="bx bx-show"></i> View
                                        </a>
                                        <a href="{{ $doc['download_url'] }}" class="mp-btn-ghost py-1 px-2" style="font-size:12px;">
                                            <i class="bx bx-download"></i> Download
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4 small" style="color:#9e8780;">
                    No personal documents (QID, Passport, Health Card, Offer Letter, etc.) have been uploaded by HR yet.
                </div>
            @endif
        </div>

    {{-- ===================================================================== --}}
    {{-- TAB 4: LEAVES (SALON MANAGER & SALON STAFF ONLY)                      --}}
    {{-- ===================================================================== --}}
    @elseif ($activeTab === 'leaves' && $isLeaveEligible && $leavesPayload)
        <div class="d-flex flex-column gap-3">
            {{-- Annual & Sick Leave Balance Cards --}}
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <div class="mp-card h-100">
                        <div class="mp-section-header">
                            <h2 class="mp-section-title">
                                <span class="mp-icon-badge"><i class="bx bx-sun"></i></span>
                                <span>Annual Leave ({{ $leavesPayload['year'] }})</span>
                            </h2>
                            <span class="mp-pill mp-pill-emerald">{{ $leavesPayload['annual']['remaining'] }} days left</span>
                        </div>
                        <div class="row text-center g-2">
                            <div class="col-3">
                                <div class="mp-off-label mb-1">Entitled</div>
                                <div class="fw-bold fs-5">{{ $leavesPayload['annual']['entitled'] }}</div>
                            </div>
                            <div class="col-3">
                                <div class="mp-off-label mb-1">Used</div>
                                <div class="fw-bold fs-5" style="color:#e5b8a8;">{{ $leavesPayload['annual']['used'] }}</div>
                            </div>
                            <div class="col-3">
                                <div class="mp-off-label mb-1">Pending</div>
                                <div class="fw-bold fs-5" style="color:#fbbf24;">{{ $leavesPayload['annual']['pending'] }}</div>
                            </div>
                            <div class="col-3">
                                <div class="mp-off-label mb-1">Remaining</div>
                                <div class="fw-bold fs-5" style="color:#6ee7b7;">{{ $leavesPayload['annual']['remaining'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="mp-card h-100">
                        <div class="mp-section-header">
                            <h2 class="mp-section-title">
                                <span class="mp-icon-badge"><i class="bx bx-plus-medical"></i></span>
                                <span>Sick Leave ({{ $leavesPayload['year'] }})</span>
                            </h2>
                            <span class="mp-pill mp-pill-emerald">{{ $leavesPayload['sick']['remaining'] }} days left</span>
                        </div>
                        <div class="row text-center g-2">
                            <div class="col-3">
                                <div class="mp-off-label mb-1">Entitled</div>
                                <div class="fw-bold fs-5">{{ $leavesPayload['sick']['entitled'] }}</div>
                            </div>
                            <div class="col-3">
                                <div class="mp-off-label mb-1">Used</div>
                                <div class="fw-bold fs-5" style="color:#fda4af;">{{ $leavesPayload['sick']['used'] }}</div>
                            </div>
                            <div class="col-3">
                                <div class="mp-off-label mb-1">Pending</div>
                                <div class="fw-bold fs-5" style="color:#fbbf24;">{{ $leavesPayload['sick']['pending'] }}</div>
                            </div>
                            <div class="col-3">
                                <div class="mp-off-label mb-1">Remaining</div>
                                <div class="fw-bold fs-5" style="color:#6ee7b7;">{{ $leavesPayload['sick']['remaining'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Leave History & + Request Leave CTA --}}
            <div class="mp-card">
                <div class="mp-section-header">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h2 class="mp-section-title me-2">
                            <span class="mp-icon-badge"><i class="bx bx-calendar-event"></i></span>
                            <span>Leave History</span>
                        </h2>
                        @foreach (['all' => 'All', 'annual' => 'Annual Leaves', 'sick' => 'Sick Leaves'] as $fKey => $fLabel)
                            <a href="{{ $makeTabUrl('leaves', ['leave_type' => $fKey]) }}"
                               class="mp-pill text-decoration-none {{ $leavesPayload['filter'] === $fKey ? 'mp-pill-role' : 'mp-pill-muted' }}">
                                {{ $fLabel }}
                            </a>
                        @endforeach
                    </div>
                    @if (!$isAdminPreview)
                        <button type="button" class="mp-btn-rosegold" data-bs-toggle="modal" data-bs-target="#portalRequestLeaveModal">
                            <i class="bx bx-plus"></i> Request Leave
                        </button>
                    @endif
                </div>

                @if (!empty($leavesPayload['history']))
                    <div class="table-responsive">
                        <table class="mp-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Dates</th>
                                    <th>Days</th>
                                    <th>Reason</th>
                                    <th>Certificate</th>
                                    <th>Status</th>
                                    <th>Admin Notes</th>
                                    @if (!$isAdminPreview)
                                        <th class="text-end">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($leavesPayload['history'] as $lv)
                                    <tr>
                                        <td>
                                            <span class="mp-pill {{ $lv['leave_type'] === 'sick' ? 'mp-pill-danger' : 'mp-pill-role' }}">
                                                {{ $lv['type_label'] }}
                                            </span>
                                        </td>
                                        <td class="text-nowrap">{{ $lv['date_range'] }}</td>
                                        <td>{{ $lv['total_days'] }}</td>
                                        <td style="color:#cbb8b0;">{{ $lv['reason'] ?: '—' }}</td>
                                        <td>
                                            @if ($lv['has_certificate'])
                                                <a href="{{ $lv['certificate_url'] }}" target="_blank" class="mp-btn-ghost py-1 px-2" style="font-size:11.5px;">
                                                    <i class="bx bx-file"></i> View
                                                </a>
                                            @else
                                                <span style="color:#8f7a73;">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $lvTone = match ($lv['status']) {
                                                    'approved' => 'emerald',
                                                    'pending' => 'amber',
                                                    'rejected' => 'danger',
                                                    default => 'muted',
                                                };
                                            @endphp
                                            <span class="mp-pill mp-pill-{{ $lvTone }}">{{ $lv['status_label'] }}</span>
                                        </td>
                                        <td class="small" style="color:#cbb8b0;">{{ $lv['admin_notes'] ?: '—' }}</td>
                                        @if (!$isAdminPreview)
                                            <td class="text-end">
                                                @if ($lv['can_cancel'])
                                                    <form method="POST" action="{{ $lv['cancel_url'] }}" class="m-0" onsubmit="return confirm('Cancel this pending leave request?');">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="mp-btn-ghost py-1 px-2" style="font-size:11.5px; color:#fda4af; border-color:rgba(253,164,175,0.34);">
                                                            Cancel
                                                        </button>
                                                    </form>
                                                @else
                                                    <span style="color:#8f7a73;">—</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 small" style="color:#9e8780;">No leave requests found for this filter.</div>
                @endif
            </div>
        </div>
    @endif
</div>

@if (!$isAdminPreview)
{{-- Change Password Modal --}}
<div class="modal fade mp-modal" id="portalChangePasswordModal" tabindex="-1" aria-labelledby="portalChangePasswordTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold mb-0" id="portalChangePasswordTitle">Change Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('portal.password.update') }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    @if ($errors->portalPassword->any())
                        <div class="alert alert-danger py-2 px-3 small mb-3">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->portalPassword->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label" for="mpCurrentPassword">Current Password *</label>
                        <input type="password" id="mpCurrentPassword" name="current_password" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="mpNewPassword">New Password *</label>
                        <input type="password" id="mpNewPassword" name="password" class="form-control" required minlength="8" maxlength="128" placeholder="Minimum 8 characters" autocomplete="new-password">
                    </div>
                    <div class="mb-1">
                        <label class="form-label" for="mpConfirmPassword">Confirm New Password *</label>
                        <input type="password" id="mpConfirmPassword" name="password_confirmation" class="form-control" required minlength="8" maxlength="128" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="mp-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="mp-btn-rosegold">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($isLeaveEligible)
{{-- Request Leave Modal --}}
<div class="modal fade mp-modal" id="portalRequestLeaveModal" tabindex="-1" aria-labelledby="portalRequestLeaveTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="portalRequestLeaveTitle">Request Leave</h5>
                    <div class="small" style="color:#b8a29a;">Submitted for Admin review. Approved dates automatically block calendar bookings.</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('portal.leaves.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="plLeaveType">Leave Type *</label>
                        <select id="plLeaveType" name="leave_type" class="form-select" required>
                            <option value="annual">Annual Leave</option>
                            <option value="sick">Sick Leave</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-5">
                            <label class="form-label" for="plStartDate">Start Date *</label>
                            <input type="date" id="plStartDate" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="plEndDate">End Date *</label>
                            <input type="date" id="plEndDate" name="end_date" class="form-control" required>
                        </div>
                        <div class="col-md-2 d-flex flex-column justify-content-end">
                            <div class="small text-center py-2" id="plTotalDaysBadge" style="border-radius:10px; background:rgba(212,163,115,0.14); color:#f3d9a4; font-weight:700;">
                                0d
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="plReason">Reason</label>
                        <textarea id="plReason" name="reason" class="form-control" rows="3" maxlength="1000" placeholder="Optional note for management"></textarea>
                    </div>
                    <div class="mb-1 d-none" id="plMedCertWrap">
                        <label class="form-label" for="plMedCert">Medical Certificate (optional, PDF/Image max 10 MB)</label>
                        <input type="file" id="plMedCert" name="medical_certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="mp-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="mp-btn-rosegold">Submit Leave Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endif
@endsection

@section('scripts')
@if (!$isAdminPreview)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if ($errors->portalPassword->any())
            const pwEl = document.getElementById('portalChangePasswordModal');
            if (pwEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(pwEl).show();
        @endif

        const typeSel = document.getElementById('plLeaveType');
        const certWrap = document.getElementById('plMedCertWrap');
        const startEl = document.getElementById('plStartDate');
        const endEl = document.getElementById('plEndDate');
        const badgeEl = document.getElementById('plTotalDaysBadge');

        function syncLeaveForm() {
            if (typeSel && certWrap) {
                certWrap.classList.toggle('d-none', typeSel.value !== 'sick');
            }
            if (startEl && endEl && badgeEl) {
                if (startEl.value && endEl.value) {
                    const s = new Date(startEl.value + 'T00:00:00');
                    const e = new Date(endEl.value + 'T00:00:00');
                    const diff = Math.floor((e - s) / 86400000) + 1;
                    badgeEl.textContent = diff > 0 ? (diff + (diff === 1 ? ' day' : ' days')) : 'Invalid';
                } else {
                    badgeEl.textContent = '0d';
                }
            }
        }

        typeSel?.addEventListener('change', syncLeaveForm);
        startEl?.addEventListener('change', function () {
            if (endEl && (!endEl.value || endEl.value < startEl.value)) {
                endEl.value = startEl.value;
            }
            syncLeaveForm();
        });
        endEl?.addEventListener('change', syncLeaveForm);
        syncLeaveForm();
    });
</script>
@endif
@endsection
