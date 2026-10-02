<div class="ec-tabs">
    <a href="{{ route('ecommerce.partners.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.partners.index') ? 'active' : '' }}">Partner Ledger</a>
    <a href="{{ route('ecommerce.expenses.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.expenses.index') ? 'active' : '' }}">Expenses</a>
    <a href="{{ route('ecommerce.inventory.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.inventory.index') ? 'active' : '' }}">Inventory &amp; Production</a>
    <a href="{{ route('ecommerce.pricing.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.pricing.index') ? 'active' : '' }}">Pricing &amp; Profit</a>
    <a href="{{ route('ecommerce.stock.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.stock.index') ? 'active' : '' }}">Stock Levels</a>
    <a href="{{ route('ecommerce.outbound.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.outbound.index') ? 'active' : '' }}">Sales &amp; Usage</a>
</div>
