<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Batas Ukuran Repositori
    |--------------------------------------------------------------------------
    |
    | Berlaku untuk ZIP terunggah, total hasil ekstraksi, dan hasil git clone
    | (PRD NFR-06: maksimal 50 MB).
    |
    */

    'max_repository_bytes' => (int) env('AUDIT_MAX_REPOSITORY_BYTES', 50 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Arsip ZIP
    |--------------------------------------------------------------------------
    |
    | Pengaman tambahan terhadap zip bomb: jumlah entri maksimum dan rasio
    | ukuran hasil ekstraksi terhadap ukuran arsip.
    |
    */

    'zip' => [
        'max_entries' => (int) env('AUDIT_ZIP_MAX_ENTRIES', 20000),
        'max_compression_ratio' => (int) env('AUDIT_ZIP_MAX_COMPRESSION_RATIO', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Repositori Git Publik
    |--------------------------------------------------------------------------
    |
    | Hanya host pada allowlist yang boleh di-clone (D-08). Daftar dipisahkan
    | koma, mis. "github.com,gitlab.com,bitbucket.org".
    |
    */

    'git' => [
        'binary' => env('AUDIT_GIT_BINARY', 'git'),
        'allowed_hosts' => array_values(array_filter(array_map(
            fn (string $host) => strtolower(trim($host)),
            explode(',', (string) env('AUDIT_GIT_ALLOWED_HOSTS', 'github.com,gitlab.com,bitbucket.org')),
        ))),
        'clone_timeout_seconds' => (int) env('AUDIT_GIT_CLONE_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Pemrosesan Audit
    |--------------------------------------------------------------------------
    |
    | Batas waktu satu ProcessAuditJob (ingestion + SAST + SCA). Harus lebih kecil
    | dari queue.connections.redis.retry_after (REDIS_QUEUE_RETRY_AFTER).
    |
    */

    'job' => [
        'timeout_seconds' => (int) env('AUDIT_JOB_TIMEOUT', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lokasi Penyimpanan Sementara
    |--------------------------------------------------------------------------
    |
    | Berkas unggahan dan direktori kerja per audit. Keduanya dihapus setelah
    | audit selesai atau gagal. Direktori kerja BUKAN sandbox/isolasi penuh.
    |
    */

    'storage' => [
        'uploads_path' => env('AUDIT_UPLOADS_PATH', storage_path('app/private/uploads')),
        'workspaces_path' => env('AUDIT_WORKSPACES_PATH', storage_path('app/private/workspaces')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Direktori yang Diabaikan
    |--------------------------------------------------------------------------
    |
    | Tidak diekstrak dan tidak ditelusuri, di kedalaman mana pun.
    |
    */

    'ignored_directories' => ['node_modules', 'vendor', '.git'],

];
