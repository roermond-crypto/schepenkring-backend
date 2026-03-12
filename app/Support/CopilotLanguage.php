<?php

namespace App\Support;

class CopilotLanguage
{
    /**
     * @return array{language:string,header_language:string,detected_from_input:bool}
     */
    public function resolve(string $input, ?string $contextLanguage = null, ?string $acceptLanguage = null, ?string $fallback = null): array
    {
        $detected = $this->detectFromText($input);
        $language = $detected
            ?? $this->normalize($contextLanguage)
            ?? $this->fromAcceptLanguage($acceptLanguage)
            ?? $this->normalize($fallback)
            ?? 'en';

        return [
            'language' => $language,
            'header_language' => strtoupper($language),
            'detected_from_input' => $detected !== null,
        ];
    }

    public function normalize(?string $language): ?string
    {
        if (! is_string($language)) {
            return null;
        }

        $language = strtolower(trim($language));
        if ($language === '') {
            return null;
        }

        return match (true) {
            str_starts_with($language, 'nl') => 'nl',
            str_starts_with($language, 'de') => 'de',
            str_starts_with($language, 'fr') => 'fr',
            str_starts_with($language, 'en') => 'en',
            default => null,
        };
    }

    public function fromAcceptLanguage(?string $acceptLanguage): ?string
    {
        if (! is_string($acceptLanguage) || trim($acceptLanguage) === '') {
            return null;
        }

        foreach (explode(',', $acceptLanguage) as $candidate) {
            $normalized = $this->normalize($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    public function translate(string $key, string $language): string
    {
        $language = $this->normalize($language) ?? 'en';

        return match ($key) {
            'clarify_open_or_search' => match ($language) {
                'nl' => 'Kunt u specificeren wat u wilt openen of zoeken?',
                'de' => 'Koennen Sie bitte angeben, was Sie oeffnen oder suchen moechten?',
                'fr' => 'Pouvez-vous préciser ce que vous voulez ouvrir ou rechercher ?',
                default => 'Can you specify what you want to open or search for?',
            },
            'clarify_action' => match ($language) {
                'nl' => 'Welke actie bedoelde u?',
                'de' => 'Welche Aktion meinten Sie?',
                'fr' => 'Quelle action vouliez-vous dire ?',
                default => 'Which action did you mean?',
            },
            'knowledge_low_confidence' => match ($language) {
                'nl' => 'Dit lijkt het dichtstbijzijnde kennisbankantwoord, maar de betrouwbaarheid is laag. Controleer het voordat u erop vertrouwt.',
                'de' => 'Dies scheint die naechstliegende Wissensdatenbank-Antwort zu sein, aber die Zuverlaessigkeit ist niedrig. Bitte pruefen Sie sie vor der Verwendung.',
                'fr' => 'Ceci semble etre la reponse la plus proche de la base de connaissances, mais la confiance est faible. Veuillez la verifier avant de vous y fier.',
                default => 'This looks like the closest knowledge-base answer, but confidence is low. Please verify it before relying on it.',
            },
            'knowledge_not_found' => match ($language) {
                'nl' => 'Ik kon nog geen betrouwbaar antwoord vinden in de kennisbank. Voeg een FAQ toe of vraag om een handmatige review.',
                'de' => 'Ich konnte noch keine verlaessliche Antwort in der Wissensdatenbank finden. Fuegen Sie einen FAQ-Eintrag hinzu oder bitten Sie um eine manuelle Pruefung.',
                'fr' => 'Je n ai pas encore trouve de reponse fiable dans la base de connaissances. Ajoutez une FAQ ou demandez une verification manuelle.',
                default => 'I could not find a trusted answer in the knowledge base yet. Please add an FAQ entry or request a manual review.',
            },
            default => '',
        };
    }

    public function looksLikeQuestion(string $input): bool
    {
        $trimmed = mb_strtolower(trim($input));
        if ($trimmed === '') {
            return false;
        }

        if (str_ends_with($trimmed, '?')) {
            return true;
        }

        foreach ([
            'how', 'what', 'where', 'when', 'why', 'who',
            'hoe', 'wat', 'waar', 'wanneer', 'waarom', 'wie',
            'wie', 'was', 'wo', 'wann', 'warum',
            'comment', 'quoi', 'ou', 'où', 'quand', 'pourquoi', 'qui', 'quel', 'quelle',
        ] as $starter) {
            if (preg_match('/^'.preg_quote($starter, '/').'\b/u', $trimmed) === 1) {
                return true;
            }
        }

        return false;
    }

    public function detectFromText(string $input): ?string
    {
        $text = mb_strtolower(trim($input));
        if ($text === '') {
            return null;
        }

        $scores = [];
        foreach ($this->languageKeywords() as $language => $keywords) {
            $scores[$language] = $this->keywordScore($text, $keywords);
        }

        arsort($scores);

        $language = array_key_first($scores);
        $topScore = $scores[$language] ?? 0;
        if ($topScore < 2) {
            return null;
        }

        $remaining = array_values($scores);
        $runnerUp = $remaining[1] ?? 0;

        return ($topScore - $runnerUp) >= 1 ? $language : null;
    }

    private function keywordScore(string $text, array $keywords): int
    {
        $score = 0.0;

        foreach ($keywords as $keyword => $weight) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'\b/u', $text) === 1) {
                $score += $weight;
            }
        }

        return (int) floor($score);
    }

    /**
     * @return array<string, array<string, float>>
     */
    private function languageKeywords(): array
    {
        return [
            'nl' => [
                'hoe' => 2.0,
                'waarom' => 2.0,
                'wanneer' => 2.0,
                'waar' => 1.5,
                'wie' => 1.0,
                'ik' => 1.0,
                'mijn' => 1.5,
                'wil' => 1.0,
                'kan' => 1.0,
                'kun' => 1.0,
                'zoek' => 1.5,
                'zoeken' => 1.5,
                'open' => 0.5,
                'openen' => 1.5,
                'klant' => 2.0,
                'gebruiker' => 2.0,
                'haven' => 2.0,
                'jacht' => 2.0,
                'boot' => 1.0,
            ],
            'de' => [
                'was' => 1.5,
                'wie' => 1.0,
                'wo' => 1.5,
                'wann' => 2.0,
                'warum' => 2.0,
                'ich' => 1.0,
                'mein' => 1.5,
                'moechte' => 1.5,
                'kunde' => 2.0,
                'benutzer' => 2.0,
                'hafen' => 2.0,
                'oeffnen' => 2.0,
                'offnen' => 2.0,
                'suchen' => 2.0,
                'finden' => 1.5,
                'boot' => 1.0,
            ],
            'fr' => [
                'comment' => 2.0,
                'pourquoi' => 2.0,
                'quand' => 2.0,
                'bonjour' => 1.5,
                'ouvrir' => 2.0,
                'rechercher' => 2.0,
                'cherche' => 1.5,
                'chercher' => 1.5,
                'utilisateur' => 2.0,
                'client' => 1.0,
                'port' => 1.5,
                'bateau' => 2.0,
                'mon' => 1.0,
                'ma' => 1.0,
                'je' => 1.0,
                'veux' => 1.0,
            ],
            'en' => [
                'how' => 2.0,
                'what' => 1.5,
                'where' => 2.0,
                'when' => 2.0,
                'why' => 2.0,
                'who' => 1.5,
                'hello' => 1.0,
                'open' => 1.5,
                'search' => 2.0,
                'find' => 1.5,
                'user' => 2.0,
                'client' => 1.0,
                'harbor' => 2.0,
                'boat' => 1.5,
                'my' => 1.0,
                'i' => 0.5,
                'want' => 1.0,
            ],
        ];
    }
}
