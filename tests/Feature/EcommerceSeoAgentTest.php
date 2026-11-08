<?php

namespace Tests\Feature;

use App\Models\EcommerceProduct;
use App\Models\EcommerceSeoArticle;
use App\Models\EcommerceSeoAudit;
use App\Models\EcommerceSeoSearchQuery;
use App\Models\User;
use App\Services\Ecommerce\EcommerceSeoAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EcommerceSeoAgentTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected EcommerceProduct $oilProduct;
    protected EcommerceProduct $shampooProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->oilProduct = EcommerceProduct::create([
            'name' => 'Hunza Apricot Oil',
            'sku' => 'LAL-OIL-100',
            'shopify_url' => 'https://laleenrituals.com/products/hunza-apricot-oil',
            'unit_size' => '100ml',
            'stock_pakistan' => 100,
            'stock_qatar' => 50,
            'selling_price' => 1500,
            'volume_ml' => 100,
        ]);

        $this->shampooProduct = EcommerceProduct::create([
            'name' => 'Sulfate-Free Botanical Shampoo',
            'sku' => 'LAL-SHM-100',
            'shopify_url' => 'https://laleenrituals.com/products/sulfate-free-botanical-shampoo',
            'unit_size' => '250ml',
            'stock_pakistan' => 80,
            'stock_qatar' => 40,
            'selling_price' => 2106,
            'volume_ml' => 250,
        ]);
    }

    public function test_auditing_and_optimizing_products_for_pk_and_qa_enforces_gotch_sops_hunza_entities_and_product_schema(): void
    {
        /** @var EcommerceSeoAgentService $seoService */
        $seoService = app(EcommerceSeoAgentService::class);

        foreach (['PK', 'QA'] as $region) {
            foreach ([$this->oilProduct, $this->shampooProduct] as $product) {
                $audit = $seoService->auditAndOptimizeTarget($product, $region);

                $this->assertInstanceOf(EcommerceSeoAudit::class, $audit);
                $this->assertSame($region, $audit->region);
                $this->assertSame('product', $audit->target_type);
                $this->assertGreaterThanOrEqual(90, $audit->seo_score);

                // Strict character limits (< 60 char meta title, < 155 char meta description)
                $this->assertLessThan(60, strlen((string) $audit->meta_title));
                $this->assertGreaterThan(10, strlen((string) $audit->meta_title));
                $this->assertLessThan(155, strlen((string) $audit->meta_description));
                $this->assertGreaterThanOrEqual(120, strlen((string) $audit->meta_description));

                // Hunza provenance entities & clean customer-facing headings (zero raw SEO framework jargon)
                $this->assertStringContainsString(
                    'cold-pressed from raw kernels handpicked in the Hunza valleys',
                    (string) $audit->optimized_body_html
                );
                $this->assertStringContainsString('Why It Works: Pure Hunza Botanicals', (string) $audit->optimized_body_html);
                $this->assertStringContainsString('How to Use for Best Results', (string) $audit->optimized_body_html);
                $this->assertStringNotContainsString('Agree:', (string) $audit->optimized_body_html);
                $this->assertStringNotContainsString('Promise:', (string) $audit->optimized_body_html);
                $this->assertStringNotContainsString('Preview:', (string) $audit->optimized_body_html);
                $this->assertStringNotContainsString('Entity-Attribute-Value (EAV)', (string) $audit->optimized_body_html);
                $this->assertStringContainsString('oleic acid', (string) $audit->optimized_body_html);
                $this->assertStringContainsString('Vitamin E', (string) $audit->optimized_body_html);
                $this->assertStringContainsString('45 to 60 washes', (string) $audit->optimized_body_html);
                $this->assertStringContainsString('does not block pores', (string) $audit->optimized_body_html);
                $this->assertStringContainsString('feeds the hair roots directly', (string) $audit->optimized_body_html);
                $this->assertStringContainsString('natural healthy fats', (string) $audit->optimized_body_html);
                $this->assertStringContainsString('light and non-greasy', (string) $audit->optimized_body_html);
                $this->assertStringContainsString('calms dry and itchy scalps', (string) $audit->optimized_body_html);
                $this->assertStringNotContainsString('non-comedogenic', (string) $audit->optimized_body_html);
                $this->assertStringNotContainsString('follicular level', (string) $audit->optimized_body_html);
                $this->assertStringNotContainsString('sebum', (string) $audit->optimized_body_html);
                $this->assertStringNotContainsString('—', (string) $audit->optimized_body_html);
                $this->assertStringNotContainsString('–', (string) $audit->optimized_body_html);
                $this->assertStringContainsString('/products/apricot-oil', (string) $audit->optimized_body_html);
                $this->assertStringContainsString('/products/shampoo', (string) $audit->optimized_body_html);

                // Extractive FAQs & JSON-LD Product + FAQPage schema
                $this->assertIsArray($audit->faq_json);
                $this->assertCount(4, $audit->faq_json);
                $this->assertNotEmpty($audit->image_alt_text);
                $this->assertNotEmpty($audit->image_filename);
                $this->assertStringEndsWith('.jpg', (string) $audit->image_filename);
                $this->assertStringContainsString('application/ld+json', (string) $audit->json_ld_schema);
                $this->assertStringContainsString('"@type": "Product"', (string) $audit->json_ld_schema);
                $this->assertStringContainsString('"@type": "FAQPage"', (string) $audit->json_ld_schema);
                $this->assertStringContainsString(
                    $region === 'QA' ? '"priceCurrency": "QAR"' : '"priceCurrency": "PKR"',
                    (string) $audit->json_ld_schema
                );
            }
        }
    }

    public function test_local_salon_seo_package_for_old_airport_and_al_wakrah_outputs_hairsalon_schema_and_qatar_hard_water_entities(): void
    {
        /** @var EcommerceSeoAgentService $seoService */
        $seoService = app(EcommerceSeoAgentService::class);

        $oldAirportAudit = $seoService->auditAndOptimizeTarget('Old Airport, Doha', 'QA', 'salon_branch');
        $alWakrahAudit = $seoService->auditAndOptimizeTarget('Al Wakrah, Qatar', 'QA', 'salon_branch');

        foreach ([$oldAirportAudit, $alWakrahAudit] as $salonAudit) {
            $this->assertSame('salon_branch', $salonAudit->target_type);
            $this->assertSame('QA', $salonAudit->region);
            $this->assertGreaterThanOrEqual(90, $salonAudit->seo_score);
            $this->assertLessThan(60, strlen((string) $salonAudit->meta_title));
            $this->assertLessThan(155, strlen((string) $salonAudit->meta_description));

            // Valid HairSalon + FAQPage JSON-LD schema
            $this->assertStringContainsString('application/ld+json', (string) $salonAudit->json_ld_schema);
            $this->assertStringContainsString('"@type": "HairSalon"', (string) $salonAudit->json_ld_schema);
            $this->assertStringContainsString('"@type": "FAQPage"', (string) $salonAudit->json_ld_schema);

            // Local Doha/Qatar desalinated hard-water & Hunza entities
            $this->assertStringContainsString('desalinated hard-water', strtolower((string) $salonAudit->optimized_body_html));
            $this->assertStringContainsString(
                'cold-pressed from raw kernels handpicked in the Hunza valleys',
                (string) $salonAudit->optimized_body_html
            );
        }

        $this->assertStringContainsString('Old Airport', $oldAirportAudit->target_name);
        $this->assertStringContainsString('Old Airport, Doha', (string) $oldAirportAudit->json_ld_schema);

        $this->assertStringContainsString('Al Wakrah', $alWakrahAudit->target_name);
        $this->assertStringContainsString('Al Wakrah', (string) $alWakrahAudit->json_ld_schema);
    }

    public function test_topical_authority_map_and_skyscraper_article_generator_include_internal_product_links(): void
    {
        /** @var EcommerceSeoAgentService $seoService */
        $seoService = app(EcommerceSeoAgentService::class);

        $pkClusters = $seoService->generateTopicalAuthorityMap('PK');
        $qaClusters = $seoService->generateTopicalAuthorityMap('QA');

        $this->assertCount(3, $pkClusters);
        $this->assertCount(3, $qaClusters);

        $pkTitles = $pkClusters->pluck('title')->implode(' | ');
        $this->assertStringContainsString('How to Stop Hairfall and Dandruff Naturally at Home', $pkTitles);
        $this->assertStringContainsString('How to Wash Oily Scalp and Stop Monsoon Frizz in Pakistan', $pkTitles);
        $this->assertStringContainsString('Hunza Apricot Kernel Oil vs Coconut and Mineral Hair Oils', $pkTitles);
        $this->assertStringNotContainsString('100% Pure Cold-Pressed Hunza Apricot Oil for Hairfall', $pkTitles);

        // Verify strict intent separation: zero keyword cannibalization between Product primary keywords and Blog target keywords
        $oilAuditPk = $seoService->auditAndOptimizeTarget($this->oilProduct, 'PK');
        $shampooAuditPk = $seoService->auditAndOptimizeTarget($this->shampooProduct, 'PK');
        $kitAuditPk = $seoService->auditAndOptimizeTarget('Hair Recovery Kit', 'PK', 'product');

        $this->assertSame('100% Pure Hunza Apricot Oil Pakistan', $oilAuditPk->primary_keyword);
        $this->assertSame('100% Pure Hunza Apricot Oil in Pakistan | Laleen Rituals', $oilAuditPk->meta_title);
        $this->assertSame('100-pure-cold-pressed-hunza-apricot-oil-100ml-laleen-rituals.jpg', $oilAuditPk->image_filename);
        $this->assertSame(
            '100% pure cold-pressed Hunza apricot oil 100ml bottle for hairfall and dry scalp by Laleen Rituals',
            $oilAuditPk->image_alt_text
        );

        $this->assertSame('Sulfate-Free Botanical Shampoo Pakistan', $shampooAuditPk->primary_keyword);
        $this->assertSame('Sulfate-Free Botanical Shampoo in Pakistan | Laleen Rituals', $shampooAuditPk->meta_title);

        $this->assertSame('Hairfall & Dandruff Recovery Kit Pakistan', $kitAuditPk->primary_keyword);
        $this->assertSame('Complete Hairfall & Dandruff Recovery Kit | Laleen Rituals', $kitAuditPk->meta_title);

        $productPrimaryKeywords = [
            strtolower($oilAuditPk->primary_keyword),
            strtolower($shampooAuditPk->primary_keyword),
            strtolower($kitAuditPk->primary_keyword),
        ];
        foreach ($pkClusters as $blogCluster) {
            $this->assertNotContains(strtolower($blogCluster->target_keyword), $productPrimaryKeywords);
            $this->assertNotContains(strtolower($blogCluster->title), $productPrimaryKeywords);
            $this->assertNotEmpty($blogCluster->image_alt_text);
            $this->assertNotEmpty($blogCluster->image_filename);
        }

        $qaTitles = $qaClusters->pluck('title')->implode(' | ');
        $this->assertStringContainsString('How to Stop Desalinated Hard-Water Hairfall in Doha', $qaTitles);
        $this->assertStringContainsString('Best Keratin, Protein & Hair Detox Salon in Old Airport & Al Wakrah', $qaTitles);
        $this->assertStringContainsString('Sulfate-Free Post-Keratin Maintenance Routine in Qatar', $qaTitles);

        // Generate all 3 PK articles and verify unique openings, real /products/apricot-oil & /products/shampoo links, no raw parenthetical URLs, no 4x repetition, and 3-question FAQ block
        $generatedArticles = $pkClusters->map(fn (EcommerceSeoArticle $a) => $seoService->generateSkyscraperArticle($a->id));
        $firstPkArticle = $generatedArticles->first();

        $this->assertSame('ready', $firstPkArticle->status);
        $this->assertLessThan(60, strlen((string) $firstPkArticle->meta_title));
        $this->assertLessThan(155, strlen((string) $firstPkArticle->meta_description));
        $this->assertStringContainsString('<h2>Why It Works: Pure Hunza Botanicals</h2>', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('<h2>How to Use for Best Results</h2>', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('<h2>Frequently Asked Questions</h2>', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('<h2>Related Hair Care Guides</h2>', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('https://laleenrituals.com/blogs/news/how-to-wash-oily-scalp-and-stop-monsoon-frizz-in-pakistan', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('https://laleenrituals.com/blogs/news/hunza-apricot-kernel-oil-vs-coconut-and-mineral-hair-oils', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('Message Zara, our Laleen Hair Care Specialist, on WhatsApp for a free 1-minute hair consultation', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('text=Assalam-o-Alaikum%20Zara,%20I%20just%20read%20your%20hair%20care%20guide%20and%20need%20advice%20for%20my%20hair', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('https://laleenrituals.com/products/apricot-oil', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('https://laleenrituals.com/products/shampoo', (string) $firstPkArticle->article_markdown);
        $this->assertStringNotContainsString('/products/hunza-apricot-oil', (string) $firstPkArticle->article_markdown);
        $this->assertStringNotContainsString('/products/sulfate-free-botanical-shampoo', (string) $firstPkArticle->article_markdown);
        $this->assertStringNotContainsString('##', (string) $firstPkArticle->article_markdown);
        $this->assertStringNotContainsString('**', (string) $firstPkArticle->article_markdown);
        $this->assertStringNotContainsString('| :--- |', (string) $firstPkArticle->article_markdown);

        foreach ($generatedArticles as $genArticle) {
            $visibleText = strip_tags((string) $genArticle->article_markdown);
            $this->assertStringNotContainsString('#', $visibleText);
            $this->assertStringNotContainsString('*', $visibleText);
            $this->assertStringNotContainsString('|', $visibleText);
            $this->assertStringNotContainsString('[', $visibleText);
            $this->assertStringNotContainsString(']', $visibleText);
            $this->assertStringNotContainsString('(', $visibleText);
            $this->assertStringNotContainsString(')', $visibleText);
        }
        $this->assertStringContainsString('does not block pores', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('feeds the hair roots directly', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('natural healthy fats', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('light and non-greasy', (string) $firstPkArticle->article_markdown);
        $this->assertStringContainsString('45 to 60 washes', (string) $firstPkArticle->article_markdown);
        $this->assertStringNotContainsString('non-comedogenic', (string) $firstPkArticle->article_markdown);
        $this->assertStringNotContainsString('follicular level', (string) $firstPkArticle->article_markdown);
        $this->assertStringNotContainsString('lipids', (string) $firstPkArticle->article_markdown);
        $this->assertStringNotContainsString('—', (string) $firstPkArticle->article_markdown);
        $this->assertStringNotContainsString('–', (string) $firstPkArticle->article_markdown);
        $this->assertSame(
            1,
            substr_count((string) $firstPkArticle->article_markdown, 'cold-pressed from raw kernels handpicked in the Hunza valleys')
        );
        $this->assertStringContainsString('"@type": "Article"', (string) $firstPkArticle->json_ld_schema);

        // Verify the 3 blog topics have genuinely distinct opening paragraphs
        $this->assertNotSame(
            substr((string) $generatedArticles[0]->article_markdown, 0, 220),
            substr((string) $generatedArticles[1]->article_markdown, 0, 220)
        );
        $this->assertNotSame(
            substr((string) $generatedArticles[1]->article_markdown, 0, 220),
            substr((string) $generatedArticles[2]->article_markdown, 0, 220)
        );

        // Controller & Minimalistic 3-Step Blade UI smoke test
        $this->actingAs($this->adminUser)
            ->get('/ecommerce/hub/seo-agent?region=pk')
            ->assertStatus(200)
            ->assertSee('SEO Studio')
            ->assertSee('Regenerate SEO')
            ->assertSee('1. Google Search Preview & Meta Tags')
            ->assertSee('2. Website Product Description & FAQs')
            ->assertSee('3. Google Rich Snippet Code (Optional)')
            ->assertSee('Blog Articles')
            ->assertSee('Hunza Apricot Oil');

        $this->actingAs($this->adminUser)
            ->post('/ecommerce/hub/seo-agent/optimize', [
                'region' => 'QA',
                'target_type' => 'salon_branch',
                'target_name' => 'Old Airport, Doha',
            ])
            ->assertRedirect();
    }

    public function test_google_search_console_auto_spy_syncs_queries_and_turns_quick_win_search_into_clean_blog_post(): void
    {
        Http::fake([
            'suggestqueries.google.com/complete/search*' => function ($request) {
                $gl = $request['gl'] ?? 'pk';
                $q = $request['q'] ?? '';
                if ($gl === 'qa') {
                    return Http::response([
                        $q,
                        [
                            'hair fall treatment qatar',
                            'protein hair treatment doha',
                            'best ladies salon al wakrah',
                            'sulfate free shampoo qatar',
                            'how to stop hard water hair fall in qatar',
                        ],
                    ], 200);
                }

                return Http::response([
                    $q,
                    [
                        'best hair oil for hairfall in pakistan',
                        'apricot oil for hair growth',
                        'sulfate free shampoo in pakistan',
                        'how to stop hair fall naturally at home',
                        'dandruff treatment at home',
                    ],
                ], 200);
            },
        ]);

        /** @var EcommerceSeoAgentService $seoService */
        $seoService = app(EcommerceSeoAgentService::class);

        $pkQueries = $seoService->syncGoogleSearchConsoleData('PK');
        $qaQueries = $seoService->syncGoogleSearchConsoleData('QA');

        $this->assertGreaterThanOrEqual(5, $pkQueries->count());
        $this->assertGreaterThanOrEqual(5, $qaQueries->count());

        // Verify zero fake numbers (impressions = 0, clicks = 0, avg_position = 0)
        foreach ($pkQueries->merge($qaQueries) as $row) {
            $this->assertSame(0, (int) $row->impressions);
            $this->assertSame(0, (int) $row->clicks);
            $this->assertEquals(0.0, (float) $row->avg_position);
        }

        $buyerQuery = EcommerceSeoSearchQuery::where('region', 'PK')
            ->where('search_query', 'best hair oil for hairfall in pakistan')
            ->firstOrFail();

        $this->assertSame('quick_win', $buyerQuery->opportunity_type);
        $this->assertSame('/products/apricot-oil', $buyerQuery->target_url);
        $this->assertNull($buyerQuery->linked_article_id);

        $symptomQuery = EcommerceSeoSearchQuery::where('region', 'PK')
            ->where('search_query', 'dandruff treatment at home')
            ->firstOrFail();

        $this->assertSame('content_gap', $symptomQuery->opportunity_type);

        $shampooQuery = EcommerceSeoSearchQuery::where('region', 'PK')
            ->where('search_query', 'sulfate free shampoo in pakistan')
            ->firstOrFail();

        $this->assertSame('quick_win', $shampooQuery->opportunity_type);
        $this->assertSame('/products/shampoo', $shampooQuery->target_url);

        $qaSalonQuery = EcommerceSeoSearchQuery::where('region', 'QA')
            ->where('search_query', 'best ladies salon al wakrah')
            ->firstOrFail();

        $this->assertSame('quick_win', $qaSalonQuery->opportunity_type);
        $this->assertSame('/locations/doha-qatar', $qaSalonQuery->target_url);

        // Verify Honest 4-Column Live Google Searches tab renders in Blade UI
        $this->actingAs($this->adminUser)
            ->get('/ecommerce/hub/seo-agent?region=pk&tab=searches')
            ->assertStatus(200)
            ->assertSee('Live Keyword Discovery from Google (google.com.pk)')
            ->assertSee('Fetch Live Google Searches')
            ->assertSee('Live Search Phrase on Google')
            ->assertSee('Search Intent')
            ->assertSee('Recommended Target')
            ->assertDontSee('Monthly Views')
            ->assertDontSee('Website Clicks')
            ->assertSee('best hair oil for hairfall in pakistan')
            ->assertSee('High Buyer Intent')
            ->assertSee('Symptom & How-To Search')
            ->assertSee('Hunza Apricot Oil')
            ->assertSee('Sulfate-Free Shampoo')
            ->assertSee('Write Blog Post');

        // 1-Click "Write Blog Post" from search query via POST route
        $response = $this->actingAs($this->adminUser)
            ->post('/ecommerce/hub/seo-agent/create-from-query', [
                'query_id' => $buyerQuery->id,
            ]);

        $buyerQuery->refresh();
        $this->assertNotNull($buyerQuery->linked_article_id);

        $article = EcommerceSeoArticle::findOrFail($buyerQuery->linked_article_id);
        $this->assertSame('ready', $article->status);
        $this->assertSame('best hair oil for hairfall in pakistan', $article->target_keyword);

        $response->assertRedirect(route('ecommerce.seo-agent.index', [
            'region' => 'pk',
            'tab' => 'topical',
            'article_id' => $article->id,
        ]));

        // Verify 3-4 related regional search phrases are woven into the generated HTML blog post
        $relatedInDb = EcommerceSeoSearchQuery::where('region', 'PK')
            ->where('id', '!=', $buyerQuery->id)
            ->limit(3)
            ->pluck('search_query')
            ->all();

        $this->assertCount(3, $relatedInDb);
        foreach ($relatedInDb as $relatedPhrase) {
            $this->assertStringContainsString($relatedPhrase, (string) $article->article_markdown);
        }

        // Verify zero raw Markdown symbols (#, **, [ ], ( )) in visible prose
        $this->assertStringNotContainsString('##', (string) $article->article_markdown);
        $this->assertStringNotContainsString('**', (string) $article->article_markdown);
        $visibleText = strip_tags((string) $article->article_markdown);
        $this->assertStringNotContainsString('#', $visibleText);
        $this->assertStringNotContainsString('*', $visibleText);
        $this->assertStringNotContainsString('[', $visibleText);
        $this->assertStringNotContainsString(']', $visibleText);
        $this->assertStringNotContainsString('(', $visibleText);
        $this->assertStringNotContainsString(')', $visibleText);
        $this->assertStringContainsString('https://laleenrituals.com/products/apricot-oil', (string) $article->article_markdown);
        $this->assertStringContainsString('https://laleenrituals.com/products/shampoo', (string) $article->article_markdown);
    }

    public function test_one_click_publish_to_shopify_updates_product_and_blog_article_sync_status_and_image_alt_tags(): void
    {
        Http::fake([
            '*/admin/api/2025-01/products/*.json' => Http::response([
                'product' => ['id' => 1001, 'title' => 'Hunza Apricot Oil'],
            ], 200),
            '*/admin/api/2025-01/blogs.json' => Http::response([
                'blogs' => [['id' => 77, 'handle' => 'news', 'title' => 'News']],
            ], 200),
            '*/admin/api/2025-01/blogs/77/articles.json' => Http::response([
                'article' => ['id' => 5001, 'handle' => 'how-to-stop-hairfall-and-dandruff-naturally-at-home'],
            ], 201),
        ]);

        /** @var EcommerceSeoAgentService $seoService */
        $seoService = app(EcommerceSeoAgentService::class);

        $audit = $seoService->auditAndOptimizeTarget($this->oilProduct, 'PK');
        $article = $seoService->generateTopicalAuthorityMap('PK', 'with_articles')->first();

        $this->assertNull($audit->shopify_published_at);
        $this->assertNull($article->shopify_published_at);

        // 1-Click Publish Product SEO to Shopify
        $this->actingAs($this->adminUser)
            ->post('/ecommerce/hub/seo-agent/publish-product', [
                'audit_id' => $audit->id,
            ])
            ->assertRedirect();

        $audit->refresh();
        $this->assertSame('published', $audit->shopify_sync_status);
        $this->assertNotNull($audit->shopify_published_at);

        // 1-Click Publish Blog Article to Shopify
        $this->actingAs($this->adminUser)
            ->post('/ecommerce/hub/seo-agent/publish-article', [
                'article_id' => $article->id,
            ])
            ->assertRedirect();

        $article->refresh();
        $this->assertSame('published', $article->status);
        $this->assertSame('published', $article->shopify_sync_status);
        $this->assertNotNull($article->shopify_published_at);

        Http::assertSent(function ($request) use ($audit) {
            return str_contains($request->url(), '/admin/api/2025-01/products/')
                && $request->method() === 'PUT'
                && ($request['product']['images'][0]['alt'] ?? '') === $audit->image_alt_text;
        });

        Http::assertSent(function ($request) use ($article) {
            return str_contains($request->url(), '/admin/api/2025-01/blogs/77/articles.json')
                && $request->method() === 'POST'
                && ($request['article']['handle'] ?? '') === $article->slug;
        });
    }
}
