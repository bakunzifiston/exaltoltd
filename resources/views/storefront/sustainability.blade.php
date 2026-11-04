<x-layouts.storefront title="Sustainability & Community" description="Our roots are in Rwanda, and our commitment is to its future: empowering farmers, reducing waste and boosting local industry.">
    <x-storefront.page-hero eyebrow="Sustainability & Community" title="Our roots are in Rwanda, and our commitment is to its future." />
    <section class="bg-off-white">
        <div class="mx-auto grid max-w-7xl gap-6 px-4 py-16 md:grid-cols-3 lg:px-8 lg:py-20">
            @foreach([
                ['Empowering Farmers', 'We provide a stable, fair market for local sugarcane and passion fruit growers, contributing to sustainable livelihoods.', '#0d6a3a'],
                ['Reducing Waste', 'Our engineering-led process is designed for efficiency, minimizing water usage and turning organic by-products into valuable resources like natural fertilizer.', '#012b51'],
                ['Boosting Local Industry', 'We are proud to be a part of Rwanda’s growing agro-processing sector, adding value to local crops and creating skilled jobs.', '#cb8d20'],
            ] as [$h, $t, $c])
                <article class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm" style="border-top: 4px solid {{ $c }}">
                    <h2 class="text-xl font-bold text-[#012b51]">{{ $h }}</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600 sm:text-base">{{ $t }}</p>
                </article>
            @endforeach
        </div>
    </section>
</x-layouts.storefront>
