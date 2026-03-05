<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\IssueReportService;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;

class ReportIssueController extends Controller
{
    public function store(Request $request, IssueReportService $reports)
    {
        $payload = $request->validate([
            'subject' => 'nullable|string|max:120',
            'description' => 'required|string|max:500',
            'page_url' => 'nullable|string|max:2048',
            'email' => 'nullable|email|max:255',
            'error_reference' => 'nullable|string|max:50',
            'source' => 'nullable|string|max:30',
            'language' => 'nullable|string|max:5',
            'conversation_id' => 'nullable|string|max:64',
            'message_id' => 'nullable|string|max:64',
            'visitor_id' => 'nullable|string|max:64',
            'session_jwt' => 'nullable|string',
            'timestamp' => 'nullable|string|max:50',
            'metadata' => 'nullable|array',
            'files' => 'nullable|array',
            'files.*.filename' => 'nullable|string|max:255',
            'files.*.filetype' => 'nullable|string|max:100',
            'files.*.content' => 'nullable|string',
            'screenshot' => 'nullable|array',
            'screenshot.filename' => 'nullable|string|max:255',
            'screenshot.filetype' => 'nullable|string|max:100',
            'screenshot.content' => 'nullable|string',
        ]);

        $user = $request->user('sanctum');
        $conversation = null;
        $message = null;

        if (!$user) {
            $conversationId = $payload['conversation_id'] ?? null;
            if ($conversationId) {
                $conversation = Conversation::find($conversationId);
            }

            if (!$conversation) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $visitorId = $payload['visitor_id'] ?? null;
            if (!empty($payload['session_jwt'])) {
                try {
                    $decoded = json_decode(Crypt::decryptString($payload['session_jwt']), true);
                    $visitorId = $decoded['visitor_id'] ?? $visitorId;
                } catch (\Throwable $e) {
                    return response()->json(['message' => 'Invalid session token'], 401);
                }
            }

            if ($conversation->visitor_id && $visitorId && $conversation->visitor_id !== $visitorId) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            if (!$payload['email'] && $conversation->contact?->email) {
                $payload['email'] = $conversation->contact->email;
            }
        }

        if (!empty($payload['message_id'])) {
            $message = Message::find($payload['message_id']);
        }

        if (!empty($payload['timestamp'])) {
            $payload['metadata'] = array_merge($payload['metadata'] ?? [], [
                'client_timestamp' => $payload['timestamp'],
            ]);
        }

        $this->validateUploads($request, $payload);

        $rateKey = $this->rateKey($user?->id, $payload['visitor_id'] ?? null, $request->ip());
        $limit = (int) env('REPORT_ISSUE_RATE_LIMIT', 3);
        if (RateLimiter::tooManyAttempts($rateKey, $limit)) {
            return response()->json(['message' => 'Too many reports. Please try again later.'], 429);
        }
        RateLimiter::hit($rateKey, 3600);

        $report = $reports->create($payload, $request, $user, $conversation, $message);

        $logData = $report->toArray();
        unset($logData['files'], $logData['file'], $logData['screenshot']);

        SystemLogService::log(
            'issue_reported',
            'IssueReport',
            $report->id,
            null,
            $logData,
            'User submitted an issue report',
            null
        );

        return response()->json([
            'message' => 'Report received',
            'report_id' => $report->id,
            'reference' => $report->error_reference ?? $report->platformError?->reference_code,
        ], 201);
    }

    private function rateKey(?int $userId, ?string $visitorId, ?string $ip): string
    {
        if ($userId) {
            return 'report-issue:user:' . $userId;
        }
        if ($visitorId) {
            return 'report-issue:visitor:' . $visitorId;
        }
        return 'report-issue:ip:' . ($ip ?? 'unknown');
    }

    private function validateUploads(Request $request, array $payload): void
    {
        $allowed = [
            'image/png', 'image/jpeg', 'image/webp',
            'application/pdf', 'text/plain', 'text/csv', 'application/json', 'text/x-log',
        ];
        $maxKb = (int) env('REPORT_ISSUE_MAX_FILE_KB', 10240);

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
            $mime = $file->getClientMimeType() ?: $file->getMimeType();
            if ($mime && !in_array($mime, $allowed, true)) {
                abort(422, 'Invalid file type.');
            }
            if ($file->getSize() > ($maxKb * 1024)) {
                abort(422, 'File too large.');
            }
        }

        foreach (['files', 'screenshot'] as $key) {
            $items = $payload[$key] ?? null;
            if ($items === null) {
                continue;
            }
            if ($key === 'screenshot') {
                $items = [$items];
            }
            foreach ($items as $item) {
                if (is_array($item)) {
                    $filetype = $item['filetype'] ?? null;
                    if ($filetype && !in_array($filetype, $allowed, true)) {
                        abort(422, 'Invalid file type.');
                    }
                }
            }
        }
    }
}
