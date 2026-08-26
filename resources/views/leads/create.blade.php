 <!-- Add Lead Modal -->
 <div class="modal fade" id="addLeadModal" tabindex="-1" aria-labelledby="addLeadModalLabel" aria-hidden="true">
     <div class="modal-dialog modal-lg modal-dialog-centered">
         <div class="modal-content">
             <div class="modal-header">
                 <h5 class="modal-title" id="addLeadModalLabel">Add New Lead</h5>
                 <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
             </div>

             <form action="{{ route('leads.store') }}" method="POST">
                 @csrf
                 <div class="modal-body">
                     <div class="row g-3">
                         <div class="col-md-6">
                             <label class="form-label">Contact (WhatsApp Number)</label>
                             <input type="text" name="phone" class="form-control" placeholder="974XXXXXXXX" value="974" required>
                         </div>
                         <div class="col-md-6">
                             <label class="form-label">Agent Assign</label>
                             <select name="assigned_agent_id" class="form-select">
                                 <option value="">-- Select Agent --</option>
                                 @foreach ($agents as $agent)
                                     <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                 @endforeach
                             </select>
                         </div>

                         <div class="col-md-6">
                             <label class="form-label">Category</label>
                             <select name="category" class="form-select">
                                 <option value="">-- Select Category --</option>
                                 @foreach (\App\Models\Lead::CATEGORIES as $key => $label)
                                     <option value="{{ $key }}">{{ $label }}</option>
                                 @endforeach
                             </select>
                         </div>
                         <div class="col-md-6">
                             <label class="form-label">Service Interest</label>
                             <input type="text" name="service_interest" class="form-control" list="serviceInterestOptions" placeholder="e.g. Highlights, Hydra Facial">
                         </div>

                         <div class="col-md-6">
                             <label class="form-label">Booking Status</label>
                             <input type="text" name="booking_status" class="form-control" placeholder="e.g. Yes, Confirmed">
                         </div>
                         <div class="col-md-6">
                             <label class="form-label">Correction Done</label>
                             <select name="correction_done" class="form-select">
                                 <option value="">-- Select --</option>
                                 @foreach (\App\Models\Lead::CORRECTION_STATUSES as $key => $label)
                                     <option value="{{ $key }}">{{ $label }}</option>
                                 @endforeach
                             </select>
                         </div>

                         <div class="col-md-6">
                             <label class="form-label">Next Follow-up Date</label>
                             <input type="date" name="next_followup_date" class="form-control">
                         </div>

                         <div class="col-12">
                             <label class="form-label">Customer Remarks</label>
                             <textarea name="customer_remarks" class="form-control" rows="3"></textarea>
                         </div>
                     </div>
                 </div>

                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                     <button type="submit" class="btn btn-primary">Save Lead</button>
                 </div>
             </form>
         </div>
     </div>
 </div>

 <datalist id="serviceInterestOptions">
     @foreach ($services ?? [] as $serviceName)
         <option value="{{ $serviceName }}"></option>
     @endforeach
 </datalist>
