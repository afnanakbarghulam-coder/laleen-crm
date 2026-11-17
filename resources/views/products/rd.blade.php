@extends('layouts.app')
@section('title', 'R&D & Sourcing — Products')

@php
    $canEditProducts = auth()->check() && auth()->user()->canEdit('products');
    $bottleStatuses = \App\Models\ProductRdItem::BOTTLE_STATUSES;
    $stickerUnlockedStatuses = \App\Models\ProductRdItem::STICKER_UNLOCKED_STATUSES;
    $sampleStatuses = \App\Models\ProductRdSample::STATUSES;
    $sampleVerdicts = \App\Models\ProductRdSample::VERDICTS;
@endphp

@section('content')
    <div class="laleen-rd-flat-wrap">
        @include('products._subnav', ['activeTab' => 'rd'])

        {{-- ================= SECTION 1: REGIONAL SKU & PACKAGING BASELINE ================= --}}
        <section class="mb-5">
            <h6 class="laleen-rd-sec-title mb-2">1. Regional SKU &amp; Packaging Baseline</h6>

            @if ($canEditProducts)
                <form method="POST" action="{{ route('products.rd.items.store') }}" id="rdInlineAddRowForm" class="laleen-rd-inline-bar d-none no-print mb-2">
                    @csrf
                    <input type="text" name="name" class="laleen-rd-flat-input" style="width: 170px;" placeholder="Product name" required>
                    <select name="market" class="laleen-rd-flat-select" style="width: 145px;">
                        <option value="both">Qatar &amp; Pakistan</option>
                        <option value="qatar">Qatar only</option>
                        <option value="pakistan">Pakistan only</option>
                    </select>
                    <input type="text" name="volume" class="laleen-rd-flat-input" style="width: 105px;" placeholder="e.g. 250 ml" value="250 ml">
                    <select name="bottle_status" class="laleen-rd-flat-select" style="width: 120px;">
                        <option value="pending">Pending</option>
                        <option value="finalized">Finalized</option>
                    </select>
                    <button type="submit" class="laleen-rd-action-link is-primary">Save</button>
                    <button type="button" class="laleen-rd-action-link" id="rdCancelAddRowBtn">Cancel</button>
                </form>
            @endif

            <div class="table-responsive">
                <table class="laleen-rd-flat-table" id="rdRegionalTable">
                    <thead>
                        <tr>
                            <th style="width: 22%;">Product</th>
                            <th style="width: 17%;">Market</th>
                            <th style="width: 17%;">Volume</th>
                            <th style="width: 19%;">Bottle Status</th>
                            <th style="width: 21%;">Sticker Status</th>
                            @if ($canEditProducts)
                                <th class="text-end no-print" style="width: 4%; padding-right: 0;"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($regionalRows as $row)
                            <tr data-regional-row="{{ $row['row_key'] }}"
                                data-item-id="{{ $row['item_id'] }}"
                                data-market="{{ $row['market'] }}">
                                <td class="laleen-rd-strong">{{ $row['product'] }}</td>
                                <td class="laleen-rd-plain">{{ $row['market_label'] }}</td>
                                <td>
                                    @if ($canEditProducts)
                                        <input type="text"
                                               class="laleen-rd-flat-input"
                                               style="width: 96px;"
                                               value="{{ $row['volume'] }}"
                                               data-pkg-volume
                                               aria-label="{{ $row['product'] }} {{ $row['market_label'] }} volume">
                                    @else
                                        <span class="laleen-rd-plain">{{ $row['volume'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($canEditProducts)
                                        <select class="laleen-rd-flat-select"
                                                data-pkg-bottle-status
                                                aria-label="{{ $row['product'] }} {{ $row['market_label'] }} bottle status">
                                            @foreach ($bottleStatuses as $val => $label)
                                                <option value="{{ $val }}" {{ $row['bottle_status'] === $val ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="laleen-rd-plain">{{ $row['bottle_status_label'] }}</span>
                                    @endif
                                </td>
                                <td data-pkg-sticker-cell>
                                    @if ($row['is_sticker_blocked'])
                                        <span class="laleen-rd-blocked" data-sticker-locked>Blocked by Bottle</span>
                                    @elseif ($canEditProducts)
                                        <select class="laleen-rd-flat-select"
                                                data-pkg-sticker-status
                                                aria-label="{{ $row['product'] }} {{ $row['market_label'] }} sticker status">
                                            @foreach ($stickerUnlockedStatuses as $val => $label)
                                                <option value="{{ $val }}" {{ $row['sticker_status'] === $val ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="laleen-rd-plain">{{ $row['sticker_status_label'] }}</span>
                                    @endif
                                </td>
                                @if ($canEditProducts)
                                    <td class="text-end no-print" style="padding-right: 0;">
                                        <button type="button"
                                                class="laleen-rd-row-del-btn"
                                                data-ask-row-del
                                                title="Delete {{ $row['product'] }} ({{ $row['market_label'] }})"
                                                aria-label="Delete {{ $row['product'] }} {{ $row['market_label'] }} row">
                                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M2.5 4h11M5.5 4V2.75A.75.75 0 0 1 6.25 2h3.5a.75.75 0 0 1 .75.75V4m1.5 0v9.25a1.25 1.25 0 0 1-1.25 1.25h-5.5A1.25 1.25 0 0 1 4 13.25V4h8Z"/>
                                            </svg>
                                        </button>
                                        <span class="laleen-rd-inline-confirm d-none" data-row-del-confirm-wrap>
                                            <button type="button"
                                                    class="laleen-rd-confirm-link"
                                                    data-confirm-regional-del
                                                    data-delete-url="{{ route('products.rd.items.regional.destroy', ['item' => $row['item_id'], 'market' => $row['market']]) }}">
                                                Delete
                                            </button>
                                            <button type="button" class="laleen-rd-cancel-link" data-cancel-row-del aria-label="Cancel">&times;</button>
                                        </span>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr data-empty-regional>
                                <td colspan="{{ $canEditProducts ? 6 : 5 }}" class="laleen-rd-blocked">No regional rows tracked.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ================= SECTION 2: SUPPLIER SAMPLE EVALUATION ================= --}}
        <section class="mb-4">
            <h6 class="laleen-rd-sec-title mb-2" id="rdSampleSectionHeading">
                2. Supplier Sample Evaluation{{ $supplierHeading !== '' ? " ({$supplierHeading})" : '' }}
            </h6>

            @if ($canEditProducts)
                <form method="POST" action="{{ route('products.rd.suppliers.store') }}" id="rdInlineAddSupplierForm" class="laleen-rd-inline-bar d-none no-print mb-2">
                    @csrf
                    <input type="text" name="name" class="laleen-rd-flat-input" style="width: 210px;" placeholder="Supplier / lab name" required>
                    <input type="text" name="country" class="laleen-rd-flat-input" style="width: 140px;" placeholder="Country (optional)">
                    <button type="submit" class="laleen-rd-action-link is-primary">Save</button>
                    <button type="button" class="laleen-rd-action-link" id="rdCancelAddSupplierBtn">Cancel</button>
                </form>
            @endif

            <div class="table-responsive">
                <table class="laleen-rd-flat-table" id="rdSamplesTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Supplier</th>
                            <th>Sample Status</th>
                            <th>Smell</th>
                            <th>Cost</th>
                            <th>Texture</th>
                            <th>Results</th>
                            <th>Concept Clear</th>
                            <th>Verdict</th>
                            @if ($canEditProducts)
                                <th class="text-end no-print" style="width: 36px; padding-right: 0;"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sampleRows as $sRow)
                            <tr data-sample-row="{{ $sRow['id'] }}"
                                data-sample-id="{{ $sRow['id'] }}"
                                data-supplier-name="{{ $sRow['supplier'] }}">
                                <td class="laleen-rd-strong">{{ $sRow['product'] }}</td>
                                <td class="laleen-rd-plain">{{ $sRow['supplier'] }}</td>

                                {{-- Sample Status --}}
                                <td>
                                    @if ($canEditProducts)
                                        <select class="laleen-rd-flat-select"
                                                data-sample-field="status"
                                                aria-label="{{ $sRow['product'] }} {{ $sRow['supplier'] }} sample status">
                                            @foreach ($sampleStatuses as $val => $label)
                                                <option value="{{ $val }}" {{ $sRow['status'] === $val ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="laleen-rd-plain">{{ $sRow['status_label'] }}</span>
                                    @endif
                                </td>

                                {{-- Smell (1 or 5) --}}
                                <td>
                                    @if ($canEditProducts)
                                        <select class="laleen-rd-flat-select is-score"
                                                data-sample-field="score_smell"
                                                aria-label="{{ $sRow['product'] }} {{ $sRow['supplier'] }} smell">
                                            <option value="">—</option>
                                            @foreach ([1, 5] as $n)
                                                <option value="{{ $n }}" {{ (int) ($sRow['score_smell'] ?? 0) === $n ? 'selected' : '' }}>{{ $n }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="laleen-rd-plain">{{ $sRow['score_smell'] ?? '—' }}</span>
                                    @endif
                                </td>

                                {{-- Cost (1, 5, or short text/quote) --}}
                                <td>
                                    @if ($canEditProducts)
                                        <input type="text"
                                               class="laleen-rd-flat-input"
                                               style="width: 82px;"
                                               placeholder="—"
                                               value="{{ $sRow['cost_display'] }}"
                                               data-sample-cost
                                               aria-label="{{ $sRow['product'] }} {{ $sRow['supplier'] }} cost">
                                    @else
                                        <span class="laleen-rd-plain">{{ $sRow['cost_display'] !== '' ? $sRow['cost_display'] : '—' }}</span>
                                    @endif
                                </td>

                                {{-- Texture (1 or 5) --}}
                                <td>
                                    @if ($canEditProducts)
                                        <select class="laleen-rd-flat-select is-score"
                                                data-sample-field="score_texture"
                                                aria-label="{{ $sRow['product'] }} {{ $sRow['supplier'] }} texture">
                                            <option value="">—</option>
                                            @foreach ([1, 5] as $n)
                                                <option value="{{ $n }}" {{ (int) ($sRow['score_texture'] ?? 0) === $n ? 'selected' : '' }}>{{ $n }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="laleen-rd-plain">{{ $sRow['score_texture'] ?? '—' }}</span>
                                    @endif
                                </td>

                                {{-- Results (1 or 5) --}}
                                <td>
                                    @if ($canEditProducts)
                                        <select class="laleen-rd-flat-select is-score"
                                                data-sample-field="score_results"
                                                aria-label="{{ $sRow['product'] }} {{ $sRow['supplier'] }} results">
                                            <option value="">—</option>
                                            @foreach ([1, 5] as $n)
                                                <option value="{{ $n }}" {{ (int) ($sRow['score_results'] ?? 0) === $n ? 'selected' : '' }}>{{ $n }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="laleen-rd-plain">{{ $sRow['score_results'] ?? '—' }}</span>
                                    @endif
                                </td>

                                {{-- Concept Clear (1 or 5) --}}
                                <td>
                                    @if ($canEditProducts)
                                        <select class="laleen-rd-flat-select is-score"
                                                data-sample-field="score_concept_clear"
                                                aria-label="{{ $sRow['product'] }} {{ $sRow['supplier'] }} concept clear">
                                            <option value="">—</option>
                                            @foreach ([1, 5] as $n)
                                                <option value="{{ $n }}" {{ (int) ($sRow['score_concept_clear'] ?? 0) === $n ? 'selected' : '' }}>{{ $n }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="laleen-rd-plain">{{ $sRow['score_concept_clear'] ?? '—' }}</span>
                                    @endif
                                </td>

                                {{-- Verdict --}}
                                <td>
                                    @if ($canEditProducts)
                                        <select class="laleen-rd-flat-select"
                                                data-sample-field="verdict"
                                                aria-label="{{ $sRow['product'] }} {{ $sRow['supplier'] }} verdict">
                                            @foreach ($sampleVerdicts as $val => $label)
                                                <option value="{{ $val }}" {{ $sRow['verdict'] === $val ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="laleen-rd-plain">{{ $sRow['verdict_label'] }}</span>
                                    @endif
                                </td>

                                {{-- Per-Row Delete --}}
                                @if ($canEditProducts)
                                    <td class="text-end no-print" style="padding-right: 0;">
                                        <button type="button"
                                                class="laleen-rd-row-del-btn"
                                                data-ask-row-del
                                                title="Delete {{ $sRow['product'] }} ({{ $sRow['supplier'] }})"
                                                aria-label="Delete {{ $sRow['product'] }} {{ $sRow['supplier'] }} sample row">
                                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M2.5 4h11M5.5 4V2.75A.75.75 0 0 1 6.25 2h3.5a.75.75 0 0 1 .75.75V4m1.5 0v9.25a1.25 1.25 0 0 1-1.25 1.25h-5.5A1.25 1.25 0 0 1 4 13.25V4h8Z"/>
                                            </svg>
                                        </button>
                                        <span class="laleen-rd-inline-confirm d-none" data-row-del-confirm-wrap>
                                            <button type="button"
                                                    class="laleen-rd-confirm-link"
                                                    data-confirm-sample-del
                                                    data-delete-url="{{ route('products.rd.samples.destroy', $sRow['id']) }}">
                                                Delete
                                            </button>
                                            <button type="button" class="laleen-rd-cancel-link" data-cancel-row-del aria-label="Cancel">&times;</button>
                                        </span>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr data-empty-samples>
                                <td colspan="{{ $canEditProducts ? 10 : 9 }}" class="laleen-rd-blocked">No supplier samples tracked.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <style>
        .laleen-rd-flat-wrap {
            max-width: 1120px;
        }

        .laleen-rd-sec-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #f8efe6;
            letter-spacing: 0.01em;
        }

        .laleen-rd-flat-table {
            width: 100%;
            border-collapse: collapse;
        }

        .laleen-rd-flat-table thead th {
            text-align: left;
            font-size: 0.76rem;
            font-weight: 500;
            color: #9e8981;
            padding: 10px 14px 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            white-space: nowrap;
        }

        .laleen-rd-flat-table tbody tr {
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        .laleen-rd-flat-table tbody tr.is-removing {
            opacity: 0;
            transform: translateX(6px);
            pointer-events: none;
        }

        .laleen-rd-flat-table tbody td {
            text-align: left;
            font-size: 0.85rem;
            color: #f8efe6;
            padding: 12px 14px 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.10);
            vertical-align: middle;
            white-space: nowrap;
        }

        .laleen-rd-strong {
            font-weight: 500;
            color: #f8efe6;
        }

        .laleen-rd-plain {
            color: #d8c7c0;
        }

        .laleen-rd-blocked {
            color: #8a756e;
            font-size: 0.84rem;
        }

        /* Clean flat inline inputs & selects */
        .laleen-rd-flat-input,
        .laleen-rd-flat-select {
            background: transparent;
            border: none;
            border-bottom: 1px solid transparent;
            color: #f8efe6;
            font-size: 0.85rem;
            padding: 2px 4px 2px 0;
            outline: none;
            transition: border-color 0.15s ease, color 0.15s ease;
        }

        .laleen-rd-flat-select {
            cursor: pointer;
            padding-right: 14px;
        }

        .laleen-rd-flat-select.is-score {
            min-width: 48px;
        }

        .laleen-rd-flat-input:hover,
        .laleen-rd-flat-select:hover {
            border-bottom-color: rgba(255, 255, 255, 0.22);
        }

        .laleen-rd-flat-input:focus,
        .laleen-rd-flat-select:focus {
            border-bottom-color: #e5b8a8;
        }

        .laleen-rd-flat-input::placeholder {
            color: #8a756e;
        }

        .laleen-rd-flat-select option {
            background-color: #191115;
            color: #f8efe6;
        }

        /* Minimalist Far-Right Per-Row Trash Button & Lightweight Inline Confirm */
        .laleen-rd-row-del-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            padding: 3px 4px;
            color: #d8c7c0;
            opacity: 0.4;
            cursor: pointer;
            transition: opacity 0.15s ease, color 0.15s ease;
        }

        .laleen-rd-row-del-btn:hover {
            opacity: 1;
            color: #fb7185;
        }

        .laleen-rd-inline-confirm {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .laleen-rd-confirm-link {
            background: transparent;
            border: none;
            padding: 0;
            font-size: 0.76rem;
            font-weight: 500;
            color: #fb7185;
            cursor: pointer;
        }

        .laleen-rd-confirm-link:hover {
            text-decoration: underline;
        }

        .laleen-rd-cancel-link {
            background: transparent;
            border: none;
            padding: 0 2px;
            font-size: 0.92rem;
            line-height: 1;
            color: #9e8981;
            cursor: pointer;
        }

        .laleen-rd-cancel-link:hover {
            color: #f8efe6;
        }

        /* Inline quiet add bar (zero modals, zero drawers) */
        .laleen-rd-inline-bar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.10);
        }

        .laleen-rd-action-link {
            background: transparent;
            border: none;
            padding: 2px 4px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #9e8981;
            cursor: pointer;
        }

        .laleen-rd-action-link:hover {
            color: #f8efe6;
        }

        .laleen-rd-action-link.is-primary {
            color: #e5b8a8;
            font-weight: 600;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            .laleen-rd-sec-title,
            .laleen-rd-flat-table thead th,
            .laleen-rd-flat-table tbody td,
            .laleen-rd-flat-input,
            .laleen-rd-flat-select,
            .laleen-rd-strong,
            .laleen-rd-plain,
            .laleen-rd-blocked {
                color: #111111 !important;
                background: #ffffff !important;
            }
            .laleen-rd-flat-table thead th,
            .laleen-rd-flat-table tbody td {
                border-bottom: 1px solid #cccccc !important;
            }
        }
    </style>
@endsection

@section('scripts')
<script>
(function () {
    const CAN_EDIT = @json($canEditProducts);
    const CSRF_TOKEN = @json(csrf_token());
    const STICKER_UNLOCKED_OPTIONS = @json($stickerUnlockedStatuses);
    const PKG_URL_TEMPLATE = @json(route('products.rd.items.packaging-status', ['item' => '__ID__']));
    const SAMPLE_URL_TEMPLATE = @json(route('products.rd.samples.update', ['sample' => '__ID__']));

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    let saveFadeTimer = null;
    function flashSaved(label = 'Saved', isError = false) {
        const el = document.getElementById('rdSaveIndicator');
        if (!el) return;
        el.textContent = label;
        el.style.color = isError ? '#fb7185' : '#b8a29a';
        el.classList.add('is-visible');
        clearTimeout(saveFadeTimer);
        saveFadeTimer = setTimeout(() => {
            el.classList.remove('is-visible');
        }, 1800);
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (!CAN_EDIT) return;

        // Quiet inline Add Row / Add Supplier toggles
        const addRowForm = document.getElementById('rdInlineAddRowForm');
        const addSupplierForm = document.getElementById('rdInlineAddSupplierForm');

        document.getElementById('rdToggleAddRowBtn')?.addEventListener('click', () => {
            addSupplierForm?.classList.add('d-none');
            addRowForm?.classList.toggle('d-none');
            if (!addRowForm?.classList.contains('d-none')) {
                addRowForm.querySelector('input[name="name"]')?.focus();
            }
        });

        document.getElementById('rdCancelAddRowBtn')?.addEventListener('click', () => {
            addRowForm?.classList.add('d-none');
        });

        document.getElementById('rdToggleAddSupplierBtn')?.addEventListener('click', () => {
            addRowForm?.classList.add('d-none');
            addSupplierForm?.classList.toggle('d-none');
            if (!addSupplierForm?.classList.contains('d-none')) {
                addSupplierForm.querySelector('input[name="name"]')?.focus();
            }
        });

        document.getElementById('rdCancelAddSupplierBtn')?.addEventListener('click', () => {
            addSupplierForm?.classList.add('d-none');
        });

        // Section 1: Regional SKU & Packaging Baseline inline updates
        function renderStickerCell(tr, rowData) {
            const cell = tr.querySelector('[data-pkg-sticker-cell]');
            if (!cell) return;

            if (rowData.is_sticker_blocked) {
                cell.innerHTML = '<span class="laleen-rd-blocked" data-sticker-locked>Blocked by Bottle</span>';
                return;
            }

            const opts = Object.entries(STICKER_UNLOCKED_OPTIONS).map(([val, lbl]) => {
                const sel = rowData.sticker_status === val ? 'selected' : '';
                return `<option value="${escapeHtml(val)}" ${sel}>${escapeHtml(lbl)}</option>`;
            }).join('');

            cell.innerHTML = `<select class="laleen-rd-flat-select" data-pkg-sticker-status>${opts}</select>`;
            bindStickerSelect(tr);
        }

        async function patchPackagingRow(tr, payload) {
            const itemId = tr.getAttribute('data-item-id');
            const market = tr.getAttribute('data-market');
            const url = PKG_URL_TEMPLATE.replace('__ID__', encodeURIComponent(itemId));

            try {
                const res = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                    },
                    body: JSON.stringify(Object.assign({ market }, payload)),
                });
                const data = await res.json();
                if (res.ok && data.ok && data.row) {
                    const volInput = tr.querySelector('[data-pkg-volume]');
                    if (volInput && document.activeElement !== volInput) {
                        volInput.value = data.row.volume;
                    }
                    const bottleSel = tr.querySelector('[data-pkg-bottle-status]');
                    if (bottleSel) {
                        bottleSel.value = data.row.bottle_status;
                    }
                    renderStickerCell(tr, data.row);
                    flashSaved('Saved');
                } else {
                    flashSaved('Save failed', true);
                }
            } catch {
                flashSaved('Offline', true);
            }
        }

        function bindStickerSelect(tr) {
            const stickerSel = tr.querySelector('[data-pkg-sticker-status]');
            if (!stickerSel || stickerSel.dataset.bound === '1') return;
            stickerSel.dataset.bound = '1';
            stickerSel.addEventListener('change', () => {
                patchPackagingRow(tr, { sticker_status: stickerSel.value });
            });
        }

        document.querySelectorAll('#rdRegionalTable tbody tr[data-regional-row]').forEach((tr) => {
            const volInput = tr.querySelector('[data-pkg-volume]');
            if (volInput) {
                let lastSavedVol = volInput.value.trim();
                const saveVolIfChanged = () => {
                    const next = volInput.value.trim();
                    if (!next || next === lastSavedVol) return;
                    lastSavedVol = next;
                    patchPackagingRow(tr, { volume: next });
                };
                volInput.addEventListener('blur', saveVolIfChanged);
                volInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        volInput.blur();
                    }
                });
            }

            const bottleSel = tr.querySelector('[data-pkg-bottle-status]');
            bottleSel?.addEventListener('change', () => {
                if (bottleSel.value !== 'finalized') {
                    renderStickerCell(tr, { is_sticker_blocked: true, sticker_status: 'blocked_by_bottle' });
                } else if (tr.querySelector('[data-sticker-locked]')) {
                    renderStickerCell(tr, { is_sticker_blocked: false, sticker_status: 'ready_for_dieline' });
                }
                patchPackagingRow(tr, { bottle_status: bottleSel.value });
            });

            bindStickerSelect(tr);
        });

        // Section 2: Supplier Sample Evaluation inline updates
        async function patchSampleRow(tr, payload) {
            const sampleId = tr.getAttribute('data-sample-id');
            const url = SAMPLE_URL_TEMPLATE.replace('__ID__', encodeURIComponent(sampleId));

            try {
                const res = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (res.ok && data.ok) {
                    flashSaved('Saved');
                } else {
                    flashSaved('Save failed', true);
                }
            } catch {
                flashSaved('Offline', true);
            }
        }

        document.querySelectorAll('#rdSamplesTable tbody tr[data-sample-row]').forEach((tr) => {
            tr.querySelectorAll('[data-sample-field]').forEach((sel) => {
                sel.addEventListener('change', () => {
                    const field = sel.getAttribute('data-sample-field');
                    patchSampleRow(tr, { [field]: sel.value === '' ? null : sel.value });
                });
            });

            const costInput = tr.querySelector('[data-sample-cost]');
            if (costInput) {
                let lastSavedCost = costInput.value.trim();
                const saveCostIfChanged = () => {
                    const next = costInput.value.trim();
                    if (next === lastSavedCost) return;
                    lastSavedCost = next;
                    patchSampleRow(tr, { cost: next });
                };
                costInput.addEventListener('blur', saveCostIfChanged);
                costInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        costInput.blur();
                    }
                });
            }
        });

        // Lightweight Inline Row Deletion for Both Tables
        document.querySelectorAll('[data-ask-row-del]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const td = btn.closest('td');
                if (!td) return;
                btn.classList.add('d-none');
                td.querySelector('[data-row-del-confirm-wrap]')?.classList.remove('d-none');
            });
        });

        document.querySelectorAll('[data-cancel-row-del]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const td = btn.closest('td');
                if (!td) return;
                td.querySelector('[data-row-del-confirm-wrap]')?.classList.add('d-none');
                td.querySelector('[data-ask-row-del]')?.classList.remove('d-none');
            });
        });

        function removeRowSmoothly(tr, tableSelector, emptyText, colCount) {
            tr.classList.add('is-removing');
            setTimeout(() => {
                const tbody = tr.closest('tbody');
                tr.remove();
                if (tbody && tbody.querySelectorAll('tr').length === 0) {
                    tbody.innerHTML = `<tr><td colspan="${colCount}" class="laleen-rd-blocked">${escapeHtml(emptyText)}</td></tr>`;
                }
            }, 180);
        }

        document.querySelectorAll('[data-confirm-regional-del]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const tr = btn.closest('tr');
                const url = btn.getAttribute('data-delete-url');
                if (!tr || !url) return;
                btn.disabled = true;

                try {
                    const res = await fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': CSRF_TOKEN,
                        },
                    });
                    const data = await res.json();
                    if (res.ok && data.ok) {
                        removeRowSmoothly(tr, '#rdRegionalTable', 'No regional rows tracked.', 6);
                        flashSaved('Deleted');
                    } else {
                        btn.disabled = false;
                        flashSaved('Delete failed', true);
                    }
                } catch {
                    btn.disabled = false;
                    flashSaved('Offline', true);
                }
            });
        });

        document.querySelectorAll('[data-confirm-sample-del]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const tr = btn.closest('tr');
                const url = btn.getAttribute('data-delete-url');
                if (!tr || !url) return;
                btn.disabled = true;

                try {
                    const res = await fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': CSRF_TOKEN,
                        },
                    });
                    const data = await res.json();
                    if (res.ok && data.ok) {
                        const headingEl = document.getElementById('rdSampleSectionHeading');
                        if (headingEl && typeof data.supplier_heading === 'string') {
                            headingEl.textContent = data.supplier_heading !== ''
                                ? `2. Supplier Sample Evaluation (${data.supplier_heading})`
                                : '2. Supplier Sample Evaluation';
                        }
                        removeRowSmoothly(tr, '#rdSamplesTable', 'No supplier samples tracked.', 10);
                        flashSaved('Deleted');
                    } else {
                        btn.disabled = false;
                        flashSaved('Delete failed', true);
                    }
                } catch {
                    btn.disabled = false;
                    flashSaved('Offline', true);
                }
            });
        });
    });
})();
</script>
@endsection
