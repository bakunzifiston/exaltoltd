<x-layouts.admin title="Notifications">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Notifications</h2>
                <p class="mt-1 text-sm text-slate-500">Payment approvals and other updates. Each one was also sent to {{ auth()->user()->email }}.</p>
            </div>
            @if (auth()->user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                    @csrf
                    <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Mark all as read</button>
                </form>
            @endif
        </div>

        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <ul class="divide-y divide-slate-100">
                @forelse ($notifications as $notification)
                    <li>
                        <a href="{{ route('admin.notifications.open', $notification->id) }}" class="flex items-start gap-3 px-5 py-4 hover:bg-slate-50 {{ $notification->read_at ? '' : 'bg-teal-50/40' }}">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-transparent' : 'bg-teal-600' }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm {{ $notification->read_at ? 'text-slate-700' : 'font-semibold text-slate-900' }}">{{ $notification->data['title'] ?? 'Notification' }}</span>
                                <span class="block text-sm text-slate-600">{{ $notification->data['message'] ?? '' }}</span>
                                <span class="mt-0.5 block text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-sm text-slate-500">No notifications yet.</li>
                @endforelse
            </ul>
        </div>
        <x-admin.table-pagination :paginator="$notifications" label="notifications" />
    </section>
</x-layouts.admin>
