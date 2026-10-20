# Perbaikan ACL role

## Masalah dan perilaku baru

- Izin `invoices.edit` sebelumnya tidak terdaftar. Invoice yang terhubung ke Quote memerlukan izin revisi yang berbeda. Sekarang **Edit Invoice / Proforma & Revisi Transaksi** membuka dan menyimpan revisi dokumen terkait. Izin lama `invoices.revise` tetap kompatibel. Pembayaran, void, expense, cetak, dan ekspor tetap memerlukan izin masing-masing.
- Pemetaan rute tidak lagi bergantung pada urutan konfigurasi: Create dan Quick Add untuk Lead, Person, Organization, Product, dan Mail diperiksa sesuai jenis permintaan. Quick Add hanya berlaku pada POST dengan penanda `quick_add`.
- Pemetaan Warehouse, Import, identitas Contact, edit inline Lead/Product, ekspor pengeluaran, dan My Email dilengkapi. Endpoint admin yang sebelumnya didaftarkan hanya dengan middleware `web` sekarang juga menjalankan autentikasi, status akun, dan ACL.
- Rute admin tanpa kebijakan ACL ditolak untuk role custom. Helper form, navigasi, serta fitur yang dibatasi ke akun sendiri mempunyai daftar pengecualian eksplisit dalam `Config/acl_routes.php`. Pengunduhan atribut memeriksa bahwa file terdaftar dan modulnya boleh diakses.
- Tombol invoice, ekspor dashboard, Web Form, serta penghapusan massal Campaign/Event memakai izin yang sama dengan server. Penyimpanan role memvalidasi array permission dan mempertahankan pilihan saat validasi form gagal.
- Alias Vendor lama dan Generate Delivery Order lama tetap kompatibel; endpoint Generate Delivery Order lama memang membuat SPK, sedangkan penerbitan Surat Jalan dari SPK tetap memakai `work-orders.delivery-orders`.

Izin parent tidak otomatis memberikan semua child melalui server. Checkbox parent di form role tetap dapat dipakai untuk memilih seluruh child secara eksplisit. Pembatasan kepemilikan data, periode terkunci, status transaksi, serta fitur khusus Administrator yang sudah ada tetap berlaku.

## Mengaktifkan akses Sales Admin

1. Masuk sebagai Administrator, buka **Settings → Roles → Sales Admin**.
2. Gunakan permission type **Custom**. Pada Invoice, aktifkan akses daftar Invoice, **View**, dan **Edit Invoice / Proforma & Revisi Transaksi**. Tambahkan izin lain hanya jika dibutuhkan.
3. Simpan. Gunakan akun Sales Admin di jendela browser terpisah, lalu muat ulang halaman. Role yang sebelumnya memakai **Revisi Transaksi (izin lama)** tetap dapat merevisi.
4. Buka Invoice → **Edit Invoice / Revisi Transaksi**, ubah nominal, isi alasan, lalu simpan. Quote, Proforma, Invoice, saldo outstanding, dan financial report memakai nilai hasil revisi yang sama. Catatan pembayaran tetap terjaga.
5. Cabut izin Edit/Revisi, simpan, lalu muat ulang akun Sales Admin. Tombol edit hilang dan URL GET/PUT revisi langsung juga ditolak. Jika kedua izin ekuivalen dicentang, cabut keduanya.

## Update VPS setelah PR di-merge

Jalankan dari direktori aplikasi yang biasa digunakan. Tidak ada migrasi atau perubahan aset build khusus pada perbaikan ACL ini.

```bash
cd /var/www/captureit-crm &&
php artisan down &&
git pull --ff-only origin main &&
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader &&
php artisan migrate --force &&
php artisan optimize:clear &&
php artisan view:cache &&
php artisan queue:restart &&
php artisan up
```

## Verifikasi

Uji otomatis mencakup seluruh katalog rute admin: rute harus terdaftar pada ACL atau pengecualian eksplisit, tidak boleh ada rute ACL duplikat, dan endpoint harus memiliki middleware. Matriks akses menguji setiap permission yang dipetakan dengan role yang memiliki izin tersebut dan role yang tidak memilikinya.

Uji HTTP terpisah mencakup revisi invoice/proforma oleh Sales Admin, pembaruan financial report, pencabutan izin, invoice standalone, endpoint provider, penyimpanan role ke database, dan file atribut. Pengujian browser memeriksa nilai checkbox role yang tersimpan, pilihan Edit Invoice, data form yang dikirim, serta pencabutannya tanpa memilih Pembayaran/Ekspor.

```bash
php artisan test --compact tests/Unit/AclRouteCoverageTest.php tests/Unit/AclMenuPermissionsTest.php tests/Unit/RolePermissionPersistenceTest.php tests/Unit/ProductCreatePermissionTest.php tests/Unit/AttributeDownloadPermissionTest.php tests/Unit/CommercialRevisionsTest.php
```

Penambahan endpoint admin berikutnya harus menyertakan pemetaan ACL atau pengecualian yang spesifik dan dapat dijelaskan; uji cakupan akan gagal jika pemetaan terlupa.
