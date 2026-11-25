@extends($layoutView)
@section('title', 'Monthly Offers — Laleen Beauty Salon')

@section('content')
<div style="max-width: 1120px; margin: 0 auto;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0" style="color:#f8efe6;">Monthly Offers</h4>
            <p class="small mb-0" style="color:#b8a29a;">Live active salon promotions, linked Combos, Service + Freebies &amp; client Terms &amp; Conditions.</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            @if ($layoutView === 'layouts.portal')
                <a href="{{ route('portal.profile') }}" class="btn btn-sm d-inline-flex align-items-center gap-1"
                   style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.14); color:#e6d6d0; border-radius:999px; padding:6px 14px; font-weight:600;">
                    <i class="bx bx-arrow-back"></i> Back to My Profile
                </a>
            @elseif (auth()->user()?->canView('services'))
                <a href="{{ route('services.index', ['tab' => 'offers']) }}" class="btn btn-sm d-inline-flex align-items-center gap-1"
                   style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.14); color:#e6d6d0; border-radius:999px; padding:6px 14px; font-weight:600;">
                    <i class="bx bx-cut"></i> Open Service Catalog
                </a>
            @endif
        </div>
    </div>

    @include('services.partials.offers-board', [
        'monthlyOffers' => $monthlyOffers,
        'todayQatar' => $todayQatar,
        'canEditOffers' => $canEditOffers,
        'combos' => $combos,
        'freebiePackages' => $freebiePackages,
        'isStandaloneOffersPage' => true,
    ])
</div>
@endsection
