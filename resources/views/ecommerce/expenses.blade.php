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

    <div class="ec-card">
        <h6>Total Ecommerce Expenses</h6>
        <div class="ec-value">QAR {{ number_format($totalExpenses, 2) }}</div>
        <div class="ec-sub">{{ $expenses->count() }} entries, all time</div>
    </div>

    <div class="ec-card">
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Category</th>
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
                            <td>{{ $expense->title }}</td>
                            <td><span class="ec-badge" style="background: rgba(217,143,131,0.14); color: var(--ec-ink);">{{ $expense->category }}</span></td>
                            <td>{{ $expense->vendor ?? '—' }}</td>
                            <td>QAR {{ number_format($expense->amount, 2) }}</td>
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
                        <tr><td colspan="8" class="text-center text-muted">No ecommerce expenses logged yet</td></tr>
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
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Amount (QAR)</label>
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
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endmoduleEdit
@endsection
