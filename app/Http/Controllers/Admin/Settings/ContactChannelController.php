<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\ContactChannel;
use App\Services\SiteSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactChannelController extends Controller
{
    private const TYPES = ['phone', 'email', 'whatsapp', 'address', 'social'];

    public function __construct(private readonly SiteSettingsService $settings) {}

    public function index(): View
    {
        $channels = ContactChannel::orderBy('type')->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.settings.contacts.index', [
            'channels' => $channels,
            'types' => self::TYPES,
        ]);
    }

    public function create(): View
    {
        return view('admin.settings.contacts.create', ['types' => self::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->handlePrimary($data);
        ContactChannel::create($data);
        $this->settings->flush();

        return redirect()->route('admin.settings.contacts.index')->with('status', 'Contact channel added.');
    }

    public function edit(ContactChannel $channel): View
    {
        return view('admin.settings.contacts.edit', [
            'channel' => $channel,
            'types' => self::TYPES,
        ]);
    }

    public function update(Request $request, ContactChannel $channel): RedirectResponse
    {
        $data = $this->validated($request);
        $this->handlePrimary($data, $channel->id);
        $channel->update($data);
        $this->settings->flush();

        return redirect()->route('admin.settings.contacts.index')->with('status', 'Contact channel updated.');
    }

    public function destroy(ContactChannel $channel): RedirectResponse
    {
        $channel->delete();
        $this->settings->flush();

        return redirect()->route('admin.settings.contacts.index')->with('status', 'Contact channel deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'label' => ['required', 'string', 'max:80'],
            'value' => ['required', 'string', 'max:500'],
            'is_primary' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
        ]);
    }

    /** When setting as primary, demote other entries of the same type. */
    private function handlePrimary(array $data, ?int $excludeId = null): void
    {
        if (! empty($data['is_primary'])) {
            $query = ContactChannel::where('type', $data['type']);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            $query->update(['is_primary' => false]);
        }
    }
}
