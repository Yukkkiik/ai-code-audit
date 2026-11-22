<?php

namespace App\Services\Sast;

/**
 * Menjalankan tooling/metrics.mjs (D-09: implementasi sendiri di atas parser `typescript` milik platform) pada file hasil
 * seleksi. Skrip hanya mem-parse file; kode, tsconfig, dan plugin repositori target tidak dieksekusi atau dimuat.
 */
class MetricsRunner
{
    /**
     * @param  array{node_binary: string, script_path: string, typescript_path: string, timeout_seconds: int, max_output_bytes: int, max_args_bytes: int, max_file_bytes: int}  $config
     */
    public function __construct(
        private array $config,
        private MetricsParser $parser,
        private SastToolExecutor $executor,
    ) {}

    /**
     * @param  list<string>  $files  path relatif terhadap $root (hasil JsTsFileSelector)
     */
    public function run(string $root, array $files): MetricsResult
    {
        if ($files !== [] && (! is_file($this->config['script_path']) || ! is_file($this->config['typescript_path']))) {
            return new MetricsResult(ToolRunResult::failed('Perkakas metrik belum terpasang di server (jalankan "npm ci" di backend/tooling).', 0), []);
        }

        $collected = [];

        $run = $this->executor->execute(
            'Metrik',
            $root,
            $files,
            $this->config,
            fn (array $chunk) => [
                $this->config['node_binary'], $this->config['script_path'],
                '--max-bytes', (string) $this->config['max_file_bytes'],
                '--', ...$chunk,
            ],
            function (string $output) use (&$collected): ToolReport {
                $report = $this->parser->parse($output);
                array_push($collected, ...$report->files);

                return new ToolReport([], $report->errorCount, $report->droppedCount);
            },
        );

        return new MetricsResult($run, $collected);
    }
}
