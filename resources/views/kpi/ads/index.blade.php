@extends('layouts.app')
@section('title', 'Ads Conversion Reports')

@section('content')
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('kpi.ads.index', ['tab' => 'leads']) }}" class="btn btn-sm {{ $activeTab === 'leads' ? 'btn-dark' : 'btn-outline-secondary' }}">Ad Leads Log</a>
            <a href="{{ route('kpi.ads.index', ['tab' => 'analytics']) }}" class="btn btn-sm {{ $activeTab === 'analytics' ? 'btn-dark' : 'btn-outline-secondary' }}">Ads Analytics</a>
            <a href="{{ route('kpi.ads.index', ['tab' => 'metrics']) }}" class="btn btn-sm {{ $activeTab === 'metrics' ? 'btn-dark' : 'btn-outline-secondary' }}">Ad Metrics</a>
        </div>
        @if ($activeTab === 'metrics')
            @moduleEdit('kpis')
                <button type="button" class="btn btn-sm laleen-rose-btn" data-bs-toggle="modal" data-bs-target="#adScreenshotImportModal">
                    Upload Screenshot
                </button>
            @endmoduleEdit
        @endif
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
                                <td class="text-end">{{ $entry->ticket_amount > 0 ? number_format($entry->ticket_amount, 2) : ($entry->isBooked() ? '0.00' : '—') }}</td>
                                <td>
                                    @if ($entry->isBooked())
                                        <span class="kpi-badge kpi-badge-green">{{ $branches[$entry->branch] }}</span>
                                    @else
                                        <span class="text-muted small">Unbooked</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $entry->remarks ?? '—' }}</td>
                                <td class="text-end" @click.stop>
                                    <div class="d-flex gap-1 justify-content-end align-items-center">
                                        @if (!$entry->isBooked())
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
                            @if (!$entry->isBooked())
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
                                                    <a :href="'https://wa.me/{{ \App\Models\Customer::whatsappDigits($entry->phone) }}?text=' + encodeURIComponent(script)"
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
    @if ($activeTab === 'analytics')
        <div id="ads-pane-analytics" class="ads-tab-pane">
            @include('kpi.ads._analytics-pane')
        </div>
    @endif

    @if ($activeTab === 'metrics')
        {{-- ================= AD METRICS TRACKER ================= --}}
        <div id="ads-pane-metrics" class="ads-tab-pane">
            @include('kpi.ads._campaign-metrics-tracker', [
                'filterActionUrl' => route('kpi.ads.index'),
                'isStandaloneEcommerce' => false,
                'stayInKpis' => true,
            ])
        </div>

        @if (auth()->check() && (auth()->user()->canEdit('kpis') || auth()->user()->canEdit('ecommerce')))
            @include('kpi.ads._screenshot-import-modal', [
                'stayInKpis' => true,
            ])
        @endif
    @endif
@endsection
