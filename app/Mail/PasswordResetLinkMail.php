<?php

namespace App\Mail;

use App\Models\User;
use App\Support\AuthEmailSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl,
        public ?string $locale = null,
        public int $ttlMinutes = 60
    ) {
    }

    public function build(): self
    {
        $emailSupport = app(AuthEmailSupport::class);
        $locale = $emailSupport->resolveLocale($this->locale, null);
        $copy = $this->copy($locale);

        return $this->subject($copy['subject'])
            ->view('emails.password_reset_link')
            ->with([
                'user' => $this->user,
                'locale' => $locale,
                'copy' => $copy,
                'subjectLine' => $copy['subject'],
                'resetUrl' => $this->resetUrl,
                'logoUrl' => $emailSupport->logoUrl(),
                'appName' => config('app.name', 'Schepenkring'),
                'ttlMinutes' => $this->ttlMinutes,
            ]);
    }

    private function copy(string $locale): array
    {
        return match ($locale) {
            'nl' => [
                'subject' => 'Stel je Schepenkring wachtwoord opnieuw in',
                'preheader' => 'Open de beveiligde link om een nieuw wachtwoord te kiezen.',
                'badge' => 'Wachtwoord reset',
                'headline' => 'Kies een nieuw wachtwoord',
                'intro' => 'We hebben een verzoek ontvangen om het wachtwoord van je Schepenkring-account opnieuw in te stellen.',
                'expires' => "Deze link verloopt over {$this->ttlMinutes} minuten voor extra beveiliging.",
                'action_label' => 'Wachtwoord opnieuw instellen',
                'action_support' => 'Met deze knop ga je direct naar de beveiligde resetpagina.',
                'fallback_label' => 'Werkt de knop niet? Open dan deze link:',
                'outro' => 'Heb je dit niet aangevraagd? Dan hoef je niets te doen en kun je deze e-mail negeren.',
                'footer' => 'Deze beveiligingsmail is automatisch verzonden door Schepenkring.',
            ],
            'de' => [
                'subject' => 'Setzen Sie Ihr Schepenkring Passwort zuruck',
                'preheader' => 'Offnen Sie den sicheren Link, um ein neues Passwort festzulegen.',
                'badge' => 'Passwort zurucksetzen',
                'headline' => 'Wahlen Sie ein neues Passwort',
                'intro' => 'Wir haben eine Anfrage erhalten, das Passwort Ihres Schepenkring-Kontos zuruckzusetzen.',
                'expires' => "Dieser Link lauft aus Sicherheitsgrunden in {$this->ttlMinutes} Minuten ab.",
                'action_label' => 'Passwort zurucksetzen',
                'action_support' => 'Mit dieser Schaltflache gelangen Sie direkt zur sicheren Reset-Seite.',
                'fallback_label' => 'Falls die Schaltflache nicht funktioniert, offnen Sie diesen Link:',
                'outro' => 'Wenn Sie dies nicht angefordert haben, muessen Sie nichts weiter tun und konnen diese E-Mail ignorieren.',
                'footer' => 'Diese Sicherheits-E-Mail wurde automatisch von Schepenkring gesendet.',
            ],
            'fr' => [
                'subject' => 'Reinitialisez votre mot de passe Schepenkring',
                'preheader' => 'Ouvrez le lien securise pour choisir un nouveau mot de passe.',
                'badge' => 'Reinitialisation du mot de passe',
                'headline' => 'Choisissez un nouveau mot de passe',
                'intro' => 'Nous avons recu une demande de reinitialisation du mot de passe de votre compte Schepenkring.',
                'expires' => "Ce lien expire dans {$this->ttlMinutes} minutes pour plus de securite.",
                'action_label' => 'Reinitialiser le mot de passe',
                'action_support' => 'Ce bouton vous redirige directement vers la page securisee de reinitialisation.',
                'fallback_label' => 'Si le bouton ne fonctionne pas, ouvrez ce lien :',
                'outro' => "Si vous n'etes pas a l'origine de cette demande, aucune autre action n'est necessaire.",
                'footer' => 'Cet e-mail de securite a ete envoye automatiquement par Schepenkring.',
            ],
            default => [
                'subject' => 'Reset your Schepenkring password',
                'preheader' => 'Open the secure link to choose a new password.',
                'badge' => 'Password reset',
                'headline' => 'Choose a new password',
                'intro' => 'We received a request to reset the password for your Schepenkring account.',
                'expires' => "For security, this link expires in {$this->ttlMinutes} minutes.",
                'action_label' => 'Reset password',
                'action_support' => 'This button takes you straight to the secure reset page.',
                'fallback_label' => 'If the button does not work, open this link:',
                'outro' => 'If you did not request this, no further action is required and you can ignore this email.',
                'footer' => 'This security email was sent automatically by Schepenkring.',
            ],
        };
    }
}
