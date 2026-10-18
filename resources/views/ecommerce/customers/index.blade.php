@extends('layouts.app')
@section('title', 'Ecommerce Customers & LTV')

@include('ecommerce._styles')

@php
    $channelBadgeClasses = \App\Models\EcommerceSale::CHANNEL_BADGE_CLASSES;
@endphp

@section('content')
    <div class="ec-header">
        <div>
            <div class="ec-sub text-uppercase fw-bold mb-1" style="font-size: 11px; letter-spacing: .08em; color: var(--ec-gold);">Customers &amp; Sales</div>
            <h4>Customers &amp; LTV (Unified Database)</h4>
            <p>Cross-channel customer identity tracking across Shopify (Pakistan), Qatar Salons, and Organic WhatsApp.</p>
        </div>
        <a href="{{ route('ecommerce.sales.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bx bx-plus"></i> Log Sale in Sales &amp; Usage
        </a>
    </div>

    @include('ecommerce.partials._hub_tabs')

    {{-- Top-Level LTV Metrics (2-Column Even Grid) --}}
    <div class="row g-3 mb-4 grid grid-cols-2">
        <div class="col-md-6">
            <div class="ec-card mb-0 h-100">
                <h6>Total Identified Customers</h6>
                <div class="ec-value">{{ number_format($totalCustomers, 0) }}</div>
                <div class="ec-sub">
                    {{ ($channel ?? 'all') !== 'all' ? 'Customers with orders in ' . $channel : 'Unique normalized phone profiles across all channels' }}
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="ec-card ec-card-highlight mb-0 h-100">
                <h6>Total Tracked LTV Revenue</h6>
                <div class="ec-value ec-positive" style="font-size: 24px;">PKR {{ number_format($totalRevenue, 2) }}</div>
                <div class="ec-sub">
                    {{ ($channel ?? 'all') !== 'all' ? 'Total sales revenue in ' . $channel : 'Cumulative spend across all channels' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Search, Channel & Smart Segment Filter Bar --}}
    <div class="ec-card mb-3" style="overflow: visible; position: relative; z-index: 20;">
        <form id="customerFilterForm" method="GET" action="{{ route('ecommerce.customers.index') }}" class="row g-3 align-items-end">
            <div class="col-lg-5 col-md-12 position-relative">
                <label class="ec-sub d-block mb-1" style="font-size: 11px; text-transform: uppercase; letter-spacing: .04em;">Search Profile</label>
                <input
                    type="text"
                    name="search"
                    id="customerSearchInput"
                    value="{{ $search }}"
                    autocomplete="off"
                    class="form-control form-control-sm ec-form-control"
                    placeholder="Type customer name, phone, or email..."
                >
                <ul id="customerSearchSuggestions" class="ec-autocomplete-list" style="display: none;"></ul>
            </div>
            <div class="col-lg-3 col-md-5">
                <label class="ec-sub d-block mb-1" style="font-size: 11px; text-transform: uppercase; letter-spacing: .04em;">Select Channel</label>
                <select name="channel" class="form-select form-select-sm ec-form-select" onchange="this.form.submit()">
                    <option value="all" {{ ($channel ?? 'all') === 'all' ? 'selected' : '' }}>All Channels</option>
                    @foreach ($filterableChannels as $chOption)
                        <option value="{{ $chOption }}" {{ ($channel ?? 'all') === $chOption ? 'selected' : '' }}>{{ $chOption }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-5">
                <label class="ec-sub d-block mb-1" style="font-size: 11px; text-transform: uppercase; letter-spacing: .04em;">Smart Segment</label>
                <select name="segment" class="form-select form-select-sm ec-form-select" onchange="this.form.submit()">
                    <option value="all" {{ $segment === 'all' ? 'selected' : '' }}>All Customers</option>
                    <option value="whales" {{ in_array($segment, ['whales', 'top_20'], true) ? 'selected' : '' }}>Top 20% LTV</option>
                    <option value="repeat" {{ in_array($segment, ['repeat', 'vip'], true) ? 'selected' : '' }}>Repeat Buyers (2+ Orders)</option>
                    <option value="one_time" {{ in_array($segment, ['one_time', 'single'], true) ? 'selected' : '' }}>One-Time Buyers</option>
                    <option value="at_risk" {{ $segment === 'at_risk' ? 'selected' : '' }}>At-Risk (Last purchase 30-45 days ago)</option>
                </select>
            </div>
            <div class="col-lg-1 col-md-2 d-flex gap-2">
                @if ($search !== '' || ($channel ?? 'all') !== 'all' || $segment !== 'all')
                    <a href="{{ route('ecommerce.customers.index') }}" class="btn btn-outline-secondary btn-sm w-100" title="Reset Filters">Reset</a>
                @else
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                @endif
            </div>
        </form>
    </div>

    {{-- Customer Directory Table --}}
    <div class="ec-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h6 class="mb-0 d-flex align-items-baseline gap-2">
                <span>UNIFIED CUSTOMER DIRECTORY</span>
                <span class="text-2xl font-bold text-[#d98f83]" style="font-size: 24px; font-weight: 800; color: #d98f83; letter-spacing: 0; font-variant-numeric: tabular-nums;">({{ $customers->count() }})</span>
            </h6>
            <span class="ec-sub">Ranked by highest Lifetime Value (LTV)</span>
        </div>

        <div class="table-responsive">
            <table class="table ec-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Phone / WhatsApp</th>
                        <th>Channels Shopped</th>
                        <th class="text-end">Total Orders</th>
                        <th class="text-end">Lifetime Value (LTV)</th>
                        <th>Last Purchase</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        @php
                            $channels = $customer->sales->pluck('channel')->filter()->unique()->values();
                            $waName = $customer->name ?: 'there';
                            $latestProduct = $customer->latest_product;
                            $waText = "Hi {$waName}, checking in from Laleen! It's been a little while since your last order of {$latestProduct}...";
                            $waLink = 'https://wa.me/' . $customer->phone . '?text=' . rawurlencode($waText);
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('ecommerce.customers.show', $customer->id) }}" class="fw-bold text-decoration-none" style="color: #f3e9e7;">
                                    {{ $customer->name ?: 'Unnamed Customer' }}
                                </a>
                            </td>
                            <td>
                                <span style="font-variant-numeric: tabular-nums;">+{{ $customer->phone }}</span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    @forelse ($channels as $ch)
                                        <span class="ec-channel-badge {{ $channelBadgeClasses[$ch] ?? 'ec-channel-shopify' }}">
                                            {{ $ch }}
                                        </span>
                                    @empty
                                        <span class="ec-sub">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="text-end fw-semibold">{{ number_format($customer->total_orders, 0) }}</td>
                            <td class="text-end fw-bold" style="color: var(--ec-success); font-variant-numeric: tabular-nums;">
                                PKR {{ number_format($customer->total_spend, 2) }}
                            </td>
                            <td>
                                @if ($customer->last_purchase_at)
                                    <div>{{ $customer->last_purchase_at->format('d M Y') }}</div>
                                    <div class="ec-sub" style="font-size: 11px;">{{ $customer->last_purchase_at->diffForHumans() }}</div>
                                @else
                                    <span class="ec-sub">—</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-2">
                                    <a
                                        href="{{ route('ecommerce.customers.show', $customer->id) }}"
                                        class="ec-profile-action-btn"
                                    >
                                        <i class="bx bx-user"></i> View Profile
                                    </a>
                                    <a
                                        href="{{ $waLink }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="ec-wa-action-btn"
                                        title="{{ $waText }}"
                                    >
                                        <i class="bx bxl-whatsapp" style="font-size: 15px;"></i> WhatsApp
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No customers found matching your filter. Adjust the Channel or Smart Segment dropdowns above.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ Automated Customer Lifecycle Pipeline (LTV Expansion) ============ --}}
    <div id="refill-queue"></div>
    <div id="lifecycle-pipeline"></div>
    @php
        $totalPipelineCount = collect($lifecyclePipeline)->sum(fn ($stage) => $stage['items']->count());
        $defaultActiveStage = 'day7';
        foreach (['day7', 'day30', 'day60'] as $candidateKey) {
            if (($lifecyclePipeline[$candidateKey]['items'] ?? collect())->isNotEmpty()) {
                $defaultActiveStage = $candidateKey;
                break;
            }
        }
    @endphp
    <div class="ec-card mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <h6 class="mb-0">Automated Customer Lifecycle Pipeline (LTV Expansion)</h6>
                <p class="ec-sub mb-0 mt-1">
                    3-stage WhatsApp retention &amp; expansion engine across revenue channels. Log a customer phone number on sales to trigger one-click deep links.
                </p>
            </div>
            <span class="ec-badge ec-badge-injection">{{ $totalPipelineCount }} customer(s) in pipeline</span>
        </div>

        {{-- 3-Stage Pipeline Tabs --}}
        <div class="row g-2 mt-2 mb-3" role="tablist">
            @foreach ($lifecyclePipeline as $stageKey => $stage)
                @php
                    $isActiveStage = $stageKey === $defaultActiveStage;
                    $stageCount = $stage['items']->count();
                @endphp
                <div class="col-md-4">
                    <button
                        type="button"
                        class="w-100 text-start p-3 rounded-3 lifecycle-stage-tab {{ $isActiveStage ? 'active' : '' }}"
                        data-lifecycle-target="lifecycle-pane-{{ $stageKey }}"
                        style="background: {{ $isActiveStage ? 'rgba(201, 166, 107, 0.14)' : 'rgba(255, 255, 255, 0.02)' }}; border: 1px solid {{ $isActiveStage ? 'var(--ec-gold, #c9a66b)' : 'var(--ec-border, rgba(255,255,255,0.08))' }}; color: inherit; transition: all 0.15s ease;"
                    >
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold" style="font-size: 14px;">{{ $stage['title'] }}</span>
                            <span class="ec-badge {{ $stage['badge_class'] }}">{{ $stageCount }} due</span>
                        </div>
                        <div class="ec-sub" style="font-size: 12px;">{{ $stage['window_label'] }} &middot; {{ $stage['subtitle'] }}</div>
                    </button>
                </div>
            @endforeach
        </div>

        {{-- Stage Panes --}}
        @foreach ($lifecyclePipeline as $stageKey => $stage)
            @php $isActiveStage = $stageKey === $defaultActiveStage; @endphp
            <div
                id="lifecycle-pane-{{ $stageKey }}"
                class="lifecycle-stage-pane"
                style="{{ $isActiveStage ? '' : 'display: none;' }}"
            >
                @if ($stage['items']->isEmpty())
                    <div class="ec-chart-empty" style="height: 120px;">
                        No customers currently in <strong>{{ $stage['title'] }}</strong> ({{ $stage['window_label'] }}).
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table ec-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Phone</th>
                                    <th>Channel</th>
                                    <th>Last Bought</th>
                                    <th class="text-end">Days Ago</th>
                                    <th>Products</th>
                                    <th class="text-end">Spent</th>
                                    <th class="text-end">WhatsApp Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stage['items'] as $row)
                                    <tr>
                                        <td>{{ $row['customer_name'] === 'there' ? '—' : $row['customer_name'] }}</td>
                                        <td>{{ $row['display_phone'] }}</td>
                                        <td>
                                            <span class="ec-channel-badge {{ $channelBadgeClasses[$row['channel']] ?? 'ec-channel-shopify' }}">
                                                {{ $row['channel'] }}
                                            </span>
                                        </td>
                                        <td>{{ $row['last_purchase_at']->format('d M Y') }}</td>
                                        <td class="text-end">{{ $row['days_since'] }}d</td>
                                        <td>{{ $row['products']->implode(', ') }}</td>
                                        <td class="text-end">PKR {{ number_format($row['total_spent'], 2) }}</td>
                                        <td class="text-end">
                                            <a href="{{ $row['wa_link'] }}" target="_blank" rel="noopener" class="ec-wa-btn" title="{{ $row['wa_text'] }}">
                                                <i class="bi bi-whatsapp"></i> {{ $stage['cta_label'] }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <script>
        document.querySelectorAll('.lifecycle-stage-tab').forEach(function (tabBtn) {
            tabBtn.addEventListener('click', function () {
                const targetId = this.getAttribute('data-lifecycle-target');

                document.querySelectorAll('.lifecycle-stage-tab').forEach(function (btn) {
                    btn.classList.remove('active');
                    btn.style.background = 'rgba(255, 255, 255, 0.02)';
                    btn.style.borderColor = 'var(--ec-border, rgba(255,255,255,0.08))';
                });

                this.classList.add('active');
                this.style.background = 'rgba(201, 166, 107, 0.14)';
                this.style.borderColor = 'var(--ec-gold, #c9a66b)';

                document.querySelectorAll('.lifecycle-stage-pane').forEach(function (pane) {
                    pane.style.display = pane.id === targetId ? '' : 'none';
                });
            });
        });
    </script>

    <style>
        .ec-autocomplete-list {
            position: absolute;
            top: calc(100% + 4px);
            left: 12px;
            right: 12px;
            z-index: 1050;
            list-style: none;
            margin: 0;
            padding: 6px;
            background: #241e1c;
            border: 1px solid rgba(217, 143, 131, 0.35);
            border-radius: 12px;
            box-shadow: 0 14px 32px rgba(0, 0, 0, 0.55);
            max-height: 280px;
            overflow-y: auto;
        }

        .ec-autocomplete-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .ec-autocomplete-item:hover {
            background: rgba(217, 143, 131, 0.14);
        }

        .ec-autocomplete-name {
            font-size: 13px;
            font-weight: 600;
            color: #f3e9e7;
        }

        .ec-autocomplete-meta {
            font-size: 11.5px;
            color: var(--ec-muted);
        }

        .ec-autocomplete-ltv {
            font-size: 12px;
            font-weight: 700;
            color: var(--ec-success);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .ec-health-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        .ec-health-badge::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .ec-health-active {
            background: rgba(142, 168, 138, 0.18);
            border: 1px solid rgba(142, 168, 138, 0.42);
            color: #9fc49a;
        }

        .ec-health-slipping {
            background: rgba(201, 166, 107, 0.18);
            border: 1px solid rgba(201, 166, 107, 0.45);
            color: #deb97c;
        }

        .ec-health-churned {
            background: rgba(168, 82, 74, 0.2);
            border: 1px solid rgba(168, 82, 74, 0.45);
            color: #e6958c;
        }

        .ec-profile-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            background: rgba(217, 143, 131, 0.12);
            border: 1px solid rgba(217, 143, 131, 0.35);
            color: #e79a91;
            border-radius: 20px;
            padding: 5px 13px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.15s ease, transform 0.15s ease, border-color 0.15s ease;
        }

        .ec-profile-action-btn:hover {
            background: rgba(217, 143, 131, 0.24);
            border-color: rgba(217, 143, 131, 0.55);
            color: #f3e9e7;
            transform: translateY(-1px);
        }

        .ec-wa-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: rgba(37, 211, 102, 0.16);
            border: 1px solid rgba(37, 211, 102, 0.45);
            color: #4ddb84;
            border-radius: 20px;
            padding: 5px 14px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
        }

        .ec-wa-action-btn:hover {
            background: rgba(37, 211, 102, 0.28);
            border-color: rgba(37, 211, 102, 0.65);
            color: #72ef9f;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.18);
        }
    </style>

    <script>
        (function () {
            const SUGGESTIONS_URL = @json(route('ecommerce.customers.suggestions'));
            const searchInput = document.getElementById('customerSearchInput');
            const suggestionsList = document.getElementById('customerSearchSuggestions');
            const filterForm = document.getElementById('customerFilterForm');
            let debounceTimer = null;

            if (!searchInput || !suggestionsList || !filterForm) {
                return;
            }

            function hideSuggestions() {
                suggestionsList.style.display = 'none';
                suggestionsList.innerHTML = '';
            }

            function renderSuggestions(items) {
                if (!Array.isArray(items) || items.length === 0) {
                    hideSuggestions();
                    return;
                }

                suggestionsList.innerHTML = '';

                items.forEach(function (customer) {
                    const li = document.createElement('li');
                    li.className = 'ec-autocomplete-item';

                    const left = document.createElement('div');
                    const nameEl = document.createElement('div');
                    nameEl.className = 'ec-autocomplete-name';
                    nameEl.textContent = customer.name || 'Unnamed Customer';

                    const metaEl = document.createElement('div');
                    metaEl.className = 'ec-autocomplete-meta';
                    metaEl.textContent = '+' + (customer.phone || '') + (customer.email ? ' · ' + customer.email : '');

                    left.appendChild(nameEl);
                    left.appendChild(metaEl);

                    const right = document.createElement('div');
                    right.className = 'ec-autocomplete-ltv';
                    const spend = Number(customer.total_spend || 0).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                    right.textContent = 'PKR ' + spend;

                    li.appendChild(left);
                    li.appendChild(right);

                    li.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        searchInput.value = customer.name || customer.phone || '';
                        hideSuggestions();
                        filterForm.submit();
                    });

                    suggestionsList.appendChild(li);
                });

                suggestionsList.style.display = 'block';
            }

            searchInput.addEventListener('keyup', function (e) {
                if (e.key === 'Escape') {
                    hideSuggestions();
                    return;
                }

                clearTimeout(debounceTimer);
                const q = this.value.trim();

                if (q.length < 1) {
                    hideSuggestions();
                    return;
                }

                debounceTimer = setTimeout(function () {
                    fetch(SUGGESTIONS_URL + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then(function (res) { return res.ok ? res.json() : []; })
                        .then(renderSuggestions)
                        .catch(hideSuggestions);
                }, 250);
            });

            document.addEventListener('click', function (e) {
                if (!searchInput.contains(e.target) && !suggestionsList.contains(e.target)) {
                    hideSuggestions();
                }
            });
        })();
    </script>
@endsection
