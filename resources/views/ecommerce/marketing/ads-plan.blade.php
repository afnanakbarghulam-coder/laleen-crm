@extends('layouts.app')
@section('title', 'Monthly Ads Plan')

@include('ecommerce._styles')

@section('content')
    <div class="ec-header">
        <div>
            <h4>Monthly Ads Plan</h4>
            <p>Every ad gated through Planning, Production, Testing and Scaling &mdash; nothing scales until its CPL beats target.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            @moduleEdit('ecommerce')
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adModal" onclick="resetAdForm()">+ Add Ad</button>
            @endmoduleEdit
            <a href="{{ route('ecommerce.marketing') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Marketing</a>
        </div>
    </div>

    <div class="table-responsive">
        <div class="row g-3 flex-nowrap ec-kanban">
            @foreach ($adsByPhase as $phase => $ads)
                <div class="col-md-3">
                    <div class="ec-day-column">
                        <div class="ec-day-header">
                            <span>{{ strtoupper($phase) }}</span>
                            <span class="ec-badge bg-secondary">{{ $ads->count() }}</span>
                        </div>
                        <div class="ec-day-body">
                            @forelse ($ads as $ad)
                                @php
                                    $cpl = $ad->actualCpl();
                                    $cplHealthy = $ad->isCplHealthy();
                                    $variance = $ad->cplVariancePercent();
                                @endphp
                                <div class="ec-content-slot {{ $phase === 'Scaling' ? 'is-live' : '' }}">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="ec-ad-name">{{ $ad->ad_name }}</div>
                                        @moduleEdit('ecommerce')
                                            <div class="d-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-warning ec-ad-edit-btn"
                                                    data-ad='@json($ad->toEditPayload())' title="Edit"><i class="bi bi-pencil-square"></i></button>
                                                <form action="{{ route('ecommerce.marketing.ads-plan.destroy', $ad->id) }}" method="POST" onsubmit="return confirm('Delete this ad?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </div>
                                        @endmoduleEdit
                                    </div>

                                    @if ($ad->person_responsible)
                                        <div class="ec-sub" style="margin-top: 4px;"><i class="bx bx-user me-1"></i>{{ $ad->person_responsible }}</div>
                                    @endif

                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        @if ($ad->total_budget !== null)
                                            <span class="ec-badge ec-phase-pill">Budget: PKR {{ number_format($ad->total_budget, 0) }}</span>
                                        @endif
                                        @if ($ad->live_date)
                                            <span class="ec-badge ec-phase-pill">Live: {{ $ad->live_date->format('d M') }}</span>
                                        @endif
                                    </div>

                                    {{-- Phase-specific body --}}
                                    @if ($phase === 'Production')
                                        <div class="mt-2 d-flex gap-1 flex-wrap">
                                            <span class="ec-badge {{ $ad->creative_asset_link ? 'ec-badge-healthy' : 'ec-badge-stalled' }}">
                                                {{ $ad->creative_asset_link ? '✓ Asset linked' : 'No asset' }}
                                            </span>
                                            <span class="ec-badge {{ empty($ad->missingChecklistPoints()) ? 'ec-badge-healthy' : 'ec-badge-stalled' }}">
                                                QC: {{ 4 - count($ad->missingChecklistPoints()) }}/4
                                            </span>
                                        </div>
                                    @elseif (in_array($phase, ['Testing', 'Scaling'], true))
                                        <div class="ec-cpl-block mt-2">
                                            <div class="d-flex justify-content-between">
                                                <span class="ec-sub">Actual CPL</span>
                                                <span class="{{ $cplHealthy === null ? '' : ($cplHealthy ? 'ec-positive' : 'ec-negative') }}" style="font-weight: 700;">
                                                    {{ $cpl === null ? '—' : 'PKR ' . number_format($cpl, 2) }}
                                                </span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="ec-sub">Target CPL</span>
                                                <span class="ec-sub">{{ $ad->target_cpl === null ? '—' : 'PKR ' . number_format($ad->target_cpl, 2) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="ec-sub">Spend / Leads</span>
                                                <span class="ec-sub">
                                                    PKR {{ number_format((float) $ad->test_spend, 0) }} / {{ (int) $ad->leads_generated }}
                                                </span>
                                            </div>
                                            @if ($variance !== null)
                                                <div class="ec-kpi-flag {{ $cplHealthy ? 'ec-positive' : 'ec-negative' }}">
                                                    {{ $cplHealthy ? '✓' : '⚠' }}
                                                    {{ number_format(abs($variance), 1) }}% {{ $variance <= 0 ? 'under' : 'over' }} target
                                                </div>
                                            @endif
                                            @if ($phase === 'Scaling' && $ad->scaling_status)
                                                <div class="mt-2"><span class="ec-badge ec-badge-healthy">{{ $ad->scaling_status }}</span></div>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Phase progression --}}
                                    @moduleEdit('ecommerce')
                                        <div class="ec-slot-controls">
                                            @if ($phase === 'Planning')
                                                <form action="{{ route('ecommerce.marketing.ads-plan.complete-planning', $ad->id) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm w-100 ec-btn-advance">✔ Brief Complete</button>
                                                </form>
                                            @elseif ($phase === 'Production')
                                                <form action="{{ route('ecommerce.marketing.ads-plan.complete-production', $ad->id) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm w-100 ec-btn-advance">🚀 Push to Testing</button>
                                                </form>
                                            @elseif ($phase === 'Testing')
                                                <form action="{{ route('ecommerce.marketing.ads-plan.scale', $ad->id) }}" method="POST" class="d-flex flex-column gap-1">
                                                    @csrf @method('PATCH')
                                                    <select name="scaling_status" class="cc-mini-select" required>
                                                        <option value="" disabled selected>Scale how?</option>
                                                        @foreach ($scalingStatuses as $status)
                                                            <option value="{{ $status }}">{{ $status }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-sm w-100 ec-btn-advance">🔥 Scale Winner</button>
                                                </form>
                                                <form action="{{ route('ecommerce.marketing.ads-plan.kill', $ad->id) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm w-100 ec-btn-kill">💀 Kill Test</button>
                                                </form>
                                            @elseif ($phase === 'Scaling')
                                                <form action="{{ route('ecommerce.marketing.ads-plan.kill', $ad->id) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm w-100 ec-btn-kill">💀 End Campaign</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endmoduleEdit
                                </div>
                            @empty
                                <div class="ec-sub text-center py-3">No ads in {{ $phase }}</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Graveyard --}}
    <div class="mt-3">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#adGraveyard">
            <i class="bx bx-ghost me-1"></i> Killed Ads ({{ $killedAds->count() }})
        </button>
        <div class="collapse mt-3" id="adGraveyard">
            <div class="row g-3">
                @forelse ($killedAds as $ad)
                    <div class="col-md-4">
                        <div class="ec-bundle-card">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="ec-ad-name">{{ $ad->ad_name }}</div>
                                @moduleEdit('ecommerce')
                                    <form action="{{ route('ecommerce.marketing.ads-plan.destroy', $ad->id) }}" method="POST" onsubmit="return confirm('Delete this ad?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endmoduleEdit
                            </div>
                            <div class="ec-sub mt-1">{{ $ad->person_responsible ?: 'Unassigned' }}</div>
                            <div class="ec-sub mt-2">
                                Spend PKR {{ number_format((float) $ad->test_spend, 2) }} &middot;
                                {{ (int) $ad->leads_generated }} lead(s) &middot;
                                CPL {{ $ad->actualCpl() === null ? '—' : 'PKR ' . number_format($ad->actualCpl(), 2) }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted small py-4">No killed ads yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    @moduleEdit('ecommerce')
        <div class="modal fade" id="adModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="adForm" method="POST" action="{{ route('ecommerce.marketing.ads-plan.store') }}">
                        @csrf
                        <input type="hidden" name="_method" id="adFormMethod">
                        <div class="modal-header">
                            <h5 class="modal-title" id="adModalTitle">Add Ad</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <h6 class="fw-bold">Phase 1 &mdash; Brief</h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label">Ad Name</label>
                                    <input type="text" name="ad_name" id="a_name" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Person Responsible</label>
                                    <select name="person_responsible" id="a_person" class="form-select">
                                        <option value="">&mdash; Unassigned &mdash;</option>
                                        @foreach ($staff as $member)
                                            <option value="{{ $member->name }}">{{ $member->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Offer</label>
                                    <textarea name="offer" id="a_offer" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Total Budget (PKR)</label>
                                    <input type="number" step="0.01" min="0" name="total_budget" id="a_budget" class="form-control">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Pre-filled Message</label>
                                    <textarea name="pre_filled_message" id="a_message" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Live Date</label>
                                    <input type="date" name="live_date" id="a_live" class="form-control">
                                </div>
                            </div>

                            <hr style="border-color: var(--ec-border);">
                            <h6 class="fw-bold">Phase 2 &mdash; Production</h6>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Raw Data Deadline</label>
                                    <input type="date" name="raw_data_deadline" id="a_raw" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Final Creative Deadline</label>
                                    <input type="date" name="final_creative_deadline" id="a_final" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Creative Asset Link</label>
                                    <input type="text" name="creative_asset_link" id="a_asset" class="form-control" placeholder="Drive / Frame.io link">
                                </div>
                            </div>
                            <div class="p-3 rounded mb-3" style="background: rgba(255,255,255,0.02); border: 1px solid var(--ec-border);">
                                <div class="row g-3">
                                    @foreach (['hook' => '1. Hook', 'value' => '2. Value', 'risk' => '3. Risk', 'cta' => '4. CTA'] as $key => $label)
                                        <div class="col-md-6">
                                            <div class="form-check mb-2">
                                                <input type="checkbox" name="chk_{{ $key }}" id="a_chk_{{ $key }}" class="form-check-input" value="1">
                                                <label for="a_chk_{{ $key }}" class="form-check-label">{{ $label }}</label>
                                            </div>
                                            <input type="text" name="txt_{{ $key }}" id="a_txt_{{ $key }}" class="form-control form-control-sm" placeholder="Describe it…">
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <hr style="border-color: var(--ec-border);">
                            <h6 class="fw-bold">Phase 3 &mdash; Testing &amp; CPL</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Test Spend (PKR)</label>
                                    <input type="number" step="0.01" min="0" name="test_spend" id="a_spend" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Leads Generated</label>
                                    <input type="number" step="1" min="0" name="leads_generated" id="a_leads" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Target CPL (PKR)</label>
                                    <input type="number" step="0.01" min="0" name="target_cpl" id="a_target" class="form-control">
                                </div>
                            </div>
                            <div class="ec-accounting-note mt-3 mb-0" id="a_cpl_preview" style="display: none;">
                                <span>🎯</span>
                                <div id="a_cpl_text"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const adSpendInput = document.getElementById('a_spend');
            const adLeadsInput = document.getElementById('a_leads');
            const adTargetInput = document.getElementById('a_target');
            const adCplPreview = document.getElementById('a_cpl_preview');
            const adCplText = document.getElementById('a_cpl_text');

            // Live CPL preview so the scaling decision is visible before saving.
            function refreshCplPreview() {
                const spend = parseFloat(adSpendInput.value) || 0;
                const leads = parseInt(adLeadsInput.value, 10) || 0;
                const target = parseFloat(adTargetInput.value) || 0;

                if (spend <= 0 || leads <= 0) {
                    adCplPreview.style.display = 'none';
                    return;
                }

                const cpl = spend / leads;
                let verdict = '';
                if (target > 0) {
                    const variance = ((cpl - target) / target) * 100;
                    verdict = cpl <= target
                        ? ' · ✓ ' + Math.abs(variance).toFixed(1) + '% under target — eligible to scale'
                        : ' · ⚠ ' + variance.toFixed(1) + '% over target — cannot scale';
                }

                adCplText.innerHTML = '<strong>Actual CPL: PKR ' + cpl.toFixed(2) + '</strong>' + verdict;
                adCplPreview.style.display = '';
            }

            [adSpendInput, adLeadsInput, adTargetInput].forEach((el) => el.addEventListener('input', refreshCplPreview));

            window.resetAdForm = function () {
                document.getElementById('adModalTitle').innerText = 'Add Ad';
                document.getElementById('adForm').action = '{{ route('ecommerce.marketing.ads-plan.store') }}';
                document.getElementById('adFormMethod').value = '';
                document.getElementById('adForm').reset();
                adCplPreview.style.display = 'none';
            };

            document.querySelectorAll('.ec-ad-edit-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const ad = JSON.parse(this.dataset.ad);
                    resetAdForm();

                    document.getElementById('adModalTitle').innerText = 'Edit Ad';
                    document.getElementById('adForm').action = '/ecommerce/marketing/ads-plan/' + ad.id;
                    document.getElementById('adFormMethod').value = 'PUT';

                    document.getElementById('a_name').value = ad.ad_name || '';
                    document.getElementById('a_person').value = ad.person_responsible || '';
                    document.getElementById('a_offer').value = ad.offer || '';
                    document.getElementById('a_budget').value = ad.total_budget || '';
                    document.getElementById('a_message').value = ad.pre_filled_message || '';
                    document.getElementById('a_live').value = ad.live_date || '';
                    document.getElementById('a_raw').value = ad.raw_data_deadline || '';
                    document.getElementById('a_final').value = ad.final_creative_deadline || '';
                    document.getElementById('a_asset').value = ad.creative_asset_link || '';
                    ['hook', 'value', 'risk', 'cta'].forEach(function (k) {
                        document.getElementById('a_chk_' + k).checked = !!ad['chk_' + k];
                        document.getElementById('a_txt_' + k).value = ad['txt_' + k] || '';
                    });
                    document.getElementById('a_spend').value = ad.test_spend || '';
                    document.getElementById('a_leads').value = ad.leads_generated || '';
                    document.getElementById('a_target').value = ad.target_cpl || '';

                    refreshCplPreview();
                    new bootstrap.Modal(document.getElementById('adModal')).show();
                });
            });
        </script>
    @endmoduleEdit

    <style>
        .ec-kanban { min-width: 1150px; }

        .ec-ad-name {
            font-weight: 700;
            font-size: 13.5px;
            color: var(--ec-ink);
        }

        .ec-phase-pill {
            background: rgba(217, 143, 131, 0.12);
            color: var(--ec-muted);
        }

        .ec-cpl-block {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--ec-border);
            border-radius: 8px;
            padding: 8px 10px;
        }

        .ec-btn-advance {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .ec-btn-kill {
            background: rgba(220, 38, 38, 0.1);
            color: #dc2626;
            border: 1px solid rgba(220, 38, 38, 0.25);
        }
    </style>
@endsection
