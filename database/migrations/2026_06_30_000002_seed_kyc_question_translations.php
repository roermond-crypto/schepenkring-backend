<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TRANSLATIONS = [
        // ── Section: Klant ────────────────────────────────────────
        'Cliënt is een natuurlijk persoon.' => [
            'section' => ['en' => 'Customer', 'de' => 'Kunde', 'fr' => 'Client'],
            'question' => [
                'en' => 'Client is a natural person.',
                'de' => 'Der Kunde ist eine natürliche Person.',
                'fr' => 'Le client est une personne physique.',
            ],
            'action' => [
                'en' => 'Copy of identity document.',
                'de' => 'Kopie des Ausweisdokuments.',
                'fr' => 'Copie de la pièce d\'identité.',
            ],
        ],
        'Cliënt is een niet-natuurlijk persoon (rechtspersoon/bedrijf).' => [
            'section' => ['en' => 'Customer', 'de' => 'Kunde', 'fr' => 'Client'],
            'question' => [
                'en' => 'Client is a legal entity (corporation/company).',
                'de' => 'Der Kunde ist eine juristische Person (Kapitalgesellschaft/Unternehmen).',
                'fr' => 'Le client est une personne morale (société/entreprise).',
            ],
            'action' => [
                'en' => 'Establish UBO. Request chamber of commerce extract and UBO identification.',
                'de' => 'UBO feststellen. Handelsregisterauszug und UBO-Identifizierung anfordern.',
                'fr' => 'Établir le BEF. Demander l\'extrait de registre de commerce et l\'identification du BEF.',
            ],
        ],
        'Cliënt is een Politiek Prominent Persoon (PEP).' => [
            'section' => ['en' => 'Customer', 'de' => 'Kunde', 'fr' => 'Client'],
            'question' => [
                'en' => 'Client is a Politically Exposed Person (PEP).',
                'de' => 'Der Kunde ist eine politisch exponierte Person (PEP).',
                'fr' => 'Le client est une Personne Politiquement Exposée (PPE).',
            ],
            'action' => [
                'en' => 'Enhanced customer due diligence. Senior management approval required.',
                'de' => 'Erweiterte Sorgfaltspflicht. Genehmigung des Senior Managements erforderlich.',
                'fr' => 'Diligence renforcée. Approbation de la direction générale requise.',
            ],
        ],
        'Cliënt heeft een andere naam dan degene die de onderhandeling voert.' => [
            'section' => ['en' => 'Customer', 'de' => 'Kunde', 'fr' => 'Client'],
            'question' => [
                'en' => 'Client has a different name than the person conducting the negotiation.',
                'de' => 'Der Kunde hat einen anderen Namen als die verhandelnde Person.',
                'fr' => 'Le client a un nom différent de celui qui mène la négociation.',
            ],
            'action' => [
                'en' => 'Establish whether the named party is the client. Record the relationship.',
                'de' => 'Feststellen, ob die genannte Person der Kunde ist. Beziehung dokumentieren.',
                'fr' => 'Vérifier si la personne désignée est le client. Documenter la relation.',
            ],
        ],
        'Cliënt blijkt aan de betalende en gefactureerde partij te verschillen.' => [
            'section' => ['en' => 'Customer', 'de' => 'Kunde', 'fr' => 'Client'],
            'question' => [
                'en' => 'The client appears to differ from the paying and invoiced party.',
                'de' => 'Der Kunde scheint sich von der zahlenden und der fakturierten Partei zu unterscheiden.',
                'fr' => 'Le client semble différer de la partie payante et de la partie facturée.',
            ],
            'action' => [
                'en' => 'Establish the relationship between the buyer and the payer.',
                'de' => 'Beziehung zwischen Käufer und Zahler feststellen.',
                'fr' => 'Établir la relation entre l\'acheteur et le payeur.',
            ],
        ],
        'Grote contante aankoop in relatie tot leeftijd of inkomen van cliënt.' => [
            'section' => ['en' => 'Customer', 'de' => 'Kunde', 'fr' => 'Client'],
            'question' => [
                'en' => 'Large cash purchase in relation to client\'s age or income.',
                'de' => 'Großer Barkauf im Verhältnis zum Alter oder Einkommen des Kunden.',
                'fr' => 'Achat important en espèces par rapport à l\'âge ou au revenu du client.',
            ],
            'action' => [
                'en' => 'Investigate origin of funds. Request supporting documents.',
                'de' => 'Herkunft der Gelder untersuchen. Belege anfordern.',
                'fr' => 'Enquêter sur l\'origine des fonds. Demander des pièces justificatives.',
            ],
        ],
        'Cliënt wenst niet van de koop af te zien nadat er om identificatie is gevraagd.' => [
            'section' => ['en' => 'Customer', 'de' => 'Kunde', 'fr' => 'Client'],
            'question' => [
                'en' => 'Client refuses to abandon the purchase after being asked for identification.',
                'de' => 'Der Kunde weigert sich, den Kauf aufzugeben, nachdem er nach einem Ausweis gefragt wurde.',
                'fr' => 'Le client refuse d\'abandonner l\'achat après avoir été demandé de s\'identifier.',
            ],
            'action' => [
                'en' => 'Report based on subjective indicator.',
                'de' => 'Meldung auf Basis eines subjektiven Indikators.',
                'fr' => 'Signaler sur la base d\'un indicateur subjectif.',
            ],
        ],
        'Cliënt wil contante aankoop ruilen of terugbrengen voor het aangekochte vaartuig.' => [
            'section' => ['en' => 'Customer', 'de' => 'Kunde', 'fr' => 'Client'],
            'question' => [
                'en' => 'Client wants to exchange or return a cash purchase for the purchased vessel.',
                'de' => 'Der Kunde möchte einen Barkauf für das erworbene Schiff eintauschen oder zurückgeben.',
                'fr' => 'Le client souhaite échanger ou retourner un achat en espèces pour le navire acheté.',
            ],
            'action' => [
                'en' => 'Ask for reason. Report if answer is unclear or evasive.',
                'de' => 'Grund erfragen. Bei unklarer oder ausweichender Antwort melden.',
                'fr' => 'Demander la raison. Signaler si la réponse est peu claire ou évasive.',
            ],
        ],
        'Pleziervaartuig met een lengte van minimaal 24 meter.' => [
            'section' => ['en' => 'Customer', 'de' => 'Kunde', 'fr' => 'Client'],
            'question' => [
                'en' => 'Pleasure vessel with a length of at least 24 metres.',
                'de' => 'Freizeitfahrzeug mit einer Länge von mindestens 24 Metern.',
                'fr' => 'Bateau de plaisance d\'une longueur d\'au moins 24 mètres.',
            ],
            'action' => [
                'en' => 'Extended customer due diligence required for high-value vessels.',
                'de' => 'Erweiterte Sorgfaltspflicht bei hochwertigen Schiffen erforderlich.',
                'fr' => 'Diligence renforcée requise pour les navires de grande valeur.',
            ],
        ],
        'Object wordt opgesplitst in meerdere transacties.' => [
            'section' => ['en' => 'Customer', 'de' => 'Kunde', 'fr' => 'Client'],
            'question' => [
                'en' => 'Object is being split into multiple transactions.',
                'de' => 'Das Objekt wird in mehrere Transaktionen aufgeteilt.',
                'fr' => 'L\'objet est divisé en plusieurs transactions.',
            ],
            'action' => [
                'en' => 'No obvious business or legitimate reason. Report as suspicious.',
                'de' => 'Kein offensichtlicher geschäftlicher oder legitimer Grund. Als verdächtig melden.',
                'fr' => 'Aucune raison commerciale ou légitime évidente. Signaler comme suspect.',
            ],
        ],

        // ── Section: Dienst ──────────────────────────────────────
        'Transactie gaat niet door omdat cliënt contant wil betalen en vermoedt dat de transactie gemeld gaat worden.' => [
            'section' => ['en' => 'Service', 'de' => 'Dienst', 'fr' => 'Service'],
            'question' => [
                'en' => 'Transaction falls through because client wants to pay cash and suspects the transaction will be reported.',
                'de' => 'Transaktion scheitert, weil der Kunde bar zahlen möchte und vermutet, dass die Transaktion gemeldet wird.',
                'fr' => 'La transaction échoue car le client souhaite payer en espèces et soupçonne que la transaction sera signalée.',
            ],
            'action' => [
                'en' => 'Ask further questions and consult source records if needed. Report depending on outcome.',
                'de' => 'Weitere Fragen stellen und ggf. Quelldokumente konsultieren. Je nach Ergebnis melden.',
                'fr' => 'Poser des questions supplémentaires et consulter les sources si nécessaire. Signaler selon le résultat.',
            ],
        ],
        'Er worden contante betalingen vooraf, deelbetalingen en/of depot-gelden geaccepteerd.' => [
            'section' => ['en' => 'Service', 'de' => 'Dienst', 'fr' => 'Service'],
            'question' => [
                'en' => 'Advance cash payments, instalments and/or deposit funds are accepted.',
                'de' => 'Vorauszahlungen in bar, Teilzahlungen und/oder Depot-Gelder werden akzeptiert.',
                'fr' => 'Des paiements anticipés en espèces, des versements et/ou des dépôts sont acceptés.',
            ],
            'action' => [
                'en' => 'Ensure good registration of instalments. If amount received ≥ €10,000: report.',
                'de' => 'Gute Erfassung der Teilzahlungen sicherstellen. Wenn erhaltener Betrag ≥ €10.000: melden.',
                'fr' => 'Assurer une bonne enregistrement des versements. Si montant reçu ≥ €10 000 : signaler.',
            ],
        ],
        'Er wordt meegewerkt aan het opknippen van één transactie in meerdere transacties.' => [
            'section' => ['en' => 'Service', 'de' => 'Dienst', 'fr' => 'Service'],
            'question' => [
                'en' => 'Cooperation is being given to splitting one transaction into multiple transactions.',
                'de' => 'Es wird mitgeholfen, eine Transaktion in mehrere Transaktionen aufzuteilen.',
                'fr' => 'Une coopération est apportée pour diviser une transaction en plusieurs transactions.',
            ],
            'action' => [
                'en' => 'If there is suspicion that splitting is done to avoid reporting: report.',
                'de' => 'Wenn der Verdacht besteht, dass die Aufteilung zur Meldungsumgehung dient: melden.',
                'fr' => 'En cas de soupçon que la division est faite pour éviter le signalement : signaler.',
            ],
        ],

        // ── Section: Transactie en betalingskanaal ───────────────
        'Transactie waarbij contante deel niet onder de €10.000 blijft en restant per bank/pin betaald wordt.' => [
            'section' => ['en' => 'Transaction and payment channel', 'de' => 'Transaktion und Zahlungskanal', 'fr' => 'Transaction et canal de paiement'],
            'question' => [
                'en' => 'Transaction where the cash portion does not remain below €10,000 and the remainder is paid by bank transfer/card.',
                'de' => 'Transaktion, bei der der Bargeldanteil nicht unter €10.000 bleibt und der Rest per Banküberweisung/Karte bezahlt wird.',
                'fr' => 'Transaction où la partie en espèces ne reste pas inférieure à 10 000 € et le reste est payé par virement/carte.',
            ],
            'action' => [
                'en' => 'Follow the procedure for combined cash/bank/card payments.',
                'de' => 'Verfahren für kombinierte Bar-/Bank-/Kartenzahlungen befolgen.',
                'fr' => 'Suivre la procédure pour les paiements combinés espèces/banque/carte.',
            ],
        ],
        'Inkoop contant van €10.000 of meer.' => [
            'section' => ['en' => 'Transaction and payment channel', 'de' => 'Transaktion und Zahlungskanal', 'fr' => 'Transaction et canal de paiement'],
            'question' => [
                'en' => 'Cash purchase of €10,000 or more.',
                'de' => 'Bareinkauf von €10.000 oder mehr.',
                'fr' => 'Achat en espèces de 10 000 € ou plus.',
            ],
            'action' => [
                'en' => 'Establish why the seller wants to be paid in cash. Identification required for cash payment.',
                'de' => 'Feststellen, warum der Verkäufer bar bezahlt werden möchte. Identifikation bei Barzahlung.',
                'fr' => 'Établir pourquoi le vendeur souhaite être payé en espèces. Identification requise pour paiement en espèces.',
            ],
        ],
        'Betaling ≥ €10.000 in coupures van €500 of vrijwel uitsluitend zeer kleine coupures.' => [
            'section' => ['en' => 'Transaction and payment channel', 'de' => 'Transaktion und Zahlungskanal', 'fr' => 'Transaction et canal de paiement'],
            'question' => [
                'en' => 'Payment ≥ €10,000 in €500 notes or almost exclusively very small denominations.',
                'de' => 'Zahlung ≥ €10.000 in €500-Scheinen oder fast ausschließlich sehr kleinen Stückelungen.',
                'fr' => 'Paiement ≥ 10 000 € en billets de 500 € ou presque exclusivement en très petites coupures.',
            ],
            'action' => [
                'en' => 'For €500 notes or very small denominations always report subjectively (money laundering suspicion).',
                'de' => 'Bei €500-Scheinen oder sehr kleinen Stückelungen immer subjektiv melden (Geldwäscheverdacht).',
                'fr' => 'Pour les billets de 500 € ou très petites coupures, toujours signaler subjectivement (soupçon de blanchiment).',
            ],
        ],
        'Transactie van ≥ €20.000 waarbij contante deel net onder €20.000 blijft en restant per bank/pin betaald wordt.' => [
            'section' => ['en' => 'Transaction and payment channel', 'de' => 'Transaktion und Zahlungskanal', 'fr' => 'Transaction et canal de paiement'],
            'question' => [
                'en' => 'Transaction of ≥ €20,000 where the cash portion stays just below €20,000 and the remainder is paid by bank/card.',
                'de' => 'Transaktion von ≥ €20.000, bei der der Bargeldanteil knapp unter €20.000 bleibt und der Rest per Bank/Karte bezahlt wird.',
                'fr' => 'Transaction de ≥ 20 000 € où la partie espèces reste juste sous 20 000 € et le reste est payé par banque/carte.',
            ],
            'action' => [
                'en' => 'Combined cash/bank payment procedure. High risk of evading report. Almost certainly report.',
                'de' => 'Verfahren für kombinierte Bar-/Bankzahlung. Hohes Risiko der Meldungsumgehung. Fast sicher melden.',
                'fr' => 'Procédure de paiement combiné espèces/banque. Risque élevé d\'évitement du signalement. Signaler quasi certainement.',
            ],
        ],

        // ── Section: Betaling in cryptomunten/debitcards ─────────
        'Betaling (geheel of gedeeltelijk) via cryptomunten of prepaid debitcards.' => [
            'section' => ['en' => 'Payment in cryptocurrency/debit cards', 'de' => 'Zahlung in Kryptowährungen/Debitkarten', 'fr' => 'Paiement en crypto-monnaies/cartes de débit'],
            'question' => [
                'en' => 'Payment (wholly or partially) via cryptocurrency or prepaid debit cards.',
                'de' => 'Zahlung (ganz oder teilweise) über Kryptowährungen oder Prepaid-Debitkarten.',
                'fr' => 'Paiement (total ou partiel) via des crypto-monnaies ou des cartes de débit prépayées.',
            ],
            'action' => [
                'en' => 'Ask further questions about the origin of assets. Report if insufficient justification.',
                'de' => 'Weitere Fragen zur Herkunft des Vermögens stellen. Bei unzureichender Begründung melden.',
                'fr' => 'Poser des questions supplémentaires sur l\'origine des actifs. Signaler si justification insuffisante.',
            ],
        ],
        'Meerdere vaartuigonderdelen die samenhangen hebben gezamenlijk factuurbedrag rond de grens van €10.000 contant.' => [
            'section' => ['en' => 'Payment in cryptocurrency/debit cards', 'de' => 'Zahlung in Kryptowährungen/Debitkarten', 'fr' => 'Paiement en crypto-monnaies/cartes de débit'],
            'question' => [
                'en' => 'Multiple related vessel parts have a combined invoice amount around the €10,000 cash threshold.',
                'de' => 'Mehrere zusammenhängende Schiffsteile haben einen kombinierten Rechnungsbetrag um die €10.000-Bargeldgrenze.',
                'fr' => 'Plusieurs pièces de navire liées ont un montant de facture combiné autour du seuil de 10 000 € en espèces.',
            ],
            'action' => [
                'en' => 'Link the payment to the transaction. Treat as one composite transaction.',
                'de' => 'Zahlung mit der Transaktion verknüpfen. Als eine zusammengesetzte Transaktion behandeln.',
                'fr' => 'Lier le paiement à la transaction. Traiter comme une transaction composite unique.',
            ],
        ],
        'Cash payment order gefactureerd op 1 koper voor alle vaartuigonderdelen waarbij dezelfde grenswaarde wordt overschreden.' => [
            'section' => ['en' => 'Payment in cryptocurrency/debit cards', 'de' => 'Zahlung in Kryptowährungen/Debitkarten', 'fr' => 'Paiement en crypto-monnaies/cartes de débit'],
            'question' => [
                'en' => 'Cash payment order invoiced to 1 buyer for all vessel parts where the same threshold is exceeded.',
                'de' => 'Barzahlungsauftrag auf 1 Käufer für alle Schiffsteile fakturiert, wobei derselbe Grenzwert überschritten wird.',
                'fr' => 'Ordre de paiement en espèces facturé à 1 acheteur pour toutes les pièces de navire avec le même seuil dépassé.',
            ],
            'action' => [
                'en' => 'There is a composite situation. Establish whether money laundering is involved.',
                'de' => 'Es liegt eine Zusammensetzung vor. Feststellen, ob Geldwäsche vorliegt.',
                'fr' => 'Il s\'agit d\'une composition. Établir s\'il y a blanchiment d\'argent.',
            ],
        ],

        // ── Section: Risico ──────────────────────────────────────
        'Er is sprake van een onacceptabel risico, bijvoorbeeld strafbaar feit of cliënt weigert zich te identificeren.' => [
            'section' => ['en' => 'Risk', 'de' => 'Risiko', 'fr' => 'Risque'],
            'question' => [
                'en' => 'There is an unacceptable risk, e.g. a criminal offence or the client refuses to identify themselves.',
                'de' => 'Es besteht ein inakzeptables Risiko, z.B. eine Straftat oder der Kunde weigert sich zu identifizieren.',
                'fr' => 'Il existe un risque inacceptable, par ex. une infraction pénale ou le client refuse de s\'identifier.',
            ],
            'action' => [
                'en' => 'The transaction is prohibited. Report subjectively based on known data.',
                'de' => 'Die Transaktion ist verboten. Auf Basis bekannter Daten subjektiv melden.',
                'fr' => 'La transaction est interdite. Signaler subjectivement sur la base des données connues.',
            ],
        ],

        // ── Section: Vestigingen ─────────────────────────────────
        'Er zijn meerdere vestigingen waar cliënten gelijktijdig vaartuigonderdelen aan kunnen schaffen.' => [
            'section' => ['en' => 'Branches', 'de' => 'Filialen', 'fr' => 'Succursales'],
            'question' => [
                'en' => 'There are multiple locations where clients can simultaneously purchase vessel parts.',
                'de' => 'Es gibt mehrere Standorte, an denen Kunden gleichzeitig Schiffsteile kaufen können.',
                'fr' => 'Il existe plusieurs sites où les clients peuvent simultanément acheter des pièces de navire.',
            ],
            'action' => [
                'en' => 'Establish a routine to determine when payments lead to applicability of composite transactions.',
                'de' => 'Routine einrichten, um festzustellen, wann Zahlungen zur Anwendbarkeit zusammengesetzter Transaktionen führen.',
                'fr' => 'Établir une routine pour déterminer quand les paiements entraînent l\'application des transactions composites.',
            ],
        ],

        // ── Section: Afhalen ─────────────────────────────────────
        'Afhalen van het vaartuig gebeurt door een ander dan de koper.' => [
            'section' => ['en' => 'Collection', 'de' => 'Abholung', 'fr' => 'Retrait'],
            'question' => [
                'en' => 'The vessel is collected by someone other than the buyer.',
                'de' => 'Das Fahrzeug wird von jemand anderem als dem Käufer abgeholt.',
                'fr' => 'Le navire est récupéré par quelqu\'un d\'autre que l\'acheteur.',
            ],
            'action' => [
                'en' => 'Request copy of ID of the collector and record their relationship to the buyer.',
                'de' => 'Kopie des Ausweises des Abholers anfordern und Beziehung zum Käufer dokumentieren.',
                'fr' => 'Demander copie de la pièce d\'identité du récupérateur et noter sa relation avec l\'acheteur.',
            ],
        ],
        'Naam en relatie tot koper van degene die afhaalt.' => [
            'section' => ['en' => 'Collection', 'de' => 'Abholung', 'fr' => 'Retrait'],
            'question' => [
                'en' => 'Name and relationship to the buyer of the person collecting.',
                'de' => 'Name und Beziehung zum Käufer der abholenden Person.',
                'fr' => 'Nom et relation à l\'acheteur de la personne qui récupère.',
            ],
            'action' => [
                'en' => 'Record in file.',
                'de' => 'In der Akte dokumentieren.',
                'fr' => 'Consigner dans le dossier.',
            ],
        ],

        // ── Section: Landen ──────────────────────────────────────
        'Cliënt of vermogensadres buiten Nederland.' => [
            'section' => ['en' => 'Countries', 'de' => 'Länder', 'fr' => 'Pays'],
            'question' => [
                'en' => 'Client or asset address outside the Netherlands.',
                'de' => 'Kunde oder Vermögensadresse außerhalb der Niederlande.',
                'fr' => 'Le client ou l\'adresse des actifs est en dehors des Pays-Bas.',
            ],
            'action' => [
                'en' => 'Client must be physically present. Clarify origin of cash.',
                'de' => 'Kunde muss physisch anwesend sein. Herkunft des Bargeldes klären.',
                'fr' => 'Le client doit être physiquement présent. Clarifier l\'origine des espèces.',
            ],
        ],
        'Land van verblijf / herkomst cliënt.' => [
            'section' => ['en' => 'Countries', 'de' => 'Länder', 'fr' => 'Pays'],
            'question' => [
                'en' => 'Country of residence / origin of client.',
                'de' => 'Land des Aufenthalts / Herkunft des Kunden.',
                'fr' => 'Pays de résidence / origine du client.',
            ],
            'action' => [
                'en' => 'Record for sanctions check.',
                'de' => 'Für Sanktionsprüfung dokumentieren.',
                'fr' => 'Consigner pour vérification des sanctions.',
            ],
        ],
        'Cliënt is afkomstig uit een land op de sanctielijst.' => [
            'section' => ['en' => 'Countries', 'de' => 'Länder', 'fr' => 'Pays'],
            'question' => [
                'en' => 'Client is from a country on the sanctions list.',
                'de' => 'Der Kunde stammt aus einem Land auf der Sanktionsliste.',
                'fr' => 'Le client est originaire d\'un pays figurant sur la liste des sanctions.',
            ],
            'action' => [
                'en' => 'Enhanced customer due diligence. If delivery to that country or to someone from it: block transaction.',
                'de' => 'Erweiterte Sorgfaltspflicht. Bei Lieferung in dieses Land oder an jemanden aus diesem Land: Transaktion sperren.',
                'fr' => 'Diligence renforcée. Si livraison dans ce pays ou à quelqu\'un en provenant : bloquer la transaction.',
            ],
        ],
        'Vaartuig wordt geleverd / afgehaald in het buitenland.' => [
            'section' => ['en' => 'Countries', 'de' => 'Länder', 'fr' => 'Pays'],
            'question' => [
                'en' => 'Vessel is delivered / collected abroad.',
                'de' => 'Das Fahrzeug wird im Ausland geliefert / abgeholt.',
                'fr' => 'Le navire est livré / récupéré à l\'étranger.',
            ],
            'action' => [
                'en' => 'Record destination and any export procedure.',
                'de' => 'Bestimmungsort und ggf. Exportverfahren dokumentieren.',
                'fr' => 'Consigner la destination et toute procédure d\'exportation.',
            ],
        ],

        // ── Section: Identiteit ──────────────────────────────────
        'Identiteitsbewijs koper geverifieerd.' => [
            'section' => ['en' => 'Identity', 'de' => 'Identität', 'fr' => 'Identité'],
            'question' => [
                'en' => 'Buyer\'s identity document verified.',
                'de' => 'Ausweisdokument des Käufers verifiziert.',
                'fr' => 'Pièce d\'identité de l\'acheteur vérifiée.',
            ],
            'action' => [
                'en' => 'Upload copy of valid identity document.',
                'de' => 'Kopie des gültigen Ausweisdokuments hochladen.',
                'fr' => 'Télécharger une copie du document d\'identité valide.',
            ],
        ],
        'Type identiteitsbewijs.' => [
            'section' => ['en' => 'Identity', 'de' => 'Identität', 'fr' => 'Identité'],
            'question' => [
                'en' => 'Type of identity document.',
                'de' => 'Art des Ausweisdokuments.',
                'fr' => 'Type de pièce d\'identité.',
            ],
            'action' => [
                'en' => 'Passport, driving licence or ID card.',
                'de' => 'Reisepass, Führerschein oder Personalausweis.',
                'fr' => 'Passeport, permis de conduire ou carte d\'identité.',
            ],
        ],
        'Documentnummer identiteitsbewijs.' => [
            'section' => ['en' => 'Identity', 'de' => 'Identität', 'fr' => 'Identité'],
            'question' => [
                'en' => 'Identity document number.',
                'de' => 'Nummer des Ausweisdokuments.',
                'fr' => 'Numéro du document d\'identité.',
            ],
            'action' => [
                'en' => 'Record for file.',
                'de' => 'Für die Akte dokumentieren.',
                'fr' => 'Consigner dans le dossier.',
            ],
        ],
        'Geboortedatum cliënt.' => [
            'section' => ['en' => 'Identity', 'de' => 'Identität', 'fr' => 'Identité'],
            'question' => [
                'en' => 'Date of birth of client.',
                'de' => 'Geburtsdatum des Kunden.',
                'fr' => 'Date de naissance du client.',
            ],
            'action' => [
                'en' => 'Record for file.',
                'de' => 'Für die Akte dokumentieren.',
                'fr' => 'Consigner dans le dossier.',
            ],
        ],
        'Identiteitsgegevens komen overeen met overige documentatie.' => [
            'section' => ['en' => 'Identity', 'de' => 'Identität', 'fr' => 'Identité'],
            'question' => [
                'en' => 'Identity details match the other documentation.',
                'de' => 'Identitätsdaten stimmen mit den übrigen Dokumenten überein.',
                'fr' => 'Les données d\'identité correspondent à l\'autre documentation.',
            ],
            'action' => [
                'en' => 'Check for name discrepancies, date of birth, etc.',
                'de' => 'Auf Namensdiskrepanzen, Geburtsdatum usw. prüfen.',
                'fr' => 'Vérifier les divergences de nom, date de naissance, etc.',
            ],
        ],
        'Er is sprake van een identiteitsmismatch.' => [
            'section' => ['en' => 'Identity', 'de' => 'Identität', 'fr' => 'Identité'],
            'question' => [
                'en' => 'There is an identity mismatch.',
                'de' => 'Es liegt eine Identitätsdiskrepanz vor.',
                'fr' => 'Il y a une discordance d\'identité.',
            ],
            'action' => [
                'en' => 'Further investigation required. Record document issues.',
                'de' => 'Weitere Untersuchung erforderlich. Dokumentenprobleme festhalten.',
                'fr' => 'Enquête approfondie requise. Consigner les problèmes de documents.',
            ],
        ],

        // ── Section: Betaling ────────────────────────────────────
        'Betaalmethode.' => [
            'section' => ['en' => 'Payment', 'de' => 'Zahlung', 'fr' => 'Paiement'],
            'question' => [
                'en' => 'Payment method.',
                'de' => 'Zahlungsmethode.',
                'fr' => 'Méthode de paiement.',
            ],
            'action' => [
                'en' => 'Record for file.',
                'de' => 'Für die Akte dokumentieren.',
                'fr' => 'Consigner dans le dossier.',
            ],
        ],
        'Contant bedrag (in euro\'s).' => [
            'section' => ['en' => 'Payment', 'de' => 'Zahlung', 'fr' => 'Paiement'],
            'question' => [
                'en' => 'Cash amount (in euros).',
                'de' => 'Barbetrag (in Euro).',
                'fr' => 'Montant en espèces (en euros).',
            ],
            'action' => [
                'en' => 'If ≥ €10,000: reporting obligation. Record for file.',
                'de' => 'Wenn ≥ €10.000: Meldepflicht. Für die Akte dokumentieren.',
                'fr' => 'Si ≥ 10 000 € : obligation de déclaration. Consigner dans le dossier.',
            ],
        ],
        'Herkomst van het contante geld toegelicht.' => [
            'section' => ['en' => 'Payment', 'de' => 'Zahlung', 'fr' => 'Paiement'],
            'question' => [
                'en' => 'Origin of the cash explained.',
                'de' => 'Herkunft des Bargeldes erläutert.',
                'fr' => 'Origine des espèces expliquée.',
            ],
            'action' => [
                'en' => 'Record explanation and supporting documents.',
                'de' => 'Erklärung und Belege dokumentieren.',
                'fr' => 'Consigner l\'explication et les pièces justificatives.',
            ],
        ],
        'IBAN van de koper.' => [
            'section' => ['en' => 'Payment', 'de' => 'Zahlung', 'fr' => 'Paiement'],
            'question' => [
                'en' => 'Buyer\'s IBAN.',
                'de' => 'IBAN des Käufers.',
                'fr' => 'IBAN de l\'acheteur.',
            ],
            'action' => [
                'en' => 'Record for file.',
                'de' => 'Für die Akte dokumentieren.',
                'fr' => 'Consigner dans le dossier.',
            ],
        ],

        // ── Section: Compliance ──────────────────────────────────
        'Transactie is getoetst aan sanctielijsten (EU, VN, OFAC).' => [
            'section' => ['en' => 'Compliance', 'de' => 'Compliance', 'fr' => 'Conformité'],
            'question' => [
                'en' => 'Transaction has been screened against sanctions lists (EU, UN, OFAC).',
                'de' => 'Transaktion wurde gegen Sanktionslisten (EU, UN, OFAC) geprüft.',
                'fr' => 'La transaction a été vérifiée par rapport aux listes de sanctions (UE, ONU, OFAC).',
            ],
            'action' => [
                'en' => 'Document the screening. Record screenshot or reference.',
                'de' => 'Prüfung dokumentieren. Screenshot oder Referenz festhalten.',
                'fr' => 'Documenter la vérification. Conserver une capture d\'écran ou une référence.',
            ],
        ],
        'Melding gedaan bij FIU-Nederland.' => [
            'section' => ['en' => 'Compliance', 'de' => 'Compliance', 'fr' => 'Conformité'],
            'question' => [
                'en' => 'Report submitted to FIU-Netherlands.',
                'de' => 'Meldung bei FIU-Niederlande eingereicht.',
                'fr' => 'Déclaration soumise à FIU-Pays-Bas.',
            ],
            'action' => [
                'en' => 'Record date and reference number of FIU report.',
                'de' => 'Datum und Referenznummer der FIU-Meldung festhalten.',
                'fr' => 'Consigner la date et le numéro de référence du rapport FIU.',
            ],
        ],
        'Referentienummer FIU-melding.' => [
            'section' => ['en' => 'Compliance', 'de' => 'Compliance', 'fr' => 'Conformité'],
            'question' => [
                'en' => 'FIU report reference number.',
                'de' => 'Referenznummer der FIU-Meldung.',
                'fr' => 'Numéro de référence du rapport FIU.',
            ],
            'action' => [
                'en' => 'Record in file.',
                'de' => 'In der Akte dokumentieren.',
                'fr' => 'Consigner dans le dossier.',
            ],
        ],
        'Aanvullende opmerkingen compliance officer.' => [
            'section' => ['en' => 'Compliance', 'de' => 'Compliance', 'fr' => 'Conformité'],
            'question' => [
                'en' => 'Additional comments from compliance officer.',
                'de' => 'Zusätzliche Anmerkungen des Compliance-Beauftragten.',
                'fr' => 'Commentaires supplémentaires du responsable conformité.',
            ],
            'action' => [
                'en' => 'Free text for special notes.',
                'de' => 'Freitext für Besonderheiten.',
                'fr' => 'Texte libre pour les remarques particulières.',
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::TRANSLATIONS as $dutchQuestion => $data) {
            $translations = [];
            foreach (['en', 'de', 'fr'] as $locale) {
                $translations[$locale] = [
                    'section'  => $data['section'][$locale]  ?? null,
                    'question' => $data['question'][$locale] ?? null,
                    'action'   => $data['action'][$locale]   ?? null,
                ];
            }

            DB::table('kyc_question_templates')
                ->where('question', $dutchQuestion)
                ->update(['translations' => json_encode($translations)]);
        }
    }

    public function down(): void
    {
        DB::table('kyc_question_templates')->update(['translations' => null]);
    }
};
