<x-layouts.admin :title="$title">
    <section class="space-y-6">
        <header>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                E-Commerce / {{ str($section)->replace('-', ' ')->title() }}
            </p>
            <h2 class="text-2xl font-semibold text-slate-900">{{ $title }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $description }}</p>
        </header>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h3 class="text-sm font-semibold text-slate-900">Scaffold Status</h3>
            <p class="mt-2 text-sm text-slate-600">
                This module page is wired and ready for CRUD implementation with ERP service integration.
            </p>

            <ul class="mt-4 space-y-2 text-sm text-slate-700">
                @foreach ($next_steps as $step)
                    <li class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">{{ $step }}</li>
                @endforeach
            </ul>
        </div>
    </section>
</x-layouts.admin>
