<x-layouts.admin title="E-Commerce Customers">
    <section class="space-y-6">
        <header>
            <h2 class="text-2xl font-semibold text-slate-900">E-Commerce / Customers</h2>
            <p class="mt-1 text-sm text-slate-600">Unify customer lifecycle data between storefront activity and ERP records.</p>
        </header>

        <div class="grid gap-4 md:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Customer Management</h3>
                <p class="mt-2 text-sm text-slate-600">Capture profiles, addresses, and account relationships with existing entities.</p>
                <a href="{{ route('admin.ecommerce.customers.profiles') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Reviews &amp; Ratings</h3>
                <p class="mt-2 text-sm text-slate-600">Moderate customer reviews and link sentiment to product performance.</p>
                <a href="{{ route('admin.ecommerce.customers.reviews') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5 md:col-span-2">
                <h3 class="text-base font-semibold text-slate-900">Notifications</h3>
                <p class="mt-2 text-sm text-slate-600">Define customer communication flows for order, shipping, and return events.</p>
                <a href="{{ route('admin.ecommerce.customers.notifications') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
        </div>
    </section>
</x-layouts.admin>
