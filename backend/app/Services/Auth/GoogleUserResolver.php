<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Mencocokkan identitas Google (`sub`) dengan akun lokal. Akun tidak pernah digabung lewat email:
 * email yang sudah dimiliki akun lain ditolak agar akun tidak dapat diambil alih.
 */
class GoogleUserResolver
{
    private const MAX_NAME = 255;

    private const MAX_EMAIL = 255;

    private const MAX_AVATAR = 2048;

    /**
     * @return User|null null bila identitas tidak dapat dipakai (email milik akun lain, data tidak valid)
     */
    public function resolve(string $googleId, ?string $name, ?string $email, ?string $avatarUrl): ?User
    {
        $email = mb_strtolower(trim((string) $email));

        if ($googleId === '' || strlen($googleId) > 64 || $email === '' || strlen($email) > self::MAX_EMAIL) {
            return null;
        }

        $name = mb_substr(trim((string) $name) ?: $email, 0, self::MAX_NAME);
        $avatarUrl = ($avatarUrl !== null && strlen($avatarUrl) <= self::MAX_AVATAR) ? $avatarUrl : null;

        $user = User::where('google_id', $googleId)->first();

        if ($user === null) {
            return $this->create($googleId, $name, $email, $avatarUrl);
        }

        $emailTaken = $email !== $user->email
            && User::where('email', $email)->where('id', '!=', $user->id)->exists();

        $user->forceFill([
            'name' => $name,
            'avatar_url' => $avatarUrl,
            'last_login_at' => now(),
        ] + ($emailTaken ? [] : ['email' => $email]))->save();

        return $user;
    }

    private function create(string $googleId, string $name, string $email, ?string $avatarUrl): ?User
    {
        if (User::where('email', $email)->exists()) {
            return null;
        }

        try {
            return User::create([
                'google_id' => $googleId,
                'name' => $name,
                'email' => $email,
                'avatar_url' => $avatarUrl,
                'last_login_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Request paralel membuat akun yang sama lebih dulu; perlakukan sebagai konflik.
            return null;
        }
    }
}
