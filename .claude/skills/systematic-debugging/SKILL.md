---
name: systematic-debugging
description: Gunakan saat menemukan bug, test gagal, atau perilaku tak terduga di proyek AI Code Security Audit Platform — temukan akar masalah dengan bukti sebelum mengubah kode.
---

# Systematic Debugging

## Tujuan
Memperbaiki masalah berdasarkan akar penyebab yang terbukti, bukan tebakan atau tambalan.

## Kapan digunakan
- Test gagal, error runtime, job gagal, hasil scan tidak sesuai, integrasi eksternal error.

## Input yang diperlukan
- Pesan error lengkap, log (`backend/storage/logs/`), `failed_jobs`, output test.
- Langkah reproduksi.

## Langkah kerja
1. **Reproduksi** — buat masalah terjadi secara konsisten (idealnya sebagai test gagal).
2. **Kumpulkan bukti** — baca error/stack trace utuh, log, data DB terkait.
3. **Persempit** — tentukan lapisan: request/validasi, service, job/queue, tool eksternal, DB, frontend.
4. **Hipotesis** — tulis satu hipotesis yang dapat diuji; uji; catat hasilnya.
5. **Perbaiki akar masalah** — perubahan minimal pada penyebab, bukan gejala.
6. **Verifikasi** — test reproduksi lulus + seluruh suite lulus.
7. Jika 3 hipotesis gagal, berhenti dan laporkan temuan kepada pengguna.

## Larangan
- `try-catch` kosong atau menelan exception untuk menyembunyikan error.
- Menonaktifkan validasi/keamanan untuk "membuat jalan".
- Mengubah banyak hal sekaligus tanpa tahu mana yang memperbaiki.
- Memperbaiki masalah di luar fitur yang sedang dikerjakan tanpa izin (laporkan saja).

## Validasi
- Ada test yang gagal sebelum dan lulus sesudah perbaikan.
- Penjelasan akar masalah didukung bukti.

## Output yang diharapkan
Ringkasan: gejala → bukti → akar masalah → perbaikan → bukti verifikasi.
