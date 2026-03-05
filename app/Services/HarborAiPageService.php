<?php

namespace App\Services;

use App\Models\Harbor;
use App\Models\HarborPage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HarborAiPageService
{
    private string $geminiApiKey;

    public function __construct()
    {
        $this->geminiApiKey = env('GEMINI_API_KEY', '');
    }

    /**
     * Generate a structured harbor page using Gemini AI.
     *
     * @return array  Structured page content or ['error' => ...]
     */
    public function generatePage(Harbor $harbor, string $locale = 'nl'): array
    {
        if (empty($this->geminiApiKey)) {
            Log::error('[HarborAiPage] No GEMINI_API_KEY configured');
            return ['error' => 'Gemini API key not configured'];
        }

        $prompt = $this->buildPrompt($harbor, $locale);
        $sourceHash = $this->computeSourceHash($harbor);

        // Check if page exists with same source hash (no changes)
        $existing = HarborPage::where('harbor_id', $harbor->id)
            ->where('locale', $locale)
            ->first();

        if ($existing && $existing->source_data_hash === $sourceHash) {
            Log::info("[HarborAiPage] No data changes for harbor {$harbor->id}, skipping regeneration");
            return $existing->page_content ?? ['skipped' => true];
        }

        try {
            $response = Http::timeout(60)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$this->geminiApiKey}",
                [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature'    => 0.3,
                        'maxOutputTokens' => 4000,
                        'responseMimeType' => 'application/json',
                    ],
                ]
            );

            if (!$response->successful()) {
                Log::error("[HarborAiPage] Gemini API error: {$response->status()}");
                return ['error' => "Gemini HTTP {$response->status()}"];
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            // Parse JSON response
            $pageContent = json_decode($text, true);
            if (!$pageContent) {
                Log::error("[HarborAiPage] Failed to parse Gemini response as JSON");
                return ['error' => 'Invalid JSON from Gemini'];
            }

            // Save to harbor_pages
            HarborPage::updateOrCreate(
                ['harbor_id' => $harbor->id, 'locale' => $locale],
                [
                    'page_content'    => $pageContent,
                    'generated_at'    => now(),
                    'source_data_hash' => $sourceHash,
                    'translated_from_hash' => $sourceHash,
                    'translation_status' => 'AI_DRAFT',
                ]
            );

            Log::info("[HarborAiPage] Generated page for harbor {$harbor->id} in locale {$locale}");
            return $pageContent;
        } catch (\Exception $e) {
            Log::error("[HarborAiPage] Exception: {$e->getMessage()}");
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Build the AI prompt with all available harbor data.
     */
    private function buildPrompt(Harbor $harbor, string $locale): string
    {
        $langMap = [
            'nl' => 'Dutch',
            'en' => 'English',
            'de' => 'German',
        ];
        $language = $langMap[$locale] ?? 'Dutch';

        $harborData = [
            'name'              => $harbor->name,
            'address'           => $harbor->full_address,
            'city'              => $harbor->city,
            'postal_code'       => $harbor->postal_code,
            'description'       => $harbor->description,
            'facilities'        => $harbor->facilities,
            'phone'             => $harbor->primary_phone ?? $harbor->phone,
            'email'             => $harbor->email,
            'website'           => $harbor->google_website ?? $harbor->website,
            'opening_hours'     => $harbor->opening_hours_json,
            'rating'            => $harbor->rating,
            'rating_count'      => $harbor->rating_count,
            'lat'               => $harbor->lat,
            'lng'               => $harbor->lng,
            'google_types'      => $harbor->place_details_json['types'] ?? [],
        ];

        $dataJson = json_encode($harborData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a professional copywriter creating a harbor/marina profile page for a yacht platform.
Write in {$language}. Generate SEO-optimized, professional content.

IMPORTANT RULES:
- NEVER invent facts. Only use the provided data.
- If information is missing, write "Neem contact op met de jachthaven voor meer informatie" (or equivalent in {$language}) instead of guessing.
- Keep text professional but warm and inviting.
- Add a visible note: "Gegevens: HISWA & Google"

HARBOR DATA:
{$dataJson}

Generate a JSON object with this exact structure:
{
  "hero_title": "Main heading for the harbor page",
  "short_intro": "2-3 sentence introduction about this harbor",
  "highlights": ["Highlight 1", "Highlight 2", "... up to 10 highlights derived from facilities and features"],
  "opening_hours_display": "Human-friendly opening hours text (or 'Contact harbor for hours' if not available)",
  "location_block": {
    "address": "Full formatted address",
    "lat": latitude,
    "lng": longitude,
    "directions_text": "Brief directions or nearby landmarks"
  },
  "faq": [
    {"question": "FAQ question 1", "answer": "Answer 1"},
    {"question": "FAQ question 2", "answer": "Answer 2"}
  ],
  "cta_block": {
    "primary_text": "Claim this harbor",
    "secondary_text": "Add your boat",
    "contact_text": "Contact us for more info"
  },
  "seo_title": "SEO-optimized page title (60 chars max)",
  "seo_description": "SEO meta description (155 chars max)",
  "slug_suggestions": ["slug-1", "slug-2"],
  "data_sources": "HISWA & Google"
}

Respond with ONLY the JSON object, no markdown formatting.
PROMPT;
    }

    /**
     * Compute a hash of the harbor's source data to detect changes.
     */
    private function computeSourceHash(Harbor $harbor): string
    {
        $data = [
            $harbor->name,
            $harbor->description,
            $harbor->facilities,
            $harbor->opening_hours_json,
            $harbor->rating,
            $harbor->primary_phone,
        ];

        return md5(json_encode($data));
    }
}
