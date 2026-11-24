<?php

namespace Tests\Support;

use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;

/**
 * Login melalui callback OAuth sungguhan (Google di-fake) dan memakai cookie session hasilnya,
 * agar jalur pemilik diuji dengan session nyata, bukan actingAs().
 */
trait LogsInViaGoogle
{
    private const FRONTEND_ORIGIN = ['Origin' => 'http://localhost:3000'];

    /**
     * @return string ID session yang dikirim server lewat cookie (sudah didekripsi; harness mengenkripsi ulang saat dikirim)
     */
    private function loginViaCallback(User $user): string
    {
        $google = (new SocialiteUser)
            ->setRaw(['sub' => $user->google_id, 'email_verified' => true])
            ->map(['id' => $user->google_id, 'name' => $user->name, 'email' => $user->email, 'avatar' => $user->avatar_url]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/api/v1/auth/google/callback?code=abc&state=xyz')->assertStatus(302);

        $this->app['auth']->forgetGuards();

        return $response->getCookie(config('session.cookie'))->getValue();
    }

    /**
     * Request berikutnya membawa cookie session dari origin frontend, tanpa sisa state request sebelumnya.
     */
    private function withSessionCookie(string $cookie): static
    {
        $this->forgetRequestState();

        // withCredentials(): request JSON hanya membawa cookie bila diaktifkan.
        return $this->withCredentials()->withCookie(config('session.cookie'), $cookie)->withHeaders(self::FRONTEND_ORIGIN);
    }

    private function withoutSessionCookie(): static
    {
        $this->forgetRequestState();
        $this->defaultCookies = [];

        return $this->withHeaders(self::FRONTEND_ORIGIN);
    }

    /**
     * Guard `web` dan store session hidup di memori selama test dan membawa user/atribut request
     * sebelumnya; tanpa dibersihkan, cookie tidak benar-benar diuji.
     */
    private function forgetRequestState(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['session.store']->flush();
    }
}
