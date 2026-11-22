<?php

namespace App\Services\Sast;

use App\Enums\MetricScope;
use App\Models\AuditScan;
use App\Models\CodeMetric;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan metrik satu audit: satu baris per file dan baris per fungsi (maksimal `$maxFunctionRows`, kompleksitas
 * tertinggi lebih dulu). Data lama audit itu diganti dalam transaksi yang sama, sehingga idempoten.
 */
class MetricsStore
{
    private const CHUNK_SIZE = 500;

    // Batas kolom DECIMAL: volume (12,2), difficulty (10,2), effort (14,2).
    private const MAX_VOLUME = 9999999999.99;

    private const MAX_DIFFICULTY = 99999999.99;

    private const MAX_EFFORT = 999999999999.99;

    public function __construct(private int $maxFunctionRows) {}

    /**
     * @param  list<FileMetrics>  $files
     * @return int jumlah baris tersimpan (file + fungsi)
     */
    public function store(AuditScan $audit, array $files): int
    {
        $rows = [];
        $stamp = now()->toDateTimeString();

        foreach ($files as $file) {
            $rows[] = $this->row($audit, MetricScope::File, $file, null, $file->values, $stamp);
        }

        $functions = [];

        foreach ($files as $file) {
            foreach ($file->functions as $function) {
                $functions[] = [$file, $function];
            }
        }

        usort($functions, fn (array $a, array $b) => [$b[1]->values->cyclomaticComplexity, $a[0]->path, $a[1]->lineStart, $a[1]->name]
            <=> [$a[1]->values->cyclomaticComplexity, $b[0]->path, $b[1]->lineStart, $b[1]->name]);

        foreach (array_slice($functions, 0, $this->maxFunctionRows) as [$file, $function]) {
            $rows[] = $this->row($audit, MetricScope::Function, $file, $function, $function->values, $stamp);
        }

        return DB::transaction(function () use ($audit, $rows) {
            CodeMetric::query()->where('audit_scan_id', $audit->id)->delete();

            foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
                CodeMetric::query()->insert($chunk);
            }

            return count($rows);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function row(AuditScan $audit, MetricScope $scope, FileMetrics $file, ?FunctionMetrics $function, MetricValues $values, string $stamp): array
    {
        $mi = $values->maintainabilityIndex();

        return [
            'audit_scan_id' => $audit->id,
            'scope' => $scope->value,
            'language' => $file->language->value,
            'file_path' => $file->path,
            'function_name' => $function?->name,
            'line_start' => $function?->lineStart,
            'loc' => $values->loc,
            'sloc' => $values->sloc,
            'cyclomatic_complexity' => $values->cyclomaticComplexity,
            'halstead_volume' => round(min($values->volume(), self::MAX_VOLUME), 2),
            'halstead_difficulty' => round(min($values->difficulty(), self::MAX_DIFFICULTY), 2),
            'halstead_effort' => round(min($values->effort(), self::MAX_EFFORT), 2),
            'maintainability_index' => $mi === null ? null : round($mi, 2),
            'created_at' => $stamp,
            'updated_at' => $stamp,
        ];
    }
}
