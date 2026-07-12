<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use App\Services\Cms\CmsPageService;
use Illuminate\Database\Seeder;

/**
 * Seeds the 'boot-aanmelden' page as real CMS data — the proving-ground
 * page for the CMS build, using the actual current hero content (pulled
 * directly from src/locales/{nl,en,de,fr}.json's BoatIntake.hero keys,
 * not placeholder text) so the admin editor has something real to show
 * on day one. Migrating BoatIntakePage.tsx to actually FETCH and render
 * this instead of its own hardcoded/dictionary content is separate,
 * later frontend work — this seeder only proves the backend content
 * model holds real content correctly end-to-end.
 */
class CmsBoatAanmeldenSeeder extends Seeder
{
    public function run(): void
    {
        $page = CmsPage::query()->where('slug', 'boot-aanmelden')->first();

        if (! $page) {
            $page = app(CmsPageService::class)->create([
                'slug' => 'boot-aanmelden',
                'name' => 'Boot aanmelden (seller intake)',
                'seo' => [
                    'title' => [
                        'nl' => 'Boot verkopen via Schepenkring',
                        'en' => 'Sell your boat through Schepenkring',
                        'de' => 'Boot verkaufen über Schepenkring',
                        'fr' => 'Vendre votre bateau via Schepenkring',
                    ],
                    'description' => [
                        'nl' => 'Meld uw boot gratis aan bij Schepenkring. Onze makelaar neemt binnen één werkdag contact met u op.',
                        'en' => 'Register your boat with Schepenkring for free. Our broker will contact you within one business day.',
                        'de' => 'Melden Sie Ihr Boot kostenlos bei Schepenkring an.',
                        'fr' => 'Inscrivez votre bateau gratuitement chez Schepenkring.',
                    ],
                ],
            ], null);
        }

        app(CmsPageService::class)->saveSections($page, [
            [
                'component' => 'HeroSection',
                'variant' => 'default',
                'sort_order' => 0,
                'is_enabled' => true,
                'content' => [
                    'badge' => [
                        'nl' => 'Gratis aanmelden',
                        'en' => 'Free registration',
                        'de' => 'Kostenlose Anmeldung',
                        'fr' => 'Inscription gratuite',
                    ],
                    'title' => [
                        'nl' => 'Verkoop uw boot via Schepenkring',
                        'en' => 'Sell your boat through Schepenkring',
                        'de' => 'Verkaufen Sie Ihr Boot über Schepenkring',
                        'fr' => 'Vendez votre bateau via Schepenkring',
                    ],
                    'subtitle' => [
                        'nl' => 'Vul uw gegevens en bootinformatie in. Onze makelaar neemt binnen één werkdag contact met u op.',
                        'en' => 'Fill in your details and boat information. Our broker will contact you within one business day.',
                        'de' => 'Füllen Sie Ihre Daten und Bootsinformationen aus. Unser Makler meldet sich innerhalb eines Werktages.',
                        'fr' => 'Remplissez vos coordonnées et les informations de votre bateau. Notre courtier vous contactera dans un jour ouvrable.',
                    ],
                    'trust_items' => [
                        'nl' => ['Geen kosten vooraf', 'Gratis waardebepaling', '25+ jaar ervaring', 'Persoonlijk contact'],
                        'en' => ['No upfront costs', 'Free valuation', '25+ years experience', 'Personal service'],
                        'de' => ['Keine Vorabkosten', 'Kostenlose Bewertung', '25+ Jahre Erfahrung', 'Persönlicher Service'],
                        'fr' => ['Aucun frais initial', 'Évaluation gratuite', "25+ ans d'expérience", 'Service personnalisé'],
                    ],
                ],
            ],
            [
                'component' => 'FormBlock',
                'variant' => 'default',
                'sort_order' => 1,
                'is_enabled' => true,
                'content' => [
                    'title' => [
                        'nl' => 'Uw gegevens',
                        'en' => 'Your details',
                        'de' => 'Ihre Daten',
                        'fr' => 'Vos coordonnées',
                    ],
                    'form_key' => 'boat_intake',
                ],
            ],
        ], null, 'Seeded from existing BoatIntakePage.tsx hero content');

        app(CmsPageService::class)->publish($page->fresh(), null);
    }
}
