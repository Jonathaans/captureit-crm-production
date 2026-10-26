# Penggunaan Consumable per Surat Jalan

Menu **Inventory → Penggunaan Consumable** dan tombol di Inventory Dashboard membuka daftar surat jalan dengan kolom Surat Jalan Number, Project Code, Project Name, Customer, Invoice, Sales Person, dan Event Date. Klik nomor surat jalan untuk membuka formulir event.

## Pemakaian

1. Siapkan item ribbon, frame, dan bahan habis pakai di menu Consumables, beserta stoknya.
2. Rilis surat jalan event. Surat jalan draft dapat dilihat, tetapi belum dapat mencatat pemakaian.
3. Buka Penggunaan Consumable, pilih surat jalan, lalu tambahkan consumable, **total jumlah terpakai**, dan catatan opsional.
4. Simpan. Stok langsung berkurang dan mutasi mencatat item, jumlah, nomor surat jalan, petugas, serta waktu pencatatan.
5. Untuk koreksi, ubah total jumlah. Misalnya sebelumnya 10 frame lalu dikoreksi menjadi 8: stok kembali 2 frame. Menghapus baris mengembalikan seluruh jumlah yang dicatat pada baris tersebut. Riwayat mutasi tetap tersimpan.

Consumable yang sudah dikelola lewat alokasi inventory surat jalan ditampilkan terpisah. Stoknya sudah berkurang saat rilis SJ; gunakan proses Return untuk mencatat jumlah sisa yang kembali dan menentukan pemakaian aktual. Formulir tambahan tidak mengizinkan item tersebut dipotong lagi. Item yang reservasinya sudah dilepas dapat dipilih kembali. Barang berstatus allocated/picked milik event lain tetap dilindungi dari pemakaian tambahan.

Surat jalan cancelled dan consumable nonaktif hanya mengizinkan pengurangan pemakaian yang sudah tercatat. Tidak ada pengembalian stok otomatis hanya karena status SJ berubah: koreksi jumlah sesuai barang yang benar-benar kembali agar barang yang sudah terpakai tidak masuk stok lagi.

## Akses

Di **Settings → Roles**, aktifkan:

- **Inventory → Penggunaan Consumable** untuk melihat daftar dan formulir.
- **Catat / Koreksi Penggunaan Consumable** untuk menyimpan.

Administrator dengan permission type `all` langsung memperoleh akses. Role dengan permission khusus perlu diberi kedua izin tersebut. Hak edit item master Consumables dan Adjust Stock tetap terpisah.

## Instalasi setelah merge

Jalankan sebagai pengguna aplikasi yang biasa dipakai di VPS, dari `/var/www/captureit-crm`:

```bash
git pull origin main
php artisan migrate --force
php artisan optimize:clear
```

Tidak perlu build ulang frontend karena form memakai komponen Vue yang didaftarkan melalui Blade. Ikuti prosedur restart worker/layanan yang biasa digunakan saat deployment.

## Verifikasi

Gunakan event uji dan consumable uji:

- Stok 100 frame, catat 10 → stok 90.
- Simpan lagi dengan total yang sama → stok tetap 90.
- Ubah total menjadi 8 → stok 92.
- Hapus baris lalu simpan → stok 100; riwayat stock out dan stock in tetap ada.
- Coba jumlah melebihi stok yang tersedia → seluruh perubahan ditolak, termasuk baris lain dalam formulir.
- Buka formulir di dua tab. Simpan tab pertama lalu tab kedua → tab lama ditolak dan diminta memuat ulang.
- Uji role lihat saja dan role yang boleh mencatat. Role lihat saja tidak dapat menggunakan endpoint simpan.
- Cek **Inventory → Stock Movements**, filter Reference Type **Penggunaan Consumable** dan nomor SJ.

Tes otomatis menggunakan SQLite `:memory:` dan migration tabel baru; tidak menyentuh database produksi:

```bash
php vendor/bin/pest tests/Unit/InventoryConsumableUsageTest.php --compact
```
