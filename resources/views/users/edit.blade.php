<!-- Edit User Modal -->
@php
    $editRoles = \App\Models\User::ROLES;
    $effectivePerms = $user->effectivePermissionsMap();
@endphp
<div class="modal fade sa-modal" id="editUserModal{{ $user->id }}" tabindex="-1"
    aria-labelledby="editUserModalLabel{{ $user->id }}" aria-hidden="true"
    x-data="staffAccessUserForm('{{ $user->id }}')">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="editUserModalLabel{{ $user->id }}" style="color: #f8efe6;">Edit Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('users.update', $user->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name{{ $user->id }}" class="form-label">Name</label>
                            <input type="text" id="name{{ $user->id }}" name="name"
                                class="form-control"
                                value="{{ $user->name }}" required>
                        </div>

                        <div class="col-md-6">
                            <label for="email{{ $user->id }}" class="form-label">Email</label>
                            <input type="email" id="email{{ $user->id }}" name="email"
                                class="form-control"
                                value="{{ $user->email }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <div class="sa-cselect-wrap" :class="{ 'is-open': roleMenuOpen }" @click.outside="roleMenuOpen = false">
                                <select id="role{{ $user->id }}" name="role" class="d-none" x-model="role">
                                    @foreach ($editRoles as $rKey => $rLabel)
                                        <option value="{{ $rKey }}" {{ $user->role === $rKey ? 'selected' : '' }}>{{ $rLabel }}</option>
                                    @endforeach
                                </select>

                                <button type="button"
                                    class="sa-cselect-trigger"
                                    id="editUserRoleTrigger{{ $user->id }}"
                                    @click="roleMenuOpen = !roleMenuOpen"
                                    :aria-expanded="roleMenuOpen">
                                    <span class="sa-cselect-label" x-text="roleLabel(role)">{{ $user->roleBadgeLabel() }}</span>
                                    <span class="sa-cselect-caret">▾</span>
                                </button>

                                <div class="sa-cselect-menu z-50" x-show="roleMenuOpen" x-cloak>
                                    @foreach ($editRoles as $rKey => $rLabel)
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
                            <label for="profile_photo{{ $user->id }}" class="form-label">Profile Photo</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="file" id="profile_photo{{ $user->id }}" name="profile_photo"
                                    class="form-control" accept="image/*">
                                @if ($user->profile_photo)
                                    <img src="{{ asset($user->profile_photo) }}" alt="Profile" class="rounded-circle flex-shrink-0"
                                        width="38" height="38" style="object-fit: cover; border: 1px solid rgba(229, 184, 168, 0.3);">
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="password{{ $user->id }}" class="form-label">New Password</label>
                            <input type="password" id="password{{ $user->id }}" name="password" class="form-control"
                                placeholder="Leave blank to keep current" autocomplete="new-password">
                        </div>

                        <div class="col-md-6">
                            <label for="password_confirmation{{ $user->id }}" class="form-label">Confirm Password</label>
                            <input type="password" id="password_confirmation{{ $user->id }}"
                                name="password_confirmation" class="form-control" placeholder="Confirm new password" autocomplete="new-password">
                        </div>

                        <div class="col-12 pt-2" style="border-top: 1px solid rgba(255, 255, 255, 0.06);">
                            @include('users._permissions-panel', ['uid' => $user->id, 'permissions' => $effectivePerms])
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sa-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="sa-btn-rosegold">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>
