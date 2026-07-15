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
    public array $nextSteps;

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
        $this->nextSteps         = [
            [
                'num'   => '1',
                'title' => 'Controleer uw e-mail',
                'body'  => 'Gebruik de link in dit bericht om extra fotos of documenten toe te voegen.',
            ],
            [
                'num'   => '2',
                'title' => 'Maak een account aan',
                'body'  => 'Uw profiel wordt automatisch ingevuld met de gegevens die u heeft ingevoerd.',
            ],
            [
                'num'   => '3',
                'title' => 'Onze makelaar neemt contact op',
                'body'  => 'Wij beoordelen uw aanmelding en nemen binnen een werkdag contact op.',
            ],
        ];
    }

    public function envelope(): Envelope
    {
        // No explicit from/replyTo — let it fall through to the mailer's
        // configured default (MAIL_FROM_ADDRESS/MAIL_FROM_NAME), same as
        // every other working mail class in this app (e.g.
        // PasswordResetLinkMail). This class used to build its own Address
        // here; removed as a precaution in case a strict SMTP provider was
        // rejecting the explicit override — genuine confirmation of the
        // real cause still needs the exception logged by
        // sendSellerConfirmation()'s catch block in storage/logs/laravel.log.
        return new Envelope(
            subject: "Boot aanmelding ontvangen: {$this->boatName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.boat_intake_confirmation');
    }
}
