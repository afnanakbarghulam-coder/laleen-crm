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
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Ad Name</label>
                            <input type="text" name="ad_name" id="maAdName" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Person Responsible</label>
                            <select name="person_responsible" id="maPersonResponsible" class="form-select" required>
                                <option value="">— Select —</option>
                                @foreach ($staff as $member)
                                    <option value="{{ $member->name }}">{{ $member->name }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" id="maPersonResponsibleHidden" value="">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Offer</label>
                            <textarea name="offer" id="maOffer" class="form-control" rows="2" placeholder="What's the offer this ad promotes?"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Budget (QAR)</label>
                            <input type="number" step="0.01" min="0" name="total_budget" id="maTotalBudget" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Pre-filled Message</label>
                            <textarea name="pre_filled_message" id="maPreFilledMessage" class="form-control" rows="2" placeholder="Caption / copy ready to paste when the ad goes live"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Live Date</label>
                            <input type="date" name="live_date" id="maLiveDate" class="form-control">
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-none" id="maProductionSection">
                        <h6 class="fw-bold">Production Deadlines</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Raw Data Deadline</label>
                                <input type="date" name="raw_data_deadline" id="maRawDataDeadline" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Final Creative Deadline</label>
                                <input type="date" name="final_creative_deadline" id="maFinalCreativeDeadline" class="form-control">
                            </div>
                        </div>

                        <h6 class="fw-bold mt-4">Quality Control — required before Testing</h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Creative Asset Link</label>
                                <input type="text" name="creative_asset_link" id="maCreativeAssetLink" class="form-control" placeholder="Link to the final creative asset (Drive, Frame.io, etc.)">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mb-1">
                                        <input type="checkbox" name="chk_hook" id="maChkHook" class="form-check-input" value="1">
                                        <label for="maChkHook" class="form-check-label">1. Hook</label>
                                    </div>
                                    <input type="text" name="txt_hook" id="maTxtHook" class="form-control form-control-sm bg-gray-900 border-gray-700 text-gray-300 focus:border-rose-400" placeholder="Describe the hook...">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mb-1">
                                        <input type="checkbox" name="chk_value" id="maChkValue" class="form-check-input" value="1">
                                        <label for="maChkValue" class="form-check-label">2. Value</label>
                                    </div>
                                    <input type="text" name="txt_value" id="maTxtValue" class="form-control form-control-sm bg-gray-900 border-gray-700 text-gray-300 focus:border-rose-400" placeholder="Describe the value...">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mb-1">
                                        <input type="checkbox" name="chk_risk" id="maChkRisk" class="form-check-input" value="1">
                                        <label for="maChkRisk" class="form-check-label">3. Risk</label>
                                    </div>
                                    <input type="text" name="txt_risk" id="maTxtRisk" class="form-control form-control-sm bg-gray-900 border-gray-700 text-gray-300 focus:border-rose-400" placeholder="Describe the risk reversal...">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mb-1">
                                        <input type="checkbox" name="chk_cta" id="maChkCta" class="form-check-input" value="1">
                                        <label for="maChkCta" class="form-check-label">4. CTA</label>
                                    </div>
                                    <input type="text" name="txt_cta" id="maTxtCta" class="form-control form-control-sm bg-gray-900 border-gray-700 text-gray-300 focus:border-rose-400" placeholder="Describe the call to action...">
                                </div>
                            </div>
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
    /** Brief fields read-only'd and Person Responsible locked once an ad has left Planning. */
    const BRIEF_LOCK_FIELD_IDS = ['maAdName', 'maOffer', 'maPreFilledMessage', 'maTotalBudget', 'maLiveDate'];

    function setBriefLocked(locked) {
        BRIEF_LOCK_FIELD_IDS.forEach(id => {
            document.getElementById(id).readOnly = locked;
        });

        const select = document.getElementById('maPersonResponsible');
        const hidden = document.getElementById('maPersonResponsibleHidden');
        select.disabled = locked;
        if (locked) {
            select.removeAttribute('name');
            hidden.setAttribute('name', 'person_responsible');
        } else {
            select.setAttribute('name', 'person_responsible');
            hidden.removeAttribute('name');
        }

        document.getElementById('maProductionSection').classList.toggle('d-none', !locked);
    }

    window.resetMonthlyAdForm = function () {
        document.getElementById('monthlyAdModalTitle').innerText = 'Add Ad';
        document.getElementById('monthlyAdForm').action = '{{ route('kpi.ads-plan.store') }}';
        document.getElementById('monthlyAdFormMethod').value = '';
        document.getElementById('monthlyAdForm').reset();
        setBriefLocked(false);
    };

    window.editMonthlyAd = function (ad) {
        resetMonthlyAdForm();

        document.getElementById('monthlyAdModalTitle').innerText = 'Edit Ad';
        document.getElementById('monthlyAdForm').action = `/kpis/ads-plan/${ad.id}`;
        document.getElementById('monthlyAdFormMethod').value = 'PUT';

        document.getElementById('maAdName').value = ad.ad_name || '';
        document.getElementById('maPersonResponsible').value = ad.person_responsible || '';
        document.getElementById('maPersonResponsibleHidden').value = ad.person_responsible || '';
        document.getElementById('maOffer').value = ad.offer || '';
        document.getElementById('maTotalBudget').value = ad.total_budget || '';
        document.getElementById('maPreFilledMessage').value = ad.pre_filled_message || '';
        document.getElementById('maLiveDate').value = ad.live_date ? ad.live_date.slice(0, 10) : '';
        document.getElementById('maRawDataDeadline').value = ad.raw_data_deadline ? ad.raw_data_deadline.slice(0, 10) : '';
        document.getElementById('maFinalCreativeDeadline').value = ad.final_creative_deadline ? ad.final_creative_deadline.slice(0, 10) : '';
        document.getElementById('maCreativeAssetLink').value = ad.creative_asset_link || '';
        document.getElementById('maChkHook').checked = !!ad.chk_hook;
        document.getElementById('maChkValue').checked = !!ad.chk_value;
        document.getElementById('maChkRisk').checked = !!ad.chk_risk;
        document.getElementById('maChkCta').checked = !!ad.chk_cta;
        document.getElementById('maTxtHook').value = ad.txt_hook || '';
        document.getElementById('maTxtValue').value = ad.txt_value || '';
        document.getElementById('maTxtRisk').value = ad.txt_risk || '';
        document.getElementById('maTxtCta').value = ad.txt_cta || '';

        setBriefLocked(ad.phase !== 'Planning');

        new bootstrap.Modal(document.getElementById('monthlyAdModal')).show();
    };

    document.querySelectorAll('.monthly-ad-edit-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            editMonthlyAd(JSON.parse(this.dataset.ad));
        });
    });
</script>
