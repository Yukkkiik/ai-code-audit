---
name: sca-dependency-audit
description: Gunakan saat mengerjakan SCA Engine — pencarian manifest, parser package.json/package-lock.json v2-v3, ekstraksi dependensi, integrasi OSV.dev querybatch, dan penyimpanan kerentanan di proyek AI Code Security Audit Platform.
---

# SCA Dependency Audit

## Tujuan
Mengekstrak dependensi npm secara akurat tanpa menebak versi, lalu memetakan kerentanannya melalui OSV.dev dengan penanganan kegagalan yang jujur.

## Kapan digunakan
TAHAP 7 (7.1–7.6) dan perubahan apa pun pada `app/Services/Sca/`.

## Input yang diperlukan
- Workspace audit yang sudah aman.
- `config/audit.php`: timeout & retry OSV, ukuran batch.

## Langkah kerja
1. **Cari manifest** secara rekursif: `package.json` dan `package-lock.json` sejajar; abaikan `node_modules/`, `vendor/`, `.git/`. Jangan mengikuti symlink.
2. **Parse JSON** dengan batas ukuran file; JSON rusak → manifest itu gagal, lanjut manifest lain.
3. **Dengan lockfile** (`lockfileVersion` 2 atau 3):
   - Gunakan objek `packages`. Key `""` = root project (bukan dependency).
   - Key `node_modules/<name>` (tanpa nested) yang tercantum di `dependencies`/`devDependencies`/`optionalDependencies`/`peerDependencies` root → `direct`; selainnya → `transitive`. Nama paket diambil dari segmen terakhir setelah `node_modules/` (perhatikan scope `@scope/name`), atau dari field `name` bila ada.
   - `dev: true` → `development`; `optional: true` → `optional`; `devOptional: true` → `development`; peer → `peer`; selain itu `production`.
   - `link: true`, `resolved` berawalan `git+`, `file:`, atau tarball non-registry → `unresolved` (`non_registry_source`), kecuali versi registry pasti tersedia.
   - Tidak ada `version` → `unresolved` (`missing_version`).
4. **lockfileVersion 1 / tidak dikenal** → tandai `unsupported_lockfile`; perlakukan seperti tanpa lockfile.
5. **Tanpa lockfile**: hanya dependensi langsung dari `package.json`.
   - Versi pasti semver (`1.2.3`, `1.2.3-beta.1`, opsional diawali `=` / `v`) → `exact`.
   - `^`, `~`, `>`, `<`, `*`, `x`, `||`, rentang dengan spasi, `latest`, tag → `unresolved` (`range_without_lockfile`).
   - `git+…`, `github:`, `file:`, `link:`, `http(s)://`, `npm:` alias tanpa versi pasti → `unresolved` (`non_registry_source`).
6. **Simpan** ke `dependencies` (deduplikasi di aplikasi).
7. **OSV.dev**:
   - `POST https://api.osv.dev/v1/querybatch` dengan `{"queries":[{"package":{"name":…,"ecosystem":"npm"},"version":…}]}` — hanya `version_status = exact`.
   - Pecah batch sesuai batas API (≤ 1000 query per request), dukung `next_page_token`.
   - Validasi: `results` harus array dengan panjang sama dengan `queries`; jika tidak → batch gagal.
   - Ambil detail tiap ID unik via `GET /v1/vulns/{id}` (querybatch hanya mengembalikan `id` & `modified`).
   - Simpan `osv_id`, `aliases`, `summary`, `details`, `severity` mentah, `affected` (rentang untuk paket npm terkait), `fixed` dari `ranges[].events`, `references` OSV (disimpan ke kolom `reference_urls`), `published`, `modified`.
   - Timeout + retry terbatas dengan backoff untuk timeout/5xx/429. 4xx lain → tidak di-retry.
8. Set `osv_status` per dependency dan `sca_status` (`completed`/`partial`/`failed`/`skipped`).

## Larangan
- Menjalankan `npm install`, `npm ls`, script `package.json`, atau membaca `node_modules`.
- Menebak versi dari rentang (`^1.2.3` ≠ `1.2.3`).
- Mengirim komponen `unresolved` ke OSV.
- Mengarang versi perbaikan atau severity yang tidak ada di data OSV.
- Mengartikan hasil kosong sebagai "aman" — gunakan "tidak ditemukan advisori pada OSV.dev".
- Membiarkan kegagalan SCA menggagalkan SAST.
- Menambah ekosistem selain npm, license audit, auto-fix, PR otomatis, reachability.

## Validasi
- Test fixture lockfile v2 & v3 nyata: jumlah direct/transitive/dev benar, nested path terpisah.
- Test package.json tanpa lockfile untuk setiap jenis spesifikasi versi.
- `Http::fake` OSV: normal, kosong, timeout, 5xx, JSON rusak, panjang hasil tidak cocok, pagination.
- Assert tidak ada request OSV untuk komponen `unresolved`.

## Output yang diharapkan
Baris `dependencies` lengkap dengan status kepastian versi, baris `vulnerabilities` dari data OSV, dan `sca_status` yang mencerminkan kegagalan sebagian secara jujur.
