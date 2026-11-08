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
        $activeTab = (string) $request->query('tab', 'onpage');

        $products = EcommerceProduct::orderBy('id')->get();
        $salonBranches = $this->seoService->getDefaultSalonBranches();

        // Ensure audits exist (and have Image SEO fields) for current region products & salon branches
        foreach ($products as $product) {
            $existingAudit = EcommerceSeoAudit::where('region', $regionUpper)
                ->where('target_type', 'product')
                ->where('ecommerce_product_id', $product->id)
                ->first();
            if (!$existingAudit || empty($existingAudit->image_alt_text) || empty($existingAudit->image_filename)) {
                $this->seoService->auditAndOptimizeTarget($product, $regionUpper);
            }
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

        // Ensure Topical Authority Map is seeded (and legacy cannibalizing PK slugs are upgraded) for current region
        $hasLegacyCannibalSlug = EcommerceSeoArticle::where('region', $regionUpper)
            ->whereIn('slug', [
                '100-pure-cold-pressed-hunza-apricot-oil-for-hairfall',
                'sulfate-free-shampoo-oily-scalp-monsoon-frizz-pakistan',
                'hunza-apricot-kernel-oil-vs-mineral-hair-oils',
            ])
            ->exists();

        if (EcommerceSeoArticle::where('region', $regionUpper)->count() === 0 || $hasLegacyCannibalSlug) {
            $this->seoService->generateTopicalAuthorityMap($regionUpper);
        }

        // Ensure Live Google Keyword Discovery queries are synced for current region (and purge any legacy fake metrics)
        if (
            EcommerceSeoSearchQuery::where('region', $regionUpper)->count() === 0
            || EcommerceSeoSearchQuery::where('region', $regionUpper)->where('impressions', '>', 0)->exists()
        ) {
            EcommerceSeoSearchQuery::where('region', $regionUpper)->where('impressions', '>', 0)->delete();
            $this->seoService->syncGoogleSearchConsoleData($regionUpper);
        }

        $searchQueries = EcommerceSeoSearchQuery::with('linkedArticle')
            ->where('region', $regionUpper)
            ->orderByRaw("CASE WHEN opportunity_type = 'quick_win' THEN 0 WHEN opportunity_type = 'content_gap' THEN 1 ELSE 2 END")
            ->orderBy('avg_position')
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

        $articles = EcommerceSeoArticle::where('region', $regionUpper)
            ->orderBy('id')
            ->get();

        foreach ($articles as $idx => $art) {
            $rawBody = (string) $art->article_markdown;
            if (
                empty($art->image_alt_text)
                || ($rawBody !== '' && (str_contains($rawBody, '## ') || str_contains($rawBody, '**') || str_contains($rawBody, '| :--- |') || !str_contains($rawBody, 'Related Hair Care Guides')))
            ) {
                $articles[$idx] = $this->seoService->generateSkyscraperArticle($art);
            }
        }

        $selectedArticleId = $request->query('article_id');
        $selectedArticle = $selectedArticleId
            ? $articles->firstWhere('id', (int) $selectedArticleId)
            : ($articles->firstWhere('status', 'ready') ?? $articles->first());

        // Top KPI Row calculations
        $avgSeoScore = $audits->count() > 0 ? (int) round($audits->avg('seo_score')) : 0;
        $optimizedProductPagesCount = $productAudits->where('seo_score', '>=', 90)->count();
        $totalProductPagesCount = $productAudits->count();
        $topicalClustersCount = $articles->count();
        $readyArticlesCount = $articles->whereIn('status', ['ready', 'published'])->count();
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
            'products' => $products,
            'salonBranches' => $salonBranches,
            'audits' => $audits,
            'productAudits' => $productAudits,
            'salonAudits' => $salonAudits,
            'selectedAudit' => $selectedAudit,
            'articles' => $articles,
            'selectedArticle' => $selectedArticle,
            'searchQueries' => $searchQueries,
            'avgSeoScore' => $avgSeoScore,
            'optimizedProductPagesCount' => $optimizedProductPagesCount,
            'totalProductPagesCount' => $totalProductPagesCount,
            'topicalClustersCount' => $topicalClustersCount,
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

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'article' => $updated,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', [
                'region' => strtolower($updated->region),
                'tab' => 'topical',
                'article_id' => $updated->id,
            ])
            ->with('status', "Skyscraper article \"{$updated->title}\" generated with APP intro & internal product silo links.");
    }

    /**
     * POST /ecommerce/hub/seo-agent/sync-gsc
     */
    public function syncSearchConsole(Request $request): RedirectResponse|JsonResponse
    {
        $region = $this->seoService->normalizeRegion($request->input('region', 'PK'));
        $queries = $this->seoService->syncGoogleSearchConsoleData($region);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'count' => $queries->count(),
                'queries' => $queries,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', [
                'region' => strtolower($region),
                'tab' => 'searches',
            ])
            ->with('status', "Synced {$queries->count()} live Google Search queries for {$region}.");
    }

    /**
     * POST /ecommerce/hub/seo-agent/create-from-query
     */
    public function createArticleFromQuery(Request $request, ?EcommerceSeoSearchQuery $searchQuery = null): RedirectResponse|JsonResponse
    {
        $queryId = $request->input('query_id') ?? $request->input('search_query_id');
        $queryModel = $searchQuery ?: EcommerceSeoSearchQuery::findOrFail((int) $queryId);
        $article = $this->seoService->createBlogArticleFromSearchQuery($queryModel);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'article' => $article,
                'search_query' => $queryModel->fresh(),
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', [
                'region' => strtolower($article->region),
                'tab' => 'topical',
                'article_id' => $article->id,
            ])
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
        $articleModel = $article ?: EcommerceSeoArticle::findOrFail((int) $articleId);
        $published = $this->seoService->publishBlogArticleToShopify($articleModel);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'article' => $published,
            ]);
        }

        return redirect()
            ->route('ecommerce.seo-agent.index', [
                'region' => strtolower($published->region),
                'tab' => 'topical',
                'article_id' => $published->id,
            ])
            ->with('status', "Published blog article \"{$published->title}\" to Shopify.");
    }
}
