<x-layouts.admin title="E-Commerce Fulfillment">
    <section class="space-y-6">
        <header>
            <h2 class="text-2xl font-semibold text-slate-900">E-Commerce / Fulfillment</h2>
            <p class="mt-1 text-sm text-slate-600">Coordinate stock and logistics execution between online demand and operations.</p>
        </header>

        <div class="grid gap-4 md:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Inventory Synchronization</h3>
                <p class="mt-2 text-sm text-slate-600">Sync available quantities with ERP inventory and production batch availability.</p>
                <a href="{{ route('admin.ecommerce.fulfillment.inventory-sync') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Delivery / Shipping Management</h3>
                <p class="mt-2 text-sm text-slate-600">Define carriers, shipping rates, and dispatch logic linked to order lifecycle.</p>
                <a href="{{ route('admin.ecommerce.fulfillment.shipping') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
        </div>
    </section>
</x-layouts.admin>
