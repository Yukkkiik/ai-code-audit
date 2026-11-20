<?php

namespace Tests\Unit\Sca;

use App\Services\Ingestion\RepositoryFileWalker;
use App\Services\Sca\ManifestFinder;
use App\Services\Sca\ManifestProject;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class ManifestFinderTest extends TestCase
{
    private string $root;

    private string $outside;

    protected function setUp(): void
    {
        parent::setUp();

        $base = sys_get_temp_dir().'/manifest-'.bin2hex(random_bytes(5));
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

    private function touch(string $path): void
    {
        $full = $this->root.'/'.$path;
        is_dir(dirname($full)) || mkdir(dirname($full), 0755, true);
        file_put_contents($full, '{}');
    }

    /**
     * @return list<ManifestProject>
     */
    private function find(): array
    {
        return (new ManifestFinder(new RepositoryFileWalker(['node_modules', 'vendor', '.git'])))->find($this->root);
    }

    public function test_finds_package_json_and_sibling_lockfile(): void
    {
        $this->touch('package.json');
        $this->touch('package-lock.json');

        $projects = $this->find();

        $this->assertCount(1, $projects);
        $this->assertSame('', $projects[0]->directory);
        $this->assertSame('package.json', $projects[0]->packageJsonPath);
        $this->assertSame('package-lock.json', $projects[0]->lockfilePath);
    }

    public function test_finds_package_json_without_lockfile(): void
    {
        $this->touch('app/package.json');

        $projects = $this->find();

        $this->assertCount(1, $projects);
        $this->assertSame('app', $projects[0]->directory);
        $this->assertSame('app/package.json', $projects[0]->packageJsonPath);
        $this->assertNull($projects[0]->lockfilePath);
    }

    public function test_finds_multiple_projects_in_monorepo_sorted(): void
    {
        $this->touch('packages/web/package.json');
        $this->touch('packages/web/package-lock.json');
        $this->touch('package.json');
        $this->touch('packages/api/package.json');

        $this->assertSame(['', 'packages/api', 'packages/web'], array_map(fn ($p) => $p->directory, $this->find()));
    }

    public function test_ignores_node_modules_vendor_and_git(): void
    {
        $this->touch('node_modules/lodash/package.json');
        $this->touch('packages/web/node_modules/x/package.json');
        $this->touch('vendor/pkg/package.json');
        $this->touch('.git/package.json');

        $this->assertSame([], $this->find());
    }

    public function test_ignores_lockfile_without_package_json(): void
    {
        $this->touch('orphan/package-lock.json');

        $this->assertSame([], $this->find());
    }

    public function test_ignores_yarn_and_pnpm_lockfiles(): void
    {
        $this->touch('package.json');
        $this->touch('yarn.lock');
        $this->touch('pnpm-lock.yaml');

        $projects = $this->find();

        $this->assertCount(1, $projects);
        $this->assertNull($projects[0]->lockfilePath);
    }

    public function test_does_not_follow_symlinks(): void
    {
        file_put_contents($this->outside.'/package.json', '{}');
        mkdir($this->outside.'/dir');
        file_put_contents($this->outside.'/dir/package.json', '{}');
        symlink($this->outside.'/package.json', $this->root.'/package.json');
        symlink($this->outside.'/dir', $this->root.'/linked');

        $this->assertSame([], $this->find());
    }

    public function test_empty_repository_returns_empty_list(): void
    {
        $this->assertSame([], $this->find());
    }

    public function test_name_match_is_exact(): void
    {
        $this->touch('package.json.bak');
        $this->touch('Package.json');
        $this->touch('package-lock.json5');

        $this->assertSame([], $this->find());
    }
}
