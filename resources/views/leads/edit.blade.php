<!-- Edit Lead Modal -->
<div class="modal fade" id="editLeadModal{{ $lead->id }}" tabindex="-1"
    aria-labelledby="editLeadModalLabel{{ $lead->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLeadModalLabel{{ $lead->id }}">Edit Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            @php [$leadCountryCode, $leadLocalNumber] = \App\Models\Lead::splitPhone($lead->phone); @endphp

            <form action="{{ route('leads.update', $lead->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Contact (WhatsApp Number)</label>
                            <div class="input-group">
                                <select name="country_code" class="form-select lead-country-code" style="width: 110px; flex: 0 0 110px;">
                                    @foreach (\App\Models\Lead::COUNTRY_CODES as $code => $label)
                                        <option value="{{ $code }}" title="{{ $label }}" {{ $leadCountryCode === $code ? 'selected' : '' }}>+{{ $code }} ({{ $label }})</option>
                                    @endforeach
                                </select>
                                <input type="text" name="phone_number" class="form-control lead-phone-number" value="{{ $leadLocalNumber }}" required>
                            </div>
                            <input type="hidden" name="customer_id" class="lead-customer-id-input" value="{{ $lead->customer_id }}">
                            <div class="lead-client-match alert alert-success py-2 px-3 mt-2 mb-0 {{ $lead->customer ? '' : 'd-none' }}" style="font-size:12.5px;">
                                <i class="bx bx-check-circle me-1"></i> Existing client profile linked
                                <span class="lead-client-visits-wrap d-none">&middot; <span class="lead-client-visits"></span> visit(s)</span> &mdash;
                                <a href="{{ $lead->customer ? route('customers.show', $lead->customer_id) : '#' }}" target="_blank" class="lead-client-profile-link">View profile</a>
                            </div>
                            <div class="lead-client-new text-muted small mt-2 d-none">
                                <i class="bx bx-info-circle me-1"></i> New number &mdash; a client profile will be created automatically.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" class="form-control lead-customer-name" value="{{ $lead->customer->name ?? '' }}" placeholder="Customer name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Agent Assign <span class="text-danger">*</span></label>
                            <select name="assigned_agent_id" class="form-select" required>
                                <option value="">-- Select Agent --</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}"
                                        {{ $lead->assigned_agent_id == $agent->id ? 'selected' : '' }}>
                                        {{ $agent->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            @if ($lead->category && !array_key_exists($lead->category, \App\Models\Lead::MANUAL_CATEGORIES))
                                <div>
                                    <span class="lead-badge cat-{{ $lead->category }}">{{ \App\Models\Lead::CATEGORIES[$lead->category] }}</span>
                                </div>
                                <div class="form-text">Set automatically from the Enhanced Calendar &mdash; not manually editable.</div>
                                <input type="hidden" name="category" value="{{ $lead->category }}">
                            @else
                                <select name="category" class="form-select" required>
                                    <option value="">-- Select Category --</option>
                                    @foreach (\App\Models\Lead::MANUAL_CATEGORIES as $key => $label)
                                        <option value="{{ $key }}" {{ $lead->category === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Service Interest <span class="text-danger">*</span></label>
                            <select name="service_interest" class="form-select" required>
                                <option value="">-- Select Service --</option>
                                @foreach ($services ?? [] as $serviceName)
                                    <option value="{{ $serviceName }}" {{ $lead->service_interest === $serviceName ? 'selected' : '' }}>{{ $serviceName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Next Follow-up Date <span class="text-danger">*</span></label>
                            <input type="date" name="next_followup_date" class="form-control next-followup-date-input" required
                                value="{{ optional($lead->next_followup_date)->format('Y-m-d') }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Customer Remarks</label>
                            <textarea name="customer_remarks" class="form-control" rows="3">{{ $lead->customer_remarks }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>
