---
name: brainstorming
description: Gunakan sebelum merancang fitur baru atau ketika kebutuhan masih ambigu di proyek AI Code Security Audit Platform — menggali kebutuhan, opsi, dan keputusan terbuka sebelum membuat plan.
---

# Brainstorming

## Tujuan
Memahami kebutuhan satu fitur secara utuh sebelum merencanakan atau menulis kode, dan memunculkan keputusan yang belum ditetapkan tanpa mengarang jawabannya.

## Kapan digunakan
- Pengguna memberi perintah **LANJUT** dan item roadmap berikutnya masih punya ketidakjelasan.
- Item roadmap bergantung pada keputusan berstatus Terbuka di `DECISIONS.md`.
- Ada lebih dari satu pendekatan teknis yang masuk akal.

## Input yang diperlukan
- Item roadmap yang dimaksud (`IMPLEMENTATION_ROADMAP.md`).
- FR/NFR terkait (`PRD.md`), alur (`FLOWS.md`), skema (`DATABASE.md`), kontrak (`API_CONTRACT.md`).
- Keputusan terkait di `DECISIONS.md`.

## Langkah kerja
1. Baca dokumen acuan untuk item tersebut.
2. Tuliskan ulang tujuan fitur dalam 1–2 kalimat.
3. Daftar asumsi; tandai mana yang sudah tercatat di dokumen dan mana yang belum.
4. Untuk setiap ketidakjelasan: ajukan 2–3 opsi, kelebihan/kekurangan, dan rekomendasi.
5. Ajukan pertanyaan seperlunya kepada pengguna (singkat, satu topik per pertanyaan).
6. Setelah jelas, lanjut ke skill `writing-plans`.

## Larangan
- Menulis kode produksi selama brainstorming.
- Menganggap keputusan Terbuka sudah disetujui.
- Menambah fitur di luar FR-01 s/d FR-10.
- Mengusulkan dependency baru tanpa alasan teknis jelas.

## Validasi
- Setiap opsi terhubung ke kebutuhan nyata (FR/NFR).
- Tidak ada asumsi tersembunyi: semua tercantum eksplisit.

## Output yang diharapkan
Ringkasan kebutuhan, daftar asumsi, opsi + rekomendasi, dan daftar pertanyaan/keputusan yang perlu dijawab pengguna.
