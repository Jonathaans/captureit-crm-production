# Ganti jenis invoice: DP dan Full Payment

Gunakan **Ganti ke Full Payment** pada detail invoice DP ketika pelanggan meminta
satu tagihan penuh dan belum ada pembayaran sah diterima. Invoice pengganti tetap
mengacu **quotation yang sama**. Tidak ada quotation, SPK, Surat Jalan, pembayaran,
atau movement stok baru yang dibuat oleh aksi penggantian.

Alur sebaliknya tersedia lewat **Ganti ke DP** pada Invoice Full UNPAID. Isi
**Transfer netto DP (Rp)** lalu klik **Hitung / Perbarui Pratinjau DP**. Halaman
menampilkan bruto DP, potongan PPh, transfer netto, dan sisa kesepakatan sebelum
konfirmasi. Setelah TOP, jatuh tempo, alasan, dan persetujuan diisi, invoice Full
menjadi VOID dan invoice DP pengganti terbit melalui proforma. Contoh tanpa PPh:
Full Rp10 juta → DP Rp3 juta menghasilkan invoice/piutang aktif Rp3 juta,
penerimaan Rp0, serta sisa belum ditagihkan Rp7 juta. Setelah DP lunas,
proforma/invoice pelunasan tersedia melalui alur biasa. Nominal DP wajib lebih
dari nol dan lebih kecil dari nilai transfer penuh; nilai penuh Quote tetap.

Tombol perhitungan memakai formulir GET dan konfirmasi memakai POST, sehingga
tidak bergantung pada JavaScript. Semua nominal diperiksa ulang saat konfirmasi.

## Alur pengguna

1. Buka invoice DP UNPAID pada event Confirm, lalu **Ganti ke Full Payment**.
2. Tinjau nomor invoice DP, nominal dokumen lama, nilai penuh kesepakatan,
   TOP, dan jatuh tempo pengganti. Isi alasan serta konfirmasi persetujuan pelanggan.
3. Konfirmasi penggantian. Backend membuat proforma penuh dan memindahkannya
   melalui konversi **event sudah deal**, tanpa mencatat penerimaan uang.
4. Invoice DP menjadi **VOID / Dibatalkan—Diganti Invoice Baru**, saldo aktif nol.
   Invoice full berstatus **UNPAID**. Kedua dokumen memiliki tautan silang,
   alasan, waktu, pengguna, dan tautan ke proforma pengganti.

Untuk kesepakatan Rp10 juta dan DP Rp3 juta tanpa pembayaran, dokumen DP asli tetap
Rp3 juta dengan tagihan aktif nol. Invoice pengganti Rp10 juta, bukan Rp7 juta.
Jika kesepakatan memakai PPh gross-up, nilai transfer netto tetap mengikuti
perhitungan PPh yang sudah ada; nominal invoice penuh dan potongannya tampil
terpisah pada pratinjau.

Pembatalan DP, pembuatan proforma, penerbitan invoice, referensi, dan audit berada
dalam satu transaksi. Kegagalan pada langkah mana pun mempertahankan invoice DP
aktif. Mengirim ulang konfirmasi yang sama mengembalikan invoice pengganti yang
sudah ada, tanpa dokumen atau uang masuk kedua. Ini berbeda dari **Cancel** pada
status event, yang tetap berarti pembatalan event.

## Ketentuan dan sumber nilai

- Wajib memiliki izin `invoices.view`, `invoices.revise`, dan `invoices.generate`.
  Tidak ada bypass berdasarkan nama role; izin payment tidak diperlukan karena
  penggantian tidak mencatat pembayaran.
- Hanya invoice jenis DP atau Full, status `unpaid`, event `confirm`, belum
  mempunyai pengganti, serta memiliki saldo konsisten dengan tagihan yang terbit.
  Invoice aktif hasil penggantian sebelumnya dapat diganti kembali jika rantai
  referensi dan log penggantian valid; misalnya DP → Full → DP.
- Tidak boleh ada pembayaran aktif, `paid_amount` bukan nol, invoice aktif lain,
  atau proforma issued lain pada quotation yang sama. Pembayaran yang masuk
  sesudah pratinjau menyebabkan konfirmasi ditolak tanpa membatalkan DP.
- Periode penerbitan DP dan periode penerbitan pengganti harus terbuka. Pengganti
  terbit pada tanggal konfirmasi; jatuh tempo wajib tanggal itu atau sesudahnya.
- Gunakan snapshot kesepakatan pada proforma asal, termasuk produk, nilai penuh,
  Bill To, dan pajak. Untuk DP legacy tanpa proforma, quotation arsip hanya boleh
  digunakan bila totalnya cocok dengan `quote_total_snapshot`. Ketidakcocokan atau
  snapshot total yang hilang ditolak untuk ditinjau melalui Revisi Transaksi.
  Invoice Full legacy tanpa proforma dapat memakai quotation yang total dan
  PPh-nya cocok dengan dokumen Full; nominal Full menjadi rujukan bila snapshot
  total legacy belum terisi.
- Konfirmasi memeriksa ulang seluruh posisi billing dan penguncian periode.
  Perubahan setelah pratinjau mengharuskan pengguna meninjau data terbaru.

## Operasional dan laporan

Nomor, item, dan nominal asli DP dipertahankan. Proforma DP asal tetap converted
dan tetap menunjuk invoice DP lama sebagai riwayat. Quote tidak ditulis ulang.

SPK dan Surat Jalan lama tetap menunjuk dokumen asal. Membuka SPK atau daftar
Surat Jalan dari invoice full menemukan dokumen proyek yang sama; meminta SPK
dari invoice full mengembalikan SPK yang sudah ada. Tidak ada pengulangan pekerjaan
atau pengeluaran stok. Penyelesaian SPK, return Surat Jalan, dan pemrosesan PO
yang sudah ada tetap dapat berjalan sesuai aturan status/arsip masing-masing.
Dokumen operasional final tetap read-only. Invoice DP VOID tidak menerima payment,
expense, SPK, atau Surat Jalan baru melalui alur invoice.

Laporan resmi mengecualikan nominal DP VOID dari total invoice, piutang, penerimaan,
Top Product, dashboard, serta ekspor. Posisi proyek tetap menjumlahkan biaya
historis pada DP lama dan biaya invoice full tepat satu kali. Misalnya biaya
Rp6 juta pada DP lama menghasilkan posisi proyek: invoice Rp10 juta, diterima
Rp0, piutang Rp10 juta, biaya Rp6 juta, margin Rp4 juta sebelum potongan PPh.
Biaya tetap memakai tanggal transaksi aslinya, termasuk bila penggantian terjadi
pada bulan berbeda.

Aturan yang sama berlaku untuk Full → DP. Full VOID keluar dari laporan dan
CSV, DP aktif dihitung sesuai tanggal penerbitan barunya. Jika penggantian
terjadi pada bulan berbeda, nilai Full keluar dari bulan terbit asal dan DP
masuk pada bulan pengganti; biaya tetap pada tanggal transaksi aslinya. SPK,
Surat Jalan, dan biaya historis tetap ditemukan sepanjang rantai penggantian,
termasuk dari invoice pelunasan DP baru. Invoice lain yang tidak tersambung
dalam rantai tetap terpisah.

Setiap penggantian menyimpan `invoice_replacements` dan satu versi
`commercial_document_revisions` dalam transaksi yang sama. **Riwayat revisi**
pada dokumen aktif menunjukkan kondisi sebelum/sesudah, termasuk Full/DP lama
menjadi VOID dan nomor, jenis, nominal, serta status invoice pengganti.
Detail invoice menampilkan tautan kedua dokumen, alasan, petugas, waktu, dan
nominal perubahan. Klik ulang tidak menambah invoice atau log kedua. Kegagalan
penyimpanan salah satu log membatalkan seluruh penggantian.

## Deploy dan pengujian

Tambahan Full → DP memakai schema revisi/penggantian yang sudah ada. Jika fitur
DP → Full sudah terpasang, pembaruan ini cukup `git pull --ff-only origin main`,
`php artisan optimize:clear`, dan reload PHP-FPM; tidak ada migration, dependency,
atau build frontend baru untuk revisi ini.

Setelah merge, ikuti prosedur backup dan maintenance VPS pada README. Deploy kode
bersama migration `2026_10_22_120000_create_invoice_replacements`, jalankan
`php artisan migrate --force`, lalu segarkan cache aplikasi dan Blade. Tidak ada
dependency atau JavaScript baru. Gunakan build Admin yang biasa bila deployment
menjalankan build aset.

Migration menambah dua referensi invoice dan tabel audit `invoice_replacements`.
Rollback migration ditolak setelah audit terisi agar riwayat tidak hilang.
Rollback kode harus mempertahankan tabel, referensi, dan invoice yang sudah terbit.

Regression berada di `tests/Unit/ProformaInvoiceWorkflowTest.php`, grup
`unpaid DP replacement with full payment`: quotation tetap satu, nilai penuh,
UNPAID tanpa penerimaan, snapshot kesepakatan, biaya proyek, SPK/SJ bersama,
klik ulang, rollback setiap tahap, pembayaran sesudah preview, konflik billing,
periode tertutup, legacy snapshot, ACL, tampilan riwayat/PDF, serta migration.

```bash
php vendor/bin/pest tests/Unit/ProformaInvoiceWorkflowTest.php --compact
php artisan view:cache
php vendor/bin/pint --test --dirty
git diff --check
```

Verifikasi revisi Full → DP: seluruh 599 unit test (5.554 assertion) dan 9 test
JavaScript pratinjau lulus. Unit test dijalankan dengan application key khusus
pengujian dan batas memori 2 GB untuk rendering PDF. Pint, kompilasi Blade, dan
pemeriksaan whitespace lulus. Validator skills masih gagal pada baseline karena
`.github/skills` tidak tersedia. Pengujian memakai SQLite in-memory; belum ada
uji interaktif pada VPS atau uji konkurensi MySQL live.
