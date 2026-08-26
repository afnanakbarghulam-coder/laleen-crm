<!-- Edit Lead Modal -->
<div class="modal fade" id="editLeadModal{{ $lead->id }}" tabindex="-1"
    aria-labelledby="editLeadModalLabel{{ $lead->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLeadModalLabel{{ $lead->id }}">Edit Lead</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('leads.update', $lead->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Contact (WhatsApp Number)</label>
                            <input type="text" name="phone" class="form-control" value="{{ $lead->phone }}"
                                required>
                            <input type="hidden" name="customer_id" class="lead-customer-id-input" value="{{ $lead->customer_id }}">
                            <div class="lead-client-match alert alert-success py-2 px-3 mt-2 mb-0 {{ $lead->customer ? '' : 'd-none' }}" style="font-size:12.5px;">
                                <i class="bx bx-check-circle me-1"></i> Existing client: <strong class="lead-client-name">{{ $lead->customer->name ?? '' ?: 'Unnamed' }}</strong>
                                <span class="lead-client-visits-wrap d-none">&middot; <span class="lead-client-visits"></span> visit(s)</span> &mdash;
                                <a href="{{ $lead->customer ? route('customers.show', $lead->customer_id) : '#' }}" target="_blank" class="lead-client-profile-link">View profile</a>
                            </div>
                            <div class="lead-client-new text-muted small mt-2 d-none">
                                <i class="bx bx-info-circle me-1"></i> New number &mdash; a client profile will be created automatically.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Agent Assign</label>
                            <select name="assigned_agent_id" class="form-select">
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
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="">-- Select Category --</option>
                                @foreach (\App\Models\Lead::CATEGORIES as $key => $label)
                                    <option value="{{ $key }}" {{ $lead->category === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Service Interest</label>
                            <input type="text" name="service_interest" class="form-control" list="serviceInterestOptions"
                                value="{{ $lead->service_interest }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Booking Status</label>
                            <input type="text" name="booking_status" class="form-control" value="{{ $lead->booking_status }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Correction Done</label>
                            <select name="correction_done" class="form-select">
                                <option value="">-- Select --</option>
                                @foreach (\App\Models\Lead::CORRECTION_STATUSES as $key => $label)
                                    <option value="{{ $key }}" {{ $lead->correction_done === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Next Follow-up Date</label>
                            <input type="date" name="next_followup_date" class="form-control"
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
