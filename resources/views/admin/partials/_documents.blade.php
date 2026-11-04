{{-- Supporting documents. Expects $record, $routePrefix ('admin.stock-returns'), $param (route key) and $canRemove. --}}
<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="text-sm font-semibold text-slate-800">Supporting documents</h3>
    <ul class="mt-3 divide-y divide-slate-100 text-sm">
        @forelse ($record->documents as $document)
            <li class="flex items-center justify-between gap-3 py-2">
                <a href="{{ route($routePrefix.'.documents.show', [$param => $record, 'document' => $document]) }}" class="text-teal-700 hover:underline">{{ $document->original_name }}</a>
                <span class="text-xs text-slate-500">{{ number_format($document->size / 1024, 0) }} KB · {{ $document->uploader?->name ?? '—' }} · {{ $document->created_at->format('d M Y') }}</span>
                @if ($canRemove)
                    <form method="POST" action="{{ route($routePrefix.'.documents.destroy', [$param => $record, 'document' => $document]) }}" onsubmit="return confirm('Remove this document?')">@csrf @method('DELETE')<button type="submit" class="text-xs font-medium text-rose-700">Remove</button></form>
                @endif
            </li>
        @empty
            <li class="py-2 text-slate-500">No documents attached.</li>
        @endforelse
    </ul>
    <form method="POST" action="{{ route($routePrefix.'.documents.store', [$param => $record]) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-3">
        @csrf
        <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" required class="text-sm">
        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Attach</button>
        <span class="text-xs text-slate-500">PDF or image, up to 5 MB each.</span>
    </form>
</div>
