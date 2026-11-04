@props(['eyebrow' => null, 'title', 'lead' => null])
<section class="relative overflow-hidden border-b-4 border-[#0d6a3a] bg-[#012b51] text-white">
    <div class="pointer-events-none absolute inset-y-0 right-0 hidden w-1/3 opacity-20 lg:block" aria-hidden="true"
         style="background-image: repeating-linear-gradient(0deg, transparent 0 34px, #cb8d20 34px 38px); mask-image: linear-gradient(90deg, transparent, #000);"></div>
    <div class="relative mx-auto max-w-7xl px-4 py-14 sm:py-20 lg:px-8">
        @if($eyebrow)<p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#cb8d20]">{{ $eyebrow }}</p>@endif
        <h1 class="mt-3 max-w-3xl text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">{{ $title }}</h1>
        @if($lead)<p class="mt-5 max-w-2xl text-base leading-relaxed text-slate-100/90 sm:text-lg">{{ $lead }}</p>@endif
        <div class="mt-6 h-1 w-24 bg-[#cb8d20]"></div>
    </div>
</section>
