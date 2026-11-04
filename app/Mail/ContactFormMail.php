<?php

namespace App\Mail;

use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ContactSubmission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Contact Form] ' . ($this->submission->subject ?: 'New message from ' . $this->submission->name),
            replyTo: [new \Illuminate\Mail\Mailables\Address($this->submission->email, $this->submission->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-form');
    }
}
