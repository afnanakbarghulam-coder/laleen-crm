<!doctype html>
<html lang="en" class="layout-wide" 
      data-assets-path="{{ asset('design/sneat-admin-template/assets/') }}/"
      data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
    <title>404 - Page Not Found | Laleen Ops </title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('design/sneat-admin-template/assets/img/favicon/favicon.ico') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/fonts/iconify-icons.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/css/theme-default.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/css/demo.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/css/pages/page-misc.css') }}" />

    <!-- Helpers -->
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/js/config.js') }}"></script>
</head>

<body>
    <!-- Content -->
    <div class="container-xxl container-p-y">
        <div class="misc-wrapper text-center">
            <h1 class="mb-2" style="font-size: 6rem;">404</h1>
            <h4 class="mb-2">Page Not Found ⚠️</h4>
            <p class="mb-4">We couldn’t find the page you were looking for.</p>
            <a href="{{ url('/') }}" class="btn btn-primary">Back to Home</a>
            <div class="mt-4">
                <img src="{{ asset('design/sneat-admin-template/assets/img/illustrations/page-misc-error-light.png') }}" 
                     alt="Error Image" width="500" class="img-fluid" />
            </div>
        </div>
    </div>

    <!-- Core JS -->
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/js/main.js') }}"></script>
</body>
</html>
