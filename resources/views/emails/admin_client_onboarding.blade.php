@php
    $displayName = trim((string) ($user->first_name ?? $user->name ?? ''));
    $greeting = $displayName !== '' ? $displayName . ',' : null;
@endphp

@include('emails.partials.auth-shell', [
    'emailLocale' => $locale,
    'subjectLine' => $subjectLine,
    'preheader' => $copy['preheader'],
    'badge' => $copy['badge'],
    'headline' => $copy['headline'],
    'greeting' => $greeting,
    'introLines' => [$copy['intro'], $copy['role']],
    'secondaryLines' => [$copy['password_label'] . ': ' . $temporaryPassword],
    'primaryActionLabel' => $copy['action_label'],
    'primaryActionUrl' => $onboardingUrl,
    'primaryActionSupportText' => $copy['action_support'],
    'fallbackLabel' => $copy['fallback_label'],
    'fallbackUrl' => $onboardingUrl,
    'outro' => $copy['outro'],
    'footer' => $copy['footer'],
    'logoUrl' => $logoUrl,
    'appName' => $appName,
])
