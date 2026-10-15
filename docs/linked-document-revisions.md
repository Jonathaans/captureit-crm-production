# Revisi transaksi: Quote, Proforma, Invoice

Gunakan **Revisi Transaksi** untuk mengubah kesepakatan yang sudah mempunyai proforma atau invoice. Form yang sama dapat dibuka dari ketiga dokumen. Nominal, produk, jumlah, satuan, diskon, pajak, penyesuaian, tanggal event, lokasi, Bill To, alamat, deskripsi, dan ketentuan pembayaran disimpan bersama ke seluruh dokumen aktif yang terhubung ke quotation tersebut.

## Cara memakai

1. Aktifkan izin **Invoices → Revisi Transaksi (Quote, Proforma, Invoice)** pada role admin/finance yang berwenang. Administrator dengan seluruh izin sudah mempunyai akses. Izin melihat atau mencatat pembayaran saja tidak memberikan hak revisi.
2. Buka invoice atau proforma, lalu klik **Revisi Transaksi**. Edit quotation yang sudah terhubung juga mengarah ke form ini bagi pengguna yang berizin.
3. Ubah rincian nilai **penuh transaksi**. Untuk transaksi DP, nominal DP tersedia terpisah; jangan memasukkan harga produk sebesar DP.
4. Periksa nilai sebelum/sesudah dan sisa tagihan setiap dokumen, isi alasan, lalu simpan.
5. Cetak ulang PDF dokumen yang direvisi. File PDF yang pernah diunduh tidak berubah otomatis. Buka ulang Financial Report/ekspor untuk melihat angka terbaru.

## Dampak pada laporan dan pembayaran

- Financial Report dan CSV membaca nilai invoice aktual yang diperbarui: invoiced, piutang, serta estimasi laba mengikuti revisi. Penerimaan kas dan pengeluaran tetap mengikuti transaksi pembayaran/pengeluaran yang sudah dicatat.
- Contoh invoice Rp10 juta dengan pembayaran Rp3 juta: revisi menjadi Rp12 juta menghasilkan piutang Rp9 juta; revisi menjadi Rp8 juta menghasilkan piutang Rp5 juta. Riwayat pembayaran tetap Rp3 juta.
- Contoh DP lunas Rp4 juta dan pelunasan Rp6 juta: total transaksi direvisi dari Rp10 juta menjadi Rp12 juta. DP tetap Rp4 juta, pelunasan menjadi Rp8 juta. Total invoice di laporan menjadi Rp12 juta tanpa menggandakan penerimaan.
- Jika pelunasan belum diterbitkan, invoice DP tetap sebesar DP. Sisa belum ditagihkan berubah dan proforma/invoice pelunasan berikutnya memakai kesepakatan terbaru. Quote/proforma saja belum menambah invoiced pada laporan.
- Invoice penuh yang sudah lunas dapat bertambah nilainya; status berubah menjadi PARTIAL dengan sisa tagihan baru. Revisi tidak boleh menurunkan tagihan di bawah pembayaran aktif. Nominal DP yang sudah lunas tetap.
- Nomor, tanggal terbit, jatuh tempo, identitas proyek, dan riwayat konversi dokumen dipertahankan. Mengubah tanggal event tidak memindahkan invoice ke bulan laporan lain.

## Konsistensi dan riwayat

Penyimpanan berlangsung dalam satu transaksi database dengan kunci dokumen dan pemeriksaan versi. Bila ada perubahan/pembayaran sejak form dibuka, pengguna harus memeriksa data terbaru. Kegagalan pada satu dokumen atau riwayat membatalkan seluruh revisi.

Riwayat mencatat pelaku, waktu, alasan, dokumen asal, serta data sebelum dan sesudah perubahan. Dokumen VOID, dibatalkan, atau diarsipkan tetap menjadi riwayat. Periode dokumen atau pembayaran yang sudah ditutup menolak revisi. Form edit lama tidak bisa menyimpan perubahan sepihak pada dokumen terhubung.

Revisi mencakup dokumen komersial. SPK, Surat Jalan, dan PO perlu ditinjau terpisah bila produk atau jadwal operasional berubah. ID item invoice yang masih dipakai dipertahankan; penghapusan item yang terhubung ke PO ditolak agar hubungan dokumen tidak terputus. Invoice lama tanpa quotation tetap memakai editor invoice yang sudah ada.

## Pemasangan setelah merge

Jalankan prosedur backup/deploy aplikasi yang biasa, lalu dari folder aplikasi:

```bash
git pull --ff-only origin main
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
```

Migrasi `2026_10_15_090000_add_commercial_document_revisions` menambahkan tabel riwayat dan kolom `invoice_items.quote_item_id`. Migrasi harus selesai sebelum aplikasi melayani penulisan dokumen. Rollback migrasi ditolak untuk mempertahankan riwayat; gunakan migrasi koreksi bila perlu. Perubahan UI memakai Blade dan JavaScript di halaman sehingga tidak membutuhkan build aset baru untuk fitur ini.

## Pengujian

```bash
php vendor/bin/pest tests/Unit/CommercialRevisionsTest.php tests/Unit/ProformaInvoiceWorkflowTest.php tests/Unit/QuoteCreationWorkflowTest.php tests/Unit/HistoricalDocumentsTest.php tests/Unit/FinancialReportExportTest.php tests/Unit/SalesUnitInvoiceGenerationTest.php tests/Unit/InvoiceBillToIdentityTest.php --compact
```

Pengujian mencakup perubahan nominal dari ketiga pintu masuk, laporan/CSV, DP dan pelunasan yang sudah maupun belum terbit, pembayaran setelah revisi, detail dan cetak dokumen, validasi, hak akses, riwayat, form kedaluwarsa, serta rollback ketika pencatatan riwayat gagal. Suite database menggunakan SQLite; uji penerimaan di staging pada database produksi tetap menjadi bagian deploy normal.
