<?php

namespace Tests\Feature\Ingestion;

use App\Exceptions\IngestionException;
use App\Services\Ingestion\SafeZipExtractor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsZipArchives;
use Tests\TestCase;
use ZipArchive;

class SafeZipExtractorTest extends TestCase
{
    use BuildsZipArchives;

    private string $root;

    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/zip-extract-'.Str::random(10);
        $this->destination = $this->root.'/workspace/source';
        File::ensureDirectoryExists($this->destination);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->root));
    }

    public function test_valid_archive_is_extracted_with_safe_permissions(): void
    {
        $zip = $this->makeZip([
            'repo/package.json' => '{"name":"contoh"}',
            'repo/src/index.js' => 'console.log("halo");',
            'repo/docs/' => '',
        ]);

        $written = $this->extractor()->extract($zip, $this->destination);

        $this->assertFileExists($this->destination.'/repo/package.json');
        $this->assertSame('console.log("halo");', file_get_contents($this->destination.'/repo/src/index.js'));
        $this->assertDirectoryExists($this->destination.'/repo/docs');
        $this->assertSame(strlen('{"name":"contoh"}') + strlen('console.log("halo");'), $written);
        $this->assertSame('0644', substr(sprintf('%o', fileperms($this->destination.'/repo/src/index.js')), -4));
    }

    public function test_ignored_directories_are_not_written_at_any_depth(): void
    {
        $zip = $this->makeZip([
            'repo/index.js' => 'ok',
            'repo/node_modules/lodash/index.js' => 'module.exports = {}',
            'repo/packages/web/node_modules/x.js' => 'x',
            'repo/vendor/autoload.php' => '<?php',
            'repo/.git/config' => '[core]',
        ]);

        $this->extractor()->extract($zip, $this->destination);

        $this->assertFileExists($this->destination.'/repo/index.js');
        $this->assertDirectoryDoesNotExist($this->destination.'/repo/node_modules');
        $this->assertDirectoryDoesNotExist($this->destination.'/repo/packages/web/node_modules');
        $this->assertDirectoryDoesNotExist($this->destination.'/repo/vendor');
        $this->assertDirectoryDoesNotExist($this->destination.'/repo/.git');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function maliciousEntryNames(): array
    {
        return [
            'parent traversal' => ['../evil.txt'],
            'nested traversal' => ['repo/../../evil.txt'],
            'absolute unix path' => ['/tmp/evil.txt'],
            'windows drive path' => ['C:/evil.txt'],
            'backslash traversal' => ['repo\\..\\..\\evil.txt'],
        ];
    }

    #[DataProvider('maliciousEntryNames')]
    public function test_path_traversal_entries_are_rejected_before_anything_is_written(string $name): void
    {
        $zip = $this->makeZip(['repo/aman.js' => 'aman', $name => 'jahat']);

        $this->assertIngestionFails('ZIP_SLIP_DETECTED', fn () => $this->extractor()->extract($zip, $this->destination));

        $this->assertSame([], File::allFiles($this->root), 'Tidak boleh ada file yang ditulis jika arsip ditolak.');
        $this->assertFileDoesNotExist(sys_get_temp_dir().'/evil.txt');
    }

    public function test_symlink_entries_are_rejected(): void
    {
        $zip = $this->makeZip(['repo/link' => '/etc/passwd'], fn (ZipArchive $zip) => $this->markAsSymlink($zip, 'repo/link'));

        $this->assertIngestionFails('ZIP_SLIP_DETECTED', fn () => $this->extractor()->extract($zip, $this->destination));
        $this->assertFalse(is_link($this->destination.'/repo/link'));
    }

    public function test_encrypted_archive_is_rejected(): void
    {
        $zip = $this->makeZip(['repo/rahasia.js' => 'isi'], function (ZipArchive $zip) {
            $zip->setPassword('kata-sandi');
            $zip->setEncryptionName('repo/rahasia.js', ZipArchive::EM_AES_256);
        });

        $this->assertIngestionFails('ARCHIVE_ENCRYPTED', fn () => $this->extractor()->extract($zip, $this->destination));
    }

    public function test_archive_exceeding_total_size_is_rejected_before_extraction(): void
    {
        config(['audit.max_repository_bytes' => 1024]);
        $zip = $this->makeZip(['repo/a.txt' => random_bytes(600), 'repo/b.txt' => random_bytes(600)]);

        $this->assertIngestionFails('ARCHIVE_TOO_LARGE', fn () => $this->extractor()->extract($zip, $this->destination));
        $this->assertSame([], File::allFiles($this->root));
    }

    public function test_zip_bomb_compression_ratio_is_rejected(): void
    {
        config(['audit.zip.max_compression_ratio' => 50]);
        $zip = $this->makeZip(['repo/bomb.txt' => str_repeat('A', 2 * 1024 * 1024)]);

        $this->assertIngestionFails('ARCHIVE_TOO_LARGE', fn () => $this->extractor()->extract($zip, $this->destination));
    }

    public function test_too_many_entries_is_rejected(): void
    {
        config(['audit.zip.max_entries' => 3]);
        $zip = $this->makeZip(['a.js' => '1', 'b.js' => '2', 'c.js' => '3', 'd.js' => '4']);

        $this->assertIngestionFails('ARCHIVE_TOO_LARGE', fn () => $this->extractor()->extract($zip, $this->destination));
    }

    public function test_corrupt_archive_is_rejected(): void
    {
        $path = $this->root.'/rusak.zip';
        file_put_contents($path, "PK\x03\x04".random_bytes(256));

        $this->assertIngestionFails('ARCHIVE_CORRUPT', fn () => $this->extractor()->extract($path, $this->destination));
    }

    public function test_empty_archive_extracts_nothing(): void
    {
        // libzip tidak menulis berkas untuk arsip tanpa entri; ZIP kosong standar = 22 byte End of Central Directory.
        $zip = $this->root.'/kosong.zip';
        file_put_contents($zip, "PK\x05\x06".str_repeat("\0", 18));

        $this->assertSame(0, $this->extractor()->extract($zip, $this->destination));
        $this->assertSame([], File::allFiles($this->destination));
    }

    private function extractor(): SafeZipExtractor
    {
        return $this->app->make(SafeZipExtractor::class);
    }

    private function assertIngestionFails(string $expectedCode, \Closure $callback): void
    {
        try {
            $callback();
        } catch (IngestionException $e) {
            $this->assertSame($expectedCode, $e->errorCode);
            $this->assertNotSame('', $e->getMessage());

            return;
        }

        $this->fail("IngestionException [{$expectedCode}] tidak dilempar.");
    }
}
