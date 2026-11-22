<?php

namespace App\Services\Sast;

use App\Enums\CodeLanguage;
use App\Exceptions\SastToolException;
use JsonException;

/**
 * Mengubah keluaran tooling/metrics.mjs menjadi FileMetrics. Tidak menyentuh filesystem maupun database.
 */
class MetricsParser
{
    private const MAX_NAME_CHARS = 255;

    private const TYPESCRIPT_EXTENSIONS = ['ts', 'tsx', 'mts', 'cts'];

    public function parse(string $json): MetricsReport
    {
        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SastToolException('Output metrik bukan JSON yang valid.');
        }

        if (! is_array($data) || ! is_array($data['files'] ?? null) || ! array_is_list($data['files'])) {
            throw new SastToolException('Struktur output metrik tidak dikenali.');
        }

        $files = [];
        $dropped = 0;

        foreach ($data['files'] as $entry) {
            $file = is_array($entry) ? $this->file($entry) : null;

            if ($file === null) {
                $dropped++;
            } else {
                $files[] = $file;
            }
        }

        $errors = $data['errors'] ?? [];

        return new MetricsReport($files, is_array($errors) ? count($errors) : 0, $dropped);
    }

    /**
     * @param  array<mixed>  $entry
     */
    private function file(array $entry): ?FileMetrics
    {
        $path = is_string($entry['path'] ?? null) ? SastText::relativePath($entry['path']) : null;
        $values = $this->values($entry);

        if ($path === null || $values === null || ! is_array($entry['functions'] ?? null) || ! array_is_list($entry['functions'])) {
            return null;
        }

        $functions = [];

        foreach ($entry['functions'] as $function) {
            $functionValues = is_array($function) ? $this->values($function) : null;

            if ($functionValues === null) {
                continue;
            }

            $name = is_string($function['name'] ?? null) ? SastText::clean($function['name'], self::MAX_NAME_CHARS) : '';
            $line = $function['line'] ?? null;

            $functions[] = new FunctionMetrics($name !== '' ? $name : '<anonymous>', is_int($line) && $line >= 1 ? $line : 1, $functionValues);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return new FileMetrics(
            $path,
            in_array($extension, self::TYPESCRIPT_EXTENSIONS, true) ? CodeLanguage::Typescript : CodeLanguage::Javascript,
            $values,
            $functions,
        );
    }

    /**
     * @param  array<mixed>  $data
     */
    private function values(array $data): ?MetricValues
    {
        $numbers = [];

        foreach (['loc', 'sloc', 'cc', 'n1', 'n2', 'N1', 'N2'] as $key) {
            $value = $data[$key] ?? null;

            if (! is_int($value) || $value < 0) {
                return null;
            }

            $numbers[] = $value;
        }

        return new MetricValues(...$numbers);
    }
}
