<?php

namespace App\Services\Ingestion;

use App\Exceptions\InvalidRepositoryUrlException;

/**
 * Kebijakan URL repositori Git publik (anti-SSRF):
 * https saja, host dari allowlist, tanpa kredensial/port/query/fragment,
 * path berupa segmen aman, dan seluruh IP hasil DNS harus alamat publik.
 */
class GitUrlValidator
{
    private const MAX_LENGTH = 2048;

    /**
     * Rentang yang tidak tercakup flag filter_var bawaan PHP atau dicek ulang secara eksplisit.
     */
    private const BLOCKED_RANGES = ['0.0.0.0/8', '100.64.0.0/10', '::/128', '::1/128', 'fc00::/7', 'fe80::/10'];

    /**
     * @param  list<string>  $allowedHosts
     */
    public function __construct(
        private HostResolver $resolver,
        private array $allowedHosts,
    ) {}

    public function validate(string $url): ValidatedGitUrl
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > self::MAX_LENGTH) {
            throw new InvalidRepositoryUrlException('URL repositori wajib diisi dan maksimal 2048 karakter.');
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidRepositoryUrlException('URL repositori tidak valid.');
        }

        if (strtolower($parts['scheme']) !== 'https') {
            throw new InvalidRepositoryUrlException('URL repositori harus menggunakan https://.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidRepositoryUrlException('URL repositori tidak boleh berisi username, password, atau token.');
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            throw new InvalidRepositoryUrlException('Port pada URL repositori tidak diizinkan.');
        }

        if (isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidRepositoryUrlException('URL repositori tidak boleh berisi query (?) atau fragment (#).');
        }

        $host = strtolower($parts['host']);

        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            throw new InvalidRepositoryUrlException('Gunakan nama host repositori, bukan alamat IP.');
        }

        if (! in_array($host, $this->allowedHosts, true)) {
            throw new InvalidRepositoryUrlException("Host {$host} tidak didukung. Host yang didukung: ".implode(', ', $this->allowedHosts).'.');
        }

        $segments = array_values(array_filter(explode('/', $parts['path'] ?? ''), fn (string $segment) => $segment !== ''));

        if (count($segments) < 2) {
            throw new InvalidRepositoryUrlException('URL harus menunjuk ke sebuah repositori, mis. https://github.com/pemilik/repo.');
        }

        foreach ($segments as $segment) {
            // Wajib diawali huruf/angka: menolak "..", "." dan nilai yang bisa terbaca sebagai opsi git ("-...").
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $segment) !== 1) {
                throw new InvalidRepositoryUrlException('Path repositori berisi karakter yang tidak diizinkan.');
            }
        }

        $nameSegments = $segments;
        $nameSegments[array_key_last($nameSegments)] = preg_replace('/\.git$/i', '', end($segments));

        if ($nameSegments[array_key_last($nameSegments)] === '') {
            throw new InvalidRepositoryUrlException('Nama repositori tidak valid.');
        }

        $this->assertResolvesToPublicAddresses($host);

        return new ValidatedGitUrl('https://'.$host.'/'.implode('/', $segments), implode('/', $nameSegments));
    }

    public static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        // IPv4-mapped IPv6 (::ffff:127.0.0.1) dinilai berdasarkan alamat IPv4-nya.
        if (str_starts_with(strtolower($ip), '::ffff:') && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return self::isPublicIp(substr($ip, 7));
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        foreach (self::BLOCKED_RANGES as $range) {
            if (self::inRange($ip, $range)) {
                return false;
            }
        }

        return true;
    }

    private function assertResolvesToPublicAddresses(string $host): void
    {
        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            throw new InvalidRepositoryUrlException('Host repositori tidak dapat ditemukan (DNS).');
        }

        foreach ($addresses as $address) {
            if (! self::isPublicIp($address)) {
                throw new InvalidRepositoryUrlException('Host repositori mengarah ke alamat jaringan internal dan tidak diizinkan.');
            }
        }
    }

    private static function inRange(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $ipBinary = inet_pton($ip);
        $subnetBinary = inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $fullBytes = intdiv((int) $bits, 8);
        $remainingBits = (int) $bits % 8;

        if (substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remainingBits)) & 0xFF);

        return ($ipBinary[$fullBytes] & $mask) === ($subnetBinary[$fullBytes] & $mask);
    }
}
