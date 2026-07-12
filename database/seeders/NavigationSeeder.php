<?php

namespace Database\Seeders;

use App\Models\NavItem;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds the current PublicHeader.tsx nav links (pulled from the real
 * locales/*.json BoatIntake.nav.* keys, not placeholder text) as real
 * nav_items rows, and the real footer contact details (also pulled from
 * locales/*.json's Support.* keys) into site_settings — so the header/
 * footer builder has real data on day one, matching the CmsBoatAanmeldenSeeder
 * approach for pages.
 */
class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        $headerLinks = [
            [
                'label' => ['nl' => 'Aanbod', 'en' => 'Listings', 'de' => 'Angebote', 'fr' => 'Offres'],
                'url' => 'https://www.schepenkring.nl/aanbod-boten/',
                'sort_order' => 0,
            ],
            [
                'label' => ['nl' => 'Vestigingen', 'en' => 'Locations', 'de' => 'Standorte', 'fr' => 'Agences'],
                'url' => 'https://www.schepenkring.nl/vestigingen/',
                'sort_order' => 1,
            ],
            [
                'label' => ['nl' => 'Over ons', 'en' => 'About', 'de' => 'Über uns', 'fr' => 'À propos'],
                'url' => 'https://www.schepenkring.nl/boot-verkopen/schip-verkopen/',
                'sort_order' => 2,
            ],
        ];

        foreach ($headerLinks as $link) {
            NavItem::query()->updateOrCreate(
                ['location' => NavItem::LOCATION_HEADER, 'url' => $link['url']],
                [
                    'label' => $link['label'],
                    'sort_order' => $link['sort_order'],
                    'is_visible' => true,
                ],
            );
        }

        $footerLinks = [
            [
                'column' => 'company',
                'label' => ['nl' => 'Aanbod', 'en' => 'Listings', 'de' => 'Angebote', 'fr' => 'Offres'],
                'url' => 'https://www.schepenkring.nl/aanbod-boten/',
                'sort_order' => 0,
            ],
            [
                'column' => 'company',
                'label' => ['nl' => 'Vestigingen', 'en' => 'Locations', 'de' => 'Standorte', 'fr' => 'Agences'],
                'url' => 'https://www.schepenkring.nl/vestigingen/',
                'sort_order' => 1,
            ],
            [
                'column' => 'support',
                'label' => ['nl' => 'Boot aanmelden', 'en' => 'Sell your boat', 'de' => 'Boot verkaufen', 'fr' => 'Vendre un bateau'],
                'url' => '/boot-aanmelden',
                'sort_order' => 0,
            ],
        ];

        foreach ($footerLinks as $link) {
            NavItem::query()->updateOrCreate(
                ['location' => NavItem::LOCATION_FOOTER, 'url' => $link['url']],
                [
                    'footer_column' => $link['column'],
                    'label' => $link['label'],
                    'sort_order' => $link['sort_order'],
                    'is_visible' => true,
                ],
            );
        }

        SiteSetting::current()->update([
            'footer_tagline' => [
                'nl' => '25+ jaar dé specialist in jachtbemiddeling.',
                'en' => '25+ years the specialist in yacht brokerage.',
                'de' => '25+ Jahre der Spezialist für Yachtvermittlung.',
                'fr' => "25+ ans le spécialiste du courtage de yachts.",
            ],
            'contact_email' => 'lelystad@schepenkring.nl',
            'contact_phone' => '+31 (0)320 711340',
            'contact_address' => 'Parkhaven 3, 8242 PE Lelystad',
        ]);
    }
}
