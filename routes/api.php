<?php

use App\Http\Controllers\Ecommerce\EcommerceAiAgentController;
use App\Http\Controllers\Ecommerce\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:120,1')->group(function () {
    Route::post('/webhooks/shopify/orders', [ShopifyWebhookController::class, 'storeOrder'])
        ->name('api.webhooks.shopify.orders');

    Route::post('/webhooks/shopify/orders-updated', [ShopifyWebhookController::class, 'updateOrder'])
        ->name('api.webhooks.shopify.orders-updated');

    Route::post('/webhooks/shopify/orders-cancelled', [ShopifyWebhookController::class, 'cancelOrder'])
        ->name('api.webhooks.shopify.orders-cancelled');

    Route::post('/webhooks/shopify/orders-fulfilled', [ShopifyWebhookController::class, 'fulfillOrder'])
        ->name('api.webhooks.shopify.orders-fulfilled');

    Route::post('/webhooks/shopify/checkouts-create', [ShopifyWebhookController::class, 'handleCheckoutWebhook'])
        ->name('api.webhooks.shopify.checkouts-create');

    Route::post('/webhooks/shopify/checkouts-update', [ShopifyWebhookController::class, 'handleCheckoutWebhook'])
        ->name('api.webhooks.shopify.checkouts-update');

    Route::get('/webhooks/ecommerce/whatsapp-inbound', [EcommerceAiAgentController::class, 'verifyWhatsappWebhook'])
        ->name('api.webhooks.ecommerce.whatsapp-inbound.verify');

    Route::post('/webhooks/ecommerce/whatsapp-inbound', [EcommerceAiAgentController::class, 'inboundWebhook'])
        ->name('api.webhooks.ecommerce.whatsapp-inbound');
});
