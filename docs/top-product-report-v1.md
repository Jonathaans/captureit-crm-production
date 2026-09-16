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
