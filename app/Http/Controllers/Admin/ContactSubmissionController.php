<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $search = (string) $request->string('search', '');

        $submissions = ContactSubmission::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        return view('admin/contact-submissions/index', compact('submissions', 'search'));
    }

    public function show(ContactSubmission $contactSubmission): View
    {
        return view('admin/contact-submissions/show', compact('contactSubmission'));
    }

    public function destroy(ContactSubmission $contactSubmission): RedirectResponse
    {
        $contactSubmission->delete();

        return redirect()
            ->route('admin.contact-submissions.index')
            ->with('status', 'Submission deleted.');
    }
}
