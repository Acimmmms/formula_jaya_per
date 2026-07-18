# KKP End-to-End QA Checklist

Gunakan checklist ini untuk Step 2 (uji alur bisnis + regresi) setelah perubahan keamanan dan validasi.

## 1. Login & Session
- [ ] Login dengan akun valid berhasil.
- [ ] Login dengan password salah gagal dengan pesan error.
- [ ] Submit login tanpa CSRF token ditolak (419).
- [ ] Logout berhasil dan tidak bisa akses halaman internal setelah logout.

### Template Hasil Uji

Gunakan tabel berikut untuk mencatat hasil uji setiap skenario. Kolom `Actual` dan `Status` diisi oleh penguji saat eksekusi.

| No | Test Case | Input | Expected | Actual | Status |
|---:|---|---|---|---|---|
| 1 | Login dengan akun valid | `username=admin`, `password=admin` | Redirect ke `dashboard.php` dan tampilkan nama user |  | Pass / Fail |
| 2 | Login tanpa CSRF token | POST tanpa field `csrf_token` | Response 419 / aksi ditolak |  | Pass / Fail |

Tambahkan baris baru untuk setiap test case yang dijalankan.

## 2. Pelanggan
- [ ] Tambah pelanggan baru berhasil.
- [ ] Edit data pelanggan berhasil.
- [ ] Hapus pelanggan tanpa kendaraan berhasil.
- [ ] Hapus pelanggan yang masih punya kendaraan ditolak.

## 3. Kendaraan
- [ ] Tambah kendaraan dengan pelanggan valid berhasil.
- [ ] Tambah kendaraan dengan no polisi duplikat ditolak.
- [ ] Edit kendaraan dan ubah no polisi ke data duplikat ditolak.
- [ ] Tambah/edit kendaraan dengan pelanggan tidak valid ditolak.
- [ ] Hapus kendaraan menghapus data servis terkait secara aman.

## 4. Servis
- [ ] Tambah servis dengan pasangan pelanggan-kendaraan yang valid berhasil.
- [ ] Tambah servis dengan pasangan pelanggan-kendaraan tidak cocok ditolak.
- [ ] Tambah servis saat kendaraan masih punya servis status masuk/proses ditolak.
- [ ] Ubah status via tombol cepat hanya boleh urutan: masuk -> proses -> selesai -> diambil.
- [ ] Ubah status dengan nilai tidak valid ditolak.

## 5. Stok
- [ ] Tambah barang baru berhasil.
- [ ] Update barang berhasil.
- [ ] Input stok/harga negatif ditolak pada tambah dan update.
- [ ] Hapus barang berjalan normal.

## 6. Transaksi
- [ ] List order servis untuk transaksi hanya menampilkan status selesai/diambil yang belum pernah ditransaksikan.
- [ ] Buat transaksi untuk servis valid berhasil.
- [ ] Buat transaksi duplikat untuk servis yang sama ditolak.
- [ ] Buat transaksi untuk servis yang belum selesai ditolak.
- [ ] Tandai lunas berhasil.
- [ ] Hapus transaksi berhasil.

## 7. Notifikasi WhatsApp
- [ ] Order servis baru memicu notifikasi WhatsApp ke nomor pelanggan.
- [ ] Perubahan status servis memicu notifikasi WhatsApp.
- [ ] Transaksi baru memicu notifikasi WhatsApp.
- [ ] Perubahan status pembayaran menjadi lunas memicu notifikasi WhatsApp.
- [ ] Jika gateway WhatsApp tidak aktif, penyimpanan data tetap berhasil.

## 8. Users (Admin)
- [ ] Tambah user dengan role/status valid berhasil.
- [ ] Tambah/update user dengan role/status tidak valid ditolak.
- [ ] Hapus akun sendiri ditolak.

## 9. Regression UI
- [ ] Semua halaman utama tetap bisa dibuka tanpa error PHP.
- [ ] Semua form POST memiliki hidden `csrf_token`.
- [ ] Alert pesan sukses/gagal tampil normal.
- [ ] Table/filter/search tetap berfungsi normal.
