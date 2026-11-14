<?php

namespace App\Services\Ingestion;

final readonly class ValidatedGitUrl
{
    public function __construct(
        public string $url,
        public string $repositoryName,
    ) {}
}
