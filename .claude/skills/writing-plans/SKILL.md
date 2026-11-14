---
name: writing-plans
description: Gunakan untuk menyusun FASE A (PLAN) satu fitur sebelum coding di proyek AI Code Security Audit Platform — menghasilkan plan 12 poin lalu berhenti menunggu persetujuan.
---

# Writing Plans

## Tujuan
Menghasilkan rencana implementasi satu fitur yang lengkap, dapat diperiksa, dan tidak melebar ke fitur lain.

## Kapan digunakan
- Setelah perintah **LANJUT**, sebelum menulis kode apa pun.
- Saat pengguna meminta **REVISI** pada plan.

## Input yang diperlukan
- Satu item roadmap.
- Hasil brainstorming (jika ada).
- Kondisi repository aktual (`pwd`, `ls -la`, `git status`).

## Langkah kerja
1. Jalankan `pwd` dan `ls -la` di root project; periksa file yang akan disentuh.
2. Susun plan dengan 12 bagian wajib:
   1. Nama fitur · 2. Tujuan · 3. Kebutuhan yang dipenuhi (FR/NFR) · 4. Penjelasan alur · 5. File yang dibuat · 6. File yang diubah · 7. Perubahan database · 8. Perubahan API · 9. Risiko keamanan · 10. Skenario error · 11. Pengujian (nama test + apa yang dibuktikan) · 12. Definition of Done.
3. Cantumkan dependency baru (jika ada) beserta alasan dan minta persetujuan eksplisit.
4. Cantumkan keputusan `DECISIONS.md` yang dibutuhkan.
5. Perbarui status item roadmap menjadi 📝.
6. **BERHENTI** dan tunggu persetujuan.

## Larangan
- Menulis/mengubah kode aplikasi pada fase ini.
- Menggabungkan lebih dari satu item roadmap dalam satu plan (kecuali pengguna meminta).
- Menyebut file/endpoint/tabel yang tidak ada di dokumen tanpa menandainya sebagai perubahan.

## Validasi
- Semua 12 bagian terisi.
- File yang diubah relevan dengan fitur.
- Setiap risiko keamanan punya mitigasi dan test.

## Output yang diharapkan
Plan terstruktur 12 bagian + daftar keputusan/izin yang dibutuhkan + kalimat penutup "Menunggu persetujuan".
