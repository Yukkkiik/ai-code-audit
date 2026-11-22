<?php

namespace App\Services\Sast;

/**
 * Menjalankan Semgrep dengan rule milik platform pada file hasil seleksi (D-22, D-31). Tidak memuat config dari
 * repositori target, tanpa telemetri, dan nosem/inline suppression di repositori target diabaikan.
 */
class SemgrepRunner
{
    /**
     * @param  array{binary: string, rules_path: string, timeout_seconds: int, per_file_timeout_seconds: int, max_output_bytes: int, max_args_bytes: int}  $config
     */
    public function __construct(
        private array $config,
        private SemgrepParser $parser,
        private SastToolExecutor $executor,
    ) {}

    /**
     * @param  list<string>  $files  path relatif terhadap $root (hasil JsTsFileSelector)
     */
    public function run(string $root, array $files): ToolRunResult
    {
        return $this->executor->execute(
            'Semgrep',
            $root,
            $files,
            $this->config,
            fn (array $chunk) => [
                $this->config['binary'], 'scan',
                '--config', $this->config['rules_path'],
                '--json', '--quiet', '--metrics=off', '--disable-version-check',
                '--disable-nosem', '--no-git-ignore',
                '--timeout', (string) $this->config['per_file_timeout_seconds'],
                '--', ...$chunk,
            ],
            fn (string $output) => $this->parser->parse($output),
            ['SEMGREP_SEND_METRICS' => 'off', 'SEMGREP_ENABLE_VERSION_CHECK' => '0', 'SEMGREP_FORCE_COLOR' => '0', 'PYTHONUTF8' => '1'],
        );
    }
}
