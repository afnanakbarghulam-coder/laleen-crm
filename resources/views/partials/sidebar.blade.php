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
                 <div class="text-truncate" data-i18n="Tables">Finance</div>
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

     </ul>
 </aside> --}}








 <aside id="layout-menu" class="layout-menu menu-vertical">

     <style>
         /* ===== SIDEBAR MODERN DESIGN ===== */

         #layout-menu {
             background: #1a1512;
             color: #cbb8b0;
             width: 260px;
             border-right: 1px solid rgba(217, 143, 131, 0.14);
         }

         #layout-menu .app-brand {
             padding: 20px 18px;
             border-bottom: 1px solid rgba(217, 143, 131, 0.14);
         }

         #layout-menu .app-brand-text {
             color: #e79a91;
             font-family: 'Playfair Display', serif;
             font-weight: 700;
             font-size: 18px;
         }

         #layout-menu .menu-inner {
             padding: 15px 10px;
         }

         #layout-menu .menu-header-text {
             color: #8a7d76;
             font-size: 11px;
             letter-spacing: 1px;
         }

         #layout-menu .menu-link {
             border-radius: 10px;
             margin: 3px 0;
             padding: 10px 14px;
             transition: all 0.25s ease;
             color: #cbb8b0;
         }

         #layout-menu .menu-link i {
             font-size: 18px;
         }

         #layout-menu .menu-link:hover {
             background: rgba(217, 143, 131, 0.08);
             color: #e79a91;
             transform: translateX(4px);
         }

         /* ACTIVE ITEM */
         #layout-menu .menu-item.active>.menu-link {
             background: linear-gradient(135deg, #d98f83, #c19a8c);
             color: #241a16;
             box-shadow: 0 6px 18px rgba(217, 143, 131, 0.25);
         }

         #layout-menu .menu-item.active i {
             color: #241a16;
         }

         .menu-divider {
             border-top: 1px solid rgba(217, 143, 131, 0.14);
         }

         /* Minimal sidebar collapse toggle */
         .sidebar-toggle-btn {
             width: 30px;
             height: 30px;
             border-radius: 8px;
             display: inline-flex;
             align-items: center;
             justify-content: center;
             background: rgba(217, 143, 131, 0.1);
             color: #e79a91;
             border: none;
             flex-shrink: 0;
             transition: all .2s ease;
             cursor: pointer;
         }

         .sidebar-toggle-btn:hover {
             background: rgba(217, 143, 131, 0.18);
         }

         .sidebar-toggle-btn i {
             font-size: 16px;
             transition: transform .3s ease;
         }

         html.sidebar-collapsed .sidebar-toggle-btn i {
             transform: rotate(180deg);
         }

         /* Collapsed sidebar state */
         html.sidebar-collapsed #layout-menu {
             width: 78px;
             overflow: hidden;
         }

         html.sidebar-collapsed #layout-menu .app-brand-link,
         html.sidebar-collapsed #layout-menu .menu-link > div,
         html.sidebar-collapsed #layout-menu .menu-header-text {
             display: none !important;
         }

         html.sidebar-collapsed #layout-menu .app-brand {
             justify-content: center;
             padding: 16px 0;
         }

         html.sidebar-collapsed #layout-menu .app-brand .sidebar-toggle-btn {
             margin-left: 0 !important;
         }

         html.sidebar-collapsed #layout-menu .menu-link {
             justify-content: center;
             padding: 10px 0;
         }

         html.sidebar-collapsed #layout-menu .menu-link i {
             margin-right: 0 !important;
             font-size: 20px;
         }

         html.sidebar-collapsed.layout-menu-fixed .layout-page {
             padding-inline-start: 78px !important;
         }
     </style>

     <div class="app-brand d-flex align-items-center">
         <a href="{{ route('dashboard') }}" class="app-brand-link d-flex align-items-center text-decoration-none">
             <img src="{{ asset('images/laleen logo1.PNG') }}" alt="Logo"
                 style="width: 46px; height: 46px; border-radius: 8px;">
             <span class="app-brand-text ms-2">Laleen Ops</span>
         </a>

         <button type="button" id="sidebarToggleBtn" class="sidebar-toggle-btn ms-auto" title="Collapse sidebar">
             <i class="bx bx-chevron-left"></i>
         </button>
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
                 <div>Finance</div>
             </a>
         </li>

         <!-- Clients -->
         <li class="menu-item {{ request()->routeIs('customers.*') ? 'active' : '' }}">
             <a href="{{ route('customers.index') }}" class="menu-link">
                 <i class="bx bx-id-card me-2"></i>
                 <div>Clients</div>
             </a>
         </li>

         <!-- Staff -->
         <li class="menu-item {{ request()->routeIs('staffs.index') || request()->routeIs('users.index') ? 'active' : '' }}">
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

         <!-- KPIs -->
         <li class="menu-item {{ request()->routeIs('kpi.*') ? 'active' : '' }}">
             <a href="{{ route('kpi.hub') }}" class="menu-link">
                 <i class="bx bx-line-chart me-2"></i>
                 <div>KPIs</div>
             </a>
         </li>

     </ul>
 </aside>

 <script>
     (function () {
         var btn = document.getElementById('sidebarToggleBtn');
         if (!btn) return;
         btn.addEventListener('click', function () {
             var collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
             try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
         });
     })();
 </script>
