CRM PRODUCTION OPERATIONS V2
============================

CAKUPAN
-------
1. Operations Dashboard menampilkan scheduler terakhir, backup terakhir,
   queue worker aktif/tidak, dan email sync terakhir.
2. Backup database harian pukul 02:00.
3. Backup database + storage mingguan hari Minggu pukul 03:00.
4. Retention GFS: daily 14 hari, weekly 8 minggu, monthly 12 bulan,
   yearly 7 tahun. File sumber tidak dihapus oleh migrasi attachment.
5. Index kondisional untuk nomor dokumen, project/date, status, invoice
   relation, expense, movement inventory, dan email.
6. Validasi extension + MIME + file signature untuk upload Admin.
7. Integrasi opsional ClamAV dengan mode fail-closed.
8. Alert CRM otomatis untuk backup/queue/stock/asset hilang atau rusak.
9. Konfigurasi object storage dan command migrasi attachment aman.
10. Pemeriksa APP_ENV, APP_DEBUG, HTTPS, queue, heartbeat, index,
    object storage, antivirus, dan pemisahan kredensial.

INSTALASI
---------
1. Extract ZIP ke root project Laravel. Installer harus berada di:
   tools/apply_crm_production_operations_v2.php

2. Dari PowerShell di root project:

   php tools/apply_crm_production_operations_v2.php
   php tools/check_crm_production_operations_v2.php

   Installer membuat backup file yang disentuh di:
   storage/app/private/patch-backups/crm-production-operations-v2-*

3. Salin nilai yang relevan dari:
   tools/ENV-production-operations-v2.example
   ke file .env. Jangan menyalin placeholder domain/kredensial mentah.

4. Untuk production, minimal gunakan:

   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://domain-crm-anda
   APP_TIMEZONE=Asia/Jakarta
   CRM_FORCE_HTTPS=true
   QUEUE_CONNECTION=database

   Setelah mengubah .env:

   php artisan optimize:clear

5. Jalankan PowerShell sebagai Administrator satu kali:

   powershell -ExecutionPolicy Bypass -File tools/install_windows_crm_workers_v2.ps1

   Script memasang dua Windows Scheduled Tasks:
   - CRM Laravel Scheduler: artisan schedule:run setiap menit.
   - CRM Laravel Queue Worker: queue:work saat Windows startup dan restart
     otomatis bila proses berhenti.

   Jika php.exe tidak ada di PATH:

   powershell -ExecutionPolicy Bypass -File tools/install_windows_crm_workers_v2.ps1 -PhpPath "C:\xampp\php\php.exe"

VERIFIKASI OPERASIONAL
----------------------
Jalankan sekali:

   php artisan crm:health:scheduler
   php artisan crm:health:queue-dispatch
   php artisan crm:email-sync-managed
   php artisan crm:backup-managed --database-only
   php artisan crm:backup-managed
   php artisan crm:operations-alerts
   php artisan crm:production-operations-check

Tunggu maksimal 2-5 menit lalu buka Admin > Operations Dashboard.
Keempat kartu health harus HEALTHY. Queue hanya menjadi healthy setelah
job heartbeat benar-benar diproses oleh queue worker.

OBJECT STORAGE ATTACHMENT
-------------------------
Default tetap local agar attachment lama tidak putus.

1. Konfigurasikan disk `s3` Laravel dengan kredensial aplikasi yang hanya
   boleh membaca/menulis bucket attachment.
2. Uji koneksi:

   php artisan crm:storage:audit --write-probe

3. Simulasikan penyalinan attachment lama:

   php artisan crm:attachments-migrate s3 --from=local

4. Jika tidak ada MISSING, jalankan penyalinan nyata:

   php artisan crm:attachments-migrate s3 --from=local --execute

5. Verifikasi view/download attachment di staging, kemudian set:

   CRM_ATTACHMENT_DISK=s3
   php artisan optimize:clear

Command migrasi tidak menghapus file local. Simpan local sampai restore test
dan seluruh attachment lama sudah diverifikasi.

OBJECT STORAGE BACKUP
---------------------
Gunakan kredensial backup terpisah dan least privilege. Isi variabel
CRM_BACKUP_AWS_* lalu set:

   CRM_BACKUP_OBJECT_DISK=crm-backup-s3

Jalankan manual backup dan pastikan output mencantumkan Object storage.
Backup dianggap gagal jika upload/validasi ukuran remote gagal.

ANTIVIRUS
---------
1. Install ClamAV pada Windows/server dan update signature database.
2. Pastikan command berikut berhasil dari user Windows yang menjalankan PHP:

   clamscan.exe --version

3. Baru set:

   CRM_CLAMAV_ENABLED=true
   CRM_CLAMAV_BINARY=C:\Program Files\ClamAV\clamscan.exe
   CRM_CLAMAV_FAIL_CLOSED=true

Upload PDF dan attachment akan ditolak jika extension, MIME, signature,
atau hasil antivirus tidak valid.

CATATAN HTTPS DAN KREDENSIAL
----------------------------
- HTTPS certificate dan DNS dipasang di Apache/Nginx/reverse proxy, bukan
  dibuat oleh patch Laravel ini.
- `CRM_FORCE_HTTPS=true` membuat URL Laravel memakai https pada production.
- APP_DEBUG wajib false di production.
- Jangan gunakan access key yang sama untuk attachment, backup, database,
  email, dan deployment.
- Simpan .env di luar version control dan rotasi kredensial berkala.

ARSIP READ-ONLY
---------------
Kebijakan read-only V1 tetap dipertahankan: invoice, quote, SPK, surat jalan,
dan movement yang final tidak dihapus. V2 tidak mengubah workflow tersebut.
Data lama tetap tersedia untuk audit; pertumbuhan file dipindahkan ke object
storage dan dikendalikan retention backup, bukan dengan menghapus transaksi.

ROLLBACK FILE
-------------
Jika perlu mengembalikan file aplikasi:

   php tools/rollback_crm_production_operations_v2.php

Rollback file tidak menghapus tabel heartbeat atau index database, karena
penghapusan otomatis dapat berisiko pada production. Keduanya aman dibiarkan.
