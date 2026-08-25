<!-- Add Appointment Modal -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1" aria-labelledby="addAppointmentModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAppointmentModalLabel">Add New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('appointments.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" id="createPhone" class="form-control" required
                                placeholder="Type to search returning clients">
                            <small id="createCustomerInfo" class="d-none d-block mt-1 text-success"></small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Customer Name</label>
                            <input type="text" name="customer_name" id="createCustomerName" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Appointment Date & Time</label>
                            <input type="datetime-local" name="appointment_datetime" class="form-control" required>
                        </div>


                        <div class="col-md-6">
                            <label class="form-label">Services</label>
                            <select name="service_name[]" class="form-select service-select" multiple required>
                                @foreach ($services as $service)
                                    <option value="{{ $service->name }}" data-price="{{ $service->price }}" data-duration="{{ $service->duration }}">
                                        {{ $service->name }} ({{ number_format($service->price, 2) }} QAR, {{ $service->duration }} min)
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted" id="createDurationTotal"></small>
                        </div>


                        <div class="col-md-6">
                            <label class="form-label">Branch</label>
                            <select name="branch" class="form-select" required>
                                <option value="">-- Select Branch --</option>
                                <option value="old_airport">Old Airport</option>
                                <option value="wakrah">Wakrah</option>
                                <option value="home_service">Home Service</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Booking Agent</label>
                            <select name="booking_agent_id" class="form-select">
                                <option value="">-- Select Agent --</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Staff</label>
                            {{-- <select name="staff_id" class="form-select">
                                <option value="">-- Select Staff --</option>
                                @foreach ($staff as $staff)
                                    <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                                @endforeach
                            </select> --}}

                            <select name="staff_id" id="staffSelect" class="form-select" required>
                                <option value="">-- Select Staff --</option>
                            </select>

                            <small id="staffHelp" class="text-danger d-none">
                                No staff available for selected service & time
                            </small>

                        </div>


                        <div class="col-md-6">
                            <label class="form-label">Total Price (QAR)</label>
                            <input type="number" name="price" id="servicePrice" class="form-control" step="1"
                                min="0">
                        </div>

                        {{-- <div class="col-md-6">
                            <label class="form-label">Lifetime Revenue (QAR)</label>
                            <input type="number" name="lifetime_revenue" class="form-control" min="0"
                                step="0.01">
                        </div> --}}

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- <script>
    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'serviceSelect') {
            let total = 0;

            Array.from(e.target.selectedOptions).forEach(option => {
                total += Number(option.dataset.price || 0);
            });

            document.getElementById('servicePrice').value = total.toFixed(2);
        }
    });
</script> --}}

<script>
    (function() {
        let staffRequestSeq = 0;
        let lookupTimer = null;

        function lookupCustomer(phone) {
            const info = document.getElementById('createCustomerInfo');
            if (!phone || phone.replace(/\D/g, '').length < 4) {
                info.classList.add('d-none');
                return;
            }

            fetch("{{ route('customers.lookup') }}?phone=" + encodeURIComponent(phone))
                .then(res => res.json())
                .then(data => {
                    if (!data.found) {
                        info.classList.add('d-none');
                        return;
                    }

                    const nameField = document.getElementById('createCustomerName');
                    if (!nameField.value && data.name) {
                        nameField.value = data.name;
                    }

                    info.textContent = `Returning client · ${data.visit_count} visit${data.visit_count === 1 ? '' : 's'}` +
                        (data.last_visit ? ` · last visit ${data.last_visit}` : '');
                    info.classList.remove('d-none');
                });
        }

        document.getElementById('createPhone').addEventListener('input', function() {
            clearTimeout(lookupTimer);
            const phone = this.value;
            lookupTimer = setTimeout(() => lookupCustomer(phone), 400);
        });

        document.getElementById('addAppointmentModal').addEventListener('shown.bs.modal', function() {
            const serviceSelect = this.querySelector('.service-select');
            const dateInput = this.querySelector('input[name="appointment_datetime"]');
            const branchSelect = this.querySelector('select[name="branch"]');
            const staffSelect = this.querySelector('#staffSelect');
            const staffHelp = this.querySelector('#staffHelp');
            const durationLabel = document.getElementById('createDurationTotal');

            function updateDuration() {
                const totalMin = Array.from(serviceSelect.selectedOptions)
                    .reduce((sum, o) => sum + Number(o.dataset.duration || 0), 0);
                durationLabel.textContent = totalMin ? `Total duration: ${totalMin} min` : '';
            }
            serviceSelect.addEventListener('change', updateDuration);
            if (window.jQuery) jQuery(serviceSelect).on('change', updateDuration);
            updateDuration();

            function loadAvailableStaff() {
                const services = Array.from(serviceSelect.selectedOptions).map(o => o.value);
                const datetime = dateInput.value;
                const branch = branchSelect.value;

                staffSelect.innerHTML = '<option value="">-- Select Staff --</option>';
                staffHelp.classList.add('d-none');
                staffSelect.disabled = true;

                if (!services.length || !datetime || !branch) return;

                const requestId = ++staffRequestSeq;

                const params = new URLSearchParams();
                services.forEach(s => params.append('services[]', s));
                params.append('appointment_datetime', datetime);
                params.append('branch', branch);

                fetch("{{ route('appointments.availableStaff') }}?" + params.toString())
                    .then(res => res.json())
                    .then(data => {
                        if (requestId !== staffRequestSeq) return;

                        if (!data.length) {
                            staffHelp.classList.remove('d-none');
                            return;
                        }

                        staffSelect.disabled = false;

                        data.forEach(staff => {
                            staffSelect.insertAdjacentHTML(
                                'beforeend',
                                `<option value="${staff.id}">${staff.name}</option>`
                            );
                        });
                    })
                    .catch(err => console.error('Fetch error:', err));
            }

            serviceSelect.addEventListener('change', loadAvailableStaff);
            dateInput.addEventListener('change', loadAvailableStaff);
            branchSelect.addEventListener('change', loadAvailableStaff);
        });
    })();
</script>
