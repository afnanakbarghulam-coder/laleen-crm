@extends('layouts.app')
@section('title', 'Monthly Ads Plan')

@php
    $labels = $phaseLabels ?? \App\Models\KpiMonthlyAd::BOARD_PHASE_LABELS;
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
                @moduleEdit('kpis')
                    <button type="button" class="btn btn-sm laleen-ma-rose-btn" data-bs-toggle="modal" data-bs-target="#monthlyAdModal" onclick="resetMonthlyAdForm()">
                        + Add Ad
                    </button>
                @endmoduleEdit
                <a href="{{ route('kpi.marketing.index') }}" class="btn btn-sm laleen-ma-ghost-btn">&larr; Back to Marketing</a>
            </div>
        </div>

        {{-- Month Filter Strip & Total Planned Budget --}}
        <div class="laleen-ma-filterbar mb-3 no-print">
            <div class="d-flex flex-wrap align-items-center gap-1">
                <a href="{{ route('kpi.ads-plan.index', ['month' => 'all']) }}"
                   class="laleen-ma-month-pill {{ $selectedMonth === 'all' ? 'is-active' : '' }}">All Months</a>
                @foreach ($availableMonths as $month)
                    <a href="{{ route('kpi.ads-plan.index', ['month' => $month]) }}"
                       class="laleen-ma-month-pill {{ $selectedMonth === $month ? 'is-active' : '' }}">
                        {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('M Y') }}
                    </a>
                @endforeach
            </div>
            <div class="small" style="color: #b8a29a;">
                Total Planned Budget: <strong style="color: #f8efe6;">{{ number_format((float) $totalPlannedBudget, 0) }} QAR</strong>
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
                                $editPayloadJson = $ad->toEditPayload();
                            @endphp
                            <article class="laleen-ma-card" data-ma-card="{{ $ad->id }}">
                                {{-- Card Header: Ad / Offer Title, Planned Budget & Creative Count --}}
                                <div class="laleen-ma-card-head flex items-start justify-between gap-2 w-full min-w-0">
                                    <div class="laleen-ma-card-copy min-w-0 flex-1">
                                        <div class="laleen-ma-card-title break-words whitespace-normal">{{ $ad->ad_name }}</div>
                                        @if ($ad->offer)
                                            <div class="laleen-ma-card-offer line-clamp-2 break-words whitespace-normal" title="{{ $ad->offer }}">{{ $ad->offer }}</div>
                                        @endif
                                    </div>

                                    @moduleEdit('kpis')
                                        <div class="laleen-ma-card-actions shrink-0 relative z-10 flex items-center gap-1.5 no-print">
                                            <button type="button"
                                                    class="laleen-ma-icon-btn monthly-ad-edit-btn p-1.5 cursor-pointer"
                                                    data-ad="{{ json_encode($editPayloadJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                                                    data-update-url="{{ route('kpi.ads-plan.update', $ad) }}"
                                                    title="Edit Ad"
                                                    aria-label="Edit {{ $ad->ad_name }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                            </button>
                                            <form action="{{ route('kpi.ads-plan.destroy', $ad) }}" method="POST" class="m-0 d-inline laleen-ma-delete-form" data-confirm="Delete this ad?">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="redirect_month" value="{{ $selectedMonth }}">
                                                <button type="submit" class="laleen-ma-icon-btn is-danger p-1.5 cursor-pointer" title="Delete Ad" aria-label="Delete {{ $ad->ad_name }}">
                                                    <i class="bi bi-trash3" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @endmoduleEdit
                                </div>

                                {{-- Compact Meta Line --}}
                                <div class="laleen-ma-meta-line mt-1">
                                    @if ($ad->total_budget !== null)
                                        <span class="laleen-ma-budget">{{ number_format((float) $ad->total_budget, 0) }} QAR</span>
                                        <span>&middot;</span>
                                    @endif
                                    <span>{{ $creativesCount }} {{ \Illuminate\Support\Str::plural('Creative', $creativesCount) }}</span>
                                    <span>&middot;</span>
                                    <span>{{ $ad->target_branch }}</span>
                                    @if ($ad->person_responsible)
                                        <span>&middot;</span>
                                        <span>{{ $ad->person_responsible }}</span>
                                    @endif
                                </div>

                                {{-- Creative Summary Hairline List --}}
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
                                                        <span class="laleen-ma-c-hook break-words whitespace-normal" title="{{ $c['name'] }}">{{ $c['name'] }}</span>
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

                                {{-- Stage Status & Gate Actions Footer --}}
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
                                            <span class="laleen-ma-subtle">Msg: {{ number_format((float) $ad->cost_per_message, 2) }} QAR</span>
                                        @elseif ($ad->offer_price !== null)
                                            <span class="laleen-ma-subtle">Ticket: {{ number_format((float) $ad->offer_price, 0) }} QAR</span>
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

                                    @moduleEdit('kpis')
                                        <div class="d-flex align-items-center gap-2 no-print">
                                            @if ($phaseKey === 'Planning')
                                                <form action="{{ route('kpi.ads-plan.complete-production', $ad) }}" method="POST" class="flex-grow-1 m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="laleen-ma-gate-btn is-advance w-100">Move to Testing &rarr;</button>
                                                </form>
                                            @elseif ($phaseKey === 'Testing')
                                                <form action="{{ route('kpi.ads-plan.scale', $ad) }}" method="POST" class="flex-grow-1 m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="laleen-ma-gate-btn is-scale w-100">Scale Winner &rarr;</button>
                                                </form>
                                                <form action="{{ route('kpi.ads-plan.kill', $ad) }}" method="POST" class="m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="laleen-ma-gate-btn is-kill">Kill</button>
                                                </form>
                                            @elseif ($phaseKey === 'Scaling')
                                                <form action="{{ route('kpi.ads-plan.kill', $ad) }}" method="POST" class="w-100 m-0">
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
                        <div class="laleen-ma-card is-killed" data-ma-card="{{ $ad->id }}">
                            <div class="laleen-ma-card-head flex items-start justify-between gap-2 w-full min-w-0">
                                <div class="laleen-ma-card-copy min-w-0 flex-1">
                                    <div class="laleen-ma-card-title break-words whitespace-normal">{{ $ad->ad_name }}</div>
                                    @if ($ad->offer)
                                        <div class="laleen-ma-card-offer line-clamp-2 break-words whitespace-normal" title="{{ $ad->offer }}">{{ $ad->offer }}</div>
                                    @endif
                                </div>
                                @moduleEdit('kpis')
                                    <div class="laleen-ma-card-actions shrink-0 relative z-10 flex items-center gap-1.5 no-print">
                                        <button type="button"
                                                class="laleen-ma-icon-btn monthly-ad-edit-btn p-1.5 cursor-pointer"
                                                data-ad="{{ json_encode($killedPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                                                data-update-url="{{ route('kpi.ads-plan.update', $ad) }}"
                                                title="View / Edit"
                                                aria-label="Edit {{ $ad->ad_name }}">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </button>
                                        <form action="{{ route('kpi.ads-plan.destroy', $ad) }}" method="POST" class="m-0 d-inline laleen-ma-delete-form" data-confirm="Delete this ad?">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="redirect_month" value="{{ $selectedMonth }}">
                                            <button type="submit" class="laleen-ma-icon-btn is-danger p-1.5 cursor-pointer" title="Delete" aria-label="Delete {{ $ad->ad_name }}">
                                                <i class="bi bi-trash3" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                @endmoduleEdit
                            </div>
                            <div class="laleen-ma-meta-line mt-1">
                                <span>{{ count($killedCreatives) }} {{ \Illuminate\Support\Str::plural('Creative', count($killedCreatives)) }}</span>
                                <span>&middot;</span>
                                <span>{{ $ad->target_branch }}</span>
                                <span>&middot;</span>
                                <span>{{ $ad->person_responsible }}</span>
                            </div>
                            @if ($ad->sales_verdict)
                                <div class="small mt-2 break-words whitespace-normal" style="color: #fda4af;">{{ $ad->sales_verdict }}</div>
                            @endif
                            @if ($ad->testing_notes)
                                <div class="laleen-ma-subtle mt-1 break-words whitespace-normal">{{ $ad->testing_notes }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="laleen-ma-empty">No killed tests yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @moduleEdit('kpis')
        @include('kpi.ads-plan._form')
    @endmoduleEdit

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

        /* 3-Column Responsive Grid — Zero Horizontal Scrollbar */
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
            overflow: hidden;
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
            position: relative;
            background: linear-gradient(180deg, rgba(34, 22, 27, 0.94) 0%, rgba(21, 14, 18, 0.97) 100%);
            border: 1px solid rgba(229, 184, 168, 0.15);
            border-radius: 13px;
            padding: 12px 13px;
            min-width: 0;
            max-width: 100%;
            overflow: hidden;
        }
        .laleen-ma-card.is-killed {
            opacity: 0.82;
        }

        .laleen-ma-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            width: 100%;
            min-width: 0;
        }
        .laleen-ma-card-copy {
            flex: 1 1 0%;
            min-width: 0;
        }

        .laleen-ma-card-title {
            font-size: 0.88rem;
            font-weight: 600;
            color: #f8efe6;
            line-height: 1.35;
            overflow-wrap: anywhere;
            word-break: break-word;
            white-space: normal;
        }
        .laleen-ma-card-offer {
            font-size: 0.75rem;
            color: #b8a29a;
            margin-top: 2px;
            line-height: 1.4;
            overflow-wrap: anywhere;
            word-break: break-word;
            white-space: normal;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .laleen-ma-card-actions {
            flex-shrink: 0;
            position: relative;
            z-index: 10;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .laleen-ma-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            color: #b8a29a;
            padding: 6px;
            min-width: 30px;
            min-height: 30px;
            font-size: 0.82rem;
            border-radius: 7px;
            cursor: pointer;
            pointer-events: auto;
            transition: color 0.14s ease, background 0.14s ease;
        }
        .laleen-ma-icon-btn i {
            pointer-events: none;
        }
        .laleen-ma-icon-btn:hover {
            color: #f8efe6;
            background: rgba(255, 255, 255, 0.06);
        }
        .laleen-ma-icon-btn.is-danger:hover {
            color: #fda4af;
            background: rgba(253, 164, 175, 0.1);
        }

        .laleen-ma-meta-line {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 5px;
            font-size: 0.73rem;
            color: #9e8981;
        }
        .laleen-ma-budget {
            color: #d4a373;
            font-weight: 600;
        }

        /* Single-line Hairline Creative Summary Rows */
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
        .laleen-ma-creative-row:last-child {
            border-bottom: none;
        }
        .laleen-ma-c-spec {
            color: #e5b8a8;
            font-weight: 600;
            white-space: nowrap;
        }
        .laleen-ma-c-spec.is-winner {
            color: #6ee7b7;
        }
        .laleen-ma-c-hook {
            color: #d8c7c0;
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .laleen-ma-ref-link {
            color: #d4a373 !important;
            font-size: 0.72rem;
            font-weight: 600;
            text-decoration: none !important;
        }
        .laleen-ma-ref-link:hover {
            color: #f8efe6 !important;
            text-decoration: underline !important;
        }

        .laleen-ma-card-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }
        .laleen-ma-subtle {
            font-size: 0.71rem;
            color: #9e8981;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .laleen-ma-winner-line {
            font-size: 0.74rem;
            color: #6ee7b7;
            font-weight: 500;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

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
        .laleen-ma-gate-btn.is-advance:hover {
            background: rgba(229, 184, 168, 0.16);
            color: #f8efe6;
        }
        .laleen-ma-gate-btn.is-scale {
            border-color: rgba(110, 231, 183, 0.32);
            color: #6ee7b7;
            background: rgba(110, 231, 183, 0.08);
        }
        .laleen-ma-gate-btn.is-scale:hover {
            background: rgba(110, 231, 183, 0.18);
        }
        .laleen-ma-gate-btn.is-kill {
            border-color: rgba(253, 164, 175, 0.28);
            color: #fda4af;
            background: rgba(253, 164, 175, 0.07);
        }
        .laleen-ma-gate-btn.is-kill:hover {
            background: rgba(253, 164, 175, 0.16);
        }
        .laleen-ma-gate-btn.is-muted {
            border-color: rgba(255, 255, 255, 0.1);
            color: #9e8981;
        }
        .laleen-ma-gate-btn.is-muted:hover {
            color: #fda4af;
            border-color: rgba(253, 164, 175, 0.28);
        }

        .laleen-ma-empty {
            text-align: center;
            color: #8a756e;
            font-size: 0.78rem;
            padding: 24px 8px;
        }

        @media print {
            .no-print { display: none !important; }
            .laleen-ma-col,
            .laleen-ma-card {
                background: #ffffff !important;
                color: #111111 !important;
                border: 1px solid #cccccc !important;
            }
            .laleen-ma-col-title,
            .laleen-ma-card-title,
            .laleen-ma-c-spec,
            .laleen-ma-c-hook {
                color: #111111 !important;
            }
        }
    </style>
@endsection
