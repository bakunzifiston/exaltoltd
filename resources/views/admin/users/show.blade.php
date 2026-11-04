<x-layouts.admin title="View User">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">User Details</h2>
                <p class="mt-1 text-sm text-slate-500">View and manage this user record.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.users.edit', $user) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Edit
                </a>
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                        Delete
                    </button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <dt class="text-sm text-slate-500">Name</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Email</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Role</dt>
                    <dd class="mt-1"><x-admin.role-badge :role="$user->role" :active="$user->is_active" /></dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Status</dt>
                    <dd class="mt-1 text-sm font-medium {{ $user->is_active ? 'text-emerald-700' : 'text-rose-700' }}">{{ $user->is_active ? 'Active' : 'Deactivated' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Can approve payments</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $user->isOwner() ? 'Yes (owner)' : ($user->can_approve_payments ? 'Yes' : 'No') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Phone</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $user->phone ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Linked employee</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $user->employee?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Monthly sales target</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $user->sales_target ? number_format($user->sales_target, 0) . ' RWF' : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Commission rate</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $user->commission_rate ? rtrim(rtrim(number_format($user->commission_rate, 2), '0'), '.') . '%' : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Last login</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $user->last_login_at?->format('Y-m-d H:i') ?? 'Never' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Created At</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900">{{ $user->created_at?->format('Y-m-d H:i') }}</dd>
                </div>
            </dl>
        </div>
    </section>
</x-layouts.admin>
