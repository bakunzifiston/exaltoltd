<x-layouts.admin title="E-Commerce Catalog">
    <section class="space-y-6">
        <header>
            <h2 class="text-2xl font-semibold text-slate-900">E-Commerce / Catalog</h2>
            <p class="mt-1 text-sm text-slate-600">Manage and publish sellable product data from ERP master records.</p>
        </header>

        <div class="grid gap-4 md:grid-cols-2">
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Product Management</h3>
                <p class="mt-2 text-sm text-slate-600">Publish ERP products, set ecommerce visibility and merchandising fields.</p>
                <a href="{{ route('admin.ecommerce.catalog.products') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Product Categories</h3>
                <p class="mt-2 text-sm text-slate-600">Define storefront categories and link them to existing product structure.</p>
                <a href="{{ route('admin.ecommerce.catalog.categories') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Product Variants</h3>
                <p class="mt-2 text-sm text-slate-600">Configure SKU options (size, color, pack) while preserving stock logic.</p>
                <a href="{{ route('admin.ecommerce.catalog.variants') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Product Images</h3>
                <p class="mt-2 text-sm text-slate-600">Manage product galleries, thumbnails, and storefront media quality.</p>
                <a href="{{ route('admin.ecommerce.catalog.images') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Videos</h3>
                <p class="mt-2 text-sm text-slate-600">Upload short storefront videos and control the order shown on home page.</p>
                <a href="{{ route('admin.ecommerce.catalog.videos') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 class="text-base font-semibold text-slate-900">Gallery</h3>
                <p class="mt-2 text-sm text-slate-600">Upload photos into albums for the gallery grid on the home page and /gallery.</p>
                <a href="{{ route('admin.ecommerce.catalog.gallery') }}" class="mt-3 inline-flex text-sm font-medium text-sky-700 hover:text-sky-800">Open module</a>
            </article>
        </div>
    </section>
</x-layouts.admin>
