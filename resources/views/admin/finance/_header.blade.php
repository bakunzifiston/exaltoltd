{{-- Page heading, tab nav and flash messages shared by the finance screens. Expects $active, $heading, $intro. --}}
<div>
    <h2 class="text-2xl font-semibold text-slate-900">{{ $heading }}</h2>
    <p class="mt-1 text-sm text-slate-500">{{ $intro }}</p>
</div>
@include('admin.finance._nav', ['active' => $active])
@if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
@if ($errors->any())
    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
        <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
