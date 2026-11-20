<?php

namespace Tests\Unit\Sca;

use App\Services\Sca\PackageLockParser;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PackageLockParserNoNetworkTest extends TestCase
{
    public function test_parsing_never_requests_resolved_urls(): void
    {
        Http::fake();
        $root = sys_get_temp_dir().'/pkglock-net-'.bin2hex(random_bytes(5));
        mkdir($root);
        file_put_contents($root.'/package-lock.json', json_encode(['lockfileVersion' => 3, 'packages' => [
            '' => [],
            'node_modules/a' => ['version' => '1.0.0', 'resolved' => 'https://registry.npmjs.org/a/-/a-1.0.0.tgz'],
            'node_modules/b' => ['version' => '1.0.0', 'resolved' => 'https://169.254.169.254/latest/meta-data'],
            'node_modules/c' => ['version' => '1.0.0', 'resolved' => 'git+ssh://git@github.com/x/c.git#abc'],
        ]]));

        try {
            $result = (new PackageLockParser(1_000_000))->parse($root, 'package-lock.json');
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }

        $this->assertCount(3, $result->dependencies);
        Http::assertNothingSent();
    }
}
