<?php

namespace App\Mail;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BuyerCounterOfferMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $boatName;
    public string $formattedOriginal;
    public string $formattedCounter;
    public string $buyerName;
    public string $counterMessage;
    public string $locationName;

    public function __construct(
        public Offer $offer,
        public float $counterAmount,
        public ?string $sellerCounterMessage,
    ) {
        $boat = $offer->yacht;
        $this->boatName          = trim(($boat?->manufacturer ?? '') . ' ' . ($boat?->model ?? '')) ?: ($boat?->boat_name ?? 'Boot');
        $this->formattedOriginal = '€ ' . number_format((float) $offer->amount, 0, ',', '.');
        $this->formattedCounter  = '€ ' . number_format($counterAmount, 0, ',', '.');
        $this->buyerName         = $offer->buyer_name ?? 'Geachte klant';
        $this->counterMessage    = $sellerCounterMessage ?? '';
        $this->locationName      = $offer->location?->name ?? config('app.name', 'Schepenkring');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tegenbod ontvangen voor {$this->boatName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.buyer_counter_offer',
        );
    }
}
