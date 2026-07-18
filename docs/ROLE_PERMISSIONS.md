# Role & Permissions — Formula Jaya Per

Dokumen ini menjelaskan hak akses yang disarankan untuk tiga peran utama di sistem. Tujuan: membuat hak akses jelas, ringkas, dan mudah dipahami oleh tim.

---

## Ringkasan Peran

- **Admin**
  - Akses penuh terhadap manajemen data operasional.
  - Tanggung jawab utama: konfigurasi, manajemen pengguna, CRUD data inti.

- **Mekanik**
  - Fokus pada operasional bengkel: mengelola alur servis.
  - Tanggung jawab utama: memperbarui status servis dan mencatat diagnosis.

- **Owner**
  - Akses baca untuk laporan dan analitik.
  - Tidak punya akses tulis ke transaksi atau data pelanggan oleh default.

---

## Hak Akses Detail (Lebih Jelas)

- **Admin**
  - Pelanggan: Create / Read / Update / Delete
  - Kendaraan: Create / Read / Update / Delete
  - Servis: Read / Update / Delete
  - Transaksi: Create / Read / Update / Delete
  - Users: Create / Read / Update / Delete / Assign roles
  - Laporan: Read / Export

- **Mekanik**
  - Servis: Read / Update status (masuk → proses → selesai → diambil)
  - Dapat menambahkan diagnosis, odometer, catatan servis
  - Tidak boleh menghapus pelanggan/kendaraan atau mengelola users

- **Owner**
  - Laporan: Read / Export (pendapatan, servis, stok)
  - Dashboard: Read-only overview
  - Tidak boleh mengubah data transaksi, pelanggan, kendaraan, atau users

---

## Contoh Penegakan (Rekomendasi Implementasi)

- Gunakan fungsi helper `hasRole($role)` di server-side untuk mengamankan tiap endpoint dan tombol.
- Contoh pada PHP sebelum menjalankan aksi sensitif:

```php
if (!hasRole('admin')) {
    http_response_code(403);
    exit('Akses ditolak');
}
```

- Untuk tindakan mekanik (update status servis), cek peran di endpoint update servis:

```php
if (!hasRole(['admin','mekanik'])) {
    http_response_code(403);
    exit('Akses ditolak');
}
```

- Di UI, sembunyikan tombol aksi yang tidak relevan bila `isLoggedIn()` dan `hasRole()` menyatakan user tidak punya hak tersebut.

---

## Rekomendasi UX (Membuatnya Lebih Menarik)

- Tampilkan ringkasan peran di halaman `Pengaturan` atau `Dashboard` dalam bentuk kartu (card) berwarna, ikon, dan deskripsi singkat.
- Sertakan tooltip singkat pada tombol "Hapus" atau "Ubah Status" untuk menjelaskan hak akses yang dibutuhkan.
- Pada halaman pengguna (`users.php`), saat menugaskan role, tampilkan checklist hak akses yang otomatis tercentang menurut role.

---

## Catatan Operasional

- Setelah perubahan hak akses utama, jalankan QA Checklist pada `QA_CHECKLIST.md` terutama bagian Login & Session, Users, dan Servis.
- Simpan backup DB sebelum melakukan perubahan massal pada peran atau permissions.

---

Jika Anda ingin, saya bisa:
- Menyisipkan widget ringkas ini ke dashboard (mengedit `dashboard.php`).
- Menambahkan pengecekan server-side di beberapa endpoint kritis.

Sebutkan opsi yang Anda inginkan selanjutnya.