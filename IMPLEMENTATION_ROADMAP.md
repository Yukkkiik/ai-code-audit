# IMPLEMENTATION ROADMAP

> Roadmap ini adalah **urutan kerja, bukan izin** untuk mengerjakan semuanya.
> Satu perintah **LANJUT** = merencanakan **satu** item berikutnya. Setiap item: PLAN → persetujuan → IMPLEMENTASI → test → laporan → BERHENTI.

Legenda status: ⬜ belum · 📝 plan diajukan · 🔨 dikerjakan · 👀 menunggu review · ✅ disetujui

## TAHAP 0 — Project Preparation
| ID | Pekerjaan | Status |
|---|---|---|
| 0.1 | Inspeksi repository & inisialisasi Git di root | ✅ |
| 0.2 | Dokumentasi (PRD, ARCHITECTURE, DATABASE, API_CONTRACT, FLOWS, TESTING, CLAUDE, README, DECISIONS, ROADMAP) | ✅ |
| 0.3 | Custom AI skills di `.claude/skills/` | ✅ |

## TAHAP 1 — Backend Foundation
| ID | Pekerjaan | Keputusan terkait | Status |
|---|---|---|---|
| 1.1 | Inisialisasi Laravel langsung di `backend/` | A-10, A-11 | ✅ |
| 1.2 | Konfigurasi environment (`.env.example`, `APP_TIMEZONE`, `FRONTEND_URL`) | A-13 | ✅ |
| 1.3 | Koneksi MySQL (database dev & testing) | A-16 | ✅ |
| 1.4 | Health check API `GET /api/v1/health` + format error seragam | D-30 | ✅ |
| 1.5 | Koneksi Redis (cache, session) + check `redis` di health endpoint | A-19 | ✅ |
| 1.6 | Konfigurasi Laravel Queue (Redis) | A-20 | ✅ |

## TAHAP 2 — Database Foundation
| ID | Pekerjaan | Status |
|---|---|---|
| 2.1 | Finalisasi skema `DATABASE.md` (persetujuan wajib) | ✅ |
| 2.2 | Migration `users` (modifikasi default Laravel; migration skeleton `cache` & `jobs` dihapus) | ✅ |
| 2.3 | Migration `audit_scans` (+ CHECK pemilik) | ✅ |
| 2.4 | Migration `scan_tool_runs`, `scan_findings`, `code_metrics` | ✅ |
| 2.5 | Migration `dependencies`, `vulnerabilities` | ✅ |
| 2.6 | Migration `ai_refactorings` (+ CHECK satu target), `ai_usage_logs` | ✅ |
| 2.6b | Migration `failed_jobs` (dibuat dengan `php artisan make:queue-failed-table`, driver `database-uuids`) — lihat A-20 | ✅ |
| 2.7 | 19 PHP Enum, 8 model + relasi + cast, factory | ✅ |
| 2.8 | Pengujian migration, constraint, cascade & relasi | ✅ |

## TAHAP 3 — Audit Management
| ID | Pekerjaan | Status |
|---|---|---|
| 3.1 | Membuat sesi audit — service `AuditScanCreator` (record + guest token); endpoint `POST /audits` dibangun di 4.1/4.4 | ✅ |
| 3.2 | `GET /api/v1/audits/{id}` + `AuditScanPolicy` (pemilik / `X-Guest-Token`) | ✅ |
| 3.3 | `AuditStatusManager` (state machine + row lock) | ✅ |
| 3.4 | `GET /api/v1/audits` (riwayat user, paginated, tanpa N+1) | ✅ |

## TAHAP 4 — Repository Ingestion (Yuki)
| ID | Pekerjaan | Keputusan | Status |
|---|---|---|---|
| 4.1 | `POST /api/v1/audits` (ZIP & Git) + `StoreAuditRequest` (ekstensi, magic bytes, 413) + `config/audit.php` | A-23 | ✅ |
| 4.2 | Validasi isi ZIP sebelum ekstraksi (path, symlink, enkripsi, jumlah entri, ukuran, rasio kompresi) | A-23 | ✅ |
| 4.3 | `SafeZipExtractor` (hitung byte saat menulis, `node_modules`/`vendor`/`.git` dilewati) | D-25 | ✅ |
| 4.4 | `GitUrlValidator` (https, allowlist, anti-SSRF DNS) | A-23 | ✅ |
| 4.5 | `GitRepositoryCloner` (depth 1, hooks/symlink/submodule/redirect off, timeout, env terisolasi) | A-23 | ✅ |
| 4.6 | `AuditWorkspace` (0700 per audit, cleanup aman symlink) + route `storage/{path}` dinonaktifkan | – | ✅ |
| 4.7 | `RepositoryFileWalker` (tanpa ikut symlink, abaikan `node_modules`/`vendor`/`.git`) + `RepositoryIngestor` | – | ✅ |

## TAHAP 5 — Background Processing
| ID | Pekerjaan | Status |
|---|---|---|
| 5.1 | `ProcessAuditJob` orkestrator + `StageRunner` / `AuditStageRunners` (sementara `UnavailableStageRunner`) | A-24 | ✅ |
| 5.2 | Dispatch dari `POST /audits` + pembaruan status per tahap + metadata (`commit_sha`, ukuran) | A-24 | ✅ |
| 5.3 | Timeout & retry policy (`tries=1`, timeout 600 s, `retry_after` 660 s) | A-24 | ✅ |
| 5.4 | `failed()` (`JOB_TIMEOUT` / `PROCESSING_FAILED`) + cleanup + `failed_jobs` + `QUEUE_UNAVAILABLE` | A-24 | ✅ |

## TAHAP 6 — SAST Engine JavaScript/TypeScript (Raihan)

> Engine diimplementasikan sebagai `StageRunner` untuk `AuditStage::Sast` dan didaftarkan di `AppServiceProvider` menggantikan `UnavailableStageRunner`.
| ID | Pekerjaan | Keputusan | Status |
|---|---|---|---|
| 6.1 | Seleksi file JS/TS (`.js .jsx .mjs .cjs .ts .tsx .mts .cts`), ekstensi didaftarkan di `config/audit.php` | – | ✅ |
| 6.2 | Runner Semgrep (JS/TS) + parser JSON | D-22, D-31 | ✅ |
| 6.3 | Runner ESLint (config platform, parser TS) + parser | D-20, D-21 | ✅ |
| 6.4 | Normalisasi & penyimpanan findings | – | ✅ |
| 6.5 | Metrik CC, Halstead, LOC, MI (JS/TS) | D-09 | ✅ |
| 6.6 | Skor kesehatan + kategori | D-10 | ✅ |
| 6.7 | Endpoint hasil SAST: `GET /audits/{id}/findings` & `/metrics` (+ `meta.scan` berisi status tool) | A-36 | ✅ |

## TAHAP 7 — SCA Engine Node.js/npm (Yuki)

> Engine diimplementasikan sebagai `StageRunner` untuk `AuditStage::Sca` dan didaftarkan di `AppServiceProvider` menggantikan `UnavailableStageRunner`.
| ID | Pekerjaan | Status |
|---|---|---|
| 7.1 | Pencarian manifest & lockfile | ✅ |
| 7.2 | Parser `package.json` (versi pasti vs rentang vs non-registry) | ✅ |
| 7.3 | Parser `package-lock.json` v2/v3 (direct/transitive, dev) | ✅ |
| 7.4 | Penyimpanan dependencies | ✅ |
| 7.5 | OSV client `querybatch` (timeout, retry, validasi, pagination) | ✅ |
| 7.6 | Pengambilan detail advisori + penyimpanan vulnerabilities + runner SCA | ✅ |
| 7.7 | Endpoint `GET /audits/{id}/dependencies` & `GET /audits/{id}/vulnerabilities` (+ detail per advisori) | ✅ |
| 7.8 | Pencocokan range versi perbaikan (prasyarat AI Remediation, Tahap 9) | ✅ |
| 7.9 | Versi perbaikan gabungan per dependency (memenuhi semua advisori yang tersimpan) | ✅ |
| 7.10 | Simpan daftar `versions` eksplisit OSV (agar advisori tanpa range tidak `undetermined`) | ✅ |

## TAHAP 8 — Authentication & Access Control
| ID | Pekerjaan | Keputusan | Status |
|---|---|---|---|
| 8.1 | Google OAuth (Raihan) | D-06, D-15, D-19 | ✅ |
| 8.2 | Session management & logout (Raihan) | D-07 | ✅ |
| 8.3 | Guest Mode (Annisa) — fungsi sudah ada sejak 3.1/3.2/4.1; 8.3 mengunci perilakunya dengan `GuestModeTest`. Larangan AI untuk guest (D-04) ditegakkan di 9.5 | D-04 | 👀 |
| 8.4 | IP rate limiting (Annisa) | D-02 | ⬜ |
| 8.5 | Kuota AI harian atomik (Annisa) | D-03, D-12 | ⬜ |

## TAHAP 9 — AI Remediation (Annisa)
| ID | Pekerjaan | Keputusan | Status |
|---|---|---|---|
| 9.1 | Prompt builder (data vs instruksi terpisah) | – | ⬜ |
| 9.2 | LLM client yang dapat dikonfigurasi | D-05 | ⬜ |
| 9.3 | Structured output + validator | – | ⬜ |
| 9.4 | Static rule template fallback | – | ⬜ |
| 9.5 | Endpoint & penyimpanan remediation | D-11 | ⬜ |
| 9.6 | Perketat skill `ai-remediation-guardrail`: versi perbaikan yang boleh disebut hanya `combined_fix.fixed_version` bila `combined_fix.status` = `fix_available` (bukan `fix_match.fixed_version` per advisori dan bukan semua `fixed_versions`) | A-34, A-37 | ⬜ |

## TAHAP 10 — Frontend, Dashboard & Reporting
| ID | Pekerjaan | Keputusan | Status |
|---|---|---|---|
| 10.1 | Inisialisasi Next.js langsung di `frontend/` | D-01, D-23 | ⬜ |
| 10.2 | API client + auth state | – | ⬜ |
| 10.3 | Halaman input repo + polling status | – | ⬜ |
| 10.4 | Dashboard audit (ringkasan, skor) | – | ⬜ |
| 10.5 | Halaman hasil SAST | – | ⬜ |
| 10.6 | Halaman hasil SCA | – | ⬜ |
| 10.7 | Diff Viewer | D-23 | ⬜ |
| 10.8 | Riwayat audit | – | ⬜ |
| 10.9 | Ekspor JSON (backend) | – | ⬜ |
| 10.10 | Ekspor PDF (backend) | D-18 | ⬜ |

## TAHAP 11 — End-to-End Integration
| ID | Pekerjaan | Status |
|---|---|---|
| 11.1 | Integrasi seluruh subsistem | ⬜ |
| 11.2 | Pengujian end-to-end | ⬜ |
| 11.3 | Security testing | ⬜ |
| 11.4 | Performance testing | ⬜ |
| 11.5 | Bug fixing | ⬜ |
| 11.6 | Dokumentasi final | ⬜ |