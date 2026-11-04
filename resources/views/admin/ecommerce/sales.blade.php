<x-layouts.admin title="E-Commerce Sales">
    <section class="space-y-6">
        <header>
            <h2 class="text-2xl font-semibold text-slate-900">E-Commerce / Sales</h2>
            <p class="mt-1 text-sm text-slate-600">Manage order-to-cash flows while reusing ERP sales and financial workflows.</p>
        </header>

        <div class="grid gap-4 md:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Shopping Cart</h3>
                <p class="mt-2 text-sm text-slate-600">Persist guest and customer carts with accurate item and pricing state.</p>
                <a href="{{ route('admin.ecommerce.sales.carts') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Orders Management</h3>
                <p class="mt-2 text-sm text-slate-600">Create and administer web orders mapped to ERP order processing rules.</p>
                <a href="{{ route('admin.ecommerce.sales.orders') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Order Status Tracking</h3>
                <p class="mt-2 text-sm text-slate-600">Track fulfillment progress and expose customer-visible status updates.</p>
                <a href="{{ route('admin.ecommerce.sales.statuses') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Payments</h3>
                <p class="mt-2 text-sm text-slate-600">Handle payment capture/reconciliation without breaking AR/AP calculations.</p>
                <a href="{{ route('admin.ecommerce.sales.payments') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Discounts &amp; Coupons</h3>
                <p class="mt-2 text-sm text-slate-600">Configure promotions and rule scopes for products, carts, and customers.</p>
                <a href="{{ route('admin.ecommerce.sales.discounts') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Returns &amp; Refunds</h3>
                <p class="mt-2 text-sm text-slate-600">Process reverse flows tied to stock, accounting, and customer balance records.</p>
                <a href="{{ route('admin.ecommerce.sales.returns') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
        </div>
    </section>
</x-layouts.admin>
