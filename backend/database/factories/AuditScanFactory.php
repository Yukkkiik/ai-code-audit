<?php

namespace Database\Factories;

use App\Enums\SourceType;
use App\Models\AuditScan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditScan>
 */
class AuditScanFactory extends Factory
{
    /**
     * Default: audit guest dari upload ZIP.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'guest_token_hash' => hash('sha256', Str::random(40)),
            'source_type' => SourceType::Zip,
            'repository_name' => fake()->slug(2),
            'original_filename' => fn (array $attributes) => $attributes['repository_name'].'.zip',
            'source_size_bytes' => fake()->numberBetween(1_000, 50 * 1024 * 1024),
        ];
    }

    /**
     * Audit milik user terdaftar (tanpa guest token).
     */
    public function ownedBy(?User $user = null): static
    {
        return $this->state(fn () => [
            'user_id' => $user ?? User::factory(),
            'guest_token_hash' => null,
        ]);
    }

    /**
     * Audit dari URL repositori Git publik.
     */
    public function fromGit(): static
    {
        return $this->state(fn () => [
            'source_type' => SourceType::Git,
            'repository_name' => 'organisasi/contoh-repo',
            'repository_url' => 'https://github.com/organisasi/contoh-repo',
            'original_filename' => null,
            'commit_sha' => fake()->sha1(),
        ]);
    }
}
