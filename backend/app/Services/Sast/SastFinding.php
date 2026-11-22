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

    /**
     * Kunci deduplikasi (DATABASE.md): SHA-256(tool, rule_id, file_path, line_start, message).
     */
    public function fingerprint(): string
    {
        return hash('sha256', implode("\0", [$this->tool->value, $this->ruleId, $this->filePath, (string) $this->lineStart, $this->message]));
    }

    public function withMessage(string $message): self
    {
        return new self(
            $this->tool, $this->ruleId, $this->category, $this->severity, $this->filePath,
            $this->lineStart, $this->lineEnd, $this->columnStart, $message, $this->codeSnippet, $this->cwe,
        );
    }

    public function withSnippet(?string $snippet): self
    {
        return new self(
            $this->tool, $this->ruleId, $this->category, $this->severity, $this->filePath,
            $this->lineStart, $this->lineEnd, $this->columnStart, $this->message, $snippet, $this->cwe,
        );
    }
}
