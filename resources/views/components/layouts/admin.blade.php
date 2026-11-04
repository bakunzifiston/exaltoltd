@php
    $topbarLabel = match(true) {
        request()->routeIs('admin.dashboard')                                            => 'Dashboard',
        request()->routeIs('admin.sales.*')                                              => 'Sales',
        request()->routeIs('admin.payment-approvals.*')                                  => 'Payment Approvals',
        request()->routeIs('admin.stock-returns.*')                                      => 'Stock Returns',
        request()->routeIs('admin.production-samples.*')                                 => 'Production Samples',
        request()->routeIs('admin.notifications.*')                                      => 'Notifications',
        request()->routeIs('admin.customers.*')                                          => 'Customers',
        request()->routeIs('admin.suppliers.*')                                          => 'Suppliers',
        request()->routeIs('admin.inventory-records.*')                                  => 'Inventory',
        request()->routeIs('admin.productions.*')                                        => 'Production',
        request()->routeIs('admin.products.*')                                           => 'Products',
        request()->routeIs('admin.employees.*')                                          => 'Employees',
        request()->routeIs('admin.users.*')                                              => 'Users',
        request()->routeIs('admin.expenses.*') || request()->routeIs('admin.expense-categories.*') => 'Expenses',
        request()->routeIs('admin.reports.*')                                            => 'Reports',
        request()->routeIs('admin.team-performance')                                     => 'Team Performance',
        request()->routeIs('admin.data-health')                                          => 'Data Health',
        request()->routeIs('admin.contact-submissions.*')                                => 'Contact Messages',
        request()->routeIs('admin.settings.*')                                           => 'Settings',
        request()->routeIs('admin.ecommerce.*')                                          => 'E-Commerce',
        default                                                                          => 'Admin',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Admin' }} - Exalto Green Ltd Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Source+Sans+3:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/admin/app.css', 'resources/js/admin/app.js'])
</head>
<body class="h-full text-slate-900 antialiased">
    <div class="admin-shell">
        <div class="admin-frame">
        <aside id="adminSidebar" class="admin-sidebar">
            <div class="px-4 py-5 border-b border-white/10">
                <a href="{{ route('storefront.home') }}" class="mb-3 block rounded-xl bg-white p-3 shadow-sm" aria-label="Exalto Green Ltd">
                    <img src="{{ asset('images/brand/exalto-green-logo.svg') }}" alt="Exalto Green Ltd" width="220" height="71" class="h-auto w-full">
                </a>
                <p class="admin-brand-badge">Exalto Green Ltd</p>
            </div>

            <nav class="p-3 space-y-2">
                @php($coreOpen = request()->routeIs('admin.dashboard') || request()->routeIs('admin.users.*') || request()->routeIs('admin.employees.*') || request()->routeIs('admin.products.*') || request()->routeIs('admin.inventory-records.*') || request()->routeIs('admin.productions.*') || request()->routeIs('admin.sales.*') || request()->routeIs('admin.payment-approvals.*') || request()->routeIs('admin.stock-returns.*') || request()->routeIs('admin.production-samples.*') || request()->routeIs('admin.customers.*') || request()->routeIs('admin.suppliers.*') || request()->routeIs('admin.expenses.*') || request()->routeIs('admin.expense-categories.*') || request()->routeIs('admin.reports.*') || request()->routeIs('admin.contact-submissions.*'))
                @php($settingsOpen = request()->routeIs('admin.settings.*'))
                <details class="admin-nav-group" @if($coreOpen) open @endif>
                    <summary class="admin-nav-group-summary">
                        <span>Core ERP</span>
                        <span class="admin-nav-group-chevron">▾</span>
                    </summary>
                    <div class="mt-1 space-y-1">
                        @can('view-analytics')
                            <x-admin.nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                                Dashboard
                            </x-admin.nav-link>
                        @endcan
                        @can('manage-catalog')
                            <x-admin.nav-link :href="route('admin.employees.index')" :active="request()->routeIs('admin.employees.*')">
                                Employees
                            </x-admin.nav-link>
                            <x-admin.nav-link :href="route('admin.products.index')" :active="request()->routeIs('admin.products.*')">
                                Products
                            </x-admin.nav-link>
                            <x-admin.nav-link :href="route('admin.inventory-records.index')" :active="request()->routeIs('admin.inventory-records.*')">
                                Inventory
                            </x-admin.nav-link>
                        @endcan
                        @can('record-production')
                            <x-admin.nav-link :href="route('admin.productions.index')" :active="request()->routeIs('admin.productions.*')">
                                {{ auth()->user()->isProduction() ? 'My Production' : 'Production' }}
                            </x-admin.nav-link>
                        @endcan
                        @can('record-sales')
                            <x-admin.nav-link :href="route('admin.sales.index')" :active="request()->routeIs('admin.sales.*')">
                                {{ auth()->user()->isSales() ? 'My Sales' : 'Sales' }}
                            </x-admin.nav-link>
                        @endcan
                        @can('record-sales')
                            <x-admin.nav-link :href="route('admin.stock-returns.index')" :active="request()->routeIs('admin.stock-returns.*')">
                                Stock Returns
                            </x-admin.nav-link>
                        @endcan
                        @can('record-samples')
                            <x-admin.nav-link :href="route('admin.production-samples.index')" :active="request()->routeIs('admin.production-samples.*')">
                                Production Samples
                            </x-admin.nav-link>
                        @endcan
                        @can('approve-payments')
                            @php($pendingApprovals = \App\Models\SalePaymentSubmission::query()->pending()->count())
                            <x-admin.nav-link :href="route('admin.payment-approvals.index')" :active="request()->routeIs('admin.payment-approvals.*')">
                                <span class="flex items-center justify-between gap-2">
                                    Payment Approvals
                                    @if ($pendingApprovals > 0)
                                        <span class="rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-bold text-slate-900">{{ $pendingApprovals }}</span>
                                    @endif
                                </span>
                            </x-admin.nav-link>
                        @endcan
                        @can('manage-catalog')
                            <x-admin.nav-link :href="route('admin.customers.index')" :active="request()->routeIs('admin.customers.*')">
                                Customers
                            </x-admin.nav-link>
                            <x-admin.nav-link :href="route('admin.suppliers.index')" :active="request()->routeIs('admin.suppliers.*')">
                                Suppliers
                            </x-admin.nav-link>
                            <x-admin.nav-link
                                :href="route('admin.expenses.index')"
                                :active="request()->routeIs('admin.expenses.*') || request()->routeIs('admin.expense-categories.*')"
                            >
                                <span class="inline-flex items-center gap-2">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 fill-none stroke-current" stroke-width="1.8" aria-hidden="true">
                                        <path d="M9 3h6l1 2h4v16H4V5h5l1-2Z" />
                                        <path d="M8 10h8M8 14h5" />
                                    </svg>
                                    Expenses
                                </span>
                            </x-admin.nav-link>
                        @endcan
                        @can('view-analytics')
                            <x-admin.nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.index')">
                                Reports
                            </x-admin.nav-link>
                            <x-admin.nav-link :href="route('admin.reports.financial.index')" :active="request()->routeIs('admin.reports.financial.*')">
                                Financial Statements
                            </x-admin.nav-link>
                            <x-admin.nav-link :href="route('admin.reports.operational.show', 'raw-materials')" :active="request()->routeIs('admin.reports.operational.*')">
                                Operational Reports
                            </x-admin.nav-link>
                            <x-admin.nav-link :href="route('admin.reports.profitability')" :active="request()->routeIs('admin.reports.profitability*')">
                                Profitability
                            </x-admin.nav-link>
                            <x-admin.nav-link :href="route('admin.finance.opening-balances.index')" :active="request()->routeIs('admin.finance.*')">
                                Finance Ledgers
                            </x-admin.nav-link>
                            <x-admin.nav-link :href="route('admin.team-performance')" :active="request()->routeIs('admin.team-performance')">
                                Team Performance
                            </x-admin.nav-link>
                            <x-admin.nav-link :href="route('admin.data-health')" :active="request()->routeIs('admin.data-health')">
                                Data Health
                            </x-admin.nav-link>
                        @endcan
                        @can('manage-users')
                            <x-admin.nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                                Users
                            </x-admin.nav-link>
                        @endcan
                        @can('manage-catalog')
                            <x-admin.nav-link :href="route('admin.contact-submissions.index')" :active="request()->routeIs('admin.contact-submissions.*')">
                                Contact Messages
                            </x-admin.nav-link>
                        @endcan
                    </div>
                </details>

                @can('manage-catalog')
                <details class="admin-nav-group" @if($settingsOpen) open @endif>
                    <summary class="admin-nav-group-summary">
                        <span>Settings</span>
                        <span class="admin-nav-group-chevron">▾</span>
                    </summary>
                    <div class="mt-1 space-y-1">
                        <x-admin.nav-link :href="route('admin.settings.general')" :active="request()->routeIs('admin.settings.general*')">
                            General
                        </x-admin.nav-link>
                        <x-admin.nav-link :href="route('admin.settings.contacts.index')" :active="request()->routeIs('admin.settings.contacts.*')">
                            Contact Channels
                        </x-admin.nav-link>
                        <x-admin.nav-link :href="route('admin.settings.email-routing.index')" :active="request()->routeIs('admin.settings.email-routing.*')">
                            Email Routing
                        </x-admin.nav-link>
                        <x-admin.nav-link :href="route('admin.settings.images.index')" :active="request()->routeIs('admin.settings.images.*')">
                            Site Images
                        </x-admin.nav-link>
                    </div>
                </details>
                @endcan

                @can('manage-catalog')
                @php($ecommerceOpen = request()->routeIs('admin.ecommerce.*'))
                <details class="admin-nav-group" @if($ecommerceOpen) open @endif>
                    <summary class="admin-nav-group-summary">
                        <span>E-Commerce</span>
                        <span class="admin-nav-group-chevron">▾</span>
                    </summary>
                    <div class="mt-1 space-y-1">
                        <x-admin.nav-link :href="route('admin.ecommerce.catalog')" :active="request()->routeIs('admin.ecommerce.catalog')">
                            Catalog
                        </x-admin.nav-link>
                        <x-admin.nav-link :href="route('admin.ecommerce.customers')" :active="request()->routeIs('admin.ecommerce.customers')">
                            Customers
                        </x-admin.nav-link>
                        <x-admin.nav-link :href="route('admin.ecommerce.sales', ['module' => 'orders', 'channel' => 'online'])" :active="request()->routeIs('admin.ecommerce.sales')">
                            Orders
                        </x-admin.nav-link>
                        <x-admin.nav-link :href="route('admin.ecommerce.fulfillment')" :active="request()->routeIs('admin.ecommerce.fulfillment')">
                            Fulfillment
                        </x-admin.nav-link>
                        <x-admin.nav-link :href="route('admin.ecommerce.analytics')" :active="request()->routeIs('admin.ecommerce.analytics')">
                            Analytics
                        </x-admin.nav-link>
                    </div>
                </details>
                @endcan
            </nav>

            @if (auth()->check() && auth()->user())
                <div class="mt-6 rounded-2xl border border-white/15 bg-white/8 p-3">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 text-base font-semibold text-white ring-1 ring-white/20">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-white">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-200/70">{{ auth()->user()->roleLabel() }}</p>
                        </div>
                    </div>

                    @can('manage-users')
                        <a
                            href="{{ route('admin.users.edit', auth()->user()) }}"
                            class="mt-3 inline-flex w-full items-center justify-center rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-xs font-medium text-white transition hover:bg-white/20"
                        >
                            Edit Profile
                        </a>
                    @endcan
                </div>
            @endif
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="flex items-center gap-2">
                    <button id="sidebarToggle" type="button" class="admin-icon-button lg:hidden" aria-label="Toggle sidebar">
                        <span class="block h-0.5 w-5 bg-slate-700"></span>
                        <span class="mt-1 block h-0.5 w-5 bg-slate-700"></span>
                        <span class="mt-1 block h-0.5 w-5 bg-slate-700"></span>
                    </button>
                    <p class="text-sm font-medium text-slate-600">{{ $topbarLabel }}</p>
                </div>

                <div class="flex items-center gap-3">
                    @php($unreadNotifications = auth()->user()->unreadNotifications()->count())
                    <a href="{{ route('admin.notifications.index') }}" class="admin-icon-button relative"
                       aria-label="Notifications{{ $unreadNotifications ? ' ('.$unreadNotifications.' unread)' : '' }}" title="Notifications">
                        <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8" aria-hidden="true">
                            <path d="M6 9a6 6 0 1 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9Z" />
                            <path d="M10 20a2 2 0 0 0 4 0" />
                        </svg>
                        @if ($unreadNotifications > 0)
                            <span class="absolute -right-1.5 -top-1.5 min-w-[1.1rem] rounded-full bg-rose-600 px-1 text-center text-[10px] font-bold leading-[1.1rem] text-white">{{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}</span>
                        @endif
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="admin-icon-button">Logout</button>
                    </form>
                </div>
            </header>

            <main class="p-0">
                <div class="admin-canvas">
                    @if(session('bulk_result'))
                        @php($bulk = session('bulk_result'))
                        <div class="mb-6 rounded-xl border px-4 py-3 text-sm shadow-sm {{ empty($bulk['skipped']) ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : 'border-amber-200 bg-amber-50 text-amber-950' }}" role="status">
                            <p class="font-semibold">{{ $bulk['deleted'] }} {{ $bulk['label'] }} deleted{{ empty($bulk['skipped']) ? '.' : ', '.count($bulk['skipped']).' skipped.' }}</p>
                            @if(!empty($bulk['skipped']))
                                <ul class="mt-2 list-disc space-y-1 pl-5">
                                    @foreach($bulk['skipped'] as $skip)
                                        <li><span class="font-medium">{{ $skip['name'] }}</span>: {{ $skip['reason'] }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                    {{ $slot }}
                </div>
            </main>
        </div>
        </div>
    </div>
</body>
</html>
