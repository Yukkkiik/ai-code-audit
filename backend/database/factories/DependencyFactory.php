<?php

namespace Database\Factories;

use App\Enums\DependencyRelation;
use App\Enums\DependencyScope;
use App\Enums\ManifestType;
use App\Enums\UnresolvedReason;
use App\Enums\VersionStatus;
use App\Models\AuditScan;
use App\Models\Dependency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dependency>
 */
class DependencyFactory extends Factory
{
    /**
     * Default: dependensi langsung dengan versi pasti dari package-lock.json v3.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audit_scan_id' => AuditScan::factory(),
            'name' => fake()->unique()->lexify('paket-??????'),
            'version' => fake()->numerify('#.#.#'),
            'declared_spec' => fn (array $attributes) => '^'.$attributes['version'],
            'scope' => DependencyScope::Production,
            'relation' => DependencyRelation::Direct,
            'manifest_path' => 'package-lock.json',
            'source_file_type' => ManifestType::PackageLockV3,
            'lock_path' => fn (array $attributes) => 'node_modules/'.$attributes['name'],
            'version_status' => VersionStatus::Exact,
            'unresolved_reason' => null,
        ];
    }

    /**
     * Dependensi yang versinya tidak dapat dipastikan (tidak dikirim ke OSV).
     */
    public function unresolved(UnresolvedReason $reason): static
    {
        return $this->state(fn () => [
            'version' => null,
            'declared_spec' => '^1.2.3',
            'manifest_path' => 'package.json',
            'source_file_type' => ManifestType::PackageJson,
            'lock_path' => null,
            'version_status' => VersionStatus::Unresolved,
            'unresolved_reason' => $reason,
        ]);
    }
}
