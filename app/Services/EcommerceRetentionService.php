<?php

namespace App\Services;

use App\Models\EcommerceCustomer;
use App\Models\EcommerceLifecycleFlow;
use App\Models\EcommerceLifecycleLog;
use App\Models\EcommerceSale;
use App\Models\EcommerceSeoArticle;
use App\Models\EcommerceWhatsappMessage;
use App\Models\EcommerceWhatsappThread;
use App\Services\Ecommerce\EcommerceWhatsappTransportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EcommerceRetentionService
{
    public const FLOW_COD_VERIFICATION_UPSELL = 'cod_verification_upsell';
    public const FLOW_POST_PURCHASE_RITUAL_REVIEW = 'post_purchase_ritual_review';
    public const FLOW_REFILL_30D_REORDER = 'refill_30d_reorder';
    public const FLOW_WINBACK_60D = 'winback_60d';
    public const FLOW_ABANDONED_CART = 'abandoned_cart';

    /**
     * Normalize region code to uppercase 'PK' or 'QA'.
     */
    public function normalizeRegion(?string $region = 'PK'): string
    {
        $clean = strtoupper(trim((string) $region));

        return in_array($clean, ['QA', 'QAR', 'QATAR'], true) ? 'QA' : 'PK';
    }

    /**
     * Automatically seed and return the 4 core flows for PK and QA.
     */
    public function ensureDefaultFlows(string $region = 'PK'): Collection
    {
        $region = $this->normalizeRegion($region);

        $defaults = [
            [
                'flow_key' => self::FLOW_COD_VERIFICATION_UPSELL,
                'name' => 'Day 0 — Instant COD Confirm + Hair Recovery Kit Upsell',
                'channel' => 'email_and_whatsapp',
                'trigger_day' => 0,
            ],
            [
                'flow_key' => self::FLOW_POST_PURCHASE_RITUAL_REVIEW,
                'name' => 'Day 4 How-To Ritual Email + Day 10 Review / Google Maps Collector',
                'channel' => 'email_and_whatsapp',
                'trigger_day' => 4,
            ],
            [
                'flow_key' => self::FLOW_REFILL_30D_REORDER,
                'name' => 'Day 30 Empty-Bottle Refill Alert + 15% VIP Repeat Discount',
                'channel' => 'email_and_whatsapp',
                'trigger_day' => 30,
            ],
            [
                'flow_key' => self::FLOW_WINBACK_60D,
                'name' => 'Day 60 Inactive Customer Win-Back Voucher',
                'channel' => 'email_and_whatsapp',
                'trigger_day' => 60,
            ],
        ];

        $flows = collect();
        foreach ($defaults as $flowDef) {
            $flow = EcommerceLifecycleFlow::firstOrCreate(
                [
                    'region' => $region,
                    'flow_key' => $flowDef['flow_key'],
                ],
                [
                    'name' => $flowDef['name'],
                    'channel' => $flowDef['channel'],
                    'trigger_day' => $flowDef['trigger_day'],
                    'is_enabled' => true,
                    'sent_count' => 0,
                    'converted_count' => 0,
                ]
            );
            $flows->push($flow);
        }

        return $flows;
    }

    /**
     * Toggle a lifecycle flow's is_enabled status.
     */
    public function toggleFlow(int|EcommerceLifecycleFlow $flowOrId, ?bool $forceState = null): EcommerceLifecycleFlow
    {
        $flow = $flowOrId instanceof EcommerceLifecycleFlow
            ? $flowOrId
            : EcommerceLifecycleFlow::findOrFail((int) $flowOrId);

        $flow->is_enabled = $forceState !== null ? $forceState : !$flow->is_enabled;
        $flow->save();

        return $flow->fresh();
    }

    /**
     * Classify a customer's lifecycle stage based on days since last order and order count:
     * - 0–14 days: 🟢 Fresh Order (In Ritual Window)
     * - 25–45 days: 🔄 Due for 30-Day Refill
     * - 46+ days: ⚠️ 60-Day Win-Back Due
     * - 2+ orders: 💎 VIP Repeat Buyer
     */
    public function classifyCustomerLifecycle(mixed $customer): array
    {
        if (is_numeric($customer)) {
            $customer = EcommerceCustomer::find((int) $customer);
        }

        if (!$customer) {
            return [
                'key' => 'fresh_order',
                'label' => '🟢 Fresh Order (In Ritual Window)',
                'short_label' => '🟢 Active',
                'badge' => '🟢 Fresh Order (In Ritual Window)',
                'flow_key' => self::FLOW_POST_PURCHASE_RITUAL_REVIEW,
                'days_since' => 0,
                'is_vip' => false,
            ];
        }

        $ordersCount = (int) ($customer->total_orders ?? $customer->orders_count ?? 0);
        $rawLastPurchase = $customer->last_purchase_at
            ?? $customer->latest_realized_sale_at
            ?? ($customer->relationLoaded('sales') ? $customer->sales->max('created_at') : null)
            ?? $customer->created_at;
        $lastPurchase = $rawLastPurchase ? Carbon::parse($rawLastPurchase) : now();

        $daysSince = max(0, (int) $lastPurchase->startOfDay()->diffInDays(now()->startOfDay()));
        $isVip = $ordersCount >= 2;

        if ($daysSince >= 25 && $daysSince <= 45) {
            return [
                'key' => 'refill_due',
                'label' => '🔄 Due for 30-Day Refill',
                'short_label' => '🔄 Due for 30d Refill',
                'badge' => $isVip ? '🔄 Due for 30-Day Refill · 💎 VIP Repeat Buyer' : '🔄 Due for 30-Day Refill',
                'flow_key' => self::FLOW_REFILL_30D_REORDER,
                'days_since' => $daysSince,
                'is_vip' => $isVip,
            ];
        }

        if ($daysSince >= 46) {
            return [
                'key' => 'winback_due',
                'label' => '⚠️ 60-Day Win-Back Due',
                'short_label' => '⚠️ Win-Back Due',
                'badge' => $isVip ? '⚠️ 60-Day Win-Back Due · 💎 VIP Repeat Buyer' : '⚠️ 60-Day Win-Back Due',
                'flow_key' => self::FLOW_WINBACK_60D,
                'days_since' => $daysSince,
                'is_vip' => $isVip,
            ];
        }

        if ($isVip && $daysSince > 14) {
            return [
                'key' => 'vip_repeat',
                'label' => '💎 VIP Repeat Buyer',
                'short_label' => '💎 VIP Repeat',
                'badge' => '💎 VIP Repeat Buyer',
                'flow_key' => self::FLOW_REFILL_30D_REORDER,
                'days_since' => $daysSince,
                'is_vip' => true,
            ];
        }

        return [
            'key' => 'fresh_order',
            'label' => $isVip ? '💎 VIP Repeat Buyer · 🟢 Fresh Order (In Ritual Window)' : '🟢 Fresh Order (In Ritual Window)',
            'short_label' => $isVip ? '💎 VIP Repeat' : '🟢 Active',
            'badge' => $isVip ? '💎 VIP Repeat Buyer' : '🟢 Fresh Order (In Ritual Window)',
            'flow_key' => $daysSince <= 2 ? self::FLOW_COD_VERIFICATION_UPSELL : self::FLOW_POST_PURCHASE_RITUAL_REVIEW,
            'days_since' => $daysSince,
            'is_vip' => $isVip,
        ];
    }

    /**
     * Evaluate customers in the active region (PK or QA), dispatch personalized Email + WhatsApp messages
     * for all enabled flows, increment sent_count, and update last_ran_at = now().
     */
    public function runDueLifecycleFlows(string $region = 'PK'): Collection
    {
        $region = $this->normalizeRegion($region);
        $this->ensureDefaultFlows($region);

        $enabledFlows = EcommerceLifecycleFlow::where('region', $region)
            ->where('is_enabled', true)
            ->orderBy('trigger_day')
            ->get()
            ->keyBy('flow_key');

        if ($enabledFlows->isEmpty()) {
            return collect();
        }

        $customers = $this->resolveCustomersForRegion($region);

        // Pre-load last 14 days of EcommerceLifecycleLog records in 1 query to prevent N+1 checks
        $recentLogMap = [];
        $recentLogs = EcommerceLifecycleLog::where('region', $region)
            ->where('sent_at', '>=', now()->subDays(14))
            ->whereNotNull('customer_id')
            ->get(['customer_id', 'flow_key', 'channel']);

        foreach ($recentLogs as $recentLog) {
            $key = "{$recentLog->customer_id}:{$recentLog->flow_key}:" . strtolower((string) $recentLog->channel);
            $recentLogMap[$key] = true;
        }

        $dispatchedLogs = collect();

        foreach ($customers as $customer) {
            try {
                $classification = $this->classifyCustomerLifecycle($customer);
                $daysSince = $classification['days_since'];

                $matchedFlowKey = null;
                if ($daysSince <= 2 && $enabledFlows->has(self::FLOW_COD_VERIFICATION_UPSELL)) {
                    $matchedFlowKey = self::FLOW_COD_VERIFICATION_UPSELL;
                } elseif ($daysSince >= 3 && $daysSince <= 24 && $enabledFlows->has(self::FLOW_POST_PURCHASE_RITUAL_REVIEW)) {
                    $matchedFlowKey = self::FLOW_POST_PURCHASE_RITUAL_REVIEW;
                } elseif ($daysSince >= 25 && $daysSince <= 45 && $enabledFlows->has(self::FLOW_REFILL_30D_REORDER)) {
                    $matchedFlowKey = self::FLOW_REFILL_30D_REORDER;
                } elseif ($daysSince >= 46 && $enabledFlows->has(self::FLOW_WINBACK_60D)) {
                    $matchedFlowKey = self::FLOW_WINBACK_60D;
                } elseif ($enabledFlows->has($classification['flow_key'])) {
                    $matchedFlowKey = $classification['flow_key'];
                } else {
                    $matchedFlowKey = $enabledFlows->keys()->first();
                }

                if (!$matchedFlowKey || !$enabledFlows->has($matchedFlowKey)) {
                    continue;
                }

                /** @var EcommerceLifecycleFlow $flow */
                $flow = $enabledFlows->get($matchedFlowKey);
                $flowChannel = strtolower((string) $flow->channel);

                if (in_array($flowChannel, ['email_and_whatsapp', 'email'], true) && !empty($customer->email)) {
                    $dedupKey = "{$customer->id}:{$flow->flow_key}:email";
                    if (empty($recentLogMap[$dedupKey])) {
                        try {
                            $dispatchedLogs->push(
                                $this->dispatchLifecycleMessage($customer, 'email', $flow->flow_key, $region, $flow)
                            );
                            $recentLogMap[$dedupKey] = true;
                        } catch (\Throwable $e) {
                            Log::warning('EcommerceRetentionService email dispatch failed for customer #' . $customer->id . ': ' . $e->getMessage());
                        }
                    }
                }

                if (in_array($flowChannel, ['email_and_whatsapp', 'whatsapp'], true) && !empty($customer->phone)) {
                    $dedupKey = "{$customer->id}:{$flow->flow_key}:whatsapp";
                    if (empty($recentLogMap[$dedupKey])) {
                        try {
                            $dispatchedLogs->push(
                                $this->dispatchLifecycleMessage($customer, 'whatsapp', $flow->flow_key, $region, $flow)
                            );
                            $recentLogMap[$dedupKey] = true;
                        } catch (\Throwable $e) {
                            Log::warning('EcommerceRetentionService WhatsApp dispatch failed for customer #' . $customer->id . ': ' . $e->getMessage());
                        }
                    }
                }

                // If customer had neither email nor phone populated, still log a default channel entry
                if (empty($customer->email) && empty($customer->phone)) {
                    $fallbackChannel = $flowChannel === 'whatsapp' ? 'whatsapp' : 'email';
                    $dedupKey = "{$customer->id}:{$flow->flow_key}:{$fallbackChannel}";
                    if (empty($recentLogMap[$dedupKey])) {
                        try {
                            $dispatchedLogs->push(
                                $this->dispatchLifecycleMessage($customer, $fallbackChannel, $flow->flow_key, $region, $flow)
                            );
                            $recentLogMap[$dedupKey] = true;
                        } catch (\Throwable $e) {
                            Log::warning('EcommerceRetentionService fallback dispatch failed for customer #' . $customer->id . ': ' . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('EcommerceRetentionService::runDueLifecycleFlows row error for customer #' . ($customer->id ?? 'unknown') . ': ' . $e->getMessage());
            }
        }

        foreach ($enabledFlows as $flow) {
            $flow->last_ran_at = now();
            $flow->save();
        }

        return $dispatchedLogs;
    }

    /**
     * 1-Click Send Single Customer Refill / Follow-Up via 'email' or 'whatsapp'.
     */
    public function sendCustomerLifecyclePing(
        int|EcommerceCustomer $customerOrId,
        string $channel = 'whatsapp',
        string $flowKey = self::FLOW_REFILL_30D_REORDER,
        ?string $region = null
    ): EcommerceLifecycleLog {
        $customer = $customerOrId instanceof EcommerceCustomer
            ? $customerOrId
            : EcommerceCustomer::findOrFail((int) $customerOrId);

        $resolvedRegion = $this->normalizeRegion($region ?: ($customer->region ?: 'PK'));
        $this->ensureDefaultFlows($resolvedRegion);

        $cleanChannel = in_array(strtolower(trim($channel)), ['email', 'whatsapp'], true)
            ? strtolower(trim($channel))
            : 'whatsapp';

        $flow = EcommerceLifecycleFlow::where('region', $resolvedRegion)
            ->where('flow_key', $flowKey)
            ->first()
            ?: EcommerceLifecycleFlow::where('region', $resolvedRegion)->first();

        return $this->dispatchLifecycleMessage(
            $customer,
            $cleanChannel,
            $flow?->flow_key ?: $flowKey,
            $resolvedRegion,
            $flow
        );
    }

    /**
     * 1-Click Blog-to-Email Newsletter Broadcast:
     * Takes any saved/published blog article from EcommerceSeoArticle, wraps its clean HTML content + cover image
     * + direct Shopify product links (/products/apricot-oil, /products/shampoo) into a luxury Laleen Rituals HTML
     * email newsletter, dispatches it to all customer emails in that region, and logs the broadcast in ecommerce_lifecycle_logs.
     */
    public function sendBlogNewsletterBroadcast(int|EcommerceSeoArticle $articleOrId, string $region = 'PK'): Collection
    {
        $article = $articleOrId instanceof EcommerceSeoArticle
            ? $articleOrId
            : EcommerceSeoArticle::findOrFail((int) $articleOrId);

        $region = $this->normalizeRegion($region ?: $article->region);
        $this->ensureDefaultFlows($region);

        $customers = $this->resolveCustomersForRegion($region)
            ->filter(fn (EcommerceCustomer $c) => !empty($c->email));

        // If no customer has an email in the filtered set yet, fall back to all customers with emails or a regional broadcast log
        if ($customers->isEmpty()) {
            $customers = EcommerceCustomer::whereNotNull('email')
                ->where('email', '!=', '')
                ->get();
        }

        $subject = "Laleen Rituals Hair Care Guide: {$article->title}";
        $logs = collect();

        if ($customers->isEmpty()) {
            $htmlBody = $this->buildBlogNewsletterHtml($article, 'Valued Customer', $region);
            $log = EcommerceLifecycleLog::create([
                'region' => $region,
                'customer_id' => null,
                'customer_name' => "All {$region} Newsletter Subscribers",
                'customer_email' => "subscribers@laleenrituals.com",
                'customer_phone' => null,
                'flow_key' => 'blog_newsletter',
                'channel' => 'email',
                'subject' => $subject,
                'message_body' => $htmlBody,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
            $logs->push($log);

            return $logs;
        }

        foreach ($customers as $customer) {
            try {
                $customerName = $customer->name ?: 'Valued Customer';
                $htmlBody = $this->buildBlogNewsletterHtml($article, $customerName, $region);

                $this->safeSendHtmlEmail($customer->email, $subject, $htmlBody);

                $log = EcommerceLifecycleLog::create([
                    'region' => $region,
                    'customer_id' => $customer->id,
                    'customer_name' => $customerName,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone,
                    'flow_key' => 'blog_newsletter',
                    'channel' => 'email',
                    'subject' => $subject,
                    'message_body' => $htmlBody,
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                $logs->push($log);
            } catch (\Throwable $e) {
                Log::warning('EcommerceRetentionService::sendBlogNewsletterBroadcast failed for customer #' . ($customer->id ?? 'unknown') . ': ' . $e->getMessage());
            }
        }

        return $logs;
    }

    /**
     * Alias for sendBlogNewsletterBroadcast().
     */
    public function broadcastBlogNewsletter(int|EcommerceSeoArticle $articleOrId, string $region = 'PK'): Collection
    {
        return $this->sendBlogNewsletterBroadcast($articleOrId, $region);
    }

    /**
     * Resolve customers belonging to a region (PK or QA) via EcommerceCustomer::forRegion($region)
     * with eager-loaded sales and latest realized sale timestamp to prevent N+1 queries.
     */
    public function resolveCustomersForRegion(string $region = 'PK'): Collection
    {
        $region = $this->normalizeRegion($region);

        return EcommerceCustomer::forRegion($region)
            ->with(['sales.product'])
            ->withMax([
                'sales as latest_realized_sale_at' => fn ($q) => $q->realizedRevenue()->forRegion($region),
            ], 'created_at')
            ->orderByDesc('total_spend')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Build and dispatch a single Email or WhatsApp lifecycle message and log it in ecommerce_lifecycle_logs.
     */
    protected function dispatchLifecycleMessage(
        EcommerceCustomer $customer,
        string $channel,
        string $flowKey,
        string $region,
        ?EcommerceLifecycleFlow $flow = null
    ): EcommerceLifecycleLog {
        $customerName = trim((string) ($customer->name ?: 'Valued Customer'));
        $latestProduct = $customer->latest_product ?: '100% Pure Hunza Apricot Oil';
        $isQatar = $region === 'QA';

        $oilUrl = 'https://laleenrituals.com/products/apricot-oil';
        $shampooUrl = 'https://laleenrituals.com/products/shampoo';
        $bundleUrl = 'https://laleenrituals.com/discount/COMPLETE20?redirect=/collections/all';
        $refillUrl = 'https://laleenrituals.com/discount/VIPREFILL15?redirect=/collections/all';

        [$subject, $whatsappText, $emailHtml] = $this->buildFlowPayloads(
            $flowKey,
            $customerName,
            $latestProduct,
            $isQatar,
            $oilUrl,
            $shampooUrl,
            $bundleUrl,
            $refillUrl
        );

        if ($channel === 'email') {
            if (!empty($customer->email)) {
                $this->safeSendHtmlEmail($customer->email, $subject, $emailHtml);
            }
            $messageBody = $emailHtml;
        } else {
            $this->safeSendWhatsappMessage($customer, $whatsappText, $flowKey, $region);
            $messageBody = $whatsappText;
        }

        $customer->update([
            'last_retention_contacted_at' => now(),
            'last_retention_stage' => $flowKey,
        ]);

        if ($flow) {
            $flow->increment('sent_count');
            $flow->update(['last_ran_at' => now()]);
        }

        return EcommerceLifecycleLog::create([
            'region' => $region,
            'customer_id' => $customer->id,
            'customer_name' => $customerName,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'flow_key' => $flowKey,
            'channel' => $channel,
            'subject' => $subject,
            'message_body' => $messageBody,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Build subject, WhatsApp text, and luxury HTML email for each lifecycle flow.
     */
    protected function buildFlowPayloads(
        string $flowKey,
        string $customerName,
        string $latestProduct,
        bool $isQatar,
        string $oilUrl,
        string $shampooUrl,
        string $bundleUrl,
        string $refillUrl
    ): array {
        return match ($flowKey) {
            self::FLOW_COD_VERIFICATION_UPSELL => [
                "Order Confirmed for {$customerName} + 20% OFF Complete Hair Recovery Kit",
                "Assalam-o-Alaikum {$customerName}! This is Sara from Laleen Rituals. Your order for {$latestProduct} is received! Reply YES to confirm fast dispatch, or upgrade to our Complete Hair Recovery Kit (Hunza Apricot Oil + Sulfate-Free Shampoo) at 20% OFF here: {$bundleUrl}",
                $this->wrapLuxuryEmailHtml(
                    "Your Laleen Rituals Order Confirmation & VIP Kit Upgrade",
                    "<p>Assalam-o-Alaikum <strong>" . e($customerName) . "</strong>,</p>"
                    . "<p>Thank you for ordering <strong>" . e($latestProduct) . "</strong> from Laleen Rituals! Before we dispatch your parcel, you can unlock <strong>20% OFF</strong> our Complete Hair Recovery Kit by pairing your <a href=\"{$oilUrl}\">100% Pure Hunza Apricot Oil</a> with our <a href=\"{$shampooUrl}\">Sulfate-Free Botanical Shampoo</a>.</p>"
                    . "<p><a href=\"{$bundleUrl}\" style=\"display:inline-block;padding:10px 20px;background:#c9a66b;color:#1c1513;text-decoration:none;font-weight:700;border-radius:6px;\">Upgrade to Complete Kit (20% OFF - Code: COMPLETE20)</a></p>",
                    $isQatar
                ),
            ],
            self::FLOW_POST_PURCHASE_RITUAL_REVIEW => [
                "Your 3-Drop Hunza Hair Ritual & Quick Review Request | Laleen Rituals",
                "Hi {$customerName}! You are now in Day 4-10 of your {$latestProduct} ritual. Warm 3 to 4 drops between your palms before washing with our Sulfate-Free Shampoo ({$shampooUrl}). How is your scalp feeling so far? Reply with your feedback" . ($isQatar ? " or visit Laleen Beauty Salon in Old Airport Doha & Al Wakrah!" : "!"),
                $this->wrapLuxuryEmailHtml(
                    "How to Get Maximum Results from Your Laleen Hair Ritual",
                    "<p>Hi <strong>" . e($customerName) . "</strong>,</p>"
                    . "<p>To get the best root-recovery results from your <strong>" . e($latestProduct) . "</strong>, warm just 3 to 4 drops of <a href=\"{$oilUrl}\">100% Pure Hunza Apricot Oil</a> in your palms and massage into your roots 1 hour before cleansing with our <a href=\"{$shampooUrl}\">Sulfate-Free Botanical Shampoo</a>.</p>"
                    . ($isQatar ? "<p>In Doha or Al Wakrah? Book a complimentary scalp check at Laleen Beauty Salon (Old Airport Doha &amp; Al Wakrah) and share your Google Maps review!</p>" : "<p>Loving your softer, healthier hair? Reply to this email with your experience to claim a 15% thank-you voucher on your next bottle!</p>"),
                    $isQatar
                ),
            ],
            self::FLOW_WINBACK_60D => [
                "We Miss You, {$customerName}! Claim Your 20% Win-Back Hair Recovery Voucher",
                "Hi {$customerName}! It has been 60+ days since your last {$latestProduct} order from Laleen Rituals. We have reserved a special 20% OFF Win-Back voucher (Code: COMPLETE20) on our 100% Pure Hunza Apricot Oil & Sulfate-Free Shampoo kit: {$bundleUrl}",
                $this->wrapLuxuryEmailHtml(
                    "Your Exclusive 20% OFF Win-Back Invitation from Laleen Rituals",
                    "<p>Hi <strong>" . e($customerName) . "</strong>,</p>"
                    . "<p>It has been a while since your last order of <strong>" . e($latestProduct) . "</strong>, and we would love to welcome you back to your Hunza botanical hair routine.</p>"
                    . "<p>For the next 48 hours, enjoy <strong>20% OFF</strong> our <a href=\"{$oilUrl}\">100% Pure Hunza Apricot Oil</a> and <a href=\"{$shampooUrl}\">Sulfate-Free Botanical Shampoo</a> bundle.</p>"
                    . "<p><a href=\"{$bundleUrl}\" style=\"display:inline-block;padding:10px 20px;background:#c9a66b;color:#1c1513;text-decoration:none;font-weight:700;border-radius:6px;\">Claim 20% OFF Win-Back Kit (Code: COMPLETE20)</a></p>",
                    $isQatar
                ),
            ],
            default => [
                "Time for Your 30-Day Laleen Bottle Refill, {$customerName} (15% VIP Repeat Discount)",
                "Hi {$customerName}! It has been ~30 days since your {$latestProduct} order from Laleen Rituals — right when most bottles run low. Use VIP code VIPREFILL15 for 15% OFF your fresh refill today: {$refillUrl}",
                $this->wrapLuxuryEmailHtml(
                    "Your 30-Day Bottle Refill Alert + 15% VIP Repeat Discount",
                    "<p>Hi <strong>" . e($customerName) . "</strong>,</p>"
                    . "<p>Your bottle of <strong>" . e($latestProduct) . "</strong> is likely due for a fresh refill! Keep your hair roots nourished without interruption with <strong>15% OFF</strong> your repeat order.</p>"
                    . "<p>Restock your <a href=\"{$oilUrl}\">100% Pure Hunza Apricot Oil</a> and <a href=\"{$shampooUrl}\">Sulfate-Free Botanical Shampoo</a> today:</p>"
                    . "<p><a href=\"{$refillUrl}\" style=\"display:inline-block;padding:10px 20px;background:#c9a66b;color:#1c1513;text-decoration:none;font-weight:700;border-radius:6px;\">Reorder Refill at 15% OFF (Code: VIPREFILL15)</a></p>",
                    $isQatar
                ),
            ],
        };
    }

    /**
     * Build luxury HTML newsletter email for an EcommerceSeoArticle.
     */
    protected function buildBlogNewsletterHtml(EcommerceSeoArticle $article, string $customerName, string $region): string
    {
        $isQatar = $region === 'QA';
        $oilUrl = 'https://laleenrituals.com/products/apricot-oil';
        $shampooUrl = 'https://laleenrituals.com/products/shampoo';
        $bundleUrl = 'https://laleenrituals.com/discount/COMPLETE20?redirect=/collections/all';
        $coverUrl = $article->featured_image_url ?: url("/ecommerce/seo-agent/articles/{$article->id}/image");

        $articleBody = (string) ($article->article_markdown ?: '<p>' . e((string) $article->meta_description) . '</p>');

        $innerHtml = "<p>Hi <strong>" . e($customerName) . "</strong>,</p>"
            . "<p>Here is our latest Hunza botanical hair care guide prepared for you by Laleen Rituals:</p>"
            . "<div style=\"margin:16px 0;\"><img src=\"" . e($coverUrl) . "\" alt=\"" . e((string) ($article->image_alt_text ?: $article->title)) . "\" style=\"max-width:100%;border-radius:10px;display:block;\"></div>"
            . "<div style=\"line-height:1.65;\">{$articleBody}</div>"
            . "<hr style=\"border:none;border-top:1px solid #e5d8ce;margin:20px 0;\">"
            . "<p><strong>Shop the Featured Laleen Rituals Routine:</strong></p>"
            . "<ul>"
            . "<li><a href=\"{$oilUrl}\">100% Pure Cold-Pressed Hunza Apricot Oil</a></li>"
            . "<li><a href=\"{$shampooUrl}\">Sulfate-Free Botanical Shampoo</a></li>"
            . "<li><a href=\"{$bundleUrl}\">Complete Hair Recovery Kit (20% OFF with code COMPLETE20)</a></li>"
            . "</ul>";

        return $this->wrapLuxuryEmailHtml($article->title, $innerHtml, $isQatar);
    }

    /**
     * Wrap content in a clean Laleen Rituals HTML email shell.
     */
    protected function wrapLuxuryEmailHtml(string $heading, string $bodyHtml, bool $isQatar): string
    {
        $footerLocation = $isQatar
            ? 'Laleen Rituals &amp; Laleen Beauty Salon · Old Airport, Doha &amp; Al Wakrah, Qatar'
            : 'Laleen Rituals · Single-Origin Hunza Valley Botanicals · Pakistan';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$heading}</title>
</head>
<body style="margin:0;padding:24px;background-color:#1c1513;color:#f6ebe6;font-family:Georgia,serif;">
    <div style="max-width:640px;margin:0 auto;background-color:#261d1a;border:1px solid #c9a66b;border-radius:12px;padding:28px;">
        <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#c9a66b;margin-bottom:8px;">LALEEN RITUALS BOTANICALS</div>
        <h1 style="font-size:22px;color:#f6ebe6;margin-top:0;margin-bottom:16px;">{$heading}</h1>
        <div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:#f3e9e7;">
            {$bodyHtml}
        </div>
        <div style="margin-top:24px;padding-top:14px;border-top:1px solid rgba(201,166,107,0.3);font-family:Arial,sans-serif;font-size:12px;color:#b9a59e;">
            {$footerLocation}
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Send an HTML email via Laravel's Mail::html() and fall back gracefully to logging if SMTP is not configured.
     */
    protected function safeSendHtmlEmail(string $toEmail, string $subject, string $htmlBody): void
    {
        try {
            Mail::html($htmlBody, function ($message) use ($toEmail, $subject) {
                $message->to($toEmail)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::info("Laleen Lifecycle Email logged for {$toEmail} ({$subject}): " . $e->getMessage());
        }
    }

    /**
     * Dispatch a WhatsApp message via Sara's EcommerceWhatsappTransportService (and thread log) when possible.
     */
    protected function safeSendWhatsappMessage(
        EcommerceCustomer $customer,
        string $whatsappText,
        string $flowKey,
        string $region
    ): void {
        try {
            if (!empty($customer->id) && class_exists(EcommerceWhatsappThread::class) && class_exists(EcommerceWhatsappMessage::class)) {
                $thread = EcommerceWhatsappThread::firstOrCreate(
                    [
                        'ecommerce_customer_id' => $customer->id,
                        'playbook' => $flowKey,
                    ],
                    [
                        'region' => $region,
                        'status' => 'active',
                        'current_step' => 1,
                        'last_message_at' => now(),
                    ]
                );

                $msg = EcommerceWhatsappMessage::create([
                    'ecommerce_whatsapp_thread_id' => $thread->id,
                    'direction' => 'outbound',
                    'sender_type' => 'ai_agent',
                    'message_body' => $whatsappText,
                    'delivery_status' => 'queued',
                ]);

                app(EcommerceWhatsappTransportService::class)->sendOutboundMessage($msg);
            }
        } catch (\Throwable $e) {
            Log::info("Laleen Lifecycle WhatsApp logged for customer #{$customer->id}: " . $e->getMessage());
        }
    }
}
