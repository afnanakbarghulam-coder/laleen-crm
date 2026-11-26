@php
    $hiddenInitially = (bool) ($hiddenInitially ?? false);
    $showLateReplyControls = (bool) ($showLateReplyControls ?? false);
    $isAdminUser = (bool) ($isAdminUser ?? false);
    $iconKey = $card['icon'] ?? 'calendar-check';
    $barPct = max(0.0, min(100.0, (float) ($card['bar_pct'] ?? 0)));
    $labelSafeHtml = htmlspecialchars((string) ($card['label'] ?? ''), ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $deltaParts = [];
    foreach (($card['delta_segments'] ?? []) as $seg) {
        $segHtml = str_replace(
            ' • ',
            ' &bull; ',
            htmlspecialchars((string) ($seg['text'] ?? ''), ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
        if (!empty($seg['class'])) {
            $cls = htmlspecialchars((string) $seg['class'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $deltaParts[] = '<span class="' . $cls . '">' . $segHtml . '</span>';
        } else {
            $deltaParts[] = $segHtml;
        }
    }
    $deltaSafeHtml = implode('', $deltaParts);
@endphp

<article class="luxe-kpi-card {{ $hiddenInitially ? 'is-hidden' : '' }}" data-owner="{{ $card['owner_key'] ?? '' }}">
    <div class="luxe-card-top">
        <div class="luxe-icon-orb {{ $card['orb_class'] ?? 'orb-rose' }}" aria-hidden="true">
            @switch($iconKey)
                @case('dollar')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    @break
                @case('trending-up')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                        <polyline points="17 6 23 6 23 12"></polyline>
                    </svg>
                    @break
                @case('instagram')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                    </svg>
                    @break
                @case('chat-lines')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        <path d="M9 10h6M9 14h4"></path>
                    </svg>
                    @break
                @case('message-circle')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                    @break
                @case('users-check')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <polyline points="16 11 18 13 22 9"></polyline>
                    </svg>
                    @break
                @case('shield-check')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        <path d="M9 12l2 2 4-4"></path>
                    </svg>
                    @break
                @case('star')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                    </svg>
                    @break
                @case('clipboard-check')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                        <path d="M9 14l2 2 4-4"></path>
                    </svg>
                    @break
                @case('clock')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    @break
                @case('alert-triangle')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    @break
                @case('tasks')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                        <line x1="9" y1="12" x2="15" y2="12"></line>
                        <line x1="9" y1="16" x2="13" y2="16"></line>
                    </svg>
                    @break
                @case('check-circle')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    @break
                @case('box')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                    @break
                @case('scale')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="3" x2="12" y2="21"></line>
                        <path d="M5 7h14"></path>
                        <path d="M6 7l-3 7a3 3 0 0 0 6 0l-3-7z"></path>
                        <path d="M18 7l-3 7a3 3 0 0 0 6 0l-3-7z"></path>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                    </svg>
                    @break
                @case('scissors')
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="6" cy="6" r="3"></circle>
                        <circle cx="6" cy="18" r="3"></circle>
                        <line x1="20" y1="4" x2="8.12" y2="15.88"></line>
                        <line x1="14.47" y1="14.48" x2="20" y2="20"></line>
                        <line x1="8.12" y1="8.12" x2="12" y2="12"></line>
                    </svg>
                    @break
                @default
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <path d="M9 14l2 2 4-4"></path>
                    </svg>
            @endswitch
        </div>

        <div class="d-flex align-items-center gap-1 flex-wrap justify-content-end">
            @if ($showLateReplyControls && !empty($card['is_late_reply_card']))
                @if ($isAdminUser)
                    <button type="button" class="luxe-micro-btn" data-bs-toggle="modal" data-bs-target="#logLateReplyModal" title="Log Late Reply Breach">+</button>
                @endif
                @if (($card['late_count'] ?? 0) > 0)
                    <button type="button" class="luxe-proof-btn" data-bs-toggle="modal" data-bs-target="#viewLateRepliesModal" title="View Late Reply Proof">
                        View Proof ({{ $card['late_count'] }})
                    </button>
                @endif
            @endif

            @if (!empty($card['tag_url']))
                <a href="{{ $card['tag_url'] }}"
                   class="luxe-owner-tag {{ $card['tag_class'] ?? '' }}"
                   @if (!empty($card['tag_title'])) title="{{ $card['tag_title'] }}" @endif>
                    {{ $card['tag_label'] }} &nearr;
                </a>
            @else
                <span class="luxe-owner-tag {{ $card['tag_class'] ?? '' }}">
                    {{ $card['tag_label'] }}
                </span>
            @endif
        </div>
    </div>

    <div class="luxe-card-label" @if (!empty($card['label_title'])) title="{{ $card['label_title'] }}" @endif>{!! $labelSafeHtml !!}</div>
    @if (!empty($card['subnote']))
        <div class="luxe-card-subnote">{{ $card['subnote'] }}</div>
    @endif

    <div class="luxe-card-hero">
        {{ $card['hero_main'] }}
        @if (!empty($card['hero_unit']))
            <span class="luxe-unit">{{ $card['hero_unit'] }}</span>
        @endif
    </div>

    <div class="luxe-card-delta">{!! $deltaSafeHtml !!}</div>

    <div class="luxe-card-viz viz-bar">
        <div class="luxe-bar-track">
            <div class="luxe-bar-fill {{ $card['bar_class'] ?? 'fill-rose-gold' }}" style="width: {{ $barPct }}%"></div>
        </div>
        <div class="luxe-bar-scale">
            <span>{{ $card['scale_left'] ?? '0%' }}</span>
            <span>{{ $card['scale_right'] ?? '100%' }}</span>
        </div>
    </div>
</article>
