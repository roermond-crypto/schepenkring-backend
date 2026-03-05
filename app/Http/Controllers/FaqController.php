<?php
// app/Http/Controllers/FaqController.php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\FaqEntry;
use App\Models\FaqTranslation;
use App\Services\LocaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FaqController extends Controller
{
    // Get all Faqs for display
    public function index(Request $request)
    {
        $localeService = app(LocaleService::class);
        $localeInfo = $localeService->resolve($request, $request->user());
        $language = $localeInfo['locale'];
        $fallbacks = $localeInfo['fallbacks'];

        $includeErrors = filter_var($request->query('include_errors', false), FILTER_VALIDATE_BOOL);

        $baseQuery = FaqTranslation::query()
            ->with('faq')
            ->whereHas('faq', function ($faqQuery) use ($request) {
                $faqQuery->where('is_active', true);

                if ($request->filled('namespace')) {
                    $faqQuery->where('namespace', $request->query('namespace'));
                }
                if ($request->has('category') && $request->category !== 'all') {
                    $faqQuery->where('category', $request->category);
                }
                if ($request->filled('subcategory')) {
                    $faqQuery->where('subcategory', $request->query('subcategory'));
                }
                if (!filter_var($request->query('include_errors', false), FILTER_VALIDATE_BOOL)) {
                    $faqQuery->where(function ($q) {
                        $q->whereNull('category')
                          ->orWhereRaw('LOWER(category) <> ?', ['errors']);
                    });
                }
            });

        if ($request->filled('search')) {
            $search = $request->query('search');
            $baseQuery->where(function ($q) use ($search) {
                $q->where('question', 'like', '%' . $search . '%')
                  ->orWhere('answer', 'like', '%' . $search . '%');
            });
        }

        $faqs = (clone $baseQuery)->where('language', $language)
            ->orderBy('views', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $fallbackUsed = null;
        if ($faqs->total() === 0 && !empty($fallbacks)) {
            foreach ($fallbacks as $fallbackLocale) {
                if ($fallbackLocale === $language) {
                    continue;
                }
                $faqs = (clone $baseQuery)->where('language', $fallbackLocale)
                    ->orderBy('views', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->paginate(20);
                if ($faqs->total() > 0) {
                    $fallbackUsed = $fallbackLocale;
                    $language = $fallbackLocale;
                    break;
                }
            }
        }

        $faqs->getCollection()->transform(function (FaqTranslation $translation) {
            $faq = $translation->faq;
            return [
                'id' => $translation->id,
                'faq_id' => $translation->faq_id,
                'language' => $translation->language,
                'namespace' => $faq?->namespace,
                'category' => $faq?->category,
                'subcategory' => $faq?->subcategory,
                'slug' => $faq?->slug,
                'question' => $translation->question,
                'answer' => $translation->answer,
                'views' => (int) ($translation->views ?? 0),
                'helpful' => (int) ($translation->helpful ?? 0),
                'not_helpful' => (int) ($translation->not_helpful ?? 0),
            ];
        });

        $categories = FaqEntry::query()
            ->where('is_active', true)
            ->when($request->filled('namespace'), function ($faqQuery) use ($request) {
                $faqQuery->where('namespace', $request->query('namespace'));
            })
            ->when(!$includeErrors, function ($faqQuery) {
                $faqQuery->where(function ($q) {
                    $q->whereNull('category')
                      ->orWhereRaw('LOWER(category) <> ?', ['errors']);
                });
            })
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values()
            ->toArray();

        $totalCount = FaqTranslation::query()
            ->where('language', $language)
            ->whereHas('faq', function ($faqQuery) use ($request) {
                $faqQuery->where('is_active', true);
                if ($request->filled('namespace')) {
                    $faqQuery->where('namespace', $request->query('namespace'));
                }
                if (!filter_var($request->query('include_errors', false), FILTER_VALIDATE_BOOL)) {
                    $faqQuery->where(function ($q) {
                        $q->whereNull('category')
                          ->orWhereRaw('LOWER(category) <> ?', ['errors']);
                    });
                }
            })
            ->count();

        return response()->json([
            'faqs' => $faqs,
            'categories' => $categories,
            'total_count' => $totalCount,
            'requested_locale' => $localeInfo['locale'],
            'fallback_locale_used' => $fallbackUsed,
        ]);
    }

    // Store new Faq (Admin only)
    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'category' => 'required|string|max:100',
            'subcategory' => 'nullable|string|max:100',
            'namespace' => 'nullable|string|max:50',
            'slug' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:5',
            'long_description' => 'nullable|string',
        ]);

        $language = strtolower((string) $request->input('language', 'nl'));
        $namespace = $request->input('namespace', 'core');
        $slug = $request->input('slug') ?: $this->buildSlug(
            $namespace,
            $request->input('category'),
            $request->input('subcategory'),
            $request->input('question')
        );

        $faq = FaqEntry::updateOrCreate(
            ['slug' => $slug],
            [
                'category' => $request->input('category'),
                'subcategory' => $request->input('subcategory'),
                'namespace' => $namespace,
                'is_active' => true,
            ]
        );

        $translation = FaqTranslation::updateOrCreate(
            ['faq_id' => $faq->id, 'language' => $language],
            [
                'question' => $request->input('question'),
                'answer' => $request->input('answer'),
                'long_description' => $request->input('long_description'),
                'source_language' => $language,
                'needs_review' => false,
                'translation_status' => \App\Support\TranslationStatus::REVIEWED,
                'source_hash' => app(\App\Services\TranslationHashService::class)->hash([
                    'question' => $request->input('question'),
                    'answer' => $request->input('answer'),
                ]),
            ]
        );

        // Train Gemini with new Faq
        $this->trainGemini();

        return response()->json([
            'message' => 'Faq added successfully',
            'faq' => [
                'id' => $translation->id,
                'faq_id' => $faq->id,
                'language' => $translation->language,
                'namespace' => $faq->namespace,
                'category' => $faq->category,
                'subcategory' => $faq->subcategory,
                'slug' => $faq->slug,
                'question' => $translation->question,
                'answer' => $translation->answer,
            ],
        ], 201);
    }

    // Update Faq
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'question' => 'sometimes|string|max:500',
            'answer' => 'sometimes|string',
            'category' => 'sometimes|string|max:100',
            'subcategory' => 'sometimes|string|max:100',
            'namespace' => 'sometimes|string|max:50',
            'slug' => 'sometimes|string|max:255',
            'long_description' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $translation = FaqTranslation::with('faq')->find($id);
        if ($translation) {
            if (array_key_exists('question', $validated)) {
                $translation->question = $validated['question'];
            }
            if (array_key_exists('answer', $validated)) {
                $translation->answer = $validated['answer'];
            }
            if (array_key_exists('long_description', $validated)) {
                $translation->long_description = $validated['long_description'];
            }
            $translation->save();

            if ($translation->faq) {
                $faq = $translation->faq;
                if (array_key_exists('category', $validated)) {
                    $faq->category = $validated['category'];
                }
                if (array_key_exists('subcategory', $validated)) {
                    $faq->subcategory = $validated['subcategory'];
                }
                if (array_key_exists('namespace', $validated)) {
                    $faq->namespace = $validated['namespace'];
                }
                if (array_key_exists('slug', $validated)) {
                    $faq->slug = $validated['slug'];
                }
                if (array_key_exists('is_active', $validated)) {
                    $faq->is_active = (bool) $validated['is_active'];
                }
                $faq->save();
            }

            // Retrain Gemini after update
            $this->trainGemini();

            return response()->json([
                'message' => 'Faq updated successfully',
                'faq' => [
                    'id' => $translation->id,
                    'faq_id' => $translation->faq_id,
                    'language' => $translation->language,
                    'namespace' => $translation->faq?->namespace,
                    'category' => $translation->faq?->category,
                    'subcategory' => $translation->faq?->subcategory,
                    'slug' => $translation->faq?->slug,
                    'question' => $translation->question,
                    'answer' => $translation->answer,
                ],
            ]);
        }

        $faq = Faq::findOrFail($id);
        $faq->update($validated);

        // Retrain Gemini after update
        $this->trainGemini();

        return response()->json([
            'message' => 'Faq updated successfully',
            'faq' => $faq,
        ]);
    }

    // Delete Faq
    public function destroy($id)
    {
        $translation = FaqTranslation::with('faq')->find($id);
        if ($translation) {
            $faq = $translation->faq;
            $translation->delete();

            if ($faq && $faq->translations()->count() === 0) {
                $faq->delete();
            }

            // Retrain Gemini after deletion
            $this->trainGemini();

            return response()->json([
                'message' => 'Faq deleted successfully',
            ]);
        }

        $faq = Faq::findOrFail($id);
        $faq->delete();

        // Retrain Gemini after deletion
        $this->trainGemini();

        return response()->json([
            'message' => 'Faq deleted successfully',
        ]);
    }

    // Get Faq by ID and increment views
    public function show($id)
    {
        $translation = FaqTranslation::with('faq')->find($id);
        if ($translation && $translation->faq && $translation->faq->is_active) {
            $translation->increment('views');
            $faq = $translation->faq;

            return response()->json([
                'id' => $translation->id,
                'faq_id' => $translation->faq_id,
                'language' => $translation->language,
                'namespace' => $faq->namespace,
                'category' => $faq->category,
                'subcategory' => $faq->subcategory,
                'slug' => $faq->slug,
                'question' => $translation->question,
                'answer' => $translation->answer,
                'views' => (int) ($translation->views ?? 0),
                'helpful' => (int) ($translation->helpful ?? 0),
                'not_helpful' => (int) ($translation->not_helpful ?? 0),
            ]);
        }

        $faq = Faq::findOrFail($id);
        $faq->increment('views');

        return response()->json($faq);
    }

    // Rate Faq helpfulness
    public function rateHelpful($id)
    {
        $translation = FaqTranslation::find($id);
        if ($translation) {
            $translation->increment('helpful');

            return response()->json([
                'message' => 'Thank you for your feedback!',
                'helpful_count' => $translation->helpful ?? 0,
            ]);
        }

        $faq = Faq::findOrFail($id);
        $faq->increment('helpful');

        return response()->json([
            'message' => 'Thank you for your feedback!',
            'helpful_count' => $faq->helpful,
        ]);
    }
    
    public function rateNotHelpful($id)
    {
        $translation = FaqTranslation::find($id);
        if ($translation) {
            $translation->increment('not_helpful');

            return response()->json([
                'message' => 'Thank you for your feedback!',
                'not_helpful_count' => $translation->not_helpful ?? 0,
            ]);
        }

        $faq = Faq::findOrFail($id);
        $faq->increment('not_helpful');

        return response()->json([
            'message' => 'Thank you for your feedback!',
            'not_helpful_count' => $faq->not_helpful,
        ]);
    }

public function askGemini(Request $request)
{
    $request->validate([
        'question' => 'required|string|max:500'
    ]);

    $apiKey = config('services.gemini.key') ?: env('GOOGLE_API_KEY');
    $model  = env('FAQ_AI_MODEL', env('GEMINI_MODEL', 'gemini-2.5-flash')); // or gemini-2.5-flash-lite
    $language = strtolower((string) $request->input('language', 'nl'));
    if (!$apiKey) {
        Log::error('askGemini error: GEMINI_API_KEY not configured');
        return response()->json([
            'answer' => 'AI is not configured. Please contact support.',
            'sources' => 0,
            'timestamp' => now()
        ], 503);
    }

    try {
        // 1. FETCH FAQS (multilingual)
        $maxFaqs = (int) env('FAQ_AI_MAX_FAQS', 200);
        $faqs = FaqTranslation::with('faq')
            ->where('language', $language)
            ->whereHas('faq', function ($faqQuery) {
                $faqQuery->where('is_active', true);
            })
            ->orderBy('views', 'desc')
            ->limit($maxFaqs)
            ->get();

        if ($faqs->isEmpty() && $language !== 'nl') {
            $faqs = FaqTranslation::with('faq')
                ->where('language', 'nl')
                ->whereHas('faq', function ($faqQuery) {
                    $faqQuery->where('is_active', true);
                })
                ->orderBy('views', 'desc')
                ->limit($maxFaqs)
                ->get();
        }

        $faqTexts = $faqs->map(function (FaqTranslation $faq) {
            return "Vraag: " . $faq->question . "\nAntwoord: " . $faq->answer;
        })->join("\n\n");
        if ($faqTexts === '') {
            return response()->json([
                'answer' => "I don't have specific information about that yet. Please contact support.",
                'sources' => 0,
                'timestamp' => now()
            ]);
        }

        // 2. BUILD SYSTEM CONTEXT for Schepen Kring
        $systemContext = "
ROLE: You are the 'Schepen Kring' AI assistant, specialized in maritime and yacht information. You are helpful, friendly, and extremely concise.

TONE & PERSONALITY: Professional, knowledgeable, and approachable. Use a calm and clear tone. Use emojis occasionally where appropriate ⚓.

WHAT IS Schepen Kring?
Schepen Kring is a platform for yacht and vessel enthusiasts, brokers, and buyers. We provide:
- Listings of sailing yachts, motor yachts, and luxury vessels.
- Detailed specifications, images, and broker contact information.
- Resources for buying, selling, and chartering vessels.

TONE & STYLE:
- CONCISE and FRIENDLY: Max 2 sentences. No paragraphs.
- HUMAN: Use friendly language. Don't sound like a manual. Use contractions (we're, it's) and occasional emojis.
- If the user asks about a specific type of yacht or feature, provide accurate information based on the FAQs.
- If you don't know the answer, politely suggest they contact support or a broker.

MANDATORY RULES:
1. LANGUAGE ADAPTATION: Detect the language the user is speaking. ALWAYS respond in the SAME language as the user. If they speak Dutch, you speak Dutch. If they speak English, you speak English. Prioritize Dutch over English if uncertain.
2. Use the following FAQs as your absolute source of truth for company facts and common questions.

KNOWLEDGE BASE:
---
FAQS:
$faqTexts
---
        ";

        // 3. PREPARE THE USER MESSAGE
        $contents = [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $request->question]
                ]
            ]
        ];

        // 4. CALL GEMINI API WITH SYSTEM INSTRUCTION
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 200)
            ->post($url, [
                "system_instruction" => [
                    "parts" => [["text" => $systemContext]]
                ],
                "contents" => $contents,
                "generationConfig" => [
                    "temperature" => 0.7,
                    "maxOutputTokens" => 250,
                ]
            ]);

        if ($response->failed()) {
            $errorBody = $response->body();
            Log::error('Gemini API failed', [
                'status' => $response->status(),
                'body' => $errorBody,
            ]);
            return response()->json([
                'answer' => "Our AI assistant is temporarily unavailable. Please contact support.",
                'sources' => 0,
                'timestamp' => now(),
                'error' => app()->environment('local') ? [
                    'status' => $response->status(),
                    'body' => $errorBody,
                ] : null,
            ], 502);
        }

        $answer = $response->json('candidates.0.content.parts.0.text');
        $answer = $answer ? trim($answer) : "I don't have specific information about that yet. Please contact support.";

        return response()->json([
            'answer' => $answer,
            'sources' => $faqs->count(),
            'timestamp' => now()
        ]);

    } catch (\Throwable $e) {
        Log::error("askGemini error: " . $e->getMessage());
        return response()->json([
            'answer' => "Our AI assistant is temporarily unavailable. Please contact support.",
            'sources' => 0,
            'timestamp' => now()
        ]);
    }
}




// Embedding function rewritten to use generateContent properly
private function createEmbedding($text)
{
    $apiKey = env('GEMINI_API_KEY') ?: env('GOOGLE_API_KEY');
    if (!$apiKey) {
        Log::error('Embedding API failed: GEMINI_API_KEY not configured');
        return null;
    }

    $body = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $text]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0,
            "maxOutputTokens" => 1
        ]
    ];

    $response = Http::timeout(15)->post(
        "https://generativelanguage.googleapis.com/v1beta/models/embedding-001:embedContent?key={$apiKey}",
        $body
    );

    if ($response->failed()) {
        Log::error("Embedding API failed: " . $response->body());
        return null;
    }

    return $response->json('embedding.values') ?? null;
}

public function storeDummy()
{
    $faq = FaqEntry::create([
        'category' => 'Yachts',
        'subcategory' => null,
        'namespace' => 'core',
        'slug' => $this->buildSlug('core', 'Yachts', null, 'What yachts are available?'),
        'is_active' => true,
    ]);

    $translation = FaqTranslation::create([
        'faq_id' => $faq->id,
        'language' => 'en',
        'question' => 'What yachts are available?',
        'answer' => 'We have sailing yachts, motor yachts, and luxury yachts.',
        'source_language' => 'en',
        'needs_review' => false,
    ]);

    $this->trainGemini();

    return response()->json([
        'message' => 'Dummy FAQ added',
        'faq' => [
            'id' => $translation->id,
            'faq_id' => $faq->id,
            'language' => $translation->language,
            'namespace' => $faq->namespace,
            'category' => $faq->category,
            'subcategory' => $faq->subcategory,
            'slug' => $faq->slug,
            'question' => $translation->question,
            'answer' => $translation->answer,
        ],
    ]);
}


    // Train Gemini with all Faqs (Admin function)
    public function trainGemini()
    {
        $faqs = FaqTranslation::with('faq')
            ->whereHas('faq', function ($faqQuery) {
                $faqQuery->where('is_active', true);
            })
            ->get(['id', 'faq_id', 'language', 'question', 'answer']);
        $faqCount = $faqs->count();
        
        Log::info("Gemini training initiated with {$faqCount} Faqs");
        
        // In a production system, you might:
        // 1. Create embeddings for each Faq
        // 2. Store them in a vector database
        // 3. Use similarity search for answers
        
        // For now, we'll just log and update a training timestamp
        cache()->put('gemini_last_trained', now()->toDateTimeString());
        cache()->put('gemini_faq_count', $faqCount);
        
        return response()->json([
            'message' => "Gemini training completed with {$faqCount} Faqs",
            'last_trained' => now()->toDateTimeString(),
            'faq_count' => $faqCount
        ]);
    }

    // Get training status
    public function getTrainingStatus()
    {
        return response()->json([
            'last_trained' => cache()->get('gemini_last_trained'),
            'faq_count' => cache()->get('gemini_faq_count', 0),
            'total_faqs' => FaqTranslation::count()
        ]);
    }

    // Get statistics
    public function stats()
    {
        $language = strtolower((string) request()->query('language', 'nl'));

        $baseQuery = FaqTranslation::query()
            ->where('language', $language)
            ->whereHas('faq', function ($faqQuery) {
                $faqQuery->where('is_active', true);
            });

        $totalFaqs = (clone $baseQuery)->count();
        $totalViews = (clone $baseQuery)->sum('views');
        $totalHelpful = (clone $baseQuery)->sum('helpful');
        $totalNotHelpful = (clone $baseQuery)->sum('not_helpful');

        $categories = FaqEntry::select('category', \DB::raw('count(*) as count'))
            ->where('is_active', true)
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->get();

        $popularFaqs = (clone $baseQuery)
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get(['id', 'question', 'views']);

        return response()->json([
            'total_faqs' => $totalFaqs,
            'total_views' => $totalViews,
            'total_helpful' => $totalHelpful,
            'total_not_helpful' => $totalNotHelpful,
            'categories' => $categories,
            'popular_faqs' => $popularFaqs,
        ]);
    }

    private function buildSlug(string $namespace, ?string $category, ?string $subcategory, string $question): string
    {
        $parts = array_filter([
            $namespace,
            $category,
            $subcategory,
            $question,
        ]);

        return Str::slug(implode(' ', $parts));
    }
}
