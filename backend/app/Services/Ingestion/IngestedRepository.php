<?php

namespace App\Services\Ingestion;

final readonly class IngestedRepository
{
    public function __construct(
        public string $rootPath,
        public int $sizeBytes,
        public ?string $commitSha,
    ) {}
}
