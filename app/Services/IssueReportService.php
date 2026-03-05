<?php

namespace App\Services;

use App\Models\IssueReport;
use App\Models\IssueReportFile;
use App\Models\PlatformError;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IssueReportService
{
    public function __construct(
        private ErrorAiService $errorAi,
        private FaqPineconeService $faqPinecone
    ) {
    }

    public function create(array $payload, Request $request, ?User $user = null, ?Conversation $conversation = null, ?Message $message = null): IssueReport
    {
        $source = $payload['source'] ?? ($conversation ? 'chat' : 'form');
        $language = strtolower((string) ($payload['language'] ?? $request->header('Accept-Language') ?? 'en'));
        $language = substr($language, 0, 2);

        $platformError = $this->resolvePlatformError($payload, $user, $source);

        $metadata = $payload['metadata'] ?? [];
        $metadata['language'] = $language;
        $metadata['report_source'] = $source;

        $subject = $payload['subject'] ?? null;
        $description = $payload['description'] ?? '';
        $subject = $subject ? trim(strip_tags($subject)) : null;
        $description = trim(strip_tags($description));

        if ($conversation) {
            $metadata['conversation_id'] = $conversation->id;
            $metadata['chat_context'] = $this->buildChatContext($conversation, $message);
        }

        $faqMatches = $this->searchErrorFaq($payload['description'] ?? '', $language);
        if (!empty($faqMatches)) {
            $metadata['error_faq_matches'] = $faqMatches;
        }

        $report = IssueReport::create([
            'platform_error_id' => $platformError?->id,
            'user_id' => $user?->id,
            'conversation_id' => $conversation?->id,
            'message_id' => $message?->id,
            'email' => $payload['email'] ?? $user?->email,
            'subject' => $subject,
            'description' => $description,
            'page_url' => $payload['page_url'] ?? null,
            'error_reference' => $payload['error_reference'] ?? $platformError?->reference_code,
            'source' => $source,
            'status' => $payload['status'] ?? 'open',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata,
        ]);

        $this->storeFiles($report, $request, $payload['files'] ?? [], $payload['screenshot'] ?? null);

        return $report;
    }

    public function storeFiles(IssueReport $report, Request $request, array $filesPayload = [], mixed $screenshotPayload = null): void
    {
        $disk = 'public';
        $pathPrefix = 'issue-reports/' . $report->id;

        $uploadedFiles = $request->file('files', []);
        if ($request->file('file')) {
            $uploadedFiles[] = $request->file('file');
        }

        if ($request->file('screenshot')) {
            $uploadedFiles[] = $request->file('screenshot');
        }

        foreach ($uploadedFiles as $file) {
            if (!$file) {
                continue;
            }
            $stored = $file->storePublicly($pathPrefix, $disk);
            IssueReportFile::create([
                'issue_report_id' => $report->id,
                'storage_disk' => $disk,
                'storage_path' => $stored,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        if (is_array($filesPayload)) {
            foreach ($filesPayload as $item) {
                $this->storeBase64File($report, $item, $pathPrefix, $disk);
            }
        }

        if (is_array($screenshotPayload)) {
            $this->storeBase64File($report, $screenshotPayload, $pathPrefix, $disk);
        }
    }

    private function storeBase64File(IssueReport $report, array $item, string $pathPrefix, string $disk): void
    {
        $content = $item['content'] ?? null;
        if (!$content || !is_string($content)) {
            return;
        }

        $decoded = $this->decodeBase64($content);
        if ($decoded === null) {
            return;
        }

        $maxKb = (int) env('REPORT_ISSUE_MAX_FILE_KB', 10240);
        if (strlen($decoded) > ($maxKb * 1024)) {
            return;
        }

        $extension = $this->guessExtension($item['filetype'] ?? null, $item['filename'] ?? null);
        $filename = $this->safeFilename($item['filename'] ?? null, $extension);
        $storagePath = trim($pathPrefix . '/' . $filename, '/');

        Storage::disk($disk)->put($storagePath, $decoded);

        IssueReportFile::create([
            'issue_report_id' => $report->id,
            'storage_disk' => $disk,
            'storage_path' => $storagePath,
            'original_name' => $item['filename'] ?? $filename,
            'mime_type' => $item['filetype'] ?? null,
            'size' => strlen($decoded),
        ]);
    }

    private function decodeBase64(string $content): ?string
    {
        if (str_contains($content, ',')) {
            $parts = explode(',', $content, 2);
            $content = $parts[1] ?? '';
        }

        $decoded = base64_decode($content, true);
        return $decoded === false ? null : $decoded;
    }

    private function safeFilename(?string $filename, string $extension): string
    {
        $base = $filename ? pathinfo($filename, PATHINFO_FILENAME) : 'attachment';
        $base = Str::slug($base);
        if ($base === '') {
            $base = 'attachment';
        }
        return $base . '-' . Str::random(8) . ($extension ? '.' . $extension : '');
    }

    private function guessExtension(?string $mimeType, ?string $filename): string
    {
        if ($filename && str_contains($filename, '.')) {
            return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        }
        if (!$mimeType) {
            return 'bin';
        }
        return match (strtolower($mimeType)) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'text/plain' => 'txt',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }

    private function resolvePlatformError(array $payload, ?User $user, string $source): ?PlatformError
    {
        $reference = $payload['error_reference'] ?? null;
        $error = null;

        if ($reference) {
            $error = PlatformError::where('reference_code', $reference)->first();
        }

        if ($error) {
            $error->occurrences_count = ($error->occurrences_count ?? 0) + 1;
            $error->users_affected = ($error->users_affected ?? 0) + 1;
            $error->last_seen_at = now();
            $error->save();
            return $error;
        }

        $title = $payload['subject'] ?? 'User reported issue';
        $title = trim(strip_tags((string) $title));
        $message = $payload['description'] ?? null;
        $message = $message ? trim(strip_tags((string) $message)) : null;

        $error = new PlatformError();
        $error->title = $title;
        $error->message = $message;
        $error->level = 'error';
        $error->project = 'manual';
        $error->environment = app()->environment();
        $error->source = $source;
        $error->route = $payload['page_url'] ?? null;
        $error->url = $payload['page_url'] ?? null;
        $error->occurrences_count = 1;
        $error->users_affected = 1;
        $error->first_seen_at = now();
        $error->last_seen_at = now();
        $error->status = 'unresolved';
        $error->tags = array_filter([
            'user_id' => $user?->id,
            'email' => $payload['email'] ?? $user?->email,
            'source' => $source,
        ]);
        $error->save();

        $summary = $this->errorAi->summarize([
            'error_type' => 'user_report',
            'message' => $message,
            'source' => $source,
            'route' => $payload['page_url'] ?? null,
            'tags' => $error->tags ?? [],
            'environment' => $error->environment,
        ]);

        if ($summary) {
            $error->ai_category = $summary['category'] ?? $error->ai_category;
            $error->ai_severity = $summary['severity'] ?? $error->ai_severity;
            $error->ai_dev_summary = $summary['dev_summary'] ?? $error->ai_dev_summary;
            $error->ai_user_message_nl = Arr::get($summary, 'user_message.nl') ?? $error->ai_user_message_nl;
            $error->ai_user_message_en = Arr::get($summary, 'user_message.en') ?? $error->ai_user_message_en;
            $error->ai_user_message_de = Arr::get($summary, 'user_message.de') ?? $error->ai_user_message_de;
            $error->ai_user_steps = $summary['user_steps'] ?? $error->ai_user_steps;
            $error->ai_suggested_checks = $summary['suggested_checks'] ?? $error->ai_suggested_checks;
            $error->save();
        }

        return $error;
    }

    private function buildChatContext(Conversation $conversation, ?Message $message): array
    {
        $messages = $conversation->messages()
            ->latest()
            ->limit(5)
            ->get()
            ->reverse()
            ->map(function (Message $msg) {
                return [
                    'id' => $msg->id,
                    'sender_type' => $msg->sender_type,
                    'text' => $msg->text,
                    'created_at' => $msg->created_at?->toDateTimeString(),
                ];
            })
            ->values()
            ->toArray();

        return [
            'conversation_id' => $conversation->id,
            'harbor_id' => $conversation->harbor_id,
            'message_id' => $message?->id,
            'messages' => $messages,
        ];
    }

    private function searchErrorFaq(string $query, string $language): array
    {
        if (trim($query) === '') {
            return [];
        }

        $matches = $this->faqPinecone->query($query, $language, null, 3, 'ERRORS');
        return array_map(function ($match) {
            return [
                'score' => $match['score'] ?? 0,
                'metadata' => $match['metadata'] ?? [],
            ];
        }, $matches);
    }
}
