# Void invoice salah input setelah event selesai

Gunakan `crm:invoice-void` jika seluruh pembayaran pada invoice adalah salah input
dan **tidak ada uang yang benar-benar diterima**, sedangkan tagihan juga harus
dibatalkan untuk diganti dengan penawaran/invoice baru. Ini bukan proses refund,
pembatalan event, atau pembalikan stok.

## Hasil koreksi

- Invoice menjadi `void`; `paid_amount` dan `balance_due` menjadi nol.
- Nomor, nominal asli, item invoice, quote, dan `event_status` tetap tersimpan.
- Baris pembayaran tetap tersimpan sebagai bukti salah input. Laporan penerimaan
  mengecualikan seluruh pembayaran pada invoice VOID, termasuk pada bulan lama.
- Financial Report, export, Top Product, ringkasan invoice, dashboard Finance Sales,
  dan komisi mengecualikan invoice VOID. Daftar invoice tetap menampilkannya.
- Alasan, pengguna CRM, waktu, snapshot sebelum koreksi, dan hasil disimpan dalam
  `crm_data_corrections` dengan jenis `invoice_void`.
- SPK, DO, item operasional, alokasi, asset, dan movement tidak diubah/dihapus.
  Hubungan ke invoice asli tetap ada. Detail dan PDF diberi tanda VOID.
- Input pembayaran, expense, PO, SPK, dan DO baru pada invoice VOID ditolak.

Perubahan ini memakai tabel yang sudah ada; tidak ada migration atau perubahan
frontend JavaScript baru. Aksi koreksi tersedia melalui console untuk operator
VPS, belum melalui tombol admin sales.

## Syarat sebelum apply

Preview menampilkan `blockers`. Apply ditolak bila ada salah satu kondisi berikut:

- SPK belum Completed/Cancelled, DO belum Returned/Cancelled, atau masih ada
  alokasi aktif (`out`, `return_pending`, `allocated`, atau `picked`).
- Invoice terkait expense, PO, invoice lain pada quote yang sama, pelunasan,
  atau proforma pelunasan/aktif yang bergantung pada invoice ini.
- Periode tanggal terbit invoice atau tanggal pembayaran sudah dikunci.
- Total baris pembayaran tidak cocok dengan `paid_amount`.
- Data berubah sesudah preview, alasan/actor tidak valid, atau invoice sudah
  dikoreksi sebelumnya.

Barang yang sudah kembali secara fisik harus dicatat lewat Return/Check-In dan
inspection sesuai kondisi nyata. Setelah selesai, tandai DO Returned dan SPK
Completed bila statusnya belum sesuai. Jangan membatalkan SPK/DO yang memang
sudah terlaksana hanya agar koreksi keuangan bisa dijalankan.

## Preview dan apply

Setelah merge/deploy, jalankan dari folder aplikasi VPS:

```bash
php artisan crm:invoice-void 'NOMOR INVOICE'
```

Perintah tanpa `--apply` hanya membaca. Tinjau invoice, jumlah pembayaran,
SPK/DO, `blockers`, dan `fingerprint`. Hasil `crm:invoice-correct` lama tidak dapat
dipakai sebagai fingerprint command ini. Jangan memakai `crm:invoice-correct
--apply` untuk invoice yang memiliki SPK/DO.

Sebelum apply, buat backup database yang bisa dipulihkan dan gunakan maintenance
window singkat. Jalankan preview lagi, pastikan `blockers` kosong, lalu isi
fingerprint terbaru, ID pengguna CRM yang bertanggung jawab, dan alasan nyata:

```bash
php artisan crm:invoice-void 'NOMOR INVOICE' \
  --apply \
  --expect='FINGERPRINT_DARI_PREVIEW_TERBARU' \
  --actor=ID_PENGGUNA_CRM \
  --reason='Salah input pembayaran tanpa uang diterima; nominal quote perlu diganti.' \
  --no-money-received
```

`--no-money-received` menyatakan seluruh pembayaran pada invoice ini fiktif/salah
input. Jangan gunakan untuk transaksi yang benar-benar menerima uang, pembayaran
campuran benar/salah, atau pemindahan dana ke invoice lain. Tidak ada bypass untuk
periode terkunci atau dependensi yang ditolak. Kegagalan transaksi membatalkan
perubahan invoice dan audit bersama-sama.

Sesudah apply, cek status VOID, penerimaan bulan pembayaran, tagihan bulan terbit,
dan riwayat SPK/DO. Simpan `audit_id` yang ditampilkan.

## Dokumen pengganti

Admin membuat quote revisi baru dengan nominal benar. Cantumkan nomor invoice,
SPK, dan DO lama pada keterangan quote sebagai referensi koreksi. Lanjutkan lewat
proforma lalu konversi sesuai kesepakatan pembayaran. Jangan mencatat DP diterima
jika uang belum masuk; gunakan konversi konfirmasi tanpa pembayaran yang tersedia
untuk tagihan sesuai TOP.

Invoice pengganti tidak otomatis memindahkan hubungan SPK/DO lama atau membuat
dokumen operasional baru. Pertahankan SPK/DO lama sebagai bukti pelaksanaan event;
jangan membuat SPK/DO baru untuk mengulang pekerjaan atau pergerakan stok yang
sama. Referensi pengganti pada tahap ini berupa keterangan dokumen, bukan relink
otomatis yang mengubah dokumen operasional yang sudah selesai.

## Validasi

Regression pada `ProformaInvoiceWorkflowTest` mencakup invoice terbit Oktober
dengan salah input penerimaan September, 1 SPK selesai dan 2 DO returned,
laporan/export bulanan dan tahunan, invoice baru melalui proforma, tidak ada stok
atau pembayaran historis yang dihapus, penolakan dependensi/closed period,
preview kedaluwarsa, pengulangan apply, audit actor, rollback, arsip, dan PDF VOID.
