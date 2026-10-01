@extends('layouts.app')
@section('title', 'Partner Ledger')

@include('ecommerce._styles')

@section('content')
    <div class="ec-header">
        <div>
            <h4>Partner Investment &amp; Capital Ledger</h4>
            <p>Capital matching across the partners, plus the full capital injection history.</p>
        </div>
        @moduleEdit('ecommerce')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartnerModal">+ Add Partner</button>
        @endmoduleEdit
    </div>

    @include('ecommerce._nav')

    <div class="row">
        <div class="col-md-6">
            <div class="ec-card">
                <h6>Total Capital Pool</h6>
                <div class="ec-value">PKR {{ number_format($totalPool, 2) }}</div>
                <div class="ec-sub">Total cash injected across all partners</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="ec-card">
                <h6>Available Balance (Runway)</h6>
                <div class="ec-value {{ $remainingBalance >= 0 ? 'ec-positive' : 'ec-negative' }}">PKR {{ number_format($remainingBalance, 2) }}</div>
                <div class="ec-sub">Capital Pool &minus; Total Ecommerce Expenses</div>
            </div>
        </div>
    </div>

    <div class="row">
        @foreach ($equalization as $row)
            <div class="col-md-4">
                <div class="ec-card">
                    <h6>{{ $row['partner']->name }}</h6>
                    <div class="ec-value">PKR {{ number_format($row['contributed'], 2) }}</div>
                    <div class="ec-sub">{{ number_format($row['percent'], 1) }}% of total pool</div>
                    <hr style="border-color: var(--ec-border); margin: 10px 0;">
                    @if ($row['balance'] <= 0.01)
                        <div class="ec-sub ec-positive">Fully Matched</div>
                    @else
                        <div class="ec-sub ec-negative">Owes PKR {{ number_format($row['balance'], 2) }} to match the top investor</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="ec-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Transaction History</h6>
            @moduleEdit('ecommerce')
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">+ Add Transaction</button>
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
                        @moduleEdit('ecommerce')<th></th>@endmoduleEdit
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->transaction_date->format('d M Y') }}</td>
                            <td>{{ $transaction->partner->name }}</td>
                            <td><span class="ec-badge ec-badge-{{ $transaction->type }}">{{ ucfirst($transaction->type) }}</span></td>
                            <td>{{ $transaction->category ?? '—' }}</td>
                            <td>PKR {{ number_format($transaction->amount, 2) }}</td>
                            <td>{{ $transaction->reference_note ?? '—' }}</td>
                            @moduleEdit('ecommerce')
                                <td>
                                    <form action="{{ route('ecommerce.transactions.destroy', $transaction->id) }}" method="POST" onsubmit="return confirm('Delete this transaction?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
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
        <div class="modal fade" id="addPartnerModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.partners.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Add Partner</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Equity %</label>
                                <input type="number" step="0.0001" name="equity_percentage" class="form-control" value="33.3333">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Add Transaction Modal --}}
        <div class="modal fade" id="addTransactionModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="" method="POST" id="transactionForm">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Add Transaction</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Partner</label>
                                <select name="_partner" id="transactionPartnerSelect" class="form-select" required onchange="document.getElementById('transactionForm').action = this.value">
                                    <option value="" disabled selected>Select partner</option>
                                    @foreach ($partners as $partner)
                                        <option value="{{ route('ecommerce.partners.transactions.store', $partner->id) }}">{{ $partner->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <input type="text" class="form-control" value="Injection (money in)" disabled>
                                <input type="hidden" name="type" value="injection">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Amount (PKR)</label>
                                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    @foreach ($transactionCategories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reference Note</label>
                                <input type="text" name="reference_note" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="transaction_date" class="form-control" value="{{ now()->toDateString() }}" required>
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
