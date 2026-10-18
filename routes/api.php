<?php

use App\Http\Controllers\Ecommerce\EcommerceCustomerController;
use App\Http\Controllers\Ecommerce\EcommerceSalesController;
use App\Http\Controllers\Ecommerce\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/ecommerce/customers/search', [EcommerceSalesController::class, 'searchCustomer'])
    ->name('api.ecommerce.customers.search');

Route::get('/ecommerce/customers/suggestions', [EcommerceCustomerController::class, 'searchSuggestions'])
    ->name('api.ecommerce.customers.suggestions');

Route::post('/webhooks/shopify/orders', [ShopifyWebhookController::class, 'storeOrder'])
    ->name('api.webhooks.shopify.orders');
