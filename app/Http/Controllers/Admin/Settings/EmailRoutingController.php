<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmailRoutingRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailRoutingController extends Controller
{
    public const EVENTS = [
        'contact_form' => 'Contact Form Submission',
        'order_placed' => 'New Order Placed',
        'order_status_update' => 'Order Status Update',
    ];

    public function index(): View
    {
        $rules = EmailRoutingRule::orderBy('event')->orderBy('id')->get();

        return view('admin.settings.email-routing.index', [
            'rules' => $rules,
            'events' => self::EVENTS,
        ]);
    }

    public function create(): View
    {
        return view('admin.settings.email-routing.create', ['events' => self::EVENTS]);
    }

    public function store(Request $request): RedirectResponse
    {
        EmailRoutingRule::create($this->validated($request));

        return redirect()->route('admin.settings.email-routing.index')->with('status', 'Routing rule added.');
    }

    public function edit(EmailRoutingRule $rule): View
    {
        return view('admin.settings.email-routing.edit', [
            'rule' => $rule,
            'events' => self::EVENTS,
        ]);
    }

    public function update(Request $request, EmailRoutingRule $rule): RedirectResponse
    {
        $rule->update($this->validated($request));

        return redirect()->route('admin.settings.email-routing.index')->with('status', 'Routing rule updated.');
    }

    public function destroy(EmailRoutingRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('admin.settings.email-routing.index')->with('status', 'Routing rule deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'event' => ['required', 'in:'.implode(',', array_keys(self::EVENTS))],
            'recipient_email' => ['required', 'email', 'max:200'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
        ]);
    }
}
