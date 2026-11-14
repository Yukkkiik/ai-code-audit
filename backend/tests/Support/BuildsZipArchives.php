<?php

namespace Tests\Support;

use Closure;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Membuat arsip ZIP uji (termasuk arsip berbahaya) di direktori sementara.
 */
trait BuildsZipArchives
{
    /**
     * @param  array<string, string>  $entries  nama entri => isi (nama berakhiran "/" = direktori)
     * @param  (Closure(ZipArchive): void)|null  $configure  pengaturan tambahan (symlink, enkripsi, dll.)
     */
    protected function makeZip(array $entries, ?Closure $configure = null): string
    {
        $path = sys_get_temp_dir().'/zip-test-'.Str::random(16).'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $name => $content) {
            str_ends_with($name, '/') ? $zip->addEmptyDir(rtrim($name, '/')) : $zip->addFromString($name, $content);
        }

        if ($configure !== null) {
            $configure($zip);
        }

        $zip->close();
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    protected function markAsSymlink(ZipArchive $zip, string $name): void
    {
        $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, (0120777 << 16));
    }
}
