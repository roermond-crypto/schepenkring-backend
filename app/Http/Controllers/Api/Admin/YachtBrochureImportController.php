<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YachtBrochureImportController extends Controller
{
    /**
     * POST /api/admin/yachts/import-brochure
     *
     * Fetches a brochure URL (HTML or PDF redirect), strips tags, and returns
     * structured hint text that the frontend feeds into the AI extraction pipeline.
     */
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brochure_url' => 'required|url|max:2048',
            'yacht_id'     => 'nullable|integer|exists:yachts,id',
        ]);

        $url     = $validated['brochure_url'];
        $yachtId = $validated['yacht_id'] ?? null;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; SchepenkringBot/1.0)',
                'Accept'     => 'text/html,application/xhtml+xml,application/pdf,*/*',
            ])->timeout(30)->get($url);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => "Could not fetch brochure URL (HTTP {$response->status()})",
                ], 422);
            }

            $contentType = strtolower($response->header('Content-Type') ?? '');
            $rawBody     = $response->body();

            if (str_contains($contentType, 'pdf')) {
                // PDF: extract readable portions (crude text extraction for hint)
                $hintText = $this->extractFromPdf($rawBody, $url);
            } else {
                // HTML
                $hintText = $this->extractFromHtml($rawBody, $url);
            }

            // Save hint to yacht owners_comment if yacht_id provided
            if ($yachtId) {
                $yacht = Yacht::findOrFail($yachtId);
                $yacht->forceFill(['owners_comment' => mb_substr($hintText, 0, 4000)])->save();

                $this->logImport($yacht, $url, mb_strlen($hintText), $request);
            }

            // Extract obvious metadata from hint text for quick preview
            $meta = $this->extractQuickMeta($hintText);

            return response()->json([
                'success'              => true,
                'hint_text'            => mb_substr($hintText, 0, 4000),
                'hint_text_length'     => mb_strlen($hintText),
                'source_url'           => $url,
                'yacht_id'             => $yachtId,
                'quick_meta'           => $meta,
                'pipeline_ready'       => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[YachtBrochureImport] Failed to fetch brochure', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to import brochure: ' . $e->getMessage(),
            ], 422);
        }
    }

    // ── Private helpers ──────────────────────────────────────────

    private function extractFromHtml(string $html, string $sourceUrl): string
    {
        // Remove script, style, nav, footer, header blocks
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<(nav|footer|header|aside)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;

        // Convert <br>, <p>, <li> to newlines
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|li|tr|h[1-6]|div)>/i', "\n", $html) ?? $html;

        // Extract image src values that might contain useful identifiers
        preg_match_all('/src=["\']([^"\']+\.(?:jpg|jpeg|png|webp))["\']/', $html, $imgMatches);
        $imageUrls = array_slice($imgMatches[1] ?? [], 0, 20);

        // Strip all remaining HTML tags
        $text = strip_tags($html);

        // Clean up whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        $text = trim($text);

        // Append image URLs if found (helpful for AI)
        if (!empty($imageUrls)) {
            $text .= "\n\n--- Afbeeldingen ---\n" . implode("\n", $imageUrls);
        }

        $text = "Bron: {$sourceUrl}\n\n" . $text;

        return $text;
    }

    private function extractFromPdf(string $pdfData, string $sourceUrl): string
    {
        // Basic text extraction from PDF binary (ASCII-range readable text)
        // Extract strings between parentheses (PDF text operators use "(text) Tj")
        preg_match_all('/\(([^\)]{2,200})\)\s*T[jJ]/', $pdfData, $matches);
        $chunks = $matches[1] ?? [];

        // Also look for BT...ET blocks with raw text
        preg_match_all('/\(([a-zA-Z0-9\s\.,\-\/\:\!\?éèëêàâùûôöïîœæ€]{3,})\)/', $pdfData, $m2);
        $chunks = array_merge($chunks, $m2[1] ?? []);

        // Deduplicate, filter short noise
        $chunks = array_unique(array_filter($chunks, fn ($c) => mb_strlen($c) > 4));

        $text = implode("\n", $chunks);
        $text = trim($text);

        if (mb_strlen($text) < 50) {
            // Fallback: binary to readable ASCII extraction
            $text = preg_replace('/[^\x20-\x7E\n\r]/', ' ', $pdfData) ?? '';
            $text = preg_replace('/\s{2,}/', ' ', $text) ?? '';
            $text = mb_substr($text, 0, 6000);
        }

        return "Bron: {$sourceUrl}\n\n" . $text;
    }

    /**
     * Quick regex-based metadata extraction for instant preview in UI.
     */
    private function extractQuickMeta(string $text): array
    {
        $meta = [];

        // Year (4-digit between 1950–2030)
        if (preg_match('/\b(19[5-9]\d|20[0-3]\d)\b/', $text, $m)) {
            $meta['year'] = (int) $m[1];
        }

        // Price (€ or EUR followed by number)
        if (preg_match('/(?:€|EUR)\s*([\d\.]+(?:,\d{2})?)/i', $text, $m)) {
            $meta['price'] = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }

        // Length (e.g. "12,50 m" or "12.50m" or "LOA: 12.5")
        if (preg_match('/(?:LOA|lengte|length)[:\s]+(\d{1,3}[,\.]\d{1,2})\s*m/i', $text, $m)) {
            $meta['loa'] = (float) str_replace(',', '.', $m[1]);
        }

        // Boat name — first non-trivial capitalised line
        $lines = array_filter(explode("\n", $text), fn ($l) => mb_strlen(trim($l)) > 5);
        foreach (array_slice($lines, 0, 10) as $line) {
            $line = trim($line);
            if (preg_match('/^[A-Z][a-zA-Z\s\-]+\d{4}/', $line)) {
                $meta['boat_name'] = $line;
                break;
            }
        }

        // Horsepower
        if (preg_match('/(\d{2,4})\s*(?:pk|hp|PK|HP|kW)/i', $text, $m)) {
            $meta['horse_power'] = (int) $m[1];
        }

        return $meta;
    }

    private function logImport(Yacht $yacht, string $url, int $textLength, Request $request): void
    {
        try {
            AuditLog::create([
                'action'      => 'yacht.brochure_imported',
                'risk_level'  => 'low',
                'result'      => 'success',
                'actor_id'    => $request->user()?->id,
                'location_id' => $yacht->location_id,
                'entity_type' => 'yacht',
                'entity_id'   => $yacht->id,
                'meta'        => [
                    'brochure_url'    => $url,
                    'text_length'     => $textLength,
                    'yacht_id'        => $yacht->id,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[YachtBrochureImport] Audit log failed: ' . $e->getMessage());
        }
    }
}
