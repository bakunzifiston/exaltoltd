{{--
    Search and social metadata for every storefront page.

    The Organization JSON-LD is what lets Google show the company logo beside "exalto"
    results; the favicon links supply the small icon on each result. Both need absolute
    URLs, so APP_URL must be the live https address.
--}}
@props(['title', 'description' => null, 'image' => null, 'type' => 'website'])
@php
    $company = $siteSettings['company_name'] ?? 'Exalto Engineering and Supply Solutions Ltd';
    $description = \Illuminate\Support\Str::limit(trim(strip_tags($description ?: ($siteSettings['meta_description'] ?? '') ?: 'Exalto transforms Rwanda\'s finest sugarcane and passion fruit into exceptional natural wines and juices. The Essence of Rwanda, Naturally Crafted.')), 160, '');
    $image = $image ?: asset('images/og-default.png');
    $canonical = url()->current();
    $organization = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => url('/').'#organization',
        'name' => $company,
        'url' => url('/'),
        'logo' => asset('images/logo-square.png'),
        'image' => asset('images/og-default.png'),
        'description' => $siteSettings['company_tagline'] ?? null,
        'sameAs' => collect($siteSocials ?? [])->pluck('value')->filter(fn ($url) => filter_var($url, FILTER_VALIDATE_URL))->values()->all() ?: null,
        'contactPoint' => ($primaryPhone ?? $primaryEmail ?? null) ? [array_filter([
            '@type' => 'ContactPoint',
            'contactType' => 'customer service',
            'telephone' => $primaryPhone?->value,
            'email' => $primaryEmail?->value,
            'areaServed' => 'RW',
        ])] : null,
    ]);
    $website = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $company,
        'url' => url('/'),
        'publisher' => ['@id' => url('/').'#organization'],
    ];
@endphp
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="index, follow">
<meta name="theme-color" content="#012B51">

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
<link rel="icon" type="image/png" sizes="96x96" href="{{ asset('favicon-96x96.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">

<meta property="og:site_name" content="{{ $company }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:locale" content="en_RW">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">

<script type="application/ld+json">{!! json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
<script type="application/ld+json">{!! json_encode($website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
