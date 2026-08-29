<style>
    #serviceModal .modal-dialog {
        max-width: 800px;
    }

    #serviceModal .svc-form-body {
        display: flex;
        min-height: 460px;
    }

    #serviceModal .svc-nav {
        width: 180px;
        flex-shrink: 0;
        border-right: 1px solid rgba(217, 143, 131,0.16);
        padding: 16px 8px;
    }

    #serviceModal .svc-nav-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 9px 12px;
        border-radius: 8px;
        font-size: 13.5px;
        font-weight: 600;
        color: #cbb8b0;
        cursor: pointer;
    }

    #serviceModal .svc-nav-item:hover {
        background: rgba(217, 143, 131,0.06);
    }

    #serviceModal .svc-nav-item.active {
        background: rgba(217, 143, 131,0.1);
        color: #b98ea3;
    }

    #serviceModal .svc-nav-item .badge {
        background: rgba(217, 143, 131,0.16);
        color: #cbb8b0;
        font-weight: 700;
    }

    #serviceModal .svc-nav-item.active .badge {
        background: #b98ea3;
        color: #fff;
    }

    #serviceModal .svc-content {
        flex: 1;
        padding: 20px 24px;
        overflow-y: auto;
    }

    #serviceModal .svc-pane {
        display: none;
    }

    #serviceModal .svc-pane.active {
        display: block;
    }

    #serviceModal .cat-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 6px;
    }

    #serviceModal .team-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 4px;
        border-bottom: 1px solid rgba(217, 143, 131,0.07);
    }

    #serviceModal .team-row img {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        object-fit: cover;
        background: rgba(217, 143, 131,0.06);
    }

    #serviceModal .photo-preview {
        width: 90px;
        height: 90px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid rgba(217, 143, 131,0.16);
        background: rgba(217, 143, 131,0.06);
    }
</style>

<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="serviceForm" enctype="multipart/form-data" action="{{ route('services.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod">

                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">New Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="svc-form-body">
                    <div class="svc-nav">
                        <div class="svc-nav-item active" data-pane="basic">
                            <span>Basic details</span>
                        </div>
                        <div class="svc-nav-item" data-pane="team">
                            <span>Team members</span>
                            <span class="badge rounded-pill" id="teamCountBadge">0</span>
                        </div>
                    </div>

                    <div class="svc-content">
                        <!-- BASIC DETAILS -->
                        <div class="svc-pane active" id="pane-basic">
                            <h6 class="fw-bold mb-3">Basic details</h6>

                            <div class="mb-3">
                                <label class="form-label">Service name</label>
                                <input type="text" name="name" id="serviceName" class="form-control" required maxlength="255">
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Menu category</label>
                                    <select name="category_id" id="serviceCategory" class="form-select">
                                        <option value="">-- Uncategorized --</option>
                                        @foreach ($categories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">The category shown in the service menu</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Treatment type <span class="text-muted">(optional)</span></label>
                                    <input type="text" name="treatment_type" id="serviceTreatmentType" class="form-control"
                                        placeholder="e.g. Clipper haircut" list="treatmentTypeSuggestions">
                                    <datalist id="treatmentTypeSuggestions">
                                        <option value="Clipper haircut">
                                        <option value="Scissor haircut">
                                        <option value="Root touch-up">
                                        <option value="Full color">
                                        <option value="Blow dry">
                                        <option value="Gel manicure">
                                        <option value="Classic pedicure">
                                        <option value="Deep tissue massage">
                                        <option value="Signature facial">
                                    </datalist>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description <span class="text-muted">(optional)</span></label>
                                <textarea name="description" id="serviceDescription" class="form-control" rows="3" maxlength="1000"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Photo <span class="text-muted">(optional)</span></label>
                                <div class="d-flex align-items-center gap-3">
                                    <img id="photoPreview" class="photo-preview d-none" src="">
                                    <input type="file" name="photo" id="servicePhoto" class="form-control" accept="image/*">
                                </div>
                            </div>

                            <hr>
                            <h6 class="fw-bold mb-3">Pricing and duration</h6>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Price type</label>
                                    <select class="form-select" disabled>
                                        <option>Fixed</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Price (QAR)</label>
                                    <input type="number" name="price" id="servicePrice" class="form-control" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Duration (min)</label>
                                    <input type="number" name="duration" id="serviceDuration" class="form-control" step="1" min="1" required>
                                </div>
                            </div>

                            <hr>
                            <h6 class="fw-bold mb-1">Beauty Planning interval</h6>
                            <p class="text-muted small mb-3">How often clients should return for this treatment. Powers the Clients module's Beauty Planning re-booking reminders — leave blank if this service isn't recurring.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Recommended re-booking interval (days)</label>
                                    <input type="number" name="rebooking_interval_days" id="serviceRebookingInterval"
                                        class="form-control" step="1" min="1" max="730" placeholder="e.g. 30"
                                        list="rebookingIntervalSuggestions">
                                    <datalist id="rebookingIntervalSuggestions">
                                        <option value="14">
                                        <option value="21">
                                        <option value="30">
                                        <option value="45">
                                        <option value="60">
                                        <option value="90">
                                    </datalist>
                                </div>
                            </div>
                        </div>

                        <!-- TEAM MEMBERS -->
                        <div class="svc-pane" id="pane-team">
                            <h6 class="fw-bold mb-1">Team members</h6>
                            <p class="text-muted small mb-3">Choose who can be booked for this service. This controls who shows up as available on the booking calendar.</p>

                            <div class="mb-2">
                                <a href="#" id="teamSelectAll" class="small">Select all</a> ·
                                <a href="#" id="teamSelectNone" class="small">Clear</a>
                            </div>

                            @foreach ($staffList as $member)
                                <div class="team-row">
                                    <input type="checkbox" name="staff_ids[]" value="{{ $member->id }}" class="form-check-input team-checkbox" id="team-{{ $member->id }}">
                                    <img src="{{ $member->profile_picture ? asset(str_replace('\\', '/', $member->profile_picture)) : asset('design/sneat-admin-template/assets/img/avatars/1.png') }}">
                                    <label for="team-{{ $member->id }}" class="mb-0 flex-grow-1">{{ $member->name }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark" id="submitBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        const modalEl = document.getElementById('serviceModal');

        modalEl.querySelectorAll('.svc-nav-item').forEach(item => {
            item.addEventListener('click', () => {
                modalEl.querySelectorAll('.svc-nav-item').forEach(i => i.classList.remove('active'));
                modalEl.querySelectorAll('.svc-pane').forEach(p => p.classList.remove('active'));
                item.classList.add('active');
                document.getElementById('pane-' + item.dataset.pane).classList.add('active');
            });
        });

        function updateTeamCount() {
            const count = modalEl.querySelectorAll('.team-checkbox:checked').length;
            document.getElementById('teamCountBadge').textContent = count;
        }
        modalEl.querySelectorAll('.team-checkbox').forEach(cb => cb.addEventListener('change', updateTeamCount));

        document.getElementById('teamSelectAll').addEventListener('click', function(e) {
            e.preventDefault();
            modalEl.querySelectorAll('.team-checkbox').forEach(cb => cb.checked = true);
            updateTeamCount();
        });
        document.getElementById('teamSelectNone').addEventListener('click', function(e) {
            e.preventDefault();
            modalEl.querySelectorAll('.team-checkbox').forEach(cb => cb.checked = false);
            updateTeamCount();
        });

        document.getElementById('servicePhoto').addEventListener('change', function() {
            const file = this.files[0];
            const preview = document.getElementById('photoPreview');
            if (!file) {
                preview.classList.add('d-none');
                return;
            }
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('d-none');
        });

        window.resetServiceForm = function() {
            document.getElementById('modalTitle').innerText = 'New Service';
            document.getElementById('serviceForm').action = '{{ route('services.store') }}';
            document.getElementById('formMethod').value = '';
            document.getElementById('serviceForm').reset();
            document.getElementById('serviceRebookingInterval').value = '';
            document.getElementById('photoPreview').classList.add('d-none');
            modalEl.querySelectorAll('.svc-nav-item').forEach(i => i.classList.remove('active'));
            modalEl.querySelectorAll('.svc-pane').forEach(p => p.classList.remove('active'));
            modalEl.querySelector('[data-pane="basic"]').classList.add('active');
            document.getElementById('pane-basic').classList.add('active');
            updateTeamCount();
        };

        modalEl.addEventListener('hidden.bs.modal', window.resetServiceForm);
    })();
</script>
