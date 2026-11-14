<?php

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Membuat skrip pengganti `git` per test (tanpa jaringan) dan mengarahkan
 * config audit.git.binary ke skrip tersebut. Argumen dan environment yang
 * diterima skrip dicatat agar bisa diperiksa oleh test.
 */
trait FakeGitBinary
{
    protected string $fakeGitLog;

    /**
     * @param  'success'|'large'|'not_found'|'network_error'|'slow'  $mode
     */
    protected function useFakeGit(string $mode): void
    {
        $dir = sys_get_temp_dir().'/fake-git-'.Str::random(10);
        File::ensureDirectoryExists($dir);
        $this->fakeGitLog = $dir.'/calls.log';
        $script = $dir.'/git';

        $clone = match ($mode) {
            'not_found' => 'echo "remote: Repository not found." >&2; echo "fatal: repository not found" >&2; exit 128',
            'network_error' => 'echo "fatal: unable to access: Could not resolve host" >&2; exit 128',
            'slow' => 'sleep 5; exit 0',
            'large' => 'mkdir -p "$last/.git"; printf "x" > "$last/index.js"; dd if=/dev/zero of="$last/.git/pack" bs=1024 count=64 2>/dev/null; exit 0',
            default => 'mkdir -p "$last/.git" "$last/src"; printf "console.log(1);\n" > "$last/src/index.js"; printf "{}\n" > "$last/package.json"; exit 0',
        };

        File::put($script, <<<SH
            #!/bin/sh
            log="{$this->fakeGitLog}"
            for arg in "\$@"; do last="\$arg"; done
            { echo "=== CALL"; printf 'ARG %s\n' "\$@"; env | sed 's/^/ENV /'; } >> "\$log"
            case " \$* " in
              *" rev-parse "*) echo "0123456789abcdef0123456789abcdef01234567"; exit 0 ;;
            esac
            {$clone}
            SH);
        chmod($script, 0755);

        config(['audit.git.binary' => $script]);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($dir));
    }

    protected function fakeGitCalls(): string
    {
        return is_file($this->fakeGitLog) ? (string) file_get_contents($this->fakeGitLog) : '';
    }
}
