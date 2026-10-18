{{-- Secondary nav for the Customers & Unified LTV Hub section. --}}
<div class="ec-subnav">
    <a href="{{ route('ecommerce.index') }}" class="ec-subnav-back" title="Back to Ecommerce">
        <i class="bx bx-chevron-left"></i>
    </a>
    <div class="ec-tabs mb-0">
        <a href="{{ route('ecommerce.customers.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.customers.*') ? 'active' : '' }}">Customers &amp; LTV</a>
        <a href="{{ route('ecommerce.sales.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.sales.index') ? 'active' : '' }}">Sales &amp; Usage</a>
        <a href="{{ route('ecommerce.net-profit.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.net-profit.index') ? 'active' : '' }}">Net Profit</a>
    </div>
</div>
