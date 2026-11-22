<?php

namespace App\Services\Sast;

/**
 * Menjalankan ESLint milik platform (D-20, D-21) pada file hasil seleksi. Config, plugin, dan parser hanya dari
 * `backend/tooling`: `--no-config-lookup` mencegah config repositori target dimuat, `--no-inline-config` membuat
 * komentar `eslint-disable` di repositori target tidak dapat menyembunyikan temuan, dan `--fix` tidak pernah dipakai.
 */
class EslintRunner
{
    /**
     * @param  array{node_binary: string, script_path: string, config_path: string, timeout_seconds: int, max_output_bytes: int, max_args_bytes: int}  $config
     */
    public function __construct(
        private array $config,
        private EslintParser $parser,
        private SastToolExecutor $executor,
    ) {}

    /**
     * @param  list<string>  $files  path relatif terhadap $root (hasil JsTsFileSelector)
     */
    public function run(string $root, array $files): ToolRunResult
    {
        if ($files !== [] && ! is_file($this->config['script_path'])) {
            return ToolRunResult::failed('ESLint belum terpasang di server (jalankan "npm ci" di backend/tooling).', 0);
        }

        return $this->executor->execute(
            'ESLint',
            $root,
            $files,
            $this->config,
            fn (array $chunk) => [
                $this->config['node_binary'], $this->config['script_path'],
                '--config', $this->config['config_path'],
                '--no-config-lookup', '--no-inline-config', '--no-warn-ignored',
                '--format', 'json',
                '--', ...$chunk,
            ],
            fn (string $output) => $this->parser->parse($output, $root),
        );
    }
}
