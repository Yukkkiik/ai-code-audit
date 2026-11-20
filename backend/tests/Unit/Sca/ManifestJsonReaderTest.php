<?php

namespace Tests\Unit\Sca;

use App\Exceptions\ManifestParseException;
use App\Services\Sca\ManifestJsonReader;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class ManifestJsonReaderTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().'/reader-'.bin2hex(random_bytes(5));
        mkdir($this->base.'/repo', 0755, true);
        mkdir($this->base.'/outside', 0755, true);
        file_put_contents($this->base.'/outside/package.json', '{"a":1}');
        file_put_contents($this->base.'/repo/ok.json', '{"a":{"b":1}}');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->base);

        parent::tearDown();
    }

    public function test_reads_as_object_or_associative_array(): void
    {
        $this->assertSame(1, ManifestJsonReader::read($this->base.'/repo', 'ok.json', 100, false)->a->b);
        $this->assertSame(['a' => ['b' => 1]], ManifestJsonReader::read($this->base.'/repo', 'ok.json', 100, true));
    }

    public function test_file_reached_through_a_symlinked_parent_directory_is_rejected(): void
    {
        symlink($this->base.'/outside', $this->base.'/repo/linked');

        $this->expectException(ManifestParseException::class);

        ManifestJsonReader::read($this->base.'/repo', 'linked/package.json', 100, true);
    }

    public function test_relative_traversal_outside_root_is_rejected(): void
    {
        $this->expectException(ManifestParseException::class);

        ManifestJsonReader::read($this->base.'/repo', '../outside/package.json', 100, true);
    }

    public function test_size_limit_is_enforced(): void
    {
        $this->expectException(ManifestParseException::class);

        ManifestJsonReader::read($this->base.'/repo', 'ok.json', 5, true);
    }

    public function test_file_exactly_at_the_limit_is_accepted(): void
    {
        $this->assertNotNull(ManifestJsonReader::read($this->base.'/repo', 'ok.json', strlen('{"a":{"b":1}}'), true));
    }
}
