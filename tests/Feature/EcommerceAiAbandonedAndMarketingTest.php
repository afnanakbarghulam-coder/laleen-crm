<?php

namespace Tests\Feature;

use App\Models\EcommerceAbandonedCheckout;
use App\Models\EcommerceAiSetting;
use App\Models\EcommerceContentCalendar;
use App\Models\EcommerceCustomer;
use App\Models\EcommerceMonthlyAd;
use App\Models\EcommercePricingModel;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductLine;
use App\Models\EcommerceSale;
use App\Models\EcommerceWhatsappThread;
use App\Models\User;
use App\Services\Ecommerce\EcommerceAiSalesAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EcommerceAiAbandonedAndMarketingTest extends TestCase
{
    use RefreshDatabase;

    protected EcommerceProduct $oilProduct;
    protected EcommerceProduct $shampooProduct;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Force deterministic fallback in tests
        Config::set('services.gemini.api_key', '');
        Config::set('services.gemini.key', '');
        Config::set('services.shopify.webhook_secret', '');

        $this->adminUser = User::factory()->create([
            'name' => 'Afnan Admin',
            'role' => 'admin',
        ]);

        $oilLine = EcommerceProductLine::create(['name' => 'Hair Oil']);
        $shampooLine = EcommerceProductLine::create(['name' => 'Botanical Shampoo']);

        $this->oilProduct = EcommerceProduct::create([
            'product_line_id' => $oilLine->id,
            'name' => 'Hunza Apricot Hair Oil',
            'sku' => 'LAL-OIL-100',
            'variant_type' => '100ml',
            'selling_price' => 1500,
            'stock_pakistan' => 60,
            'stock_qatar' => 30,
        ]);

        EcommercePricingModel::create([
            'ecommerce_product_id' => $this->oilProduct->id,
            'currency' => 'PKR',
            'retail_price' => 1500,
            'liquid_cost' => 180,
            'bottle_cost' => 60,
            'label_cost' => 20,
            'pump_cost' => 20,
            'box_cost' => 40,
        ]);

        $this->shampooProduct = EcommerceProduct::create([
            'product_line_id' => $shampooLine->id,
            'name' => 'Sulfate-Free Botanical Shampoo',
            'sku' => 'LAL-SHM-250',
            'variant_type' => '250ml',
            'selling_price' => 1200,
            'stock_pakistan' => 45,
            'stock_qatar' => 25,
        ]);

        EcommercePricingModel::create([
            'ecommerce_product_id' => $this->shampooProduct->id,
            'currency' => 'PKR',
            'retail_price' => 1200,
            'liquid_cost' => 150,
            'bottle_cost' => 50,
            'label_cost' => 15,
            'pump_cost' => 20,
            'box_cost' => 35,
        ]);

        EcommerceAiSetting::current()->update([
            'mode' => 'autopilot',
            'whatsapp_driver' => 'simulator',
            'abandoned_checkout_enabled' => true,
            'abandoned_checkout_delay_minutes' => 15,
        ]);
    }

    public function test_shopify_abandoned_checkout_webhook_and_15_minute_whatsapp_rescue(): void
    {
        $checkoutPayload = [
            'id' => 9988776655,
            'token' => 'chk_tok_9988776655',
            'currency' => 'PKR',
            'subtotal_price' => '1500.00',
            'abandoned_checkout_url' => 'https://laleenrituals.com/checkouts/ac/chk_tok_9988776655/recover',
            'email' => 'sara@example.com',
            'shipping_address' => [
                'first_name' => 'Sara',
                'last_name' => 'Ahmed',
                'phone' => '0300-1122334',
                'country_code' => 'PK',
            ],
            'line_items' => [
                [
                    'title' => 'Hunza Apricot Hair Oil',
                    'sku' => 'LAL-OIL-100',
                    'quantity' => 1,
                    'price' => '1500.00',
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/shopify/checkouts-create', $checkoutPayload);
        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('shopify_checkout_id', '9988776655');

        $checkout = EcommerceAbandonedCheckout::where('shopify_checkout_id', '9988776655')->firstOrFail();
        $this->assertSame('abandoned', $checkout->status);
        $this->assertSame('PK', $checkout->region);
        $this->assertSame('923001122334', $checkout->phone);
        $this->assertNotNull($checkout->ecommerce_customer_id);

        /** @var EcommerceAiSalesAgentService $agent */
        $agent = app(EcommerceAiSalesAgentService::class);

        // Before 15 minutes have elapsed, scan should NOT trigger outreach yet
        $earlyScan = $agent->runOutreachScan('pk');
        $this->assertSame(0, $earlyScan['abandoned_threads_created']);

        // Advance time 16 minutes
        $this->travel(16)->minutes();

        $scanResult = $agent->runOutreachScan('pk');
        $this->assertSame(1, $scanResult['abandoned_threads_created']);

        $checkout->refresh();
        $this->assertSame('outreach_sent', $checkout->status);
        $this->assertNotNull($checkout->outreach_sent_at);

        $thread = EcommerceWhatsappThread::where('ecommerce_customer_id', $checkout->ecommerce_customer_id)
            ->where('playbook', 'abandoned_checkout')
            ->firstOrFail();

        $this->assertSame('abandoned_followup_sent', $thread->state);

        $outboundMsg = $thread->messages()->where('direction', 'outbound')->latest('id')->firstOrFail();
        $this->assertStringContainsString('Hunza Apricot Hair Oil', $outboundMsg->message_body);
        $this->assertStringContainsString('https://laleenrituals.com/checkouts/ac/chk_tok_9988776655/recover', $outboundMsg->message_body);
    }

    public function test_abandoned_checkout_auto_recovery_attribution_on_order_creation(): void
    {
        $customer = EcommerceCustomer::create([
            'name' => 'Mariam Khan',
            'phone' => '923219988776',
            'total_spend' => 0,
            'total_orders' => 0,
        ]);

        $checkout = EcommerceAbandonedCheckout::create([
            'shopify_checkout_id' => 'CHK-RECOVER-101',
            'shopify_checkout_token' => 'tok_recover_101',
            'ecommerce_customer_id' => $customer->id,
            'customer_name' => 'Mariam Khan',
            'phone' => $customer->phone,
            'region' => 'PK',
            'currency' => 'PKR',
            'subtotal_price' => 1500,
            'abandoned_checkout_url' => 'https://laleenrituals.com/checkouts/ac/tok_recover_101/recover',
            'line_items_json' => [[
                'title' => $this->oilProduct->name,
                'quantity' => 1,
                'price' => 1500,
                'ecommerce_product_id' => $this->oilProduct->id,
            ]],
            'status' => 'abandoned',
            'abandoned_at' => now()->subMinutes(20),
        ]);

        /** @var EcommerceAiSalesAgentService $agent */
        $agent = app(EcommerceAiSalesAgentService::class);
        $thread = $agent->initiateAbandonedCheckoutRescue($checkout);
        $this->assertNotNull($thread);
        $this->assertSame('outreach_sent', $checkout->fresh()->status);

        // Now simulate Shopify order creation for that customer/checkout token
        $orderPayload = [
            'id' => 5544332211,
            'name' => '#1055',
            'checkout_token' => 'tok_recover_101',
            'financial_status' => 'paid',
            'currency' => 'PKR',
            'phone' => '+923219988776',
            'customer' => [
                'first_name' => 'Mariam',
                'last_name' => 'Khan',
                'phone' => '+923219988776',
            ],
            'line_items' => [
                [
                    'id' => 778899,
                    'sku' => 'LAL-OIL-100',
                    'title' => 'Hunza Apricot Hair Oil',
                    'quantity' => 1,
                    'price' => '1500.00',
                ],
            ],
        ];

        $orderResp = $this->postJson('/api/webhooks/shopify/orders', $orderPayload);
        $orderResp->assertStatus(200)->assertJsonPath('status', 'ok');

        $checkout->refresh();
        $this->assertSame('recovered', $checkout->status);
        $this->assertNotNull($checkout->recovered_at);
        $this->assertSame('SHOPIFY-5544332211', $checkout->recovered_order_group_id);

        $thread->refresh();
        $this->assertSame('upsell_accepted', $thread->state);
        $this->assertEquals(1500.00, (float) $thread->upsell_revenue_generated);

        $sysEvent = $thread->messages()->where('sender_type', 'system_event')->latest('id')->first();
        $this->assertNotNull($sysEvent);
        $this->assertStringContainsString('Abandoned Checkout Recovered!', $sysEvent->message_body);
    }

    public function test_abandoned_checkout_customer_reply_converts_to_cod_order_in_whatsapp(): void
    {
        $customer = EcommerceCustomer::create([
            'name' => 'Hira Tariq',
            'phone' => '923334455667',
            'total_spend' => 0,
            'total_orders' => 0,
        ]);

        $checkout = EcommerceAbandonedCheckout::create([
            'shopify_checkout_id' => 'CHK-COD-202',
            'shopify_checkout_token' => 'tok_cod_202',
            'ecommerce_customer_id' => $customer->id,
            'customer_name' => 'Hira Tariq',
            'phone' => $customer->phone,
            'region' => 'PK',
            'currency' => 'PKR',
            'subtotal_price' => 3000,
            'abandoned_checkout_url' => 'https://laleenrituals.com/checkouts/ac/tok_cod_202/recover',
            'line_items_json' => [[
                'title' => $this->oilProduct->name,
                'quantity' => 2,
                'price' => 1500,
                'ecommerce_product_id' => $this->oilProduct->id,
            ]],
            'status' => 'abandoned',
            'abandoned_at' => now()->subMinutes(20),
        ]);

        $initialStock = (float) $this->oilProduct->stock_pakistan;

        /** @var EcommerceAiSalesAgentService $agent */
        $agent = app(EcommerceAiSalesAgentService::class);
        $thread = $agent->initiateAbandonedCheckoutRescue($checkout);
        $this->assertNotNull($thread);

        // Customer replies asking to confirm on COD right inside WhatsApp
        $result = $agent->handleInboundCustomerMessage($thread, 'Confirm my order here on COD');

        $this->assertSame('convert_abandoned_to_cod_order', $result['ai_decision']['should_trigger_action']);
        $this->assertSame('cod_confirmed_upsell_pitched', $result['thread']->state);
        $this->assertEquals(3000.00, (float) $result['thread']->upsell_revenue_generated);

        // Verify checkout is marked recovered
        $checkout->refresh();
        $this->assertSame('recovered', $checkout->status);
        $this->assertNotNull($checkout->recovered_order_group_id);

        // Verify EcommerceSale COD order was created with is_ai_upsell = false and warehouse stock deducted by 2
        $createdSale = EcommerceSale::where('order_group_id', $checkout->recovered_order_group_id)->first();
        $this->assertNotNull($createdSale);
        $this->assertFalse((bool) $createdSale->is_ai_upsell);
        $this->assertSame('unpaid_cod', $createdSale->payment_status);
        $this->assertSame(2, (int) $createdSale->quantity);
        $this->assertEquals(3000.00, (float) $createdSale->total_price);

        $this->oilProduct->refresh();
        $this->assertEquals($initialStock - 2, (float) $this->oilProduct->stock_pakistan);
    }

    public function test_ai_marketing_generator_creates_20_ad_scripts_and_28_calendar_slots(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Trigger 1-Click AI Ad Script Generator (20-Video Kanban)
        $adsResponse = $this->post('/ecommerce/hub/marketing/ai-generate-ads', [
            'plan_month' => '2026-11',
            'region' => 'pk',
        ]);
        $adsResponse->assertRedirect();

        $ads = EcommerceMonthlyAd::where('plan_month', '2026-11')->get();
        $this->assertCount(20, $ads);

        $firstAd = $ads->first();
        $this->assertNotEmpty($firstAd->framework);
        $this->assertNotEmpty($firstAd->hook_text);
        $this->assertNotEmpty($firstAd->spoken_script);
        $this->assertNotEmpty($firstAd->b_roll_shot_list);
        $this->assertNotEmpty($firstAd->google_flow_prompt);
        $this->assertStringContainsString('zero AI plastic gloss', $firstAd->google_flow_prompt);

        // 2. Trigger 1-Click 4x7 Social Content Calendar Generator (28-Day Grid)
        $calResponse = $this->post('/ecommerce/hub/marketing/ai-generate-calendar', [
            'week_start_date' => '2026-11-02',
            'region' => 'pk',
        ]);
        $calResponse->assertRedirect();

        $calendarSlots = EcommerceContentCalendar::all();
        $this->assertCount(28, $calendarSlots);

        $distinctWeeks = $calendarSlots->pluck('week_start_date')->unique();
        $this->assertCount(4, $distinctWeeks);

        $sampleSlot = $calendarSlots->first();
        $this->assertNotEmpty($sampleSlot->objective);
        $this->assertNotEmpty($sampleSlot->script_concept);
        $this->assertNotEmpty($sampleSlot->caption_copy);
        $this->assertNotEmpty($sampleSlot->visual_direction);
        $this->assertNotEmpty($sampleSlot->stories_content);
        $this->assertNotNull($sampleSlot->ecommerce_product_id);
    }

    public function test_audit_fixes_webhook_verification_handoff_resume_abandoned_preview_currency_and_sales_shortcut(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Meta GET Webhook Verification (hub.challenge)
        Config::set('services.whatsapp.verify_token', 'test_meta_verify_2026');

        $validVerify = $this->get('/api/webhooks/ecommerce/whatsapp-inbound?hub.mode=subscribe&hub.verify_token=test_meta_verify_2026&hub.challenge=CHALLENGE_998877');
        $validVerify->assertStatus(200);
        $this->assertSame('CHALLENGE_998877', $validVerify->getContent());

        $invalidVerify = $this->get('/api/webhooks/ecommerce/whatsapp-inbound?hub.mode=subscribe&hub.verify_token=wrong_token&hub.challenge=CHALLENGE_998877');
        $invalidVerify->assertStatus(403);

        // 2. toggleHandoff() Resume State Mapping across Playbooks
        $customer = EcommerceCustomer::create([
            'name' => 'Zainab Ali',
            'phone' => '923005556677',
            'total_spend' => 0,
            'total_orders' => 0,
        ]);

        $checkout = EcommerceAbandonedCheckout::create([
            'shopify_checkout_id' => 'CHK-PREVIEW-505',
            'shopify_checkout_token' => 'tok_preview_505',
            'ecommerce_customer_id' => $customer->id,
            'customer_name' => 'Zainab Ali',
            'phone' => $customer->phone,
            'region' => 'PK',
            'currency' => 'PKR',
            'subtotal_price' => 2700,
            'abandoned_checkout_url' => 'https://laleenrituals.com/checkouts/ac/tok_preview_505/recover',
            'line_items_json' => [
                [
                    'title' => 'Hunza Apricot Hair Oil',
                    'quantity' => 1,
                    'price' => 1500,
                    'ecommerce_product_id' => $this->oilProduct->id,
                ],
                [
                    'title' => 'Sulfate-Free Botanical Shampoo',
                    'quantity' => 1,
                    'price' => 1200,
                    'ecommerce_product_id' => $this->shampooProduct->id,
                ],
            ],
            'status' => 'abandoned',
            'abandoned_at' => now()->subMinutes(20),
        ]);

        /** @var EcommerceAiSalesAgentService $agent */
        $agent = app(EcommerceAiSalesAgentService::class);
        $thread = $agent->initiateAbandonedCheckoutRescue($checkout);
        $this->assertNotNull($thread);

        // Pause AI (switch to human_handoff)
        $this->post("/ecommerce/hub/ai-agent/threads/{$thread->id}/toggle-handoff")->assertRedirect();
        $this->assertSame('human_handoff', $thread->fresh()->state);

        // Resume AI -> should map back to 'abandoned_followup_sent'
        $this->post("/ecommerce/hub/ai-agent/threads/{$thread->id}/toggle-handoff")->assertRedirect();
        $this->assertSame('abandoned_followup_sent', $thread->fresh()->state);

        // 3. Abandoned Cart Line Items Preview in Right-Hand Receipt Panel & Unified Chat Box
        $aiPage = $this->get("/ecommerce/hub/ai-agent?region=pk&thread_id={$thread->id}");
        $aiPage->assertStatus(200);
        $aiPage->assertSee('Abandoned Cart Preview');
        $aiPage->assertSee('1x Hunza Apricot Hair Oil');
        $aiPage->assertSee('1x Sulfate-Free Botanical Shampoo');
        $aiPage->assertSee('2,700');
        $aiPage->assertSee('id="chat-messages-container"', false);
        $aiPage->assertSee('Chat as Customer (Test AI)');
        $aiPage->assertSee('Reply as Zara (Staff Override)');
        $aiPage->assertSee('Send as Customer ↵');

        // Verify simulateInbound auto-replies immediately even when mode is set to 'copilot'
        $settings = EcommerceAiSetting::current();
        $settings->update(['mode' => 'copilot', 'whatsapp_driver' => 'simulator']);

        $simResp = $this->post("/ecommerce/hub/ai-agent/threads/{$thread->id}/simulate-inbound", [
            'customer_reply' => 'Does it help hairfall?',
            'resume_ai' => 1,
        ]);
        $simResp->assertRedirect();

        $latestOutbound = $thread->messages()
            ->where('direction', 'outbound')
            ->where('sender_type', 'ai')
            ->latest('id')
            ->first();
        $this->assertNotNull($latestOutbound);
        $this->assertSame('sent', $latestOutbound->delivery_status);

        // 4. Dynamic Regional Currency in ads-plan.blade.php
        $adsPagePk = $this->get('/ecommerce/marketing/ads-plan?region=pk');
        $adsPagePk->assertStatus(200);
        $adsPagePk->assertSee('Total Planned Budget:');
        $adsPagePk->assertSee('0 PKR');

        $adsPageQa = $this->get('/ecommerce/marketing/ads-plan?region=qa');
        $adsPageQa->assertStatus(200);
        $adsPageQa->assertSee('0 QAR');

        // 5. Direct WhatsApp AI Thread Shortcut (💬 AI Chat) in sales.blade.php
        EcommerceSale::create([
            'ecommerce_product_id' => $this->oilProduct->id,
            'ecommerce_customer_id' => $customer->id,
            'order_group_id' => 'ORD-SHORTCUT-99',
            'reference_id' => '#9901',
            'customer_name' => 'Zainab Ali',
            'customer_phone' => $customer->phone,
            'quantity' => 1,
            'channel' => 'Shopify (Pakistan)',
            'order_status' => 'pending',
            'payment_status' => 'unpaid_cod',
            'stock_deducted' => true,
            'unit_price' => 1500,
            'total_price' => 1500,
        ]);

        $salesPage = $this->get('/ecommerce/hub/sales?region=pk');
        $salesPage->assertStatus(200);
        $salesPage->assertSee('💬 AI Chat');
        $salesPage->assertSee('order_group_id=ORD-SHORTCUT-99');
    }

    public function test_blank_cold_customer_chat_and_reset_and_bubble_alignment(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Start a blank cold customer chat (no scenario / no order)
        $startResp = $this->post('/ecommerce/hub/ai-agent/start-cold-chat', [
            'region' => 'pk',
        ]);
        $startResp->assertRedirect();

        $coldThread = EcommerceWhatsappThread::where('playbook', 'inbound_support')
            ->latest('id')
            ->first();
        $this->assertNotNull($coldThread);
        $this->assertSame('inbound_active', $coldThread->state);
        $this->assertNotNull($coldThread->order_group_id);
        $this->assertStringStartsWith('COLD-', (string) $coldThread->order_group_id);
        $this->assertStringStartsWith('New WhatsApp Visitor #', (string) $coldThread->customer?->name);
        $this->assertStringContainsString('@whatsapp.local', (string) $coldThread->customer?->email);

        // Verify no outbound AI template was sent—only the subtle system note
        $this->assertSame(0, $coldThread->messages()->where('sender_type', 'ai')->count());
        $this->assertSame(1, $coldThread->messages()->where('sender_type', 'system_event')->count());

        // 2. Render page: verify Blank Chat button, Clear/Reset button, Panels toggle, AJAX containers, and Catalog card
        $page = $this->get("/ecommerce/hub/ai-agent?region=pk&thread_id={$coldThread->id}");
        $page->assertStatus(200);
        $page->assertSee('+ New Blank Chat');
        $page->assertSee('↺ Reset');
        $page->assertSee('◫ Panels');
        $page->assertSee('✕ Hide Chips');
        $page->assertSee('bubbleSlideUp', false);
        $page->assertSee('id="ai-chat-timeline"', false);
        $page->assertSee('id="ai-order-receipt-panel"', false);
        $page->assertSee('appendZaraTypingIndicator', false);
        $page->assertSee('Catalog &middot; PK', false);
        $page->assertSee('Hunza Apricot Hair Oil');
        $page->assertSee('Sulfate-Free Botanical Shampoo');

        // 3. Send a message via JSON AJAX as the cold customer -> verify JSON response structure (messages_html, receipt_html, kpis_html)
        $ajaxReply = $this->postJson("/ecommerce/hub/ai-agent/threads/{$coldThread->id}/simulate-inbound", [
            'customer_reply' => 'Hi!',
            'resume_ai' => 1,
        ]);
        $ajaxReply->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'status' => 'ok',
                'thread_id' => $coldThread->id,
            ])
            ->assertJsonStructure([
                'ok',
                'status',
                'messages_html',
                'new_messages',
                'receipt_html',
                'kpis_html',
            ]);

        // Verify legacy MySQL utf8mb3 4-byte emoji sanitizer converts 4-byte emojis into 3-byte BMP symbols
        $rawEmojiText = '💬 Blank Cold Chat 🎉 Order 📦 Confirmed 🔄 Reset 🚨 Alert';
        $sanitizedText = \App\Models\EcommerceWhatsappMessage::sanitizeForLegacyMysql($rawEmojiText);
        $this->assertSame(0, preg_match('/[\x{10000}-\x{10FFFF}]/u', $sanitizedText));
        $this->assertStringContainsString('✨ Blank Cold Chat', $sanitizedText);

        $pageAfterReply = $this->get("/ecommerce/hub/ai-agent?region=pk&thread_id={$coldThread->id}");
        $pageAfterReply->assertStatus(200);
        $pageAfterReply->assertSee('You (Customer)');
        $pageAfterReply->assertSee('✨ Zara (Laleen AI)');

        // 4. Reset/Clear the chat -> verify AI messages are wiped and thread returns to clean state
        $this->post("/ecommerce/hub/ai-agent/threads/{$coldThread->id}/reset-chat")
            ->assertRedirect();

        $this->assertSame(0, $coldThread->fresh()->messages()->where('sender_type', 'ai')->count());
        $this->assertSame('inbound_active', $coldThread->fresh()->state);
    }

    public function test_cold_chat_three_step_cod_booking_multi_bottle_upgrade_and_safe_revert(): void
    {
        $this->actingAs($this->adminUser);

        // Start a blank cold customer chat (no prior Shopify checkout, no order, no address on file)
        $this->post('/ecommerce/hub/ai-agent/start-cold-chat', [
            'region' => 'pk',
        ])->assertRedirect();

        $thread = EcommerceWhatsappThread::where('playbook', 'inbound_support')
            ->latest('id')
            ->first();
        $this->assertNotNull($thread);

        /** @var EcommerceAiSalesAgentService $agent */
        $agent = app(EcommerceAiSalesAgentService::class);

        // Step 1: Cold customer says "Filhal sirf oil bhej dein, let me try the oil first" WITHOUT providing an address
        // -> Zara must NOT create an address-less DB order! She shares the Shopify link + COMPLETE20 bundle tip + asks for Full Name, Complete Delivery Address & City
        $step1 = $agent->handleInboundCustomerMessage($thread, 'Filhal sirf oil bhej dein, let me try the oil first');
        $thread = $step1['thread'];

        $this->assertSame('send_checkout_link', $step1['ai_decision']['should_trigger_action']);
        $this->assertTrue(EcommerceWhatsappThread::isColdChatOrderRef($thread->order_group_id));
        $this->assertSame('inbound_support', $thread->playbook);
        $this->assertSame('inbound_active', $thread->state);
        $this->assertSame($this->oilProduct->id, (int) $thread->offered_product_id);

        $step1Reply = (string) $step1['outbound_message']?->message_body;
        $this->assertStringContainsString('https://', $step1Reply);
        $this->assertStringContainsString('COMPLETE20', $step1Reply);
        $this->assertStringContainsString('Full Name, Complete Delivery Address & City', $step1Reply);

        // Step 2: Cold customer replies with their actual shipping address
        // -> Now Zara extracts & saves their address, creates the base COD order (is_ai_upsell = false), and pitches the 20% off Open-Box upgrade!
        $step2 = $agent->handleInboundCustomerMessage($thread, 'Ayesha Khan, House 14, Street 5, DHA Phase 6, Lahore');
        $thread = $step2['thread'];

        $this->assertSame('convert_abandoned_to_cod_order', $step2['ai_decision']['should_trigger_action']);
        $this->assertNotNull($thread->order_group_id);
        $this->assertSame('cod_upsell', $thread->playbook);
        $this->assertSame('cod_confirmed_upsell_pitched', $thread->state);
        $this->assertSame('Ayesha Khan, House 14, Street 5, DHA Phase 6, Lahore', $thread->delivery_notes);
        $this->assertSame('Ayesha Khan', $thread->customer?->name);
        $this->assertSame($this->shampooProduct->id, (int) $thread->offered_product_id);
        $this->assertStringContainsString('New WhatsApp COD Order Created!', (string) $step2['system_event']?->message_body);
        $this->assertStringContainsString('UPGRADE', (string) $step2['outbound_message']?->message_body);

        $baseRows = EcommerceSale::where('order_group_id', $thread->order_group_id)->get();
        $this->assertCount(1, $baseRows);
        $baseRow = $baseRows->first();
        $this->assertFalse((bool) $baseRow->is_ai_upsell);
        $this->assertSame($this->oilProduct->id, (int) $baseRow->ecommerce_product_id);
        $this->assertSame(1, (int) $baseRow->quantity);
        $this->assertSame('Ayesha Khan, House 14, Street 5, DHA Phase 6, Lahore', $baseRow->delivery_notes);
        $baseTotal = (float) $baseRow->total_price;
        $this->assertGreaterThan(0, $baseTotal);

        // Step 3: Customer says "Okay add 2 bottles to my box"
        $step3 = $agent->handleInboundCustomerMessage($thread, 'Okay add 2 bottles to my box');
        $thread = $step3['thread'];

        $this->assertSame('execute_order_upgrade', $step3['ai_decision']['should_trigger_action']);
        $this->assertSame(2, (int) $step3['ai_decision']['requested_quantity']);
        $this->assertSame('upsell_accepted', $thread->state);

        $allRowsAfterUpgrade = EcommerceSale::where('order_group_id', $thread->order_group_id)->get();
        $this->assertCount(2, $allRowsAfterUpgrade);

        $upsellRow = $allRowsAfterUpgrade->where('is_ai_upsell', true)->first();
        $this->assertNotNull($upsellRow);
        $this->assertSame(2, (int) $upsellRow->quantity);
        $upgradedTotal = (float) $allRowsAfterUpgrade->sum('total_price');
        $this->assertGreaterThan($baseTotal, $upgradedTotal);
        $this->assertStringContainsString(number_format($upgradedTotal, 0), (string) $step3['outbound_message']?->message_body);

        // Step 4: Customer says "Wait sorry, remove the extra bottles, only send my original order"
        $step4 = $agent->handleInboundCustomerMessage($thread, 'Wait sorry, remove the extra bottles, only send my original order');
        $thread = $step4['thread'];

        $this->assertSame('revert_upsell', $step4['ai_decision']['should_trigger_action']);
        $this->assertSame('upsell_declined', $thread->state);
        $this->assertEquals(0.0, (float) $thread->upsell_revenue_generated);

        $remainingRows = EcommerceSale::where('order_group_id', $thread->order_group_id)->get();
        $this->assertCount(1, $remainingRows);
        $remainingRow = $remainingRows->first();
        $this->assertFalse((bool) $remainingRow->is_ai_upsell);
        $this->assertSame($this->oilProduct->id, (int) $remainingRow->ecommerce_product_id);
        $this->assertSame(1, (int) $remainingRow->quantity);
        $this->assertEquals($baseTotal, (float) $remainingRow->total_price);
        $this->assertStringNotContainsString('PKR 0)', (string) $step4['outbound_message']?->message_body);
        $this->assertStringContainsString(number_format($baseTotal, 0), (string) $step4['outbound_message']?->message_body);
    }
}
