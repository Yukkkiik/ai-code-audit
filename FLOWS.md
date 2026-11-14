# FLOWS — Alur End-to-End & Penanganan Error

> Status dokumen: **DRAFT — menunggu persetujuan tim**

## 1. State Machine Audit

```text
                ┌──────────── gagal ingestion ─────────────┐
                │                                          ▼
 queued ──▶ ingesting ──▶ scanning ──▶ completed        failed
                              │
                              ├──▶ partial   (SAST atau SCA gagal, yang lain berhasil)
                              └──▶ failed    (SAST dan SCA sama-sama gagal)
```

Status tahap (`sast_status`, `sca_status`): `pending → running → completed | partial | failed | skipped`.

Aturan status akhir audit:
| SAST | SCA | Audit |
|---|---|---|
| completed/skipped | completed/skipped | `completed` |
| completed / partial | failed / partial | `partial` |
| failed | completed / partial | `partial` |
| failed | skipped | `failed` |
| skipped | failed | `failed` |
| failed | failed | `failed` |
| (ingestion gagal) | – | `failed` (kedua tahap `skipped`) |

Aturan umum (diimplementasikan di `AuditStatusManager::resolveFinalStatus`): tanpa tahap `failed`/`partial` → `completed`; ada tahap yang menghasilkan data (`completed`/`partial`) → `partial`; selain itu → `failed`. Revisi 2026-11-15: kombinasi `failed` + `skipped` kini `failed` (sebelumnya tertulis `partial`), karena tahap `skipped` tidak menghasilkan data sehingga tidak memenuhi definisi `partial`.

Transisi status hanya dilakukan melalui `AuditStatusManager` (row lock + validasi alur); transisi yang melanggar alur melempar `InvalidAuditTransitionException`.

Catatan: jika keduanya `skipped` (tidak ada file yang didukung sama sekali), audit tetap `completed` tetapi dashboard dan laporan menyatakan "tidak ada yang diperiksa", bukan "aman".

## 2. Alur Utama (Happy Path)

1. **Akses** — pengguna membuka web sebagai Guest atau login Google.
2. **Input** — upload ZIP atau isi URL Git.
3. **Validasi request** (sinkron, di controller/Form Request):
   - ZIP: ekstensi, MIME, magic bytes `PK\x03\x04`, ukuran ≤ 50 MB.
   - Git: skema `https`, format URL, host valid, tidak ada kredensial di URL.
   - Guest: cek rate limit IP.
4. **Buat audit** — simpan `audit_scans` (`queued`), simpan ZIP ke `storage/app/private/uploads/{public_id}.zip`, dispatch `ProcessAuditJob`. Response `202`.
5. **Worker — Ingestion** (`ingesting`):
   - Buat workspace `storage/app/private/workspaces/{public_id}/`.
   - ZIP: cek setiap entri (path, symlink, ukuran total hasil ekstraksi, jumlah file, rasio kompresi) **sebelum** menulis; ekstrak.
   - Git: resolve DNS & tolak IP privat, `git clone --depth 1 --no-tags` dengan timeout, hooks nonaktif, tanpa submodule/LFS; ukur ukuran hasil.
6. **Worker — SAST** (`scanning`, `sast_status=running`): jalankan Semgrep dan ESLint pada file JavaScript/TypeScript (file bahasa lain diabaikan) → normalisasi → simpan `scan_findings`; hitung metrik → `code_metrics`; hitung skor kesehatan.
7. **Worker — SCA** (`sca_status=running`): cari `package.json`/`package-lock.json` (abaikan `node_modules`, `vendor`, `.git`) → parse → simpan `dependencies` → `querybatch` OSV untuk versi pasti → ambil detail advisori → simpan `vulnerabilities`.
8. **Finalisasi** — tentukan status akhir, isi `finished_at`.
9. **Cleanup** — hapus workspace & ZIP (di `finally`, juga saat gagal).
10. **Hasil** — frontend polling `GET /audits/{id}` hingga status final, lalu menampilkan dashboard.
11. **AI** — pengguna memilih temuan → `POST /remediations` → cek kuota → prompt → LLM → validasi → simpan → Diff Viewer.
12. **Laporan** — unduh PDF/JSON.

## 3. Alternative Flow & Edge Case

### Ingestion
| Kondisi | Penanganan |
|---|---|
| File bukan ZIP / ZIP rusak | 422 `VALIDATION_FAILED` (`details.file`, cek magic bytes saat request) atau `failed` + `ARCHIVE_CORRUPT` (job). |
| ZIP terenkripsi (berpassword) | `failed` + `ARCHIVE_ENCRYPTED`. |
| ZIP > 50 MB | 413 di request (juga dibatasi `upload_max_filesize`/`post_max_size` PHP). |
| Hasil ekstraksi > 50 MB / zip bomb | Hentikan ekstraksi, `failed` + `ARCHIVE_TOO_LARGE`. |
| Entri `../`, path absolut, symlink | `failed` + `ZIP_SLIP_DETECTED`, tidak ada file ditulis di luar workspace. |
| URL ke IP privat/loopback/metadata (`169.254.169.254`) | 422 `VALIDATION_FAILED` (`details.repository_url`), atau `failed` + `INVALID_REPOSITORY_URL` di job jika DNS berubah — URL divalidasi ulang tepat sebelum clone. |
| Repo di-rename/dipindah (HTTP redirect) | `failed` + `REPOSITORY_NOT_FOUND`: redirect dinonaktifkan agar host tujuan tidak lolos dari allowlist. |
| Repo privat / tidak ada | `failed` + `REPOSITORY_NOT_FOUND` (git dijalankan dengan `GIT_TERMINAL_PROMPT=0` agar tidak menunggu kredensial). |
| Clone melebihi waktu | `failed` + `CLONE_TIMEOUT`. |
| Repo kosong | Ingestion berhasil; SAST & SCA `skipped`. |

### SAST
| Kondisi | Penanganan |
|---|---|
| Tool tidak terpasang | Tool itu dicatat gagal di `scan_tool_runs`; tool lain tetap berjalan; `sast_status=partial`. |
| Tool timeout / exit code error | Dicatat; output parsial tidak dianggap lengkap. |
| Output JSON tidak valid | Tool dianggap gagal (tidak di-swallow). |
| Tidak ada file JavaScript/TypeScript | `sast_status=skipped`, skor NULL. Dashboard menyatakan "tidak ada file JS/TS yang dapat dianalisis". |
| Repo campuran (mis. JS + Python) | Hanya file JS/TS yang dianalisis; laporan menyebutkan bahwa bahasa lain tidak diperiksa. |
| Repo berisi `.eslintrc`/`eslint.config.js` | **Diabaikan**; hanya config platform yang dipakai. |

### SCA
| Kondisi | Penanganan |
|---|---|
| Tidak ada `package.json` | `sca_status=skipped`. |
| Hanya ada `yarn.lock` / `pnpm-lock.yaml` | Lockfile tersebut tidak dibaca; diperlakukan sebagai "tanpa lockfile" (hanya dependensi langsung berversi pasti). |
| `package.json` tanpa lockfile | Hanya dependensi langsung dengan versi pasti; rentang → `unresolved` (`range_without_lockfile`). |
| Lockfile v1 | Dependensi dari lockfile ditandai `unsupported_lockfile`; fallback ke aturan tanpa lockfile. |
| Sumber git/file/link/tarball | `unresolved` (`non_registry_source`), tidak dikirim ke OSV. |
| JSON rusak | Manifest itu dicatat gagal; manifest lain tetap diproses → `partial`. |
| OSV timeout / 5xx | Retry terbatas (backoff); tetap gagal → dependency `osv_status=failed`, `sca_status=partial` atau `failed`. |
| OSV respons tidak valid / jumlah hasil ≠ jumlah query | Batch dianggap gagal (tidak dipetakan sembarangan). |
| Hasil OSV kosong | Ditampilkan "tidak ditemukan advisori di OSV untuk versi ini", **bukan** "aman". |

### AI
| Kondisi | Penanganan |
|---|---|
| Kuota habis | 429 `AI_QUOTA_EXCEEDED`, LLM tidak dipanggil. |
| LLM timeout/error | Fallback template jika ada; jika tidak → 502/504; status `failed` tersimpan. |
| Output tidak sesuai skema / klaim tidak didukung | `rejected_validation`; fallback template jika ada. |
| Kode repo berisi teks "ignore previous instructions" | Diperlakukan sebagai data (dibungkus delimiter, tidak pernah menjadi system prompt). |

### Auth
| Kondisi | Penanganan |
|---|---|
| `state` OAuth tidak cocok | Tolak, redirect `login?error=oauth_failed`. |
| User membatalkan di Google | Redirect `login?error=oauth_cancelled`. |
| Email Google belum terverifikasi | Tolak (menunggu D-15). |

## 4. Retry, Timeout, Failed Jobs

| Item | Usulan awal (dapat dikonfigurasi di `config/audit.php`) |
|---|---|
| `ProcessAuditJob` `tries` | 1 untuk pipeline (hindari pemindaian ganda); retry dilakukan per panggilan HTTP eksternal. |
| Job `timeout` | Ditentukan saat TAHAP 5 (harus < `retry_after` Redis queue). |
| Timeout per tool SAST | Ditentukan saat TAHAP 6. |
| OSV.dev | timeout per request + maksimal 2 retry dengan backoff untuk 5xx/timeout. |
| LLM | timeout per request; tanpa retry otomatis berulang (biaya). |
| Job gagal tak terduga | Masuk `failed_jobs`; method `failed()` menandai audit `failed` + cleanup workspace. |

## 5. Penanganan Error (prinsip)

- Tidak ada `catch` kosong. Setiap exception dicatat (log) dan diterjemahkan ke status/kode error yang jelas.
- Pesan ke pengguna aman (tanpa path server, stack trace, atau isi secret).
- Kegagalan satu subsistem tidak membatalkan subsistem lain (SAST ⟂ SCA).
- Cleanup workspace selalu dijalankan (`finally` + handler `failed()`).
