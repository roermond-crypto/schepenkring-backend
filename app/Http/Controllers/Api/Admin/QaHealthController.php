<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\User;
use App\Models\Yacht;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

class QaHealthController extends Controller
{
    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
            'models' => $this->checkModels(),
            'openai_key' => $this->checkEnvKey('OPENAI_API_KEY'),
            'vonage_key' => $this->checkEnvKey('VONAGE_API_KEY'),
            'signhost_key' => $this->checkEnvKey('SIGNHOST_API_KEY'),
            'pinecone_key' => $this->checkEnvKey('PINECONE_API_KEY'),
        ];

        $passing = collect($checks)->every(fn ($c) => $c['status'] === 'ok');

        return response()->json([
            'status' => $passing ? 'healthy' : 'degraded',
            'checks' => $checks,
            'generated_at' => now()->toIso8601String(),
        ], $passing ? 200 : 207);
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');
            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'qa_health_ping_' . now()->timestamp;
            Cache::put($key, 1, 5);
            $hit = Cache::get($key) === 1;
            Cache::forget($key);
            return $hit ? ['status' => 'ok'] : ['status' => 'fail', 'error' => 'Cache read/write mismatch'];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            $size = Queue::size();
            return ['status' => 'ok', 'queue_size' => $size];
        } catch (Throwable $e) {
            return ['status' => 'warn', 'error' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $writable = Storage::disk('local')->put('qa_health_check.tmp', '1');
            Storage::disk('local')->delete('qa_health_check.tmp');
            return $writable ? ['status' => 'ok'] : ['status' => 'fail', 'error' => 'Storage not writable'];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkModels(): array
    {
        try {
            $counts = [
                'locations' => Location::count(),
                'yachts' => Yacht::count(),
                'users' => User::count(),
            ];
            return ['status' => 'ok', 'counts' => $counts];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }

    private function checkEnvKey(string $key): array
    {
        return config(strtolower(str_replace('_KEY', '', $key)) . '.api_key') || env($key)
            ? ['status' => 'ok']
            : ['status' => 'warn', 'error' => "{$key} not configured"];
    }
}
