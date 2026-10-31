@extends('layouts.app')

@section('title', 'Lead Management')

<style>
    .popup-box {
        position: fixed;
        bottom: 25px;
        right: 25px;
        width: 310px;
        background: #241e1c;
        border-radius: 12px;
        padding: 20px 18px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
        border-left: 6px solid #c9a66b;
        animation: popupFade 0.35s ease-out;
        display: none;
        z-index: 9999;
    }

    .popup-content h4 {
        font-size: 18px;
        font-weight: 600;
        color: #c97b4a;
        margin-bottom: 10px;
        padding-left: 5px;
    }

    .popup-content ul {
        margin: 0;
        padding-left: 18px;
    }

    .popup-content ul li {
        margin-bottom: 6px;
        font-size: 14px;
    }

    .close-icon {
        position: absolute;
        top: 8px;
        right: 12px;
        font-size: 22px;
        font-weight: bold;
        cursor: pointer;
        color: #c9a39a;
        transition: 0.2s ease;
    }

    .close-icon:hover {
        color: #e79a91;
        transform: scale(1.1);
    }

    @keyframes popupFade {
        from {
            opacity: 0;
            transform: translateY(20px) scale(0.95);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .lead-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        white-space: nowrap;
    }

    .lead-badge.cat-follow_up { background: rgba(142,168,138,.15); color: #8ea88a; }
    .lead-badge.cat-inquiry { background: rgba(138,166,171,.15); color: #8aa6ab; }
    .lead-badge.cat-no_show { background: rgba(201,166,107,.15); color: #c9a66b; }
    .lead-badge.cat-cancel { background: rgba(168,82,74,.15); color: #a8524a; }

    .needful-done-select {
        border: 1px solid rgba(138,125,118,.28);
        border-radius: 999px;
        padding: 4px 26px 4px 12px;
        font-size: 11.5px;
        font-weight: 600;
        cursor: pointer;
        background-color: rgba(138,125,118,.18);
        color: #8a7d76;
        transition: all 0.2s ease;
    }

    .needful-done-select.needful-booked,
    .needful-done-badge.needful-booked {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 999px;
        padding: 4px 12px;
        font-size: 11.5px;
        font-weight: 600;
        background-color: rgba(74, 222, 128, .15);
        color: #4ade80;
        border: 1px solid rgba(74, 222, 128, .4);
        box-shadow: 0 0 12px rgba(74, 222, 128, .18);
        cursor: default;
        white-space: nowrap;
    }
    .needful-done-select.needful-yes {
        background-color: rgba(234, 179, 8, .16);
        color: #eab308;
        border-color: rgba(234, 179, 8, .35);
    }
    .needful-done-select.needful-no {
        background-color: rgba(168, 82, 74, .14);
        color: #a8524a;
        border-color: rgba(168, 82, 74, .3);
    }

    .lead-quick-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 13px;
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid rgba(217, 143, 131, 0.2);
        background: rgba(36, 30, 28, 0.72);
        color: #cbb8b0;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .lead-quick-pill:hover {
        border-color: rgba(217, 143, 131, 0.45);
        color: #f2e6e2;
        transform: translateY(-1px);
    }

    .lead-quick-pill.is-active {
        background: rgba(217, 143, 131, 0.18);
        border-color: #d98f83;
        color: #e79a91;
        box-shadow: 0 0 12px rgba(217, 143, 131, 0.15);
    }

    .lead-quick-pill.pill-overdue.is-active,
    .lead-quick-pill.pill-overdue:hover {
        border-color: #ef4444;
        color: #fca5a5;
        background: rgba(239, 68, 68, 0.14);
    }

    .lead-quick-pill.pill-unscheduled.is-active,
    .lead-quick-pill.pill-unscheduled:hover {
        border-color: #f59e0b;
        color: #fcd34d;
        background: rgba(245, 158, 11, 0.14);
    }

    .lead-quick-pill.pill-booked.is-active,
    .lead-quick-pill.pill-booked:hover {
        border-color: #4ade80;
        color: #4ade80;
        background: rgba(74, 222, 128, 0.14);
    }

    .lead-pill-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: rgba(255, 255, 255, 0.08);
    }

    .lead-client-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11.5px;
        color: #8ea88a;
        margin-top: 3px;
    }
</style>
@section('content')
    <!-- Page Navbar with Add Button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold">Lead Management</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('leads.analytics') }}" class="btn btn-outline-secondary">
                <i class="bx bx-bar-chart-alt-2 me-1"></i> Analytics
            </a>
            @moduleEdit('leads')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeadModal">
                    <i class="bx bx-plus me-1"></i> Add Lead
                </button>
            @endmoduleEdit
        </div>
    </div>

    <!-- Action Filter Bar & Filter Section -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <!-- Quick Action Filter Pills -->
            <div class="d-flex align-items-center gap-2 flex-wrap" id="leadQuickFilterBar"
                data-overdue-ids='@json($overdueLeads->pluck("id")->values())'
                data-unscheduled-ids='@json($unscheduledLeads->pluck("id")->values())'>
                <a href="{{ route('leads.index') }}"
                    class="lead-quick-pill {{ empty($alertFilter) ? 'is-active' : '' }}">
                    <span>All Leads</span>
                </a>
                <a href="{{ route('leads.index', ['alert_filter' => 'overdue']) }}"
                    class="lead-quick-pill pill-overdue {{ ($alertFilter ?? null) === 'overdue' ? 'is-active' : '' }}"
                    id="overdueLeadsPill">
                    <span>🔴 Overdue Follow-ups</span>
                    <span class="lead-pill-count" id="overdueLeadsPillCount">{{ $overdueCount ?? $overdueLeads->count() }}</span>
                </a>
                <a href="{{ route('leads.index', ['alert_filter' => 'unscheduled']) }}"
                    class="lead-quick-pill pill-unscheduled {{ ($alertFilter ?? null) === 'unscheduled' ? 'is-active' : '' }}"
                    id="unscheduledLeadsPill">
                    <span>🟠 Needs Follow-up Date</span>
                    <span class="lead-pill-count" id="unscheduledLeadsPillCount">{{ $unscheduledCount ?? $unscheduledLeads->count() }}</span>
                </a>
                <a href="{{ route('leads.index', ['alert_filter' => 'booked']) }}"
                    class="lead-quick-pill pill-booked {{ ($alertFilter ?? null) === 'booked' ? 'is-active' : '' }}"
                    id="bookedLeadsPill">
                    <span>🟢 Booked</span>
                    <span class="lead-pill-count" id="bookedLeadsPillCount">{{ $bookedCount ?? 0 }}</span>
                </a>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse"
                    aria-expanded="false" aria-controls="filterCollapse">
                    <i class="bx bx-filter-alt me-1"></i> Filters
                </button>

                <form method="GET" action="{{ route('leads.index') }}" id="followupDateForm" class="d-flex align-items-center gap-2 mb-0">
                    @if (!empty($alertFilter))
                        <input type="hidden" name="alert_filter" value="{{ $alertFilter }}">
                    @endif
                    <label class="form-label mb-0 small text-muted">Next Follow-up Date</label>
                    <input type="date" name="followup_date" class="form-control form-control-sm" style="width: auto;"
                        value="{{ request('followup_date') }}" onchange="this.form.submit()">
                    @if (request('followup_date'))
                        <a href="{{ route('leads.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                    @endif
                </form>
            </div>
        </div>

        <div class="collapse mt-3 {{ request()->hasAny(['category', 'phone_number', 'start_date', 'end_date', 'agent_id']) ? 'show' : '' }}" id="filterCollapse">
            <div class="card card-body shadow-sm border-0">
                <form method="GET" action="{{ route('leads.index') }}">
                    @if (request('followup_date'))
                        <input type="hidden" name="followup_date" value="{{ request('followup_date') }}">
                    @endif
                    <div class="leads-filter-grid">
                        <div>
                            <label class="form-label">Category</label>
                            <select name="category[]" class="form-select leads-multiselect" multiple data-placeholder="All Categories">
                                @foreach (\App\Models\Lead::CATEGORIES as $key => $label)
                                    <option value="{{ $key }}" {{ in_array($key, (array) request('category', [])) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Agent</label>
                            <select name="agent_id" class="form-select">
                                <option value="">All Agents</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}" {{ (string) request('agent_id') === (string) $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="form-label">From</label>
                            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                        </div>

                        <div>
                            <label class="form-label">To</label>
                            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                        </div>

                        <div class="leads-filter-grid-span-2">
                            <label class="form-label">Contact (WhatsApp Number)</label>
                            <div class="input-group">
                                <select name="country_code" class="form-select" style="width: 110px; flex: 0 0 110px;">
                                    @foreach (\App\Models\Lead::COUNTRY_CODES as $code => $label)
                                        <option value="{{ $code }}" title="{{ $label }}" {{ request('country_code', '974') === $code ? 'selected' : '' }}>+{{ $code }} ({{ $label }})</option>
                                    @endforeach
                                </select>
                                <input type="text" name="phone_number" class="form-control" placeholder="XXXXXXXX"
                                    value="{{ request('phone_number') }}">
                            </div>
                        </div>

                        <div class="leads-filter-grid-span-2 d-flex gap-2 align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                            <a href="{{ route('leads.index') }}" class="btn btn-secondary w-100">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .leads-filter-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 1rem;
            align-items: end;
        }

        @media (min-width: 768px) {
            .leads-filter-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .leads-filter-grid-span-2 {
                grid-column: span 2 / span 2;
            }
        }
    </style>


    <!-- Lead Table -->
    <div class="card">
        <h5 class="card-header">All Leads</h5>
        <div class="table-responsive text-nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Contact</th>
                        <th>Category</th>
                        <th>Customer Remarks</th>
                        <th>Service Interest</th>
                        <th>Agent Assign</th>
                        <th>Needful Done</th>
                        <th>Next Follow-up Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                @forelse($leads as $lead)
                    @php
                        $isBookedOrClosed = in_array($lead->needful_done, \App\Models\Lead::CLOSED_STATUSES, true)
                            || in_array(strtolower((string) ($lead->status ?? '')), ['booked', 'closed'], true);
                        $leadCategories = $lead->categories ?? ($lead->category ? [$lead->category] : []);
                        $leadCategories = \App\Models\Lead::normalizeCategories($leadCategories);
                        $leadCatLabel = !empty($leadCategories)
                            ? implode(' + ', array_map(fn($c) => \App\Models\Lead::CATEGORIES[$c] ?? $c, $leadCategories))
                            : 'Inquiry';
                        $leadAgeDays = max(0, (int) floor(($lead->created_at ?? now())->diffInDays(now())));
                    @endphp
                    <tbody class="table-border-bottom-0" x-data="{
                        showAiPanel: false,
                        isLoading: false,
                        isClosed: {{ $isBookedOrClosed ? 'true' : 'false' }},
                        script: '',
                        copied: false,
                        errorMsg: '',
                        generateScript() {
                            if (this.isClosed) return;
                            this.showAiPanel = true;
                            this.isLoading = true;
                            this.errorMsg = '';
                            this.copied = false;
                            fetch('{{ route('leads.ai-script', $lead) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({
                                    lead_id: {{ $lead->id }},
                                    module: 'lead_management',
                                    name: @js($lead->customer->name ?? $lead->name ?? ''),
                                    customer_name: @js($lead->customer->name ?? $lead->name ?? ''),
                                    categories: @js($leadCategories ?: ['inquiry']),
                                    service_interest: @js(!empty($lead->service_interest) ? implode(', ', (array) $lead->service_interest) : ''),
                                    remarks: @js($lead->customer_remarks ?? '')
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
                        <tr @if (!$isBookedOrClosed) @click="if (!isClosed) showAiPanel = !showAiPanel" style="cursor: pointer;" @endif>
                            <td>
                                @if (!$isBookedOrClosed)
                                    <i class="bx me-1 text-muted" x-show="!isClosed" :class="showAiPanel ? 'bx-chevron-down' : 'bx-chevron-right'"></i>
                                @endif
                                {{ $lead->created_at->format('d M Y') }}
                            </td>
                            <td @click.stop>
                                {{ $lead->phone }}
                                @if ($lead->customer)
                                    <a href="{{ route('customers.show', $lead->customer_id) }}" target="_blank" class="lead-client-link">
                                        <i class="bx bx-check-circle"></i> {{ $lead->customer->name ?: 'Linked client' }}
                                    </a>
                                @endif
                            </td>
                            <td>
                                @if (!empty($leadCategories))
                                    <div class="d-flex flex-wrap gap-1 justify-content-center align-items-center flex gap-1.5">
                                        @foreach ($leadCategories as $catKey)
                                            <span class="lead-badge cat-{{ $catKey }}">{{ \App\Models\Lead::CATEGORIES[$catKey] ?? $catKey }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td style="max-width: 220px; white-space: normal;">{{ $lead->customer_remarks ?? '—' }}</td>
                            <td style="max-width: 200px; white-space: normal;">{{ !empty($lead->service_interest) ? implode(', ', $lead->service_interest) : '—' }}</td>
                            <td>{{ $lead->agent->name ?? '—' }}</td>
                            <td @click.stop>
                                @if ($lead->needful_done === 'booked')
                                    <span class="needful-done-badge needful-booked" title="Auto-verified via Calendar Booking">
                                        <span>✅ Booked Appointment</span>
                                        <i class="bx bx-lock-alt" style="font-size: 11.5px; opacity: 0.85;"></i>
                                    </span>
                                @else
                                    @moduleEdit('leads')
                                        <select class="needful-done-select {{ $lead->needful_done ? 'needful-' . $lead->needful_done : '' }}"
                                            data-lead-id="{{ $lead->id }}"
                                            data-previous="{{ $lead->needful_done }}"
                                            data-url="{{ route('leads.needful-done', $lead->id) }}"
                                            @change="isClosed = ($event.target.value === 'no'); if (isClosed) showAiPanel = false;">
                                            <option value="" {{ !$lead->needful_done ? 'selected' : '' }}>— Pending</option>
                                            @foreach (\App\Models\Lead::MANUAL_NEEDFUL_STATUSES as $key => $label)
                                                <option value="{{ $key }}" {{ $lead->needful_done === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <span class="needful-done-select {{ $lead->needful_done ? 'needful-' . $lead->needful_done : '' }}">
                                            {{ $lead->needful_done ? (\App\Models\Lead::NEEDFUL_STATUSES[$lead->needful_done] ?? $lead->needful_done) : '— Pending' }}
                                        </span>
                                    @endmoduleEdit
                                @endif
                            </td>
                            <td>{{ (!in_array($lead->needful_done, \App\Models\Lead::CLOSED_STATUSES, true) && $lead->next_followup_date) ? $lead->next_followup_date->format('d M Y') : '—' }}</td>
                            <td @click.stop>
                                <div class="d-flex gap-2 justify-content-center align-items-center">
                                    @if (!$isBookedOrClosed)
                                        <button type="button"
                                            class="btn btn-sm btn-primary"
                                            x-show="!isClosed"
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

                                    @moduleEdit('leads')
                                        <!-- Edit Button -->
                                        <button type="button" class="btn btn-sm btn-outline-primary" title="Edit Lead"
                                            data-bs-toggle="modal" data-bs-target="#editLeadModal{{ $lead->id }}">
                                            <i class="bx bx-edit-alt"></i>
                                        </button>

                                        <!-- Delete Button -->
                                        <form action="{{ route('leads.destroy', $lead->id) }}" method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this lead?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Lead">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </form>
                                    @endmoduleEdit
                                </div>
                            </td>
                        </tr>
                        @if (!$isBookedOrClosed)
                            <tr x-show="showAiPanel && !isClosed" x-cloak class="bg-dark bg-opacity-10">
                                <td colspan="9" class="p-3" style="white-space: normal;">
                                    <div class="d-flex flex-column gap-2 p-3 rounded border border-secondary border-opacity-25" style="background: rgba(20, 16, 14, 0.45);">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <div class="small fw-bold text-uppercase text-muted" style="letter-spacing: 0.04em;">
                                                🧠 Sales Consultant &mdash; {{ $leadCatLabel }} ({{ $leadAgeDays }}d old)
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
                                            </div>
                                        </div>

                                        <div x-show="!script && !isLoading && !errorMsg" x-cloak class="text-muted small">
                                            Click <strong>🧠 Generate AI Closing Script</strong> to create an ultra-short AIDA WhatsApp script tailored to this lead.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                @empty
                    <tbody class="table-border-bottom-0">
                        <tr>
                            <td colspan="9" class="text-center text-muted">No leads found</td>
                        </tr>
                    </tbody>
                @endforelse

            </table>
        </div>
    </div>
    <!--/ Basic Bootstrap Table -->

    <div id="followupPopup" class="popup-box">
        <span class="close-icon" onclick="closePopup()">×</span>

        <div class="popup-content">
            <h4>🔔 Pending Follow-ups</h4>
            <p id="followupMessage"></p>
        </div>
    </div>


    {{-- Edit modals render here rather than inside the table body above: a <form>
         placed directly in <tbody> (before any <tr>) gets corrupted by the browser's
         table foster-parenting rules, which silently drops any hidden input added to
         it by JavaScript after page load. --}}
    @moduleEdit('leads')
        @foreach ($leads as $lead)
            @include('leads.edit', ['lead' => $lead])
        @endforeach

        {{-- The unscheduled-leads banner above can link to leads outside the table's own
             filter/pagination, so their edit modals need rendering here too (skipping any
             already in $leads to avoid duplicate modal IDs). --}}
        @foreach ($unscheduledLeads->whereNotIn('id', $leads->pluck('id')) as $lead)
            @include('leads.edit', ['lead' => $lead])
        @endforeach

        @include('leads.create')
    @endmoduleEdit

    <script>
        // Smart client linking: as the country code / phone number change in the
        // Add or any Edit modal, check the combined number against the clients
        // table and surface a match/new badge, auto-filling the customer name.
        (function() {
            let lookupTimer = null;

            function handlePhoneChange(el) {
                const form = el.closest('form');
                const matchBox = form.querySelector('.lead-client-match');
                const newBox = form.querySelector('.lead-client-new');
                const hiddenId = form.querySelector('.lead-customer-id-input');
                const nameInput = form.querySelector('.lead-customer-name');
                const countryCode = form.querySelector('.lead-country-code').value;
                const numberVal = form.querySelector('.lead-phone-number').value;
                // Match the server's normalization: Qatar (the default/legacy
                // convention every existing client record uses) stays
                // unprefixed; only a non-Qatar code is actually prepended.
                const digits = (countryCode !== '974' ? countryCode + numberVal : numberVal).replace(/\D/g, '');

                clearTimeout(lookupTimer);
                hiddenId.value = '';
                matchBox.classList.add('d-none');
                newBox.classList.add('d-none');

                if (numberVal.replace(/\D/g, '').length < 6) return;

                lookupTimer = setTimeout(() => {
                    fetch(`{{ route('customers.lookup') }}?phone=${encodeURIComponent(digits)}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.found) {
                                hiddenId.value = data.id;
                                if (nameInput && data.name) nameInput.value = data.name;
                                matchBox.querySelector('.lead-client-visits').textContent = data.visit_count;
                                matchBox.querySelector('.lead-client-visits-wrap')?.classList.remove('d-none');
                                matchBox.querySelector('.lead-client-profile-link').href = data.profile_url;
                                matchBox.classList.remove('d-none');
                                newBox.classList.add('d-none');
                            } else {
                                newBox.classList.remove('d-none');
                                matchBox.classList.add('d-none');
                            }
                        })
                        .catch(() => {});
                }, 400);
            }

            document.addEventListener('input', function(e) {
                if (e.target.matches('.lead-phone-number')) handlePhoneChange(e.target);
            });
            document.addEventListener('change', function(e) {
                if (e.target.matches('.lead-country-code')) handlePhoneChange(e.target);
            });
        })();

        // Open the native calendar picker on any click inside the Next
        // Follow-up Date field (icon or text area alike) instead of making
        // staff hit the small calendar glyph precisely. Delegated on
        // document since the Edit modal is repeated once per lead row.
        document.addEventListener('click', function(e) {
            const dateInput = e.target.closest('.next-followup-date-input');
            if (dateInput && typeof dateInput.showPicker === 'function') {
                try {
                    dateInput.showPicker();
                } catch (err) {
                    // Ignored - e.g. browser blocks showPicker() outside a user gesture.
                }
            }
        });

        window.updateQuickFilterPillCounts = function(leadId, oldStatus, newStatus) {
            const bar = document.getElementById('leadQuickFilterBar');
            if (!bar) return;

            const idNum = Number(leadId);
            const closedStatuses = ['booked', 'no'];
            const wasClosed = closedStatuses.includes(oldStatus);
            const isNowClosed = closedStatuses.includes(newStatus);

            let overdueIds = [];
            let unscheduledIds = [];
            try { overdueIds = JSON.parse(bar.dataset.overdueIds || '[]'); } catch (e) {}
            try { unscheduledIds = JSON.parse(bar.dataset.unscheduledIds || '[]'); } catch (e) {}

            const overdueEl = document.getElementById('overdueLeadsPillCount');
            if (overdueEl && overdueIds.includes(idNum) && wasClosed !== isNowClosed) {
                const cur = Math.max(0, (parseInt(overdueEl.textContent, 10) || 0) + (isNowClosed ? -1 : 1));
                overdueEl.textContent = cur;
            }

            const unscheduledEl = document.getElementById('unscheduledLeadsPillCount');
            if (unscheduledEl && unscheduledIds.includes(idNum) && wasClosed !== isNowClosed) {
                const cur = Math.max(0, (parseInt(unscheduledEl.textContent, 10) || 0) + (isNowClosed ? -1 : 1));
                unscheduledEl.textContent = cur;
            }

            const bookedEl = document.getElementById('bookedLeadsPillCount');
            if (bookedEl) {
                const wasBooked = oldStatus === 'booked';
                const isNowBooked = newStatus === 'booked';
                if (wasBooked !== isNowBooked) {
                    const cur = Math.max(0, (parseInt(bookedEl.textContent, 10) || 0) + (isNowBooked ? 1 : -1));
                    bookedEl.textContent = cur;
                }
            }
        };

        // Inline "Needful Done" toggle straight from the leads table.
        document.addEventListener('change', function(e) {
            if (!e.target.matches('.needful-done-select')) return;

            const select = e.target;
            const previous = select.dataset.previous ?? '';
            const nextVal = select.value;

            fetch(select.dataset.url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ needful_done: nextVal }),
            })
                .then(res => {
                    if (!res.ok) throw new Error();
                    select.classList.remove('needful-booked', 'needful-yes', 'needful-no');
                    if (nextVal) select.classList.add('needful-' + nextVal);
                    select.dataset.previous = nextVal;
                    if (select.dataset.leadId && typeof window.updateQuickFilterPillCounts === 'function') {
                        window.updateQuickFilterPillCounts(select.dataset.leadId, previous, nextVal);
                    }
                })
                .catch(() => {
                    select.value = previous;
                    alert('Could not update Needful Done. Please try again.');
                });
        });

        document.addEventListener("DOMContentLoaded", function() {

            fetch("{{ route('leads.check.followups') }}")
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        showFollowupNotification(data.leads);
                    }
                })
                .catch(error => console.log(error));
        });

        function showFollowupNotification(leads) {
            let popup = document.getElementById("followupPopup");

            let listHTML = "<ul>";
            leads.forEach(lead => {
                listHTML += `<li><strong>${lead.phone}</strong></li>`;
            });
            listHTML += "</ul>";

            document.getElementById("followupMessage").innerHTML =
                `You have <strong>${leads.length}</strong> lead(s) due for follow-up today:<br><br>${listHTML}`;

            popup.style.display = "block";
        }

        function closePopup() {
            document.getElementById("followupPopup").style.display = "none";
        }

        function toggleLeadAlertAccordion(headerEl) {
            const body = headerEl.nextElementSibling;
            const chevron = headerEl.querySelector('.kpi-alert-chevron');
            const isOpening = body.style.display === 'none';
            body.style.display = isOpening ? 'block' : 'none';
            chevron.classList.toggle('is-open', isOpening);
        }
    </script>


    @if (session('existing_lead_id'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                let modalId = 'editLeadModal{{ session('existing_lead_id') }}';
                let modalEl = document.getElementById(modalId);

                if (modalEl) {
                    let modal = new bootstrap.Modal(modalEl);
                    modal.show();

                    // Optional: scroll to modal for better UX
                    modalEl.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            });
        </script>
    @endif

@endsection

@section('scripts')
    <script>
        $(document).ready(function() {
            $('.leads-multiselect').select2({
                width: '100%',
                allowClear: true,
                closeOnSelect: false,
            });
        });
    </script>
@endsection
