<?php

namespace Tests\Feature;

use App\Console\Commands\CrmHealthCheckCommand;
use App\Models\EcommerceCustomer;
use App\Models\EcommerceLifecycleFlow;
use App\Models\EcommerceLifecycleLog;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use App\Models\EcommerceSeoArticle;
use App\Services\EcommerceRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class CrmSystemSecurityAndHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_health_check_command_passes_with_exit_code_zero(): void
    {
        $this->artisan('crm:health-check')
            ->expectsOutputToContain('Laleen CRM Full-System Health & Security Diagnostic Report')
            ->expectsOutputToContain('All CRM health, security, database, storage, and config checks passed.')
            ->assertExitCode(0);
    }

    public function test_zero_raw_env_calls_exist_inside_app_directory(): void
    {
        $rawEnvHits = CrmHealthCheckCommand::findRawEnvCallsInDirectory(app_path());

        $this->assertSame(
            [],
            $rawEnvHits,
            'Expected 0 raw env() calls in app/, found: ' . implode(', ', $rawEnvHits)
        );
    }

    public function test_webhook_and_ai_routes_have_rate_limiting_middleware_attached(): void
    {
        $routes = Route::getRoutes();

        $webhookRouteNames = [
            'api.webhooks.shopify.orders',
            'api.webhooks.shopify.orders-updated',
            'api.webhooks.shopify.orders-cancelled',
            'api.webhooks.shopify.orders-fulfilled',
            'api.webhooks.shopify.checkouts-create',
            'api.webhooks.shopify.checkouts-update',
            'api.webhooks.ecommerce.whatsapp-inbound',
        ];

        foreach ($webhookRouteNames as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Expected webhook route [{$name}] to exist.");
            $middleware = $route->gatherMiddleware();
            $this->assertTrue(
                in_array('throttle:120,1', $middleware, true),
                "Expected webhook route [{$name}] to include throttle:120,1 middleware."
            );
        }

        $aiAndBulkRouteNames = [
            'command-center.ask',
            'command-center.tts',
            'ai-script.generate',
            'leads.analytics.ai-diagnosis',
            'leads.ai-morning-brief',
            'leads.ai-script',
            'staff-complaints.draft-notice-ai',
            'customers.sync-retention-leads',
            'customers.ai-retention-script',
            'ecommerce.customers.run-flows',
            'ecommerce.customers.send-newsletter',
            'ecommerce.ai-agent.run-scan',
            'ecommerce.seo-agent.optimize',
            'ecommerce.seo-agent.articles.generate',
            'ecommerce.seo-agent.articles.generate-image',
            'ecommerce.hub.marketing.ai-generate-ads',
            'ecommerce.hub.marketing.ai-generate-calendar',
        ];

        foreach ($aiAndBulkRouteNames as $name) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Expected route [{$name}] to exist.");
            $middleware = $route->gatherMiddleware();
            $this->assertTrue(
                in_array('throttle:20,1', $middleware, true),
                "Expected heavy/AI route [{$name}] to include throttle:20,1 middleware."
            );
        }
    }

    public function test_production_webhooks_reject_requests_when_secrets_are_unconfigured(): void
    {
        $originalEnv = $this->app['env'];

        try {
            $this->app['env'] = 'production';
            config([
                'services.shopify.webhook_secret' => '',
                'services.whatsapp.webhook_token' => '',
                'services.whatsapp.app_secret' => '',
            ]);

            $shopifyResponse = $this->postJson('/api/webhooks/shopify/orders', ['id' => 999]);
            $shopifyResponse->assertStatus(401);

            $whatsappResponse = $this->postJson('/api/webhooks/ecommerce/whatsapp-inbound', [
                'phone' => '+923001112233',
                'message_body' => 'Hello',
            ]);
            $whatsappResponse->assertStatus(401);
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    public function test_fault_isolation_continues_batch_when_individual_customer_dispatch_fails(): void
    {
        $product = EcommerceProduct::create([
            'name' => '100% Pure Hunza Apricot Oil',
            'sku' => 'LALEEN-OIL-100',
            'category' => 'Hair Oil',
            'price' => 3500,
            'cost_price' => 800,
            'stock_pakistan' => 50,
            'stock_qatar' => 25,
        ]);

        $customer1 = EcommerceCustomer::create([
            'name' => 'First Customer (Simulated Failure)',
            'phone' => '+923001110001',
            'email' => 'fail-customer@example.com',
            'region' => 'PK',
            'total_orders' => 1,
            'total_spend' => 3500,
        ]);

        $customer2 = EcommerceCustomer::create([
            'name' => 'Second Customer (Should Succeed)',
            'phone' => '+923001110002',
            'email' => 'success-customer@example.com',
            'region' => 'PK',
            'total_orders' => 1,
            'total_spend' => 3500,
        ]);

        foreach ([$customer1, $customer2] as $idx => $cust) {
            EcommerceSale::create([
                'order_group_id' => 'ORD-PK-' . ($idx + 1),
                'ecommerce_product_id' => $product->id,
                'ecommerce_customer_id' => $cust->id,
                'customer_name' => $cust->name,
                'customer_phone' => $cust->phone,
                'channel' => 'Shopify (Pakistan)',
                'quantity' => 1,
                'unit_price' => 3500,
                'total_price' => 3500,
                'order_status' => 'delivered',
                'created_at' => now()->subDays(31),
            ]);
        }

        // Subclass service to throw on the first customer during dispatchLifecycleMessage
        $faultService = new class extends EcommerceRetentionService {
            protected function dispatchLifecycleMessage(
                EcommerceCustomer $customer,
                string $channel,
                string $flowKey,
                string $region,
                ?EcommerceLifecycleFlow $flow = null
            ): EcommerceLifecycleLog {
                if ($customer->email === 'fail-customer@example.com') {
                    throw new RuntimeException('Simulated SMTP / WhatsApp transport failure for first customer');
                }

                return parent::dispatchLifecycleMessage($customer, $channel, $flowKey, $region, $flow);
            }
        };

        $dispatchedLogs = $faultService->runDueLifecycleFlows('PK');

        $this->assertNotEmpty($dispatchedLogs, 'Expected batch run to continue and dispatch for remaining healthy customers.');
        $this->assertTrue(
            $dispatchedLogs->contains(fn (EcommerceLifecycleLog $log) => $log->customer_id === $customer2->id),
            'Expected customer #2 to receive lifecycle messages despite customer #1 throwing an exception.'
        );

        // Also verify fault isolation in broadcastBlogNewsletter
        $article = EcommerceSeoArticle::create([
            'region' => 'PK',
            'title' => 'Hunza Apricot Oil Hair Fall Guide',
            'slug' => 'hunza-apricot-oil-hair-fall-guide',
            'target_keyword' => 'apricot oil for hair fall',
            'meta_description' => 'Cold-pressed Hunza Apricot Oil guide.',
            'article_markdown' => '<p>3 drops ritual.</p>',
            'status' => 'published',
        ]);

        $newsletterFaultService = new class extends EcommerceRetentionService {
            protected function safeSendHtmlEmail(string $toEmail, string $subject, string $htmlBody): void
            {
                if ($toEmail === 'fail-customer@example.com') {
                    throw new RuntimeException('Simulated newsletter failure for first subscriber');
                }
                parent::safeSendHtmlEmail($toEmail, $subject, $htmlBody);
            }
        };

        $newsletterLogs = $newsletterFaultService->broadcastBlogNewsletter($article, 'PK');

        $this->assertCount(1, $newsletterLogs);
        $this->assertSame($customer2->id, $newsletterLogs->first()->customer_id);
    }
}
