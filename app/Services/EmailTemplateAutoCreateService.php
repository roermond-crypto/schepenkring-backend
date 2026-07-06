<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use App\Models\Location;
use Illuminate\Support\Facades\Log;

class EmailTemplateAutoCreateService
{
    /** All supported event types (expanded from 15 → 26) */
    public const TEMPLATE_TYPES = [
        // Auth / onboarding
        'user_registration',
        'email_verification',
        'welcome_email',
        'password_reset',
        'password_invite',

        // Offers / bids
        'offer_received_buyer',
        'offer_received_seller',
        'seller_counter_offer',
        'seller_accept_offer',
        'seller_reject_offer',

        // Viewings / appointments
        'viewing_request_buyer',
        'viewing_request_location',
        'booking_confirmation',

        // Boat lifecycle
        'boat_submitted',
        'boat_approved',
        'boat_rejected',

        // Chat / communication
        'chat_message_email',
        'callback_request',
        'brochure_request',
        'question_received',
        'seller_invite',

        // Contract / signing
        'contract_signing',
        'signhost_request',
        'contract_signed',

        // Finance
        'invoice_created',
        'payment_received',
    ];

    public const READABLE_NAMES = [
        'user_registration'         => 'Gebruikersregistratie',
        'email_verification'        => 'E-mailverificatie',
        'welcome_email'             => 'Welkomstmail',
        'password_reset'            => 'Wachtwoord resetten',
        'password_invite'           => 'Wachtwoord uitnodiging',
        'offer_received_buyer'      => 'Bod ontvangen (koper)',
        'offer_received_seller'     => 'Bod ontvangen (verkoper)',
        'seller_counter_offer'      => 'Tegenbod van verkoper',
        'seller_accept_offer'       => 'Bod geaccepteerd',
        'seller_reject_offer'       => 'Bod afgewezen',
        'viewing_request_buyer'     => 'Bezichtigingsverzoek (koper)',
        'viewing_request_location'  => 'Bezichtigingsverzoek (vestiging)',
        'booking_confirmation'      => 'Boekingsbevestiging',
        'boat_submitted'            => 'Boot ingediend',
        'boat_approved'             => 'Boot goedgekeurd',
        'boat_rejected'             => 'Boot afgewezen',
        'chat_message_email'        => 'Chatbericht per e-mail',
        'callback_request'          => 'Terugbelverzoek',
        'brochure_request'          => 'Brochureverzoek',
        'question_received'         => 'Vraag ontvangen',
        'seller_invite'             => 'Uitnodiging verkoper',
        'contract_signing'          => 'Contract ondertekenen',
        'signhost_request'          => 'Signhost ondertekeningsverzoek',
        'contract_signed'           => 'Contract ondertekend',
        'invoice_created'           => 'Factuur aangemaakt',
        'payment_received'          => 'Betaling ontvangen',
    ];

    /** Tag categories used by the tag-insert panel in the editor */
    public const TAG_CATEGORIES = [
        'User'     => ['user_name', 'user_email'],
        'Buyer'    => ['buyer_name', 'buyer_email', 'buyer_phone'],
        'Seller'   => ['seller_name', 'seller_email'],
        'Boat'     => ['boat_name', 'boat_price', 'boat_year', 'boat_url'],
        'Location' => ['location_name', 'location_email', 'location_phone', 'location_address', 'location_url', 'location_logo'],
        'Deal'     => ['offer_amount', 'counter_offer_amount', 'booking_date', 'booking_time', 'offer_link', 'chat_link', 'brochure_link', 'contract_link'],
        'Contract' => ['contract_number', 'signhost_url'],
        'Invoice'  => ['invoice_number', 'invoice_amount', 'invoice_date', 'invoice_link'],
        'Payment'  => ['payment_amount', 'payment_date'],
        'Company'  => ['company_name', 'company_kvk', 'company_btw'],
        'System'   => ['verification_link', 'password_reset_link', 'verification_code'],
    ];

    // ─── Per-type default subjects (multilingual) ────────────────────────────

    private const DEFAULT_SUBJECTS = [
        'user_registration'         => ['nl' => 'Welkom bij {{location_name}} — activeer uw account',          'en' => 'Welcome to {{location_name}} — activate your account'],
        'email_verification'        => ['nl' => 'Bevestig uw e-mailadres',                                     'en' => 'Verify your email address'],
        'welcome_email'             => ['nl' => 'Welkom bij {{location_name}}!',                                'en' => 'Welcome to {{location_name}}!'],
        'password_reset'            => ['nl' => 'Wachtwoord opnieuw instellen',                                 'en' => 'Reset your password'],
        'password_invite'           => ['nl' => 'Stel uw wachtwoord in',                                       'en' => 'Set your password'],
        'offer_received_buyer'      => ['nl' => 'Uw bod op {{boat_name}} is ontvangen',                        'en' => 'Your offer on {{boat_name}} has been received'],
        'offer_received_seller'     => ['nl' => 'Nieuw bod ontvangen op {{boat_name}}',                        'en' => 'New offer received on {{boat_name}}'],
        'seller_counter_offer'      => ['nl' => 'Tegenbod op uw bod op {{boat_name}}',                         'en' => 'Counter offer on your bid for {{boat_name}}'],
        'seller_accept_offer'       => ['nl' => 'Gefeliciteerd! Uw bod op {{boat_name}} is geaccepteerd',      'en' => 'Congratulations! Your offer on {{boat_name}} was accepted'],
        'seller_reject_offer'       => ['nl' => 'Uw bod op {{boat_name}} is helaas afgewezen',                 'en' => 'Unfortunately your offer on {{boat_name}} was declined'],
        'viewing_request_buyer'     => ['nl' => 'Bezichtigingsverzoek bevestigd — {{boat_name}}',              'en' => 'Viewing request confirmed — {{boat_name}}'],
        'viewing_request_location'  => ['nl' => 'Nieuw bezichtigingsverzoek — {{boat_name}}',                  'en' => 'New viewing request — {{boat_name}}'],
        'booking_confirmation'      => ['nl' => 'Boeking bevestigd — {{booking_date}}',                        'en' => 'Booking confirmed — {{booking_date}}'],
        'boat_submitted'            => ['nl' => 'Uw boot is ingediend voor beoordeling',                       'en' => 'Your boat has been submitted for review'],
        'boat_approved'             => ['nl' => 'Uw boot {{boat_name}} is goedgekeurd!',                       'en' => 'Your boat {{boat_name}} has been approved!'],
        'boat_rejected'             => ['nl' => 'Update over uw ingediende boot',                              'en' => 'Update regarding your submitted boat'],
        'chat_message_email'        => ['nl' => 'Nieuw bericht over {{boat_name}}',                            'en' => 'New message about {{boat_name}}'],
        'callback_request'          => ['nl' => 'Terugbelverzoek ontvangen van {{buyer_name}}',                'en' => 'Callback request received from {{buyer_name}}'],
        'brochure_request'          => ['nl' => 'Uw brochure voor {{boat_name}}',                              'en' => 'Your brochure for {{boat_name}}'],
        'question_received'         => ['nl' => 'Nieuwe vraag over {{boat_name}}',                             'en' => 'New question about {{boat_name}}'],
        'seller_invite'             => ['nl' => 'Uitnodiging van {{location_name}}',                           'en' => 'Invitation from {{location_name}}'],
        'contract_signing'          => ['nl' => 'Uw contract voor {{boat_name}} is gereed',                    'en' => 'Your contract for {{boat_name}} is ready'],
        'signhost_request'          => ['nl' => 'Ondertekening vereist — {{boat_name}}',                       'en' => 'Signature required — {{boat_name}}'],
        'contract_signed'           => ['nl' => 'Contract ondertekend — {{boat_name}}',                        'en' => 'Contract signed — {{boat_name}}'],
        'invoice_created'           => ['nl' => 'Nieuwe factuur van {{location_name}}',                        'en' => 'New invoice from {{location_name}}'],
        'payment_received'          => ['nl' => 'Betaling ontvangen — bedankt!',                               'en' => 'Payment received — thank you!'],
    ];

    private const DEFAULT_PREHEADERS = [
        'user_registration'         => 'Klik op de activatielink om uw account te activeren.',
        'email_verification'        => 'Uw verificatiecode staat in deze e-mail.',
        'welcome_email'             => 'Leuk dat u er bent! Ontdek alles wat {{location_name}} te bieden heeft.',
        'password_reset'            => 'Gebruik de link in deze e-mail om een nieuw wachtwoord in te stellen.',
        'password_invite'           => 'Stel uw wachtwoord in om toegang te krijgen tot uw account.',
        'offer_received_buyer'      => 'Uw bod is in goede orde ontvangen en wordt verwerkt.',
        'offer_received_seller'     => 'Een geïnteresseerde koper heeft een bod uitgebracht.',
        'seller_counter_offer'      => 'De verkoper heeft een tegenbod gedaan. Bekijk de details.',
        'seller_accept_offer'       => 'Goed nieuws! Uw bod is geaccepteerd. De volgende stap is het contract.',
        'seller_reject_offer'       => 'Helaas is uw bod niet geaccepteerd. Wellicht zijn er andere mogelijkheden.',
        'viewing_request_buyer'     => 'Uw bezichtigingsverzoek is bevestigd. Zie details hieronder.',
        'viewing_request_location'  => 'Er is een nieuw bezichtigingsverzoek ontvangen.',
        'booking_confirmation'      => 'Uw boeking is bevestigd. We zien u binnenkort!',
        'boat_submitted'            => 'Uw boot is ingediend en wordt beoordeeld door ons team.',
        'boat_approved'             => 'Gefeliciteerd! Uw boot is live en zichtbaar voor kopers.',
        'boat_rejected'             => 'We hebben uw inzending beoordeeld. Lees het bericht voor details.',
        'chat_message_email'        => 'U heeft een nieuw bericht ontvangen. Reageer via de chatpagina.',
        'callback_request'          => 'Een geïnteresseerde wil graag worden teruggebeld.',
        'brochure_request'          => 'Hier is de brochure die u heeft aangevraagd.',
        'question_received'         => 'Er is een vraag ontvangen over een van uw boten.',
        'seller_invite'             => 'We nodigen u uit uw boot te plaatsen. Bekijk het aanbod.',
        'contract_signing'          => 'Uw contract staat klaar. Onderteken eenvoudig online.',
        'signhost_request'          => 'U dient een document te ondertekenen. Klik op de link hieronder.',
        'contract_signed'           => 'Alle partijen hebben het contract ondertekend.',
        'invoice_created'           => 'Uw factuur is beschikbaar. Bekijk en betaal eenvoudig online.',
        'payment_received'          => 'Uw betaling is in goede orde ontvangen. Bedankt!',
    ];

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create all templates for a given location (skips existing ones).
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
        $exists = EmailTemplate::where('location_id', $location->id)
            ->where('type', $type)
            ->where('is_archived', false)
            ->exists();

        if ($exists) {
            return null;
        }

        $global = EmailTemplate::where('is_global', true)
            ->whereNull('location_id')
            ->where('type', $type)
            ->where('is_archived', false)
            ->first();

        $readableName = self::READABLE_NAMES[$type] ?? $type;
        $name         = "{$location->name} — {$readableName}";

        $subject   = $global?->subject   ?? self::buildDefaultSubjectStatic($type);
        $blocks    = $global?->blocks    ?? self::buildDefaultBlocksStatic($type);
        $preheader = $global?->preheader ?? (self::DEFAULT_PREHEADERS[$type] ?? '');

        $template = EmailTemplate::create([
            'type'               => $type,
            'name'               => $name,
            'description'        => "Sjabloon voor {$location->name}.",
            'is_global'          => false,
            'location_id'        => $location->id,
            'parent_template_id' => $global?->id,
            'language_default'   => 'nl',
            'subject'            => $subject,
            'preheader'          => $preheader,
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
            'change_note'       => 'Initieel aangemaakt',
        ]);

        Log::info('[EmailTemplateAutoCreateService] Template created', [
            'template_id' => $template->id,
            'location_id' => $location->id,
            'type'        => $type,
        ]);

        return $template;
    }

    /**
     * Create global master templates for all types. Called from seeder.
     */
    public static function createGlobalMasterTemplates(): void
    {
        foreach (self::TEMPLATE_TYPES as $type) {
            $exists = EmailTemplate::where('is_global', true)
                ->whereNull('location_id')
                ->where('type', $type)
                ->where('is_archived', false)
                ->exists();

            if ($exists) {
                continue;
            }

            $readableName = self::READABLE_NAMES[$type] ?? $type;
            $subject      = self::buildDefaultSubjectStatic($type);
            $blocks       = self::buildDefaultBlocksStatic($type);
            $preheader    = self::DEFAULT_PREHEADERS[$type] ?? '';

            $template = EmailTemplate::create([
                'type'             => $type,
                'name'             => "Globaal — {$readableName}",
                'description'      => "Globaal standaardsjabloon voor: {$readableName}",
                'is_global'        => true,
                'location_id'      => null,
                'language_default' => 'nl',
                'subject'          => $subject,
                'preheader'        => $preheader,
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
                'change_note'       => 'Globaal standaardsjabloon aangemaakt',
            ]);

            Log::info('[EmailTemplateAutoCreateService] Global master template created', [
                'template_id' => $template->id,
                'type'        => $type,
            ]);
        }
    }

    // ─── Static helpers ──────────────────────────────────────────────────────

    public static function buildDefaultSubjectStatic(string $type): array
    {
        $subjects = self::DEFAULT_SUBJECTS[$type] ?? ['nl' => 'Bericht van {{location_name}}', 'en' => 'Message from {{location_name}}'];

        // Always return all 4 supported locales (de/fr fall back to nl when missing)
        return [
            'nl' => $subjects['nl'] ?? $subjects['en'] ?? '',
            'en' => $subjects['en'] ?? $subjects['nl'] ?? '',
            'de' => $subjects['de'] ?? $subjects['nl'] ?? '',
            'fr' => $subjects['fr'] ?? $subjects['nl'] ?? '',
        ];
    }

    public static function buildDefaultBlocksStatic(string $type): array
    {
        $blocks = [];

        // Logo
        $blocks[] = [
            'type'     => 'logo',
            'settings' => [
                'src'    => '{{location_logo}}',
                'alt'    => '{{location_name}}',
                'height' => 50,
                'align'  => 'center',
            ],
        ];

        // Header
        $headerNl = self::defaultHeaderText($type, 'nl');
        $headerEn = self::defaultHeaderText($type, 'en');
        $blocks[] = [
            'type'     => 'header',
            'settings' => [
                'content' => ['nl' => $headerNl, 'en' => $headerEn, 'de' => $headerNl, 'fr' => $headerNl],
                'align'   => 'left',
            ],
        ];

        // Body text
        $textNl = self::defaultTextContent($type, 'nl');
        $textEn = self::defaultTextContent($type, 'en');
        $blocks[] = [
            'type'     => 'text',
            'settings' => [
                'content' => ['nl' => $textNl, 'en' => $textEn, 'de' => $textNl, 'fr' => $textNl],
            ],
        ];

        // CTA button (where applicable)
        $button = self::defaultButtonBlock($type);
        if ($button !== null) {
            $blocks[] = $button;
        }

        // Divider + footer
        $blocks[] = [
            'type'     => 'divider',
            'settings' => ['color' => '#e5e7eb', 'thickness' => 1],
        ];

        $blocks[] = [
            'type'     => 'footer',
            'settings' => [
                'content' => [
                    'nl' => "Met vriendelijke groet,\n{{location_name}}\n{{location_phone}} | {{location_email}}",
                    'en' => "Kind regards,\n{{location_name}}\n{{location_phone}} | {{location_email}}",
                    'de' => "Mit freundlichen Grüßen,\n{{location_name}}\n{{location_phone}} | {{location_email}}",
                    'fr' => "Cordialement,\n{{location_name}}\n{{location_phone}} | {{location_email}}",
                ],
            ],
        ];

        return $blocks;
    }

    private static function defaultHeaderText(string $type, string $lang = 'nl'): string
    {
        if ($lang === 'en') {
            return match ($type) {
                'user_registration'        => 'Welcome! Activate your account',
                'email_verification'       => 'Verify your email address',
                'welcome_email'            => 'Welcome to {{location_name}}!',
                'password_reset'           => 'Reset your password',
                'password_invite'          => 'Set your password',
                'offer_received_buyer'     => 'Your offer has been received',
                'offer_received_seller'    => 'New offer received',
                'seller_counter_offer'     => 'Counter offer received',
                'seller_accept_offer'      => 'Congratulations! Offer accepted',
                'seller_reject_offer'      => 'Offer not accepted',
                'viewing_request_buyer'    => 'Viewing confirmed',
                'viewing_request_location' => 'New viewing request',
                'booking_confirmation'     => 'Booking confirmed',
                'boat_submitted'           => 'Boat submitted for review',
                'boat_approved'            => 'Your boat has been approved!',
                'boat_rejected'            => 'Update about your boat',
                'chat_message_email'       => 'New message received',
                'callback_request'         => 'Callback request received',
                'brochure_request'         => 'Your requested brochure',
                'question_received'        => 'Question received',
                'seller_invite'            => 'Invitation from {{location_name}}',
                'contract_signing'         => 'Contract ready for signing',
                'signhost_request'         => 'Signature required',
                'contract_signed'          => 'Contract signed',
                'invoice_created'          => 'New invoice available',
                'payment_received'         => 'Payment received',
                default                    => 'Message from {{location_name}}',
            };
        }

        return match ($type) {
            'user_registration'        => 'Welkom! Activeer uw account',
            'email_verification'       => 'Bevestig uw e-mailadres',
            'welcome_email'            => 'Welkom bij {{location_name}}!',
            'password_reset'           => 'Wachtwoord opnieuw instellen',
            'password_invite'          => 'Stel uw wachtwoord in',
            'offer_received_buyer'     => 'Uw bod is ontvangen',
            'offer_received_seller'    => 'Nieuw bod ontvangen',
            'seller_counter_offer'     => 'Tegenbod ontvangen',
            'seller_accept_offer'      => 'Gefeliciteerd! Bod geaccepteerd',
            'seller_reject_offer'      => 'Bod niet geaccepteerd',
            'viewing_request_buyer'    => 'Bezichtiging bevestigd',
            'viewing_request_location' => 'Nieuw bezichtigingsverzoek',
            'booking_confirmation'     => 'Boeking bevestigd',
            'boat_submitted'           => 'Boot ingediend voor beoordeling',
            'boat_approved'            => 'Uw boot is goedgekeurd!',
            'boat_rejected'            => 'Update over uw boot',
            'chat_message_email'       => 'Nieuw bericht ontvangen',
            'callback_request'         => 'Terugbelverzoek ontvangen',
            'brochure_request'         => 'Uw aangevraagde brochure',
            'question_received'        => 'Vraag ontvangen',
            'seller_invite'            => 'Uitnodiging van {{location_name}}',
            'contract_signing'         => 'Contract klaar voor ondertekening',
            'signhost_request'         => 'Ondertekening vereist',
            'contract_signed'          => 'Contract ondertekend',
            'invoice_created'          => 'Nieuwe factuur beschikbaar',
            'payment_received'         => 'Betaling ontvangen',
            default                    => 'Bericht van {{location_name}}',
        };
    }

    private static function defaultTextContent(string $type, string $lang = 'nl'): string
    {
        if ($lang === 'en') {
            return match ($type) {
                'user_registration'        => "Dear {{user_name}},\n\nThank you for registering with {{location_name}}.\n\nClick the button below to activate your account.",
                'email_verification'       => "Dear {{user_name}},\n\nUse the verification code below to confirm your email address:\n\n{{verification_code}}\n\nThis code is valid for 15 minutes.",
                'welcome_email'            => "Dear {{user_name}},\n\nWelcome to {{location_name}}! We are glad to have you.\n\nExplore our selection of boats and feel free to contact us if you have any questions.",
                'password_reset'           => "Dear {{user_name}},\n\nWe received a request to reset your password.\n\nClick the button below to set a new password. This link is valid for 60 minutes.",
                'password_invite'          => "Dear {{user_name}},\n\nYou have been invited to the platform of {{location_name}}.\n\nSet your password via the button below to access your account.",
                'offer_received_buyer'     => "Dear {{buyer_name}},\n\nThank you for your offer of {{offer_amount}} on {{boat_name}}. Our team is processing your offer and the seller will be notified.",
                'offer_received_seller'    => "Dear {{seller_name}},\n\nA new offer of {{offer_amount}} has been received on {{boat_name}} from {{buyer_name}}.",
                'seller_counter_offer'     => "Dear {{buyer_name}},\n\nThe seller has made a counter offer of {{counter_offer_amount}} on {{boat_name}}.",
                'seller_accept_offer'      => "Dear {{buyer_name}},\n\nCongratulations! Your offer on {{boat_name}} has been accepted. The next step is signing the purchase contract.",
                'seller_reject_offer'      => "Dear {{buyer_name}},\n\nUnfortunately the seller has not accepted your offer on {{boat_name}}. Please browse our other listings.",
                'viewing_request_buyer'    => "Dear {{buyer_name}},\n\nYour viewing request for {{boat_name}} has been confirmed.\n\nDate: {{booking_date}}\nTime: {{booking_time}}\nLocation: {{location_name}} — {{location_address}}",
                'viewing_request_location' => "A new viewing request has been received for {{boat_name}}.\n\nBuyer: {{buyer_name}} ({{buyer_email}} / {{buyer_phone}})\nDate: {{booking_date}}\nTime: {{booking_time}}",
                'booking_confirmation'     => "Dear {{buyer_name}},\n\nYour booking is confirmed!\n\nDate: {{booking_date}}\nTime: {{booking_time}}\nLocation: {{location_name}}",
                'boat_submitted'           => "Dear {{seller_name}},\n\nYour boat {{boat_name}} has been successfully submitted and is being reviewed by our team.",
                'boat_approved'            => "Dear {{seller_name}},\n\nGreat news! Your boat {{boat_name}} has been approved and is now visible to potential buyers on our platform.",
                'boat_rejected'            => "Dear {{seller_name}},\n\nAfter reviewing your boat {{boat_name}} we are unfortunately unable to list it at this time. Please contact us at {{location_email}} for more information.",
                'chat_message_email'       => "Dear {{user_name}},\n\nYou have received a new message about {{boat_name}}. Reply via the chat page by clicking the button below.",
                'callback_request'         => "A callback request has been received.\n\nName: {{buyer_name}}\nPhone: {{buyer_phone}}\nEmail: {{buyer_email}}",
                'brochure_request'         => "Dear {{buyer_name}},\n\nThank you for your interest in {{boat_name}}. Please find the requested brochure attached.",
                'question_received'        => "Dear {{seller_name}},\n\nA question has been received about your boat {{boat_name}} from {{buyer_name}} ({{buyer_email}}).",
                'seller_invite'            => "Dear {{seller_name}},\n\n{{location_name}} invites you to list your boat on our platform.\n\nClick the button below to set up your account.",
                'contract_signing'         => "Dear {{buyer_name}},\n\nThe purchase contract for {{boat_name}} is ready for signing. Sign it easily online via the button below.",
                'signhost_request'         => "Dear {{user_name}},\n\nYou are required to sign a document via Signhost. Click the button below to start the signing process.",
                'contract_signed'          => "Dear {{buyer_name}},\n\nAll parties have signed the contract for {{boat_name}}. A copy of the signed contract is enclosed.",
                'invoice_created'          => "Dear {{user_name}},\n\nA new invoice from {{location_name}} is available.\n\nAmount: {{invoice_amount}}\nInvoice date: {{invoice_date}}",
                'payment_received'         => "Dear {{user_name}},\n\nThank you! Your payment of {{payment_amount}} was received on {{payment_date}}. Please keep this as proof of payment.",
                default                    => "Dear {{user_name}},\n\nPlease find the information prepared for you below.\n\nKind regards,\n{{location_name}}",
            };
        }

        // Dutch (default)
        return match ($type) {
            'user_registration'        => "Beste {{user_name}},\n\nBedankt voor uw registratie bij {{location_name}}.\n\nKlik op de knop hieronder om uw account te activeren.",
            'email_verification'       => "Beste {{user_name}},\n\nGebruik de onderstaande verificatiecode om uw e-mailadres te bevestigen:\n\n{{verification_code}}\n\nDeze code is 15 minuten geldig.",
            'welcome_email'            => "Beste {{user_name}},\n\nWelkom bij {{location_name}}! Wij zijn blij dat u er bent.\n\nOntdek ons aanbod van boten en neem gerust contact op als u vragen heeft.",
            'password_reset'           => "Beste {{user_name}},\n\nWe hebben een verzoek ontvangen om uw wachtwoord opnieuw in te stellen.\n\nKlik op de knop hieronder om een nieuw wachtwoord in te stellen. Deze link is 60 minuten geldig.",
            'password_invite'          => "Beste {{user_name}},\n\nU bent uitgenodigd voor het platform van {{location_name}}.\n\nStel uw wachtwoord in via de knop hieronder om toegang te krijgen tot uw account.",
            'offer_received_buyer'     => "Beste {{buyer_name}},\n\nBedankt voor uw bod van {{offer_amount}} op {{boat_name}}. Wij hebben uw bod in goede orde ontvangen en zullen dit zo spoedig mogelijk behandelen.",
            'offer_received_seller'    => "Beste {{seller_name}},\n\nEr is een nieuw bod van {{offer_amount}} ontvangen op {{boat_name}} van {{buyer_name}}.",
            'seller_counter_offer'     => "Beste {{buyer_name}},\n\nDe verkoper heeft een tegenbod gedaan van {{counter_offer_amount}} op {{boat_name}}.",
            'seller_accept_offer'      => "Beste {{buyer_name}},\n\nGefeliciteerd! Uw bod op {{boat_name}} is geaccepteerd door de verkoper. De volgende stap is het ondertekenen van het koopcontract.",
            'seller_reject_offer'      => "Beste {{buyer_name}},\n\nHelaas heeft de verkoper uw bod op {{boat_name}} niet geaccepteerd. Bekijk ons andere aanbod.",
            'viewing_request_buyer'    => "Beste {{buyer_name}},\n\nUw bezichtigingsverzoek voor {{boat_name}} is bevestigd.\n\nDatum: {{booking_date}}\nTijd: {{booking_time}}\nLocatie: {{location_name}} — {{location_address}}",
            'viewing_request_location' => "Er is een nieuw bezichtigingsverzoek ontvangen voor {{boat_name}}.\n\nKoper: {{buyer_name}} ({{buyer_email}} / {{buyer_phone}})\nDatum: {{booking_date}}\nTijd: {{booking_time}}",
            'booking_confirmation'     => "Beste {{buyer_name}},\n\nUw boeking is bevestigd!\n\nDatum: {{booking_date}}\nTijd: {{booking_time}}\nLocatie: {{location_name}}",
            'boat_submitted'           => "Beste {{seller_name}},\n\nUw boot {{boat_name}} is succesvol ingediend en wordt nu beoordeeld door ons team.",
            'boat_approved'            => "Beste {{seller_name}},\n\nGoed nieuws! Uw boot {{boat_name}} is goedgekeurd en is nu zichtbaar voor potentiële kopers op ons platform.",
            'boat_rejected'            => "Beste {{seller_name}},\n\nNa beoordeling van uw boot {{boat_name}} kunnen wij dit aanbod helaas op dit moment niet plaatsen. Neem contact op via {{location_email}} voor meer informatie.",
            'chat_message_email'       => "Beste {{user_name}},\n\nU heeft een nieuw bericht ontvangen over {{boat_name}}. Reageer via de chatpagina door op de knop hieronder te klikken.",
            'callback_request'         => "Er is een terugbelverzoek ontvangen.\n\nNaam: {{buyer_name}}\nTelefoon: {{buyer_phone}}\nE-mail: {{buyer_email}}",
            'brochure_request'         => "Beste {{buyer_name}},\n\nBedankt voor uw interesse in {{boat_name}}. Hierbij ontvangt u de aangevraagde brochure.",
            'question_received'        => "Beste {{seller_name}},\n\nEr is een vraag ontvangen over uw boot {{boat_name}} van {{buyer_name}} ({{buyer_email}}).",
            'seller_invite'            => "Beste {{seller_name}},\n\n{{location_name}} nodigt u uit om uw boot te plaatsen op ons platform.\n\nKlik op de knop hieronder om uw account in te stellen.",
            'contract_signing'         => "Beste {{buyer_name}},\n\nHet koopcontract voor {{boat_name}} is gereed voor ondertekening. Onderteken het contract eenvoudig online via de knop hieronder.",
            'signhost_request'         => "Beste {{user_name}},\n\nU dient een document te ondertekenen via Signhost. Klik op de knop hieronder om het ondertekeningsproces te starten.",
            'contract_signed'          => "Beste {{buyer_name}},\n\nAlle partijen hebben het contract voor {{boat_name}} ondertekend. Een kopie van het ondertekende contract is bijgevoegd.",
            'invoice_created'          => "Beste {{user_name}},\n\nEr is een nieuwe factuur voor u beschikbaar gesteld door {{location_name}}.\n\nBedrag: {{invoice_amount}}\nFactuurdatum: {{invoice_date}}",
            'payment_received'         => "Beste {{user_name}},\n\nBedankt! Uw betaling van {{payment_amount}} is in goede orde ontvangen op {{payment_date}}. Bewaar dit bericht als betalingsbewijs.",
            default                    => "Beste {{user_name}},\n\nHieronder vindt u de informatie die voor u is klaargezet.\n\nMet vriendelijke groet,\n{{location_name}}",
        };
    }

    private static function defaultButtonBlock(string $type): ?array
    {
        $config = match ($type) {
            'user_registration'     => ['nl' => 'Account activeren',       'en' => 'Activate account',        'url' => '{{verification_link}}',  'color' => '#1d4ed8'],
            'email_verification'    => ['nl' => 'E-mail bevestigen',        'en' => 'Confirm email',           'url' => '{{verification_link}}',  'color' => '#1d4ed8'],
            'welcome_email'         => ['nl' => 'Bekijk ons aanbod',        'en' => 'Browse our boats',        'url' => '{{location_url}}',       'color' => '#1d4ed8'],
            'password_reset'        => ['nl' => 'Wachtwoord instellen',     'en' => 'Set password',            'url' => '{{password_reset_link}}','color' => '#dc2626'],
            'password_invite'       => ['nl' => 'Wachtwoord instellen',     'en' => 'Set password',            'url' => '{{password_reset_link}}','color' => '#1d4ed8'],
            'offer_received_buyer'  => ['nl' => 'Bod bekijken',             'en' => 'View offer',              'url' => '{{offer_link}}',         'color' => '#1d4ed8'],
            'offer_received_seller' => ['nl' => 'Bod bekijken',             'en' => 'View offer',              'url' => '{{offer_link}}',         'color' => '#1d4ed8'],
            'seller_counter_offer'  => ['nl' => 'Tegenbod bekijken',        'en' => 'View counter offer',      'url' => '{{offer_link}}',         'color' => '#f59e0b'],
            'seller_accept_offer'   => ['nl' => 'Contract bekijken',        'en' => 'View contract',           'url' => '{{contract_link}}',      'color' => '#16a34a'],
            'seller_reject_offer'   => ['nl' => 'Bekijk ons aanbod',        'en' => 'Browse listings',         'url' => '{{location_url}}',       'color' => '#6b7280'],
            'viewing_request_buyer' => ['nl' => 'Details bekijken',         'en' => 'View details',            'url' => '{{boat_url}}',           'color' => '#1d4ed8'],
            'booking_confirmation'  => ['nl' => 'Boeking bekijken',         'en' => 'View booking',            'url' => '{{boat_url}}',           'color' => '#1d4ed8'],
            'boat_submitted'        => ['nl' => 'Mijn boten',               'en' => 'My boats',                'url' => '{{boat_url}}',           'color' => '#1d4ed8'],
            'boat_approved'         => ['nl' => 'Advertentie bekijken',     'en' => 'View listing',            'url' => '{{boat_url}}',           'color' => '#16a34a'],
            'boat_rejected'         => ['nl' => 'Contact opnemen',          'en' => 'Contact us',              'url' => '{{location_url}}',       'color' => '#6b7280'],
            'chat_message_email'    => ['nl' => 'Reageren',                 'en' => 'Reply',                   'url' => '{{chat_link}}',          'color' => '#1d4ed8'],
            'brochure_request'      => ['nl' => 'Download brochure',        'en' => 'Download brochure',       'url' => '{{brochure_link}}',      'color' => '#1d4ed8'],
            'seller_invite'         => ['nl' => 'Account instellen',        'en' => 'Set up account',          'url' => '{{verification_link}}',  'color' => '#1d4ed8'],
            'contract_signing'      => ['nl' => 'Contract ondertekenen',    'en' => 'Sign contract',           'url' => '{{contract_link}}',      'color' => '#16a34a'],
            'signhost_request'      => ['nl' => 'Document ondertekenen',    'en' => 'Sign document',           'url' => '{{signhost_url}}',       'color' => '#7c3aed'],
            'contract_signed'       => ['nl' => 'Contract downloaden',      'en' => 'Download contract',       'url' => '{{contract_link}}',      'color' => '#6b7280'],
            'invoice_created'       => ['nl' => 'Factuur bekijken',         'en' => 'View invoice',            'url' => '{{invoice_link}}',       'color' => '#1d4ed8'],
            'payment_received'      => ['nl' => 'Overzicht bekijken',       'en' => 'View overview',           'url' => '{{location_url}}',       'color' => '#16a34a'],
            default                 => null,
        };

        if ($config === null) {
            return null;
        }

        return [
            'type'     => 'button',
            'settings' => [
                'label' => [
                    'nl' => $config['nl'],
                    'en' => $config['en'],
                    'de' => $config['nl'],
                    'fr' => $config['nl'],
                ],
                'url'   => $config['url'],
                'color' => $config['color'],
                'align' => 'center',
            ],
        ];
    }
}
