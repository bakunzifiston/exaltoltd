<?php

namespace Tests\Feature;

use App\Models\ContactChannel;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function catalogue(int $products = 12): void
    {
        for ($i = 1; $i <= $products; $i++) {
            Product::create(['name' => 'Neza Product '.$i, 'type' => 'Neza Product '.$i, 'barcode' => 'SF-'.$i, 'price' => 1000 + $i]);
        }
        foreach (['phone' => '+250788000000', 'email' => 'info@shakyltd.rw', 'whatsapp' => '+250788000000', 'social' => 'https://instagram.com/shakyltd'] as $type => $value) {
            ContactChannel::create(['type' => $type, 'label' => ucfirst($type), 'value' => $value, 'is_primary' => true, 'is_active' => true, 'sort_order' => 1]);
        }
    }

    /** Each product card used to re-run nine contact-channel queries through the view composer. */
    public function test_the_shop_page_query_count_does_not_grow_with_products(): void
    {
        $this->catalogue();

        DB::enableQueryLog();
        $this->get(route('storefront.shop'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(20, $count, 'Shop page ran '.$count.' queries.');
    }

    /** @return list<array<string, mixed>> Every JSON-LD block on a page, decoded. */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        return array_map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
    }

    public function test_every_page_tells_search_engines_the_logo_and_icon(): void
    {
        $this->catalogue(1);

        $html = $this->get(route('storefront.home'))->assertOk()->getContent();

        $organization = collect($this->jsonLd($html))->firstWhere('@type', 'Organization');
        $this->assertNotNull($organization, 'The Organization structured data is what puts the logo in search results.');
        $this->assertSame(asset('images/logo-square.png'), $organization['logo']);
        $this->assertStringStartsWith('http', $organization['logo']);
        $this->assertContains('https://instagram.com/shakyltd', $organization['sameAs']);
        $this->assertStringContainsString('<link rel="icon" href="'.asset('favicon.ico').'"', $html);
        $this->assertStringContainsString('<meta name="description"', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.asset('images/og-default.png').'"', $html);
        $this->assertFileExists(public_path('images/logo-square.png'));
        $this->assertSame([512, 512], array_slice(getimagesize(public_path('images/logo-square.png')), 0, 2));
    }

    public function test_product_pages_describe_the_product_without_inventing_ratings(): void
    {
        $this->catalogue(1);
        $product = Product::firstOrFail();

        $html = $this->get(route('storefront.product', $product))->assertOk()->getContent();
        $data = collect($this->jsonLd($html))->firstWhere('@type', 'Product');

        $this->assertSame('Neza Product 1', $data['name']);
        $this->assertSame('RWF', $data['offers']['priceCurrency']);
        $this->assertSame('1001.00', $data['offers']['price']);
        $this->assertArrayNotHasKey('aggregateRating', $data);
        $this->assertArrayNotHasKey('review', $data);
    }

    public function test_the_sitemap_lists_public_pages_and_products(): void
    {
        $this->catalogue(2);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());

        $locations = [];
        foreach ($xml->url as $url) {
            $locations[] = (string) $url->loc;
        }
        $this->assertContains(route('storefront.home'), $locations);
        $this->assertContains(route('storefront.product', Product::first()), $locations);
        $this->assertStringContainsString('Sitemap: https://exalto.rw/sitemap.xml', file_get_contents(public_path('robots.txt')));
    }
}
