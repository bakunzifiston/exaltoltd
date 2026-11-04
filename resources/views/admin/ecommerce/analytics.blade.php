<x-layouts.admin title="E-Commerce Analytics">
    <section class="space-y-6">
        <header>
            <h2 class="text-2xl font-semibold text-slate-900">E-Commerce / Analytics</h2>
            <p class="mt-1 text-sm text-slate-600">Measure storefront performance using integrated ERP sales and inventory data.</p>
        </header>

        <div class="grid gap-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Sales Reports</h3>
                <p class="mt-2 text-sm text-slate-600">Analyze conversion, revenue, order mix, and return effects for e-commerce channels.</p>
                <a href="{{ route('admin.ecommerce.analytics.sales-reports') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
        </div>
    </section>
</x-layouts.admin>
