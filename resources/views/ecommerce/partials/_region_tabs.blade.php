@php
    $activeRegion = $region ?? (in_array(strtolower((string) request('region', 'pk')), ['qa', 'qar', 'qatar'], true) ? 'qa' : 'pk');
    $pkUrl = request()->fullUrlWithQuery(['region' => 'pk', 'channels' => null]);
    $qaUrl = request()->fullUrlWithQuery(['region' => 'qa', 'channels' => null]);
@endphp
<div class="d-inline-flex align-items-center p-1 rounded-3" style="background: #1c1615; border: 1px solid rgba(217, 143, 131, 0.28); gap: 4px;">
    <a
        href="{{ $pkUrl }}"
        class="btn btn-sm px-3 py-1 fw-semibold text-decoration-none"
        style="border-radius: 6px; font-size: 12.5px; {{ $activeRegion === 'pk' ? 'background: rgba(217, 143, 131, 0.22); color: #f3e9e7; border: 1px solid rgba(217, 143, 131, 0.5);' : 'background: transparent; color: #9ca3af; border: 1px solid transparent;' }}"
    >
        🇵🇰 Pakistan (PKR)
    </a>
    <a
        href="{{ $qaUrl }}"
        class="btn btn-sm px-3 py-1 fw-semibold text-decoration-none"
        style="border-radius: 6px; font-size: 12.5px; {{ $activeRegion === 'qa' ? 'background: rgba(201, 166, 107, 0.22); color: #f3e9e7; border: 1px solid rgba(201, 166, 107, 0.5);' : 'background: transparent; color: #9ca3af; border: 1px solid transparent;' }}"
    >
        🇶🇦 Qatar (QAR)
    </a>
</div>
