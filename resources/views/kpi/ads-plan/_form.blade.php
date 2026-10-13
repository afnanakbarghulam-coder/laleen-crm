{{-- Shared Add/Edit modal for Monthly Ad rows. JS toggles it between
     "create" (form posts to ads-plan.store) and "edit" (posts to
     ads-plan.update for the clicked card). Phase itself is changed from
     the Kanban card's inline select, not from this form. --}}
<div class="modal fade" id="monthlyAdModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" id="monthlyAdForm" action="{{ route('kpi.ads-plan.store') }}">
                @csrf
                <input type="hidden" name="_method" id="monthlyAdFormMethod">

                <div class="modal-header">
                    <h5 class="modal-title" id="monthlyAdModalTitle">Add Ad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-bold">Phase 1: The Brief</h6>
                    <hr class="mt-1">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">Ad Name</label>
                            <input type="text" name="ad_name" id="maAdName" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Person Responsible</label>
                            <input type="text" name="person_responsible" id="maPersonResponsible" class="form-control" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Pre-filled Message</label>
                            <textarea name="pre_filled_message" id="maPreFilledMessage" class="form-control" rows="2" placeholder="Caption / copy ready to paste when the ad goes live"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Performance Target</label>
                            <input type="text" name="performance_target" id="maPerformanceTarget" class="form-control" placeholder="e.g. 50 leads at QAR 15 CPL">
                        </div>
                    </div>

                    <h6 class="fw-bold">Phase 2: Production</h6>
                    <hr class="mt-1">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Shoot Date</label>
                            <input type="date" name="shoot_date" id="maShootDate" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Team</label>
                            <input type="text" name="team" id="maTeam" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Raw Clips Date</label>
                            <input type="date" name="raw_clips_date" id="maRawClipsDate" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Editing Deadline</label>
                            <input type="date" name="editing_deadline" id="maEditingDeadline" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Approved By</label>
                            <input type="text" name="approved_by" id="maApprovedBy" class="form-control">
                        </div>
                    </div>

                    <h6 class="fw-bold">Phase 3: Live Math</h6>
                    <hr class="mt-1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Live Date</label>
                            <input type="date" name="live_date" id="maLiveDate" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Budget (QAR)</label>
                            <input type="number" step="0.01" min="0" name="total_budget" id="maTotalBudget" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.resetMonthlyAdForm = function () {
        document.getElementById('monthlyAdModalTitle').innerText = 'Add Ad';
        document.getElementById('monthlyAdForm').action = '{{ route('kpi.ads-plan.store') }}';
        document.getElementById('monthlyAdFormMethod').value = '';
        document.getElementById('monthlyAdForm').reset();
    };

    window.editMonthlyAd = function (ad) {
        resetMonthlyAdForm();

        document.getElementById('monthlyAdModalTitle').innerText = 'Edit Ad';
        document.getElementById('monthlyAdForm').action = `/kpis/ads-plan/${ad.id}`;
        document.getElementById('monthlyAdFormMethod').value = 'PUT';

        document.getElementById('maAdName').value = ad.ad_name || '';
        document.getElementById('maPersonResponsible').value = ad.person_responsible || '';
        document.getElementById('maPreFilledMessage').value = ad.pre_filled_message || '';
        document.getElementById('maPerformanceTarget').value = ad.performance_target || '';
        document.getElementById('maShootDate').value = ad.shoot_date ? ad.shoot_date.slice(0, 10) : '';
        document.getElementById('maTeam').value = ad.team || '';
        document.getElementById('maRawClipsDate').value = ad.raw_clips_date ? ad.raw_clips_date.slice(0, 10) : '';
        document.getElementById('maEditingDeadline').value = ad.editing_deadline ? ad.editing_deadline.slice(0, 10) : '';
        document.getElementById('maApprovedBy').value = ad.approved_by || '';
        document.getElementById('maLiveDate').value = ad.live_date ? ad.live_date.slice(0, 10) : '';
        document.getElementById('maTotalBudget').value = ad.total_budget || '';

        new bootstrap.Modal(document.getElementById('monthlyAdModal')).show();
    };

    document.querySelectorAll('.monthly-ad-edit-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            editMonthlyAd(JSON.parse(this.dataset.ad));
        });
    });
</script>
