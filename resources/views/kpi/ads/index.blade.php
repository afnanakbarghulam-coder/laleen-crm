@extends('layouts.app')
@section('title', 'Ads Conversion Reports')

@section('content')
    <style>
        .ad-followup-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            line-height: 1.35;
            transition: opacity 0.15s ease, transform 0.15s ease;
        }
        .ad-followup-pill:hover {
            opacity: 0.9;
            transform: translateY(-0.5px);
        }
        .ad-followup-pill.is-active {
            background: rgba(217, 143, 131, 0.16);
            border: 1px solid rgba(217, 143, 131, 0.38);
            color: #f4c4bc;
        }
        .ad-followup-pill.is-booked {
            background: rgba(74, 222, 128, 0.14);
            border: 1px solid rgba(74, 222, 128, 0.35);
            color: #86efac;
        }
        .ad-followup-pill.is-lost {
            background: rgba(168, 82, 74, 0.16);
            border: 1px solid rgba(168, 82, 74, 0.35);
            color: #e2a299;
        }
        .ad-status-caption {
            display: block;
            font-size: 10.5px;
            color: rgba(255, 255, 255, 0.55);
            margin-top: 2px;
            line-height: 1.25;
        }
        .ad-leads-search-wrap {
            position: relative;
        }
        .ad-leads-search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(235, 210, 200, 0.55);
            font-size: 1rem;
            pointer-events: none;
        }
        .ad-leads-search-input {
            padding-left: 2.2rem !important;
            padding-right: 2.2rem !important;
        }
        .ad-leads-search-clear {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            color: rgba(245, 232, 226, 0.85);
            font-size: 15px;
            line-height: 1;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .ad-leads-search-clear:hover {
            background: rgba(217, 143, 131, 0.28);
            color: #fff;
        }
        .ad-leads-filter-panel {
            overflow: visible !important;
            position: relative;
            z-index: 30;
        }
        .ad-leads-suggestions-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 1060;
            background: #241e1c !important;
            background-color: #241e1c !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            opacity: 1 !important;
            border: 1px solid rgba(217, 143, 131, 0.34);
            border-radius: 12px;
            box-shadow: 0 20px 48px rgba(0, 0, 0, 0.78);
            max-height: 340px;
            overflow-y: auto;
            padding: 6px;
        }
        .ad-leads-suggestion-item {
            width: 100%;
            text-align: left;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 9px;
            padding: 8px 10px;
            color: #f6ede9;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 3px;
            transition: background 0.12s ease, border-color 0.12s ease;
        }
        .ad-leads-suggestion-item:hover,
        .ad-leads-suggestion-item.is-active {
            background: #332926;
            border-color: rgba(217, 143, 131, 0.42);
        }
        .ad-suggest-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .ad-suggest-name {
            font-size: 13px;
            font-weight: 600;
            color: #f7efeb;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ad-suggest-phone {
            font-size: 12px;
            font-weight: 500;
            color: #d7b7ae;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .ad-suggest-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-size: 11px;
            color: rgba(235, 215, 208, 0.62);
        }
        .ad-suggest-cat {
            display: inline-flex;
            align-items: center;
            padding: 1px 7px;
            border-radius: 999px;
            background: rgba(217, 143, 131, 0.16);
            border: 1px solid rgba(217, 143, 131, 0.3);
            color: #f4c4bc;
            font-weight: 600;
            max-width: 58%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ad-suggest-date-wrap {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .ad-suggest-outside-badge {
            display: inline-flex;
            align-items: center;
            padding: 1px 6px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
            background: rgba(245, 158, 11, 0.16);
            border: 1px solid rgba(245, 158, 11, 0.4);
            color: #fcd34d;
        }
        .ad-suggest-hl {
            background: rgba(217, 143, 131, 0.34);
            color: #fff6f4;
            padding: 0 2px;
            border-radius: 3px;
        }
        .ad-leads-suggestion-status {
            padding: 10px 12px;
            font-size: 12.5px;
            color: rgba(235, 215, 208, 0.72);
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>

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

        @php
            $entriesSearchVal = (string) ($entriesSearch ?? '');
            $isSearchingAllDates = ! empty($searchAllDates);
        @endphp

        <div class="kpi-panel ad-leads-filter-panel mb-3">
            <form method="GET" action="{{ route('kpi.ads.index') }}" id="adLeadsFilterForm" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="leads">
                @if ($isSearchingAllDates)
                    <input type="hidden" name="all_dates" id="adLeadsAllDatesHidden" value="1">
                @endif
                <div class="col-md-3 col-lg-2">
                    <label class="form-label" for="adLeadsFromInput">From</label>
                    <input type="date" name="entries_from" id="adLeadsFromInput" class="form-control" value="{{ $entriesFrom?->format('Y-m-d') }}">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label class="form-label" for="adLeadsToInput">To</label>
                    <input type="date" name="entries_to" id="adLeadsToInput" class="form-control" value="{{ $entriesTo?->format('Y-m-d') }}">
                </div>
                <div class="col-md-6 col-lg-4">
                    <label class="form-label" for="adLeadsSearchInput">Search</label>
                    <div class="ad-leads-search-wrap" id="adLeadsSearchWrap">
                        <i class="bx bx-search ad-leads-search-icon" aria-hidden="true"></i>
                        <input
                            type="text"
                            name="search"
                            id="adLeadsSearchInput"
                            class="form-control ad-leads-search-input"
                            value="{{ $entriesSearchVal }}"
                            data-initial-search="{{ $entriesSearchVal }}"
                            data-suggestions-url="{{ route('kpi.ad-leads.suggestions') }}"
                            placeholder="Name or phone number..."
                            autocomplete="off"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="adLeadsSuggestionsMenu"
                        >
                        <button
                            type="button"
                            id="adLeadsSearchClearBtn"
                            class="ad-leads-search-clear {{ $entriesSearchVal !== '' ? '' : 'd-none' }}"
                            aria-label="Clear search"
                            title="Clear search"
                        >&times;</button>
                        <div id="adLeadsSuggestionsMenu" class="ad-leads-suggestions-menu d-none" role="listbox"></div>
                    </div>
                </div>
                <div class="col-md-12 col-lg-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="adLeadsFilterBtn">Filter</button>
                    @if ($entriesFrom || $entriesTo || $entriesSearchVal !== '' || $isSearchingAllDates)
                        <a href="{{ route('kpi.ads.index', ['tab' => 'leads']) }}" class="btn btn-outline-secondary" id="adLeadsResetBtn">Reset</a>
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
                            $entryFollowUpDate = $entry->effectiveFollowUpDate();
                            $entryStatusKey = $entry->effectiveStatusKey();
                            $entryStatusLabel = $entry->effectiveStatusLabel();
                            $entryFollowUpUrl = $entry->followUpUrl();
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
                                'status' => $entry->status,
                                'follow_up_date' => $entryFollowUpDate?->format('Y-m-d'),
                                'assigned_agent_id' => $entry->assigned_agent_id ?: $entry->lead?->assigned_agent_id,
                                'lead_id' => $entry->lead_id,
                                'follow_up_url' => $entryFollowUpUrl,
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
                                <td data-ad-lead-status-cell="{{ $entry->id }}">
                                    @if ($entry->isBooked())
                                        <span class="kpi-badge kpi-badge-green">{{ $branches[$entry->branch] }}</span>
                                        @if ($entryFollowUpUrl)
                                            <div class="mt-1">
                                                <a href="{{ $entryFollowUpUrl }}" class="ad-followup-pill is-booked" @click.stop title="View synced lead in Lead Management">
                                                    <i class="bx bx-link-alt"></i> In Follow-Ups &middot; Booked
                                                </a>
                                            </div>
                                        @endif
                                    @elseif ($entry->isSyncedToFollowUps() && $entryFollowUpUrl)
                                        @php
                                            $pillClass = match ($entryStatusKey) {
                                                'lost' => 'is-lost',
                                                'booked' => 'is-booked',
                                                default => 'is-active',
                                            };
                                            $pillText = match (true) {
                                                $entryStatusKey === 'lost' => 'In Follow-Ups · Lost',
                                                $entryStatusKey === 'booked' => 'In Follow-Ups · Booked',
                                                $entryFollowUpDate !== null => 'In Follow-Ups · Due ' . $entryFollowUpDate->format('d M'),
                                                default => 'In Follow-Ups',
                                            };
                                        @endphp
                                        <a href="{{ $entryFollowUpUrl }}" class="ad-followup-pill {{ $pillClass }}" @click.stop title="Open synced record in Lead Management">
                                            <i class="bx bx-calendar-check"></i> {{ $pillText }}
                                        </a>
                                        @if ($entryStatusLabel && ! in_array($entryStatusKey, ['follow_up', 'lost', 'booked'], true))
                                            <span class="ad-status-caption">{{ $entryStatusLabel }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted small">Unbooked</span>
                                        @if ($entryStatusLabel && $entryStatusKey === 'lost')
                                            <div><span class="ad-followup-pill is-lost mt-1">{{ $entryStatusLabel }}</span></div>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-muted small" data-ad-lead-remarks-cell="{{ $entry->id }}">{{ $entry->remarks ?? '—' }}</td>
                                <td class="text-end" @click.stop>
                                    <div class="d-flex gap-1 justify-content-end align-items-center flex-nowrap">
                                        @if (!$entry->isBooked())
                                            @moduleEdit('kpis')
                                                @if (!$entry->isSyncedToFollowUps())
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-info text-nowrap ad-lead-send-followup-btn"
                                                        data-entry='@json($entryPayload)'
                                                        data-send-url="{{ route('kpi.ad-leads.send-to-followups', $entry) }}"
                                                        title="Send to Lead Management Follow-Ups">
                                                        <i class="bx bx-calendar-plus me-1"></i>Send to Follow-Ups
                                                    </button>
                                                @endif
                                            @endmoduleEdit
                                            <button type="button"
                                                class="btn btn-sm btn-primary text-nowrap"
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
                                                data-ad-lead-edit-btn="{{ $entry->id }}"
                                                data-entry='@json($entryPayload)'
                                                title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form action="{{ route('kpi.ad-leads.destroy', $entry) }}" method="POST" class="d-inline m-0" onsubmit="return confirm('Delete this ad lead entry?')">
                                                @csrf @method('DELETE')
                                                @foreach (request()->only(['tab', 'entries_from', 'entries_to', 'search', 'all_dates', 'month', 'entries_page']) as $rfKey => $rfVal)
                                                    @if ($rfVal !== null && $rfVal !== '')
                                                        <input type="hidden" name="return_filters[{{ $rfKey }}]" value="{{ $rfVal }}">
                                                    @endif
                                                @endforeach
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
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    @if ($entriesSearchVal !== '' && ($entriesFrom || $entriesTo))
                                        <div>
                                            No ad leads matched <strong class="text-reset">&ldquo;{{ $entriesSearchVal }}&rdquo;</strong>
                                            between {{ $entriesFrom?->format('d M Y') }} and {{ $entriesTo?->format('d M Y') }}.
                                        </div>
                                        <div class="mt-2 d-flex justify-content-center gap-2 flex-wrap">
                                            <a href="{{ route('kpi.ads.index', ['tab' => 'leads', 'search' => $entriesSearchVal, 'all_dates' => 1]) }}"
                                                id="adLeadsSearchAllDatesBtn"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="bx bx-calendar-x me-1"></i>Search all dates
                                            </a>
                                            <a href="{{ route('kpi.ads.index', ['tab' => 'leads']) }}" class="btn btn-sm btn-outline-secondary">
                                                Reset filters
                                            </a>
                                        </div>
                                    @elseif ($entriesSearchVal !== '')
                                        <div>
                                            No ad leads matched <strong class="text-reset">&ldquo;{{ $entriesSearchVal }}&rdquo;</strong> across all dates.
                                        </div>
                                        <div class="mt-2">
                                            <a href="{{ route('kpi.ads.index', ['tab' => 'leads']) }}" class="btn btn-sm btn-outline-secondary">
                                                Reset filters
                                            </a>
                                        </div>
                                    @else
                                        No ad leads logged{{ ($entriesFrom || $entriesTo) ? ' for this period' : ' yet' }}.
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    @endforelse
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end align-items-center mt-2 mb-0">{{ $adLeadEntries->links() }}</div>

        @moduleEdit('kpis')
            @include('kpi.ads._lead-entry-modal', ['uid' => 'new', 'entry' => null])
            @include('kpi.ads._send-to-followups-modal')
        @endmoduleEdit

        <script>
            (function initAdLeadsSearchBar() {
                const form = document.getElementById('adLeadsFilterForm');
                const searchWrap = document.getElementById('adLeadsSearchWrap');
                const searchInput = document.getElementById('adLeadsSearchInput');
                const clearBtn = document.getElementById('adLeadsSearchClearBtn');
                const menuEl = document.getElementById('adLeadsSuggestionsMenu');
                const fromInput = document.getElementById('adLeadsFromInput');
                const toInput = document.getElementById('adLeadsToInput');

                if (!form || !searchInput || !menuEl) return;

                const suggestionsUrl = searchInput.dataset.suggestionsUrl || '';
                let debounceTimer = null;
                let activeController = null;
                let currentItems = [];
                let activeIndex = -1;
                let isOpen = false;

                function syncClearVisibility() {
                    if (!clearBtn) return;
                    clearBtn.classList.toggle('d-none', searchInput.value.trim() === '');
                }

                function escapeHtml(text) {
                    return String(text || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                function highlightSubstring(text, query) {
                    const source = String(text || '');
                    const needle = String(query || '').trim();
                    if (!needle || !source) {
                        return escapeHtml(source);
                    }

                    const lowerSource = source.toLowerCase();
                    const lowerNeedle = needle.toLowerCase();
                    const idx = lowerSource.indexOf(lowerNeedle);
                    if (idx !== -1) {
                        return escapeHtml(source.slice(0, idx))
                            + '<mark class="ad-suggest-hl">'
                            + escapeHtml(source.slice(idx, idx + needle.length))
                            + '</mark>'
                            + escapeHtml(source.slice(idx + needle.length));
                    }

                    // For phone strings, also highlight matching normalized digit tail when input had +974/spaces
                    const digits = needle.replace(/\D+/g, '');
                    const localDigits = digits.startsWith('974') && digits.length > 3 ? digits.slice(3) : digits;
                    if (localDigits.length >= 2) {
                        const dIdx = lowerSource.indexOf(localDigits);
                        if (dIdx !== -1) {
                            return escapeHtml(source.slice(0, dIdx))
                                + '<mark class="ad-suggest-hl">'
                                + escapeHtml(source.slice(dIdx, dIdx + localDigits.length))
                                + '</mark>'
                                + escapeHtml(source.slice(dIdx + localDigits.length));
                        }
                    }

                    return escapeHtml(source);
                }

                function openMenu() {
                    isOpen = true;
                    menuEl.classList.remove('d-none');
                    searchInput.setAttribute('aria-expanded', 'true');
                }

                function closeMenu() {
                    isOpen = false;
                    activeIndex = -1;
                    menuEl.classList.add('d-none');
                    searchInput.setAttribute('aria-expanded', 'false');
                }

                function renderLoading() {
                    currentItems = [];
                    activeIndex = -1;
                    menuEl.innerHTML = '<div class="ad-leads-suggestion-status"><span class="spinner-border spinner-border-sm text-secondary" role="status" aria-hidden="true"></span><span>Searching...</span></div>';
                    openMenu();
                }

                function renderEmpty() {
                    currentItems = [];
                    activeIndex = -1;
                    menuEl.innerHTML = '<div class="ad-leads-suggestion-status">No matching ad leads</div>';
                    openMenu();
                }

                function updateActiveItemUi() {
                    const buttons = menuEl.querySelectorAll('.ad-leads-suggestion-item');
                    buttons.forEach(function (btn, idx) {
                        const isActive = idx === activeIndex;
                        btn.classList.toggle('is-active', isActive);
                        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                        if (isActive) {
                            btn.scrollIntoView({ block: 'nearest' });
                        }
                    });
                }

                function selectSuggestion(item) {
                    if (!item) return;

                    const selectedTerm = (item.phone && String(item.phone).trim() !== '')
                        ? String(item.phone).trim()
                        : String(item.customer_name || '').trim();

                    searchInput.value = selectedTerm;
                    syncClearVisibility();
                    closeMenu();

                    if (item.in_range === false) {
                        if (fromInput) fromInput.value = '';
                        if (toInput) toInput.value = '';
                        let hiddenAll = document.getElementById('adLeadsAllDatesHidden');
                        if (!hiddenAll) {
                            hiddenAll = document.createElement('input');
                            hiddenAll.type = 'hidden';
                            hiddenAll.name = 'all_dates';
                            hiddenAll.id = 'adLeadsAllDatesHidden';
                            hiddenAll.value = '1';
                            form.appendChild(hiddenAll);
                        } else {
                            hiddenAll.value = '1';
                        }
                    }

                    form.submit();
                }

                function renderItems(items, query) {
                    currentItems = Array.isArray(items) ? items : [];
                    activeIndex = -1;

                    if (currentItems.length === 0) {
                        renderEmpty();
                        return;
                    }

                    menuEl.innerHTML = '';

                    currentItems.forEach(function (item, idx) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'ad-leads-suggestion-item';
                        btn.setAttribute('role', 'option');
                        btn.setAttribute('aria-selected', 'false');
                        btn.dataset.index = String(idx);

                        const outsideBadgeHtml = item.in_range === false
                            ? '<span class="ad-suggest-outside-badge">Outside range</span>'
                            : '';

                        btn.innerHTML = ''
                            + '<div class="ad-suggest-top">'
                            +   '<span class="ad-suggest-name">' + highlightSubstring(item.customer_name || 'Unnamed Lead', query) + '</span>'
                            +   '<span class="ad-suggest-phone">' + highlightSubstring(item.phone || '—', query) + '</span>'
                            + '</div>'
                            + '<div class="ad-suggest-meta">'
                            +   '<span class="ad-suggest-cat">' + escapeHtml(item.ad_type || 'Ad Lead') + '</span>'
                            +   '<span class="ad-suggest-date-wrap"><span>' + escapeHtml(item.date || '') + '</span>' + outsideBadgeHtml + '</span>'
                            + '</div>';

                        btn.addEventListener('mouseenter', function () {
                            activeIndex = idx;
                            updateActiveItemUi();
                        });

                        btn.addEventListener('mousedown', function (e) {
                            e.preventDefault();
                            selectSuggestion(item);
                        });

                        menuEl.appendChild(btn);
                    });

                    openMenu();
                }

                function fetchSuggestions(query) {
                    if (!suggestionsUrl) return;

                    if (activeController) {
                        activeController.abort();
                    }
                    activeController = new AbortController();

                    renderLoading();

                    const url = new URL(suggestionsUrl, window.location.origin);
                    url.searchParams.set('q', query);
                    if (fromInput && fromInput.value.trim() !== '') {
                        url.searchParams.set('from', fromInput.value.trim());
                    }
                    if (toInput && toInput.value.trim() !== '') {
                        url.searchParams.set('to', toInput.value.trim());
                    }

                    fetch(url.toString(), {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                        signal: activeController.signal,
                    })
                    .then(function (res) {
                        if (!res.ok) throw new Error('Suggestions request failed');
                        return res.json();
                    })
                    .then(function (items) {
                        if (searchInput.value.trim() !== query) return;
                        renderItems(items, query);
                    })
                    .catch(function (err) {
                        if (err && err.name === 'AbortError') return;
                        closeMenu();
                    });
                }

                searchInput.addEventListener('input', function () {
                    syncClearVisibility();
                    const term = searchInput.value.trim();

                    if (debounceTimer) {
                        clearTimeout(debounceTimer);
                        debounceTimer = null;
                    }

                    if (term.length < 2) {
                        if (activeController) {
                            activeController.abort();
                            activeController = null;
                        }
                        currentItems = [];
                        closeMenu();
                        return;
                    }

                    debounceTimer = setTimeout(function () {
                        fetchSuggestions(term);
                    }, 200);
                });

                searchInput.addEventListener('keydown', function (e) {
                    if (!isOpen || currentItems.length === 0) {
                        if (e.key === 'Escape' && isOpen) {
                            e.preventDefault();
                            closeMenu();
                        }
                        return;
                    }

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        activeIndex = (activeIndex + 1) % currentItems.length;
                        updateActiveItemUi();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        activeIndex = activeIndex <= 0 ? (currentItems.length - 1) : (activeIndex - 1);
                        updateActiveItemUi();
                    } else if (e.key === 'Enter') {
                        if (activeIndex >= 0 && currentItems[activeIndex]) {
                            e.preventDefault();
                            selectSuggestion(currentItems[activeIndex]);
                        } else {
                            closeMenu();
                        }
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        closeMenu();
                    }
                });

                document.addEventListener('mousedown', function (e) {
                    if (!isOpen) return;
                    if (searchWrap && searchWrap.contains(e.target)) return;
                    closeMenu();
                });

                if (clearBtn) {
                    clearBtn.addEventListener('click', function () {
                        if (debounceTimer) {
                            clearTimeout(debounceTimer);
                            debounceTimer = null;
                        }
                        if (activeController) {
                            activeController.abort();
                            activeController = null;
                        }
                        const hadInitialSearch = (searchInput.dataset.initialSearch || '').trim() !== '';
                        searchInput.value = '';
                        currentItems = [];
                        menuEl.innerHTML = '';
                        closeMenu();
                        syncClearVisibility();
                        if (hadInitialSearch) {
                            form.submit();
                        } else {
                            searchInput.focus();
                        }
                    });
                }

                // If user manually enters a date while in "Search all dates" mode, exit all_dates mode
                [fromInput, toInput].forEach(function (dateEl) {
                    if (!dateEl) return;
                    dateEl.addEventListener('change', function () {
                        const allDatesHidden = document.getElementById('adLeadsAllDatesHidden');
                        if (allDatesHidden && (dateEl.value || '').trim() !== '') {
                            allDatesHidden.remove();
                        }
                    });
                });
            })();
        </script>
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
