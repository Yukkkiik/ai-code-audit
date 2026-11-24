<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\EnforcingCsrfToken;
use Tests\Support\LogsInViaGoogle;
use Tests\TestCase;

class SessionLogoutTest extends TestCase
{
    use LogsInViaGoogle, RefreshDatabase;

    public function test_me_returns_profile_without_internal_fields_and_is_not_cacheable(): void
    {
        $user = User::factory()->create(['name' => 'Budi', 'email' => 'budi@example.com', 'avatar_url' => 'https://x.test/a.png']);
        $cookie = $this->loginViaCallback($user);

        $response = $this->withSessionCookie($cookie)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertExactJsonStructure(['data' => ['id', 'name', 'email', 'avatar_url']])
            ->assertJsonPath('data.name', 'Budi')
            ->assertJsonPath('data.email', 'budi@example.com')
            ->assertJsonPath('data.avatar_url', 'https://x.test/a.png');
        $this->assertStringNotContainsString($user->google_id, $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_me_requires_login(): void
    {
        $this->withoutSessionCookie()->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_logout_returns_204_destroys_server_side_session_and_rejects_old_cookie(): void
    {
        $cookie = $this->loginViaCallback(User::factory()->create());
        $sessionId = session()->getId();
        $this->assertNotSame('', session()->getHandler()->read($sessionId));

        $this->withSessionCookie($cookie)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame('', session()->getHandler()->read($sessionId));
        $this->withSessionCookie($cookie)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_regenerates_csrf_token(): void
    {
        $cookie = $this->loginViaCallback(User::factory()->create());
        $tokenBefore = session()->token();

        $this->withSessionCookie($cookie)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertNotSame($tokenBefore, session()->token());
    }

    public function test_logout_from_frontend_origin_requires_xsrf_token(): void
    {
        config(['sanctum.middleware.validate_csrf_token' => EnforcingCsrfToken::class]);
        $cookie = $this->loginViaCallback(User::factory()->create());
        $csrf = session()->token();

        $this->withSessionCookie($cookie)->postJson('/api/v1/auth/logout')
            ->assertStatus(419)
            ->assertJsonPath('error.code', 'CSRF_TOKEN_MISMATCH');
        $this->withSessionCookie($cookie)->getJson('/api/v1/auth/me')->assertOk();

        $this->withSessionCookie($cookie)->postJson('/api/v1/auth/logout', [], ['X-CSRF-TOKEN' => $csrf])->assertNoContent();
        $this->withSessionCookie($cookie)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_requires_login(): void
    {
        $this->withoutSessionCookie()->postJson('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_expired_session_is_rejected(): void
    {
        $cookie = $this->loginViaCallback(User::factory()->create());
        session()->getHandler()->destroy(session()->getId());

        $this->withSessionCookie($cookie)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withSessionCookie($cookie)->getJson('/api/v1/audits')->assertUnauthorized();
    }

    public function test_session_lifetime_is_configured_as_idle_timeout(): void
    {
        $this->assertSame(120, config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));
    }
}
