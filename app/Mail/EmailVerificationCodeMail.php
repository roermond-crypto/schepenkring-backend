<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $ttlMinutes,
        public string $verifyUrl
    ) {
    }

    public function build(): self
    {
        return $this->subject('Verify your email')
            ->view('emails.verify-email-code')
            ->with([
                'code' => $this->code,
                'ttlMinutes' => $this->ttlMinutes,
                'verifyUrl' => $this->verifyUrl,
            ]);
    }
}
