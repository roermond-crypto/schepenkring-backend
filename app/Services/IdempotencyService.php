<?php

namespace App\Services;

use App\Models\IdempotencyRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyService
{
    public function resolveKey(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');
        return $key !== '' ? $key : null;
    }

    public function scope(Request $request): string
    {
        $actionKey = $request->attributes->get('action_key');
        if (is_string($actionKey) && $actionKey !== '') {
            return 'action:' . $actionKey;
        }

        return $request->method() . ':' . '/' . ltrim($request->path(), '/');
    }

    public function requestHash(Request $request): string
    {
        $payload = [
            'action' => $request->attributes->get('action_key'),
            'method' => $request->method(),
            'path' => '/' . ltrim($request->path(), '/'),
            'query' => $this->normalizeArray($request->query()),
            'body' => $this->normalizeArray($this->redact($request->input())),
        ];

        return hash('sha256', json_encode($payload));
    }

    public function begin(Request $request, int $ttlSeconds = 900): array
    {
        $key = $this->resolveKey($request);
        if (! $key) {
            return ['status' => 'missing'];
        }

        $scope = $this->scope($request);
        $requestHash = $this->requestHash($request);
        $ownerKey = $this->ownerKey($request);

        $record = IdempotencyRecord::query()
            ->where('owner_key', $ownerKey)
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->first();

        if ($record) {
            if ($record->request_hash !== $requestHash) {
                return ['status' => 'conflict'];
            }

            if ($record->response_status !== null) {
                return ['status' => 'replay', 'response' => $this->replayResponse($record)];
            }

            if ($record->expires_at && $record->expires_at->isPast()) {
                $record->delete();
            } else {
                return ['status' => 'processing'];
            }
        }

        $record = IdempotencyRecord::create([
            'owner_key' => $ownerKey,
            'scope' => $scope,
            'idempotency_key' => $key,
            'request_hash' => $requestHash,
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);

        return ['status' => 'new', 'record' => $record];
    }

    public function storeResponse(IdempotencyRecord $record, Response $response): void
    {
        if ($record->response_status !== null) {
            return;
        }

        $body = method_exists($response, 'getContent') ? $response->getContent() : null;

        $record->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => $body,
            'response_headers' => $response->headers->all(),
        ]);
    }

    public function replayResponse(IdempotencyRecord $record): Response
    {
        $status = $record->response_status ?? 200;
        $headers = $record->response_headers ?? [];

        if ($this->isJsonResponse($record)) {
            return new JsonResponse(
                json_decode((string) $record->response_body, true),
                $status,
                $this->flattenHeaders($headers)
            );
        }

        return new Response(
            $record->response_body ?? '',
            $status,
            $this->flattenHeaders($headers)
        );
    }

    private function ownerKey(Request $request): string
    {
        if ($request->user()) {
            return 'user:' . $request->user()->id;
        }

        $visitorId = $request->input('visitor_id') ?? $request->input('visitorId');
        if ($visitorId) {
            return 'visitor:' . $visitorId;
        }

        $conversationId = $request->route('conversationId') ?? $request->input('conversation_id');
        if ($conversationId) {
            return 'conversation:' . $conversationId;
        }

        $leadId = $request->route('leadId') ?? $request->input('lead_id');
        if ($leadId) {
            return 'lead:' . $leadId;
        }

        return 'ip:' . $request->ip();
    }

    private function isJsonResponse(IdempotencyRecord $record): bool
    {
        $headers = $record->response_headers ?? [];
        $contentTypes = $headers['content-type'] ?? $headers['Content-Type'] ?? null;
        if (is_array($contentTypes)) {
            $contentTypes = $contentTypes[0] ?? null;
        }
        return is_string($contentTypes) && str_contains($contentTypes, 'application/json');
    }

    private function flattenHeaders(array $headers): array
    {
        $flattened = [];
        foreach ($headers as $name => $values) {
            $flattened[$name] = is_array($values) ? ($values[0] ?? null) : $values;
        }
        return $flattened;
    }

    private function redact(array $payload): array
    {
        $redactKeys = [
            'password',
            'token',
            'authorization',
            'idempotency_key',
            'otp',
        ];

        foreach ($redactKeys as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }

    private function normalizeArray(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->normalizeArray($value);
            }
        }

        ksort($payload);

        return $payload;
    }
}
