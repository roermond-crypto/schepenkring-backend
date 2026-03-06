<?php

namespace App\Mail;

use App\Models\Bidder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BidVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Bidder $bidder, public string $token)
    {
    }

    public function build(): self
    {
        $baseUrl = rtrim((string) config('bidding.verify_url', ''), '/');
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $verifyUrl = $baseUrl . $separator . 'token=' . urlencode($this->token);

        return $this->subject('Verify your email to place a bid')
            ->view('emails.bid_verification')
            ->with([
                'bidder' => $this->bidder,
                'verifyUrl' => $verifyUrl,
            ]);
    }
}
