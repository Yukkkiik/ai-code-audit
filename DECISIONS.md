# DECISIONS — Keputusan Teknis

Status: **Disetujui** · **Usulan** (diusulkan Claude, menunggu persetujuan) · **Terbuka** (belum ada usulan final, perlu keputusan tim).
Keputusan berstatus Usulan/Terbuka **tidak boleh** diimplementasikan sampai disetujui.

## A. Sudah Disetujui (dari master prompt)

| ID | Keputusan |
|---|---|
| A-01 | Monorepo tunggal; `.git` di root `ai-code-security-audit-platform/`. |
| A-02 | Laravel di `backend/`, Next.js di `frontend/`, tanpa folder bertingkat. |
| A-03 | Stack: Next.js, Laravel REST API, MySQL, Redis (queue, cache, rate limit), Laravel Queue. |
| A-04 | **(Direvisi 2026-11-14)** SAST hanya JavaScript/TypeScript: Semgrep + ESLint. Metrik hanya JS/TS. Bandit dikeluarkan dari stack karena hanya untuk Python. |
| A-05 | **(Direvisi 2026-11-14)** SCA hanya paket Node.js/npm: `package.json`, `package-lock.json` v2/v3; OSV.dev REST API. `yarn.lock`/`pnpm-lock.yaml` tidak dibaca. |
| A-06 | Batas repositori 50 MB; Git publik saja. |
| A-07 | Skor kesehatan tidak memasukkan hasil SCA. |
| A-08 | Output AI advisory, structured output, tervalidasi; provider dapat dikonfigurasi. |
| A-09 | Pengembangan satu fitur per sesi, plan-first. |
| A-10 | **(D-01, disetujui 2026-11-14)** Backend: skeleton `laravel/laravel` ^13.11 → terpasang **Laravel Framework 13.42.0**, PHP 8.4.26. Versi Next.js dicatat saat TAHAP 10.1. |
| A-12 | **(Selesai, 2026-11-14)** Driver sementara (`file`/`sync`) sudah diganti seluruhnya: session & cache (A-19), queue (A-20). |
| A-20 | **(Disetujui 2026-11-14)** `QUEUE_CONNECTION=redis` (koneksi Redis `default`, antrean `default`, `retry_after` 90 detik — ditinjau ulang di TAHAP 5 bersama timeout job). Tabel `failed_jobs` dibuat di TAHAP 2 (opsi A); sampai saat itu belum ada job aplikasi. Test memakai `QUEUE_CONNECTION=sync` kecuali test queue yang memakai Redis secara eksplisit. |
| A-19 | **(D-07, disetujui 2026-11-14)** Session & cache di Redis (`SESSION_DRIVER=redis`, `CACHE_STORE=redis`): DB Redis 0 (default/session) dan 1 (cache) untuk dev, **14 dan 15 untuk test** (`phpunit.xml`). Semua key memakai prefix dari `APP_NAME`. Timeout koneksi `REDIS_TIMEOUT=2` detik. Redis lokal tanpa password; server/production **wajib** memakai password (`REDIS_PASSWORD`). |
| A-13 | **(Disetujui 2026-11-14)** Timezone aplikasi `APP_TIMEZONE=UTC` (penyimpanan data). `config/audit.php` dibuat pada fitur yang pertama memakainya (4.1/6.1), bukan sebagai kerangka kosong. |
| A-14 | **(D-32, selesai 2026-11-14)** `backend/CLAUDE.md` & `backend/AGENTS.md` bawaan skeleton dihapus oleh pemilik; `laravel/boost` tidak dipasang. |
| A-15 | **(D-33, 2026-11-14)** `laravel/pao` (dev dependency bawaan skeleton) dipertahankan — hanya memadatkan output test saat dijalankan AI agent. |
| A-16 | **(Disetujui 2026-11-14)** MySQL: database dev `audit_app`, database test `audit_app_testing`, user khusus `audit_app`@`127.0.0.1` (hak hanya pada dua database itu). Database & user dibuat manual oleh developer; kredensial hanya di `backend/.env`. |
| A-17 | **(D-30, disetujui 2026-11-14)** Prefix API `/api/v1` (`routes/api.php` didaftarkan manual di `bootstrap/app.php`, **tanpa** `install:api`/Sanctum) dan format error seragam via `ApiExceptionRenderer`. |
| A-18 | **(D-34, disetujui 2026-11-14)** Mesin dev lokal memakai `DB_USERNAME=root` tanpa password (pilihan pemilik mesin). Template `.env.example` tetap `audit_app`; server/production **wajib** memakai user terbatas dengan password. Pengaman `_testing` di `DatabaseConnectionTest` tetap berlaku. |
| A-21 | **(Disetujui 2026-11-14)** Skema `DATABASE.md` final (revisi R1–R8): `audit_scans.user_id` `ON DELETE CASCADE` + CHECK pemilik (menggantikan usulan D-14 `SET NULL`, yang terbukti ERROR 3823 bila dikombinasikan dengan CHECK); kolom `vulnerabilities.reference_urls` (bukan reserved word `references`); enum `VulnerabilitySeverity`; `dependencies.declared_spec` VARCHAR(1024); `ai_usage_logs` khusus user (tanpa `guest_hash`); tabel baru `scan_tool_runs`; struktur `failed_jobs` didokumentasikan. Termasuk prinsip D-27 (snapshot advisori per audit) dan D-28 (enum = VARCHAR + PHP Enum). |
| A-22 | **(2026-11-15, TAHAP 3)** (a) Pembuatan audit berupa service `AuditScanCreator`; endpoint `POST /audits` dibangun di TAHAP 4 bersama validasi ZIP/Git agar tidak ada endpoint setengah jadi. (b) Akses audit yang ditolak dijawab `404`, bukan `403`. (c) Semua transisi status lewat `AuditStatusManager` dengan row lock. (d) `summary` bernilai `null` untuk tahap tanpa hasil. (e) Route `/api/*` tidak me-redirect guest ke `login`; selalu `401` JSON. (f) Sumber identitas user untuk middleware `auth` masih guard default `web`; mekanisme login (Sanctum/OAuth) dipasang di TAHAP 8 (D-06). |
| A-23 | **(2026-11-15, TAHAP 4)** (a) D-08: allowlist host Git default `github.com,gitlab.com,bitbucket.org`, dapat diubah lewat `AUDIT_GIT_ALLOWED_HOSTS` (diterapkan sesuai rekomendasi; tim dapat mengganti). (b) Batas default yang dapat dikonfigurasi: `AUDIT_ZIP_MAX_ENTRIES=20000`, `AUDIT_ZIP_MAX_COMPRESSION_RATIO=100`, `AUDIT_GIT_CLONE_TIMEOUT=120` detik — **angka menunggu konfirmasi tim**. (c) Error input pada `POST /audits` memakai `VALIDATION_FAILED` + `details` (mengganti `INVALID_ARCHIVE`/`INVALID_REPOSITORY_URL` di level request). (d) Kode ingestion baru `ARCHIVE_ENCRYPTED`. (e) git dijalankan tanpa shell, dengan environment yang dibersihkan dari variabel aplikasi (secret `.env` tidak diteruskan). (f) Redirect HTTP saat clone dinonaktifkan; repo yang di-rename perlu URL barunya. (g) Disk `local` tidak lagi dilayani via route `storage/{path}`. |
| A-24 | **(2026-11-15, TAHAP 5)** (a) `ProcessAuditJob` tunggal (D-26), payload hanya `auditScanId`. (b) `tries = 1`; `AUDIT_JOB_TIMEOUT=600` s; `REDIS_QUEUE_RETRY_AFTER=660` s (default `config/queue.php` diubah dari 90) — **angka menunggu konfirmasi tim**. (c) Engine tahap dipasang lewat antarmuka `StageRunner`; sampai TAHAP 6/7 dipakai `UnavailableStageRunner` yang mencatat tahap `failed` (bukan `skipped`/`completed`). (d) Exception di satu tahap → tahap itu `failed` dengan pesan generik, tahap lain tetap berjalan. (e) Kode error baru: `JOB_TIMEOUT`, `PROCESSING_FAILED`, `QUEUE_UNAVAILABLE`. (f) Antrean memakai queue `default` pada koneksi `redis`. |
| A-25 | **(2026-11-20, TAHAP 7.1–7.2, disetujui)** (a) `ManifestFinder` mengelompokkan `package.json` + `package-lock.json` sejajar per direktori; satu repo boleh berisi banyak proyek (monorepo); lockfile tanpa `package.json` sejajar diabaikan. (b) `PackageJsonParser`: alias `npm:<nama>@<versi pasti>` dicatat `exact` dengan nama asli; alias lain → `non_registry_source`; nama sama di beberapa bagian (dependencies/dev/optional/peer) dikembalikan sebagai entri terpisah (deduplikasi di 7.4); spesifikasi bukan string → `unresolved` (`range_without_lockfile`); nama paket >214 karakter menggagalkan manifest itu; `declared_spec` dipotong 1024 karakter, klasifikasi memakai spesifikasi penuh. (c) `AUDIT_SCA_MAX_MANIFEST_BYTES=5242880` (5 MB), angka disetujui 2026-11-20. |
| A-29 | **(2026-11-20, TAHAP 7.3, disetujui)** (a) `PackageLockParser` hanya membaca objek `packages` lockfile v2/v3 (objek `dependencies` gaya lama diabaikan); lockfile v1/versi tidak dikenal → hasil `unsupported` (bukan error), runner 7.4 memakai aturan tanpa lockfile dan memberi alasan `unsupported_lockfile` pada dependensi langsung berupa rentang. (b) Himpunan *direct* = gabungan nama dari `dependencies`/`devDependencies`/`optionalDependencies`/`peerDependencies` entri root `""` **dan** semua entri workspace (key tanpa segmen `node_modules`); entri workspace hanya dibaca untuk himpunan ini, tidak disimpan sebagai dependensi. Pencocokan memakai nama pada key (bukan field `name`), hanya untuk key `node_modules/<nama>` yang tidak bersarang. `declared_spec` diisi dari rentang tersebut untuk dependensi direct (root didahulukan), NULL untuk transitif. (c) Dependensi hanya jika key diawali `node_modules/` atau memuat `/node_modules/` (batas segmen; `packages/my_node_modules/x` dilewati). Key berbentuk tak wajar (mis. `node_modules/a/lib`) menggagalkan lockfile itu. (d) Scope: `dev` → development, `optional` → optional, `devOptional` → development, `peer` → peer, selain itu production (urutan prioritas ini). (e) `exact` hanya untuk semver pasti dari sumber registry: `link`, `resolved` non-http(s) (git/file/path), atau tarball http(s) yang bukan tata letak `…/<nama>/-/<nama-tanpa-scope>-<versi>.tgz` dengan nama dan versi yang cocok → `non_registry_source`; tanpa `resolved` (bundled) dengan versi pasti → `exact`; versi hilang/bukan semver pasti → `missing_version`. (f) Pembaca berkas bersama `ManifestJsonReader`: tolak symlink, realpath harus di dalam root (termasuk symlink pada direktori induk), batas ukuran ditegakkan saat membaca, depth JSON 64. (g) `AUDIT_SCA_MAX_LOCKFILE_BYTES=5242880` (5 MB; diturunkan dari usulan awal 10 MB setelah pengukuran memori di bawah, atas keputusan pengguna 2026-11-20) — angka disetujui 2026-11-20. Lockfile didekode penuh ke memori; `memory_limit` CLI lokal = 128M (diverifikasi 2026-11-20). Pengukuran parser saja (tanpa framework/DB) dengan lockfile sintetik dari data nyata pada 128M: 2 MB → puncak 18 MB, 5 MB → 40 MB, 10 MB → 74 MB, 20 MB dan 30 MB → kehabisan memori. (h) **Catatan untuk 7.4:** `ManifestParseException` pada lockfile → fallback aturan tanpa lockfile dan `sca_status=partial`, dengan alasan yang tampak di laporan akhir (kegagalan parse lockfile tidak boleh menghilangkan pemindaian transitif tanpa jejak); runner 7.4 yang memegang fallback saat parser mengembalikan `unsupported`. |
| A-26 | **(D-25, disetujui 2026-11-20)** Workspace di `backend/storage/app/private/workspaces/{public_id}/`. Di luar `public/`, mudah dibersihkan, satu folder per audit. |
| A-27 | **(D-26, disetujui 2026-11-20)** Satu `ProcessAuditJob` orkestrator (ingestion → SAST → SCA → skor → cleanup). Workspace harus tetap ada antar-tahap; kegagalan SAST tidak menghentikan SCA (berbeda dengan `Bus::chain`). |
| A-28 | **(D-29, disetujui 2026-11-20)** ID publik audit = ULID; guest mengakses via `X-Guest-Token` (hash SHA-256 di DB). Tidak bisa ditebak; tidak menyimpan token mentah. |
| A-11 | **(D-16, disetujui 2026-11-14)** Framework test backend: **PHPUnit** (bawaan skeleton, terpasang 12.5.44). |

## B. Usulan (menunggu persetujuan)

| ID | Usulan | Alasan |
|---|---|---|
| D-06 | Autentikasi frontend↔backend memakai **Laravel Sanctum mode SPA** (cookie + CSRF). | First-party Laravel; tidak menyimpan token di `localStorage`. Butuh frontend & backend pada site yang sama (mis. `localhost:3000` & `localhost:8000`). |
| D-11 | AI remediation **sinkron** dengan timeout (bukan job). | Lebih sederhana untuk satu temuan; dievaluasi ulang jika latensi terlalu tinggi. |
| D-15 | Tolak login Google jika email belum terverifikasi. | Mencegah akun dengan email tak tervalidasi. |
| D-31 | Eksekusi tool via Symfony Process (array argumen, timeout), Semgrep dengan `--metrics=off`. | Tanpa shell injection; tidak mengirim telemetri. |

## C. Terbuka (perlu keputusan tim — tidak dikarang)

| ID | Pertanyaan | Pemilik | Dibutuhkan pada |
|---|---|---|---|
| D-02 | Angka rate limit guest (audit per IP per jam/hari) dan batas umum API. | Annisa | TAHAP 8 |
| D-03 | Angka kuota AI harian user; apakah panggilan yang gagal mengembalikan kuota. | Annisa | TAHAP 8 |
| D-04 | Apakah guest boleh memakai AI remediation? Jika ya, batasnya. | Annisa | TAHAP 8 |
| D-05 | Provider dan model LLM; cara structured output (JSON mode / tool call). | Annisa + tim | TAHAP 9 |
| D-09 | Perkakas/metode metrik JS/TS: library AST (dependency baru) atau implementasi sendiri. | Raihan | TAHAP 6 |
| D-10 | Rumus skor kesehatan 0–100 dan batas kategori (mis. Baik/Sedang/Buruk); bobot severity. | Raihan | TAHAP 6 |
| D-12 | Timezone reset kuota harian AI (mis. `Asia/Jakarta`). Timezone aplikasi sudah ditetapkan di A-13. | Annisa | TAHAP 8.5 |
| D-13 | Retensi data audit (guest & user) dan apakah ada penghapusan otomatis. | Tim | TAHAP 3 |
| D-17 | Perkakas test frontend (mis. Vitest/Playwright) — dependency baru. | Tim | TAHAP 10 |
| D-18 | Library PDF (mis. `barryvdh/laravel-dompdf`) — dependency baru. | Annisa | TAHAP 10 |
| D-19 | Library Google OAuth (`laravel/socialite`) — dependency baru. | Raihan | TAHAP 8 |
| D-20 | Cara memasang ESLint milik platform (mis. `backend/tooling/package.json`) di server. | Raihan | TAHAP 6 |
| D-21 | Plugin ESLint keamanan (mis. `eslint-plugin-security`) dan parser TypeScript. | Raihan | TAHAP 6 |
| D-22 | Ruleset Semgrep: rule lokal yang di-commit vs registry (`p/…`, butuh jaringan). | Raihan | TAHAP 6 |
| D-23 | Styling frontend (CSS Modules bawaan vs Tailwind) dan library diff viewer. | Tim | TAHAP 10 |
| D-24 | Repo Git di home folder (`/Users/muhmmadyuki/.git`) — dibiarkan, atau dihapus oleh pemilik? Lihat Catatan. | Yuki | Segera |

## D. Catatan

- **D-24**: Saat inspeksi, `git rev-parse --show-toplevel` menunjukkan repository Git berada di `/Users/muhmmadyuki` (home), tanpa commit. Claude telah menjalankan `git init -b main` di root project sehingga project kini punya repository sendiri. Repo di home **tidak** diubah. Risiko: perintah `git add .` di home dapat ikut menambahkan `.ssh/` dan `.git-credentials`. Keputusan menghapus `~/.git` sepenuhnya milik pemilik perangkat.
- Perkakas yang terdeteksi di mesin pengembang (2026-11-14): PHP 8.4.26, Composer 2.9.2, Node 25.2.1, npm 11.6.2, MySQL 9.4.0, Redis 8.10.2, Semgrep 1.180.0, Git 2.52.0. (Bandit tidak diperlukan setelah revisi A-04.)
- Plugin Superpowers terpasang di Claude Code (skill `superpowers:*`). Skill proyek di `.claude/skills/` berdiri sendiri dan disesuaikan dengan aturan proyek.