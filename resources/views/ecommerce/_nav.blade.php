<div class="ec-tabs">
    <a href="{{ route('ecommerce.partners.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.partners.index') ? 'active' : '' }}">Partner Ledger</a>
    <a href="{{ route('ecommerce.expenses.index') }}" class="ec-tab {{ request()->routeIs('ecommerce.expenses.index') ? 'active' : '' }}">Expenses</a>
</div>
