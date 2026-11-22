<?php

namespace App\Services\Sast;

use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\Severity;
use App\Exceptions\SastToolException;
use JsonException;

/**
 * Mengubah output `semgrep --json` ke SastFinding. Tidak menyentuh filesystem maupun database.
 */
class SemgrepParser
{
    private const MAX_MESSAGE_CHARS = 2000;

    private const MAX_SNIPPET_CHARS = 2000;

    public function parse(string $json): ToolReport
    {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SastToolException('Output Semgrep bukan JSON yang valid.');
        }

        if (! is_array($data) || ! isset($data['results']) || ! is_array($data['results']) || ! array_is_list($data['results'])) {
            throw new SastToolException('Struktur output Semgrep tidak dikenali.');
        }

        $findings = [];
        $dropped = 0;

        foreach ($data['results'] as $result) {
            $finding = is_array($result) ? $this->finding($result) : null;

            if ($finding === null) {
                $dropped++;
            } else {
                $findings[] = $finding;
            }
        }

        $errors = $data['errors'] ?? [];

        return new ToolReport($findings, is_array($errors) ? count($errors) : 0, $dropped);
    }

    /**
     * @param  array<mixed>  $result
     */
    private function finding(array $result): ?SastFinding
    {
        $extra = is_array($result['extra'] ?? null) ? $result['extra'] : [];
        $metadata = is_array($extra['metadata'] ?? null) ? $extra['metadata'] : [];

        $path = is_string($result['path'] ?? null) ? SastText::relativePath($result['path']) : null;
        $ruleId = is_string($result['check_id'] ?? null) ? $this->ruleId($result['check_id']) : '';

        if ($path === null || $ruleId === '') {
            return null;
        }

        $start = is_array($result['start'] ?? null) ? $result['start'] : [];
        $end = is_array($result['end'] ?? null) ? $result['end'] : [];
        $message = is_string($extra['message'] ?? null) ? SastText::clean($extra['message'], self::MAX_MESSAGE_CHARS) : '';

        return new SastFinding(
            tool: FindingTool::Semgrep,
            ruleId: $ruleId,
            category: $this->category($metadata['category'] ?? null),
            severity: $this->severity($extra['severity'] ?? null),
            filePath: $path,
            lineStart: $this->position($start['line'] ?? null),
            lineEnd: $this->position($end['line'] ?? null),
            columnStart: $this->position($start['col'] ?? null),
            message: $message !== '' ? $message : $ruleId,
            codeSnippet: $this->snippet($extra['lines'] ?? null),
            cwe: $this->cwe($metadata['cwe'] ?? null),
        );
    }

    /**
     * Semgrep menambahkan awalan dari path file rule (mis. "tooling.semgrep.js-ts-security.eval"): ambil segmen
     * terakhir agar struktur direktori server tidak ikut tersimpan.
     */
    private function ruleId(string $checkId): string
    {
        $position = strrpos($checkId, '.');

        return SastText::clean($position === false ? $checkId : substr($checkId, $position + 1), 255);
    }

    private function severity(mixed $value): Severity
    {
        return match (is_string($value) ? strtoupper($value) : '') {
            'ERROR' => Severity::High,
            'WARNING' => Severity::Medium,
            default => Severity::Low,
        };
    }

    private function category(mixed $value): FindingCategory
    {
        return match (is_string($value) ? strtolower($value) : 'security') {
            'best-practice', 'best_practice' => FindingCategory::BestPractice,
            'correctness', 'performance', 'maintainability', 'portability' => FindingCategory::Quality,
            default => FindingCategory::Security,
        };
    }

    private function position(mixed $value): ?int
    {
        return is_int($value) && $value >= 1 ? $value : null;
    }

    /**
     * Semgrep tanpa login mengisi `lines` dengan "requires login": itu bukan cuplikan kode.
     */
    private function snippet(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '' || strtolower(trim($value)) === 'requires login') {
            return null;
        }

        return SastText::clean($value, self::MAX_SNIPPET_CHARS);
    }

    private function cwe(mixed $value): ?string
    {
        foreach (is_array($value) ? $value : [$value] as $candidate) {
            if (is_string($candidate) && preg_match('/CWE-\d{1,5}/i', $candidate, $match) === 1) {
                return strtoupper($match[0]);
            }
        }

        return null;
    }
}
