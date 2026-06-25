<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ($this->actions() as $action) {
            DB::table('copilot_actions')->updateOrInsert(
                ['action_id' => $action['action_id']],
                array_merge($action, ['updated_at' => $now, 'created_at' => $now])
            );

            $actionDbId = DB::table('copilot_actions')
                ->where('action_id', $action['action_id'])
                ->value('id');

            if (!$actionDbId) {
                continue;
            }

            foreach ($action['_phrases'] ?? [] as $phrase) {
                DB::table('copilot_action_phrases')->updateOrInsert(
                    [
                        'copilot_action_id' => $actionDbId,
                        'phrase' => $phrase['phrase'],
                        'language' => $phrase['language'],
                    ],
                    [
                        'priority' => $phrase['priority'],
                        'enabled' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        $ids = array_column($this->actions(), 'action_id');
        DB::table('copilot_actions')->whereIn('action_id', $ids)->delete();
    }

    private function actions(): array
    {
        return [
            [
                'action_id' => 'boat.list',
                'title' => 'Open boats',
                'short_description' => 'Open the boats/yachts overview.',
                'module' => 'yachts',
                'target_type' => 'page',
                'description' => 'Navigate to the admin yachts list.',
                'route_template' => '/admin/yachts',
                'required_params' => json_encode([]),
                'tags' => json_encode(['boat', 'yacht', 'list', 'overview']),
                'required_role' => 'admin',
                'risk_level' => 'low',
                'confirmation_required' => false,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'open boats', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'boat list', 'language' => 'en', 'priority' => 95],
                    ['phrase' => 'all boats', 'language' => 'en', 'priority' => 90],
                    ['phrase' => 'yacht overview', 'language' => 'en', 'priority' => 85],
                    ['phrase' => 'open boten', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'boten lijst', 'language' => 'nl', 'priority' => 95],
                    ['phrase' => 'alle boten', 'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'jachten overzicht', 'language' => 'nl', 'priority' => 85],
                ],
            ],
            [
                'action_id' => 'boat.import',
                'title' => 'Import boats',
                'short_description' => 'Open the bulk boat import flow.',
                'module' => 'yachts',
                'target_type' => 'page',
                'description' => 'Navigate to the yacht import page.',
                'route_template' => '/admin/yachts?import=true',
                'required_params' => json_encode([]),
                'tags' => json_encode(['boat', 'import', 'bulk']),
                'required_role' => 'admin',
                'risk_level' => 'medium',
                'confirmation_required' => false,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'import boats', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'bulk import', 'language' => 'en', 'priority' => 90],
                    ['phrase' => 'import yachts', 'language' => 'en', 'priority' => 95],
                    ['phrase' => 'boten importeren', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'import boten', 'language' => 'nl', 'priority' => 95],
                ],
            ],
            [
                'action_id' => 'chat.open',
                'title' => 'Open chat',
                'short_description' => 'Open the chat inbox.',
                'module' => 'chat',
                'target_type' => 'page',
                'description' => 'Navigate to the admin chat / inbox.',
                'route_template' => '/admin/chat',
                'required_params' => json_encode([]),
                'tags' => json_encode(['chat', 'inbox', 'messages']),
                'required_role' => 'admin',
                'risk_level' => 'low',
                'confirmation_required' => false,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'open chat', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'chat inbox', 'language' => 'en', 'priority' => 95],
                    ['phrase' => 'open messages', 'language' => 'en', 'priority' => 85],
                    ['phrase' => 'open chat', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'berichten', 'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'inbox openen', 'language' => 'nl', 'priority' => 95],
                ],
            ],
            [
                'action_id' => 'bookings.open',
                'title' => 'Open bookings',
                'short_description' => 'Open the bookings overview.',
                'module' => 'bookings',
                'target_type' => 'page',
                'description' => 'Navigate to the admin bookings list.',
                'route_template' => '/admin/bookings',
                'required_params' => json_encode([]),
                'tags' => json_encode(['bookings', 'reservations']),
                'required_role' => 'admin',
                'risk_level' => 'low',
                'confirmation_required' => false,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'open bookings', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'bookings overview', 'language' => 'en', 'priority' => 95],
                    ['phrase' => 'reservations', 'language' => 'en', 'priority' => 85],
                    ['phrase' => 'open boekingen', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'boekingen overzicht', 'language' => 'nl', 'priority' => 95],
                ],
            ],
            [
                'action_id' => 'users.open',
                'title' => 'Open users',
                'short_description' => 'Open the users overview.',
                'module' => 'users',
                'target_type' => 'page',
                'description' => 'Navigate to the admin users list.',
                'route_template' => '/admin/users',
                'required_params' => json_encode([]),
                'tags' => json_encode(['users', 'clients', 'accounts']),
                'required_role' => 'admin',
                'risk_level' => 'low',
                'confirmation_required' => false,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'open users', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'user list', 'language' => 'en', 'priority' => 95],
                    ['phrase' => 'all users', 'language' => 'en', 'priority' => 90],
                    ['phrase' => 'open gebruikers', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'gebruikers overzicht', 'language' => 'nl', 'priority' => 95],
                    ['phrase' => 'alle gebruikers', 'language' => 'nl', 'priority' => 90],
                ],
            ],
            [
                'action_id' => 'locations.open',
                'title' => 'Open locations',
                'short_description' => 'Open the locations / harbors overview.',
                'module' => 'locations',
                'target_type' => 'page',
                'description' => 'Navigate to the admin locations (harbors) list.',
                'route_template' => '/admin/locations',
                'required_params' => json_encode([]),
                'tags' => json_encode(['locations', 'harbors', 'partners']),
                'required_role' => 'admin',
                'risk_level' => 'low',
                'confirmation_required' => false,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'open locations', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'harbors', 'language' => 'en', 'priority' => 90],
                    ['phrase' => 'location list', 'language' => 'en', 'priority' => 95],
                    ['phrase' => 'open locaties', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'havens', 'language' => 'nl', 'priority' => 90],
                    ['phrase' => 'locaties overzicht', 'language' => 'nl', 'priority' => 95],
                ],
            ],
            [
                'action_id' => 'audit.open',
                'title' => 'Open audit log',
                'short_description' => 'Open the system audit log.',
                'module' => 'audit',
                'target_type' => 'page',
                'description' => 'Navigate to the admin audit log page.',
                'route_template' => '/admin/audit',
                'required_params' => json_encode([]),
                'tags' => json_encode(['audit', 'log', 'events', 'history']),
                'required_role' => 'admin',
                'risk_level' => 'low',
                'confirmation_required' => false,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'open audit', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'audit log', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'system log', 'language' => 'en', 'priority' => 90],
                    ['phrase' => 'event history', 'language' => 'en', 'priority' => 85],
                    ['phrase' => 'open audit', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'auditlog', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'systeem log', 'language' => 'nl', 'priority' => 90],
                ],
            ],
            [
                'action_id' => 'bids.open',
                'title' => 'Open bids',
                'short_description' => 'Open the bids / offers overview.',
                'module' => 'bids',
                'target_type' => 'page',
                'description' => 'Navigate to the admin offers / bids list.',
                'route_template' => '/admin/offers',
                'required_params' => json_encode([]),
                'tags' => json_encode(['bids', 'offers', 'deals']),
                'required_role' => 'admin',
                'risk_level' => 'low',
                'confirmation_required' => false,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'open bids', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'offers', 'language' => 'en', 'priority' => 95],
                    ['phrase' => 'bids overview', 'language' => 'en', 'priority' => 90],
                    ['phrase' => 'open biedingen', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'biedingen overzicht', 'language' => 'nl', 'priority' => 95],
                    ['phrase' => 'aanbiedingen', 'language' => 'nl', 'priority' => 85],
                ],
            ],
            [
                'action_id' => 'customer.create',
                'title' => 'Create customer',
                'short_description' => 'Open the new customer creation form.',
                'module' => 'users',
                'target_type' => 'page',
                'description' => 'Navigate to create a new customer/user.',
                'route_template' => '/admin/users/new',
                'required_params' => json_encode([]),
                'tags' => json_encode(['customer', 'user', 'create', 'new']),
                'required_role' => 'admin',
                'risk_level' => 'medium',
                'confirmation_required' => false,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'create customer', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'new customer', 'language' => 'en', 'priority' => 95],
                    ['phrase' => 'add user', 'language' => 'en', 'priority' => 90],
                    ['phrase' => 'nieuwe klant', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'klant aanmaken', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'gebruiker toevoegen', 'language' => 'nl', 'priority' => 90],
                ],
            ],
            [
                'action_id' => 'contract.generate',
                'title' => 'Generate contract',
                'short_description' => 'Open the contract generation flow.',
                'module' => 'contracts',
                'target_type' => 'page',
                'description' => 'Navigate to generate a contract for a deal.',
                'route_template' => '/admin/contracts/generate',
                'required_params' => json_encode([]),
                'tags' => json_encode(['contract', 'deal', 'generate', 'sign']),
                'required_role' => 'admin',
                'risk_level' => 'medium',
                'confirmation_required' => true,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'generate contract', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'create contract', 'language' => 'en', 'priority' => 95],
                    ['phrase' => 'contract genereren', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'contract maken', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'genereer contract', 'language' => 'nl', 'priority' => 95],
                    ['phrase' => 'koopcontract maken', 'language' => 'nl', 'priority' => 90],
                ],
            ],
            [
                'action_id' => 'offer.search',
                'title' => 'Search offers',
                'short_description' => 'Search and filter offers / bids.',
                'module' => 'bids',
                'target_type' => 'search',
                'description' => 'Open offers filtered by boat name or buyer.',
                'route_template' => '/admin/offers',
                'required_params' => json_encode([]),
                'tags' => json_encode(['offers', 'search', 'filter', 'bids']),
                'required_role' => 'admin',
                'risk_level' => 'low',
                'confirmation_required' => false,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'search offers', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'find bids', 'language' => 'en', 'priority' => 95],
                    ['phrase' => 'offers by boat', 'language' => 'en', 'priority' => 85],
                    ['phrase' => 'zoek aanbiedingen', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'biedingen zoeken', 'language' => 'nl', 'priority' => 95],
                ],
            ],
            [
                'action_id' => 'impersonate.open',
                'title' => 'Impersonate user',
                'short_description' => 'Open the impersonation flow for a user.',
                'module' => 'users',
                'target_type' => 'page',
                'description' => 'Navigate to the impersonation page. Admin only.',
                'route_template' => '/admin/users',
                'required_params' => json_encode([]),
                'tags' => json_encode(['impersonate', 'user', 'login-as']),
                'required_role' => 'admin',
                'risk_level' => 'high',
                'confirmation_required' => true,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'impersonate user', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'login as user', 'language' => 'en', 'priority' => 90],
                    ['phrase' => 'gebruiker nabootsen', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'inloggen als gebruiker', 'language' => 'nl', 'priority' => 90],
                ],
            ],
            [
                'action_id' => 'copilot.open',
                'title' => 'Open Copilot',
                'short_description' => 'Open the Copilot admin page.',
                'module' => 'copilot',
                'target_type' => 'page',
                'description' => 'Navigate to the admin copilot configuration page.',
                'route_template' => '/admin/copilot',
                'required_params' => json_encode([]),
                'tags' => json_encode(['copilot', 'admin', 'config']),
                'required_role' => 'admin',
                'risk_level' => 'low',
                'confirmation_required' => false,
                'enabled' => true,
                '_phrases' => [
                    ['phrase' => 'open copilot', 'language' => 'en', 'priority' => 100],
                    ['phrase' => 'copilot settings', 'language' => 'en', 'priority' => 90],
                    ['phrase' => 'open copilot', 'language' => 'nl', 'priority' => 100],
                    ['phrase' => 'copilot beheer', 'language' => 'nl', 'priority' => 90],
                ],
            ],
        ];
    }
};
