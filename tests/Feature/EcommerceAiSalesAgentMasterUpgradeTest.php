<?php

namespace Tests\Feature;

use App\Models\EcommerceAiSetting;
use App\Models\EcommerceBundle;
use App\Models\EcommerceBundleItem;
use App\Models\EcommerceCustomer;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductLine;
use App\Models\EcommerceSale;
use App\Models\EcommerceWhatsappMessage;
use App\Models\EcommerceWhatsappThread;
use App\Services\Ecommerce\EcommerceAiSalesAgentService;
use App\Services\Ecommerce\EcommerceWhatsappTransportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EcommerceAiSalesAgentMasterUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected EcommerceProduct $apricotOil;
    protected EcommerceProduct $botanicalShampoo;
    protected EcommerceAiSetting $settings;

    protected function setUp(): void
    {
        parent::setUp();

        // Force deterministic mode by default in unit/feature tests unless testing Gemini HTTP mock
        Config::set('services.gemini.api_key', '');
        Config::set('services.gemini.key', '');
        Config::set('services.whatsapp.webhook_token', null);

        $oilLine = EcommerceProductLine::create(['name' => 'Hair Oil']);
        $shampooLine = EcommerceProductLine::create(['name' => 'Shampoo']);

        $this->apricotOil = EcommerceProduct::create([
            'name' => 'Hunza Apricot Oil',
            'product_line_id' => $oilLine->id,
            'sku' => 'LAL-OIL-100',
            'shopify_url' => '/products/hunza-apricot-oil',
            'unit_size' => '100ml',
            'volume_ml' => 100,
            'stock_pakistan' => 50,
            'stock_qatar' => 25,
            'sold_pakistan' => 10,
            'sold_qatar' => 5,
            'selling_price' => 1500.00,
        ]);

        $this->botanicalShampoo = EcommerceProduct::create([
            'name' => 'Sulfate-Free Botanical Shampoo',
            'product_line_id' => $shampooLine->id,
            'sku' => 'LAL-SHM-200',
            'shopify_url' => '/products/sulfate-free-botanical-shampoo',
            'unit_size' => '200ml',
            'volume_ml' => 200,
            'stock_pakistan' => 40,
            'stock_qatar' => 20,
            'sold_pakistan' => 8,
            'sold_qatar' => 4,
            'selling_price' => 1200.00,
        ]);

        $this->settings = EcommerceAiSetting::current();
        $this->settings->update([
            'mode' => 'autopilot',
            'whatsapp_driver' => 'simulator',
            'upsell_discount_percent' => 20.00,
        ]);
    }

    public function test_expanded_botanical_faq_is_present_in_default_knowledge_base(): void
    {
        $setting = EcommerceAiSetting::current();

        $this->assertStringContainsString('Oily vs. Dry Scalp Application:', $setting->brand_knowledge_base);
        $this->assertStringContainsString('Bottle Size & Longevity:', $setting->brand_knowledge_base);
        $this->assertStringContainsString('Men, Beard & Kids Safety:', $setting->brand_knowledge_base);
        $this->assertStringContainsString('Color, Keratin & Protein Treated Hair:', $setting->brand_knowledge_base);
        $this->assertStringContainsString('Shelf Life:', $setting->brand_knowledge_base);
    }

    public function test_triple_threat_pitch_and_1_pivot_downsell_rule_and_lifecycle_memory(): void
    {
        $vipCustomer = EcommerceCustomer::create([
            'name' => 'Ayesha Khan',
            'phone' => '923001112233',
            'total_spend' => 4500.00,
            'total_orders' => 3,
        ]);

        $orderGroupId = 'ORD-VIP-1001';
        EcommerceSale::create([
            'ecommerce_product_id' => $this->apricotOil->id,
            'ecommerce_customer_id' => $vipCustomer->id,
            'order_group_id' => $orderGroupId,
            'reference_id' => '#VIP101',
            'customer_name' => $vipCustomer->name,
            'customer_phone' => $vipCustomer->phone,
            'quantity' => 1,
            'channel' => 'Shopify (Pakistan)',
            'order_status' => 'pending',
            'payment_status' => 'unpaid_cod',
            'stock_deducted' => true,
            'unit_price' => 1500.00,
            'total_price' => 1500.00,
        ]);

        /** @var EcommerceAiSalesAgentService $service */
        $service = app(EcommerceAiSalesAgentService::class);
        $thread = $service->initiateCodConfirmation($orderGroupId);

        $this->assertNotNull($thread);
        $firstOutbound = $thread->messages->last();
        // Repeat VIP lifecycle greeting ("Welcome back")
        $this->assertStringContainsString('Welcome back to the Laleen family', $firstOutbound->message_body);

        // Customer confirms in native Urdu script
        $confirmResult = $service->handleInboundCustomerMessage($thread, 'جی ہاں کنفرم کر دیں');
        $this->assertSame('confirm_cod', $confirmResult['ai_decision']['detected_intent']);
        $this->assertSame('cod_confirmed_upsell_pitched', $confirmResult['thread']->state);
        $this->assertStringContainsString('PKR 40', $confirmResult['outbound_message']->message_body);

        // 1st Objection ("I'll try the oil first") -> triggers 1-Pivot Downsell (Complementary OR 2nd bottle of Hero Product)
        $pivotResult = $service->handleInboundCustomerMessage($confirmResult['thread'], "I'll try the oil first");
        $this->assertSame('none', $pivotResult['ai_decision']['should_trigger_action']);
        $this->assertSame('cod_confirmed_upsell_pitched', $pivotResult['thread']->state);
        $this->assertStringContainsString('2nd bottle of Hunza Apricot Oil', $pivotResult['outbound_message']->message_body);
        $this->assertStringContainsString('PKR 40 per wash', $pivotResult['outbound_message']->message_body);

        // 2nd Decline -> immediately locks order without pressure
        $declineResult = $service->handleInboundCustomerMessage($pivotResult['thread'], 'No thanks, ship just the oil');
        $this->assertSame('lock_order_without_upsell', $declineResult['ai_decision']['should_trigger_action']);
        $this->assertSame('upsell_declined', $declineResult['thread']->state);
    }

    public function test_multi_quantity_upgrade_and_post_upgrade_reversal_action(): void
    {
        $customer = EcommerceCustomer::create([
            'name' => 'Zainab Ali',
            'phone' => '923219998877',
            'total_spend' => 0,
            'total_orders' => 1,
        ]);

        $orderGroupId = 'ORD-QTY-2002';
        EcommerceSale::create([
            'ecommerce_product_id' => $this->apricotOil->id,
            'ecommerce_customer_id' => $customer->id,
            'order_group_id' => $orderGroupId,
            'reference_id' => '#QTY202',
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'quantity' => 1,
            'channel' => 'Shopify (Pakistan)',
            'order_status' => 'pending',
            'payment_status' => 'unpaid_cod',
            'stock_deducted' => true,
            'unit_price' => 1500.00,
            'total_price' => 1500.00,
        ]);

        /** @var EcommerceAiSalesAgentService $service */
        $service = app(EcommerceAiSalesAgentService::class);
        $thread = $service->initiateCodConfirmation($orderGroupId);

        // Confirm COD
        $service->handleInboundCustomerMessage($thread, 'YES');
        $thread->refresh();
        $this->assertSame('cod_confirmed_upsell_pitched', $thread->state);

        // Multi-quantity upgrade: "UPGRADE please add 2 shampoos"
        $initialShampooStock = (float) $this->botanicalShampoo->stock_pakistan;
        $initialShampooSold = (float) $this->botanicalShampoo->sold_pakistan;

        $upgradeResult = $service->handleInboundCustomerMessage($thread, 'UPGRADE please add 2 shampoos');
        $this->assertSame('execute_order_upgrade', $upgradeResult['ai_decision']['should_trigger_action']);
        $this->assertSame(2, $upgradeResult['ai_decision']['requested_quantity']);
        $this->assertSame('upsell_accepted', $upgradeResult['thread']->state);

        // 20% off 1200 = 960 per unit -> 2 units = 1920
        $this->assertEquals(1920.00, (float) $upgradeResult['thread']->upsell_revenue_generated);

        $upsellSale = EcommerceSale::where('order_group_id', $orderGroupId)
            ->where('is_ai_upsell', true)
            ->first();
        $this->assertNotNull($upsellSale);
        $this->assertSame(2, (int) $upsellSale->quantity);
        $this->assertEquals(960.00, (float) $upsellSale->unit_price);
        $this->assertEquals(1920.00, (float) $upsellSale->total_price);

        $this->botanicalShampoo->refresh();
        $this->assertEquals($initialShampooStock - 2, (float) $this->botanicalShampoo->stock_pakistan);
        $this->assertEquals($initialShampooSold + 2, (float) $this->botanicalShampoo->sold_pakistan);

        // Post-Upgrade Reversal: "Wait remove the shampoo, only send the oil"
        $revertResult = $service->handleInboundCustomerMessage($upgradeResult['thread'], 'Wait remove the shampoo, only send the oil');
        $this->assertSame('revert_upsell', $revertResult['ai_decision']['detected_intent']);
        $this->assertSame('revert_upsell', $revertResult['ai_decision']['should_trigger_action']);
        $this->assertSame('upsell_declined', $revertResult['thread']->state);
        $this->assertEquals(0.00, (float) $revertResult['thread']->upsell_revenue_generated);
        $this->assertStringContainsString('Removed AI upsell item per customer request', $revertResult['system_event']->message_body);

        // Stock restored & upsell row deleted
        $this->botanicalShampoo->refresh();
        $this->assertEquals($initialShampooStock, (float) $this->botanicalShampoo->stock_pakistan);
        $this->assertEquals($initialShampooSold, (float) $this->botanicalShampoo->sold_pakistan);
        $this->assertDatabaseMissing('ecommerce_sales', [
            'order_group_id' => $orderGroupId,
            'is_ai_upsell' => true,
        ]);
    }

    public function test_duplicate_order_deduplication_action(): void
    {
        $customer = EcommerceCustomer::create([
            'name' => 'Hira Tariq',
            'phone' => '923334445566',
        ]);

        // Active order group
        EcommerceSale::create([
            'ecommerce_product_id' => $this->apricotOil->id,
            'ecommerce_customer_id' => $customer->id,
            'order_group_id' => 'ORD-MAIN-1',
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'quantity' => 1,
            'channel' => 'Shopify (Pakistan)',
            'order_status' => 'pending',
            'payment_status' => 'unpaid_cod',
            'stock_deducted' => true,
            'unit_price' => 1500.00,
            'total_price' => 1500.00,
        ]);

        // Duplicate order group placed within last 48h
        EcommerceSale::create([
            'ecommerce_product_id' => $this->apricotOil->id,
            'ecommerce_customer_id' => $customer->id,
            'order_group_id' => 'ORD-DUP-2',
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'quantity' => 1,
            'channel' => 'Shopify (Pakistan)',
            'order_status' => 'pending',
            'payment_status' => 'unpaid_cod',
            'stock_deducted' => true,
            'unit_price' => 1500.00,
            'total_price' => 1500.00,
        ]);

        $oilStockBefore = (float) $this->apricotOil->stock_pakistan;

        /** @var EcommerceAiSalesAgentService $service */
        $service = app(EcommerceAiSalesAgentService::class);
        $thread = $service->initiateCodConfirmation('ORD-MAIN-1');

        $dupResult = $service->handleInboundCustomerMessage($thread, 'I accidentally placed two orders, please cancel duplicate order');
        $this->assertSame('cancel_duplicate_orders', $dupResult['ai_decision']['should_trigger_action']);
        $this->assertStringContainsString('Cancelled duplicate pending order(s) per customer request', $dupResult['system_event']->message_body);

        $dupSale = EcommerceSale::where('order_group_id', 'ORD-DUP-2')->first();
        $mainSale = EcommerceSale::where('order_group_id', 'ORD-MAIN-1')->first();
        $this->assertSame('cancelled', $dupSale->order_status);
        $this->assertSame('pending', $mainSale->order_status);
        $this->assertNotNull($mainSale->cod_confirmed_at);

        $this->apricotOil->refresh();
        $this->assertEquals($oilStockBefore + 1, (float) $this->apricotOil->stock_pakistan);
    }

    public function test_inbound_cold_buyer_consultation_and_meta_webhook_media_and_opt_out(): void
    {
        // 1. Cold buyer sends message to webhook from unknown phone number
        $coldResponse = $this->postJson('/api/webhooks/ecommerce/whatsapp-inbound', [
            'from' => '03017778899',
            'profile_name' => 'Mariam Siddiqui',
            'text' => ['body' => 'Hi, I have severe hairfall and dry roots, what do you recommend?'],
            'message_id' => 'wamid.cold.1',
        ]);

        $coldResponse->assertStatus(200);
        $threadId = $coldResponse->json('thread_id');
        $thread = EcommerceWhatsappThread::find($threadId);

        $this->assertNotNull($thread);
        $this->assertSame('inbound_support', $thread->playbook);
        $this->assertSame('inbound_active', $thread->state);

        $outbound = $thread->messages()->where('direction', 'outbound')->latest('id')->first();
        $this->assertNotNull($outbound);
        $this->assertStringContainsString('Hunza Apricot Oil', $outbound->message_body);
        $this->assertStringContainsString('cold-pressed from raw kernels handpicked in the Hunza valleys', $outbound->message_body);
        $this->assertStringContainsString('You can place your Cash-on-Delivery order in 30 seconds via our official website link below:', $outbound->message_body);
        $this->assertStringContainsString('PKR 1,500', $outbound->message_body);
        $this->assertStringContainsString('https://laleenrituals.com/products/hunza-apricot-oil', $outbound->message_body);

        // 2. Customer sends a WhatsApp Location Pin via Meta Cloud API payload structure
        $locResponse = $this->postJson('/api/webhooks/ecommerce/whatsapp-inbound', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '923017778899',
                            'id' => 'wamid.loc.2',
                            'type' => 'location',
                            'location' => [
                                'latitude' => 31.5204,
                                'longitude' => 74.3587,
                                'name' => 'DHA Phase 5',
                                'address' => 'Street 12, Lahore',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ]);

        $locResponse->assertStatus(200);
        $thread->refresh();
        $this->assertStringContainsString('DHA Phase 5', (string) $thread->delivery_notes);
        $this->assertStringContainsString('31.5204', (string) $thread->delivery_notes);

        // 3. Customer sends a Voice Note with no text body
        $voiceResponse = $this->postJson('/api/webhooks/ecommerce/whatsapp-inbound', [
            'from' => '923017778899',
            'type' => 'audio',
            'audio' => ['id' => 'media.123'],
            'message_id' => 'wamid.voice.3',
        ]);
        $voiceResponse->assertStatus(200);
        $this->assertDatabaseHas('ecommerce_whatsapp_messages', [
            'thread_id' => $thread->id,
            'message_body' => '[Voice Note Received]',
        ]);

        // 4. Customer replies STOP -> Opt-Out compliance
        $stopResponse = $this->postJson('/api/webhooks/ecommerce/whatsapp-inbound', [
            'from' => '923017778899',
            'text' => 'STOP',
            'message_id' => 'wamid.stop.4',
        ]);
        $stopResponse->assertStatus(200);
        $thread->refresh();
        $this->assertSame('opted_out', $thread->state);
    }

    public function test_cultural_instincts_wismo_payment_methods_and_meta_24h_template_fallback(): void
    {
        $customer = EcommerceCustomer::create([
            'name' => 'Sana Mir',
            'phone' => '923451234567',
        ]);

        $orderGroupId = 'ORD-CULT-500';
        EcommerceSale::create([
            'ecommerce_product_id' => $this->apricotOil->id,
            'ecommerce_customer_id' => $customer->id,
            'order_group_id' => $orderGroupId,
            'reference_id' => '#CULT50',
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'quantity' => 1,
            'channel' => 'Shopify (Pakistan)',
            'order_status' => 'shipped',
            'payment_status' => 'unpaid_cod',
            'stock_deducted' => true,
            'unit_price' => 1500.00,
            'total_price' => 1500.00,
        ]);

        /** @var EcommerceAiSalesAgentService $service */
        $service = app(EcommerceAiSalesAgentService::class);
        $thread = $service->initiateCodConfirmation($orderGroupId, true);

        // WISMO on shipped order
        $wismoRes = $service->handleInboundCustomerMessage($thread, 'Where is my order? Has it shipped?');
        $this->assertStringContainsString('dispatched with our courier', $wismoRes['outbound_message']->message_body);

        // Payment method question
        $payRes = $service->handleInboundCustomerMessage($thread, 'Can I pay via EasyPaisa or JazzCash?');
        $this->assertStringContainsString('Cash on Delivery', $payRes['outbound_message']->message_body);
        $this->assertStringContainsString('EasyPaisa', $payRes['outbound_message']->message_body);

        // Salary-date future dispatch hold -> confirms order + extracts delivery note
        $salaryRes = $service->handleInboundCustomerMessage($thread, 'Please deliver next week after the 1st salary');
        $this->assertSame('confirm_cod', $salaryRes['ai_decision']['detected_intent']);
        $this->assertNotNull($salaryRes['thread']->delivery_notes);

        // Meta 24-Hour Window Template Support in EcommerceWhatsappTransportService::sendViaMetaCloud
        Config::set('services.whatsapp.token', 'test-meta-token');
        Config::set('services.whatsapp.phone_number_id', '1234567890');
        Config::set('services.whatsapp.template_name', 'laleen_order_confirm');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.meta.ok']],
            ], 200),
        ]);

        // Create a fresh thread with NO inbound messages (outside 24h window) -> should send type = "template"
        $freshCustomer = EcommerceCustomer::create([
            'name' => 'Noor Fatima',
            'phone' => '97455112233',
        ]);
        $freshThread = EcommerceWhatsappThread::create([
            'ecommerce_customer_id' => $freshCustomer->id,
            'region' => 'QA',
            'playbook' => 'cod_upsell',
            'state' => 'awaiting_cod_confirm',
        ]);
        $outMsg = EcommerceWhatsappMessage::create([
            'thread_id' => $freshThread->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'delivery_status' => 'pending_approval',
            'message_body' => 'Hello Noor!',
        ]);

        /** @var EcommerceWhatsappTransportService $transport */
        $transport = app(EcommerceWhatsappTransportService::class);
        $transport->sendViaMetaCloud($outMsg, $freshCustomer->phone, $freshThread);

        Http::assertSent(function ($request) {
            return $request->data()['type'] === 'template'
                && $request->data()['template']['name'] === 'laleen_order_confirm';
        });
        $this->assertSame('sent', $outMsg->fresh()->delivery_status);
    }
}
