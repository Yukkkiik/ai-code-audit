<?php

namespace App\Services\Access;

use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

/**
 * Batas pembuatan audit per hari kalender (D-02): guest per IP, user per akun.
 * Hitungan reset pukul 00:00 pada `audit.rate_limits.timezone`.
 */
class AuditSubmissionLimiter
{
    public function __construct(private readonly RateLimiter $limiter) {}

    /**
     * Memakai satu jatah dan mengembalikan kunci untuk release().
     *
     * @throws ThrottleRequestsException jika jatah hari ini habis (jatah tidak terpakai)
     */
    public function hit(Request $request, ?User $user): string
    {
        $config = config('audit.rate_limits');
        $now = now($config['timezone']);
        $max = $user === null ? $config['guest_audits_per_day'] : $config['user_audits_per_day'];
        $subject = $user === null ? 'guest:'.self::guestFingerprint($request) : 'user:'.$user->id;
        $key = 'audit-submit:'.$subject.':'.$now->format('Y-m-d');

        // Increment atomik: dua request bersamaan tidak dapat melewati batas.
        $attempts = $this->limiter->hit($key, max(1, (int) ceil($now->diffInSeconds($now->copy()->addDay()->startOfDay()))));

        if ($attempts > $max) {
            $this->limiter->decrement($key);

            throw new ThrottleRequestsException('Too Many Attempts.', null, [
                'Retry-After' => $this->limiter->availableIn($key),
                'X-RateLimit-Limit' => $max,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        return $key;
    }

    /**
     * Mengembalikan jatah bila audit akhirnya tidak terbentuk (mis. antrean tidak tersedia).
     */
    public function release(string $key): void
    {
        $this->limiter->decrement($key);
    }

    /**
     * IP guest tidak disimpan mentah (NFR-09): hanya HMAC-nya yang menjadi bagian kunci cache.
     */
    public static function guestFingerprint(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
    }
}
