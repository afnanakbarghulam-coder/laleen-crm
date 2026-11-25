<!doctype html>
<html lang="en" class="layout-wide customizer-hide"
    data-assets-path="{{ asset('design/sneat-admin-template/assets') }}/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Register - Laleen Ops </title>

    <link rel="icon" type="image/x-icon"
        href="{{ asset('design/sneat-admin-template/assets/img/favicon/favicon.ico') }}" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;900&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />

    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/fonts/iconify-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/css/demo.css') }}" />
    <link rel="stylesheet"
        href="{{ asset('design/sneat-admin-template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/css/pages/page-auth.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/luxury-theme.css') }}?v={{ filemtime(public_path('css/luxury-theme.css')) }}" />

    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/js/config.js') }}"></script>
</head>

<body>
    <div class="luxe-bg" aria-hidden="true">
        <div class="luxe-orb luxe-orb-1"></div>
        <div class="luxe-orb luxe-orb-2"></div>
        <div class="luxe-orb luxe-orb-3"></div>
        <div class="luxe-orb luxe-orb-4"></div>
        <div class="luxe-orb luxe-orb-5"></div>
        <div class="luxe-shimmer"></div>
    </div>

    <div class="container-xxl">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner">
                <!-- Register Card -->
                <div class="card px-sm-6 px-0">
                    <div class="card-body">
                        <div class="app-brand justify-content-center mb-6">
                            <a href="/" class="app-brand-link gap-2">
                                <span class="app-brand-text demo text-heading fw-bold">Laleen Ops </span>
                            </a>
                        </div>

                        <h4 class="mb-1">Adventure starts here 🚀</h4>
                        <p class="mb-6">Create your account below</p>

                        <!-- Laravel Fortify Register Form -->
                        <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-6">
                                <label for="name" class="form-label">Name</label>
                                <input id="name" type="text" name="name"
                                    class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name') }}" required autofocus>
                                @error('name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="mb-6">
                                <label for="email" class="form-label">Email</label>
                                <input id="email" type="email" name="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    value="{{ old('email') }}" required>
                                @error('email')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="mb-6">
                                <label class="form-label" for="password">Password</label>
                                <div class="luxe-password-wrap">
                                    <input type="password" id="password" name="password"
                                        class="form-control luxe-password-input @error('password') is-invalid @enderror" required
                                        autocomplete="new-password">
                                    <button type="button"
                                            class="luxe-password-toggle js-auth-password-toggle"
                                            data-target="password"
                                            aria-label="Show password"
                                            aria-pressed="false"
                                            title="Show password">
                                        <svg class="js-icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
                                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
                                            <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                                            <line x1="1" y1="1" x2="23" y2="23"></line>
                                        </svg>
                                        <svg class="js-icon-eye d-none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                                @error('password')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="mb-6">
                                <label class="form-label" for="password_confirmation">Confirm Password</label>
                                <div class="luxe-password-wrap">
                                    <input type="password" id="password_confirmation" name="password_confirmation"
                                        class="form-control luxe-password-input" required autocomplete="new-password">
                                    <button type="button"
                                            class="luxe-password-toggle js-auth-password-toggle"
                                            data-target="password_confirmation"
                                            aria-label="Show confirm password"
                                            aria-pressed="false"
                                            title="Show password">
                                        <svg class="js-icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"></path>
                                            <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"></path>
                                            <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                                            <line x1="1" y1="1" x2="23" y2="23"></line>
                                        </svg>
                                        <svg class="js-icon-eye d-none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {{-- <div class="mb-6">
                                <label for="role" class="form-label">Role</label>
                                <select id="role" name="role"
                                    class="form-select @error('role') is-invalid @enderror" required>
                                    <option value="user" {{ old('role') == 'user' ? 'selected' : '' }}>User</option>
                                    <option value="agent" {{ old('role') == 'agent' ? 'selected' : '' }}>Agent
                                    </option>
                                    <option value="staff" {{ old('role') == 'staff' ? 'selected' : '' }}>Staff
                                    </option>
                                    <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin
                                    </option>
                                </select>
                                @error('role')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div> --}}

                            <div class="mb-6">
                                <label for="profile_photo" class="form-label">Profile Photo</label>
                                <input type="file" id="profile_photo" name="profile_photo"
                                    class="form-control @error('profile_photo') is-invalid @enderror" accept="image/*">
                                @error('profile_photo')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            {{-- <div class="my-7">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="terms-conditions" required>
                                    <label class="form-check-label" for="terms-conditions">
                                        I agree to <a href="#">privacy policy & terms</a>
                                    </label>
                                </div>
                            </div> --}}

                            <button class="btn btn-primary d-grid w-100" type="submit">Sign Up</button>
                        </form>

                        <p class="text-center mt-4">
                            <span>Already have an account?</span>
                            <a href="{{ route('login') }}">
                                <span>Sign in instead</span>
                            </a>
                        </p>
                    </div>
                </div>
                <!-- /Register Card -->
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}">
    </script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/js/main.js') }}"></script>
    <script>
        document.querySelectorAll('.js-auth-password-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const input = document.getElementById(btn.dataset.target);
                if (!input) return;
                const showing = input.type === 'password';
                input.type = showing ? 'text' : 'password';
                btn.setAttribute('aria-pressed', showing ? 'true' : 'false');
                btn.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
                btn.setAttribute('title', showing ? 'Hide password' : 'Show password');
                btn.querySelector('.js-icon-eye-off')?.classList.toggle('d-none', showing);
                btn.querySelector('.js-icon-eye')?.classList.toggle('d-none', !showing);
            });
        });
    </script>
</body>

</html>
