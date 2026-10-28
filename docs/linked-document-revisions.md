# Revisi transaksi: Quote, Proforma, Invoice

Gunakan **Revisi Transaksi** untuk mengubah kesepakatan yang sudah mempunyai proforma atau invoice. Form yang sama dapat dibuka dari ketiga dokumen. Nominal, produk, jumlah, satuan, diskon, pajak, penyesuaian, tanggal event, lokasi, Bill To, alamat, deskripsi, dan ketentuan pembayaran disimpan bersama ke seluruh dokumen aktif yang terhubung ke quotation tersebut.

## Cara memakai

1. Aktifkan izin **Invoices → Revisi Transaksi (Quote, Proforma, Invoice)** pada role admin/finance yang berwenang. Administrator dengan seluruh izin sudah mempunyai akses. Izin melihat atau mencatat pembayaran saja tidak memberikan hak revisi.
2. Buka invoice atau proforma, lalu klik **Revisi Transaksi**. Edit quotation yang sudah terhubung juga mengarah ke form ini bagi pengguna yang berizin.
3. Ubah rincian nilai **penuh transaksi**. Untuk transaksi DP, nominal DP tersedia terpisah; jangan memasukkan harga produk sebesar DP.
4. Periksa nilai sebelum/sesudah dan sisa tagihan setiap dokumen, isi alasan, lalu simpan.
5. Cetak ulang PDF dokumen yang direvisi. File PDF yang pernah diunduh tidak berubah otomatis. Buka ulang Financial Report/ekspor untuk melihat angka terbaru.

## Revisi Proforma DP menjadi Full

Buka **Proforma DP → Revisi Transaksi / DP ke Full → Jenis tagihan proforma → Full — 100% nilai transaksi**. Periksa pratinjau nilai, isi alasan, lalu simpan. Contoh: penawaran Rp10 juta dengan Proforma DP Rp4 juta berubah menjadi Proforma Full Rp10 juta pada nomor proforma yang sama. Bila rincian transaksi juga direvisi menjadi Rp12 juta, nominal Full mengikuti Rp12 juta. PPh/gross-up, diskon, pajak, produk, dan TOP mengikuti perhitungan revisi yang sama.

Pilihan Full mengabaikan pembagian DP dan menampilkan 100% total transaksi. Jenis, nominal, dan PDF proforma ikut berubah; nomor, tanggal terbit, jatuh tempo, serta status menunggu konversi dipertahankan. Riwayat memperlihatkan DP → Penuh beserta nilai sebelum/sesudah dan petugasnya. Revisi ini belum menerbitkan invoice atau mencatat penerimaan. Saat kemudian dikonversi, invoice mengikuti jenis Full dan nominal terbaru; tidak ada tagihan DP atau pelunasan tambahan untuk nilai yang sama.

Perubahan jenis tersedia untuk Proforma DP atau Full yang masih issued, belum menjadi invoice, dan menjadi satu-satunya dokumen tagihan aktif pada quotation tersebut. Proforma yang sudah dikonversi memakai alur penggantian dari invoice terkait; proforma asal tetap disimpan sebagai riwayat. Proforma pelunasan tidak memakai pilihan ini. Revisi nominal biasa tetap mempertahankan jenis yang dipilih. Hak revisi, pemeriksaan periode terkunci, versi formulir, dan penyimpanan audit tetap berlaku.

## Revisi Full menjadi DP

Buka **Proforma Full → Revisi Transaksi / Full ke DP → Jenis tagihan proforma → DP**, lalu isi **Transfer netto DP (Rp)**. Contoh kesepakatan Rp10 juta tanpa PPh: masukkan Rp3 juta agar proforma menjadi DP Rp3 juta, dengan sisa kesepakatan Rp7 juta. Harga produk tetap menunjukkan nilai penuh transaksi. Jika gross-up aktif, bruto DP dan PPh dihitung dari transfer netto tersebut. Nomor, tanggal, serta status proforma tetap; riwayat mencatat Penuh → DP dan nominal sebelum/sesudahnya. Proforma belum menambah tagihan pada Financial Report sampai dikonversi menjadi invoice.

Untuk **Invoice Full**, gunakan **Ganti ke DP → isi Transfer netto DP → Hitung / Perbarui Pratinjau DP → isi TOP, jatuh tempo, alasan dan konfirmasi**. Alur ini berlaku pada invoice UNPAID dengan event Confirm, tanpa pembayaran aktif atau dokumen tagihan aktif lain, serta periode terbuka. Invoice Full lama menjadi VOID, dan invoice DP baru diterbitkan dari proforma pengganti pada quotation yang sama. Invoice yang sudah menerima pembayaran harus menyelesaikan koreksi pencatatan melalui alur yang sesuai terlebih dahulu; perubahan jenis tidak memindahkan atau menghapus penerimaan sah.

Financial Report/CSV kemudian menghitung invoice DP aktif, bukan Full lama ditambah DP baru. Pada contoh Rp10 juta menjadi DP Rp3 juta, total invoice dan piutang aktif menjadi Rp3 juta, uang diterima tetap Rp0; sisa Rp7 juta baru ditagihkan melalui pelunasan setelah DP lunas. Biaya aktual proyek tetap dihitung. Log penggantian dan **Riwayat revisi** mencatat pengguna, waktu, alasan, jenis/nominal lama, VOID, serta invoice DP pengganti. Lihat [panduan penggantian invoice](invoice-dp-full-replacement.md) untuk ketentuan dan dampak lintas periode.

## TOP dan alamat

- **TOP / Ketentuan pembayaran** pada form revisi mengambil nilai dari dokumen yang dibuka. Contoh: jika Quote berisi `7 Days` dan Proforma berisi `After Event`, revisi dari Proforma menampilkan `After Event`. Setelah disimpan, TOP baru diterapkan ke Quote, semua Proforma aktif, dan Invoice aktif terkait, termasuk hasil cetaknya.
- TOP mendukung beberapa baris hingga 2.000 karakter, sama dengan saat membuat Proforma. Tanggal jatuh tempo tetap terpisah dan tidak dihitung ulang dari teks TOP.
- Kolom teks **Address/Alamat** pada komponen alamat bersama (kontak, perusahaan, Lead, Quote) dan Revisi Transaksi tidak memiliki batas jumlah karakter di aplikasi. Alamat beberapa baris disimpan lengkap dan diteruskan ke dokumen terkait. Ketentuan kolom lokasi lain dan kewajiban mengisi alamat pada form yang membutuhkannya tetap berlaku.
- Revisi TOP atau alamat saja tidak mengubah nilai tagihan, riwayat pembayaran, atau angka Financial Report.

## Ringkasan PDF untuk pelanggan

PDF Quote, Invoice, dan cetak/Simpan PDF Proforma hanya menampilkan **Sub Total, Discount, dan GRAND TOTAL** pada ringkasan nilai. Baris Tax, Adjustment, rincian gross-up/PPh, transfer netto, total kontrak/sisa kontrak, serta ringkasan pembayaran internal tidak dicetak. Subtotal, diskon, dan nilai akhir tetap memakai kesepakatan yang tersimpan; pajak, penyesuaian, dan gross-up tetap termasuk dalam perhitungan Grand Total meskipun rinciannya disembunyikan.

Grand Total Proforma DP/pelunasan memakai nominal tagihan proforma tersebut, bukan keseluruhan Quote. Untuk tagihan penuh, subtotal dan diskon berasal dari snapshot Quote; DP/pelunasan mengikuti porsi tagihan seperti invoice hasil konversinya. Form internal, pembayaran, PPh, balance, dan Financial Report tetap memakai perhitungan yang sama. PDF lama perlu dicetak/diunduh ulang untuk memakai tampilan ini.

## Dampak pada laporan dan pembayaran

- Financial Report dan CSV membaca nilai invoice aktual yang diperbarui: invoiced, piutang, serta estimasi laba mengikuti revisi. Penerimaan kas dan pengeluaran tetap mengikuti transaksi pembayaran/pengeluaran yang sudah dicatat.
- Contoh invoice Rp10 juta dengan pembayaran Rp3 juta: revisi menjadi Rp12 juta menghasilkan piutang Rp9 juta; revisi menjadi Rp8 juta menghasilkan piutang Rp5 juta. Riwayat pembayaran tetap Rp3 juta.
- Contoh DP lunas Rp4 juta dan pelunasan Rp6 juta: total transaksi direvisi dari Rp10 juta menjadi Rp12 juta. DP tetap Rp4 juta, pelunasan menjadi Rp8 juta. Total invoice di laporan menjadi Rp12 juta tanpa menggandakan penerimaan.
- Jika pelunasan belum diterbitkan, invoice DP tetap sebesar DP. Sisa belum ditagihkan berubah dan proforma/invoice pelunasan berikutnya memakai kesepakatan terbaru. Quote/proforma saja belum menambah invoiced pada laporan.
- Invoice penuh yang sudah lunas dapat bertambah nilainya; status berubah menjadi PARTIAL dengan sisa tagihan baru. Revisi tidak boleh menurunkan tagihan di bawah pembayaran aktif. Transfer netto DP yang sudah lunas tetap. Jika Gross-up PPh diaktifkan, bruto DP menyesuaikan potongan PPh tanpa mengubah penerimaan asli. Lihat [panduan gross-up](pph-gross-up.md).
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

Migrasi `2026_10_16_070000_expand_commercial_payment_terms` memperbesar penyimpanan TOP pada Quote dan Invoice menjadi `TEXT`. Rollback ke batas lama 100 karakter ditolak jika masih ada TOP yang lebih panjang, agar data tidak terpotong. Alamat sudah memakai penyimpanan JSON sehingga tidak membutuhkan migrasi tambahan.

## Pengujian

```bash
php vendor/bin/pest tests/Unit/CommercialRevisionsTest.php tests/Unit/ProformaInvoiceWorkflowTest.php tests/Unit/QuoteCreationWorkflowTest.php tests/Unit/HistoricalDocumentsTest.php tests/Unit/FinancialReportExportTest.php tests/Unit/SalesUnitInvoiceGenerationTest.php tests/Unit/InvoiceBillToIdentityTest.php --compact
```

Pengujian mencakup perubahan nominal dari ketiga pintu masuk, laporan/CSV, DP dan pelunasan yang sudah maupun belum terbit, pembayaran setelah revisi, detail dan cetak dokumen, validasi, hak akses, riwayat, form kedaluwarsa, serta rollback ketika pencatatan riwayat gagal. Suite database menggunakan SQLite; uji penerimaan di staging pada database produksi tetap menjadi bagian deploy normal.
