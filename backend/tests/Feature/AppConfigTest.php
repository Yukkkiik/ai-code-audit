<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppConfigTest extends TestCase
{
    /**
     * @var array<string, string|false>
     */
    private array $originalEnv = [];

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            $this->setEnv($key, $value);
        }

        parent::tearDown();
    }

    public function test_timezone_is_read_from_environment(): void
    {
        $this->overrideEnv('APP_TIMEZONE', 'Asia/Jakarta');

        $config = require config_path('app.php');

        $this->assertSame('Asia/Jakarta', $config['timezone']);
    }

    public function test_timezone_defaults_to_utc(): void
    {
        $this->overrideEnv('APP_TIMEZONE', false);

        $config = require config_path('app.php');

        $this->assertSame('UTC', $config['timezone']);
    }

    public function test_frontend_url_is_read_from_environment(): void
    {
        $this->overrideEnv('FRONTEND_URL', 'https://audit.example.test');

        $config = require config_path('app.php');

        $this->assertSame('https://audit.example.test', $config['frontend_url']);
    }

    public function test_frontend_url_defaults_to_local_next_dev_server(): void
    {
        $this->overrideEnv('FRONTEND_URL', false);

        $config = require config_path('app.php');

        $this->assertSame('http://localhost:3000', $config['frontend_url']);
    }

    private function overrideEnv(string $key, string|false $value): void
    {
        if (! array_key_exists($key, $this->originalEnv)) {
            $this->originalEnv[$key] = getenv($key);
        }

        $this->setEnv($key, $value);
    }

    private function setEnv(string $key, string|false $value): void
    {
        if ($value === false) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
