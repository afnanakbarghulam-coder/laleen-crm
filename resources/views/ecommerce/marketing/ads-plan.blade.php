@extends('layouts.app')
@section('title', 'Monthly Ads Plan')

@section('content')
    <div class="kpi-header">
        <div>
            <h4>Monthly Ads Plan</h4>
            <p>3-Creative Batch &amp; 7-Day Funnel Test — move a card through the gates right from the board.</p>
        </div>
        <div class="kpi-header-meta d-flex align-items-center flex-wrap gap-2">
            @moduleEdit('ecommerce')
                <form method="POST" action="{{ route('ecommerce.hub.marketing.ai-generate-ads') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="plan_month" value="{{ $selectedMonth !== 'all' ? $selectedMonth : now()->format('Y-m') }}">
                    <input type="hidden" name="region" value="{{ request('region', 'pk') }}">
                    <button type="submit" class="btn btn-sm" style="background: rgba(201, 166, 107, 0.16); color: #deb97c; border: 1px solid rgba(201, 166, 107, 0.38); font-weight: 600;">
                        ⚡ AI Generate 20 Ad Scripts
                    </button>
                </form>

                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#monthlyAdModal" onclick="resetMonthlyAdForm()">
                    <i class="bx bx-plus me-1"></i> Add Ad
                </button>
            @endmoduleEdit
            <a href="{{ route('ecommerce.marketing') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i>Back to Marketing</a>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 p-2 rounded mb-3" style="background-color: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05);">
        <a href="{{ route('ecommerce.marketing.ads-plan.index', ['month' => 'all']) }}" class="btn btn-sm {{ $selectedMonth === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">All Months</a>
        @foreach ($availableMonths as $month)
            <a href="{{ route('ecommerce.marketing.ads-plan.index', ['month' => $month]) }}" class="btn btn-sm {{ $selectedMonth === $month ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}
            </a>
        @endforeach
        <span class="ms-auto small" style="color: rgba(255, 255, 255, 0.6);">
            Total Planned Budget: <strong style="color: rgba(255, 255, 255, 0.9);">{{ number_format((float) $totalPlannedBudget, 0) }} {{ $currency ?? (request('region', 'pk') === 'qa' ? 'QAR' : 'PKR') }}</strong>
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
                                    $deadline = $ad->nextDeadline();
                                    $creativesCount = (!empty($ad->creative_asset_link)) + (!empty($ad->creative_asset_link_2)) + (!empty($ad->creative_asset_link_3));
                                    $checklistCount = $ad->chk_hook + $ad->chk_value + $ad->chk_risk + $ad->chk_cta;
                                    $daysRemaining = $ad->testing_ends_at ? now()->startOfDay()->diffInDays($ad->testing_ends_at->copy()->startOfDay(), false) : null;
                                    $winningName = match ($ad->winning_creative) {
                                        'Creative 1' => $ad->creative_1_name,
                                        'Creative 2' => $ad->creative_2_name,
                                        'Creative 3' => $ad->creative_3_name,
                                        default => null,
                                    };
                                    $winningLink = match ($ad->winning_creative) {
                                        'Creative 1' => $ad->creative_asset_link,
                                        'Creative 2' => $ad->creative_asset_link_2,
                                        'Creative 3' => $ad->creative_asset_link_3,
                                        default => null,
                                    };
                                @endphp
                                <div class="card">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div class="fw-bold">{{ $ad->ad_name }}</div>
                                            @moduleEdit('ecommerce')
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-warning monthly-ad-edit-btn"
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
                                        <div class="small text-muted mt-1"><i class="bx bx-user me-1"></i>{{ $ad->person_responsible }}</div>

                                        <div class="mt-2 d-flex flex-wrap gap-1">
                                            @if ($ad->framework)
                                                <span class="badge" style="background: rgba(201, 166, 107, 0.18); color: #deb97c; border: 1px solid rgba(201, 166, 107, 0.35);">
                                                    {{ $ad->framework }}
                                                </span>
                                            @endif
                                            <span class="badge bg-light text-dark">{{ $ad->target_branch }}</span>
                                            @if ($ad->plan_month)
                                                <span class="badge bg-light text-dark">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $ad->plan_month)->format('M Y') }}</span>
                                            @endif
                                            @if ($ad->offer_price !== null)
                                                <span class="badge bg-light text-dark">Ticket: {{ number_format((float) $ad->offer_price, 0) }} {{ ($ad->region ?? 'PK') === 'QA' ? 'QAR' : 'PKR' }}</span>
                                            @endif
                                        </div>

                                        @if ($ad->hook_text || $ad->spoken_script || $ad->google_flow_prompt)
                                            <div class="mt-2">
                                                <button type="button"
                                                    class="btn btn-sm w-100 ai-script-drawer-btn"
                                                    style="background: rgba(217, 143, 131, 0.12); color: #e8b4ab; border: 1px solid rgba(217, 143, 131, 0.28); font-size: 11.5px; font-weight: 600;"
                                                    data-ad-script='@json($ad->toEditPayload())'>
                                                    🎬 View AI Script &amp; Google Flow Prompt
                                                </button>
                                            </div>
                                        @endif

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
                                                @if ($ad->raw_data_status === 'available')
                                                    Raw: <span style="color: #10b981;">Ready</span>
                                                @elseif ($ad->raw_data_deadline)
                                                    Raw Due: {{ $ad->raw_data_deadline->format('d M Y') }}
                                                @endif
                                                @if ($ad->final_creative_deadline)
                                                    &middot; Edit Due: {{ $ad->final_creative_deadline->format('d M Y') }}
                                                @endif
                                            </div>
                                            <div class="mt-2 d-flex gap-1">
                                                <span class="badge {{ $creativesCount === 3 ? 'bg-success' : 'bg-secondary' }}">Creatives: {{ $creativesCount }}/3</span>
                                                <span class="badge {{ $checklistCount === 4 ? 'bg-success' : 'bg-secondary' }}">QC: {{ $checklistCount }}/4</span>
                                            </div>
                                            @moduleEdit('ecommerce')
                                                <form action="{{ route('ecommerce.marketing.ads-plan.complete-production', $ad) }}" method="POST">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="btn btn-sm w-100 mt-2" style="background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2);">🚀 Push to Testing</button>
                                                </form>
                                            @endmoduleEdit
                                        @elseif ($phase === 'Testing')
                                            <div class="small mt-1" style="color: rgba(255, 255, 255, 0.5);">
                                                Test Window: {{ $ad->testing_started_at?->format('d M') ?: '—' }} → {{ $ad->testing_ends_at?->format('d M Y') ?: '—' }}
                                                @if ($daysRemaining !== null)
                                                    <span class="badge {{ $daysRemaining < 0 ? 'bg-danger' : 'bg-warning text-dark' }}">
                                                        {{ $daysRemaining < 0 ? abs($daysRemaining) . 'd overdue' : $daysRemaining . 'd left' }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="small mt-1" style="color: rgba(255, 255, 255, 0.6);">
                                                Winning Creative: {{ $ad->winning_creative ?: '—' }}
                                                @if ($ad->cost_per_message !== null)
                                                    &middot; Msg Cost: {{ number_format((float) $ad->cost_per_message, 2) }} QAR
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
                                                <div class="small fw-bold" style="color: #10b981;">🏆 {{ $ad->winning_creative ?: '—' }}{{ $winningName ? ' — ' . $winningName : '' }}</div>
                                                @if ($winningLink)
                                                    <a href="{{ $winningLink }}" target="_blank" class="small d-block text-truncate" style="color: #10b981;">{{ $winningLink }}</a>
                                                @endif
                                                @if ($ad->cost_per_message !== null)
                                                    <div class="small mt-1" style="color: #10b981;">Msg Cost: {{ number_format((float) $ad->cost_per_message, 2) }} QAR</div>
                                                @endif
                                                @if ($ad->sales_verdict)
                                                    <div class="small mt-1" style="color: #10b981;">Verdict: {{ $ad->sales_verdict }}</div>
                                                @endif
                                                @if ($ad->verdict_at)
                                                    <div class="small mt-1" style="color: rgba(16, 185, 129, 0.7);">Scaled on {{ $ad->verdict_at->format('d M Y') }}</div>
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
                    @php
                        $killedWinningName = match ($ad->winning_creative) {
                            'Creative 1' => $ad->creative_1_name,
                            'Creative 2' => $ad->creative_2_name,
                            'Creative 3' => $ad->creative_3_name,
                            default => null,
                        };
                    @endphp
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
                                <div class="small text-muted mt-1">{{ $ad->target_branch }} &middot; {{ $ad->person_responsible }}</div>
                                <div class="small mt-2" style="color: rgba(255, 255, 255, 0.6);">
                                    Winning Creative: {{ $ad->winning_creative ?: '—' }}{{ $killedWinningName ? ' — ' . $killedWinningName : '' }}
                                    @if ($ad->cost_per_message !== null)
                                        &middot; Msg Cost: {{ number_format((float) $ad->cost_per_message, 2) }} QAR
                                    @endif
                                </div>
                                @if ($ad->sales_verdict)
                                    <div class="small mt-1" style="color: #dc2626;">{{ $ad->sales_verdict }}</div>
                                @endif
                                @if ($ad->verdict_at)
                                    <div class="small mt-1" style="color: rgba(255, 255, 255, 0.4);">Killed on {{ $ad->verdict_at->format('d M Y') }}</div>
                                @endif
                                @if ($ad->testing_notes)
                                    <div class="small mt-2 p-2 rounded" style="background-color: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05);">{{ $ad->testing_notes }}</div>
                                @endif
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
        @include('ecommerce.marketing._ads-plan-form')
    @endmoduleEdit

    {{-- AI Ad Script & Google Flow Prompt Viewer Modal --}}
    <div class="modal fade" id="aiAdScriptViewerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background: #201917; color: #f2e6e2; border: 1px solid rgba(217, 143, 131, 0.28);">
                <div class="modal-header" style="border-bottom-color: rgba(255, 255, 255, 0.08);">
                    <div>
                        <span class="badge mb-1" id="aiModalFrameworkBadge" style="background: rgba(201, 166, 107, 0.2); color: #deb97c; border: 1px solid rgba(201, 166, 107, 0.38);">DTC Framework</span>
                        <h5 class="modal-title mb-0" id="aiModalAdTitle">AI Ad Script Blueprint</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex flex-column gap-3">
                    <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08);">
                        <div class="text-uppercase fw-bold small mb-1" style="color: #deb97c; letter-spacing: .05em;">🪝 3-Second Visual &amp; Audio Hook</div>
                        <div id="aiModalHookText" style="font-size: 13.5px; line-height: 1.5;">—</div>
                    </div>

                    <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08);">
                        <div class="text-uppercase fw-bold small mb-1" style="color: #e8b4ab; letter-spacing: .05em;">🎙️ Full Spoken Script (30–45 Seconds)</div>
                        <div id="aiModalSpokenScript" style="font-size: 13.5px; line-height: 1.6; white-space: pre-line;">—</div>
                    </div>

                    <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08);">
                        <div class="text-uppercase fw-bold small mb-1" style="color: #a3c49e; letter-spacing: .05em;">🎞️ B-Roll Shot List</div>
                        <div id="aiModalBRoll" style="font-size: 13px; line-height: 1.55; white-space: pre-line;">—</div>
                    </div>

                    <div class="p-3 rounded" style="background: rgba(201, 166, 107, 0.08); border: 1px solid rgba(201, 166, 107, 0.28);">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-uppercase fw-bold small" style="color: #deb97c; letter-spacing: .05em;">🎥 Photorealistic Google Flow Video Prompt</span>
                            <button type="button" class="btn btn-sm btn-outline-light py-0 px-2" style="font-size: 11px;" onclick="navigator.clipboard.writeText(document.getElementById('aiModalGoogleFlowPrompt').innerText)">Copy Prompt</button>
                        </div>
                        <div id="aiModalGoogleFlowPrompt" style="font-size: 12.5px; line-height: 1.5; color: #e7dcd8;">—</div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top-color: rgba(255, 255, 255, 0.08);">
                    <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.ai-script-drawer-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const ad = JSON.parse(this.dataset.adScript || '{}');
                document.getElementById('aiModalAdTitle').textContent = ad.ad_name || 'AI Video Ad Blueprint';
                document.getElementById('aiModalFrameworkBadge').textContent = ad.framework || 'DTC Ad Angle';
                document.getElementById('aiModalHookText').textContent = ad.hook_text || ad.txt_hook || '—';
                document.getElementById('aiModalSpokenScript').textContent = ad.spoken_script || ad.pre_filled_message || '—';
                document.getElementById('aiModalBRoll').textContent = ad.b_roll_shot_list || ad.txt_value || '—';
                document.getElementById('aiModalGoogleFlowPrompt').textContent = ad.google_flow_prompt || '—';
                new bootstrap.Modal(document.getElementById('aiAdScriptViewerModal')).show();
            });
        });
    </script>
@endsection
