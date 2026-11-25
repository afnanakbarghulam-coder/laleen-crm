{{--
    Single Unified "Manage Role & Access" + "+ Add Login" + "Reset Password" Modals
    Shared by BOTH `Roles & Access` (users/index.blade.php) and `Directory & Profiles` (staff/index.blade.php)
    so there are zero duplicate or conflicting access UIs.

    Expected variables:
    - $linkableStaffList: collection/array of Staff rows with id, name, email, branch, bookable, user_id
    - $accessUsersMap: array keyed by user_id => [id, name, email, role, is_active, staff_id, permissions]
--}}
@php
    $uRolesMap = \App\Models\User::ROLES;
    $uActiveRolesMap = \App\Models\User::ACTIVE_ROLES;
    $uLegacyRolesMap = \App\Models\User::LEGACY_ROLES;
    $uRemoteRoles = \App\Models\User::REMOTE_ROLES;
    $uBranchRoles = \App\Models\User::BRANCH_ROLES;
    $uModulesMap = \App\Models\User::MODULES;
    $uRolePresets = \App\Models\User::ROLE_PERMISSION_PRESETS;
    $uDefaultPerms = \App\Models\User::defaultPermissionsForRole('user');
    $uCurrentAuthId = (int) auth()->id();

    $uStaffList = collect($linkableStaffList ?? [])->map(function ($s) {
        $isRemote = method_exists($s, 'isRemote') ? $s->isRemote() : (bool) ($s->is_remote ?? empty($s->branch));
        $branchKey = method_exists($s, 'getBranchKeyAttribute')
            ? $s->branch_key
            : ($isRemote ? 'remote' : strtolower((string) ($s->branch ?? 'remote')));
        $branchLabel = method_exists($s, 'getLocationLabelAttribute')
            ? $s->location_label
            : ($isRemote ? 'Remote' : match ($branchKey) {
                'old_airport' => 'Old Airport',
                'wakrah', 'al_wakrah' => 'Al Wakrah',
                'both' => 'Old Airport & Al Wakrah',
                default => 'Remote',
            });
        return [
            'id' => (int) $s->id,
            'name' => (string) $s->name,
            'email' => (string) ($s->email ?? ''),
            'branch' => $branchKey,
            'is_remote' => $isRemote,
            'branch_label' => $branchLabel,
            'bookable' => (bool) $s->bookable,
            'user_id' => $s->user_id ? (int) $s->user_id : null,
            'suggested_role' => ($s->bookable && !$isRemote) ? 'salon_staff' : 'agent',
            'preview_url' => route('portal.admin-preview', $s->id),
        ];
    })->values()->all();

    $uUsersPayload = $accessUsersMap ?? [];
@endphp

<style>
    .sa-modal .modal-content {
        background: #1b1315 !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        border: 1px solid rgba(229, 184, 168, 0.22) !important;
        color: #f8efe6;
        border-radius: 18px !important;
        box-shadow: 0 24px 64px rgba(0, 0, 0, 0.72) !important;
    }
    .sa-modal .modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.06) !important;
        padding: 16px 22px 14px;
    }
    .sa-modal .modal-body {
        padding: 18px 22px;
        max-height: 76vh;
        overflow-y: auto;
    }
    .sa-modal .modal-footer {
        border-top: 1px solid rgba(255, 255, 255, 0.06) !important;
        padding: 12px 22px 16px;
    }
    .sa-modal .form-label {
        font-size: 12px;
        font-weight: 600;
        color: #d6c3bc;
        margin-bottom: 5px;
    }
    .sa-modal .form-control,
    .sa-modal .form-select {
        background-color: #130d0f !important;
        color: #f8efe6 !important;
        border: 1px solid rgba(229, 184, 168, 0.22) !important;
        border-radius: 10px;
        color-scheme: dark;
    }
    .sa-modal .form-select {
        appearance: none;
        -webkit-appearance: none;
        padding-right: 2.25rem;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23d4a373' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.8rem center !important;
        background-size: 13px 10px !important;
    }
    .sa-modal .form-select option {
        background-color: #130d0f !important;
        color: #f8efe6 !important;
    }
    .sa-modal .form-control:focus,
    .sa-modal .form-select:focus {
        border-color: #d98f83 !important;
        box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.18) !important;
    }
    .sa-modal-section {
        padding-bottom: 16px;
        margin-bottom: 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    .sa-modal-section:last-child {
        padding-bottom: 0;
        margin-bottom: 0;
        border-bottom: none;
    }
    .sa-modal-section-title {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: #d4a373;
        margin-bottom: 10px;
    }
    .sa-mode-tabs {
        display: inline-flex;
        gap: 6px;
        padding: 4px;
        border-radius: 999px;
        background: #130d0f;
        border: 1px solid rgba(229, 184, 168, 0.16);
        margin-bottom: 16px;
    }
    .sa-mode-tab {
        border: none;
        background: transparent;
        color: #b8a29a;
        font-size: 12px;
        font-weight: 600;
        padding: 6px 14px;
        border-radius: 999px;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .sa-mode-tab.is-active {
        background: linear-gradient(135deg, #d98f83, #c19a8c);
        color: #1c110f;
        font-weight: 700;
    }
    .sa-inline-chip {
        font-size: 11px;
        font-weight: 600;
        padding: 2px 9px;
        border-radius: 999px;
        background: rgba(212, 163, 115, 0.14);
        color: #d4a373;
    }
    .sa-preset-btn {
        background: rgba(229, 184, 168, 0.1);
        color: #e5b8a8;
        border: 1px solid rgba(229, 184, 168, 0.28);
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        padding: 3px 11px;
        cursor: pointer;
    }
    .sa-preset-btn:hover {
        background: rgba(229, 184, 168, 0.2);
        color: #f8efe6;
    }
    .sa-perm-table-wrap {
        max-height: 280px;
        overflow-y: auto;
        padding-right: 2px;
    }
    .sa-perm-table th {
        font-size: 11px;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #b8a29a !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
        padding: 7px 10px;
        position: sticky;
        top: 0;
        background: #1b1315;
        z-index: 2;
    }
    .sa-perm-table td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        padding: 6px 10px;
        font-size: 12.5px;
    }
    .sa-perm-table tr:last-child td { border-bottom: none !important; }
    .sa-radio {
        cursor: pointer;
        background-color: #130d0f;
        border-color: rgba(229, 184, 168, 0.35);
    }
    .sa-radio:checked {
        background-color: #e5b8a8 !important;
        border-color: #e5b8a8 !important;
    }
    .sa-modal-err {
        padding: 9px 12px;
        border-radius: 10px;
        background: rgba(253, 164, 175, 0.12);
        border: 1px solid rgba(253, 164, 175, 0.36);
        color: #fda4af;
        font-size: 12.5px;
        margin-bottom: 14px;
    }
</style>

{{-- 1. UNIFIED "+ ADD LOGIN" / "+ GRANT ACCESS" MODAL --}}
<div class="modal fade sa-modal" id="addLoginModal" tabindex="-1" aria-labelledby="addLoginModalTitle" aria-hidden="true" x-data="staffAccessUserForm('new')">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="addLoginModalTitle" style="color: #f8efe6;">Add Portal &amp; CRM Login</h5>
                    <div class="small" style="color: #b8a29a;">Grant login access to an existing team member or create a new account.</div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addLoginForm" method="POST" action="{{ route('users.store') }}" @submit.prevent="submitCreate($event)">
                @csrf
                <div class="modal-body">
                    <div class="sa-modal-err d-none" id="addLoginError"></div>

                    <div class="sa-mode-tabs" role="tablist">
                        <button type="button" class="sa-mode-tab" :class="{ 'is-active': createMode === 'existing_staff' }"
                                @click="setCreateMode('existing_staff')">
                            Grant Login to Existing Staff
                        </button>
                        <button type="button" class="sa-mode-tab" :class="{ 'is-active': createMode === 'new_account' }"
                                @click="setCreateMode('new_account')">
                            Create New Account
                        </button>
                    </div>

                    {{-- Mode A: Existing Unlinked Staff Picker --}}
                    <div class="sa-modal-section" x-show="createMode === 'existing_staff'" x-cloak>
                        <div class="sa-modal-section-title">Select Unlinked Staff Member</div>
                        <select class="form-select" id="addLoginExistingStaff" name="existing_staff_id"
                                x-model="existingStaffId" @change="onSelectExistingStaff()">
                            <option value="">-- Choose a team member without login --</option>
                            <template x-for="st in unlinkedStaffOptions()" :key="st.id">
                                <option :value="String(st.id)" x-text="st.name + ' (' + st.branch_label + ')'"></option>
                            </template>
                        </select>
                        <div class="small mt-1" style="color: #8f7a73;" x-show="unlinkedStaffOptions().length === 0">
                            All team members already have login accounts. Switch to "Create New Account" to add someone new.
                        </div>
                    </div>

                    {{-- Login Details --}}
                    <div class="sa-modal-section">
                        <div class="sa-modal-section-title">1. Account &amp; Credentials</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="addLoginName">Full Name *</label>
                                <input type="text" id="addLoginName" name="name" class="form-control" x-model="name" required maxlength="255" placeholder="e.g., Sara Ahmed">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="addLoginEmail">Login Email *</label>
                                <input type="email" id="addLoginEmail" name="email" class="form-control" x-model="email" required maxlength="255" placeholder="name@laleen.qa" autocomplete="off">
                            </div>
                            <div class="col-md-7">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0" for="addLoginPassword">Temporary Password *</label>
                                    <button type="button" class="sa-preset-btn py-0 px-2" @click="generatePassword('addLoginPassword')">
                                        <i class="bx bx-refresh"></i> Generate
                                    </button>
                                </div>
                                <div class="input-group">
                                    <input type="password" id="addLoginPassword" name="password" class="form-control" x-model="password" required minlength="8" maxlength="128" placeholder="Minimum 8 characters" autocomplete="new-password">
                                    <button type="button" class="btn btn-outline-secondary" @click="togglePwVisibility('addLoginPassword')" style="border-color:rgba(229,184,168,0.22); color:#e5b8a8;">
                                        <i class="bx bx-show"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="addLoginRole">Assigned Role *</label>
                                <select id="addLoginRole" name="role" class="form-select" x-model="role" @change="selectRole(role)" required>
                                    @foreach ($uActiveRolesMap as $rKey => $rLabel)
                                        <option value="{{ $rKey }}">{{ $rLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Optional Linked HR Profile toggle when creating a brand-new account --}}
                        <div class="mt-3 pt-2" style="border-top: 1px solid rgba(255,255,255,0.05);" x-show="createMode === 'new_account'" x-cloak>
                            <div class="row g-2 align-items-center">
                                <div class="col-md-7">
                                    <label class="form-check form-switch mb-0 d-flex align-items-center gap-2" style="cursor:pointer;">
                                        <input type="checkbox" class="form-check-input m-0" name="create_staff_profile" value="1" x-model="createStaffProfile">
                                        <span class="small fw-semibold" style="color: #f8efe6;">Also create linked Staff HR Profile (enables /my-profile)</span>
                                    </label>
                                </div>
                                <div class="col-md-5" x-show="createStaffProfile" x-cloak>
                                    <select class="form-select form-select-sm" name="staff_branch" x-model="staffBranch" :disabled="isRemoteRole(role)">
                                        <option value="remote" :disabled="isBranchRole(role)">Remote</option>
                                        <option value="old_airport" :disabled="isRemoteRole(role)">Old Airport Branch</option>
                                        <option value="wakrah" :disabled="isRemoteRole(role)">Al Wakrah Branch</option>
                                        <option value="both" :disabled="isRemoteRole(role)">Both Branches</option>
                                    </select>
                                    <div class="small mt-1" style="color: #9e8780;" x-show="isRemoteRole(role)" x-cloak>
                                        Remote roles are automatically assigned as Remote.
                                    </div>
                                    <div class="small mt-1" style="color: #9e8780;" x-show="isBranchRole(role)" x-cloak>
                                        Requires a physical salon branch (Old Airport or Al Wakrah).
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Module Permissions --}}
                    <div class="sa-modal-section">
                        <div class="sa-modal-section-title">2. Role &amp; Module Permissions</div>
                        @include('users._permissions-panel', ['uid' => 'new', 'permissions' => $uDefaultPerms])
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sa-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn-rosegold" :disabled="submitting">
                        <span x-text="submitting ? 'Saving…' : (createMode === 'existing_staff' ? 'Grant Access' : 'Create Account')">Create Account</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 2. UNIFIED "MANAGE ROLE & ACCESS" MODAL (SHARED BY ROLES & ACCESS + DIRECTORY & PROFILES) --}}
<div class="modal fade sa-modal" id="manageAccessModal" tabindex="-1" aria-labelledby="manageAccessModalTitle" aria-hidden="true" x-data="staffAccessUserForm('manage')">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="manageAccessModalTitle" style="color: #f8efe6;">
                        Manage Role &amp; Access
                    </h5>
                    <div class="small" style="color: #b8a29a;" x-text="name + ' · ' + email"></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="manageAccessForm" method="POST" @submit.prevent="submitManage($event)">
                @csrf
                <div class="modal-body">
                    <div class="sa-modal-err d-none" id="manageAccessError"></div>

                    {{-- SECTION 1: LOGIN & STATUS --}}
                    <div class="sa-modal-section">
                        <div class="sa-modal-section-title">1. Login &amp; Status</div>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label" for="maName">Account Name *</label>
                                <input type="text" id="maName" name="name" class="form-control" x-model="name" required maxlength="255">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="maEmail">Login Email *</label>
                                <input type="email" id="maEmail" name="email" class="form-control" x-model="email" required maxlength="255">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label d-block">Status</label>
                                <label class="form-check form-switch d-inline-flex align-items-center gap-2 mb-1" style="cursor:pointer;">
                                    <input type="checkbox" class="form-check-input m-0" x-model="isActive" :disabled="Number(editingUserId) === {{ $uCurrentAuthId }}">
                                    <span class="small fw-semibold" :style="isActive ? 'color:#6ee7b7;' : 'color:#fda4af;'" x-text="isActive ? 'Active' : 'Disabled'"></span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="small" style="color: #8f7a73;" x-show="!isActive" x-cloak>
                                Disabling immediately invalidates all active sessions for this user.
                            </div>
                            <button type="button" class="sa-preset-btn ms-auto" @click="showInlineResetPw = !showInlineResetPw">
                                <i class="bx bx-key me-1"></i>
                                <span x-text="showInlineResetPw ? 'Hide Password Reset' : 'Reset Password'">Reset Password</span>
                            </button>
                        </div>

                        <div class="mt-2 pt-2" x-show="showInlineResetPw" x-cloak style="border-top: 1px dashed rgba(229,184,168,0.16);">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0" for="maPassword">New Temporary Password (optional)</label>
                                <button type="button" class="sa-preset-btn py-0 px-2" @click="generatePassword('maPassword')">
                                    <i class="bx bx-refresh"></i> Generate
                                </button>
                            </div>
                            <div class="input-group">
                                <input type="password" id="maPassword" name="password" class="form-control" x-model="password" minlength="8" maxlength="128" placeholder="Leave blank to keep current password" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" @click="togglePwVisibility('maPassword')" style="border-color:rgba(229,184,168,0.22); color:#e5b8a8;">
                                    <i class="bx bx-show"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 2: ROLE & MODULE PERMISSIONS --}}
                    <div class="sa-modal-section">
                        <div class="sa-modal-section-title">2. Role &amp; Module Permissions</div>
                        <div class="mb-3" style="max-width: 360px;">
                            <label class="form-label" for="maRole">Assigned Role *</label>
                            <select id="maRole" name="role" class="form-select" x-model="role" @change="selectRole(role)" required>
                                <template x-if="legacyCurrentRole">
                                    <option :value="legacyCurrentRole" x-text="legacyCurrentRoleLabel + ' (Legacy — please reassign)'"></option>
                                </template>
                                @foreach ($uActiveRolesMap as $rKey => $rLabel)
                                    <option value="{{ $rKey }}">{{ $rLabel }}</option>
                                @endforeach
                            </select>
                            <div class="small mt-1" style="color: #fbbf24;" x-show="isLegacyRole(role)" x-cloak>
                                This account uses a retired legacy role. Reassign it to an active role above.
                            </div>
                        </div>

                        @include('users._permissions-panel', ['uid' => 'manage', 'permissions' => $uDefaultPerms])
                    </div>

                    {{-- SECTION 3: LINKED STAFF HR PROFILE --}}
                    <div class="sa-modal-section" id="maLinkedProfileSection">
                        <div class="sa-modal-section-title">3. Linked Staff HR Profile (1-to-1)</div>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label" for="maStaffId">Linked Staff Record</label>
                                <select id="maStaffId" name="staff_id" class="form-select" x-model="linkedStaffId">
                                    <option value="">-- Unlinked (No Staff HR Profile) --</option>
                                    <template x-for="st in linkableStaffOptions()" :key="st.id">
                                        <option :value="String(st.id)" x-text="st.name + ' · ' + st.branch_label"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button type="button" class="sa-preset-btn w-100 py-2"
                                        x-show="!linkedStaffId"
                                        :disabled="creatingProfile"
                                        @click="createStaffProfileForUser()">
                                    <i class="bx bx-user-plus me-1"></i>
                                    <span x-text="creatingProfile ? 'Creating…' : 'Create Staff Profile'">Create Staff Profile</span>
                                </button>
                                <a class="sa-btn-ghost w-100 justify-content-center py-2"
                                   x-show="linkedStaffId && currentPreviewUrl()"
                                   :href="currentPreviewUrl()">
                                    <i class="bx bx-show"></i> Preview Portal Profile
                                </a>
                            </div>
                        </div>
                        <div class="small mt-1" style="color: #8f7a73;">
                            Linking a Staff HR Profile powers Branch Location, Skills, Shifts, and Off Days on <strong>/my-profile</strong>.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sa-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn-rosegold" :disabled="submitting">
                        <span x-text="submitting ? 'Saving…' : 'Save Changes'">Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 3. QUICK RESET PASSWORD MODAL --}}
<div class="modal fade sa-modal" id="quickResetPwModal" tabindex="-1" aria-labelledby="quickResetPwTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="quickResetPwTitle" style="color:#f8efe6;">Reset Password</h5>
                    <div class="small" style="color:#b8a29a;" id="qrpUserMeta"></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickResetPwForm">
                <div class="modal-body">
                    <input type="hidden" id="qrpUserId">
                    <div class="sa-modal-err d-none" id="qrpError"></div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label mb-0" for="qrpPassword">New Temporary Password *</label>
                        <button type="button" class="sa-preset-btn py-0 px-2" id="qrpGenBtn">
                            <i class="bx bx-refresh"></i> Generate
                        </button>
                    </div>
                    <div class="input-group">
                        <input type="password" id="qrpPassword" class="form-control" required minlength="8" maxlength="128" placeholder="Minimum 8 characters" autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary" id="qrpToggleBtn" style="border-color:rgba(229,184,168,0.22); color:#e5b8a8;">
                            <i class="bx bx-show"></i>
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sa-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn-rosegold" id="qrpSubmitBtn">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        const rolesMap = {{ \Illuminate\Support\Js::from($uRolesMap) }};
        const legacyRolesMap = {{ \Illuminate\Support\Js::from($uLegacyRolesMap) }};
        const remoteRolesList = {{ \Illuminate\Support\Js::from($uRemoteRoles) }};
        const branchRolesList = {{ \Illuminate\Support\Js::from($uBranchRoles) }};
        const modulesMap = {{ \Illuminate\Support\Js::from($uModulesMap) }};
        const rolePresets = {{ \Illuminate\Support\Js::from($uRolePresets) }};
        const staffList = {{ \Illuminate\Support\Js::from($uStaffList) }};
        const usersById = {{ \Illuminate\Support\Js::from($uUsersPayload) }};
        const csrfToken = '{{ csrf_token() }}';

        window.__laleenUnifiedAccessData = { rolesMap, modulesMap, rolePresets, staffList, usersById };

        function genStrongPassword() {
            const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            const lower = 'abcdefghijkmnopqrstuvwxyz';
            const digits = '23456789';
            const symbols = '!@#$%&*';
            const all = upper + lower + digits + symbols;
            let pw = upper[Math.floor(Math.random() * upper.length)]
                + lower[Math.floor(Math.random() * lower.length)]
                + digits[Math.floor(Math.random() * digits.length)]
                + symbols[Math.floor(Math.random() * symbols.length)];
            for (let i = 0; i < 8; i++) pw += all[Math.floor(Math.random() * all.length)];
            return pw.split('').sort(() => 0.5 - Math.random()).join('');
        }

        function registerUnifiedAccessAlpine() {
            if (!window.Alpine || window.__laleenStaffAccessAlpineRegistered) return;
            window.__laleenStaffAccessAlpineRegistered = true;

            window.Alpine.data('staffAccessUserForm', function (uid) {
                return {
                    uid: String(uid),
                    createMode: 'existing_staff',
                    existingStaffId: '',
                    editingUserId: null,
                    name: '',
                    email: '',
                    password: '',
                    role: 'salon_staff',
                    isActive: true,
                    linkedStaffId: '',
                    createStaffProfile: true,
                    staffBranch: 'old_airport',
                    showInlineResetPw: false,
                    submitting: false,
                    creatingProfile: false,
                    staffVersion: 0,
                    legacyCurrentRole: '',
                    legacyCurrentRoleLabel: '',
                    permissions: Object.assign({}, rolePresets['salon_staff'] || {}),
                    overriddenSlugs: {},

                    init() {
                        if (this.uid === 'manage') {
                            window.__laleenManageAccessComponent = this;
                        } else if (this.uid === 'new') {
                            window.__laleenAddLoginComponent = this;
                        }
                    },

                    isLegacyRole(rKey) {
                        return Boolean(rKey && Object.prototype.hasOwnProperty.call(legacyRolesMap, rKey));
                    },

                    isRemoteRole(rKey) {
                        return remoteRolesList.includes(String(rKey || ''));
                    },

                    isBranchRole(rKey) {
                        return branchRolesList.includes(String(rKey || ''));
                    },

                    roleLabel(rKey) {
                        if (rKey === 'agent') return 'Chat Agent';
                        return rolesMap[rKey] || rKey || 'User';
                    },

                    unlinkedStaffOptions() {
                        void this.staffVersion;
                        return staffList.filter(s => !s.user_id);
                    },

                    linkableStaffOptions() {
                        void this.staffVersion;
                        const curUid = Number(this.editingUserId || 0);
                        return staffList.filter(s => !s.user_id || Number(s.user_id) === curUid);
                    },

                    currentPreviewUrl() {
                        void this.staffVersion;
                        const sid = Number(this.linkedStaffId || 0);
                        const found = staffList.find(s => Number(s.id) === sid);
                        return found ? found.preview_url : '';
                    },

                    setCreateMode(mode) {
                        this.createMode = mode;
                        this.clearError('addLoginError');
                        if (mode === 'new_account') {
                            this.existingStaffId = '';
                            if (!this.role || this.role === 'salon_staff') {
                                this.selectRole('agent', true);
                            }
                        } else {
                            this.onSelectExistingStaff();
                        }
                    },

                    onSelectExistingStaff() {
                        const sid = Number(this.existingStaffId || 0);
                        const st = staffList.find(s => Number(s.id) === sid);
                        if (!st) return;
                        this.name = st.name || '';
                        this.email = st.email || '';
                        this.selectRole(st.suggested_role || 'salon_staff', true);
                    },

                    selectRole(newRole, forcePreset = false) {
                        this.role = newRole;
                        if (this.isRemoteRole(newRole)) {
                            this.staffBranch = 'remote';
                        } else if (this.isBranchRole(newRole) && this.staffBranch === 'remote') {
                            this.staffBranch = 'old_airport';
                        }
                        if (!this.isLegacyRole(newRole)) {
                            this.legacyCurrentRole = '';
                            this.legacyCurrentRoleLabel = '';
                        }
                        const preset = rolePresets[newRole] || rolePresets['user'] || {};
                        if (newRole === 'salon_staff' || forcePreset) {
                            this.overriddenSlugs = {};
                        }
                        Object.keys(modulesMap).forEach((slug) => {
                            if (newRole === 'salon_staff') {
                                this.permissions[slug] = 'none';
                            } else if (forcePreset || !this.overriddenSlugs[slug]) {
                                this.permissions[slug] = preset[slug] || 'none';
                            }
                        });
                    },

                    setModulePermission(slug, level) {
                        if (this.role === 'salon_staff') return;
                        this.permissions[slug] = level;
                        this.overriddenSlugs[slug] = true;
                    },

                    applyRolePreset() {
                        this.selectRole(this.role, true);
                    },

                    generatePassword(inputId) {
                        const pw = genStrongPassword();
                        this.password = pw;
                        const el = document.getElementById(inputId);
                        if (el) {
                            el.value = pw;
                            el.type = 'text';
                            el.focus();
                            el.select();
                        }
                    },

                    togglePwVisibility(inputId) {
                        const el = document.getElementById(inputId);
                        if (!el) return;
                        el.type = el.type === 'text' ? 'password' : 'text';
                    },

                    showError(elId, msg) {
                        const box = document.getElementById(elId);
                        if (!box) return;
                        box.textContent = msg;
                        box.classList.remove('d-none');
                    },

                    clearError(elId) {
                        const box = document.getElementById(elId);
                        if (!box) return;
                        box.textContent = '';
                        box.classList.add('d-none');
                    },

                    hydrateForManage(userObj) {
                        this.clearError('manageAccessError');
                        this.editingUserId = Number(userObj.id);
                        this.name = userObj.name || '';
                        this.email = userObj.email || '';
                        this.role = userObj.role || 'salon_staff';
                        if (this.isLegacyRole(this.role)) {
                            this.legacyCurrentRole = this.role;
                            this.legacyCurrentRoleLabel = legacyRolesMap[this.role] || this.role;
                        } else {
                            this.legacyCurrentRole = '';
                            this.legacyCurrentRoleLabel = '';
                        }
                        this.isActive = Boolean(userObj.is_active);
                        this.linkedStaffId = userObj.staff_id ? String(userObj.staff_id) : '';
                        this.password = '';
                        this.showInlineResetPw = false;
                        this.overriddenSlugs = {};
                        const basePerms = userObj.role === 'salon_staff'
                            ? (rolePresets['salon_staff'] || {})
                            : Object.assign({}, rolePresets[userObj.role] || {}, userObj.permissions || {});
                        Object.keys(modulesMap).forEach((slug) => {
                            this.permissions[slug] = basePerms[slug] || 'none';
                        });
                        const targetStaffId = this.linkedStaffId;
                        this.$nextTick(() => {
                            this.linkedStaffId = targetStaffId;
                            const sel = document.getElementById('maStaffId');
                            if (sel) sel.value = targetStaffId;
                        });
                    },

                    hydrateForAdd(opts = {}) {
                        this.clearError('addLoginError');
                        this.name = '';
                        this.email = '';
                        this.password = '';
                        this.overriddenSlugs = {};
                        this.createStaffProfile = true;
                        this.staffBranch = 'old_airport';
                        const hasUnlinked = this.unlinkedStaffOptions().length > 0;
                        const targetMode = opts.mode || (opts.staffId ? 'existing_staff' : (hasUnlinked ? 'existing_staff' : 'new_account'));
                        this.createMode = targetMode;
                        const targetSid = opts.staffId ? String(opts.staffId) : '';
                        this.existingStaffId = targetSid;
                        if (targetMode === 'existing_staff' && targetSid) {
                            this.onSelectExistingStaff();
                        } else if (targetMode === 'existing_staff') {
                            this.selectRole('salon_staff', true);
                        } else {
                            this.selectRole('agent', true);
                        }
                        this.$nextTick(() => {
                            this.existingStaffId = targetSid;
                            const sel = document.getElementById('addLoginExistingStaff');
                            if (sel) sel.value = targetSid;
                        });
                    },

                    async submitCreate() {
                        this.clearError('addLoginError');
                        if (this.createMode === 'existing_staff' && !this.existingStaffId) {
                            this.showError('addLoginError', 'Please select a staff member without a login, or switch to Create New Account.');
                            return;
                        }
                        this.submitting = true;
                        try {
                            const payload = {
                                name: this.name.trim(),
                                email: this.email.trim(),
                                password: this.password,
                                role: this.role,
                                permissions: this.permissions,
                            };
                            if (this.createMode === 'existing_staff') {
                                payload.existing_staff_id = Number(this.existingStaffId);
                            } else {
                                payload.create_staff_profile = this.createStaffProfile ? 1 : 0;
                                payload.staff_branch = this.staffBranch;
                            }

                            const res = await fetch('{{ route('users.store') }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify(payload),
                            });
                            const data = await res.json().catch(() => ({}));
                            if (res.ok && data.success) {
                                bootstrap.Modal.getInstance(document.getElementById('addLoginModal'))?.hide();
                                window.location.reload();
                            } else {
                                const msg = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Could not create account.');
                                this.showError('addLoginError', msg);
                            }
                        } catch (e) {
                            this.showError('addLoginError', 'Network error while saving account.');
                        } finally {
                            this.submitting = false;
                        }
                    },

                    async submitManage() {
                        this.clearError('manageAccessError');
                        if (!this.editingUserId) return;
                        this.submitting = true;
                        try {
                            const payload = {
                                name: this.name.trim(),
                                email: this.email.trim(),
                                role: this.role,
                                is_active: this.isActive ? 1 : 0,
                                staff_id: this.linkedStaffId ? Number(this.linkedStaffId) : null,
                                permissions: this.permissions,
                            };
                            if (this.password && this.password.trim() !== '') {
                                payload.password = this.password;
                            }

                            const res = await fetch(`/users/${this.editingUserId}`, {
                                method: 'PUT',
                                headers: {
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify(payload),
                            });
                            const data = await res.json().catch(() => ({}));
                            if (res.ok && data.success) {
                                bootstrap.Modal.getInstance(document.getElementById('manageAccessModal'))?.hide();
                                window.location.reload();
                            } else {
                                const msg = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Could not update access settings.');
                                this.showError('manageAccessError', msg);
                            }
                        } catch (e) {
                            this.showError('manageAccessError', 'Network error while updating account.');
                        } finally {
                            this.submitting = false;
                        }
                    },

                    async createStaffProfileForUser() {
                        this.clearError('manageAccessError');
                        if (!this.editingUserId) return;
                        this.creatingProfile = true;
                        try {
                            const reqBranch = this.isRemoteRole(this.role)
                                ? 'remote'
                                : '';
                            const res = await fetch(`/users/${this.editingUserId}/create-staff-profile`, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify(reqBranch ? { branch: reqBranch } : {}),
                            });
                            const data = await res.json().catch(() => ({}));
                            if (res.ok && data.success && data.staff) {
                                const isRemote = Boolean(data.staff.is_remote) || !data.staff.branch || String(data.staff.branch).toLowerCase() === 'remote';
                                const bKey = isRemote ? 'remote' : String(data.staff.branch || '').toLowerCase();
                                const bLabel = data.staff.location_label || (isRemote
                                    ? 'Remote'
                                    : (bKey === 'wakrah' || bKey === 'al_wakrah'
                                        ? 'Al Wakrah'
                                        : (bKey === 'both' ? 'Old Airport & Al Wakrah' : 'Old Airport')));
                                staffList.push({
                                    id: Number(data.staff.id),
                                    name: data.staff.name,
                                    email: this.email,
                                    branch: bKey,
                                    is_remote: isRemote,
                                    branch_label: bLabel,
                                    bookable: Boolean(data.staff.bookable),
                                    user_id: Number(this.editingUserId),
                                    preview_url: data.staff.preview_url || `/staff-portal-preview/${data.staff.id}`,
                                });
                                this.staffVersion++;
                                const newSid = String(data.staff.id);
                                this.linkedStaffId = newSid;
                                this.$nextTick(() => {
                                    this.linkedStaffId = newSid;
                                    const sel = document.getElementById('maStaffId');
                                    if (sel) sel.value = newSid;
                                });
                                if (typeof showStaffToast === 'function') showStaffToast(data.message);
                            } else {
                                this.showError('manageAccessError', data.message || 'Could not create Staff HR profile.');
                            }
                        } catch (e) {
                            this.showError('manageAccessError', 'Network error while creating Staff HR profile.');
                        } finally {
                            this.creatingProfile = false;
                        }
                    },
                };
            });
        }

        if (window.Alpine) {
            registerUnifiedAccessAlpine();
        } else {
            document.addEventListener('alpine:init', registerUnifiedAccessAlpine);
        }

        window.openManageAccessModal = function (userIdOrObj, focusLinkSection = false) {
            const userObj = (typeof userIdOrObj === 'object' && userIdOrObj)
                ? userIdOrObj
                : usersById[String(userIdOrObj)];
            if (!userObj || !window.__laleenManageAccessComponent) return;
            window.__laleenManageAccessComponent.hydrateForManage(userObj);
            const modalEl = document.getElementById('manageAccessModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            if (focusLinkSection) {
                setTimeout(() => {
                    document.getElementById('maLinkedProfileSection')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    document.getElementById('maStaffId')?.focus();
                }, 220);
            }
        };

        window.openAddLoginModal = function (opts = {}) {
            if (!window.__laleenAddLoginComponent) return;
            window.__laleenAddLoginComponent.hydrateForAdd(opts);
            const modalEl = document.getElementById('addLoginModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        };

        window.openQuickResetPasswordModal = function (userId, userName, userEmail) {
            const modalEl = document.getElementById('quickResetPwModal');
            if (!modalEl) return;
            document.getElementById('qrpUserId').value = String(userId);
            document.getElementById('qrpUserMeta').textContent = `${userName || ''} · ${userEmail || ''}`;
            const pwEl = document.getElementById('qrpPassword');
            pwEl.value = '';
            pwEl.type = 'password';
            document.getElementById('qrpError')?.classList.add('d-none');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        };

        document.getElementById('qrpGenBtn')?.addEventListener('click', function () {
            const pwEl = document.getElementById('qrpPassword');
            if (!pwEl) return;
            pwEl.value = genStrongPassword();
            pwEl.type = 'text';
            pwEl.focus();
            pwEl.select();
        });

        document.getElementById('qrpToggleBtn')?.addEventListener('click', function () {
            const pwEl = document.getElementById('qrpPassword');
            if (!pwEl) return;
            pwEl.type = pwEl.type === 'text' ? 'password' : 'text';
        });

        document.getElementById('quickResetPwForm')?.addEventListener('submit', async function (e) {
            e.preventDefault();
            const uid = document.getElementById('qrpUserId').value;
            const errEl = document.getElementById('qrpError');
            const btn = document.getElementById('qrpSubmitBtn');
            errEl?.classList.add('d-none');
            btn.disabled = true;
            btn.textContent = 'Updating…';
            try {
                const res = await fetch(`/users/${uid}/reset-password`, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ password: document.getElementById('qrpPassword').value }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('quickResetPwModal'))?.hide();
                    if (typeof showStaffToast === 'function') {
                        showStaffToast(data.message || 'Password updated.');
                    } else {
                        window.location.reload();
                    }
                } else {
                    const msg = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Could not reset password.');
                    if (errEl) { errEl.textContent = msg; errEl.classList.remove('d-none'); }
                }
            } catch (err) {
                if (errEl) { errEl.textContent = 'Network error. Please try again.'; errEl.classList.remove('d-none'); }
            } finally {
                btn.disabled = false;
                btn.textContent = 'Update Password';
            }
        });
    })();
</script>
