DELIVERY ORDER ADD ITEM HOTFIX V1.2
===================================

Hotfix ini dipasang setelah V1.1 ketika tombol Hapus sudah bekerja tetapi
Tambah Item/Tambah Baris belum menambahkan baris.

V1.2 tidak lagi memakai native HTML <template>. Baris baru dibuat dengan
mengkloning struktur baris form yang sudah dirender, mengganti index input,
dan mengosongkan nilainya. Ini lebih kompatibel dengan lifecycle Vue/layout
admin CRM.

INSTALASI
---------
Ekstrak ZIP ke root Laravel, lalu jalankan:

php tools/apply_delivery_order_add_item_hotfix_v1_2.php
php tools/check_delivery_order_add_item_hotfix_v1_2.php
php artisan optimize:clear

Setelah checker PASS, tutup tab lama atau tekan Ctrl+F5.

UJI
---
1. Klik Tambah Item; counter harus bertambah satu.
2. Klik Tambah Baris; counter harus bertambah satu.
3. Baris baru harus kosong dengan Qty 1 dan Unit "unit".
4. Hapus baris baru dan pastikan counter berkurang.
5. Tambahkan hingga minimal 12 item, simpan, lalu buka ulang.

ROLLBACK
--------
php tools/rollback_delivery_order_add_item_hotfix_v1_2.php
php artisan optimize:clear
