@extends('layouts.app')
@section('title', 'Ecommerce Expenses')

@include('ecommerce._styles')

@section('content')
    <div class="ec-header">
        <div>
            <h4>Ecommerce Expense Tracker</h4>
            <p>Online-only operating expenses, isolated from salon/physical costs.</p>
        </div>
        @moduleEdit('ecommerce')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#expenseModal">+ Log Expense</button>
        @endmoduleEdit
    </div>

    @include('ecommerce._nav')

    <div class="row">
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total Capital Pool</h6>
                <div class="ec-value">PKR {{ number_format($totalPool, 2) }}</div>
                <div class="ec-sub">Total cash injected across all partners</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Total Ecommerce Expenses</h6>
                <div class="ec-value">PKR {{ number_format($totalExpenses, 2) }}</div>
                <div class="ec-sub">{{ $expenses->count() }} entries, all time</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="ec-card">
                <h6>Available Balance (Runway)</h6>
                <div class="ec-value {{ $remainingBalance >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($remainingBalance, 2) }}</div>
                <div class="ec-sub">Capital Pool &minus; Total Ecommerce Expenses</div>
            </div>
        </div>
    </div>

    <div class="ec-card">
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Paid Using</th>
                        <th>Vendor</th>
                        <th>Amount</th>
                        <th>Receipt</th>
                        <th>Logged By</th>
                        @moduleEdit('ecommerce')<th></th>@endmoduleEdit
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->expense_date->format('d M Y') }}</td>
                            <td>
                                {{ $expense->title }}
                                @if ($expense->notes)
                                    <div class="ec-sub" style="margin-top: 2px;">{{ $expense->notes }}</div>
                                @endif
                            </td>
                            <td><span class="ec-badge" style="background: rgba(217,143,131,0.14); color: var(--ec-ink);">{{ $expense->category }}</span></td>
                            <td>
                                @if ($expense->funding_source === 'sales')
                                    <span class="ec-badge ec-badge-injection">Sales Revenue</span>
                                @else
                                    <span class="ec-badge ec-badge-distribution">Partner Ledger</span>
                                @endif
                            </td>
                            <td>{{ $expense->vendor ?? '—' }}</td>
                            <td>PKR {{ number_format($expense->amount, 2) }}</td>
                            <td>
                                @if ($expense->receipt_path)
                                    <a href="{{ asset($expense->receipt_path) }}" target="_blank">View</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $expense->creator?->name ?? '—' }}</td>
                            @moduleEdit('ecommerce')
                                <td>
                                    <form action="{{ route('ecommerce.expenses.destroy', $expense->id) }}" method="POST" onsubmit="return confirm('Delete this expense?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            @endmoduleEdit
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted">No ecommerce expenses logged yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @moduleEdit('ecommerce')
        <div class="modal fade" id="expenseModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.expenses.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Log Ecommerce Expense</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description / Note</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="What was this expense for?"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" id="expenseCategorySelect" class="form-select">
                                    <option value="" selected>Select a category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                    <option value="__add_new__">+ Add New Category</option>
                                </select>
                                <input type="text" id="expenseCategoryCustomInput" class="form-control mt-2" placeholder="Type the new category name" style="display: none;">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Paid Using</label>
                                <div class="d-flex gap-3">
                                    @foreach ($fundingSources as $value => $label)
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="funding_source" id="funding_{{ $value }}" value="{{ $value }}" {{ $value === 'partner_ledger' ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="funding_{{ $value }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Amount (PKR)</label>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Vendor</label>
                                <input type="text" name="vendor" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="expense_date" class="form-control" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Receipt (optional)</label>
                                <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            </div>

                            <hr style="border-color: var(--ec-border);">

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="expenseRestockCheckbox" name="includes_raw_material_restock" value="1">
                                <label class="form-check-label" for="expenseRestockCheckbox">This expense includes a raw material restock</label>
                            </div>
                            <div id="expenseRestockFields" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Raw Material Category</label>
                                    <select name="raw_material_category" id="expenseRestockCategorySelect" class="form-select">
                                        <option value="" disabled selected>Select a raw material category</option>
                                        @foreach ($rawMaterialCategories as $rawMaterialCategory)
                                            <option value="{{ $rawMaterialCategory }}">{{ $rawMaterialCategory }}</option>
                                        @endforeach
                                        <option value="__add_new__">+ Add New Raw Material</option>
                                    </select>
                                    <input type="text" id="expenseRestockCategoryCustomInput" class="form-control mt-2" placeholder="Type the new raw material name" style="display: none;">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Quantity Received</label>
                                    <input type="number" step="0.01" min="0.01" name="quantity_received" id="expenseRestockQuantityInput" class="form-control" placeholder="e.g. 10">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const expenseCategorySelect = document.getElementById('expenseCategorySelect');
            const expenseCategoryCustomInput = document.getElementById('expenseCategoryCustomInput');

            expenseCategorySelect.addEventListener('change', function () {
                if (this.value === '__add_new__') {
                    expenseCategoryCustomInput.style.display = 'block';
                    expenseCategoryCustomInput.name = 'category';
                    expenseCategoryCustomInput.required = true;
                    expenseCategorySelect.removeAttribute('name');
                    expenseCategoryCustomInput.focus();
                } else {
                    expenseCategoryCustomInput.style.display = 'none';
                    expenseCategoryCustomInput.removeAttribute('name');
                    expenseCategoryCustomInput.required = false;
                    expenseCategoryCustomInput.value = '';
                    expenseCategorySelect.name = 'category';
                }
            });

            const expenseRestockCheckbox = document.getElementById('expenseRestockCheckbox');
            const expenseRestockFields = document.getElementById('expenseRestockFields');
            const expenseRestockCategorySelect = document.getElementById('expenseRestockCategorySelect');
            const expenseRestockCategoryCustomInput = document.getElementById('expenseRestockCategoryCustomInput');
            const expenseRestockQuantityInput = document.getElementById('expenseRestockQuantityInput');

            function resetExpenseRestockCategoryCustomInput() {
                expenseRestockCategoryCustomInput.style.display = 'none';
                expenseRestockCategoryCustomInput.removeAttribute('name');
                expenseRestockCategoryCustomInput.required = false;
                expenseRestockCategoryCustomInput.value = '';
                expenseRestockCategorySelect.name = 'raw_material_category';
            }

            expenseRestockCategorySelect.addEventListener('change', function () {
                if (this.value === '__add_new__') {
                    expenseRestockCategoryCustomInput.style.display = 'block';
                    expenseRestockCategoryCustomInput.name = 'raw_material_category';
                    expenseRestockCategoryCustomInput.required = true;
                    expenseRestockCategorySelect.removeAttribute('name');
                    expenseRestockCategoryCustomInput.focus();
                } else {
                    resetExpenseRestockCategoryCustomInput();
                }
            });

            expenseRestockCheckbox.addEventListener('change', function () {
                if (this.checked) {
                    expenseRestockFields.style.display = 'block';
                    expenseRestockCategorySelect.required = true;
                    expenseRestockQuantityInput.required = true;
                } else {
                    expenseRestockFields.style.display = 'none';
                    expenseRestockCategorySelect.required = false;
                    expenseRestockQuantityInput.required = false;
                    expenseRestockCategorySelect.value = '';
                    expenseRestockQuantityInput.value = '';
                    resetExpenseRestockCategoryCustomInput();
                }
            });

            document.getElementById('expenseModal').addEventListener('hidden.bs.modal', function () {
                this.querySelector('form').reset();
                expenseCategoryCustomInput.style.display = 'none';
                expenseCategoryCustomInput.removeAttribute('name');
                expenseCategoryCustomInput.required = false;
                expenseCategoryCustomInput.value = '';
                expenseCategorySelect.name = 'category';

                expenseRestockFields.style.display = 'none';
                expenseRestockCategorySelect.required = false;
                expenseRestockQuantityInput.required = false;
                resetExpenseRestockCategoryCustomInput();
            });
        </script>
    @endmoduleEdit
@endsection
