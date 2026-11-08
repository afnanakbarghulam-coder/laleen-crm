<?php

namespace Tests\Feature;

use App\Models\EcommerceCustomer;
use App\Models\EcommerceLifecycleFlow;
use App\Models\EcommerceLifecycleLog;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use App\Models\EcommerceSeoArticle;
use App\Models\User;
use App\Services\EcommerceRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcommerceRetentionTest extends TestCase
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
            'name' => '100% Pure Hunza Apricot Oil',
            'sku' => 'LAL-OIL-100',
            'shopify_url' => 'https://laleenrituals.com/products/apricot-oil',
            'unit_size' => '100ml',
            'stock_pakistan' => 100,
            'stock_qatar' => 50,
            'selling_price' => 1500,
            'volume_ml' => 100,
        ]);

        $this->shampooProduct = EcommerceProduct::create([
            'name' => 'Sulfate-Free Botanical Shampoo',
            'sku' => 'LAL-SHM-250',
            'shopify_url' => 'https://laleenrituals.com/products/shampoo',
            'unit_size' => '250ml',
            'stock_pakistan' => 80,
            'stock_qatar' => 40,
            'selling_price' => 2106,
            'volume_ml' => 250,
        ]);
    }

    public function test_opening_customers_and_ltv_seeds_and_displays_four_lifecycle_flows_for_pk_and_qa(): void
    {
        foreach (['pk' => 'PK', 'qa' => 'QA'] as $queryRegion => $upperRegion) {
            $response = $this->actingAs($this->adminUser)
                ->get("/ecommerce/hub/customers?region={$queryRegion}");

            $response->assertStatus(200)
                ->assertSee('📦 Day-0 COD + Kit Upsell')
                ->assertSee('✨ Day-4/10 Ritual & Review')
                ->assertSee('🔄 Day-30 Bottle Refill')
                ->assertSee('💎 Day-60 Win-Back')
                ->assertSee('▶️ Run Due Flows Now')
                ->assertSee('📧 Send SEO Blog as Email Newsletter:')
                ->assertSee("🚀 Send Newsletter to All {$upperRegion} Customers")
                ->assertSee('📬 View Recent Email & WhatsApp Automation Logs');

            $flows = EcommerceLifecycleFlow::where('region', $upperRegion)->pluck('flow_key')->all();
            $this->assertCount(4, $flows);
            $this->assertContains('cod_verification_upsell', $flows);
            $this->assertContains('post_purchase_ritual_review', $flows);
            $this->assertContains('refill_30d_reorder', $flows);
            $this->assertContains('winback_60d', $flows);
        }
    }

    public function test_toggling_lifecycle_flow_switches_is_enabled_cleanly(): void
    {
        /** @var EcommerceRetentionService $retentionService */
        $retentionService = app(EcommerceRetentionService::class);
        $flows = $retentionService->ensureDefaultFlows('PK');

        /** @var EcommerceLifecycleFlow $refillFlow */
        $refillFlow = $flows->firstWhere('flow_key', 'refill_30d_reorder');
        $this->assertTrue($refillFlow->is_enabled);

        $this->actingAs($this->adminUser)
            ->post("/ecommerce/hub/customers/flows/{$refillFlow->id}/toggle", [
                'region' => 'pk',
            ])
            ->assertRedirect(route('ecommerce.customers.index', ['region' => 'pk']));

        $refillFlow->refresh();
        $this->assertFalse($refillFlow->is_enabled);

        $this->actingAs($this->adminUser)
            ->post("/ecommerce/hub/customers/flows/{$refillFlow->id}/toggle", [
                'region' => 'pk',
            ])
            ->assertRedirect(route('ecommerce.customers.index', ['region' => 'pk']));

        $refillFlow->refresh();
        $this->assertTrue($refillFlow->is_enabled);
    }

    public function test_running_due_flows_processes_eligible_customers_and_creates_lifecycle_logs(): void
    {
        $freshCustomer = EcommerceCustomer::create([
            'name' => 'Ayesha Khan',
            'phone' => '923001112233',
            'email' => 'ayesha@example.com',
            'region' => 'PK',
            'total_orders' => 1,
            'total_spend' => 1500,
            'last_purchase_at' => now()->subDays(1),
        ]);

        $refillCustomer = EcommerceCustomer::create([
            'name' => 'Sana Tariq',
            'phone' => '923004445566',
            'email' => 'sana@example.com',
            'region' => 'PK',
            'total_orders' => 2,
            'total_spend' => 3600,
            'last_purchase_at' => now()->subDays(31),
        ]);

        $winbackCustomer = EcommerceCustomer::create([
            'name' => 'Hira Malik',
            'phone' => '923007778899',
            'email' => 'hira@example.com',
            'region' => 'PK',
            'total_orders' => 1,
            'total_spend' => 1500,
            'last_purchase_at' => now()->subDays(62),
        ]);

        /** @var EcommerceRetentionService $retentionService */
        $retentionService = app(EcommerceRetentionService::class);

        $this->assertSame('🟢 Fresh Order (In Ritual Window)', $retentionService->classifyCustomerLifecycle($freshCustomer)['label']);
        $this->assertSame('🔄 Due for 30-Day Refill', $retentionService->classifyCustomerLifecycle($refillCustomer)['label']);
        $this->assertTrue($retentionService->classifyCustomerLifecycle($refillCustomer)['is_vip']);
        $this->assertSame('⚠️ 60-Day Win-Back Due', $retentionService->classifyCustomerLifecycle($winbackCustomer)['label']);

        $this->actingAs($this->adminUser)
            ->post('/ecommerce/hub/customers/run-flows', [
                'region' => 'PK',
            ])
            ->assertRedirect(route('ecommerce.customers.index', ['region' => 'pk']));

        $logs = EcommerceLifecycleLog::where('region', 'PK')->get();
        // 3 customers x 2 channels (email + whatsapp) = 6 lifecycle logs
        $this->assertCount(6, $logs);

        $this->assertDatabaseHas('ecommerce_lifecycle_logs', [
            'customer_id' => $freshCustomer->id,
            'flow_key' => 'cod_verification_upsell',
            'channel' => 'email',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('ecommerce_lifecycle_logs', [
            'customer_id' => $refillCustomer->id,
            'flow_key' => 'refill_30d_reorder',
            'channel' => 'whatsapp',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('ecommerce_lifecycle_logs', [
            'customer_id' => $winbackCustomer->id,
            'flow_key' => 'winback_60d',
            'channel' => 'email',
            'status' => 'sent',
        ]);

        $refillFlow = EcommerceLifecycleFlow::where('region', 'PK')
            ->where('flow_key', 'refill_30d_reorder')
            ->firstOrFail();
        $this->assertGreaterThanOrEqual(2, $refillFlow->sent_count);
        $this->assertNotNull($refillFlow->last_ran_at);
    }

    public function test_sending_seo_blog_article_as_email_newsletter_dispatches_and_logs_broadcast(): void
    {
        $pkCustomer1 = EcommerceCustomer::create([
            'name' => 'Zainab Ali',
            'phone' => '923211112222',
            'email' => 'zainab@example.com',
            'region' => 'PK',
            'total_orders' => 1,
            'total_spend' => 1500,
            'last_purchase_at' => now()->subDays(10),
        ]);

        $pkCustomer2 = EcommerceCustomer::create([
            'name' => 'Mahira Noor',
            'phone' => '923213334444',
            'email' => 'mahira@example.com',
            'region' => 'PK',
            'total_orders' => 3,
            'total_spend' => 5100,
            'last_purchase_at' => now()->subDays(5),
        ]);

        $article = EcommerceSeoArticle::create([
            'region' => 'PK',
            'business_line' => 'rituals_ecommerce',
            'cluster_topic' => 'Hairfall Recovery',
            'search_intent' => 'informational',
            'title' => 'Which Hair Oil Is Good For Hair Fall',
            'slug' => 'which-hair-oil-is-good-for-hair-fall',
            'target_keyword' => 'which hair oil is good for hair fall',
            'internal_link_target_url' => '/products/apricot-oil',
            'article_markdown' => '<article><h1>Which Hair Oil Is Good For Hair Fall</h1><p>Pure Hunza Apricot Oil feeds the hair roots directly.</p></article>',
            'meta_title' => 'Which Hair Oil Is Good For Hair Fall | Laleen',
            'meta_description' => 'Discover 100% pure Hunza Apricot Oil and Sulfate-Free Botanical Shampoo.',
            'image_alt_text' => 'Hunza Apricot Oil guide',
            'image_filename' => 'which-hair-oil-is-good-for-hair-fall-laleen-rituals.jpg',
            'status' => 'ready',
        ]);

        $this->actingAs($this->adminUser)
            ->post('/ecommerce/hub/customers/send-newsletter', [
                'region' => 'PK',
                'article_id' => $article->id,
            ])
            ->assertRedirect(route('ecommerce.customers.index', ['region' => 'pk']));

        $newsletterLogs = EcommerceLifecycleLog::where('region', 'PK')
            ->where('flow_key', 'blog_newsletter')
            ->get();

        $this->assertCount(2, $newsletterLogs);

        foreach ($newsletterLogs as $log) {
            $this->assertSame('email', $log->channel);
            $this->assertSame('sent', $log->status);
            $this->assertStringContainsString('Which Hair Oil Is Good For Hair Fall', (string) $log->subject);
            $this->assertStringContainsString('/products/apricot-oil', (string) $log->message_body);
            $this->assertStringContainsString('/products/shampoo', (string) $log->message_body);
        }

        $this->assertDatabaseHas('ecommerce_lifecycle_logs', [
            'customer_id' => $pkCustomer1->id,
            'customer_email' => 'zainab@example.com',
            'flow_key' => 'blog_newsletter',
        ]);
        $this->assertDatabaseHas('ecommerce_lifecycle_logs', [
            'customer_id' => $pkCustomer2->id,
            'customer_email' => 'mahira@example.com',
            'flow_key' => 'blog_newsletter',
        ]);
    }

    public function test_clicking_per_customer_email_refill_or_wa_refill_sends_and_logs_retention_message(): void
    {
        $customer = EcommerceCustomer::create([
            'name' => 'Fatima Zahra',
            'phone' => '97455112233',
            'email' => 'fatima.doha@example.com',
            'region' => 'QA',
            'total_orders' => 2,
            'total_spend' => 220,
            'last_purchase_at' => now()->subDays(30),
        ]);

        EcommerceSale::create([
            'ecommerce_product_id' => $this->oilProduct->id,
            'ecommerce_customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'quantity' => 1,
            'channel' => 'Salon (Old Airport)',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'unit_price' => 110,
            'total_price' => 110,
        ]);

        // 1-Click Email Refill
        $this->actingAs($this->adminUser)
            ->post("/ecommerce/hub/customers/{$customer->id}/send-ping", [
                'channel' => 'email',
                'flow_key' => 'refill_30d_reorder',
                'region' => 'qa',
            ])
            ->assertRedirect(route('ecommerce.customers.index', ['region' => 'qa']));

        $this->assertDatabaseHas('ecommerce_lifecycle_logs', [
            'customer_id' => $customer->id,
            'region' => 'QA',
            'channel' => 'email',
            'flow_key' => 'refill_30d_reorder',
            'status' => 'sent',
        ]);

        // 1-Click WA Refill
        $this->actingAs($this->adminUser)
            ->post("/ecommerce/hub/customers/{$customer->id}/send-ping", [
                'channel' => 'whatsapp',
                'flow_key' => 'refill_30d_reorder',
                'region' => 'qa',
            ])
            ->assertRedirect(route('ecommerce.customers.index', ['region' => 'qa']));

        $this->assertDatabaseHas('ecommerce_lifecycle_logs', [
            'customer_id' => $customer->id,
            'region' => 'QA',
            'channel' => 'whatsapp',
            'flow_key' => 'refill_30d_reorder',
            'status' => 'sent',
        ]);

        $customer->refresh();
        $this->assertNotNull($customer->last_retention_contacted_at);
        $this->assertSame('refill_30d_reorder', $customer->last_retention_stage);
    }

    public function test_qatar_shopify_webhook_routes_to_organic_qatar_stock_and_strictly_isolates_regional_customer_ltv(): void
    {
        config(['services.shopify.webhook_secret' => '']);

        $initialPkStock = (float) $this->oilProduct->stock_pakistan;
        $initialQaStock = (float) $this->oilProduct->stock_qatar;

        // 1. Send a Qatar Shopify order webhook (QAR + +974 phone)
        $qatarOrderPayload = [
            'id' => 8899001122,
            'name' => '#QA-2001',
            'financial_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'currency' => 'QAR',
            'phone' => '+97455889900',
            'email' => 'noor.alwakrah@example.com',
            'customer' => [
                'first_name' => 'Noor',
                'last_name' => 'Al-Thani',
                'phone' => '+97455889900',
                'email' => 'noor.alwakrah@example.com',
            ],
            'line_items' => [
                [
                    'id' => 445566,
                    'sku' => 'LAL-OIL-100',
                    'title' => '100% Pure Hunza Apricot Oil',
                    'quantity' => 2,
                    'price' => '65.00',
                ],
            ],
        ];

        $this->postJson('/api/webhooks/shopify/orders', $qatarOrderPayload)
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        $this->oilProduct->refresh();
        $this->assertEquals($initialPkStock, (float) $this->oilProduct->stock_pakistan);
        $this->assertEquals($initialQaStock - 2, (float) $this->oilProduct->stock_qatar);
        $this->assertEquals(2.0, (float) $this->oilProduct->sold_qatar);

        $qaSale = EcommerceSale::where('shopify_order_id', '8899001122')->firstOrFail();
        $this->assertSame('Organic (Qatar)', $qaSale->channel);
        $this->assertEquals(130.00, (float) $qaSale->total_price);

        $qaCustomer = EcommerceCustomer::where('phone', '97455889900')->firstOrFail();
        $this->assertSame('QA', $qaCustomer->region);
        $this->assertEquals(130.00, (float) $qaCustomer->total_spend);
        $this->assertSame(1, (int) $qaCustomer->total_orders);

        // 2. Create a Pakistan customer and sale
        $pkCustomer = EcommerceCustomer::create([
            'name' => 'Amina Lahore',
            'phone' => '923009988112',
            'email' => 'amina.lahore@example.com',
            'region' => 'PK',
            'total_spend' => 0,
            'total_orders' => 0,
        ]);

        EcommerceSale::create([
            'ecommerce_product_id' => $this->oilProduct->id,
            'ecommerce_customer_id' => $pkCustomer->id,
            'customer_name' => $pkCustomer->name,
            'customer_phone' => $pkCustomer->phone,
            'quantity' => 1,
            'channel' => 'Shopify (Pakistan)',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'unit_price' => 1500,
            'total_price' => 1500,
        ]);
        $pkCustomer->recalculateLtv();
        $this->assertEquals(1500.00, (float) $pkCustomer->fresh()->total_spend);

        // 3. Verify strict regional customer isolation on GET /ecommerce/hub/customers
        $pkResponse = $this->actingAs($this->adminUser)
            ->get('/ecommerce/hub/customers?region=pk');
        $pkResponse->assertStatus(200)
            ->assertSee('Amina Lahore')
            ->assertDontSee('Noor Al-Thani');

        $qaResponse = $this->actingAs($this->adminUser)
            ->get('/ecommerce/hub/customers?region=qa');
        $qaResponse->assertStatus(200)
            ->assertSee('Noor Al-Thani')
            ->assertDontSee('Amina Lahore');
    }
}
