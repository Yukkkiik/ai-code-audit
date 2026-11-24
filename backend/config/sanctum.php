<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

/*
| Sanctum dipakai hanya untuk mode SPA (D-06): session cookie + CSRF.
| Token API pribadi tidak dipakai dan tabel `personal_access_tokens` tidak dibuat.
*/
return [

    /*
    | Request dari host berikut diperlakukan sebagai SPA first-party: session cookie
    | dan header X-XSRF-TOKEN wajib. Frontend dan backend harus berada pada site yang sama.
    */
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost:3000,127.0.0.1:3000',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    'guard' => ['web'],

    'expiration' => null,

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
