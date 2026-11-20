<?php

namespace Tests\Support;

use App\Enums\AuditStatus;
use App\Enums\OsvQueryStatus;
use App\Enums\SourceType;
use App\Enums\StageStatus;
use App\Enums\UnresolvedReason;
use App\Enums\VersionStatus;
use App\Models\AuditScan;
use App\Models\Dependency;
use App\Models\Vulnerability;
use App\Services\Audit\AuditScanCreator;

/**
 * Data uji untuk endpoint hasil SCA: audit guest (dengan token), dependensi, dan advisori.
 */
trait BuildsScaResults
{
    /**
     * @param  array<string, mixed>  $state
     * @return array{AuditScan, string}
     */
    private function guestAudit(array $state = []): array
    {
        $created = $this->app->make(AuditScanCreator::class)->create(null, SourceType::Zip, 'tugas-akhir');
        $created->audit->forceFill($state + $this->finished())->save();

        return [$created->audit, $created->guestToken];
    }

    /**
     * @return array<string, mixed>
     */
    private function finished(StageStatus $sca = StageStatus::Completed): array
    {
        return ['status' => AuditStatus::Completed, 'sca_status' => $sca, 'sast_status' => StageStatus::Completed];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function dependency(AuditScan $audit, array $attributes = []): Dependency
    {
        return Dependency::factory()->create($attributes + ['audit_scan_id' => $audit->id, 'osv_status' => OsvQueryStatus::Queried]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function unresolvedDependency(AuditScan $audit, array $attributes = []): Dependency
    {
        return Dependency::factory()->unresolved(UnresolvedReason::RangeWithoutLockfile)->create($attributes + [
            'audit_scan_id' => $audit->id, 'osv_status' => OsvQueryStatus::NotQueried, 'version_status' => VersionStatus::Unresolved,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function vulnerability(Dependency $dependency, array $attributes = []): Vulnerability
    {
        return Vulnerability::factory()->create($attributes + ['dependency_id' => $dependency->id]);
    }

    /**
     * Baris minimal: detail advisori tidak berhasil diambil.
     */
    private function minimalVulnerability(Dependency $dependency, string $osvId = 'GHSA-minimal-0000-0001'): Vulnerability
    {
        return $this->vulnerability($dependency, [
            'osv_id' => $osvId, 'aliases' => null, 'summary' => null, 'details' => null, 'severity_label' => null,
            'severity_vectors' => null, 'affected_ranges' => null, 'fixed_versions' => null, 'reference_urls' => null,
            'published_at' => null, 'modified_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function token(string $token): array
    {
        return ['X-Guest-Token' => $token];
    }
}
