CRM TARGETED PERFORMANCE OPTIMIZATION V1
========================================

Tujuan
------
Patch ini mengoptimalkan tiga hot path hasil pengujian data Medium:

1. Internal Chat
   - Menghilangkan query per-conversation pada initial page.
   - Menghilangkan query per-conversation pada sidebar realtime.
   - Tetap WebSocket-only; tidak menambahkan polling.
   - Tetap hanya memuat 50 pesan terbaru ketika membuka room.

2. Invoice
   - Join invoice_items hanya dilakukan ketika filter Product digunakan.
   - Query normal tidak lagi membutuhkan DISTINCT atas row item yang berlipat.
   - Daftar opsi Product dicache selama 5 menit dan dipisahkan per database.

3. Quote
   - Default sorting menggunakan quotes.id yang qualified dan terindeks.

4. Database
   - Menambahkan composite index untuk chat, invoice, invoice_items, dan quote.
   - Tidak mengubah atau menghapus data bisnis.


ISI ZIP
-------
tools/apply_crm_targeted_performance_optimization_v1.php
tools/check_crm_targeted_performance_optimization_v1.php


PERSIAPAN
---------
1. Jangan bersihkan data Medium.
2. Pastikan Git/worktree memiliki backup atau commit terakhir.
3. Extract ZIP ke root project:

   C:\Users\Administrator\Documents\laravel-crm-2.2

4. Hasilnya harus berada di:

   tools\apply_crm_targeted_performance_optimization_v1.php
   tools\check_crm_targeted_performance_optimization_v1.php


APPLY KODE
----------
Jalankan dari root project:

php tools/apply_crm_targeted_performance_optimization_v1.php

Installer membuat backup otomatis di tools/backups sebelum mengubah file.


APPLY MIGRATION KE DATABASE PERFORMANCE
---------------------------------------
Gunakan terminal PowerShell baru. Jangan mengubah .env permanen.

$env:DB_DATABASE="captureit_crm_performance"
php artisan optimize:clear
php artisan migrate --force
php tools/check_crm_targeted_performance_optimization_v1.php --database=captureit_crm_performance

Jika checker seluruhnya [OK], hapus override terminal:

Remove-Item Env:DB_DATABASE -ErrorAction SilentlyContinue
php artisan optimize:clear


JALANKAN SERVER TEST PERFORMANCE
-------------------------------
Terminal khusus server performance:

$env:DB_DATABASE="captureit_crm_performance"
$env:APP_URL="http://127.0.0.1:8001"
$env:APP_DEBUG="false"
$env:DEBUGBAR_ENABLED="false"
Remove-Item Env:ASSET_URL -ErrorAction SilentlyContinue
php artisan optimize:clear
php artisan serve --host=127.0.0.1 --port=8001

Buka:

http://127.0.0.1:8001/admin/quotes
http://127.0.0.1:8001/admin/invoices
http://127.0.0.1:8001/admin/internal-chat


CARA MEMBANDINGKAN
------------------
Untuk setiap halaman:

1. Buka F12 > Network.
2. Pastikan filter Network kosong.
3. Reload halaman tiga kali.
4. Klik request bertipe document, lalu buka Timing.
5. Catat Waiting for server response dan Duration.
6. Untuk Quote/Invoice, catat juga request Fetch/XHR datagrid.
7. Untuk Internal Chat, ukur:
   - Halaman tanpa conversation.
   - Membuka satu conversation.
   - Request sidebar-summary setelah WebSocket connected.

Target lokal development setelah warm-up:

- Quote/Invoice document: di bawah sekitar 2 detik.
- Quote/Invoice XHR datagrid: di bawah sekitar 1 detik.
- Internal Chat document: di bawah sekitar 2 detik.
- sidebar-summary: di bawah sekitar 500 ms.

php artisan serve tetap server development single-process. Hasil VPS produksi
dengan Nginx + PHP-FPM + OPcache dapat berbeda.


SETELAH TEST BERHASIL
--------------------
Migration juga harus diterapkan ke database utama saat siap:

php artisan optimize:clear
php artisan migrate
php tools/check_crm_targeted_performance_optimization_v1.php

Pastikan .env kembali memakai DB_DATABASE=db_crm sebelum perintah tersebut.


CATATAN KEAMANAN
----------------
- Migration hanya menambah index dan bersifat idempotent berdasarkan nama index.
- Tidak ada DELETE, TRUNCATE, DROP TABLE, atau perubahan isi data bisnis.
- Rollback migration hanya melepas index yang dibuat patch ini.
- WebSocket/Reverb tidak diubah dan polling tidak diaktifkan kembali.

