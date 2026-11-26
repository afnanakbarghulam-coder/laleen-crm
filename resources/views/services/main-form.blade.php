@php
    $canFullEditServices = (bool) (auth()->user()?->canEdit('services'));
    $canEditServiceSteps = (bool) (auth()->user()?->canEditServiceSteps());
@endphp

<style>
    #serviceModal .modal-dialog {
        max-width: 840px;
    }

    #serviceModal .modal-content {
        background: #1b1315 !important;
        background-color: #1b1315 !important;
        opacity: 1 !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        border: 1px solid rgba(217, 143, 131, 0.30) !important;
        border-radius: 16px;
        box-shadow: 0 24px 64px rgba(0, 0, 0, 0.85) !important;
        color: #f8efe6;
    }

    #serviceModal .modal-header,
    #serviceModal .modal-footer {
        background: #1b1315 !important;
        border-color: rgba(255, 255, 255, 0.07) !important;
    }

    #serviceModal .modal-header .btn-close {
        position: static !important;
        transform: none !important;
        margin: 0 0 0 auto !important;
        background-color: transparent !important;
        box-shadow: none !important;
        opacity: 0.78;
    }

    #serviceModal .svc-form-body {
        display: flex;
        min-height: 470px;
        max-height: 68vh;
        background: #1b1315;
    }

    #serviceModal .svc-nav {
        width: 196px;
        flex-shrink: 0;
        border-right: 1px solid rgba(217, 143, 131, 0.16);
        padding: 16px 10px;
        background: #150e10;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    #serviceModal .svc-nav-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        padding: 9px 12px;
        border-radius: 9px;
        font-size: 13px;
        font-weight: 600;
        color: #cbb8b0;
        cursor: pointer;
        user-select: none;
        transition: background 0.14s ease, color 0.14s ease;
    }

    #serviceModal .svc-nav-item:hover {
        background: rgba(217, 143, 131, 0.08);
        color: #f8efe6;
    }

    #serviceModal .svc-nav-item.active {
        background: rgba(217, 143, 131, 0.16);
        color: #f3d9a4;
    }

    #serviceModal .svc-nav-item .badge {
        background: rgba(217, 143, 131, 0.16);
        color: #cbb8b0;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        min-width: 24px;
    }

    #serviceModal .svc-nav-item.active .badge {
        background: #d98f83;
        color: #1b1315;
    }

    #serviceModal .svc-content {
        flex: 1;
        padding: 20px 24px;
        overflow-y: auto;
        min-width: 0;
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
        border-bottom: 1px solid rgba(217, 143, 131, 0.07);
    }

    #serviceModal .team-row img {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        object-fit: cover;
        background: rgba(217, 143, 131, 0.06);
    }

    #serviceModal .photo-preview {
        width: 90px;
        height: 90px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid rgba(217, 143, 131, 0.16);
        background: rgba(217, 143, 131, 0.06);
    }

    #serviceModal .svc-readonly-banner {
        background: rgba(212, 163, 115, 0.09);
        border: 1px solid rgba(212, 163, 115, 0.28);
        color: #f3d9a4;
        border-radius: 10px;
        padding: 8px 12px;
        font-size: 12px;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Service Steps Tab Controls */
    #serviceModal .svc-steps-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    #serviceModal .svc-step-row {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #130d0f;
        border: 1px solid rgba(217, 143, 131, 0.20);
        border-radius: 10px;
        padding: 6px 8px;
        transition: border-color 0.14s ease;
    }

    #serviceModal .svc-step-row:focus-within {
        border-color: rgba(217, 143, 131, 0.55);
    }

    #serviceModal .svc-step-num {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: rgba(217, 143, 131, 0.16);
        border: 1px solid rgba(217, 143, 131, 0.36);
        color: #f3d9a4;
        font-size: 11.5px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    #serviceModal .svc-step-input {
        flex: 1;
        min-width: 0;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        color: #f8efe6 !important;
        font-size: 13px;
        padding: 4px 6px;
        outline: none;
    }

    #serviceModal .svc-step-input::placeholder {
        color: #7d6861;
    }

    #serviceModal .svc-step-input:disabled {
        color: #e6d6d0 !important;
        opacity: 0.9;
    }

    #serviceModal .svc-step-icon-btn {
        width: 28px;
        height: 28px;
        border-radius: 7px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.03);
        color: #cbb8b0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        cursor: pointer;
        padding: 0;
        flex-shrink: 0;
        transition: all 0.12s ease;
    }

    #serviceModal .svc-step-icon-btn:hover:not(:disabled) {
        background: rgba(217, 143, 131, 0.18);
        border-color: rgba(217, 143, 131, 0.42);
        color: #ffffff;
    }

    #serviceModal .svc-step-icon-btn.is-remove:hover:not(:disabled) {
        background: rgba(251, 113, 133, 0.16);
        border-color: rgba(251, 113, 133, 0.42);
        color: #fda4af;
    }

    #serviceModal .svc-step-icon-btn:disabled {
        opacity: 0.3;
        cursor: not-allowed;
    }

    #serviceModal .svc-copy-steps-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        background: rgba(52, 211, 153, 0.12);
        border: 1px solid rgba(52, 211, 153, 0.34);
        color: #6ee7b7;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.14s ease;
        flex-shrink: 0;
    }

    #serviceModal .svc-copy-steps-btn:hover:not(:disabled) {
        background: rgba(52, 211, 153, 0.22);
        color: #a7f3d0;
    }

    #serviceModal .svc-copy-steps-btn:disabled {
        opacity: 0.38;
        cursor: not-allowed;
    }

    #serviceModal .svc-add-step-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 999px;
        background: rgba(217, 143, 131, 0.14);
        border: 1px solid rgba(217, 143, 131, 0.36);
        color: #f8efe6;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.14s ease;
    }

    #serviceModal .svc-add-step-btn:hover {
        background: rgba(217, 143, 131, 0.25);
        border-color: rgba(217, 143, 131, 0.58);
        color: #ffffff;
    }

    #serviceModal .svc-paste-toggle-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #cbb8b0;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.14s ease;
    }

    #serviceModal .svc-paste-toggle-btn:hover {
        background: rgba(255, 255, 255, 0.09);
        color: #f8efe6;
    }

    #serviceModal .svc-paste-box {
        background: #130d0f;
        border: 1px solid rgba(217, 143, 131, 0.28);
        border-radius: 10px;
        padding: 10px 12px;
        margin-top: 10px;
    }

    .svc-steps-toast {
        position: fixed;
        top: 22px;
        right: 24px;
        z-index: 1095;
        background: #1b1315;
        border: 1px solid rgba(110, 231, 183, 0.44);
        color: #6ee7b7;
        border-radius: 12px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 600;
        box-shadow: 0 18px 46px rgba(0, 0, 0, 0.65);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .svc-steps-toast.is-error {
        border-color: rgba(251, 113, 133, 0.48);
        color: #fda4af;
    }

    @media (max-width: 640px) {
        #serviceModal .svc-form-body {
            flex-direction: column;
            max-height: 75vh;
        }
        #serviceModal .svc-nav {
            width: 100%;
            flex-direction: row;
            overflow-x: auto;
            border-right: none;
            border-bottom: 1px solid rgba(217, 143, 131, 0.16);
        }
    }
</style>

<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="serviceForm" enctype="multipart/form-data" action="{{ route('services.store') }}" novalidate>
                @csrf
                <input type="hidden" name="_method" id="formMethod">
                <input type="hidden" name="steps_submitted" value="1">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="modalTitle" style="color:#f8efe6;">New Service</h5>
                        <div class="small d-none" id="modalSubtitle" style="color:#9e8780;"></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                        <div class="svc-nav-item" data-pane="steps">
                            <span>Service steps</span>
                            <span class="badge rounded-pill" id="stepsCountBadge">0</span>
                        </div>
                    </div>

                    <div class="svc-content">
                        <!-- BASIC DETAILS -->
                        <div class="svc-pane active" id="pane-basic">
                            <div class="svc-readonly-banner d-none" id="svcBasicReadonlyBanner">
                                <i class="bx bx-lock-alt fs-6"></i>
                                <span>View-only — contact Admin to update pricing, duration, or catalog details.</span>
                            </div>

                            <h6 class="fw-bold mb-3">Basic details</h6>

                            <div class="mb-3">
                                <label class="form-label">Service name</label>
                                <input type="text" name="name" id="serviceName" class="form-control js-svc-full-field" required maxlength="255">
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Menu category</label>
                                    <select name="category_id" id="serviceCategory" class="form-select js-svc-full-field">
                                        <option value="">-- Uncategorized --</option>
                                        @foreach ($categories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">The category shown in the service menu</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Treatment type <span class="text-muted">(optional)</span></label>
                                    <input type="text" name="treatment_type" id="serviceTreatmentType" class="form-control js-svc-full-field"
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
                                <textarea name="description" id="serviceDescription" class="form-control js-svc-full-field" rows="3" maxlength="1000"></textarea>
                            </div>

                            <div class="mb-3" id="servicePhotoWrap">
                                <label class="form-label">Photo <span class="text-muted">(optional)</span></label>
                                <div class="d-flex align-items-center gap-3">
                                    <img id="photoPreview" class="photo-preview d-none" src="">
                                    <input type="file" name="photo" id="servicePhoto" class="form-control js-svc-full-field" accept="image/*">
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
                                    <input type="number" name="price" id="servicePrice" class="form-control js-svc-full-field" step="0.01" min="0" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Duration (min)</label>
                                    <input type="number" name="duration" id="serviceDuration" class="form-control js-svc-full-field" step="1" min="1" required>
                                </div>
                            </div>

                            <hr>
                            <h6 class="fw-bold mb-1">Beauty Planning interval</h6>
                            <p class="text-muted small mb-3">How often clients should return for this treatment. Powers the Clients module's Beauty Planning re-booking reminders — leave blank if this service isn't recurring.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Recommended re-booking interval (days)</label>
                                    <input type="number" name="rebooking_interval_days" id="serviceRebookingInterval"
                                        class="form-control js-svc-full-field" step="1" min="1" max="730" placeholder="e.g. 30"
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
                            <div class="svc-readonly-banner d-none" id="svcTeamReadonlyBanner">
                                <i class="bx bx-lock-alt fs-6"></i>
                                <span>View-only — contact Admin to update team member assignments.</span>
                            </div>

                            <h6 class="fw-bold mb-1">Team members</h6>
                            <p class="text-muted small mb-3">Choose who can be booked for this service. This controls who shows up as available on the booking calendar.</p>

                            <div class="mb-2" id="teamBulkLinks">
                                <a href="#" id="teamSelectAll" class="small">Select all</a> &middot;
                                <a href="#" id="teamSelectNone" class="small">Clear</a>
                            </div>

                            @foreach ($staffList as $member)
                                <div class="team-row">
                                    <input type="checkbox" name="staff_ids[]" value="{{ $member->id }}" class="form-check-input team-checkbox js-svc-full-field" id="team-{{ $member->id }}">
                                    <img src="{{ $member->profile_picture ? asset(str_replace('\\', '/', $member->profile_picture)) : asset('design/sneat-admin-template/assets/img/avatars/1.png') }}">
                                    <label for="team-{{ $member->id }}" class="mb-0 flex-grow-1">{{ $member->name }}</label>
                                </div>
                            @endforeach
                        </div>

                        <!-- SERVICE STEPS -->
                        <div class="svc-pane" id="pane-steps">
                            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                <div class="min-w-0">
                                    <h6 class="fw-bold mb-1" style="color:#f8efe6;">Service steps</h6>
                                    <p class="small mb-0" style="color:#9e8780; line-height:1.45;">
                                        List the treatment steps in order so agents and stylists share the exact same details with clients.
                                    </p>
                                </div>
                                <button type="button" id="copyServiceStepsBtn" class="svc-copy-steps-btn" disabled>
                                    <i class="bx bx-copy"></i>
                                    <span id="copyServiceStepsLabel">Copy Steps</span>
                                </button>
                            </div>

                            <div id="serviceStepsError" class="alert py-2 px-3 mb-3 d-none" style="background:rgba(251,113,133,0.12); border:1px solid rgba(251,113,133,0.34); color:#fda4af; border-radius:10px; font-size:12.5px;"></div>

                            <div id="serviceStepsList" class="svc-steps-list"></div>

                            <div id="serviceStepsEmptyReadonly" class="text-center py-4 small d-none" style="color:#8f7a73; border:1px dashed rgba(255,255,255,0.10); border-radius:10px;">
                                No treatment steps documented for this service yet.
                            </div>

                            <div id="serviceStepsControls" class="mt-3">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <button type="button" id="addServiceStepBtn" class="svc-add-step-btn">
                                        <i class="bx bx-plus"></i>
                                        <span>Add Step</span>
                                    </button>
                                    <button type="button" id="togglePasteStepsBtn" class="svc-paste-toggle-btn">
                                        <i class="bx bx-paste"></i>
                                        <span>Paste List</span>
                                    </button>
                                    <span class="small ms-auto" style="font-size:11px; color:#8f7a73;">Tip: Press Enter for next step or paste a multi-line list</span>
                                </div>

                                <div id="pasteStepsBox" class="svc-paste-box d-none">
                                    <label class="form-label small mb-1" for="pasteStepsTextarea" style="color:#cbb8b0;">Paste steps (one per line — numbers and bullets are cleaned automatically)</label>
                                    <textarea id="pasteStepsTextarea" rows="4" class="form-control mb-2" style="background:#0f0a0c; border-color:rgba(217,143,131,0.24); color:#f8efe6; font-size:12.5px;"
                                        placeholder="1. Deep Cleansing&#10;2. Gentle Exfoliation & Steam&#10;3. Fruit Mask&#10;4. Moisturizer & SPF"></textarea>
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <button type="button" id="cancelPasteStepsBtn" class="btn btn-sm" style="color:#cbb8b0; border:1px solid rgba(255,255,255,0.12); border-radius:999px; font-size:12px;">Cancel</button>
                                        <button type="button" id="applyPasteStepsBtn" class="btn btn-sm fw-bold" style="background:linear-gradient(135deg,#d98f83,#c48479); color:#1b1315; border:none; border-radius:999px; font-size:12px; padding:4px 14px;">Insert Steps</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal"
                        style="background:rgba(255,255,255,0.05); border:1px solid rgba(229,184,168,0.24); color:#f2e6e1; border-radius:999px; padding:6px 16px; font-weight:600;">Cancel</button>
                    <button type="submit" class="btn btn-sm fw-bold px-4" id="submitBtn"
                        style="background:linear-gradient(135deg,#d98f83,#c48479); color:#1b1315; border:none; border-radius:999px; padding:7px 20px;">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        const CAN_FULL_EDIT = @json($canFullEditServices);
        const CAN_EDIT_STEPS = @json($canEditServiceSteps);
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

        const modalEl = document.getElementById('serviceModal');
        if (!modalEl) return;

        const formEl = document.getElementById('serviceForm');
        const submitBtn = document.getElementById('submitBtn');
        const stepsListEl = document.getElementById('serviceStepsList');
        const stepsCountBadge = document.getElementById('stepsCountBadge');
        const copyStepsBtn = document.getElementById('copyServiceStepsBtn');
        const copyStepsLabel = document.getElementById('copyServiceStepsLabel');
        const stepsControlsEl = document.getElementById('serviceStepsControls');
        const stepsEmptyReadonlyEl = document.getElementById('serviceStepsEmptyReadonly');
        const stepsErrorEl = document.getElementById('serviceStepsError');
        const pasteBoxEl = document.getElementById('pasteStepsBox');
        const pasteTextareaEl = document.getElementById('pasteStepsTextarea');

        let currentModalMode = CAN_FULL_EDIT ? 'full' : (CAN_EDIT_STEPS ? 'steps_only' : 'readonly');
        let currentServiceId = null;
        let currentServiceName = '';

        function showSvcStepsToast(message, isError = false) {
            const existing = document.getElementById('svcStepsToastEl');
            if (existing) existing.remove();
            const el = document.createElement('div');
            el.id = 'svcStepsToastEl';
            el.className = 'svc-steps-toast' + (isError ? ' is-error' : '');
            el.innerHTML = `<i class="bx ${isError ? 'bx-error-circle' : 'bx-check-circle'} fs-5"></i><span></span>`;
            el.querySelector('span').textContent = message;
            document.body.appendChild(el);
            setTimeout(() => {
                el.style.transition = 'opacity 0.25s ease';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 260);
            }, 3200);
        }

        function setActivePane(paneName) {
            modalEl.querySelectorAll('.svc-nav-item').forEach(i => i.classList.toggle('active', i.dataset.pane === paneName));
            modalEl.querySelectorAll('.svc-pane').forEach(p => p.classList.toggle('active', p.id === 'pane-' + paneName));
        }

        modalEl.querySelectorAll('.svc-nav-item').forEach(item => {
            item.addEventListener('click', () => setActivePane(item.dataset.pane));
        });

        function updateTeamCount() {
            const count = modalEl.querySelectorAll('.team-checkbox:checked').length;
            document.getElementById('teamCountBadge').textContent = count;
        }
        modalEl.querySelectorAll('.team-checkbox').forEach(cb => cb.addEventListener('change', updateTeamCount));

        document.getElementById('teamSelectAll')?.addEventListener('click', function(e) {
            e.preventDefault();
            if (currentModalMode !== 'full') return;
            modalEl.querySelectorAll('.team-checkbox').forEach(cb => cb.checked = true);
            updateTeamCount();
        });
        document.getElementById('teamSelectNone')?.addEventListener('click', function(e) {
            e.preventDefault();
            if (currentModalMode !== 'full') return;
            modalEl.querySelectorAll('.team-checkbox').forEach(cb => cb.checked = false);
            updateTeamCount();
        });

        document.getElementById('servicePhoto')?.addEventListener('change', function() {
            const file = this.files[0];
            const preview = document.getElementById('photoPreview');
            if (!file) {
                preview.classList.add('d-none');
                return;
            }
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('d-none');
        });

        // ---------------- DYNAMIC NUMBERED STEPS LIST ----------------
        function parseMultilineSteps(rawText) {
            return String(rawText || '')
                .split(/\r\n|\r|\n/)
                .map(line => line.replace(/^\s*(?:\d+[\.\)\:\-]\s*|[•\-\*–—]\s*)+/, '').trim())
                .filter(line => line.length > 0)
                .map(line => line.slice(0, 255));
        }

        function getNonEmptySteps() {
            return Array.from(stepsListEl.querySelectorAll('.svc-step-input'))
                .map(inp => inp.value.trim())
                .filter(v => v.length > 0);
        }

        function syncStepsUI() {
            const rows = Array.from(stepsListEl.querySelectorAll('.svc-step-row'));
            const canEditStepsNow = currentModalMode === 'full' || currentModalMode === 'steps_only';

            rows.forEach((row, idx) => {
                const numEl = row.querySelector('.svc-step-num');
                if (numEl) numEl.textContent = String(idx + 1);

                const upBtn = row.querySelector('.js-step-up');
                const downBtn = row.querySelector('.js-step-down');
                if (upBtn) upBtn.disabled = idx === 0;
                if (downBtn) downBtn.disabled = idx === rows.length - 1;
            });

            const nonEmpty = getNonEmptySteps();
            stepsCountBadge.textContent = String(nonEmpty.length);
            copyStepsBtn.disabled = nonEmpty.length === 0;

            if (!canEditStepsNow) {
                stepsEmptyReadonlyEl.classList.toggle('d-none', nonEmpty.length > 0);
            } else {
                stepsEmptyReadonlyEl.classList.add('d-none');
            }
        }

        function createStepRow(value = '', insertAfterRow = null, focusNew = false) {
            const canEditStepsNow = currentModalMode === 'full' || currentModalMode === 'steps_only';
            const row = document.createElement('div');
            row.className = 'svc-step-row';

            const numPill = document.createElement('span');
            numPill.className = 'svc-step-num';
            numPill.textContent = '1';

            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'steps[]';
            input.className = 'svc-step-input';
            input.maxLength = 255;
            input.placeholder = 'e.g. Deep cleansing & steam...';
            input.value = value;
            input.disabled = !canEditStepsNow;

            input.addEventListener('input', syncStepsUI);

            if (canEditStepsNow) {
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        e.stopPropagation();
                        createStepRow('', row, true);
                    }
                });

                input.addEventListener('paste', (e) => {
                    const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
                    if (input.value.trim() === '' && /[\r\n]/.test(text)) {
                        const parsed = parseMultilineSteps(text);
                        if (parsed.length > 1) {
                            e.preventDefault();
                            input.value = parsed[0];
                            let anchor = row;
                            for (let i = 1; i < parsed.length; i++) {
                                anchor = createStepRow(parsed[i], anchor, false);
                            }
                            syncStepsUI();
                            anchor.querySelector('.svc-step-input')?.focus();
                        }
                    }
                });
            }

            row.appendChild(numPill);
            row.appendChild(input);

            if (canEditStepsNow) {
                const upBtn = document.createElement('button');
                upBtn.type = 'button';
                upBtn.className = 'svc-step-icon-btn js-step-up';
                upBtn.title = 'Move Up';
                upBtn.setAttribute('aria-label', 'Move step up');
                upBtn.innerHTML = '<i class="bx bx-up-arrow-alt"></i>';
                upBtn.addEventListener('click', () => {
                    const prev = row.previousElementSibling;
                    if (prev) {
                        stepsListEl.insertBefore(row, prev);
                        syncStepsUI();
                    }
                });

                const downBtn = document.createElement('button');
                downBtn.type = 'button';
                downBtn.className = 'svc-step-icon-btn js-step-down';
                downBtn.title = 'Move Down';
                downBtn.setAttribute('aria-label', 'Move step down');
                downBtn.innerHTML = '<i class="bx bx-down-arrow-alt"></i>';
                downBtn.addEventListener('click', () => {
                    const next = row.nextElementSibling;
                    if (next) {
                        stepsListEl.insertBefore(next, row);
                        syncStepsUI();
                    }
                });

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'svc-step-icon-btn is-remove js-step-remove';
                removeBtn.title = 'Remove Step';
                removeBtn.setAttribute('aria-label', 'Remove step');
                removeBtn.innerHTML = '&times;';
                removeBtn.addEventListener('click', () => {
                    row.remove();
                    syncStepsUI();
                });

                row.appendChild(upBtn);
                row.appendChild(downBtn);
                row.appendChild(removeBtn);
            }

            if (insertAfterRow && insertAfterRow.parentElement === stepsListEl) {
                insertAfterRow.insertAdjacentElement('afterend', row);
            } else {
                stepsListEl.appendChild(row);
            }

            syncStepsUI();
            if (focusNew) {
                input.focus();
            }
            return row;
        }

        function renderSteps(stepsArray) {
            stepsListEl.innerHTML = '';
            const clean = Array.isArray(stepsArray)
                ? stepsArray.map(s => String(s ?? '').trim()).filter(s => s.length > 0)
                : [];
            const canEditStepsNow = currentModalMode === 'full' || currentModalMode === 'steps_only';

            if (clean.length > 0) {
                clean.forEach(stepText => createStepRow(stepText));
            } else if (canEditStepsNow) {
                createStepRow('');
            }
            syncStepsUI();
        }

        document.getElementById('addServiceStepBtn')?.addEventListener('click', () => {
            createStepRow('', null, true);
        });

        document.getElementById('togglePasteStepsBtn')?.addEventListener('click', () => {
            const willOpen = pasteBoxEl.classList.contains('d-none');
            pasteBoxEl.classList.toggle('d-none', !willOpen);
            if (willOpen) {
                pasteTextareaEl.value = '';
                pasteTextareaEl.focus();
            }
        });

        document.getElementById('cancelPasteStepsBtn')?.addEventListener('click', () => {
            pasteBoxEl.classList.add('d-none');
            pasteTextareaEl.value = '';
        });

        document.getElementById('applyPasteStepsBtn')?.addEventListener('click', () => {
            const parsed = parseMultilineSteps(pasteTextareaEl.value);
            if (parsed.length === 0) {
                pasteBoxEl.classList.add('d-none');
                return;
            }
            // If only 1 empty starter row exists, replace it cleanly
            const existingRows = Array.from(stepsListEl.querySelectorAll('.svc-step-row'));
            if (existingRows.length === 1 && existingRows[0].querySelector('.svc-step-input')?.value.trim() === '') {
                existingRows[0].remove();
            }
            let lastRow = null;
            parsed.forEach(stepText => {
                lastRow = createStepRow(stepText, null, false);
            });
            pasteBoxEl.classList.add('d-none');
            pasteTextareaEl.value = '';
            syncStepsUI();
            lastRow?.querySelector('.svc-step-input')?.focus();
        });

        copyStepsBtn?.addEventListener('click', async () => {
            const steps = getNonEmptySteps();
            if (steps.length === 0) return;

            const svcName = (document.getElementById('serviceName')?.value || currentServiceName || 'Service').trim();
            const lines = [`${svcName} — Treatment Steps:`];
            steps.forEach((step, idx) => lines.push(`${idx + 1}. ${step}`));
            const text = lines.join('\n');

            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    ta.remove();
                }
                copyStepsLabel.textContent = 'Copied!';
                showSvcStepsToast('Treatment steps copied to clipboard.');
                setTimeout(() => { copyStepsLabel.textContent = 'Copy Steps'; }, 1800);
            } catch (err) {
                showSvcStepsToast('Unable to copy automatically.', true);
            }
        });

        function applyModalMode(mode) {
            currentModalMode = mode;
            const isFull = mode === 'full';
            const isStepsOnly = mode === 'steps_only';
            const canEditStepsNow = isFull || isStepsOnly;

            modalEl.querySelectorAll('.js-svc-full-field').forEach(el => {
                el.disabled = !isFull;
            });
            document.getElementById('svcBasicReadonlyBanner')?.classList.toggle('d-none', isFull);
            document.getElementById('svcTeamReadonlyBanner')?.classList.toggle('d-none', isFull);
            document.getElementById('teamBulkLinks')?.classList.toggle('d-none', !isFull);
            document.getElementById('servicePhoto')?.classList.toggle('d-none', !isFull);

            stepsControlsEl?.classList.toggle('d-none', !canEditStepsNow);
            pasteBoxEl?.classList.add('d-none');
            stepsErrorEl?.classList.add('d-none');

            if (isFull) {
                submitBtn.classList.remove('d-none');
                submitBtn.textContent = 'Save';
            } else if (isStepsOnly) {
                submitBtn.classList.remove('d-none');
                submitBtn.textContent = 'Save Steps';
            } else {
                submitBtn.classList.add('d-none');
            }
        }

        // Handle Agent Steps-Only Save cleanly via PATCH /services/{service}/steps,
        // and switch back to Basic details if required fields are missing in Full Edit mode.
        formEl?.addEventListener('submit', async (e) => {
            if (currentModalMode === 'readonly') {
                e.preventDefault();
                return;
            }
            if (currentModalMode !== 'steps_only') {
                const nameEl = document.getElementById('serviceName');
                const priceEl = document.getElementById('servicePrice');
                const durEl = document.getElementById('serviceDuration');
                const invalidBasic = [nameEl, priceEl, durEl].find(el => el && !el.checkValidity());
                if (invalidBasic) {
                    e.preventDefault();
                    setActivePane('basic');
                    setTimeout(() => invalidBasic.reportValidity(), 20);
                }
                return; // Standard Admin POST /services or PUT /services/{id}
            }

            e.preventDefault();
            if (!currentServiceId) return;

            stepsErrorEl.classList.add('d-none');
            const stepsPayload = getNonEmptySteps();
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            try {
                const res = await fetch(`/services/${currentServiceId}/steps`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                    },
                    body: JSON.stringify({ steps: stepsPayload }),
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const errMsg = data?.message || 'Unable to save service steps.';
                    stepsErrorEl.textContent = errMsg;
                    stepsErrorEl.classList.remove('d-none');
                    return;
                }

                const savedSteps = Array.isArray(data.steps) ? data.steps : stepsPayload;
                // Update list card pill & cached JSON payload live
                if (typeof window.syncServiceStepsCard === 'function') {
                    window.syncServiceStepsCard(currentServiceId, savedSteps);
                }

                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                showSvcStepsToast(data.message || 'Service steps updated successfully.');
            } catch (err) {
                stepsErrorEl.textContent = 'Network error while saving steps. Please try again.';
                stepsErrorEl.classList.remove('d-none');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Steps';
            }
        });

        window.resetServiceForm = function() {
            currentServiceId = null;
            currentServiceName = '';
            document.getElementById('modalTitle').innerText = 'New Service';
            const subEl = document.getElementById('modalSubtitle');
            if (subEl) { subEl.textContent = ''; subEl.classList.add('d-none'); }

            formEl.action = '{{ route('services.store') }}';
            document.getElementById('formMethod').value = '';
            formEl.reset();
            document.getElementById('serviceRebookingInterval').value = '';
            document.getElementById('photoPreview').classList.add('d-none');

            applyModalMode(CAN_FULL_EDIT ? 'full' : (CAN_EDIT_STEPS ? 'steps_only' : 'readonly'));
            renderSteps([]);
            setActivePane('basic');
            updateTeamCount();
        };

        window.openServiceModal = function(service, staffIds, initialPane = 'basic') {
            if (!service) return;
            currentServiceId = service.id;
            currentServiceName = service.name || '';

            const mode = CAN_FULL_EDIT ? 'full' : (CAN_EDIT_STEPS ? 'steps_only' : 'readonly');
            applyModalMode(mode);

            const titleEl = document.getElementById('modalTitle');
            const subEl = document.getElementById('modalSubtitle');
            if (mode === 'full') {
                titleEl.innerText = 'Edit Service';
                subEl.textContent = service.name || '';
                subEl.classList.toggle('d-none', !service.name);
                formEl.action = `/services/${service.id}`;
                document.getElementById('formMethod').value = 'PUT';
            } else {
                titleEl.innerText = service.name || 'Service Details';
                subEl.textContent = mode === 'steps_only'
                    ? 'Update treatment steps · Pricing & catalog settings are managed by Admin'
                    : 'View-only service treatment details';
                subEl.classList.remove('d-none');
                formEl.action = `/services/${service.id}/steps`;
                document.getElementById('formMethod').value = 'PATCH';
            }

            document.getElementById('serviceName').value = service.name || '';
            document.getElementById('serviceCategory').value = service.category_id || '';
            document.getElementById('serviceTreatmentType').value = service.treatment_type || '';
            document.getElementById('serviceDescription').value = service.description || '';
            document.getElementById('servicePrice').value = service.price ?? '';
            document.getElementById('serviceDuration').value = service.duration ?? '';
            document.getElementById('serviceRebookingInterval').value = service.rebooking_interval_days || '';

            const preview = document.getElementById('photoPreview');
            if (service.photo) {
                preview.src = `/${service.photo}`;
                preview.classList.remove('d-none');
            } else {
                preview.classList.add('d-none');
            }

            const staffSet = new Set((staffIds || []).map(Number));
            modalEl.querySelectorAll('.team-checkbox').forEach(cb => {
                cb.checked = staffSet.has(Number(cb.value));
            });
            updateTeamCount();

            renderSteps(service.steps || []);
            setActivePane(initialPane || (mode === 'full' ? 'basic' : 'steps'));

            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        };

        modalEl.addEventListener('hidden.bs.modal', window.resetServiceForm);
        renderSteps([]);
    })();
</script>
