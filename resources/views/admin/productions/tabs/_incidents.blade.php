<div class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="text-sm font-semibold text-slate-800">Record an incident</h3>
    <p class="text-sm text-slate-600">A customer complaint, a non-conformance found in production or storage, or a recall of this batch. Close it once the actions and the disposition of the stock are recorded.</p>
    <form method="POST" action="{{ route('admin.productions.incidents.store', $production) }}" class="grid gap-3 md:grid-cols-3">
        @csrf
        <label class="text-sm text-slate-600">Type<select name="type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach (\App\Models\TraceIncident::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="text-sm text-slate-600">Opened on<input type="date" name="opened_at" required max="{{ now()->toDateString() }}" value="{{ old('opened_at', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <label class="text-sm text-slate-600">Title<input name="title" required maxlength="150" value="{{ old('title') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        <label class="text-sm text-slate-600 md:col-span-3">What happened<textarea name="description" required rows="3" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('description') }}</textarea></label>
        <label class="text-sm text-slate-600 md:col-span-3">Actions taken so far <span class="text-xs text-slate-400">(optional)</span><textarea name="actions_taken" rows="2" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('actions_taken') }}</textarea></label>
        <div><button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Record incident</button></div>
    </form>
</div>

@forelse ($incidents as $incident)
    <div class="rounded-xl border {{ $incident->status === 'open' ? 'border-amber-200' : 'border-slate-200' }} bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $incident->typeLabel() }} · opened {{ $incident->opened_at->format('d M Y') }}@if ($incident->creator) by {{ $incident->creator->name }}@endif</p>
                <h4 class="mt-1 font-semibold text-slate-900">{{ $incident->title }}</h4>
            </div>
            <span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $incident->status === 'open' ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $incident->status === 'open' ? 'Open' : 'Closed '.$incident->closed_at?->format('d M Y') }}</span>
        </div>
        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $incident->description }}</p>
        @if ($incident->actions_taken)<p class="mt-2 text-sm text-slate-600"><span class="font-medium">Actions:</span> {{ $incident->actions_taken }}</p>@endif
        @if ($incident->disposition)<p class="mt-1 text-sm text-slate-600"><span class="font-medium">Disposition:</span> {{ $incident->disposition }}</p>@endif
        @if ($incident->status === 'open')
            <form method="POST" action="{{ route('admin.productions.incidents.close', [$production, $incident]) }}" class="mt-3 grid gap-3 md:grid-cols-3">
                @csrf @method('PATCH')
                <label class="text-sm text-slate-600 md:col-span-2">Actions taken<input name="actions_taken" required maxlength="5000" value="{{ $incident->actions_taken }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="text-sm text-slate-600">Disposition of the stock<input name="disposition" required maxlength="150" placeholder="e.g. destroyed, relabelled, released" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <div><button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Close incident</button></div>
            </form>
        @endif
    </div>
@empty
    <p class="rounded-xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500 shadow-sm">No incidents recorded for this batch.</p>
@endforelse
