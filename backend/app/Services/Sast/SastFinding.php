<?php

namespace App\Services\Sast;

use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\Severity;

/**
 * Temuan SAST dalam bentuk seragam untuk semua tool (Semgrep, ESLint). Path selalu relatif terhadap root repositori.
 */
final readonly class SastFinding
{
    public function __construct(
        public FindingTool $tool,
        public string $ruleId,
        public FindingCategory $category,
        public Severity $severity,
        public string $filePath,
        public ?int $lineStart,
        public ?int $lineEnd,
        public ?int $columnStart,
        public string $message,
        public ?string $codeSnippet = null,
        public ?string $cwe = null,
    ) {}
}
