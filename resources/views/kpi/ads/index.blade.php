@extends('layouts.app')
@section('title', 'Ads Conversion Reports')

@section('content')
    <div class="d-flex gap-2 mb-4">
        <button type="button" class="ads-tab-btn btn btn-sm {{ $activeTab === 'leads' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="leads">Ad Leads Log</button>
        <button type="button" class="ads-tab-btn btn btn-sm {{ $activeTab === 'analytics' ? 'btn-dark' : 'btn-outline-secondary' }}" data-tab="analytics">Ads Analytics</button>
    </div>

    {{-- ================= AD LEADS LOG ================= --}}
    <div id="ads-pane-leads" class="ads-tab-pane {{ $activeTab === 'leads' ? '' : 'd-none' }}">
        <div class="kpi-header">
            <div>
                <h4>Ad Leads Data Entry</h4>
                <p>Log every incoming ad lead here — it automatically feeds the Ads Conversion Report and Analytics for any matching date range.</p>
            </div>
            @moduleEdit('kpis')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adLeadEntryModal" onclick="resetAdLeadEntryForm()">
                    <i class="bx bx-plus me-1"></i> Add Entry
                </button>
            @endmoduleEdit
        </div>

        <div class="kpi-panel mb-3">
            <form method="GET" action="{{ route('kpi.ads.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="leads">
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="entries_from" class="form-control" value="{{ $entriesFrom?->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="entries_to" class="form-control" value="{{ $entriesTo?->format('Y-m-d') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if ($entriesFrom || $entriesTo)
                        <a href="{{ route('kpi.ads.index', ['tab' => 'leads']) }}" class="btn btn-outline-secondary">Reset</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="kpi-panel p-0 mb-2" style="overflow:hidden;">
            <div class="table-responsive">
                <table class="table kpi-table mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Contact</th>
                            <th>Ad Type</th>
                            <th class="text-end">Ticket (QAR)</th>
                            <th>Branch</th>
                            <th>Remarks</th>
                            <th></th>
                        </tr>
                    </thead>
                    @forelse ($adLeadEntries as $entry)
                        @php
                            [$entryCountryCode, $entryPhoneNumber] = \App\Models\Lead::splitPhone($entry->phone);
                            $entryCustomerName = $entry->customer_name ?: ($entry->customer->name ?? null);
                            $entryPayload = [
                                'id' => $entry->id,
                                'date' => $entry->date?->format('Y-m-d'),
                                'phone' => $entry->phone,
                                'country_code' => $entryCountryCode,
                                'phone_number' => $entryPhoneNumber,
                                'customer_id' => $entry->customer_id,
                                'customer_name' => $entryCustomerName,
                                'category' => $entry->category,
                                'ticket_amount' => $entry->ticket_amount,
                                'branch' => $entry->branch,
                                'remarks' => $entry->remarks,
                            ];
                        @endphp
                        <tbody x-data="{
                            showAiPanel: false,
                            isLoading: false,
                            script: '',
                            copied: false,
                            errorMsg: '',
                            generateScript() {
                                this.showAiPanel = true;
                                this.isLoading = true;
                                this.errorMsg = '';
                                this.copied = false;
                                fetch('{{ route('kpi.ad-leads.ai-script', $entry) }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({
                                        lead_id: {{ $entry->id }},
                                        name: @js($entryCustomerName ?? ''),
                                        customer_name: @js($entryCustomerName ?? ''),
                                        category: @js($entry->category ?? ''),
                                        remarks: @js($entry->remarks ?? '')
                                    })
                                })
                                .then(r => r.json())
                                .then(data => {
                                    if (data && data.script) {
                                        this.script = data.script;
                                    } else {
                                        this.errorMsg = 'Could not generate script. Please try again.';
                                    }
                                    this.isLoading = false;
                                })
                                .catch(() => {
                                    this.errorMsg = 'Network error while generating AI script.';
                                    this.isLoading = false;
                                })
                                .finally(() => {
                                    this.isLoading = false;
                                });
                            },
                            copyToWhatsApp() {
                                if (!this.script) return;
                                navigator.clipboard.writeText(this.script).then(() => {
                                    this.copied = true;
                                    setTimeout(() => { this.copied = false; }, 2500);
                                });
                            }
                        }" style="border-top: none;">
                            <tr @if (!$entry->isBooked()) @click="showAiPanel = !showAiPanel" style="cursor: pointer;" @endif>
                                <td>
                                    @if (!$entry->isBooked())
                                        <i class="bx me-1 text-muted" :class="showAiPanel ? 'bx-chevron-down' : 'bx-chevron-right'"></i>
                                    @endif
                                    {{ $entry->date->format('d M Y') }}
                                </td>
                                <td>
                                    @if ($entryCustomerName)
                                        <div class="fw-semibold">
                                            @if ($entry->customer_id)
                                                <a href="{{ route('customers.show', $entry->customer_id) }}" target="_blank" class="text-decoration-none text-reset" @click.stop>
                                                    {{ $entryCustomerName }}
                                                </a>
                                            @else
                                                {{ $entryCustomerName }}
                                            @endif
                                        </div>
                                        <div class="text-muted small">{{ $entry->phone }}</div>
                                    @else
                                        <div>{{ $entry->phone }}</div>
                                    @endif
                                </td>
                                <td>{{ $entry->category }}</td>
                                <td class="text-end">{{ $entry->ticket_amount > 0 ? number_format($entry->ticket_amount, 2) : '—' }}</td>
                                <td>
                                    @if ($entry->branch)
                                        <span class="kpi-badge kpi-badge-green">{{ $branches[$entry->branch] }}</span>
                                    @else
                                        <span class="text-muted small">Unbooked</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $entry->remarks ?? '—' }}</td>
                                <td class="text-end" @click.stop>
                                    <div class="d-flex gap-1 justify-content-end align-items-center">
                                        @if (!$entry->isBooked() && empty($entry->branch))
                                            <button type="button"
                                                class="btn btn-sm btn-primary"
                                                @click="generateScript()"
                                                :disabled="isLoading"
                                                title="Generate AI Closing Script">
                                                <span x-show="!isLoading">🧠 Generate AI Closing Script</span>
                                                <span x-show="isLoading" x-cloak>
                                                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                                    Generating...
                                                </span>
                                            </button>
                                        @endif
                                        @moduleEdit('kpis')
                                            <button type="button" class="btn btn-sm btn-outline-warning ad-lead-edit-btn"
                                                data-entry='@json($entryPayload)'
                                                title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form action="{{ route('kpi.ad-leads.destroy', $entry) }}" method="POST" onsubmit="return confirm('Delete this ad lead entry?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endmoduleEdit
                                    </div>
                                </td>
                            </tr>
                            @if (!$entry->isBooked() && empty($entry->branch))
                                <tr x-show="showAiPanel" x-cloak class="bg-dark bg-opacity-10">
                                    <td colspan="7" class="p-3">
                                        <div class="d-flex flex-column gap-2 p-3 rounded border border-secondary border-opacity-25" style="background: rgba(20, 16, 14, 0.45);">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                <div class="small fw-bold text-uppercase text-muted" style="letter-spacing: 0.04em;">
                                                    🧠 Sales Consultant &mdash; {{ $entry->category }} ({{ max(0, (int) floor(($entry->created_at ?? $entry->date ?? now())->diffInDays(now()))) }}d old)
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <button type="button"
                                                        class="btn btn-sm btn-primary"
                                                        @click="generateScript()"
                                                        :disabled="isLoading">
                                                        <span x-show="!isLoading">🧠 Generate AI Closing Script</span>
                                                        <span x-show="isLoading" x-cloak>
                                                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                                                            Thinking...
                                                        </span>
                                                    </button>
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1"
                                                        x-on:click="showAiPanel = false"
                                                        title="Close AI panel">
                                                        <i class="bi bi-x-lg"></i>
                                                        <span>Close</span>
                                                    </button>
                                                </div>
                                            </div>

                                            <div x-show="isLoading" x-cloak class="py-2 text-muted small">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                                                    <span>Crafting high-converting 2-sentence WhatsApp closing script...</span>
                                                </div>
                                            </div>

                                            <div x-show="errorMsg && !isLoading" x-cloak class="text-danger small" x-text="errorMsg"></div>

                                            <div x-show="script && !isLoading" x-cloak>
                                                <div class="p-3 rounded mb-2 border border-secondary border-opacity-25" style="background: rgba(255, 255, 255, 0.04); border-left: 3px solid var(--bs-primary, #c98a7b) !important; font-size: 0.92rem; line-height: 1.5;" x-text="script"></div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <button type="button" class="btn btn-sm btn-success fw-semibold" @click="copyToWhatsApp()">
                                                        <i class="bx bxl-whatsapp me-1"></i>
                                                        <span x-text="copied ? 'Copied to Clipboard!' : 'Copy to WhatsApp'"></span>
                                                    </button>
                                                    <a :href="'https://wa.me/{{ preg_replace('/[^0-9]/', '', $entry->phone) }}?text=' + encodeURIComponent(script)"
                                                       target="_blank"
                                                       rel="noopener"
                                                       class="btn btn-sm btn-outline-success">
                                                        <i class="bx bx-link-external me-1"></i> Open WhatsApp Chat
                                                    </a>
                                                </div>
                                            </div>

                                            <div x-show="!script && !isLoading && !errorMsg" x-cloak class="text-muted small">
                                                Click <strong>🧠 Generate AI Closing Script</strong> to create a 2-sentence scarcity + risk-reversal WhatsApp close tailored to this lead.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    @empty
                        <tbody>
                            <tr><td colspan="7" class="text-center text-muted py-4">No ad leads logged{{ ($entriesFrom || $entriesTo) ? ' for this period' : ' yet' }}.</td></tr>
                        </tbody>
                    @endforelse
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end align-items-center mt-2 mb-0">{{ $adLeadEntries->links() }}</div>

        @moduleEdit('kpis')
            @include('kpi.ads._lead-entry-modal', ['uid' => 'new', 'entry' => null])
        @endmoduleEdit
    </div>

    {{-- ================= ADS ANALYTICS ================= --}}
    <div id="ads-pane-analytics" class="ads-tab-pane {{ $activeTab === 'analytics' ? '' : 'd-none' }}">
        @php
            $analyticsCategories = $analyticsReport->computedCategories();
            $analyticsTotals = $analyticsReport->totals();
            $branchComparison = $analyticsReport->branchComparison();
            $insights = $analyticsReport->actionableInsights();
        @endphp

        <div class="kpi-header">
            <div>
                <h4>Ads Analytics</h4>
                <p>Live performance breakdown computed straight from the Ad Leads Log — nothing needs to be saved first.</p>
            </div>
            <span class="kpi-badge {{ $analyticsTotals['overall_met_target'] ? 'kpi-badge-green' : 'kpi-badge-red' }}">
                Overall Conversion: {{ $analyticsTotals['overall_conversion'] }}% {{ $analyticsTotals['overall_met_target'] ? '(Target met)' : '(Below 20% target)' }}
            </span>
        </div>

        <div class="kpi-panel mb-3">
            <form method="GET" action="{{ route('kpi.ads.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="analytics">
                <div class="col-md-3">
                    <label class="form-label">From</label>
                    <input type="date" name="analytics_from" class="form-control" value="{{ $analyticsFrom->format('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To</label>
                    <input type="date" name="analytics_to" class="form-control" value="{{ $analyticsTo->format('Y-m-d') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('kpi.ads.index', ['tab' => 'analytics']) }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Overall Conversion</div>
                    <div class="kpi-stat-value">{{ $analyticsTotals['overall_conversion'] }}%</div>
                    <div class="kpi-stat-sub">Target: 20%</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Total Leads</div>
                    <div class="kpi-stat-value">{{ $analyticsTotals['total_leads'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Total Bookings</div>
                    <div class="kpi-stat-value">{{ $analyticsTotals['total_bookings'] }}</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="kpi-stat-card">
                    <div class="kpi-stat-label">Total Revenue</div>
                    <div class="kpi-stat-value">{{ number_format($analyticsTotals['total_revenue'], 0) }} <small class="fs-6">QAR</small></div>
                </div>
            </div>
        </div>

        <div class="kpi-panel mb-3">
            <h6>Category Conversion Breakdown</h6>
            <div class="table-responsive">
                <table class="table kpi-table align-middle">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Leads</th>
                            <th>Bookings</th>
                            <th style="min-width:140px;">Conversion</th>
                            <th>Avg Ticket</th>
                            <th>Revenue</th>
                            <th>vs 20% Target</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($analyticsCategories as $c)
                            @php
                                $barColor = match($c['status']) { 'Above' => 'green', 'Near' => 'amber', 'Below' => 'amber', default => 'red' };
                                $badge = $analyticsReport->categoryBadge($c);
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $c['name'] }}</td>
                                <td>{{ $c['leads'] }}</td>
                                <td>{{ $c['bookings'] }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="kpi-progress-track" style="width:80px;">
                                            <div class="kpi-progress-fill {{ $barColor }}" style="width:{{ min($c['conversion'] / 20 * 100, 100) }}%"></div>
                                        </div>
                                        <span class="small fw-semibold">{{ $c['conversion'] }}%</span>
                                    </div>
                                </td>
                                <td>{{ number_format($c['avg_ticket'], 2) }}</td>
                                <td>{{ number_format($c['revenue'], 2) }}</td>
                                <td>{{ $c['pct_of_target'] }}%</td>
                                <td><span class="kpi-badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No ad leads logged for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="kpi-panel mb-3">
            <h6>Branch Performance Comparison</h6>
            <p class="text-muted small mb-3">Old Airport vs. Al Wakrah — bookings, revenue, and the gap between them.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="kpi-stat-card h-100 {{ $branchComparison['leading_branch'] === 'Old Airport' ? 'border border-success' : '' }}">
                        <div class="kpi-stat-label">Old Airport {{ $branchComparison['leading_branch'] === 'Old Airport' ? '👑' : '' }}</div>
                        <div class="kpi-stat-value">{{ $branchComparison['old_airport']['bookings'] }} <small class="fs-6">bookings</small></div>
                        <div class="kpi-stat-sub">
                            {{ number_format($branchComparison['old_airport']['revenue'], 2) }} QAR
                            &middot; {{ $branchComparison['old_airport']['booking_share'] }}% of bookings
                            &middot; {{ $branchComparison['old_airport']['revenue_share'] }}% of revenue
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="kpi-stat-card h-100 {{ $branchComparison['leading_branch'] === 'Al Wakrah' ? 'border border-success' : '' }}">
                        <div class="kpi-stat-label">Al Wakrah {{ $branchComparison['leading_branch'] === 'Al Wakrah' ? '👑' : '' }}</div>
                        <div class="kpi-stat-value">{{ $branchComparison['wakrah']['bookings'] }} <small class="fs-6">bookings</small></div>
                        <div class="kpi-stat-sub">
                            {{ number_format($branchComparison['wakrah']['revenue'], 2) }} QAR
                            &middot; {{ $branchComparison['wakrah']['booking_share'] }}% of bookings
                            &middot; {{ $branchComparison['wakrah']['revenue_share'] }}% of revenue
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-3 small text-muted">
                @if ($branchComparison['total_bookings'] === 0)
                    No bookings logged for either branch in this period.
                @elseif ($branchComparison['leading_branch'])
                    <strong>{{ $branchComparison['leading_branch'] }}</strong> is ahead by
                    {{ $branchComparison['booking_gap'] }} {{ Str::plural('booking', $branchComparison['booking_gap']) }}
                    and {{ number_format($branchComparison['revenue_gap'], 2) }} QAR in revenue.
                @else
                    Both branches are performing evenly.
                @endif
            </div>
        </div>

        <div class="kpi-panel mb-4">
            <h6>Actionable Insights</h6>
            <p class="text-muted small mb-3">Rule-based recommendations — which campaigns to scale, fix, or pause — based on conversion vs. the 20% target.</p>
            @forelse ($insights as $insight)
                @php
                    $actionBadge = match ($insight['action']) {
                        'scale' => ['label' => 'Scale', 'class' => 'kpi-badge-green'],
                        'fix' => ['label' => 'Fix', 'class' => 'kpi-badge-amber'],
                        default => ['label' => 'Pause', 'class' => 'kpi-badge-red'],
                    };
                @endphp
                <div class="d-flex align-items-start gap-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <span class="kpi-badge {{ $actionBadge['class'] }}" style="min-width:64px; text-align:center; flex-shrink:0;">{{ $actionBadge['label'] }}</span>
                    <div>
                        <span class="fw-semibold">{{ $insight['name'] }}</span>
                        @if ($insight['low_sample'])
                            <span class="text-muted small">(small sample — keep monitoring)</span>
                        @endif
                        <div class="text-muted small">{{ $insight['reason'] }}</div>
                    </div>
                </div>
            @empty
                <p class="text-muted small mb-0">No ad leads logged for this period.</p>
            @endforelse
        </div>

        <div class="kpi-header mt-4">
            <div>
                <h4>Ads Conversion Reports</h4>
                <p>Pick a date range and generate a saved snapshot — leads, bookings, conversion %, revenue, and branch split are calculated automatically from the Ad Leads Log.</p>
            </div>
        </div>

        @moduleEdit('kpis')
            <div class="kpi-panel mb-3">
                <form method="POST" action="{{ route('kpi.ads.store') }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" name="date_from" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" name="date_to" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="bx bx-plus me-1"></i> Generate Report</button>
                    </div>
                </form>
            </div>
        @endmoduleEdit

        <div class="kpi-panel p-0" style="overflow:hidden;">
            <div class="table-responsive">
                <table class="table kpi-table mb-0">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Categories</th>
                            <th class="text-end">Total Leads</th>
                            <th class="text-end">Total Bookings</th>
                            <th class="text-end">Overall Conversion</th>
                            <th class="text-end">Total Revenue (QAR)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            @php $totals = $report->totals(); @endphp
                            <tr>
                                <td><a href="{{ route('kpi.ads.show', $report) }}">{{ $report->date_from->format('d M Y') }} – {{ $report->date_to->format('d M Y') }}</a></td>
                                <td>{{ count($report->computedCategories()) }}</td>
                                <td class="text-end">{{ $totals['total_leads'] }}</td>
                                <td class="text-end">{{ $totals['total_bookings'] }}</td>
                                <td class="text-end">
                                    <span class="kpi-badge {{ $totals['overall_met_target'] ? 'kpi-badge-green' : 'kpi-badge-red' }}">{{ $totals['overall_conversion'] }}%</span>
                                </td>
                                <td class="text-end">{{ number_format($totals['total_revenue'], 2) }}</td>
                                <td class="text-end">
                                    @moduleEdit('kpis')
                                        <form action="{{ route('kpi.ads.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this report?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bx bx-trash"></i></button>
                                        </form>
                                    @endmoduleEdit
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No reports saved yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end align-items-center mt-2 mb-0">{{ $reports->links() }}</div>
    </div>

    <script>
        document.querySelectorAll('.ads-tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.ads-tab-btn').forEach(b => {
                    b.classList.remove('btn-dark');
                    b.classList.add('btn-outline-secondary');
                });
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-dark');

                document.querySelectorAll('.ads-tab-pane').forEach(p => p.classList.add('d-none'));
                document.getElementById('ads-pane-' + this.dataset.tab).classList.remove('d-none');
            });
        });
    </script>
@endsection
