<?php

namespace Database\Seeders;

use App\Models\KycQuestionTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KycQuestionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing questions before re-seeding
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        KycQuestionTemplate::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $sort = 0;

        $questions = [
            // ── Klant ────────────────────────────────────────────
            [
                'section'     => 'Klant',
                'question'    => 'Cliënt is een natuurlijk persoon.',
                'action'      => 'Kopie van identiteitsbewijs.',
                'field_type'  => 'yes_no',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => true,
            ],
            [
                'section'     => 'Klant',
                'question'    => 'Cliënt is een niet-natuurlijk persoon (rechtspersoon/bedrijf).',
                'action'      => 'UBO vaststellen. Vraag uittreksel KvK en identificatie UBO.',
                'field_type'  => 'yes_no',
                'risk_points' => 20,
                'risk_flag'   => 'warning',
                'required'    => true,
            ],
            [
                'section'     => 'Klant',
                'question'    => 'Cliënt is een Politiek Prominent Persoon (PEP).',
                'action'      => 'Verscherpt cliëntenonderzoek. Senior management toestemming vereist.',
                'field_type'  => 'yes_no',
                'risk_points' => 40,
                'risk_flag'   => 'critical',
                'required'    => true,
            ],
            [
                'section'     => 'Klant',
                'question'    => 'Cliënt heeft een andere naam dan degene die de onderhandeling voert.',
                'action'      => 'Stel vast of de naamgestelde de cliënt is. Leg relatie vast.',
                'field_type'  => 'yes_no',
                'risk_points' => 20,
                'risk_flag'   => 'warning',
                'required'    => false,
            ],
            [
                'section'     => 'Klant',
                'question'    => 'Cliënt blijkt aan de betalende en gefactureerde partij te verschillen.',
                'action'      => 'Stel vast wat de relatie is tussen koper en betaler.',
                'field_type'  => 'yes_no',
                'risk_points' => 30,
                'risk_flag'   => 'warning',
                'required'    => false,
            ],
            [
                'section'     => 'Klant',
                'question'    => 'Grote contante aankoop in relatie tot leeftijd of inkomen van cliënt.',
                'action'      => 'Onderzoek herkomst geld. Vraag bewijsstukken op.',
                'field_type'  => 'yes_no',
                'risk_points' => 30,
                'risk_flag'   => 'warning',
                'required'    => false,
            ],
            [
                'section'     => 'Klant',
                'question'    => 'Cliënt wenst niet van de koop af te zien nadat er om identificatie is gevraagd.',
                'action'      => 'Meld op basis van subjectieve indicator.',
                'field_type'  => 'yes_no',
                'risk_points' => 100,
                'risk_flag'   => 'blocking',
                'required'    => true,
            ],
            [
                'section'     => 'Klant',
                'question'    => 'Cliënt wil contante aankoop ruilen of terugbrengen voor het aangekochte vaartuig.',
                'action'      => 'Vraag naar reden. Bij onduidelijke of ontwijkende beantwoording melden.',
                'field_type'  => 'yes_no',
                'risk_points' => 50,
                'risk_flag'   => 'critical',
                'required'    => false,
            ],
            [
                'section'     => 'Klant',
                'question'    => 'Pleziervaartuig met een lengte van minimaal 24 meter.',
                'action'      => 'Uitgebreid cliëntenonderzoek vereist bij hoge waarde.',
                'field_type'  => 'yes_no',
                'risk_points' => 10,
                'risk_flag'   => 'warning',
                'required'    => false,
            ],
            [
                'section'     => 'Klant',
                'question'    => 'Object wordt opgesplitst in meerdere transacties.',
                'action'      => 'Geen voor de hand liggende zakelijke of rechtmatige reden. Meld als verdacht.',
                'field_type'  => 'yes_no',
                'risk_points' => 60,
                'risk_flag'   => 'critical',
                'required'    => false,
            ],

            // ── Dienst ───────────────────────────────────────────
            [
                'section'     => 'Dienst',
                'question'    => 'Transactie gaat niet door omdat cliënt contant wil betalen en vermoedt dat de transactie gemeld gaat worden.',
                'action'      => 'Nadere vragen stellen en eventueel opname bronnen raadplegen. Afhankelijk van uitkomst melden.',
                'field_type'  => 'yes_no',
                'risk_points' => 80,
                'risk_flag'   => 'critical',
                'required'    => false,
            ],
            [
                'section'     => 'Dienst',
                'question'    => 'Er worden contante betalingen vooraf, deelbetalingen en/of depot-gelden geaccepteerd.',
                'action'      => 'Zorg voor goede registratie van deelbetalingen. Indien ontvangen bedrag ≥ €10.000 melden.',
                'field_type'  => 'yes_no',
                'risk_points' => 30,
                'risk_flag'   => 'warning',
                'required'    => false,
            ],
            [
                'section'     => 'Dienst',
                'question'    => 'Er wordt meegewerkt aan het opknippen van één transactie in meerdere transacties.',
                'action'      => 'Indien vermoeden dat opknippen wordt gedaan om melden te voorkomen: melden.',
                'field_type'  => 'yes_no',
                'risk_points' => 70,
                'risk_flag'   => 'blocking',
                'required'    => false,
            ],

            // ── Transactie en betalingskanaal ─────────────────────
            [
                'section'     => 'Transactie en betalingskanaal',
                'question'    => 'Transactie waarbij contante deel niet onder de €10.000 blijft en restant per bank/pin betaald wordt.',
                'action'      => 'Procedure bij combinatie contant/bank/pin volgen.',
                'field_type'  => 'yes_no',
                'risk_points' => 50,
                'risk_flag'   => 'warning',
                'required'    => false,
            ],
            [
                'section'     => 'Transactie en betalingskanaal',
                'question'    => 'Inkoop contant van €10.000 of meer.',
                'action'      => 'Stel vast waarom verkoper contant betaald wil worden. Identificatie als contante betaling.',
                'field_type'  => 'yes_no',
                'risk_points' => 50,
                'risk_flag'   => 'critical',
                'required'    => true,
            ],
            [
                'section'     => 'Transactie en betalingskanaal',
                'question'    => 'Betaling ≥ €10.000 in coupures van €500 of vrijwel uitsluitend zeer kleine coupures.',
                'action'      => 'Bij coupures van €500 of zeer kleine coupures altijd subjectief melden (witwasvermoeden).',
                'field_type'  => 'yes_no',
                'risk_points' => 60,
                'risk_flag'   => 'critical',
                'required'    => false,
            ],
            [
                'section'     => 'Transactie en betalingskanaal',
                'question'    => 'Transactie van ≥ €20.000 waarbij contante deel net onder €20.000 blijft en restant per bank/pin betaald wordt.',
                'action'      => 'Procedure combinatie contant/bankbetaling. Risico van ontwijken melding groot. Vrijwel zeker melden.',
                'field_type'  => 'yes_no',
                'risk_points' => 70,
                'risk_flag'   => 'critical',
                'required'    => false,
            ],

            // ── Betaling in cryptomunten/debitcards ───────────────
            [
                'section'     => 'Betaling in cryptomunten/debitcards',
                'question'    => 'Betaling (geheel of gedeeltelijk) via cryptomunten of prepaid debitcards.',
                'action'      => 'Nadere vragen stellen over herkomst vermogen. Melden indien onvoldoende onderbouwing.',
                'field_type'  => 'yes_no',
                'risk_points' => 20,
                'risk_flag'   => 'warning',
                'required'    => true,
            ],
            [
                'section'     => 'Betaling in cryptomunten/debitcards',
                'question'    => 'Meerdere vaartuigonderdelen die samenhangen hebben gezamenlijk factuurbedrag rond de grens van €10.000 contant.',
                'action'      => 'Koppel betaling aan de transactie. Behandel als één samengestelde transactie.',
                'field_type'  => 'yes_no',
                'risk_points' => 50,
                'risk_flag'   => 'warning',
                'required'    => false,
            ],
            [
                'section'     => 'Betaling in cryptomunten/debitcards',
                'question'    => 'Cash payment order gefactureerd op 1 koper voor alle vaartuigonderdelen waarbij dezelfde grenswaarde wordt overschreden.',
                'action'      => 'Sprake van samenstelling. Stel vast of sprake is van witwassen.',
                'field_type'  => 'yes_no',
                'risk_points' => 60,
                'risk_flag'   => 'critical',
                'required'    => false,
            ],

            // ── Risico ────────────────────────────────────────────
            [
                'section'     => 'Risico',
                'question'    => 'Er is sprake van een onacceptabel risico, bijvoorbeeld strafbaar feit of cliënt weigert zich te identificeren.',
                'action'      => 'Het is verboden de transactie te verrichten. Meld subjectief op basis van bekende gegevens.',
                'field_type'  => 'yes_no',
                'risk_points' => 100,
                'risk_flag'   => 'blocking',
                'required'    => true,
            ],

            // ── Vestigingen ───────────────────────────────────────
            [
                'section'     => 'Vestigingen',
                'question'    => 'Er zijn meerdere vestigingen waar cliënten gelijktijdig vaartuigonderdelen aan kunnen schaffen.',
                'action'      => 'Routine opnemen om vast te stellen zodra betalingen leiden tot toepasselijkheid samengestelde transactie.',
                'field_type'  => 'yes_no',
                'risk_points' => 30,
                'risk_flag'   => 'warning',
                'required'    => false,
            ],

            // ── Afhalen ───────────────────────────────────────────
            [
                'section'     => 'Afhalen',
                'question'    => 'Afhalen van het vaartuig gebeurt door een ander dan de koper.',
                'action'      => 'Kopie identiteitsbewijs van afhaler vragen en relatie tot koper vastleggen.',
                'field_type'  => 'yes_no',
                'risk_points' => 15,
                'risk_flag'   => 'warning',
                'required'    => false,
            ],
            [
                'section'     => 'Afhalen',
                'question'    => 'Naam en relatie tot koper van degene die afhaalt.',
                'action'      => 'Vastleggen in dossier.',
                'field_type'  => 'text',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => false,
                // Conditional: only show if previous yes_no = yes (set after seeding)
            ],

            // ── Landen ────────────────────────────────────────────
            [
                'section'     => 'Landen',
                'question'    => 'Cliënt of vermogensadres buiten Nederland.',
                'action'      => 'Fysiek aanwezig zijn van cliënt. Schep zijn op herkomst contant geld.',
                'field_type'  => 'yes_no',
                'risk_points' => 15,
                'risk_flag'   => 'warning',
                'required'    => true,
            ],
            [
                'section'     => 'Landen',
                'question'    => 'Land van verblijf / herkomst cliënt.',
                'action'      => 'Vastleggen voor sanctietoets.',
                'field_type'  => 'country',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => false,
            ],
            [
                'section'     => 'Landen',
                'question'    => 'Cliënt is afkomstig uit een land op de sanctielijst.',
                'action'      => 'Verscherpt cliëntenonderzoek. Indien levering naar dat land of aan iemand uit dat land: transactie blokkeren.',
                'field_type'  => 'yes_no',
                'risk_points' => 100,
                'risk_flag'   => 'blocking',
                'required'    => true,
            ],
            [
                'section'     => 'Landen',
                'question'    => 'Vaartuig wordt geleverd / afgehaald in het buitenland.',
                'action'      => 'Vastleggen bestemming en eventuele exportprocedure.',
                'field_type'  => 'yes_no',
                'risk_points' => 10,
                'risk_flag'   => 'none',
                'required'    => false,
            ],

            // ── Identiteit ────────────────────────────────────────
            [
                'section'     => 'Identiteit',
                'question'    => 'Identiteitsbewijs koper geverifieerd.',
                'action'      => 'Upload kopie geldig identiteitsbewijs.',
                'field_type'  => 'yes_no',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => true,
            ],
            [
                'section'     => 'Identiteit',
                'question'    => 'Type identiteitsbewijs.',
                'action'      => 'Paspoort, rijbewijs of ID-kaart.',
                'field_type'  => 'dropdown',
                'options'     => ['Paspoort', 'Rijbewijs', 'ID-kaart', 'Verblijfsvergunning'],
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => true,
            ],
            [
                'section'     => 'Identiteit',
                'question'    => 'Documentnummer identiteitsbewijs.',
                'action'      => 'Vastleggen voor dossier.',
                'field_type'  => 'text',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => true,
            ],
            [
                'section'     => 'Identiteit',
                'question'    => 'Geboortedatum cliënt.',
                'action'      => 'Vastleggen voor dossier.',
                'field_type'  => 'date',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => true,
            ],
            [
                'section'     => 'Identiteit',
                'question'    => 'Identiteitsgegevens komen overeen met overige documentatie.',
                'action'      => 'Controleer op naamsverschillen, geboortedatum etc.',
                'field_type'  => 'yes_no',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => true,
            ],
            [
                'section'     => 'Identiteit',
                'question'    => 'Er is sprake van een identiteitsmismatch.',
                'action'      => 'Nader onderzoek vereist. Document vraagtekens vastleggen.',
                'field_type'  => 'yes_no',
                'risk_points' => 40,
                'risk_flag'   => 'critical',
                'required'    => false,
            ],

            // ── Betaling ─────────────────────────────────────────
            [
                'section'     => 'Betaling',
                'question'    => 'Betaalmethode.',
                'action'      => 'Vastleggen voor dossier.',
                'field_type'  => 'dropdown',
                'options'     => ['Bankoverschrijving', 'Contant', 'Combinatie contant/bank', 'Crypto', 'Debitcard', 'Anders'],
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => true,
            ],
            [
                'section'     => 'Betaling',
                'question'    => 'Contant bedrag (in euro\'s).',
                'action'      => 'Indien ≥ €10.000: meldplicht. Vastleggen voor dossier.',
                'field_type'  => 'money',
                'risk_points' => 50,
                'risk_flag'   => 'critical',
                'required'    => false,
            ],
            [
                'section'     => 'Betaling',
                'question'    => 'Herkomst van het contante geld toegelicht.',
                'action'      => 'Vastleggen verklaring en bewijsstukken.',
                'field_type'  => 'textarea',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => false,
            ],
            [
                'section'     => 'Betaling',
                'question'    => 'IBAN van de koper.',
                'action'      => 'Vastleggen voor dossier.',
                'field_type'  => 'text',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => false,
            ],

            // ── Compliance ────────────────────────────────────────
            [
                'section'     => 'Compliance',
                'question'    => 'Transactie is getoetst aan sanctielijsten (EU, VN, OFAC).',
                'action'      => 'Documenteer toetsing. Screenshot of referentie vastleggen.',
                'field_type'  => 'yes_no',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => true,
            ],
            [
                'section'     => 'Compliance',
                'question'    => 'Melding gedaan bij FIU-Nederland.',
                'action'      => 'Vastleggen datum en referentienummer FIU-melding.',
                'field_type'  => 'yes_no',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => false,
            ],
            [
                'section'     => 'Compliance',
                'question'    => 'Referentienummer FIU-melding.',
                'action'      => 'Vastleggen in dossier.',
                'field_type'  => 'text',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => false,
            ],
            [
                'section'     => 'Compliance',
                'question'    => 'Aanvullende opmerkingen compliance officer.',
                'action'      => 'Vrije tekst voor bijzonderheden.',
                'field_type'  => 'textarea',
                'risk_points' => 0,
                'risk_flag'   => 'none',
                'required'    => false,
            ],
        ];

        foreach ($questions as $q) {
            KycQuestionTemplate::create(array_merge($q, ['sort_order' => $sort++]));
        }

        $this->command->info('KYC questions seeded: ' . count($questions) . ' questions across ' . count(array_unique(array_column($questions, 'section'))) . ' sections.');
    }
}
