<?php

namespace App\Mail;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class OrderNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Sale $sale,
        public readonly Collection $rows,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[New Order] ' . $this->sale->sales_id . ' – ' . $this->sale->customer_name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-notification');
    }
}
