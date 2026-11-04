<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\EquityMovement;
use App\Models\InventoryRecord;
use App\Models\Loan;
use App\Models\OpeningBalance;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);
    }

    public function test_every_finance_screen_loads_for_an_owner(): void
    {
        $owner = $this->owner();

        foreach ([
            'admin.finance.opening-balances.index',
            'admin.finance.cash-accounts.index',
            'admin.finance.supplier-payments.index',
            'admin.finance.fixed-assets.index',
            'admin.finance.loans.index',
            'admin.finance.equity.index',
        ] as $route) {
            $this->actingAs($owner)->get(route($route))->assertOk();
        }
    }

    public function test_every_finance_screen_renders_its_records(): void
    {
        $owner = $this->owner();
        $account = CashAccount::create(['name' => 'Main till', 'type' => 'cash', 'opening_balance' => 1000, 'opening_balance_date' => '2024-12-31']);
        $record = InventoryRecord::withoutEvents(fn () => InventoryRecord::create(['supplier_name' => 'Green Leaf Cooperative', 'item_type' => 'Raw Material', 'item_name' => 'Fresh Red Chilli', 'quantity_in' => 10, 'quantity_out' => 0, 'damaged' => 0, 'storage_location' => 'Store', 'record_date' => '2025-01-02', 'unit_cost' => 100, 'total_amount' => 1000]));
        SupplierPayment::create(['inventory_record_id' => $record->id, 'supplier_name' => 'Green Leaf Cooperative', 'amount' => 400, 'method' => 'Cash', 'paid_at' => '2025-01-03', 'cash_account_id' => $account->id]);
        \App\Models\FixedAsset::create(['name' => 'Bottling machine', 'acquisition_date' => '2025-01-01', 'cost' => 120000, 'salvage_value' => 0, 'useful_life_months' => 120]);
        $loan = Loan::create(['lender' => 'Bank of Kigali', 'principal' => 200000, 'interest_rate' => 12, 'rate_basis' => 'annual', 'start_date' => '2025-02-01', 'term_months' => 24, 'repayment_frequency' => 'monthly', 'status' => 'active']);
        $loan->repayments()->create(['paid_at' => '2025-03-05', 'total_amount' => 12000, 'principal_portion' => 10000, 'interest_portion' => 2000]);
        EquityMovement::create(['type' => EquityMovement::TYPE_DRAWING, 'amount' => 5000, 'occurred_at' => '2025-04-01']);
        OpeningBalance::create(['as_of_date' => '2024-12-31', 'line_key' => OpeningBalance::CASH, 'amount' => 1000]);
        OpeningBalance::create(['as_of_date' => '2024-12-31', 'line_key' => OpeningBalance::SHARE_CAPITAL, 'amount' => 1000]);

        foreach ([
            'admin.finance.opening-balances.index' => 'Balances',
            'admin.finance.cash-accounts.index' => 'Main till',
            'admin.finance.supplier-payments.index' => 'Green Leaf Cooperative',
            'admin.finance.fixed-assets.index' => 'Bottling machine',
            'admin.finance.loans.index' => 'Bank of Kigali',
            'admin.finance.equity.index' => 'Owner drawing',
        ] as $route => $text) {
            $this->actingAs($owner)->get(route($route))->assertOk()->assertSee($text);
        }
    }

    public function test_sales_staff_cannot_reach_the_finance_ledgers(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_SALES, 'is_active' => true]);

        $this->actingAs($staff)->get(route('admin.finance.opening-balances.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.finance.equity.store'), [])->assertForbidden();
    }

    public function test_unbalanced_opening_balances_are_rejected(): void
    {
        $response = $this->actingAs($this->owner())->post(route('admin.finance.opening-balances.store'), [
            'as_of_date' => '2024-12-31',
            'amounts' => [OpeningBalance::CASH => 500000, OpeningBalance::SHARE_CAPITAL => 100000],
        ]);

        $response->assertSessionHasErrors('amounts');
        $this->assertSame(0, OpeningBalance::query()->count(), 'Nothing should be saved when the set does not balance.');
    }

    public function test_balanced_opening_balances_are_saved(): void
    {
        $this->actingAs($this->owner())->post(route('admin.finance.opening-balances.store'), [
            'as_of_date' => '2024-12-31',
            'amounts' => [
                OpeningBalance::CASH => 500000,
                OpeningBalance::ACCOUNTS_PAYABLE => 200000,
                OpeningBalance::SHARE_CAPITAL => 300000,
            ],
        ])->assertRedirect();

        $this->assertEqualsWithDelta(0, OpeningBalance::balanceCheck('2024-12-31'), 0.01);
        $this->assertEqualsWithDelta(500000, (float) OpeningBalance::where('line_key', OpeningBalance::CASH)->value('amount'), 0.01);
    }

    public function test_recording_a_supplier_payment_settles_the_purchase(): void
    {
        $owner = $this->owner();
        $record = InventoryRecord::withoutEvents(fn () => InventoryRecord::create([
            'supplier_name' => 'Supplier', 'item_type' => 'Raw Material', 'item_name' => 'Chilli',
            'quantity_in' => 10, 'quantity_out' => 0, 'damaged' => 0, 'storage_location' => 'Store',
            'record_date' => '2025-01-05', 'total_amount' => 50000, 'amount_paid' => 0, 'payment_status' => 'Unpaid',
        ]));

        $this->actingAs($owner)->post(route('admin.finance.supplier-payments.store'), [
            'inventory_record_id' => $record->id, 'amount' => 20000,
            'method' => 'Cash', 'paid_at' => '2025-01-06',
        ])->assertRedirect();

        $record->refresh();
        $this->assertEqualsWithDelta(20000, (float) $record->amount_paid, 0.01);
        $this->assertSame('Partial', $record->payment_status);

        // Settling the rest flips it to Paid.
        $this->actingAs($owner)->post(route('admin.finance.supplier-payments.store'), [
            'inventory_record_id' => $record->id, 'amount' => 30000,
            'method' => 'Cash', 'paid_at' => '2025-01-07',
        ])->assertRedirect();

        $this->assertSame('Paid', $record->refresh()->payment_status);
        $this->assertSame(2, SupplierPayment::query()->count());
    }

    public function test_loan_repayment_split_must_equal_the_total_paid(): void
    {
        $owner = $this->owner();
        $loan = Loan::create([
            'lender' => 'Bank of Kigali', 'principal' => 200000, 'interest_rate' => 12,
            'rate_basis' => 'annual', 'start_date' => '2025-01-01', 'term_months' => 24,
            'repayment_frequency' => 'monthly', 'status' => 'active',
        ]);

        // 9,000 + 2,000 does not equal 12,000.
        $this->actingAs($owner)->post(route('admin.finance.loans.repayments.store', $loan), [
            'paid_at' => '2025-02-05', 'total_amount' => 12000,
            'principal_portion' => 9000, 'interest_portion' => 2000,
        ])->assertSessionHasErrors('total_amount');

        $this->assertSame(0, $loan->repayments()->count());

        // Repaying more principal than is outstanding is refused too.
        $this->actingAs($owner)->post(route('admin.finance.loans.repayments.store', $loan), [
            'paid_at' => '2025-02-05', 'total_amount' => 300000,
            'principal_portion' => 300000, 'interest_portion' => 0,
        ])->assertSessionHasErrors('principal_portion');

        $this->actingAs($owner)->post(route('admin.finance.loans.repayments.store', $loan), [
            'paid_at' => '2025-02-05', 'total_amount' => 12000,
            'principal_portion' => 10000, 'interest_portion' => 2000,
        ])->assertRedirect();

        $this->assertSame(1, $loan->repayments()->count());
    }

    public function test_cash_account_and_equity_movements_can_be_recorded(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.finance.cash-accounts.store'), [
            'name' => 'Main till', 'type' => 'cash', 'opening_balance' => 100000,
        ])->assertRedirect();
        $this->assertSame(1, CashAccount::query()->count());

        $this->actingAs($owner)->post(route('admin.finance.equity.store'), [
            'type' => EquityMovement::TYPE_DRAWING, 'amount' => 25000, 'occurred_at' => '2025-03-01',
        ])->assertRedirect();

        // A drawing reduces equity, so its signed effect is negative.
        $this->assertEqualsWithDelta(-25000, EquityMovement::first()->signedAmount(), 0.01);
    }
}
