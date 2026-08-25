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
                             <label class="form-label">Customer Name</label>
                             <input type="text" name="name" class="form-control">
                         </div>
                         <div class="col-md-6">
                             <label class="form-label">Phone Number</label>
                             <input type="text" name="phone" class="form-control" required>
                         </div>
                         <div class="col-md-6">
                             <label class="form-label">Assigned Agent</label>
                             <select name="assigned_agent_id" class="form-select">
                                 <option value="">-- Select Agent --</option>
                                 @foreach ($agents as $agent)
                                     <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                 @endforeach
                             </select>
                         </div>

                         <div class="col-md-6">
                             <label class="form-label">Lead Source</label>
                             <input type="text" name="lead_source" class="form-control">
                         </div>
                         <div class="col-md-6">
                             <label class="form-label">Follow-up Date</label>
                             <input type="date" name="followup_date" class="form-control">
                         </div>
                         <div class="col-12">
                             <label class="form-label">Notes</label>
                             <textarea name="notes" class="form-control" rows="3"></textarea>
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
