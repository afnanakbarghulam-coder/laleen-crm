@extends('layouts.app')

@section('title', 'Legal Tasks — Laleen Ops')

@section('content')
    @php
        $totalVaultCount = $legalDocuments->count() + $staffLegalDocuments->count();
    @endphp

    <style>
        .lt-shell {
            background: radial-gradient(circle at 14% 0%, rgba(217, 143, 131, 0.08) 0%, transparent 45%), #140c0f;
            border: 1px solid rgba(229, 184, 168, 0.14);
            border-radius: 22px;
            padding: 24px 26px 28px;
            color: #f8efe6;
        }
        .lt-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }
        .lt-title {
            font-size: 24px;
            font-weight: 700;
            color: #f8efe6;
            letter-spacing: -0.02em;
            margin: 0 0 4px 0;
        }
        .lt-subtitle {
            font-size: 13px;
            color: #b59e99;
            margin: 0;
        }
        .lt-top-tabs {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 4px;
            border-radius: 14px;
            background: rgba(25, 16, 20, 0.92);
            border: 1px solid rgba(229, 184, 168, 0.22);
        }
        .lt-top-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: #b59e99;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.16s ease;
        }
        .lt-top-tab:hover {
            color: #f8efe6;
        }
        .lt-top-tab.active {
            background: linear-gradient(135deg, #d98f83, #c19a8c);
            color: #1c110f;
            font-weight: 700;
        }
        .lt-tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            background: rgba(255, 255, 255, 0.10);
            color: inherit;
        }
        .lt-top-tab.active .lt-tab-badge {
            background: rgba(28, 17, 15, 0.18);
            color: #1c110f;
        }
        .lt-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }
        .lt-pill-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
        }
        .lt-filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 13px;
            border-radius: 999px;
            border: 1px solid rgba(229, 184, 168, 0.18);
            background: rgba(29, 18, 22, 0.78);
            color: #b59e99;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
        }
        .lt-filter-pill:hover {
            color: #f8efe6;
            border-color: rgba(229, 184, 168, 0.38);
        }
        .lt-filter-pill.active {
            background: rgba(229, 184, 168, 0.18);
            border-color: #d98f83;
            color: #f8efe6;
        }
        .lt-search-input {
            background: rgba(29, 18, 22, 0.88);
            border: 1px solid rgba(229, 184, 168, 0.22);
            color: #f8efe6;
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 12.5px;
            min-width: 220px;
            outline: none;
        }
        .lt-search-input:focus {
            border-color: #d98f83;
        }
        .lt-primary-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 999px;
            border: none;
            background: linear-gradient(135deg, #d98f83, #c19a8c);
            color: #1c110f;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            transition: filter 0.15s ease;
            white-space: nowrap;
        }
        .lt-primary-btn:hover {
            filter: brightness(1.07);
        }
        .lt-table-wrap {
            background: #1b1115;
            border: 1px solid rgba(229, 184, 168, 0.14);
            border-radius: 16px;
            overflow: hidden;
        }
        .lt-table {
            width: 100%;
            margin: 0;
            color: #f8efe6;
            border-collapse: collapse;
        }
        .lt-table thead th {
            background: rgba(255, 255, 255, 0.025);
            color: #b59e99;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 13px 16px;
            border-bottom: 1px solid rgba(229, 184, 168, 0.14);
            white-space: nowrap;
        }
        .lt-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(229, 184, 168, 0.08);
            font-size: 13px;
            vertical-align: top;
            color: #efe2da;
        }
        .lt-table tbody tr:last-child td {
            border-bottom: none;
        }
        .lt-table tbody tr:hover {
            background: rgba(229, 184, 168, 0.035);
        }
        .lt-sn {
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            color: #d4a373;
            width: 56px;
        }
        .lt-work-title {
            font-weight: 600;
            color: #f8efe6;
            font-size: 13.5px;
            line-height: 1.4;
        }
        .lt-muted-text {
            color: #a68f89;
            font-size: 12.5px;
            line-height: 1.45;
        }
        .lt-why-pending {
            color: #fda4af;
            font-size: 12.5px;
            line-height: 1.45;
        }
        .lt-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
            white-space: nowrap;
        }
        .lt-badge-champagne {
            background: rgba(212, 163, 115, 0.16);
            color: #f3d9a4;
            border: 1px solid rgba(212, 163, 115, 0.38);
        }
        .lt-badge-amber {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.38);
        }
        .lt-badge-danger {
            background: rgba(251, 113, 133, 0.15);
            color: #fda4af;
            border: 1px solid rgba(251, 113, 133, 0.38);
        }
        .lt-badge-emerald {
            background: rgba(52, 211, 153, 0.15);
            color: #6ee7b7;
            border: 1px solid rgba(52, 211, 153, 0.38);
        }
        .lt-badge-muted {
            background: rgba(255, 255, 255, 0.06);
            color: #b59e99;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .lt-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 5px 10px;
            border-radius: 999px;
            border: 1px solid rgba(229, 184, 168, 0.20);
            background: rgba(255, 255, 255, 0.03);
            color: #dfd0ca;
            font-size: 11.5px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.14s ease;
        }
        .lt-icon-btn:hover {
            border-color: #d98f83;
            color: #fff;
            background: rgba(217, 143, 131, 0.14);
        }
        .lt-icon-btn.is-danger:hover {
            border-color: rgba(251, 113, 133, 0.55);
            color: #fda4af;
            background: rgba(251, 113, 133, 0.14);
        }
        .lt-subtab-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(229, 184, 168, 0.12);
        }
        .lt-subtab-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 15px;
            border-radius: 999px;
            border: 1px solid rgba(229, 184, 168, 0.20);
            background: rgba(29, 18, 22, 0.8);
            color: #b59e99;
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .lt-subtab-btn:hover {
            color: #f8efe6;
            border-color: rgba(229, 184, 168, 0.42);
        }
        .lt-subtab-btn.active {
            background: rgba(212, 163, 115, 0.18);
            border-color: #d4a373;
            color: #f8efe6;
        }
        .lt-sync-hint {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            color: #c8b6ae;
            padding: 10px 14px;
            border-radius: 12px;
            background: rgba(52, 211, 153, 0.08);
            border: 1px solid rgba(52, 211, 153, 0.24);
            margin-bottom: 14px;
        }
        .lt-modal .modal-content {
            background: #1b1115;
            color: #f8efe6;
            border: 1px solid rgba(229, 184, 168, 0.28);
            border-radius: 18px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.72);
        }
        .lt-modal .modal-header,
        .lt-modal .modal-footer {
            border-color: rgba(229, 184, 168, 0.12);
            padding: 16px 22px;
        }
        .lt-modal .modal-body {
            padding: 18px 22px;
        }
        .lt-modal .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #e5b8a8;
        }
    </style>

    <div class="lt-shell">
        <div class="lt-header">
            <div>
                <h1 class="lt-title">Legal Tasks</h1>
                <p class="lt-subtitle">Laleen Salon Work compliance tracker &amp; private Branch &amp; Staff legal documents vault.</p>
            </div>

            <div class="lt-top-tabs" role="tablist" aria-label="Legal Module Sections">
                <a href="{{ route('legal.tasks.index', ['tab' => 'work']) }}"
                   class="lt-top-tab {{ $activeTab === 'work' ? 'active' : '' }}"
                   role="tab" aria-selected="{{ $activeTab === 'work' ? 'true' : 'false' }}">
                    <i class="bx bx-briefcase-alt-2"></i>
                    <span>Laleen Salon Work</span>
                    <span class="lt-tab-badge">{{ $workEntries->count() }}</span>
                </a>
                <a href="{{ route('legal.tasks.index', ['tab' => 'vault', 'vault' => $vaultSubTab]) }}"
                   class="lt-top-tab {{ $activeTab === 'vault' ? 'active' : '' }}"
                   role="tab" aria-selected="{{ $activeTab === 'vault' ? 'true' : 'false' }}">
                    <i class="bx bx-folder-open"></i>
                    <span>Legal Documents Vault</span>
                    <span class="lt-tab-badge">{{ $totalVaultCount }}</span>
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <strong>Please check the form:</strong>
                <ul class="mb-0 ps-3 mt-1 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- =====================================================================
             TAB 1: LALEEN SALON WORK
             ===================================================================== --}}
        @if ($activeTab === 'work')
            <div class="lt-toolbar">
                <div class="lt-pill-group" role="group" aria-label="Filter Work Entries by Status">
                    <button type="button" class="lt-filter-pill active" data-work-status="all">
                        All <span class="lt-tab-badge">{{ $statusCounts['all'] }}</span>
                    </button>
                    @foreach (\App\Models\LegalTask::STATUSES as $stKey => $stLabel)
                        <button type="button" class="lt-filter-pill" data-work-status="{{ $stKey }}">
                            {{ $stLabel }} <span class="lt-tab-badge">{{ $statusCounts[$stKey] ?? 0 }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <input type="search" id="workSearchInput" class="lt-search-input" placeholder="Search work, remarks, pending reason…">
                    @if ($canEditLegal)
                        <button type="button" class="lt-primary-btn" data-bs-toggle="modal" data-bs-target="#createWorkModal">
                            <i class="bx bx-plus"></i> Add Work Entry
                        </button>
                    @endif
                </div>
            </div>

            <div class="lt-table-wrap">
                <div class="table-responsive">
                    <table class="lt-table" id="laleenWorkTable">
                        <thead>
                            <tr>
                                <th style="width: 60px;">S.N</th>
                                <th style="min-width: 240px;">Nature of Work</th>
                                <th style="width: 145px;">Status</th>
                                <th style="min-width: 220px;">Remarks</th>
                                <th style="min-width: 220px;">Why Pending?</th>
                                @if ($canEditLegal)
                                    <th class="text-end" style="width: 150px;">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($workEntries as $idx => $entry)
                                @php
                                    $badge = $entry->statusBadge();
                                    $sn = $entry->serial_number ?: ($idx + 1);
                                    $searchHaystack = strtolower(implode(' ', array_filter([
                                        (string) $sn,
                                        $entry->displayWorkTitle(),
                                        $entry->statusLabel(),
                                        $entry->remarks,
                                        $entry->why_pending,
                                        $entry->person_responsible,
                                    ])));
                                    $editPayload = [
                                        'id' => $entry->id,
                                        'serial_number' => $entry->serial_number ?? $sn,
                                        'nature_of_work' => $entry->displayWorkTitle(),
                                        'status' => $entry->status,
                                        'remarks' => (string) ($entry->remarks ?? ''),
                                        'why_pending' => (string) ($entry->why_pending ?? ''),
                                        'person_responsible' => (string) ($entry->person_responsible ?? ''),
                                        'deadline' => $entry->deadline ? $entry->deadline->format('Y-m-d') : '',
                                    ];
                                @endphp
                                <tr data-work-row data-status="{{ $entry->status }}" data-search="{{ $searchHaystack }}">
                                    <td class="lt-sn">{{ $sn }}</td>
                                    <td>
                                        <div class="lt-work-title">{{ $entry->displayWorkTitle() }}</div>
                                        @if ($entry->person_responsible || $entry->deadline)
                                            <div class="lt-muted-text mt-1">
                                                @if ($entry->person_responsible)
                                                    <span><i class="bx bx-user me-1"></i>{{ $entry->person_responsible }}</span>
                                                @endif
                                                @if ($entry->person_responsible && $entry->deadline)
                                                    &middot;
                                                @endif
                                                @if ($entry->deadline)
                                                    <span><i class="bx bx-calendar me-1"></i>Due {{ $entry->deadline->format('d M Y') }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="lt-badge lt-badge-{{ $badge['tone'] }}">{{ $badge['label'] }}</span>
                                    </td>
                                    <td>
                                        @if (filled($entry->remarks))
                                            <div class="lt-muted-text" style="color:#e5d8d1; white-space:pre-line;">{{ $entry->remarks }}</div>
                                        @else
                                            <span class="lt-muted-text">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (filled($entry->why_pending))
                                            <div class="lt-why-pending" style="white-space:pre-line;">{{ $entry->why_pending }}</div>
                                        @else
                                            <span class="lt-muted-text">&mdash;</span>
                                        @endif
                                    </td>
                                    @if ($canEditLegal)
                                        <td class="text-end text-nowrap">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <button type="button"
                                                        class="lt-icon-btn js-edit-work-btn"
                                                        data-payload='@json($editPayload)'
                                                        title="Edit Entry">
                                                    <i class="bx bx-edit-alt"></i> Edit
                                                </button>
                                                <form action="{{ route('legal.tasks.destroy', $entry) }}"
                                                      method="POST"
                                                      class="d-inline m-0"
                                                      onsubmit="return confirm('Delete this legal work entry?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="lt-icon-btn is-danger" title="Delete Entry">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canEditLegal ? 6 : 5 }}" class="text-center py-5 lt-muted-text">
                                        No legal work entries logged yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        {{-- =====================================================================
             TAB 2: LEGAL DOCUMENTS VAULT
             ===================================================================== --}}
        @else
            <div class="lt-subtab-bar" role="tablist" aria-label="Vault Document Sub-Tabs">
                <a href="{{ route('legal.tasks.index', ['tab' => 'vault', 'vault' => 'branch']) }}"
                   class="lt-subtab-btn {{ $vaultSubTab === 'branch' ? 'active' : '' }}">
                    <i class="bx bx-buildings"></i>
                    <span>Branch &amp; Company Documents</span>
                    <span class="lt-tab-badge">{{ $legalDocuments->count() }}</span>
                </a>
                <a href="{{ route('legal.tasks.index', ['tab' => 'vault', 'vault' => 'staff']) }}"
                   class="lt-subtab-btn {{ $vaultSubTab === 'staff' ? 'active' : '' }}">
                    <i class="bx bx-id-card"></i>
                    <span>Staff Legal Documents</span>
                    <span class="lt-tab-badge">{{ $staffLegalDocuments->count() }}</span>
                </a>
            </div>

            @if ($vaultSubTab === 'branch')
                {{-- SUB-TAB 1: BRANCH & COMPANY LEGAL DOCUMENTS --}}
                <div class="lt-toolbar">
                    <div class="lt-pill-group" role="group" aria-label="Filter Branch Documents by Scope">
                        <button type="button" class="lt-filter-pill active" data-doc-scope="all">All</button>
                        @foreach (\App\Models\LegalDocument::SCOPES as $scKey => $scLabel)
                            <button type="button" class="lt-filter-pill" data-doc-scope="{{ $scKey }}">{{ $scLabel }}</button>
                        @endforeach
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <input type="search" id="branchDocSearchInput" class="lt-search-input" placeholder="Search CR, license, doc #…">
                        @if ($canEditLegal)
                            <button type="button" class="lt-primary-btn" data-bs-toggle="modal" data-bs-target="#uploadBranchDocModal">
                                <i class="bx bx-upload"></i> Upload Document
                            </button>
                        @endif
                    </div>
                </div>

                <div class="lt-table-wrap">
                    <div class="table-responsive">
                        <table class="lt-table" id="branchLegalDocsTable">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Branch / Scope</th>
                                    <th>Category</th>
                                    <th>Doc #</th>
                                    <th>Expiry &amp; Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($legalDocuments as $doc)
                                    @php
                                        $exp = $doc->expiryStatus($todayQatar);
                                        $docHaystack = strtolower(implode(' ', array_filter([
                                            $doc->title,
                                            $doc->scopeLabel(),
                                            $doc->categoryLabel(),
                                            $doc->document_number,
                                            $doc->notes,
                                            $doc->original_filename,
                                        ])));
                                    @endphp
                                    <tr data-branch-doc-row data-scope="{{ $doc->scope }}" data-search="{{ $docHaystack }}">
                                        <td>
                                            <div class="lt-work-title">{{ $doc->title }}</div>
                                            <div class="lt-muted-text mt-1">
                                                {{ $doc->original_filename }} &middot; {{ $doc->formattedFileSize() }}
                                                @if ($doc->uploader)
                                                    &middot; Uploaded by {{ $doc->uploader->name }}
                                                @endif
                                            </div>
                                            @if (filled($doc->notes))
                                                <div class="lt-muted-text mt-1" style="color:#d7c6be;">{{ $doc->notes }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="lt-badge lt-badge-champagne">{{ $doc->scopeLabel() }}</span>
                                        </td>
                                        <td>{{ $doc->categoryLabel() }}</td>
                                        <td style="font-variant-numeric: tabular-nums;">{{ $doc->document_number ?: '—' }}</td>
                                        <td>
                                            <div class="d-flex flex-column align-items-start gap-1">
                                                <span class="lt-badge lt-badge-{{ $exp['tone'] }}">{{ $exp['label'] }}</span>
                                                <span class="lt-muted-text" style="font-variant-numeric: tabular-nums;">
                                                    {{ $doc->expiry_date ? $doc->expiry_date->format('d M Y') : 'No expiry date' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <a href="{{ route('legal-documents.download', [$doc, 'inline' => 1]) }}"
                                                   target="_blank" rel="noopener"
                                                   class="lt-icon-btn" title="View">
                                                    <i class="bx bx-show"></i> View
                                                </a>
                                                <a href="{{ route('legal-documents.download', $doc) }}"
                                                   class="lt-icon-btn" title="Download">
                                                    <i class="bx bx-download"></i>
                                                </a>
                                                @if ($canEditLegal)
                                                    <form action="{{ route('legal.documents.destroy', $doc) }}"
                                                          method="POST"
                                                          class="d-inline m-0"
                                                          onsubmit="return confirm('Delete this document permanently from the legal vault?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="lt-icon-btn is-danger" title="Delete">
                                                            <i class="bx bx-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 lt-muted-text">
                                            No branch or company legal documents uploaded yet. Upload CRs, Trade Licenses, Civil Defense certificates, or Lease Agreements here.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            @else
                {{-- SUB-TAB 2: STAFF LEGAL DOCUMENTS (SYNCS TO /my-profile -> Documents) --}}
                <div class="lt-sync-hint">
                    <i class="bx bx-check-shield" style="color:#6ee7b7; font-size:18px;"></i>
                    <span>
                        Staff documents uploaded here (such as <strong>6-Month Agreement Papers</strong>, <strong>Health Certificates / Health Cards</strong>, QIDs, and Passports) are stored privately and automatically synced as <strong>Verified</strong> on that employee&rsquo;s <strong>My Profile &rarr; Documents</strong> tab.
                    </span>
                </div>

                <div class="lt-toolbar">
                    <div class="lt-pill-group" role="group" aria-label="Filter Staff Legal Documents by Category">
                        <button type="button" class="lt-filter-pill active" data-staff-doc-cat="all">All</button>
                        <button type="button" class="lt-filter-pill" data-staff-doc-cat="six_month_agreement">6-Month Agreements</button>
                        <button type="button" class="lt-filter-pill" data-staff-doc-cat="health_card">Health Cards</button>
                        <button type="button" class="lt-filter-pill" data-staff-doc-cat="qid">QID</button>
                        <button type="button" class="lt-filter-pill" data-staff-doc-cat="passport">Passport</button>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <input type="search" id="staffDocSearchInput" class="lt-search-input" placeholder="Search staff name, doc #, title…">
                        @if ($canEditLegal)
                            <button type="button" class="lt-primary-btn" data-bs-toggle="modal" data-bs-target="#uploadStaffLegalDocModal">
                                <i class="bx bx-upload"></i> Upload Staff Legal Doc
                            </button>
                        @endif
                    </div>
                </div>

                <div class="lt-table-wrap">
                    <div class="table-responsive">
                        <table class="lt-table" id="staffLegalDocsTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Document</th>
                                    <th>Category</th>
                                    <th>Expiry</th>
                                    <th>Portal Sync</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($staffLegalDocuments as $sDoc)
                                    @php
                                        $exp = $sDoc->expiryStatus($todayQatar);
                                        $ver = $sDoc->verificationStatusBadge();
                                        $sHaystack = strtolower(implode(' ', array_filter([
                                            $sDoc->staff?->name,
                                            $sDoc->title,
                                            $sDoc->categoryLabel(),
                                            $sDoc->document_number,
                                            $sDoc->original_filename,
                                        ])));
                                    @endphp
                                    <tr data-staff-doc-row data-category="{{ $sDoc->category }}" data-search="{{ $sHaystack }}">
                                        <td>
                                            <div class="lt-work-title">{{ $sDoc->staff?->name ?? 'Unknown Staff' }}</div>
                                            <div class="lt-muted-text">{{ $sDoc->staff?->location_label ?? '' }}</div>
                                        </td>
                                        <td>
                                            <div class="lt-work-title">{{ $sDoc->title }}</div>
                                            <div class="lt-muted-text mt-1">
                                                @if ($sDoc->document_number)
                                                    #{{ $sDoc->document_number }} &middot;
                                                @endif
                                                {{ $sDoc->original_filename }} ({{ $sDoc->formattedFileSize() }})
                                            </div>
                                        </td>
                                        <td>
                                            <span class="lt-badge lt-badge-champagne">{{ $sDoc->categoryLabel() }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column align-items-start gap-1">
                                                <span class="lt-badge lt-badge-{{ $exp['tone'] }}">{{ $exp['label'] }}</span>
                                                <span class="lt-muted-text" style="font-variant-numeric: tabular-nums;">
                                                    {{ $sDoc->expiry_date ? $sDoc->expiry_date->format('d M Y') : 'No expiry' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="lt-badge lt-badge-{{ $ver['tone'] }}">{{ $ver['label'] }}</span>
                                        </td>
                                        <td class="text-end text-nowrap">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <a href="{{ route('staff-documents.download', [$sDoc, 'inline' => 1]) }}"
                                                   target="_blank" rel="noopener"
                                                   class="lt-icon-btn" title="View">
                                                    <i class="bx bx-show"></i> View
                                                </a>
                                                <a href="{{ route('staff-documents.download', $sDoc) }}"
                                                   class="lt-icon-btn" title="Download">
                                                    <i class="bx bx-download"></i>
                                                </a>
                                                @if ($canEditLegal)
                                                    <form action="{{ route('legal.staff-documents.destroy', $sDoc) }}"
                                                          method="POST"
                                                          class="d-inline m-0"
                                                          onsubmit="return confirm('Delete this staff legal document? This also removes it from their portal profile.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="lt-icon-btn is-danger" title="Delete">
                                                            <i class="bx bx-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-5 lt-muted-text">
                                            No staff legal documents uploaded yet. Upload 6-Month Agreement Papers, Health Certificates, QIDs, or Passports here.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </div>

    @if ($canEditLegal)
        {{-- MODAL 1: ADD LALEEN SALON WORK ENTRY --}}
        <div class="modal fade lt-modal" id="createWorkModal" tabindex="-1" aria-labelledby="createWorkModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('legal.tasks.store') }}" method="POST" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold mb-0" id="createWorkModalLabel">Add Laleen Salon Work Entry</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label" for="createWorkSn">S.N</label>
                                <input type="number" name="serial_number" id="createWorkSn" class="form-control" min="1" placeholder="Auto">
                            </div>
                            <div class="col-md-9">
                                <label class="form-label" for="createWorkNature">Nature of Work <span class="text-danger">*</span></label>
                                <input type="text" name="nature_of_work" id="createWorkNature" class="form-control" placeholder="e.g. Wakrah Civil Defense Renewal" required>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="createWorkStatus">Status <span class="text-danger">*</span></label>
                                <select name="status" id="createWorkStatus" class="form-select" required>
                                    @foreach (\App\Models\LegalTask::STATUSES as $stKey => $stLabel)
                                        <option value="{{ $stKey }}" @selected($stKey === 'under_process')>{{ $stLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="createWorkDeadline">Target Date (Optional)</label>
                                <input type="date" name="deadline" id="createWorkDeadline" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="createWorkRemarks">Remarks</label>
                            <textarea name="remarks" id="createWorkRemarks" rows="2" class="form-control" placeholder="Current status notes, filings submitted, contacts…"></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="createWorkWhyPending">Why Pending?</label>
                            <textarea name="why_pending" id="createWorkWhyPending" rows="2" class="form-control" placeholder="Blocker, missing paper, awaiting government approval…"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="lt-primary-btn">Save Entry</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- MODAL 2: EDIT LALEEN SALON WORK ENTRY --}}
        <div class="modal fade lt-modal" id="editWorkModal" tabindex="-1" aria-labelledby="editWorkModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form id="editWorkForm" method="POST" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold mb-0" id="editWorkModalLabel">Edit Laleen Salon Work Entry</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label" for="editWorkSn">S.N</label>
                                <input type="number" name="serial_number" id="editWorkSn" class="form-control" min="1">
                            </div>
                            <div class="col-md-9">
                                <label class="form-label" for="editWorkNature">Nature of Work <span class="text-danger">*</span></label>
                                <input type="text" name="nature_of_work" id="editWorkNature" class="form-control" required>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="editWorkStatus">Status <span class="text-danger">*</span></label>
                                <select name="status" id="editWorkStatus" class="form-select" required>
                                    @foreach (\App\Models\LegalTask::STATUSES as $stKey => $stLabel)
                                        <option value="{{ $stKey }}">{{ $stLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="editWorkDeadline">Target Date (Optional)</label>
                                <input type="date" name="deadline" id="editWorkDeadline" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="editWorkRemarks">Remarks</label>
                            <textarea name="remarks" id="editWorkRemarks" rows="2" class="form-control"></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="editWorkWhyPending">Why Pending?</label>
                            <textarea name="why_pending" id="editWorkWhyPending" rows="2" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="lt-primary-btn">Update Entry</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- MODAL 3: UPLOAD BRANCH & COMPANY LEGAL DOCUMENT --}}
        <div class="modal fade lt-modal" id="uploadBranchDocModal" tabindex="-1" aria-labelledby="uploadBranchDocModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('legal.documents.store') }}" method="POST" enctype="multipart/form-data" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold mb-0" id="uploadBranchDocModalLabel">Upload Branch / Company Legal Document</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="docScope">Branch / Scope <span class="text-danger">*</span></label>
                                <select name="scope" id="docScope" class="form-select" required>
                                    @foreach (\App\Models\LegalDocument::SCOPES as $scKey => $scLabel)
                                        <option value="{{ $scKey }}">{{ $scLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="docCategory">Category <span class="text-danger">*</span></label>
                                <select name="category" id="docCategory" class="form-select" required>
                                    @foreach (\App\Models\LegalDocument::CATEGORIES as $catKey => $catLabel)
                                        <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label" for="docTitle">Document Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="docTitle" class="form-control" placeholder="e.g. Old Airport Trade License 2026" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="docNumber">Document Number</label>
                                <input type="text" name="document_number" id="docNumber" class="form-control" placeholder="CR / License #">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="docIssueDate">Issue Date</label>
                                <input type="date" name="issue_date" id="docIssueDate" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="docExpiryDate">Expiry Date</label>
                                <input type="date" name="expiry_date" id="docExpiryDate" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="docFile">File (PDF, JPG, PNG, WEBP — max 10 MB) <span class="text-danger">*</span></label>
                            <input type="file" name="file" id="docFile" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="docNotes">Notes (Optional)</label>
                            <input type="text" name="notes" id="docNotes" class="form-control" placeholder="Renewal notes or government reference">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="lt-primary-btn">Upload to Vault</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- MODAL 4: UPLOAD STAFF LEGAL DOCUMENT (SYNCS TO /my-profile -> Documents) --}}
        <div class="modal fade lt-modal" id="uploadStaffLegalDocModal" tabindex="-1" aria-labelledby="uploadStaffLegalDocLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route('legal.staff-documents.store') }}" method="POST" enctype="multipart/form-data" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold mb-0" id="uploadStaffLegalDocLabel">Upload Staff Legal Document</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="sDocStaffId">Team Member <span class="text-danger">*</span></label>
                                <select name="staff_id" id="sDocStaffId" class="form-select" required>
                                    <option value="">Select staff member…</option>
                                    @foreach ($staffMembers as $st)
                                        <option value="{{ $st->id }}">{{ $st->name }} ({{ $st->location_label }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="sDocCategory">Document Category <span class="text-danger">*</span></label>
                                <select name="category" id="sDocCategory" class="form-select" required>
                                    @foreach (\App\Models\StaffDocument::LEGAL_STAFF_CATEGORIES as $catKey => $catLabel)
                                        <option value="{{ $catKey }}">{{ $catLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label" for="sDocTitle">Document Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="sDocTitle" class="form-control" placeholder="e.g. 6-Month Agreement Paper (Signed)" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="sDocNumber">Document Number</label>
                                <input type="text" name="document_number" id="sDocNumber" class="form-control" placeholder="QID / Health Card #">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="sDocIssue">Issue Date</label>
                                <input type="date" name="issue_date" id="sDocIssue" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="sDocExpiry">Expiry Date</label>
                                <input type="date" name="expiry_date" id="sDocExpiry" class="form-control">
                            </div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="sDocFile">File (PDF, JPG, PNG, WEBP — max 10 MB) <span class="text-danger">*</span></label>
                            <input type="file" name="file" id="sDocFile" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                            <div class="small mt-1" style="color:#a68f89;">
                                Automatically saved as <strong>Verified</strong> and synced to this staff member&rsquo;s <code>/my-profile</code> Documents tab.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="lt-primary-btn">Upload &amp; Sync</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // 1. Laleen Salon Work status filter + search
            const workPills = document.querySelectorAll('[data-work-status]');
            const workRows = document.querySelectorAll('[data-work-row]');
            const workSearch = document.getElementById('workSearchInput');
            let activeWorkStatus = 'all';

            function filterWorkRows() {
                const q = (workSearch?.value || '').trim().toLowerCase();
                workRows.forEach(function (row) {
                    const st = row.getAttribute('data-status') || '';
                    const hay = row.getAttribute('data-search') || '';
                    const matchStatus = activeWorkStatus === 'all' || st === activeWorkStatus;
                    const matchQuery = q === '' || hay.includes(q);
                    row.style.display = (matchStatus && matchQuery) ? '' : 'none';
                });
            }

            workPills.forEach(function (pill) {
                pill.addEventListener('click', function () {
                    activeWorkStatus = this.getAttribute('data-work-status') || 'all';
                    workPills.forEach(p => p.classList.toggle('active', p === this));
                    filterWorkRows();
                });
            });
            if (workSearch) {
                workSearch.addEventListener('input', filterWorkRows);
            }

            // Edit Work modal trigger
            const editModalEl = document.getElementById('editWorkModal');
            document.querySelectorAll('.js-edit-work-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!editModalEl) return;
                    let payload = {};
                    try { payload = JSON.parse(btn.dataset.payload || '{}'); } catch (e) {}
                    document.getElementById('editWorkForm').action = `/legal/tasks/${payload.id}`;
                    document.getElementById('editWorkSn').value = payload.serial_number || '';
                    document.getElementById('editWorkNature').value = payload.nature_of_work || '';
                    document.getElementById('editWorkStatus').value = payload.status || 'pending';
                    document.getElementById('editWorkDeadline').value = payload.deadline || '';
                    document.getElementById('editWorkRemarks').value = payload.remarks || '';
                    document.getElementById('editWorkWhyPending').value = payload.why_pending || '';
                    if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(editModalEl).show();
                });
            });

            // 2. Branch & Company Docs scope filter + search
            const scopePills = document.querySelectorAll('[data-doc-scope]');
            const branchDocRows = document.querySelectorAll('[data-branch-doc-row]');
            const branchDocSearch = document.getElementById('branchDocSearchInput');
            let activeScope = 'all';

            function filterBranchDocs() {
                const q = (branchDocSearch?.value || '').trim().toLowerCase();
                branchDocRows.forEach(function (row) {
                    const sc = row.getAttribute('data-scope') || '';
                    const hay = row.getAttribute('data-search') || '';
                    const matchScope = activeScope === 'all' || sc === activeScope;
                    const matchQuery = q === '' || hay.includes(q);
                    row.style.display = (matchScope && matchQuery) ? '' : 'none';
                });
            }

            scopePills.forEach(function (pill) {
                pill.addEventListener('click', function () {
                    activeScope = this.getAttribute('data-doc-scope') || 'all';
                    scopePills.forEach(p => p.classList.toggle('active', p === this));
                    filterBranchDocs();
                });
            });
            if (branchDocSearch) {
                branchDocSearch.addEventListener('input', filterBranchDocs);
            }

            // 3. Staff Legal Docs category filter + search
            const staffCatPills = document.querySelectorAll('[data-staff-doc-cat]');
            const staffDocRows = document.querySelectorAll('[data-staff-doc-row]');
            const staffDocSearch = document.getElementById('staffDocSearchInput');
            let activeStaffCat = 'all';

            function filterStaffDocs() {
                const q = (staffDocSearch?.value || '').trim().toLowerCase();
                staffDocRows.forEach(function (row) {
                    const cat = row.getAttribute('data-category') || '';
                    const hay = row.getAttribute('data-search') || '';
                    const matchCat = activeStaffCat === 'all' || cat === activeStaffCat;
                    const matchQuery = q === '' || hay.includes(q);
                    row.style.display = (matchCat && matchQuery) ? '' : 'none';
                });
            }

            staffCatPills.forEach(function (pill) {
                pill.addEventListener('click', function () {
                    activeStaffCat = this.getAttribute('data-staff-doc-cat') || 'all';
                    staffCatPills.forEach(p => p.classList.toggle('active', p === this));
                    filterStaffDocs();
                });
            });
            if (staffDocSearch) {
                staffDocSearch.addEventListener('input', filterStaffDocs);
            }
        });
    </script>
@endsection
