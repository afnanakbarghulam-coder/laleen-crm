@php
    $todayQatar = $todayQatar ?? \Illuminate\Support\Carbon::now('Asia/Qatar');
    $canEditOffers = isset($canEditOffers) ? (bool) $canEditOffers : (bool) (auth()->user()?->canEdit('services'));
    $isStandaloneOffersPage = (bool) ($isStandaloneOffersPage ?? false);
    $offersCollection = collect($monthlyOffers ?? []);
    $combosList = collect($combos ?? []);
    $freebiesList = collect($freebiePackages ?? []);

    $offerStatusCounts = ['active' => 0, 'upcoming' => 0, 'expired' => 0, 'all' => $offersCollection->count()];
    foreach ($offersCollection as $o) {
        $st = $o->liveStatus($todayQatar)['key'];
        if (isset($offerStatusCounts[$st])) {
            $offerStatusCounts[$st]++;
        }
    }

    $offerToneMap = [
        'emerald' => ['bg' => 'rgba(52, 211, 153, 0.14)', 'border' => 'rgba(52, 211, 153, 0.34)', 'color' => '#6ee7b7'],
        'amber' => ['bg' => 'rgba(245, 158, 11, 0.14)', 'border' => 'rgba(245, 158, 11, 0.34)', 'color' => '#fbbf24'],
        'muted' => ['bg' => 'rgba(255, 255, 255, 0.06)', 'border' => 'rgba(255, 255, 255, 0.12)', 'color' => '#b8a29a'],
    ];
    $offerTypeStyles = [
        'combo' => ['bg' => 'rgba(212, 163, 115, 0.14)', 'border' => 'rgba(212, 163, 115, 0.34)', 'color' => '#f3d9a4'],
        'service_freebie' => ['bg' => 'rgba(52, 211, 153, 0.12)', 'border' => 'rgba(52, 211, 153, 0.30)', 'color' => '#6ee7b7'],
        'standalone' => ['bg' => 'rgba(217, 143, 131, 0.15)', 'border' => 'rgba(217, 143, 131, 0.34)', 'color' => '#e79a91'],
    ];
@endphp

<style>
    .mo-num {
        font-variant-numeric: tabular-nums;
    }

    .mo-toolbar {
        background: linear-gradient(180deg, rgba(33, 22, 25, 0.94) 0%, rgba(23, 16, 18, 0.98) 100%);
        border: 1px solid rgba(217, 143, 131, 0.18);
        border-radius: 16px;
        padding: 14px 18px;
        margin-bottom: 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .mo-pill-group {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .mo-filter-btn {
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(20, 14, 16, 0.65);
        color: #b8a29a;
        border-radius: 999px;
        padding: 5px 13px;
        font-size: 12.5px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.14s ease;
    }

    .mo-filter-btn:hover {
        color: #f8efe6;
        border-color: rgba(217, 143, 131, 0.4);
    }

    .mo-filter-btn.active {
        background: linear-gradient(135deg, #d98f83, #c48479);
        border-color: transparent;
        color: #1b1315;
    }

    .mo-card {
        background: linear-gradient(180deg, rgba(34, 22, 26, 0.96) 0%, rgba(22, 15, 18, 0.99) 100%);
        border: 1px solid rgba(217, 143, 131, 0.22);
        border-radius: 16px;
        padding: 20px;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 14px;
    }

    .mo-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
        line-height: 1.35;
    }

    .mo-title {
        font-size: 17px;
        font-weight: 700;
        color: #f8efe6;
        margin: 0;
        line-height: 1.3;
    }

    .mo-price {
        font-size: 24px;
        font-weight: 800;
        color: #f3d9a4;
        line-height: 1.1;
        letter-spacing: -0.02em;
    }

    .mo-orig-price {
        font-size: 13px;
        color: #9e8780;
        text-decoration: line-through;
    }

    .mo-includes-box {
        padding-top: 10px;
        border-top: 1px solid rgba(255, 255, 255, 0.06);
    }

    .mo-section-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #a8938b;
        margin-bottom: 6px;
    }

    .mo-inc-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 4px 0;
        font-size: 13px;
        color: #eadbd6;
    }

    .mo-terms-box {
        background: rgba(20, 14, 16, 0.72);
        border: 1px solid rgba(217, 143, 131, 0.14);
        border-radius: 12px;
        padding: 12px 14px;
    }

    .mo-terms-list {
        margin: 0;
        padding-left: 16px;
        font-size: 12.5px;
        color: #d6c3bc;
        line-height: 1.5;
    }

    .mo-terms-list li + li {
        margin-top: 3px;
    }

    .mo-copy-btn {
        background: rgba(52, 211, 153, 0.14);
        border: 1px solid rgba(52, 211, 153, 0.34);
        color: #6ee7b7;
        border-radius: 999px;
        padding: 6px 14px;
        font-size: 12.5px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    .mo-copy-btn:hover {
        background: rgba(52, 211, 153, 0.22);
        color: #a7f3d0;
    }

    .mo-input, .mo-select {
        background-color: #130d0f !important;
        border: 1px solid rgba(217, 143, 131, 0.26) !important;
        color: #f8efe6 !important;
        border-radius: 10px !important;
        font-size: 13px;
        color-scheme: dark;
    }

    .mo-select {
        -webkit-appearance: none !important;
        -moz-appearance: none !important;
        appearance: none !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23e5b8a8' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.75rem center !important;
        background-size: 12px 10px !important;
        padding-right: 2.2rem !important;
    }

    .mo-select option {
        background-color: #130d0f;
        color: #f8efe6;
    }

    .mo-input:focus, .mo-select:focus {
        border-color: #d98f83 !important;
        box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.18) !important;
    }

    .mo-modal .modal-content {
        background: #1b1315 !important;
        background-color: #1b1315 !important;
        opacity: 1 !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        border: 1px solid rgba(217, 143, 131, 0.34) !important;
        border-radius: 16px;
        box-shadow: 0 24px 64px rgba(0, 0, 0, 0.85) !important;
        color: #f8efe6;
    }

    .mo-modal .modal-header,
    .mo-modal .modal-body,
    .mo-modal .modal-footer {
        background: #1b1315 !important;
        background-color: #1b1315 !important;
    }

    .mo-modal .modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
    }

    .mo-modal .modal-header .btn-close {
        position: static !important;
        transform: none !important;
        margin: 0 0 0 auto !important;
        background-color: transparent !important;
        box-shadow: none !important;
        opacity: 0.78;
    }

    .mo-modal .modal-footer {
        border-top: 1px solid rgba(255, 255, 255, 0.07);
    }

    .mo-toast {
        position: fixed;
        top: 22px;
        right: 24px;
        z-index: 1095;
        background: #1b1315;
        border: 1px solid rgba(110, 231, 183, 0.44);
        color: #6ee7b7;
        border-radius: 12px;
        padding: 10px 16px;
        font-size: 13px;
        font-weight: 600;
        box-shadow: 0 18px 46px rgba(0, 0, 0, 0.65);
        display: flex;
        align-items: center;
        gap: 8px;
    }
</style>

@if (session('success') && $isStandaloneOffersPage)
    <div class="alert py-2 px-3 mb-3 d-flex align-items-center gap-2" style="background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.34); color:#6ee7b7; border-radius:12px; font-size:13px;">
        <i class="bx bx-check-circle fs-5"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

{{-- FILTER & STATUS TOOLBAR --}}
<div class="mo-toolbar" id="monthlyOffersBoard">
    <div class="d-flex align-items-center flex-wrap gap-2">
        <span class="mo-badge" style="background:rgba(212,163,115,0.14); border:1px solid rgba(212,163,115,0.34); color:#f3d9a4; font-size:12px; padding:5px 12px;">
            <i class="bx bx-sparkles"></i> Active Offers &mdash; {{ $todayQatar->format('M Y') }}
        </span>

        <div class="mo-pill-group" role="group" aria-label="Filter offers by status">
            <button type="button" class="mo-filter-btn active" data-mo-status="active">
                Active Now ({{ $offerStatusCounts['active'] }})
            </button>
            <button type="button" class="mo-filter-btn" data-mo-status="upcoming">
                Upcoming ({{ $offerStatusCounts['upcoming'] }})
            </button>
            <button type="button" class="mo-filter-btn" data-mo-status="expired">
                Expired ({{ $offerStatusCounts['expired'] }})
            </button>
            <button type="button" class="mo-filter-btn" data-mo-status="all">
                All ({{ $offerStatusCounts['all'] }})
            </button>
        </div>
    </div>

    <div class="d-flex align-items-center flex-wrap gap-2">
        <select id="moBranchFilter" class="form-select form-select-sm mo-select" style="width:auto; min-width:136px;" aria-label="Filter by branch">
            <option value="all">All Branches</option>
            <option value="old_airport">Old Airport</option>
            <option value="al_wakrah">Al Wakrah</option>
        </select>
        <input type="text" id="moSearchInput" class="form-control form-control-sm mo-input" style="width:190px;" placeholder="Search offers...">
        @if ($canEditOffers && $isStandaloneOffersPage)
            <button type="button" class="btn btn-sm fw-bold px-3 py-2" id="addOfferStandaloneBtn"
                style="background:linear-gradient(135deg,#d98f83,#c48479); color:#1b1315; border:none; border-radius:999px;">
                <i class="bx bx-plus me-1"></i> Add Monthly Offer
            </button>
        @endif
    </div>
</div>

{{-- RESPONSIVE 2-COLUMN OFFER CARDS --}}
<div class="row g-3" id="moCardsGrid">
    @foreach ($offersCollection as $offer)
        @php
            $st = $offer->liveStatus($todayQatar);
            $stTone = $offerToneMap[$st['tone']] ?? $offerToneMap['muted'];
            $typeTone = $offerTypeStyles[$offer->offer_type] ?? $offerTypeStyles['standalone'];
            $effPrice = $offer->effectivePrice();
            $origVal = $offer->effectiveOriginalValue();
            $includedList = $offer->includedServicesList();
            $termsLines = $offer->termsLines();
            $editPayload = [
                'id' => $offer->id,
                'title' => $offer->title,
                'offer_type' => $offer->offer_type,
                'combo_id' => $offer->combo_id,
                'service_freebie_id' => $offer->service_freebie_id,
                'promo_price_qar' => $offer->promo_price_qar,
                'original_value_qar' => $offer->original_value_qar,
                'branch_scope' => $offer->branch_scope,
                'valid_from' => $offer->valid_from?->toDateString(),
                'valid_until' => $offer->valid_until?->toDateString(),
                'terms_and_conditions' => $offer->terms_and_conditions,
                'staff_notes' => $offer->staff_notes,
                'is_active' => (bool) $offer->is_active,
            ];
            $searchHaystack = strtolower(
                $offer->title . ' ' . $offer->typeBadgeLabel() . ' ' . $offer->branchScopeLabel() . ' '
                . implode(' ', array_column($includedList, 'name')) . ' '
                . $offer->terms_and_conditions . ' ' . ($offer->staff_notes ?? '')
            );
        @endphp
        <div class="col-12 col-lg-6 mo-card-col"
             data-mo-card-status="{{ $st['key'] }}"
             data-mo-card-branch="{{ $offer->branch_scope }}"
             data-mo-card-search="{{ $searchHaystack }}"
             style="{{ $st['key'] !== 'active' ? 'display:none;' : '' }}">
            <div class="mo-card">
                <div class="d-flex flex-column gap-3">
                    {{-- Top Badges & Price --}}
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div class="min-w-0">
                            <div class="d-flex align-items-center flex-wrap gap-1 mb-2">
                                <span class="mo-badge" style="background:{{ $typeTone['bg'] }}; border:1px solid {{ $typeTone['border'] }}; color:{{ $typeTone['color'] }};">
                                    {{ $offer->typeBadgeLabel() }}
                                </span>
                                <span class="mo-badge" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); color:#d6c3bc;">
                                    <i class="bx bx-map-pin"></i> {{ $offer->branchScopeLabel() }}
                                </span>
                                <span class="mo-badge mo-num" style="background:{{ $stTone['bg'] }}; border:1px solid {{ $stTone['border'] }}; color:{{ $stTone['color'] }};">
                                    {{ $st['label'] }}
                                </span>
                            </div>
                            <h5 class="mo-title">{{ $offer->title }}</h5>
                            <div class="small mo-num mt-1" style="color:#9e8780;">
                                Valid {{ $offer->valid_from?->format('d M Y') }} &ndash; {{ $offer->valid_until?->format('d M Y') }}
                            </div>
                        </div>

                        <div class="text-end flex-shrink-0 mo-num">
                            <div class="mo-price">{{ number_format($effPrice, 0) }} <span style="font-size:13px; font-weight:700;">QAR</span></div>
                            @if ($origVal && $origVal > $effPrice)
                                <div class="mo-orig-price">{{ number_format($origVal, 0) }} QAR</div>
                            @endif
                        </div>
                    </div>

                    {{-- Live Included Services (Synced from Combo / Service + Freebies) --}}
                    @if (!empty($includedList))
                        <div class="mo-includes-box">
                            <div class="mo-section-label">Included Services</div>
                            @foreach ($includedList as $inc)
                                <div class="mo-inc-item">
                                    <span class="d-flex align-items-center gap-2">
                                        <i class="bx {{ $inc['is_free'] ? 'bx-gift' : 'bx-check' }}" style="color:{{ $inc['is_free'] ? '#6ee7b7' : '#d98f83' }};"></i>
                                        <span>{{ $inc['name'] }}</span>
                                    </span>
                                    @if ($inc['is_free'])
                                        <span class="mo-badge" style="background:rgba(52,211,153,0.14); border:1px solid rgba(52,211,153,0.34); color:#6ee7b7;">FREE</span>
                                    @elseif (!empty($inc['meta']))
                                        <span class="small mo-num" style="color:#9e8780;">{{ $inc['meta'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Terms & Conditions Callout --}}
                    <div class="mo-terms-box">
                        <div class="mo-section-label"><i class="bx bx-file-blank me-1"></i> Terms &amp; Conditions</div>
                        <ul class="mo-terms-list">
                            @foreach ($termsLines as $tLine)
                                <li>{{ $tLine }}</li>
                            @endforeach
                        </ul>
                    </div>

                    @if ($offer->staff_notes)
                        <div class="small d-flex align-items-start gap-2" style="color:#b8a29a;">
                            <i class="bx bx-info-circle mt-1" style="color:#d4a373;"></i>
                            <span><strong style="color:#e8cfa9;">Team Note:</strong> {{ $offer->staff_notes }}</span>
                        </div>
                    @endif
                </div>

                {{-- Footer Actions --}}
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3" style="border-top:1px solid rgba(255,255,255,0.06);">
                    <button type="button" class="mo-copy-btn js-mo-copy" data-copy-text="{{ $offer->whatsappCopyText() }}">
                        <i class="bx bxl-whatsapp fs-5"></i>
                        <span>Copy for Client</span>
                    </button>

                    @if ($canEditOffers)
                        <div class="d-flex align-items-center gap-1">
                            <button type="button"
                                class="btn btn-sm btn-outline-warning py-1 px-2 js-mo-edit-btn"
                                data-offer='@json($editPayload)'
                                title="Edit Offer">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <form method="POST" action="{{ route('offers.toggle', $offer) }}" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-secondary py-1 px-2" title="{{ $offer->is_active ? 'Archive' : 'Activate' }}">
                                    <i class="bx {{ $offer->is_active ? 'bx-archive-in' : 'bx-revision' }}"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('offers.destroy', $offer) }}" class="d-inline"
                                  onsubmit="return confirm('Delete monthly offer {{ addslashes($offer->title) }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Offer">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

<div id="moEmptyState" class="text-center py-5 {{ $offerStatusCounts['active'] > 0 ? 'd-none' : '' }}" style="color:#9e8780;">
    <i class="bx bx-purchase-tag-alt fs-2 d-block mb-2" style="color:#d98f83;"></i>
    <div class="fw-semibold" style="color:#cbb8b0;">No monthly offers match this view.</div>
    <div class="small mt-1">Switch filter tabs above{{ $canEditOffers ? ' or publish a new Monthly Offer.' : '.' }}</div>
</div>

{{-- ADD / EDIT MONTHLY OFFER MODAL (SERVICES FULL EDIT / ADMIN ONLY) --}}
@if ($canEditOffers)
<div class="modal fade mo-modal" id="monthlyOfferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('offers.store') }}" id="monthlyOfferForm">
                @csrf
                <input type="hidden" name="_method" id="moFormMethod" value="POST">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="moModalTitle">Add Monthly Offer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small text-muted">Offer Title <span style="color:#fda4af;">*</span></label>
                            <input type="text" name="title" id="moTitle" class="form-control mo-input" required maxlength="255" placeholder="e.g. November Glow Package">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small text-muted">Offer Type <span style="color:#fda4af;">*</span></label>
                            <select name="offer_type" id="moOfferType" class="form-select mo-select" required>
                                <option value="combo">Linked Combo</option>
                                <option value="service_freebie">Linked Service + Freebies</option>
                                <option value="standalone">Standalone Monthly Promo</option>
                            </select>
                        </div>

                        <div class="col-12" id="moComboWrap">
                            <label class="form-label small text-muted">Select Combo (price &amp; services sync live)</label>
                            <select name="combo_id" id="moComboId" class="form-select mo-select">
                                <option value="">Choose a combo...</option>
                                @foreach ($combosList as $cb)
                                    <option value="{{ $cb->id }}">
                                        {{ $cb->name }} — {{ number_format((float) $cb->price, 0) }} QAR ({{ $cb->services->count() }} services)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 d-none" id="moFreebieWrap">
                            <label class="form-label small text-muted">Select Service + Freebies Package (price &amp; freebies sync live)</label>
                            <select name="service_freebie_id" id="moFreebieId" class="form-select mo-select">
                                <option value="">Choose a Service + Freebies offer...</option>
                                @foreach ($freebiesList as $fp)
                                    <option value="{{ $fp->id }}">
                                        {{ $fp->name }} — {{ number_format((float) $fp->price, 0) }} QAR
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4" id="moPromoPriceWrap">
                            <label class="form-label small text-muted">Promo Price (QAR) <span class="d-none" id="moPriceReqStar" style="color:#fda4af;">*</span></label>
                            <input type="number" step="0.01" min="0" name="promo_price_qar" id="moPromoPrice" class="form-control mo-input mo-num" placeholder="Auto-synced">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Original Value (QAR)</label>
                            <input type="number" step="0.01" min="0" name="original_value_qar" id="moOrigValue" class="form-control mo-input mo-num" placeholder="Optional">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Branch Scope <span style="color:#fda4af;">*</span></label>
                            <select name="branch_scope" id="moBranchScope" class="form-select mo-select" required>
                                <option value="all">All Branches</option>
                                <option value="old_airport">Old Airport</option>
                                <option value="al_wakrah">Al Wakrah</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted">Valid From (Qatar Date) <span style="color:#fda4af;">*</span></label>
                            <input type="date" name="valid_from" id="moValidFrom" class="form-control mo-input mo-num"
                                value="{{ $todayQatar->copy()->startOfMonth()->toDateString() }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Valid Until (Qatar Date) <span style="color:#fda4af;">*</span></label>
                            <input type="date" name="valid_until" id="moValidUntil" class="form-control mo-input mo-num"
                                value="{{ $todayQatar->copy()->endOfMonth()->toDateString() }}" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label small text-muted">Terms &amp; Conditions (one condition per line) <span style="color:#fda4af;">*</span></label>
                            <textarea name="terms_and_conditions" id="moTerms" rows="4" class="form-control mo-input" required
                                placeholder="Valid Sunday to Wednesday only&#10;Advance booking required via WhatsApp&#10;Cannot be combined with other discounts or vouchers"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label small text-muted">Internal Staff Notes (optional — not included in client copy)</label>
                            <textarea name="staff_notes" id="moStaffNotes" rows="2" class="form-control mo-input"
                                placeholder="e.g. Reception: mention keratin add-on upsell at checkout."></textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" id="moIsActive" value="1" checked>
                                <label class="form-check-label small" for="moIsActive">Published / Active on Offers Board</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn fw-bold px-4" style="background:linear-gradient(135deg,#d98f83,#c48479); color:#1b1315; border:none; border-radius:999px;">
                        Save Monthly Offer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
    (function () {
        if (window.__laleenMonthlyOffersBound) return;
        window.__laleenMonthlyOffersBound = true;

        function showMoToast(msg) {
            const existing = document.getElementById('moToastEl');
            if (existing) existing.remove();
            const el = document.createElement('div');
            el.id = 'moToastEl';
            el.className = 'mo-toast';
            el.innerHTML = '<i class="bx bx-check-circle fs-5"></i><span></span>';
            el.querySelector('span').textContent = msg;
            document.body.appendChild(el);
            setTimeout(() => {
                el.style.transition = 'opacity 0.25s ease';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 260);
            }, 3000);
        }

        // Filtering (Status + Branch + Search)
        let activeStatus = 'active';
        const statusBtns = document.querySelectorAll('[data-mo-status]');
        const branchSelect = document.getElementById('moBranchFilter');
        const searchInput = document.getElementById('moSearchInput');
        const cards = Array.from(document.querySelectorAll('.mo-card-col'));
        const emptyState = document.getElementById('moEmptyState');

        function applyOfferFilters() {
            const br = branchSelect ? branchSelect.value : 'all';
            const q = searchInput ? searchInput.value.trim().toLowerCase() : '';
            let visibleCount = 0;

            cards.forEach(card => {
                const cStatus = card.dataset.moCardStatus;
                const cBranch = card.dataset.moCardBranch;
                const cSearch = card.dataset.moCardSearch || '';

                const matchStatus = activeStatus === 'all' || cStatus === activeStatus;
                const matchBranch = br === 'all' || cBranch === 'all' || cBranch === br;
                const matchSearch = !q || cSearch.includes(q);

                const show = matchStatus && matchBranch && matchSearch;
                card.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            if (emptyState) {
                emptyState.classList.toggle('d-none', visibleCount > 0);
            }
        }

        statusBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                activeStatus = btn.dataset.moStatus;
                statusBtns.forEach(b => b.classList.toggle('active', b === btn));
                applyOfferFilters();
            });
        });
        branchSelect?.addEventListener('change', applyOfferFilters);
        searchInput?.addEventListener('input', applyOfferFilters);

        // Copy for Client WhatsApp text
        document.querySelectorAll('.js-mo-copy').forEach(btn => {
            btn.addEventListener('click', async () => {
                const text = btn.dataset.copyText || '';
                try {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        await navigator.clipboard.writeText(text);
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        ta.remove();
                    }
                    showMoToast('Offer details & Terms copied for WhatsApp.');
                } catch (e) {
                    showMoToast('Unable to copy automatically.');
                }
            });
        });

        // Modal wiring (Admin / Services Edit)
        const modalEl = document.getElementById('monthlyOfferModal');
        const formEl = document.getElementById('monthlyOfferForm');
        const typeSelect = document.getElementById('moOfferType');
        const comboWrap = document.getElementById('moComboWrap');
        const freebieWrap = document.getElementById('moFreebieWrap');
        const promoPriceInput = document.getElementById('moPromoPrice');
        const priceReqStar = document.getElementById('moPriceReqStar');
        const comboSelect = document.getElementById('moComboId');
        const freebieSelect = document.getElementById('moFreebieId');

        function syncOfferTypeFields() {
            if (!typeSelect) return;
            const t = typeSelect.value;
            comboWrap?.classList.toggle('d-none', t !== 'combo');
            freebieWrap?.classList.toggle('d-none', t !== 'service_freebie');
            if (comboSelect) comboSelect.required = (t === 'combo');
            if (freebieSelect) freebieSelect.required = (t === 'service_freebie');
            if (promoPriceInput) promoPriceInput.required = (t === 'standalone');
            priceReqStar?.classList.toggle('d-none', t !== 'standalone');
        }

        typeSelect?.addEventListener('change', syncOfferTypeFields);

        window.openMonthlyOfferCreateModal = function () {
            if (!modalEl || !formEl) return;
            document.getElementById('moModalTitle').textContent = 'Add Monthly Offer';
            formEl.action = '{{ route('offers.store') }}';
            document.getElementById('moFormMethod').value = 'POST';
            document.getElementById('moTitle').value = '';
            typeSelect.value = 'combo';
            if (comboSelect) comboSelect.value = '';
            if (freebieSelect) freebieSelect.value = '';
            promoPriceInput.value = '';
            document.getElementById('moOrigValue').value = '';
            document.getElementById('moBranchScope').value = 'all';
            document.getElementById('moValidFrom').value = '{{ $todayQatar->copy()->startOfMonth()->toDateString() }}';
            document.getElementById('moValidUntil').value = '{{ $todayQatar->copy()->endOfMonth()->toDateString() }}';
            document.getElementById('moTerms').value = '';
            document.getElementById('moStaffNotes').value = '';
            document.getElementById('moIsActive').checked = true;
            syncOfferTypeFields();
            new bootstrap.Modal(modalEl).show();
        };

        document.getElementById('addOfferBtn')?.addEventListener('click', window.openMonthlyOfferCreateModal);
        document.getElementById('addOfferStandaloneBtn')?.addEventListener('click', window.openMonthlyOfferCreateModal);

        document.querySelectorAll('.js-mo-edit-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!modalEl || !formEl) return;
                const offer = JSON.parse(btn.dataset.offer || '{}');
                document.getElementById('moModalTitle').textContent = 'Edit Monthly Offer';
                formEl.action = `/monthly-offers/${offer.id}`;
                document.getElementById('moFormMethod').value = 'PUT';
                document.getElementById('moTitle').value = offer.title || '';
                typeSelect.value = offer.offer_type || 'standalone';
                if (comboSelect) comboSelect.value = offer.combo_id || '';
                if (freebieSelect) freebieSelect.value = offer.service_freebie_id || '';
                promoPriceInput.value = offer.promo_price_qar ?? '';
                document.getElementById('moOrigValue').value = offer.original_value_qar ?? '';
                document.getElementById('moBranchScope').value = offer.branch_scope || 'all';
                document.getElementById('moValidFrom').value = offer.valid_from || '';
                document.getElementById('moValidUntil').value = offer.valid_until || '';
                document.getElementById('moTerms').value = offer.terms_and_conditions || '';
                document.getElementById('moStaffNotes').value = offer.staff_notes || '';
                document.getElementById('moIsActive').checked = Boolean(offer.is_active);
                syncOfferTypeFields();
                new bootstrap.Modal(modalEl).show();
            });
        });

        syncOfferTypeFields();
    })();
</script>
