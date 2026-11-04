<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($pageTitle = ($title ?? 'Home').' - '.($siteSettings['company_name'] ?? 'Exalto Engineering and Supply Solutions Ltd'))
    <title>{{ $pageTitle }}</title>
    <x-storefront.seo-head :title="$pageTitle" :description="$description ?? null" :image="$image ?? null" :type="$ogType ?? ($attributes->get('og-type') ?? 'website')" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Source+Sans+3:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-off-white text-slate-800 antialiased">
    <div class="min-h-screen">
        <header class="sticky top-0 z-[100] border-b border-slate-200 border-t-4 border-t-navy bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 lg:px-8">
                <a href="{{ route('storefront.home') }}" class="flex items-center" aria-label="Exalto Green Ltd home">
                    <img src="{{ asset('images/brand/exalto-green-logo.svg') }}" alt="Exalto Green Ltd" width="220" height="71" class="h-12 w-auto sm:h-14">
                </a>

                <nav class="hidden items-center gap-5 text-sm font-semibold text-slate-700 xl:flex" aria-label="Primary">
                    @foreach([['storefront.home','Home'],['storefront.about','About Us'],['storefront.shop','Our Beverages'],['storefront.process','Our Process'],['storefront.distribution','Distribution'],['storefront.sustainability','Sustainability'],['storefront.contact','Contact']] as [$r,$label])
                        <a href="{{ route($r) }}" @class(['hover:text-[#0d6a3a]', 'text-[#0d6a3a] border-b-2 border-[#cb8d20]' => request()->routeIs($r === 'storefront.shop' ? ['storefront.shop','storefront.product'] : $r)])>{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-2">
                    <a href="{{ route('storefront.cart') }}" class="hidden rounded-lg px-2 py-2 text-sm font-semibold text-slate-700 hover:text-[#0d6a3a] sm:inline">Cart</a>
                    <a href="{{ route('storefront.wishlist') }}" class="hidden rounded-lg px-2 py-2 text-sm font-semibold text-slate-700 hover:text-[#0d6a3a] sm:inline">Wishlist</a>
                    <details class="relative xl:hidden">
                        <summary class="flex cursor-pointer list-none items-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800">Menu</summary>
                        <div class="absolute right-0 z-[120] mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                            @foreach([['storefront.home','Home'],['storefront.about','About Us'],['storefront.shop','Our Beverages'],['storefront.process','Our Process'],['storefront.distribution','Distribution'],['storefront.sustainability','Sustainability'],['storefront.contact','Contact'],['storefront.cart','Cart'],['storefront.wishlist','Wishlist']] as [$r,$label])
                                <a href="{{ route($r) }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-800 hover:bg-slate-100">{{ $label }}</a>
                            @endforeach
                        </div>
                    </details>
                @auth
                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="relative z-[110] rounded-lg bg-[#012b51] px-4 py-2 text-sm font-medium text-white hover:bg-[#011f3a]"
                    >
                        Dashboard
                    </a>
                @else
                    <a
                        href="{{ route('admin.login') }}"
                        class="relative z-[110] rounded-lg bg-[#012b51] px-4 py-2 text-sm font-medium text-white hover:bg-[#011f3a]"
                    >
                        Login
                    </a>
                @endauth
                </div>
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="mt-16 border-t-4 border-t-brand-green bg-[#012b51] text-white">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 md:grid-cols-4 lg:px-8">
                <div>
                    <img src="{{ asset('images/brand/exalto-green-symbol.svg') }}" alt="" width="44" height="46" class="mb-3 h-11 w-auto rounded-lg bg-white p-1.5" aria-hidden="true">
                    <h3 class="text-base font-semibold">{{ $siteSettings['company_name'] ?? 'Exalto Engineering and Supply Solutions Ltd' }}</h3>
                    <p class="mt-1 text-sm font-semibold text-[#cb8d20]">The Essence of Rwanda, Naturally Crafted.</p>
                    @if($primaryAddress)
                        <p class="mt-3 text-sm text-slate-200">{{ $primaryAddress->value }}</p>
                    @endif
                    @if($primaryPhone)
                        <a href="tel:+{{ preg_replace('/\D/', '', $primaryPhone->value) }}" class="mt-2 block text-sm text-slate-200 hover:text-[#cb8d20]">{{ $primaryPhone->value }}</a>
                    @endif
                    @if($primaryEmail)
                        <p class="mt-2 text-sm text-slate-200">{{ $primaryEmail->value }}</p>
                    @endif
                </div>
                <div>
                    <h3 class="text-base font-semibold">Quick Links</h3>
                    <ul class="mt-3 space-y-2 text-sm text-slate-200">
                        <li><a href="{{ route('storefront.about') }}" class="hover:text-[#cb8d20]">About Us</a></li>
                        <li><a href="{{ route('storefront.shop') }}" class="hover:text-[#cb8d20]">Our Beverages</a></li>
                        <li><a href="{{ route('storefront.process') }}" class="hover:text-[#cb8d20]">Our Process</a></li>
                        <li><a href="{{ route('storefront.distribution') }}" class="hover:text-[#cb8d20]">Distribution</a></li>
                        <li><a href="{{ route('storefront.sustainability') }}" class="hover:text-[#cb8d20]">Sustainability</a></li>
                        <li><a href="{{ route('storefront.contact') }}" class="hover:text-[#cb8d20]">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-base font-semibold">Our Beverages</h3>
                    <ul class="mt-3 space-y-2 text-sm text-slate-200">
                        <li><a href="{{ route('storefront.shop') }}" class="hover:text-[#cb8d20]">Exalto Natural Sugarcane Wine</a></li>
                        <li><a href="{{ route('storefront.shop') }}" class="hover:text-[#cb8d20]">Exalto Pure Passion Fruit Juice</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-base font-semibold">Newsletter</h3>
                    <form action="{{ route('storefront.contact.submit') }}" method="POST" class="mt-3 space-y-2">
                        @csrf
                        <input type="hidden" name="name" value="Newsletter Subscriber">
                        <input type="hidden" name="subject" value="Newsletter Subscription">
                        <input type="hidden" name="message" value="Please subscribe me to Exalto Engineering and Supply Solutions Ltd newsletter updates.">
                        <input type="text" name="phone" placeholder="Phone (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <input type="email" name="email" required placeholder="Email address" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="w-full rounded-lg bg-[#cb8d20] px-3 py-2 text-sm font-semibold text-white hover:bg-[#b27a1b]">Subscribe</button>
                    </form>
                    <p class="mt-3 text-xs text-slate-200">Follow us: LinkedIn | Instagram | Facebook</p>
                </div>
            </div>
        </footer>

        <x-storefront.whatsapp-float />
    </div>
</body>
</html>
