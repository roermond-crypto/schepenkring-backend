<?php

namespace App\Mail;

use App\Models\Offer;
use App\Models\OfferToken;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerOfferNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $boatName;
    public string $formattedAmount;
    public string $formattedAsking;
    public string $acceptUrl;
    public string $rejectUrl;
    public string $counterUrl;
    public bool $belowMinimum;
    public string $buyerMessage;

    public function __construct(
        public Offer $offer,
        public User $seller,
        public OfferToken $acceptToken,
        public OfferToken $rejectToken,
    ) {
        $boat = $offer->yacht;
        $this->boatName       = trim(($boat?->manufacturer ?? '') . ' ' . ($boat?->model ?? '')) ?: ($boat?->boat_name ?? 'Boot');
        $this->formattedAmount = '€ ' . number_format((float) $offer->amount, 0, ',', '.');
        $this->formattedAsking = $offer->asking_price ? '€ ' . number_format((float) $offer->asking_price, 0, ',', '.') : '—';
        $this->belowMinimum   = (bool) $offer->below_minimum;
        $this->buyerMessage   = $offer->message ?? '';

        $base = rtrim(config('app.url', 'https://app.schepen-kring.nl'), '/');
        $this->acceptUrl  = "{$base}/api/offers/reply?token={$acceptToken->token}&action=accept";
        $this->rejectUrl  = "{$base}/api/offers/reply?token={$rejectToken->token}&action=reject";

        // Counter token needs to go to a frontend form
        $frontendBase = rtrim(config('app.frontend_url', 'https://schepenkring.nl'), '/');
        $counterToken = $offer->tokens()->where('action', 'counter')->latest()->first();
        $this->counterUrl = $counterToken
            ? "{$frontendBase}/nl/aanbod/tegenbod?token={$counterToken->token}"
            : '';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nieuw bod ontvangen op {$this->boatName} — {$this->formattedAmount}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller_offer_notification',
        );
    }
}
