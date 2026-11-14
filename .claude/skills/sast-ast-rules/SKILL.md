---
name: sast-ast-rules
description: Gunakan saat mengerjakan SAST Engine JavaScript/TypeScript (Semgrep, ESLint), normalisasi temuan, metrik Cyclomatic Complexity/Halstead/Maintainability Index, atau skor kesehatan di proyek AI Code Security Audit Platform.
---

# SAST & AST Rules

## Tujuan
Menjalankan analisis statis secara aman **khusus untuk kode JavaScript/TypeScript** dan menghasilkan temuan serta metrik dalam format seragam yang dapat ditelusuri.

## Kapan digunakan
TAHAP 6 (6.1–6.7) dan perubahan apa pun pada `app/Services/Sast/`.

## Input yang diperlukan
- Path workspace audit (sudah lolos `repo-sandbox-safety`).
- Daftar file hasil traversal (tanpa `node_modules`, `vendor`, `.git`).
- Konfigurasi platform di `backend/tooling/` dan `config/audit.php`.

## Langkah kerja
1. **Seleksi file** dari ekstensi: `.js .jsx .mjs .cjs` → `javascript`; `.ts .tsx .mts .cts` → `typescript`. File lain (`.py`, `.php`, `.vue`, `.svelte`, dll.) **diabaikan**. File `.d.ts` dan file minified (`*.min.js`) diabaikan.
2. **Tidak ada file JS/TS** → tool tidak dijalankan, `sast_status=skipped`.
3. **Eksekusi aman** via Symfony Process dengan array argumen + timeout:
   - Semgrep: `--json --metrics=off --disable-version-check`, ruleset JS/TS sesuai D-22, `--config` hanya dari platform, dibatasi ke file JS/TS (`--include`).
   - ESLint: config platform eksplisit, **nonaktifkan** pencarian config repo (flat config `--config <platform> --no-config-lookup` atau padanan versi terpasang), parser TypeScript dari tooling platform (D-21), `--format json`, tanpa `--fix`.
4. **Parse JSON**; JSON tidak valid / exit code error → tool gagal (dicatat, tidak di-swallow). Perhatikan exit code non-zero yang berarti "ada temuan" (bukan error) per tool.
5. **Normalisasi** ke struktur: `tool, rule_id, category, severity, file_path (relatif), line_start, line_end, column_start, code_snippet, message, cwe, fingerprint`.
6. **Pemetaan severity** (usulan, finalisasi bersama D-10):
   | Tool | Asal | Normalisasi |
   |---|---|---|
   | Semgrep | `ERROR` / `WARNING` / `INFO` | `high` / `medium` / `low` |
   | ESLint | `2` (error) / `1` (warn) | `medium` / `low` (aturan security bisa dinaikkan via map rule) |
7. **Metrik** (JS/TS saja, alat menunggu D-09):
   - Cyclomatic Complexity = 1 + jumlah titik keputusan (`if`, `else if`, `for`, `for…in`, `for…of`, `while`, `do…while`, `case`, `catch`, `&&`, `||`, `??`, `?:`).
   - Halstead: n1, n2, N1, N2 → Volume = N·log2(n), Difficulty = (n1/2)·(N2/n2), Effort = D·V.
   - MI = max(0, (171 − 5.2·ln(V) − 0.23·CC − 16.2·ln(SLOC)) · 100/171).
8. **Skor kesehatan** 0–100 dari temuan SAST + metrik saja (rumus D-10). **Tanpa** data SCA.
9. Catat status setiap tool di `scan_tool_runs` (`completed`/`failed`/`skipped`, jumlah file, jumlah temuan, pesan error aman, durasi). Simpan ke `scan_findings` / `code_metrics`, set `sast_status` (`completed`/`partial`/`failed`/`skipped`).

## Larangan
- Memuat/menjalankan konfigurasi, plugin, atau kode dari repositori target (`eslint.config.*`, `.eslintrc.*`, `tsconfig.json` sebagai project service, `.semgrep.yml`, dsb.).
- Menambah dukungan bahasa selain JavaScript/TypeScript atau tool lain (mis. Bandit) tanpa persetujuan.
- Menggunakan `--fix` atau mengubah file repo.
- Shell string (`exec("semgrep $path")`).
- Mengirim kode ke Semgrep cloud / mengaktifkan metrics.
- Memasukkan hasil SCA ke skor kesehatan.
- Menampilkan "0 temuan" bila tool gagal/tidak dijalankan.

## Validasi
- File path temuan relatif terhadap root repo dan berada di dalam workspace.
- Severity selalu salah satu enum `Severity`.
- Fingerprint unik per audit (deduplikasi).
- Test fixture output nyata tiap tool + kalkulasi metrik dengan nilai hitung manual.

## Output yang diharapkan
Baris `scan_findings` & `code_metrics` ternormalisasi, `health_score`/`health_category`, serta status tahap SAST yang jujur (termasuk tool mana yang gagal).
