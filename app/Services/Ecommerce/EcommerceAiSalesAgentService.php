<?php

namespace App\Services\Ecommerce;

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
                    if (!$keeper->order_group_id && $dup->order_group_id) {
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
            'day30_refill' => trim((string) ($settings->day30_discount_code ?: 'VIPREFILL15')),
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

        $step1Body = "Assalam-o-Alaikum {$customerName}! This is {$personaName} from Laleen Rituals ✨ We just received your Cash-on-Delivery order ({$orderRef}) for *{$itemsSummary}* (Total: {$currency} {$formattedTotal}). Please reply *YES* to confirm your delivery address so our warehouse team can hand-pack your box today!";

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
            ? 'warm 4–6 drops between your palms and massage gently into your scalp for 5 minutes before washing so the Vitamin E absorbs deeply into the roots.'
            : 'let the sulfate-free botanical lather sit for 60 seconds before rinsing with cool water to gently clear scalp buildup without stripping natural oils.';

        $body = "Assalam-o-Alaikum {$customerName}! This is {$personaName} from Laleen Rituals ✨ It's been a week since your *{$boughtProductName}* arrived — how is your hair feeling after your first few uses?\n\n"
            . "💡 *Week-1 Ritual Tip:* For maximum results, {$ritualTip}";

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

        $body = "Assalam-o-Alaikum {$customerName}! This is {$personaName} from Laleen Rituals ✨ It's been about a month since your *{$primaryProduct->name}* arrived — is your bottle getting down to the last few drops?\n\n"
            . "To keep your hair ritual consistent without interruption, I've unlocked your *15% OFF VIP Refill* privilege (*{$currency} {$fmtRefillPrice}* instead of {$currency} {$fmtBasePrice} with code *{$discountCode}*).\n\n"
            . "You can claim your fresh bottle directly here whenever you're ready:\n{$checkoutUrl}";

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
            ->whereIn('state', ['checkout_link_sent', 'day30_refill_pitched', 'day7_cross_sell_pitched'])
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

        if ($thread->state === 'human_handoff') {
            return [
                'inbound_message' => $inboundMsg,
                'ai_decision' => null,
                'outbound_message' => null,
                'system_event' => null,
                'thread' => $thread->fresh(['customer', 'offeredProduct', 'messages']),
            ];
        }

        $thread->loadMissing(['customer', 'offeredProduct.pricingModel', 'offeredBundle', 'messages']);

        $orderSales = $thread->order_group_id
            ? EcommerceSale::with(['product.productLine', 'product.pricingModel'])
                ->where('order_group_id', $thread->order_group_id)
                ->get()
            : collect();

        // Refresh offer if not yet populated OR if offered_addon_price is <= 0
        if (!$thread->offered_product_id || (float) $thread->offered_addon_price <= 0) {
            $overridePct = $thread->playbook === 'day30_refill' ? 15.0 : null;
            $offer = $this->resolveComplementaryOffer($orderSales, $thread->region, $settings, $overridePct);
            if ($offer['offered_product']) {
                $thread->offered_product_id = $offer['offered_product']->id;
                $thread->offered_bundle_id = $offer['offered_bundle']?->id;
                $thread->offered_addon_price = $offer['offered_addon_price'];
                $thread->save();
                $thread->load('offeredProduct.pricingModel');
            }
        }

        // Call Gemini Brain (with deterministic fallback & terminal-state guardrails)
        $aiDecision = $this->evaluateCustomerMessageWithGemini($thread, $orderSales, $customerText, $settings);

        // Execute Deterministic Database Actions based on structured output
        $actionResult = $this->executeDeterministicAction($thread, $orderSales, $aiDecision, $settings);

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
     * and apply Ezra Firestone playbook guardrails so state transitions are 100% deterministic.
     */
    protected function evaluateCustomerMessageWithGemini(
        EcommerceWhatsappThread $thread,
        Collection $orderSales,
        string $customerText,
        EcommerceAiSetting $settings
    ): array {
        $currency = $thread->currency();
        $stockField = strtoupper($thread->region) === 'QA' ? 'stock_qatar' : 'stock_pakistan';
        $offeredProduct = $thread->offeredProduct;
        $offeredStock = $offeredProduct ? (float) $offeredProduct->{$stockField} : 0.0;
        $discountPct = $thread->playbook === 'day30_refill'
            ? 15.0
            : max(0.0, min(90.0, (float) ($settings->upsell_discount_percent ?? 20.0)));

        $basePrice = $offeredProduct
            ? $offeredProduct->resolveBasePrice($thread->region)
            : ($thread->region === 'QA' ? 65.0 : 1200.0);

        $addonPrice = (float) ($thread->offered_addon_price ?? 0);
        if ($addonPrice <= 0) {
            $addonPrice = max(1.0, round($basePrice * (1 - ($discountPct / 100)), 2));
        }

        $discountCode = $this->discountCodeForPlaybook($thread->playbook, $settings);
        $checkoutUrl = $offeredProduct
            ? $offeredProduct->getShopifyCheckoutUrl($discountCode, $settings->shopify_store_domain)
            : ('https://' . ($settings->shopify_store_domain ?: 'laleenrituals.com'));

        $currentOrderTotal = round(
            (float) $orderSales->sum('total_price')
            + (float) $orderSales->sum('shipping_cost')
            + (float) $orderSales->sum('tax_amount'),
            2
        );
        $upgradedOrderTotal = round($currentOrderTotal + $addonPrice, 2);

        $orderItemsSummary = $orderSales->isNotEmpty()
            ? $orderSales->map(fn (EcommerceSale $s) => $s->quantity . 'x ' . ($s->product?->name ?? 'Product'))->implode(', ')
            : ($thread->customer?->latest_product ?? 'Laleen Hair Care');

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
            checkoutUrl: $checkoutUrl
        );

        // Hard Terminal-State Guardrail:
        // If the thread is ALREADY in ['upsell_accepted', 'upsell_declined', 'completed'],
        // return the deterministic terminal response immediately so the AI NEVER re-pitches the upsell!
        // Also return deterministic immediately if a safety escalation or first-time objection guardrail triggered.
        if (
            in_array($thread->state, ['upsell_accepted', 'upsell_declined', 'completed'], true)
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

        $historyLines = $thread->messages
            ->take(-10)
            ->map(fn (EcommerceWhatsappMessage $m) => strtoupper($m->sender_type) . ': ' . $m->message_body)
            ->implode("\n");

        $customerName = $thread->customer?->name ?: 'Valued Customer';
        $personaName = $settings->agent_persona_name ?: 'Zara';
        $offeredProductName = $offeredProduct?->name ?? 'Complementary Hair Ritual';
        $shortRef = $thread->shortOrderRef();
        $knowledgeBase = trim((string) ($settings->brand_knowledge_base ?: EcommerceAiSetting::DEFAULT_KNOWLEDGE_BASE));

        $systemPrompt = <<<PROMPT
You are {$personaName}, the warm, persuasive Ezra Firestone-trained WhatsApp Retention & Upsell Concierge for Laleen Rituals ({$thread->region} region, currency: {$currency}).

LALEEN RITUALS BOTANICAL KNOWLEDGE BASE (USE THIS TO ANSWER PRODUCT, USAGE & DELIVERY QUESTIONS CONCISELY IN 1-2 SENTENCES):
{$knowledgeBase}

LIVE CONTEXT (100% DETERMINISTIC — NEVER INVENT PRODUCTS, PRICES, LINKS, OR STOCK):
- Customer Name: {$customerName}
- Playbook: {$thread->playbook}
- Current Thread State: {$thread->state}
- Order Reference: {$shortRef}
- Ordered Items: {$orderItemsSummary}
- Current Order Total: {$currency} {$currentOrderTotal}
- Offered Product: {$offeredProductName}
- Offered Product Regular Price: {$currency} {$basePrice}
- Discounted Offer Price ({$discountPct}% OFF): {$currency} {$addonPrice}
- Exact New Order Total if Upgraded (Playbook 1 only): {$currency} {$upgradedOrderTotal}
- Direct Shopify Discount Checkout Link (Playbooks 2 & 3): {$checkoutUrl} (Code: {$discountCode})
- Warehouse Stock Available: {$offeredStock} units

5 REAL-WORLD CONVERSATIONAL TRAINING & SAFETY RULES:
1. MULTILINGUAL & ROMAN URDU / GULF FLUENCY:
   - Understand Roman Urdu seamlessly (e.g., "Haan g confirm kr dein", "Kitne din mein aayega", "Shampoo add kr dein", "Filhal sirf oil bhej dein") as well as Gulf English/Arabic phrases ("Inshallah", "Khallas").
   - If the customer writes in Roman Urdu, reply in warm, natural Pakistani English mixed with polite Roman Urdu greetings ("Jee bilkul!", "Inshallah"), keeping product names and {$currency} prices crystal clear.

2. SMART OBJECTION HANDLING ("Let me try the oil first" / "Too expensive"):
   - "I'll try the oil first" / "Filhal sirf oil bhej dein": Never argue. On their FIRST hesitation, validate them warmly ("Totally fair!"), give a 1-sentence reason why pairing works better ("The only reason we offer the Sulfate-Free Shampoo now is that regular shampoos leave sulfate buildup that blocks the Apricot Oil from absorbing deeply—plus it ships free inside your current box"), and ask once if they want the {$discountPct}% off upgrade or just the oil (`should_trigger_action`: "none"). If they decline a SECOND time, immediately trigger `lock_order_without_upsell`.
   - "Price is high / More discount?": Never invent a bigger discount or negotiate price. Explain the value per wash (concentrated botanical formula lasting 45–60 washes).

3. REALISTIC RESULTS TIMELINE:
   - Wash 1–2: Noticeably softer hair, reduced frizz, clean hydrated scalp.
   - Weeks 2–4 (used 2–3x weekly): Visible reduction in hairfall and stronger roots.

4. QATAR LOCAL CONTEXT (when region === 'QA'):
   - For Qatar customers (QAR), know that Laleen Rituals is available for same/next-day delivery across Doha, or in-person at *Laleen Beauty Salon (Old Airport & Al Wakrah branches)*.

5. HARD SAFETY & ANTI-HALLUCINATION GUARDRAILS:
   - NEVER invent products, prices, or discount codes outside the exact LIVE CONTEXT provided above.
   - If a customer reports a medical scalp disease (psoriasis, alopecia, fungal infection, eczema), severe allergy, or damaged parcel, set `detected_intent = "human_handoff"` and `should_trigger_action = "escalate_to_human"`.

6. AUTOMATIC ADDRESS & DELIVERY NOTE EXTRACTION:
   - If the customer provides an updated address, landmark, phone number, or courier timing instruction (e.g., "deliver after 5pm", "call before coming", "House 14, Street 3 near Beaconhouse"), extract it into `"extracted_delivery_note"`. Otherwise set `"extracted_delivery_note": null`.

FORMATTING RULE:
- Use single asterisks `*text*` for bolding (WhatsApp native format). Never use double asterisks `**text**`.
- Always refer to the order using the short reference `{$shortRef}`, never a long UUID.

EZRA FIRESTONE STATE MACHINE RULES:
1. PLAYBOOK 1 (`cod_upsell` — Pre-Shipment Open Box):
   - If Current Thread State is `awaiting_cod_confirm` and customer confirms ("YES", "confirm", "sure", "ok", "Haan g confirm kr dein"):
     * `detected_intent`: "confirm_cod"
     * `should_trigger_action`: "mark_cod_confirmed_and_pitch_upsell"
     * `reply_message`: Thank them warmly for confirming Order {$shortRef}! Then pitch the "Wait—Your Box Is Still Open!" upgrade: offer to slip 1x *{$offeredProductName}* into their open parcel right now for *{$discountPct}% OFF* (*{$currency} {$addonPrice}* instead of {$currency} {$basePrice}) with *FREE extra delivery*, bringing their new COD total to *{$currency} {$upgradedOrderTotal}*. Tell them to reply *UPGRADE* to add it before the courier seals the box, or *NO THANKS* to ship as-is.
   - If Current Thread State is `cod_confirmed_upsell_pitched` and customer accepts ("UPGRADE", "yes add it", "Shampoo add kr dein"):
     * `detected_intent`: "accept_upsell"
     * `should_trigger_action`: "execute_order_upgrade"
     * `reply_message`: Celebrate warmly! Confirm that 1x *{$offeredProductName}* (+{$currency} {$addonPrice}) has been added to Order {$shortRef} with free shipping, and confirm their updated total of *{$currency} {$upgradedOrderTotal}*.
   - If Current Thread State is `cod_confirmed_upsell_pitched` and customer firmly declines ("no", "no thanks", "ship as is"):
     * `detected_intent`: "decline_upsell"
     * `should_trigger_action`: "lock_order_without_upsell"

2. PLAYBOOKS 2 & 3 (`day7_cross_sell` & `day30_refill` — Post-Purchase Retention via Shopify Checkout Link):
   - CRITICAL RULE: Never auto-create a database COD order on Day 7 or Day 30! Always send the Direct Shopify Discount Checkout Link (`{$checkoutUrl}`) instead.
   - When the customer replies positively, shares how their hair feels, asks a question, or asks for the link ("Loving it so far!", "Send me the link", "YES", "UPGRADE"):
     * `detected_intent`: "accept_upsell"
     * `should_trigger_action`: "send_checkout_link"
     * `reply_message`: Answer any question concisely in 1-2 sentences using the Botanical Knowledge Base, then warmly share the *{$discountPct}% OFF* price (*{$currency} {$addonPrice}* with code *{$discountCode}*) for *{$offeredProductName}* and include the exact link: {$checkoutUrl}
   - If the customer says "Not yet" or declines on Day 7 / Day 30:
     * `detected_intent`: "decline_upsell"
     * `should_trigger_action`: "lock_order_without_upsell"

Return ONLY valid JSON matching this exact schema:
{
  "detected_intent": "confirm_cod | accept_upsell | decline_upsell | ask_question | cancel_order | human_handoff",
  "sentiment": "positive | neutral | frustrated",
  "reply_message": "Warm, Ezra-Firestone-style conversational WhatsApp reply...",
  "should_trigger_action": "none | mark_cod_confirmed_and_pitch_upsell | execute_order_upgrade | send_checkout_link | lock_order_without_upsell | cancel_cod_order | escalate_to_human | revert_upsell",
  "extracted_delivery_note": "string or null (if the customer provided an updated address, landmark, phone number, or courier timing instruction like 'deliver after 5pm')"
}
PROMPT;

        $userPrompt = "CONVERSATION HISTORY:\n{$historyLines}\n\nLATEST CUSTOMER REPLY:\n\"{$customerText}\"\n\nRespond with strict JSON.";

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

                        return [
                            'detected_intent' => $intent,
                            'sentiment' => (string) ($decoded['sentiment'] ?? $deterministic['sentiment']),
                            'reply_message' => $replyMessage,
                            'should_trigger_action' => $action,
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
     * Extract an address update, landmark, phone number, or courier timing note from a customer's message.
     */
    public function extractDeliveryNoteFromText(string $customerText): ?string
    {
        $clean = trim($customerText);
        if ($clean === '') {
            return null;
        }

        // Match timing instructions, landmarks, house/street/flat/gate/sector, or call instructions
        $patterns = [
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
     * Detect if the customer is writing in Roman Urdu.
     */
    protected function isRomanUrduMessage(string $customerText): bool
    {
        return (bool) preg_match(
            '/\b(haan|han\s+g|jee|ji\s+haan|kr\s*dein|kar\s*dein|kardein|krdo|kardo|bhej\s*dein|bhejdein|kitne\s+din|kab\s+tak|aayega|ayega|milega|shampoo\s+add|oil\s+bhej|filhal|sirf|mehenga|sasta|baal|girna|rukega)\b/i',
            $customerText
        );
    }

    /**
     * Resolve a concise 1-2 sentence answer from the Laleen Rituals Botanical Knowledge Base
     * when a customer asks about hairfall, sulfates/keratin, results timeline, price/value, usage, or delivery.
     */
    protected function resolveBotanicalKnowledgeAnswer(string $customerText, string $region): ?string
    {
        $lower = strtolower($customerText);
        $isRomanUrdu = $this->isRomanUrduMessage($customerText);
        $greetingPrefix = $isRomanUrdu ? 'Jee bilkul! ' : '';

        // Price / Extra Discount Objection ("Too expensive", "More discount?")
        if (preg_match('/\b(expensive|costly|price is high|high price|too much|more discount|extra discount|bigger discount|mehenga|kam krein|kam karein|final price|negotiate)\b/i', $lower)) {
            return $greetingPrefix . 'Because our Laleen formulas are highly concentrated cold-pressed botanicals (you only need 4–6 drops per ritual), a single bottle lasts *45–60 washes* — working out to just a few ' . (strtoupper($region) === 'QA' ? 'riyals' : 'rupees') . ' per wash while giving your hair pure salon-grade nourishment!';
        }

        // Realistic Results Timeline ("When will I see results?", "How many washes?")
        if (preg_match('/\b(how soon|how many washes|when will i see|results|timeline|how long to work|kitne din mein farak|kab tak farak|work)\b/i', $lower) && !preg_match('/\b(deliver|delivery|courier|arrive|aayega|ayega|shipping)\b/i', $lower)) {
            return $greetingPrefix . 'In *Wash 1–2*, you will feel noticeably softer hair, reduced frizz, and a clean, hydrated scalp. Within *Weeks 2–4* (used consistently 2–3x weekly), you will see a visible reduction in hairfall and stronger roots!';
        }

        // Qatar Salon / Branch Question
        if (strtoupper($region) === 'QA' && preg_match('/\b(salon|branch|branches|pickup|pick up|visit|in person|where are you|location|wakrah|old airport|doha)\b/i', $lower)) {
            return 'In Qatar, you can enjoy fast *same-day or next-day delivery* across Doha, or pick up in person at *Laleen Beauty Salon (Old Airport & Al Wakrah branches)*!';
        }

        // Delivery Timeline (English + Roman Urdu "Kitne din mein aayega")
        if (preg_match('/\b(deliver|delivery|shipping|when will|how long|days|courier|arrive|kitne din|kab tak|aayega|ayega|milega)\b/i', $lower)) {
            if (strtoupper($region) === 'QA') {
                return $greetingPrefix . 'Inshallah in Qatar, we offer fast *same-day or next-day delivery* across Doha, or you can visit us in person at *Laleen Beauty Salon (Old Airport & Al Wakrah branches)*!';
            }

            return $greetingPrefix . 'Inshallah across Pakistan, our courier delivers within *3–5 working days* right to your doorstep via Cash on Delivery!';
        }

        if (preg_match('/\b(hairfall|hair fall|shedding|thinning|roots|apricot|greasy|oily|baal)\b/i', $lower)) {
            return $greetingPrefix . 'Our *Hunza Apricot Oil* is cold-pressed and rich in Vitamin E & oleic acid — it absorbs deeply into the scalp without feeling greasy to nourish dry roots, visibly reduce hairfall within 2–4 weeks, and boost natural shine.';
        }

        if (preg_match('/\b(sulfate|sulfates|paraben|parabens|silicone|keratin|color|colour|chemical|gentle)\b/i', $lower)) {
            return $greetingPrefix . 'Our *Botanical Shampoo* is 100% free of sulfates, parabens, and harsh silicones, making it completely safe for keratin and color-treated hair while gently clearing buildup so treatment oils absorb 2x deeper.';
        }

        if (preg_match('/\b(how to use|apply|application|routine|overnight|minutes|drops|kaise lagana|tarika)\b/i', $lower)) {
            return $greetingPrefix . 'Simply warm 4–6 drops of *Hunza Apricot Oil* between your palms and massage into your scalp for 5 minutes before washing (or leave overnight 2–3x a week), then wash with our *Sulfate-Free Botanical Shampoo*.';
        }

        if (preg_match('/\b(serum|mask|frizz|humidity|heat|split ends)\b/i', $lower)) {
            return $greetingPrefix . 'Our *Hair Serum & Hair Mask* lock in moisture for all-day frizz control, heat/humidity protection, and deep weekly cuticle repair.';
        }

        return null;
    }

    /**
     * Deterministic Ezra Firestone intent & response engine — guarantees 100% reliability
     * in offline/test environments and enforces terminal-state, objection & Playbook 2/3 link rules.
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
        string $checkoutUrl
    ): array {
        $lower = strtolower(trim($customerText));
        $customerName = $thread->customer?->name ?: 'there';
        $personaName = $settings->agent_persona_name ?: 'Zara';
        $offeredName = $offeredProduct?->name ?? 'Laleen Complementary Ritual';
        $shortRef = $thread->shortOrderRef();
        $fmtCurrentTotal = number_format($currentOrderTotal, 0);
        $fmtAddonPrice = number_format($addonPrice, 0);
        $fmtBasePrice = number_format($basePrice, 0);
        $fmtUpgradedTotal = number_format($upgradedOrderTotal, 0);
        $fmtDiscountPct = number_format($discountPct, 0);
        $isRetentionPlaybook = in_array($thread->playbook, ['day7_cross_sell', 'day30_refill'], true);
        $isRomanUrdu = $this->isRomanUrduMessage($customerText);
        $botanicalAnswer = $this->resolveBotanicalKnowledgeAnswer($customerText, $thread->region);
        $extractedDeliveryNote = $this->extractDeliveryNoteFromText($customerText);

        // 1. Check for Medical Scalp Condition, Severe Allergy, Angry Customer, or Damaged Parcel -> Escalate to Human
        if (preg_match('/\b(psoriasis|eczema|alopecia|fungal|infection|bleeding|severe allergy|allergic reaction|dermatitis|medical|doctor|angry|broken|leaked|damaged|fake|scam|refund|complaint|manager|human|agent|worst|terrible)\b/i', $lower)) {
            $isMedical = (bool) preg_match('/\b(psoriasis|eczema|alopecia|fungal|infection|bleeding|severe allergy|allergic reaction|dermatitis|medical|doctor)\b/i', $lower);
            $handoffReply = $isMedical
                ? "Thank you for sharing that with us, {$customerName} 🙏 Because your scalp health and safety come first with medical or allergic conditions, I have paused our automated assistant and connected your chat directly to our Senior Hair & Care Specialist to guide you personally."
                : "I am so sorry to hear this, {$customerName} 🙏 I have paused our automated dispatch assistant and escalated Order {$shortRef} directly to our Senior Customer Care Manager. A team member will reply to you personally within minutes.";

            return [
                'detected_intent' => 'human_handoff',
                'sentiment' => 'frustrated',
                'should_trigger_action' => 'escalate_to_human',
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'ezra-playbook-engine',
                'tokens' => 0,
                'reply_message' => $handoffReply,
            ];
        }

        // 2. Check for full order cancellation intent
        if (preg_match('/\b(cancel order|cancel my order|mistake|don\'t send|do not send|wrong order|order cancel)\b/i', $lower) || $lower === 'cancel') {
            return [
                'detected_intent' => 'cancel_order',
                'sentiment' => 'neutral',
                'should_trigger_action' => 'cancel_cod_order',
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'ezra-playbook-engine',
                'tokens' => 0,
                'reply_message' => "No problem at all, {$customerName}! I have cancelled Order {$shortRef} as requested so it won't be dispatched. Whenever you're ready in the future, we'll be right here for you ✨",
            ];
        }

        // 3. TERMINAL STATES: ['upsell_accepted', 'upsell_declined', 'completed']
        if (in_array($thread->state, ['upsell_accepted', 'upsell_declined', 'completed'], true)) {
            if (
                $thread->state === 'upsell_accepted'
                && !$isRetentionPlaybook
                && preg_match('/\b(remove|revert|take off|without|cancel upgrade|remove upgrade|remove shampoo|remove oil|only original)\b/i', $lower)
            ) {
                $originalTotal = max(0.0, round($currentOrderTotal - $addonPrice, 2));
                $fmtOriginalTotal = number_format($originalTotal, 0);

                return [
                    'detected_intent' => 'decline_upsell',
                    'sentiment' => 'neutral',
                    'should_trigger_action' => 'revert_upsell',
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'ezra-playbook-engine',
                    'tokens' => 0,
                    'reply_message' => "All taken care of, {$customerName}! I have removed the extra add-on from Order {$shortRef}. Your original order (Total: *{$currency} {$fmtOriginalTotal}*) is packed and ready for dispatch today ✨",
                ];
            }

            $prefix = $botanicalAnswer ? ($botanicalAnswer . "\n\n") : '';
            $noteAck = $extractedDeliveryNote ? "I've also noted your delivery instruction (*\"{$extractedDeliveryNote}\"*). " : '';

            return [
                'detected_intent' => 'ask_question',
                'sentiment' => 'positive',
                'should_trigger_action' => 'none',
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'ezra-playbook-engine',
                'tokens' => 0,
                'reply_message' => $prefix . "Thank you, {$customerName}! {$noteAck}Your order {$shortRef} (*{$orderItemsSummary}* — Total: *{$currency} {$fmtCurrentTotal}*) is packed and ready for dispatch 📦✨ We'll share your courier tracking details as soon as it ships!",
            ];
        }

        // 4. PLAYBOOKS 2 & 3 (`day7_cross_sell` & `day30_refill`): Direct Shopify Discount Checkout Link Flow
        if ($isRetentionPlaybook) {
            if (preg_match('/\b(not yet|no|no thanks|nope|nahi|later|skip|khallas|khalas)\b/i', $lower)) {
                return [
                    'detected_intent' => 'decline_upsell',
                    'sentiment' => 'neutral',
                    'should_trigger_action' => 'lock_order_without_upsell',
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'ezra-playbook-engine',
                    'tokens' => 0,
                    'reply_message' => "No rush at all, {$customerName}! Keep enjoying your Laleen ritual, and whenever you're ready for your next bottle, just message us here and we'll take care of you ✨",
                ];
            }

            // If customer asks a botanical question on Day 7 / Day 30, answer it AND share the checkout link!
            $intro = $botanicalAnswer
                ? "{$botanicalAnswer}\n\n"
                : ($isRomanUrdu ? "Jee bilkul, {$customerName}! ✨ " : "So glad to hear from you, {$customerName}! ✨ ");

            $reply = $intro
                . "To complete your ritual with *{$offeredName}*, I've activated your *{$fmtDiscountPct}% OFF* privilege (*{$currency} {$fmtAddonPrice}* instead of {$currency} {$fmtBasePrice} using code *{$discountCode}*).\n\n"
                . "Tap your direct checkout link below to claim your bottle:\n{$checkoutUrl}";

            return [
                'detected_intent' => $botanicalAnswer ? 'ask_question' : 'accept_upsell',
                'sentiment' => 'positive',
                'should_trigger_action' => 'send_checkout_link',
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'source' => 'deterministic_playbook',
                'model' => 'ezra-playbook-engine',
                'tokens' => 0,
                'reply_message' => $reply,
            ];
        }

        // 5. PLAYBOOK 1 (`cod_upsell`) — State: awaiting_cod_confirm
        if ($thread->state === 'awaiting_cod_confirm') {
            $isCodConfirm = (bool) preg_match(
                '/\b(yes|yep|yeah|confirm|confirmed|sure|ok|okay|haan|han|jee|ji|send|dispatch|kr\s*dein|kar\s*dein|kardein|bhej\s*dein|inshallah)\b/i',
                $lower
            );

            if ($isCodConfirm) {
                $greeting = $isRomanUrdu ? "Jee bilkul, JazakAllah {$customerName}! Inshallah your" : "JazakAllah {$customerName}! Your";
                $noteLine = $extractedDeliveryNote ? " (Delivery note saved: *\"{$extractedDeliveryNote}\"*)" : '';

                if ($settings->pre_shipment_upsell_enabled && $offeredProduct && $offeredStock >= 1 && $addonPrice > 0) {
                    $reply = "{$greeting} COD order {$shortRef} for *{$orderItemsSummary}* is officially confirmed{$noteLine} and our team is opening your box right now 📦✨\n\n"
                        . "*Wait — while your box is still open on the packing table:*\n"
                        . "Because you ordered {$orderItemsSummary}, our hair specialists recommend pairing it with *{$offeredName}* for the complete 2-step ritual. Since your courier parcel is already paid for, I can slip 1x *{$offeredName}* into this exact box for *{$fmtDiscountPct}% OFF* (*{$currency} {$fmtAddonPrice}* instead of {$currency} {$fmtBasePrice}) with *ZERO extra delivery fee*!\n\n"
                        . "Your new COD total would be *{$currency} {$fmtUpgradedTotal}*. Just reply *UPGRADE* to add it to your open box, or *NO THANKS* to ship as-is!";
                } else {
                    $reply = "{$greeting} COD order {$shortRef} for *{$orderItemsSummary}* ({$currency} {$fmtCurrentTotal}) is confirmed{$noteLine} and locked for dispatch today 📦✨ We'll share your tracking details as soon as the courier picks it up!";
                }

                return [
                    'detected_intent' => 'confirm_cod',
                    'sentiment' => 'positive',
                    'should_trigger_action' => 'mark_cod_confirmed_and_pitch_upsell',
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => $isRomanUrdu || $extractedDeliveryNote !== null,
                    'source' => 'deterministic_playbook',
                    'model' => 'ezra-playbook-engine',
                    'tokens' => 0,
                    'reply_message' => $reply,
                ];
            }

            if ($botanicalAnswer) {
                return [
                    'detected_intent' => 'ask_question',
                    'sentiment' => 'positive',
                    'should_trigger_action' => 'none',
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'ezra-playbook-engine',
                    'tokens' => 0,
                    'reply_message' => "{$botanicalAnswer}\n\nPlease reply *YES* whenever you're ready to confirm Order {$shortRef} (*{$currency} {$fmtCurrentTotal}*) so our warehouse team can hand-pack your box today! ✨",
                ];
            }
        }

        // 6. PLAYBOOK 1 (`cod_upsell`) — State: cod_confirmed_upsell_pitched
        if ($thread->state === 'cod_confirmed_upsell_pitched') {
            $isAccept = (bool) preg_match(
                '/\b(upgrade|add it|yes add|add to box|yes please|sure add|add shampoo|add oil|shampoo add|oil add|book it|include it)\b/i',
                $lower
            ) || preg_match('/^(yes|yep|yeah|sure|ok|okay|haan|jee|haan g|ji haan)$/i', $lower);

            if ($isAccept) {
                $prefix = $isRomanUrdu ? "Jee bilkul, {$customerName}! 🎉 Inshallah " : "Yay! 🎉 ";

                return [
                    'detected_intent' => 'accept_upsell',
                    'sentiment' => 'positive',
                    'should_trigger_action' => 'execute_order_upgrade',
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => $isRomanUrdu || $extractedDeliveryNote !== null,
                    'source' => 'deterministic_playbook',
                    'model' => 'ezra-playbook-engine',
                    'tokens' => 0,
                    'reply_message' => "{$prefix}I've just added *1x {$offeredName}* (+{$currency} {$fmtAddonPrice} at {$fmtDiscountPct}% OFF with FREE shipping) straight into Order {$shortRef}! Your updated Cash-on-Delivery total is *{$currency} {$fmtUpgradedTotal}*. Our warehouse team is sealing your upgraded ritual box for dispatch today! ✨",
                ];
            }

            // Smart Objection Handling: "Let me try the oil first" / "Filhal sirf oil bhej dein"
            $isTryFirstObjection = (bool) preg_match(
                '/\b(try the oil first|try oil first|try it first|try first|filhal|sirf oil|only oil for now|pehle oil|test first)\b/i',
                $lower
            );

            $alreadyHandledTryFirstObjection = $thread->messages
                ->where('direction', 'outbound')
                ->contains(fn (EcommerceWhatsappMessage $m) => str_contains(strtolower((string) $m->message_body), 'sulfate buildup'));

            if ($isTryFirstObjection && !$alreadyHandledTryFirstObjection) {
                $opener = $isRomanUrdu ? "Jee bilkul, totally fair {$customerName}! ✨" : "Totally fair, {$customerName}! ✨";

                return [
                    'detected_intent' => 'ask_question',
                    'sentiment' => 'neutral',
                    'should_trigger_action' => 'none',
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'ezra-playbook-engine',
                    'tokens' => 0,
                    'reply_message' => "{$opener} The only reason we offer the *{$offeredName}* right now is that regular shampoos leave sulfate buildup that blocks the *Hunza Apricot Oil* from absorbing deeply — plus it ships *FREE* inside your current open box at *{$fmtDiscountPct}% OFF* (*+{$currency} {$fmtAddonPrice}*). Would you like me to slip in the {$fmtDiscountPct}% off upgrade, or should we ship just the oil for now?",
                ];
            }

            if (
                $isTryFirstObjection
                || preg_match('/\b(no|no thanks|nope|nahi|skip|as is|as-is|original only|just the|just ship|ship just|khallas|khalas)\b/i', $lower)
            ) {
                $closer = $isRomanUrdu ? "Jee bilkul, all set {$customerName}! Inshallah your" : "All set, {$customerName}! Your";

                return [
                    'detected_intent' => 'decline_upsell',
                    'sentiment' => 'neutral',
                    'should_trigger_action' => 'lock_order_without_upsell',
                    'extracted_delivery_note' => $extractedDeliveryNote,
                    'is_high_confidence_keyword' => true,
                    'force_deterministic' => true,
                    'source' => 'deterministic_playbook',
                    'model' => 'ezra-playbook-engine',
                    'tokens' => 0,
                    'reply_message' => "{$closer} original order {$shortRef} for *{$orderItemsSummary}* (Total: *{$currency} {$fmtCurrentTotal}*) is locked and heading to dispatch right now 📦✨ Thank you for choosing Laleen Rituals!",
                ];
            }
        }

        // 7. Botanical Knowledge Q&A or Price Question while in Playbook 1
        if ($botanicalAnswer) {
            $nextStepPrompt = $thread->state === 'cod_confirmed_upsell_pitched'
                ? "Since your box for Order {$shortRef} is still open, you can add *1x {$offeredName}* for *{$fmtDiscountPct}% OFF* (*+{$currency} {$fmtAddonPrice}*, free shipping) by replying *UPGRADE*, or reply *NO THANKS* to ship as-is! ✨"
                : "Please reply *YES* to confirm Order {$shortRef} (*{$currency} {$fmtCurrentTotal}*) for dispatch today! ✨";

            return [
                'detected_intent' => 'ask_question',
                'sentiment' => 'positive',
                'should_trigger_action' => 'none',
                'extracted_delivery_note' => $extractedDeliveryNote,
                'is_high_confidence_keyword' => true,
                'force_deterministic' => true,
                'source' => 'deterministic_playbook',
                'model' => 'ezra-playbook-engine',
                'tokens' => 0,
                'reply_message' => "{$botanicalAnswer}\n\n{$nextStepPrompt}",
            ];
        }

        return [
            'detected_intent' => 'ask_question',
            'sentiment' => 'neutral',
            'should_trigger_action' => 'none',
            'extracted_delivery_note' => $extractedDeliveryNote,
            'is_high_confidence_keyword' => false,
            'source' => 'deterministic_playbook',
            'model' => 'ezra-playbook-engine',
            'tokens' => 0,
            'reply_message' => ($offeredProduct && $addonPrice > 0)
                ? "Great question, {$customerName}! Adding *1x {$offeredName}* at your special {$fmtDiscountPct}% OFF open-box price is only *+{$currency} {$fmtAddonPrice}* (normally {$currency} {$fmtBasePrice}) with *FREE shipping*, making your complete COD total *{$currency} {$fmtUpgradedTotal}*. Just reply *UPGRADE* if you'd like me to slip it into Order {$shortRef} before dispatch! ✨"
                : "Hi {$customerName}! This is {$personaName} from Laleen Rituals ✨ Your order {$shortRef} for *{$orderItemsSummary}* is *{$currency} {$fmtCurrentTotal}*. Please let me know if you have any questions!",
        ];
    }

    /**
     * Execute deterministic database mutations based on the AI / Playbook action.
     */
    protected function executeDeterministicAction(
        EcommerceWhatsappThread $thread,
        Collection $orderSales,
        array &$aiDecision,
        EcommerceAiSetting $settings
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

        if ($action === 'mark_cod_confirmed_and_pitch_upsell') {
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

                if ($offeredProduct && (float) $thread->offered_addon_price <= 0) {
                    $pct = $thread->playbook === 'day30_refill' ? 15.0 : max(0.0, min(90.0, (float) ($settings->upsell_discount_percent ?? 20.0)));
                    $basePrice = $offeredProduct->resolveBasePrice($thread->region);
                    $thread->offered_addon_price = max(1.0, round($basePrice * (1 - ($pct / 100)), 2));
                }

                $thread->state = 'checkout_link_sent';
                $thread->unread_count = 0;
                $thread->save();

                if ($thread->customer) {
                    $thread->customer->update([
                        'last_retention_contacted_at' => now(),
                        'last_retention_stage' => $thread->playbook,
                    ]);
                }

                $fmtPrice = number_format((float) $thread->offered_addon_price, 0);
                $prodName = $offeredProduct?->name ?? 'Ritual Upgrade';

                $systemEvent = EcommerceWhatsappMessage::create([
                    'thread_id' => $thread->id,
                    'direction' => 'outbound',
                    'sender_type' => 'system_event',
                    'delivery_status' => 'sent',
                    'message_body' => "🔗 Sent {$discountCode} Shopify checkout link for {$prodName} ({$currency} {$fmtPrice})",
                ]);
            });
        } elseif ($action === 'execute_order_upgrade') {
            DB::transaction(function () use ($thread, $orderSales, $settings, $currency, &$systemEvent, &$overrideReply) {
                $lockedThread = EcommerceWhatsappThread::lockForUpdate()->find($thread->id);
                if (!$lockedThread || $lockedThread->state === 'upsell_accepted') {
                    return;
                }

                $isQatar = strtoupper($lockedThread->region) === 'QA';
                $stockField = $isQatar ? 'stock_qatar' : 'stock_pakistan';
                $soldField = $isQatar ? 'sold_qatar' : 'sold_pakistan';

                $product = $lockedThread->offered_product_id
                    ? EcommerceProduct::lockForUpdate()->find($lockedThread->offered_product_id)
                    : null;

                if (!$product || (float) $product->{$stockField} < 1) {
                    $lockedThread->state = 'upsell_declined';
                    $lockedThread->save();
                    $thread->state = 'upsell_declined';

                    $overrideReply = "So sorry {$thread->customer?->name}—our last unit of {$product?->name} just sold out a moment ago! Your original order is confirmed and heading to dispatch right away ✨";
                    return;
                }

                $addonPrice = round((float) ($lockedThread->offered_addon_price ?? 0), 2);
                if ($addonPrice <= 0) {
                    $discountPct = max(0.0, min(90.0, (float) ($settings->upsell_discount_percent ?? 20.0)));
                    $basePrice = $product->resolveBasePrice($lockedThread->region);
                    $addonPrice = max(1.0, round($basePrice * (1 - ($discountPct / 100)), 2));
                    $lockedThread->offered_addon_price = $addonPrice;
                }

                $pricingModel = EcommercePricingModel::where('ecommerce_product_id', $product->id)->first();
                $unitCogs = $pricingModel?->bomUnitCost() ?? 0.0;

                $firstSale = $orderSales->first();
                $targetOrderGroupId = $lockedThread->order_group_id ?: ('AI-CROSS-SELL-' . Str::upper(Str::random(8)));
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
                    'quantity' => 1,
                    'channel' => $channel,
                    'order_status' => $orderStatus,
                    'payment_status' => $paymentStatus,
                    'stock_deducted' => true,
                    'is_ai_upsell' => true,
                    'cod_confirmed_at' => now(),
                    'reason' => 'AI WhatsApp Agent Pre-Shipment Box Upgrade',
                    'delivery_notes' => $lockedThread->delivery_notes ?: $firstSale?->delivery_notes,
                    'unit_price' => $addonPrice,
                    'total_price' => $addonPrice,
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

                $product->{$stockField} = max(0.0, (float) $product->{$stockField} - 1);
                $product->{$soldField} = (float) $product->{$soldField} + 1;
                $product->save();

                $lockedThread->order_group_id = $targetOrderGroupId;
                $lockedThread->upsell_revenue_generated = round((float) $lockedThread->upsell_revenue_generated + $addonPrice, 2);
                $lockedThread->state = 'upsell_accepted';
                $lockedThread->unread_count = 0;
                $lockedThread->save();

                $thread->order_group_id = $targetOrderGroupId;
                $thread->offered_addon_price = $addonPrice;
                $thread->upsell_revenue_generated = $lockedThread->upsell_revenue_generated;
                $thread->state = 'upsell_accepted';

                if ($lockedThread->ecommerce_customer_id) {
                    $customer = EcommerceCustomer::lockForUpdate()->find($lockedThread->ecommerce_customer_id);
                    $customer?->recalculateLtv();
                }

                $formattedPrice = number_format($addonPrice, 2);
                $shortOrderRef = EcommerceWhatsappThread::formatShortOrderRef($targetOrderGroupId);
                $systemEvent = EcommerceWhatsappMessage::create([
                    'thread_id' => $lockedThread->id,
                    'direction' => 'outbound',
                    'sender_type' => 'system_event',
                    'delivery_status' => 'sent',
                    'message_body' => "⚡ AI Agent automatically added {$product->name} (+{$currency} {$formattedPrice}) to Order {$shortOrderRef}!",
                ]);
            });
        } elseif ($action === 'revert_upsell') {
            DB::transaction(function () use ($thread, $shortRef, &$systemEvent) {
                $lockedThread = EcommerceWhatsappThread::lockForUpdate()->find($thread->id);
                if (!$lockedThread || !$lockedThread->order_group_id) {
                    return;
                }

                $isQatar = strtoupper($lockedThread->region) === 'QA';
                $stockField = $isQatar ? 'stock_qatar' : 'stock_pakistan';
                $soldField = $isQatar ? 'sold_qatar' : 'sold_pakistan';

                $upsellRows = EcommerceSale::lockForUpdate()
                    ->where('order_group_id', $lockedThread->order_group_id)
                    ->where('is_ai_upsell', true)
                    ->get();

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

                $systemEvent = EcommerceWhatsappMessage::create([
                    'thread_id' => $lockedThread->id,
                    'direction' => 'outbound',
                    'sender_type' => 'system_event',
                    'delivery_status' => 'sent',
                    'message_body' => "↩️ Removed AI upgrade from Order {$shortRef} & restored stock",
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
     */
    public function runOutreachScan(?string $region = null): array
    {
        $settings = EcommerceAiSetting::current();
        $codThreadsCreated = 0;
        $nudgesSent = 0;
        $day7ThreadsCreated = 0;
        $day30ThreadsCreated = 0;

        // 1. Find any unpaid_cod orders from the last 48 hours without an active thread and trigger Playbook 1
        if ($settings->cod_confirmation_enabled) {
            $codQuery = EcommerceSale::query()
                ->whereIn('channel', EcommerceSale::REVENUE_CHANNELS)
                ->whereIn('order_status', ['pending', 'shipped'])
                ->where('payment_status', 'unpaid_cod')
                ->whereNull('cod_confirmed_at')
                ->where('created_at', '>=', now()->subHours(48))
                ->whereNotNull('order_group_id');

            if ($region !== null && trim($region) !== '') {
                $codQuery->forRegion($region);
            }

            $codOrderGroupIds = $codQuery->pluck('order_group_id')->unique()->values();

            foreach ($codOrderGroupIds as $groupId) {
                $alreadyHasThread = EcommerceWhatsappThread::where('order_group_id', $groupId)
                    ->where('playbook', 'cod_upsell')
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

        if ($region !== null && trim($region) !== '') {
            $nudgeQuery->forRegion($region);
        }

        foreach ($nudgeQuery->get() as $staleThread) {
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
            'total_created' => $codThreadsCreated + $nudgesSent + $day7ThreadsCreated + $day30ThreadsCreated,
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
