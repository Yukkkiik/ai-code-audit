<?php

namespace App\Services\Sca;

use App\Enums\DependencyRelation;
use App\Enums\DependencyScope;
use App\Enums\ManifestType;
use App\Enums\UnresolvedReason;
use App\Enums\VersionStatus;
use App\Exceptions\ManifestParseException;

/**
 * Membaca dependensi dari `package-lock.json` v2/v3 (objek `packages`). Versi diambil apa adanya dari
 * lockfile; hanya semver pasti dari sumber registry yang `exact`. Isi berkas diperlakukan sebagai data:
 * URL `resolved` tidak pernah diakses, tidak ada proses yang dijalankan.
 */
class PackageLockParser
{
    private const DEPENDENCY_SECTIONS = ['dependencies', 'devDependencies', 'optionalDependencies', 'peerDependencies'];

    private const MAX_NAME_LENGTH = 214;

    private const MAX_PATH_LENGTH = 1024;

    public function __construct(private int $maxBytes) {}

    /**
     * @return ParsedLockfile `unsupported` untuk lockfile v1 atau versi tidak dikenal
     *
     * @throws ManifestParseException
     */
    public function parse(string $root, string $lockfilePath): ParsedLockfile
    {
        $lock = ManifestJsonReader::read($root, $lockfilePath, $this->maxBytes, true);

        if (! is_array($lock) || ($lock !== [] && array_is_list($lock))) {
            throw ManifestParseException::invalid();
        }

        $type = match ($lock['lockfileVersion'] ?? null) {
            2 => ManifestType::PackageLockV2,
            3 => ManifestType::PackageLockV3,
            default => null,
        };

        if ($type === null) {
            return ParsedLockfile::unsupported();
        }

        $packages = $lock['packages'] ?? null;

        if (! is_array($packages) || ($packages !== [] && array_is_list($packages))) {
            throw ManifestParseException::invalid();
        }

        $direct = $this->declaredDirectDependencies($packages);
        $result = [];

        foreach ($packages as $key => $entry) {
            $key = (string) $key;

            if (! $this->isDependencyKey($key)) {
                continue;
            }

            if (! is_array($entry) || strlen($key) > self::MAX_PATH_LENGTH) {
                throw ManifestParseException::invalid();
            }

            $result[] = $this->dependency($key, $entry, $direct, $type, $lockfilePath);
        }

        return ParsedLockfile::supported($type, $result);
    }

    /**
     * Nama dependensi langsung = gabungan empat bagian dependensi dari entri root "" dan dari semua
     * entri workspace (key tanpa segmen node_modules). Entri tersebut hanya dibaca untuk himpunan ini,
     * bukan dimasukkan sebagai dependensi. Spesifikasi root didahulukan.
     *
     * @param  array<array-key, mixed>  $packages
     * @return array<string, string|null> nama => spesifikasi yang dideklarasikan
     */
    private function declaredDirectDependencies(array $packages): array
    {
        $sources = [];

        if (isset($packages[''])) {
            $sources[] = $packages[''];
        }

        foreach ($packages as $key => $entry) {
            if ((string) $key !== '' && ! $this->isDependencyKey((string) $key)) {
                $sources[] = $entry;
            }
        }

        $direct = [];

        foreach ($sources as $source) {
            foreach (self::DEPENDENCY_SECTIONS as $section) {
                if (! is_array($source) || ! is_array($source[$section] ?? null)) {
                    continue;
                }

                foreach ($source[$section] as $name => $spec) {
                    $direct[(string) $name] ??= is_string($spec) ? $spec : null;
                }
            }
        }

        return $direct;
    }

    /**
     * Dependensi hanya jika key diawali "node_modules/" atau memuat "/node_modules/" (batas segmen).
     */
    private function isDependencyKey(string $key): bool
    {
        return str_starts_with($key, 'node_modules/') || str_contains($key, '/node_modules/');
    }

    /**
     * @param  array<array-key, mixed>  $entry
     * @param  array<string, string|null>  $direct
     */
    private function dependency(string $key, array $entry, array $direct, ManifestType $type, string $lockfilePath): ParsedDependency
    {
        $keyName = $this->nameFromKey($key);
        $name = isset($entry['name']) && is_string($entry['name']) && $entry['name'] !== '' ? $entry['name'] : $keyName;

        if (strlen($name) > self::MAX_NAME_LENGTH) {
            throw ManifestParseException::invalid();
        }

        $isDirect = $key === 'node_modules/'.$keyName && array_key_exists($keyName, $direct);
        $version = isset($entry['version']) && is_string($entry['version']) ? ExactVersion::parse($entry['version']) : null;
        $reason = $this->unresolvedReason($entry, $name, $version);

        return new ParsedDependency(
            name: $name,
            declaredSpec: $isDirect && $direct[$keyName] !== null ? substr($direct[$keyName], 0, 1024) : null,
            scope: $this->scope($entry),
            versionStatus: $reason === null ? VersionStatus::Exact : VersionStatus::Unresolved,
            version: $reason === null ? $version : null,
            unresolvedReason: $reason,
            manifestPath: $lockfilePath,
            sourceFileType: $type,
            relation: $isDirect ? DependencyRelation::Direct : DependencyRelation::Transitive,
            lockPath: $key,
        );
    }

    /**
     * Segmen setelah "node_modules/" terakhir: "name" atau "@scope/name".
     */
    private function nameFromKey(string $key): string
    {
        $segments = explode('/', $key);
        $rest = array_slice($segments, max(array_keys($segments, 'node_modules', true)) + 1);

        $scoped = isset($rest[0]) && str_starts_with($rest[0], '@');
        $valid = $scoped
            ? count($rest) === 2 && strlen($rest[0]) > 1 && $rest[1] !== ''
            : count($rest) === 1 && $rest[0] !== '';

        if (! $valid) {
            throw ManifestParseException::invalid();
        }

        return implode('/', $rest);
    }

    /**
     * @param  array<array-key, mixed>  $entry
     */
    private function scope(array $entry): DependencyScope
    {
        return match (true) {
            ($entry['dev'] ?? false) === true => DependencyScope::Development,
            ($entry['optional'] ?? false) === true => DependencyScope::Optional,
            ($entry['devOptional'] ?? false) === true => DependencyScope::Development,
            ($entry['peer'] ?? false) === true => DependencyScope::Peer,
            default => DependencyScope::Production,
        };
    }

    /**
     * @param  array<array-key, mixed>  $entry
     * @return UnresolvedReason|null null = versi pasti dari sumber registry
     */
    private function unresolvedReason(array $entry, string $name, ?string $version): ?UnresolvedReason
    {
        if (($entry['link'] ?? false) === true) {
            return UnresolvedReason::NonRegistrySource;
        }

        $resolved = $entry['resolved'] ?? null;

        if ($resolved !== null && (! is_string($resolved) || ! in_array(parse_url($resolved, PHP_URL_SCHEME), ['http', 'https'], true))) {
            return UnresolvedReason::NonRegistrySource;
        }

        if ($version === null) {
            return UnresolvedReason::MissingVersion;
        }

        if ($resolved !== null && ! $this->isRegistryTarball($resolved, $name, $version)) {
            return UnresolvedReason::NonRegistrySource;
        }

        return null;
    }

    /**
     * Tata letak registry standar: `…/<nama>/-/<nama-tanpa-scope>-<versi>.tgz`. Nama di URL dan nama
     * berkas harus sama dengan nama paket dan versinya, jika tidak sumbernya tidak dianggap registry.
     */
    private function isRegistryTarball(string $resolved, string $name, string $version): bool
    {
        $path = rawurldecode((string) parse_url($resolved, PHP_URL_PATH));
        $bare = str_contains($name, '/') ? substr($name, (int) strrpos($name, '/') + 1) : $name;

        return str_ends_with($path, '/'.$name.'/-/'.$bare.'-'.$version.'.tgz');
    }
}
