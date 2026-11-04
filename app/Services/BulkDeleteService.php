<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Deletes several records of one admin list at once.
 *
 * Every record goes through the same controller `destroy` method the single Delete button uses,
 * so each module keeps its own rules (records already in use are refused, files are removed,
 * balances are recalculated, owners are protected). A record the rules refuse is skipped and
 * reported; it never stops the rest of the batch.
 */
class BulkDeleteService
{
    public const MAX_IDS = 200;

    /** module key => label, model, controller, destroy method, abilities the user must hold */
    public const MODULES = [
        'users' => ['label' => 'users', 'model' => \App\Models\User::class, 'controller' => \App\Http\Controllers\Admin\UserController::class, 'gates' => ['manage-users']],
        'employees' => ['label' => 'employees', 'model' => \App\Models\Employee::class, 'controller' => \App\Http\Controllers\Admin\EmployeeController::class, 'gates' => ['manage-catalog']],
        'products' => ['label' => 'products', 'model' => \App\Models\Product::class, 'controller' => \App\Http\Controllers\Admin\ProductController::class, 'gates' => ['manage-catalog']],
        'inventory-records' => ['label' => 'inventory records', 'model' => \App\Models\InventoryRecord::class, 'controller' => \App\Http\Controllers\Admin\InventoryRecordController::class, 'gates' => ['manage-catalog', 'delete-records']],
        'customers' => ['label' => 'customers', 'model' => \App\Models\Customer::class, 'controller' => \App\Http\Controllers\Admin\CustomerController::class, 'gates' => ['manage-catalog']],
        'suppliers' => ['label' => 'suppliers', 'model' => \App\Models\Supplier::class, 'controller' => \App\Http\Controllers\Admin\SupplierController::class, 'gates' => ['manage-catalog']],
        'expenses' => ['label' => 'expenses', 'model' => \App\Models\Expense::class, 'controller' => \App\Http\Controllers\Admin\ExpenseController::class, 'gates' => ['manage-catalog']],
        'expense-categories' => ['label' => 'expense categories', 'model' => \App\Models\ExpenseCategory::class, 'controller' => \App\Http\Controllers\Admin\ExpenseCategoryController::class, 'gates' => ['manage-catalog']],
        'contact-submissions' => ['label' => 'messages', 'model' => \App\Models\ContactSubmission::class, 'controller' => \App\Http\Controllers\Admin\ContactSubmissionController::class, 'gates' => ['manage-catalog']],
        'sales' => ['label' => 'sales', 'model' => \App\Models\Sale::class, 'controller' => \App\Http\Controllers\Admin\SaleController::class, 'gates' => ['record-sales', 'delete-records']],
        'productions' => ['label' => 'productions', 'model' => \App\Models\Production::class, 'controller' => \App\Http\Controllers\Admin\ProductionController::class, 'gates' => ['record-production', 'delete-records']],
        'cash-accounts' => ['label' => 'cash accounts', 'model' => \App\Models\CashAccount::class, 'controller' => \App\Http\Controllers\Admin\CashAccountController::class, 'gates' => ['view-analytics']],
        'supplier-payments' => ['label' => 'supplier payments', 'model' => \App\Models\SupplierPayment::class, 'controller' => \App\Http\Controllers\Admin\SupplierPaymentController::class, 'gates' => ['view-analytics']],
        'fixed-assets' => ['label' => 'fixed assets', 'model' => \App\Models\FixedAsset::class, 'controller' => \App\Http\Controllers\Admin\FixedAssetController::class, 'gates' => ['view-analytics']],
        'loans' => ['label' => 'loans', 'model' => \App\Models\Loan::class, 'controller' => \App\Http\Controllers\Admin\LoanController::class, 'gates' => ['view-analytics']],
        'loan-repayments' => ['label' => 'repayments', 'model' => \App\Models\LoanRepayment::class, 'controller' => \App\Http\Controllers\Admin\LoanController::class, 'method' => 'destroyRepayment', 'gates' => ['view-analytics']],
        'equity-movements' => ['label' => 'equity movements', 'model' => \App\Models\EquityMovement::class, 'controller' => \App\Http\Controllers\Admin\EquityMovementController::class, 'gates' => ['view-analytics']],
        'financial-reports' => ['label' => 'reports', 'model' => \App\Models\FinancialReport::class, 'controller' => \App\Http\Controllers\Admin\FinancialReportController::class, 'gates' => ['view-analytics']],
        'site-contacts' => ['label' => 'contact channels', 'model' => \App\Models\ContactChannel::class, 'controller' => \App\Http\Controllers\Admin\Settings\ContactChannelController::class, 'gates' => ['manage-catalog']],
        'email-routing' => ['label' => 'routing rules', 'model' => \App\Models\EmailRoutingRule::class, 'controller' => \App\Http\Controllers\Admin\Settings\EmailRoutingController::class, 'gates' => ['manage-catalog']],
        'site-images' => ['label' => 'images', 'model' => \App\Models\SiteImage::class, 'controller' => \App\Http\Controllers\Admin\Settings\SiteImageController::class, 'gates' => ['manage-catalog']],
        'videos' => ['label' => 'videos', 'model' => \App\Models\StorefrontVideo::class, 'controller' => \App\Http\Controllers\Admin\Ecommerce\Modules\VideoManagementController::class, 'gates' => ['manage-catalog']],
        'gallery-photos' => ['label' => 'photos', 'model' => \App\Models\StorefrontGalleryPhoto::class, 'controller' => \App\Http\Controllers\Admin\Ecommerce\Modules\GalleryManagementController::class, 'gates' => ['manage-catalog']],
        'partners' => ['label' => 'partners', 'model' => \App\Models\Partner::class, 'controller' => \App\Http\Controllers\Admin\Ecommerce\Modules\PartnerManagementController::class, 'gates' => ['manage-catalog']],
    ];

    public static function module(string $key): ?array
    {
        return self::MODULES[$key] ?? null;
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array{deleted: int, skipped: array<int, array{name: string, reason: string}>, label: string}
     */
    public function delete(string $key, array $ids, User $user): array
    {
        $module = self::module($key);
        abort_if($module === null, 404);
        foreach ($module['gates'] as $ability) {
            abort_unless($user->can($ability), 403);
        }

        $ids = collect($ids)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->take(self::MAX_IDS)->values();
        /** @var class-string<Model> $model */
        $model = $module['model'];
        $method = $module['method'] ?? 'destroy';
        $controller = app($module['controller']);
        $records = $model::query()->whereKey($ids->all())->get();

        $deleted = 0;
        $skipped = [];

        foreach ($records as $record) {
            $reason = $this->deleteOne($controller, $method, $model, $record);
            if ($reason === null) {
                $deleted++;
            } else {
                $skipped[] = ['name' => $this->nameOf($record), 'reason' => $reason];
            }
        }

        // Ids that no longer exist (deleted in another tab) are not an error, just nothing to do.
        return ['deleted' => $deleted, 'skipped' => $skipped, 'label' => $module['label']];
    }

    /** Runs the controller's own destroy for one record. Returns null on success, or why it was refused. */
    private function deleteOne(object $controller, string $method, string $model, Model $record): ?string
    {
        $session = app('session.store');
        $reason = null;

        try {
            DB::transaction(function () use ($controller, $method, $model, $record): void {
                $arguments = [];
                foreach ((new \ReflectionMethod($controller, $method))->getParameters() as $parameter) {
                    $type = $parameter->getType();
                    if ($type instanceof \ReflectionNamedType && is_a($model, $type->getName(), true)) {
                        $arguments[$parameter->getName()] = $record;
                    }
                }
                app()->call([$controller, $method], $arguments);
            });
        } catch (HttpException $e) {
            $reason = $e->getStatusCode() === 403 ? 'You are not allowed to delete this record.' : 'Could not be deleted.';
        } catch (HttpResponseException $e) {
            $reason = 'Could not be deleted.';
        } catch (\Throwable $e) {
            Log::warning('[bulk-delete] record could not be deleted', ['model' => $model, 'id' => $record->getKey(), 'error' => $e->getMessage()]);
            $reason = 'It is linked to other records and cannot be deleted.';
        }

        // A refused delete returns a redirect carrying the reason; take it, then clear it so it
        // is not shown a second time on the next page.
        $refusal = $session->get('errors')?->getBag('default')->first() ?: $session->get('warning');
        $session->forget(['errors', 'warning', 'status']);

        if ($reason === null && $model::query()->whereKey($record->getKey())->exists()) {
            $reason = $refusal ?: 'Could not be deleted.';
        }

        return $reason;
    }

    private function nameOf(Model $record): string
    {
        foreach (['name', 'title', 'sales_id', 'batch_number', 'lot_number', 'subject', 'recipient_email', 'label', 'value', 'type'] as $field) {
            $value = $record->getAttribute($field);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return \Illuminate\Support\Str::limit((string) $value, 50);
            }
        }

        return '#'.$record->getKey();
    }
}
