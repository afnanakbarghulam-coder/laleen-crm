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
                    @php
                        $sidebarTabParam = $activeTabParam ?? ($activeTab === 'topical' ? 'blogs' : 'onpage');
                    @endphp
                    @forelse ($productAudits as $pAudit)
                        @php
                            $isSelected = $selectedAudit && $selectedAudit->id === $pAudit->id;
                        @endphp
                        <a href="{{ route('ecommerce.seo-agent.index', ['region' => $region, 'audit_id' => $pAudit->id, 'tab' => $sidebarTabParam]) }}"
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
                            $isSelected = $selectedAudit && $selectedAudit->id === $sAudit->id;
                            $cleanBranchName = str_replace('Laleen Beauty Salon — ', '', $sAudit->target_name);
                        @endphp
                        <a href="{{ route('ecommerce.seo-agent.index', ['region' => $region, 'audit_id' => $sAudit->id, 'tab' => $sidebarTabParam]) }}"
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
            {{-- Simple 2-Tab Switcher --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="ec-tabs mb-0">
                    <a href="{{ route('ecommerce.seo-agent.index', array_filter(['region' => $region, 'audit_id' => $selectedAudit?->id, 'tab' => 'onpage'])) }}"
                       class="ec-tab {{ $activeTab === 'onpage' ? 'active' : '' }}">
                        📄 Page SEO
                    </a>
                    <a href="{{ route('ecommerce.seo-agent.index', array_filter(['region' => $region, 'audit_id' => $selectedAudit?->id, 'tab' => 'blogs'])) }}"
                       class="ec-tab {{ $activeTab === 'topical' ? 'active' : '' }}">
                        📝 Blog Studio
                    </a>
                </div>
            </div>

            @if ($activeTab === 'topical')
                {{-- TAB 2: BLOG STUDIO (SINGLE UNIFIED HORIZONTAL TOOLBAR + CLEAN ARTICLE CARD) --}}
                @php
                    $ungeneratedQueries = ($searchQueries ?? collect())->whereNull('linked_article_id')->values();
                    $firstUngeneratedQuery = $ungeneratedQueries->first();
                    $hasSelectedArticle = $selectedArticle !== null;
                @endphp

                {{-- ONE UNIFIED HORIZONTAL CONTROL BAR --}}
                <div class="ec-card px-3 py-2 mb-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        {{-- 1. Unified Topic & Keyword Selector --}}
                        <div class="flex-grow-1" style="min-width: 260px;">
                            <select id="unified-blog-topic-select"
                                    class="form-select form-select-sm seo-clean-input"
                                    aria-label="Select Saved Blog or New Google Search"
                                    onchange="handleUnifiedBlogSelectChange(this)">
                                <optgroup label="Saved Blog Articles">
                                    @forelse ($articles as $article)
                                        @php
                                            $isCurrentArticle = $selectedArticle && $selectedArticle->id === $article->id;
                                            $articleUrl = route('ecommerce.seo-agent.index', array_filter([
                                                'region' => $region,
                                                'audit_id' => $selectedAudit?->id,
                                                'tab' => 'blogs',
                                                'article_id' => $article->id,
                                            ]));
                                        @endphp
                                        <option value="article:{{ $article->id }}"
                                                data-type="article"
                                                data-url="{{ $articleUrl }}"
                                                {{ $isCurrentArticle ? 'selected' : '' }}>
                                            ✅ {{ $article->title }}
                                        </option>
                                    @empty
                                        <option value="" disabled>No saved blog articles yet</option>
                                    @endforelse
                                </optgroup>

                                <optgroup label="New Google Searches (Click Write to Generate)">
                                    @forelse ($ungeneratedQueries as $sq)
                                        <option value="query:{{ $sq->id }}"
                                                data-type="query"
                                                data-query-id="{{ $sq->id }}"
                                                {{ !$hasSelectedArticle && $firstUngeneratedQuery && $firstUngeneratedQuery->id === $sq->id ? 'selected' : '' }}>
                                            🔍 {{ $sq->search_query }}
                                        </option>
                                    @empty
                                        <option value="" disabled>All synced Google searches have blog articles</option>
                                    @endforelse
                                </optgroup>
                            </select>
                        </div>

                        {{-- 2. Clean Inline Action Bar (on the same row) --}}
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            {{-- Actions shown when a Saved Blog Article is selected --}}
                            <div id="blog-selected-actions" class="d-flex align-items-center gap-2 flex-wrap {{ $hasSelectedArticle ? '' : 'd-none' }}">
                                @if ($selectedArticle)
                                    @moduleEdit('ecommerce')
                                        <form method="POST" action="{{ route('ecommerce.seo-agent.publish-article') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="article_id" value="{{ $selectedArticle->id }}">
                                            <input type="hidden" name="audit_id" value="{{ $selectedAudit?->id }}">
                                            <input type="hidden" name="tab" value="blogs">
                                            <button type="submit" class="btn btn-xs seo-primary-btn">
                                                🚀 Publish to Shopify
                                            </button>
                                        </form>
                                    @endmoduleEdit

                                    <button type="button" class="btn btn-xs seo-copy-btn" onclick="copyRichHtmlBlock('blog-article-rendered-view', this)">
                                        📋 Copy
                                    </button>

                                    @moduleEdit('ecommerce')
                                        <form method="POST" action="{{ route('ecommerce.seo-agent.articles.generate', $selectedArticle) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="audit_id" value="{{ $selectedAudit?->id }}">
                                            <input type="hidden" name="tab" value="blogs">
                                            <button type="submit" class="btn btn-xs seo-copy-btn">
                                                ✨ Rewrite
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('ecommerce.seo-agent.articles.generate-image', $selectedArticle) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="audit_id" value="{{ $selectedAudit?->id }}">
                                            <input type="hidden" name="tab" value="blogs">
                                            <button type="submit" class="btn btn-xs seo-copy-btn" title="Regenerate AI Cover Image">
                                                🎨 Image
                                            </button>
                                        </form>
                                    @endmoduleEdit
                                @endif
                            </div>

                            {{-- Action shown when an ungenerated Google Search is selected in the dropdown --}}
                            @moduleEdit('ecommerce')
                                <div id="query-selected-actions" class="d-flex align-items-center gap-2 flex-wrap {{ $hasSelectedArticle ? 'd-none' : '' }}">
                                    <form method="POST" action="{{ route('ecommerce.seo-agent.create-from-query') }}" class="d-inline" onsubmit="syncUnifiedSelectedQueryId(this)">
                                        @csrf
                                        <input type="hidden" name="query_id" value="{{ $firstUngeneratedQuery?->id }}">
                                        <input type="hidden" name="audit_id" value="{{ $selectedAudit?->id }}">
                                        <input type="hidden" name="tab" value="blogs">
                                        <button type="submit" class="btn btn-xs seo-primary-btn" {{ $ungeneratedQueries->isEmpty() ? 'disabled' : '' }}>
                                            ✨ Write Blog for This Search
                                        </button>
                                    </form>
                                </div>

                                {{-- Always-available Sync & Context-Aware Delete Icon Buttons --}}
                                <form method="POST" action="{{ route('ecommerce.seo-agent.sync-gsc') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="region" value="{{ $regionUpper }}">
                                    <input type="hidden" name="audit_id" value="{{ $selectedAudit?->id }}">
                                    <input type="hidden" name="tab" value="blogs">
                                    <button type="submit" class="btn btn-xs seo-copy-btn" title="Fetch fresh Google searches">
                                        🔄
                                    </button>
                                </form>

                                @if ($selectedArticle)
                                    <form id="delete-article-form" method="POST" action="{{ route('ecommerce.seo-agent.articles.destroy', $selectedArticle) }}" class="d-inline {{ $hasSelectedArticle ? '' : 'd-none' }}" onsubmit="return confirm('Delete this blog article?')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="audit_id" value="{{ $selectedAudit?->id }}">
                                        <input type="hidden" name="tab" value="blogs">
                                        <button type="submit" class="btn btn-xs seo-delete-btn" title="Delete blog article">
                                            🗑️
                                        </button>
                                    </form>
                                @endif

                                <form id="delete-query-form" method="POST" action="{{ route('ecommerce.seo-agent.delete-query') }}" class="d-inline {{ $hasSelectedArticle ? 'd-none' : '' }}" onsubmit="if(!confirm('Remove this search phrase from the list?')) return false; syncUnifiedSelectedQueryId(this);">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="query_id" value="{{ $firstUngeneratedQuery?->id }}">
                                    <input type="hidden" name="audit_id" value="{{ $selectedAudit?->id }}">
                                    <input type="hidden" name="tab" value="blogs">
                                    <button type="submit" class="btn btn-xs seo-delete-btn" title="Delete search phrase" {{ $ungeneratedQueries->isEmpty() ? 'disabled' : '' }}>
                                        🗑️
                                    </button>
                                </form>
                            @endmoduleEdit
                        </div>
                    </div>
                </div>

                {{-- MAIN ARTICLE CARD --}}
                @if ($selectedArticle)
                    <div class="ec-card p-4 mb-0">
                        {{-- Collapsed SEO Meta Tags & Image Alt Text Drawer (Closed by Default) --}}
                        <details class="seo-schema-details mb-3 pb-2" style="border-bottom: 1px solid rgba(217, 143, 131, 0.14);">
                            <summary class="d-flex justify-content-between align-items-center py-1" style="cursor: pointer; list-style: none;">
                                <span style="font-size: 12.5px; font-weight: 600; color: var(--ec-muted);">
                                    ⚙️ View SEO Meta Tags &amp; Image Alt Text (Auto-synced on Publish)
                                </span>
                                <div class="d-flex align-items-center gap-2">
                                    @if ($selectedArticle->shopify_published_at)
                                        <span class="seo-ready-badge">
                                            ✓ Synced to Shopify ({{ $selectedArticle->shopify_published_at->format('M d, H:i') }})
                                        </span>
                                    @endif
                                    <span style="font-size: 11px; color: var(--ec-gold);">Show / Hide</span>
                                </div>
                            </summary>
                            <div class="row g-2 mt-2 pt-1">
                                <div class="col-12 mb-1">
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 p-2 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(201, 166, 107, 0.22);">
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <img src="{{ $selectedArticle->featured_image_data_uri }}"
                                                 alt="{{ $selectedArticle->image_alt_text }}"
                                                 class="h-16 w-24 object-cover rounded cursor-pointer hover:opacity-90 transition"
                                                 onclick="openSeoCoverLightbox()"
                                                 title="Click to view full-size cover image"
                                                 style="width: 96px; height: 64px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 1px solid rgba(201, 166, 107, 0.45);">
                                            <div style="font-size: 12px; color: var(--ec-muted);">
                                                🖼️ Cover Image: <code>{{ $selectedArticle->image_filename }}</code> (Auto-synced to Shopify)
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <a href="{{ route('ecommerce.seo-agent.articles.view-image', $selectedArticle) }}"
                                               target="_blank"
                                               rel="noopener"
                                               class="btn btn-xs seo-copy-btn"
                                               onclick="event.preventDefault(); openSeoCoverLightbox();">
                                                🔍 View Full Image
                                            </a>
                                            <a href="{{ route('ecommerce.seo-agent.articles.view-image', ['article' => $selectedArticle->id, 'download' => 1]) }}"
                                               download="{{ $selectedArticle->image_filename }}"
                                               class="btn btn-xs seo-copy-btn">
                                                ⬇️ Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="small fw-semibold" style="color: var(--ec-muted);">
                                            SEO Title <span style="opacity: .7;">({{ strlen((string) $selectedArticle->meta_title) }}/60)</span>
                                        </label>
                                        <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('blog-meta-title-input', this)">📋 Copy</button>
                                    </div>
                                    <input id="blog-meta-title-input" type="text" class="form-control seo-clean-input" value="{{ $selectedArticle->meta_title }}" readonly>
                                </div>
                                <div class="col-md-7">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <label class="small fw-semibold" style="color: var(--ec-muted);">
                                            SEO Description <span style="opacity: .7;">({{ strlen((string) $selectedArticle->meta_description) }}/155)</span>
                                        </label>
                                        <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('blog-meta-desc-input', this)">📋 Copy</button>
                                    </div>
                                    <input id="blog-meta-desc-input" type="text" class="form-control seo-clean-input" value="{{ $selectedArticle->meta_description }}" readonly>
                                </div>
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
                        </details>

                        <div id="blog-article-rendered-view" class="seo-clean-preview-box seo-rendered-description p-4 rounded">
                            @if ($selectedArticle->article_markdown)
                                {!! $selectedArticle->article_markdown !!}
                            @else
                                <p class="mb-0 text-muted">Click "Rewrite" above to generate this blog post.</p>
                            @endif
                        </div>
                        <textarea id="blog-article-copy-box" class="d-none" readonly>{{ $selectedArticle->article_markdown }}</textarea>
                    </div>

                    {{-- Full-Screen Lightbox Modal for Cover Image Preview --}}
                    <div id="seo-cover-lightbox-modal"
                         class="d-none"
                         onclick="if(event.target === this) closeSeoCoverLightbox()"
                         style="position: fixed; inset: 0; z-index: 9999; background: rgba(10, 8, 7, 0.88); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; padding: 24px;">
                        <div class="ec-card p-3" style="max-width: 920px; width: 100%; border: 1px solid rgba(201, 166, 107, 0.45); box-shadow: 0 20px 60px rgba(0,0,0,0.75);">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2 pb-2" style="border-bottom: 1px solid rgba(217, 143, 131, 0.18);">
                                <div style="font-size: 12.5px; color: #f6ebe6;">
                                    🖼️ <strong>{{ $selectedArticle->image_filename }}</strong>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="{{ route('ecommerce.seo-agent.articles.view-image', $selectedArticle) }}"
                                       target="_blank"
                                       rel="noopener"
                                       class="btn btn-xs seo-copy-btn">
                                        ↗ Open in New Tab
                                    </a>
                                    <a href="{{ route('ecommerce.seo-agent.articles.view-image', ['article' => $selectedArticle->id, 'download' => 1]) }}"
                                       download="{{ $selectedArticle->image_filename }}"
                                       class="btn btn-xs seo-primary-btn">
                                        ⬇️ Download
                                    </a>
                                    <button type="button" class="btn btn-xs seo-delete-btn" onclick="closeSeoCoverLightbox()" title="Close preview">
                                        ✕
                                    </button>
                                </div>
                            </div>
                            <div class="text-center">
                                <img src="{{ $selectedArticle->featured_image_data_uri }}"
                                     alt="{{ $selectedArticle->image_alt_text }}"
                                     style="max-width: 100%; max-height: 74vh; object-fit: contain; border-radius: 8px;">
                            </div>
                            <div class="mt-2 pt-1 text-center" style="font-size: 11.5px; color: var(--ec-muted);">
                                Alt Text: {{ $selectedArticle->image_alt_text }}
                            </div>
                        </div>
                    </div>
                @endif
            @elseif ($selectedAudit)
                {{-- TAB 1: PAGE SEO (TOP ACTION BAR + SINGLE MAIN CONTENT SHEET + COLLAPSED DETAILS FOOTER) --}}
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

                {{-- 1. Top Action Bar --}}
                <div class="ec-card px-3 py-2 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-semibold" style="color: #f6ebe6; font-size: 14px;">
                            {{ $selectedAudit->target_name }}
                        </span>
                        @if ($selectedAudit->shopify_published_at)
                            <span class="seo-ready-badge">
                                ✓ Synced to Shopify ({{ $selectedAudit->shopify_published_at->format('M d, H:i') }})
                            </span>
                        @endif
                    </div>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        @if ($selectedAudit->target_type === 'product')
                            @moduleEdit('ecommerce')
                                <form method="POST" action="{{ route('ecommerce.seo-agent.publish-product') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="audit_id" value="{{ $selectedAudit->id }}">
                                    <button type="submit" class="btn btn-xs seo-primary-btn">
                                        🚀 Publish to Shopify
                                    </button>
                                </form>
                            @endmoduleEdit
                        @endif

                        <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('seo-shopify-html-hidden', this)">
                            📋 Copy Description
                        </button>

                        @moduleEdit('ecommerce')
                            <form method="POST" action="{{ route('ecommerce.seo-agent.optimize') }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="region" value="{{ $regionUpper }}">
                                <input type="hidden" name="target_type" value="{{ $selectedAudit->target_type }}">
                                <input type="hidden" name="ecommerce_product_id" value="{{ $selectedAudit->ecommerce_product_id }}">
                                <input type="hidden" name="target_name" value="{{ $selectedAudit->target_name }}">
                                <button type="submit" class="btn btn-xs seo-copy-btn">
                                    ✨ Regenerate
                                </button>
                            </form>
                        @endmoduleEdit
                    </div>
                </div>

                {{-- 2. Main Content Card (Google Snippet Preview + Readable Description & FAQs + Collapsed Details Footer) --}}
                <div class="ec-card p-4 mb-0">
                    {{-- Clean Google Search Snippet Preview --}}
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

                    {{-- Readable Product Description + FAQs in One Clean Sheet --}}
                    <div class="seo-clean-preview-box p-3 rounded mb-3">
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

                    {{-- 3. Collapsed <details> Footer for Manual Meta Tags, Image Alt & Schema Code --}}
                    <details class="seo-schema-details pt-2" style="border-top: 1px solid rgba(217, 143, 131, 0.14);">
                        <summary class="d-flex justify-content-between align-items-center py-1" style="cursor: pointer; list-style: none;">
                            <span style="font-size: 12.5px; font-weight: 600; color: var(--ec-muted);">
                                ⚙️ Manual Meta Tags, Image Alt &amp; Schema Code
                            </span>
                            <span style="font-size: 11px; color: var(--ec-gold);">Show / Hide</span>
                        </summary>

                        <div class="row g-2 mt-2 pt-1">
                            <div class="col-md-5">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="small fw-semibold" style="color: var(--ec-muted);">
                                        Meta Title <span style="opacity: .7;">({{ strlen((string) $selectedAudit->meta_title) }}/60)</span>
                                    </label>
                                    <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('seo-meta-title-input', this)">📋 Copy</button>
                                </div>
                                <input id="seo-meta-title-input" type="text" class="form-control seo-clean-input" value="{{ $selectedAudit->meta_title }}" readonly>
                            </div>

                            <div class="col-md-7">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="small fw-semibold" style="color: var(--ec-muted);">
                                        Meta Description <span style="opacity: .7;">({{ strlen((string) $selectedAudit->meta_description) }}/155)</span>
                                    </label>
                                    <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('seo-meta-desc-input', this)">📋 Copy</button>
                                </div>
                                <input id="seo-meta-desc-input" type="text" class="form-control seo-clean-input" value="{{ $selectedAudit->meta_description }}" readonly>
                            </div>

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

                            <div class="col-12 mt-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="small fw-semibold" style="color: var(--ec-muted);">JSON-LD Schema Code</label>
                                    <button type="button" class="btn btn-xs seo-copy-btn" onclick="copySeoBlock('seo-jsonld-box', this)">📋 Copy Schema Code</button>
                                </div>
                                <textarea id="seo-jsonld-box" class="form-control seo-clean-input" rows="5" style="font-family: monospace; font-size: 11.5px;" readonly>{{ $selectedAudit->json_ld_schema }}</textarea>
                            </div>
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
        .seo-delete-btn {
            background: rgba(220, 88, 88, 0.14);
            border: 1px solid rgba(220, 88, 88, 0.35);
            color: #f7c6c6;
            font-size: 11.5px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 7px;
        }
        .seo-delete-btn:hover {
            background: rgba(220, 88, 88, 0.28);
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
        function openSeoCoverLightbox() {
            const modal = document.getElementById('seo-cover-lightbox-modal');
            if (modal) {
                modal.classList.remove('d-none');
            }
        }

        function closeSeoCoverLightbox() {
            const modal = document.getElementById('seo-cover-lightbox-modal');
            if (modal) {
                modal.classList.add('d-none');
            }
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeSeoCoverLightbox();
            }
        });

        function handleUnifiedBlogSelectChange(select) {
            if (!select) return;
            const selectedOption = select.options[select.selectedIndex];
            if (!selectedOption) return;

            const optionType = selectedOption.getAttribute('data-type');
            const blogActions = document.getElementById('blog-selected-actions');
            const queryActions = document.getElementById('query-selected-actions');
            const deleteArticleForm = document.getElementById('delete-article-form');
            const deleteQueryForm = document.getElementById('delete-query-form');

            if (optionType === 'article') {
                const targetUrl = selectedOption.getAttribute('data-url');
                if (targetUrl) {
                    window.location = targetUrl;
                }
                return;
            }

            if (optionType === 'query') {
                const queryId = selectedOption.getAttribute('data-query-id');
                if (blogActions) blogActions.classList.add('d-none');
                if (queryActions) queryActions.classList.remove('d-none');
                if (deleteArticleForm) deleteArticleForm.classList.add('d-none');
                if (deleteQueryForm) deleteQueryForm.classList.remove('d-none');

                document.querySelectorAll('#query-selected-actions input[name="query_id"], #delete-query-form input[name="query_id"]').forEach((input) => {
                    input.value = queryId || '';
                });
            }
        }

        function syncUnifiedSelectedQueryId(form) {
            const select = document.getElementById('unified-blog-topic-select');
            if (!select || !form) return;
            const selectedOption = select.options[select.selectedIndex];
            if (!selectedOption) return;
            const queryId = selectedOption.getAttribute('data-query-id');
            const input = form.querySelector('input[name="query_id"]');
            if (input && queryId) {
                input.value = queryId;
            }
        }

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
