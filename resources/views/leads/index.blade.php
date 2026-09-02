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
        border: none;
        border-radius: 999px;
        padding: 3px 24px 3px 10px;
        font-size: 11.5px;
        font-weight: 600;
        cursor: pointer;
        background-color: rgba(138,125,118,.18);
        color: #8a7d76;
    }

    .needful-done-select.needful-yes { background-color: rgba(142,168,138,.15); color: #8ea88a; }
    .needful-done-select.needful-no { background-color: rgba(168,82,74,.12); color: #a8524a; }

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

    @if ($overdueLeads->count())
        <div class="kpi-alert kpi-alert-red">
            <i class="bx bx-error-circle"></i>
            <div class="flex-grow-1">
                <strong>{{ $overdueLeads->count() }} {{ Str::plural('lead', $overdueLeads->count()) }} overdue for follow-up!</strong>
                <div class="text-muted small mb-2">Next Follow-up Date has passed and Needful Done isn't marked Yes.</div>
                <div style="max-height: 170px; overflow-y: auto;">
                    @foreach ($overdueLeads as $lead)
                        <div class="d-flex justify-content-between small mb-1 pe-2">
                            <span>{{ $lead->customer->name ?? 'Unnamed' }} &middot; {{ $lead->phone }}</span>
                            <span class="text-muted">Was due {{ $lead->next_followup_date->format('d M Y') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if ($unscheduledLeads->count())
        <div class="kpi-alert kpi-alert-amber">
            <i class="bx bx-calendar-x"></i>
            <div class="flex-grow-1">
                <strong>{{ $unscheduledLeads->count() }} Cancelled/No-show {{ Str::plural('lead', $unscheduledLeads->count()) }} need a follow-up date!</strong>
                <div class="text-muted small mb-2">Contact the client, agree a rebooking timeline, and set Next Follow-up Date.</div>
                <div style="max-height: 170px; overflow-y: auto;">
                    @foreach ($unscheduledLeads as $lead)
                        <div class="d-flex justify-content-between align-items-center small mb-1 pe-2">
                            <span>
                                {{ $lead->customer->name ?? 'Unnamed' }} &middot; {{ $lead->phone }}
                                <span class="lead-badge cat-{{ $lead->category }} ms-1">{{ \App\Models\Lead::CATEGORIES[$lead->category] }}</span>
                                <span class="text-muted">{{ $lead->service_interest }}</span>
                            </span>
                            @moduleEdit('leads')
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0" data-bs-toggle="modal" data-bs-target="#editLeadModal{{ $lead->id }}">Set date</button>
                            @endmoduleEdit
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <!-- Filter Section -->
    <div class="mb-4">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse"
                aria-expanded="false" aria-controls="filterCollapse">
                <i class="bx bx-filter-alt me-1"></i> Filters
            </button>

            <form method="GET" action="{{ route('leads.index') }}" id="followupDateForm" class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 small text-muted">Next Follow-up Date</label>
                <input type="date" name="followup_date" class="form-control form-control-sm" style="width: auto;"
                    value="{{ request('followup_date') }}" onchange="this.form.submit()">
                @if (request('followup_date'))
                    <a href="{{ route('leads.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
            </form>
        </div>

        <div class="collapse mt-3 {{ request()->hasAny(['category', 'phone_number']) ? 'show' : '' }}" id="filterCollapse">
            <div class="card card-body shadow-sm border-0">
                <form method="GET" action="{{ route('leads.index') }}">
                    @if (request('followup_date'))
                        <input type="hidden" name="followup_date" value="{{ request('followup_date') }}">
                    @endif
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category[]" class="form-select leads-multiselect" multiple data-placeholder="All Categories">
                                @foreach (\App\Models\Lead::CATEGORIES as $key => $label)
                                    <option value="{{ $key }}" {{ in_array($key, (array) request('category', [])) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
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

                        <div class="col-md-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                            <a href="{{ route('leads.index') }}" class="btn btn-secondary w-100">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- Lead Table -->
    <div class="card">
        <h5 class="card-header">All Leads</h5>
        <div class="table-responsive text-nowrap">
            <table class="table">
                <thead>
                    <th>Date</th>
                    <th>Contact</th>
                    <th>Category</th>
                    <th>Customer Remarks</th>
                    <th>Service Interest</th>
                    <th>Agent Assign</th>
                    <th>Needful Done</th>
                    <th>Next Follow-up Date</th>
                    <th>Actions</th>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($leads as $lead)
                        @moduleEdit('leads')
                            @include('leads.edit', ['lead' => $lead])
                        @endmoduleEdit

                        <tr>
                            <td>{{ $lead->created_at->format('d M Y') }}</td>
                            <td>
                                {{ $lead->phone }}
                                @if ($lead->customer)
                                    <a href="{{ route('customers.show', $lead->customer_id) }}" target="_blank" class="lead-client-link">
                                        <i class="bx bx-check-circle"></i> {{ $lead->customer->name ?: 'Linked client' }}
                                    </a>
                                @endif
                            </td>
                            <td>
                                @if ($lead->category)
                                    <span class="lead-badge cat-{{ $lead->category }}">{{ \App\Models\Lead::CATEGORIES[$lead->category] ?? $lead->category }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $lead->customer_remarks ?? '—' }}</td>
                            <td>{{ $lead->service_interest ?? '—' }}</td>
                            <td>{{ $lead->agent->name ?? '—' }}</td>
                            <td>
                                @moduleEdit('leads')
                                    <select class="needful-done-select {{ $lead->needful_done ? 'needful-' . $lead->needful_done : '' }}"
                                        data-lead-id="{{ $lead->id }}"
                                        data-previous="{{ $lead->needful_done }}"
                                        data-url="{{ route('leads.needful-done', $lead->id) }}">
                                        <option value="" {{ !$lead->needful_done ? 'selected' : '' }}>—</option>
                                        @foreach (\App\Models\Lead::NEEDFUL_STATUSES as $key => $label)
                                            <option value="{{ $key }}" {{ $lead->needful_done === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="needful-done-select {{ $lead->needful_done ? 'needful-' . $lead->needful_done : '' }}">
                                        {{ $lead->needful_done ? (\App\Models\Lead::NEEDFUL_STATUSES[$lead->needful_done] ?? $lead->needful_done) : '—' }}
                                    </span>
                                @endmoduleEdit
                            </td>
                            <td>{{ $lead->next_followup_date ? $lead->next_followup_date->format('d M Y') : '—' }}</td>
                            <td>
                                @moduleEdit('leads')
                                    <div class="d-flex gap-2 justify-content-center">
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

                                    </div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endmoduleEdit
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">No leads found</td>
                        </tr>
                    @endforelse
                </tbody>

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


    {{-- The unscheduled-leads banner above can link to leads outside the table's own
         filter/pagination, so their edit modals need rendering here too (skipping any
         already in $leads to avoid duplicate modal IDs). --}}
    @moduleEdit('leads')
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

        // Inline "Needful Done" toggle straight from the leads table.
        document.addEventListener('change', function(e) {
            if (!e.target.matches('.needful-done-select')) return;

            const select = e.target;
            const previous = select.dataset.previous ?? '';

            fetch(select.dataset.url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ needful_done: select.value }),
            })
                .then(res => {
                    if (!res.ok) throw new Error();
                    select.classList.remove('needful-yes', 'needful-no');
                    if (select.value) select.classList.add('needful-' + select.value);
                    select.dataset.previous = select.value;
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
