# Internal Chat WebSocket (Laravel Reverb) V1

Implementasi ini mengganti polling konstan Internal Chat dengan private WebSocket channels. HTTP tetap digunakan untuk mutasi yang tervalidasi (kirim, edit, hapus, typing) dan untuk sinkronisasi satu kali setelah event. Jika Reverb terputus, endpoint lama otomatis menjadi fallback setiap 30 detik.

## Cakupan

- Pesan baru, edit, hapus, dan read receipt memicu sinkronisasi real-time.
- Sidebar conversation dan unread badge diperbarui oleh event.
- Workflow notification dikirim ke user channel dan popup di-acknowledge agar tidak muncul dua kali.
- Typing indicator memakai event WebSocket; polling typing hanya fallback.
- Private channel conversation hanya boleh diakses oleh member conversation tersebut.
- Kegagalan broadcast tidak menggagalkan penyimpanan pesan.

## Dependency

Jalankan dari root project:

```bash
composer require laravel/reverb:^1.0 --with-all-dependencies
cd packages/Webkul/Admin
npm install
npm run build
cd ../../../
```

`packages/Webkul/Admin/package.json` sudah mendaftarkan `laravel-echo` dan `pusher-js`. Perintah Composer di atas sengaja dijalankan pada environment project agar `composer.lock` dibentuk oleh versi dependency yang benar-benar kompatibel dengan platform PHP server.

## Environment staging

Gunakan credential acak yang berbeda untuk setiap environment. Contoh untuk domain staging saat ini:

```dotenv
BROADCAST_CONNECTION=reverb
BROADCAST_DRIVER=reverb
CRM_INTERNAL_CHAT_REALTIME_ENABLED=true
CRM_INTERNAL_CHAT_FALLBACK_POLL_MS=30000

REVERB_APP_ID=<random-app-id>
REVERB_APP_KEY=<random-public-key>
REVERB_APP_SECRET=<random-secret>
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=crm-capture-it-staging.vartech.id
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=crm-capture-it-staging.vartech.id
```

Jangan commit credential aktual ke GitHub.

Untuk pengujian lokal Windows (`php artisan serve` pada port 8000), gunakan host publik socket `127.0.0.1:8080` dan jalankan Reverb di terminal terpisah:

```dotenv
BROADCAST_CONNECTION=reverb
BROADCAST_DRIVER=reverb
CRM_INTERNAL_CHAT_REALTIME_ENABLED=true
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_ALLOWED_ORIGINS=127.0.0.1
```

```powershell
php artisan reverb:start --debug
```

## Service dan reverse proxy

1. Sesuaikan path dan user pada `deploy/supervisor/crm-reverb.conf.example`.
2. Salin konfigurasi ke Supervisor, lalu jalankan `supervisorctl reread`, `supervisorctl update`, dan `supervisorctl restart crm-reverb`.
3. Tambahkan dua location dari `deploy/nginx/crm-reverb.conf.example` ke server block HTTPS CRM.
4. Jalankan `nginx -t` sebelum reload Nginx.
5. Setelah setiap deployment, jalankan `php artisan reverb:restart` agar proses panjang memuat kode terbaru.

Reverb membutuhkan proxy untuk path `/app` (WebSocket) dan `/apps` (broadcast API). Jangan membuka port 8080 langsung ke internet.

## Aktivasi

```bash
php artisan optimize:clear
php artisan route:list --path=broadcasting/auth
php artisan test --compact tests/Unit/InternalChatRealtimeEventTest.php
php tools/check_internal_chat_websocket_reverb_v1.php
```

Jalankan proses Reverb di production melalui process manager, bukan terminal interaktif:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart crm-reverb
sudo supervisorctl status crm-reverb
```

Jika `BROADCAST_CONNECTION` masih `log` atau `null`, halaman tetap bekerja dalam mode `Fallback`; status itu bukan WebSocket aktif.

Buka dua browser dengan dua akun CRM. Di DevTools > Network harus terlihat koneksi Socket `/app/...`. Kirim, edit, hapus, dan baca pesan dari akun pertama; akun kedua harus berubah tanpa request periodik 2/4/5/12 detik. Badge pada chat menampilkan `Live`. Matikan Reverb sebentar untuk memastikan badge menjadi `Fallback` dan sinkronisasi HTTP 30 detik tetap berjalan.

## Referensi resmi

- Laravel Reverb: https://laravel.com/docs/12.x/reverb
- Laravel Broadcasting dan private channels: https://laravel.com/docs/12.x/broadcasting
