<div class="ec-tabs">
    <a href="{{ route('ecommerce.dashboard') }}" class="ec-tab {{ request()->routeIs('ecommerce.dashboard') ? 'active' : '' }}">P&L Overview</a>
    <a href="{{ route('ecommerce.partners.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.partners.index') ? 'active' : '' }}">Partner Ledger</a>
    <a href="{{ route('ecommerce.products.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.products.index') ? 'active' : '' }}">Unit Economics</a>
    <a href="{{ route('ecommerce.expenses.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.expenses.index') ? 'active' : '' }}">Expenses</a>
</div>
