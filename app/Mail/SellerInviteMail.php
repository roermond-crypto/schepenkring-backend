<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Yacht;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $sellerName;
    public string $boatName;
    public string $locationName;
    public string $loginUrl;
    public string $resetUrl;

    public function __construct(
        public User $seller,
        public Yacht $yacht,
    ) {
        $this->sellerName   = $seller->name ?? $seller->email;
        $this->boatName     = trim(($yacht->manufacturer ?? '') . ' ' . ($yacht->model ?? '')) ?: ($yacht->boat_name ?? 'Boot');
        $this->locationName = $yacht->location?->name ?? config('app.name', 'Schepenkring');

        $frontendBase = rtrim(config('app.frontend_url', 'https://www.schepen-kring.nl'), '/');
        $this->loginUrl = "{$frontendBase}/nl/login";
        $this->resetUrl = "{$frontendBase}/nl/wachtwoord-vergeten?email=" . urlencode($seller->email);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Uitnodiging: beheer uw boot op Schepenkring",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller_invite',
        );
    }
}
