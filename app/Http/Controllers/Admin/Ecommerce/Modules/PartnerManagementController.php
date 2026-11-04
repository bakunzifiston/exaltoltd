<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PartnerManagementController extends Controller
{
    public function index(Request $request): View
    {
        $partners = Partner::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.ecommerce.modules.partners', [
            'partners' => $partners,
            'maxLogoMb' => max((int) config('storefront.partner_logo_max_mb', 2), 1),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $maxLogoMb = max((int) config('storefront.partner_logo_max_mb', 2), 1);
        $maxLogoKb = $maxLogoMb * 1024;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'logo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', "max:{$maxLogoKb}"],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'logo_file.max' => "Logo must be {$maxLogoMb}MB or smaller.",
            'logo_file.image' => 'Only JPG, JPEG, PNG, WEBP, and SVG images are allowed.',
        ]);

        $data = [
            'name' => trim((string) $validated['name']),
            'website_url' => $validated['website_url'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];

        if ($request->hasFile('logo_file')) {
            $data['logo_path'] = $request->file('logo_file')->store('storefront/partners', 'public');
        }

        Partner::create($data);

        return back()->with('status', 'Partner added successfully.');
    }

    public function update(Request $request, Partner $partner): RedirectResponse
    {
        $maxLogoMb = max((int) config('storefront.partner_logo_max_mb', 2), 1);
        $maxLogoKb = $maxLogoMb * 1024;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'logo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', "max:{$maxLogoKb}"],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'logo_file.max' => "Logo must be {$maxLogoMb}MB or smaller.",
            'logo_file.image' => 'Only JPG, JPEG, PNG, WEBP, and SVG images are allowed.',
        ]);

        $data = [
            'name' => trim((string) $validated['name']),
            'website_url' => $validated['website_url'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ];

        if ($request->hasFile('logo_file')) {
            if ($partner->logo_path) {
                Storage::disk('public')->delete($partner->logo_path);
            }

            $data['logo_path'] = $request->file('logo_file')->store('storefront/partners', 'public');
        }

        $partner->update($data);

        return back()->with('status', 'Partner updated successfully.');
    }

    public function destroy(Partner $partner): RedirectResponse
    {
        if ($partner->logo_path) {
            Storage::disk('public')->delete($partner->logo_path);
        }

        $partner->delete();

        return back()->with('status', 'Partner deleted successfully.');
    }
}
