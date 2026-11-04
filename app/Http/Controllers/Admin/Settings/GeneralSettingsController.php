<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Services\SiteSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeneralSettingsController extends Controller
{
    public function __construct(private readonly SiteSettingsService $settings) {}

    public function edit(): View
    {
        return view('admin.settings.general', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:120'],
            'company_tagline' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'sample_approval_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'maps_embed_url' => ['nullable', 'url', 'max:1000'],
            'maps_open_url' => ['nullable', 'url', 'max:1000'],
            'hours_weekday' => ['nullable', 'string', 'max:100'],
            'hours_saturday' => ['nullable', 'string', 'max:100'],
            'hours_sunday' => ['nullable', 'string', 'max:100'],
        ]);

        foreach ($data as $key => $value) {
            $group = str_starts_with($key, 'maps_') || str_starts_with($key, 'hours_') ? 'contact' : 'general';
            $this->settings->set($key, $value ?: null, $group);
        }

        return back()->with('status', 'General settings saved.');
    }
}
