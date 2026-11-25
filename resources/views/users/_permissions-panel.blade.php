{{--
    14-Module Permission Matrix shared by Add Login and Manage Role & Access modals.
    Expects:
    - $uid: unique suffix for input ids ('new' or 'manage')
    - $permissions: array of module => 'none'|'view'|'edit'
--}}
@php
    $permissions = $permissions ?? [];
    $modulesList = \App\Models\User::MODULES;
@endphp

<div class="permission-panel">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
        <div class="d-flex align-items-center gap-2">
            <label class="form-label fw-bold mb-0" style="color: #f8efe6; font-size: 13.5px;">Module Permissions</label>
            <span class="sa-inline-chip">{{ count($modulesList) }} Modules</span>
        </div>
        <button type="button" class="sa-preset-btn" @click="applyRolePreset()" x-show="role !== 'salon_staff'">
            Reset to Role Preset
        </button>
    </div>

    <p class="small mb-2" style="color: #b8a29a;" x-show="role !== 'admin' && role !== 'salon_staff'">
        Choose access per module &mdash; <strong style="color: #f8efe6;">No Access</strong> (hidden + 403),
        <strong style="color: #f8efe6;">View Only</strong> (read-only), or
        <strong style="color: #f8efe6;">Full Edit</strong> (create, update &amp; delete).
    </p>
    <p class="small mb-2" style="color: #6ee7b7;" x-show="role === 'admin'" x-cloak>
        &#10003; Admin accounts automatically hold Full Edit across all 14 modules.
    </p>
    <div class="small mb-2 px-3 py-2" x-show="role === 'salon_staff'" x-cloak
         style="border-radius: 10px; background: rgba(212, 163, 115, 0.12); border: 1px solid rgba(212, 163, 115, 0.34); color: #f3d9a4; font-weight: 600;">
        <i class="bx bx-lock-alt me-1"></i> Portal Only &mdash; /my-profile (All CRM modules blocked)
    </div>

    <div class="sa-perm-table-wrap" :style="role === 'salon_staff' ? 'opacity: 0.45; pointer-events: none;' : ''">
        <table class="table table-sm sa-perm-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Module</th>
                    <th class="text-center">No Access</th>
                    <th class="text-center">View Only</th>
                    <th class="text-center">Full Edit</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($modulesList as $slug => $label)
                    @php($current = \App\Models\User::normalizePermissionLevel($permissions[$slug] ?? 'none'))
                    <tr>
                        <td class="fw-medium" style="color: #f5ebe6;">{{ $label }}</td>
                        @foreach (['none' => 'No Access', 'view' => 'View Only', 'edit' => 'Full Edit'] as $level => $levelLabel)
                            <td class="text-center">
                                <input
                                    type="radio"
                                    class="form-check-input sa-radio"
                                    name="permissions[{{ $slug }}]"
                                    id="perm_{{ $slug }}_{{ $level }}_{{ $uid }}"
                                    value="{{ $level }}"
                                    {{ $current === $level ? 'checked' : '' }}
                                    :checked="role === 'salon_staff' ? ('{{ $level }}' === 'none') : (permissions['{{ $slug }}'] === '{{ $level }}')"
                                    :disabled="role === 'salon_staff'"
                                    @change="setModulePermission('{{ $slug }}', '{{ $level }}')"
                                    aria-label="{{ $label }} - {{ $levelLabel }}"
                                >
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
