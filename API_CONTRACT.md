# API CONTRACT — Versi Awal

> Status dokumen: **DRAFT — menunggu persetujuan tim**
> Base URL: `{BACKEND_URL}/api/v1` · Format: JSON (`Content-Type: application/json`) kecuali upload (`multipart/form-data`) dan PDF.

## 1. Konvensi

### Response sukses
```json
{ "data": { ... }, "meta": { ... } }
```
`meta` untuk pagination (`current_page`, `per_page`, `total`, `last_page`). Daftar hasil SCA (`/dependencies`, `/vulnerabilities`) menambahkan `meta.scan` (lihat §2.3.1).

### Response error (seragam)
```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Pesan yang aman dibaca pengguna.",
    "details": { "repository_url": ["URL harus menggunakan https."] }
  }
}
```
`details` bernilai `null` jika tidak ada detail tambahan (diisi error per field untuk `VALIDATION_FAILED`).
Stack trace, path server, dan pesan exception mentah **tidak pernah** dikirim ke client — termasuk saat `APP_DEBUG=true`. Semua error di `/api/*` selalu JSON, walaupun request tidak mengirim header `Accept: application/json`. (Implementasi: `backend/app/Exceptions/ApiExceptionRenderer.php`.)

### Kode error umum
| HTTP | code | Kapan |
|---|---|---|
| 400 | `BAD_REQUEST` | Request tidak dapat diproses. |
| 401 | `UNAUTHENTICATED` | Endpoint butuh login. |
| 403 | `FORBIDDEN` | Bukan pemilik audit / guest token salah. |
| 404 | `NOT_FOUND` | Resource tidak ada (juga dipakai untuk audit milik orang lain agar tidak membocorkan keberadaan). |
| 405 | `METHOD_NOT_ALLOWED` | Method HTTP tidak didukung endpoint. |
| 409 | `AUDIT_NOT_READY` | Data diminta sebelum tahap terkait selesai. |
| 413 | `PAYLOAD_TOO_LARGE` | File > 50 MB. |
| 422 | `VALIDATION_FAILED` | Validasi input gagal. |
| 429 | `RATE_LIMITED` / `AI_QUOTA_EXCEEDED` | Batas guest atau kuota AI habis. Header `Retry-After`. |
| 500 | `INTERNAL_ERROR` | Kesalahan tak terduga. |
| 502/504 | `UPSTREAM_ERROR` / `UPSTREAM_TIMEOUT` | Layanan eksternal (LLM) gagal, bila tidak ada fallback. |

### Autentikasi & akses audit
- **User terdaftar**: cookie session (Sanctum SPA, usulan D-06). Request mutasi wajib header `X-XSRF-TOKEN`.
- **Guest**: header `X-Guest-Token: <token>` yang diterima saat membuat audit.
- Akses audit diizinkan jika: `audit.user_id == user login`, **atau** audit guest dan `sha256(X-Guest-Token) == guest_token_hash`.

### Representasi `Audit`
```json
{
  "id": "01JD3X5W8Q2G7B9N4K6M1R0T2Y",
  "source_type": "git",
  "repository_name": "owner/repo",
  "repository_url": "https://github.com/owner/repo",
  "commit_sha": "a1b2c3...",
  "status": "scanning",
  "sast": { "status": "completed", "error": null },
  "sca":  { "status": "running", "error": null },
  "health_score": null,
  "health_category": null,
  "summary": {
    "findings_by_severity": { "critical": 0, "high": 2, "medium": 5, "low": 1, "info": 0 },
    "dependencies_total": null,
    "dependencies_unresolved": null,
    "vulnerabilities_total": null
  },
  "error": null,
  "queued_at": "2026-11-14T14:00:00Z",
  "started_at": "2026-11-14T14:00:02Z",
  "finished_at": null,
  "created_at": "2026-11-14T14:00:00Z"
}
```
`summary` hanya menghitung tahap yang `completed`/`partial`; untuk tahap lain field-nya bernilai **`null`** (contoh di atas: SCA masih `running`) — tidak pernah `0`, agar tidak terbaca sebagai "tidak ada masalah". Frontend wajib membaca `sast.status` / `sca.status`.

Field yang **tidak pernah** dikirim: `id` internal (integer), `user_id`, `guest_token_hash`.

## 2. Endpoint

### 2.1 System
| Method | Path | Auth | Keterangan |
|---|---|---|---|
| GET | `/health` | – | Status aplikasi dan dependensinya. |

Response `200`:
```json
{ "data": { "status": "ok", "checks": { "database": "ok", "redis": "ok" }, "time": "2026-11-14T14:00:00Z" } }
```
Jika salah satu dependensi gagal → `503` dengan `status: "degraded"` dan check terkait `"fail"`. Detail kegagalan hanya dicatat di log server. `time` selalu UTC (ISO-8601, akhiran `Z`).

### 2.2 Auth (FR-02, FR-03)
| Method | Path | Auth | Keterangan |
|---|---|---|---|
| GET | `/auth/google/redirect` | – | Redirect 302 ke Google (parameter `state` divalidasi). |
| GET | `/auth/google/callback` | – | Callback Google → buat/perbarui user → session → redirect ke `FRONTEND_URL`. Gagal → redirect `FRONTEND_URL/login?error=oauth_failed`. |
| GET | `/auth/me` | User | Data user + sisa kuota AI. |
| POST | `/auth/logout` | User | Invalidasi session + regenerasi CSRF token. `204`. |

`GET /auth/me` → `200`:
```json
{ "data": { "id": 1, "name": "…", "email": "…", "avatar_url": "…", "ai_quota": { "limit": null, "used": 0, "remaining": null, "resets_on": "2026-11-15" } } }
```
(`limit` diisi dari konfigurasi setelah D-03 disepakati.)

### 2.3 Audit (FR-05, FR-09)
| Method | Path | Auth | Keterangan |
|---|---|---|---|
| POST | `/audits` | Guest / User | Buat audit + dispatch job. Guest dikenai rate limit IP. **Dibangun di TAHAP 4** (ZIP: 4.1, Git: 4.4) di atas `AuditScanCreator`. |
| GET | `/audits` | User | Riwayat audit milik user (paginated, terbaru dulu). Query: `page` (≥1), `per_page` (1–100, default 15). Tanpa login → `401 UNAUTHENTICATED`. ✅ |
| GET | `/audits/{id}` | Pemilik | Detail + status (dipakai polling). `{id}` = ULID. Bukan pemilik / token salah / ID tidak valid → `404 NOT_FOUND`. ✅ |
| GET | `/audits/{id}/findings` | Pemilik / Guest | Temuan SAST (paginated). Query: `severity`, `tool`, `category`, `page`, `per_page` (1–100, default 15). Lihat §2.3.2. ✅ |
| GET | `/audits/{id}/metrics` | Pemilik / Guest | Metrik kode (paginated). Query: `scope=file\|function` (default `file`), `page`, `per_page` (1–100, default 15). Lihat §2.3.2. ✅ |
| GET | `/audits/{id}/dependencies` | Pemilik / Guest | Daftar dependency (paginated). Query: `scope`, `relation`, `version_status`, `page`, `per_page` (1–100, default 15). Lihat §2.3.1. ✅ |
| GET | `/audits/{id}/vulnerabilities` | Pemilik / Guest | Daftar kerentanan (paginated), **tanpa** `details`. Query: `severity`, `page`, `per_page`. Lihat §2.3.1. ✅ |
| GET | `/audits/{id}/vulnerabilities/{vulnerabilityId}` | Pemilik / Guest | Detail satu baris kerentanan (teks lengkap, vektor CVSS, range, versi perbaikan OSV, referensi). `{vulnerabilityId}` = integer. Lihat §2.3.1. ✅ |

**POST `/audits` — ZIP** (`multipart/form-data`)
| Field | Aturan |
|---|---|
| `source_type` | wajib, `zip` |
| `file` | wajib, file, ekstensi `.zip` & magic bytes `PK\x03\x04` (atau `PK\x05\x06` untuk arsip kosong), ≤ `AUDIT_MAX_REPOSITORY_BYTES` (50 MB) |

**POST `/audits` — Git** (`application/json`)
| Field | Aturan |
|---|---|
| `source_type` | wajib, `git` |
| `repository_url` | wajib, `https://`, ≤ 2048 karakter, tanpa userinfo (`user:pass@`), tanpa port selain 443, tanpa query/fragment, host ada di allowlist `AUDIT_GIT_ALLOWED_HOSTS` (default `github.com`, `gitlab.com`, `bitbucket.org`), bukan IP literal, path ≥ 2 segmen aman, dan **seluruh IP hasil DNS publik** (anti-SSRF) |

Response `202 Accepted`:
```json
{ "data": { "audit": { "id": "01JD…", "status": "queued", "…": "…" }, "guest_token": "only-returned-once-for-guest" } }
```
`guest_token` hanya ada untuk guest dan hanya dikirim sekali.

Error saat request: `422 VALIDATION_FAILED` dengan `details.file` / `details.repository_url` berisi alasan (mis. "Berkas bukan arsip ZIP yang valid.", "Host evil.example.com tidak didukung..."), `413 PAYLOAD_TOO_LARGE` (termasuk berkas yang ditolak batas `upload_max_filesize`/`post_max_size` PHP), dan `429 RATE_LIMITED` (dibangun di TAHAP 8). Revisi 2026-11-15: kode `INVALID_ARCHIVE` / `INVALID_REPOSITORY_URL` di level request diganti `VALIDATION_FAILED` + `details` agar semua error input memakai satu format per field.

Status implementasi: endpoint aktif. Audit dibuat berstatus `queued`, lalu `ProcessAuditJob` (payload hanya ID audit) di-dispatch ke antrean Redis dan diproses worker. Tahap SAST (TAHAP 6) dan SCA (TAHAP 7) dijalankan engine asli; status dan pesan tiap tahap ada di `sast`/`sca` pada representasi audit.

#### 2.3.1 Hasil SCA: `dependencies` dan `vulnerabilities` (item 7.7)

**Akses.** Pemilik (`audit.user_id`) atau guest dengan `X-Guest-Token` (hanya header; token di query string tidak diterima). Semua penolakan → `404 NOT_FOUND`, dan dicek **sebelum** validasi query sehingga filter salah tidak membedakan audit yang ada dari yang tidak. Audit yang masih berjalan (`queued`/`ingesting`/`scanning`) dengan `sca.status` `pending`/`running` → `409 AUDIT_NOT_READY`. Audit yang sudah berakhir tetapi `sca.status` masih `pending`/`running` (job mati) → `200` dengan `meta.scan.message` "Pemeriksaan SCA tidak selesai…" dan `note` `null`. Semua respons (termasuk error) membawa `Cache-Control: private, no-store` dan `X-Content-Type-Options: nosniff`.

**Paginasi.** `page` ≥ 1, `per_page` 1–100 (default 15); halaman melewati akhir → `data: []`. Nilai filter di luar enum → `422 VALIDATION_FAILED`.

**`GET /audits/{id}/dependencies`** — urut `name`, lalu `id`:
```json
{ "data": [ { "id": 12, "name": "lodash", "version": "4.17.15", "declared_spec": "^4.17.0", "scope": "production", "relation": "direct",
              "manifest_path": "package-lock.json", "source_file_type": "package_lock_v3", "lock_path": "node_modules/lodash",
              "version_status": "exact", "unresolved_reason": null, "osv_status": "queried", "vulnerabilities_count": 3 } ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "scan": { … } } }
```
`vulnerabilities_count` bernilai angka **hanya** bila `osv_status=queried`; selain itu `null` (tidak pernah `0`), agar dependensi yang tidak diperiksa tidak terbaca "tanpa kerentanan".

**`GET /audits/{id}/vulnerabilities`** — urut: `critical`, `high`, `unknown`, `medium`, `low`, lalu `osv_id`, `id`:
```json
{ "data": [ { "id": 45, "osv_id": "GHSA-35jh-r3h4-6jhm", "aliases": ["CVE-2021-23337"], "summary": "…", "severity": "high", "detail_available": true,
              "dependency": { "id": 12, "name": "lodash", "version": "4.17.15", "scope": "production", "relation": "direct" },
              "published_at": "2021-05-06T16:05:51Z", "modified_at": "2026-10-17T02:55:38Z" } ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "scan": { … } } }
```
- **Satu baris = pasangan (dependency, advisori).** Advisori yang sama dapat muncul beberapa kali (satu per dependency yang terdampak); `id` adalah id baris, bukan id advisori.
- `severity`: `critical|high|medium|low|unknown`. `unknown` berarti severity tidak dapat diturunkan **atau** detail advisori tidak berhasil diambil (`detail_available: false`); tidak pernah ditampilkan sebagai `low`, dan diurutkan sebelum `medium`. Filter `severity=unknown` mencakup keduanya. Skor CVSS tidak dihitung (hanya vektor mentah di detail).
- Daftar **tidak** memuat `details`, `severity_vectors`, `affected_ranges`, versi perbaikan, maupun `reference_urls`. Untuk baris dengan `detail_available: false`, `summary` dan `aliases` bernilai `null`.

**`GET /audits/{id}/vulnerabilities/{vulnerabilityId}`** — semua field item daftar ditambah `details`, `severity_vectors`, `affected_ranges`, `osv_fixed_versions`, `osv_fixed_versions_note`, `reference_urls` (hanya `http(s)`), dan `dependency` lengkap (`declared_spec`, `manifest_path`, `source_file_type`, `lock_path`, `version_status`, `unresolved_reason`, `osv_status`). Baris dicari dengan `audit_scan_id` audit pada URL; id milik audit lain, tidak ada, atau bukan angka → `404`. Selain field di atas, detail memuat `fix_match`.
- `osv_fixed_versions` = **semua** nilai `fixed` pada **seluruh** range advisori untuk paket itu, apa adanya dari OSV, **bukan rekomendasi versi**. `[]` = OSV tidak mencantumkan versi perbaikan; `null` = detail tidak berhasil diambil. Untuk versi yang berlaku bagi dependency ini, gunakan `fix_match`. Klien tidak boleh menampilkan `osv_fixed_versions` sebagai "versi yang disarankan".

**`fix_match`** (item 7.8; hanya di detail) — pencocokan **versi terpasang** dengan range afektasi advisori ini:
```json
"fix_match": { "status": "fix_available", "installed_version": "2.2.2", "fixed_version": "2.4.0",
               "version_change": "minor", "reason": null,
               "note": "Versi perbaikan terkecil menurut data OSV untuk advisori ini, pada range yang memuat versi terpasang. Hanya berlaku untuk advisori ini. Versi itu dapat terkena advisori lain. Keberadaannya di registry npm tidak diperiksa." }
```
| `status` | Arti |
|---|---|
| `fix_available` | Versi terpasang berada di range yang terdampak dan ada nilai `fixed` yang valid. `fixed_version` terisi. |
| `no_fix_recorded` | Versi terpasang terdampak, tetapi OSV tidak mencatat nilai `fixed` yang dapat dipakai (mis. range hanya memakai `last_affected`). |
| `undetermined` | Tidak dapat dipastikan; lihat `reason`. |

| `reason` (hanya untuk `undetermined`) | Arti |
|---|---|
| `detail_unavailable` | Detail advisori tidak berhasil diambil (baris minimal). |
| `no_range_data` | Tidak ada range SEMVER/ECOSYSTEM untuk paket ini (mis. hanya range GIT atau daftar `versions` eksplisit yang tidak disimpan). |
| `not_in_listed_ranges` | OSV mengembalikan advisori ini, tetapi range yang tersimpan tidak memuat versi terpasang (di bawah `introduced`, pada atau di atas `fixed`, atau di celah antar range). **Bukan berarti tidak terdampak.** |
| `unevaluable_range` | Ada range yang tidak dapat dievaluasi (event `limit`, versi bukan semver ketat, event tidak wajar, atau data melebihi batas). |
| `invalid_version` | Versi terpasang bukan semver ketat. |

- Tidak ada status "tidak terdampak": advisori yang dikembalikan OSV tidak boleh dinyatakan tidak berlaku oleh klien berdasarkan `fix_match`.
- `fixed_version` hanya terisi untuk `fix_available`, dan selalu nilai `fixed` yang **tertulis di OSV** (persis seperti ditulis), tidak pernah hasil perhitungan. Nilainya adalah `fixed` terkecil yang lebih besar dari versi terpasang dan sendiri tidak terdampak oleh range advisori ini; itu dapat berasal dari cabang lain, ditandai oleh `version_change`.
- `version_change` (`patch|minor|major|prerelease`, `null` bila bukan `fix_available`) hanya **petunjuk** besarnya lompatan dari versi terpasang ke `fixed_version`; `prerelease` berarti salah satu versinya prerelease. Ini bukan rekomendasi dan **tidak boleh dipakai UI untuk label seperti "aman diupgrade"**.
- `note` memuat tiga hal: hanya untuk advisori ini; versi itu dapat terkena advisori lain; keberadaannya di registry npm tidak diperiksa.
- Dihitung saat dibaca dari `affected_ranges` dan versi dependency yang tersimpan (tanpa kolom baru). Daftar kerentanan tidak memuat `fix_match`.

**`meta.scan`** (pada kedua daftar):
```json
"scan": { "status": "partial", "message": "…isi sca_error atau penjelasan…",
          "coverage": { "dependencies_total": 12, "checked": 9, "check_failed": 1, "not_checked": 2, "unresolved": 2,
                        "advisories": 4, "advisories_without_detail": 1 },
          "note": "…atau null" }
```
- `coverage` dihitung untuk **seluruh audit**, bukan halaman atau filter. `dependencies_total = checked + check_failed + not_checked`; `unresolved` adalah bagian dari `not_checked`.
- `note` disusun dari hitungan. Daftar kerentanan dengan 0 advisori: "Tidak ditemukan advisori pada OSV.dev untuk N dependensi yang diperiksa; ini bukan jaminan bebas kerentanan." (atau "Tidak ada dependensi yang diperiksa ke OSV.dev; …" bila N = 0). Bila ada advisori, `note` hanya menyebut cakupan tidak lengkap ("Cakupan pemeriksaan tidak lengkap: …"). Teks tidak pernah memakai kata "aman" atau "bersih".

**Keamanan klien.** `summary`, `details`, dan teks advisori lain berasal dari OSV dan **wajib di-escape** saat ditampilkan; API mengirimnya sebagai data JSON apa adanya. Teks advisori tidak boleh diperlakukan sebagai instruksi oleh AI. Matriks akses harus diuji ulang dengan login sungguhan setelah Tahap 8, dan rate limit guest (8.4) harus mencakup endpoint ini.

#### 2.3.2 Hasil SAST: `findings` dan `metrics` (item 6.7)

**Akses & kesiapan.** Sama dengan §2.3.1: pemilik atau guest dengan header `X-Guest-Token` (token di query string tidak diterima); semua penolakan → `404 NOT_FOUND`, dicek **sebelum** validasi query. Audit yang masih berjalan (`queued`/`ingesting`/`scanning`) dengan `sast.status` `pending`/`running` → `409 AUDIT_NOT_READY` (tahap SCA tidak memengaruhi). Audit yang sudah berakhir tetapi `sast.status` masih `pending`/`running` → `200` dengan `meta.scan.message` "Pemeriksaan SAST tidak selesai…" dan `note` `null`. Semua respons (termasuk error) membawa `Cache-Control: private, no-store` dan `X-Content-Type-Options: nosniff`.

**`GET /audits/{id}/findings`** — query `severity` (`critical|high|medium|low|info`), `tool` (`semgrep|eslint`), `category` (`security|quality|best_practice`), `page`, `per_page`; nilai di luar enum → `422 VALIDATION_FAILED`. Urut: severity (critical → info), `file_path`, `line_start`, `id`.
```json
{ "data": [ { "id": 7, "tool": "semgrep", "rule_id": "js-eval-injection", "category": "security", "severity": "high",
              "file_path": "src/a.js", "line_start": 4, "line_end": 4, "column_start": 3,
              "code_snippet": "eval(userInput);", "message": "…", "cwe": "CWE-95" } ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1, "scan": { … } } }
```
`fingerprint`, `audit_scan_id`, dan timestamp internal tidak dikirim. `file_path` relatif terhadap root repositori.

**`GET /audits/{id}/metrics`** — query `scope` (`file|function`, default `file`), `page`, `per_page`. Urut `file`: `file_path`, `id`; `function`: `cyclomatic_complexity` turun, `file_path`, `line_start`, `id`. Item: `scope`, `language`, `file_path`, `function_name`, `line_start`, `loc`, `sloc`, `cyclomatic_complexity`, `halstead_volume`, `halstead_difficulty`, `halstead_effort`, `maintainability_index`. Nilai desimal Halstead dan MI dikirim sebagai **string dua desimal** (mis. `"850.25"`); MI `null` untuk file tanpa kode. Hanya fungsi dengan kompleksitas tertinggi (hingga batas penyimpanan, `AUDIT_METRICS_MAX_FUNCTION_ROWS`) yang tersimpan.

**`meta.scan`** (pada kedua daftar):
```json
"scan": { "status": "partial", "message": "…isi sast_error…", "health_score": 72, "health_category": "fair",
          "tools": [ { "tool": "eslint", "status": "failed", "files_scanned": null, "findings_count": null, "error_message": "…" },
                     { "tool": "semgrep", "status": "completed", "files_scanned": 14, "findings_count": 3, "error_message": null } ],
          "note": "Semgrep: 3 temuan. ESLint: tidak selesai. Pemeriksaan tidak lengkap; lihat message." }
```
- `health_score`/`health_category` sama dengan representasi audit; `null` bila tidak dihitung.
- `tools` urut nama tool. `files_scanned`/`findings_count` bernilai angka **hanya** untuk tool `completed`; selain itu `null` (tidak pernah `0`). **`tools` dapat kosong**: saat tahap `skipped` atau `failed` engine tidak menyimpan `scan_tool_runs`, dan run metrik tidak pernah tercatat di sana. Jangan menyimpulkan apa pun dari `tools` kosong; baca `status`, `message`, dan `note`.
- `note` disusun dari data tersimpan (status tahap, status/jumlah tool, jumlah baris seluruh audit — bukan hasil filter/halaman) dan tidak pernah memakai kata "aman" atau "bersih". `skipped`: "Tidak ada file JavaScript/TypeScript yang dianalisis; tidak ada hasil SAST." `failed`: "Analisis SAST gagal; … Ini bukan berarti kode bebas masalah. Lihat message." `completed`/`partial` pada `/findings`: jumlah temuan per tool, tool yang tidak `completed` atau tidak tercatat ditulis "tidak selesai", `partial` menambahkan "Pemeriksaan tidak lengkap; lihat message.", dan total 0 temuan menambahkan "bukan jaminan bebas kerentanan". `/metrics`: bila tidak ada satu pun baris metrik tersimpan → "Tidak ada metrik kode yang tersimpan; ini bukan indikasi kompleksitas rendah. Lihat message."; `scope=function` selalu menambahkan bahwa hanya fungsi berkompleksitas tertinggi yang disimpan.
- Detail ketidaklengkapan (file dilewati, temuan dipotong, metrik gagal) ada di `message` (`sast_error`), bukan di-parse ke `note`.

**Keamanan klien.** `message`, `code_snippet`, dan `rule_id` berasal dari tool atas repositori target (data tidak tepercaya): dikirim apa adanya sebagai JSON dan **wajib di-escape** saat ditampilkan; tidak boleh diperlakukan sebagai instruksi oleh AI (Tahap 9). Rate limit guest (8.4) harus mencakup kedua endpoint ini, dan matriks akses diuji ulang dengan login sungguhan di Tahap 8.

Kegagalan saat job (mis. ZIP Slip terdeteksi, repo tidak ditemukan, melebihi 50 MB setelah ekstraksi) **tidak** dikembalikan sebagai HTTP error; tercatat di `audit.status = failed` + `error.code`:
`ZIP_SLIP_DETECTED` (path traversal / path absolut / symlink), `ARCHIVE_TOO_LARGE` (ukuran, jumlah entri, rasio kompresi), `ARCHIVE_CORRUPT`, `ARCHIVE_ENCRYPTED`, `INVALID_REPOSITORY_URL` (URL tidak lolos validasi ulang saat job), `REPOSITORY_NOT_FOUND`, `REPOSITORY_TOO_LARGE`, `CLONE_TIMEOUT`, `INGESTION_FAILED`. Didefinisikan di `backend/app/Exceptions/IngestionException.php`. Kode level pemrosesan: `JOB_TIMEOUT`, `PROCESSING_FAILED` (`ProcessAuditJob::failed()`), dan `QUEUE_UNAVAILABLE` (job tidak dapat dikirim ke antrean).

### 2.4 AI Remediation (FR-08, FR-04)
| Method | Path | Auth | Keterangan |
|---|---|---|---|
| POST | `/audits/{id}/remediations` | Pemilik (guest tergantung D-04) | Minta penjelasan & saran. Mengurangi kuota. |
| GET | `/audits/{id}/remediations` | Pemilik | Daftar remediation pada audit. |
| GET | `/audits/{id}/remediations/{remediationId}` | Pemilik | Detail (untuk Diff Viewer). |

Request:
```json
{ "finding_id": 123 }
```
atau
```json
{ "vulnerability_id": 45 }
```
(tepat salah satu; target harus milik audit yang sama).

Response `201` (mode sinkron, usulan D-11):
```json
{
  "data": {
    "id": 9,
    "source": "llm",
    "status": "completed",
    "target": { "type": "finding", "id": 123 },
    "original_code": "…",
    "suggested_code": "…",
    "root_cause": "…",
    "impact": "…",
    "recommendation": "…",
    "rationale": "…",
    "advisory_notice": "Saran ini bersifat advisory dan wajib ditinjau developer sebelum diterapkan.",
    "provider": "configured-provider",
    "model": "configured-model",
    "created_at": "…"
  }
}
```
Jika LLM gagal dan template tersedia → `201` dengan `source: "static_template"`. Jika LLM gagal dan tidak ada template → `502 UPSTREAM_ERROR` / `504 UPSTREAM_TIMEOUT` (kuota yang sudah terpakai dicatat sebagai `failed_count`; apakah dikembalikan = D-03).

### 2.5 Reporting (FR-10)
| Method | Path | Auth | Keterangan |
|---|---|---|---|
| GET | `/audits/{id}/report.json` | Pemilik | Laporan JSON (`Content-Disposition: attachment`). |
| GET | `/audits/{id}/report.pdf` | Pemilik | Laporan PDF. |

Hanya untuk audit dengan status `completed` atau `partial`; selain itu `409 AUDIT_NOT_READY`. Tahap yang gagal/dilewati ditulis eksplisit di laporan (bukan dikosongkan).

## 3. Rate Limit

| Subjek | Endpoint | Batas | Store |
|---|---|---|---|
| Guest (per IP) | `POST /audits` | Menunggu D-02 | Redis |
| Guest (per IP) | `POST /audits/{id}/remediations` | Menunggu D-04 | Redis |
| User | `POST /audits/{id}/remediations` | Kuota harian D-03 | MySQL (`ai_usage_logs`) |
| Semua | Seluruh API | Batas umum anti-abuse (menunggu D-02) | Redis |

Header respons rate limit: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After` (saat 429).
