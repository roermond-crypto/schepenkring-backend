<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic Mailable that sends a pre-rendered HTML email from the template builder.
 * Use EmailTemplateResolver::resolveAndRender() to produce the $rendered payload,
 * then pass it to this Mailable.
 */
class TemplatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $htmlContent,
        private readonly string $emailSubject,
        private readonly string $preheader = '',
        private readonly ?string $fromName  = null,
        private readonly ?string $fromEmail = null,
        private readonly ?string $replyTo   = null,
    ) {}

    public function envelope(): Envelope
    {
        $envelope = new Envelope(subject: $this->emailSubject);

        if ($this->fromEmail || $this->fromName) {
            $envelope = new Envelope(
                subject: $this->emailSubject,
                from: new \Illuminate\Mail\Mailables\Address(
                    $this->fromEmail ?? config('mail.from.address'),
                    $this->fromName  ?? config('mail.from.name'),
                ),
            );
        }

        if ($this->replyTo) {
            // replyTo is set after construction; no fluent API in Laravel 10+
            $this->replyTo([$this->replyTo]);
        }

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->htmlContent);
    }

    /**
     * Build the Mailable from a resolved template payload.
     *
     * @param  array{html: string, subject: string, preheader: string, from_name: ?string, from_email: ?string, reply_to: ?string}  $rendered
     */
    public static function fromResolved(array $rendered): self
    {
        return new self(
            htmlContent: $rendered['html'],
            emailSubject: $rendered['subject'],
            preheader:   $rendered['preheader'] ?? '',
            fromName:    $rendered['from_name']  ?? null,
            fromEmail:   $rendered['from_email'] ?? null,
            replyTo:     $rendered['reply_to']   ?? null,
        );
    }
}
