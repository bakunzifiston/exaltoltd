<?php

namespace App\Support\Admin;

use App\Http\Controllers\Admin\Ecommerce\Modules\CartManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\CategoryManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\CustomerManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\DiscountManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\EcommerceSalesReportController;
use App\Http\Controllers\Admin\Ecommerce\Modules\InventorySyncController;
use App\Http\Controllers\Admin\Ecommerce\Modules\NotificationManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\OrderManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\OrderStatusTrackingController;
use App\Http\Controllers\Admin\Ecommerce\Modules\PaymentManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\ProductImageManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\ProductManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\ReturnManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\ReviewManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\ShippingManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\VariantManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\GalleryManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\PartnerManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\VideoManagementController;

class EcommerceHub
{
    /**
     * @return array<string, array{title: string, description: string, route: string, default: string, modules: array<string, array{label: string, controller: class-string, action: string}>}>
     */
    public static function sections(): array
    {
        return [
            'catalog' => [
                'title' => 'E-Commerce / Catalog',
                'description' => 'Manage and publish sellable product data from ERP master records.',
                'route' => 'admin.ecommerce.catalog',
                'default' => 'products',
                'modules' => [
                    'products' => [
                        'label' => 'Products',
                        'controller' => ProductManagementController::class,
                        'action' => '__invoke',
                    ],
                    'categories' => [
                        'label' => 'Categories',
                        'controller' => CategoryManagementController::class,
                        'action' => '__invoke',
                    ],
                    'variants' => [
                        'label' => 'Variants',
                        'controller' => VariantManagementController::class,
                        'action' => '__invoke',
                    ],
                    'images' => [
                        'label' => 'Images',
                        'controller' => ProductImageManagementController::class,
                        'action' => '__invoke',
                    ],
                    'videos' => [
                        'label' => 'Videos',
                        'controller' => VideoManagementController::class,
                        'action' => 'index',
                    ],
                    'gallery' => [
                        'label' => 'Gallery',
                        'controller' => GalleryManagementController::class,
                        'action' => 'index',
                    ],
                    'partners' => [
                        'label' => 'Partners',
                        'controller' => PartnerManagementController::class,
                        'action' => 'index',
                    ],
                ],
            ],
            'customers' => [
                'title' => 'E-Commerce / Customers',
                'description' => 'Unify customer lifecycle data between storefront activity and ERP records.',
                'route' => 'admin.ecommerce.customers',
                'default' => 'profiles',
                'modules' => [
                    'profiles' => [
                        'label' => 'Profiles',
                        'controller' => CustomerManagementController::class,
                        'action' => '__invoke',
                    ],
                    'reviews' => [
                        'label' => 'Reviews',
                        'controller' => ReviewManagementController::class,
                        'action' => '__invoke',
                    ],
                    'notifications' => [
                        'label' => 'Notifications',
                        'controller' => NotificationManagementController::class,
                        'action' => '__invoke',
                    ],
                ],
            ],
            'sales' => [
                'title' => 'E-Commerce / Sales',
                'description' => 'Manage order-to-cash flows while reusing ERP sales and financial workflows.',
                'route' => 'admin.ecommerce.sales',
                'default' => 'orders',
                'modules' => [
                    'carts' => [
                        'label' => 'Carts',
                        'controller' => CartManagementController::class,
                        'action' => '__invoke',
                    ],
                    'orders' => [
                        'label' => 'Orders',
                        'controller' => OrderManagementController::class,
                        'action' => '__invoke',
                    ],
                    'statuses' => [
                        'label' => 'Statuses',
                        'controller' => OrderStatusTrackingController::class,
                        'action' => '__invoke',
                    ],
                    'payments' => [
                        'label' => 'Payments',
                        'controller' => PaymentManagementController::class,
                        'action' => '__invoke',
                    ],
                    'discounts' => [
                        'label' => 'Discounts',
                        'controller' => DiscountManagementController::class,
                        'action' => '__invoke',
                    ],
                    'returns' => [
                        'label' => 'Returns',
                        'controller' => ReturnManagementController::class,
                        'action' => '__invoke',
                    ],
                ],
            ],
            'fulfillment' => [
                'title' => 'E-Commerce / Fulfillment',
                'description' => 'Coordinate stock and logistics execution between online demand and operations.',
                'route' => 'admin.ecommerce.fulfillment',
                'default' => 'inventory-sync',
                'modules' => [
                    'inventory-sync' => [
                        'label' => 'Inventory Sync',
                        'controller' => InventorySyncController::class,
                        'action' => '__invoke',
                    ],
                    'shipping' => [
                        'label' => 'Shipping',
                        'controller' => ShippingManagementController::class,
                        'action' => '__invoke',
                    ],
                ],
            ],
            'analytics' => [
                'title' => 'E-Commerce / Analytics',
                'description' => 'Measure storefront performance using integrated ERP sales and inventory data.',
                'route' => 'admin.ecommerce.analytics',
                'default' => 'sales-reports',
                'modules' => [
                    'sales-reports' => [
                        'label' => 'Sales Reports',
                        'controller' => EcommerceSalesReportController::class,
                        'action' => '__invoke',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, controller: class-string, action: string}>
     */
    public static function modules(string $section): array
    {
        return self::sections()[$section]['modules'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function route(?string $hubRouteName, ?string $hubModule, string $fallbackRoute, array $query = []): string
    {
        $query = array_merge(request()->query(), $query);

        if ($hubRouteName && $hubModule) {
            $query['module'] = $hubModule;

            return route($hubRouteName, $query);
        }

        return route($fallbackRoute, $query);
    }
}
