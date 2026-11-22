<?php

namespace App\Services\Sca;

use App\Enums\FixMatchReason;
use App\Enums\FixMatchStatus;
use App\Enums\VersionChange;

/**
 * Hasil pencocokan satu advisori dengan satu versi terpasang. `fixedVersion` hanya terisi untuk `fix_available`
 * dan selalu nilai `fixed` yang tertulis di OSV; `reason` hanya terisi untuk `undetermined`.
 */
final readonly class FixMatch
{
    private function __construct(
        public FixMatchStatus $status,
        public string $installedVersion,
        public ?string $fixedVersion,
        public ?VersionChange $versionChange,
        public ?FixMatchReason $reason,
    ) {}

    public static function fixAvailable(string $installed, string $fixed, VersionChange $change): self
    {
        return new self(FixMatchStatus::FixAvailable, $installed, $fixed, $change, null);
    }

    public static function noFixRecorded(string $installed): self
    {
        return new self(FixMatchStatus::NoFixRecorded, $installed, null, null, null);
    }

    public static function undetermined(string $installed, FixMatchReason $reason): self
    {
        return new self(FixMatchStatus::Undetermined, $installed, null, null, $reason);
    }
}
