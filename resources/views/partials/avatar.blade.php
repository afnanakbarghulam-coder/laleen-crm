{{--
    Reusable circular Avatar with clean dark-rose initials fallback.
    Verifies file existence on disk AND attaches client-side onerror fallback
    so broken image icons or raw "Profile" alt text never appear anywhere.

    Props:
    - $src: nullable relative path or URL (e.g., $user->profile_photo or $member->profile_picture)
    - $name: display name for initials
    - $size: pixel diameter (default 38)
    - $class: optional extra classes
--}}
@php
    $size = (int) ($size ?? 38);
    $displayName = trim((string) ($name ?? 'Laleen'));
    $initials = collect(preg_split('/\s+/', $displayName))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('') ?: 'L';

    $rawSrc = is_string($src ?? null) ? trim(str_replace('\\', '/', $src)) : '';
    $resolvedUrl = null;
    if ($rawSrc !== '') {
        if (str_starts_with($rawSrc, 'http://') || str_starts_with($rawSrc, 'https://')) {
            $resolvedUrl = $rawSrc;
        } else {
            $relative = ltrim($rawSrc, '/');
            if (is_file(public_path($relative))) {
                $resolvedUrl = asset($relative);
            }
        }
    }
    $fontSize = max(11, (int) round($size * 0.36));
@endphp
<span class="laleen-avatar-wrap {{ $class ?? '' }}"
      style="position:relative; display:inline-flex; align-items:center; justify-content:center; width:{{ $size }}px; height:{{ $size }}px; border-radius:50%; flex-shrink:0; overflow:hidden; background:linear-gradient(135deg, rgba(217,143,131,0.24), rgba(168,118,108,0.16)); border:1px solid rgba(229,184,168,0.32); color:#f8efe6; font-weight:700; font-size:{{ $fontSize }}px; letter-spacing:0.02em; user-select:none;">
    @if ($resolvedUrl)
        <img src="{{ $resolvedUrl }}"
             alt="{{ $displayName }}"
             style="width:100%; height:100%; object-fit:cover; display:block;"
             onerror="this.remove(); this.nextElementSibling.style.display='flex';">
        <span style="display:none; width:100%; height:100%; align-items:center; justify-content:center;">{{ $initials }}</span>
    @else
        <span>{{ $initials }}</span>
    @endif
</span>
