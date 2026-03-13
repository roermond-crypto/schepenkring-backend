<?php

namespace Tests\Feature\Api;

use App\Models\Yacht;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerateDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_description_returns_four_structured_languages_and_persists_french(): void
    {
        config()->set('services.openai.key', 'test-openai-key');
        config()->set('services.pinecone.key', null);
        config()->set('services.pinecone.host', null);

        $yacht = Yacht::create([
            'vessel_id' => (string) Str::uuid(),
            'boat_name' => 'Bayliner 3288',
            'manufacturer' => 'Bayliner',
            'model' => '3288',
            'year' => 1994,
            'loa' => 8.75,
            'beam' => 3.00,
            'draft' => 1.00,
            'fuel' => 'Diesel',
            'engine_manufacturer' => 'MerCruiser',
            'horse_power' => 260,
            'status' => 'draft',
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'nl' => 'De Bayliner 3288 uit 1994 biedt een toegankelijke combinatie van ruimte, comfort en overzicht aan boord. Met haar dieselmotorisatie en herkenbare indeling is dit een praktisch motorjacht voor ontspannen tochten. Aan dek voelt het schip overzichtelijk en uitnodigend aan. Binnen ontstaat een lichte leefruimte die prettig werkt voor dagtochten en langer verblijf. De techniek sluit aan op het gebruiksgemak van dit type schip. Daarmee is dit een interessant aanbod voor kopers die een degelijk familiejacht zoeken.',
                            'en' => 'This 1994 Bayliner 3288 combines practical space, onboard comfort, and approachable handling. With diesel power and a proven motor yacht layout, it offers a strong foundation for relaxed cruising. On deck the boat feels open and easy to manage. Inside, the living space supports weekends away as well as longer stays. The technical setup matches the straightforward character of the vessel. That makes it an appealing option for buyers looking for a capable family cruiser.',
                            'de' => 'Diese Bayliner 3288 aus dem Jahr 1994 verbindet Platz, Komfort und gutmütige Fahreigenschaften. Mit Dieselantrieb und bewährtem Motoryacht-Layout bietet sie eine solide Basis fuer entspannte Törns. An Deck wirkt das Schiff uebersichtlich und angenehm zu bewegen. Im Innenbereich entsteht ein heller Wohnraum fuer Wochenenden und laengere Aufenthalte. Auch die Technik passt zum unkomplizierten Charakter dieses Bootes. Damit ist sie ein interessantes Angebot fuer Familien und Genussfahrer.',
                            'fr' => 'Ce Bayliner 3288 de 1994 reunit espace pratique, confort a bord et prise en main rassurante. Avec sa motorisation diesel et son plan bien connu de motor-yacht, il constitue une base serieuse pour des navigations detendues. Sur le pont, le bateau parait clair et facile a exploiter. A l interieur, le volume de vie convient aussi bien aux sorties du week-end qu aux sejours plus longs. La partie technique reste coherente avec le caractere simple et fiable de l ensemble. Cela en fait une offre attractive pour un acheteur a la recherche d un cruiser familial.',
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/ai/generate-description', [
            'yacht_id' => $yacht->id,
            'tone' => 'professional',
            'min_words' => 200,
            'max_words' => 500,
            'form_values' => [
                'manufacturer' => 'Bayliner',
                'model' => '3288',
                'year' => 1994,
                'loa' => 8.75,
                'beam' => 3.00,
                'draft' => 1.00,
                'fuel' => 'Diesel',
                'engine_manufacturer' => 'MerCruiser',
                'horse_power' => 260,
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertStringContainsString('<h1>', (string) $response->json('descriptions.nl'));
        $this->assertStringContainsString('Bayliner 3288', (string) $response->json('descriptions.nl'));
        $this->assertStringContainsString('<h2>', (string) $response->json('descriptions.nl'));
        $this->assertStringContainsString('<h3>', (string) $response->json('descriptions.nl'));
        $this->assertStringContainsString('<h4>', (string) $response->json('descriptions.nl'));
        $this->assertStringContainsString('<ul>', (string) $response->json('descriptions.nl'));
        $this->assertStringContainsString('<h1>', (string) $response->json('descriptions.en'));
        $this->assertStringContainsString('Bayliner 3288', (string) $response->json('descriptions.en'));
        $this->assertStringContainsString('<ul>', (string) $response->json('descriptions.en'));
        $this->assertStringContainsString('<h1>', (string) $response->json('descriptions.de'));
        $this->assertStringContainsString('Bayliner 3288', (string) $response->json('descriptions.de'));
        $this->assertStringContainsString('<ul>', (string) $response->json('descriptions.de'));
        $this->assertStringContainsString('<h1>', (string) $response->json('descriptions.fr'));
        $this->assertStringContainsString('Bayliner 3288', (string) $response->json('descriptions.fr'));
        $this->assertStringContainsString('<ul>', (string) $response->json('descriptions.fr'));

        $yacht->refresh();

        $this->assertStringContainsString('<h1>', (string) $yacht->short_description_nl);
        $this->assertStringContainsString('Bayliner 3288', (string) $yacht->short_description_nl);
        $this->assertStringContainsString('<h4>', (string) $yacht->short_description_en);
        $this->assertStringContainsString('<ul>', (string) $yacht->short_description_de);
        $this->assertStringContainsString('<ul>', (string) $yacht->short_description_fr);
        $this->assertNotEmpty($yacht->short_description_fr);

        Http::assertSentCount(1);
    }
}
