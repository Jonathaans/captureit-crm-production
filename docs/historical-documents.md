# Input invoice dan quotation lama

Fitur **Input Data Lama** memasukkan dokumen yang sudah diterbitkan sebelum memakai CRM. Nomor asli dan tanggal dokumen dapat diisi manual. Invoice penuh dapat dibuat tanpa quotation; DP dan pelunasan tetap merujuk quotation agar sisa tagihannya benar.

## Update aplikasi

Perubahan ini dibangun di atas `main` yang sudah mencakup PR29. Setelah perubahan digabung:

```powershell
git pull --ff-only origin main
php artisan migrate
php artisan optimize:clear
```

Jalankan migrasi sebelum membuka halaman Quotation atau Input Data Lama. Migrasi menambahkan riwayat input, tanggal quotation asli, dan registrasi nomor dokumen, serta memperbesar kapasitas nominal yang sebelumnya terbatas di bawah Rp100 juta. Migrasi mempertahankan data yang sudah ada; rollback otomatis sengaja ditolak untuk menjaga dokumen dan riwayat nomor.

Administrator dapat langsung mengakses fitur. Untuk role khusus, aktifkan izin **Input Data Lama (Quote, Invoice, Pembayaran)** di pengaturan role. Pengguna hanya melihat batch input miliknya. Izin melihat invoice/quotation tetap diperlukan untuk membuka hasilnya dari tautan dokumen.

## Input satu invoice

1. Buka **Invoice → Input Data Lama → Input manual**. Jenis dokumen awal adalah Invoice.
2. Isi nomor asli, tanggal invoice, pelanggan, sales, unit bisnis, nama proyek, dan jatuh tempo. Bulan laporan mengikuti **tanggal dokumen**, bukan bulan saat input dilakukan.
3. Pilih jenis tagihan. Invoice penuh boleh tanpa quotation. DP membutuhkan nomor quotation. Pelunasan membutuhkan nomor quotation dan nomor invoice DP terkait.
4. Isi item, jumlah, hari, serta total setiap baris. Produk master opsional untuk invoice, wajib untuk quotation.
5. Isi diskon, pajak, penyesuaian, dan total akhir sesuai dokumen asli. Total akhir harus sama dengan jumlah item dikurangi diskon, ditambah pajak dan penyesuaian.
6. Jika sudah menerima pembayaran, tambahkan setiap penerimaan dengan nominal, tanggal asli, metode, dan referensinya. Status unpaid/partial/paid dihitung otomatis.
7. Klik **Periksa data**, buka rincian preview, lalu centang konfirmasi dan simpan. Preview belum membuat invoice atau pembayaran. Klik simpan berulang pada batch yang sudah selesai tidak membuat dokumen ganda.

Contact, Sales, dan Produk harus sudah tersedia di CRM. Untuk pelanggan tanpa perusahaan, pilih tampilan tagihan **Person**. Kode proyek boleh diisi sesuai dokumen lama; jika kosong, CRM membuat kode `LEG-…`. Invoice yang terhubung ke quotation mengikuti kode proyek dan unit bisnis quotation tersebut.

Contoh: invoice tanggal 10 Agustus senilai Rp10 juta, pembayaran tanggal 2 September Rp3 juta, diinput pada Oktober. Nilai invoice muncul pada periode Agustus dan uang masuk pada September, dengan sisa tagihan Rp7 juta. Tanggal input tetap Oktober untuk riwayat. Filter status event pada laporan tetap berlaku.

## Import Excel

Unduh template dari halaman Input Data Lama. Workbook memuat:

| Sheet | Isi |
| --- | --- |
| Dokumen | Satu baris per invoice/quotation: nomor, tanggal, pelanggan, sales, nilai, serta relasi tagihan. |
| Item | Satu atau beberapa baris per dokumen. `type` dan `number` harus cocok dengan sheet Dokumen. |
| Pembayaran | Satu baris per penerimaan. `number` harus merujuk invoice dalam file yang sama. |
| Petunjuk dan Master | Panduan serta ID Contact, Sales, dan Produk yang tersedia. Sheet ini tidak diimpor. |

Gunakan `type` bernilai `invoice` atau `quote`, dan tanggal `YYYY-MM-DD` atau `DD/MM/YYYY`. Tanggal Excel juga diterima. Nominal harus berupa angka tanpa pemisah ribuan. Ganti rumus dengan hasilnya melalui **Paste Values**.

Anda dapat memakai nama kolom sendiri lalu mencocokkannya setelah unggah. Bila memakai nama Contact/Sales atau SKU sebagai pengganti ID, nilainya harus cocok dengan tepat dan unik. Untuk CSV satu sheet, petakan kolom Dokumen dan Item ke sheet yang sama, satu dokumen dan satu item per baris; gunakan XLSX untuk rincian beberapa item/pembayaran.

Preview memisahkan nilai quotation dan invoice supaya tidak terhitung dua kali. Urutan baris bebas: sistem memproses quotation lebih dulu, kemudian invoice DP, lalu pelunasan. Setelah preview, pemeriksaan diulang saat simpan; jika satu dokumen gagal, seluruh batch tidak disimpan.

## Aturan penting

- Maksimum 200 dokumen per batch, 100 item dan 100 pembayaran per dokumen; file maksimum 2 MB, 8 sheet data, 2.500 baris data dan 60 kolom per sheet.
- Nomor asli dipertahankan, maksimum 30 karakter. Pemeriksaan duplikat mengabaikan perbedaan huruf besar/kecil, spasi, dan tanda hubung. Nomor invoice yang pernah dikoreksi/void tetap dianggap terpakai. Nomor otomatis berikutnya melewati nomor manual yang memakai pola bulan yang sama.
- Tanggal dokumen dan pembayaran tidak boleh di masa depan. Periode invoice dan pembayaran yang sudah ditutup harus dibuka melalui menu Periode Keuangan sebelum input.
- Total pembayaran tidak boleh melebihi invoice. DP harus lebih kecil dari quotation; pelunasan harus sesuai sisa tagihan. Proforma aktif pada quotation harus diselesaikan terlebih dahulu agar tidak terjadi tagihan ganda.
- Preview berlaku 24 jam. Setelah itu input/unggah ulang untuk memeriksa data terbaru.
- Input data lama tidak otomatis membuat proforma, SPK, DO, jadwal event, atau mengirim email. Dokumen tetap tersedia di daftar biasa dan dapat dicetak sebagai PDF.

## Koreksi nama pada PDF

Cetak ulang PDF setelah mengubah nama Person/Company di Contact. Invoice, proforma, dan quotation memakai nama terbaru dari Contact yang terkait, dengan nama tersimpan sebagai cadangan jika Contact sudah tidak tersedia. Perubahan nama tidak mengubah nomor, nominal, pembayaran, atau penerima invoice menjadi Contact lain pada quotation. File PDF yang sudah diunduh sebelumnya tidak berubah.

## Verifikasi pengembang

Pengujian memakai database SQLite terpisah dan data sintetis. Jalankan pengujian input historis, billing, laporan, dan regresi alur dari PR29:

```bash
php vendor/bin/pest tests/Unit/HistoricalDocumentsTest.php tests/Unit/ProformaInvoiceWorkflowTest.php tests/Unit/FinancialReportExportTest.php tests/Unit/QuoteCreationWorkflowTest.php
```

Migrasi perubahan kapasitas nominal perlu dijalankan pada database MySQL/MariaDB instalasi tujuan; tes SQLite tidak memverifikasi batas DECIMAL mesin tersebut.

Hasil verifikasi: 126 tes terkait lulus (1.111 assertions). Uji browser meliputi input manual, template XLSX, mapping, preview, simpan invoice/pembayaran, form ponsel, dan PDF. Contoh invoice Rp250 juta tersimpan lunas; contoh Rp10 juta dengan pembayaran Rp3 juta tersimpan partial dan bersisa Rp7 juta.

Full unit suite menghasilkan 280 lulus, 16 risky, dan 2 gagal pada pemeriksaan teks lama `InventoryQrLabel20x10LayoutTest` serta `LeadContactQuickCreateTest`. Kedua kegagalan juga direproduksi dengan sumber dari `main` setelah PR29: selector QR sudah berubah menjadi pilihan beberapa item, dan prefill alamat lead sudah dipindahkan ke service. Fitur input invoice ini tidak mengubah kedua alur tersebut. Validasi skill repository terhalang karena direktori `.github/skills` yang dirujuk `AGENTS.md` tidak tersedia.
