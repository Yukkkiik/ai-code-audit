<?php

namespace App\Services\Ingestion;

use App\Enums\SourceType;
use App\Exceptions\IngestionException;
use App\Exceptions\InvalidRepositoryUrlException;
use App\Models\AuditScan;
use FilesystemIterator;
use Illuminate\Filesystem\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Menyiapkan isi repositori sebuah audit di workspace-nya (ZIP → ekstraksi aman, Git → clone aman).
 * Tidak mengubah status audit dan tidak membersihkan workspace: keduanya tanggung jawab job (TAHAP 5).
 */
class RepositoryIngestor
{
    public function __construct(
        private AuditWorkspace $workspace,
        private SafeZipExtractor $extractor,
        private GitUrlValidator $urlValidator,
        private GitRepositoryCloner $cloner,
        private Filesystem $files,
    ) {}

    public function ingest(AuditScan $audit): IngestedRepository
    {
        $this->workspace->create($audit);
        $source = $this->workspace->sourcePath($audit);

        return match ($audit->source_type) {
            SourceType::Zip => $this->fromZip($audit, $source),
            SourceType::Git => $this->fromGit($audit, $source),
        };
    }

    private function fromZip(AuditScan $audit, string $source): IngestedRepository
    {
        $upload = $this->workspace->uploadPath($audit);

        if (! is_file($upload)) {
            throw IngestionException::failed('Berkas ZIP unggahan tidak ditemukan.');
        }

        $size = $this->extractor->extract($upload, $source);

        return new IngestedRepository($this->detectRoot($source), $size, null);
    }

    private function fromGit(AuditScan $audit, string $source): IngestedRepository
    {
        // Divalidasi ulang tepat sebelum clone: DNS atau konfigurasi bisa berubah sejak request diterima.
        try {
            $validated = $this->urlValidator->validate((string) $audit->repository_url);
        } catch (InvalidRepositoryUrlException $e) {
            throw IngestionException::invalidRepositoryUrl($e->getMessage(), $e);
        }

        $commitSha = $this->cloner->clone($validated->url, $source, $this->workspace->homePath($audit));
        $size = $this->directorySize($source);

        if ($size > (int) config('audit.max_repository_bytes')) {
            throw IngestionException::repositoryTooLarge();
        }

        $this->files->deleteDirectory($source.'/.git');

        return new IngestedRepository($this->detectRoot($source), $size, $commitSha);
    }

    /**
     * ZIP dari GitHub dll. biasanya membungkus repositori dalam satu folder (mis. "repo-main/").
     */
    private function detectRoot(string $source): string
    {
        $entries = array_values(array_diff((array) scandir($source), ['.', '..']));

        if (count($entries) === 1) {
            $candidate = $source.'/'.$entries[0];

            if (is_dir($candidate) && ! is_link($candidate)) {
                return $candidate;
            }
        }

        return $source;
    }

    private function directorySize(string $path): int
    {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && ! $file->isLink()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
