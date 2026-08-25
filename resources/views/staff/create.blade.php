<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStaffModalLabel">Add Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('staffs.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Branch</label>
                            <select name="branch" class="form-select" required>
                                <option value="">-- Select Branch --</option>
                                <option value="old_airport">Old Airport</option>
                                <option value="wakrah">Wakrah</option>
                                <option value="both">Both</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" name="profile_picture" class="form-control" accept="image/*">
                        </div>

                        {{-- <div class="col-md-6">
                            <label class="form-label">Skills</label>
                            <select name="skills[]" class="form-select select2" multiple="multiple">
                                @foreach ($skillsList as $skill)
                                    <option value="{{ $skill }}">{{ $skill }}</option>
                                @endforeach
                            </select>
                        </div> --}}

                        {{-- <div class="col-md-6">
                            <label class="form-label">Skills</label>
                            <input type="text" name="skills_text" class="form-control" placeholder="e.g. Haircut, Facial, Massage">
                        </div> --}}

                        <div class="col-md-6">
                            <label class="form-label">Skills</label>
                            <select name="skills[]" class="form-select select2" multiple required>
                                @foreach ($services as $service)
                                    <option value="{{ $service->name }}">
                                        {{ $service->name }} ({{ number_format($service->price, 2) }} QAR)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Working Hours (start)</label>
                            <input type="time" name="working_hours[start]" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Working Hours (end)</label>
                            <input type="time" name="working_hours[end]" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Weekly Off</label>
                            <select name="weekly_off[]" class="form-select select2" multiple="multiple">
                                @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $d)
                                    <option value="{{ $d }}">{{ $d }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Availability</label>
                            <select name="availability_status" class="form-select" id="availability_status" required>
                                <option value="present">Present</option>
                                <option value="on-leave">On Leave</option>
                                <option value="sick">Sick</option>
                            </select>
                        </div>

                        <!-- Hidden Date Range Section -->
                        <div class="col-12 d-none" id="offDatesDiv">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Off From</label>
                                    <input type="date" name="off_from" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Off To</label>
                                    <input type="date" name="off_to" class="form-control">
                                </div>
                            </div>
                        </div>


                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

@section('scripts')
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                width: '100%',
                placeholder: 'Select options',
                allowClear: true
            });

            $('#availability_status').on('change', function() {
                if ($(this).val() === 'on-leave' || $(this).val() === 'sick') {
                    $('#offDatesDiv').removeClass('d-none');
                } else {
                    $('#offDatesDiv').addClass('d-none');
                }
            }).trigger('change'); // initial check
        });
    </script>
@endsection
