<?php

namespace App\Notifications;

use App\Models\SalePaymentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Shared shape of the payment-approval notifications: an email plus an in-app copy that
 * the admin bell lists. Sent synchronously — the hosting has no queue worker.
 */
abstract class PaymentNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly SalePaymentSubmission $submission)
    {
        $this->submission->loadMissing(['sale', 'submitter', 'reviewer']);
    }

    /** In-app first, so a mail failure still leaves the notification in the bell. */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    abstract protected function title(): string;

    abstract protected function message(): string;

    abstract protected function url(): string;

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'url' => $this->url(),
            'sale_id' => $this->submission->sale_id,
            'submission_id' => $this->submission->id,
            'amount' => (float) $this->submission->amount,
            'status' => $this->submission->status,
        ];
    }

    protected function amount(): string
    {
        return number_format((float) $this->submission->amount, 0).' RWF';
    }

    protected function saleRef(): string
    {
        return $this->submission->sale?->sales_id ?? 'sale #'.$this->submission->sale_id;
    }
}
