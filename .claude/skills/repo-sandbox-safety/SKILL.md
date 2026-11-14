---
name: repo-sandbox-safety
description: Gunakan saat mengerjakan ingesti repositori (upload ZIP, ekstraksi, git clone URL publik), validasi URL anti-SSRF, workspace sementara, traversal file, atau eksekusi tool pada repo target di proyek AI Code Security Audit Platform.
---

# Repo Sandbox Safety

## Tujuan
Memproses repositori tak tepercaya tanpa menulis di luar workspace, tanpa menjalankan kodenya, dan tanpa membuka jalan SSRF — serta membersihkan sisa data.

## Kapan digunakan
TAHAP 4 (4.1–4.7), TAHAP 5 (cleanup), dan setiap kode yang membaca/menjalankan sesuatu terhadap repo target.

## Input yang diperlukan
- File ZIP terunggah atau URL Git.
- `public_id` audit.
- Batas dari `config/audit.php` (ukuran 50 MB, jumlah file maksimum, rasio kompresi, timeout clone).

## Langkah kerja
### ZIP
1. Validasi di request: ekstensi `.zip`, MIME, magic bytes `PK\x03\x04`, ukuran ≤ 50 MB.
2. Buka dengan `ZipArchive`; iterasi entri **sebelum** menulis:
   - Tolak nama berisi `..` sebagai segmen, path absolut (`/`, `\`, `C:`), null byte, backslash traversal.
   - Tolak symlink (cek atribut eksternal Unix mode `S_IFLNK`).
   - Hitung total ukuran tak terkompresi (≤ 50 MB), jumlah file, dan rasio kompresi.
3. Tulis tiap entri secara manual ke `workspace/<path ternormalisasi>`, lalu pastikan `realpath` hasil berada di dalam `realpath(workspace)`.
4. Jangan pernah memakai `extractTo()` tanpa validasi entri.

### Git
1. Hanya `https://`; tolak userinfo, port non-standar, fragment aneh; host sesuai D-08.
2. Resolve DNS; tolak IP privat/loopback/link-local/CGNAT/multicast/IPv6 lokal (`127.0.0.0/8`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `169.254.0.0/16`, `100.64.0.0/10`, `::1`, `fc00::/7`, `fe80::/10`, `0.0.0.0`).
3. Clone via Symfony Process (array argumen) dengan timeout:
   `git -c core.hooksPath=/dev/null -c protocol.file.allow=never -c http.followRedirects=false clone --depth 1 --no-tags --single-branch --no-recurse-submodules -- <url> <workspace>/repo`
   Env: `GIT_TERMINAL_PROMPT=0`, `GIT_LFS_SKIP_SMUDGE=1`, `GIT_CONFIG_NOSYSTEM=1`, `HOME` ke direktori kosong.
4. Ukur ukuran hasil clone; > 50 MB → gagal `REPOSITORY_TOO_LARGE`.
5. Hapus/abaikan symlink yang menunjuk keluar workspace.

### Workspace & traversal
1. Satu direktori per audit: `storage/app/private/workspaces/{public_id}/`.
2. Traversal rekursif tanpa mengikuti symlink; abaikan `node_modules/`, `vendor/`, `.git/`.
3. Cleanup di `finally` dan di `failed()` job; hapus juga ZIP upload.

## Larangan
- Menjalankan kode, skrip, hook, Makefile, `npm/pip/composer install`, atau build dari repo target.
- Shell string untuk perintah apa pun yang memuat input pengguna.
- Mengikuti redirect HTTP saat clone tanpa validasi ulang host.
- Menyimpan source code penuh ke database.
- Mengklaim direktori sementara sebagai isolasi keamanan penuh.

## Validasi
- Test ZIP: `../evil`, path absolut, symlink, zip bomb, file bukan ZIP, ZIP valid.
- Test URL: http, userinfo, IP privat langsung, hostname → IP privat, metadata cloud, host di luar allowlist.
- Test workspace terhapus pada jalur sukses dan gagal.

## Output yang diharapkan
Workspace berisi salinan repo yang tervalidasi, metadata (`repository_name`, `source_size_bytes`, `commit_sha`), atau status `failed` dengan `error_code` yang jelas — tanpa sisa file setelah job selesai.
