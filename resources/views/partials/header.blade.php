<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
    id="layout-navbar">

    <!-- Menu toggle (mobile) -->
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-4" href="#">
            <i class="bx bx-menu bx-sm"></i>
        </a>
    </div>

    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">

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
