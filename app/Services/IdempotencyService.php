<?php

namespace App\Services;

use App\Models\IdempotencyKey;
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
        return $request->method() . ':' . '/' . ltrim($request->path(), '/');
    }

    public function requestHash(Request $request): string
    {
        $payload = [
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
        if (!$key) {
            return ['status' => 'missing'];
        }

        $user = $request->user();
        if (!$user) {
            return ['status' => 'unauthorized'];
        }

        $scope = $this->scope($request);
        $requestHash = $this->requestHash($request);

        $record = IdempotencyKey::query()
            ->where('user_id', $user->id)
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

            return ['status' => 'processing'];
        }

        $record = IdempotencyKey::create([
            'user_id' => $user->id,
            'scope' => $scope,
            'idempotency_key' => $key,
            'request_hash' => $requestHash,
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);

        return ['status' => 'new', 'record' => $record];
    }

    public function storeResponse(IdempotencyKey $record, Response $response): void
    {
        if ($record->response_status !== null) {
            return;
        }

        $body = null;
        if (method_exists($response, 'getContent')) {
            $body = $response->getContent();
        }

        $record->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => $body,
            'response_headers' => $response->headers->all(),
        ]);
    }

    public function replayResponse(IdempotencyKey $record): Response
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

    private function isJsonResponse(IdempotencyKey $record): bool
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
            if (is_array($values)) {
                $flattened[$name] = $values[0] ?? null;
            } else {
                $flattened[$name] = $values;
            }
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
