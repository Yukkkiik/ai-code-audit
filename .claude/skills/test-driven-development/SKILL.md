---
name: test-driven-development
description: Gunakan saat FASE B (implementasi) fitur yang sudah disetujui di proyek AI Code Security Audit Platform — tulis test gagal dulu, implementasi minimal, lalu refactor, dengan output test aktual.
---

# Test-Driven Development

## Tujuan
Memastikan setiap perilaku fitur dibuktikan oleh test yang benar-benar dijalankan, bukan diklaim.

## Kapan digunakan
Setiap implementasi fitur atau perbaikan bug setelah plan disetujui.

## Input yang diperlukan
- Plan yang disetujui (bagian Pengujian & DoD).
- Skenario dari `TESTING.md`.
- Fixture yang dibutuhkan (ZIP contoh, output JSON tool, respons OSV/LLM palsu).

## Langkah kerja
1. **Red** — tulis test untuk satu perilaku; jalankan; pastikan gagal karena alasan yang benar.
2. **Green** — tulis kode minimal agar test lulus.
3. **Refactor** — rapikan tanpa mengubah perilaku; jalankan ulang test.
4. Ulangi per perilaku, termasuk jalur error dan kasus keamanan.
5. Jalankan seluruh test suite terkait + formatter (`./vendor/bin/pint` / `npm run lint`) + type-check frontend.
6. Salin output aktual ke laporan.

Pola proyek:
- Backend feature test memakai `RefreshDatabase` dengan database MySQL testing.
- Layanan eksternal (OSV.dev, LLM, Google) di-fake (`Http::fake()`); assert juga request yang **tidak boleh** terkirim (mis. komponen `unresolved` ke OSV).
- Test keamanan (ZIP Slip, SSRF, otorisasi) wajib untuk fitur terkait.

## Larangan
- Menghapus, melewati (`skip`), atau melemahkan test agar lulus.
- Mengarang hasil test.
- Test yang hanya memeriksa "tidak error" tanpa assert perilaku.
- Memanggil layanan eksternal nyata di test otomatis.

## Validasi
- Setiap item DoD punya minimal satu test.
- Test pernah terlihat gagal sebelum implementasi.
- Seluruh suite lulus setelah perubahan.

## Output yang diharapkan
Daftar test baru, perintah yang dijalankan, dan output aktual (jumlah lulus/gagal).
