<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StorefrontGalleryAlbum;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** The public pages search engines should index, rebuilt at most once an hour. */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('storefront_sitemap', 3600, function (): string {
            $urls = [];
            foreach (['storefront.home' => '1.0', 'storefront.shop' => '0.9', 'storefront.about' => '0.6', 'storefront.process' => '0.6', 'storefront.distribution' => '0.7', 'storefront.sustainability' => '0.5', 'storefront.gallery' => '0.5', 'storefront.contact' => '0.6'] as $route => $priority) {
                $urls[] = ['loc' => route($route), 'priority' => $priority, 'lastmod' => null];
            }
            foreach (Product::query()->orderBy('id')->get(['id', 'updated_at']) as $product) {
                $urls[] = ['loc' => route('storefront.product', $product), 'priority' => '0.8', 'lastmod' => $product->updated_at];
            }
            foreach (StorefrontGalleryAlbum::query()->where('is_active', true)->orderBy('id')->get(['id', 'updated_at']) as $album) {
                $urls[] = ['loc' => route('storefront.gallery.album', $album), 'priority' => '0.4', 'lastmod' => $album->updated_at];
            }

            $body = '';
            foreach ($urls as $url) {
                $body .= '<url><loc>'.e($url['loc']).'</loc>'
                    .($url['lastmod'] ? '<lastmod>'.$url['lastmod']->toDateString().'</lastmod>' : '')
                    .'<priority>'.$url['priority'].'</priority></url>';
            }

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
