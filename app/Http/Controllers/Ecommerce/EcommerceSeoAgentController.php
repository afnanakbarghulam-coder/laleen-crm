<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use App\Models\EcommerceSeoArticle;
use App\Models\EcommerceSeoAudit;
use App\Models\EcommerceSeoSearchQuery;
use App\Services\Ecommerce\EcommerceSeoAgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class EcommerceSeoAgentController extends Controller
{
    public function __construct(
        protected EcommerceSeoAgentService $seoService
    ) {
    }

    /**
     * GET /ecommerce/hub/seo-agent
     */
    public function index(Request $request)
    {
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $regionUpper = $this->seoService->normalizeRegion($region);
        $currency = EcommerceSale::currencyForRegion($region);
        $rawTab = strtolower((string) $request->query('tab', 'onpage'));
        $isBlogTab = in_array($rawTab, ['topical', 'blogs', 'blog', 'searches'], true);
        $activeTab = $isBlogTab ? 'topical' : 'onpage';
        $activeTabParam = $isBlogTab ? (in_array($rawTab, ['blogs', 'topical'], true) ? $rawTab : 'blogs') : ($rawTab === 'page' ? 'page' : 'onpage');
        $showAllTargets = $request->boolean('show_all');

        $products = EcommerceProduct::orderBy('id')->get();
        $salonBranches = $this->seoService->getDefaultSalonBranches();

        // Ensure audits exist (and have Image SEO fields) for current region products, Hair Recovery Kit, & salon branches
        foreach ($products as $product) {
            $existingAudit = EcommerceSeoAudit::where('region', $regionUpper)
                ->where('target_type', 'product')
                ->where('ecommerce_product_id', $product->id)
                ->first();
            if (!$existingAudit || empty($existingAudit->image_alt_text) || empty($existingAudit->image_filename)) {
                $this->seoService->auditAndOptimizeTarget($product, $regionUpper);
            }
        }

        $kitAuditExists = EcommerceSeoAudit::where('region', $regionUpper)
            ->where('target_type', 'product')
            ->where('target_url_slug', 'hair-recovery-kit')
            ->exists();
        if (!$kitAuditExists) {
            $this->seoService->auditAndOptimizeTarget('Hair Recovery Kit', $regionUpper, 'product');
        }

        foreach ($salonBranches as $branch) {
            $existingBranchAudit = EcommerceSeoAudit::where('region', $regionUpper)
                ->where('target_type', 'salon_branch')
                ->where('target_url_slug', $branch['slug'])
                ->first();
            if (!$existingBranchAudit || empty($existingBranchAudit->image_alt_text)) {
                $this->seoService->auditAndOptimizeTarget($branch['name'], $regionUpper, 'salon_branch');
            }
        }

        // Ensure Topical Authority Map is seeded for all left-sidebar targets in current region
        $expectedBlueprintCount = count($this->seoService->getRegionalTopicalBlueprints($regionUpper));
        $hasLegacyCannibalSlug = EcommerceSeoArticle::where('region', $regionUpper)
            ->whereIn('slug', [
                '100-pure-cold-pressed-hunza-apricot-oil-for-hairfall',
                'sulfate-free-shampoo-oily-scalp-monsoon-frizz-pakistan',
                'hunza-apricot-kernel-oil-vs-mineral-hair-oils',
            ])
            ->exists();

        if (EcommerceSeoArticle::where('region', $regionUpper)->count() < $expectedBlueprintCount || $hasLegacyCannibalSlug) {
            $this->seoService->generateTopicalAuthorityMap($regionUpper);
        }

        // Ensure Live Google Keyword Discovery queries are seeded for current region without blocking GET with external HTTP calls
        if (
            EcommerceSeoSearchQuery::where('region', $regionUpper)->count() < 6
            || EcommerceSeoSearchQuery::where('region', $regionUpper)->where('impressions', '>', 0)->exists()
        ) {
            EcommerceSeoSearchQuery::where('region', $regionUpper)->where('impressions', '>', 0)->delete();
            $this->seoService->syncGoogleSearchConsoleData($regionUpper, null, false);
        }

        $allSearchQueries = EcommerceSeoSearchQuery::with('linkedArticle')
            ->where('region', $regionUpper)
            ->orderByRaw("CASE WHEN opportunity_type = 'quick_win' THEN 0 WHEN opportunity_type = 'content_gap' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->get();

        $audits = EcommerceSeoAudit::with('product')
            ->where('region', $regionUpper)
            ->orderByRaw("CASE WHEN target_type = 'product' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();

        $productAudits = $audits->where('target_type', 'product')->values();
        $salonAudits = $audits->where('target_type', 'salon_branch')->values();

        $selectedAuditId = $request->query('audit_id');
        $selectedProductId = $request->query('product_id');
        $selectedSlug = $request->query('target_slug');

        $selectedAudit = null;
        if ($selectedAuditId) {
            $selectedAudit = $audits->firstWhere('id', (int) $selectedAuditId);
        } elseif ($selectedProductId) {
            $selectedAudit = $productAudits->firstWhere('ecommerce_product_id', (int) $selectedProductId);
        } elseif ($selectedSlug) {
            $selectedAudit = $audits->firstWhere('target_url_slug', (string) $selectedSlug);
        }

        if (!$selectedAudit) {
            $selectedAudit = $audits->first();
        }

        $selectedTargetUrl = $selectedAudit ? $this->seoService->resolveTargetCanonicalUrl($selectedAudit) : null;

        $allArticles = EcommerceSeoArticle::where('region', $regionUpper)
            ->orderBy('id')
            ->get();

        foreach ($allArticles as $idx => $art) {
            $rawBody = (string) $art->article_markdown;
            if (
                empty($art->image_alt_text)
                || ($rawBody !== '' && (str_contains($rawBody, '## ') || str_contains($rawBody, '**') || str_contains($rawBody, '| :--- |') || !str_contains($rawBody, 'Related Hair Care Guides')))
            ) {
                $allArticles[$idx] = $this->seoService->generateSkyscraperArticle($art, false);
            }
        }

        if (!$showAllTargets && $selectedTargetUrl) {
            $searchQueries = $allSearchQueries->filter(
                fn (EcommerceSeoSearchQuery $sq) => $this->seoService->matchesTargetUrl($sq->target_url, $selectedTargetUrl, $sq->search_query)
            )->values();

            $articles = $allArticles->filter(
                fn (EcommerceSeoArticle $art) => $this->seoService->matchesTargetUrl($art->internal_link_target_url, $selectedTargetUrl, $art->title . ' ' . $art->target_keyword)
            )->values();
        } else {
            $searchQueries = $allSearchQueries;
            $articles = $allArticles;
        }

        $selectedArticleId = $request->query('article_id');
        $selectedArticle = $selectedArticleId
            ? $articles->firstWhere('id', (int) $selectedArticleId)
            : null;

        if (!$selectedArticle) {
            $selectedArticle = $articles->firstWhere('status', 'ready') ?? $articles->first();
        }

        if ($selectedArticle && empty($selectedArticle->article_markdown)) {
            $selectedArticle = $this->seoService->generateSkyscraperArticle($selectedArticle, false);
            $articles = $articles->map(fn ($a) => $a->id === $selectedArticle->id ? $selectedArticle : $a);
        }

        if (
            $selectedArticle
            && (empty($selectedArticle->featured_image_path) || !Storage::disk('public')->exists($selectedArticle->featured_image_path))
        ) {
            $selectedArticle = $this->seoService->generateArticleImage($selectedArticle, false);
            $articles = $articles->map(fn ($a) => $a->id === $selectedArticle->id ? $selectedArticle : $a);
        }

        // Top KPI Row calculations
        $avgSeoScore = $audits->count() > 0 ? (int) round($audits->avg('seo_score')) : 0;
        $optimizedProductPagesCount = $productAudits->where('seo_score', '>=', 90)->count();
        $totalProductPagesCount = $productAudits->count();
        $topicalClustersCount = $articles->count();
        $totalArticlesCount = $allArticles->count();
        $totalSearchQueriesCount = $allSearchQueries->count();
        $readyArticlesCount = $allArticles->whereIn('status', ['ready', 'published'])->count();
        $schemaCoveredCount = $audits->filter(
            fn (EcommerceSeoAudit $a) => str_contains((string) $a->json_ld_schema, 'application/ld+json')
        )->count();
        $schemaCoveragePercent = $audits->count() > 0
            ? (int) round(($schemaCoveredCount / $audits->count()) * 100)
            : 100;

        return view('ecommerce.seo-agent', [
            'region' => strtolower($regionUpper),
            'regionUpper' => $regionUpper,
            'currency' => $currency,
            'activeTab' => $activeTab,
            'activeTabParam' => $activeTabParam,
            'showAllTargets' => $showAllTargets,
            'products' => $products,
            'salonBranches' => $salonBranches,
            'audits' => $audits,
            'productAudits' => $productAudits,
            'salonAudits' => $salonAudits,
            'selectedAudit' => $selectedAudit,
            'selectedTargetUrl' => $selectedTargetUrl,
            'articles' => $articles,
            'selectedArticle' => $selectedArticle,
            'searchQueries' => $searchQueries,
            'avgSeoScore' => $avgSeoScore,
            'optimizedProductPagesCount' => $optimizedProductPagesCount,
            'totalProductPagesCount' => $totalProductPagesCount,
            'topicalClustersCount' => $topicalClustersCount,
            'totalArticlesCount' => $totalArticlesCount,
            'totalSearchQueriesCount' => $totalSearchQueriesCount,
            'readyArticlesCount' => $readyArticlesCount,
            'schemaCoveragePercent' => $schemaCoveragePercent,
        ]);
    }

    /**
     * POST /ecommerce/hub/seo-agent/optimize
     */
    public function optimizeTarget(Request $request): RedirectResponse|JsonResponse
    {
        $region = $this->seoService->normalizeRegion($request->input('region', 'PK'));
        $targetType = (string) $request->input('target_type', 'product');
        $productId = $request->input('ecommerce_product_id');
        $targetName = (string) $request->input('target_name', '');

        if ($targetType === 'salon_branch') {
            $audit = $this->seoService->auditAndOptimizeTarget($targetName ?: 'Old Airport, Doha', $region, 'salon_branch');
        } elseif ($productId) {
            $product = EcommerceProduct::findOrFail((int) $productId);
            $audit = $this->seoService->auditAndOptimizeTarget($product, $region);
        } else {
            $audit = $this->seoService->auditAndOptimizeTarget($targetName ?: 'Hunza Apricot Oil', $region, $targetType);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'audit' => $audit,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', [
                'region' => strtolower($region),
                'audit_id' => $audit->id,
                'tab' => 'onpage',
            ])
            ->with('status', "SEO Master optimized \"{$audit->target_name}\" ({$audit->seo_score}/100 Gotch Score).");
    }

    /**
     * POST /ecommerce/hub/seo-agent/optimize-all
     */
    public function optimizeAll(Request $request): RedirectResponse|JsonResponse
    {
        $region = $this->seoService->normalizeRegion($request->input('region', 'PK'));
        $audits = $this->seoService->optimizeAllForRegion($region);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'count' => $audits->count(),
                'audits' => $audits,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', [
                'region' => strtolower($region),
                'tab' => 'onpage',
            ])
            ->with('status', "All {$audits->count()} product & salon targets optimized for {$region}.");
    }

    /**
     * POST /ecommerce/hub/seo-agent/topical-map
     */
    public function generateTopicalMap(Request $request): RedirectResponse|JsonResponse
    {
        $region = $this->seoService->normalizeRegion($request->input('region', 'PK'));
        $mode = (string) $request->input('mode', 'all');

        $articles = $this->seoService->generateTopicalAuthorityMap($region, $mode);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'articles' => $articles,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', [
                'region' => strtolower($region),
                'tab' => 'topical',
            ])
            ->with('status', "Topical Authority Map generated ({$articles->count()} clusters for {$region}).");
    }

    /**
     * POST /ecommerce/hub/seo-agent/articles/{article}/generate
     */
    public function generateArticle(Request $request, ?EcommerceSeoArticle $article = null): RedirectResponse|JsonResponse
    {
        $articleModel = $article ?: EcommerceSeoArticle::findOrFail((int) $request->input('article_id'));
        $updated = $this->seoService->generateSkyscraperArticle($articleModel);
        $auditId = $request->input('audit_id');
        $tab = (string) $request->input('tab', 'topical');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'article' => $updated,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', array_filter([
                'region' => strtolower($updated->region),
                'audit_id' => $auditId,
                'tab' => $tab,
                'article_id' => $updated->id,
            ]))
            ->with('status', "Skyscraper article \"{$updated->title}\" generated with APP intro & internal product silo links.");
    }

    /**
     * POST /ecommerce/hub/seo-agent/articles/{article}/generate-image
     */
    public function generateArticleImage(Request $request, ?EcommerceSeoArticle $article = null): RedirectResponse|JsonResponse
    {
        $articleId = $request->input('article_id') ?? $request->input('id');
        $auditId = $request->input('audit_id');
        $tab = (string) $request->input('tab', 'blogs');

        $articleModel = $article ?: EcommerceSeoArticle::findOrFail((int) $articleId);
        $updated = $this->seoService->generateArticleImage($articleModel);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'article' => $updated,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', array_filter([
                'region' => strtolower($updated->region),
                'audit_id' => $auditId,
                'tab' => $tab,
                'article_id' => $updated->id,
            ]))
            ->with('status', "AI Cover Image generated for \"{$updated->title}\" ({$updated->image_filename}).");
    }

    /**
     * GET /ecommerce/seo-agent/articles/{article}/image
     * Serves the blog cover image directly from Storage::disk('public') with proper MIME type and SEO filename.
     */
    public function viewArticleImage(Request $request, EcommerceSeoArticle $article): Response
    {
        if (empty($article->featured_image_path) || !Storage::disk('public')->exists($article->featured_image_path)) {
            $article = $this->seoService->generateArticleImage($article);
        }

        $bytes = (string) Storage::disk('public')->get($article->featured_image_path);
        if (strlen($bytes) < 16) {
            $article = $this->seoService->generateArticleImage($article);
            $bytes = (string) Storage::disk('public')->get($article->featured_image_path);
        }

        $trimmed = ltrim($bytes);
        if (str_starts_with($trimmed, '<svg') || str_starts_with($trimmed, '<?xml')) {
            $mime = 'image/svg+xml';
        } elseif (str_starts_with($bytes, "\x89PNG")) {
            $mime = 'image/png';
        } else {
            $mime = 'image/jpeg';
        }

        $filename = $article->image_filename ?: ($article->slug . '-laleen-rituals.jpg');
        $dispositionType = $request->boolean('download') ? 'attachment' : 'inline';

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => "{$dispositionType}; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * POST /ecommerce/hub/seo-agent/sync-gsc
     */
    public function syncSearchConsole(Request $request): RedirectResponse|JsonResponse
    {
        $region = $this->seoService->normalizeRegion($request->input('region', 'PK'));
        $auditId = $request->input('audit_id');
        $targetSlug = $request->input('target_slug');
        $tab = (string) $request->input('tab', 'topical');

        $queries = $this->seoService->syncGoogleSearchConsoleData($region, $auditId ?: $targetSlug);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'count' => $queries->count(),
                'queries' => $queries,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', array_filter([
                'region' => strtolower($region),
                'audit_id' => $auditId,
                'tab' => $tab,
            ]))
            ->with('status', "Synced {$queries->count()} live Google Search queries for {$region}.");
    }

    /**
     * POST /ecommerce/hub/seo-agent/create-from-query
     */
    public function createArticleFromQuery(Request $request, ?EcommerceSeoSearchQuery $searchQuery = null): RedirectResponse|JsonResponse
    {
        $queryId = $request->input('query_id') ?? $request->input('search_query_id');
        $auditId = $request->input('audit_id');
        $targetSlug = $request->input('target_slug');
        $tab = (string) $request->input('tab', 'topical');

        $queryModel = $searchQuery ?: EcommerceSeoSearchQuery::findOrFail((int) $queryId);
        $article = $this->seoService->createBlogArticleFromSearchQuery($queryModel, $auditId ?: $targetSlug);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'article' => $article,
                'search_query' => $queryModel->fresh(),
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', array_filter([
                'region' => strtolower($article->region),
                'audit_id' => $auditId,
                'tab' => $tab,
                'article_id' => $article->id,
            ]))
            ->with('status', "Blog post written for Google search \"{$queryModel->search_query}\" and ready to copy.");
    }

    /**
     * POST /ecommerce/hub/seo-agent/publish-product
     */
    public function publishProduct(Request $request, ?EcommerceSeoAudit $audit = null): RedirectResponse|JsonResponse
    {
        $auditId = $request->input('audit_id') ?? $request->input('id');
        $auditModel = $audit ?: EcommerceSeoAudit::findOrFail((int) $auditId);
        $published = $this->seoService->publishProductSeoToShopify($auditModel);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'audit' => $published,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', [
                'region' => strtolower($published->region),
                'audit_id' => $published->id,
                'tab' => 'onpage',
            ])
            ->with('status', "Published SEO & Image Alt Text for \"{$published->target_name}\" to Shopify.");
    }

    /**
     * POST /ecommerce/hub/seo-agent/publish-article
     */
    public function publishArticle(Request $request, ?EcommerceSeoArticle $article = null): RedirectResponse|JsonResponse
    {
        $articleId = $request->input('article_id') ?? $request->input('id');
        $auditId = $request->input('audit_id');
        $tab = (string) $request->input('tab', 'topical');

        $articleModel = $article ?: EcommerceSeoArticle::findOrFail((int) $articleId);
        $published = $this->seoService->publishBlogArticleToShopify($articleModel);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'article' => $published,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', array_filter([
                'region' => strtolower($published->region),
                'audit_id' => $auditId,
                'tab' => $tab,
                'article_id' => $published->id,
            ]))
            ->with('status', "Published blog article \"{$published->title}\" to Shopify.");
    }

    /**
     * DELETE/POST /ecommerce/hub/seo-agent/articles/{article}
     */
    public function deleteArticle(Request $request, ?EcommerceSeoArticle $article = null): RedirectResponse|JsonResponse
    {
        $articleId = $request->input('article_id') ?? $request->input('id');
        $auditId = $request->input('audit_id');
        $tab = (string) $request->input('tab', 'topical');

        $articleModel = $article ?: EcommerceSeoArticle::findOrFail((int) $articleId);

        $region = strtolower((string) $articleModel->region);
        $regionUpper = strtoupper($region);
        $deletedTitle = $articleModel->title;

        EcommerceSeoSearchQuery::where('linked_article_id', $articleModel->id)
            ->update(['linked_article_id' => null]);

        $articleModel->delete();

        $nextArticle = EcommerceSeoArticle::where('region', $regionUpper)
            ->orderBy('id')
            ->first();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'deleted_title' => $deletedTitle,
                'next_article_id' => $nextArticle?->id,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', array_filter([
                'region' => $region,
                'audit_id' => $auditId,
                'tab' => $tab,
                'article_id' => $nextArticle?->id,
            ]))
            ->with('status', "Deleted blog article \"{$deletedTitle}\".");
    }

    /**
     * DELETE/POST /ecommerce/hub/seo-agent/queries/{searchQuery}
     */
    public function deleteSearchQuery(Request $request, ?EcommerceSeoSearchQuery $searchQuery = null): RedirectResponse|JsonResponse
    {
        $queryId = $request->input('query_id') ?? $request->input('search_query_id') ?? $request->input('id');
        $auditId = $request->input('audit_id');
        $tab = (string) $request->input('tab', 'topical');

        $queryModel = $searchQuery ?: EcommerceSeoSearchQuery::findOrFail((int) $queryId);

        $region = strtolower((string) $queryModel->region);
        $deletedPhrase = $queryModel->search_query;

        $queryModel->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'deleted_phrase' => $deletedPhrase,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', array_filter([
                'region' => $region,
                'audit_id' => $auditId,
                'tab' => $tab,
            ]))
            ->with('status', "Removed Google search phrase \"{$deletedPhrase}\".");
    }
}
