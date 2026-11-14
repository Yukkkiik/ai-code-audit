<?php

namespace App\Services\Ingestion;

use App\Exceptions\IngestionException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Clone repositori Git publik secara dangkal tanpa menjalankan apa pun dari repositori:
 * hooks, symlink, submodule, protokol selain https, dan redirect dinonaktifkan.
 * Perintah dijalankan sebagai array argumen (tanpa shell) dengan environment terisolasi.
 */
class GitRepositoryCloner
{
    private const HARDENING = [
        '-c', 'core.hooksPath=/dev/null',
        '-c', 'core.symlinks=false',
        '-c', 'core.fsmonitor=false',
        '-c', 'protocol.allow=never',
        '-c', 'protocol.https.allow=always',
        '-c', 'http.followRedirects=false',
        '-c', 'transfer.fsckObjects=true',
        '-c', 'submodule.recurse=false',
    ];

    /**
     * @return string SHA commit HEAD hasil clone
     */
    public function clone(string $url, string $destination, string $homeDirectory): string
    {
        $git = (string) config('audit.git.binary');
        $environment = $this->environment($homeDirectory);

        try {
            $result = Process::timeout((int) config('audit.git.clone_timeout_seconds'))
                ->env($environment)
                ->run([
                    $git, ...self::HARDENING,
                    'clone', '--depth', '1', '--no-tags', '--single-branch', '--no-recurse-submodules', '--template=',
                    '--', $url, $destination,
                ]);
        } catch (ProcessTimedOutException $e) {
            throw IngestionException::cloneTimeout($e);
        }

        if ($result->failed()) {
            // stderr hanya untuk log developer, tidak dikirim ke pengguna.
            Log::warning('git clone gagal.', [
                'url' => $url,
                'exit_code' => $result->exitCode(),
                'stderr' => Str::limit($result->errorOutput(), 2000),
            ]);

            throw $this->classifyFailure($result->errorOutput());
        }

        $revision = Process::timeout(30)
            ->env($environment)
            ->path($destination)
            ->run([$git, ...self::HARDENING, 'rev-parse', 'HEAD']);

        $sha = trim($revision->output());

        if ($revision->failed() || preg_match('/^[0-9a-f]{40}$/', $sha) !== 1) {
            throw IngestionException::failed('Commit hasil clone tidak dapat dibaca.');
        }

        return $sha;
    }

    private function classifyFailure(string $stderr): IngestionException
    {
        $stderr = strtolower($stderr);

        foreach (['repository not found', 'not found', 'could not read username', 'authentication failed', 'terminal prompts disabled', 'returned error: 403'] as $marker) {
            if (str_contains($stderr, $marker)) {
                return IngestionException::repositoryNotFound();
            }
        }

        return IngestionException::failed('Repositori tidak dapat diambil. Periksa URL lalu coba lagi.');
    }

    /**
     * Seluruh variabel environment proses PHP (termasuk secret dari .env) dihapus;
     * hanya PATH dan variabel git yang aman yang diteruskan.
     *
     * @return array<string, string|false>
     */
    private function environment(string $homeDirectory): array
    {
        $inherited = array_keys(array_filter(array_merge(getenv(), $_ENV), 'is_string'));
        $cleared = array_fill_keys($inherited, false);
        unset($cleared['PATH']);

        return array_merge($cleared, [
            'HOME' => $homeDirectory,
            'GIT_TERMINAL_PROMPT' => '0',
            'GIT_ASKPASS' => '',
            'SSH_ASKPASS' => '',
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GIT_ALLOW_PROTOCOL' => 'https',
            'GIT_LFS_SKIP_SMUDGE' => '1',
            'LC_ALL' => 'C',
        ]);
    }
}
