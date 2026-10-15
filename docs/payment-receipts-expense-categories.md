# Bon pembayaran dan kategori expense

Invoice → Input Payment sekarang menerima satu bon opsional berupa PDF, JPG/JPEG,
atau PNG, maksimal 5 MB. Bon muncul sebagai tautan **Unduh bon** di riwayat
pembayaran. Pembayaran lama tetap dapat dibuka tanpa bon.

Settings → Keuangan → **Kategori Expense** menyediakan tambah kategori, ubah nama
dan deskripsi, serta aktif/nonaktif. Pencarian tersedia pada halaman Settings dan
daftar kategori. Kategori aktif tersedia pada form tambah/edit expense.
Kategori nonaktif masih dapat dipertahankan saat mengedit expense lama, tetapi
tidak dapat dipilih untuk transaksi baru. Tidak ada penghapusan kategori agar
riwayat tetap terbaca.

Nama kategori yang diperbarui tampil pada riwayat invoice, ringkasan kategori di
Financial Report, serta kedua ekspor CSV expense. Kode kategori pada expense
tetap sama; mengganti nama/status tidak mengubah nominal atau tanggal transaksi.
Kategori bawaan dan kode kategori yang sudah digunakan expense lama diimpor
oleh migration. Posting expense otomatis dari Purchase Order tetap mengikuti
alur yang sudah ada.

## Penyimpanan dan akses

- Bon pembayaran disimpan pada disk `local`, di
  `invoice-payment-receipts/{invoice_id}/...` (konfigurasi bawaan:
  `storage/app/invoice-payment-receipts`). Sertakan direktori ini dalam backup.
- File diunduh melalui route yang memeriksa login, izin `invoices.view`, dan
  pasangan invoice/payment. File tidak menggunakan URL `public/storage`.
- Input pembayaran menggunakan izin `invoices.payment` yang sudah ada.
- Settings kategori menggunakan izin terpisah
  `settings.finance.expense-categories`, `.create`, dan `.edit`.
  Pengguna dengan role `all` langsung memiliki akses; role khusus perlu dicentang
  melalui Settings → User → Roles.
- File diperiksa berdasarkan ekstensi, MIME, signature PDF/struktur gambar, dan
  ukuran. Jika penyimpanan atau pencatatan gagal, pembayaran tidak tersimpan dan
  saldo invoice tidak berubah. Bon tetap tersedia pada riwayat yang di-VOID.
- Perhitungan nominal tetap menggunakan PaymentService yang ada, termasuk nilai
  transfer netto PPh, saldo, status pembayaran, dan financial report.

## Pembaruan di VPS

Setelah PR di-merge, gunakan prosedur backup/deploy biasa. Dari folder aplikasi:

```bash
git status --short
git pull --ff-only origin main
php artisan down
php artisan migrate --force
php artisan optimize:clear
php artisan view:cache
php artisan up
```

Perubahan ini tidak menambah dependency atau mengubah aset hasil build, sehingga
tidak membutuhkan `npm run build`. Dua migration menambah metadata bon pada
`payments` dan tabel `expense_categories`. Pastikan user PHP dapat menulis ke
disk `local`. Untuk menerima file 5 MB, konfigurasi PHP/web server harus mendukung
`upload_max_filesize` minimal 5M, `post_max_size` minimal 8M, dan batas body request
web server minimal 8 MB. Muat ulang PHP/web server jika konfigurasi itu diubah.

Jika salah satu perintah gagal, selesaikan error sebelum membuka maintenance
mode. Hindari rollback schema setelah bon/kategori baru digunakan; gunakan
backup konsisten database dan file bila perlu memulihkan versi sebelumnya.

## Uji penerimaan

1. Tambah kategori **Akomodasi Kru** di Settings. Pastikan muncul pada form
   expense di invoice.
2. Catat pembayaran sebagian dengan PDF, lalu pembayaran lain dengan JPG/PNG.
   Periksa nominal, sisa tagihan, tanggal, dan unduhan bon di riwayat.
3. Coba file selain format tersebut, file lebih dari 5 MB, serta pembayaran yang
   melebihi saldo. Pastikan muncul error dan tidak ada pembayaran baru.
4. Tambah expense memakai kategori baru. Periksa kategori dan jumlah pada
   Financial Report periode yang sesuai serta ekspor CSV expense.
5. Ubah nama kategori lalu nonaktifkan. Riwayat mengikuti nama baru dan jumlah
   tetap sama; kategori tidak tersedia untuk expense baru, tetapi expense lama
   masih bisa diedit tanpa mengganti kategori.
6. Periksa akses dengan role admin dan role input transaksi tanpa izin mengelola
   kategori. Pastikan menu dan aksi sesuai hak akses.

Tes otomatis menggunakan database SQLite terisolasi dan storage palsu. Perintah
untuk suite fitur ini:

```bash
php -d memory_limit=512M vendor/bin/pest tests/Unit/PaymentReceiptsAndExpenseCategoriesTest.php
```

Uji penerimaan pada database dan konfigurasi upload VPS tetap diperlukan setelah
deploy.
