<?php

namespace App\Providers;

use App\Models\ContactChannel;
use App\Models\InventoryRecord;
use App\Models\Production;
use App\Models\ProductionSample;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockReturn;
use App\Models\User;
use App\Services\InventoryValuationService;
use App\Services\ProductionCostingService;
use App\Services\SiteSettingsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Which roles may use each capability. Owner is granted everything via Gate::before.
     *
     * @var array<string, list<string>>
     */
    private const ABILITIES = [
        // Whole-business visibility: dashboard analytics, reports, team performance.
        'view-analytics' => [User::ROLE_MANAGER],
        // Master data & operations: products, inventory, suppliers, customers, expenses, e-commerce.
        'manage-catalog' => [User::ROLE_MANAGER],
        // Login accounts and roles.
        'manage-users' => [],
        // Record sales (sales role is additionally scoped to its own records in controllers).
        'record-sales' => [User::ROLE_MANAGER, User::ROLE_SALES],
        // See and edit every sale, not just your own.
        'manage-all-sales' => [User::ROLE_MANAGER],
        // Record production batches.
        'record-production' => [User::ROLE_MANAGER, User::ROLE_PRODUCTION],
        // See and edit every production batch.
        'manage-all-production' => [User::ROLE_MANAGER],
        // Delete financial/stock records (kept away from front-line staff).
        'delete-records' => [User::ROLE_MANAGER],
        // Inspect and approve stock returns: puts stock back and credits the customer.
        'approve-returns' => [User::ROLE_MANAGER],
        // Request production samples for customers, shops and events.
        'record-samples' => [User::ROLE_MANAGER, User::ROLE_PRODUCTION, User::ROLE_SALES],
        // Approve production samples (within the limit set in General Settings).
        'approve-samples' => [User::ROLE_MANAGER],
    ];

    /** Records whose changes invalidate the costing and stock valuation caches. */
    private const COSTING_SOURCES = [InventoryRecord::class, Production::class, Sale::class, SaleItem::class, StockReturn::class, ProductionSample::class];

    public function register(): void
    {
        // One costing model per request: statements ask the same questions for every month
        // end, and each of these loads lots, batches and sales once and answers from memory.
        $this->app->scoped(ProductionCostingService::class);
        $this->app->scoped(InventoryValuationService::class);
        $this->app->scoped(SiteSettingsService::class);
    }

    public function boot(): void
    {
        // Fix for older MySQL/MariaDB utf8mb4 index length limits.
        Schema::defaultStringLength(191);

        // Those caches must not outlive a change to what they were built from.
        foreach (self::COSTING_SOURCES as $model) {
            foreach (['saved', 'deleted'] as $event) {
                $model::$event(function (): void {
                    foreach ([ProductionCostingService::class, InventoryValuationService::class] as $service) {
                        if ($this->app->resolved($service)) {
                            $this->app->make($service)->flush();
                        }
                    }
                });
            }
        }

        // Owner can do everything; inactive accounts can do nothing.
        Gate::before(function (User $user, string $ability) {
            if (! $user->is_active) {
                return false;
            }

            return $user->isOwner() ? true : null;
        });

        foreach (self::ABILITIES as $ability => $roles) {
            Gate::define($ability, fn (User $user): bool => $user->hasRole(...$roles));
        }

        // Confirm reported payments. Granted per user by the owner, not by role.
        Gate::define('approve-payments', fn (User $user): bool => $user->canApprovePayments());

        // Share site settings and contact data with every storefront view and the layout
        // (the footer lives there). Built once per request by SiteSettingsService.
        // Wrapped in a try/catch so artisan commands during fresh installs don't crash.
        View::composer(['storefront.*', 'components.layouts.storefront', 'components.storefront.seo-head'], function (\Illuminate\View\View $view): void {
            try {
                $view->with(app(SiteSettingsService::class)->viewData());
            } catch (\Throwable) {
                $view->with([
                    'siteSettings' => [], 'sitePhones' => collect(), 'siteEmails' => collect(),
                    'siteWhatsapps' => collect(), 'siteAddresses' => collect(), 'siteSocials' => collect(),
                    'primaryPhone' => null, 'primaryEmail' => null, 'primaryWhatsapp' => null, 'primaryAddress' => null,
                ]);
            }
        });

        ContactChannel::saved(fn () => app(SiteSettingsService::class)->flush());
        ContactChannel::deleted(fn () => app(SiteSettingsService::class)->flush());
    }
}
