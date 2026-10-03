<style>
    #addStaffModal .modal-dialog {
        max-width: 860px;
    }

    #addStaffModal .stf-body {
        display: flex;
        min-height: 480px;
    }

    #addStaffModal .stf-nav {
        width: 200px;
        flex-shrink: 0;
        border-right: 1px solid rgba(217, 143, 131,0.16);
        padding: 16px 10px;
        overflow-y: auto;
    }

    #addStaffModal .stf-nav-group {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #c9a39a;
        margin: 14px 10px 6px;
    }

    #addStaffModal .stf-nav-group:first-child {
        margin-top: 4px;
    }

    #addStaffModal .stf-nav-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #cbb8b0;
        cursor: pointer;
    }

    #addStaffModal .stf-nav-item:hover {
        background: rgba(217, 143, 131,0.06);
    }

    #addStaffModal .stf-nav-item.active {
        background: rgba(217, 143, 131,0.1);
        color: #b98ea3;
    }

    #addStaffModal .stf-nav-item .badge {
        background: rgba(217, 143, 131,0.16);
        color: #cbb8b0;
        font-weight: 700;
    }

    #addStaffModal .stf-nav-item.active .badge {
        background: #b98ea3;
        color: #fff;
    }

    #addStaffModal .stf-content {
        flex: 1;
        padding: 22px 26px;
        overflow-y: auto;
    }

    #addStaffModal .stf-pane {
        display: none;
    }

    #addStaffModal .stf-pane.active {
        display: block;
    }

    #addStaffModal .photo-upload {
        width: 84px;
        height: 84px;
        border-radius: 50%;
        background: rgba(185,142,163,0.14);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #b98ea3;
        font-size: 24px;
        cursor: pointer;
        overflow: hidden;
        flex-shrink: 0;
    }

    #addStaffModal .photo-upload img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
</style>

<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="staffForm" enctype="multipart/form-data" action="{{ route('staffs.store') }}">
                @csrf
                <input type="hidden" name="_method" id="staffFormMethod">

                <div class="modal-header">
                    <h5 class="modal-title" id="staffModalTitle">Add team member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="stf-body">
                    <div class="stf-nav">
                        <div class="stf-nav-group">Personal</div>
                        <div class="stf-nav-item active" data-pane="profile"><span>Profile</span></div>
                        <div class="stf-nav-item" data-pane="address"><span>Addresses</span></div>
                        <div class="stf-nav-item" data-pane="emergency"><span>Emergency contact</span></div>

                        <div class="stf-nav-group">Pay &amp; Employment</div>
                        <div class="stf-nav-item" data-pane="employment"><span>Employment details</span></div>
                        <div class="stf-nav-item" data-pane="pay"><span>Wages &amp; commissions</span></div>

                        <div class="stf-nav-group">Access</div>
                        <div class="stf-nav-item" data-pane="access"><span>Roles &amp; permissions</span></div>
                    </div>

                    <div class="stf-content">
                        <!-- PROFILE -->
                        <div class="stf-pane active" id="stf-profile">
                            <h6 class="fw-bold">Profile</h6>
                            <p class="text-muted small">Manage this team member's personal profile</p>

                            <label class="photo-upload mb-3">
                                <span id="stfPhotoPlaceholder"><i class="bx bx-camera"></i></span>
                                <img id="stfPhotoPreview" class="d-none">
                                <input type="file" name="profile_picture" id="stfPhotoInput" accept="image/*" class="d-none">
                            </label>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">First name *</label>
                                    <input type="text" name="first_name" id="stfFirstName" class="form-control" required maxlength="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last name</label>
                                    <input type="text" name="last_name" id="stfLastName" class="form-control" maxlength="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" id="stfEmail" class="form-control" maxlength="255">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone number</label>
                                    <input type="text" name="phone" id="stfPhone" class="form-control" maxlength="30">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Birthday</label>
                                    <input type="date" name="birthday" id="stfBirthday" class="form-control">
                                </div>
                            </div>
                        </div>

                        <!-- ADDRESSES -->
                        <div class="stf-pane" id="stf-address">
                            <h6 class="fw-bold">Addresses</h6>
                            <p class="text-muted small">Home address on file for this team member</p>

                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" name="address_line1" id="stfAddress" class="form-control" maxlength="255">
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" id="stfCity" class="form-control" maxlength="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Country</label>
                                    <input type="text" name="country" id="stfCountry" class="form-control" maxlength="100">
                                </div>
                            </div>
                        </div>

                        <!-- EMERGENCY CONTACT -->
                        <div class="stf-pane" id="stf-emergency">
                            <h6 class="fw-bold">Emergency contact</h6>
                            <p class="text-muted small">Who should we call in case of an emergency?</p>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Contact name</label>
                                    <input type="text" name="emergency_contact_name" id="stfEmName" class="form-control" maxlength="255">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Relationship</label>
                                    <input type="text" name="emergency_contact_relationship" id="stfEmRelationship" class="form-control" maxlength="100" placeholder="e.g. Spouse, Parent">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone number</label>
                                    <input type="text" name="emergency_contact_phone" id="stfEmPhone" class="form-control" maxlength="30">
                                </div>
                            </div>
                        </div>

                        <!-- EMPLOYMENT DETAILS -->
                        <div class="stf-pane" id="stf-employment">
                            <h6 class="fw-bold">Employment details</h6>
                            <p class="text-muted small">Start date and employment details</p>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Start date</label>
                                    <input type="date" name="start_date" id="stfStartDate" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">End date</label>
                                    <input type="date" name="end_date" id="stfEndDate" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Employment type</label>
                                    <select name="employment_type" id="stfEmploymentType" class="form-select">
                                        <option value="">-- Select an option --</option>
                                        <option value="full_time">Full-time</option>
                                        <option value="part_time">Part-time</option>
                                        <option value="contractor">Contractor</option>
                                        <option value="freelance">Freelance</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Team member ID</label>
                                    <input type="text" name="staff_member_id" id="stfMemberId" class="form-control" maxlength="100">
                                    <small class="text-muted">An identifier used for external systems like payroll</small>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label">Notes</label>
                                <textarea name="internal_notes" id="stfNotes" class="form-control" rows="3" maxlength="1000" placeholder="A private note only viewable in the team member list"></textarea>
                            </div>
                        </div>

                        <!-- WAGES & COMMISSIONS -->
                        <div class="stf-pane" id="stf-pay">
                            <h6 class="fw-bold">Wages &amp; commissions</h6>
                            <p class="text-muted small">Used for internal wage tracking. Base salary and hourly wage feed the Payroll &amp; Overtime tab (Net Salary = Base Salary + Overtime Pay - Deductions); commission is not part of that calculation.</p>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Base salary (QAR / period)</label>
                                    <input type="number" name="base_salary" id="stfBaseSalary" class="form-control" min="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Hourly wage (QAR)</label>
                                    <input type="number" name="hourly_wage" id="stfWage" class="form-control" min="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Commission rate (%)</label>
                                    <input type="number" name="commission_rate" id="stfCommission" class="form-control" min="0" max="100" step="0.1">
                                </div>
                            </div>
                        </div>

                        <!-- ACCESS -->
                        <div class="stf-pane" id="stf-access">
                            <h6 class="fw-bold">Roles &amp; permissions</h6>
                            <p class="text-muted small">Grant this team member a login so they can access the CRM, and control what they can do.</p>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="has_access" id="stfHasAccess" value="1">
                                <label class="form-check-label" for="stfHasAccess">This team member has system access</label>
                            </div>

                            <div id="stfAccessFields" class="d-none">
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select name="access_role" id="stfAccessRole" class="form-select">
                                        <option value="admin">Admin — full access to every module</option>
                                        <option value="manager">Manager — branch operations &amp; oversight</option>
                                        <option value="agent">Agent — bookings, leads, revenue</option>
                                        <option value="staff">Staff — calendar &amp; appointments only</option>
                                        <option value="user">User — limited read-only access</option>
                                    </select>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Login email</label>
                                        <input type="email" name="access_email" id="stfAccessEmail" class="form-control" maxlength="255">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="access_password" id="stfAccessPassword" class="form-control" minlength="8" placeholder="Leave blank to keep current">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        const modalEl = document.getElementById('addStaffModal');

        modalEl.querySelectorAll('.stf-nav-item').forEach(item => {
            item.addEventListener('click', () => {
                modalEl.querySelectorAll('.stf-nav-item').forEach(i => i.classList.remove('active'));
                modalEl.querySelectorAll('.stf-pane').forEach(p => p.classList.remove('active'));
                item.classList.add('active');
                document.getElementById('stf-' + item.dataset.pane).classList.add('active');
            });
        });

        document.getElementById('stfPhotoInput').addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            const preview = document.getElementById('stfPhotoPreview');
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('d-none');
            document.getElementById('stfPhotoPlaceholder').classList.add('d-none');
        });
        document.querySelector('#addStaffModal .photo-upload').addEventListener('click', () => {
            document.getElementById('stfPhotoInput').click();
        });

        document.getElementById('stfHasAccess').addEventListener('change', function() {
            document.getElementById('stfAccessFields').classList.toggle('d-none', !this.checked);
        });

        window.resetStaffForm = function() {
            document.getElementById('staffModalTitle').innerText = 'Add team member';
            document.getElementById('staffForm').action = '{{ route('staffs.store') }}';
            document.getElementById('staffFormMethod').value = '';
            document.getElementById('staffForm').reset();
            document.getElementById('stfPhotoPreview').classList.add('d-none');
            document.getElementById('stfPhotoPlaceholder').classList.remove('d-none');
            document.getElementById('stfAccessFields').classList.add('d-none');
            modalEl.querySelectorAll('.stf-nav-item').forEach(i => i.classList.remove('active'));
            modalEl.querySelectorAll('.stf-pane').forEach(p => p.classList.remove('active'));
            modalEl.querySelector('[data-pane="profile"]').classList.add('active');
            document.getElementById('stf-profile').classList.add('active');
        };

        window.editStaff = function(member, hasAccess, accessRole, accessEmail) {
            resetStaffForm();

            document.getElementById('staffModalTitle').innerText = 'Edit team member';
            document.getElementById('staffForm').action = `/staffs/${member.id}`;
            document.getElementById('staffFormMethod').value = 'PUT';

            document.getElementById('stfFirstName').value = member.first_name || member.name || '';
            document.getElementById('stfLastName').value = member.last_name || '';
            document.getElementById('stfEmail').value = member.email || '';
            document.getElementById('stfPhone').value = member.phone || '';
            document.getElementById('stfBirthday').value = member.birthday ? member.birthday.slice(0, 10) : '';

            document.getElementById('stfAddress').value = member.address_line1 || '';
            document.getElementById('stfCity').value = member.city || '';
            document.getElementById('stfCountry').value = member.country || '';

            document.getElementById('stfEmName').value = member.emergency_contact_name || '';
            document.getElementById('stfEmRelationship').value = member.emergency_contact_relationship || '';
            document.getElementById('stfEmPhone').value = member.emergency_contact_phone || '';

            document.getElementById('stfStartDate').value = member.start_date ? member.start_date.slice(0, 10) : '';
            document.getElementById('stfEndDate').value = member.end_date ? member.end_date.slice(0, 10) : '';
            document.getElementById('stfEmploymentType').value = member.employment_type || '';
            document.getElementById('stfMemberId').value = member.staff_member_id || '';
            document.getElementById('stfNotes').value = member.internal_notes || '';

            document.getElementById('stfBaseSalary').value = member.base_salary || '';
            document.getElementById('stfWage').value = member.hourly_wage || '';
            document.getElementById('stfCommission').value = member.commission_rate || '';

            if (member.profile_picture) {
                const preview = document.getElementById('stfPhotoPreview');
                preview.src = `/${member.profile_picture}`;
                preview.classList.remove('d-none');
                document.getElementById('stfPhotoPlaceholder').classList.add('d-none');
            }

            document.getElementById('stfHasAccess').checked = hasAccess;
            document.getElementById('stfAccessFields').classList.toggle('d-none', !hasAccess);
            if (hasAccess) {
                document.getElementById('stfAccessRole').value = accessRole;
                document.getElementById('stfAccessEmail').value = accessEmail;
            }

            new bootstrap.Modal(modalEl).show();
        };

        modalEl.addEventListener('hidden.bs.modal', window.resetStaffForm);
    })();
</script>
