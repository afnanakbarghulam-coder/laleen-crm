@php
    $orbClass = match ($card['orb_tone'] ?? 'rose') {
        'emerald' => 'orb-emerald',
        'champagne' => 'orb-champagne',
        'amber' => 'orb-amber',
        'ruby' => 'orb-ruby',
        default => 'orb-rose',
    };
    $tagClass = match ($card['tag_tone'] ?? 'muted') {
        'pass' => 'tag-pass',
        'rose' => 'tag-rose',
        'champagne' => 'tag-champagne',
        'ruby' => 'tag-ruby',
        default => 'tag-muted',
    };
    $deltaClass = match ($card['delta_tone'] ?? 'rose') {
        'emerald' => 'hl-emerald',
        'champagne' => 'hl-champagne',
        'ruby' => 'hl-ruby',
        default => 'hl-rose',
    };
    $barClass = match ($card['bar_tone'] ?? 'rose-gold') {
        'emerald' => 'fill-emerald',
        'champagne' => 'fill-champagne',
        'ruby' => 'fill-ruby',
        default => 'fill-rose-gold',
    };
    $barPct = max(0.0, min(100.0, (float) ($card['bar_pct'] ?? 0)));
    $iconKey = $card['icon'] ?? 'calendar-check';
@endphp

<article class="mp-luxe-kpi-card">
    <div class="mp-luxe-card-top">
        <div class="mp-luxe-orb {{ $orbClass }}" aria-hidden="true">
            @switch($iconKey)
                @case('clock')
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    @break
                @case('users')
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <polyline points="16 11 18 13 22 9"></polyline>
                    </svg>
                    @break
                @case('alert')
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    @break
                @case('tasks')
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                        <line x1="9" y1="12" x2="15" y2="12"></line>
                        <line x1="9" y1="16" x2="13" y2="16"></line>
                    </svg>
                    @break
                @case('check-circle')
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    @break
                @case('star')
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                    </svg>
                    @break
                @case('shield')
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        <path d="M9 12l2 2 4-4"></path>
                    </svg>
                    @break
                @case('trending')
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                        <polyline points="17 6 23 6 23 12"></polyline>
                    </svg>
                    @break
                @case('reels')
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                    </svg>
                    @break
                @case('chat')
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                    @break
                @case('store')
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    @break
                @default
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <path d="M9 14l2 2 4-4"></path>
                    </svg>
            @endswitch
        </div>

        @if (!empty($card['tag_url']))
            <a href="{{ $card['tag_url'] }}" class="mp-luxe-tag {{ $tagClass }}">{{ $card['tag'] }} &nearr;</a>
        @else
            <span class="mp-luxe-tag {{ $tagClass }}">{{ $card['tag'] }}</span>
        @endif
    </div>

    <div class="mp-luxe-label">{{ $card['label'] }}</div>
    @if (!empty($card['subnote']))
        <div class="mp-luxe-subnote">{{ $card['subnote'] }}</div>
    @endif

    <div class="mp-luxe-hero">
        <span>{{ $card['hero_main'] }}</span>
        @if (!empty($card['hero_unit']))
            <span class="mp-luxe-unit">{{ $card['hero_unit'] }}</span>
        @endif
    </div>

    <div class="mp-luxe-delta">
        <span class="{{ $deltaClass }}">{{ $card['delta_highlight'] }}</span>
        @if (!empty($card['delta_text']))
            <span>{{ $card['delta_text'] }}</span>
        @endif
    </div>

    <div class="mp-luxe-viz">
        <div class="mp-luxe-bar-track">
            <div class="mp-luxe-bar-fill {{ $barClass }}" style="width: {{ $barPct }}%;"></div>
        </div>
        <div class="mp-luxe-bar-scale">
            <span>{{ $card['scale_left'] ?? '0%' }}</span>
            <span>{{ $card['scale_right'] ?? '100%' }}</span>
        </div>
    </div>
</article>
