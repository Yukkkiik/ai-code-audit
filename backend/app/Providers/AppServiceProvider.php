<?php

namespace App\Providers;

use App\Enums\AuditStage;
use App\Services\Audit\Stages\AuditStageRunners;
use App\Services\Audit\Stages\UnavailableStageRunner;
use App\Services\Ingestion\DnsHostResolver;
use App\Services\Ingestion\GitUrlValidator;
use App\Services\Ingestion\HostResolver;
use App\Services\Ingestion\RepositoryFileWalker;
use App\Services\Sast\EslintRunner;
use App\Services\Sast\FindingNormalizer;
use App\Services\Sast\JsTsFileSelector;
use App\Services\Sast\MetricsRunner;
use App\Services\Sast\MetricsStore;
use App\Services\Sast\SemgrepRunner;
use App\Services\Sast\SnippetReader;
use App\Services\Sca\OsvClient;
use App\Services\Sca\PackageJsonParser;
use App\Services\Sca\PackageLockParser;
use App\Services\Sca\ScaStageRunner;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HostResolver::class, DnsHostResolver::class);

        $this->app->when(GitUrlValidator::class)
            ->needs('$allowedHosts')
            ->giveConfig('audit.git.allowed_hosts');

        $this->app->when(RepositoryFileWalker::class)
            ->needs('$ignoredDirectories')
            ->giveConfig('audit.ignored_directories');

        $this->app->bind(JsTsFileSelector::class, fn () => new JsTsFileSelector(
            config('audit.sast.extensions'),
            array_values(array_unique([...config('audit.ignored_directories'), ...config('audit.sast.ignored_directories')])),
            config('audit.sast.ignored_suffixes'),
            config('audit.sast.max_files'),
            config('audit.sast.max_file_bytes'),
        ));

        $this->app->bind(SnippetReader::class, fn () => new SnippetReader(
            config('audit.sast.snippet_max_lines'),
            config('audit.sast.snippet_max_line_chars'),
            config('audit.sast.max_file_bytes'),
        ));

        $this->app->bind(FindingNormalizer::class, fn ($app) => new FindingNormalizer(
            $app->make(SnippetReader::class),
            config('audit.sast.max_findings'),
        ));

        $this->app->when(MetricsRunner::class)
            ->needs('$config')
            ->giveConfig('audit.sast.metrics');

        $this->app->when(MetricsStore::class)
            ->needs('$maxFunctionRows')
            ->giveConfig('audit.sast.metrics.max_function_rows');

        $this->app->when(EslintRunner::class)
            ->needs('$config')
            ->giveConfig('audit.sast.eslint');

        $this->app->when(SemgrepRunner::class)
            ->needs('$config')
            ->giveConfig('audit.sast.semgrep');

        $this->app->when(PackageJsonParser::class)
            ->needs('$maxBytes')
            ->giveConfig('audit.sca.max_manifest_bytes');

        $this->app->when(PackageLockParser::class)
            ->needs('$maxBytes')
            ->giveConfig('audit.sca.max_lockfile_bytes');

        $this->app->when(OsvClient::class)
            ->needs('$config')
            ->give(function () {
                $config = config('audit.sca.osv');

                // Hanya https; http diizinkan di environment testing agar dapat diarahkan ke server tiruan.
                if (! str_starts_with((string) $config['base_url'], 'https://') && ! $this->app->environment('testing')) {
                    throw new InvalidArgumentException('URL dasar OSV harus memakai https.');
                }

                return $config;
            });

        // Engine SAST (TAHAP 6) belum ada: tahap dicatat gagal secara jujur. SCA memakai engine asli.
        $this->app->singleton(AuditStageRunners::class, fn ($app) => new AuditStageRunners([
            new UnavailableStageRunner(AuditStage::Sast),
            $app->make(ScaStageRunner::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
