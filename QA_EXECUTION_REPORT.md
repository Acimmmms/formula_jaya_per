# KKP QA Execution Report

Tanggal: 2026-04-10
Scope: Step 2 (alur end-to-end + perbaikan bug alur bisnis)

## A. Automated Verification (Executed)

### 1) CSRF Coverage
Status: PASS

- Semua endpoint POST utama memanggil `verifyCsrfOrExit()`:
  - `KKP/login.php`
  - `KKP/pages/pelanggan.php`
  - `KKP/pages/kendaraan.php`
  - `KKP/pages/servis.php`
  - `KKP/pages/stok.php`
  - `KKP/pages/transaksi.php`
  - `KKP/pages/users.php`
- Semua form POST sudah menyertakan `<?= csrfInput() ?>`.

### 2) Syntax / Lint
Status: PASS

- No errors ditemukan pada file yang dimodifikasi selama step 2:
  - `KKP/pages/servis.php`
  - `KKP/pages/transaksi.php`
  - `KKP/pages/kendaraan.php`
  - `KKP/pages/stok.php`
  - `KKP/pages/users.php`

### 3) Business-Rule Guards
Status: PASS (code-level)

- Servis:
  - Validasi relasi pelanggan-kendaraan saat create.
  - Blok create jika kendaraan masih punya servis status `masuk/proses`.
  - Validasi status whitelist + validasi transisi urutan status.
- Transaksi:
  - Hanya servis status `selesai/diambil` yang bisa ditransaksikan.
  - Blok duplikasi transaksi per `servis_id`.
  - Validasi whitelist `status_bayar` dan `metode_bayar`.
  - Dropdown order servis sudah filter `belum punya transaksi`.
- Kendaraan:
  - Validasi pelanggan harus valid saat create/update.
  - Cegah no polisi duplikat saat update.
- Stok:
  - Cegah nilai stok/harga negatif pada create/update.
- Users:
  - Validasi whitelist role dan status.

## B. Manual Verification (Pending in Browser)

Status: PENDING (butuh uji interaktif via browser + DB)

Jalankan sesuai file checklist:
- `KKP/QA_CHECKLIST.md`

Prioritas manual test yang disarankan:
1. Login sukses/gagal + token CSRF invalid.
2. Create servis dengan pelanggan-kendaraan mismatch (harus ditolak).
3. Create servis kedua pada kendaraan yang masih progres (harus ditolak).
4. Create transaksi untuk servis yang sama dua kali (kedua harus ditolak).
5. Update status cepat di servis, coba transisi lompat (harus ditolak).

## C. Risk Notes

- Utility reset password masih ada file fisik (`KKP/reset-password.php`), walau sudah diproteksi localhost + key. Tetap disarankan dihapus sebelum production.
- Belum ada automated UI/integration tests, jadi regresi visual/interaksi masih mengandalkan tes manual.
