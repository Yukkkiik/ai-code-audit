<?php

namespace App\Services\Sast;

use App\Enums\AuditStatus;
use App\Enums\FindingTool;
use App\Enums\MetricScope;
use App\Enums\StageStatus;
use App\Enums\ToolRunStatus;
use App\Models\AuditScan;

/**
 * `meta.scan` pada daftar hasil SAST: status tahap, pesan, skor, status tiap tool, dan catatan yang disusun dari
 * data tersimpan agar daftar kosong tidak terbaca sebagai "tidak ada masalah". Catatan tidak pernah memakai kata
 * "aman" atau "bersih". Hitungan memakai total seluruh audit, bukan hasil filter atau halaman.
 *
 * SastStageRunner tidak menyimpan `scan_tool_runs` saat tahap `skipped` atau `failed`, dan run metrik tidak pernah
 * tercatat di sana; karena itu catatan untuk kedua status itu dibangun dari status tahap, bukan dari `tools`.
 */
class SastScanSummary
{
    private const UNFINISHED_MESSAGE = 'Pemeriksaan SAST tidak selesai karena pemrosesan audit berhenti sebelum tahap ini selesai.';

    /**
     * @return array<string, mixed>
     */
    public function forFindings(AuditScan $audit): array
    {
        return $this->build($audit, function (array $tools) use ($audit) {
            $note = $this->toolsNote($audit, $tools);

            return $audit->sast_status === StageStatus::Partial ? $note.' Pemeriksaan tidak lengkap; lihat message.' : $note;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function forMetrics(AuditScan $audit, MetricScope $scope): array
    {
        return $this->build($audit, function () use ($audit, $scope) {
            $parts = [];

            if (! $audit->codeMetrics()->exists()) {
                $parts[] = 'Tidak ada metrik kode yang tersimpan; ini bukan indikasi kompleksitas rendah. Lihat message.';
            }

            if ($scope === MetricScope::Function) {
                $parts[] = 'Hanya fungsi dengan kompleksitas tertinggi (hingga batas penyimpanan) yang disimpan.';
            }

            return $parts === [] ? null : implode(' ', $parts);
        });
    }

    /**
     * @param  callable(list<array<string, mixed>>): ?string  $completedNote  catatan untuk tahap completed/partial
     * @return array<string, mixed>
     */
    private function build(AuditScan $audit, callable $completedNote): array
    {
        $unfinished = $this->unfinished($audit);

        $tools = $this->tools($audit);

        // Skipped/failed: tidak ada hasil tersimpan, catatan dari status tahap saja.
        $note = match (true) {
            $unfinished => null,
            $audit->sast_status === StageStatus::Skipped => 'Tidak ada file JavaScript/TypeScript yang dianalisis; tidak ada hasil SAST.',
            $audit->sast_status === StageStatus::Failed => 'Analisis SAST gagal; tidak ada hasil yang dapat ditampilkan. Ini bukan berarti kode bebas masalah. Lihat message.',
            in_array($audit->sast_status, [StageStatus::Completed, StageStatus::Partial], true) => $completedNote($tools),
            default => null,
        };

        return [
            'status' => $audit->sast_status->value,
            'message' => $unfinished ? self::UNFINISHED_MESSAGE : $audit->sast_error,
            'health_score' => $audit->health_score,
            'health_category' => $audit->health_category,
            'tools' => $tools,
            'note' => $note,
        ];
    }

    /**
     * Audit sudah berakhir tetapi tahap SAST tidak pernah selesai (mis. job mati).
     */
    private function unfinished(AuditScan $audit): bool
    {
        return in_array($audit->status, [AuditStatus::Completed, AuditStatus::Partial, AuditStatus::Failed], true)
            && in_array($audit->sast_status, [StageStatus::Pending, StageStatus::Running], true);
    }

    /**
     * Jumlah file dan temuan hanya berarti untuk tool yang `completed`; selain itu null (tidak pernah 0).
     *
     * @return list<array{tool: string, status: string, files_scanned: int|null, findings_count: int|null, error_message: string|null}>
     */
    private function tools(AuditScan $audit): array
    {
        return $audit->toolRuns()
            ->select(['tool', 'status', 'files_scanned', 'findings_count', 'error_message'])
            ->orderBy('tool')
            ->get()
            ->map(fn ($run) => [
                'tool' => $run->tool->value,
                'status' => $run->status->value,
                'files_scanned' => $run->status === ToolRunStatus::Completed ? $run->files_scanned : null,
                'findings_count' => $run->status === ToolRunStatus::Completed ? $run->findings_count : null,
                'error_message' => $run->error_message,
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $tools
     */
    private function toolsNote(AuditScan $audit, array $tools): string
    {
        $byTool = array_column($tools, null, 'tool');
        $parts = [];

        foreach (FindingTool::cases() as $tool) {
            $label = $tool === FindingTool::Eslint ? 'ESLint' : 'Semgrep';
            $run = $byTool[$tool->value] ?? null;

            // Tool gagal, dilewati, atau tidak tercatat sama-sama "tidak selesai".
            $parts[] = $run !== null && $run['status'] === ToolRunStatus::Completed->value
                ? "{$label}: {$run['findings_count']} temuan."
                : "{$label}: tidak selesai.";
        }

        if (! $audit->findings()->exists()) {
            $parts[] = 'Tidak ada temuan tersimpan; ini bukan jaminan bebas kerentanan.';
        }

        return implode(' ', $parts);
    }
}
