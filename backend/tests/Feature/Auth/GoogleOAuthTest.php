<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use RuntimeException;
use Tests\Support\EnforcingCsrfToken;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND = 'https://app.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => self::FRONTEND,
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost:8000/api/v1/auth/google/callback',
        ]);
    }

    public function test_redirect_sends_user_to_google_with_state(): void
    {
        $response = $this->get('/api/v1/auth/google/redirect');

        $response->assertStatus(302);

        $location = parse_url($response->headers->get('Location'));
        parse_str($location['query'], $query);

        $this->assertSame('accounts.google.com', $location['host']);
        $this->assertSame('test-client-id', $query['client_id']);
        $this->assertSame('http://localhost:8000/api/v1/auth/google/callback', $query['redirect_uri']);
        $this->assertStringContainsString('email', $query['scope']);
        $this->assertNotEmpty($query['state']);
        $this->assertSame($query['state'], session('state'));
    }

    public function test_redirect_from_stateful_frontend_origin_still_stores_state(): void
    {
        $response = $this->get('/api/v1/auth/google/redirect', ['Referer' => 'http://localhost:3000/login']);

        $response->assertStatus(302);
        $this->assertNotEmpty(session('state'));
    }

    public function test_callback_creates_new_user_and_logs_in(): void
    {
        $this->fakeProviderReturning($this->googleUser());

        $this->get('/api/v1/auth/google/callback?code=abc&state=xyz')
            ->assertRedirect(self::FRONTEND);

        $user = User::where('google_id', '1234567890')->firstOrFail();
        $this->assertSame('Budi', $user->name);
        $this->assertSame('budi@example.com', $user->email);
        $this->assertSame('https://lh3.googleusercontent.com/a/x', $user->avatar_url);
        $this->assertNotNull($user->last_login_at);
        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->id, session(Auth::guard('web')->getName()));
    }

    public function test_callback_updates_existing_user_by_google_id(): void
    {
        $existing = User::factory()->create([
            'google_id' => '1234567890',
            'name' => 'Nama Lama',
            'email' => 'lama@example.com',
            'last_login_at' => null,
        ]);
        $this->fakeProviderReturning($this->googleUser(['name' => 'Budi Baru', 'email' => 'Baru@Example.com']));

        $this->get('/api/v1/auth/google/callback?code=abc&state=xyz')->assertRedirect(self::FRONTEND);

        $this->assertSame(1, User::count());
        $existing->refresh();
        $this->assertSame('Budi Baru', $existing->name);
        $this->assertSame('baru@example.com', $existing->email);
        $this->assertNotNull($existing->last_login_at);
        $this->assertAuthenticatedAs($existing);
    }

    public function test_existing_user_keeps_email_when_new_email_belongs_to_another_user(): void
    {
        $existing = User::factory()->create(['google_id' => '1234567890', 'email' => 'lama@example.com']);
        User::factory()->create(['google_id' => '999', 'email' => 'budi@example.com']);
        $this->fakeProviderReturning($this->googleUser());

        $this->get('/api/v1/auth/google/callback?code=abc&state=xyz')->assertRedirect(self::FRONTEND);

        $this->assertSame('lama@example.com', $existing->refresh()->email);
        $this->assertAuthenticatedAs($existing);
    }

    public function test_callback_rejects_unverified_email(): void
    {
        $this->fakeProviderReturning($this->googleUser(['email_verified' => false]));

        $this->get('/api/v1/auth/google/callback?code=abc&state=xyz')
            ->assertRedirect(self::FRONTEND.'/login?error=oauth_failed');

        $this->assertSame(0, User::count());
        $this->assertGuest('web');
    }

    public function test_callback_rejects_missing_email_verified_flag(): void
    {
        $this->fakeProviderReturning($this->googleUser(['email_verified' => null]));

        $this->get('/api/v1/auth/google/callback?code=abc&state=xyz')
            ->assertRedirect(self::FRONTEND.'/login?error=oauth_failed');

        $this->assertSame(0, User::count());
    }

    public function test_callback_rejects_email_owned_by_another_google_id(): void
    {
        $owner = User::factory()->create(['google_id' => '999', 'email' => 'budi@example.com']);
        $this->fakeProviderReturning($this->googleUser());

        $this->get('/api/v1/auth/google/callback?code=abc&state=xyz')
            ->assertRedirect(self::FRONTEND.'/login?error=oauth_failed');

        $this->assertSame(1, User::count());
        $this->assertSame('999', $owner->refresh()->google_id);
        $this->assertGuest('web');
    }

    public function test_callback_rejects_missing_google_id(): void
    {
        $this->fakeProviderReturning($this->googleUser(['sub' => '']));

        $this->get('/api/v1/auth/google/callback?code=abc&state=xyz')
            ->assertRedirect(self::FRONTEND.'/login?error=oauth_failed');

        $this->assertSame(0, User::count());
    }

    public function test_callback_with_access_denied_redirects_cancelled_without_calling_google(): void
    {
        Socialite::shouldReceive('driver')->never();

        $this->get('/api/v1/auth/google/callback?error=access_denied&state=xyz')
            ->assertRedirect(self::FRONTEND.'/login?error=oauth_cancelled');

        $this->assertGuest('web');
    }

    public function test_callback_with_other_error_redirects_failed(): void
    {
        Socialite::shouldReceive('driver')->never();

        $this->get('/api/v1/auth/google/callback?error=server_error')
            ->assertRedirect(self::FRONTEND.'/login?error=oauth_failed');
    }

    public function test_callback_with_invalid_state_redirects_failed_and_creates_no_user(): void
    {
        $this->get('/api/v1/auth/google/callback?code=abc&state=forged')
            ->assertRedirect(self::FRONTEND.'/login?error=oauth_failed');

        $this->assertSame(0, User::count());
        $this->assertGuest('web');
    }

    public function test_callback_regenerates_session_id_on_login(): void
    {
        $name = config('session.cookie');
        $before = $this->get('/api/v1/auth/google/redirect')->getCookie($name)->getValue();
        $this->fakeProviderReturning($this->googleUser());

        $after = $this->withCookie($name, $before)
            ->get('/api/v1/auth/google/callback?code=abc&state=xyz')
            ->assertRedirect(self::FRONTEND)
            ->getCookie($name)->getValue();

        $this->assertNotSame($before, $after);
        $this->assertSame('', session()->getHandler()->read($before));
    }

    public function test_callback_ignores_redirect_parameters_from_request(): void
    {
        $this->fakeProviderReturning($this->googleUser());

        $this->get('/api/v1/auth/google/callback?code=abc&state=xyz&redirect=https://evil.test&next=//evil.test')
            ->assertRedirect(self::FRONTEND);
    }

    public function test_provider_failure_redirects_failed_and_logs_without_secrets(): void
    {
        Log::spy();
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new RuntimeException('token=SECRET-TOKEN code=SECRET-CODE'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/api/v1/auth/google/callback?code=SECRET-CODE&state=xyz')
            ->assertRedirect(self::FRONTEND.'/login?error=oauth_failed');

        Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context = []) {
            $logged = $message.json_encode($context);

            return ! str_contains($logged, 'SECRET') && ($context['exception'] ?? null) === RuntimeException::class;
        });
    }

    public function test_stateful_origin_post_without_xsrf_token_is_rejected(): void
    {
        config(['sanctum.middleware.validate_csrf_token' => EnforcingCsrfToken::class]);

        $this->postJson('/api/v1/audits', [], ['Origin' => 'http://localhost:3000'])
            ->assertStatus(419)
            ->assertJsonPath('error.code', 'CSRF_TOKEN_MISMATCH');
    }

    public function test_request_without_stateful_origin_does_not_require_xsrf_token(): void
    {
        config(['sanctum.middleware.validate_csrf_token' => EnforcingCsrfToken::class]);

        $this->postJson('/api/v1/audits', [])->assertStatus(422);
    }

    public function test_csrf_cookie_endpoint_is_available(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function googleUser(array $overrides = []): SocialiteUser
    {
        $data = array_merge([
            'sub' => '1234567890',
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'picture' => 'https://lh3.googleusercontent.com/a/x',
            'email_verified' => true,
        ], $overrides);

        return (new SocialiteUser)->setRaw($data)->map([
            'id' => $data['sub'],
            'name' => $data['name'],
            'email' => $data['email'],
            'avatar' => $data['picture'],
        ]);
    }

    private function fakeProviderReturning(SocialiteUser $user): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
