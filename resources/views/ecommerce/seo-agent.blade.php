@extends('layouts.app')
@section('title', 'SEO Studio')

@include('ecommerce._styles')

@php
    $currency = $currency ?? 'PKR';
    $region = $region ?? 'pk';
    $regionUpper = $regionUpper ?? strtoupper($region);
    $activeTab = $activeTab ?? 'onpage';
@endphp

@section('content')
    {{-- Single Clean Header Bar --}}
    <div class="ec-header mb-2 py-1 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-baseline gap-2">
            <h4 class="mb-0" style="font-size: 1.15rem; font-weight: 600;">SEO Studio</h4>
            <span class="text-uppercase fw-semibold" style="font-size: 10.5px; letter-spacing: .06em; color: var(--ec-gold);">
                {{ $regionUpper === 'QA' ? 'Qatar (QAR)' : 'Pakistan (PKR)' }}
            </span>
        </div>
        @moduleEdit('ecommerce')
            <form method="POST" action="{{ route('ecommerce.seo-agent.optimize-all') }}" class="d-inline">
                @csrf
                <input type="hidden" name="region" value="{{ $regionUpper }}">
                <button type="submit" class="btn btn-sm seo-primary-btn">
                    ✨ Regenerate SEO
                </button>
            </form>
        @endmoduleEdit
    </div>

    @include('ecommerce.partials._hub_tabs')

    @if (session('status'))
        <div class="alert alert-success py-2 px-3 mb-3" style="background: rgba(142, 168, 138, 0.16); border: 1px solid rgba(142, 168, 138, 0.4); color: #d6ead3; font-size: 13px; border-radius: 10px;">
            <i class="bx bx-check-circle me-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="row g-4">
        {{-- CLEAN LEFT SIDEBAR: Select a Page --}}
        <div class="col-lg-3">
            <div class="ec-card p-3 mb-0">
                <div class="text-uppercase fw-semibold mb-2" style="font-size: 11px; letter-spacing: .06em; color: var(--ec-muted);">
                    Products
                </div>
                <div class="d-flex flex-column gap-2 mb-3">
                    @forelse ($productAudits as $pAudit)
                        @php
                            $isSelected = $selectedAudit && $selectedAudit->id === $pAudit->id && $activeTab === 'onpage';
                        @endphp
                        <a href="{{ route('ecommerce.seo-agent.index', ['region' => $region, 'audit_id' => $pAudit->id, 'tab' => 'onpage']) }}"
                           class="seo-page-link {{ $isSelected ? 'active' : '' }}">
                            <span class="seo-page-name">{{ $pAudit->target_name }}</span>
                            <span class="seo-ready-badge">Ready</span>
                        </a>
                    @empty
                        <div class="text-muted small py-1">No products added yet.</div>
                    @endforelse
                </div>

                <div class="text-uppercase fw-semibold mb-2 pt-2" style="font-size: 11px; letter-spacing: .06em; color: var(--ec-muted); border-top: 1px solid var(--ec-border);">
                    Salon Locations
                </div>
                <div class="d-flex flex-column gap-2">
                    @foreach ($salonAudits as $sAudit)
                        @php
                            $isSelected = $selectedAudit && $selectedAudit->id === $sAudit->id && $activeTab === 'onpage';
                            $cleanBranchName = str_replace('Laleen Beauty Salon — ', '', $sAudit->target_name);
                        @endphp
                        <a href="{{ route('ecommerce.seo-agent.index', ['region' => $region, 'audit_id' => $sAudit->id, 'tab' => 'onpage']) }}"
                           class="seo-page-link {{ $isSelected ? 'active' : '' }}">
                            <span class="seo-page-name">{{ $cleanBranchName }}</span>
                            <span class="seo-ready-badge">Ready</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- CLEAN MAIN WORKSPACE --}}
        <div class="col-lg-9">
            {{-- Simple 3-Tab Switcher --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="ec-tabs mb-0">
                    <a href="{{ route('ecommerce.seo-agent.index', ['region' => $region, 'audit_id' => $selectedAudit?->id, 'tab' => 'onpage']) }}"
                       class="ec-tab {{ $activeTab === 'onpage' ? 'active' : '' }}">
                        📄 Page SEO
                    </a>
                    <a href="{{ route('ecommerce.seo-agent.index', ['region' => $region, 'tab' => 'topical']) }}"
                       class="ec-tab {{ $activeTab === 'topical' ? 'active' : '' }}">
                        📝 Blog Articles ({{ $topicalClustersCount }})
                    </a>
                    <a href="{{ route('ecommerce.seo-agent.index', ['region' => $region, 'tab' => 'searches']) }}"
                       class="ec-tab {{ $activeTab === 'searches' ? 'active' : '' }}">
                        🔍 Live Google Searches
                    </a>
                </div>
            </div>

            @if ($activeTab === 'searches')
                {{-- TAB 3: LIVE GOOGLE SEARCHES (LIVE KEYWORD DISCOVERY) --}}
                <div class="ec-card p-4 mb-0">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div style="font-size: 13px; color: #ede0db;">
                            🟢 Live Keyword Discovery from Google ({{ strtoupper($region) === 'QA' ? 'google.com.qa' : 'google.com.pk' }}) — Real search phrases people are typing into Google right now.
                        </div>
                        @moduleEdit('ecommerce')
                            <form method="POST" action="{{ route('ecommerce.seo-agent.sync-gsc') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="region" value="{{ $regionUpper }}">
                                <button type="submit" class="btn btn-xs seo-copy-btn">
                                    🔄 Fetch Live Google Searches
                                </button>
                            </form>
                        @endmoduleEdit
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0" style="color: #f3e5dc; border-color: rgba(217, 143, 131, 0.16);">
                            <thead>
                                <tr style="border-bottom: 1px solid rgba(217, 143, 131, 0.22); font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; color: var(--ec-muted);">
                                    <th class="py-2">Live Search Phrase on Google</th>
                                    <th class="py-2">Search Intent</th>
                                    <th class="py-2">Recommended Target</th>
                                    <th class="py-2 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($searchQueries ?? [] as $sq)
                                    @php
                                        $targetUrl = (string) $sq->target_url;
                                        if (str_contains($targetUrl, 'shampoo')) {
                                            $recommendedTarget = 'Sulfate-Free Shampoo';
                                        } elseif (str_contains($targetUrl, 'location') || str_contains($targetUrl, 'doha') || $sq->business_unit === 'salon_local') {
                                            $recommendedTarget = 'Laleen Beauty Salon';
                                        } else {
                                            $recommendedTarget = 'Hunza Apricot Oil';
                                        }
                                    @endphp
                                    <tr style="border-bottom: 1px solid rgba(217, 143, 131, 0.1);">
                                        <td class="py-3 fw-semibold" style="font-size: 13.5px; color: #f6ebe6;">
                                            {{ $sq->search_query }}
                                        </td>
                                        <td class="py-3">
                                            @if ($sq->opportunity_type === 'quick_win')
                                                <span class="badge rounded-pill" style="background: rgba(142, 168, 138, 0.2); color: #9fd498; font-size: 11px; font-weight: 600;">
                                                    🔥 High Buyer Intent
                                                </span>
                                            @else
                                                <span class="badge rounded-pill" style="background: rgba(201, 166, 107, 0.2); color: #deb97c; font-size: 11px; font-weight: 600;">
                                                    💡 Symptom &amp; How-To Search
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3" style="font-size: 13px; color: #e8dad4;">
                                            {{ $recommendedTarget }}
                                        </td>
                                        <td class="py-3 text-end">
                                            @if ($sq->linked_article_id)
                                                <a href="{{ route('ecommerce.seo-agent.index', ['region' => $region, 'tab' => 'topical', 'article_id' => $sq->linked_article_id]) }}"
                                                   class="btn btn-xs seo-copy-btn">
                                                    ✅ View Blog Post
                                                </a>
                                            @else
                                                @moduleEdit('ecommerce')
                                                    <form method="POST" action="{{ route('ecommerce.seo-agent.create-from-query') }}" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="query_id" value="{{ $sq->id }}">
                                                        <button type="submit" class="btn btn-xs seo-primary-btn">
                                                            ✨ Write Blog Post
                                                        </button>
                                                    </form>
                                                @endmoduleEdit
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            No Google searches fetched yet. Click "Fetch Live Google Searches" above.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @elseif ($activeTab === 'topical')
                {{-- TAB 2: BLOG ARTICLES --}}
                <div class="row g-3 mb-3">
                    @foreach ($articles as $article)
                        @php
                            $isCurrentArticle = $selectedArticle && $selectedArticle->id === $article->id;
                        @endphp
                        <div class="col-md-4">
                            <div class="ec-card p-3 mb-0 h-100 d-flex flex-column justify-content-between"
                                 style="{{ $isCurrentArticle ? 'border-color: rgba(201,166,107,0.55);' : '' }}">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        @if ($article->status === 'published' || $article->shopify_published_at)
                                            <span class="seo-ready-badge">Synced to Shopify</span>
                                        @else
                                            <span class="seo-ready-badge">{{ $article->status === 'ready' ? 'Ready' : 'Draft' }}</span>
                                        @endif
                                    </div>
                                    <div class="fw-semibold mb-1" style="font-size: 14px; color: #f6ebe6;">
                                        {{ $article->title }}
                                    </div>
                                    <div class="mb-3" style="font-size: 12px; color: var(--ec-muted);">
                                        Search Phrase: {{ $article->target_keyword }}
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center gap-2 pt-2" style="border-top: 1px solid rgba(217,143,131,0.12);">
                                    @if ($article->article_markdown)
                                        <a href="{{ route('ecommerce.seo-agent.index', ['region' => $region, 'tab' => 'topical', 'article_id' => $article->id]) }}"
                                           class="btn btn-xs seo-copy-btn w-100 text-center">
                                            📖 Read &amp; Copy Article
                                        </a>
                                    @else
                                        @moduleEdit('ecommerce')
                                            <form method="POST" action="{{ route('ecommerce.seo-agent.articles.generate', $article) }}" class="w-100">
                                                @csrf
                                                <button type="submit" class="btn btn-xs seo-primary-btn w-100">
                                                    ✍️ Write &amp; Copy Article
                                                </button>
                                            </form>
                                        @endmoduleEdit
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($selectedArticle)
                    <div class="ec-card p-4 mb-0">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <h5 class="mb-0" style="color: #f6ebe6; font-size: 1rem;">{{ $selectedArticle->title }}</h5>
                                    @if ($selectedArticle->shopify_published_at)
                                        <span class="seo-ready-badge">
                                            ✓ Synced to Shopify ({{ $selectedArticle->shopify_published_at->format('M d, H:i') }})
                                        </span>
                                    @endif
                                </div>
                                <span style="font-size: 12px; color: var(--ec-muted);">Search Phrase: {{ $selectedArticle->target_keyword }}</span>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                @moduleEdit('ecommerce')
                                    <form method="POST" action="{{ route('ecommerce.seo-agent.articles.generate', $selectedArticle) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-xs seo-copy-btn">
                                            ✨ Write / Refresh Article
                                        </button>
                                    </form>
                                @endmoduleEdit
                                <button type="button" class="btn btn-xs seo-copy-btn" onclick="copyRichHtmlBlock('blog-article-rendered-view', this)">
                                    📋 Copy Article
                                </button>
                                @moduleEdit('ecommerce')
                                    <form method="POST" action="{{ route('ecommerce.seo-agent.publish-article') }}">
                                        @csrf
                                        <input type="hidden" name="article_id" value="{{ $selectedArticle->id }}">
                                        <button type="submit" class="btn btn-xs seo-primary-btn">
                                            🚀 Publish Blog to Shopify
                                        </button>
                                    </form>
                                @endmoduleEdit
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-7">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="small fw-semibold" style="color: var(--ec-muted);">Image Alt Text</label>
                                    <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('blog-image-alt-input', this)">📋 Copy</button>
                                </div>
                                <input id="blog-image-alt-input" type="text" class="form-control seo-clean-input" value="{{ $selectedArticle->image_alt_text }}" readonly>
                            </div>
                            <div class="col-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="small fw-semibold" style="color: var(--ec-muted);">Recommended Image Filename</label>
                                    <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('blog-image-filename-input', this)">📋 Copy</button>
                                </div>
                                <input id="blog-image-filename-input" type="text" class="form-control seo-clean-input" value="{{ $selectedArticle->image_filename }}" readonly>
                            </div>
                        </div>

                        <div id="blog-article-rendered-view" class="seo-clean-preview-box seo-rendered-description p-4 rounded">
                            @if ($selectedArticle->article_markdown)
                                {!! $selectedArticle->article_markdown !!}
                            @else
                                <p class="mb-0 text-muted">Click "Write &amp; Copy Article" above to generate this blog post.</p>
                            @endif
                        </div>
                        <textarea id="blog-article-copy-box" class="d-none" readonly>{{ $selectedArticle->article_markdown }}</textarea>
                    </div>
                @endif
            @elseif ($selectedAudit)
                {{-- TAB 1: ONLY 3 SIMPLE CARDS FOR THE SELECTED ITEM --}}

                {{-- CARD 1: 1. Google Search Preview & Meta Tags --}}
                <div class="ec-card p-4 mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="mb-0" style="color: #f6ebe6; font-size: 13.5px; text-transform: none; font-weight: 600;">
                                1. Google Search Preview &amp; Meta Tags
                            </h6>
                            @if ($selectedAudit->shopify_published_at)
                                <span class="seo-ready-badge">
                                    ✓ Synced to Shopify ({{ $selectedAudit->shopify_published_at->format('M d, H:i') }})
                                </span>
                            @endif
                        </div>
                        @if ($selectedAudit->target_type === 'product')
                            @moduleEdit('ecommerce')
                                <form method="POST" action="{{ route('ecommerce.seo-agent.publish-product') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="audit_id" value="{{ $selectedAudit->id }}">
                                    <button type="submit" class="btn btn-xs seo-primary-btn">
                                        🚀 Publish SEO to Shopify
                                    </button>
                                </form>
                            @endmoduleEdit
                        @endif
                    </div>

                    <div class="seo-serp-preview p-3 rounded mb-3">
                        <div style="font-size: 12px; color: #9aa0a6; margin-bottom: 3px;">
                            https://laleenrituals.com/{{ $selectedAudit->target_type === 'salon_branch' ? 'locations' : 'products' }}/{{ $selectedAudit->target_url_slug }}
                        </div>
                        <div class="seo-serp-title mb-1">
                            {{ $selectedAudit->meta_title }}
                        </div>
                        <div class="seo-serp-desc">
                            {{ $selectedAudit->meta_description }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="small fw-semibold" style="color: var(--ec-muted);">
                                SEO Title <span style="opacity: .7;">({{ strlen((string) $selectedAudit->meta_title) }}/60 chars)</span>
                            </label>
                            <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('seo-meta-title-input', this)">📋 Copy</button>
                        </div>
                        <input id="seo-meta-title-input" type="text" class="form-control seo-clean-input" value="{{ $selectedAudit->meta_title }}" readonly>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="small fw-semibold" style="color: var(--ec-muted);">
                                SEO Description <span style="opacity: .7;">({{ strlen((string) $selectedAudit->meta_description) }}/155 chars)</span>
                            </label>
                            <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('seo-meta-desc-input', this)">📋 Copy</button>
                        </div>
                        <textarea id="seo-meta-desc-input" class="form-control seo-clean-input" rows="2" readonly>{{ $selectedAudit->meta_description }}</textarea>
                    </div>

                    <div class="row g-2 pt-2" style="border-top: 1px solid rgba(217, 143, 131, 0.14);">
                        <div class="col-md-7">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="small fw-semibold" style="color: var(--ec-muted);">Image Alt Text</label>
                                <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('seo-image-alt-input', this)">📋 Copy</button>
                            </div>
                            <input id="seo-image-alt-input" type="text" class="form-control seo-clean-input" value="{{ $selectedAudit->image_alt_text }}" readonly>
                        </div>
                        <div class="col-md-5">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="small fw-semibold" style="color: var(--ec-muted);">Recommended Image Filename</label>
                                <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('seo-image-filename-input', this)">📋 Copy</button>
                            </div>
                            <input id="seo-image-filename-input" type="text" class="form-control seo-clean-input" value="{{ $selectedAudit->image_filename }}" readonly>
                        </div>
                    </div>
                </div>

                {{-- CARD 2: 2. Website Product Description & FAQs --}}
                @php
                    $plainTextCopy = trim(strip_tags(str_replace(['</h2>', '</p>', '</li>'], ["\n\n", "\n\n", "\n"], (string) $selectedAudit->optimized_body_html)));
                    $fullHtmlForShopify = (string) $selectedAudit->optimized_body_html;
                    if (!empty($selectedAudit->faq_json)) {
                        $plainTextCopy .= "\n\nFrequently Asked Questions\n";
                        $fullHtmlForShopify .= "\n<h2>Frequently Asked Questions</h2>\n";
                        foreach ($selectedAudit->faq_json as $faq) {
                            $q = $faq['question'] ?? '';
                            $a = $faq['answer'] ?? '';
                            $plainTextCopy .= "\nQ: {$q}\nA: {$a}\n";
                            $fullHtmlForShopify .= "<p><strong>{$q}</strong><br>{$a}</p>\n";
                        }
                    }
                @endphp
                <div class="ec-card p-4 mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h6 class="mb-0" style="color: #f6ebe6; font-size: 13.5px; text-transform: none; font-weight: 600;">
                            2. Website Product Description &amp; FAQs
                        </h6>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('seo-plain-text-hidden', this)">
                                📋 Copy Text
                            </button>
                            <button type="button" class="btn btn-xs seo-primary-btn" onclick="copySeoBlock('seo-shopify-html-hidden', this)">
                                📋 Copy HTML for Shopify
                            </button>
                        </div>
                    </div>

                    <div class="seo-clean-preview-box p-3 rounded">
                        <div class="seo-rendered-description">
                            {!! $selectedAudit->optimized_body_html !!}
                        </div>

                        @if (!empty($selectedAudit->faq_json))
                            <div class="mt-3 pt-3" style="border-top: 1px solid rgba(217, 143, 131, 0.16);">
                                <h2 style="font-size: 14px; font-weight: 600; color: #f3e5dc; margin-bottom: 10px;">
                                    Frequently Asked Questions
                                </h2>
                                <div class="d-flex flex-column gap-2">
                                    @foreach ($selectedAudit->faq_json as $faq)
                                        <div class="p-2 rounded" style="background: rgba(255,255,255,0.03);">
                                            <div class="fw-semibold" style="font-size: 13px; color: #deb97c;">
                                                {{ $faq['question'] ?? '' }}
                                            </div>
                                            <div style="font-size: 12.5px; color: #e8dad4; margin-top: 2px;">
                                                {{ $faq['answer'] ?? '' }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <textarea id="seo-plain-text-hidden" class="d-none" readonly>{{ $plainTextCopy }}</textarea>
                    <textarea id="seo-shopify-html-hidden" class="d-none" readonly>{{ $fullHtmlForShopify }}</textarea>
                </div>

                {{-- CARD 3: 3. Google Rich Snippet Code (Optional) --}}
                <div class="ec-card p-3 mb-0">
                    <details class="seo-schema-details">
                        <summary class="d-flex justify-content-between align-items-center" style="cursor: pointer; list-style: none;">
                            <span style="font-size: 13px; font-weight: 600; color: var(--ec-muted);">
                                3. Google Rich Snippet Code (Optional) — <span style="font-weight: 400;">Advanced: View Google Schema Code for Theme</span>
                            </span>
                            <button type="button" class="btn btn-xs seo-copy-btn" onclick="event.preventDefault(); copySeoBlock('seo-jsonld-box', this)">
                                📋 Copy Schema Code
                            </button>
                        </summary>
                        <div class="mt-3">
                            <textarea id="seo-jsonld-box" class="form-control seo-clean-input" rows="8" style="font-family: monospace; font-size: 11.5px;" readonly>{{ $selectedAudit->json_ld_schema }}</textarea>
                        </div>
                    </details>
                </div>
            @endif
        </div>
    </div>

    <style>
        .seo-primary-btn {
            background: linear-gradient(135deg, #c9a66b 0%, #b3894c 100%);
            color: #1b1412;
            font-weight: 600;
            font-size: 12px;
            border: none;
            border-radius: 8px;
            padding: 6px 14px;
        }
        .seo-primary-btn:hover {
            background: linear-gradient(135deg, #d7b67b 0%, #c19758 100%);
            color: #120d0c;
        }
        .seo-copy-btn {
            background: rgba(217, 143, 131, 0.14);
            border: 1px solid rgba(217, 143, 131, 0.32);
            color: #f3e5dc;
            font-size: 11.5px;
            font-weight: 600;
            padding: 4px 11px;
            border-radius: 7px;
            text-decoration: none;
        }
        .seo-copy-btn:hover {
            background: rgba(217, 143, 131, 0.26);
            color: #fff;
        }
        .seo-page-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(217, 143, 131, 0.14);
            text-decoration: none;
            color: #f3e5dc;
            transition: all 0.16s ease;
        }
        .seo-page-link:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
        }
        .seo-page-link.active {
            background: rgba(201, 166, 107, 0.14);
            border-color: rgba(201, 166, 107, 0.55);
            color: #fff;
        }
        .seo-page-name {
            font-size: 13px;
            font-weight: 600;
        }
        .seo-ready-badge {
            font-size: 10.5px;
            font-weight: 600;
            color: #9fd498;
            background: rgba(142, 168, 138, 0.18);
            padding: 2px 8px;
            border-radius: 999px;
            white-space: nowrap;
        }
        .seo-serp-preview {
            background: #202124;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-family: Arial, sans-serif;
        }
        .seo-serp-title {
            color: #8ab4f8;
            font-size: 17px;
            font-weight: 400;
            line-height: 1.3;
        }
        .seo-serp-desc {
            color: #bdc1c6;
            font-size: 13px;
            line-height: 1.45;
        }
        .seo-clean-input {
            background: rgba(16, 13, 12, 0.65) !important;
            border: 1px solid rgba(217, 143, 131, 0.22) !important;
            color: #f3e5dc !important;
            font-size: 13px;
        }
        .seo-clean-preview-box {
            background: rgba(18, 14, 13, 0.52);
            border: 1px solid rgba(217, 143, 131, 0.16);
            color: #ede0db;
            font-size: 13.5px;
            line-height: 1.6;
        }
        .seo-rendered-description h1 {
            font-size: 18px;
            font-weight: 700;
            color: #f6ebe6;
            margin-top: 0;
            margin-bottom: 12px;
        }
        .seo-rendered-description h2 {
            font-size: 15px;
            font-weight: 600;
            color: #deb97c;
            margin-top: 18px;
            margin-bottom: 8px;
        }
        .seo-rendered-description ul,
        .seo-rendered-description ol {
            padding-left: 20px;
            margin-bottom: 14px;
        }
        .seo-rendered-description li {
            margin-bottom: 6px;
        }
        .seo-rendered-description a {
            color: #deb97c;
            text-decoration: underline;
        }
        .seo-rendered-description table {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
            font-size: 12.5px;
        }
        .seo-rendered-description th,
        .seo-rendered-description td {
            border: 1px solid rgba(217, 143, 131, 0.22);
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        .seo-rendered-description th {
            background: rgba(201, 166, 107, 0.12);
            color: #f6ebe6;
            font-weight: 600;
        }
        .seo-schema-details summary::-webkit-details-marker {
            display: none;
        }
    </style>

    <script>
        function copySeoBlock(elementId, btn) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const text = el.value !== undefined ? el.value : el.textContent;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text);
            } else {
                el.classList.remove('d-none');
                el.select();
                document.execCommand('copy');
                el.classList.add('d-none');
            }
            const original = btn.innerHTML;
            btn.innerHTML = '✓ Copied!';
            setTimeout(() => { btn.innerHTML = original; }, 1600);
        }

        function copyRichHtmlBlock(elementId, btn) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const htmlContent = el.innerHTML.trim();
            const plainContent = (el.innerText || el.textContent || '').trim();

            if (navigator.clipboard && window.ClipboardItem) {
                const item = new ClipboardItem({
                    'text/html': new Blob([htmlContent], { type: 'text/html' }),
                    'text/plain': new Blob([plainContent], { type: 'text/plain' })
                });
                navigator.clipboard.write([item]).catch(() => {
                    navigator.clipboard.writeText(plainContent);
                });
            } else if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(plainContent);
            }

            const original = btn.innerHTML;
            btn.innerHTML = '✓ Copied!';
            setTimeout(() => { btn.innerHTML = original; }, 1600);
        }
    </script>
@endsection
