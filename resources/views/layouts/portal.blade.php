<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="{{ asset('images/laleen logo1.PNG') }}">
    <title>@yield('title', 'My Profile — Laleen Staff Portal')</title>

    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/css/core.css') }}">
    <link rel="stylesheet" href="{{ asset('css/luxury-theme.css') }}?v={{ filemtime(public_path('css/luxury-theme.css')) }}">

    <style>
        body.laleen-portal-body {
            min-height: 100vh;
            background: radial-gradient(circle at top center, #26181c 0%, #140d0f 55%, #0e090a 100%);
            color: #f8efe6;
            font-family: 'Poppins', sans-serif;
            margin: 0;
        }

        .portal-topbar {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgba(20, 13, 15, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(229, 184, 168, 0.14);
        }

        .portal-topbar-inner {
            max-width: 960px;
            margin: 0 auto;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .portal-brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .portal-brand img {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            object-fit: cover;
        }

        .portal-brand-text {
            font-family: 'Playfair Display', serif;
            font-size: 17px;
            font-weight: 700;
            color: #e5b8a8;
            line-height: 1.1;
        }

        .portal-brand-sub {
            display: block;
            font-family: 'Poppins', sans-serif;
            font-size: 10.5px;
            font-weight: 500;
            letter-spacing: 0.04em;
            color: #9e8780;
        }

        .portal-logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid rgba(253, 164, 175, 0.3);
            background: rgba(253, 164, 175, 0.08);
            color: #fda4af;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .portal-logout-btn:hover {
            background: rgba(253, 164, 175, 0.16);
            border-color: rgba(253, 164, 175, 0.52);
            color: #ffe4e8;
        }

        .portal-main {
            max-width: 960px;
            margin: 0 auto;
            padding: 22px 16px 48px;
        }
    </style>
</head>
<body class="laleen-portal-body">
    <header class="portal-topbar">
        <div class="portal-topbar-inner">
            <a href="{{ route('portal.profile') }}" class="portal-brand">
                <img src="{{ asset('images/laleen logo1.PNG') }}" alt="Laleen Logo">
                <div>
                    <span class="portal-brand-text">Laleen</span>
                    <span class="portal-brand-sub">Staff Portal</span>
                </div>
            </a>

            <div class="d-flex align-items-center gap-2">
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="portal-logout-btn">
                        <i class="bx bx-log-out"></i>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="portal-main">
        @include('partials.flash')
        @yield('content')
    </main>

    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/bootstrap.js') }}"></script>
    @yield('scripts')
</body>
</html>
