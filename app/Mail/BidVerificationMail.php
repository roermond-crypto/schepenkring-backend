<?php

namespace App\Mail;

use App\Models\Bidder;
use App\Support\AuthEmailSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BidVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Bidder $bidder,
        public string $token,
        public ?string $preferredLocale = null
    )
    {
    }

    public function build(): self
    {
        $emailSupport = app(AuthEmailSupport::class);
        $locale = $emailSupport->resolveLocale($this->preferredLocale, null);
        $copy = $this->copy($locale);
        $baseUrl = rtrim((string) config('bidding.verify_url', ''), '/');
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $verifyUrl = $baseUrl . $separator . 'token=' . urlencode($this->token);

        return $this->subject($copy['subject'])
            ->view('emails.bid_verification')
            ->with([
                'bidder' => $this->bidder,
                'verifyUrl' => $verifyUrl,
                'locale' => $locale,
                'copy' => $copy,
                'subjectLine' => $copy['subject'],
                'logoUrl' => $emailSupport->logoUrl(),
                'appName' => config('app.name', 'Schepenkring'),
            ]);
    }

    private function copy(string $locale): array
    {
        return match ($locale) {
            'nl' => [
                'subject' => 'Bevestig je e-mail om een bod te plaatsen',
                'preheader' => 'Verifieer je e-mailadres om veilig verder te gaan met bieden.',
                'badge' => 'Bodbevestiging',
                'headline' => 'Bevestig je e-mailadres',
                'intro' => 'Bevestig eerst je e-mailadres voordat je een bod plaatst op Schepenkring.',
                'action_label' => 'E-mail bevestigen',
                'action_support' => 'Na bevestiging kun je je bod afronden.',
                'fallback_label' => 'Werkt de knop niet? Open dan deze link:',
                'outro' => 'Heb je dit niet aangevraagd? Dan kun je deze e-mail veilig negeren.',
                'footer' => 'Deze beveiligingsmail is automatisch verzonden door Schepenkring.',
            ],
            'de' => [
                'subject' => 'Bestatigen Sie Ihre E-Mail, um ein Gebot abzugeben',
                'preheader' => 'Verifizieren Sie Ihre E-Mail-Adresse, um sicher weiterzubieten.',
                'badge' => 'Gebotsbestatigung',
                'headline' => 'Bestatigen Sie Ihre E-Mail-Adresse',
                'intro' => 'Bitte bestatigen Sie zuerst Ihre E-Mail-Adresse, bevor Sie ein Gebot auf Schepenkring abgeben.',
                'action_label' => 'E-Mail bestatigen',
                'action_support' => 'Nach der Bestatigung konnen Sie Ihr Gebot abschliessen.',
                'fallback_label' => 'Falls die Schaltflache nicht funktioniert, offnen Sie diesen Link:',
                'outro' => 'Wenn Sie dies nicht angefordert haben, konnen Sie diese E-Mail ignorieren.',
                'footer' => 'Diese Sicherheits-E-Mail wurde automatisch von Schepenkring gesendet.',
            ],
            'fr' => [
                'subject' => 'Confirmez votre e-mail pour placer une enchere',
                'preheader' => 'Verifiez votre adresse e-mail pour poursuivre votre enchere en toute securite.',
                'badge' => "Confirmation d'enchere",
                'headline' => 'Confirmez votre adresse e-mail',
                'intro' => "Veuillez d'abord confirmer votre adresse e-mail avant de placer une enchere sur Schepenkring.",
                'action_label' => "Confirmer l'e-mail",
                'action_support' => "Une fois votre e-mail confirme, vous pourrez finaliser votre enchere.",
                'fallback_label' => 'Si le bouton ne fonctionne pas, ouvrez ce lien :',
                'outro' => "Si vous n'etes pas a l'origine de cette demande, vous pouvez ignorer cet e-mail.",
                'footer' => 'Cet e-mail de securite a ete envoye automatiquement par Schepenkring.',
            ],
            default => [
                'subject' => 'Verify your email to place a bid',
                'preheader' => 'Verify your email address to continue bidding securely.',
                'badge' => 'Bid confirmation',
                'headline' => 'Verify your email address',
                'intro' => 'Please verify your email address before placing your bid on Schepenkring.',
                'action_label' => 'Verify email',
                'action_support' => 'Once your email is verified, you can complete your bid.',
                'fallback_label' => 'If the button does not work, open this link:',
                'outro' => 'If you did not request this, you can safely ignore this email.',
                'footer' => 'This security email was sent automatically by Schepenkring.',
            ],
        };
    }
}
