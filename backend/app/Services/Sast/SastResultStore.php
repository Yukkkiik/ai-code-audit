<?php

namespace App\Services\Sast;

use App\Enums\FindingTool;
use App\Enums\ToolRunStatus;
use App\Models\AuditScan;
use App\Models\ScanFinding;
use App\Models\ScanToolRun;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan hasil SAST satu audit: temuan ternormalisasi dan status tiap tool. Data lama audit itu diganti dalam
 * transaksi yang sama, sehingga memanggil ulang tidak menggandakan data dan kegagalan tidak meninggalkan data setengah jadi.
 */
class SastResultStore
{
    private const CHUNK_SIZE = 500;

    private const MAX_ERROR_MESSAGE_CHARS = 500;

    /**
     * @param  array<string, ToolRunResult>  $toolRuns  kunci = nilai FindingTool; tool yang tidak dijalankan tidak disertakan
     * @param  list<SastFinding>  $findings  hasil FindingNormalizer (sudah unik per fingerprint)
     * @return int jumlah temuan tersimpan
     */
    public function store(AuditScan $audit, array $toolRuns, array $findings): int
    {
        return DB::transaction(function () use ($audit, $toolRuns, $findings) {
            ScanFinding::query()->where('audit_scan_id', $audit->id)->delete();
            ScanToolRun::query()->where('audit_scan_id', $audit->id)->delete();

            $now = now();
            $stamp = $now->toDateTimeString();

            foreach (array_chunk($findings, self::CHUNK_SIZE) as $chunk) {
                ScanFinding::query()->insert(array_map(fn (SastFinding $finding) => [
                    'audit_scan_id' => $audit->id,
                    'tool' => $finding->tool->value,
                    'rule_id' => $finding->ruleId,
                    'category' => $finding->category->value,
                    'severity' => $finding->severity->value,
                    'file_path' => $finding->filePath,
                    'line_start' => $finding->lineStart,
                    'line_end' => $finding->lineEnd,
                    'column_start' => $finding->columnStart,
                    'code_snippet' => $finding->codeSnippet,
                    'message' => $finding->message,
                    'cwe' => $finding->cwe,
                    'fingerprint' => $finding->fingerprint(),
                    'created_at' => $stamp,
                    'updated_at' => $stamp,
                ], $chunk));
            }

            $perTool = array_count_values(array_map(fn (SastFinding $finding) => $finding->tool->value, $findings));

            foreach ($toolRuns as $tool => $run) {
                $ran = $run->status !== ToolRunStatus::Skipped;

                ScanToolRun::query()->create([
                    'audit_scan_id' => $audit->id,
                    'tool' => FindingTool::from($tool),
                    'status' => $run->status,
                    'files_scanned' => $run->status === ToolRunStatus::Completed ? $run->filesScanned : null,
                    'findings_count' => $run->status === ToolRunStatus::Completed ? ($perTool[$tool] ?? 0) : null,
                    'error_message' => $run->message === null ? null : mb_substr($run->message, 0, self::MAX_ERROR_MESSAGE_CHARS),
                    'duration_ms' => $ran ? $run->durationMs : null,
                    'started_at' => $ran ? $now->copy()->subMilliseconds($run->durationMs) : null,
                    'finished_at' => $ran ? $now : null,
                ]);
            }

            return count($findings);
        });
    }
}
