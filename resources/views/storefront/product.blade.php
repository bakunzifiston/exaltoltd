<x-layouts.storefront :title="$details->name"
    :description="$details->description ?: $details->name.' by Exalto, made in Rwanda. Order online for delivery.'"
    :image="$details->image_path ? asset('storage/'.$details->image_path) : null"
    og-type="product">
    @push('head')
        {{-- Product data for search results. No rating is included: there are no genuine customer reviews to report. --}}
        <script type="application/ld+json">{!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $details->name,
            'description' => $details->description ?: null,
            'image' => $details->image_path ? [asset('storage/'.$details->image_path)] : [asset('images/logo-square.png')],
            'sku' => $details->sku ?? $details->barcode,
            'gtin' => preg_match('/^\d{8}(\d{4,6})?$/', (string) $details->barcode) ? $details->barcode : null,
            'brand' => ['@type' => 'Brand', 'name' => ($siteSettings['company_name'] ?? 'Exalto Engineering and Supply Solutions Ltd')],
            'manufacturer' => ['@id' => url('/').'#organization'],
            'offers' => [
                '@type' => 'Offer',
                'url' => url()->current(),
                'priceCurrency' => 'RWF',
                'price' => number_format((float) $details->price, 2, '.', ''),
                'availability' => (float) ($details->sellable_qty ?? 0) >= 1 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'seller' => ['@id' => url('/').'#organization'],
            ],
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush
    @php
        $canAddToCart = (float) ($details->sellable_qty ?? 0) >= 1;
        $minQty = (int) ($details->min_order_qty ?? 5);
        $inquiryUrl = route('storefront.contact') . '?subject=' . urlencode('Product Inquiry: ' . $details->name);
    @endphp

    <section class="mx-auto max-w-6xl px-4 py-12 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <div class="grid gap-8 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-8">
                @if ($details->image_path)
                    <img src="{{ asset('storage/' . $details->image_path) }}" alt="{{ $details->name }}" class="h-72 w-full rounded-xl object-cover">
                @else
                    <div class="flex h-72 items-center justify-center rounded-xl bg-gradient-to-br from-[#fef7ef] to-[#f8fafc] text-center text-slate-500">
                        Premium Exalto Product Visual
                    </div>
                @endif
            </div>
            <div>
                <h1 class="mt-2 text-3xl font-bold">{{ $details->name }}</h1>
                <p class="mt-4 text-slate-600">{{ $details->description ?: 'Natural Rwandan beverage by Exalto.' }}</p>
                <div class="mt-5 space-y-2 text-sm">
                    <p><span class="font-medium">Price:</span> RWF {{ number_format((float) $details->price, 2) }}</p>
                    <p><span class="font-medium">Barcode:</span> {{ $details->barcode }}</p>
                    <p><span class="font-medium">Minimum online order:</span> <strong>{{ $minQty }}</strong> units</p>
                </div>

                @if ($canAddToCart)
                    <div class="mt-6 flex gap-3">
                        <form method="POST" action="{{ route('storefront.cart.add', $details->id) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="number" min="1" name="quantity" value="1" class="w-16 rounded-lg border border-slate-300 px-2 py-2 text-sm">
                            <button class="rounded-lg bg-[#012b51] px-4 py-2 text-sm font-medium text-white hover:bg-[#011f3a]">Add to Cart</button>
                        </form>
                        <form method="POST" action="{{ route('storefront.wishlist.add', $details->id) }}">
                            @csrf
                            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Wishlist</button>
                        </form>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">
                        Need fewer than {{ $minQty }} units? Find us at
                        <a href="#partner-stores" class="font-medium text-[#012b51] underline decoration-[#012b51]/30 underline-offset-2 hover:decoration-[#012b51]">partner supermarkets</a> near you.
                    </p>
                @else
                    <div class="mt-6 rounded-xl border border-[#012b51]/15 bg-[#e9eff6] p-5">
                        <p class="text-sm font-medium text-slate-800">This product is currently made to order.</p>
                        <p class="mt-1 text-sm text-slate-600">Submit an inquiry and our team will get back to you within 24 hours.</p>
                        <a
                            href="{{ $inquiryUrl }}"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-[#cb8d20] px-5 py-2.5 text-sm font-semibold text-[#012b51] shadow-sm transition hover:bg-[#b27a1b]"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                            Inquire About This Product
                        </a>
                    </div>
                    <form method="POST" action="{{ route('storefront.wishlist.add', $details->id) }}" class="mt-3">
                        @csrf
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Add to Wishlist</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Partner stores section --}}
        @if (isset($partners) && $partners->isNotEmpty())
            <div id="partner-stores" class="mt-8 rounded-xl border border-slate-200 bg-white p-6">
                <h3 class="text-base font-semibold text-slate-900">Find us at partner supermarkets</h3>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($partners as $partner)
                        @if ($partner->website_url)
                            <a href="{{ $partner->website_url }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-[#012b51]/30 hover:bg-white hover:shadow-sm">{{ $partner->name }}</a>
                        @else
                            <span class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700">{{ $partner->name }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="text-xl font-semibold">Product Variants</h2>
                <div class="mt-3 space-y-2 text-sm text-slate-700">
                    @forelse ($variants as $variant)
                        <p>{{ $variant->name }} ({{ $variant->barcode }})</p>
                    @empty
                        <p>No additional variants available yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="text-xl font-semibold">Reviews & Ratings</h2>
                <div class="mt-3 space-y-3 text-sm">
                    @forelse ($reviews as $review)
                        <article class="rounded-lg bg-slate-50 p-3">
                            <p class="font-medium">{{ $review->customer_name }} - {{ $review->rating }}/5</p>
                            <p class="text-slate-600">{{ $review->comment }}</p>
                        </article>
                    @empty
                        <p class="text-slate-600">No reviews yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</x-layouts.storefront>
