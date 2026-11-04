<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CashAccountController;
use App\Http\Controllers\Admin\ContactSubmissionController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DataHealthController;
use App\Http\Controllers\Admin\Ecommerce\AnalyticsController as EcommerceAnalyticsController;
use App\Http\Controllers\Admin\Ecommerce\CatalogController as EcommerceCatalogController;
use App\Http\Controllers\Admin\Ecommerce\CustomersController as EcommerceCustomersController;
use App\Http\Controllers\Admin\Ecommerce\FulfillmentController as EcommerceFulfillmentController;
use App\Http\Controllers\Admin\Ecommerce\Modules\GalleryManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\PartnerManagementController;
use App\Http\Controllers\Admin\Ecommerce\Modules\VideoManagementController;
use App\Http\Controllers\Admin\Ecommerce\SalesController as EcommerceSalesController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EquityMovementController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\FinancialReportController;
use App\Http\Controllers\Admin\FixedAssetController;
use App\Http\Controllers\Admin\InventoryRecordController;
use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OpeningBalanceController;
use App\Http\Controllers\Admin\OperationalReportController;
use App\Http\Controllers\Admin\PaymentApprovalController;
use App\Http\Controllers\Admin\PaymentProofController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductionSampleController;
use App\Http\Controllers\Admin\ProductionController;
use App\Http\Controllers\Admin\ProductionTraceabilityController;
use App\Http\Controllers\Admin\ProfitabilityController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SalePaymentController;
use App\Http\Controllers\Admin\StockReturnController;
use App\Http\Controllers\Admin\Settings\ContactChannelController;
use App\Http\Controllers\Admin\Settings\EmailRoutingController;
use App\Http\Controllers\Admin\Settings\GeneralSettingsController;
use App\Http\Controllers\Admin\Settings\SiteImageController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\SupplierPaymentController;
use App\Http\Controllers\Admin\TeamPerformanceController;
use App\Http\Controllers\Admin\UserController;
use App\Services\OperationalReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('admin-app')
    ->name('admin.')
    ->middleware(['web', 'guest'])
    ->group(function (): void {
        Route::get('/login', [LoginController::class, 'create'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    });

Route::prefix('admin-app')
    ->name('admin.')
    ->middleware(['web', 'admin.session'])
    ->group(function (): void {
        // Available to every authenticated, active user. The controller routes each
        // role to the screen it actually works from.
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        // In-app notifications (the bell), for everyone.
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');

        // Payment proof files — the controller decides who may see each one.
        Route::get('/payment-proofs/{proof}', [PaymentProofController::class, 'show'])->name('payment-proofs.show');

        // Owner and users granted "Can approve payments" — confirm reported payments.
        Route::middleware('can:approve-payments')->group(function (): void {
            Route::get('/payment-approvals', [PaymentApprovalController::class, 'index'])->name('payment-approvals.index');
            Route::get('/payment-approvals/{submission}', [PaymentApprovalController::class, 'show'])->name('payment-approvals.show');
            Route::post('/payment-approvals/{submission}/approve', [PaymentApprovalController::class, 'approve'])->name('payment-approvals.approve');
            Route::post('/payment-approvals/{submission}/reject', [PaymentApprovalController::class, 'reject'])->name('payment-approvals.reject');
        });

        // Owner only — login accounts and roles.
        Route::middleware('can:manage-users')->group(function (): void {
            Route::resource('users', UserController::class);
        });

        // Owner / Manager — whole-business visibility.
        Route::middleware('can:view-analytics')->group(function (): void {
            Route::get('/reports', ReportController::class)->name('reports.index');
            Route::get('/reports/profitability', [ProfitabilityController::class, 'index'])->name('reports.profitability');
            Route::post('/reports/profitability/recost', [ProfitabilityController::class, 'recost'])->name('reports.profitability.recost');
            Route::get('/reports/financial', [FinancialReportController::class, 'index'])->name('reports.financial.index');
            Route::post('/reports/financial', [FinancialReportController::class, 'store'])->name('reports.financial.store');
            Route::get('/reports/financial/{financialReport}', [FinancialReportController::class, 'show'])->name('reports.financial.show');
            Route::get('/reports/financial/{financialReport}/edit', [FinancialReportController::class, 'edit'])->name('reports.financial.edit');
            Route::put('/reports/financial/{financialReport}', [FinancialReportController::class, 'update'])->name('reports.financial.update');
            Route::delete('/reports/financial/{financialReport}', [FinancialReportController::class, 'destroy'])->name('reports.financial.destroy');
            Route::get('/reports/financial/{financialReport}/export/{format}', [FinancialReportController::class, 'export'])->name('reports.financial.export');

            // Raw material, sales and production reports, previewed on screen and downloaded as Excel.
            Route::get('/reports/operational/{type}', [OperationalReportController::class, 'show'])
                ->whereIn('type', array_keys(OperationalReportService::TYPES))->name('reports.operational.show');
            Route::get('/reports/operational/{type}/export', [OperationalReportController::class, 'export'])
                ->whereIn('type', array_keys(OperationalReportService::TYPES))->name('reports.operational.export');

            // Finance subledgers. Without these the balance sheet and cash flow statement
            // have nothing to read: cash, payments out, assets, borrowings and capital.
            Route::prefix('finance')->name('finance.')->group(function (): void {
                Route::get('/opening-balances', [OpeningBalanceController::class, 'index'])->name('opening-balances.index');
                Route::post('/opening-balances', [OpeningBalanceController::class, 'store'])->name('opening-balances.store');
                Route::delete('/opening-balances', [OpeningBalanceController::class, 'destroy'])->name('opening-balances.destroy');

                Route::get('/cash-accounts', [CashAccountController::class, 'index'])->name('cash-accounts.index');
                Route::post('/cash-accounts', [CashAccountController::class, 'store'])->name('cash-accounts.store');
                Route::put('/cash-accounts/{cashAccount}', [CashAccountController::class, 'update'])->name('cash-accounts.update');
                Route::delete('/cash-accounts/{cashAccount}', [CashAccountController::class, 'destroy'])->name('cash-accounts.destroy');

                Route::get('/supplier-payments', [SupplierPaymentController::class, 'index'])->name('supplier-payments.index');
                Route::post('/supplier-payments', [SupplierPaymentController::class, 'store'])->name('supplier-payments.store');
                Route::delete('/supplier-payments/{supplierPayment}', [SupplierPaymentController::class, 'destroy'])->name('supplier-payments.destroy');

                Route::get('/fixed-assets', [FixedAssetController::class, 'index'])->name('fixed-assets.index');
                Route::post('/fixed-assets', [FixedAssetController::class, 'store'])->name('fixed-assets.store');
                Route::put('/fixed-assets/{fixedAsset}', [FixedAssetController::class, 'update'])->name('fixed-assets.update');
                Route::delete('/fixed-assets/{fixedAsset}', [FixedAssetController::class, 'destroy'])->name('fixed-assets.destroy');

                Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
                Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
                Route::delete('/loans/{loan}', [LoanController::class, 'destroy'])->name('loans.destroy');
                Route::post('/loans/{loan}/repayments', [LoanController::class, 'storeRepayment'])->name('loans.repayments.store');
                Route::delete('/loan-repayments/{loanRepayment}', [LoanController::class, 'destroyRepayment'])->name('loans.repayments.destroy');

                Route::get('/equity', [EquityMovementController::class, 'index'])->name('equity.index');
                Route::post('/equity', [EquityMovementController::class, 'store'])->name('equity.store');
                Route::delete('/equity/{equityMovement}', [EquityMovementController::class, 'destroy'])->name('equity.destroy');
            });

            Route::get('/team-performance', TeamPerformanceController::class)->name('team-performance');
            Route::get('/data-health', DataHealthController::class)->name('data-health');
        });

        // Owner / Manager — master data and operations.
        Route::middleware('can:manage-catalog')->group(function (): void {
            Route::resource('employees', EmployeeController::class);
            Route::resource('products', ProductController::class);
            Route::get('inventory-records/stock-on-hand', [InventoryRecordController::class, 'stockOnHand'])->name('inventory-records.stock');
            Route::get('inventory-records/item-names', [InventoryRecordController::class, 'itemNameSuggestions'])->name('inventory-records.item-names');
            Route::get('inventory-records/supplier-names', [InventoryRecordController::class, 'supplierNameSuggestions'])->name('inventory-records.supplier-names');
            Route::resource('inventory-records', InventoryRecordController::class);
            // Recall: forward trace from a delivery to every batch and customer it reached.
            Route::get('inventory-records/{inventoryRecord}/recall', [ProductionTraceabilityController::class, 'recall'])->name('inventory-records.recall');
            Route::get('inventory-records/{inventoryRecord}/recall/export', [ProductionTraceabilityController::class, 'recallExport'])->name('inventory-records.recall.export');
            Route::post('inventory-records/{inventoryRecord}/incidents', [ProductionTraceabilityController::class, 'storeLotIncident'])->name('inventory-records.incidents.store');
            Route::resource('customers', CustomerController::class);
            Route::resource('suppliers', SupplierController::class);
            Route::resource('expenses', ExpenseController::class)->except(['show']);
            Route::resource('expenses/categories', ExpenseCategoryController::class)
                ->except(['show', 'create', 'edit'])
                ->names('expense-categories')
                ->parameters(['categories' => 'expense_category']);
            Route::resource('contact-submissions', ContactSubmissionController::class)->only(['index', 'show', 'destroy']);

            // ── Site Settings ────────────────────────────────────────────────
            Route::prefix('settings')->name('settings.')->group(function (): void {
                Route::get('/general', [GeneralSettingsController::class, 'edit'])->name('general');
                Route::put('/general', [GeneralSettingsController::class, 'update'])->name('general.update');

                Route::resource('contacts', ContactChannelController::class)
                    ->except(['show'])
                    ->parameters(['contacts' => 'channel']);

                Route::resource('email-routing', EmailRoutingController::class)
                    ->except(['show'])
                    ->parameters(['email-routing' => 'rule']);

                Route::resource('images', SiteImageController::class)
                    ->except(['show', 'edit', 'update'])
                    ->parameters(['images' => 'image']);
                Route::post('images/{image}/toggle', [SiteImageController::class, 'toggle'])->name('images.toggle');
            });
        });

        // Owner / Manager / Sales — sales role is scoped to its own records in the controller.
        Route::middleware('can:record-sales')->group(function (): void {
            Route::get('sales/customer-names', [SaleController::class, 'customerNameSuggestions'])->name('sales.customer-names');
            Route::resource('sales', SaleController::class);
            Route::post('sales/{sale}/payments', [SalePaymentController::class, 'store'])->name('sales.payments.store');
            Route::delete('sales/{sale}/payment-submissions/{submission}', [SalePaymentController::class, 'withdraw'])->name('sales.payment-submissions.withdraw');
            Route::delete('sales/{sale}/payments/{payment}', [SalePaymentController::class, 'destroy'])->name('sales.payments.destroy');
        });

        // Stock returns against delivered orders. Sales staff see their own orders' returns;
        // inspection and approval are checked in the controller (approve-returns).
        Route::middleware('can:record-sales')->group(function (): void {
            Route::resource('stock-returns', StockReturnController::class)->except(['destroy']);
            Route::prefix('stock-returns/{stockReturn}')->name('stock-returns.')->controller(StockReturnController::class)->group(function (): void {
                Route::post('/submit', 'submit')->name('submit');
                Route::post('/approve', 'approve')->name('approve');
                Route::post('/reject', 'reject')->name('reject');
                Route::post('/cancel', 'cancel')->name('cancel');
                Route::post('/documents', 'uploadDocuments')->name('documents.store');
                Route::get('/documents/{document}', 'downloadDocument')->name('documents.show');
                Route::delete('/documents/{document}', 'destroyDocument')->name('documents.destroy');
            });
        });

        // Free samples from production. Approval is checked in the controller (approve-samples).
        Route::middleware('can:record-samples')->group(function (): void {
            Route::resource('production-samples', ProductionSampleController::class)->except(['destroy']);
            Route::prefix('production-samples/{productionSample}')->name('production-samples.')->controller(ProductionSampleController::class)->group(function (): void {
                Route::post('/approve', 'approve')->name('approve');
                Route::post('/reject', 'reject')->name('reject');
                Route::post('/cancel', 'cancel')->name('cancel');
                Route::post('/documents', 'uploadDocuments')->name('documents.store');
                Route::get('/documents/{document}', 'downloadDocument')->name('documents.show');
                Route::delete('/documents/{document}', 'destroyDocument')->name('documents.destroy');
            });
        });

        // Owner / Manager / Production — production role is scoped to its own batches in the controller.
        Route::middleware('can:record-production')->group(function (): void {
            Route::resource('productions', ProductionController::class);

            // Traceability: quality checks and release, incidents, labels and trace reports.
            Route::prefix('productions/{production}')->name('productions.')->controller(ProductionTraceabilityController::class)->group(function (): void {
                Route::post('/quality-checks', 'storeQualityCheck')->name('quality-checks.store');
                Route::delete('/quality-checks/{check}', 'destroyQualityCheck')->name('quality-checks.destroy');
                Route::post('/release', 'release')->name('release');
                Route::post('/incidents', 'storeIncident')->name('incidents.store');
                Route::patch('/incidents/{incident}', 'closeIncident')->name('incidents.close');
                Route::get('/label', 'label')->name('label');
                Route::get('/trace/{format}', 'export')->whereIn('format', ['xlsx', 'pdf'])->name('trace.export');
            });
        });

        // Owner / Manager — operational e-commerce hub.
        Route::middleware('can:manage-catalog')->prefix('ecommerce')->name('ecommerce.')->group(function (): void {
            Route::get('/catalog', EcommerceCatalogController::class)->name('catalog');
            Route::get('/catalog/products', fn (Request $request) => redirect()->route('admin.ecommerce.catalog', array_merge($request->query(), ['module' => 'products'])))->name('catalog.products');
            Route::get('/catalog/categories', fn (Request $request) => redirect()->route('admin.ecommerce.catalog', array_merge($request->query(), ['module' => 'categories'])))->name('catalog.categories');
            Route::get('/catalog/variants', fn (Request $request) => redirect()->route('admin.ecommerce.catalog', array_merge($request->query(), ['module' => 'variants'])))->name('catalog.variants');
            Route::get('/catalog/images', fn (Request $request) => redirect()->route('admin.ecommerce.catalog', array_merge($request->query(), ['module' => 'images'])))->name('catalog.images');
            Route::get('/catalog/videos', fn (Request $request) => redirect()->route('admin.ecommerce.catalog', array_merge($request->query(), ['module' => 'videos'])))->name('catalog.videos');
            Route::post('/catalog/videos', [VideoManagementController::class, 'store'])->name('catalog.videos.store');
            Route::put('/catalog/videos/{video}', [VideoManagementController::class, 'update'])->name('catalog.videos.update');
            Route::delete('/catalog/videos/{video}', [VideoManagementController::class, 'destroy'])->name('catalog.videos.destroy');

            Route::get('/catalog/gallery', fn (Request $request) => redirect()->route('admin.ecommerce.catalog', array_merge($request->query(), ['module' => 'gallery'])))->name('catalog.gallery');
            Route::post('/catalog/gallery', [GalleryManagementController::class, 'store'])->name('catalog.gallery.store');
            Route::put('/catalog/gallery/{galleryPhoto}', [GalleryManagementController::class, 'update'])->name('catalog.gallery.update');
            Route::delete('/catalog/gallery/{galleryPhoto}', [GalleryManagementController::class, 'destroy'])->name('catalog.gallery.destroy');

            Route::get('/catalog/partners', fn (Request $request) => redirect()->route('admin.ecommerce.catalog', array_merge($request->query(), ['module' => 'partners'])))->name('catalog.partners');
            Route::post('/catalog/partners', [PartnerManagementController::class, 'store'])->name('catalog.partners.store');
            Route::put('/catalog/partners/{partner}', [PartnerManagementController::class, 'update'])->name('catalog.partners.update');
            Route::delete('/catalog/partners/{partner}', [PartnerManagementController::class, 'destroy'])->name('catalog.partners.destroy');

            Route::get('/customers', EcommerceCustomersController::class)->name('customers');
            Route::get('/customers/profiles', fn (Request $request) => redirect()->route('admin.ecommerce.customers', array_merge($request->query(), ['module' => 'profiles'])))->name('customers.profiles');
            Route::get('/customers/reviews', fn (Request $request) => redirect()->route('admin.ecommerce.customers', array_merge($request->query(), ['module' => 'reviews'])))->name('customers.reviews');
            Route::get('/customers/notifications', fn (Request $request) => redirect()->route('admin.ecommerce.customers', array_merge($request->query(), ['module' => 'notifications'])))->name('customers.notifications');

            Route::get('/sales', EcommerceSalesController::class)->name('sales');
            Route::get('/sales/carts', fn (Request $request) => redirect()->route('admin.ecommerce.sales', array_merge($request->query(), ['module' => 'carts'])))->name('sales.carts');
            Route::get('/sales/orders', fn (Request $request) => redirect()->route('admin.ecommerce.sales', array_merge($request->query(), ['module' => 'orders'])))->name('sales.orders');
            Route::get('/sales/statuses', fn (Request $request) => redirect()->route('admin.ecommerce.sales', array_merge($request->query(), ['module' => 'statuses'])))->name('sales.statuses');
            Route::get('/sales/payments', fn (Request $request) => redirect()->route('admin.ecommerce.sales', array_merge($request->query(), ['module' => 'payments'])))->name('sales.payments');
            Route::get('/sales/discounts', fn (Request $request) => redirect()->route('admin.ecommerce.sales', array_merge($request->query(), ['module' => 'discounts'])))->name('sales.discounts');
            Route::get('/sales/returns', fn (Request $request) => redirect()->route('admin.ecommerce.sales', array_merge($request->query(), ['module' => 'returns'])))->name('sales.returns');

            Route::get('/fulfillment', EcommerceFulfillmentController::class)->name('fulfillment');
            Route::get('/fulfillment/inventory-sync', fn (Request $request) => redirect()->route('admin.ecommerce.fulfillment', array_merge($request->query(), ['module' => 'inventory-sync'])))->name('fulfillment.inventory-sync');
            Route::get('/fulfillment/shipping', fn (Request $request) => redirect()->route('admin.ecommerce.fulfillment', array_merge($request->query(), ['module' => 'shipping'])))->name('fulfillment.shipping');

            Route::get('/analytics', EcommerceAnalyticsController::class)->name('analytics');
            Route::get('/analytics/sales-reports', fn (Request $request) => redirect()->route('admin.ecommerce.analytics', array_merge($request->query(), ['module' => 'sales-reports'])))->name('analytics.sales-reports');
        });
    });
