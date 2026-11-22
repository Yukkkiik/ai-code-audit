<?php

namespace App\Services\Sast;

use App\Enums\FindingTool;
use App\Enums\Severity;

/**
 * Menggabungkan temuan semua tool menjadi satu daftar tanpa duplikat, terurut deterministik, dengan cuplikan kode.
 *
 * Aturan deduplikasi:
 *  1. Fingerprint sama (tool, rule, file, baris awal, pesan) → satu saja (yang pertama).
 *  2. Temuan ESLint dibuang bila Semgrep sudah melaporkan CWE yang sama pada file dan baris awal yang sama
 *     (isu yang sama dari dua tool). Aturan ESLint berbeda pada lokasi yang sama tanpa padanan Semgrep tetap dipertahankan,
 *     begitu pula dua aturan dari tool yang sama.
 */
class FindingNormalizer
{
    private const SEVERITY_ORDER = [
        'critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4,
    ];

    public function __construct(private SnippetReader $snippets, private int $maxFindings) {}

    /**
     * @param  list<SastFinding>  $findings  gabungan hasil semua tool
     */
    public function normalize(array $findings, string $root): NormalizedFindings
    {
        $unique = [];

        foreach ($findings as $finding) {
            $finding = $finding->withMessage($this->withoutRoot($finding->message, $root));
            $unique[$finding->fingerprint()] ??= $finding;
        }

        $semgrepLocations = [];

        foreach ($unique as $finding) {
            if ($finding->tool === FindingTool::Semgrep && $finding->cwe !== null && $finding->lineStart !== null) {
                $semgrepLocations[$this->locationKey($finding)] = true;
            }
        }

        $kept = array_values(array_filter($unique, fn (SastFinding $finding) => ! (
            $finding->tool === FindingTool::Eslint
            && $finding->cwe !== null
            && $finding->lineStart !== null
            && isset($semgrepLocations[$this->locationKey($finding)])
        )));

        $duplicates = count($findings) - count($kept);

        usort($kept, fn (SastFinding $a, SastFinding $b) => [self::rank($a->severity), $a->filePath, $a->lineStart ?? 0, $a->ruleId, $a->tool->value, $a->message]
            <=> [self::rank($b->severity), $b->filePath, $b->lineStart ?? 0, $b->ruleId, $b->tool->value, $b->message]);

        $truncated = max(0, count($kept) - $this->maxFindings);
        $kept = array_slice($kept, 0, $this->maxFindings);

        // Cuplikan dibaca per file (urutan file) agar setiap file dibaca sekali.
        $prepared = [];

        foreach ($this->groupedByFile($kept) as $path => $indexes) {
            foreach ($indexes as $index) {
                $finding = $kept[$index];
                $snippet = $finding->codeSnippet ?? $this->snippets->read($root, $path, $finding->lineStart, $finding->lineEnd);
                $prepared[$index] = $finding->withSnippet($snippet);
            }
        }

        ksort($prepared);

        return new NormalizedFindings(array_values($prepared), $duplicates, $truncated);
    }

    private function locationKey(SastFinding $finding): string
    {
        return $finding->filePath."\0".$finding->lineStart."\0".$finding->cwe;
    }

    private static function rank(Severity $severity): int
    {
        return self::SEVERITY_ORDER[$severity->value];
    }

    /**
     * @param  list<SastFinding>  $findings
     * @return array<string, list<int>>
     */
    private function groupedByFile(array $findings): array
    {
        $groups = [];

        foreach ($findings as $index => $finding) {
            $groups[$finding->filePath][] = $index;
        }

        return $groups;
    }

    /**
     * Pesan tool kadang menyebut path absolut workspace; path server tidak boleh tersimpan.
     */
    private function withoutRoot(string $message, string $root): string
    {
        $variants = array_unique(array_filter([$root, str_replace('\\', '/', $root), (string) realpath($root)]));

        return str_replace($variants, '.', $message);
    }
}
