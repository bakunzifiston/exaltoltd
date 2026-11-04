<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/** To the sales person: the payment they reported has been received and confirmed. */
class PaymentApprovedNotification extends PaymentNotification
{
    protected function title(): string
    {
        return 'Payment received and approved';
    }

    protected function message(): string
    {
        return sprintf(
            '%s for %s was approved by %s.',
            $this->amount(),
            $this->saleRef(),
            $this->submission->reviewer?->name ?? 'an approver',
        );
    }

    protected function url(): string
    {
        return route('admin.sales.show', $this->submission->sale_id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sale = $this->submission->sale?->fresh();

        $mail = (new MailMessage)
            ->subject('[Payment approved] '.$this->amount().' – '.$this->saleRef())
            ->greeting('Hello '.$notifiable->name.',')
            ->line('The payment of **'.$this->amount().'** for **'.$this->saleRef().'** ('.($sale?->customer_name ?? '—').') has been received and approved by '.($this->submission->reviewer?->name ?? 'an approver').'.');

        if ($sale) {
            $mail->line('**Sale status:** '.$sale->payment_status.' · **Balance due:** '.number_format($sale->balance_due, 0).' RWF');
        }

        return $mail->action('View sale', $this->url());
    }
}
