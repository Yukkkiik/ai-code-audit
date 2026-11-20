# ARCHITECTURE — AI Code Security Audit Platform

> Status dokumen: **DRAFT — menunggu persetujuan tim**

## 1. Tech Stack

| Komponen | Teknologi | Catatan |
|---|---|---|
| Frontend | Next.js (App Router, TypeScript) | Versi stabil terbaru saat inisialisasi. |
| Backend | Laravel REST API | Versi stabil terbaru yang kompatibel dengan PHP 8.4 (terpasang: PHP 8.4.26). |
| Database | MySQL | Terpasang lokal: MySQL 9.4. |
| Queue | Redis + Laravel Queue | Terpasang lokal: Redis 8.10. |
| Cache & Rate Limit | Redis | Laravel `RateLimiter` dengan store Redis. |
| SAST | Semgrep, ESLint (JS/TS saja) | Semgrep 1.180 terpasang; ESLint dipasang sebagai tooling backend (bukan dari repo target). |
| SCA | OSV.dev REST API (npm saja) | `POST /v1/querybatch` + `GET /v1/vulns/{id}`. |
| AI | LLM API (provider dapat dikonfigurasi) | Provider belum ditetapkan. |

## 2. Gambaran Arsitektur

```text
┌──────────────┐   HTTPS/JSON    ┌───────────────────────────────┐
│  Next.js     │ ──────────────▶ │ Laravel REST API (/api/v1)    │
│  (frontend/) │ ◀────────────── │  - Controllers (tipis)        │
└──────────────┘                 │  - Form Requests (validasi)   │
                                 │  - Services (business logic)  │
                                 │  - API Resources (response)   │
                                 └──────┬───────────────┬────────┘
                                        │ dispatch job  │ Eloquent
                                        ▼               ▼
                                 ┌────────────┐   ┌──────────┐
                                 │ Redis      │   │ MySQL    │
                                 │ queue/cache│   │          │
                                 └─────┬──────┘   └────▲─────┘
                                       │ worker        │ simpan hasil
                                       ▼               │
                       ┌───────────────────────────────┴──────┐
                       │ ProcessAuditJob (queue worker)       │
                       │ 1. Ingestion  → workspace sementara  │
                       │ 2. SAST       → Semgrep/ESLint (JS/TS)│
                       │ 3. SCA        → parser + OSV.dev     │
                       │ 4. Skor       → health score         │
                       │ 5. Cleanup    → hapus workspace      │
                       └──────────────────────────────────────┘
                                       │
                         HTTP keluar: OSV.dev, LLM API, git host publik
```

## 3. Struktur Folder

```text
ai-code-security-audit-platform/
├── backend/                      # Laravel (artisan & composer.json langsung di sini)
│   ├── app/
│   │   ├── Http/Controllers/Api/V1/
│   │   ├── Http/Requests/
│   │   ├── Http/Resources/
│   │   ├── Jobs/                 # ProcessAuditJob (orkestrator audit)
│   │   ├── Models/
│   │   ├── Enums/                # AuditStatus, StageStatus, Severity, dst.
│   │   └── Services/
│   │       ├── Ingestion/        # Yuki: ZIP, Git, workspace
│   │       ├── Sast/             # Raihan: runner tool, normalizer, metrics, score
│   │       ├── Sca/              # Yuki: manifest finder, parser, OSV client
│   │       ├── Ai/               # Annisa: prompt builder, LLM client, validator, fallback
│   │       ├── Access/           # Annisa: guest limit, quota
│   │       └── Reporting/        # Annisa: PDF/JSON
│   ├── config/audit.php          # batas ukuran, timeout, kuota (dibaca dari .env)
│   ├── database/migrations/
│   ├── tooling/                  # konfigurasi ESLint/Semgrep milik platform (tepercaya)
│   └── tests/{Unit,Feature}/
├── frontend/                     # Next.js (package.json langsung di sini)
│   └── src/app/ ...
├── .claude/skills/
└── *.md (dokumentasi)
```

Struktur internal di atas adalah usulan; file baru hanya dibuat ketika fiturnya disetujui.

## 4. Pembagian Modul

| Modul | Lokasi backend | Pemilik |
|---|---|---|
| Audit Management (sesi audit, status, riwayat) | `Services/Audit` (`AuditScanCreator`, `AuditStatusManager`), `Policies/AuditScanPolicy`, `AuditController`, `Http/Resources/AuditResource` | Bersama |
| Repository Ingestion | `Services/Ingestion` (`AuditWorkspace`, `SafeZipExtractor`, `GitUrlValidator`, `GitRepositoryCloner`, `RepositoryFileWalker`, `RepositoryIngestor`), `StoreAuditRequest` | Yuki |
| SAST Engine + metrik + skor | `Services/Sast` | Raihan |
| SCA Engine | `Services/Sca` (`ManifestFinder`, `ManifestProject`, `PackageJsonParser`, `PackageLockParser`, `ParsedDependency`, `ParsedLockfile`, `ManifestJsonReader`, `ExactVersion`) | Yuki |
| Auth (Google OAuth, sesi) | `AuthController`, `Services/Auth` | Raihan |
| Guest Mode, rate limit, kuota | `Services/Access`, middleware | Annisa |
| AI Remediation | `Services/Ai` | Annisa |
| Reporting | `Services/Reporting` | Annisa |
| Dashboard & Diff Viewer | `frontend/` | Bersama + Annisa |

## 5. Komunikasi Antarkomponen

- **Frontend ↔ Backend**: REST JSON di `/api/v1`, kontrak di `API_CONTRACT.md`.
- **Autentikasi** (usulan, menunggu persetujuan): Laravel Sanctum mode SPA (cookie session + CSRF). Google OAuth via Laravel Socialite — callback di backend, lalu redirect ke frontend.
- **Guest**: menerima `guest_token` acak saat membuat audit; dikirim lewat header `X-Guest-Token` untuk mengakses audit tersebut. Backend hanya menyimpan hash-nya.
- **Background**: Controller membuat `audit_scans` lalu dispatch `ProcessAuditJob` ke Redis. Job menjalankan `RepositoryIngestor`, lalu setiap tahap lewat `StageRunner` (`App\Services\Audit\Stages`), dan semua perubahan status lewat `AuditStatusManager`. Frontend melakukan polling `GET /api/v1/audits/{id}`. Payload job hanya berisi ID audit — **tidak** berisi isi kode, path absolut di luar workspace, atau secret (payload tersimpan apa adanya di Redis).
- **Kontrak antar subsistem**: SAST dan SCA masing-masing menulis ke tabelnya sendiri dan memperbarui `sast_status` / `sca_status`. AI dan Reporting hanya membaca data tersimpan.

## 6. Alur Sistem Utama (ringkas)

1. `POST /api/v1/audits` → validasi → buat audit (`queued`) → dispatch job → `202 Accepted`.
2. Worker: `ingesting` → siapkan workspace → `scanning` → SAST → SCA → skor.
3. Status akhir: `completed`, `partial` (salah satu tahap gagal), atau `failed`.
4. Cleanup workspace di blok `finally`.
5. Pengguna membuka hasil, meminta AI remediation, mengunduh laporan.

Detail ada di `FLOWS.md`.

## 7. Prinsip Keamanan Arsitektur

- Perkakas dijalankan via `Symfony\Component\Process` dengan argumen array (tanpa shell string), timeout, dan working directory = workspace.
- ESLint memakai konfigurasi platform (`backend/tooling/`) — konfigurasi dari repo target tidak dimuat.
- Tidak menjalankan `npm install`, `pip install`, script, hook, atau build dari repo target.
- Git clone: hanya `https://`, host diverifikasi (anti-SSRF), `--depth 1`, tanpa submodule, tanpa LFS, hooks dinonaktifkan.
- Direktori sementara per audit; ini **bukan** sandbox penuh. Batasan ini dicatat di README dan laporan.
- Output AI berstatus advisory dan tidak pernah diterapkan/dijalankan otomatis.
