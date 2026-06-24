@php
    $impactLines = [];
    $labels = [
        'boats'                      => 'Boten',
        'yachts'                     => 'Jachten',
        'clients'                    => 'Klanten',
        'staff_assignments'          => 'Medewerkers',
        'leads'                      => 'Leads',
        'conversations'              => 'Chats',
        'tasks'                      => 'Taken',
        'boards'                     => 'Borden',
        'sign_requests'              => 'Ondertekeningsverzoeken',
        'harbor_channels'            => 'Kanalen',
        'call_sessions'              => 'Belsessies',
        'columns'                    => 'Kolommen',
        'task_automations'           => 'Taakautomatiseringen',
        'task_automation_templates'  => 'Automatiseringstemplates',
    ];
    foreach (($impact ?? []) as $key => $count) {
        if ($count > 0) {
            $label = $labels[$key] ?? $key;
            $impactLines[] = "{$label}: {$count}";
        }
    }
    $hasImpact = count($impactLines) > 0;
@endphp

@include('emails.partials.auth-shell', [
    'emailLocale'            => 'nl',
    'subjectLine'            => "Verzoek tot verwijdering locatie: {$location->name}",
    'preheader'              => "Actie vereist: {$requester->name} wil locatie {$location->name} archiveren.",
    'badge'                  => 'Locatie verwijderingsverzoek',
    'headline'               => "Verzoek tot archivering: {$location->name}",
    'greeting'               => 'Hallo,',
    'introLines'             => [
        "{$requester->name} heeft een verzoek ingediend om locatie <strong>{$location->name}</strong> ({$location->code}) te archiveren.",
        "Reden: <em>{$request->reason}</em>",
    ],
    'secondaryLines'         => $hasImpact
        ? array_merge(
            ["<strong>Gekoppelde gegevens bij deze locatie:</strong>"],
            $impactLines,
            $request->move_to_location_id ? ["Records worden verplaatst naar locatie #{$request->move_to_location_id}."] : []
          )
        : ["Deze locatie bevat geen gekoppelde gegevens."],
    'primaryActionLabel'     => 'Verwijdering goedkeuren',
    'primaryActionUrl'       => $approveUrl,
    'primaryActionSupportText' => 'Na goedkeuring wordt de locatie gearchiveerd. Records kunnen eventueel worden verplaatst.',
    'fallbackLabel'          => 'Of gebruik deze link om te annuleren:',
    'fallbackUrl'            => $cancelUrl,
    'outro'                  => "Deze link verloopt op {$expiresAt}. Als jij dit niet wilt goedkeuren, klik dan op de annuleerlink.",
    'footer'                 => 'Dit is een beveiligde actie-e-mail van Schepenkring Platform.',
    'logoUrl'                => $logoUrl ?? null,
    'appName'                => $appName ?? 'Schepenkring',
])
