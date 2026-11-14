<?php

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Mengarahkan direktori upload & workspace audit ke folder sementara per test,
 * lalu menghapusnya setelah test selesai.
 */
trait UsesTemporaryAuditStorage
{
    protected string $auditStorageRoot;

    protected function setUpTemporaryAuditStorage(): void
    {
        $this->auditStorageRoot = sys_get_temp_dir().'/audit-test-'.Str::random(12);
        File::ensureDirectoryExists($this->auditStorageRoot);

        config([
            'audit.storage.uploads_path' => $this->auditStorageRoot.'/uploads',
            'audit.storage.workspaces_path' => $this->auditStorageRoot.'/workspaces',
        ]);

        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->auditStorageRoot));
    }
}
