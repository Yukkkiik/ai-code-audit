<?php

namespace App\Services\Sca;

use App\Enums\DependencyScope;
use App\Enums\UnresolvedReason;
use App\Enums\VersionStatus;
use App\Exceptions\ManifestParseException;
use JsonException;
use stdClass;

/**
 * Membaca dependensi langsung dari satu `package.json` tanpa menebak versi: hanya versi semver
 * pasti yang `exact`; rentang, tag, dan sumber non-registry tetap `unresolved`.
 * Isi berkas diperlakukan sebagai data: tidak ada script yang dijalankan dan tidak ada akses jaringan.
 */
class PackageJsonParser
{
    private const SECTIONS = [
        'dependencies' => DependencyScope::Production,
        'devDependencies' => DependencyScope::Development,
        'optionalDependencies' => DependencyScope::Optional,
        'peerDependencies' => DependencyScope::Peer,
    ];

    private const NON_REGISTRY_PREFIXES = [
        'git+', 'git:', 'git://', 'github:', 'gitlab:', 'bitbucket:', 'gist:',
        'file:', 'link:', 'workspace:', 'http://', 'https://',
    ];

    private const MAX_NAME_LENGTH = 214;

    private const MAX_SPEC_LENGTH = 1024;

    private const MAX_JSON_DEPTH = 64;

    private const SEMVER = '(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)\.(?:0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?';

    public function __construct(private int $maxBytes) {}

    /**
     * @param  string  $relativePath  path relatif terhadap $root (dari ManifestFinder)
     * @return list<ParsedDependency>
     *
     * @throws ManifestParseException
     */
    public function parse(string $root, string $relativePath): array
    {
        $manifest = $this->decode(rtrim($root, '/').'/'.$relativePath);

        $result = [];

        foreach (self::SECTIONS as $section => $scope) {
            if (! property_exists($manifest, $section)) {
                continue;
            }

            if (! $manifest->{$section} instanceof stdClass) {
                throw ManifestParseException::invalid();
            }

            foreach ((array) $manifest->{$section} as $name => $spec) {
                $result[] = $this->classify((string) $name, $spec, $scope, $relativePath);
            }
        }

        return $result;
    }

    private function decode(string $path): stdClass
    {
        if (is_link($path) || ! is_file($path)) {
            throw ManifestParseException::unreadable();
        }

        $size = filesize($path);

        if ($size === false) {
            throw ManifestParseException::unreadable();
        }

        if ($size > $this->maxBytes) {
            throw ManifestParseException::tooLarge();
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw ManifestParseException::unreadable();
        }

        try {
            $manifest = json_decode($contents, false, self::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ManifestParseException::invalid();
        }

        if (! $manifest instanceof stdClass) {
            throw ManifestParseException::invalid();
        }

        return $manifest;
    }

    private function classify(string $name, mixed $spec, DependencyScope $scope, string $manifestPath): ParsedDependency
    {
        if (strlen($name) > self::MAX_NAME_LENGTH) {
            throw ManifestParseException::invalid();
        }

        if (! is_string($spec)) {
            return $this->unresolved($name, null, $scope, UnresolvedReason::RangeWithoutLockfile, $manifestPath);
        }

        $declared = substr($spec, 0, self::MAX_SPEC_LENGTH);
        $trimmed = trim($spec);

        if (str_starts_with($trimmed, 'npm:')) {
            return $this->alias($name, $declared, substr($trimmed, 4), $scope, $manifestPath);
        }

        if ($this->isNonRegistry($trimmed)) {
            return $this->unresolved($name, $declared, $scope, UnresolvedReason::NonRegistrySource, $manifestPath);
        }

        if (($version = $this->exactVersion($trimmed)) !== null) {
            return $this->exact($name, $declared, $version, $scope, $manifestPath);
        }

        return $this->unresolved($name, $declared, $scope, UnresolvedReason::RangeWithoutLockfile, $manifestPath);
    }

    /**
     * `npm:<nama-asli>@<spec>`: hanya versi pasti yang dapat ditentukan, dan nama yang
     * dikueri ke OSV adalah nama asli, bukan alias.
     */
    private function alias(string $name, string $declared, string $target, DependencyScope $scope, string $manifestPath): ParsedDependency
    {
        $at = strrpos($target, '@');

        if ($at !== false && $at > 0) {
            $real = substr($target, 0, $at);
            $version = $this->exactVersion(substr($target, $at + 1));

            if ($version !== null && $real !== '' && strlen($real) <= self::MAX_NAME_LENGTH) {
                return $this->exact($real, $declared, $version, $scope, $manifestPath);
            }
        }

        return $this->unresolved($name, $declared, $scope, UnresolvedReason::NonRegistrySource, $manifestPath);
    }

    private function isNonRegistry(string $spec): bool
    {
        foreach (self::NON_REGISTRY_PREFIXES as $prefix) {
            if (str_starts_with($spec, $prefix)) {
                return true;
            }
        }

        // Singkatan GitHub: user/repo atau user/repo#ref.
        return preg_match('/^[\w.-]+\/[\w.-]+(#.*)?$/', $spec) === 1;
    }

    private function exactVersion(string $spec): ?string
    {
        return preg_match('/^=?v?('.self::SEMVER.')$/', trim($spec), $m) === 1 ? $m[1] : null;
    }

    private function exact(string $name, string $declared, string $version, DependencyScope $scope, string $manifestPath): ParsedDependency
    {
        return new ParsedDependency($name, $declared, $scope, VersionStatus::Exact, $version, null, $manifestPath);
    }

    private function unresolved(string $name, ?string $declared, DependencyScope $scope, UnresolvedReason $reason, string $manifestPath): ParsedDependency
    {
        return new ParsedDependency($name, $declared, $scope, VersionStatus::Unresolved, null, $reason, $manifestPath);
    }
}
