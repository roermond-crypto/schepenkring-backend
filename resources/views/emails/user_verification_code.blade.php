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
    'introLines' => [$copy['intro']],
    'codeLabel' => $copy['code_label'],
    'code' => $code,
    'secondaryLines' => [$copy['expires']],
    'primaryActionLabel' => $copy['action_label'],
    'primaryActionUrl' => $verifyUrl,
    'primaryActionSupportText' => $copy['action_support'],
    'fallbackLabel' => $copy['fallback_label'],
    'fallbackUrl' => $verifyUrl,
    'outro' => $copy['outro'],
    'footer' => $copy['footer'],
    'logoUrl' => $logoUrl,
    'appName' => $appName,
])
