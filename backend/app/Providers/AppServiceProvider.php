<?php

namespace App\Providers;

use App\Enums\AuditStage;
use App\Services\Audit\Stages\AuditStageRunners;
use App\Services\Audit\Stages\UnavailableStageRunner;
use App\Services\Ingestion\DnsHostResolver;
use App\Services\Ingestion\GitUrlValidator;
use App\Services\Ingestion\HostResolver;
use App\Services\Ingestion\RepositoryFileWalker;
use Illuminate\Support\ServiceProvider;

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

        // Engine SAST (TAHAP 6) & SCA (TAHAP 7) belum ada: tahap dicatat gagal secara jujur.
        $this->app->singleton(AuditStageRunners::class, fn () => new AuditStageRunners([
            new UnavailableStageRunner(AuditStage::Sast),
            new UnavailableStageRunner(AuditStage::Sca),
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
