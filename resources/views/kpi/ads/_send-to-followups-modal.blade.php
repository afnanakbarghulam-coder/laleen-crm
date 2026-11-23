{{-- Compact confirmation modal before pushing an Ad Lead to Lead Management (Follow-Ups). --}}
@php
    $defaultSendFollowUpDate = \App\Models\Lead::salonToday()->addDay()->format('Y-m-d');
@endphp

<style>
    #sendToFollowUpsModal.modal.show {
        opacity: 1 !important;
    }

    #sendToFollowUpsModal .modal-content {
        background: #241e1c !important;
        background-color: #241e1c !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        border: 1px solid rgba(217, 143, 131, 0.24) !important;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.72) !important;
        opacity: 1 !important;
    }

    #sendToFollowUpsModal .modal-header,
    #sendToFollowUpsModal .modal-body,
    #sendToFollowUpsModal .modal-footer {
        background: #241e1c !important;
        background-color: #241e1c !important;
    }

    .send-followup-summary-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 10px;
        padding: 10px 12px;
        border-radius: 10px;
        background: #1b1614;
        border: 1px solid rgba(217, 143, 131, 0.16);
        font-size: 12.5px;
        color: #f2e6e2;
        margin-bottom: 14px;
    }

    .send-followup-summary-sep {
        color: rgba(255, 255, 255, 0.35);
    }

    .send-followup-cat-pill {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        background: rgba(217, 143, 131, 0.16);
        border: 1px solid rgba(217, 143, 131, 0.35);
        color: #f4c4bc;
    }
</style>

<div class="modal fade" id="sendToFollowUpsModal" tabindex="-1" aria-labelledby="sendToFollowUpsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #241e1c !important; background-color: #241e1c !important; backdrop-filter: none !important; -webkit-backdrop-filter: none !important;">
            <form id="sendToFollowUpsForm" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="sendToFollowUpsModalTitle">Send to Follow-Ups</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="send-followup-summary-bar" id="sendFollowUpSummaryBar">
                        <strong id="sendFollowUpSummaryName">—</strong>
                        <span class="send-followup-summary-sep">&middot;</span>
                        <span class="text-muted" id="sendFollowUpSummaryPhone">—</span>
                        <span class="send-followup-cat-pill" id="sendFollowUpSummaryCategory">—</span>
                    </div>

                    <div id="sendFollowUpErrorAlert" class="alert alert-danger py-2 px-3 small d-none" role="alert"></div>

                    <div class="mb-3">
                        <label class="form-label" for="sendFollowUpDate">Follow-Up Date <span class="text-danger">*</span></label>
                        <input
                            type="date"
                            name="follow_up_date"
                            id="sendFollowUpDate"
                            class="form-control"
                            value="{{ $defaultSendFollowUpDate }}"
                            data-default-date="{{ $defaultSendFollowUpDate }}"
                            required
                        >
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="sendFollowUpRemarks">Remarks</label>
                        <textarea
                            name="remarks"
                            id="sendFollowUpRemarks"
                            class="form-control"
                            rows="3"
                            maxlength="2000"
                            placeholder="e.g. Will confirm tomorrow afternoon"
                        ></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="sendFollowUpCancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="sendFollowUpSubmitBtn">
                        <span id="sendFollowUpSubmitLabel">Push to Follow-Ups</span>
                        <span id="sendFollowUpSubmitSpinner" class="d-none">
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            Pushing...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        const modalEl = document.getElementById('sendToFollowUpsModal');
        const formEl = document.getElementById('sendToFollowUpsForm');
        const dateEl = document.getElementById('sendFollowUpDate');
        const remarksEl = document.getElementById('sendFollowUpRemarks');
        const nameEl = document.getElementById('sendFollowUpSummaryName');
        const phoneEl = document.getElementById('sendFollowUpSummaryPhone');
        const catEl = document.getElementById('sendFollowUpSummaryCategory');
        const errorEl = document.getElementById('sendFollowUpErrorAlert');
        const submitBtn = document.getElementById('sendFollowUpSubmitBtn');
        const submitLabel = document.getElementById('sendFollowUpSubmitLabel');
        const submitSpinner = document.getElementById('sendFollowUpSubmitSpinner');

        if (!modalEl || !formEl) return;

        let activeEntryId = null;
        let activeSendUrl = null;
        let activeTriggerBtn = null;
        let activeOnSuccess = null;
        let isSubmitting = false;

        function setSubmitting(loading) {
            isSubmitting = loading;
            if (submitBtn) submitBtn.disabled = loading;
            if (submitLabel) submitLabel.classList.toggle('d-none', loading);
            if (submitSpinner) submitSpinner.classList.toggle('d-none', !loading);
        }

        function showError(message) {
            if (!errorEl) return;
            errorEl.textContent = message || 'Could not sync to Follow-Ups. Please try again.';
            errorEl.classList.remove('d-none');
        }

        function clearError() {
            if (!errorEl) return;
            errorEl.textContent = '';
            errorEl.classList.add('d-none');
        }

        window.openSendToFollowUpsModal = function (triggerOrOptions) {
            let entry = {};
            const isDomElement = triggerOrOptions && typeof triggerOrOptions === 'object' && triggerOrOptions.nodeType === 1;

            if (isDomElement) {
                try {
                    entry = JSON.parse(triggerOrOptions.dataset.entry || '{}');
                } catch (e) {
                    entry = {};
                }
                activeSendUrl = triggerOrOptions.dataset.sendUrl || '';
                activeTriggerBtn = triggerOrOptions;
                activeOnSuccess = null;
            } else if (triggerOrOptions && typeof triggerOrOptions === 'object') {
                entry = triggerOrOptions.entry || {};
                activeSendUrl = triggerOrOptions.sendUrl || '';
                activeTriggerBtn = null;
                activeOnSuccess = typeof triggerOrOptions.onSuccess === 'function' ? triggerOrOptions.onSuccess : null;
            } else {
                activeSendUrl = '';
                activeTriggerBtn = null;
                activeOnSuccess = null;
            }

            activeEntryId = entry.id || null;

            formEl.action = activeSendUrl;
            clearError();
            setSubmitting(false);

            if (nameEl) nameEl.textContent = entry.customer_name || 'Ad Lead Client';
            if (phoneEl) phoneEl.textContent = entry.phone || '—';
            if (catEl) catEl.textContent = entry.category || 'Ad Lead';

            const defaultDate = dateEl ? (dateEl.dataset.defaultDate || '') : '';
            if (dateEl) {
                dateEl.value = entry.follow_up_date ? String(entry.follow_up_date).slice(0, 10) : defaultDate;
            }

            // Prefer the live table row remarks if edited previously, else payload remarks
            const remarksCell = activeEntryId ? document.querySelector(`[data-ad-lead-remarks-cell="${activeEntryId}"]`) : null;
            let liveRemarks = entry.remarks || '';
            if (remarksCell) {
                const cellText = (remarksCell.textContent || '').trim();
                liveRemarks = cellText === '—' ? '' : cellText;
            }
            if (remarksEl) {
                remarksEl.value = liveRemarks;
            }

            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        };

        // Bind directly on each button (not document) because the parent <td @click.stop> stops bubbling to document.
        document.querySelectorAll('.ad-lead-send-followup-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                window.openSendToFollowUpsModal(btn);
            });
        });

        if (dateEl) {
            dateEl.addEventListener('click', function () {
                if (typeof dateEl.showPicker === 'function') {
                    try { dateEl.showPicker(); } catch (err) {}
                }
            });
        }

        formEl.addEventListener('submit', function (e) {
            e.preventDefault();
            if (isSubmitting || !activeSendUrl) return;

            clearError();

            const followUpDateVal = (dateEl?.value || '').trim();
            const remarksVal = (remarksEl?.value || '').trim();

            if (!followUpDateVal) {
                showError('Please choose a Follow-Up Date.');
                dateEl?.focus();
                return;
            }

            setSubmitting(true);

            fetch(activeSendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify({
                    follow_up_date: followUpDateVal,
                    remarks: remarksVal,
                }),
            })
            .then(async function (res) {
                let data = null;
                try {
                    data = await res.json();
                } catch (err) {}

                if (!res.ok || !data || !data.success) {
                    const firstFieldError = data && data.errors
                        ? Object.values(data.errors).flat()[0]
                        : null;
                    throw new Error(firstFieldError || (data && data.message) || 'Could not push lead to Follow-Ups.');
                }

                // 1. Immediately update Remarks cell on the Ads Log row
                if (activeEntryId) {
                    const remarksCell = document.querySelector(`[data-ad-lead-remarks-cell="${activeEntryId}"]`);
                    if (remarksCell) {
                        remarksCell.textContent = (data.remarks && String(data.remarks).trim() !== '') ? data.remarks : '—';
                    }

                    // 2. Immediately update Status / Branch cell with clickable "In Follow-Ups · Due <date>" pill
                    const statusCell = document.querySelector(`[data-ad-lead-status-cell="${activeEntryId}"]`);
                    if (statusCell && data.follow_up_url) {
                        statusCell.innerHTML = '';
                        const link = document.createElement('a');
                        link.href = data.follow_up_url;
                        link.className = 'ad-followup-pill is-active';
                        link.title = 'Open synced record in Lead Management';
                        link.addEventListener('click', function (ev) { ev.stopPropagation(); });

                        const icon = document.createElement('i');
                        icon.className = 'bx bx-calendar-check';
                        link.appendChild(icon);
                        link.appendChild(document.createTextNode(' ' + (data.follow_up_pill_text || 'In Follow-Ups')));
                        statusCell.appendChild(link);
                    }

                    // 3. Keep the Edit button's JSON payload synchronized so editing later shows updated remarks
                    const editBtn = document.querySelector(`[data-ad-lead-edit-btn="${activeEntryId}"]`);
                    if (editBtn && editBtn.dataset.entry) {
                        try {
                            const updatedPayload = JSON.parse(editBtn.dataset.entry);
                            updatedPayload.remarks = data.remarks || '';
                            updatedPayload.follow_up_date = data.follow_up_date || null;
                            updatedPayload.lead_id = data.lead_id || null;
                            updatedPayload.follow_up_url = data.follow_up_url || null;
                            updatedPayload.status = data.status || 'follow_up';
                            updatedPayload.assigned_agent_id = data.assigned_agent_id || null;
                            editBtn.dataset.entry = JSON.stringify(updatedPayload);
                        } catch (err) {}
                    }
                }

                // 4. Remove the "Send to Follow-Ups" button on that row (now linked via status pill)
                if (activeTriggerBtn) {
                    activeTriggerBtn.remove();
                }

                // 5. Close modal cleanly
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                if (typeof activeOnSuccess === 'function') {
                    activeOnSuccess(data);
                }

                window.dispatchEvent(new CustomEvent('ad-lead-sent-to-followups', { detail: data }));
            })
            .catch(function (err) {
                showError(err && err.message ? err.message : 'Network error while pushing to Follow-Ups.');
            })
            .finally(function () {
                setSubmitting(false);
            });
        });
    })();
</script>
