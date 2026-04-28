<?php

namespace App\Mail;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IntegrationAccessDetailsMail extends Mailable
{
    use Queueable, SerializesModels;

    public Integration $integration;
    public ?User $requestedBy;
    public ?string $passwordValue;
    public ?string $apiKeyValue;

    public function __construct(Integration $integration, ?User $requestedBy = null)
    {
        $this->integration = $integration;
        $this->requestedBy = $requestedBy;
        $this->passwordValue = $integration->password();
        $this->apiKeyValue = $integration->apiKey();
    }

    public function build(): self
    {
        $subjectLabel = $this->integration->label
            ? "{$this->integration->integration_type} ({$this->integration->label})"
            : $this->integration->integration_type;

        return $this
            ->subject("Integration access details: {$subjectLabel}")
            ->view('emails.integration_access_details');
    }
}
