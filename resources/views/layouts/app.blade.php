<!doctype html>
<html lang="en" class="layout-menu-fixed layout-compact"
    data-assets-path="{{ asset('design/sneat-admin-template/assets/') }}/" data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="{{ asset('images/laleen logo1.PNG') }}">

    <title>@yield('title', 'Laleen Ops')</title>

    <!-- Boxicons Icons -->
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">


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


    {{-- <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /> --}}
    {{-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> --}}


    <Style>
        .flash-hide {
            opacity: 0;
            transition: opacity 0.7s ease;
            /* smooth fade-out */
        }
    </Style>
</head>

<body>
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
