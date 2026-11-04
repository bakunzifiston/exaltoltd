<x-layouts.admin title="Edit Email Routing Rule">
    <section class="max-w-lg space-y-6">
        <div>
            <a href="{{ route('admin.settings.email-routing.index') }}" class="text-sm text-teal-700 hover:underline">← Back to routing rules</a>
            <h2 class="mt-2 text-2xl font-semibold text-slate-900">Edit Routing Rule</h2>
        </div>

        <form method="POST" action="{{ route('admin.settings.email-routing.update', $rule) }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            @csrf
            @method('PUT')
            @include('admin.settings.email-routing._form', ['rule' => $rule])
            <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <a href="{{ route('admin.settings.email-routing.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-teal-700 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-800">Update Rule</button>
            </div>
        </form>
    </section>
</x-layouts.admin>
