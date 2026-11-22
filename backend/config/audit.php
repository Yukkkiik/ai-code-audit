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
    | SCA
    |--------------------------------------------------------------------------
    |
    | Batas ukuran satu berkas yang dibaca parser. Lockfile didekode seluruhnya ke memori
    | (pemakaian memori beberapa kali lipat ukuran berkas): sesuaikan dengan memory_limit worker.
    |
    */

    'sca' => [
        'max_manifest_bytes' => (int) env('AUDIT_SCA_MAX_MANIFEST_BYTES', 5 * 1024 * 1024),
        'max_lockfile_bytes' => (int) env('AUDIT_SCA_MAX_LOCKFILE_BYTES', 5 * 1024 * 1024),

        // Cadangan waktu job (detik) yang tidak boleh dipakai pemanggilan OSV: untuk penyimpanan, finalisasi, dan cleanup.
        'job_margin_seconds' => (int) env('AUDIT_SCA_JOB_MARGIN_SECONDS', 30),

        // OSV.dev querybatch. base_url wajib https (kecuali environment testing); batch_size maksimum 1000 (batas API).
        // max_total_seconds = anggaran waktu seluruh pemanggilan OSV dalam satu audit (harus jauh di bawah timeout job).
        'osv' => [
            'base_url' => env('AUDIT_OSV_BASE_URL', 'https://api.osv.dev'),
            'timeout_seconds' => (int) env('AUDIT_OSV_TIMEOUT', 15),
            'connect_timeout_seconds' => (int) env('AUDIT_OSV_CONNECT_TIMEOUT', 5),
            'max_attempts' => (int) env('AUDIT_OSV_MAX_ATTEMPTS', 3),
            'backoff_ms' => (int) env('AUDIT_OSV_BACKOFF_MS', 500),
            'batch_size' => (int) env('AUDIT_OSV_BATCH_SIZE', 1000),
            'max_pages' => (int) env('AUDIT_OSV_MAX_PAGES', 20),
            'max_total_seconds' => (int) env('AUDIT_OSV_MAX_TOTAL_SECONDS', 180),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SAST (JavaScript/TypeScript)
    |--------------------------------------------------------------------------
    |
    | Seleksi file yang dianalisis. Direktori di `ignored_directories` ditambahkan ke
    | `audit.ignored_directories`; berkas ber-akhiran `ignored_suffixes` (minified/deklarasi)
    | dilewati. File kosong, di atas `max_file_bytes`, atau melewati `max_files` tidak dianalisis.
    |
    */

    'sast' => [
        'extensions' => ['js', 'jsx', 'mjs', 'cjs', 'ts', 'tsx', 'mts', 'cts'],
        'ignored_directories' => ['dist', 'build', 'coverage'],
        'ignored_suffixes' => ['.min.js', '.min.mjs', '.min.cjs', '.d.ts', '.d.mts', '.d.cts'],
        'max_files' => (int) env('AUDIT_SAST_MAX_FILES', 2000),
        'max_file_bytes' => (int) env('AUDIT_SAST_MAX_FILE_BYTES', 1024 * 1024),

        // Penyimpanan temuan: batas jumlah per audit (severity terendah dibuang lebih dulu) dan cuplikan kode.
        'max_findings' => (int) env('AUDIT_SAST_MAX_FINDINGS', 5000),
        'snippet_max_lines' => (int) env('AUDIT_SAST_SNIPPET_MAX_LINES', 5),
        'snippet_max_line_chars' => (int) env('AUDIT_SAST_SNIPPET_MAX_LINE_CHARS', 300),

        // Semgrep dengan rule lokal milik platform (D-22); tidak pernah memakai registry atau config repositori target.
        // timeout_seconds = anggaran total seluruh batch; per_file_timeout_seconds = batas Semgrep per file.
        'semgrep' => [
            'binary' => env('AUDIT_SEMGREP_BINARY', 'semgrep'),
            'rules_path' => base_path('tooling/semgrep'),
            'timeout_seconds' => (int) env('AUDIT_SEMGREP_TIMEOUT', 180),
            'per_file_timeout_seconds' => (int) env('AUDIT_SEMGREP_FILE_TIMEOUT', 10),
            'max_output_bytes' => (int) env('AUDIT_SEMGREP_MAX_OUTPUT_BYTES', 20 * 1024 * 1024),
            'max_args_bytes' => (int) env('AUDIT_SEMGREP_MAX_ARGS_BYTES', 12000),
        ],

        // Metrik kode (D-09): tooling/metrics.mjs memakai parser `typescript` milik platform.
        // max_function_rows = batas baris metrik per fungsi per audit (kompleksitas tertinggi lebih dulu); baris per file selalu disimpan.
        'metrics' => [
            'node_binary' => env('AUDIT_NODE_BINARY', 'node'),
            'script_path' => base_path('tooling/metrics.mjs'),
            'typescript_path' => base_path('tooling/node_modules/typescript/lib/typescript.js'),
            'timeout_seconds' => (int) env('AUDIT_METRICS_TIMEOUT', 120),
            'max_output_bytes' => (int) env('AUDIT_METRICS_MAX_OUTPUT_BYTES', 20 * 1024 * 1024),
            'max_args_bytes' => (int) env('AUDIT_METRICS_MAX_ARGS_BYTES', 12000),
            'max_file_bytes' => (int) env('AUDIT_SAST_MAX_FILE_BYTES', 1024 * 1024),
            'max_function_rows' => (int) env('AUDIT_METRICS_MAX_FUNCTION_ROWS', 10000),
        ],

        // ESLint milik platform (D-20, D-21): dipasang di backend/tooling lewat `npm ci --ignore-scripts`.
        'eslint' => [
            'node_binary' => env('AUDIT_NODE_BINARY', 'node'),
            'script_path' => base_path('tooling/node_modules/eslint/bin/eslint.js'),
            'config_path' => base_path('tooling/eslint.config.mjs'),
            'timeout_seconds' => (int) env('AUDIT_ESLINT_TIMEOUT', 180),
            'max_output_bytes' => (int) env('AUDIT_ESLINT_MAX_OUTPUT_BYTES', 20 * 1024 * 1024),
            'max_args_bytes' => (int) env('AUDIT_ESLINT_MAX_ARGS_BYTES', 12000),
        ],
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
