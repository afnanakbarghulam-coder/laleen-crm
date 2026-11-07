{{-- Secondary nav for the Financials & Inventory section only. --}}
@php $regionParam = ['region' => $region ?? request('region', 'pk')]; @endphp
<div class="ec-subnav d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('ecommerce.index', $regionParam) }}" class="ec-subnav-back" title="Back to Ecommerce">
            <i class="bx bx-chevron-left"></i>
        </a>
        <div class="ec-tabs mb-0">
            <a href="{{ route('ecommerce.partners.index', $regionParam) }}" class="ec-tab {{ request()->routeIs('ecommerce.partners.index') ? 'active' : '' }}">Partner Ledger</a>
            <a href="{{ route('ecommerce.expenses.index', $regionParam) }}" class="ec-tab {{ request()->routeIs('ecommerce.expenses.index') ? 'active' : '' }}">Expenses</a>
            <a href="{{ route('ecommerce.inventory.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.inventory.index') ? 'active' : '' }}">Inventory &amp; Production</a>
            <a href="{{ route('ecommerce.pricing.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.pricing.index') ? 'active' : '' }}">Pricing &amp; Profit</a>
            <a href="{{ route('ecommerce.stock.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.stock.index') ? 'active' : '' }}">Stock Levels</a>
        </div>
    </div>
    @if (request()->routeIs('ecommerce.partners.index') || request()->routeIs('ecommerce.expenses.index'))
        @include('ecommerce.partials._region_tabs')
    @endif
</div>
