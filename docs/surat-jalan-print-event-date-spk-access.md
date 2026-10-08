# Cetak Surat Jalan, filter Event Date, dan akses SPK

Perubahan ini berbasis `main` yang sudah memuat perbaikan cancel Surat Jalan di PR #18 (`f2c6c48`).

## Perilaku

- **Print Surat Jalan** membuka dokumen A4 di tab baru dan memanggil dialog print browser setelah gambar/font siap. Tidak mengunduh PDF otomatis. Tersedia tombol Print untuk mencoba kembali setelah dialog ditutup. Invoice dan PDF SPK tetap memakai alur sebelumnya.
- Halaman cetak tidak memuat widget chat/notifikasi. Footer memiliki ruang tersendiri pada setiap halaman; daftar equipment panjang dan tanda tangan tetap dapat dicetak.
- Filter **Event Date** di daftar Delivery Orders menyediakan tanggal awal/akhir dan pilihan cepat. Kedua batas tanggal termasuk hasil, termasuk saat tanggal awal sama dengan tanggal akhir. Filter tetap dapat digabung dengan status dan filter lain.
- Hak akses SPK mengikuti permission role, tanpa daftar nama role yang diizinkan. Edit, Generate dari Invoice, Print, Generate Surat Jalan, dan Update Status diperiksa secara terpisah pada server dan tombol tampilan. Pembatasan Sales User terhadap SPK miliknya tetap berlaku.
- Tidak ada migrasi, perubahan dependensi, atau perubahan frontend build. Tidak ada perubahan otomatis pada permission role yang tersimpan.

## Pengaturan Warehouse Staff

Pada **Settings → Roles → Warehouse Staff → Surat Perintah Kerja**:

| Permission | Pengaturan |
| --- | --- |
| View SPK | Centang |
| Edit SPK | Centang |
| Generate SPK from Invoice | Jangan dicentang |
| Print SPK | Centang bila diperlukan |
| Generate Surat Jalan from SPK | Sesuai tugas gudang |
| Update SPK Status | Sesuai tugas gudang |

Simpan role. Gunakan akun Warehouse Staff untuk tes, bukan akun Administrator dengan akses `all`.

## Tes lokal

Setelah mengambil branch/merge ini di checkout lokal yang perubahan lokalnya sudah diamankan, jalankan:

```shell
php artisan optimize:clear
php artisan test --compact tests/Unit/WorkOrderAccessTest.php tests/Unit/WorkOrderControllerAccessTest.php tests/Unit/DeliveryOrderEventDateFilterTest.php tests/Unit/DeliveryOrderCancellationTest.php
```

1. Buka Delivery Orders → Filter → Event Date. Coba satu tanggal, rentang dua tanggal, lalu tambah filter status. Pastikan tanggal event menjadi acuan, meskipun delivery date berbeda. Clear filter harus menampilkan daftar kembali.
2. Dengan Warehouse Staff yang memiliki View/Edit tetapi tidak Generate dari Invoice, buka SPK yang sudah ada, ubah catatan/item, lalu simpan. Edit harus berhasil. Tombol generate dari invoice tidak tampil; permintaan langsung ke endpoint generate harus ditolak 403.
3. Hilangkan Edit SPK dan simpan role. Tombol Edit harus hilang dan akses langsung ke halaman edit/update ditolak. Izin Generate saja tidak boleh membuka edit.
4. Klik Print Surat Jalan dari detail SJ dan dari invoice. Tab cetak dan dialog print harus terbuka; periksa logo, equipment, kode aset, footer, serta empat kolom tanda tangan. Tutup dialog lalu coba tombol Print. Coba SJ pendek dan lebih dari satu halaman.
5. Pastikan Print Invoice dan PDF SPK tetap berfungsi seperti sebelumnya.
6. Untuk regresi cancel PR #18, gunakan SJ percobaan berstatus DRAFT dengan reservasi alat. Cancel harus mengubah status menjadi CANCELLED dan membebaskan reservasi; barang yang sudah OUT tetap tidak dapat dibatalkan melalui aksi ini.

## Verifikasi saat pengembangan

- Skenario test baru dieksekusi dengan assertion ketat melalui runner terisolasi PHP 8.3.33, source Laravel 12.69.1, dan SQLite in-memory: permission setiap aksi, edit/simpan controller SPK, penolakan generate, pembatasan pemilik Sales User, serta filter tanggal inklusif. Ini bukan eksekusi penuh Pest/artisan pada instalasi aplikasi.
- Controller cancel beserta service reservasi diuji dengan SQLite: reservasi serialized/quantity dilepas, stok fisik tidak ditambah lagi, dan status OUT/RETURN_PENDING tetap ditolak.
- Blade cetak dirender dengan fixture 0/12/60 item. Chromium headless memverifikasi pemanggilan `window.print()` setelah logo dimuat, cetak ulang, tidak ada download/JavaScript error, serta toolbar tidak ikut dicetak. Hasil A4 diperiksa untuk konten, footer, dan tanda tangan. Dialog printer OS dan printer fisik perlu tes manual di browser pengguna.
- PHP diformat dengan Laravel Pint; diff diperiksa. Validator skill repository tetap gagal karena `.github/skills` tidak ada pada baseline repository.
