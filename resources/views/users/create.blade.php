<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                            <select name="role" class="form-select" required>
                                <option value="user">User</option>
                                <option value="staff">Staff</option>
                                <option value="agent">Agent</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/*">
                        </div>

                        <div class="col-12">
                            <hr>
                            @include('users._permissions-panel', ['uid' => 'new', 'permissions' => []])
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>
