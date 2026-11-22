<?php

namespace Tests\Feature\Sast;

use App\Services\Sast\JsTsFileSelector;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class SastIgnoredDirectoriesTest extends TestCase
{
    public function test_build_output_directories_are_ignored_with_default_config(): void
    {
        $root = sys_get_temp_dir().'/sast-ignore-'.bin2hex(random_bytes(4));

        foreach (['src', '.next/static', 'node_modules/x', 'dist', 'build', 'coverage', '.nuxt', '.turbo', '.cache'] as $directory) {
            mkdir($root.'/'.$directory, 0755, true);
            file_put_contents($root.'/'.$directory.'/out.js', 'x');
        }

        try {
            $selection = $this->app->make(JsTsFileSelector::class)->select($root);
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }

        $this->assertSame(['src/out.js'], $selection->files);
    }
}
