# Edit equipment setelah scan Surat Jalan

Tim gudang dapat mengedit Surat Jalan DRAFT walaupun sudah mempunyai alokasi aktif. Form lama menolak seluruh penyimpanan jika menemukan alokasi dan membangun ulang semua baris equipment ketika diizinkan menyimpan.

Perubahan ini menyimpan baris lama berdasarkan ID, sehingga hasil scan, source product/SKU, dan relasi alokasi tetap melekat pada baris yang sama. Baris baru ditambahkan tanpa mengulang scan alat lama. Nama, deskripsi, catatan, data penerima, dan jumlah kebutuhan dapat diperbarui.

Jumlah kebutuhan boleh dinaikkan atau dikurangi sampai batas jumlah yang sudah dialokasikan. Mengganti inventory, menghapus baris, atau menurunkan jumlah di bawah alokasi memerlukan reset pada item terkait terlebih dahulu. Item tanpa alokasi boleh diubah atau dihapus. Persyaratan inventory manual dari template tetap wajib dilengkapi.

Setelah menambah kebutuhan, buka Allocation dan lengkapi alat tambahan. Surat Jalan baru dapat dirilis setelah seluruh kebutuhan lengkap. Penyimpanan equipment tidak mengeluarkan atau mengembalikan stok. Status setelah issue tetap memakai proses pengiriman dan pengembalian yang sudah ada.

Pemeriksaan versi form mencakup baris equipment, bukan aktivitas scan. Scan yang terjadi ketika halaman Edit terbuka tidak membatalkan form. Jika staf lain menambah atau mengedit kebutuhan lebih dahulu, tab lama meminta refresh agar tidak menghapus perubahan tersebut. Edit, scan serialized, reserve quantity, dan issue menggunakan lock parent Surat Jalan sebelum memproses kebutuhan; scan membaca ulang mapping, jumlah, dan status terbaru di dalam transaksi.

## Pengujian lokal

```sh
composer dump-autoload -o
php artisan test --compact --filter='DeliveryOrderEquipmentTest|CrmDataCorrectionsTest'
```

Skenario database meliputi:

- Semua alat sudah discan, lalu menambah item dan menaikkan jumlah; ID alokasi, hasil scan, stok, dan pergerakan lama tetap utuh.
- Kebutuhan baru menghalangi release sampai lengkap, kemudian seluruh alat dapat dirilis dan stok ribbon dipotong tepat sekali.
- Penolakan remapping, penghapusan, atau pengurangan yang bertentangan dengan alokasi; rollback seluruh perubahan pada kegagalan.
- Perubahan item tanpa alokasi, reset per item, serta kebutuhan laptop manual.
- Tab edit lama, ID baris duplikat/milik Surat Jalan lain, status yang sudah dirilis, dan request scan dengan model yang sudah kedaluwarsa.

Validasi pengembangan menggunakan PHP 8.3.33 dan Laravel 12.69.1 sesuai composer.lock dengan SQLite in-memory: 6 skenario baru dan 10 skenario regresi lulus. Syntax PHP, kompilasi Blade, serta aturan validasi controller untuk beberapa baris baru dan ID duplikat diperiksa. Suite aplikasi penuh, concurrency MySQL, dan UI browser production tidak dijalankan dari lingkungan ini. `bin/validate-skills.sh` masih gagal karena `.github/skills` tidak tersedia pada baseline repository.

## Deploy dan cek UI

Deploy setelah pengujian lokal dan merge PR melalui prosedur maintenance yang digunakan sebelumnya: hentikan queue/Reverb, backup, ambil main dengan fast-forward, bangun autoload, bersihkan dan bangun cache, reload PHP-FPM, lalu aktifkan queue/Reverb dan aplikasi.

Tidak ada migration, perubahan dependensi, atau build frontend dalam perbaikan ini. Jangan mengulang command apply koreksi invoice atau katalog. Pertahankan build lokal VPS saat pull.

Setelah deploy, muat ulang halaman Edit yang sudah terbuka. Pada Surat Jalan DRAFT yang sudah discan:

1. Tambah satu equipment atau tambah jumlah kebutuhan, kemudian simpan.
2. Pastikan QR/alokasi lama tetap tampil dan tambahan masih menunggu alokasi.
3. Lengkapi alokasi tambahan lewat alur normal sebelum release.

Uji release/return dengan database lokal; di production lanjutkan hanya transaksi gudang yang memang diperlukan.
