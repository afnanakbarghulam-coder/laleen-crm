@extends('layouts.app')
@section('title', 'Monthly Ads Plan')

@php
    $labels = $phaseLabels ?? \App\Models\EcommerceMonthlyAd::BOARD_PHASE_LABELS;
    $activeRegion = strtolower((string) ($region ?? request('region', 'pk')));
    $activeCurrency = $currency ?? ($activeRegion === 'qa' ? 'QAR' : 'PKR');
@endphp

@section('content')
    <div class="laleen-ma-root">
        {{-- Clean Minimalist Header --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-0" style="color: #f8efe6; font-weight: 600;">Monthly Ads Plan</h4>
                <div class="small" style="color: #b8a29a;">Creative Batches &amp; Funnel Testing</div>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2 no-print">
                @moduleEdit('ecommerce')
                    <form method="POST" action="{{ route('ecommerce.hub.marketing.ai-generate-ads') }}" class="d-inline m-0">
                        @csrf
                        <input type="hidden" name="plan_month" value="{{ $selectedMonth !== 'all' ? $selectedMonth : now()->format('Y-m') }}">
                        <input type="hidden" name="region" value="{{ $activeRegion }}">
                        <button type="submit" class="btn btn-sm laleen-ma-ghost-btn">
                            ⚡ AI Generate 20 Ad Scripts
                        </button>
                    </form>

                    <button type="button" class="btn btn-sm laleen-ma-rose-btn" data-bs-toggle="modal" data-bs-target="#monthlyAdModal" onclick="resetMonthlyAdForm()">
                        + Add Ad
                    </button>
                @endmoduleEdit
                <a href="{{ route('ecommerce.marketing') }}" class="btn btn-sm laleen-ma-ghost-btn">&larr; Back to Marketing</a>
            </div>
        </div>

        {{-- Month Filter Strip & Total Planned Budget --}}
        <div class="laleen-ma-filterbar mb-3 no-print">
            <div class="d-flex flex-wrap align-items-center gap-1">
                <a href="{{ route('ecommerce.marketing.ads-plan.index', ['month' => 'all', 'region' => $activeRegion]) }}"
                   class="laleen-ma-month-pill {{ $selectedMonth === 'all' ? 'is-active' : '' }}">All Months</a>
                @foreach ($availableMonths as $month)
                    <a href="{{ route('ecommerce.marketing.ads-plan.index', ['month' => $month, 'region' => $activeRegion]) }}"
                       class="laleen-ma-month-pill {{ $selectedMonth === $month ? 'is-active' : '' }}">
                        {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('M Y') }}
                    </a>
                @endforeach
            </div>
            <div class="small" style="color: #b8a29a;">
                Total Planned Budget: <strong style="color: #f8efe6;">{{ number_format((float) $totalPlannedBudget, 0) }} {{ $activeCurrency }}</strong>
            </div>
        </div>

        {{-- Crisp 3-Column Equal-Width Board (Zero Horizontal Scroll) --}}
        <div class="laleen-ma-board-grid">
            @foreach ($adsByPhase as $phaseKey => $ads)
                @php
                    $colLabel = $labels[$phaseKey] ?? $phaseKey;
                @endphp
                <div class="laleen-ma-col" data-ma-column="{{ $phaseKey }}">
                    <div class="laleen-ma-col-header">
                        <span class="laleen-ma-col-title">{{ $colLabel }}</span>
                        <span class="laleen-ma-count-chip">{{ $ads->count() }}</span>
                    </div>

                    <div class="laleen-ma-col-body">
                        @forelse ($ads as $ad)
                            @php
                                $creatives = $ad->resolvedCreatives();
                                $creativesCount = count($creatives);
                                $deadline = $ad->nextDeadline();
                                $daysRemaining = $ad->testing_ends_at
                                    ? now()->startOfDay()->diffInDays($ad->testing_ends_at->copy()->startOfDay(), false)
                                    : null;
                                $winDetails = $ad->winningCreativeDetails();
                                $cardCurrency = strtoupper((string) ($ad->region ?? $activeRegion)) === 'QA' ? 'QAR' : 'PKR';
                                $editPayloadJson = $ad->toEditPayload();
                            @endphp
                            <article class="laleen-ma-card" data-ma-card="{{ $ad->id }}">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="min-w-0">
                                        <div class="laleen-ma-card-title">{{ $ad->ad_name }}</div>
                                        @if ($ad->offer)
                                            <div class="laleen-ma-card-offer text-truncate" title="{{ $ad->offer }}">{{ $ad->offer }}</div>
                                        @endif
                                    </div>

                                    @moduleEdit('ecommerce')
                                        <div class="d-inline-flex align-items-center gap-1 flex-shrink-0 no-print">
                                            <button type="button"
                                                    class="laleen-ma-icon-btn monthly-ad-edit-btn"
                                                    data-ad='@json($editPayloadJson)'
                                                    title="Edit Ad"
                                                    aria-label="Edit {{ $ad->ad_name }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form action="{{ route('ecommerce.marketing.ads-plan.destroy', $ad) }}" method="POST" class="m-0 d-inline" onsubmit="return confirm('Delete this ad?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="laleen-ma-icon-btn is-danger" title="Delete Ad" aria-label="Delete {{ $ad->ad_name }}">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @endmoduleEdit
                                </div>

                                <div class="laleen-ma-meta-line mt-1">
                                    @if ($ad->total_budget !== null)
                                        <span class="laleen-ma-budget">{{ number_format((float) $ad->total_budget, 0) }} {{ $cardCurrency }}</span>
                                        <span>&middot;</span>
                                    @endif
                                    <span>{{ $creativesCount }} {{ \Illuminate\Support\Str::plural('Creative', $creativesCount) }}</span>
                                    @if ($ad->framework)
                                        <span>&middot;</span>
                                        <span style="color: #e5b8a8;">{{ $ad->framework }}</span>
                                    @endif
                                    @if ($ad->person_responsible)
                                        <span>&middot;</span>
                                        <span>{{ $ad->person_responsible }}</span>
                                    @endif
                                </div>

                                @if ($creativesCount > 0)
                                    <div class="laleen-ma-creatives-list mt-2">
                                        @foreach ($creatives as $c)
                                            @php
                                                $isWinner = $winDetails['label'] && strcasecmp($winDetails['label'], $c['label']) === 0;
                                            @endphp
                                            <div class="laleen-ma-creative-row">
                                                <div class="min-w-0 d-flex align-items-baseline gap-1 flex-wrap">
                                                    <span class="laleen-ma-c-spec {{ $isWinner ? 'is-winner' : '' }}">
                                                        {{ $isWinner ? '★ ' : '' }}{{ $c['short_label'] }} &middot; {{ $c['type_label'] }} &middot; {{ $c['media_label'] }} {{ $c['size'] }}
                                                    </span>
                                                    @if ($c['name'] !== '')
                                                        <span class="laleen-ma-c-hook text-truncate" title="{{ $c['name'] }}">{{ $c['name'] }}</span>
                                                    @endif
                                                </div>
                                                @if (!empty($c['reference_url']))
                                                    <a href="{{ $c['reference_url'] }}"
                                                       target="_blank"
                                                       rel="noopener noreferrer"
                                                       class="laleen-ma-ref-link flex-shrink-0">Ref ↗</a>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($ad->hook_text || $ad->spoken_script || $ad->google_flow_prompt)
                                    <div class="mt-2 no-print">
                                        <button type="button"
                                                class="laleen-ma-gate-btn w-100 ai-script-drawer-btn"
                                                data-ad-script='@json($editPayloadJson)'>
                                            🎬 View AI Script &amp; Flow Prompt
                                        </button>
                                    </div>
                                @endif

                                <div class="laleen-ma-card-footer mt-2 pt-2">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-2">
                                        <span class="laleen-ma-subtle">
                                            @if ($phaseKey === 'Testing' && $ad->testing_ends_at)
                                                Ends {{ $ad->testing_ends_at->format('d M') }}
                                                @if ($daysRemaining !== null)
                                                    ({{ $daysRemaining < 0 ? abs($daysRemaining) . 'd overdue' : $daysRemaining . 'd left' }})
                                                @endif
                                            @elseif ($deadline)
                                                {{ $deadline['label'] }}: {{ $deadline['date']->format('d M Y') }}
                                            @else
                                                {{ $ad->plan_month ? \Illuminate\Support\Carbon::createFromFormat('Y-m', $ad->plan_month)->format('M Y') : '' }}
                                            @endif
                                        </span>

                                        @if ($ad->cost_per_message !== null)
                                            <span class="laleen-ma-subtle">Msg: {{ number_format((float) $ad->cost_per_message, 2) }} {{ $cardCurrency }}</span>
                                        @elseif ($ad->offer_price !== null)
                                            <span class="laleen-ma-subtle">Ticket: {{ number_format((float) $ad->offer_price, 0) }} {{ $cardCurrency }}</span>
                                        @endif
                                    </div>

                                    @if ($phaseKey === 'Scaling' && ($winDetails['label'] || $ad->sales_verdict))
                                        <div class="laleen-ma-winner-line mb-2">
                                            @if ($winDetails['label'])
                                                <span>Winner: {{ $winDetails['label'] }}{{ $winDetails['name'] ? ' — ' . $winDetails['name'] : '' }}</span>
                                            @endif
                                            @if ($ad->sales_verdict)
                                                <span>{{ $winDetails['label'] ? ' · ' : '' }}{{ $ad->sales_verdict }}</span>
                                            @endif
                                        </div>
                                    @endif

                                    @moduleEdit('ecommerce')
                                        <div class="d-flex align-items-center gap-2 no-print">
                                            @if ($phaseKey === 'Planning')
                                                <form action="{{ route('ecommerce.marketing.ads-plan.complete-production', $ad) }}" method="POST" class="flex-grow-1 m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="laleen-ma-gate-btn is-advance w-100">Move to Testing &rarr;</button>
                                                </form>
                                            @elseif ($phaseKey === 'Testing')
                                                <form action="{{ route('ecommerce.marketing.ads-plan.scale', $ad) }}" method="POST" class="flex-grow-1 m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="laleen-ma-gate-btn is-scale w-100">Scale Winner &rarr;</button>
                                                </form>
                                                <form action="{{ route('ecommerce.marketing.ads-plan.kill', $ad) }}" method="POST" class="m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="laleen-ma-gate-btn is-kill">Kill</button>
                                                </form>
                                            @elseif ($phaseKey === 'Scaling')
                                                <form action="{{ route('ecommerce.marketing.ads-plan.kill', $ad) }}" method="POST" class="w-100 m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="laleen-ma-gate-btn is-muted w-100">End Campaign</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endmoduleEdit
                                </div>
                            </article>
                        @empty
                            <div class="laleen-ma-empty">No ads in {{ $colLabel }}.</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Collapsible Killed Tests Graveyard --}}
        <div class="mt-4 no-print">
            <button type="button" class="btn btn-sm laleen-ma-ghost-btn" data-bs-toggle="collapse" data-bs-target="#killedAdsGraveyard">
                Killed Tests Graveyard ({{ $killedAds->count() }})
            </button>
            <div class="collapse mt-3" id="killedAdsGraveyard">
                <div class="laleen-ma-board-grid">
                    @forelse ($killedAds as $ad)
                        @php
                            $killedCreatives = $ad->resolvedCreatives();
                            $killedPayload = $ad->toEditPayload();
                        @endphp
                        <div class="laleen-ma-card is-killed">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="laleen-ma-card-title">{{ $ad->ad_name }}</div>
                                @moduleEdit('ecommerce')
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="laleen-ma-icon-btn monthly-ad-edit-btn" data-ad='@json($killedPayload)' title="View / Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('ecommerce.marketing.ads-plan.destroy', $ad) }}" method="POST" class="m-0 d-inline" onsubmit="return confirm('Delete this ad?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="laleen-ma-icon-btn is-danger" title="Delete"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    </div>
                                @endmoduleEdit
                            </div>
                            <div class="laleen-ma-meta-line mt-1">
                                <span>{{ count($killedCreatives) }} {{ \Illuminate\Support\Str::plural('Creative', count($killedCreatives)) }}</span>
                                <span>&middot;</span>
                                <span>{{ $ad->person_responsible }}</span>
                            </div>
                            @if ($ad->sales_verdict)
                                <div class="small mt-2" style="color: #fda4af;">{{ $ad->sales_verdict }}</div>
                            @endif
                            @if ($ad->testing_notes)
                                <div class="laleen-ma-subtle mt-1">{{ $ad->testing_notes }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="laleen-ma-empty">No killed tests yet.</div>
                    @endforelse
                </div>
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
                    <div style="padding-bottom: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.06);">
                        <div class="text-uppercase fw-bold small mb-1" style="color: #deb97c; letter-spacing: .05em;">3-Second Visual &amp; Audio Hook</div>
                        <div id="aiModalHookText" style="font-size: 13.5px; line-height: 1.5;">—</div>
                    </div>

                    <div style="padding-bottom: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.06);">
                        <div class="text-uppercase fw-bold small mb-1" style="color: #e8b4ab; letter-spacing: .05em;">Full Spoken Script (30–45 Seconds)</div>
                        <div id="aiModalSpokenScript" style="font-size: 13.5px; line-height: 1.6; white-space: pre-line;">—</div>
                    </div>

                    <div style="padding-bottom: 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.06);">
                        <div class="text-uppercase fw-bold small mb-1" style="color: #a3c49e; letter-spacing: .05em;">B-Roll Shot List</div>
                        <div id="aiModalBRoll" style="font-size: 13px; line-height: 1.55; white-space: pre-line;">—</div>
                    </div>

                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-uppercase fw-bold small" style="color: #deb97c; letter-spacing: .05em;">Photorealistic Google Flow Video Prompt</span>
                            <button type="button" class="btn btn-sm laleen-ma-ghost-btn py-0 px-2" onclick="navigator.clipboard.writeText(document.getElementById('aiModalGoogleFlowPrompt').innerText)">Copy Prompt</button>
                        </div>
                        <div id="aiModalGoogleFlowPrompt" style="font-size: 12.5px; line-height: 1.5; color: #e7dcd8;">—</div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top-color: rgba(255, 255, 255, 0.08);">
                    <button type="button" class="btn btn-sm laleen-ma-ghost-btn" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .laleen-ma-rose-btn {
            background: linear-gradient(135deg, #d98f83 0%, #c4776b 100%);
            color: #180f13 !important;
            font-weight: 600;
            border: none;
            border-radius: 999px;
            padding: 5px 14px;
            font-size: 0.8rem;
        }
        .laleen-ma-rose-btn:hover { opacity: 0.92; }

        .laleen-ma-ghost-btn {
            background: rgba(255, 255, 255, 0.04);
            color: #e5b8a8 !important;
            border: 1px solid rgba(229, 184, 168, 0.2);
            border-radius: 999px;
            padding: 5px 13px;
            font-size: 0.78rem;
            font-weight: 500;
        }
        .laleen-ma-ghost-btn:hover {
            background: rgba(229, 184, 168, 0.12);
            color: #f8efe6 !important;
        }

        .laleen-ma-filterbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding: 8px 12px;
            background: rgba(21, 14, 18, 0.72);
            border: 1px solid rgba(229, 184, 168, 0.12);
            border-radius: 12px;
        }

        .laleen-ma-month-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 11px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 500;
            color: #b8a29a !important;
            text-decoration: none !important;
            transition: all 0.14s ease;
        }
        .laleen-ma-month-pill:hover { color: #f8efe6 !important; background: rgba(255, 255, 255, 0.05); }
        .laleen-ma-month-pill.is-active {
            background: rgba(229, 184, 168, 0.18);
            color: #f8efe6 !important;
            font-weight: 600;
        }

        .laleen-ma-board-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 14px;
            width: 100%;
        }
        @media (min-width: 992px) {
            .laleen-ma-board-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .laleen-ma-col {
            background: rgba(20, 13, 17, 0.58);
            border: 1px solid rgba(229, 184, 168, 0.12);
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .laleen-ma-col-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 11px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .laleen-ma-col-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #f8efe6;
        }
        .laleen-ma-count-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 22px;
            height: 20px;
            padding: 0 7px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
            background: rgba(229, 184, 168, 0.14);
            color: #e5b8a8;
        }

        .laleen-ma-col-body {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1 1 auto;
        }

        .laleen-ma-card {
            background: linear-gradient(180deg, rgba(34, 22, 27, 0.94) 0%, rgba(21, 14, 18, 0.97) 100%);
            border: 1px solid rgba(229, 184, 168, 0.15);
            border-radius: 13px;
            padding: 12px 13px;
            min-width: 0;
        }
        .laleen-ma-card.is-killed { opacity: 0.82; }

        .laleen-ma-card-title {
            font-size: 0.88rem;
            font-weight: 600;
            color: #f8efe6;
            line-height: 1.35;
        }
        .laleen-ma-card-offer {
            font-size: 0.75rem;
            color: #b8a29a;
            margin-top: 1px;
        }

        .laleen-ma-icon-btn {
            background: transparent;
            border: none;
            color: #b8a29a;
            padding: 2px 5px;
            font-size: 0.78rem;
            border-radius: 6px;
            transition: color 0.14s ease, background 0.14s ease;
        }
        .laleen-ma-icon-btn:hover { color: #f8efe6; background: rgba(255, 255, 255, 0.06); }
        .laleen-ma-icon-btn.is-danger:hover { color: #fda4af; background: rgba(253, 164, 175, 0.1); }

        .laleen-ma-meta-line {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 5px;
            font-size: 0.73rem;
            color: #9e8981;
        }
        .laleen-ma-budget { color: #d4a373; font-weight: 600; }

        .laleen-ma-creatives-list {
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding-top: 4px;
        }
        .laleen-ma-creative-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            padding: 4px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 0.74rem;
        }
        .laleen-ma-creative-row:last-child { border-bottom: none; }
        .laleen-ma-c-spec { color: #e5b8a8; font-weight: 600; white-space: nowrap; }
        .laleen-ma-c-spec.is-winner { color: #6ee7b7; }
        .laleen-ma-c-hook { color: #d8c7c0; max-width: 190px; }
        .laleen-ma-ref-link {
            color: #d4a373 !important;
            font-size: 0.72rem;
            font-weight: 600;
            text-decoration: none !important;
        }
        .laleen-ma-ref-link:hover { color: #f8efe6 !important; text-decoration: underline !important; }

        .laleen-ma-card-footer { border-top: 1px solid rgba(255, 255, 255, 0.06); }
        .laleen-ma-subtle { font-size: 0.71rem; color: #9e8981; }
        .laleen-ma-winner-line { font-size: 0.74rem; color: #6ee7b7; font-weight: 500; }

        .laleen-ma-gate-btn {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(229, 184, 168, 0.22);
            color: #e5b8a8;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.14s ease;
        }
        .laleen-ma-gate-btn.is-advance:hover { background: rgba(229, 184, 168, 0.16); color: #f8efe6; }
        .laleen-ma-gate-btn.is-scale {
            border-color: rgba(110, 231, 183, 0.32);
            color: #6ee7b7;
            background: rgba(110, 231, 183, 0.08);
        }
        .laleen-ma-gate-btn.is-scale:hover { background: rgba(110, 231, 183, 0.18); }
        .laleen-ma-gate-btn.is-kill {
            border-color: rgba(253, 164, 175, 0.28);
            color: #fda4af;
            background: rgba(253, 164, 175, 0.07);
        }
        .laleen-ma-gate-btn.is-kill:hover { background: rgba(253, 164, 175, 0.16); }
        .laleen-ma-gate-btn.is-muted { border-color: rgba(255, 255, 255, 0.1); color: #9e8981; }
        .laleen-ma-gate-btn.is-muted:hover { color: #fda4af; border-color: rgba(253, 164, 175, 0.28); }

        .laleen-ma-empty { text-align: center; color: #8a756e; font-size: 0.78rem; padding: 24px 8px; }

        @media print {
            .no-print { display: none !important; }
            .laleen-ma-col, .laleen-ma-card {
                background: #ffffff !important;
                color: #111111 !important;
                border: 1px solid #cccccc !important;
            }
            .laleen-ma-col-title, .laleen-ma-card-title, .laleen-ma-c-spec, .laleen-ma-c-hook {
                color: #111111 !important;
            }
        }
    </style>

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
