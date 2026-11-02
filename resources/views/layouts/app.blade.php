<!doctype html>
<html lang="en" class="layout-menu-fixed layout-compact"
    data-assets-path="{{ asset('design/sneat-admin-template/assets/') }}/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="{{ asset('images/laleen logo1.PNG') }}">

    <script>
        try {
            if (localStorage.getItem('sidebarCollapsed') === '1') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
        } catch (e) {}
    </script>

    <title>@yield('title', 'Laleen Ops')</title>

    <!-- Boxicons Icons -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <!-- Luxury Theme Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;900&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- CSS Files --}}
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/css/core.css') }}">
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/vendor/css/theme-default.css') }}">
    <link rel="stylesheet" href="{{ asset('design/sneat-admin-template/assets/css/demo.css') }}">
    <link rel="stylesheet"
        href="{{ asset('design/sneat-admin-template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}">
    <link rel="stylesheet"
        href="{{ asset('design/sneat-admin-template/assets/vendor/libs/apex-charts/apex-charts.css') }}">
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/helpers.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/js/config.js') }}"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Luxury Theme (loaded last so it overrides template defaults) -->
    <link rel="stylesheet" href="{{ asset('css/luxury-theme.css') }}?v={{ filemtime(public_path('css/luxury-theme.css')) }}">


    {{-- <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /> --}}
    {{-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> --}}


    <Style>
        .flash-hide {
            opacity: 0;
            transition: opacity 0.7s ease;
            /* smooth fade-out */
        }
    </Style>
    <style>[x-cloak] { display: none !important; }</style>
    <style>
        /* Reusable Zero-Lag Dark-Luxury CSS Tooltip Component */
        .crm-tooltip-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
            font-style: normal;
            line-height: 1;
            color: #e79a91;
            background: rgba(217, 143, 131, 0.12);
            border: 1px solid rgba(201, 166, 107, 0.5);
            cursor: help;
            position: relative;
            vertical-align: middle;
            flex-shrink: 0;
            user-select: none;
            transition: background 0.08s ease, border-color 0.08s ease, color 0.08s ease;
        }

        .crm-tooltip-trigger:hover,
        .crm-tooltip-trigger:focus {
            background: rgba(217, 143, 131, 0.28);
            border-color: #e79a91;
            color: #fff;
            outline: none;
        }

        [data-tooltip] {
            position: relative;
        }

        [data-tooltip]::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%) translateY(4px);
            width: max-content;
            max-width: 260px;
            padding: 8px 11px;
            border-radius: 8px;
            background: #191413;
            color: #f4eae6;
            border: 1px solid rgba(217, 143, 131, 0.45);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.55);
            font-family: 'Poppins', sans-serif;
            font-size: 11.5px;
            font-weight: 400;
            line-height: 1.45;
            letter-spacing: 0;
            text-transform: none;
            text-align: left;
            white-space: normal;
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.07s ease, transform 0.07s ease, visibility 0.07s ease;
            z-index: 9999;
        }

        [data-tooltip]::before {
            content: '';
            position: absolute;
            bottom: calc(100% + 3px);
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px 5px 0 5px;
            border-style: solid;
            border-color: rgba(217, 143, 131, 0.55) transparent transparent transparent;
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.07s ease, visibility 0.07s ease;
            z-index: 9999;
        }

        [data-tooltip]:hover::after,
        [data-tooltip]:focus::after,
        [data-tooltip]:focus-visible::after {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }

        [data-tooltip]:hover::before,
        [data-tooltip]:focus::before,
        [data-tooltip]:focus-visible::before {
            opacity: 1;
            visibility: visible;
        }

        /* Prevent parent tooltip from rendering if it contains a nested tooltip child */
        [data-tooltip]:has([data-tooltip])::after,
        [data-tooltip]:has([data-tooltip])::before {
            display: none !important;
        }

        /* Bottom placement modifier so top-of-page buttons/headers never clip off-screen */
        [data-tooltip].tooltip-bottom::after {
            bottom: auto;
            top: calc(100% + 8px);
            transform: translateX(-50%) translateY(-4px);
        }

        [data-tooltip].tooltip-bottom::before {
            bottom: auto;
            top: calc(100% + 3px);
            border-width: 0 5px 5px 5px;
            border-color: transparent transparent rgba(217, 143, 131, 0.55) transparent;
        }

        [data-tooltip].tooltip-bottom:hover::after,
        [data-tooltip].tooltip-bottom:focus::after {
            transform: translateX(-50%) translateY(0);
        }

        /* Right-edge alignment modifier so right-most buttons never overflow viewport */
        [data-tooltip].tooltip-align-right::after {
            left: auto;
            right: 0;
            transform: translateX(0) translateY(4px);
        }

        [data-tooltip].tooltip-align-right:hover::after,
        [data-tooltip].tooltip-align-right:focus::after {
            transform: translateX(0) translateY(0);
        }

        [data-tooltip].tooltip-bottom.tooltip-align-right::after {
            left: auto;
            right: 0;
            transform: translateX(0) translateY(-4px);
        }

        [data-tooltip].tooltip-bottom.tooltip-align-right:hover::after,
        [data-tooltip].tooltip-bottom.tooltip-align-right:focus::after {
            transform: translateX(0) translateY(0);
        }

        /* Left-edge alignment modifier so first-column pills never clip on the left */
        [data-tooltip].tooltip-align-left::after {
            left: 0;
            transform: translateX(0) translateY(4px);
        }

        [data-tooltip].tooltip-align-left:hover::after,
        [data-tooltip].tooltip-align-left:focus::after {
            transform: translateX(0) translateY(0);
        }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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

    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            {{-- Sidebar --}}
            @include('partials.sidebar')

            <!-- Layout container -->
            <div class="layout-page">

                {{-- Navbar --}}
                @include('partials.header')

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        @include('partials.flash')
                        @yield('content')

                    </div>

                    {{-- Footer --}}
                    @include('partials.footer')
                </div>
                <!-- / Content wrapper -->
            </div>
            <!-- / Layout page -->

        </div>
        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    {{-- JS --}}
    {{-- <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/menu.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/js/main.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/js/dashboards-analytics.js') }}"></script> --}}


    <!-- Core JS -->
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}">
    </script>
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/js/menu.js') }}"></script>



    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Vendors JS -->
    <script src="{{ asset('design/sneat-admin-template/assets/vendor/libs/apex-charts/apexcharts.js') }}"></script>

    <!-- Main JS -->
    <script src="{{ asset('design/sneat-admin-template/assets/js/main.js') }}"></script>
    <script src="{{ asset('design/sneat-admin-template/assets/js/dashboards-analytics.js') }}"></script>

    @include('users.profile')

    <script>
        setTimeout(() => {
            const msgs = document.querySelectorAll('.flash-msg');
            msgs.forEach(msg => {
                msg.classList.add('flash-hide'); // fade out

                // after fade completes (700ms), close Bootstrap alert
                setTimeout(() => {
                    $(msg).alert('close');
                }, 700);
            });
        }, 30000); // wait 3 seconds before starting fade
    </script>

    <script>
        // Any .card in this theme uses backdrop-filter for the glass effect,
        // which (per spec) makes it a containing block for position:fixed
        // descendants - so a Bootstrap modal defined inside a card (e.g. one
        // modal per table row) gets sized/positioned relative to that card
        // instead of the viewport, and Bootstrap's body-level backdrop can
        // end up painting on top of it. Relocating the modal to be a direct
        // child of <body> right before it opens sidesteps this everywhere,
        // for every current and future modal, without touching each view.
        document.addEventListener('show.bs.modal', function (e) {
            if (e.target.parentElement !== document.body) {
                document.body.appendChild(e.target);
            }
        });
    </script>

    @yield('scripts')



    {{-- appointment script --}}
    <script>
        $(document).ready(function() {

            function initServiceSelect2(modal) {
                modal.find('.service-select').each(function() {
                    if (!$(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2({
                            dropdownParent: modal,
                            placeholder: 'Select services',
                            width: '100%',
                            closeOnSelect: false
                        });
                    }
                });
            }

            // ADD MODAL
            $('#addAppointmentModal').on('shown.bs.modal', function() {
                initServiceSelect2($(this));
            });

            // EDIT MODALS (delegated)
            $(document).on('shown.bs.modal', '.modal', function() {
                initServiceSelect2($(this));
            });

            // PRICE CALCULATION (Select2-compatible)
            $(document).on('change', '.service-select', function() {
                let total = 0;

                $(this).find(':selected').each(function() {
                    total += Number($(this).data('price') || 0);
                });

                const modal = $(this).closest('.modal');

                modal.find('#servicePrice').val(total.toFixed(2));
                modal.find('input[name="price"]').val(total.toFixed(2));
            });

        });
    </script>
</body>

</html>
