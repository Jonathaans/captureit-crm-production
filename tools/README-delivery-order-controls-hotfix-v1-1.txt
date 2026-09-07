DELIVERY ORDER CONTROLS HOTFIX V1.1
===================================

TUJUAN
------
Memperbaiki tombol Hapus, Tambah Item, Tambah Baris, pencarian, dan auto-fill
inventory pada UI unlimited item Surat Jalan.

AKAR MASALAH
------------
Tampilan V1 sudah terpasang, tetapi inline JavaScript di partial Blade tidak
dieksekusi pada layout admin di instalasi ini. V1.1 memindahkan logic tersebut
ke asset public JavaScript dan memakai event delegation agar tetap aktif saat
halaman dirender atau dibuka ulang.

INSTALASI
---------
Ekstrak isi ZIP ke root Laravel, lalu jalankan:

php tools/apply_delivery_order_controls_hotfix_v1_1.php
php tools/check_delivery_order_controls_hotfix_v1_1.php
php artisan optimize:clear

Setelah itu lakukan hard refresh browser dengan Ctrl+F5.

UJI WAJIB
---------
1. Klik Tambah Item dan pastikan satu baris baru muncul.
2. Klik Tambah Baris dan pastikan satu baris baru muncul.
3. Isi nama pada baris baru, lalu klik Hapus dan konfirmasi.
4. Tambahkan hingga minimal 12 item, simpan, lalu buka ulang.
5. Uji pencarian dan auto-fill nama/unit dari Inventory Item.

ROLLBACK
--------
php tools/rollback_delivery_order_controls_hotfix_v1_1.php
php artisan optimize:clear
