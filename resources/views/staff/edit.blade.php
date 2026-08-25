{{-- <!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal{{ $member->id }}" tabindex="-1"
    aria-labelledby="editStaffModalLabel{{ $member->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStaffModalLabel{{ $member->id }}">Edit {{ $member->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('staffs.update', $member->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Name -->
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="{{ $member->name }}"
                                required>
                        </div>

                        <!-- Profile Picture -->
                        <div class="col-md-6">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" name="profile_picture" class="form-control">
                            @if ($member->profile_picture)
                                <img src="{{ asset($member->profile_picture) }}" class="img-thumbnail mt-2"
                                    width="80">
                            @endif
                        </div>

                        <!-- Branch -->
                        <div class="col-md-6">
                            <label class="form-label">Branch</label>
                            <select name="branch" class="form-select" required>
                                <option value="old_airport" {{ $member->branch == 'old_airport' ? 'selected' : '' }}>Old
                                    Airport</option>
                                <option value="wakrah" {{ $member->branch == 'wakrah' ? 'selected' : '' }}>Wakrah
                                </option>
                                <option value="both" {{ $member->branch == 'both' ? 'selected' : '' }}>Both</option>
                            </select>
                        </div>

                        <!-- Skills -->
                        <!-- Skills -->
                        <div class="col-md-6">
                            <label class="form-label">Skills</label>
                            <select name="skills[]" class="form-select select2-edit" multiple>
                                @foreach ($skillsList as $skill)
                                    <option value="{{ $skill }}"
                                        {{ in_array($skill, $member->skills ?? []) ? 'selected' : '' }}>
                                        {{ $skill }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Working Hours Start -->
                        <div class="col-md-6">
                            <label class="form-label">Working Hours (start)</label>
                            <input type="time" name="working_hours[start]" class="form-control"
                                value="{{ $member->working_hours['start'] ?? '' }}">
                        </div>

                        <!-- Working Hours End -->
                        <div class="col-md-6">
                            <label class="form-label">Working Hours (end)</label>
                            <input type="time" name="working_hours[end]" class="form-control"
                                value="{{ $member->working_hours['end'] ?? '' }}">
                        </div>

                        <!-- Weekly Off -->
                        <div class="col-md-6">
                            <label class="form-label">Weekly Off</label>
                            <select name="weekly_off[]" class="form-select select2-edit" multiple>
                                @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $d)
                                    <option value="{{ $d }}"
                                        {{ in_array($d, $member->weekly_off ?? []) ? 'selected' : '' }}>
                                        {{ $d }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Availability -->
                        <div class="col-md-6">
                            <label class="form-label">Availability</label>
                            <select name="availability_status" class="form-select edit-availability"
                                data-id="{{ $member->id }}" required>
                                <option value="present"
                                    {{ $member->availability_status == 'present' ? 'selected' : '' }}>Present</option>
                                <option value="on-leave"
                                    {{ $member->availability_status == 'on-leave' ? 'selected' : '' }}>On Leave
                                </option>
                                <option value="sick" {{ $member->availability_status == 'sick' ? 'selected' : '' }}>
                                    Sick</option>
                            </select>
                        </div>

                        <!-- Off Dates (show only if on-leave or sick) -->
                        <div class="col-12 {{ $member->availability_status == 'on-leave' || $member->availability_status == 'sick' ? '' : 'd-none' }}"
                            id="editOffDates{{ $member->id }}">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Off From</label>
                                    <input type="date" name="off_from" class="form-control"
                                        value="{{ $member->off_from }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Off To</label>
                                    <input type="date" name="off_to" class="form-control"
                                        value="{{ $member->off_to }}">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

@section('scripts')
    <script>
 
        $(document).ready(function() {
            // Initialize Select2 only inside this modal
            $('#editStaffModal{{ $member->id }}').on('shown.bs.modal', function() {
                $(this).find('.select2-edit').select2({
                    width: '100%',
                    placeholder: 'Select options',
                    allowClear: true
                });

                // Trigger availability check
                $(this).find('.edit-availability').trigger('change');
            });

            // Show/hide Off Dates dynamically
            document.querySelectorAll('.edit-availability').forEach(function(select) {
                select.addEventListener('change', function() {
                    let id = this.getAttribute('data-id');
                    let div = document.getElementById('editOffDates' + id);
                    if (this.value === 'on-leave' || this.value === 'sick') {
                        div.classList.remove('d-none');
                    } else {
                        div.classList.add('d-none');
                    }
                });
            });
        });
    </script>
@endsection --}}














<!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal{{ $member->id }}" tabindex="-1"
    aria-labelledby="editStaffModalLabel{{ $member->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStaffModalLabel{{ $member->id }}">Edit {{ $member->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('staffs.update', $member->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">

                        <!-- Name -->
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" value="{{ $member->name }}"
                                required>
                        </div>

                        <!-- Profile Picture -->
                        <div class="col-md-6">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" name="profile_picture" class="form-control">
                            @if ($member->profile_picture)
                                <img src="{{ asset($member->profile_picture) }}" class="img-thumbnail mt-2"
                                    width="80">
                            @endif
                        </div>

                        <!-- Branch -->
                        <div class="col-md-6">
                            <label class="form-label">Branch</label>
                            <select name="branch" class="form-select" required>
                                <option value="old_airport" {{ $member->branch == 'old_airport' ? 'selected' : '' }}>Old
                                    Airport</option>
                                <option value="wakrah" {{ $member->branch == 'wakrah' ? 'selected' : '' }}>Wakrah
                                </option>
                                <option value="both" {{ $member->branch == 'both' ? 'selected' : '' }}>Both</option>
                            </select>
                        </div>

                        <!-- Skills (as comma-separated text) -->
                        {{-- <div class="col-md-6">
                            <label class="form-label">Skills</label>
                            <input type="text" name="skills_text" class="form-control"
                                value="{{ implode(', ', $member->skills ?? []) }}"
                                placeholder="e.g. Haircut, Facial, Massage">
                        </div> --}}

                        <div class="col-md-6">
                            <label class="form-label">Services</label>
                            <select name="skills[]" class="form-select select2-edit" multiple required>
                                @foreach ($services as $service)
                                    <option value="{{ $service->name }}"
                                        {{ in_array($service->name, $member->skills ?? []) ? 'selected' : '' }}>
                                        {{ $service->name }} ({{ number_format($service->price, 2) }} QAR)
                                    </option>
                                @endforeach
                            </select>
                        </div>


                        <!-- Working Hours Start -->
                        <div class="col-md-6">
                            <label class="form-label">Working Hours (start)</label>
                            <input type="time" name="working_hours[start]" class="form-control"
                                value="{{ $member->working_hours['start'] ?? '' }}">
                        </div>

                        <!-- Working Hours End -->
                        <div class="col-md-6">
                            <label class="form-label">Working Hours (end)</label>
                            <input type="time" name="working_hours[end]" class="form-control"
                                value="{{ $member->working_hours['end'] ?? '' }}">
                        </div>

                        <!-- Weekly Off -->
                        <div class="col-md-6">
                            <label class="form-label">Weekly Off</label>
                            <select name="weekly_off[]" class="form-select select2-edit" multiple>
                                @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $d)
                                    <option value="{{ $d }}"
                                        {{ in_array($d, $member->weekly_off ?? []) ? 'selected' : '' }}>
                                        {{ $d }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Availability -->
                        <div class="col-md-6">
                            <label class="form-label">Availability</label>
                            <select name="availability_status" class="form-select edit-availability"
                                data-id="{{ $member->id }}" required>
                                <option value="present"
                                    {{ $member->availability_status == 'present' ? 'selected' : '' }}>Present</option>
                                <option value="on-leave"
                                    {{ $member->availability_status == 'on-leave' ? 'selected' : '' }}>On Leave
                                </option>
                                <option value="sick" {{ $member->availability_status == 'sick' ? 'selected' : '' }}>
                                    Sick</option>
                            </select>
                        </div>

                        <!-- Off Dates -->
                        <div class="col-12 {{ $member->availability_status == 'on-leave' || $member->availability_status == 'sick' ? '' : 'd-none' }}"
                            id="editOffDates{{ $member->id }}">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Off From</label>
                                    <input type="date" name="off_from" class="form-control"
                                        value="{{ $member->off_from }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Off To</label>
                                    <input type="date" name="off_to" class="form-control"
                                        value="{{ $member->off_to }}">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

@section('scripts')
    <script>
        $(document).ready(function() {
            // Initialize Select2 only inside this modal
            $('#editStaffModal{{ $member->id }}').on('shown.bs.modal', function() {
                $(this).find('.select2-edit').select2({
                    width: '100%',
                    placeholder: 'Select options',
                    allowClear: true
                });

                // Trigger availability check
                $(this).find('.edit-availability').trigger('change');
            });

            // Show/hide Off Dates dynamically
            document.querySelectorAll('.edit-availability').forEach(function(select) {
                select.addEventListener('change', function() {
                    let id = this.getAttribute('data-id');
                    let div = document.getElementById('editOffDates' + id);
                    if (this.value === 'on-leave' || this.value === 'sick') {
                        div.classList.remove('d-none');
                    } else {
                        div.classList.add('d-none');
                    }
                });
            });
        });
    </script>
@endsection
