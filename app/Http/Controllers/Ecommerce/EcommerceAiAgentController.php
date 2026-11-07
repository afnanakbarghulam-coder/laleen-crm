<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\EcommerceAiSetting;
use App\Models\EcommerceCustomer;
use App\Models\EcommerceSale;
use App\Models\EcommerceWhatsappMessage;
use App\Models\EcommerceWhatsappThread;
use App\Services\Ecommerce\EcommerceAiSalesAgentService;
use App\Services\Ecommerce\EcommerceWhatsappTransportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $activeThread = $selectedThreadId
            ? ($allRegionThreads->firstWhere('id', $selectedThreadId) ?? $threads->first())
            : $threads->first();

        if ($activeThread && $activeThread->unread_count > 0) {
            $activeThread->unread_count = 0;
            $activeThread->save();
        }

        $activeOrderSales = collect();
        if ($activeThread && $activeThread->order_group_id) {
            $activeOrderSales = EcommerceSale::with(['product.pricingModel', 'bundle'])
                ->where('order_group_id', $activeThread->order_group_id)
                ->orderBy('id')
                ->get();
        }

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
                'Outreach Scan complete: %d COD confirmation(s), %d 6-hour nudge(s), %d Day-7 cross-sell(s), and %d Day-30 refill thread(s) processed.',
                $result['cod_threads_created'] ?? 0,
                $result['nudges_sent'] ?? 0,
                $result['day7_threads_created'] ?? 0,
                $result['day30_threads_created'] ?? 0
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
        if (!in_array($playbook, ['cod_upsell', 'day7_cross_sell', 'day30_refill'], true)) {
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

        if ($playbook === 'day7_cross_sell' || $playbook === 'day30_refill') {
            $firstSale = EcommerceSale::with('customer')
                ->where('order_group_id', $orderGroupIdStr)
                ->orderBy('id')
                ->first();

            if (!$firstSale) {
                return back()->withErrors(['error' => "Order {$orderGroupIdStr} not found."]);
            }

            $customer = $firstSale->customer;
            if (!$customer) {
                $region = EcommerceSale::regionForChannel($firstSale->channel);
                $defaultCode = $region === 'qa' ? '+974' : '+92';
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

            $createdThread = $playbook === 'day30_refill'
                ? $this->agentService->initiateDay30Refill($customer, $orderGroupIdStr)
                : $this->agentService->initiateDay7CrossSell($customer, $orderGroupIdStr);

            $playbookLabel = $playbook === 'day30_refill'
                ? 'Day 30 VIP Refill Link'
                : 'Day 7 Ritual + Cross-Sell Link';
        } else {
            $createdThread = $this->agentService->initiateCodConfirmation($orderGroupIdStr, true);
            $playbookLabel = 'COD Confirmation + Box Upgrade';
        }

        if (!$createdThread) {
            return back()->withErrors(['error' => "Could not initiate {$playbookLabel} for order {$orderGroupIdStr}."]);
        }

        $regionParam = strtolower($createdThread->region);

        return redirect()
            ->route('ecommerce.ai-agent.index', ['region' => $regionParam, 'thread_id' => $createdThread->id])
            ->with('success', "AI {$playbookLabel} initiated for Order {$createdThread->shortOrderRef()}.");
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
    public function sendManual(Request $request, EcommerceWhatsappThread $thread): RedirectResponse
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

        return back()->with('success', 'Manual WhatsApp reply sent.');
    }

    /**
     * POST /ecommerce/hub/ai-agent/threads/{thread}/simulate-inbound
     */
    public function simulateInbound(Request $request, EcommerceWhatsappThread $thread): RedirectResponse
    {
        $validated = $request->validate([
            'customer_reply' => 'required|string|max:2000',
        ]);

        // If thread was in human_handoff and admin is simulating a customer test reply, allow resuming AI if requested
        if ($thread->state === 'human_handoff' && $request->boolean('resume_ai')) {
            $thread->state = $thread->playbook === 'day7_cross_sell' ? 'day7_checkin_sent' : 'awaiting_cod_confirm';
            $thread->save();
        }

        $result = $this->agentService->handleInboundCustomerMessage($thread, $validated['customer_reply']);
        $intent = $result['ai_decision']['detected_intent'] ?? 'processed';
        $action = $result['ai_decision']['should_trigger_action'] ?? 'none';

        return redirect()
            ->route('ecommerce.ai-agent.index', [
                'region' => strtolower($thread->region),
                'thread_id' => $thread->id,
            ])
            ->with('success', "Simulated customer reply processed (Intent: {$intent} | Action: {$action}).");
    }

    /**
     * POST /ecommerce/hub/ai-agent/threads/{thread}/toggle-handoff
     */
    public function toggleHandoff(Request $request, EcommerceWhatsappThread $thread): RedirectResponse
    {
        if ($thread->state === 'human_handoff') {
            $resumeState = $thread->playbook === 'day7_cross_sell' ? 'day7_checkin_sent' : 'awaiting_cod_confirm';
            if ($thread->order_group_id) {
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
        $messageBody = trim((string) (
            $payload['message_body']
            ?? $payload['text']
            ?? data_get($payload, 'entry.0.changes.0.value.messages.0.text.body')
            ?? ''
        ));
        $externalId = $payload['external_message_id']
            ?? $payload['message_id']
            ?? data_get($payload, 'entry.0.changes.0.value.messages.0.id');

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
            $normalizedPk = EcommerceCustomer::normalizePhone((string) $rawPhone, '92');
            $normalizedQa = EcommerceCustomer::normalizePhone((string) $rawPhone, '974');

            $customer = EcommerceCustomer::whereIn('phone', array_filter([$normalizedPk, $normalizedQa]))->first();
            if ($customer) {
                $thread = EcommerceWhatsappThread::where('ecommerce_customer_id', $customer->id)
                    ->orderByDesc('last_message_at')
                    ->orderByDesc('id')
                    ->first();

                if (!$thread) {
                    $region = str_starts_with($customer->phone, '974') ? 'QA' : 'PK';
                    $thread = EcommerceWhatsappThread::create([
                        'ecommerce_customer_id' => $customer->id,
                        'region' => $region,
                        'playbook' => 'inbound_support',
                        'state' => 'idle',
                        'last_message_at' => now(),
                    ]);
                }
            }
        }

        if (!$thread) {
            return response()->json(['error' => 'Thread or customer not found for inbound WhatsApp message.'], 404);
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
