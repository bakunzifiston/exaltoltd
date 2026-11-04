<?php

namespace Tests\Feature;

use App\Exceptions\PaymentReviewException;
use App\Mail\OnlineOrderPaymentReceivedMail;
use App\Models\CashAccount;
use App\Models\Product;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SalePaymentSubmission;
use App\Models\User;
use App\Notifications\PaymentApprovedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentSubmittedNotification;
use App\Services\PaymentSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class PaymentApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $approver;

    private User $manager;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->owner = User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);
        $this->approver = User::factory()->create(['role' => User::ROLE_MANAGER, 'is_active' => true, 'can_approve_payments' => true]);
        $this->manager = User::factory()->create(['role' => User::ROLE_MANAGER, 'is_active' => true, 'can_approve_payments' => false]);
        $this->seller = User::factory()->create(['role' => User::ROLE_SALES, 'is_active' => true]);
    }

    public function test_reporting_a_mobile_money_payment_requires_proof_and_a_reference(): void
    {
        $sale = $this->sale($this->seller);

        $this->actingAs($this->seller)
            ->post(route('admin.sales.payments.store', $sale), $this->paymentPayload(['proofs' => [], 'reference' => '']))
            ->assertSessionHasErrors(['proofs', 'reference']);

        $this->assertSame(0, SalePaymentSubmission::count());
    }

    public function test_cash_can_be_reported_without_proof_but_still_needs_approval(): void
    {
        Notification::fake();
        $sale = $this->sale($this->seller);

        $this->actingAs($this->seller)
            ->post(route('admin.sales.payments.store', $sale), $this->paymentPayload(['method' => 'Cash', 'reference' => '', 'proofs' => []]))
            ->assertSessionHasNoErrors();

        $this->assertSame(SalePaymentSubmission::STATUS_PENDING, SalePaymentSubmission::sole()->status);
        $this->assertSame(0, SalePayment::count());
    }

    public function test_a_reported_payment_waits_for_approval_and_is_not_counted_as_received(): void
    {
        Notification::fake();
        $sale = $this->sale($this->seller);

        $this->actingAs($this->seller)
            ->post(route('admin.sales.payments.store', $sale), $this->paymentPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'submitted for approval'));

        $submission = SalePaymentSubmission::with('proofs')->sole();
        $this->assertSame(SalePaymentSubmission::STATUS_PENDING, $submission->status);
        $this->assertSame($this->seller->id, $submission->submitted_by);
        $this->assertCount(1, $submission->proofs);

        // Nothing reaches the ledger every finance report reads.
        $this->assertSame(0, SalePayment::count());
        $sale->refresh();
        $this->assertSame('Pending', $sale->payment_status);
        $this->assertEquals(0, (float) $sale->amount_paid);
        $this->assertEquals(100000, $sale->pending_approval_amount);

        // Stored privately, under a server-chosen name.
        $proof = $submission->proofs->first();
        Storage::disk('local')->assertExists($proof->path);
        Storage::disk('public')->assertMissing($proof->path);
        $this->assertStringNotContainsString('momo-screenshot', $proof->path);
        $this->assertSame('momo-screenshot.jpg', $proof->original_name);
    }

    public function test_approvers_are_notified_but_not_the_submitter_or_managers_without_the_right(): void
    {
        Notification::fake();
        $sale = $this->sale($this->seller);

        $this->actingAs($this->seller)->post(route('admin.sales.payments.store', $sale), $this->paymentPayload());

        Notification::assertSentTo([$this->owner, $this->approver], PaymentSubmittedNotification::class);
        Notification::assertNotSentTo([$this->seller, $this->manager], PaymentSubmittedNotification::class);
    }

    public function test_sales_staff_cannot_approve_and_nobody_approves_their_own_report(): void
    {
        Notification::fake();
        $submission = $this->report($this->seller);

        $this->actingAs($this->seller)
            ->post(route('admin.payment-approvals.approve', $submission))
            ->assertForbidden();
        $this->actingAs($this->manager)
            ->post(route('admin.payment-approvals.approve', $submission))
            ->assertForbidden();

        // Even once granted approval rights, the submitter can't confirm their own claim.
        $this->seller->update(['can_approve_payments' => true]);
        $this->actingAs($this->seller)
            ->post(route('admin.payment-approvals.approve', $submission))
            ->assertSessionHasErrors('review');

        $this->assertTrue($submission->fresh()->isPending());
        $this->assertSame(0, SalePayment::count());
    }

    public function test_approving_writes_the_ledger_marks_the_sale_paid_and_tells_the_sales_person(): void
    {
        Notification::fake();
        $account = CashAccount::create(['name' => 'MTN MoMo Till', 'type' => CashAccount::TYPE_MOBILE_MONEY, 'is_active' => true]);
        $submission = $this->report($this->seller);

        $this->actingAs($this->approver)
            ->post(route('admin.payment-approvals.approve', $submission), ['cash_account_id' => $account->id])
            ->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame(SalePaymentSubmission::STATUS_APPROVED, $submission->status);
        $this->assertSame($this->approver->id, $submission->reviewed_by);

        $payment = SalePayment::sole();
        $this->assertSame($submission->sale_payment_id, $payment->id);
        $this->assertEquals(100000, (float) $payment->amount);
        $this->assertSame('MP241002.1234.A56789', $payment->reference);
        $this->assertSame($account->id, $payment->cash_account_id);
        $this->assertSame($this->seller->id, $payment->recorded_by, 'The sales person keeps the collection credit.');

        $sale = $submission->sale->fresh();
        $this->assertSame('Paid', $sale->payment_status);
        $this->assertEquals(100000, (float) $sale->amount_paid);
        $this->assertSame(
            $this->approver->id,
            $sale->statusEvents()->where('field', 'payment_status')->where('to_status', 'Paid')->value('changed_by'),
        );

        Notification::assertSentTo($this->seller, PaymentApprovedNotification::class);
        Notification::assertNotSentTo($this->approver, PaymentApprovedNotification::class);
    }

    public function test_rejecting_needs_a_reason_tells_the_sales_person_and_allows_a_new_report(): void
    {
        Notification::fake();
        $submission = $this->report($this->seller);

        $this->actingAs($this->approver)
            ->post(route('admin.payment-approvals.reject', $submission), ['review_note' => ''])
            ->assertSessionHasErrors('review_note');
        $this->assertTrue($submission->fresh()->isPending());

        $this->actingAs($this->approver)
            ->post(route('admin.payment-approvals.reject', $submission), ['review_note' => 'TX ID not found on the MoMo statement'])
            ->assertSessionHasNoErrors();

        $submission->refresh();
        $this->assertSame(SalePaymentSubmission::STATUS_REJECTED, $submission->status);
        $this->assertSame('TX ID not found on the MoMo statement', $submission->review_note);
        $this->assertSame('Pending', $submission->sale->fresh()->payment_status);
        $this->assertSame(0, SalePayment::count());

        Notification::assertSentTo(
            $this->seller,
            PaymentRejectedNotification::class,
            fn (PaymentRejectedNotification $n) => str_contains($n->toArray($this->seller)['message'], 'TX ID not found')
                && str_contains((string) $n->toMail($this->seller)->render(), 'TX ID not found on the MoMo statement'),
        );

        // The full balance is open again for a corrected report.
        $this->actingAs($this->seller)
            ->post(route('admin.sales.payments.store', $submission->sale), $this->paymentPayload(['reference' => 'MP241002.9999.B11111']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, SalePaymentSubmission::count());
    }

    public function test_a_payment_cannot_be_reviewed_twice(): void
    {
        Notification::fake();
        $submission = $this->report($this->seller);
        $service = app(PaymentSubmissionService::class);

        $service->approve($submission, $this->approver);

        try {
            $service->approve($submission, $this->owner);
            $this->fail('A second approval should be refused.');
        } catch (PaymentReviewException $e) {
            $this->assertStringContainsString('already been approved', $e->getMessage());
        }

        $this->expectException(PaymentReviewException::class);
        $service->reject($submission, $this->owner, 'Changed my mind');
    }

    public function test_reports_cannot_exceed_the_invoice_and_approval_rechecks_the_total(): void
    {
        Notification::fake();
        $sale = $this->sale($this->seller);

        $this->actingAs($this->seller)
            ->post(route('admin.sales.payments.store', $sale), $this->paymentPayload(['amount' => 60000]))
            ->assertSessionHasNoErrors();

        // 60,000 is already awaiting approval, so only 40,000 may still be reported.
        $this->actingAs($this->seller)
            ->post(route('admin.sales.payments.store', $sale), $this->paymentPayload(['amount' => 50000, 'reference' => 'MP-OTHER']))
            ->assertSessionHasErrors('amount');
        $this->assertSame(1, SalePaymentSubmission::count());

        // The invoice is cut after the report was made: approval must not overpay it.
        $sale->update(['total_revenue' => 50000]);
        $this->actingAs($this->approver)
            ->post(route('admin.payment-approvals.approve', SalePaymentSubmission::sole()))
            ->assertSessionHasErrors('review');
        $this->assertSame(0, SalePayment::count());
    }

    public function test_proof_files_are_only_shown_to_people_involved(): void
    {
        Notification::fake();
        $submission = $this->report($this->seller);
        $proof = $submission->proofs()->sole();
        $otherSeller = User::factory()->create(['role' => User::ROLE_SALES, 'is_active' => true]);

        $this->actingAs($otherSeller)->get(route('admin.payment-proofs.show', $proof))->assertForbidden();
        $this->actingAs($this->seller)->get(route('admin.payment-proofs.show', $proof))->assertOk();
        $this->actingAs($this->approver)->get(route('admin.payment-proofs.show', $proof))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_a_reused_proof_or_reference_is_flagged_to_the_approver(): void
    {
        Notification::fake();
        $screenshot = UploadedFile::fake()->image('momo.jpg', 400, 700);
        $bytes = file_get_contents($screenshot->getRealPath());

        $first = $this->report($this->seller, [UploadedFile::fake()->createWithContent('momo.jpg', $bytes)]);
        $second = $this->report($this->seller, [UploadedFile::fake()->createWithContent('same-again.jpg', $bytes)]);

        $this->actingAs($this->approver)
            ->get(route('admin.payment-approvals.show', $second))
            ->assertOk()
            ->assertSee('The same proof file was already submitted for '.$first->sale->sales_id)
            ->assertSee('Reference MP241002.1234.A56789 was already used for '.$first->sale->sales_id);
    }

    public function test_a_mail_failure_does_not_undo_an_approval_and_the_in_app_copy_is_kept(): void
    {
        $submission = $this->report($this->seller);

        Mail::extend('exploding', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new RuntimeException('SMTP is down');
            }

            public function __toString(): string
            {
                return 'exploding://';
            }
        });
        config(['mail.mailers.exploding' => ['transport' => 'exploding'], 'mail.default' => 'exploding']);
        app('mail.manager')->forgetMailers();

        $this->actingAs($this->approver)
            ->post(route('admin.payment-approvals.approve', $submission))
            ->assertSessionHasNoErrors();

        $this->assertSame(SalePaymentSubmission::STATUS_APPROVED, $submission->fresh()->status);
        $this->assertSame(1, SalePayment::count());
        $this->assertSame(1, $this->seller->notifications()->count());
        $this->assertSame('Payment received and approved', $this->seller->notifications()->first()->data['title']);
    }

    public function test_an_approver_recording_a_payment_has_it_approved_at_once(): void
    {
        Notification::fake();
        $sale = $this->sale($this->seller);

        $this->actingAs($this->approver)
            ->post(route('admin.sales.payments.store', $sale), $this->paymentPayload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'payment recorded'));

        $submission = SalePaymentSubmission::sole();
        $this->assertSame(SalePaymentSubmission::STATUS_APPROVED, $submission->status);
        $this->assertSame($this->approver->id, $submission->reviewed_by);
        $this->assertCount(1, $submission->proofs, 'Proof is kept even when approval is immediate.');
        $this->assertSame('Paid', $sale->fresh()->payment_status);

        Notification::assertNothingSentTo($this->owner);
        Notification::assertSentTo($this->seller, PaymentApprovedNotification::class);
    }

    public function test_marking_a_sale_paid_on_the_form_needs_proof_and_waits_for_approval(): void
    {
        Notification::fake();
        $production = $this->production();
        $payload = [
            'customer_name' => 'Walk-in Customer',
            'sale_date' => '2026-10-02',
            'payment_status' => 'Paid',
            'delivery_status' => 'Delivered',
            'sales_channel' => 'Momo Pay',
            'items' => [['product_id' => $production->product_id, 'production_id' => $production->id, 'quantity_sold' => 2, 'unit_price' => 5000]],
        ];

        $this->actingAs($this->seller)
            ->post(route('admin.sales.store'), $payload)
            ->assertSessionHasErrors(['payment_proofs', 'payment_reference']);
        $this->assertSame(0, Sale::count());

        $this->actingAs($this->seller)
            ->post(route('admin.sales.store'), $payload + [
                'payment_reference' => 'MP241002.5555.C22222',
                'payment_proofs' => [UploadedFile::fake()->image('momo.png')],
            ])
            ->assertSessionHasNoErrors();

        $sale = Sale::sole();
        $this->assertSame('Pending', $sale->payment_status, 'A sale reported as paid stays pending until approved.');
        $this->assertSame(0, $sale->payments()->count());

        $submission = $sale->paymentSubmissions()->sole();
        $this->assertSame(SalePaymentSubmission::STATUS_PENDING, $submission->status);
        $this->assertEquals(10000, (float) $submission->amount);
        $this->assertSame('MoMo', $submission->method);
        $this->assertSame('MP241002.5555.C22222', $submission->reference);
        Notification::assertSentTo($this->approver, PaymentSubmittedNotification::class);

        // Re-saving the sale as Paid doesn't need a second proof while the first is pending.
        $this->actingAs($this->seller)
            ->put(route('admin.sales.update', $sale), $payload)
            ->assertSessionHasNoErrors();
        $this->assertSame(1, SalePaymentSubmission::count());
        $this->assertSame('Pending', $sale->fresh()->payment_status);
    }

    public function test_only_shop_order_customers_are_emailed_when_their_payment_is_approved(): void
    {
        Notification::fake();
        Mail::fake();
        // Emails go out after the response; called outside a request, run them at once.
        $this->withoutDefer();

        $walkIn = $this->report($this->seller, null, $this->sale($this->seller, ['customer_email' => 'walkin@example.com']));
        $online = $this->report($this->seller, null, $this->sale($this->seller, [
            'sales_channel' => 'Online Store',
            'customer_email' => 'shopper@example.com',
        ]), ['reference' => 'MP-ONLINE-1']);

        $service = app(PaymentSubmissionService::class);
        $service->approve($walkIn, $this->approver);
        $service->approve($online, $this->approver);

        Mail::assertSent(OnlineOrderPaymentReceivedMail::class, fn ($mail) => $mail->hasTo('shopper@example.com')
            && str_contains($mail->render(), 'Your order is now being processed'));
        Mail::assertNotSent(OnlineOrderPaymentReceivedMail::class, fn ($mail) => $mail->hasTo('walkin@example.com'));
        Mail::assertSentCount(1);
    }

    public function test_only_the_submitter_can_withdraw_a_pending_report(): void
    {
        Notification::fake();
        $submission = $this->report($this->seller);

        $this->actingAs($this->approver)
            ->delete(route('admin.sales.payment-submissions.withdraw', [$submission->sale, $submission]))
            ->assertSessionHasErrors('submission');
        $this->assertTrue($submission->fresh()->isPending());

        $this->actingAs($this->seller)
            ->delete(route('admin.sales.payment-submissions.withdraw', [$submission->sale, $submission]))
            ->assertSessionHasNoErrors();
        $this->assertSame(SalePaymentSubmission::STATUS_WITHDRAWN, $submission->fresh()->status);
    }

    public function test_removing_a_confirmed_payment_keeps_its_report_as_reversed(): void
    {
        Notification::fake();
        $submission = $this->report($this->seller);
        app(PaymentSubmissionService::class)->approve($submission, $this->approver);
        $sale = $submission->sale->fresh();

        $this->actingAs($this->owner)
            ->delete(route('admin.sales.payments.destroy', [$sale, $submission->fresh()->sale_payment_id]))
            ->assertRedirect();

        $this->assertSame(SalePaymentSubmission::STATUS_REVERSED, $submission->fresh()->status);
        $this->assertSame('Pending', $sale->fresh()->payment_status);
        $this->assertCount(1, $submission->proofs()->get());
    }

    public function test_deleting_a_sale_removes_its_proof_files(): void
    {
        Notification::fake();
        $submission = $this->report($this->seller);
        $path = $submission->proofs()->sole()->path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->owner)->delete(route('admin.sales.destroy', $submission->sale))->assertRedirect();

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, SalePaymentSubmission::count());
    }

    public function test_the_owner_grants_approval_rights_from_the_users_screen(): void
    {
        $this->assertFalse($this->seller->can('approve-payments'));

        $this->actingAs($this->owner)->put(route('admin.users.update', $this->seller), [
            'name' => $this->seller->name,
            'email' => $this->seller->email,
            'role' => User::ROLE_SALES,
            'is_active' => '1',
            'can_approve_payments' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($this->seller->fresh()->can_approve_payments);
        $this->assertTrue($this->seller->fresh()->can('approve-payments'));
        $this->assertTrue($this->owner->can('approve-payments'), 'Owners can always approve.');
    }

    public function test_approval_screens_render_for_approvers_and_are_closed_to_sales_staff(): void
    {
        Notification::fake();
        $submission = $this->report($this->seller);

        $this->actingAs($this->approver)->get(route('admin.payment-approvals.index'))
            ->assertOk()
            ->assertSee($submission->sale->sales_id)
            ->assertSee('Review');
        $this->actingAs($this->approver)->get(route('admin.payment-approvals.show', $submission))
            ->assertOk()
            ->assertSee('Approve payment')
            ->assertSee('Reject');
        $this->actingAs($this->approver)->get(route('admin.sales.show', $submission->sale))
            ->assertOk()
            ->assertSee('Reported Payments')
            ->assertSee('Approve payment');
        $this->actingAs($this->approver)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('1 payment awaiting approval');

        $this->actingAs($this->seller)->get(route('admin.payment-approvals.index'))->assertForbidden();
        $this->actingAs($this->seller)->get(route('admin.sales.show', $submission->sale))
            ->assertOk()
            ->assertSee('awaiting approval')
            ->assertSee('Withdraw')
            ->assertDontSee('Approve payment');
        $this->actingAs($this->seller)->get(route('admin.sales.index', ['payment_status' => 'Awaiting Approval']))
            ->assertOk()
            ->assertSee($submission->sale->sales_id);
        $this->actingAs($this->seller)->get(route('admin.sales.create'))
            ->assertOk()
            ->assertSee('Proof of payment')
            ->assertSee('enctype="multipart/form-data"', false);
        $this->actingAs($this->seller)->get(route('admin.sales.edit', $submission->sale))->assertOk();
    }

    public function test_notifications_page_lists_and_opens_in_app_notifications(): void
    {
        $submission = $this->report($this->seller);
        $notification = $this->approver->notifications()->sole();

        $this->actingAs($this->approver)->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Payment awaiting approval');

        $this->actingAs($this->approver)->get(route('admin.notifications.open', $notification->id))
            ->assertRedirect(route('admin.payment-approvals.show', $submission));
        $this->assertNotNull($notification->fresh()->read_at);

        // Someone else's notification can't be opened.
        $this->actingAs($this->seller)->get(route('admin.notifications.open', $notification->id))->assertNotFound();
    }

    // --- Helpers ------------------------------------------------------------

    private function sale(User $creator, array $overrides = []): Sale
    {
        return Sale::create(array_merge([
            'customer_name' => 'Jane Customer',
            'total_revenue' => 100000,
            'payment_status' => 'Pending',
            'delivery_status' => 'Pending',
            'sales_channel' => 'Momo Pay',
            'sale_date' => '2026-10-01',
            'created_by' => $creator->id,
        ], $overrides));
    }

    /** A pending report made through the service (no HTTP). */
    private function report(User $by, ?array $proofs = null, ?Sale $sale = null, array $data = []): SalePaymentSubmission
    {
        $this->actingAs($by);

        return app(PaymentSubmissionService::class)->submit(
            $sale ?? $this->sale($by),
            array_merge(['amount' => 100000, 'method' => 'MoMo', 'paid_at' => '2026-10-02', 'reference' => 'MP241002.1234.A56789'], $data),
            $proofs ?? [UploadedFile::fake()->image('momo.jpg')],
            $by,
        );
    }

    private function paymentPayload(array $overrides = []): array
    {
        return array_merge([
            'amount' => 100000,
            'method' => 'MoMo',
            'paid_at' => '2026-10-02',
            'reference' => 'MP241002.1234.A56789',
            'proofs' => [UploadedFile::fake()->image('momo-screenshot.jpg')],
        ], $overrides);
    }

    private function production(): Production
    {
        $product = Product::create(['name' => 'Neza Sauce 250ml', 'type' => 'Neza Sauce 250ml', 'barcode' => 'PAY-TEST-250']);

        return Production::withoutEvents(fn () => Production::create([
            'batch_id' => 'SHK/BN/900',
            'product_id' => $product->id,
            'barcode' => $product->barcode,
            'quantity_produced' => 50,
            'quantity_remaining' => 50,
            'damaged' => 0,
            'production_date' => '2026-09-30',
            'inventory_record_id' => [],
        ]));
    }
}
