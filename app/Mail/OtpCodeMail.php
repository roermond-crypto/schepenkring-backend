<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $ttlMinutes,
        public string $purpose
    ) {
    }

    public function build(): self
    {
        return $this->subject('Your security verification code')
            ->view('emails.otp-code')
            ->with([
                'code' => $this->code,
                'ttlMinutes' => $this->ttlMinutes,
                'purpose' => $this->purpose,
            ]);
    }
}
