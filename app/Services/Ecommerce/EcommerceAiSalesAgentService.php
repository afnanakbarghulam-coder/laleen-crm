<?php

namespace App\Services\Ecommerce;

use App\Models\EcommerceAbandonedCheckout;
use App\Models\EcommerceAiSetting;
use App\Models\EcommerceBundle;
use App\Models\EcommerceCustomer;
use App\Models\EcommercePricingModel;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use App\Models\EcommerceWhatsappMessage;
use App\Models\EcommerceWhatsappThread;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EcommerceAiSalesAgentService
{
    public function __construct(
        protected EcommerceWhatsappTransportService $transport
    ) {
    }

    /**
     * Self-healing repair for any historical/test threads or AI upsell sales
     * that were created when a product had selling_price = 0.00, plus deduplication
     * of multiple threads belonging to the same customer & region.
     */
    public function repairZeroPriceThreadsAndSales(): void
    {
        $this->deduplicateCustomerThreads();

        $settings = EcommerceAiSetting::current();
        $discountPercent = max(0.0, min(90.0, (float) ($settings->upsell_discount_percent ?? 20.00)));

        $zeroThreads = EcommerceWhatsappThread::with('offeredProduct.pricingModel')
            ->whereNotNull('offered_product_id')
            ->where(function ($q) {
                $q->whereNull('offered_addon_price')
                    ->orWhere('offered_addon_price', '<=', 0);
            })
            ->get();

        foreach ($zeroThreads as $thread) {
            if ($thread->offeredProduct) {
                $pct = $thread->playbook === 'day30_refill' ? 15.0 : $discountPercent;
                $basePrice = $thread->offeredProduct->resolveBasePrice($thread->region);
                $addonPrice = max(1.0, round($basePrice * (1 - ($pct / 100)), 2));
                $thread->offered_addon_price = $addonPrice;
                $thread->save();
            }
        }

        $zeroUpsellSales = EcommerceSale::with('product.pricingModel')
            ->where('is_ai_upsell', true)
            ->where('unit_price', '<=', 0)
            ->get();

        foreach ($zeroUpsellSales as $sale) {
            $isQatar = in_array((string) $sale->channel, EcommerceSale::QATAR_CHANNELS, true);
            $region = $isQatar ? 'QA' : 'PK';
            $currency = $isQatar ? 'QAR' : 'PKR';

            $linkedThread = $sale->order_group_id
                ? EcommerceWhatsappThread::where('order_group_id', $sale->order_group_id)->first()
                : null;

            $resolvedAddonPrice = (float) ($linkedThread?->offered_addon_price ?? 0);
            if ($resolvedAddonPrice <= 0 && $sale->product) {
                $basePrice = $sale->product->resolveBasePrice($region);
                $resolvedAddonPrice = max(1.0, round($basePrice * (1 - ($discountPercent / 100)), 2));
            }
            if ($resolvedAddonPrice <= 0) {
                $resolvedAddonPrice = $isQatar ? 52.00 : 960.00;
            }

            $qty = max(1, (int) $sale->quantity);
            $sale->unit_price = $resolvedAddonPrice;
            $sale->total_price = round($resolvedAddonPrice * $qty, 2);
            $sale->save();

            if ($linkedThread) {
                if ((float) $linkedThread->offered_addon_price <= 0) {
                    $linkedThread->offered_addon_price = $resolvedAddonPrice;
                }
                if ((float) $linkedThread->upsell_revenue_generated <= 0) {
                    $linkedThread->upsell_revenue_generated = $sale->total_price;
                }
                $linkedThread->save();

                $fmtPrice = number_format((float) $sale->total_price, 0);
                foreach ($linkedThread->messages()->where('sender_type', 'system_event')->get() as $sysMsg) {
                    if (str_contains($sysMsg->message_body, '0.00')) {
                        $sysMsg->message_body = str_replace(
                            ["{$currency} 0.00", '0.00'],
                            ["{$currency} {$fmtPrice}", $fmtPrice],
                            $sysMsg->message_body
                        );
                        $sysMsg->save();
                    }
                }
            }

            if ($sale->ecommerce_customer_id) {
                EcommerceCustomer::find($sale->ecommerce_customer_id)?->recalculateLtv();
            }
        }
    }

    /**
     * Deduplicate any multiple EcommerceWhatsappThread rows for the same customer and region
     * by merging all messages and accumulated upsell revenue into the latest thread.
     */
    public function deduplicateCustomerThreads(?int $customerId = null, ?string $region = null): ?EcommerceWhatsappThread
    {
        $query = EcommerceWhatsappThread::query()
            ->whereNotNull('ecommerce_customer_id')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($customerId !== null) {
            $query->where('ecommerce_customer_id', $customerId);
        }
        if ($region !== null && trim($region) !== '') {
            $query->where('region', strtoupper(trim($region)));
        }

        $grouped = $query->get()->groupBy(
            fn (EcommerceWhatsappThread $t) => $t->ecommerce_customer_id . '|' . strtoupper((string) $t->region)
        );

        $latestKeeper = null;

        foreach ($grouped as $threadsInGroup) {
            /** @var EcommerceWhatsappThread $keeper */
            $keeper = $threadsInGroup->first();
            $latestKeeper = $keeper;

            if ($threadsInGroup->count() <= 1) {
                continue;
            }

            $duplicates = $threadsInGroup->slice(1);
            $duplicateIds = $duplicates->pluck('id')->all();

            DB::transaction(function () use ($keeper, $duplicates, $duplicateIds) {
                EcommerceWhatsappMessage::whereIn('thread_id', $duplicateIds)
                    ->update(['thread_id' => $keeper->id]);

                $extraRevenue = (float) $duplicates->sum('upsell_revenue_generated');
                $extraUnread = (int) $duplicates->sum('unread_count');

                if ($extraRevenue > 0) {
                    $keeper->upsell_revenue_generated = round((float) $keeper->upsell_revenue_generated + $extraRevenue, 2);
                }
                if ($extraUnread > 0) {
                    $keeper->unread_count = (int) $keeper->unread_count + $extraUnread;
                }

                foreach ($duplicates as $dup) {
                    if (
                        EcommerceWhatsappThread::isColdChatOrderRef($keeper->order_group_id)
                        && !EcommerceWhatsappThread::isColdChatOrderRef($dup->order_group_id)
                    ) {
                        $keeper->order_group_id = $dup->order_group_id;
                    }
                    if (!$keeper->offered_product_id && $dup->offered_product_id) {
                        $keeper->offered_product_id = $dup->offered_product_id;
                    }
                    if (!$keeper->offered_bundle_id && $dup->offered_bundle_id) {
                        $keeper->offered_bundle_id = $dup->offered_bundle_id;
                    }
                    if ((float) $keeper->offered_addon_price <= 0 && (float) $dup->offered_addon_price > 0) {
                        $keeper->offered_addon_price = $dup->offered_addon_price;
                    }
                }

                $keeper->save();

                EcommerceWhatsappThread::whereIn('id', $duplicateIds)->delete();
            });
        }

        return $latestKeeper;
    }

    /**
     * Resolve or update the single unified WhatsApp thread for a customer in a region.
     */
    protected function resolveUnifiedCustomerThread(int $customerId, string $region, array $attributes): EcommerceWhatsappThread
    {
        $regionUpper = strtoupper(trim($region));
        $existing = $this->deduplicateCustomerThreads($customerId, $regionUpper);

        if ($existing) {
            $existing->fill($attributes);
            $existing->region = $regionUpper;
            $existing->save();

            return $existing;
        }

        return EcommerceWhatsappThread::create(array_merge([
            'ecommerce_customer_id' => $customerId,
            'region' => $regionUpper,
        ], $attributes));
    }

    /**
     * A. Deterministic Catalog & Complementary Pairing Resolver
     *
     * Computes exact SKU pairing and discounted add-on price in PHP so Gemini
     * never hallucinates prices or out-of-stock products.
     *
     * @param  Collection<int, EcommerceSale>  $orderSales
     * @return array{
     *   offered_product: ?EcommerceProduct,
     *   offered_bundle: ?EcommerceBundle,
     *   offered_addon_price: ?float,
     *   base_price: ?float,
     *   discount_percent: float,
     *   available_stock: float,
     *   pairing_reason: string
     * }
     */
    public function resolveComplementaryOffer(
        Collection $orderSales,
        string $region = 'PK',
        ?EcommerceAiSetting $settings = null,
        ?float $overrideDiscountPercent = null
    ): array {
        $settings = $settings ?? EcommerceAiSetting::current();
        $regionUpper = strtoupper(EcommerceSale::normalizeRegion($region));
        $stockField = $regionUpper === 'QA' ? 'stock_qatar' : 'stock_pakistan';
        $discountPercent = max(0.0, min(90.0, (float) ($overrideDiscountPercent ?? $settings->upsell_discount_percent ?? 20.00)));

        $allProducts = EcommerceProduct::with(['productLine', 'pricingModel'])
            ->orderBy('id')
            ->get();

        $inStockProducts = $allProducts->filter(fn (EcommerceProduct $p) => (float) $p->{$stockField} >= 1);

        $boughtProductIds = $orderSales
            ->pluck('ecommerce_product_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $boughtProducts = $allProducts->whereIn('id', $boughtProductIds);

        $isHairOil = function (EcommerceProduct $p): bool {
            $lineName = strtolower((string) ($p->productLine?->name ?? ''));
            $prodName = strtolower((string) $p->name);

            return str_contains($lineName, 'oil') || str_contains($prodName, 'oil');
        };

        $isShampoo = function (EcommerceProduct $p): bool {
            $lineName = strtolower((string) ($p->productLine?->name ?? ''));
            $prodName = strtolower((string) $p->name);

            return str_contains($lineName, 'shampoo') || str_contains($prodName, 'shampoo');
        };

        $boughtOil = $boughtProducts->contains($isHairOil);
        $boughtShampoo = $boughtProducts->contains($isShampoo);

        $selectedProduct = null;
        $pairingReason = 'Complementary Ritual Upgrade';

        // Rule 1: Bought Hair Oil and did NOT buy Shampoo -> select in-stock Shampoo
        if ($boughtOil && !$boughtShampoo) {
            $selectedProduct = $inStockProducts
                ->reject(fn (EcommerceProduct $p) => in_array((int) $p->id, $boughtProductIds, true))
                ->filter($isShampoo)
                ->sortByDesc(fn (EcommerceProduct $p) => $p->resolveBasePrice($regionUpper) - $p->unit_cogs)
                ->first();

            if ($selectedProduct) {
                $pairingReason = 'Complete the 2-Step Wash & Seal Ritual (Balances Oil Pre-Wash)';
            }
        }

        // Rule 2: Bought Shampoo and did NOT buy Hair Oil -> select in-stock Hair Oil
        if (!$selectedProduct && $boughtShampoo && !$boughtOil) {
            $selectedProduct = $inStockProducts
                ->reject(fn (EcommerceProduct $p) => in_array((int) $p->id, $boughtProductIds, true))
                ->filter($isHairOil)
                ->sortByDesc(fn (EcommerceProduct $p) => $p->resolveBasePrice($regionUpper) - $p->unit_cogs)
                ->first();

            if ($selectedProduct) {
                $pairingReason = 'Deep Follicle Nourishment Before Your Laleen Shampoo Ritual';
            }
        }

        // Rule 3: Highest-margin in-stock complementary product not already in their order
        if (!$selectedProduct) {
            $selectedProduct = $inStockProducts
                ->reject(fn (EcommerceProduct $p) => in_array((int) $p->id, $boughtProductIds, true))
                ->sortByDesc(fn (EcommerceProduct $p) => $p->resolveBasePrice($regionUpper) - $p->unit_cogs)
                ->first();

            if ($selectedProduct) {
                $pairingReason = 'Highest-Margin Complementary Ritual Add-On';
            }
        }

        // Rule 4: Fallback if customer already bought every distinct SKU — offer an extra unit of highest-margin in-stock SKU
        if (!$selectedProduct && $inStockProducts->isNotEmpty()) {
            $selectedProduct = $inStockProducts
                ->sortByDesc(fn (EcommerceProduct $p) => $p->resolveBasePrice($regionUpper) - $p->unit_cogs)
                ->first();
            $pairingReason = '2nd Bottle Ritual Value Add-On';
        }

        $matchingBundle = null;
        if ($selectedProduct && !empty($boughtProductIds)) {
            $targetIds = array_unique(array_merge($boughtProductIds, [(int) $selectedProduct->id]));
            $bundles = EcommerceBundle::with('items')->get();
            foreach ($bundles as $bundle) {
                $bundleProdIds = $bundle->items->pluck('ecommerce_product_id')->map(fn ($id) => (int) $id)->all();
                if (count(array_intersect($targetIds, $bundleProdIds)) >= 2) {
                    $matchingBundle = $bundle;
                    break;
                }
            }
        }

        if (!$selectedProduct) {
            return [
                'offered_product' => null,
                'offered_bundle' => null,
                'offered_addon_price' => null,
                'base_price' => null,
                'discount_percent' => $discountPercent,
                'available_stock' => 0.0,
                'pairing_reason' => 'No in-stock complementary product available',
            ];
        }

        $basePrice = $selectedProduct->resolveBasePrice($regionUpper);
        $offeredAddonPrice = max(1.0, round($basePrice * (1 - ($discountPercent / 100)), 2));

        return [
            'offered_product' => $selectedProduct,
            'offered_bundle' => $matchingBundle,
            'offered_addon_price' => $offeredAddonPrice,
            'base_price' => $basePrice,
            'discount_percent' => $discountPercent,
            'available_stock' => (float) $selectedProduct->{$stockField},
            'pairing_reason' => $pairingReason,
        ];
    }

    /**
     * Resolve the discount code for a given playbook.
     */
    public function discountCodeForPlaybook(string $playbook, ?EcommerceAiSetting $settings = null): string
    {
        $settings = $settings ?? EcommerceAiSetting::current();

        return match ($playbook) {
            'day30_refill', 'abandoned_checkout' => trim((string) ($settings->day30_discount_code ?: 'VIPREFILL15')),
            default => trim((string) ($settings->day7_discount_code ?: 'COMPLETE20')),
        };
    }

    /**
     * B.1 Playbook 1 Trigger: Instant COD Confirmation + Pre-Calculated "Box Is Still Open" Upgrade
     */
    public function initiateCodConfirmation(string $orderGroupId, bool $force = false): ?EcommerceWhatsappThread
    {
        $settings = EcommerceAiSetting::current();

        if (!$force && !$settings->cod_confirmation_enabled) {
            return null;
        }

        $sales = EcommerceSale::with(['product.productLine', 'product.pricingModel', 'customer'])
            ->where('order_group_id', $orderGroupId)
            ->whereNotIn('order_status', EcommerceSale::INACTIVE_ORDER_STATUSES)
            ->orderBy('id')
            ->get();

        if ($sales->isEmpty()) {
            return null;
        }

        $firstSale = $sales->first();
        if (!$force && $firstSale->payment_status !== 'unpaid_cod') {
            return null;
        }

        $isQatar = in_array((string) $firstSale->channel, EcommerceSale::QATAR_CHANNELS, true);
        $region = $isQatar ? 'QA' : 'PK';
        $currency = $isQatar ? 'QAR' : 'PKR';
        $defaultCountryCode = $isQatar ? '974' : '92';

        $customer = $firstSale->customer;
        if (!$customer) {
            $normalizedPhone = EcommerceCustomer::normalizePhone($firstSale->customer_phone, $defaultCountryCode);
            if ($normalizedPhone === '') {
                $normalizedPhone = ($isQatar ? '974000' : '92300') . str_pad((string) $firstSale->id, 6, '0', STR_PAD_LEFT);
            }

            $customer = EcommerceCustomer::firstOrCreate(
                ['phone' => $normalizedPhone],
                [
                    'name' => $firstSale->customer_name ?: 'Valued Customer',
                    'total_spend' => 0,
                    'total_orders' => 0,
                ]
            );

            EcommerceSale::where('order_group_id', $orderGroupId)
                ->whereNull('ecommerce_customer_id')
                ->update(['ecommerce_customer_id' => $customer->id]);
        }

        $existingThread = $this->deduplicateCustomerThreads($customer->id, $region)
            ?? EcommerceWhatsappThread::where('order_group_id', $orderGroupId)
                ->where('playbook', 'cod_upsell')
                ->first();

        if ($existingThread && !$force && $existingThread->state === 'opted_out') {
            return null;
        }

        if ($existingThread && !$force && $existingThread->order_group_id === $orderGroupId && $existingThread->playbook === 'cod_upsell') {
            return $existingThread;
        }

        $offer = $this->resolveComplementaryOffer($sales, $region, $settings);

        $customerName = trim((string) ($firstSale->customer_name ?: $customer->name ?: 'there'));
        $personaName = trim((string) ($settings->agent_persona_name ?: 'Zara'));
        $orderRef = $firstSale->reference_id ?: EcommerceWhatsappThread::formatShortOrderRef($orderGroupId);

        $itemsSummary = $sales->map(function (EcommerceSale $s) {
            $name = $s->product?->name ?? 'Laleen Hair Care';

            return $s->quantity > 1 ? "{$s->quantity}x {$name}" : $name;
        })->implode(' + ');

        $orderTotal = round((float) $sales->sum('total_price') + (float) $sales->sum('shipping_cost') + (float) $sales->sum('tax_amount'), 2);
        $formattedTotal = number_format($orderTotal, 0);

        $ordersCount = (int) ($customer->orders_count ?? 1);
        $vipGreeting = $ordersCount >= 2
            ? "Welcome back to the Laleen family, {$customerName}! ✨ This is {$personaName} from Laleen Rituals."
            : "Assalam-o-Alaikum {$customerName}! This is {$personaName} from Laleen Rituals ✨";

        $step1Body = "{$vipGreeting} We just received your Cash-on-Delivery order ({$orderRef}) for *{$itemsSummary}* (Total: *{$currency} {$formattedTotal}*).\n\n"
            . "Should our warehouse hand-pack your triple-sealed box for dispatch today? Just reply *YES* to confirm your address, or share any delivery timing note!";

        $thread = $this->resolveUnifiedCustomerThread($customer->id, $region, [
            'order_group_id' => $orderGroupId,
            'playbook' => 'cod_upsell',
            'state' => 'awaiting_cod_confirm',
            'offered_product_id' => $offer['offered_product']?->id,
            'offered_bundle_id' => $offer['offered_bundle']?->id,
            'offered_addon_price' => $offer['offered_addon_price'],
            'last_message_at' => now(),
            'nudge_sent_at' => null,
        ]);

        $deliveryStatus = $settings->isAutopilot() ? 'sent' : 'pending_approval';

        $outboundMessage = EcommerceWhatsappMessage::create([
            'thread_id' => $thread->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'delivery_status' => $deliveryStatus,
            'message_body' => $step1Body,
            'ai_metadata' => [
                'step' => 'cod_confirmation_step_1',
                'playbook' => 'cod_upsell',
                'order_ref' => $orderRef,
                'order_total' => $orderTotal,
                'offered_product_name' => $offer['offered_product']?->name,
                'offered_addon_price' => $offer['offered_addon_price'],
                'pairing_reason' => $offer['pairing_reason'],
            ],
        ]);

        if ($settings->isAutopilot()) {
            $this->transport->sendOutboundMessage($outboundMessage, $settings);
        }

        return $thread->fresh(['customer', 'offeredProduct', 'offeredBundle', 'messages']);
    }

    /**
     * Playbook 2: Day 7 Ritual Consumption Check-In & Complementary Cross-Sell
     *
     * Checks what the customer bought in their last order, shares a warm 1-sentence
     * scalp/hair ritual tip, asks how their hair feels, and pre-calculates the
     * complementary product at 20% off (`offered_product_id`, `offered_addon_price`).
     *
     * @param  string|Collection<int, EcommerceSale>|null  $baseOrderGroupIdOrSales
     */
    public function initiateDay7CrossSell(
        EcommerceCustomer $customer,
        $baseOrderGroupIdOrSales = null,
        ?EcommerceAiSetting $settings = null
    ): ?EcommerceWhatsappThread {
        $settings = $settings ?? EcommerceAiSetting::current();

        if ($baseOrderGroupIdOrSales instanceof Collection) {
            $customerSales = $baseOrderGroupIdOrSales;
        } elseif (is_string($baseOrderGroupIdOrSales) && trim($baseOrderGroupIdOrSales) !== '') {
            $customerSales = EcommerceSale::with(['product.productLine', 'product.pricingModel'])
                ->where('order_group_id', trim($baseOrderGroupIdOrSales))
                ->orderBy('id')
                ->get();
        } else {
            $latestSale = EcommerceSale::where('ecommerce_customer_id', $customer->id)
                ->whereNotIn('order_status', EcommerceSale::INACTIVE_ORDER_STATUSES)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            $customerSales = $latestSale && $latestSale->order_group_id
                ? EcommerceSale::with(['product.productLine', 'product.pricingModel'])
                    ->where('order_group_id', $latestSale->order_group_id)
                    ->get()
                : collect($latestSale ? [$latestSale] : []);
        }

        $firstSale = $customerSales->first();
        $isQatar = $firstSale
            ? in_array((string) $firstSale->channel, EcommerceSale::QATAR_CHANNELS, true)
            : str_starts_with((string) $customer->phone, '974');
        $region = $isQatar ? 'QA' : 'PK';
        $orderGroupId = $firstSale?->order_group_id;

        $offer = $this->resolveComplementaryOffer($customerSales, $region, $settings);
        $boughtProductName = $firstSale?->product?->name ?? $customer->latest_product;
        $offeredProduct = $offer['offered_product'];
        $personaName = $settings->agent_persona_name ?: 'Zara';
        $customerName = $customer->name ?: 'there';

        $ritualTip = str_contains(strtolower($boughtProductName), 'oil')
            ? 'warm 4–6 drops between your palms and massage into your scalp for 5 minutes before washing so the Vitamin E absorbs deeply into your roots.'
            : 'let the sulfate-free botanical lather sit for 60 seconds before rinsing with cool water to gently clear buildup without stripping natural oils.';

        $body = "Assalam-o-Alaikum {$customerName}! This is {$personaName} from Laleen Rituals ✨ It's been a week since your *{$boughtProductName}* arrived—most women in our Laleen community notice softer, calmer strands after their first 2 washes.\n\n"
            . "💡 *Week-1 Tip:* Try to {$ritualTip} How is your hair feeling so far, or do you have any questions about your routine?";

        $thread = $this->resolveUnifiedCustomerThread($customer->id, $region, [
            'order_group_id' => $orderGroupId,
            'playbook' => 'day7_cross_sell',
            'state' => 'day7_checkin_sent',
            'offered_product_id' => $offeredProduct?->id,
            'offered_bundle_id' => $offer['offered_bundle']?->id,
            'offered_addon_price' => $offer['offered_addon_price'],
            'last_message_at' => now(),
        ]);

        $deliveryStatus = $settings->isAutopilot() ? 'sent' : 'pending_approval';

        $msg = EcommerceWhatsappMessage::create([
            'thread_id' => $thread->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'delivery_status' => $deliveryStatus,
            'message_body' => $body,
            'ai_metadata' => [
                'step' => 'day7_ritual_checkin',
                'playbook' => 'day7_cross_sell',
                'bought_product' => $boughtProductName,
                'offered_product_name' => $offeredProduct?->name,
                'offered_addon_price' => $offer['offered_addon_price'],
            ],
        ]);

        if ($settings->isAutopilot()) {
            $this->transport->sendOutboundMessage($msg, $settings);
        }

        $customer->update([
            'last_retention_contacted_at' => now(),
            'last_retention_stage' => 'day7_cross_sell',
        ]);

        return $thread->fresh(['customer', 'offeredProduct', 'offeredBundle', 'messages']);
    }

    /**
     * Playbook 3: Day 30 VIP Refill Check-In & Direct 15% OFF Shopify Checkout Link
     *
     * Identifies the primary product from the customer's last delivered order,
     * calculates a 15% VIP Refill Discount (`VIPREFILL15`), checks in warmly asking
     * if their bottle is getting down to the last few drops, and shares their direct
     * 15% OFF VIP Refill Shopify checkout link.
     */
    public function initiateDay30Refill(
        EcommerceCustomer $customer,
        ?string $baseOrderGroupId = null,
        ?EcommerceAiSetting $settings = null
    ): ?EcommerceWhatsappThread {
        $settings = $settings ?? EcommerceAiSetting::current();

        if ($baseOrderGroupId !== null && trim($baseOrderGroupId) !== '') {
            $customerSales = EcommerceSale::with(['product.productLine', 'product.pricingModel'])
                ->where('order_group_id', trim($baseOrderGroupId))
                ->orderBy('id')
                ->get();
        } else {
            $latestSale = EcommerceSale::with(['product.productLine', 'product.pricingModel'])
                ->where('ecommerce_customer_id', $customer->id)
                ->whereNotIn('order_status', EcommerceSale::INACTIVE_ORDER_STATUSES)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            $customerSales = $latestSale ? collect([$latestSale]) : collect();
        }

        $firstSale = $customerSales->first();
        $isQatar = $firstSale
            ? in_array((string) $firstSale->channel, EcommerceSale::QATAR_CHANNELS, true)
            : str_starts_with((string) $customer->phone, '974');
        $region = $isQatar ? 'QA' : 'PK';
        $currency = $isQatar ? 'QAR' : 'PKR';
        $orderGroupId = $firstSale?->order_group_id;

        $primaryProduct = $firstSale?->product
            ?? EcommerceProduct::with('pricingModel')->orderBy('id')->first();

        if (!$primaryProduct) {
            return null;
        }

        $basePrice = $primaryProduct->resolveBasePrice($region);
        $refillDiscountPct = 15.0;
        $refillPrice = max(1.0, round($basePrice * (1 - ($refillDiscountPct / 100)), 2));
        $discountCode = $this->discountCodeForPlaybook('day30_refill', $settings);
        $checkoutUrl = $primaryProduct->getShopifyCheckoutUrl($discountCode, $settings->shopify_store_domain);

        $personaName = $settings->agent_persona_name ?: 'Zara';
        $customerName = $customer->name ?: 'there';
        $fmtRefillPrice = number_format($refillPrice, 0);
        $fmtBasePrice = number_format($basePrice, 0);
        $costPerWash = $isQatar ? 'QAR 1.5' : 'PKR 40';

        $body = "Welcome back {$customerName}! This is {$personaName} from Laleen Rituals ✨ To protect your 30-day root momentum right before the last drops of your *{$primaryProduct->name}* run out, I've unlocked your *15% OFF VIP Refill* (*{$currency} {$fmtRefillPrice}* instead of {$currency} {$fmtBasePrice} — under {$costPerWash}/wash with code *{$discountCode}*).\n\n"
            . "Claim your fresh bottle here:\n{$checkoutUrl}";

        $thread = $this->resolveUnifiedCustomerThread($customer->id, $region, [
            'order_group_id' => $orderGroupId,
            'playbook' => 'day30_refill',
            'state' => 'day30_refill_pitched',
            'offered_product_id' => $primaryProduct->id,
            'offered_bundle_id' => null,
            'offered_addon_price' => $refillPrice,
            'last_message_at' => now(),
        ]);

        $deliveryStatus = $settings->isAutopilot() ? 'sent' : 'pending_approval';

        $msg = EcommerceWhatsappMessage::create([
            'thread_id' => $thread->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'delivery_status' => $deliveryStatus,
            'message_body' => $body,
            'ai_metadata' => [
                'step' => 'day30_vip_refill',
                'playbook' => 'day30_refill',
                'offered_product_name' => $primaryProduct->name,
                'offered_addon_price' => $refillPrice,
                'discount_code' => $discountCode,
                'checkout_url' => $checkoutUrl,
            ],
        ]);

        if ($settings->isAutopilot()) {
            $this->transport->sendOutboundMessage($msg, $settings);
        }

        $customer->update([
            'last_retention_contacted_at' => now(),
            'last_retention_stage' => 'day30_refill',
        ]);

        return $thread->fresh(['customer', 'offeredProduct', 'offeredBundle', 'messages']);
    }

    /**
     * When a customer completes an order on Shopify (or manually), check if they have
     * a thread in 'checkout_link_sent' state from the last 7 days — if so, mark that
     * thread 'upsell_accepted' and credit upsell_revenue_generated!
     */
    public function attributeRetentionThreadConversion(
        ?EcommerceCustomer $customer,
        string $orderGroupId,
        float $orderRevenue
    ): ?EcommerceWhatsappThread {
        if (!$customer) {
            return null;
        }

        $thread = EcommerceWhatsappThread::where('ecommerce_customer_id', $customer->id)
            ->whereIn('state', ['checkout_link_sent', 'day30_refill_pitched', 'day7_cross_sell_pitched', 'inbound_active'])
            ->where('updated_at', '>=', now()->subDays(7))
            ->orderByDesc('updated_at')
            ->first();

        if (!$thread) {
            return null;
        }

        $creditedAmount = $orderRevenue > 0
            ? round($orderRevenue, 2)
            : round((float) ($thread->offered_addon_price ?: 0), 2);

        $thread->state = 'upsell_accepted';
        $thread->upsell_revenue_generated = round((float) $thread->upsell_revenue_generated + $creditedAmount, 2);
        $thread->order_group_id = $orderGroupId;
        $thread->unread_count = 0;
        $thread->save();

        // Mark the newly created sale row(s) as AI-attributed upsells
        EcommerceSale::where('order_group_id', $orderGroupId)
            ->update(['is_ai_upsell' => true]);

        $currency = $thread->currency();
        $fmtRevenue = number_format($creditedAmount, 0);
        $shortRef = EcommerceWhatsappThread::formatShortOrderRef($orderGroupId);

        EcommerceWhatsappMessage::create([
            'thread_id' => $thread->id,
            'direction' => 'outbound',
            'sender_type' => 'system_event',
            'delivery_status' => 'sent',
            'message_body' => "🎉 Customer completed Shopify checkout ({$shortRef}) · +{$currency} {$fmtRevenue} attributed to AI",
        ]);

        return $thread;
    }

    /**
     * Playbook 4: 15-Minute Abandoned Checkout WhatsApp Rescue Engine.
     *
     * Reuses the customer's unified EcommerceWhatsappThread with playbook = 'abandoned_checkout'
     * and state = 'abandoned_followup_sent', sends a warm Ezra Firestone + Alex Hormozi + Andy Elliott
     * opening check-in with their exact cart items and clickable abandoned_checkout_url, and marks
     * the checkout status = 'outreach_sent'.
     */
    public function initiateAbandonedCheckoutRescue(
        EcommerceAbandonedCheckout $checkout,
        ?EcommerceAiSetting $settings = null
    ): ?EcommerceWhatsappThread {
        $settings = $settings ?? EcommerceAiSetting::current();

        $regionUpper = strtoupper(EcommerceSale::normalizeRegion($checkout->region ?: 'PK'));
        $isQatar = $regionUpper === 'QA';
        $currency = $checkout->currency ?: ($isQatar ? 'QAR' : 'PKR');
        $defaultCode = $isQatar ? '974' : '92';

        $customer = $checkout->customer;
        if (!$customer) {
            $normalizedPhone = EcommerceCustomer::normalizePhone((string) $checkout->phone, $defaultCode);
            if ($normalizedPhone === '') {
                return null;
            }

            $customer = EcommerceCustomer::firstOrCreate(
                ['phone' => $normalizedPhone],
                [
                    'name' => $checkout->customer_name ?: 'Valued Guest',
                    'email' => $checkout->email,
                    'total_spend' => 0,
                    'total_orders' => 0,
                ]
            );

            $checkout->ecommerce_customer_id = $customer->id;
            $checkout->phone = $normalizedPhone;
            $checkout->save();
        }

        // Check if customer opted out
        $existingThread = $this->deduplicateCustomerThreads($customer->id, $regionUpper);
        if ($existingThread && $existingThread->state === 'opted_out') {
            return null;
        }

        // Resolve primary product from line_items_json
        $lineItems = is_array($checkout->line_items_json) ? $checkout->line_items_json : [];
        $primaryProduct = null;
        foreach ($lineItems as $item) {
            if (!empty($item['ecommerce_product_id'])) {
                $primaryProduct = EcommerceProduct::with('pricingModel')->find((int) $item['ecommerce_product_id']);
                if ($primaryProduct) {
                    break;
                }
            }
            $title = trim((string) ($item['title'] ?? $item['name'] ?? ''));
            if ($title !== '') {
                $primaryProduct = EcommerceProduct::with('pricingModel')
                    ->whereRaw('LOWER(name) = ?', [strtolower($title)])
                    ->orWhere('name', 'like', '%' . $title . '%')
                    ->first();
                if ($primaryProduct) {
                    break;
                }
            }
        }

        if (!$primaryProduct) {
            $primaryProduct = EcommerceProduct::with('pricingModel')->orderBy('id')->first();
        }

        $subtotal = (float) $checkout->subtotal_price;
        if ($subtotal <= 0 && $primaryProduct) {
            $subtotal = $primaryProduct->resolveBasePrice($regionUpper);
        }
        $vipDiscountedPrice = max(1.0, round($subtotal * 0.85, 2));

        $abandonedUrl = trim((string) $checkout->abandoned_checkout_url);
        if ($abandonedUrl === '') {
            $abandonedUrl = $primaryProduct
                ? $primaryProduct->getShopifyCheckoutUrl(null, $settings->shopify_store_domain)
                : ('https://' . ($settings->shopify_store_domain ?: 'laleenrituals.com'));
        }

        $customerName = trim((string) ($checkout->customer_name ?: $customer->name ?: 'there'));
        $personaName = trim((string) ($settings->agent_persona_name ?: 'Zara'));
        $itemsSummary = $checkout->itemsSummaryText();
        $ordersCount = (int) ($customer->orders_count ?? 0);

        $greeting = $ordersCount >= 2
            ? "Welcome back to the Laleen family, {$customerName}! This is {$personaName} from Laleen Rituals ✨"
            : "Assalam-o-Alaikum {$customerName}! This is {$personaName} from Laleen Rituals ✨";

        $body = "{$greeting} I noticed you left {$itemsSummary} waiting in your cart—most women in our Laleen community tell us our pure cold-pressed Hunza botanicals feel noticeably lighter and calmer from the very first wash.\n\n"
            . "Did you have a quick question about whether it suits your scalp/hair type, or would you like me to reserve your bottle with priority dispatch right here?\n{$abandonedUrl}";

        $orderRefTag = 'CART-' . ($checkout->shopify_checkout_token ?: $checkout->shopify_checkout_id);

        $existingOrderRef = ($existingThread && !EcommerceWhatsappThread::isColdChatOrderRef($existingThread->order_group_id))
            ? $existingThread->order_group_id
            : $orderRefTag;

        $thread = $this->resolveUnifiedCustomerThread($customer->id, $regionUpper, [
            'order_group_id' => $existingOrderRef,
            'playbook' => 'abandoned_checkout',
            'state' => 'abandoned_followup_sent',
            'offered_product_id' => $primaryProduct?->id,
            'offered_bundle_id' => null,
            'offered_addon_price' => $vipDiscountedPrice,
            'last_message_at' => now(),
        ]);

        $deliveryStatus = $settings->isAutopilot() ? 'sent' : 'pending_approval';

        $msg = EcommerceWhatsappMessage::create([
            'thread_id' => $thread->id,
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'delivery_status' => $deliveryStatus,
            'message_body' => $body,
            'ai_metadata' => [
                'step' => 'abandoned_checkout_15m_rescue',
                'playbook' => 'abandoned_checkout',
                'abandoned_checkout_id' => $checkout->id,
                'shopify_checkout_id' => $checkout->shopify_checkout_id,
                'abandoned_checkout_url' => $abandonedUrl,
                'subtotal_price' => $subtotal,
            ],
        ]);

        if ($settings->isAutopilot()) {
            $this->transport->sendOutboundMessage($msg, $settings);
        }

        $checkout->update([
            'status' => 'outreach_sent',
            'outreach_sent_at' => now(),
        ]);

        return $thread->fresh(['customer', 'offeredProduct', 'offeredBundle', 'messages']);
    }

    /**
     * Automatic Conversion & Recovery Attribution for Playbook 4 (`abandoned_checkout`).
     *
     * Checks if the customer has an open EcommerceAbandonedCheckout (`status` in `['abandoned', 'outreach_sent']`)
     * within the last 7 days matching `shopify_checkout_token`, `ecommerce_customer_id`, or `phone`.
     * Marks it recovered and credits any active `'abandoned_checkout'` WhatsApp thread.
     */
    public function attributeAbandonedCheckoutRecovery(
        ?EcommerceCustomer $customer,
        string $orderGroupId,
        float $orderTotal,
        string $currency = 'PKR',
        ?string $checkoutToken = null,
        ?string $rawPhone = null
    ): ?EcommerceAbandonedCheckout {
        $normalizedPk = $rawPhone ? EcommerceCustomer::normalizePhone($rawPhone, '92') : '';
        $normalizedQa = $rawPhone ? EcommerceCustomer::normalizePhone($rawPhone, '974') : '';
        $customerPhone = $customer?->phone ?: '';
        $phones = array_values(array_unique(array_filter([$customerPhone, $normalizedPk, $normalizedQa, $rawPhone])));

        $checkout = EcommerceAbandonedCheckout::query()
            ->whereIn('status', ['abandoned', 'outreach_sent'])
            ->where(function ($q) {
                $q->whereNull('abandoned_at')
                    ->orWhere('abandoned_at', '>=', now()->subDays(7));
            })
            ->where(function ($q) use ($checkoutToken, $customer, $phones) {
                $hasCondition = false;
                if ($checkoutToken !== null && trim($checkoutToken) !== '') {
                    $q->orWhere('shopify_checkout_token', trim($checkoutToken))
                        ->orWhere('shopify_checkout_id', trim($checkoutToken));
                    $hasCondition = true;
                }
                if ($customer) {
                    $q->orWhere('ecommerce_customer_id', $customer->id);
                    $hasCondition = true;
                }
                if (!empty($phones)) {
                    $q->orWhereIn('phone', $phones);
                    $hasCondition = true;
                }
                if (!$hasCondition) {
                    $q->whereRaw('1 = 0');
                }
            })
            ->orderByDesc('id')
            ->first();

        if (!$checkout) {
            return null;
        }

        $creditedAmount = $orderTotal > 0 ? round($orderTotal, 2) : round((float) $checkout->subtotal_price, 2);

        $checkout->update([
            'status' => 'recovered',
            'recovered_at' => now(),
            'recovered_order_group_id' => $orderGroupId,
            'ecommerce_customer_id' => $checkout->ecommerce_customer_id ?: $customer?->id,
        ]);

        $targetCustomerId = $customer?->id ?: $checkout->ecommerce_customer_id;
        if ($targetCustomerId) {
            $thread = EcommerceWhatsappThread::where('ecommerce_customer_id', $targetCustomerId)
                ->where('playbook', 'abandoned_checkout')
                ->orderByDesc('updated_at')
                ->first();

            if ($thread) {
                $thread->upsell_revenue_generated = round((float) $thread->upsell_revenue_generated + $creditedAmount, 2);
                $thread->state = 'upsell_accepted';
                $thread->order_group_id = $orderGroupId;
                $thread->unread_count = 0;
                $thread->save();

                EcommerceSale::where('order_group_id', $orderGroupId)
                    ->update(['is_ai_upsell' => true]);

                $fmtTotal = number_format($creditedAmount, 0);
                EcommerceWhatsappMessage::create([
                    'thread_id' => $thread->id,
                    'direction' => 'outbound',
                    'sender_type' => 'system_event',
                    'delivery_status' => 'sent',
                    'message_body' => "🎉 Abandoned Checkout Recovered! Order #{$orderGroupId} ({$currency} {$fmtTotal})",
                ]);
            }
        }

        return $checkout;
    }

    /**
     * Build the live regional catalog of in-stock EcommerceProduct items and active EcommerceBundle offers
     * for Inbound Cold Buyer Consultation (`inbound_support`) and general concierge recommendations.
     *
     * @return array{
     *   catalog_text: string,
     *   recommended_product: ?EcommerceProduct,
     *   recommended_bundle: ?EcommerceBundle,
     *   recommended_price: float,
     *   recommended_url: string,
     *   concern_diagnosis: string
     * }
     */
    public function buildRegionalCatalogContext(
        string $region,
        EcommerceAiSetting $settings,
        string $customerText = ''
    ): array {
        $regionUpper = strtoupper(EcommerceSale::normalizeRegion($region));
        $currency = $regionUpper === 'QA' ? 'QAR' : 'PKR';
        $stockField = $regionUpper === 'QA' ? 'stock_qatar' : 'stock_pakistan';
        $costPerWashLabel = $regionUpper === 'QA' ? 'QAR 1.5' : 'PKR 40';

        $inStockProducts = EcommerceProduct::with(['productLine', 'pricingModel'])
            ->orderBy('id')
            ->get()
            ->filter(fn (EcommerceProduct $p) => (float) $p->{$stockField} >= 1)
            ->values();

        if ($inStockProducts->isEmpty()) {
            $inStockProducts = EcommerceProduct::with(['productLine', 'pricingModel'])->orderBy('id')->get();
        }

        $bundles = EcommerceBundle::with('items.product')->orderBy('id')->get();

        $catalogLines = [];
        foreach ($inStockProducts as $prod) {
            $price = $prod->resolveBasePrice($regionUpper);
            $fmtPrice = number_format($price, 0);
            $url = $prod->getShopifyCheckoutUrl(null, $settings->shopify_store_domain);
            $stock = (int) $prod->{$stockField};
            $sizeInfo = $prod->unit_size ?: ($prod->volume_ml ? ((int) $prod->volume_ml . 'ml') : '45–60 washes');
            $catalogLines[] = "- SKU: *{$prod->name}* ({$sizeInfo}, lasts 45–60 washes / < {$costPerWashLabel} per wash) | Price: {$currency} {$fmtPrice} | Stock: {$stock} | Checkout URL: {$url}";
        }

        foreach ($bundles as $bundle) {
            $bundlePrice = (float) $bundle->bundle_price;
            if ($bundlePrice > 0) {
                $fmtBundlePrice = number_format($bundlePrice, 0);
                $itemsDesc = $bundle->items
                    ->map(fn ($item) => $item->quantity . 'x ' . ($item->product?->name ?? 'Item'))
                    ->implode(' + ');
                $catalogLines[] = "- BUNDLE: *{$bundle->name}* ({$itemsDesc}) | Bundle Price: {$currency} {$fmtBundlePrice}";
            }
        }

        $lower = strtolower($customerText);
        $recommendedProduct = null;
        $concernDiagnosis = "Dealing with stubborn hair stress can feel so frustrating—most women in our Laleen community see calmer, healthier roots within 2–4 weeks of switching to pure botanicals.";

        if (preg_match('/\b(keratin|color|colour|treated|protein|sulfate|shampoo|buildup|oily scalp|greasy scalp|wash)\b/i', $lower)) {
            $recommendedProduct = $inStockProducts->first(
                fn (EcommerceProduct $p) => str_contains(strtolower($p->name), 'shampoo')
            );
            $concernDiagnosis = "Hard-water buildup and harsh commercial sulfates can be so stubborn on your scalp and salon treatments!";
        } elseif (preg_match('/\b(frizz|frizzy|split ends|heat|humidity|serum|mask|rough|damaged)\b/i', $lower)) {
            $recommendedProduct = $inStockProducts->first(
                fn (EcommerceProduct $p) => str_contains(strtolower($p->name), 'serum') || str_contains(strtolower($p->name), 'mask')
            );
            $concernDiagnosis = "Managing daily frizz and humidity dryness can be so exhausting on your strands!";
        } elseif (preg_match('/\b(dandruff|flaky|flakes|itchy|dry scalp)\b/i', $lower)) {
            $recommendedProduct = $inStockProducts->first(
                fn (EcommerceProduct $p) => str_contains(strtolower($p->name), 'oil')
            );
            $concernDiagnosis = "Dealing with a dry, flaky scalp is so frustrating, especially when harsh washes strip your natural barrier.";
        } else {
            // Default to Hair Oil for hairfall/thinning/shedding/growth or general inquiry
            $recommendedProduct = $inStockProducts->first(
                fn (EcommerceProduct $p) => str_contains(strtolower($p->name), 'oil')
            );
            if (preg_match('/\b(hairfall|hair fall|shedding|thinning|bald|baal|girna|growth)\b/i', $lower)) {
                $concernDiagnosis = "Seeing sudden shedding in the shower is so stressful—most women in our Laleen community dealing with hard-water hairfall start right here.";
            }
        }

        if (!$recommendedProduct) {
            $recommendedProduct = $inStockProducts->first();
        }

        $recommendedPrice = $recommendedProduct
            ? $recommendedProduct->resolveBasePrice($regionUpper)
            : ($regionUpper === 'QA' ? 65.0 : 1200.0);

        $recommendedUrl = $recommendedProduct
            ? $recommendedProduct->getShopifyCheckoutUrl(null, $settings->shopify_store_domain)
            : ('https://' . ($settings->shopify_store_domain ?: 'laleenrituals.com'));

        return [
            'catalog_text' => !empty($catalogLines) ? implode("\n", $catalogLines) : 'All signature Laleen botanicals in stock.',
            'recommended_product' => $recommendedProduct,
            'recommended_bundle' => $bundles->first(),
            'recommended_price' => $recommendedPrice,
            'recommended_url' => $recommendedUrl,
            'concern_diagnosis' => $concernDiagnosis,
        ];
    }

    /**
     * B.2 Inbound Reply Handler: Process customer message with Gemini structured JSON output
     * and execute deterministic database actions.
     */
    public function handleInboundCustomerMessage(
        EcommerceWhatsappThread $thread,
        string $customerText,
        ?string $externalMessageId = null
    ): array {
        $customerText = trim($customerText);
        $settings = EcommerceAiSetting::current();

        if ($thread->playbook === 'inbound_support' && $thread->state === 'idle') {
            $thread->state = 'inbound_active';
        }

        $inboundMsg = EcommerceWhatsappMessage::create([
            'thread_id' => $thread->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'delivery_status' => 'delivered',
            'message_body' => $customerText,
            'external_message_id' => $externalMessageId ?: ('in-' . uniqid()),
        ]);

        $thread->unread_count = (int) $thread->unread_count + 1;
        $thread->last_message_at = now();
        $thread->save();

        // Allow STOP / UNSUBSCRIBE even if thread was in human_handoff
        if ($thread->state === 'human_handoff' && !preg_match('/^(stop|unsubscribe|opt\s*out)$/i', $customerText)) {
            return [
                'inbound_message' => $inboundMsg,
                'ai_decision' => null,
                'outbound_message' => null,
                'system_event' => null,
                'thread' => $thread->fresh(['customer', 'offeredProduct', 'messages']),
            ];
        }

        $thread->loadMissing(['customer', 'offeredProduct.pricingModel', 'offeredBundle', 'messages']);

        $orderSales = ($thread->order_group_id && !EcommerceWhatsappThread::isColdChatOrderRef($thread->order_group_id))
            ? EcommerceSale::with(['product.productLine', 'product.pricingModel'])
                ->where('order_group_id', $thread->order_group_id)
                ->get()
            : collect();

        // Refresh offer if not yet populated OR if offered_addon_price is <= 0
        if (!$thread->offered_product_id || (float) $thread->offered_addon_price <= 0) {
            $overridePct = in_array($thread->playbook, ['day30_refill', 'abandoned_checkout'], true)
                ? 15.0
                : ($thread->playbook === 'inbound_support' ? 0.0 : null);
            $offer = $this->resolveComplementaryOffer($orderSales, $thread->region, $settings, $overridePct);
            if ($offer['offered_product']) {
                $thread->offered_product_id = $offer['offered_product']->id;
                $thread->offered_bundle_id = $offer['offered_bundle']?->id;
                $thread->offered_addon_price = $thread->playbook === 'inbound_support'
                    ? $offer['base_price']
                    : $offer['offered_addon_price'];
                $thread->save();
                $thread->load('offeredProduct.pricingModel');
            }
        }

        // Call Gemini Brain (with Master Conversational Mind, deterministic fallback & terminal-state guardrails)
        $aiDecision = $this->evaluateCustomerMessageWithGemini($thread, $orderSales, $customerText, $settings);

        // Execute Deterministic Database Actions based on structured output
        $actionResult = $this->executeDeterministicAction($thread, $orderSales, $aiDecision, $settings, $customerText);

        // Save the AI reply message (if any), formatted cleanly with single *bold* for WhatsApp
        $outboundMessage = null;
        $replyText = trim((string) ($actionResult['override_reply'] ?? $aiDecision['reply_message'] ?? ''));

        if ($replyText !== '') {
            $replyText = EcommerceWhatsappMessage::toWhatsappNativeFormatting($replyText);
            $deliveryStatus = $settings->isAutopilot() ? 'sent' : 'pending_approval';
            $outboundMessage = EcommerceWhatsappMessage::create([
                'thread_id' => $thread->id,
                'direction' => 'outbound',
                'sender_type' => 'ai',
                'delivery_status' => $deliveryStatus,
                'message_body' => $replyText,
                'ai_metadata' => [
                    'detected_intent' => $aiDecision['detected_intent'] ?? 'ask_question',
                    'sentiment' => $aiDecision['sentiment'] ?? 'neutral',
                    'should_trigger_action' => $aiDecision['should_trigger_action'] ?? 'none',
                    'requested_quantity' => $aiDecision['requested_quantity'] ?? 1,
                    'source' => $aiDecision['source'] ?? 'gemini',
                    'model' => $aiDecision['model'] ?? null,
                    'tokens' => $aiDecision['tokens'] ?? null,
                ],
            ]);

            if ($settings->isAutopilot()) {
                $this->transport->sendOutboundMessage($outboundMessage, $settings);
            }
        }

        return [
            'inbound_message' => $inboundMsg,
            'ai_decision' => $aiDecision,
            'outbound_message' => $outboundMessage,
            'system_event' => $actionResult['system_event'] ?? null,
            'thread' => $thread->fresh(['customer', 'offeredProduct', 'offeredBundle', 'messages']),
        ];
    }

    /**
     * Call Gemini (`gemini-2.5-flash` or configured model) with `response_mime_type: "application/json"`
     * using the Master Conversational Mind (Ezra Firestone + Alex Hormozi + Andy Elliott),
     * the 5 Cognitive & Emotional Intelligence rules, and the 4 Cultural Closer instincts.
     */
    protected function evaluateCustomerMessageWithGemini(
        EcommerceWhatsappThread $thread,
        Collection $orderSales,
        string $customerText,
        EcommerceAiSetting $settings
    ): array {
        $currency = $thread->currency();
        $isQatar = strtoupper($thread->region) === 'QA';
        $stockField = $isQatar ? 'stock_qatar' : 'stock_pakistan';
        $offeredProduct = $thread->offeredProduct;
        $offeredStock = $offeredProduct ? (float) $offeredProduct->{$stockField} : 0.0;
        $discountPct = in_array($thread->playbook, ['day30_refill', 'abandoned_checkout'], true)
            ? 15.0
            : max(0.0, min(90.0, (float) ($settings->upsell_discount_percent ?? 20.0)));

        $basePrice = $offeredProduct
            ? $offeredProduct->resolveBasePrice($thread->region)
            : ($isQatar ? 65.0 : 1200.0);

        $addonPrice = (float) ($thread->offered_addon_price ?? 0);
        if ($addonPrice <= 0) {
            $addonPrice = max(1.0, round($basePrice * (1 - ($discountPct / 100)), 2));
        }

        $discountCode = $this->discountCodeForPlaybook($thread->playbook, $settings);
        $checkoutUrl = $offeredProduct
            ? $offeredProduct->getShopifyCheckoutUrl(
                $thread->playbook === 'inbound_support' ? null : $discountCode,
                $settings->shopify_store_domain
            )
            : ('https://' . ($settings->shopify_store_domain ?: 'laleenrituals.com'));

        $currentOrderTotal = round(
            (float) $orderSales->sum('total_price')
            + (float) $orderSales->sum('shipping_cost')
            + (float) $orderSales->sum('tax_amount'),
            2
        );
        $requestedQty = $this->extractRequestedQuantity($customerText);
        $upgradedOrderTotal = round($currentOrderTotal + ($addonPrice * $requestedQty), 2);

        $orderItemsSummary = $orderSales->isNotEmpty()
            ? $orderSales->map(fn (EcommerceSale $s) => $s->quantity . 'x ' . ($s->product?->name ?? 'Product'))->implode(', ')
            : ($thread->customer?->latest_product ?? 'Laleen Hair Care');

        $firstOrderSale = $orderSales->first();
        $liveOrderStatus = $firstOrderSale?->order_status ?? 'none';
        $livePaymentStatus = $firstOrderSale?->payment_status ?? 'none';
        $liveOrderDate = $firstOrderSale?->created_at ? $firstOrderSale->created_at->format('Y-m-d H:i') : 'N/A';

        $heroProduct = $firstOrderSale?->product;
        $heroProductName = $heroProduct?->name ?? 'Hunza Apricot Oil';
        $heroBasePrice = $heroProduct ? $heroProduct->resolveBasePrice($thread->region) : $basePrice;
        $heroDiscountPrice = max(1.0, round($heroBasePrice * (1 - ($discountPct / 100)), 2));

        $catalogContext = $this->buildRegionalCatalogContext($thread->region, $settings, $customerText);

        $deterministic = $this->buildDeterministicPlaybookDecision(
            thread: $thread,
            customerText: $customerText,
            settings: $settings,
            currency: $currency,
            orderItemsSummary: $orderItemsSummary,
            currentOrderTotal: $currentOrderTotal,
            upgradedOrderTotal: $upgradedOrderTotal,
            offeredProduct: $offeredProduct,
            offeredStock: $offeredStock,
            basePrice: $basePrice,
            addonPrice: $addonPrice,
            discountPct: $discountPct,
            discountCode: $discountCode,
            checkoutUrl: $checkoutUrl,
            liveOrderStatus: $liveOrderStatus,
            livePaymentStatus: $livePaymentStatus,
            liveOrderDate: $liveOrderDate,
            heroProductName: $heroProductName,
            heroDiscountPrice: $heroDiscountPrice,
            catalogContext: $catalogContext
        );

        // Hard Terminal-State & High-Confidence Safety Guardrail:
        // If the thread is ALREADY in ['upsell_accepted', 'upsell_declined', 'completed', 'opted_out'],
        // return the deterministic terminal response immediately so the AI NEVER re-pitches the upsell!
        // Also return deterministic immediately if a safety escalation, opt-out, revert, duplicate cancellation,
        // or 1-Pivot Downsell guardrail triggered.
        if (
            in_array($thread->state, ['upsell_accepted', 'upsell_declined', 'completed', 'opted_out'], true)
            || !empty($deterministic['force_deterministic'])
        ) {
            return $deterministic;
        }

        $apiKey = trim((string) (config('services.gemini.api_key') ?: config('services.gemini.key')));
        if ($apiKey === '') {
            return $deterministic;
        }

        $configuredModel = (string) config('services.gemini.command_model', config('services.gemini.model', 'gemini-2.5-flash'));
        $modelsToTry = array_values(array_unique([
            $configuredModel,
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
        ]));

        // Read the last 6 messages in thread history carefully to avoid repetitive phrasing
        $historyLines = $thread->messages
            ->take(-6)
            ->map(fn (EcommerceWhatsappMessage $m) => strtoupper($m->sender_type) . ': ' . $m->message_body)
            ->implode("\n");

        $lastOutboundBody = (string) ($thread->messages
            ->where('direction', 'outbound')
            ->where('sender_type', 'ai')
            ->last()?->message_body ?? 'None');

        $customer = $thread->customer;
        $customerName = $customer?->name ?: 'Valued Customer';
        $customerOrdersCount = (int) ($customer?->orders_count ?? 0);
        $customerTotalSpend = number_format((float) ($customer?->total_spend ?? 0), 0);
        $lifecycleSegment = $customerOrdersCount >= 2
            ? "Repeat VIP (orders_count: {$customerOrdersCount}, total_spend: {$currency} {$customerTotalSpend}) — Greet as a returning VIP member of the Laleen family ('Welcome back!') and skip basic beginner intros."
            : "First-Timer (orders_count: {$customerOrdersCount}, total_spend: {$currency} {$customerTotalSpend}) — Focus on trust, reassurance, and how gentle/effective the first wash will feel.";

        $personaName = $settings->agent_persona_name ?: 'Zara';
        $offeredProductName = $offeredProduct?->name ?? 'Complementary Hair Ritual';
        $shortRef = $thread->shortOrderRef();
        $costPerWashMetric = $isQatar ? 'QAR 1.5' : 'PKR 40';
        $knowledgeBase = trim((string) ($settings->brand_knowledge_base ?: EcommerceAiSetting::DEFAULT_KNOWLEDGE_BASE));

        $systemPrompt = <<<PROMPT
You are {$personaName}, the Master Conversational Sales & Hair Concierge for Laleen Rituals ({$thread->region} region, currency: {$currency}), trained in the combined frameworks of Ezra Firestone, Alex Hormozi, and Andy Elliott.

LALEEN RITUALS BOTANICAL KNOWLEDGE BASE & EXPANDED FAQ:
{$knowledgeBase}

LIVE CUSTOMER, ORDER & CATALOG CONTEXT (100% DETERMINISTIC — NEVER INVENT PRODUCTS, PRICES, LINKS, OR STOCK):
- Customer Name: {$customerName}
- Customer Lifecycle Memory: {$lifecycleSegment}
- Customer Orders Count: {$customerOrdersCount}
- Customer Total Spend: {$currency} {$customerTotalSpend}
- Active Playbook: {$thread->playbook}
- Current Thread State: {$thread->state}
- Order Reference: {$shortRef}
- Live Order Status in DB: {$liveOrderStatus} (Payment Status: {$livePaymentStatus}, Placed At: {$liveOrderDate})
- Ordered Items: {$orderItemsSummary}
- Current Order Total: {$currency} {$currentOrderTotal}
- Hero Ordered Product (for 2nd-Bottle 1-Pivot Downsell): {$heroProductName} (20% OFF 2nd Bottle Price: {$currency} {$heroDiscountPrice})
- Complementary Offered Product: {$offeredProductName}
- Offered Product Regular Price: {$currency} {$basePrice}
- Discounted Offer Price ({$discountPct}% OFF): {$currency} {$addonPrice} (Cost-Per-Wash: < {$costPerWashMetric} across 45–60 washes)
- Exact New Order Total if Upgraded (Playbook 1, {$requestedQty}x unit): {$currency} {$upgradedOrderTotal}
- Direct Shopify Discount Checkout Link (Playbooks 2, 3 & Inbound Support): {$checkoutUrl} (Code: {$discountCode})
- Warehouse Stock Available for Offered Product: {$offeredStock} units

IN-STOCK REGIONAL CATALOG & ACTIVE BUNDLES ({$thread->region}):
{$catalogContext['catalog_text']}

PREVIOUS OUTBOUND AI MESSAGE (NEVER REPEAT ITS EXACT GREETING, COST-PER-WASH PHRASING, OR CLOSING CTA):
"{$lastOutboundBody}"

=== PART 1: THE TRIPLE-THREAT SALES ARCHITECTURE ===
1. LAYER 1 — EZRA FIRESTONE (Opening Warmth, Community Proof & Reason-Why):
   - BAN generic robotic sales clichés ("take your hair care to the next level", "I have a special offer just for you").
   - Always lead with genuine warmth, 1-sentence community social proof ("Most women in our Laleen community dealing with hard-water hairfall..."), and a transparent, authentic "Reason-Why" for every discount:
     * Playbook 1 (`cod_upsell` — Open Box): Their box is still open on the packing table and the courier is already booked for their address, so we pass the saved shipping & bundle margin ({$discountPct}% off) straight to them with zero extra delivery fee.
     * Playbook 2 (`day7_cross_sell` — Day 7): Rewarding their Week-1 consistency so they get the biological synergy of root nourishment (Oil) + sulfate-free cleansing/cuticle sealing (Shampoo/Recovery Kit).
     * Playbook 3 (`day30_refill` — Day 30): Protecting their 30-day root momentum right before the last drops of their bottle run out.

2. LAYER 2 — ALEX HORMOZI ($100M Value Equation & Cost-Per-Use Math):
   - Whenever quoting a price or handling a price hesitation, break the price down into Cost-Per-Wash / Cost-Per-Week ("A concentrated bottle lasts 45–60 washes—less than {$costPerWashMetric} per wash to protect your roots from harsh sulfates").
   - Anchor the Biological Mechanism: explain that applying pure cold-pressed Hunza Apricot Oil onto a scalp washed with harsh commercial sulfates blocks absorption—pairing them protects the investment they already made.

3. LAYER 3 — ANDY ELLIOTT (Unshakeable Conviction, 1-Pivot Downsell & The A/B Close):
   - Never end a pitch or objection rebuttal with a dead-end statement. Always finish with a warm, high-conviction A/B Choice Question that guides the buyer to action.
   - THE 1-PIVOT DOWNSELL RULE: If a customer objects to the complementary upgrade ("I'll try the oil first", "Filhal sirf oil bhej dein", or "Too expensive") for the FIRST time, validate them warmly, deliver the 1-sentence Hormozi cost-per-wash + biological mechanism rebuttal, and offer an A/B Pivot: either lock in the {$discountPct}% off *{$offeredProductName}* (+{$currency} {$addonPrice}) OR add a *2nd bottle of {$heroProductName} at {$discountPct}% off* (+{$currency} {$heroDiscountPrice}) for family or next month's supply so they don't run out mid-ritual (`should_trigger_action`: "none"). If they decline a SECOND time, immediately respect their decision and trigger `lock_order_without_upsell` with zero pressure.

=== PART 2: THE 5 COGNITIVE & EMOTIONAL INTELLIGENCE RULES ===
1. CUSTOMER LIFECYCLE MEMORY (`First-Timer` vs. `Repeat VIP`):
   - If `orders_count <= 1`: Focus on trust, reassurance, and how gentle/effective the first wash will feel.
   - If `orders_count >= 2`: Explicitly greet and acknowledge them as a returning VIP member of the Laleen family ("Welcome back!") and skip basic beginner intros.
2. STRICT WHATSAPP MOBILE READABILITY & ENERGY MIRRORING (MAX 75–85 WORDS):
   - Keep every WhatsApp reply concise, scannable, and mobile-friendly—strictly UNDER 75–85 WORDS broken into 2 short paragraphs maximum. Never send walls of text.
   - Mirror the customer's energy, brevity, and language: English, Roman Urdu ("Jee bilkul!", "Inshallah"), or native Urdu/Arabic script ("جی", "ہاں", "کنفرم", "نعم", "تأكيد").
3. EMOTIONAL VALIDATION BEFORE BOTANICAL MECHANISM:
   - When a customer mentions hairfall, thinning, dandruff, or frizz, validate the emotional stress in 5–8 words first ("Dealing with sudden shedding in the shower is so frustrating..." or "Hard water buildup can be so stubborn on your roots...") before explaining the botanical solution.
4. ZERO REPETITIVE PHRASING ACROSS MULTI-TURN CHATS:
   - Read the last 6 messages carefully. NEVER repeat the exact same opening greeting, cost-per-wash line, or closing call-to-action phrasing used in the previous outbound message.
5. BOTTLE SIZE & CONCENTRATION CLARITY:
   - Immediately answer bottle size and longevity questions ("Kitne ml hai", "How long does 1 bottle last?") with exact usage longevity: 45–60 washes (~1.5 to 2 months of regular 2–3x weekly rituals).

=== PART 3: THE 4 CULTURAL & MASTER CLOSER INSTINCTS (PAKISTAN & QATAR) ===
1. FAMILY / HUSBAND / SISTER CONSULTATION:
   - If a customer says "Let me ask my sister/mom/husband", frame the shared household ritual benefit (e.g., sharing the Sulfate-Free Shampoo in the shower while keeping individual oils) and offer to hold their open box for a few hours so their {$discountPct}% discount stays reserved.
2. SALARY-DATE / FUTURE DISPATCH HOLD:
   - If a customer asks to deliver next week or after the 1st of the month (payday/salary), confirm the order now and automatically save the requested dispatch/delivery date into `extracted_delivery_note`.
3. LOGISTICAL BUYING SIGNALS:
   - When a customer asks about bottle leakage, courier calls before delivery, or paying cash at the door, recognize it as a high-intent buying signal—reassure them in 1 crisp sentence (triple-sealed leak-proof packaging, rider always calls ahead, Cash on Delivery) and close immediately.
4. PAST BAD EXPERIENCE CONTRAST:
   - If they mention other hair oils failed or felt heavy/greasy, contrast cheap mineral-oil/synthetic-fragrance dilutions against Laleen's 100% pure cold-pressed Hunza apricot kernel formula that absorbs cleanly into the scalp without grease.

=== PART 4: PLAYBOOK & DETERMINISTIC ACTION RULES ===
- INBOUND COLD BUYER CONSULTATION (`playbook === 'inbound_support'`):
  * Act as an elite Laleen Rituals Hair Concierge: if the customer sends a simple greeting ("Hi", "Hello", "Salam"), welcome them warmly and ask what hair concern they are looking to solve (hairfall/shedding, frizz/dryness, or sulfate-free daily cleansing) while highlighting our hero botanicals.
  * When they share a hair concern or ask a product/price question, diagnose their hair concern in 1–2 warm sentences, recommend the best matching SKU or Bundle from the IN-STOCK REGIONAL CATALOG using Ezra+Hormozi+Elliott with its exact {$currency} price, and share their direct Shopify checkout URL ({$checkoutUrl}).
  * CRITICAL ADDRESS GUARDRAIL FOR COLD VISITORS: If a cold customer in `inbound_support` (with no prior orders and no delivery address provided yet) says they want to buy/try/send a product (e.g. "Filhal sirf oil bhej dein, let me try the oil first", "Send me 1 Apricot Oil", "I want to order the shampoo"), NEVER trigger `convert_abandoned_to_cod_order` without their shipping address! Instead, set `should_trigger_action = "send_checkout_link"` and reply warmly with: (1) their direct clickable Shopify Checkout URL ({$checkoutUrl}) for the exact product they asked for, (2) a mention that they can also grab the Complete Hair Recovery Bundle (`COMPLETE20` for 20% off) at checkout if they want both Oil + Shampoo, and (3) "Click your direct link above to confirm your delivery address—or simply reply here with your *Full Name, Complete Delivery Address & City* and I'll book your Cash-on-Delivery parcel right here in chat!"
  * ONLY trigger `should_trigger_action = "convert_abandoned_to_cod_order"` in `inbound_support` AFTER the cold customer replies with their actual delivery details (House/Flat/Street/Sector/Area/City + Name), and extract that address into `extracted_delivery_note`.
- LIVE WISMO ("Where is my order?" / "Kab milega?" / "Has it shipped?") & PAYMENT METHODS:
  * Give an accurate status update based on `Live Order Status in DB` (`pending` = confirmed and being hand-packed at warehouse for 3–5 day delivery in PK / same-next day in QA; `shipped` = dispatched with courier and in transit; `delivered` = marked delivered).
  * If they ask about EasyPaisa / JazzCash / Bank Transfer / Card, confirm Cash on Delivery is already active on their parcel, and if they prefer digital prepayment/bank transfer, note it or connect a team member with official account details.
- MULTI-QUANTITY UPGRADE (`requested_quantity`):
  * Set `"requested_quantity"` (integer 1 to 5) if the customer explicitly asks for multiple bottles (e.g., "add 2 shampoos" -> 2). Default to 1.
- POST-UPGRADE REVERSAL (`revert_upsell`):
  * If `Current Thread State` is `upsell_accepted` and customer asks to remove the upgrade ("remove the shampoo, only send the oil" / "cancel the upgrade"), return `detected_intent = "revert_upsell"` and `should_trigger_action = "revert_upsell"`.
- DUPLICATE ORDER DEDUPLICATION (`cancel_duplicate_orders`):
  * If the customer says they accidentally placed two orders, return `should_trigger_action = "cancel_duplicate_orders"`.
- OPT-OUT COMPLIANCE (`STOP` / `UNSUBSCRIBE`):
  * If customer replies "STOP" or "UNSUBSCRIBE", return `detected_intent = "opt_out"` and `should_trigger_action = "opt_out"`.
- PLAYBOOKS 2 & 3 (`day7_cross_sell` & `day30_refill`):
  * Never trigger `execute_order_upgrade` on Day 7 or Day 30; always use `send_checkout_link` and include `{$checkoutUrl}`.
- PLAYBOOK 4 (`abandoned_checkout`):
  * If customer asks a product/delivery question: answer warmly with the Master Conversational Mind + include their direct checkout link.
  * If customer hesitates on price or shipping ("Too expensive", "Shipping is high"): offer a VIP 15% savings link (`VIPREFILL15`) and explain the cost-per-wash math (`should_trigger_action = "send_checkout_link"`).
  * If customer says "Confirm my order here on COD" (or asks to book via Cash on Delivery in WhatsApp): trigger `should_trigger_action = "convert_abandoned_to_cod_order"`.
- FORMATTING:
  * Use single asterisks `*text*` for WhatsApp bolding (never `**text**`). Refer to the order as `{$shortRef}`.

Return ONLY valid JSON matching this exact schema:
{
  "detected_intent": "confirm_cod | accept_upsell | decline_upsell | revert_upsell | cancel_duplicate_orders | ask_question | cancel_order | opt_out | human_handoff",
  "sentiment": "positive | neutral | frustrated",
  "reply_message": "Warm, Ezra+Hormozi+Elliott WhatsApp reply strictly under 75-85 words in max 2 short paragraphs...",
  "should_trigger_action": "none | mark_cod_confirmed_and_pitch_upsell | execute_order_upgrade | convert_abandoned_to_cod_order | send_checkout_link | lock_order_without_upsell | revert_upsell | cancel_duplicate_orders | cancel_cod_order | opt_out | escalate_to_human",
  "requested_quantity": 1,
  "extracted_delivery_note": "string or null (updated address, landmark, phone number, courier timing, or future salary-date dispatch hold)"
}
PROMPT;

        $userPrompt = "CONVERSATION HISTORY (LAST 6 MESSAGES):\n{$historyLines}\n\nLATEST CUSTOMER REPLY:\n\"{$customerText}\"\n\nRespond with strict JSON.";

        foreach ($modelsToTry as $model) {
            try {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $payload = [
                    'systemInstruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [['text' => $userPrompt]],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.25,
                        'maxOutputTokens' => 1024,
                        'responseMimeType' => 'application/json',
                    ],
                ];

                if (str_contains($model, '2.5') && !str_contains($model, 'lite')) {
                    $payload['generationConfig']['thinkingConfig'] = ['thinkingBudget' => 0];
                }

                $response = Http::connectTimeout(5)
                    ->timeout(15)
                    ->withOptions([
                        'curl' => [
                            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                            CURLOPT_TCP_KEEPALIVE => 1,
                        ],
                    ])
                    ->when(app()->environment('local'), fn ($h) => $h->withoutVerifying())
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    $rawText = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
                    $cleanJson = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/is', '', trim($rawText)));
                    $decoded = json_decode($cleanJson, true);

                    if (is_array($decoded) && !empty($decoded['reply_message'])) {
                        $action = (string) ($decoded['should_trigger_action'] ?? 'none');
                        $intent = (string) ($decoded['detected_intent'] ?? 'ask_question');
                        $replyMessage = EcommerceWhatsappMessage::toWhatsappNativeFormatting((string) $decoded['reply_message']);

                        // Enforce deterministic guardrails for Playbooks 2 & 3 (never execute_order_upgrade on Day 7 / Day 30!)
                        if (in_array($thread->playbook, ['day7_cross_sell', 'day30_refill'], true) && $action === 'execute_order_upgrade') {
                            $action = 'send_checkout_link';
                        }

                        if ($deterministic['is_high_confidence_keyword']) {
                            $action = $deterministic['should_trigger_action'];
                            $intent = $deterministic['detected_intent'];
                        }

                        // Ensure exact Direct Shopify Discount Checkout Link is always present when action === 'send_checkout_link'
                        if ($action === 'send_checkout_link' && !str_contains($replyMessage, $checkoutUrl)) {
                            $replyMessage .= "\n\nClaim your *{$discountPct}% OFF* (*{$currency} " . number_format($addonPrice, 0) . "* with code *{$discountCode}*) here:\n{$checkoutUrl}";
                        }

                        $geminiDeliveryNote = isset($decoded['extracted_delivery_note']) && is_string($decoded['extracted_delivery_note'])
                            ? trim($decoded['extracted_delivery_note'])
                            : null;
                        if ($geminiDeliveryNote === '' || strtolower((string) $geminiDeliveryNote) === 'null') {
                            $geminiDeliveryNote = null;
                        }

                        // Cold Visitor Address Guardrail: Never allow Gemini to create a DB order in inbound_support without a shipping address
                        if (
                            $thread->playbook === 'inbound_support'
                            && $action === 'convert_abandoned_to_cod_order'
                            && empty($thread->delivery_notes)
                            && empty($deterministic['extracted_delivery_note'])
                            && $this->extractColdCustomerShippingAddress($customerText, $thread) === null
                        ) {
                            $action = 'send_checkout_link';
                            $geminiDeliveryNote = null;
                        }

                        $resolvedQty = max(
                            1,
                            min(
                                5,
                                (int) ($decoded['requested_quantity'] ?? $deterministic['requested_quantity'] ?? 1)
                            )
                        );
                        if (($deterministic['requested_quantity'] ?? 1) > 1 && $resolvedQty === 1) {
                            $resolvedQty = (int) $deterministic['requested_quantity'];
                        }

                        return [
                            'detected_intent' => $intent,
                            'sentiment' => (string) ($decoded['sentiment'] ?? $deterministic['sentiment']),
                            'reply_message' => $replyMessage,
                            'should_trigger_action' => $action,
                            'requested_quantity' => $resolvedQty,
                            'extracted_delivery_note' => $geminiDeliveryNote ?: ($deterministic['extracted_delivery_note'] ?? null),
                            'source' => 'gemini',
                            'model' => $model,
                            'tokens' => data_get($response->json(), 'usageMetadata.totalTokenCount'),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::info("EcommerceAiSalesAgentService Gemini model {$model} fallback.", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $deterministic;
    }

    /**
     * Extract requested upgrade bottle quantity (1 to 5) when a customer explicitly asks
     * for multiple units (e.g., "add 2 shampoos", "send 3 bottles", "do shampoo add kr dein").
     */
    public function extractRequestedQuantity(string $customerText): int
    {
        $lower = strtolower(trim($customerText));
        if ($lower === '') {
            return 1;
        }

        // Ignore "2nd bottle" (which means adding 1 extra bottle to make 2 total) unless they say "add 2 bottles"
        if (preg_match('/\b(?:add|send|include|book|give|want|bhej|kr\s*dein|kar\s*dein)\s+(?:me\s+)?([2-5])\b/i', $lower, $m)) {
            return max(1, min(5, (int) $m[1]));
        }

        if (preg_match('/\b([2-5])\s*(?:x\b|bottles|bottle|shampoos|shampoo|oils|oil|units|packs|pieces|pcs)\b/i', $lower, $m)) {
            return max(1, min(5, (int) $m[1]));
        }

        $wordMap = [
            'two' => 2,
            'do' => 2,
            'three' => 3,
            'teen' => 3,
            'four' => 4,
            'char' => 4,
            'five' => 5,
            'panch' => 5,
        ];

        foreach ($wordMap as $word => $num) {
            if (preg_match('/\b' . $word . '\s+(?:bottles|bottle|shampoos|shampoo|oils|oil|units|packs)\b/i', $lower)) {
                return $num;
            }
        }

        return 1;
    }

    /**
     * Extract an address update, landmark, phone number, courier timing note,
     * salary-date / future dispatch hold, or WhatsApp Location Pin from a customer's message.
     */
    public function extractDeliveryNoteFromText(string $customerText): ?string
    {
        $clean = trim($customerText);
        if ($clean === '') {
            return null;
        }

        if (preg_match('/\[Location Pin Received\]\s*(.+)$/is', $clean, $locMatch)) {
            return trim($locMatch[1]);
        }

        // Match salary-date / future dispatch hold, timing instructions, landmarks, house/street/flat/gate/sector, or call instructions
        $patterns = [
            '/\b((?:please\s+)?(?:deliver|send|dispatch|ship|hold|keep)\b[^.!?\n]*(?:next\s+week|next\s+month|after\s+(?:the\s+)?\d+(?:st|nd|rd|th)?|on\s+(?:the\s+)?\d+(?:st|nd|rd|th)?|salary|payday|pay\s*day|monday|tuesday|wednesday|thursday|friday|saturday|sunday|kal|parson|agle\s+hafte|1st|5th|10th)[^.!?\n]*)/i',
            '/\b((?:salary|payday|pay\s*day|1st\s+of|first\s+of|next\s+week|agle\s+hafte)[^.!?\n]*(?:deliver|send|dispatch|bhej|baad|after)[^.!?\n]*)/i',
            '/\b((?:please\s+)?(?:deliver|send|come|drop|bring|dispatch)\b[^.!?\n]*(?:after|before|between|around|at|by|evening|morning|afternoon|night|\d+\s*(?:am|pm)|baje)[^.!?\n]*)/i',
            '/\b((?:sham|subah|dopehar|raat)\s*\d*[^.!?\n]*(?:baje|baad|pehle)[^.!?\n]*)/i',
            '/\b((?:please\s+)?(?:call|ring|whatsapp|message)\s+(?:me\s+)?(?:before|on|at|when)\b[^.!?\n]*)/i',
            '/\b((?:leave|drop)\s+(?:it\s+)?(?:at|with)\s+(?:reception|guard|security|gate|concierge|neighbor|neighbour)[^.!?\n]*)/i',
            '/\b((?:house|flat|apt|apartment|villa|plot|street|st|road|lane|sector|phase|block|gate|building|bldg|floor|near|opposite|opp|behind|beside|landmark)\s*[#:\-\dA-Za-z][^.!?\n]{4,120})/i',
            '/\b((?:new|updated|change)\s+(?:address|number|phone|location)\s*[:\-]?\s*[^.!?\n]{5,120})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $clean, $matches)) {
                $note = trim($matches[1], " \t\n\r\0\x0B,.;:-");
                if (strlen($note) >= 5) {
                    return ucfirst($note);
                }
            }
        }

        return null;
    }

    /**
     * Extract a cold customer's shipping address (Full Name, Complete Delivery Address & City)
     * when they reply in `inbound_support` to book a Cash-on-Delivery order in chat.
     */
    public function extractColdCustomerShippingAddress(
        string $customerText,
        ?EcommerceWhatsappThread $thread = null
    ): ?string {
        $clean = trim($customerText);
        if ($clean === '' || strlen($clean) < 10) {
            return null;
        }

        if (preg_match('/\[Location Pin Received\]\s*(.+)$/is', $clean, $locMatch)) {
            return trim($locMatch[1]);
        }

        $lower = strtolower($clean);

        // Ignore questions about delivery times, shipping charges, or product efficacy
        if (
            str_contains($clean, '?')
            || preg_match('/\b(how\s+long|how\s+much|what\s+is|when\s+will|kitne\s+din|kab\s+tak|kab\s+milega|delivery\s+charge|delivery\s+fee|shipping\s+cost|shipping\s+fee|does\s+it|is\s+it)\b/i', $lower)
        ) {
            return null;
        }

        // Check if message contains explicit street/house/building/sector/area/city address markers
        $hasStreetOrBuildingMarker = (bool) preg_match(
            '/\b(house|h\s*#|h\.no|flat|apt|apartment|villa|plot|street|st\s*#|st\b|road|rd\b|lane|sector|phase|block|gate|building|bldg|floor|town|colony|society|gulberg|dha|defence|johar|bahria|clifton|nazimabad|pechs|cantt|model\s+town|wapda\s+town|zone\s*\d+|west\s+bay|lusail|wakrah|rayyan|pearl|f-\d+|g-\d+|i-\d+|e-\d+|address\s*[:\-])\b/i',
            $lower
        );

        $hasCityName = (bool) preg_match(
            '/\b(karachi|lahore|islamabad|rawalpindi|faisalabad|multan|peshawar|quetta|sialkot|gujranwala|hyderabad|abbottabad|bahawalpur|sargodha|sukkur|mardan|gujrat|sahiwal|mirpur|muzaffarabad|doha|al\s+wakrah|al\s+rayyan|lusail|al\s+khor)\b/i',
            $lower
        );

        if ($hasStreetOrBuildingMarker) {
            return preg_replace('/^(?:my\s+address\s+is|delivery\s+address\s*[:\-]?|address\s*[:\-]?)\s*/i', '', $clean) ?: $clean;
        }

        // If Zara's previous message explicitly asked for "Full Name, Complete Delivery Address & City"
        // and the customer replied with a city name + comma/multi-word details, accept it as their shipping address
        $askedForAddressInThread = $thread && $thread->messages
            ->where('direction', 'outbound')
            ->contains(fn (EcommerceWhatsappMessage $m) => str_contains(strtolower((string) $m->message_body), 'complete delivery address'));

        if ($askedForAddressInThread && $hasCityName && str_word_count($clean) >= 3) {
            return $clean;
        }

        return null;
    }

    /**
     * Detect if the customer is writing in Roman Urdu or native Urdu/Arabic script.
     */
    protected function isRomanUrduMessage(string $customerText): bool
    {
        return (bool) preg_match(
            '/\b(haan|han\s+g|jee|ji\s+haan|kr\s*dein|kar\s*dein|kardein|krdo|kardo|bhej\s*dein|bhejdein|kitne\s+din|kitne\s+ml|kab\s+tak|kab\s+milega|aayega|ayega|milega|shampoo\s+add|oil\s+bhej|filhal|sirf|mehenga|sasta|baal|girna|rukega|pooch|poocho|salary)\b/iu',
            $customerText
        );
    }

    /**
     * Detect native Urdu or Arabic script confirmation keywords (`جی`, `ہاں`, `کنفرم`, `نعم`, `تأكيد`).
     */
    protected function hasNativeScriptConfirmation(string $customerText): bool
    {
        return (bool) preg_match(
            '/(جی|ہاں|کنفرم|بھیج|نعم|تأكيد|تاكيد|موافق|تمام|أكيد|اکید|ضرور|ٹھیک)/u',
            $customerText
        );
    }

    /**
     * Resolve a concise 1–2 sentence answer from the Laleen Rituals Botanical Knowledge Base,
     * applying Emotional Validation before Botanical Mechanism, Hormozi Cost-Per-Wash math,
     * and the 4 Cultural Closer Instincts.
     */
    protected function resolveBotanicalKnowledgeAnswer(
        string $customerText,
        string $region,
        string $liveOrderStatus = 'pending',
        string $shortRef = '#ORDER'
    ): ?string {
        $lower = strtolower($customerText);
        $isQatar = strtoupper($region) === 'QA';
        $isRomanUrdu = $this->isRomanUrduMessage($customerText);
        $greetingPrefix = $isRomanUrdu ? 'Jee bilkul! ' : '';
        $costPerWash = $isQatar ? 'QAR 1.5' : 'PKR 40';

        // 1. Cultural Instinct — Family / Husband / Sister Consultation ("Let me ask my sister/mom/husband")
        if (preg_match('/\b(ask my|consult my|check with my|sister|mom|mother|husband|wife|family|ammi|ami|baji|api|bhai|shohar|ghar\s+walon|pooch)\b/i', $lower)) {
            return $greetingPrefix . "Take your time checking with your family! Many households in our Laleen community share the *Sulfate-Free Botanical Shampoo* in the shower while keeping individual oils, and I can hold your open box for a few hours so your *20% OFF* discount and free shipping stay reserved.";
        }

        // 2. Cultural Instinct — Past Bad Experience Contrast ("Other oils didn't work / felt heavy or greasy")
        if (preg_match('/\b(other oil|other oils|tried many|didn\'t work|did not work|failed|waste|sticky|greasy|heavy|chipchipa|mineral oil|pehle\s+bhi)\b/i', $lower)) {
            return $greetingPrefix . "So many commercial hair oils fail because they are diluted with heavy mineral oil and synthetic fragrance that sit on top of the scalp. Laleen is *100% pure cold-pressed Hunza Apricot Kernel Oil*—it absorbs cleanly into the follicle within minutes without any grease!";
        }

        // 3. Cultural Instinct — Logistical Buying Signals (Leakage, Courier Calls Ahead, Paying Cash at Door)
        if (preg_match('/\b(leak|leakage|spill|seal|sealed|packaging|packing|rider call|courier call|call before|cash on delivery|pay cash|at the door)\b/i', $lower)) {
            return $greetingPrefix . "Rest assured, every Laleen bottle is *triple-sealed in leak-proof packaging*, our courier rider *always calls ahead* before arriving, and you pay 100% Cash on Delivery right at your doorstep!";
        }

        // 4. Payment Methods ("EasyPaisa / JazzCash / Bank Transfer / Card")
        if (preg_match('/\b(easypaisa|easy paisa|jazzcash|jazz cash|bank transfer|ibft|sadapay|nayapay|card|online payment|prepay|qpay|fawran)\b/i', $lower)) {
            return $greetingPrefix . "*Cash on Delivery* is already active on your parcel {$shortRef} so you can pay comfortably at your doorstep—or if you prefer *EasyPaisa, JazzCash, or Bank Transfer*, just let us know and our team will share our official account details right away!";
        }

        // 5. Bottle Size & Longevity ("Kitne ml hai", "How long does 1 bottle last?", "Bottle size")
        if (preg_match('/\b(kitne ml|how many ml|bottle size|size of bottle|how big|how long does.*last|kitna chalta|how many washes|quantity in bottle|longevity)\b/i', $lower)) {
            return $greetingPrefix . "Because our botanical formula is highly concentrated, a single bottle lasts *45–60 washes* (~1.5 to 2 months of regular 2–3x weekly rituals)—working out to less than *{$costPerWash} per wash*!";
        }

        // 6. Expanded FAQ — Oily vs. Dry Scalp Application
        if (preg_match('/\b(oily scalp|dry scalp|how to apply|how to use|apply|application|routine|overnight|minutes|drops|kaise lagana|tarika)\b/i', $lower)) {
            return $greetingPrefix . "For *oily scalps*, massage 3–4 drops of *Hunza Apricot Oil* 1–2 hours before washing and cleanse with our *Sulfate-Free Shampoo*. For *dry or damaged scalps*, leave 4–6 drops in overnight 2–3x a week for deep root recovery!";
        }

        // 7. Expanded FAQ — Men, Beard & Kids Safety
        if (preg_match('/\b(men|man|male|beard|kids|children|child|baby|boys|safe for kids|safe for men|bachon)\b/i', $lower)) {
            return $greetingPrefix . "Yes! Our 100% cold-pressed botanical formula is completely free of harsh chemicals, making it safe and highly effective for *men's scalp & beard nourishment* as well as *children aged 5+*!";
        }

        // 8. Expanded FAQ — Shelf Life / Expiry
        if (preg_match('/\b(shelf life|expiry|expire|expiration|how long can i keep|store|storage)\b/i', $lower)) {
            return $greetingPrefix . "Every fresh batch has a *12-month shelf life* after opening when stored in a cool, dry place away from direct sunlight!";
        }

        // 9. Price / Extra Discount Objection ("Too expensive", "More discount?") -> Hormozi Cost-Per-Wash Math
        if (preg_match('/\b(expensive|costly|price is high|high price|too much|more discount|extra discount|bigger discount|mehenga|kam krein|kam karein|final price|negotiate)\b/i', $lower)) {
            return $greetingPrefix . "Because our cold-pressed botanicals are concentrated (only 4–6 drops per ritual), one bottle lasts *45–60 washes*—that's less than *{$costPerWash} per wash* to protect your roots from harsh sulfates!";
        }

        // 10. Realistic Results Timeline ("When will I see results?", "How soon?")
        if (preg_match('/\b(how soon|when will i see|results|timeline|how long to work|kitne din mein farak|kab tak farak|work)\b/i', $lower) && !preg_match('/\b(deliver|delivery|courier|arrive|aayega|ayega|shipping|shipped|order)\b/i', $lower)) {
            return $greetingPrefix . "In *Wash 1–2*, you'll feel noticeably softer hair, reduced frizz, and a clean, hydrated scalp. Within *Weeks 2–4* (used 2–3x weekly), most women in our Laleen community see a visible reduction in hairfall and stronger roots!";
        }

        // 11. Qatar Salon / Branch Question
        if ($isQatar && preg_match('/\b(salon|branch|branches|pickup|pick up|visit|in person|where are you|location|wakrah|old airport|doha)\b/i', $lower)) {
            return "In Qatar, you can enjoy fast *same-day or next-day delivery* across Doha, or pick up in person at *Laleen Beauty Salon (Old Airport & Al Wakrah branches)*!";
        }

        // 12. Live WISMO ("Where is my order?", "Kab milega?", "Has it shipped?", "Tracking")
        if (preg_match('/\b(where is my order|order status|has it shipped|shipped yet|tracking|kab milega|kab aayega|kab tak aayega|kitne din|deliver|delivery|shipping|when will|courier|arrive)\b/i', $lower)) {
            $statusLower = strtolower($liveOrderStatus);
            if ($statusLower === 'shipped') {
                return $greetingPrefix . "Great news! Order {$shortRef} has already been *dispatched with our courier* and is currently in transit—the rider will call you ahead of delivery!";
            }
            if ($statusLower === 'delivered') {
                return $greetingPrefix . "Order {$shortRef} is marked as *delivered* in our system! Please let us know how your Laleen ritual feels or if you need any assistance.";
            }
            if ($isQatar) {
                return $greetingPrefix . "Order {$shortRef} is *confirmed and being hand-packed* at our Doha warehouse for *same-day or next-day delivery* (or available at Laleen Beauty Salon in Old Airport & Al Wakrah)!";
            }

            return $greetingPrefix . "Inshallah Order {$shortRef} is *confirmed and being hand-packed* at our warehouse right now for *3–5 working day delivery* nationwide via Cash on Delivery!";
        }

        // 13. Hairfall / Thinning / Dandruff -> Emotional Validation FIRST + Botanical Mechanism
        if (preg_match('/\b(hairfall|hair fall|shedding|thinning|dandruff|flaky|roots|apricot|baal|girna)\b/i', $lower)) {
            return $greetingPrefix . "Dealing with sudden shedding in the shower is so frustrating—most women in our Laleen community use our *100% pure cold-pressed Hunza Apricot Oil* (rich in Vitamin E & oleic acid) because it absorbs deeply without grease to nourish roots and visibly reduce hairfall in 2–4 weeks.";
        }

        // 14. Sulfates / Keratin / Color-Treated Hair -> Emotional Validation FIRST + Biological Mechanism
        if (preg_match('/\b(sulfate|sulfates|paraben|parabens|silicone|keratin|color|colour|protein|chemical|gentle)\b/i', $lower)) {
            return $greetingPrefix . "Hard water and harsh commercial sulfates can be so stubborn on treated hair! Our *Sulfate-Free Botanical Shampoo* (0% sulfates, parabens, or harsh salts) protects keratin/color treatments while clearing follicle buildup so *Hunza Apricot Oil* absorbs 2x deeper.";
        }

        // 15. Frizz / Serum / Mask -> Emotional Validation FIRST + Botanical Mechanism
        if (preg_match('/\b(serum|mask|frizz|frizzy|humidity|heat|split ends)\b/i', $lower)) {
            return $greetingPrefix . "Fighting daily frizz and humidity dryness can be so exhausting—our *Hair Serum & Hair Mask* seal the cuticle for effortless shine, heat protection, and deep weekly repair.";
        }

        return null;
    }

    /**
     * Deterministic Master Closer (Ezra Firestone + Alex Hormozi + Andy Elliott) intent & response engine —
     * guarantees 100% reliability in offline/test environments and enforces all guardrails.
     */
    protected function buildDeterministicPlaybookDecision(
        EcommerceWhatsappThread $thread,
        string $customerText,
        EcommerceAiSetting $settings,
        string $currency,
        string $orderItemsSummary,
        float $currentOrderTotal,
        float $upgradedOrderTotal,
        ?EcommerceProduct $offeredProduct,
        float $offeredStock,
        float $basePrice,
        float $addonPrice,
        float $discountPct,
        string $discountCode,
        string $checkoutUrl,
        string $liveOrderStatus = 'pending',
        string $livePaymentStatus = 'unpaid_cod',
        string $liveOrderDate = 'N/A',
        string $heroProductName = 'Hunza Apricot Oil',
        float $heroDiscountPrice = 960.0,
        array $catalogContext = []
    ): array {
        $trimmed = trim($customerText);
        $lower = strtolower($trimmed);
        $customer = $thread->customer;
        $customerName = $customer?->name ?: 'there';
        $ordersCount = (int) ($customer?->orders_count ?? 0);
        $isRepeatVip = $ordersCount >= 2;
        $personaName = $settings->agent_persona_name ?: 'Zara';
        $offeredName = $offeredProduct?->name ?? 'Sulfate-Free Botanical Shampoo';
        $shortRef = $thread->shortOrderRef();
        $isQatar = strtoupper($thread->region) === 'QA';
        $costPerWash = $isQatar ? 'QAR 1.5' : 'PKR 40';

        $requestedQty = $this->extractRequestedQuantity($customerText);
        $totalAddonForQty = round($addonPrice * $requestedQty, 2);
        $upgradedTotalForQty = round($currentOrderTotal + $totalAddonForQty, 2);

        $fmtCurrentTotal = number_format($currentOrderTotal, 0);
        $fmtAddonPrice = number_format($addonPrice, 0);
        $fmtTotalAddonForQty = number_format($totalAddonForQty, 0);
        $fmtBasePrice = number_format($basePrice, 0);
        $fmtUpgradedTotal = number_format($upgradedTotalForQty, 0);
        $fmtHeroDiscountPrice = number_format($heroDiscountPrice, 0);
        $fmtDiscountPct = number_format($discountPct, 0);

        $isRetentionPlaybook = in_array($thread->playbook, ['day7_cross_sell', 'day30_refill'], true);
        $isRomanUrdu = $this->isRomanUrduMessage($customerText);
        $hasNativeConfirm = $this->hasNativeScriptConfirmation($customerText);
        $botanicalAnswer = $this->resolveBotanicalKnowledgeAnswer($customerText, $thread->region, $liveOrderStatus, $shortRef);
        $extractedDeliveryNote = $this->extractDeliveryNoteFromText($customerText);

        // 0A. Opt-Out Compliance (`STOP` / `UNSUBSCRIBE`)
        if (preg_match('/^(stop|unsubscribe|opt\s*out|stop\s+messages|unsub)$/i', $lower)) {
            return [
                'detected_intent' => 'opt_out',
                'sentiment' => 'neutral',
                'should_trigger_action' => 'opt_out',
                'requested_quantity' => 1,
                'extracted_delivery_note' => null,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'master-closer-engine',
                'tokens' => 0,
                'reply_message' => "You have been unsubscribed from automated WhatsApp reminders, {$customerName}. We won't send any further automated follow-ups—if you ever need help with an order in the future, just message us here anytime! ✨",
            ];
        }

        // 0B. Non-Text Media Grace (`[Voice Note Received]`, `[Image Received]`, `[Location Pin Received]`)
        if (str_starts_with($trimmed, '[Location Pin Received]')) {
            $cta = $thread->state === 'awaiting_cod_confirm'
                ? "Should I go ahead and lock Order {$shortRef} (*{$currency} {$fmtCurrentTotal}*) for dispatch today? Just reply *YES*!"
                : "Our courier rider will use your exact pinned location and call ahead before arriving! ✨";

            return [
                'detected_intent' => 'ask_question',
                'sentiment' => 'positive',
                'should_trigger_action' => 'none',
                'requested_quantity' => 1,
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'master-closer-engine',
                'tokens' => 0,
                'reply_message' => "JazakAllah {$customerName}! 📍 I've saved your WhatsApp location pin directly onto Order {$shortRef} for our delivery rider.\n\n{$cta}",
            ];
        }

        if ($trimmed === '[Voice Note Received]' || str_starts_with($trimmed, '[Image Received]')) {
            $mediaLabel = $trimmed === '[Voice Note Received]' ? 'voice note' : 'image';
            $promptAction = $thread->state === 'awaiting_cod_confirm'
                ? "To help our packing table move fast, could you reply with a quick *YES* (or type any address note) to confirm Order {$shortRef}, or would you like a team member to review your {$mediaLabel}?"
                : "Could you type a quick text reply (like *UPGRADE* or *NO THANKS*) for Order {$shortRef}, or should I have our specialist review your {$mediaLabel} personally?";

            return [
                'detected_intent' => 'ask_question',
                'sentiment' => 'neutral',
                'should_trigger_action' => 'none',
                'requested_quantity' => 1,
                'extracted_delivery_note' => null,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'master-closer-engine',
                'tokens' => 0,
                'reply_message' => "Thank you for sending that {$mediaLabel}, {$customerName}! ✨\n\n{$promptAction}",
            ];
        }

        // 1. Check for Medical Scalp Condition, Severe Allergy, Angry Customer, or Damaged Parcel -> Escalate to Human
        // Note: "will it leak?" is a logistical buying signal, whereas "my bottle leaked / arrived broken" is a damaged parcel escalation.
        $isLogisticalLeakQuestion = (bool) preg_match('/\b(will it leak|does it leak|leak proof|leak-proof|is it sealed|packing kaisi)\b/i', $lower);
        if (
            !$isLogisticalLeakQuestion
            && preg_match('/\b(psoriasis|eczema|alopecia|fungal|infection|bleeding|severe allergy|allergic reaction|dermatitis|medical|doctor|angry|broken|leaked|damaged|fake|scam|refund|complaint|manager|human|agent|worst|terrible)\b/i', $lower)
        ) {
            $isMedical = (bool) preg_match('/\b(psoriasis|eczema|alopecia|fungal|infection|bleeding|severe allergy|allergic reaction|dermatitis|medical|doctor)\b/i', $lower);
            $handoffReply = $isMedical
                ? "Thank you for sharing that with us, {$customerName} 🙏 Because your scalp health and safety come first with medical or allergic conditions, I have paused our automated assistant and connected your chat directly to our Senior Hair & Care Specialist to guide you personally."
                : "I am so sorry to hear this, {$customerName} 🙏 I have paused our automated dispatch assistant and escalated Order {$shortRef} directly to our Senior Customer Care Manager. A team member will reply to you personally within minutes.";

            return [
                'detected_intent' => 'human_handoff',
                'sentiment' => 'frustrated',
                'should_trigger_action' => 'escalate_to_human',
                'requested_quantity' => 1,
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'master-closer-engine',
                'tokens' => 0,
                'reply_message' => $handoffReply,
            ];
        }

        // 2. Duplicate Order Deduplication (`cancel_duplicate_orders`)
        if (preg_match('/\b(two orders|2 orders|duplicate order|duplicate orders|ordered twice|placed twice|placed two|double order|do order|2 dafa|do dafa|cancel duplicate|cancel extra order|accidentally ordered)\b/i', $lower)) {
            return [
                'detected_intent' => 'cancel_duplicate_orders',
                'sentiment' => 'positive',
                'should_trigger_action' => 'cancel_duplicate_orders',
                'requested_quantity' => 1,
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'master-closer-engine',
                'tokens' => 0,
                'reply_message' => "All sorted, {$customerName}! 🧹 I have cancelled your duplicate pending order so you won't be double-charged, and kept Order {$shortRef} (*{$orderItemsSummary}* — *{$currency} {$fmtCurrentTotal}*) confirmed and active.\n\nWhile your box for {$shortRef} is open, would you like to slip in *1x {$offeredName}* at *{$fmtDiscountPct}% OFF* (*+{$currency} {$fmtAddonPrice}*, free shipping), or ship your original order as-is?",
            ];
        }

        // 3. Check for full order cancellation intent
        if (preg_match('/\b(cancel order|cancel my order|mistake|don\'t send|do not send|wrong order|order cancel)\b/i', $lower) || $lower === 'cancel') {
            return [
                'detected_intent' => 'cancel_order',
                'sentiment' => 'neutral',
                'should_trigger_action' => 'cancel_cod_order',
                'requested_quantity' => 1,
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'master-closer-engine',
                'tokens' => 0,
                'reply_message' => "No problem at all, {$customerName}! I have cancelled Order {$shortRef} as requested so it won't be dispatched. Whenever you're ready in the future, we'll be right here for you ✨",
            ];
        }

        // 4. INBOUND COLD BUYER CONSULTATION PLAYBOOK (`inbound_support`)
        if ($thread->playbook === 'inbound_support' && EcommerceWhatsappThread::isColdChatOrderRef($thread->order_group_id)) {
            $recProduct = $offeredProduct ?? ($catalogContext['recommended_product'] ?? null);
            if (preg_match('/\boil\b/i', $lower) && !preg_match('/\bshampoo\b/i', $lower)) {
                $oilCandidate = EcommerceProduct::with('pricingModel')
                    ->where('name', 'like', '%oil%')
                    ->orderBy('id')
                    ->first();
                if ($oilCandidate) {
                    $recProduct = $oilCandidate;
                }
            } elseif (preg_match('/\bshampoo\b/i', $lower) && !preg_match('/\boil\b/i', $lower)) {
                $shampooCandidate = EcommerceProduct::with('pricingModel')
                    ->where('name', 'like', '%shampoo%')
                    ->orderBy('id')
                    ->first();
                if ($shampooCandidate) {
                    $recProduct = $shampooCandidate;
                }
            } elseif (!$recProduct) {
                $recProduct = $catalogContext['recommended_product'] ?? null;
            }

            $recName = $recProduct?->name ?? 'Hunza Apricot Oil';
            $recPrice = $recProduct
                ? $recProduct->resolveBasePrice($thread->region)
                : (float) ($catalogContext['recommended_price'] ?? $basePrice);
            $fmtRecPrice = number_format($recPrice, 0);
            $recUrl = $recProduct
                ? $recProduct->getShopifyCheckoutUrl(null, $settings->shopify_store_domain)
                : (string) ($catalogContext['recommended_url'] ?? $checkoutUrl);
            $diagnosis = (string) ($catalogContext['concern_diagnosis'] ?? 'Most women in our Laleen community see calmer, healthier roots within 2–4 weeks of switching to pure cold-pressed botanicals.');

            if ($recProduct && ($thread->offered_product_id !== $recProduct->id || (float) $thread->offered_addon_price !== $recPrice)) {
                $thread->offered_product_id = $recProduct->id;
                $thread->offered_addon_price = $recPrice;
                $thread->save();
            }

            $hasPriorAiReply = $thread->messages
                ->where('direction', 'outbound')
                ->where('sender_type', 'ai')
                ->isNotEmpty();

            $vipWelcome = !$hasPriorAiReply
                ? ($isRepeatVip ? "Welcome back to the Laleen family, {$customerName}! ✨ " : "Assalam-o-Alaikum {$customerName}! ✨ ")
                : ($isRomanUrdu ? "Jee bilkul, {$customerName}! ✨ " : '');

            $coldShippingAddress = $this->extractColdCustomerShippingAddress($customerText, $thread);
            $existingThreadAddress = trim((string) ($thread->delivery_notes ?? ''));
            $resolvedShippingAddress = $coldShippingAddress ?: ($existingThreadAddress !== '' ? $existingThreadAddress : null);

            $wantsToBuyOrOrder = (bool) preg_match(
                '/\b(book\s+\d*|book\s+my|book\s+it|book\s+order|order\s+on\s+cod|confirm\s+on\s+cod|confirm\s+my\s+order|cash\s+on\s+delivery|order\s+book|bhej\s*dein|bhejdein|sirf\s+oil\s+bhej|oil\s+bhej|shampoo\s+bhej|try\s+the\s+oil\s+first|try\s+oil\s+first|only\s+send\s+(?:the\s+)?(?:oil|shampoo)|send\s+(?:only\s+)?(?:the\s+)?(?:oil|shampoo)|send\s+me\s+\d*\s*(?:bottle|oil|shampoo|apricot)|want\s+to\s+(?:order|buy|try)|order\s+the\s+(?:oil|shampoo)|buy\s+the\s+(?:oil|shampoo))\b/i',
                $lower
            );

            // 4.1 ONLY create an in-chat COD order for cold visitors AFTER they provide their delivery address!
            if ($coldShippingAddress !== null || ($wantsToBuyOrOrder && $resolvedShippingAddress !== null)) {
                $totalBookingPrice = round($recPrice * $requestedQty, 2);
                $fmtBookingTotal = number_format($totalBookingPrice, 0);
                $qtyLabel = $requestedQty > 1 ? "{$requestedQty}x " : '1x ';

                return [
                    'detected_intent' => 'confirm_cod',
                    'sentiment' => 'positive',
                    'should_trigger_action' => 'convert_abandoned_to_cod_order',
                    'requested_quantity' => $requestedQty,
                    'extracted_delivery_note' => $resolvedShippingAddress,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "JazakAllah {$customerName}! 🎉 I have booked your Cash-on-Delivery order for *{$qtyLabel}{$recName}* (Total: *{$currency} {$fmtBookingTotal}*) to *{$resolvedShippingAddress}* right here in WhatsApp and locked your parcel for priority warehouse dispatch today! 📦✨",
                ];
            }

            // 4.2 Cold customer wants to buy/try a product ("Filhal sirf oil bhej dein, let me try the oil first" / "Send me 1 Apricot Oil")
            // WITHOUT having provided a delivery address yet -> Share clickable Shopify Checkout URL FIRST + mention COMPLETE20 bundle + ask for Full Name, Complete Delivery Address & City!
            if ($wantsToBuyOrOrder) {
                $totalBookingPrice = round($recPrice * $requestedQty, 2);
                $fmtBookingTotal = number_format($totalBookingPrice, 0);
                $qtyLabel = $requestedQty > 1 ? "{$requestedQty}x " : '1x ';
                $opener = $isRomanUrdu
                    ? "Jee bilkul, wonderful choice {$customerName}! ✨"
                    : "Wonderful choice, {$customerName}! ✨";

                $reply = "{$opener} Here is your direct checkout link for *{$qtyLabel}{$recName}* (*{$currency} {$fmtBookingTotal}* — lasts 45–60 washes, under {$costPerWash} per wash):\n{$recUrl}\n\n"
                    . "*(Tip: You can also grab our **Complete Hair Recovery Bundle** with code **COMPLETE20** for **20% OFF** at checkout if you'd like both the Oil + Sulfate-Free Shampoo!)*\n\n"
                    . "Click your direct link above to confirm your delivery address—or simply reply here with your *Full Name, Complete Delivery Address & City* and I'll book your Cash-on-Delivery parcel right here in chat!";

                return [
                    'detected_intent' => 'ask_question',
                    'sentiment' => 'positive',
                    'should_trigger_action' => 'send_checkout_link',
                    'requested_quantity' => $requestedQty,
                    'extracted_delivery_note' => null,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => $reply,
                ];
            }

            // Check if customer sent a simple greeting ("hi", "hello", "salam", "hey", "aoa") without a specific question yet
            $isPureGreeting = (bool) preg_match('/^(hi|hello|hey|salam|assalam\s*o\s*alaikum|assalamualaikum|aoa|asalam\s*o\s*alaikum|good\s+morning|good\s+evening)[\s!?.]*$/i', $trimmed);
            if ($isPureGreeting) {
                $personaName = $settings->agent_persona_name ?: 'Zara';
                $greetingReply = "{$vipWelcome}I'm {$personaName}, your personal Hair Concierge at Laleen Rituals.\n\n"
                    . "Are you looking to solve *hairfall & root thinning*, *frizz & dryness*, or switch to a *100% sulfate-free daily wash*? Share your hair goal (or ask about our *{$recName}* at *{$currency} {$fmtRecPrice}*) and I'll guide you right away!";

                return [
                    'detected_intent' => 'ask_question',
                    'sentiment' => 'positive',
                    'should_trigger_action' => 'none',
                    'requested_quantity' => 1,
                    'extracted_delivery_note' => null,
                    'is_high_confidence_keyword' => false,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => $greetingReply,
                ];
            }

            $leadText = $botanicalAnswer ?: $diagnosis;
            $reply = "{$vipWelcome}{$leadText}\n\n"
                . "I recommend starting with our concentrated *{$recName}* (*{$currency} {$fmtRecPrice}* — lasts 45–60 washes, under {$costPerWash} per wash). Would you like me to book *{$recName}* for you on Cash-on-Delivery right here, or grab it via your direct link below?\n{$recUrl}";

            return [
                'detected_intent' => 'ask_question',
                'sentiment' => 'positive',
                'should_trigger_action' => 'none',
                'requested_quantity' => $requestedQty,
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => false,
                'source' => 'deterministic_playbook',
                'model' => 'master-closer-engine',
                'tokens' => 0,
                'reply_message' => $reply,
            ];
        }

        // 4B. PLAYBOOK 4: 15-MINUTE ABANDONED CHECKOUT WHATSAPP RESCUE (`abandoned_checkout`)
        if ($thread->playbook === 'abandoned_checkout') {
            $activeCheckout = $customer
                ? EcommerceAbandonedCheckout::where('ecommerce_customer_id', $customer->id)
                    ->whereIn('status', ['abandoned', 'outreach_sent'])
                    ->orderByDesc('id')
                    ->first()
                : null;

            $abandonedUrl = trim((string) ($activeCheckout?->abandoned_checkout_url ?: $checkoutUrl));
            $vip15Url = $offeredProduct
                ? $offeredProduct->getShopifyCheckoutUrl('VIPREFILL15', $settings->shopify_store_domain)
                : $abandonedUrl;
            $cartItemsSummary = $activeCheckout ? $activeCheckout->itemsSummaryText() : "*{$offeredName}*";
            $cartSubtotal = (float) ($activeCheckout?->subtotal_price ?: $basePrice);
            if ($cartSubtotal <= 0) {
                $cartSubtotal = $basePrice;
            }
            $vip15Price = max(1.0, round($cartSubtotal * 0.85, 2));
            $fmtCartSubtotal = number_format($cartSubtotal, 0);
            $fmtVip15Price = number_format($vip15Price, 0);

            // Check if customer asks for the checkout link or says "Filhal sirf oil bhej dein" -> share direct checkout link first
            $wantsLinkInAbandoned = (bool) preg_match(
                '/\b(send\s+(?:me\s+)?(?:the\s+)?link|share\s+(?:the\s+)?link|checkout\s+link|cart\s+link|filhal|sirf\s+oil|try\s+(?:the\s+)?oil\s+first|only\s+oil)\b/i',
                $lower
            );

            if ($wantsLinkInAbandoned) {
                $opener = $isRomanUrdu ? "Jee bilkul, {$customerName}! ✨" : "Here you go, {$customerName}! ✨";

                return [
                    'detected_intent' => 'ask_question',
                    'sentiment' => 'positive',
                    'should_trigger_action' => 'send_checkout_link',
                    'requested_quantity' => 1,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "{$opener} Here is your direct checkout link for {$cartItemsSummary} (*{$currency} {$fmtCartSubtotal}*):\n{$abandonedUrl}\n\nOr if you'd like me to convert your cart into a confirmed Cash-on-Delivery order right here in WhatsApp, just reply *CONFIRM ON COD*!",
                ];
            }

            // Check if customer wants to confirm/book their abandoned cart directly on Cash on Delivery (COD)
            $wantsCodConversion = (bool) preg_match(
                '/\b(confirm my order|confirm order|confirm here|on cod|cash on delivery|cod kr|cod kar|reserve my bottle|reserve it|book my order|book here|send on cod|dispatch on cod)\b/i',
                $lower
            ) || (
                $hasNativeConfirm && !preg_match('/\b(link|discount|expensive)\b/i', $lower)
            );

            if ($wantsCodConversion) {
                $opener = $isRomanUrdu
                    ? "Jee bilkul, JazakAllah {$customerName}! 🎉 Inshallah I"
                    : "JazakAllah {$customerName}! 🎉 I";

                return [
                    'detected_intent' => 'confirm_cod',
                    'sentiment' => 'positive',
                    'should_trigger_action' => 'convert_abandoned_to_cod_order',
                    'requested_quantity' => $requestedQty,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "{$opener} have converted your cart ({$cartItemsSummary} — Total: *{$currency} {$fmtCartSubtotal}*) into a confirmed *Cash-on-Delivery* order right here and locked your bottle for priority warehouse dispatch today! 📦✨",
                ];
            }

            // Check if customer hesitates on price or shipping -> 1-Pivot Downsell / Incentives Rule (VIPREFILL15 + Cost-Per-Wash math)
            $isPriceOrShippingHesitation = (bool) preg_match(
                '/\b(too expensive|expensive|shipping is high|high shipping|delivery charge|delivery fee|price is high|costly|mehenga|discount|offer|cheaper|kam krein)\b/i',
                $lower
            );

            if ($isPriceOrShippingHesitation) {
                $opener = $isRomanUrdu ? "Jee bilkul, totally understandable {$customerName}! ✨" : "Totally understandable, {$customerName}! ✨";

                return [
                    'detected_intent' => 'ask_question',
                    'sentiment' => 'neutral',
                    'should_trigger_action' => 'send_checkout_link',
                    'requested_quantity' => 1,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "{$opener} Because our pure Hunza botanical formula is concentrated, 1 bottle lasts *45–60 washes* (under *{$costPerWash} per wash* to protect your roots from harsh sulfates)—and to help with shipping, I've unlocked a *VIP 15% OFF* savings link (*{$currency} {$fmtVip15Price}* instead of {$currency} {$fmtCartSubtotal} with code *VIPREFILL15*).\n\nWould you like to claim your 15% savings via the link below, or reply *CONFIRM ON COD* so I can lock it for you right here?\n{$vip15Url}",
                ];
            }

            // Check if customer declines
            if (preg_match('/\b(no thanks|not interested|don\'t want|cancel|nahi chahiye|skip)\b/i', $lower)) {
                return [
                    'detected_intent' => 'decline_upsell',
                    'sentiment' => 'neutral',
                    'should_trigger_action' => 'lock_order_without_upsell',
                    'requested_quantity' => 1,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "No pressure at all, {$customerName}! Your cart link will stay saved whenever you're ready, and if you ever have any hair care questions, just message us here anytime ✨",
                ];
            }

            // Product / Delivery Q&A in Playbook 4
            $answerLead = $botanicalAnswer
                ?: "Most women in our Laleen community love how lightweight and non-greasy our cold-pressed Hunza botanicals feel from the very first wash (1 bottle lasts *45–60 washes* — under *{$costPerWash} per wash*).";

            return [
                'detected_intent' => 'ask_question',
                'sentiment' => 'positive',
                'should_trigger_action' => 'none',
                'requested_quantity' => 1,
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'master-closer-engine',
                'tokens' => 0,
                'reply_message' => "{$answerLead}\n\nWould you like to complete your order for {$cartItemsSummary} via your direct cart link below, or reply *CONFIRM ON COD* so I can reserve your bottle right here?\n{$abandonedUrl}",
            ];
        }

        // 4C. PRE-DISPATCH ORDER MUTATIONS (Revert Extra Bottles OR Add Bottles From Any Active Pre-Dispatch State)
        if (!$isRetentionPlaybook && !EcommerceWhatsappThread::isColdChatOrderRef($thread->order_group_id)) {
            $isRevertRequest = (bool) preg_match(
                '/\b(remove\s+(?:the\s+)?(?:extra|shampoo|oil|upgrade|add-on|addon|bottles|bottle)|revert|take\s+off|cancel\s+(?:the\s+)?upgrade|remove\s+upgrade|only\s+send\s+(?:my\s+)?original|only\s+send\s+(?:the\s+)?oil|just\s+send\s+(?:the\s+)?oil|only\s+original)\b/i',
                $lower
            );

            if ($isRevertRequest && ($thread->state === 'upsell_accepted' || (float) $thread->upsell_revenue_generated > 0 || $thread->state === 'cod_confirmed_upsell_pitched' || $thread->state === 'upsell_declined')) {
                $upsellAmount = (float) ($thread->upsell_revenue_generated > 0 ? $thread->upsell_revenue_generated : 0.0);
                $originalTotal = max(0.0, round($currentOrderTotal - $upsellAmount, 2));
                if ($originalTotal <= 0 && $currentOrderTotal > 0) {
                    $originalTotal = $currentOrderTotal;
                }
                $fmtOriginalTotal = number_format($originalTotal, 0);

                return [
                    'detected_intent' => 'revert_upsell',
                    'sentiment' => 'neutral',
                    'should_trigger_action' => 'revert_upsell',
                    'requested_quantity' => 1,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "All taken care of, {$customerName}! I have removed the extra upsell bottles from Order {$shortRef}. Your original order (Total: *{$currency} {$fmtOriginalTotal}*) is locked and ready for dispatch today ✨",
                ];
            }

            $isExplicitBoxUpgradeRequest = !$isRevertRequest
                && $liveOrderStatus === 'pending'
                && (bool) preg_match(
                    '/\b(add\s+[1-5]?\s*(?:more\s+)?(?:bottles|bottle|shampoos|shampoo|oils|oil|units|packs)|add\s+(?:it|to\s+my\s+box|to\s+box|the\s+shampoo|the\s+oil|shampoo|oil|2nd\s+bottle|second\s+bottle)|include\s+(?:the\s+)?(?:shampoo|oil|it|bottles|bottle)|upgrade(?:\s+my\s+order|\s+my\s+box)?|do\s+(?:bottles|bottle|shampoo|oil)\s+add|shampoo\s+add|oil\s+add|(?:haan|yes|ok|okay|sure)\s+add)\b/i',
                    $lower
                );

            if ($isExplicitBoxUpgradeRequest) {
                $prefix = $isRomanUrdu ? "Jee bilkul, {$customerName}! 🎉 Inshallah " : "Wonderful choice, {$customerName}! 🎉 ";
                $qtyLabel = "{$requestedQty}x {$offeredName}";

                return [
                    'detected_intent' => 'accept_upsell',
                    'sentiment' => 'positive',
                    'should_trigger_action' => 'execute_order_upgrade',
                    'requested_quantity' => $requestedQty,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "{$prefix}I've just added *{$qtyLabel}* (+{$currency} {$fmtTotalAddonForQty} at {$fmtDiscountPct}% OFF with FREE shipping) straight into your open box for Order {$shortRef}!\n\nYour updated Cash-on-Delivery total is *{$currency} {$fmtUpgradedTotal}*, and our warehouse team is sealing your parcel for dispatch today ✨",
                ];
            }
        }

        // 5. TERMINAL STATES: ['upsell_accepted', 'upsell_declined', 'completed']
        if (in_array($thread->state, ['upsell_accepted', 'upsell_declined', 'completed'], true)) {
            if (
                $thread->state === 'upsell_accepted'
                && !$isRetentionPlaybook
                && preg_match('/\b(remove|revert|take off|without|cancel upgrade|cancel the upgrade|remove upgrade|remove the shampoo|remove shampoo|remove oil|only send the oil|only send oil|just send the oil|only original|sirf oil)\b/i', $lower)
            ) {
                $upsellAmount = (float) ($thread->upsell_revenue_generated > 0 ? $thread->upsell_revenue_generated : $addonPrice);
                $originalTotal = max(0.0, round($currentOrderTotal - $upsellAmount, 2));
                $fmtOriginalTotal = number_format($originalTotal, 0);

                return [
                    'detected_intent' => 'revert_upsell',
                    'sentiment' => 'neutral',
                    'should_trigger_action' => 'revert_upsell',
                    'requested_quantity' => 1,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "All taken care of, {$customerName}! I have removed the AI upsell add-on from Order {$shortRef}. Your original order (Total: *{$currency} {$fmtOriginalTotal}*) is locked and ready for dispatch today ✨",
                ];
            }

            $prefix = $botanicalAnswer ? ($botanicalAnswer . "\n\n") : '';
            $noteAck = $extractedDeliveryNote ? "I've saved your delivery note (*\"{$extractedDeliveryNote}\"*). " : '';

            return [
                'detected_intent' => 'ask_question',
                'sentiment' => 'positive',
                'should_trigger_action' => 'none',
                'requested_quantity' => 1,
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'master-closer-engine',
                'tokens' => 0,
                'reply_message' => $prefix . "Thank you, {$customerName}! {$noteAck}Your order {$shortRef} (*{$orderItemsSummary}* — Total: *{$currency} {$fmtCurrentTotal}*) is packed and ready for dispatch 📦✨",
            ];
        }

        // 6. PLAYBOOKS 2 & 3 (`day7_cross_sell` & `day30_refill`): Direct Shopify Discount Checkout Link Flow
        if ($isRetentionPlaybook) {
            if (preg_match('/\b(not yet|no|no thanks|nope|nahi|later|skip|khallas|khalas)\b/i', $lower)) {
                return [
                    'detected_intent' => 'decline_upsell',
                    'sentiment' => 'neutral',
                    'should_trigger_action' => 'lock_order_without_upsell',
                    'requested_quantity' => 1,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "No rush at all, {$customerName}! Keep enjoying your Laleen ritual, and whenever you're ready for your next bottle, just message us here and we'll take care of you ✨",
                ];
            }

            $reasonWhy = $thread->playbook === 'day30_refill'
                ? "To protect your 30-day root momentum before your last drops run out, I've activated *{$fmtDiscountPct}% OFF* on *{$offeredName}* (*{$currency} {$fmtAddonPrice}* instead of {$currency} {$fmtBasePrice} — under {$costPerWash}/wash with code *{$discountCode}*)."
                : "To reward your Week-1 consistency with the synergy of root nourishment + sulfate-free cleansing, I've unlocked *{$fmtDiscountPct}% OFF* on *{$offeredName}* (*{$currency} {$fmtAddonPrice}* instead of {$currency} {$fmtBasePrice} — under {$costPerWash}/wash with code *{$discountCode}*).";

            $intro = $botanicalAnswer
                ? "{$botanicalAnswer}\n\n"
                : ($isRomanUrdu ? "Jee bilkul, {$customerName}! ✨ " : "So glad to hear from you, {$customerName}! ✨ ");

            $reply = $intro . $reasonWhy . "\n\nClaim your bottle directly here:\n{$checkoutUrl}";

            return [
                'detected_intent' => $botanicalAnswer ? 'ask_question' : 'accept_upsell',
                'sentiment' => 'positive',
                'should_trigger_action' => 'send_checkout_link',
                'requested_quantity' => $requestedQty,
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'source' => 'deterministic_playbook',
                'model' => 'master-closer-engine',
                'tokens' => 0,
                'reply_message' => $reply,
            ];
        }

        // 7. PLAYBOOK 1 (`cod_upsell`) — State: awaiting_cod_confirm
        if ($thread->state === 'awaiting_cod_confirm') {
            $isSalaryOrFutureHold = $extractedDeliveryNote !== null
                && preg_match('/\b(next\s+week|next\s+month|salary|payday|pay\s*day|after\s+(?:the\s+)?\d+|1st|5th|10th|hold|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i', $lower);

            $isCodConfirm = $hasNativeConfirm
                || $isSalaryOrFutureHold
                || (bool) preg_match(
                    '/\b(yes|yep|yeah|confirm|confirmed|sure|ok|okay|haan|han|jee|ji|send|dispatch|kr\s*dein|kar\s*dein|kardein|bhej\s*dein|inshallah)\b/i',
                    $lower
                );

            if ($isCodConfirm) {
                $vipPrefix = $isRepeatVip ? "Welcome back, {$customerName}! " : '';
                $greeting = $isRomanUrdu
                    ? "Jee bilkul, JazakAllah {$customerName}! {$vipPrefix}Your"
                    : "JazakAllah {$customerName}! {$vipPrefix}Your";
                $noteLine = $extractedDeliveryNote ? " (Dispatch note saved: *\"{$extractedDeliveryNote}\"*)" : '';

                if ($settings->pre_shipment_upsell_enabled && $offeredProduct && $offeredStock >= 1 && $addonPrice > 0) {
                    $reply = "{$greeting} order {$shortRef} (*{$orderItemsSummary}*) is confirmed{$noteLine}! Most women in our Laleen community pair it with *{$offeredName}* because commercial sulfates block pure Apricot Oil from absorbing deeply into the roots.\n\n"
                        . "Since your box is still open and courier shipping is already booked, I can slip *1x {$offeredName}* inside at *{$fmtDiscountPct}% OFF* (*+{$currency} {$fmtAddonPrice}* instead of {$currency} {$fmtBasePrice} — under {$costPerWash}/wash across 45–60 washes, *ZERO extra delivery fee*, new total *{$currency} {$fmtUpgradedTotal}*). Reply *UPGRADE* to add it now, or *NO THANKS* to ship as-is!";
                } else {
                    $reply = "{$greeting} COD order {$shortRef} for *{$orderItemsSummary}* (*{$currency} {$fmtCurrentTotal}*) is confirmed{$noteLine} and locked for dispatch today 📦✨ We'll share your tracking details as soon as the courier picks it up!";
                }

                return [
                    'detected_intent' => 'confirm_cod',
                    'sentiment' => 'positive',
                    'should_trigger_action' => 'mark_cod_confirmed_and_pitch_upsell',
                    'requested_quantity' => 1,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => $isRomanUrdu || $hasNativeConfirm || $extractedDeliveryNote !== null,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => $reply,
                ];
            }

            if ($botanicalAnswer) {
                return [
                    'detected_intent' => 'ask_question',
                    'sentiment' => 'positive',
                    'should_trigger_action' => 'none',
                    'requested_quantity' => 1,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "{$botanicalAnswer}\n\nShould I confirm Order {$shortRef} (*{$currency} {$fmtCurrentTotal}*) for dispatch today, or do you have any special delivery note? Just reply *YES*! ✨",
                ];
            }
        }

        // 8. PLAYBOOK 1 (`cod_upsell`) — State: cod_confirmed_upsell_pitched
        if ($thread->state === 'cod_confirmed_upsell_pitched') {
            $isAccept = $hasNativeConfirm
                || (bool) preg_match(
                    '/\b(upgrade|add it|yes add|add to box|yes please|sure add|add shampoo|add oil|shampoo add|oil add|book it|include it|2nd bottle|second bottle|add 2|add two|do shampoo|do bottle)\b/i',
                    $lower
                )
                || preg_match('/^(yes|yep|yeah|sure|ok|okay|haan|jee|haan g|ji haan)$/i', $lower);

            if ($isAccept) {
                $prefix = $isRomanUrdu ? "Jee bilkul, {$customerName}! 🎉 Inshallah " : "Wonderful choice, {$customerName}! 🎉 ";
                $qtyLabel = "{$requestedQty}x {$offeredName}";

                return [
                    'detected_intent' => 'accept_upsell',
                    'sentiment' => 'positive',
                    'should_trigger_action' => 'execute_order_upgrade',
                    'requested_quantity' => $requestedQty,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => $isRomanUrdu || $hasNativeConfirm || $requestedQty > 1 || $extractedDeliveryNote !== null,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "{$prefix}I've just added *{$qtyLabel}* (+{$currency} {$fmtTotalAddonForQty} at {$fmtDiscountPct}% OFF with FREE shipping) straight into your open box for Order {$shortRef}!\n\nYour updated Cash-on-Delivery total is *{$currency} {$fmtUpgradedTotal}*, and our warehouse team is sealing your parcel for dispatch today ✨",
                ];
            }

            // Andy Elliott + Alex Hormozi 1-Pivot Downsell Rule:
            // On 1st objection ("I'll try the oil first", "Filhal sirf oil bhej dein", "Too expensive"),
            // validate warmly, give 1-sentence Hormozi cost-per-wash + biological mechanism rebuttal,
            // and offer an A/B Pivot: 20% off complementary item OR 2nd bottle of their hero product at 20% off!
            $isFirstPivotObjection = (bool) preg_match(
                '/\b(try the oil first|try oil first|try it first|try first|filhal|sirf oil|only oil for now|pehle oil|test first|too expensive|expensive|mehenga|costly)\b/i',
                $lower
            );

            $alreadyOfferedDownsellPivot = $thread->messages
                ->where('direction', 'outbound')
                ->contains(fn (EcommerceWhatsappMessage $m) => str_contains(strtolower((string) $m->message_body), '2nd bottle'));

            if ($isFirstPivotObjection && !$alreadyOfferedDownsellPivot) {
                $opener = $isRomanUrdu ? "Jee bilkul, totally fair {$customerName}! ✨" : "Totally fair, {$customerName}! ✨";

                return [
                    'detected_intent' => 'ask_question',
                    'sentiment' => 'neutral',
                    'should_trigger_action' => 'none',
                    'requested_quantity' => 1,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "{$opener} Applying pure *Hunza Apricot Oil* onto a scalp washed with harsh commercial sulfates blocks absorption—and at *{$fmtDiscountPct}% OFF* (*+{$currency} {$fmtAddonPrice}*, under *{$costPerWash} per wash* across 45–60 washes with free shipping), pairing them protects your hair investment.\n\nWould you like to lock in the *{$fmtDiscountPct}% OFF {$offeredName}*, or would you prefer adding a *2nd bottle of {$heroProductName} at {$fmtDiscountPct}% OFF* (*+{$currency} {$fmtHeroDiscountPrice}*) for family or next month's supply before we seal your box?",
                ];
            }

            if (
                $isFirstPivotObjection
                || preg_match('/\b(no|no thanks|nope|nahi|skip|as is|as-is|original only|just the|just ship|ship just|khallas|khalas)\b/i', $lower)
            ) {
                $closer = $isRomanUrdu ? "Jee bilkul, all set {$customerName}! Inshallah your" : "All set, {$customerName}! Your";

                return [
                    'detected_intent' => 'decline_upsell',
                    'sentiment' => 'neutral',
                    'should_trigger_action' => 'lock_order_without_upsell',
                    'requested_quantity' => 1,
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'master-closer-engine',
                    'tokens' => 0,
                    'reply_message' => "{$closer} original order {$shortRef} for *{$orderItemsSummary}* (Total: *{$currency} {$fmtCurrentTotal}*) is locked and heading to dispatch right now 📦✨ Thank you for choosing Laleen Rituals!",
                ];
            }
        }

        // 9. Botanical Knowledge Q&A, Cultural Instincts, WISMO, or Payment Question while in Playbook 1
        if ($botanicalAnswer) {
            $nextStepPrompt = $thread->state === 'cod_confirmed_upsell_pitched'
                ? "While your box for Order {$shortRef} is still open, would you like to add *1x {$offeredName}* at *{$fmtDiscountPct}% OFF* (*+{$currency} {$fmtAddonPrice}*, free shipping) by replying *UPGRADE*, or ship as-is with *NO THANKS*?"
                : "Should our team pack Order {$shortRef} (*{$currency} {$fmtCurrentTotal}*) for dispatch today? Just reply *YES* to confirm! ✨";

            return [
                'detected_intent' => 'ask_question',
                'sentiment' => 'positive',
                'should_trigger_action' => 'none',
                'requested_quantity' => 1,
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'master-closer-engine',
                'tokens' => 0,
                'reply_message' => "{$botanicalAnswer}\n\n{$nextStepPrompt}",
            ];
        }

        return [
            'detected_intent' => 'ask_question',
            'sentiment' => 'neutral',
            'should_trigger_action' => 'none',
            'requested_quantity' => $requestedQty,
            'extracted_delivery_note' => $extractedDeliveryNote,
            'is_high_confidence_keyword' => false,
            'source' => 'deterministic_playbook',
            'model' => 'master-closer-engine',
            'tokens' => 0,
            'reply_message' => ($offeredProduct && $addonPrice > 0)
                ? "Great question, {$customerName}! Because your box for Order {$shortRef} is still open on our packing table, slipping in *1x {$offeredName}* at *{$fmtDiscountPct}% OFF* is just *+{$currency} {$fmtAddonPrice}* (under {$costPerWash}/wash across 45–60 washes, *FREE extra shipping*, new total *{$currency} {$fmtUpgradedTotal}*).\n\nWould you like to reply *UPGRADE* to add it before the courier seals your parcel, or *NO THANKS* to ship as-is?"
                : "Hi {$customerName}! This is {$personaName} from Laleen Rituals ✨ Your order {$shortRef} for *{$orderItemsSummary}* is *{$currency} {$fmtCurrentTotal}*. Would you like us to lock it for dispatch today?",
        ];
    }

    /**
     * Execute deterministic database mutations based on the AI / Playbook action.
     */
    protected function executeDeterministicAction(
        EcommerceWhatsappThread $thread,
        Collection $orderSales,
        array &$aiDecision,
        EcommerceAiSetting $settings,
        string $customerText = ''
    ): array {
        $action = (string) ($aiDecision['should_trigger_action'] ?? 'none');
        $currency = $thread->currency();
        $shortRef = $thread->shortOrderRef();
        $systemEvent = null;
        $overrideReply = null;

        // 0. Automatic Address & Delivery Note Extraction (`extracted_delivery_note`)
        $extractedNote = trim((string) ($aiDecision['extracted_delivery_note'] ?? ''));
        if ($extractedNote !== '' && strtolower($extractedNote) !== 'null') {
            $thread->delivery_notes = $extractedNote;
            $thread->save();

            if ($thread->order_group_id) {
                EcommerceSale::where('order_group_id', $thread->order_group_id)
                    ->update(['delivery_notes' => $extractedNote]);
            }

            $systemEvent = EcommerceWhatsappMessage::create([
                'thread_id' => $thread->id,
                'direction' => 'outbound',
                'sender_type' => 'system_event',
                'delivery_status' => 'sent',
                'message_body' => "📍 Saved delivery note: {$extractedNote}",
            ]);
        }

        // Safety invariant: Playbooks 2 & 3 NEVER auto-create a DB order; they send a checkout link instead.
        if (in_array($thread->playbook, ['day7_cross_sell', 'day30_refill'], true) && $action === 'execute_order_upgrade') {
            $action = 'send_checkout_link';
            $aiDecision['should_trigger_action'] = 'send_checkout_link';
        }

        if ($action === 'opt_out' || $action === 'opt_out_thread' || ($aiDecision['detected_intent'] ?? '') === 'opt_out') {
            $thread->state = 'opted_out';
            $thread->unread_count = 0;
            $thread->save();

            $systemEvent = EcommerceWhatsappMessage::create([
                'thread_id' => $thread->id,
                'direction' => 'outbound',
                'sender_type' => 'system_event',
                'delivery_status' => 'sent',
                'message_body' => '🔕 Customer opted out of automated WhatsApp reminders (STOP/UNSUBSCRIBE)',
            ]);
        } elseif ($action === 'cancel_duplicate_orders') {
            DB::transaction(function () use ($thread, $settings, &$systemEvent) {
                $dupQuery = EcommerceSale::query()
                    ->where('order_status', 'pending')
                    ->where('payment_status', 'unpaid_cod')
                    ->where('created_at', '>=', now()->subHours(48))
                    ->whereNotNull('order_group_id');

                if ($thread->order_group_id) {
                    $dupQuery->where('order_group_id', '!=', $thread->order_group_id);
                }

                $dupQuery->where(function ($q) use ($thread) {
                    if ($thread->ecommerce_customer_id) {
                        $q->where('ecommerce_customer_id', $thread->ecommerce_customer_id);
                    }
                    if ($thread->customer?->phone) {
                        $q->orWhere('customer_phone', $thread->customer->phone);
                    }
                });

                $duplicateGroupIds = $dupQuery->pluck('order_group_id')->unique()->values();
                foreach ($duplicateGroupIds as $dupGroupId) {
                    EcommerceSale::transitionOrderGroup((string) $dupGroupId, 'cancelled', 'refunded');
                    EcommerceWhatsappThread::where('order_group_id', (string) $dupGroupId)
                        ->where('id', '!=', $thread->id)
                        ->update(['state' => 'completed', 'unread_count' => 0]);
                }

                if ($thread->order_group_id) {
                    EcommerceSale::where('order_group_id', $thread->order_group_id)
                        ->whereNull('cod_confirmed_at')
                        ->update(['cod_confirmed_at' => now()]);
                }

                if ($thread->state === 'awaiting_cod_confirm') {
                    $stockField = strtoupper($thread->region) === 'QA' ? 'stock_qatar' : 'stock_pakistan';
                    $offeredProduct = $thread->offeredProduct ? EcommerceProduct::find($thread->offered_product_id) : null;
                    $hasStock = $offeredProduct && (float) $offeredProduct->{$stockField} >= 1;
                    $canPitchUpsell = $settings->pre_shipment_upsell_enabled
                        && $hasStock
                        && (float) $thread->offered_addon_price > 0;
                    $thread->state = $canPitchUpsell ? 'cod_confirmed_upsell_pitched' : 'upsell_declined';
                    $thread->unread_count = 0;
                    $thread->save();
                }

                $systemEvent = EcommerceWhatsappMessage::create([
                    'thread_id' => $thread->id,
                    'direction' => 'outbound',
                    'sender_type' => 'system_event',
                    'delivery_status' => 'sent',
                    'message_body' => '🧹 Cancelled duplicate pending order(s) per customer request',
                ]);
            });
        } elseif ($action === 'mark_cod_confirmed_and_pitch_upsell') {
            DB::transaction(function () use ($thread, $settings, $shortRef, &$systemEvent) {
                if ($thread->order_group_id) {
                    EcommerceSale::where('order_group_id', $thread->order_group_id)
                        ->whereNull('cod_confirmed_at')
                        ->update(['cod_confirmed_at' => now()]);
                }

                $stockField = strtoupper($thread->region) === 'QA' ? 'stock_qatar' : 'stock_pakistan';
                $offeredProduct = $thread->offeredProduct ? EcommerceProduct::find($thread->offered_product_id) : null;
                $hasStock = $offeredProduct && (float) $offeredProduct->{$stockField} >= 1;

                if ($offeredProduct && (float) $thread->offered_addon_price <= 0) {
                    $discountPct = max(0.0, min(90.0, (float) ($settings->upsell_discount_percent ?? 20.0)));
                    $basePrice = $offeredProduct->resolveBasePrice($thread->region);
                    $thread->offered_addon_price = max(1.0, round($basePrice * (1 - ($discountPct / 100)), 2));
                }

                $canPitchUpsell = $settings->pre_shipment_upsell_enabled
                    && $hasStock
                    && (float) $thread->offered_addon_price > 0;

                $thread->state = $canPitchUpsell
                    ? 'cod_confirmed_upsell_pitched'
                    : 'upsell_declined';
                $thread->unread_count = 0;
                $thread->save();

                $systemEvent = EcommerceWhatsappMessage::create([
                    'thread_id' => $thread->id,
                    'direction' => 'outbound',
                    'sender_type' => 'system_event',
                    'delivery_status' => 'sent',
                    'message_body' => $canPitchUpsell
                        ? "✅ COD Confirmed ({$shortRef}) · Pitched {$offeredProduct?->name}"
                        : "✅ COD Confirmed ({$shortRef}) · Locked for dispatch",
                ]);
            });
        } elseif ($action === 'send_checkout_link') {
            DB::transaction(function () use ($thread, $settings, $currency, &$systemEvent) {
                $offeredProduct = $thread->offeredProduct ? EcommerceProduct::find($thread->offered_product_id) : null;
                $discountCode = $this->discountCodeForPlaybook($thread->playbook, $settings);

                if ($thread->playbook === 'inbound_support') {
                    if ($offeredProduct) {
                        $thread->offered_addon_price = $offeredProduct->resolveBasePrice($thread->region);
                    }
                    $thread->state = 'inbound_active';
                } else {
                    if ($offeredProduct && (float) $thread->offered_addon_price <= 0) {
                        $pct = $thread->playbook === 'day30_refill' ? 15.0 : max(0.0, min(90.0, (float) ($settings->upsell_discount_percent ?? 20.0)));
                        $basePrice = $offeredProduct->resolveBasePrice($thread->region);
                        $thread->offered_addon_price = max(1.0, round($basePrice * (1 - ($pct / 100)), 2));
                    }
                    $thread->state = 'checkout_link_sent';
                }

                $thread->unread_count = 0;
                $thread->save();

                if ($thread->customer && $thread->playbook !== 'inbound_support') {
                    $thread->customer->update([
                        'last_retention_contacted_at' => now(),
                        'last_retention_stage' => $thread->playbook,
                    ]);
                }

                $fmtPrice = number_format((float) $thread->offered_addon_price, 0);
                $prodName = $offeredProduct?->name ?? 'Ritual Upgrade';
                $codeLabel = $thread->playbook === 'inbound_support' ? 'direct' : $discountCode;

                $systemEvent = EcommerceWhatsappMessage::create([
                    'thread_id' => $thread->id,
                    'direction' => 'outbound',
                    'sender_type' => 'system_event',
                    'delivery_status' => 'sent',
                    'message_body' => "🔗 Sent {$codeLabel} Shopify checkout link for {$prodName} ({$currency} {$fmtPrice})",
                ]);
            });
        } elseif ($action === 'convert_abandoned_to_cod_order') {
            $requestedQty = max(1, min(5, (int) ($aiDecision['requested_quantity'] ?? 1)));
            DB::transaction(function () use ($thread, $settings, $currency, $requestedQty, &$aiDecision, &$systemEvent, &$overrideReply) {
                $lockedThread = EcommerceWhatsappThread::lockForUpdate()->find($thread->id);
                if (!$lockedThread || $lockedThread->state === 'upsell_accepted') {
                    return;
                }

                $wasInboundColdChat = ($lockedThread->playbook === 'inbound_support');
                $isQatar = strtoupper($lockedThread->region) === 'QA';
                $stockField = $isQatar ? 'stock_qatar' : 'stock_pakistan';
                $soldField = $isQatar ? 'sold_qatar' : 'sold_pakistan';
                $channel = $isQatar ? 'Organic (Qatar)' : 'Shopify (Pakistan)';

                $checkout = $lockedThread->ecommerce_customer_id
                    ? EcommerceAbandonedCheckout::lockForUpdate()
                        ->where('ecommerce_customer_id', $lockedThread->ecommerce_customer_id)
                        ->whereIn('status', ['abandoned', 'outreach_sent'])
                        ->orderByDesc('id')
                        ->first()
                    : null;

                // Hard Safety Guardrail: Cold visitors (`inbound_support`) with no prior orders and no checkout
                // MUST have a shipping address before creating a database COD order!
                $shippingAddress = trim((string) ($lockedThread->delivery_notes ?: ($aiDecision['extracted_delivery_note'] ?? '')));
                if ($wasInboundColdChat && !$checkout && (int) ($lockedThread->customer?->orders_count ?? 0) === 0 && $shippingAddress === '') {
                    return;
                }

                if ($shippingAddress !== '') {
                    $lockedThread->delivery_notes = $shippingAddress;
                    $thread->delivery_notes = $shippingAddress;

                    // If customer record has a generic visitor name and they wrote "Full Name, Address...", extract their name
                    if ($lockedThread->customer && str_starts_with((string) $lockedThread->customer->name, 'New WhatsApp Visitor')) {
                        $parts = array_map('trim', explode(',', $shippingAddress));
                        if (!empty($parts[0]) && preg_match('/^[A-Za-z][A-Za-z\s.\'-]{2,35}$/', $parts[0]) && !preg_match('/\b(house|flat|street|road|sector|phase|block|dha|gulberg|lahore|karachi|islamabad|doha)\b/i', $parts[0])) {
                            $lockedThread->customer->name = $parts[0];
                            $lockedThread->customer->save();
                        }
                    }
                }

                $lineItems = ($checkout && is_array($checkout->line_items_json) && !empty($checkout->line_items_json))
                    ? $checkout->line_items_json
                    : [];

                if (empty($lineItems)) {
                    $fallbackProduct = $lockedThread->offered_product_id
                        ? EcommerceProduct::find($lockedThread->offered_product_id)
                        : EcommerceProduct::orderBy('id')->first();

                    if ($fallbackProduct) {
                        $lineItems = [[
                            'ecommerce_product_id' => $fallbackProduct->id,
                            'title' => $fallbackProduct->name,
                            'quantity' => $requestedQty,
                            'price' => $fallbackProduct->resolveBasePrice($lockedThread->region),
                        ]];
                    }
                }

                $newOrderGroupId = 'COD-RESCUE-' . Str::upper(Str::random(6));
                $orderTotal = 0.0;
                $primaryProduct = null;

                foreach ($lineItems as $item) {
                    $product = null;
                    if (!empty($item['ecommerce_product_id'])) {
                        $product = EcommerceProduct::lockForUpdate()->find((int) $item['ecommerce_product_id']);
                    }
                    if (!$product && !empty($item['title'])) {
                        $title = trim((string) $item['title']);
                        $product = EcommerceProduct::lockForUpdate()
                            ->whereRaw('LOWER(name) = ?', [strtolower($title)])
                            ->orWhere('name', 'like', '%' . $title . '%')
                            ->first();
                    }
                    if (!$product && $lockedThread->offered_product_id) {
                        $product = EcommerceProduct::lockForUpdate()->find($lockedThread->offered_product_id);
                    }
                    if (!$product) {
                        $product = EcommerceProduct::lockForUpdate()->orderBy('id')->first();
                    }
                    if (!$product) {
                        continue;
                    }

                    if (!$primaryProduct) {
                        $primaryProduct = $product;
                    }

                    $qty = max(1, (int) ($item['quantity'] ?? 1));
                    $unitPrice = (float) ($item['price'] ?? 0);
                    if ($unitPrice <= 0) {
                        $unitPrice = $product->resolveBasePrice($lockedThread->region);
                    }
                    $lineTotal = round($unitPrice * $qty, 2);
                    $orderTotal = round($orderTotal + $lineTotal, 2);

                    $pricingModel = EcommercePricingModel::where('ecommerce_product_id', $product->id)->first();
                    $unitCogs = $pricingModel?->bomUnitCost() ?? 0.0;

                    EcommerceSale::create([
                        'ecommerce_product_id' => $product->id,
                        'ecommerce_customer_id' => $lockedThread->ecommerce_customer_id,
                        'reference_id' => $newOrderGroupId,
                        'order_group_id' => $newOrderGroupId,
                        'customer_name' => $checkout?->customer_name ?: $lockedThread->customer?->name ?: 'Valued Guest',
                        'customer_phone' => $checkout?->phone ?: $lockedThread->customer?->phone,
                        'quantity' => $qty,
                        'channel' => $channel,
                        'order_status' => 'pending',
                        'payment_status' => 'unpaid_cod',
                        'stock_deducted' => true,
                        'is_ai_upsell' => false,
                        'cod_confirmed_at' => now(),
                        'reason' => $wasInboundColdChat
                            ? 'AI WhatsApp Cold Chat Direct COD Order'
                            : 'AI WhatsApp Playbook 4 Abandoned Cart Converted to COD Order',
                        'delivery_notes' => $lockedThread->delivery_notes,
                        'unit_price' => $unitPrice,
                        'total_price' => $lineTotal,
                        'unit_cogs' => $unitCogs,
                        'unit_liquid_cost' => (float) ($pricingModel->liquid_cost ?? 0),
                        'unit_bottle_cost' => (float) ($pricingModel->bottle_cost ?? 0),
                        'unit_label_cost' => (float) ($pricingModel->label_cost ?? 0),
                        'unit_pump_cost' => (float) ($pricingModel->pump_cost ?? 0),
                        'unit_outer_box_cost' => (float) ($pricingModel->box_cost ?? 0),
                        'shipping_cost' => 0,
                        'tax_amount' => 0,
                        'meta_ad_allocation' => 0,
                    ]);

                    $product->{$stockField} = max(0.0, (float) $product->{$stockField} - $qty);
                    $product->{$soldField} = (float) $product->{$soldField} + $qty;
                    $product->save();
                }

                if ($checkout) {
                    $checkout->update([
                        'status' => 'recovered',
                        'recovered_at' => now(),
                        'recovered_order_group_id' => $newOrderGroupId,
                    ]);
                }

                // Resolve complementary product for Open-Box Upgrade pitch
                $createdOrderSales = EcommerceSale::with(['product.productLine', 'product.pricingModel'])
                    ->where('order_group_id', $newOrderGroupId)
                    ->get();

                $compOffer = $this->resolveComplementaryOffer($createdOrderSales, $lockedThread->region, $settings);
                $compProduct = $compOffer['offered_product'] ?? null;
                $compAddonPrice = (float) ($compOffer['offered_addon_price'] ?? 0);
                $compBasePrice = (float) ($compOffer['base_price'] ?? 0);
                $compDiscountPct = (float) ($compOffer['discount_percent'] ?? 20.0);

                $lockedThread->order_group_id = $newOrderGroupId;
                $lockedThread->playbook = 'cod_upsell';
                $lockedThread->state = 'cod_confirmed_upsell_pitched';
                $lockedThread->offered_product_id = $compProduct?->id ?? $lockedThread->offered_product_id;
                $lockedThread->offered_bundle_id = $compOffer['offered_bundle']?->id ?? null;
                $lockedThread->offered_addon_price = $compAddonPrice > 0 ? $compAddonPrice : $lockedThread->offered_addon_price;
                $lockedThread->offered_discount_percent = $compDiscountPct;
                if (!$wasInboundColdChat) {
                    $lockedThread->upsell_revenue_generated = round((float) $lockedThread->upsell_revenue_generated + $orderTotal, 2);
                }
                $lockedThread->unread_count = 0;
                $lockedThread->save();

                $thread->order_group_id = $newOrderGroupId;
                $thread->playbook = 'cod_upsell';
                $thread->state = 'cod_confirmed_upsell_pitched';
                $thread->offered_product_id = $lockedThread->offered_product_id;
                $thread->offered_bundle_id = $lockedThread->offered_bundle_id;
                $thread->offered_addon_price = $lockedThread->offered_addon_price;
                $thread->offered_discount_percent = $compDiscountPct;
                $thread->upsell_revenue_generated = $lockedThread->upsell_revenue_generated;

                if ($lockedThread->ecommerce_customer_id) {
                    EcommerceCustomer::lockForUpdate()->find($lockedThread->ecommerce_customer_id)?->recalculateLtv();
                }

                $fmtTotal = number_format($orderTotal, 0);
                $eventMessageBody = $wasInboundColdChat
                    ? "🛍️ New WhatsApp COD Order Created! Order #{$newOrderGroupId} ({$currency} {$fmtTotal})"
                    : "🎉 Abandoned Checkout Recovered! Order #{$newOrderGroupId} ({$currency} {$fmtTotal})";

                $systemEvent = EcommerceWhatsappMessage::create([
                    'thread_id' => $lockedThread->id,
                    'direction' => 'outbound',
                    'sender_type' => 'system_event',
                    'delivery_status' => 'sent',
                    'message_body' => $eventMessageBody,
                ]);

                // Build Ezra + Hormozi + Elliott Open-Box Upgrade / 1-Pivot pitch
                $customerName = $lockedThread->customer?->name ?: 'there';
                $shortNewRef = EcommerceWhatsappThread::formatShortOrderRef($newOrderGroupId);
                $createdSummary = $createdOrderSales->isNotEmpty()
                    ? $createdOrderSales->map(fn (EcommerceSale $s) => $s->quantity . 'x ' . ($s->product?->name ?? 'Product'))->implode(', ')
                    : ('1x ' . ($primaryProduct?->name ?? 'Hunza Apricot Oil'));
                $heroName = $primaryProduct?->name ?? 'Hunza Apricot Oil';
                $heroBasePrice = $primaryProduct ? $primaryProduct->resolveBasePrice($lockedThread->region) : $orderTotal;
                $heroDiscountPrice = max(1.0, round($heroBasePrice * (1 - ($compDiscountPct / 100)), 2));

                if ($compProduct && $compAddonPrice > 0) {
                    $compName = $compProduct->name;
                    $fmtCompAddon = number_format($compAddonPrice, 0);
                    $fmtCompBase = number_format($compBasePrice, 0);
                    $fmtCompPct = number_format($compDiscountPct, 0);
                    $fmtHeroDisc = number_format($heroDiscountPrice, 0);
                    $costPerWash = $isQatar ? 'QAR 1.5' : 'PKR 40';
                    $addressBadge = $shippingAddress !== '' ? " (📍 Delivery Address: *{$shippingAddress}*)" : '';

                    $overrideReply = "JazakAllah {$customerName}! 🎉 I have booked your Cash-on-Delivery order {$shortNewRef} for *{$createdSummary}* (Total: *{$currency} {$fmtTotal}*){$addressBadge} right here in WhatsApp!\n\n"
                        . "Most women in our Laleen community pair it with *{$compName}* because commercial sulfates block pure botanicals from absorbing deeply into the roots—and since your box for {$shortNewRef} is open on our packing table right now with courier shipping already covered, I can slip in *1x {$compName}* at *{$fmtCompPct}% OFF* (*+{$currency} {$fmtCompAddon}* instead of {$currency} {$fmtCompBase} — under {$costPerWash}/wash across 45–60 washes, *ZERO extra shipping fee*), or add a *2nd bottle of {$heroName} at {$fmtCompPct}% OFF* (*+{$currency} {$fmtHeroDisc}*) for family! Reply *UPGRADE* (or tell me how many bottles to add to your box), or *NO THANKS* to ship as-is ✨";
                }
            });
        } elseif ($action === 'execute_order_upgrade') {
            $qty = max(1, min(5, (int) ($aiDecision['requested_quantity'] ?? 1)));

            DB::transaction(function () use ($thread, $orderSales, $settings, $currency, $qty, $customerText, &$systemEvent, &$overrideReply) {
                $lockedThread = EcommerceWhatsappThread::lockForUpdate()->find($thread->id);
                if (!$lockedThread) {
                    return;
                }

                $isQatar = strtoupper($lockedThread->region) === 'QA';
                $stockField = $isQatar ? 'stock_qatar' : 'stock_pakistan';
                $soldField = $isQatar ? 'sold_qatar' : 'sold_pakistan';

                $liveOrderSales = ($lockedThread->order_group_id && !EcommerceWhatsappThread::isColdChatOrderRef($lockedThread->order_group_id))
                    ? EcommerceSale::with(['product.productLine', 'product.pricingModel'])
                        ->where('order_group_id', $lockedThread->order_group_id)
                        ->orderBy('id')
                        ->get()
                    : $orderSales;

                $firstSale = $liveOrderSales->first();
                if ($firstSale && !in_array($firstSale->order_status, ['pending', 'shipped'], true)) {
                    return;
                }

                $lowerMsg = strtolower($customerText);
                $product = null;

                // If customer explicitly specified shampoo or oil in their upgrade message, match that product
                if (preg_match('/\bshampoo\b/i', $lowerMsg) && !preg_match('/\boil\b/i', $lowerMsg)) {
                    $product = EcommerceProduct::lockForUpdate()
                        ->where('name', 'like', '%shampoo%')
                        ->orderBy('id')
                        ->first();
                } elseif (preg_match('/\boil\b/i', $lowerMsg) && !preg_match('/\bshampoo\b/i', $lowerMsg)) {
                    $product = EcommerceProduct::lockForUpdate()
                        ->where('name', 'like', '%oil%')
                        ->orderBy('id')
                        ->first();
                }

                if (!$product && $lockedThread->offered_product_id) {
                    $product = EcommerceProduct::lockForUpdate()->find($lockedThread->offered_product_id);
                }

                if (!$product) {
                    $fallbackOffer = $this->resolveComplementaryOffer($liveOrderSales, $lockedThread->region, $settings);
                    if ($fallbackOffer['offered_product']) {
                        $product = EcommerceProduct::lockForUpdate()->find($fallbackOffer['offered_product']->id);
                    }
                }

                if (!$product || (float) $product->{$stockField} < $qty) {
                    $lockedThread->state = 'upsell_declined';
                    $lockedThread->save();
                    $thread->state = 'upsell_declined';

                    $overrideReply = "So sorry {$thread->customer?->name}—we don't have {$qty}x unit(s) of {$product?->name} left in warehouse stock right now! Your original order is confirmed and heading to dispatch right away ✨";
                    return;
                }

                $discountPct = max(0.0, min(90.0, (float) ($settings->upsell_discount_percent ?? 20.0)));
                if ((int) $lockedThread->offered_product_id === (int) $product->id && (float) ($lockedThread->offered_addon_price ?? 0) > 0) {
                    $addonPrice = round((float) $lockedThread->offered_addon_price, 2);
                } else {
                    $basePrice = $product->resolveBasePrice($lockedThread->region);
                    $addonPrice = max(1.0, round($basePrice * (1 - ($discountPct / 100)), 2));
                }

                $lockedThread->offered_product_id = $product->id;
                $lockedThread->offered_addon_price = $addonPrice;

                $totalUpsellPrice = round($addonPrice * $qty, 2);

                $pricingModel = EcommercePricingModel::where('ecommerce_product_id', $product->id)->first();
                $unitCogs = $pricingModel?->bomUnitCost() ?? 0.0;

                $targetOrderGroupId = ($lockedThread->order_group_id && !EcommerceWhatsappThread::isColdChatOrderRef($lockedThread->order_group_id))
                    ? $lockedThread->order_group_id
                    : ('AI-CROSS-SELL-' . Str::upper(Str::random(8)));
                $channel = $firstSale?->channel ?: ($isQatar ? 'Organic (Qatar)' : 'Shopify (Pakistan)');
                $orderStatus = $firstSale && in_array($firstSale->order_status, ['pending', 'shipped'], true)
                    ? $firstSale->order_status
                    : 'pending';
                $paymentStatus = $firstSale?->payment_status ?: 'unpaid_cod';

                EcommerceSale::create([
                    'ecommerce_product_id' => $product->id,
                    'ecommerce_bundle_id' => $lockedThread->offered_bundle_id,
                    'ecommerce_customer_id' => $lockedThread->ecommerce_customer_id,
                    'shopify_order_id' => $firstSale?->shopify_order_id,
                    'reference_id' => $firstSale?->reference_id ?: ('AI-UPSELL-' . $lockedThread->id),
                    'order_group_id' => $targetOrderGroupId,
                    'customer_name' => $firstSale?->customer_name ?: $thread->customer?->name,
                    'customer_phone' => $firstSale?->customer_phone ?: $thread->customer?->phone,
                    'quantity' => $qty,
                    'channel' => $channel,
                    'order_status' => $orderStatus,
                    'payment_status' => $paymentStatus,
                    'stock_deducted' => true,
                    'is_ai_upsell' => true,
                    'cod_confirmed_at' => now(),
                    'reason' => 'AI WhatsApp Agent Pre-Shipment Box Upgrade',
                    'delivery_notes' => $lockedThread->delivery_notes ?: $firstSale?->delivery_notes,
                    'unit_price' => $addonPrice,
                    'total_price' => $totalUpsellPrice,
                    'unit_cogs' => $unitCogs,
                    'unit_liquid_cost' => (float) ($pricingModel->liquid_cost ?? 0),
                    'unit_bottle_cost' => (float) ($pricingModel->bottle_cost ?? 0),
                    'unit_label_cost' => (float) ($pricingModel->label_cost ?? 0),
                    'unit_pump_cost' => (float) ($pricingModel->pump_cost ?? 0),
                    'unit_outer_box_cost' => (float) ($pricingModel->box_cost ?? 0),
                    'shipping_cost' => 0,
                    'tax_amount' => 0,
                    'meta_ad_allocation' => 0,
                ]);

                EcommerceSale::where('order_group_id', $targetOrderGroupId)
                    ->whereNull('cod_confirmed_at')
                    ->update(['cod_confirmed_at' => now()]);

                $product->{$stockField} = max(0.0, (float) $product->{$stockField} - $qty);
                $product->{$soldField} = (float) $product->{$soldField} + $qty;
                $product->save();

                $lockedThread->order_group_id = $targetOrderGroupId;
                $lockedThread->upsell_revenue_generated = round((float) $lockedThread->upsell_revenue_generated + $totalUpsellPrice, 2);
                $lockedThread->state = 'upsell_accepted';
                $lockedThread->unread_count = 0;
                $lockedThread->save();

                $thread->order_group_id = $targetOrderGroupId;
                $thread->offered_product_id = $product->id;
                $thread->offered_addon_price = $addonPrice;
                $thread->upsell_revenue_generated = $lockedThread->upsell_revenue_generated;
                $thread->state = 'upsell_accepted';

                if ($lockedThread->ecommerce_customer_id) {
                    $customer = EcommerceCustomer::lockForUpdate()->find($lockedThread->ecommerce_customer_id);
                    $customer?->recalculateLtv();
                }

                $allUpdatedRows = EcommerceSale::with('product')
                    ->where('order_group_id', $targetOrderGroupId)
                    ->orderBy('id')
                    ->get();
                $combinedOrderTotal = round(
                    (float) $allUpdatedRows->sum('total_price')
                    + (float) $allUpdatedRows->sum('shipping_cost')
                    + (float) $allUpdatedRows->sum('tax_amount'),
                    2
                );

                $formattedPrice = number_format($totalUpsellPrice, 2);
                $fmtUpsellNoDec = number_format($totalUpsellPrice, 0);
                $fmtCombinedNoDec = number_format($combinedOrderTotal, 0);
                $fmtDiscountPct = number_format($discountPct, 0);
                $shortOrderRef = EcommerceWhatsappThread::formatShortOrderRef($targetOrderGroupId);
                $qtyPrefix = $qty > 1 ? "{$qty}x " : '';
                $customerName = $lockedThread->customer?->name ?: 'there';

                $overrideReply = "Wonderful choice, {$customerName}! 🎉 I've just added *{$qty}x {$product->name}* (+{$currency} {$fmtUpsellNoDec} at {$fmtDiscountPct}% OFF with FREE shipping) straight into your open box for Order {$shortOrderRef}!\n\nYour updated Cash-on-Delivery total is *{$currency} {$fmtCombinedNoDec}*, and our warehouse team is sealing your parcel for dispatch today ✨";

                $systemEvent = EcommerceWhatsappMessage::create([
                    'thread_id' => $lockedThread->id,
                    'direction' => 'outbound',
                    'sender_type' => 'system_event',
                    'delivery_status' => 'sent',
                    'message_body' => "⚡ AI Agent automatically added {$qtyPrefix}{$product->name} (+{$currency} {$formattedPrice}) to Order {$shortOrderRef}!",
                ]);
            });
        } elseif ($action === 'revert_upsell') {
            DB::transaction(function () use ($thread, $currency, $shortRef, &$systemEvent, &$overrideReply) {
                $lockedThread = EcommerceWhatsappThread::lockForUpdate()->find($thread->id);
                if (!$lockedThread || !$lockedThread->order_group_id) {
                    return;
                }

                $isQatar = strtoupper($lockedThread->region) === 'QA';
                $stockField = $isQatar ? 'stock_qatar' : 'stock_pakistan';
                $soldField = $isQatar ? 'sold_qatar' : 'sold_pakistan';

                $allOrderRows = EcommerceSale::with('product')
                    ->lockForUpdate()
                    ->where('order_group_id', $lockedThread->order_group_id)
                    ->orderBy('id')
                    ->get();

                $baseRows = $allOrderRows->where('is_ai_upsell', false);
                $upsellRows = $allOrderRows->where('is_ai_upsell', true);

                // Safety Guardrail: Never delete the entire order! Ensure at least 1 base order row remains intact.
                if ($baseRows->isEmpty() && $allOrderRows->isNotEmpty()) {
                    $primaryRow = $allOrderRows->first();
                    $primaryRow->is_ai_upsell = false;
                    $primaryRow->save();

                    $upsellRows = $allOrderRows->filter(fn (EcommerceSale $r) => $r->id !== $primaryRow->id && (bool) $r->is_ai_upsell);
                }

                $removedAnyUpsell = false;
                foreach ($upsellRows as $row) {
                    if ($row->stock_deducted && $row->ecommerce_product_id) {
                        $prod = EcommerceProduct::lockForUpdate()->find($row->ecommerce_product_id);
                        if ($prod) {
                            $prod->{$stockField} = (float) $prod->{$stockField} + (int) $row->quantity;
                            $prod->{$soldField} = max(0.0, (float) $prod->{$soldField} - (int) $row->quantity);
                            $prod->save();
                        }
                    }
                    $row->delete();
                    $removedAnyUpsell = true;
                }

                $lockedThread->upsell_revenue_generated = 0;
                $lockedThread->state = 'upsell_declined';
                $lockedThread->unread_count = 0;
                $lockedThread->save();

                $thread->upsell_revenue_generated = 0;
                $thread->state = 'upsell_declined';

                if ($lockedThread->ecommerce_customer_id) {
                    EcommerceCustomer::lockForUpdate()->find($lockedThread->ecommerce_customer_id)?->recalculateLtv();
                }

                $remainingRows = EcommerceSale::with('product')
                    ->where('order_group_id', $lockedThread->order_group_id)
                    ->orderBy('id')
                    ->get();

                $remainingTotal = round(
                    (float) $remainingRows->sum('total_price')
                    + (float) $remainingRows->sum('shipping_cost')
                    + (float) $remainingRows->sum('tax_amount'),
                    2
                );
                $fmtRemainingTotal = number_format($remainingTotal, 0);
                $remainingSummary = $remainingRows->isNotEmpty()
                    ? $remainingRows->map(fn (EcommerceSale $s) => $s->quantity . 'x ' . ($s->product?->name ?? 'Product'))->implode(', ')
                    : 'your original item';
                $customerName = $lockedThread->customer?->name ?: 'there';
                $shortOrderRef = EcommerceWhatsappThread::formatShortOrderRef($lockedThread->order_group_id);

                if ($removedAnyUpsell) {
                    $overrideReply = "All taken care of, {$customerName}! I have removed the extra upsell bottles from Order {$shortOrderRef}. Your original order for *{$remainingSummary}* (Total: *{$currency} {$fmtRemainingTotal}*) is locked and ready for dispatch today ✨";
                } else {
                    $overrideReply = "All set, {$customerName}! No extra bottles are attached—your original order {$shortOrderRef} for *{$remainingSummary}* (Total: *{$currency} {$fmtRemainingTotal}*) is locked and ready for dispatch today ✨";
                }

                $systemEvent = EcommerceWhatsappMessage::create([
                    'thread_id' => $lockedThread->id,
                    'direction' => 'outbound',
                    'sender_type' => 'system_event',
                    'delivery_status' => 'sent',
                    'message_body' => "↩️ Removed AI upsell item per customer request ({$shortOrderRef})",
                ]);
            });
        } elseif ($action === 'lock_order_without_upsell') {
            $thread->state = 'upsell_declined';
            $thread->unread_count = 0;
            $thread->save();

            $systemEvent = EcommerceWhatsappMessage::create([
                'thread_id' => $thread->id,
                'direction' => 'outbound',
                'sender_type' => 'system_event',
                'delivery_status' => 'sent',
                'message_body' => "📦 Order {$shortRef} locked for dispatch without add-on",
            ]);
        } elseif ($action === 'cancel_cod_order') {
            if ($thread->order_group_id) {
                EcommerceSale::transitionOrderGroup($thread->order_group_id, 'cancelled', 'refunded');
            }
            $thread->state = 'completed';
            $thread->unread_count = 0;
            $thread->save();

            $systemEvent = EcommerceWhatsappMessage::create([
                'thread_id' => $thread->id,
                'direction' => 'outbound',
                'sender_type' => 'system_event',
                'delivery_status' => 'sent',
                'message_body' => "🛑 Order {$shortRef} cancelled & warehouse stock restored",
            ]);
        } elseif ($action === 'escalate_to_human' || ($aiDecision['sentiment'] ?? '') === 'frustrated' || ($aiDecision['detected_intent'] ?? '') === 'human_handoff') {
            $thread->state = 'human_handoff';
            $thread->save();

            $systemEvent = EcommerceWhatsappMessage::create([
                'thread_id' => $thread->id,
                'direction' => 'outbound',
                'sender_type' => 'system_event',
                'delivery_status' => 'sent',
                'message_body' => "🚨 Escalated Order {$shortRef} to Human Support",
            ]);
        } elseif ($action === 'pitch_day7_cross_sell') {
            $thread->state = 'day7_cross_sell_pitched';
            $thread->unread_count = 0;
            $thread->save();
        }

        return [
            'system_event' => $systemEvent,
            'override_reply' => $overrideReply,
        ];
    }

    /**
     * C. Outreach Scan:
     * 1. Unconfirmed COD orders from the last 48 hours without a thread -> Playbook 1
     * 2. 6-Hour Unconfirmed COD Nudge on awaiting_cod_confirm threads with no inbound reply
     * 3. Day 7 Ritual Cross-Sell candidates -> Playbook 2
     * 4. Day 30 VIP Refill candidates -> Playbook 3
     * (Skips any customer/thread in state = 'opted_out')
     */
    public function runOutreachScan(?string $region = null): array
    {
        $settings = EcommerceAiSetting::current();
        $codThreadsCreated = 0;
        $nudgesSent = 0;
        $day7ThreadsCreated = 0;
        $day30ThreadsCreated = 0;
        $abandonedThreadsCreated = 0;

        $optedOutCustomerIds = EcommerceWhatsappThread::where('state', 'opted_out')
            ->pluck('ecommerce_customer_id')
            ->filter()
            ->unique()
            ->all();

        // 0. Playbook 4: 15-Minute Abandoned Checkout WhatsApp Rescue
        if ($settings->abandoned_checkout_enabled ?? true) {
            $delayMinutes = max(1, (int) ($settings->abandoned_checkout_delay_minutes ?? 15));
            $abandonedQuery = EcommerceAbandonedCheckout::with('customer')
                ->where('status', 'abandoned')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where('abandoned_at', '<=', now()->subMinutes($delayMinutes));

            if (!empty($optedOutCustomerIds)) {
                $abandonedQuery->where(function ($q) use ($optedOutCustomerIds) {
                    $q->whereNull('ecommerce_customer_id')
                        ->orWhereNotIn('ecommerce_customer_id', $optedOutCustomerIds);
                });
            }

            if ($region !== null && trim($region) !== '') {
                $abandonedQuery->where('region', strtoupper(EcommerceSale::normalizeRegion($region)));
            }

            foreach ($abandonedQuery->get() as $checkout) {
                $thread = $this->initiateAbandonedCheckoutRescue($checkout, $settings);
                if ($thread) {
                    $abandonedThreadsCreated++;
                }
            }
        }

        // 1. Find any unpaid_cod orders from the last 48 hours without an active thread and trigger Playbook 1
        if ($settings->cod_confirmation_enabled) {
            $codQuery = EcommerceSale::query()
                ->whereIn('channel', EcommerceSale::REVENUE_CHANNELS)
                ->whereIn('order_status', ['pending', 'shipped'])
                ->where('payment_status', 'unpaid_cod')
                ->whereNull('cod_confirmed_at')
                ->where('created_at', '>=', now()->subHours(48))
                ->whereNotNull('order_group_id');

            if (!empty($optedOutCustomerIds)) {
                $codQuery->where(function ($q) use ($optedOutCustomerIds) {
                    $q->whereNull('ecommerce_customer_id')
                        ->orWhereNotIn('ecommerce_customer_id', $optedOutCustomerIds);
                });
            }

            if ($region !== null && trim($region) !== '') {
                $codQuery->forRegion($region);
            }

            $codOrderGroupIds = $codQuery->pluck('order_group_id')->unique()->values();

            foreach ($codOrderGroupIds as $groupId) {
                $alreadyHasThread = EcommerceWhatsappThread::where('order_group_id', $groupId)
                    ->whereIn('playbook', ['cod_upsell'])
                    ->exists();

                if (!$alreadyHasThread) {
                    $thread = $this->initiateCodConfirmation((string) $groupId);
                    if ($thread) {
                        $codThreadsCreated++;
                    }
                }
            }
        }

        // 2. 6-Hour Unconfirmed COD Nudge:
        // Find threads in state 'awaiting_cod_confirm' where created_at <= now()->subHours(6),
        // nudge_sent_at is null, and no inbound customer reply has been received.
        $nudgeQuery = EcommerceWhatsappThread::with(['customer', 'messages'])
            ->where('state', 'awaiting_cod_confirm')
            ->whereNull('nudge_sent_at')
            ->where('created_at', '<=', now()->subHours(6));

        if (!empty($optedOutCustomerIds)) {
            $nudgeQuery->whereNotIn('ecommerce_customer_id', $optedOutCustomerIds);
        }

        if ($region !== null && trim($region) !== '') {
            $nudgeQuery->forRegion($region);
        }

        foreach ($nudgeQuery->get() as $staleThread) {
            if ($staleThread->state === 'opted_out') {
                continue;
            }
            $hasInboundReply = $staleThread->messages->contains('direction', 'inbound');
            if ($hasInboundReply) {
                continue;
            }

            $customerName = $staleThread->customer?->name ?: 'there';
            $personaName = $settings->agent_persona_name ?: 'Zara';
            $shortRef = $staleThread->shortOrderRef();

            $nudgeBody = "Hi {$customerName}! Just a gentle reminder from {$personaName} at Laleen Rituals ✨ We are holding your parcel ({$shortRef}) at our packing table for today's courier pickup. Please reply *YES* to confirm your delivery address so we can dispatch your box today!";

            $deliveryStatus = $settings->isAutopilot() ? 'sent' : 'pending_approval';
            $nudgeMsg = EcommerceWhatsappMessage::create([
                'thread_id' => $staleThread->id,
                'direction' => 'outbound',
                'sender_type' => 'ai',
                'delivery_status' => $deliveryStatus,
                'message_body' => $nudgeBody,
                'ai_metadata' => [
                    'step' => 'cod_6h_nudge',
                    'playbook' => 'cod_upsell',
                ],
            ]);

            if ($settings->isAutopilot()) {
                $this->transport->sendOutboundMessage($nudgeMsg, $settings);
            }

            $staleThread->nudge_sent_at = now();
            $staleThread->last_message_at = now();
            $staleThread->save();
            $nudgesSent++;
        }

        // 3. Find customers in the Day 7 Cross-Sell window (delivered/realized 6–8 days ago, no repeat order, not contacted in last 7 days)
        if ($settings->day7_cross_sell_enabled) {
            $windowStart = now()->subDays(8)->startOfDay();
            $windowEnd = now()->subDays(6)->endOfDay();

            $day7SalesQuery = EcommerceSale::with(['customer', 'product.productLine', 'product.pricingModel'])
                ->realizedRevenue()
                ->whereNotNull('ecommerce_customer_id')
                ->whereBetween('created_at', [$windowStart, $windowEnd]);

            if (!empty($optedOutCustomerIds)) {
                $day7SalesQuery->whereNotIn('ecommerce_customer_id', $optedOutCustomerIds);
            }

            if ($region !== null && trim($region) !== '') {
                $day7SalesQuery->forRegion($region);
            }

            $day7SalesByCustomer = $day7SalesQuery->get()->groupBy('ecommerce_customer_id');

            foreach ($day7SalesByCustomer as $customerId => $customerWindowSales) {
                /** @var ?EcommerceCustomer $customer */
                $customer = $customerWindowSales->first()?->customer;
                if (!$customer || $customer->wasRecentlyContacted(7)) {
                    continue;
                }

                $hasNewerOrder = EcommerceSale::where('ecommerce_customer_id', $customer->id)
                    ->whereNotIn('order_status', EcommerceSale::INACTIVE_ORDER_STATUSES)
                    ->where('created_at', '>', $windowEnd)
                    ->exists();

                if ($hasNewerOrder) {
                    continue;
                }

                $alreadyHasDay7Thread = EcommerceWhatsappThread::where('ecommerce_customer_id', $customer->id)
                    ->where('playbook', 'day7_cross_sell')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->exists();

                if ($alreadyHasDay7Thread) {
                    continue;
                }

                $thread = $this->initiateDay7CrossSell($customer, $customerWindowSales, $settings);
                if ($thread) {
                    $day7ThreadsCreated++;
                }
            }
        }

        // 4. Find customers in the Day 30 Refill window (delivered/realized 28–35 days ago, no newer order, not contacted in last 7 days)
        if ($settings->day30_refill_enabled ?? true) {
            $day30Start = now()->subDays(35)->startOfDay();
            $day30End = now()->subDays(28)->endOfDay();

            $day30SalesQuery = EcommerceSale::with(['customer', 'product.productLine', 'product.pricingModel'])
                ->realizedRevenue()
                ->whereNotNull('ecommerce_customer_id')
                ->whereBetween('created_at', [$day30Start, $day30End]);

            if (!empty($optedOutCustomerIds)) {
                $day30SalesQuery->whereNotIn('ecommerce_customer_id', $optedOutCustomerIds);
            }

            if ($region !== null && trim($region) !== '') {
                $day30SalesQuery->forRegion($region);
            }

            $day30SalesByCustomer = $day30SalesQuery->get()->groupBy('ecommerce_customer_id');

            foreach ($day30SalesByCustomer as $customerId => $customerWindowSales) {
                /** @var ?EcommerceCustomer $customer */
                $customer = $customerWindowSales->first()?->customer;
                if (!$customer || $customer->wasRecentlyContacted(7)) {
                    continue;
                }

                $hasNewerOrder = EcommerceSale::where('ecommerce_customer_id', $customer->id)
                    ->whereNotIn('order_status', EcommerceSale::INACTIVE_ORDER_STATUSES)
                    ->where('created_at', '>', $day30End)
                    ->exists();

                if ($hasNewerOrder) {
                    continue;
                }

                $alreadyHasDay30Thread = EcommerceWhatsappThread::where('ecommerce_customer_id', $customer->id)
                    ->where('playbook', 'day30_refill')
                    ->where('created_at', '>=', now()->subDays(14))
                    ->exists();

                if ($alreadyHasDay30Thread) {
                    continue;
                }

                $firstSale = $customerWindowSales->first();
                $thread = $this->initiateDay30Refill($customer, $firstSale?->order_group_id, $settings);
                if ($thread) {
                    $day30ThreadsCreated++;
                }
            }
        }

        return [
            'cod_threads_created' => $codThreadsCreated,
            'nudges_sent' => $nudgesSent,
            'day7_threads_created' => $day7ThreadsCreated,
            'day30_threads_created' => $day30ThreadsCreated,
            'abandoned_threads_created' => $abandonedThreadsCreated,
            'total_created' => $codThreadsCreated + $nudgesSent + $day7ThreadsCreated + $day30ThreadsCreated + $abandonedThreadsCreated,
        ];
    }

    /**
     * Approve & dispatch a Co-Pilot draft message (optionally updating its body text first).
     */
    public function approveAndSendDraft(EcommerceWhatsappMessage $message, ?string $editedBody = null): EcommerceWhatsappMessage
    {
        if ($editedBody !== null && trim($editedBody) !== '') {
            $message->message_body = EcommerceWhatsappMessage::toWhatsappNativeFormatting(trim($editedBody));
        }

        $message->delivery_status = 'sent';
        $message->save();

        return $this->transport->sendOutboundMessage($message, EcommerceAiSetting::current());
    }
}
