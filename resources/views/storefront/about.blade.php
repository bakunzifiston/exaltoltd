<x-layouts.storefront title="About Us" description="Exalto Engineering and Supply Solutions Ltd was founded on a love for Rwanda's agricultural bounty and a drive for engineering excellence.">
    <x-storefront.page-hero eyebrow="About Us" title="Engineering excellence meets Rwanda’s agricultural bounty." />

    <section class="bg-off-white">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 lg:grid-cols-[1.2fr_1fr] lg:px-8 lg:py-20">
            <div>
                <h2 class="text-2xl font-bold text-[#012b51] sm:text-3xl">Our Story</h2>
                <div class="mt-5 space-y-4 text-base leading-relaxed text-slate-700">
                    <p>EXALTO ENGINEERING AND SUPPLY SOLUTIONS LTD was founded on a dual passion: a love for Rwanda’s rich agricultural bounty and a drive for engineering excellence.</p>
                    <p>We saw an opportunity to elevate two of our country’s natural treasures, sugarcane and passion fruit, into premium beverages. By applying meticulous engineering principles to the art of beverage making, we developed a unique process that captures the authentic taste and vitality of our ingredients.</p>
                    <p>From our base in Rwanda, we are not just a company; we are a team of innovators dedicated to quality, sustainability, and the growth of Rwanda’s agro-processing industry.</p>
                </div>
            </div>
            <figure class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <img src="{{ asset('images/storefront/hero-exalto.jpg') }}" alt="An engineer reviewing plans beside irrigation works, with sugarcane and passion fruit fields behind" loading="lazy" class="h-full w-full object-cover">
            </figure>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-16 md:grid-cols-2 lg:px-8">
            <article class="rounded-2xl bg-[#012b51] p-8 text-white">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#cb8d20]">Our Vision</p>
                <p class="mt-3 text-lg leading-relaxed">To be a globally recognized leader in the creation of innovative, natural beverages, showcasing the quality and potential of Rwandan-sourced ingredients.</p>
            </article>
            <article class="rounded-2xl border-t-4 border-[#0d6a3a] bg-off-white p-8 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#0d6a3a]">Our Mission</p>
                <p class="mt-3 text-lg leading-relaxed text-slate-700">To produce and supply superior natural wines and juices using innovative, sustainable manufacturing processes, while creating value for our local farming partners and delivering refreshing, healthy products to our customers worldwide.</p>
            </article>
        </div>
    </section>

    <section class="bg-off-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-12 lg:px-8">
            <p class="text-lg font-semibold text-[#012b51]">Experience the pure, vibrant flavours of Rwanda in every sip.</p>
            <a href="{{ route('storefront.shop') }}" class="rounded-lg bg-[#cb8d20] px-6 py-3 text-sm font-semibold text-[#012b51] hover:bg-[#b27a1b]">Discover Our Beverages</a>
        </div>
    </section>
</x-layouts.storefront>
