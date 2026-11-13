<!-- Add User Modal -->
@php
    $createRoles = \App\Models\User::ROLES;
    $createDefaultPerms = \App\Models\User::defaultPermissionsForRole('user');
@endphp
<div class="modal fade sa-modal" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true" x-data="staffAccessUserForm('new')">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="addUserModalLabel" style="color: #f8efe6;">Add Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Temporary Password</label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <div class="sa-cselect-wrap" :class="{ 'is-open': roleMenuOpen }" @click.outside="roleMenuOpen = false">
                                <select name="role" id="createUserRoleSelect" class="d-none" x-model="role">
                                    @foreach ($createRoles as $rKey => $rLabel)
                                        <option value="{{ $rKey }}" {{ $rKey === 'user' ? 'selected' : '' }}>{{ $rLabel }}</option>
                                    @endforeach
                                </select>

                                <button type="button"
                                    class="sa-cselect-trigger"
                                    id="createUserRoleTrigger"
                                    @click="roleMenuOpen = !roleMenuOpen"
                                    :aria-expanded="roleMenuOpen">
                                    <span class="sa-cselect-label" x-text="roleLabel(role)">User</span>
                                    <span class="sa-cselect-caret">▾</span>
                                </button>

                                <div class="sa-cselect-menu z-50" x-show="roleMenuOpen" x-cloak>
                                    @foreach ($createRoles as $rKey => $rLabel)
                                        <button type="button"
                                            class="sa-cselect-option"
                                            :class="{ 'is-selected': role === '{{ $rKey }}' }"
                                            @click="selectRole('{{ $rKey }}')">
                                            <span>{{ $rLabel }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/*">
                        </div>

                        <div class="col-12 pt-2" style="border-top: 1px solid rgba(255, 255, 255, 0.06);">
                            @include('users._permissions-panel', ['uid' => 'new', 'permissions' => $createDefaultPerms])
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sa-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn-rosegold">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>
