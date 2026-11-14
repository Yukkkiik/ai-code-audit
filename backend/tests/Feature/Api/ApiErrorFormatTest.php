<?php

namespace Tests\Feature\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiErrorFormatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/_test')->group(function () {
            Route::post('/validation', fn (Request $request) => $request->validate(['name' => 'required']));
            Route::get('/unauthenticated', fn () => throw new AuthenticationException);
            Route::get('/forbidden', fn () => throw new AuthorizationException('Rahasia internal'));
            Route::get('/throttled', fn () => throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => 60]));
            Route::get('/crash', fn () => throw new RuntimeException('SQLSTATE[HY000] /Users/secret/path.php'));
        });
    }

    public function test_unknown_api_route_returns_not_found_envelope(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertExactJsonStructure(['error' => ['code', 'message', 'details']])
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_api_errors_are_json_even_without_accept_header(): void
    {
        $this->get('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_wrong_method_returns_method_not_allowed_envelope(): void
    {
        $this->postJson('/api/v1/health')
            ->assertStatus(405)
            ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
    }

    public function test_validation_error_returns_field_details(): void
    {
        $this->postJson('/api/_test/validation', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['name']]]);
    }

    public function test_unauthenticated_returns_401_envelope(): void
    {
        $this->getJson('/api/_test/unauthenticated')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_forbidden_returns_403_without_internal_message(): void
    {
        $this->getJson('/api/_test/forbidden')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN')
            ->assertDontSee('Rahasia internal');
    }

    public function test_throttled_returns_429_with_retry_after(): void
    {
        $this->getJson('/api/_test/throttled')
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertHeader('Retry-After', '60');
    }

    public function test_unexpected_error_hides_details_even_in_debug_mode(): void
    {
        Exceptions::fake();
        config(['app.debug' => true]);

        $this->getJson('/api/_test/crash')
            ->assertStatus(500)
            ->assertExactJsonStructure(['error' => ['code', 'message', 'details']])
            ->assertJsonPath('error.code', 'INTERNAL_ERROR')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('/Users/secret');

        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_web_routes_keep_default_error_rendering(): void
    {
        $response = $this->get('/does-not-exist')->assertNotFound();

        $this->assertStringNotContainsString(
            'application/json',
            (string) $response->headers->get('Content-Type'),
        );
    }
}
