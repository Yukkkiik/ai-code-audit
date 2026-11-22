<?php

namespace App\Services\Sast;

use App\Enums\CodeLanguage;

final readonly class FileMetrics
{
    /**
     * @param  list<FunctionMetrics>  $functions
     */
    public function __construct(
        public string $path,
        public CodeLanguage $language,
        public MetricValues $values,
        public array $functions,
    ) {}
}
