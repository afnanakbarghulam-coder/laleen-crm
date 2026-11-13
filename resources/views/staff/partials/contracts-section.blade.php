@if ($isAdminViewer ?? false)
    @php
        $contractsCollection = $staffContracts ?? collect();
        $templatesMap = $contractTemplates ?? \App\Models\StaffContract::allTemplates();
        $newCatSentinel = \App\Models\StaffContract::NEW_CATEGORY_SENTINEL;
    @endphp

    <style>
        /* Dark Espresso Modal Controls & Custom Dropdowns (eliminates white/blue native Windows popup) */
        .sc-modal .modal-content {
            background: linear-gradient(180deg, #22161b 0%, #160e12 100%);
            border: 1px solid rgba(229, 184, 168, 0.2);
            color: #f8efe6;
            border-radius: 18px;
        }
        .sc-modal .modal-header,
        .sc-modal .modal-footer {
            border-color: rgba(255, 255, 255, 0.06);
        }
        .sc-modal .form-control,
        .sc-modal .form-select {
            background-color: #1b1214 !important;
            color: #f5ebe6 !important;
            border: 1px solid rgba(229, 184, 168, 0.22);
            border-radius: 10px;
            color-scheme: dark;
        }
        .sc-modal .form-select option {
            background-color: #1b1214 !important;
            color: #f5ebe6 !important;
        }
        .sc-modal .form-control:focus,
        .sc-modal .form-select:focus {
            border-color: #e5b8a8;
            box-shadow: 0 0 0 3px rgba(229, 184, 168, 0.12);
        }

        /* Custom Luxury Popover Select Wrapper */
        .sc-cselect-wrap {
            position: relative;
            width: 100%;
        }
        .sc-cselect-trigger {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            background: #1b1214;
            color: #f5ebe6;
            border: 1px solid rgba(229, 184, 168, 0.22);
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13.5px;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.15s ease;
        }
        .sc-cselect-trigger:hover,
        .sc-cselect-wrap.open .sc-cselect-trigger {
            border-color: #e5b8a8;
        }
        .sc-cselect-menu {
            display: none;
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            right: 0;
            max-height: 230px;
            overflow-y: auto;
            background: #1b1214;
            border: 1px solid rgba(229, 184, 168, 0.28);
            border-radius: 12px;
            padding: 5px;
            z-index: 1095;
            box-shadow: 0 14px 34px rgba(0, 0, 0, 0.58);
        }
        .sc-cselect-wrap.open .sc-cselect-menu {
            display: block;
        }
        .sc-cselect-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            border: none;
            background: transparent;
            color: #f5ebe6;
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 13px;
            text-align: left;
            cursor: pointer;
        }
        .sc-cselect-option:hover,
        .sc-cselect-option.is-selected {
            background: rgba(229, 184, 168, 0.14);
            color: #f8efe6;
        }
        .sc-cselect-option.is-add-new {
            color: #e5b8a8;
            font-weight: 700;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            margin-top: 4px;
            padding-top: 9px;
            border-radius: 0 0 8px 8px;
        }
        .sc-cselect-option:disabled {
            opacity: 0.38;
            cursor: not-allowed;
        }

        /* Compact Row More (⋯) Menu */
        .sc-more-wrap {
            position: relative;
            display: inline-block;
        }
        .sc-more-menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 5px);
            min-width: 185px;
            background: #1b1214;
            border: 1px solid rgba(229, 184, 168, 0.24);
            border-radius: 12px;
            padding: 5px;
            z-index: 1060;
            text-align: left;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.55);
        }
        .sc-more-wrap.open .sc-more-menu {
            display: block;
        }
        .sc-more-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            border: none;
            background: transparent;
            color: #f5ebe6;
            font-size: 12.5px;
            font-weight: 600;
            padding: 7px 11px;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
        }
        .sc-more-item:hover {
            background: rgba(229, 184, 168, 0.13);
            color: #f8efe6;
        }
        .sc-more-item.danger {
            color: #fda4af;
        }
        .sc-more-item.accent {
            color: #d4a373;
        }

        .sc-new-cat-row {
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid rgba(229, 184, 168, 0.18);
        }
    </style>

    <div class="laleen-contract-card mb-4">
        <div class="laleen-contract-header">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h6 class="fw-bold mb-0" style="color: #f8efe6; font-size: 15.5px;">Staff Contracts &amp; Secure PIN Portal</h6>
                <span class="sc-inline-chip">{{ $contractsCollection->count() }} Contracts</span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="sc-btn-rosegold" data-bs-toggle="modal" data-bs-target="#createContractModal">
                    + Create Contract
                </button>
            </div>
        </div>

        <div class="table-responsive" style="overflow: visible;">
            <table class="table team-table align-middle mb-0" id="staffContractsTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Designation</th>
                        <th class="text-end">Compensation</th>
                        <th>Status</th>
                        <th>Expiry</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contractsCollection as $contract)
                        @php
                            $cStaff = $contract->staff;
                            $cStaffName = $cStaff?->name ?? '—';
                            $cPortalUrl = route('contracts.portal.show', $contract->secure_token);
                            $cPdfUrl = route('contracts.portal.pdf', $contract->secure_token);
                            $hasCustomPin = $cStaff ? $cStaff->hasCustomContractPin() : false;
                        @endphp
                        <tr data-contract-id="{{ $contract->id }}">
                            <td>
                                <div class="team-name">{{ $cStaffName }}</div>
                                <div class="text-muted small">{{ $contract->contract_number }} &middot; {{ $contract->branchLabel() }}</div>
                            </td>
                            <td>
                                <span class="fw-semibold" style="color: #f8efe6;">{{ $contract->designationLabel() }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <span class="fw-semibold" style="color: #f8efe6;">
                                    {{ $contract->currency }} {{ number_format($contract->monthlyCompensation(), 2) }}
                                </span>
                            </td>
                            <td>
                                <span class="sc-status-badge sc-status-{{ $contract->status }}">
                                    {{ $contract->statusLabel() }}
                                </span>
                                @if ($contract->signed_at)
                                    <div class="text-muted" style="font-size: 11px;">
                                        Signed {{ $contract->signed_at->copy()->timezone('Asia/Qatar')->format('d M Y') }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                {{ $contract->expiry_date ? $contract->expiry_date->format('d M Y') : 'Open-Ended' }}
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-1 justify-content-end">
                                    <a href="{{ $cPortalUrl }}" target="_blank" rel="noopener" class="sc-action-pill" title="Open official agreement">
                                        Open ↗
                                    </a>
                                    <button type="button"
                                        class="sc-action-pill"
                                        data-copy-contract-link="{{ $cPortalUrl }}"
                                        onclick="copyContractPortalLink(this)"
                                        title="Copy private contract link">
                                        Copy Link
                                    </button>

                                    <div class="sc-more-wrap">
                                        <button type="button"
                                            class="sc-action-pill px-2"
                                            onclick="toggleContractRowMenu(event, this)"
                                            aria-label="More contract actions"
                                            title="More actions">
                                            &#8943;
                                        </button>
                                        <div class="sc-more-menu">
                                            <a href="{{ $cPdfUrl }}" class="sc-more-item">
                                                <span>Download PDF</span>
                                                <span class="text-muted small">PDF</span>
                                            </a>

                                            @if ($cStaff)
                                                <button type="button"
                                                    class="sc-more-item"
                                                    onclick='closeAllContractMenus(); openContractPinModal({{ $cStaff->id }}, @json($cStaffName), {{ $hasCustomPin ? "true" : "false" }})'>
                                                    <span>Set / Reset PIN</span>
                                                    <span class="text-muted small">{{ $hasCustomPin ? 'Custom' : '2456' }}</span>
                                                </button>
                                            @endif

                                            @if ($contract->status === \App\Models\StaffContract::STATUS_DRAFT)
                                                <form method="POST" action="{{ route('staff-contracts.update-status', $contract) }}" class="m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="pending_signature">
                                                    <button type="submit" class="sc-more-item accent">
                                                        <span>Send to Sign</span>
                                                        <span>↗</span>
                                                    </button>
                                                </form>
                                            @endif

                                            @if (!$contract->isLocked())
                                                <form method="POST" action="{{ route('staff-contracts.destroy', $contract) }}" class="m-0"
                                                    onsubmit="return confirm('Remove this unsigned contract?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="sc-more-item danger">
                                                        <span>Delete Contract</span>
                                                        <span>✕</span>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No staff contracts issued yet — click <strong>+ Create Contract</strong> to generate an agreement.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="laleen-contract-guardrail">
            <span style="color: #6ee7b7;">✓ Default staff PIN is 2456 until customized via ⋯ &rarr; Set / Reset PIN</span>
            <span style="color: #fda4af;">✕ Signed Active agreements lock permanently against edits or deletion</span>
        </div>
    </div>

    {{-- Create Contract Modal (Admin Only) --}}
    <div class="modal fade sc-modal" id="createContractModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="POST" action="{{ route('staff-contracts.store') }}" class="modal-content" id="createContractForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Create Staff Contract</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Team Member</label>
                            <select name="staff_id" id="ccStaffSelect" class="form-select sc-dark-select" required onchange="syncContractStaffDefaults()">
                                <option value="">Select staff member</option>
                                @foreach ($allStaff as $s)
                                    <option value="{{ $s->id }}"
                                        data-branch="{{ $s->branch }}"
                                        data-base-salary="{{ (float) ($s->base_salary ?? 0) }}">
                                        {{ $s->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Contract Template / Category</label>
                            <select name="template_key" id="ccTemplateSelect" class="form-select sc-dark-select" required onchange="applyContractTemplateDefaults()">
                                @foreach ($templatesMap as $tKey => $tpl)
                                    <option value="{{ $tKey }}">{{ $tpl['label'] }} ({{ implode('/', $tpl['allowed_currencies']) }})</option>
                                @endforeach
                                <option value="{{ $newCatSentinel }}" data-add-new="1">+ Add Category</option>
                            </select>
                        </div>

                        {{-- Inline + Add Category Builder Row --}}
                        <div class="col-12 d-none" id="ccNewCategoryRow">
                            <div class="sc-new-cat-row">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1" style="color: #e5b8a8;">New Category / Designation Name *</label>
                                        <input type="text"
                                            name="custom_category_name"
                                            id="ccCustomCategoryName"
                                            class="form-control form-control-sm"
                                            maxlength="120"
                                            placeholder="e.g. Nail Technician, Receptionist, Video Editor"
                                            oninput="handleCustomCategoryInput()">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1" style="color: #e5b8a8;">Category Currency</label>
                                        <select name="custom_category_currency" id="ccCustomCategoryCurrency" class="form-select form-select-sm sc-dark-select" onchange="handleCustomCategoryCurrencyChange()">
                                            <option value="QAR">QAR</option>
                                            <option value="PKR">PKR</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="button" class="sc-btn-ghost w-100 justify-content-center" id="ccSaveCategoryBtn" onclick="saveReusableContractCategory()">
                                            Save Category
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Designation <span class="text-muted small">(Auto-synced)</span></label>
                            <input type="text" name="role_title" id="ccRoleTitle" class="form-control" maxlength="120" oninput="window.__ccRoleOverridden = true" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Branch / Location</label>
                            <select name="branch" id="ccBranchSelect" class="form-select sc-dark-select" required>
                                @foreach (\App\Models\StaffContract::BRANCHES as $bKey => $bLabel)
                                    <option value="{{ $bKey }}">{{ $bLabel }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Currency</label>
                            <select name="currency" id="ccCurrencySelect" class="form-select sc-dark-select" required>
                                <option value="QAR">QAR</option>
                                <option value="PKR">PKR</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Compensation</label>
                            <input type="number" name="compensation" id="ccCompensation" class="form-control" min="0" step="0.01" value="0.00" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="{{ now('Asia/Qatar')->toDateString() }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Expiry Date (Optional)</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Initial Status</label>
                            <select name="status" class="form-select sc-dark-select">
                                <option value="pending_signature" selected>Pending Signature</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">4-Digit PIN (Optional)</label>
                            <input type="text" name="pin" class="form-control" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="Default: 2456">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Contract Clauses &amp; Terms</label>
                            <textarea name="clauses_body" id="ccClausesBody" class="form-control" rows="7" oninput="window.__ccClausesOverridden = true" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sc-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="sc-btn-rosegold">Issue Contract &amp; Generate Private Link</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Set / Reset 4-Digit PIN Modal (Admin Only) --}}
    <div class="modal fade sc-modal" id="contractPinModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" id="contractPinForm" class="modal-content">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="contractPinModalTitle">Set / Reset 4-Digit Contract PIN</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" id="contractPinStaffPickerWrap">
                        <label class="form-label">Team Member</label>
                        <select id="contractPinStaffSelect" class="form-select sc-dark-select" onchange="updatePinFormAction()">
                            @foreach ($allStaff as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="contractPinValueInput">New 4-Digit PIN</label>
                        <input type="text" id="contractPinValueInput" name="pin" class="form-control text-center fw-bold"
                            style="letter-spacing: 0.35em; font-size: 18px;"
                            inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="2456" required>
                    </div>
                    <p class="text-muted small mb-0" id="contractPinStatusHint">
                        Staff enter this 4-digit PIN on their private link to unlock contract terms for 10 minutes.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sc-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="sc-btn-rosegold">Save 4-Digit PIN</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const NEW_CATEGORY_SENTINEL = @json($newCatSentinel);
            const CONTRACT_TEMPLATES = @json($templatesMap);

            window.__ccRoleOverridden = false;
            window.__ccClausesOverridden = false;

            function buildStandardCustomClauses(designation, currency) {
                const role = (designation || '').trim() || 'Team Member';
                const curr = (currency || 'QAR').toUpperCase();
                return [
                    `1. DESIGNATION & OPERATIONAL DUTIES\nThe Team Member serves as ${role} for Laleen Beauty Salon and shall perform all assigned operational, service, or digital responsibilities in accordance with Laleen's quality protocols and shift schedules.`,
                    `2. MONTHLY COMPENSATION (${curr})\nAgreed Monthly Compensation is denominated in ${curr} as stated in the Compensation Summary of this agreement and settled monthly in accordance with Laleen CRM attendance and payroll policy.`,
                    `3. QUALITY, PROFESSIONAL CONDUCT & SLA DISCIPLINE\nThe Team Member shall uphold Laleen's luxury presentation, hygiene, punctuality, and service standards across every client and team interaction, logging required shift and task updates accurately in Laleen CRM.`,
                    `4. CONFIDENTIALITY & BRAND PROTECTION\nClient records, phone numbers, pricing, creative assets, and internal CRM data remain the exclusive confidential property of Laleen Beauty Salon. Unauthorized sharing, off-system solicitation, or data export is strictly prohibited.`,
                    `5. ATTENDANCE, LEAVE & SEPARATION NOTICE\nShift schedules, approved leave, and notice periods follow published Laleen CRM rosters, signed salon policy, and applicable labour regulations.`
                ].join('\n\n');
            }

            /* Custom Dark Popover Enhancer for <select class="sc-dark-select"> */
            function refreshCustomSelectUI(selectEl) {
                if (!selectEl || !selectEl._cselectWrap) return;
                const wrap = selectEl._cselectWrap;
                const triggerLabel = wrap.querySelector('.sc-cselect-label');
                const menu = wrap.querySelector('.sc-cselect-menu');
                const selectedOpt = selectEl.options[selectEl.selectedIndex];
                if (triggerLabel) {
                    triggerLabel.textContent = selectedOpt ? selectedOpt.textContent : 'Select...';
                }
                if (!menu) return;
                menu.innerHTML = '';
                Array.from(selectEl.options).forEach(opt => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'sc-cselect-option'
                        + (opt.value === selectEl.value ? ' is-selected' : '')
                        + (opt.dataset.addNew === '1' ? ' is-add-new' : '');
                    btn.disabled = !!opt.disabled;
                    btn.textContent = opt.textContent;
                    btn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        if (opt.disabled) return;
                        selectEl.value = opt.value;
                        wrap.classList.remove('open');
                        refreshCustomSelectUI(selectEl);
                        selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    menu.appendChild(btn);
                });
            }

            function initCustomDarkSelects() {
                document.querySelectorAll('select.sc-dark-select').forEach(selectEl => {
                    if (selectEl._cselectWrap) {
                        refreshCustomSelectUI(selectEl);
                        return;
                    }
                    const wrap = document.createElement('div');
                    wrap.className = 'sc-cselect-wrap';
                    selectEl.parentNode.insertBefore(wrap, selectEl);
                    selectEl.style.display = 'none';
                    wrap.appendChild(selectEl);

                    const trigger = document.createElement('button');
                    trigger.type = 'button';
                    trigger.className = 'sc-cselect-trigger';
                    trigger.innerHTML = '<span class="sc-cselect-label text-truncate"></span><span style="color:#e5b8a8;font-size:11px;">▾</span>';

                    const menu = document.createElement('div');
                    menu.className = 'sc-cselect-menu';

                    trigger.addEventListener('click', function (e) {
                        e.stopPropagation();
                        const wasOpen = wrap.classList.contains('open');
                        document.querySelectorAll('.sc-cselect-wrap.open').forEach(w => w.classList.remove('open'));
                        if (!wasOpen) {
                            refreshCustomSelectUI(selectEl);
                            wrap.classList.add('open');
                        }
                    });

                    wrap.appendChild(trigger);
                    wrap.appendChild(menu);
                    selectEl._cselectWrap = wrap;
                    refreshCustomSelectUI(selectEl);
                });
            }

            window.applyContractTemplateDefaults = function () {
                const tplSelect = document.getElementById('ccTemplateSelect');
                if (!tplSelect) return;

                const newCatRow = document.getElementById('ccNewCategoryRow');
                const customNameInput = document.getElementById('ccCustomCategoryName');
                const customCurrSelect = document.getElementById('ccCustomCategoryCurrency');
                const roleInput = document.getElementById('ccRoleTitle');
                const currencySelect = document.getElementById('ccCurrencySelect');
                const branchSelect = document.getElementById('ccBranchSelect');
                const clausesArea = document.getElementById('ccClausesBody');

                window.__ccRoleOverridden = false;
                window.__ccClausesOverridden = false;

                if (tplSelect.value === NEW_CATEGORY_SENTINEL) {
                    newCatRow?.classList.remove('d-none');
                    if (customNameInput) {
                        customNameInput.required = true;
                        setTimeout(() => customNameInput.focus(), 80);
                    }
                    if (currencySelect) {
                        Array.from(currencySelect.options).forEach(opt => opt.disabled = false);
                        currencySelect.value = customCurrSelect?.value || 'QAR';
                        refreshCustomSelectUI(currencySelect);
                    }
                    const catLabel = customNameInput?.value?.trim() || '';
                    if (roleInput) roleInput.value = catLabel;
                    if (clausesArea) {
                        clausesArea.value = buildStandardCustomClauses(catLabel || 'Specialist', currencySelect?.value || 'QAR');
                    }
                    return;
                }

                newCatRow?.classList.add('d-none');
                if (customNameInput) customNameInput.required = false;

                const tpl = CONTRACT_TEMPLATES[tplSelect.value];
                if (!tpl) return;

                if (roleInput) roleInput.value = tpl.default_role || tpl.label || '';
                if (clausesArea) clausesArea.value = tpl.clauses || '';

                if (currencySelect) {
                    const allowed = tpl.allowed_currencies || ['QAR', 'PKR'];
                    Array.from(currencySelect.options).forEach(opt => {
                        opt.disabled = !allowed.includes(opt.value);
                    });
                    currencySelect.value = tpl.default_currency || allowed[0];
                    refreshCustomSelectUI(currencySelect);
                }

                const staffSelect = document.getElementById('ccStaffSelect');
                if (branchSelect) {
                    if (tplSelect.value === 'remote_chat_creative' || tpl.default_branch === 'remote') {
                        branchSelect.value = 'remote';
                    } else if (!staffSelect || !staffSelect.value) {
                        branchSelect.value = tpl.default_branch || 'old_airport';
                    }
                    refreshCustomSelectUI(branchSelect);
                }
            };

            window.handleCustomCategoryInput = function () {
                const nameVal = document.getElementById('ccCustomCategoryName')?.value?.trim() || '';
                const currVal = document.getElementById('ccCustomCategoryCurrency')?.value || 'QAR';
                const roleInput = document.getElementById('ccRoleTitle');
                const clausesArea = document.getElementById('ccClausesBody');

                if (roleInput && !window.__ccRoleOverridden) {
                    roleInput.value = nameVal;
                }
                if (clausesArea && !window.__ccClausesOverridden) {
                    clausesArea.value = buildStandardCustomClauses(nameVal || 'Specialist', currVal);
                }
            };

            window.handleCustomCategoryCurrencyChange = function () {
                const currVal = document.getElementById('ccCustomCategoryCurrency')?.value || 'QAR';
                const currencySelect = document.getElementById('ccCurrencySelect');
                const branchSelect = document.getElementById('ccBranchSelect');
                if (currencySelect) {
                    currencySelect.value = currVal;
                    refreshCustomSelectUI(currencySelect);
                }
                if (branchSelect && currVal === 'PKR') {
                    branchSelect.value = 'remote';
                    refreshCustomSelectUI(branchSelect);
                }
                handleCustomCategoryInput();
            };

            window.saveReusableContractCategory = function () {
                const nameInput = document.getElementById('ccCustomCategoryName');
                const currSelect = document.getElementById('ccCustomCategoryCurrency');
                const branchSelect = document.getElementById('ccBranchSelect');
                const clausesArea = document.getElementById('ccClausesBody');
                const tplSelect = document.getElementById('ccTemplateSelect');
                const saveBtn = document.getElementById('ccSaveCategoryBtn');

                const label = (nameInput?.value || '').trim();
                if (label.length < 2) {
                    if (typeof showStaffToast === 'function') {
                        showStaffToast('Enter a category name first (e.g. Nail Technician).', 'error');
                    }
                    nameInput?.focus();
                    return;
                }

                if (saveBtn) saveBtn.disabled = true;

                fetch(`{{ route('staff-contracts.categories.store') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        label: label,
                        default_currency: currSelect?.value || 'QAR',
                        default_branch: branchSelect?.value || 'old_airport',
                        clauses: clausesArea?.value || '',
                    }),
                })
                    .then(r => r.json().then(data => ({ ok: r.ok, data })))
                    .then(({ ok, data }) => {
                        if (ok && data.success && data.slug) {
                            CONTRACT_TEMPLATES[data.slug] = data.template;
                            let existingOpt = Array.from(tplSelect.options).find(o => o.value === data.slug);
                            const optText = `${data.template.label} (${data.template.allowed_currencies.join('/')})`;
                            if (!existingOpt) {
                                existingOpt = document.createElement('option');
                                existingOpt.value = data.slug;
                                const addNewOpt = tplSelect.querySelector(`option[value="${NEW_CATEGORY_SENTINEL}"]`);
                                tplSelect.insertBefore(existingOpt, addNewOpt);
                            }
                            existingOpt.textContent = optText;
                            tplSelect.value = data.slug;
                            refreshCustomSelectUI(tplSelect);
                            applyContractTemplateDefaults();

                            if (typeof showStaffToast === 'function') {
                                showStaffToast(`Saved "${data.template.label}" as a reusable contract category.`);
                            }
                        } else if (typeof showStaffToast === 'function') {
                            showStaffToast(data.message || 'Could not save category.', 'error');
                        }
                    })
                    .finally(() => {
                        if (saveBtn) saveBtn.disabled = false;
                    });
            };

            window.syncContractStaffDefaults = function () {
                const staffSelect = document.getElementById('ccStaffSelect');
                if (!staffSelect || !staffSelect.value) return;
                const opt = staffSelect.options[staffSelect.selectedIndex];
                const branchSelect = document.getElementById('ccBranchSelect');
                const compInput = document.getElementById('ccCompensation');
                const tplSelect = document.getElementById('ccTemplateSelect');

                if (branchSelect && opt.dataset.branch && tplSelect?.value !== 'remote_chat_creative') {
                    branchSelect.value = opt.dataset.branch;
                    refreshCustomSelectUI(branchSelect);
                }
                if (compInput && Number(opt.dataset.baseSalary || 0) > 0) {
                    compInput.value = Number(opt.dataset.baseSalary).toFixed(2);
                }
            };

            window.copyContractPortalLink = function (btn) {
                const url = btn?.dataset?.copyContractLink;
                if (!url) return;
                const flashCopied = () => {
                    const originalText = btn.textContent;
                    btn.textContent = '✓ Copied!';
                    setTimeout(() => { btn.textContent = originalText; }, 1600);
                    if (typeof showStaffToast === 'function') {
                        showStaffToast('Copied private contract link.');
                    }
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(flashCopied);
                } else {
                    flashCopied();
                }
            };

            window.closeAllContractMenus = function () {
                document.querySelectorAll('.sc-more-wrap.open').forEach(w => w.classList.remove('open'));
            };

            window.toggleContractRowMenu = function (event, btn) {
                if (event) event.stopPropagation();
                const wrap = btn?.closest('.sc-more-wrap');
                if (!wrap) return;
                const wasOpen = wrap.classList.contains('open');
                closeAllContractMenus();
                if (!wasOpen) wrap.classList.add('open');
            };

            window.updatePinFormAction = function () {
                const staffSelect = document.getElementById('contractPinStaffSelect');
                const form = document.getElementById('contractPinForm');
                if (staffSelect && form && staffSelect.value) {
                    form.action = `/staffs/${staffSelect.value}/contract-pin`;
                }
            };

            window.openContractPinModal = function (staffId = null, staffName = '', hasCustomPin = false) {
                const modalEl = document.getElementById('contractPinModal');
                const selectEl = document.getElementById('contractPinStaffSelect');
                const titleEl = document.getElementById('contractPinModalTitle');
                const hintEl = document.getElementById('contractPinStatusHint');
                const inputEl = document.getElementById('contractPinValueInput');
                if (!modalEl || !selectEl) return;

                if (staffId) {
                    selectEl.value = String(staffId);
                    refreshCustomSelectUI(selectEl);
                    titleEl.textContent = `Set / Reset PIN — ${staffName}`;
                    hintEl.textContent = hasCustomPin
                        ? `${staffName} currently uses a custom 4-digit PIN. Enter a new 4-digit PIN to reset it.`
                        : `${staffName} currently uses default PIN 2456. Enter a new 4-digit PIN to customize it.`;
                } else {
                    titleEl.textContent = 'Set / Reset 4-Digit Contract PIN';
                    hintEl.textContent = 'Staff enter this 4-digit PIN on their private link to unlock contract terms for 10 minutes.';
                }

                if (inputEl) inputEl.value = '';
                updatePinFormAction();
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            };

            document.addEventListener('click', function (e) {
                if (!e.target.closest('.sc-cselect-wrap')) {
                    document.querySelectorAll('.sc-cselect-wrap.open').forEach(w => w.classList.remove('open'));
                }
                if (!e.target.closest('.sc-more-wrap')) {
                    closeAllContractMenus();
                }
            });

            document.addEventListener('DOMContentLoaded', function () {
                initCustomDarkSelects();
                applyContractTemplateDefaults();
                updatePinFormAction();
            });
        })();
    </script>
@else
    <div class="laleen-contract-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="fw-bold mb-1" style="color: #f8efe6; font-size: 15px;">Staff Contracts &amp; Secure PIN Portal</h6>
                <p class="small mb-0" style="color: #b8a29a;">
                    Contract templates and PIN controls are restricted to Admin accounts. Team members view and sign their own agreement via their private contract link.
                </p>
            </div>
            <span class="sc-inline-chip">Admin Protected</span>
        </div>
    </div>
@endif
