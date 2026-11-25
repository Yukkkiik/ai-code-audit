# AI Code Security Audit Platform

Platform web untuk audit kualitas dan keamanan kode repositori: **SAST** untuk JavaScript/TypeScript (Semgrep, ESLint), **SCA** untuk paket Node.js/npm (via OSV.dev), dan **AI Remediation** dengan Diff Viewer serta laporan PDF/JSON.

> **Status proyek:** backend (Laravel 13) sudah mencakup audit management, ingestion, background job, SAST, SCA, dan login Google + session. **Belum ada:** frontend (Next.js), Guest Mode/rate limit/kuota AI, AI Remediation, dan laporan PDF/JSON. Urutan dan status per item ada di `IMPLEMENTATION_ROADMAP.md`.

## Tim
| Anggota | Tanggung jawab |
|---|---|
| Muhamad Raihan Fauzan | SAST Engine & Core Authentication |
| Muhamad Yuki | SCA Engine & Repository Ingestion |
| Annisa Fadilla Efendi Harahap | AI Remediation, AI Tutor, Reporting & Access Control |

## Struktur Repository
```text
ai-code-security-audit-platform/
├── backend/     # Laravel 13 REST API (/api/v1)
│   └── tooling/ # ESLint, Semgrep rules, metrik milik platform (tepercaya)
├── frontend/    # Next.js (belum dibuat)
├── .claude/skills/
└── *.md         # dokumentasi
```

## Fitur yang Sudah Berjalan (backend)
| Fitur | Endpoint |
|---|---|
| Health check | `GET /api/v1/health` |
| Buat audit (ZIP / URL Git publik) | `POST /api/v1/audits` |
| Detail dan riwayat audit | `GET /api/v1/audits/{id}`, `GET /api/v1/audits` |
| Hasil SAST | `GET /api/v1/audits/{id}/findings`, `/metrics` |
| Hasil SCA | `GET /api/v1/audits/{id}/dependencies`, `/vulnerabilities`, `/vulnerabilities/{vulnerabilityId}` |
| Login Google, profil, logout | `GET /api/v1/auth/google/redirect`, `/callback`, `GET /auth/me`, `POST /auth/logout` |

Kontrak lengkap: `API_CONTRACT.md`. Alur dan penanganan error: `FLOWS.md`.

## Prasyarat
| Perangkat | Kegunaan |
|---|---|
| PHP 8.4 + Composer | Backend Laravel |
| Node.js + npm | ESLint dan metrik di `backend/tooling`, frontend nanti |
| MySQL | Database |
| Redis | Queue, cache, session |
| Git | Clone repositori publik |
| Semgrep | SAST JavaScript/TypeScript |

## Instalasi & Menjalankan
### Database MySQL (sekali per mesin)
Jalankan sebagai root MySQL (`mysql -u root -p`), ganti `<password>` dengan password pilihan Anda:
```sql
CREATE DATABASE audit_app         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE audit_app_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'audit_app'@'127.0.0.1' IDENTIFIED BY '<password>';
GRANT ALL PRIVILEGES ON audit_app.*         TO 'audit_app'@'127.0.0.1';
GRANT ALL PRIVILEGES ON audit_app_testing.* TO 'audit_app'@'127.0.0.1';
```
Lalu isi `DB_PASSWORD=<password>` di `backend/.env` (jangan di `.env.example`). Test memakai `audit_app_testing` (diatur di `phpunit.xml`) dengan kredensial yang sama dari `.env`.

| Error | Penyebab | Solusi |
|---|---|---|
| `Access denied for user 'audit_app'` | Password di `.env` salah / user belum dibuat | Periksa `DB_PASSWORD`, jalankan ulang SQL di atas |
| `Unknown database 'audit_app'` | Database belum dibuat | Jalankan `CREATE DATABASE` di atas |
| `Connection refused` (port 3306) | MySQL mati | `brew services start mysql` |
| `Connection refused` (port 6379) / error session | Redis mati | `brew services start redis` |

### Backend (setelah clone repository)
```bash
cd backend
composer install
(cd tooling && npm ci --ignore-scripts)   # ESLint & metrik milik platform
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan test
php artisan serve         # http://localhost:8000
```
Redis wajib berjalan karena session, cache, dan queue disimpan di Redis.

### Queue worker
Pemindaian audit hanya diproses jika worker berjalan di terminal terpisah:
```bash
cd backend
php artisan queue:work redis
```
Setiap `POST /api/v1/audits` mengirim satu `ProcessAuditJob`. Job yang di-dispatch saat worker mati tetap tersimpan di Redis dan diproses ketika worker dinyalakan. Setelah mengubah kode, jalankan `php artisan queue:restart` karena worker menyimpan kode lama di memori.

| Perintah | Kegunaan |
|---|---|
| `php artisan queue:work redis --stop-when-empty` | Proses semua job lalu berhenti |
| `php artisan queue:failed` | Daftar job yang gagal (tabel `failed_jobs`) |
| `php artisan queue:forget <uuid>` / `queue:flush` | Hapus satu / semua catatan job gagal |

Job **tidak** di-retry otomatis (`tries = 1`) agar repositori tidak dipindai ganda; audit yang gagal ditandai `failed` beserta kode errornya.

### Login Google
1. Buat OAuth client di Google Cloud Console; redirect URI = nilai `GOOGLE_REDIRECT_URI`.
2. Isi `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET` di `backend/.env`.
3. Autentikasi memakai Laravel Sanctum mode SPA (cookie session + CSRF). Dari origin frontend, panggil `GET /sanctum/csrf-cookie` lebih dulu, lalu kirim `X-XSRF-TOKEN` pada request mutasi (termasuk guest `POST /audits`).
4. `GET /auth/me` dan `POST /auth/logout` hanya mengenali session untuk request dari origin frontend; membuka URL-nya langsung di address bar menghasilkan `401`. CORS untuk frontend dikonfigurasi saat frontend dibuat.

## Konfigurasi Environment
Semua secret disimpan di `backend/.env` / `frontend/.env.local` dan **tidak** di-commit. Template ada di `backend/.env.example`. Kode membaca nilai lewat `config()`, bukan `env()` langsung.

| Variabel | Kegunaan | Default |
|---|---|---|
| `APP_KEY` | Kunci enkripsi (`php artisan key:generate`) | kosong |
| `APP_DEBUG` | **Wajib `false` di production** | `true` |
| `APP_URL` / `FRONTEND_URL` | URL backend / frontend (redirect OAuth) | `http://localhost:8000` / `http://localhost:3000` |
| `APP_TIMEZONE` | Timezone aplikasi | `UTC` |
| `DB_*` | Koneksi MySQL; `DB_PASSWORD` hanya di `.env` lokal | `mysql`, `127.0.0.1:3306` |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | Semua di Redis | `redis` |
| `SESSION_LIFETIME` | Masa aktif session tanpa aktivitas (menit) | `120` |
| `SANCTUM_STATEFUL_DOMAINS` | Origin frontend yang memakai session cookie | `localhost:3000` |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | Google OAuth | kosong / kosong / `…/api/v1/auth/google/callback` |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | Koneksi Redis; password **wajib** di server | `127.0.0.1` / `6379` / kosong |
| `REDIS_DB` / `REDIS_CACHE_DB` / `REDIS_TIMEOUT` | DB session, DB cache (test: 14/15), timeout detik | `0` / `1` / `2` |
| `AUDIT_JOB_TIMEOUT` / `REDIS_QUEUE_RETRY_AFTER` | Timeout job / `retry_after` (harus lebih besar) | `600` / `660` |

Variabel audit (ingestion, SAST, SCA/OSV):

| Variabel | Kegunaan | Default |
|---|---|---|
| `AUDIT_MAX_REPOSITORY_BYTES` | Batas ukuran ZIP, hasil ekstraksi, dan hasil clone | `52428800` (50 MB) |
| `AUDIT_GIT_ALLOWED_HOSTS` | Allowlist host Git | `github.com,gitlab.com,bitbucket.org` |
| `AUDIT_GIT_CLONE_TIMEOUT` | Batas waktu `git clone` (detik) | `120` |
| `AUDIT_ZIP_MAX_ENTRIES` / `AUDIT_ZIP_MAX_COMPRESSION_RATIO` | Pengaman zip bomb | `20000` / `100` |
| `AUDIT_SAST_MAX_FILES` / `AUDIT_SAST_MAX_FILE_BYTES` | Batas file yang dipindai SAST | `2000` / `1048576` |
| `AUDIT_SEMGREP_BINARY` / `AUDIT_SEMGREP_TIMEOUT` | Semgrep | `semgrep` / `180` |
| `AUDIT_NODE_BINARY` / `AUDIT_ESLINT_TIMEOUT` | Node untuk ESLint dan metrik | `node` / `180` |
| `AUDIT_SCA_MAX_MANIFEST_BYTES` / `AUDIT_SCA_MAX_LOCKFILE_BYTES` | Batas ukuran `package.json` / lockfile | `5242880` |
| `AUDIT_SCA_JOB_MARGIN_SECONDS` | Margin waktu job untuk SCA | `30` |
| `AUDIT_OSV_*` | Base URL (wajib https), timeout, retry, batch, anggaran waktu OSV.dev | lihat `.env.example` |

`git` dan `semgrep` wajib terpasang di server.

> **Catatan upload:** PHP default membatasi `upload_max_filesize=2M` dan `post_max_size=8M`; ZIP di atas 2 MB akan dijawab `413`. Agar ZIP sampai 50 MB dapat diunggah, ubah `php.ini` (lokasi: `php --ini`):
> ```ini
> upload_max_filesize = 50M
> post_max_size = 55M
> ```
> lalu restart `php artisan serve`.

## Pengujian
Lihat `TESTING.md`.
```bash
cd backend && php artisan test
cd backend && ./vendor/bin/pint --test
```
Test yang memanggil OSV.dev nyata dikecualikan dari run default: `RUN_LIVE_OSV_TESTS=1 php artisan test --group=live`.

## Batasan yang Perlu Diketahui
- Repositori diproses di direktori sementara per audit lalu dihapus. Ini **bukan** sandbox/isolasi penuh.
- Kode dan skrip repositori target tidak pernah dijalankan; dependency tidak dipasang; config ESLint/Semgrep repo target tidak dimuat.
- Hanya JavaScript/TypeScript (SAST) dan npm dengan `package.json` / `package-lock.json` v2–v3 (SCA).
- Hasil kosong (SAST maupun SCA) **tidak** berarti bebas kerentanan.
- Rekomendasi AI (setelah dibangun) bersifat advisory dan wajib ditinjau developer.

## Dokumentasi
`PRD.md` · `ARCHITECTURE.md` · `DATABASE.md` · `API_CONTRACT.md` · `FLOWS.md` · `TESTING.md` · `DECISIONS.md` · `IMPLEMENTATION_ROADMAP.md` · `CLAUDE.md`
