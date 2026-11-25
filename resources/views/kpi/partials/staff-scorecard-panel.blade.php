@php
    $showBackButton = (bool) ($showBackButton ?? false);
    $columns = (int) ($scorecardVm['columns'] ?? 3);
    $gridModifier = $columns === 2 ? 'luxe-kpi-grid--2' : 'luxe-kpi-grid--3';
    $cards = $scorecardVm['cards'] ?? [];
@endphp

<div class="luxe-scorecard-scope">
    {{-- Individual Scorecard Header Bar (Exact KPIs Module Header — Back button omitted on /my-profile) --}}
    <div class="luxe-staff-detail-banner">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="luxe-icon-orb {{ $scorecardVm['orb_class'] ?? 'orb-rose' }} luxe-avatar-initials">
                {{ $scorecardVm['initials'] ?? 'LK' }}
            </div>
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h3 class="mb-0" style="font-size: 19px; font-weight: 700; color: var(--lx-cream, #f8efe6); font-family: 'Poppins', sans-serif;">
                        {{ $scorecardVm['display_name'] ?? ($scorecardVm['name'] ?? '') }}
                    </h3>
                    @if (!empty($scorecardVm['overall_badge']))
                        <span class="luxe-owner-tag {{ $scorecardVm['tag_class'] ?? '' }}" style="font-size: 11px;">
                            {{ $scorecardVm['overall_badge'] }}
                        </span>
                    @endif
                </div>
                <div class="luxe-staff-role mb-0 mt-1">{{ $scorecardVm['role_subtitle'] ?? '' }}</div>
            </div>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
            @if (!empty($scorecardVm['period_label']))
                <span class="luxe-period-chip">{{ $scorecardVm['period_label'] }}</span>
            @endif
            @if ($showBackButton)
                <button type="button" id="backToAllStaffBtn" class="luxe-back-all-btn">
                    &larr; Back to All Staff Scorecards
                </button>
            @endif
        </div>
    </div>

    {{-- Exact KPIs Module Scorecard Grid --}}
    @if (!empty($cards))
        <div class="luxe-kpi-grid {{ $gridModifier }}">
            @foreach ($cards as $card)
                @include('kpi.partials.kpi-card', [
                    'card' => $card,
                    'hiddenInitially' => false,
                    'showLateReplyControls' => false,
                ])
            @endforeach
        </div>
    @else
        <div class="mp-card text-center py-4">
            <div class="small" style="color:#9e8780;">
                {{ $scorecardVm['empty_message'] ?? 'No KPI scorecards are configured for this role yet.' }}
            </div>
        </div>
    @endif
</div>
