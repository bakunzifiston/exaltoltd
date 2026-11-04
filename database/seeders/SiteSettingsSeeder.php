<?php

namespace Database\Seeders;

use App\Models\ContactChannel;
use App\Models\EmailRoutingRule;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // ── General settings ──────────────────────────────────────────────────
        $settings = [
            ['key' => 'company_name',    'value' => 'Exalto Engineering and Supply Solutions Ltd',                    'group' => 'general', 'type' => 'text'],
            ['key' => 'company_tagline', 'value' => 'The Essence of Rwanda, Naturally Crafted.',        'group' => 'general', 'type' => 'text'],
            ['key' => 'meta_description', 'value' => 'Exalto transforms Rwanda\'s finest sugarcane and passion fruit into exceptional natural wines and juices. The Essence of Rwanda, Naturally Crafted.', 'group' => 'general', 'type' => 'text'],
            ['key' => 'hours_weekday',   'value' => 'Mon–Fri · 8:30–17:30',         'group' => 'contact', 'type' => 'text'],
            ['key' => 'hours_saturday',  'value' => 'Sat · 9:00–13:00 · CAT',       'group' => 'contact', 'type' => 'text'],
            ['key' => 'hours_sunday',    'value' => 'Sun · Closed',                  'group' => 'contact', 'type' => 'text'],
            [
                'key'   => 'maps_embed_url',
                'value' => 'https://maps.google.com/maps?q=Kamonyi%2C%20Southern%20Province%2C%20Rwanda&hl=en&z=11&output=embed',
                'group' => 'contact',
                'type'  => 'text',
            ],
            [
                'key'   => 'maps_open_url',
                'value' => 'https://www.google.com/maps/search/?api=1&query=Kamonyi%2C+Southern+Province%2C+Rwanda',
                'group' => 'contact',
                'type'  => 'text',
            ],
        ];

        foreach ($settings as $row) {
            SiteSetting::updateOrCreate(['key' => $row['key']], $row);
        }

        // ── Contact channels ──────────────────────────────────────────────────
        $channels = [
            ['type' => 'phone',    'label' => 'Main Line',         'value' => '+250788537463',        'is_primary' => true,  'is_active' => true, 'sort_order' => 1],
            ['type' => 'email',    'label' => 'General Inquiries', 'value' => 'exaltoltd@gmail.com',  'is_primary' => true,  'is_active' => true, 'sort_order' => 1],
            ['type' => 'whatsapp', 'label' => 'WhatsApp',          'value' => '250788537463',         'is_primary' => true,  'is_active' => true, 'sort_order' => 1],
            ['type' => 'address',  'label' => 'Main Office',       'value' => 'Kamonyi, Rwanda', 'is_primary' => true, 'is_active' => true, 'sort_order' => 1],
        ];

        foreach ($channels as $channel) {
            // Only seed if this type+label combination doesn't already exist
            ContactChannel::firstOrCreate(
                ['type' => $channel['type'], 'label' => $channel['label']],
                $channel
            );
        }

        // ── Email routing rules ───────────────────────────────────────────────
        $rules = [
            ['event' => 'contact_form', 'recipient_email' => 'info@shakyltd.rw',     'recipient_name' => 'Exalto Green Ltd Info',     'is_active' => true],
            ['event' => 'contact_form', 'recipient_email' => 'business@shakyltd.rw', 'recipient_name' => 'Exalto Green Ltd Business', 'is_active' => true],
            ['event' => 'order_placed', 'recipient_email' => 'info@shakyltd.rw',     'recipient_name' => 'Orders',             'is_active' => true],
        ];

        foreach ($rules as $rule) {
            EmailRoutingRule::firstOrCreate(
                ['event' => $rule['event'], 'recipient_email' => $rule['recipient_email']],
                $rule
            );
        }
    }
}
