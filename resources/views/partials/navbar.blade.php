<li class="nav-item navbar-dropdown dropdown-user dropdown">
    <a class="nav-link dropdown-toggle hide-arrow d-flex align-items-center" data-bs-toggle="dropdown">

        {{-- User Name --}}
        <span class="me-2 fw-semibold">
            {{ auth()->user()->name }}
        </span>

        {{-- User Avatar --}}
        <div class="avatar avatar-online">
            @include('partials.avatar', [
                'src' => auth()->user()->profile_photo,
                'name' => auth()->user()->name,
                'size' => 40,
            ])
        </div>

    </a>

    <ul class="dropdown-menu dropdown-menu-end">

        <li>
            <a class="dropdown-item" href="#">
                <div class="d-flex">
                    <div class="flex-shrink-0 me-3">
                        <div class="avatar avatar-online">
                            @include('partials.avatar', [
                                'src' => auth()->user()->profile_photo,
                                'name' => auth()->user()->name,
                                'size' => 40,
                            ])
                        </div>
                    </div>

                    <div class="flex-grow-1">
                        <h6 class="mb-0">{{ auth()->user()->name }}</h6>
                        <small class="text-body-secondary text-capitalize">
                            {{ auth()->user()->role }}
                        </small>
                    </div>
                </div>
            </a>
        </li>

        <li><div class="dropdown-divider my-1"></div></li>

        <li>
            <a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#profileModal">
                <i class="bx bx-user me-3"></i> My Profile
            </a>
        </li>

        <li>
            <a class="dropdown-item">
                <i class="bx bx-cog me-3"></i> Settings
            </a>
        </li>

        <li><div class="dropdown-divider my-1"></div></li>

        <li>
            <a class="dropdown-item" href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                <i class="bx bx-power-off me-3"></i> Log Out
            </a>

            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                @csrf
            </form>
        </li>

    </ul>
</li>
