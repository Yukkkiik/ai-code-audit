# API CONTRACT — Versi Awal

> Status dokumen: **DRAFT — menunggu persetujuan tim**
> Base URL: `{BACKEND_URL}/api/v1` · Format: JSON (`Content-Type: application/json`) kecuali upload (`multipart/form-data`) dan PDF.

## 1. Konvensi

### Response sukses
```json
{ "data": { ... }, "meta": { ... } }
```
`meta` hanya untuk pagination (`current_page`, `per_page`, `total`, `last_page`).

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
| GET | `/audits/{id}/findings` | Pemilik | Temuan SAST. Query: `severity`, `tool`, `category`, `page`, `per_page` (≤100). |
| GET | `/audits/{id}/metrics` | Pemilik | Metrik kode. Query: `scope=file|function`, `page`. |
| GET | `/audits/{id}/dependencies` | Pemilik | Daftar dependency. Query: `scope`, `relation`, `version_status`, `page`. |
| GET | `/audits/{id}/vulnerabilities` | Pemilik | Kerentanan dependency. Query: `severity`, `page`. |

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

Status implementasi: endpoint aktif. Audit dibuat berstatus `queued`, lalu `ProcessAuditJob` (payload hanya ID audit) di-dispatch ke antrean Redis dan diproses worker. Tahap SCA kini dijalankan engine asli (TAHAP 7); hingga TAHAP 6 selesai, tahap SAST tercatat `failed` dengan pesan "Engine SAST belum tersedia di versi platform ini." Endpoint `GET /audits/{id}/dependencies` dan `/vulnerabilities` belum dibangun (item 7.7).

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
