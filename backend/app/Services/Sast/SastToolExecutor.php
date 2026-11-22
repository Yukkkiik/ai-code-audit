<?php

namespace App\Services\Sast;

use App\Exceptions\SastToolException;
use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;

/**
 * Mengeksekusi tool SAST eksternal (Semgrep, ESLint) pada file hasil seleksi: tanpa shell (array argumen),
 * lingkungan proses dibersihkan (secret `.env` tidak diteruskan), anggaran waktu total, batas ukuran output,
 * dan file dibagi ke beberapa batch agar tidak melewati batas panjang command line.
 *
 * shortcut: batas ukuran output hanya diperiksa setelah proses selesai (memori sudah terpakai), naikkan menjadi
 * pembacaan streaming bila output tool terbukti besar.
 */
class SastToolExecutor
{
    private const BINARY_MISSING = 127;

    /**
     * @param  list<string>  $files  path relatif terhadap $root
     * @param  array{timeout_seconds: int, max_output_bytes: int, max_args_bytes: int}  $limits
     * @param  Closure(list<string>): list<string>  $buildCommand  batch file → command lengkap (array argumen)
     * @param  Closure(string): ToolReport  $parse  output stdout → laporan; melempar SastToolException bila tidak valid
     * @param  array<string, string>  $toolEnv  variabel environment tambahan khusus tool
     */
    public function execute(
        string $label,
        string $root,
        array $files,
        array $limits,
        Closure $buildCommand,
        Closure $parse,
        array $toolEnv = [],
    ): ToolRunResult {
        if ($files === []) {
            return ToolRunResult::skipped("Tidak ada file JavaScript/TypeScript untuk diperiksa {$label}.");
        }

        $started = microtime(true);
        $deadline = $started + $limits['timeout_seconds'];
        $home = sys_get_temp_dir().'/sast-home-'.bin2hex(random_bytes(8));
        mkdir($home, 0700, true);

        $findings = [];
        $scanned = 0;
        $failures = [];
        $toolErrors = 0;
        $dropped = 0;

        try {
            foreach ($this->chunks($files, $limits['max_args_bytes']) as $chunk) {
                $remaining = $deadline - microtime(true);

                if ($remaining <= 0) {
                    $failures[] = 'waktu habis';

                    continue;
                }

                try {
                    $output = $this->runChunk($label, $root, $buildCommand($chunk), $home, $toolEnv, (int) ceil($remaining), $limits['max_output_bytes']);
                    $report = $parse($output);
                } catch (SastToolException $e) {
                    $failures[] = $e->getMessage();

                    if ($e->getCode() === self::BINARY_MISSING) {
                        break;
                    }

                    continue;
                }

                array_push($findings, ...$report->findings);
                $scanned += count($chunk);
                $toolErrors += $report->errorCount;
                $dropped += $report->droppedCount;
            }
        } finally {
            (new Filesystem)->deleteDirectory($home);
        }

        $duration = (int) round((microtime(true) - $started) * 1000);

        if ($scanned === 0) {
            return ToolRunResult::failed("{$label} gagal: ".implode('; ', array_unique(array_map(fn (string $failure) => rtrim($failure, '.'), $failures))).'.', $duration);
        }

        $notes = [];

        if ($failures !== []) {
            $notes[] = 'sebagian file tidak diperiksa ('.implode('; ', array_unique(array_map(fn (string $failure) => rtrim($failure, '.'), $failures))).')';
        }

        if ($toolErrors > 0) {
            $notes[] = "{$label} melaporkan {$toolErrors} kesalahan analisis (mis. file tidak dapat di-parse)";
        }

        if ($dropped > 0) {
            $notes[] = "{$dropped} hasil dibuang karena tidak valid";
        }

        return ToolRunResult::completed(
            $findings,
            $scanned,
            $failures !== [] || $toolErrors > 0,
            $notes === [] ? null : ucfirst(implode('; ', $notes)).'.',
            $duration,
        );
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $toolEnv
     */
    private function runChunk(string $label, string $root, array $command, string $home, array $toolEnv, int $timeoutSeconds, int $maxOutputBytes): string
    {
        try {
            $result = Process::path($root)
                ->env($this->environment($home, $toolEnv))
                ->timeout($timeoutSeconds)
                ->run($command);
        } catch (ProcessTimedOutException) {
            throw new SastToolException('waktu habis');
        } catch (ProcessRuntimeException) {
            throw new SastToolException("{$label} tidak dapat dijalankan (binary tidak ditemukan)", self::BINARY_MISSING);
        }

        $exitCode = $result->exitCode();

        if ($exitCode === 127) {
            throw new SastToolException("{$label} tidak dapat dijalankan (binary tidak ditemukan)", self::BINARY_MISSING);
        }

        // 0 = bersih, 1 = ada temuan (ESLint) atau kondisi tertentu Semgrep. Selain itu = kegagalan/crash tool.
        if ($exitCode !== 0 && $exitCode !== 1) {
            Log::warning('Tool SAST gagal.', [
                'tool' => $label,
                'exit_code' => $exitCode,
                'stderr' => SastText::clean($result->errorOutput(), 500),
            ]);

            throw new SastToolException("{$label} berhenti dengan kode {$exitCode}");
        }

        $output = $result->output();

        if (strlen($output) > $maxOutputBytes) {
            throw new SastToolException("output {$label} melebihi batas ukuran");
        }

        return $output;
    }

    /**
     * @param  list<string>  $files
     * @return list<list<string>>
     */
    private function chunks(array $files, int $maxArgsBytes): array
    {
        $chunks = [];
        $current = [];
        $bytes = 0;

        foreach ($files as $file) {
            $size = strlen($file) + 1;

            if ($current !== [] && $bytes + $size > $maxArgsBytes) {
                $chunks[] = $current;
                $current = [];
                $bytes = 0;
            }

            $current[] = $file;
            $bytes += $size;
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Seluruh variabel proses PHP (termasuk secret `.env`) dihapus; hanya PATH (dan variabel dasar Windows) yang diteruskan.
     *
     * @param  array<string, string>  $toolEnv
     * @return array<string, string|false>
     */
    private function environment(string $home, array $toolEnv): array
    {
        $inherited = array_keys(array_filter(array_merge(getenv(), $_ENV), 'is_string'));
        $cleared = array_fill_keys($inherited, false);
        // Windows: tanpa SystemRoot/TEMP, proses (mis. Node) gagal start. Variabel ini bukan secret.
        foreach (['PATH', 'PATHEXT', 'SystemRoot', 'SYSTEMROOT', 'windir', 'TEMP', 'TMP'] as $keep) {
            unset($cleared[$keep]);
        }

        return array_merge($cleared, ['NO_COLOR' => '1'], $toolEnv, ['HOME' => $home, 'USERPROFILE' => $home]);
    }
}
