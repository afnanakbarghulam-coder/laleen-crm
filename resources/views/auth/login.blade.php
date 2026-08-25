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
        href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/css/pages/page-auth.css') }}" />

    <!-- Helpers -->
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/js/config.js') }}"></script>
</head>

<body>
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
                        <p class="mb-6">Sign in to manage your salon</p>

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
                                       value="{{ old('email') }}" required autofocus />
                            </div>

                            <div class="mb-6 form-password-toggle">
                                <label class="form-label" for="password">Password</label>
                                <div class="input-group input-group-merge">
                                    <input type="password" id="password" name="password"
                                           class="form-control" placeholder="••••••••" required />
                                    <span class="input-group-text cursor-pointer">
                                        <i class="icon-base bx bx-hide"></i>
                                    </span>
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
</body>
</html>
