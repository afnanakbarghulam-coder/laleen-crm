<div class="ec-tabs">
    <a href="{{ route('ecommerce.partners.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.partners.index') ? 'active' : '' }}">Partner Ledger</a>
    <a href="{{ route('ecommerce.expenses.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.expenses.index') ? 'active' : '' }}">Expenses</a>
    <a href="{{ route('ecommerce.inventory.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.inventory.index') ? 'active' : '' }}">Inventory &amp; Production</a>
    <a href="{{ route('ecommerce.outbound.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.outbound.index') ? 'active' : '' }}">Outbound &amp; Usage</a>
</div>
