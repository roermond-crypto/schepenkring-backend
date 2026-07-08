<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * POST /api/admin/bids/extract
 *
 * Accepts raw text and/or a screenshot image.
 * Sends to OpenAI to extract: boat name, buyer email, buyer name, offer amount, phone, message.
 * Also stores the raw text and image for audit purposes.
 */
class BidExtractController extends Controller
{
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'text'       => 'nullable|string|max:10000',
            'image'      => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:10240',
            'image_data' => 'nullable|string', // base64 data URI
        ]);

        if (!$request->filled('text') && !$request->hasFile('image') && !$request->filled('image_data')) {
            return response()->json(['message' => 'Geef tekst of een afbeelding op.'], 422);
        }

        $apiKey = config('services.openai.key') ?? config('services.openai.api_key') ?? env('OPENAI_API_KEY');
        if (!$apiKey) {
            return response()->json(['message' => 'AI extractie is niet geconfigureerd.'], 503);
        }

        // Store raw artifacts for audit
        $storedImagePath = null;
        $rawText = $request->input('text', '');

        if ($request->hasFile('image')) {
            $storedImagePath = $request->file('image')->store('bid-extractions', 'public');
        } elseif ($request->filled('image_data')) {
            $dataUri = $request->input('image_data');
            if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $dataUri, $m)) {
                $ext = $m[1];
                $storedImagePath = 'bid-extractions/' . Str::uuid() . '.' . $ext;
                Storage::disk('public')->put($storedImagePath, base64_decode($m[2]));
            }
        }

        // Build OpenAI messages
        $systemPrompt = <<<'SYS'
You are a bid extraction assistant for a yacht marketplace.
Extract the following fields from the provided text or image:
- boat_name: the name/type of the boat
- buyer_name: full name of the buyer
- buyer_email: email address of the buyer
- buyer_phone: phone number of the buyer
- offer_amount: numeric offer amount in EUR (numbers only, no currency symbols)
- message: any relevant note or message from the buyer

Return ONLY a valid JSON object with these keys. Use null for any field you cannot determine.
Example: {"boat_name":"Bavaria 38","buyer_name":"Jan de Vries","buyer_email":"jan@example.com","buyer_phone":"+31612345678","offer_amount":85000,"message":"Graag snel reageren."}
SYS;

        $userContent = [];

        if (!empty($rawText)) {
            $userContent[] = [
                'type' => 'text',
                'text' => "Extract bid details from the following text:\n\n" . $rawText,
            ];
        }

        if ($storedImagePath) {
            $imageUrl = Storage::disk('public')->url($storedImagePath);
            $userContent[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => $imageUrl, 'detail' => 'low'],
            ];

            if (empty($rawText)) {
                $userContent[] = [
                    'type' => 'text',
                    'text' => 'Extract bid details from this screenshot.',
                ];
            }
        }

        if (empty($userContent)) {
            return response()->json(['message' => 'Geen inhoud om te verwerken.'], 422);
        }

        try {
            $model = count(array_filter($userContent, fn($c) => $c['type'] === 'image_url')) > 0
                ? 'gpt-4o-mini'
                : 'gpt-4o-mini';

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => $model,
                    'max_tokens'  => 512,
                    'temperature' => 0,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userContent],
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('[BidExtract] OpenAI error: ' . $response->body());
                return response()->json(['message' => 'AI extractie mislukt.'], 500);
            }

            $content = $response->json('choices.0.message.content', '{}');
            // Strip markdown code fences if present
            $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
            $content = preg_replace('/\s*```$/', '', $content);

            $extracted = json_decode($content, true);
            if (!is_array($extracted)) {
                $extracted = [];
            }

            return response()->json([
                'extracted'    => $extracted,
                'raw_text'     => $rawText ?: null,
                'stored_image' => $storedImagePath ? Storage::disk('public')->url($storedImagePath) : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('[BidExtract] Exception: ' . $e->getMessage());
            return response()->json(['message' => 'AI extractie mislukt: ' . $e->getMessage()], 500);
        }
    }
}
