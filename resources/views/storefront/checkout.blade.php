<x-layouts.storefront title="Checkout">
    <section class="border-b border-slate-200/80 bg-[#f8fafc]">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:py-12 lg:px-8">
            <nav class="text-xs font-medium text-[#012b51]" aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <li><a href="{{ route('storefront.home') }}" class="text-slate-600 transition hover:text-[#012b51]">Home</a></li>
                    <li class="text-slate-400" aria-hidden="true">/</li>
                    <li><a href="{{ route('storefront.shop') }}" class="text-slate-600 transition hover:text-[#012b51]">Shop</a></li>
                    <li class="text-slate-400" aria-hidden="true">/</li>
                    <li><a href="{{ route('storefront.cart') }}" class="text-slate-600 transition hover:text-[#012b51]">Cart</a></li>
                    <li class="text-slate-400" aria-hidden="true">/</li>
                    <li class="font-semibold text-slate-900" aria-current="page">Checkout</li>
                </ol>
            </nav>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Checkout</h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600 sm:text-base">
                Almost there—tell us where to reach you and where to deliver. You’ll receive confirmation from our team once the order is reviewed.
            </p>
            @unless ($rows->isEmpty())
                <div class="mt-6 flex flex-wrap gap-4 text-xs font-medium uppercase tracking-[0.12em] text-slate-500">
                    <span class="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">1 Cart</span>
                    <span class="rounded-full bg-[#012b51] px-3 py-1 text-[#cb8d20]/95 ring-1 ring-[#011f3a]">2 Checkout</span>
                    <span class="rounded-full bg-white px-3 py-1 ring-1 ring-slate-200">3 Confirmation</span>
                </div>
            @endunless
        </div>
    </section>

    @if ($rows->isEmpty())
        <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8 lg:py-16">
            <div class="mx-auto max-w-lg rounded-2xl border border-dashed border-slate-200 bg-white px-8 py-14 text-center shadow-sm ring-1 ring-slate-900/[0.03]">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-700" aria-hidden="true">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
                <p class="mt-5 text-lg font-semibold text-slate-900">Your cart is empty</p>
                <p class="mt-2 text-sm text-slate-600">Add products before checkout. You can browse the shop or return home.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <a href="{{ route('storefront.shop') }}" class="inline-flex items-center justify-center rounded-xl bg-[#012b51] px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#011f3a]">Go to shop</a>
                    <a href="{{ route('storefront.cart') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-800 transition hover:bg-slate-50">Back to cart</a>
                </div>
            </div>
        </div>
    @else
        <div class="mx-auto max-w-7xl px-4 py-10 lg:px-8 lg:py-14">
            <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start xl:grid-cols-[minmax(0,1fr)_24rem]">
                {{-- Order summary first on mobile --}}
                <aside class="order-1 lg:order-2">
                    <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-lg shadow-slate-900/5 ring-1 ring-slate-900/[0.04] lg:sticky lg:top-[5.25rem]">
                        <div class="border-b border-slate-100 bg-gradient-to-br from-[#012b51] to-[#011a30] px-5 py-5 text-white">
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-[#cb8d20]/90">Your order</p>
                            <p class="mt-1 text-lg font-semibold">Summary</p>
                        </div>
                        <div class="max-h-[min(50vh,24rem)] space-y-3 overflow-y-auto px-5 py-4 lg:max-h-none lg:overflow-visible">
                            @foreach ($rows as $row)
                                @php
                                    $product = $row['product'];
                                    $q = (int) $row['quantity'];
                                    $line = (float) $row['line_total'];
                                    $unit = (float) $row['unit_price'];
                                @endphp
                                <div class="flex gap-3 border-b border-slate-100 pb-3 last:border-0 last:pb-0">
                                    <a href="{{ route('storefront.product', $product->id) }}" class="relative shrink-0 overflow-hidden rounded-lg bg-slate-100 ring-1 ring-slate-200">
                                        @if ($product->image_path)
                                            <img
                                                src="{{ asset('storage/' . $product->image_path) }}"
                                                alt="{{ $product->name }}"
                                                width="96"
                                                height="96"
                                                class="h-14 w-14 object-cover sm:h-16 sm:w-16"
                                                loading="lazy"
                                                decoding="async"
                                            >
                                        @else
                                            <span class="flex h-14 w-14 items-center justify-center text-[10px] text-slate-500 sm:h-16 sm:w-16">—</span>
                                        @endif
                                    </a>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold leading-snug text-slate-900">{{ $product->name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            × {{ $q }} <span aria-hidden="true">·</span> RWF {{ number_format($unit, 2) }} each
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-sm font-semibold tabular-nums text-slate-900">RWF {{ number_format($line, 2) }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="border-t border-slate-100 bg-slate-50/80 px-5 py-4">
                            <div class="flex items-end justify-between gap-3 border-b border-dashed border-slate-200 pb-4">
                                <span class="text-sm font-semibold text-slate-700">Subtotal</span>
                                <span class="text-xl font-bold tracking-tight text-[#012b51] tabular-nums">RWF {{ number_format((float) $subtotal, 2) }}</span>
                            </div>
                            <ul class="mt-3 space-y-1 text-xs leading-relaxed text-slate-500">
                                <li class="flex gap-2">
                                    <span class="mt-1.5 inline-block h-1 w-1 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                    Taxes and shipping are not calculated here—your quote may include adjustments.
                                </li>
                                <li class="flex gap-2">
                                    <span class="mt-1.5 inline-block h-1 w-1 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                    Payment and delivery timelines are confirmed by Exalto Green Ltd after submission.
                                </li>
                            </ul>
                            <div class="mt-4 rounded-lg border border-emerald-200/80 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-950">
                                Order total may be adjusted if inventory or fulfillment rules apply—our team contacts you before charging.
                            </div>
                            <p class="mt-4 flex items-center gap-2 text-xs text-slate-500">
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                                Delivered responsibly from Kamonyi, Rwanda · Customer data used only for this order.
                            </p>
                        </div>
                    </div>

                    @if ($rows->count() > 0)
                        <a href="{{ route('storefront.cart') }}" class="mt-5 flex items-center justify-center gap-2 text-sm font-semibold text-[#012b51] transition hover:text-[#011f3a] lg:hidden">
                            <span aria-hidden="true">←</span> Edit cart
                        </a>
                    @endif
                </aside>

                {{-- Form --}}
                <div class="order-2 lg:order-1">
                    @if ($errors->any())
                        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 shadow-sm">
                            <p class="font-semibold">Please fix the following:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 marker:text-red-400">
                                @foreach ($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-8 hidden lg:flex lg:items-center lg:justify-between">
                        <a href="{{ route('storefront.cart') }}" class="text-sm font-semibold text-[#012b51] underline-offset-2 transition hover:underline">&larr; Back to cart</a>
                        <div class="flex items-center gap-2 text-xs text-slate-500">
                            <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                            Secure checkout form
                        </div>
                    </div>

                    <form action="{{ route('storefront.checkout.place') }}" method="POST" class="space-y-6">
                        @csrf

                        @if ($errors->has('checkout'))
                            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-950">
                                {{ $errors->first('checkout') }}
                            </div>
                        @endif

                        <fieldset class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-900/[0.03] sm:p-6">
                            <legend class="px-1 text-xs font-semibold uppercase tracking-[0.12em] text-[#012b51]">Contact</legend>
                            <p class="mt-3 text-sm text-slate-600">We use this information to confirm payment and coordinate delivery.</p>
                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label for="checkout-name" class="mb-1.5 block text-sm font-medium text-slate-800">Full name <span class="text-rose-600">*</span></label>
                                    <input
                                        id="checkout-name"
                                        name="name"
                                        type="text"
                                        autocomplete="name"
                                        value="{{ old('name') }}"
                                        required
                                        maxlength="120"
                                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                                        class="w-full rounded-xl border px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2 {{ $errors->has('name') ? 'border-rose-300 bg-rose-50 focus:ring-rose-500/25' : 'border-slate-200 bg-white focus:border-[#012b51]/35 focus:ring-[#012b51]/20' }}"
                                        placeholder="e.g. Jean Baptiste Mugisha"
                                    >
                                    @error('name')<p class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="checkout-email" class="mb-1.5 block text-sm font-medium text-slate-800">Email <span class="text-rose-600">*</span></label>
                                    <input
                                        id="checkout-email"
                                        name="email"
                                        type="email"
                                        autocomplete="email"
                                        value="{{ old('email') }}"
                                        required
                                        maxlength="120"
                                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                        class="w-full rounded-xl border px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2 {{ $errors->has('email') ? 'border-rose-300 bg-rose-50 focus:ring-rose-500/25' : 'border-slate-200 bg-white focus:border-[#012b51]/35 focus:ring-[#012b51]/20' }}"
                                        placeholder="name@example.com"
                                    >
                                    @error('email')<p class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="checkout-phone" class="mb-1.5 block text-sm font-medium text-slate-800">Phone <span class="text-rose-600">*</span></label>
                                    <input
                                        id="checkout-phone"
                                        name="phone"
                                        type="tel"
                                        autocomplete="tel"
                                        value="{{ old('phone') }}"
                                        required
                                        maxlength="50"
                                        aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}"
                                        class="w-full rounded-xl border px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2 {{ $errors->has('phone') ? 'border-rose-300 bg-rose-50 focus:ring-rose-500/25' : 'border-slate-200 bg-white focus:border-[#012b51]/35 focus:ring-[#012b51]/20' }}"
                                        placeholder="+250 …"
                                    >
                                    @error('phone')<p class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm ring-1 ring-slate-900/[0.03] sm:p-6">
                            <legend class="px-1 text-xs font-semibold uppercase tracking-[0.12em] text-[#012b51]">Delivery</legend>
                            <p class="mt-3 text-sm text-slate-600">Full delivery location helps our logistics team estimate timing and courier handoff.</p>
                            <div class="mt-5 space-y-4">
                                <div>
                                    <label for="checkout-address" class="mb-1.5 block text-sm font-medium text-slate-800">Street address / district / landmark <span class="text-rose-600">*</span></label>
                                    <input
                                        id="checkout-address"
                                        name="address"
                                        type="text"
                                        autocomplete="street-address"
                                        value="{{ old('address') }}"
                                        required
                                        maxlength="255"
                                        aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}"
                                        class="w-full rounded-xl border px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2 {{ $errors->has('address') ? 'border-rose-300 bg-rose-50 focus:ring-rose-500/25' : 'border-slate-200 bg-white focus:border-[#012b51]/35 focus:ring-[#012b51]/20' }}"
                                        placeholder="House number, cell, closest landmark…"
                                    >
                                    @error('address')<p class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="checkout-notes" class="mb-1.5 block text-sm font-medium text-slate-800">Order notes <span class="text-slate-400">(optional)</span></label>
                                    <textarea
                                        id="checkout-notes"
                                        name="notes"
                                        rows="4"
                                        maxlength="1000"
                                        class="w-full resize-y rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm transition focus:border-[#012b51]/35 focus:outline-none focus:ring-2 focus:ring-[#012b51]/20"
                                        placeholder="Preferred delivery window, invoice details, allergens, gate codes…"
                                    >{{ old('notes') }}</textarea>
                                    @error('notes')<p class="mt-1.5 text-xs font-medium text-rose-700">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </fieldset>

                        <div class="flex flex-col gap-4 rounded-2xl border border-[#012b51]/20 bg-gradient-to-br from-[#012b51]/[0.07] to-[#011a30]/[0.05] px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="flex gap-3 text-sm text-slate-700">
                                <svg class="h-8 w-8 shrink-0 text-[#012b51]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                                <p>No payment is captured on this page. Submitting sends your cart and details to Exalto Green Ltd for review—we’ll reply with confirmation and payment options.</p>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="w-full rounded-xl bg-[#cb8d20] px-6 py-3.5 text-sm font-semibold text-[#012b51] shadow-md transition hover:bg-[#b27a1b] active:scale-[0.99] sm:w-auto sm:min-w-[14rem]"
                        >
                            Place order request · RWF {{ number_format((float) $subtotal, 2) }}
                        </button>
                        <p class="text-xs text-slate-500">
                            By placing this request you confirm the details above are accurate. Need help?
                            <a href="{{ route('storefront.contact') }}" class="font-semibold text-[#012b51] underline-offset-2 hover:underline">Contact us</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    @endif
</x-layouts.storefront>
