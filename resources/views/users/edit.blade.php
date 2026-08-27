<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal{{ $user->id }}" tabindex="-1"
    aria-labelledby="editUserModalLabel{{ $user->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel{{ $user->id }}">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('users.update', $user->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name{{ $user->id }}" class="form-label">Name</label>
                        <input type="text" id="name{{ $user->id }}" name="name"
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $user->name) }}" required>
                        @error('name')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email{{ $user->id }}" class="form-label">Email</label>
                        <input type="email" id="email{{ $user->id }}" name="email"
                            class="form-control @error('email') is-invalid @enderror"
                            value="{{ old('email', $user->email) }}" required>
                        @error('email')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="role{{ $user->id }}" class="form-label">Role</label>
                        <select id="role{{ $user->id }}" name="role"
                            class="form-select @error('role') is-invalid @enderror" required>
                            <option value="admin" {{ $user->role == 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="manager" {{ $user->role == 'manager' ? 'selected' : '' }}>Manager</option>
                            <option value="agent" {{ $user->role == 'agent' ? 'selected' : '' }}>Agent</option>
                            <option value="staff" {{ $user->role == 'staff' ? 'selected' : '' }}>Staff</option>
                            <option value="user" {{ $user->role == 'user' ? 'selected' : '' }}>User</option>
                        </select>
                        @error('role')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="profile_photo{{ $user->id }}" class="form-label">Profile Photo</label>
                        <input type="file" id="profile_photo{{ $user->id }}" name="profile_photo"
                            class="form-control @error('profile_photo') is-invalid @enderror" accept="image/*">
                        @if ($user->profile_photo)
                            <img src="{{ asset($user->profile_photo) }}" alt="Profile" class="rounded-circle mt-2"
                                width="40" height="40">
                        @endif
                        @error('profile_photo')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password{{ $user->id }}" class="form-label">Password <small
                                class="text-muted">(Leave blank to keep current)</small></label>
                        <input type="password" id="password{{ $user->id }}" name="password" class="form-control"
                            autocomplete="new-password">
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation{{ $user->id }}" class="form-label">Confirm
                            Password</label>
                        <input type="password" id="password_confirmation{{ $user->id }}"
                            name="password_confirmation" class="form-control" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>
