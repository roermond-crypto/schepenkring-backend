<?php

namespace App\Mail;

use App\Models\BoatIntake;
use App\Models\Location;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BoatIntakeLocationNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $sellerName;
    public string $boatLabel;
    public string $adminUrl;

    public function __construct(
        public BoatIntake $intake,
        public Location $location,
    ) {
        $this->sellerName = $intake->sellerFullName();
        $this->boatLabel  = trim("{$intake->boat_brand} {$intake->boat_model}") ?: 'Boot';
        $base = rtrim(config('app.frontend_url', 'https://schepenkring.nl'), '/');
        $this->adminUrl = "{$base}/nl/dashboard/admin/yachts";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nieuwe boot aanmelding — {$this->boatLabel} van {$this->sellerName}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.boat_intake_location_notification');
    }
}
