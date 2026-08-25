@extends('layouts.app')

@section('title', 'Appointment Bookings')

@section('content')
    <!-- Page Navbar with Add Button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold">Appointment Bookings</h4>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAppointmentModal">
            <i class="bx bx-plus me-1"></i> Add Appointment
        </button>
    </div>

    <!-- Filter Section -->
    <div class="mb-4">
        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse"
            aria-expanded="false" aria-controls="filterCollapse">
            <i class="bx bx-filter-alt me-1"></i> Filters
        </button>

        <div class="collapse mt-3 {{ request()->hasAny(['branch', 'agent_id', 'service_name', 'date', 'status', 'price_from', 'price_to', 'customer_name', 'phone']) ? 'show' : '' }}"
            id="filterCollapse">
            <div class="card card-body shadow-sm border-0">
                <form method="GET" action="{{ route('appointments.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Branch</label>
                            <select name="branch" class="form-select">
                                <option value="">All Branches</option>
                                <option value="old_airport" {{ request('branch') == 'old_airport' ? 'selected' : '' }}>Old
                                    Airport</option>
                                <option value="wakrah" {{ request('branch') == 'wakrah' ? 'selected' : '' }}>Wakrah</option>
                                <option value="home_service" {{ request('branch') == 'home_service' ? 'selected' : '' }}>
                                    Home Service</option>
                            </select>

                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Agent</label>
                            <select name="agent_id" class="form-select">
                                <option value="">All Agents</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}"
                                        {{ request('agent_id') == $agent->id ? 'selected' : '' }}>
                                        {{ $agent->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Service Name</label>
                            <input type="text" name="service_name" class="form-control" placeholder="Search by Service"
                                value="{{ request('service_name') }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Appointment Date</label>
                            <input type="date" name="date" class="form-control" value="{{ request('date') }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Min Price (QAR)</label>
                            <input type="number" name="price_from" class="form-control" step="1" min="0" placeholder="From" value="{{ request('price_from') }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Max Price (QAR)</label>
                            <input type="number" name="price_to" class="form-control" step="1" min="0" placeholder="To" value="{{ request('price_to') }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Customer Name</label>
                            <input type="text" name="customer_name" class="form-control" placeholder="Search by customer" value="{{ request('customer_name') }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="Search by phone" value="{{ request('phone') }}">
                        </div>

                        <div class="col-md-12 d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                            <a href="{{ route('appointments.index') }}" class="btn btn-secondary w-100">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Appointment Table -->
    <div class="card">
        <h5 class="card-header">All Appointments <span class="badge bg-primary">{{ $appointments->count() }}</span></h5>
        <div class="table-responsive text-nowrap">
            <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Customer Name</th>
                        <th>Phone</th>
                        <th>Service Name</th>
                        <th>Branch</th>
                        <th>Appointment Date & Time</th>
                        <th>Price (QAR)</th>
                        <th>Staff</th>
                        {{-- <th>Lifetime Revenue</th> --}}
                        <th>Status</th>
                        <th>Agent</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($appointments as $appointment)
                        <tr>
                            {{-- <td>{{ $appointment->customer_name }}</td> --}}
                            <td>
                                <button class="btn btn-link p-0"
                                    onclick="showCustomerProfile('{{ $appointment->phone }}')">
                                    {{ $appointment->customer_name }}
                                </button>
                            </td>

                            <td> {{ $appointment->phone }}</td>

                            <td>{{ $appointment->service_name }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $appointment->branch)) }}</td>
                            <td>{{ \Carbon\Carbon::parse($appointment->appointment_datetime)->format('d M Y, h:i A') }}
                            </td>
                            <td>{{ number_format($appointment->price, 2) }}</td>
                            {{-- <td>{{ number_format($appointment->lifetime_revenue, 2) }}</td> --}}
                            <td>{{ $appointment->staff?->name ?? 'N/A' }}</td>
                            <td>
                                @php
                                    $statusColors = [
                                        'pending'     => 'secondary',
                                        'arrived'     => 'info',
                                        'in_progress' => 'primary',
                                        'completed'   => 'success',
                                        'no_show'     => 'danger',
                                        'cancelled'   => 'warning',
                                    ];
                                @endphp

                                <span class="badge bg-{{ $statusColors[$appointment->status] ?? 'secondary' }}">
                                    {{ ucwords(str_replace('_', ' ', $appointment->status)) }}
                                </span>
                            </td>

                            <td>{{ $appointment->agent->name ?? '—' }}</td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn p-0 dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        @if ($appointment->status !== 'completed' && $appointment->status !== 'cancelled')
                                            <a class="dropdown-item" href="{{ route('appointments.revenue.payment', $appointment->id) }}">
                                                <i class="bx bx-credit-card me-1"></i> Checkout
                                            </a>
                                        @endif
                                        <a class="dropdown-item" href="#" data-bs-toggle="modal"
                                            data-bs-target="#editAppointmentModal{{ $appointment->id }}">
                                            <i class="bx bx-edit-alt me-1"></i> Edit
                                        </a>
                                        <form action="{{ route('appointments.destroy', $appointment->id) }}"
                                            method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button class="dropdown-item text-danger" type="submit"
                                                onclick="return confirm('Delete this appointment?');">
                                                <i class="bx bx-trash me-1"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        @include('appointments.edit', ['appointment' => $appointment])
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted">No appointments found</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <th colspan="5" class="text-end">Total:</th>
                        <th>{{ number_format($total_price, 2) }} QAR</th>
                        {{-- <th>{{ number_format($total_revenue, 2) }} QAR</th> --}}
                        <th colspan="3"></th>
                    </tr>
                </tfoot>

            </table>
        </div>
    </div>

    @include('appointments.create')
    @include('appointments.customer_profile')

    <script>
        function showCustomerProfile(phone) {
            fetch(`/appointments/customer-profile/${phone}`)
                .then(res => {
                    if (!res.ok) throw new Error();
                    return res.json();
                })
                .then(data => {
                    document.getElementById('profileName').innerText = data.customer_name;
                    document.getElementById('profilePhone').innerText = data.phone;
                    document.getElementById('profileVisits').innerText = data.total_visits;
                    document.getElementById('profileFirstVisit').innerText = data.first_visit;

                    // Hide Last Visit if only 1 visit
                    if (data.total_visits <= 1) {
                        document.getElementById('lastVisitRow').style.display = 'none';
                    } else {
                        document.getElementById('lastVisitRow').style.display = 'block';
                        document.getElementById('profileLastVisit').innerText = data.last_visit;
                    }

                    document.getElementById('profileServices').innerText = data.services_taken;
                    document.getElementById('profileRevenue').innerText = data.lifetime_revenue;

                    const fullLink = document.getElementById('profileFullLink');
                    if (data.customer_id) {
                        fullLink.href = `/customers/${data.customer_id}`;
                        fullLink.classList.remove('d-none');
                    } else {
                        fullLink.classList.add('d-none');
                    }

                    // Populate appointments table
                    let tbody = document.getElementById('profileAppointments');
                    tbody.innerHTML = '';
                    data.appointments.forEach(a => {
                        tbody.innerHTML += `
                    <tr>
                        <td>${a.appointment_datetime}</td>
                        <td>${a.service_name}</td>
                        <td>${a.price}</td>
                        <td>${a.branch}</td>
                        <td>${a.agent}</td>
                    </tr>
                `;
                    });

                    new bootstrap.Modal(document.getElementById('customerProfileModal')).show();
                })
                .catch(() => alert('No records found'));
        }
        

        document.addEventListener('DOMContentLoaded', function() {

        // Loop through all edit modals
        document.querySelectorAll('.editServiceSelect').forEach(serviceSelect => {
            const modal = serviceSelect.closest('.modal');
            const appointmentId = serviceSelect.dataset.appointmentId;
            const dateInput = modal.querySelector('.editDateInput');
            const branchSelect = modal.querySelector('.editBranchSelect');
            const staffSelect = modal.querySelector('.editStaffSelect');
            const staffHelp = modal.querySelector('.editStaffHelp');
            const priceInput = modal.querySelector('.editPriceInput');

            function updateStaff() {
                const services = Array.from(serviceSelect.selectedOptions).map(o => o.value);
                const datetime = dateInput.value;
                const branch = branchSelect.value;

                // Reset staff dropdown
                staffSelect.innerHTML = '<option value="">-- Select Staff --</option>';
                staffHelp.classList.add('d-none');
                staffSelect.disabled = true;

                if (!services.length || !datetime || !branch) return;

                const params = new URLSearchParams();
                services.forEach(s => params.append('services[]', s));
                params.append('appointment_datetime', datetime);
                params.append('branch', branch);

                fetch("{{ route('appointments.availableStaff') }}?" + params.toString())
                    .then(res => res.json())
                    .then(data => {
                        if (!data.length) {
                            staffHelp.classList.remove('d-none');
                            return;
                        }

                        staffSelect.disabled = false;
                        staffHelp.classList.add('d-none');

                        data.forEach(staff => {
                            staffSelect.insertAdjacentHTML(
                                'beforeend',
                                `<option value="${staff.id}">${staff.name}</option>`
                            );
                        });
                    });
            }

            function updatePrice() {
                let total = 0;
                Array.from(serviceSelect.selectedOptions).forEach(option => {
                    total += Number(option.dataset.price || 0);
                });
                if (priceInput) priceInput.value = total.toFixed(2);
            }

            // Trigger updates on change
            serviceSelect.addEventListener('change', function() {
                updateStaff();
                updatePrice();
            });
            dateInput.addEventListener('change', updateStaff);
            branchSelect.addEventListener('change', updateStaff);
        });

    });
    </script>

@endsection
