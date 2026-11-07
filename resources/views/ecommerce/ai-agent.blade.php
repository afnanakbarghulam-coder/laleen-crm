@extends('layouts.app')
@section('title', 'AI Sales Agent')

@include('ecommerce._styles')

@php
    $currency = $currency ?? 'PKR';
    $region = $region ?? 'pk';
    $regionUpper = $regionUpper ?? strtoupper($region);
    $filter = $filter ?? 'all';
    $driverLabel = match ($settings->whatsapp_driver) {
        'meta_cloud' => 'Meta Cloud',
        'webhook' => 'Webhook',
        default => 'Simulator',
    };
@endphp

@section('content')
    {{-- Minimal Luxury Header --}}
    <div class="ec-header mb-3">
        <div>
            <div class="ec-sub text-uppercase fw-bold mb-1" style="font-size: 10.5px; letter-spacing: .1em; color: var(--ec-gold);">
                WhatsApp Concierge &middot; {{ $region === 'qa' ? 'Qatar (QAR)' : 'Pakistan (PKR)' }}
            </div>
            <h4 class="mb-1">AI Sales &amp; Retention Agent</h4>
            <p class="mb-0" style="font-size: 13px; color: var(--ec-muted);">
                Automated COD confirmation, pre-shipment open-box upgrades, and Day-7 ritual follow-ups.
            </p>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2">
            @moduleEdit('ecommerce')
                {{-- Quick Co-Pilot / Auto-Pilot Switch --}}
                <form method="POST" action="{{ route('ecommerce.ai-agent.settings') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="quick_toggle_mode" value="1">
                    <input type="hidden" name="mode" value="{{ $settings->isAutopilot() ? 'copilot' : 'autopilot' }}">
                    <button type="submit" class="ai-header-btn {{ $settings->isAutopilot() ? 'ai-header-btn-active' : '' }}">
                        <span class="ai-mode-dot {{ $settings->isAutopilot() ? 'dot-green' : 'dot-gold' }}"></span>
                        {{ $settings->isAutopilot() ? 'Auto-Pilot' : 'Co-Pilot' }}
                    </button>
                </form>

                {{-- Run Outreach Scan --}}
                <form method="POST" action="{{ route('ecommerce.ai-agent.run-scan') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="region" value="{{ $region }}">
                    <button type="submit" class="ai-header-btn">
                        <i class="bx bx-radar"></i> Run Scan
                    </button>
                </form>

                {{-- Clearly Visible Settings Button --}}
                <button type="button" class="ai-header-btn border-white/15 text-stone-200 hover:bg-white/5" data-bs-toggle="modal" data-bs-target="#aiAgentSettingsModal">
                    <i class="bx bx-slider-alt"></i> Settings
                </button>
            @endmoduleEdit
        </div>
    </div>

    @include('ecommerce.partials._hub_tabs')

    {{-- Sleek Low-Profile Horizontal KPI Ribbon --}}
    <div id="ai-kpi-ribbon" class="ai-kpi-ribbon py-3 px-5 mb-4">
        <div class="ai-ribbon-stat">
            <span class="ai-ribbon-label">Confirmed COD:</span>
            <strong class="ai-ribbon-val">{{ number_format($codConfirmedCount) }}/{{ number_format($codTotalCount) }} ({{ number_format($codConfirmationRate, 0) }}%)</strong>
        </div>
        <div class="ai-ribbon-divider"></div>
        <div class="ai-ribbon-stat">
            <span class="ai-ribbon-label">Box Upgrades Won:</span>
            <strong class="ai-ribbon-val" style="color: #deb97c;">{{ number_format($upsellWonCodCount) }} ({{ number_format($upsellConversionRate, 0) }}%)</strong>
        </div>
        <div class="ai-ribbon-divider"></div>
        <div class="ai-ribbon-stat">
            <span class="ai-ribbon-label">AI Upsell Revenue:</span>
            <strong class="ai-ribbon-val ec-positive">{{ $currency }} {{ number_format($extraUpsellRevenue, 0) }}</strong>
        </div>
        <div class="ai-ribbon-divider"></div>
        <div class="ai-ribbon-stat">
            <span class="ai-ribbon-label">Recovered Carts:</span>
            <strong class="ai-ribbon-val" style="color: #8ea88a;">{{ number_format($recoveredCartsCount ?? 0) }}</strong>
        </div>
        <div class="ai-ribbon-divider"></div>
        <div class="ai-ribbon-stat">
            <span class="ai-ribbon-label">Mode:</span>
            <strong class="ai-ribbon-val">{{ $settings->isAutopilot() ? 'Auto-Pilot' : 'Co-Pilot' }} ({{ $driverLabel }})</strong>
            @if ($pendingApprovalMessagesCount > 0)
                <span class="ai-approval-count">{{ $pendingApprovalMessagesCount }} pending</span>
            @endif
        </div>
    </div>

    {{-- Minimalist 3-Column Workspace (28% / 47% / 25%) --}}
    <div class="ai-workspace-grid">
        {{-- LEFT COLUMN: Minimalist Thread List (28% Width) --}}
        <div id="ai-thread-list-panel" class="ai-col-left ec-card p-3 mb-0 d-flex flex-column">
            @moduleEdit('ecommerce')
                <form method="POST" action="{{ route('ecommerce.ai-agent.start-cold-chat') }}" class="mb-2">
                    @csrf
                    <input type="hidden" name="region" value="{{ $region }}">
                    <button type="submit" class="ai-blank-chat-btn w-100">
                        + 💬 New Blank Customer Chat (No Scenario)
                    </button>
                </form>
            @endmoduleEdit

            {{-- 3 Clean Filter Tabs: All, Needs Approval, Escalated --}}
            <div class="ai-clean-tabs mb-3">
                @php
                    $isNeedsApproval = in_array($filter, ['needs_approval', 'pending_approval'], true);
                    $isEscalated = in_array($filter, ['escalated', 'human_handoff'], true);
                    $isAll = !$isNeedsApproval && !$isEscalated;
                @endphp
                <a href="{{ route('ecommerce.ai-agent.index', ['region' => $region, 'filter' => 'all']) }}"
                   class="ai-clean-tab {{ $isAll ? 'active' : '' }}">
                    All <span class="ai-tab-count">{{ $filterCounts['all'] ?? 0 }}</span>
                </a>
                <a href="{{ route('ecommerce.ai-agent.index', ['region' => $region, 'filter' => 'needs_approval']) }}"
                   class="ai-clean-tab {{ $isNeedsApproval ? 'active' : '' }}">
                    Needs Approval <span class="ai-tab-count">{{ $filterCounts['needs_approval'] ?? 0 }}</span>
                </a>
                <a href="{{ route('ecommerce.ai-agent.index', ['region' => $region, 'filter' => 'escalated']) }}"
                   class="ai-clean-tab {{ $isEscalated ? 'active' : '' }}">
                    Escalated <span class="ai-tab-count">{{ $filterCounts['escalated'] ?? 0 }}</span>
                </a>
            </div>

            {{-- Clean Thread Cards (Line 1: Customer Name + Status Badge; Line 2: 1-line truncated preview) --}}
            <div class="ai-thread-scroll flex-grow-1">
                @forelse ($threads as $t)
                    @php
                        $isSelected = $activeThread && $activeThread->id === $t->id;
                        $badge = $t->compactStatusBadge();
                        $lastMsg = $t->messages->last();
                        $previewText = $lastMsg
                            ? preg_replace('/\b([0-9a-f]{8})-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '#$1', str_replace('*', '', $lastMsg->message_body))
                            : 'No messages yet';
                    @endphp
                    <a href="{{ route('ecommerce.ai-agent.index', ['region' => $region, 'filter' => $filter, 'thread_id' => $t->id]) }}"
                       class="ai-minimal-thread-card {{ $isSelected ? 'active' : '' }}">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                            <span class="ai-thread-name text-truncate">
                                {{ $t->customer?->name ?: 'Valued Customer' }}
                            </span>
                            <span class="ai-status-badge variant-{{ $badge['variant'] }}">
                                <span class="ai-status-dot"></span>{{ $badge['label'] }}
                            </span>
                        </div>
                        <div class="ai-thread-preview text-xs text-stone-400 truncate">
                            {{ $previewText }}
                        </div>
                    </a>
                @empty
                    <div class="text-center py-5 my-auto" style="color: var(--ec-muted);">
                        <i class="bx bx-message-rounded" style="font-size: 28px; opacity: 0.45;"></i>
                        <div class="mt-2" style="font-size: 12.5px;">No conversations in this filter.</div>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- CENTER COLUMN: Clean Conversation Canvas (47% Width) --}}
        <div class="ai-col-center ec-card p-0 mb-0 d-flex flex-column overflow-hidden">
            @if ($activeThread)
                {{-- Minimal Chat Header: Customer Name, Phone Number, Short Order Badge, and Pause AI / Human Takeover right-aligned --}}
                <div id="ai-chat-header" class="ai-chat-header px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2 flex-shrink-0">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="fw-bold" style="font-size: 14.5px; color: var(--ec-text);">
                            {{ $activeThread->customer?->name ?: 'Valued Customer' }}
                        </span>
                        <span style="color: rgba(255,255,255,0.2);">&middot;</span>
                        <span style="font-size: 12.5px; color: var(--ec-muted);">
                            +{{ $activeThread->customer?->phone }}
                        </span>
                        @if ($activeThread->order_group_id)
                            <span class="ai-order-ref-pill">
                                Order {{ $activeThread->shortOrderRef() }}
                            </span>
                        @elseif ($activeThread->playbook === 'inbound_support')
                            <span class="ai-order-ref-pill" style="color: #b7d8b2; background: rgba(142, 168, 138, 0.14); border-color: rgba(142, 168, 138, 0.32);">
                                💬 Cold Customer Chat
                            </span>
                        @endif
                    </div>

                    @moduleEdit('ecommerce')
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <form method="POST" action="{{ route('ecommerce.ai-agent.threads.reset-chat', $activeThread->id) }}" class="m-0 ai-reset-chat-form">
                                @csrf
                                <button type="submit" class="ai-handoff-toggle" title="Wipe messages and test from scratch">
                                    🔄 Clear / Reset Chat
                                </button>
                            </form>
                            <form method="POST" action="{{ route('ecommerce.ai-agent.threads.toggle-handoff', $activeThread->id) }}" class="m-0">
                                @csrf
                                <button type="submit" class="ai-handoff-toggle {{ $activeThread->state === 'human_handoff' ? 'is-paused' : '' }}">
                                    @if ($activeThread->state === 'human_handoff')
                                        <i class="bx bx-play-circle"></i> Resume AI
                                    @else
                                        <i class="bx bx-pause-circle"></i> Pause AI / Human Takeover
                                    @endif
                                </button>
                            </form>
                        </div>
                    @endmoduleEdit
                </div>

                {{-- Clean Message Bubbles Timeline (WhatsApp Customer Perspective: Customer on Right, Zara on Left) --}}
                <div id="ai-chat-timeline" class="ai-chat-stream flex-grow-1 d-flex flex-column gap-3">
                    <div id="chat-messages-container" class="d-flex flex-column gap-3">
                        @foreach ($activeThread->messages as $msg)
                            @if ($msg->sender_type === 'system_event')
                                {{-- Sleek 1-Line Centered System Event Pill --}}
                                <div class="d-flex justify-content-center shrink-0 flex-shrink-0 my-2">
                                    <div class="ai-system-pill shrink-0 flex-shrink-0 my-2 py-1.5 px-3.5 leading-normal text-xs rounded-full">
                                        {!! $msg->formattedHtmlBody() !!}
                                    </div>
                                </div>
                            @elseif ($msg->direction === 'inbound')
                                {{-- Customer Message Bubble (Aligned RIGHT — "You (Customer)") --}}
                                <div class="d-flex justify-content-end w-100">
                                    <div class="ai-msg-bubble ai-msg-inbound ms-auto">
                                        <div class="ai-msg-sender-label is-customer">You (Customer)</div>
                                        <div class="ai-msg-body">{!! $msg->formattedHtmlBody() !!}</div>
                                        <div class="ai-msg-time">{{ $msg->created_at?->format('H:i') }} &checkmark;&checkmark;</div>
                                    </div>
                                </div>
                            @else
                                {{-- Outbound AI / Staff Message Bubble (Aligned LEFT — "✨ Zara (Laleen AI)") --}}
                                <div class="d-flex justify-content-start w-100">
                                    <div class="ai-msg-bubble ai-msg-outbound me-auto {{ $msg->delivery_status === 'pending_approval' ? 'ai-msg-draft' : '' }}">
                                        @if ($msg->delivery_status === 'pending_approval')
                                            <form method="POST" action="{{ route('ecommerce.ai-agent.messages.approve', $msg->id) }}">
                                                @csrf
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span style="font-size: 11px; font-weight: 600; color: #deb97c;">
                                                        Co-Pilot Draft &mdash; Review &amp; Send
                                                    </span>
                                                    <span class="ai-msg-time">{{ $msg->created_at?->format('H:i') }}</span>
                                                </div>
                                                <textarea name="message_body" rows="3" class="form-control form-control-sm mb-2 ai-draft-textarea">{{ $msg->message_body }}</textarea>
                                                <button type="submit" class="btn btn-sm btn-success fw-semibold px-3">
                                                    Approve &amp; Send
                                                </button>
                                            </form>
                                        @else
                                            <div class="ai-msg-sender-label is-zara">
                                                @if ($msg->sender_type === 'staff')
                                                    👩‍💼 {{ $settings->agent_persona_name ?: 'Zara' }} (Staff Override)
                                                @else
                                                    ✨ {{ $settings->agent_persona_name ?: 'Zara' }} (Laleen AI)
                                                @endif
                                            </div>
                                            <div class="ai-msg-body">{!! $msg->formattedHtmlBody() !!}</div>
                                            <div class="ai-msg-time">
                                                {{ $msg->sender_type === 'staff' ? 'Staff · ' : '' }}{{ $msg->created_at?->format('H:i') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                @moduleEdit('ecommerce')
                    {{-- Unified Chat Input Bar (Customer Test Mode vs Staff Override Mode) --}}
                    @php
                        $simulatorChips = match ($activeThread->playbook) {
                            'inbound_support' => ['Hi! What do you recommend for hairfall?', 'Is your shampoo sulfate-free?', 'How much is the Hair Oil?', 'Book 1 Hunza Apricot Oil on COD'],
                            'day7_cross_sell', 'day30_refill' => ['Loving it so far!', 'Send me the link', 'Does the shampoo have sulfates?', 'Not yet'],
                            'abandoned_checkout' => ['Confirm my order here on COD', 'Shipping is high', 'Does it help hairfall?', 'No thanks'],
                            default => ['YES', 'Does it help hairfall?', 'UPGRADE', 'No thanks'],
                        };
                        $defaultCustomerMode = ($settings->whatsapp_driver ?? 'simulator') === 'simulator';
                        $customerDisplayName = $activeThread->customer?->name ?: 'Customer';
                        $personaDisplayName = $settings->agent_persona_name ?: 'Zara';
                        $customerPlaceholder = "Type a message as {$customerDisplayName} and press Enter to see {$personaDisplayName} reply...";
                        $staffPlaceholder = "Send a message as {$personaDisplayName} (Staff Override)...";
                    @endphp
                    <div class="ai-unified-composer px-4 py-2.5 flex-shrink-0">
                        {{-- Mode Switcher Pills + Quick-Reply Chips --}}
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <div class="ai-mode-pill-group" role="group" aria-label="Chat Sender Mode">
                                <button type="button"
                                        id="mode-btn-customer"
                                        class="ai-mode-pill {{ $defaultCustomerMode ? 'active is-customer' : '' }}"
                                        onclick="setUnifiedChatMode('customer')">
                                    👤 Chat as Customer (Test AI)
                                </button>
                                <button type="button"
                                        id="mode-btn-staff"
                                        class="ai-mode-pill {{ !$defaultCustomerMode ? 'active is-staff' : '' }}"
                                        onclick="setUnifiedChatMode('staff')">
                                    👩‍💼 Reply as {{ $personaDisplayName }} (Staff Override)
                                </button>
                            </div>

                            {{-- Quick-Reply Suggestion Chips (Visible in Customer Mode) --}}
                            <div id="quick-reply-chips-row" class="d-flex align-items-center flex-wrap gap-1 {{ $defaultCustomerMode ? '' : 'd-none' }}">
                                @foreach ($simulatorChips as $chip)
                                    <form method="POST" action="{{ route('ecommerce.ai-agent.threads.simulate-inbound', $activeThread->id) }}" class="d-inline ai-chip-form">
                                        @csrf
                                        <input type="hidden" name="customer_reply" value="{{ $chip }}">
                                        <input type="hidden" name="resume_ai" value="1">
                                        <button type="submit" class="ai-sim-chip">{{ $chip }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </div>

                        {{-- Single Prominent Chat Input Form --}}
                        <form id="unified-chat-form"
                              method="POST"
                              action="{{ $defaultCustomerMode ? route('ecommerce.ai-agent.threads.simulate-inbound', $activeThread->id) : route('ecommerce.ai-agent.threads.send-manual', $activeThread->id) }}"
                              data-customer-action="{{ route('ecommerce.ai-agent.threads.simulate-inbound', $activeThread->id) }}"
                              data-staff-action="{{ route('ecommerce.ai-agent.threads.send-manual', $activeThread->id) }}"
                              data-customer-placeholder="{{ $customerPlaceholder }}"
                              data-staff-placeholder="{{ $staffPlaceholder }}"
                              data-persona-name="{{ $personaDisplayName }}"
                              class="d-flex align-items-center gap-2">
                            @csrf
                            <input type="hidden" name="resume_ai" value="1">
                            <input type="text"
                                   id="unified-chat-input"
                                   name="{{ $defaultCustomerMode ? 'customer_reply' : 'message_body' }}"
                                   class="form-control ai-unified-input {{ $defaultCustomerMode ? 'is-customer' : 'is-staff' }}"
                                   placeholder="{{ $defaultCustomerMode ? $customerPlaceholder : $staffPlaceholder }}"
                                   autocomplete="off"
                                   required>
                            <button type="submit"
                                    id="unified-chat-submit"
                                    class="ai-unified-submit {{ $defaultCustomerMode ? 'is-customer' : 'is-staff' }}">
                                {{ $defaultCustomerMode ? 'Send as Customer ↵' : 'Send as ' . $personaDisplayName . ' ↵' }}
                            </button>
                        </form>
                    </div>
                @endmoduleEdit
            @else
                <div class="p-5 text-center my-auto" style="color: var(--ec-muted);">
                    <i class="bx bxl-whatsapp" style="font-size: 40px; color: #8ea88a; opacity: 0.55;"></i>
                    <h6 class="mt-3 mb-1">No Conversation Selected</h6>
                    <p style="font-size: 12.5px; max-width: 340px; margin: 0 auto;">
                        Select a customer thread on the left or launch a COD confirmation from the right panel.
                    </p>
                </div>
            @endif
        </div>

        {{-- RIGHT COLUMN: Streamlined Order Receipt Card (25% Width) --}}
        <div class="ai-col-right d-flex flex-column gap-3">
            <div id="ai-order-receipt-panel" class="ec-card p-3 mb-0">
                @if ($activeThread)
                    @php
                        $isInboundCatalogPreview = $activeThread->playbook === 'inbound_support' && $activeOrderSales->isEmpty();
                        $isAbandonedPreview = !$isInboundCatalogPreview && $activeOrderSales->isEmpty() && !empty($activeAbandonedCheckout);
                        $abandonedLineItems = $isAbandonedPreview && is_array($activeAbandonedCheckout->line_items_json)
                            ? $activeAbandonedCheckout->line_items_json
                            : [];
                        $orderSubtotal = $isAbandonedPreview
                            ? (float) ($activeAbandonedCheckout->subtotal_price ?? 0)
                            : (float) $activeOrderSales->sum('total_price');
                        $orderShipping = (float) $activeOrderSales->sum('shipping_cost');
                        $orderTax = (float) $activeOrderSales->sum('tax_amount');
                        $orderGrandTotal = round($orderSubtotal + $orderShipping + $orderTax, 2);
                        $codConfirmedAt = $activeOrderSales->firstWhere('cod_confirmed_at', '!=', null)?->cod_confirmed_at
                            ?? (in_array($activeThread->state, ['cod_confirmed_upsell_pitched', 'upsell_accepted', 'upsell_declined'], true) ? $activeThread->updated_at : null);

                        $resolvedOfferBasePrice = $activeThread->offeredProduct
                            ? $activeThread->offeredProduct->resolveBasePrice($regionUpper)
                            : 0;
                        $resolvedOfferAddonPrice = (float) ($activeThread->offered_addon_price ?? 0);
                        if ($resolvedOfferAddonPrice <= 0 && $resolvedOfferBasePrice > 0) {
                            $resolvedOfferAddonPrice = round($resolvedOfferBasePrice * (1 - ((float) $settings->upsell_discount_percent / 100)), 2);
                        }
                        $completedUpgradeAmount = (float) ($activeThread->upsell_revenue_generated > 0 ? $activeThread->upsell_revenue_generated : $resolvedOfferAddonPrice);
                    @endphp

                    @if ($isInboundCatalogPreview)
                        {{-- In-Stock Catalog & Bundles Card for Cold Customer Chats (playbook === 'inbound_support') --}}
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2" style="border-bottom: 1px solid var(--ec-border);">
                            <span class="fw-bold" style="font-size: 13px; color: var(--ec-text);">
                                🛍️ In-Stock Catalog &amp; Bundles (Available to Pitch)
                            </span>
                            <span class="ai-cod-confirmed-badge">{{ $regionUpper }}</span>
                        </div>
                        <p class="mb-2" style="font-size: 11.5px; color: var(--ec-muted);">
                            Live regional SKUs &amp; prices {{ $settings->agent_persona_name ?: 'Zara' }} uses while consulting cold buyers:
                        </p>
                        <div class="mb-2">
                            @forelse ($catalogProducts ?? [] as $catProd)
                                @php
                                    $catPrice = $catProd->resolveBasePrice($regionUpper);
                                    $catStock = (int) ($regionUpper === 'QA' ? $catProd->stock_qatar : $catProd->stock_pakistan);
                                @endphp
                                <div class="d-flex justify-content-between align-items-center py-1.5" style="font-size: 12px; padding: 5px 0; border-bottom: 1px dashed rgba(255,255,255,0.07);">
                                    <div class="text-truncate pe-2" style="color: var(--ec-text);">
                                        <span class="fw-semibold">{{ $catProd->name }}</span>
                                        <span style="font-size: 10.5px; color: var(--ec-muted);">({{ $catStock }} in stock)</span>
                                    </div>
                                    <div class="fw-bold text-nowrap" style="color: #deb97c;">
                                        {{ $currency }} {{ number_format($catPrice, 0) }}
                                    </div>
                                </div>
                            @empty
                                <div style="font-size: 12px; color: var(--ec-muted);">No regional SKUs found.</div>
                            @endforelse
                        </div>

                        @if (!empty($catalogBundles) && count($catalogBundles) > 0)
                            <div class="mt-2 pt-2" style="border-top: 1px solid rgba(255,255,255,0.07);">
                                <div class="text-uppercase fw-bold mb-1" style="font-size: 10px; letter-spacing: .05em; color: #a3c49e;">
                                    Active Ritual Bundles
                                </div>
                                @foreach ($catalogBundles as $bundle)
                                    @if ((float) $bundle->bundle_price > 0)
                                        <div class="d-flex justify-content-between align-items-center py-1" style="font-size: 11.5px;">
                                            <span class="text-truncate pe-2" style="color: var(--ec-text);">{{ $bundle->name }}</span>
                                            <strong class="ec-positive text-nowrap">{{ $currency }} {{ number_format($bundle->bundle_price, 0) }}</strong>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    @else
                        {{-- Order Summary Receipt Header --}}
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2" style="border-bottom: 1px solid var(--ec-border);">
                            <span class="fw-bold" style="font-size: 13.5px; color: var(--ec-text);">
                                {{ $isAbandonedPreview ? 'Cart ' . $activeThread->shortOrderRef() : 'Order ' . $activeThread->shortOrderRef() }}
                            </span>
                            @if ($isAbandonedPreview)
                                <span class="ai-cod-pending-badge" style="color: #deb97c; background: rgba(201, 166, 107, 0.16); border: 1px solid rgba(201, 166, 107, 0.35);">Abandoned Cart Preview</span>
                            @elseif ($codConfirmedAt)
                                <span class="ai-cod-confirmed-badge">COD Confirmed ✓</span>
                            @else
                                <span class="ai-cod-pending-badge">Awaiting Confirm</span>
                            @endif
                        </div>

                        {{-- Receipt Line Items --}}
                        <div class="mb-3">
                            @if ($isAbandonedPreview && !empty($abandonedLineItems))
                                @foreach ($abandonedLineItems as $cartItem)
                                    @php
                                        $cQty = max(1, (int) ($cartItem['quantity'] ?? 1));
                                        $cPrice = (float) ($cartItem['price'] ?? 0);
                                        $cTotal = round($cPrice * $cQty, 2);
                                    @endphp
                                    <div class="d-flex justify-content-between align-items-center py-1.5" style="font-size: 12.5px; padding: 5px 0; border-bottom: 1px dashed rgba(255,255,255,0.06);">
                                        <div class="text-truncate pe-2" style="color: var(--ec-text);">
                                            {{ $cQty }}x {{ $cartItem['title'] ?? $cartItem['name'] ?? 'Product' }}
                                        </div>
                                        <div class="fw-semibold text-nowrap">
                                            {{ $currency }} {{ number_format($cTotal, 0) }}
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                @forelse ($activeOrderSales as $saleLine)
                                    <div class="d-flex justify-content-between align-items-center py-1.5" style="font-size: 12.5px; padding: 5px 0; border-bottom: 1px dashed rgba(255,255,255,0.06);">
                                        <div class="text-truncate pe-2" style="color: var(--ec-text);">
                                            {{ $saleLine->quantity }}x {{ $saleLine->product?->name ?? 'Product' }}
                                            @if ($saleLine->is_ai_upsell)
                                                <span class="ai-upsell-tag">[AI Upsell]</span>
                                            @endif
                                        </div>
                                        <div class="fw-semibold text-nowrap">
                                            {{ $currency }} {{ number_format($saleLine->total_price, 0) }}
                                        </div>
                                    </div>
                                @empty
                                    <div style="font-size: 12px; color: var(--ec-muted);">No order items linked.</div>
                                @endforelse
                            @endif

                            @if ($orderShipping > 0)
                                <div class="d-flex justify-content-between align-items-center" style="font-size: 12px; padding: 4px 0; color: var(--ec-muted);">
                                    <span>Shipping</span>
                                    <span>{{ $currency }} {{ number_format($orderShipping, 0) }}</span>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between align-items-center pt-2 mt-1 fw-bold" style="font-size: 13.5px;">
                                <span>Total</span>
                                <span class="ec-positive">{{ $currency }} {{ number_format($orderGrandTotal, 0) }}</span>
                            </div>

                            @php
                                $receiptDeliveryNote = $activeThread->delivery_notes
                                    ?: $activeOrderSales->firstWhere('delivery_notes', '!=', null)?->delivery_notes;
                            @endphp
                            @if ($receiptDeliveryNote)
                                <div class="mt-2 p-2 rounded" style="font-size: 11.5px; background: rgba(201, 166, 107, 0.1); border: 1px solid rgba(201, 166, 107, 0.28); color: #deb97c;">
                                    <div class="fw-bold" style="font-size: 10.5px; letter-spacing: .04em; text-transform: uppercase;">
                                        📍 Dispatch / Delivery Note
                                    </div>
                                    <div style="color: var(--ec-text); margin-top: 2px;">
                                        {{ $receiptDeliveryNote }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Smart Conditional Upgrade Card --}}
                        @php
                            $activeDiscountCode = $activeThread->playbook === 'day30_refill'
                                ? ($settings->day30_discount_code ?: 'VIPREFILL15')
                                : ($settings->day7_discount_code ?: 'COMPLETE20');
                            $activeDiscountPercent = $activeThread->playbook === 'day30_refill'
                                ? 15
                                : (float) $settings->upsell_discount_percent;
                        @endphp
                        @if ($activeThread->state === 'upsell_accepted')
                            <div class="ai-upgrade-completed-pill">
                                ✨ {{ in_array($activeThread->playbook, ['day7_cross_sell', 'day30_refill'], true) ? 'Shopify Retention Order Completed' : 'Box Upgrade Completed' }} (+{{ $currency }} {{ number_format($completedUpgradeAmount, 0) }})
                            </div>
                        @elseif ($activeThread->state === 'checkout_link_sent')
                            <div class="ai-checkout-link-pill">
                                🔗 Direct Discount Link Sent ({{ $activeDiscountCode }} &middot; {{ $currency }} {{ number_format($resolvedOfferAddonPrice, 0) }})
                            </div>
                        @elseif (in_array($activeThread->state, ['awaiting_cod_confirm', 'cod_confirmed_upsell_pitched', 'day7_checkin_sent', 'day7_cross_sell_pitched', 'day30_refill_pitched'], true) && $activeThread->offeredProduct)
                            <div class="ai-next-best-card p-2.5 rounded">
                                <div class="text-uppercase fw-bold mb-1" style="font-size: 10px; letter-spacing: .06em; color: #deb97c;">
                                    {{ $activeThread->playbook === 'day30_refill' ? 'VIP Refill Offer' : 'Next-Best-Product Offer' }} ({{ number_format($activeDiscountPercent, 0) }}% OFF)
                                </div>
                                <div class="fw-semibold" style="font-size: 13px; color: var(--ec-text);">
                                    {{ $activeThread->offeredProduct->name }}
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1" style="font-size: 12px;">
                                    <span style="color: var(--ec-muted); text-decoration: line-through;">
                                        {{ $currency }} {{ number_format($resolvedOfferBasePrice, 0) }}
                                    </span>
                                    <strong class="ec-positive">
                                        +{{ $currency }} {{ number_format($resolvedOfferAddonPrice, 0) }} ({{ $activeThread->playbook === 'cod_upsell' ? 'Free Ship' : $activeDiscountCode }})
                                    </strong>
                                </div>
                            </div>
                        @elseif ($activeThread->state === 'upsell_declined')
                            <div class="ai-upgrade-declined-pill">
                                📦 Order Locked &middot; Ready to Ship
                            </div>
                        @endif
                    @endif
                @endif

                {{-- Compact Trigger Selector for Any Order & Playbook + Blank Customer Chat Launcher --}}
                @moduleEdit('ecommerce')
                    <div class="{{ $activeThread ? 'mt-3 pt-3' : '' }}" style="{{ $activeThread ? 'border-top: 1px solid var(--ec-border);' : '' }}">
                        <form method="POST" action="{{ route('ecommerce.ai-agent.start-cold-chat') }}" class="mb-2.5" style="margin-bottom: 10px;">
                            @csrf
                            <input type="hidden" name="region" value="{{ $region }}">
                            <button type="submit" class="ai-blank-chat-btn w-100">
                                + 💬 New Blank Customer Chat (No Scenario)
                            </button>
                        </form>

                        <form method="POST" action="{{ route('ecommerce.ai-agent.trigger-cod-order') }}">
                            @csrf
                            <label class="form-label mb-1" style="font-size: 11px; color: var(--ec-muted);">
                                Launch Playbook on Order ({{ $regionUpper }})
                            </label>
                            <select name="playbook" class="form-select form-select-sm ai-clean-select mb-1.5" style="margin-bottom: 6px;">
                                <option value="cod_upsell" {{ ($activeThread?->playbook ?? 'cod_upsell') === 'cod_upsell' ? 'selected' : '' }}>
                                    Playbook 1: COD Confirm + Box Upgrade
                                </option>
                                <option value="day7_cross_sell" {{ ($activeThread?->playbook ?? '') === 'day7_cross_sell' ? 'selected' : '' }}>
                                    Playbook 2: Day 7 Ritual + Cross-Sell Link
                                </option>
                                <option value="day30_refill" {{ ($activeThread?->playbook ?? '') === 'day30_refill' ? 'selected' : '' }}>
                                    Playbook 3: Day 30 VIP Refill Link
                                </option>
                                <option value="abandoned_checkout" {{ ($activeThread?->playbook ?? '') === 'abandoned_checkout' ? 'selected' : '' }}>
                                    Playbook 4: 15-Min Abandoned Cart Rescue
                                </option>
                            </select>
                            <div class="d-flex gap-1">
                                <select name="order_group_id" class="form-select form-select-sm ai-clean-select" required>
                                    <option value="">Select order...</option>
                                    @foreach ($recentOrderGroups as $groupId => $groupRows)
                                        @php
                                            $firstRow = $groupRows->first();
                                            $groupSum = (float) $groupRows->sum('total_price');
                                            $shortGroupRef = \App\Models\EcommerceWhatsappThread::formatShortOrderRef($groupId);
                                        @endphp
                                        <option value="{{ $groupId }}">
                                            {{ $shortGroupRef }} &middot; {{ $firstRow->customer_name ?: 'Guest' }} ({{ $currency }} {{ number_format($groupSum, 0) }})
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="ai-header-btn px-2.5">Start</button>
                            </div>
                        </form>
                    </div>
                @endmoduleEdit
            </div>
        </div>
    </div>

    {{-- Settings Modal --}}
    @moduleEdit('ecommerce')
        <div class="modal fade" id="aiAgentSettingsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('ecommerce.ai-agent.settings') }}" class="modal-content" style="background: #221b1a; color: var(--ec-text); border: 1px solid var(--ec-border);">
                    @csrf
                    <div class="modal-header" style="border-bottom-color: var(--ec-border);">
                        <h5 class="modal-title">AI Sales Agent Settings</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Operating Mode</label>
                            <select name="mode" class="form-select">
                                <option value="copilot" {{ $settings->mode === 'copilot' ? 'selected' : '' }}>Co-Pilot (Drafts messages for 1-click staff approval)</option>
                                <option value="autopilot" {{ $settings->mode === 'autopilot' ? 'selected' : '' }}>Auto-Pilot (Dispatches messages immediately)</option>
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Agent Persona Name</label>
                                <input type="text" name="agent_persona_name" class="form-control" value="{{ $settings->agent_persona_name }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Open-Box Upsell Discount (%)</label>
                                <input type="number" step="0.5" min="0" max="90" name="upsell_discount_percent" class="form-control" value="{{ $settings->upsell_discount_percent }}" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Shopify Store Domain</label>
                                <input type="text" name="shopify_store_domain" class="form-control" placeholder="laleenrituals.com" value="{{ $settings->shopify_store_domain ?: 'laleenrituals.com' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Day 7 Discount Code</label>
                                <input type="text" name="day7_discount_code" class="form-control" placeholder="COMPLETE20" value="{{ $settings->day7_discount_code ?: 'COMPLETE20' }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Day 30 Refill Code</label>
                                <input type="text" name="day30_discount_code" class="form-control" placeholder="VIPREFILL15" value="{{ $settings->day30_discount_code ?: 'VIPREFILL15' }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">WhatsApp Transport Driver</label>
                            <select name="whatsapp_driver" class="form-select">
                                <option value="simulator" {{ $settings->whatsapp_driver === 'simulator' ? 'selected' : '' }}>Simulator (In-CRM Live Testing)</option>
                                <option value="webhook" {{ $settings->whatsapp_driver === 'webhook' ? 'selected' : '' }}>Webhook Bridge (n8n / Make)</option>
                                <option value="meta_cloud" {{ $settings->whatsapp_driver === 'meta_cloud' ? 'selected' : '' }}>Official Meta WhatsApp Cloud API (v20.0)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Outbound Webhook URL (n8n / Make)</label>
                            <input type="url" name="outbound_webhook_url" class="form-control" placeholder="https://hook.eu1.make.com/..." value="{{ $settings->outbound_webhook_url }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Laleen Rituals Botanical Knowledge Base (Gemini Prompt Context)</label>
                            <textarea name="brand_knowledge_base" rows="5" class="form-control" style="font-size: 12px;">{{ $settings->brand_knowledge_base ?: \App\Models\EcommerceAiSetting::DEFAULT_KNOWLEDGE_BASE }}</textarea>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="cod_confirmation_enabled" value="1" id="codConfSwitch" {{ $settings->cod_confirmation_enabled ? 'checked' : '' }}>
                            <label class="form-check-label" for="codConfSwitch">Auto-Trigger COD Confirmation on New Unpaid COD Orders</label>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="pre_shipment_upsell_enabled" value="1" id="upsellSwitch" {{ $settings->pre_shipment_upsell_enabled ? 'checked' : '' }}>
                            <label class="form-check-label" for="upsellSwitch">Pitch &ldquo;Box Is Still Open&rdquo; Complementary Upgrade after COD Confirm</label>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="day7_cross_sell_enabled" value="1" id="day7Switch" {{ $settings->day7_cross_sell_enabled ? 'checked' : '' }}>
                            <label class="form-check-label" for="day7Switch">Enable Playbook 2 (Day-7 Ritual Check-In &amp; Shopify Cross-Sell Link)</label>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="day30_refill_enabled" value="1" id="day30Switch" {{ ($settings->day30_refill_enabled ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="day30Switch">Enable Playbook 3 (Day-30 VIP Refill Check-In &amp; 15% OFF Link)</label>
                        </div>

                        <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="abandoned_checkout_enabled" value="1" id="abandonedCheckoutSwitch" {{ ($settings->abandoned_checkout_enabled ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="abandonedCheckoutSwitch">Enable Playbook 4 (15-Min Abandoned Cart WhatsApp Rescue)</label>
                            </div>
                            <div style="width: 110px;">
                                <input type="number" min="1" max="1440" name="abandoned_checkout_delay_minutes" class="form-control form-control-sm" value="{{ $settings->abandoned_checkout_delay_minutes ?? 15 }}" title="Delay in minutes">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top-color: var(--ec-border);">
                        <button type="button" class="btn btn-outline-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    @endmoduleEdit

    <style>
        /* Header Action Buttons */
        .ai-header-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            font-weight: 500;
            padding: 6px 13px;
            border-radius: 8px;
            color: #e7e5e4;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: all 0.15s ease;
        }
        .ai-header-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.25);
            color: #fff;
        }
        .ai-header-btn-active {
            border-color: rgba(142, 168, 138, 0.45);
            background: rgba(142, 168, 138, 0.12);
        }
        .ai-mode-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }
        .ai-mode-dot.dot-green { background: #8ea88a; box-shadow: 0 0 6px #8ea88a; }
        .ai-mode-dot.dot-gold { background: #deb97c; }

        /* Horizontal KPI Ribbon */
        .ai-kpi-ribbon {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid var(--ec-border);
        }
        .ai-ribbon-stat {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        .ai-ribbon-label {
            color: var(--ec-muted);
            font-weight: 500;
        }
        .ai-ribbon-val {
            color: var(--ec-text);
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .ai-ribbon-divider {
            width: 1px;
            height: 18px;
            background: rgba(255, 255, 255, 0.1);
        }
        .ai-approval-count {
            font-size: 11px;
            font-weight: 600;
            padding: 1px 7px;
            border-radius: 10px;
            background: rgba(201, 166, 107, 0.2);
            color: #deb97c;
        }

        /* 28% / 47% / 25% Split-Screen Workspace Grid */
        .ai-workspace-grid {
            display: grid;
            grid-template-columns: 28% 47% 25%;
            gap: 14px;
            align-items: start;
        }
        @media (max-width: 1199px) {
            .ai-workspace-grid {
                grid-template-columns: 1fr;
            }
        }
        .ai-col-left,
        .ai-col-center {
            height: calc(100vh - 140px);
            min-height: 560px;
            max-height: calc(100vh - 140px);
            overflow: hidden;
        }

        /* 3 Clean Filter Tabs */
        .ai-clean-tabs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4px;
            padding: 3px;
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--ec-border);
        }
        .ai-clean-tab {
            text-align: center;
            font-size: 11.5px;
            font-weight: 500;
            padding: 5px 6px;
            border-radius: 6px;
            color: var(--ec-muted);
            text-decoration: none;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .ai-clean-tab:hover {
            color: var(--ec-text);
        }
        .ai-clean-tab.active {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-weight: 600;
        }
        .ai-tab-count {
            font-size: 10.5px;
            opacity: 0.7;
            margin-left: 2px;
        }

        /* Minimalist Thread Cards */
        .ai-thread-scroll {
            overflow-y: auto;
            padding-right: 2px;
        }
        .ai-minimal-thread-card {
            display: block;
            padding: 10px 12px;
            margin-bottom: 6px;
            border-radius: 9px;
            text-decoration: none;
            background: transparent;
            border: 1px solid transparent;
            border-bottom-color: rgba(255, 255, 255, 0.05);
            transition: all 0.15s ease;
        }
        .ai-minimal-thread-card:hover {
            background: rgba(255, 255, 255, 0.03);
        }
        .ai-minimal-thread-card.active {
            background: rgba(217, 143, 131, 0.1);
            border-color: rgba(217, 143, 131, 0.3);
        }
        .ai-thread-name {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--ec-text);
        }
        .ai-thread-preview {
            font-size: 12px;
            color: #a8a29e;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ai-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10.5px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            white-space: nowrap;
        }
        .ai-status-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
        }
        .ai-status-badge.variant-upgraded {
            color: #a3c49e;
            background: rgba(142, 168, 138, 0.16);
        }
        .ai-status-badge.variant-confirmed {
            color: #d6c7c2;
            background: rgba(255, 255, 255, 0.07);
        }
        .ai-status-badge.variant-awaiting {
            color: #deb97c;
            background: rgba(201, 166, 107, 0.14);
        }
        .ai-status-badge.variant-escalated {
            color: #eba199;
            background: rgba(168, 82, 74, 0.22);
        }

        /* Center Chat Canvas */
        .ai-chat-header {
            flex-shrink: 0;
            border-bottom: 1px solid var(--ec-border);
            background: rgba(255, 255, 255, 0.02);
        }
        .ai-order-ref-pill {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 6px;
            color: #deb97c;
            background: rgba(201, 166, 107, 0.12);
            border: 1px solid rgba(201, 166, 107, 0.25);
        }
        .ai-handoff-toggle {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11.5px;
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 6px;
            color: #d6c7c2;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.14);
            transition: all 0.15s ease;
        }
        .ai-handoff-toggle:hover {
            background: rgba(168, 82, 74, 0.18);
            border-color: rgba(168, 82, 74, 0.45);
            color: #eba199;
        }
        .ai-handoff-toggle.is-paused {
            background: rgba(142, 168, 138, 0.18);
            border-color: rgba(142, 168, 138, 0.45);
            color: #a3c49e;
        }

        #ai-chat-timeline,
        .ai-chat-stream {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            scroll-behavior: smooth;
            padding: 1.25rem 1.25rem 2rem 1.25rem;
            background: rgba(12, 9, 8, 0.25);
        }
        .ai-blank-chat-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            padding: 8px 12px;
            border-radius: 8px;
            color: #16110f;
            background: linear-gradient(135deg, #deb97c, #8ea88a);
            border: 1px solid rgba(222, 185, 124, 0.55);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .ai-blank-chat-btn:hover {
            filter: brightness(1.08);
            transform: translateY(-1px);
        }

        .ai-msg-bubble {
            max-width: 80%;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.5;
        }
        /* Customer Messages ($msg->direction === 'inbound'): Aligned RIGHT like an outgoing WhatsApp user bubble */
        .ai-msg-inbound {
            align-self: flex-end;
            margin-left: auto;
            background: rgba(142, 168, 138, 0.18);
            border: 1px solid rgba(142, 168, 138, 0.38);
            border-bottom-right-radius: 4px;
            color: var(--ec-text);
        }
        /* Zara AI / Staff Messages ($msg->direction === 'outbound'): Aligned LEFT with warm dark luxury card border */
        .ai-msg-outbound {
            align-self: flex-start;
            margin-right: auto;
            background: rgba(255, 255, 255, 0.045);
            border: 1px solid rgba(201, 166, 107, 0.32);
            border-bottom-left-radius: 4px;
            color: var(--ec-text);
        }
        .ai-msg-sender-label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 4px;
        }
        .ai-msg-sender-label.is-customer {
            color: #b7d8b2;
            text-align: right;
        }
        .ai-msg-sender-label.is-zara {
            color: #deb97c;
            text-align: left;
        }
        .ai-msg-draft {
            width: 88%;
            background: rgba(201, 166, 107, 0.1);
            border: 1px dashed rgba(201, 166, 107, 0.45);
        }
        .ai-msg-time {
            font-size: 10px;
            color: var(--ec-muted);
            text-align: right;
            margin-top: 4px;
        }
        .ai-draft-textarea {
            background: rgba(0, 0, 0, 0.3) !important;
            color: #fff !important;
            border-color: rgba(201, 166, 107, 0.35) !important;
            font-size: 12.5px;
        }

        /* Sleek 1-Line Centered System Event Pill */
        .shrink-0 {
            flex-shrink: 0 !important;
        }
        .leading-normal {
            line-height: 1.5 !important;
        }
        .ai-system-pill {
            display: inline-block;
            align-self: center;
            flex-shrink: 0;
            font-size: 11.5px;
            font-weight: 500;
            line-height: 1.5;
            padding: 6px 14px;
            margin: 4px 0;
            border-radius: 9999px;
            color: #b7d8b2;
            background: rgba(142, 168, 138, 0.14);
            border: 1px solid rgba(142, 168, 138, 0.3);
            max-width: 94%;
            white-space: nowrap;
            overflow-x: hidden;
            overflow-y: visible;
            text-overflow: ellipsis;
        }

        /* Unified Chat Composer & Mode Switcher */
        .ai-clean-input,
        .ai-clean-select {
            background: rgba(0, 0, 0, 0.25) !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
            color: var(--ec-text) !important;
            font-size: 12.5px;
        }
        .ai-unified-composer {
            border-top: 1px solid rgba(201, 166, 107, 0.28);
            background: #171210;
            flex-shrink: 0;
            position: relative;
            z-index: 2;
        }
        .ai-typing-dots {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-left: 6px;
            vertical-align: middle;
        }
        .ai-typing-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #deb97c;
            opacity: 0.4;
            animation: aiTypingPulse 1.2s infinite ease-in-out;
        }
        .ai-typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }
        .ai-typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes aiTypingPulse {
            0%, 80%, 100% {
                opacity: 0.35;
                transform: scale(0.85);
            }
            40% {
                opacity: 1;
                transform: scale(1.2);
            }
        }
        .ai-mode-pill-group {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px;
            border-radius: 9999px;
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .ai-mode-pill {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 11px;
            border-radius: 9999px;
            border: 1px solid transparent;
            background: transparent;
            color: var(--ec-muted);
            transition: all 0.15s ease;
            cursor: pointer;
            white-space: nowrap;
        }
        .ai-mode-pill:hover {
            color: var(--ec-text);
        }
        .ai-mode-pill.active.is-customer {
            color: #f3d79b;
            background: rgba(201, 166, 107, 0.22);
            border-color: rgba(201, 166, 107, 0.45);
        }
        .ai-mode-pill.active.is-staff {
            color: #f5c2b8;
            background: rgba(217, 143, 131, 0.22);
            border-color: rgba(217, 143, 131, 0.45);
        }
        .ai-unified-input {
            background: rgba(0, 0, 0, 0.35) !important;
            color: var(--ec-text) !important;
            font-size: 13px;
            padding: 8px 13px;
            border-radius: 8px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .ai-unified-input.is-customer {
            border: 1px solid rgba(201, 166, 107, 0.42) !important;
        }
        .ai-unified-input.is-customer:focus {
            border-color: #deb97c !important;
            box-shadow: 0 0 0 2px rgba(201, 166, 107, 0.18) !important;
        }
        .ai-unified-input.is-staff {
            border: 1px solid rgba(217, 143, 131, 0.42) !important;
        }
        .ai-unified-input.is-staff:focus {
            border-color: #d98f83 !important;
            box-shadow: 0 0 0 2px rgba(217, 143, 131, 0.18) !important;
        }
        .ai-unified-submit {
            font-size: 12px;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 8px;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .ai-unified-submit.is-customer {
            background: linear-gradient(135deg, #c9a66b, #8ea88a);
            color: #16110f;
            border: 1px solid rgba(222, 185, 124, 0.6);
        }
        .ai-unified-submit.is-customer:hover {
            filter: brightness(1.08);
        }
        .ai-unified-submit.is-staff {
            background: #d98f83;
            color: #1c1413;
            border: 1px solid rgba(217, 143, 131, 0.6);
        }
        .ai-unified-submit.is-staff:hover {
            filter: brightness(1.08);
        }
        .ai-sim-chip {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 9px;
            border-radius: 12px;
            color: #deb97c;
            background: rgba(201, 166, 107, 0.12);
            border: 1px solid rgba(201, 166, 107, 0.32);
            transition: all 0.15s ease;
        }
        .ai-sim-chip:hover {
            background: rgba(201, 166, 107, 0.24);
            color: #fff;
        }

        /* Right Column Order Receipt */
        .ai-cod-confirmed-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            color: #a3c49e;
            background: rgba(142, 168, 138, 0.16);
        }
        .ai-cod-pending-badge {
            font-size: 11px;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 10px;
            color: var(--ec-muted);
            background: rgba(255, 255, 255, 0.06);
        }
        .ai-upsell-tag {
            font-size: 10px;
            font-weight: 600;
            color: #a3c49e;
            margin-left: 4px;
        }
        .ai-next-best-card {
            padding: 10px 12px;
            background: rgba(201, 166, 107, 0.07);
            border: 1px solid rgba(201, 166, 107, 0.24);
        }
        .ai-upgrade-completed-pill {
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 8px;
            color: #b7d8b2;
            background: rgba(142, 168, 138, 0.16);
            border: 1px solid rgba(142, 168, 138, 0.35);
        }
        .ai-checkout-link-pill {
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 8px;
            color: #deb97c;
            background: rgba(201, 166, 107, 0.14);
            border: 1px solid rgba(201, 166, 107, 0.35);
        }
        .ai-upgrade-declined-pill {
            text-align: center;
            font-size: 12px;
            font-weight: 500;
            padding: 7px 12px;
            border-radius: 8px;
            color: var(--ec-muted);
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--ec-border);
        }
    </style>

    <script>
        function scrollChatToBottom() {
            var timeline = document.getElementById('ai-chat-timeline');
            if (timeline) {
                timeline.scrollTop = timeline.scrollHeight;
            }
            var chatStream = document.getElementById('chat-messages-container');
            if (chatStream) {
                chatStream.scrollTop = chatStream.scrollHeight;
            }
        }

        function focusUnifiedChatInput() {
            var input = document.getElementById('unified-chat-input');
            if (input) {
                input.focus();
            }
        }

        function escapeHtmlText(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }

        function currentFormattedTime() {
            var now = new Date();
            var hh = String(now.getHours()).padStart(2, '0');
            var mm = String(now.getMinutes()).padStart(2, '0');
            return hh + ':' + mm;
        }

        function appendOptimisticCustomerBubble(messageText, isStaffMode, personaName) {
            var container = document.getElementById('chat-messages-container') || document.getElementById('ai-chat-timeline');
            if (!container) {
                return;
            }

            var wrapper = document.createElement('div');
            var safeText = escapeHtmlText(messageText);
            var timeStr = currentFormattedTime();

            if (isStaffMode) {
                wrapper.className = 'd-flex justify-content-start w-100';
                wrapper.innerHTML =
                    '<div class="ai-msg-bubble ai-msg-outbound me-auto">' +
                        '<div class="ai-msg-sender-label is-zara">👩‍💼 ' + escapeHtmlText(personaName || 'Zara') + ' (Staff Override)</div>' +
                        '<div class="ai-msg-body">' + safeText + '</div>' +
                        '<div class="ai-msg-time">Staff · ' + timeStr + '</div>' +
                    '</div>';
            } else {
                wrapper.className = 'd-flex justify-content-end w-100';
                wrapper.innerHTML =
                    '<div class="ai-msg-bubble ai-msg-inbound ms-auto">' +
                        '<div class="ai-msg-sender-label is-customer">You (Customer)</div>' +
                        '<div class="ai-msg-body">' + safeText + '</div>' +
                        '<div class="ai-msg-time">' + timeStr + ' &checkmark;&checkmark;</div>' +
                    '</div>';
            }

            container.appendChild(wrapper);
            scrollChatToBottom();
        }

        function appendZaraTypingIndicator(personaName) {
            var container = document.getElementById('chat-messages-container') || document.getElementById('ai-chat-timeline');
            if (!container) {
                return;
            }

            var existing = document.getElementById('ai-typing-indicator-bubble');
            if (existing) {
                existing.remove();
            }

            var wrapper = document.createElement('div');
            wrapper.id = 'ai-typing-indicator-bubble';
            wrapper.className = 'd-flex justify-content-start w-100';
            wrapper.innerHTML =
                '<div class="ai-msg-bubble ai-msg-outbound me-auto">' +
                    '<div class="ai-msg-sender-label is-zara">✨ ' + escapeHtmlText(personaName || 'Zara') + ' (Laleen AI)</div>' +
                    '<div class="ai-msg-body" style="color: #d6c7c2; font-style: italic;">' +
                        '✨ ' + escapeHtmlText(personaName || 'Zara') + ' (Laleen AI) is typing' +
                        '<span class="ai-typing-dots">' +
                            '<span class="ai-typing-dot"></span>' +
                            '<span class="ai-typing-dot"></span>' +
                            '<span class="ai-typing-dot"></span>' +
                        '</span>' +
                    '</div>' +
                '</div>';

            container.appendChild(wrapper);
            scrollChatToBottom();
        }

        function swapAjaxDomFromHtml(htmlString) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(htmlString, 'text/html');

            var selectors = [
                '#ai-chat-timeline',
                '#ai-order-receipt-panel',
                '#ai-kpi-ribbon',
                '#ai-thread-list-panel',
                '#ai-chat-header',
                '#quick-reply-chips-row'
            ];

            selectors.forEach(function (sel) {
                var currentEl = document.querySelector(sel);
                var nextEl = doc.querySelector(sel);
                if (currentEl && nextEl) {
                    currentEl.innerHTML = nextEl.innerHTML;
                }
            });

            scrollChatToBottom();
            focusUnifiedChatInput();
        }

        function sendAjaxChatRequest(actionUrl, formData) {
            return fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html, application/json'
                }
            })
            .then(function (response) {
                return response.text();
            })
            .then(function (html) {
                if (html && html.indexOf('ai-chat-timeline') !== -1) {
                    swapAjaxDomFromHtml(html);
                } else {
                    window.location.reload();
                }
            })
            .catch(function () {
                window.location.reload();
            });
        }

        function setUnifiedChatMode(mode) {
            var form = document.getElementById('unified-chat-form');
            var input = document.getElementById('unified-chat-input');
            var submitBtn = document.getElementById('unified-chat-submit');
            var btnCustomer = document.getElementById('mode-btn-customer');
            var btnStaff = document.getElementById('mode-btn-staff');
            var chipsRow = document.getElementById('quick-reply-chips-row');

            if (!form || !input || !submitBtn) {
                return;
            }

            var personaName = form.getAttribute('data-persona-name') || 'Zara';

            if (mode === 'staff') {
                form.setAttribute('action', form.getAttribute('data-staff-action'));
                input.setAttribute('name', 'message_body');
                input.setAttribute('placeholder', form.getAttribute('data-staff-placeholder'));
                input.classList.remove('is-customer');
                input.classList.add('is-staff');

                submitBtn.textContent = 'Send as ' + personaName + ' ↵';
                submitBtn.classList.remove('is-customer');
                submitBtn.classList.add('is-staff');

                if (btnCustomer) btnCustomer.className = 'ai-mode-pill';
                if (btnStaff) btnStaff.className = 'ai-mode-pill active is-staff';
                if (chipsRow) chipsRow.classList.add('d-none');
            } else {
                form.setAttribute('action', form.getAttribute('data-customer-action'));
                input.setAttribute('name', 'customer_reply');
                input.setAttribute('placeholder', form.getAttribute('data-customer-placeholder'));
                input.classList.remove('is-staff');
                input.classList.add('is-customer');

                submitBtn.textContent = 'Send as Customer ↵';
                submitBtn.classList.remove('is-staff');
                submitBtn.classList.add('is-customer');

                if (btnStaff) btnStaff.className = 'ai-mode-pill';
                if (btnCustomer) btnCustomer.className = 'ai-mode-pill active is-customer';
                if (chipsRow) chipsRow.classList.remove('d-none');
            }

            focusUnifiedChatInput();
        }

        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || !form.tagName || form.tagName.toLowerCase() !== 'form') {
                return;
            }

            // 1. Unified Chat Input Form (#unified-chat-form)
            if (form.id === 'unified-chat-form') {
                e.preventDefault();
                var input = document.getElementById('unified-chat-input');
                var messageText = input ? input.value.trim() : '';
                if (!messageText) {
                    return;
                }

                var formData = new FormData(form);
                var isStaffMode = input && input.getAttribute('name') === 'message_body';
                var personaName = form.getAttribute('data-persona-name') || 'Zara';

                // Optimistic UI (0ms): append bubble, clear input, keep focus, scroll to bottom
                appendOptimisticCustomerBubble(messageText, isStaffMode, personaName);
                if (input) {
                    input.value = '';
                    input.focus();
                }

                // Typing Indicator if chatting as customer
                if (!isStaffMode) {
                    appendZaraTypingIndicator(personaName);
                }

                sendAjaxChatRequest(form.action, formData);
                return;
            }

            // 2. Quick-Reply Suggestion Chip Form (.ai-chip-form)
            if (form.classList && form.classList.contains('ai-chip-form')) {
                e.preventDefault();
                var chipInput = form.querySelector('input[name="customer_reply"]');
                var chipText = chipInput ? chipInput.value.trim() : '';
                if (!chipText) {
                    return;
                }

                var chipFormData = new FormData(form);
                var mainForm = document.getElementById('unified-chat-form');
                var persona = mainForm ? (mainForm.getAttribute('data-persona-name') || 'Zara') : 'Zara';

                appendOptimisticCustomerBubble(chipText, false, persona);
                appendZaraTypingIndicator(persona);
                focusUnifiedChatInput();

                sendAjaxChatRequest(form.action, chipFormData);
                return;
            }

            // 3. Clear / Reset Chat Form (.ai-reset-chat-form)
            if (form.classList && form.classList.contains('ai-reset-chat-form')) {
                e.preventDefault();
                var resetFormData = new FormData(form);
                sendAjaxChatRequest(form.action, resetFormData);
                return;
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            scrollChatToBottom();
            focusUnifiedChatInput();
        });

        window.addEventListener('load', function () {
            scrollChatToBottom();
            focusUnifiedChatInput();
        });
    </script>
@endsection
