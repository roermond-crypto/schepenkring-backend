<?php

namespace App\Mail;

use App\Models\BoatIntake;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BoatIntakeConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $sellerName;
    public string $boatName;
    public string $locationName;
    public int $scoreTotal;
    public array $breakdown;
    public array $missingItems;
    public int $photoCount;
    public int $photoTarget;
    public int $descriptionLength;
    public int $descriptionTarget;
    public bool $hasMissingItems;

    public function __construct(
        public BoatIntake $intake,
        public array $score,
        public string $resumeUrl,
    ) {
        $this->sellerName        = $intake->sellerFullName();
        $this->boatName          = trim("{$intake->boat_brand} {$intake->boat_model}") ?: 'Uw boot';
        $this->locationName      = $intake->location?->name ?? 'Schepenkring';
        $this->scoreTotal        = (int) $score['total'];
        $this->breakdown         = $score['breakdown'];
        $this->missingItems      = $score['missing'] ?? [];
        $this->photoCount        = $score['photo_count'];
        $this->photoTarget       = $score['photo_target'];
        $this->descriptionLength = $score['description_length'];
        $this->descriptionTarget = $score['description_target'];
        $this->hasMissingItems   = ! empty($this->missingItems);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Uw boot aanmelding ontvangen — {$this->boatName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.boat_intake_confirmation');
    }
}
