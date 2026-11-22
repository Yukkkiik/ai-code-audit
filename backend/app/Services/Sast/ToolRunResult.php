<?php

namespace App\Services\Sast;

use App\Enums\ToolRunStatus;

/**
 * Hasil satu tool SAST. `incomplete` = sebagian file/bagian tidak teranalisis (satu batch gagal, file gagal
 * di-parse, waktu habis): temuan tetap valid tetapi tidak boleh dibaca sebagai "bersih".
 * `message` aman untuk pengguna; null bila tidak ada catatan.
 */
final readonly class ToolRunResult
{
    /**
     * @param  list<SastFinding>  $findings
     */
    private function __construct(
        public ToolRunStatus $status,
        public array $findings,
        public int $filesScanned,
        public bool $incomplete,
        public ?string $message,
        public int $durationMs,
    ) {}

    /**
     * @param  list<SastFinding>  $findings
     */
    public static function completed(array $findings, int $filesScanned, bool $incomplete, ?string $message, int $durationMs): self
    {
        return new self(ToolRunStatus::Completed, $findings, $filesScanned, $incomplete, $message, $durationMs);
    }

    public static function failed(string $message, int $durationMs): self
    {
        return new self(ToolRunStatus::Failed, [], 0, true, $message, $durationMs);
    }

    public static function skipped(string $message): self
    {
        return new self(ToolRunStatus::Skipped, [], 0, false, $message, 0);
    }
}
