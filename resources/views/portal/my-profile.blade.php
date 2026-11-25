@extends($isSalonStaff ? 'layouts.portal' : 'layouts.app')

@section('title', 'My Profile — Laleen Staff Portal')

@section('content')
<style>
    .mp-wrap {
        max-width: 960px;
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

    .mp-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(229, 184, 168, 0.35);
        flex-shrink: 0;
    }

    .mp-avatar-fallback {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(217, 143, 131, 0.24), rgba(212, 163, 115, 0.18));
        border: 1px solid rgba(229, 184, 168, 0.32);
        color: #f8efe6;
        font-family: 'Playfair Display', serif;
        font-size: 22px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
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

    .mp-shift-days {
        font-weight: 600;
        color: #e5b8a8;
    }

    .mp-shift-hours {
        color: #f8efe6;
        font-variant-numeric: tabular-nums;
    }

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

    .mp-skill-meta {
        font-size: 11px;
        color: #b8a29a;
    }

    .mp-off-label {
        font-size: 11.5px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #b8a29a;
        margin-bottom: 8px;
    }

    .mp-off-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .mp-off-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        padding: 8px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        font-size: 13px;
    }

    .mp-off-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .mp-btn-ghost {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: 999px;
        border: 1px solid rgba(229, 184, 168, 0.25);
        background: rgba(255, 255, 255, 0.03);
        color: #f8efe6;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
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
    }

    .mp-coming-soon {
        text-align: center;
        padding: 46px 20px;
    }

    .mp-coming-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: rgba(217, 143, 131, 0.12);
        border: 1px solid rgba(217, 143, 131, 0.25);
        color: #e5b8a8;
        font-size: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 14px;
    }

    #portalChangePasswordModal .modal-content {
        background: #1b1315 !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        border: 1px solid rgba(229, 184, 168, 0.22) !important;
        border-radius: 16px !important;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.72) !important;
        color: #f8efe6;
    }

    #portalChangePasswordModal .modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }

    #portalChangePasswordModal .modal-footer {
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    #portalChangePasswordModal .form-label {
        font-size: 12px;
        font-weight: 600;
        color: #d6c3bc;
    }

    #portalChangePasswordModal .form-control {
        background-color: #130d0f !important;
        border: 1px solid rgba(229, 184, 168, 0.22) !important;
        color: #f8efe6 !important;
        border-radius: 10px;
    }

    #portalChangePasswordModal .form-control:focus {
        border-color: #d98f83 !important;
        box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.18) !important;
    }
</style>

<div class="mp-wrap">
    {{-- Profile Header Card --}}
    <div class="mp-card mb-3">
        <div class="mp-hero mb-0">
            <div class="mp-hero-left">
                @if ($profile['avatar'])
                    <img src="{{ $profile['avatar'] }}" alt="{{ $profile['name'] }}" class="mp-avatar">
                @else
                    <div class="mp-avatar-fallback">{{ $profile['initials'] }}</div>
                @endif
                <div class="min-w-0">
                    <h1 class="mp-name text-truncate">{{ $profile['name'] }}</h1>
                    <div class="mp-badges">
                        <span class="mp-pill mp-pill-role">{{ $profile['role_badge'] }}</span>
                        <span class="mp-pill mp-pill-{{ $profile['status_tone'] }}">{{ $profile['status_label'] }}</span>
                        @if ($profile['employment_type'])
                            <span class="mp-pill" style="background:rgba(255,255,255,0.05); color:#cbb8b0; border:1px solid rgba(255,255,255,0.08);">
                                {{ $profile['employment_type'] }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <button type="button" class="mp-btn-ghost" data-bs-toggle="modal" data-bs-target="#portalChangePasswordModal">
                <i class="bx bx-lock-alt"></i>
                <span>Change Password</span>
            </button>
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

    {{-- Portal Navigation Tabs --}}
    <nav class="mp-tabs" role="tablist" aria-label="My Profile Sections">
        <a href="{{ route('portal.profile', ['tab' => 'overview']) }}"
            class="mp-tab-link {{ $activeTab === 'overview' ? 'is-active' : '' }}" role="tab">
            <i class="bx bx-user"></i>
            <span>Overview</span>
        </a>
        <a href="{{ route('portal.profile', ['tab' => 'contracts']) }}"
            class="mp-tab-link {{ $activeTab === 'contracts' ? 'is-active' : '' }}" role="tab">
            <i class="bx bx-file"></i>
            <span>Contracts</span>
        </a>
        <a href="{{ route('portal.profile', ['tab' => 'documents']) }}"
            class="mp-tab-link {{ $activeTab === 'documents' ? 'is-active' : '' }}" role="tab">
            <i class="bx bx-folder"></i>
            <span>Documents</span>
        </a>
        <a href="{{ route('portal.profile', ['tab' => 'leaves']) }}"
            class="mp-tab-link {{ $activeTab === 'leaves' ? 'is-active' : '' }}" role="tab">
            <i class="bx bx-calendar-event"></i>
            <span>Leaves</span>
        </a>
    </nav>

    {{-- TAB 1: OVERVIEW --}}
    @if ($activeTab === 'overview')
        <div class="row g-3">
            {{-- Card 1: Branch Location & Working Shifts --}}
            <div class="col-12 col-md-6">
                <div class="mp-card h-100">
                    <div class="mp-section-header">
                        <h2 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-map-pin"></i></span>
                            <span>Branch Location</span>
                        </h2>
                        @if ($shiftSchedule['weekly_hours'] > 0)
                            <span class="mp-pill" style="background:rgba(229,184,168,0.1); color:#e5b8a8; border:1px solid rgba(229,184,168,0.2);">
                                {{ $shiftSchedule['weekly_hours'] }}h / wk
                            </span>
                        @endif
                    </div>

                    <div class="mp-branch-name">{{ $branchInfo['label'] }}</div>
                    <div class="mp-branch-sub">{{ $branchInfo['subtitle'] }}</div>

                    <div class="mp-shift-list">
                        <div class="mp-off-label mb-0">Working Shift Hours</div>
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

            {{-- Card 2: Skills / Agent Channels --}}
            <div class="col-12 col-md-6">
                <div class="mp-card h-100">
                    <div class="mp-section-header">
                        <h2 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx {{ $skillsPayload['mode'] === 'agent' ? 'bx-message-dots' : 'bx-spa' }}"></i></span>
                            <span>{{ $skillsPayload['title'] }}</span>
                        </h2>
                        @if (!empty($skillsPayload['items']))
                            <span class="mp-pill" style="background:rgba(255,255,255,0.05); color:#cbb8b0; border:1px solid rgba(255,255,255,0.09);">
                                {{ count($skillsPayload['items']) }}
                            </span>
                        @endif
                    </div>

                    @if ($skillsPayload['mode'] === 'salon_staff')
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
                    @else
                        <div class="mp-skill-grid">
                            @foreach ($skillsPayload['items'] as $scopeItem)
                                <span class="mp-skill-chip">
                                    <span>{{ $scopeItem['name'] }}</span>
                                    @if (!empty($scopeItem['meta']))
                                        <span class="mp-skill-meta">&middot; {{ $scopeItem['meta'] }}</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                        @if (!empty($skillsPayload['extra_services']))
                            <div class="mt-3 pt-3" style="border-top: 1px solid rgba(255,255,255,0.06);">
                                <div class="mp-off-label">Assigned Salon Skills</div>
                                <div class="mp-skill-grid">
                                    @foreach ($skillsPayload['extra_services'] as $svc)
                                        <span class="mp-skill-chip">
                                            <span>{{ $svc['name'] }}</span>
                                            <span class="mp-skill-meta">{{ $svc['duration'] }}m</span>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Card 3: Off Days (Weekly Fixed + Next 30 Days in Asia/Qatar) --}}
            <div class="col-12">
                <div class="mp-card">
                    <div class="mp-section-header">
                        <h2 class="mp-section-title">
                            <span class="mp-icon-badge"><i class="bx bx-calendar-x"></i></span>
                            <span>Off Days</span>
                        </h2>
                        <span class="small" style="color:#9e8780;">Qatar Time &middot; {{ $todayQatarLabel }}</span>
                    </div>

                    <div class="row g-4">
                        <div class="col-12 col-md-5">
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

                            @if (!empty($offDaysPayload['upcoming_weekly_dates']))
                                <div class="mt-3 pt-3" style="border-top: 1px solid rgba(255,255,255,0.06);">
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
                        </div>

                        <div class="col-12 col-md-7">
                            <div class="mp-off-label">Scheduled Time Off (Next 30 Days)</div>
                            @if (!empty($offDaysPayload['scheduled_time_offs']))
                                <div class="mp-off-list">
                                    @foreach ($offDaysPayload['scheduled_time_offs'] as $to)
                                        <div class="mp-off-row">
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
                                </div>
                            @else
                                <div class="small" style="color:#9e8780;">No additional scheduled time off in the next 30 days.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($activeTab === 'contracts')
        <div class="mp-card mp-coming-soon">
            <div class="mp-coming-icon"><i class="bx bx-file"></i></div>
            <h2 class="h5 fw-bold mb-2" style="color:#f8efe6;">Contracts &amp; Agreements</h2>
            <p class="small mb-0 mx-auto" style="max-width:420px; color:#b8a29a;">
                Coming soon — your employment contracts, signed agreements, and downloadable PDFs will appear here.
            </p>
        </div>
    @elseif ($activeTab === 'documents')
        <div class="mp-card mp-coming-soon">
            <div class="mp-coming-icon"><i class="bx bx-folder"></i></div>
            <h2 class="h5 fw-bold mb-2" style="color:#f8efe6;">Personal Documents</h2>
            <p class="small mb-0 mx-auto" style="max-width:420px; color:#b8a29a;">
                Coming soon — access your QID, passport copies, health certificates, and HR notices in one place.
            </p>
        </div>
    @elseif ($activeTab === 'leaves')
        <div class="mp-card mp-coming-soon">
            <div class="mp-coming-icon"><i class="bx bx-calendar-event"></i></div>
            <h2 class="h5 fw-bold mb-2" style="color:#f8efe6;">Leave Requests &amp; Balance</h2>
            <p class="small mb-0 mx-auto" style="max-width:420px; color:#b8a29a;">
                Coming soon — submit leave requests, track approvals, and review your annual leave balance directly from your portal.
            </p>
        </div>
    @endif
</div>

{{-- Change Password Modal --}}
<div class="modal fade" id="portalChangePasswordModal" tabindex="-1" aria-labelledby="portalChangePasswordTitle" aria-hidden="true">
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
@endsection

@section('scripts')
@if ($errors->portalPassword->any())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('portalChangePasswordModal');
        if (el && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
    });
</script>
@endif
@endsection
