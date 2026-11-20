<?php

namespace App\Services\Sca;

use App\Models\AuditScan;
use App\Models\Dependency;
use App\Models\Vulnerability;

/**
 * Cakupan pemeriksaan SCA untuk SELURUH audit (bukan halaman atau filter): dependencies_total =
 * checked + check_failed + not_checked, dan unresolved adalah bagian dari not_checked.
 */
final readonly class ScaCoverage
{
    public function __construct(
        public int $total,
        public int $checked,
        public int $checkFailed,
        public int $notChecked,
        public int $unresolved,
        public int $advisories,
        public int $advisoriesWithoutDetail,
    ) {}

    public static function of(AuditScan $audit): self
    {
        $total = $checked = $failed = $notChecked = $unresolved = 0;

        $rows = Dependency::query()
            ->where('audit_scan_id', $audit->id)
            ->toBase()
            ->selectRaw('osv_status, version_status, count(*) as total')
            ->groupBy('osv_status', 'version_status')
            ->get();

        foreach ($rows as $row) {
            $count = (int) $row->total;
            $total += $count;

            match ($row->osv_status) {
                'queried' => $checked += $count,
                'failed' => $failed += $count,
                default => $notChecked += $count,
            };

            // Komponen tanpa versi pasti tidak pernah dikirim ke OSV, sehingga selalu bagian dari not_checked.
            if ($row->version_status === 'unresolved' && ! in_array($row->osv_status, ['queried', 'failed'], true)) {
                $unresolved += $count;
            }
        }

        $advisories = Vulnerability::query()
            ->where('audit_scan_id', $audit->id)
            ->toBase()
            ->selectRaw('count(*) as total, coalesce(sum(severity_label is null), 0) as without_detail')
            ->first();

        return new self($total, $checked, $failed, $notChecked, $unresolved, (int) $advisories->total, (int) $advisories->without_detail);
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'dependencies_total' => $this->total,
            'checked' => $this->checked,
            'check_failed' => $this->checkFailed,
            'not_checked' => $this->notChecked,
            'unresolved' => $this->unresolved,
            'advisories' => $this->advisories,
            'advisories_without_detail' => $this->advisoriesWithoutDetail,
        ];
    }
}
