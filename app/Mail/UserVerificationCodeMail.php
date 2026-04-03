<?php

namespace App\Mail;

use App\Models\User;
use App\Support\AuthEmailSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
        public int $ttlMinutes,
        public ?string $preferredLocale = null
    ) {
    }

    public function build(): self
    {
        $emailSupport = app(AuthEmailSupport::class);
        $locale = $emailSupport->resolveLocale($this->preferredLocale, null);
        $copy = $this->copy($locale);

        return $this->subject($copy['subject'])
            ->view('emails.user_verification_code')
            ->with([
                'user' => $this->user,
                'code' => $this->code,
                'ttlMinutes' => $this->ttlMinutes,
                'locale' => $locale,
                'copy' => $copy,
                'subjectLine' => $copy['subject'],
                'verifyUrl' => $emailSupport->localizedFrontendPath(
                    'auth/verify-email?email=' . urlencode($this->user->email),
                    $locale
                ),
                'logoUrl' => $emailSupport->logoUrl(),
                'appName' => config('app.name', 'Schepenkring'),
            ]);
    }

    private function copy(string $locale): array
    {
        return match ($locale) {
            'nl' => [
                'subject' => 'Je Schepenkring verificatiecode',
                'preheader' => 'Gebruik deze code om je e-mailadres veilig te bevestigen.',
                'badge' => 'Accountbeveiliging',
                'headline' => 'Bevestig je e-mailadres',
                'intro' => 'Gebruik onderstaande code om je Schepenkring-account te verifiëren.',
                'code_label' => 'Je verificatiecode',
                'expires' => "Deze code verloopt over {$this->ttlMinutes} minuten.",
                'action_label' => 'Open verificatiepagina',
                'action_support' => 'Je kunt de code handmatig invoeren op de verificatiepagina.',
                'fallback_label' => 'Werkt de knop niet? Open dan deze link:',
                'outro' => 'Heb je dit niet aangevraagd? Dan kun je deze e-mail veilig negeren.',
                'footer' => 'Deze beveiligingsmail is automatisch verzonden door Schepenkring.',
            ],
            'de' => [
                'subject' => 'Ihr Schepenkring Verifizierungscode',
                'preheader' => 'Verwenden Sie diesen Code, um Ihre E-Mail-Adresse sicher zu bestätigen.',
                'badge' => 'Kontosicherheit',
                'headline' => 'Bestätigen Sie Ihre E-Mail-Adresse',
                'intro' => 'Verwenden Sie den folgenden Code, um Ihr Schepenkring-Konto zu verifizieren.',
                'code_label' => 'Ihr Verifizierungscode',
                'expires' => "Dieser Code läuft in {$this->ttlMinutes} Minuten ab.",
                'action_label' => 'Verifizierungsseite öffnen',
                'action_support' => 'Sie können den Code auf der Verifizierungsseite manuell eingeben.',
                'fallback_label' => 'Falls die Schaltfläche nicht funktioniert, öffnen Sie diesen Link:',
                'outro' => 'Wenn Sie dies nicht angefordert haben, können Sie diese E-Mail ignorieren.',
                'footer' => 'Diese Sicherheits-E-Mail wurde automatisch von Schepenkring gesendet.',
            ],
            'fr' => [
                'subject' => 'Votre code de verification Schepenkring',
                'preheader' => 'Utilisez ce code pour confirmer votre adresse e-mail en toute sécurité.',
                'badge' => 'Securite du compte',
                'headline' => 'Confirmez votre adresse e-mail',
                'intro' => 'Utilisez le code ci-dessous pour verifier votre compte Schepenkring.',
                'code_label' => 'Votre code de verification',
                'expires' => "Ce code expire dans {$this->ttlMinutes} minutes.",
                'action_label' => 'Ouvrir la page de verification',
                'action_support' => 'Vous pouvez saisir le code manuellement sur la page de verification.',
                'fallback_label' => 'Si le bouton ne fonctionne pas, ouvrez ce lien :',
                'outro' => "Si vous n'etes pas a l'origine de cette demande, vous pouvez ignorer cet e-mail.",
                'footer' => 'Cet e-mail de securite a ete envoye automatiquement par Schepenkring.',
            ],
            default => [
                'subject' => 'Your Schepenkring verification code',
                'preheader' => 'Use this code to securely confirm your email address.',
                'badge' => 'Account security',
                'headline' => 'Confirm your email address',
                'intro' => 'Use the code below to verify your Schepenkring account.',
                'code_label' => 'Your verification code',
                'expires' => "This code expires in {$this->ttlMinutes} minutes.",
                'action_label' => 'Open verification page',
                'action_support' => 'You can enter the code manually on the verification page.',
                'fallback_label' => 'If the button does not work, open this link:',
                'outro' => 'If you did not request this, you can safely ignore this email.',
                'footer' => 'This security email was sent automatically by Schepenkring.',
            ],
        };
    }
}
