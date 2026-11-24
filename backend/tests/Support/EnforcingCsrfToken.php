<?php

namespace Tests\Support;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * ValidateCsrfToken bawaan melewati pemeriksaan saat unit test; subclass ini memaksanya aktif
 * agar kewajiban X-XSRF-TOKEN pada request SPA dapat diuji.
 */
class EnforcingCsrfToken extends ValidateCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}
