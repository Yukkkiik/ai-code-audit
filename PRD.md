# PRD — AI Code Security Audit Platform

> Status dokumen: **Disetujui; diperbarui seiring implementasi.**
> Versi: 0.2 · Dibuat 2026-11-14 · Diperbarui 2026-11-25

## 1. Ringkasan Produk

AI Code Security Audit Platform adalah aplikasi web untuk memeriksa kualitas dan keamanan kode sebuah repositori secara terpusat. Pengguna cukup mengunggah ZIP atau memasukkan URL repositori Git publik, lalu sistem menjalankan:

1. **SAST** — analisis statis kode sumber JavaScript/TypeScript (Semgrep, ESLint) + metrik kualitas.
2. **SCA** — analisis kerentanan dependensi Node.js (npm) melalui OSV.dev.

> **Cakupan bahasa:** hanya **JavaScript/TypeScript** dan paket **Node.js (npm)**. Bahasa lain tidak dianalisis.
3. **AI Remediation & Reporting** — penjelasan edukatif, saran perbaikan, Diff Viewer, dan laporan PDF/JSON.

Pengguna tidak perlu memasang perkakas audit di perangkat masing-masing.

## 2. Target Pengguna

| Persona | Kebutuhan |
|---|---|
| Mahasiswa | Memahami kerentanan pada tugas/proyek, belajar dari penjelasan AI. |
| Developer pemula/menengah | Pemeriksaan cepat sebelum rilis, daftar dependensi rentan. |
| Guest | Mencoba layanan tanpa login, dengan batasan berbasis IP. |

## 3. Kebutuhan Fungsional

| ID | Nama | Deskripsi | Penanggung jawab | Prioritas |
|---|---|---|---|---|
| FR-01 | Guest Mode | Pemindaian tanpa login, dibatasi per alamat IP (Redis rate limiter). | Annisa | Tinggi |
| FR-02 | Google OAuth | Login menggunakan akun Google (OAuth 2.0). | Raihan | Tinggi |
| FR-03 | Session Management | Pengguna terdaftar mengelola sesi dan logout. | Raihan | Tinggi |
| FR-04 | AI Daily Quota | Kuota harian permintaan AI untuk pengguna terdaftar, reset berdasarkan tanggal. | Annisa | Sedang |
| FR-05 | Repository Ingestion | Input ZIP atau URL Git publik, maksimal 50 MB. | Yuki | Tinggi |
| FR-06 | SAST Scanning | Semgrep + ESLint untuk JS/TS, metrik + skor kesehatan. | Raihan | Tinggi |
| FR-07 | SCA Scanning | Kerentanan dependensi Node.js (npm) via OSV.dev REST API. | Yuki | Tinggi |
| FR-08 | AI Remediation | Penjelasan edukatif + rekomendasi perbaikan berdasarkan temuan. | Annisa | Sedang |
| FR-09 | Audit Dashboard & Diff Viewer | Rapor keamanan/kualitas + perbandingan kode asli vs rekomendasi. | Bersama + Annisa | Tinggi |
| FR-10 | Report Export | Unduh laporan PDF dan JSON. | Annisa | Sedang |

Fitur di luar daftar ini **tidak** dibangun tanpa persetujuan.

## 4. Kebutuhan Nonfungsional

| ID | Kategori | Kebutuhan |
|---|---|---|
| NFR-01 | Keamanan | Cegah ZIP Slip, Path Traversal, SSRF; tidak menjalankan kode/skrip repositori target; tidak memasang dependency repositori target. |
| NFR-02 | Keamanan | Tidak ada secret di source code; seluruh kredensial lewat `.env`. |
| NFR-03 | Keandalan | Kegagalan SCA tidak menggagalkan SAST (dan sebaliknya). Setiap tahap punya status yang jelas. |
| NFR-04 | Keandalan | Semua pemanggilan eksternal (OSV.dev, LLM, git clone) memiliki timeout dan error handling. |
| NFR-05 | Kinerja | Pemindaian berjalan di background (Laravel Queue + Redis); request HTTP tidak menunggu pemindaian selesai. |
| NFR-06 | Batas ukuran | Repositori maksimal 50 MB (ZIP terkompresi dan hasil ekstraksi/clone dibatasi). |
| NFR-07 | Kebersihan data | Direktori kerja sementara dihapus setelah audit selesai atau gagal. |
| NFR-08 | Kejujuran hasil | Hasil kosong tidak diklaim "aman"; data yang tidak diperiksa tidak ditampilkan seolah berhasil diperiksa. |
| NFR-09 | Privasi | IP guest tidak disimpan mentah di database (hanya hash bila diperlukan). |
| NFR-10 | Maintainability | Kode terstruktur per modul, diuji (unit/feature), dokumentasi diperbarui. |

## 5. Ruang Lingkup

### Termasuk
- Input ZIP dan URL Git publik (tanpa autentikasi).
- SAST: Semgrep dan ESLint, hanya untuk file JavaScript/TypeScript (`.js .jsx .mjs .cjs .ts .tsx .mts .cts`).
- Metrik: Cyclomatic Complexity, Halstead, LOC, Maintainability Index (JS/TS).
- Skor kesehatan 0–100 + kategori (tanpa memasukkan hasil SCA).
- SCA: paket Node.js/npm saja (`package.json`, `package-lock.json` v2 & v3).
- AI Remediation advisory + fallback static rule template.
- Guest Mode, Google OAuth, kuota AI harian.
- Dashboard, riwayat audit, Diff Viewer, laporan PDF/JSON.

### Tidak Termasuk
- Repositori privat / Git berautentikasi.
- Analisis SAST untuk bahasa selain JavaScript/TypeScript (Python, PHP, Java, dll.) — file tersebut diabaikan.
- Analisis file template framework (`.vue`, `.svelte`, `.astro`) — diabaikan.
- Ekosistem selain npm untuk SCA (pip, composer, dll.), termasuk `yarn.lock` dan `pnpm-lock.yaml`.
- Bandit (alat khusus Python) — dikeluarkan dari stack karena di luar cakupan bahasa.
- Instalasi dependency, auto-fix dependency, Pull Request otomatis.
- License auditing, reachability analysis.
- Penerapan otomatis kode rekomendasi AI ke repositori.
- Isolasi sandbox penuh (container/VM) — direktori sementara **bukan** isolasi keamanan penuh.

## 6. Batasan

- Ukuran repositori maksimal 50 MB.
- Lockfile npm yang didukung hanya versi 2 dan 3 (`lockfileVersion` 1 ditandai tidak didukung).
- Versi dari rentang (`^1.2.3`, `~1.2.3`) tidak ditebak.
- Provider LLM: DeepSeek; model belum ditetapkan (D-05).
- Batas audit: guest 2, user terdaftar 10; kuota AI user 10 per hari. Batas audit berlaku per hari (D-02); panggilan AI yang gagal mengembalikan kuota (D-03). Guest tidak boleh memakai AI (D-04).

## 7. Prioritas Implementasi

Backend-first, database-first, satu fitur per sesi. Urutan lengkap ada di `IMPLEMENTATION_ROADMAP.md`.

## 8. Kriteria Keberhasilan Produk

- Pengguna dapat menyelesaikan alur: input repo → status audit → hasil SAST/SCA → saran AI → unduh laporan.
- Setiap temuan dapat ditelusuri ke tool, rule, file, dan baris.
- Setiap kegagalan tahap terlihat jelas di status dan laporan.
