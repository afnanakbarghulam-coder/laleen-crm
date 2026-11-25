<!doctype html>
<html lang="en" class="layout-wide customizer-hide"
      data-assets-path="{{ asset('design/sneat-admin-template/assets') }}/"
      data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>Login - Laleen Ops </title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon"
          href="{{ asset('design/sneat-admin-template/assets/img/favicon/favicon.ico') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;900&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/css/pages/page-auth.css') }}" />

    <!-- Luxury Theme -->
    <link rel="stylesheet" href="{{ asset('css/luxury-theme.css') }}?v={{ filemtime(public_path('css/luxury-theme.css')) }}" />

    <!-- Helpers -->
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
                <div class="card px-sm-6 px-0">
                    <div class="card-body">

                        <!-- Logo -->
                        <div class="app-brand justify-content-center">
                            <a href="#" class="app-brand-link gap-2">
                                <span class="app-brand-text demo text-heading fw-bold">Laleen Ops</span>
                            </a>
                        </div>

                        <h4 class="mb-1">Welcome Back! 👋</h4>
                        <p class="mb-6">Sign in to Laleen Ops &amp; Staff Portal</p>

                        <!-- Laravel Fortify Login Form -->
                        <form method="POST" action="{{ route('login') }}" id="formAuthentication">
                            @csrf

                            @if ($errors->any())
                                <div class="alert alert-danger mb-3">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="mb-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email"
                                       class="form-control" placeholder="Enter your email"
                                       value="{{ old('email') }}" autocomplete="email" required autofocus />
                            </div>

                            <div class="mb-6">
                                <label class="form-label" for="password">Password</label>
                                <div class="luxe-password-wrap">
                                    <input type="password" id="password" name="password"
                                           class="form-control luxe-password-input" placeholder="••••••••"
                                           autocomplete="current-password" required />
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
                            </div>

                            <div class="mb-8 d-flex justify-content-between align-items-center">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember-me" />
                                    <label class="form-check-label" for="remember-me">Remember Me</label>
                                </div>
                                {{-- <a href="{{ route('password.request') }}">
                                    <span>Forgot Password?</span>
                                </a> --}}
                            </div>

                            <div class="mb-6">
                                <button class="btn btn-primary d-grid w-100" type="submit">Login</button>
                            </div>
                        </form>

                        {{-- <p class="text-center">
                            <span>New to the platform?</span>
                            <a href="{{ route('register') }}">
                                <span>Create an account</span>
                            </a>
                        </p> --}}

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS Files -->
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
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
