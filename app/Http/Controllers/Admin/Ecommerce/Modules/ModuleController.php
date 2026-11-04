<?php

namespace App\Http\Controllers\Admin\Ecommerce\Modules;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function show(string $section, string $module): View
    {
        $map = $this->moduleMap();

        abort_unless(isset($map[$section][$module]), 404);

        $payload = $map[$section][$module];

        return view('admin.ecommerce.modules.show', [
            'section' => $section,
            'module' => $module,
            'title' => $payload['title'],
            'description' => $payload['description'],
            'next_steps' => $payload['next_steps'],
        ]);
    }

    /**
     * @return array<string, array<string, array{title: string, description: string, next_steps: array<int, string>}>>
     */
    private function moduleMap(): array
    {
        return [
            'catalog' => [
                'products' => [
                    'title' => 'Product Management',
                    'description' => 'Publish ERP products to storefront channels without duplicating core product records.',
                    'next_steps' => ['List synced products', 'Add publish/unpublish actions', 'Add channel-specific metadata'],
                ],
                'categories' => [
                    'title' => 'Product Categories',
                    'description' => 'Build category trees and map existing products into storefront navigation.',
                    'next_steps' => ['Define category hierarchy', 'Assign products to categories', 'Expose category visibility controls'],
                ],
                'variants' => [
                    'title' => 'Product Variants',
                    'description' => 'Model size/color/package options tied to the existing product and stock model.',
                    'next_steps' => ['Generate variant combinations', 'Map SKU codes', 'Bind variant stock availability'],
                ],
                'images' => [
                    'title' => 'Product Images',
                    'description' => 'Manage storefront media while keeping canonical ERP product ownership.',
                    'next_steps' => ['Upload image gallery', 'Set primary image', 'Optimize image ordering'],
                ],
            ],
            'customers' => [
                'profiles' => [
                    'title' => 'Customer Management',
                    'description' => 'Unify e-commerce customer profiles with existing ERP account entities.',
                    'next_steps' => ['Create customer index', 'Link billing/shipping addresses', 'Track account lifecycle'],
                ],
                'reviews' => [
                    'title' => 'Reviews & Ratings',
                    'description' => 'Capture and moderate customer product feedback for storefront trust.',
                    'next_steps' => ['Add moderation queue', 'Attach reviews to products', 'Support rating analytics'],
                ],
                'notifications' => [
                    'title' => 'Notifications',
                    'description' => 'Configure customer communication for order and service events.',
                    'next_steps' => ['Define event templates', 'Queue notifications', 'Track delivery status'],
                ],
            ],
            'sales' => [
                'carts' => [
                    'title' => 'Shopping Cart',
                    'description' => 'Persist customer cart state with accurate prices and stock checks.',
                    'next_steps' => ['Store guest/auth carts', 'Validate stock on cart update', 'Prepare checkout handoff'],
                ],
                'orders' => [
                    'title' => 'Orders Management',
                    'description' => 'Convert checkout payloads into ERP-safe orders and line items.',
                    'next_steps' => ['Create order index', 'Link orders to sales workflow', 'Add status transitions'],
                ],
                'statuses' => [
                    'title' => 'Order Status Tracking',
                    'description' => 'Track order progression across payment, picking, shipping, and completion.',
                    'next_steps' => ['Define status timeline', 'Log status changes', 'Expose customer-facing tracking'],
                ],
                'payments' => [
                    'title' => 'Payments',
                    'description' => 'Capture and reconcile transactions while preserving financial records.',
                    'next_steps' => ['Configure payment methods', 'Record transaction references', 'Integrate reconciliation hooks'],
                ],
                'discounts' => [
                    'title' => 'Discounts & Coupons',
                    'description' => 'Apply rule-based promotions at cart and order level.',
                    'next_steps' => ['Create coupon rules', 'Add validity windows', 'Enforce usage limits'],
                ],
                'returns' => [
                    'title' => 'Returns & Refunds',
                    'description' => 'Handle reverse logistics and financial reversals safely.',
                    'next_steps' => ['Open return requests', 'Approve/reject returns', 'Post refund outcomes'],
                ],
            ],
            'fulfillment' => [
                'inventory-sync' => [
                    'title' => 'Inventory Synchronization',
                    'description' => 'Continuously align storefront sellable quantity with ERP inventory availability.',
                    'next_steps' => ['Publish stock feed', 'Reserve quantity during checkout', 'Sync adjustments and returns'],
                ],
                'shipping' => [
                    'title' => 'Delivery / Shipping Management',
                    'description' => 'Manage dispatch and carrier logistics tied to order fulfillment.',
                    'next_steps' => ['Define delivery zones', 'Configure shipping rates', 'Track shipment lifecycle'],
                ],
            ],
            'analytics' => [
                'sales-reports' => [
                    'title' => 'Sales Reports',
                    'description' => 'Report e-commerce performance using existing ERP metrics and finance rules.',
                    'next_steps' => ['Build channel KPIs', 'Break down product conversion', 'Track return-adjusted revenue'],
                ],
            ],
        ];
    }
}
