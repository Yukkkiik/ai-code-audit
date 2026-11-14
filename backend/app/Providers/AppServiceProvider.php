<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
