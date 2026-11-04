<?php

namespace App\Services;

use App\Exceptions\PaymentReviewException;
use App\Mail\OnlineOrderPaymentReceivedMail;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SalePaymentSubmission;
use App\Models\User;
use App\Notifications\PaymentApprovedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationSender;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Reported payments: submit with proof, then approve (writes the ledger) or reject.
 *
 * The ledger (`sale_payments`) is only ever written by an approval, so every report that
 * reads it keeps meaning "money confirmed received". People who may approve payments have
 * their own submissions approved on the spot; the proof is still kept.
 */
class PaymentSubmissionService
{
    private const PROOF_DISK = 'local';

    /**
     * @param  array{amount: float|string, method: string, paid_at: string, reference?: ?string, notes?: ?string, cash_account_id?: ?int}  $data
     * @param  list<UploadedFile>  $proofs
     */
    public function submit(Sale $sale, array $data, array $proofs, ?User $by): SalePaymentSubmission
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($sale, $data, $proofs, $by, &$stored) {
                $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
                $amount = round((float) $data['amount'], 2);
                $open = $sale->submittable_balance;

                if ($amount <= 0) {
                    throw new PaymentReviewException('The payment amount must be greater than zero.');
                }

                if ($amount > $open + 0.009) {
                    $pending = $sale->pending_approval_amount;
                    throw new PaymentReviewException(sprintf(
                        'Amount exceeds the %s RWF still open on this sale%s.',
                        number_format($open, 0),
                        $pending > 0 ? ' ('.number_format($pending, 0).' RWF is already awaiting approval)' : '',
                    ));
                }

                if ($proofs === [] && SalePaymentSubmission::proofRequiredFor($data['method'])) {
                    throw new PaymentReviewException('Upload proof of payment for '.$data['method'].' payments.');
                }

                $submission = $sale->paymentSubmissions()->create([
                    'amount' => $amount,
                    'method' => $data['method'],
                    'paid_at' => $data['paid_at'],
                    'reference' => filled($data['reference'] ?? null) ? trim($data['reference']) : null,
                    'notes' => $data['notes'] ?? null,
                    'cash_account_id' => $data['cash_account_id'] ?? null,
                    'status' => SalePaymentSubmission::STATUS_PENDING,
                    'submitted_by' => $by?->id,
                ]);

                foreach ($proofs as $file) {
                    $stored[] = $this->storeProof($submission, $file, $by);
                }

                if ($by?->canApprovePayments()) {
                    $this->confirm($submission, $by, $submission->cash_account_id, null);
                } else {
                    DB::afterCommit(fn () => $this->notifyApprovers($submission));
                }

                return $submission;
            });
        } catch (Throwable $e) {
            Storage::disk(self::PROOF_DISK)->delete($stored);
            throw $e;
        }
    }

    public function approve(SalePaymentSubmission $submission, User $reviewer, ?int $cashAccountId = null, ?string $note = null): SalePaymentSubmission
    {
        return DB::transaction(function () use ($submission, $reviewer, $cashAccountId, $note) {
            $locked = $this->lockForReview($submission, $reviewer);
            $this->confirm($locked, $reviewer, $cashAccountId ?? $locked->cash_account_id, $note);

            return $locked;
        });
    }

    public function reject(SalePaymentSubmission $submission, User $reviewer, string $reason): SalePaymentSubmission
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new PaymentReviewException('Give a reason so the sales person knows what to fix.');
        }

        return DB::transaction(function () use ($submission, $reviewer, $reason) {
            $locked = $this->lockForReview($submission, $reviewer);
            $locked->update([
                'status' => SalePaymentSubmission::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $reason,
            ]);

            DB::afterCommit(fn () => $this->notifyRejected($locked));

            return $locked;
        });
    }

    /** The submitter takes back a claim they made by mistake, before anyone reviews it. */
    public function withdraw(SalePaymentSubmission $submission, User $by): SalePaymentSubmission
    {
        return DB::transaction(function () use ($submission, $by) {
            $locked = SalePaymentSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

            if ($locked->submitted_by !== $by->id) {
                throw new PaymentReviewException('Only the person who submitted this payment can withdraw it.');
            }
            $this->ensurePending($locked);

            $locked->update(['status' => SalePaymentSubmission::STATUS_WITHDRAWN]);

            return $locked;
        });
    }

    /**
     * Signs that a proof or reference is being reused — the cheapest fraud to attempt.
     *
     * @return list<string>
     */
    public function duplicateWarnings(SalePaymentSubmission $submission): array
    {
        $warnings = [];

        $hashes = $submission->proofs->pluck('sha256')->filter()->unique()->values();
        if ($hashes->isNotEmpty()) {
            SalePaymentSubmission::query()
                ->with('sale:id,sales_id')
                ->whereKeyNot($submission->id)
                ->whereHas('proofs', fn ($q) => $q->whereIn('sha256', $hashes))
                ->get()
                ->each(function (SalePaymentSubmission $other) use (&$warnings) {
                    $warnings[] = sprintf(
                        'The same proof file was already submitted for %s (%s RWF, %s).',
                        $other->sale?->sales_id ?? 'a deleted sale',
                        number_format((float) $other->amount, 0),
                        strtolower($other->statusLabel()),
                    );
                });
        }

        $reference = mb_strtolower(trim((string) $submission->reference));
        if ($reference !== '') {
            SalePaymentSubmission::query()
                ->with('sale:id,sales_id')
                ->whereKeyNot($submission->id)
                ->whereRaw('LOWER(TRIM(reference)) = ?', [$reference])
                ->get()
                ->each(function (SalePaymentSubmission $other) use (&$warnings, $submission) {
                    $warnings[] = sprintf(
                        'Reference %s was already used for %s (%s RWF, %s).',
                        $submission->reference,
                        $other->sale?->sales_id ?? 'a deleted sale',
                        number_format((float) $other->amount, 0),
                        strtolower($other->statusLabel()),
                    );
                });

            // Payments recorded before approvals existed have no submission of their own.
            SalePayment::query()
                ->with('sale:id,sales_id')
                ->whereDoesntHave('submission')
                ->whereRaw('LOWER(TRIM(reference)) = ?', [$reference])
                ->get()
                ->each(function (SalePayment $payment) use (&$warnings, $submission) {
                    $warnings[] = sprintf(
                        'Reference %s matches a payment already recorded on %s (%s RWF).',
                        $submission->reference,
                        $payment->sale?->sales_id ?? 'a deleted sale',
                        number_format((float) $payment->amount, 0),
                    );
                });
        }

        return $warnings;
    }

    // --- Internals ----------------------------------------------------------

    private function lockForReview(SalePaymentSubmission $submission, User $reviewer): SalePaymentSubmission
    {
        if (! $reviewer->canApprovePayments()) {
            throw new PaymentReviewException('You are not allowed to review payments.');
        }

        $locked = SalePaymentSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();
        $this->ensurePending($locked);

        if ($locked->submitted_by !== null && $locked->submitted_by === $reviewer->id) {
            throw new PaymentReviewException('You cannot review a payment you submitted. Another approver must review it.');
        }

        return $locked;
    }

    private function ensurePending(SalePaymentSubmission $submission): void
    {
        if (! $submission->isPending()) {
            throw new PaymentReviewException('This payment has already been '.strtolower($submission->statusLabel()).'.');
        }
    }

    /** Write the ledger row and re-derive the sale's status. Caller holds the transaction. */
    private function confirm(SalePaymentSubmission $submission, User $reviewer, ?int $cashAccountId, ?string $note): void
    {
        $sale = Sale::query()->whereKey($submission->sale_id)->lockForUpdate()->firstOrFail();
        $confirmed = (float) $sale->payments()->sum('amount');

        if ($confirmed + (float) $submission->amount > (float) $sale->total_revenue + 0.009) {
            throw new PaymentReviewException(sprintf(
                'Approving this would bring confirmed payments to %s RWF, above the invoice total of %s RWF. Reject it, or correct the sale first.',
                number_format($confirmed + (float) $submission->amount, 0),
                number_format((float) $sale->total_revenue, 0),
            ));
        }

        $payment = $sale->payments()->create([
            'amount' => $submission->amount,
            'method' => $submission->method,
            'paid_at' => $submission->paid_at,
            'reference' => $submission->reference,
            'notes' => $submission->notes,
            'cash_account_id' => $cashAccountId,
            // The sales person who collected it keeps the credit in team performance.
            'recorded_by' => $submission->submitted_by ?? $reviewer->id,
        ]);

        $submission->update([
            'status' => SalePaymentSubmission::STATUS_APPROVED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
            'cash_account_id' => $cashAccountId,
            'sale_payment_id' => $payment->id,
        ]);

        $sale->recalculatePaymentState();

        DB::afterCommit(fn () => $this->notifyApproved($submission));
    }

    private function storeProof(SalePaymentSubmission $submission, UploadedFile $file, ?User $by): string
    {
        // store() names the file itself; the client's file name is kept for display only.
        $path = $file->store('payment-proofs/'.now()->format('Y/m'), self::PROOF_DISK);

        if ($path === false) {
            throw new PaymentReviewException('The proof file could not be saved. Please try again.');
        }

        $submission->proofs()->create([
            'disk' => self::PROOF_DISK,
            'path' => $path,
            // Fits the 191-character string columns (Schema::defaultStringLength).
            'original_name' => Str::limit($file->getClientOriginalName() ?: basename($path), 180, ''),
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $by?->id,
        ]);

        return $path;
    }

    private function notifyApprovers(SalePaymentSubmission $submission): void
    {
        $approvers = User::query()
            ->paymentApprovers()
            ->when($submission->submitted_by, fn ($q, $id) => $q->whereKeyNot($id))
            ->get();

        if ($approvers->isEmpty()) {
            Log::warning('[mail] payment_submitted: nobody is allowed to approve payments', [
                'submission_id' => $submission->id,
            ]);
        }

        $this->deliver($approvers, new PaymentSubmittedNotification($submission), 'payment_submitted', $submission);
    }

    private function notifyApproved(SalePaymentSubmission $submission): void
    {
        $sale = $submission->sale()->first();
        $staff = User::query()
            ->where('is_active', true)
            ->whereIn('id', array_filter([$submission->submitted_by, $sale?->created_by]))
            ->when($submission->reviewed_by, fn ($q, $id) => $q->whereKeyNot($id))
            ->get();

        $this->deliver($staff, new PaymentApprovedNotification($submission), 'payment_approved', $submission);

        // Customers hear about it only for shop orders: the payment is what moves the order on.
        if ($sale && $sale->sales_channel === 'Online Store') {
            $this->mailOnlineCustomer($sale, $submission);
        }
    }

    private function notifyRejected(SalePaymentSubmission $submission): void
    {
        $submitter = User::query()
            ->where('is_active', true)
            ->whereKey($submission->submitted_by)
            ->when($submission->reviewed_by, fn ($q, $id) => $q->whereKeyNot($id))
            ->get();

        $this->deliver($submitter, new PaymentRejectedNotification($submission), 'payment_rejected', $submission);
    }

    private function mailOnlineCustomer(Sale $sale, SalePaymentSubmission $submission): void
    {
        $email = collect([$sale->customer_email, $sale->barcode])
            ->first(fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL));

        if (! $email) {
            Log::info('[mail] order_payment_received: no customer email on order', ['order' => $sale->sales_id]);

            return;
        }

        $mail = new OnlineOrderPaymentReceivedMail($sale->fresh(), $submission);

        // Sent after the response, so a slow mail server never holds up the approval.
        defer(function () use ($mail, $email, $sale): void {
            try {
                Mail::to($email, $sale->customer_name)->send($mail);
                Log::info('[mail] order_payment_received: sent', ['order' => $sale->sales_id, 'to' => $email]);
            } catch (Throwable $e) {
                Log::error('[mail] order_payment_received: FAILED', [
                    'order' => $sale->sales_id,
                    'to' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Notify each person on their own, so one bad address or an SMTP outage can't stop the
     * rest — or undo the approval that triggered it. The in-app copy is stored now; the
     * email goes out after the response, so a slow mail server never holds up the page.
     *
     * @param  Collection<int, User>  $users
     */
    private function deliver(Collection $users, Notification $notification, string $event, SalePaymentSubmission $submission): void
    {
        foreach ($users as $user) {
            $channels = $notification->via($user);
            $send = function (array $only) use ($user, $notification, $event, $submission): void {
                try {
                    NotificationSender::sendNow($user, $notification, $only);
                    Log::info("[notify] {$event}: sent via ".implode(', ', $only), ['submission_id' => $submission->id, 'to' => $user->email]);
                } catch (Throwable $e) {
                    Log::error("[notify] {$event}: FAILED via ".implode(', ', $only), [
                        'submission_id' => $submission->id,
                        'to' => $user->email,
                        'error' => $e->getMessage(),
                    ]);
                }
            };

            if ($now = array_values(array_diff($channels, ['mail']))) {
                $send($now);
            }
            if (in_array('mail', $channels, true)) {
                defer(fn () => $send(['mail']));
            }
        }
    }
}
