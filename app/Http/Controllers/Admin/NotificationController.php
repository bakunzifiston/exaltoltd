<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** The in-app copy of every notification, listed behind the bell in the admin top bar. */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()
            ->paginate(TablePageSize::resolve($request, 20))
            ->withQueryString();

        return view('admin.notifications.index', compact('notifications'));
    }

    /** Mark one as read and go where it points. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        $url = (string) ($item->data['url'] ?? '');

        // Only follow links into this app (trailing slash so "app.example.evil" can't match).
        return Str::startsWith($url, rtrim(url('/'), '/').'/')
            ? redirect()->to($url)
            : redirect()->route('admin.notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
