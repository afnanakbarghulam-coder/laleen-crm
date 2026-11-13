@if (($isAdminViewer ?? false) && auth()->user()?->isAdmin())
    @php
        $contractsCollection = $staffContracts ?? collect();
        $templatesMap = $contractTemplates ?? \App\Models\StaffContract::allTemplates();
        $newCatSentinel = \App\Models\StaffContract::NEW_CATEGORY_SENTINEL;
        $salonMembers = $contractSalonMembers ?? collect($allStaff ?? [])->map(fn ($s) => [
            'key' => 'staff:' . $s->id,
            'type' => 'staff',
            'id' => $s->id,
            'name' => $s->name,
            'tag' => \App\Models\StaffContract::BRANCHES[$s->branch] ?? 'Salon Staff',
            'template_key' => 'salon_beautician',
            'role_title' => 'Salon Beautician',
            'branch' => array_key_exists((string) $s->branch, \App\Models\StaffContract::BRANCHES) ? (string) $s->branch : 'old_airport',
            'currency' => 'QAR',
            'base_salary' => 0.0,
            'has_custom_pin' => method_exists($s, 'hasCustomContractPin') ? $s->hasCustomContractPin() : false,
        ])->values();
        $accessMembers = $contractAccessMembers ?? collect();
        $defaultTemplateKey = array_key_first($templatesMap) ?: 'salon_beautician';
        $defaultTemplate = $templatesMap[$defaultTemplateKey] ?? [
            'label' => 'Salon Beautician',
            'default_role' => 'Salon Beautician',
            'default_currency' => 'QAR',
            'default_branch' => 'old_airport',
            'clauses' => '',
        ];
        $defaultStartDate = now('Asia/Qatar')->toDateString();
    @endphp

    <style>
        [x-cloak] { display: none !important; }

        /* Dark Espresso Modal Controls & Custom Dropdowns (zero clipping, zero white/blue native popup) */
        .sc-modal .modal-dialog,
        .sc-modal .modal-content,
        .sc-modal .modal-body {
            overflow: visible !important;
        }
        .sc-modal .modal-content {
            background: linear-gradient(180deg, #22161b 0%, #160e12 100%) !important;
            border: 1px solid rgba(229, 184, 168, 0.2) !important;
            color: #f8efe6;
            border-radius: 18px !important;
        }
        .sc-modal .modal-header,
        .sc-modal .modal-footer {
            border-color: rgba(255, 255, 255, 0.06) !important;
        }
        .sc-modal .modal-header .btn-close {
            background-color: #1b1214 !important;
            border: 1px solid rgba(229, 184, 168, 0.24) !important;
            border-radius: 10px !important;
            filter: invert(1) grayscale(100%) brightness(180%);
            opacity: 0.85;
        }
        .sc-modal .modal-header .btn-close:hover {
            border-color: #e5b8a8 !important;
            opacity: 1;
        }
        .sc-modal .form-control,
        .sc-modal .form-select {
            background-color: #1b1214 !important;
            color: #f5ebe6 !important;
            border: 1px solid rgba(229, 184, 168, 0.22);
            border-radius: 10px;
            color-scheme: dark;
        }
        .sc-modal .form-select {
            padding-right: 1.75rem !important;
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
        .sc-cselect-wrap.is-open {
            z-index: 70;
        }
        .sc-cselect-trigger {
            width: 100%;
            min-height: 39px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            background: #1b1214;
            color: #f5ebe6;
            border: 1px solid rgba(229, 184, 168, 0.22);
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            line-height: 1.35;
            text-align: left;
            cursor: pointer;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .sc-cselect-trigger:hover,
        .sc-cselect-wrap.is-open .sc-cselect-trigger {
            border-color: #e5b8a8;
            box-shadow: 0 0 0 3px rgba(229, 184, 168, 0.1);
        }
        .sc-cselect-label {
            display: inline-flex;
            align-items: center;
            min-width: 0;
            flex: 1 1 auto;
            overflow: hidden;
            white-space: nowrap;
        }
        .sc-cselect-caret {
            color: #e5b8a8;
            font-size: 11px;
            flex-shrink: 0;
        }
        .sc-cselect-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            min-width: 100%;
            max-height: 260px;
            overflow-y: auto;
            background: #1b1214;
            border: 1px solid rgba(229, 184, 168, 0.3);
            border-radius: 12px;
            padding: 6px;
            z-index: 1120;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.68);
        }
        .sc-cselect-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
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
            background: rgba(229, 184, 168, 0.15);
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
        .sc-cselect-group-label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #b8a29a;
            padding: 8px 10px 4px;
            margin-top: 4px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }
        .sc-cselect-group-label:first-child {
            margin-top: 0;
            border-top: none;
        }
        .sc-cselect-tag {
            display: inline-flex;
            align-items: center;
            padding: 1px 8px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 600;
            background: rgba(229, 184, 168, 0.12);
            color: #e5b8a8;
            white-space: nowrap;
            margin-left: 8px;
            flex-shrink: 0;
        }
        .sc-cselect-tag.is-access {
            background: rgba(212, 163, 115, 0.15);
            color: #d4a373;
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

        .sc-resig-row {
            padding: 10px 14px;
            border-radius: 12px;
            background: rgba(212, 163, 115, 0.08);
            border: 1px solid rgba(212, 163, 115, 0.24);
        }

        .sc-check {
            cursor: pointer;
            background-color: #1b1214;
            border-color: rgba(229, 184, 168, 0.35);
        }

        .sc-check:checked {
            background-color: #e5b8a8 !important;
            border-color: #e5b8a8 !important;
        }
    </style>

    <div class="laleen-contract-card mb-4">
        <div class="laleen-contract-header">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h6 class="fw-bold mb-0" style="color: #f8efe6; font-size: 15.5px;">Staff Contracts &amp; Secure PIN Portal</h6>
                <span class="sc-inline-chip">{{ $contractsCollection->count() }} Contracts</span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="button" class="sc-btn-rosegold" data-bs-toggle="modal" data-bs-target="#createContractModal" onclick="openCreateContractModal()">
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
                        <th>Status</th>
                        <th>Expiry</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contractsCollection as $contract)
                        @php
                            $cMemberKey = $contract->memberKey();
                            $cStaffName = $contract->memberName();
                            $cAdminShowUrl = route('staff-contracts.show', $contract);
                            $cPortalUrl = route('contracts.portal.show', $contract->secure_token);
                            $cAdminPdfUrl = route('staff-contracts.pdf', $contract);
                            $cEditPayloadUrl = route('staff-contracts.edit-payload', $contract);
                            $hasCustomPin = $contract->hasCustomMemberPin();
                        @endphp
                        <tr data-contract-id="{{ $contract->id }}">
                            <td>
                                <div class="team-name">{{ $cStaffName }}</div>
                                <div class="text-muted small">{{ $contract->contract_number }} &middot; {{ $contract->branchLabel() }}</div>
                            </td>
                            <td>
                                <span class="fw-semibold" style="color: #f8efe6;">{{ $contract->designationLabel() }}</span>
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
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex align-items-center gap-1 justify-content-end flex-nowrap text-nowrap" style="white-space: nowrap;">
                                    <a href="{{ $cAdminShowUrl }}" target="_blank" rel="noopener" class="sc-action-pill" title="Open official agreement">
                                        Open ↗
                                    </a>
                                    <button type="button"
                                        class="sc-action-pill"
                                        data-edit-contract-id="{{ $contract->id }}"
                                        data-edit-payload-url="{{ $cEditPayloadUrl }}"
                                        onclick="openEditContractModal({{ $contract->id }})"
                                        title="Edit contract">
                                        Edit
                                    </button>
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
                                            <a href="{{ $cAdminPdfUrl }}" class="sc-more-item">
                                                <span>Download PDF</span>
                                                <span class="text-muted small">PDF</span>
                                            </a>

                                            @if ($cMemberKey !== '')
                                                <button type="button"
                                                    class="sc-more-item"
                                                    data-member-key="{{ $cMemberKey }}"
                                                    data-member-name="{{ $cStaffName }}"
                                                    data-has-custom-pin="{{ $hasCustomPin ? '1' : '0' }}"
                                                    onclick="handleOpenPinFromRow(this)">
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
                            <td colspan="5" class="text-center text-muted py-4">
                                No staff contracts issued yet — click <strong>+ Create Contract</strong> to generate an agreement.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="laleen-contract-guardrail">
            <span style="color: #6ee7b7;">✓ Default staff PIN is 2456 until customized via ⋯ &rarr; Set / Reset PIN</span>
            <span style="color: #fda4af;">✕ Signed Active agreements lock permanently for staff and are editable only by Admin</span>
        </div>
    </div>

    {{-- Create / Edit Contract Modal (Admin Only — Declarative Alpine State via Alpine.data('contractModal')) --}}
    <div class="modal fade sc-modal" id="createContractModal" tabindex="-1" aria-hidden="true" x-data="contractModal"
        @open-contract-create.window="resetCreateMode()"
        @open-contract-edit.window="openEditMode($event.detail)">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="POST" action="{{ route('staff-contracts.store') }}" :action="mode === 'edit' ? editActionUrl : storeActionUrl" class="modal-content" id="createContractForm" @submit="validateBeforeSubmit($event)">
                @csrf
                <template x-if="mode === 'edit'">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" x-text="mode === 'edit' ? ('Edit Staff Contract · ' + editingContractNumber) : 'Create Staff Contract'">Create Staff Contract</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        {{-- 1. Team Member Custom Dropdown (Salon Staff + Staff Access) --}}
                        <div class="col-md-6">
                            <label class="form-label">Team Member</label>
                            <div class="sc-cselect-wrap" :class="{ 'is-open': openMenu === 'member' }" @click.outside="closeMenu('member')">
                                <input type="hidden" name="team_member" :value="selectedMemberKey">
                                <select id="ccStaffSelect" class="d-none" aria-hidden="true">
                                    <option value="">Select team member</option>
                                    @if ($salonMembers->isNotEmpty())
                                        <optgroup label="Salon Staff">
                                            @foreach ($salonMembers as $m)
                                                <option value="{{ $m['key'] }}" data-template-key="{{ $m['template_key'] }}">{{ $m['name'] }} — {{ $m['tag'] }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if ($accessMembers->isNotEmpty())
                                        <optgroup label="Staff Access · CRM Team">
                                            @foreach ($accessMembers as $m)
                                                <option value="{{ $m['key'] }}" data-template-key="{{ $m['template_key'] }}">{{ $m['name'] }} — {{ $m['tag'] }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>

                                <button type="button" class="sc-cselect-trigger" id="ccStaffTrigger" @click="toggleMenu('member')" :aria-expanded="openMenu === 'member'">
                                    <span class="sc-cselect-label">
                                        <template x-if="selectedMemberKey">
                                            <span class="d-inline-flex align-items-center text-truncate">
                                                <span class="text-truncate" x-text="selectedMemberName"></span>
                                                <span class="sc-cselect-tag" :class="{ 'is-access': selectedMemberType === 'user' }" x-show="selectedMemberTag" x-text="selectedMemberTag"></span>
                                            </span>
                                        </template>
                                        <template x-if="!selectedMemberKey">
                                            <span style="color: #b8a29a;">Select team member</span>
                                        </template>
                                    </span>
                                    <span class="sc-cselect-caret">▾</span>
                                </button>

                                <div class="sc-cselect-menu z-50" x-show="openMenu === 'member'" x-cloak>
                                    <template x-if="salonMembers.length > 0">
                                        <div>
                                            <div class="sc-cselect-group-label">Salon Staff</div>
                                            <template x-for="m in salonMembers" :key="m.key">
                                                <button type="button"
                                                    class="sc-cselect-option"
                                                    :class="{ 'is-selected': selectedMemberKey === m.key }"
                                                    @click="selectMember(m)">
                                                    <span class="text-truncate" x-text="m.name"></span>
                                                    <span class="sc-cselect-tag" x-text="m.tag"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="accessMembers.length > 0">
                                        <div>
                                            <div class="sc-cselect-group-label">Staff Access &middot; CRM Team</div>
                                            <template x-for="m in accessMembers" :key="m.key">
                                                <button type="button"
                                                    class="sc-cselect-option"
                                                    :class="{ 'is-selected': selectedMemberKey === m.key }"
                                                    @click="selectMember(m)">
                                                    <span class="text-truncate" x-text="m.name"></span>
                                                    <span class="sc-cselect-tag is-access" x-text="m.tag"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div class="small mt-1" style="color: #fda4af;" x-show="memberError" x-cloak x-text="memberError"></div>
                        </div>

                        {{-- 2. Contract Template / Category Custom Dropdown --}}
                        <div class="col-md-6">
                            <label class="form-label">Contract Template / Category</label>
                            <div class="sc-cselect-wrap" :class="{ 'is-open': openMenu === 'template' }" @click.outside="closeMenu('template')">
                                <input type="hidden" name="template_key" id="ccTemplateSelect" :value="templateKey">
                                {{-- Hidden server-rendered labels for test & accessibility parity --}}
                                <div class="d-none" aria-hidden="true">
                                    @foreach ($templatesMap as $tKey => $tpl)
                                        <span>{{ $tpl['label'] }} ({{ implode('/', $tpl['allowed_currencies']) }})</span>
                                    @endforeach
                                    <span>+ Add Category</span>
                                </div>

                                <button type="button" class="sc-cselect-trigger" id="ccTemplateTrigger" @click="toggleMenu('template')" :aria-expanded="openMenu === 'template'">
                                    <span class="sc-cselect-label text-truncate" x-text="currentTemplateLabel()"></span>
                                    <span class="sc-cselect-caret">▾</span>
                                </button>

                                <div class="sc-cselect-menu z-50" x-show="openMenu === 'template'" x-cloak>
                                    <template x-for="entry in templateEntries()" :key="entry.key">
                                        <button type="button"
                                            class="sc-cselect-option"
                                            :class="{ 'is-selected': templateKey === entry.key }"
                                            @click="selectTemplate(entry.key)">
                                            <span class="text-truncate" x-text="entry.display"></span>
                                        </button>
                                    </template>
                                    <button type="button"
                                        class="sc-cselect-option is-add-new"
                                        :class="{ 'is-selected': templateKey === newCatSentinel }"
                                        @click="selectTemplate(newCatSentinel)">
                                        <span>+ Add Category</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Inline + Add Category Builder Row --}}
                        <div class="col-12" id="ccNewCategoryRow" x-show="templateKey === newCatSentinel" x-cloak>
                            <div class="sc-new-cat-row">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label small mb-1" style="color: #e5b8a8;">New Category / Designation Name *</label>
                                        <input type="text"
                                            name="custom_category_name"
                                            id="ccCustomCategoryName"
                                            x-ref="customCatInput"
                                            x-model="customCategoryName"
                                            @input="onCustomCategoryInput()"
                                            :required="templateKey === newCatSentinel"
                                            class="form-control form-control-sm"
                                            maxlength="120"
                                            placeholder="e.g. Nail Technician, Receptionist, Video Editor">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1" style="color: #e5b8a8;">Category Currency</label>
                                        <div class="sc-cselect-wrap" :class="{ 'is-open': openMenu === 'customCurrency' }" @click.outside="closeMenu('customCurrency')">
                                            <input type="hidden" name="custom_category_currency" :value="customCategoryCurrency">
                                            <button type="button" class="sc-cselect-trigger py-1" @click="toggleMenu('customCurrency')">
                                                <span class="sc-cselect-label" x-text="customCategoryCurrency"></span>
                                                <span class="sc-cselect-caret">▾</span>
                                            </button>
                                            <div class="sc-cselect-menu z-50" x-show="openMenu === 'customCurrency'" x-cloak>
                                                <button type="button" class="sc-cselect-option" :class="{ 'is-selected': customCategoryCurrency === 'QAR' }" @click="selectCustomCurrency('QAR')">QAR</button>
                                                <button type="button" class="sc-cselect-option" :class="{ 'is-selected': customCategoryCurrency === 'PKR' }" @click="selectCustomCurrency('PKR')">PKR</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="button" class="sc-btn-ghost w-100 justify-content-center" id="ccSaveCategoryBtn" :disabled="savingCategory" @click="saveReusableCategory()">
                                            <span x-text="savingCategory ? 'Saving...' : 'Save Category'">Save Category</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 3. Designation, Branch, Currency, Compensation --}}
                        <div class="col-md-4">
                            <label class="form-label">Designation</label>
                            <input type="text"
                                name="role_title"
                                id="ccRoleTitle"
                                class="form-control"
                                maxlength="120"
                                value="{{ $defaultTemplate['default_role'] ?? 'Salon Beautician' }}"
                                x-model="roleTitle"
                                @input="roleOverridden = true"
                                required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Branch / Location</label>
                            <div class="sc-cselect-wrap" :class="{ 'is-open': openMenu === 'branch' }" @click.outside="closeMenu('branch')">
                                <input type="hidden" name="branch" id="ccBranchSelect" :value="branch">
                                <button type="button" class="sc-cselect-trigger" @click="toggleMenu('branch')">
                                    <span class="sc-cselect-label text-truncate" x-text="branches[branch] || 'Old Airport'"></span>
                                    <span class="sc-cselect-caret">▾</span>
                                </button>
                                <div class="sc-cselect-menu z-50" x-show="openMenu === 'branch'" x-cloak>
                                    @foreach (\App\Models\StaffContract::BRANCHES as $bKey => $bLabel)
                                        <button type="button"
                                            class="sc-cselect-option"
                                            :class="{ 'is-selected': branch === '{{ $bKey }}' }"
                                            @click="branch = '{{ $bKey }}'; openMenu = null;">
                                            <span>{{ $bLabel }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Currency</label>
                            <div class="sc-cselect-wrap" :class="{ 'is-open': openMenu === 'currency' }" @click.outside="closeMenu('currency')">
                                <input type="hidden" name="currency" id="ccCurrencySelect" :value="currency">
                                <button type="button" class="sc-cselect-trigger" @click="toggleMenu('currency')">
                                    <span class="sc-cselect-label" x-text="currency"></span>
                                    <span class="sc-cselect-caret">▾</span>
                                </button>
                                <div class="sc-cselect-menu z-50" x-show="openMenu === 'currency'" x-cloak>
                                    <button type="button"
                                        class="sc-cselect-option"
                                        :class="{ 'is-selected': currency === 'QAR' }"
                                        :disabled="!isCurrencyAllowed('QAR')"
                                        @click="if (isCurrencyAllowed('QAR')) { currency = 'QAR'; openMenu = null; }">
                                        <span>QAR</span>
                                    </button>
                                    <button type="button"
                                        class="sc-cselect-option"
                                        :class="{ 'is-selected': currency === 'PKR' }"
                                        :disabled="!isCurrencyAllowed('PKR')"
                                        @click="if (isCurrencyAllowed('PKR')) { currency = 'PKR'; openMenu = null; }">
                                        <span>PKR</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Compensation</label>
                            <input type="number" name="compensation" id="ccCompensation" class="form-control" min="0" step="0.01" x-model="compensation" required>
                        </div>

                        {{-- 4. Dates, Generous Status Column (col-md-4 — never truncates "Pending Signature"), & 4-Digit PIN --}}
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="{{ $defaultStartDate }}" x-model="startDate">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control" x-model="expiryDate">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" x-text="mode === 'edit' ? 'Status' : 'Initial Status'">Initial Status</label>
                            <div class="sc-cselect-wrap" :class="{ 'is-open': openMenu === 'status' }" @click.outside="closeMenu('status')">
                                <input type="hidden" name="status" :value="status">
                                <button type="button" class="sc-cselect-trigger" id="ccInitialStatusTrigger" @click="toggleMenu('status')">
                                    <span class="sc-cselect-label" x-text="statuses[status] || 'Pending Signature'">Pending Signature</span>
                                    <span class="sc-cselect-caret">▾</span>
                                </button>
                                <div class="sc-cselect-menu z-50" x-show="openMenu === 'status'" x-cloak>
                                    <button type="button"
                                        class="sc-cselect-option"
                                        :class="{ 'is-selected': status === 'pending_signature' }"
                                        @click="selectStatus('pending_signature')">
                                        <span>Pending Signature</span>
                                    </button>
                                    <button type="button"
                                        class="sc-cselect-option"
                                        :class="{ 'is-selected': status === 'draft' }"
                                        @click="selectStatus('draft')">
                                        <span>Draft</span>
                                    </button>
                                    <template x-if="mode === 'edit'">
                                        <button type="button"
                                            class="sc-cselect-option"
                                            :class="{ 'is-selected': status === 'active' }"
                                            @click="selectStatus('active')">
                                            <span>Active</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">4-Digit PIN</label>
                            <input type="text" name="pin" class="form-control" x-model="pin" inputmode="numeric" pattern="\d{4}" maxlength="4" :placeholder="mode === 'edit' ? 'Keep' : '2456'" placeholder="2456">
                        </div>

                        {{-- Smart Re-Signature Guardrail Toggle when editing an already-signed Active contract --}}
                        <div class="col-12" x-show="mode === 'edit' && wasActive" x-cloak>
                            <div class="sc-resig-row d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <label class="d-flex align-items-center gap-2 mb-0" style="cursor: pointer; color: #f8efe6; font-size: 13px; font-weight: 600;">
                                    <input type="hidden" name="require_resignature" value="0" :disabled="!(mode === 'edit' && wasActive)">
                                    <input type="checkbox"
                                        name="require_resignature"
                                        value="1"
                                        class="form-check-input m-0 sc-check"
                                        :disabled="!(mode === 'edit' && wasActive)"
                                        x-model="requireResignature"
                                        @change="onToggleResignature()">
                                    <span>Require staff re-signature (resets status to Pending Signature &amp; clears prior signature)</span>
                                </label>
                                <span class="sc-inline-chip" x-text="requireResignature ? 'Resets to Pending Signature' : 'Keeps Active'"></span>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Contract Clauses &amp; Terms</label>
                            <textarea name="clauses_body"
                                id="ccClausesBody"
                                class="form-control"
                                rows="7"
                                x-model="clausesBody"
                                @input="clausesOverridden = true"
                                required>{{ $defaultTemplate['clauses'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sc-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="sc-btn-rosegold" x-text="mode === 'edit' ? 'Save Contract Changes' : 'Issue Contract & Generate Private Link'">Issue Contract &amp; Generate Private Link</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Set / Reset 4-Digit PIN Modal (Admin Only — Declarative Alpine State) --}}
    <div class="modal fade sc-modal" id="contractPinModal" tabindex="-1" aria-hidden="true" x-data="contractPinModal" @open-contract-pin.window="openFor($event.detail)">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('staff-contracts.update-member-pin') }}" id="contractPinForm" class="modal-content">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="contractPinModalTitle" x-text="modalTitle">Set / Reset 4-Digit Contract PIN</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" id="contractPinStaffPickerWrap">
                        <label class="form-label">Team Member</label>
                        <div class="sc-cselect-wrap" :class="{ 'is-open': openMenu === 'pinMember' }" @click.outside="openMenu = null">
                            <input type="hidden" name="team_member" id="contractPinStaffSelect" :value="selectedMemberKey">
                            <button type="button" class="sc-cselect-trigger" @click="openMenu = (openMenu === 'pinMember' ? null : 'pinMember')">
                                <span class="sc-cselect-label">
                                    <template x-if="selectedMemberKey">
                                        <span class="d-inline-flex align-items-center text-truncate">
                                            <span class="text-truncate" x-text="selectedMemberName"></span>
                                            <span class="sc-cselect-tag" :class="{ 'is-access': selectedMemberType === 'user' }" x-show="selectedMemberTag" x-text="selectedMemberTag"></span>
                                        </span>
                                    </template>
                                    <template x-if="!selectedMemberKey">
                                        <span style="color: #b8a29a;">Select team member</span>
                                    </template>
                                </span>
                                <span class="sc-cselect-caret">▾</span>
                            </button>

                            <div class="sc-cselect-menu z-50" x-show="openMenu === 'pinMember'" x-cloak>
                                <template x-if="salonMembers.length > 0">
                                    <div>
                                        <div class="sc-cselect-group-label">Salon Staff</div>
                                        <template x-for="m in salonMembers" :key="'pin-' + m.key">
                                            <button type="button"
                                                class="sc-cselect-option"
                                                :class="{ 'is-selected': selectedMemberKey === m.key }"
                                                @click="pickPinMember(m)">
                                                <span class="text-truncate" x-text="m.name"></span>
                                                <span class="sc-cselect-tag" x-text="m.tag"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="accessMembers.length > 0">
                                    <div>
                                        <div class="sc-cselect-group-label">Staff Access &middot; CRM Team</div>
                                        <template x-for="m in accessMembers" :key="'pin-' + m.key">
                                            <button type="button"
                                                class="sc-cselect-option"
                                                :class="{ 'is-selected': selectedMemberKey === m.key }"
                                                @click="pickPinMember(m)">
                                                <span class="text-truncate" x-text="m.name"></span>
                                                <span class="sc-cselect-tag is-access" x-text="m.tag"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="contractPinValueInput">New 4-Digit PIN</label>
                        <input type="text" id="contractPinValueInput" name="pin" x-model="pinValue" class="form-control text-center fw-bold"
                            style="letter-spacing: 0.35em; font-size: 18px;"
                            inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="2456" required>
                    </div>
                    <p class="text-muted small mb-0" id="contractPinStatusHint" x-text="statusHint">
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
            const LALEEN_CONTRACT_BOOT = {
                newCategorySentinel: {{ \Illuminate\Support\Js::from($newCatSentinel) }},
                templates: {{ \Illuminate\Support\Js::from($templatesMap) }},
                salonMembers: {{ \Illuminate\Support\Js::from($salonMembers->values()->all()) }},
                accessMembers: {{ \Illuminate\Support\Js::from($accessMembers->values()->all()) }},
                branches: {{ \Illuminate\Support\Js::from(\App\Models\StaffContract::BRANCHES) }},
                statuses: {
                    pending_signature: 'Pending Signature',
                    draft: 'Draft',
                    active: 'Active'
                },
                storeUrl: {{ \Illuminate\Support\Js::from(route('staff-contracts.store')) }},
                defaultStartDate: {{ \Illuminate\Support\Js::from($defaultStartDate) }},
                saveCategoryUrl: {{ \Illuminate\Support\Js::from(route('staff-contracts.categories.store')) }},
                csrfToken: {{ \Illuminate\Support\Js::from(csrf_token()) }}
            };

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

            function createContractModalComponent() {
                const tplKeys = Object.keys(LALEEN_CONTRACT_BOOT.templates || {});
                const initialTplKey = tplKeys[0] || 'salon_beautician';
                const initialTpl = (LALEEN_CONTRACT_BOOT.templates || {})[initialTplKey] || {};

                return {
                    mode: 'create',
                    editingContractId: null,
                    editingContractNumber: '',
                    storeActionUrl: LALEEN_CONTRACT_BOOT.storeUrl || '',
                    editActionUrl: '',
                    wasActive: false,
                    requireResignature: true,

                    openMenu: null,
                    memberError: '',
                    newCatSentinel: LALEEN_CONTRACT_BOOT.newCategorySentinel,
                    templates: Object.assign({}, LALEEN_CONTRACT_BOOT.templates || {}),
                    salonMembers: LALEEN_CONTRACT_BOOT.salonMembers || [],
                    accessMembers: LALEEN_CONTRACT_BOOT.accessMembers || [],
                    branches: LALEEN_CONTRACT_BOOT.branches || {},
                    statuses: LALEEN_CONTRACT_BOOT.statuses || {},

                    selectedMemberKey: '',
                    selectedMemberName: '',
                    selectedMemberTag: '',
                    selectedMemberType: '',

                    templateKey: initialTplKey,
                    customCategoryName: '',
                    customCategoryCurrency: 'QAR',
                    savingCategory: false,

                    roleTitle: initialTpl.default_role || initialTpl.label || 'Salon Beautician',
                    roleOverridden: false,
                    branch: initialTpl.default_branch || 'old_airport',
                    currency: initialTpl.default_currency || 'QAR',
                    compensation: '0.00',
                    startDate: LALEEN_CONTRACT_BOOT.defaultStartDate || '',
                    expiryDate: '',
                    status: 'pending_signature',
                    pin: '',
                    clausesBody: initialTpl.clauses || '',
                    clausesOverridden: false,

                    init() {
                        this.applyTemplateDefaults();
                    },

                    resetCreateMode() {
                        this.mode = 'create';
                        this.editingContractId = null;
                        this.editingContractNumber = '';
                        this.editActionUrl = '';
                        this.wasActive = false;
                        this.requireResignature = true;
                        this.openMenu = null;
                        this.memberError = '';
                        this.selectedMemberKey = '';
                        this.selectedMemberName = '';
                        this.selectedMemberTag = '';
                        this.selectedMemberType = '';
                        this.templateKey = initialTplKey;
                        this.customCategoryName = '';
                        this.customCategoryCurrency = 'QAR';
                        this.compensation = '0.00';
                        this.startDate = LALEEN_CONTRACT_BOOT.defaultStartDate || '';
                        this.expiryDate = '';
                        this.status = 'pending_signature';
                        this.pin = '';
                        this.applyTemplateDefaults();
                    },

                    openEditMode(detail) {
                        const c = detail?.contract;
                        if (!c) return;

                        this.mode = 'edit';
                        this.editingContractId = c.id;
                        this.editingContractNumber = c.contract_number || '';
                        this.editActionUrl = c.update_url || '';
                        this.openMenu = null;
                        this.memberError = '';

                        this.selectedMemberKey = c.member_key || '';
                        this.selectedMemberName = c.member_name || '';
                        this.selectedMemberTag = c.member_tag || '';
                        this.selectedMemberType = c.member_type || 'staff';

                        if (c.template_key && !this.templates[c.template_key]) {
                            this.templates[c.template_key] = {
                                label: c.role_title || 'Custom Category',
                                default_role: c.role_title || 'Custom Category',
                                default_currency: c.currency || 'QAR',
                                allowed_currencies: ['QAR', 'PKR'],
                                default_branch: c.branch || 'old_airport',
                                clauses: c.clauses_body || ''
                            };
                        }

                        this.templateKey = c.template_key || initialTplKey;
                        this.customCategoryName = '';
                        this.customCategoryCurrency = c.currency || 'QAR';

                        this.roleTitle = c.role_title || '';
                        this.roleOverridden = true;
                        this.branch = c.branch || 'old_airport';
                        this.currency = c.currency || 'QAR';
                        this.compensation = c.compensation || '0.00';
                        this.startDate = c.start_date || LALEEN_CONTRACT_BOOT.defaultStartDate || '';
                        this.expiryDate = c.expiry_date || '';
                        this.pin = '';
                        this.clausesBody = c.clauses_body || '';
                        this.clausesOverridden = true;

                        this.wasActive = (c.status === 'active');
                        if (this.wasActive) {
                            this.requireResignature = true;
                            this.status = 'pending_signature';
                        } else {
                            this.requireResignature = false;
                            this.status = c.status || 'pending_signature';
                        }
                    },

                    selectStatus(nextStatus) {
                        this.status = nextStatus;
                        if (this.mode === 'edit' && this.wasActive) {
                            this.requireResignature = (nextStatus !== 'active');
                        }
                        this.openMenu = null;
                    },

                    onToggleResignature() {
                        if (!this.wasActive) return;
                        this.status = this.requireResignature ? 'pending_signature' : 'active';
                    },

                    toggleMenu(name) {
                        this.openMenu = (this.openMenu === name) ? null : name;
                    },

                    closeMenu(name) {
                        if (this.openMenu === name) {
                            this.openMenu = null;
                        }
                    },

                    templateEntries() {
                        return Object.entries(this.templates).map(([key, tpl]) => ({
                            key,
                            display: `${tpl.label} (${(tpl.allowed_currencies || ['QAR']).join('/')})`
                        }));
                    },

                    currentTemplateLabel() {
                        if (this.templateKey === this.newCatSentinel) {
                            return '+ Add Category';
                        }
                        const tpl = this.templates[this.templateKey];
                        if (!tpl) {
                            return 'Select template';
                        }
                        return `${tpl.label} (${(tpl.allowed_currencies || ['QAR']).join('/')})`;
                    },

                    isCurrencyAllowed(curr) {
                        if (this.templateKey === this.newCatSentinel) {
                            return true;
                        }
                        const tpl = this.templates[this.templateKey];
                        const allowed = tpl?.allowed_currencies || ['QAR', 'PKR'];
                        return allowed.includes(curr);
                    },

                    selectMember(m) {
                        this.selectedMemberKey = m.key;
                        this.selectedMemberName = m.name;
                        this.selectedMemberTag = m.tag || '';
                        this.selectedMemberType = m.type || 'staff';
                        this.memberError = '';
                        this.openMenu = null;

                        if (m.template_key && this.templates[m.template_key] && this.templateKey !== this.newCatSentinel) {
                            this.templateKey = m.template_key;
                            this.applyTemplateDefaults();
                        }

                        if (m.role_title && this.templateKey !== this.newCatSentinel) {
                            this.roleTitle = m.role_title;
                            this.roleOverridden = false;
                        }

                        if (m.branch && this.branches[m.branch]) {
                            this.branch = m.branch;
                        }

                        if (m.currency && this.isCurrencyAllowed(m.currency)) {
                            this.currency = m.currency;
                        }

                        const baseComp = Number(m.base_salary || 0);
                        if (baseComp > 0) {
                            this.compensation = baseComp.toFixed(2);
                        }
                    },

                    selectTemplate(tKey) {
                        this.templateKey = tKey;
                        this.openMenu = null;
                        this.applyTemplateDefaults();
                    },

                    applyTemplateDefaults() {
                        this.roleOverridden = false;
                        this.clausesOverridden = false;

                        if (this.templateKey === this.newCatSentinel) {
                            this.currency = this.customCategoryCurrency || 'QAR';
                            const cleanName = (this.customCategoryName || '').trim();
                            this.roleTitle = cleanName;
                            this.clausesBody = buildStandardCustomClauses(cleanName || 'Specialist', this.currency);
                            this.$nextTick(() => {
                                if (this.$refs.customCatInput) {
                                    this.$refs.customCatInput.focus();
                                }
                            });
                            return;
                        }

                        const tpl = this.templates[this.templateKey];
                        if (!tpl) return;

                        this.roleTitle = tpl.default_role || tpl.label || '';
                        this.clausesBody = tpl.clauses || '';

                        const allowed = tpl.allowed_currencies || ['QAR', 'PKR'];
                        this.currency = tpl.default_currency || allowed[0] || 'QAR';

                        if (this.templateKey === 'remote_chat_creative' || tpl.default_branch === 'remote') {
                            this.branch = 'remote';
                        } else {
                            const currentMember = [...this.salonMembers, ...this.accessMembers].find(x => x.key === this.selectedMemberKey);
                            if (currentMember && currentMember.branch && currentMember.branch !== 'remote') {
                                this.branch = currentMember.branch;
                            } else {
                                this.branch = tpl.default_branch || 'old_airport';
                            }
                        }
                    },

                    onCustomCategoryInput() {
                        const cleanName = (this.customCategoryName || '').trim();
                        if (!this.roleOverridden) {
                            this.roleTitle = cleanName;
                        }
                        if (!this.clausesOverridden) {
                            this.clausesBody = buildStandardCustomClauses(cleanName || 'Specialist', this.customCategoryCurrency);
                        }
                    },

                    selectCustomCurrency(curr) {
                        this.customCategoryCurrency = curr;
                        this.currency = curr;
                        if (curr === 'PKR') {
                            this.branch = 'remote';
                        }
                        this.openMenu = null;
                        this.onCustomCategoryInput();
                    },

                    saveReusableCategory() {
                        const label = (this.customCategoryName || '').trim();
                        if (label.length < 2) {
                            if (typeof showStaffToast === 'function') {
                                showStaffToast('Enter a category name first (e.g. Nail Technician).', 'error');
                            }
                            if (this.$refs.customCatInput) {
                                this.$refs.customCatInput.focus();
                            }
                            return;
                        }

                        this.savingCategory = true;
                        fetch(LALEEN_CONTRACT_BOOT.saveCategoryUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': LALEEN_CONTRACT_BOOT.csrfToken
                            },
                            body: JSON.stringify({
                                label: label,
                                default_currency: this.customCategoryCurrency || 'QAR',
                                default_branch: this.branch || 'old_airport',
                                clauses: this.clausesBody || ''
                            })
                        })
                            .then(r => r.json().then(data => ({ ok: r.ok, data })))
                            .then(({ ok, data }) => {
                                if (ok && data.success && data.slug) {
                                    this.templates[data.slug] = data.template;
                                    this.templateKey = data.slug;
                                    this.applyTemplateDefaults();
                                    if (typeof showStaffToast === 'function') {
                                        showStaffToast(`Saved "${data.template.label}" as a reusable contract category.`);
                                    }
                                } else if (typeof showStaffToast === 'function') {
                                    showStaffToast(data.message || 'Could not save category.', 'error');
                                }
                            })
                            .finally(() => {
                                this.savingCategory = false;
                            });
                    },

                    validateBeforeSubmit(event) {
                        if (!this.selectedMemberKey) {
                            event.preventDefault();
                            this.memberError = 'Please select a team member.';
                            this.openMenu = 'member';
                        }
                    }
                };
            }

            function createContractPinModalComponent() {
                const allMembers = [
                    ...(LALEEN_CONTRACT_BOOT.salonMembers || []),
                    ...(LALEEN_CONTRACT_BOOT.accessMembers || [])
                ];
                const first = allMembers[0] || null;

                return {
                    openMenu: null,
                    salonMembers: LALEEN_CONTRACT_BOOT.salonMembers || [],
                    accessMembers: LALEEN_CONTRACT_BOOT.accessMembers || [],
                    selectedMemberKey: first ? first.key : '',
                    selectedMemberName: first ? first.name : '',
                    selectedMemberTag: first ? (first.tag || '') : '',
                    selectedMemberType: first ? (first.type || 'staff') : 'staff',
                    hasCustomPin: first ? !!first.has_custom_pin : false,
                    pinValue: '',
                    modalTitle: first ? `Set / Reset PIN — ${first.name}` : 'Set / Reset 4-Digit Contract PIN',
                    statusHint: first
                        ? (first.has_custom_pin
                            ? `${first.name} currently uses a custom 4-digit PIN. Enter a new 4-digit PIN to reset it.`
                            : `${first.name} currently uses default PIN 2456. Enter a new 4-digit PIN to customize it.`)
                        : 'Staff enter this 4-digit PIN on their private link to unlock contract terms for 10 minutes.',

                    pickPinMember(m) {
                        this.selectedMemberKey = m.key;
                        this.selectedMemberName = m.name;
                        this.selectedMemberTag = m.tag || '';
                        this.selectedMemberType = m.type || 'staff';
                        this.hasCustomPin = !!m.has_custom_pin;
                        this.openMenu = null;
                        this.modalTitle = `Set / Reset PIN — ${m.name}`;
                        this.statusHint = this.hasCustomPin
                            ? `${m.name} currently uses a custom 4-digit PIN. Enter a new 4-digit PIN to reset it.`
                            : `${m.name} currently uses default PIN 2456. Enter a new 4-digit PIN to customize it.`;
                    },

                    openFor(detail) {
                        this.pinValue = '';
                        this.openMenu = null;
                        if (!detail || !detail.memberKey) return;
                        const normalized = String(detail.memberKey).includes(':')
                            ? String(detail.memberKey)
                            : `staff:${detail.memberKey}`;
                        const found = [...this.salonMembers, ...this.accessMembers].find(x => x.key === normalized);
                        if (found) {
                            this.pickPinMember(Object.assign({}, found, { has_custom_pin: !!detail.hasCustomPin }));
                        } else {
                            this.selectedMemberKey = normalized;
                            this.selectedMemberName = detail.staffName || 'Team Member';
                            this.selectedMemberTag = '';
                            this.hasCustomPin = !!detail.hasCustomPin;
                            this.modalTitle = `Set / Reset PIN — ${this.selectedMemberName}`;
                            this.statusHint = this.hasCustomPin
                                ? `${this.selectedMemberName} currently uses a custom 4-digit PIN. Enter a new 4-digit PIN to reset it.`
                                : `${this.selectedMemberName} currently uses default PIN 2456. Enter a new 4-digit PIN to customize it.`;
                        }
                    }
                };
            }

            window.contractModal = createContractModalComponent;
            window.contractPinModal = createContractPinModalComponent;

            function registerContractAlpineComponents() {
                if (!window.Alpine || window.__laleenContractAlpineRegistered) return;
                window.__laleenContractAlpineRegistered = true;
                window.Alpine.data('contractModal', createContractModalComponent);
                window.Alpine.data('contractPinModal', createContractPinModalComponent);
            }

            if (window.Alpine) {
                registerContractAlpineComponents();
            } else {
                document.addEventListener('alpine:init', registerContractAlpineComponents);
            }

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

            window.openCreateContractModal = function () {
                closeAllContractMenus();
                window.dispatchEvent(new CustomEvent('open-contract-create'));
            };

            window.openEditContractModal = function (contractId) {
                closeAllContractMenus();
                const modalEl = document.getElementById('createContractModal');
                if (!modalEl) return;
                const triggerBtn = document.querySelector('[data-edit-contract-id="' + contractId + '"]');
                const payloadUrl = triggerBtn?.dataset?.editPayloadUrl || ('/staff-contracts/' + contractId + '/edit-payload');

                fetch(payloadUrl, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(r => r.json().then(data => ({ ok: r.ok, data })))
                    .then(({ ok, data }) => {
                        if (!ok || !data?.contract) {
                            if (typeof showStaffToast === 'function') {
                                showStaffToast(data?.message || 'Could not load contract details.', 'error');
                            }
                            return;
                        }
                        window.dispatchEvent(new CustomEvent('open-contract-edit', {
                            detail: { contract: data.contract }
                        }));
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    })
                    .catch(() => {
                        if (typeof showStaffToast === 'function') {
                            showStaffToast('Could not load contract details.', 'error');
                        }
                    });
            };

            window.openContractPinModal = function (memberKey = null, staffName = '', hasCustomPin = false) {
                const modalEl = document.getElementById('contractPinModal');
                if (!modalEl) return;
                window.dispatchEvent(new CustomEvent('open-contract-pin', {
                    detail: { memberKey, staffName, hasCustomPin }
                }));
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            };

            window.handleOpenPinFromRow = function (btn) {
                closeAllContractMenus();
                if (!btn) return;
                openContractPinModal(
                    btn.dataset.memberKey || '',
                    btn.dataset.memberName || '',
                    btn.dataset.hasCustomPin === '1'
                );
            };

            document.addEventListener('click', function (e) {
                if (!e.target.closest('.sc-more-wrap')) {
                    closeAllContractMenus();
                }
            });
        })();
    </script>
@else
    @php
        $personalContracts = $staffContracts ?? collect();
    @endphp
    <div class="laleen-contract-card mb-4">
        <div class="laleen-contract-header">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h6 class="fw-bold mb-0" style="color: #f8efe6; font-size: 15.5px;">Staff Contracts &amp; Secure PIN Portal</h6>
                <span class="sc-inline-chip">PIN Protected</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table team-table align-middle mb-0" id="staffContractsTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Status</th>
                        <th>Expiry</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($personalContracts as $contract)
                        <tr data-contract-id="{{ $contract->id }}">
                            <td>
                                <div class="team-name">{{ $contract->memberName() }}</div>
                                <div class="text-muted small">{{ $contract->contract_number }} &middot; {{ $contract->branchLabel() }}</div>
                            </td>
                            <td>
                                <span class="fw-semibold" style="color: #f8efe6;">{{ $contract->designationLabel() }}</span>
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
                            <td class="text-end text-nowrap">
                                <a href="{{ route('contracts.portal.show', $contract->secure_token) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="sc-action-pill"
                                    title="Unlock with your 4-digit PIN">
                                    Open ↗
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No personal contract issued yet. Team members view and sign their own agreement via their private 4-digit PIN gate.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="laleen-contract-guardrail">
            <span style="color: #6ee7b7;">✓ Personal agreements require your private 4-digit PIN to unlock and download</span>
            <span style="color: #fda4af;">✕ Colleague contracts, salaries, and payroll settings are restricted to Admin</span>
        </div>
    </div>
@endif
