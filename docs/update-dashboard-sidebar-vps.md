# Update Dashboard Inventory dan Dashboard Sales

Perubahan ini menambahkan dua menu utama tepat setelah Dashboard:

1. Dashboard
2. Dashboard Inventory
3. Dashboard Sales
4. Leads dan menu berikutnya

Dashboard Inventory keluar dari submenu Inventory. Tombol Finance & Sales Dashboard pada header Invoices dipindahkan ke sidebar. Halaman Dashboard Sales tetap menampilkan Finance & Sales Dashboard yang sudah ada. Perubahan berlaku pada desktop dan menu mobile.

URL, nama route, perhitungan, dan izin akses dashboard tetap sama. Menu Inventory menuju submenu pertama yang boleh dibuka pengguna. Penanda Dashboard Inventory aktif hanya pada route dashboard, bukan semua URL yang diawali `/admin/inventory`.

## Apa yang dipasang

- `packages/Webkul/Admin/src/Config/menu.php`
- `packages/Webkul/Admin/src/Menu/DashboardMenuItem.php`
- `packages/Webkul/Admin/src/Services/SidebarNavigationService.php`
- Kedua view sidebar desktop/mobile.
- View `invoices/index.blade.php`.
- `tools/check_standalone_dashboard_navigation_v1.php`.

Tidak ada perubahan dependency, database, konfigurasi `.env`, atau aset JavaScript/CSS dalam patch ini. Untuk diff yang hanya memuat patch ini, tidak diperlukan migration database atau build npm. Jika release target memuat perubahan lain, gunakan prosedur lengkap pada [panduan production](../README.production-vps.md#update-aplikasi-berikutnya).

## Pemeriksaan sebelum release

Jalankan di checkout pengembangan/staging dengan PHP 8.3+ dan dependency Composer yang sudah terpasang:

```bash
php -l packages/Webkul/Admin/src/Menu/DashboardMenuItem.php
php -l packages/Webkul/Admin/src/Services/SidebarNavigationService.php
php -l packages/Webkul/Admin/src/Config/menu.php
php -l tools/check_standalone_dashboard_navigation_v1.php
php tools/check_standalone_dashboard_navigation_v1.php
./vendor/bin/pint --test packages/Webkul/Admin/src/Menu/DashboardMenuItem.php packages/Webkul/Admin/src/Services/SidebarNavigationService.php tools/check_standalone_dashboard_navigation_v1.php
```

Checker menguji pemisahan menu, urutan, akses admin dan role terbatas, desktop/mobile tanpa duplikasi, serta penanda menu aktif. Checker menggunakan container terisolasi dan tidak melakukan boot aplikasi, koneksi database, request HTTP, atau perubahan data. Pemeriksaan ini perlu dilengkapi uji tampilan pada staging.

## Memasang di VPS setelah PR digabung

Contoh ini mengikuti panduan repository: folder aplikasi `/var/www/captureit-crm`, PHP-FPM 8.3, Supervisor, serta scheduler pada `/etc/cron.d/captureit-crm`. Gunakan akun deployment dan sesuaikan lokasi serta nama layanan jika instalasi VPS berbeda. Akses VPS dan konfigurasi layanan belum diverifikasi dari percakapan ini.

Jalankan setiap tahap satu per satu. Jika sebuah perintah gagal, selesaikan penyebabnya sebelum melanjutkan. Penggabungan PR di GitHub tidak dengan sendirinya mengubah file di VPS.

### 1. Tentukan commit yang akan dipasang

```bash
cd /var/www/captureit-crm
git status --short
git rev-parse HEAD
git fetch origin
git log --oneline HEAD..origin/main
git diff --name-only HEAD origin/main
```

Pastikan PR sudah digabung ke `main`. Jika ada perubahan lokal, simpan dan tinjau terlebih dahulu. Jangan memakai `git reset --hard` atau menghapus file lokal untuk memaksa update. Periksa daftar perubahan agar langkah khusus patch ini tidak digunakan untuk release yang juga mengubah dependency, schema, atau aset.

Setelah commit target ditinjau:

```bash
CRM_PREVIOUS_SHA=$(git rev-parse HEAD)
CRM_RELEASE_SHA=$(git rev-parse origin/main)
git show --no-patch --oneline "$CRM_RELEASE_SHA"
```

Catat kedua SHA di catatan deployment. Variabel di atas hanya bertahan pada sesi shell yang sama. Jika memasang release lain, isi `CRM_RELEASE_SHA` dengan SHA lengkap dari release yang sudah diperiksa.

### 2. Maintenance dan backup

```bash
php artisan down
```

Hentikan sementara hanya scheduler CRM. Bila menggunakan file cron sesuai contoh, pindahkan file tersebut ke lokasi sementara yang belum dipakai:

```bash
CRM_CRON_HOLD="/etc/captureit-crm-cron-hold-$(date +%Y%m%d-%H%M%S)"
sudo mv --no-clobber /etc/cron.d/captureit-crm "$CRM_CRON_HOLD"
```

Catat nilai `CRM_CRON_HOLD`. Bila scheduler dipasang melalui crontab, timer, atau panel, nonaktifkan jadwal CRM melalui metode yang memang dipakai. Tunggu scheduler/job yang sudah berjalan selesai. Jangan menghentikan layanan cron global.

```bash
sudo supervisorctl stop 'captureit-crm-worker:*' captureit-crm-reverb
php artisan crm:backup-managed
```

Lanjutkan setelah proses CRM yang dapat menulis data sudah berhenti dan backup database beserta attachment berhasil. Simpan backup di lokasi yang dapat dipulihkan. `.env`, `APP_KEY`, database, dan `storage` tetap menggunakan data instalasi yang sudah berjalan.

### 3. Pasang source dan perbarui cache

```bash
git checkout --detach "$CRM_RELEASE_SHA"
php artisan config:clear
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer check-platform-reqs --no-dev
php tools/check_standalone_dashboard_navigation_v1.php
php artisan optimize:clear
php artisan optimize
sudo /usr/sbin/php-fpm8.3 -t
sudo systemctl reload php8.3-fpm
```

Pastikan checkout selesai tanpa konflik. Pertahankan `.env` dan attachment. Jangan menjalankan installer ulang, `key:generate`, atau import database untuk patch navigasi ini.

### 4. Jalankan layanan dan verifikasi

```bash
sudo supervisorctl start 'captureit-crm-worker:*' captureit-crm-reverb
sudo supervisorctl status
```

Pulihkan scheduler dengan metode yang dipakai pada tahap 2. Untuk contoh file cron, pastikan lokasi `/etc/cron.d/captureit-crm` belum berisi file baru sebelum memulihkannya:

```bash
sudo mv --no-clobber "$CRM_CRON_HOLD" /etc/cron.d/captureit-crm
php artisan up
```

Buka `https://crm.captureitphotobooth.id`, lalu lakukan hard refresh dan periksa:

- Dashboard Inventory dan Dashboard Sales tampil langsung di sidebar, termasuk pada mobile.
- Dashboard Inventory tidak lagi ada di submenu Inventory.
- Tombol dashboard pada header Invoices sudah tidak ada. Generate dari Quote tetap tersedia.
- Dashboard Inventory hanya aktif pada halaman dashboard. Inventory Items/Assets mengaktifkan menu Inventory.
- Admin dan role terbatas menerima menu sesuai izin lama. Role dengan `inventory.dashboard` bisa membuka dashboard tanpa harus memperoleh izin inventory lain.
- Dashboard Sales mengikuti salah satu izin lama: `invoices`, `invoices.view`, atau `invoices.financial-report`.
- Filter, angka dashboard, export Sales, invoice, inventory, dan realtime chat tetap berfungsi.

Catat commit yang terpasang dan hasil pemeriksaan. Uji HTTP ini perlu dilakukan pada VPS; keberhasilan checker lokal saja tidak membuktikan konfigurasi VPS sudah benar.

## Jika perlu kembali ke versi sebelumnya

Untuk patch ini saja, schema database tidak berubah. Aktifkan kembali maintenance, hentikan scheduler dan proses CRM seperti tahap 2, kemudian checkout `CRM_PREVIOUS_SHA` yang sudah dicatat. Jalankan Composer install, pembersihan/cache aplikasi, reload PHP-FPM, dan pemulihan layanan seperti di atas. Checker baru tidak ada pada source lama, sehingga lewati hanya checker patch tersebut saat rollback.

Jangan restore database atau menjalankan `migrate:rollback` untuk membatalkan perpindahan menu. Jika release juga memuat perubahan lain, nilai kompatibilitas schema sebelum rollback mengikuti panduan production lengkap.
