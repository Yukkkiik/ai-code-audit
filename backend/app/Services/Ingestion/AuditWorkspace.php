<?php

namespace App\Services\Ingestion;

use App\Models\AuditScan;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;

/**
 * Direktori kerja sementara per audit (BUKAN sandbox/isolasi penuh):
 *   {workspaces}/{public_id}/source  isi repositori
 *   {workspaces}/{public_id}/home    HOME kosong untuk proses git
 *   {uploads}/{public_id}.zip        berkas ZIP unggahan
 */
class AuditWorkspace
{
    public function __construct(private Filesystem $files) {}

    public function path(AuditScan $audit): string
    {
        return $this->base('workspaces_path').'/'.$this->directoryName($audit);
    }

    public function sourcePath(AuditScan $audit): string
    {
        return $this->path($audit).'/source';
    }

    public function homePath(AuditScan $audit): string
    {
        return $this->path($audit).'/home';
    }

    public function uploadPath(AuditScan $audit): string
    {
        return $this->base('uploads_path').'/'.$this->directoryName($audit).'.zip';
    }

    public function create(AuditScan $audit): void
    {
        $path = $this->path($audit);

        if (file_exists($path) || is_link($path)) {
            throw new LogicException("Workspace audit {$audit->public_id} sudah ada.");
        }

        $this->files->ensureDirectoryExists(dirname($path), 0700);

        foreach ([$path, $this->sourcePath($audit), $this->homePath($audit)] as $directory) {
            mkdir($directory, 0700);
        }
    }

    public function storeUpload(AuditScan $audit, UploadedFile $file): void
    {
        $target = $this->uploadPath($audit);

        $this->files->ensureDirectoryExists(dirname($target), 0700);
        $file->move(dirname($target), basename($target));
        chmod($target, 0600);
    }

    /**
     * Menghapus workspace dan berkas unggahan. Symlink di dalam workspace hanya di-unlink,
     * tidak pernah diikuti (Filesystem::deleteDirectory memeriksa isLink()).
     */
    public function cleanup(AuditScan $audit): bool
    {
        $path = $this->path($audit);
        $upload = $this->uploadPath($audit);

        if (is_dir($path) && ! is_link($path)) {
            $this->files->deleteDirectory($path);
        }

        if (is_file($upload)) {
            $this->files->delete($upload);
        }

        $clean = ! file_exists($path) && ! file_exists($upload);

        if (! $clean) {
            Log::error('Workspace audit gagal dibersihkan.', ['audit' => $audit->public_id, 'path' => $path]);
        }

        return $clean;
    }

    private function directoryName(AuditScan $audit): string
    {
        $id = (string) $audit->public_id;

        if (! Str::isUlid($id)) {
            throw new LogicException('public_id audit tidak valid untuk path workspace.');
        }

        return strtolower($id);
    }

    private function base(string $key): string
    {
        return rtrim((string) config("audit.storage.{$key}"), '/');
    }
}
