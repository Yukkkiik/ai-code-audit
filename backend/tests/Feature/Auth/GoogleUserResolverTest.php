<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\GoogleUserResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleUserResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_name_falls_back_to_email_and_long_name_is_truncated(): void
    {
        $resolver = new GoogleUserResolver;

        $blank = $resolver->resolve('1', '  ', 'a@example.com', null);
        $long = $resolver->resolve('2', str_repeat('n', 400), 'b@example.com', null);

        $this->assertSame('a@example.com', $blank->name);
        $this->assertSame(255, mb_strlen($long->name));
    }

    public function test_oversized_avatar_is_dropped_but_login_still_works(): void
    {
        $user = (new GoogleUserResolver)->resolve('1', 'Budi', 'a@example.com', 'https://x.test/'.str_repeat('a', 2100));

        $this->assertNotNull($user);
        $this->assertNull($user->avatar_url);
    }

    public function test_invalid_identity_is_rejected(): void
    {
        $resolver = new GoogleUserResolver;

        $this->assertNull($resolver->resolve('', 'Budi', 'a@example.com', null));
        $this->assertNull($resolver->resolve(str_repeat('1', 65), 'Budi', 'a@example.com', null));
        $this->assertNull($resolver->resolve('1', 'Budi', null, null));
        $this->assertNull($resolver->resolve('1', 'Budi', str_repeat('a', 250).'@example.com', null));
        $this->assertSame(0, User::count());
    }

    public function test_email_is_matched_case_insensitively_for_conflicts(): void
    {
        User::factory()->create(['google_id' => '9', 'email' => 'budi@example.com']);

        $this->assertNull((new GoogleUserResolver)->resolve('1', 'Budi', 'BUDI@Example.com', null));
        $this->assertSame(1, User::count());
    }
}
