<!-- Profile Modal -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @php $user = auth()->user(); @endphp

                <div class="modal-body text-center">

                    <div class="mb-3 d-flex justify-content-center">
                        @include('partials.avatar', [
                            'src' => $user?->profile_photo,
                            'name' => $user?->name ?? 'User',
                            'size' => 104,
                        ])
                    </div>

                    <input type="text" name="name"
                        value="{{ old('name', $user?->name) }}"
                        class="form-control mb-3"
                        @if(!$user?->isAdmin()) readonly title="Account name can only be modified by an administrator" @endif
                        required>

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
