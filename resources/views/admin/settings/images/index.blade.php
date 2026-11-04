<x-layouts.admin title="Site Images">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Site Images</h2>
                <p class="mt-1 text-sm text-slate-500">Upload images for different sections of the storefront (logo, about, contact, hero, etc.).</p>
            </div>
            <a href="{{ route('admin.settings.images.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">
                Upload Image
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        @if($images->isEmpty())
            <div class="rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center shadow-sm">
                <p class="text-sm text-slate-500">No images uploaded yet.</p>
                <a href="{{ route('admin.settings.images.create') }}" class="mt-4 inline-block rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Upload first image</a>
            </div>
        @else
            @php $grouped = $images->groupBy('context'); @endphp
            @foreach($contexts as $ctx)
                @if($grouped->has($ctx))
                    <div class="space-y-3">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ ucfirst($ctx) }}</h3>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            @foreach($grouped[$ctx] as $image)
                                <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm {{ $image->is_active ? '' : 'opacity-60' }}">
                                    <div class="aspect-video bg-slate-100">
                                        <img src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->label }}" class="h-full w-full object-cover">
                                    </div>
                                    <div class="p-3 space-y-2">
                                        <p class="text-sm font-medium text-slate-800 truncate">{{ $image->label }}</p>
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-[10px] font-semibold uppercase {{ $image->is_active ? 'text-emerald-700' : 'text-slate-400' }}">
                                                {{ $image->is_active ? 'Active' : 'Hidden' }}
                                            </span>
                                            <div class="flex items-center gap-1.5">
                                                <form method="POST" action="{{ route('admin.settings.images.toggle', $image) }}">
                                                    @csrf
                                                    <button class="rounded border border-slate-200 px-2 py-0.5 text-[11px] font-medium text-slate-600 hover:bg-slate-50">
                                                        {{ $image->is_active ? 'Hide' : 'Show' }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.settings.images.destroy', $image) }}" onsubmit="return confirm('Delete this image?')">
                                                    @csrf @method('DELETE')
                                                    <button class="rounded border border-rose-200 px-2 py-0.5 text-[11px] font-medium text-rose-700 hover:bg-rose-50">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    </section>
</x-layouts.admin>
