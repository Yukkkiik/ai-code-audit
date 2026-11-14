<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Mengubah exception pada /api/* menjadi format error seragam (API_CONTRACT.md §1).
 * Pesan exception asli tidak pernah dikirim ke client, termasuk saat APP_DEBUG=true.
 */
class ApiExceptionRenderer
{
    /**
     * @var array<int, array{string, string}>
     */
    private const HTTP_ERRORS = [
        400 => ['BAD_REQUEST', 'Request tidak dapat diproses.'],
        401 => ['UNAUTHENTICATED', 'Silakan login terlebih dahulu.'],
        403 => ['FORBIDDEN', 'Anda tidak memiliki akses ke resource ini.'],
        404 => ['NOT_FOUND', 'Resource tidak ditemukan.'],
        405 => ['METHOD_NOT_ALLOWED', 'Method HTTP tidak didukung untuk endpoint ini.'],
        413 => ['PAYLOAD_TOO_LARGE', 'Ukuran data melebihi batas yang diizinkan.'],
        429 => ['RATE_LIMITED', 'Terlalu banyak permintaan. Coba lagi nanti.'],
    ];

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') || $e instanceof HttpResponseException) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return $this->error('VALIDATION_FAILED', 'Data yang dikirim tidak valid.', 422, $e->errors());
        }

        if ($e instanceof AuthenticationException) {
            return $this->error(...self::HTTP_ERRORS[401], status: 401);
        }

        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            $status = $e->getStatusCode();
            [$code, $message] = self::HTTP_ERRORS[$status] ?? self::HTTP_ERRORS[400];

            return $this->error($code, $message, $status, headers: $e->getHeaders());
        }

        return $this->error('INTERNAL_ERROR', 'Terjadi kesalahan pada server.', 500);
    }

    /**
     * @param  array<string, mixed>|null  $details
     * @param  array<string, string>  $headers
     */
    private function error(string $code, string $message, int $status, ?array $details = null, array $headers = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status, $headers);
    }
}
