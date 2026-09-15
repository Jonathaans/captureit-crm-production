# Sales Commission Export Permission Fix V1

Perbaikan ini menangani HTTP 500 pada endpoint Export CSV Komisi Sales ketika
role pengguna menyimpan `permissions` sebagai `null`.

Halaman Finance & Sales Dashboard sudah memakai helper `bouncer()` untuk
otorisasi, sedangkan controller export sebelumnya memanggil
`User::hasPermission()` secara langsung. Implementasi lama dapat meneruskan
nilai `null` ke `in_array()`.

Controller export sekarang memakai allowlist nama role yang dinormalisasi dan
hanya menerima `Administrator` atau `SuperAdministrator`. Panel Export Deal
Lunas per Sales juga tidak dirender untuk role lain. Pemeriksaan tetap dilakukan
di backend agar URL export tidak dapat dipanggil langsung oleh role yang tidak
diizinkan.

Tidak ada perubahan pada data user, role, invoice, payment, perhitungan komisi,
schema database, atau format CSV.

## Pemeriksaan production

    php -l packages/Webkul/Admin/src/Http/Controllers/Invoice/SalesCommissionExportController.php
    php -l tools/check_sales_commission_paid_deals_export_v1.php
    php tools/check_sales_commission_paid_deals_export_v1.php
    php artisan optimize:clear
    php artisan optimize

Setelah pemeriksaan lulus, login dengan akun yang dapat membuka Finance & Sales
Dashboard dan jalankan Export CSV Komisi Sales.
