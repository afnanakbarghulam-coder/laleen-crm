{{-- Secondary nav for the Financials & Inventory section only. --}}
<div class="ec-subnav">
    <a href="{{ route('ecommerce.index') }}" class="ec-subnav-back" title="Back to Ecommerce">
        <i class="bx bx-chevron-left"></i>
    </a>
    <div class="ec-tabs mb-0">
        <a href="{{ route('ecommerce.partners.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.partners.index') ? 'active' : '' }}">Partner Ledger</a>
        <a href="{{ route('ecommerce.expenses.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.expenses.index') ? 'active' : '' }}">Expenses</a>
        <a href="{{ route('ecommerce.inventory.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.inventory.index') ? 'active' : '' }}">Inventory &amp; Production</a>
        <a href="{{ route('ecommerce.pricing.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.pricing.index') ? 'active' : '' }}">Pricing &amp; Profit</a>
        <a href="{{ route('ecommerce.stock.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.stock.index') ? 'active' : '' }}">Stock Levels</a>
    </div>
</div>
