<?php

namespace App\Mail;

use App\Models\Location;
use App\Models\LocationDeleteRequest;
use App\Models\User;
use App\Support\AuthEmailSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LocationDeleteRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Location              $location,
        public LocationDeleteRequest $deleteRequest,
        public User                  $requester,
        public string                $approveUrl,
        public string                $cancelUrl,
    ) {}

    public function build(): self
    {
        $emailSupport = app(AuthEmailSupport::class);
        $impact       = $this->deleteRequest->affected_records ?? [];

        return $this->subject("Verzoek tot verwijdering locatie: {$this->location->name}")
            ->view('emails.location_delete_request')
            ->with([
                'location'    => $this->location,
                'request'     => $this->deleteRequest,
                'requester'   => $this->requester,
                'approveUrl'  => $this->approveUrl,
                'cancelUrl'   => $this->cancelUrl,
                'impact'      => $impact,
                'expiresAt'   => $this->deleteRequest->expires_at->format('d-m-Y H:i'),
                'logoUrl'     => $emailSupport->logoUrl(),
                'appName'     => config('app.name', 'Schepenkring'),
            ]);
    }
}
