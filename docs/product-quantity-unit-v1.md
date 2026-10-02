# Master Product dan satuan transaksi Pcs / Day

Patch ini dibuat dari `main` pada commit `40754ca`, setelah pembaruan Lead owner, alamat, Day, dan filter QR sebelumnya. Terapkan serta uji di **LOKAL Windows dahulu**. VPS baru diperbarui setelah hasil lokal diperiksa dan perubahan digabung ke `main`.

## Perubahan

- Master Product menampilkan nama, SKU, kategori, harga, dan satuan default `Pcs` atau `Day`. Kolom serta tab stok di Master Product disembunyikan. Data stok dan modul Inventory/Assets tetap tersedia.
- Produk pada Lead, Quote, dan Invoice memakai Quantity + Unit. Satuan default mengikuti Master Product, lalu dapat diganti pada setiap baris transaksi.
- Subtotal baris = Quantity × Unit Price. Diskon, pajak, dan adjustment tetap dihitung.
- Invoice Full Payment, DP, Pelunasan, dan PDF membawa satuan dari Quote. Nilai DP/Pelunasan tetap sesuai alokasi tagihannya.
- Laporan Top Product memisahkan jumlah satuan, misalnya `2 Pcs / 3 Day`.
- Aturan edit/read-only Quote yang sudah memiliki Invoice tetap mengikuti aturan aplikasi yang sudah ada. Patch ini tidak membuka kunci dokumen.

## Dokumen lama dan perlengkapan

Migration hanya menambah kolom. Migration tidak menulis ulang kuantitas, harga, total, atau status dokumen lama.

Baris lama yang memakai Day × Qty ditampilkan dengan kuantitas gabungan agar totalnya tetap sama. Contoh:

| Data lama | Tampilan baru | Subtotal |
|---|---|---|
| Qty 2 × Day 3 × Rp500.000 | Quantity 6, Unit Day, Price Rp500.000 | Rp3.000.000 |
| Qty 100 × Day 1 × Rp5.000 | Quantity 100, Unit Pcs, Price Rp5.000 | Rp500.000 |

Saat disimpan ulang, baris memakai bentuk baru. Jumlah perlengkapan fisiknya disimpan terpisah: contoh baris lama di atas tetap memerlukan 2 set, bukan 6 set. Baris baru `3 Day` memakai 1 set template perlengkapan selama 3 hari; `3 Pcs` memakai 3 set.

Produk lama mendapatkan default `Pcs`. Ubah default produk sewaan menjadi `Day` melalui Edit Product. Perubahan default Master Product tidak mengganti satuan dokumen yang sudah tersimpan.

## Penerapan di LOKAL Windows

Simpan file `product-quantity-unit-v1.patch` di folder Downloads. Buka terminal PowerShell VS Code pada project CRM.

1. Periksa pekerjaan lokal:

```powershell
cd C:\Users\Administrator\Documents\laravel-crm-2.2
git status -sb
```

Jika masih ada perubahan kode yang belum disimpan dalam commit, selesaikan atau cadangkan terlebih dahulu. Jangan memakai `reset --hard`, `git clean`, atau `git add .` untuk membersihkan file cache/LSP. Jangan menerapkan patch dua kali.

2. Mulai dari `main` terbaru. Jalankan baris satu per satu, berhenti bila ada error:

```powershell
git fetch origin
git switch main
git pull --ff-only origin main
git switch -c feature/product-quantity-unit-v1
git apply --check "$env:USERPROFILE\Downloads\product-quantity-unit-v1.patch"
git apply "$env:USERPROFILE\Downloads\product-quantity-unit-v1.patch"
```

`git apply --check` yang berhasil biasanya tidak menampilkan output. Bila pemeriksaan gagal karena file berbeda, kirim hasilnya; jangan memaksa penerapan. Jika branch tersebut sudah ada, periksa `git status -sb` sebelum melanjutkan, bukan membuat branch lagi.

3. Cadangkan database lokal melalui fitur Export phpMyAdmin atau alat backup yang biasa digunakan. Kemudian:

```powershell
php artisan migrate
php artisan optimize:clear
php tools/check_sales_units_v1.php
php artisan test --compact tests/Unit/LeadProductDayTest.php
php artisan test --compact tests/Unit/QuoteItemDayEditingTest.php
php artisan test --compact tests/Unit/SalesUnitPersistenceTest.php
php artisan test --compact tests/Unit/SalesUnitInvoiceGenerationTest.php
php artisan test --compact tests/Unit/TopProductReportServiceTest.php
php artisan test --compact tests/Unit/QuoteBillToIdentityTest.php
php artisan test --compact tests/Unit/InvoiceBillToIdentityTest.php
```

Migration yang ditambahkan: `2026_10_01_100000_add_sales_units_to_products_and_document_items`. Tidak ada dependency baru atau perubahan bundle JavaScript/CSS; patch ini tidak memerlukan `npm run build`.

## Tes melalui browser LOKAL

Gunakan data percobaan lokal. Setelah perubahan, refresh dengan Ctrl+F5.

| Langkah | Hasil yang diperiksa |
|---|---|
| Buka Master Product | Tidak ada kolom stok; kategori dan Unit terlihat |
| Buat/edit produk default Day, simpan lalu buka kembali | Kategori dan Unit tersimpan |
| Tambah produk itu ke Lead, Quantity 3, Price 500000 | Unit Day otomatis, subtotal 1500000 |
| Simpan lalu buka kembali Lead | Quantity dan Unit tetap |
| Generate Quote dari Lead, lalu edit dan simpan | Quantity, Unit, dan total terbawa serta tersimpan |
| Tambah produk Pcs, Quantity 100, Price 5000 | Subtotal 500000; tidak dikalikan Day lagi |
| Cetak Quote dan generate Invoice Full Payment | Qty dan Unit sesuai Quote, total serta Bill To tetap benar |
| Dengan Quote percobaan lain, buat DP lalu Pelunasan | Unit tetap; jumlah tagihan sesuai alokasi DP/Pelunasan |
| Buka, simpan, lalu cetak dokumen lama yang masih boleh diedit | Total sebelum/sesudah tetap, diskon dan pajak tidak diterapkan dua kali |
| Periksa Surat Jalan untuk 3 Day | Jumlah perlengkapan mengikuti 1 set template; baris lama mempertahankan jumlah set sebelumnya |

## Batas verifikasi paket

Pemeriksaan yang telah dijalankan: helper perhitungan PHP 8.3, sintaks PHP/Blade, JavaScript perhitungan Quote, render tabel PDF Quote/Invoice, migration dan penyimpanan model dengan SQLite sementara, serta penyalinan item Full Payment/DP/Pelunasan. Lingkungan pengembangan paket tidak memiliki seluruh dependency aplikasi terpasang, sehingga suite Pest aplikasi lengkap dan alur browser CRM masih harus dijalankan di lokal Anda.

## Setelah tes lokal berhasil

Commit hanya file perubahan fitur dan tesnya; jangan memasukkan `.env`, patch unduhan, cache PHPUnit, atau file LSP. Push branch `feature/product-quantity-unit-v1`, lalu buat PR dengan **base `main`**, **compare `feature/product-quantity-unit-v1`**. Review dan merge sebelum update VPS.

Pada VPS, ikuti prosedur deployment repository dan cadangkan database terlebih dahulu. Versi kode ini memerlukan migration baru di atas sebelum melayani request. Jangan menjalankan seed/demo data atau `migrate:fresh` di VPS.

Migration sengaja menolak `down()` yang menghapus kolom karena akan menghilangkan arti satuan transaksi. Jika perlu pemulihan setelah transaksi baru dibuat, gunakan perbaikan maju; pemulihan penuh harus mencocokkan backup database dengan versi kode, bukan sekadar mengembalikan kode lama.
