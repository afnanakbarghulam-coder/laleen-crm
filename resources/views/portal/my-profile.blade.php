@php
    $isAdminPreview = (bool) ($isAdminPreview ?? false);
    $isLeaveEligible = (bool) ($isLeaveEligible ?? false);
    $layoutView = (!$isAdminPreview && ($isSalonStaff ?? false)) ? 'layouts.portal' : 'layouts.app';
    $makeTabUrl = $portalTabRoute ?? fn (string $tab, array $extra = []) => route('portal.profile', array_merge(['tab' => $tab], $extra));

    $dedMonthTotal = (float) ($deductionsPayload['month_total'] ?? 0);
    $dedDecimals = abs($dedMonthTotal - round($dedMonthTotal)) < 0.005 ? 0 : 2;
    $dedFormattedSummary = $dedMonthTotal > 0
        ? ('-' . number_format($dedMonthTotal, $dedDecimals) . ' QAR')
        : '0 QAR';
    $dedItemsCount = count($deductionsPayload['items'] ?? []);
@endphp

@extends($layoutView)

@section('title', $isAdminPreview ? ("Admin Preview — {$profile['name']}'s Portal Profile") : 'My Profile — Laleen Staff Portal')

@section('content')
@include('kpi.partials.scorecard-styles')
<style>
    .mp-wrap {
        max-width: 1080px;
        margin: 0 auto;
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .mp-num {
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
        font-variant-numeric: tabular-nums;
    }

    .mp-card {
        background: linear-gradient(180deg, rgba(33, 21, 26, 0.94) 0%, rgba(21, 14, 18, 0.98) 100%);
        border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: 16px;
        padding: 20px 22px;
        color: #f8efe6;
        position: relative;
    }

    /* Compact Single-Row Profile Header */
    .mp-header-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
        padding: 15px 20px;
        margin-bottom: 16px;
    }

    .mp-header-left {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
        flex: 1 1 320px;
    }

    .mp-name {
        font-family: 'Playfair Display', serif;
        font-size: 20px;
        font-weight: 700;
        color: #f8efe6;
        line-height: 1.2;
        margin: 0;
    }

    .mp-meta-line {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px 8px;
        margin-top: 5px;
        font-size: 12.5px;
        color: #b8a29a;
    }

    .mp-meta-sep {
        color: rgba(255, 255, 255, 0.22);
        user-select: none;
    }

    .mp-meta-text {
        color: #cbb8b0;
        font-weight: 500;
    }

    .mp-meta-email {
        color: #9e8780;
        word-break: break-all;
    }

    .mp-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 2.5px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
        line-height: 1.4;
    }

    .mp-pill-role {
        background: rgba(212, 163, 115, 0.14);
        color: #f3d9a4;
        border: 1px solid rgba(212, 163, 115, 0.30);
    }

    .mp-pill-emerald {
        background: rgba(52, 211, 153, 0.12);
        color: #6ee7b7;
        border: 1px solid rgba(52, 211, 153, 0.28);
    }

    .mp-pill-amber {
        background: rgba(245, 158, 11, 0.14);
        color: #fbbf24;
        border: 1px solid rgba(245, 158, 11, 0.30);
    }

    .mp-pill-danger {
        background: rgba(251, 113, 133, 0.14);
        color: #fda4af;
        border: 1px solid rgba(251, 113, 133, 0.30);
    }

    .mp-pill-muted {
        background: rgba(255, 255, 255, 0.045);
        color: #cbb8b0;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    /* Tabs */
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
        padding: 7px 15px;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(29, 18, 22, 0.7);
        color: #b8a29a;
        transition: all 0.15s ease;
    }

    .mp-tab-link:hover {
        color: #f8efe6;
        border-color: rgba(229, 184, 168, 0.32);
    }

    .mp-tab-link.is-active {
        background: linear-gradient(135deg, #d98f83, #c19a8c);
        color: #1c110f;
        border-color: transparent;
    }

    /* Row 1: 4-Column Summary Strip (Uniform height, sans-serif tabular-nums) */
    .mp-stat-card {
        background: linear-gradient(180deg, rgba(33, 21, 26, 0.94) 0%, rgba(21, 14, 18, 0.98) 100%);
        border: 1px solid rgba(255, 255, 255, 0.07);
        border-radius: 16px;
        padding: 18px 18px 16px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 132px;
        height: 100%;
    }

    .mp-stat-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 12px;
    }

    .mp-stat-label-wrap {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    .mp-stat-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(229, 184, 168, 0.10);
        border: 1px solid rgba(229, 184, 168, 0.18);
        color: #e5b8a8;
        font-size: 14px;
        flex-shrink: 0;
    }

    .mp-stat-label {
        font-size: 11.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #a8938b;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mp-stat-value {
        font-family: 'Poppins', sans-serif;
        font-variant-numeric: tabular-nums;
        font-size: 23px;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: #f8efe6;
        line-height: 1.15;
        margin-bottom: 6px;
    }

    .mp-stat-sub {
        font-size: 12px;
        color: #9e8780;
        line-height: 1.35;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .mp-eye-btn {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.09);
        background: rgba(255, 255, 255, 0.03);
        color: #cbb8b0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        flex-shrink: 0;
        transition: all 0.15s ease;
    }

    .mp-eye-btn:hover {
        background: rgba(229, 184, 168, 0.12);
        border-color: rgba(229, 184, 168, 0.35);
        color: #f8efe6;
    }

    /* Section Header & Clean Hairline Blocks */
    .mp-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        padding-bottom: 12px;
        margin-bottom: 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    .mp-section-title {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        font-size: 14.5px;
        font-weight: 600;
        color: #f8efe6;
        margin: 0;
    }

    .mp-icon-badge {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(229, 184, 168, 0.10);
        border: 1px solid rgba(229, 184, 168, 0.18);
        color: #e5b8a8;
        font-size: 15px;
        flex-shrink: 0;
    }

    .mp-sub-heading {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #9e8780;
        margin-bottom: 8px;
    }

    .mp-zone {
        padding-top: 14px;
        margin-top: 14px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .mp-skill-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
    }

    .mp-skill-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 11px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 500;
        background: rgba(229, 184, 168, 0.07);
        border: 1px solid rgba(229, 184, 168, 0.16);
        color: #f8efe6;
    }

    .mp-skill-meta { font-size: 11px; color: #a8938b; }

    .mp-list-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px 12px;
        padding: 9px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        font-size: 12.5px;
    }
    .mp-list-row:first-child { padding-top: 2px; }
    .mp-list-row:last-child { border-bottom: none; padding-bottom: 0; }

    /* Tables */
    .mp-table {
        width: 100%;
        color: #f8efe6;
        margin-bottom: 0;
    }
    .mp-table thead th {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #9e8780;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        padding: 8px 10px;
    }
    .mp-table tbody td {
        font-size: 13px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        padding: 10px;
        vertical-align: middle;
    }
    .mp-table tbody tr:last-child td { border-bottom: none; }

    /* Buttons & Modals */
    .mp-btn-ghost {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 13px;
        border-radius: 999px;
        border: 1px solid rgba(229, 184, 168, 0.22);
        background: rgba(255, 255, 255, 0.03);
        color: #f8efe6;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s ease;
        white-space: nowrap;
    }

    .mp-btn-ghost:hover {
        background: rgba(229, 184, 168, 0.12);
        border-color: rgba(229, 184, 168, 0.42);
        color: #fff;
    }

    .mp-btn-rosegold {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        border-radius: 999px;
        border: none;
        background: linear-gradient(135deg, #d98f83, #c19a8c);
        color: #1c110f;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        white-space: nowrap;
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
             style="border-radius:14px; background:rgba(212,163,115,0.11); border:1px solid rgba(212,163,115,0.32);">
            <div class="d-flex align-items-center gap-2" style="font-size:12.5px; color:#f3d9a4; font-weight:600;">
                <i class="bx bx-show fs-5"></i>
                <span>Admin Preview &mdash; Viewing {{ $profile['name'] }}'s Portal Profile</span>
            </div>
            <a href="{{ route('users.index') }}" class="mp-btn-ghost py-1 px-3">
                <i class="bx bx-arrow-back"></i>
                <span>Back to Roles &amp; Access</span>
            </a>
        </div>
    @endif

    {{-- Compressed Single-Row Luxury Dark Profile Header --}}
    <div class="mp-card mp-header-bar">
        <div class="mp-header-left">
            @include('partials.avatar', [
                'src' => $profile['avatar_raw'] ?? null,
                'name' => $profile['name'],
                'size' => 50,
            ])
            <div class="min-w-0">
                <h1 class="mp-name text-truncate">{{ $profile['name'] }}</h1>
                <div class="mp-meta-line">
                    <span class="mp-pill mp-pill-role">{{ $profile['role_badge'] }}</span>
                    <span class="mp-meta-sep" aria-hidden="true">&middot;</span>
                    <span class="mp-meta-text">{{ $branchInfo['label'] }}</span>
                    <span class="mp-meta-sep" aria-hidden="true">&middot;</span>
                    <span class="mp-pill mp-pill-{{ $profile['status_tone'] }}">{{ $profile['status_label'] }}</span>
                    @if ($profile['employment_type'])
                        <span class="mp-meta-sep" aria-hidden="true">&middot;</span>
                        <span class="mp-meta-text">{{ $profile['employment_type'] }}</span>
                    @endif
                    @if ($profile['email'])
                        <span class="mp-meta-sep" aria-hidden="true">&middot;</span>
                        <span class="mp-meta-email">{{ $profile['email'] }}</span>
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

    {{-- Navigation Tabs: Overview | Performance | Contracts | Documents | Leaves (branch eligible only) --}}
    <nav class="mp-tabs" role="tablist" aria-label="My Profile Sections">
        <a href="{{ $makeTabUrl('overview') }}"
           class="mp-tab-link {{ $activeTab === 'overview' ? 'is-active' : '' }}" role="tab">
            <i class="bx bx-user"></i>
            <span>Overview</span>
        </a>
        <a href="{{ $makeTabUrl('performance') }}"
           class="mp-tab-link {{ $activeTab === 'performance' ? 'is-active' : '' }}" role="tab">
            <i class="bx bx-bar-chart-alt-2"></i>
            <span>Performance</span>
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
                <span class="badge rounded-pill mp-num" style="background:rgba(0,0,0,0.22); color:inherit; font-size:10.5px;">{{ count($documentsPayload) }}</span>
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
    {{-- TAB 1: OVERVIEW (BALANCED 4-COL SUMMARY STRIP + 6/6 DETAILS GRID)     --}}
    {{-- ===================================================================== --}}
    @if ($activeTab === 'overview')
        {{-- Row 1: 4-Column Uniform Summary Strip --}}
        <div class="row g-3 mb-3">
            {{-- 1. Work / Branch Location --}}
            <div class="col-6 col-lg-3">
                <div class="mp-stat-card">
                    <div class="mp-stat-head">
                        <div class="mp-stat-label-wrap">
                            <span class="mp-stat-icon"><i class="bx bx-map-pin"></i></span>
                            <span class="mp-stat-label">{{ $branchInfo['is_remote'] ? 'Work Location' : 'Branch Location' }}</span>
                        </div>
                    </div>
                    <div>
                        <div class="mp-stat-value" style="font-size:21px;">{{ $branchInfo['label'] }}</div>
                        <div class="mp-stat-sub" title="{{ $branchInfo['compact_subtitle'] }}">{{ $branchInfo['compact_subtitle'] }}</div>
                    </div>
                </div>
            </div>

            {{-- 2. Monthly Salary (Own Base Salary + Show/Hide Eye Toggle) --}}
            <div class="col-6 col-lg-3">
                <div class="mp-stat-card">
                    <div class="mp-stat-head">
                        <div class="mp-stat-label-wrap">
                            <span class="mp-stat-icon"><i class="bx bx-wallet"></i></span>
                            <span class="mp-stat-label">Monthly Salary</span>
                        </div>
                        @if ($salaryPayload['has_salary'])
                            <button type="button"
                                    id="mpSalaryToggleBtn"
                                    class="mp-eye-btn"
                                    aria-label="Hide monthly salary"
                                    aria-pressed="false"
                                    title="Show / Hide salary">
                                <i class="bx bx-show" id="mpSalaryToggleIcon"></i>
                            </button>
                        @endif
                    </div>
                    <div>
                        <div class="mp-stat-value" style="{{ $salaryPayload['has_salary'] ? '' : 'color:#9e8780; font-size:20px;' }}">
                            <span id="mpSalaryValue"
                                  data-salary-real="{{ $salaryPayload['formatted'] }}"
                                  data-salary-masked="••••••">{{ $salaryPayload['formatted'] }}</span>
                        </div>
                        <div class="mp-stat-sub">{{ $salaryPayload['source_label'] }}</div>
                    </div>
                </div>
            </div>

            {{-- 3. Absentees --}}
            <div class="col-6 col-lg-3">
                <div class="mp-stat-card">
                    <div class="mp-stat-head">
                        <div class="mp-stat-label-wrap">
                            <span class="mp-stat-icon"><i class="bx bx-user-x"></i></span>
                            <span class="mp-stat-label">Absentees</span>
                        </div>
                    </div>
                    <div>
                        <div class="mp-stat-value" style="color:{{ $absenteesPayload['month_count'] > 0 ? '#fda4af' : '#6ee7b7' }};">
                            {{ $absenteesPayload['month_count'] }}
                        </div>
                        <div class="mp-stat-sub mp-num">
                            {{ $absenteesPayload['month_label'] }} &middot; YTD: {{ $absenteesPayload['ytd_count'] }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. MTD Deductions --}}
            <div class="col-6 col-lg-3">
                <div class="mp-stat-card">
                    <div class="mp-stat-head">
                        <div class="mp-stat-label-wrap">
                            <span class="mp-stat-icon"><i class="bx bx-receipt"></i></span>
                            <span class="mp-stat-label">MTD Deductions</span>
                        </div>
                    </div>
                    <div>
                        <div class="mp-stat-value" style="color:{{ $dedMonthTotal > 0 ? '#fda4af' : '#f8efe6' }};">
                            {{ $dedFormattedSummary }}
                        </div>
                        <div class="mp-stat-sub">
                            {{ $deductionsPayload['month_label'] }} &middot; {{ $dedItemsCount > 0 ? ($dedItemsCount . ' recorded') : 'Clean record' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 2: Balanced Equal 6 / 6 Details Grid --}}
        <div class="row g-3 align-items-stretch">
            {{-- Left Card (6 cols): Off Days & Attendance --}}
            <div class="col-12 col-lg-6">
                <div class="mp-card h-100">
                    <div class="mp-section-header">
                        <h2 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-calendar-x"></i></span>
                            <span>Off Days &amp; Attendance</span>
                        </h2>
                        <span class="small" style="color:#9e8780;">Qatar Time &middot; {{ $todayQatarLabel }}</span>
                    </div>

                    {{-- Weekly Fixed Off Days --}}
                    <div>
                        <div class="mp-sub-heading">Weekly Fixed Off Day(s)</div>
                        @if (!empty($offDaysPayload['weekly_off_days']))
                            <div class="mp-skill-grid">
                                @foreach ($offDaysPayload['weekly_off_days'] as $dayName)
                                    <span class="mp-pill mp-pill-role">{{ $dayName }}</span>
                                @endforeach
                                @foreach (array_slice($offDaysPayload['upcoming_weekly_dates'], 0, 4) as $uDate)
                                    <span class="mp-skill-chip mp-num">
                                        {{ $uDate['label'] }}
                                        @if ($uDate['is_today'])
                                            <span class="mp-skill-meta">(Today)</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <div class="small" style="color:#9e8780;">No fixed weekly off day set.</div>
                        @endif
                    </div>

                    {{-- Published Shift Hours (only if schedule exists) --}}
                    @if ($shiftSchedule['has_schedule'])
                        <div class="mp-zone">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="mp-sub-heading mb-0">Working Shift Hours</div>
                                @if ($shiftSchedule['weekly_hours'] > 0)
                                    <span class="small mp-num" style="color:#e5b8a8; font-weight:600;">{{ $shiftSchedule['weekly_hours'] }}h / wk</span>
                                @endif
                            </div>
                            @foreach ($shiftSchedule['rows'] as $row)
                                <div class="mp-list-row">
                                    <span style="font-weight:600; color:#e5b8a8;">{{ $row['days'] }}</span>
                                    <span class="mp-num" style="color:#f8efe6;">{{ $row['hours'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Scheduled Time Off & Approved Leaves (Next 30 Days) --}}
                    <div class="mp-zone">
                        <div class="mp-sub-heading">Scheduled Time Off &amp; Approved Leaves (Next 30 Days)</div>
                        @if (!empty($offDaysPayload['scheduled_time_offs']))
                            @foreach ($offDaysPayload['scheduled_time_offs'] as $to)
                                <div class="mp-list-row">
                                    <div>
                                        <div class="fw-semibold mp-num" style="color:#f8efe6;">{{ $to['date_range'] }}</div>
                                        @if ($to['notes'])
                                            <div class="small" style="color:#9e8780;">{{ $to['notes'] }}</div>
                                        @endif
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="small mp-num" style="color:#9e8780;">{{ $to['days'] }} {{ $to['days'] === 1 ? 'day' : 'days' }}</span>
                                        <span class="mp-pill {{ $to['reason'] === 'sick' ? 'mp-pill-danger' : 'mp-pill-amber' }}">
                                            {{ $to['reason_label'] }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="small" style="color:#9e8780;">No scheduled time off in the next 30 days.</div>
                        @endif
                    </div>

                    {{-- Recent Absent Dates --}}
                    <div class="mp-zone">
                        <div class="mp-sub-heading">Recent Absent Dates ({{ $absenteesPayload['year_label'] }})</div>
                        @if (!empty($absenteesPayload['recent_dates']))
                            @foreach ($absenteesPayload['recent_dates'] as $ab)
                                <div class="mp-list-row">
                                    <span class="fw-semibold mp-num" style="color:#fda4af;">{{ $ab['date_label'] }}</span>
                                    @if ($ab['notes'])
                                        <span class="small" style="color:#a8938b;">{{ $ab['notes'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <div class="small" style="color:#9e8780;">No absences recorded in {{ $absenteesPayload['year_label'] }}.</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Right Card (6 cols): Role Essentials (Skills for Salon Staff) & Recent Deductions --}}
            <div class="col-12 col-lg-6">
                <div class="mp-card h-100">
                    @if ($isSalonStaff && $skillsPayload)
                        <div class="mp-section-header">
                            <h2 class="mp-section-title">
                                <span class="mp-icon-badge"><i class="bx bx-spa"></i></span>
                                <span>{{ $skillsPayload['title'] }}</span>
                            </h2>
                            @if (!empty($skillsPayload['items']))
                                <span class="mp-pill mp-pill-muted mp-num">{{ count($skillsPayload['items']) }}</span>
                            @endif
                        </div>

                        @if (!empty($skillsPayload['items']))
                            <div class="mp-skill-grid">
                                @foreach ($skillsPayload['items'] as $skill)
                                    <span class="mp-skill-chip">
                                        <span>{{ $skill['name'] }}</span>
                                        @if (!empty($skill['duration']))
                                            <span class="mp-skill-meta mp-num">{{ $skill['duration'] }}m</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <div class="small" style="color:#9e8780;">No service skills assigned yet.</div>
                        @endif

                        {{-- Compact Recent Deductions below Skills --}}
                        <div class="mp-zone">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="mp-sub-heading mb-0">Recent Deductions &amp; Penalties</div>
                                <span class="mp-pill mp-num {{ $dedMonthTotal > 0 ? 'mp-pill-danger' : 'mp-pill-emerald' }}">
                                    {{ $deductionsPayload['month_label'] }}: {{ $dedFormattedSummary }}
                                </span>
                            </div>
                            @if (!empty($deductionsPayload['items']))
                                @foreach (array_slice($deductionsPayload['items'], 0, 5) as $ded)
                                    <div class="mp-list-row">
                                        <div class="min-w-0">
                                            <div class="fw-semibold" style="color:#f8efe6;">{{ $ded['reason'] }}</div>
                                            <div class="small mp-num" style="color:#9e8780;">
                                                {{ $ded['date_label'] }} &middot; {{ $ded['source'] }}
                                            </div>
                                        </div>
                                        <span class="fw-bold text-nowrap mp-num" style="color:#fda4af;">
                                            -{{ number_format($ded['amount'], 2) }} QAR
                                        </span>
                                    </div>
                                @endforeach
                            @else
                                <div class="small" style="color:#9e8780;">No deductions or penalties recorded on your profile.</div>
                            @endif
                        </div>
                    @else
                        <div class="mp-section-header">
                            <h2 class="mp-section-title">
                                <span class="mp-icon-badge"><i class="bx bx-receipt"></i></span>
                                <span>Recent Deductions &amp; Penalties</span>
                            </h2>
                            <span class="mp-pill mp-num {{ $dedMonthTotal > 0 ? 'mp-pill-danger' : 'mp-pill-emerald' }}">
                                {{ $deductionsPayload['month_label'] }}: {{ $dedFormattedSummary }}
                            </span>
                        </div>

                        @if (!empty($deductionsPayload['items']))
                            @foreach (array_slice($deductionsPayload['items'], 0, 8) as $ded)
                                <div class="mp-list-row">
                                    <div class="min-w-0">
                                        <div class="fw-semibold" style="color:#f8efe6;">{{ $ded['reason'] }}</div>
                                        <div class="small mp-num" style="color:#9e8780;">
                                            {{ $ded['date_label'] }} &middot;
                                            <span style="color:{{ $ded['is_manual'] ? '#a8938b' : '#fbbf24' }};">{{ $ded['source'] }}</span>
                                        </div>
                                    </div>
                                    <span class="fw-bold text-nowrap mp-num" style="color:#fda4af;">
                                        -{{ number_format($ded['amount'], 2) }} QAR
                                    </span>
                                </div>
                            @endforeach
                        @else
                            <div class="py-4 text-center">
                                <div class="small fw-semibold mb-1" style="color:#cbb8b0;">No deductions or penalties</div>
                                <div class="small" style="color:#8f7a73;">Your payroll record is clear with no deductions logged.</div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

    {{-- ===================================================================== --}}
    {{-- TAB 2: PERFORMANCE (1-TO-1 KPIs MODULE SCORECARD + ROLE DETAILS)      --}}
    {{-- ===================================================================== --}}
    @elseif ($activeTab === 'performance' && $performanceScorecard)
        <div class="d-flex flex-column gap-3">
            {{-- Shared Exact KPIs Module Scorecard Header + KPI Card Grid --}}
            @include('kpi.partials.staff-scorecard-panel', [
                'scorecardVm' => $performanceScorecard,
                'showBackButton' => false,
            ])

            {{-- OPS LEAD: Pending Ops Tracker Items Table --}}
            @if ($isOpsLead && array_key_exists('ops_items', $performanceScorecard))
                @php $opsItems = $performanceScorecard['ops_items'] ?? []; @endphp
                <div class="mp-card">
                    <div class="mp-section-header">
                        <h3 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-list-check"></i></span>
                            <span>Pending Ops Tracker Items</span>
                        </h3>
                        <div class="d-flex align-items-center gap-2">
                            <span class="mp-pill mp-pill-muted mp-num">{{ count($opsItems) }} Open</span>
                            @if (!empty($performanceScorecard['action_url']))
                                <a href="{{ $performanceScorecard['action_url'] }}" class="mp-btn-ghost py-1 px-3">
                                    Open Ops Tracker &nearr;
                                </a>
                            @endif
                        </div>
                    </div>

                    @if (!empty($opsItems))
                        <div class="table-responsive">
                            <table class="mp-table">
                                <thead>
                                    <tr>
                                        <th>Action Item</th>
                                        <th>Scope</th>
                                        <th>Due (Qatar)</th>
                                        <th class="text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($opsItems as $op)
                                        <tr>
                                            <td class="fw-semibold" style="color:#f8efe6;">{{ $op['title'] }}</td>
                                            <td style="color:#cbb8b0;">{{ $op['scope'] }}</td>
                                            <td class="text-nowrap mp-num" style="color:#a8938b;">{{ $op['due_label'] }}</td>
                                            <td class="text-end">
                                                @if ($op['is_overdue'])
                                                    <span class="mp-pill mp-pill-danger">Overdue</span>
                                                @elseif ($op['priority'] === 'urgent')
                                                    <span class="mp-pill mp-pill-amber">Urgent</span>
                                                @else
                                                    <span class="mp-pill mp-pill-muted">Pending</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="small" style="color:#6ee7b7;">All Ops Tracker items are completed.</div>
                    @endif
                </div>
            @endif

            {{-- SALON MANAGER: Pending Complaints for Action (Strictly Branch-Scoped) --}}
            @if ($isSalonManager && $branchPendingComplaints !== null)
                @php $bpc = $branchPendingComplaints; @endphp
                <div class="mp-card">
                    <div class="mp-section-header">
                        <h3 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-error-circle"></i></span>
                            <span>Pending Complaints for Action &mdash; {{ $bpc['branch_label'] }}</span>
                        </h3>
                        <span class="mp-pill mp-num {{ count($bpc['items']) > 0 ? 'mp-pill-amber' : 'mp-pill-emerald' }}">
                            {{ count($bpc['items']) }} Open
                        </span>
                    </div>

                    @if (!empty($bpc['items']))
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
                                    @foreach ($bpc['items'] as $cmp)
                                        <tr>
                                            <td class="fw-semibold mp-num" style="color:#f3d9a4;">{{ $cmp['ref'] }}</td>
                                            <td class="text-nowrap mp-num">{{ $cmp['date_label'] }}</td>
                                            <td>{{ $cmp['category'] }}</td>
                                            <td>{{ $cmp['staff_names'] }}</td>
                                            <td><span class="mp-pill mp-pill-amber">{{ $cmp['status'] }}</span></td>
                                            <td class="text-end">
                                                <a href="{{ $cmp['resolve_url'] }}" class="mp-btn-ghost py-1 px-3">
                                                    Open / Resolve
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="small" style="color:#6ee7b7;">No pending complaints in {{ $bpc['branch_label'] }}.</div>
                    @endif
                </div>
            @endif

            {{-- SALON STAFF: Weekly Evaluations History --}}
            @if ($isSalonStaff && $weeklyEvaluations !== null)
                <div class="mp-card">
                    <div class="mp-section-header">
                        <h3 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-award"></i></span>
                            <span>Weekly Evaluations</span>
                        </h3>
                        <span class="mp-pill mp-pill-muted mp-num">{{ count($weeklyEvaluations) }}</span>
                    </div>

                    @if (!empty($weeklyEvaluations))
                        <div class="table-responsive">
                            <table class="mp-table">
                                <thead>
                                    <tr>
                                        <th>Week / Date</th>
                                        <th>Evaluator</th>
                                        <th>Score</th>
                                        <th>Manager Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($weeklyEvaluations as $ev)
                                        <tr>
                                            <td class="text-nowrap mp-num">{{ $ev['date_label'] }}</td>
                                            <td>{{ $ev['evaluator'] }}</td>
                                            <td>
                                                <span class="mp-pill mp-num {{ (float) $ev['score'] >= 4.0 ? 'mp-pill-emerald' : ((float) $ev['score'] >= 3.0 ? 'mp-pill-amber' : 'mp-pill-danger') }}">
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
            @endif

            {{-- SALON STAFF: My Complaints (STRICT PRIVACY — zero client PII) --}}
            @if ($isSalonStaff && $myComplaints !== null)
                <div class="mp-card">
                    <div class="mp-section-header">
                        <h3 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-message-square-error"></i></span>
                            <span>My Complaints</span>
                        </h3>
                        <span class="mp-pill mp-pill-muted mp-num">{{ count($myComplaints) }}</span>
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
                                            <td class="fw-semibold text-nowrap mp-num" style="color:#f3d9a4;">{{ $mc['ref'] }}</td>
                                            <td class="text-nowrap mp-num">{{ $mc['date_label'] }}</td>
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
            @endif
        </div>

    {{-- ===================================================================== --}}
    {{-- TAB 3: CONTRACTS (STRICTLY OWN, SALARY-ALIGNED, READ-ONLY)            --}}
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
                        <div class="col-6 col-md-4 col-lg">
                            <div class="mp-sub-heading mb-1">Monthly Base Salary</div>
                            <div class="fw-bold mp-num" style="font-size:16px; color:#f3d9a4;">{{ $ac['formatted_base_salary'] }}</div>
                            @if ($ac['total_allowances'] > 0)
                                <div class="small mp-num" style="color:#9e8780;">Total pkg: {{ $ac['formatted_total_package'] }}</div>
                            @endif
                        </div>
                        <div class="col-6 col-md-4 col-lg">
                            <div class="mp-sub-heading mb-1">Job Title / Role</div>
                            <div class="fw-semibold">{{ $ac['role_title'] }}</div>
                        </div>
                        <div class="col-6 col-md-4 col-lg">
                            <div class="mp-sub-heading mb-1">Contract Type</div>
                            <div>{{ $ac['contract_type'] }}</div>
                        </div>
                        <div class="col-6 col-md-6 col-lg">
                            <div class="mp-sub-heading mb-1">Start Date</div>
                            <div class="mp-num">{{ $ac['start_date'] }}</div>
                        </div>
                        <div class="col-6 col-md-6 col-lg">
                            <div class="mp-sub-heading mb-1">End / Renewal Date</div>
                            <div class="mp-num">{{ $ac['expiry_date'] }}</div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 pt-3" style="border-top:1px solid rgba(255,255,255,0.06);">
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
                                <div class="small mp-num" style="color:#9e8780;">
                                    Issued: {{ $sDoc['issue_date'] }} &middot; Renewal/Expiry: {{ $sDoc['expiry_date'] }}
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ $sDoc['view_url'] }}" target="_blank" class="mp-btn-ghost py-1 px-3">
                                    <i class="bx bx-show"></i> View
                                </a>
                                <a href="{{ $sDoc['download_url'] }}" class="mp-btn-ghost py-1 px-3">
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
                                    <th>Monthly Base</th>
                                    <th>Start &ndash; End</th>
                                    <th>Status</th>
                                    <th class="text-end">Document</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($contractsPayload['prior_contracts'] as $pc)
                                    <tr>
                                        <td class="fw-semibold mp-num">{{ $pc['contract_number'] }}</td>
                                        <td>{{ $pc['role_title'] }}</td>
                                        <td class="mp-num">{{ $pc['formatted_base_salary'] }}</td>
                                        <td class="mp-num">{{ $pc['start_date'] }} &ndash; {{ $pc['expiry_date'] }}</td>
                                        <td><span class="mp-pill mp-pill-muted">{{ $pc['status_label'] }}</span></td>
                                        <td class="text-end">
                                            <a href="{{ $pc['download_url'] }}" class="mp-btn-ghost py-1 px-3">
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
    {{-- TAB 4: DOCUMENTS (STRICTLY OWN PRIVATE STORAGE, READ-ONLY)            --}}
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
                                            <div class="small mp-num" style="color:#9e8780;">No. {{ $doc['document_number'] }}</div>
                                        @endif
                                    </td>
                                    <td class="text-nowrap mp-num">{{ $doc['issue_date_label'] }}</td>
                                    <td class="text-nowrap mp-num">{{ $doc['expiry_date_label'] }}</td>
                                    <td>
                                        <span class="mp-pill mp-pill-{{ $doc['expiry_status']['tone'] }}">
                                            {{ $doc['expiry_status']['label'] }}
                                        </span>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ $doc['view_url'] }}" target="_blank" class="mp-btn-ghost py-1 px-2 me-1">
                                            <i class="bx bx-show"></i> View
                                        </a>
                                        <a href="{{ $doc['download_url'] }}" class="mp-btn-ghost py-1 px-2">
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
    {{-- TAB 5: LEAVES (SALON MANAGER & SALON STAFF ONLY)                      --}}
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
                            <span class="mp-pill mp-pill-emerald mp-num">{{ $leavesPayload['annual']['remaining'] }} days left</span>
                        </div>
                        <div class="row text-center g-2 mp-num">
                            <div class="col-3">
                                <div class="mp-sub-heading mb-1">Entitled</div>
                                <div class="fw-bold fs-4">{{ $leavesPayload['annual']['entitled'] }}</div>
                            </div>
                            <div class="col-3">
                                <div class="mp-sub-heading mb-1">Used</div>
                                <div class="fw-bold fs-4" style="color:#e5b8a8;">{{ $leavesPayload['annual']['used'] }}</div>
                            </div>
                            <div class="col-3">
                                <div class="mp-sub-heading mb-1">Pending</div>
                                <div class="fw-bold fs-4" style="color:#fbbf24;">{{ $leavesPayload['annual']['pending'] }}</div>
                            </div>
                            <div class="col-3">
                                <div class="mp-sub-heading mb-1">Remaining</div>
                                <div class="fw-bold fs-4" style="color:#6ee7b7;">{{ $leavesPayload['annual']['remaining'] }}</div>
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
                            <span class="mp-pill mp-pill-emerald mp-num">{{ $leavesPayload['sick']['remaining'] }} days left</span>
                        </div>
                        <div class="row text-center g-2 mp-num">
                            <div class="col-3">
                                <div class="mp-sub-heading mb-1">Entitled</div>
                                <div class="fw-bold fs-4">{{ $leavesPayload['sick']['entitled'] }}</div>
                            </div>
                            <div class="col-3">
                                <div class="mp-sub-heading mb-1">Used</div>
                                <div class="fw-bold fs-4" style="color:#fda4af;">{{ $leavesPayload['sick']['used'] }}</div>
                            </div>
                            <div class="col-3">
                                <div class="mp-sub-heading mb-1">Pending</div>
                                <div class="fw-bold fs-4" style="color:#fbbf24;">{{ $leavesPayload['sick']['pending'] }}</div>
                            </div>
                            <div class="col-3">
                                <div class="mp-sub-heading mb-1">Remaining</div>
                                <div class="fw-bold fs-4" style="color:#6ee7b7;">{{ $leavesPayload['sick']['remaining'] }}</div>
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
                                        <td class="text-nowrap mp-num">{{ $lv['date_range'] }}</td>
                                        <td class="mp-num">{{ $lv['total_days'] }}</td>
                                        <td style="color:#cbb8b0;">{{ $lv['reason'] ?: '—' }}</td>
                                        <td>
                                            @if ($lv['has_certificate'])
                                                <a href="{{ $lv['certificate_url'] }}" target="_blank" class="mp-btn-ghost py-1 px-2">
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
                                                        <button type="submit" class="mp-btn-ghost py-1 px-2" style="color:#fda4af; border-color:rgba(253,164,175,0.34);">
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
                            <div class="small text-center py-2 mp-num" id="plTotalDaysBadge" style="border-radius:10px; background:rgba(212,163,115,0.14); color:#f3d9a4; font-weight:700;">
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
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Monthly Salary Show/Hide Privacy Toggle (works in both Self-Service & Admin Preview)
        const salBtn = document.getElementById('mpSalaryToggleBtn');
        const salVal = document.getElementById('mpSalaryValue');
        const salIcon = document.getElementById('mpSalaryToggleIcon');
        if (salBtn && salVal) {
            let isMasked = false;
            salBtn.addEventListener('click', function () {
                isMasked = !isMasked;
                salVal.textContent = isMasked ? (salVal.dataset.salaryMasked || '••••••') : (salVal.dataset.salaryReal || '');
                salBtn.setAttribute('aria-pressed', isMasked ? 'true' : 'false');
                salBtn.setAttribute('aria-label', isMasked ? 'Show monthly salary' : 'Hide monthly salary');
                if (salIcon) {
                    salIcon.className = isMasked ? 'bx bx-hide' : 'bx bx-show';
                }
            });
        }

        @if (!$isAdminPreview)
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
        @endif
    });
</script>
@endsection
