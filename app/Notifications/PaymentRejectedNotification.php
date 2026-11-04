<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/** To the sales person: the payment they reported was not confirmed, and why. */
class PaymentRejectedNotification extends PaymentNotification
{
    protected function title(): string
    {
        return 'Payment rejected';
    }

    protected function message(): string
    {
        return sprintf(
            '%s for %s was rejected: %s',
            $this->amount(),
            $this->saleRef(),
            $this->submission->review_note,
        );
    }

    protected function url(): string
    {
        return route('admin.sales.show', $this->submission->sale_id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Payment rejected] '.$this->amount().' – '.$this->saleRef())
            ->greeting('Hello '.$notifiable->name.',')
            ->line('The payment of **'.$this->amount().'** you reported for **'.$this->saleRef().'** was not approved by '.($this->submission->reviewer?->name ?? 'an approver').'.')
            ->line('**Reason:** '.$this->submission->review_note)
            ->action('Open sale', $this->url())
            ->line('You can submit the payment again from the sale page with corrected details or proof.');
    }
}
