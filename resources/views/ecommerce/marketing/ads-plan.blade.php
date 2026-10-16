@extends('layouts.app')
@section('title', 'Monthly Ads Plan')

@include('ecommerce._styles')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Monthly Ads Plan</h4>
            <p>Phased pipeline for every E-commerce ad &mdash; move a card through the gates right from the board.</p>
        </div>
        <div class="kpi-header-meta">
            @moduleEdit('ecommerce')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#monthlyAdModal" onclick="resetMonthlyAdForm()">
                    <i class="bx bx-plus me-1"></i> Add Ad
                </button>
            @endmoduleEdit
            <a href="{{ route('ecommerce.marketing') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Marketing</a>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2 px-3 rounded mb-3" style="background-color: rgba(255, 255, 255, 0.02); border: 1px solid rgba(217, 143, 131, 0.16);">
        <div class="d-flex flex-wrap align-items-center gap-3 small" style="color: var(--luxe-muted);">
            <span>Planning: <strong style="color: var(--luxe-ink);">{{ $adsByPhase['Planning']->count() }}</strong></span>
            <span>Production: <strong style="color: var(--luxe-ink);">{{ $adsByPhase['Production']->count() }}</strong></span>
            <span>Testing: <strong style="color: var(--luxe-ink);">{{ $adsByPhase['Testing']->count() }}</strong></span>
            <span>Scaling: <strong style="color: #10b981;">{{ $adsByPhase['Scaling']->count() }}</strong></span>
        </div>
        <span class="ms-auto small" style="color: rgba(255, 255, 255, 0.6);">
            Total Planned Budget: <strong style="color: rgba(255, 255, 255, 0.9);">PKR {{ number_format((float) ($totalPlannedBudget ?? 0), 0) }}</strong>
        </span>
    </div>

    <div class="table-responsive">
        <div class="row g-3 flex-nowrap" style="min-width: 1400px;">
            @foreach ($adsByPhase as $phase => $ads)
                <div class="col-md-3">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <strong>{{ $phase }}</strong>
                            <span class="badge bg-secondary">{{ $ads->count() }}</span>
                        </div>
                        <div class="card-body d-flex flex-column gap-2">
                            @forelse ($ads as $ad)
                                @php
                                    $deadline = null;
                                    if ($phase === 'Planning') {
                                        $deadline = $ad->live_date ? ['label' => 'Go Live', 'date' => $ad->live_date] : null;
                                    } elseif ($phase === 'Production') {
                                        if ($ad->raw_data_deadline && empty($ad->creative_asset_link)) {
                                            $deadline = ['label' => 'Raw Data Due', 'date' => $ad->raw_data_deadline];
                                        } else {
                                            $dueDate = $ad->final_creative_deadline ?? $ad->live_date;
                                            $deadline = $dueDate ? ['label' => 'Final Creative Due', 'date' => $dueDate] : null;
                                        }
                                    } elseif (in_array($phase, ['Testing', 'Scaling'], true)) {
                                        $deadline = $ad->live_date ? ['label' => 'Live', 'date' => $ad->live_date] : null;
                                    }

                                    $checklistCount = 4 - count($ad->missingChecklistPoints());
                                    $cpl = $ad->actualCpl();
                                    $cplHealthy = $ad->isCplHealthy();
                                    $variance = $ad->cplVariancePercent();
                                @endphp
                                <div class="card">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div class="fw-bold">{{ $ad->ad_name }}</div>
                                            @moduleEdit('ecommerce')
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-warning monthly-ad-edit-btn ec-ad-edit-btn"
                                                        data-ad='@json($ad->toEditPayload())'
                                                        title="Edit">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <form action="{{ route('ecommerce.marketing.ads-plan.destroy', $ad) }}" method="POST" onsubmit="return confirm('Delete this ad?')">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </div>
                                            @endmoduleEdit
                                        </div>
                                        <div class="small text-muted mt-1"><i class="bx bx-user me-1"></i>{{ $ad->person_responsible ?: 'Unassigned' }}</div>

                                        <div class="mt-2 d-flex flex-wrap gap-1">
                                            @if ($ad->total_budget !== null)
                                                <span class="badge bg-light text-dark">Budget: PKR {{ number_format((float) $ad->total_budget, 0) }}</span>
                                            @endif
                                            @if ($ad->offer)
                                                <span class="badge bg-light text-dark text-truncate" style="max-width: 190px;" title="{{ $ad->offer }}">{{ $ad->offer }}</span>
                                            @endif
                                        </div>

                                        <div class="mt-2">
                                            @if ($deadline)
                                                <span class="badge {{ $deadline['date']->isPast() ? 'bg-danger' : 'bg-warning text-dark' }}">
                                                    {{ $deadline['label'] }}: {{ $deadline['date']->format('d M Y') }}
                                                </span>
                                            @else
                                                <span class="badge bg-light text-dark">No deadline set</span>
                                            @endif
                                        </div>

                                        @if ($phase === 'Planning')
                                            @if ($ad->live_date)
                                                <div class="small mt-1" style="color: rgba(255, 255, 255, 0.5);">Live: {{ $ad->live_date->format('d M Y') }}</div>
                                            @endif
                                            @if (auth()->user()->name === $ad->person_responsible)
                                                <form action="{{ route('ecommerce.marketing.ads-plan.complete-planning', $ad) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm w-100 mt-2" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);">✔ Mark Phase 1 Completed</button>
                                                </form>
                                            @else
                                                <div class="text-muted text-center small mt-2 py-1 border rounded" style="border-color: #374151 !important;">Waiting for completion by assigned staff</div>
                                            @endif
                                        @elseif ($phase === 'Production')
                                            <div class="small mt-1" style="color: rgba(255, 255, 255, 0.5);">
                                                @if ($ad->raw_data_deadline)
                                                    Raw Due: {{ $ad->raw_data_deadline->format('d M Y') }}
                                                @endif
                                                @if ($ad->raw_data_deadline && $ad->final_creative_deadline)
                                                    &middot;
                                                @endif
                                                @if ($ad->final_creative_deadline)
                                                    Edit Due: {{ $ad->final_creative_deadline->format('d M Y') }}
                                                @endif
                                            </div>
                                            <div class="mt-2 d-flex gap-1 flex-wrap">
                                                <span class="badge {{ !empty($ad->creative_asset_link) ? 'bg-success' : 'bg-secondary' }}">
                                                    {{ !empty($ad->creative_asset_link) ? '✓ Asset Linked' : 'No Asset' }}
                                                </span>
                                                <span class="badge {{ $checklistCount === 4 ? 'bg-success' : 'bg-secondary' }}">
                                                    QC: {{ $checklistCount }}/4
                                                </span>
                                            </div>
                                            @moduleEdit('ecommerce')
                                                <form action="{{ route('ecommerce.marketing.ads-plan.complete-production', $ad) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm w-100 mt-2" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2);">🚀 Push to Testing</button>
                                                </form>
                                            @endmoduleEdit
                                        @elseif ($phase === 'Testing')
                                            <div class="mt-2 p-2 rounded" style="background-color: rgba(255, 255, 255, 0.02); border: 1px solid rgba(217, 143, 131, 0.16);">
                                                <div class="d-flex justify-content-between small">
                                                    <span style="color: rgba(255, 255, 255, 0.6);">Actual CPL</span>
                                                    <strong style="color: {{ $cplHealthy === null ? 'var(--luxe-ink)' : ($cplHealthy ? '#10b981' : '#dc2626') }};">
                                                        {{ $cpl === null ? '—' : 'PKR ' . number_format($cpl, 2) }}
                                                    </strong>
                                                </div>
                                                <div class="d-flex justify-content-between small mt-1">
                                                    <span style="color: rgba(255, 255, 255, 0.5);">Target CPL</span>
                                                    <span style="color: rgba(255, 255, 255, 0.8);">{{ $ad->target_cpl === null ? '—' : 'PKR ' . number_format((float) $ad->target_cpl, 2) }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between small mt-1">
                                                    <span style="color: rgba(255, 255, 255, 0.5);">Spend / Leads</span>
                                                    <span style="color: rgba(255, 255, 255, 0.8);">PKR {{ number_format((float) $ad->test_spend, 0) }} / {{ (int) $ad->leads_generated }}</span>
                                                </div>
                                                @if ($variance !== null)
                                                    <div class="small mt-1 fw-semibold" style="color: {{ $cplHealthy ? '#10b981' : '#dc2626' }};">
                                                        {{ $cplHealthy ? '✓ ' . number_format(abs($variance), 1) . '% under target — Ready to Scale' : '⚠ ' . number_format(abs($variance), 1) . '% over target — Scaling Locked' }}
                                                    </div>
                                                @elseif ((float) $ad->test_spend > 0 && (int) $ad->leads_generated === 0)
                                                    <div class="small mt-1 fw-semibold" style="color: #dc2626;">
                                                        ⚠ Spend with 0 leads — Force Kill
                                                    </div>
                                                @endif
                                            </div>
                                            @moduleEdit('ecommerce')
                                                <div class="d-flex gap-2 mt-2">
                                                    <form action="{{ route('ecommerce.marketing.ads-plan.scale', $ad) }}" method="POST" class="flex-grow-1">
                                                        @csrf @method('PATCH')
                                                        <button type="submit" class="btn btn-sm w-100" style="background-color: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);">🔥 Scale Winner</button>
                                                    </form>
                                                    <form action="{{ route('ecommerce.marketing.ads-plan.kill', $ad) }}" method="POST" class="flex-grow-1">
                                                        @csrf @method('PATCH')
                                                        <button type="submit" class="btn btn-sm w-100" style="background-color: rgba(220, 38, 38, 0.1); color: #dc2626; border: 1px solid rgba(220, 38, 38, 0.2);">💀 Kill Test</button>
                                                    </form>
                                                </div>
                                            @endmoduleEdit
                                        @elseif ($phase === 'Scaling')
                                            <div class="mt-2 p-2 rounded" style="background-color: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2);">
                                                <div class="small fw-bold" style="color: #10b981;">🏆 {{ $ad->scaling_status ?: 'Scaling Winner' }}</div>
                                                @if ($ad->creative_asset_link)
                                                    <a href="{{ $ad->creative_asset_link }}" target="_blank" class="small d-block text-truncate" style="color: #10b981;">{{ $ad->creative_asset_link }}</a>
                                                @endif
                                                @if ($cpl !== null)
                                                    <div class="small mt-1" style="color: #10b981;">Actual CPL: PKR {{ number_format($cpl, 2) }}</div>
                                                @endif
                                                @if ($ad->target_cpl !== null)
                                                    <div class="small mt-1" style="color: rgba(16, 185, 129, 0.8);">Target CPL: PKR {{ number_format((float) $ad->target_cpl, 2) }} &middot; Leads: {{ (int) $ad->leads_generated }}</div>
                                                @endif
                                            </div>
                                            @moduleEdit('ecommerce')
                                                <form action="{{ route('ecommerce.marketing.ads-plan.kill', $ad) }}" method="POST" class="mt-2">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm w-100" style="background-color: rgba(255, 255, 255, 0.03); color: rgba(255, 255, 255, 0.5); border: 1px solid rgba(255, 255, 255, 0.08);">💀 End Campaign</button>
                                                </form>
                                            @endmoduleEdit
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-center text-muted small py-4">No ads in {{ $phase }}.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-4">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#killedAdsGraveyard">
            <i class="bx bx-ghost me-1"></i> Killed Tests Graveyard ({{ $killedAds->count() }})
        </button>
        <div class="collapse mt-3" id="killedAdsGraveyard">
            <div class="row g-3">
                @forelse ($killedAds as $ad)
                    @php $killedCpl = $ad->actualCpl(); @endphp
                    <div class="col-md-4">
                        <div class="card h-100" style="background-color: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05);">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="fw-bold">{{ $ad->ad_name }}</div>
                                    @moduleEdit('ecommerce')
                                        <form action="{{ route('ecommerce.marketing.ads-plan.destroy', $ad) }}" method="POST" onsubmit="return confirm('Delete this ad?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endmoduleEdit
                                </div>
                                <div class="small text-muted mt-1">{{ $ad->person_responsible ?: 'Unassigned' }}</div>
                                <div class="small mt-2" style="color: rgba(255, 255, 255, 0.6);">
                                    Spend: PKR {{ number_format((float) $ad->test_spend, 2) }}
                                    &middot; Leads: {{ (int) $ad->leads_generated }}
                                </div>
                                <div class="small mt-1" style="color: #dc2626;">
                                    Actual CPL: {{ $killedCpl === null ? '— (0 leads)' : 'PKR ' . number_format($killedCpl, 2) }}
                                    @if ($ad->target_cpl !== null)
                                        (Target: PKR {{ number_format((float) $ad->target_cpl, 2) }})
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted small py-4">No killed tests yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    @moduleEdit('ecommerce')
        {{-- Shared Add/Edit modal for E-commerce Monthly Ad rows, matching the salon modal structure and phase locking. --}}
        <div class="modal fade" id="monthlyAdModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form method="POST" id="monthlyAdForm" action="{{ route('ecommerce.marketing.ads-plan.store') }}">
                        @csrf
                        <input type="hidden" name="_method" id="monthlyAdFormMethod">

                        <div class="modal-header">
                            <h5 class="modal-title" id="monthlyAdModalTitle">Add Ad</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <h6 class="fw-bold mb-3">Phase 1 &mdash; Ad Brief</h6>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Ad Name</label>
                                    <input type="text" name="ad_name" id="maAdName" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Person Responsible</label>
                                    <select name="person_responsible" id="maPersonResponsible" class="form-select" required>
                                        <option value="">&mdash; Select &mdash;</option>
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
                                    <label class="form-label">Total Budget (PKR)</label>
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
                                <h6 class="fw-bold">Phase 2 &mdash; Production Deadlines</h6>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Raw Data Deadline</label>
                                        <input type="date" name="raw_data_deadline" id="maRawDataDeadline" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Final Creative Deadline</label>
                                        <input type="date" name="final_creative_deadline" id="maFinalCreativeDeadline" class="form-control">
                                    </div>
                                </div>

                                <h6 class="fw-bold mt-4">4-Point Quality Control Checklist &mdash; required before Testing</h6>
                                <div class="row g-3 mb-3">
                                    <div class="col-12">
                                        <label class="form-label">Creative Asset Link</label>
                                        <input type="text" name="creative_asset_link" id="maCreativeAssetLink" class="form-control" placeholder="Link to the final creative asset (Drive, Frame.io, etc.)">
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
                                <h6 class="fw-bold">Phase 3 &mdash; Testing &amp; CPL Gatekeeper</h6>
                                <div class="p-3 rounded" style="background-color: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05);">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Test Spend (PKR)</label>
                                            <input type="number" step="0.01" min="0" name="test_spend" id="maTestSpend" class="form-control" placeholder="e.g. 5000">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Leads Generated</label>
                                            <input type="number" step="1" min="0" name="leads_generated" id="maLeadsGenerated" class="form-control" placeholder="e.g. 25">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Target CPL (PKR)</label>
                                            <input type="number" step="0.01" min="0" name="target_cpl" id="maTargetCpl" class="form-control" placeholder="e.g. 250">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Live Actual CPL Computation</label>
                                            <div class="p-2 px-3 rounded d-flex align-items-center justify-content-between" id="maActualCplBox" style="background-color: rgba(20, 16, 14, 0.5); border: 1px solid var(--luxe-border-strong); min-height: 38px;">
                                                <span class="small text-muted">Actual CPL:</span>
                                                <strong id="maActualCplValue" style="color: var(--luxe-ink);">—</strong>
                                            </div>
                                            <div class="form-text mt-1" id="maActualCplVerdict"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Scaling Strategy</label>
                                            <select name="scaling_status" id="maScalingStatus" class="form-select">
                                                <option value="">&mdash; Select Strategy &mdash;</option>
                                                @foreach ($scalingStatuses as $status)
                                                    <option value="{{ $status }}">{{ $status }}</option>
                                                @endforeach
                                            </select>
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
            const PRODUCTION_TEXT_FIELD_IDS = ['maRawDataDeadline', 'maFinalCreativeDeadline', 'maCreativeAssetLink', 'maTxtHook', 'maTxtValue', 'maTxtRisk', 'maTxtCta'];
            const PRODUCTION_CHECK_FIELD_IDS = ['maChkHook', 'maChkValue', 'maChkRisk', 'maChkCta'];

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

                document.getElementById('maProductionSection').classList.toggle('d-none', !locked);
            }

            function setProductionLocked(locked) {
                PRODUCTION_TEXT_FIELD_IDS.forEach(id => {
                    document.getElementById(id).readOnly = locked;
                });
                PRODUCTION_CHECK_FIELD_IDS.forEach(id => {
                    document.getElementById(id).disabled = locked;
                });
            }

            function updateActualCplHelper() {
                const spendVal = document.getElementById('maTestSpend').value;
                const leadsVal = document.getElementById('maLeadsGenerated').value;
                const targetVal = document.getElementById('maTargetCpl').value;

                const spend = parseFloat(spendVal);
                const leads = parseInt(leadsVal, 10);
                const target = parseFloat(targetVal);

                const cplValueEl = document.getElementById('maActualCplValue');
                const verdictEl = document.getElementById('maActualCplVerdict');
                const cplBox = document.getElementById('maActualCplBox');

                if (isNaN(spend) || spend <= 0) {
                    cplValueEl.textContent = '—';
                    cplValueEl.style.color = 'var(--luxe-ink)';
                    cplBox.style.borderColor = 'var(--luxe-border-strong)';
                    verdictEl.textContent = 'Enter Test Spend, Leads Generated, and Target CPL to evaluate.';
                    verdictEl.style.color = 'var(--luxe-muted)';
                    return;
                }

                if (isNaN(leads) || leads <= 0) {
                    cplValueEl.textContent = 'No Leads (∞)';
                    cplValueEl.style.color = '#dc2626';
                    cplBox.style.borderColor = 'rgba(220, 38, 38, 0.5)';
                    verdictEl.textContent = '⚠ Gate Locked: Spend recorded with 0 leads — cannot scale (Kill Test).';
                    verdictEl.style.color = '#dc2626';
                    return;
                }

                const actualCpl = spend / leads;
                cplValueEl.textContent = `PKR ${actualCpl.toFixed(2)}`;

                if (!isNaN(target) && target > 0) {
                    const variance = ((actualCpl - target) / target) * 100;
                    if (actualCpl <= target) {
                        cplValueEl.style.color = '#10b981';
                        cplBox.style.borderColor = 'rgba(16, 185, 129, 0.5)';
                        verdictEl.textContent = `✓ Gate Unlocked: ${Math.abs(variance).toFixed(1)}% under Target CPL (PKR ${target.toFixed(2)}) — eligible to Scale.`;
                        verdictEl.style.color = '#10b981';
                    } else {
                        cplValueEl.style.color = '#dc2626';
                        cplBox.style.borderColor = 'rgba(220, 38, 38, 0.5)';
                        verdictEl.textContent = `⚠ Gate Locked: ${variance.toFixed(1)}% over Target CPL (PKR ${target.toFixed(2)}) — Scaling blocked (Optimise or Kill).`;
                        verdictEl.style.color = '#dc2626';
                    }
                } else {
                    cplValueEl.style.color = 'var(--luxe-ink)';
                    cplBox.style.borderColor = 'var(--luxe-border-strong)';
                    verdictEl.textContent = 'Set a Target CPL to unlock the Scaling gatekeeper comparison.';
                    verdictEl.style.color = 'var(--luxe-muted)';
                }
            }

            ['maTestSpend', 'maLeadsGenerated', 'maTargetCpl'].forEach(id => {
                document.getElementById(id).addEventListener('input', updateActualCplHelper);
            });

            window.resetMonthlyAdForm = function () {
                document.getElementById('monthlyAdModalTitle').innerText = 'Add Ad';
                document.getElementById('monthlyAdForm').action = '{{ route('ecommerce.marketing.ads-plan.store') }}';
                document.getElementById('monthlyAdFormMethod').value = '';
                document.getElementById('monthlyAdForm').reset();
                setBriefLocked(false);
                setProductionLocked(false);
                document.getElementById('maTestingSection').classList.add('d-none');
                updateActualCplHelper();
            };

            window.resetAdForm = window.resetMonthlyAdForm;

            window.editMonthlyAd = function (ad) {
                resetMonthlyAdForm();

                document.getElementById('monthlyAdModalTitle').innerText = 'Edit Ad';
                document.getElementById('monthlyAdForm').action = `/ecommerce/marketing/ads-plan/${ad.id}`;
                document.getElementById('monthlyAdFormMethod').value = 'PUT';

                document.getElementById('maAdName').value = ad.ad_name || '';
                document.getElementById('maPersonResponsible').value = ad.person_responsible || '';
                document.getElementById('maPersonResponsibleHidden').value = ad.person_responsible || '';
                document.getElementById('maOffer').value = ad.offer || '';
                document.getElementById('maTotalBudget').value = ad.total_budget || '';
                document.getElementById('maPreFilledMessage').value = ad.pre_filled_message || '';
                document.getElementById('maLiveDate').value = ad.live_date || '';

                document.getElementById('maRawDataDeadline').value = ad.raw_data_deadline || '';
                document.getElementById('maFinalCreativeDeadline').value = ad.final_creative_deadline || '';
                document.getElementById('maCreativeAssetLink').value = ad.creative_asset_link || '';

                document.getElementById('maChkHook').checked = !!ad.chk_hook;
                document.getElementById('maChkValue').checked = !!ad.chk_value;
                document.getElementById('maChkRisk').checked = !!ad.chk_risk;
                document.getElementById('maChkCta').checked = !!ad.chk_cta;
                document.getElementById('maTxtHook').value = ad.txt_hook || '';
                document.getElementById('maTxtValue').value = ad.txt_value || '';
                document.getElementById('maTxtRisk').value = ad.txt_risk || '';
                document.getElementById('maTxtCta').value = ad.txt_cta || '';

                document.getElementById('maTestSpend').value = ad.test_spend || '';
                document.getElementById('maLeadsGenerated').value = ad.leads_generated !== null && ad.leads_generated !== undefined ? ad.leads_generated : '';
                document.getElementById('maTargetCpl').value = ad.target_cpl || '';
                document.getElementById('maScalingStatus').value = ad.scaling_status || '';

                setBriefLocked(ad.phase !== 'Planning');
                setProductionLocked(!['Planning', 'Production'].includes(ad.phase));
                document.getElementById('maTestingSection').classList.toggle('d-none', !['Testing', 'Scaling', 'Killed'].includes(ad.phase));
                updateActualCplHelper();

                new bootstrap.Modal(document.getElementById('monthlyAdModal')).show();
            };

            document.querySelectorAll('.monthly-ad-edit-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    editMonthlyAd(JSON.parse(this.dataset.ad));
                });
            });
        </script>
    @endmoduleEdit
@endsection

