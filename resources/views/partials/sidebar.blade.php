 {{-- <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
     <div class="app-brand demo">
         <a href="{{ route('dashboard') }}" class="app-brand-link">
             <span class="app-brand-logo demo mx-1">
                 <img src="{{ asset('images/laleen logo1.PNG') }}" alt="Logo" style="width: 56px; height: 56px">
             </span>
             <span class="app-brand-text demo menu-text">Laleen Ops</span>
         </a>

         <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
             <i class="bx bx-chevron-left d-block d-xl-none align-middle"></i>
         </a>
     </div>

     <div class="menu-divider mt-0"></div>
     <div class="menu-inner-shadow"></div>

     <ul class="menu-inner py-1">
         <!-- Dashboard -->
         <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
             <a href="{{ route('dashboard') }}" class="menu-link">
                 <i class="menu-icon tf-icons bx bx-home-smile"></i>
                 <div class="text-truncate" data-i18n="Dashboard">Dashboard</div>
             </a>
         </li>

         <!-- Leads -->
         <li class="menu-header small text-uppercase">
             <span class="menu-header-text">Leads</span>
         </li>

         <li class="menu-item {{ request()->routeIs('leads.index') ? 'active' : '' }}">
             <a href="{{ route('leads.index') }}" class="menu-link">
                 <i class="menu-icon tf-icons bx bx-user-plus"></i>
                 <div class="text-truncate">Leads</div>
             </a>
         </li>

         <li class="menu-header small text-uppercase">
             <span class="menu-header-text">Bookings</span>
         </li>

         <li class="menu-item {{ request()->routeIs('appointments.index') ? 'active' : '' }}">
             <a href="{{ route('appointments.index') }}" class="menu-link">
                 <i class="menu-icon tf-icons bx bx-calendar"></i>
                 <div class="text-truncate" data-i18n="Tables">Bookings</div>
             </a>
         </li>

         <li class="menu-item {{ request()->routeIs('appointments.revenue.*') ? 'active' : '' }}">
             <a href="{{ route('appointments.revenue.index') }}" class="menu-link">
                 <i class="menu-icon tf-icons bx bx-money"></i>
                 <div class="text-truncate" data-i18n="Tables">Revenue Report</div>
             </a>
         </li>

         <li class="menu-item {{ request()->routeIs('users.index') ? 'active' : '' }}">
             <a href="{{ route('users.index') }}" class="menu-link">
                 <i class="menu-icon tf-icons bx bx-group"></i>
                 <div class="text-truncate" data-i18n="Tables">Users</div>
             </a>
         </li>

         <li class="menu-item {{ request()->routeIs('staffs.index') ? 'active' : '' }}">
             <a href="{{ route('staffs.index') }}" class="menu-link">
                 <i class="menu-icon tf-icons bx bx-group"></i>
                 <div class="text-truncate" data-i18n="Tables">Staff Management</div>
             </a>
         </li>

         <li class="menu-item {{ request()->routeIs('appointments.calendar') ? 'active' : '' }}">
             <a href="{{ route('appointments.calendar') }}" class="menu-link">
                 <i class="menu-icon tf-icons bx bx-calendar-check"></i>
                 <div class="text-truncate" data-i18n="Tables">Enhanced Calendar</div>
             </a>
         </li>

         <li class="menu-item {{ request()->routeIs('services.index') ? 'active' : '' }}">
             <a href="{{ route('services.index') }}" class="menu-link">
                 <i class="menu-icon tf-icons bx bx-calendar-check"></i>
                 <div class="text-truncate" data-i18n="Tables">Services</div>
             </a>
         </li>

         <li class="menu-item {{ request()->routeIs('daily-target.index') ? 'active' : '' }}">
             <a href="{{ route('daily-target.index') }}" class="menu-link">
                 <i class="menu-icon tf-icons bx bx-target-lock"></i>
                 <div class="text-truncate" data-i18n="Tables">Daily Target Tracker</div>
             </a>
         </li>

     </ul>
 </aside> --}}








 <aside id="layout-menu" class="layout-menu menu-vertical">

     <style>
         /* ===== SIDEBAR MODERN DESIGN ===== */

         #layout-menu {
             background: #111827;
             color: #cbd5e1;
             width: 260px;
             border-right: 1px solid rgba(255, 255, 255, 0.05);
         }

         #layout-menu .app-brand {
             padding: 20px 18px;
             border-bottom: 1px solid rgba(255, 255, 255, 0.05);
         }

         #layout-menu .app-brand-text {
             color: #ffffff;
             font-weight: 600;
             font-size: 18px;
         }

         #layout-menu .menu-inner {
             padding: 15px 10px;
         }

         #layout-menu .menu-header-text {
             color: #6b7280;
             font-size: 11px;
             letter-spacing: 1px;
         }

         #layout-menu .menu-link {
             border-radius: 10px;
             margin: 3px 0;
             padding: 10px 14px;
             transition: all 0.25s ease;
             color: #cbd5e1;
         }

         #layout-menu .menu-link i {
             font-size: 18px;
         }

         #layout-menu .menu-link:hover {
             background: rgba(255, 255, 255, 0.06);
             color: #ffffff;
             transform: translateX(4px);
         }

         /* ACTIVE ITEM */
         #layout-menu .menu-item.active>.menu-link {
             background: linear-gradient(135deg, #2563eb, #3b82f6);
             color: #ffffff;
             box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35);
         }

         #layout-menu .menu-item.active i {
             color: #ffffff;
         }

         .menu-divider {
             border-top: 1px solid rgba(255, 255, 255, 0.05);
         }

         .layout-menu-toggle {
             color: #ffffff;
         }
     </style>

     <div class="app-brand d-flex align-items-center">
         <a href="{{ route('dashboard') }}" class="app-brand-link d-flex align-items-center text-decoration-none">
             <img src="{{ asset('images/laleen logo1.PNG') }}" alt="Logo"
                 style="width: 46px; height: 46px; border-radius: 8px;">
             <span class="app-brand-text ms-2">Laleen Ops</span>
         </a>

         <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
             <i class="bx bx-chevron-left"></i>
         </a>
     </div>

     <div class="menu-divider mt-0"></div>
     <div class="menu-inner-shadow"></div>

     <ul class="menu-inner py-1">

         <!-- Dashboard -->
         <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
             <a href="{{ route('dashboard') }}" class="menu-link">
                 <i class="bx bx-home-smile me-2"></i>
                 <div>Dashboard</div>
             </a>
         </li>

         <!-- Leads -->
         {{-- <li class="menu-header small text-uppercase mt-3">
             <span class="menu-header-text">Leads</span>
         </li> --}}

         <li class="menu-item {{ request()->routeIs('leads.index') ? 'active' : '' }}">
             <a href="{{ route('leads.index') }}" class="menu-link">
                 <i class="bx bx-user-plus me-2"></i>
                 <div>Leads</div>
             </a>
         </li>

         <!-- Bookings -->
         {{-- <li class="menu-header small text-uppercase mt-3">
             <span class="menu-header-text">Bookings</span>
         </li> --}}

         <li class="menu-item {{ request()->routeIs('appointments.index') ? 'active' : '' }}">
             <a href="{{ route('appointments.index') }}" class="menu-link">
                 <i class="bx bx-calendar me-2"></i>
                 <div>Bookings</div>
             </a>
         </li>

         <li class="menu-item {{ request()->routeIs('appointments.revenue.*') ? 'active' : '' }}">
             <a href="{{ route('appointments.revenue.index') }}" class="menu-link">
                 <i class="bx bx-money me-2"></i>
                 <div>Revenue Report</div>
             </a>
         </li>

         <!-- Clients -->
         <li class="menu-item {{ request()->routeIs('customers.*') ? 'active' : '' }}">
             <a href="{{ route('customers.index') }}" class="menu-link">
                 <i class="bx bx-id-card me-2"></i>
                 <div>Clients</div>
             </a>
         </li>

         <!-- Users -->
         @if (auth()->user()->role === 'admin')
             <li class="menu-item {{ request()->routeIs('users.index') ? 'active' : '' }}">
                 <a href="{{ route('users.index') }}" class="menu-link">
                     <i class="bx bx-group me-2"></i>
                     <div>Users</div>
                 </a>
             </li>
         @endif

         <!-- Staff -->
         <li class="menu-item {{ request()->routeIs('staffs.index') ? 'active' : '' }}">
             <a href="{{ route('staffs.index') }}" class="menu-link">
                 <i class="bx bx-user me-2"></i>
                 <div>Staff Management</div>
             </a>
         </li>

         <!-- Calendar -->
         <li class="menu-item {{ request()->routeIs('appointments.calendar') ? 'active' : '' }}">
             <a href="{{ route('appointments.calendar') }}" class="menu-link">
                 <i class="bx bx-calendar-check me-2"></i>
                 <div>Enhanced Calendar</div>
             </a>
         </li>

         <!-- Services -->
         <li class="menu-item {{ request()->routeIs('services.index') ? 'active' : '' }}">
             <a href="{{ route('services.index') }}" class="menu-link">
                 <i class="bx bx-briefcase me-2"></i>
                 <div>Services</div>
             </a>
         </li>

         <!-- Products -->
         <li class="menu-item {{ request()->routeIs('products.index') ? 'active' : '' }}">
             <a href="{{ route('products.index') }}" class="menu-link">
                 <i class="bx bx-package me-2"></i>
                 <div>Products</div>
             </a>
         </li>

         <!-- Daily Target -->
         <li class="menu-item {{ request()->routeIs('daily-target.index') ? 'active' : '' }}">
             <a href="{{ route('daily-target.index') }}" class="menu-link">
                 <i class="bx bx-target-lock me-2"></i>
                 <div>Daily Target Tracker</div>
             </a>
         </li>

     </ul>
 </aside>
