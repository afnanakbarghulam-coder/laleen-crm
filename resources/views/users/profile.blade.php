<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @php $user = auth()->user(); @endphp

                <div class="modal-body text-center">

                    <div class="mb-3">
                        @if ($user?->profile_photo)
                            <img src="{{ asset($user->profile_photo) }}"
                                 class="rounded-circle mb-3" width="120" height="120">
                        @else
                            <i class="bx bx-user" style="font-size:100px"></i>
                        @endif
                    </div>

                    <input type="text" name="name"
                        value="{{ old('name', $user?->name) }}"
                        class="form-control mb-3" required>

                    <input type="email" name="email"
                        value="{{ old('email', $user?->email) }}"
                        class="form-control mb-3" required>

                    <input type="password" name="password"
                        class="form-control mb-3">

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary">Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>
