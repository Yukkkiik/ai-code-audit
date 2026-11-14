---
name: requesting-code-review
description: Gunakan setelah implementasi fitur selesai dan sebelum melapor di proyek AI Code Security Audit Platform — self-review perubahan terhadap plan, keamanan, dan kontrak, lalu susun laporan untuk review pengguna.
---

# Requesting Code Review

## Tujuan
Menyerahkan hasil satu fitur yang sudah diperiksa sendiri secara jujur, dengan laporan standar, lalu berhenti.

## Kapan digunakan
Akhir FASE B, setelah semua test dijalankan.

## Input yang diperlukan
- Plan yang disetujui.
- `git diff` / `git status`.
- Output test, linter, type-check.

## Langkah kerja
1. Bandingkan diff dengan plan: tidak ada file di luar cakupan, tidak ada fitur tambahan.
2. Checklist keamanan: secret, validasi input, otorisasi, path traversal, SSRF, eksekusi proses, output AI.
3. Checklist kontrak: response/error sesuai `API_CONTRACT.md`; skema sesuai `DATABASE.md`.
4. Checklist kualitas: tidak ada `catch` kosong, tidak ada kode mati/dummy, penamaan konsisten.
5. Pastikan dokumentasi dan status roadmap (👀) diperbarui.
6. Susun laporan format `CLAUDE.md` §3, dengan output test aktual.
7. Tulis **SELESAI MENUNGGU REVIEW PENGGUNA.** lalu **BERHENTI**.

## Larangan
- Mengklaim selesai tanpa output test.
- Menyembunyikan masalah yang belum selesai — tulis di bagian Catatan.
- Memulai fitur berikutnya.
- Commit/push tanpa diminta.

## Validasi
- Setiap item DoD dicentang dengan bukti.

## Output yang diharapkan
Laporan 10 bagian sesuai format proyek, berakhir dengan status menunggu review.
