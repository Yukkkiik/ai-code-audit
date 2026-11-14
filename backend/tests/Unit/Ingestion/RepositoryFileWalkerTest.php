<?php

namespace Tests\Unit\Ingestion;

use App\Services\Ingestion\RepositoryFileWalker;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class RepositoryFileWalkerTest extends TestCase
{
    private string $root;

    private string $outside;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/walker-'.bin2hex(random_bytes(5));
        $this->root = $base.'/repo';
        $this->outside = $base.'/outside';

        foreach (['src/lib', 'node_modules/lodash', 'packages/web/node_modules/x', 'vendor/pkg', '.git/objects', 'docs'] as $dir) {
            mkdir($this->root.'/'.$dir, 0755, true);
        }
        mkdir($this->outside, 0755, true);

        file_put_contents($this->root.'/package.json', '{}');
        file_put_contents($this->root.'/src/index.js', 'a');
        file_put_contents($this->root.'/src/lib/util.ts', 'b');
        file_put_contents($this->root.'/docs/README.md', 'c');
        file_put_contents($this->root.'/node_modules/lodash/index.js', 'x');
        file_put_contents($this->root.'/packages/web/node_modules/x/index.js', 'x');
        file_put_contents($this->root.'/vendor/pkg/a.php', 'x');
        file_put_contents($this->root.'/.git/objects/abc', 'x');
        file_put_contents($this->outside.'/secret.js', 'rahasia');

        symlink($this->outside, $this->root.'/src/linked-dir');
        symlink($this->outside.'/secret.js', $this->root.'/src/linked-file.js');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(dirname($this->root));

        parent::tearDown();
    }

    public function test_lists_regular_files_relative_and_sorted_skipping_ignored_directories(): void
    {
        $walker = new RepositoryFileWalker(['node_modules', 'vendor', '.git']);

        $this->assertSame([
            'docs/README.md',
            'package.json',
            'src/index.js',
            'src/lib/util.ts',
        ], $walker->files($this->root));
    }

    public function test_symlinks_are_never_followed_or_listed(): void
    {
        $files = (new RepositoryFileWalker(['node_modules', 'vendor', '.git']))->files($this->root);

        $this->assertNotContains('src/linked-file.js', $files);
        $this->assertNotContains('src/linked-dir/secret.js', $files);
    }

    public function test_extension_filter_is_case_insensitive(): void
    {
        file_put_contents($this->root.'/src/App.JSX', 'c');
        $walker = new RepositoryFileWalker(['node_modules', 'vendor', '.git']);

        $this->assertSame(['src/App.JSX', 'src/index.js', 'src/lib/util.ts'], $walker->files($this->root, ['js', 'jsx', 'ts']));
    }
}
