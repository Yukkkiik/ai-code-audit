<?php

namespace App\Services\Sca;

use App\Enums\DependencyScope;
use App\Enums\UnresolvedReason;
use App\Exceptions\ManifestParseException;

/**
 * Menggabungkan finder dan parser menjadi daftar dependensi satu audit.
 * Per proyek: lockfile yang didukung adalah sumber utama; selain itu (v1, rusak, tanpa lockfile)
 * dipakai aturan "tanpa lockfile" dari package.json. Kegagalan satu manifest dicatat, proyek lain lanjut.
 */
class DependencyCollector
{
    private const MAX_PATH_LENGTH = 1024;

    private const MAX_VERSION_LENGTH = 100;

    private const MAX_SPEC_LENGTH = 1024;

    private const SCOPE_PRIORITY = ['production' => 0, 'optional' => 1, 'development' => 2, 'peer' => 3];

    public function __construct(
        private ManifestFinder $finder,
        private PackageJsonParser $packageJson,
        private PackageLockParser $lockfile,
    ) {}

    public function collect(string $root): CollectedDependencies
    {
        $projects = $this->finder->find($root);

        if ($projects === []) {
            return new CollectedDependencies(false, [], []);
        }

        $failures = [];

        /** @var array<string, array{project: ManifestProject, lock: ParsedLockfile|null, lockExists: bool}> $states */
        $states = [];

        foreach ($projects as $project) {
            if (! $this->pathsAreStorable($project)) {
                $failures[] = new ManifestFailure($project->packageJsonPath, 'Path berkas tidak valid atau terlalu panjang.');

                continue;
            }

            $lock = null;

            if ($project->lockfilePath !== null) {
                try {
                    $lock = $this->lockfile->parse($root, $project->lockfilePath);
                } catch (ManifestParseException $e) {
                    $failures[] = new ManifestFailure($project->lockfilePath, $e->getMessage());
                }
            }

            $states[$project->directory] = ['project' => $project, 'lock' => $lock, 'lockExists' => $project->lockfilePath !== null];
        }

        $dependencies = [];

        foreach ($states as $state) {
            $project = $state['project'];

            // Workspace tanpa lockfile sendiri yang terdaftar di lockfile induknya sudah tercakup di sana.
            if ($project->lockfilePath === null && $this->isCoveredWorkspace($project->directory, $states)) {
                continue;
            }

            $declared = [];

            try {
                $declared = $this->packageJson->parse($root, $project->packageJsonPath);
            } catch (ManifestParseException $e) {
                $failures[] = new ManifestFailure($project->packageJsonPath, $e->getMessage());
            }

            $lock = $state['lock'];

            if ($lock !== null && $lock->supported) {
                $dependencies = [...$dependencies, ...$lock->dependencies, ...$this->missingFromLockfile($declared, $lock->dependencies)];

                continue;
            }

            // Lockfile v1/tidak dikenal: rentang diberi alasan yang menyebut lockfile tidak didukung.
            if ($lock !== null) {
                $declared = array_map(
                    fn (ParsedDependency $dep) => $dep->unresolvedReason === UnresolvedReason::RangeWithoutLockfile
                        ? $dep->asUnresolved(UnresolvedReason::UnsupportedLockfile)
                        : $dep,
                    $declared,
                );
            }

            $dependencies = [...$dependencies, ...$declared];
        }

        return new CollectedDependencies(true, $this->finalize($dependencies), $failures);
    }

    private function pathsAreStorable(ManifestProject $project): bool
    {
        foreach ([$project->packageJsonPath, $project->lockfilePath] as $path) {
            if ($path !== null && (strlen($path) > self::MAX_PATH_LENGTH || ! mb_check_encoding($path, 'UTF-8'))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, array{project: ManifestProject, lock: ParsedLockfile|null, lockExists: bool}>  $states
     */
    private function isCoveredWorkspace(string $directory, array $states): bool
    {
        foreach ($states as $ancestorDirectory => $ancestor) {
            $ancestorDirectory = (string) $ancestorDirectory;
            $lock = $ancestor['lock'];

            if ($lock === null || ! $lock->supported || $ancestorDirectory === $directory) {
                continue;
            }

            if ($ancestorDirectory !== '' && ! str_starts_with($directory, $ancestorDirectory.'/')) {
                continue;
            }

            $relative = $ancestorDirectory === '' ? $directory : substr($directory, strlen($ancestorDirectory) + 1);

            if (in_array($relative, $lock->workspacePaths, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dependensi langsung (selain peer) di package.json yang tidak punya entri top-level
     * `node_modules/<nama>` di lockfile, sehingga tidak hilang diam-diam saat lockfile tidak sinkron.
     *
     * @param  list<ParsedDependency>  $declared
     * @param  list<ParsedDependency>  $fromLockfile
     * @return list<ParsedDependency>
     */
    private function missingFromLockfile(array $declared, array $fromLockfile): array
    {
        $topLevel = [];

        foreach ($fromLockfile as $dep) {
            if (str_starts_with((string) $dep->lockPath, 'node_modules/') && substr_count((string) $dep->lockPath, 'node_modules/') === 1) {
                $topLevel[$dep->name] = true;
            }
        }

        return array_values(array_filter(
            $declared,
            fn (ParsedDependency $dep) => $dep->scope !== DependencyScope::Peer && ! isset($topLevel[$dep->name]),
        ));
    }

    /**
     * Menyesuaikan nilai dengan panjang kolom, lalu menggabungkan duplikat. Kunci: (manifest, nama, versi,
     * lock_path); jika kunci sama, scope berprioritas tertinggi dipertahankan.
     *
     * @param  list<ParsedDependency>  $dependencies
     * @return list<ParsedDependency>
     */
    private function finalize(array $dependencies): array
    {
        $unique = [];

        foreach ($dependencies as $dep) {
            $dep = $this->fit($dep);
            $key = implode("\0", [$dep->manifestPath, $dep->name, $dep->version ?? '', $dep->lockPath ?? '']);

            if (! isset($unique[$key]) || self::SCOPE_PRIORITY[$dep->scope->value] < self::SCOPE_PRIORITY[$unique[$key]->scope->value]) {
                $unique[$key] = $dep;
            }
        }

        return array_values($unique);
    }

    private function fit(ParsedDependency $dep): ParsedDependency
    {
        if ($dep->declaredSpec !== null) {
            $dep = $dep->withDeclaredSpec(mb_strcut(mb_scrub($dep->declaredSpec, 'UTF-8'), 0, self::MAX_SPEC_LENGTH, 'UTF-8'));
        }

        // Versi tidak boleh dipotong (akan salah dikaitkan ke advisori): versi terlalu panjang dianggap tidak pasti.
        if ($dep->version !== null && strlen($dep->version) > self::MAX_VERSION_LENGTH) {
            return $dep->asUnresolved(UnresolvedReason::MissingVersion);
        }

        return $dep;
    }
}
