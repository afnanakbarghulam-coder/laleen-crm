<!-- Edit Appointment Modal -->
<div class="modal fade" id="editAppointmentModal{{ $appointment->id }}" tabindex="-1"
    aria-labelledby="editAppointmentModalLabel{{ $appointment->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAppointmentModalLabel{{ $appointment->id }}">Edit Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('appointments.update', $appointment->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Customer Name</label>
                            <input type="text" name="customer_name" class="form-control"
                                value="{{ $appointment->customer_name }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="{{ $appointment->phone }}"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Appointment Date & Time</label>
                            <input type="datetime-local" name="appointment_datetime" class="form-control editDateInput"
                                value="{{ \Carbon\Carbon::parse($appointment->appointment_datetime)->format('Y-m-d\TH:i') }}"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Services</label>
                            <select name="service_name[]" class="form-select editServiceSelect"
                                data-appointment-id="{{ $appointment->id }}" multiple required>
                                @foreach ($services as $service)
                                    <option value="{{ $service->name }}" data-price="{{ $service->price }}"
                                        {{ in_array($service->name, explode(', ', $appointment->service_name)) ? 'selected' : '' }}>
                                        {{ $service->name }} ({{ number_format($service->price, 2) }} QAR)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Branch</label>
                            <select name="branch" class="form-select editBranchSelect" required>
                                <option value="old_airport"
                                    {{ $appointment->branch == 'old_airport' ? 'selected' : '' }}>Old Airport</option>
                                <option value="wakrah" {{ $appointment->branch == 'wakrah' ? 'selected' : '' }}>Wakrah
                                </option>
                                <option value="home_service"
                                    {{ $appointment->branch == 'home_service' ? 'selected' : '' }}>Home Service
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Booking Agent</label>
                            <select name="booking_agent_id" class="form-select">
                                <option value="">-- Select Agent --</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}"
                                        {{ $appointment->booking_agent_id == $agent->id ? 'selected' : '' }}>
                                        {{ $agent->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Staff</label>
                            <select name="staff_id" class="form-select editStaffSelect" required>
                                <option value="">-- Select Staff --</option>
                                {{-- @foreach ($availableStaff[$appointment->id] ?? [] as $staff)
                                    <option value="{{ $staff->id }}"
                                        {{ $appointment->staff_id == $staff->id ? 'selected' : '' }}>
                                        {{ $staff->name }}
                                    </option>
                                @endforeach --}}

                                <select name="staff_id"
                                        class="form-select editStaffSelect"
                                        data-current-staff="{{ $appointment->staff_id }}"
                                        required>
                                </select>

                            </select>
                            <small class="text-danger d-none editStaffHelp">No staff available for selected service &
                                time</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Price (QAR)</label>
                            <input type="number" name="price" class="form-control editPriceInput"
                                value="{{ $appointment->price }}" min="0" step="1">
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- <script>
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
</script> --}}
