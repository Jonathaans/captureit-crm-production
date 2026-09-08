# CaptureIT CRM

Panduan deployment production untuk CaptureIT CRM berbasis Laravel 12/Krayin CRM. Dokumen ini ditujukan untuk Ubuntu VPS dengan Nginx, PHP-FPM, MySQL, Supervisor, Laravel Scheduler, Queue Worker, dan Laravel Reverb WebSocket.

> [!IMPORTANT]
> Jangan mengunggah `.env`, password, private key, backup database, atau credential object storage ke GitHub. Untuk migrasi aplikasi lama, pertahankan `APP_KEY` lama.

## Daftar isi

1. [Arsitektur production](#arsitektur-production)
2. [Persiapan sebelum upload](#persiapan-sebelum-upload)
3. [Upload melalui GitHub](#upload-melalui-github-disarankan)
4. [Upload tanpa GitHub](#upload-tanpa-github-melalui-scp)
5. [Menyiapkan Ubuntu VPS](#menyiapkan-ubuntu-vps)
6. [Database dan environment](#database-dan-environment)
7. [Instalasi aplikasi](#instalasi-aplikasi)
8. [Nginx dan HTTPS](#nginx-dan-https)
9. [Queue, Reverb, dan Scheduler](#queue-reverb-dan-scheduler)
10. [Backup dan restore](#backup-dan-restore)
11. [Verifikasi dan QA](#verifikasi-dan-qa)
12. [Update aplikasi berikutnya](#update-aplikasi-berikutnya)
13. [Troubleshooting](#troubleshooting)
14. [Checklist go-live](#checklist-go-live)

## Arsitektur production

| Komponen | Rekomendasi |
|---|---|
| OS | Ubuntu Server 24.04 LTS |
| Web server | Nginx |
| PHP | PHP 8.3 FPM dan CLI |
| Database | MySQL 8.0.32+ |
| Dependency | Composer 2.5+, Node.js 20/22 LTS |
| Background job | Supervisor + `queue:work` |
| Scheduler | Cron menjalankan `schedule:run` setiap menit |
| Realtime chat | Laravel Reverb di `127.0.0.1:6001` |
| Public WebSocket | WSS melalui Nginx dan port 443 |
| SSL | Let's Encrypt/sertifikat TLS valid |

Port publik yang dibuka cukup `22`, `80`, dan `443`. Jangan membuka MySQL `3306` atau Reverb `6001` ke internet.

## Persiapan sebelum upload

Di komputer pengembang, pastikan semua perubahan sudah diuji dan masuk Git:

```powershell
Set-Location C:\Users\Administrator\Documents\laravel-crm-2.2
git status
git add .
git commit -m "Prepare CaptureIT CRM production release"
git push origin main
git rev-parse HEAD
```

Catat hash commit. Lebih aman membuat tag release:

```powershell
git tag -a v1.0.0 -m "CaptureIT CRM production v1.0.0"
git push origin v1.0.0
```

Sebelum push, pastikan:

- `.env` tidak muncul di `git status`.
- Folder backup dan file database tidak ikut Git.
- Hotfix WebSocket-only, Finance Dashboard, flexible DP/pelunasan, Operations Dashboard, dan inventory sudah masuk commit.
- Checker lokal dan smoke test utama lulus.

## Upload melalui GitHub (disarankan)

Metode ini memudahkan deployment dan rollback.

### Repository public

Di VPS, login sebagai user deployment lalu clone release:

```bash
sudo mkdir -p /var/www
sudo chown "$USER":www-data /var/www
cd /var/www
git clone https://github.com/Jonathaans/captureit-crm-production.git captureit-crm
cd captureit-crm
git fetch --all --tags
git checkout v1.0.0
git rev-parse HEAD
```

Ganti `v1.0.0` dengan tag atau commit yang benar-benar sudah diuji.

### Repository private

Gunakan GitHub Deploy Key read-only atau SSH key milik service account. Jangan menaruh Personal Access Token di URL clone atau di README.

```bash
git clone git@github.com:Jonathaans/captureit-crm-production.git /var/www/captureit-crm
```

## Upload tanpa GitHub melalui SCP

Metode ini dapat digunakan jika VPS tidak boleh menarik source langsung dari GitHub.

Di PowerShell komputer pengembang:

```powershell
Set-Location C:\Users\Administrator\Documents\laravel-crm-2.2
git archive --format=zip --output=captureit-crm.zip HEAD
scp .\captureit-crm.zip USER_VPS@IP_VPS:/tmp/
```

Di VPS:

```bash
sudo mkdir -p /var/www/captureit-crm
sudo chown "$USER":www-data /var/www/captureit-crm
unzip /tmp/captureit-crm.zip -d /var/www/captureit-crm
cd /var/www/captureit-crm
```

Source archive tidak membawa `.env`, database, attachment runtime, atau backup. Data tersebut dipindahkan secara terpisah melalui backup CRM yang terenkripsi dan jalur aman.

## Menyiapkan Ubuntu VPS

Jalankan sebagai user yang memiliki akses `sudo`:

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y nginx mysql-server supervisor git unzip curl ca-certificates composer rsync ufw \
  php8.3-cli php8.3-fpm php8.3-mysql php8.3-bcmath php8.3-curl \
  php8.3-gd php8.3-intl php8.3-mbstring php8.3-xml php8.3-zip
```

Install Node.js 22 LTS dari NodeSource. Unduh script terlebih dahulu agar dapat diperiksa sebelum dijalankan:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x -o /tmp/nodesource_setup.sh
less /tmp/nodesource_setup.sh
sudo -E bash /tmp/nodesource_setup.sh
sudo apt install -y nodejs
rm /tmp/nodesource_setup.sh
```

Setelah itu periksa seluruh versi:

```bash
php -v
php -m
composer --version
node --version
npm --version
mysql --version
nginx -v
```

Repository memerlukan PHP `^8.3` dan mematok platform Composer PHP `8.3.30`. Jangan menggunakan `--ignore-platform-reqs`.

Amankan MySQL dan firewall:

```bash
sudo mysql_secure_installation
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

Jangan menutup sesi SSH aktif sebelum memastikan rule `OpenSSH` sudah diizinkan.

## Database dan environment

### Membuat database

Masuk ke MySQL:

```bash
sudo mysql
```

Jalankan SQL berikut dengan password acak yang kuat:

```sql
CREATE DATABASE crm_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crm_app'@'127.0.0.1' IDENTIFIED BY 'GANTI_PASSWORD_DATABASE_KUAT';
GRANT ALL PRIVILEGES ON crm_production.* TO 'crm_app'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

Jangan menggunakan akun MySQL `root` untuk aplikasi.

### Membuat `.env`

```bash
cd /var/www/captureit-crm
cp .env.example .env
nano .env
```

Contoh nilai production:

```dotenv
APP_NAME="CaptureIT CRM"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://crm.domainanda.com
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
APP_CURRENCY=IDR

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crm_production
DB_USERNAME=crm_app
DB_PASSWORD=GANTI_PASSWORD_DATABASE_KUAT
DB_PREFIX=

BROADCAST_CONNECTION=reverb
BROADCAST_DRIVER=reverb
CACHE_DRIVER=file
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

CRM_FORCE_HTTPS=true
CRM_INTERNAL_CHAT_REALTIME_ENABLED=true

REVERB_APP_ID=GANTI_ID_ACAK
REVERB_APP_KEY=GANTI_KEY_ACAK
REVERB_APP_SECRET=GANTI_SECRET_PANJANG
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=6001
REVERB_HOST=crm.domainanda.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=crm.domainanda.com

CRM_DAILY_DATABASE_BACKUP_TIME=02:00
CRM_WEEKLY_FULL_BACKUP_DAY=0
CRM_WEEKLY_FULL_BACKUP_TIME=03:00
CRM_BACKUP_KEEP_DAILY_DAYS=14
CRM_BACKUP_KEEP_WEEKLY_WEEKS=8
CRM_BACKUP_KEEP_MONTHLY_MONTHS=12
CRM_BACKUP_KEEP_YEARLY_YEARS=7

CRM_ATTACHMENT_DISK=local
CRM_UPLOAD_MAX_KB=20480
CRM_CLAMAV_ENABLED=false
CRM_BACKUP_OBJECT_DISK=
```

Isi SMTP, IMAP, dan credential lain sesuai provider perusahaan. `CRM_BACKUP_OBJECT_DISK` dibiarkan kosong sampai object storage dan credential-nya benar-benar siap.

Atur izin `.env`:

```bash
sudo chown "$USER":www-data /var/www/captureit-crm/.env
sudo chmod 640 /var/www/captureit-crm/.env
```

Jalankan perintah deployment dari akun VPS khusus, bukan dari akun aplikasi publik.

### Aturan `APP_KEY`

- Instalasi benar-benar kosong: jalankan `php artisan key:generate` satu kali.
- Migrasi aplikasi lama: salin `APP_KEY` lama; jangan menjalankan `key:generate`.
- Simpan recovery copy `APP_KEY` di password manager perusahaan.

Mengganti `APP_KEY` pada sistem yang sudah berisi data dapat merusak session dan data terenkripsi.

## Instalasi aplikasi

### Dependency backend dan frontend

```bash
cd /var/www/captureit-crm
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

cd packages/Webkul/Admin
npm install
npm run build
cd ../../..
```

Repository saat ini tidak memiliki `package-lock.json`, sehingga gunakan `npm install`, bukan `npm ci`. Jangan menjalankan `npm run dev` di production.

### Pilih satu jalur database

#### A. Instalasi benar-benar kosong

```bash
cd /var/www/captureit-crm
php artisan key:generate
php artisan krayin-crm:install
php artisan migrate --force
```

Buat akun Admin sendiri dan segera ganti seluruh password default.

#### B. Migrasi database dan attachment lama

Di server lama, buat full backup:

```bash
php artisan crm:backup-managed
```

Lokasi default:

```text
storage/app/private/crm-backups/crm-backup-YYYYMMDD-HHMMSS.zip
```

Pindahkan ZIP melalui jalur aman, lalu:

1. Salin `APP_KEY` lama ke `.env` VPS.
2. Extract backup di folder sementara.
3. Import `database.sql` ke database kosong.
4. Salin isi `storage-app` ke `storage/app` aplikasi baru.
5. Jangan menjalankan `key:generate`.
6. Jangan menjalankan `krayin-crm:install`.
7. Jalankan migration release baru.

```bash
mysql -h 127.0.0.1 -u crm_app -p crm_production < /path/aman/database.sql
rsync -a /path/aman/storage-app/ /var/www/captureit-crm/storage/app/
cd /var/www/captureit-crm
php artisan migrate --force
php artisan migrate:status
```

### Storage, cache, dan permission

```bash
cd /var/www/captureit-crm
php artisan storage:link
php artisan optimize:clear

sudo chown -R "$USER":www-data /var/www/captureit-crm
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 0664 {} \;

php artisan optimize
```

Hindari `chmod -R 777`.

## Nginx dan HTTPS

Buat konfigurasi:

```bash
sudo nano /etc/nginx/sites-available/captureit-crm
```

Isi awal:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name crm.domainanda.com;

    root /var/www/captureit-crm/public;
    index index.php;
    client_max_body_size 25M;

    access_log /var/log/nginx/captureit-crm-access.log;
    error_log  /var/log/nginx/captureit-crm-error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /apps/ {
        proxy_pass http://127.0.0.1:6001;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /app/ {
        proxy_pass http://127.0.0.1:6001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 60s;
        proxy_send_timeout 60s;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan site dan uji konfigurasi:

```bash
sudo ln -s /etc/nginx/sites-available/captureit-crm /etc/nginx/sites-enabled/captureit-crm
sudo nginx -t
sudo systemctl reload nginx
```

Pastikan DNS domain sudah menunjuk ke IP VPS, lalu pasang HTTPS:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d crm.domainanda.com
sudo certbot renew --dry-run
```

Setelah sertifikat aktif, pastikan HTTP dialihkan ke HTTPS dan endpoint Reverb tetap berada pada virtual host HTTPS yang sama.

## Queue, Reverb, dan Scheduler

### Supervisor Queue Worker

```bash
sudo nano /etc/supervisor/conf.d/captureit-crm-worker.conf
```

```ini
[program:captureit-crm-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/captureit-crm/artisan queue:work --queue=default --sleep=3 --tries=3 --timeout=120 --max-time=3600
directory=/var/www/captureit-crm
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/captureit-crm/storage/logs/queue-worker.log
stopwaitsecs=3600
```

### Supervisor Laravel Reverb

```bash
sudo nano /etc/supervisor/conf.d/captureit-crm-reverb.conf
```

```ini
[program:captureit-crm-reverb]
command=/usr/bin/php /var/www/captureit-crm/artisan reverb:start --host=127.0.0.1 --port=6001
directory=/var/www/captureit-crm
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/captureit-crm/storage/logs/reverb.log
stopwaitsecs=30
```

Aktifkan keduanya:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start captureit-crm-worker:*
sudo supervisorctl start captureit-crm-reverb
sudo supervisorctl status
sudo ss -lntp | grep 6001
```

Reverb harus listen pada `127.0.0.1:6001`, bukan `0.0.0.0:6001`.

### Laravel Scheduler

Buat satu cron saja:

```bash
sudo nano /etc/cron.d/captureit-crm
```

```cron
* * * * * www-data cd /var/www/captureit-crm && /usr/bin/php artisan schedule:run >> /var/www/captureit-crm/storage/logs/scheduler.log 2>&1
```

```bash
sudo chmod 644 /etc/cron.d/captureit-crm
sudo systemctl restart cron
php artisan schedule:list
```

Scheduler aplikasi menangani heartbeat, email sync, backup harian, full backup mingguan, retention, dan operational alert. Jangan membuat cron terpisah untuk job yang sudah terdaftar di `routes/console.php`.

## Backup dan restore

Uji backup setelah deployment:

```bash
cd /var/www/captureit-crm
php artisan crm:backup-managed --database-only
php artisan crm:backup-managed
```

Periksa:

```text
storage/app/private/crm-backups
```

Jadwal default:

- Database backup setiap hari pukul 02:00.
- Full database + storage setiap Minggu pukul 03:00.
- Retention dijalankan setiap hari.
- Scheduler tetap berjalan setiap menit, tetapi itu bukan berarti backup dibuat setiap menit.

Backup pada VPS yang sama bukan disaster recovery. Simpan salinan terenkripsi di object storage, NAS, atau akun cloud terpisah.

### Uji restore bulanan

Jangan pernah menguji restore langsung ke `crm_production`.

1. Ambil backup off-site terbaru.
2. Buat database `crm_restore_test`.
3. Import `database.sql` ke database test.
4. Gunakan copy aplikasi/staging dengan `.env` khusus test.
5. Pastikan queue, email, dan domain production tidak digunakan oleh staging.
6. Login dan periksa Invoice, Quote, SPK, Surat Jalan, Inventory, serta attachment.
7. Catat nama backup, tanggal, ukuran, durasi, hasil, dan petugas penguji.

## Verifikasi dan QA

Jalankan dari root project:

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan route:list --path=admin
php tools/check_crm_production_operations_v2.php
php artisan crm:production-operations-check
```

Jika checker tersedia pada release:

```bash
php tools/check_internal_chat_websocket_only_v2.php
php tools/check_finance_sales_dashboard_style_stability_hotfix_v1_2.php
```

Target Operations Dashboard:

- Scheduler: `HEALTHY`.
- Backup Terakhir: `HEALTHY` setelah backup pertama.
- Queue Worker: `HEALTHY`.
- Email Sync: `HEALTHY` setelah akun email diuji.

### Test WebSocket

1. Buka Internal Chat dengan dua akun berbeda.
2. Buka DevTools → Network → Socket/WS.
3. Koneksi `/app/...` harus berstatus `101 Switching Protocols`.
4. Private channel auth harus `200`.
5. Pesan harus muncul realtime tanpa refresh.
6. Tidak boleh ada request polling berulang seperti `poll`, `sidebar-summary`, `unread-summary`, atau `typing-status`.
7. Matikan Reverb sementara; UI harus menunjukkan offline.
8. Hidupkan kembali; UI harus reconnect dan melakukan satu kali resync.

### Smoke test bisnis

- Login, logout, role, dan ACL.
- Lead → Quote → Invoice.
- DP nominal fleksibel dan DP persentase.
- Payment DP → Pelunasan tanpa double-count.
- Financial Report dan Finance & Sales Dashboard.
- Export Financial Report dan Export All Expenses.
- SPK, Surat Jalan dengan lebih dari 10 item, Allocation, Picking, Return.
- Missing/Damaged Recovery dan Inventory Movement audit trail.
- Quote/Invoice PDF multipage tidak menabrak header.
- SMTP, IMAP sync, notification, attachment chat.
- Database backup, full backup, dan restore ke database test.

## Update aplikasi berikutnya

Gunakan tag/commit baru yang sudah lolos QA:

```bash
cd /var/www/captureit-crm
php artisan down
php artisan crm:backup-managed --database-only

git fetch --all --tags
git checkout TAG_ATAU_COMMIT_BARU

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

cd packages/Webkul/Admin
npm install
npm run build
cd ../../..

php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart

sudo supervisorctl restart captureit-crm-worker:*
sudo supervisorctl restart captureit-crm-reverb
sudo supervisorctl status

php artisan up
```

Jika deployment gagal ketika maintenance mode masih aktif, perbaiki penyebabnya lalu jalankan `php artisan up` hanya setelah aplikasi aman dibuka kembali.

Setelah update, ulangi production checker dan smoke test kritis.

## Troubleshooting

### HTTP 500 atau halaman kosong

```bash
php artisan optimize:clear
tail -n 200 storage/logs/laravel.log
sudo tail -n 200 /var/log/nginx/captureit-crm-error.log
```

Periksa permission `storage`, `bootstrap/cache`, `.env`, koneksi database, dan PHP-FPM.

### Queue Worker warning

```bash
sudo supervisorctl status captureit-crm-worker:*
php artisan queue:failed
tail -n 200 storage/logs/queue-worker.log
```

Pastikan `QUEUE_CONNECTION=database`, migration jobs tersedia, dan worker berjalan.

### Scheduler warning

```bash
php artisan schedule:run
tail -n 200 storage/logs/scheduler.log
```

Pastikan hanya ada satu cron `schedule:run`.

### WebSocket gagal

```bash
sudo supervisorctl status captureit-crm-reverb
sudo ss -lntp | grep 6001
tail -n 200 storage/logs/reverb.log
sudo nginx -t
```

Periksa `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`, `REVERB_ALLOWED_ORIGINS`, sertifikat HTTPS, dan reverse proxy Nginx.

### UI tidak memiliki style setelah build

```bash
cd /var/www/captureit-crm/packages/Webkul/Admin
npm install
npm run build
cd ../../..
php artisan optimize:clear
```

Lakukan hard refresh browser setelah build selesai.

### Backup gagal

Periksa ekstensi ZIP, permission folder backup, ruang disk, dan log:

```bash
php -m | grep -i zip
df -h
tail -n 200 storage/logs/crm-database-backup.log
tail -n 200 storage/logs/crm-full-backup.log
```

## Checklist go-live

- [ ] Tag/commit release dicatat.
- [ ] `.env` tidak ada di GitHub.
- [ ] `APP_ENV=production`.
- [ ] `APP_DEBUG=false`.
- [ ] `APP_URL` menggunakan HTTPS.
- [ ] `APP_KEY` benar dan recovery copy disimpan aman.
- [ ] Database user bukan root.
- [ ] Composer `--no-dev` selesai tanpa error.
- [ ] Frontend production build berhasil.
- [ ] Semua migration selesai.
- [ ] Nginx root menuju folder `public`.
- [ ] Permission tidak menggunakan `777`.
- [ ] Queue Worker aktif di Supervisor.
- [ ] Laravel Reverb aktif di Supervisor.
- [ ] Scheduler cron aktif dan hanya satu.
- [ ] WebSocket menunjukkan status `101`.
- [ ] Operations Dashboard menunjukkan health yang sehat.
- [ ] Full QA flow bisnis selesai.
- [ ] Database backup dan full backup berhasil.
- [ ] Copy backup off-site tersedia.
- [ ] Restore test berhasil.
- [ ] Monitoring disk dan log aktif.
- [ ] Role/ACL serta password Admin sudah diatur.

## Catatan Windows Server

Untuk server Windows, gunakan script repository berikut sebagai Administrator:

```powershell
PowerShell -ExecutionPolicy Bypass -File .\tools\install_windows_crm_workers_v2.ps1 `
  -ProjectPath "C:\Apps\captureit-crm" `
  -PhpPath "C:\xampp\php\php.exe"
```

Task yang harus aktif:

- `CRM Laravel Scheduler`
- `CRM Laravel Queue Worker`
- `CRM Laravel Reverb` pada `127.0.0.1:6001`

Gunakan Apache/Nginx production, arahkan DocumentRoot ke folder `public`, dan jangan memakai `php artisan serve` untuk production.

## License dan basis proyek

CaptureIT CRM dibangun di atas [Krayin CRM](https://krayincrm.com) dan Laravel. Lihat file [LICENSE](LICENSE) untuk ketentuan lisensi repository.
