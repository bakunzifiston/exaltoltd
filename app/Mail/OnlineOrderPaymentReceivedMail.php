<?php

namespace App\Mail;

use App\Models\Sale;
use App\Models\SalePaymentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To a website customer, once their payment is confirmed: the order is now being processed.
 * Only shop orders get this — walk-in and phone sales don't email the customer.
 */
class OnlineOrderPaymentReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Sale $sale,
        public readonly SalePaymentSubmission $submission,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Received – ' . $this->sale->sales_id . ' | Exalto Green Ltd',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-payment-received');
    }
}
