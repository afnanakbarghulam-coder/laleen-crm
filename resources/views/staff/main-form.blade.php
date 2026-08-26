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
        border-right: 1px solid rgba(213,180,169,0.16);
        padding: 16px 10px;
        overflow-y: auto;
    }

    #addStaffModal .stf-nav-group {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #b6a49b;
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
        background: rgba(213,180,169,0.06);
    }

    #addStaffModal .stf-nav-item.active {
        background: rgba(213,180,169,0.1);
        color: #b98ea3;
    }

    #addStaffModal .stf-nav-item .badge {
        background: rgba(213,180,169,0.16);
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

    #addStaffModal .svc-catalog-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 7px 8px;
        border-radius: 6px;
        font-size: 13px;
    }

    #addStaffModal .svc-catalog-row:hover {
        background: rgba(213,180,169,0.06);
    }

    #addStaffModal .svc-catalog-row .meta {
        font-size: 11px;
        color: #b6a49b;
    }

    #addStaffModal .svc-cat-header {
        font-weight: 700;
        font-size: 12.5px;
        color: #e79a91;
        background: rgba(213,180,169,0.06);
        border-radius: 6px;
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

                        <div class="stf-nav-group">Workspace</div>
                        <div class="stf-nav-item" data-pane="services"><span>Services</span><span class="badge rounded-pill" id="stfServiceCount">0</span></div>
                        <div class="stf-nav-item" data-pane="location"><span>Location</span></div>
                        <div class="stf-nav-item" data-pane="settings"><span>Settings</span></div>

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

                        <!-- SERVICES -->
                        <div class="stf-pane" id="stf-services">
                            <h6 class="fw-bold">Services</h6>
                            <p class="text-muted small">Choose the services this team member provides. This controls who shows up as available on the booking calendar.</p>

                            <input type="text" id="stfServiceSearch" class="form-control form-control-sm mb-2" placeholder="Search services">

                            <div class="svc-catalog-row svc-cat-header">
                                <label class="mb-0 flex-grow-1"><input type="checkbox" id="stfAllServices" class="form-check-input me-2">All services</label>
                            </div>

                            <div id="stfServiceList" style="max-height:320px; overflow-y:auto;">
                                @foreach ($categories as $cat)
                                    <div class="svc-cat-block" data-cat-name="{{ strtolower($cat->name) }}">
                                        <div class="svc-catalog-row svc-cat-header mt-2">
                                            <label class="mb-0 flex-grow-1">
                                                <input type="checkbox" class="form-check-input me-2 cat-checkbox" data-cat="{{ $cat->id }}">
                                                {{ $cat->name }} <span class="text-muted">({{ $cat->services->count() }})</span>
                                            </label>
                                        </div>
                                        @foreach ($cat->services as $svc)
                                            <div class="svc-catalog-row" data-svc-name="{{ strtolower($svc->name) }}" data-cat="{{ $cat->id }}">
                                                <label class="mb-0 flex-grow-1">
                                                    <input type="checkbox" name="service_ids[]" value="{{ $svc->id }}" class="form-check-input me-2 svc-checkbox" data-cat="{{ $cat->id }}">
                                                    {{ $svc->name }}
                                                    <span class="meta">{{ $svc->duration }}min</span>
                                                </label>
                                                <span>{{ number_format($svc->price, 2) }} QAR</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach

                                @php $uncategorized = $services->whereNull('category_id'); @endphp
                                @if ($uncategorized->count())
                                    <div class="svc-cat-block" data-cat-name="uncategorized">
                                        <div class="svc-catalog-row svc-cat-header mt-2">
                                            <span>Uncategorized</span>
                                        </div>
                                        @foreach ($uncategorized as $svc)
                                            <div class="svc-catalog-row" data-svc-name="{{ strtolower($svc->name) }}">
                                                <label class="mb-0 flex-grow-1">
                                                    <input type="checkbox" name="service_ids[]" value="{{ $svc->id }}" class="form-check-input me-2 svc-checkbox">
                                                    {{ $svc->name }}
                                                    <span class="meta">{{ $svc->duration }}min</span>
                                                </label>
                                                <span>{{ number_format($svc->price, 2) }} QAR</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- LOCATION -->
                        <div class="stf-pane" id="stf-location">
                            <h6 class="fw-bold">Location</h6>
                            <p class="text-muted small">Which branch does this team member work from?</p>

                            <select name="branch" id="stfBranch" class="form-select" required>
                                <option value="old_airport">Old Airport</option>
                                <option value="wakrah">Wakrah</option>
                                <option value="both">Both</option>
                            </select>
                        </div>

                        <!-- SETTINGS -->
                        <div class="stf-pane" id="stf-settings">
                            <h6 class="fw-bold">Settings</h6>
                            <p class="text-muted small">Calendar bookability</p>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="bookable" id="stfBookable" value="1" checked>
                                <label class="form-check-label" for="stfBookable">
                                    Bookable on the calendar
                                    <div class="text-muted small">Turn off for staff who don't provide services directly (e.g. receptionists, managers)</div>
                                </label>
                            </div>

                            <div class="alert alert-light border small mb-0">
                                <i class="bx bx-info-circle me-1"></i>
                                Working hours and time off are managed in
                                <a href="{{ route('shifts.index') }}">Scheduled shifts</a>.
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
                            <p class="text-muted small">Used for internal wage and commission tracking</p>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Hourly wage (QAR)</label>
                                    <input type="number" name="hourly_wage" id="stfWage" class="form-control" min="0" step="0.01">
                                </div>
                                <div class="col-md-6">
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

        function updateServiceCount() {
            document.getElementById('stfServiceCount').textContent =
                modalEl.querySelectorAll('.svc-checkbox:checked').length;
        }

        document.getElementById('stfAllServices').addEventListener('change', function() {
            modalEl.querySelectorAll('.svc-checkbox, .cat-checkbox').forEach(cb => cb.checked = this.checked);
            updateServiceCount();
        });
        modalEl.querySelectorAll('.cat-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                modalEl.querySelectorAll(`.svc-checkbox[data-cat="${this.dataset.cat}"]`).forEach(sc => sc.checked = this.checked);
                updateServiceCount();
            });
        });
        modalEl.querySelectorAll('.svc-checkbox').forEach(cb => cb.addEventListener('change', updateServiceCount));

        document.getElementById('stfServiceSearch').addEventListener('input', function() {
            const q = this.value.trim().toLowerCase();
            modalEl.querySelectorAll('.svc-catalog-row[data-svc-name]').forEach(row => {
                row.style.display = row.dataset.svcName.includes(q) ? '' : 'none';
            });
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
            updateServiceCount();
        };

        window.editStaff = function(member, serviceIds, hasAccess, accessRole, accessEmail) {
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

            document.getElementById('stfBranch').value = member.branch;
            document.getElementById('stfBookable').checked = !!member.bookable;

            document.getElementById('stfStartDate').value = member.start_date ? member.start_date.slice(0, 10) : '';
            document.getElementById('stfEndDate').value = member.end_date ? member.end_date.slice(0, 10) : '';
            document.getElementById('stfEmploymentType').value = member.employment_type || '';
            document.getElementById('stfMemberId').value = member.staff_member_id || '';
            document.getElementById('stfNotes').value = member.internal_notes || '';

            document.getElementById('stfWage').value = member.hourly_wage || '';
            document.getElementById('stfCommission').value = member.commission_rate || '';

            modalEl.querySelectorAll('.svc-checkbox').forEach(cb => {
                cb.checked = serviceIds.includes(Number(cb.value));
            });
            modalEl.querySelectorAll('.cat-checkbox').forEach(cb => {
                const catBoxes = modalEl.querySelectorAll(`.svc-checkbox[data-cat="${cb.dataset.cat}"]`);
                cb.checked = catBoxes.length > 0 && [...catBoxes].every(b => b.checked);
            });
            document.getElementById('stfAllServices').checked =
                modalEl.querySelectorAll('.svc-checkbox').length > 0 &&
                [...modalEl.querySelectorAll('.svc-checkbox')].every(b => b.checked);
            updateServiceCount();

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
