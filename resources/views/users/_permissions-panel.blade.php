{{--
    Per-module permission panel, shared by the Add Staff and Edit Staff modals.
    Expects:
    - $uid: a unique suffix for input ids (e.g. 'new' or the user's id)
    - $permissions: array of module => 'none'|'view'|'edit' (defaults to 'none')
--}}
@php($permissions = $permissions ?? [])
<div class="permission-panel">
    <label class="form-label fw-bold mb-1">Module Permissions</label>
    <p class="text-muted small mb-2">
        Admins always have full access to every module. For other roles, choose what each module can do —
        <strong>No Access</strong> (hidden entirely), <strong>View Only</strong> (browse, no changes), or
        <strong>Full Edit</strong> (create, update, delete).
    </p>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Module</th>
                    <th class="text-center">No Access</th>
                    <th class="text-center">View Only</th>
                    <th class="text-center">Full Edit</th>
                </tr>
            </thead>
            <tbody>
                @foreach (config('modules') as $slug => $label)
                    @php($current = $permissions[$slug] ?? 'none')
                    <tr>
                        <td>{{ $label }}</td>
                        @foreach (['none' => 'No Access', 'view' => 'View Only', 'edit' => 'Full Edit'] as $level => $levelLabel)
                            <td class="text-center">
                                <input
                                    type="radio"
                                    class="form-check-input"
                                    name="permissions[{{ $slug }}]"
                                    id="perm_{{ $slug }}_{{ $level }}_{{ $uid }}"
                                    value="{{ $level }}"
                                    {{ $current === $level ? 'checked' : '' }}
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
