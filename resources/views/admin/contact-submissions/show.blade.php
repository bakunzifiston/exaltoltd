<x-layouts.admin title="Contact Submission">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Contact Submission</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $contactSubmission->created_at->format('Y-m-d H:i') }}</p>
            </div>
            <a href="{{ route('admin.contact-submissions.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                Back
            </a>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Name</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $contactSubmission->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Email</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $contactSubmission->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Phone</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $contactSubmission->phone ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Subject</dt>
                    <dd class="mt-1 text-sm text-slate-900">{{ $contactSubmission->subject ?: '-' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Message</dt>
                    <dd class="mt-1 whitespace-pre-line text-sm text-slate-900">{{ $contactSubmission->message }}</dd>
                </div>
            </dl>
        </div>
    </section>
</x-layouts.admin>
