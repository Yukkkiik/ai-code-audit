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
| 1.1 | Inisialisasi Laravel langsung di `backend/` | A-10, A-11 | 👀 |
| 1.2 | Konfigurasi environment (`.env.example`, `APP_TIMEZONE`, `FRONTEND_URL`) | A-13 | 👀 |
| 1.3 | Koneksi MySQL (database dev & testing) | A-16 | 👀 |
| 1.4 | Health check API `GET /api/v1/health` + format error seragam | D-30 | 👀 |
| 1.5 | Koneksi Redis (cache, session) + check `redis` di health endpoint | A-19 | 👀 |
| 1.6 | Konfigurasi Laravel Queue (Redis) | A-20 | 👀 |

## TAHAP 2 — Database Foundation
| ID | Pekerjaan | Status |
|---|---|---|
| 2.1 | Finalisasi skema `DATABASE.md` (persetujuan wajib) | ✅ |
| 2.2 | Migration `users` (modifikasi default Laravel; migration skeleton `cache` & `jobs` dihapus) | 👀 |
| 2.3 | Migration `audit_scans` (+ CHECK pemilik) | 👀 |
| 2.4 | Migration `scan_tool_runs`, `scan_findings`, `code_metrics` | 👀 |
| 2.5 | Migration `dependencies`, `vulnerabilities` | 👀 |
| 2.6 | Migration `ai_refactorings` (+ CHECK satu target), `ai_usage_logs` | 👀 |
| 2.6b | Migration `failed_jobs` (dibuat dengan `php artisan make:queue-failed-table`, driver `database-uuids`) — lihat A-20 | 👀 |
| 2.7 | 19 PHP Enum, 8 model + relasi + cast, factory | 👀 |
| 2.8 | Pengujian migration, constraint, cascade & relasi | 👀 |

## TAHAP 3 — Audit Management
| ID | Pekerjaan | Status |
|---|---|---|
| 3.1 | Membuat sesi audit (tanpa ingestion; record + guest token) | ⬜ |
| 3.2 | Membaca detail audit + otorisasi pemilik/guest | ⬜ |
| 3.3 | Service transisi status audit & tahap | ⬜ |
| 3.4 | Riwayat audit user (paginated) | ⬜ |

## TAHAP 4 — Repository Ingestion (Yuki)
| ID | Pekerjaan | Keputusan | Status |
|---|---|---|---|
| 4.1 | Upload ZIP + validasi request + `config/audit.php` (batas 50 MB) + batas `php.ini` (`upload_max_filesize`, `post_max_size`) | – | ⬜ |
| 4.2 | Validasi isi ZIP (path, symlink, ukuran, zip bomb) | – | ⬜ |
| 4.3 | Ekstraksi aman ke workspace | D-25 | ⬜ |
| 4.4 | Validasi URL Git (anti-SSRF) | D-08 | ⬜ |
| 4.5 | Git clone aman (timeout, depth 1, hooks off, batas ukuran) | – | ⬜ |
| 4.6 | Temporary workspace manager + cleanup + nonaktifkan route bawaan `storage/{path}` (disk `local` → `serve`) | – | ⬜ |
| 4.7 | Traversal file rekursif (abaikan `node_modules`, `vendor`, `.git`) | – | ⬜ |

## TAHAP 5 — Background Processing
| ID | Pekerjaan | Status |
|---|---|---|
| 5.1 | `ProcessAuditJob` kerangka orkestrator | ⬜ |
| 5.2 | Worker & pembaruan status per tahap | ⬜ |
| 5.3 | Timeout & retry policy | ⬜ |
| 5.4 | Penanganan failed jobs + cleanup (tabel `failed_jobs` dari 2.6b) | ⬜ |

## TAHAP 6 — SAST Engine JavaScript/TypeScript (Raihan)
| ID | Pekerjaan | Keputusan | Status |
|---|---|---|---|
| 6.1 | Seleksi file JS/TS (`.js .jsx .mjs .cjs .ts .tsx .mts .cts`), ekstensi didaftarkan di `config/audit.php` | – | ⬜ |
| 6.2 | Runner Semgrep (JS/TS) + parser JSON | D-22, D-31 | ⬜ |
| 6.3 | Runner ESLint (config platform, parser TS) + parser | D-20, D-21 | ⬜ |
| 6.4 | Normalisasi & penyimpanan findings | – | ⬜ |
| 6.5 | Metrik CC, Halstead, LOC, MI (JS/TS) | D-09 | ⬜ |
| 6.6 | Skor kesehatan + kategori | D-10 | ⬜ |

## TAHAP 7 — SCA Engine Node.js/npm (Yuki)
| ID | Pekerjaan | Status |
|---|---|---|
| 7.1 | Pencarian manifest & lockfile | ⬜ |
| 7.2 | Parser `package.json` (versi pasti vs rentang vs non-registry) | ⬜ |
| 7.3 | Parser `package-lock.json` v2/v3 (direct/transitive, dev) | ⬜ |
| 7.4 | Penyimpanan dependencies | ⬜ |
| 7.5 | OSV client `querybatch` (timeout, retry, validasi, pagination) | ⬜ |
| 7.6 | Pengambilan detail advisori + penyimpanan vulnerabilities | ⬜ |

## TAHAP 8 — Authentication & Access Control
| ID | Pekerjaan | Keputusan | Status |
|---|---|---|---|
| 8.1 | Google OAuth (Raihan) | D-06, D-15, D-19 | ⬜ |
| 8.2 | Session management & logout (Raihan) | D-07 | ⬜ |
| 8.3 | Guest Mode (Annisa) | D-04 | ⬜ |
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
