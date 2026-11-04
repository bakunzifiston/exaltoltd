<x-layouts.storefront title="Home" description="At Exalto, we transform Rwanda's finest sugarcane and passion fruit into exceptional natural wines and juices, merging advanced engineering with time-honoured traditions.">
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-[#012b51] text-white">
        <img src="{{ asset('images/storefront/hero-exalto.jpg') }}" alt="" width="1920" height="800" class="absolute inset-0 h-full w-full object-cover object-right" aria-hidden="true">
        <div class="absolute inset-0 bg-gradient-to-r from-[#011a30] via-[#012b51]/85 to-[#012b51]/20" aria-hidden="true"></div>
        <div class="relative mx-auto flex min-h-[min(80vh,38rem)] max-w-7xl flex-col justify-center px-4 py-20 lg:px-8">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#cb8d20]">Exalto · Kamonyi, Rwanda</p>
                <h1 class="mt-4 text-4xl font-bold leading-[1.1] sm:text-5xl lg:text-[3.25rem]">The Essence of Rwanda, Naturally Crafted.</h1>
                <p class="mt-5 text-base leading-relaxed text-white/90 sm:text-lg">
                    At Exalto, we transform Rwanda’s finest sugarcane and passion fruit into exceptional natural wines and juices. By merging advanced engineering with time-honoured traditions, we create beverages that are as pure and vibrant as their source. Experience a refreshing taste of innovation and nature, crafted for both local enjoyment and global appreciation.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('storefront.shop') }}" class="inline-flex items-center justify-center rounded-lg bg-[#cb8d20] px-6 py-3 text-sm font-semibold text-[#012b51] shadow-sm transition hover:bg-[#b27a1b]">Discover Our Beverages</a>
                    <a href="{{ route('storefront.distribution') }}" class="inline-flex items-center justify-center rounded-lg border border-white/40 bg-white/10 px-6 py-3 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/20">Become a Distributor</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Vision & mission --}}
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-16 lg:px-8 lg:py-20">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#0d6a3a]">Purpose</p>
                <h2 class="mt-2 text-3xl font-bold text-[#012b51]">Our vision &amp; mission</h2>
            </div>
            <div class="mt-10 grid gap-6 md:grid-cols-2">
                <article class="rounded-2xl bg-[#012b51] p-8 text-white shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#cb8d20]">Our Vision</p>
                    <p class="mt-3 text-lg leading-relaxed">To be a globally recognized leader in the creation of innovative, natural beverages, showcasing the quality and potential of Rwandan-sourced ingredients.</p>
                </article>
                <article class="rounded-2xl border-t-4 border-[#0d6a3a] bg-off-white p-8 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#0d6a3a]">Our Mission</p>
                    <p class="mt-3 text-lg leading-relaxed text-slate-700">To produce and supply superior natural wines and juices using innovative, sustainable manufacturing processes, while creating value for our local farming partners and delivering refreshing, healthy products to our customers worldwide.</p>
                </article>
            </div>
        </div>
    </section>

    {{-- Key differentiators --}}
    <section class="bg-off-white">
        <div class="mx-auto max-w-7xl px-4 py-16 lg:px-8 lg:py-20">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#0d6a3a]">Why Exalto</p>
                <h2 class="mt-2 text-3xl font-bold text-[#012b51]">Rooted in Rwanda. Engineered for quality.</h2>
            </div>
            <div class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach([
                    ['Naturally Sourced', 'We use 100% locally grown sugarcane and passion fruit, supporting Rwandan farmers.', '#0d6a3a'],
                    ['Engineered for Purity', 'Our modern processing techniques ensure every bottle retains its natural flavour and health benefits.', '#012b51'],
                    ['Local & Global Reach', 'Proudly serving our community in Rwanda and sharing our craft with the world.', '#cb8d20'],
                ] as [$h, $t, $c])
                    <article class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm" style="border-top: 4px solid {{ $c }}">
                        <h3 class="text-lg font-semibold text-[#012b51]">{{ $h }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $t }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Featured beverages --}}
    <section class="border-y border-slate-200 bg-white">
        <div class="mx-auto max-w-7xl px-4 py-16 lg:px-8 lg:py-20">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#0d6a3a]">Our Beverages</p>
                    <h2 class="mt-2 text-3xl font-bold text-[#012b51]">Pure, vibrant flavours of Rwanda</h2>
                </div>
                <a href="{{ route('storefront.shop') }}" class="text-sm font-semibold text-[#012b51] underline-offset-4 hover:underline">View all beverages →</a>
            </div>
            <div class="mt-10 grid gap-8 md:grid-cols-2">
                @include('storefront._beverage-cards')
            </div>
            <figure class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <img src="{{ asset('images/storefront/passion-fruit-juice-banner.jpg') }}" alt="A bottle of La Vie passion juice lying among ripe passion fruits" width="1080" height="666" loading="lazy" class="max-h-[28rem] w-full object-cover object-bottom">
                <figcaption class="border-t border-slate-100 px-6 py-3 text-sm text-slate-600">Cold-pressed from ripe, locally sourced passion fruits to preserve nutrients and taste.</figcaption>
            </figure>
        </div>
    </section>

    {{-- Best sellers (live products from the shop) --}}
    <section class="border-b border-slate-200 bg-off-white">
        <div class="mx-auto max-w-7xl px-4 py-16 lg:px-8 lg:py-20">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#0d6a3a]">Customer favorites</p>
                    <h2 class="mt-2 text-3xl font-bold text-[#012b51]">Best sellers</h2>
                </div>
                <a href="{{ route('storefront.shop') }}" class="text-sm font-semibold text-[#012b51] underline-offset-4 hover:underline">Shop full catalog →</a>
            </div>
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($bestSellers->take(4) as $product)
                    @include('storefront._shop-product-card', ['product' => $product, 'layout' => 'grid', 'bestSellerIds' => $bestSellerIds])
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white/80 px-6 py-12 text-center text-sm text-slate-600">
                        Best sellers will appear here once products have recorded sales.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- Process teaser --}}
    <section class="bg-[#012b51] text-white">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-14 lg:grid-cols-[1fr_auto] lg:items-center lg:px-8">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#cb8d20]">The Exalto Difference</p>
                <h2 class="mt-2 text-2xl font-bold sm:text-3xl">Sourcing, extraction, fermentation and bottling, engineered with precision.</h2>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-200">Our name reflects our commitment to engineering and excellence. Our process is what sets us apart.</p>
            </div>
            <a href="{{ route('storefront.process') }}" class="inline-flex items-center justify-center rounded-lg bg-[#cb8d20] px-6 py-3 text-sm font-semibold text-[#012b51] hover:bg-[#b27a1b]">See Our Process</a>
        </div>
    </section>

    {{-- Quick links --}}
    <section class="bg-off-white">
        <div class="mx-auto max-w-7xl px-4 py-14 lg:px-8">
            <h2 class="text-center text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Quick Links</h2>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('storefront.shop') }}" class="rounded-full border border-[#012b51]/20 bg-white px-6 py-2.5 text-sm font-semibold text-[#012b51] hover:border-[#0d6a3a] hover:text-[#0d6a3a]">Sugarcane Wine</a>
                <a href="{{ route('storefront.shop') }}" class="rounded-full border border-[#012b51]/20 bg-white px-6 py-2.5 text-sm font-semibold text-[#012b51] hover:border-[#0d6a3a] hover:text-[#0d6a3a]">Passion Fruit Juice</a>
                <a href="{{ route('storefront.contact') }}" class="rounded-full bg-[#012b51] px-6 py-2.5 text-sm font-semibold text-white hover:bg-[#011f3a]">Contact Us</a>
            </div>
        </div>
    </section>
</x-layouts.storefront>
