# Top Product Report V1

Top Product ditampilkan pada halaman **Financial Report** dan mengikuti filter
Year, Month, Business Unit, Event Status, dan Product pada halaman tersebut.

## Aturan perhitungan

- Hanya transaksi dengan `event_status = confirm` yang dihitung.
- Satu quotation/project dihitung sebagai satu deal.
- Invoice DP dan pelunasan untuk quotation yang sama tidak menggandakan jumlah
  deal maupun kuantitas produk.
- Periode deal memakai tanggal invoice confirmed pertama.
- Produk dan kuantitas memakai snapshot `quote_items`. Untuk invoice tanpa
  quotation, laporan memakai `invoice_items`.
- Deal Value dibagi proporsional ke setiap produk berdasarkan nilai item.
- Payment Received adalah seluruh pembayaran yang sudah diterima untuk deal,
  lalu dibagi proporsional ke setiap produk.
- Jika Event Status difilter ke Prospect atau Cancel, Top Product kosong karena
  laporan ini secara khusus mengukur produk yang sudah confirmed.

## Export

Tombol **Export Top Product** menghasilkan CSV berformat UTF-8 dengan pemisah
semicolon agar mudah dibuka di Excel. Export memuat seluruh produk hasil filter,
sedangkan tabel halaman hanya menampilkan 10 peringkat teratas.

## Pemeriksaan lokal

Untuk membuat data transaksi khusus pengujian, jalankan dry run lalu apply:

```bash
php tools/seed_top_product_demo_v1.php
php tools/seed_top_product_demo_v1.php --apply --date=2026-09-16
```

Script hanya dapat berjalan saat `APP_ENV=local`. Data memakai prefix
`DEMO-TOP-`, sehingga dapat dibersihkan tanpa menyentuh transaksi lain:

```bash
php tools/seed_top_product_demo_v1.php --cleanup --apply
```

Hasil yang diharapkan untuk September 2026 dan Business Unit
**Capture It - Photobooth**:

| Rank | Product | Projects | Qty | Deal Value | Received |
| ---: | --- | ---: | ---: | ---: | ---: |
| 1 | DEMO Classic Photobooth | 3 | 3 | Rp15.000.000 | Rp12.500.000 |
| 2 | DEMO 360 Booth | 2 | 2 | Rp14.000.000 | Rp14.000.000 |
| 3 | DEMO Slow Motion | 1 | 1 | Rp9.000.000 | Rp0 |

Sesudah membuat atau membersihkan data demo, jalankan pemeriksaan aplikasi:

```bash
php artisan test --compact tests/Unit/TopProductReportServiceTest.php
./vendor/bin/pint --test \
  packages/Webkul/Admin/src/Services/TopProductReportService.php \
  packages/Webkul/Admin/src/Http/Controllers/Invoice/FinancialReportController.php \
  tests/Unit/TopProductReportServiceTest.php
php artisan optimize:clear
php artisan route:cache
php artisan view:cache
```

Tidak ada migration baru untuk fitur ini.
