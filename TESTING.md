# TESTING — Strategi Pengujian

> Status dokumen: **DRAFT — menunggu persetujuan tim**

## 1. Tingkatan Pengujian

| Tingkat | Alat | Cakupan |
|---|---|---|
| Unit | PHPUnit/Pest (bawaan Laravel, dipilih saat inisialisasi — D-16) | Parser, normalizer, kalkulator metrik/skor, validator URL, validator output AI. |
| Feature | Laravel HTTP tests (`RefreshDatabase`) | Endpoint API, validasi, otorisasi, kode error, status audit. |
| Integration | Laravel tests + `Http::fake()` / fixture | Job pipeline, OSV client, LLM client, eksekusi tool SAST nyata pada fixture kecil. |
| Security | Feature/unit test khusus | ZIP Slip, symlink, zip bomb, SSRF, akses audit milik orang lain, prompt injection. |
| Frontend | Lint + type-check (`tsc --noEmit`); test komponen menunggu D-17 | Halaman dan komponen. |
| End-to-End | Manual terstruktur (TAHAP 11); otomatisasi menunggu keputusan | Alur penuh dari input hingga laporan. |

Database pengujian: MySQL terpisah `audit_app_testing` (diatur di `backend/phpunit.xml`) agar perilaku CHECK/JSON/unique sama dengan produksi. `DatabaseConnectionTest` memastikan test tidak pernah berjalan di database tanpa akhiran `_testing`.

**Pengaman database test:** `tests/TestCase.php` menghentikan test yang memakai `RefreshDatabase`, `DatabaseMigrations`, atau `DatabaseTruncation` **sebelum** tabel dihapus jika nama database tidak berakhiran `_testing`. Pengaman dipasang di `setUpTraits()` — bukan di hook `beforeRefreshingDatabase()`, karena method trait menimpa method kelas induk sehingga hook tersebut tidak pernah terpanggil.

Redis pengujian: DB 14 (default/session) dan 15 (cache), diatur di `backend/phpunit.xml`. `RedisConnectionTest` memastikan test tidak menulis ke DB 0/1. Test **tidak boleh** memakai `FLUSHDB`/`FLUSHALL` — Redis lokal dapat dipakai aplikasi lain; hapus hanya key milik test.

Layanan eksternal (OSV.dev, LLM, Google) di-fake pada test otomatis. Pengujian dengan layanan nyata hanya dilakukan manual dan dicatat di laporan.

## 2. Skenario Uji per Modul

### Foundation
- `GET /api/v1/health` → 200 saat DB & Redis hidup; 503 saat salah satu mati.
- Queue: job yang di-dispatch ke koneksi `redis` tersimpan di antrean dan baru diproses saat `queue:work` berjalan (`QueueConnectionTest`, job khusus test `Tests\Support\Jobs\QueueProbeJob`).

### Database Foundation
- `tests/Feature/Database/*`: struktur setiap tabel (kolom, tipe, nullable, default, index, foreign key + `ON DELETE`, CHECK) dibandingkan dengan `DATABASE.md`; memakai query langsung (tanpa model) melalui helper `Tests\Support\InteractsWithSchema`.
- Perilaku constraint: unique (`google_id`, `email`, `public_id`, fingerprint per audit, tool per audit, advisori per dependency, usage per user per hari), CHECK pemilik audit, CHECK tepat satu target AI.
- Cascade: hapus user → audit, refactoring, usage log ikut terhapus; hapus audit → seluruh hasil terhapus; hapus requester audit lain → `requested_by_user_id` menjadi NULL.
- `DatabaseSchemaTest`: hanya tabel di `DATABASE.md` yang ada; semua InnoDB + `utf8mb4_unicode_ci`.
- `tests/Feature/Models/*`: ULID `public_id`, field tersembunyi, cast enum/JSON/tanggal/decimal, relasi dua arah, factory state (`ownedBy`, `fromGit`, `unresolved`, `forVulnerability`).
- `QueueConnectionTest`: job gagal tercatat di `failed_jobs`.

### Audit Management
- Diimplementasikan di `tests/Feature/Audit/AuditScanCreatorTest.php`, `AuditStatusManagerTest.php`, `tests/Feature/Api/V1/ShowAuditTest.php`, `ListAuditsTest.php`.
- Guest token hanya dikembalikan sekali; database menyimpan hash SHA-256; verifikasi constant-time.
- Aturan status akhir diuji untuk 10 kombinasi status SAST/SCA (data provider); transisi ilegal dan model usang ditolak.
- Daftar audit memakai jumlah query konstan (≤ 2) walaupun ada banyak audit & temuan.
- Membuat audit menghasilkan `public_id` ULID dan status `queued`.
- Guest hanya bisa membaca audit dengan `X-Guest-Token` yang benar; token salah → 404.
- User tidak bisa membaca audit user lain → 404.
- Riwayat hanya berisi audit milik user, urut terbaru.

### Repository Ingestion
- Diimplementasikan di `tests/Feature/Ingestion/*`, `tests/Unit/Ingestion/*`, `tests/Feature/Api/V1/StoreAuditTest.php`. Helper: `BuildsZipArchives` (ZIP berbahaya), `FakeGitBinary` (pengganti `git` tanpa jaringan, mencatat argumen & environment), `FakeHostResolver` (DNS palsu), `UsesTemporaryAuditStorage`.
- Clone test memastikan opsi pengamanan git terpasang, URL berada setelah `--`, dan secret aplikasi (mis. `DB_PASSWORD`, `APP_KEY`) **tidak** sampai ke proses git.
- ZIP valid diekstrak ke workspace audit sendiri.
- ZIP berisi `../evil.txt`, `/etc/passwd`, symlink → ditolak, tidak ada file di luar workspace.
- ZIP bomb (rasio tinggi / ukuran ekstrak > 50 MB) → `ARCHIVE_TOO_LARGE`.
- File bukan ZIP berganti ekstensi `.zip` → ditolak.
- URL `http://`, `https://user:pass@host`, `https://127.0.0.1/…`, `https://10.0.0.1/…`, `https://169.254.169.254/`, host yang resolve ke IP privat → ditolak.
- Workspace terhapus setelah sukses **dan** setelah gagal.
- `node_modules`, `vendor`, `.git` diabaikan saat traversal.

### Background Processing
- `tests/Feature/Audit/ProcessAuditJobTest.php`: alur ZIP & Git end-to-end (termasuk worker Redis sungguhan), runner default jujur (`failed` "belum tersedia"), exception satu tahap tidak menggagalkan tahap lain, ingestion gagal, job duplikat, audit terhapus, `failed()` untuk timeout & error tak terduga, kebijakan `tries`/timeout/`retry_after`, payload hanya ID.
- `StoreAuditTest`: setiap audit yang diterima men-dispatch tepat satu job; request ditolak tidak men-dispatch; Redis mati → audit `QUEUE_UNAVAILABLE`.
- Engine tahap di test diganti `Tests\Support\RecordingStageRunner`.

### SAST
- Output contoh Semgrep/ESLint (fixture JSON) dinormalisasi ke format seragam.
- Repo campuran: file `.py`, `.php`, `.vue` tidak dikirim ke tool; hanya `.js .jsx .mjs .cjs .ts .tsx .mts .cts`.
- Repo tanpa file JS/TS → `sast_status=skipped`, skor NULL.
- Pemetaan severity per tool sesuai tabel di skill `sast-ast-rules`.
- Config ESLint milik repo tidak dimuat.
- Tool gagal → tahap `partial`, tool lain tetap tersimpan.
- Kalkulasi CC, Halstead, MI pada fungsi contoh dengan nilai yang dihitung manual.
- Skor kesehatan berada di 0–100 dan tidak berubah oleh data SCA.

### SCA
- Pencarian manifest (`ManifestFinder`): `package.json` + lockfile sejajar, monorepo, `node_modules`/`vendor`/`.git` dan symlink diabaikan, lockfile tanpa `package.json` diabaikan, `yarn.lock`/`pnpm-lock.yaml` tidak dicari.
- Parser `package.json` (`PackageJsonParser`): klasifikasi di bawah; alias `npm:` dengan versi pasti memakai nama asli; JSON rusak/terlalu besar/terlalu dalam/symlink/nama >214 karakter → `ManifestParseException` tanpa path server.
- Parser `package-lock.json` (`PackageLockParser`) dengan fixture v2/v3 tulisan tangan (`tests/Fixtures/Sca/`, bukan hasil `npm install`): hitungan direct/transitive/dev, nested path terpisah (`lock_path`), scope, alias, workspace (direct dari root + workspace), tarball registry harus cocok nama & versi, `link`/git/file → `non_registry_source`, v1/versi tak dikenal → `unsupported`, batas ukuran/depth/symlink/realpath di luar root, tanpa request jaringan. Satu test memakai lockfile npm asli (`package-lock-real.json`, disalin dari paket `laravel/framework`, lisensi MIT; v3, 134 paket): tidak melempar, jumlah > 0, tidak ada non-semver yang `exact`. Lockfile dari repo pengguna sendiri belum ada (dapat ditambahkan di samping).
- Penyimpanan dependensi: `DependencyCollector` (lockfile didahulukan, fallback v1/rusak/besar, gap-fill, workspace tercakup lockfile induk, dedup + prioritas scope, batas kolom, kegagalan tanpa path server) dan `DependencyStore` (kolom dan enum, chunk 500, rollback, idempoten, tidak menyentuh audit lain, cascade ke vulnerabilities, karakter khusus). Keduanya belum dipakai runner (7.6).
- OSV client (`OsvClient`, `Http::fake` + `preventStrayRequests`, `Sleep::fake`): bentuk request, pemetaan per posisi, dedup, batch 1000, retry 5xx/429/koneksi dengan backoff, tanpa retry untuk 4xx/JSON rusak/respons tidak valid, redirect tidak diikuti, validasi struktur, pagination (merge, hanya query ber-token, token berulang per query, tak berujung), kegagalan per batch tanpa hasil sebagian, anggaran waktu (habis di tengah, backoff melewati sisa), alasan gagal berupa enum, log tanpa body/nama paket, exception tak terduga tidak ditelan, binding menolak base URL non-https di luar testing. Belum diuji terhadap OSV nyata.
- package.json saja: versi pasti (`1.2.3`) tercatat `exact`; `^1.2.3`, `~1.2.3`, `*`, `latest` → `unresolved`.
- lockfile v2 dan v3: direct vs transitive benar, `dev: true` → `development`, nested `node_modules/a/node_modules/b` tercatat terpisah.
- Sumber `git+`, `file:`, `link:` → `non_registry_source`.
- Lockfile v1 → `unsupported_lockfile`; lockfile rusak/terlalu besar → fallback dengan rentang `lockfile_unreadable` (non-registry dan versi pasti tidak berubah).
- `yarn.lock` / `pnpm-lock.yaml` diabaikan (tidak diparse).
- Komponen `unresolved` tidak pernah dikirim ke OSV (assert pada `Http::fake`).
- OSV: respons normal, kosong, timeout, 500, JSON rusak, jumlah hasil tidak cocok, pagination `next_page_token`.
- SCA gagal → SAST tetap tersimpan, audit `partial`.

### Auth & Access Control
- Callback OAuth membuat user baru / memperbarui user lama berdasarkan `google_id`.
- `state` salah ditolak.
- Logout menginvalidasi session.
- Rate limit guest: permintaan ke-(N+1) → 429 + `Retry-After`.
- Kuota AI: permintaan bersamaan tidak melebihi batas (uji dengan beberapa proses/transaksi paralel).
- Kuota reset pada `usage_date` berikutnya.

### AI Remediation
- Prompt hanya memuat data temuan; kode repo dibungkus delimiter sebagai data.
- Output tidak sesuai skema → `rejected_validation`.
- Output yang menyebut versi aman yang tidak ada di `fixed_versions` OSV → ditolak.
- LLM timeout → fallback template bila ada.
- Tidak ada file proyek yang diubah dan tidak ada kode yang dieksekusi.

### Reporting
- JSON memuat identitas audit, status setiap tahap, SAST, SCA, metrik, skor, rekomendasi.
- Tahap gagal tertulis "gagal diperiksa", bukan kosong.
- PDF dapat dibuat untuk audit `partial`.
- Audit belum selesai → 409.

## 3. Perintah Pengujian (diisi saat setiap tahap selesai)

```bash
# Backend
cd backend && php artisan test
cd backend && ./vendor/bin/pint --test

# Frontend
cd frontend && npm run lint
cd frontend && npx tsc --noEmit
```

## 4. Definition of Done (per fitur)

- [ ] Sesuai kebutuhan dan plan yang disetujui.
- [ ] Logic berjalan sesuai `FLOWS.md`.
- [ ] Data tersimpan benar di database (jika relevan) — dibuktikan dengan test.
- [ ] Validasi input dan error handling tersedia (tanpa `catch` kosong).
- [ ] Response sesuai `API_CONTRACT.md`.
- [ ] Test baru lulus, test lama tetap lulus (output aktual dilampirkan).
- [ ] Linter/formatter dan type-check (frontend) bersih.
- [ ] Tidak ada secret di source code.
- [ ] Dokumentasi terkait diperbarui.
- [ ] Cara mencoba dijelaskan.
- [ ] Diperiksa dan disetujui pengguna.
