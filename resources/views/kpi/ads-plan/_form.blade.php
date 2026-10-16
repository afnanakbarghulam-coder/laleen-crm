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
                        <div class="col-md-5">
                            <label class="form-label">Ad Name</label>
                            <input type="text" name="ad_name" id="maAdName" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Plan Month</label>
                            <input type="month" name="plan_month" id="maPlanMonth" class="form-control" required>
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
                        <div class="col-md-6">
                            <label class="form-label">Target Branch</label>
                            <select name="target_branch" id="maTargetBranch" class="form-select">
                                @foreach (\App\Models\KpiMonthlyAd::BRANCHES as $branch)
                                    <option value="{{ $branch }}">{{ $branch }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" id="maTargetBranchHidden" value="">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Service Ticket / Offer Price (QAR)</label>
                            <input type="number" step="0.01" min="0" name="offer_price" id="maOfferPrice" class="form-control" placeholder="e.g. 50 or 300">
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-none" id="maProductionSection">
                        <h6 class="fw-bold">Production Deadlines</h6>
                        <div class="mb-3">
                            <label class="form-label d-block">Raw Data Status</label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input type="radio" class="form-check-input" name="raw_data_status" id="maRawDataAvailable" value="available">
                                    <label class="form-check-label" for="maRawDataAvailable">✔ Available Now</label>
                                </div>
                                <div class="form-check">
                                    <input type="radio" class="form-check-input" name="raw_data_status" id="maRawDataScheduled" value="scheduled" checked>
                                    <label class="form-check-label" for="maRawDataScheduled">📅 Set Deadline Date</label>
                                </div>
                            </div>
                            <div class="mt-2 small d-none" id="maRawDataAvailableNote" style="color: #10b981;">Raw footage is ready for editing</div>
                            <div class="mt-2" id="maRawDataDeadlineWrap">
                                <label class="form-label">Raw Data Deadline</label>
                                <input type="date" name="raw_data_deadline" id="maRawDataDeadline" class="form-control">
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Final Creative Deadline</label>
                                <input type="date" name="final_creative_deadline" id="maFinalCreativeDeadline" class="form-control">
                            </div>
                        </div>

                        <h6 class="fw-bold mt-4">3 Named Creatives — required before Testing</h6>
                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label class="form-label">Creative 1 Name</label>
                                <input type="text" name="creative_1_name" id="maCreative1Name" class="form-control" placeholder="e.g. C1 - Hook A (Urdu Voiceover)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Creative 1 Asset Link</label>
                                <input type="text" name="creative_asset_link" id="maCreativeAssetLink" class="form-control" placeholder="Link to Creative 1">
                            </div>
                        </div>
                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label class="form-label">Creative 2 Name</label>
                                <input type="text" name="creative_2_name" id="maCreative2Name" class="form-control" placeholder="e.g. C2 - Before/After Reel">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Creative 2 Asset Link</label>
                                <input type="text" name="creative_asset_link_2" id="maCreativeAssetLink2" class="form-control" placeholder="Link to Creative 2">
                            </div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Creative 3 Name</label>
                                <input type="text" name="creative_3_name" id="maCreative3Name" class="form-control" placeholder="e.g. C3 - Offer Breakdown">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Creative 3 Asset Link</label>
                                <input type="text" name="creative_asset_link_3" id="maCreativeAssetLink3" class="form-control" placeholder="Link to Creative 3">
                            </div>
                        </div>
                        <div class="p-3 rounded mb-3" style="background-color: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05);">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input type="checkbox" name="chk_hook" id="maChkHook" class="form-check-input" value="1">
                                        <label for="maChkHook" class="form-check-label">1. Hook</label>
                                    </div>
                                    <input type="text" name="txt_hook" id="maTxtHook" class="form-control form-control-sm" style="border-color: rgba(255, 255, 255, 0.1);" placeholder="Describe the hook...">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input type="checkbox" name="chk_value" id="maChkValue" class="form-check-input" value="1">
                                        <label for="maChkValue" class="form-check-label">2. Value</label>
                                    </div>
                                    <input type="text" name="txt_value" id="maTxtValue" class="form-control form-control-sm" style="border-color: rgba(255, 255, 255, 0.1);" placeholder="Describe the value...">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input type="checkbox" name="chk_risk" id="maChkRisk" class="form-check-input" value="1">
                                        <label for="maChkRisk" class="form-check-label">3. Risk</label>
                                    </div>
                                    <input type="text" name="txt_risk" id="maTxtRisk" class="form-control form-control-sm" style="border-color: rgba(255, 255, 255, 0.1);" placeholder="Describe the risk reversal...">
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input type="checkbox" name="chk_cta" id="maChkCta" class="form-check-input" value="1">
                                        <label for="maChkCta" class="form-check-label">4. CTA</label>
                                    </div>
                                    <input type="text" name="txt_cta" id="maTxtCta" class="form-control form-control-sm" style="border-color: rgba(255, 255, 255, 0.1);" placeholder="Describe the call to action...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-none" id="maTestingSection">
                        <h6 class="fw-bold">7-Day Creative &amp; Funnel Evaluation</h6>
                        <div class="small p-2 rounded mb-3 d-flex flex-wrap gap-3 align-items-center" style="background-color: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05);">
                            <span>Prod Started: <strong id="maTrailProdStarted">—</strong></span>
                            <span>Test Started: <strong id="maTrailTestStarted">—</strong></span>
                            <span class="d-flex align-items-center gap-2">
                                Test Ends:
                                <input type="date" name="testing_ends_at" id="maTestingEndsAt" class="form-control form-control-sm" style="width: auto;">
                            </span>
                        </div>
                        <div class="p-3 rounded" style="background-color: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05);">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Winning Creative</label>
                                    <select name="winning_creative" id="maWinningCreative" class="form-select">
                                        <option value=""></option>
                                        <option value="Creative 1" data-base-label="Creative 1">Creative 1</option>
                                        <option value="Creative 2" data-base-label="Creative 2">Creative 2</option>
                                        <option value="Creative 3" data-base-label="Creative 3">Creative 3</option>
                                        <option value="None">None</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Cost Per WhatsApp Msg (QAR)</label>
                                    <input type="number" step="0.01" min="0" name="cost_per_message" id="maCostPerMessage" class="form-control">
                                    <div class="form-text" id="maCostPerMessageHelper"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Funnel Verdict</label>
                                    <select name="sales_verdict" id="maSalesVerdict" class="form-select">
                                        <option value="">-- Select Funnel Verdict --</option>
                                        <option value="High Messages + Sales (Scale)">High Messages + Sales (Scale)</option>
                                        <option value="Audience Only / No Sales">Audience Only / No Sales</option>
                                        <option value="Too Expensive / Dead">Too Expensive / Dead</option>
                                        <option value="__custom__">+ Add Custom Verdict Category...</option>
                                    </select>
                                    <div id="maCustomVerdictWrap" class="mt-2 d-none">
                                        <input type="text" class="form-control form-control-sm" id="maSalesVerdictCustom" name="sales_verdict_custom" placeholder="Enter custom verdict category...">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Audience &amp; Funnel Notes</label>
                                    <textarea name="testing_notes" id="maTestingNotes" class="form-control" rows="1" placeholder="Which audience worked? Why did C3 get clicks but no sales?"></textarea>
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
    /** Brief fields read-only'd and Person Responsible / Target Branch locked once an ad has left Planning. */
    const BRIEF_LOCK_FIELD_IDS = ['maAdName', 'maPlanMonth', 'maOffer', 'maPreFilledMessage', 'maTotalBudget', 'maLiveDate', 'maOfferPrice'];

    const PRESET_SALES_VERDICTS = ['High Messages + Sales (Scale)', 'Audience Only / No Sales', 'Too Expensive / Dead'];

    function lockSelectWithHidden(selectId, hiddenId, name, locked) {
        const select = document.getElementById(selectId);
        const hidden = document.getElementById(hiddenId);
        select.disabled = locked;
        if (locked) {
            hidden.value = select.value;
            select.removeAttribute('name');
            hidden.setAttribute('name', name);
        } else {
            select.setAttribute('name', name);
            hidden.removeAttribute('name');
        }
    }

    function setBriefLocked(locked) {
        BRIEF_LOCK_FIELD_IDS.forEach(id => {
            document.getElementById(id).readOnly = locked;
        });

        lockSelectWithHidden('maPersonResponsible', 'maPersonResponsibleHidden', 'person_responsible', locked);
        lockSelectWithHidden('maTargetBranch', 'maTargetBranchHidden', 'target_branch', locked);

        document.getElementById('maProductionSection').classList.toggle('d-none', !locked);
    }

    function updateCostPerMessageHelper() {
        const cost = parseFloat(document.getElementById('maCostPerMessage').value);
        const offerPrice = parseFloat(document.getElementById('maOfferPrice').value);
        const helper = document.getElementById('maCostPerMessageHelper');

        if (!isNaN(cost) && !isNaN(offerPrice) && offerPrice > 0) {
            helper.innerText = `Msg Cost is ${((cost / offerPrice) * 100).toFixed(1)}% of Service Ticket`;
        } else {
            helper.innerText = '';
        }
    }

    document.getElementById('maCostPerMessage').addEventListener('input', updateCostPerMessageHelper);
    document.getElementById('maOfferPrice').addEventListener('input', updateCostPerMessageHelper);

    function updateRawDataUI() {
        const available = document.getElementById('maRawDataAvailable').checked;
        document.getElementById('maRawDataAvailableNote').classList.toggle('d-none', !available);
        document.getElementById('maRawDataDeadlineWrap').classList.toggle('d-none', available);
    }

    document.getElementById('maRawDataAvailable').addEventListener('change', updateRawDataUI);
    document.getElementById('maRawDataScheduled').addEventListener('change', updateRawDataUI);

    function updateSalesVerdictUI() {
        document.getElementById('maCustomVerdictWrap').classList.toggle('d-none', document.getElementById('maSalesVerdict').value !== '__custom__');
    }

    document.getElementById('maSalesVerdict').addEventListener('change', updateSalesVerdictUI);

    function resetWinningCreativeLabels() {
        document.querySelectorAll('#maWinningCreative option[data-base-label]').forEach(opt => {
            opt.textContent = opt.dataset.baseLabel;
        });
    }

    window.resetMonthlyAdForm = function () {
        document.getElementById('monthlyAdModalTitle').innerText = 'Add Ad';
        document.getElementById('monthlyAdForm').action = '{{ route('kpi.ads-plan.store') }}';
        document.getElementById('monthlyAdFormMethod').value = '';
        document.getElementById('monthlyAdForm').reset();
        document.getElementById('maPlanMonth').value = new Date().toISOString().slice(0, 7);
        document.getElementById('maRawDataScheduled').checked = true;
        updateRawDataUI();
        resetWinningCreativeLabels();
        updateSalesVerdictUI();
        document.getElementById('maSalesVerdictCustom').value = '';
        document.getElementById('maTrailProdStarted').textContent = '—';
        document.getElementById('maTrailTestStarted').textContent = '—';
        document.getElementById('maTestingEndsAt').value = '';
        setBriefLocked(false);
        document.getElementById('maTestingSection').classList.add('d-none');
        updateCostPerMessageHelper();
    };

    window.editMonthlyAd = function (ad) {
        resetMonthlyAdForm();

        document.getElementById('monthlyAdModalTitle').innerText = 'Edit Ad';
        document.getElementById('monthlyAdForm').action = `/kpis/ads-plan/${ad.id}`;
        document.getElementById('monthlyAdFormMethod').value = 'PUT';

        document.getElementById('maAdName').value = ad.ad_name || '';
        document.getElementById('maPlanMonth').value = ad.plan_month || new Date().toISOString().slice(0, 7);
        document.getElementById('maPersonResponsible').value = ad.person_responsible || '';
        document.getElementById('maPersonResponsibleHidden').value = ad.person_responsible || '';
        document.getElementById('maOffer').value = ad.offer || '';
        document.getElementById('maTotalBudget').value = ad.total_budget || '';
        document.getElementById('maPreFilledMessage').value = ad.pre_filled_message || '';
        document.getElementById('maLiveDate').value = ad.live_date || '';
        document.getElementById('maTargetBranch').value = ad.target_branch || 'All Branches';
        document.getElementById('maTargetBranchHidden').value = ad.target_branch || 'All Branches';
        document.getElementById('maOfferPrice').value = ad.offer_price || '';

        document.getElementById('maRawDataAvailable').checked = ad.raw_data_status === 'available';
        document.getElementById('maRawDataScheduled').checked = ad.raw_data_status !== 'available';
        updateRawDataUI();
        document.getElementById('maRawDataDeadline').value = ad.raw_data_deadline || '';
        document.getElementById('maFinalCreativeDeadline').value = ad.final_creative_deadline || '';

        document.getElementById('maCreative1Name').value = ad.creative_1_name || '';
        document.getElementById('maCreativeAssetLink').value = ad.creative_asset_link || '';
        document.getElementById('maCreative2Name').value = ad.creative_2_name || '';
        document.getElementById('maCreativeAssetLink2').value = ad.creative_asset_link_2 || '';
        document.getElementById('maCreative3Name').value = ad.creative_3_name || '';
        document.getElementById('maCreativeAssetLink3').value = ad.creative_asset_link_3 || '';

        document.getElementById('maChkHook').checked = !!ad.chk_hook;
        document.getElementById('maChkValue').checked = !!ad.chk_value;
        document.getElementById('maChkRisk').checked = !!ad.chk_risk;
        document.getElementById('maChkCta').checked = !!ad.chk_cta;
        document.getElementById('maTxtHook').value = ad.txt_hook || '';
        document.getElementById('maTxtValue').value = ad.txt_value || '';
        document.getElementById('maTxtRisk').value = ad.txt_risk || '';
        document.getElementById('maTxtCta').value = ad.txt_cta || '';

        document.getElementById('maTrailProdStarted').textContent = ad.production_started_at || '—';
        document.getElementById('maTrailTestStarted').textContent = ad.testing_started_at || '—';
        document.getElementById('maTestingEndsAt').value = ad.testing_ends_at || '';

        resetWinningCreativeLabels();
        const creativeNameByLabel = { 'Creative 1': ad.creative_1_name, 'Creative 2': ad.creative_2_name, 'Creative 3': ad.creative_3_name };
        document.querySelectorAll('#maWinningCreative option[data-base-label]').forEach(opt => {
            const name = creativeNameByLabel[opt.dataset.baseLabel];
            if (name) {
                opt.textContent = `${opt.dataset.baseLabel} — ${name}`;
            }
        });
        document.getElementById('maWinningCreative').value = ad.winning_creative || '';

        document.getElementById('maCostPerMessage').value = ad.cost_per_message || '';

        if (ad.sales_verdict && !PRESET_SALES_VERDICTS.includes(ad.sales_verdict)) {
            document.getElementById('maSalesVerdict').value = '__custom__';
            document.getElementById('maSalesVerdictCustom').value = ad.sales_verdict;
        } else {
            document.getElementById('maSalesVerdict').value = ad.sales_verdict || '';
            document.getElementById('maSalesVerdictCustom').value = '';
        }
        updateSalesVerdictUI();

        document.getElementById('maTestingNotes').value = ad.testing_notes || '';

        setBriefLocked(ad.phase !== 'Planning');
        document.getElementById('maTestingSection').classList.toggle('d-none', !['Testing', 'Scaling', 'Killed'].includes(ad.phase));
        updateCostPerMessageHelper();

        new bootstrap.Modal(document.getElementById('monthlyAdModal')).show();
    };

    document.querySelectorAll('.monthly-ad-edit-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            editMonthlyAd(JSON.parse(this.dataset.ad));
        });
    });
</script>
