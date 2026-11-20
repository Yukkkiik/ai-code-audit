<?php

namespace App\Services\Sca;

use App\Enums\DependencyRelation;
use App\Enums\DependencyScope;
use App\Enums\ManifestType;
use App\Enums\UnresolvedReason;
use App\Enums\VersionStatus;

/**
 * Satu dependensi hasil parsing `package.json` (langsung) atau `package-lock.json` (langsung/transitif,
 * `lockPath` = key di `packages`). `declaredSpec` dipotong ke batas
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
        public DependencyRelation $relation = DependencyRelation::Direct,
        public ?string $lockPath = null,
    ) {}

    public function withDeclaredSpec(?string $declaredSpec): self
    {
        return new self(
            $this->name, $declaredSpec, $this->scope, $this->versionStatus, $this->version, $this->unresolvedReason,
            $this->manifestPath, $this->sourceFileType, $this->relation, $this->lockPath,
        );
    }

    public function asUnresolved(UnresolvedReason $reason): self
    {
        return new self(
            $this->name, $this->declaredSpec, $this->scope, VersionStatus::Unresolved, null, $reason,
            $this->manifestPath, $this->sourceFileType, $this->relation, $this->lockPath,
        );
    }
}
