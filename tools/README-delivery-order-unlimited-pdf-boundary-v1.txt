DELIVERY ORDER UNLIMITED ITEMS + PDF SAFE BOUNDARY V1
=====================================================

FITUR
-----
1. Edit Surat Jalan tidak lagi menampilkan tepat 10 baris statis.
2. Jumlah item dapat ditambah tanpa batas melalui tombol Tambah Item.
3. Item dapat dihapus per baris, dicari, dan dihitung otomatis.
4. Tabel memiliki header sticky dan area scroll untuk daftar panjang.
5. Pemilihan Inventory Item dapat mengisi nama dan unit secara otomatis.
6. PDF Quote dan Invoice memiliki text-safe top boundary 20 mm pada setiap
   halaman A4, termasuk halaman kedua dan berikutnya.
7. Header tabel item tetap berulang pada halaman lanjutan.

INSTALASI
---------
Ekstrak isi ZIP ke root project Laravel sehingga file berada di folder tools,
lalu jalankan:

php tools/apply_delivery_order_unlimited_pdf_boundary_v1.php
php tools/check_delivery_order_unlimited_pdf_boundary_v1.php

Jika checker PASS:

php artisan optimize:clear

UJI WAJIB
---------
1. Buka edit Surat Jalan.
2. Tambahkan minimal 12 item.
3. Simpan, buka ulang, dan pastikan seluruh 12 item tetap tampil.
4. Buat Quote dan Invoice dengan item cukup banyak hingga dua halaman.
5. Pastikan isi halaman kedua mulai sekitar 20 mm dari tepi atas dan tidak
   masuk area header/text boundary.
6. Pastikan footer tidak bertumpuk dengan isi.

BACKUP DAN ROLLBACK
-------------------
Installer menyimpan file lama di:

storage/app/private/patch-backups/delivery-order-unlimited-pdf-boundary-v1-*

Untuk memulihkan patch terbaru:

php tools/rollback_delivery_order_unlimited_pdf_boundary_v1.php
php artisan optimize:clear

CATATAN
-------
- Backend Surat Jalan memang menerima array item tanpa batas 10; perubahan ini
  memperbaiki UI yang sebelumnya membuat minimal 10 baris statis.
- Installer bersifat idempotent dan memakai preflight. Jika struktur file lokal
  berbeda, patch berhenti sebelum melakukan perubahan.

