<!-- Customer Profile Modal -->
<div class="modal fade" id="customerProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Customer Profile</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Customer Info -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <span id="profileName"></span></p>
                        <p><strong>Phone:</strong> <span id="profilePhone"></span></p>
                        <p><strong>Total Visits:</strong> <span id="profileVisits"></span></p>
                        <p><strong>First Visit:</strong> <span id="profileFirstVisit"></span></p>
                        <p id="lastVisitRow"><strong>Last Visit:</strong> <span id="profileLastVisit"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Services Taken:</strong> <span id="profileServices"></span></p>
                        <p><strong>Lifetime Revenue (QAR):</strong> <span id="profileRevenue"></span></p>
                    </div>
                </div>

                <hr>

                <!-- Appointments Table -->
                <h6>Appointments</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Service</th>
                                <th>Price (QAR)</th>
                                <th>Branch</th>
                                <th>Agent</th>
                            </tr>
                        </thead>
                        <tbody id="profileAppointments"></tbody>
                    </table>
                </div>

            </div>
            <div class="modal-footer">
                <a href="#" id="profileFullLink" class="btn btn-primary d-none">
                    <i class="bx bx-user-circle"></i> View Full Profile
                </a>
            </div>
        </div>
    </div>
</div>
