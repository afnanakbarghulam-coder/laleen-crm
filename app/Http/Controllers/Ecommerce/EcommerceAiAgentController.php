<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceAbandonedCheckout;
use App\Models\EcommerceAiSetting;
use App\Models\EcommerceBundle;
use App\Models\EcommerceCustomer;
use App\Models\EcommerceProduct;
use App\Models\EcommerceSale;
use App\Models\EcommerceWhatsappMessage;
use App\Models\EcommerceWhatsappThread;
use App\Services\Ecommerce\EcommerceAiSalesAgentService;
use App\Services\Ecommerce\EcommerceWhatsappTransportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EcommerceAiAgentController extends Controller
{
    public function __construct(
        protected EcommerceAiSalesAgentService $agentService,
        protected EcommerceWhatsappTransportService $transportService
    ) {
    }

    /**
     * GET /ecommerce/hub/ai-agent
     */
    public function index(Request $request)
    {
        $region = EcommerceSale::normalizeRegion($request->query('region', 'pk'));
        $regionUpper = strtoupper($region);
        $currency = EcommerceSale::currencyForRegion($region);
        $filter = (string) $request->query('filter', 'all');

        // Self-heal any historical/test threads or AI upsell sales with 0.00 price
        $this->agentService->repairZeroPriceThreadsAndSales();

        $settings = EcommerceAiSetting::current();

        // All threads in the active region for scoreboard metrics + badge counts
        $allRegionThreads = EcommerceWhatsappThread::with(['customer', 'offeredProduct', 'offeredBundle', 'messages'])
            ->where('region', $regionUpper)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        // Scoreboard Metric 1: COD Orders Confirmed by AI (Count & % Confirmation Rate)
        $codThreads = $allRegionThreads->where('playbook', 'cod_upsell');
        $confirmedStates = ['cod_confirmed_upsell_pitched', 'upsell_accepted', 'upsell_declined'];
        $codConfirmedThreads = $codThreads->filter(function (EcommerceWhatsappThread $t) use ($confirmedStates) {
            return in_array($t->state, $confirmedStates, true);
        });
        $codConfirmedCount = $codConfirmedThreads->count();
        $codTotalCount = $codThreads->count();
        $codConfirmationRate = $codTotalCount > 0
            ? round(($codConfirmedCount / $codTotalCount) * 100, 1)
            : 0.0;

        // Scoreboard Metric 2: Pre-Shipment Upsell Conversion Rate (% of confirmed COD orders that accepted upgrade)
        $upsellWonCodCount = $codConfirmedThreads->where('state', 'upsell_accepted')->count();
        $upsellConversionRate = $codConfirmedCount > 0
            ? round(($upsellWonCodCount / $codConfirmedCount) * 100, 1)
            : 0.0;

        // Scoreboard Metric 3: Extra AI Upsell Revenue Added (SUM(upsell_revenue_generated) in PKR / QAR)
        $extraUpsellRevenue = round((float) $allRegionThreads->sum('upsell_revenue_generated'), 2);
        $totalUpsellWinsCount = $allRegionThreads->filter(
            fn (EcommerceWhatsappThread $t) => $t->state === 'upsell_accepted' || (float) $t->upsell_revenue_generated > 0
        )->count();

        // Scoreboard Metric 4: Pending Co-Pilot Approvals (Count badge)
        $pendingApprovalMessagesCount = EcommerceWhatsappMessage::where('delivery_status', 'pending_approval')
            ->whereIn('thread_id', $allRegionThreads->pluck('id'))
            ->count();

        // Scoreboard Metric 5: Recovered Abandoned Carts (Playbook 4)
        $recoveredCartsQuery = EcommerceAbandonedCheckout::where('region', $regionUpper)
            ->where('status', 'recovered');
        $recoveredCartsCount = (clone $recoveredCartsQuery)->count();
        $recoveredCartsRevenue = round((float) (clone $recoveredCartsQuery)->sum('subtotal_price'), 2);

        // Filter counts for Left Column tabs (3 clean tabs: All, Needs Approval, Escalated)
        $needsApprovalCount = $allRegionThreads->filter(
            fn (EcommerceWhatsappThread $t) => $t->messages->contains('delivery_status', 'pending_approval')
        )->count();
        $escalatedCount = $allRegionThreads->where('state', 'human_handoff')->count();

        $filterCounts = [
            'all' => $allRegionThreads->count(),
            'needs_approval' => $needsApprovalCount,
            'pending_approval' => $needsApprovalCount,
            'escalated' => $escalatedCount,
            'human_handoff' => $escalatedCount,
            'upsell_pitched' => $allRegionThreads->whereIn('state', ['cod_confirmed_upsell_pitched', 'day7_cross_sell_pitched', 'checkout_link_sent'])->count(),
            'upsell_won' => $totalUpsellWinsCount,
        ];

        // Apply active filter to threads list
        $threads = match ($filter) {
            'needs_approval', 'pending_approval' => $allRegionThreads->filter(
                fn (EcommerceWhatsappThread $t) => $t->messages->contains('delivery_status', 'pending_approval')
            )->values(),
            'escalated', 'human_handoff' => $allRegionThreads->where('state', 'human_handoff')->values(),
            'upsell_pitched' => $allRegionThreads->whereIn('state', ['cod_confirmed_upsell_pitched', 'day7_cross_sell_pitched', 'checkout_link_sent'])->values(),
            'upsell_won' => $allRegionThreads->filter(
                fn (EcommerceWhatsappThread $t) => $t->state === 'upsell_accepted' || (float) $t->upsell_revenue_generated > 0
            )->values(),
            default => $allRegionThreads->values(),
        };

        $selectedThreadId = $request->filled('thread_id') ? (int) $request->query('thread_id') : null;
        $requestedOrderGroupId = $request->filled('order_group_id') ? trim((string) $request->query('order_group_id')) : null;

        $activeThread = null;
        if ($selectedThreadId) {
            $activeThread = $allRegionThreads->firstWhere('id', $selectedThreadId);
        } elseif ($requestedOrderGroupId) {
            $activeThread = $allRegionThreads->firstWhere('order_group_id', $requestedOrderGroupId);
            if (!$activeThread) {
                $orderSale = EcommerceSale::with('customer')->where('order_group_id', $requestedOrderGroupId)->first();
                if ($orderSale && $orderSale->customer) {
                    $activeThread = $allRegionThreads->firstWhere('ecommerce_customer_id', $orderSale->customer->id);
                }
            }
        }
        $activeThread = $activeThread ?? $threads->first();

        if ($activeThread && $activeThread->unread_count > 0) {
            $activeThread->unread_count = 0;
            $activeThread->save();
        }

        $activeOrderSales = collect();
        if (
            $activeThread
            && !empty($activeThread->order_group_id)
            && !str_starts_with(strtoupper((string) $activeThread->order_group_id), 'COLD-')
        ) {
            $activeOrderSales = EcommerceSale::with(['product.pricingModel', 'bundle'])
                ->where('order_group_id', $activeThread->order_group_id)
                ->orderBy('id')
                ->get();
        }

        $activeAbandonedCheckout = null;
        if ($activeThread && $activeThread->playbook === 'abandoned_checkout' && $activeOrderSales->isEmpty() && $activeThread->ecommerce_customer_id) {
            $activeAbandonedCheckout = EcommerceAbandonedCheckout::where('ecommerce_customer_id', $activeThread->ecommerce_customer_id)
                ->orderByDesc('id')
                ->first();
        }

        // In-stock regional catalog & bundles for right-hand preview when playbook === 'inbound_support'
        $stockField = $regionUpper === 'QA' ? 'stock_qatar' : 'stock_pakistan';
        $catalogProducts = EcommerceProduct::with('pricingModel')
            ->orderBy('id')
            ->get()
            ->filter(fn (EcommerceProduct $p) => (float) $p->{$stockField} >= 1)
            ->values();
        if ($catalogProducts->isEmpty()) {
            $catalogProducts = EcommerceProduct::with('pricingModel')->orderBy('id')->get();
        }
        $catalogBundles = EcommerceBundle::with('items.product')->orderBy('id')->get();

        // Recent active orders in this region so admin can manually trigger AI confirmation on any order
        $recentOrderGroups = EcommerceSale::with('product')
            ->forRegion($region)
            ->whereIn('channel', EcommerceSale::REVENUE_CHANNELS)
            ->whereNotIn('order_status', EcommerceSale::INACTIVE_ORDER_STATUSES)
            ->whereNotNull('order_group_id')
            ->orderByDesc('created_at')
            ->limit(40)
            ->get()
            ->groupBy('order_group_id')
            ->take(12);

        return view('ecommerce.ai-agent', [
            'region' => $region,
            'regionUpper' => $regionUpper,
            'currency' => $currency,
            'filter' => $filter,
            'settings' => $settings,
            'threads' => $threads,
            'activeThread' => $activeThread,
            'activeOrderSales' => $activeOrderSales,
            'activeAbandonedCheckout' => $activeAbandonedCheckout,
            'catalogProducts' => $catalogProducts,
            'catalogBundles' => $catalogBundles,
            'recentOrderGroups' => $recentOrderGroups,
            'filterCounts' => $filterCounts,
            'codConfirmedCount' => $codConfirmedCount,
            'codTotalCount' => $codTotalCount,
            'codConfirmationRate' => $codConfirmationRate,
            'upsellWonCodCount' => $upsellWonCodCount,
            'upsellConversionRate' => $upsellConversionRate,
            'extraUpsellRevenue' => $extraUpsellRevenue,
            'totalUpsellWinsCount' => $totalUpsellWinsCount,
            'pendingApprovalMessagesCount' => $pendingApprovalMessagesCount,
            'recoveredCartsCount' => $recoveredCartsCount,
            'recoveredCartsRevenue' => $recoveredCartsRevenue,
        ]);
    }

    /**
     * POST /ecommerce/hub/ai-agent/settings
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $settings = EcommerceAiSetting::current();

        // Support quick 1-click mode toggle OR full settings modal update
        if ($request->has('quick_toggle_mode')) {
            $validated = $request->validate([
                'mode' => ['required', Rule::in(EcommerceAiSetting::MODES)],
            ]);
            $settings->mode = $validated['mode'];
            $settings->save();

            $modeLabel = $settings->mode === 'autopilot' ? '⚡ Full Auto-Pilot' : '🛡️ Co-Pilot (Approve Drafts)';

            return back()->with('success', "AI Sales Agent switched to {$modeLabel}.");
        }

        $validated = $request->validate([
            'mode' => ['required', Rule::in(EcommerceAiSetting::MODES)],
            'cod_confirmation_enabled' => 'nullable|boolean',
            'pre_shipment_upsell_enabled' => 'nullable|boolean',
            'day7_cross_sell_enabled' => 'nullable|boolean',
            'day30_refill_enabled' => 'nullable|boolean',
            'abandoned_checkout_enabled' => 'nullable|boolean',
            'abandoned_checkout_delay_minutes' => 'nullable|integer|min:1|max:1440',
            'upsell_discount_percent' => 'required|numeric|min:0|max:90',
            'shopify_store_domain' => 'nullable|string|max:190',
            'day7_discount_code' => 'nullable|string|max:60',
            'day30_discount_code' => 'nullable|string|max:60',
            'brand_knowledge_base' => 'nullable|string|max:12000',
            'whatsapp_driver' => ['required', Rule::in(EcommerceAiSetting::DRIVERS)],
            'outbound_webhook_url' => 'nullable|url|max:255',
            'agent_persona_name' => 'required|string|max:60',
        ]);

        $settings->update([
            'mode' => $validated['mode'],
            'cod_confirmation_enabled' => $request->boolean('cod_confirmation_enabled'),
            'pre_shipment_upsell_enabled' => $request->boolean('pre_shipment_upsell_enabled'),
            'day7_cross_sell_enabled' => $request->boolean('day7_cross_sell_enabled'),
            'day30_refill_enabled' => $request->boolean('day30_refill_enabled'),
            'abandoned_checkout_enabled' => $request->has('abandoned_checkout_enabled') ? $request->boolean('abandoned_checkout_enabled') : ($settings->abandoned_checkout_enabled ?? true),
            'abandoned_checkout_delay_minutes' => (int) ($validated['abandoned_checkout_delay_minutes'] ?? $settings->abandoned_checkout_delay_minutes ?? 15),
            'upsell_discount_percent' => (float) $validated['upsell_discount_percent'],
            'shopify_store_domain' => trim((string) ($validated['shopify_store_domain'] ?? '')) ?: 'laleenrituals.com',
            'day7_discount_code' => strtoupper(trim((string) ($validated['day7_discount_code'] ?? ''))) ?: 'COMPLETE20',
            'day30_discount_code' => strtoupper(trim((string) ($validated['day30_discount_code'] ?? ''))) ?: 'VIPREFILL15',
            'brand_knowledge_base' => trim((string) ($validated['brand_knowledge_base'] ?? '')) ?: EcommerceAiSetting::DEFAULT_KNOWLEDGE_BASE,
            'whatsapp_driver' => $validated['whatsapp_driver'],
            'outbound_webhook_url' => $validated['outbound_webhook_url'] ?? null,
            'agent_persona_name' => trim($validated['agent_persona_name']),
        ]);

        return back()->with('success', 'AI Sales Agent settings updated.');
    }

    /**
     * POST /ecommerce/hub/ai-agent/run-scan
     */
    public function runOutreachScan(Request $request): RedirectResponse
    {
        $region = $request->input('region');
        $result = $this->agentService->runOutreachScan($region ? (string) $region : null);

        return back()->with(
            'success',
            sprintf(
                'Outreach Scan complete: %d COD confirmation(s), %d 6-hour nudge(s), %d Day-7 cross-sell(s), %d Day-30 refill(s), and %d Abandoned Cart rescue(s) processed.',
                $result['cod_threads_created'] ?? 0,
                $result['nudges_sent'] ?? 0,
                $result['day7_threads_created'] ?? 0,
                $result['day30_threads_created'] ?? 0,
                $result['abandoned_threads_created'] ?? 0
            )
        );
    }

    /**
     * POST /ecommerce/hub/ai-agent/threads/{thread}/Trigger-cod
     */
    public function triggerCod(Request $request, $thread = null): RedirectResponse
    {
        $orderGroupId = $request->input('order_group_id');
        $playbook = (string) $request->input('playbook', 'cod_upsell');
        if (!in_array($playbook, ['cod_upsell', 'day7_cross_sell', 'day30_refill', 'abandoned_checkout'], true)) {
            $playbook = 'cod_upsell';
        }

        if (!$orderGroupId && $thread !== null) {
            $threadModel = $thread instanceof EcommerceWhatsappThread
                ? $thread
                : EcommerceWhatsappThread::find($thread);

            if ($threadModel && $threadModel->order_group_id) {
                $orderGroupId = $threadModel->order_group_id;
            } elseif (is_string($thread) && $thread !== '0') {
                $orderGroupId = $thread;
            }
        }

        if (!$orderGroupId) {
            return back()->withErrors(['error' => 'Please select a valid Order Group ID to launch a playbook.']);
        }

        $orderGroupIdStr = (string) $orderGroupId;

        if (in_array($playbook, ['day7_cross_sell', 'day30_refill', 'abandoned_checkout'], true)) {
            $firstSale = EcommerceSale::with(['customer', 'product'])
                ->where('order_group_id', $orderGroupIdStr)
                ->orderBy('id')
                ->first();

            if (!$firstSale) {
                return back()->withErrors(['error' => "Order {$orderGroupIdStr} not found."]);
            }

            $region = EcommerceSale::regionForChannel($firstSale->channel);
            $regionUpper = strtoupper($region);
            $currency = EcommerceSale::currencyForRegion($region);
            $defaultCode = $region === 'qa' ? '+974' : '+92';

            $customer = $firstSale->customer;
            if (!$customer) {
                $phone = EcommerceCustomer::normalizePhone($firstSale->customer_phone, $defaultCode);
                if ($phone === '') {
                    $phone = ($region === 'qa' ? '+9745500' : '+9230000') . str_pad((string) $firstSale->id, 4, '0', STR_PAD_LEFT);
                }

                $customer = EcommerceCustomer::firstOrCreate(
                    ['phone' => $phone],
                    ['name' => $firstSale->customer_name ?: 'Valued Client']
                );
                EcommerceSale::where('order_group_id', $orderGroupIdStr)
                    ->whereNull('ecommerce_customer_id')
                    ->update(['ecommerce_customer_id' => $customer->id]);
            }

            if ($playbook === 'abandoned_checkout') {
                $checkout = EcommerceAbandonedCheckout::where('ecommerce_customer_id', $customer->id)
                    ->whereIn('status', ['abandoned', 'outreach_sent'])
                    ->orderByDesc('id')
                    ->first();

                if (!$checkout) {
                    $product = $firstSale->product;
                    $unitPrice = (float) ($firstSale->unit_price > 0 ? $firstSale->unit_price : ($product ? $product->resolveBasePrice($regionUpper) : 1200));
                    $qty = max(1, (int) $firstSale->quantity);
                    $checkoutUrl = $product ? $product->getShopifyCheckoutUrl() : 'https://laleenrituals.com/checkout';

                    $checkout = EcommerceAbandonedCheckout::create([
                        'shopify_checkout_id' => 'SIM-CHK-' . $firstSale->id . '-' . time(),
                        'shopify_checkout_token' => 'tok-' . $firstSale->id,
                        'ecommerce_customer_id' => $customer->id,
                        'customer_name' => $customer->name ?: $firstSale->customer_name,
                        'phone' => $customer->phone,
                        'region' => $regionUpper,
                        'currency' => $currency,
                        'subtotal_price' => round($unitPrice * $qty, 2),
                        'abandoned_checkout_url' => $checkoutUrl,
                        'line_items_json' => [[
                            'title' => $product?->name ?? 'Hunza Apricot Oil',
                            'quantity' => $qty,
                            'price' => $unitPrice,
                            'ecommerce_product_id' => $product?->id,
                        ]],
                        'status' => 'abandoned',
                        'abandoned_at' => now()->subMinutes(16),
                    ]);
                }

                $createdThread = $this->agentService->initiateAbandonedCheckoutRescue($checkout);
                $playbookLabel = '15-Min Abandoned Cart Rescue';
            } elseif ($playbook === 'day30_refill') {
                $createdThread = $this->agentService->initiateDay30Refill($customer, $orderGroupIdStr);
                $playbookLabel = 'Day 30 VIP Refill Link';
            } else {
                $createdThread = $this->agentService->initiateDay7CrossSell($customer, $orderGroupIdStr);
                $playbookLabel = 'Day 7 Ritual + Cross-Sell Link';
            }
        } else {
            $createdThread = $this->agentService->initiateCodConfirmation($orderGroupIdStr, true);
            $playbookLabel = 'COD Confirmation + Box Upgrade';
        }

        if (!$createdThread) {
            return back()->withErrors(['error' => "Could not initiate {$playbookLabel} for order {$orderGroupIdStr}."]);
        }

        if (EcommerceAiSetting::current()->whatsapp_driver === 'simulator') {
            $pendingDrafts = EcommerceWhatsappMessage::where('thread_id', $createdThread->id)
                ->where('delivery_status', 'pending_approval')
                ->get();
            foreach ($pendingDrafts as $draft) {
                $this->agentService->approveAndSendDraft($draft);
            }
        }

        $regionParam = strtolower($createdThread->region);

        return redirect()
            ->route('ecommerce.ai-agent.index', ['region' => $regionParam, 'thread_id' => $createdThread->id])
            ->with('success', "AI {$playbookLabel} initiated for Order {$createdThread->shortOrderRef()}.");
    }

    /**
     * POST /ecommerce/hub/ai-agent/start-cold-chat
     * Create a fresh test customer and blank inbound_support WhatsApp thread with zero scenario or order.
     */
    public function startColdCustomerChat(Request $request): RedirectResponse
    {
        $region = EcommerceSale::normalizeRegion($request->input('region', 'pk'));
        $regionUpper = strtoupper($region);

        try {
            $visitorNum = random_int(100, 999);
            $phonePrefix = $regionUpper === 'QA' ? '97455' : '92300';
            $uniquePhone = $phonePrefix . str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $visitorName = "New WhatsApp Visitor #{$visitorNum}";
            $visitorEmail = 'visitor-' . random_int(1000, 9999) . '-' . $visitorNum . '@whatsapp.local';

            $customerData = $this->filterExistingTableColumns('ecommerce_customers', [
                'name' => $visitorName,
                'phone' => $uniquePhone,
                'email' => $visitorEmail,
                'region' => $regionUpper,
                'orders_count' => 0,
                'total_orders' => 0,
                'total_spend' => 0,
            ]);

            $customer = EcommerceCustomer::create($customerData);

            $stockField = $regionUpper === 'QA' ? 'stock_qatar' : 'stock_pakistan';
            $heroProduct = EcommerceProduct::with('pricingModel')
                ->orderBy('id')
                ->get()
                ->first(fn (EcommerceProduct $p) => (float) $p->{$stockField} >= 1)
                ?? EcommerceProduct::with('pricingModel')->orderBy('id')->first();

            $coldOrderGroupId = 'COLD-' . strtoupper(Str::random(6));

            $threadData = $this->filterExistingTableColumns('ecommerce_whatsapp_threads', [
                'ecommerce_customer_id' => $customer->id,
                'order_group_id' => $coldOrderGroupId,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'region' => $regionUpper,
                'playbook' => 'inbound_support',
                'state' => 'inbound_active',
                'offered_product_id' => $heroProduct?->id,
                'offered_addon_price' => $heroProduct ? $heroProduct->resolveBasePrice($regionUpper) : 0,
                'upsell_revenue_generated' => 0,
                'unread_count' => 0,
                'last_message_at' => now(),
            ]);

            $thread = EcommerceWhatsappThread::create($threadData);

            EcommerceWhatsappMessage::create([
                'thread_id' => $thread->id,
                'direction' => 'outbound',
                'sender_type' => 'system_event',
                'delivery_status' => 'sent',
                'message_body' => '💬 Blank Cold Customer Chat started — type any message below as a new customer!',
            ]);

            return redirect()
                ->route('ecommerce.ai-agent.index', [
                    'region' => $region,
                    'thread_id' => $thread->id,
                ])
                ->with('success', "Blank Cold Customer Chat started for {$customer->name}. Type any message below!");
        } catch (\Throwable $e) {
            Log::error('startColdCustomerChat error: ' . $e->getMessage(), [
                'exception' => $e,
                'region' => $regionUpper,
            ]);

            $fallbackThread = EcommerceWhatsappThread::where('region', $regionUpper)
                ->orderByDesc('id')
                ->first();

            return redirect()
                ->route('ecommerce.ai-agent.index', array_filter([
                    'region' => $region,
                    'thread_id' => $fallbackThread?->id,
                ]))
                ->withErrors(['error' => 'Could not initialize a new blank chat: ' . $e->getMessage()]);
        }
    }

    /**
     * Filter attributes against the actual database table schema so extra/optional columns
     * never fail on servers with older or stricter schemas.
     */
    protected function filterExistingTableColumns(string $table, array $attributes): array
    {
        try {
            $columns = Schema::getColumnListing($table);
            if (!empty($columns)) {
                $lookup = array_flip($columns);

                return array_intersect_key($attributes, $lookup);
            }
        } catch (\Throwable $e) {
            // Fallback to default attributes if schema inspection fails
        }

        return $attributes;
    }

    /**
     * Alias for startColdCustomerChat().
     */
    public function startNewCustomerChat(Request $request): RedirectResponse
    {
        return $this->startColdCustomerChat($request);
    }

    /**
     * POST /ecommerce/hub/ai-agent/threads/{thread}/reset-chat
     * Wipe the current thread's messages and reset its state to test from scratch.
     */
    public function resetThreadChat(Request $request, EcommerceWhatsappThread $thread): RedirectResponse|JsonResponse
    {
        $thread->messages()->delete();

        if ($thread->playbook === 'inbound_support' || EcommerceWhatsappThread::isColdChatOrderRef($thread->order_group_id)) {
            $thread->playbook = 'inbound_support';
            $thread->order_group_id = 'COLD-' . strtoupper(Str::random(6));
            $thread->state = 'inbound_active';
            $thread->upsell_revenue_generated = 0;
            $thread->delivery_notes = null;
            $thread->unread_count = 0;
            $thread->last_message_at = now();
            $thread->save();

            $resetMsg = EcommerceWhatsappMessage::create([
                'thread_id' => $thread->id,
                'direction' => 'outbound',
                'sender_type' => 'system_event',
                'delivery_status' => 'sent',
                'message_body' => '💬 Blank Cold Customer Chat started — type any message below as a new customer!',
            ]);
        } else {
            $thread->state = match ($thread->playbook) {
                'day7_cross_sell' => 'day7_checkin_sent',
                'day30_refill' => 'day30_refill_pitched',
                'abandoned_checkout' => 'abandoned_followup_sent',
                default => 'awaiting_cod_confirm',
            };
            $thread->upsell_revenue_generated = 0;
            $thread->delivery_notes = null;
            $thread->unread_count = 0;
            $thread->last_message_at = now();
            $thread->save();

            $resetMsg = EcommerceWhatsappMessage::create([
                'thread_id' => $thread->id,
                'direction' => 'outbound',
                'sender_type' => 'system_event',
                'delivery_status' => 'sent',
                'message_body' => '🔄 Chat cleared — type any message below as a customer to test from scratch!',
            ]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($this->buildAjaxThreadPayload($thread, [$resetMsg], null, true));
        }

        return redirect()
            ->route('ecommerce.ai-agent.index', [
                'region' => strtolower($thread->region),
                'thread_id' => $thread->id,
            ])
            ->with('success', 'Conversation history cleared and reset for fresh testing.');
    }

    /**
     * POST /ecommerce/hub/ai-agent/messages/{message}/approve
     */
    public function approveMessage(Request $request, EcommerceWhatsappMessage $message): RedirectResponse
    {
        $validated = $request->validate([
            'message_body' => 'nullable|string|max:4000',
        ]);

        $this->agentService->approveAndSendDraft($message, $validated['message_body'] ?? null);

        return back()->with('success', 'Co-Pilot draft approved and dispatched via WhatsApp.');
    }

    /**
     * POST /ecommerce/hub/ai-agent/threads/{thread}/send-manual
     */
    public function sendManual(Request $request, EcommerceWhatsappThread $thread): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'message_body' => 'required|string|max:4000',
            'pause_ai' => 'nullable|boolean',
        ]);

        $msg = EcommerceWhatsappMessage::create([
            'thread_id' => $thread->id,
            'direction' => 'outbound',
            'sender_type' => 'staff',
            'delivery_status' => 'sent',
            'message_body' => trim($validated['message_body']),
        ]);

        $this->transportService->sendOutboundMessage($msg, EcommerceAiSetting::current());

        if ($request->boolean('pause_ai')) {
            $thread->state = 'human_handoff';
        }
        $thread->unread_count = 0;
        $thread->last_message_at = now();
        $thread->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($this->buildAjaxThreadPayload($thread, [], $msg));
        }

        return redirect()
            ->route('ecommerce.ai-agent.index', [
                'region' => strtolower($thread->region),
                'thread_id' => $thread->id,
            ])
            ->with('success', 'Manual WhatsApp reply sent.');
    }

    /**
     * POST /ecommerce/hub/ai-agent/threads/{thread}/simulate-inbound
     */
    public function simulateInbound(Request $request, EcommerceWhatsappThread $thread): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'customer_reply' => 'required|string|max:2000',
        ]);

        try {
            // If thread was in human_handoff and admin is simulating a customer test reply, allow resuming AI if requested
            if ($thread->state === 'human_handoff' && $request->boolean('resume_ai', true)) {
                $resumeState = match ($thread->playbook) {
                    'day7_cross_sell' => 'day7_checkin_sent',
                    'day30_refill' => 'day30_refill_pitched',
                    'abandoned_checkout' => 'abandoned_followup_sent',
                    'inbound_support' => 'inbound_active',
                    default => 'awaiting_cod_confirm',
                };

                if ($thread->playbook === 'cod_upsell' && !EcommerceWhatsappThread::isColdChatOrderRef($thread->order_group_id)) {
                    $isConfirmed = EcommerceSale::where('order_group_id', $thread->order_group_id)
                        ->whereNotNull('cod_confirmed_at')
                        ->exists();
                    if ($isConfirmed) {
                        $resumeState = 'cod_confirmed_upsell_pitched';
                    }
                }

                $thread->state = $resumeState;
                $thread->save();
            }

            $result = $this->agentService->handleInboundCustomerMessage($thread, $validated['customer_reply']);

            // Always auto-reply immediately when testing in Simulator (even if global mode is Co-Pilot)
            if (!empty($result['outbound_message']) && $result['outbound_message']->delivery_status === 'pending_approval') {
                $this->agentService->approveAndSendDraft($result['outbound_message']);
            }
            $pendingDrafts = EcommerceWhatsappMessage::where('thread_id', $thread->id)
                ->where('delivery_status', 'pending_approval')
                ->get();
            foreach ($pendingDrafts as $draft) {
                $this->agentService->approveAndSendDraft($draft);
            }

            $intent = $result['ai_decision']['detected_intent'] ?? 'processed';
            $action = $result['ai_decision']['should_trigger_action'] ?? 'none';

            if ($request->expectsJson() || $request->ajax()) {
                $appendMessages = array_values(array_filter([
                    $result['system_event'] ?? null,
                    !empty($result['outbound_message']) ? $result['outbound_message']->fresh() : null,
                ]));

                return response()->json($this->buildAjaxThreadPayload(
                    $result['thread'] ?? $thread->fresh(),
                    $appendMessages,
                    $result['inbound_message'] ?? null
                ));
            }

            return redirect()
                ->route('ecommerce.ai-agent.index', [
                    'region' => strtolower($thread->region),
                    'thread_id' => $thread->id,
                ])
                ->with('success', "Simulated customer reply processed (Intent: {$intent} | Action: {$action}).");
        } catch (\Throwable $e) {
            Log::error('simulateInbound error: ' . $e->getMessage(), [
                'thread_id' => $thread->id,
                'exception' => $e,
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'Could not process reply: ' . $e->getMessage(),
                ], 500);
            }

            return back()->withErrors(['error' => 'Could not process reply: ' . $e->getMessage()]);
        }
    }

    /**
     * Build a structured JSON payload for zero-reload AJAX chat updates.
     */
    protected function buildAjaxThreadPayload(
        EcommerceWhatsappThread $thread,
        array $appendMessages = [],
        ?EcommerceWhatsappMessage $optimisticMessage = null,
        bool $replaceTimeline = false
    ): array {
        $settings = EcommerceAiSetting::current();
        $personaName = $settings->agent_persona_name ?: 'Zara';

        $messagesHtmlParts = [];
        $structuredMessages = [];

        if ($optimisticMessage) {
            $structuredMessages[] = [
                'id' => $optimisticMessage->id,
                'direction' => $optimisticMessage->direction,
                'sender_type' => $optimisticMessage->sender_type,
                'delivery_status' => $optimisticMessage->delivery_status,
                'body' => $optimisticMessage->message_body,
                'html' => $optimisticMessage->formattedHtmlBody(),
                'time' => $optimisticMessage->created_at?->format('H:i'),
            ];
        }

        foreach ($appendMessages as $msg) {
            if (!$msg instanceof EcommerceWhatsappMessage) {
                continue;
            }
            $htmlBody = $msg->formattedHtmlBody();
            $timeStr = $msg->created_at?->format('H:i') ?? now()->format('H:i');

            $structuredMessages[] = [
                'id' => $msg->id,
                'direction' => $msg->direction,
                'sender_type' => $msg->sender_type,
                'delivery_status' => $msg->delivery_status,
                'body' => $msg->message_body,
                'html' => $htmlBody,
                'time' => $timeStr,
            ];

            if ($msg->sender_type === 'system_event') {
                $messagesHtmlParts[] = '<div class="d-flex justify-content-center shrink-0 flex-shrink-0 my-1 ai-bubble-animate">'
                    . '<div class="ai-system-pill shrink-0 flex-shrink-0 my-1 py-1 px-3 leading-normal text-xs rounded-full">'
                    . $htmlBody
                    . '</div></div>';
            } elseif ($msg->direction === 'inbound') {
                $messagesHtmlParts[] = '<div class="d-flex justify-content-end w-100 ai-bubble-animate">'
                    . '<div class="ai-msg-bubble ai-msg-inbound ms-auto">'
                    . '<div class="ai-msg-sender-label is-customer">You (Customer)</div>'
                    . '<div class="ai-msg-body">' . $htmlBody . '</div>'
                    . '<div class="ai-msg-time">' . $timeStr . ' &checkmark;&checkmark;</div>'
                    . '</div></div>';
            } else {
                $senderLabel = $msg->sender_type === 'staff'
                    ? '👩‍💼 ' . e($personaName) . ' (Staff Override)'
                    : '✨ ' . e($personaName) . ' (Laleen AI)';
                $timePrefix = $msg->sender_type === 'staff' ? 'Staff · ' : '';

                $messagesHtmlParts[] = '<div class="d-flex justify-content-start w-100 ai-bubble-animate">'
                    . '<div class="ai-msg-bubble ai-msg-outbound me-auto">'
                    . '<div class="ai-msg-sender-label is-zara">' . $senderLabel . '</div>'
                    . '<div class="ai-msg-body">' . $htmlBody . '</div>'
                    . '<div class="ai-msg-time">' . $timePrefix . $timeStr . '</div>'
                    . '</div></div>';
            }
        }

        // Render updated index view in memory to extract updated right-hand receipt, KPI ribbon, header, and thread list
        $subRequest = Request::create('/ecommerce/hub/ai-agent', 'GET', [
            'region' => strtolower($thread->region),
            'thread_id' => $thread->id,
        ]);
        $renderedHtml = $this->index($subRequest)->render();

        return [
            'ok' => true,
            'status' => 'ok',
            'thread_id' => $thread->id,
            'thread_state' => $thread->state,
            'playbook' => $thread->playbook,
            'replace_timeline' => $replaceTimeline,
            'messages_html' => implode("\n", $messagesHtmlParts),
            'new_messages' => $structuredMessages,
            'receipt_html' => $this->extractElementInnerHtml($renderedHtml, 'ai-order-receipt-panel'),
            'kpis_html' => $this->extractElementInnerHtml($renderedHtml, 'ai-kpi-ribbon'),
            'header_html' => $this->extractElementInnerHtml($renderedHtml, 'ai-chat-header'),
            'thread_list_html' => $this->extractElementInnerHtml($renderedHtml, 'ai-thread-list-panel'),
            'chips_html' => $this->extractElementInnerHtml($renderedHtml, 'quick-reply-chips-row'),
        ];
    }

    /**
     * Extract innerHTML of a specific element ID from a rendered Blade HTML string.
     */
    protected function extractElementInnerHtml(string $html, string $elementId): ?string
    {
        if ($html === '') {
            return null;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $el = $dom->getElementById($elementId);
        if (!$el) {
            return null;
        }

        $inner = '';
        foreach ($el->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return $inner;
    }

    /**
     * POST /ecommerce/hub/ai-agent/threads/{thread}/toggle-handoff
     */
    public function toggleHandoff(Request $request, EcommerceWhatsappThread $thread): RedirectResponse
    {
        if ($thread->state === 'human_handoff') {
            $resumeState = match ($thread->playbook) {
                'day7_cross_sell' => 'day7_checkin_sent',
                'day30_refill' => 'day30_refill_pitched',
                'abandoned_checkout' => 'abandoned_followup_sent',
                'inbound_support' => 'inbound_active',
                default => 'awaiting_cod_confirm',
            };

            if ($thread->playbook === 'cod_upsell' && $thread->order_group_id) {
                $isConfirmed = EcommerceSale::where('order_group_id', $thread->order_group_id)
                    ->whereNotNull('cod_confirmed_at')
                    ->exists();
                if ($isConfirmed) {
                    $resumeState = 'cod_confirmed_upsell_pitched';
                }
            }

            $thread->state = $resumeState;
            $thread->save();

            EcommerceWhatsappMessage::create([
                'thread_id' => $thread->id,
                'direction' => 'outbound',
                'sender_type' => 'system_event',
                'delivery_status' => 'sent',
                'message_body' => '🤖 Staff returned thread control to AI Sales Agent.',
            ]);

            return back()->with('success', 'Thread returned to AI Sales Agent control.');
        }

        $thread->state = 'human_handoff';
        $thread->save();

        EcommerceWhatsappMessage::create([
            'thread_id' => $thread->id,
            'direction' => 'outbound',
            'sender_type' => 'system_event',
            'delivery_status' => 'sent',
            'message_body' => '👤 Staff took over conversation (Human Handoff active — AI paused).',
        ]);

        return back()->with('success', 'Thread switched to Human Handoff mode.');
    }

    /**
     * GET /api/webhooks/ecommerce/whatsapp-inbound
     * Official Meta WhatsApp Cloud API Webhook Verification endpoint (hub.challenge).
     */
    public function verifyWhatsappWebhook(Request $request)
    {
        $query = $request->query();
        $mode = (string) ($query['hub_mode'] ?? $query['hub.mode'] ?? $request->input('hub_mode') ?? '');
        $token = (string) ($query['hub_verify_token'] ?? $query['hub.verify_token'] ?? $request->input('hub_verify_token') ?? '');
        $challenge = (string) ($query['hub_challenge'] ?? $query['hub.challenge'] ?? $request->input('hub_challenge') ?? '');

        $expectedToken = (string) config('services.whatsapp.verify_token', env('WHATSAPP_VERIFY_TOKEN', 'laleen_whatsapp_verify_2026'));

        if ($mode === 'subscribe' && $expectedToken !== '' && hash_equals($expectedToken, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden: Invalid verify token.', 403);
    }

    /**
     * POST /api/webhooks/ecommerce/whatsapp-inbound
     * Accepts inbound customer replies from n8n/Make bridge or Meta WhatsApp Cloud API.
     */
    public function inboundWebhook(Request $request): JsonResponse
    {
        $configuredToken = config('services.whatsapp.webhook_token');
        if ($configuredToken !== null && trim((string) $configuredToken) !== '') {
            $headerToken = (string) $request->header('X-Ecommerce-Webhook-Token', '');
            if ($headerToken === '' || !hash_equals((string) $configuredToken, $headerToken)) {
                return response()->json(['error' => 'Unauthorized: Invalid X-Ecommerce-Webhook-Token.'], 401);
            }
        }

        $payload = $request->all();

        // Extract fields supporting both n8n/Make flat JSON and Meta Cloud API nested payload
        $threadId = $payload['thread_id'] ?? null;
        $rawPhone = $payload['phone']
            ?? $payload['from']
            ?? data_get($payload, 'entry.0.changes.0.value.messages.0.from');
        $msgType = strtolower((string) (
            $payload['type']
            ?? $payload['message_type']
            ?? data_get($payload, 'entry.0.changes.0.value.messages.0.type')
            ?? 'text'
        ));
        $messageBody = trim((string) (
            $payload['message_body']
            ?? (is_string($payload['text'] ?? null) ? $payload['text'] : data_get($payload, 'text.body'))
            ?? data_get($payload, 'entry.0.changes.0.value.messages.0.text.body')
            ?? ''
        ));
        $externalId = $payload['external_message_id']
            ?? $payload['message_id']
            ?? data_get($payload, 'entry.0.changes.0.value.messages.0.id');

        // Non-Text Media Grace: location pin, audio/voice note, or image
        $locationData = $payload['location']
            ?? data_get($payload, 'entry.0.changes.0.value.messages.0.location');
        $extractedLocationNote = null;

        if ($msgType === 'location' || is_array($locationData)) {
            $lat = is_array($locationData) ? ($locationData['latitude'] ?? null) : null;
            $lng = is_array($locationData) ? ($locationData['longitude'] ?? null) : null;
            $locName = is_array($locationData) ? trim((string) ($locationData['name'] ?? '')) : '';
            $locAddr = is_array($locationData) ? trim((string) ($locationData['address'] ?? '')) : '';

            $locParts = array_values(array_filter([
                $locName !== '' ? $locName : null,
                $locAddr !== '' ? $locAddr : null,
                ($lat !== null && $lng !== null) ? "Coords: {$lat}, {$lng}" : null,
            ]));
            $extractedLocationNote = 'WhatsApp Location Pin: ' . (!empty($locParts) ? implode(' | ', $locParts) : 'Pinned Coordinates');

            if ($messageBody === '') {
                $messageBody = "[Location Pin Received] {$extractedLocationNote}";
            }
        } elseif (
            $messageBody === ''
            && (
                in_array($msgType, ['audio', 'voice'], true)
                || isset($payload['audio'])
                || isset($payload['voice'])
                || data_get($payload, 'entry.0.changes.0.value.messages.0.audio') !== null
                || data_get($payload, 'entry.0.changes.0.value.messages.0.voice') !== null
            )
        ) {
            $messageBody = '[Voice Note Received]';
        } elseif (
            $messageBody === ''
            && (
                $msgType === 'image'
                || isset($payload['image'])
                || data_get($payload, 'entry.0.changes.0.value.messages.0.image') !== null
            )
        ) {
            $caption = trim((string) (
                data_get($payload, 'image.caption')
                ?? data_get($payload, 'entry.0.changes.0.value.messages.0.image.caption')
                ?? ''
            ));
            $messageBody = $caption !== '' ? "[Image Received] {$caption}" : '[Image Received]';
        }

        if ($messageBody === '') {
            return response()->json(['status' => 'ignored_empty_message'], 200);
        }

        // Idempotency check on external_message_id
        if ($externalId && EcommerceWhatsappMessage::where('external_message_id', (string) $externalId)->exists()) {
            return response()->json(['status' => 'duplicate_ignored', 'external_message_id' => $externalId], 200);
        }

        $thread = null;
        if ($threadId) {
            $thread = EcommerceWhatsappThread::find($threadId);
        }

        if (!$thread && $rawPhone) {
            $cleanDigits = preg_replace('/\D+/', '', (string) $rawPhone);
            $isQatarPhone = str_starts_with($cleanDigits, '974')
                || (strlen($cleanDigits) === 8 && in_array($cleanDigits[0] ?? '', ['3', '5', '6', '7'], true));
            $defaultCountryCode = $isQatarPhone ? '974' : '92';

            $normalizedPk = EcommerceCustomer::normalizePhone((string) $rawPhone, '92');
            $normalizedQa = EcommerceCustomer::normalizePhone((string) $rawPhone, '974');
            $primaryNormalized = EcommerceCustomer::normalizePhone((string) $rawPhone, $defaultCountryCode);

            $customer = EcommerceCustomer::whereIn('phone', array_values(array_unique(array_filter([$primaryNormalized, $normalizedPk, $normalizedQa, $cleanDigits]))))->first();

            // Cold Buyer Inbound Support: create customer if phone number is brand new
            if (!$customer && $primaryNormalized !== '') {
                $senderName = trim((string) (
                    $payload['customer_name']
                    ?? $payload['profile_name']
                    ?? data_get($payload, 'entry.0.changes.0.value.contacts.0.profile.name')
                    ?? 'Valued Guest'
                ));
                $customer = EcommerceCustomer::create([
                    'name' => $senderName !== '' ? $senderName : 'Valued Guest',
                    'phone' => $primaryNormalized,
                    'total_spend' => 0,
                    'total_orders' => 0,
                ]);
            }

            if ($customer) {
                $thread = EcommerceWhatsappThread::where('ecommerce_customer_id', $customer->id)
                    ->orderByDesc('last_message_at')
                    ->orderByDesc('id')
                    ->first();

                if (!$thread) {
                    $region = str_starts_with((string) $customer->phone, '974') ? 'QA' : 'PK';
                    $thread = EcommerceWhatsappThread::create($this->filterExistingTableColumns('ecommerce_whatsapp_threads', [
                        'ecommerce_customer_id' => $customer->id,
                        'order_group_id' => 'COLD-' . strtoupper(Str::random(6)),
                        'customer_name' => $customer->name,
                        'customer_phone' => $customer->phone,
                        'region' => $region,
                        'playbook' => 'inbound_support',
                        'state' => 'inbound_active',
                        'upsell_revenue_generated' => 0,
                        'unread_count' => 0,
                        'last_message_at' => now(),
                    ]));
                } elseif ($thread->playbook === 'inbound_support' && $thread->state === 'idle') {
                    $thread->state = 'inbound_active';
                    $thread->save();
                }
            }
        }

        if (!$thread) {
            return response()->json(['error' => 'Thread or customer not found for inbound WhatsApp message.'], 404);
        }

        if ($extractedLocationNote !== null) {
            $thread->delivery_notes = $extractedLocationNote;
            $thread->save();

            if ($thread->order_group_id) {
                EcommerceSale::where('order_group_id', $thread->order_group_id)
                    ->update(['delivery_notes' => $extractedLocationNote]);
            }
        }

        $result = $this->agentService->handleInboundCustomerMessage(
            $thread,
            $messageBody,
            $externalId ? (string) $externalId : null
        );

        return response()->json([
            'status' => 'ok',
            'thread_id' => $thread->id,
            'thread_state' => $result['thread']->state,
            'detected_intent' => $result['ai_decision']['detected_intent'] ?? null,
            'triggered_action' => $result['ai_decision']['should_trigger_action'] ?? null,
            'outbound_message_id' => $result['outbound_message']?->id,
        ], 200);
    }
}
