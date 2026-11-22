<?php

namespace App\Services\Sast;

use App\Enums\FindingCategory;
use App\Enums\FindingTool;
use App\Enums\Severity;
use App\Exceptions\SastToolException;
use JsonException;

/**
 * Mengubah output `eslint --format json` ke SastFinding (bentuk yang sama dengan hasil Semgrep).
 * Tidak menyentuh database; hanya `realpath($root)` untuk membuat path absolut dari ESLint menjadi relatif.
 */
class EslintParser
{
    private const MAX_MESSAGE_CHARS = 2000;

    /** Aturan inti ESLint yang bermakna keamanan; aturan plugin `security/*` selalu keamanan. */
    private const CORE_SECURITY_RULES = ['no-eval', 'no-implied-eval', 'no-new-func', 'no-script-url'];

    private const CWE = [
        'no-eval' => 'CWE-95',
        'no-implied-eval' => 'CWE-95',
        'no-new-func' => 'CWE-95',
        'no-script-url' => 'CWE-79',
        'security/detect-eval-with-expression' => 'CWE-95',
        'security/detect-child-process' => 'CWE-78',
        'security/detect-non-literal-fs-filename' => 'CWE-22',
        'security/detect-non-literal-require' => 'CWE-829',
        'security/detect-non-literal-regexp' => 'CWE-1333',
        'security/detect-unsafe-regex' => 'CWE-1333',
        'security/detect-possible-timing-attacks' => 'CWE-208',
        'security/detect-pseudoRandomBytes' => 'CWE-338',
        'security/detect-buffer-noassert' => 'CWE-119',
        'security/detect-new-buffer' => 'CWE-119',
        'security/detect-disable-mustache-escape' => 'CWE-79',
        'security/detect-no-csrf-before-method-override' => 'CWE-352',
        'security/detect-bidi-characters' => 'CWE-838',
    ];

    public function parse(string $json, string $root): ToolReport
    {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SastToolException('Output ESLint bukan JSON yang valid.');
        }

        if (! is_array($data) || ! array_is_list($data)) {
            throw new SastToolException('Struktur output ESLint tidak dikenali.');
        }

        $roots = $this->roots($root);
        $findings = [];
        $parseErrors = 0;
        $dropped = 0;

        foreach ($data as $file) {
            $path = is_array($file) && is_string($file['filePath'] ?? null) ? $this->relativePath($file['filePath'], $roots) : null;
            $messages = is_array($file) && is_array($file['messages'] ?? null) ? $file['messages'] : [];

            if ($path === null) {
                $dropped += max(1, count($messages));

                continue;
            }

            foreach ($messages as $message) {
                if (! is_array($message)) {
                    $dropped++;

                    continue;
                }

                if (($message['fatal'] ?? false) === true) {
                    $parseErrors++;

                    continue;
                }

                $finding = $this->finding($message, $path);

                $finding === null ? $dropped++ : $findings[] = $finding;
            }
        }

        return new ToolReport($findings, $parseErrors, $dropped);
    }

    /**
     * @param  array<mixed>  $message
     */
    private function finding(array $message, string $path): ?SastFinding
    {
        $ruleId = is_string($message['ruleId'] ?? null) ? SastText::clean($message['ruleId'], 255) : '';

        if ($ruleId === '') {
            return null;
        }

        $security = str_starts_with($ruleId, 'security/') || in_array($ruleId, self::CORE_SECURITY_RULES, true);
        $isError = ($message['severity'] ?? null) === 2;
        $text = is_string($message['message'] ?? null) ? SastText::clean($message['message'], self::MAX_MESSAGE_CHARS) : '';

        return new SastFinding(
            tool: FindingTool::Eslint,
            ruleId: $ruleId,
            category: $security ? FindingCategory::Security : FindingCategory::Quality,
            severity: match (true) {
                $security && $isError => Severity::High,
                $security, $isError => Severity::Medium,
                default => Severity::Low,
            },
            filePath: $path,
            lineStart: $this->position($message['line'] ?? null),
            lineEnd: $this->position($message['endLine'] ?? null),
            columnStart: $this->position($message['column'] ?? null),
            message: $text !== '' ? $text : $ruleId,
            cwe: self::CWE[$ruleId] ?? null,
        );
    }

    /**
     * @return list<string> awalan root (dipisah "/", tanpa slash akhir): path asli dan realpath
     */
    private function roots(string $root): array
    {
        $candidates = [$root, (string) realpath($root)];

        return array_values(array_unique(array_filter(
            array_map(fn (string $path) => rtrim(str_replace('\\', '/', $path), '/'), $candidates),
            fn (string $path) => $path !== '',
        )));
    }

    /**
     * @param  list<string>  $roots
     */
    private function relativePath(string $filePath, array $roots): ?string
    {
        $normalized = str_replace('\\', '/', $filePath);

        foreach ($roots as $root) {
            if (str_starts_with($normalized, $root.'/')) {
                return SastText::relativePath(substr($normalized, strlen($root) + 1));
            }
        }

        return SastText::relativePath($normalized);
    }

    private function position(mixed $value): ?int
    {
        return is_int($value) && $value >= 1 ? $value : null;
    }
}
