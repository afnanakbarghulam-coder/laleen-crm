{{-- Secondary nav for the Customers & Unified LTV Hub section. --}}
@php $regionParam = ['region' => $region ?? request('region', 'pk')]; @endphp
<div class="ec-subnav d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('ecommerce.index', $regionParam) }}" class="ec-subnav-back" title="Back to Ecommerce">
            <i class="bx bx-chevron-left"></i>
        </a>
        <div class="ec-tabs mb-0">
            <a href="{{ route('ecommerce.customers.index', $regionParam) }}" class="ec-tab {{ request()->routeIs('ecommerce.customers.*') ? 'active' : '' }}">Customers &amp; LTV</a>
            <a href="{{ route('ecommerce.sales.index', $regionParam) }}" class="ec-tab {{ request()->routeIs('ecommerce.sales.index') ? 'active' : '' }}">Sales &amp; Usage</a>
            <a href="{{ route('ecommerce.net-profit.index', $regionParam) }}" class="ec-tab {{ request()->routeIs('ecommerce.net-profit.index') ? 'active' : '' }}">Net Profit</a>
            <a href="{{ route('ecommerce.ai-agent.index', $regionParam) }}" class="ec-tab {{ request()->routeIs('ecommerce.ai-agent.*') ? 'active' : '' }}">🤖 AI Sales Agent</a>
            <a href="{{ route('ecommerce.seo-agent.index', $regionParam) }}" class="ec-tab {{ request()->routeIs('ecommerce.seo-agent.*') ? 'active' : '' }}">🔍 SEO Command Center</a>
        </div>
    </div>
    @include('ecommerce.partials._region_tabs')
</div>
