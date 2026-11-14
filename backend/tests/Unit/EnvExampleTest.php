<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class EnvExampleTest extends TestCase
{
    /**
     * Key yang wajib tersedia di template environment.
     */
    private const REQUIRED_KEYS = [
        'APP_NAME',
        'APP_ENV',
        'APP_KEY',
        'APP_DEBUG',
        'APP_URL',
        'APP_TIMEZONE',
        'FRONTEND_URL',
        'DB_CONNECTION',
        'SESSION_DRIVER',
        'CACHE_STORE',
        'QUEUE_CONNECTION',
        'REDIS_HOST',
        'REDIS_DB',
        'REDIS_CACHE_DB',
        'REDIS_TIMEOUT',
    ];

    /**
     * Key yang berisi secret dan harus kosong di template.
     */
    private const SECRET_KEYS = [
        'APP_KEY',
        'DB_PASSWORD',
        'AWS_ACCESS_KEY_ID',
        'AWS_SECRET_ACCESS_KEY',
    ];

    public function test_env_example_contains_required_keys(): void
    {
        $values = $this->parseEnvExample();

        foreach (self::REQUIRED_KEYS as $key) {
            $this->assertArrayHasKey($key, $values, "Key {$key} tidak ada di .env.example.");
        }
    }

    public function test_env_example_does_not_contain_secrets(): void
    {
        $values = $this->parseEnvExample();

        foreach (self::SECRET_KEYS as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $this->assertContains($values[$key], ['', 'null'], "Key {$key} di .env.example harus kosong.");
        }
    }

    public function test_env_example_uses_utc_timezone_and_project_name(): void
    {
        $values = $this->parseEnvExample();

        $this->assertSame('UTC', $values['APP_TIMEZONE']);
        $this->assertSame('AI Code Security Audit Platform', $values['APP_NAME']);
    }

    public function test_env_example_uses_redis_for_cache_session_and_queue(): void
    {
        $values = $this->parseEnvExample();

        $this->assertSame('redis', $values['SESSION_DRIVER']);
        $this->assertSame('redis', $values['CACHE_STORE']);
        $this->assertSame('redis', $values['QUEUE_CONNECTION']);
    }

    /**
     * Membaca .env.example, termasuk baris yang dikomentari (# KEY=value),
     * agar secret yang hanya dikomentari tetap terdeteksi.
     *
     * @return array<string, string>
     */
    private function parseEnvExample(): array
    {
        $path = dirname(__DIR__, 2).'/.env.example';

        $this->assertFileExists($path);

        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (! preg_match('/^\s*#?\s*([A-Z0-9_]+)=(.*)$/', $line, $matches)) {
                continue;
            }

            $values[$matches[1]] = trim($matches[2], " \t\"'");
        }

        return $values;
    }
}
