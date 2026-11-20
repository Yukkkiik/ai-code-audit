<?php

namespace Tests\Unit\Sca;

use App\Services\Sca\ManifestFailure;
use PHPUnit\Framework\TestCase;

class ManifestFailureTest extends TestCase
{
    public function test_control_characters_and_newlines_are_removed_from_path_and_message(): void
    {
        $failure = new ManifestFailure("app/\n\e[31mpackage\0.json\r", 'Berkas rusak.');

        $this->assertSame('app/[31mpackage.json', $failure->path);
        $this->assertSame('app/[31mpackage.json: Berkas rusak.', $failure->message);
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', $failure->message);
    }

    public function test_bidi_override_characters_are_removed(): void
    {
        $failure = new ManifestFailure("app/\u{202E}nosj.tpircs/package.json", 'x');

        $this->assertStringNotContainsString("\u{202E}", $failure->message);
    }

    public function test_message_is_limited_to_300_characters(): void
    {
        $failure = new ManifestFailure(str_repeat('é', 500).'/package.json', str_repeat('a', 500));

        $this->assertSame(300, mb_strlen($failure->message));
        $this->assertLessThanOrEqual(300, mb_strlen($failure->path));
    }

    public function test_invalid_utf8_is_scrubbed(): void
    {
        $failure = new ManifestFailure("app/\xFF\xFE/package.json", 'Berkas rusak.');

        $this->assertTrue(mb_check_encoding($failure->message, 'UTF-8'));
        $this->assertTrue(mb_check_encoding($failure->path, 'UTF-8'));
    }

    public function test_short_message_is_kept_whole(): void
    {
        $this->assertSame('package.json: Berkas rusak.', (new ManifestFailure('package.json', 'Berkas rusak.'))->message);
    }
}
