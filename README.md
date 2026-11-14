# AI Code Security Audit Platform

Platform web untuk audit kualitas dan keamanan kode repositori: **SAST** untuk JavaScript/TypeScript (Semgrep, ESLint), **SCA** untuk paket Node.js/npm (via OSV.dev), dan **AI Remediation** dengan Diff Viewer serta laporan PDF/JSON.

> **Status proyek:** TAHAP 1.1 — kerangka backend Laravel 13 sudah diinisialisasi; frontend **belum**. Langkah instalasi di bawah dilengkapi seiring setiap tahap selesai.

## Tim
| Anggota | Tanggung jawab |
|---|---|
| Muhamad Raihan Fauzan | SAST Engine & Core Authentication |
| Muhamad Yuki | SCA Engine & Repository Ingestion |
| Annisa Fadilla Efendi Harahap | AI Remediation, AI Tutor, Reporting & Access Control |

## Struktur Repository
```text
ai-code-security-audit-platform/
├── backend/     # Laravel 13 REST API (kerangka)
├── frontend/    # Next.js (belum dibuat)
├── .claude/skills/
└── *.md         # dokumentasi
```

## Prasyarat
| Perangkat | Kegunaan |
|---|---|
| PHP 8.4 + Composer | Backend Laravel |
| Node.js + npm | Frontend Next.js, ESLint tooling |
| MySQL | Database |
| Redis | Queue, cache, session, rate limit |
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
| `Connection refused` (port 6379) / halaman web error session | Redis mati | `brew services start redis` |

### Backend (setelah clone repository)
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan test          # verifikasi
php artisan serve         # http://localhost:8000
```
Catatan: Redis wajib berjalan (`brew services start redis`) karena session dan cache disimpan di Redis. Queue masih `sync` sampai Laravel Queue dikonfigurasi. **Jangan** menjalankan `php artisan migrate` dulu; skema database menunggu persetujuan TAHAP 2.

_Akan diisi setelah item roadmap terkait selesai:_
- Database, Redis, migrasi (TAHAP 1.3–1.6, TAHAP 2).
- Queue worker (TAHAP 1.6/5): `php artisan queue:work redis`.
- Frontend (TAHAP 10.1): `npm install`, `npm run dev`.

## Konfigurasi Environment
Semua secret (kredensial DB, Google OAuth, API key LLM) disimpan di `backend/.env` / `frontend/.env.local` dan **tidak** di-commit. Template variabel ada di `backend/.env.example`.

| Variabel | Kegunaan | Default |
|---|---|---|
| `APP_NAME` | Nama aplikasi | `AI Code Security Audit Platform` |
| `APP_ENV` | `local` / `production` | `local` |
| `APP_KEY` | Kunci enkripsi — dibuat dengan `php artisan key:generate`, **jangan** di-commit | kosong |
| `APP_DEBUG` | Mode debug — **wajib `false` di production** | `true` |
| `APP_URL` | URL backend | `http://localhost:8000` |
| `APP_TIMEZONE` | Timezone aplikasi & penyimpanan data | `UTC` |
| `FRONTEND_URL` | URL Next.js (CORS, redirect OAuth) | `http://localhost:3000` |
| `DB_*` | Koneksi MySQL (`audit_app`); `DB_PASSWORD` hanya di `.env` lokal | `mysql`, `127.0.0.1:3306` |
| `SESSION_DRIVER` / `CACHE_STORE` | Session & cache di Redis | `redis` / `redis` |
| `QUEUE_CONNECTION` | Sementara `sync` sampai Laravel Queue dikonfigurasi | `sync` |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | Koneksi Redis; password **wajib** di server | `127.0.0.1` / `6379` / kosong |
| `REDIS_DB` / `REDIS_CACHE_DB` | DB Redis untuk session dan cache (test memakai 14/15) | `0` / `1` |
| `REDIS_TIMEOUT` | Batas waktu koneksi Redis (detik) | `2` |

Kode aplikasi membaca nilai lewat `config()`, bukan `env()` langsung.

> **Catatan upload:** PHP lokal default membatasi `upload_max_filesize=2M` dan `post_max_size=8M`. Upload ZIP 50 MB membutuhkan penyesuaian `php.ini` (ditangani di TAHAP 4.1).

## Pengujian
Lihat `TESTING.md`. Perintah ringkas (setelah inisialisasi):
```bash
cd backend && php artisan test
cd frontend && npm run lint && npx tsc --noEmit
```

## Batasan Keamanan yang Perlu Diketahui
- Repositori diproses di direktori sementara per audit lalu dihapus. Ini **bukan** sandbox/isolasi penuh.
- Kode dan skrip repositori target tidak pernah dijalankan; dependency tidak dipasang.
- Hasil SCA kosong **tidak** berarti bebas kerentanan.
- Rekomendasi AI bersifat advisory dan wajib ditinjau developer.

## Dokumentasi
`PRD.md` · `ARCHITECTURE.md` · `DATABASE.md` · `API_CONTRACT.md` · `FLOWS.md` · `TESTING.md` · `DECISIONS.md` · `IMPLEMENTATION_ROADMAP.md` · `CLAUDE.md`
