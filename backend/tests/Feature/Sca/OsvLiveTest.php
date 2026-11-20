<?php

namespace Tests\Feature\Sca;

use App\Services\Sca\OsvClient;
use App\Services\Sca\OsvQuery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Memanggil OSV.dev SUNGGUHAN. Dikecualikan dari run default (grup `live` di phpunit.xml) dan juga dilewati
 * kecuali RUN_LIVE_OSV_TESTS=1. Jalankan manual: RUN_LIVE_OSV_TESTS=1 php artisan test --group=live
 */
#[Group('live')]
class OsvLiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('RUN_LIVE_OSV_TESTS') !== '1') {
            $this->markTestSkipped('Test live OSV dilewati; set RUN_LIVE_OSV_TESTS=1 untuk menjalankannya.');
        }
    }

    public function test_lodash_4_17_15_has_advisories_and_their_details_parse(): void
    {
        $client = $this->app->make(OsvClient::class);

        $result = $client->query([new OsvQuery('lodash', '4.17.15')])[0];

        $this->assertFalse($result->isFailed(), (string) $result->message());
        $this->assertNotEmpty($result->vulnerabilities, 'lodash 4.17.15 diharapkan punya advisori di OSV.');

        $ids = array_map(fn ($v) => $v->id, array_slice($result->vulnerabilities, 0, 3));

        foreach ($client->fetchVulnerabilities($ids) as $id => $detail) {
            $this->assertFalse($detail->isFailed(), "Detail $id gagal: ".$detail->message());
            $this->assertSame((string) $id, $detail->detail->id);
        }
    }
}
