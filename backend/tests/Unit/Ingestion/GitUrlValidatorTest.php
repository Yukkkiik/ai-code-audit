<?php

namespace Tests\Unit\Ingestion;

use App\Exceptions\InvalidRepositoryUrlException;
use App\Services\Ingestion\GitUrlValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeHostResolver;

class GitUrlValidatorTest extends TestCase
{
    private function validator(array $records = ['github.com' => ['140.82.112.3'], 'gitlab.com' => ['172.65.251.78']]): GitUrlValidator
    {
        return new GitUrlValidator(new FakeHostResolver($records), ['github.com', 'gitlab.com', 'bitbucket.org']);
    }

    public function test_valid_github_url_is_normalized(): void
    {
        $result = $this->validator()->validate('  https://GitHub.com/Organisasi/Contoh-Repo.git/  ');

        $this->assertSame('https://github.com/Organisasi/Contoh-Repo.git', $result->url);
        $this->assertSame('Organisasi/Contoh-Repo', $result->repositoryName);
    }

    public function test_gitlab_subgroup_path_is_allowed(): void
    {
        $result = $this->validator()->validate('https://gitlab.com/grup/sub-grup/proyek');

        $this->assertSame('grup/sub-grup/proyek', $result->repositoryName);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedUrls(): array
    {
        return [
            'http tanpa TLS' => ['http://github.com/org/repo'],
            'ssh' => ['ssh://git@github.com/org/repo.git'],
            'scp style' => ['git@github.com:org/repo.git'],
            'file scheme' => ['file:///etc/passwd'],
            'kredensial di URL' => ['https://user:pass@github.com/org/repo'],
            'username saja' => ['https://token@github.com/org/repo'],
            'port non standar' => ['https://github.com:8443/org/repo'],
            'host tidak diizinkan' => ['https://evil.example.com/org/repo'],
            'subdomain tidak diizinkan' => ['https://api.github.com/org/repo'],
            'host mirip' => ['https://github.com.evil.com/org/repo'],
            'IP literal' => ['https://140.82.112.3/org/repo'],
            'IPv6 literal' => ['https://[::1]/org/repo'],
            'query string' => ['https://github.com/org/repo?ref=main'],
            'fragment' => ['https://github.com/org/repo#readme'],
            'hanya owner' => ['https://github.com/org'],
            'path traversal' => ['https://github.com/org/../repo'],
            'karakter aneh' => ['https://github.com/org/re po'],
            'opsi git disamarkan' => ['https://github.com/--upload-pack=x/repo'],
            'kosong' => [''],
            'bukan URL' => ['bukan url'],
        ];
    }

    #[DataProvider('rejectedUrls')]
    public function test_unsafe_urls_are_rejected(string $url): void
    {
        $this->expectException(InvalidRepositoryUrlException::class);

        $this->validator()->validate($url);
    }

    public function test_url_longer_than_2048_characters_is_rejected(): void
    {
        $this->expectException(InvalidRepositoryUrlException::class);

        $this->validator()->validate('https://github.com/org/'.str_repeat('a', 2050));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function privateAddresses(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'private 10/8' => ['10.0.0.5'],
            'private 172.16/12' => ['172.16.3.4'],
            'private 192.168/16' => ['192.168.1.1'],
            'cloud metadata' => ['169.254.169.254'],
            'CGNAT 100.64/10' => ['100.64.1.1'],
            'unspecified' => ['0.0.0.0'],
            'IPv6 loopback' => ['::1'],
            'IPv6 link-local' => ['fe80::1'],
            'IPv6 unique local' => ['fc00::1'],
            'IPv4-mapped loopback' => ['::ffff:127.0.0.1'],
        ];
    }

    #[DataProvider('privateAddresses')]
    public function test_host_resolving_to_private_address_is_rejected(string $ip): void
    {
        $this->expectException(InvalidRepositoryUrlException::class);

        $this->validator(['github.com' => ['140.82.112.3', $ip]])->validate('https://github.com/org/repo');
    }

    public function test_unresolvable_host_is_rejected(): void
    {
        $this->expectException(InvalidRepositoryUrlException::class);

        $this->validator([])->validate('https://github.com/org/repo');
    }

    public function test_public_addresses_are_recognized(): void
    {
        $this->assertTrue(GitUrlValidator::isPublicIp('140.82.112.3'));
        $this->assertTrue(GitUrlValidator::isPublicIp('2606:50c0:8000::153'));
        $this->assertFalse(GitUrlValidator::isPublicIp('bukan-ip'));
    }
}
