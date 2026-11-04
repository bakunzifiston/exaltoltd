<?php

namespace App\Services;

use App\Models\ContactChannel;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SiteSettingsService
{
    private const TTL = 3600;
    private const CACHE_KEY = 'site_settings_all';
    private const CHANNELS_KEY = 'site_contact_channels';

    /** Active contact channels for this request, read once. */
    private ?Collection $activeChannels = null;

    /** What every storefront view receives, built once per request. */
    private ?array $viewData = null;

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL, function (): array {
            return SiteSetting::all()->pluck('value', 'key')->all();
        });
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, ?string $value, string $group = 'general', string $type = 'text'): void
    {
        SiteSetting::setValue($key, $value, $group, $type);
        $this->flush();
    }

    public function setMany(array $pairs, string $group = 'general'): void
    {
        foreach ($pairs as $key => $value) {
            SiteSetting::setValue($key, $value, $group);
        }
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CHANNELS_KEY);
        $this->activeChannels = $this->viewData = null;
    }

    /** Returns all active channels of a type, ordered by sort_order then id. */
    public function channels(string $type): Collection
    {
        return $this->activeChannels()->where('type', $type)->values();
    }

    /** Returns the primary channel of a type, or the first active one. */
    public function primaryChannel(string $type): ?ContactChannel
    {
        return $this->channels($type)
            ->sortBy(fn (ContactChannel $c) => [$c->is_primary ? 0 : 1, $c->sort_order, $c->id])
            ->first();
    }

    /**
     * Settings and contact details shared with every storefront view.
     *
     * Composers run for every view rendered, including each product card partial on the
     * shop page, so this is built once per request instead of nine queries per render.
     */
    public function viewData(): array
    {
        return $this->viewData ??= [
            'siteSettings' => $this->all(),
            'sitePhones' => $this->channels('phone'),
            'siteEmails' => $this->channels('email'),
            'siteWhatsapps' => $this->channels('whatsapp'),
            'siteAddresses' => $this->channels('address'),
            'siteSocials' => $this->channels('social'),
            'primaryPhone' => $this->primaryChannel('phone'),
            'primaryEmail' => $this->primaryChannel('email'),
            'primaryWhatsapp' => $this->primaryChannel('whatsapp'),
            'primaryAddress' => $this->primaryChannel('address'),
        ];
    }

    private function activeChannels(): Collection
    {
        return $this->activeChannels ??= Cache::remember(self::CHANNELS_KEY, self::TTL,
            fn () => ContactChannel::active()->orderBy('sort_order')->orderBy('id')->get());
    }
}
