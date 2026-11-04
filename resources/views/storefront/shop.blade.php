@php
    $layoutQuery = fn (string $value) => http_build_query(array_merge(request()->except('page'), ['layout' => $value]));
@endphp

<x-layouts.storefront title="Our Beverages" description="Experience the pure, vibrant flavours of Rwanda in every sip. Our beverages are all-natural, with no artificial additives.">
    {{-- Page header / hero --}}
    <section class="relative overflow-hidden border-b border-[#011f3a]/30 bg-[#012b51] text-white">
        <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-[#cb8d20]/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-24 h-64 w-64 rounded-full bg-white/5 blur-3xl"></div>
        <div class="relative mx-auto max-w-7xl px-4 py-12 sm:py-16 lg:px-8">
            <nav class="text-xs font-medium text-[#cb8d20]/95" aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <li>
                        <a href="{{ route('storefront.home') }}" class="transition hover:text-white">Home</a>
                    </li>
                    <li class="text-white/50" aria-hidden="true">/</li>
                    <li class="text-white/90" aria-current="page">Our Beverages</li>
                </ol>
            </nav>
            <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl lg:text-[2.75rem]">
                Our Beverages
            </h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-100/90 sm:text-base">
                Experience the pure, vibrant flavours of Rwanda in every sip. Our beverages are all-natural, with no artificial additives.
            </p>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-10 lg:px-8 lg:py-14">
        {{-- Search & filters toolbar --}}
        <form method="GET" action="{{ route('storefront.shop') }}" class="space-y-4 rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm ring-1 ring-slate-900/[0.03] sm:p-5">
            <input type="hidden" name="layout" value="{{ $layout }}">

            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="relative flex-1 min-w-0">
                    <label for="shop-search" class="sr-only">Search products</label>
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </span>
                    <input
                        id="shop-search"
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search name, category, description, barcode…"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50/80 py-2.5 pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-400 transition focus:border-[#012b51]/40 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#012b51]/20"
                    >
                </div>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <span class="hidden text-xs font-medium uppercase tracking-wide text-slate-500 xl:inline">View</span>
                    <div class="flex rounded-lg border border-slate-200 p-0.5 bg-slate-50">
                        <a
                            href="{{ url()->current() }}?{{ $layoutQuery('grid') }}"
                            class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-xs font-semibold transition {{ $layout === 'grid' ? 'bg-white text-[#012b51] shadow-sm' : 'text-slate-600 hover:text-slate-900' }}"
                            aria-current="{{ $layout === 'grid' ? 'true' : 'false' }}"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                            Grid
                        </a>
                        <a
                            href="{{ url()->current() }}?{{ $layoutQuery('list') }}"
                            class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-xs font-semibold transition {{ $layout === 'list' ? 'bg-white text-[#012b51] shadow-sm' : 'text-slate-600 hover:text-slate-900' }}"
                            aria-current="{{ $layout === 'list' ? 'true' : 'false' }}"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                            List
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
                <div class="lg:col-span-3">
                    <label for="category" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Category</label>
                    <select
                        id="category"
                        name="category"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-[#012b51]/40 focus:outline-none focus:ring-2 focus:ring-[#012b51]/20"
                    >
                        <option value="">All categories</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label for="price_min" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Min price (RWF)</label>
                    <input
                        id="price_min"
                        type="number"
                        name="price_min"
                        value="{{ $price_min }}"
                        min="0"
                        step="100"
                        placeholder="Any"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm transition focus:border-[#012b51]/40 focus:outline-none focus:ring-2 focus:ring-[#012b51]/20"
                    >
                </div>
                <div class="lg:col-span-2">
                    <label for="price_max" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Max price (RWF)</label>
                    <input
                        id="price_max"
                        type="number"
                        name="price_max"
                        value="{{ $price_max }}"
                        min="0"
                        step="100"
                        placeholder="Any"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm transition focus:border-[#012b51]/40 focus:outline-none focus:ring-2 focus:ring-[#012b51]/20"
                    >
                </div>
                <div class="lg:col-span-3">
                    <label for="order" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sort by</label>
                    <select
                        id="order"
                        name="order"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 transition focus:border-[#012b51]/40 focus:outline-none focus:ring-2 focus:ring-[#012b51]/20"
                    >
                        <option value="name" @selected($order === 'name')>Name (A–Z)</option>
                        <option value="newest" @selected($order === 'newest')>Newest</option>
                        <option value="popular" @selected($order === 'popular')>Popular (best selling)</option>
                        <option value="price_asc" @selected($order === 'price_asc')>Price: Low to High</option>
                        <option value="price_desc" @selected($order === 'price_desc')>Price: High to Low</option>
                    </select>
                </div>
                <div class="flex flex-wrap gap-2 lg:col-span-2 lg:justify-end">
                    <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-xl bg-[#012b51] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#011f3a] hover:shadow active:scale-[0.98] sm:flex-none lg:min-w-[7rem]">
                        Apply filters
                    </button>
                    <a href="{{ route('storefront.shop', ['layout' => $layout]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                        Reset
                    </a>
                </div>
            </div>
        </form>

        @if (session('status'))
            <div class="mt-6 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm">
                <span class="mt-0.5 text-emerald-600" aria-hidden="true">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.872l-3.236 4.53L7.53 10.53a.75.75 0 00-1.06 1.061l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" /></svg>
                </span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <p class="mt-6 text-sm text-slate-600">
            Showing <span class="font-semibold text-slate-900">{{ $products->total() }}</span>
            {{ \Illuminate\Support\Str::plural('product', $products->total()) }}
            @if ($search !== '' || $category !== '' || $price_min !== '' || $price_max !== '')
                <span class="text-slate-500">matching your criteria</span>
            @endif
        </p>

        @if ($products->isEmpty())
            <div class="mt-10 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-8 py-16 text-center">
                <p class="text-base font-medium text-slate-800">No products match these filters.</p>
                <p class="mt-2 text-sm text-slate-600">Try clearing the search or widening the price range.</p>
                <a href="{{ route('storefront.shop', ['layout' => $layout]) }}" class="mt-6 inline-flex rounded-lg bg-[#012b51] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#011f3a]">View all products</a>
            </div>
        @elseif ($layout === 'grid')
            <div class="mt-8 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($products as $product)
                    @include('storefront._shop-product-card', ['layout' => 'grid'])
                @endforeach
            </div>
        @else
            <div class="mt-8 flex flex-col gap-5">
                @foreach ($products as $product)
                    @include('storefront._shop-product-card', ['layout' => 'list'])
                @endforeach
            </div>
        @endif

        @if (!$products->isEmpty())
            <div class="mt-12 border-t border-slate-200 pt-8">
                {{ $products->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
</x-layouts.storefront>
