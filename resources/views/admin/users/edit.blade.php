<x-layouts.admin title="Edit User">
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Edit User</h2>
            <p class="mt-1 text-sm text-slate-500">Update user profile data.</p>
        </div>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @method('PUT')
            @include('admin.users._form', ['submitLabel' => 'Save Changes'])
        </form>
    </section>
</x-layouts.admin>
