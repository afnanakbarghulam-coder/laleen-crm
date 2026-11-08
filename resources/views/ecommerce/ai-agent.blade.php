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
    {{-- Compact Header + Hub Tabs --}}
    <div class="ec-header mb-2 py-1 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-baseline gap-2">
            <h4 class="mb-0" style="font-size: 1.05rem; font-weight: 600;">AI Sales &amp; Retention Agent</h4>
            <span class="text-uppercase fw-semibold" style="font-size: 10px; letter-spacing: .08em; color: var(--ec-gold);">
                WhatsApp Support &middot; {{ $region === 'qa' ? 'Qatar (QAR)' : 'Pakistan (PKR)' }}
            </span>
        </div>
    </div>

    @include('ecommerce.partials._hub_tabs')

    {{-- Single Sleek Low-Profile KPI & Action Bar --}}
    <div id="ai-kpi-ribbon" class="ai-kpi-ribbon">
        {{-- Left: Subtle Inline Metric Pills --}}
        <div class="ai-ribbon-metrics">
            <span class="ai-metric-pill">
                <span class="ai-metric-label">COD:</span>
                <strong>{{ number_format($codConfirmedCount) }}/{{ number_format($codTotalCount) }}</strong>
            </span>
            <span class="ai-metric-sep">&middot;</span>
            <span class="ai-metric-pill">
                <span class="ai-metric-label">Upgrades:</span>
                <strong style="color: #deb97c;">{{ number_format($upsellWonCodCount) }}</strong>
            </span>
            <span class="ai-metric-sep">&middot;</span>
            <span class="ai-metric-pill">
                <span class="ai-metric-label">AI Rev:</span>
                <strong class="ec-positive">{{ $currency }} {{ number_format($extraUpsellRevenue, 0) }}</strong>
            </span>
            <span class="ai-metric-sep">&middot;</span>
            <span class="ai-metric-pill">
                <span class="ai-metric-label">Recovered:</span>
                <strong style="color: #8ea88a;">{{ number_format($recoveredCartsCount ?? 0) }}</strong>
            </span>
            @if ($pendingApprovalMessagesCount > 0)
                <span class="ai-approval-count">{{ $pendingApprovalMessagesCount }} pending</span>
            @endif
        </div>

        {{-- Right: + New Blank Chat, Auto-Pilot / Co-Pilot Toggle, Run Scan, ⚙ Settings --}}
        <div class="ai-ribbon-actions">
            @moduleEdit('ecommerce')
                <form method="POST" action="{{ route('ecommerce.ai-agent.start-cold-chat') }}" class="m-0 d-inline">
                    @csrf
                    <input type="hidden" name="region" value="{{ $region }}">
                    <button type="submit" class="ai-top-pill-btn ai-new-chat-pill">
                        + New Blank Chat
                    </button>
                </form>

                <form method="POST" action="{{ route('ecommerce.ai-agent.settings') }}" class="m-0 d-inline">
                    @csrf
                    <input type="hidden" name="quick_toggle_mode" value="1">
                    <input type="hidden" name="mode" value="{{ $settings->isAutopilot() ? 'copilot' : 'autopilot' }}">
                    <button type="submit" class="ai-top-pill-btn {{ $settings->isAutopilot() ? 'is-autopilot' : '' }}" title="Mode: {{ $settings->isAutopilot() ? 'Auto-Pilot' : 'Co-Pilot' }} ({{ $driverLabel }})">
                        <span class="ai-mode-dot {{ $settings->isAutopilot() ? 'dot-green' : 'dot-gold' }}"></span>
                        {{ $settings->isAutopilot() ? 'Auto-Pilot' : 'Co-Pilot' }}
                    </button>
                </form>

                <form method="POST" action="{{ route('ecommerce.ai-agent.run-scan') }}" class="m-0 d-inline">
                    @csrf
                    <input type="hidden" name="region" value="{{ $region }}">
                    <button type="submit" class="ai-top-pill-btn">
                        <i class="bx bx-radar"></i> Run Scan
                    </button>
                </form>

                <button type="button" class="ai-top-pill-btn ai-settings-icon-btn" data-bs-toggle="modal" data-bs-target="#aiAgentSettingsModal" title="AI Sales Agent Settings">
                    ⚙
                </button>
            @endmoduleEdit
        </div>
    </div>

    {{-- Minimalist 3-Pane Flex Workspace (240px Left / Fluid Center / 260px Right) --}}
    <div class="ai-workspace-grid">
        {{-- LEFT COLUMN: Minimalist Inbox List (240px Width, Flat Divider Rows) --}}
        <div id="ai-thread-list-panel" class="ai-col-left ec-card p-0 mb-0 d-flex flex-column">
            {{-- Subtle Borderless Filter Switcher: All · Approval · Escalated --}}
            <div class="ai-clean-tabs">
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
                    Approval <span class="ai-tab-count">{{ $filterCounts['needs_approval'] ?? 0 }}</span>
                </a>
                <a href="{{ route('ecommerce.ai-agent.index', ['region' => $region, 'filter' => 'escalated']) }}"
                   class="ai-clean-tab {{ $isEscalated ? 'active' : '' }}">
                    Escalated <span class="ai-tab-count">{{ $filterCounts['escalated'] ?? 0 }}</span>
                </a>
            </div>

            {{-- Flat Edge-to-Edge Thread List Rows --}}
            <div class="ai-thread-scroll flex-grow-1">
                @forelse ($threads as $t)
                    @php
                        $isSelected = $activeThread && $activeThread->id === $t->id;
                        $badge = $t->compactStatusBadge();
                        if ($t->playbook === 'inbound_support' || stripos((string) ($badge['label'] ?? ''), 'Concierge') !== false) {
                            $badge['label'] = str_ireplace('Concierge', 'Support', (string) ($badge['label'] ?? 'Support'));
                        }
                        $lastMsg = $t->messages->last();
                        $previewText = $lastMsg
                            ? preg_replace('/\b([0-9a-f]{8})-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '#$1', str_replace('*', '', $lastMsg->message_body))
                            : 'No messages yet';
                    @endphp
                    <a href="{{ route('ecommerce.ai-agent.index', ['region' => $region, 'filter' => $filter, 'thread_id' => $t->id]) }}"
                       class="ai-minimal-thread-card {{ $isSelected ? 'active' : '' }}">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-0.5">
                            <span class="ai-thread-name text-truncate">
                                {{ $t->customer?->name ?: 'Valued Customer' }}
                            </span>
                            <span class="ai-status-badge variant-{{ $badge['variant'] }}">
                                <span class="ai-status-dot"></span>{{ $badge['label'] }}
                            </span>
                        </div>
                        <div class="ai-thread-preview">
                            {{ $previewText }}
                        </div>
                    </a>
                @empty
                    <div class="text-center py-5 my-auto" style="color: var(--ec-muted);">
                        <i class="bx bx-message-rounded" style="font-size: 24px; opacity: 0.45;"></i>
                        <div class="mt-1" style="font-size: 0.74rem;">No conversations in this filter.</div>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- CENTER COLUMN: Streamlined Conversation Canvas (Fluid 1fr, min-width: 0) --}}
        <div class="ai-col-center ec-card p-0 mb-0 d-flex flex-column overflow-hidden">
            @if ($activeThread)
                {{-- Clean, Properly Padded Flex Header Bar --}}
                <div id="ai-chat-header" class="ai-chat-header">
                    <div class="ai-chat-header-left">
                        <span class="ai-chat-customer-name">
                            {{ $activeThread->customer?->name ?: 'Valued Customer' }}
                        </span>
                        <span class="ai-chat-customer-phone">
                            +{{ $activeThread->customer?->phone }}
                        </span>
                        @php
                            $isColdOrderRef = empty($activeThread->order_group_id) || str_starts_with(strtoupper((string) $activeThread->order_group_id), 'COLD-');
                        @endphp
                        @if (!$isColdOrderRef)
                            <span class="ai-order-ref-pill">
                                Order {{ $activeThread->shortOrderRef() }}
                            </span>
                        @elseif ($activeThread->playbook === 'inbound_support' || $isColdOrderRef)
                            <span class="ai-order-ref-pill is-cold-chat">
                                Cold Chat
                            </span>
                        @endif
                    </div>

                    @moduleEdit('ecommerce')
                        <div class="ai-chat-header-right">
                            <form method="POST" action="{{ route('ecommerce.ai-agent.threads.reset-chat', $activeThread->id) }}" class="m-0 ai-reset-chat-form">
                                @csrf
                                <button type="submit" class="ai-ghost-header-btn" title="Clear / Reset Chat">
                                    ↺ Reset
                                </button>
                            </form>
                            <form method="POST" action="{{ route('ecommerce.ai-agent.threads.toggle-handoff', $activeThread->id) }}" class="m-0">
                                @csrf
                                <button type="submit" class="ai-ghost-header-btn {{ $activeThread->state === 'human_handoff' ? 'is-paused' : '' }}">
                                    @if ($activeThread->state === 'human_handoff')
                                        ▶ Resume
                                    @else
                                        ⏸ Pause AI
                                    @endif
                                </button>
                            </form>
                            <button type="button"
                                    id="ai-panels-toggle-btn"
                                    class="ai-ghost-header-btn ai-panels-toggle-btn"
                                    onclick="toggleAiSidePanels()"
                                    title="Collapse / Expand Side Panels">
                                ◫ Panels
                            </button>
                        </div>
                    @endmoduleEdit
                </div>

                {{-- Clean Message Bubbles Timeline (WhatsApp Customer Perspective: Customer on Right, Zara on Left) --}}
                <div id="ai-chat-timeline" class="ai-chat-stream flex-grow-1 d-flex flex-column gap-3">
                    <div id="chat-messages-container" class="d-flex flex-column gap-3">
                        @foreach ($activeThread->messages as $msg)
                            @if ($msg->sender_type === 'system_event')
                                {{-- Sleek 1-Line Centered System Event Pill --}}
                                <div class="d-flex justify-content-center shrink-0 flex-shrink-0 my-1">
                                    <div class="ai-system-pill shrink-0 flex-shrink-0 my-1 py-1 px-3 leading-normal text-xs rounded-full">
                                        {!! $msg->formattedHtmlBody() !!}
                                    </div>
                                </div>
                            @elseif ($msg->direction === 'inbound')
                                {{-- Customer Message Bubble (Aligned RIGHT — "You (Customer)") --}}
                                <div class="d-flex justify-content-end w-100">
                                    <div class="ai-msg-bubble ai-msg-inbound ms-auto">
                                        <div class="ai-msg-sender-label is-customer">You (Customer)</div>
                                        <div class="ai-msg-body">{!! $msg->formattedHtmlBody() !!}</div>
                                        <div class="ai-msg-time" data-timestamp="{{ $msg->created_at?->toIso8601String() }}" data-time-suffix=" ✓✓">{{ $msg->created_at?->format('H:i') }} &checkmark;&checkmark;</div>
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
                                                    <span class="ai-msg-time" data-timestamp="{{ $msg->created_at?->toIso8601String() }}">{{ $msg->created_at?->format('H:i') }}</span>
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
                                            <div class="ai-msg-time" data-timestamp="{{ $msg->created_at?->toIso8601String() }}" data-time-prefix="{{ $msg->sender_type === 'staff' ? 'Staff · ' : '' }}">
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
                    {{-- Compact 2-Row Bottom Input Tray (Row 1: Single-line scrollable chips; Row 2: Inline Mode Switch + Input + Send) --}}
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
                    <div class="ai-unified-composer px-3 py-2 flex-shrink-0">
                        {{-- Row 1: Single Horizontal Scrollable Quick-Reply Chips Strip + Tiny Hide/Show Toggle --}}
                        <div id="quick-reply-chips-wrapper" class="d-flex align-items-center justify-content-between gap-2 mb-1.5 {{ $defaultCustomerMode ? '' : 'd-none' }}" style="margin-bottom: 6px;">
                            <div id="quick-reply-chips-row" class="ai-chips-scroll-strip d-flex align-items-center flex-nowrap gap-1">
                                @foreach ($simulatorChips as $chip)
                                    <form method="POST" action="{{ route('ecommerce.ai-agent.threads.simulate-inbound', $activeThread->id) }}" class="d-inline flex-shrink-0 m-0 ai-chip-form">
                                        @csrf
                                        <input type="hidden" name="customer_reply" value="{{ $chip }}">
                                        <input type="hidden" name="resume_ai" value="1">
                                        <button type="submit" class="ai-sim-chip">{{ $chip }}</button>
                                    </form>
                                @endforeach
                            </div>
                            <button type="button"
                                    id="toggle-chips-btn"
                                    class="ai-hide-chips-btn flex-shrink-0"
                                    onclick="toggleQuickReplyChipsStrip()">
                                ✕ Hide Chips
                            </button>
                        </div>

                        {{-- Row 2: Compact Segmented Mode Switch + Prominent Input + Submit Button on 1 Single Line --}}
                        <form id="unified-chat-form"
                              method="POST"
                              action="{{ $defaultCustomerMode ? route('ecommerce.ai-agent.threads.simulate-inbound', $activeThread->id) : route('ecommerce.ai-agent.threads.send-manual', $activeThread->id) }}"
                              data-customer-action="{{ route('ecommerce.ai-agent.threads.simulate-inbound', $activeThread->id) }}"
                              data-staff-action="{{ route('ecommerce.ai-agent.threads.send-manual', $activeThread->id) }}"
                              data-customer-placeholder="{{ $customerPlaceholder }}"
                              data-staff-placeholder="{{ $staffPlaceholder }}"
                              data-persona-name="{{ $personaDisplayName }}"
                              class="d-flex align-items-center gap-2 m-0">
                            @csrf
                            <input type="hidden" name="resume_ai" value="1">

                            {{-- Compact Inline Segmented Mode Switcher --}}
                            <div class="ai-mode-pill-group flex-shrink-0" role="group" aria-label="Chat Sender Mode">
                                <button type="button"
                                        id="mode-btn-customer"
                                        class="ai-mode-pill {{ $defaultCustomerMode ? 'active is-customer' : '' }}"
                                        title="👤 Chat as Customer (Test AI)"
                                        onclick="setUnifiedChatMode('customer')">
                                    👤 Customer <span class="d-none">Chat as Customer (Test AI)</span>
                                </button>
                                <button type="button"
                                        id="mode-btn-staff"
                                        class="ai-mode-pill {{ !$defaultCustomerMode ? 'active is-staff' : '' }}"
                                        title="👩‍💼 Reply as {{ $personaDisplayName }} (Staff Override)"
                                        onclick="setUnifiedChatMode('staff')">
                                    👩‍💼 {{ $personaDisplayName }} <span class="d-none">Reply as {{ $personaDisplayName }} (Staff Override)</span>
                                </button>
                            </div>

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

        {{-- RIGHT COLUMN: Minimalist Context Panel (260px Width, Clean Compact Sections) --}}
        <div class="ai-col-right d-flex flex-column">
            <div id="ai-order-receipt-panel" class="ec-card p-3 mb-0 d-flex flex-column justify-content-between h-100">
                <div class="ai-context-scroll">
                    @if ($activeThread)
                        @php
                            $isColdOrderRef = empty($activeThread->order_group_id) || str_starts_with(strtoupper((string) $activeThread->order_group_id), 'COLD-');
                            $isInboundCatalogPreview = ($activeThread->playbook === 'inbound_support' || $isColdOrderRef) && $activeOrderSales->isEmpty();
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
                            {{-- Compact Catalog Pills for Cold Customer Chats (playbook === 'inbound_support') --}}
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1.5" style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                                <span class="fw-semibold" style="font-size: 0.8rem; color: var(--ec-text);">
                                    Catalog &middot; {{ $regionUpper }}
                                </span>
                                <span class="ai-cod-confirmed-badge" style="font-size: 0.65rem;">In Stock</span>
                            </div>
                            <div class="mb-2">
                                @forelse ($catalogProducts ?? [] as $catProd)
                                    @php
                                        $catPrice = $catProd->resolveBasePrice($regionUpper);
                                        $catStock = (int) ($regionUpper === 'QA' ? $catProd->stock_qatar : $catProd->stock_pakistan);
                                    @endphp
                                    <div class="ai-catalog-row">
                                        <div class="text-truncate pe-2" style="color: var(--ec-text);">
                                            <span class="fw-medium">{{ $catProd->name }}</span>
                                            <span class="ai-catalog-stock">&middot; {{ $catStock }} left</span>
                                        </div>
                                        <strong class="text-nowrap" style="color: #deb97c;">
                                            {{ $currency }} {{ number_format($catPrice, 0) }}
                                        </strong>
                                    </div>
                                @empty
                                    <div style="font-size: 0.75rem; color: var(--ec-muted);">No regional SKUs found.</div>
                                @endforelse
                            </div>

                            @if (!empty($catalogBundles) && count($catalogBundles) > 0)
                                <div class="mt-2 pt-2" style="border-top: 1px solid rgba(255,255,255,0.05);">
                                    <div class="text-uppercase fw-semibold mb-1" style="font-size: 0.65rem; letter-spacing: .05em; color: #a3c49e;">
                                        Bundles
                                    </div>
                                    @foreach ($catalogBundles as $bundle)
                                        @if ((float) $bundle->bundle_price > 0)
                                            <div class="ai-catalog-row" style="border-bottom: none; padding: 3px 0;">
                                                <span class="text-truncate pe-2" style="color: var(--ec-text);">{{ $bundle->name }}</span>
                                                <strong class="ec-positive text-nowrap">{{ $currency }} {{ number_format($bundle->bundle_price, 0) }}</strong>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        @else
                            {{-- Compact Order / Cart Receipt Header --}}
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1.5" style="border-bottom: 1px solid rgba(255,255,255,0.06);">
                                <span class="fw-semibold" style="font-size: 0.82rem; color: var(--ec-text);">
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
                            <div class="mb-2">
                                @if ($isAbandonedPreview && !empty($abandonedLineItems))
                                    @foreach ($abandonedLineItems as $cartItem)
                                        @php
                                            $cQty = max(1, (int) ($cartItem['quantity'] ?? 1));
                                            $cPrice = (float) ($cartItem['price'] ?? 0);
                                            $cTotal = round($cPrice * $cQty, 2);
                                        @endphp
                                        <div class="ai-catalog-row">
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
                                        <div class="ai-catalog-row">
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
                                        <div style="font-size: 0.75rem; color: var(--ec-muted);">No order items linked.</div>
                                    @endforelse
                                @endif

                                @if ($orderShipping > 0)
                                    <div class="d-flex justify-content-between align-items-center" style="font-size: 0.74rem; padding: 4px 0; color: var(--ec-muted);">
                                        <span>Shipping</span>
                                        <span>{{ $currency }} {{ number_format($orderShipping, 0) }}</span>
                                    </div>
                                @endif

                                <div class="d-flex justify-content-between align-items-center pt-2 mt-1 fw-bold" style="font-size: 0.84rem;">
                                    <span>Total</span>
                                    <span class="ec-positive">{{ $currency }} {{ number_format($orderGrandTotal, 0) }}</span>
                                </div>

                                @php
                                    $receiptDeliveryNote = $activeThread->delivery_notes
                                        ?: $activeOrderSales->firstWhere('delivery_notes', '!=', null)?->delivery_notes;
                                @endphp
                                @if ($receiptDeliveryNote)
                                    <div class="mt-2 p-2 rounded" style="font-size: 0.73rem; background: rgba(201, 166, 107, 0.09); border: 1px solid rgba(201, 166, 107, 0.25); color: #deb97c;">
                                        <div class="fw-bold" style="font-size: 0.65rem; letter-spacing: .04em; text-transform: uppercase;">
                                            📍 Delivery Note
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
                                <div class="ai-next-best-card p-2 rounded">
                                    <div class="text-uppercase fw-bold mb-0.5" style="font-size: 0.64rem; letter-spacing: .05em; color: #deb97c;">
                                        {{ $activeThread->playbook === 'day30_refill' ? 'VIP Refill Offer' : 'Next-Best Offer' }} ({{ number_format($activeDiscountPercent, 0) }}% OFF)
                                    </div>
                                    <div class="fw-semibold text-truncate" style="font-size: 0.78rem; color: var(--ec-text);">
                                        {{ $activeThread->offeredProduct->name }}
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-1" style="font-size: 0.74rem;">
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
                </div>

                {{-- Compact "Launch Playbook" Collapsible Accordion Drawer --}}
                @moduleEdit('ecommerce')
                    <details class="ai-playbook-drawer {{ $activeThread ? 'mt-3 pt-2' : '' }}" {{ !$activeThread ? 'open' : '' }}>
                        <summary class="ai-playbook-summary">
                            <span>Launch Playbook on Order ({{ $regionUpper }})</span>
                            <span class="ai-drawer-chevron">▾</span>
                        </summary>
                        <form method="POST" action="{{ route('ecommerce.ai-agent.trigger-cod-order') }}" class="mt-2">
                            @csrf
                            <select name="playbook" class="form-select form-select-sm ai-clean-select mb-1.5" style="margin-bottom: 6px; font-size: 0.75rem;">
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
                                <select name="order_group_id" class="form-select form-select-sm ai-clean-select" style="font-size: 0.75rem;" required>
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
                                <button type="submit" class="ai-top-pill-btn px-2.5">Start</button>
                            </div>
                        </form>
                    </details>
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
        /* Hide page footer on AI Agent screen to maximize vertical chat viewport */
        footer,
        .content-footer {
            display: none !important;
        }
        .container-p-y {
            padding-top: 0.5rem !important;
            padding-bottom: 0.35rem !important;
        }

        /* Sleek Low-Profile KPI & Navigation Ribbon */
        .ai-kpi-ribbon {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            padding: 8px 14px;
            border-radius: 10px;
            margin-bottom: 10px;
            background: rgba(255, 255, 255, 0.025);
            border: 1px solid rgba(255, 255, 255, 0.07);
        }
        .ai-ribbon-metrics {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 0.76rem;
            color: var(--ec-text);
            font-variant-numeric: tabular-nums;
        }
        .ai-metric-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .ai-metric-label {
            color: var(--ec-muted);
            font-weight: 500;
        }
        .ai-metric-sep {
            color: rgba(255, 255, 255, 0.22);
        }
        .ai-approval-count {
            font-size: 0.68rem;
            font-weight: 600;
            padding: 1px 7px;
            border-radius: 10px;
            background: rgba(201, 166, 107, 0.2);
            color: #deb97c;
        }
        .ai-ribbon-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }
        .ai-top-pill-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.76rem;
            font-weight: 500;
            padding: 5px 12px;
            border-radius: 7px;
            color: #e7e5e4;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .ai-top-pill-btn:hover {
            background: rgba(255, 255, 255, 0.09);
            border-color: rgba(255, 255, 255, 0.24);
            color: #fff;
        }
        .ai-new-chat-pill {
            color: #16110f;
            font-weight: 600;
            background: linear-gradient(135deg, #deb97c, #8ea88a);
            border-color: rgba(222, 185, 124, 0.55);
        }
        .ai-new-chat-pill:hover {
            color: #16110f;
            filter: brightness(1.08);
        }
        .ai-top-pill-btn.is-autopilot {
            border-color: rgba(142, 168, 138, 0.45);
            background: rgba(142, 168, 138, 0.12);
        }
        .ai-settings-icon-btn {
            padding: 5px 9px;
            font-size: 0.82rem;
        }
        .ai-mode-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }
        .ai-mode-dot.dot-green { background: #8ea88a; box-shadow: 0 0 6px #8ea88a; }
        .ai-mode-dot.dot-gold { background: #deb97c; }

        /* Minimalist 3-Pane Flex Workspace (240px Left / Fluid Center / 260px Right) */
        .ai-workspace-grid {
            display: flex;
            gap: 12px;
            align-items: stretch;
            width: 100%;
        }
        .ai-col-left {
            flex: 0 0 240px;
            width: 240px;
            min-width: 0;
            height: calc(100vh - 115px);
            min-height: 740px;
            max-height: 86vh;
            overflow: hidden;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .ai-col-center {
            flex: 1 1 auto;
            min-width: 0;
            height: calc(100vh - 115px);
            min-height: 740px;
            max-height: 86vh;
            overflow: hidden;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .ai-col-right {
            flex: 0 0 260px;
            width: 260px;
            min-width: 0;
            height: calc(100vh - 115px);
            min-height: 740px;
            max-height: 86vh;
            overflow: hidden;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @media (max-width: 1199px) {
            .ai-workspace-grid {
                flex-direction: column;
            }
            .ai-col-left,
            .ai-col-center,
            .ai-col-right {
                flex: 1 1 auto;
                width: 100%;
                height: auto;
                min-height: 480px;
                max-height: none;
            }
        }

        /* 1-Click ◫ Panels Collapse Mode (Expands Center Chat to 100% Width) */
        body.ai-panels-collapsed .ai-col-left,
        body.ai-panels-collapsed .ai-col-right {
            flex: 0 0 0px !important;
            width: 0px !important;
            min-width: 0px !important;
            opacity: 0;
            padding: 0 !important;
            margin: 0 !important;
            border-width: 0 !important;
            pointer-events: none;
        }
        body.ai-panels-collapsed .ai-workspace-grid {
            gap: 0;
        }
        body.ai-panels-collapsed .ai-panels-toggle-btn {
            color: #f3d79b;
            background: rgba(201, 166, 107, 0.2);
            border-color: rgba(201, 166, 107, 0.45);
        }

        /* Left Column: Subtle Borderless Filter Switcher & Flat Edge-to-Edge Thread Rows */
        .ai-clean-tabs {
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(0, 0, 0, 0.18);
            flex-shrink: 0;
        }
        .ai-clean-tab {
            flex: 1 1 0;
            text-align: center;
            font-size: 0.74rem;
            font-weight: 500;
            padding: 7px 6px;
            color: var(--ec-muted);
            text-decoration: none;
            border-bottom: 2px solid transparent;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .ai-clean-tab:hover {
            color: var(--ec-text);
            background: rgba(255, 255, 255, 0.02);
        }
        .ai-clean-tab.active {
            color: #fff;
            font-weight: 600;
            border-bottom-color: #d4a373;
            background: rgba(255, 255, 255, 0.03);
        }
        .ai-tab-count {
            font-size: 0.66rem;
            opacity: 0.65;
            margin-left: 2px;
        }

        .ai-thread-scroll {
            overflow-y: auto;
            scrollbar-width: thin;
        }
        .ai-minimal-thread-card {
            display: block;
            padding: 9px 12px;
            margin: 0;
            border-radius: 0;
            text-decoration: none;
            background: transparent;
            border-left: 2px solid transparent;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        .ai-minimal-thread-card:hover {
            background: rgba(255, 255, 255, 0.025);
        }
        .ai-minimal-thread-card.active {
            border-left: 2px solid #d4a373;
            background: rgba(255, 255, 255, 0.04);
        }
        .ai-thread-name {
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--ec-text);
            min-width: 0;
        }
        .ai-thread-preview {
            font-size: 0.73rem;
            color: var(--ec-text);
            opacity: 0.55;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 2px;
        }
        .ai-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.65rem;
            font-weight: 600;
            padding: 1px 6px;
            border-radius: 10px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .ai-status-dot {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: currentColor;
        }
        .ai-status-badge.variant-upgraded {
            color: #a3c49e;
            background: rgba(142, 168, 138, 0.15);
        }
        .ai-status-badge.variant-confirmed {
            color: #d6c7c2;
            background: rgba(255, 255, 255, 0.06);
        }
        .ai-status-badge.variant-awaiting {
            color: #deb97c;
            background: rgba(201, 166, 107, 0.13);
        }
        .ai-status-badge.variant-escalated {
            color: #eba199;
            background: rgba(168, 82, 74, 0.2);
        }

        /* Center Chat Header (Properly Padded Flex Bar, No Clipping) */
        .ai-chat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.07);
            background: rgba(26, 20, 18, 0.95);
            z-index: 10;
            flex-shrink: 0;
        }
        .ai-chat-header-left {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            overflow: hidden;
        }
        .ai-chat-customer-name {
            font-weight: 600;
            font-size: 0.92rem;
            color: var(--ec-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ai-chat-customer-phone {
            font-size: 0.75rem;
            color: var(--ec-text);
            opacity: 0.6;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .ai-order-ref-pill {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 6px;
            color: #deb97c;
            background: rgba(201, 166, 107, 0.12);
            border: 1px solid rgba(201, 166, 107, 0.25);
            white-space: nowrap;
            flex-shrink: 0;
        }
        .ai-order-ref-pill.is-cold-chat {
            color: #b7d8b2;
            background: rgba(142, 168, 138, 0.14);
            border-color: rgba(142, 168, 138, 0.3);
        }
        .ai-chat-header-right {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        .ai-ghost-header-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.74rem;
            font-weight: 500;
            padding: 4px 9px;
            border-radius: 6px;
            color: #d6c7c2;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s ease;
        }
        .ai-ghost-header-btn:hover {
            background: rgba(255, 255, 255, 0.09);
            border-color: rgba(255, 255, 255, 0.22);
            color: #fff;
        }
        .ai-ghost-header-btn.is-paused {
            background: rgba(142, 168, 138, 0.18);
            border-color: rgba(142, 168, 138, 0.45);
            color: #a3c49e;
        }

        /* Center Chat Stream (Starts Cleanly Below Header with padding-top: 18px) */
        #ai-chat-timeline,
        .ai-chat-stream {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            scroll-behavior: smooth;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: rgba(201, 166, 107, 0.35) rgba(0, 0, 0, 0.2);
            padding: 18px 18px 24px 18px;
            background: rgba(12, 9, 8, 0.25);
        }
        #ai-chat-timeline::-webkit-scrollbar {
            width: 6px;
        }
        #ai-chat-timeline::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
        }
        #ai-chat-timeline::-webkit-scrollbar-thumb {
            background: rgba(201, 166, 107, 0.35);
            border-radius: 9999px;
        }
        #ai-chat-timeline::-webkit-scrollbar-thumb:hover {
            background: rgba(201, 166, 107, 0.55);
        }

        @keyframes bubbleSlideUp {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .ai-bubble-animate {
            animation: bubbleSlideUp 0.22s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .ai-chips-scroll-strip {
            white-space: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
            max-width: 100%;
            padding-bottom: 1px;
        }
        .ai-chips-scroll-strip::-webkit-scrollbar {
            display: none;
        }
        .ai-hide-chips-btn {
            font-size: 10.5px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 6px;
            color: var(--ec-muted);
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s ease;
        }
        .ai-hide-chips-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }

        .ai-msg-bubble {
            max-width: 78%;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.5;
        }
        /* Customer Messages ($msg->direction === 'inbound'): Aligned RIGHT */
        .ai-msg-inbound {
            align-self: flex-end;
            margin-left: auto;
            background: rgba(142, 168, 138, 0.18);
            border: 1px solid rgba(142, 168, 138, 0.38);
            border-bottom-right-radius: 4px;
            color: var(--ec-text);
        }
        /* Zara AI / Staff Messages ($msg->direction === 'outbound'): Aligned LEFT */
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
            font-size: 0.76rem;
        }
        .ai-unified-composer {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
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

        /* Right Column Context Panel (260px Width, Clean Compact Rows & Accordion Drawer) */
        .ai-context-scroll {
            overflow-y: auto;
            flex: 1 1 auto;
            min-height: 0;
        }
        .ai-catalog-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.78rem;
            padding: 5px 0;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.05);
        }
        .ai-catalog-stock {
            font-size: 0.7rem;
            color: var(--ec-muted);
            margin-left: 3px;
        }
        .ai-playbook-drawer {
            border-top: 1px solid rgba(255, 255, 255, 0.07);
            flex-shrink: 0;
        }
        .ai-playbook-summary {
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--ec-muted);
            cursor: pointer;
            padding: 4px 0;
            user-select: none;
        }
        .ai-playbook-summary::-webkit-details-marker {
            display: none;
        }
        .ai-playbook-summary:hover {
            color: var(--ec-text);
        }
        .ai-drawer-chevron {
            font-size: 0.75rem;
            transition: transform 0.15s ease;
        }
        .ai-playbook-drawer[open] .ai-drawer-chevron {
            transform: rotate(180deg);
        }
        .ai-cod-confirmed-badge {
            font-size: 0.68rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 10px;
            color: #a3c49e;
            background: rgba(142, 168, 138, 0.16);
        }
        .ai-cod-pending-badge {
            font-size: 0.68rem;
            font-weight: 500;
            padding: 2px 7px;
            border-radius: 10px;
            color: var(--ec-muted);
            background: rgba(255, 255, 255, 0.06);
        }
        .ai-upsell-tag {
            font-size: 0.64rem;
            font-weight: 600;
            color: #a3c49e;
            margin-left: 3px;
        }
        .ai-next-best-card {
            padding: 8px 10px;
            background: rgba(201, 166, 107, 0.07);
            border: 1px solid rgba(201, 166, 107, 0.24);
        }
        .ai-upgrade-completed-pill {
            text-align: center;
            font-size: 0.74rem;
            font-weight: 600;
            padding: 7px 10px;
            border-radius: 7px;
            color: #b7d8b2;
            background: rgba(142, 168, 138, 0.16);
            border: 1px solid rgba(142, 168, 138, 0.35);
        }
        .ai-checkout-link-pill {
            text-align: center;
            font-size: 0.74rem;
            font-weight: 600;
            padding: 7px 10px;
            border-radius: 7px;
            color: #deb97c;
            background: rgba(201, 166, 107, 0.14);
            border: 1px solid rgba(201, 166, 107, 0.35);
        }
        .ai-upgrade-declined-pill {
            text-align: center;
            font-size: 0.74rem;
            font-weight: 500;
            padding: 6px 10px;
            border-radius: 7px;
            color: var(--ec-muted);
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--ec-border);
        }
    </style>

    <script>
        function scrollChatToBottom(smooth) {
            var timeline = document.getElementById('ai-chat-timeline');
            if (timeline) {
                if (smooth && typeof timeline.scrollTo === 'function') {
                    timeline.scrollTo({ top: timeline.scrollHeight, behavior: 'smooth' });
                } else {
                    timeline.scrollTop = timeline.scrollHeight;
                }
            }
        }

        function focusUnifiedChatInput() {
            var input = document.getElementById('unified-chat-input');
            if (input) {
                input.focus();
            }
        }

        function toggleAiSidePanels(forceState) {
            var isCurrentlyCollapsed = document.body.classList.contains('ai-panels-collapsed');
            var nextState = typeof forceState === 'boolean' ? forceState : !isCurrentlyCollapsed;

            if (nextState) {
                document.body.classList.add('ai-panels-collapsed');
            } else {
                document.body.classList.remove('ai-panels-collapsed');
            }

            try {
                localStorage.setItem('laleen_ai_panels_collapsed', nextState ? '1' : '0');
            } catch (err) {}

            var btn = document.getElementById('ai-panels-toggle-btn');
            if (btn) {
                btn.innerHTML = nextState ? '◫ Show Panels' : '◫ Panels';
            }

            setTimeout(function () {
                scrollChatToBottom(true);
            }, 60);
        }

        function toggleQuickReplyChipsStrip() {
            var row = document.getElementById('quick-reply-chips-row');
            var btn = document.getElementById('toggle-chips-btn');
            if (!row || !btn) {
                return;
            }

            var isHidden = row.classList.contains('d-none');
            if (isHidden) {
                row.classList.remove('d-none');
                btn.textContent = '✕ Hide Chips';
            } else {
                row.classList.add('d-none');
                btn.textContent = '+ Quick Chips';
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

        function localizeChatTimestamps() {
            var els = document.querySelectorAll('.ai-msg-time[data-timestamp]');
            els.forEach(function (el) {
                var iso = el.getAttribute('data-timestamp');
                if (!iso) {
                    return;
                }
                var dt = new Date(iso);
                if (isNaN(dt.getTime())) {
                    return;
                }
                var hh = String(dt.getHours()).padStart(2, '0');
                var mm = String(dt.getMinutes()).padStart(2, '0');
                var prefix = el.getAttribute('data-time-prefix') || '';
                var suffix = el.getAttribute('data-time-suffix') || '';
                el.textContent = prefix + hh + ':' + mm + suffix;
            });
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
                wrapper.className = 'd-flex justify-content-start w-100 ai-bubble-animate';
                wrapper.innerHTML =
                    '<div class="ai-msg-bubble ai-msg-outbound me-auto">' +
                        '<div class="ai-msg-sender-label is-zara">👩‍💼 ' + escapeHtmlText(personaName || 'Zara') + ' (Staff Override)</div>' +
                        '<div class="ai-msg-body">' + safeText + '</div>' +
                        '<div class="ai-msg-time">Staff · ' + timeStr + '</div>' +
                    '</div>';
            } else {
                wrapper.className = 'd-flex justify-content-end w-100 ai-bubble-animate';
                wrapper.innerHTML =
                    '<div class="ai-msg-bubble ai-msg-inbound ms-auto">' +
                        '<div class="ai-msg-sender-label is-customer">You (Customer)</div>' +
                        '<div class="ai-msg-body">' + safeText + '</div>' +
                        '<div class="ai-msg-time">' + timeStr + ' &checkmark;&checkmark;</div>' +
                    '</div>';
            }

            container.appendChild(wrapper);
            scrollChatToBottom(true);
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
            wrapper.className = 'd-flex justify-content-start w-100 ai-bubble-animate';
            wrapper.innerHTML =
                '<div class="ai-msg-bubble ai-msg-outbound me-auto">' +
                    '<div class="ai-msg-sender-label is-zara">✨ ' + escapeHtmlText(personaName || 'Zara') + ' (Laleen AI)</div>' +
                    '<div class="ai-msg-body" style="color: #d6c7c2; font-style: italic;">' +
                        '✨ ' + escapeHtmlText(personaName || 'Zara') + ' is typing' +
                        '<span class="ai-typing-dots">' +
                            '<span class="ai-typing-dot"></span>' +
                            '<span class="ai-typing-dot"></span>' +
                            '<span class="ai-typing-dot"></span>' +
                        '</span>' +
                    '</div>' +
                '</div>';

            container.appendChild(wrapper);
            scrollChatToBottom(true);
        }

        function applyJsonAjaxChatUpdate(payload) {
            var typingBubble = document.getElementById('ai-typing-indicator-bubble');
            if (typingBubble) {
                typingBubble.remove();
            }

            var container = document.getElementById('chat-messages-container') || document.getElementById('ai-chat-timeline');
            if (container) {
                if (payload.replace_timeline) {
                    container.innerHTML = payload.messages_html || '';
                } else if (payload.messages_html) {
                    container.insertAdjacentHTML('beforeend', payload.messages_html);
                }
            }

            var receiptEl = document.getElementById('ai-order-receipt-panel');
            if (receiptEl && payload.receipt_html) {
                receiptEl.innerHTML = payload.receipt_html;
            }

            var kpisEl = document.getElementById('ai-kpi-ribbon');
            if (kpisEl && payload.kpis_html) {
                kpisEl.innerHTML = payload.kpis_html;
            }

            var headerEl = document.getElementById('ai-chat-header');
            if (headerEl && payload.header_html) {
                headerEl.innerHTML = payload.header_html;
                var panelsBtn = document.getElementById('ai-panels-toggle-btn');
                if (panelsBtn && document.body.classList.contains('ai-panels-collapsed')) {
                    panelsBtn.innerHTML = '◫ Show Panels';
                }
            }

            var threadListEl = document.getElementById('ai-thread-list-panel');
            if (threadListEl && payload.thread_list_html) {
                threadListEl.innerHTML = payload.thread_list_html;
            }

            var chipsRowEl = document.getElementById('quick-reply-chips-row');
            if (chipsRowEl && payload.chips_html) {
                chipsRowEl.innerHTML = payload.chips_html;
            }

            localizeChatTimestamps();
            scrollChatToBottom(true);
            focusUnifiedChatInput();
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

            localizeChatTimestamps();
            scrollChatToBottom(true);
            focusUnifiedChatInput();
        }

        function appendAjaxErrorRetryBubble(errorMessage, actionUrl, formData) {
            var typingBubble = document.getElementById('ai-typing-indicator-bubble');
            if (typingBubble) {
                typingBubble.remove();
            }

            var container = document.getElementById('chat-messages-container') || document.getElementById('ai-chat-timeline');
            if (!container) {
                return;
            }

            var existingErr = document.getElementById('ai-ajax-error-bubble');
            if (existingErr) {
                existingErr.remove();
            }

            var wrapper = document.createElement('div');
            wrapper.id = 'ai-ajax-error-bubble';
            wrapper.className = 'd-flex justify-content-center w-100 my-1 ai-bubble-animate';
            wrapper.innerHTML =
                '<div class="ai-system-pill py-1.5 px-3 text-xs rounded-full" style="color: #f5c2b8; background: rgba(168, 82, 74, 0.22); border-color: rgba(168, 82, 74, 0.45); white-space: normal;">' +
                    '⚠ ' + escapeHtmlText(errorMessage || 'Message delivery delayed — click Retry to resend.') +
                    ' <button type="button" class="ai-ghost-header-btn ms-2" style="padding: 1px 7px; font-size: 0.7rem;" id="ai-retry-send-btn">↻ Retry</button>' +
                '</div>';

            container.appendChild(wrapper);
            scrollChatToBottom(true);

            var retryBtn = wrapper.querySelector('#ai-retry-send-btn');
            if (retryBtn && actionUrl && formData) {
                retryBtn.addEventListener('click', function () {
                    wrapper.remove();
                    var mainForm = document.getElementById('unified-chat-form');
                    var persona = mainForm ? (mainForm.getAttribute('data-persona-name') || 'Zara') : 'Zara';
                    appendZaraTypingIndicator(persona);
                    sendAjaxChatRequest(actionUrl, formData);
                });
            }
        }

        function sendAjaxChatRequest(actionUrl, formData) {
            return fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json, text/html'
                }
            })
            .then(function (response) {
                var contentType = response.headers.get('content-type') || '';
                if (contentType.indexOf('application/json') !== -1) {
                    return response.json().then(function (json) {
                        return { ok: response.ok, type: 'json', data: json };
                    });
                }
                return response.text().then(function (html) {
                    return { ok: response.ok, type: 'html', data: html };
                });
            })
            .then(function (res) {
                if (res.type === 'json' && res.data && (res.data.ok === true || res.data.status === 'ok')) {
                    applyJsonAjaxChatUpdate(res.data);
                } else if (res.type === 'html' && res.ok && res.data && res.data.indexOf('ai-chat-timeline') !== -1) {
                    var typingBubble = document.getElementById('ai-typing-indicator-bubble');
                    if (typingBubble) {
                        typingBubble.remove();
                    }
                    swapAjaxDomFromHtml(res.data);
                } else {
                    var errMsg = (res.data && res.data.message) ? res.data.message : 'Temporary connection issue while generating reply.';
                    appendAjaxErrorRetryBubble(errMsg, actionUrl, formData);
                }
            })
            .catch(function () {
                appendAjaxErrorRetryBubble('Network error while sending message.', actionUrl, formData);
            });
        }

        function setUnifiedChatMode(mode) {
            var form = document.getElementById('unified-chat-form');
            var input = document.getElementById('unified-chat-input');
            var submitBtn = document.getElementById('unified-chat-submit');
            var btnCustomer = document.getElementById('mode-btn-customer');
            var btnStaff = document.getElementById('mode-btn-staff');
            var chipsWrapper = document.getElementById('quick-reply-chips-wrapper');

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
                if (chipsWrapper) chipsWrapper.classList.add('d-none');
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
                if (chipsWrapper) chipsWrapper.classList.remove('d-none');
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

                // Optimistic UI (0ms): append bubble, clear input, keep focus, smooth scroll to bottom
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
            try {
                if (localStorage.getItem('laleen_ai_panels_collapsed') === '1') {
                    toggleAiSidePanels(true);
                }
            } catch (err) {}
            localizeChatTimestamps();
            scrollChatToBottom(false);
            focusUnifiedChatInput();
        });

        window.addEventListener('load', function () {
            scrollChatToBottom(false);
            focusUnifiedChatInput();
        });
    </script>
@endsection
