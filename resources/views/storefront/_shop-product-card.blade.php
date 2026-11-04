@php
    /** @var \App\Models\Product $product */
    $canAddToCart = (float) ($product->sellable_qty ?? 0) >= 1;
    $isNew = $product->created_at && $product->created_at->isAfter(now()->subDays(45));
    $isBestSeller = in_array((int) $product->id, $bestSellerIds ?? [], true);
    $compare = $product->compare_at_price !== null && (float) $product->compare_at_price > (float) $product->price;
    $discountPct = $compare ? (int) round((1 - (float) $product->price / (float) $product->compare_at_price) * 100) : null;
    $sold = max(0.0, (float) ($product->sold_units ?? 0));
    $rating = round(min(5, max(4, 4 + log(1 + $sold) / 5)), 1);
    $starsFilled = min(5, max(1, (int) round($rating)));
    $desc = $product->description ?: 'Natural Rwandan beverage by Exalto.';
    $inquiryUrl = route('storefront.contact') . '?subject=' . urlencode('Product Inquiry: ' . $product->name);
@endphp

@if ($layout === 'grid')
    <article class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04] transition duration-300 hover:-translate-y-1 hover:shadow-lg">
        <div class="relative aspect-[4/5] overflow-hidden bg-slate-100">
            @if ($product->image_path)
                <img
                    src="{{ asset('storage/' . $product->image_path) }}"
                    alt="{{ $product->name }}"
                    width="600"
                    height="750"
                    loading="lazy"
                    decoding="async"
                    class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                >
            @else
                <div class="flex h-full w-full items-center justify-center text-sm text-slate-500">No image</div>
            @endif
            <div class="pointer-events-none absolute left-2 top-2 flex max-w-[85%] flex-wrap gap-1.5">
                @if ($isNew)
                    <span class="rounded-md bg-[#012b51] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">New</span>
                @endif
                @if ($isBestSeller)
                    <span class="rounded-md bg-[#cb8d20] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#012b51]">Best seller</span>
                @endif
                @if ($compare && $discountPct)
                    <span class="rounded-md bg-rose-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Discount −{{ $discountPct }}%</span>
                @endif
            </div>
        </div>
        <div class="flex flex-1 flex-col p-5">
            <h2 class="text-lg font-semibold leading-snug text-slate-900">{{ $product->name }}</h2>
            <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-slate-600">{{ $desc }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-slate-700" title="Estimated from storefront sales momentum (not individual reviews).">
                <span class="flex items-center gap-0.5 text-amber-500" aria-hidden="true">
                    @for ($i = 1; $i <= 5; $i++)
                        <svg class="h-4 w-4 {{ $i <= $starsFilled ? 'fill-current' : 'fill-slate-200 text-slate-200' }}" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                    @endfor
                </span>
                <span class="text-xs text-slate-500">{{ number_format($rating, 1) }}/5</span>
            </div>
            <div class="mt-4 flex flex-wrap items-baseline gap-2 border-t border-slate-100 pt-4">
                @if ($compare)
                    <span class="text-sm text-slate-400 line-through">RWF {{ number_format((float) $product->compare_at_price, 2) }}</span>
                @endif
                <span class="text-lg font-bold text-[#012b51]">RWF {{ number_format((float) $product->price, 2) }}</span>
            </div>
            <div class="mt-5 flex flex-wrap gap-2">
                @if ($canAddToCart)
                    <form method="POST" action="{{ route('storefront.cart.add', $product->id) }}" class="flex-1 min-w-[8rem]">
                        @csrf
                        <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-[#012b51] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#011f3a]">
                            Add to cart
                        </button>
                    </form>
                @else
                    <a href="{{ $inquiryUrl }}" class="flex flex-1 min-w-[8rem] items-center justify-center gap-1.5 rounded-xl bg-[#cb8d20] px-4 py-2.5 text-sm font-semibold text-[#012b51] shadow-sm transition hover:bg-[#b27a1b]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                        Inquire
                    </a>
                @endif
                <a
                    href="{{ route('storefront.product', $product->id) }}"
                    class="inline-flex flex-1 min-w-[8rem] items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-[#012b51]/30 hover:bg-slate-50"
                >
                    View product
                </a>
                <form method="POST" action="{{ route('storefront.wishlist.add', $product->id) }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex h-[42px] w-[42px] items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                        title="Add to wishlist"
                        aria-label="Add {{ $product->name }} to wishlist"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                    </button>
                </form>
            </div>
        </div>
    </article>
@else
    <article class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-slate-900/[0.04] transition duration-300 hover:shadow-md md:flex-row">
        <div class="relative h-56 shrink-0 overflow-hidden bg-slate-100 md:h-auto md:w-56 lg:w-64">
            @if ($product->image_path)
                <img
                    src="{{ asset('storage/' . $product->image_path) }}"
                    alt="{{ $product->name }}"
                    width="400"
                    height="500"
                    loading="lazy"
                    decoding="async"
                    class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03] md:aspect-[4/5]"
                >
            @else
                <div class="flex h-full min-h-[14rem] w-full items-center justify-center text-sm text-slate-500">No image</div>
            @endif
            <div class="pointer-events-none absolute left-2 top-2 flex flex-wrap gap-1.5">
                @if ($isNew)
                    <span class="rounded-md bg-[#012b51] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">New</span>
                @endif
                @if ($isBestSeller)
                    <span class="rounded-md bg-[#cb8d20] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#012b51]">Best seller</span>
                @endif
                @if ($compare && $discountPct)
                    <span class="rounded-md bg-rose-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Discount −{{ $discountPct }}%</span>
                @endif
            </div>
        </div>
        <div class="flex flex-1 flex-col p-5 md:py-6 md:pr-6">
            <div class="flex flex-1 flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <h2 class="text-xl font-semibold text-slate-900">{{ $product->name }}</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600 lg:line-clamp-3">{{ $desc }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-slate-700" title="Estimated from storefront sales momentum.">
                        <span class="flex items-center gap-0.5 text-amber-500" aria-hidden="true">
                            @for ($i = 1; $i <= 5; $i++)
                                <svg class="h-4 w-4 {{ $i <= $starsFilled ? 'fill-current' : 'fill-slate-200 text-slate-200' }}" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                            @endfor
                        </span>
                        <span class="text-xs text-slate-500">{{ number_format($rating, 1) }}/5</span>
                    </div>
                </div>
                <div class="shrink-0 text-left lg:text-right">
                    @if ($compare)
                        <p class="text-sm text-slate-400 line-through">RWF {{ number_format((float) $product->compare_at_price, 2) }}</p>
                    @endif
                    <p class="text-2xl font-bold text-[#012b51]">RWF {{ number_format((float) $product->price, 2) }}</p>
                </div>
            </div>
            <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-5">
                @if ($canAddToCart)
                    <form method="POST" action="{{ route('storefront.cart.add', $product->id) }}">
                        @csrf
                        <button type="submit" class="inline-flex min-w-[10rem] items-center justify-center rounded-xl bg-[#012b51] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#011f3a]">
                            Add to cart
                        </button>
                    </form>
                @else
                    <a href="{{ $inquiryUrl }}" class="inline-flex min-w-[10rem] items-center justify-center gap-1.5 rounded-xl bg-[#cb8d20] px-5 py-2.5 text-sm font-semibold text-[#012b51] transition hover:bg-[#b27a1b]">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                        Inquire
                    </a>
                @endif
                <a
                    href="{{ route('storefront.product', $product->id) }}"
                    class="inline-flex min-w-[10rem] items-center justify-center rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-800 transition hover:border-[#012b51]/30 hover:bg-slate-50"
                >
                    View product
                </a>
                <form method="POST" action="{{ route('storefront.wishlist.add', $product->id) }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex h-[42px] w-[42px] items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                        title="Add to wishlist"
                        aria-label="Add {{ $product->name }} to wishlist"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                    </button>
                </form>
            </div>
        </div>
    </article>
@endif
