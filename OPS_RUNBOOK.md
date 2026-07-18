# KKP Operational Runbook

Dokumen ini untuk menjalankan aplikasi KKP secara operasional harian.

## 1. Persiapan Awal

- Gunakan stack: PHP 8+, MySQL/MariaDB, Apache (XAMPP/Laragon).
- Import schema awal dari `KKP/DATABASE.sql`.
- Set path aplikasi agar bisa diakses di browser, misalnya: `http://localhost/KKP`.

## 2. Keamanan Dasar

- Folder `KKP/config/` sudah diblokir akses web via `.htaccess`.
- Semua form POST utama sudah memakai CSRF token.
- Login sudah punya pembatasan percobaan (lock sementara 5 menit).
- File util reset password:
  - `KKP/reset-password.php` hanya untuk localhost + key.
  - Disarankan **hapus file ini** setelah deployment stabil.

## 3. Backup Rutin

Script backup:
- `KKP/ops/backup_db.ps1`

Contoh eksekusi (PowerShell):

```powershell
powershell -ExecutionPolicy Bypass -File "D:\Semester 6\page\KKP\ops\backup_db.ps1"
```

Output backup akan disimpan di:
- `KKP/ops/backups/`

Saran jadwal:
- Minimal 1x per hari (akhir jam operasional).
- Simpan salinan mingguan ke drive/cloud terpisah.

## 4. Prosedur Harian Operasional

1. Login admin/owner.
2. Input data pelanggan/kendaraan baru bila ada.
3. Input order servis baru.
4. Update status servis sesuai progres.
5. Buat transaksi ketika servis selesai/diambil.
6. Cek laporan harian/bulanan.
7. Jalankan backup DB di akhir hari.

## 5. Laporan Operasional

Halaman `Laporan` mendukung ekspor CSV:
- Export Servis bulanan
- Export Pendapatan

Gunakan filter periode bulan sebelum export.

## 6. QA Setelah Update

Gunakan:
- `KKP/QA_CHECKLIST.md`
- `KKP/QA_EXECUTION_REPORT.md`

Sebelum dipakai user:
- Uji login/logout
- Uji tambah/edit/hapus data inti
- Uji alur servis -> transaksi
- Uji export laporan

## 7. Notifikasi WhatsApp Otomatis

Fitur notifikasi WhatsApp memakai gateway API eksternal. Di aplikasi ini, integrasi dikontrol dari halaman **WhatsApp Notifikasi** dan dari environment variable berikut:

- `WHATSAPP_ENABLED=true`
- `WHATSAPP_API_URL=https://api.fonnte.com/send`
- `WHATSAPP_API_KEY=token-gateway-anda`
- `WHATSAPP_COUNTRY_CODE=62`

### Opsi 1: Fonnte

1. Buat akun di Fonnte.
2. Ambil API key dari dashboard Fonnte.
3. Set `WHATSAPP_API_URL` ke `https://api.fonnte.com/send`.
4. Isi `WHATSAPP_API_KEY` dengan token Fonnte.
5. Aktifkan `WHATSAPP_ENABLED=true`.

### Opsi 2: WhatsApp Business API

1. Siapkan provider/gateway yang mendukung endpoint HTTP untuk pengiriman pesan.
2. Arahkan `WHATSAPP_API_URL` ke endpoint gateway tersebut.
3. Isi `WHATSAPP_API_KEY` dengan bearer token atau API key yang diminta provider.
4. Jika provider memakai format request berbeda, sesuaikan lewat gateway/adapter di server.
5. Aktifkan `WHATSAPP_ENABLED=true`.

### Cara Uji

1. Buka menu **WhatsApp Notifikasi**.
2. Isi nomor tujuan dan pesan tes.
3. Klik **Kirim Tes**.
4. Jika berhasil, cek inbox WhatsApp penerima.

Saat aktif, sistem akan mengirim pesan otomatis ketika:

- Order servis baru dibuat
- Status servis berubah ke proses
- Status servis berubah ke selesai
- Transaksi baru dibuat
- Status pembayaran diubah menjadi lunas

Pastikan nomor telepon pelanggan tersimpan dalam format yang valid.

## 8. Troubleshooting Cepat

- Gagal login terus:
  - Tunggu lock 5 menit jika terlalu banyak percobaan.
- Error koneksi DB:
  - Cek `KKP/config/database.php` dan status MySQL.
- Style tidak berubah:
  - Hard refresh `Ctrl+F5`.
- Export CSV tidak terunduh:
  - Pastikan browser tidak memblokir popup/download.
- Notifikasi WhatsApp tidak terkirim:
  - Cek `WHATSAPP_ENABLED` dan `WHATSAPP_API_KEY`.
  - Pastikan endpoint gateway bisa diakses dari server.
  - Pastikan nomor pelanggan ada dan valid.

## 9. Handover ke Tim

Saat handover, pastikan tim menerima:
- URL aplikasi
- Akun admin operasional
- Lokasi backup
- SOP harian (dokumen ini)
- Checklist QA
