<?php

namespace App\Services\Sca;

use App\Enums\DependencyScope;
use App\Enums\ManifestType;
use App\Enums\UnresolvedReason;
use App\Enums\VersionStatus;

/**
 * Satu dependensi langsung hasil parsing `package.json`. `declaredSpec` dipotong ke batas
 * kolom (1024); klasifikasi versi memakai spesifikasi penuh.
 */
final readonly class ParsedDependency
{
    public function __construct(
        public string $name,
        public ?string $declaredSpec,
        public DependencyScope $scope,
        public VersionStatus $versionStatus,
        public ?string $version,
        public ?UnresolvedReason $unresolvedReason,
        public string $manifestPath,
        public ManifestType $sourceFileType = ManifestType::PackageJson,
    ) {}
}
