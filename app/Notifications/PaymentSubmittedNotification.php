<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/** To approvers: a payment was reported and needs checking against the account. */
class PaymentSubmittedNotification extends PaymentNotification
{
    protected function title(): string
    {
        return 'Payment awaiting approval';
    }

    protected function message(): string
    {
        return sprintf(
            '%s reported %s for %s (%s).',
            $this->submission->submitter?->name ?? 'Someone',
            $this->amount(),
            $this->saleRef(),
            $this->submission->sale?->customer_name ?? 'customer',
        );
    }

    protected function url(): string
    {
        return route('admin.payment-approvals.show', $this->submission);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->submission;

        return (new MailMessage)
            ->subject('[Payment to approve] '.$this->amount().' – '.$this->saleRef())
            ->greeting('Hello '.$notifiable->name.',')
            ->line(($s->submitter?->name ?? 'A team member').' reported a payment that needs your approval.')
            ->line('**Sale:** '.$this->saleRef().' — '.($s->sale?->customer_name ?? '—'))
            ->line('**Amount:** '.$this->amount())
            ->line('**Method:** '.$s->method.($s->reference ? ' · Ref '.$s->reference : ''))
            ->line('**Paid on:** '.$s->paid_at?->format('d M Y'))
            ->line('**Proof files:** '.$s->proofs()->count())
            ->action('Review payment', $this->url())
            ->line('Check the proof against the receiving account before approving. The sale is not marked paid until you do.');
    }
}
