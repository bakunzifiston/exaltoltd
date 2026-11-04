<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log In - {{ config('app.name', 'Exalto Green Ltd') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Source+Sans+3:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .login-split-image {
            background-color: #012b51;
            border-right: 6px solid #0d6a3a;
        }
        .input-success { border-color: #16a34a !important; box-shadow: 0 0 0 1px #16a34a; }
        .input-error   { border-color: #dc2626 !important; box-shadow: 0 0 0 1px #dc2626; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { animation: spin 0.7s linear infinite; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: fadeInUp 0.4s ease-out both; }
    </style>
</head>

<body class="h-full bg-off-white antialiased">
    {{-- Skip link for keyboard users --}}
    <a href="#login-form" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-[#012b51] focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#012b51]">
        Skip to login form
    </a>

    <div class="flex min-h-full">
        {{-- Left: Brand imagery (hidden on mobile, shown on lg+) --}}
        <div class="login-split-image relative hidden w-1/2 lg:block" aria-hidden="true">
            <div class="absolute inset-0 bg-gradient-to-br from-[#011f3a] via-[#012b51] to-[#0a3865]"></div>
            <div class="relative flex h-full flex-col justify-between p-10">
                <a href="{{ route('storefront.home') }}" class="inline-block self-start rounded-2xl bg-white/95 px-5 py-4 shadow-lg">
                    <img src="{{ asset('images/brand/exalto-green-logo.svg') }}" alt="" width="220" height="71" class="h-14 w-auto">
                </a>

                <div class="max-w-md">
                    <blockquote class="text-lg font-medium leading-relaxed text-white/90">
                        "The Essence of Rwanda, Naturally Crafted."
                    </blockquote>
                    <p class="mt-3 text-sm text-[#cb8d20]/90">Natural wines and juices &middot; Kamonyi, Rwanda</p>
                </div>

                <p class="text-xs text-white/40">&copy; {{ date('Y') }} Exalto Green Ltd. All rights reserved.</p>
            </div>
        </div>

        {{-- Right: Login form --}}
        <div class="flex w-full flex-col items-center justify-center px-4 py-10 sm:px-8 lg:w-1/2">
            {{-- Mobile logo --}}
            <a href="{{ route('storefront.home') }}" class="mb-8 flex items-center gap-3 lg:hidden">
                <img src="{{ asset('images/brand/exalto-green-logo.svg') }}" alt="Exalto Green Ltd" width="220" height="71" class="h-14 w-auto">
            </a>

            <div class="w-full max-w-sm">
                <div class="fade-in-up">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Welcome back</h1>
                    <p class="mt-1.5 text-sm text-slate-500">Sign in to access your dashboard.</p>
                </div>

                {{-- Global error banner --}}
                @if ($errors->any())
                    <div
                        class="fade-in-up mt-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3"
                        role="alert"
                        aria-live="assertive"
                    >
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd" />
                        </svg>
                        <div>
                            @foreach ($errors->all() as $error)
                                <p class="text-sm font-medium text-red-800">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form
                    id="login-form"
                    method="POST"
                    action="{{ route('admin.login.store') }}"
                    class="mt-8 space-y-5"
                    novalidate
                >
                    @csrf

                    {{-- Email / Username field --}}
                    <div>
                        <label for="login" class="mb-1.5 block text-sm font-medium text-slate-700">
                            Email or username
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400" aria-hidden="true">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.465 14.493a1.23 1.23 0 0 0 .41 1.412A9.957 9.957 0 0 0 10 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 0 0-13.074.003Z" />
                                </svg>
                            </span>
                            <input
                                id="login"
                                name="login"
                                type="text"
                                value="{{ old('login') }}"
                                required
                                autocomplete="username"
                                autofocus
                                placeholder="you@example.com"
                                aria-describedby="login-hint"
                                class="block w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-10 pr-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-[#012b51] focus:outline-none focus:ring-2 focus:ring-[#012b51]/20"
                            >
                        </div>
                        <p id="login-hint" class="sr-only">Enter your registered email address or username.</p>
                    </div>

                    {{-- Password field --}}
                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="password" class="block text-sm font-medium text-slate-700">
                                Password
                            </label>
                            <a
                                href="{{ route('storefront.contact') }}"
                                class="text-xs font-medium text-[#012b51] hover:text-[#011f3a] focus:outline-none focus:underline"
                            >
                                Forgot password?
                            </a>
                        </div>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400" aria-hidden="true">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                aria-describedby="password-caps-warning"
                                class="block w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-10 pr-11 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-[#012b51] focus:outline-none focus:ring-2 focus:ring-[#012b51]/20"
                            >
                            {{-- Show/hide toggle --}}
                            <button
                                type="button"
                                id="toggle-password"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 transition hover:text-slate-600 focus:outline-none focus:text-[#012b51]"
                                aria-label="Show password"
                            >
                                {{-- Eye open (visible when password is hidden) --}}
                                <svg id="icon-eye-open" class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" />
                                    <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 0 1 0-1.186A10.004 10.004 0 0 1 10 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0 1 10 17c-4.257 0-7.893-2.66-9.336-6.41ZM14 10a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" clip-rule="evenodd" />
                                </svg>
                                {{-- Eye closed (visible when password is shown) --}}
                                <svg id="icon-eye-closed" class="hidden h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.745-1.745a10.029 10.029 0 0 0 3.3-4.38 1.651 1.651 0 0 0 0-1.185A10.004 10.004 0 0 0 9.999 3a9.956 9.956 0 0 0-4.744 1.194L3.28 2.22ZM7.752 6.69l1.092 1.092a2.5 2.5 0 0 1 3.374 3.373l1.092 1.092a4 4 0 0 0-5.558-5.558Z" clip-rule="evenodd" />
                                    <path d="M10.748 13.93l2.523 2.523A9.987 9.987 0 0 1 10 17a10.003 10.003 0 0 1-9.336-6.41 1.651 1.651 0 0 1 0-1.186 10.05 10.05 0 0 1 2.839-4.084l2.09 2.09a4 4 0 0 0 5.155 5.52Z" />
                                </svg>
                            </button>
                        </div>
                        {{-- Caps Lock warning --}}
                        <p
                            id="password-caps-warning"
                            class="mt-1.5 hidden items-center gap-1.5 text-xs font-medium text-amber-600"
                            role="status"
                            aria-live="polite"
                        >
                            <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                            </svg>
                            Caps Lock is on
                        </p>
                    </div>

                    {{-- Remember me --}}
                    <div class="flex items-center gap-2.5">
                        <input
                            id="remember"
                            name="remember"
                            type="checkbox"
                            value="1"
                            class="h-4 w-4 rounded border-slate-300 text-[#012b51] focus:ring-[#012b51]/30"
                        >
                        <label for="remember" class="text-sm text-slate-600">Remember me</label>
                    </div>

                    {{-- Submit --}}
                    <button
                        id="login-submit"
                        type="submit"
                        class="group relative flex w-full items-center justify-center gap-2 rounded-lg bg-[#012b51] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition duration-200 hover:bg-[#011f3a] focus:outline-none focus:ring-2 focus:ring-[#012b51] focus:ring-offset-2 active:scale-[0.98] disabled:pointer-events-none disabled:opacity-60"
                    >
                        {{-- Spinner (hidden by default) --}}
                        <svg id="login-spinner" class="spinner hidden h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span id="login-text">Log In</span>
                    </button>
                </form>

                <p class="mt-8 text-center text-xs text-slate-400">
                    <a href="{{ route('storefront.home') }}" class="font-medium text-[#012b51] hover:text-[#011f3a] focus:outline-none focus:underline">&larr; Back to website</a>
                </p>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form        = document.getElementById('login-form');
        const loginInput  = document.getElementById('login');
        const passInput   = document.getElementById('password');
        const toggleBtn   = document.getElementById('toggle-password');
        const eyeOpen     = document.getElementById('icon-eye-open');
        const eyeClosed   = document.getElementById('icon-eye-closed');
        const capsWarning = document.getElementById('password-caps-warning');
        const submitBtn   = document.getElementById('login-submit');
        const spinnerEl   = document.getElementById('login-spinner');
        const textEl      = document.getElementById('login-text');

        // --- Show / Hide password ---
        toggleBtn.addEventListener('click', function () {
            const isHidden = passInput.type === 'password';
            passInput.type = isHidden ? 'text' : 'password';
            eyeOpen.classList.toggle('hidden', isHidden);
            eyeClosed.classList.toggle('hidden', !isHidden);
            this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            passInput.focus();
        });

        // --- Caps Lock detection ---
        function checkCaps(e) {
            if (typeof e.getModifierState === 'function') {
                const on = e.getModifierState('CapsLock');
                capsWarning.classList.toggle('hidden', !on);
                capsWarning.classList.toggle('flex', on);
            }
        }
        passInput.addEventListener('keyup', checkCaps);
        passInput.addEventListener('keydown', checkCaps);

        // --- Real-time inline validation ---
        function setValid(el) {
            el.classList.remove('input-error');
            el.classList.add('input-success');
        }
        function setInvalid(el) {
            el.classList.remove('input-success');
            el.classList.add('input-error');
        }
        function clearState(el) {
            el.classList.remove('input-success', 'input-error');
        }

        loginInput.addEventListener('input', function () {
            const v = this.value.trim();
            if (v.length === 0) { clearState(this); return; }
            if (v.includes('@')) {
                /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? setValid(this) : setInvalid(this);
            } else {
                v.length >= 2 ? setValid(this) : setInvalid(this);
            }
        });

        passInput.addEventListener('input', function () {
            const v = this.value;
            if (v.length === 0) { clearState(this); return; }
            v.length >= 1 ? setValid(this) : setInvalid(this);
        });

        // --- Loading state on submit ---
        form.addEventListener('submit', function () {
            submitBtn.disabled = true;
            spinnerEl.classList.remove('hidden');
            textEl.textContent = 'Signing in…';
        });
    });
    </script>
</body>
</html>
