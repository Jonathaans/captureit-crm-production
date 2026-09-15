# Delivery Order Return Manual Input V1

Fitur ini menambahkan jalur cadangan ketika QR asset rusak atau tidak dapat
dibaca. Staff dapat memasukkan Asset Code, Barcode, atau Serial Number pada
halaman Return Warehouse.

Input manual menggunakan route admin.delivery-orders.return.scan-check-in
dan field barcode yang sama dengan scanner. Controller tetap memvalidasi:

- asset ditemukan;
- asset serialized adalah bagian dari Surat Jalan yang sedang diproses;
- status allocation adalah out, return_pending, atau returned;
- perubahan status dilakukan melalui DeliveryOrderReturnService.

Asset Code dan Barcode diprioritaskan karena unik. Serial Number hanya diterima
jika menemukan tepat satu asset. Serial Number yang dipakai lebih dari satu
asset ditolak dan staff diminta menggunakan Asset Code.

## Perubahan

- Tombol **Input Manual** dan panel form responsif memakai disclosure native
  HTML, sehingga panel tetap dapat dibuka tanpa JavaScript.
- Form HTML tetap berfungsi jika JavaScript gagal.
- Pada mode JavaScript, input manual menggunakan antrean scanner yang sama.
- Feedback sukses, duplikat, dan error memakai komponen pesan scanner.
- Field manual tidak ditangkap oleh listener keyboard scanner.
- Tombol hanya tersedia bagi pengguna dengan izin
  delivery-orders.return.check-in.

Tidak ada perubahan schema database, migration, dependency Composer, atau aset
JavaScript/CSS. Patch ini tidak memerlukan php artisan migrate maupun build
npm.

## Pemeriksaan di VPS production

Pemeriksaan ini tidak memerlukan dependency development:

    php -l packages/Webkul/Admin/src/Http/Controllers/DeliveryOrder/DeliveryOrderReturnController.php
    php -l tools/check_delivery_order_return_manual_input_v1.php
    php tools/check_delivery_order_return_manual_input_v1.php

`php artisan test` tidak dijalankan di VPS production jika command tersebut
tidak tersedia.

## Pemeriksaan di development

Jika dependency development/Pest terpasang, jalankan:

    ./vendor/bin/pest tests/Unit/DeliveryOrderReturnManualInputTest.php

Uji browser dengan akun yang memiliki izin Check-In:

1. Scan QR normal tetap berhasil.
2. Buka **Input Manual**, masukkan Asset Code valid, dan pastikan menjadi
   RECEIVED.
3. Masukkan kode asset dari Surat Jalan lain dan pastikan ditolak.
4. Masukkan kode yang tidak ada dan pastikan ditolak.
5. Masukkan kembali asset yang sudah diterima dan pastikan menjadi pesan
   duplikat tanpa movement tambahan.
6. Uji Serial Number unik dan Serial Number ambigu.
7. Pastikan role tanpa izin Check-In tidak melihat tombol manual.

Setelah patch diterapkan:

    php artisan optimize:clear
    php artisan optimize
    sudo systemctl reload php8.3-fpm
