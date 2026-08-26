@extends('layouts.app')
@section('title', 'Finance')

<style>
    :root {
        --fin-border: rgba(217, 143, 131,0.16);
        --fin-border-strong: rgba(217, 143, 131,0.3);
        --fin-muted: #c9a39a;
        --fin-ink: #e79a91;
        --fin-primary: #d98f83;
        --fin-success: #8ea88a;
        --fin-danger: #a8524a;
        --fin-warning: #c9a66b;
        --fin-info: #8aa6ab;
    }

    .fin-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 18px;
    }

    .fin-header h4 {
        margin-bottom: 2px;
    }

    .fin-header p {
        color: var(--fin-muted);
        margin-bottom: 0;
        font-size: 13.5px;
    }

    /* ---------------- FILTER BAR ---------------- */
    .fin-filter-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--fin-border);
        border-radius: 16px;
        padding: 14px 18px;
        margin-bottom: 20px;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
    }

    .fin-filter-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .fin-preset-group {
        display: inline-flex;
        background: rgba(217, 143, 131,0.08);
        border-radius: 9px;
        padding: 3px;
        flex-wrap: wrap;
    }

    .fin-preset-btn {
        border: none;
        background: transparent;
        padding: 0 12px;
        height: 36px;
        font-size: 12.5px;
        font-weight: 600;
        border-radius: 7px;
        color: #c9a39a;
        transition: all .15s ease;
        white-space: nowrap;
    }

    .fin-preset-btn.active {
        background: #241e1c;
        color: var(--fin-ink);
        box-shadow: 0 1px 3px rgba(16, 24, 40, .12);
    }

    .fin-filter-divider {
        width: 1px;
        align-self: stretch;
        min-height: 26px;
        background: var(--fin-border);
        margin: 0 2px;
    }

    .fin-date-input, .fin-branch-select {
        height: 36px;
        border: 1px solid var(--fin-border-strong);
        border-radius: 9px;
        padding: 0 10px;
        font-size: 13px;
        font-weight: 600;
        color: var(--fin-ink);
        background-color: #241e1c;
    }

    .fin-date-input:focus, .fin-branch-select:focus {
        outline: none;
        border-color: var(--fin-primary);
        box-shadow: 0 0 0 3px rgba(217, 143, 131, .15);
    }

    .fin-apply-btn {
        height: 36px;
        border-radius: 9px;
        font-weight: 700;
        font-size: 13px;
        padding: 0 16px;
    }

    /* ---------------- KPI CARDS ---------------- */
    .fin-kpi-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--fin-border);
        border-radius: 16px;
        padding: 18px 20px;
        height: 100%;
        transition: all .2s ease;
    }

    .fin-kpi-card:hover {
        box-shadow: 0 8px 24px rgba(16, 24, 40, .08);
        transform: translateY(-2px);
    }

    .fin-kpi-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
    }

    .fin-kpi-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--fin-muted);
    }

    .fin-kpi-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .fin-kpi-value {
        font-size: 26px;
        font-weight: 700;
        color: var(--fin-ink);
        letter-spacing: -.01em;
        line-height: 1.2;
    }

    .fin-kpi-sub {
        display: flex;
        gap: 14px;
        margin-top: 10px;
        font-size: 12.5px;
        color: var(--fin-muted);
    }

    .fin-kpi-sub b {
        color: var(--fin-ink);
        font-weight: 700;
    }

    /* ---------------- CHART CARDS ---------------- */
    .fin-chart-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--fin-border);
        border-radius: 16px;
        padding: 18px 20px;
        height: 100%;
    }

    .fin-chart-card h6 {
        font-weight: 700;
        margin-bottom: 2px;
    }

    .fin-chart-card .fin-chart-sub {
        font-size: 12.5px;
        color: var(--fin-muted);
        margin-bottom: 10px;
    }

    /* ---------------- BRANCH TABLE ---------------- */
    .fin-branch-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--fin-muted);
        font-weight: 700;
        border-top: none;
    }

    .fin-branch-table td {
        font-size: 13.5px;
        vertical-align: middle;
    }

    /* ---------------- SECTION CARDS / TABLES ---------------- */
    .fin-section-card {
        background: rgba(36, 30, 28, 0.6);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--fin-border);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .fin-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid var(--fin-border);
    }

    .fin-section-head h6 {
        margin-bottom: 0;
        font-weight: 700;
    }

    .fin-table {
        margin-bottom: 0;
    }

    .fin-table thead th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: var(--fin-muted);
        font-weight: 700;
        border-top: none;
        background: rgba(217, 143, 131,0.05);
        white-space: nowrap;
    }

    .fin-table tbody td {
        font-size: 13.5px;
        vertical-align: middle;
    }

    .fin-item-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11.5px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 999px;
        margin: 1px 2px 1px 0;
        white-space: nowrap;
    }

    .fin-item-chip.service {
        background: rgba(217, 143, 131, .1);
        color: var(--fin-primary);
    }

    .fin-item-chip.product {
        background: rgba(138,166,171, .1);
        color: #8aa6ab;
    }

    .fin-pay-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11.5px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 999px;
        margin: 1px 2px 1px 0;
        white-space: nowrap;
        background: rgba(217, 143, 131,0.08);
        color: #cbb8b0;
    }

    .fin-category-badge {
        display: inline-block;
        font-size: 11.5px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 999px;
        background: rgba(168,82,74, .08);
        color: #c9a66b;
    }
</style>

@section('content')

    <div class="fin-header">
        <div>
            <h4>Finance</h4>
            <p>Live profit &amp; loss, revenue trends and expense tracking across your branches.</p>
            @if ($branch || $staffId)
                <div class="mt-1">
                    @if ($branch)
                        <span class="fin-pay-chip">Branch: {{ $branches[$branch] }}</span>
                    @endif
                    @if ($staffId)
                        <span class="fin-pay-chip">Staff: {{ $staffList->firstWhere('id', $staffId)?->name }}</span>
                    @endif
                </div>
            @endif
        </div>
        <button type="button" class="btn btn-primary fin-apply-btn" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="bx bx-plus me-1"></i> Add Expense
        </button>
    </div>

    <!-- FILTER BAR -->
    <div class="fin-filter-card">
        <form method="GET" action="{{ route('appointments.revenue.index') }}" id="finFilterForm">
            <div class="fin-filter-row">
                <div class="fin-preset-group" id="finPresetGroup">
                    <button type="button" class="fin-preset-btn" data-preset="today">Today</button>
                    <button type="button" class="fin-preset-btn" data-preset="yesterday">Yesterday</button>
                    <button type="button" class="fin-preset-btn" data-preset="this_week">This Week</button>
                    <button type="button" class="fin-preset-btn" data-preset="this_month">This Month</button>
                    <button type="button" class="fin-preset-btn" data-preset="last_month">Last Month</button>
                </div>

                <div class="fin-filter-divider"></div>

                <input type="date" name="from" id="finFrom" class="fin-date-input" value="{{ $from->format('Y-m-d') }}">
                <span class="text-muted small">to</span>
                <input type="date" name="to" id="finTo" class="fin-date-input" value="{{ $to->format('Y-m-d') }}">

                <div class="fin-filter-divider"></div>

                <select name="branch" class="fin-branch-select">
                    <option value="">All Branches</option>
                    @foreach ($branches as $key => $label)
                        <option value="{{ $key }}" {{ $branch === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="staff_id" class="fin-branch-select">
                    <option value="">All Staff</option>
                    @foreach ($staffList as $staffMember)
                        <option value="{{ $staffMember->id }}" {{ $staffId === $staffMember->id ? 'selected' : '' }}>{{ $staffMember->name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary fin-apply-btn">Apply</button>
                @if ($branch || $staffId || request()->hasAny(['from', 'to']))
                    <a href="{{ route('appointments.revenue.index') }}" class="btn btn-outline-secondary fin-apply-btn">Reset</a>
                @endif
            </div>
        </form>
    </div>

    <!-- KPI CARDS -->
    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3">
            <div class="fin-kpi-card">
                <div class="fin-kpi-top">
                    <span class="fin-kpi-label">Gross Sales</span>
                    <span class="fin-kpi-icon" style="background:rgba(217, 143, 131,.1); color:var(--fin-primary);">
                        <i class="bx bx-trending-up"></i>
                    </span>
                </div>
                <div class="fin-kpi-value">{{ number_format($grossSales, 2) }} <small class="fs-6 fw-semibold text-muted">QAR</small></div>
                <div class="fin-kpi-sub">
                    <span>Services <b>{{ number_format($grossServices, 2) }}</b></span>
                    <span>Products <b>{{ number_format($grossProducts, 2) }}</b></span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="fin-kpi-card">
                <div class="fin-kpi-top">
                    <span class="fin-kpi-label">Total Expenses</span>
                    <span class="fin-kpi-icon" style="background:rgba(168,82,74,.1); color:var(--fin-danger);">
                        <i class="bx bx-receipt"></i>
                    </span>
                </div>
                <div class="fin-kpi-value">{{ number_format($totalExpenses, 2) }} <small class="fs-6 fw-semibold text-muted">QAR</small></div>
                <div class="fin-kpi-sub">
                    <span>{{ $expenses->count() }} recorded {{ Str::plural('expense', $expenses->count()) }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="fin-kpi-card">
                <div class="fin-kpi-top">
                    <span class="fin-kpi-label">Net Profit</span>
                    <span class="fin-kpi-icon" style="background:rgba(142,168,138,.12); color:#7fa876;">
                        <i class="bx bx-line-chart"></i>
                    </span>
                </div>
                <div class="fin-kpi-value" style="color: {{ $netProfit >= 0 ? '#7fa876' : 'var(--fin-danger)' }};">
                    {{ number_format($netProfit, 2) }} <small class="fs-6 fw-semibold text-muted">QAR</small>
                </div>
                <div class="fin-kpi-sub">
                    <span>Gross Sales &minus; Expenses</span>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="fin-kpi-card">
                <div class="fin-kpi-top">
                    <span class="fin-kpi-label">Profit Margin</span>
                    <span class="fin-kpi-icon" style="background:rgba(138,166,171,.1); color:#8aa6ab;">
                        <i class="bx bx-pie-chart-alt-2"></i>
                    </span>
                </div>
                <div class="fin-kpi-value">{{ number_format($profitMargin, 1) }}%</div>
                <div class="fin-kpi-sub">
                    <span>Discounts given <b>{{ number_format($totalDiscounts, 2) }}</b></span>
                    <span>Tips <b>{{ number_format($totalTips, 2) }}</b></span>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 1 -->
    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="fin-chart-card">
                <h6>Revenue vs Expense Trend</h6>
                <div class="fin-chart-sub">{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</div>
                <div id="finTrendChart"></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="fin-chart-card">
                <h6>Revenue Breakdown</h6>
                <div class="fin-chart-sub">Services vs. Retail Products</div>
                <div id="finDonutChart"></div>
            </div>
        </div>
    </div>

    <!-- CHARTS ROW 2: BRANCH COMPARISON -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="fin-chart-card">
                <h6>Branch Performance</h6>
                <div class="fin-chart-sub">Gross sales vs. expenses by branch &middot; {{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</div>
                <div class="row g-4 align-items-center">
                    <div class="col-lg-8">
                        <div id="finBranchChart"></div>
                    </div>
                    <div class="col-lg-4">
                        <div class="table-responsive">
                            <table class="table fin-branch-table">
                                <thead>
                                    <tr>
                                        <th>Branch</th>
                                        <th class="text-end">Sales</th>
                                        <th class="text-end">Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($branchBreakdown as $row)
                                        <tr>
                                            <td class="fw-semibold">{{ $row['label'] }}</td>
                                            <td class="text-end">{{ number_format($row['sales'], 0) }}</td>
                                            <td class="text-end fw-semibold" style="color: {{ $row['profit'] >= 0 ? '#7fa876' : 'var(--fin-danger)' }};">
                                                {{ number_format($row['profit'], 0) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DAILY SALES LEDGER -->
    <div class="fin-section-card">
        <div class="fin-section-head">
            <h6><i class="bx bx-list-ul me-1"></i> Daily Sales Ledger</h6>
            <span class="text-muted small">{{ $sales->count() }} {{ Str::plural('transaction', $sales->count()) }}</span>
        </div>
        <div class="table-responsive">
            <table class="table fin-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Client</th>
                        <th>Staff</th>
                        <th>Branch</th>
                        <th>Items Sold</th>
                        <th>Payment</th>
                        <th class="text-end">Total (QAR)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="text-nowrap">{{ $sale->created_at->format('d M, h:i A') }}</td>
                            <td>{{ $sale->customer->name ?? $sale->appointment?->customer_name ?? 'Walk-in' }}</td>
                            <td>{{ $sale->staff->name ?? '—' }}</td>
                            <td>{{ $branches[$sale->branch] ?? ucwords(str_replace('_', ' ', $sale->branch ?? '')) }}</td>
                            <td>
                                @foreach ($sale->items as $item)
                                    <span class="fin-item-chip {{ $item->type }}">
                                        <i class="bx {{ $item->type === 'service' ? 'bx-cut' : 'bx-package' }}"></i>
                                        {{ $item->name }}{{ $item->type === 'product' && $item->quantity > 1 ? ' × ' . $item->quantity : '' }}
                                    </span>
                                @endforeach
                            </td>
                            <td>
                                @foreach ($sale->payments as $payment)
                                    <span class="fin-pay-chip">
                                        <i class="bx {{ $payment->method === 'cash' ? 'bx-money' : ($payment->method === 'card' ? 'bx-credit-card' : 'bx-transfer') }}"></i>
                                        {{ ucfirst(str_replace('_', ' ', $payment->method)) }}: {{ number_format($payment->amount, 2) }}
                                    </span>
                                @endforeach
                            </td>
                            <td class="text-end fw-semibold">{{ number_format($sale->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No sales recorded for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- EXPENSE TRACKER -->
    <div class="fin-section-card">
        <div class="fin-section-head">
            <h6><i class="bx bx-wallet me-1"></i> Expense Tracker</h6>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="bx bx-plus"></i> Add Expense
            </button>
        </div>
        <div class="px-3 py-3 border-bottom d-flex flex-wrap align-items-center gap-2" style="background:rgba(217, 143, 131,0.05);">
            <form method="GET" action="{{ route('appointments.revenue.index') }}" class="d-flex flex-wrap align-items-center gap-2">
                <input type="hidden" name="staff_id" value="{{ $staffId }}">
                <span class="text-muted small fw-semibold">Check total expenses between</span>
                <input type="date" name="from" class="fin-date-input" style="height:32px;" value="{{ $from->format('Y-m-d') }}">
                <span class="text-muted small">and</span>
                <input type="date" name="to" class="fin-date-input" style="height:32px;" value="{{ $to->format('Y-m-d') }}">
                <span class="text-muted small">for</span>
                <select name="branch" class="fin-branch-select" style="height:32px;">
                    <option value="">All Branches</option>
                    @foreach ($branches as $key => $label)
                        <option value="{{ $key }}" {{ $branch === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            </form>
            <span class="ms-auto fw-bold">
                Total for {{ $from->format('d M') }} &ndash; {{ $to->format('d M Y') }}: {{ number_format($totalExpenses, 2) }} QAR
            </span>
        </div>
        <div class="table-responsive">
            <table class="table fin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Added By</th>
                        <th class="text-end">Amount (QAR)</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td class="text-nowrap">{{ $expense->expense_date->format('d M Y') }}</td>
                            <td>{{ $expense->branch ? ($branches[$expense->branch] ?? $expense->branch) : 'General' }}</td>
                            <td><span class="fin-category-badge">{{ $expense->category }}</span></td>
                            <td>{{ $expense->description ?? '—' }}</td>
                            <td>{{ $expense->creator->name ?? '—' }}</td>
                            <td class="text-end fw-semibold">{{ number_format($expense->amount, 2) }}</td>
                            <td class="text-end">
                                <form action="{{ route('expenses.destroy', $expense->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this expense?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No expenses recorded for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($expenses->count())
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-end fw-semibold">Total Expenses</td>
                            <td class="text-end fw-bold">{{ number_format($totalExpenses, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <!-- ADD EXPENSE MODAL -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('expenses.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Date</label>
                                <input type="date" name="expense_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Branch</label>
                                <select name="branch" class="form-select">
                                    <option value="">General (all branches)</option>
                                    @foreach ($branches as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" required>
                                    @foreach ($expenseCategories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Amount (QAR)</label>
                                <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description <span class="text-muted">(optional)</span></label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
    <script>
        function toLocalISODate(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        document.querySelectorAll('.fin-preset-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const today = new Date();
                let from = new Date(today);
                let to = new Date(today);

                switch (this.dataset.preset) {
                    case 'today':
                        break;
                    case 'yesterday':
                        from.setDate(from.getDate() - 1);
                        to.setDate(to.getDate() - 1);
                        break;
                    case 'this_week':
                        from.setDate(from.getDate() - from.getDay());
                        break;
                    case 'this_month':
                        from = new Date(today.getFullYear(), today.getMonth(), 1);
                        break;
                    case 'last_month':
                        from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        to = new Date(today.getFullYear(), today.getMonth(), 0);
                        break;
                }

                document.getElementById('finFrom').value = toLocalISODate(from);
                document.getElementById('finTo').value = toLocalISODate(to);
                document.getElementById('finFilterForm').submit();
            });
        });

        // Revenue vs Expense trend
        new ApexCharts(document.querySelector('#finTrendChart'), {
            chart: { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [
                { name: 'Revenue', data: @json($trendRevenue) },
                { name: 'Expenses', data: @json($trendExpenses) },
            ],
            xaxis: { categories: @json($trendLabels), labels: { rotate: -45, style: { fontSize: '11px' } } },
            yaxis: { labels: { formatter: (v) => v.toFixed(0) } },
            colors: ['#d98f83', '#a8524a'],
            fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .05 } },
            stroke: { curve: 'smooth', width: 2.5 },
            dataLabels: { enabled: false },
            legend: { position: 'top', horizontalAlign: 'left' },
            tooltip: { y: { formatter: (v) => v.toFixed(2) + ' QAR' } },
            grid: { borderColor: 'rgba(217, 143, 131,0.16)' },
        }).render();

        // Revenue breakdown donut
        new ApexCharts(document.querySelector('#finDonutChart'), {
            chart: { type: 'donut', height: 320, fontFamily: 'inherit' },
            series: [{{ $grossServices }}, {{ $grossProducts }}],
            labels: ['Services', 'Products'],
            colors: ['#d98f83', '#8aa6ab'],
            dataLabels: { enabled: true, formatter: (val) => val.toFixed(1) + '%' },
            legend: { position: 'bottom' },
            tooltip: { y: { formatter: (v) => v.toFixed(2) + ' QAR' } },
            plotOptions: {
                pie: {
                    donut: {
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Gross Sales',
                                formatter: () => '{{ number_format($grossSales, 2) }}',
                            }
                        }
                    }
                }
            },
        }).render();

        // Branch comparison
        new ApexCharts(document.querySelector('#finBranchChart'), {
            chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [
                { name: 'Gross Sales', data: @json(array_column($branchBreakdown, 'sales')) },
                { name: 'Expenses', data: @json(array_column($branchBreakdown, 'expenses')) },
            ],
            xaxis: { categories: @json(array_column($branchBreakdown, 'label')) },
            colors: ['#d98f83', '#a8524a'],
            plotOptions: { bar: { columnWidth: '45%', borderRadius: 6 } },
            dataLabels: { enabled: false },
            legend: { position: 'top', horizontalAlign: 'left' },
            tooltip: { y: { formatter: (v) => v.toFixed(2) + ' QAR' } },
            grid: { borderColor: 'rgba(217, 143, 131,0.16)' },
        }).render();
    </script>
@endsection
