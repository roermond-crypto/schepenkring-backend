<?php

namespace App\Mail;

use App\Models\User;
use App\Support\AuthEmailSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class AdminClientOnboardingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
        public string $clientRole,
        public ?string $preferredLocale = null
    ) {
    }

    public function build(): self
    {
        $emailSupport = app(AuthEmailSupport::class);
        $locale = $emailSupport->resolveLocale($this->preferredLocale, null);
        $copy = $this->copy($locale);
        $onboardingUrl = $this->onboardingUrl($locale);

        return $this->subject($copy['subject'])
            ->view('emails.admin_client_onboarding')
            ->with([
                'user' => $this->user,
                'locale' => $locale,
                'copy' => $copy,
                'subjectLine' => $copy['subject'],
                'onboardingUrl' => $onboardingUrl,
                'temporaryPassword' => $this->temporaryPassword,
                'logoUrl' => $emailSupport->logoUrl(),
                'appName' => config('app.name', 'Schepenkring'),
            ]);
    }

    private function onboardingUrl(string $locale): string
    {
        $baseUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $role = $this->clientRole === 'seller' ? 'seller' : 'buyer';
        $next = "/{$locale}/dashboard/{$role}/onboarding";

        return $baseUrl . "/{$locale}/auth?mode=login&next=" . rawurlencode($next);
    }

    private function copy(string $locale): array
    {
        $roleLabel = Str::of($this->clientRole)->title();

        return match ($locale) {
            'nl' => [
                'subject' => 'Voltooi je Schepenkring onboarding',
                'preheader' => 'Je account is aangemaakt. Log in om je onboarding af te ronden.',
                'badge' => 'Onboarding',
                'headline' => 'Je Schepenkring account staat klaar',
                'intro' => 'We hebben een Schepenkring account voor je aangemaakt. Log in om je onboarding veilig af te ronden.',
                'role' => 'Accounttype: ' . $roleLabel,
                'password_label' => 'Tijdelijk wachtwoord',
                'action_label' => 'Onboarding starten',
                'action_support' => 'Gebruik je e-mailadres en het tijdelijke wachtwoord om in te loggen.',
                'fallback_label' => 'Werkt de knop niet? Open dan deze link:',
                'outro' => 'Wijzig je wachtwoord na het inloggen wanneer daarom wordt gevraagd.',
                'footer' => 'Deze e-mail is automatisch verzonden door Schepenkring.',
            ],
            default => [
                'subject' => 'Complete your Schepenkring onboarding',
                'preheader' => 'Your account has been created. Sign in to complete onboarding.',
                'badge' => 'Onboarding',
                'headline' => 'Your Schepenkring account is ready',
                'intro' => 'A Schepenkring account has been created for you. Sign in to complete your onboarding securely.',
                'role' => 'Account type: ' . $roleLabel,
                'password_label' => 'Temporary password',
                'action_label' => 'Start onboarding',
                'action_support' => 'Use your email address and the temporary password to sign in.',
                'fallback_label' => 'If the button does not work, open this link:',
                'outro' => 'Please change your password after signing in when prompted.',
                'footer' => 'This email was sent automatically by Schepenkring.',
            ],
        };
    }
}
