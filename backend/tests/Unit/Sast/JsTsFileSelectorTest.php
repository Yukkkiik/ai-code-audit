<?php

namespace Tests\Unit\Sast;

use App\Services\Sast\JsTsFileSelector;
use App\Services\Sast\JsTsSelection;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class JsTsFileSelectorTest extends TestCase
{
    private string $root;

    private string $outside;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/sast-select-'.bin2hex(random_bytes(5));
        $this->root = $base.'/repo';
        $this->outside = $base.'/outside';
        mkdir($this->root, 0755, true);
        mkdir($this->outside, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory(dirname($this->root));

        parent::tearDown();
    }

    private function put(string $path, string $content = 'x'): void
    {
        $full = $this->root.'/'.$path;
        is_dir(dirname($full)) || mkdir(dirname($full), 0755, true);
        file_put_contents($full, $content);
    }

    private function select(int $maxFiles = 100, int $maxBytes = 1000): JsTsSelection
    {
        return (new JsTsFileSelector(
            ['js', 'jsx', 'mjs', 'cjs', 'ts', 'tsx', 'mts', 'cts'],
            ['node_modules', 'vendor', '.git', 'dist', 'build', 'coverage'],
            ['.min.js', '.d.ts'],
            $maxFiles,
            $maxBytes,
        ))->select($this->root);
    }

    public function test_selects_all_supported_extensions_case_insensitively_in_stable_order(): void
    {
        foreach (['b.js', 'a.jsx', 'c.mjs', 'd.cjs', 'e.ts', 'f.tsx', 'g.mts', 'h.cts', 'Z.TS', 'src/app.js'] as $file) {
            $this->put($file);
        }
        $this->put('readme.md');
        $this->put('data.json');

        $this->assertSame(
            ['Z.TS', 'a.jsx', 'b.js', 'c.mjs', 'd.cjs', 'e.ts', 'f.tsx', 'g.mts', 'h.cts', 'src/app.js'],
            $this->select()->files,
        );
    }

    public function test_skips_ignored_directories_minified_and_declaration_files(): void
    {
        $this->put('src/ok.ts');
        $this->put('node_modules/x/index.js');
        $this->put('packages/a/dist/out.js');
        $this->put('build/out.js');
        $this->put('coverage/lcov.js');
        $this->put('.git/hooks/h.js');
        $this->put('public/app.min.js');
        $this->put('src/types.d.ts');

        $selection = $this->select();

        $this->assertSame(['src/ok.ts'], $selection->files);
        $this->assertSame(2, $selection->skippedGenerated);
    }

    public function test_skips_empty_and_oversized_files(): void
    {
        $this->put('empty.js', '');
        $this->put('big.js', str_repeat('a', 1001));
        $this->put('edge.js', str_repeat('a', 1000));

        $selection = $this->select();

        $this->assertSame(['edge.js'], $selection->files);
        $this->assertSame(1, $selection->skippedTooLarge);
    }

    public function test_truncates_deterministically_at_max_files(): void
    {
        foreach (['c.js', 'a.js', 'b.js', 'd.js'] as $file) {
            $this->put($file);
        }

        $selection = $this->select(maxFiles: 2);

        $this->assertSame(['a.js', 'b.js'], $selection->files);
        $this->assertSame(2, $selection->skippedOverLimit);
        $this->assertTrue($selection->isTruncated());
    }

    public function test_symlinks_pointing_outside_root_are_ignored(): void
    {
        $this->put('ok.js');
        file_put_contents($this->outside.'/secret.js', 'rahasia');
        mkdir($this->outside.'/dir');
        file_put_contents($this->outside.'/dir/s.js', 'rahasia');

        if (! @symlink($this->outside.'/secret.js', $this->root.'/link.js') || ! @symlink($this->outside.'/dir', $this->root.'/linkdir')) {
            $this->markTestSkipped('Symlink tidak didukung/diizinkan di environment ini.');
        }

        $this->assertSame(['ok.js'], $this->select()->files);
    }

    public function test_repository_without_js_ts_files_yields_empty_selection(): void
    {
        $this->put('README.md');

        $selection = $this->select();

        $this->assertTrue($selection->isEmpty());
        $this->assertFalse($selection->isTruncated());
    }

    public function test_missing_root_yields_empty_selection(): void
    {
        $selection = (new JsTsFileSelector(['js'], [], [], 10, 100))->select($this->root.'/tidak-ada');

        $this->assertTrue($selection->isEmpty());
    }
}
