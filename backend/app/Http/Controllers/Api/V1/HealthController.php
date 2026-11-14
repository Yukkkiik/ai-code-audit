<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
        ];

        $healthy = ! in_array('fail', $checks, true);

        return response()->json([
            'data' => [
                'status' => $healthy ? 'ok' : 'degraded',
                'checks' => $checks,
                'time' => now()->utc()->toIso8601ZuluString(),
            ],
        ], $healthy ? 200 : 503);
    }

    /**
     * Detail kegagalan hanya dicatat di log, tidak dikirim ke client.
     */
    private function checkDatabase(): string
    {
        try {
            DB::connection()->select('SELECT 1');

            return 'ok';
        } catch (Throwable $e) {
            Log::warning('Health check: database tidak dapat dijangkau.', ['exception' => $e]);

            return 'fail';
        }
    }
}
