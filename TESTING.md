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

Layanan eksternal (OSV.dev, LLM, Google) di-fake pada test otomatis. Pengujian dengan layanan nyata hanya dilakukan manual dan dicatat di laporan.

## 2. Skenario Uji per Modul

### Foundation
- `GET /api/v1/health` → 200 saat DB & Redis hidup; 503 saat salah satu mati.

### Audit Management
- Membuat audit menghasilkan `public_id` ULID dan status `queued`.
- Guest hanya bisa membaca audit dengan `X-Guest-Token` yang benar; token salah → 404.
- User tidak bisa membaca audit user lain → 404.
- Riwayat hanya berisi audit milik user, urut terbaru.

### Repository Ingestion
- ZIP valid diekstrak ke workspace audit sendiri.
- ZIP berisi `../evil.txt`, `/etc/passwd`, symlink → ditolak, tidak ada file di luar workspace.
- ZIP bomb (rasio tinggi / ukuran ekstrak > 50 MB) → `ARCHIVE_TOO_LARGE`.
- File bukan ZIP berganti ekstensi `.zip` → ditolak.
- URL `http://`, `https://user:pass@host`, `https://127.0.0.1/…`, `https://10.0.0.1/…`, `https://169.254.169.254/`, host yang resolve ke IP privat → ditolak.
- Workspace terhapus setelah sukses **dan** setelah gagal.
- `node_modules`, `vendor`, `.git` diabaikan saat traversal.

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
- package.json saja: versi pasti (`1.2.3`) tercatat `exact`; `^1.2.3`, `~1.2.3`, `*`, `latest` → `unresolved`.
- lockfile v2 dan v3: direct vs transitive benar, `dev: true` → `development`, nested `node_modules/a/node_modules/b` tercatat terpisah.
- Sumber `git+`, `file:`, `link:` → `non_registry_source`.
- Lockfile v1 → `unsupported_lockfile`.
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
