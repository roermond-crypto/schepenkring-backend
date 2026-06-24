<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use App\Models\Location;
use Illuminate\Support\Facades\Log;

class EmailTemplateAutoCreateService
{
    public const TEMPLATE_TYPES = [
        'offer_received_buyer',
        'offer_received_seller',
        'seller_counter_offer',
        'seller_accept_offer',
        'seller_reject_offer',
        'viewing_request_buyer',
        'viewing_request_location',
        'callback_request',
        'brochure_request',
        'question_received',
        'chat_message_email',
        'seller_invite',
        'contract_signing',
        'booking_confirmation',
        'password_invite',
    ];

    public const READABLE_NAMES = [
        'offer_received_buyer'      => 'Bod ontvangen (koper)',
        'offer_received_seller'     => 'Bod ontvangen (verkoper)',
        'seller_counter_offer'      => 'Tegenbod van verkoper',
        'seller_accept_offer'       => 'Bod geaccepteerd',
        'seller_reject_offer'       => 'Bod afgewezen',
        'viewing_request_buyer'     => 'Bezichtigingsverzoek (koper)',
        'viewing_request_location'  => 'Bezichtigingsverzoek (vestiging)',
        'callback_request'          => 'Terugbelverzoek',
        'brochure_request'          => 'Brochureverzoek',
        'question_received'         => 'Vraag ontvangen',
        'chat_message_email'        => 'Chatbericht per e-mail',
        'seller_invite'             => 'Uitnodiging verkoper',
        'contract_signing'          => 'Contract ondertekenen',
        'booking_confirmation'      => 'Boekingsbevestiging',
        'password_invite'           => 'Wachtwoord uitnodiging',
    ];

    /**
     * Default Dutch subjects per template type.
     */
    private const DEFAULT_SUBJECTS = [
        'offer_received_buyer'     => 'Uw bod op {{boat_name}} is ontvangen',
        'offer_received_seller'    => 'Nieuw bod ontvangen op {{boat_name}}',
        'seller_counter_offer'     => 'Tegenbod op uw bod op {{boat_name}}',
        'seller_accept_offer'      => 'Uw bod op {{boat_name}} is geaccepteerd!',
        'seller_reject_offer'      => 'Uw bod op {{boat_name}} is afgewezen',
        'viewing_request_buyer'    => 'Bevestiging bezichtiging {{boat_name}}',
        'viewing_request_location' => 'Nieuwe bezichtigingsaanvraag voor {{boat_name}}',
        'callback_request'         => 'Nieuw terugbelverzoek van {{buyer_name}}',
        'brochure_request'         => 'Uw brochure voor {{boat_name}}',
        'question_received'        => 'Nieuwe vraag over {{boat_name}}',
        'chat_message_email'       => 'U heeft een nieuw chatbericht',
        'seller_invite'            => 'Uitnodiging voor {{location_name}}',
        'contract_signing'         => 'Uw contract voor {{boat_name}} is gereed',
        'booking_confirmation'     => 'Bevestiging bezichtiging {{boat_name}}',
        'password_invite'          => 'Stel uw wachtwoord in',
    ];

    /**
     * Create all 15 default templates for a given location.
     */
    public function createForLocation(Location $location): void
    {
        foreach (self::TEMPLATE_TYPES as $type) {
            try {
                $this->createForLocationType($location, $type);
            } catch (\Throwable $e) {
                Log::error('[EmailTemplateAutoCreateService] Failed to create template', [
                    'location_id' => $location->id,
                    'type'        => $type,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create a single template for a location + type, unless it already exists.
     */
    public function createForLocationType(Location $location, string $type): ?EmailTemplate
    {
        // Skip if already exists
        $exists = EmailTemplate::where('location_id', $location->id)
            ->where('type', $type)
            ->exists();

        if ($exists) {
            return null;
        }

        // Find global master template
        $global = EmailTemplate::where('is_global', true)
            ->whereNull('location_id')
            ->where('type', $type)
            ->first();

        $readableName = self::READABLE_NAMES[$type] ?? $type;
        $name         = "{$location->name} — {$readableName}";

        if ($global) {
            $subject         = $global->subject;
            $blocks          = $global->blocks;
            $parentId        = $global->id;
        } else {
            $subject  = $this->buildDefaultSubject($type);
            $blocks   = $this->buildDefaultBlocks($type);
            $parentId = null;
        }

        $template = EmailTemplate::create([
            'type'               => $type,
            'name'               => $name,
            'is_global'          => false,
            'location_id'        => $location->id,
            'parent_template_id' => $parentId,
            'language_default'   => 'nl',
            'subject'            => $subject,
            'blocks'             => $blocks,
            'is_active'          => true,
            'is_archived'        => false,
            'current_version'    => 1,
        ]);

        EmailTemplateVersion::create([
            'email_template_id' => $template->id,
            'version'           => 1,
            'subject'           => $subject,
            'blocks'            => $blocks,
            'change_note'       => 'Initiële versie',
        ]);

        Log::info('[EmailTemplateAutoCreateService] Template created', [
            'template_id' => $template->id,
            'location_id' => $location->id,
            'type'        => $type,
        ]);

        return $template;
    }

    /**
     * Create global master templates for all 15 types.
     * Called from seeder.
     */
    public static function createGlobalMasterTemplates(): void
    {
        foreach (self::TEMPLATE_TYPES as $type) {
            $exists = EmailTemplate::where('is_global', true)
                ->whereNull('location_id')
                ->where('type', $type)
                ->exists();

            if ($exists) {
                continue;
            }

            $readableName = self::READABLE_NAMES[$type] ?? $type;
            $subject      = self::buildDefaultSubjectStatic($type);
            $blocks       = self::buildDefaultBlocksStatic($type);

            $template = EmailTemplate::create([
                'type'             => $type,
                'name'             => "Globaal — {$readableName}",
                'description'      => "Globale standaardsjabloon voor: {$readableName}",
                'is_global'        => true,
                'location_id'      => null,
                'language_default' => 'nl',
                'subject'          => $subject,
                'blocks'           => $blocks,
                'is_active'        => true,
                'is_archived'      => false,
                'current_version'  => 1,
            ]);

            EmailTemplateVersion::create([
                'email_template_id' => $template->id,
                'version'           => 1,
                'subject'           => $subject,
                'blocks'            => $blocks,
                'change_note'       => 'Globale standaardsjabloon aangemaakt',
            ]);

            Log::info('[EmailTemplateAutoCreateService] Global master template created', [
                'template_id' => $template->id,
                'type'        => $type,
            ]);
        }
    }

    // ─── Instance helpers (used from createForLocationType) ─────────────────

    private function buildDefaultSubject(string $type): array
    {
        return self::buildDefaultSubjectStatic($type);
    }

    private function buildDefaultBlocks(string $type): array
    {
        return self::buildDefaultBlocksStatic($type);
    }

    // ─── Static helpers (used from createGlobalMasterTemplates) ─────────────

    private static function buildDefaultSubjectStatic(string $type): array
    {
        $nl = self::DEFAULT_SUBJECTS[$type] ?? 'Bericht van Schepenkring';

        return [
            'nl' => $nl,
            'en' => $nl,
            'de' => $nl,
            'fr' => $nl,
        ];
    }

    private static function buildDefaultBlocksStatic(string $type): array
    {
        $readableName = self::READABLE_NAMES[$type] ?? $type;

        $baseBlocks = [
            [
                'type'     => 'logo',
                'settings' => [
                    'src'    => '{{location_logo}}',
                    'alt'    => '{{location_name}}',
                    'height' => 50,
                ],
            ],
            [
                'type'     => 'header',
                'settings' => [
                    'content' => [
                        'nl' => $readableName,
                        'en' => $readableName,
                        'de' => $readableName,
                        'fr' => $readableName,
                    ],
                ],
            ],
        ];

        // Type-specific text block
        $textContent = self::defaultTextContent($type);

        $baseBlocks[] = [
            'type'     => 'text',
            'settings' => [
                'content' => [
                    'nl' => $textContent,
                    'en' => $textContent,
                    'de' => $textContent,
                    'fr' => $textContent,
                ],
            ],
        ];

        // Add a button block for templates that need one
        $buttonBlock = self::defaultButtonBlock($type);
        if ($buttonBlock !== null) {
            $baseBlocks[] = $buttonBlock;
        }

        // Footer
        $baseBlocks[] = [
            'type'     => 'divider',
            'settings' => [],
        ];
        $baseBlocks[] = [
            'type'     => 'footer',
            'settings' => [
                'content' => [
                    'nl' => 'Met vriendelijke groet,\n{{location_name}}\n{{location_phone}} | {{location_email}}',
                    'en' => 'Kind regards,\n{{location_name}}\n{{location_phone}} | {{location_email}}',
                    'de' => 'Mit freundlichen Grüßen,\n{{location_name}}\n{{location_phone}} | {{location_email}}',
                    'fr' => 'Cordialement,\n{{location_name}}\n{{location_phone}} | {{location_email}}',
                ],
            ],
        ];

        return $baseBlocks;
    }

    private static function defaultTextContent(string $type): string
    {
        return match ($type) {
            'offer_received_buyer'     => "Beste {{buyer_name}},\n\nBedankt voor uw bod van {{offer_amount}} op {{boat_name}}. Wij hebben uw bod in goede orde ontvangen en zullen dit zo spoedig mogelijk behandelen.",
            'offer_received_seller'    => "Beste {{seller_name}},\n\nEr is een nieuw bod ontvangen op {{boat_name}}. Het bod bedraagt {{offer_amount}}.",
            'seller_counter_offer'     => "Beste {{buyer_name}},\n\nDe verkoper heeft een tegenbod gedaan van {{counter_offer_amount}} op {{boat_name}}.",
            'seller_accept_offer'      => "Beste {{buyer_name}},\n\nGefeliciteerd! Uw bod van {{offer_amount}} op {{boat_name}} is geaccepteerd door de verkoper.",
            'seller_reject_offer'      => "Beste {{buyer_name}},\n\nHelaas moeten wij u mededelen dat uw bod op {{boat_name}} niet geaccepteerd is.",
            'viewing_request_buyer'    => "Beste {{buyer_name}},\n\nUw bezichtiging van {{boat_name}} is bevestigd op {{booking_date}} om {{booking_time}}.",
            'viewing_request_location' => "Er is een nieuwe bezichtigingsaanvraag ontvangen voor {{boat_name}}.\n\nKoper: {{buyer_name}} ({{buyer_email}})\nDatum: {{booking_date}} om {{booking_time}}",
            'callback_request'         => "Er is een terugbelverzoek ontvangen van {{buyer_name}}.\nTelefoonnummer: {{buyer_phone}}",
            'brochure_request'         => "Beste {{buyer_name}},\n\nHierbij ontvangt u de brochure voor {{boat_name}}.",
            'question_received'        => "Beste {{buyer_name}},\n\nBedankt voor uw vraag over {{boat_name}}. Wij nemen zo spoedig mogelijk contact met u op.",
            'chat_message_email'       => "Beste {{buyer_name}},\n\nU heeft een nieuw bericht ontvangen. Klik op de onderstaande knop om het gesprek te bekijken.",
            'seller_invite'            => "Beste {{seller_name}},\n\nU bent uitgenodigd om uw schip te verkopen via {{location_name}}. Klik op de onderstaande knop om uw account in te stellen.",
            'contract_signing'         => "Beste {{buyer_name}},\n\nHet koopcontract voor {{boat_name}} is gereed voor ondertekening.",
            'booking_confirmation'     => "Beste {{buyer_name}},\n\nUw bezichtiging van {{boat_name}} is bevestigd op {{booking_date}} om {{booking_time}}.",
            'password_invite'          => "Beste {{buyer_name}},\n\nWelkom! Klik op de onderstaande knop om uw wachtwoord in te stellen en toegang te krijgen tot uw account.",
            default                    => "Beste {{buyer_name}},\n\nBedankt voor uw interesse.",
        };
    }

    private static function defaultButtonBlock(string $type): ?array
    {
        $config = match ($type) {
            'offer_received_seller' => ['label' => ['nl' => 'Bekijk bod', 'en' => 'View offer', 'de' => 'Angebot ansehen', 'fr' => "Voir l'offre"], 'url' => '{{offer_link}}'],
            'seller_counter_offer'  => ['label' => ['nl' => 'Bekijk tegenbod', 'en' => 'View counter offer', 'de' => 'Gegenangebot ansehen', 'fr' => 'Voir la contre-offre'], 'url' => '{{offer_link}}'],
            'seller_accept_offer'   => ['label' => ['nl' => 'Bekijk contract', 'en' => 'View contract', 'de' => 'Vertrag ansehen', 'fr' => 'Voir le contrat'], 'url' => '{{contract_link}}'],
            'chat_message_email'    => ['label' => ['nl' => 'Open chat', 'en' => 'Open chat', 'de' => 'Chat öffnen', 'fr' => 'Ouvrir le chat'], 'url' => '{{chat_link}}'],
            'brochure_request'      => ['label' => ['nl' => 'Download brochure', 'en' => 'Download brochure', 'de' => 'Broschüre herunterladen', 'fr' => 'Télécharger la brochure'], 'url' => '{{brochure_link}}'],
            'seller_invite'         => ['label' => ['nl' => 'Account instellen', 'en' => 'Set up account', 'de' => 'Konto einrichten', 'fr' => 'Configurer le compte'], 'url' => '{{verification_link}}'],
            'contract_signing'      => ['label' => ['nl' => 'Contract ondertekenen', 'en' => 'Sign contract', 'de' => 'Vertrag unterzeichnen', 'fr' => 'Signer le contrat'], 'url' => '{{contract_link}}'],
            'password_invite'       => ['label' => ['nl' => 'Wachtwoord instellen', 'en' => 'Set password', 'de' => 'Passwort festlegen', 'fr' => 'Définir le mot de passe'], 'url' => '{{password_reset_link}}'],
            default                 => null,
        };

        if ($config === null) {
            return null;
        }

        return [
            'type'     => 'button',
            'settings' => [
                'label' => $config['label'],
                'url'   => $config['url'],
            ],
        ];
    }
}
