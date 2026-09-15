# ACL Login Landing Fix V1

Perbaikan ini memastikan semua akun aktif dengan sedikitnya satu menu yang diizinkan dapat login tanpa dipaksa membuka `/admin/dashboard`.

## Perilaku

- Halaman setelah login adalah menu pertama dari sidebar yang sudah difilter ACL.
- Nama role tidak di-hardcode. Perubahan ACL otomatis mengubah menu dan halaman awal pengguna.
- Session diregenerasi setelah login.
- `url.intended` akun sebelumnya dihapus.
- Logout menghapus data session dan meregenerasi token CSRF.
- Login setelah reset password menggunakan tujuan ACL yang sama.
- Tautan pada halaman 401/403 dan logo header menuju halaman pertama yang diizinkan.
- `/admin/dashboard` tetap tersedia hanya untuk akun yang memiliki izin `dashboard`.

## Penerapan di server

Tidak ada migration database untuk perbaikan ini.

```bash
cd /var/www/captureit-crm

git apply --check acl-login-landing-fix-v1.patch
git apply acl-login-landing-fix-v1.patch

php artisan optimize:clear
php tools/check_acl_login_landing_v1.php
```

## Uji manual

1. Login sebagai Administrator dan pastikan masuk ke menu pertama yang diizinkan.
2. Logout.
3. Login pada browser yang sama sebagai Warehouse Staff.
4. Pastikan login berhasil dan tidak diarahkan ke `/admin/dashboard` jika ACL dashboard tidak aktif.
5. Pastikan sidebar hanya berisi menu yang diizinkan.
6. Logout lalu ulangi dengan Sales User dan Head Warehouse.
7. Coba membuka URL yang tidak diizinkan. Dari halaman error, klik `Go Back` dan pastikan kembali ke menu yang diizinkan.
