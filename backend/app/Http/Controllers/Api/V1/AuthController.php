<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\Auth\GoogleUserResolver;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Semua hasil berupa redirect ke frontend; tujuan hanya dari konfigurasi, tidak pernah dari input request.
     */
    public function callback(Request $request, GoogleUserResolver $resolver): RedirectResponse
    {
        if ($request->query->has('error')) {
            return $this->failure($request->query('error') === 'access_denied' ? 'oauth_cancelled' : 'oauth_failed', 'provider_error');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Exception $e) {
            // Pesan exception dapat memuat code/token, jadi hanya kelasnya yang dicatat.
            return $this->failure('oauth_failed', 'exchange_failed', ['exception' => $e::class]);
        }

        // Hanya `true` yang diterima (D-15): flag hilang atau bukan boolean dianggap belum terverifikasi.
        if (($google->user['email_verified'] ?? null) !== true) {
            return $this->failure('oauth_failed', 'email_not_verified');
        }

        $user = $resolver->resolve((string) $google->getId(), $google->getName(), $google->getEmail(), $google->getAvatar());

        if ($user === null) {
            return $this->failure('oauth_failed', 'identity_rejected');
        }

        // login() sudah meregenerasi session dan menghapus ID lama (anti session fixation).
        Auth::guard('web')->login($user);

        return redirect()->away($this->frontendUrl());
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Session dihapus dari Redis (bukan hanya cookie) dan token CSRF diganti.
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * @param  array<string, string>  $context
     */
    private function failure(string $error, string $reason, array $context = []): RedirectResponse
    {
        Log::warning('Login Google gagal.', ['reason' => $reason] + $context);

        return redirect()->away($this->frontendUrl().'/login?error='.$error);
    }

    private function frontendUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/');
    }
}
