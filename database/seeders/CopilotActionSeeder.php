<?php

namespace Database\Seeders;

use App\Models\CopilotAction;
use App\Models\CopilotActionPhrase;
use Illuminate\Database\Seeder;

class CopilotActionSeeder extends Seeder
{
    public function run(): void
    {
        $actions = [
            // ── Boats ──────────────────────────────────────────────────────────
            [
                'action_id'           => 'boat.create',
                'title'               => 'Boot aanmaken',
                'module'              => 'boats',
                'target_type'         => 'page',
                'route_template'      => '/admin/yachts/new?fresh=true',
                'risk_level'          => 'low',
                'confirmation_required' => false,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'create boat',        'language' => 'en', 'priority' => 100],
                    ['phrase' => 'new boat',           'language' => 'en', 'priority' => 100],
                    ['phrase' => 'add yacht',          'language' => 'en', 'priority' => 100],
                    ['phrase' => 'boot toevoegen',     'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'nieuwe boot',        'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'boot aanmaken',      'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'boot maken',         'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'nieuwe yacht',       'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'jacht aanmaken',     'language' => 'nl', 'priority' => 90],
                ],
            ],
            [
                'action_id'           => 'boat.list',
                'title'               => 'Boten overzicht',
                'module'              => 'boats',
                'target_type'         => 'page',
                'route_template'      => '/admin/yachts',
                'risk_level'          => 'low',
                'confirmation_required' => false,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'open boats',         'language' => 'en', 'priority' => 90],
                    ['phrase' => 'list boats',         'language' => 'en', 'priority' => 90],
                    ['phrase' => 'all yachts',         'language' => 'en', 'priority' => 80],
                    ['phrase' => 'boten overzicht',    'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'alle boten',         'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'open boten',         'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'jachten overzicht',  'language' => 'nl', 'priority' => 80],
                ],
            ],
            [
                'action_id'           => 'boat.import',
                'title'               => 'Boten importeren',
                'module'              => 'boats',
                'target_type'         => 'page',
                'route_template'      => '/admin/yachts?import=true',
                'risk_level'          => 'medium',
                'confirmation_required' => true,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'import boats',       'language' => 'en', 'priority' => 90],
                    ['phrase' => 'import yachts',      'language' => 'en', 'priority' => 90],
                    ['phrase' => 'boten importeren',   'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'importeer boten',    'language' => 'nl', 'priority' => 90],
                ],
            ],

            // ── Chat ──────────────────────────────────────────────────────────
            [
                'action_id'           => 'chat.open',
                'title'               => 'Chat openen',
                'module'              => 'chat',
                'target_type'         => 'page',
                'route_template'      => '/admin/chat',
                'risk_level'          => 'low',
                'confirmation_required' => false,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'open chat',          'language' => 'en', 'priority' => 90],
                    ['phrase' => 'open messages',      'language' => 'en', 'priority' => 80],
                    ['phrase' => 'chat openen',        'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'berichten openen',   'language' => 'nl', 'priority' => 80],
                    ['phrase' => 'open berichten',     'language' => 'nl', 'priority' => 80],
                ],
            ],

            // ── Bookings ───────────────────────────────────────────────────────
            [
                'action_id'           => 'bookings.open',
                'title'               => 'Boekingen openen',
                'module'              => 'bookings',
                'target_type'         => 'page',
                'route_template'      => '/admin/bookings',
                'risk_level'          => 'low',
                'confirmation_required' => false,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'open bookings',      'language' => 'en', 'priority' => 90],
                    ['phrase' => 'list bookings',      'language' => 'en', 'priority' => 80],
                    ['phrase' => 'boekingen openen',   'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'alle boekingen',     'language' => 'nl', 'priority' => 80],
                    ['phrase' => 'open boekingen',     'language' => 'nl', 'priority' => 90],
                ],
            ],

            // ── Users ──────────────────────────────────────────────────────────
            [
                'action_id'           => 'users.open',
                'title'               => 'Gebruikers openen',
                'module'              => 'users',
                'target_type'         => 'page',
                'route_template'      => '/admin/users',
                'risk_level'          => 'low',
                'confirmation_required' => false,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'open users',         'language' => 'en', 'priority' => 90],
                    ['phrase' => 'list users',         'language' => 'en', 'priority' => 80],
                    ['phrase' => 'gebruikers openen',  'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'alle gebruikers',    'language' => 'nl', 'priority' => 80],
                    ['phrase' => 'open gebruikers',    'language' => 'nl', 'priority' => 90],
                ],
            ],
            [
                'action_id'           => 'customer.create',
                'title'               => 'Klant aanmaken',
                'module'              => 'users',
                'target_type'         => 'page',
                'route_template'      => '/admin/users?action=create',
                'risk_level'          => 'low',
                'confirmation_required' => false,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'create customer',    'language' => 'en', 'priority' => 90],
                    ['phrase' => 'new customer',       'language' => 'en', 'priority' => 90],
                    ['phrase' => 'add user',           'language' => 'en', 'priority' => 80],
                    ['phrase' => 'klant aanmaken',     'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'nieuwe klant',       'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'klant toevoegen',    'language' => 'nl', 'priority' => 90],
                ],
            ],

            // ── Locations ─────────────────────────────────────────────────────
            [
                'action_id'           => 'locations.open',
                'title'               => 'Locaties openen',
                'module'              => 'locations',
                'target_type'         => 'page',
                'route_template'      => '/admin/locations',
                'risk_level'          => 'low',
                'confirmation_required' => false,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'open locations',     'language' => 'en', 'priority' => 90],
                    ['phrase' => 'list harbors',       'language' => 'en', 'priority' => 80],
                    ['phrase' => 'locaties openen',    'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'havens openen',      'language' => 'nl', 'priority' => 80],
                    ['phrase' => 'alle locaties',      'language' => 'nl', 'priority' => 80],
                ],
            ],

            // ── Audit ─────────────────────────────────────────────────────────
            [
                'action_id'           => 'audit.open',
                'title'               => 'Audit log openen',
                'module'              => 'audit',
                'target_type'         => 'page',
                'route_template'      => '/admin/boat-audit',
                'risk_level'          => 'low',
                'confirmation_required' => false,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'open audit',         'language' => 'en', 'priority' => 90],
                    ['phrase' => 'audit log',          'language' => 'en', 'priority' => 90],
                    ['phrase' => 'audit openen',       'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'audit log openen',   'language' => 'nl', 'priority' => 90],
                ],
            ],

            // ── Bids / Offers ─────────────────────────────────────────────────
            [
                'action_id'           => 'bids.open',
                'title'               => 'Biedingen openen',
                'module'              => 'bids',
                'target_type'         => 'page',
                'route_template'      => '/admin/offers',
                'risk_level'          => 'low',
                'confirmation_required' => false,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'open bids',          'language' => 'en', 'priority' => 90],
                    ['phrase' => 'open offers',        'language' => 'en', 'priority' => 90],
                    ['phrase' => 'biedingen openen',   'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'open biedingen',     'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'aanbiedingen openen','language' => 'nl', 'priority' => 80],
                ],
            ],
            [
                'action_id'           => 'offer.search',
                'title'               => 'Biedingen zoeken',
                'module'              => 'bids',
                'target_type'         => 'search',
                'route_template'      => '/admin/offers?q={query}',
                'risk_level'          => 'low',
                'confirmation_required' => false,
                'required_role'       => 'admin',
                'required_params'     => ['query'],
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'search bids',        'language' => 'en', 'priority' => 80],
                    ['phrase' => 'find bids for',      'language' => 'en', 'priority' => 80],
                    ['phrase' => 'biedingen zoeken',   'language' => 'nl', 'priority' => 80],
                    ['phrase' => 'zoek biedingen',     'language' => 'nl', 'priority' => 80],
                ],
            ],

            // ── Contracts ─────────────────────────────────────────────────────
            [
                'action_id'           => 'contract.generate',
                'title'               => 'Contract genereren',
                'module'              => 'contracts',
                'target_type'         => 'api',
                'route_template'      => '/api/yachts/{yacht_id}/contract/generate',
                'risk_level'          => 'medium',
                'confirmation_required' => true,
                'required_role'       => 'admin',
                'required_params'     => ['yacht_id'],
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'generate contract',       'language' => 'en', 'priority' => 90],
                    ['phrase' => 'create contract',         'language' => 'en', 'priority' => 90],
                    ['phrase' => 'contract genereren',      'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'genereer contract',       'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'contract maken',          'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'maak contract',           'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'koopcontract maken',      'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'genereer contract voor deal', 'language' => 'nl', 'priority' => 80],
                ],
            ],

            // ── Impersonation ─────────────────────────────────────────────────
            [
                'action_id'           => 'impersonate.open',
                'title'               => 'Gebruiker overnemen',
                'module'              => 'users',
                'target_type'         => 'page',
                'route_template'      => '/admin/users',
                'risk_level'          => 'high',
                'confirmation_required' => true,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'impersonate user',     'language' => 'en', 'priority' => 90],
                    ['phrase' => 'login as user',        'language' => 'en', 'priority' => 90],
                    ['phrase' => 'gebruiker overnemen',  'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'inloggen als gebruiker','language' => 'nl', 'priority' => 90],
                ],
            ],

            // ── AI assistant actions ───────────────────────────────────────────
            [
                'action_id'           => 'ai.summarize_chat',
                'title'               => 'Chat samenvatten',
                'module'              => 'ai',
                'target_type'         => 'ai',
                'route_template'      => null,
                'risk_level'          => 'low',
                'confirmation_required' => false,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'summarize chat',       'language' => 'en', 'priority' => 90],
                    ['phrase' => 'chat samenvatten',     'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'vat chat samen',       'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'samenvatting chat',    'language' => 'nl', 'priority' => 80],
                ],
            ],
            [
                'action_id'           => 'ai.generate_description',
                'title'               => 'Bootbeschrijving genereren',
                'module'              => 'ai',
                'target_type'         => 'ai',
                'route_template'      => null,
                'risk_level'          => 'low',
                'confirmation_required' => false,
                'required_role'       => 'admin',
                'enabled'             => true,
                'phrases' => [
                    ['phrase' => 'generate description',      'language' => 'en', 'priority' => 90],
                    ['phrase' => 'write boat description',    'language' => 'en', 'priority' => 90],
                    ['phrase' => 'beschrijving genereren',    'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'genereer beschrijving',     'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'maak beschrijving',         'language' => 'nl', 'priority' => 80],
                ],
            ],
        ];

        foreach ($actions as $actionData) {
            $phrases = $actionData['phrases'] ?? [];
            unset($actionData['phrases']);

            $action = CopilotAction::updateOrCreate(
                ['action_id' => $actionData['action_id']],
                $actionData
            );

            foreach ($phrases as $phraseData) {
                CopilotActionPhrase::updateOrCreate(
                    [
                        'copilot_action_id' => $action->id,
                        'phrase'            => $phraseData['phrase'],
                    ],
                    array_merge($phraseData, [
                        'enabled' => true,
                    ])
                );
            }
        }
    }
}
