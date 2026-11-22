<?php

namespace App\Services\Sast;

use App\Exceptions\SastToolException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;

/**
 * Menjalankan Semgrep dengan rule milik platform pada file hasil seleksi (D-22, D-31). Tidak memuat config dari
 * repositori target, tanpa shell, tanpa telemetri, dan tanpa lingkungan proses aplikasi (secret `.env` tidak diteruskan).
 * File dibagi ke beberapa batch agar tidak melewati batas panjang command line.
 *
 * shortcut: batas ukuran output hanya diperiksa setelah proses selesai (memori sudah terpakai), naikkan menjadi
 * pembacaan streaming bila output Semgrep terbukti besar.
 */
class SemgrepRunner
{
    private const BINARY_MISSING = 127;

    private const SEMGREP_ENV = [
        'SEMGREP_SEND_METRICS' => 'off',
        'SEMGREP_ENABLE_VERSION_CHECK' => '0',
        'SEMGREP_FORCE_COLOR' => '0',
        'NO_COLOR' => '1',
        'PYTHONUTF8' => '1',
    ];

    /**
     * @param  array{binary: string, rules_path: string, timeout_seconds: int, per_file_timeout_seconds: int, max_output_bytes: int, max_args_bytes: int}  $config
     */
    public function __construct(private array $config, private SemgrepParser $parser) {}

    /**
     * @param  list<string>  $files  path relatif terhadap $root (hasil JsTsFileSelector)
     */
    public function run(string $root, array $files): ToolRunResult
    {
        if ($files === []) {
            return ToolRunResult::skipped('Tidak ada file JavaScript/TypeScript untuk diperiksa Semgrep.');
        }

        $started = microtime(true);
        $deadline = $started + $this->config['timeout_seconds'];
        $home = sys_get_temp_dir().'/semgrep-home-'.bin2hex(random_bytes(8));
        mkdir($home, 0700, true);

        $findings = [];
        $scanned = 0;
        $failures = [];
        $semgrepErrors = 0;
        $dropped = 0;

        try {
            foreach ($this->chunks($files) as $chunk) {
                $remaining = $deadline - microtime(true);

                if ($remaining <= 0) {
                    $failures[] = 'waktu habis';

                    continue;
                }

                try {
                    $report = $this->runChunk($root, $chunk, $home, (int) ceil($remaining));
                } catch (SastToolException $e) {
                    $failures[] = $e->getMessage();

                    if ($e->getCode() === self::BINARY_MISSING) {
                        break;
                    }

                    continue;
                }

                array_push($findings, ...$report->findings);
                $scanned += count($chunk);
                $semgrepErrors += $report->errorCount;
                $dropped += $report->droppedCount;
            }
        } finally {
            (new Filesystem)->deleteDirectory($home);
        }

        $duration = (int) round((microtime(true) - $started) * 1000);

        if ($scanned === 0) {
            return ToolRunResult::failed('Semgrep gagal: '.implode('; ', array_unique($failures)).'.', $duration);
        }

        $notes = [];

        if ($failures !== []) {
            $notes[] = 'sebagian file tidak diperiksa ('.implode('; ', array_unique($failures)).')';
        }

        if ($semgrepErrors > 0) {
            $notes[] = "Semgrep melaporkan {$semgrepErrors} kesalahan analisis (mis. file tidak dapat di-parse)";
        }

        if ($dropped > 0) {
            $notes[] = "{$dropped} hasil dibuang karena tidak valid";
        }

        return ToolRunResult::completed(
            $findings,
            $scanned,
            $failures !== [] || $semgrepErrors > 0,
            $notes === [] ? null : ucfirst(implode('; ', $notes)).'.',
            $duration,
        );
    }

    /**
     * @param  list<string>  $chunk
     */
    private function runChunk(string $root, array $chunk, string $home, int $timeoutSeconds): SemgrepReport
    {
        try {
            $result = Process::path($root)
                ->env($this->environment($home))
                ->timeout($timeoutSeconds)
                ->run([
                    $this->config['binary'], 'scan',
                    '--config', $this->config['rules_path'],
                    '--json', '--quiet', '--metrics=off', '--disable-version-check',
                    '--disable-nosem', '--no-git-ignore',
                    '--timeout', (string) $this->config['per_file_timeout_seconds'],
                    '--', ...$chunk,
                ]);
        } catch (ProcessTimedOutException) {
            throw new SastToolException('waktu habis');
        } catch (ProcessRuntimeException) {
            throw new SastToolException('Semgrep tidak dapat dijalankan (binary tidak ditemukan)', self::BINARY_MISSING);
        }

        $exitCode = $result->exitCode();

        if ($exitCode === 127) {
            throw new SastToolException('Semgrep tidak dapat dijalankan (binary tidak ditemukan)', self::BINARY_MISSING);
        }

        // Tanpa --error, Semgrep keluar 0 meskipun ada temuan; 1 = ada temuan pada versi tertentu. Selain itu = kegagalan.
        if ($exitCode !== 0 && $exitCode !== 1) {
            Log::warning('Semgrep gagal.', [
                'exit_code' => $exitCode,
                'stderr' => SastText::clean($result->errorOutput(), 500),
            ]);

            throw new SastToolException("Semgrep berhenti dengan kode {$exitCode}");
        }

        $output = $result->output();

        if (strlen($output) > $this->config['max_output_bytes']) {
            throw new SastToolException('output Semgrep melebihi batas ukuran');
        }

        return $this->parser->parse($output);
    }

    /**
     * @param  list<string>  $files
     * @return list<list<string>>
     */
    private function chunks(array $files): array
    {
        $chunks = [];
        $current = [];
        $bytes = 0;

        foreach ($files as $file) {
            $size = strlen($file) + 1;

            if ($current !== [] && $bytes + $size > $this->config['max_args_bytes']) {
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
     * Seluruh variabel proses PHP (termasuk secret `.env`) dihapus; hanya PATH yang diteruskan.
     *
     * @return array<string, string|false>
     */
    private function environment(string $home): array
    {
        $inherited = array_keys(array_filter(array_merge(getenv(), $_ENV), 'is_string'));
        $cleared = array_fill_keys($inherited, false);
        unset($cleared['PATH']);

        return array_merge($cleared, self::SEMGREP_ENV, ['HOME' => $home, 'USERPROFILE' => $home]);
    }
}
