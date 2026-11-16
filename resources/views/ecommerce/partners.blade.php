@extends('layouts.app')
@section('title', 'Partner Ledger')

@include('ecommerce._styles')

@section('content')
    @php
        $region = $region ?? 'pk';
        $currency = $currency ?? 'PKR';
        $partnerDeleteMeta = $partnerDeleteMeta ?? [];
    @endphp

    <style>
        .ec-partner-card {
            position: relative;
        }

        .ec-partner-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .ec-partner-card-head h6 {
            margin-bottom: 0;
        }

        .ec-partner-delete-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            border-radius: 8px;
            border: 1px solid transparent;
            background: transparent;
            color: rgba(201, 163, 154, 0.58);
            font-size: 13px;
            line-height: 1;
            transition: color 0.15s ease, background 0.15s ease, border-color 0.15s ease;
            flex-shrink: 0;
        }

        .ec-partner-delete-btn:hover,
        .ec-partner-delete-btn:focus-visible {
            color: #e89a90;
            background: rgba(168, 82, 74, 0.16);
            border-color: rgba(217, 143, 131, 0.28);
            outline: none;
        }

        .ec-ledger-modal .modal-content {
            background: linear-gradient(180deg, #22191c 0%, #171114 100%);
            border: 1px solid rgba(217, 143, 131, 0.24);
            border-radius: 16px;
            color: #f8efe6;
            box-shadow: 0 22px 48px rgba(0, 0, 0, 0.55);
        }

        .ec-ledger-modal .modal-header,
        .ec-ledger-modal .modal-footer {
            border-color: rgba(217, 143, 131, 0.14);
        }

        .ec-ledger-modal .modal-title {
            color: #f8efe6;
            font-weight: 600;
            font-size: 16px;
        }

        .ec-ledger-modal .form-label {
            color: #d7beb6;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .ec-dark-input,
        .ec-dark-select {
            background-color: #1c1518 !important;
            border: 1px solid rgba(217, 143, 131, 0.26) !important;
            color: #f8efe6 !important;
            border-radius: 10px;
            color-scheme: dark;
        }

        .ec-dark-input::placeholder {
            color: #967f78 !important;
        }

        .ec-dark-input:focus,
        .ec-dark-select:focus {
            background-color: #21181c !important;
            border-color: #d98f83 !important;
            color: #f8efe6 !important;
            box-shadow: 0 0 0 3px rgba(217, 143, 131, 0.16) !important;
        }

        .ec-dark-select option {
            background-color: #1c1518;
            color: #f8efe6;
        }

        .ec-inline-link-btn {
            background: none;
            border: 0;
            padding: 0;
            font-size: 12px;
            font-weight: 600;
            color: var(--ec-primary);
            cursor: pointer;
            transition: color 0.15s ease;
        }

        .ec-inline-link-btn:hover,
        .ec-inline-link-btn:focus-visible {
            color: #f4c6bc;
            text-decoration: underline;
            outline: none;
        }

        .ec-btn-ghost {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(217, 143, 131, 0.22);
            color: #d7beb6;
        }

        .ec-btn-ghost:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #f8efe6;
        }

        .ec-btn-danger-soft {
            background: rgba(168, 82, 74, 0.24);
            border: 1px solid rgba(224, 147, 138, 0.42);
            color: #f8deda;
            font-weight: 600;
        }

        .ec-btn-danger-soft:hover {
            background: rgba(168, 82, 74, 0.38);
            border-color: rgba(224, 147, 138, 0.65);
            color: #ffffff;
        }

        .ec-tx-delete-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            padding: 0;
            border-radius: 8px;
            border: 1px solid rgba(217, 143, 131, 0.18);
            background: transparent;
            color: rgba(201, 163, 154, 0.72);
            transition: all 0.15s ease;
        }

        .ec-tx-delete-btn:hover {
            color: #f0a299;
            background: rgba(168, 82, 74, 0.18);
            border-color: rgba(224, 147, 138, 0.4);
        }

        .ec-validation-banner {
            background: rgba(168, 82, 74, 0.16);
            border: 1px solid rgba(224, 147, 138, 0.36);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 18px;
            color: #f5d8d4;
            font-size: 13px;
        }
    </style>

    <div class="ec-header">
        <div>
            <div class="ec-sub text-uppercase fw-bold mb-1" style="font-size: 11px; letter-spacing: .08em; color: var(--ec-gold);">Financials &amp; Inventory</div>
            <h4>Partner Investment &amp; Capital Ledger ({{ $currency }})</h4>
            <p>Capital matching across the partners, plus the full capital injection history.</p>
        </div>
        @moduleEdit('ecommerce')
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartnerModal">+ Add Partner</button>
        @endmoduleEdit
    </div>

    @include('ecommerce.partials._financials_tabs')

    @if ($errors->any())
        <div class="ec-validation-banner" role="alert">
            <div class="fw-semibold mb-1">Please check your entry:</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="ec-card">
                <h6>Total Capital Pool ({{ $currency }})</h6>
                <div class="ec-value">{{ $currency }} {{ number_format($totalPool, 2) }}</div>
                <div class="ec-sub">Total cash injected across all partners ({{ $currency }})</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="ec-card">
                <h6>Available Balance (Runway)</h6>
                <div class="ec-value {{ $remainingBalance >= 0 ? 'ec-positive' : 'ec-negative' }}">{{ $currency }} {{ number_format($remainingBalance, 2) }}</div>
                <div class="ec-sub">Capital Pool &minus; Partner-Funded Expenses</div>
            </div>
        </div>
    </div>

    <div class="row" id="partnerCardsGrid">
        @forelse ($equalization as $row)
            @php
                $pModel = $row['partner'];
                $txMeta = $partnerDeleteMeta[$pModel->id] ?? ['count' => 0, 'summary' => $currency . ' 0.00'];
            @endphp
            <div class="col-md-4">
                <div class="ec-card ec-partner-card" data-partner-card="{{ $pModel->id }}">
                    <div class="ec-partner-card-head">
                        <h6>{{ $pModel->name }}</h6>
                        @moduleEdit('ecommerce')
                            <button type="button"
                                class="ec-partner-delete-btn"
                                title="Remove {{ $pModel->name }}"
                                aria-label="Remove {{ $pModel->name }}"
                                data-bs-toggle="modal"
                                data-bs-target="#deletePartnerModal"
                                data-partner-id="{{ $pModel->id }}"
                                data-partner-name="{{ $pModel->name }}"
                                data-delete-url="{{ route('ecommerce.partners.destroy', $pModel->id) }}"
                                data-tx-count="{{ $txMeta['count'] }}"
                                data-tx-summary="{{ $txMeta['summary'] }}">
                                <i class="bi bi-trash3"></i>
                            </button>
                        @endmoduleEdit
                    </div>
                    <div class="ec-value">{{ $currency }} {{ number_format($row['contributed'], 2) }}</div>
                    <div class="ec-sub">{{ number_format($row['percent'], 1) }}% of total pool</div>
                    <div class="mt-2">
                        <span class="ec-badge ec-badge-distribution">Distributed: {{ $currency }} {{ number_format($row['distributed'], 2) }}</span>
                    </div>
                    <hr style="border-color: var(--ec-border); margin: 10px 0;">
                    @if ($row['balance'] <= 0.01)
                        <div class="ec-sub ec-positive">Fully Matched</div>
                    @else
                        <div class="ec-sub ec-negative">Owes {{ $currency }} {{ number_format($row['balance'], 2) }} to match the top investor</div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="ec-card text-center py-4">
                    <div class="ec-sub mb-0">No partners in the ledger yet &mdash; click <strong>+ Add Partner</strong> above to add your first partner.</div>
                </div>
            </div>
        @endforelse
    </div>

    <div class="ec-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Transaction History</h6>
            @moduleEdit('ecommerce')
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal" {{ $partners->isEmpty() ? 'disabled' : '' }}>+ Add Transaction</button>
            @endmoduleEdit
        </div>
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Partner</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Note</th>
                        @moduleEdit('ecommerce')<th class="text-end"></th>@endmoduleEdit
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->transaction_date->format('d M Y') }}</td>
                            <td>{{ $transaction->partner?->name ?? '—' }}</td>
                            <td><span class="ec-badge ec-badge-{{ $transaction->type }}">{{ ucfirst($transaction->type) }}</span></td>
                            <td>{{ $transaction->category ?? '—' }}</td>
                            <td class="{{ $transaction->type === 'distribution' ? 'ec-negative' : '' }}">
                                {{ $transaction->type === 'distribution' ? '−' : '+' }} {{ $transaction->currency ?? $currency }} {{ number_format($transaction->amount, 2) }}
                            </td>
                            <td>{{ $transaction->reference_note ?? '—' }}</td>
                            @moduleEdit('ecommerce')
                                <td class="text-end">
                                    <button type="button"
                                        class="ec-tx-delete-btn"
                                        title="Delete transaction"
                                        aria-label="Delete transaction"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteTransactionModal"
                                        data-delete-url="{{ route('ecommerce.transactions.destroy', $transaction->id) }}"
                                        data-tx-label="{{ ($transaction->partner?->name ?? 'Partner') . ' · ' . ($transaction->currency ?? $currency) . ' ' . number_format($transaction->amount, 2) }}">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </td>
                            @endmoduleEdit
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No transactions logged yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @moduleEdit('ecommerce')
        {{-- Add Partner Modal --}}
        <div class="modal fade ec-ledger-modal" id="addPartnerModal" tabindex="-1" aria-labelledby="addPartnerModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.partners.store') }}" method="POST" id="addPartnerForm">
                        @csrf
                        <input type="hidden" name="region" value="{{ $region }}">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addPartnerModalTitle">Add Partner</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="partnerNameInput">Name</label>
                                <input type="text"
                                    name="name"
                                    id="partnerNameInput"
                                    class="form-control ec-dark-input @error('name') is-invalid @enderror"
                                    value="{{ old('name') }}"
                                    placeholder="e.g. ZAYD"
                                    required
                                    maxlength="255">
                                @error('name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="partnerEmailInput">Email <span class="text-muted fw-normal text-lowercase">(optional)</span></label>
                                <input type="email"
                                    name="email"
                                    id="partnerEmailInput"
                                    class="form-control ec-dark-input @error('email') is-invalid @enderror"
                                    value="{{ old('email') }}"
                                    placeholder="partner@laleen.qa"
                                    maxlength="255">
                            </div>
                            <div class="mb-1">
                                <label class="form-label" for="partnerEquityInput">Equity %</label>
                                <input type="number"
                                    step="0.0001"
                                    min="0"
                                    max="100"
                                    name="equity_percentage"
                                    id="partnerEquityInput"
                                    class="form-control ec-dark-input"
                                    value="{{ old('equity_percentage', '33.3333') }}">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn ec-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Partner</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Delete Partner Confirmation Modal (Zero raw browser confirm()) --}}
        <div class="modal fade ec-ledger-modal" id="deletePartnerModal" tabindex="-1" aria-labelledby="deletePartnerModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="" method="POST" id="deletePartnerForm">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="region" value="{{ $region }}">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deletePartnerModalTitle">Remove Partner</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0" id="deletePartnerModalMessage" style="font-size: 13.5px; line-height: 1.6; color: #ead8d2;">
                                Remove this partner from the Partner Ledger?
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn ec-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn ec-btn-danger-soft" id="confirmDeletePartnerBtn">Remove Partner</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Delete Transaction Confirmation Modal --}}
        <div class="modal fade ec-ledger-modal" id="deleteTransactionModal" tabindex="-1" aria-labelledby="deleteTransactionModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <form action="" method="POST" id="deleteTransactionForm">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="region" value="{{ $region }}">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteTransactionModalTitle">Delete Transaction</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0" id="deleteTransactionModalMessage" style="font-size: 13px; line-height: 1.55; color: #ead8d2;">
                                Delete this recorded transaction?
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm ec-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm ec-btn-danger-soft">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Add Transaction Modal --}}
        <div class="modal fade ec-ledger-modal" id="addTransactionModal" tabindex="-1" aria-labelledby="addTransactionModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="" method="POST" id="transactionForm">
                        @csrf
                        <input type="hidden" name="region" value="{{ $region }}">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addTransactionModalTitle">Add Transaction</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="transactionPartnerSelect">Partner</label>
                                <select name="_partner" id="transactionPartnerSelect" class="form-select ec-dark-select" required>
                                    <option value="" disabled selected>Select partner</option>
                                    @foreach ($partners as $partner)
                                        <option value="{{ route('ecommerce.partners.transactions.store', $partner->id) }}">{{ $partner->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="transactionTypeSelect">Type</label>
                                <select name="type" id="transactionTypeSelect" class="form-select ec-dark-select" required>
                                    <option value="injection" selected>Capital Injection (Money In)</option>
                                    <option value="distribution">Profit Distribution / Withdrawal (Money Out)</option>
                                </select>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="transactionCurrencySelect">Currency</label>
                                    <select name="currency" id="transactionCurrencySelect" class="form-select ec-dark-select" required>
                                        <option value="PKR" {{ $currency === 'PKR' ? 'selected' : '' }}>PKR (Pakistan)</option>
                                        <option value="QAR" {{ $currency === 'QAR' ? 'selected' : '' }}>QAR (Qatar)</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="transactionAmountInput">Amount</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" id="transactionAmountInput" class="form-control ec-dark-input" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label mb-0" for="transactionCategorySelect">Category</label>
                                    <button type="button" class="ec-inline-link-btn" id="toggleNewCategoryBtn">+ New category</button>
                                </div>
                                <select name="category" id="transactionCategorySelect" class="form-select ec-dark-select" required>
                                    @foreach ($transactionCategories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                    <option value="__create_new__">+ New category...</option>
                                </select>

                                <div id="inlineNewCategoryRow" class="mt-2 d-none">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text"
                                            id="inlineNewCategoryInput"
                                            class="form-control form-control-sm ec-dark-input"
                                            maxlength="100"
                                            placeholder="e.g. Influencer Seeding, Lab Testing..."
                                            autocomplete="off">
                                        <button type="button" class="btn btn-sm btn-primary flex-shrink-0" id="confirmNewCategoryBtn">Add</button>
                                        <button type="button" class="btn btn-sm ec-btn-ghost flex-shrink-0" id="cancelNewCategoryBtn">Cancel</button>
                                    </div>
                                    <div id="inlineNewCategoryError" class="text-danger small mt-1 d-none"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="transactionNoteInput">Reference Note</label>
                                <input type="text" name="reference_note" id="transactionNoteInput" class="form-control ec-dark-input" placeholder="Optional reference or memo" maxlength="255">
                            </div>
                            <div class="mb-1">
                                <label class="form-label" for="transactionDateInput">Date</label>
                                <input type="date" name="transaction_date" id="transactionDateInput" class="form-control ec-dark-input" value="{{ now()->toDateString() }}" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn ec-btn-ghost" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const csrfToken = '{{ csrf_token() }}';
                const createCategoryUrl = '{{ route('ecommerce.partners.categories.store') }}';

                // 1. Partner Select -> Form Action Sync
                const txForm = document.getElementById('transactionForm');
                const partnerSelect = document.getElementById('transactionPartnerSelect');
                if (txForm && partnerSelect) {
                    const syncTxAction = () => {
                        if (partnerSelect.value) {
                            txForm.action = partnerSelect.value;
                        }
                    };
                    partnerSelect.addEventListener('change', syncTxAction);
                    syncTxAction();
                }

                // 2. Delete Partner Confirmation Modal Population
                const deletePartnerModalEl = document.getElementById('deletePartnerModal');
                const deletePartnerForm = document.getElementById('deletePartnerForm');
                const deletePartnerTitle = document.getElementById('deletePartnerModalTitle');
                const deletePartnerMsg = document.getElementById('deletePartnerModalMessage');
                const confirmDeletePartnerBtn = document.getElementById('confirmDeletePartnerBtn');

                if (deletePartnerModalEl) {
                    deletePartnerModalEl.addEventListener('show.bs.modal', function (event) {
                        const trigger = event.relatedTarget;
                        if (!trigger) return;

                        const partnerName = trigger.getAttribute('data-partner-name') || 'this partner';
                        const deleteUrl = trigger.getAttribute('data-delete-url') || '';
                        const txCount = parseInt(trigger.getAttribute('data-tx-count') || '0', 10) || 0;
                        const txSummary = trigger.getAttribute('data-tx-summary') || '';

                        if (deletePartnerForm) {
                            deletePartnerForm.action = deleteUrl;
                        }

                        if (txCount > 0) {
                            if (deletePartnerTitle) deletePartnerTitle.textContent = 'Delete ' + partnerName;
                            if (confirmDeletePartnerBtn) confirmDeletePartnerBtn.textContent = 'Delete Partner & Transactions';
                            if (deletePartnerMsg) {
                                deletePartnerMsg.textContent = `Delete ${partnerName}? This will also remove their ${txCount} recorded transaction(s) (${txSummary}) and recalculate the Total Capital Pool and Available Balance.`;
                            }
                        } else {
                            if (deletePartnerTitle) deletePartnerTitle.textContent = 'Remove ' + partnerName;
                            if (confirmDeletePartnerBtn) confirmDeletePartnerBtn.textContent = 'Remove Partner';
                            if (deletePartnerMsg) {
                                deletePartnerMsg.textContent = `Remove ${partnerName} from the Partner Ledger?`;
                            }
                        }
                    });
                }

                // 3. Delete Transaction Confirmation Modal Population
                const deleteTxModalEl = document.getElementById('deleteTransactionModal');
                const deleteTxForm = document.getElementById('deleteTransactionForm');
                const deleteTxMsg = document.getElementById('deleteTransactionModalMessage');

                if (deleteTxModalEl) {
                    deleteTxModalEl.addEventListener('show.bs.modal', function (event) {
                        const trigger = event.relatedTarget;
                        if (!trigger) return;
                        const deleteUrl = trigger.getAttribute('data-delete-url') || '';
                        const txLabel = trigger.getAttribute('data-tx-label') || 'this transaction';
                        if (deleteTxForm) deleteTxForm.action = deleteUrl;
                        if (deleteTxMsg) deleteTxMsg.textContent = `Delete recorded transaction (${txLabel})?`;
                    });
                }

                // 4. Inline Custom Category Creation inside Add Transaction Modal
                const categorySelect = document.getElementById('transactionCategorySelect');
                const toggleNewCatBtn = document.getElementById('toggleNewCategoryBtn');
                const newCatRow = document.getElementById('inlineNewCategoryRow');
                const newCatInput = document.getElementById('inlineNewCategoryInput');
                const confirmNewCatBtn = document.getElementById('confirmNewCategoryBtn');
                const cancelNewCatBtn = document.getElementById('cancelNewCategoryBtn');
                const newCatError = document.getElementById('inlineNewCategoryError');
                let previousSelectedCategory = categorySelect && categorySelect.value !== '__create_new__'
                    ? categorySelect.value
                    : 'Initial Inventory';

                function showInlineCategoryCreator() {
                    if (!newCatRow || !newCatInput) return;
                    if (categorySelect && categorySelect.value && categorySelect.value !== '__create_new__') {
                        previousSelectedCategory = categorySelect.value;
                    }
                    newCatRow.classList.remove('d-none');
                    if (newCatError) {
                        newCatError.textContent = '';
                        newCatError.classList.add('d-none');
                    }
                    requestAnimationFrame(() => newCatInput.focus());
                }

                function hideInlineCategoryCreator(restorePrevious = true) {
                    if (!newCatRow) return;
                    newCatRow.classList.add('d-none');
                    if (newCatInput) newCatInput.value = '';
                    if (newCatError) {
                        newCatError.textContent = '';
                        newCatError.classList.add('d-none');
                    }
                    if (restorePrevious && categorySelect && categorySelect.value === '__create_new__') {
                        categorySelect.value = previousSelectedCategory;
                    }
                }

                function upsertAndSelectCategoryOption(catName) {
                    if (!categorySelect || !catName) return;
                    const trimmed = catName.trim();
                    if (!trimmed) return;

                    let matchedOption = Array.from(categorySelect.options).find(
                        opt => opt.value !== '__create_new__' && opt.value.toLowerCase() === trimmed.toLowerCase()
                    );

                    if (!matchedOption) {
                        matchedOption = document.createElement('option');
                        matchedOption.value = trimmed;
                        matchedOption.textContent = trimmed;
                        const createNewOpt = categorySelect.querySelector('option[value="__create_new__"]');
                        if (createNewOpt) {
                            categorySelect.insertBefore(matchedOption, createNewOpt);
                        } else {
                            categorySelect.appendChild(matchedOption);
                        }
                    }

                    categorySelect.value = matchedOption.value;
                    previousSelectedCategory = matchedOption.value;
                }

                async function saveInlineCategory() {
                    if (!newCatInput) return;
                    const rawName = newCatInput.value.trim();
                    if (!rawName) {
                        if (newCatError) {
                            newCatError.textContent = 'Enter a category name first.';
                            newCatError.classList.remove('d-none');
                        }
                        newCatInput.focus();
                        return;
                    }

                    if (confirmNewCatBtn) confirmNewCatBtn.disabled = true;

                    try {
                        const response = await fetch(createCategoryUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ name: rawName }),
                        });

                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            const msg = (payload && payload.errors && payload.errors.name && payload.errors.name[0])
                                || payload.message
                                || 'Could not save category.';
                            if (newCatError) {
                                newCatError.textContent = msg;
                                newCatError.classList.remove('d-none');
                            }
                            return;
                        }

                        const canonical = (payload && payload.category) ? String(payload.category) : rawName;
                        upsertAndSelectCategoryOption(canonical);
                        hideInlineCategoryCreator(false);
                    } catch (err) {
                        // Fallback: add locally into select so form submit persists it via storeTransaction
                        upsertAndSelectCategoryOption(rawName);
                        hideInlineCategoryCreator(false);
                    } finally {
                        if (confirmNewCatBtn) confirmNewCatBtn.disabled = false;
                    }
                }

                if (toggleNewCatBtn) {
                    toggleNewCatBtn.addEventListener('click', showInlineCategoryCreator);
                }

                if (categorySelect) {
                    categorySelect.addEventListener('change', function () {
                        if (categorySelect.value === '__create_new__') {
                            showInlineCategoryCreator();
                        } else {
                            previousSelectedCategory = categorySelect.value;
                            hideInlineCategoryCreator(false);
                        }
                    });
                }

                if (confirmNewCatBtn) {
                    confirmNewCatBtn.addEventListener('click', saveInlineCategory);
                }

                if (cancelNewCatBtn) {
                    cancelNewCatBtn.addEventListener('click', function () {
                        hideInlineCategoryCreator(true);
                    });
                }

                if (newCatInput) {
                    newCatInput.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            saveInlineCategory();
                        } else if (e.key === 'Escape') {
                            e.preventDefault();
                            e.stopPropagation();
                            hideInlineCategoryCreator(true);
                        }
                    });
                }

                if (txForm) {
                    txForm.addEventListener('submit', function (e) {
                        if (partnerSelect && partnerSelect.value) {
                            txForm.action = partnerSelect.value;
                        } else {
                            e.preventDefault();
                            partnerSelect?.focus();
                            return;
                        }

                        // If user typed a new category inline and pressed Save directly without clicking Add first
                        if (newCatRow && !newCatRow.classList.contains('d-none') && newCatInput && newCatInput.value.trim() !== '') {
                            upsertAndSelectCategoryOption(newCatInput.value.trim());
                            hideInlineCategoryCreator(false);
                        } else if (categorySelect && categorySelect.value === '__create_new__') {
                            e.preventDefault();
                            showInlineCategoryCreator();
                        }
                    });
                }

                @if ($errors->has('name') || $errors->has('email') || $errors->has('equity_percentage'))
                    const addPartnerEl = document.getElementById('addPartnerModal');
                    if (addPartnerEl && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(addPartnerEl).show();
                    }
                @endif
            });
        </script>
    @endmoduleEdit
@endsection
