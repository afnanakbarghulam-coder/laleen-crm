<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
    id="layout-navbar">

    <!-- Menu toggle (mobile) -->
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-4" href="#">
            <i class="bx bx-menu bx-sm"></i>
        </a>
    </div>

    @php
        // Back button is clamped per-module: from any sub-page it jumps straight to
        // that module's index/root page; from the root page itself it steps up to
        // the module's parent (a KPI report's hub, or the dashboard). Computed as an
        // explicit target URL (not history.back()) so it never depends on how the
        // user actually navigated here.
        $routeName = request()->route() ? request()->route()->getName() : '';
        $dashboardUrl = route('dashboard');
        $backTarget = $dashboardUrl;

        if (str_starts_with($routeName, 'kpi.')) {
            $seg = explode('.', $routeName)[1] ?? 'hub';
            if ($seg === 'hub') {
                $backTarget = $dashboardUrl;
            } else {
                $rootUrl = route("kpi.$seg.index");
                $backTarget = $routeName === "kpi.$seg.index" ? route('kpi.hub') : $rootUrl;
            }
        } elseif (str_starts_with($routeName, 'appointments.revenue')) {
            $backTarget = $routeName === 'appointments.revenue.index' ? $dashboardUrl : route('appointments.revenue.index');
        } elseif ($routeName === 'appointments.calendar') {
            $backTarget = $dashboardUrl;
        } elseif (str_starts_with($routeName, 'appointments') || str_starts_with($routeName, 'sales') || str_starts_with($routeName, 'staff-blocks')) {
            $backTarget = $routeName === 'appointments.index' ? $dashboardUrl : route('appointments.index');
        } elseif (str_starts_with($routeName, 'leads')) {
            $backTarget = $routeName === 'leads.index' ? $dashboardUrl : route('leads.index');
        } elseif (str_starts_with($routeName, 'customers')) {
            $backTarget = $routeName === 'customers.index' ? $dashboardUrl : route('customers.index');
        } elseif (str_starts_with($routeName, 'users')) {
            $backTarget = $routeName === 'users.index' ? $dashboardUrl : route('users.index');
        } elseif (str_starts_with($routeName, 'staffs') || str_starts_with($routeName, 'shifts')) {
            $backTarget = $routeName === 'staffs.index' ? $dashboardUrl : route('staffs.index');
        } elseif (str_starts_with($routeName, 'services') || str_starts_with($routeName, 'service-categories')) {
            $backTarget = $routeName === 'services.index' ? $dashboardUrl : route('services.index');
        } elseif (str_starts_with($routeName, 'products')) {
            $backTarget = $routeName === 'products.index' ? $dashboardUrl : route('products.index');
        }
    @endphp

    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">

        @unless ($routeName === 'dashboard')
            <!-- Back (clamped to the current module's first/index page) -->
            <a href="{{ $backTarget }}" class="navbar-back-btn me-3" title="Go back">
                <i class="bx bx-arrow-back"></i>
            </a>
        @endunless

        <!-- Search (optional) -->
        {{-- <div class="navbar-nav align-items-center">
            <span class="fw-bold">Dashboard</span>
        </div> --}}

        <div class="navbar-nav align-items-center">
            {{-- <span class="fw-bold"> @yield('title') </span> --}}
        </div>

        <ul class="navbar-nav flex-row align-items-center ms-auto">

            {{-- If user NOT logged in --}}
            @guest
                <li class="nav-item">
                    <a href="{{ route('login') }}" class="nav-link fw-bold">
                        <i class="bx bx-log-in-circle me-1"></i> Login
                    </a>
                </li>

                <li class="nav-item ms-3">
                    <a href="{{ route('register') }}" class="nav-link fw-bold">
                        <i class="bx bx-user-plus me-1"></i> Register
                    </a>
                </li>
            @endguest


            {{-- If user IS logged in --}}
            @auth
                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                   <a class="nav-link dropdown-toggle hide-arrow d-flex align-items-center" data-bs-toggle="dropdown">
                        {{-- User Name --}}
                        <span class="me-2 fw-semibold"> {{ auth()->user()->name }}</span>

                        @if(auth()->user()->profile_photo)
                            <div class="avatar avatar-online">
                                <img src="{{ asset(auth()->user()->profile_photo) }}"
                                    alt="Profile" class="w-px-40 rounded-circle">
                            </div>
                        @else
                            <div class="avatar avatar-online d-flex justify-content-center align-items-center bg-light rounded-circle"
                                style="width: 40px; height: 40px;">
                                <i class="bx bx-user fs-3 text-secondary"></i>
                            </div>
                        @endif

                    </a>



                    <ul class="dropdown-menu dropdown-menu-end">
                        {{-- <li>
                            <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                <i class="bx bx-user me-2"></i> My Profile
                            </a>
                        </li> --}}

                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="dropdown-item text-danger">
                                    <i class="bx bx-log-out me-2"></i> Logout
                                </button>
                            </form>
                        </li>
                    </ul>
                </li>
            @endauth

        </ul>

    </div>

</nav>
